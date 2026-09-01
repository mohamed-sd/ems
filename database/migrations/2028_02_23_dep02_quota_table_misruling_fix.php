<?php
/**
 * 2028_02_23_dep02_quota_table_misruling_fix.php — تصحيحُ حكمٍ خاطئٍ في مواصفةِ `DEP-02`
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: restore sup_quota_supplier_unit · move SUP-11 columns to sup_slot_allocation_quota
 *
 * ◆ **الحكمُ الخاطئُ ومصدرُ كشفِه**: مواصفةُ `DEP-02` أسندت سطحَ «الخانات
 *   المكافئة وتوزيع الحصص» (السطح 11 · 20 حقلًا) إلى `sup_quota_supplier_unit`.
 *   وهجرةُ `2027_12_07_repair01_sup_guide_tables` تقول بنصِّها فوقَ الجدولِ:
 *   *«حصص الموردين والوحدات التعاقدية — حبّة: وحدة تعاقدية واحدة بحصتها
 *   وهامشها»* — أي **السطح 12 (50 حقلًا)**، وتعليقاتُ أعمدتِه تحمل حقولَه
 *   حرفًا. والجدولُ الصحيحُ للسطحِ 11 قائمٌ باسمِه: **`sup_slot_allocation_quota`**
 *   («الخانات المكافئة وتوزيع الحصص — حبّة: مورد × حاوية × نوع آلية»).
 *   ⇒ الحكمُ صُحِّح، **ولم يُبرَّر**.
 *
 * ◆ **وما فعلته الهجرةُ السابقةُ يُردّ حرفًا**: ثلاثةٌ وعشرون عمودًا أُسقطت
 *   من `sup_quota_supplier_unit` **تُعاد بتعريفِها الأصليِّ المنقولِ من ملفِّ
 *   هجرتِها** (النوعُ والقابليةُ والافتراضيُّ والتعليق)، وعشرون عمودًا أُضيفت
 *   للسطحِ 11 **تُسقَط منه** — والجدولُ فارغٌ فلا بيانَ يُفقَد.
 *
 * ⛔ **ولا يُترك الخطأُ ويُسمّى «قرارًا»**: الورقةُ الحاكمةُ وتعليقُ الجدولِ
 *   يقولان الشيءَ نفسَه، والمخالفةُ كانت مني.
 *
 * التشغيل: php database/migrations/2028_02_23_dep02_quota_table_misruling_fix.php
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

$rows = (int) $conn->query("SELECT COUNT(*) FROM `sup_quota_supplier_unit`")->fetch_row()[0];
if ($rows > 0) { exit("⛔ الجدولُ ليس فارغًا ({$rows} صفًّا) — لا يُعاد تشكيلُه بلا حكمِ بيانات\n"); }

/* ① إعادةُ الأعمدةِ الأصليةِ بتعريفِها الحرفيّ */
$BACK = array(
    "`c2` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'التسلسل الزمني للوحدة التعاقدية ◄'",
    "`c4` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'نموذج العمل'",
    "`c12` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'وحدة القياس'",
    "`c14` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'التصنيف (استمرارية)'",
    "`c19` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'أشهر منقضية ◄'",
    "`month_21` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'وحدات-شهر ◄'",
    "`c25` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'الأساسية المتاحة ◄'",
    "`c26` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'الاحتياطية ◄'",
    "`c27` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'فجوة الأساسية ◄'",
    "`c28` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'أساسية نشطة (حي) ◄'",
    "`equipment_30` DECIMAL(9,4) NULL COMMENT 'نسبة تغطية المعدات ◄'",
    "`c32` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'المنفذ ◄'",
    "`c34` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'سريان الحصة من'",
    "`c35` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'إلى'",
    "`supplier_36` DECIMAL(18,2) NULL COMMENT 'سعر وحدة المورد (م08) ◄'",
    "`c37` DECIMAL(18,2) NULL COMMENT 'سعر بيع الوحدة (قراءة) ◄'",
    "`c38` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'هامش الوحدة ◄'",
    "`c39` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'علم هامش سالب ◄'",
    "`c40` VARCHAR(60) NOT NULL DEFAULT '' COMMENT 'حالة الوحدة التعاقدية'",
    "`c47` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'عملة البيع (قراءة) ◄'",
    "`c48` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'ملاءمة عملة الهامش ◄'",
    "`c49` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'علم حصة جارية بلا نشاط ◄'",
    "`c50` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'آخر نشاط بالوحدة التعاقدية ◄'",
);
$have = array();
$q = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sup_quota_supplier_unit'");
while ($x = $q->fetch_row()) { $have[$x[0]] = 1; }
$add = array();
foreach ($BACK as $d) {
    preg_match('~^`([^`]+)`~', $d, $m);
    if (!isset($have[$m[1]])) { $add[] = 'ADD COLUMN ' . $d; }
}
if ($add) {
    if (!$conn->query("ALTER TABLE `sup_quota_supplier_unit` " . implode(', ', $add))) {
        exit("⛔ ردُّ الأعمدة: {$conn->error}\n");
    }
    echo '✔ رُدَّت ' . count($add) . " أعمدةً بتعريفِها الأصليّ\n";
}

/* ② إسقاطُ أعمدةِ السطحِ 11 من جدولِ السطحِ 12 */
$MINE = array('dist_row_code', 'obligation_cycle_key', 'supplier_no_ref', 'supplier_name_ref',
              'line_type_ref', 'monthly_unit_base', 'avg_monthly_executed', 'equivalent_units',
              'granted_units', 'largest_remainder_gap', 'excess_machines', 'supplier_class',
              'shared_slot_member', 'shared_contribution_pct', 'contractual_machines',
              'actual_machines', 'contractual_vs_actual_gap', 'valid_from', 'dist_decision_ref',
              'dist_state');
$drop = array();
foreach ($MINE as $c) { if (isset($have[$c])) { $drop[] = "DROP COLUMN `{$c}`"; } }
if ($drop) {
    if (!$conn->query("ALTER TABLE `sup_quota_supplier_unit` " . implode(', ', $drop))) {
        exit("⛔ إسقاطُ أعمدةِ السطح 11: {$conn->error}\n");
    }
    echo '✔ أُسقطت ' . count($drop) . " أعمدةً كانت لسطحٍ آخر\n";
}

/* ③ وسمُ حكمِ الشاشةِ في سجلِّ الشاشات — الجدولُ الصحيحُ للسطحِ 11 */
$conn->query("UPDATE repair01_screen_registry
    SET grain_entity = 'sup_slot_allocation_quota',
        source_of_truth = 'sup_slot_allocation_quota',
        grain_witness = 'الجدول sup_slot_allocation_quota بحبته (مورد × حاوية × نوع آلية) بنص هجرة 2027_12_07'
  WHERE route = 'suppliers/supplier_quota_distribution.php'");
echo "✔ سجلُّ الشاشاتِ يشير إلى الجدولِ الصحيح\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
