<?php
/**
 * 2027_12_30_amd01_impact_bridge.php — جسرُ مفرداتِ الأثرِ إلى المعرِّفات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `AMD-01` المرحلة ٤ تشترط إسقاطَ كلِّ قرارٍ حاكمٍ على
 *   **كلِّ ما يتأثّر به** (§٨)، والمقيسُ اليوم: **صفرٌ من ١٢٥ قرارًا** أُسقط
 *   على المحاورِ الأربعةَ عشرَ كلِّها، و**٩٥٥ خليّةً محجوبة**. والسببُ عمودٌ
 *   واحد: `repair01_decisions.affected_screens` **مفرداتٌ حرّةٌ لا معرِّفات** —
 *   «م03-2» و«كل الأسطح» و«شهادة العودة» — فلا يُعرف أيُّ سطحٍ يتأثّر.
 *
 * ◆ **وهذا الجدولُ موضعُ الجسرِ لا الجسرَ نفسَه**: صفٌّ لكلِّ **مفردةٍ** في
 *   كلِّ قرار، بحكمِ حلِّها وشاهدِه. ⛔ **ولا تُدهَس المفرداتُ الحرّةُ** —
 *   العمودُ الأصليُّ يبقى كما ورد في المصدرِ الحاكم، **والحلُّ يُكتب بجانبِه**:
 *   فالمصدرُ حقيقةٌ والحلُّ اجتهادٌ بشاهد، وخلطُهما يُفقد أيَّهما يُراجَع.
 *
 * ◆ **وقاعدتان صلبتان تُسنّان الآن** — لأنَّ الجدولَ يولد فارغًا فيصدق شرطُهما:
 *   · حلٌّ بلا شاهدٍ مرفوض.
 *   · وحلٌّ إلى سطحٍ بلا `screen_id` مرفوض، وحلٌّ إلى نطاقٍ بلا `unit` مرفوض
 *     — فالصنفُ يُلزِم حمولتَه ولا يُترك وسمًا فارغًا.
 *
 * التشغيل: php database/migrations/2027_12_30_amd01_impact_bridge.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_decision_screen_bridge` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `decision_id`   VARCHAR(32)  NOT NULL COMMENT 'القرار الحاكم',
  `token_raw`     VARCHAR(190) NOT NULL COMMENT 'المفردة كما وردت في المصدر الحاكم — لا تُصحح',
  `resolution`    ENUM('SCREEN','UNIT','SCOPE_CLASS','UNRESOLVED') NOT NULL
                  COMMENT 'صنف الحل: سطح بعينه · نطاق · طبقة معلنة · متعذر',
  `unit_code`     VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'رمز النطاق حين يُحل إلى نطاق',
  `screen_id`     VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'معرف السطح حين يُحل إلى سطح',
  `scope_class`   VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'الطبقة المعلنة حين يكون المدى صنفا لا فردا',
  `bridge_rule`   VARCHAR(48)  NOT NULL COMMENT 'القاعدة التي حلت المفردة — T1..T4',
  `bridge_witness` VARCHAR(400) NOT NULL COMMENT 'شاهد الحل — ولا حل بلا شاهد',
  `snapshot_id`   VARCHAR(48)  NOT NULL DEFAULT '' COMMENT 'اللقطة التي قيس عليها',
  `resolved_at`   DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dec_token` (`decision_id`, `token_raw`),
  KEY `ix_res` (`resolution`),
  KEY `ix_scr` (`screen_id`),
  KEY `ix_unit` (`unit_code`),
  CONSTRAINT `chk_bridge_witness` CHECK (`bridge_witness` <> ''),
  CONSTRAINT `chk_bridge_payload` CHECK (
      (`resolution` = 'SCREEN'      AND `screen_id`   <> '') OR
      (`resolution` = 'UNIT'        AND `unit_code`   <> '') OR
      (`resolution` = 'SCOPE_CLASS' AND `scope_class` <> '') OR
      (`resolution` = 'UNRESOLVED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='AMD-01 م4 · جسر مفردات الأثر الحرة إلى المعرفات — بشاهد لكل حل'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }

$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_decision_screen_bridge")->fetch_row()[0];
echo "  ✔ `repair01_decision_screen_bridge` جاهزٌ — صفوفٌ فيه $n\n";
echo "  ✔ قاعدتان صلبتان: شاهدٌ لازمٌ · وصنفٌ يُلزِم حمولتَه\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ الجسرِ مفتوحٌ — و`affected_screens` الحرُّ لم يُمَسّ\n";
