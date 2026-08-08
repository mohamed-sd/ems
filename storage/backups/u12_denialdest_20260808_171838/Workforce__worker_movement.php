<?php
/** EQUIP-OPE-S04 — 8.11 التحرّك والوصول + 8.12 النقل بين المشاريع (موحّدة). Bolt-on. */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../login.php', 'بيئة شركة غير صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Workforce/worker_movement.php');
$can_view=$pp['can_view']; $can_add=$pp['can_add']; $can_edit=$pp['can_edit']; $can_delete=$pp['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../login.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
// العزل عبر بوابة المستأجر — والسوبر يمرّ عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$wm_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('worker movement super') : ems_tenant_db();

$STATES=['مسودة','أمرٌ صادر','في الطريق','وصل','مستلَم بالموقع','جاهزٌ للعمل','ملغى'];
$DIRECTIONS=['التحاق أول','عودة من إجازة','مغادرة لإجازة/مأمورية','نقل بين مشاريع','مغادرة نهائية'];

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save' && $can_add) {
    $worker_id=intval($_POST['worker_id']??0);
    $direction=trim($_POST['direction']??'التحاق أول');
    // نقطة الانطلاق: ولاية + مدينة (مع إبقاء origin النصّي للتوافق الرجعي)
    $origin_state=trim($_POST['origin_state']??''); $origin_state=$origin_state!==''?$origin_state:null;
    $origin_city =trim($_POST['origin_city']??'');  $origin_city =$origin_city!==''?$origin_city:null;
    $origin=trim(implode(' - ', array_filter([$origin_state,$origin_city]))); $origin=$origin!==''?$origin:null;
    // الوجهة: ولاية + مدينة + مشروع
    $dest_proj=!empty($_POST['destination_project_id'])?intval($_POST['destination_project_id']):null;
    $dest_state=trim($_POST['destination_state']??''); $dest_state=$dest_state!==''?$dest_state:null;
    $dest_city =trim($_POST['destination_city']??'');  $dest_city =$dest_city!==''?$dest_city:null;
    $transport=trim($_POST['transport_mode']??''); $transport=$transport!==''?$transport:null;
    $dep=!empty($_POST['departure_date'])?$_POST['departure_date']:null;
    $exp=!empty($_POST['expected_arrival'])?$_POST['expected_arrival']:null;
    $act=!empty($_POST['actual_arrival'])?$_POST['actual_arrival']:null;
    $received_by=!empty($_POST['received_by'])?intval($_POST['received_by']):null;
    $housing=!empty($_POST['housing_unit_id'])?intval($_POST['housing_unit_id']):null;
    $site_zone=trim($_POST['site_zone']??''); $site_zone=$site_zone!==''?$site_zone:null;
    $safety=isset($_POST['safety_kit_received'])?1:0;
    $custody=null; // مؤجّل (S09)
    $ready=!empty($_POST['ready_date'])?$_POST['ready_date']:null;
    $transfer_type=trim($_POST['transfer_type']??''); $transfer_type=$transfer_type!==''?$transfer_type:null;
    $from_proj=!empty($_POST['from_project_id'])?intval($_POST['from_project_id']):null;
    $to_proj=$dest_proj; // «إلى مشروع» = نفس «الوجهة (المشروع)» — حُذف الحقل المكرَّر من النموذج
    $state=trim($_POST['state']??'مسودة');
    if(!in_array($direction,$DIRECTIONS,true)) $direction=$DIRECTIONS[0];
    if(!in_array($state,$STATES,true)) $state=$STATES[0];
    $notes=trim($_POST['notes']??''); $notes=$notes!==''?$notes:null;
    if ($worker_id>0) {
        // أعمدة الولاية/المدينة الأربعة قائمة منذ ترحيلها (سقط فحص db_table_has_column
        // بسقوط الهجرات الذاتية) — والقيمة الفعلية تُمرَّر كلها للبوابة، وcompany_id تحقنه هي.
        try {
            $wm_gate->insert('worker_movement', array(
                'employee_id' => $worker_id, 'direction' => $direction,
                'origin' => $origin, 'origin_state' => $origin_state, 'origin_city' => $origin_city,
                'destination_project_id' => $dest_proj, 'destination_state' => $dest_state, 'destination_city' => $dest_city,
                'transport_mode' => $transport, 'departure_date' => $dep, 'expected_arrival' => $exp, 'actual_arrival' => $act,
                'received_by' => $received_by, 'housing_unit_id' => $housing, 'site_zone' => $site_zone,
                'safety_kit_received' => $safety, 'custody_received' => $custody, 'ready_date' => $ready,
                'transfer_type' => $transfer_type, 'from_project_id' => $from_proj, 'to_project_id' => $to_proj,
                'state' => $state, 'notes' => $notes, 'created_by' => $user_id));
        } catch (\Throwable $t) { error_log('worker_movement.php insert: ' . $t->getMessage()); }
    }
    ems_gov_flash_redirect('worker_movement.php', '✅ تم الحفظ', 'GOV-OK-200', ''); exit();
}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='set_state' && $can_edit) {
    $id=intval($_POST['id']??0); $ns=trim($_POST['new_state']??'');
    if ($id>0 && in_array($ns,$STATES,true)) {
        try { $wm_gate->update('worker_movement', array('state' => $ns), array('id' => $id)); }
        catch (\Throwable $t) { error_log('worker_movement.php set_state: ' . $t->getMessage()); }
    }
    ems_gov_flash_redirect('worker_movement.php', '✅ تم تحديث الحالة', 'GOV-OK-200', ''); exit();
}
if (($_GET['delete']??'')!=='' && $can_delete) {
    $d=intval($_GET['delete']);
    try { $wm_gate->deleteRow('worker_movement', $d, 'worker movement delete'); }
    catch (\Throwable $t) { error_log('worker_movement.php delete: ' . $t->getMessage()); }
    ems_gov_flash_redirect('worker_movement.php', '✅ تم الحذف', 'GOV-OK-200', ''); exit();
}

$workers=[];
try {
    $wm_workers = $wm_gate->scopedQuery(array('scope'=>array('wp'=>'employees')),
        "SELECT wp.id,wp.name AS name FROM employees wp WHERE 1=1 AND {TENANT_SCOPE} ORDER BY wp.name");
    foreach($wm_workers as $w){$workers[$w['id']]=$w['name'];}
} catch (\Throwable $t) { error_log('worker_movement.php workers: ' . $t->getMessage()); }
$projects=[];
try {
    $wm_projects = $wm_gate->scopedQuery(array('scope'=>array('project'=>'project')),
        "SELECT id,name FROM project WHERE 1=1 AND {TENANT_SCOPE} ORDER BY id DESC LIMIT 500");
    foreach($wm_projects as $p){$projects[$p['id']]=$p['name'];}
} catch (\Throwable $t) { error_log('worker_movement.php projects: ' . $t->getMessage()); }
$housing=[];
try {
    $wm_housing = $wm_gate->scopedQuery(array('scope'=>array('housing_unit'=>'housing_unit')),
        "SELECT id,name FROM housing_unit WHERE 1=1 AND {TENANT_SCOPE} ORDER BY name");
    foreach($wm_housing as $h){$housing[$h['id']]=$h['name'];}
} catch (\Throwable $t) { error_log('worker_movement.php housing: ' . $t->getMessage()); }

$page_title="إيكوبيشن | تنقلات العاملين"; 
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php'; include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main">
    <?php $header_title='تنقلات العاملين'; $header_icon='fas fa-route'; $header_actions=array();
    if($can_add) $header_actions[]=array('id'=>'toggleForm','class'=>'add-btn','icon'=>'fas fa-plus-circle','label'=>'أمر تحرّك/نقل');
    $header_back=array('href'=>'worker_register.php','class'=>'','icon'=>'fas fa-arrow-right','label'=>'سجل العامل');
    include('../includes/page_header.php'); ?>
    <?php if(!empty($_GET['msg'])): $ok=strpos($_GET['msg'],'✅')!==false; ?>
        <div class="success-message <?= $ok?'is-success':'is-error' ?>"><i class="fas <?= $ok?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <form id="mForm" action="" method="post" class="allforms" style="display:none;">
        <input type="hidden" name="action" value="save">
        <div class="card-header"><h5><i class="fas fa-plus"></i> أمر تحرّك / نقل</h5></div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:14px;">
            <!-- صف 1: الأساسيات -->
            <div class="field"><label>الموظف</label><select name="worker_id" required><option value="">—</option><?php foreach($workers as $wid=>$wn): ?><option value="<?= intval($wid) ?>"><?= htmlspecialchars($wn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>نوع الحركة</label><select name="direction"><?php foreach($DIRECTIONS as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>الحالة</label><select name="state"><?php foreach($STATES as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>وسيلة النقل</label><select name="transport_mode"><option value="">—</option><option>بري</option><option>جوي</option><option>ترتيب مورد</option></select></div>

            <!-- صف 2: الموقع (الانطلاق والوجهة) -->
            <div class="field"><label>الانطلاق — الولاية</label><input type="text" name="origin_state" placeholder="الولاية"></div>
            <div class="field"><label>الانطلاق — المدينة</label><input type="text" name="origin_city" placeholder="المدينة"></div>
            <div class="field"><label>الوجهة — الولاية</label><input type="text" name="destination_state" placeholder="الولاية"></div>
            <div class="field"><label>الوجهة — المدينة</label><input type="text" name="destination_city" placeholder="المدينة"></div>

            <!-- صف 3: الوجهة (المشروع) واللوجستيات -->
            <div class="field"><label>الوجهة — المشروع</label><select name="destination_project_id"><option value="">—</option><?php foreach($projects as $pid=>$pn): ?><option value="<?= intval($pid) ?>"><?= htmlspecialchars($pn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>السكن</label><select name="housing_unit_id"><option value="">—</option><?php foreach($housing as $hid=>$hn): ?><option value="<?= intval($hid) ?>"><?= htmlspecialchars($hn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>منطقة العمل</label><input type="text" name="site_zone"></div>
            <div class="field"><label>نوع النقل (للنقل)</label><select name="transfer_type"><option value="">—</option><option>مؤقت</option><option>دائم</option><option>إعادة تخصيص</option></select></div>

            <!-- صف 4: التواريخ وزمن الرحلة -->
            <div class="field"><label>تاريخ التحرك</label><input type="date" name="departure_date" id="dep_date" onchange="emsCalcTrip()"></div>
            <div class="field"><label>الوصول المتوقع</label><input type="date" name="expected_arrival"></div>
            <div class="field"><label>الوصول الفعلي</label><input type="date" name="actual_arrival" id="act_date" onchange="emsCalcTrip()"></div>
            <div class="field"><label>زمن الرحلة (محسوب)</label><input type="text" id="trip_days" readonly placeholder="يُحسب من التحرك → الوصول الفعلي"></div>

            <!-- صف 5: الجاهزية والاستلام -->
            <div class="field"><label>تاريخ الجاهزية</label><input type="date" name="ready_date"></div>
            <div class="field"><label>المستلِم (موظف #)</label><input type="number" name="received_by"></div>
            <div class="field" style="grid-column:span 2;display:flex;align-items:center;gap:8px;"><input type="checkbox" name="safety_kit_received" id="skr" value="1"><label for="skr" style="margin:0;">استلام معدات السلامة</label></div>

            <!-- صف 6: الملاحظات (عرض كامل) -->
            <div class="field" style="grid-column:1/-1;"><label>ملاحظات</label><input type="text" name="notes"></div>
        </div>
        <div style="padding:0 14px 16px;"><button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button></div>
    </form>
    <div class="table-wrap" style="margin-top:14px;"><table class="data-table" style="width:100%;">
        <thead><tr><th>إجراءات</th><th>#</th><th>كود الموظف</th><th>الحركة</th><th>الوجهة</th><th>الوصول الفعلي</th><th>زمن الرحلة</th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم الأمر</th>
              <th class="ems-fn-th" data-fn="1">من الموقع</th>
              <th class="ems-fn-th" data-fn="1">إلى الموقع</th>
              <th class="ems-fn-th" data-fn="1">نوع التنقل</th>
              <th class="ems-fn-th" data-fn="1">سبب التنقل</th>
              <th class="ems-fn-th" data-fn="1">تاريخ المغادرة</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الوصول</th>
              <th class="ems-fn-th" data-fn="1">رحلة الترحيل</th>
              <th class="ems-fn-th" data-fn="1">التخصيص السابق</th>
              <th class="ems-fn-th" data-fn="1">التخصيص الجديد</th>
              <th class="ems-fn-th" data-fn="1">أثر على الأجر</th>
              <th class="ems-fn-th" data-fn="1">أثر على البدلات</th>
              <th class="ems-fn-th" data-fn="1">السكن الجديد</th>
              <th class="ems-fn-th" data-fn="1">أصدره</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              </tr></thead><tbody>
        <?php $list = array();
        try {
            $list = $wm_gate->scopedQuery(array('scope'=>array('m'=>'worker_movement'), 'enrich'=>array('e'=>'employees','p'=>'project')),
                "SELECT m.*, e.name AS wname, p.name AS pname,
            DATEDIFF(m.actual_arrival, m.departure_date) AS trip_days
            FROM worker_movement m LEFT JOIN employees e ON e.id=m.employee_id
            LEFT JOIN project p ON p.id=m.destination_project_id WHERE 1=1 AND {TENANT_SCOPE} ORDER BY m.id DESC");
        } catch (\Throwable $t) { error_log('worker_movement.php list: ' . $t->getMessage()); }
        $i=1; $WF_VIEW = []; if($list){ foreach($list as $r): $i++;
            $sc=($r['state']==='جاهزٌ للعمل'||$r['state']==='مستلَم بالموقع')?'status-active':(($r['state']==='ملغى')?'status-inactive':'status-warning');
            $WF_VIEW[$r['id']] = ems_wf_view_payload('تفاصيل أمر التحرّك/النقل', 'fas fa-route', [
                ems_wf_field('الموظف', $r['wname'] ?: '-', 'fas fa-user', ['size' => 'lg']),
                ems_wf_field('نوع الحركة', $r['direction'], 'fas fa-arrows-turn-right'),
                ems_wf_field('الانطلاق (الولاية)', ($r['origin_state'] ?? '') ?: '-', 'fas fa-location-arrow'),
                ems_wf_field('الانطلاق (المدينة)', ($r['origin_city'] ?? '') ?: '-', 'fas fa-location-dot'),
                ems_wf_field('الوجهة (الولاية)', ($r['destination_state'] ?? '') ?: '-', 'fas fa-flag-checkered'),
                ems_wf_field('الوجهة (المدينة)', ($r['destination_city'] ?? '') ?: '-', 'fas fa-map-pin'),
                ems_wf_field('الوجهة (المشروع)', $r['pname'] ?: '-', 'fas fa-folder-open'),
                ems_wf_field('وسيلة النقل', $r['transport_mode'] ?: '-', 'fas fa-van-shuttle'),
                ems_wf_field('تاريخ التحرك', $r['departure_date'] ?: '-', 'fas fa-calendar-day'),
                ems_wf_field('الوصول المتوقع', $r['expected_arrival'] ?: '-', 'fas fa-calendar'),
                ems_wf_field('الوصول الفعلي', $r['actual_arrival'] ?: '-', 'fas fa-calendar-check'),
                ems_wf_field('زمن الرحلة', ($r['trip_days']!==null && $r['trip_days']!=='') ? (intval($r['trip_days']).' يوم') : '-', 'fas fa-stopwatch'),
                ems_wf_field('تاريخ الجاهزية', $r['ready_date'] ?: '-', 'fas fa-circle-check'),
                ems_wf_field('نوع النقل', $r['transfer_type'] ?: '-', 'fas fa-right-left'),
                ems_wf_field('منطقة العمل', $r['site_zone'] ?: '-', 'fas fa-map-location-dot'),
                ems_wf_field('استلام معدات السلامة', intval($r['safety_kit_received']) ? 'نعم' : 'لا', 'fas fa-helmet-safety'),
                ems_wf_field('الحالة', $r['state'], 'fas fa-flag', ['type' => 'status']),
                ems_wf_field('ملاحظات', $r['notes'] ?: '-', 'fas fa-align-right', ['size' => 'full']),
            ]); ?>
            <tr><td><div class="action-btns" style="gap:4px;align-items:center;">
                <?= ems_wf_view_button($r['id']) ?>
                <form action="" method="post" style="display:inline;"><input type="hidden" name="action" value="set_state"><input type="hidden" name="id" value="<?= intval($r['id']) ?>">
                    <select name="new_state" onchange="this.form.submit()" <?= $can_edit?'':'disabled' ?> style="padding:2px;"><?php foreach($STATES as $s): ?><option value="<?= $s ?>" <?= ($r['state']===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
                </form>
                <?php if($can_delete): ?><a href="worker_movement.php?delete=<?= intval($r['id']) ?>" class="action-btn delete" onclick="return confirm('حذف؟')"><i class="fas fa-trash"></i></a><?php endif; ?>
            </div></td>
            <td><?= intval($r['id']) ?></td><td><strong><?= htmlspecialchars($r['wname'] ?: '-') ?></strong></td>
            <td><?= htmlspecialchars($r['direction']) ?></td><td><?= htmlspecialchars($r['pname'] ?: '-') ?></td>
            <td><?= htmlspecialchars($r['actual_arrival'] ?: '-') ?></td>
            <td><?= ($r['trip_days']!==null && $r['trip_days']!=='') ? (intval($r['trip_days']).' يوم') : '-' ?></td>
            <td><span class="status-pill <?= $sc ?>"><?= htmlspecialchars($r['state']) ?></span></td></tr>
        <?php endforeach; } if(!$list||$i===1): ?><tr><td colspan="8" style="text-align:center;color:#888;padding:18px;">لا توجد أوامرٌ بعد.</td></tr><?php endif; ?>
        </tbody></table></div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
<script>
(function(){var b=document.getElementById('toggleForm'),f=document.getElementById('mForm');if(b&&f)b.addEventListener('click',function(){f.style.display=(f.style.display==='none'||!f.style.display)?'block':'none';});})();
// زمن الرحلة = (الوصول الفعلي − تاريخ التحرك) بالأيام — يُحسب تلقائياً
function emsCalcTrip(){
  var dep=document.getElementById('dep_date'), act=document.getElementById('act_date'), out=document.getElementById('trip_days');
  if(!dep||!act||!out) return;
  if(dep.value && act.value){
    var diff=Math.round((new Date(act.value)-new Date(dep.value))/86400000);
    out.value = isNaN(diff) ? '' : (diff + ' يوم');
  } else { out.value=''; }
}
</script>
</body></html>
