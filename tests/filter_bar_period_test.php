<?php
/**
 * tests/filter_bar_period_test.php — شاهدُ شريطِ الفلاترِ وفلترِ الفترة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0497 · INJ-0543 · INJ-0561 · INJ-0564 · INJ-0556
 *
 * ── INJ-0497 · مكوّنٌ بصفرِ مستهلكٍ ومحاورُ بلا قارئ ─────────────────────────
 * `.ems-filterbar` مبنيٌّ بالكاملِ في `assets/css/ems-shell.css` (رأسٌ · عدّادُ
 * المفعَّلِ · زرُّ تفريغٍ · طيٌّ) و**بصفرِ مستهلك**. ومكوّنٌ بصفرِ مستهلكٍ ليس
 * مكوّنًا — هو شفرةٌ ميتة. وخمسةُ محاورَ تُبذر ويُقرأ منها اثنان.
 * **الإصلاح**: العُدَّةُ تتبنّى الشريطَ على كلِّ نموذجِ ترشيح، ولكلِّ محورٍ صار
 * قارئٌ مرئيٌّ — والمحورُ الذي لا يحمل خبرًا (`data` ثابتًا في الخادم) أُزيل
 * من بذرِ الخادمِ وصار يُكتب من مصدرِه الحيِّ في المتصفح.
 *
 * ── INJ-0543 · INJ-0561 · فلترُ الفترةِ **من الخادم** ───────────────────────
 * فلاترُ أوامرِ الترحيلِ الخمسةُ تُبنى قيمُها من **محتوى الصفحةِ المُصيَّرة** — أي
 * ترشّح ما وصل لا ما في القاعدة. ولا فلترَ فترةٍ في أيِّ شاشةِ مخازنَ رغم أن كلَّ
 * حركةٍ تحمل `moved_at`. صار المدى في `WHERE` (أو في شرطِ الوصلِ حيث يلزم).
 *
 * ── INJ-0564 · جدولٌ لا يُصيَّر ────────────────────────────────────────────
 * شاشةُ الجردِ لا تُصيّر جدولًا حتى يُختار مخزن — فتُفتح بلا جدولٍ ولا تفسير.
 * صار يُصيَّر دائمًا فتشرح الحالةُ الفارغةُ المشتركةُ ما ينقص.
 *
 * ── والقياسُ **بفرقِ الصفوف** لا بوجودِ الحقل ───────────────────────────────
 * حقلُ تاريخٍ لا يعني ترشيحًا. فيُقاس: صفوفٌ بلا فلترٍ ⇒ **صفرٌ** بنافذةٍ
 * مستقبليةٍ ⇒ العددُ نفسُه بنافذةٍ تغطّي المدى. ثلاثُ قراءاتٍ لا واحدة.
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
$say('══ INJ-0497 · 0543 · 0561 · 0564 · شريطُ الفلاترِ وفلترُ الفترةِ من الخادم');

$kit = (string) file_get_contents($ROOT . '/assets/js/ui-unification.js');
$css = (string) file_get_contents($ROOT . '/assets/css/ems-shell.css');

/* ── ① الشريطُ متبنًّى في العُدَّة ──────────────────────────────────────────── */
$ok(strpos($css, '.ems-filterbar') !== false, 'الشريطُ معرَّفٌ في قشرةِ الأنماط');
$ok(strpos($kit, 'function bootFilterBars') !== false, 'والعُدَّةُ تبني متبنّيَه');
$ok(strpos($kit, 'bootFilterBars();') !== false, '**وتناديه في الإقلاع** — لا معرَّفًا بلا نداء');
$ok(strpos($kit, "classList.add('ems-filterbar', 'open')") !== false,
    'ويُلبس نموذجَ الترشيحِ صنفَ الشريط');
$ok(strpos($kit, 'ems-filter-count') !== false && strpos($kit, 'فلاتر مفعَّلة') !== false,
    'ويحسب **عددَ الفلاترِ المفعَّلة**');
$ok(strpos($kit, 'ems-filter-clear') !== false && strpos($kit, "clear.addEventListener('click'") !== false,
    'ويصل زرَّ التفريغِ بفعلٍ حقيقيّ');

/* عددُ الشاشاتِ المرشَّحةِ للتبنّي — الشريطُ يظهر عليها آليًّا */
$cand = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    $path = str_replace('\\', '/', $p->getPathname());
    if (substr($path, -4) !== '.php') { continue; }
    if (preg_match('~/(storage/backups|\.claude|vendor|node_modules|tests|tools|docs)/~', $path)) { continue; }
    $src = (string) @file_get_contents($path);
    if (!preg_match_all('~<form[^>]*>(.*?)</form>~su', $src, $m)) { continue; }
    foreach ($m[0] as $i => $whole) {
        if (preg_match('~method=["\']post["\']~i', $whole)) { continue; }
        if (preg_match_all('~<(input|select)\b~i', $m[1][$i]) >= 2) { $cand++; break; }
    }
}
$ok($cand >= 5, "والشاشاتُ التي ينالها الشريطُ: {$cand} (القبولُ ≥ ٥)");

/* ── ② لكلِّ محورٍ مبذورٍ قارئٌ ─────────────────────────────────────────────── */
$axes = array('data', 'permission', 'edit', 'connection', 'freshness');
$noReader = array();
foreach ($axes as $ax) {
    if (strpos($kit, 'data-ax-' . $ax . '="') === false) { $noReader[] = $ax; }
}
$ok(empty($noReader), '**ولكلِّ محورٍ قارئٌ واحدٌ على الأقلِّ في العُدَّة** ('
    . (count($axes) - count($noReader)) . '/' . count($axes) . ')',
    'بلا قارئ: ' . implode(' · ', $noReader));
$sc = (string) file_get_contents($ROOT . '/includes/screen_contract.php');
$ok(strpos($sc, "'data' => 'data',") === false,
    'والمحورُ الثابتُ الذي لا يحمل خبرًا أُزيل من بذرِ الخادم');
$ok(strpos($kit, "window.EmsScreenShell.set('data', filtered ? 'no-results' : 'empty')") !== false,
    'وصار يُكتب من مصدرِه الحيِّ حيث يُعرف: فارغٌ أم لا نتائجَ لبحث');

/* ── ③ فلترُ الفترةِ يعمل **من الخادم**: يُقاس بفرقِ الصفوف ────────────────── */
$jar = sys_get_temp_dir() . '/fbperiod_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 120));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch); curl_close($ch);
    return $b;
};
$login = function ($user) use ($jar, $BASE, $http) {
    @unlink($jar);
    $b = $http($BASE . '/login.php');
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
    $r = $http($BASE . '/login.php', http_build_query(array(
        'username' => $user, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
    return mb_strpos($r, 'name="password"') === false;
};
$rowsOf = function ($html) {
    if (!preg_match('~<tbody[^>]*>(.*?)</tbody>~su', $html, $m)) { return -1; }
    return preg_match_all('~<tr~', $m[1]);
};
/* ◆ **كلُّ** حساباتِ الدورِ لا أوّلَها: في دورِ النقلِ حسابانِ أحدُهما كلمتُه
     غيرُ القياسيةِ — فاختيارُ الأوّلِ يُعلن «لا حسابَ» وفي الدورِ حسابٌ يعمل. */
$usersOfRole = function ($role) use ($conn, $CO) {
    $out = array();
    $st = $conn->prepare("SELECT username FROM users WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id");
    $r = (string) $role; $st->bind_param('si', $r, $CO); $st->execute();
    $res = $st->get_result();
    while ($res && ($x = $res->fetch_row())) { $out[] = (string) $x[0]; }
    $st->close();
    return $out;
};
$userOfRole = function ($role) use ($usersOfRole, &$login) {
    foreach ($usersOfRole($role) as $u) { if ($login($u)) { return $u; } }
    return '';
};

/* أوامرُ الترحيل (INJ-0543) — دورُ النقلِ وحدَه يراها */
$u23 = $userOfRole(23);
$ok($u23 !== '', "دخل حسابُ دورِ النقل ({$u23})");
$base = $http($BASE . '/Transport/transfer_orders_list.php');
$ok(strpos($base, 'data-ems-period') !== false, 'ونموذجُ الفترةِ مُصيَّرٌ في أوامرِ الترحيل');
$n0 = $rowsOf($base);
$nFuture = $rowsOf($http($BASE . '/Transport/transfer_orders_list.php?from=2099-01-01'));
$nWide = $rowsOf($http($BASE . '/Transport/transfer_orders_list.php?from=2020-01-01&to=2030-12-31'));
$ok($n0 > 0, "وبلا فلترٍ يعود {$n0} صفًّا");
$ok($nFuture === 0, '**ونافذةٌ مستقبليةٌ تعود بصفرٍ من الخادم** (' . $nFuture . ')');
$ok($nWide > 0 && $nWide <= $n0, "ونافذةٌ تغطّي المدى تعود بـ{$nWide}");

/* المخزون والجرد (INJ-0561 · INJ-0564) */
$u25 = $userOfRole(25);
$ok($u25 !== '', "ودخل حسابُ أمينِ المستودع ({$u25})");
$s0 = $rowsOf($http($BASE . '/Procurement/stock_proc.php'));
$sF = $rowsOf($http($BASE . '/Procurement/stock_proc.php?from=2099-01-01'));
$ok(strpos($http($BASE . '/Procurement/stock_proc.php'), 'data-ems-period') !== false,
    'ونموذجُ الفترةِ مُصيَّرٌ في المخزون');
$ok($s0 > 0 && $sF === 0, "وأرصدةُ المخزونِ {$s0} ⇒ **صفرٌ** بنافذةٍ مستقبلية");

$whId = 0;
$r = $conn->query("SELECT id FROM proc_warehouse WHERE company_id = {$CO}
                    AND id IN (SELECT DISTINCT warehouse_id FROM proc_stock_move WHERE company_id = {$CO})
                    ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $whId = (int) $x[0]; }
$ok($whId > 0, "ووُجد مخزنٌ له حركاتٌ (#{$whId})");
$c0 = $rowsOf($http($BASE . '/Procurement/wh_count.php?wh=' . $whId));
$cF = $rowsOf($http($BASE . '/Procurement/wh_count.php?wh=' . $whId . '&from=2099-01-01'));
$ok($c0 > 0 && $cF === 0, "وأصنافُ الجردِ {$c0} ⇒ **صفرٌ** بنافذةٍ مستقبلية");
$noWh = $http($BASE . '/Procurement/wh_count.php');
$ok(preg_match_all('~<table~', $noWh) >= 1,
    '**وشاشةُ الجردِ تُصيّر جدولًا حتى بلا اختيارِ مخزن** — فالفراغُ يُفسَّر لا يُخفى');

/* ── ④ INJ-0556 · طلباتُ الشراء: فترةٌ **وإدارةٌ طالبةٌ** من الخادم ─────────── */
$u16 = '';
foreach ($usersOfRole(16) as $cand16) { if ($login($cand16)) { $u16 = $cand16; break; } }
$ok($u16 !== '', "ودخل حسابُ المشتريات ({$u16})");
$q0 = $rowsOf($http($BASE . '/Procurement/requests_proc.php'));
$qF = $rowsOf($http($BASE . '/Procurement/requests_proc.php?from=2099-01-01'));
$ok(strpos($http($BASE . '/Procurement/requests_proc.php'), 'data-ems-period') !== false,
    'ونموذجُ الفترةِ مُصيَّرٌ في طلباتِ الشراء');
$ok($q0 > 0 && $qF === 0, "وطلباتُ الشراءِ {$q0} ⇒ **صفرٌ** بنافذةٍ مستقبلية");
$topDept = '';
$r = $conn->query("SELECT requesting_dept FROM proc_request
                    WHERE company_id = {$CO} AND requesting_dept <> ''
                    GROUP BY requesting_dept ORDER BY COUNT(*) DESC LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $topDept = (string) $x[0]; }
$ok($topDept !== '', "ووُجدت إدارةٌ طالبةٌ حيّةٌ («{$topDept}»)");
$qD = $rowsOf($http($BASE . '/Procurement/requests_proc.php?dept=' . rawurlencode($topDept)));
$ok($qD > 0 && $qD < $q0, "**وترشيحُ الإدارةِ الطالبةِ يضيّق من الخادم**: {$q0} ⇒ {$qD}");
@unlink($jar);

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
