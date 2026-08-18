<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
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

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
require_once __DIR__ . '/../app/Services/Payroll/OffsetService.php';

use App\Services\Payroll\OffsetService as OFS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض سلفيات الموظفين ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('advances super') : ems_tenant_db();

$TYPE_LABELS  = array('cash' => 'سلفةٌ نقدية', 'on_behalf' => 'دفعٌ نيابةً عنه', 'charged' => 'مصروفٌ محمَّلٌ عليه');
$STATE_LABELS = array('draft' => 'مسودة', 'approved' => 'معتمَدة', 'active' => 'نشطة',
                      'settled' => 'مستردَّة', 'cancelled' => 'ملغاة');

$redirect = function ($msg) { ems_gov_flash_redirect('employee_advances.php', $msg, 'GOV-INFO-200', ''); exit(); };

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
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
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
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا سلفياتِ موظفين مفتوحةً بعدُ', 'افتحْ أولَ سلفةٍ بزرِّ «سلفة جديدة» في رأسِ الشاشة');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>
    <style>
        .adv-note-muted { color: var(--c-s-666); }
        .adv-required-mark { color: var(--c-state-danger-strong); }
        .adv-protection-form { display: flex; gap: var(--space-2); align-items: center; margin-top: 10px; flex-wrap: wrap; }
        .adv-protection-input { max-width: 180px; }
        .adv-submit-row { margin-top: var(--space-3); }
        .adv-table-full { width: 100%; }
        .adv-inline-form { display: inline; }
    </style>

    <div class="card"><div class="card-body">
        <strong>حدُّ حمايةِ الصافي:</strong>
        <?php if ($protection === null): ?>
            <span class="badge badge-warning">لم يُقرَّر بعد</span>
            <span class="adv-note-muted"> — وحتى يُقرَّر يُخصم القسطُ كاملًا، ولا يُفترض حدٌّ لم يقرّره أحد.</span>
        <?php else: ?>
            <span class="badge badge-success"><?php echo htmlspecialchars((string)$protection); ?>٪</span>
            <span class="adv-note-muted"> — لا ينزل صافي العامل تحت هذه النسبة من إجماليه؛ وما لا يسعه الحدُّ
                <strong>يُرحَّل</strong> للفترة التالية ولا يُلغى.</span>
        <?php endif; ?>
        <?php if ($can_edit): ?>
        <form method="post" class="adv-protection-form">
        <?= csrf_field() ?>
            <input type="hidden" name="ad_action" value="set_protection">
            <input type="number" step="0.01" min="0" max="100" name="protection_percent" class="adv-protection-input"
                   placeholder="فارغٌ = غيرُ مقرَّر" aria-label="فارغٌ = غيرُ مقرَّر"
                   value="<?php echo $protection === null ? '' : htmlspecialchars((string)$protection); ?>">
            <button type="submit" class="btn-primary"><i class="fa fa-shield-halved"></i> حفظ الحد</button>
        </form>
        <?php endif; ?>
    </div></div>

    <?php if ($can_add): ?>
    <form method="post" class="allforms" id="advForm">
        <?= csrf_field() ?>
        <input type="hidden" name="ad_action" value="open">
        <div class="card"><div class="card-header"><h5><i class="fa fa-hand-holding-dollar"></i> سلفةٌ جديدة</h5></div>
        <div class="card-body"><div class="form-grid">
            <div class="form-group">
                <label for="emsf_1675_61b58">المستفيد <span class="adv-required-mark">*</span></label>
                <select name="person_id" required id="emsf_1675_61b58">
                    <option value="">— اختر —</option>
                    <?php foreach ($people as $p): ?>
                        <option value="<?php echo intval($p['id']); ?>">
                            #<?php echo intval($p['id']); ?> — <?php echo htmlspecialchars((string)$p['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="emsf_1676_f721b">النوع</label>
                <select name="advance_type" id="emsf_1676_f721b">
                    <?php foreach ($TYPE_LABELS as $k => $lbl): ?>
                        <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="emsf_1677_95bc0">المبلغ <span class="adv-required-mark">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" required id="emsf_1677_95bc0"></div>
            <div class="form-group"><label for="emsf_1678_244f8">العملة</label><input type="text" name="currency" maxlength="8" id="emsf_1678_244f8"></div>
            <div class="form-group"><label for="emsf_1679_3b65c">مستند الصرف <span class="adv-required-mark">*</span>
                    <small>— «كلٌّ بمستنده»</small></label>
                <input type="text" name="doc_ref" required maxlength="120" placeholder="إذنُ صرف 2049/221" id="emsf_1679_3b65c"></div>
            <div class="form-group"><label for="emsf_1680_f09a4">تاريخ الصرف <span class="adv-required-mark">*</span></label>
                <input type="date" name="issued_date" required id="emsf_1680_f09a4"></div>
            <div class="form-group"><label for="emsf_1681_8f91d">عدد الأقساط</label>
                <input type="number" min="1" name="installments_count" value="1" id="emsf_1681_8f91d"></div>
            <div class="form-group"><label for="emsf_1682_8818a">قسط الفترة <small>— فارغٌ = المبلغ ÷ الأقساط</small></label>
                <input type="number" step="0.01" min="0.01" name="installment_amount" id="emsf_1682_8818a"></div>
            <div class="form-group"><label for="emsf_1683_dd69b">أول فترة خصم</label>
                <input type="date" name="first_deduction_period" id="emsf_1683_dd69b"></div>
            <div class="form-group"><label for="emsf_1684_dd952">ملاحظة</label><input type="text" name="note" maxlength="255" id="emsf_1684_dd952"></div>
        </div>
        <div class="adv-submit-row"><button type="submit" class="btn-primary"><i class="fa fa-save"></i> فتح السلفة</button></div>
        </div></div>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> السلفيات وأرصدتُها</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap adv-table-full">
            <thead><tr>
                <th>الإجراءات</th><th>المستفيد</th><th>النوع</th><th>المبلغ</th>
                <th>قيمة القسط</th><th>المستردّ</th><th>الرصيد</th><th>المستند</th><th>الحالة</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم السلفة</th>
                <th class="ems-fn-th" data-fn="1">كود الموظف</th>
                <th class="ems-fn-th" data-fn="1">تاريخ الطلب</th>
                <th class="ems-fn-th" data-fn="1">سبب السلفة</th>
                <th class="ems-fn-th" data-fn="1">عدد أقساط الاستقطاع</th>
                <th class="ems-fn-th" data-fn="1">تاريخ بدء الاستقطاع</th>
                <th class="ems-fn-th" data-fn="1">المسدَّد</th>
                <th class="ems-fn-th" data-fn="1">المتبقي</th>
                <th class="ems-fn-th" data-fn="1">اعتماد المدير</th>
                <th class="ems-fn-th" data-fn="1">الاعتماد المالي</th>
                <th class="ems-fn-th" data-fn="1">تاريخ الصرف</th>
                <th class="ems-fn-th" data-fn="1">رقم سند الصرف</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                </tr></thead>
            <tbody>
            <?php foreach ($advances as $a): ?>
                <tr>
                    <td>
                    <?php if ($can_edit && (string)$a['state'] === 'draft'): ?>
                        <form method="post" class="adv-inline-form">
        <?= csrf_field() ?>
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
