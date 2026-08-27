<?php
/**
 * Risk/risk_escalations.php — تصعيدات المخاطر (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * الاختراق الحرج أو خروج الشهية أو تأخر المعالجة الجوهري يصعد بمساره
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

$perms = w14_perms($conn, 'Risk/risk_escalations.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'risk_escalations',
                 array('orderBy' => 'id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | تصعيدات المخاطر';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'تصعيدات المخاطر'; $header_icon = 'fa fa-arrow-up-right-dots'; $header_actions = array();
    $header_back = array('href' => 'risk_acceptance.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'قبول المخاطر');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد التصعيدات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "is_auto", "1") ?></div><div class="ems-stat-label">تصعيدات آلية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "acknowledged_by") ?></div><div class="ems-stat-label">تصعيدات مستلمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_distinct($rows, "to_authority") ?></div><div class="ems-stat-label">الجهات المصعد إليها</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا تصعيدات مسجلة', 'التصعيد واقعة بسببها لا تنبيه يمر'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_risk_escalations')); ?>
    <table id="emsList_risk_escalations" class="data-table">
        <thead><tr><th>الخطر</th><th>سبب التصعيد</th><th>الجهة</th><th>آلي</th><th>المستلم</th><th>تاريخ الاستلام</th><th>تاريخ التصعيد</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["risk_id"] ?></td>
                    <td><?= ems_w14_txt($r["reason_ar"]) ?></td>
                    <td><?= ems_w14_state((string) $r["to_authority"]) ?></td>
                    <td><?= (int) $r["is_auto"] ?></td>
                    <td><?= (int) $r["acknowledged_by"] ?></td>
                    <td><?= ems_w14_txt($r["acknowledged_at"]) ?></td>
                    <td><?= ems_w14_txt($r["created_at"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
