<?php
/** EQUIP-OPE-S04 — 8.10 الاحتياج وطلب القوى والتخطيط. Bolt-on. مرشّحون يدوياً (قرار 6). */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/WorkerCategory.php';
require_once __DIR__ . '/../app/Services/Workforce/PlanningService.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Workforce/workforce_requirement.php');
$can_view=$pp['can_view']; $can_add=$pp['can_add']; $can_edit=$pp['can_edit']; $can_delete=$pp['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
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
    <?php // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا احتياج قوى عاملة مسجلا لأي مشروع بعد', 'سجل أول احتياج بزر «احتياج جديد» في رأس الشاشة'); ?>
    <style>
        .wr-form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-3); padding: 14px; }
        .wr-hint-muted { color: var(--c-888, #888); }
        .wr-manual-label { display: flex; align-items: center; gap: 6px; font-weight: var(--weight-regular); margin-top: 4px; font-size: .85rem; }
        .wr-auto-hint { color: var(--c-b9770e, #b9770e); }
        .wr-inline-check { display: flex; align-items: center; gap: var(--space-2); }
        .wr-check-label { margin: 0; }
        .wr-span-full { grid-column: 1 / -1; }
        .wr-form-actions { padding: 0 14px 16px; display: flex; gap: 10px; }
        .wr-cancel-btn { background: var(--c-ink-500); }
        .wr-table-wrap { margin-top: 14px; }
        .wr-table-full { width: 100%; }
        .wr-empty-cell { text-align: center; color: var(--c-888, #888); padding: 18px; }
    </style>
    <?php if(!empty($_GET['msg'])): $ok=strpos($_GET['msg'],'✅')!==false; ?>
        <div class="success-message <?= $ok?'is-success':'is-error' ?>"><i class="fas <?= $ok?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <form id="rForm" action="" method="post" class="allforms<?= $edit?' allforms-visible':'' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit?intval($edit['id']):0 ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?= $edit?'تعديل احتياج':'احتياج جديد' ?></h5></div>
        <div class="wr-form-grid">
            <div class="field"><label for="emsf_634_3d483">المشروع</label><select name="project_id" id="emsf_634_3d483"><option value="">—</option><?php foreach($projects as $pid=>$pn): ?><option value="<?= intval($pid) ?>" <?= (intval($edit['project_id']??0)===intval($pid))?'selected':'' ?>><?= htmlspecialchars($pn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="emsf_635_6db6c">الفئة</label><select name="worker_category" id="emsf_635_6db6c"><?php foreach(ems_worker_categories() as $c): ?><option value="<?= $c ?>" <?= (($edit['worker_category']??'')===$c)?'selected':'' ?>><?= $c ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="emsf_636_beefe">المطلوب</label><input type="number" name="required_qty" id="emsf_636_beefe" aria-label="عدد العاملين المطلوب" value="<?= htmlspecialchars($edit['required_qty'] ?? '0') ?>"></div>
            <div class="field"><label for="emsf_637_4aad4">المتوفر <small class="wr-hint-muted">(يحسب آليا)</small></label>
                <input type="number" name="available_qty" id="emsf_637_4aad4" aria-label="عدد العاملين المتوفر" value="<?= htmlspecialchars($edit['available_qty'] ?? '0') ?>">
                <label class="wr-manual-label" for="wr_manual_available"><input type="checkbox" name="manual_available" id="wr_manual_available" value="1"> إدخال يدوي للمتوفر (تجاوز)</label>
                <?php if($auto_preview!==null): ?><small class="wr-auto-hint">المحسوب آليا الآن من التخصيصات النشطة: <strong><?= intval($auto_preview) ?></strong></small><?php endif; ?>
            </div>
            <div class="field"><label for="wr_priority">الأولوية</label><select name="priority" id="wr_priority"><?php foreach($PRIORITY as $p): ?><option value="<?= $p ?>" <?= (($edit['priority']??'عادية')===$p)?'selected':'' ?>><?= $p ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="emsf_638_3e1af">تاريخ الحاجة</label><input type="date" name="need_date" id="emsf_638_3e1af" aria-label="تاريخ الحاجة للقوى العاملة" value="<?= htmlspecialchars($edit['need_date'] ?? '') ?>"></div>
            <div class="field"><label for="emsf_639_5cba4">مرحلة التلبية</label><select name="fulfillment_stage" id="emsf_639_5cba4"><?php foreach($STAGES as $s): ?><option value="<?= $s ?>" <?= (($edit['fulfillment_stage']??'مفتوح')===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
            <div class="field wr-inline-check"><input type="checkbox" name="is_critical" id="crit" value="1" <?= (intval($edit['is_critical']??0)===1)?'checked':'' ?>><label for="crit" class="wr-check-label">وظيفة حرجة</label></div>
            <div class="field wr-span-full"><label for="wr_candidates_note">المرشحون (إدخال يدوي)</label><input type="text" name="candidates_note" id="wr_candidates_note" aria-label="أسماء المرشحين — إدخال يدوي" value="<?= htmlspecialchars($edit['candidates_note'] ?? '') ?>"></div>
            <div class="field wr-span-full"><label for="emsf_640_318d4">ملاحظات</label><input type="text" name="notes" id="emsf_640_318d4" aria-label="ملاحظات على الاحتياج" value="<?= htmlspecialchars($edit['notes'] ?? '') ?>"></div>
        </div>
        <div class="wr-form-actions"><button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ (يحسب المتوفر والعجز/الفائض)</button><a href="workforce_requirement.php" class="add-btn wr-cancel-btn"><i class="fas fa-times"></i> إلغاء</a></div>
    </form>
    <?php /* GUIDE_COLS:govui_field_close:emsList_workforce_requirement
         الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
         والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
         ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
    $GUIDE_COLS = array(
        'معرف الاحتياج' => 'id',
        'رقم المشروع' => 'project_id',
        'كود عقد العميل' => 'client_contract_code',
        'الموقع' => 'site_ref',
        'الفئة التشغيلية' => 'worker_category',
        'نوع المعدة المرتبط' => 'linked_equipment_type',
        'العدد المطلوب' => 'required_qty',
        'مستوى التأهيل المطلوب' => 'required_qualification_level',
        'نمط الوردية' => 'shift_pattern',
        'من تاريخ' => 'need_from_date',
        'إلى تاريخ' => 'need_date',
        'مصدر الاحتياج' => 'need_source_ref',
        'حالة الاحتياج' => 'state',
        'المراجع' => 'reviewer_name',
        'تاريخ الاعتماد' => 'approved_on',
    );
    $D = array();
    $__gridRows = ems_w14_guide_rows('workforce_requirement');
    echo ems_w14_grid('emsList_workforce_requirement', $GUIDE_COLS, $__gridRows, $D, 'لا احتياج مسجل بعد'); /* /GUIDE_COLS */ ?></div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
<script>(function(){var b=document.getElementById('toggleForm'),f=document.getElementById('rForm');if(b&&f)b.addEventListener('click',function(){f.classList.toggle("allforms-visible");});})();</script>
</body></html>
