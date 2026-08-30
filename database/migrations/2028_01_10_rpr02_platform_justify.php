<?php
/**
 * 2028_01_10_rpr02_platform_justify.php — تبريرُ `PLATFORM` بأربعتِه وحكمُه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `RPR-02` **#١٣**: ثلاثون سطحًا `PLATFORM_SHARED`
 *   **مسجَّلةٌ** (‏هجرة `2028_01_07`) **وغيرُ مبرَّرة**. و§٥·٤ لا تكتفي بالتسجيل:
 *   **أربعةُ معايير معًا**، ⛔ **وما لم يستوفِ الأربعةَ يعود إلى إدارتِه من
 *   السبعَ عشرة** — وهذه الجملةُ الأخيرةُ هي التي لم تُنفَّذ.
 *
 * ◆ **وهذا الموضعُ يحفظ الحكمَ لا القرار**: لكلِّ سطحٍ **أيُّ المعاييرِ استوفى**
 *   (`criteria_met` أربعةُ مواضعَ: `1`/`·`)، وكم دورًا يُصيَّر له، ونطاقُه إن
 *   وُجد، **ومرجعُ القاعدةِ الحاكمة** (`RPR-02 §5.4`) — ⛔ **لا وسمُ اعتمادٍ
 *   يُمنح**: المرجعُ قاعدةٌ مكتوبةٌ **سابقةٌ للقياس**، ومن يملك منحَ الوسمِ بلا
 *   مرجعٍ يملك تصفيرَ المقياسِ بجملة.
 *
 * ⛔ **والمعيارُ ③ (‏مالكٌ تقنيٌّ مسمًّى شخصًا) بيانُه غيرُ موجود** —
 *   `repair01_platform_capabilities.tech_owner` فارغٌ في صفِّه الوحيد ولا جدولَ
 *   آخرَ يحمله. **وتسميةُ شخصٍ من عندنا تلفيق.** ⇒ يُحسب **مُخِلًّا** ويُعلَن،
 *   ولا يُعدُّ مستوفًى بالسكوت.
 *
 * ◆ **وقاعدتان صلبتان**: لا صفَّ بلا شاهدٍ ولا بلا لقطة · و`PLATFORM_JUSTIFIED`
 *   يوجب **مرجعَ قاعدةٍ غيرَ فارغ** · و`RETURN_TO_SCOPE` يوجب **نطاقًا مكتوبًا**
 *   (‏فالعودةُ إلى لا موضعٍ ليست عودة).
 *
 * التشغيل: php database/migrations/2028_01_10_rpr02_platform_justify.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_platform_justification` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `screen_id`      VARCHAR(12) NOT NULL COMMENT 'السطح — والمعرف هو الربط',
  `label_ar`       VARCHAR(190) NOT NULL DEFAULT '',
  `verdict`        ENUM('PLATFORM_JUSTIFIED','RETURN_TO_SCOPE','NO_SCOPE_TO_RETURN')
                   NOT NULL COMMENT 'حكم 5-4 — والمقياس يعلن ايه',
  `criteria_met`   VARCHAR(4) NOT NULL COMMENT 'اربعة مواضع: رقم المعيار المستوفى او نقطة',
  `roles_rendered` SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ادوار يصير لها المسار — شاهد المعيار الاول',
  `scope_code`     VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'النطاق الذي يعود اليه',
  `decision_ref`   VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'مرجع القاعدة الحاكمة — سابق للقياس',
  `witness`        VARCHAR(600) NOT NULL COMMENT 'شاهد الحكم — ولا صف بلا شاهد',
  `snapshot_id`    VARCHAR(48) NOT NULL COMMENT 'اللقطة التي قيس عليها',
  `measured_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pj_screen` (`screen_id`),
  KEY `ix_pj_verdict` (`verdict`),
  CONSTRAINT `chk_pj_witness`  CHECK (`witness` <> ''),
  CONSTRAINT `chk_pj_snapshot` CHECK (`snapshot_id` <> ''),
  CONSTRAINT `chk_pj_ref`      CHECK (`verdict` <> 'PLATFORM_JUSTIFIED' OR `decision_ref` <> ''),
  CONSTRAINT `chk_pj_scope`    CHECK (`verdict` <> 'RETURN_TO_SCOPE' OR `scope_code` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 13 · تبرير PLATFORM باربعة 5-4 — وما لم يستوف يعود الى ادارته'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }

$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_platform_justification")->fetch_row()[0];
echo "  ✔ `repair01_platform_justification` جاهزٌ — صفوفٌ فيه $n\n";
echo "  ✔ أربعُ قواعدَ صلبة: شاهدٌ · لقطةٌ · **تبريرٌ يوجب مرجعَ قاعدةٍ** · **وعودةٌ توجب نطاقًا**\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ الحكمِ مفتوحٌ — و`repair01_platform_surface` لم يُمَسّ\n";
