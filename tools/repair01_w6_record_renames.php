<?php
/**
 * tools/repair01_w6_record_renames.php — تسجيلُ إعادةِ التسميةِ في السجلِّ المعياريّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المشكلة**: تنقيةُ W06 غيّرت أسماءَ بنودٍ في السايدبار (نزعُ تشكيلٍ وإعادةُ
 *   صياغةِ الشرطة). ومصفوفةُ حفظِ السايدبار (`uxui_preserve_check`) تطابق
 *   **بالاسمِ لا بالمسار**، فرأت الاسمَ القديمَ مفقودًا والجديدَ طارئًا ورفضت
 *   الالتزام — سبعةُ أدوارٍ بنقصٍ «غيرِ مصرَّح».
 *
 * ◆ **والعلاجُ تسجيلٌ لا عكس**: للمصفوفةِ آليّةٌ مقصودةٌ لهذا — عمودُ
 *   `nav_canonical.old_names` (قائمةٌ مفصولةٌ بـ`·`). الاسمُ القديمُ المسجَّلُ
 *   فيه يُحسَب «أُعيدت تسميتُه بالسجل» لا فقدًا. فالإصلاحُ **إعلانُ ما جرى**
 *   لا التراجعُ عن تنقيةٍ صحيحة.
 *
 * ◆ **ويُشتقُّ من اللقطةِ لا يُكتب بيد**: يُقارَن `docs/uxui_live_positions.tsv`
 *   (‏أساسُ «قبل») بـ`nav_items` الحيِّ على **المسارِ نفسِه**. فما اختلف اسمُه
 *   والمسارُ واحدٌ **إعادةُ تسميةٍ بالتعريف** — ولا يُسجَّل ما لم يتغيَّر.
 *
 * التشغيل: php tools/repair01_w6_record_renames.php [--dry]
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
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$DRY = in_array('--dry', $argv, true);

$norm = function ($r) { return strtolower(trim(preg_replace('#^\.\./#', '', preg_replace('/[?\#].*$/', '', (string) $r)))); };

/* ═══ ① أسماءُ «قبل» من لقطةِ الأساس ═══ */
$base = $ROOT . '/docs/uxui_live_positions.tsv';
if (!is_file($base)) { exit("لا أساسَ قبليًّا: $base\n"); }
$fh = fopen($base, 'r');
$hdr = fgetcsv($fh, 0, "\t");
$ix = array_flip($hdr);
$before = array();   /* route => array(labels) */
while (($row = fgetcsv($fh, 0, "\t")) !== false) {
    if (!isset($row[$ix['route']], $row[$ix['label']])) { continue; }
    $rt = $norm($row[$ix['route']]);
    $lb = trim((string) $row[$ix['label']]);
    if ($rt === '' || $lb === '') { continue; }
    $before[$rt][$lb] = true;
}
fclose($fh);
echo "① لقطةُ الأساس: " . count($before) . " مسارًا\n";

/* ═══ ② الأسماءُ الحيّةُ الآن ═══ */
$now = array();
$r = $conn->query("SELECT route, label_ar FROM nav_items WHERE active=1 AND COALESCE(route,'')<>''");
while ($r && $x = $r->fetch_assoc()) {
    $rt = $norm($x['route']);
    $lb = trim((string) $x['label_ar']);
    if ($rt !== '' && $lb !== '') { $now[$rt][$lb] = true; }
}
echo "② الحيُّ الآن: " . count($now) . " مسارًا\n";

/* ═══ ③ ما اختلف اسمُه والمسارُ واحد ═══ */
$canon = array();
$r = $conn->query("SELECT route, canonical_ar, COALESCE(old_names,'') old_names FROM nav_canonical");
while ($r && $x = $r->fetch_assoc()) { $canon[$norm($x['route'])] = $x; }

$rows = array();
foreach ($before as $rt => $labels) {
    if (!isset($now[$rt])) { continue; }                 /* المسارُ نفسُه غاب — ليس إعادةَ تسمية */
    foreach (array_keys($labels) as $old) {
        if (isset($now[$rt][$old])) { continue; }         /* الاسمُ باقٍ — لا تغيير */
        $rows[] = array('route' => $rt, 'old' => $old, 'new' => implode(' | ', array_keys($now[$rt])));
    }
}
echo "③ إعادةُ تسميةٍ مقيسة: " . count($rows) . "\n\n";

/* ═══ ④ التسجيل ═══ */
$ins = 0; $skip = 0; $noCanon = array();
foreach ($rows as $w) {
    if (!isset($canon[$w['route']])) { $noCanon[$w['route']] = 1; continue; }
    $olds = array_filter(array_map('trim', preg_split('/[\/·]+/u', $canon[$w['route']]['old_names'])));
    if (in_array($w['old'], $olds, true)) { $skip++; continue; }
    $olds[] = $w['old'];
    $val = implode(' · ', array_values(array_filter($olds, function ($v) { return $v !== '' && $v !== '—'; })));
    printf("  %-40s\n     قديم: %s\n     حيّ  : %s\n", mb_substr($w['route'], 0, 40), $w['old'], mb_substr($w['new'], 0, 70));
    if ($DRY) { $ins++; continue; }
    $ok = $conn->query("UPDATE nav_canonical SET old_names='" . $conn->real_escape_string($val)
        . "' WHERE LOWER(TRIM(route))='" . $conn->real_escape_string($w['route']) . "'");
    if ($ok) { $ins++; $canon[$w['route']]['old_names'] = $val; }
    else { echo "     ✘ {$conn->error}\n"; }
}
echo "\n④ سُجِّل: $ins · مسجَّلٌ سلفًا: $skip" . ($DRY ? '  (تجربةٌ جافّة)' : '') . "\n";
if ($noCanon) {
    echo "⚠ مسارٌ بلا صفٍّ في nav_canonical (لا يُسجَّل فيه): " . count($noCanon) . "\n";
    foreach (array_slice(array_keys($noCanon), 0, 6) as $k) { echo "   · $k\n"; }
}
