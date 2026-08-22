<?php
/**
 * 2027_10_18_frd_sec008_space_url_shadow.php
 *   FR-SEC-008 · CHG-SEC-SCOPE-01 — سجلُّ ظلِّ حارسِ العنوانِ المباشر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا ظلٌّ قبلَ الإنفاذ**: العنوانُ المباشرُ **لا يسأل عن المساحةِ اليوم**
 *   — قِيس حيًّا: الدورُ 4 يفتح `Equipments/equipments.php` بـ200 وهو مُعلَنٌ
 *   `FORBIDDEN` في مساحتِه. وقلبُ المنعِ دفعةً على **265 ظهورًا ممنوعًا في 18
 *   مساحة** تغييرُ وصولٍ حيٍّ لا يقرّره منفِّذ. فيُقاس الأثرُ أوّلًا بأسمائِه،
 *   ثم يُقلَب بقرارِ مالكٍ على أثرٍ معلوم.
 *
 * ◆ **والسجلُّ يجيب سؤالَ المالكِ لا سؤالي**: من أيُّ دورٍ · إلى أيِّ مسارٍ · في
 *   أيِّ مساحةٍ · كم مرّة. فالقرارُ يُبنى على أثرٍ مقيسٍ لا على تقدير.
 *
 * التشغيل:  php database/migrations/2027_10_18_frd_sec008_space_url_shadow.php
 * الرجوع :  php database/migrations/2027_10_18_frd_sec008_space_url_shadow.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

if (in_array('--revert', $argv, true)) {
    $conn->query("DROP TABLE IF EXISTS `gov_space_url_shadow`");
    echo "↺ أُسقط سجلُّ الظل\n";
    exit(0);
}

$conn->query("CREATE TABLE IF NOT EXISTS `gov_space_url_shadow` (
    `id`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `route`    VARCHAR(200) NOT NULL,
    `space_ar` VARCHAR(120) NOT NULL,
    `role_id`  INT NOT NULL,
    `user_id`  INT NOT NULL,
    `mode`     VARCHAR(12) NOT NULL COMMENT 'observe · enforce',
    `seen_at`  DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `ix_route_space` (`route`, `space_ar`),
    KEY `ix_seen` (`seen_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='FR-SEC-008 — ما كان سيُمنَع لو أُنفِذ حارسُ العنوانِ المباشر'");

function cnt(mysqli $c, $sql) { $r = @$c->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; }
$ok = cnt($conn, "SELECT COUNT(*) FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gov_space_url_shadow'");
if ($ok !== 1) { exit("⛔ لم يُنشأ الجدول\n"); }

$forb = cnt($conn, "SELECT COUNT(*) FROM `gov_space_appearances` WHERE `cls` = 'FORBIDDEN'");
$sp   = cnt($conn, "SELECT COUNT(DISTINCT `space_ar`) FROM `gov_space_appearances`");
printf("✔ سجلُّ الظلِّ جاهز · المقامُ المرصود: %d ظهورًا ممنوعًا في %d مساحة\n", $forb, $sp);
echo "◆ الوضعُ الافتراضيُّ **observe** — لا يُمنع أحدٌ حتى يُقلَب بـ`EMS_SPACE_URL_GUARD=enforce`\n";
echo "  بقرارِ مالكِ المجالِ على أثرٍ مقيس.\n";

ems_migration_recorded(__FILE__, $conn, 0);
