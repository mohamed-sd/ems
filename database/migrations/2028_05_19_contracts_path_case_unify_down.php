<?php
/**
 * 2028_05_19_contracts_path_case_unify_down.php — إعادةُ الحالةِ الصغيرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⚠ **العكسُ يعيد العطبَ عمدًا**: يُرجِع المساراتِ التسعةَ إلى الحالةِ الصغيرةِ
 *   التي كانت — أي إلى حالةٍ **تردُّ 404 على لينكس**. لا يُشغَّل إلّا لاستعادةِ
 *   لقطةٍ سابقةٍ بعينِها.
 * ◆ ويقتصر على التسعةِ بالاسمِ فلا يمسُّ مسارًا آخرَ في الجداولِ الأربعة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

$NINE = array('contract_amendments_renewal', 'contract_baseline_targets', 'contract_commercial_lines',
              'contract_coverage_cycles', 'contract_obligation_matrix', 'monthly_containers_loss',
              'monthly_sales_performance', 'precontract_review', 'sales_activities');
$TARGETS = array('nav_items' => 'route', 'modules' => 'code',
                 'nav_canonical' => 'route', 'gov_profile_items' => 'item_ref');
$total = 0;
foreach ($TARGETS as $t => $c) {
    $n = 0;
    foreach ($NINE as $s) {
        $up = 'Contracts/' . $s . '.php';
        $lo = 'contracts/' . $s . '.php';
        $st = $conn->prepare("UPDATE `{$t}` SET `{$c}` = ? WHERE `{$c}` = BINARY ?");
        $st->bind_param('ss', $lo, $up);
        $st->execute(); $n += $st->affected_rows; $st->close();
    }
    printf("   %-20s صفوفٌ أُعيدت: %d\n", $t, $n);
    $total += $n;
}
printf("   المجموع: %d\n", $total);

$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` = '2028_05_19_contracts_path_case_unify.php'");
echo "✔ عُكس — والعطبُ عاد كما كان قبلَ الجولة.\n";
