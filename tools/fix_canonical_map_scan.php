<?php
/**
 * tools/fix_canonical_map_scan.php — رمزُ الوثيقةِ ⇄ مسارُ القرصِ ⇄ مالكٌ واحد
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0457 (§٨-٤ الأسطول) · INJ-0476 (§١١-٤ الموارد البشرية)
 *
 * ── العلّة ────────────────────────────────────────────────────────────────
 * تعلن `INJAZ-FRD-01` رمزَ ملفٍّ لكلِّ شاشة (`equip_models.php`)، والمنفَّذُ على
 * القرصِ اسمٌ آخرُ في مجلدٍ آخر (`Equipments/fleet_models.php`). فلا يُقفل
 * «ربطُ الأفعالِ مكتمل» آليًّا: كلُّ مطابقةٍ بين حكمِ الوثيقةِ وشاشةِ النظامِ
 * تحتاج اجتهادًا يدويًّا — ويسهل ازدواجُ الملكيةِ بين إدارتين.
 *
 * ── والسجلُّ الواحدُ الذي يحسم ──────────────────────────────────────────────
 * `nav09_file_map` **هو** سجلُّ الأسماءِ المعتمدة: `canonical_file` ⇄ `real_path`
 * ⇄ `owner_dept`. وإنشاءُ سجلٍّ ثانٍ لِما له سجلٌّ **هو بعينِه عيبُ «مخزنانِ
 * لحقيقةٍ واحدة»** الذي تعالجه هذه الحملة — فلا يُنشأ.
 *
 * ── والفاحصُ عامٌّ لا لبندين ───────────────────────────────────────────────
 * يقرأ **كلَّ** جداولِ «§N-٤ شاشاتُها وأفعالُها» في الوثيقةِ المقروءة، فيحرس
 * الإداراتِ كلَّها لا الأسطولَ والمواردَ وحدَهما.
 *
 * ◆ ويرسب على ثلاثٍ: رمزٌ بلا صفّ · صفٌّ بمسارٍ لا وجودَ له على القرص ·
 *   رمزٌ بأكثرَ من مالك.
 *
 * التشغيل: php tools/fix_canonical_map_scan.php [--json] [--dept=8]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');

/** أرقامُ الإداراتِ بالعربيةِ الهنديةِ كما تكتبها الوثيقة. */
function cn_ar2num($s)
{
    $map = array('٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                 '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9');
    return strtr($s, $map);
}

/* ── ① رموزُ الوثيقة: كلُّ جدولِ «§N-٤ شاشاتُها وأفعالُها» ───────────────── */
$DOC = $ROOT . '/docs/update0012/extracted/FRD-v5.md';
if (!is_file($DOC)) { exit("الوثيقةُ المقروءةُ مفقودة: {$DOC}\n"); }
$lines = file($DOC, FILE_IGNORE_NEW_LINES);
$sections = array();   // deptNo ⇒ [ [title, file], … ]
$cur = null;
foreach ($lines as $ln) {
    if (preg_match('~^##\s*▐\s*([٠-٩]+)-٤\s+شاشاتُها~u', $ln, $m)) {
        $cur = (int) cn_ar2num($m[1]);
        if (!isset($sections[$cur])) { $sections[$cur] = array(); }
        continue;
    }
    if ($cur === null) { continue; }
    if (preg_match('~^##~u', $ln)) { $cur = null; continue; }
    if (!preg_match('~^\|\s*(.+?)\s*\|\s*([A-Za-z0-9_]+\.php)\s*\|~u', $ln, $m)) { continue; }
    $sections[$cur][] = array('title' => trim($m[1]), 'file' => trim($m[2]));
}

/* ── ② السجلُّ الواحد ────────────────────────────────────────────────────── */
$MAP = array();
$r = $conn->query('SELECT canonical_file, real_path, owner_dept, state FROM nav09_file_map');
if ($r === false) { exit("تعذّرت قراءةُ nav09_file_map: " . $conn->error . "\n"); }
while ($x = $r->fetch_assoc()) {
    $k = strtolower(trim((string) $x['canonical_file']));
    if (!isset($MAP[$k])) { $MAP[$k] = array(); }
    $MAP[$k][] = $x;
}

/* ── ③ الحكم ─────────────────────────────────────────────────────────────── */
$only = 0;
foreach ($argv as $a) { if (preg_match('~^--dept=(\d+)$~', $a, $m)) { $only = (int) $m[1]; } }

$gaps = array(); $okN = 0; $totalN = 0;
ksort($sections);
foreach ($sections as $deptNo => $rows) {
    if ($only && $deptNo !== $only) { continue; }
    foreach ($rows as $row) {
        $totalN++;
        $k = strtolower($row['file']);
        if (!isset($MAP[$k])) {
            $gaps[] = array('dept' => $deptNo, 'file' => $row['file'], 'title' => $row['title'],
                            'why' => 'no_row', 'detail' => 'لا صفَّ في السجلّ');
            continue;
        }
        if (count($MAP[$k]) > 1) {
            $owners = array();
            foreach ($MAP[$k] as $c) { $owners[] = (string) $c['owner_dept']; }
            $gaps[] = array('dept' => $deptNo, 'file' => $row['file'], 'title' => $row['title'],
                            'why' => 'many_owners', 'detail' => implode(' · ', array_unique($owners)));
            continue;
        }
        $rowMap = $MAP[$k][0];
        $rp = trim((string) $rowMap['real_path']);
        if ($rp === '') {
            $gaps[] = array('dept' => $deptNo, 'file' => $row['file'], 'title' => $row['title'],
                            'why' => 'no_path', 'detail' => 'صفٌّ بلا مسار');
            continue;
        }
        if (!is_file($ROOT . '/' . $rp)) {
            $gaps[] = array('dept' => $deptNo, 'file' => $row['file'], 'title' => $row['title'],
                            'why' => 'dead_path', 'detail' => $rp);
            continue;
        }
        if (trim((string) $rowMap['owner_dept']) === '') {
            $gaps[] = array('dept' => $deptNo, 'file' => $row['file'], 'title' => $row['title'],
                            'why' => 'no_owner', 'detail' => $rp);
            continue;
        }
        $okN++;
    }
}

echo "══ رمزُ الوثيقةِ ⇄ مسارُ القرصِ ⇄ مالكٌ واحد ══\n\n";
echo '  جداولُ «§N-٤» المقروءة: ' . count($sections) . " إدارةً\n";
echo '  الرموزُ المقيسة: ' . $totalN . ' · مطابقٌ تامًّا: ' . $okN . ' · بلا مطابقة: ' . count($gaps) . "\n\n";
if (!$gaps) {
    echo "  ✔ **صفرُ رمزٍ بلا مطابقة** — لكلِّ رمزٍ صفٌّ ومسارٌ موجودٌ ومالكٌ واحد\n";
} else {
    $byWhy = array();
    foreach ($gaps as $g) { $byWhy[$g['why']][] = $g; }
    $lbl = array('no_row' => 'لا صفَّ في السجلّ', 'no_path' => 'صفٌّ بلا مسار',
                 'dead_path' => 'مسارٌ لا وجودَ له على القرص', 'no_owner' => 'صفٌّ بلا مالك',
                 'many_owners' => 'رمزٌ بأكثرَ من مالك');
    foreach ($byWhy as $why => $list) {
        echo '  ✘ ' . (isset($lbl[$why]) ? $lbl[$why] : $why) . ' — ' . count($list) . "\n";
        foreach ($list as $g) {
            echo '      §' . $g['dept'] . '-٤ · ' . str_pad($g['file'], 26)
               . ' ' . $g['title'] . ($g['detail'] !== '' ? '  ⟵ ' . $g['detail'] : '') . "\n";
        }
    }
}
if (in_array('--json', $argv, true)) {
    file_put_contents($ROOT . '/docs/fix_progress/canonical_map_scan.json',
        json_encode(array('total' => $totalN, 'ok' => $okN, 'gaps' => $gaps),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\n  · كُتب: docs/fix_progress/canonical_map_scan.json\n";
}
exit(empty($gaps) ? 0 : 1);
