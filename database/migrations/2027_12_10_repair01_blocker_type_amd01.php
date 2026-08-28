<?php
/**
 * 2027_12_10_repair01_blocker_type_amd01.php — مفرداتُ الحجبِ الثلاثُ (AMD-01 §ج)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما أمرت به وثيقةُ التعديل**: *«أضف عمود `Blocker_Type` بثلاث قيم: حاجزُ
 *   إنفاذ · قيمةٌ تُضبط · محسومٌ آليةً ومفتوحٌ قيمة. صنِّف الأحدَ عشرَ عليه —
 *   اثنان وتسعة»*.
 *
 * ◆ **وما قِستُه قبل التنفيذ — وهو تصحيحٌ للأمر**: العمودُ `blocker_type`
 *   **موجودٌ ومملوءٌ سلفًا** (‏`STRUCTURAL` 6 · `THRESHOLD` 12 · فارغٌ 107)،
 *   لكنّه **يجيب سؤالًا آخر**: «أبنيويٌّ هو أم عتبة؟» (‏محورُ `RPR-PATCH-01`
 *   الذي يحرسه `G0-11`). ومفرداتُ `AMD-01` تجيب سؤالًا ثالثًا: **«أيحجب هذا
 *   إنفاذَ `RPR-02`؟»**
 *   ⇒ فالمفرداتُ الثلاثُ **تُضاف إلى العمودِ نفسِه** كما أمر، **والأحدَ عشرَ
 *   وحدَهم يُعادُ تصنيفُهم عليها**؛ والسبعةُ المعتمَدةُ تبقى على المحورِ الأوّل.
 *   ⛔ **ولا يُمحى محورٌ ليحلَّ محورٌ** — فالسؤالان كلاهما يُسأل.
 *
 * ◆ **و`G0-11` يبقى بأسنانِه**: قاعدتُه — *«كلُّ `DEC-OPEN` مصنَّفٌ · وما هو
 *   قيمةٌ لا يحجب بنيويًّا»* — **لا تتغيّر**، وتتّسع مفردتُها وحدَها. وهذا هو
 *   الفرقُ بين **نصِّ القاعدةِ وقيمتِها**: تحديثُ المفردةِ ليس تخفيفَ حاجب.
 *
 * ◆ **وإعادةُ صياغةِ `DEC-OPEN-15`** كما نصَّت الوثيقةُ: آليّتُه **محسومةٌ سلفًا**
 *   بـ`DEC-WH-01` (‏`APPROVED` · التتبّعُ بحسبِ دليلِ الأصناف) **والمفتوحُ
 *   القيمُ لا الآلية** — فوسمُه `STRUCTURAL`/`READY_TO_BUILD_BLOCKER` **يجعله
 *   يُقرأ فجوةً معماريّةً وهو ليس كذلك**.
 *
 * التشغيل: php database/migrations/2027_12_10_repair01_blocker_type_amd01.php
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
/* ⛔ **عميلٌ `utf8mb4` قبل أيِّ `ALTER` يحمل مفرداتٍ عربيّة** — وإلّا كُتبت
     قيمُ الـ`ENUM` بترميزٍ آخرَ فلا يطابقها `UPDATE` بعدُ ولا `WHERE`. */
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };

/* ① توسيعُ المفردةِ — والقديمُ يبقى ────────────────────────────────────── */
$r = $conn->query("SHOW COLUMNS FROM repair01_decisions LIKE 'blocker_type'");
$cur = ($r && $r->num_rows) ? (string) $r->fetch_assoc()['Type'] : '';
$WANT = "enum('STRUCTURAL','THRESHOLD','حاجز إنفاذ','قيمة تضبط','محسوم آلية ومفتوح قيمة')";
if (mb_strpos($cur, 'حاجز إنفاذ') === false) {
    $ok = $conn->query("ALTER TABLE `repair01_decisions`
        MODIFY COLUMN `blocker_type` $WANT NULL
        COMMENT 'محوران: بنيوي/عتبة (RPR-PATCH-01) ومفردات AMD-01 الثلاث للاحد عشر'");
    if (!$ok) { exit("✘ تعذّر توسيعُ blocker_type: {$conn->error}\n"); }
    echo "  ✔ اتّسعت المفردة: blocker_type ⇐ خمسُ قيمٍ (‏محوران)\n";
} else {
    echo "  ◆ المفردةُ متّسعةٌ سلفًا\n";
}

/* ② تصنيفُ الأحدَ عشرَ — اثنان وثمانيةٌ وواحد ──────────────────────────── */
$MAP = array(
    /* أ · اثنان يحجبان الإنفاذ — ومؤجَّلان بقرارِ المالكِ لا بتقصير */
    'DEC-OPEN-01' => 'حاجز إنفاذ',
    'DEC-OPEN-02' => 'حاجز إنفاذ',
    /* ب · ثمانيةٌ قيمٌ تُضبط — تُبنى محرِّكاتُها ومواضعُ القيمِ تُقرأ من سجلِّ السياسات */
    'DEC-OPEN-04' => 'قيمة تضبط',
    'DEC-OPEN-05' => 'قيمة تضبط',
    'DEC-OPEN-06' => 'قيمة تضبط',
    'DEC-OPEN-07' => 'قيمة تضبط',
    'DEC-OPEN-08' => 'قيمة تضبط',
    'DEC-OPEN-09' => 'قيمة تضبط',
    'DEC-OPEN-10' => 'قيمة تضبط',
    'DEC-OPEN-11' => 'قيمة تضبط',
    /* ج · واحدٌ محسومٌ آليةً ومفتوحٌ قيمة */
    'DEC-OPEN-15' => 'محسوم آلية ومفتوح قيمة',
);
$moved = 0; $same = 0;
foreach ($MAP as $id => $val) {
    $r = $conn->query("SELECT blocker_type FROM repair01_decisions WHERE decision_id='" . $e($id) . "'");
    if (!$r || !$r->num_rows) { echo "  ✘ لا صفَّ: $id\n"; continue; }
    $was = (string) $r->fetch_row()[0];
    if ($was === $val) { $same++; continue; }
    $ok = $conn->query("UPDATE repair01_decisions SET blocker_type='" . $e($val) . "'
                         WHERE decision_id='" . $e($id) . "'");
    if (!$ok) { exit("✘ تعذّر تصنيفُ $id: {$conn->error}\n"); }
    $moved++;
    printf("  ✔ %-14s %-12s ⇐ %s\n", $id, $was, $val);
}

/* ③ إعادةُ صياغةِ DEC-OPEN-15 — «الآليةُ محسومةٌ والمطلوبُ قائمةُ الفئات» ── */
$Q15 = 'الآلية محسومة بـDEC-WH-01 (التتبع بحسب دليل الأصناف: Lot/Serial/Expiry) — '
     . 'والمطلوب قائمة الفئات لا الآلية.';
$WHY15 = 'AMD-01 ملحق §ج — لا يقرأ فجوة معمارية: المحسوم آلية والمفتوح قيمة';
$ok = $conn->query("UPDATE repair01_decisions
      SET question = '" . $e($Q15) . "',
          blocking_reason = '" . $e($WHY15) . "',
          blocking_level = 'CONFIG_PENDING'
    WHERE decision_id = 'DEC-OPEN-15'");
if (!$ok) { exit("✘ تعذّرت إعادةُ صياغةِ DEC-OPEN-15: {$conn->error}\n"); }
echo "  ✔ أُعيدت صياغةُ DEC-OPEN-15 · وحجبُه CONFIG_PENDING لا READY_TO_BUILD_BLOCKER\n";

/* ④ الحصيلةُ — بعدٍّ لا بدعوى ──────────────────────────────────────────── */
$r = $conn->query("SELECT COALESCE(blocker_type,'(فارغ)') bt, COUNT(*) n
                     FROM repair01_decisions WHERE decision_id LIKE 'DEC-OPEN-%'
                    GROUP BY 1 ORDER BY n DESC");
echo "\n  ── تصنيفُ الـ18 `DEC-OPEN` بعد التنفيذ ──\n";
while ($x = $r->fetch_assoc()) { printf("     %-26s %d\n", $x['bt'], $x['n']); }

require_once __DIR__ . '/_ledger.php';
$ms = (int) round((microtime(true) - $t0) * 1000);
ems_migration_recorded(__FILE__, $conn, $ms);

echo "\n✔ AMD-01 §ج: نُقل $moved · مطابقٌ سلفًا $same\n";
