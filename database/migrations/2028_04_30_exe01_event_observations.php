<?php
/**
 * 2028_04_30_exe01_event_observations.php — فصلُ القياسِ عن الحكم (EXE-01 §4)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الدستور: «لا يُمحى ما قرّره المالكُ بعمليةٍ آليّة».** و`gov_event_rulings`
 *   يحمل اليومَ **حكمًا بشريًّا وقياسًا آليًّا في الصفِّ نفسِه** — سبعةُ أعمدةِ
 *   قياسٍ من اثني عشر. وكتّابُه يكتبون مباشرةً، فإعادةُ القياسِ **تدهس تفويضَ
 *   المالكِ بلا خطأٍ ولا إنذار**.
 *
 * ◆ **والإضافةُ أوّلًا لا النزع**: يُنشأ سجلُّ الملاحظاتِ ورؤيةُ الجمع، ويُرحَّل
 *   القرّاءُ إليهما، **ثمَّ** تُنزَع أعمدةُ القياسِ في دفعةٍ تالية. فلا يُكسَر
 *   قارئٌ في المنتصف. ⇐ **هذه الهجرةُ إضافةٌ محضةٌ: صفرُ `ALTER` وصفرُ حذف.**
 *
 * ⛔ **والملاحظةُ تُربَط بإصدارِ الحكمِ لا بالحكمِ مجرَّدًا** (‏§4 حرفًا): فإن
 *   تغيّر الحكمُ عُرِف أيُّ إصدارٍ كانت تقيسه. ومفتاحُ `gov_event_rulings`
 *   هو `event_key` وحدَه — فيُحمَل معه رقمُ الإصدارِ في الملاحظة.
 *
 * ⛔ **ولا يُختلق تاريخٌ سابقٌ لحكمٍ قائم** (‏§4): الأحكامُ الثمانيةُ والخمسون
 *   تُعتمَد **إصدارًا أوّلَ بمصدرِها المعروف** — ولا تُكتب لها ملاحظةٌ رجعيّةٌ
 *   بزمنٍ لم تُقَس فيه.
 *
 * ⛔ **ولا تُكتب ملاحظةٌ بلا معرِّفِ لقطة** (‏معيار `OBSERVATION_WITHOUT_SNAPSHOT = 0`):
 *   العمودُ `NOT NULL` بلا افتراضيٍّ فارغ — فالقيدُ بنيويٌّ لا عُرفيّ.
 *
 * التشغيل: php database/migrations/2028_04_30_exe01_event_observations.php
 * العكس  : php database/migrations/2028_04_30_exe01_event_observations_down.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
$t0 = microtime(true);
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

/* ═══ ⓪ حارسٌ: لا تُنشأ الملاحظاتُ بلا سجلِّ أحكامٍ تُنسَب إليه ═══════════ */
$ex = $conn->query("SHOW TABLES LIKE 'gov_event_rulings'");
if (!$ex || $ex->num_rows === 0) { exit("⛔ `gov_event_rulings` غيرُ موجود — لا تُنشأ ملاحظةٌ بلا حكمٍ تنتسب إليه\n"); }

$made = 0; $had = 0;

/* ═══ ① سجلُّ الملاحظاتِ — إلحاقيٌّ لا يُعدَّل ═══════════════════════════ */
$ddl = "CREATE TABLE IF NOT EXISTS `gov_event_observations` (\n"
     . "  `observation_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
     . "  `event_key` VARCHAR(120) NOT NULL COMMENT 'مفتاحُ الحدثِ — يطابق gov_event_rulings',\n"
     . "  `ruling_version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'إصدارُ الحكمِ الذي تقيسه هذه الملاحظة',\n"
     . "  `snapshot_id` VARCHAR(64) NOT NULL COMMENT 'لا ملاحظةَ بلا لقطة — القيدُ بنيويّ',\n"
     . "  `measured_at` DATETIME NOT NULL,\n"
     . "  `produced_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'المُنتِجُ المكتشَف',\n"
     . "  `consumers_total` SMALLINT UNSIGNED NOT NULL DEFAULT 0,\n"
     . "  `consumers_active` SMALLINT UNSIGNED NOT NULL DEFAULT 0,\n"
     . "  `effect_consumers` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'مستهلكُ الأثرِ لا المراقب',\n"
     . "  `watch_consumers` SMALLINT UNSIGNED NOT NULL DEFAULT 0,\n"
     . "  `handler_on_disk` TINYINT(1) NOT NULL DEFAULT 0,\n"
     . "  `observed_class` ENUM('BUSINESS','AUDIT','OPERATIONAL_TELEMETRY','CONTROL','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN'\n"
     . "     COMMENT 'التصنيفُ المرصودُ — لا يُكتب في الحكم',\n"
     . "  `ruling_at_measure` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'الحكمُ كما كان لحظةَ القياس',\n"
     . "  `drift` ENUM('ALIGNED','DRIFTED','UNDETERMINED') NOT NULL DEFAULT 'UNDETERMINED'\n"
     . "     COMMENT 'أَيوافق المرصودُ الحكم؟ — يُرفَع ولا يُصحَّح آليًّا',\n"
     . "  `evidence_ref` VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'مرجعٌ قابلٌ للفحص',\n"
     . "  PRIMARY KEY (`observation_id`),\n"
     . "  UNIQUE KEY `uq_key_version_snapshot` (`event_key`, `ruling_version`, `snapshot_id`),\n"
     . "  KEY `ix_drift` (`drift`),\n"
     . "  KEY `ix_snapshot` (`snapshot_id`)\n"
     . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n"
     . "  COMMENT='EXE-01 §4 — قياسُ الأحداثِ منفصلًا عن حكمِها · إلحاقيٌّ لا يُعدَّل'";
$e = $conn->query("SHOW TABLES LIKE 'gov_event_observations'");
$existed = $e && $e->num_rows > 0;
if (!$conn->query($ddl)) { exit('⛔ فشل إنشاء `gov_event_observations`: ' . $conn->error . "\n"); }
if ($existed) { $had++; echo "  = `gov_event_observations` موجودٌ سلفًا\n"; } else { $made++; echo "  + `gov_event_observations` أُنشئ\n"; }

/* ═══ ② رؤيةُ الجمعِ — للعرضِ ولا يُكتب فيها ═══════════════════════════ */
$view = "CREATE OR REPLACE VIEW `v_event_ruling_current` AS\n"
      . "SELECT r.`event_key`,\n"
      . "       r.`ruling`      AS `governing_ruling`,\n"
      . "       r.`reason`      AS `ruling_reason`,\n"
      . "       r.`decided_by`  AS `decided_by`,\n"
      . "       r.`decided_at`  AS `decided_at`,\n"
      . "       o.`snapshot_id`, o.`measured_at`, o.`produced_count`,\n"
      . "       o.`consumers_active`, o.`effect_consumers`, o.`watch_consumers`,\n"
      . "       o.`observed_class`, o.`drift`\n"
      . "  FROM `gov_event_rulings` r\n"
      . "  LEFT JOIN `gov_event_observations` o\n"
      . "         ON o.`event_key` = r.`event_key`\n"
      . "        AND o.`observation_id` = (SELECT MAX(o2.`observation_id`)\n"
      . "                                    FROM `gov_event_observations` o2\n"
      . "                                   WHERE o2.`event_key` = r.`event_key`)";
if (!$conn->query($view)) { exit('⛔ فشل إنشاء الرؤية: ' . $conn->error . "\n"); }
echo "  + رؤية `v_event_ruling_current` (أحدثُ حكمٍ × أحدثُ قياس)\n";

printf("\n◆ EXE-01 §4: أُنشئ %d · قائمٌ سلفًا %d · زمن %.2fs\n", $made, $had, microtime(true) - $t0);
echo "⛔ ولم يُمَسَّ `gov_event_rulings` بعمودٍ ولا صفٍّ — إضافةٌ محضة.\n";

require_once __DIR__ . '/_ledger.php';
if (function_exists('ems_migration_recorded')) {
    ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
} else {
    echo '◆ قيِّدها بـ`php database/migrate.php mark-applied ' . basename(__FILE__) . "`\n";
}
