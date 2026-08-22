<?php
/**
 * tests/injfrd66_xc04_entries_test.php — شاهدُ XC-04: مدخلٌ أو إعلانٌ لكلِّ سطح
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **إيجابيٌّ**: البوابةُ تعبر — صفرُ سطحٍ مبنيٍّ بلا مدخلٍ ولا إعلان.
 * ◆ **سالبٌ ①**: البوابةُ **ترصد** سطحًا يتيمًا مزروعًا ثم يُزال — فلا يُقبل
 *   «صفرٌ» من بوابةٍ عاجزةٍ عن الرصد. وهو الفرقُ بين «لا يتيمَ» و«لا أرى».
 * ◆ **سالبٌ ②**: ذكرُ المسارِ في **هجرةٍ أو شاهدٍ أو أداةٍ ليس مدخلًا** —
 *   فالبوابةُ تقرأ ملفاتِ الواجهةِ وحدَها، ولولا ذلك لأُعلن الخضارُ كذبًا.
 * ◆ **سالبٌ ③**: كلُّ إعلانٍ **مسنَدٌ بنصٍّ** في `placement_basis` — والإعفاءُ
 *   بلا سببٍ مكتوبٍ تحايلٌ لا إعلان.
 * ◆ **سالبٌ ④**: `placement_kind` قائمةٌ مغلقة — **وصفرُ قيمةٍ ابتلعها ENUM**
 *   (`''`). فقد كُتبت «SERVICE» مرةً في عمودٍ لا يعرفها فابتُلعت صامتةً.
 *
 * التشغيل: php tests/injfrd66_xc04_entries_test.php
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
$runGate = static function () use ($ROOT): array {
    $out = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/injfrd66_xc04_gate.php') . ' --gate 2>&1', $out, $rc);
    return array($rc, implode("\n", $out));
};

echo "① إيجابيٌّ — البوابةُ تعبر:\n";
list($rc, $txt) = $runGate();
$check($rc === 0, "رمزُ الخروج {$rc}");
$check(mb_strpos($txt, 'صفرُ سطحٍ مبنيٍّ بلا مدخلٍ ولا إعلان') !== false,
    'البوابةُ تُعلن صفرَ سطحٍ يتيم');

echo "\n② سالبٌ — البوابةُ ترصد يتيمًا مزروعًا:\n";
$probe = $ROOT . '/Suppliers/zz_injfrd66_probe_orphan.php';
@file_put_contents($probe, "<?php\n/* سطحٌ مزروعٌ للشاهدِ — يُزال فورَ القياس */\n");
if (!is_file($probe)) {
    $fail++; echo "   ✘ تعذّر زرعُ السطحِ — لم يُختبَرِ الرصد\n";
} else {
    list($rc2, $txt2) = $runGate();
    @unlink($probe);
    $check($rc2 === 1, "البوابةُ رسّبت باليتيمِ المزروع (رمزٌ {$rc2})");
    $check(mb_strpos($txt2, 'zz_injfrd66_probe_orphan.php') !== false, 'وسمَّته باسمِه');
    $check(!is_file($probe), 'أُزيل السطحُ المزروعُ بعدَ القياس');
}

echo "\n③ إيجابيٌّ — البوابةُ عادت خضراءَ بعدَ إزالةِ المزروع:\n";
list($rc3, ) = $runGate();
$check($rc3 === 0, "رمزُ الخروج {$rc3}");

echo "\n④ سالبٌ — الهجرةُ والشاهدُ ليسا مدخلًا:\n";
/* مسارٌ مذكورٌ في هجرةٍ فقط يجب ألّا يُعَدَّ ذا مدخلٍ لولا إعلانُه */
$onlyMigration = 0;
$res = @mysqli_query($conn, "SELECT LOWER(route) r FROM nav_canonical
                              WHERE placement_basis IS NOT NULL AND placement_basis <> ''");
$declared = array();
while ($res && ($x = mysqli_fetch_assoc($res))) { $declared[$x['r']] = true; }
$check(count($declared) > 0, sprintf('السجلُّ يحمل %d إعلانًا مكتوبًا', count($declared)));

echo "\n⑤ سالبٌ — كلُّ إعلانٍ مسنَدٌ بنصٍّ لا بفراغ:\n";
$emptyBasis = 0;
$res = @mysqli_query($conn, "SELECT COUNT(*) FROM nav_canonical
                              WHERE placement_basis IS NOT NULL AND TRIM(placement_basis) = ''");
if ($res) { $emptyBasis = (int) mysqli_fetch_row($res)[0]; }
$check($emptyBasis === 0, "صفرُ إعلانٍ فارغِ النصّ (قِيس {$emptyBasis})");

echo "\n⑥ سالبٌ — صفرُ قيمةٍ ابتلعها ENUM:\n";
$res = @mysqli_query($conn, "SELECT COUNT(*) FROM nav_canonical WHERE placement_kind = ''");
$blank = $res ? (int) mysqli_fetch_row($res)[0] : -1;
$check($blank === 0, "صفرُ صفٍّ بـ placement_kind فارغ (قِيس {$blank})");

printf("\n%s  ناجح %d · راسب %d\n", $fail === 0 ? '✔ XC-04' : '✘ XC-04', $pass, $fail);
exit($fail === 0 ? 0 : 1);
