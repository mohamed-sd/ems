<?php
/**
 * tools/repair01_edc_owners.php — مالكُ السطحِ الموروثِ من مجلَّدِه على القرص
 * ═══════════════════════════════════════════════════════════════════════════
 * **أمرُ المالك · البند 18**: «أيُّ `Surface` بلا `Domain` يجب أن يأخذ حكمًا
 * قبل `Release`. المهم: `No Orphan Surface`.» **والقرار ⑤**: «لا تخمّنْ مالكَها.»
 *
 * ⚠ **وما كان يعمي الاشتقاقَ في المحاولةِ الأولى**: `repair01_screen_registry`
 *   يخزّن **اسمَ الملفِّ وحدَه** (`admin_close.php`) لا مسارَه، فقاعدةُ «مالكُ
 *   المجلَّدِ الغالب» رأت `dirname()` يساوي نقطةً في السبعةِ والثلاثين كلِّها
 *   **فحكمت أنّها في الجذرِ بلا مجلَّد** — وهي في `Tickets/` و`movement/`
 *   و`Equipments/`. **والمجلَّدُ موجودٌ على القرصِ لا في السجلّ.**
 *   والتحليلُ الجنائيُّ كشفه لأنّه يحلّ المسارَ من الشجرةِ الحيّة.
 *
 * ◆ **وإشارتانِ لا واحدة**: المجلَّدُ يقترح، **والجداولُ التي يلمسها الملفُّ
 *   تؤكّد أو تنقض**. فملفٌ في `Tickets/` يكتب في `tickets` مالكُه البلاغاتُ
 *   يقينًا، وملفٌ في `Tickets/` يكتب في `fin_journal_entries` **يُرفع** لأنَّ
 *   الإشارتَين تتنازعان.
 *
 * ⛔ **ولا يُنسَب حيث تتنازع الإشارتان** — فالتنازعُ يُعلَن ولا يُحسم بترجيحٍ.
 *
 * التشغيل: php tools/repair01_edc_owners.php [--report]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$REPORT = in_array('--report', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

/* ═══ ① خريطةُ المجلَّدِ إلى الإدارة — **مقيسةٌ من السجلِّ لا مكتوبةٌ يدويًّا**
     فالمجلَّدُ الذي يملك أكثرُ أسطحِه إدارةً واحدةً بأغلبيّةٍ مطلقةٍ يقترحها. */
$idx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    if (substr($p->getFilename(), -4) !== '.php') { continue; }
    $s = strtr($p->getPathname(), DIRECTORY_SEPARATOR, '/');
    if (strpos($s, '/.git/') !== false || strpos($s, '/vendor/') !== false) { continue; }
    if (!isset($idx[$p->getFilename()])) { $idx[$p->getFilename()] = $s; }
}
$dirOf = function ($base) use ($idx, $ROOT) {
    if (!isset($idx[$base])) { return ''; }
    $rel = str_replace($ROOT . '/', '', $idx[$base]);
    $d = dirname($rel);
    return ($d === '.' ? '' : strtolower($d));
};

$byDir = array();
$q = $conn->query("SELECT screen_file, owner_code FROM repair01_screen_registry
                    WHERE on_disk = 1 AND COALESCE(owner_code,'') NOT IN ('', 'PLATFORM')");
while ($q && ($x = $q->fetch_assoc())) {
    $d = $dirOf(basename((string) $x['screen_file']));
    if ($d === '') { continue; }
    $o = (string) $x['owner_code'];
    if (!isset($byDir[$d])) { $byDir[$d] = array(); }
    $byDir[$d][$o] = isset($byDir[$d][$o]) ? $byDir[$d][$o] + 1 : 1;
}

/* ═══ ② خريطةُ الجدولِ إلى الإدارة — من مالكِ الأسطحِ التي تلمسه ═══════════ */
$TBL = array();
$q = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
while ($q && ($x = $q->fetch_row())) { if (strlen($x[0]) > 4) { $TBL[] = $x[0]; } }
/* بادئةُ الجدولِ تدلُّ على نطاقِه — وهي عُرفٌ مقيسٌ في هذا المستودع */
$PREF = array('tkt_' => 'DEP-10', 'ticket' => 'DEP-10', 'mnt_' => 'DEP-14', 'trp_' => 'DEP-15',
              'proc_' => 'DEP-16', 'wh_' => 'DEP-17', 'acc_' => 'DEP-05', 'tre_' => 'DEP-06',
              'fin_' => 'DEP-03', 'hr_' => 'DEP-07', 'gov_' => 'DEP-08', 'risk_' => 'DEP-09',
              'audit_' => 'IAF', 'iaf_' => 'IAF', 'equipment' => 'DEP-04', 'asset' => 'DEP-04',
              'timesheet' => 'DEP-11', 'movement' => 'DEP-11');

/* ═══ ③ الاشتقاق ══════════════════════════════════════════════════════════ */
$rows = array();
$q = $conn->query("SELECT screen_id, screen_file, ownership_verdict FROM repair01_screen_registry
                    WHERE COALESCE(owner_code,'') = '' AND on_disk = 1 ORDER BY screen_file");
while ($q && ($x = $q->fetch_assoc())) { $rows[] = $x; }

$done = 0; $left = array(); $tal = array();
foreach ($rows as $x) {
    $b = basename((string) $x['screen_file']);
    $d = $dirOf($b);
    /* ⓐ اقتراحُ المجلَّدِ بأغلبيّةٍ مطلقة */
    $sugDir = ''; $dirWhy = '';
    if ($d !== '' && isset($byDir[$d])) {
        $t = array_sum($byDir[$d]); arsort($byDir[$d]);
        $top = key($byDir[$d]); $c = current($byDir[$d]);
        if ($t >= 3 && $c * 2 > $t) { $sugDir = $top; $dirWhy = "مجلد $d بغلبة مطلقة $c من $t"; }
    }
    /* ⓑ تأكيدُ الجداولِ التي يلمسها */
    $sugTbl = ''; $tblWhy = '';
    if (isset($idx[$b])) {
        $src = (string) @file_get_contents($idx[$b]);
        $hit = array();
        foreach ($PREF as $p => $dep) {
            if (preg_match('~\b(FROM|INTO|UPDATE|JOIN)\s+`?' . preg_quote($p, '~') . '\w*~i', $src)) {
                $hit[$dep] = isset($hit[$dep]) ? $hit[$dep] + 1 : 1;
            }
        }
        if ($hit) { arsort($hit); $sugTbl = key($hit); $tblWhy = 'جداول ' . key($hit) . ' بعدد ' . current($hit); }
    }
    /* ⓒ الحكم */
    if ($sugDir !== '' && $sugTbl !== '' && $sugDir !== $sugTbl) {
        $left[] = array($b, "تنازعٌ: المجلد يقترح $sugDir والجداول تقترح $sugTbl");
        continue;
    }
    $own = ($sugDir !== '' ? $sugDir : $sugTbl);
    if ($own === '') { $left[] = array($b, 'لا مجلدَ غالبٌ ولا جدولَ دالّ'); continue; }
    $why = 'البند 18 — ' . ($dirWhy !== '' ? $dirWhy : '') . ($dirWhy !== '' && $tblWhy !== '' ? ' · مؤكَّدًا بـ' : '') . $tblWhy;
    $tal[$own] = isset($tal[$own]) ? $tal[$own] + 1 : 1;
    if ($REPORT) { $done++; continue; }
    if ($conn->query("UPDATE repair01_screen_registry
                         SET owner_code = '" . $e($own) . "',
                             verdict_rule = CONCAT(verdict_rule, ' | " . $e($why) . "')
                       WHERE screen_id = '" . $e($x['screen_id']) . "'")) { $done++; }
}

echo "\n═══ مالكُ السطحِ الموروثِ — البند 18 ═══\n";
echo ($REPORT ? "  وضعُ التقرير: يقرأ ولا يكتب\n" : "");
printf("  المقام %d · نُسب %d · يبقى للمالك %d\n\n", count($rows), $done, count($left));
arsort($tal);
foreach ($tal as $k => $c) { printf("     %-10s %d\n", $k, $c); }
if ($left) {
    echo "\n  ◆ يُرفع للمالك:\n";
    foreach ($left as $z) { printf("     · %-38s %s\n", $z[0], $z[1]); }
}
echo "\n────────────────────────────────────────────────────────────\n";
if (!$REPORT) {
    $n = (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry
                              WHERE COALESCE(owner_code,'') = '' AND on_disk = 1")->fetch_row()[0];
    printf("سطحٌ حيٌّ بلا مالكٍ الآن: %d\n", $n);
}
