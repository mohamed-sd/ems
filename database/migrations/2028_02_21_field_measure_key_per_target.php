<?php
/**
 * 2028_02_21_field_measure_key_per_target.php — مفتاحُ دفترِ الحقولِ بالهدفِ لا بالسطحِ وحدَه
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: alter:repair01_field_measure(uq_screen ⇒ uq_screen_req)
 *
 * ◆ **العطبُ المقيسُ حيًّا**: `--apply` في `tools/rpr02_field_measure.php` ردَّ
 *   التثبيتَ بـ`Duplicate entry 'SCR-0578' for key 'uq_screen'`. والسببُ ليس
 *   بياناتٍ فاسدةً بل **مفتاحًا يخالف نموذجَ الأمر**: `SCR-0578` تخدم هدفَين
 *   من ورقةِ الموردين (`SUP-18` قياسُ التغطية · `SUP-20` استحقاقاتُ الموردين)،
 *   و§24 من `GOV_EXEC` ينصُّ صراحةً **`Screens ≠ Placements`** — فالسطحُ
 *   الواحدُ يخدم أكثرَ من هدفٍ بحقٍّ، وستةُ أسطحٍ تفعلها الآن (‏`SCR-0652`
 *   بأربعةِ أهداف).
 *
 * ◆ **وحبّةُ الدفترِ هدفٌ لا سطح**: كلُّ صفٍّ يحمل `requirement_id` ومقامَه
 *   وبسطَه من حقولِ **ذلك الهدف**. فلو بقي المفتاحُ على السطحِ وحدَه لَسقط
 *   قياسُ الهدفِ الثاني صامتًا أو رُدَّ التثبيتُ كلُّه — وكلاهما كذبٌ على المقام.
 *   ⇒ المفتاحُ `(screen_id, requirement_id)`.
 *
 * ⛔ **ولا يُصحَّح المخزنُ لتخضرَّ الأداة**: العطبُ في المفتاحِ فيُصلَح المفتاح،
 *   ثمَّ يُعاد التشغيلُ من الصفرِ (الأداةُ تكنس دفترَها قبلَ التثبيت).
 *
 * التشغيل: php database/migrations/2028_02_21_field_measure_key_per_target.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$has = $conn->query("SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'repair01_field_measure'
      AND INDEX_NAME = 'uq_screen' LIMIT 1");
if ($has && $has->num_rows) {
    if (!$conn->query("ALTER TABLE `repair01_field_measure` DROP INDEX `uq_screen`")) {
        exit("⛔ {$conn->error}\n");
    }
    echo "✔ أُسقط المفتاحُ القديم uq_screen\n";
}
$has = $conn->query("SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'repair01_field_measure'
      AND INDEX_NAME = 'uq_screen_req' LIMIT 1");
if (!$has || !$has->num_rows) {
    if (!$conn->query("ALTER TABLE `repair01_field_measure`
        ADD UNIQUE KEY `uq_screen_req` (`screen_id`, `requirement_id`)")) {
        exit("⛔ {$conn->error}\n");
    }
    echo "✔ المفتاحُ الجديد uq_screen_req (screen_id, requirement_id)\n";
}
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
