<?php
/**
 * 2027_07_14_a11y_measurements.php — قياسُ الوصولِ الحيِّ مسجَّلًا بإصدارِ المكوّن
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ البندُ ③ في بوابةِ الترقيةِ كان مثبَّتًا على `PARTIAL` بشيفرةٍ صريحة:
 *   «التضادُّ وحدَه مقيسٌ آليًّا». فالأحدَ عشرَ الباقيةُ لم تُقَس قطُّ — وإعلانُ
 *   `PARTIAL` كان **صادقًا** لأنه لم يدَّعِ ما لم يُقَس.
 * ◆ فيُنشأ سجلٌّ يحفظ نتيجةَ الاثنَي عشرَ فحصًا **لكلِّ شاشةٍ بإصدارِ مكوّنٍ
 *   محدَّد** — فتغييرُ المكتبةِ يُبطل القياسَ ولا يرثه.
 * ◆ **و`keyboard_modality` عمودٌ لا تعليق**: القياسُ لاغٍ ما لم تُضغط Tab
 *   حقيقيةٌ قبلَه (كروم لا يُطابق `:focus-visible` بعد الفأرة). فيُحفظ شرطُ
 *   القياسِ مع الرقم — لا رقمَ بلا شرطِه.
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
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `gov_a11y_measurements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `screen_file` VARCHAR(190) NOT NULL,
  `component_version` VARCHAR(32) NOT NULL
      COMMENT 'قياسٌ بإصدارٍ سابقٍ لا يُقرأ — المكتبةُ تغيّرت فالقياسُ لاغٍ',
  `checks_total` TINYINT UNSIGNED NOT NULL DEFAULT 12,
  `violations_total` SMALLINT UNSIGNED NOT NULL,
  `keyboard_modality` TINYINT(1) NOT NULL
      COMMENT 'أضُغطت Tab حقيقيةٌ قبل القياس؟ بلا ذلك فحصُ التركيزِ لاغٍ',
  `detail_json` TEXT NOT NULL COMMENT 'مخرَجُ المسبارِ كما هو — لا ملخَّصٌ مُعاد صوغُه',
  `measured_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_screen_ver` (`screen_file`, `component_version`),
  KEY `ix_ver` (`component_version`),
  /* ◆ قياسٌ بلا نمطيةِ لوحةِ مفاتيحٍ **لا يُقبل خاليًا من المخالفات** — لأنه
       لم يقس التركيزَ أصلًا. فالصفرُ الكاذبُ مرفوضٌ بقيدٍ لا بتنبيهٍ مكتوب. */
  CONSTRAINT `chk_a11y_modality` CHECK (
      `keyboard_modality` = 1 OR `violations_total` > 0
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='الاثنا عشرَ فحصًا مقيسةً حيًّا — بإصدارِ المكوّنِ وشرطِ القياس'");

if (!$ok) { exit("✗ {$conn->error}\n"); }
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.CHECK_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='gov_a11y_measurements'");
echo "════ سجلُّ قياسِ الوصول ════\n";
echo "  الجدولُ مُنشأ · قيودُ CHECK: " . ($r ? (int) $r->fetch_assoc()['c'] : 0) . "\n";
echo "✔ صفرُ مخالفاتٍ بلا نمطيةِ لوحةِ مفاتيحٍ **مرفوضٌ بقيد**\n";
