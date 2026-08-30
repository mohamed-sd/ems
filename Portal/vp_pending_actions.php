<?php
/**
 * Portal/vp_pending_actions.php — الإجراءاتُ والقراراتُ المطلوبةُ منّي (VP-10)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **بندٌ ينتظر فعلَ النائبِ — صفٌّ موحَّدٌ من كلِّ الشاشات** (نصُّ
 * الحبّة) — رحلةٌ عابرةُ نطاقات (CROSS_JOURNEY).
 *
 * ◆ الروافدُ الثلاثةُ بالاسم: اعتماداتُ القمّةِ غيرُ المقرَّرةِ
 *   (`exec_approvals`) · قراراتُ الإدارةِ العليا المفتوحةُ بمهلِها
 *   (`exec_decisions`) · تصعيداتُ المخاطرِ غيرُ المُقَرِّ بها
 *   (`risk_escalations`) — وكلُّ صفٍّ يسمّي فاعلَ فعلِه.
 * ◆ لا كتابةَ هنا — الصفُّ الموحَّدُ يجمع ولا يقرِّر.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Portal/vp_pending_actions.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('vp pending actions super') : ems_tenant_db();
/* اليومُ من ساعةِ القاعدةِ (VT-07) — للمهلِ المتجاوزة */
$today = (string) ($conn->query('SELECT CURDATE()')->fetch_row()[0]);

$rows = array(); $nLate = 0;
try {
    foreach ($gate->select('exec_approvals', array('orderBy' => 'id DESC', 'limit' => 1000)) as $x0) {
        if (trim((string) $x0['decision']) !== '') { continue; }
        $late = (string) $x0['deadline'] !== '' && $x0['deadline'] !== null && (string) $x0['deadline'] < $today;
        if ($late) { $nLate++; }
        $rows[] = array('kind' => 'اعتماد بانتظار قرار', 'ref' => (string) $x0['request_no'],
            'src' => (string) $x0['requesting_dept'], 'desc' => mb_substr((string) $x0['raise_reason'], 0, 55),
            'due' => (string) $x0['deadline'] !== '' && $x0['deadline'] !== null ? ((string) $x0['deadline'] . ($late ? '، متجاوزة' : '')) : 'بلا مهلة',
            'link' => 'approvals_inbox.php', 'label' => 'صندوق الاعتمادات');
    }
} catch (\Throwable $t) { error_log('vp_pending approvals: ' . $t->getMessage()); }
try {
    foreach ($gate->select('exec_decisions', array('orderBy' => 'id DESC', 'limit' => 1000)) as $x0) {
        $st = (string) $x0['status'];
        if ($st === 'closed' || $st === 'done' || $st === 'منفذ') { continue; }
        $late = (string) $x0['exec_deadline'] !== '' && $x0['exec_deadline'] !== null && (string) $x0['exec_deadline'] < $today;
        if ($late) { $nLate++; }
        $rows[] = array('kind' => 'قرار عال قيد التنفيذ', 'ref' => (string) $x0['decision_no'],
            'src' => (string) $x0['assigned_dept'] !== '' ? (string) $x0['assigned_dept'] : (string) $x0['raising_dept'],
            'desc' => mb_substr((string) $x0['issue_desc'], 0, 55),
            'due' => (string) $x0['exec_deadline'] !== '' && $x0['exec_deadline'] !== null ? ((string) $x0['exec_deadline'] . ($late ? '، متجاوزة' : '')) : 'بلا مهلة',
            'link' => 'exec_strategic_decisions.php', 'label' => 'قرارات الادارة العليا');
    }
} catch (\Throwable $t) { error_log('vp_pending decisions: ' . $t->getMessage()); }
try {
    foreach ($gate->select('risk_escalations', array('orderBy' => 'id DESC', 'limit' => 1000)) as $x0) {
        if ((int) $x0['acknowledged_by'] > 0) { continue; }
        $rows[] = array('kind' => 'تصعيد خطر بلا اقرار', 'ref' => 'تصعيد رقم ' . (int) $x0['id'],
            'src' => 'ادارة المخاطر', 'desc' => mb_substr((string) $x0['reason_ar'], 0, 55),
            'due' => 'فور الاطلاع', 'link' => '../Risk/risk_escalations.php', 'label' => 'تصعيدات المخاطر');
    }
} catch (\Throwable $t) { error_log('vp_pending risk: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | المطلوب مني';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الاجراءات والقرارات المطلوبة مني: صف موحد من كل الشاشات بفاعل كل فعل'; $header_icon = 'fa fa-list-check'; $header_actions = array();
    $header_back = array('href' => 'vp_dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة قيادة النائب');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($rows)) ?></div><div class="ems-stat-label">بنود تنتظر فعلا</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nLate) ?></div><div class="ems-stat-label">مهل متجاوزة بيوم القاعدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value">3</div><div class="ems-stat-label">روافد مسماة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($rows)) - number_format(0) ?></div><div class="ems-stat-label">كل بند بفاعل فعله</div></div>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead><tr><th>النوع</th><th>المرجع</th><th>الجهة</th><th>البيان</th><th>المهلة</th><th>فعله عند فاعله</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $x0): ?>
                <tr>
                    <td><?= htmlspecialchars($x0['kind']) ?></td>
                    <td><?= htmlspecialchars($x0['ref']) ?></td>
                    <td><?= htmlspecialchars($x0['src']) ?></td>
                    <td><?= htmlspecialchars($x0['desc']) ?></td>
                    <td><?= htmlspecialchars($x0['due']) ?></td>
                    <td><a href="<?= htmlspecialchars($x0['link']) ?>"><?= htmlspecialchars($x0['label']) ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="6">لا بنود منتظرة في الروافد الثلاثة</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        صف موحد يجمع من الروافد الثلاثة ولا يقرر: الاعتمادات غير المقررة والقرارات العليا المفتوحة بمهلها
        وتصعيدات المخاطر بلا اقرار، والمهل المتجاوزة تقاس بيوم القاعدة. الفعل عند فاعله المسمى في كل صف.
    </div>
</div>
<?php include '../infooter.php'; ?>
