<?php
/**
 * eng01_bus_wave.php — موجةُ تسليمٍ لنوعِ حدثٍ واحد (ENG-01 · TSP-0318/0319)
 * ═══════════════════════════════════════════════════════════════════════════
 * «◆ ولا يُبنى المحرّكُ كاملًا دفعةً واحدة — بل يُثبت تسليمُ حدثٍ حقيقيٍّ
 *    واحدٍ قبلَ التوسّعِ إلى بقيةِ المستهلكين»
 * «◆ بقيةُ المستهلكينَ بموجاتٍ — نوعٌ في كلِّ موجةٍ باختبارِه»
 *
 * الوقائعُ الـ21,211 القائمةُ سبقت المحرّك، فلا صفوفَ تسليمٍ لها. وهذه الأداةُ
 * تفتح صفوفَ التسليمِ لنوعٍ واحدٍ ثم تُشغّل العامل — موجةً موجة.
 *
 * التشغيل:
 *   php tools/eng01_bus_wave.php --list
 *   php tools/eng01_bus_wave.php --type=finance.hour_recognized [--limit=50] [--dry-run]
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Services/Bus/EventOutboxFanout.php';
require_once dirname(__DIR__) . '/app/Services/Bus/EventDeliveryWorker.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$db = $conn;

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z\-]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = $m[2] ?? '1'; }
}

if (isset($args['list']) || !isset($args['type'])) {
    echo "\n▐ الأنواعُ القائمةُ وحالُ تسليمِها\n\n";
    printf("  %-46s %-9s %-9s %-9s %s\n", 'نوعُ الحدث', 'وقائع', 'مفتوحة', 'سُلّمت', 'مشتركون');
    echo "  " . str_repeat('─', 92) . "\n";
    $r = $db->query(
        "SELECT e.event_key,
                COUNT(DISTINCT e.id) facts,
                COUNT(DISTINCT d.id) opened,
                COUNT(DISTINCT CASE WHEN d.state='processed' THEN d.id END) done,
                (SELECT COUNT(*) FROM event_consumers c WHERE c.event_name=e.event_key AND c.active=1) subs
           FROM ems_business_events e
      LEFT JOIN ems_event_deliveries d ON d.outbox_id = e.id
          GROUP BY e.event_key
          ORDER BY COUNT(DISTINCT e.id) DESC"
    );
    $tf = $to = $td = 0;
    while ($x = $r->fetch_assoc()) {
        printf("  %-46s %-9s %-9s %-9s %s\n", $x['event_key'], $x['facts'], $x['opened'], $x['done'], $x['subs']);
        $tf += (int) $x['facts']; $to += (int) $x['opened']; $td += (int) $x['done'];
    }
    echo "  " . str_repeat('─', 92) . "\n";
    printf("  %-46s %-9s %-9s %-9s\n", 'المجموع', $tf, $to, $td);
    echo "\n";
    exit(0);
}

$type  = (string) $args['type'];
$limit = max(1, min(2000, (int) ($args['limit'] ?? 200)));
$dry   = isset($args['dry-run']);

$subs = \App\Services\Bus\EventOutboxFanout::subscribersFor($db, $type);
if (!$subs) { exit("لا مشتركَ نشطًا للنوع «{$type}» — سجّلْه أولًا (CK-11)\n"); }

echo "\n══ موجةُ تسليم: {$type} ══\n";
echo "  المشتركون: " . count($subs) . " (" . implode(', ', array_column($subs, 'consumer_key')) . ")\n";

$st = $db->prepare(
    "SELECT e.id, e.company_id FROM ems_business_events e
      WHERE e.event_key = ?
        AND NOT EXISTS (SELECT 1 FROM ems_event_deliveries d WHERE d.outbox_id = e.id)
      ORDER BY e.id ASC LIMIT " . $limit
);
$st->bind_param('s', $type);
$st->execute();
$pending = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

echo "  وقائعُ بلا صفِّ تسليم: " . count($pending) . "\n";
if ($dry) { echo "  (--dry-run — لم يُكتب شيء)\n\n"; exit(0); }

$opened = 0;
foreach ($pending as $p) {
    $opened += \App\Services\Bus\EventOutboxFanout::open($db, (int) $p['id'], $type, (int) $p['company_id']);
}
echo "  ✔ فُتح {$opened} صفَّ تسليم\n";

$w = new \App\Services\Bus\EventDeliveryWorker($db, 'wave-' . substr(md5($type), 0, 8));
$tot = array('claimed' => 0, 'processed' => 0, 'failed' => 0, 'dlq' => 0, 'skipped' => 0);
for ($i = 0; $i < 40; $i++) {
    $r = $w->runOnce(200);
    if ($r['claimed'] === 0) { break; }
    foreach ($tot as $k => $_) { $tot[$k] += $r[$k]; }
}
echo "  ✔ العامل: التقط={$tot['claimed']} نجح={$tot['processed']} فشل={$tot['failed']} عُزل={$tot['dlq']}\n";

$done = (int) $db->query(
    "SELECT COUNT(*) FROM ems_event_deliveries d
       JOIN ems_business_events e ON e.id = d.outbox_id
      WHERE e.event_key = '" . $db->real_escape_string($type) . "' AND d.state = 'processed'"
)->fetch_row()[0];
echo "  ✔ تسليماتٌ ناجحةٌ لهذا النوعِ الآن: {$done}\n\n";
