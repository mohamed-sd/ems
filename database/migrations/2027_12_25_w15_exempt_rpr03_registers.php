<?php
/**
 * 2027_12_25_w15_exempt_rpr03_registers.php — سجلّا `RPR-03` يُعلَنان بجولتِهما
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس**: `repair01_w15_gate` `W15-05` (‏«لا جدولَ أعمالٍ جديدٌ
 *   أنشأته هذه المرحلة») رسَب بـ**نموٍّ غيرِ مُعلَنٍ = ٢**، والجدولان
 *   `rpr03_event_classification` و`rpr03_event_dead_letter_rulings` **أنشأتهما
 *   هذه الجولةُ لا `W15`**.
 *
 * ◆ **والعلاجُ إعلانٌ لا تليين** — ونصُّ الحاجبِ نفسُه يحكم: *«⛔ وتوسيعُ نمطِ
 *   الاستبعادِ يُسكِت الحاجبَ عن كلِّ نموٍّ قادمٍ بالجملة **وهو تليينٌ لا
 *   إصلاح**. فالمُعلَنُ وحدَه يُستثنى — في `repair01_w15_table_exempt`
 *   **بجولتِه وسببِه** — والحاجبُ يسقط على غيرِ المُعلَنِ كما كان»*.
 *   ⇒ **فلا يُوسَّع النمطُ إلى `rpr03\_%`** — يُعلَن الجدولان بأسمائهما.
 *
 * ◆ **ولماذا يستحقّان الإعلانَ**: كلاهما **سجلُّ حكمٍ حوكميّ** لا حقيقةَ أعمال:
 *   الأوّلُ يحمل تصنيفَ نوعِ الحدثِ بدليلِه (`RPR-03` §٤·٢)، والثاني يحمل حكمَ
 *   الرسالةِ الميتةِ بسببِه (§٦·٣). **ولا يكتب فيهما مستخدمٌ ولا يقرؤهما سطح** —
 *   وهما بمنزلةِ `gov_migration_settlement` المُعلَنِ سلفًا بالسببِ نفسِه.
 *
 * ⛔ **ولا يُعلَن جدولٌ لم يُنشأ بعد** — يُقاس وجودُه أوّلًا، فإعلانُ غائبٍ
 *   يفتح بابًا لما سيأتي.
 *
 * التشغيل: php database/migrations/2027_12_25_w15_exempt_rpr03_registers.php
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

$DECL = array(
    'rpr03_event_classification' => 'سجلُّ حكمِ نوعِ الحدثِ بدليلِه — RPR-03 §4-2 الخطوة 1. '
        . 'سجلُّ حوكمةٍ لا حقيقةَ اعمال: لا يكتب فيه مستخدم ولا يقرؤه سطح.',
    'rpr03_event_dead_letter_rulings' => 'سجلُّ حكمِ الرسالةِ الميتةِ بسببِه — RPR-03 §6-3. '
        . 'ومقامُه غيرُ مقامِ gov_dead_letter_rulings (‏ذاك مفتاحُه job_id).',
);
$ROUND = 'RPR-03 · مسار ب — تكاملُ الأحداث';

$done = 0; $skip = 0;
foreach ($DECL as $t => $why) {
    /* ⛔ **ولا يُعلَن جدولٌ لم يُنشأ بعد** */
    $ex = (int) $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '"
                             . $conn->real_escape_string($t) . "'")->fetch_row()[0];
    if (!$ex) { echo "  ⛔ `$t` غيرُ موجودٍ — ولا يُعلَن غائب\n"; $skip++; continue; }
    $st = $conn->prepare("INSERT INTO repair01_w15_table_exempt
        (table_name, owner_round, why, declared_at, declared_by)
        VALUES (?, ?, ?, NOW(), '2027_12_25_w15_exempt_rpr03_registers.php')
        ON DUPLICATE KEY UPDATE owner_round = VALUES(owner_round), why = VALUES(why)");
    $st->bind_param('sss', $t, $ROUND, $why);
    if (!$st->execute()) { exit("✘ تعذّر إعلانُ `$t`: {$conn->error}\n"); }
    echo "  ✔ أُعلن `$t` بجولتِه وسببِه\n";
    $done++;
}

/* الحصيلةُ بعدٍّ — ⛔ ولا يُصدَّق الكاتبُ على كلمتِه */
$grown = (int) $conn->query("SELECT COUNT(*) FROM information_schema.TABLES t
    LEFT JOIN repair01_w15_table_snapshot s ON s.table_name = t.TABLE_NAME
    LEFT JOIN repair01_w15_table_exempt   x ON x.table_name = t.TABLE_NAME
   WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE'
     AND t.TABLE_NAME NOT LIKE 'repair01\\_%'
     AND s.table_name IS NULL AND x.table_name IS NULL")->fetch_row()[0];
printf("\n  أُعلن %d · تُخطّي %d · **نموٌّ غيرُ مُعلَنٍ الآن: %d**\n", $done, $skip, $grown);

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ أُعلن النموُّ بجولتِه — ⛔ ولم يُوسَّع نمطُ الاستبعاد\n";
