<?php
/** EQUIP-OPE-S04 — 8.7 تسوية العامل التشغيلي (+ بنود المستحقات/الخصومات). Bolt-on. */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Workforce/worker_settlement.php');
$can_view=$pp['can_view']; $can_add=$pp['can_add']; $can_edit=$pp['can_edit']; $can_delete=$pp['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
// العزل عبر بوابة المستأجر — والسوبر يمرّ عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$ws_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('worker settlement super') : ems_tenant_db();
$STATES=['محتسب','معتمد','مدفوع'];

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save') {
    $id=intval($_POST['id']??0); $is_editing=$id>0;
    if (($is_editing && !$can_edit) || (!$is_editing && !$can_add)) { ems_gov_flash_redirect('worker_settlement.php', 'لا صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
    $worker_id=intval($_POST['worker_id']??0);
    $contract_id=!empty($_POST['worker_contract_id'])?intval($_POST['worker_contract_id']):null;
    $source=trim($_POST['source_type']??''); $source=$source!==''?$source:null;
    $party=trim($_POST['settlement_party']??''); $party=$party!==''?$party:null;
    $basis=trim($_POST['settlement_basis']??''); $basis=$basis!==''?$basis:null;
    $net=$_POST['net_amount']!==''?floatval($_POST['net_amount']):null;
    $net_note=trim($_POST['net_finance_note']??''); $net_note=$net_note!==''?$net_note:null;
    $state=trim($_POST['state']??'محتسب');
    $notes=trim($_POST['notes']??''); $notes=$notes!==''?$notes:null;
    if (!$is_editing) {
        if ($worker_id<=0) { ems_gov_flash_redirect('worker_settlement.php', 'يجب اختيار عامل ❌', 'GOV-FAIL-409', ''); exit(); }
        $nid=0;
        try {
            // company_id تحقنه البوابة من سياق الجلسة، وتعيد المعرّف الوليد
            $nid = intval($ws_gate->insert('worker_settlement', array(
                'employee_id' => $worker_id, 'worker_contract_id' => $contract_id, 'source_type' => $source,
                'settlement_party' => $party, 'settlement_basis' => $basis, 'net_amount' => $net,
                'net_finance_note' => $net_note, 'state' => $state, 'notes' => $notes, 'created_by' => $user_id)));
        } catch (\Throwable $t) { error_log('worker_settlement.php insert: ' . $t->getMessage()); }
        ems_gov_redirect("Location: worker_settlement.php?edit=".$nid."&msg=✅+تم+الحفظ"); exit();
    } else {
        try {
            $ws_gate->update('worker_settlement', array(
                'worker_contract_id' => $contract_id, 'source_type' => $source, 'settlement_party' => $party,
                'settlement_basis' => $basis, 'net_amount' => $net, 'net_finance_note' => $net_note,
                'state' => $state, 'notes' => $notes), array('id' => $id));
        } catch (\Throwable $t) { error_log('worker_settlement.php update: ' . $t->getMessage()); }
        ems_gov_redirect("Location: worker_settlement.php?edit=".$id."&msg=✅+تم+التحديث"); exit();
    }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_line' && $can_edit) {
    $sid=intval($_POST['settlement_id']??0); $lt=trim($_POST['line_type']??'مستحق');
    $desc=trim($_POST['description']??''); $desc=$desc!==''?$desc:null; $amt=$_POST['amount']!==''?floatval($_POST['amount']):null;
    if ($sid>0) {
        // تأكّد أن التسوية الأب تتبع شركة المستخدم قبل الإدراج (worker_settlement_line بلا company_id)
        $owned=$is_super_admin;
        if (!$is_super_admin) {
            try { $owned = ($ws_gate->selectOne('worker_settlement', array('columns'=>array('id'), 'where'=>array('id'=>$sid))) !== null); }
            catch (\Throwable $t) { $owned = false; error_log('worker_settlement.php add_line own: ' . $t->getMessage()); }
        }
        if (!$owned) { ems_gov_flash_redirect('worker_settlement.php', 'التسوية خارج نطاق الشركة ❌', 'GOV-SCOPE-403', ''); exit(); }
        try {
            // ابن T_CHILD: البوابة تتحقق من ملكية الأب (settlement_id) قبل الإدراج
            $ws_gate->insert('worker_settlement_line', array(
                'settlement_id' => $sid, 'line_type' => $lt, 'description' => $desc, 'amount' => $amt));
        } catch (\Throwable $t) { error_log('worker_settlement.php add_line: ' . $t->getMessage()); }
    }
    ems_gov_redirect("Location: worker_settlement.php?edit=".$sid."&msg=✅+تم+حفظ+البند"); exit();
}
if (($_GET['del_line']??'')!=='' && $can_delete) { $lid=intval($_GET['del_line']); $sid=intval($_GET['edit']??0);
    // عزل الشركة عبر التسوية الأب (worker_settlement_line بلا company_id): يُستحضَر أبو
    // السطر الحقيقي عبر البوابة (المعزول بالأب) ثم يُحذف بقيد الأب+الملكية (deleteChild).
    try {
        $ws_line = $ws_gate->selectOne('worker_settlement_line', array('where' => array('id' => $lid)));
        if ($ws_line !== null) {
            $ws_gate->deleteChild('worker_settlement_line', $lid,
                'worker_settlement', intval($ws_line['settlement_id']), 'settlement_id', 'settlement line delete');
        }
    } catch (\Throwable $t) { error_log('worker_settlement.php del_line: ' . $t->getMessage()); }
    ems_gov_redirect("Location: worker_settlement.php?edit=".$sid."&msg=✅+تم+حذف+البند"); exit(); }
if (($_GET['delete']??'')!=='' && $can_delete) { $d=intval($_GET['delete']);
    try { $ws_gate->deleteRow('worker_settlement', $d, 'settlement delete'); }
    catch (\Throwable $t) { error_log('worker_settlement.php delete: ' . $t->getMessage()); }
    ems_gov_flash_redirect('worker_settlement.php', '✅ تم الحذف', 'GOV-OK-200', ''); exit(); }

$edit=null; $lines=[]; $edit_id=intval($_GET['edit']??0);
if ($edit_id>0) {
    try {
        $ws_edit_rows = $ws_gate->scopedQuery(array('scope'=>array('ws'=>'worker_settlement'), 'enrich'=>array('e'=>'employees')),
            "SELECT ws.*, e.name AS wname FROM worker_settlement ws LEFT JOIN employees e ON e.id=ws.employee_id WHERE ws.id=? AND {TENANT_SCOPE} LIMIT 1",
            array($edit_id));
        $edit = !empty($ws_edit_rows) ? $ws_edit_rows[0] : null;
        if ($edit) {
            $lines = $ws_gate->select('worker_settlement_line', array('where' => array('settlement_id' => $edit_id), 'orderBy' => 'id'));
        }
    } catch (\Throwable $t) { error_log('worker_settlement.php edit: ' . $t->getMessage()); }
}
$workers=[];
try {
    $ws_workers = $ws_gate->scopedQuery(array('scope'=>array('wp'=>'employees')),
        "SELECT wp.id,wp.name AS name FROM employees wp WHERE 1=1 AND {TENANT_SCOPE} ORDER BY wp.name");
    foreach($ws_workers as $w){$workers[$w['id']]=$w['name'];}
} catch (\Throwable $t) { error_log('worker_settlement.php workers: ' . $t->getMessage()); }

$page_title="إيكوبيشن | تسويات العاملين"; 
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php'; include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main">
    <?php $header_title='تسويات العاملين'; $header_icon='fas fa-hand-holding-dollar'; $header_actions=array();
    if($can_add) $header_actions[]=array('id'=>'toggleForm','class'=>'add-btn','icon'=>'fas fa-plus-circle','label'=>'تسوية جديدة');
    $header_back=array('href'=>'worker_register.php','class'=>'','icon'=>'fas fa-arrow-right','label'=>'سجل العامل');
    include('../includes/page_header.php'); ?>
    <?php // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا تسويات عاملين مسجلة في هذا السجل العرضي', 'أعد التسوية من شاشة «تسويات الموظفين» الموحدة لتظهر هنا'); ?>
    <style>
        .ws-notice-body { border-right: 4px solid var(--c-f59e0b, #f59e0b); }
        .ws-notice-text { color: var(--c-note-ink); margin: 0; line-height: var(--leading-loose); }
        .ws-notice-link { margin-top: var(--space-2); }
        .ws-form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-3); padding: 14px; }
        .ws-span-full { grid-column: 1 / -1; }
        .ws-form-actions { padding: 0 14px 16px; display: flex; gap: 10px; }
        .ws-cancel-btn { background: var(--c-ink-500); }
        .ws-lines-card { display: block; }
        .ws-line-actions { display: flex; align-items: flex-end; }
        .ws-table-wrap { margin-top: 14px; }
        .ws-table-full { width: 100%; }
        .ws-empty-line { text-align: center; color: var(--c-888, #888); }
        .ws-empty-cell { text-align: center; color: var(--c-888, #888); padding: 18px; }
    </style>
    <?php /* ── إحالةٌ إلى المسار الموحّد (E-02 · 2026-07-29) ──────────────────
       هذه الشاشةُ مسارُ كتابةٍ ثانٍ بإدخالٍ يدويٍّ حرٍّ للمبالغ — ومبدأُ النظام
       «الإدخالُ مرةً واحدةً في المنبع وما سواه اشتقاق». فأُحيلت إلى العرض
       (سُحبت مِنحُ الإضافة والتعديل بترحيل 2026_07_29)، والعملُ في الشاشة
       الموحّدة التي تجلب البنودَ من مصادرها. ولم يُمسّ صفٌّ: الجدولُ فارغٌ
       أصلًا (صفرُ صفٍّ مقيسٌ 2026-07-29) والبنيةُ باقيةٌ كما هي. */ ?>
    <div class="card"><div class="card-body ws-notice-body">
        <p class="ws-notice-text">
            <i class="fas fa-triangle-exclamation"></i>
            <strong>هذه الشاشة صارت للعرض فقط.</strong>
            تسوية الموظف تعد الآن من الشاشة الموحدة التي <strong>تجلب البنود من
            مصادرها</strong> — استحقاقه وتحميلاته من دفتر ذممه بروابط أصولها،
            بلا إدخال مبلغ باليد، وبفصل يدين: من يعد لا يجيز.
            <br>
            <a class="btn btn-sm btn-primary ws-notice-link"
               href="employee_settlements.php">
               <i class="fas fa-arrow-left"></i> اذهب إلى «تسويات الموظفين»</a>
        </p>
    </div></div>
    <?php if(!empty($_GET['msg'])): $ok=strpos($_GET['msg'],'✅')!==false; ?>
        <div class="success-message <?= $ok?'is-success':'is-error' ?>"><i class="fas <?= $ok?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <form id="sForm" action="" method="post" class="allforms<?= $edit?' allforms-visible':'' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit?intval($edit['id']):0 ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?= $edit?'تعديل تسوية':'تسوية جديدة' ?></h5></div>
        <div class="ws-form-grid">
            <div class="field"><label for="ws_worker">الموظف</label><?php if($edit): ?><input type="text" id="ws_worker" aria-label="اسم الموظف صاحب التسوية" value="<?= htmlspecialchars($edit['wname'] ?: ('#'.$edit['employee_id'])) ?>" disabled><?php else: ?><select name="worker_id" id="ws_worker" required><option value="">—</option><?php foreach($workers as $wid=>$wn): ?><option value="<?= intval($wid) ?>"><?= htmlspecialchars($wn) ?></option><?php endforeach; ?></select><?php endif; ?></div>
            <div class="field"><label for="ws_source_type">المصدر</label><select name="source_type" id="ws_source_type"><option value="">—</option><?php foreach(['شركة','مورد','مقاول'] as $s): ?><option value="<?= $s ?>" <?= (($edit['source_type']??'')===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="emsf_624_8d8b1">أساس التسوية</label><select name="settlement_basis" id="emsf_624_8d8b1"><option value="">—</option><?php foreach(['عمالة شركة','فاتورة مورد','مستخلص مقاول'] as $b): ?><option value="<?= $b ?>" <?= (($edit['settlement_basis']??'')===$b)?'selected':'' ?>><?= $b ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="emsf_625_12a01">الحالة</label><select name="state" id="emsf_625_12a01"><?php foreach($STATES as $s): ?><option value="<?= $s ?>" <?= (($edit['state']??'محتسب')===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="emsf_626_d9937">جهة التسوية</label><input type="text" name="settlement_party" id="emsf_626_d9937" aria-label="جهة التسوية" value="<?= htmlspecialchars($edit['settlement_party'] ?? '') ?>"></div>
            <div class="field"><label for="emsf_627_607ef">عقد مرتبط (#)</label><input type="number" name="worker_contract_id" id="emsf_627_607ef" aria-label="رقم العقد المرتبط" value="<?= htmlspecialchars($edit['worker_contract_id'] ?? '') ?>"></div>
            <div class="field"><label for="emsf_628_39c52">الصافي (مالي — يدوي)</label><input type="number" step="0.01" name="net_amount" id="emsf_628_39c52" aria-label="صافي التسوية" value="<?= htmlspecialchars($edit['net_amount'] ?? '') ?>"></div>
            <div class="field"><label for="emsf_629_386b4">تعليق مالي</label><input type="text" name="net_finance_note" id="emsf_629_386b4" aria-label="تعليق المالية على الصافي" value="<?= htmlspecialchars($edit['net_finance_note'] ?? '') ?>"></div>
            <div class="field ws-span-full"><label for="emsf_630_ac9d5">ملاحظات</label><input type="text" name="notes" id="emsf_630_ac9d5" aria-label="ملاحظات على التسوية" value="<?= htmlspecialchars($edit['notes'] ?? '') ?>"></div>
        </div>
        <div class="ws-form-actions"><button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button><a href="worker_settlement.php" class="add-btn ws-cancel-btn"><i class="fas fa-times"></i> إلغاء</a></div>
    </form>

    <?php if ($edit): $sum=0; foreach($lines as $l){ $sum += ($l['line_type']==='خصم'?-1:1)*floatval($l['amount']); } ?>
    <div class="allforms ws-lines-card">
        <div class="card-header"><h5><i class="fas fa-list"></i> بنود المستحقات والخصومات — الصافي المحسوب: <?= number_format($sum,2) ?></h5></div>
        <form action="" method="post" class="ws-form-grid">
        <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_line"><input type="hidden" name="settlement_id" value="<?= intval($edit['id']) ?>">
            <div class="field"><label for="emsf_631_06f0f">النوع</label><select name="line_type" id="emsf_631_06f0f"><option>مستحق</option><option>خصم</option></select></div>
            <div class="field"><label for="emsf_632_4e9a8">الوصف</label><input type="text" name="description" id="emsf_632_4e9a8"></div>
            <div class="field"><label for="emsf_633_c75a6">المبلغ</label><input type="number" step="0.01" name="amount" id="emsf_633_c75a6"></div>
            <div class="field ws-line-actions"><button type="submit" class="add-btn"><i class="fas fa-plus"></i> إضافة بند</button></div>
        </form>
        <table class="data-table ws-table-full"><thead><tr><th>النوع</th><th>الوصف</th><th>المبلغ</th><th></th></tr></thead><tbody>
        <?php if(empty($lines)): ?><tr><td colspan="4" class="ws-empty-line">لا بنود</td></tr><?php else: foreach($lines as $l): ?>
            <tr><td><span class="badge badge-info"><?= htmlspecialchars($l['line_type']) ?></span></td><td><?= htmlspecialchars($l['description'] ?: '-') ?></td><td><?= htmlspecialchars($l['amount'] ?: '0') ?></td>
            <td><?php if($can_delete): ?><a href="worker_settlement.php?edit=<?= intval($edit['id']) ?>&del_line=<?= intval($l['id']) ?>" class="action-btn delete" onclick="return confirm('حذف البند؟')"><i class="fas fa-trash"></i></a><?php endif; ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody></table>
    </div>
    <?php endif; ?>

    <div class="table-wrap ws-table-wrap"><table class="data-table ws-table-full">
        <thead><tr><th>إجراءات</th><th>#</th><th>الموظف</th><th>المصدر</th><th>الأساس</th><th>الصافي</th><th>الحالة</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              </tr></thead><tbody>
        <?php $list = array();
        try {
            $list = $ws_gate->scopedQuery(array('scope'=>array('ws'=>'worker_settlement'), 'enrich'=>array('e'=>'employees')),
                "SELECT ws.*, e.name AS wname FROM worker_settlement ws LEFT JOIN employees e ON e.id=ws.employee_id WHERE 1=1 AND {TENANT_SCOPE} ORDER BY ws.id DESC");
        } catch (\Throwable $t) { error_log('worker_settlement.php list: ' . $t->getMessage()); }
        $i=1; $WF_VIEW = []; if($list){ foreach($list as $r): $i++; $sc=($r['state']==='مدفوع')?'status-active':(($r['state']==='معتمد')?'status-warning':'status-inactive');
            $WF_VIEW[$r['id']] = ems_wf_view_payload('تفاصيل التسوية', 'fas fa-hand-holding-dollar', [
                ems_wf_field('الموظف', $r['wname'] ?: '-', 'fas fa-user', ['size' => 'lg']),
                ems_wf_field('المصدر', $r['source_type'] ?: '-', 'fas fa-sitemap'),
                ems_wf_field('أساس التسوية', $r['settlement_basis'] ?: '-', 'fas fa-scale-balanced'),
                ems_wf_field('جهة التسوية', $r['settlement_party'] ?: '-', 'fas fa-building'),
                ems_wf_field('عقد مرتبط', $r['worker_contract_id'] ? ('#' . intval($r['worker_contract_id'])) : '-', 'fas fa-file-signature'),
                ems_wf_field('الصافي', $r['net_amount'] !== null ? $r['net_amount'] : '-', 'fas fa-sack-dollar'),
                ems_wf_field('تعليق مالي', $r['net_finance_note'] ?: '-', 'fas fa-comment-dollar', ['size' => 'lg']),
                ems_wf_field('الحالة', $r['state'], 'fas fa-flag', ['type' => 'status']),
                ems_wf_field('ملاحظات', $r['notes'] ?: '-', 'fas fa-align-right', ['size' => 'full']),
            ]); ?>
            <tr><td><div class="action-btns">
                <?= ems_wf_view_button($r['id']) ?>
                <?php if($can_edit): ?><a href="worker_settlement.php?edit=<?= intval($r['id']) ?>" class="action-btn edit"><i class="fas fa-edit"></i></a><?php endif; ?>
                <?php if($can_delete): ?><a href="worker_settlement.php?delete=<?= intval($r['id']) ?>" class="action-btn delete" onclick="return confirm('حذف التسوية؟')"><i class="fas fa-trash"></i></a><?php endif; ?>
            </div></td>
            <td><?= intval($r['id']) ?></td><td><strong><?= htmlspecialchars($r['wname'] ?: '-') ?></strong></td>
            <td><?= htmlspecialchars($r['source_type'] ?: '-') ?></td><td><?= htmlspecialchars($r['settlement_basis'] ?: '-') ?></td>
            <td><?= htmlspecialchars($r['net_amount'] ?: '-') ?></td>
            <td><span class="status-pill <?= $sc ?>"><?= htmlspecialchars($r['state']) ?></span></td></tr>
        <?php endforeach; } if(!$list||$i===1): ?><tr><td colspan="7" class="ws-empty-cell">لا توجد تسويات بعد.</td></tr><?php endif; ?>
        </tbody></table></div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
<script>(function(){var b=document.getElementById('toggleForm'),f=document.getElementById('sForm');if(b&&f)b.addEventListener('click',function(){f.classList.toggle("allforms-visible");});})();</script>
</body></html>
