<?php
/**
 * 2027_12_19_amd01_decision_impact.php — سجلُّ أثرِ القرارِ بأربعةَ عشرَ محورًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المخرَجُ بنصِّه** — `MASTER_EXEC` §٤·٥: *«أنشئْ `Decision Impact Register`:
 *   الوثائقُ · الإداراتُ · الشاشاتُ · الحقولُ · الحبّةُ · مصدرُ الحقيقةِ ·
 *   البياناتُ · الصلاحياتُ · آلاتُ الحالةِ · الاعتماداتُ · الأحداثُ ·
 *   التكاملاتُ · الاختباراتُ · الترحيل»* — **أربعةَ عشرَ محورًا**.
 *
 * ◆ **ونصّان بعددَين — والأعلى يحكم**: `AMD-01` المرحلة ٤ تقول «بأعمدته
 *   التسعة» و`MASTER_EXEC` §٤·٥ يعدّ أربعةَ عشر. ⛔ **وليس تعارضًا يُرفع**:
 *   الأربعةَ عشرَ **تشمل** التسعةَ ولا تناقضها، و`MASTER_EXEC` رتبتُه ٠ في
 *   سلطةِ النصوصِ الإجرائيّة (§٠·١). ⇒ **يُعمل بالأشمل** ويُسجَّل الحكم.
 *
 * ◆ **وخمسةٌ من الأربعةَ عشرَ قائمةٌ سلفًا** في `repair01_decisions` ومملوءةٌ
 *   ١٢٥/١٢٥: الوثائقُ · الشاشاتُ · القواعدُ · الترحيلُ · الشيفرة. **فتُنقل
 *   حرفًا** ⛔ ولا يُعاد تأليفُها.
 *
 * ◆ **والتسعةُ الباقيةُ لا تُشتقّ مكانيكيًّا — وهذا مقيسٌ لا مظنون**: عمودُ
 *   `affected_screens` **مفرداتُه حرّةٌ لا معرِّفات**: ١٨٠ مفردةً فريدةً منها
 *   «م08-4» و«ر09-2» و«شيت» و«كل الأسطح» — **وصفرٌ منها يشبه `SCR-nnnn`
 *   وصفرٌ يشبه رمزَ نطاق**. فلا جسرَ إلى الشاشاتِ ولا إلى حقولِها ولا حبّتِها
 *   ولا مصدرِ حقيقتِها. ⇒ **تُوسَم `NEEDS_ADJUDICATION`** ويُعلَن عددُها،
 *   ⛔ **ولا تُملأ بنصٍّ مؤلَّفٍ ليكتمل عدَدُ الأعمدة** — فسجلُّ أثرٍ مخترَعٌ
 *   أسوأُ من سجلٍّ ناقصٍ مُعلَن.
 *
 * التشغيل: php database/migrations/2027_12_19_amd01_decision_impact.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_decision_impact` (
  `decision_id`   VARCHAR(32)  NOT NULL,
  `axis`          ENUM('DOCUMENTS','DEPARTMENTS','SCREENS','FIELDS','GRAIN','SOURCE_OF_TRUTH',
                       'DATA','PERMISSIONS','STATE_MACHINES','APPROVALS','EVENTS',
                       'INTEGRATIONS','TESTS','MIGRATION') NOT NULL
                  COMMENT 'محاور MASTER_EXEC §4-5 الاربعة عشر',
  `impact`        TEXT         NULL COMMENT 'الاثر — منقولا حرفا من مصدره',
  `impact_source` VARCHAR(120) NOT NULL DEFAULT ''
                  COMMENT 'من اين نقل — ولا اثر بلا مصدر يسميه',
  `status`        ENUM('PROJECTED','NEEDS_ADJUDICATION','NOT_APPLICABLE') NOT NULL
                  DEFAULT 'NEEDS_ADJUDICATION'
                  COMMENT 'PROJECTED اسقط بدليل · NEEDS_ADJUDICATION لا يشتق مكانيكيا',
  `blocked_why`   VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'سبب عدم الاسقاط — مقيسا لا مظنونا',
  `snapshot_id`   VARCHAR(48)  NOT NULL DEFAULT '',
  `updated_at`    DATETIME     NULL,
  PRIMARY KEY (`decision_id`, `axis`),
  KEY `ix_di_status` (`status`),
  CONSTRAINT `chk_di_projected`
    CHECK (`status` <> 'PROJECTED' OR (`impact` IS NOT NULL AND `impact_source` <> '')),
  CONSTRAINT `chk_di_blocked`
    CHECK (`status` <> 'NEEDS_ADJUDICATION' OR `blocked_why` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='AMD-01 المرحلة 4 — سجل اثر القرار باربعة عشر محورا'");
if (!$ok) { exit("✘ تعذّر الإنشاء: {$conn->error}\n"); }
echo "  ✔ `repair01_decision_impact` — وقاعدتان: لا إسقاطَ بلا مصدرٍ · ولا حجبَ بلا سبب\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ الموضعُ مُهيَّأ — والإسقاطُ بـ`amd01_phase4_impact.php`\n";
