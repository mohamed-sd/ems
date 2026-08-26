<?php
/**
 * Finance/tre_fx_deals.php — تنفيذ عمليات الصرف الأجنبي (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * الصرف الاجنبي (TRS-12) — الشراء والبيع الفعلي بسعر الصفقة الموثق وجدول الاسعار للمقارنة لا للاحلال.
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

$perms = fin_page_perms($conn, 'Finance/tre_fx_deals.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('tre_fx_deal', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_fx_deals.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | تنفيذ عمليات الصرف الأجنبي';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تنفيذ عمليات الصرف الأجنبي'; $header_icon = 'fa fa-coins'; $header_actions = array();
    $header_back = array('href' => 'currencies_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أسعار الصرف');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">الصفقات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "deal_kind", "buy") ?></div><div class="ems-stat-label">صفقات شراء</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "deal_kind", "sell") ?></div><div class="ems-stat-label">صفقات بيع</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_distinct($rows, "buy_currency") ?></div><div class="ems-stat-label">ازواج عملات</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا صفقات صرف', 'الصفقة بمستندها وبسعرها الموثق'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الرقم</th><th>النوع</th><th>عملة البيع</th><th>عملة الشراء</th><th>مبلغ البيع</th><th>مبلغ الشراء</th><th>سعر الصفقة</th><th>سعر الجدول</th><th>المستند</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["deal_no"]) ?></td>
                    <td><?= ((string) $r["deal_kind"] === "buy" ? "شراء" : "بيع") ?></td>
                    <td><?= htmlspecialchars((string) $r["sell_currency"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["buy_currency"]) ?></td>
                    <td><?= number_format((float) $r["sell_amount"], 2) ?></td>
                    <td><?= number_format((float) $r["buy_amount"], 2) ?></td>
                    <td><?= htmlspecialchars((string) $r["deal_rate"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["table_rate"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["doc_ref"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
