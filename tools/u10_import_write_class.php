<?php
/**
 * tools/u10_import_write_class.php — استيراد تصنيف الكتابة (U10-A5 · الورقة 21)
 * ───────────────────────────────────────────────────────────────────────────
 * يقرأ ورقة «21 — تصنيف الكتابة للأفعال» من INJAZ-MASTER-MAP-1.xlsx ويحدّث
 * nav09_action_map.write_class. الرموز الأربعة المنفصلة (DEC-A) تُحل عبر
 * nav09_action_alias بسياق شاشتها. idempotent.
 * php tools/u10_import_write_class.php [--apply]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
require_once dirname(__DIR__) . '/vendor/autoload.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };
$ROOT = dirname(__DIR__);

$MAP_CLASS = array(
    'Read Only' => 'read_only',
    'Domain Write' => 'domain_write',
    'Governance Write' => 'governance_write',
    'External Side Effect' => 'external_side_effect',
);

/* alias القديم → الجديد بسياق الشاشة (للرموز المنفصلة) */
$alias = array(); // old_code => [ [new_code, canonical_file], ... ]
$r = mysqli_query($conn, "SELECT old_code, new_code, canonical_file FROM nav09_action_alias WHERE status = 'active'");
while ($x = mysqli_fetch_assoc($r)) { $alias[$x['old_code']][] = $x; }

$known = array(); // canonical_code => screen_title
$r = mysqli_query($conn, "SELECT canonical_code, screen_title, canonical_file FROM nav09_action_map");
while ($x = mysqli_fetch_assoc($r)) { $known[$x['canonical_code']] = $x; }

$F = $ROOT . '/docs/update0010/INJAZ-MASTER-MAP-1.xlsx';
$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($F);
$reader->setReadDataOnly(true);
$reader->setLoadSheetsOnly(array('21 — تصنيف الكتابة للأفعال'));
$wb = $reader->load($F);
$sh = $wb->getSheetByName('21 — تصنيف الكتابة للأفعال');
if (!$sh) { $o('✘ الورقة 21 غير موجودة'); exit(1); }

$updates = array(); $missing = array(); $cnt = array();
foreach ($sh->toArray(null, true, false, false) as $i => $row) {
    $code = trim((string) ($row[1] ?? ''));
    $screen = trim((string) ($row[4] ?? ''));
    $wcRaw = trim((string) ($row[5] ?? ''));
    if ($i === 0 || $code === '' || !isset($MAP_CLASS[$wcRaw])) { continue; }
    $wc = $MAP_CLASS[$wcRaw];

    $target = null;
    if (isset($known[$code])) {
        $target = $code;
    } elseif (isset($alias[$code])) {
        /* حل السياق: صف الورقة يحمل الشاشة — يطابق screen_title للرمز الجديد */
        foreach ($alias[$code] as $a) {
            if (isset($known[$a['new_code']]) && mb_strpos($known[$a['new_code']]['screen_title'], mb_substr($screen, 0, 10)) !== false) {
                $target = $a['new_code']; break;
            }
        }
        if ($target === null && count($alias[$code]) === 1) { $target = $alias[$code][0]['new_code']; }
    }
    if ($target === null) { $missing[] = "$code ($screen)"; continue; }
    /* الرمز المكرر بشاشات (rpt.export ×3): التصنيف واحد فالتكرار حميد */
    $updates[$target] = $wc;
    $cnt[$wc] = ($cnt[$wc] ?? 0) + 1;
}

$o('══ U10-A5 — ' . ($APPLY ? 'APPLY' : 'DRY-RUN') . ' ══');
foreach ($cnt as $k => $v) { $o("  $k: $v"); }
$o('رموز مستهدفة فريدة: ' . count($updates) . ' · بلا هدف: ' . count($missing));
foreach ($missing as $m) { $o("  ⚠ $m"); }

if ($APPLY) {
    $st = $conn->prepare("UPDATE nav09_action_map SET write_class = ? WHERE canonical_code = ?");
    $n = 0;
    foreach ($updates as $code => $wc) {
        $st->bind_param('ss', $wc, $code);
        $st->execute() or die($st->error . "\n");
        $n += $conn->affected_rows > 0 ? 1 : 0;
    }
    $st->close();
    $o("✔ حُدّث: $n");
    $r = mysqli_query($conn, "SELECT write_class, COUNT(*) c FROM nav09_action_map GROUP BY write_class");
    while ($x = mysqli_fetch_assoc($r)) { $o('  الشاهد: ' . ($x['write_class'] ?? 'NULL') . ' = ' . $x['c']); }
}
