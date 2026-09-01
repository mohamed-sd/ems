<?php
/**
 * 2028_03_01_sup_contract_lines_guide_columns.php — حقولُ الورقةِ لحصصِ الموردين (GOV_EXEC §12)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: alter:supplier_contract_lines (SUP-12 حصص الموردين والوحدات التعاقدية)
 *
 * ◆ **الجدولُ الحيُّ هو المالك**: سطحُ `Suppliers/supplier_contract_units.php`
 *   يقرأ `supplier_contract_lines` (ستّةُ صفوفٍ حيّة) — **لا** `sup_quota_supplier_unit`
 *   المولَّدَ الفارغ. فالحقولُ تُضاف حيث تُقرأ، ⛔ **ولا يُحوَّل السطحُ إلى
 *   جدولٍ فارغٍ لأجلِ رقمٍ في مقياس** — ذلك يُخفي بياناتٍ حيّةً لِيُخضِرَّ عمود.
 *
 * ◆ وكلُّ عمودٍ يحمل اسمَ حقلِ ورقتِه في تعليقِه، والمشتقُّ منها يكتبه محرّكُه.
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

$COLS = array(
 'slot_code'            => array("VARCHAR(60) NULL DEFAULT NULL", 'كود الوحدة التعاقدية · Slot_ID'),
 'slot_sequence'        => array("VARCHAR(60) NULL DEFAULT NULL", 'التسلسل الزمني للوحدة التعاقدية ◄'),
 'client_no'            => array("VARCHAR(60) NULL DEFAULT NULL", 'رقم العميل'),
 'business_model'       => array("VARCHAR(80) NULL DEFAULT NULL", 'نموذج العمل'),
 'contract_no'          => array("VARCHAR(60) NULL DEFAULT NULL", 'رقم العقد'),
 'renewal_no'           => array("VARCHAR(60) NULL DEFAULT NULL", 'رقم التجديد (دورة الالتزام)'),
 'container_key'        => array("VARCHAR(80) NULL DEFAULT NULL", 'مفتاح دورة الالتزام · Key'),
 'supplier_no'          => array("VARCHAR(60) NULL DEFAULT NULL", 'رقم المورد'),
 'supplier_name'        => array("VARCHAR(255) NULL DEFAULT NULL", 'اسم المورد (بحث)'),
 'supplier_contract_code'=> array("VARCHAR(80) NULL DEFAULT NULL", 'كود عقد المورد'),
 'line_type'            => array("VARCHAR(80) NULL DEFAULT NULL", 'نوع الآلية/البند'),
 'slot_type'            => array("VARCHAR(80) NULL DEFAULT NULL", 'نوع الوحدة التعاقدية'),
 'continuity_class'     => array("VARCHAR(80) NULL DEFAULT NULL", 'التصنيف (استمرارية)'),
 'slots_for_line'       => array("DECIMAL(12,3) NULL DEFAULT NULL", 'عدد الوحدات التعاقدية للآلية'),
 'inferred_role'        => array("VARCHAR(80) NULL DEFAULT NULL", 'الدور المستنتَج'),
 'slot_monthly_basis'   => array("DECIMAL(18,3) NULL DEFAULT NULL", 'أساس الوحدة التعاقدية الشهري ◄'),
 'supplier_months_in_cycle'=> array("DECIMAL(8,2) NULL DEFAULT NULL", 'أشهر عقد المورد بدورة الالتزام (كما ورد)'),
 'elapsed_months'       => array("DECIMAL(8,2) NULL DEFAULT NULL", 'أشهر منقضية ◄'),
 'cycle_months_total'   => array("DECIMAL(8,2) NULL DEFAULT NULL", 'أشهر دورة الالتزام (إجمالي) ◄'),
 'unit_months'          => array("DECIMAL(14,3) NULL DEFAULT NULL", 'وحدات-شهر ◄'),
 'supplier_share'       => array("DECIMAL(12,4) NULL DEFAULT NULL", 'حصة المورد ◄'),
 'monthly_target'       => array("DECIMAL(18,3) NULL DEFAULT NULL", 'المستهدف الشهري'),
 'primary_units_required'=> array("DECIMAL(12,3) NULL DEFAULT NULL", 'المعدات الأساسية المطلوبة'),
 'primary_available'    => array("DECIMAL(12,3) NULL DEFAULT NULL", 'الأساسية المتاحة ◄'),
 'standby_available'    => array("DECIMAL(12,3) NULL DEFAULT NULL", 'الاحتياطية ◄'),
 'primary_gap'          => array("DECIMAL(12,3) NULL DEFAULT NULL", 'فجوة الأساسية ◄'),
 'primary_active'       => array("DECIMAL(12,3) NULL DEFAULT NULL", 'أساسية نشطة (حي) ◄'),
 'equipment_deficit_flag'=> array("VARCHAR(40) NULL DEFAULT NULL", 'علم عجز معدات ◄'),
 'equipment_coverage_pct'=> array("DECIMAL(8,2) NULL DEFAULT NULL", 'نسبة تغطية المعدات ◄'),
 'standby_reliance'     => array("VARCHAR(80) NULL DEFAULT NULL", 'الاعتماد على الاحتياطي ◄'),
 'executed_qty'         => array("DECIMAL(18,3) NULL DEFAULT NULL", 'المنفَّذ ◄'),
 'achievement_pct'      => array("DECIMAL(8,2) NULL DEFAULT NULL", 'نسبة التحقق ◄'),
 'share_valid_from'     => array("DATE NULL DEFAULT NULL", 'سريان الحصة من'),
 'share_valid_to'       => array("DATE NULL DEFAULT NULL", 'إلى'),
 'supplier_unit_price'  => array("DECIMAL(18,2) NULL DEFAULT NULL", 'سعر وحدة المورد (م08) ◄'),
 'sale_unit_price'      => array("DECIMAL(18,2) NULL DEFAULT NULL", 'سعر بيع الوحدة (قراءة) ◄'),
 'unit_margin_val'      => array("DECIMAL(18,2) NULL DEFAULT NULL", 'هامش الوحدة ◄'),
 'negative_margin_flag' => array("VARCHAR(40) NULL DEFAULT NULL", 'علم هامش سالب ◄'),
 'slot_state'           => array("VARCHAR(80) NULL DEFAULT NULL", 'حالة الوحدة التعاقدية'),
 'evidence_level'       => array("VARCHAR(80) NULL DEFAULT NULL", 'الحجية'),
 'client_total_obligation'=> array("DECIMAL(18,3) NULL DEFAULT NULL", 'إجمالي التزام العميل (مصدر)'),
 'share_of_obligation_pct'=> array("DECIMAL(8,2) NULL DEFAULT NULL", 'نسبة الحصة من الالتزام ◄'),
 'deficit_surplus'      => array("DECIMAL(18,3) NULL DEFAULT NULL", 'العجز / الفائض ◄'),
 'notes'                => array("VARCHAR(500) NULL DEFAULT NULL", 'ملاحظات'),
 'contract_code_read'   => array("VARCHAR(80) NULL DEFAULT NULL", 'كود العقد (قراءة) ◄'),
 'sale_currency_read'   => array("VARCHAR(20) NULL DEFAULT NULL", 'عملة البيع (قراءة) ◄'),
 'margin_currency_fit'  => array("VARCHAR(80) NULL DEFAULT NULL", 'ملاءمة عملة الهامش ◄'),
 'idle_share_flag'      => array("VARCHAR(40) NULL DEFAULT NULL", 'علم حصة جارية بلا نشاط ◄'),
 'last_slot_activity'   => array("VARCHAR(80) NULL DEFAULT NULL", 'آخر نشاط بالوحدة التعاقدية ◄'),
);
$have = array();
$q = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'supplier_contract_lines'");
while ($x = $q->fetch_row()) { $have[$x[0]] = 1; }
$add = array();
foreach ($COLS as $k => $d) {
    if (isset($have[$k])) { continue; }
    $add[] = "ADD COLUMN `{$k}` {$d[0]} COMMENT '" . $conn->real_escape_string($d[1]) . "'";
}
if ($add) {
    if (!$conn->query("ALTER TABLE `supplier_contract_lines` " . implode(', ', $add))) { exit("⛔ {$conn->error}\n"); }
    echo '✔ ' . count($add) . " عمودًا\n";
} else { echo "· قائمةٌ سلفًا\n"; }
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
