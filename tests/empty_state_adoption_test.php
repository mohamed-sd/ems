<?php
/**
 * tests/empty_state_adoption_test.php — شاهدُ تبنّي الحالةِ الفارغةِ المشتركة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0238 · INJ-0432
 *
 * ── INJ-0238 · المكوّنُ موجودٌ ويعمل — ومتبنّوه ١٣ من ٣٤٦ ──────────────────
 * `EmsUI.emptyState` في `assets/js/ems-components.js` سليمٌ، لكنَّ الشاشاتِ لا
 * تناديه. **وأسوأُ من ذلك**: العُدَّةُ المركزيةُ نفسُها كانت من غيرِ المتبنّين —
 * `bootEmptyStates()` في `ui-unification.js` تبني عنوانًا وتلميحًا وزرًّا بيدها،
 * بألوانٍ صلبةٍ وحشوةٍ خاصة. فنسختان لحالةٍ واحدةٍ تتفرَّقان: هو عينُ العيب.
 *
 * **الإصلاح**: العُدَّةُ تنادي المكوّن. وهي محمَّلةٌ في القشرةِ لكلِّ شاشة —
 * فالتبنّي يصير بالبناءِ لا بتعديلِ ٣٤٦ ملفًّا.
 *
 * ── INJ-0432 · لوحةُ الدور: رسمٌ بلا بيانات ورسمٌ بلا مكتبة ────────────────
 * `if (typeof Chart === 'undefined') setTimeout(draw, 150)` يعيد المحاولةَ
 * **إلى الأبد** — فحجبُ المكتبةِ يترك مساحةً بيضاءَ بلا تفسيرٍ ولا نهاية. ولا
 * حالةَ فارغةً عند صفرِ بيانات: يُرسم مخططٌ بمحاورَ افتراضيةٍ يوهم أن ثمَّ قياسًا.
 * صار حارسًا مركزيًّا `EmsUI.chartGuard`: انتظارٌ **محدود**، ثم حالةٌ مُعلَنة.
 *
 * ── ويُقاس **موضعُ النداءِ** لا ذكرُ الاسم ─────────────────────────────────
 * تعريفٌ بلا نداءٍ لا يُنتج شيئًا على الشاشة. فيُشترط النداءُ بأقواسِه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ INJ-0238 · INJ-0432 · الحالةُ الفارغةُ مكوّنٌ واحدٌ مُتبنًّى بالبناء');

$cmp = (string) file_get_contents($ROOT . '/assets/js/ems-components.js');
$kit = (string) file_get_contents($ROOT . '/assets/js/ui-unification.js');

/* ── ① المكوّنُ يحمل ما يشترطه القبول: سببٌ ومسارُ خروج ────────────────────── */
$ok(strpos($cmp, 'EmsUI.emptyState = function') !== false, 'المكوّنُ المشتركُ معرَّفٌ');
$ok(strpos($cmp, 'ems-state-reason') !== false && strpos($cmp, 'opts.reason') !== false,
    'ويحمل **سببًا** لكلِّ حالةِ فراغ');
$ok(strpos($cmp, 'opts.createHref') !== false, 'و**مسارَ خروجٍ** (زرَّ إنشاءٍ حين يوجد)');
$ok(strpos($cmp, 'ems-btn-secondary') !== false && strpos($cmp, 'onClear') !== false,
    'و«لا نتائج» تعرض مخرجَها الخاصَّ: تفريغُ الفلاتر');

/* ── ② والعُدَّةُ **تنادي** المكوّنَ ولا تبني نسخةً ثانية ───────────────────── */
$ok(preg_match('~window\.EmsUI\.emptyState\(\{~', $kit) === 1,
    '**والعُدَّةُ المركزيةُ تناديه** بأقواسِه — لا تذكر اسمَه فقط');
$ok(preg_match('~window\.EmsUI\.noResults\(\{~', $kit) === 1,
    'وتنادي «لا نتائج» عند وجودِ فلترٍ فعّال');
$ok(strpos($kit, 'emsClearFilters') !== false,
    'ومسارُ الخروجِ موصولٌ فعلًا (`emsClearFilters`) — لا زرًّا بلا أثر');
/* لا نسخةَ ثانيةً: العناوينُ والتلميحاتُ المبنيةُ بيدٍ أُزيلت */
$ok(strpos($kit, "title.textContent = filtered ?") === false
    && strpos($kit, "hint.style.fontSize = '14px'") === false,
    '**ولا نسخةَ ثانيةً مبنيةً بيدٍ** في العُدَّة');

/* ── ③ ونسبةُ التبنّي: الشاشاتُ ذاتُ الجداولِ تُحمّل العُدَّةَ من القشرة ────── */
$withTables = 0; $withKit = 0; $missing = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    $path = str_replace('\\', '/', $p->getPathname());
    if (substr($path, -4) !== '.php') { continue; }
    if (preg_match('~/(storage/backups|\.claude/worktrees|vendor|node_modules|tests|tools|docs)/~', $path)) { continue; }
    $src = (string) @file_get_contents($path);
    if (stripos($src, '<table') === false) { continue; }
    $withTables++;
    /* القشرةُ تُحمِّل العُدَّةَ: `inheader.php` أو `insidebar.php` أو نداءٌ مباشر */
    if (strpos($src, 'inheader.php') !== false || strpos($src, 'insidebar.php') !== false
        || strpos($src, 'ui-unification.js') !== false || strpos($src, 'u13_screen_kit') !== false
        || strpos($src, 'fin_analysis_shell') !== false) { $withKit++; }
    else { $missing[] = str_replace($ROOT . '/', '', $path); }
}
$rate = $withTables > 0 ? round($withKit * 100 / $withTables, 1) : 0;
$ok($withTables > 100, "الشاشاتُ ذاتُ الجداول: {$withTables}");
$ok($rate >= 80.0, "**ونسبةُ التبنّي {$rate}٪** ({$withKit}/{$withTables}) — تتجاوز ٨٠٪",
    'خارجَ القشرة: ' . implode(' · ', array_slice($missing, 0, 6)));

/* ── ④ حارسُ الرسومِ مركزيٌّ ومُتبنًّى ─────────────────────────────────────── */
$ok(strpos($cmp, 'EmsUI.chartGuard = function') !== false, 'وحارسُ الرسومِ مكوّنٌ مركزيّ');
$ok(preg_match('~opts\.timeoutMs \|\| 3000~', $cmp) === 1,
    '**وانتظارُه محدودٌ** — فحجبُ المكتبةِ ينتهي بحالةٍ لا بمحاولةٍ أبدية');
$rb = (string) file_get_contents($ROOT . '/includes/role_board_widgets.php');
$ok(strpos($rb, 'EmsUI.chartGuard(') !== false, 'ولوحةُ الدورِ تتبنّاه');
$ok(preg_match('~if \(typeof Chart === .undefined.\) \{ return setTimeout\(draw, 150\); \}~', $rb) === 0,
    '**ولا محاولةَ أبديةً باقيةً** في لوحةِ الدور');
$ep = (string) file_get_contents($ROOT . '/Employees/employee_profile.php');
$ok(strpos($ep, 'EmsUI.chartGuard(ctx, hasData, renderFn)') !== false,
    'وملفُّ الموظفِ يفوّض للمركزيِّ بدلَ نسختِه المحلية');

/* ── ⑤ والوصولُ إلى المتصفحِ مُقاسٌ على HTTP لا على المصدر ─────────────────── */
$jar = sys_get_temp_dir() . '/emptyst_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch); curl_close($ch);
    return $b;
};
$st = $conn->prepare("SELECT username FROM users WHERE role = '1' AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
$st->bind_param('i', $CO); $st->execute();
$x = $st->get_result()->fetch_row(); $st->close();
$b = $http($BASE . '/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
$http($BASE . '/login.php', http_build_query(array(
    'username' => $x ? $x[0] : '', 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
$page = $http($BASE . '/main/role_board.php');
$js = $http($BASE . '/assets/js/ui-unification.js');
$cmpJs = $http($BASE . '/assets/js/ems-components.js');
@unlink($jar);
$ok(strpos($page, 'EmsUI.chartGuard(') !== false, 'ونداءُ الحارسِ يصل إلى المتصفحِ في لوحةِ الدور');
$ok(strpos($js, 'window.EmsUI.emptyState({') !== false, 'والعُدَّةُ المخدومةُ تحمل النداء');
$ok(strpos($cmpJs, 'EmsUI.chartGuard = function') !== false, 'والمكوّنُ المخدومُ يحمل الحارس');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
