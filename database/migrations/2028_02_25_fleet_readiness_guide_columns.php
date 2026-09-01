<?php
/**
 * 2028_02_25_fleet_readiness_guide_columns.php — حقولُ الورقةِ لسطحَي الجاهزية (GOV_EXEC §12)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: alter:asset_readiness (حقول FLEET-19 · FLEET-20)
 *
 * ◆ **السطحُ يخدم هدفَين** (`Screens ≠ Placements` §24): «الملخص التشغيلي
 *   الشهري» (`FLEET-19` · 28 حقلًا منطبقًا) و«الجاهزية الشهرية» (`FLEET-20` ·
 *   15 حقلًا) — والجدولُ `asset_readiness` يحمل خمسةَ عشرَ عمودًا فقط، فالمقيسُ
 *   من الأثرِ كان **0/28 و1/15**.
 *
 * ◆ **وكلُّ عمودٍ مُضافٍ مشتقٌّ لا مُدخَل** — بنصِّ رأسِ الشاشةِ نفسِها:
 *   *«مشتقّةٌ بالكامل — لا إدخال»* و*«لا تُدخَل الساعاتُ يدويًّا مرّتين»*.
 *   فتعليقُ كلِّ عمودٍ يحمل اسمَ حقلِ الورقةِ حرفًا بعلامةِ اشتقاقِه (◄)،
 *   ولا نموذجَ إدخالٍ واحدٌ في الشاشة. ⛔ والقيمةُ يكتبها محرّكُ الاشتقاقِ
 *   (`AssetLifecycleService`) لا يدٌ.
 *
 * التشغيل: php database/migrations/2028_02_25_fleet_readiness_guide_columns.php
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
    /* FLEET-19 — الملخص التشغيلي الشهري */
    'record_code'        => array("VARCHAR(60) NULL DEFAULT NULL", 'كود السجل'),
    'unit_key'           => array("VARCHAR(80) NULL DEFAULT NULL", 'مفتاح الوحدة ◄'),
    'project_ref'        => array("VARCHAR(120) NULL DEFAULT NULL", 'المشروع ◄'),
    'business_model'     => array("VARCHAR(80) NULL DEFAULT NULL", 'نموذج العمل ◄'),
    'meter_start'        => array("DECIMAL(12,2) NULL DEFAULT NULL", 'العداد أول الشهر'),
    'meter_end'          => array("DECIMAL(12,2) NULL DEFAULT NULL", 'العداد آخر الشهر'),
    'meter_hours'        => array("DECIMAL(12,2) NULL DEFAULT NULL", 'ساعات العداد ◄'),
    'jackhammer_hours'   => array("DECIMAL(12,2) NULL DEFAULT NULL", 'ساعات الجاك همر'),
    'extra_hours'        => array("DECIMAL(12,2) NULL DEFAULT NULL", 'الساعات الإضافية'),
    'maint_down_hours'   => array("DECIMAL(12,2) NULL DEFAULT NULL", 'تعطل الصيانة'),
    'reliab_down_hours'  => array("DECIMAL(12,2) NULL DEFAULT NULL", 'تعطل اعتمادية'),
    'oper_down_hours'    => array("DECIMAL(12,2) NULL DEFAULT NULL", 'تعطل تشغيلي'),
    'total_hours'        => array("DECIMAL(12,2) NULL DEFAULT NULL", 'إجمالي الساعات ◄'),
    'accumulated_hours'  => array("DECIMAL(14,2) NULL DEFAULT NULL", 'الساعات المتراكمة ◄'),
    'tons_moved'         => array("DECIMAL(14,2) NULL DEFAULT NULL", 'الأطنان المنقولة'),
    'meters_done'        => array("DECIMAL(14,2) NULL DEFAULT NULL", 'الأمتار المنجزة'),
    'fuel_consumed'      => array("DECIMAL(14,2) NULL DEFAULT NULL", 'الوقود المستهلك'),
    'fuel_rate_hour'     => array("DECIMAL(12,3) NULL DEFAULT NULL", 'معدل الوقود للساعة ◄'),
    'meter_vs_timesheet' => array("DECIMAL(12,2) NULL DEFAULT NULL", 'فرق العداد عن التايم شيت ◄'),
    'statement_source'   => array("VARCHAR(80) NULL DEFAULT NULL", 'مصدر البيان ▼'),
    'confidence_grade'   => array("VARCHAR(60) NULL DEFAULT NULL", 'درجة الثقة ▼'),
    'reviewer'           => array("VARCHAR(160) NULL DEFAULT NULL", 'المراجع'),
    'approved_at'        => array("DATETIME NULL DEFAULT NULL", 'تاريخ الاعتماد'),
    /* FLEET-20 — الجاهزية الشهرية */
    'available_hours'    => array("DECIMAL(12,2) NULL DEFAULT NULL", 'الساعات المتاحة ◄'),
    'operating_hours'    => array("DECIMAL(12,2) NULL DEFAULT NULL", 'ساعات التشغيل ◄'),
    'unplanned_down'     => array("DECIMAL(12,2) NULL DEFAULT NULL", 'توقف غير مخطط ◄'),
    'performance_pct'    => array("DECIMAL(8,2) NULL DEFAULT NULL", 'نسبة الأداء ◄'),
    'oee_pct'            => array("DECIMAL(8,2) NULL DEFAULT NULL", 'الكفاءة الكلية ◄'),
    'down_days'          => array("INT NULL DEFAULT NULL", 'عدد أيام التوقف ◄'),
    'readiness_state'    => array("VARCHAR(60) NULL DEFAULT NULL", 'حالة الجاهزية ◄'),
);
$have = array();
$q = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asset_readiness'");
while ($x = $q->fetch_row()) { $have[$x[0]] = 1; }
$add = array();
foreach ($COLS as $k => $d) {
    if (isset($have[$k])) { continue; }
    $add[] = "ADD COLUMN `{$k}` {$d[0]} COMMENT '" . $conn->real_escape_string($d[1]) . "'";
}
if ($add) {
    if (!$conn->query("ALTER TABLE `asset_readiness` " . implode(', ', $add))) { exit("⛔ {$conn->error}\n"); }
    echo '✔ ' . count($add) . " عمودًا أُضيف بتعليقِ حقلِ الورقةِ حرفًا\n";
} else { echo "· قائمةٌ سلفًا\n"; }
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
