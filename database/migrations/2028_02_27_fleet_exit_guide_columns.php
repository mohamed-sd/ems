<?php
/**
 * 2028_02_27_fleet_exit_guide_columns.php — حقولُ الورقةِ لسطحَي الخروج (GOV_EXEC §12)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: alter:asset_exit (FLEET-21 الخروج المؤقت · FLEET-22 الخروج الدائم)
 *
 * ◆ سطحٌ واحدٌ (`Fleet/asset_exit.php`) يخدم هدفَين بحبّةٍ واحدةٍ **مميَّزةٍ
 *   بنوعِ الخروج** (`exit_kind`): مؤقّتٌ ودائم. فالجدولُ واحدٌ والأعمدةُ اتحادُ
 *   الحقلَين، وكلُّ عمودٍ يحمل اسمَ حقلِ ورقتِه في تعليقِه.
 * ⛔ **ولا جدولان لحبّةٍ واحدة**: التمييزُ بعمودِ النوعِ لا بجدولٍ ثانٍ.
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
  /* FLEET-21 — الخروج المؤقت */
  'exit_code'          => array("VARCHAR(60) NULL DEFAULT NULL", 'رقم الخروج'),
  'meter_reading'      => array("DECIMAL(12,2) NULL DEFAULT NULL", 'قراءة العداد'),
  'withdrawing_party'  => array("VARCHAR(160) NULL DEFAULT NULL", 'الجهة التي سحبت'),
  'justification'      => array("VARCHAR(500) NULL DEFAULT NULL", 'المبرر'),
  'decision_notice_ref'=> array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع القرار أو الإخطار'),
  'expected_days'      => array("INT NULL DEFAULT NULL", 'المدة المتوقعة (يوم)'),
  'return_service_ref' => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع إعادة الخدمة'),
  'actual_out_days'    => array("INT NULL DEFAULT NULL", 'أيام الخروج الفعلية ◄'),
  'deviation_days'     => array("INT NULL DEFAULT NULL", 'الانحراف عن المتوقع ◄'),
  'contract_unit_effect'=> array("VARCHAR(255) NULL DEFAULT NULL", 'الأثر على الوحدة التعاقدية'),
  'client_notified'    => array("VARCHAR(40) NULL DEFAULT NULL", 'أُبلغ العميل؟ ▼'),
  'substitute_asset'   => array("VARCHAR(60) NULL DEFAULT NULL", 'الأصل البديل'),
  /* FLEET-22 — الخروج الدائم */
  'disposal_code'      => array("VARCHAR(60) NULL DEFAULT NULL", 'رقم الاستبعاد'),
  'disposal_decision_date' => array("DATE NULL DEFAULT NULL", 'تاريخ قرار الاستبعاد'),
  'actual_exit_date'   => array("DATE NULL DEFAULT NULL", 'تاريخ الخروج الفعلي'),
  'disposal_reason'    => array("VARCHAR(120) NULL DEFAULT NULL", 'سبب الاستبعاد ▼'),
  'technical_state_ref'=> array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع الحالة الفنية'),
  'buyer_receiver'     => array("VARCHAR(160) NULL DEFAULT NULL", 'المشتري أو المستلم'),
  'buyer_relation'     => array("VARCHAR(80) NULL DEFAULT NULL", 'علاقة المشتري ▼'),
  'final_meter'        => array("DECIMAL(12,2) NULL DEFAULT NULL", 'قراءة العداد النهائية'),
  'net_proceeds'       => array("DECIMAL(18,2) NULL DEFAULT NULL", 'صافي الحصيلة'),
  'currency_ref'       => array("VARCHAR(20) NULL DEFAULT NULL", 'العملة ▼'),
  'cost_ref'           => array("DECIMAL(18,2) NULL DEFAULT NULL", 'التكلفة — مرجع ◄'),
  'accum_depr_ref'     => array("DECIMAL(18,2) NULL DEFAULT NULL", 'مجمع الإهلاك — مرجع ◄'),
  'book_value_ref'     => array("DECIMAL(18,2) NULL DEFAULT NULL", 'القيمة الدفترية — مرجع ◄'),
  'gain_loss'          => array("DECIMAL(18,2) NULL DEFAULT NULL", 'المكسب أو الخسارة ◄'),
  'sale_minutes_ref'   => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع محضر البيع'),
  'journal_ref'        => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع القيد المحاسبي'),
  'title_transfer_ref' => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع نقل الملكية'),
  'owners_approval'    => array("VARCHAR(40) NULL DEFAULT NULL", 'موافقة الملاك ▼'),
  'unit_vacated'       => array("VARCHAR(40) NULL DEFAULT NULL", 'أُخليت الوحدة التعاقدية؟ ▼'),
  /* كتلةُ التدقيقِ المشتركة */
  'reviewer'           => array("VARCHAR(160) NULL DEFAULT NULL", 'المراجع'),
  'approved_by'        => array("INT NULL DEFAULT NULL", 'المعتمِد'),
  'approved_at'        => array("DATETIME NULL DEFAULT NULL", 'تاريخ الاعتماد'),
  'record_basis'       => array("VARCHAR(80) NULL DEFAULT NULL", 'أساس السجل ▼'),
  'src_ref'            => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع المصدر'),
  'data_state'         => array("VARCHAR(60) NULL DEFAULT NULL", 'حالة البيانات ▼'),
);
$have = array();
$q = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asset_exit'");
while ($x = $q->fetch_row()) { $have[$x[0]] = 1; }
$add = array();
foreach ($COLS as $k => $d) {
    if (isset($have[$k])) { continue; }
    $add[] = "ADD COLUMN `{$k}` {$d[0]} COMMENT '" . $conn->real_escape_string($d[1]) . "'";
}
if ($add) {
    if (!$conn->query("ALTER TABLE `asset_exit` " . implode(', ', $add))) { exit("⛔ {$conn->error}\n"); }
    echo '✔ ' . count($add) . " عمودًا\n";
} else { echo "· قائمةٌ سلفًا\n"; }
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
