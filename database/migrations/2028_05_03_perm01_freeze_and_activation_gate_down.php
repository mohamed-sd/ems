<?php
/**
 * 2028_05_03_perm01_freeze_and_activation_gate_down.php — عكسُ التجميدِ والبوّابة
 * ◆ **العكسُ يُسقِط الحارسَ لا القرار**: القادحان يُحذفان والجدولان يُسقَطان،
 *   فيعود المخطَّطُ إلى ما كان — ولا يُمَسُّ قالبٌ ولا منحةٌ ولا صلاحيّةُ أحد.
 * ⛔ ولا يُنشئ هذا الملفُّ شيئًا — عكسٌ محضٌ.
 * ◆ **ورفعُ التجميدِ وحدَه لا يحتاج هذا الملفَّ**: سطرٌ واحدٌ يُطفئ الصفَّ
 *   ويُبقي البوّابةَ قائمةً — والعكسُ يُنزع بها البوّابةُ نفسُها.
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
if ($conn->connect_errno) { exit("connect fail: " . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

foreach (array('trg_perm01_grant_freeze', 'trg_perm01_profile_activate') as $t) {
    $conn->query("DROP TRIGGER IF EXISTS `{$t}`");
    echo "- قادحٌ حُذف: {$t}\n";
}
foreach (array('gov_profile_activation_approval', 'gov_policy_freeze') as $t) {
    $conn->query("DROP TABLE IF EXISTS `{$t}`");
    echo "- جدولٌ أُسقط: {$t}\n";
}
$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` IN ('2028_05_03_perm01_freeze_and_activation_gate.php',
                                    '2028_05_03_perm01_freeze_and_activation_gate_down.php')");
echo "- قيدا الدفتر: " . $conn->affected_rows . "\n\n✔ عُكست.\n";
