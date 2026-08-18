<?php
/**
 * tools/uxui_promotion_gate.php — بوابةُ ترقيةِ النمطِ التسع (ف١٦-٢)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ «مرت التسعةُ ← VISUAL_PATTERN_APPROVED ← عمّم فورًا ولا تنتظرني».
 *   وهذه الأداةُ **تجمع** الفحوصَ التسعةَ ولا تُعيد اختراعَها: كلُّ فحصٍ له
 *   أداتُه، وهذه تقرأ مخرَجَها وتحكم — فمصدرُ كلِّ رقمٍ واحدٌ لا اثنان.
 *
 * ◆ وحكمُ البند ٢ بنصِّه: «G19 بقياسِ متصفحٍ حقيقيٍّ… **وحتى يُصلَح قياسُها لا
 *   تُحسب ضمن نسبةِ المرور**» — فالبوابةُ تُخرج ما لا قياسَ متصفحٍ حديثًا له
 *   من المقامِ وتُعلنه، ولا تعدُّه مارًّا ولا راسبًا.
 *
 * ◆ والبندان ٨ و٩ **لا يُشغَّلان هنا بحالٍ**: نصُّ المالكِ «لا تكن أنت البانيَ
 *   ومشغّلَ البوابةِ والمصادقَ البصريَّ معًا… ينفّذهما شخصٌ آخر». فتُقرأ نتيجتُهما
 *   من `gov_independent_reviews` إن سُجِّلت بمنفِّذٍ مستقل، وإلا فـ
 *   `BLOCKED_EXTERNAL_INPUT` — ولا تُرقَّى شاشةٌ بدونهما.
 *
 * التشغيل:
 *   php tools/uxui_promotion_gate.php                كلُّ الذهبية
 *   php tools/uxui_promotion_gate.php --screen=X     شاشةٌ واحدة
 *   php tools/uxui_promotion_gate.php --md=<path>    تقريرُ Markdown
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');
$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : '1'; }
}
$PHPBIN = PHP_BINARY;
function run($cmd) { return (string) @shell_exec($cmd . ' 2>&1'); }

/* الإصدارُ العاملُ من بصمةِ الملفاتِ الآن — القياسُ الأقدمُ منه لا يُقرأ */
$FILES = array('assets/css/uxui-tokens.css', 'assets/css/uxui-components.css',
               'includes/uxui_components.php', 'includes/status_display.php', 'assets/css/ems-screens.css');
$parts = array();
foreach ($FILES as $f) { if (is_file($ROOT . '/' . $f)) { $parts[$f] = hash_file('sha256', $ROOT . '/' . $f); } }
ksort($parts);
$fp = hash('sha256', implode('|', array_map(function ($k, $v) { return $k . ':' . $v; }, array_keys($parts), $parts)));
$verRow = $conn->query("SELECT version_tag, state FROM gov_component_versions WHERE fingerprint='" . $conn->real_escape_string($fp) . "'");
$ver = ($verRow && $verRow->num_rows) ? $verRow->fetch_assoc() : null;
$verTag = $ver ? $ver['version_tag'] : null;

/* الشاشاتُ الذهبية */
$screens = array();
$w = !empty($args['screen']) ? " WHERE screen_file='" . $conn->real_escape_string($args['screen']) . "'" : '';
$q = $conn->query("SELECT screen_file, title_ar, category, pattern_state, approval_basis FROM gov_golden_approvals {$w} ORDER BY id");
while ($q && ($x = $q->fetch_assoc())) { $screens[] = $x; }
if (!$screens) { exit("لا شاشاتٍ\n"); }

/* ── الفحوصُ المشترَكةُ تُشغَّل مرةً واحدةً لكلِّ الشاشات ── */
echo "════ بوابةُ ترقيةِ النمطِ التسع ════\n";
echo "  إصدارُ المكوّن: " . ($verTag ?: '⚠ بصمةٌ غيرُ مسجَّلة') . ($ver ? " ({$ver['state']})" : '') . "\n\n";

/* ① صفرُ فقد */
$o1 = run(escapeshellarg($PHPBIN) . ' ' . escapeshellarg($ROOT . '/tools/uxui_golden_inventory.php')
        . ' --check=' . escapeshellarg($ROOT . '/docs/uxui_golden_inventory_pre.tsv'));
$c1_ok = (strpos($o1, 'صفرُ فقدٍ في العشرِ الذهبية') !== false);
$c1_bad = array();
foreach (explode("\n", $o1) as $ln) { if (strpos($ln, '✗') !== false && strpos($ln, 'نقصٌ') !== false) { $c1_bad[] = trim($ln); } }

/* ② بواباتُ G14..G20 النصية (وG19 من قياسِ المتصفحِ أدناه) */
$o2 = run(escapeshellarg($PHPBIN) . ' ' . escapeshellarg($ROOT . '/tools/uxui_golden_gates.php'));
$g2 = array();
foreach (explode("\n", $o2) as $ln) {
    if (preg_match('~^\s*([✔✗])\s+(\S+\.php)\s+\(.*مخالفات:\s*(\d+)~u', $ln, $m)) { $g2[$m[2]] = (int) $m[3]; }
}

/* ③ الوصولُ الرقميّ — التضادُّ آليٌّ · والأحدَ عشرَ من قياسِ المتصفحِ المسجَّل */
$o3 = run(escapeshellarg($PHPBIN) . ' ' . escapeshellarg($ROOT . '/tools/uxw_a11y_contrast.php'));
$c3_contrast = (preg_match('~راسبة:\s*0~u', $o3) === 1);

/* ⑥ مركزيةُ المكوّنات */
$o6 = run(escapeshellarg($PHPBIN) . ' ' . escapeshellarg($ROOT . '/tools/uxui_component_centrality.php'));
$g6 = array();
foreach (explode("\n", $o6) as $ln) {
    if (preg_match('~^\s*✔\s+(\S+\.php)\s*$~u', $ln, $m)) { $g6[$m[1]] = 0; }
    elseif (preg_match('~^\s*✗\s+(\S+\.php)\s+—\s+مخالفات:\s*(\d+)~u', $ln, $m)) { $g6[$m[1]] = (int) $m[2]; }
}

/* ②/④/⑦ قياسُ المتصفحِ المسجَّلُ **بالإصدارِ الحالي** */
$meas = array();
if ($verTag !== null) {
    $q = $conn->query("SELECT screen_file, viewport_w, header_within_limit, has_h_scroll,
                              primary_buttons, stacked_toolbars, measured_at
                         FROM gov_visual_measurements
                        WHERE component_version = '" . $conn->real_escape_string($verTag) . "'");
    while ($q && ($x = $q->fetch_assoc())) { $meas[$x['screen_file']][(int) $x['viewport_w']] = $x; }
}

/* ⑧/⑨ المراجعةُ المستقلة — تُقرأ ولا تُشغَّل */
$indep = array();
$hasTbl = $conn->query("SHOW TABLES LIKE 'gov_independent_reviews'");
if ($hasTbl && $hasTbl->num_rows) {
    $q = $conn->query("SELECT screen_file, review_kind, verdict FROM gov_independent_reviews WHERE verdict = 'PASS'");
    while ($q && ($x = $q->fetch_assoc())) { $indep[$x['screen_file']][$x['review_kind']] = true; }
}

/* ── الحكمُ لكلِّ شاشة ── */
$rows = array();
foreach ($screens as $s) {
    $f = $s['screen_file'];
    $r = array('screen' => $f, 'title' => $s['title_ar'], 'category' => $s['category']);

    $r['c1'] = in_array($f, array_map(function ($x) { return trim(explode(':', ltrim($x, ' ✗'))[0]); }, $c1_bad), true) ? 'FAIL' : ($c1_ok ? 'PASS' : 'FAIL');
    $r['c2_text'] = isset($g2[$f]) ? ($g2[$f] === 0 ? 'PASS' : 'FAIL(' . $g2[$f] . ')') : 'N/A';

    /* البند ٢ · G19 وما يقيسه المتصفح: بلا قياسٍ حديثٍ = **خارجَ المقام** */
    $m1920 = $meas[$f][1920] ?? null;
    $m1366 = $meas[$f][1366] ?? null;
    $mTab  = $meas[$f][768] ?? null;
    if (!$m1920 && !$m1366) { $r['c2_browser'] = 'NOT_MEASURED'; }
    else {
        $mm = $m1920 ?: $m1366;
        $r['c2_browser'] = ((int) $mm['header_within_limit'] === 1 && (int) $mm['stacked_toolbars'] <= 1
                            && (int) $mm['primary_buttons'] <= 1) ? 'PASS' : 'FAIL';
    }
    $r['c3'] = $c3_contrast ? 'PARTIAL' : 'FAIL';   /* التضادُّ وحدَه مقيسٌ آليًّا */
    if (!$m1920 || !$m1366 || !$mTab) { $r['c4'] = 'NOT_MEASURED'; }
    else {
        $r['c4'] = ((int) $m1920['has_h_scroll'] === 0 && (int) $m1366['has_h_scroll'] === 0
                    && (int) $mTab['has_h_scroll'] === 0) ? 'PASS' : 'FAIL';
    }
    $r['c5'] = isset($g2[$f]) ? ($g2[$f] === 0 ? 'PASS' : 'FAIL') : 'N/A';
    $r['c6'] = isset($g6[$f]) ? ($g6[$f] === 0 ? 'PASS' : 'FAIL(' . $g6[$f] . ')') : 'N/A';
    $r['c7'] = ($verTag !== null && ($m1920 || $m1366)) ? 'PASS' : 'NOT_MEASURED';
    $r['c8'] = isset($indep[$f]['human_test']) ? 'PASS' : 'BLOCKED_EXTERNAL_INPUT';
    $r['c9'] = isset($indep[$f]['investor_round']) ? 'PASS' : 'BLOCKED_EXTERNAL_INPUT';

    $checks = array($r['c1'], $r['c2_text'], $r['c2_browser'], $r['c3'], $r['c4'], $r['c5'], $r['c6'], $r['c7'], $r['c8'], $r['c9']);
    $counted = 0; $passed = 0;
    foreach ($checks as $v) {
        if ($v === 'NOT_MEASURED' || $v === 'N/A') { continue; }   /* خارجَ المقامِ بنصِّ القرار */
        $counted++;
        if ($v === 'PASS') { $passed++; }
    }
    $r['passed'] = $passed; $r['counted'] = $counted;
    $r['verdict'] = ($passed === $counted && $counted > 0
                     && $r['c8'] === 'PASS' && $r['c9'] === 'PASS') ? 'VISUAL_PATTERN_APPROVED' : 'NOT_YET';
    $rows[] = $r;
}

/* ── التقرير ── */
$approved = 0;
foreach ($rows as $r) {
    $mark = $r['verdict'] === 'VISUAL_PATTERN_APPROVED' ? '✔' : '✗';
    if ($r['verdict'] === 'VISUAL_PATTERN_APPROVED') { $approved++; }
    printf("  %s %-34s %d/%d · فئة=%s\n", $mark, $r['screen'], $r['passed'], $r['counted'], $r['category'] ?: '—');
    printf("      ①فقد=%s ②نص=%s ②متصفح=%s ③وصول=%s ④استجابة=%s ⑤لغة=%s ⑥مكوّنات=%s ⑦أساس=%s ⑧بشري=%s ⑨عرض=%s\n",
        $r['c1'], $r['c2_text'], $r['c2_browser'], $r['c3'], $r['c4'], $r['c5'], $r['c6'], $r['c7'], $r['c8'], $r['c9']);
}
echo "\n  ◆ مرقَّاةٌ (VISUAL_PATTERN_APPROVED): {$approved}/" . count($rows) . "\n";
echo "  ◆ «NOT_MEASURED» **خارجَ المقامِ** بنصِّ ف١٦-٢ — لا تُحسب مارّةً ولا راسبة.\n";
echo "  ◆ البندان ⑧ و⑨ لا يُشغَّلان هنا بحال: منفِّذُهما مستقلٌّ عن البانِي.\n";

if (!empty($args['md'])) {
    $L = array('# بوابةُ ترقيةِ النمطِ التسع — الحالُ المقيس', '',
        '· ' . date('Y-m-d H:i') . ' · إصدارُ المكوّن: `' . ($verTag ?: 'غيرُ مسجَّل') . '`',
        '· أمرُ الإنتاج: `php tools/uxui_promotion_gate.php --md=<الملف>`', '',
        '| الشاشة | ①فقد | ②نص | ②متصفح | ③وصول | ④استجابة | ⑤لغة | ⑥مكوّنات | ⑦أساس | ⑧بشري | ⑨عرض | الحكم |',
        '|---|---|---|---|---|---|---|---|---|---|---|');
    foreach ($rows as $r) {
        $L[] = '| `' . $r['screen'] . '` | ' . $r['c1'] . ' | ' . $r['c2_text'] . ' | ' . $r['c2_browser']
             . ' | ' . $r['c3'] . ' | ' . $r['c4'] . ' | ' . $r['c5'] . ' | ' . $r['c6'] . ' | ' . $r['c7']
             . ' | ' . $r['c8'] . ' | ' . $r['c9'] . ' | **' . $r['verdict'] . '** |';
    }
    $L[] = '';
    $L[] = '**مرقَّاة:** ' . $approved . '/' . count($rows);
    file_put_contents($args['md'], implode("\n", $L) . "\n");
    echo "  MD ⇐ {$args['md']}\n";
}
