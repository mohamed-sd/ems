<?php
/**
 * tools/repair01_w8_regression.php — انحدارُ الوحدتَين المرجعيّتَين
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **§19 صراحةً**: «المبيعات والموردون Reference Implementations · لا تعيد
 *   بناءهما · **شغّل Regression أوّلًا**». فهذه الأداةُ تُشغَّل **قبل أوّلِ
 *   لمسة** بشوطِ `BASELINE`، ثمَّ بعد الإصلاحِ بشوطِ `AFTER` — والبوّابةُ
 *   تقارن الشوطَين فتسقط على **تراجعٍ** لا على سقوطٍ لحظيّ.
 *
 * ◆ **وكلُّ فحصٍ يعيد بناءَ مقامِه**: مقامٌ صفريٌّ يخرج `EMPTY_DENOM` ولا يمرُّ
 *   `PASS` صامتًا — وهو صنفُ العطبِ الذي أوقع `W1-08` وحواجبَ W07 الخمسة.
 *
 * ◆ **ولا فحصَ بلا متطلَّبٍ كاشف**: `revealed_by` في كلِّ صفّ، و`CHECK` في
 *   المخطَّطِ يمنع صفًّا بلا كاشف.
 *
 * التشغيل: php tools/repair01_w8_regression.php --baseline
 *          php tools/repair01_w8_regression.php --after
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w8_scan.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$argvJoined = implode(' ', array_slice($argv, 1));
$phase = (strpos($argvJoined, '--after') !== false) ? 'AFTER' : 'BASELINE';
$dry   = (strpos($argvJoined, '--dry') !== false);

if (!repair01_w8_table_exists($conn, 'repair01_w8_regression')) {
    exit("دفترُ الانحدارِ غيرُ موجود — شغّلْ الهجرةَ أوّلًا:\n"
       . "  php database/migrations/2027_11_25_repair01_w8_sal_sup.php\n");
}

/* الشوطُ الأوّلُ لا يُدهَس: BASELINE يُكتب مرّةً واحدةً ثمَّ يُحفَظ */
$hasBaseline = (int) repair01_w8_one($conn, "SELECT COUNT(*) FROM repair01_w8_regression WHERE phase='BASELINE'");
if ($phase === 'BASELINE' && $hasBaseline > 0 && !$dry) {
    echo "↷ شوطُ BASELINE مكتوبٌ سلفًا ($hasBaseline صفًّا) — **لا يُدهَس**.\n";
    echo "   الشوطُ الأوّلُ دليلُ ما قبلَ اللمسِ، وإعادةُ كتابتِه تمحو الدليل.\n";
    echo "   للقياسِ بعدَ الإصلاح: php tools/repair01_w8_regression.php --after\n";
    $phase = null;
}

echo "═══════ انحدارُ W08 — المبيعاتُ والموردون" . ($phase ? " · شوطُ $phase" : " · عرضٌ فقط") . " ═══════\n\n";

$res  = repair01_w8_run_regression($conn);
$runId = 'W8R-' . date('YmdHis') . '-' . substr(str_replace('.', '', (string) microtime(true)), -6);

$pass = 0; $fail = 0; $empty = 0;
$byFam = array();
foreach ($res as $key => $r) {
    $mark = $r['verdict'] === 'PASS' ? '✔' : ($r['verdict'] === 'EMPTY_DENOM' ? '◐' : '✘');
    if ($r['verdict'] === 'PASS') { $pass++; } elseif ($r['verdict'] === 'EMPTY_DENOM') { $empty++; } else { $fail++; }
    $byFam[$r['family']] = ($byFam[$r['family']] ?? 0) + 1;
    $t = $r['title'];
    $padded = $t . str_repeat(' ', max(0, 52 - mb_strlen($t, 'UTF-8')));
    echo '  ' . $mark . ' ' . str_pad($key, 26) . $padded . $r['detail'] . '  ⇐ ' . $r['rev'] . "\n";

    if ($phase === null || $dry) { continue; }
    $q = "INSERT INTO repair01_w8_regression
            (phase, run_id, check_key, family, title_ar, denominator, measured, expected, verdict, detail, revealed_by)
          VALUES ('" . $conn->real_escape_string($phase) . "','" . $conn->real_escape_string($runId) . "','"
          . $conn->real_escape_string($key) . "','" . $conn->real_escape_string($r['family']) . "','"
          . $conn->real_escape_string($r['title']) . "'," . (int) $r['denom'] . "," . (int) $r['measured'] . ",'0','"
          . $conn->real_escape_string($r['verdict']) . "','" . $conn->real_escape_string($r['detail']) . "','"
          . $conn->real_escape_string($r['rev']) . "')
          ON DUPLICATE KEY UPDATE run_id=VALUES(run_id), denominator=VALUES(denominator),
            measured=VALUES(measured), verdict=VALUES(verdict), detail=VALUES(detail),
            title_ar=VALUES(title_ar), family=VALUES(family), revealed_by=VALUES(revealed_by), run_at=NOW()";
    if ($conn->query($q) !== true) { echo "     ⚠ تعذّر التسجيل: " . $conn->error . "\n"; }
}

echo "\n" . str_repeat('─', 100) . "\n";
$famTxt = array();
foreach ($byFam as $f => $n) { $famTxt[] = "$f $n"; }
printf("الانحدار: %d فحصًا (%s)  ·  عابرٌ %d  ·  ساقطٌ %d  ·  مقامٌ صفريٌّ %d\n",
       count($res), implode(' · ', $famTxt), $pass, $fail, $empty);
if ($phase !== null && !$dry) { echo "سُجِّل شوطُ $phase بالجولة $runId\n"; }

/* المقارنةُ حين يوجد الشوطان — التراجعُ وحدَه يُسقط، لا السقوطُ الموروث */
if ($phase === 'AFTER' || $hasBaseline > 0) {
    $reg = array();
    $r = $conn->query("SELECT b.check_key, b.verdict bv, a.verdict av, b.measured bm, a.measured am
                         FROM repair01_w8_regression b
                         JOIN repair01_w8_regression a ON a.check_key = b.check_key AND a.phase='AFTER'
                        WHERE b.phase='BASELINE'");
    $regressed = array(); $fixed = 0; $same = 0;
    while ($r && $x = $r->fetch_assoc()) {
        if ($x['bv'] === 'PASS' && $x['av'] !== 'PASS') { $regressed[] = $x['check_key']; }
        elseif ($x['bv'] !== 'PASS' && $x['av'] === 'PASS') { $fixed++; }
        else { $same++; }
    }
    if ($r && ($fixed + $same + count($regressed)) > 0) {
        echo "\nالمقارنةُ بالشوطِ الأوّل: أُصلح $fixed · لم يتغيّر $same · **تراجعَ** " . count($regressed);
        if ($regressed) { echo ' ⇐ ' . implode('، ', $regressed); }
        echo "\n";
    }
}
exit($fail > 0 ? 1 : 0);
