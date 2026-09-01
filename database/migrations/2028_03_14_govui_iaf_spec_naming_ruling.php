<?php
/**
 * 2028_03_14_govui_iaf_spec_naming_ruling.php — حكمُ الانطباقِ لعقدِ الإغلاق
 * ═══════════════════════════════════════════════════════════════════════════
 * `G-MIG-04`: كلُّ اسمٍ خارجَ عُرفِ `YYYY_MM_DD_slug` في مجلَّدِ الهجراتِ
 * **مُعلَنٌ بحكمِه** — وإلّا رسبت البوّابةُ ولم يُلتزَم فوقَها.
 *
 * ◆ و`_iaf_field_closure_spec.php` **ليس هجرةً**: لا يفتح اتصالًا ولا ينفّذ
 *   جملةً، بل `return array(...)` يقرؤه الأمامُ والعكسُ معًا. وسابقتُه في
 *   الدفترِ `_ledger.php` بالحكمِ نفسِه (`UNMANAGED_NAME` · `NOT_A_MIGRATION`).
 *
 * ⛔ ولا يُرفَع `verified` بلا دليلٍ مكتوب — والدليلُ هنا **بنيةُ الملفِّ نفسِها**.
 *
 * @migration-objects: seed gov_migration_settlement
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$file = '_iaf_field_closure_spec.php';
$path = __DIR__ . '/' . $file;

/* الدليلُ يُقاس من الملفِّ لا يُدَّعى: لا اتصالَ ولا استعلامَ فيه، وهو يُرجِع مصفوفة. */
$src   = is_file($path) ? (string) file_get_contents($path) : '';
$checks = array(
    'لا اتصالَ قاعدةٍ'  => (strpos($src, 'new mysqli') === false),
    'لا استعلامَ'        => (strpos($src, '->query(') === false),
    'يُرجِع مصفوفةً'      => (strpos($src, 'return array(') !== false),
);
$found = count(array_filter($checks));
$total = count($checks);
if ($src === '' || $found !== $total) {
    echo "⛔ لم يتحقّقِ الدليلُ البنيويُّ — لا يُرفَع الحكمُ.\n";
    ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
    return;
}

$kind     = 'UNMANAGED_NAME';
$ruling   = 'NOT_A_MIGRATION';
$evidence = 'عقدُ بياناتٍ مشتركٌ يقرؤه 2028_03_13 الأمامُ وعكسُه: صفرُ اتصالٍ وصفرُ استعلامٍ في نصِّه، ويُرجِع array';
$by       = 'GOV_UI_EXEC/IAF';
$owner    = 'GOV_UI_EXEC §2 — إغلاقُ حقولِ المراجعةِ الداخليّة 2026-09-01';

$st = $conn->prepare("INSERT INTO gov_migration_settlement
    (filename, kind, ruling, evidence, verified, objects_checked, objects_found, owner_ref, settled_at, settled_by)
    VALUES (?, ?, ?, ?, 1, ?, ?, ?, NOW(), ?)
    ON DUPLICATE KEY UPDATE kind = VALUES(kind), ruling = VALUES(ruling),
        evidence = VALUES(evidence), verified = VALUES(verified),
        owner_ref = VALUES(owner_ref), settled_by = VALUES(settled_by),
        objects_checked = VALUES(objects_checked), objects_found = VALUES(objects_found)");
if (!$st) { echo "prepare fail: " . $conn->error . "\n"; }
else {
    $st->bind_param('ssssiiss', $file, $kind, $ruling, $evidence, $total, $found, $owner, $by);
    if ($st->execute()) { echo "حكمُ الانطباقِ مقيَّدٌ: {$file} ⇐ {$kind} · {$ruling}\n"; }
    else { echo "execute fail: " . $st->error . "\n"; }
    $st->close();
}
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
