<?php
/**
 * tools/u10_cm00_seed_batch.php — بذرُ CM-00 (U10-B9 الدفعة التمثيلية + B11 دفعة العشرين)
 * ───────────────────────────────────────────────────────────────────────────
 * يحقن قبل تضمين inheader سطرَ بذرِ المحاور من الخادم:
 *   require_once screen_contract + ems_shell_axes($perms|$permissions|null)
 * المحاورُ تُشتق من محرك الصلاحيات (AX-2/AX-3) — ولا أثرَ بصريًّا على أزرار
 * الشاشات القديمة (فئة ems-write-action وحدها تخضع للإخفاء) فالهجرة بلا خطر
 * (MG-2: يُحقن والمحليُّ يبقى عاملًا). كل ملف يُلنت بعد تعديله ويُرجع عند الفشل.
 *
 * php tools/u10_cm00_seed_batch.php [--apply]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };
$PHP_BIN = PHP_BINARY;

/* B9 — الثماني التمثيلية (MG-4: تمثل الأنماط لا الأعلى استعمالًا) */
$REPRESENTATIVE = array(
    'Finance/cfo_daily_board_fin.php',   // TP-01 لوحة إدارة
    'Contracts/contracts.php',           // TP-03 سجل مؤسسي طويل
    'Finance/approvals_inbox.php',       // TP-07 مراجعة واعتماد (CM-42)
    'ActivityLogs/activity_logs.php',    // TP-16 سجل تدقيق
    'Timesheet/timesheet.php',           // مستند ميداني (TP-17 الأقرب)
    'Reports/margin_report.php',         // TP-10 تقرير
    'Tickets/ticket_types_config.php',   // TP-11 إعدادات
    'Procurement/wh_receipt.php',        // مستند استلام (سلسلة مستندية)
);
$TARGET_TOTAL = 31;

/* إكمال الدفعة من الوجهات القانونية بتنوع المجلدات (حتمي الترتيب) */
$targets = $REPRESENTATIVE;
$r = mysqli_query($conn, "SELECT DISTINCT real_path FROM nav09_file_map WHERE real_path IS NOT NULL AND state <> 'soon' ORDER BY real_path");
$byDir = array();
while ($x = mysqli_fetch_assoc($r)) {
    $p = $x['real_path'];
    if (in_array($p, $targets, true)) { continue; }
    $byDir[dirname($p)][] = $p;
}
ksort($byDir);
$round = 0;
while (count($targets) < $TARGET_TOTAL) {
    $added = false;
    foreach ($byDir as $dir => $list) {
        if (isset($list[$round])) {
            $targets[] = $list[$round];
            $added = true;
            if (count($targets) >= $TARGET_TOTAL) { break; }
        }
    }
    if (!$added) { break; }
    $round++;
}

$SEED = "// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات\n"
      . "require_once __DIR__ . '/../includes/screen_contract.php';\n"
      . "ems_shell_axes(isset(\$perms) ? \$perms : (isset(\$permissions) ? \$permissions : null));\n";

$cnt = array('seeded' => 0, 'already' => 0, 'no_anchor' => 0, 'lint_fail' => 0);
$ledger = array();
foreach ($targets as $rel) {
    $file = $ROOT . '/' . $rel;
    if (!is_file($file)) { $cnt['no_anchor']++; $ledger[] = array($rel, 'missing'); continue; }
    $src = file_get_contents($file);
    if (strpos($src, 'ems_shell_axes') !== false) { $cnt['already']++; $ledger[] = array($rel, 'already'); continue; }
    /* المرساة: سطرُ تضمين inheader (include/require بأشكاله) */
    if (!preg_match("~^[ \t]*(?:<\?php\s+)?(?:include|require)(?:_once)?\s*\(?\s*['\"](?:\.\./)?inheader\.php['\"]~m", $src, $m, PREG_OFFSET_CAPTURE)) {
        $cnt['no_anchor']++; $ledger[] = array($rel, 'no_inheader_anchor'); continue;
    }
    $pos = $m[0][1];
    /* مرساةٌ في سياق HTML (<?php require ... ?>) تحتاج بذرةً مغلفةً ببلوكها */
    $inHtml = strpos($m[0][0], '<?php') !== false;
    $seed = $inHtml ? "<?php\n" . $SEED . "?>\n" : $SEED;
    $new = substr($src, 0, $pos) . $seed . substr($src, $pos);
    $ledger[] = array($rel, 'seed@' . $pos);
    if (!$APPLY) { $cnt['seeded']++; continue; }
    $bak = $src;
    file_put_contents($file, $new);
    exec('"' . $PHP_BIN . '" -l ' . escapeshellarg($file) . ' 2>&1', $out, $rc);
    if ($rc !== 0) {
        file_put_contents($file, $bak);
        $cnt['lint_fail']++;
        $ledger[count($ledger) - 1][1] = 'LINT_FAIL_REVERTED';
        continue;
    }
    $cnt['seeded']++;
}

$csv = $ROOT . '/docs/update0010/CM00_SEED_LEDGER.csv';
$fh = fopen($csv, 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, array('screen', 'result'));
foreach ($ledger as $L) { fputcsv($fh, $L); }
fclose($fh);

$o('══ CM-00 seed — ' . ($APPLY ? 'APPLY' : 'DRY-RUN') . ' · مستهدفة: ' . count($targets) . ' ══');
$o("  بُذرت: {$cnt['seeded']} · متبنيةٌ سلفًا: {$cnt['already']} · بلا مرساة: {$cnt['no_anchor']} · فشل لنت (رُجعت): {$cnt['lint_fail']}");
$o('الدفتر: ' . $csv);
