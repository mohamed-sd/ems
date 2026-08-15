<?php
/**
 * tests/entitlement_gate_queue_test.php — بوابةُ الاستحقاق: صفرٌ صادقٌ لا صفرٌ كاذب
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0037: «بوجود صفوف `fin_dues` بحالة `pending` تعرض الشاشةُ
 *   **عددَها الصحيحَ** وصفوفَها **بأعمدةٍ مملوءة**؛ و**صفرُ خطأ SQL** في السجل
 *   عند فتحها».
 *
 * ── العلّةُ التي يحرسها ───────────────────────────────────────────────────
 * كانت الشاشةُ تسأل عن خمسةِ أعمدةٍ لا وجودَ لها في `fin_dues`
 * (`due_no`·`party_kind`·`beneficiary_ref`·`state`·`source_kind`)، فيرسب
 * الاستعلامُ ويبتلع `if ($res)` رسوبَه — **فيلبس الفشلُ ثوبَ الخلوّ**: شاشةٌ
 * تقول «لا أثرَ ينتظر» وفي القاعدةِ عشرون صفًّا معلَّقًا.
 *
 * ── والشاهدُ يقيس ثلاثةً لا واحدًا ────────────────────────────────────────
 * ① **رقمانِ متطابقانِ في موضعين**: عددُ الخدمةِ = عددُ القاعدةِ = عددُ الشاشة.
 * ② **الأعمدةُ المطلوبةُ موجودةٌ في الجدولِ حقًّا** — تُقرأ من `SHOW COLUMNS`.
 * ③ **الاختبارُ السلبيُّ (GT-01)**: تُنادى الخدمةُ على اتصالٍ لا يرى الجدولَ،
 *    فيجب أن تُرجع `ok=false` ورمزَ ٥٠٠ **ولا تُرجع قائمةً فارغةً بابتسامة**.
 *    وفاحصٌ لا يرسب عند إفسادِ مفحوصِه يصادق على نفسِه.
 * ◆ ولا يبذر هذا الشاهدُ ولا يحذف صفًّا واحدًا — يقيس القائمَ حيًّا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/app/Services/Policy/UnitJourneyService.php';

use App\Services\Policy\UnitJourneyService as UJ;

$conn = $GLOBALS['conn'];
$CO   = 4;
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ بوابةُ الاستحقاق: صفرٌ صادقٌ لا صفرٌ كاذب (INJ-0037)');

/* ── ① الأعمدةُ المسؤولُ عنها موجودةٌ في الجدولِ حقًّا ───────────────────── */
$say("\n── ① كلُّ عمودٍ تسأل عنه الخدمةُ له وجودٌ في `fin_dues`");
$cols = array();
$r = $conn->query('SHOW COLUMNS FROM fin_dues');
while ($r && ($x = $r->fetch_assoc())) { $cols[strtolower($x['Field'])] = true; }
$need = array('party_type', 'party_ref', 'due_type', 'direction', 'amount', 'currency',
              'period_ref', 'event_id', 'source_doc_type', 'source_doc_id', 'settlement_state');
$miss = array();
foreach ($need as $c) { if (!isset($cols[$c])) { $miss[] = $c; } }
$ok(!$miss, 'الأعمدةُ الأحدَ عشرَ كلُّها قائمةٌ في الجدول', implode(',', $miss));

/* والأشباحُ الخمسةُ التي كانت تُسأل — يجب ألا تعود إلى الشاشةِ أبدًا */
$ghosts = array('due_no', 'party_kind', 'beneficiary_ref', 'source_kind');
$alive = array();
foreach ($ghosts as $g) { if (isset($cols[$g])) { $alive[] = $g; } }
$ok(!$alive, 'والأشباحُ الأربعةُ ما زالت غيرَ موجودةٍ — فالإصلاحُ كان في الشاشةِ لا في الجدول',
    implode(',', $alive));
$scr = (string) @file_get_contents($ROOT . '/Finance/entitlement_gate.php');
$back = array();
foreach (array_merge($ghosts, array("d.state")) as $g) {
    if (preg_match('~\$r\[\'' . preg_quote($g, '~') . '\'\]|d\.' . preg_quote($g, '~') . '\b~', $scr)) { $back[] = $g; }
}
$ok(!$back, 'ولا تذكرها الشاشةُ في قراءةٍ ولا عرض', implode(',', $back));
$ok(strpos($scr, 'UnitJourneyService::gateQueue') !== false,
    'والطابورُ من الخدمةِ المالكةِ لا من استعلامٍ في الشاشة (CS-05)');

/* ── ② رقمانِ متطابقانِ في موضعين ────────────────────────────────────────── */
$say("\n── ② العددُ نفسُه في القاعدةِ وفي الخدمةِ وفي الشاشة");
$dbN = -1;
$r = $conn->query("SELECT COUNT(*) FROM fin_dues
                    WHERE company_id={$CO} AND is_deleted=0 AND settlement_state='pending'");
if ($r && ($x = $r->fetch_row())) { $dbN = (int) $x[0]; }
$ok($dbN >= 0, 'القاعدةُ: ' . $dbN . ' استحقاقًا معلَّقًا في الشركة ' . $CO);

$q = UJ::gateQueue($conn, $CO);
$ok($q['ok'] === true && $q['code'] === 200, 'الخدمةُ نجحت برمزٍ ٢٠٠', $q['error']);
$ok($q['total'] === min($dbN, 200), 'الخدمةُ: ' . $q['total'] . ' — مطابقٌ للقاعدة', 'القاعدة=' . $dbN);
$ok($dbN > 0, 'وثمَّ صفوفٌ حقيقيةٌ تُقاس عليها (لا حكمَ على طابورٍ خاوٍ)');

/* والأعمدةُ **مملوءةٌ** لا مفاتيحَ فارغة — نصُّ القبولِ يشترط «بأعمدةٍ مملوءة» */
$filled = 0; $empty = array();
foreach ($q['rows'] as $row) {
    $bad = array();
    foreach (array('party_type', 'party_ref', 'due_type', 'amount', 'currency', 'settlement_state') as $c) {
        if (!array_key_exists($c, $row) || $row[$c] === null || (string) $row[$c] === '') { $bad[] = $c; }
    }
    if ($bad) { $empty[] = '#' . $row['id'] . ':' . implode('/', $bad); } else { $filled++; }
}
$ok($filled === $q['total'], 'وكلُّ صفٍّ بأعمدةٍ مملوءة: ' . $filled . '/' . $q['total'],
    implode(' · ', array_slice($empty, 0, 3)));
$ok(isset($q['actionable']) && $q['actionable'] <= $q['total'],
    'ويُعلَن القابلُ للفعلِ صراحةً: ' . $q['actionable'] . ' من ' . $q['total']
    . ' — فلا يُعرض زرٌّ يردُّ ٤٠٤');

/* ── ③ الاختبارُ السلبيّ: أفسِد المفحوصَ وأثبِت الرسوب (GT-01) ───────────── */
$say("\n── ③ الاختبارُ السلبيّ: الفشلُ يُعلَن برمزٍ ولا يلبس ثوبَ الخلوّ");
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
mysqli_report(MYSQLI_REPORT_OFF);
/* اتصالٌ إلى مخطَّطٍ لا يحوي `fin_dues` — فيرسب الاستعلامُ حتمًا وبلا مساسٍ ببيانة */
$blind = @new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), 'information_schema', $p);
if ($blind->connect_errno) {
    $ok(false, 'تعذّر فتحُ اتصالِ الإفساد — لا اختبارَ سلبيّ', $blind->connect_error);
} else {
    $bad = UJ::gateQueue($blind, $CO);
    $ok($bad['ok'] === false, 'رسبت الخدمةُ حين لم ترَ الجدولَ — لم تدّعِ النجاح');
    $ok((int) $bad['code'] === 500, 'وبرمزٍ ٥٠٠ ظاهرٍ لا صامت (code=' . $bad['code'] . ')');
    $ok($bad['error'] !== '' && strpos($bad['error'], 'FIN-500') === 0,
        'وبرسالةٍ تحمل الرمزَ: ' . mb_substr($bad['error'], 0, 70));
    $ok($bad['total'] === 0 && $bad['rows'] === array(),
        'ولم تُرجع صفوفًا مختلَقة — الفشلُ فشلٌ لا قائمةٌ فارغة');
    $blind->close();
}
/* ولو عادت الشاشةُ إلى الأعمدةِ الشبحيةِ لَرسب الاستعلامُ صامتًا — نُثبته حيًّا */
$rGhost = @$conn->query("SELECT d.due_no, d.state FROM fin_dues d WHERE d.company_id={$CO} LIMIT 1");
$ok($rGhost === false,
    'والاستعلامُ القديمُ ما زال يرسب فعلًا — فالعيبُ كان حقيقيًّا لا مظنونًا: ' . mb_substr($conn->error, 0, 60));

/* ── ④ برهانُ الشاشةِ الحيّةِ عبر HTTP ───────────────────────────────────── */
$say("\n── ④ الشاشةُ نفسُها عبر HTTP (تُتخطّى إن كان Apache متوقفًا)");
$BASE = 'http://localhost/ems';
$jar  = sys_get_temp_dir() . '/ems_eg_' . $CO . '.cookie';
@unlink($jar);
$req = function ($url, $post = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $raw = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr((string) $raw, 0, $hs), substr((string) $raw, $hs));
};
list($c0, , $b0) = $req($BASE . '/login.php');
if ($c0 === 0) {
    $say('  ⓘ Apache متوقفٌ — تُتخطّى الطبقةُ ④ **ويُعلَن التخطّي** ولا يُدّعى نجاحُها');
} else {
    /* ◆ الفاعلُ **مديرٌ ماليٌّ (الدور ١٧)** لا الدورُ ١: بوابةُ الاستحقاقِ شاشةُ
         الإدارةِ المالية، والدورُ ١ يُردُّ عنها بحقٍّ (GOV-PERM-403). واختيارُ
         فاعلٍ محجوبٍ يقرأ حجبًا صحيحًا **عطبًا في المنتج** — وذلك خطأُ المقياسِ لا المنتج. */
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b0, $tm);
    $req($BASE . '/login.php', array('username' => 'مديرمالي', 'password' => '12345678',
        'csrf_token' => isset($tm[1]) ? $tm[1] : ''));
    $errLog = $ROOT . '/logs/php_errors.log';
    $sizeBefore = file_exists($errLog) ? filesize($errLog) : 0;
    list($c1, , $b1) = $req($BASE . '/Finance/entitlement_gate.php');
    $ok($c1 === 200, 'فُتحت الشاشةُ برمزِ ٢٠٠ (رمز=' . $c1 . ')');
    $ok(preg_match('~بانتظار البوابة:\s*(\d+)~u', $b1, $bm) && (int) $bm[1] === $q['total'],
        'وشارةُ العددِ تقول ' . (isset($bm[1]) ? $bm[1] : '؟') . ' — الرقمُ نفسُه في موضعٍ ثانٍ',
        'الخدمة=' . $q['total']);
    $ok(mb_strpos($b1, 'لا أثرَ أوليًّا ينتظر البوابة') === false,
        'ولا تُعلن الخلوَّ وفي القاعدةِ ' . $dbN . ' صفًّا');
    /* صفٌّ حقيقيٌّ ظاهرٌ بأعمدتِه: نأخذ أوّلَ استحقاقٍ ونتحقق من ظهورِ طرفِه ومبلغِه */
    $first = $q['rows'] ? $q['rows'][0] : null;
    if ($first) {
        $needle = '#' . (int) $first['id'];
        $amt    = number_format((float) $first['amount'], 2);
        $ok(mb_strpos($b1, $needle) !== false, 'والصفُّ الأولُ ظاهرٌ بمعرِّفه ' . $needle);
        $ok(mb_strpos($b1, $amt) !== false, 'وبمبلغِه ' . $amt . ' — خليةٌ مملوءةٌ لا فارغة');
        $ok(mb_strpos($b1, (string) $first['due_type']) !== false,
            'وبنوعِ استحقاقِه ' . $first['due_type']);
    }
    $sizeAfter = file_exists($errLog) ? filesize($errLog) : 0;
    $added = $sizeAfter > $sizeBefore
        ? (string) file_get_contents($errLog, false, null, $sizeBefore, $sizeAfter - $sizeBefore) : '';
    $sqlErr = preg_match('~Unknown column|You have an error in your SQL~i', $added);
    $ok(!$sqlErr, '«وصفرُ خطأ SQL في السجل عند فتحها» — نصُّ القبولِ حرفًا',
        mb_substr(preg_replace('~\s+~', ' ', $added), 0, 160));
}
@unlink($jar);

$say("\n══ النتيجة: ناجحٌ {$PASS} · راسبٌ {$FAIL}");
$say("PASS={$PASS} · FAIL={$FAIL}");   /* الصيغةُ التي يقرأها `tests/_regression.php` */
exit($FAIL > 0 ? 1 : 0);
