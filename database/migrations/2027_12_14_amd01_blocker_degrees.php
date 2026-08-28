<?php
/**
 * 2027_12_14_amd01_blocker_degrees.php — درجاتُ الحجبِ الستُّ ومرحلةُ القيمةِ المؤجَّلة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما يوجبه الأمر** — `MASTER_EXEC` §٣: ستُّ درجاتٍ **تنسخ كلَّ تصنيفٍ سابق**:
 *   `STRUCTURAL_BLOCKER` · `BUILD_BLOCKER` · `ENFORCEMENT_BLOCKER` ·
 *   `UAT_BLOCKER` · `GO_LIVE_BLOCKER` · `CONFIG_PENDING`.
 *
 * ◆ **وما قِستُه**: `repair01_decisions.blocking_level` مفرداتُه اليوم
 *   `STRUCTURAL_TARGET_BLOCKER` · `READY_TO_BUILD_BLOCKER` · `UAT_BLOCKER` ·
 *   `GO_LIVE_BLOCKER` · `CONFIG_PENDING` · `NONE`.
 *   ⇒ **ثلاثٌ تطابق · واثنتان تختلفان اسمًا · و`ENFORCEMENT_BLOCKER` غائبةٌ
 *      أصلًا**. وغيابُها ليس تفصيلًا: قيمُ حدودِ الاعتمادِ **حاجزُ إنفاذٍ بعينِه**
 *      (`RPR-03` §٥)، وبلا المفردةِ تُكتب `CONFIG_PENDING` بلا بيانِ مرحلتِها
 *      **فتُقرأ كأنّها لا تحجب شيئًا أبدًا**.
 *
 * ◆ **والقديمُ لا يُمحى ليحلَّ الجديد**: المفرداتُ تتّسع، **والأسماءُ القديمةُ
 *   تبقى** حتى يُحكَم على كلِّ صفٍّ من جديد — فمحوُ مفردةٍ يدهس تاريخًا.
 *
 * ◆ **و`CONFIG_PENDING` بلا مرحلةٍ ممنوعة** — `MASTER_EXEC` §٣: *«ولكلِّ
 *   `CONFIG_PENDING` تُذكر صراحةً المرحلةُ التي تصير عندها حاجزًا»*. فعمودٌ
 *   جديدٌ `config_pending_stage` يفتح الموضعَ — **والقاعدةُ الصلبةُ تلي ملءَه**
 *   في `2027_12_15`، لأنَّ إضافتَها الآنَ تُرَدّ بأحدَ عشرَ صفًّا فارغًا (وهو
 *   خرقٌ قائمٌ صادق) أو تُغري بملءِ الفراغِ بأيِّ نصٍّ ليمرَّ الحاجب.
 *
 * التشغيل: php database/migrations/2027_12_14_amd01_blocker_degrees.php
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
/* ⛔ **عميلٌ `utf8mb4` قبل أيِّ `ALTER`** — والدرسُ محروق. */
$conn->set_charset('utf8mb4');

/* ① المفرداتُ تتّسع — والقديمُ يبقى ─────────────────────────────────────── */
$r = $conn->query("SHOW COLUMNS FROM `repair01_decisions` LIKE 'blocking_level'");
$cur = ($r && $r->num_rows) ? (string) $r->fetch_assoc()['Type'] : '';
if (strpos($cur, 'ENFORCEMENT_BLOCKER') !== false) {
    echo "  ◆ المفردةُ متّسعةٌ سلفًا\n";
} else {
    $WANT = "enum('STRUCTURAL_TARGET_BLOCKER','READY_TO_BUILD_BLOCKER','UAT_BLOCKER',"
          . "'GO_LIVE_BLOCKER','CONFIG_PENDING','NONE',"
          . "'STRUCTURAL_BLOCKER','BUILD_BLOCKER','ENFORCEMENT_BLOCKER')";
    $ok = $conn->query("ALTER TABLE `repair01_decisions`
        MODIFY COLUMN `blocking_level` $WANT NULL
        COMMENT 'درجات MASTER_EXEC §3 الست — والقديمة تبقى حتى يحكم على كل صف من جديد'");
    if (!$ok) { exit("✘ تعذّر توسيعُ `blocking_level`: {$conn->error}\n"); }
    echo "  ✔ اتّسعت المفردة — والستُّ حاضرةٌ ومنها `ENFORCEMENT_BLOCKER` التي كانت غائبة\n";
}

/* ② مرحلةُ القيمةِ المؤجَّلة ────────────────────────────────────────────── */
$r = $conn->query("SHOW COLUMNS FROM `repair01_decisions` LIKE 'config_pending_stage'");
if ($r && $r->num_rows) {
    echo "  ◆ `config_pending_stage` قائمٌ سلفًا\n";
} else {
    $ok = $conn->query("ALTER TABLE `repair01_decisions`
        ADD COLUMN `config_pending_stage` VARCHAR(190) NOT NULL DEFAULT ''
            COMMENT 'المرحلة التي تصير عندها القيمة المؤجلة حاجزا — MASTER_EXEC §3'
            AFTER `blocking_level`");
    if (!$ok) { exit("✘ تعذّرت إضافةُ `config_pending_stage`: {$conn->error}\n"); }
    echo "  ✔ أُضيف `config_pending_stage`\n";
}

/* ③ عمودُ حكمِ المراجعةِ العكسيّةِ السداسيّ — `AMD-01` المرحلة ٢ ──────────
     ستّةُ أحكام: `OPEN_VALID` · `ALREADY_DECIDED` · `SUPERSEDED` ·
     `CONFIG_PENDING` · `WRONG_BLOCKER_CLASS` · `CONFLICT`. */
$r = $conn->query("SHOW COLUMNS FROM `repair01_decisions` LIKE 'amd01_verdict'");
if ($r && $r->num_rows) {
    echo "  ◆ `amd01_verdict` قائمٌ سلفًا\n";
} else {
    $ok = $conn->query("ALTER TABLE `repair01_decisions`
        ADD COLUMN `amd01_verdict` ENUM('OPEN_VALID','ALREADY_DECIDED','SUPERSEDED',
            'CONFIG_PENDING','WRONG_BLOCKER_CLASS','CONFLICT') NULL
            COMMENT 'حكم المراجعة العكسية السداسي — AMD-01 المرحلة 2',
        ADD COLUMN `amd01_verdict_ref` VARCHAR(300) NOT NULL DEFAULT ''
            COMMENT 'مرجع الحكم — ولا حكم بلا مرجع'");
    if (!$ok) { exit("✘ تعذّرت إضافةُ `amd01_verdict`: {$conn->error}\n"); }
    echo "  ✔ أُضيف `amd01_verdict` بستّةِ أحكامٍ و`amd01_verdict_ref`\n";
}

/* ④ ⛔ **والقاعدتان الصلبتان لا تُضافان هنا** — والسببُ مقيسٌ لا مُقدَّر:
     أُضيفتا فرسبتا فورًا، لأنَّ أحدَ عشرَ صفًّا `CONFIG_PENDING` بلا مرحلةٍ
     مكتوبة. **وهذا رسوبٌ صادق**: القاعدةُ كشفت خرقًا قائمًا لا عطبًا فيها.
     ⇒ والقيمُ **أحكامُ المرحلةِ الثانيةِ لا محتوى هجرة**، فلا تُخترَع هنا
     لتمرَّ قاعدة. تُملأ بـ`amd01_phase2_decisions.php` ثمَّ تُقفل القاعدتان
     في `2027_12_15_amd01_decision_locks.php`.
     ⛔ **ولا يُقلب الترتيبُ**: قاعدةٌ تُضاف قبل أن يصدق محتواها إمّا تُرَدّ
        وإمّا تُغري بملءِ الفراغِ بأيِّ نصٍّ ليمرَّ الحاجب. */

$r = $conn->query("SELECT COALESCE(blocking_level,'(NULL)') b, COUNT(*) n
                     FROM repair01_decisions GROUP BY 1 ORDER BY n DESC");
echo "\n  ── درجاتُ الحجبِ بعد التوسيع ──\n";
while ($x = $r->fetch_assoc()) { printf("     %-28s %d\n", $x['b'], $x['n']); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));

echo "\n✔ الستُّ حاضرةٌ · والمواضعُ مفتوحةٌ — والقفلُ بعدَ صدقِ المحتوى لا قبلَه\n";
