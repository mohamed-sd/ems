<?php
/**
 * tools/h16_resolution_probe.php — خريطةُ حلِّ المسار إلى موديول (ح-16) · v1
 * ═══════════════════════════════════════════════════════════════════════════
 * `modules` يحمل صفًّا لكل مالكٍ للشاشة المشتركة (main/project_users.php ستةُ
 * صفوف · Reports/reports.php خمسة). وحلُّ المسار في get_module_id_by_script_path
 * كان `LIMIT 1` بلا ترتيب ⇒ يُختار صفٌّ **عشوائيٌّ** (أدنى id عمليًّا)، فقد
 * تُقاس صلاحيةُ دورٍ على موديولٍ يملكه دورٌ آخر.
 *
 * هذه الأداةُ تطبع، لكل دورٍ ولكل رابطٍ نشطٍ في قائمته، الموديولَ المُحلَّ
 * والنتيجةَ (can_view). تُشغَّل قبل التعديل وبعده ويُقارَن المخرجان.
 *
 * php tools/h16_resolution_probe.php > before.txt
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

$roles = array();
$q = mysqli_query($conn, "SELECT DISTINCT role_id FROM nav_items WHERE active=1 ORDER BY role_id");
while ($r = mysqli_fetch_assoc($q)) { $roles[] = (int) $r['role_id']; }

$denied = 0; $total = 0;
foreach ($roles as $rid) {
    // نُحاكي الجلسة كي تقرأ الدوالُّ الدورَ الحالي
    $_SESSION['user'] = array('role' => $rid, 'company_id' => 4, 'id' => 0);
    $q = mysqli_query($conn, "SELECT DISTINCT route FROM nav_items WHERE role_id=$rid AND active=1 ORDER BY route");
    while ($r = mysqli_fetch_assoc($q)) {
        $route = (string) $r['route'];
        if ($route === '') { continue; }
        $path = '/ems/' . ltrim(preg_replace('#^\.\./#', '', parse_url($route, PHP_URL_PATH)), '/');
        $mid  = get_module_id_by_script_path($conn, $path);
        $perm = get_current_page_permissions($conn, $path);
        $total++;
        $view = !empty($perm['can_view']) ? 'view=1' : 'view=0';
        if ($mid !== null && empty($perm['can_view'])) { $denied++; $view .= ' ← محجوب'; }
        $o(sprintf('%-4s %-46s mid=%-6s %s', $rid, basename($path), var_export($mid, true), $view));
    }
}
$o('');
$o("الإجمالي: $total رابطًا · محجوبٌ رغم وجوده في القائمة: $denied");
