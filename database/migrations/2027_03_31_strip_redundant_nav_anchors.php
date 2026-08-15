<?php
/**
 * 2027_03_31_strip_redundant_nav_anchors.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تجريدُ المِراسي التي **لا تُميّز شيئًا** — ⇐ INJ-0512 · INJ-0132 · INJ-0154
 *                                             · INJ-0428 · INJ-0554
 *
 * المِرساةُ في `nav_items.route` (`…#n9g11i1`) لها معنًى **واحدٌ فقط**: تمييزُ
 * مدخلين يقصدانِ **الملفَّ نفسَه** من مرحلتين. فإن كان الملفُّ يظهر **مرةً واحدةً**
 * في قائمةِ ذلك الدورِ فالمِرساةُ زائدةٌ — لا تُميّز شيئًا، وتجعل الرابطَ يبدو
 * ملفًّا آخرَ في كلِّ قياسٍ لا يجرّدها (وهو جذرُ خمسةِ بنودٍ في السجل).
 *
 * ◆ **ولا تُجرَّد مِرساةٌ تُميّز فعلًا**: إن ظهر الملفُّ مرتين أو أكثرَ لدورٍ واحدٍ
 *   بقيت مِراسيه — فتجريدُها يجعلهما رابطًا واحدًا مكرَّرًا ويكسر الوصولَ إلى
 *   أحدِ القسمين (وهو ما تحرسه مِراسي الوصول · INJ-0459).
 * ◆ وأرشيفٌ قبل التعديل — فالردُّ ممكن.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ تجريدُ المِراسي التي لا تُميّز ══\n\n";

$norm = function ($r) {
    $r = explode('#', (string) $r, 2)[0];
    $r = explode('?', $r, 2)[0];
    $r = preg_replace('~^(\.\./)+~', '', $r);
    return strtolower(trim(ltrim((string) $r, '/')));
};

/* كم مرةً يظهر كلُّ ملفٍّ في قائمةِ كلِّ دور؟ */
$count = array();
$rows = array();
$r = $conn->query('SELECT id, role_id, route FROM nav_items WHERE route LIKE \'%#%\'');
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
$all = $conn->query('SELECT role_id, route FROM nav_items');
while ($all && ($x = $all->fetch_assoc())) {
    $k = (int) $x['role_id'] . '|' . $norm($x['route']);
    $count[$k] = (isset($count[$k]) ? $count[$k] : 0) + 1;
}

echo '  صفوفٌ ذاتُ مِرساة: ' . count($rows) . "\n";
$strip = array(); $keep = 0;
foreach ($rows as $x) {
    $k = (int) $x['role_id'] . '|' . $norm($x['route']);
    if (isset($count[$k]) && $count[$k] > 1) { $keep++; continue; }   /* تُميّز — تبقى */
    $strip[] = $x;
}
echo '  مِراسٍ تُميّز فعلًا (تبقى): ' . $keep . "\n";
echo '  مِراسٍ زائدةٌ (تُجرَّد): ' . count($strip) . "\n\n";
if (!$strip) { echo "  · لا شيءَ يُجرَّد — الهجرةُ مطبَّقةٌ سلفًا\n"; exit(0); }

$conn->query('CREATE TABLE IF NOT EXISTS nav_items_archive_anchors LIKE nav_items');
$done = 0; $failed = 0;
foreach ($strip as $x) {
    $id = (int) $x['id'];
    $conn->query('INSERT INTO nav_items_archive_anchors SELECT * FROM nav_items WHERE id = ' . $id);
    $clean = explode('#', (string) $x['route'], 2)[0];
    $st = $conn->prepare('UPDATE nav_items SET route = ? WHERE id = ?');
    if (!$st) { $failed++; continue; }
    $st->bind_param('si', $clean, $id);
    if ($st->execute() && $conn->affected_rows > 0) { $done++; } else { $failed++; }
    $st->close();
}
echo "  ✔ جُرِّدت {$done} مِرساةً" . ($failed ? " · تعذَّر {$failed}" : '') . "\n";

$left = 0;
$r = $conn->query('SELECT COUNT(*) FROM nav_items WHERE route LIKE \'%#%\'');
if ($r) { $left = (int) $r->fetch_row()[0]; }
echo "  · المتبقّي بمِرساةٍ (تُميّز فعلًا): {$left}\n";
echo "  ◆ الردُّ إن لزم: من `nav_items_archive_anchors` بالمعرّف.\n";
exit($failed === 0 ? 0 : 1);
