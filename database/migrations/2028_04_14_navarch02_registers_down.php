<?php
/**
 * 2028_04_14_navarch02_registers_down.php — عكسُ سجلَّاتِ NAV-ARCH-02
 * @migration-objects: nav_workspaces columns, nav_workspace_placements,
 *                     nav_legacy_disposition, nav_cross_domain_register
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العكسُ يُسقِط ما أنشأته الهجرةُ وحدَه**: الجداولُ الثلاثةُ الجديدةُ
 *   وأعمدةُ §6 الستّةُ في `nav_workspaces`. و`kind` **لم تمسَّه الهجرةُ فلا
 *   يمسُّه العكس**.
 * ⛔ **ولا يُسقَط جدولٌ فيه صفوفٌ صامتًا**: إن حمل الجدولُ أحكامًا مسجَّلةً
 *   يُسمَّى عددُها ويُترَك — فحكمٌ يُمحى بلا نداءٍ خسارةٌ لا تراجُع.
 *   وللإسقاطِ رغمَ ذلك: `--force`.
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
$force = in_array('--force', $argv, true);

foreach (array('nav_cross_domain_register', 'nav_legacy_disposition', 'nav_workspace_placements') as $t) {
    $q = $conn->query("SHOW TABLES LIKE '{$t}'");
    if (!$q || !$q->num_rows) { echo "= {$t} غيرُ موجود\n"; continue; }
    $c = $conn->query("SELECT COUNT(*) c FROM `{$t}`");
    $n = $c ? (int) $c->fetch_assoc()['c'] : 0;
    if ($n > 0 && !$force) {
        echo "! {$t} فيه {$n} حكمًا مسجَّلًا — لم يُسقَط. للإسقاط: --force\n";
        continue;
    }
    echo $conn->query("DROP TABLE `{$t}`") ? "- جدول {$t} ({$n} صفًّا)\n"
                                           : "x {$t}: " . $conn->error . "\n";
}

foreach (array('version', 'governing_source', 'owner_domain', 'workspace_type',
               'canonical_name', 'workspace_code') as $col) {
    $q = $conn->query("SHOW COLUMNS FROM `nav_workspaces` LIKE '{$col}'");
    if (!$q || !$q->num_rows) { echo "= nav_workspaces.{$col} غيرُ موجود\n"; continue; }
    echo $conn->query("ALTER TABLE `nav_workspaces` DROP COLUMN `{$col}`")
        ? "- nav_workspaces.{$col}\n" : "x nav_workspaces.{$col}: " . $conn->error . "\n";
}

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000), 'baseline');
