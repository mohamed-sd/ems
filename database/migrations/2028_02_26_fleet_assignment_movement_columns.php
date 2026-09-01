<?php
/**
 * 2028_02_26_fleet_assignment_movement_columns.php — حقولُ الورقةِ للتخصيصِ والحركة (GOV_EXEC §12)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: alter:asset_assignment (FLEET-12) · alter:fleet_equipment_history (FLEET-13)
 *
 * ◆ سطحٌ واحدٌ (`Fleet/asset_assignments.php`) يخدم هدفَين — «التخصيص على
 *   الوحدات» (`FLEET-12`) و«حركة الموقع والمشروع» (`FLEET-13`) — بجدولَين:
 *   جدولُ الإسنادِ وسجلُّ الوقائع. وكلُّ عمودٍ يحمل اسمَ حقلِ الورقةِ في تعليقِه.
 *
 * ⛔ **ولا يُدمَج الهدفان في جدولٍ واحد**: حبّةُ الإسنادِ مدّةٌ مفتوحةٌ وحبّةُ
 *   الحركةِ واقعةٌ لحظيّة — والدمجُ يخلط مدّةً بواقعة.
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

$PLAN = array(
  'asset_assignment' => array(
    'assign_code'        => array("VARCHAR(60) NULL DEFAULT NULL", 'رقم التخصيص'),
    'unit_key'           => array("VARCHAR(80) NULL DEFAULT NULL", 'مفتاح الوحدة'),
    'client_contract_ref'=> array("VARCHAR(80) NULL DEFAULT NULL", 'كود عقد العميل ◄'),
    'client_no'          => array("VARCHAR(60) NULL DEFAULT NULL", 'رقم العميل ◄'),
    'project_no'         => array("VARCHAR(60) NULL DEFAULT NULL", 'رقم المشروع ◄'),
    'business_model'     => array("VARCHAR(80) NULL DEFAULT NULL", 'نموذج العمل ◄'),
    'machine_no_contract'=> array("VARCHAR(60) NULL DEFAULT NULL", 'رقم الآلية في عقد العميل'),
    'project_asset_code' => array("VARCHAR(60) NULL DEFAULT NULL", 'كود المعدة بالمشروع ◄'),
    'activation_ref'     => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع التفعيل'),
    'substitute_asset'   => array("VARCHAR(60) NULL DEFAULT NULL", 'الأصل البديل'),
    'replace_sla_days'   => array("INT NULL DEFAULT NULL", 'مهلة الإحلال (يوم)'),
    'gap_days'           => array("INT NULL DEFAULT NULL", 'أيام التوقف بين الأصلين ◄'),
    'executed_hours'     => array("DECIMAL(14,2) NULL DEFAULT NULL", 'الساعات المنفَّذة ◄'),
    'reviewer'           => array("VARCHAR(160) NULL DEFAULT NULL", 'المراجع'),
    'approved_by'        => array("INT NULL DEFAULT NULL", 'المعتمِد'),
    'approved_at'        => array("DATETIME NULL DEFAULT NULL", 'تاريخ الاعتماد'),
    'record_basis'       => array("VARCHAR(80) NULL DEFAULT NULL", 'أساس السجل ▼'),
    'src_ref'            => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع المصدر'),
    'data_state'         => array("VARCHAR(60) NULL DEFAULT NULL", 'حالة البيانات ▼'),
  ),
  'fleet_equipment_history' => array(
    'move_code'          => array("VARCHAR(60) NULL DEFAULT NULL", 'رقم الحركة'),
    'move_seq'           => array("INT NULL DEFAULT NULL", 'تسلسل الحركة'),
    'from_site'          => array("VARCHAR(120) NULL DEFAULT NULL", 'من موقع'),
    'from_project'       => array("VARCHAR(120) NULL DEFAULT NULL", 'من مشروع'),
    'from_unit'          => array("VARCHAR(80) NULL DEFAULT NULL", 'من وحدة تعاقدية'),
    'to_site'            => array("VARCHAR(120) NULL DEFAULT NULL", 'إلى موقع'),
    'to_project'         => array("VARCHAR(120) NULL DEFAULT NULL", 'إلى مشروع'),
    'to_unit'            => array("VARCHAR(80) NULL DEFAULT NULL", 'إلى وحدة تعاقدية'),
    'move_reason'        => array("VARCHAR(80) NULL DEFAULT NULL", 'سبب الحركة ▼'),
    'meter_at_depart'    => array("DECIMAL(12,2) NULL DEFAULT NULL", 'قراءة العداد عند المغادرة'),
    'meter_at_arrive'    => array("DECIMAL(12,2) NULL DEFAULT NULL", 'قراءة العداد عند الوصول'),
    'transfer_request_ref'=> array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع طلب الترحيل'),
    'transfer_order_ref' => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع أمر الترحيل'),
    'pre_move_check_ref' => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع فحص ما قبل الحركة'),
    'post_move_check_ref'=> array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع فحص ما بعد الحركة'),
    'transit_damage'     => array("VARCHAR(500) NULL DEFAULT NULL", 'أضرار أثناء النقل'),
    'out_of_service_days'=> array("INT NULL DEFAULT NULL", 'أيام خارج الخدمة ◄'),
  ),
);
$n = 0;
foreach ($PLAN as $tbl => $cols) {
    $have = array();
    $q = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $conn->real_escape_string($tbl) . "'");
    while ($x = $q->fetch_row()) { $have[$x[0]] = 1; }
    $add = array();
    foreach ($cols as $k => $d) {
        if (isset($have[$k])) { continue; }
        $add[] = "ADD COLUMN `{$k}` {$d[0]} COMMENT '" . $conn->real_escape_string($d[1]) . "'";
    }
    if (!$add) { echo "· {$tbl}: قائمةٌ سلفًا\n"; continue; }
    if (!$conn->query("ALTER TABLE `{$tbl}` " . implode(', ', $add))) { exit("⛔ {$tbl}: {$conn->error}\n"); }
    echo '✔ ' . $tbl . ': ' . count($add) . " عمودًا\n";
    $n += count($add);
}
echo "✔ المجموع: {$n}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
