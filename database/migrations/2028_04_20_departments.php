<?php
/**
 * 2028_04_20_departments.php — سجلُّ الإداراتِ (الأقسام) المنصّيّ
 * @migration-objects: departments
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **طبقةُ المزوّد لا المستأجر**: الإداراتُ تُدار من لوحةِ الإدارةِ العليا
 *   (`admin/departments.php`) وتخدم المستأجرين جميعًا — فلا `company_id` فيها،
 *   وتصنيفُها في `TenantRegistry` هو `T_PLATFORM` (‏كـ`super_admins`): بوّابةُ
 *   المستأجرِ ترفضها، والوصولُ عبر `ems_platform_db()` حصرًا.
 *
 * ⚠ **والتسجيلُ شرطُ الوجود**: جدولٌ خارجَ `TenantRegistry` تُرفَض كلُّ عملياتِه
 *   عبرَ البوّابةِ (fail-closed) — فالهجرةُ وحدَها لا تكفي، ومعها سطرُ السجلّ.
 *
 * ◆ **`code` فريدٌ**: كودُ الإدارةِ مفتاحُ ربطٍ لصفحاتٍ وأكوادٍ حيّة، فتكرارُه
 *   يفتح بابَ الالتباس. و`display_order` ترتيبُ ظهورٍ لا معرِّف.
 *
 * ⛔ **ولا حذفَ من الشاشة**: الإدارةُ مرتبطةٌ بأسطحٍ وأكوادٍ حيّة — الشاشةُ
 *   تُعطّل الحذفَ نصًّا، والجدولُ يبقى قابلًا للحذفِ إداريًّا عبر القاعدةِ وحدَها.
 *
 * والعكسُ في `_down.php`.
 * ═══════════════════════════════════════════════════════════════════════════
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

$conn->query("CREATE TABLE IF NOT EXISTS `departments` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`          VARCHAR(20)  NOT NULL COMMENT 'كود الإدارة — فريدٌ، مفتاحُ ربطِ الأسطحِ والأكواد',
  `name`          VARCHAR(150) NOT NULL COMMENT 'اسم الإدارة',
  `display_order` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ترتيب العرض في القوائم',
  `notes`         TEXT         NULL COMMENT 'ملاحظات',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_departments_code` (`code`),
  KEY `ix_departments_order` (`display_order`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='الإدارات — تُدار من لوحة الإدارة العليا (طبقةُ المزوّد · T_PLATFORM)'");
if ($conn->errno) { exit('⛔ تعذّر إنشاءُ الجدول: ' . $conn->error . "\n"); }

$q = $conn->query('SELECT COUNT(*) c FROM `departments`')->fetch_assoc();
printf("= `departments` قائمٌ — فيه %d إدارةً\n", (int) $q['c']);
echo "◆ التسجيلُ في السجلّ: departments => T_PLATFORM (في app/Core/TenantRegistry.php)\n";

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
