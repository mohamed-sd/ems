<?php
/**
 * tools/ctl_entity_name.php — تسميةُ كيانِ الحبّةِ من عمودِها المحكوم
 * ═══════════════════════════════════════════════════════════════════════════
 * **مسارُ فكِّ الجاهزيّة** (‏أمرُ الاستئنافِ الثاني: الحواجبُ فجواتُ جاهزيّةٍ
 * تنفيذيّةٌ لا أسبابُ توقّف): حاجبُ `ENTITY_UNNAMED` يطلب كيانًا مسمًّى
 * لكلِّ معاملةٍ — **والمصدرُ عمودُ `grain` المحكومُ في الدفترِ نفسِه** لا
 * نصٌّ حرٌّ: صيغُ الدفترِ الرسميّتان تُقرآن بقاعدتَين مسمّاتَين:
 *   ① «صف واحد = X واحد/واحدة …» ⇒ الكيانُ X (نصُّ الدفترِ يسمّيه حرفًا).
 *   ② «X × Y × …» ⇒ الكيانُ المركَّبُ رأسُه X (حبّةُ تقاطعٍ معلنة).
 * وكلُّ تسميةٍ تقتبس موضعَها شاهدًا — وما لم تصبه قاعدةٌ يبقى بلا كيانٍ
 * **حاجبًا لهدفِه وحدَه** ويُعرَض بالاسم.
 *
 * التشغيل: php tools/ctl_entity_name.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$snap = '';
$r = @$conn->query("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $snap = (string) $x[0]; }

$rows = array();
$r = $conn->query("SELECT requirement_id, grain FROM repair01_requirements
                    WHERE requirement_type = 'TRANSACTION'
                      AND (grain_entity IS NULL OR grain_entity = '') ORDER BY requirement_id");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }

$named = array(); $left = array();
foreach ($rows as $q) {
    $g = trim((string) $q['grain']);
    $ent = ''; $why = '';
    /* ① «صف واحد = X …» — الدفترُ يسمّي الكيانَ نصًّا */
    if (preg_match('~صف\s+واحد\s*=\s*(.+?)\s+واحدة?\b~u', $g, $m)) {
        $ent = trim($m[1]);
        $why = 'قاعدة ①: نصُّ الحبّةِ «صف واحد = ' . $ent . '» يسمّيه حرفًا';
    } elseif (preg_match('~صف\s+واحد\s*=\s*([^·—-]+)~u', $g, $m)) {
        $ent = trim($m[1]);
        $why = 'قاعدة ①ب: نصُّ الحبّةِ «صف واحد = …» ورأسُ عبارتِه';
    } elseif (preg_match('~^\s*([^×—·-]+?)\s*×~u', $g, $m)) {
        /* ② حبّةُ تقاطعٍ — رأسُها الكيان */
        $ent = trim($m[1]);
        $why = 'قاعدة ②: حبّةُ تقاطعٍ معلنةٌ ورأسُها «' . $ent . '»';
    } elseif (preg_match('~^\s*([^—·-]{2,60}?)\s*(?:—|$)~u', $g, $m) && mb_strlen(trim($m[1])) <= 60) {
        /* ③ حبّةٌ اسميّةٌ مفردةٌ قبل الشرح («فاتورة تأهيل وعناية واحدة») */
        $ent = trim(preg_replace('~\s+واحدة?$~u', '', trim($m[1])));
        $why = 'قاعدة ③: حبّةٌ اسميّةٌ مفردةٌ قبل الفاصلِ الشارح';
    }
    /* لفظُ «واحد/واحدة» عدُّ حبّةٍ لا جزءُ اسمِ الكيان — يُشذَّب من الطرف */
    $ent = trim(preg_replace('~\s+واحدة?$~u', '', $ent));
    if ($ent !== '' && mb_strlen($ent) <= 120) {
        $named[$q['requirement_id']] = array($ent, $why . ' · من عمودِ grain المحكوم');
    } else {
        $left[] = $q['requirement_id'] . ' «' . mb_substr($g, 0, 40) . '»';
    }
}

printf("\n═══ تسميةُ الكيانِ — %d معاملةً بلا كيان ═══\n", count($rows));
printf("  سُمّي: **%d** · بقي بلا قاعدةٍ (يحجب نفسَه وحدَه): **%d**\n\n", count($named), count($left));
foreach ($named as $id => $j) { printf("  ✔ %-10s ⇒ «%s» — %s\n", $id, $j[0], mb_substr($j[1], 0, 70)); }
if ($left) { echo "\n  ⛔ بلا قاعدة: " . implode(' · ', $left) . "\n"; }

if (!$APPLY) { echo "\n⛔ معاينةٌ — التطبيقُ بـ--apply\n"; exit(0); }
$n = 0;
foreach ($named as $id => $j) {
    $ok = $conn->query("UPDATE repair01_requirements
          SET grain_entity = '" . $e($j[0]) . "', entity_witness = '" . $e($j[1] . ' · لقطة ' . $snap) . "'
        WHERE requirement_id = '" . $e($id) . "' AND (grain_entity IS NULL OR grain_entity = '')");
    if (!$ok) { exit("✘ $id: {$conn->error}\n"); }
    $n += $conn->affected_rows;
}
printf("\n✔ سُمّي كيانُ **%d** معاملةً بشاهدِ موضعِه\n", $n);
