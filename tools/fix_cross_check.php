<?php
/**
 * tools/fix_cross_check.php — الأداةُ المستقلةُ الثانية (GT-02/4)
 * ═══════════════════════════════════════════════════════════════════════════
 * الحكم: «يقيس بأداتين مستقلتين **ويرسب عند اختلافهما**».
 *
 * ◆ لماذا: أداةٌ واحدةٌ تصادق على نفسِها مهما بلغت دقّتُها. وجردُ الوثائقِ
 *   (`fix_docs_inventory.php`) يقرأ **خريطةَ حالاتٍ مكتوبةً بيد** — وهي رأيُ
 *   من كتبها. فإن سهوتُ فوسمتُ رمزًا بـ«مُغلق» وشاهدُه أحمرُ، لم يكن في
 *   النظامِ ما يكذّبني.
 * ◆ فهذه الأداةُ لا تقرأ الخريطةَ إطلاقًا: تُشغِّل البوابات وتقرأ **شواهدَها
 *   الحيّة**، ثم تقابل. والاختلافُ رسوبٌ في الاتجاهين:
 *     ① وُسم «مُغلق» وشاهدُه أحمر  ⇒ ادّعاءٌ بلا شاهد.
 *     ② وُسم «مفتوح» وشاهدُه أخضر ⇒ خريطةٌ متعفّنة (تُبخس الإنجازَ وتُفقد الثقة).
 * ◆ والمصدرُ الوحيدُ المشترك هو **رمزُ المعيار** — لا رمزَ ولا منطقَ ولا ملفَّ
 *   بينهما، فالاستقلالُ حقيقيٌّ لا اسميّ.
 *
 * التشغيل: php tools/fix_cross_check.php
 * الخروج: 0 إن اتفقتا · 1 إن اختلفتا.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

/* ◆ لا تُقاس شجرةٌ وهي مُفسَدةٌ عمدًا: الاختباراتُ السلبيةُ تُبدّل ملفًّا ثم تعيده،
     فإن قِيس بينهما ظهر معيارٌ أخضرُ أحمرَ — وهو ما وقع فعلًا في أولِ تشغيلٍ
     لهذه الأداة: أُعلن AC-U10 مخالفًا وما كان به خلاف. فيُفحص سجلُّ الذممِ
     أولًا: ملفٌّ عالقٌ فيه يعني أن جولةً تعمل الآن أو ماتت قبلَ استعادتِها. */
$pending = (array) glob($ROOT . '/storage/neg_pending/*.json');
if ($pending) {
    echo "الشجرةُ مُفسَدةٌ الآن عمدًا (" . count($pending) . " ملفًّا مُبدَّلًا):\n";
    foreach ($pending as $p) {
        $rec = json_decode((string) @file_get_contents($p), true);
        echo '  · ' . basename($rec['abs'] ?? $p) . "\n";
    }
    exit("قياسٌ الآن يكذب. انتظرْ انتهاءَ الاختباراتِ السلبية، أو شغّلها لتسترجعَ العالق.\n");
}

/* ══ ① الأداةُ «أ» — المعلَن: خريطةُ الحالاتِ المكتوبةُ بيد ═══════════════ */
$declared = array();
$invSrc = (string) @file_get_contents($ROOT . '/tools/fix_docs_inventory.php');
if (preg_match('/\$CODE_STATE\s*=\s*array\(([\s\S]*?)\n\);/u', $invSrc, $m)) {
    $body = preg_replace('#/\*[\s\S]*?\*/|//[^\n]*#u', '', $m[1]);
    if (preg_match_all("/'([A-Za-z0-9-]+)'\s*=>\s*'([^']+)'/u", $body, $km, PREG_SET_ORDER)) {
        foreach ($km as $kv) { $declared[$kv[1]] = $kv[2]; }
    }
}
if (!$declared) { exit("تعذّرت قراءةُ خريطةِ الحالات — الأداةُ «أ» صامتة.\n"); }

/* ══ ② الأداةُ «ب» — المرصود: شواهدُ البواباتِ الحيّة ════════════════════ */
$observed = array();
$sources  = array();
foreach (array('fix_gate.php', 'fix_ui_gate.php') as $gate) {
    $out = (string) @shell_exec(escapeshellarg(PHP_BINARY) . ' '
         . escapeshellarg($ROOT . '/tools/' . $gate) . ' 2>&1');
    if (preg_match_all('/^(✔|✘)\s+([A-Za-z0-9\/_-]+)/mu', $out, $gm, PREG_SET_ORDER)) {
        foreach ($gm as $g) {
            $observed[$g[2]] = ($g[1] === '✔');
            $sources[$g[2]]  = $gate;
        }
    }
}
if (!$observed) { exit("لم تُنتج البواباتُ شواهدَ — الأداةُ «ب» صامتة.\n"); }

/* ══ ③ المقابلة ═══════════════════════════════════════════════════════════ */
echo "══════════════════════════════════════════════════════════════════════\n";
echo " الفحصُ المتقاطع (GT-02/4) — أداتان مستقلتان ترسبان عند اختلافهما\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";
printf("الأداةُ «أ» (المعلَن): %d رمزًا في خريطةِ الحالات\n", count($declared));
printf("الأداةُ «ب» (المرصود): %d شاهدًا حيًّا من البوابتين\n\n", count($observed));

$agree = array(); $clash = array(); $onlyDeclared = 0;
foreach ($declared as $code => $state) {
    /* الرمزُ الفرعيُّ يُقابَل بأصلِه: `AC-U7` تُرصد باسمِها، و`SH-08/4` جزءٌ من SH-08. */
    $obsKey = null;
    if (array_key_exists($code, $observed)) {
        $obsKey = $code;
    } else {
        foreach ($observed as $oc => $ok) {
            if (strpos($oc, $code . '/') === 0) { $obsKey = $oc; break; }
        }
    }
    if ($obsKey === null) { $onlyDeclared++; continue; }   // لا شاهدَ في البوابتين

    $green = $observed[$obsKey];
    $saysClosed = ($state === 'مُغلق');
    if ($saysClosed === $green) {
        $agree[] = $code;
    } else {
        $clash[] = array($code, $state, $green ? 'أخضر' : 'أحمر', $sources[$obsKey], $obsKey);
    }
}

printf("متقابلٌ فعلًا: %d رمزًا · بلا شاهدٍ في البوابتين: %d\n", count($agree) + count($clash), $onlyDeclared);
printf("متفقٌ: %d · مختلفٌ: %d\n\n", count($agree), count($clash));

if ($clash) {
    echo "◆ اختلافاتٌ — كلٌّ منها ادّعاءٌ بلا شاهدٍ أو خريطةٌ متعفّنة:\n";
    foreach ($clash as $c) {
        printf("  ✘ %-10s المعلَن «%s» · المرصود %s (%s ← %s)\n",
            $c[0], $c[1], $c[2], $c[4], $c[3]);
    }
    echo "\n";
}

echo str_repeat('═', 70) . "\n";
echo $clash
    ? ('النتيجة: رسوبٌ — الأداتان تختلفان في ' . count($clash) . " رمزًا\n")
    : ("النتيجة: اتفاقٌ تامٌّ — " . count($agree) . " رمزًا بشاهدٍ يطابق إعلانَه\n");
echo str_repeat('═', 70) . "\n";
exit($clash ? 1 : 0);
