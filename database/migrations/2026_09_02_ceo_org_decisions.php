<?php
/**
 * 2026_09_02_ceo_org_decisions.php — سجلُّ القراراتِ الهيكليّة (‏CEO-21)
 * @migration-objects: ceo_org_decisions
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الهدفُ `NT-EX-CEO-021` «قرارات الهيكل التنظيمي» لم يكن مبنيًّا** — وكان
 *   اسمُه موضوعًا على `admin/org_structure.php` وهي **خريطةُ هيكلٍ لا سجلُّ
 *   قرارات**: صفرٌ من ثلاثةَ عشرَ حقلًا. و§10 يقول: «اذا كان Target من
 *   `CURRENT_RELEASE` **فلا يجوز اعلان الادارة مكتملة قبل بنائه**».
 *
 * ◆ **والحبّةُ من سجلِّ الأهدافِ حرفًا**: «قرار هيكلي — تغيير بنية دائمة أو
 *   شبه دائمة — **سجل واحد**». والحقولُ الثلاثةَ عشرَ بأسمائها وترتيبِها من
 *   «09 · 02_تتبع_الحقول» — واسمُ الحقلِ **في تعليقِ عمودِه حرفًا**.
 *
 * ⛔ **ولا يُمَسُّ `org_units` ولا `org_assignments`**: هذا سجلٌّ ثالثٌ لحبّةٍ
 *   ثالثة — القرارُ لا الوحدةُ ولا التكليف.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$sql = "CREATE TABLE IF NOT EXISTS `ceo_org_decisions` (
  `id`               INT NOT NULL AUTO_INCREMENT,
  `company_id`       INT NOT NULL DEFAULT 0 COMMENT 'بوابة المستأجر',
  `decision_no`      VARCHAR(64)  NULL DEFAULT NULL COMMENT 'رقم القرار',
  `decision_date`    DATE         NULL DEFAULT NULL COMMENT 'تاريخ القرار',
  `decision_kind`    VARCHAR(190) NULL DEFAULT NULL COMMENT 'نوع القرار',
  `affected_unit`    VARCHAR(190) NULL DEFAULT NULL COMMENT 'الوحدة المعنية',
  `change_desc`      TEXT         NULL DEFAULT NULL COMMENT 'وصف التغيير',
  `change_reason`    TEXT         NULL DEFAULT NULL COMMENT 'مبرر التغيير',
  `admin_vp_review`  VARCHAR(190) NULL DEFAULT NULL COMMENT 'مراجعة نائب الشؤون الإدارية',
  `roles_perms_impact` TEXT       NULL DEFAULT NULL COMMENT 'أثر على الأدوار والصلاحيات',
  `effective_date`   DATE         NULL DEFAULT NULL COMMENT 'تاريخ النفاذ',
  `decision_doc`     VARCHAR(255) NULL DEFAULT NULL COMMENT 'مرفق القرار',
  `decision_state`   VARCHAR(64)  NULL DEFAULT NULL COMMENT 'حالة القرار',
  `reviewer_name`    VARCHAR(190) NULL DEFAULT NULL COMMENT 'المراجع',
  `approved_on`      DATE         NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
  `created_by`       INT          NULL DEFAULT NULL,
  `created_at`       DATETIME     NULL DEFAULT NULL,
  `approved_by`      INT          NULL DEFAULT NULL,
  `data_state`       VARCHAR(64)  NULL DEFAULT NULL,
  `src_ref`          VARCHAR(190) NULL DEFAULT NULL,
  `updated_at`       DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_co` (`company_id`),
  KEY `ix_date` (`decision_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'CEO-21 — قرارات الهيكل التنظيمي · حبة: قرار هيكلي واحد'";
echo $conn->query($sql) ? "+ جدول ceo_org_decisions\n" : "x ceo_org_decisions: " . $conn->error . "\n";

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
