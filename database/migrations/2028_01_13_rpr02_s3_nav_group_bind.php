<?php
/**
 * 2028_01_13_rpr02_s3_nav_group_bind.php — موضعُ ربطِ بندِ الملاحةِ بمجموعتِه المعتمدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `RPR-02` §٦ الخطوةُ **س٣**: مجموعةُ البندِ الحيِّ في
 *   المخزنِ (`nav_items.group_id ⟶ link_groups.name`) **تخالف** المجموعةَ
 *   المعتمدةَ في سجلِّ الملاحةِ المعياريِّ (`nav_canonical.group_name`) في
 *   **١٬٢٢٣** موضعًا على **٢٧٩** مسارًا فريدًا وأربعةٍ وثلاثين دورًا —
 *   **و٤٣٤ زوجًا متميّزًا** (‏«الميزانية والإنجاز ⇒ عمليات التمويل» ٤٦ موضعًا …).
 *
 * ◆ **ولماذا هو عطبٌ وإن لم يره مستخدمٌ اليوم**: المُصيِّرُ الحيُّ
 *   (`printEmsTenGroupNav`) يأخذ القسمَ من `nav_canonical`/`gov_target_nav`
 *   **ولا يقرأ `link_groups`** — فالمخزنُ **مصدرٌ ثانٍ ساكنٌ يتفرَّق عن الأول**.
 *   ومتى سقطت بذرةُ التبويبِ العشريِّ أو أُطفئت (`EMS_NAV_TEN=off`) عاد
 *   المُصيِّرُ إلى `link_groups` **فرأى المستخدمُ المجموعةَ المخالفةَ فجأةً**.
 *   ⇒ **قارئان في ملفَّين يتفرّقان — يُزال أحدُهما أو يُطابَق** ([[counter-parity-two-readers]]).
 *
 * ◆ **واتجاهُ الكتابةِ إلى المخزنِ لا إلى السجل**: `nav_canonical` هو المعتمَدُ
 *   الذي يقرؤه المُصيِّر، **فتغييرُه لأجلِ إرثٍ لا يُصيَّر قلبٌ للسلطة**.
 *   ⇒ يُعاد توجيهُ `nav_items.group_id` إلى مجموعةِ الدورِ التي اسمُها اسمُ
 *   المعتمَد — وتُنشأ المجموعةُ متى لم تكن، **بترتيبٍ مشتقٍّ من `sort_no`
 *   لا مخترَع**.
 *
 * ◆ **وهذا الموضعُ نفسُه مصدرُ التراجع**: لكلِّ بندٍ `before_group_id` واسمُه،
 *   و`_down` يردُّه ثمَّ يُسقط ما أُنشئ من مجموعاتٍ **إن بقي بلا ساكن**.
 *   ⛔ **ولا تراجعَ بذاكرةٍ ولا بإعادةِ تشغيلِ أداة.**
 *
 * ◆ **وثلاثُ قواعدَ صلبة**: شاهدٌ · لقطةٌ · **ولا صفَّ بلا تغييرٍ فعليّ**
 *   (‏`before_group_id` يساوي `after_group_id` ⇒ ضجيجٌ في سجلِّ تغييرٍ لا ربط).
 *
 * التشغيل: php database/migrations/2028_01_13_rpr02_s3_nav_group_bind.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_nav_group_bind` (
  `id`                INT(11) NOT NULL AUTO_INCREMENT,
  `nav_item_id`       INT(11) NOT NULL COMMENT 'صف nav_items — ومفتاح الرد',
  `role_id`           INT(11) NOT NULL,
  `route`             VARCHAR(190) NOT NULL DEFAULT '',
  `canon_route`       VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'المسار الذي جسر الى السجل المعتمد',
  `before_group_id`   INT(11) NULL COMMENT 'المجموعة قبل — NULL يعني بلا مجموعة',
  `before_group_name` VARCHAR(190) NOT NULL DEFAULT '',
  `after_group_id`    INT(11) NOT NULL,
  `after_group_name`  VARCHAR(190) NOT NULL,
  `group_created`     TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = المجموعة انشئت لهذا الربط',
  `witness`           VARCHAR(600) NOT NULL COMMENT 'شاهد الربط — ولا صف بلا شاهد',
  `snapshot_id`       VARCHAR(48) NOT NULL COMMENT 'اللقطة التي قيس عليها',
  `applied_at`        DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ngb_item` (`nav_item_id`),
  KEY `ix_ngb_role` (`role_id`),
  KEY `ix_ngb_after` (`after_group_id`),
  CONSTRAINT `chk_ngb_witness`  CHECK (`witness` <> ''),
  CONSTRAINT `chk_ngb_snapshot` CHECK (`snapshot_id` <> ''),
  CONSTRAINT `chk_ngb_changed`  CHECK (`before_group_id` IS NULL OR `before_group_id` <> `after_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 6 س3 · ربط بند الملاحة بمجموعته المعتمدة — قبل وبعد لكل بند وهو مصدر التراجع'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }

$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_nav_group_bind")->fetch_row()[0];
echo "  ✔ `repair01_nav_group_bind` جاهزٌ — صفوفٌ فيه $n\n";
echo "  ✔ ثلاثُ قواعدَ صلبة: شاهدٌ · لقطةٌ · **ولا صفَّ بلا تغييرٍ فعليّ**\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ الربطِ مفتوحٌ — و`nav_items` لم تُمَسّ\n";
