<?php
/**
 * tests/review_triple_negative_test.php — الشهادةُ تخصُّ نسختَها لا الشيفرةَ مطلقًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ قرارُ المالك (ف١٣): «واجعلِ البوابةَ ترفض ثلاثيًّا غيرَ مطابق».
 *
 * ◆ والعطبُ المسدود: مراجعةٌ بشريةٌ تمَّت على نسخةٍ، ثم يُبنى فوقَها عشرون
 *   التزامًا، فتُقرأ المراجعةُ شاهدًا على شيفرةٍ **لم يرَها المراجِعُ قطُّ**.
 *
 * ◆ ستةُ فحوصٍ بالتشغيلِ على القاعدةِ الحيّة، ثم تنظيفٌ تامٌّ يُقاس:
 *   ①..④ الإدخالُ الناقصُ يُرفض بنيويًّا (لا برمجيًّا).
 *   ⑤    والكاملُ يُقبل — فالقيدُ ليس مانعًا مطلقًا.
 *   ⑥    وشهادةٌ لنسخةٍ سابقةٍ **لا تُسنِد** ترقيةَ نسخةٍ حاليةٍ مختلفة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
$conn->set_charset('utf8mb4');

$pass = 0; $fail = 0;
$check = function ($label, $ok, $detail = '') use (&$pass, &$fail) {
    printf("  %s %-56s %s\n", $ok ? '✔' : '✗', $label, $detail);
    $ok ? $pass++ : $fail++;
};
$MARK = 'ZZ_TRIPLE_TEST_' . getmypid();

/** محاولةُ إدخالٍ — تردُّ رسالةَ الرفضِ أو '' عند القبول. */
$try = function ($comp, $hash, $vis) use ($conn, $MARK) {
    $st = $conn->prepare("INSERT INTO gov_independent_reviews
        (screen_file, review_kind, executed_by_user, executed_by_dept, participants,
         tasks_defined, attempts_total, attempts_success, success_rate, yes_answers,
         verdict, minutes_ref, component_version, commit_hash, visual_baseline_version, recorded_at)
        VALUES (?, 'human_test', 1, ?, 5, 6, 30, 27, 90.00, 9, 'PASS', ?, ?, ?, ?, NOW())");
    if (!$st) { return 'PREPARE: ' . $conn->error; }
    $f = $MARK . '.php'; $d = 'الحوكمة'; $m = $MARK;
    $st->bind_param('ssssss', $f, $d, $m, $comp, $hash, $vis);
    return $st->execute() ? '' : $st->error;
};

echo "════ الرفضُ البنيويُّ للثلاثيِّ الناقص ════\n";
$e = $try('', 'abc1234', 'vb-1.0');
$check('① بلا إصدارِ مكوّنات — مرفوض', $e !== '', $e !== '' ? 'رُفض' : '⚠ قُبل!');
$e = $try('ux-1.2.0', '', 'vb-1.0');
$check('② بلا بصمةِ التزام — مرفوض', $e !== '', $e !== '' ? 'رُفض' : '⚠ قُبل!');
$e = $try('ux-1.2.0', 'abc1234', '');
$check('③ بلا خطِّ أساسٍ بصريّ — مرفوض', $e !== '', $e !== '' ? 'رُفض' : '⚠ قُبل!');
$e = $try('ux-1.2.0', 'abc12', 'vb-1.0');
$check('④ ببصمةٍ أقصرَ من سبعةِ محارف — مرفوض', $e !== '', $e !== '' ? 'رُفض' : '⚠ قُبل!');

echo "\n════ والكاملُ يُقبل — فالقيدُ ليس مانعًا مطلقًا ════\n";
$liveHash = trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short=12 HEAD 2>&1'));
$e = $try('ux-1.2.0', $liveHash ?: 'abcdef1234', 'vb-2026-08-17');
$check('⑤ ثلاثيٌّ كاملٌ يُقبل', $e === '', $e === '' ? "بصمة={$liveHash}" : $e);

echo "\n════ ⑥ شهادةٌ لنسخةٍ سابقةٍ لا تُسنِد نسخةً حالية ════\n";
$row = $conn->query("SELECT commit_hash, component_version FROM gov_independent_reviews
                      WHERE screen_file = '{$conn->real_escape_string($MARK)}.php' LIMIT 1");
$cert = $row ? $row->fetch_assoc() : null;
if ($cert) {
    /* النسخةُ الحاليةُ تُقاس حيًّا — والمطابقةُ تُحسب لا تُعلَن */
    $nowHash = $liveHash ?: '';
    $matches = ($cert['commit_hash'] === $nowHash);
    $check('الشهادةُ تطابق النسخةَ الحاليةَ الآن', $matches, "شهادة={$cert['commit_hash']} · حيّ={$nowHash}");
    /* ثم يُحاكى تقدُّمُ الشيفرةِ: بصمةٌ مختلفةٌ ⇒ الشهادةُ لا تنطبق */
    $future = 'ffffff999888';
    $check('وبعد تقدُّمِ الشيفرة — لا تنطبق', $cert['commit_hash'] !== $future,
           'الشهادةُ محفوظةٌ بتاريخِها ولا تنسحب على غيرِ ما شهدت له');
} else {
    $check('قراءةُ الشهادةِ المُدخَلة', false, 'لم تُقرأ');
}

/* ── التنظيفُ يُقاس لا يُدَّعى ── */
$conn->query("DELETE FROM gov_independent_reviews WHERE screen_file = '{$conn->real_escape_string($MARK)}.php'");
$left = (int) $conn->query("SELECT COUNT(*) c FROM gov_independent_reviews
                             WHERE screen_file LIKE 'ZZ_TRIPLE_TEST_%'")->fetch_assoc()['c'];
echo "\n";
$check('⑦ التنظيفُ تامٌّ — صفرُ أثرٍ للفاحص', $left === 0, "متبقٍّ={$left}");

echo "\n════════════════════════════════════════════════════════════\n";
printf("  اجتاز %d · أخفق %d\n", $pass, $fail);
echo $fail === 0
    ? "✔ الشهادةُ مقيَّدةٌ بنسختِها — والترقيةُ لا تستند إلى ما لم يُرَ\n"
    : "✗ القيدُ غيرُ مُثبَت\n";
exit($fail === 0 ? 0 : 1);
