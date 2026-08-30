<?php
/**
 * Suppliers/supplier_qualification.php — التأهيلُ القانونيُّ والائتمانيّ (SUP-03)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **سجلُّ تأهيلٍ قانونيٍّ وائتمانيٍّ واحدٌ لمورد** — معاملةٌ آلتُها
 * `W8_STATES#suppliers` (المرجعُ الصريحُ المربوطُ في الدفتر:
 * registered ⇐ qualified ⇐ suspended/blacklisted بمالكَيها).
 *
 * ◆ عناصرُ التأهيلِ تُقرأ من سجلِّ الموردِ نفسِه بالاسم: السجلُّ التجاريُّ ·
 *   الرقمُ الضريبيُّ · الهويّةُ وانتهاؤها · الحسابُ البنكيُّ الموثَّقُ
 *   بتاريخِ توثيقِه · وحالةُ التسجيلِ الماليِّ — وكلُّ عنصرٍ يُعرَض
 *   مستوفًى أو ناقصًا باسمِه.
 * ◆ ⛔ **حالةُ آلةِ التأهيلِ بلا عمودِ تخزينٍ في سجلِّ الموردين بعدُ** —
 *   فتُعرَض الآلةُ حرفًا واستيفاءُ العناصرِ قياسًا، **ولا تُختلَق حالةٌ
 *   مخزنةٌ**: تخزينُها يحتاج عمودَه بقناتِه (فجوةُ جاهزيّةٍ تُعلَن).
 * ◆ القراءةُ `select` معزولًا (GAP-29) — سجلُّ حوكمةٍ بلا POST.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Suppliers/supplier_qualification.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier qualification super') : ems_tenant_db();
require_once __DIR__ . '/../includes/w8_machine_panel.php';

/* اليومُ من ساعةِ القاعدةِ (VT-07) — لانتهاءِ الهويّة */
$today = (string) ($conn->query('SELECT CURDATE()')->fetch_row()[0]);

$rows = array(); $nS = 0; $nFull = 0; $nBank = 0; $nExpired = 0;
try {
    foreach ($gate->select('suppliers', array('orderBy' => 'id ASC', 'limit' => 2000)) as $s0) {
        $nS++;
        $items = array();
        $cr = trim((string) $s0['commercial_registration']) !== '';
        $tax = trim((string) $s0['tax_number']) !== '';
        $idn = trim((string) $s0['identity_number']) !== '';
        $idExp = (string) $s0['identity_expiry_date'];
        $idLive = $idn && ($idExp === '' || $idExp === null || $idExp >= $today);
        if ($idn && !$idLive) { $nExpired++; }
        $bank = $s0['bank_verified_at'] !== null && $s0['bank_verified_at'] !== '';
        if ($bank) { $nBank++; }
        $items[] = 'سجل تجاري ' . ($cr ? 'مستوفى' : 'ناقص');
        $items[] = 'رقم ضريبي ' . ($tax ? 'مستوفى' : 'ناقص');
        $items[] = 'هوية ' . ($idn ? ($idLive ? 'سارية' : 'منتهية بتاريخها') : 'ناقصة');
        $items[] = 'حساب بنكي ' . ($bank ? ('موثق في ' . substr((string) $s0['bank_verified_at'], 0, 10)) : 'غير موثق');
        $full = $cr && $tax && $idLive && $bank;
        if ($full) { $nFull++; }
        $rows[] = array(
            'ref'  => 'سجل تأهيل المورد رقم ' . (int) $s0['id'],
            'sup'  => (string) $s0['name'],
            'kind' => (string) $s0['supplier_type'] !== '' ? (string) $s0['supplier_type'] : 'غير مصنف',
            'items' => implode(' · ', $items),
            'fin'  => trim((string) $s0['financial_registration_status']) !== '' ? (string) $s0['financial_registration_status'] : 'بلا حالة تسجيل مالي',
            'verdict' => $full ? 'عناصره مستوفاة قياسا' : 'ناقص العناصر المسماة',
        );
    }
} catch (\Throwable $t) { error_log('supplier_qualification: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | التأهيل القانوني والائتماني';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'التأهيل القانوني والائتماني: سجل تأهيل واحد لكل مورد بعناصره المسماة'; $header_icon = 'fa fa-user-check'; $header_actions = array();
    $header_back = array('href' => 'suppliers.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الموردين');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nS) ?></div><div class="ems-stat-label">موردون في السجل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nFull) ?></div><div class="ems-stat-label">مستوفو العناصر قياسا</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nBank) ?></div><div class="ems-stat-label">بحساب بنكي موثق</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nExpired) ?></div><div class="ems-stat-label">هويات منتهية بتاريخها</div></div>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead>
                <tr>
                    <th>سجل التأهيل</th>
                    <th>المورد</th>
                    <th>نوعه</th>
                    <th>عناصر التأهيل باسمها</th>
                    <th>التسجيل المالي</th>
                    <th>الحكم القياسي</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $x0): ?>
                <tr>
                    <td><?= htmlspecialchars($x0['ref']) ?></td>
                    <td><?= htmlspecialchars($x0['sup']) ?></td>
                    <td><?= htmlspecialchars($x0['kind']) ?></td>
                    <td><?= htmlspecialchars($x0['items']) ?></td>
                    <td><?= htmlspecialchars($x0['fin']) ?></td>
                    <td><?= htmlspecialchars($x0['verdict']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="6">لا موردين في السجل بعد</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= ems_w8_machine_panel($conn, 'suppliers', 'آلة حالة تأهيل المورد المؤلفة في مرجعها، تعرض حرفا') ?>

    <div class="ems-note-box">
        عناصر التأهيل تقرأ من سجل المورد نفسه باسمها، والحكم المعروض قياس استيفاء لا حالة مخزنة:
        حالة آلة التأهيل بلا عمود تخزين في سجل الموردين بعد، فتعرض الآلة حرفا ولا تختلق حالة،
        وتخزينها فجوة جاهزية معلنة تفك بعمودها وقناته. لا كتابة من هذه الشاشة.
    </div>
</div>
<?php include '../infooter.php'; ?>
