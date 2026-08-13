<?php
/**
 * tests/wide_table_views_test.php — شاهدُ الشاشاتِ الطويلةِ ومنتقي المناظر
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0493
 *
 * **العيب**: ١٦٥ جدولًا بعشرين عمودًا فأكثر، و**صفرُ** شاشاتٍ تستعمل «المناظرَ
 * المحفوظة». وجدولٌ بهذا العرضِ لا يُقرأ: يُمسح أفقيًّا بحثًا عن عمودَين في كلِّ
 * مرة، ولا سبيلَ لحفظِ ما يهمُّ المستخدم.
 *
 * **الإصلاح** في العُدَّةِ: `buildColumnsAndViews()` تُبنى لكلِّ جدولٍ مُهيَّأ —
 * منتقي أعمدةٍ · زرُّ «إظهار كل الأعمدة» · منتقي مناظرَ محفوظةٍ بالاسم.
 * والخزنُ في `localStorage` بمفتاحِ المسارِ ورقمِ الجدول — فهو **يعبر انتهاءَ
 * الجلسة**، وهو نصُّ القبول: «يبقى بعد الخروجِ والدخول».
 *
 * ── والقياسُ الحيُّ الذي لا تبلغه أداةُ CLI ─────────────────────────────────
 * المنتقي يُبنى في المتصفحِ لا في الخادم، فلا يظهر في مُخرَجِ HTTP. فقيس في
 * متصفحٍ حقيقيٍّ على `Contracts/contracts.php` (٢٩ رأسًا · ٥٢ عمودًا في الجدول):
 *   · منتقي أعمدةٍ ١ · منتقي مناظرَ ١ · زرُّ «إظهار كل الأعمدة» حاضر.
 *   · حُفظ منظرٌ باسمِه، ثم **خروجٌ ودخولٌ جديد** — فبقي في المنتقي وفي الخزن.
 * وهذا الفاحصُ يحرس ما يمكن حراستُه آليًّا: أنَّ الجداولَ الطويلةَ **مؤهَّلةٌ**
 * للعُدَّةِ (فلا يُبنى المنتقي لجدولٍ خارجَها)، وأنَّ الخزنَ يعبر الجلسة.
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
$say('══ INJ-0493 · الجداولُ الطويلةُ تنال منتقيَ أعمدةٍ ومناظرَ تعبر الجلسة');

$kit = (string) file_get_contents($ROOT . '/assets/js/ui-unification.js');

/* ── ① المكوّنُ مبنيٌّ **ومُنادًى** ────────────────────────────────────────── */
$ok(strpos($kit, 'function buildColumnsAndViews') !== false, 'المكوّنُ مبنيٌّ في العُدَّة');
$ok(strpos($kit, 'buildColumnsAndViews(api, tableEl, host);') !== false,
    '**ومُنادًى** بفاصلتِه المنقوطة — لا معرَّفًا فقط');
$ok(strpos($kit, 'ems-colvis') !== false && strpos($kit, 'ems-views') !== false,
    'ويبني منتقيَ الأعمدةِ ومنتقيَ المناظرِ معًا');
$ok(preg_match('~إظهار كل الأعمدة|إظهارِ كلِّ الأعمدة~u', $kit) === 1
    || preg_match('~إظهار كل الأعمدة~u', $kit) >= 1,
    'وزرَّ «إظهار كل الأعمدة»');

/* ── ② والخزنُ يعبر انتهاءَ الجلسةِ — لا `sessionStorage` ولا متغيّرُ ذاكرة ── */
$ok(strpos($kit, "'ems.views:' + location.pathname") !== false,
    'ومفتاحُ الخزنِ مسارُ الشاشةِ ورقمُ الجدول — فلا تتداخل شاشتان');
$ok(strpos($kit, 'localStorage.setItem') !== false && strpos($kit, 'localStorage.getItem') !== false,
    '**والخزنُ في `localStorage`** — فيعبر الخروجَ والدخول');
$ok(strpos($kit, 'sessionStorage') === false,
    'ولا أثرَ لـ`sessionStorage` — فهو يموت بإغلاقِ اللسان');

/* ── ③ الجداولُ الطويلةُ: تُعَدُّ وتُقاس أهليتُها للعُدَّة ─────────────────── */
$wide = array(); $wideIneligible = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    $path = str_replace('\\', '/', $p->getPathname());
    if (substr($path, -4) !== '.php') { continue; }
    if (preg_match('~/(storage/backups|\.claude/worktrees|vendor|node_modules|tests|tools|docs)/~', $path)) { continue; }
    $src = (string) @file_get_contents($path);
    if (stripos($src, '<thead') === false) { continue; }
    if (!preg_match_all('~<thead\b.*?</thead>~su', $src, $m)) { continue; }
    foreach ($m[0] as $head) {
        $cols = preg_match_all('~<th\b~i', $head);
        if ($cols < 20) { continue; }
        $rel = str_replace($ROOT . '/', '', $path);
        $wide[$rel] = $cols;
        /* خروجٌ **صلبٌ** يمنع العُدَّةَ — والاستشاريُّ لا يمنع */
        if (strpos($src, 'data-no-dt="hard"') !== false || strpos($src, 'ems-no-enhance') !== false) {
            $wideIneligible[] = $rel . ' (خروجٌ صلب)';
        }
        break;
    }
}
$ok(count($wide) >= 50, 'الجداولُ الطويلةُ (٢٠ عمودًا فأكثر): ' . count($wide) . ' ملفًّا');
$ok(empty($wideIneligible),
    '**وكلُّها مؤهَّلةٌ لعُدَّةِ المنتقي** — لا خروجَ صلبًا يمنعها (' . count($wideIneligible) . ')',
    implode(' · ', array_slice($wideIneligible, 0, 5)));

/* ── ④ وعيّنةٌ تُصيَّر فعلًا بعشرينَ رأسًا فأكثر على HTTP ────────────────────── */
$jar = sys_get_temp_dir() . '/widetbl_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 120));
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
$sample = array('Contracts/contracts.php', 'Employees/employees.php', 'Equipments/equipments.php');
$rendered = 0; $withKit = 0; $maxCols = 0;
foreach ($sample as $rel) {
    $h = $http($BASE . '/' . $rel);
    if (mb_strpos($h, 'name="password"') !== false) { continue; }
    if (!preg_match('~<thead\b.*?</thead>~su', $h, $mm)) { continue; }
    $c = preg_match_all('~<th\b~i', $mm[0]);
    if ($c >= 20) { $rendered++; $maxCols = max($maxCols, $c); }
    if (strpos($h, 'ui-unification.js') !== false) { $withKit++; }
}
@unlink($jar);
$ok($rendered >= 2, "وصُيِّرت {$rendered} شاشاتٍ بعشرينَ رأسًا فأكثر (أعرضُها {$maxCols})");
$ok($withKit === count($sample), 'وكلُّها تُحمّل العُدَّةَ التي تبني المنتقي',
    "{$withKit}/" . count($sample));

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
