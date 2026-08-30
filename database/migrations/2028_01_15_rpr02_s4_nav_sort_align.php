<?php
/**
 * 2028_01_15_rpr02_s4_nav_sort_align.php — موضعُ محاذاةِ ترتيبِ المخزنِ بدورةِ العمل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `RPR-02` §٦ الخطوةُ **س٤**: *«رتِّبِ الأسطحَ على دورةِ
 *   العملِ في الملفِّ **لا على تاريخِ الإضافة**»*. و`nav_items.sort_order`
 *   يحمل بالضبطِ تاريخَ الإضافة، فيتفرَّق عن `nav_canonical.sort_no` **ترتيبًا
 *   داخلَ السلّةِ الواحدة** في **٥١٠** مواضعَ على **١٨١** مسارًا فريدًا
 *   و**١٨٧ سلّةً** من ١٬٥٣٦.
 *
 * ◆ **والشقُّ الأولُ من س٤ سبقه** (`2028_01_12`): المعتمَدُ نفسُه حوذي بالملفِّ
 *   في ١٣١ مسارًا — **فهذا الموضعُ يُلحق المخزنَ بالمعتمَدِ بعدَ استقرارِه**،
 *   ⛔ **ولا يُقفز فوق حلقةٍ فيتفرّق قارئان** ([[counter-parity-two-readers]]).
 *
 * ◆ **والمقياسُ ترتيبٌ لا قيمة**: `sort_order=10` و`sort_no=3` قد ينتجان
 *   الموضعَ نفسَه. ⇒ **فالعلاجُ إعادةُ توزيعِ قيمِ السلّةِ نفسِها على بنودِها
 *   بترتيبِ الدورة** — ⛔ **لا نسخُ `sort_no` قيمةً**: القيمُ تبقى قيمَ السلّةِ
 *   فلا تتحرَّك السلّةُ بالنسبةِ لأخواتِها في أيِّ مُصيِّرٍ يقرأ `sort_order`،
 *   ويتحرَّك ما بداخلها على دورةِ العمل. **وأصغرُ تغييرٍ يحقّق المطلوب.**
 *
 * ◆ **والتعادلُ يُكسر كما يكسره المقياس** — بالمسار: قيمتان متساويتان تُنتجان
 *   ترتيبًا يقرّره كسرُ التعادل، فلو تُركت متساويةً لبقي الموضعُ مخالفًا وهو
 *   «مُحاذًى» في الظاهر. ⇒ **تُجعل القيمُ متزايدةً تمامًا داخلَ السلّة.**
 *
 * ◆ **وهذا الموضعُ نفسُه مصدرُ التراجع**: لكلِّ بندٍ `before_sort`،
 *   و`_down` يردُّه. ⛔ **ولا تراجعَ بذاكرةٍ ولا بإعادةِ تشغيلِ أداة.**
 *
 * التشغيل: php database/migrations/2028_01_15_rpr02_s4_nav_sort_align.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_nav_sort_align` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `nav_item_id`  INT(11) NOT NULL COMMENT 'صف nav_items — ومفتاح الرد',
  `role_id`      INT(11) NOT NULL,
  `group_id`     INT(11) NULL COMMENT 'السلة التي رتب داخلها',
  `route`        VARCHAR(190) NOT NULL DEFAULT '',
  `before_sort`  INT(11) NOT NULL COMMENT 'الترتيب قبل — وهو تاريخ الاضافة',
  `after_sort`   INT(11) NOT NULL COMMENT 'الترتيب بعد — وهو موضع الدورة',
  `canon_sort`   INT(11) NOT NULL DEFAULT 0 COMMENT 'sort_no المعتمد الذي رتب عليه',
  `bucket_size`  INT(11) NOT NULL DEFAULT 0 COMMENT 'عدد بنود السلة — فالترتيب نسبي',
  `witness`      VARCHAR(600) NOT NULL COMMENT 'شاهد المحاذاة — ولا صف بلا شاهد',
  `snapshot_id`  VARCHAR(48) NOT NULL,
  `applied_at`   DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nsa_item` (`nav_item_id`),
  KEY `ix_nsa_role` (`role_id`),
  CONSTRAINT `chk_nsa_witness`  CHECK (`witness` <> ''),
  CONSTRAINT `chk_nsa_snapshot` CHECK (`snapshot_id` <> ''),
  CONSTRAINT `chk_nsa_changed`  CHECK (`before_sort` <> `after_sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 6 س4 · محاذاة ترتيب المخزن بدورة العمل — قبل وبعد وهو مصدر التراجع'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }

$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_nav_sort_align")->fetch_row()[0];
echo "  ✔ `repair01_nav_sort_align` جاهزٌ — صفوفٌ فيه $n\n";
echo "  ✔ ثلاثُ قواعدَ صلبة: شاهدٌ · لقطةٌ · **ولا صفَّ بلا تغييرٍ فعليّ**\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ المحاذاةِ مفتوحٌ — و`nav_items` لم تُمَسّ\n";
