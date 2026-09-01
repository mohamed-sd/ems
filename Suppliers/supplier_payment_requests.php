<?php
/**
 * Suppliers/supplier_payment_requests.php — طلباتُ الدفعِ وحالةُ الصرف (SUP-23)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **طلبُ دفعٍ/دفعةٌ واحدة** — معاملةٌ آلتُها `W8_STATES#settlements`
 * (المرجعُ الصريحُ المربوطُ في الدفتر).
 *
 * ◆ **الأفعالُ عند فاعلِها الواحدِ لا تُنسخ**: دورةُ التسويةِ ينفّذها
 *   `SettlementService` من شاشةِ التسوياتِ (اعتمادٌ بسلّم LD-13 وإنشاءُ
 *   طلبِ الدفعِ آليًّا عند الصافي الموجب) والصرفُ من شاشةِ مدفوعاتِ
 *   الماليّة — **وهذه سجلُّ الحوكمةِ للمسار**: كلُّ طلبِ دفعٍ بحالتِه
 *   وسلسلتِه، ولوحةُ الآلةِ المؤلَّفةِ حرفًا، والنزولُ لفاعلِ كلِّ فعل.
 *   ⛔ مسارُ كتابةٍ ثانٍ لنفسِ الفعلِ ازدواجٌ يفترق — فلا POST هنا.
 * ◆ القراءةُ `select` معزولًا والتجميعُ في الذاكرة (GAP-29) — ولوحةُ
 *   الآلةِ من مخزنِ الموجةِ الحوكميِّ العالميّ.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Suppliers/supplier_payment_requests.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier payment requests super') : ems_tenant_db();
require_once __DIR__ . '/../includes/w8_machine_panel.php';
$stateQ = isset($_GET['state']) ? preg_replace('~[^a-z_]~', '', (string) $_GET['state']) : '';

$STL = array('draft' => 'مسودة', 'review' => 'قيد المراجعة', 'approved' => 'معتمدة',
             'payment_requested' => 'طلب صرف قائم', 'paid' => 'مصروفة', 'closed' => 'مقفلة بذمة');
$rows = array(); $nAll = 0; $nReq = 0; $nPaid = 0; $nTreasury = 0;
try {
    foreach ($gate->select('settlements', array('orderBy' => 'id DESC', 'limit' => 3000)) as $x0) {
        if ((string) $x0['party_type'] !== 'supplier') { continue; }
        if ((int) (isset($x0['is_deleted']) ? $x0['is_deleted'] : 0) === 1) { continue; }
        if ((string) $x0['net_direction'] !== 'payable') { continue; }
        $st = (string) $x0['state'];
        $nAll++;
        if ($st === 'payment_requested') { $nReq++; }
        if ($x0['paid_at'] !== null && $x0['paid_at'] !== '') { $nPaid++; }
        if ((int) (isset($x0['borne_by_treasury']) ? $x0['borne_by_treasury'] : 0) === 1) { $nTreasury++; }
        if ($stateQ !== '' && $st !== $stateQ) { continue; }
        $rows[] = array(
            'no'    => (string) $x0['settlement_no'],
            'sup'   => (string) $x0['party_name'],
            'per'   => substr((string) $x0['period_from'], 0, 7),
            'net'   => number_format((float) $x0['net_amount'], 0) . ' ' . (string) $x0['currency'],
            'state' => isset($STL[$st]) ? $STL[$st] : $st,
            'req'   => (int) $x0['payment_request_id'] > 0 ? ('طلب رقم ' . (int) $x0['payment_request_id']) : 'لم ينشأ بعد',
            'appr'  => $x0['approved_at'] !== null ? (string) $x0['approved_at'] : 'غير معتمدة بعد',
            'paid'  => $x0['paid_at'] !== null && $x0['paid_at'] !== '' ? (string) $x0['paid_at'] : 'لم تصرف بعد',
            'sid'   => (int) $x0['id'],
        );
    }
} catch (\Throwable $t) { error_log('supplier_payment_requests: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | طلبات الدفع وحالة الصرف';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'طلبات الدفع وحالة الصرف: طلب دفع واحد بسلسلته، والفعل عند فاعله'; $header_icon = 'fa fa-money-check'; $header_actions = array();
    $header_back = array('href' => 'settlements.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'تسويات الموردين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nAll) ?></div><div class="ems-stat-label">تسويات مستحقة للموردين</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nReq) ?></div><div class="ems-stat-label">طلبات صرف قائمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nPaid) ?></div><div class="ems-stat-label">مصروفة بتاريخ سدادها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nTreasury) ?></div><div class="ems-stat-label">تحملتها الخزينة</div></div>
    </div>

    <div class="ems-filter-box">
        <form method="get" class="ems-filters">
            <div class="ems-filter-item">
                <label>الحالة</label>
                <select name="state" onchange="this.form.submit()">
                    <option value="">الكل</option>
                    <?php foreach ($STL as $k0 => $v0): ?>
                    <option value="<?= $k0 ?>" <?= $k0 === $stateQ ? 'selected' : '' ?>><?= htmlspecialchars($v0) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم الطلب' => 'id',
            'التاريخ' => 'date',
            'رقم المورد' => 'no_supplier',
            'الجهة المستفيدة' => 'c4',
            'طريقة الدفع' => 'payment',
            'اسم البنك' => 'name_bank',
            'رقم الحساب' => 'no_account',
            'اسم صاحب الحساب' => 'name_account',
            'علم طرف ثالث' => 'c9',
            'مرجع تفويض الطرف الثالث (م03)' => 'ref_delegation',
            'العميل/المشروع' => 'project',
            'نظام التقسيم' => 'c12',
            'مرجع التسوية (م17)' => 'ref',
            'المستلم ج.س (لاستحقاق الجنيه)' => 'c14',
            'المستلم بالجنيه (مدولر)' => 'c15',
            'سعر الصرف (مصدره وتاريخه)' => 'disburse_source_date',
            'المستلم الكلي $' => 'c17',
            'مفتاح شهر الصرف (YYYYMM)' => 'month_disburse',
            'شهر الاستحقاق المسدد (كما ورد)' => 'month_entitlement',
            'الإقفال المخصص' => 'closure',
            'حالة التخصيص' => 'allocation',
            'حالة الطلب' => 'c22',
            'حالة الصرف (قراءة)' => 'disburse',
            'تاريخ الصرف' => 'date_disburse',
            'مرجع الصرف' => 'ref_disburse',
            'سياق الآلية (إن ورد)' => 'c26',
            'طبيعة الدفعة (مستخرجة)' => 'payment_27',
            'البيان (مصدر)' => 'source',
            'ملاحظات' => 'notes',
            'المنشئ' => 'creator_name',
            'المراجع' => 'reviewer_name',
            'المعتمد' => 'approver_name',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('sup_payment_disburse');
        echo ems_w14_grid('emsList_sup_payreq', $GUIDE_COLS, $__gridRows, $D, 'لا طلب دفع مسجل بعد'); /* /GUIDE_COLS */ ?>
    </div>

    <?= ems_w8_machine_panel($conn, 'settlements', 'آلة حالة التسوية المؤلفة في مرجعها الحاكم، تعرض حرفا ولا تؤلف هنا') ?>

    <div class="ems-note-box">
        سجل حوكمة لمسار طلب الدفع: الدورة ينفذها محرك التسويات من شاشته باعتماد سلم الاعتماد،
        وطلب الدفع ينشأ آليا عند اعتماد الصافي الموجب، والصرف من شاشة مدفوعات المالية.
        لا فعل كتابة من هذه الشاشة كي لا يزدوج مسار الفعل الواحد.
    </div>
</div>
<?php include '../infooter.php'; ?>
