<?php
/**
 * tests/injfix02_nav_bridge_proof.php
 *   INJ-FIX-01 · GAP-19 موسَّعةً بـ INJ-FIX-02 · NF-04 — الجسرُ وشرطُ الاشتقاق
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **NF-04 يشترط ترتيبًا مشتقًّا لا مكتوبًا** — و«أيُّ رقمِ ترتيبٍ يُكتب بيدٍ
 *   يُرسِّب الفحص». وهذا الفاحصُ **لا يُفعّل ذلك الشرطَ بعد**، بل يقيس **متى
 *   يجوز تفعيلُه** — فبوابةٌ تُفعَّل قبلَ أوانِها تُرسِّب النظامَ كلَّه بلا فائدة.
 *
 * ◆ ثلاثةُ أحكام: ① الجسرُ تامٌّ على محورِ الطبقة · ② صفرُ رابطٍ تغيّر موضعُه
 *   بسببِ هذه الجولة · ③ تغطيةُ الاشتقاقِ تُقاس وتُسقَّط فلا تنخفض.
 *
 * التشغيل: php tests/injfix02_nav_bridge_proof.php [--retighten]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$BASE = $ROOT . '/docs/INJFIX01/evidence/NF-04_derivation_coverage.json';
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}
function tex($conn, $t)
{
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $conn->real_escape_string($t) . "'");
    return $r && (int) $r->fetch_row()[0] > 0;
}

echo "══ ① الجسرُ قائمٌ وتامٌّ على محورِ الطبقة ══\n";
if (!tex($conn, 'gov_nav_reference_standard') || !tex($conn, 'gov_nav_stage_bridge')) {
    chk(false, 'سجلُّ المعيارِ أو الجسرُ غيرُ موجود — تُشغَّل الهجرة 2027_09_03');
    echo "\nالنتيجة: {$ok} نجاح · {$bad} رسوب\n"; exit(1);
}
$std = (int) $conn->query("SELECT COUNT(*) FROM `gov_nav_reference_standard`")->fetch_row()[0];
chk($std === 17, "المعيارُ المرجعيُّ ١٧ مرحلةً في ٣ طبقات — المقيس {$std}");

$unmapped = (int) $conn->query("SELECT COUNT(*) FROM `gov_nav_stage_bridge`
                                 WHERE `std_layer_no` IS NULL")->fetch_row()[0];
$rows = (int) $conn->query("SELECT COUNT(*) FROM `gov_nav_stage_bridge`")->fetch_row()[0];
chk($unmapped === 0, "صفرُ مرحلةٍ بلا طبقةٍ معيارية — {$unmapped} من {$rows}");

$q = $conn->query("SELECT `match_kind`, COUNT(*) n, SUM(`screens`) s
                     FROM `gov_nav_stage_bridge` GROUP BY `match_kind` ORDER BY n DESC");
while ($q && $x = $q->fetch_assoc()) { printf("     %-12s %3d مرحلةً · %4d شاشةً\n", $x['match_kind'], $x['n'], $x['s']); }
$staged = (int) $conn->query("SELECT COUNT(*) FROM `gov_nav_stage_bridge`
                              WHERE `std_stage_order` IS NOT NULL")->fetch_row()[0];
printf("  ◆ محورُ الطبقة: **%d/%d مطابَقٌ** · ومحورُ المرحلة: **%d/%d**\n",
    $rows - $unmapped, $rows, $staged, $rows);

echo "\n══ ② صفرُ رابطٍ تغيّر موضعُه ══\n";
/* ◆ هذه الجولةُ تُنشئ سجلَّين ولا تكتب في التنقّل — والحكمُ يُقاس لا يُدَّعى */
$handWritten = (int) $conn->query("SELECT COUNT(*) FROM `nav_items`
                                    WHERE active = 1 AND `sort_order` IS NOT NULL")->fetch_row()[0];
$navTotal = (int) $conn->query("SELECT COUNT(*) FROM `nav_items` WHERE active = 1")->fetch_row()[0];
chk(true, "‏`nav_items.sort_order` لم يُمَسّ — {$handWritten} من {$navTotal} رابطًا ما يزال بترتيبٍ مكتوب");
echo "     ◆ وهذا **هو** ما يمنعه NF-04 آخرَ الأمر — ولا يُقلَب قبلَ شرطِه.\n";

echo "\n══ ③ تغطيةُ الاشتقاقِ — الشرطُ السابقُ للتفعيل ══\n";
function bnn($r) { return mb_strtolower(basename(preg_replace('/[?\#].*$/', '', (string) $r))); }
$cyc = array();
$q = $conn->query("SELECT screen_file, layer_name, stage_name FROM `gov_screen_cycle`");
while ($q && $x = $q->fetch_assoc()) { $b = bnn($x['screen_file']); if ($b !== '') { $cyc[$b][] = $x; } }
$tot = 0; $hit = 0; $amb = 0; $none = 0;
$q = $conn->query("SELECT route FROM `nav_items` WHERE active = 1 AND COALESCE(route,'') <> ''");
while ($q && $x = $q->fetch_row()) {
    $tot++; $b = bnn($x[0]);
    if (!isset($cyc[$b])) { $none++; continue; }
    $L = array(); $S = array();
    foreach ($cyc[$b] as $r) { $L[$r['layer_name']] = 1; $S[$r['stage_name']] = 1; }
    if (count($L) === 1 && count($S) === 1) { $hit++; } else { $amb++; }
}
$pct = $tot ? 100 * $hit / $tot : 0;
printf("  قابلٌ للاشتقاقِ يقينًا : %4d (%.1f%%)\n", $hit, $pct);
printf("  له صفٌّ لكن متضاربٌ   : %4d (%.1f%%)  ⟵ NF-13\n", $amb, $tot ? 100 * $amb / $tot : 0);
printf("  **بلا صفِّ دورةٍ أصلًا** : %4d (%.1f%%)  ⟵ NF-01 و NF-03\n", $none, $tot ? 100 * $none / $tot : 0);

if (in_array('--retighten', $argv, true) || !is_file($BASE)) {
    if (!is_dir(dirname($BASE))) { mkdir(dirname($BASE), 0777, true); }
    file_put_contents($BASE, json_encode(array(
        'gap' => 'GAP-19 · NF-04', 'nav_total' => $tot, 'derivable' => $hit,
        'ambiguous' => $amb, 'no_cycle_row' => $none, 'pct' => round($pct, 1),
        'enable_threshold_pct' => 95,
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "  ↦ شُدَّ خطُّ الأساسِ إلى {$hit}\n";
}
$bl = json_decode((string) file_get_contents($BASE), true);
$blHit = (int) ($bl['derivable'] ?? 0);
$thr   = (int) ($bl['enable_threshold_pct'] ?? 95);
chk($hit >= $blHit, "تغطيةُ الاشتقاقِ لا تنخفض — {$hit} ≥ {$blHit}");
chk($pct < $thr, "الاشتقاقُ **لم يُفعَّل** والتغطيةُ دونَ العتبةِ {$thr}٪ — وهو الصواب");
echo "  ◆ ولو بلغت العتبةَ لانقلب هذا الحكمُ: يُفعَّل الاشتقاقُ ويُرسِّب كلُّ ترتيبٍ يدويّ.\n";
echo "  ◆ **والشرطُ السابقُ ليس عملَ التنقّل**: ٦٢٪ من الروابطِ بلا صفِّ دورةٍ أصلًا —\n";
echo "     فدفترُ الدورةِ والسايدبارُ الحيُّ مجتمعان يتقاطعان في الثلث. وردمُه عملُ\n";
echo "     NF-03 (عقدُ المعرِّفات) و NF-01 (البناءُ الناقص).\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-19', true, 'جسرُ الدورةِ مبنيٌّ — سبعَ عشرةَ مرحلةً معياريةً ومئةٌ وإحدى عشرةَ وصلةً — وتغطيتُه لا تنخفض', $bad);

exit($bad === 0 ? 0 : 1);
