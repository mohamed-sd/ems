<?php
/** EQUIP-OPE-S04 — 8.10 الاحتياج وطلب القوى والتخطيط. Bolt-on. مرشّحون يدوياً (قرار 6). */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/WorkerCategory.php';
require_once __DIR__ . '/../app/Services/Workforce/PlanningService.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../login.php', 'بيئة شركة غير صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Workforce/workforce_requirement.php');
$can_view=$pp['can_view']; $can_add=$pp['can_add']; $can_edit=$pp['can_edit']; $can_delete=$pp['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../login.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
// العزل عبر بوابة المستأجر — والسوبر يمرّ عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$wr_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('workforce requirement super') : ems_tenant_db();
$PRIORITY=['عادية','عالية','حرجة']; $STAGES=['مفتوح','استقطاب','ترشيح واعتماد','تعاقد','تحرّك','مُلبّى'];

function ems_req_derive($required,$available){
    $shortage = max($required - $available, 0); $surplus = max($available - $required, 0);
    $state = ($shortage>0)?'عجز':(($surplus>0)?'فائض':'متوازن');
    return [$shortage,$surplus,$state];
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save') {
    $id=intval($_POST['id']??0); $is_editing=$id>0;
    if (($is_editing && !$can_edit) || (!$is_editing && !$can_add)) { ems_gov_flash_redirect('workforce_requirement.php', 'لا صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
    $project_id=!empty($_POST['project_id'])?intval($_POST['project_id']):null;
    $category=trim($_POST['worker_category']??'مشغّل/سائق');
    $required=intval($_POST['required_qty']??0);
    $available=intval($_POST['available_qty']??0);
    if (!in_array($category, ems_worker_categories(), true)) $category='مشغّل/سائق';
    // المتوفّر آليٌّ افتراضاً (PlanningService: العاملون بتخصيصٍ نشطٍ للمشروع والفئة)؛
    // «إدخال يدوي» يبقى تجاوزاً صريحاً يحترم القيمة المُدخَلة.
    $manual_available = isset($_POST['manual_available']);
    if (!$manual_available && $project_id) {
        $available = ems_planning_available($conn, $project_id, $category, $is_super_admin?null:$company_id);
    }
    list($shortage,$surplus,$state)=ems_req_derive($required,$available);
    $is_critical=isset($_POST['is_critical'])?1:0;
    $priority=trim($_POST['priority']??'عادية');
    $need_date=!empty($_POST['need_date'])?$_POST['need_date']:null;
    $stage=trim($_POST['fulfillment_stage']??'مفتوح');
    $candidates=trim($_POST['candidates_note']??''); $candidates=$candidates!==''?$candidates:null;
    $notes=trim($_POST['notes']??''); $notes=$notes!==''?$notes:null;
    if (!$is_editing) {
        try {
            // company_id تحقنه البوابة من سياق الجلسة
            $wr_gate->insert('workforce_requirement', array(
                'project_id' => $project_id, 'worker_category' => $category, 'required_qty' => $required,
                'available_qty' => $available, 'shortage_qty' => $shortage, 'surplus_qty' => $surplus,
                'is_critical' => $is_critical, 'priority' => $priority, 'need_date' => $need_date,
                'fulfillment_stage' => $stage, 'state' => $state, 'candidates_note' => $candidates,
                'notes' => $notes, 'created_by' => $user_id));
        } catch (\Throwable $t) { error_log('workforce_requirement.php insert: ' . $t->getMessage()); }
        ems_gov_flash_redirect('workforce_requirement.php', '✅ تم الحفظ', 'GOV-OK-200', ''); exit();
    } else {
        try {
            $wr_gate->update('workforce_requirement', array(
                'project_id' => $project_id, 'worker_category' => $category, 'required_qty' => $required,
                'available_qty' => $available, 'shortage_qty' => $shortage, 'surplus_qty' => $surplus,
                'is_critical' => $is_critical, 'priority' => $priority, 'need_date' => $need_date,
                'fulfillment_stage' => $stage, 'state' => $state, 'candidates_note' => $candidates,
                'notes' => $notes), array('id' => $id));
        } catch (\Throwable $t) { error_log('workforce_requirement.php update: ' . $t->getMessage()); }
        ems_gov_redirect("Location: workforce_requirement.php?edit=".$id."&msg=✅+تم+التحديث"); exit();
    }
}
if (($_GET['delete']??'')!=='' && $can_delete) { $d=intval($_GET['delete']);
    try { $wr_gate->deleteRow('workforce_requirement', $d, 'workforce requirement delete'); }
    catch (\Throwable $t) { error_log('workforce_requirement.php delete: ' . $t->getMessage()); }
    ems_gov_flash_redirect('workforce_requirement.php', '✅ تم الحذف', 'GOV-OK-200', ''); exit(); }

$edit=null; $edit_id=intval($_GET['edit']??0);
if ($edit_id>0) {
    try { $edit = $wr_gate->selectOne('workforce_requirement', array('where' => array('id' => $edit_id))); }
    catch (\Throwable $t) { $edit = null; error_log('workforce_requirement.php edit: ' . $t->getMessage()); }
}
$projects=[];
try {
    $wr_projects = $wr_gate->scopedQuery(array('scope'=>array('project'=>'project')),
        "SELECT id,name FROM project WHERE 1=1 AND {TENANT_SCOPE} ORDER BY id DESC LIMIT 500");
    foreach($wr_projects as $p){$projects[$p['id']]=$p['name'];}
} catch (\Throwable $t) { error_log('workforce_requirement.php projects: ' . $t->getMessage()); }

// معاينةٌ للمتوفّر المحسوب آلياً (PlanningService) للسجل قيد التعديل — للعرض فقط.
$auto_preview=null;
if ($edit && !empty($edit['project_id']) && !empty($edit['worker_category'])) {
    $auto_preview = ems_planning_available($conn, intval($edit['project_id']), $edit['worker_category'], $is_super_admin?null:$company_id);
}

$page_title="إيكوبيشن | احتياج القوى والتخطيط"; 
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php'; include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main">
    <?php $header_title='احتياج القوى والتخطيط'; $header_icon='fas fa-clipboard-list'; $header_actions=array();
    if($can_add) $header_actions[]=array('id'=>'toggleForm','class'=>'add-btn','icon'=>'fas fa-plus-circle','label'=>'احتياج جديد');
    $header_back=array('href'=>'worker_register.php','class'=>'','icon'=>'fas fa-arrow-right','label'=>'سجل العامل');
    include('../includes/page_header.php'); ?>
    <?php if(!empty($_GET['msg'])): $ok=strpos($_GET['msg'],'✅')!==false; ?>
        <div class="success-message <?= $ok?'is-success':'is-error' ?>"><i class="fas <?= $ok?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <form id="rForm" action="" method="post" class="allforms" style="<?= $edit?'display:block;':'display:none;' ?>">
        <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit?intval($edit['id']):0 ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?= $edit?'تعديل احتياج':'احتياج جديد' ?></h5></div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:14px;">
            <div class="field"><label>المشروع</label><select name="project_id"><option value="">—</option><?php foreach($projects as $pid=>$pn): ?><option value="<?= intval($pid) ?>" <?= (intval($edit['project_id']??0)===intval($pid))?'selected':'' ?>><?= htmlspecialchars($pn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>الفئة</label><select name="worker_category"><?php foreach(ems_worker_categories() as $c): ?><option value="<?= $c ?>" <?= (($edit['worker_category']??'')===$c)?'selected':'' ?>><?= $c ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>المطلوب</label><input type="number" name="required_qty" value="<?= htmlspecialchars($edit['required_qty'] ?? '0') ?>"></div>
            <div class="field"><label>المتوفّر <small style="color:#888;">(يُحسَب آلياً)</small></label>
                <input type="number" name="available_qty" value="<?= htmlspecialchars($edit['available_qty'] ?? '0') ?>">
                <label style="display:flex;align-items:center;gap:6px;font-weight:400;margin-top:4px;font-size:.85rem;"><input type="checkbox" name="manual_available" value="1"> إدخال يدوي للمتوفّر (تجاوز)</label>
                <?php if($auto_preview!==null): ?><small style="color:#b9770e;">المحسوب آلياً الآن من التخصيصات النشطة: <strong><?= intval($auto_preview) ?></strong></small><?php endif; ?>
            </div>
            <div class="field"><label>الأولوية</label><select name="priority"><?php foreach($PRIORITY as $p): ?><option value="<?= $p ?>" <?= (($edit['priority']??'عادية')===$p)?'selected':'' ?>><?= $p ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>تاريخ الحاجة</label><input type="date" name="need_date" value="<?= htmlspecialchars($edit['need_date'] ?? '') ?>"></div>
            <div class="field"><label>مرحلة التلبية</label><select name="fulfillment_stage"><?php foreach($STAGES as $s): ?><option value="<?= $s ?>" <?= (($edit['fulfillment_stage']??'مفتوح')===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
            <div class="field" style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_critical" id="crit" value="1" <?= (intval($edit['is_critical']??0)===1)?'checked':'' ?>><label for="crit" style="margin:0;">وظيفة حرجة</label></div>
            <div class="field" style="grid-column:1/-1;"><label>المرشّحون (إدخال يدوي)</label><input type="text" name="candidates_note" value="<?= htmlspecialchars($edit['candidates_note'] ?? '') ?>"></div>
            <div class="field" style="grid-column:1/-1;"><label>ملاحظات</label><input type="text" name="notes" value="<?= htmlspecialchars($edit['notes'] ?? '') ?>"></div>
        </div>
        <div style="padding:0 14px 16px;display:flex;gap:10px;"><button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ (يحسب المتوفّر والعجز/الفائض)</button><a href="workforce_requirement.php" class="add-btn" style="background:#6b7280;"><i class="fas fa-times"></i> إلغاء</a></div>
    </form>
    <div class="table-wrap" style="margin-top:14px;"><table class="data-table" style="width:100%;">
        <thead><tr><th>إجراءات</th><th>المشروع</th><th>الفئة</th><th>مطلوب</th><th>متوفّر</th><th>عجز</th><th>فائض</th><th>الأولوية</th><th>المرحلة</th><th>الحالة</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              </tr></thead><tbody>
        <?php $list = array();
        try {
            $list = $wr_gate->scopedQuery(array('scope'=>array('wr'=>'workforce_requirement'), 'enrich'=>array('p'=>'project')),
                "SELECT wr.*, p.name AS pname FROM workforce_requirement wr LEFT JOIN project p ON p.id=wr.project_id WHERE 1=1 AND {TENANT_SCOPE} ORDER BY wr.id DESC");
        } catch (\Throwable $t) { error_log('workforce_requirement.php list: ' . $t->getMessage()); }
        $i=1; $WF_VIEW = []; if($list){ foreach($list as $r): $i++; $sc=($r['state']==='عجز')?'status-inactive':(($r['state']==='فائض')?'status-warning':'status-active');
            $WF_VIEW[$r['id']] = ems_wf_view_payload('تفاصيل الاحتياج', 'fas fa-clipboard-list', [
                ems_wf_field('المشروع', $r['pname'] ?: '-', 'fas fa-folder-open', ['size' => 'lg']),
                ems_wf_field('الفئة', $r['worker_category'], 'fas fa-layer-group'),
                ems_wf_field('المطلوب', intval($r['required_qty']), 'fas fa-list-ol'),
                ems_wf_field('المتوفّر', intval($r['available_qty']), 'fas fa-user-check'),
                ems_wf_field('العجز', intval($r['shortage_qty']), 'fas fa-arrow-trend-down'),
                ems_wf_field('الفائض', intval($r['surplus_qty']), 'fas fa-arrow-trend-up'),
                ems_wf_field('وظيفة حرجة', intval($r['is_critical']) ? 'نعم' : 'لا', 'fas fa-triangle-exclamation'),
                ems_wf_field('الأولوية', $r['priority'], 'fas fa-fire'),
                ems_wf_field('مرحلة التلبية', $r['fulfillment_stage'], 'fas fa-diagram-project'),
                ems_wf_field('تاريخ الحاجة', $r['need_date'] ?: '-', 'fas fa-calendar-day'),
                ems_wf_field('الحالة', $r['state'], 'fas fa-flag', ['type' => 'status']),
                ems_wf_field('المرشّحون', $r['candidates_note'] ?: '-', 'fas fa-users', ['size' => 'full']),
                ems_wf_field('ملاحظات', $r['notes'] ?: '-', 'fas fa-align-right', ['size' => 'full']),
            ]); ?>
            <tr><td><div class="action-btns">
                <?= ems_wf_view_button($r['id']) ?>
                <?php if($can_edit): ?><a href="workforce_requirement.php?edit=<?= intval($r['id']) ?>" class="action-btn edit"><i class="fas fa-edit"></i></a><?php endif; ?>
                <?php if($can_delete): ?><a href="workforce_requirement.php?delete=<?= intval($r['id']) ?>" class="action-btn delete" onclick="return confirm('حذف؟')"><i class="fas fa-trash"></i></a><?php endif; ?>
            </div></td>
            <td><?= htmlspecialchars($r['pname'] ?: '-') ?></td><td><span class="badge badge-info"><?= htmlspecialchars($r['worker_category']) ?></span></td>
            <td><?= intval($r['required_qty']) ?></td><td><?= intval($r['available_qty']) ?></td>
            <td><?= intval($r['shortage_qty']) ?><?= intval($r['is_critical'])?' ⚠️':'' ?></td><td><?= intval($r['surplus_qty']) ?></td>
            <td><?= htmlspecialchars($r['priority']) ?></td><td><?= htmlspecialchars($r['fulfillment_stage']) ?></td>
            <td><span class="status-pill <?= $sc ?>"><?= htmlspecialchars($r['state']) ?></span></td></tr>
        <?php endforeach; } if(!$list||$i===1): ?><tr><td colspan="10" style="text-align:center;color:#888;padding:18px;">لا توجد سجلاتٌ بعد.</td></tr><?php endif; ?>
        </tbody></table></div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
<script>(function(){var b=document.getElementById('toggleForm'),f=document.getElementById('rForm');if(b&&f)b.addEventListener('click',function(){f.style.display=(f.style.display==='none'||!f.style.display)?'block':'none';});})();</script>
</body></html>
