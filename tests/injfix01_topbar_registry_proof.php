<?php
/**
 * tests/injfix01_topbar_registry_proof.php — INJ-FIX-01 · GAP-28 (الموجةُ ب)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «التسعةُ تدخل السجلَّ المعياريَّ وتخضع لعزلِ المساحةِ كغيرِها ·
 *   وصفرُ مسارٍ مصيَّرٍ خارجَ السجل».
 *
 * ◆ **والمساراتُ تُقرأ من `includes/topbar.php` نفسِه لا من قائمةٍ منسوخة** —
 *   فقائمةٌ منسوخةٌ تتقادم بصمتٍ، ويُضاف رابطٌ عاشرُ فلا يراه أحد. **والفاحصُ
 *   الذي يقرأ نسختَه هو لا يفحص شيئًا.**
 *
 * التشغيل: php tests/injfix01_topbar_registry_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
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

/* ══ ① المساراتُ تُستخرَج من المصدرِ الحيّ ═══════════════════════════════ */
$src = (string) @file_get_contents($ROOT . '/includes/topbar.php');
if ($src === '') { exit("✘ تعذّرت قراءةُ includes/topbar.php\n"); }
/* تُنزع التعليقاتُ أولًا — فمسارٌ مذكورٌ في شرحٍ ليس مسارًا مُصيَّرًا */
$code = '';
foreach (token_get_all($src) as $tk) {
    if (is_array($tk) && ($tk[0] === T_COMMENT || $tk[0] === T_DOC_COMMENT)) { continue; }
    $code .= is_array($tk) ? $tk[1] : $tk;
}
$routes = array();
if (preg_match_all("/ems_url\(\s*'([^']+\.php)'/", $code, $m)) {
    foreach ($m[1] as $r) { $routes[$r] = 1; }
}
$routes = array_keys($routes);
sort($routes);
echo "══ ① مساراتُ الشريطِ العلويِّ من المصدرِ الحيّ ══\n";
printf("  المُستخرَجُ الآن: %d مسارًا\n", count($routes));
foreach ($routes as $r) { echo "     · {$r}\n"; }
chk(count($routes) >= 9, 'استُخرج تسعةُ مساراتٍ فأكثرَ من المصدر — ' . count($routes));

/* ══ ② كلُّ مسارٍ: في سجلِّ العزلِ أو في إعفاءٍ مُعلَن ═════════════════════ */
echo "\n══ ② كلٌّ في السجلِّ أو في إعفاءٍ مكتوب ══\n";
$r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_topbar_exemptions'");
$hasEx = $r && (int) $r->fetch_row()[0] > 0;
if (!$hasEx) { chk(false, 'سجلُّ الإعفاءاتِ غيرُ موجود — تُشغَّل الهجرة 2027_09_04'); }

$outside = array();
foreach ($routes as $t) {
    $b = mb_strtolower(basename(preg_replace('/[?\#].*$/', '', $t)));
    $q = $conn->prepare("SELECT COUNT(*) FROM `gov_space_appearances`
                          WHERE LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(`route`,'?',1),'/',-1)) = ?");
    $q->bind_param('s', $b); $q->execute();
    $inAp = (int) $q->get_result()->fetch_row()[0]; $q->close();
    $inEx = 0;
    if ($hasEx) {
        $q = $conn->prepare("SELECT COUNT(*) FROM `gov_topbar_exemptions` WHERE `route` = ?");
        $q->bind_param('s', $t); $q->execute();
        $inEx = (int) $q->get_result()->fetch_row()[0]; $q->close();
    }
    if ($inAp === 0 && $inEx === 0) { $outside[] = $t; }
    printf("     %-32s %s\n", $t, $inAp > 0 ? "بالسجل ({$inAp})" : ($inEx > 0 ? 'إعفاءٌ مُعلَن' : '**خارج**'));
}
chk(count($outside) === 0, '**صفرُ مسارٍ مصيَّرٍ خارجَ السجلِّ والإعفاء** — ' . count($outside)
    . (count($outside) ? ' — ' . implode(' · ', $outside) : ''));

/* ══ ③ لا إعفاءَ بلا سبب — ولا إعفاءَ متقادم ═════════════════════════════ */
echo "\n══ ③ الإعفاءاتُ مكتوبةُ السببِ وغيرُ متقادمة ══\n";
if ($hasEx) {
    $noReason = array(); $stale = array();
    $q = $conn->query("SELECT `route`,`kind`,`reason` FROM `gov_topbar_exemptions`");
    $cnt = 0;
    while ($q && $x = $q->fetch_assoc()) {
        $cnt++;
        if (trim((string) $x['reason']) === '') { $noReason[] = $x['route']; }
        if (!in_array($x['route'], $routes, true)) { $stale[] = $x['route']; }
        printf("     %-32s %-16s %s\n", $x['route'], $x['kind'], mb_substr($x['reason'], 0, 40));
    }
    chk(count($noReason) === 0, "لكلِّ إعفاءٍ سببٌ مكتوب — {$cnt} إعفاءً");
    chk(count($stale) === 0, 'لا إعفاءَ متقادمًا — كلُّ مُعفًى ما يزال في الشريط ('
        . count($stale) . ')' . (count($stale) ? ' — ' . implode(' · ', $stale) : ''));
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-28', true, 'مساراتُ الشريطِ العلويِّ العشرةُ في السجل — والإعفاءُ مُعلَنٌ في gov_topbar_exemptions', $bad);

exit($bad === 0 ? 0 : 1);
