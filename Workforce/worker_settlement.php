<?php
/** EQUIP-OPE-S04 — 8.7 تسوية العامل التشغيلي (+ بنود المستحقات/الخصومات). Bolt-on. */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=بيئة+شركة+غير+صالحة+❌"); exit(); }

$pp = check_page_permissions($conn, 'Workforce/worker_settlement.php');
$can_view=$pp['can_view']; $can_add=$pp['can_add']; $can_edit=$pp['can_edit']; $can_delete=$pp['can_delete'];
if (!$can_view) { header("Location: ../login.php?msg=لا+توجد+صلاحية+❌"); exit(); }
// العزل عبر بوابة المستأجر — والسوبر يمرّ عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$ws_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('worker settlement super') : ems_tenant_db();
$STATES=['محتسب','معتمد','مدفوع'];

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save') {
    $id=intval($_POST['id']??0); $is_editing=$id>0;
    if (($is_editing && !$can_edit) || (!$is_editing && !$can_add)) { header("Location: worker_settlement.php?msg=لا+صلاحية+❌"); exit(); }
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
        if ($worker_id<=0) { header("Location: worker_settlement.php?msg=يجب+اختيار+عامل+❌"); exit(); }
        $nid=0;
        try {
            // company_id تحقنه البوابة من سياق الجلسة، وتعيد المعرّف الوليد
            $nid = intval($ws_gate->insert('worker_settlement', array(
                'employee_id' => $worker_id, 'worker_contract_id' => $contract_id, 'source_type' => $source,
                'settlement_party' => $party, 'settlement_basis' => $basis, 'net_amount' => $net,
                'net_finance_note' => $net_note, 'state' => $state, 'notes' => $notes, 'created_by' => $user_id)));
        } catch (\Throwable $t) { error_log('worker_settlement.php insert: ' . $t->getMessage()); }
        header("Location: worker_settlement.php?edit=".$nid."&msg=✅+تم+الحفظ"); exit();
    } else {
        try {
            $ws_gate->update('worker_settlement', array(
                'worker_contract_id' => $contract_id, 'source_type' => $source, 'settlement_party' => $party,
                'settlement_basis' => $basis, 'net_amount' => $net, 'net_finance_note' => $net_note,
                'state' => $state, 'notes' => $notes), array('id' => $id));
        } catch (\Throwable $t) { error_log('worker_settlement.php update: ' . $t->getMessage()); }
        header("Location: worker_settlement.php?edit=".$id."&msg=✅+تم+التحديث"); exit();
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
        if (!$owned) { header("Location: worker_settlement.php?msg=التسوية+خارج+نطاق+الشركة+❌"); exit(); }
        try {
            // ابن T_CHILD: البوابة تتحقق من ملكية الأب (settlement_id) قبل الإدراج
            $ws_gate->insert('worker_settlement_line', array(
                'settlement_id' => $sid, 'line_type' => $lt, 'description' => $desc, 'amount' => $amt));
        } catch (\Throwable $t) { error_log('worker_settlement.php add_line: ' . $t->getMessage()); }
    }
    header("Location: worker_settlement.php?edit=".$sid."&msg=✅+تم+حفظ+البند"); exit();
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
    header("Location: worker_settlement.php?edit=".$sid."&msg=✅+تم+حذف+البند"); exit(); }
if (($_GET['delete']??'')!=='' && $can_delete) { $d=intval($_GET['delete']);
    try { $ws_gate->deleteRow('worker_settlement', $d, 'settlement delete'); }
    catch (\Throwable $t) { error_log('worker_settlement.php delete: ' . $t->getMessage()); }
    header("Location: worker_settlement.php?msg=✅+تم+الحذف"); exit(); }

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

$page_title="إيكوبيشن | تسويات العاملين"; include '../inheader.php'; include '../insidebar.php';
?>
<div class="main">
    <?php $header_title='تسويات العاملين'; $header_icon='fas fa-hand-holding-dollar'; $header_actions=array();
    if($can_add) $header_actions[]=array('id'=>'toggleForm','class'=>'add-btn','icon'=>'fas fa-plus-circle','label'=>'تسوية جديدة');
    $header_back=array('href'=>'worker_register.php','class'=>'','icon'=>'fas fa-arrow-right','label'=>'سجل العامل');
    include('../includes/page_header.php'); ?>
    <?php if(!empty($_GET['msg'])): $ok=strpos($_GET['msg'],'✅')!==false; ?>
        <div class="success-message <?= $ok?'is-success':'is-error' ?>"><i class="fas <?= $ok?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <form id="sForm" action="" method="post" class="allforms" style="<?= $edit?'display:block;':'display:none;' ?>">
        <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit?intval($edit['id']):0 ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?= $edit?'تعديل تسوية':'تسوية جديدة' ?></h5></div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:14px;">
            <div class="field"><label>الموظف</label><?php if($edit): ?><input type="text" value="<?= htmlspecialchars($edit['wname'] ?: ('#'.$edit['employee_id'])) ?>" disabled><?php else: ?><select name="worker_id" required><option value="">—</option><?php foreach($workers as $wid=>$wn): ?><option value="<?= intval($wid) ?>"><?= htmlspecialchars($wn) ?></option><?php endforeach; ?></select><?php endif; ?></div>
            <div class="field"><label>المصدر</label><select name="source_type"><option value="">—</option><?php foreach(['شركة','مورد','مقاول'] as $s): ?><option value="<?= $s ?>" <?= (($edit['source_type']??'')===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>أساس التسوية</label><select name="settlement_basis"><option value="">—</option><?php foreach(['عمالة شركة','فاتورة مورد','مستخلص مقاول'] as $b): ?><option value="<?= $b ?>" <?= (($edit['settlement_basis']??'')===$b)?'selected':'' ?>><?= $b ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>الحالة</label><select name="state"><?php foreach($STATES as $s): ?><option value="<?= $s ?>" <?= (($edit['state']??'محتسب')===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>جهة التسوية</label><input type="text" name="settlement_party" value="<?= htmlspecialchars($edit['settlement_party'] ?? '') ?>"></div>
            <div class="field"><label>عقد مرتبط (#)</label><input type="number" name="worker_contract_id" value="<?= htmlspecialchars($edit['worker_contract_id'] ?? '') ?>"></div>
            <div class="field"><label>الصافي (مالي — يدوي)</label><input type="number" step="0.01" name="net_amount" value="<?= htmlspecialchars($edit['net_amount'] ?? '') ?>"></div>
            <div class="field"><label>تعليق مالي</label><input type="text" name="net_finance_note" value="<?= htmlspecialchars($edit['net_finance_note'] ?? '') ?>"></div>
            <div class="field" style="grid-column:1/-1;"><label>ملاحظات</label><input type="text" name="notes" value="<?= htmlspecialchars($edit['notes'] ?? '') ?>"></div>
        </div>
        <div style="padding:0 14px 16px;display:flex;gap:10px;"><button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button><a href="worker_settlement.php" class="add-btn" style="background:#6b7280;"><i class="fas fa-times"></i> إلغاء</a></div>
    </form>

    <?php if ($edit): $sum=0; foreach($lines as $l){ $sum += ($l['line_type']==='خصم'?-1:1)*floatval($l['amount']); } ?>
    <div class="allforms" style="display:block;">
        <div class="card-header"><h5><i class="fas fa-list"></i> بنود المستحقات والخصومات — الصافي المحسوب: <?= number_format($sum,2) ?></h5></div>
        <form action="" method="post" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:14px;">
            <input type="hidden" name="action" value="add_line"><input type="hidden" name="settlement_id" value="<?= intval($edit['id']) ?>">
            <div class="field"><label>النوع</label><select name="line_type"><option>مستحق</option><option>خصم</option></select></div>
            <div class="field"><label>الوصف</label><input type="text" name="description"></div>
            <div class="field"><label>المبلغ</label><input type="number" step="0.01" name="amount"></div>
            <div class="field" style="display:flex;align-items:flex-end;"><button type="submit" class="add-btn"><i class="fas fa-plus"></i> إضافة بند</button></div>
        </form>
        <table class="data-table" style="width:100%;"><thead><tr><th>النوع</th><th>الوصف</th><th>المبلغ</th><th></th></tr></thead><tbody>
        <?php if(empty($lines)): ?><tr><td colspan="4" style="text-align:center;color:#888;">لا بنود</td></tr><?php else: foreach($lines as $l): ?>
            <tr><td><span class="badge badge-info"><?= htmlspecialchars($l['line_type']) ?></span></td><td><?= htmlspecialchars($l['description'] ?: '-') ?></td><td><?= htmlspecialchars($l['amount'] ?: '0') ?></td>
            <td><?php if($can_delete): ?><a href="worker_settlement.php?edit=<?= intval($edit['id']) ?>&del_line=<?= intval($l['id']) ?>" class="action-btn delete" onclick="return confirm('حذف البند؟')"><i class="fas fa-trash"></i></a><?php endif; ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody></table>
    </div>
    <?php endif; ?>

    <div class="table-wrap" style="margin-top:14px;"><table class="data-table" style="width:100%;">
        <thead><tr><th>إجراءات</th><th>#</th><th>الموظف</th><th>المصدر</th><th>الأساس</th><th>الصافي</th><th>الحالة</th></tr></thead><tbody>
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
        <?php endforeach; } if(!$list||$i===1): ?><tr><td colspan="7" style="text-align:center;color:#888;padding:18px;">لا توجد تسوياتٌ بعد.</td></tr><?php endif; ?>
        </tbody></table></div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
<script>(function(){var b=document.getElementById('toggleForm'),f=document.getElementById('sForm');if(b&&f)b.addEventListener('click',function(){f.style.display=(f.style.display==='none'||!f.style.display)?'block':'none';});})();</script>
</body></html>
