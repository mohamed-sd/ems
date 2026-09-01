<?php
/**
 * tools/u13_render_one.php — تصييرُ شاشةٍ واحدةٍ بجلسةٍ محقونةٍ (عمليةٌ ابن)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لماذا عمليةٌ منفصلةٌ لكلِّ شاشة:
 *   الشاشةُ المرتدةُ حوكميًّا تنتهي بـ`exit()` — فتصييرُ عشرينَ شاشةً في عمليةٍ
 *   واحدةٍ يموت عند أولِ ارتداد. ولأن الحكمَ يجب أن يشمل الكلَّ، لكلِّ شاشةٍ
 *   عمليتُها.
 *
 * ◆ ولماذا جلسةٌ محقونةٌ لا تسجيلُ دخول:
 *   حاملو الأدوارِ الجديدةِ (رئيسُ الحساباتِ والمراجعُ الداخلي) أُنشئوا **بلا
 *   كلمةِ مرورٍ صالحة** عمدًا — فإنشاءُ بيانِ اعتمادٍ قرارُ مالكٍ لا أداة.
 *   وهذا الحاقنُ يُثبت أن الشاشةَ **تُصيَّر لدورِها** بلا أن يُخلَق بيانُ اعتماد.
 *   وهو أقوى من تسجيلِ الدخول: يبلغ كلَّ دورٍ لا الأدوارَ التي لها كلمةُ مرور.
 *
 * التشغيل (لا يُستدعى مباشرةً عادةً):
 *   php tools/u13_render_one.php <Dir/file.php> <role> <user_id> <company_id>
 * الخرج: سطرُ حكمٍ واحدٌ على stdout ثم محتوى الصفحةِ إن طُلب بـ--body
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '0');

$rel  = isset($argv[1]) ? $argv[1] : '';
$role = isset($argv[2]) ? (string) $argv[2] : '';
$uid  = isset($argv[3]) ? (int) $argv[3] : 0;
$co   = isset($argv[4]) ? (int) $argv[4] : 0;
$want = in_array('--body', $argv, true);
if ($rel === '') { exit("VERDICT|fail|بلا مسار\n"); }

$ROOT = dirname(__DIR__);
$path = $ROOT . '/' . $rel;
if (!is_file($path)) { exit("VERDICT|fail|الملفُّ غيرُ موجود\n"); }

/* ◆ گوتشا: الحقنُ قبلَ `session_start()` **يُمحى** — فالدالةُ تستبدل `$_SESSION`
     بما في المخزن (وهو فارغ)، فتظنُّ الشاشةُ أن لا جلسةَ وترتدُّ إلى الدخول.
     فالجلسةُ تُفتح هنا أولًا، ثم يُحقن المستخدم، ثم تجدها العُدّةُ مفتوحةً
     فلا تفتحها ثانيةً — فيبقى الحقنُ حيًّا. */
require_once $ROOT . '/includes/session_bootstrap.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

/* الجلسةُ محقونةٌ — والشاشةُ تقرؤها كما تقرأ جلسةَ متصفح.
   ◆ `role` نصًّا: المنصةُ تقارن الدورَ بـ=== على نصّ (includes/roles.php). */
$_SESSION = array('user' => array(
    'id' => $uid, 'role' => $role, 'company_id' => $co,
    'name' => 'فاحصُ التصيير', 'username' => 'u13_render',
));
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/ems/' . $rel;
$_SERVER['SCRIPT_NAME']    = '/ems/' . $rel;
$_SERVER['PHP_SELF']       = '/ems/' . $rel;
$_SERVER['SCRIPT_FILENAME'] = $path;
$_SERVER['HTTP_HOST']      = 'localhost';
$_GET = array();
$_POST = array();

/* ═══ معاملاتُ الطلب: `--get=k=v&k2=v2` ═══════════════════════════════════
 * ◆ **الحاجزُ الذي ترفعه**: أسطحٌ تشترط معاملًا في المسار (`contract_id`
 *   مثلًا) وترتدُّ بدونه — فتُقرأ «لا تُصيَّر» وهي تُصيَّر بمعاملِها.
 *   ⛔ **والحكمُ على سطحٍ بلا معاملِه حكمٌ على غيرِ محلِّه**. */
foreach ($argv as $a) {
    if (strpos($a, '--get=') !== 0) { continue; }
    parse_str(substr($a, 6), $__q);
    if (is_array($__q)) { $_GET = $__q; $_SERVER['REQUEST_URI'] .= '?' . substr($a, 6); }
}

/* ═══ وضعُ الفعل: `--post=<ملفُ json>` ═════════════════════════════════════
   ◆ الحمولةُ تأتي في **ملفٍّ** لا في argv: Git Bash يفسد العربيَّ في السطرِ
     الآمر، وحمولاتُ الأفعالِ عربيةٌ كلُّها.
   ◆ ورمزُ CSRF يُولَّد في الجلسةِ نفسِها ويُمرَّر في الحمولة — إلا حين يطلب
     الفاحصُ إسقاطَه ليُثبت أن الحارسَ يمنع. */
foreach ($argv as $a) {
    if (strpos($a, '--post=') !== 0) { continue; }
    $pf = substr($a, 7);
    $payload = is_file($pf) ? json_decode((string) file_get_contents($pf), true) : null;
    if (!is_array($payload)) { exit("VERDICT|fail|حمولةٌ غيرُ صالحة: " . $pf . "\n"); }
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $payload;
    if (!array_key_exists('csrf_token', $_POST)) {
        require_once $ROOT . '/includes/security.php';
        $_POST['csrf_token'] = generate_csrf_token();
    } elseif ($_POST['csrf_token'] === '__VALID__') {
        require_once $ROOT . '/includes/security.php';
        $_POST['csrf_token'] = generate_csrf_token();
    }
}

/* الأخطاءُ تُلتقط ولا تُطبع — فالحكمُ يُبنى عليها لا على ضجيجِ الشاشة. */
$ERRORS = array();
set_error_handler(function ($no, $str, $file, $line) use (&$ERRORS) {
    if (!(error_reporting() & $no)) { return true; }
    $ERRORS[] = basename((string) $file) . ':' . $line . ' ' . $str;
    return true;
});

/**
 * ◆ گوتشا: التحويلُ الحوكميُّ ينتهي بـ`exit()` — فيموت الفاحصُ قبلَ أن يحكم،
 *   ويظهر ذلك «خرجًا فارغًا» فيُقرأ نجاحًا أو عطبًا بلا تمييز. فالحكمُ يُصدَر
 *   في `register_shutdown_function` — وهي تعمل بعدَ `exit()` أيضًا.
 */
$GLOBALS['u13_want_body'] = $want;
$GLOBALS['u13_errors']    = &$ERRORS;
register_shutdown_function(function () {
    $body = '';
    while (ob_get_level() > 0) { $body = ob_get_clean() . $body; }
    $errs = isset($GLOBALS['u13_errors']) ? $GLOBALS['u13_errors'] : array();

    $fatal = '';
    $last = error_get_last();
    if ($last !== null && in_array($last['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR), true)) {
        $fatal = $last['message'] . ' @ ' . basename((string) $last['file']) . ':' . $last['line'];
    }
    if ($fatal === '' && !empty($GLOBALS['u13_throwable'])) { $fatal = $GLOBALS['u13_throwable']; }

    $hasShell = (strpos($body, 'ems-unified-page-shell') !== false);
    $hasTable = (strpos($body, 'alltables') !== false);

    if ($fatal !== '')              { $v = 'fatal|' . preg_replace('~\s+~', ' ', mb_substr($fatal, 0, 200)); }
    elseif ($errs)                  { $v = 'warn|' . preg_replace('~\s+~', ' ', mb_substr(implode(' · ', array_slice($errs, 0, 2)), 0, 200)); }
    elseif ($hasShell && $hasTable) { $v = 'ok|صُيّرت بالغلافِ والجدول'; }
    elseif ($hasShell)              { $v = 'ok|صُيّرت بالغلاف'; }
    elseif (trim($body) === '')     { $v = 'gov|ارتدادٌ حوكميٌّ — لا صلاحيةَ لهذا الدور'; }
    else                            { $v = 'empty|خرجٌ بلا غلافٍ موحَّد (' . mb_strlen($body) . ' حرفًا)'; }

    fwrite(STDOUT, 'VERDICT|' . $v . "\n");

    /* حصيلةُ الفعل — تُقتنص من لافتةِ العُدّةِ نفسِها لا من تخمين. */
    if (preg_match('~<div class="alert alert-(success|danger)">(.*?)</div>~su', $body, $m)) {
        fwrite(STDOUT, 'ACT|' . ($m[1] === 'success' ? 'ok' : 'no') . '|'
             . preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($m[2]), ENT_QUOTES, 'UTF-8')) . "\n");
    }
    if (!empty($GLOBALS['u13_want_body'])) { fwrite(STDOUT, $body); }
});

ob_start();
try {
    chdir(dirname($path));
    include $path;
} catch (\Throwable $e) {
    $GLOBALS['u13_throwable'] = get_class($e) . ': ' . $e->getMessage()
                              . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
}
