<?php
/**
 * 2027_12_29_rpr02_built_grain_columns.php — موضعُ **الحبّةِ المقيسةِ على المبنيّ**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما يوجبه الأمر** — `RPR-02` §٧ الخطوة ١: *«تصحيحُ الحبّة — كما في الملف،
 *   **ولا سطحَ يجمع حبّتين**»*. و§٤·٢ يعرّف `مطابَق` بأنّه *«له سطحٌ مبنيٌّ
 *   **بالحبّةِ والمالكِ نفسيهما**»* ⇒ فبلا حبّةٍ مقيسةٍ على الجانبِ المبنيِّ
 *   **لا يُحكَم بمطابقةٍ أصلًا**، وذاك حاجزُ الـ١٣٠ هدفًا المعلَّقة.
 *
 * ◆ **ولا يُدهَس `grain_ar`** — فهو **حبّةٌ مُعلَنةٌ** كتبتها الموجتان ١٤ و١٥
 *   لأسطحِ نموِّهما (٤٧ صفًّا)، ومصدرُها **قرارُ الموجةِ لا قياسُ الملفّ**.
 *   وخلطُ المُعلَنِ بالمقيسِ في عمودٍ واحدٍ يُفقد أيَّهما شاهدٌ على الآخر
 *   ⇒ فالمقيسُ **أعمدةٌ جديدةٌ بجانبه** لا فوقه (درسُ «سجلّان لا سجلّ»).
 *
 * ◆ **والقاعدةُ الصلبةُ تُسنّ الآن لا تؤجَّل** — لأنَّ محتواها صادقٌ ابتداءً:
 *   الأعمدةُ تولد فارغةً، والقيدُ يقول «حبّةٌ بلا شاهدٍ مرفوضة» فلا يُخالفه
 *   صفٌّ قائم. (‏وهذا يخالف حالَ `2027_12_14` حيث سبق المحتوى القاعدة.)
 *
 * التشغيل: php database/migrations/2027_12_29_rpr02_built_grain_columns.php
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

$has = function ($col) use ($conn) {
    $r = $conn->query("SHOW COLUMNS FROM `repair01_screen_registry` LIKE '" . $conn->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
};

/* ① أعمدةُ الحبّةِ المقيسة ───────────────────────────────────────────────── */
$ADD = array(
    'grain_entity'      => "VARCHAR(80) NOT NULL DEFAULT '' COMMENT 'كيان الحبة المقيس — الجدول الأساسي للسطح (RPR-02 §7-1)'",
    'grain_cardinality' => "ENUM('ROW','LINE','LIVE_READ','LIST','NONE') NOT NULL DEFAULT 'NONE' COMMENT 'صنف الحبة المقيس — سطر/بند/قراءة حية/قائمة'",
    'grain_measured'    => "VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'الحبة المقيسة منطوقة بالعربية للمقارنة بحبة التصميم'",
    'grain_rule'        => "VARCHAR(48) NOT NULL DEFAULT '' COMMENT 'القاعدة التي قررت الحبة — G1..G6'",
    'grain_witness'     => "VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'شاهد القياس: المسار المحلول والجداول بأدوارها — ولا حبة بلا شاهد'",
    'grain_multi'       => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'سطح يجمع حبتين — خرق RPR-02 §7 الخطوة 1'",
);
$after = 'grain_ar';
foreach ($ADD as $col => $def) {
    if ($has($col)) { echo "  ◆ `$col` قائمٌ سلفًا\n"; $after = $col; continue; }
    if (!$conn->query("ALTER TABLE `repair01_screen_registry` ADD COLUMN `$col` $def AFTER `$after`")) {
        exit("✘ تعذّرت إضافةُ `$col`: {$conn->error}\n");
    }
    echo "  ✔ أُضيف `$col`\n";
    $after = $col;
}

/* ② القاعدةُ الصلبة — حبّةٌ بلا شاهدٍ مرفوضة ──────────────────────────────
     ⛔ ولا تُسَنُّ قاعدةٌ قبلَ صدقِ محتواها: الأعمدةُ تولد فارغةً هنا،
        فالقيدُ لا يُرَدّ بصفٍّ قائم. */
$r = $conn->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_grain_witness'");
$exists = $r ? (int) $r->fetch_row()[0] : 0;
if ($exists) {
    echo "  ◆ `chk_grain_witness` مسنونٌ سلفًا\n";
} else {
    $bad = (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry
                                WHERE grain_entity <> '' AND grain_witness = ''")->fetch_row()[0];
    if ($bad > 0) {
        echo "  ⚠ $bad صفًّا بحبّةٍ بلا شاهد — **والقاعدةُ لا تُسَنُّ على محتوًى كاذب**\n";
    } else {
        $ok = $conn->query("ALTER TABLE `repair01_screen_registry`
            ADD CONSTRAINT `chk_grain_witness`
            CHECK (`grain_entity` = '' OR `grain_witness` <> '')");
        if (!$ok) { exit("✘ تعذّر سنُّ `chk_grain_witness`: {$conn->error}\n"); }
        echo "  ✔ سُنَّ `chk_grain_witness` — حبّةٌ بلا شاهدٍ مرفوضةٌ على مستوى المخطَّط\n";
    }
}

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));

echo "\n✔ موضعُ الحبّةِ المقيسةِ مفتوحٌ — والمُعلَنُ `grain_ar` لم يُمَسّ\n";
