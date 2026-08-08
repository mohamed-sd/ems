<?php
/**
 * Suppliers/supplier_documents.php — وثائقُ المورد وحسابُه البنكي (M-19)
 * ───────────────────────────────────────────────────────────────────────────
 * UX-05 §5.1-①: «الهوية والوثائق **بتواريخ صلاحيتها** (تنبيهٌ آلي قبل
 * الانتهاء) — والحقولُ النظامية **أعمدةٌ واجبة** (السجل التجاري · الضريبي ·
 * **الحساب البنكي الموثَّق**)».
 *
 * وسجلُّ التدقيق أسفلَها **قراءةٌ على `activity_logs`** — سجلٌّ واحدٌ يُقرأ
 * بمرشِّح نطاقه لا سجلٌّ ثانٍ يتباعد.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Supplier/SupplierDocumentService.php';

use App\Services\Supplier\SupplierDocumentService as SDS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$MODULE_CODE = 'Suppliers/supplier_documents.php';
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
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+وثائق+الموردين+❌");
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier documents super') : ems_tenant_db();

$selected = intval($_GET['supplier_id'] ?? 0);
$redirect = function ($msg, $sid) { header("Location: supplier_documents.php?supplier_id=" . intval($sid)
    . "&msg=" . rawurlencode($msg)); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strval($_POST['sd_action'] ?? '');
    $sid = intval($_POST['supplier_id'] ?? 0);
    if (!$can_add && !$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌', $sid); }

    if ($action === 'document') {
        $r = SDS::saveDocument($conn, $gate, $company_id, $sid, array(
            'doc_type'    => $_POST['doc_type'] ?? '',
            'doc_no'      => $_POST['doc_no'] ?? '',
            'issuer'      => $_POST['issuer'] ?? '',
            'issue_date'  => $_POST['issue_date'] ?? '',
            'expiry_date' => $_POST['expiry_date'] ?? '',
            'alert_days'  => $_POST['alert_days'] ?? '',
            'file_ref'    => $_POST['file_ref'] ?? '',
            'note'        => $_POST['dnote'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? 'حُفظت الوثيقة ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $sid);
    }

    if ($action === 'bank') {
        $r = SDS::verifyBank($conn, $gate, $company_id, $sid, array(
            'bank_name'       => $_POST['bank_name'] ?? '',
            'bank_account_no' => $_POST['bank_account_no'] ?? '',
            'bank_iban'       => $_POST['bank_iban'] ?? '',
            'bank_doc_ref'    => $_POST['bank_doc_ref'] ?? '',
        ), $uid);
        $redirect($r['ok'] ? 'وُثِّق الحسابُ البنكي ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $sid);
    }
}

// ── القراءة ────────────────────────────────────────────────────────────────
$suppliers = array();
try {
    $suppliers = $gate->scopedQuery(array('scope' => array('s' => 'suppliers')),
        "SELECT s.id, s.name FROM suppliers s
          WHERE {TENANT_SCOPE} AND COALESCE(s.is_deleted,0)=0 ORDER BY s.name");
} catch (\Throwable $t) { $suppliers = array(); }
if ($selected <= 0 && $suppliers) { $selected = intval($suppliers[0]['id']); }

$sup = null;
if ($selected > 0) {
    try { $sup = $gate->selectOne('suppliers', array('where' => array('id' => $selected))); }
    catch (\Throwable $t) { $sup = null; }
}
$docs  = $selected > 0 ? SDS::documentsOf($gate, $selected) : array();
$state = $selected > 0 ? SDS::documentState($gate, $selected) : array('expired' => array(),
    'expiring' => array(), 'missing' => array(), 'as_of' => date('Y-m-d'));
$gateInfo = $selected > 0 ? SDS::gateFor($gate, $selected) : array('reasons' => array(), 'mode' => 'off', 'blocked' => false);
$audit = $selected > 0 ? SDS::auditOf($conn, $company_id, $selected, 60) : array();

$page_title = 'إيكوبيشن | وثائق المورد وحسابه البنكي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف المورد الأم
$sf_supplier_id = intval($_GET['supplier_id'] ?? $_GET['id'] ?? 0); $sf_active = 'documents';
if ($sf_supplier_id > 0) include __DIR__ . '/../includes/supplier_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'وثائق المورد وحسابه البنكي'; $header_icon = 'fa fa-id-card';
    $header_actions = array();
    $header_back = array('href' => 'supplier_closure.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'تصفية الإنهاء');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>

    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <strong>المورد:</strong>
            <select name="supplier_id" onchange="this.form.submit()" style="min-width:320px">
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?php echo intval($s['id']); ?>" <?php echo $selected === intval($s['id']) ? 'selected' : ''; ?>>
                        #<?php echo intval($s['id']); ?> — <?php echo htmlspecialchars((string)$s['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="alert <?php echo $gateInfo['reasons'] ? 'alert-warning' : 'alert-success'; ?>" style="margin-top:10px">
            <strong>بوابةُ المستندات (<?php echo htmlspecialchars($gateInfo['mode']); ?>):</strong>
            <?php if (!$gateInfo['reasons']): ?>
                لا مانعَ — الوثائقُ النظاميةُ حاضرةٌ ساريةٌ والحسابُ موثَّق.
            <?php else: ?>
                <ul style="margin:6px 0 0 0">
                <?php foreach ($gateInfo['reasons'] as $rr): ?>
                    <li><?php echo htmlspecialchars($rr); ?></li>
                <?php endforeach; ?>
                </ul>
                <small><?php echo $gateInfo['blocked']
                    ? 'الوضعُ enforce: اعتمادُ تسويته يُرفض حتى تُستكمل.'
                    : 'الوضعُ monitor: يُقاس ويُعلَن ويمرّ — ولا يُقلب إلى enforce إلا بعد أسبوعِ رصدٍ نظيف.'; ?></small>
            <?php endif; ?>
        </div>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-building-columns"></i> الحسابُ البنكيُّ الموثَّق</h5></div>
    <div class="card-body">
        <p style="color:#666"><strong>توثيقٌ بلا مستندٍ دعوى</strong> — رقمُ الحساب والمستندُ إلزاميان معًا،
            ويحرسهما <code>CHECK</code> فوق حارس الخدمة.</p>
        <?php if ($sup !== null): ?>
        <p>
            الحالة:
            <?php if ($sup['bank_verified_at'] !== null): ?>
                <span class="badge badge-success">موثَّق في <?php echo htmlspecialchars((string)$sup['bank_verified_at']); ?></span>
                · مستند <?php echo htmlspecialchars((string)$sup['bank_doc_ref']); ?>
                · حساب <?php echo htmlspecialchars((string)$sup['bank_account_no']); ?>
            <?php else: ?>
                <span class="badge badge-danger">غيرُ موثَّق</span>
            <?php endif; ?>
        </p>
        <?php endif; ?>
        <?php if ($can_edit): ?>
        <form method="post" class="ems-form">
            <input type="hidden" name="sd_action" value="bank">
            <input type="hidden" name="supplier_id" value="<?php echo $selected; ?>">
            <div class="form-grid">
                <div class="form-group"><label>البنك</label>
                    <input type="text" name="bank_name" maxlength="150"
                           value="<?php echo htmlspecialchars((string)($sup['bank_name'] ?? '')); ?>"></div>
                <div class="form-group"><label>رقم الحساب <span style="color:#c00">*</span></label>
                    <input type="text" name="bank_account_no" maxlength="60" required
                           value="<?php echo htmlspecialchars((string)($sup['bank_account_no'] ?? '')); ?>"></div>
                <div class="form-group"><label>IBAN</label>
                    <input type="text" name="bank_iban" maxlength="60"
                           value="<?php echo htmlspecialchars((string)($sup['bank_iban'] ?? '')); ?>"></div>
                <div class="form-group"><label>مستند التوثيق <span style="color:#c00">*</span></label>
                    <input type="text" name="bank_doc_ref" maxlength="120" required
                           placeholder="شهادةٌ بنكيةٌ أو شيكٌ ملغًى"></div>
            </div>
            <div style="margin-top:12px"><button type="submit" class="btn-save"><i class="fa fa-shield-halved"></i> وثِّق الحساب</button></div>
        </form>
        <?php endif; ?>
    </div></div>

    <?php if ($can_add): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-file-lines"></i> وثيقةٌ نظامية</h5></div>
    <div class="card-body">
        <p style="color:#666">«الوثائقُ <strong>بتواريخ صلاحيتها</strong> — تنبيهٌ آليٌّ قبل الانتهاء»:
            و<strong>السجلُّ التجاري والشهادةُ الضريبية يلزمهما تاريخُ صلاحية</strong>،
            فتنبيهٌ بلا تاريخٍ وعدٌ لا يُنفَّذ.</p>
        <form method="post" class="ems-form">
            <input type="hidden" name="sd_action" value="document">
            <input type="hidden" name="supplier_id" value="<?php echo $selected; ?>">
            <div class="form-grid">
                <div class="form-group"><label>النوع <span style="color:#c00">*</span></label>
                    <select name="doc_type" required>
                        <?php foreach (SDS::SUPPLIER_DOC_TYPES as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>الرقم <span style="color:#c00">*</span></label>
                    <input type="text" name="doc_no" maxlength="100" required></div>
                <div class="form-group"><label>جهة الإصدار</label><input type="text" name="issuer" maxlength="255"></div>
                <div class="form-group"><label>تاريخ الإصدار</label><input type="date" name="issue_date"></div>
                <div class="form-group"><label>تاريخ الانتهاء</label><input type="date" name="expiry_date"></div>
                <div class="form-group"><label>التنبيه قبل (أيام)</label>
                    <input type="number" min="1" step="1" name="alert_days" value="30"></div>
                <div class="form-group"><label>المرفق</label><input type="text" name="file_ref" maxlength="255"></div>
                <div class="form-group"><label>ملاحظة</label><input type="text" name="dnote" maxlength="200"></div>
            </div>
            <div style="margin-top:12px"><button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ الوثيقة</button></div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-bell"></i>
        وثائقُ المورد — منتهية <?php echo count($state['expired']); ?>
        · تُوشك <?php echo count($state['expiring']); ?>
        · ناقصة <?php echo count($state['missing']); ?></h5></div>
    <div class="card-body">
        <?php if ($state['missing']): ?>
            <div class="alert alert-danger">وثائقُ نظاميةٌ ناقصة:
                <strong><?php echo htmlspecialchars(implode(' · ', $state['missing'])); ?></strong></div>
        <?php endif; ?>
        <div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>النوع</th><th>الرقم</th><th>الجهة</th><th>الإصدار</th>
                <th>الانتهاء</th><th>التنبيه</th><th>الحال</th>
                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                </tr></thead>
            <tbody>
            <?php foreach ($docs as $d):
                $exp = $d['expiry_date'];
                $cls = 'badge-success'; $lbl = 'سارية';
                if ($exp === null) { $cls = 'badge-secondary'; $lbl = 'لا تنتهي'; }
                elseif ((string)$exp < $state['as_of']) { $cls = 'badge-danger'; $lbl = 'منتهية'; }
                else {
                    $alertFrom = date('Y-m-d', strtotime((string)$exp . ' -' . intval($d['alert_days']) . ' days'));
                    if ($state['as_of'] >= $alertFrom) { $cls = 'badge-warning'; $lbl = 'تُوشك'; }
                }
            ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)$d['doc_type']); ?></td>
                    <td><?php echo htmlspecialchars((string)$d['doc_no']); ?></td>
                    <td><?php echo htmlspecialchars((string)($d['issuer'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($d['issue_date'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($exp ?? '—')); ?></td>
                    <td><?php echo intval($d['alert_days']); ?> يومًا</td>
                    <td><span class="badge <?php echo $cls; ?>"><?php echo $lbl; ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-clock-rotate-left"></i>
        سجلُّ تدقيق المورد <small>— قراءةٌ على <code>activity_logs</code>، لا سجلٌّ ثانٍ</small></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>متى</th><th>أين</th><th>ماذا</th><th>قبل</th><th>بعد</th><th>من</th></tr></thead>
            <tbody>
            <?php foreach ($audit as $a): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)$a['created_at']); ?></td>
                    <td><?php echo htmlspecialchars((string)$a['screen_name']); ?>
                        #<?php echo intval($a['record_id']); ?></td>
                    <td><?php echo htmlspecialchars((string)$a['action_type']); ?></td>
                    <td><small><?php echo htmlspecialchars(mb_substr((string)($a['old_value'] ?? ''), 0, 120)); ?></small></td>
                    <td><small><?php echo htmlspecialchars(mb_substr((string)($a['new_value'] ?? ''), 0, 120)); ?></small></td>
                    <td><?php echo intval($a['user_id']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
