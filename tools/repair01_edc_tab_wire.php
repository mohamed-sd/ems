<?php
/**
 * tools/repair01_edc_tab_wire.php — وصلُ التبويباتِ بالشريطِ القائمِ لا اختراعُه
 * ═══════════════════════════════════════════════════════════════════════════
 * **حكمُ المالك ⑦**: تُبنى التبويباتُ المحكومُ بدمجِها ⛔ «**ولا نضحّي بالصلاحيةِ
 * من أجلِ سايدبارٍ أنظف**».
 *
 * ◆ **والعُرفُ مبنيٌّ سلفًا**: `includes/entity_tabs.php` شريطُ رحلةِ الكيانِ
 *   الموحَّد (UXW-01 §8-2)، **وفيه تسجيلاتٌ للأزواجِ نفسِها** التي حكمتُ عليها
 *   بـ`MERGE_READY`. ⇒ **فالمهمّةُ وصلٌ لا اختراع**، و**بناءٌ يخترع نمطًا ثالثًا
 *   أسوأُ من دَينٍ مُعلَن**.
 *
 * ⛔ **ولا يُخفى بندٌ من الملاحة**: الشريطُ **يضيف طريقًا** ولا يسدُّ طريقًا.
 *   فالوصولُ لا ينقص لأحد، **وإخفاءُ البندِ قرارُ ملاحةٍ منفصلٌ بقرارِ المالك**.
 *
 * ◆ والشريطُ **عرضٌ محضٌ**: «لا معاملَ يُمرَّر ولا منطقَ يُلمس — والصلاحيةُ على
 *   الوجهةِ يفحصها حارسُها هي» (‏نصُّ رأسِ المكوّن). فحقنُه لا يوسّع وصولًا.
 *
 * التشغيل: php tools/repair01_edc_tab_wire.php [--apply]
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
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

require_once $ROOT . '/includes/entity_tabs.php';
$REG = function_exists('ems_entity_tabs_registry') ? ems_entity_tabs_registry() : array();
/* المسارُ ⇐ الكيانُ الذي يسجّله — من السجلِّ لا من تخمين */
$owned = array();
foreach ($REG as $ent => $d) {
    foreach ((isset($d['tabs']) ? $d['tabs'] : array()) as $lab => $rt) {
        /* ⚠ **الوسيطُ الثاني اسمُ التبويبِ لا مسارُه**: `$isActive = ($name === $active)`.
             ونسختي الأولى مرَّرت المسارَ **فما نشط تبويبٌ قطّ** — وكان يمرُّ صامتًا. */
        if ($rt !== '') { $owned[strtolower($rt)] = array('ent' => $ent, 'tab' => $lab); }
    }
}
printf("كياناتٌ مسجَّلةٌ في الشريط: %d · مساراتٌ يغطّيها: %d\n", count($REG), count($owned));

$idx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    if (substr($p->getFilename(), -4) !== '.php') { continue; }
    $s = strtr($p->getPathname(), DIRECTORY_SEPARATOR, '/');
    if (strpos($s, '/.git/') !== false || strpos($s, '/vendor/') !== false) { continue; }
    if (!isset($idx[$p->getFilename()])) { $idx[$p->getFilename()] = $s; }
}

/* الأزواجُ المحكومُ عليها `MERGE_READY` — من المخزنِ لا من قائمةٍ مكتوبة */
$pairs = array();
$q = $conn->query("SELECT t.screen_file AS child, t.route AS croute, p.screen_file AS parent, p.route AS proute
                     FROM repair01_screen_registry t
                     JOIN repair01_screen_registry p ON p.screen_id = t.parent_screen_id
                    WHERE t.ownership_verdict = 'TAB_CHILD' AND t.on_disk = 1
                      AND t.parent_rule LIKE 'MERGE\\_READY%'");
while ($q && ($x = $q->fetch_assoc())) { $pairs[] = $x; }

$need = array(); $have = 0; $noEnt = array();
foreach ($pairs as $x) {
    foreach (array(array($x['child'], $x['croute']), array($x['parent'], $x['proute'])) as $s) {
        list($b, $rt) = $s;
        if (!isset($idx[$b])) { continue; }
        $o = isset($owned[strtolower((string) $rt)]) ? $owned[strtolower((string) $rt)] : null;
        $ent = $o ? $o['ent'] : '';
        if ($ent === '') { $noEnt[$rt] = true; continue; }
        $c = (string) @file_get_contents($idx[$b]);
        if (strpos($c, 'ems_entity_tabs') !== false) { $have++; continue; }
        $need[$rt] = array('file' => $idx[$b], 'ent' => $ent, 'tab' => $o['tab'], 'base' => $b);
    }
}
printf("أزواجٌ محكومٌ بدمجِها: %d · يحمل الشريطَ سلفًا: %d · **يحتاج وصلًا: %d**\n",
    count($pairs), $have, count($need));
if ($noEnt) {
    printf("\n◆ **مسارٌ محكومٌ بالدمجِ ولا كيانَ يسجّله في الشريط: %d**\n", count($noEnt));
    echo "   ⛔ ولا يُخترَع له كيانٌ هنا — التسجيلُ في §8-2 قرارُ بنيةٍ لا حقنُ سطر:\n";
    foreach (array_keys($noEnt) as $r) { echo "     · $r\n"; }
}
echo "\n";
foreach ($need as $rt => $d) { printf("  يحتاج  %-34s كيان=%s\n", $rt, $d['ent']); }

if (!$APPLY) { echo "\n◆ عرضٌ فقط — أضِف `--apply`\n"; exit(0); }

/* ── الوصل: سطران بعد القشرةِ مباشرةً ─────────────────────────────────────── */
$done = 0;
foreach ($need as $rt => $d) {
    $f = $d['file'];
    $c = (string) file_get_contents($f);
    /* الموضع: بعدَ آخرِ `require_once` في رأسِ الملفِّ — فالشريطُ عرضٌ يتبع القشرة */
    if (!preg_match_all('~^\s*(?:require|include)(?:_once)?\s*[^;]+;\s*$~mi', $c, $m, PREG_OFFSET_CAPTURE)) {
        echo "  ✘ {$d['base']} — لا موضعَ إدراجٍ واضح\n"; continue;
    }
    $last = end($m[0]);
    $at   = $last[1] + strlen($last[0]);
    $rel  = str_repeat('/..', substr_count(substr(strtr($f, DIRECTORY_SEPARATOR, '/'),
                strlen(strtr($ROOT, DIRECTORY_SEPARATOR, '/')) + 1), '/'));
    if (!is_file(dirname($f) . $rel . '/includes/entity_tabs.php')) {
        echo "  ✘ {$d['base']} — مسارُ المكوّنِ لا يحلّ\n"; continue;
    }
    $ins = "\n\n/* شريط رحلة الكيان الموحد — UXW-01 8-2 */\n"
         . "require_once __DIR__ . '" . $rel . "/includes/entity_tabs.php';";
    $out = substr($c, 0, $at) . $ins . substr($c, $at);
    /* والنداءُ عند أوّلِ مخرَجٍ مُصيَّر */
    if (strpos($out, 'ems_entity_tabs_for') === false) {
        $out = preg_replace('~(
\s*<div class="(?:main|phead)\b)~',
            "
<?php /* شريط رحلة الكيان — بالمسار لا بالاسم، فالاسم يسكن السجل وحده */ "
          . "echo ems_entity_tabs_for('" . $rt . "'); ?>$1", $out, 1);
    }
    file_put_contents($f, $out);
    $chk = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" -l ' . escapeshellarg($f) . ' 2>&1', $chk, $rc);
    if ($rc !== 0) { file_put_contents($f, $c); echo "  ✘ رُدَّ {$d['base']} — " . implode(' ', $chk) . "\n"; continue; }
    $done++;
    printf("  ✔ %-34s كيان=%s\n", $rt, $d['ent']);
}
printf("\nوُصل: %d من %d\n", $done, count($need));
