<?php
/**
 * 2028_01_03_rpr02_sot_witness.php — عمودا شاهدِ مصدرِ الحقيقة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `RPR-02` §١٢ المقياس **#٩** («أسطحٌ تكتب ومالكُ حقيقتِها
 *   مجهول») = **١٩٠**، و§٥·٩ يشترط `Unknown Write Owner = 0` قبل الإغلاق.
 *   و`repair01_screen_registry.source_of_truth` عمودٌ قائمٌ **بلا موضعٍ لشاهدِه**.
 *
 * ⛔ **وقيمةٌ بلا شاهدٍ هي بعينِها العطبُ الذي نعالجه**: مَن يملك كتابةَ
 *   `source_of_truth` بلا شاهدٍ يملك **تصفيرَ المقياسِ #٩ بجملةِ `UPDATE` واحدة**
 *   بلا أن يُقاس شيء. فالعمودان يُسنّان **قبل** أن تُكتب قيمةٌ واحدة، وقاعدةٌ
 *   صلبةٌ تلزمهما معًا: **مصدرُ حقيقةٍ مكتوبٌ بلا قاعدةٍ وشاهدٍ مرفوضٌ في القاعدة
 *   لا في مراجعةٍ لاحقة**.
 *
 * ◆ **والقاعدةُ تصدق الآن**: الأسطحُ التي تحمل `source_of_truth` غيرَ فارغٍ
 *   اليومَ **ثمانيةَ عشرَ** — وتُمنح قاعدةً تاريخيّةً مُعلَنةً (`PRE_W17_DECLARED`)
 *   وشاهدًا يقول إنّها سبقت العمودَين، **فلا تُدهَس ولا تُزعم مقيسةً**.
 *
 * التشغيل: php database/migrations/2028_01_03_rpr02_sot_witness.php
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
    $r = $conn->query("SHOW COLUMNS FROM `repair01_screen_registry` LIKE '" . $col . "'");
    return $r && $r->num_rows > 0;
};

if (!$has('sot_rule')) {
    $ok = $conn->query("ALTER TABLE `repair01_screen_registry`
        ADD COLUMN `sot_rule` VARCHAR(48) NOT NULL DEFAULT ''
            COMMENT 'قاعدة تعيين مصدر الحقيقة — SOLE_WRITER او DUPLICATE_SOURCE او قرار مسجل',
        ADD COLUMN `sot_witness` VARCHAR(500) NOT NULL DEFAULT ''
            COMMENT 'شاهد التعيين — ولا مصدر حقيقة مكتوب بلا شاهد',
        ADD COLUMN `sot_snapshot` VARCHAR(48) NOT NULL DEFAULT ''
            COMMENT 'اللقطة التي قيس عليها التعيين'");
    if (!$ok) { exit("✘ تعذّر إضافةُ الأعمدة: {$conn->error}\n"); }
    echo "  ✔ أُضيفت `sot_rule` و`sot_witness` و`sot_snapshot`\n";
} else {
    echo "  ◆ الأعمدةُ قائمةٌ سلفًا — ولا يُعاد إنشاؤها\n";
}

/* **القائمُ قبلَ العمودَين يُعلَن تاريخيًّا — ولا يُزعم مقيسًا ولا يُدهَس** */
$pre = (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry
                            WHERE source_of_truth <> '' AND sot_rule = ''")->fetch_row()[0];
if ($pre > 0) {
    $conn->query("UPDATE repair01_screen_registry
        SET sot_rule = 'PRE_W17_DECLARED',
            sot_witness = 'قيمةٌ سابقةٌ لعمودَي الشاهد — كُتبت في موجةٍ سابقةٍ بقرارِها، وتُعلَن تاريخيّةً ولا تُزعم مقيسةً بقاعدةِ الكاتبِ الوحيد'
      WHERE source_of_truth <> '' AND sot_rule = ''");
    echo "  ✔ وُسم **$pre** صفًّا قائمًا بـ`PRE_W17_DECLARED` — لا يُزعم مقيسًا\n";
}

/* ⛔ **والقاعدةُ الصلبةُ تُسنُّ بعد الوسمِ فتصدق على كلِّ صفٍّ قائم** */
$r = $conn->query("SHOW CREATE TABLE `repair01_screen_registry`");
$ddl = $r ? $r->fetch_row()[1] : '';
if (strpos($ddl, 'chk_sot_witness') === false) {
    $ok = $conn->query("ALTER TABLE `repair01_screen_registry`
        ADD CONSTRAINT `chk_sot_witness` CHECK (`source_of_truth` = '' OR `sot_witness` <> '')");
    echo $ok ? "  ✔ سُنَّت `chk_sot_witness`: **لا مصدرَ حقيقةٍ مكتوبٌ بلا شاهد**\n"
             : "  ⚠ تعذّر سنُّ القاعدة: {$conn->error}\n";
} else {
    echo "  ◆ `chk_sot_witness` قائمةٌ سلفًا\n";
}

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ الشاهدِ مفتوحٌ — ولم تُكتب قيمةُ مصدرِ حقيقةٍ واحدةٍ بعد\n";
