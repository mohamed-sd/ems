<?php
/**
 * 2028_01_14_rpr02_s7_canon_screen_id.php — موضعُ ربطِ السجلِّ المعتمَدِ بمعرِّفِ الشاشة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `RPR-02` §٦ الخطوةُ **س٧**: *«اربطِ الكلَّ بسجلِّ
 *   الملاحةِ المعياريِّ — **ولا ترتيبَ يدويٌّ موازٍ**»*. والمقيسُ
 *   بـ`rpr02_s6_sidebar.php`: **٧٤٤** موضعًا على **١٦٤** مسارًا فريدًا.
 *
 * ◆ **وتفكيكُ السبعِمئةٍ وأربعةٍ وأربعين بسببِها لا بجملتِها** — ⛔ فالرقمُ
 *   المجموعُ يخفي أيَّ يدٍ تُصلحه:
 *   | السبب | مواضع | مسارٌ فريد |
 *   |---|---:|---:|
 *   | صفُّه المعتمَدُ **بلا `screen_id`** | **٧٤٣** | **١٦٣** |
 *   | **لا صفَّ معتمَدَ له ألبتّة** | **١** | **١** |
 *   | لا سطحَ في السجلِّ بهذا المسار | صفر | صفر |
 *   | معرِّفان مختلفان | صفر | صفر |
 *
 * ◆ **فالعلاجُ واحدٌ لا سبعةٌ**: يُملأ `nav_canonical.screen_id` من
 *   `repair01_screen_registry.screen_id` **بمطابقةِ المسارِ نفسِه** — جسرٌ
 *   حتميٌّ ⛔ **لا اشتقاقَ باسمٍ ولا تخمين**، و`uq_route` في الطرفين يجعل
 *   المطابقةَ واحدًا لواحد.
 *
 * ⛔ **والواحدُ الباقي لا يُخترع له صفّ**: `Timesheet/view_timesheet.php` سطحٌ
 *   حيٌّ مسجَّلٌ (`SCR-0610`) **بلا صفِّ تسميةٍ ألبتّة** — وإنشاءُ صفٍّ معتمَدٍ
 *   له **فعلُ اعتمادِ تسميةٍ وموضع**، وهو **مرفوعٌ سلفًا للمالكِ** في
 *   `RPR-02` **#١٦** (‏«اسمٌ معروضٌ غيرُ معتمَد» — واحدٌ بلا صفٍّ ألبتّة).
 *   ⇒ **يُوقَف ما يحجبه وحدَه** ويُعرض بعددِه، ⛔ ولا يُعاد رفعُه.
 *
 * ◆ **وهذا الموضعُ نفسُه مصدرُ التراجع**: لكلِّ مسارٍ `before_screen_id`،
 *   و`_down` يردُّه. ⛔ **ولا تراجعَ بذاكرةٍ ولا بإعادةِ تشغيلِ أداة.**
 *
 * التشغيل: php database/migrations/2028_01_14_rpr02_s7_canon_screen_id.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_canon_screen_bind` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `route`            VARCHAR(190) NOT NULL COMMENT 'مسار الصف المعتمد — ومفتاح الرد',
  `before_screen_id` VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'المعرف قبل — والفراغ هو الحال الغالب',
  `after_screen_id`  VARCHAR(12) NOT NULL COMMENT 'المعرف من سجل الاسطح المبني',
  `registry_route`   VARCHAR(200) NOT NULL COMMENT 'مسار السجل الذي جسر — ولا جسر باسم',
  `live_positions`   INT(11) NOT NULL DEFAULT 0 COMMENT 'مواضع حية يفكها هذا الربط',
  `witness`          VARCHAR(600) NOT NULL COMMENT 'شاهد الربط — ولا صف بلا شاهد',
  `snapshot_id`      VARCHAR(48) NOT NULL,
  `applied_at`       DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_csb_route` (`route`),
  CONSTRAINT `chk_csb_witness`  CHECK (`witness` <> ''),
  CONSTRAINT `chk_csb_snapshot` CHECK (`snapshot_id` <> ''),
  CONSTRAINT `chk_csb_after`    CHECK (`after_screen_id` <> ''),
  CONSTRAINT `chk_csb_changed`  CHECK (`before_screen_id` <> `after_screen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 6 س7 · ربط السجل المعتمد بمعرف الشاشة — قبل وبعد وهو مصدر التراجع'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }

$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_canon_screen_bind")->fetch_row()[0];
echo "  ✔ `repair01_canon_screen_bind` جاهزٌ — صفوفٌ فيه $n\n";
echo "  ✔ ثلاثُ قواعدَ صلبة: شاهدٌ · لقطةٌ · **ولا صفَّ بلا تغييرٍ فعليّ**\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ الربطِ مفتوحٌ — و`nav_canonical` لم تُمَسّ\n";
