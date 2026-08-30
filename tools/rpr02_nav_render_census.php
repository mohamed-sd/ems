<?php
/**
 * tools/rpr02_nav_render_census.php — إحصاءُ التنقلِ المُصيَّرِ: **العضويّةُ والترتيبُ منفصلَين**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا**: تغييرٌ في المخزنِ قد يحرّك **ترتيبَ** الروابطِ (وهو المطلوبُ في
 *   §٦ س٤) وقد يحرّك **عضويّتَها** (‏رابطٌ يسقط أو يُزدَوَج — وهو عطب).
 *   ⛔ **وبصمةُ الـHTML وحدَها تخلط الاثنَين**: تختلف للسببَين معًا فلا تفرّق
 *   بين علاجٍ نجح وعطبٍ وقع.
 *
 * ◆ **فالإحصاءُ سطران لكلِّ دور**:
 *   - `MEMBER` بصمةُ **مجموعةِ** (رابط · اسم · قسم) **مرتَّبةً أبجديًّا** —
 *     ⇒ لا تتأثر بالترتيبِ ألبتّة، فاختلافُها **يعني فقدًا أو زيادةً**.
 *   - `ORDER`  بصمةُ **تسلسلِ** الروابطِ كما تُطبع — تختلف متى تحرَّك الترتيب.
 *
 * ◆ **والقياسُ من المُصيَّرِ لا من الجداول** — `uxp_render_role_html` بجلسةِ
 *   مستخدمٍ حقيقيٍّ لكلِّ دور، فتسري بواباتُ المنحِ كما تسري عليه.
 *
 * التشغيل:
 *   php tools/rpr02_nav_render_census.php > before.txt
 *   … التغيير …
 *   php tools/rpr02_nav_render_census.php > after.txt ; diff before.txt after.txt
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
/* ⛔ العُدَّةُ كاملةً — وإلّا سقط النداءُ فادحًا صامتًا (`config.php` يبتلع CLI) */
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$roles = array();
$r = $conn->query("SELECT DISTINCT role_id FROM nav_items WHERE active = 1 ORDER BY role_id");
while ($r && ($x = $r->fetch_row())) { $roles[] = (int) $x[0]; }

$totalPos = 0;
foreach ($roles as $rid) {
    $html = uxp_render_role_html($conn, $rid);
    $pos  = uxp_parse_nav_html($html);
    $seq = array();
    foreach ($pos as $p) {
        $seq[] = trim((string) $p['group']) . '\u{2023}' . (isset($p['section']) ? trim((string) $p['section']) : '')
               . '\u{2023}' . trim((string) $p['label']) . '\u{2023}' . uxp_norm($p['href']);
    }
    $totalPos += count($seq);
    $mem = $seq; sort($mem);
    printf("دور %-3d مواضع %-4d MEMBER %s  ORDER %s\n", $rid, count($seq),
           substr(sha1(implode("\n", $mem)), 0, 12), substr(sha1(implode("\n", $seq)), 0, 12));
}
printf("المجموع: %d دورًا · %d موضعًا مُصيَّرًا\n", count($roles), $totalPos);
