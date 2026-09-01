<?php
/**
 * 2028_03_05_govui_target_registry_and_landing.php — سجلُّ §4 وصنفُ §8 الجديد
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: create:govui_target_registry + alter:nav_placements(placement_type enum)
 *
 * ◆ **①  `Target Registry` بتسعةَ عشرَ عمودًا (§4)**: الأعمدةُ التي كانت
 *   مبعثرةً بين `nav_targets` و`nav_placements` و`nav_workspaces` وبطاقةِ
 *   الدليلِ تجتمع في سجلٍّ واحدٍ **بمصدرِ كلِّ خانةٍ مكتوبًا**
 *   (`Source_File` · `Source_Sheet` · `Source_Row`)، ويُضاف `Required_Roles`
 *   و`Canonical_Group_Label` و`Group_Order` و`State_Model_Ref` — وهي الناقصةُ
 *   بنصِّ البرومت. **والمعرِّفُ مفتاحُه** فلا يُعاد ترقيمُه.
 *
 * ◆ **②  `LANDING_PAGE` صنفٌ جديدٌ في الأمر (§8)**: `nav_placements` كانت
 *   تعرف ستّةَ أصنافٍ، والأمرُ يذكر **سبعة**. والسابعُ ليس تجميلًا: مجموعةُ
 *   **«اللوحة — خارج الدورة»** في تسعَ عشرةَ ورقةً تقول بنصِّها إنَّ هذه
 *   الشاشةَ **خارج الدورة** — فهي صفحةُ هبوطِ المساحةِ لا مرحلةٌ في دورتِها،
 *   وخلطُها بـ`MENU_ITEM` يجعل «الترتيبَ المستنديَّ» يعُدُّ اللوحةَ مرحلةً أولى.
 *
 * ◆ ⛔ **ولا يُغيَّر صنفُ صفٍّ هنا**: التوسعةُ للمفرداتِ فقط، والتصنيفُ يقع
 *   في هجرةٍ تالية بشاهدِ ورقتِه.
 *
 * التشغيل: php database/migrations/2028_03_05_govui_target_registry_and_landing.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `govui_target_registry` (
  `target_id` varchar(24) NOT NULL COMMENT 'ثابتٌ قبلَ البناءِ وبعدَه (§4)',
  `workspace_id` varchar(24) NOT NULL,
  `workspace_type` varchar(24) NOT NULL COMMENT 'DEPARTMENT · EXECUTIVE · PERSONAL · PLATFORM_UTILITY',
  `department_id` varchar(24) NOT NULL DEFAULT '' COMMENT 'فارغٌ للتنفيذيِّ والشخصيِّ بحكمِ §1',
  `group_id` int(11) DEFAULT NULL,
  `canonical_group_label` varchar(190) NOT NULL DEFAULT '',
  `group_order` smallint(6) DEFAULT NULL,
  `target_screen_id` varchar(12) NOT NULL DEFAULT '' COMMENT 'SCR-#### متى بُني',
  `canonical_screen_label` varchar(190) NOT NULL,
  `screen_order` smallint(6) NOT NULL,
  `surface_type` varchar(16) NOT NULL COMMENT 'أصنافُ §8 السبعة',
  `surface_rule` varchar(190) NOT NULL DEFAULT '' COMMENT 'القاعدةُ التي حسمت الصنف — ولا صنفَ بلا قاعدة',
  `grain` varchar(400) NOT NULL DEFAULT '',
  `owner` varchar(190) NOT NULL DEFAULT '',
  `source_of_truth` varchar(1000) NOT NULL DEFAULT '',
  `required_roles` varchar(255) NOT NULL DEFAULT '',
  `state_model_ref` varchar(255) NOT NULL DEFAULT '',
  `source_file` varchar(120) NOT NULL,
  `source_sheet` varchar(120) NOT NULL,
  `source_row` smallint(6) NOT NULL,
  `built_route` varchar(190) NOT NULL DEFAULT '',
  `built_at_snapshot` varchar(48) NOT NULL DEFAULT '',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`target_id`),
  KEY `ix_gtr_ws` (`workspace_id`,`screen_order`),
  KEY `ix_gtr_surface` (`surface_type`),
  CONSTRAINT `chk_gtr_rule` CHECK (`surface_type` = '' or `surface_rule` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GOV_UI_EXEC §4 — سجلُّ الأهدافِ بتسعةَ عشرَ عمودًا من الملفَّين الحاكمَين'");
if (!$ok) { exit("⛔ CREATE: {$conn->error}\n"); }
echo "  ✔ govui_target_registry\n";

$ok = $conn->query("ALTER TABLE `nav_placements`
  MODIFY `placement_type` enum('MENU_ITEM','TAB_CHILD','DIRECT_ONLY','PROJECTION','UTILITY','LANDING_PAGE','NOT_BUILT')
  NOT NULL COMMENT 'أصنافُ §8 السبعة — وLANDING_PAGE أُضيف بأمرِ GOV_UI_EXEC'");
if (!$ok) { exit("⛔ ALTER: {$conn->error}\n"); }
echo "  ✔ nav_placements.placement_type ⇐ سبعةُ أصناف\n";

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
