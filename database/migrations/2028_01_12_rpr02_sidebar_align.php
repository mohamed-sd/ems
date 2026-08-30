<?php
/**
 * 2028_01_12_rpr02_sidebar_align.php — موضعُ محاذاةِ السايدبارِ بالملفِّ التصميميّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `RPR-02` **#٨**: المُصيَّرُ مقابلَ الملفِّ التصميميِّ
 *   **مطابقٌ ٥٢٢ من ٧٦٧** ⇒ **٦٨٫١٪**، والمخالفُ **٢٤٥** موضعًا على **٤٥**
 *   مسارًا فريدًا: مجموعةٌ مُصيَّرةٌ تخالف مجموعةَ الملفّ، وترتيبٌ لا يتبع تسلسلَه.
 *
 * ◆ **وهذا البندُ وحدَه يغيّر ما يراه المستخدمون يوميًّا** — فالتغييرُ يُسجَّل
 *   **قبلَه وبعدَه لكلِّ مسارٍ بقاعدتِه وشاهدِه** في هذا الموضع، **وهو نفسُه
 *   مصدرُ التراجع**: `_down` يردُّ كلَّ قيمةٍ إلى ما كانت عليه من هذا الجدول
 *   ثمَّ يُسقطه. ⛔ **ولا تراجعَ بذاكرةٍ ولا بإعادةِ تشغيلِ أداة.**
 *
 * ◆ **وقاعدتان صلبتان**: لا صفَّ بلا شاهدٍ ولا بلا لقطة · **ولا صفَّ بلا تغيير**
 *   (‏قبلٌ يساوي بعدًا في الحقلَين معًا ⇒ ليس محاذاةً بل ضجيجٌ في سجلِّ تغيير).
 *
 * التشغيل: php database/migrations/2028_01_12_rpr02_sidebar_align.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_sidebar_align` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `route`         VARCHAR(190) NOT NULL COMMENT 'المسار — ومفتاح الرد',
  `requirement_id` VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'متطلب الملف المطابق',
  `unit_name`     VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'ادارة الملف',
  `before_group`  VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'المجموعة المصيرة قبل',
  `after_group`   VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'مجموعة الملف بعد',
  `before_order`  INT(11) NOT NULL DEFAULT 0 COMMENT 'الترتيب قبل',
  `after_order`   INT(11) NOT NULL DEFAULT 0 COMMENT 'تسلسل الملف بعد',
  `change_kind`   ENUM('GROUP','ORDER','GROUP_AND_ORDER') NOT NULL,
  `witness`       VARCHAR(600) NOT NULL COMMENT 'شاهد المحاذاة — ولا صف بلا شاهد',
  `snapshot_id`   VARCHAR(48) NOT NULL COMMENT 'اللقطة التي قيس عليها',
  `applied_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_route` (`route`),
  KEY `ix_sa_kind` (`change_kind`),
  CONSTRAINT `chk_sa_witness`  CHECK (`witness` <> ''),
  CONSTRAINT `chk_sa_snapshot` CHECK (`snapshot_id` <> ''),
  CONSTRAINT `chk_sa_changed`  CHECK (`before_group` <> `after_group` OR `before_order` <> `after_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 8 · محاذاة السايدبار بالملف التصميمي — قبل وبعد لكل مسار وهو مصدر التراجع'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }

$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_sidebar_align")->fetch_row()[0];
echo "  ✔ `repair01_sidebar_align` جاهزٌ — صفوفٌ فيه $n\n";
echo "  ✔ ثلاثُ قواعدَ صلبة: شاهدٌ · لقطةٌ · **ولا صفَّ بلا تغييرٍ فعليّ**\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ المحاذاةِ مفتوحٌ — و`nav_canonical` لم تُمَسّ\n";
