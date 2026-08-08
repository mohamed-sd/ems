<?php
/** EQUIP-OPE-S04 — وحدات السكن (مرجعي · 8.11). Bolt-on، صفر لمسٍ للقائم. */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../login.php', 'بيئة شركة غير صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Workforce/housing_units.php');
$can_view=$pp['can_view']; $can_add=$pp['can_add']; $can_edit=$pp['can_edit']; $can_delete=$pp['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../login.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
// العزل عبر بوابة المستأجر (TenantDb) — والسوبر يمرّ عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$hu_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('housing units super') : ems_tenant_db();

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='save' && ($can_add || $can_edit)) {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $capacity = $_POST['capacity'] !== '' ? intval($_POST['capacity']) : null;
    $location = trim($_POST['location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    if ($name !== '') {
        if ($id > 0 && $can_edit) {
            try {
                $hu_gate->update('housing_unit',
                    array('name' => $name, 'project_id' => $project_id, 'capacity' => $capacity,
                          'location' => $location, 'notes' => $notes),
                    array('id' => $id));
            } catch (\Throwable $t) { error_log('housing_units.php update: ' . $t->getMessage()); }
        } elseif ($can_add) {
            try {
                // company_id تحقنه البوابة من سياق الجلسة
                $hu_gate->insert('housing_unit',
                    array('name' => $name, 'project_id' => $project_id, 'capacity' => $capacity,
                          'location' => $location, 'notes' => $notes));
            } catch (\Throwable $t) { error_log('housing_units.php insert: ' . $t->getMessage()); }
        }
    }
    ems_gov_flash_redirect('housing_units.php', '✅ تم الحفظ', 'GOV-OK-200', ''); exit();
}
if (($_GET['delete'] ?? '') !== '' && $can_delete) {
    $d=intval($_GET['delete']);
    try { $hu_gate->deleteRow('housing_unit', $d, 'housing unit delete'); }
    catch (\Throwable $t) { error_log('housing_units.php delete: ' . $t->getMessage()); }
    ems_gov_flash_redirect('housing_units.php', '✅ تم الحذف', 'GOV-OK-200', ''); exit();
}

$projects = [];
try {
    $hu_proj_rows = $hu_gate->scopedQuery(array('scope' => array('project' => 'project')),
        "SELECT id, name FROM project WHERE 1=1 AND {TENANT_SCOPE} ORDER BY id DESC LIMIT 500");
    foreach ($hu_proj_rows as $p) { $projects[$p['id']] = $p['name']; }
} catch (\Throwable $t) { error_log('housing_units.php projects: ' . $t->getMessage()); }

$page_title = "إيكوبيشن | السكن والإعاشة";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main">
    <?php $header_title='السكن والإعاشة'; $header_icon='fas fa-building'; $header_actions=array();
    if ($can_add) $header_actions[]=array('id'=>'toggleForm','class'=>'add-btn','icon'=>'fas fa-plus-circle','label'=>'وحدة سكن');
    $header_back=array('href'=>'worker_register.php','class'=>'','icon'=>'fas fa-arrow-right','label'=>'سجل العامل');
    include('../includes/page_header.php'); ?>
    <?php if (!empty($_GET['msg'])): $ok=strpos($_GET['msg'],'✅')!==false; ?>
        <div class="success-message <?= $ok?'is-success':'is-error' ?>"><i class="fas <?= $ok?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <form id="hForm" action="" method="post" class="allforms" style="display:none;">
        <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="0">
        <div class="card-header"><h5><i class="fas fa-plus"></i> وحدة سكن/مخيم</h5></div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;padding:14px;">
            <div class="field"><label>الاسم</label><input type="text" name="name" required></div>
            <div class="field"><label>المشروع</label><select name="project_id"><option value="">—</option><?php foreach($projects as $pid=>$pn): ?><option value="<?= intval($pid) ?>"><?= htmlspecialchars($pn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>السعة</label><input type="number" name="capacity"></div>
            <div class="field"><label>الموقع</label><input type="text" name="location"></div>
            <div class="field" style="grid-column:2/-1;"><label>ملاحظات</label><input type="text" name="notes"></div>
        </div>
        <div style="padding:0 14px 16px;"><button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button></div>
    </form>
    <div class="table-wrap" style="margin-top:14px;"><table class="data-table" style="width:100%;">
        <thead><tr><th>إجراءات</th><th>المُنشئ — الاسم والصفة</th><th>المشروع</th><th>السعة</th><th>الموقع</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">كود الوحدة</th>
              <th class="ems-fn-th" data-fn="1">المعسكر</th>
              <th class="ems-fn-th" data-fn="1">نوع الوحدة</th>
              <th class="ems-fn-th" data-fn="1">الشاغلون الحاليون</th>
              <th class="ems-fn-th" data-fn="1">المتاح</th>
              <th class="ems-fn-th" data-fn="1">تاريخ التخصيص</th>
              <th class="ems-fn-th" data-fn="1">نوع الإعاشة</th>
              <th class="ems-fn-th" data-fn="1">تكلفة الإعاشة الشهرية</th>
              <th class="ems-fn-th" data-fn="1">الجهة المتحملة</th>
              <th class="ems-fn-th" data-fn="1">المسؤول</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead><tbody>
        <?php $list = array();
        try {
            $list = $hu_gate->scopedQuery(array('scope' => array('h' => 'housing_unit'), 'enrich' => array('p' => 'project')),
                "SELECT h.*, p.name AS pname FROM housing_unit h LEFT JOIN project p ON p.id=h.project_id WHERE 1=1 AND {TENANT_SCOPE} ORDER BY h.id DESC");
        } catch (\Throwable $t) { error_log('housing_units.php list: ' . $t->getMessage()); }
        $i=1; $WF_VIEW = []; if($list){ foreach($list as $r): $i++;
            $WF_VIEW[$r['id']] = ems_wf_view_payload('تفاصيل وحدة السكن', 'fas fa-building', [
                ems_wf_field('الاسم', $r['name'], 'fas fa-signature', ['size' => 'lg']),
                ems_wf_field('المشروع', $r['pname'] ?: '-', 'fas fa-folder-open'),
                ems_wf_field('السعة', $r['capacity'] !== null ? $r['capacity'] : '-', 'fas fa-users'),
                ems_wf_field('الموقع', $r['location'] ?: '-', 'fas fa-location-dot', ['size' => 'lg']),
                ems_wf_field('ملاحظات', $r['notes'] ?: '-', 'fas fa-align-right', ['size' => 'full']),
            ]); ?>
            <tr><td><div class="action-btns">
                <?= ems_wf_view_button($r['id']) ?>
                <?php if($can_edit): ?><a href="javascript:void(0)" class="action-btn edit hu-edit"
                    data-id="<?= intval($r['id']) ?>"
                    data-name="<?= htmlspecialchars((string)$r['name'], ENT_QUOTES, 'UTF-8') ?>"
                    data-project="<?= intval($r['project_id']) ?>"
                    data-capacity="<?= htmlspecialchars((string)($r['capacity'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    data-location="<?= htmlspecialchars((string)($r['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    data-notes="<?= htmlspecialchars((string)($r['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    title="تعديل"><i class="fas fa-edit"></i></a><?php endif; ?>
                <?php if($can_delete): ?><a href="housing_units.php?delete=<?= intval($r['id']) ?>" class="action-btn delete" onclick="return confirm('حذف؟')"><i class="fas fa-trash"></i></a><?php endif; ?>
            </div></td>
            <td><strong><?= htmlspecialchars($r['name']) ?></strong></td><td><?= htmlspecialchars($r['pname'] ?: '-') ?></td>
            <td><?= htmlspecialchars($r['capacity'] ?: '-') ?></td><td><?= htmlspecialchars($r['location'] ?: '-') ?></td></tr>
        <?php endforeach; } if(!$list||$i===1): ?><tr><td colspan="5" style="text-align:center;color:#888;padding:18px;">لا توجد وحدات سكن بعد.</td></tr><?php endif; ?>
        </tbody></table></div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
<script>(function(){
    var b=document.getElementById('toggleForm'),f=document.getElementById('hForm');
    function setHeader(t){var h=f.querySelector('.card-header h5');if(h)h.innerHTML='<i class="fas fa-'+(t==='edit'?'edit':'plus')+'"></i> '+(t==='edit'?'تعديل وحدة سكن':'وحدة سكن/مخيم');}
    function resetForm(){if(!f)return;f.reset();f.querySelector('[name="id"]').value='0';setHeader('add');}
    // زر «وحدة سكن» (إضافة): يفتح نموذجاً نظيفاً
    if(b&&f)b.addEventListener('click',function(){var hidden=(f.style.display==='none'||!f.style.display);if(hidden){resetForm();}f.style.display=hidden?'block':'none';});
    // أزرار التعديل: تملأ النموذج ببيانات الوحدة
    document.querySelectorAll('.hu-edit').forEach(function(btn){
        btn.addEventListener('click',function(){
            if(!f)return; var d=this.dataset;
            f.querySelector('[name="id"]').value=d.id;
            f.querySelector('[name="name"]').value=d.name||'';
            var ps=f.querySelector('[name="project_id"]'); if(ps) ps.value=(d.project&&d.project!=='0')?d.project:'';
            f.querySelector('[name="capacity"]').value=(d.capacity&&d.capacity!=='')?d.capacity:'';
            f.querySelector('[name="location"]').value=d.location||'';
            f.querySelector('[name="notes"]').value=d.notes||'';
            setHeader('edit'); f.style.display='block';
            window.scrollTo({top:(f.offsetTop||0)-20,behavior:'smooth'});
        });
    });
})();</script>
</body></html>
