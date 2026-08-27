<?php
/**
 * Portal/exec_contract_registry.php — سجل العقود الموحد (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * نافذة قراءة واحدة تجمع العقود من سجلات مالكيها والقيادة لا تملك العقد ولا سجله
 *
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها
 *   **إدارة المبيعات التعاقدية والعقود** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.
 *
 * ◆ **والرؤيةُ لا تساوي السلطة**: هذا سطحُ قراءةٍ بلا فعلِ كتابة؛ والقرارُ
 *   يمرُّ بمحرّكِ الاعتمادِ نفسِه عند مالكِ المستند لا من هنا.
 *
 * ◆ **والنطاقُ من محرّكٍ واحد**: الرئيسُ يرى الشركةَ والنائبُ نطاقَه
 *   والموظّفُ صفوفَه — بالشيفرةِ نفسِها. ⛔ ولا ثلاثةَ أنظمة.
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

$perms = w15_perms($conn, 'Portal/exec_contract_registry.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('orderBy' => 'contract_signing_date DESC, id DESC', 'limit' => 500, 'scope_col' => 'project_id');
$rows = w15_rows($is_super, 'contracts', $vis, $opt);

$page_title = 'إيكوبيشن | سجل العقود الموحد';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سجل العقود الموحد'; $header_icon = 'fa fa-file-signature'; $header_actions = array();
    $header_back = array('href' => 'ceo_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة القيادة التنفيذية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد العقود</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "contract_status", "نافذ") ?></div><div class="ems-stat-label">عقود نافذة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "contract_status", "مقفل") ?></div><div class="ems-stat-label">عقود مقفلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "signing_authority_ref") ?></div><div class="ems-stat-label">عقود بمرجع سلطة توقيع</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا عقود مسجلة', 'السجل نافذة قراءة فوق سجلات مالكيها'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_exec_contract_registry')); ?>
    <table id="emsList_exec_contract_registry" class="data-table">
        <thead><tr><th>رقم العقد</th><th>الطرف الآخر</th><th>تاريخ التوقيع</th><th>البداية</th><th>النهاية</th><th>العملة</th><th>مرجع سلطة التوقيع</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= (int) $r["id"] ?></td>
                    <td><?= ems_w15_txt($r["second_party"]) ?></td>
                    <td><?= ems_w15_txt($r["contract_signing_date"]) ?></td>
                    <td><?= ems_w15_txt($r["actual_start"]) ?></td>
                    <td><?= ems_w15_txt($r["actual_end"]) ?></td>
                    <td><?= ems_w15_txt($r["price_currency_contract"]) ?></td>
                    <td><?= ems_w15_txt($r["signing_authority_ref"]) ?></td>
                    <td><?= ems_w15_state((string) $r["contract_status"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
