<?php
/* وسمُ المحميِّ بقرارِ المالك — والمجالُ مسجَّلٌ في الصفِّ سلفًا */
if (PHP_SAPI !== 'cli') { exit("CLI\n"); }
require_once dirname(__DIR__) . '/includes/env.php';
mysqli_report(MYSQLI_REPORT_OFF);
$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$c = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
$c->set_charset('utf8mb4');
$c->query("UPDATE gov_test_data_isolation SET resolution = 'PENDING_OWNER'
            WHERE policy_domain <> 'OPERATIONAL_DATA'");
echo "محميّةٌ مُعلَّقةٌ بقرارِ المالك: {$c->affected_rows}\n";
$r = $c->query("SELECT resolution, policy_domain, COUNT(*) n FROM gov_test_data_isolation
                 GROUP BY resolution, policy_domain ORDER BY n DESC");
while ($x = $r->fetch_assoc()) { printf("  %-14s %-22s %s\n", $x['resolution'], $x['policy_domain'], $x['n']); }
