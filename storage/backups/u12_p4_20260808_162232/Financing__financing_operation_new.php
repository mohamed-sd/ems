<?php
/**
 * Financing/financing_operation_new.php — إنشاء عملية تمويل (FIN-01 §8-② · الشاشة 211)
 * ───────────────────────────────────────────────────────────────────────────
 * باب التمويل خلف بوابة المجال المقيَّد (DEC-01 ②). معالج: **النموذج أولًا
 * فيحدد بقية الحقول والمعالجة المحاسبية** — ولا اعتماد بلا نموذج ومعالجة
 * مكتوبة (FinancingService يرفض 422). الأقساط تُولَّد ولا تُدخل يدويًّا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';
require_once dirname(__DIR__) . '/app/Core/OwnershipDomainGuard.php';
require_once dirname(__DIR__) . '/app/Services/Financing/FinancingService.php';

use App\Core\OwnershipDomainGuard;
use App\Services\Financing\FinancingService;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$role = strval($_SESSION['user']['role'] ?? '');
$uid = intval($_SESSION['user']['id'] ?? 0);
$co = $company_id ?: 4;

// بوابة المجال المقيَّد — الإنشاء يتطلب رؤية شروط التمويل تحديدًا
$granted = ($role === '-1') || OwnershipDomainGuard::hasGrant($conn, $co, $uid, OwnershipDomainGuard::PERM_TERMS);
if (!$granted) {
    http_response_code(403);
    exit('403 — إنشاء عمليات التمويل يتطلب منحة ownership.finance_terms الفردية (FIN-01 §1.1)');
}

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = FinancingService::createOperation($conn, $co, array(
        'op_code' => trim(strval($_POST['op_code'] ?? '')),
        'financier_entity_id' => intval($_POST['financier_entity_id'] ?? 0),
        'model_code' => strval($_POST['model_code'] ?? ''),
        'currency' => trim(strval($_POST['currency'] ?? '')),
        'signed_date' => strval($_POST['signed_date'] ?? date('Y-m-d')),
        'capital' => floatval($_POST['capital'] ?? 0),
        'purchase_value' => ($_POST['purchase_value'] ?? '') !== '' ? floatval($_POST['purchase_value']) : null,
        'down_payment' => floatval($_POST['down_payment'] ?? 0),
        'profit_rate' => ($_POST['profit_rate'] ?? '') !== '' ? floatval($_POST['profit_rate']) : null,
        'profit_amount' => ($_POST['profit_amount'] ?? '') !== '' ? floatval($_POST['profit_amount']) : null,
        'installments_no' => intval($_POST['installments_no'] ?? 0),
        'installment_amount' => ($_POST['installment_amount'] ?? '') !== '' ? floatval($_POST['installment_amount']) : null,
        'maturity_date' => ($_POST['maturity_date'] ?? '') !== '' ? strval($_POST['maturity_date']) : null,
    ), $uid);
    if ($r['ok']) {
        $msg = $r['reason'];
        if (intval($_POST['installments_no'] ?? 0) > 0 && ($_POST['first_due'] ?? '') !== '') {
            $g = FinancingService::generateInstallments($conn, $co, $r['op_id'], strval($_POST['first_due']));
            $msg .= ' · ' . $g['reason'];
        }
    } else { $err = $r['reason']; }
}

$models = $conn->query("SELECT model_code, name_ar, legal_owner_effect, accounting_recognition, depreciation_bearer, policy_doc_ref FROM financing_models WHERE active = 1 ORDER BY model_code")->fetch_all(MYSQLI_ASSOC);
$financiers = $conn->query(
    "SELECT e.entity_id, e.legal_name FROM legal_entities e
       JOIN entity_roles r ON r.entity_id = e.entity_id AND r.role = 'financier'
            AND (r.valid_to IS NULL OR r.valid_to >= CURDATE())
      WHERE e.state = 'active' ORDER BY e.legal_name"
)->fetch_all(MYSQLI_ASSOC);

$page_title = 'إيكوبيشن | إنشاء عملية تمويل';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'إنشاء عملية تمويل — النموذج أولًا'; $header_icon = 'fa fa-money-check-dollar';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('النموذج أولًا فيحدد بقية الحقول والمعالجة المحاسبية: المرابحة أصل ملموس والتزام، '
        . 'والإجارة التشغيلية حق استخدام لا أصل مملوك — ولو عوملت كالمرابحة لتضخمت الأصول والالتزامات '
        . 'معًا. لا اعتماد بلا نموذج ومعالجة مكتوبة، والأقساط تُولَّد من العملية ولا تُدخل يدويًّا.',
        array('اختر النموذج قبل كل شيء', 'المعالجة المحاسبية تظهر تحت كل نموذج'));
    if ($msg !== '') { echo '<div class="alert alert-success">' . htmlspecialchars($msg) . '</div>'; }
    if ($err !== '') { echo '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>'; }
    ?>
    <div class="card"><div class="card-body">
        <h4>① النموذج — يحدد المعالجة قبل أي حقل</h4>
        <div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">
        <thead><tr><th></th><th>النموذج</th><th>أثر الملكية</th><th>الاعتراف المحاسبي</th><th>حامل الإهلاك</th><th>السياسة المكتوبة</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead><tbody>
        <?php foreach ($models as $m): ?>
        <tr>
            <td><input type="radio" name="model_pick" value="<?php echo htmlspecialchars($m['model_code']); ?>" form="opform"
                       onclick="document.getElementById('model_code').value=this.value"></td>
            <td><strong><?php echo htmlspecialchars($m['name_ar']); ?></strong><br><small><?php echo htmlspecialchars($m['model_code']); ?></small></td>
            <td><?php echo htmlspecialchars($m['legal_owner_effect']); ?></td>
            <td><?php echo htmlspecialchars($m['accounting_recognition']); ?></td>
            <td><?php echo htmlspecialchars((string) $m['depreciation_bearer']); ?></td>
            <td><small><?php echo htmlspecialchars((string) $m['policy_doc_ref']) ?: '<span class="badge badge-danger">بلا سياسة — لن يُقبل</span>'; ?></small></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div></div>

    <div class="card"><div class="card-body">
        <h4>② العملية — رأس المال والعائد والأقساط</h4>
        <form method="post" id="opform" class="ems-form" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
            <input type="hidden" name="model_code" id="model_code" value="">
            <input type="text" name="op_code" placeholder="كود العملية *" required>
            <select name="financier_entity_id" required>
                <option value="">— الممول *</option>
                <?php foreach ($financiers as $f): ?>
                <option value="<?php echo intval($f['entity_id']); ?>"><?php echo htmlspecialchars($f['legal_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="currency" placeholder="العملة *" required>
            <input type="date" name="signed_date" value="<?php echo date('Y-m-d'); ?>">
            <input type="number" step="0.01" name="capital" placeholder="رأس المال *" required>
            <input type="number" step="0.01" name="purchase_value" placeholder="قيمة شراء العين">
            <input type="number" step="0.01" name="down_payment" placeholder="المقدم" value="0">
            <input type="number" step="0.01" name="profit_rate" placeholder="نسبة الأرباح ٪">
            <input type="number" step="0.01" name="profit_amount" placeholder="قيمة الأرباح">
            <input type="number" name="installments_no" placeholder="عدد الأقساط">
            <input type="number" step="0.01" name="installment_amount" placeholder="قيمة القسط (تُحسب إن تُركت)">
            <input type="date" name="first_due" title="استحقاق أول قسط — لتوليد الجدول آليًّا">
            <input type="date" name="maturity_date" title="تاريخ النهاية">
            <button class="btn-save" type="submit" style="grid-column:span 4"
                    onclick="if(!document.getElementById('model_code').value){alert('النموذج أولًا — اختره من الجدول أعلاه');return false;}">
                إنشاء العملية وتوليد أقساطها
            </button>
        </form>
    </div></div>
</div>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
