<?php
/**
 * 2028_01_30_final_close_owner_assign.php — سجلُّ إغلاقِ الملكيّةِ المؤجَّلة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ أمرُ `FINAL_CLOSE` §١·٢ البند ③: **47 سطحًا** `owner_code` فارغٌ بقرارِ
 *   `W2-D-01` («لا يُخمَّن مالكٌ — والحسمُ في موجةِ إدارتِه») — والموجاتُ
 *   انقضت والحسمُ لم يقع، و`FINAL_CLOSE` ينسخ التأجيلَ: **يُسند أو يُصنَّف
 *   بشاهدٍ ⇒ صفر**.
 * ◆ **وهذا الجدولُ مصدرُ التراجعِ نفسُه**: لكلِّ صفٍّ حالُه قبلَ الإسناد،
 *   و`_down` يردُّ `owner_code` الفارغَ بقيمِه القبليّةِ ويحذف صفوفَ القدراتِ
 *   المنصّيّةِ المدرَجةَ ثمَّ يُسقط الجدول.
 * ◆ ولا صفَّ بلا شاهدٍ ولا بلا قاعدةِ إسناد.
 *
 * التشغيل: php database/migrations/2028_01_30_final_close_owner_assign.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
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
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_owner_close` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `screen_id`     VARCHAR(12) NOT NULL COMMENT 'السطح في repair01_screen_registry',
  `route`         VARCHAR(200) NOT NULL,
  `before_owner`  VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'المالك قبل الاسناد (فارغ بقرار W2-D-01)',
  `before_rule`   VARCHAR(48) NOT NULL DEFAULT '',
  `after_owner`   VARCHAR(12) NOT NULL COMMENT 'المالك المسند او PLTF للقدرات المنصية',
  `assign_rule`   VARCHAR(48) NOT NULL COMMENT 'قاعدة الاسناد FC3_*',
  `witness`       VARCHAR(600) NOT NULL COMMENT 'الشاهد المقيس — ولا صف بلا شاهد',
  `capability`    VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'رمز القدرة المنصية لصفوف PLTF',
  `snapshot_id`   VARCHAR(48) NOT NULL,
  `applied_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_oc_screen` (`screen_id`),
  CONSTRAINT `chk_oc_witness` CHECK (`witness` <> ''),
  CONSTRAINT `chk_oc_after`   CHECK (`after_owner` <> ''),
  CONSTRAINT `chk_oc_rule`    CHECK (`assign_rule` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FINAL_CLOSE 1-2 · اغلاق ملكية W2-D-01 المؤجلة بشاهد لكل سطح — وهو مصدر التراجع'");
if (!$ok) { exit("✘ {$conn->error}\n"); }
echo "  ✔ `repair01_owner_close` جاهز\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
