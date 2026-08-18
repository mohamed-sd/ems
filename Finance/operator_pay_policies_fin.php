<?php
/**
 * Finance/operator_pay_policies_fin.php — سياسات مستحقات المشغّلين (UX-02 §8.2)
 * ───────────────────────────────────────────────────────────────────────────
 * «تُعاد جدولَ سياساتٍ يدعم النماذج الثلاثة والأسس السبعة» — هذه الشاشة تدير
 * `contract_hour_policies` بوضع party_scope=operator (UX-02 §15.2-ج — جدولٌ
 * واحدٌ بوضعَين: حكمُ الساعة للعميل/المورد وسياسةُ المشغّل) الذي يقرؤه OperatorDue:
 *   المستحق = Σ (كمية الأساس × معدله) مقصوصةً بالحدين — والأخصُّ نطاقًا يغلب.
 *
 * قاعدة الغلبة (قرار المالك 2026-07-26): السياسةُ تغلب — والمسار القديم
 * (fin_operator_pay «بالراتب/بالمستحق») يبقى سقوطًا معلَنًا حين لا سياسة.
 * الإيقاف soft (deleted_at) — والسياسات التجريبية (is_trial) موسومةٌ ظاهرًا
 * وتُستبدل قيمُها قبل أي استعمالٍ حقيقي.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';
require_once __DIR__ . '/../app/Services/Payroll/PayPolicyStateMachine.php';

use App\Services\Payroll\PayPolicyStateMachine as PPS;

$ctx             = fin_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = fin_page_perms($conn, 'Finance/operator_pay_policies_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_edit = $perms['can_edit'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض سياسات المشغّلين ❌', 'GOV-PERM-403', '');
    exit();
}

$MODELS = array('hour' => 'ساعة', 'ton' => 'طن', 'trip' => 'نقلة', 'meter' => 'متر');
$BASES  = array('actual' => 'تشغيل فعلي', 'standby' => 'استعداد', 'attendance' => 'حضور',
                'ton' => 'طن', 'trip' => 'نقلة', 'meter' => 'متر', 'composite' => 'مركّب');

// ── إيقاف سياسة (soft — تبقى في السجل التاريخي) ──
if (isset($_GET['stop_policy'])) {
    if (!$can_edit) { ems_gov_flash_redirect('operator_pay_policies_fin.php', 'لا توجد صلاحية الضبط ❌', 'GOV-PERM-403', ''); exit(); }
    $pid = intval($_GET['stop_policy']);
    try {
        fin_gate($is_super_admin)->softDelete('contract_hour_policies', $pid);
        ems_gov_flash_redirect('operator_pay_policies_fin.php', 'أُوقفت السياسة ✅', 'GOV-OK-200', '');
    } catch (\Throwable $t) {
        error_log('operator_pay_policies stop: ' . $t->getMessage());
        ems_gov_flash_redirect('operator_pay_policies_fin.php', 'تعذّر الإيقاف ❌', 'GOV-FAIL-409', '');
    }
    exit();
}

// ── تفعيلُ سياسةٍ مسودة (E-24) — وبه يقع الإخلاف ──
if (isset($_GET['activate_policy'])) {
    if (!$can_edit) { ems_gov_flash_redirect('operator_pay_policies_fin.php', 'لا توجد صلاحية الضبط ❌', 'GOV-PERM-403', ''); exit(); }
    $r = PPS::activate($conn, fin_gate($is_super_admin), $company_id,
                       intval($_GET['activate_policy']), $current_user_id);
    $m = $r['ok']
        ? ('فُعّلت السياسة ✅' . (count($r['superseded']) > 0
            ? (' · أُخلفت ' . count($r['superseded']) . ' سياسةً سابقةً بسريانها') : ''))
        : ($r['code'] . ' — ' . $r['reason'] . ' ❌');
    ems_gov_flash_redirect('operator_pay_policies_fin.php', $m, 'GOV-INFO-200', '');
    exit();
}

// ── إنهاءُ سياسةٍ بسببه (E-24) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['expire_policy'])) {
    if (!$can_edit) { ems_gov_flash_redirect('operator_pay_policies_fin.php', 'لا توجد صلاحية الضبط ❌', 'GOV-PERM-403', ''); exit(); }
    $r = PPS::expire($conn, fin_gate($is_super_admin), $company_id,
                     intval($_POST['policy_id'] ?? 0), strval($_POST['expire_reason'] ?? ''),
                     $current_user_id);
    ems_gov_flash_redirect('operator_pay_policies_fin.php', $r['ok'] ? 'أُنهيت السياسةُ بسببها المكتوب ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), 'GOV-OK-200', '');
    exit();
}

// ── إضافة سياسة ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_policy'])) {
    if (!$can_edit) { ems_gov_flash_redirect('operator_pay_policies_fin.php', 'لا توجد صلاحية الضبط ❌', 'GOV-PERM-403', ''); exit(); }

    $emp   = intval($_POST['employee_id'] ?? 0);
    $model = strval($_POST['work_model'] ?? '');
    $basis = strval($_POST['basis'] ?? '');
    $rateRaw = trim(strval($_POST['rate'] ?? ''));
    $cur   = trim(strval($_POST['currency'] ?? 'SDG'));

    $err = null;
    if ($emp <= 0)                        { $err = 'اختر المشغّل'; }
    elseif (!isset($MODELS[$model]))      { $err = 'اختر نموذج العمل'; }
    elseif (!isset($BASES[$basis]))       { $err = 'اختر أساس الاستحقاق'; }
    elseif ($rateRaw === '' || !is_numeric($rateRaw) || (float) $rateRaw <= 0) { $err = 'المعدّل رقمٌ موجبٌ إلزامي'; }
    elseif ($cur === '')                  { $err = 'العملة إلزامية'; }
    if ($err !== null) { ems_gov_flash_redirect('operator_pay_policies_fin.php', "{$err} ❌", 'GOV-FAIL-409', ''); exit(); }

    $optNum = function ($k) {
        $v = trim(strval($_POST[$k] ?? ''));
        return ($v !== '' && is_numeric($v) && (float) $v >= 0) ? round((float) $v, 2) : null;
    };
    $optDate = function ($k) {
        $v = trim(strval($_POST[$k] ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    };
    // النطاقُ بأسماء الوثيقة: scope_type + scope_id (لا عمودان منفصلان)
    $scopeProj = intval($_POST['scope_project_id'] ?? 0);
    $scopeType = intval($_POST['scope_equipment_type'] ?? 0);
    $row = array(
        'party_scope' => 'operator',       // وضعُ سياسة المشغّل في الجدول الموحّد
        'operator_id' => $emp,
        'work_model'  => $model,
        'pay_basis'   => $basis,
        'rate'        => round((float) $rateRaw, 4),
        'currency'    => mb_substr($cur, 0, 10),
        'min_amount'  => $optNum('min_amount'),
        'max_amount'  => $optNum('max_amount'),
        'effective_from' => $optDate('effective_from'),
        'effective_to'   => $optDate('effective_to'),
        'scope_type'  => $scopeProj > 0 ? 'project' : ($scopeType > 0 ? 'equip_type' : null),
        'scope_id'    => $scopeProj > 0 ? $scopeProj : ($scopeType > 0 ? $scopeType : null),
        'ops_state'   => null,             // لا حالةَ ساعةٍ لصف المشغّل
        'ruling'      => 'full',           // إلزاميٌّ في الجدول — محايدٌ لهذا الوضع
        'deductions_note' => mb_substr(trim(strval($_POST['deductions_note'] ?? '')), 0, 200) ?: null,
        'exceptions_note' => mb_substr(trim(strval($_POST['exceptions_note'] ?? '')), 0, 200) ?: null,
        'note'        => mb_substr(trim(strval($_POST['note'] ?? '')), 0, 200) ?: null,
        'is_trial'    => isset($_POST['is_trial']) ? 1 : 0,
        // E-24: **تُحفظ مسودةً** — والتفعيلُ فعلٌ ثانٍ يُخلِف ما قبله بسريانه.
        // والاعتمادُ يقع مع التفعيل لا مع الحفظ (مسودةٌ معتمدةٌ تناقض).
        'policy_state' => 'draft',
        'created_by'  => $current_user_id,
    );
    try {
        fin_gate($is_super_admin)->insert('contract_hour_policies', $row);
        ems_gov_flash_redirect('operator_pay_policies_fin.php', 'حُفظت مسودةُ السياسة — فعّلها لتسري ✅', 'GOV-OK-200', '');
    } catch (\Throwable $t) {
        error_log('operator_pay_policies add: ' . $t->getMessage());
        ems_gov_flash_redirect('operator_pay_policies_fin.php', 'تعذّرت الإضافة ❌', 'GOV-FAIL-409', '');
    }
    exit();
}

// ── القراءات ──
$gate = fin_gate($is_super_admin);

// E-24: تصريحُ ما انقضى سريانُه — **تصريحٌ بما وقع لا قرارٌ جديد**، ولا يقع
// إلا بيدٍ لها الضبط (فالقارئُ لا يكتب).
if ($can_edit) { PPS::sweepExpired($gate); }

// السياسات (الحيّة والموقوفة تُعرضان — الموقوفة موسومة)
$policies = $gate->scopedQuery(
    array('scope' => array('p' => 'contract_hour_policies'), 'enrich' => array('e' => 'employees', 'pr' => 'project')),
    "SELECT p.*, p.pay_basis AS basis, e.name AS emp_name, pr.name AS proj_name,
            CASE WHEN p.scope_type = 'project' THEN p.scope_id END AS scope_project_id,
            CASE WHEN p.scope_type = 'equip_type' THEN p.scope_id END AS scope_equipment_type
       FROM contract_hour_policies p
       LEFT JOIN employees e ON e.id = p.operator_id
       LEFT JOIN project pr ON pr.id = (CASE WHEN p.scope_type = 'project' THEN p.scope_id END)
      WHERE {TENANT_SCOPE} AND p.party_scope = 'operator'
      ORDER BY (p.deleted_at IS NOT NULL) ASC, e.name ASC, p.pay_basis ASC");

// المشغّلون (من سجل الدوام — نفس مصدر الشاشة القديمة)
$operators = $gate->scopedQuery(
    array('scope' => array('t' => 'timesheet'), 'enrich' => array('e' => 'employees')),
    "SELECT DISTINCT t.employee_id, e.name
     FROM timesheet t LEFT JOIN employees e ON e.id = t.employee_id
     WHERE {TENANT_SCOPE} AND t.employee_id IS NOT NULL AND t.employee_id <> ''
     ORDER BY e.name ASC");

// المشاريع ونوعا النطاق
$projects = $gate->scopedQuery(
    array('scope' => array('p' => 'project')),
    "SELECT p.id, p.name FROM project p WHERE {TENANT_SCOPE} AND (p.is_deleted = 0 OR p.is_deleted IS NULL) ORDER BY p.name");
$equipTypes = array();
try {
    $etr = $conn->query("SELECT id, name FROM equipments_types ORDER BY id");
    if ($etr) { while ($et = $etr->fetch_assoc()) { $equipTypes[intval($et['id'])] = strval($et['name']); } }
} catch (\Throwable $t) { /* قاموسٌ عام — غيابه لا يكسر الشاشة */ }

$liveN = 0; $trialN = 0; $draftN = 0; $supN = 0;
foreach ($policies as $p) {
    $ps = strval($p['policy_state']);
    if ($ps === 'draft') { $draftN++; }
    if ($ps === 'superseded') { $supN++; }
    if ($p['deleted_at'] === null && $ps === 'active') {
        $liveN++; if (intval($p['is_trial']) === 1) { $trialN++; }
    }
}

$page_title = 'إيكوبيشن | سياسات مستحقات المشغّلين';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main fin-oppol-main ems-unified-page-shell">
    <?php
    $header_title = 'سياسات مستحقات المشغّلين';
    $header_icon  = 'fa fa-scale-balanced';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا سياساتِ استحقاقٍ للمشغّلين بعدُ', 'أضف أولى السياساتِ من نموذجِ «سياسة جديدة» أعلاه ثم فعّلها لتسري');
    ?>
    <style>
        /* UXW-01 ②: أصنافُ الصفحةِ بدلَ الأنماطِ الموضعية — ألوانُها رموزٌ حصرًا */
        .fin-oppol-intro { color: var(--c-4b5563); margin: 0 0 12px; line-height: 1.8; }
        .fin-oppol-badges { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .fin-oppol-badge { padding: 6px 12px; }
        .fin-oppol-h5 { margin: 0 0 10px; }
        .fin-oppol-form { box-shadow: none; padding: 0; }
        .fin-oppol-check-label { display: flex; align-items: center; gap: 8px; }
        .fin-oppol-check { width: auto; }
        .fin-oppol-tbl { width: 100%; }
        .fin-oppol-stopped { opacity: .55; }
        .fin-oppol-ltr { direction: ltr; }
        .fin-oppol-note { color: var(--c-ink-500); }
        .fin-oppol-wrap { white-space: normal; }
        .fin-oppol-act { text-decoration: none; padding: 5px 10px; }
        .fin-oppol-inline-form { display: flex; gap: 6px; align-items: center; }
        .fin-oppol-reason { width: 150px; }
        .fin-oppol-stopbtn { border: 0; padding: 5px 10px; }
        .fin-oppol-emptynote { color: var(--c-ink-500); text-align: center; padding: 16px; }
    </style>

    <?php fin_msg_banner(); ?>

    <div class="card"><div class="card-body">
        <p class="fin-oppol-intro">
            <i class="fas fa-circle-info"></i>
            لكل مشغّلٍ <strong>سياسةُ استحقاقٍ</strong> بنموذج عمله (ساعة/طن/نقلة/متر) وأساسه
            (تشغيل فعلي · استعداد · حضور · إنتاج) ومعدله وحدَّيه ونطاقه —
            والمستحق = <code>Σ كمية الأساس × معدله</code> مقصوصًا بالحدين، والأخصُّ نطاقًا يغلب.
            <strong>السياسةُ تغلب</strong>؛ ومن لا سياسةَ له يُقرأ من
            <a href="operator_pay_fin.php">الوضع القديم (بالراتب/بالمستحق)</a> مؤقتًا.
        </p>
        <div class="fin-oppol-badges">
            <span class="badge badge-success fin-oppol-badge"><?php echo $liveN; ?> سياسة نافذة</span>
            <?php if ($draftN > 0): ?>
            <span class="badge badge-secondary fin-oppol-badge">
                <i class="fas fa-pen"></i> <?php echo $draftN; ?> مسودة — <strong>لا تسعّر شيئًا حتى تُفعَّل</strong>
            </span>
            <?php endif; ?>
            <?php if ($supN > 0): ?>
            <span class="badge badge-info fin-oppol-badge">
                <?php echo $supN; ?> مستبدَلة — تبقى حاكمةً لما قبل سريان خَلَفها
            </span>
            <?php endif; ?>
            <?php if ($trialN > 0): ?>
            <span class="badge badge-warning fin-oppol-badge">
                <i class="fas fa-flask"></i> <?php echo $trialN; ?> تجريبية — استبدل قيمَها قبل الاستعمال الحقيقي
            </span>
            <?php endif; ?>
        </div>
    </div></div>

    <?php if ($can_edit): ?>
    <div class="card"><div class="card-body">
        <h5 class="fin-oppol-h5"><i class="fas fa-plus"></i> سياسة جديدة</h5>
        <form action="" method="post" class="allforms allforms-visible fin-oppol-form">
        <?php echo csrf_field(); ?>
            <input type="hidden" name="add_policy" value="1">
            <div class="form-section"><div class="form-grid">
                <div class="form-group">
                    <label for="oppol_employee_id">المشغّل *</label>
                    <select id="oppol_employee_id" name="employee_id" required>
                        <option value="">— اختر —</option>
                        <?php foreach ($operators as $op) {
                            $eid = intval($op['employee_id']);
                            $nm = ($op['name'] !== null && $op['name'] !== '') ? $op['name'] : ('#' . $eid);
                            echo "<option value='{$eid}'>" . htmlspecialchars($nm) . "</option>";
                        } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="oppol_work_model">نموذج العمل *</label>
                    <select id="oppol_work_model" name="work_model" required>
                        <?php foreach ($MODELS as $k => $v) { echo "<option value='{$k}'>{$v}</option>"; } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="oppol_basis">أساس الاستحقاق *</label>
                    <select id="oppol_basis" name="basis" required>
                        <?php foreach ($BASES as $k => $v) {
                            $note = ($k === 'composite') ? ' (بلا صيغةٍ بعد — لا يُحسب)' : '';
                            echo "<option value='{$k}'>{$v}{$note}</option>";
                        } ?>
                    </select>
                </div>
                <div class="form-group"><label>المعدّل *</label>
                    <input type="number" name="rate" step="0.0001" min="0.0001" required placeholder="لوحدة الأساس"></div>
                <div class="form-group"><label for="oppol_currency">العملة *</label>
                    <input type="text" id="oppol_currency" name="currency" value="SDG" required maxlength="10"></div>
                <div class="form-group"><label>حدٌّ أدنى يومي</label>
                    <input type="number" name="min_amount" step="0.01" min="0" placeholder="اختياري"></div>
                <div class="form-group"><label>حدٌّ أقصى يومي</label>
                    <input type="number" name="max_amount" step="0.01" min="0" placeholder="اختياري"></div>
                <div class="form-group"><label for="oppol_effective_from">سريان من</label>
                    <input type="date" id="oppol_effective_from" name="effective_from"></div>
                <div class="form-group"><label for="oppol_effective_to">سريان إلى</label>
                    <input type="date" id="oppol_effective_to" name="effective_to"></div>
                <div class="form-group">
                    <label for="oppol_scope_project_id">نطاق: مشروع</label>
                    <select id="oppol_scope_project_id" name="scope_project_id">
                        <option value="0">— افتراضية (كل المشاريع) —</option>
                        <?php foreach ($projects as $pr) {
                            echo "<option value='" . intval($pr['id']) . "'>" . htmlspecialchars($pr['name']) . "</option>";
                        } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="oppol_scope_equipment_type">نطاق: نوع معدة</label>
                    <select id="oppol_scope_equipment_type" name="scope_equipment_type">
                        <option value="0">— كل الأنواع —</option>
                        <?php foreach ($equipTypes as $tid => $tname) {
                            echo "<option value='{$tid}'>" . htmlspecialchars($tname) . "</option>";
                        } ?>
                    </select>
                </div>
                <div class="form-group"><label>الخصومات (توثيق)</label>
                    <input type="text" name="deductions_note" maxlength="200" placeholder="اختياري"></div>
                <div class="form-group"><label>الاستثناءات (توثيق)</label>
                    <input type="text" name="exceptions_note" maxlength="200" placeholder="اختياري"></div>
                <div class="form-group"><label for="oppol_note">ملاحظة</label>
                    <input type="text" id="oppol_note" name="note" maxlength="200"></div>
                <div class="form-group"><label class="fin-oppol-check-label" for="oppol_is_trial">
                    <input type="checkbox" id="oppol_is_trial" name="is_trial" value="1" class="fin-oppol-check"> سياسةٌ تجريبية (توسم ولا تُعتمد للأجر الحقيقي)</label></div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> إضافة السياسة</button>
            </div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <h5 class="fin-oppol-h5"><i class="fas fa-list"></i> السياسات</h5>
        <div class="table-container">
            <table id="polTable" class="display nowrap alltables fin-oppol-tbl" data-page-length="25" data-order='[]' data-state-save="false">
                <thead><tr>
                    <th>المشغّل</th><th>النموذج</th><th>الأساس</th><th>المعدّل</th>
                    <th>الحدود</th><th>العملة</th><th>النطاق</th><th>السريان</th>
                    <th>الحالة</th><?php if ($can_edit) echo '<th>إجراء</th>'; ?>
                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    </tr></thead>
                <tbody>
                <?php foreach ($policies as $p) {
                    $stopped = ($p['deleted_at'] !== null);
                    $scope = 'افتراضية';
                    if ($p['scope_project_id'] !== null) {
                        $scope = 'مشروع: ' . ($p['proj_name'] !== null ? $p['proj_name'] : ('#' . $p['scope_project_id']));
                    } elseif ($p['scope_equipment_type'] !== null) {
                        $tid = intval($p['scope_equipment_type']);
                        $scope = 'نوع: ' . (isset($equipTypes[$tid]) ? $equipTypes[$tid] : ('#' . $tid));
                    }
                    $valid = ($p['effective_from'] ?: '…') . ' → ' . ($p['effective_to'] ?: '…');
                    $limits = ($p['min_amount'] !== null ? '≥' . $p['min_amount'] : '') .
                              (($p['min_amount'] !== null && $p['max_amount'] !== null) ? ' · ' : '') .
                              ($p['max_amount'] !== null ? '≤' . $p['max_amount'] : '');
                    echo "<tr" . ($stopped ? " class='fin-oppol-stopped'" : '') . ">";
                    echo "<td>" . htmlspecialchars($p['emp_name'] !== null && $p['emp_name'] !== '' ? $p['emp_name'] : ('#' . $p['operator_id'])) . "</td>";
                    echo "<td>" . ($MODELS[$p['work_model']] ?? $p['work_model']) . "</td>";
                    echo "<td>" . ($BASES[$p['basis']] ?? $p['basis']) . "</td>";
                    echo "<td>" . rtrim(rtrim(number_format((float) $p['rate'], 4, '.', ''), '0'), '.') . "</td>";
                    echo "<td>" . ($limits !== '' ? $limits : '—') . "</td>";
                    echo "<td>" . htmlspecialchars($p['currency']) . "</td>";
                    echo "<td>" . htmlspecialchars($scope) . "</td>";
                    echo "<td class='fin-oppol-ltr'>" . $valid . "</td>";
                    // E-24: الحالةُ من آلتها — والوسمُ التجريبيُّ محورٌ ثانٍ لا بديلٌ عنها
                    $ps = strval($p['policy_state']);
                    $psCls = array('draft' => 'badge-secondary', 'active' => 'badge-success',
                                   'superseded' => 'badge-info', 'expired' => 'badge-dark');
                    $stateCell = "<span class='badge " . ($psCls[$ps] ?? 'badge-secondary') . "'>"
                        . htmlspecialchars(PPS::labelAr($ps)) . "</span>";
                    if ($ps === 'superseded' && $p['superseded_by'] !== null) {
                        $stateCell .= " <small>← #" . intval($p['superseded_by']) . "</small>";
                    }
                    if (intval($p['is_trial']) === 1) {
                        $stateCell .= " <span class='badge badge-warning'><i class='fas fa-flask'></i> تجريبية</span>";
                    }
                    if ($stopped) { $stateCell .= " <span class='badge badge-secondary'>موقوفة</span>"; }
                    if ($p['state_note'] !== null && $p['state_note'] !== '') {
                        $stateCell .= "<div><small class='fin-oppol-note'>"
                            . htmlspecialchars(strval($p['state_note'])) . "</small></div>";
                    }
                    echo "<td class='fin-oppol-wrap'>" . $stateCell . "</td>";
                    if ($can_edit) {
                        $act = '—';
                        if ($ps === 'draft') {
                            $act = "<a href='?activate_policy=" . intval($p['id']) . "' class='badge badge-success fin-oppol-act' onclick=\"return confirm('تفعيلُ السياسة؟ ما يُخلِفه سريانُها من سياساتٍ نافذةٍ يُغلق عند يومٍ قبله.');\"><i class='fas fa-play'></i> فعّل</a>";
                        } elseif ($ps === 'active' || $ps === 'superseded') {
                            $act = "<form method='post' class='fin-oppol-inline-form'>" . csrf_field()
                                 . "<input type='hidden' name='expire_policy' value='1'>"
                                 . "<input type='hidden' name='policy_id' value='" . intval($p['id']) . "'>"
                                 . "<input type='text' name='expire_reason' maxlength='200' required placeholder='سببُ الإنهاء' class='fin-oppol-reason'>"
                                 . "<button type='submit' class='badge badge-danger fin-oppol-stopbtn'><i class='fas fa-stop'></i> أنهِ</button></form>";
                        }
                        echo "<td>" . $act . "</td>";
                    }
                    echo "</tr>";
                } ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($policies)): ?>
            <p class="fin-oppol-emptynote"><i class="fas fa-circle-info"></i> لا سياساتٍ بعد — أضف أولى السياسات من النموذج أعلاه.</p>
        <?php endif; ?>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<!-- UXW-01 ⑤: التهيئةُ المحليةُ أُزيلت — المكوّنُ المركزيُّ في assets/js/ui-unification.js
     يلتقط #polTable آليًّا (تعريبٌ وأزرارُ تصدير)، والسلوكُ المحفوظُ معلَنٌ بسماتِ <table>. -->
</body>
</html>
