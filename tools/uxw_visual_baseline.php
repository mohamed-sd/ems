<?php
/**
 * tools/uxw_visual_baseline.php — خطُّ الأساسِ البنيويُّ للذهبية (بوابةُ المنعِ ١٠)
 * ───────────────────────────────────────────────────────────────────────────
 * منهجُ UXR-01 نفسُه: ما لا يُقاس إلا بمتصفحٍ (بكسلات) يُقاس هنا بوكيلِه
 * البنيويِّ المعلَن — هيكلُ الوسومِ والأصنافِ كما يصل المتصفحَ فعلًا بجلسةٍ
 * مسجَّلة — ويكتمل بصريًّا في جولةِ UAT.
 *
 *   php tools/uxw_visual_baseline.php --capture   # يبني/يجدِّد خطَّ الأساس
 *   php tools/uxw_visual_baseline.php             # يقارن؛ الفرقُ يكتب .ssdiff/*.diff.txt
 *                                                 # فترسِّبه بوابةُ المنعِ ١٠ حتى يُعتمد بسببٍ
 * اعتمادُ فرقٍ مقصود: أعد --capture بعد كتابةِ السببِ في .ssdiff/APPROVALS.md
 */
error_reporting(E_ALL);
/* ◆ **سقطت المقارنةُ برمز 255 بعدَ 84 ثانيةً على شاشةٍ ضخمة** — و`preg` تُعيد
     NULL صامتةً عند تجاوزِ حدِّ الارتدادِ أو الذاكرة، **فتُقرأ «لا وسومَ» لا
     «تعذّر القياس»**. والحدُّ يُرفع هنا، **ومُرجَعُ الاستبدالِ يُفحَص أدناه**:
     أداةٌ تُقارن ولا تعرف متى عجزت تُنتج «صفرَ فرقٍ» من عجزِها. */
ini_set('pcre.backtrack_limit', '100000000');
ini_set('pcre.recursion_limit', '10000000');
ini_set('memory_limit', '1024M');
$ROOT = dirname(__DIR__);
$CAPTURE = in_array('--capture', $argv, true);
$BASE = 'http://localhost/ems';
$DIR = $ROOT . '/.ssdiff';
if (!is_dir($DIR)) { mkdir($DIR, 0777, true); }

/* جلساتٌ مسجَّلةٌ بحساباتِ الاختبار — حسابٌ لكلِّ شاشةٍ بحسبِ دورِها
   الخريطةُ في tools/uxw_accounts.txt: بادئةُ مسار<TAB>اسمُ الحساب (كلمةُ المرور 12345678
   لحساباتِ الاختبارِ كلِّها — دليلُ UAT). الافتراضُ: محمد. */
$ACCOUNTS = array();
if (is_file(__DIR__ . '/uxw_accounts.txt')) {
    foreach (file(__DIR__ . '/uxw_accounts.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if (trim($ln) === '' || $ln[0] === '#') continue;
        $c = explode("\t", $ln);
        if (count($c) >= 2) { $ACCOUNTS[trim($c[0])] = trim($c[1]); }
    }
}
$JARS = array(); $LAST_URL = '';
function http_as(string $user, string $url, $post = null) {
    global $JARS, $LAST_URL, $BASE;
    if (!isset($JARS[$user])) {
        $JARS[$user] = sys_get_temp_dir() . '/uxw_vb_' . md5($user) . '.txt';
        @unlink($JARS[$user]);
    }
    $jar = $JARS[$user];
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch);
    $LAST_URL = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return (string) $b;
}
function login_as(string $user) {
    global $BASE;
    static $done = array();
    if (isset($done[$user])) return;
    $login = http_as($user, "$BASE/login.php");
    $csrf = preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)~', $login, $m) ? $m[1] : '';
    http_as($user, "$BASE/login.php", array('username' => $user, 'password' => '12345678', 'csrf_token' => $csrf));
    $done[$user] = true;
}
function account_for(string $rel, array $ACCOUNTS): string {
    $best = 'محمد'; $bestLen = 0;
    foreach ($ACCOUNTS as $prefix => $user) {
        if (strpos($rel, $prefix) === 0 && strlen($prefix) > $bestLen) { $best = $user; $bestLen = strlen($prefix); }
    }
    return $best;
}

/* الهيكلُ البنيوي: تسلسلُ الوسومِ وأصنافِها — بلا نصوصٍ ولا قيمٍ متغيرة */
function skeleton(string $html): string {
    if (!preg_match('~<body[^>]*>(.*)</body>~su', $html, $m)) { $m = array('', $html); }
    $b = $m[1];
    $b = preg_replace('~<script\b.*?</script>~su', '', $b);
    /* preg_match_all كان يُجسِّد كلَّ وسومِ الصفحةِ ومصفوفاتِها دفعةً واحدة —
       فصفحةٌ بعشراتِ الآلافِ من العقدِ تستنزف 128م.ب وتُسقط المقارنةَ في
       منتصفِها بخطأٍ فادح (رُصد على شاشةِ تقاريرَ ضخمةٍ فبقيت ~60 شاشةً بلا
       مقارنةٍ صامتةً). الكنسُ بردِّ نداءٍ يبني السطرَ ويُهمل المطابقةَ فورًا. */
    $lines = array();
    preg_replace_callback(
        '~<([a-zA-Z][a-zA-Z0-9-]*)((?:\s+[^<>]*?)?)/?>~su',
        function ($t) use (&$lines) {
            $cls = preg_match('~class\s*=\s*["\']([^"\']*)~u', $t[2], $cm)
                 ? trim(preg_replace('/\s+/', ' ', $cm[1])) : '';
            $lines[] = strtolower($t[1]) . ($cls !== '' ? '.' . str_replace(' ', '.', $cls) : '');
            return '';
        },
        $b
    );
    /* ◆ **العجزُ يُعلَن ولا يُقرأ «لا وسوم»**: `preg_last_error` غيرُ صفرٍ يعني
         أن المسحَ توقّف — وهيكلٌ ناقصٌ يُقارَن بكاملٍ يُنتج فرقًا كاذبًا،
         أو يُلتقط أساسًا ناقصًا **فيصمت عن كلِّ فرقٍ بعدَه**. */
    if (preg_last_error() !== PREG_NO_ERROR) {
        return '__SKELETON_ERROR__:' . preg_last_error_msg();
    }
    return implode("\n", $lines);
}

$scope = array();
foreach (file(__DIR__ . '/uxw_scope.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    $ln = trim($ln);
    if ($ln !== '' && $ln[0] !== '#') { $scope[] = $ln; }
}

/* شاشاتٌ ديناميكيةُ البنيةِ بطبيعتِها (نشاطٌ حيٌّ يتغير بكلِّ جلب) — تُلتقط
   ولا تُقارَن بنيويًّا؛ الإعلانُ في tools/uxw_dynamic.txt بسببِ كلِّ واحدة */
$DYNAMIC = array();
if (is_file(__DIR__ . '/uxw_dynamic.txt')) {
    foreach (file(__DIR__ . '/uxw_dynamic.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if (trim($ln) === '' || $ln[0] === '#') continue;
        $c = explode("\t", $ln);
        $DYNAMIC[trim($c[0])] = isset($c[1]) ? trim($c[1]) : '';
    }
}

$diffs = 0;
/* معاملاتُ جلبٍ لشاشاتٍ تشترط معاملَ مسارٍ أو تُحوَّل لأختِها الكنسيةِ عمدًا
   (tools/uxw_fetch_params.txt: مسار<TAB>سلسلةُ استعلام[<TAB>اسمُ الوجهةِ المتوقَّعة]) */
$PARAMS = array(); $EXPECT = array();
if (is_file(__DIR__ . '/uxw_fetch_params.txt')) {
    foreach (file(__DIR__ . '/uxw_fetch_params.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if (trim($ln) === '' || $ln[0] === '#') continue;
        $c = explode("\t", $ln);
        if (count($c) >= 2) { $PARAMS[trim($c[0])] = trim($c[1]); }
        if (count($c) >= 3) { $EXPECT[trim($c[0])] = trim($c[2]); }
    }
}
foreach ($scope as $rel) {
    $slug = str_replace(array('/', '.php'), array('__', ''), $rel);
    $user = account_for($rel, $ACCOUNTS);
    login_as($user);
    $qs = isset($PARAMS[$rel]) ? ('?' . $PARAMS[$rel]) : '';
    $html = http_as($user, "$BASE/" . $rel . $qs);
    $wanted = isset($EXPECT[$rel]) ? $EXPECT[$rel] : basename($rel);
    if (strpos($LAST_URL, $wanted) === false) {
        echo "⚠ {$rel}: حُوِّل عنها ({$LAST_URL}) بحساب «{$user}» — لم تُقَس؛ سجِّل حسابًا مخوَّلًا في uxw_accounts.txt\n";
        continue;
    }
    if (strlen($html) < 500) {
        echo "⚠ {$rel}: صفحةٌ قصيرةٌ — لم تُقَس\n";
        continue;
    }
    $skel = skeleton($html);
    $file = "$DIR/{$slug}.skel";
    if ($CAPTURE || !is_file($file)) {
        file_put_contents($file, $skel);
        @unlink("$DIR/{$slug}.diff.txt");
        echo "✔ أساس: {$rel} (" . substr_count($skel, "\n") . " عقدة)\n";
        continue;
    }
    if (isset($DYNAMIC[$rel])) {
        @unlink("$DIR/{$slug}.diff.txt");
        echo "◇ ديناميكيةٌ معلَنة: {$rel} — تُلتقط ولا تُقارَن ({$DYNAMIC[$rel]})\n";
        continue;
    }
    $base = file_get_contents($file);
    if ($base === $skel) {
        @unlink("$DIR/{$slug}.diff.txt");
        echo "✔ مطابق: {$rel}\n";
    } else {
        $a = explode("\n", $base); $b2 = explode("\n", $skel);
        $only = array_merge(array_diff($a, $b2), array_diff($b2, $a));
        file_put_contents("$DIR/{$slug}.diff.txt",
            "فرقٌ بنيويٌّ غيرُ معتمدٍ في {$rel}\nعقدُ الأساس: " . count($a) . " · الحاليّ: " . count($b2)
            . "\nعيّنةُ الفرق:\n" . implode("\n", array_slice($only, 0, 30)) . "\n");
        echo "✘ فرق: {$rel} — كُتب {$slug}.diff.txt (يعتمده --capture بعد توثيقِ السبب)\n";
        $diffs++;
    }
}
exit($diffs > 0 ? 1 : 0);
