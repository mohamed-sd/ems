<?php
/**
 * tests/edc_dead_vocab_not_a_zero.php — أيميّز الحارسُ الإغلاقَ من المفردةِ الميتة؟
 * ═══════════════════════════════════════════════════════════════════════════
 * **الواقعةُ التي وُلد منها**: كتبتُ `DC-08` بمفردةِ `LEGACY_READ_ONLY` —
 * **ومفرداتُ العمودِ الحيّةُ** `SOURCE/RETIRE/TARGET/MERGE/PROJECTION` لا غير.
 * فأعطى العدّادُ **صفرًا** ⇒ **وهو أخضرُ كاذبٌ لا إغلاق**، والحقيقةُ ثمانيةُ
 * أسطحٍ ماليّةٍ حيّةٍ مُعلَنةٍ للتقاعدِ أو الدمج.
 *
 * ◆ **ورابعُ ظهورٍ لهذا النمطِ في يومٍ واحد**: مفردةٌ لم تُقرأ من مصدرِها
 *   (‏مفرداتُ الموجات · `finance_debt_class` · وغيرُها). **فالعلاجُ حارسٌ لا
 *   تصحيحُ صفٍّ** — والحارسُ يُثبت بالمفردةِ الميتةِ الحقيقيّةِ نفسِها.
 *
 * التشغيل: php tests/edc_dead_vocab_not_a_zero.php   (خروج 0 = نجاح)
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
$TOOL = $ROOT . '/tools/repair01_edc_classify.php';
$pass = 0; $fail = 0;
$ok = function ($c, $m) use (&$pass, &$fail) {
    if ($c) { $pass++; echo "  ✔ $m\n"; } else { $fail++; echo "  ✘ $m\n"; }
};
/* يشغّل المصنِّفَ ويعيد رمزَ خروجِه ونصَّه */
$run = function () use ($TOOL) {
    $out = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($TOOL) . ' 2>&1', $out, $rc);
    return array($rc, implode("\n", $out));
};
$SEED = 'ZT-DEAD';
$conn->query("DELETE FROM repair01_debt_register WHERE class_code = '$SEED'");

echo "\n══ ① الحالُ النظيفُ يمرّ ══\n";
list($rc, $txt) = $run();
$ok($rc === 0, 'المصنِّفُ يخرج صفرًا — ولا مفردةَ ميتةً في الأصنافِ القائمة');
$ok(strpos($txt, 'ولا صفرَ من مفردةٍ ميتة') !== false, 'ويعلن ذلك صراحةً لا صمتًا');

echo "\n══ ② والحارسُ يعرف كيف يرسُب — بالمفردةِ الميتةِ الحقيقيّةِ نفسِها ══\n";
$conn->query("INSERT INTO repair01_debt_register
   (class_code, class_name_ar, measure_sql, blocking_level, assigned_wave, debt_owner, exit_criteria)
   VALUES ('$SEED', 'زرع اختبار سالب',
     \"SELECT COUNT(*) FROM repair01_screen_registry WHERE finance_debt_class = 'LEGACY_READ_ONLY'\",
     'MINOR', 'W16', 'DEP-08', 'يكنس في نهاية الفحص')");
list($rc, $txt) = $run();
$ok($rc === 1, '**رُسِّب** — والصفرُ من مفردةٍ ميتةٍ لم يُحسب إغلاقًا');
$ok(strpos($txt, 'LEGACY_READ_ONLY') !== false, 'وسمّى المفردةَ الميتةَ بعينِها');

echo "\n══ ③ ولا يُرسِّب البريءَ — مفردةٌ حيّةٌ تعطي صفرًا مشروعًا ══\n";
$conn->query("UPDATE repair01_debt_register SET measure_sql =
   \"SELECT COUNT(*) FROM repair01_screen_registry
      WHERE finance_debt_class = 'MERGE' AND owner_code = 'DEP-99'\"
 WHERE class_code = '$SEED'");
list($rc, $txt) = $run();
$ok($rc === 0, 'مفردةٌ حيّةٌ (`MERGE`) بصفرٍ حقيقيٍّ تمرّ — فالحارسُ يميّز ولا يعمّ');

echo "\n══ ④ الكنس ══\n";
$conn->query("DELETE FROM repair01_debt_register WHERE class_code = '$SEED'");
$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_debt_register WHERE class_code = '$SEED'")->fetch_row()[0];
$ok($n === 0, 'كُنس الزرعُ — ولا أثرَ للفحصِ في السجل');
list($rc, ) = $run();
$ok($rc === 0, 'والحالُ عاد نظيفًا');

echo "\n──────────────────────────────────────────────────────────\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
