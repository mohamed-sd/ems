<?php
/**
 * 2027_04_03_tickets_views_not_anchors.php
 * ═══════════════════════════════════════════════════════════════════════════
 * مدخلا البلاغاتِ صارا **منظرين** لا مِرساتين — ⇐ INJ-0512 · INJ-0514
 *
 * **المقيس**: ملفّانِ في قائمةِ الدور ٢٤ لكلٍّ منهما مدخلان بتسميتين مختلفتين،
 * يُميَّزانِ بمِرساةٍ (`#n9g10i1`) — والمِرساةُ لا تُغيّر ما يُعرض: كلا المدخلين
 * يفتح الشاشةَ نفسَها بالمحتوى نفسِه. فالمستخدمُ يضغط «البلاغات الدورية
 * المولَّدة» فيجد «البلاغات المتكررة وسببها الجذر».
 *
 * ── القرار: منظرٌ بمعاملٍ لا مِرساةٌ بلا أثر ───────────────────────────────
 * المدخلُ الثاني يصير `?view=<code>` — **وجهةٌ مختلفةٌ فعلًا** تقرؤها الشاشةُ
 * وتُبدّل بها ما تعرض. فيتحقّق نصُّ INJ-0512 («كلُّ رابطٍ يشير إلى وجهةٍ
 * مختلفة») ونصُّ INJ-0514 («ثلاثةُ روابطَ لثلاثةِ مناظرَ متمايزة»).
 *
 * ◆ ولا يُحذف مدخلٌ: كلاهما وعدٌ للمستخدمِ بمحتوًى — والحذفُ يُفقده أحدَهما.
 * ◆ والمِرساةُ تُنزع بعد إضافةِ المعامل، فلا يبقى مميِّزٌ لا يُميّز.
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

echo "══ مدخلا البلاغاتِ صارا منظرين ══\n\n";

$norm = function ($r) {
    $r = explode('#', (string) $r, 2)[0];
    $r = explode('?', $r, 2)[0];
    return strtolower(trim(preg_replace('~^(\.\./)+~', '', $r)));
};
/* رمزُ المنظرِ من التسمية — مستقرٌّ ومقروء */
$VIEW = array(
    'البلاغات الدورية المولَّدة'                    => 'generated',
    'إعدادات البلاغات — الأنواع والمهل والتصعيد'    => 'settings',
);

$groups = array();
$r = $conn->query('SELECT id, role_id, label_ar, route FROM nav_items ORDER BY role_id, id');
while ($r && ($x = $r->fetch_assoc())) {
    $groups[(int) $x['role_id'] . '|' . $norm($x['route'])][] = $x;
}
$conn->query('CREATE TABLE IF NOT EXISTS nav_items_archive_views LIKE nav_items');
$done = 0; $skipped = 0;
foreach ($groups as $k => $g) {
    if (count($g) < 2) { continue; }
    /* الأوّلُ يبقى نقيًّا؛ ومَن بعدَه يصير منظرًا */
    foreach (array_slice($g, 1) as $row) {
        $lbl = trim(preg_replace('~\s+~u', ' ', (string) $row['label_ar']));
        $code = isset($VIEW[$lbl]) ? $VIEW[$lbl] : null;
        if ($code === null) {
            /* رمزٌ مشتقٌّ من التسميةِ حين لا تكون في الخريطة — مستقرٌّ لا عشوائيّ */
            $code = 'v' . substr(md5($lbl), 0, 6);
        }
        $base = explode('#', (string) $row['route'], 2)[0];
        if (strpos($base, '?') !== false) { $skipped++; continue; }   /* له معاملٌ سلفًا */
        $new = $base . '?view=' . $code;
        $conn->query('INSERT INTO nav_items_archive_views SELECT * FROM nav_items WHERE id = ' . (int) $row['id']);
        $st = $conn->prepare('UPDATE nav_items SET route = ? WHERE id = ?');
        if (!$st) { continue; }
        $id = (int) $row['id'];
        $st->bind_param('si', $new, $id);
        if ($st->execute() && $conn->affected_rows > 0) {
            $done++;
            echo '  ✔ دور ' . $row['role_id'] . ' «' . mb_substr($lbl, 0, 34) . '» ⇒ ' . $new . "\n";
        }
        $st->close();
    }
}
echo "\n  المحوَّل إلى منظرٍ: {$done}" . ($skipped ? " · له معاملٌ سلفًا: {$skipped}" : '') . "\n";
$left = 0;
$r = $conn->query("SELECT COUNT(*) FROM nav_items WHERE route LIKE '%#%'");
if ($r) { $left = (int) $r->fetch_row()[0]; }
echo "  · صفوفٌ ما تزال بمِرساة: {$left}\n";
exit(0);
