<?php
/**
 * 2028_01_16_rpr02_s2_nav_label_align.php — موضعُ محاذاةِ الاسمِ المخزَّنِ بالمعتمَد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `RPR-02` §٦ الخطوةُ **س٢**: *«صحِّحِ الأسماءَ المعروضةَ
 *   على التسميةِ المعتمدة»*. والمقيسُ **١٥٤** موضعًا على **٢٥** مسارًا —
 *   **ويُفكَّك بسببِه**: `nav_items.label_ar` يخالف `nav_canonical.canonical_ar`
 *   المعتمَدَ في **١٥١** موضعًا على **٢٣** مسارًا · و**٢** تسميتُهما
 *   `PENDING_OWNER` · و**١** بلا صفِّ تسميةٍ ألبتّة.
 *
 * ◆ **والثلاثةُ الباقيةُ مرفوعةٌ سلفًا** — وهي بعينِها مقياسُ `RPR-02` **#١٦**
 *   («اسمٌ معروضٌ غيرُ معتمَد» = ٣ على مسارَين): `Reports/daily_units_report.php`
 *   (‏`PENDING_OWNER` — **فعلُ اعتمادٍ**) و`Timesheet/view_timesheet.php`
 *   (‏بلا صفٍّ — **فعلُ تسمية**). ⇒ ⛔ **لا يُعاد رفعُها** (‏أمرُ الإنهاءِ §٤).
 *
 * ◆ **والمُصيِّرُ يأخذ اسمَ المعتمَدِ سلفًا** (`unified_nav.php`: متى كان الصفُّ
 *   `APPROVED` فالاسمُ `canonical_ar`) — **فالمخزنُ قارئٌ ثانٍ ساكنٌ يتفرَّق**،
 *   وهذا يُطابقه به. ⇒ **والمتوقَّعُ صفرُ فرقٍ في العضويّةِ المُصيَّرة**،
 *   ويُقاس بـ`rpr02_nav_render_census.php` ⛔ لا يُظَنّ.
 *
 * ⚠ **وحدُّ العمودِ يُحترم**: `nav_items.label_ar` **varchar(64)** و
 *   `canonical_ar` **varchar(190)** — فما جاوز الأربعةَ والستِّين **يُوقَف
 *   ويُسمّى**، ⛔ ولا يُبتَر اسمٌ معتمَدٌ ليدخل عمودًا.
 *
 * ◆ **وهذا الموضعُ نفسُه مصدرُ التراجع** · ⛔ **ولا تراجعَ بذاكرةٍ ولا بأداة.**
 *
 * التشغيل: php database/migrations/2028_01_16_rpr02_s2_nav_label_align.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_nav_label_align` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `nav_item_id`  INT(11) NOT NULL COMMENT 'صف nav_items — ومفتاح الرد',
  `role_id`      INT(11) NOT NULL,
  `route`        VARCHAR(190) NOT NULL DEFAULT '',
  `before_label` VARCHAR(190) NOT NULL COMMENT 'الاسم المخزن قبل',
  `after_label`  VARCHAR(190) NOT NULL COMMENT 'الاسم المعتمد بعد',
  `canon_status` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'حالة الصف المعتمد — والمحاذاة لا تقع الا على APPROVED',
  `witness`      VARCHAR(600) NOT NULL COMMENT 'شاهد المحاذاة — ولا صف بلا شاهد',
  `snapshot_id`  VARCHAR(48) NOT NULL,
  `applied_at`   DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nla_item` (`nav_item_id`),
  KEY `ix_nla_role` (`role_id`),
  CONSTRAINT `chk_nla_witness`  CHECK (`witness` <> ''),
  CONSTRAINT `chk_nla_snapshot` CHECK (`snapshot_id` <> ''),
  CONSTRAINT `chk_nla_appr`     CHECK (`canon_status` = 'APPROVED'),
  CONSTRAINT `chk_nla_changed`  CHECK (`before_label` <> `after_label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 6 س2 · محاذاة الاسم المخزن بالمعتمد — قبل وبعد وهو مصدر التراجع'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }

$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_nav_label_align")->fetch_row()[0];
echo "  ✔ `repair01_nav_label_align` جاهزٌ — صفوفٌ فيه $n\n";
echo "  ✔ أربعُ قواعدَ صلبة: شاهدٌ · لقطةٌ · **معتمَدٌ لا معلَّق** · **ولا صفَّ بلا تغيير**\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ المحاذاةِ مفتوحٌ — و`nav_items` لم تُمَسّ\n";
