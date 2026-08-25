<?php
/**
 * 2027_11_19_repair01_w3_person_guard_down.php — نزعُ قادحِ سجلِّ الهوية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **يُشغَّل قبلَ `php tools/repair01_w3_apply.php --revert`**: الإرجاعُ يعيد
 *   الصفوفَ المحجورةَ إلى الاستعمالِ (‏`active = 1`) وهي بلا كيانٍ — والقادحُ
 *   القائمُ يمنع ذلك بحقٍّ، فيتوقّف التراجعُ في منتصفِه.
 * ═══════════════════════════════════════════════════════════════════════════
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

$err = 0;
foreach (array('chk_persons_w3_company', 'chk_persons_w3_master_key') as $c) {
    if ($conn->query("ALTER TABLE `persons` DROP CONSTRAINT `$c`") === true) { echo "  ✔ نُزع القيدُ $c\n"; }
    else { echo "  ⟳ $c — " . $conn->error . "\n"; }
}
/* والقوادحُ إن وُجدت في بيئةٍ يملك فيها المهاجرُ SUPER */
foreach (array('trg_persons_w3_bi', 'trg_persons_w3_bu') as $t) {
    if ($conn->query("DROP TRIGGER IF EXISTS `$t`") !== true) { echo "  ⟳ $t — " . $conn->error . "\n"; $err++; }
}
echo ($err === 0 ? "الحكم: رجعت ✔\n" : "الحكم: أخطاء ✘\n");
$conn->close();
exit($err === 0 ? 0 : 1);
