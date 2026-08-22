<?php
/**
 * tests/injfrd66_xc08_sod_test.php — شاهدُ XC-08: فصلُ الواجبات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **إيجابيٌّ ①**: البوابةُ تعبر — صفرُ جمعٍ حيٍّ لخانتَين.
 * ◆ **إيجابيٌّ ②**: الحارسُ **قائمٌ في الخدمةِ نصًّا** لا في الواجهةِ وحدَها —
 *   `SettlementService` يردُّ 403 «لا يعتمد المرءُ ما أعدّ». والشرطُ في طبقةِ
 *   الخدمةِ هو ما يشترطه `XC-07` («الشرطُ مفحوصٌ في الخدمة»).
 * ◆ **سالبٌ ③**: الدَّينُ التاريخيُّ **مؤرَّخٌ لا مطموس**: أحدثُ مخالفةٍ
 *   **أقدمُ** من أحدثِ صفٍّ مقيس — فالمتأخّرونَ ملتزمون. ولو انقلب الترتيبُ
 *   لعادت خرقًا حيًّا يُرسِّب.
 * ◆ **سالبٌ ④**: صفرٌ في خانةِ الفاعلِ **ليس فاعلًا** — فصفوفُ `0` تُستبعد،
 *   وإلا قُرئ المجهولُ مطابقًا فظهرت مخالفاتٌ وهمية.
 * ◆ **سالبٌ ⑤**: البوابةُ **لا تُخضِّر ما لا تقيس** — عمليةٌ بلا خانتَي منشئٍ
 *   ومعتمِدٍ تُعلَن «غيرَ مقيسة» لا «مفصولة».
 *
 * التشغيل: php tests/injfrd66_xc08_sod_test.php
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
$str = static function (string $sql) use ($conn): string {
    $r = @mysqli_query($conn, $sql);
    $v = $r ? mysqli_fetch_row($r)[0] : null;
    return $v === null ? '' : (string) $v;
};

echo "① إيجابيٌّ — بوابةُ XC-08 تعبر:\n";
$out = array(); $rc = 0;
exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/injfrd66_xc08_sod_gate.php') . ' --gate 2>&1', $out, $rc);
$txt = implode("\n", $out);
$check($rc === 0, "رمزُ الخروج {$rc}");
$check(preg_match('~أحمر\s+0~u', $txt) === 1, 'صفرُ جمعٍ حيٍّ لخانتَين');

echo "\n② إيجابيٌّ — الحارسُ في طبقةِ الخدمةِ نصًّا:\n";
$svc = (string) @file_get_contents($ROOT . '/app/Services/Settlement/SettlementService.php');
$check($svc !== '', 'SettlementService مقروء');
$check(mb_strpos($svc, "intval(\$st['prepared_by']) === intval(\$userId)") !== false,
    'شرطُ «المُعِدُّ ≠ المعتمِد» قائمٌ في الخدمة');
$check(mb_strpos($svc, 'فصلُ اليدين') !== false && mb_strpos($svc, '403') !== false,
    'ويردُّ 403 بسببٍ مكتوب');

echo "\n③ سالبٌ — الدَّينُ التاريخيُّ مؤرَّخٌ لا مطموس:\n";
$W = "prepared_by IS NOT NULL AND prepared_by <> 0 AND approved_by IS NOT NULL AND approved_by <> 0";
$bad  = $num("SELECT COUNT(*) FROM settlements WHERE is_deleted=0 AND {$W} AND prepared_by = approved_by");
$mxB  = $str("SELECT MAX(created_at) FROM settlements WHERE is_deleted=0 AND {$W} AND prepared_by = approved_by");
$mxA  = $str("SELECT MAX(created_at) FROM settlements WHERE is_deleted=0 AND {$W}");
if ($bad > 0) {
    $check($mxB !== '' && $mxA !== '' && $mxB < $mxA,
        sprintf('%d مخالفةً تاريخيةً · أحدثُها %s وأحدثُ صفٍّ %s', $bad, substr($mxB, 0, 10), substr($mxA, 0, 10)));
} else { $check(true, 'صفرُ مخالفةٍ حتى تاريخية'); }

echo "\n④ سالبٌ — الصفرُ في خانةِ الفاعلِ ليس فاعلًا:\n";
/* الخطرُ المحدَّد: صفٌّ **طرفاه مجهولان معًا** يُقرأ «متطابقًا» في العدِّ
   الساذج (`0 <=> 0` صحيح) فيُحسب مخالفةً وهو لا يُثبت شيئًا. فيُقاس
   عددُهما، ويُتحقَّق أنَّ العدَّ المنضبطَ لا يشملهم. */
$bothUnknown = $num("SELECT COUNT(*) FROM settlements WHERE is_deleted=0
                       AND (prepared_by IS NULL OR prepared_by=0)
                       AND (approved_by IS NULL OR approved_by=0)");
$oneUnknown  = $num("SELECT COUNT(*) FROM settlements WHERE is_deleted=0
                       AND ((prepared_by IS NULL OR prepared_by=0) XOR (approved_by IS NULL OR approved_by=0))");
$naive = $num("SELECT COUNT(*) FROM settlements WHERE is_deleted=0 AND prepared_by <=> approved_by");
$check($naive - $bothUnknown === $bad,
    sprintf('الساذجُ %d − المجهولانِ معًا %d = المنضبطُ %d', $naive, $bothUnknown, $bad));
$check($bad === $num("SELECT COUNT(*) FROM settlements WHERE is_deleted=0 AND {$W} AND prepared_by = approved_by"),
    sprintf('المنضبطُ يستبعد %d صفًّا مجهولَ طرفٍ واحدٍ و%d مجهولَ الطرفَين', $oneUnknown, $bothUnknown));

echo "\n⑤ سالبٌ — البوابةُ لا تُخضِّر ما لا تقيس:\n";
$check(mb_strpos($txt, 'غيرُ مقيسٍ') !== false, 'تُعلن «غيرَ مقيس» صراحةً');
$check(preg_match('~أخضر\s+(\d+)~u', $txt, $m) === 1 && (int) $m[1] === 0,
    'ولم تُعلن خضراءَ عمليةً لم تقسها');

printf("\n%s  ناجح %d · راسب %d\n", $fail === 0 ? '✔ XC-08' : '✘ XC-08', $pass, $fail);
exit($fail === 0 ? 0 : 1);
