<?php
/**
 * 2028_02_03_navr_identity_cardinality.php — هويّةُ الهدفِ وكاردينالية المساحات
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: table:nav_targets, column:nav_placements.target_id,
 *   constraint:uq_one_primary (nav_ws_roles), drop:uq_role_binding
 *
 * ◆ أمرُ الحوكمةِ الموحَّد:
 *   §١٨ — قيدُ `uq(role_id, binding)` كان يمنع عمليًّا أكثرَ من SECONDARY
 *   واحدةٍ للدور. الصحيح: منعُ التكرارِ بالمفتاحِ الأصليِّ (workspace,role)
 *   + **قاعدةٌ مستقلّة**: لا أكثرَ من PRIMARY واحدةٍ للدور — تُنفَّذ بعمودٍ
 *   محسوبٍ PERSISTENT (‏`role_id` عند PRIMARY وإلا NULL) بفهرسٍ فريدٍ —
 *   وNULL لا يتكرّرِ حسابُه ففتحت SECONDARY بلا سقف.
 *   §١٩ — لكلِّ هدفِ ملاحةٍ `target_id` **ثابتٌ منذ تعريفِه في المصدرِ الحاكم**
 *   (source·sheet·row·canonical·workspace·group·order·visibility) —
 *   ⛔ ولا يبقى اسمُ Excel هو المفتاحَ طويلَ الأجل.
 *
 * التشغيل: php database/migrations/2028_02_03_navr_identity_cardinality.php
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

/* ── §١٨ كارديناليةُ المساحات ─────────────────────────────────────────────── */
$conn->query("ALTER TABLE `nav_ws_roles` DROP INDEX `uq_role_binding`");
echo "① أُسقط `uq_role_binding` (كان يسقّف SECONDARY بواحدة)\n";
$has = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nav_ws_roles' AND COLUMN_NAME='primary_role'")->fetch_row()[0];
if (!(int) $has) {
    $ok = $conn->query("ALTER TABLE `nav_ws_roles`
        ADD COLUMN `primary_role` INT AS (IF(`binding`='PRIMARY', `role_id`, NULL)) PERSISTENT,
        ADD UNIQUE KEY `uq_one_primary` (`primary_role`)");
    if (!$ok) { exit("⛔ قاعدةُ PRIMARY الواحدة فشلت: {$conn->error}\n"); }
}
echo "② قاعدةُ «PRIMARY واحدةٌ للدور» عمودًا محسوبًا بفهرسٍ فريد — وSECONDARY بلا سقف\n";

/* ── §١٩ سجلُّ هويّةِ الأهداف ─────────────────────────────────────────────── */
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `nav_targets` (
    `target_id` VARCHAR(24) NOT NULL COMMENT 'NT-<code>-<nnn> ثابتٌ منذ التعريف',
    `source_doc` VARCHAR(190) NOT NULL,
    `sheet_code` VARCHAR(24) NOT NULL,
    `row_no` SMALLINT UNSIGNED NOT NULL COMMENT 'صفُّ الورقةِ لحظةَ التعريف — نسبٌ لا مفتاحُ مطابقة',
    `canonical_title` VARCHAR(190) NOT NULL,
    `workspace_id` VARCHAR(24) NOT NULL,
    `group_key` VARCHAR(150) NOT NULL,
    `target_order` SMALLINT UNSIGNED NOT NULL,
    `visibility_class` VARCHAR(16) NOT NULL COMMENT 'MENU_ITEM/TAB_CHILD/PROJECTION/DIRECT_ONLY/UTILITY/NOT_BUILT',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`target_id`),
    UNIQUE KEY `uq_ws_order` (`workspace_id`, `sheet_code`, `target_order`),
    CONSTRAINT `fk_nt_ws` FOREIGN KEY (`workspace_id`) REFERENCES `nav_workspaces`(`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='NAVR §١٩: هويّةُ هدفِ الملاحةِ الثابتة — واسمُ Excel نسبٌ لا مفتاح'");
if (!$ok) { exit("⛔ nav_targets فشل: {$conn->error}\n"); }
echo "③ `nav_targets` قائم\n";

$has = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nav_placements' AND COLUMN_NAME='target_id'")->fetch_row()[0];
if (!(int) $has) {
    $ok = $conn->query("ALTER TABLE `nav_placements`
        ADD COLUMN `target_id` VARCHAR(24) NULL AFTER `target_ref`,
        ADD KEY `ix_np_target` (`target_id`),
        ADD CONSTRAINT `fk_np_target` FOREIGN KEY (`target_id`) REFERENCES `nav_targets`(`target_id`)");
    if (!$ok) { exit("⛔ عمودُ target_id فشل: {$conn->error}\n"); }
}
echo "④ `nav_placements.target_id` موصولٌ بسجلِّ الهويّة\n";

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "✔ اكتملت وقُيّدت ذاتيًّا\n";
