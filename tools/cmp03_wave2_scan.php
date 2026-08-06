<?php
/**
 * tools/cmp03_wave2_scan.php — مسح شاشات المخزن البيني المتبقية (الموجة ٢)
 * يستخرج من كل شاشة: $CANONICAL و$FIELDS و$COLS — ويطبع التسميات المميزة
 * عبر الشاشات كلها (مادة قاموس الأعمدة).
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }

$ROOT = dirname(__DIR__);
$SKIP = array( // حارة الجلسة الموازية أو معدَّل غير ملتزم
    'Procurement/po_match.php', 'Procurement/wh_receipt.php', 'Suppliers/supplier_bank.php',
);
$files = array();
foreach (explode("\n", trim(shell_exec('git -C "' . $ROOT . '" grep -l cmp03_screen_rows -- "*.php"'))) as $f) {
    $f = trim($f);
    if ($f === '' || strpos($f, 'tools/') === 0 || strpos($f, 'database/') === 0) { continue; }
    if (in_array($f, $SKIP, true)) { continue; }
    $files[] = $f;
}

$screens = array();
$labels = array();
foreach ($files as $f) {
    $src = file_get_contents($ROOT . '/' . $f);
    if (!preg_match("/\\\$CANONICAL\s*=\s*'([^']+)'/u", $src, $m)) { continue; }
    $canon = $m[1];
    $fields = array();
    if (preg_match("/\\\$FIELDS\s*=\s*array\s*\((.*?)\);/us", $src, $m2)) {
        preg_match_all("/=>\s*'([^']*)'/u", $m2[1], $mm);
        $fields = $mm[1];
    }
    $cols = array();
    if (preg_match("/\\\$COLS\s*=\s*array\s*\((.*?)\);/us", $src, $m3)) {
        preg_match_all("/=>\s*'([^']*)'/u", $m3[1], $mm);
        $cols = $mm[1];
    }
    $screens[$canon] = array('file' => $f, 'fields' => $fields, 'cols' => $cols);
    foreach ($fields as $lb) { $labels[$lb] = isset($labels[$lb]) ? $labels[$lb] + 1 : 1; }
}

fwrite(STDOUT, "screens=" . count($screens) . "\n");
foreach ($screens as $c => $s) {
    fwrite(STDOUT, "· {$c} ← {$s['file']} (حقول " . count($s['fields']) . ")\n");
}
arsort($labels);
fwrite(STDOUT, "---- التسميات المميزة: " . count($labels) . " ----\n");
foreach ($labels as $lb => $n) { fwrite(STDOUT, str_pad($n, 4) . $lb . "\n"); }
