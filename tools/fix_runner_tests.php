<?php
/**
 * tools/fix_runner_tests.php — INJ-0149: أمرٌ واحدٌ يشغّل ويُرجع رمزًا صادقًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ اختبارُ القبول: «أمرٌ واحدٌ يشغّل كلَّ الاختباراتِ ويُرجع صفرًا عند النجاح؛
 *   وخطُّ التسليم يفشل عند سقوط أيِّ اختبار».
 *
 * ◆ ولم يُبنَ مشغِّلٌ ثانٍ: `tests/_regression.php` قائمٌ ويُرجع رمزًا — فبناءُ
 *   ثانٍ يُنتج **متنازعَين**، وذاك عيبٌ اجتنبتُه في كلِّ بندٍ من هذه الحزمة.
 *   بل رُبط بأمرٍ واحدٍ (`composer test`) و**حُمِيَ**.
 *
 * ◆ والحمايةُ ليست ترفًا: ترويسةُ `.env` نفسِها تسجّل أن **تشغيلَ حزمةِ
 *   الانحدارِ كاملةً دمّر الملفَّ** (13,052 بايتًا من المسافات · صفر سطر) لأن
 *   اختبارًا كتب فيه ومسارُ استرجاعه غيرُ آمن. فمشغِّلُ اختباراتٍ يُهلك إعداداتَ
 *   النظامِ عيبٌ أخطرُ من غيابِ المشغِّل.
 *
 * ◆ ويُجَسُّ العقدُ في الاتجاهين: نجاحٌ ⇒ 0 · سقوطٌ ⇒ 1 · ومسُّ ملفٍّ محميٍّ ⇒
 *   استعادةٌ فورية + رمزٌ غيرُ صفريّ.
 *
 * التشغيل: php tools/fix_runner_tests.php
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

/** يُشغّل المشغِّلَ على حزمةٍ مؤقتةٍ ويعيد [الرمز، المخرَج]. */
function run_suite($ROOT, $args = '')
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/tests/_regression.php')
         . ($args !== '' ? ' ' . $args : '') . ' 2>&1';
    $out = array(); $code = 0;
    exec($cmd, $out, $code);
    return array($code, implode("\n", $out));
}

echo "══════════════════════════════════════════════════════════════════════\n";
echo " أمرٌ واحدٌ ورمزٌ صادق — INJ-0149 · " . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n";

/* ══ ① الأمرُ الواحدُ مُعلَنٌ في مكانٍ يجده خطُّ التسليم ═══════════════════ */
head('① الأمرُ الواحد');
$cj = json_decode((string) file_get_contents($ROOT . '/composer.json'), true);
check(is_array($cj) && isset($cj['scripts']), '`composer.json` فيه قسمُ `scripts` (كان غائبًا)');
foreach (array('test', 'test:all', 'verify') as $s) {
    check(isset($cj['scripts'][$s]), "وفيه الأمرُ `composer {$s}`");
}
check(isset($cj['scripts-descriptions']), 'ولكلِّ أمرٍ وصفٌ مكتوبٌ — فالأمرُ بلا وصفٍ لا يُستعمل');
$verify = isset($cj['scripts']['verify']) ? (array) $cj['scripts']['verify'] : array();
check(count($verify) >= 3, '`composer verify` يجمع الانحدارَ وثلاثَ بوابات: ' . implode(' · ', $verify));

/* ══ ② عقدُ رمزِ الخروج — نجاحٌ صفرٌ وسقوطٌ واحد ═══════════════════════ */
head('② رمزُ الخروج يُجَسُّ في الاتجاهين');
$tmpOk = $ROOT . '/tests/_probe_pass_test.php';
file_put_contents($tmpOk, "<?php\nif (PHP_SAPI !== 'cli') { exit(1); }\necho \"النتيجة: 1 ناجح · 0 فاشل\\n\";\nexit(0);\n");
$tmpBad = $ROOT . '/tests/_probe_fail_test.php';
file_put_contents($tmpBad, "<?php\nif (PHP_SAPI !== 'cli') { exit(1); }\necho \"النتيجة: 0 ناجح · 1 فاشل\\n\";\nexit(1);\n");
register_shutdown_function(static function () use ($tmpOk, $tmpBad, $ROOT) {
    @unlink($tmpOk); @unlink($tmpBad);
    @unlink($ROOT . '/tests/_probe_harm_test.php');
});

/* حزمةٌ مؤقتةٌ من ملفٍّ ناجحٍ وحدَه: يُنادى بالاسم عبر suite=all؟ لا —
   يُجَسُّ العقدُ بتشغيلِ الملفَّين مباشرةً ثم بالمشغِّلِ على `all`. */
$out = array(); $code = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmpOk) . ' 2>&1', $out, $code);
check($code === 0, 'مِسبارٌ ناجحٌ يُرجع 0 بنفسِه');
$out = array(); $code = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmpBad) . ' 2>&1', $out, $code);
check($code === 1, 'ومِسبارٌ ساقطٌ يُرجع 1 بنفسِه');

list($codeAll, $txtAll) = run_suite($ROOT, 'all');
check(strpos($txtAll, '_probe_pass_test') !== false, 'والمشغِّلُ يكتشف المِسبارَ الناجحَ في وضعِ `all`');
check(strpos($txtAll, '_probe_fail_test') !== false, 'ويكتشف الساقطَ أيضًا');
check($codeAll !== 0, 'ويُرجع رمزًا غيرَ صفريٍّ لأن فيها ساقطًا — ' . $codeAll);
if (preg_match('/حمراء:\s*(\d+)/u', $txtAll, $m)) {
    check((int) $m[1] > 0, 'ويُبلّغ عددَ الحمراء: ' . $m[1]);
}

/* ══ ③ حِمى الملفاتِ الحساسة — الكارثةُ المسجَّلةُ لا تتكرر ═══════════════ */
head('③ الحِمى: اختبارٌ يُفسد `.env` يُستعاد فورًا ويُسمّى');
$envPath = $ROOT . '/.env';
$envBefore = is_file($envPath) ? (string) file_get_contents($envPath) : null;
check($envBefore !== null && strlen($envBefore) > 100, '`.env` قائمٌ قبل الجسّ (' . strlen((string) $envBefore) . ' بايت)');
$harm = $ROOT . '/tests/_probe_harm_test.php';
file_put_contents($harm,
    "<?php\nif (PHP_SAPI !== 'cli') { exit(1); }\n"
  . "/* مِسبارٌ يحاكي الكارثةَ المسجَّلة: يكتب في .env ولا يستعيده */\n"
  . "file_put_contents(dirname(__DIR__) . '/.env', str_repeat(' ', 128));\n"
  . "echo \"النتيجة: 1 ناجح · 0 فاشل\\n\";\nexit(0);\n");
list($codeH, $txtH) = run_suite($ROOT, 'all');
$envAfter = is_file($envPath) ? (string) file_get_contents($envPath) : null;
check($envAfter === $envBefore, '**`.env` عاد حرفًا بحرف** بعد اختبارٍ أفسده — والكارثةُ لا تتكرر');
check(strpos($txtH, '_probe_harm_test') !== false && strpos($txtH, 'محميًّا') !== false,
    'والمشغِّلُ **يسمّي** الاختبارَ الذي مسَّه — فمن أفسد يُعرَف لا يُخمَّن');
check($codeH !== 0, 'ويُسقط الجولةَ رمزًا (' . $codeH . ') — فالإفسادُ فشلٌ ولو نجحت تأكيداتُه');
@unlink($harm);

/* ══ ④ والنظيفُ يعود نظيفًا ═════════════════════════════════════════════ */
head('④ الاستعادة');
@unlink($tmpOk); @unlink($tmpBad);
$envFinal = is_file($envPath) ? (string) file_get_contents($envPath) : null;
check($envFinal === $envBefore, '`.env` سليمٌ في نهايةِ الجسّ');
check(!is_file($tmpOk) && !is_file($tmpBad) && !is_file($harm), 'ومسابرُ الاختبارِ أُزيلت كلُّها');

echo "\n" . str_repeat('═', 70) . "\n";
printf("ناجحٌ: %d · فاشلٌ: %d\n", $PASS, $FAIL);
echo "◆ أمرٌ واحدٌ (`composer test`) · رمزٌ صادقٌ في الاتجاهين · وحِمًى يمنع تكرارَ\n";
echo "  كارثةٍ وقعت فعلًا: مشغِّلُ اختباراتٍ أهلك `.env`.\n";
echo str_repeat('═', 70) . "\n";
exit($FAIL === 0 ? 0 : 1);
