<?php
/**
 * 2027_07_09_ui_surface_registry.php — سجلُّ أسطحِ العرض (ف١٦ · ثامنًا ①)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ قرارُ المالك: «لا تعتبر 753 ملفًّا = 753 شاشةً منطقية. ابنِ UI Surface
 *   Registry بـ surface_id · source_file · canonical_name · owner ·
 *   surface_type · parent_surface · entry_method — فالملفُّ قد ينتج أكثرَ من
 *   سطح، والسطحُ قد يتركب من ملفات. **والمقامُ الحاكمُ أسطحٌ قابلةٌ للتصييرِ
 *   لا عددُ ملفات**».
 *
 * ◆ الأصنافُ السبعةُ بنصِّها:
 *   NAVIGABLE      سطحٌ يُبلَغ من التنقلِ مباشرة
 *   CHILD_RECORD   سجلٌّ ابنٌ داخلَ سطحٍ أمّ (تبويبٌ في ملفِّ كيان)
 *   ACTION_TARGET  هدفُ فعلٍ لا يُتصفَّح (معالجُ POST · نقطةُ إجراء)
 *   MODAL_DRAWER   نافذةٌ أو درجٌ يُفتح فوقَ سطحٍ قائم
 *   DRILLDOWN      تعمُّقٌ من صفٍّ أو مؤشرٍ إلى تفصيله
 *   TECHNICAL_ONLY تشخيصٌ فنيٌّ لا عملُ مستخدم
 *   DEPRECATED     متقاعدٌ يُحتفظ بتعريفِه ولا يُولَّد
 *
 * ◆ وهذه الهجرةُ تُنشئ السجلَّ **فارغًا** ولا تملؤه: الملءُ بجردٍ **قراءةً
 *   فقط** (tools/uxui_surface_scan.php) ثم تصنيفٍ — لأن الاشتقاقَ الآليَّ
 *   للصنفِ تخمينٌ، والتخمينُ ممنوعٌ بقاعدةِ «لا رقمَ بلا مصدر».
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `ui_surfaces` (
  `surface_id` VARCHAR(80) NOT NULL COMMENT 'معرِّفُ السطحِ — قد يتعدد للملفِّ الواحد',
  `source_file` VARCHAR(190) NOT NULL COMMENT 'الملفُّ المُنتِج — وقد يشترك سطحان في ملف',
  `extra_files` TEXT NULL COMMENT 'ملفاتٌ أخرى يتركب منها السطحُ (شركاءُ التصيير)',
  `canonical_name` VARCHAR(190) NULL COMMENT 'الاسمُ المعياريُّ من nav_canonical إن وُجد',
  `owner_dept` VARCHAR(120) NULL,
  `surface_type` ENUM('NAVIGABLE','CHILD_RECORD','ACTION_TARGET','MODAL_DRAWER',
                      'DRILLDOWN','TECHNICAL_ONLY','DEPRECATED') NULL
      COMMENT 'NULL = لم يُصنَّف بعد — ولا يُخمَّن',
  `parent_surface` VARCHAR(80) NULL COMMENT 'السطحُ الأمُّ للتبويبِ والتعمُّقِ والدرج',
  `entry_method` VARCHAR(60) NULL COMMENT 'كيف يُبلَغ: sidebar · tab · row_action · modal · post_handler · direct_url',
  `renderable` TINYINT(1) NULL COMMENT '1 = يُصيَّر صفحةً — وهو **مقامُ النسب**',
  `evidence` VARCHAR(255) NULL COMMENT 'شاهدُ التصنيفِ — لا تصنيفَ بلا شاهد',
  `classified_by` VARCHAR(60) NULL,
  `classified_at` DATETIME NULL,
  `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`surface_id`),
  KEY `ix_file` (`source_file`),
  KEY `ix_type` (`surface_type`),
  KEY `ix_parent` (`parent_surface`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سجلُّ أسطحِ العرض — المقامُ أسطحٌ قابلةٌ للتصييرِ لا عددُ ملفات'");
if (!$ok) { exit("✗ {$conn->error}\n"); }

/* سجلُّ جردِ التلوثِ — قراءةٌ فقط في جولتِه الأولى بنصِّ القرار */
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `gov_pollution_findings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_name` VARCHAR(64) NOT NULL,
  `column_name` VARCHAR(64) NOT NULL,
  `marker` VARCHAR(24) NOT NULL COMMENT 'UAT · TEST · SEED · DEMO · SAMPLE · NOTE_IN_CODE',
  `hits` INT UNSIGNED NOT NULL,
  `sample_value` VARCHAR(255) NULL,
  `column_role` VARCHAR(40) NULL COMMENT 'خانةُ رمزٍ/تعدادٍ أم حقلٌ نصيٌّ مشروع',
  `verdict` ENUM('UNCLASSIFIED','LEGIT_PRODUCTION','TEST_POLLUTION','SUSPECT') NOT NULL DEFAULT 'UNCLASSIFIED'
      COMMENT 'التصنيفُ الثلاثيُّ — والافتراضُ «غيرُ مصنَّف» لا «تلوث»',
  `action_taken` ENUM('NONE','QUARANTINED','FIXED') NOT NULL DEFAULT 'NONE',
  `scanned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tcm` (`table_name`, `column_name`, `marker`),
  KEY `ix_verdict` (`verdict`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='جردُ التلوثِ — الجولةُ الأولى قراءةٌ فقط ولا كتابةَ في بيانات'");
if (!$ok) { exit("✗ {$conn->error}\n"); }

echo "✔ ui_surfaces و gov_pollution_findings أُنشئا فارغَين\n";
echo "  الملءُ بجردٍ **قراءةً فقط**: tools/uxui_surface_scan.php · tools/uxui_pollution_scan.php\n";
echo "  ولا تصنيفَ آليٍّ للصنفِ ولا حكمَ تلوثٍ بلا شاهد — «لا رقمَ بلا مصدر».\n";
