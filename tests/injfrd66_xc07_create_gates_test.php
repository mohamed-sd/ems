<?php
/**
 * tests/injfrd66_xc07_create_gates_test.php — شاهدُ XC-07: أزرارُ الإنشاءِ ببواباتِها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **إيجابيٌّ ①**: صفرُ سطحٍ في الإدارتَين يكتب واقعةَ أعمالٍ بلا حارسٍ في الخدمة.
 * ◆ **سالبٌ ②**: البوابةُ **ترسُب** بسطحٍ مزروعٍ يكتب بلا حارس — فصفرُها رؤيةٌ لا عمًى.
 * ◆ **سالبٌ ③**: و**لا ترسُب** بكتابةٍ في جدولِ أثرٍ أو رفض — «الأثرُ لكلِّ رفض»
 *   واجبٌ يفرضه البابُ الثاني، وعدُّه خرقًا يُحمِّر نظامًا سليمًا.
 * ◆ **سالبٌ ④**: الحارسُ الحقيقيُّ في **الخدمة**: `SupplierClosureService`
 *   و`SupplierEvaluationService` يُستدعيان قبلَ الانتقالِ لا بعدَه.
 *
 * ◆ **ولا يُعدُّ الزرُّ عدًّا نصّيًّا**: «إضافة/إنشاء» تَرِد مئاتِ المراتِ
 *   تعليقًا ووسمًا — والوثيقةُ تعدُّ ثمانيةَ عشرَ زرًّا، والعدُّ النصّيُّ يعطي
 *   مئاتٍ. فالمقياسُ على **موضعِ الفحصِ** لا على ظهورِ الزر.
 *
 * التشغيل: php tests/injfrd66_xc07_create_gates_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $msg) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "   ✔ {$msg}\n"; } else { $fail++; echo "   ✘ {$msg}\n"; }
};
$runGate = static function () use ($ROOT): array {
    $o = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/injfrd66_xc07_create_gates.php') . ' --gate 2>&1', $o, $rc);
    return array($rc, implode("\n", $o));
};

echo "① إيجابيٌّ — البوابةُ تعبر:\n";
list($rc, $txt) = $runGate();
$check($rc === 0, "رمزُ الخروج {$rc}");
$check(preg_match('~بلا حارسٍ في الخدمة:\s*0~u', $txt) === 1, 'صفرُ سطحٍ يكتب بلا حارس');

echo "\n② سالبٌ — البوابةُ ترسُب بكتابةٍ مزروعةٍ بلا حارس:\n";
$probe = $ROOT . '/Clients/zz_injfrd66_bare_write.php';
@file_put_contents($probe, "<?php\n/* مسبارٌ — يُزال فورَ القياس */\n"
    . "\$sql = \"INSERT INTO quotations (client_id, amount_total) VALUES (1, 5)\";\n");
if (!is_file($probe)) { $fail++; echo "   ✘ تعذّر الزرع\n"; }
else {
    list($rc2, $txt2) = $runGate();
    @unlink($probe);
    $check($rc2 === 1, "رسّبت بالمسبارِ (رمزٌ {$rc2})");
    $check(mb_strpos($txt2, 'zz_injfrd66_bare_write.php') !== false, 'وسمَّته باسمِه');
    $check(mb_strpos($txt2, 'بلا حارسٍ في الخدمة') !== false, 'وسمَّت السببَ');
    $check(!is_file($probe), 'أُزيل المسبار');
}

echo "\n③ سالبٌ — ولا ترسُب بقيدِ رفضٍ أو أثر:\n";
$probe2 = $ROOT . '/Clients/zz_injfrd66_denial_write.php';
@file_put_contents($probe2, "<?php\n/* مسبارٌ — قيدُ رفضٍ لا إنشاء */\n"
    . "\$sql = \"INSERT INTO guard_denials (reason, ref) VALUES ('منع', 'X')\";\n");
if (!is_file($probe2)) { $fail++; echo "   ✘ تعذّر الزرع\n"; }
else {
    list($rc3, $txt3) = $runGate();
    @unlink($probe2);
    $check($rc3 === 0, "لم ترسُب بقيدِ الرفضِ (رمزٌ {$rc3}) — «الأثرُ لكلِّ رفض» واجبٌ لا خرق");
    $check(mb_strpos($txt3, 'أثرٌ/رفضٌ فقط') !== false, 'وصنَّفته أثرًا لا إنشاءً');
    $check(!is_file($probe2), 'أُزيل المسبار');
}

echo "\n④ إيجابيٌّ — البوابةُ عادت خضراءَ بعدَ إزالةِ المسبارَين:\n";
list($rc4, ) = $runGate();
$check($rc4 === 0, "رمزُ الخروج {$rc4}");

echo "\n⑤ إيجابيٌّ — الحارسُ يُستدعى قبلَ الفعلِ لا بعدَه:\n";
$svc = (string) @file_get_contents($ROOT . '/app/Services/Contract/SupplierContractService.php');
$posClose = mb_strpos($svc, 'contractCloseGate');
$posWrite = mb_strpos($svc, "\$gate->update('supplier_contracts'");
$check($posClose !== false && $posWrite !== false && $posClose < $posWrite,
    'بوابةُ الإقفالِ تُستدعى قبلَ كتابةِ الحالة');
$posEval = mb_strpos($svc, 'renewalGate');
$check($posEval !== false && $posWrite !== false && $posEval < $posWrite,
    'وبوابةُ التقييمِ للتجديدِ كذلك');

printf("\n%s  ناجح %d · راسب %d\n", $fail === 0 ? '✔ XC-07' : '✘ XC-07', $pass, $fail);
exit($fail === 0 ? 0 : 1);
