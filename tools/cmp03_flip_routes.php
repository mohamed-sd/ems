<?php
/**
 * tools/cmp03_flip_routes.php — قلب مسارات nav_items للشاشات المبنية (CMP-03 ⑥)
 * ───────────────────────────────────────────────────────────────────────────
 * الروابط المولدة كانت على main/soon.php?screen=X والقاموس قُلب بعد البناء —
 * نحدّث المسار وحده (بمرساة التكرار إن وجدت) كما كان سيولده routeOf المستورد،
 * بلا مساس بصفوف العرض ولا الظهورات (فرق المصفوفة −233 سابقٌ ومحسوم لقرارٍ آخر).
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);

$r = mysqli_query($conn, "SELECT canonical_file, real_path FROM nav09_file_map
                          WHERE state <> 'soon' AND real_path IS NOT NULL");
$n = 0;
while ($x = mysqli_fetch_assoc($r)) {
    $old = 'main/soon.php?screen=' . $x['canonical_file'];
    $eo = mysqli_real_escape_string($conn, $old);
    $en = mysqli_real_escape_string($conn, $x['real_path']);
    $c = mysqli_query($conn, "SELECT COUNT(*) c FROM nav_items WHERE route LIKE '$eo%'");
    $cnt = ($c && ($y = mysqli_fetch_assoc($c))) ? intval($y['c']) : 0;
    if ($cnt === 0) { continue; }
    echo ($APPLY ? '✔ ' : '⏸ ') . "{$x['canonical_file']}: $cnt رابطًا ← {$x['real_path']}\n";
    if ($APPLY) {
        mysqli_query($conn, "UPDATE nav_items SET route = REPLACE(route, '$eo', '$en') WHERE route LIKE '$eo%'");
        if (mysqli_errno($conn) === 1062) {
            /* uq_nav_role_route: للدور صفٌّ قائمٌ على المسار الهدف (رابط «أخرى»
               رهن قرار المالك فلا يُمس) — نفرّد بمرساةٍ كعرف التكرار القائم؛
               الوجهة واحدة والفاحص يقارن بعد نزع المرساة. صفًّا صفًّا لأن 1062
               يجهض التحديث الجماعي كله. */
            $r2 = mysqli_query($conn, "SELECT id, route FROM nav_items WHERE route LIKE '$eo%'");
            while ($y = mysqli_fetch_assoc($r2)) {
                $newRoute = str_replace($old, $x['real_path'], $y['route']);
                $enr = mysqli_real_escape_string($conn, $newRoute);
                mysqli_query($conn, "UPDATE nav_items SET route = '$enr' WHERE id = " . intval($y['id']));
                if (mysqli_errno($conn) === 1062) {
                    mysqli_query($conn, "UPDATE nav_items SET route = '$enr#cmp03' WHERE id = " . intval($y['id']));
                }
            }
        }
    }
    $n += $cnt;
}
echo "\n" . ($APPLY ? 'قُلب' : 'سيُقلب') . " $n رابطًا.\n";
