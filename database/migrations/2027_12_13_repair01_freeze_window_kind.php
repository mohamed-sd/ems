<?php
/**
 * 2027_12_13_repair01_freeze_window_kind.php — نافذتان لا نافذةٌ واحدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس**: `repair01_freeze.php` يشترط لكلِّ تجميدٍ **انحدارًا
 *   أخضرَ كاملًا**. وقد رُدَّ أوّلُ تجميدٍ في `MASTER_EXEC` بـ«نجح 17 · رسب 8»،
 *   والثمانيةُ **حواجزُ موجاتٍ مفتوحةٌ بحقّ** (`W6` · `W7` · `W8` · `W13` ·
 *   `W14` · `W16` · `W135`) — أي **حالُ البرنامجِ الذي جاء الأمرُ ليقيسه**.
 *
 * ◆ **والقاعدةُ لا تنطبق أصلًا** — `AMD-01` المرحلة ٥: *«ولا تستخدمْ استثناءً
 *   لتجاوزِ قاعدةٍ لا تنطبق أصلًا — صحِّحْ قاعدةَ الانطباقِ نفسَها»*. فالنصُّ
 *   الدستوريُّ (البند ⑬) يمنع **التعديلَ أثناءَ** النافذة، ولا يشترط الخضرةَ
 *   **لدخولِها**. والخضرةُ شرطُ **إصدارِ أساسٍ معتمَد** لا شرطُ **قياسٍ تشخيصيّ**.
 *   ⛔ **ولولا التصحيحُ لصار الشرطُ حاجزًا دائريًّا**: لا قياسَ قبل التجميد،
 *   ولا تجميدَ قبل خضرةٍ، ولا خضرةَ قبل عملٍ يُبنى على قياس.
 *
 * ◆ **والتصحيحُ يشدُّ ولا يُرخي**: النافذةُ التشخيصيّةُ **تختم إحصاءَ الانحدارِ
 *   في اللقطةِ نفسِها** بأسماءِ الحواجزِ الساقطة. فاليومَ تُجمَّد لقطةٌ ولا تحمل
 *   عن الانحدارِ حرفًا؛ وبعدَه **لا يستطيع تقريرٌ صادرٌ عن لقطةٍ أن يدّعيَ خضرةً
 *   يكذّبها ختمُ لقطتِه**.
 *
 * ◆ **والافتراضُ يبقى الأشدَّ**: `window_kind` الافتراضيُّ `BASELINE`، فمن لم
 *   يُعلن نوعَه خضع للشرطِ القديم كما كان.
 *
 * التشغيل: php database/migrations/2027_12_13_repair01_freeze_window_kind.php
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
    $r = $conn->query("SHOW COLUMNS FROM `repair01_freeze_snapshot` LIKE '" . $col . "'");
    return $r && $r->num_rows > 0;
};

/* ① نوعُ النافذةِ — والافتراضُ الأشدّ ──────────────────────────────────── */
if ($has('window_kind')) {
    echo "  ◆ `window_kind` قائمٌ سلفًا\n";
} else {
    $ok = $conn->query("ALTER TABLE `repair01_freeze_snapshot`
        ADD COLUMN `window_kind` ENUM('BASELINE','DIAGNOSTIC') NOT NULL DEFAULT 'BASELINE'
            COMMENT 'BASELINE يشترط انحدارا اخضر · DIAGNOSTIC يختم الاحصاء ولا يشترط الخضرة'
            AFTER `purpose`");
    if (!$ok) { exit("✘ تعذّرت إضافةُ `window_kind`: {$conn->error}\n"); }
    echo "  ✔ أُضيف `window_kind` — والافتراضُ `BASELINE`\n";
}

/* ② إحصاءُ الانحدارِ المختوم ────────────────────────────────────────────
     ⛔ **ولا يُترك فارغًا**: لقطةٌ بلا إحصاءٍ لا يُعرَف أخضرَ كانت أم حمراء. */
if ($has('regression_census')) {
    echo "  ◆ `regression_census` قائمٌ سلفًا\n";
} else {
    $ok = $conn->query("ALTER TABLE `repair01_freeze_snapshot`
        ADD COLUMN `regression_census` VARCHAR(512) NOT NULL DEFAULT ''
            COMMENT 'نجح/المجموع · واسماء الحواجز الساقطة وقت الختم'
            AFTER `window_kind`");
    if (!$ok) { exit("✘ تعذّرت إضافةُ `regression_census`: {$conn->error}\n"); }
    echo "  ✔ أُضيف `regression_census`\n";
}

/* ③ التاريخيُّ يُعلَن ولا يُخترَع ──────────────────────────────────────── */
$ok = $conn->query("UPDATE `repair01_freeze_snapshot`
                       SET `regression_census` = '— غير مختوم عند التجميد'
                     WHERE `regression_census` = ''");
if (!$ok) { exit("✘ تعذّر وسمُ التاريخيّ: {$conn->error}\n"); }
printf("  ✔ وُسمت %d لقطةً تاريخيّةً — ولا إحصاءَ رجعيًّا مخترَعًا\n", $conn->affected_rows);

/* ④ قاعدةٌ صلبة: لا لقطةَ بإحصاءٍ فارغ ──────────────────────────────────
     والتاريخيُّ يمرُّ بوسمِه المُعلَنِ لأنّه ليس فارغًا — فالقاعدةُ تلزم من
     بعدَها ولا تُبطل ما قبلَها. */
$r = $conn->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_frz_census'");
if ($r && (int) $r->fetch_row()[0] > 0) {
    echo "  ◆ القاعدةُ قائمةٌ سلفًا\n";
} else {
    $ok = $conn->query("ALTER TABLE `repair01_freeze_snapshot`
        ADD CONSTRAINT `chk_frz_census` CHECK (`regression_census` <> '')");
    if (!$ok) { exit("✘ تعذّرت إضافةُ القاعدة: {$conn->error}\n"); }
    echo "  ✔ قاعدةٌ صلبة: لا لقطةَ بإحصاءِ انحدارٍ فارغ\n";
}

$r = $conn->query("SELECT `window_kind`, COUNT(*) n FROM `repair01_freeze_snapshot` GROUP BY 1");
echo "\n  ── اللقطاتُ بنوعِ نافذتِها ──\n";
while ($x = $r->fetch_assoc()) { printf("     %-12s %d\n", $x['window_kind'], $x['n']); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));

echo "\n✔ نافذتان: الأساسُ يشترط الخضرةَ · والتشخيصُ يختم الحمرةَ ولا يهرب منها\n";
