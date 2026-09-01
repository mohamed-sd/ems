<?php
/**
 * 2028_03_15_drill_migration_set_hash.php — بصمةُ مجموعةِ الهجراتِ في محضرِ التمرين
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس**: `FR-GOV-014` يحكم على تقادُمِ تمرينِ التثبيتِ بمقارنةِ
 *   **ساعتَي حائط**: `dr_drills.finished_at ≥ MAX(schema_migrations.applied_at)`.
 *   وساعةُ الحائطِ **ترجع**: قيست في هذه الجولةِ رجعةٌ مقدارُها 11.4 ساعةً
 *   (صار النطاقُ `EDT` و`PHP` على `Africa/Cairo`)، فصار **65 صفًّا** في دفترِ
 *   الهجراتِ بختمٍ أعلى من `NOW()`. فأيُّ تمرينٍ يُشغَّل الآن يُقرأ **متقادمًا
 *   وهو أحدثُ ما وقع** — والحكمُ على غيرِ مَحلِّه.
 *
 * ◆ **والعلاجُ أن يقيسَ الحاجبُ ما يدّعيه**: «غيرُ متقادمٍ» معناها **أنَّ
 *   التمرينَ تحقّق من مجموعةِ الهجراتِ التي عندك الآن** — لا أنَّ ختمَه أكبر.
 *   فتُقيَّد في المحضرِ **بصمةُ المجموعة** (اسمُ كلِّ هجرةٍ مع بصمتِها مرتَّبةً)،
 *   والتقادمُ = اختلافُ البصمة.
 *
 * ◆ ⭐ **وهو أشدُّ لا أرخى**: الختمُ لا يُثبت أنَّ المخطَّطَ هو الذي فُحص —
 *   فهجرةٌ تضيف **عمودًا** لا تحرّك عددَ الكائنات ولا تُكشف بمقارنةِ عددٍ،
 *   وتُكشف بالبصمة. ⛔ ولا يُرخى الحكمُ على المحاضرِ القديمة: صفٌّ بلا بصمةٍ
 *   (`NULL`) يبقى محكومًا بقاعدةِ الختمِ كما كان.
 *
 * @migration-objects: col:dr_drills.migration_set_hash
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
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
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$has = false;
$q = $conn->query("SHOW COLUMNS FROM dr_drills LIKE 'migration_set_hash'");
if ($q && $q->num_rows) { $has = true; }

if (!$has) {
    if ($conn->query("ALTER TABLE dr_drills
                       ADD COLUMN migration_set_hash CHAR(40) NULL
                       COMMENT 'SHA-1 لمجموعةِ الهجراتِ المطبَّقةِ وقتَ التمرين — تقادُمٌ بلا ساعةِ حائط'")) {
        echo "أُضيف dr_drills.migration_set_hash\n";
    } else {
        echo "⛔ تعذّرت الإضافة: " . $conn->error . "\n";
    }
} else {
    echo "= العمودُ قائمٌ أصلًا\n";
}
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
