<?php
/**
 * 2027_12_22_rpr03_event_classification.php — تصنيفُ أنواعِ الأحداثِ الثمانيةِ والخمسين
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `RPR-03` §٤·٢ الخطوة ١: *«تصنيفُ الأنواعِ الثمانيةِ
 *   والخمسين: حدثُ أعمالٍ يستلزم أثرًا · أو تدقيقيّ · أو مهملٌ يُتقاعد —
 *   **بحكمٍ موثَّقٍ لكلٍّ**»* · و§٤·١: *«الحدثُ التدقيقيُّ لا يحتاج مستهلكَ
 *   أعمال — يحقّق غرضَه الرقابيَّ بوجودِه … وطلبُ مستهلكٍ لكلِّ واحدٍ من
 *   الثمانيةِ والخمسين هدفٌ لا يُبلَغ ولا ينبغي»*.
 *
 * ◆ **والمقامُ يُعاد قياسُه** — §٢·١: «أحدَ عشرَ حدثَ أعمال» **خطُّ أساسٍ
 *   تاريخيٌّ لا حالةٌ راهنة.**
 *
 * ◆ **وموضعُ الحكمِ يُفتح هنا والحكمُ يليه** — الترتيبُ نفسُه المحروقُ في هذه
 *   الجولة: الموضعُ ← ثمَّ الملءُ بدليلِه ← ثمَّ القفل. ⛔ ولا تُخترَع أحكامٌ
 *   في هجرةٍ لتمرَّ قاعدة.
 *
 * ◆ **والقاعدةُ الصلبةُ: لا حكمَ بلا دليلٍ يسمّي قياسَه** — فحكمٌ على نوعِ
 *   حدثٍ بلا شاهدٍ يُبقي السؤالَ «لِمَ صُنِّف هكذا؟» بلا جواب، **ويسمح برفعِ
 *   نسبةِ التغطيةِ بتقاعدِ ما لا ينبغي تقاعدُه**.
 *
 * التشغيل: php database/migrations/2027_12_22_rpr03_event_classification.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `rpr03_event_classification` (
  `event_key`    VARCHAR(120) NOT NULL,
  `category`     VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'فئة الناقل كما هي',
  `classification` ENUM('BUSINESS','AUDIT','RETIRED','NEEDS_ADJUDICATION') NOT NULL
                 DEFAULT 'NEEDS_ADJUDICATION'
                 COMMENT 'RPR-03 §4-2 الخطوة 1 — والتدقيقي لا يحتاج مستهلك اعمال',
  `rule_applied` VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'اي قاعدة انتجت الحكم',
  `evidence`     VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الدليل المقيس — ولا حكم بلا شاهد',
  `occurrences`  INT(11)      NOT NULL DEFAULT 0,
  `with_amount`  INT(11)      NOT NULL DEFAULT 0,
  `with_qty`     INT(11)      NOT NULL DEFAULT 0,
  `snapshot_id`  VARCHAR(48)  NOT NULL DEFAULT '',
  `ruled_at`     DATETIME     NULL,
  PRIMARY KEY (`event_key`),
  KEY `ix_rec_class` (`classification`),
  CONSTRAINT `chk_rec_evidence`
    CHECK (`classification` = 'NEEDS_ADJUDICATION' OR `evidence` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-03 §4-2 — تصنيف انواع الاحداث بحكم موثق لكل'");
if (!$ok) { exit("✘ تعذّر الإنشاء: {$conn->error}\n"); }
echo "  ✔ `rpr03_event_classification` — والقاعدةُ الصلبةُ: لا حكمَ بلا شاهد\n";

$n = (int) $conn->query("SELECT COUNT(DISTINCT event_key) FROM ems_business_events")->fetch_row()[0];
printf("  أنواعٌ في الصندوق: **%d** · وكلُّها بلا حكمٍ بعد\n", $n);

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ الموضعُ مُهيَّأ — والحكمُ بـ`rpr03_event_classify.php`\n";
