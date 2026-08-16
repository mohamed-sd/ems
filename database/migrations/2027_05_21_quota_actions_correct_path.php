<?php
/**
 * 2027_05_21_quota_actions_correct_path.php
 * ═══════════════════════════════════════════════════════════════════════════
 * الثلاثةُ الباقية (quota.allocate/consume/post): المكافئُ الحيُّ في
 * `Suppliers/shares_coverage.php` لا `Operations/` — گوتشا مسارٍ لا غياب.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
if (!is_file($ROOT . '/Suppliers/shares_coverage.php')) { exit("✘ الشاشةُ غيرُ موجودة\n"); }

$n = 0;
foreach (array(
    'quota.allocate' => 'إسنادُ الحصصِ حيٌّ على op_containers',
    'quota.consume'  => 'الاستهلاكُ حيٌّ في container_consumption/allocated',
    'quota.post'     => 'ترحيلُ الحصةِ = صفُّ استهلاكٍ بمفتاحِ عطالة',
) as $code => $why) {
    $st = $conn->prepare("UPDATE nav09_action_map
                          SET state='alias', live_code=?,
                              guard_evidence=CONCAT('مكافئٌ حيٌّ: Suppliers/shares_coverage.php — ', ?),
                              guard_verified='yes', updated_at=NOW()
                          WHERE canonical_code=? AND state='declared_unbuilt'");
    $st->bind_param('sss', $code, $why, $code);
    $st->execute(); $n += $conn->affected_rows; $st->close();
}
echo "رُقّي $n من 3\n✔ تمّت\n";
