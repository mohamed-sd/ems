<?php
/**
 * 2028_01_29_sidebar_render_align.php — سجلُّ محاذاةِ السايدبارِ **المُصيَّرة**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ أمرُ `SIDEBAR_RENDER_FIX` §٤·٣: المحاذاةُ السابقةُ كتبت في عمودٍ ميّتٍ
 *   تصييرًا (`nav_canonical.group_name` — التجربة ⑧ حركةُ عدّادٍ صفر)، والحاكمُ
 *   المُثبَتُ بحركةِ العدّادِ للمُعلَنِ هو `gov_target_nav` (⑤ في الجولةِ
 *   الأولى و③) **ومفتاحُه الدورُ** ⇒ فالمحاذاةُ إعلانٌ لكلِّ دورٍ على حدة.
 * ◆ **وهذا الجدولُ مصدرُ التراجعِ نفسُه**: لكلِّ صفٍّ حالُ الإعلانِ قبلَه
 *   (كان قائمًا بقيمِه أو لم يكن)، و`_down` يردُّ القائمَ ويحذف المُدرَجَ
 *   ثمَّ يُسقط الجدول. ⛔ ولا تراجعَ بذاكرةٍ ولا بإعادةِ تشغيلِ أداة.
 * ◆ ولا صفَّ بلا شاهدٍ ولا بلا لقطةٍ **ولا بلا شاهدِ شجرةٍ مُصيَّرةٍ قبليّ**.
 *
 * التشغيل: php database/migrations/2028_01_29_sidebar_render_align.php
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
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_render_align` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `role_id`         INT(11) NOT NULL COMMENT 'الدور — الحاكم بمفتاح الدور',
  `route`           VARCHAR(160) NOT NULL COMMENT 'المسار',
  `requirement_id`  VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'متطلب الملف المطابق بالجسر',
  `had_row`         TINYINT(1) NOT NULL COMMENT '1=كان له اعلان قائم قبل المحاذاة',
  `gt_id`           INT(11) NULL COMMENT 'صف الاعلان في gov_target_nav (القائم او المدرج)',
  `before_group_ar` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'مجموعة الاعلان قبل (للقائم)',
  `before_group_no` INT(11) NOT NULL DEFAULT 0,
  `before_item_no`  INT(11) NOT NULL DEFAULT 0,
  `after_group_ar`  VARCHAR(120) NOT NULL COMMENT 'مجموعة الملف التصميمي',
  `after_group_no`  INT(11) NOT NULL,
  `after_item_no`   INT(11) NOT NULL,
  `rendered_before` VARCHAR(220) NOT NULL COMMENT 'الراس والفرعي المصيران قبل — شاهد الشجرة لا الجدول',
  `witness`         VARCHAR(600) NOT NULL COMMENT 'شاهد المحاذاة — ولا صف بلا شاهد',
  `snapshot_id`     VARCHAR(48) NOT NULL COMMENT 'اللقطة',
  `applied_at`      DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ra_role_route` (`role_id`, `route`),
  CONSTRAINT `chk_ra_witness`  CHECK (`witness` <> ''),
  CONSTRAINT `chk_ra_snapshot` CHECK (`snapshot_id` <> ''),
  CONSTRAINT `chk_ra_rendered` CHECK (`rendered_before` <> ''),
  CONSTRAINT `chk_ra_after`    CHECK (`after_group_ar` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SIDEBAR_RENDER_FIX 4-3 · محاذاة المصير في الحاكم المثبت بحركة العداد — وهو مصدر التراجع'");
if (!$ok) { exit("✘ {$conn->error}\n"); }
echo "  ✔ `repair01_render_align` جاهز\n";
