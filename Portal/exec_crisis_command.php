<?php
/**
 * Portal/exec_crisis_command.php — قيادة الأزمات والطوارئ (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * تفعيل قيادي فوق الحدث لا تكرار له والحدث يبقى عند مصدره وهذه اضافة قرار لا نسخة واقعة
 *
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها
 *   **سجل قرارات القيادة** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.
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

$perms = w15_perms($conn, 'Portal/exec_crisis_command.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('where' => array('issue_type' => 'أزمة'), 'orderBy' => 'raised_date DESC, id DESC', 'limit' => 200, 'scope_col' => 'company_id');
$rows = w15_rows($is_super, 'exec_decisions', $vis, $opt);

$page_title = 'إيكوبيشن | قيادة الأزمات والطوارئ';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'قيادة الأزمات والطوارئ'; $header_icon = 'fa fa-tower-broadcast'; $header_actions = array();
    $header_back = array('href' => 'exec_escalations.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'التصعيدات العليا');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد حالات التفعيل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "status", "مفتوح") ?></div><div class="ems-stat-label">تفعيل قائم</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "assigned_dept") ?></div><div class="ems-stat-label">حالات لها إدارة مكلفة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "authority_ref") ?></div><div class="ems-stat-label">حالات بمرجع سلطة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا تفعيل قائم', 'التفعيل يقع فوق الحدث ولا ينسخه'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_exec_crisis_command')); ?>
    <table id="emsList_exec_crisis_command" class="data-table">
        <thead><tr><th>رقم القرار</th><th>تاريخ الرفع</th><th>الإدارة الرافعة</th><th>الموضوع</th><th>الإدارة المكلفة</th><th>المهلة</th><th>مرجع السلطة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w15_txt($r["decision_no"]) ?></td>
                    <td><?= ems_w15_txt($r["raised_date"]) ?></td>
                    <td><?= ems_w15_txt($r["raising_dept"]) ?></td>
                    <td><?= ems_w15_txt($r["issue_desc"]) ?></td>
                    <td><?= ems_w15_txt($r["assigned_dept"]) ?></td>
                    <td><?= ems_w15_txt($r["exec_deadline"]) ?></td>
                    <td><?= ems_w15_txt($r["authority_ref"]) ?></td>
                    <td><?= ems_w15_state((string) $r["status"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
