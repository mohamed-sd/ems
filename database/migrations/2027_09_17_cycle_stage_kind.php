<?php
/**
 * 2027_09_17_cycle_stage_kind.php
 *   للشاشةِ العابرةِ مرحلةٌ قانونيةٌ في بيتِها وقراءةٌ سياقيةٌ في غيرِه — NF-13
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العيبُ الذي كشفته المرحلةُ ①**: حين صُحِّحت أسماءُ دفترِ الدورةِ إلى
 *   مساراتِها الحقيقية، صار للمسارِ الواحدِ **عدةُ صفوفِ دورةٍ بمراحلَ متناقضة**
 *   — `chats/index.php` في ستَّ عشرةَ إدارة، و`tax_invoices.php` في ثلاث.
 *   فانخفضت تغطيةُ الاشتقاقِ من ٢٥٥ إلى ٢٤٥ لأن التناقضَ يُخرج الرابطَ من
 *   «قابلٍ للاشتقاقِ يقينًا» إلى «له صفٌّ لكن متضارب».
 *
 * ◆ **ولا يُعالَج بشدِّ السقّاطة**: الانخفاضُ **أثرُ تغييري أنا**، وشدُّ خطِّ
 *   الأساسِ عليه يُخفي ما صنعتُه. والعلاجُ هو حكمُ NF-13 نفسُه بنصِّه:
 *   «للشاشةِ العابرةِ **مرحلةٌ قانونيةٌ في بيتِها وقراءةٌ سياقيةٌ في غيرِه**».
 *
 * ◆ **فيُضاف `stage_kind`**: `canonical` للصفِّ في بيتِ الشاشة، و`contextual`
 *   لبقيةِ الإداراتِ التي تقرؤها. والبيتُ يُحدَّد من `gov_screen_path_map.owner_dept`
 *   — وهو حكمٌ بشريٌّ منقولٌ من وثائقِ المواءمة، **لا ترجيحٌ بالأغلبية**.
 *
 * ◆ **وصفرُ فقد**: لا صفَّ يُحذف — يُوسَم فقط. والرجوعُ يُسقط العمود.
 *
 * التشغيل:  php database/migrations/2027_09_17_cycle_stage_kind.php
 * الرجوع :  php database/migrations/2027_09_17_cycle_stage_kind.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$has = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_screen_cycle'
                        AND COLUMN_NAME='stage_kind'");
$hasCol = $has ? (int) $has->fetch_row()[0] : 0;

if (in_array('--revert', $argv, true)) {
    if ($hasCol) { $conn->query("ALTER TABLE `gov_screen_cycle` DROP COLUMN `stage_kind`"); }
    echo "↺ أُسقط العمودُ `stage_kind` — ولا صفَّ فُقد\n";
    exit(0);
}

if (!$hasCol) {
    $ok = $conn->query("ALTER TABLE `gov_screen_cycle`
        ADD COLUMN `stage_kind` ENUM('canonical','contextual') NOT NULL DEFAULT 'canonical'
        COMMENT 'NF-13 — canonical: مرحلةٌ قانونيةٌ في بيتِ الشاشة · contextual: قراءةٌ سياقيةٌ في إدارةٍ أخرى'");
    if (!$ok) { exit("✘ تعذّرت الإضافة: {$conn->error}\n"); }
    echo "① أُضيف العمودُ `stage_kind` (الافتراضُ canonical — فلا يتغيّر حكمُ صفٍّ بلا سبب)\n";
} else {
    echo "① العمودُ قائمٌ سلفًا\n";
}

/* ── ② الوسمُ بالحكمِ المكتوب: البيتُ من سجلِّ المصالحة ─────────────────── */
$conn->query("UPDATE `gov_screen_cycle` SET `stage_kind` = 'canonical'");
$marked = 0;
$q = $conn->query(
  "SELECT c.id, c.dept_name, c.screen_file, m.owner_dept
     FROM `gov_screen_cycle` c
     JOIN `gov_screen_path_map` m ON m.real_route = c.screen_file
    WHERE m.real_route IS NOT NULL AND m.owner_dept IS NOT NULL");
$upd = $conn->prepare("UPDATE `gov_screen_cycle` SET `stage_kind` = 'contextual' WHERE `id` = ?");
while ($q && $r = $q->fetch_assoc()) {
    if ($r['dept_name'] === $r['owner_dept']) { continue; }     /* هذا هو البيت */
    $upd->bind_param('i', $r['id']);
    if ($upd->execute() && $upd->affected_rows) { $marked++; }
}
$upd->close();
printf("② وُسِم %d صفًّا قراءةً سياقيةً — والبيتُ يبقى canonical\n", $marked);

/* ── ③ ما بقي متضاربًا بلا حكمٍ مكتوب — يُعلَن ولا يُخمَّن ─────────────── */
$q = $conn->query(
  "SELECT `screen_file`, COUNT(*) n, COUNT(DISTINCT `stage_name`) s
     FROM `gov_screen_cycle` WHERE `stage_kind` = 'canonical'
    GROUP BY `screen_file` HAVING n > 1 AND s > 1 ORDER BY n DESC LIMIT 12");
$rest = array();
while ($q && $r = $q->fetch_assoc()) { $rest[] = $r['screen_file'] . " ({$r['n']}×{$r['s']})"; }
printf("③ مسارٌ ما يزال بأكثرَ من مرحلةٍ قانونيةٍ متمايزة: %d %s\n",
       count($rest), $rest ? '— ' . implode(' · ', array_slice($rest, 0, 6)) : '');
echo "   ◆ وهذه **سابقةٌ لهذه الجولةِ ولم تصنعْها**: تُحسم بحكمِ مالكٍ لكلِّ مسار\n";
echo "     في جولةِ إدارتِه — ولا تُخمَّن هنا بترجيحِ أغلبية.\n";

ems_migration_recorded(__FILE__, $conn, 0);
