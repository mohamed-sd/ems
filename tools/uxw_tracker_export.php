<?php
/**
 * tools/uxw_tracker_export.php — تصديرُ حالةِ الترحيلِ للدفترِ الحاكم
 * ───────────────────────────────────────────────────────────────────────────
 * المخرَجُ الأول من UXW-01: «الدفترُ محدَّثًا بحالةِ الترحيل». يُخرج CSV
 * يُلصَق في ورقةِ الدفترِ (عمودُ الحالة) — كلُّ رقمٍ فيه مقيسٌ لحظةَ التشغيل:
 *   الشاشةُ في النطاقِ؟ · مخالفاتُها الآن؟ · خطُّ أساسِها البصريُّ موجود؟
 *
 *   php tools/uxw_tracker_export.php   ⇒ storage/reports/uxw01_migration_status.csv
 */
error_reporting(E_ALL);
$ROOT = dirname(__DIR__);
$PHP = PHP_BINARY;

$scope = array();
foreach (file(__DIR__ . '/uxw_scope.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    $ln = trim($ln);
    if ($ln !== '' && $ln[0] !== '#') { $scope[] = $ln; }
}

$out = array();
$out[] = "الشاشة\tفي النطاق المرحَّل\tمخالفات البوابات الآن\tخط الأساس البصري\tالحالة";
foreach ($scope as $rel) {
    $tmp = tempnam(sys_get_temp_dir(), 'uxwsc');
    file_put_contents($tmp, $rel . "\n");
    exec('"' . $PHP . '" ' . escapeshellarg(__DIR__ . '/uxw_gates.php') . ' --scope=' . escapeshellarg($tmp) . ' 2>&1', $o, $code);
    $joined = implode("\n", $o);
    unset($o);
    @unlink($tmp);
    $fileViol = preg_match_all('/^\s+\[[^\]]+\] ' . preg_quote($rel, '/') . ' /mu', $joined);
    $slug = str_replace(array('/', '.php'), array('__', ''), $rel);
    $hasBase = is_file($ROOT . '/.ssdiff/' . $slug . '.skel') ? 'موجود' : 'غائب';
    $status = ($fileViol === 0 && $hasBase === 'موجود') ? 'مُرحَّلة — بوابات صفر وخط أساس'
            : ($fileViol === 0 ? 'مُرحَّلة — بلا خط أساس بعد' : "قيد الترحيل — {$fileViol} مخالفة");
    $out[] = "{$rel}\tنعم\t{$fileViol}\t{$hasBase}\t{$status}";
}

$dir = $ROOT . '/storage/reports';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }
$file = $dir . '/uxw01_migration_status.csv';
file_put_contents($file, "\xEF\xBB\xBF" . str_replace("\t", ',', implode("\n", array_map(function ($l) {
    return str_replace(',', '،', $l); // لا فواصلَ لاتينيةً داخلَ الخلايا
}, $out))));
echo "✔ " . count($scope) . " شاشةً ⇒ storage/reports/uxw01_migration_status.csv\n";
foreach (array_slice($out, 1) as $l) { echo "  " . str_replace("\t", ' · ', $l) . "\n"; }
