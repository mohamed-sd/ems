<?php
/**
 * Finance/acc_credit_control.php — الرقابة الائتمانية وحدود العملاء (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * الرقابة الائتمانية (ACC-15) — المالية تضبط الحد والتجاوز يحجب او يصعد بقاعدة.
 *
 * ◆ **الحبّةُ `Legal Entity × Accounting Period`** (‏`DEC-OPEN-03`): القراءةُ
 *   تمرُّ ببوّابةِ المستأجرِ التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر،
 *   ولا رقمَ يخلط كيانَين بلا وسمٍ مسجَّل.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في خدمةِ الدورةِ
 *   بحارسِها ورمزِ ردِّها، والشاشةُ تعرض ما وقع.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';
require_once __DIR__ . '/w11_view.php';

$ctx = fin_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = fin_page_perms($conn, 'Finance/acc_credit_control.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('acc_credit_limit', array('orderBy' => 'customer_entity_id', 'limit' => 400));
} catch (\Throwable $t) { error_log('acc_credit_control.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | الرقابة الائتمانية وحدود العملاء';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الرقابة الائتمانية وحدود العملاء'; $header_icon = 'fa fa-user-shield'; $header_actions = array();
    $header_back = array('href' => 'dues_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الذمم والمستحقات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عملاء بحدود</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "is_active", 1) ?></div><div class="ems-stat-label">حدود نشطة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_over($rows) ?></div><div class="ems-stat-label">تجاوز الحد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "breach_action", "block") ?></div><div class="ems-stat-label">التجاوز يحجب</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا حدود ائتمانية مسجلة', 'الحد يعتمد بقاعدة صلاحية ولا بيع فوقه بلا اعتماد'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_acc_credit_control')); ?>
    <table id="emsList_acc_credit_control" class="data-table">
        <thead><tr><th>العميل</th><th>الحد</th><th>العملة</th><th>التعرض القائم</th><th>عند التجاوز</th><th>قاعدة الصلاحية</th><th>سبب الحد</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["customer_entity_id"] ?></td>
                    <td><?= number_format((float) $r["limit_amount"], 2) ?></td>
                    <td><?= htmlspecialchars((string) $r["currency"]) ?></td>
                    <td><?= number_format((float) $r["exposure_amount"], 2) ?></td>
                    <td><?= ((string) $r["breach_action"] === "block" ? "يحجب" : "يصعد") ?></td>
                    <td><?= htmlspecialchars((string) $r["authority_rule_id"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["why"]) ?></td>
                    <td><?= ((int) $r["is_active"] === 1 ? "نشط" : "موقوف") ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
