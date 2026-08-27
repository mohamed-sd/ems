<?php
/**
 * Portal/exec_reserved_matters.php — المسائل المحجوزة (RPR-W15)
 * ───────────────────────────────────────────────────────────────────────────
 * ما حجزته وثائق الشركة لجهة حاكمة لا يقرر تنفيذيا مهما بلغت الحاجة وهذه نافذة تعريف واحالة
 *
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): قراءةٌ حيّةٌ من سجلِّ مالكِها
 *   **إدارة الحوكمة والالتزام** — ⛔ ولا يخزّن هذا السطحُ حقيقةً ولا ينسخها.
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

$perms = w15_perms($conn, 'Portal/exec_reserved_matters.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$vis = w15_visibility($conn, $ctx);
$opt = array('orderBy' => 'policy_no, version_no DESC', 'limit' => 300, 'scope_col' => 'company_id');
$rows = w15_rows($is_super, 'gov_policy', $vis, $opt);

$page_title = 'إيكوبيشن | المسائل المحجوزة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'المسائل المحجوزة'; $header_icon = 'fa fa-lock'; $header_actions = array();
    $header_back = array('href' => 'ceo_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة القيادة التنفيذية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">عدد الوثائق الحاكمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_count($rows, "state", "effective") ?></div><div class="ems-stat-label">وثائق نافذة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_filled($rows, "review_due") ?></div><div class="ems-stat-label">وثائق لها موعد مراجعة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w15_distinct($rows, "owner_dept") ?></div><div class="ems-stat-label">الإدارات المالكة</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا مسائل محجوزة مسجلة', 'المسألة المحجوزة تعرف وتحال ولا تقرر هنا'); ?>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_exec_reserved_matters')); ?>
    <table id="emsList_exec_reserved_matters" class="data-table">
        <thead><tr><th>رقم الوثيقة</th><th>الموضوع</th><th>المجال</th><th>الإدارة المالكة</th><th>تاريخ النفاذ</th><th>موعد المراجعة</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= ems_w15_txt($r["policy_no"]) ?></td>
                    <td><?= ems_w15_txt($r["title_ar"]) ?></td>
                    <td><?= ems_w15_txt($r["domain_ar"]) ?></td>
                    <td><?= ems_w15_txt($r["owner_dept"]) ?></td>
                    <td><?= ems_w15_txt($r["effective_from"]) ?></td>
                    <td><?= ems_w15_txt($r["review_due"]) ?></td>
                    <td><?= ems_w15_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
