<?php
/**
 * tools/govui_field_rename_triage.php — أسماءُ الحقولِ التي أعادت الحزمةُ تسميتَها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السؤال**: أيُّ حقلٍ **غيَّرت الحزمةُ الحاكمةُ الجديدةُ اسمَه**، وما زال
 *   السطحُ يعرض **الاسمَ القديمَ حرفًا**؟ فذاك رَنَمٌ مطلوبٌ بنصِّ §7
 *   («اسمٌ قديمٌ استُبدل في الملفاتِ الجديدة» لا يُقبل في الواجهة)،
 *   **وإثباتُه مقيسٌ لا مُخمَّن**: القديمُ في `_pkg_backup_20260901/09` والجديدُ
 *   في `09` المنصوب، والمعروضُ في أثرِ السطح.
 *
 * ◆ **والمزاوجةُ بالموضعِ لا بالاسم**: صفوفُ `02_تتبع_الحقول` لم تُضَف ولم
 *   تُحذف (فرقُ الحزمةِ: **صفرُ صفٍّ زائدٍ أو ناقص**) — فالحقلُ يُطابَق
 *   بـ`(requirement_id, seq)`، وتغيُّرُ `field_name` وحدَه هو إعادةُ التسمية.
 *
 * ◆ ⛔ **ولا يُقترح رَنَمٌ إلّا إذا كان القديمُ ظاهرًا في الأثرِ حرفًا**
 *   (مطابقةُ نصٍّ مطبَّعٍ على وسمٍ مُصيَّر) — فلو لم يظهر القديمُ فالمسألةُ
 *   حقلٌ غائبٌ لا اسمٌ متقادم، وهي جبهةٌ أخرى.
 *
 * ⛔ ولا يكتب شيئًا — يُخرج قائمةَ فرزٍ بمواضعِها في الملفّات.
 * التشغيل: php tools/govui_field_rename_triage.php [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/lib/xlsx_io.php';
require_once $ROOT . '/tools/lib/rpr02a_guide.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$MD = in_array('--md', $argv, true);

/** صفُّ تتبّعِ الحقولِ ⇒ [requirement_id, seq] => field_name */
$read = function ($path) {
    $wb = xlsx_read($path);
    $rows = isset($wb['02_تتبع_الحقول']) ? $wb['02_تتبع_الحقول'] : array();
    ksort($rows);
    $out = array();
    foreach ($rows as $ri => $r) {
        if ($ri <= 3) { continue; }
        ksort($r);
        $cell = function ($i) use ($r) { return isset($r[$i]) ? trim((string) $r[$i]) : ''; };
        $fn = $cell(5);
        if ($fn === '' || $fn === 'اسم الحقل') { continue; }
        $out[$cell(0) . '|' . $cell(4)] = $fn;
    }
    return $out;
};
$oldF = $read($ROOT . '/docs/REPAIR01_20260823/_pkg_backup_20260901/09 · السجلات المؤسسية والقرارات.xlsx');
$newF = $read($ROOT . '/docs/REPAIR01_20260823/09 · السجلات المؤسسية والقرارات.xlsx');
printf("خانات تتبّعِ الحقول: القديم %d · الجديد %d\n", count($oldF), count($newF));

$ren = array();
foreach ($newF as $k => $nv) {
    if (!isset($oldF[$k])) { continue; }
    if (rpr02a_nz($oldF[$k]) === rpr02a_nz($nv)) { continue; }
    list($req, $seq) = explode('|', $k, 2);
    $ren[] = array('req' => $req, 'seq' => $seq, 'old' => $oldF[$k], 'new' => $nv);
}
printf("حقولٌ أعادت الحزمةُ تسميتَها: **%d**\n", count($ren));

/* السطحُ المبنيُّ لكلِّ متطلَّبٍ — من جسرِ القياسِ نفسِه */
$scr = array();
$r = $conn->query("SELECT f.requirement_id, f.screen_id, f.artifact_path, s.canonical_label_ar
                     FROM repair01_field_measure f
                     LEFT JOIN repair01_screen_registry s ON s.screen_id = f.screen_id");
while ($x = $r->fetch_assoc()) { $scr[$x['requirement_id']][] = $x; }

$hit = array(); $noScreen = 0; $notShown = 0;
foreach ($ren as $x) {
    if (!isset($scr[$x['req']])) { $noScreen++; continue; }
    foreach ($scr[$x['req']] as $s) {
        $path = $ROOT . '/' . $s['artifact_path'];
        if (!is_file($path)) { continue; }
        $body = (string) file_get_contents($path);
        /* الاسمُ القديمُ ظاهرًا حرفًا في الأثر — وسمًا لا تعليقًا */
        if (mb_strpos($body, $x['old']) === false) { $notShown++; continue; }
        if (mb_strpos($body, $x['new']) !== false) { continue; }   /* الجديدُ حاضرٌ سلفًا */
        $hit[] = array_merge($x, array('screen_id' => $s['screen_id'],
            'path' => $s['artifact_path'], 'name' => (string) $s['canonical_label_ar']));
    }
}
printf("منها **قديمُها ظاهرٌ في الأثرِ والجديدُ غائب: %d** · بلا سطحٍ مجسور %d · قديمُها غيرُ ظاهر %d\n\n",
    count($hit), $noScreen, $notShown);
$byPath = array();
foreach ($hit as $h) { $byPath[$h['path']][] = $h; }
ksort($byPath);
foreach ($byPath as $p => $list) {
    printf("── %s (%s)\n", $p, $list[0]['screen_id']);
    foreach ($list as $h) { printf("     «%s»  ⇒  «%s»   [%s·%s]\n", $h['old'], $h['new'], $h['req'], $h['seq']); }
}
file_put_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_FIELD_RENAMES.json',
    json_encode(array('renamed_in_package' => count($ren), 'actionable' => $hit),
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\n⇐ docs/REPAIR01_20260823/GOVUI_FIELD_RENAMES.json\n";
