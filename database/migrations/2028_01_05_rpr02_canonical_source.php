<?php
/**
 * 2028_01_05_rpr02_canonical_source.php — سجلُّ المصدرِ القانونيِّ للحقيقةِ المكرَّرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `RPR-02` §١٢ **#١٠** («حقيقةٌ واحدةٌ لها مصدران») =
 *   **٩ كياناتٍ** بعد تصحيحِ مدى الحقيقة، و§٥·٩ تشترط `Duplicate Canonical
 *   Source = 0`: *«لكلِّ حقيقةِ أعمالٍ **مالكٌ قانونيٌّ واحد** · وتظهر إسقاطًا
 *   في أيِّ عددٍ من الإدارات · ⛔ **ولا تُنشأ ولا تُعدَّل من مصدرَين مستقلَّين**»*.
 *
 * ◆ **وبُحث عن سجلٍّ قائمٍ يسمّي المالكَ القانونيَّ فلم يوجد**:
 *   `repair01_master_entities` **تسعةُ صفوفٍ** لبياناتِ الهويّةِ الرئيسةِ لا
 *   دفترُ ملكيّةٍ للحقائق · و`repair01_ownership` مفتاحُها **المسار** وتصف ظهورَ
 *   المساحاتِ لا ملكيّةَ الكيانات. ⇒ **فالموضعُ يُفتح ولا يُنسخ**.
 *
 * ◆ **وأربعُ قواعدَ بترتيبٍ حتميّ — والمقياسُ يُعلن أيَّتَها حكمت**:
 *   **N1 · `ARTIFACT_NAME_IDENTITY`** — اسمُ ملفِّ السطحِ **هو اسمُ الكيان**
 *        (`Audit/iaf_findings.php` ⇐ `iaf_findings`) ⇒ أقوى الشواهد.
 *   **N2 · `DECLARED_OVER_INFERRED`** — كاتبٌ **يُعلن الجدولَ في ملفِّه**
 *        (`G1`/`G1B`) وغيرُه **مُستنتَجٌ بالقياس** (`G2`/`G3`) ⇒ المُعلِنُ هو
 *        المصدر. **وهي مرتبةُ الشواهدِ التي يستعملها قياسُ الحبّةِ نفسُه**.
 *   **N3 · `CROSS_OWNER_NEEDS_CONTRACT`** — الكتّابُ تحت **إدارتين فأكثر** ⇒
 *        ⛔ **لا يُحسم بقياس**: هذا **خرقُ §٥·٩ نفسُه** ويحتاج **عقدًا مُعلَنًا**،
 *        وهو بعينِه ما يعدّه المقياسُ **#١١**. ⇒ يُعلَن ولا يُرجَّح.
 *   **N4 · `TIE_DECLARED`** — إدارةٌ واحدةٌ ولا مُرجِّحَ بين كتّابِها ⇒ يُعلَن
 *        مفتوحًا **بأسمائهم**. ⛔ **والغموضُ يُعلَن ولا يُحسم بأوّلِ المصادفات**.
 *
 * ⛔ **ولا يُكتب مصدرٌ قانونيٌّ بلا قاعدةٍ وشاهد** — والقاعدةُ الصلبةُ تردُّه
 *   في القاعدةِ نفسِها: **من يملك كتابتَه بلا شاهدٍ يملك تصفيرَ #١٠ بجملةٍ واحدة**.
 *
 * التشغيل: php database/migrations/2028_01_05_rpr02_canonical_source.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_canonical_source` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `entity`         VARCHAR(64)  NOT NULL COMMENT 'الكيان المكتوب — حقيقة الاعمال',
  `writers`        SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'عدد الاسطح الحية التي تكتبه',
  `owners`         SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'عدد الادارات المالكة لتلك الاسطح',
  `rule_code`      ENUM('N1_ARTIFACT_NAME_IDENTITY','N2_DECLARED_OVER_INFERRED',
                        'N3_CROSS_OWNER_NEEDS_CONTRACT','N4_TIE_DECLARED') NOT NULL
                   COMMENT 'القاعدة التي حكمت — والمقياس يعلن ايتها',
  `canonical_screen` VARCHAR(12) NOT NULL DEFAULT ''
                   COMMENT 'المصدر القانوني حين يحسم بقياس — وفارغ حين لا يحسم',
  `canonical_owner`  VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'الادارة المالكة للمصدر القانوني',
  `writer_screens` VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'كل الكتاب بمعرفاتهم وملاكهم',
  `resolved`       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'هل حسم بقياس ام اعلن مفتوحا',
  `witness`        VARCHAR(600) NOT NULL COMMENT 'شاهد الحكم او شاهد تعذره — ولا صف بلا شاهد',
  `snapshot_id`    VARCHAR(48)  NOT NULL COMMENT 'اللقطة التي قيس عليها',
  `measured_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_entity` (`entity`),
  KEY `ix_rule` (`rule_code`),
  KEY `ix_resolved` (`resolved`),
  CONSTRAINT `chk_cs_witness`   CHECK (`witness` <> ''),
  CONSTRAINT `chk_cs_snapshot`  CHECK (`snapshot_id` <> ''),
  CONSTRAINT `chk_cs_resolved`  CHECK (`resolved` = 0 OR `canonical_screen` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 5-9 · المصدر القانوني للحقيقة المكررة — بقاعدة وشاهد لكل كيان'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }

$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_canonical_source")->fetch_row()[0];
echo "  ✔ `repair01_canonical_source` جاهزٌ — صفوفٌ فيه $n\n";
echo "  ✔ ثلاثُ قواعدَ صلبة: شاهدٌ لازمٌ · ولقطةٌ لازمةٌ · و`resolved` يُلزِم مصدرَه\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ السجلِّ مفتوحٌ — و`grain_entity` و`source_of_truth` لم يُمَسّا\n";
