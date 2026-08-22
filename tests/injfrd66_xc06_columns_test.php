<?php
/**
 * tests/injfrd66_xc06_columns_test.php — شاهدُ XC-06: بوابتا الأعمدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما يُثبته هذا الشاهدُ وما لا يُثبته** — والتصريحُ بالحدِّ جزءٌ من الصدق:
 *   **يُثبت**: أنَّ الألفَ والأربعمئةِ والسبعةَ عشرَ حقلًا **كلَّها محكومة** —
 *   لكلِّ حقلٍ درجةٌ من العشرِ وقاعدةُ ظهورٍ ومتطلبٌ حاكمٌ من الستةِ والستين،
 *   والمقامانِ 589 و828 كما في المرجعِ الحاكم. وهذا **نصُّ معيارِ القبول**.
 *   **ولا يُثبت**: أنَّ كلَّ حقلٍ درجتُه «إدخالُ أعمال» له خانةُ إدخالٍ
 *   مُصيَّرةٌ على شاشةٍ حيّة — فالمصفوفةُ لا تحمل عمودَ الشاشةِ ولا العمود.
 *   ذلك قياسٌ آخرُ يحتاج خريطةَ حقلٍ ⇐ شاشة، وهي غيرُ موجودة.
 *
 * ◆ **إيجابيٌّ ①**: البوابةُ تعبر بصفرِ نقص.
 * ◆ **إيجابيٌّ ②**: المصدرُ **في الشجرةِ** لا في مجلَّدِ التنزيلات.
 * ◆ **سالبٌ ③**: البوابةُ **ترسُب** بصفٍّ مزروعٍ بلا حكم — فصفرُها رؤيةٌ لا عمًى.
 * ◆ **سالبٌ ④**: الجدولُ وملفُّ المصدرِ **متطابقانِ عددًا** — فلا تتفرَّق نسختان.
 *
 * التشغيل: php tests/injfrd66_xc06_columns_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$pass = 0; $fail = 0;
$check = static function (bool $ok, string $msg) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "   ✔ {$msg}\n"; } else { $fail++; echo "   ✘ {$msg}\n"; }
};
$num = static function (string $sql) use ($conn): int {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};
$runGate = static function () use ($ROOT): array {
    $o = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/injfrd66_xc06_columns_gate.php') . ' --gate 2>&1', $o, $rc);
    return array($rc, implode("\n", $o));
};

echo "① إيجابيٌّ — البوابةُ تعبر:\n";
list($rc, $txt) = $runGate();
$check($rc === 0, "رمزُ الخروج {$rc}");
$check(mb_strpos($txt, '589/589') !== false && mb_strpos($txt, '828/828') !== false,
    'المقامانِ 589/589 و828/828');
$check(mb_strpos($txt, '1417/1417') !== false, 'الإجمالي 1417/1417');

echo "\n② إيجابيٌّ — المصدرُ في الشجرةِ لا في التنزيلات:\n";
$src = $ROOT . '/docs/injfrd66/field_trace.tsv';
$check(is_file($src), 'docs/injfrd66/field_trace.tsv محفوظٌ في المستودع');
$check($num("SELECT COUNT(*) FROM gov_field_trace") === 1417, 'gov_field_trace يحمل 1417 صفًّا');

echo "\n③ سالبٌ — البوابةُ ترسُب بصفٍّ بلا حكم:\n";
$probeCell = 'ZZ999';
@mysqli_query($conn, "INSERT INTO gov_field_trace
        (book,sheet,sheet_no,header_cell,field_no,field_name,to_be,visibility_rule,req_id)
     VALUES ('المبيعات التعاقدية','00_مسبار_الشاهد',0,'{$probeCell}',0,'حقلٌ مزروعٌ للشاهد','','','SAL-01')");
$planted = ($num("SELECT COUNT(*) FROM gov_field_trace WHERE header_cell='{$probeCell}'") === 1);
if (!$planted) {
    $fail++; echo "   ✘ تعذّر زرعُ الصفِّ — لم يُختبَرِ الرصد\n";
} else {
    list($rc2, $txt2) = $runGate();
    @mysqli_query($conn, "DELETE FROM gov_field_trace WHERE header_cell='{$probeCell}'");
    $check($rc2 === 1, "البوابةُ رسّبت بالصفِّ المزروع (رمزٌ {$rc2})");
    $check(mb_strpos($txt2, 'حقولٌ بلا حكم: 1') !== false, 'وسمَّت السببَ: حقلٌ بلا حكم');
    $check($num("SELECT COUNT(*) FROM gov_field_trace") === 1417, 'أُزيل الصفُّ المزروعُ بعدَ القياس');
}

echo "\n④ إيجابيٌّ — البوابةُ عادت خضراءَ:\n";
list($rc3, ) = $runGate();
$check($rc3 === 0, "رمزُ الخروج {$rc3}");

echo "\n⑤ سالبٌ — الجدولُ والملفُّ لا يتفرَّقان:\n";
$fileRows = 0;
if (is_file($src)) {
    $fh = fopen($src, 'r');
    fgets($fh);                                   /* الترويسة */
    while (($l = fgets($fh)) !== false) { if (trim($l) !== '') { $fileRows++; } }
    fclose($fh);
}
$check($fileRows === $num("SELECT COUNT(*) FROM gov_field_trace"),
    sprintf('الملفُّ %d صفًّا والجدولُ %d', $fileRows, $num("SELECT COUNT(*) FROM gov_field_trace")));

echo "\n⑥ حدُّ هذا الشاهدِ — مُعلَنٌ لا مسكوتٌ عنه:\n";
$inputs = $num("SELECT COUNT(*) FROM gov_field_trace WHERE to_be='إدخال أعمال'");
printf("   ⏸ %d حقلًا درجتُه «إدخالُ أعمال» — محكومةٌ بالمصفوفةِ، **ولم يُقَسْ**\n", $inputs);
echo "      أنَّ لكلٍّ منها خانةَ إدخالٍ مُصيَّرةً على شاشةٍ حيّة. والمصفوفةُ لا\n";
echo "      تحمل عمودَ الشاشةِ ولا العمود، فالقياسُ يحتاج خريطةَ حقلٍ ⇐ شاشة.\n";

/* ── ⑦ XC-10 — قاموسُ قواعدِ الاستنتاجِ يُقاس من المصفوفةِ نفسِها ────────
   ◆ المعيارُ الحرفيّ: «صفرُ حقلٍ مشتقٍّ بلا قاعدةٍ مسجَّلة» — ويُقاس هنا.
   ◆ **ونصُّ المتطلبِ أوسعُ من معيارِه**: «قاعدةٌ معلنةٌ **بمصدرِها وقيدِها
     وتصنيفِ حجيتِها**». والمقيسُ أنَّ القواعدَ **صنفيّةٌ لا حقليّة** — جملةٌ
     واحدةٌ لكلِّ درجة. فيُعلَن الفرقُ ولا يُبتلع. */
echo "
⑦ XC-10 — قاعدةٌ لكلِّ حقلٍ مشتق:
";
$DRV = "to_be IN ('مشتق محسوب','موروث بالإسناد من الأب','لقطة تعاقدية')";
$derived  = $num("SELECT COUNT(*) FROM gov_field_trace WHERE {$DRV}");
$noRule   = $num("SELECT COUNT(*) FROM gov_field_trace WHERE {$DRV} AND (visibility_rule='' OR visibility_rule='—')");
$distinct = $num("SELECT COUNT(DISTINCT visibility_rule) FROM gov_field_trace WHERE {$DRV}");
$check($noRule === 0, sprintf('%d حقلًا مشتقًّا · صفرُ حقلٍ بلا قاعدة', $derived));
printf("   ⏸ والقواعدُ **صنفيّةٌ لا حقليّة**: %d قاعدةً متمايزةً لـ%d حقل.
", $distinct, $derived);
echo "      ونصُّ XC-10 يشترط «مصدرَها وقيدَها وتصنيفَ حجيتها» — فالمعيارُ
";
echo "      الحرفيُّ مستوفًى والنصُّ الأوسعُ لا. فقبولُه «جزئي» لا «نعم».
";

printf("\n%s  ناجح %d · راسب %d\n", $fail === 0 ? '✔ XC-06' : '✘ XC-06', $pass, $fail);
exit($fail === 0 ? 0 : 1);
