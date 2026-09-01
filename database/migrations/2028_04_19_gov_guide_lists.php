<?php
/**
 * 2028_04_19_gov_guide_lists.php — سجلُّ «القوائمِ المحكومة» من الدليلِ المعماريّ
 * @migration-objects: gov_guide_lists
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **حكمُ المالك**: «كلُّ ما تحتاجه موجودٌ في الدليلِ المعماريّ». ونموذجُ
 *   الإضافةِ المشتقُّ (`includes/w14_guide_form.php`) يشتقُّ حقولَه من
 *   `repair01_fields`، لكنَّ حقلَ `REFERENCE` حكمُه **«قائمة محكومة من بيتها»**
 *   ⇒ **يلزمه مفرداتُه**، وإلّا خرج خانةَ نصٍّ حرّةً — **وهي نقضُ حكمِه**.
 *
 * ◆ **والمقيسُ قبل البناء**: 372 بطاقةَ شاشةٍ في الدليل، **211 منها تحمل صفَّ
 *   «القوائم المحكومة (قيمها القانونية من مصدرها)»** بصيغةٍ واحدة:
 *     `<اسم الحقل> ▼: قيمة · قيمة` والفاصلُ بين الحقولِ `¦`.
 *   ⇒ ومن أصلِ 75 حقلَ قائمةٍ في الأسطحِ التسعةِ والعشرين **71 مغطًّى بقيمِه**.
 *
 * ⛔ **ولا مفردةَ تُخترَع**: القيمُ تُنقَل حرفًا. والعمودُ إن كان `ENUM` فمفرداتُه
 *   من المخطَّطِ تغلب (‏فالمخطَّطُ هو ما يقبل الكتابة)، وإلّا فقيمُ الدليل —
 *   **وكتابةُ مفردةٍ لا يعرفها العمودُ تُبتلَع فراغًا صامتًا**
 *   [[enum-silent-empty-write]].
 *
 * ◆ **والقارئُ واحدٌ**: `tools/lib/guide_lists.php` — تستعمله هذه الهجرةُ
 *   وأداةُ إعادةِ الاستيعابِ معًا، ⛔ فلا قارئان يتفرّقان.
 *
 * والعكسُ في `_down.php`.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/tools/lib/guide_lists.php';
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

$conn->query("CREATE TABLE IF NOT EXISTS `gov_guide_lists` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `surface_key` VARCHAR(190) NOT NULL COMMENT 'اسمُ السطحِ مُسوًّى — مفتاحُ المطابقة',
  `surface_ar`  VARCHAR(190) NOT NULL COMMENT 'اسمُ السطحِ كما في الورقة',
  `field_key`   VARCHAR(190) NOT NULL COMMENT 'اسمُ الحقلِ مُسوًّى',
  `field_ar`    VARCHAR(190) NOT NULL COMMENT 'اسمُ الحقلِ كما في الورقة',
  `value_ar`    VARCHAR(190) NOT NULL COMMENT 'المفردةُ القانونيةُ حرفًا — ⛔ لا تُترجَم',
  `sort_no`     SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ترتيبُها في الورقة',
  `src_ref`     VARCHAR(255) NOT NULL COMMENT 'الملفُّ والورقةُ والبندُ والصف — ⛔ ولا قيمةَ بلا مرجعِ خليّة',
  `active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_guide_list_value` (`surface_key`,`field_key`,`value_ar`),
  KEY `ix_guide_list_field` (`surface_key`,`field_key`,`active`,`sort_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='قيمُ القوائمِ المحكومةِ من الدليلِ المعماريّ — SILENT_DROP_FIX §2·2-④'");
if ($conn->errno) { exit('⛔ تعذّر إنشاءُ الجدول: ' . $conn->error . "\n"); }

$rows = ems_guide_lists_read(array(
    $ROOT . '/docs/REPAIR01_20260823/01 · الدليل المعماري.xlsx',
    $ROOT . '/docs/REPAIR01_20260823/02 · القيادة.xlsx',
));
echo '◆ قُرئ من الدليل: ' . count($rows) . " قيمةً\n";
if (!$rows) { exit("⛔ صفرُ قيمةٍ — ⛔ ولا يُقيَّد نجاحٌ على قراءةٍ فارغة\n"); }

$st = $conn->prepare("INSERT INTO `gov_guide_lists`
    (surface_key, surface_ar, field_key, field_ar, value_ar, sort_no, src_ref, active, created_at)
    VALUES (?,?,?,?,?,?,?,1,NOW())
    ON DUPLICATE KEY UPDATE sort_no = VALUES(sort_no), src_ref = VALUES(src_ref), active = 1");
$n = 0; $err = array();
foreach ($rows as $x) {
    $st->bind_param('sssssis', $x['surface_key'], $x['surface_raw'], $x['field_key'],
                    $x['field_raw'], $x['value_ar'], $x['sort_no'], $x['src_ref']);
    if ($st->execute()) { $n++; } else { $err[$st->errno] = $st->error; }
}
$st->close();

$q = $conn->query('SELECT COUNT(*) c, COUNT(DISTINCT surface_key) s, COUNT(DISTINCT CONCAT(surface_key,"|",field_key)) f
                     FROM gov_guide_lists WHERE active = 1')->fetch_assoc();
printf("= كُتب %d · والسجلُّ الآن: %d قيمةً في %d حقلًا على %d سطحًا\n", $n, $q['c'], $q['f'], $q['s']);
foreach ($err as $e) { echo "x {$e}\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
