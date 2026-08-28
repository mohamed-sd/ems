<?php
/**
 * tools/rpr02a_pkg_diff.php — فرقُ الحزمتَين **بمحتوى الخلايا لا ببصمةِ الملف**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا لا بالبصمة**: إعادةُ التوليدِ وحدَها تغيّر `md5` بلا حرفٍ واحدٍ
 *   يتغيّر في خليّة. فالبصمةُ تقول «تحرّك» لكلِّ مصنَّفٍ أُعيد توليدُه.
 * ◆ **ولا بعدِّ الأسطرِ وحدَه**: مصنَّفٌ عُدِّلت خلاياه بلا نموِّ أسطرٍ يظهر
 *   «0» في عدِّ الأسطرِ وهو **متحرِّكٌ فعلًا** (وقع في 05 · 06 · 10 · 11).
 * ◆ فالمقياسُ هنا ثلاثيّ: أوراقٌ مضافةٌ/محذوفة · أسطرٌ مضافةٌ/محذوفة ·
 *   **صفوفٌ مُعدَّلةُ الخلايا** — والصفُّ يُطابَق بموضعِه في الورقة.
 *
 * التشغيل: php tools/rpr02a_pkg_diff.php <dirOld> <dirNew> [--json=path]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/xlsx_io.php';

$dirOld = $argv[1] ?? ($ROOT . '/docs/REPAIR01_20260823');
$dirNew = $argv[2] ?? '';
if ($dirNew === '') { exit("usage: php tools/rpr02a_pkg_diff.php <dirOld> <dirNew> [--json=path]\n"); }
$jsonOut = '';
foreach ($argv as $a) { if (strpos($a, '--json=') === 0) { $jsonOut = substr($a, 7); } }

/** صفٌّ ⇐ نصٌّ واحدٌ قابلٌ للمقارنة */
function rpr_row_sig(array $row) {
    $max = empty($row) ? -1 : max(array_keys($row));
    $out = array();
    for ($i = 0; $i <= $max; $i++) { $out[] = isset($row[$i]) ? trim((string) $row[$i]) : ''; }
    return rtrim(implode("\x1f", $out), "\x1f");
}

$files = glob($dirNew . '/*.xlsx');
sort($files, SORT_NATURAL);
$report = array();
foreach ($files as $fNew) {
    $base = basename($fNew);
    $fOld = $dirOld . '/' . $base;
    if (!is_file($fOld)) { $report[] = array('file' => $base, 'status' => 'NEW_FILE'); continue; }

    $md5Same = (md5_file($fOld) === md5_file($fNew));
    $A = xlsx_read($fOld);
    $B = xlsx_read($fNew);

    $sheetsOld = array_keys($A); $sheetsNew = array_keys($B);
    $rowsOld = 0; foreach ($A as $r) { $rowsOld += count($r); }
    $rowsNew = 0; foreach ($B as $r) { $rowsNew += count($r); }

    $modRows = 0; $addRows = 0; $delRows = 0; $sheetsTouched = array();
    foreach ($B as $sh => $rowsB) {
        $rowsA = isset($A[$sh]) ? $A[$sh] : array();
        $keys = array_unique(array_merge(array_keys($rowsA), array_keys($rowsB)));
        sort($keys);
        $m = 0; $a = 0; $d = 0;
        foreach ($keys as $k) {
            $sa = isset($rowsA[$k]) ? rpr_row_sig($rowsA[$k]) : null;
            $sb = isset($rowsB[$k]) ? rpr_row_sig($rowsB[$k]) : null;
            if ($sa === null && $sb !== null) { $a++; }
            elseif ($sa !== null && $sb === null) { $d++; }
            elseif ($sa !== $sb) { $m++; }
        }
        if ($m || $a || $d) { $sheetsTouched[] = array('sheet' => $sh, 'mod' => $m, 'add' => $a, 'del' => $d); }
        $modRows += $m; $addRows += $a; $delRows += $d;
    }
    foreach ($A as $sh => $rowsA) {
        if (!isset($B[$sh])) { $sheetsTouched[] = array('sheet' => $sh, 'mod' => 0, 'add' => 0, 'del' => count($rowsA)); $delRows += count($rowsA); }
    }

    $moved = ($modRows + $addRows + $delRows) > 0;
    $report[] = array(
        'file' => $base,
        'md5_same' => $md5Same,
        'sheets_old' => count($sheetsOld), 'sheets_new' => count($sheetsNew),
        'rows_old' => $rowsOld, 'rows_new' => $rowsNew, 'rows_delta' => $rowsNew - $rowsOld,
        'cells_mod_rows' => $modRows, 'rows_added' => $addRows, 'rows_deleted' => $delRows,
        'sheets_touched' => count($sheetsTouched),
        'verdict' => $moved ? 'MOVED' : 'IDENTICAL_CONTENT',
        'detail' => $sheetsTouched,
    );
}

$ts = date('Y-m-d H:i:s');
echo "# RPR-02-A · فرقُ الحزمتَين بالمحتوى\n";
echo "> `php tools/rpr02a_pkg_diff.php " . $dirOld . " " . $dirNew . "`\n";
echo "> مولَّدٌ حيًّا: $ts\n\n";
printf("%-46s %-8s %8s %8s %8s %8s %8s  %s\n", 'المصنَّف', 'md5', 'أسطر-', 'أسطر+', 'Δأسطر', 'مُعدَّل', 'أوراق', 'الحكم');
$movedN = 0;
foreach ($report as $r) {
    if (!isset($r['verdict'])) { printf("%-46s %s\n", $r['file'], $r['status']); continue; }
    if ($r['verdict'] === 'MOVED') { $movedN++; }
    printf("%-46s %-8s %8d %8d %8d %8d %8d  %s\n", $r['file'],
        $r['md5_same'] ? 'same' : 'diff',
        $r['rows_deleted'], $r['rows_added'], $r['rows_delta'], $r['cells_mod_rows'], $r['sheets_touched'], $r['verdict']);
}
echo "\nالمتحرِّكُ محتوًى: $movedN من " . count($report) . "\n\n";
foreach ($report as $r) {
    if (!isset($r['detail']) || !$r['detail']) { continue; }
    echo "## " . $r['file'] . "\n";
    foreach ($r['detail'] as $d) { printf("  - %-40s mod=%d add=%d del=%d\n", $d['sheet'], $d['mod'], $d['add'], $d['del']); }
}
if ($jsonOut !== '') { file_put_contents($jsonOut, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); echo "\nJSON ⇒ $jsonOut\n"; }
