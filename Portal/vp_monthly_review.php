<?php
/**
 * Portal/vp_monthly_review.php — المراجعة الشهرية للنائب (VP-05)
 * ───────────────────────────────────────────────────────────────────────────
 * **جزءٌ من تجهيزِ Executive Monthly Pack بالنطاق** (‏`VP-05` نصًّا) —
 * Grain: **محورٌ × شهرٌ ضمن النطاق**.
 *
 * ◆ المحرّكُ محرّكُ `exec_monthly_pack` نفسُه (`w15_view.php` ⇒
 *   `ScopeEngine::visibility`) — النطاقُ يضيق بهويّةِ النائبِ تلقائيًّا،
 *   ⛔ ولا نسخةَ دوريّةً ولا مخزنَ محلّيًّا.
 * ◆ إسقاطٌ لا مصدر: الحزمةُ تتجمّع من إقفالاتِ الإداراتِ الشهريّة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w15_view.php';

$ctx = w15_ctx();
$is_super = $ctx['is_super'];
if (!$is_super && $ctx['company_id'] <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w15_perms($conn, 'Portal/vp_monthly_review.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('orderBy' => 'accounting_month DESC, id DESC', 'limit' => 300, 'scope_col' => 'entity_id');
$rows = w15_rows($is_super, 'fin_monthly_close', $vis, $opt);

$page_title = 'إيكوبيشن | المراجعة الشهرية للنائب';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'المراجعة الشهرية للنائب: المحرك العام نفسه والنطاق نطاقك'; $header_icon = 'fa fa-calendar-check'; $header_actions = array();
    $header_back = array('href' => 'vp_weekly_report.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'التقرير الأسبوعي للنائب');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">إقفالات ضمن نطاقك</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "state", "approved") ?></div><div class="ems-stat-label">إقفالات معتمدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "rollforward_ok", "1") ?></div><div class="ems-stat-label">إقفالات متصلة بالسابق</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_distinct($rows, "accounting_month") ?></div><div class="ems-stat-label">الأشهر المشمولة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا إقفالات شهرية ضمن نطاقك بعد', 'الحزمة تتجمع من إقفالات الإدارات بنطاقك'); ?>

    <?php require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_vp_monthly_review')); ?>
    <table id="emsList_vp_monthly_review" class="data-table">
        <thead><tr><th>الشهر</th><th>رقم الإقفال</th><th>الكيان</th><th>الرصيد الافتتاحي</th><th>المستحق</th><th>المسدد</th><th>الرصيد الختامي</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w15_txt($r["accounting_month"]) ?></td>
                    <td><?= ems_w15_txt($r["close_code"]) ?></td>
                    <td><?= (int) $r["entity_id"] ?></td>
                    <td><?= ems_w15_num($r["open_balance"]) ?></td>
                    <td><?= ems_w15_num($r["due_in_month"]) ?></td>
                    <td><?= ems_w15_num($r["paid_in_month"]) ?></td>
                    <td><?= ems_w15_num($r["close_balance"]) ?></td>
                    <td><?= ems_w15_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
