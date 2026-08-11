<?php
/**
 * tools/fix_m10_gate_tests.php — INJ-0241 · INJ-0242: أتُميّز بوابةُ M-10 فعلًا؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ البندان يشتكيان **فاحصًا يصادق زورًا** لا عيبًا في المنتج:
 *   · INJ-0241 (AC-05): كان الشرطُ مطابقةَ الحروفِ t-a-b-l-e في مصدرِ الملف —
 *     وكلُّ ملفٍّ يحتويها، فيمرُّ أخضرَ على كلِّ شيءٍ ولا يرسب على شيء.
 *   · INJ-0242 (AC-06): كان عدَّ صفوفٍ ووجودَ جدول، يتحققان ببذرِ بياناتٍ بلا
 *     فحصِ شاشةٍ ولا نداءِ حارس.
 *
 * ◆ واختبارُ قبولِهما **لا يطلب أن يمرَّ المعيار** — يطلب أن **يرسب على
 *   المخالف**: «أنشئ ملفًّا فيه جدولٌ وحدَه: يجب أن يرسب AC-05» · «AC-06 يرسب
 *   ما دام هناك حقلٌ تصيّره شاشتُه بلا نداءِ حارس». فالمطلوبُ **تمييزٌ**.
 *
 * ◆ فيُجَسُّ التمييزُ في الاتجاهين: مُخالِفٌ **يُرصَد**، وسليمٌ **لا يُرصَد**.
 *   وفاحصٌ يرسب على الكلِّ عاجزٌ كفاحصٍ يمرُّ على الكل.
 *
 * التشغيل: php tools/fix_m10_gate_tests.php
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
$db = fix_db();
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

/** يُشغّل البوابةَ ويعيد سطرَ معيارٍ بعينِه وسطرَ شاهدِه. */
function gate_line($ROOT, $code)
{
    $out = (string) @shell_exec(escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg($ROOT . '/tools/m10_ac_gate.php') . ' 2>&1');
    $lines = explode("\n", $out);
    foreach ($lines as $i => $ln) {
        if (strpos($ln, $code) !== false) {
            return array('line' => trim($ln),
                         'detail' => isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '',
                         'pass' => strpos($ln, '✔') !== false);
        }
    }
    return array('line' => '', 'detail' => '', 'pass' => null);
}

/** عددُ المخالفاتِ من شاهدِ AC-06 — والتوسيمُ يلفُّ العبارةَ كلَّها لا الرقمَ وحدَه. */
function viol_count($detail)
{
    if (preg_match('/\*\*بلا حارس: (\d+)\*\*/u', $detail, $m)) { return (int) $m[1]; }
    if (preg_match('/بلا حارس:\s*\**\s*(\d+)/u', $detail, $m)) { return (int) $m[1]; }
    return null;
}

echo "══════════════════════════════════════════════════════════════════════\n";
echo " بوابةُ M-10: أتُميّز؟ — INJ-0241 · INJ-0242 · " . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n";

/* ══ ① AC-05 — تصييرٌ حيٌّ لا مطابقةُ نصّ ═══════════════════════════════ */
head('① AC-05: الشرطُ الخاوي أُبطل — والدليلُ من الناتجِ لا من المصدر');
/* ◆ گوتشا وقعتُ فيها وهي مسجَّلةٌ عندي: «مطابقةُ نصٍّ لا توسيم». أولُ صياغةٍ
     بحثت عن الشرطِ المُبطَل في مصدرِ الأداةِ فرصدته — وكان **في تعليقي أنا**
     الذي يشرحه. فالفاحصُ رصد شرحَ الإصلاحِ لا الإصلاحَ المخالف.
     **تُجرَّد التعليقاتُ قبل أيِّ عدّ.** */
$srcRaw = (string) file_get_contents($ROOT . '/tools/m10_ac_gate.php');
$src = function_exists('fix_strip_comments') ? fix_strip_comments($srcRaw) : $srcRaw;
if (!function_exists('fix_strip_comments')) {
    $src = preg_replace('#/\*.*?\*/#s', '', $src);
    $src = preg_replace('#^\s*//.*$#m', '', $src);
}
$emptyCond = strpos($src, "strpos(\$src, 'table') !== false") !== false
          || strpos($src, "strpos(\$src,'table') !== false") !== false;
check(!$emptyCond, 'الشرطُ الخاوي مرفوعٌ من **الشيفرةِ** (بعد تجريدِ التعليقات)');
check(strpos($src, 'fix_screen_view_evidence') !== false,
    'وحلَّ محلَّه دليلٌ من **تصييرٍ حيّ** (`fix_screen_view_evidence`)');

/* التمييزُ: ملفٌّ فيه جدولٌ وحدَه يجب أن يُرفض */
$tmpRel = 'Finance/_probe_bare_table.php';
$tmpAbs = $ROOT . '/' . $tmpRel;
file_put_contents($tmpAbs, "<?php /* مِسبار */ ?>\n<table><tr><td>1</td></tr></table>\n");
$ev = function_exists('fix_screen_view_evidence')
    ? fix_screen_view_evidence($ROOT, $tmpRel, '1')
    : array('ok' => null, 'reason' => 'الدالةُ غيرُ متاحة');
@unlink($tmpAbs);
check(empty($ev['ok']), 'وملفٌّ فيه جدولٌ وحدَه بلا منتقي منظرٍ **يُرفض** — '
    . mb_substr((string) ($ev['reason'] ?? ''), 0, 54));
check(!is_file($tmpAbs), 'ومِسبارُ الملفِّ أُزيل فلا يبقى أثرٌ في الشجرة');

/* ══ ② AC-06 — فحصُ تبنٍّ لا عدُّ صفوف ══════════════════════════════════ */
head('② AC-06: الشرطُ صار تبنّيًا — ويُجَسُّ في الاتجاهين');
check(strpos($src, '$polCount >= 20 && $readLog') === false,
    'شرطُ «عشرون صفًّا وجدولٌ موجود» مرفوعٌ من الأداة');
check(strpos($src, 'SensitiveFieldGuard::canRead|ems_log_sensitive_read') !== false,
    'وحلَّ محلَّه اشتراطُ نداءِ حارسٍ في السطحِ المُصيِّر');

$before = gate_line($ROOT, 'AC-06');
check($before['pass'] === false,
    'AC-06 **يرسب** على الحالِ الراهنة — وهو ما يطلبه اختبارُ القبولِ حرفيًّا');
$violBefore = viol_count($before['detail']);
check($violBefore !== null && $violBefore > 0,
    'ويُبلّغ عددًا موجبًا من المخالفات: ' . var_export($violBefore, true));

/* التمييزُ الموجب: حقلٌ **مُصيَّرٌ بحارسٍ** لا يُحسب مخالفًا.
   `Employees/employee_card.php` ينادي الحارسَ (مقيسٌ سابقًا) — فحقلٌ من جدولِه
   لا يجوز أن يُرصَد. يُدرَج صفًّا مؤقتًا ثم يُحذف. */
$CO = 4;
$db->query("DELETE FROM scr_sensitive_fields WHERE no_policy LIKE 'PROBE-M10-%'");
$okIns = $db->query("INSERT INTO scr_sensitive_fields
    (company_id, no_policy, table_name, field_name, classification_sensitivity,
     log_views_flag, status, status_label, is_seed, created_by, created_by_name, created_at, updated_at)
    VALUES ({$CO}, 'PROBE-M10-OK', 'employee_card', 'national_id', 'مِسبار', 'نعم',
            'معتمد', 'معتمد', 0, 0, 'مِسبار', NOW(), NOW())");
check($okIns !== false, 'أُدرج حقلٌ مِسباريٌّ على جدولٍ سطحُه ينادي الحارس');
$after = gate_line($ROOT, 'AC-06');
$violAfter = viol_count($after['detail']);
check($violAfter !== null && $violBefore !== null && $violAfter === $violBefore,
    'ولم يزد عددُ المخالفات (' . var_export($violBefore, true) . ' ← ' . var_export($violAfter, true)
    . ') — فالمحروسُ لا يُرصَد مخالفًا');
$db->query("DELETE FROM scr_sensitive_fields WHERE no_policy = 'PROBE-M10-OK'");

/* التمييزُ السالب: حقلٌ **مُصيَّرٌ بلا حارس** يجب أن يُرصَد */
$probeRel = 'Finance/_probe_unguarded.php';
$probeAbs = $ROOT . '/' . $probeRel;
file_put_contents($probeAbs,
    "<?php /* مِسبار — يُصيّر حقلًا حساسًا بلا حارس */\n"
  . "\$r = \$conn->query('SELECT probe_secret_col FROM fin_probe_tbl');\n"
  . "echo htmlspecialchars(\$row['probe_secret_col']);\n");
$db->query("INSERT INTO scr_sensitive_fields
    (company_id, no_policy, table_name, field_name, classification_sensitivity,
     log_views_flag, status, status_label, is_seed, created_by, created_by_name, created_at, updated_at)
    VALUES ({$CO}, 'PROBE-M10-BAD', 'fin_probe_tbl', 'probe_secret_col', 'مِسبار', 'نعم',
            'معتمد', 'معتمد', 0, 0, 'مِسبار', NOW(), NOW())");
$bad = gate_line($ROOT, 'AC-06');
$violBad = viol_count($bad['detail']);
check($violBad !== null && $violBefore !== null && $violBad === $violBefore + 1,
    'وحقلٌ يُطبَع بلا حارسٍ **يُرصَد**: المخالفاتُ ' . var_export($violBefore, true)
    . ' ← ' . var_export($violBad, true));
/* ◆ ولا يُشترط ظهورُ اسمِ المِسبارِ في سطرِ الشاهد: الشاهدُ يعرض **أربعَ**
     مخالفاتٍ (`array_slice($viol, 0, 4)`) والمِسبارُ الثانيةَ عشرة. وأولُ
     صياغةٍ اشترطته فرسبت — وكان الرسوبُ في **افتراضِ الاختبارِ** لا في البوابة.
     والدليلُ الكافي على الرصدِ هو ارتفاعُ العدد (11 ← 12) وقد تحقّق أعلاه.
     ويُشترط هنا ما يصحُّ اشتراطُه: أن الشاهدَ **يسمّي** مخالفاتٍ بأسمائها. */
check(preg_match('/[a-z_]+\.[a-z_]+ →/u', $bad['detail']) === 1,
    'وشاهدُ البوابةِ يسمّي المخالفاتِ بجدولِها وحقلِها ومسارِها (لا عددًا مجرَّدًا)');

@unlink($probeAbs);
$db->query("DELETE FROM scr_sensitive_fields WHERE no_policy LIKE 'PROBE-M10-%'");
check(!is_file($probeAbs), 'وأُزيل مِسبارُ الملفِّ فلا يبقى أثر');
$leftRs = $db->query("SELECT COUNT(*) FROM scr_sensitive_fields WHERE no_policy LIKE 'PROBE-M10-%'");
$left = $leftRs ? (int) $leftRs->fetch_row()[0] : -1;
check($left === 0, 'وأُزيلت صفوفُ المِسبارِ من القاعدة — الشجرةُ والقاعدةُ نظيفتان');

echo "\n" . str_repeat('═', 70) . "\n";
printf("ناجحٌ: %d · فاشلٌ: %d\n", $PASS, $FAIL);
echo "◆ والحكمُ: المعياران يميّزان — يرسبان على المخالفِ ولا يرسبان على السليم.\n";
echo "  ورسوبُ AC-05 و AC-06 اليومَ **هو الدليلُ** على أنهما صاروا يقيسان شيئًا،\n";
echo "  ولا يُقرأ الرسوبُ تراجعًا: البوابةُ كانت 15/15 كاذبةً فصارت 12/15 صادقة.\n";
echo str_repeat('═', 70) . "\n";
exit($FAIL === 0 ? 0 : 1);
