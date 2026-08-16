<?php
/** EQUIP-OPE-S04 — 8.6 الإجازات التبادلية + 8.13 الغياب والطوارئ (موحّدة). Bolt-on. */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/CoverageService.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Workforce/worker_leave_absence.php');
$can_view=$pp['can_view']; $can_add=$pp['can_add']; $can_edit=$pp['can_edit']; $can_delete=$pp['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
// العزل عبر بوابة المستأجر — والسوبر يمرّ عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$la_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('worker leave absence super') : ems_tenant_db();

$STATES = ['مطلوب','معتمد','مفتوح','مُغطًّى','منتهٍ','مغلق'];

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='save' && $can_add) {
    $worker_id=intval($_POST['worker_id']??0);
    $event_class=trim($_POST['event_class']??'مخطّط');
    $event_type=trim($_POST['event_type']??'');
    $date_from=!empty($_POST['date_from'])?$_POST['date_from']:null;
    $date_to=!empty($_POST['date_to'])?$_POST['date_to']:null;
    $substitute=!empty($_POST['substitute_id'])?intval($_POST['substitute_id']):null;
    $rotation=trim($_POST['rotation_pattern']??''); $rotation=$rotation!==''?$rotation:null;
    $next_due=!empty($_POST['next_due_date'])?$_POST['next_due_date']:null;
    $coverage=trim($_POST['coverage_impact']??''); $coverage=$coverage!==''?$coverage:null;
    $outcome=trim($_POST['outcome']??''); $outcome=$outcome!==''?$outcome:null;
    $state=trim($_POST['state']??'مطلوب');
    if(!in_array($state,$STATES,true)) $state=$STATES[0];
    $reason=trim($_POST['reason']??''); $reason=$reason!==''?$reason:null;
    $notes=trim($_POST['notes']??''); $notes=$notes!==''?$notes:null;
    if ($worker_id>0 && $event_type!=='') {
        // محرّك التغطية: عند خروج العامل بلا بديلٍ محدَّد، نقترح أنسب بديلٍ متاح.
        if ($substitute===null) { $substitute = ems_coverage_best_id($conn, $worker_id); }
        try {
            // company_id تحقنه البوابة من سياق الجلسة
            $la_gate->insert('worker_leave_absence', array(
                'employee_id' => $worker_id, 'event_class' => $event_class, 'event_type' => $event_type,
                'date_from' => $date_from, 'date_to' => $date_to, 'substitute_id' => $substitute,
                'rotation_pattern' => $rotation, 'next_due_date' => $next_due, 'coverage_impact' => $coverage,
                'outcome' => $outcome, 'state' => $state, 'reason' => $reason, 'notes' => $notes,
                'created_by' => $user_id));
        } catch (\Throwable $t) { error_log('worker_leave_absence.php insert: ' . $t->getMessage()); }
    }
    ems_gov_flash_redirect('worker_leave_absence.php', '✅ تم الحفظ', 'GOV-OK-200', ''); exit();
}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='set_state' && $can_edit) {
    $id=intval($_POST['id']??0); $ns=trim($_POST['new_state']??'');
    if ($id>0 && in_array($ns,$STATES,true)) {
        try { $la_gate->update('worker_leave_absence', array('state' => $ns), array('id' => $id)); }
        catch (\Throwable $t) { error_log('worker_leave_absence.php set_state: ' . $t->getMessage()); }
    }
    ems_gov_flash_redirect('worker_leave_absence.php', '✅ تم تحديث الحالة', 'GOV-OK-200', ''); exit();
}
if (($_GET['delete']??'')!=='' && $can_delete) {
    $d=intval($_GET['delete']);
    try { $la_gate->deleteRow('worker_leave_absence', $d, 'leave absence delete'); }
    catch (\Throwable $t) { error_log('worker_leave_absence.php delete: ' . $t->getMessage()); }
    ems_gov_flash_redirect('worker_leave_absence.php', '✅ تم الحذف', 'GOV-OK-200', ''); exit();
}

$workers=[];
try {
    $la_workers = $la_gate->scopedQuery(array('scope'=>array('wp'=>'employees')),
        "SELECT wp.id,wp.name AS name FROM employees wp WHERE 1=1 AND {TENANT_SCOPE} ORDER BY wp.name");
    foreach($la_workers as $w){$workers[$w['id']]=$w['name'];}
} catch (\Throwable $t) { error_log('worker_leave_absence.php workers: ' . $t->getMessage()); }

$page_title="إيكوبيشن | الإجازات والغياب"; 
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php'; include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main">
    <?php $header_title='الإجازات والغياب'; $header_icon='fas fa-plane-departure'; $header_actions=array();
    if($can_add) $header_actions[]=array('id'=>'toggleForm','class'=>'add-btn','icon'=>'fas fa-plus-circle','label'=>'تسجيل إجازة/غياب');
    $header_back=array('href'=>'worker_register.php','class'=>'','icon'=>'fas fa-arrow-right','label'=>'سجل العامل');
    include('../includes/page_header.php'); ?>
    <?php if(!empty($_GET['msg'])): $ok=strpos($_GET['msg'],'✅')!==false; ?>
        <div class="success-message <?= $ok?'is-success':'is-error' ?>"><i class="fas <?= $ok?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <form id="lForm" action="" method="post" class="allforms" style="display:none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="card-header"><h5><i class="fas fa-plus"></i> إجازة / غياب</h5></div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:14px;">
            <div class="field"><label for="emsf_1856_cdcec">الموظف</label><select name="worker_id" required id="emsf_1856_cdcec"><option value="">—</option><?php foreach($workers as $wid=>$wn): ?><option value="<?= intval($wid) ?>"><?= htmlspecialchars($wn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="emsf_1857_c08d3">التصنيف</label><select name="event_class" id="emsf_1857_c08d3"><option value="مخطّط">مخطّط (إجازة/تناوب)</option><option value="طارئ">طارئ (غياب)</option></select></div>
            <div class="field"><label for="emsf_1858_1c6c2">النوع</label><select name="event_type" required id="emsf_1858_1c6c2">
                <optgroup label="مخطّط"><option>تبادلية</option><option>اعتيادية</option><option>مأمورية</option></optgroup>
                <optgroup label="طارئ"><option>غياب مفاجئ</option><option>انقطاع عن العمل</option><option>هروب من الموقع</option><option>مرض مفاجئ</option><option>إصابة</option><option>ظرف أسري طارئ</option><option>وفاة</option></optgroup>
            </select></div>
            <div class="field"><label for="emsf_1859_f0a3f">الحالة</label><select name="state" id="emsf_1859_f0a3f"><?php foreach($STATES as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="emsf_1860_cfebe">من</label><input type="date" name="date_from" id="emsf_1860_cfebe"></div>
            <div class="field"><label for="emsf_1861_517b0">إلى</label><input type="date" name="date_to" id="emsf_1861_517b0"></div>
            <div class="field"><label for="emsf_1862_01550">البديل</label><select name="substitute_id" id="emsf_1862_01550"><option value="">—</option><?php foreach($workers as $wid=>$wn): ?><option value="<?= intval($wid) ?>"><?= htmlspecialchars($wn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="emsf_1863_63f44">أثر التغطية</label><select name="coverage_impact" id="emsf_1863_63f44"><option value="">—</option><option>مغطًّى</option><option>فجوة جزئية</option><option>فجوة حرجة</option></select></div>
            <div class="field"><label for="emsf_1864_da617">الاستحقاق القادم</label><input type="date" name="next_due_date" id="emsf_1864_da617"></div>
            <div class="field"><label for="emsf_1865_33bec">النتيجة</label><select name="outcome" id="emsf_1865_33bec"><option value="">—</option><option>عودة للعمل</option><option>تحويل لإجازة</option><option>إنهاء وتسوية</option></select></div>
            <div class="field" style="grid-column:1/-1;"><label for="emsf_1866_9bedf">السبب/ملاحظات</label><input type="text" name="reason" id="emsf_1866_9bedf"></div>
        </div>
        <div style="padding:0 14px 16px;"><button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button></div>
    </form>
    <div class="table-wrap" style="margin-top:14px;"><table class="data-table" style="width:100%;">
        <thead><tr><th>إجراءات</th><th>#</th><th>كود الموظف</th><th>التصنيف</th><th>نوع الإجازة</th><th>من تاريخ</th><th>إلى تاريخ</th><th>البديل المخصَّص</th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم الطلب</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الطلب</th>
              <th class="ems-fn-th" data-fn="1">عدد الأيام</th>
              <th class="ems-fn-th" data-fn="1">الرصيد قبل</th>
              <th class="ems-fn-th" data-fn="1">الرصيد بعد</th>
              <th class="ems-fn-th" data-fn="1">المستند المؤيد</th>
              <th class="ems-fn-th" data-fn="1">أثر الأجر</th>
              <th class="ems-fn-th" data-fn="1">اعتماد المدير</th>
              <th class="ems-fn-th" data-fn="1">اعتماد الموارد</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              </tr></thead><tbody>
        <?php $list = array();
        try {
            $list = $la_gate->scopedQuery(array('scope'=>array('la'=>'worker_leave_absence'), 'enrich'=>array('e'=>'employees','e2'=>'employees')),
                "SELECT la.*, e.name AS wname, e2.name AS sname
            FROM worker_leave_absence la
            LEFT JOIN employees e ON e.id=la.employee_id
            LEFT JOIN employees e2 ON e2.id=la.substitute_id
            WHERE 1=1 AND {TENANT_SCOPE} ORDER BY la.id DESC");
        } catch (\Throwable $t) { error_log('worker_leave_absence.php list: ' . $t->getMessage()); }
        $i=1; $WF_VIEW = []; if($list){ foreach($list as $r): $i++;
            $sc=($r['state']==='مغلق'||$r['state']==='منتهٍ')?'status-inactive':(($r['state']==='مُغطًّى'||$r['state']==='معتمد')?'status-active':'status-warning');
            $WF_VIEW[$r['id']] = ems_wf_view_payload('تفاصيل الإجازة/الغياب', 'fas fa-plane-departure', [
                ems_wf_field('الموظف', $r['wname'] ?: '-', 'fas fa-user', ['size' => 'lg']),
                ems_wf_field('التصنيف', $r['event_class'], 'fas fa-layer-group'),
                ems_wf_field('النوع', $r['event_type'], 'fas fa-tag'),
                ems_wf_field('من', $r['date_from'] ?: '-', 'fas fa-calendar-day'),
                ems_wf_field('إلى', $r['date_to'] ?: '-', 'fas fa-calendar-xmark'),
                ems_wf_field('البديل', $r['sname'] ?: '-', 'fas fa-user-shield'),
                ems_wf_field('أثر التغطية', $r['coverage_impact'] ?: '-', 'fas fa-shield-halved'),
                ems_wf_field('نمط التناوب', $r['rotation_pattern'] ?: '-', 'fas fa-rotate'),
                ems_wf_field('الاستحقاق القادم', $r['next_due_date'] ?: '-', 'fas fa-calendar-check'),
                ems_wf_field('النتيجة', $r['outcome'] ?: '-', 'fas fa-flag-checkered'),
                ems_wf_field('الحالة', $r['state'], 'fas fa-flag', ['type' => 'status']),
                ems_wf_field('السبب/ملاحظات', $r['reason'] ?: '-', 'fas fa-align-right', ['size' => 'full']),
            ]); ?>
            <tr><td><div class="action-btns" style="gap:4px;align-items:center;">
                <?= ems_wf_view_button($r['id']) ?>
                <form action="" method="post" style="display:inline;">
        <?= csrf_field() ?><input type="hidden" name="action" value="set_state"><input type="hidden" name="id" value="<?= intval($r['id']) ?>">
                    <select name="new_state" onchange="this.form.submit()" <?= $can_edit?'':'disabled' ?> style="padding:2px;"><?php foreach($STATES as $s): ?><option value="<?= $s ?>" <?= ($r['state']===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
                </form>
                <?php if($can_delete): ?><a href="worker_leave_absence.php?delete=<?= intval($r['id']) ?>" class="action-btn delete" onclick="return confirm('حذف؟')"><i class="fas fa-trash"></i></a><?php endif; ?>
            </div></td>
            <td><?= intval($r['id']) ?></td><td><strong><?= htmlspecialchars($r['wname'] ?: '-') ?></strong></td>
            <td><?= htmlspecialchars($r['event_class']) ?></td><td><?= htmlspecialchars($r['event_type']) ?></td>
            <td><?= htmlspecialchars($r['date_from'] ?: '-') ?></td><td><?= htmlspecialchars($r['date_to'] ?: '-') ?></td>
            <td><?= htmlspecialchars($r['sname'] ?: '-') ?></td>
            <td><span class="status-pill <?= $sc ?>"><?= htmlspecialchars($r['state']) ?></span></td></tr>
        <?php endforeach; } if(!$list||$i===1): ?><tr><td colspan="9" style="text-align:center;color:#888;padding:18px;">لا توجد سجلاتٌ بعد.</td></tr><?php endif; ?>
        </tbody></table></div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
<script>(function(){var b=document.getElementById('toggleForm'),f=document.getElementById('lForm');if(b&&f)b.addEventListener('click',function(){f.style.display=(f.style.display==='none'||!f.style.display)?'block':'none';});})();</script>
</body></html>
