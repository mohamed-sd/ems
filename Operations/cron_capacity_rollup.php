<?php
/**
 * cron_capacity_rollup.php — شبكةُ أمانِ الاشتقاقِ الصعوديّ F-03/F-04 (CLI فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * التشغيل: php Operations/cron_capacity_rollup.php --company=4 [--dry-run]
 * الانحرافُ عيبٌ يُبلَّغ ويُصحَّح — وما رفضته قيودُ CHECK يبقى ظاهرًا للفحصين
 * CK-01/CK-03 حتى يُصحَّح مصدرُه.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Capacity/CapacityRollupService.php';
use App\Services\Capacity\CapacityRollupService as CR;

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z\-]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = $m[2] ?? '1'; }
}
$co = (int) ($args['company'] ?? 0);
if ($co <= 0) { exit("يلزم --company=<id>\n"); }

if (isset($args['dry-run'])) {
    $r = $conn->query("SELECT COUNT(*) FROM (
        SELECT p.id FROM op_containers p
        LEFT JOIN op_containers c ON c.parent_id = p.id AND c.is_deleted = 0
        WHERE p.company_id = $co AND p.is_deleted = 0
          AND EXISTS (SELECT 1 FROM op_containers x WHERE x.parent_id = p.id AND x.is_deleted = 0)
        GROUP BY p.id, p.allocated_qty
        HAVING ABS(p.allocated_qty - COALESCE(SUM(c.cap_qty), 0)) >= 0.005) d");
    echo 'آباءٌ منحرفون: ' . ($r ? $r->fetch_row()[0] : '?') . " (قياسٌ بلا كتابة)\n";
    exit(0);
}

$res = CR::recompute($conn, $co);
printf("[capacity-rollup %s] measured=%d drifted=%d fixed=%d blocked=%d\n",
    date('Y-m-d H:i:s'), $res['measured'], $res['drifted'], $res['fixed'], count($res['blocked']));
foreach ($res['blocked'] as $b) { echo "  ⚠ يتجاوز السعةَ فلم يُشتق: $b\n"; }
exit(count($res['blocked']) ? 1 : 0);
