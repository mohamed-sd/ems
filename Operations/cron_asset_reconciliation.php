<?php
/**
 * cron_asset_reconciliation.php — مطابقةُ ساعاتِ الأصولِ بالتشغيل (CLI فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * حكمُ N-17: «تقريرٌ دوريٌّ يقارن ساعاتِ سجلِّ الأصولِ بساعاتِ التايم شيت لكلِّ
 * معدةٍ ويُلزم بتفسيرِ الفرق — فلا فرقَ بلا سبب. ومنه معدلُ الإهلاكِ بالساعةِ
 * والإهلاكُ غيرُ المحتسَب — **ومعدةٌ عملت ولم تُهلك تشوّه تكلفةَ المشروعِ وربحيتَه**».
 *
 * ◆ الخدمةُ `AssetReconciliationService` مبنيةٌ منذ N-17 و**بلا نداءٍ واحدٍ**
 *   في الشجرة (كشفُ ra11). وجدولُها `asset_hour_reconciliations` فيه صفّان —
 *   أي أنها شُغِّلت مرةً في اختبارٍ ثم نُسيت. هذا الكرونُ بابُها.
 *
 * ◆ وهي عاطلةٌ بنيويًّا (`ON DUPLICATE KEY UPDATE` على المعدة×الفترة) —
 *   فإعادةُ التشغيلِ تُحدِّث ولا تُكرِّر.
 *
 * التشغيل:
 *   php Operations/cron_asset_reconciliation.php --company=4 --period=2026-07
 *   php Operations/cron_asset_reconciliation.php --company=4 --last=6   (آخرُ ستةِ أشهرٍ فيها نشاط)
 *   php Operations/cron_asset_reconciliation.php --company=4 --last=6 --dry-run
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Fleet/AssetReconciliationService.php';
use App\Services\Fleet\AssetReconciliationService as ARS;

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z\-]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = $m[2] ?? '1'; }
}
$companyId = isset($args['company']) ? (int) $args['company'] : 0;
$period    = $args['period'] ?? null;
$last      = isset($args['last']) ? max(1, (int) $args['last']) : 0;
$tol       = isset($args['tolerance']) ? (float) $args['tolerance'] : 1.0;
$dry       = isset($args['dry-run']);
if ($companyId <= 0) { exit("يلزم --company=<id>\n"); }
if (!$period && !$last) { exit("يلزم --period=YYYY-MM أو --last=<n>\n"); }

/* الفتراتُ ذاتُ النشاطِ تُكتشف من المصدرِ لا تُفترض */
$periods = array();
if ($period) {
    $periods[] = $period;
} else {
    /* ◆ «آخرُ ستةِ أشهر» = آخرُها **حتى اليوم** لا آخرُها تاريخًا: البياناتُ فيها
       صفوفٌ بتواريخَ مستقبلية (2031-03 مثلًا) فترتيبُ التاريخِ وحدَه يلتقط بذورًا
       ويترك الأشهرَ ذاتَ الحجمِ الحقيقيّ. (قِيست: 2026-06 فيه 2,725 صفًّا و81 معدة
       بينما 2031-03 فيه صفرُ معدة.) */
    $st = $conn->prepare("SELECT DATE_FORMAT(t.`date`, '%Y-%m') p, COUNT(*) n
                          FROM timesheet t
                          WHERE t.company_id = ? AND t.`date` IS NOT NULL AND t.`date` <= CURDATE()
                          GROUP BY p ORDER BY p DESC LIMIT ?");
    $st->bind_param('ii', $companyId, $last);
    $st->execute();
    $rs = $st->get_result();
    while ($x = $rs->fetch_assoc()) { $periods[] = $x['p']; }
    $st->close();
}
if (!$periods) { exit("لا فترةَ ذاتَ نشاط\n"); }

echo "══ مطابقةُ ساعاتِ الأصولِ — كيان $companyId · حدُّ السماح $tol ساعة ══\n";
echo '  الفترات: ' . implode(' · ', $periods) . "\n";

$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? (int) $r->fetch_row()[0] : 0; };
$before = $one("SELECT COUNT(*) FROM asset_hour_reconciliations WHERE company_id=$companyId");

if ($dry) {
    echo "\n◆ قياسٌ فقط — لا كتابة\n";
    foreach ($periods as $p) {
        $pe = $conn->real_escape_string($p);
        $n = $one("SELECT COUNT(DISTINCT o.equipment) FROM timesheet t JOIN operations o ON o.id=t.operator
                   WHERE t.company_id=$companyId AND DATE_FORMAT(t.`date`,'%Y-%m')='$pe'");
        printf("  %-9s معداتٌ ذاتُ نشاط: %s\n", $p, number_format($n));
    }
    exit(0);
}

$totRows = 0; $totUndep = 0;
foreach ($periods as $p) {
    $res = ARS::buildPeriod($conn, $companyId, $p, $tol);
    $totRows += (int) $res['rows'];
    $totUndep += (int) $res['undepreciated'];
    printf("  %-9s معدات=%-5s عملت ولم تُهلك=%-5s %s\n",
        $p, $res['rows'], $res['undepreciated'], empty($res['ok']) ? '✘ ' . $res['reason'] : '');
}

$after = $one("SELECT COUNT(*) FROM asset_hour_reconciliations WHERE company_id=$companyId");
echo "\n══ الحصيلة ══\n";
printf("  صفوفُ المطابقة: %s ⇐ %s\n", number_format($before), number_format($after));
printf("  عولجت %s معدة-فترة · منها %s عملت ولم تُهلك\n", number_format($totRows), number_format($totUndep));

$open = $one("SELECT COUNT(*) FROM asset_hour_reconciliations WHERE company_id=$companyId AND state='open'");
$undep = $one("SELECT COUNT(*) FROM asset_hour_reconciliations WHERE company_id=$companyId AND undepreciated_flag=1");
printf("  فروقٌ تنتظر تفسيرًا: %s · إهلاكٌ غيرُ محتسَب: %s\n", number_format($open), number_format($undep));

$r = $conn->query("SELECT period, equipment_id, register_hours, timesheet_hours,
                          ROUND(ABS(register_hours-timesheet_hours),2) فرق, undepreciated_flag
                   FROM asset_hour_reconciliations
                   WHERE company_id=$companyId AND state='open'
                   ORDER BY ABS(register_hours-timesheet_hours) DESC LIMIT 8");
if ($r && $r->num_rows) {
    echo "\n  أكبرُ الفروقِ غيرِ المفسَّرة:\n";
    printf("    %-9s %-8s %12s %12s %10s %s\n", 'الفترة', 'معدة', 'سجلُّ الأصول', 'التايم شيت', 'الفرق', 'بلا إهلاك');
    while ($x = $r->fetch_row()) {
        printf("    %-9s %-8s %12s %12s %10s %s\n", $x[0], $x[1], $x[2], $x[3], $x[4], $x[5] ? 'نعم' : '—');
    }
}
echo "\n[asset-recon " . date('Y-m-d H:i:s') . "] rows={$totRows} undepreciated={$totUndep} open={$open}\n";
