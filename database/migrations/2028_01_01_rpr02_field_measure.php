<?php
/**
 * 2028_01_01_rpr02_field_measure.php — موضعُ دفترِ حقولِ المبنيِّ المقيسة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `RPR-02` §١٢ المقياس **#٣** («مطابقةُ حقولِ كلِّ سطحٍ
 *   مطابَقٍ لملفِّه») كان **محجوبًا** بنصِّه: *«الجانبُ المبنيُّ `gov_field_class`
 *   لا يغطّي إلّا ٤٤ سطحًا من ٦٢١ ومفتاحُه سبيكةٌ لا مسار»*. والطرفُ التصميميُّ
 *   موجودٌ منذ الاستيعاب (`repair01_fields` ٧٥٩١ حقلًا)، **والناقصُ كان الطرفَ
 *   المبنيَّ نفسَه** — لا مفتاحًا مفقودًا.
 *
 * ◆ **وهذا الجدولُ موضعُ الدفترِ لا الدفترَ**: `rpr02_field_measure.php` يملؤه
 *   بقياسِ الأثرِ سطحًا سطحًا، **صفٌّ واحدٌ لكلِّ سطحٍ مطابَق**.
 *   ⛔ **ولا يُنشئ الجدولَ العُدّةُ نفسُها**: `ems_app` بلا `CREATE` **بحقٍّ لا
 *   بعطب** — والمخطَّطُ يتغيّر بهجرةٍ مسجَّلةٍ في الدفتر، **وإلّا رسب المقياسُ
 *   #١٢ (`UNRECONCILED_MIGRATIONS = 0`) بجدولٍ وُلد خارجَ الدفتر**.
 *
 * ◆ **وثلاثُ قواعدَ صلبةٍ تُسنُّ الآن** — لأنَّ الجدولَ يولد فارغًا فيصدق شرطُها:
 *   · **شاهدٌ لازم** — فصفٌّ بلا شاهدٍ رقمٌ لا يُراجَع.
 *   · **والمطابَقُ لا يتجاوز المنطبق** — فنسبةٌ فوقَ المئةِ عطبُ قياسٍ لا إنجاز.
 *   · **ولقطةٌ لازمة** — فرقمٌ بلا لقطةٍ لا يُعرف أيَّ نسخةٍ يمثّل (§٢·②).
 *
 * ◆ **وعمودُ `vocab_terms` هو الفارقُ بين صفرَين**: صفرُ مطابقةٍ بمفرداتٍ
 *   موجودةٍ **عطبٌ حقيقيّ**، وصفرُ مطابقةٍ بصفرِ مفرداتٍ **عجزُ قياسٍ مُعلَن**.
 *   ⛔ ولو خلا العمودُ لَاستوى الحكمان في الجدولِ واستوى الصادقُ بالكاذب.
 *
 * التشغيل: php database/migrations/2028_01_01_rpr02_field_measure.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_field_measure` (
  `id`                INT(11) NOT NULL AUTO_INCREMENT,
  `screen_id`         VARCHAR(12)  NOT NULL COMMENT 'معرف السطح المبني — الطرف المبني',
  `target_uid`        VARCHAR(12)  NOT NULL COMMENT 'الهدف المطابق في كون الأهداف',
  `requirement_id`    VARCHAR(48)  NOT NULL COMMENT 'مفتاح الطرف التصميمي في repair01_fields',
  `unit`              VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'نطاق الهدف',
  `artifact_path`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'الأثر الذي قيس منه — لا اسم الشاشة',
  `vocab_terms`       INT(11) NOT NULL DEFAULT 0
                      COMMENT 'مفردات الأثر المنتزعة — صفرها يفرق عجز القياس عن صفر المطابقة',
  `design_total`      INT(11) NOT NULL DEFAULT 0 COMMENT 'حقول التصميم كلها',
  `design_audit`      INT(11) NOT NULL DEFAULT 0 COMMENT 'منها AUDIT — الحاقية بنص 7-11 وخارج المقام',
  `design_applicable` INT(11) NOT NULL DEFAULT 0 COMMENT 'المقام المنطبق = الكل ناقص AUDIT',
  `matched`           INT(11) NOT NULL DEFAULT 0 COMMENT 'ما وجد حاضرا في الأثر',
  `missing_sample`    VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'عينة الناقص بأسمائه — لا عدد مجرد',
  `witness`           VARCHAR(500) NOT NULL COMMENT 'شاهد القياس أو شاهد العجز — ولا صف بلا شاهد',
  `snapshot_id`       VARCHAR(48)  NOT NULL COMMENT 'اللقطة التي قيس عليها',
  `measured_at`       DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_screen` (`screen_id`),
  KEY `ix_snap` (`snapshot_id`),
  KEY `ix_unit` (`unit`),
  CONSTRAINT `chk_fm_witness`  CHECK (`witness` <> ''),
  CONSTRAINT `chk_fm_snapshot` CHECK (`snapshot_id` <> ''),
  CONSTRAINT `chk_fm_bounds`   CHECK (`matched` <= `design_applicable`
                                  AND `design_applicable` <= `design_total`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 7-5 · دفتر حقول المبني مقيسة من الأثر — بشاهد لكل سطح'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }

$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_field_measure")->fetch_row()[0];
echo "  ✔ `repair01_field_measure` جاهزٌ — صفوفٌ فيه $n\n";
echo "  ✔ ثلاثُ قواعدَ صلبة: شاهدٌ لازمٌ · ولقطةٌ لازمةٌ · والمطابَقُ لا يتجاوز المنطبق\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ الدفترِ مفتوحٌ — و`repair01_fields` و`gov_field_class` لم يُمَسّا\n";
