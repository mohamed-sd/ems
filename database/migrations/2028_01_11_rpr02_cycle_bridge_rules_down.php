<?php
/**
 * 2028_01_11_rpr02_cycle_bridge_rules_down.php — ردُّ قيدِ الجسرِ إلى نصِّه السابق
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والردُّ يوجب تفريغَ ما كتبته القاعدةُ الجديدة** — وإلّا رُدَّ القيدُ
 *   السابقُ على صفوفٍ تخالفه فسقط الترحيلُ العكسيّ. ⇒ يُفرَّغ معرِّفُ صفوفِ
 *   `PATH_OR_SCOPE_RESOLVED` أوّلًا ثمَّ يُعاد القيدُ.
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

@$conn->query("ALTER TABLE `gov_screen_cycle` DROP CONSTRAINT `chk_cyc_bridge`");
$n = 0;
if ($conn->query("UPDATE `gov_screen_cycle`
                     SET `screen_id` = '', `bridge_rule` = 'AMBIGUOUS_DECLARED'
                   WHERE `bridge_rule` = 'PATH_OR_SCOPE_RESOLVED'")) {
    $n = $conn->affected_rows;
}
echo "  ✔ فُرِّغ معرِّفُ $n صفًّا حُسم بالقاعدةِ الرابعة\n";
$ok = $conn->query("ALTER TABLE `gov_screen_cycle`
    ADD CONSTRAINT `chk_cyc_bridge` CHECK (
        (`bridge_rule` = 'BASENAME_UNIQUE' AND `screen_id` <> '')
     OR (`bridge_rule` <> 'BASENAME_UNIQUE' AND `screen_id` = ''))");
if (!$ok) { exit("✘ تعذّر ردُّ القيد: {$conn->error}\n"); }
echo "  ✔ رُدَّ `chk_cyc_bridge` إلى نصِّه السابق\n";
