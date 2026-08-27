<?php
/**
 * Governance/breaches.php — سجل الإخلالات (RPR-W14)
 * ───────────────────────────────────────────────────────────────────────────
 * كل إخلال بقاعدة أو التزام يسجل بأثره ومعالجته ولا يغلق بلا إجراء ودليل
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

$perms = w14_perms($conn, 'Governance/breaches.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = w14_rows($is_super, 'gov_breach',
                 array('orderBy' => 'id DESC', 'limit' => 500));

$page_title = 'إيكوبيشن | سجل الإخلالات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل الإخلالات'; $header_icon = 'fa fa-triangle-exclamation'; $header_actions = array();
    $header_back = array('href' => 'investigations.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'التحقيقات');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الإخلالات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "opened") ?></div><div class="ems-stat-label">إخلالات مفتوحة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_count($rows, "state", "closed") ?></div><div class="ems-stat-label">إخلالات مغلقة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w14_filled($rows, "deviation_no") ?></div><div class="ems-stat-label">إخلالات لها انحراف مرجعي</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا إخلالات مسجلة', 'الإخلال حالة بأساسها لا ملاحظة'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_breaches')); ?>
    <table id="emsList_breaches" class="data-table">
        <thead><tr><th>رقم الحالة</th><th>الموضوع</th><th>أساس الفتح</th><th>الضابط المكسور</th><th>السياسة</th><th>الانحراف المرجعي</th><th>الخطورة</th><th>الإجراء التصحيحي</th><th>دليل الإغلاق</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w14_txt($r["case_no"]) ?></td>
                    <td><?= ems_w14_txt($r["title_ar"]) ?></td>
                    <td><?= ems_w14_state((string) $r["opened_basis"]) ?></td>
                    <td><?= ems_w14_txt($r["control_ref"]) ?></td>
                    <td><?= ems_w14_txt($r["policy_no"]) ?></td>
                    <td><?= ems_w14_txt($r["deviation_no"]) ?></td>
                    <td><?= ems_w14_state((string) $r["severity"]) ?></td>
                    <td><?= ems_w14_txt($r["action_no"]) ?></td>
                    <td><?= ems_w14_txt($r["close_evidence"]) ?></td>
                    <td><?= ems_w14_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
