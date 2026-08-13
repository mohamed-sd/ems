<?php
/**
 * tests/risk_owner_unit_test.php — شاهدُ «الإدارة المالكة» في سجلِّ المخاطر
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0577
 *
 * **العيب**: حقلُ «الإدارة المالكة (وحدة الهيكل)» كان
 * `<input name="owner_unit_id" type="number" placeholder="unit_id">` — يطلب من
 * المستخدمِ رقمَ الوحدةِ **عن ظهرِ قلب**، والخدمةُ تقبله بـ`(int)` بلا تحقُّق.
 * فرقمٌ لا وجودَ له — أو وحدةُ كيانٍ آخر — يُحفظ، ثم يبتلعه `LEFT JOIN org_units`
 * فيظهر الخطرُ بمالكٍ **فارغٍ** لا بخطأ.
 *
 * **الإصلاح** طرفان: قائمةٌ بأسماءِ وحداتِ الكيانِ العربيةِ في الشاشة، و**رفضٌ
 * بـ422 في الخادم** — فترشيحُ الواجهةِ وحدَه بابٌ خلفيٌّ لكلِّ POST مباشر.
 *
 * ── والطرفانِ يُقاسان معًا ──────────────────────────────────────────────────
 * فاحصٌ يقيس الرفضَ وحدَه يمرُّ على إصلاحٍ يرفض **كلَّ شيء**. فيُقاس المسارُ
 * الصحيحُ أيضًا: خطرٌ بوحدةٍ حقيقيةٍ يُحفظ ويُكنس بعائلةِ وسمِه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
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

/* ── كنسُ عائلةِ الوسمِ قبلَ الجولةِ وبعدها ───────────────────────────────── */
$TAG = 'RSKUNIT-PROBE';
$sweep = function () use ($conn, $TAG) {
    $ids = array();
    $r = $conn->query("SELECT id FROM risk_register WHERE title LIKE '%" . $TAG . "%'");
    while ($r && ($x = $r->fetch_row())) { $ids[] = (int) $x[0]; }
    $n = 0;
    foreach ($ids as $id) {
        @$conn->query('DELETE FROM risk_events WHERE risk_id = ' . $id);
        if ($conn->query('DELETE FROM risk_register WHERE id = ' . $id)) { $n += $conn->affected_rows; }
    }
    return $n;
};
$pre = $sweep();
$say('══ INJ-0577 · الإدارةُ المالكةُ قائمةٌ مُتحقَّقةٌ لا رقمٌ حرّ'
     . ($pre ? "  (كُنس {$pre} من جولةٍ سابقة)" : ''));

/* ── حسابُ دورٍ يكتب في السجل ──────────────────────────────────────────────── */
$user = ''; $roleUsed = 0;
foreach (array(28, 29, 30, 9) as $rid) {
    $st = $conn->prepare("SELECT username FROM users WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
    $r = (string) $rid; $st->bind_param('si', $r, $CO); $st->execute();
    $x = $st->get_result()->fetch_row(); $st->close();
    if ($x) { $user = (string) $x[0]; $roleUsed = $rid; break; }
}
$ok($user !== '', "وُجد حسابُ دورِ المخاطر ({$user} · دور {$roleUsed})");

$jar = sys_get_temp_dir() . '/rskunit_' . getmypid() . '.txt';
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

/* ── ① الشاشة: قائمةٌ بأسماءَ عربيةٍ لا حقلُ رقم ──────────────────────────── */
$scr = $http($BASE . '/Risk/risk_register.php');
$h = $scr['body'];
$ok($scr['code'] === 200 && mb_strpos($h, 'name="password"') === false, 'وصُيِّر سجلُّ المخاطر');
$ok(preg_match('~<select\s+name="owner_unit_id"~', $h) === 1,
    '**الحقلُ قائمةُ اختيارٍ** لا مُدخَلَ رقم');
/* ◆ الترتيبُ داخلَ الوسمِ غيرُ مضمون: `type` قد يسبق `name`. فأوّلُ صياغةٍ
     اشترطت `name` ثم `type` فبقيت خضراءَ حين أُعيد المُدخَلُ بترتيبٍ معكوس. */
$anyNumberInput = 0;
if (preg_match_all('~<input\b[^>]*>~i', $h, $mi)) {
    foreach ($mi[0] as $tagStr) {
        if (strpos($tagStr, 'name="owner_unit_id"') !== false
            && strpos($tagStr, 'type="number"') !== false) { $anyNumberInput++; }
    }
}
$ok($anyNumberInput === 0, 'ولا أثرَ لمُدخَلِ الرقمِ الحرِّ في المُخرَج',
    "وُجد {$anyNumberInput}");
$nOpt = 0;
if (preg_match('~<select\s+name="owner_unit_id".*?</select>~su', $h, $m)) {
    $nOpt = preg_match_all('~<option value="\d+">~', $m[0]);
    $arabic = preg_match_all('~<option value="\d+">[^<]*[\x{0600}-\x{06FF}]~u', $m[0]);
} else { $arabic = 0; }
$ok($nOpt > 0, "وفيها {$nOpt} وحدةً من هيكلِ الكيان");
$ok($arabic === $nOpt && $nOpt > 0, '**وكلُّها بأسماءَ عربيةٍ لا أرقام**',
    "عربيّ={$arabic} من {$nOpt}");

/* ── ② الخادم: وحدةٌ لا وجودَ لها تُردُّ 422 ───────────────────────────────── */
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $h, $ct);
$tok = isset($ct[1]) ? $ct[1] : '';
$ruId = 0;
$r = $conn->query("SELECT id FROM risk_units WHERE company_id = {$CO} AND active = 1 ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $ruId = (int) $x[0]; }
$badUnit = 0;
$r = $conn->query('SELECT COALESCE(MAX(unit_id),0) + 9999 FROM org_units');
if ($r && ($x = $r->fetch_row())) { $badUnit = (int) $x[0]; }
$ok($ruId > 0 && $badUnit > 0, "وحدةُ خطرٍ حيّةٌ (#{$ruId}) ورقمُ وحدةٍ لا وجودَ له (#{$badUnit})");

$post = function ($unitId, $suffix) use ($http, $BASE, $tok, $ruId, $TAG) {
    return $http($BASE . '/Risk/risk_actions.php', http_build_query(array(
        'do' => 'risk_create', 'csrf_token' => $tok, 'ru_id' => $ruId,
        'title' => 'خطرٌ مزروعٌ ' . $suffix . ' · ' . $TAG,
        'root_cause' => 'زرعُ فاحصٍ', 'scope_type' => 'إداري',
        'owner_unit_id' => $unitId, 'force_duplicate' => 1)));
};
$bad = $post($badUnit, 'برقمٍ وهميّ');
$ok($bad['code'] === 422, '**وحدةٌ خارجَ هيكلِ الكيانِ تُردُّ 422**', 'الرمزُ ' . $bad['code']);
$ok(strpos($bad['body'], 'RSK-UNIT-422') !== false, 'برمزٍ مميِّزٍ `RSK-UNIT-422`',
    mb_substr(strip_tags($bad['body']), 0, 120));
$leak = 0;
$r = $conn->query("SELECT COUNT(*) FROM risk_register WHERE title LIKE '%برقمٍ وهميّ%'");
if ($r && ($x = $r->fetch_row())) { $leak = (int) $x[0]; }
$ok($leak === 0, 'ولم يُحفظ منها صفٌّ واحد');

/* ── ③ والمسارُ الصحيحُ يمرُّ — فرفضُ الكلِّ ليس إصلاحًا ───────────────────── */
$goodUnit = 0;
$r = $conn->query("SELECT unit_id FROM org_units WHERE company_id = {$CO} AND COALESCE(active,1) = 1 ORDER BY unit_id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $goodUnit = (int) $x[0]; }
$good = $post($goodUnit, 'بوحدةٍ حقيقية');
$madeId = 0;
$r = $conn->query("SELECT id, owner_unit_id FROM risk_register WHERE title LIKE '%بوحدةٍ حقيقية%' ORDER BY id DESC LIMIT 1");
$madeUnit = 0;
if ($r && ($x = $r->fetch_row())) { $madeId = (int) $x[0]; $madeUnit = (int) $x[1]; }
$ok($good['code'] === 200 && $madeId > 0, "**وخطرٌ بوحدةٍ حقيقيةٍ (#{$goodUnit}) يُحفظ**",
    'الرمزُ ' . $good['code'] . ' · ' . mb_substr(strip_tags($good['body']), 0, 100));
$ok($madeUnit === $goodUnit, 'وتُخزَّن وحدتُه كما أُرسلت');

@unlink($jar);
$post2 = $sweep();
$say("   كُنس ختامًا: {$post2} صفًّا");
$left = 0;
$r = $conn->query("SELECT COUNT(*) FROM risk_register WHERE title LIKE '%" . $TAG . "%'");
if ($r && ($x = $r->fetch_row())) { $left = (int) $x[0]; }
$ok($left === 0, "صفرُ ثغرةٍ من عائلةِ الوسمِ بعد الجولة ({$left})");

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
