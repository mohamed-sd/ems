<?php
/**
 * 2028_01_09_rpr02_field_measure_type.php — دفترُ الحقولِ بنوعِ الحقل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — المقياسُ **#٣** يجمع في مقامٍ واحدٍ حقولًا تسأل عنها
 *   **ستُّ خطواتٍ مختلفةٍ في §٧**: `BUSINESS_INPUT` (الخطوة ٥ · الحقولُ الناقصة)
 *   · `IMPORTED_READONLY` (٦ · المستوردة) · `DERIVED` (٧ · المشتقّة) ·
 *   `FK_INHERITED`/`PARENT_INHERITED` (٢ · الأبُ والابن) · و`PK_GENERATED`
 *   **يقول الملفُّ عنه بنفسِه «يولّده النظام ولا يُحرَّر»**.
 *   ⇒ **فنسبةٌ واحدةٌ لستَّةِ أسئلةٍ تُخفي أيَّها يحتاج عملًا**.
 *
 * ◆ **وهذا الجدولُ يفكّك ولا يُقصي**: كلُّ نوعٍ بمقامِه وبسطِه، **والمجموعُ
 *   يبقى كما هو** — فمن شاء النسبةَ المخلوطةَ جمعها، ومن شاء خطوةً بعينِها
 *   قرأها وحدَها. ⛔ **ولا يُخرَج نوعٌ من المقامِ هنا** — الإخراجُ قرارُ عرضٍ
 *   في اللوحةِ **بنصٍّ يُذكر**، لا حذفٌ في المخزن.
 *
 * التشغيل: php database/migrations/2028_01_09_rpr02_field_measure_type.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);
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
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_field_measure_type` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `field_type`  VARCHAR(40) NOT NULL COMMENT 'نوع الحقل التصميمي',
  `step_ref`    VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'خطوة 7 التي تسأل عن هذا النوع',
  `applicable`  INT(11) NOT NULL DEFAULT 0 COMMENT 'المقام لهذا النوع',
  `matched`     INT(11) NOT NULL DEFAULT 0 COMMENT 'ما وجد حاضرا في الاثر',
  `design_rule` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'ما يقوله الملف عن هذا النوع بنفسه',
  `witness`     VARCHAR(400) NOT NULL COMMENT 'شاهد — ولا صف بلا شاهد',
  `snapshot_id` VARCHAR(48) NOT NULL,
  `measured_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type` (`field_type`),
  CONSTRAINT `chk_fmt_witness`  CHECK (`witness` <> ''),
  CONSTRAINT `chk_fmt_snapshot` CHECK (`snapshot_id` <> ''),
  CONSTRAINT `chk_fmt_bounds`   CHECK (`matched` <= `applicable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 3 · دفتر الحقول مفككا بنوع الحقل — ستة اسئلة لا سؤال'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }
echo "  ✔ `repair01_field_measure_type` جاهزٌ\n";
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ التفكيكِ مفتوحٌ — و`repair01_field_measure` لم يُمَسّ\n";
