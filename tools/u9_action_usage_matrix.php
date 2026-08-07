<?php
/**
 * tools/u9_action_usage_matrix.php — مصفوفةُ استعمالِ رموزِ الأفعال (update0009)
 * ═══════════════════════════════════════════════════════════════════════════
 * الخطوةُ ① من خطةِ ترحيلِ الرموز (ملفُّ التتبع · الورقةُ 08) وشرطُ البوابةِ الحية
 * (الورقةُ 15): «حصرُ كلِّ موضعٍ يقرأ الرمزَ أو يكتبه: الكودُ والقاموسُ والتقارير».
 * لا يغيّر شيئًا — قياسٌ خالص:
 *   ① رموزُ القاموس (nav09_action_map · 242) تُمسح في كل PHP حيٍّ كسلاسلَ حرفية.
 *   ② رموزُ الأفعال الحية (actions.action_code) تُقارن عكسيًّا بالقاموس — فحص ⑧.
 *   ③ المخرج: docs/update0009/ACTION_USAGE_MATRIX.csv + ملخصٌ حكمًا لكل صنف.
 *
 * php tools/u9_action_usage_matrix.php [--csv=path]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

$csvPath = $ROOT . '/docs/update0009/ACTION_USAGE_MATRIX.csv';
foreach ($argv as $a) { if (strpos($a, '--csv=') === 0) { $csvPath = substr($a, 6); } }

/* ── القاموس والسجل الحي ─────────────────────────────────────────────────── */
$dict = array(); // code => ['state'=>, 'live'=>, 'screen'=>]
$r = mysqli_query($conn, "SELECT canonical_code, state, live_code, screen_title FROM nav09_action_map");
while ($x = mysqli_fetch_assoc($r)) {
    $dict[$x['canonical_code']] = array('state' => $x['state'], 'live' => (string) $x['live_code'], 'screen' => $x['screen_title']);
}
$liveReg = array(); // action_code => handler_path
$r = mysqli_query($conn, "SELECT action_code, handler_path, active FROM actions");
while ($x = mysqli_fetch_assoc($r)) { $liveReg[$x['action_code']] = array('path' => (string) $x['handler_path'], 'active' => (int) $x['active']); }

/* ── مسح الكود: كل السلاسل الحرفية بنمط رمزٍ منقوط في PHP الحي ──────────── */
$EXCLUDE = array('vendor', 'node_modules', '.git', '.claude', '.ssdiff', 'storage', 'docs', 'assets');
$targets = array_flip(array_merge(array_keys($dict), array_keys($liveReg)));
$hits = array(); // code => array(file => count)
$stack = array($ROOT);
$scanned = 0;
while ($stack) {
    $dir = array_pop($stack);
    $h = opendir($dir);
    if (!$h) { continue; }
    while (($e = readdir($h)) !== false) {
        if ($e === '.' || $e === '..') { continue; }
        $p = $dir . '/' . $e;
        if (is_dir($p)) {
            if ($dir === $ROOT && in_array($e, $EXCLUDE, true)) { continue; }
            $stack[] = $p;
            continue;
        }
        if (substr($e, -4) !== '.php') { continue; }
        $src = file_get_contents($p);
        if ($src === false) { continue; }
        $scanned++;
        if (!preg_match_all("/['\"]([a-z][a-z0-9_]*(?:\\.[a-z][a-z0-9_]*){1,3})['\"]/", $src, $m)) { continue; }
        $rel = substr(str_replace('\\', '/', $p), strlen($ROOT) + 1);
        foreach ($m[1] as $lit) {
            if (!isset($targets[$lit])) { continue; }
            if (!isset($hits[$lit])) { $hits[$lit] = array(); }
            $hits[$lit][$rel] = isset($hits[$lit][$rel]) ? $hits[$lit][$rel] + 1 : 1;
        }
    }
    closedir($h);
}

/* ── التصنيف والمخرج ─────────────────────────────────────────────────────── */
@mkdir(dirname($csvPath), 0777, true);
$fh = fopen($csvPath, 'w');
fwrite($fh, "\xEF\xBB\xBF"); // BOM لعربية Excel
fputcsv($fh, array('code', 'kind', 'state', 'live_code', 'screen', 'code_occurrences', 'files', 'verdict'));

$cnt = array('bound' => 0, 'used_unbound' => 0, 'dict_only' => 0);
foreach ($dict as $code => $d) {
    $occ = isset($hits[$code]) ? array_sum($hits[$code]) : 0;
    $files = isset($hits[$code]) ? implode(' | ', array_keys($hits[$code])) : '';
    if ($d['state'] === 'alias' && $d['live'] !== '') { $v = 'bound — مربوطٌ بمعالجٍ حي'; $cnt['bound']++; }
    elseif ($occ > 0) { $v = 'used_unbound — يظهر في الكودِ وربطُه غيرُ موثَّق'; $cnt['used_unbound']++; }
    else { $v = 'dictionary_only — قاموسٌ بلا أثرٍ في الكود (معلَّق)'; $cnt['dict_only']++; }
    fputcsv($fh, array($code, 'dictionary', $d['state'], $d['live'], $d['screen'], $occ, $files, $v));
}

/* فحص ⑧ عكسيًّا: رمزٌ حيٌّ في actions لا يعرفه القاموس (لا كرمزٍ ولا كlive_code) */
$dictLive = array();
foreach ($dict as $d) { if ($d['live'] !== '') { $dictLive[$d['live']] = 1; } }
$orphan = 0;
foreach ($liveReg as $code => $lr) {
    if (isset($dict[$code]) || isset($dictLive[$code])) { continue; }
    $occ = isset($hits[$code]) ? array_sum($hits[$code]) : 0;
    $files = isset($hits[$code]) ? implode(' | ', array_keys($hits[$code])) : '';
    fputcsv($fh, array($code, 'live_registry', $lr['active'] ? 'active' : 'inactive', '', $lr['path'], $occ, $files,
        'live_without_doc — معالجٌ حيٌّ خارجَ القاموس (فحص ⑧)'));
    $orphan++;
}
fclose($fh);

$o('══ مصفوفةُ الاستعمال — فُحص ' . $scanned . ' ملفَّ PHP حيٍّ ══');
$o('رموزُ القاموس: ' . count($dict));
$o('  مربوطٌ بمعالجٍ حي (alias): ' . $cnt['bound']);
$o('  يظهر في الكودِ وربطُه غيرُ موثَّق: ' . $cnt['used_unbound']);
$o('  قاموسٌ بلا أثرٍ في الكود: ' . $cnt['dict_only']);
$o('معالجاتٌ حيةٌ في actions خارجَ القاموس (فحص ⑧): ' . $orphan . ' من ' . count($liveReg));
$o('المخرج: ' . $csvPath);
$o('◆ البوابةُ الحيةُ (الورقة 15): هذه مصفوفةُ الاستعمال المطلوبةُ قبل Dry Run — ولا يُغيَّر رمزٌ في الكود قبل خطةِ الترحيل الست.');
