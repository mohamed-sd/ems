<?php
/**
 * ENG-01 · فحصُ النظائرِ القائمةِ بالتفصيل — أعمدةٌ وقيودٌ وتعدادٌ حقيقي
 * التشغيل:  php tools/eng01_analogue_probe.php
 * (TABLE_ROWS تقديرٌ في InnoDB — فالتعدادُ هنا COUNT(*) حقيقي)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$db = $conn;

$TARGETS = [
    'ems_business_events', 'ems_event_deliveries', 'ems_event_consumers',
    'ems_event_dead_letter', 'ems_processed_events', 'ems_job_queue',
    'event_consumers', 'capacity_outbox', 'recurring_tasks',
    'asset_hour_reconciliations', 'fin_depreciation', 'fleet_depreciation_profile',
    'fin_assets', 'fin_event_links',
];

foreach ($TARGETS as $t) {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->bind_param('s', $t); $st->execute();
    $ex = (int)$st->get_result()->fetch_row()[0]; $st->close();
    if (!$ex) { echo "\n▐ $t — غير موجود\n"; continue; }

    $cnt = $db->query("SELECT COUNT(*) FROM `$t`")->fetch_row()[0];
    echo "\n══════════════════════════════════════════════════════════════════\n";
    echo "▐ $t   —   صفوفٌ حقيقية: $cnt\n";
    echo "══════════════════════════════════════════════════════════════════\n";
    $r = $db->query("SHOW CREATE TABLE `$t`");
    echo $r->fetch_assoc()['Create Table'] . "\n";
}
echo "\n";
