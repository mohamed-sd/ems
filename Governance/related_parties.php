<?php
/**
 * Governance/related_parties.php — الأطراف ذات العلاقة (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * كل تعامل مع طرف ذي علاقة يمر بإفصاح إلزامي ويوسم بين الكيانات منذ إنشائه
 *
 * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ
 *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في خدمةِ نطاقِه
 *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. **وثلاثةُ نطاقاتٍ لا محرّكٌ واحد.**
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w14_view.php';

$ctx = w14_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = w14_perms($conn, 'Governance/related_parties.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_related_party',
                 array('orderBy' => 'id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | الأطراف ذات العلاقة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الأطراف ذات العلاقة'; $header_icon = 'fa fa-handshake'; $header_actions = array();
    $header_back = array('href' => 'conflict_disclosures.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'تضارب المصالح');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الأطراف</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "intercompany_flag", "1") ?></div><div class="ems-stat-label">تعاملات بين كيانات المجموعة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "disclosure_no") ?></div><div class="ems-stat-label">تعاملات لها إفصاح</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "active") ?></div><div class="ems-stat-label">أطراف نشطة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أطراف ذات علاقة', 'الطرف سطر بتعامله لا اسم في ملاحظة'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>رقم الطرف</th><th>اسم الطرف</th><th>صفة العلاقة</th><th>مرجع التعامل</th><th>قيمة التعامل</th><th>الإفصاح</th><th>بين كيانات المجموعة</th><th>نوع المعاملة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["party_no"]) ?></td>
                    <td><?= ems_w14_txt($r["party_name"]) ?></td>
                    <td><?= ems_w14_txt($r["relation_ar"]) ?></td>
                    <td><?= ems_w14_txt($r["deal_ref"]) ?></td>
                    <td><?= ems_w14_num($r["deal_amount"]) ?></td>
                    <td><?= ems_w14_txt($r["disclosure_no"]) ?></td>
                    <td><?= (int) $r["intercompany_flag"] ?></td>
                    <td><?= ems_w14_txt($r["transaction_type"]) ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
