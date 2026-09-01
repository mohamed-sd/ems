<?php
/**
 * 2028_02_28_fleet_intake_guide_columns.php — حقولُ الورقةِ لأسطحِ دخولِ الأصل (GOV_EXEC §12)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: alter:asset_intake (FLEET-03 · FLEET-11) · asset_source_check (FLEET-04)
 *                     · asset_inspection_order (FLEET-05)
 *
 * ◆ سطحٌ واحدٌ (`Fleet/asset_intake.php`) يخدم **أربعةَ أهداف** بأربعِ حبّاتٍ
 *   متمايزة: الطلبُ (`asset_intake`) · واقعةُ التحقّقِ (`asset_source_check`) ·
 *   أمرُ التفتيشِ (`asset_inspection_order`) · والتفعيلُ — **وهو واقعةٌ على
 *   الطلبِ نفسِه** (سطرُ الإدخالِ يُفعَّل) فأعمدتُه في `asset_intake` ولا جدولَ
 *   خامسَ يُنشأ لحبّةٍ لا تستقلّ.
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
 'asset_intake' => array(
   /* FLEET-03 */
   'request_date'      => array("DATE NULL DEFAULT NULL", 'تاريخ الطلب'),
   'requester_name'    => array("VARCHAR(160) NULL DEFAULT NULL", 'طالب الإدخال'),
   'need_source'       => array("VARCHAR(80) NULL DEFAULT NULL", 'مصدر الاحتياج ▼'),
   'client_contract_ref'=> array("VARCHAR(80) NULL DEFAULT NULL", 'مرجع عقد العميل'),
   'project_no'        => array("VARCHAR(60) NULL DEFAULT NULL", 'رقم المشروع'),
   'power_source_code' => array("VARCHAR(60) NULL DEFAULT NULL", 'كود مصدر القدرة'),
   'requested_class'   => array("VARCHAR(80) NULL DEFAULT NULL", 'رمز التصنيف المطلوب'),
   'requested_spec'    => array("VARCHAR(500) NULL DEFAULT NULL", 'المواصفة المطلوبة'),
   'requested_count'   => array("INT NULL DEFAULT NULL", 'العدد المطلوب'),
   'need_date'         => array("DATE NULL DEFAULT NULL", 'تاريخ الحاجة'),
   'operational_justification' => array("VARCHAR(500) NULL DEFAULT NULL", 'المبرر التشغيلي'),
   'impact_if_unmet'   => array("VARCHAR(500) NULL DEFAULT NULL", 'الأثر إن لم يُلبَّ'),
   'resulting_asset_code' => array("VARCHAR(60) NULL DEFAULT NULL", 'كود الأصل الناتج ◄'),
   /* FLEET-11 — التفعيل: واقعةٌ على الطلبِ نفسِه */
   'activation_code'   => array("VARCHAR(60) NULL DEFAULT NULL", 'رقم التفعيل'),
   'activation_kind'   => array("VARCHAR(80) NULL DEFAULT NULL", 'نوع الواقعة ▼'),
   'inspection_ref'    => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع الفحص'),
   'work_order_ref'    => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع أمر الصيانة'),
   'activation_date'   => array("DATE NULL DEFAULT NULL", 'تاريخ التفعيل'),
   'activation_meter'  => array("DECIMAL(12,2) NULL DEFAULT NULL", 'قراءة العداد'),
   'activation_site'   => array("VARCHAR(120) NULL DEFAULT NULL", 'الموقع'),
   'activation_project'=> array("VARCHAR(120) NULL DEFAULT NULL", 'المشروع'),
   'state_before'      => array("VARCHAR(80) NULL DEFAULT NULL", 'الحالة قبل ◄'),
   'state_after'       => array("VARCHAR(80) NULL DEFAULT NULL", 'الحالة بعد ◄'),
   'readiness_evidence'=> array("VARCHAR(255) NULL DEFAULT NULL", 'دليل الجاهزية'),
   'down_days_before'  => array("INT NULL DEFAULT NULL", 'أيام التوقف قبل التفعيل ◄'),
   /* كتلةُ التدقيق */
   'reviewer'          => array("VARCHAR(160) NULL DEFAULT NULL", 'المراجع'),
   'approved_at'       => array("DATETIME NULL DEFAULT NULL", 'تاريخ الاعتماد'),
   'record_basis'      => array("VARCHAR(80) NULL DEFAULT NULL", 'أساس السجل ▼'),
   'data_state'        => array("VARCHAR(60) NULL DEFAULT NULL", 'حالة البيانات ▼'),
 ),
 'asset_source_check' => array(
   'check_code'        => array("VARCHAR(60) NULL DEFAULT NULL", 'رقم التحقق'),
   'power_source_code' => array("VARCHAR(60) NULL DEFAULT NULL", 'كود مصدر القدرة'),
   'supplying_party'   => array("VARCHAR(160) NULL DEFAULT NULL", 'الطرف المُورِّد'),
   'party_nature'      => array("VARCHAR(80) NULL DEFAULT NULL", 'طبيعة الطرف ▼'),
   'ownership_proven'  => array("VARCHAR(40) NULL DEFAULT NULL", 'أُثبتت الملكية؟ ▼'),
   'ownership_proof_ref'=> array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع إثبات الملكية'),
   'docs_required'     => array("VARCHAR(500) NULL DEFAULT NULL", 'المستندات المطلوبة ◄'),
   'docs_received'     => array("VARCHAR(500) NULL DEFAULT NULL", 'المستندات المستلمة'),
   'docs_missing'      => array("VARCHAR(500) NULL DEFAULT NULL", 'المستندات الناقصة ◄'),
   'docs_complete_pct' => array("DECIMAL(8,2) NULL DEFAULT NULL", 'نسبة اكتمال المستندات ◄'),
   'chassis_matches'   => array("VARCHAR(40) NULL DEFAULT NULL", 'الشاسيه مطابق للمستند؟ ▼'),
   'chassis_duplicate' => array("VARCHAR(40) NULL DEFAULT NULL", 'الشاسيه مكرر بالسجل؟ ◄'),
   'reservations'      => array("VARCHAR(500) NULL DEFAULT NULL", 'التحفظات'),
   'exception_ref'     => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع الاستثناء'),
   'reviewer'          => array("VARCHAR(160) NULL DEFAULT NULL", 'المراجع'),
   'approved_by'       => array("INT NULL DEFAULT NULL", 'المعتمِد'),
   'approved_at'       => array("DATETIME NULL DEFAULT NULL", 'تاريخ الاعتماد'),
   'record_basis'      => array("VARCHAR(80) NULL DEFAULT NULL", 'أساس السجل ▼'),
   'src_ref'           => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع المصدر'),
   'data_state'        => array("VARCHAR(60) NULL DEFAULT NULL", 'حالة البيانات ▼'),
 ),
 'asset_inspection_order' => array(
   'inspection_type'   => array("VARCHAR(80) NULL DEFAULT NULL", 'نوع التفتيش ▼'),
   'issue_reason'      => array("VARCHAR(120) NULL DEFAULT NULL", 'سبب إصدار الأمر ▼'),
   'cause_event_ref'   => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع الواقعة المسبِّبة'),
   'issuer_name'       => array("VARCHAR(160) NULL DEFAULT NULL", 'مُصدر الأمر'),
   'issue_date'        => array("DATE NULL DEFAULT NULL", 'تاريخ إصدار الأمر'),
   'executor_party'    => array("VARCHAR(120) NULL DEFAULT NULL", 'الجهة المنفِّذة ▼'),
   'assigned_inspector'=> array("VARCHAR(160) NULL DEFAULT NULL", 'المفتش المكلَّف'),
   'target_site'       => array("VARCHAR(120) NULL DEFAULT NULL", 'الموقع المستهدف'),
   'project_ref'       => array("VARCHAR(120) NULL DEFAULT NULL", 'المشروع ◄'),
   'priority'          => array("VARCHAR(60) NULL DEFAULT NULL", 'الأولوية ▼'),
   'inspection_scope'  => array("VARCHAR(500) NULL DEFAULT NULL", 'نطاق التفتيش المطلوب'),
   'card_no'           => array("VARCHAR(60) NULL DEFAULT NULL", 'رقم بطاقة التفتيش ◄'),
   'actual_exec_date'  => array("DATE NULL DEFAULT NULL", 'تاريخ التنفيذ الفعلي ◄'),
   'delay_days'        => array("INT NULL DEFAULT NULL", 'التأخير عن الموعد (يوم) ◄'),
   'reviewer'          => array("VARCHAR(160) NULL DEFAULT NULL", 'المراجع'),
   'review_date'       => array("DATE NULL DEFAULT NULL", 'تاريخ المراجعة'),
   'approved_by'       => array("INT NULL DEFAULT NULL", 'المعتمِد'),
   'approved_at'       => array("DATETIME NULL DEFAULT NULL", 'تاريخ الاعتماد'),
   'record_basis'      => array("VARCHAR(80) NULL DEFAULT NULL", 'أساس السجل ▼'),
   'src_ref'           => array("VARCHAR(120) NULL DEFAULT NULL", 'مرجع المصدر'),
   'data_state'        => array("VARCHAR(60) NULL DEFAULT NULL", 'حالة البيانات ▼'),
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
    echo "✔ {$tbl}: " . count($add) . " عمودًا\n";
    $n += count($add);
}
echo "✔ المجموع: {$n}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
