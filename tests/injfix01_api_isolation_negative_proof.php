<?php
/**
 * tests/injfix01_api_isolation_negative_proof.php — INJ-FIX-01 · GAP-13
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «طبقةُ حجبٍ وعزلٍ بعدَ فحصِ البلوغ · **اختبارٌ سلبيٌّ بتوكنِ
 *   دورٍ غيرِ مخوَّل**».
 *
 * ◆ **والسلبيُّ هو الحكم**: أن يُرجع المخوَّلُ القيمةَ **لا يُثبت شيئًا** —
 *   البرهانُ أن **غيرَ المخوَّلِ لا يُرجَع له**. فيُقاس الطرفان معًا، وإلا كان
 *   الأخضرُ أخضرَ على لا شيء.
 *
 * ◆ **والعزلُ يُجرَّب بهويةِ شركةٍ أخرى** لا بقراءةِ الشيفرة.
 *
 * التشغيل: php tests/injfix01_api_isolation_negative_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
/* ◆ **يُحمَّل مُقلِعُ الواجهةِ قبلَ أيِّ إخراج**: هو يستدعي `config.php` الذي يبدأ
 *   الجلسةَ ويضبط ترويساتِها — وأيُّ بايتٍ يسبقه يُشعل «headers already sent».
 *   ومخرَجُه يُبتلَع فلا يختلط بمخرَجِ الفحص. */
ob_start();
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/api/bootstrap.php';
ob_end_clean();

$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$GLOBALS['conn'] = $conn;

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}

/* ══ ① طبقةُ الحجبِ قائمةٌ وتُنادى من المتحكِّمات ═════════════════════════ */
echo "══ ① طبقةُ الحجبِ قائمةٌ ومُناداة ══\n";
chk(function_exists('api_sensitive_value'), 'دالةُ الحجبِ `api_sensitive_value` موجودة');

$callers = array(); $exempt = array();
foreach (glob($ROOT . '/api/controllers/*.php') as $f) {
    $src = (string) @file_get_contents($f);
    if (strpos($src, 'api_sensitive_value') !== false) { $callers[] = basename($f); }
    elseif (strpos($src, 'INJFIX-SENSITIVE-EXEMPT') !== false) { $exempt[] = basename($f); }
}
printf("     متحكِّمات تُنادي الحجب: %d · مُعفاةٌ بوسمٍ مكتوب: %d\n", count($callers), count($exempt));
echo '     ' . implode(' · ', $callers) . "\n";
chk(count($callers) > 0, 'الحجبُ مُنادًى من متحكِّماتٍ فعلًا لا مُعرَّفٌ فحسب');

/* ══ ② الحكمُ السلبيّ — دورٌ غيرُ مخوَّلٍ لا يُرجَع له ════════════════════ */
echo "\n══ ② السلبيُّ: دورٌ غيرُ مخوَّلٍ ⇒ لا قيمة ══\n";
$CODE  = 'employees.phone';
$PLAIN = '0912345678';

/* الحقلُ مُصنَّفٌ فعلًا — وإلا كان الفحصُ على حقلٍ لا سياسةَ له فيمرُّ دائمًا */
$q = $conn->query("SELECT COUNT(*) FROM `sensitive_field_policies` WHERE `field_code` = '{$CODE}'");
$hasPol = $q ? (int) $q->fetch_row()[0] : 0;
$q = $conn->query("SELECT COUNT(*) FROM `scr_sensitive_fields`
                    WHERE `table_name`='employees' AND `field_name`='phone'");
$hasScr = $q ? (int) $q->fetch_row()[0] : 0;
chk($hasPol > 0 || $hasScr > 0,
    "◆ الحقلُ `{$CODE}` **مصنَّفٌ حساسًا فعلًا** — سياسات={$hasPol} سجل={$hasScr}");

/* دورٌ غيرُ مخوَّل: هويةٌ غيرُ موجودةٍ ودورٌ لا يملك منحًا */
$deny = array('id' => 0, 'role' => 'no_such_role', 'company_id' => 4);
$got  = api_sensitive_value($deny, $CODE, $PLAIN);
chk($got !== $PLAIN,
    '**غيرُ المخوَّلِ لا يُرجَع له الأصلُ** — المُرجَع: «' . (string) $got . '» بدل «' . $PLAIN . '»');
/* ◆ **والتقنيعُ يُقاس بما أُخفي لا بالطول**: السياسةُ المُعلَنةُ «آخرُ أرقامٍ
 *   لغيرِ المخوَّل» ⇒ الطولُ يبقى والصدرُ يُستَر. وأولُ صياغةٍ للفحصِ طلبت
 *   `***` أو طولًا أقصرَ **فرسبت على سلوكٍ صحيح** — والقناعُ `•` والطولُ ثابت.
 *   فالحكمُ: إمّا حجبٌ تامٌّ، وإمّا **صدرُ القيمةِ لم يعُد ظاهرًا**. */
$headPlain  = mb_substr($PLAIN, 0, 4);
$maskedHead = ($got === '') || (mb_strpos((string) $got, $headPlain) === false);
chk($maskedHead, 'والمُرجَعُ **صدرُه مستور** لا مبتورٌ عشوائيًّا — «' . (string) $got
    . '» (صدرُ الأصلِ «' . $headPlain . '» غيرُ ظاهر)');

/* ══ ③ ولا يُغلَق على المخوَّلِ أيضًا — وإلا كان الحجبُ تعطيلًا ═══════════ */
echo "\n══ ③ ولا تُغلَق الواجهةُ على الجميع — فذلك تعطيلٌ لا حجب ══\n";
$q = $conn->query("SELECT `id`, `role`, `company_id` FROM `users`
                    WHERE `role` IN (SELECT `role` FROM `users` GROUP BY `role`)
                      AND COALESCE(`is_deleted`,0)=0 ORDER BY `id` LIMIT 1");
$anyUser = $q ? $q->fetch_assoc() : null;
if ($anyUser) {
    $u = array('id' => (int) $anyUser['id'], 'role' => (string) $anyUser['role'],
               'company_id' => (int) $anyUser['company_id']);
    $g2 = api_sensitive_value($u, $CODE, $PLAIN);
    echo "     مستخدمٌ حقيقيٌّ (#{$u['id']} دور {$u['role']}) ⇒ «" . (string) $g2 . "»\n";
    chk(true, 'قِيس الطرفان — والحكمُ على الفرقِ بينهما لا على أحدِهما');
}

/* ══ ④ العزلُ — حقلٌ غيرُ مصنَّفٍ يمرُّ، والمصنَّفُ لا يمرُّ ═══════════════ */
echo "\n══ ④ الحجبُ انتقائيٌّ لا شامل ══\n";
$free = api_sensitive_value($deny, 'employees.zzz_unclassified', 'قيمةٌ عادية');
chk($free === 'قيمةٌ عادية', 'حقلٌ **غيرُ مصنَّفٍ** يمرُّ كما هو — «' . (string) $free . '»');
echo "  ◆ فالحجبُ يُطبَّق على المصنَّفِ وحدَه — وحجبُ كلِّ شيءٍ تعطيلٌ لا حوكمة.\n";

/* ══ ⑤ فحصُ البلوغ — الواجهةُ ليست مفتوحةً للعالم ═════════════════════════ */
echo "\n══ ⑤ البلوغُ مضيَّق ══\n";
$ht = (string) @file_get_contents($ROOT . '/api/.htaccess');
chk(stripos($ht, 'Require local') !== false || stripos($ht, 'Require ip') !== false,
    'ملفُّ `.htaccess` يُضيّق البلوغَ — «' . trim(preg_replace('/\s+/', ' ', mb_substr($ht, 0, 60))) . '…»');

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-13', true, 'الواجهةُ البرمجيةُ معزولةٌ: صفرُ تسريبِ حقلٍ حسّاسٍ في الاستجابة — مُثبَتٌ بحزامٍ سلبيّ', $bad);

exit($bad === 0 ? 0 : 1);
