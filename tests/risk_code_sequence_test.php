<?php
/**
 * tests/risk_code_sequence_test.php — شاهدُ «المُرقِّمُ لا يقرأ إلا رمزَه»
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0631
 *
 * **العيب**: مُولِّدُ رمزِ الخطرِ كان يقتطع بالموضعِ لا بالنمط:
 *
 *     SELECT COALESCE(MAX(CAST(SUBSTRING(risk_code, 5) AS UNSIGNED)),0)+1 …
 *
 * والخمسةُ عددُ حروفِ `RSK-` + واحد. فحين وُجد في السجلِّ صفٌّ ببادئةٍ من خمسةِ
 * حروفٍ `RISK-00004` — وهي سبعةَ عشرَ صفًّا مبذورةً في الكيان 4 — عاد الاقتطاعُ
 * بـ`'-00004'`، و**MariaDB تلفُّ السالبَ إلى مُتمِّمِه الموجب**:
 *
 *     CAST('-00004' AS UNSIGNED) = 18446744073709551612   (تنبيهٌ 1105 لا خطأ)
 *
 * فصار «التالي» ‏1.8e19 والرمزُ المولَّدُ `RSK-9223372036854775807`.
 *
 * **وأسوأُ من الرقمِ صمتُ القاعدة**: `sql_mode` خالٍ في هذا التركيب، والعمودُ
 * `varchar(16)`. فثلاثةٌ وعشرون حرفًا **تُبتر إلى ستةَ عشرَ** بتنبيهٍ لا بخطأ:
 * أوّلُ إنشاءٍ ينجح برمزٍ مشوَّهٍ يُحفظ، وما بعده يصطدم بـ`uq_risk_code`.
 *
 * **الإصلاح**: المُرقِّمُ يقرأ ما يطابق نمطَه وحدَه `^RSK-[0-9]+$`، ويُقاس
 * الطولُ قبل الإدراجِ فلا بترَ صامتًا. والبادئةُ القديمةُ تبقى في صفوفِها لا
 * تُحذف ولا تُعاد تسميتُها — تصير **خاملةً** بحكمِ النمط.
 *
 * ── ولا يُقاس الأخضرُ وحدَه ──────────────────────────────────────────────────
 * فاحصٌ يزرع صفًّا ثمَّ يقنع بأنَّ الرمزَ «يبدو سليمًا» يمرُّ على مُرقِّمٍ لم يُصلَح
 * أصلًا إن كان الزرعُ لا يوقعه. فهنا **حارسُ دخولٍ**: يُشغَّل شكلُ الاستعلامِ
 * القديمِ على الصفِّ المزروعِ ويُشترط أن يعودَ بالرقمِ الملفوف — فإن لم يقع الفخُّ
 * فالجولةُ كلُّها بلا معنًى وتُردُّ حمراء.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Risk/RiskService.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };

/* ── كنسُ عائلةِ الوسمِ قبلَ الجولةِ وبعدها ───────────────────────────────────
     والأحداثُ تُكنس مع صفوفِها: الجذرُ المحايدُ `ems_business_events` هو مقصدُ
     `RiskEvents::fire`، فجولةٌ لا تكنسه تترك حقائقَ لكياناتٍ لم تعد موجودة. */
$TAG = 'RSKSEQ-PROBE';
$sweep = function () use ($conn, $TAG, $CO) {
    $ids = array();
    $st = $conn->prepare("SELECT id FROM risk_register WHERE company_id = ? AND title LIKE ?");
    $like = '%' . $TAG . '%';
    $st->bind_param('is', $CO, $like);
    $st->execute();
    $r = $st->get_result();
    while ($x = $r->fetch_row()) { $ids[] = (int) $x[0]; }
    $st->close();
    $n = 0;
    foreach ($ids as $id) {
        $conn->query("DELETE FROM ems_business_events
                       WHERE source_module = 'risk' AND entity_type = 'risk' AND entity_id = " . $id);
        /* ◆ يُقرأ مُرجَعُ الحذفِ لا يُفترض: قيدٌ مانعٌ يردُّ `false` صامتًا،
             فتُعلن الجولةُ «صفرَ ثغرةٍ» وهي تاركةٌ صفَّها في السجل. */
        if ($conn->query('DELETE FROM risk_register WHERE id = ' . $id)) { $n += $conn->affected_rows; }
    }
    return $n;
};
$pre = $sweep();
$say('══ INJ-0631 · مُرقِّمُ المخاطرِ يقرأ نمطَه وحدَه — والبادئةُ الغريبةُ خاملة'
     . ($pre ? "  (كُنس {$pre} من جولةٍ سابقة)" : ''));

/* ── ① الزرعُ: صفٌّ ببادئةِ خمسةِ حروفٍ كالصفوفِ السبعةَ عشرَ المبذورة ────────── */
$ruId = 0;
$r = $conn->query("SELECT id FROM risk_units WHERE company_id = {$CO} AND active = 1 ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $ruId = (int) $x[0]; }
$ok($ruId > 0, "وحدةُ خطرٍ حيّةٌ للزرع (#{$ruId})");

$PLANT = 'RISK-99001';
$conn->query("DELETE FROM risk_register WHERE company_id = {$CO} AND risk_code = '{$PLANT}'");
$st = $conn->prepare("INSERT INTO risk_register (company_id, risk_code, ru_id, title, root_cause, created_by)
                      VALUES (?, ?, ?, ?, 'زرعُ فاحصٍ', 1)");
$ptitle = 'خطرٌ قديمُ البادئةِ · ' . $TAG;
$st->bind_param('isis', $CO, $PLANT, $ruId, $ptitle);
$planted = $st->execute();
$plantId = (int) $conn->insert_id;
$st->close();
$ok($planted && $plantId > 0, "زُرع صفٌّ ببادئةٍ قديمة `{$PLANT}` (#{$plantId})");

/* ── ② حارسُ الدخولِ: أيقعُ الفخُّ أصلًا؟ ─────────────────────────────────────
     يُشغَّل **شكلُ الاستعلامِ القديمِ نفسُه** على الشجرةِ بعد الزرع. فإن لم يعُد
     بالرقمِ الملفوفِ فالزرعُ لا يمثّل العيبَ المرصود، وكلُّ أخضرَ بعده كاذب. */
$legacyMax = '0';
$r = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(risk_code, 5) AS UNSIGNED)), 0) + 1 nx
                     FROM risk_register WHERE company_id = {$CO}");
if ($r && ($x = $r->fetch_row())) { $legacyMax = (string) $x[0]; }
$trapSprung = (function_exists('bccomp') ? bccomp($legacyMax, '4294967295') > 0
                                         : (float) $legacyMax > 4294967295.0);
$ok($trapSprung, '**والفخُّ يقع**: شكلُ الاستعلامِ القديمِ يعود بالرقمِ الملفوف',
    "عاد {$legacyMax} — والزرعُ لا يمثّل العيب");
$say("   ⟪ الاقتطاعُ بالموضعِ يعطي: {$legacyMax} ⟫");

/* ── ③ التالي المتوقَّع: خَلَفُ أكبرِ رمزٍ **مطابقٍ للنمط** وحدَه ─────────────── */
$maxValid = 0;
$r = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(risk_code, 5) AS UNSIGNED)), 0) mx
                     FROM risk_register
                    WHERE company_id = {$CO} AND risk_code REGEXP '^RSK-[0-9]+$'");
if ($r && ($x = $r->fetch_row())) { $maxValid = (int) $x[0]; }
$expected = 'RSK-' . str_pad((string) ($maxValid + 1), 6, '0', STR_PAD_LEFT);
$ok($maxValid > 0, "وأكبرُ رمزٍ مطابقٍ للنمطِ في الكيان: {$maxValid}");

/* ── ④ الحسابُ والدخولُ ─────────────────────────────────────────────────────── */
$user = ''; $roleUsed = 0;
foreach (array(28, 29, 30, 9) as $rid) {
    $st = $conn->prepare("SELECT username FROM users WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
    $r2 = (string) $rid; $st->bind_param('si', $r2, $CO); $st->execute();
    $x = $st->get_result()->fetch_row(); $st->close();
    if ($x) { $user = (string) $x[0]; $roleUsed = $rid; break; }
}
$ok($user !== '', "وُجد حسابُ دورِ المخاطر ({$user} · دور {$roleUsed})");

$jar = sys_get_temp_dir() . '/rskseq_' . getmypid() . '.txt';
$http = function ($url, $fields = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90));
    if ($fields !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $fields); }
    $b = (string) curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('body' => $b, 'code' => $c);
};
$b = $http($BASE . '/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b['body'], $t);
$lb = $http($BASE . '/login.php', http_build_query(array(
    'username' => $user, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
$ok(mb_strpos($lb['body'], 'name="password"') === false, 'ودخل');

$scr = $http($BASE . '/Risk/risk_register.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $scr['body'], $ct);
$tok = isset($ct[1]) ? $ct[1] : '';
$ok($scr['code'] === 200 && $tok !== '', 'وصُيِّر سجلُّ المخاطر برمزِ حمايةٍ');

/* ── ⑤ الإنشاءُ عبرَ الشاشةِ — المسارُ الذي رُصد عليه العيبُ حيًّا ───────────── */
/* ◆ INJ-0109: صار «لا خطرَ يُسجَّل بلا وحدةٍ مالكة» — فالبذرُ يحمل وحدتَه.
     (ولا يُليَّن الحارسُ ليمرَّ فاحصٌ: الفاحصُ يتبع الحكمَ لا العكس.) */
$__ouId = 0;
$__ouq = $conn->query('SELECT unit_id FROM org_units WHERE company_id = ' . (int) $CO . ' ORDER BY unit_id LIMIT 1');
if ($__ouq && ($__oux = $__ouq->fetch_row())) { $__ouId = (int) $__oux[0]; }
$ok($__ouId > 0, 'ووحدةٌ إداريةٌ مالكةٌ للبذر #' . $__ouId);
$mk = $http($BASE . '/Risk/risk_actions.php', http_build_query(array(
    'do' => 'risk_create', 'csrf_token' => $tok, 'ru_id' => $ruId,
    'owner_unit_id' => $__ouId,
    'title' => 'خطرٌ مزروعٌ بعدَ القديمِ · ' . $TAG,
    'root_cause' => 'زرعُ فاحصٍ', 'scope_type' => 'إداري', 'force_duplicate' => 1)));
$ok($mk['code'] === 200, 'وأُنشئ خطرٌ عبرَ `do=risk_create`',
    'الرمزُ ' . $mk['code'] . ' · ' . mb_substr(strip_tags($mk['body']), 0, 140));

/* ◆ الرمزُ يُقرأ من **مُرجَعِ الخدمةِ قبلَ التخزين** لا من العمودِ بعدَه: العمودُ
     `varchar(16)` و`sql_mode` خالٍ، فرمزٌ من ثلاثةٍ وعشرين حرفًا يصل القاعدةَ
     فتبتره إلى ستةَ عشرَ **بتنبيهٍ لا بخطأ**. فقياسُ المخزَّنِ وحدَه يرى بقيةَ
     البترِ «قصيرةً» ويعلن سلامةَ ما لم يسلم. */
$json = json_decode($mk['body'], true);
$genCode = is_array($json) && isset($json['risk_code']) ? (string) $json['risk_code'] : '';
$ok(is_array($json) && !empty($json['ok']), 'وردَّت الخدمةُ نجاحًا',
    mb_substr(strip_tags($mk['body']), 0, 140));

$madeId = 0; $madeCode = '';
$r = $conn->query("SELECT id, risk_code FROM risk_register
                    WHERE company_id = {$CO} AND title LIKE '%بعدَ القديمِ%' ORDER BY id DESC LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $madeId = (int) $x[0]; $madeCode = (string) $x[1]; }
$ok($madeId > 0, 'وحُفظ صفُّه', 'لم يُحفظ صفٌّ — ' . mb_substr(strip_tags($mk['body']), 0, 140));
$say("   ⟪ المولَّدُ: «{$genCode}» · المخزَّنُ: «{$madeCode}» ⟫");

/* ── ⑥ الأحكامُ الأربعةُ على الرمزِ المولَّد ────────────────────────────────── */
$ok(preg_match('~^RSK-\d{6}$~', $genCode) === 1,
    '**رمزٌ عاديٌّ من ستةِ أرقامٍ ببادئةِ `RSK-`**', "وُلِّد «{$genCode}»");
$ok($genCode !== '' && strlen($genCode) <= 16, 'ويسعُه العمودُ **قبلَ** بلوغِه القاعدة',
    'طولُ المولَّدِ ' . strlen($genCode) . ' > 16');
$ok($genCode !== '' && $genCode === $madeCode, 'فخُزِّن كما وُلِّد — لا بترَ صامتًا',
    "وُلِّد «{$genCode}» وخُزِّن «{$madeCode}»");
$ok($genCode === $expected, "**وهو خَلَفُ أكبرِ رمزٍ مطابقٍ للنمط** ({$expected})",
    "وُلِّد «{$genCode}» والمنتظَرُ «{$expected}»");

/* ── ⑦ والقديمُ باقٍ كما هو — لا حذفَ ولا إعادةَ تسمية ────────────────────── */
$stillThere = ''; $stillCount = 0;
$r = $conn->query("SELECT risk_code FROM risk_register WHERE id = {$plantId}");
if ($r && ($x = $r->fetch_row())) { $stillThere = (string) $x[0]; }
$r = $conn->query("SELECT COUNT(*) FROM risk_register WHERE company_id = {$CO} AND risk_code LIKE 'RISK-%'");
if ($r && ($x = $r->fetch_row())) { $stillCount = (int) $x[0]; }
$ok($stillThere === $PLANT, '**والصفُّ القديمُ باقٍ برمزِه** — خاملٌ لا ممسوح',
    "صار «{$stillThere}»");
$ok($stillCount >= 18, "وصفوفُ البادئةِ القديمةِ في الكيانِ كما هي ({$stillCount} بالمزروع)");

/* ── ⑧ والمُرقِّمُ المعمَّمُ نفسُه: ثلاثةُ جداولَ كلُّها ببادئةٍ قديمةٍ حيّة ─────
     `risk_reviews` و`risk_incidents` و`risk_committee` فيها عشرون صفًّا لكلٍّ
     كلُّها `RISK-#####` — أي أنَّ المُرقِّمَ المعمَّمَ يقع في الفخِّ نفسِه. ويُقاس
     قراءةً بلا كتابةٍ: `nextCode` دالّةٌ خالصةٌ لا تُدرج. */
$seqs = array(
    'risk_reviews'   => array('review_code', 'RVW-', 5),
    'risk_incidents' => array('incident_code', 'INC-', 6),
    'risk_committee' => array('minute_code', 'CMT-', 5),
);
foreach ($seqs as $tbl => $spec) {
    list($col, $prefix, $width) = $spec;
    $legacy = 0;
    $r = $conn->query("SELECT COUNT(*) FROM `{$tbl}` WHERE `{$col}` NOT REGEXP '^" . rtrim($prefix, '-') . "-[0-9]+$'");
    if ($r && ($x = $r->fetch_row())) { $legacy = (int) $x[0]; }
    $code = '';
    try {
        $code = \App\Services\Risk\RiskService::nextCode($conn, $CO, $tbl, $col, $prefix, $width);
    } catch (\Throwable $e) { $code = 'EX: ' . $e->getMessage(); }
    $re = '~^' . preg_quote($prefix, '~') . '\d{' . $width . '}$~';
    $ok(preg_match($re, $code) === 1 && strlen($code) <= 16,
        "و`{$tbl}` ({$legacy} صفًّا ببادئةٍ غريبة) ⇒ «{$code}»",
        "عاد «{$code}»");
}

/* ── ⑨ الكنسُ الختاميُّ وصفرُ الثغرة ───────────────────────────────────────── */
@unlink($jar);
$post = $sweep();
$say("   كُنس ختامًا: {$post} صفًّا");
$left = 0;
$r = $conn->query("SELECT COUNT(*) FROM risk_register WHERE company_id = {$CO} AND title LIKE '%" . $TAG . "%'");
if ($r && ($x = $r->fetch_row())) { $left = (int) $x[0]; }
$ok($left === 0, "صفرُ ثغرةٍ من عائلةِ الوسمِ بعد الجولة ({$left})");

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
