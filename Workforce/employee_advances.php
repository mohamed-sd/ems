<?php
/**
 * Workforce/employee_advances.php — بوابةُ السلفيات (H-09-④ · ENT-01 §4)
 * ───────────────────────────────────────────────────────────────────────────
 * «**بوابةٌ واحدةٌ لكل ما يُصرف خارج المسيّر**: سلفةٌ نقديةٌ · دفعٌ نيابةً عن
 * العامل · مصروفٌ محمَّلٌ عليه — **كلٌّ بمستنده وجدولِ استرداده**»، و«الرصيدُ
 * المتبقي ظاهرٌ دائمًا» (§7).
 *
 * كلُّ فعلٍ عبر `OffsetService` — والاعتمادُ بيدٍ غيرِ يد المنشئ.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Payroll/OffsetService.php';

use App\Services\Payroll\OffsetService as OFS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$MODULE_CODE = 'Workforce/employee_advances.php';
$can_view = $can_add = $can_edit = false;
if ($is_super_admin) {
    $can_view = $can_add = $can_edit = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit
                            FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = (intval($row['can_view']) === 1);
        $can_add  = (intval($row['can_add'])  === 1);
        $can_edit = (intval($row['can_edit']) === 1);
    }
    $st->close();
}
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+سلفيات+الموظفين+❌");
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('advances super') : ems_tenant_db();

$TYPE_LABELS  = array('cash' => 'سلفةٌ نقدية', 'on_behalf' => 'دفعٌ نيابةً عنه', 'charged' => 'مصروفٌ محمَّلٌ عليه');
$STATE_LABELS = array('draft' => 'مسودة', 'approved' => 'معتمَدة', 'active' => 'نشطة',
                      'settled' => 'مستردَّة', 'cancelled' => 'ملغاة');

$redirect = function ($msg) { header("Location: employee_advances.php?msg=" . rawurlencode($msg)); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strval($_POST['ad_action'] ?? '');

    if ($action === 'open') {
        if (!$can_add) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
        $r = OFS::openAdvance($conn, $gate, $company_id, array(
            'person_id'          => $_POST['person_id'] ?? 0,
            'advance_type'       => $_POST['advance_type'] ?? 'cash',
            'amount'             => $_POST['amount'] ?? 0,
            'currency'           => $_POST['currency'] ?? '',
            'doc_ref'            => $_POST['doc_ref'] ?? '',
            'issued_date'        => $_POST['issued_date'] ?? '',
            'installments_count' => $_POST['installments_count'] ?? 1,
            'installment_amount' => $_POST['installment_amount'] ?? '',
            'first_deduction_period' => $_POST['first_deduction_period'] ?? '',
            'note'               => $_POST['note'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? 'فُتحت السلفة (مسودة) — تنتظر اعتمادَ غيرِ منشئها ✅'
                           : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }

    if ($action === 'approve') {
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
        $r = OFS::approveAdvance($conn, $gate, $company_id, intval($_POST['advance_id'] ?? 0), $uid);
        $redirect($r['ok'] ? 'اعتُمدت السلفة — تُخصم أقساطُها في المسيّر ✅'
                           : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }

    if ($action === 'set_protection') {
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
        $raw = trim(strval($_POST['protection_percent'] ?? ''));
        $val = ($raw === '') ? null : round(floatval($raw), 2);
        if ($val !== null && ($val < 0 || $val > 100)) { $redirect('نسبةُ الحماية بين 0 و100 ❌'); }
        $exists = $conn->query("SELECT id FROM payroll_settings WHERE company_id=" . intval($company_id))->fetch_assoc();
        $sql = $val === null ? 'NULL' : $val;
        if ($exists) {
            $conn->query("UPDATE payroll_settings SET protection_percent={$sql}, updated_by="
                         . intval($uid) . " WHERE company_id=" . intval($company_id));
        } else {
            $conn->query("INSERT INTO payroll_settings (company_id, protection_percent, updated_by)
                          VALUES (" . intval($company_id) . ", {$sql}, " . intval($uid) . ")");
        }
        $redirect($val === null ? 'أُلغي حدُّ الحماية — يُعلَن أنه غيرُ مقرَّر ✅'
                                : ('حدُّ الحماية صار ' . $val . '٪ ✅'));
    }
}

// ── القراءة ────────────────────────────────────────────────────────────────
$advances = OFS::advancesOf($gate, 0);
$protection = OFS::protectionPercent($gate);

$people = array();
try {
    $people = $gate->scopedQuery(array('scope' => array('e' => 'employees')),
        "SELECT e.id, e.name FROM employees e WHERE {TENANT_SCOPE} ORDER BY e.name LIMIT 500");
} catch (\Throwable $t) { $people = array(); }
$nameOf = array();
foreach ($people as $p) { $nameOf[(int) $p['id']] = (string) $p['name']; }

$page_title = 'إيكوبيشن | سلفيات الموظفين';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'سلفيات الموظفين'; $header_icon = 'fa fa-hand-holding-dollar';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('href' => 'javascript:void(0)', 'id' => 'toggleAdvForm',
            'icon' => 'fa fa-plus', 'label' => 'سلفة جديدة', 'class' => 'add');
    }
    $header_back = array('href' => 'payroll_runs.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'مسيّر الرواتب');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>

    <div class="card"><div class="card-body">
        <strong>حدُّ حمايةِ الصافي:</strong>
        <?php if ($protection === null): ?>
            <span class="badge badge-warning">لم يُقرَّر بعد</span>
            <span style="color:#666"> — وحتى يُقرَّر يُخصم القسطُ كاملًا، ولا يُفترض حدٌّ لم يقرّره أحد.</span>
        <?php else: ?>
            <span class="badge badge-success"><?php echo htmlspecialchars((string)$protection); ?>٪</span>
            <span style="color:#666"> — لا ينزل صافي العامل تحت هذه النسبة من إجماليه؛ وما لا يسعه الحدُّ
                <strong>يُرحَّل</strong> للفترة التالية ولا يُلغى.</span>
        <?php endif; ?>
        <?php if ($can_edit): ?>
        <form method="post" style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap">
            <input type="hidden" name="ad_action" value="set_protection">
            <input type="number" step="0.01" min="0" max="100" name="protection_percent"
                   value="<?php echo $protection === null ? '' : htmlspecialchars((string)$protection); ?>"
                   placeholder="فارغٌ = غيرُ مقرَّر" style="max-width:180px">
            <button type="submit" class="btn-save"><i class="fa fa-shield-halved"></i> حفظ الحد</button>
        </form>
        <?php endif; ?>
    </div></div>

    <?php if ($can_add): ?>
    <form method="post" class="allforms" id="advForm">
        <input type="hidden" name="ad_action" value="open">
        <div class="card"><div class="card-header"><h5><i class="fa fa-hand-holding-dollar"></i> سلفةٌ جديدة</h5></div>
        <div class="card-body"><div class="form-grid">
            <div class="form-group">
                <label>المستفيد <span style="color:#c00">*</span></label>
                <select name="person_id" required>
                    <option value="">— اختر —</option>
                    <?php foreach ($people as $p): ?>
                        <option value="<?php echo intval($p['id']); ?>">
                            #<?php echo intval($p['id']); ?> — <?php echo htmlspecialchars((string)$p['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>النوع</label>
                <select name="advance_type">
                    <?php foreach ($TYPE_LABELS as $k => $lbl): ?>
                        <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>المبلغ <span style="color:#c00">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" required></div>
            <div class="form-group"><label>العملة</label><input type="text" name="currency" maxlength="8"></div>
            <div class="form-group"><label>مستند الصرف <span style="color:#c00">*</span>
                    <small>— «كلٌّ بمستنده»</small></label>
                <input type="text" name="doc_ref" required maxlength="120" placeholder="إذنُ صرف 2049/221"></div>
            <div class="form-group"><label>تاريخ الصرف <span style="color:#c00">*</span></label>
                <input type="date" name="issued_date" required></div>
            <div class="form-group"><label>عدد الأقساط</label>
                <input type="number" min="1" name="installments_count" value="1"></div>
            <div class="form-group"><label>قسط الفترة <small>— فارغٌ = المبلغ ÷ الأقساط</small></label>
                <input type="number" step="0.01" min="0.01" name="installment_amount"></div>
            <div class="form-group"><label>أول فترة خصم</label>
                <input type="date" name="first_deduction_period"></div>
            <div class="form-group"><label>ملاحظة</label><input type="text" name="note" maxlength="255"></div>
        </div>
        <div style="margin-top:12px"><button type="submit" class="btn-save"><i class="fa fa-save"></i> فتح السلفة</button></div>
        </div></div>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> السلفيات وأرصدتُها</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr>
                <th>الإجراءات</th><th>المستفيد</th><th>النوع</th><th>المبلغ</th>
                <th>القسط</th><th>المستردّ</th><th>الرصيد</th><th>المستند</th><th>الحالة</th>
            </tr></thead>
            <tbody>
            <?php foreach ($advances as $a): ?>
                <tr>
                    <td>
                    <?php if ($can_edit && (string)$a['state'] === 'draft'): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="ad_action" value="approve">
                            <input type="hidden" name="advance_id" value="<?php echo intval($a['id']); ?>">
                            <button type="submit" class="action-btn edit" title="اعتماد (من أنشأ لا يعتمد)">
                                <i class="fas fa-check"></i></button>
                        </form>
                    <?php endif; ?>
                    </td>
                    <td>#<?php echo intval($a['person_id']); ?>
                        <?php echo htmlspecialchars(isset($nameOf[(int)$a['person_id']])
                                    ? ' — ' . $nameOf[(int)$a['person_id']] : ''); ?></td>
                    <td><?php echo htmlspecialchars($TYPE_LABELS[$a['advance_type']] ?? $a['advance_type']); ?></td>
                    <td><?php echo htmlspecialchars((string)$a['amount']); ?></td>
                    <td><?php echo htmlspecialchars((string)$a['installment_amount']); ?>
                        <small>× <?php echo intval($a['installments_count']); ?></small></td>
                    <td><?php echo htmlspecialchars((string)$a['recovered']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$a['balance']); ?></strong></td>
                    <td><small><?php echo htmlspecialchars((string)$a['doc_ref']); ?></small></td>
                    <td><?php
                        $s = (string) $a['state'];
                        $cls = $s === 'settled' ? 'badge-success' : ($s === 'draft' ? 'badge-warning' : 'badge-info');
                        echo "<span class='badge {$cls}'>" . htmlspecialchars($STATE_LABELS[$s] ?? $s) . '</span>';
                    ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var b = document.getElementById('toggleAdvForm'), f = document.getElementById('advForm');
        if (b && f) { b.addEventListener('click', function () { f.classList.toggle('allforms-visible'); }); }
    });
})();
</script>
</body>
</html>
