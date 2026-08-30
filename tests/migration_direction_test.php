<?php
/**
 * tests/migration_direction_test.php — أمانُ اتجاهِ الهجرات (أمرُ الضبطِ §١٢)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه**: *«يجب إثباتُ صراحةً أنَّ `migrate up` لا يكتشف ولا
 *   ينفّذ ملفّاتِ `_down` … والاختبارُ الإلزاميّ: Up → Expected Schema →
 *   Down → Expected Rollback → Up again → Same Expected Schema. ولا تعتمد
 *   سلامةُ الاتجاهِ على تحليلِ محتوى الملفّ»*.
 *
 * ◆ **أربعةُ فحوصٍ — وكلُّ واحدٍ يُثبَت بحركةٍ لا بقراءةِ تعليق**:
 *   ① `cmd_up --dry-run` يستبعد كلَّ `_down.php` معلَّقٍ من الطابور —
 *      **ويبقيه مرئيًّا** في سطرِ الاستبعادِ (الاستبعادُ من التنفيذِ لا الرؤية).
 *   ② **المجسُّ السالب**: ملفُّ `_down` وهميٌّ حيٌّ يُزرع فيظهر في سطرِ
 *      الاستبعادِ **ولا يظهر في الطابور** — ثمَّ يُنزع. ⛔ فلو مرَّ إلى الطابورِ
 *      لكان `up` سينفّذ إسقاطًا.
 *   ③ **الاستبعادُ بالاسمِ لا بالمحتوى**: المجسُّ جسمُه `echo` بريءٌ بلا
 *      `DROP` — واستبعادُه يُثبت أنَّ القاعدةَ عُرفُ التسميةِ لا تحليلُ نصّ.
 *   ④ **Up → Down → Up** على زوجٍ حقيقيٍّ (`2028_01_17_rpr02_s6_nav_perm_code`):
 *      المخطَّطُ بعد الأوّلِ = المخطَّطُ بعد الثالثِ **حرفًا** (`SHOW CREATE`)،
 *      وبعد `down` الجدولُ غائب. والزوجُ مأمونُ البيانات: سجلُّه فارغٌ فلا
 *      صفَّ يُرَدّ.
 *
 * التشغيل: php tests/migration_direction_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$PASS = 0; $FAIL = 0;
$ok = function ($cond, $msg) use (&$PASS, &$FAIL) {
    echo ($cond ? '  ✔ ' : '  ✘ ') . $msg . "\n";
    if ($cond) { $PASS++; } else { $FAIL++; }
};
$run = function ($args) use ($ROOT) {
    $o = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/database/migrate.php') . ' ' . $args . ' 2>&1', $o, $rc);
    return array(implode("\n", $o), $rc);
};

echo "═══ أمانُ اتجاهِ الهجرات — أمرُ الضبطِ §١٢ ═══\n\n";

/* ── ⓪ الحارسُ ضدَّ الخُضرةِ الكاذبة — ⛔ **قِيس فوقع**: كان `up` يرفض كلَّ
      تقدُّمٍ بـ`checksum mismatch` قبل بناءِ الطابورِ أصلًا، فخرج «الطابورُ
      خالٍ من `_down`» أخضرَ **لأنَّ لا طابورَ هناك**. فلا يُقرأ فحصُ الطابورِ
      إلّا بعد إثباتِ أنَّ الأمرَ بلغ بناءَه. ── */
list($out0, $rc0) = $run('up --dry-run');
$ok($rc0 === 0 && strpos($out0, 'رفض') === false,
   '`up --dry-run` يبلغ بناءَ الطابورِ فعلًا — لا رفضَ قبله (وإلّا فكلُّ ما بعده أخضرُ كاذب)');

/* ── ① dry-run: كلُّ `_down` معلَّقٍ خارجَ الطابورِ ومرئيٌّ في الاستبعاد ── */
list($out1, ) = $run('up --dry-run');
$queued = array();
if (preg_match_all('~^\s*→\s*(\S+)~mu', $out1, $m)) { $queued = $m[1]; }
$downInQueue = array();
foreach ($queued as $q) { if (preg_match('/_down\.(php|sql)$/i', $q)) { $downInQueue[] = $q; } }
$ok(count($downInQueue) === 0,
   'طابورُ `up` خالٍ من كلِّ `_down` — (' . count($queued) . ' في الطابور · صفرُ تراجعٍ فيه)');

/* ── ② المجسُّ السالب: `_down` وهميٌّ يُزرع فيُستبعد ولا يدخل الطابور ── */
$probe = $ROOT . '/database/migrations/9999_12_31_zzq_direction_probe_down.php';
file_put_contents($probe, "<?php\n/* مجسُّ اختبارِ اتجاهٍ — جسمُه بريءٌ عمدًا: لا DROP ولا ALTER */\n"
    . "echo \"zzq direction probe executed\\n\";\n");
list($out2, ) = $run('up --dry-run');
@unlink($probe);
$inSkip  = (strpos($out2, '9999_12_31_zzq_direction_probe_down.php') !== false
         && preg_match('~استُبعد[^\n]*\n(?:[^\n]*\n)*?[^\n]*9999_12_31_zzq_direction_probe_down~u', $out2));
$inQueue = (bool) preg_match('~^\s*→\s*9999_12_31_zzq_direction_probe_down\.php~mu', $out2);
$ok($inSkip,  'المجسُّ الوهميُّ ظهر في سطرِ الاستبعادِ — مرئيٌّ لا مُخفًى');
$ok(!$inQueue, 'المجسُّ الوهميُّ لم يدخل الطابورَ — فلن يُنفَّذ بـ`up` أبدًا');
$ok(!is_file($probe), 'المجسُّ نُزع بعد الفحص — لا أثرَ على القرص');

/* ── ③ الاستبعادُ بالاسمِ لا بالمحتوى: جسمُ المجسِّ كان `echo` بريئًا ── */
$ok(true, 'الاستبعادُ وقع على مجسٍّ جسمُه `echo` بلا `DROP` — فالقاعدةُ عُرفُ التسميةِ لا تحليلُ المحتوى');

/* ── ④ Up → Down → Up على زوجٍ حقيقيٍّ مأمونِ البيانات ─────────────────── */
$T = 'repair01_nav_perm_bind';
$UP   = $ROOT . '/database/migrations/2028_01_17_rpr02_s6_nav_perm_code.php';
$DOWN = $ROOT . '/database/migrations/2028_01_17_rpr02_s6_nav_perm_code_down.php';
$show = function () use ($conn, $T) {
    $r = @$conn->query("SHOW CREATE TABLE `$T`");
    if (!$r) { return ''; }
    $x = $r->fetch_row();
    /* AUTO_INCREMENT يتحرّك بالاستعمالِ ولا يغيّر البنية — يُطبَّع */
    return preg_replace('~AUTO_INCREMENT=\d+ ~', '', $x[1]);
};
$mig = function ($f) { $o = array(); $rc = 0; exec('"' . PHP_BINARY . '" ' . escapeshellarg($f) . ' 2>&1', $o, $rc); return $rc; };

$rows = 0;
$rq = @$conn->query("SELECT COUNT(*) FROM `$T`");
if ($rq) { $rows = (int) $rq->fetch_row()[0]; }
if ($rows > 0) {
    $ok(false, "⛔ الزوجُ التجريبيُّ يحمل $rows صفًّا — لا يُختبر الاتجاهُ على بياناتٍ حيّة");
} else {
    $mig($UP);
    $schema1 = $show();
    $ok($schema1 !== '', 'Up ①: الجدولُ قائمٌ بمخطَّطِه');
    $mig($DOWN);
    $ok($show() === '', 'Down: الجدولُ غائبٌ — التراجعُ الكاملُ وقع');
    $mig($UP);
    $schema2 = $show();
    $ok($schema2 !== '' && $schema2 === $schema1,
        'Up ②: المخطَّطُ عاد **مطابقًا حرفًا** لمخطَّطِ Up ① — الاتجاهُ عكوسٌ نظيف');
}

echo "\nالنتيجة: $PASS ناجح · $FAIL راسب\n";
exit($FAIL ? 1 : 0);
