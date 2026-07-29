<?php
/**
 * tests/fx_currencies_screen_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP لشاشة العملات وأسعار الصرف (FES-01 §3.1/§3.3 · الدستور §6).
 * من **الشاشة الحقيقية** لا من الدوال:
 *   ① تُفتح لدور المالية وتعرض عملةَ الأساس وما ينتظر سعرًا
 *   ② سعرٌ جديدٌ يُحفظ بزرِّه — **فيُقيَّم** ما كان ينتظره من الدفتر والذمم
 *   ③ التقييمُ صادقٌ حسابيًّا: base = ROUND(amount × rate, 2) على صفٍّ مقيس
 *   ④ العطالة: السعرُ نفسُه بالتاريخ نفسِه يُرفض بمرجع القائم
 *   ⑤ الصلاحية: القارئُ المالي (22) يفتح ولا يضبط
 * ثم يكنس سعرَه ويعيد الصفوف إلى ما كانت عليه بالضبط.
 *
 * يعمل على عملةِ اختبارٍ وهمية (QAR) وصفوفٍ يبذرها — لا يمسّ الجنيهَ ولا صفًّا حيًّا.
 * التشغيل: php tests/fx_currencies_screen_http_proof.php   (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$BASE = 'http://localhost/ems';
$TMP  = sys_get_temp_dir();
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

function fx_req($url, $jar, $post = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}
function fx_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = fx_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return fx_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}
function fx_msg($headers) {
    if (preg_match('~Location:\s*(\S+)~i', $headers, $m)) {
        $q = parse_url(trim($m[1]), PHP_URL_QUERY);
        parse_str((string) $q, $p);
        return isset($p['msg']) ? $p['msg'] : '';
    }
    return '';
}

// ── بيئةُ القياس ─────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
if ($db->connect_error) { fwrite(STDERR, "DB: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

$CO    = 4;
$CODE  = 'QAR';                      // عملةُ اختبارٍ غيرُ مستعملةٍ في أي بيانات
$FROM  = '2091-01-01';
$RATE  = 0.25;
$MARK  = 'FXP' . getmypid();
$SCR   = $BASE . '/Finance/currencies_fin.php';

// كنسٌ استباقي
$db->query("DELETE FROM ems_business_events WHERE event_no LIKE 'FXP%'");
$db->query("DELETE FROM fin_fx_rates   WHERE currency_code = '{$CODE}'");
$db->query("DELETE FROM fin_currencies WHERE code = '{$CODE}'");

// عملةٌ مسجَّلةٌ بلا سعر + حدثان ينتظرانه (مبلغان معلومان لنتحقق من الحساب)
$db->query("INSERT INTO fin_currencies (company_id, code, name_ar, symbol, decimals, is_base, active, sort_order, created_at)
            VALUES ({$CO}, '{$CODE}', 'ريالُ اختبار', 'ر', 2, 0, 1, 98, NOW())");
// `event_uuid` = CHAR(26) بالضبط — نصٌّ أطولُ يُبتر فتتساوى المعرِّفاتُ وتتصادم.
$uuid = function ($i) use ($MARK) {
    return substr(str_pad($MARK . 'X' . $i, 26, '0', STR_PAD_LEFT), -26);
};
// حدثان بعد السريان بمبلغين معلومين، وثالثٌ **قبله** يجب أن يبقى بلا معادل
$seedEvents = array(
    array(1, 400.00, '2091-03-01 10:00:00'),
    array(2,  33.33, '2091-04-01 10:00:00'),
    array(3, 100.00, '2090-06-01 10:00:00'),
);
foreach ($seedEvents as $e) {
    list($i, $amt, $when) = $e;
    $st = $db->prepare(
        "INSERT INTO ems_business_events (company_id, event_no, event_uuid, event_key, category,
         source_module, entity_type, entity_id, amount, currency, event_status, occurred_at, created_at)
         VALUES (?, ?, ?, ?, 'revenue', 'test', 'test', ?, ?, ?, 'recorded', ?, NOW())");
    $no  = "{$MARK}-{$i}";
    $uu  = $uuid($i);
    $key = "fx:proof:{$i}";
    $st->bind_param('isssidss', $CO, $no, $uu, $key, $i, $amt, $CODE, $when);
    $st->execute();
    $st->close();
}

$jar = $TMP . "/fx_proof_{$MARK}.txt";

// ═══════════════════════════════════════════════════════════════════════════
head('① الشاشةُ تُفتح لمدير المالية وتقول حالَها');

fx_login('مديرمالي', $jar);
list($c, $h, $b) = fx_req($SCR, $jar);
check($c === 200, "الشاشةُ ترد 200 (وُجد {$c})");
check(strpos($b, 'العملات وأسعار الصرف') !== false, 'وعنوانُها ظاهر');
check(strpos($b, 'عملةُ الأساس') !== false, 'وتُعلن عملةَ الأساس');
check(strpos($b, 'ريالُ اختبار') !== false, 'وتعرض العملةَ المسجَّلة');
check(strpos($b, 'لا سعرَ بعد') !== false, '★ وتقول صراحةً «لا سعرَ بعد» لمن لا سعرَ له');
check(strpos($b, 'بانتظار سعرِ صرفٍ لتاريخه') !== false, 'وتَعُدّ ما ينتظر سعرًا');
// حقلُ السعر نصيٌّ لا رقميّ — `type=number` كان يرفض الفاصلةَ العربية
// والأرقامَ الهندية بصمتٍ فيبدو أنه لا يقبل الكسور.
check(preg_match('~name="rate_to_base"[^>]*type="text"~', $b) === 1
      || preg_match('~type="text"[^>]*name="rate_to_base"~', $b) === 1,
    '★ وحقلُ السعر نصيٌّ يقبل الكسر');
check(strpos($b, 'inputmode="decimal"') !== false, 'وبلوحةِ أرقامٍ عشريةٍ على الهاتف');

// الحمايةُ مركزيةٌ تُحقن في كل فورم (ob في config.php) — تُلتقط من الصفحة نفسِها
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $tk);
$csrf = isset($tk[1]) ? $tk[1] : '';
check($csrf !== '', 'ورمزُ الحماية مضمَّنٌ آليًّا في نماذجها');

// ═══════════════════════════════════════════════════════════════════════════
head('② سعرٌ من الشاشة — والتقييمُ يجري فورًا');

list($c, $h, $b) = fx_req($SCR, $jar, array(
    'add_rate'       => '1',
    'csrf_token'     => $csrf,
    'currency_code'  => $CODE,
    'rate_to_base'   => (string) $RATE,
    'effective_from' => $FROM,
    'source'         => 'برهان',
    'note'           => $MARK,
));
$msg = fx_msg($h);
check(strpos($msg, 'سُجّل السعر') !== false, "السعرُ حُفظ من الشاشة (رسالة: {$msg})");
check(strpos($msg, 'وقُيّم') !== false, '★ والرسالةُ تقول كم صفًّا قُيّم — لا حفظٌ صامت');

$r = $db->query("SELECT event_no, amount, fx_rate, base_amount FROM ems_business_events
                  WHERE event_no LIKE '{$MARK}-%' ORDER BY event_no");
$rows = array();
while ($x = $r->fetch_assoc()) { $rows[$x['event_no']] = $x; }

check(isset($rows["{$MARK}-1"]) && $rows["{$MARK}-1"]['base_amount'] !== null,
    'الحدثُ الذي بعد السريان قُيّم');
check(isset($rows["{$MARK}-3"]) && $rows["{$MARK}-3"]['base_amount'] === null,
    '★ والذي قبله بقي بلا معادل — لا يُستعار سعرُ ما بعده');

// ═══════════════════════════════════════════════════════════════════════════
head('③ التقييمُ صادقٌ حسابيًّا: base = ROUND(amount × rate, 2)');

$e1 = isset($rows["{$MARK}-1"]) ? $rows["{$MARK}-1"] : null;
$e2 = isset($rows["{$MARK}-2"]) ? $rows["{$MARK}-2"] : null;
check($e1 && abs((float) $e1['fx_rate'] - $RATE) < 0.00000001, 'والسعرُ المخزَّن هو المُدخل');
check($e1 && abs((float) $e1['base_amount'] - 100.00) < 0.005,
    '★ 400 × 0.25 = 100.00 (وُجد ' . ($e1 ? $e1['base_amount'] : '—') . ')');
check($e2 && abs((float) $e2['base_amount'] - 8.33) < 0.005,
    'و33.33 × 0.25 = 8.3325 → 8.33 بتقريبِ خانتين (وُجد ' . ($e2 ? $e2['base_amount'] : '—') . ')');

// ═══════════════════════════════════════════════════════════════════════════
head('③-ب الكسرُ العشريُّ يمرّ من الشاشة بصيغتيه');

// كسرٌ صغيرٌ جدًّا بالنقطة (الصيغةُ المتوقَّعة لسعر الجنيه مقابل الدولار)
list($c, $h, $b) = fx_req($SCR, $jar, array(
    'add_rate' => '1', 'csrf_token' => $csrf, 'currency_code' => $CODE,
    'rate_to_base' => '0.00166667', 'effective_from' => '2092-01-01',
));
check(strpos(fx_msg($h), 'سُجّل السعر') !== false, '★ كسرٌ بثمانِ خاناتٍ (0.00166667) يُقبل');
$r = $db->query("SELECT rate_to_base FROM fin_fx_rates
                  WHERE currency_code='{$CODE}' AND effective_from='2092-01-01'");
$got = $r->fetch_assoc();
check($got && abs((float) $got['rate_to_base'] - 0.00166667) < 0.000000005,
    'ويُخزَّن بدقّته لا مبتورًا (وُجد ' . ($got ? $got['rate_to_base'] : '—') . ')');

// الفاصلةُ العربية ٫ — ما كان `type=number` يرفضه صامتًا
list($c, $h, $b) = fx_req($SCR, $jar, array(
    'add_rate' => '1', 'csrf_token' => $csrf, 'currency_code' => $CODE,
    'rate_to_base' => '0٫75', 'effective_from' => '2092-02-01',
));
check(strpos(fx_msg($h), 'سُجّل السعر') !== false, '★ والفاصلةُ العشرية العربية (0٫75) تُقبل');
$r = $db->query("SELECT rate_to_base FROM fin_fx_rates
                  WHERE currency_code='{$CODE}' AND effective_from='2092-02-01'");
$got = $r->fetch_assoc();
check($got && abs((float) $got['rate_to_base'] - 0.75) < 0.0000001, 'وتُقرأ 0.75 لا 75');

// وما ليس رقمًا يُردّ برسالةٍ صريحة لا يُحفظ صفرًا
list($c, $h, $b) = fx_req($SCR, $jar, array(
    'add_rate' => '1', 'csrf_token' => $csrf, 'currency_code' => $CODE,
    'rate_to_base' => 'ثمانية', 'effective_from' => '2092-09-01',
));
$msg = fx_msg($h);
check(strpos($msg, 'رقم') !== false, "★ وما ليس رقمًا يُردّ صراحةً ({$msg})");
$r = $db->query("SELECT COUNT(*) c FROM fin_fx_rates
                  WHERE currency_code='{$CODE}' AND effective_from='2092-09-01'");
check(intval($r->fetch_assoc()['c']) === 0, 'ولا يُحفظ صفرًا صامتًا');

// ═══════════════════════════════════════════════════════════════════════════
head('④ العطالة: سعرٌ بالتاريخ نفسِه لا يتكرر');

list($c, $h, $b) = fx_req($SCR, $jar, array(
    'add_rate' => '1', 'csrf_token' => $csrf, 'currency_code' => $CODE,
    'rate_to_base' => '0.30', 'effective_from' => $FROM,
));
$msg = fx_msg($h);
check(strpos($msg, 'سلفًا') !== false, "★ يُرفض بمرجع القائم لا يُنشئ ثانيًا (رسالة: {$msg})");
$r = $db->query("SELECT COUNT(*) c FROM fin_fx_rates WHERE currency_code='{$CODE}' AND effective_from='{$FROM}'");
check(intval($r->fetch_assoc()['c']) === 1, 'وصفٌّ واحدٌ لا صفّان');

// ═══════════════════════════════════════════════════════════════════════════
head('⑤ الصلاحية: القارئُ المالي يفتح ولا يضبط');

$jar2 = $TMP . "/fx_proof_rdr_{$MARK}.txt";
fx_login('fin.reader@equipation.sd', $jar2);
list($c, $h, $b) = fx_req($SCR, $jar2);
check($c === 200, 'القارئُ يفتح الشاشة');
check(strpos($b, 'سعرُ صرفٍ جديد') === false, '★ ولا يرى نموذجَ الإضافة');

// لا نموذجَ في صفحته ⇒ **لا رمزَ حمايةٍ أصلًا** — فالطلبُ المزوَّر يسقط عند
// الحارس المركزي (403) قبل بلوغ فحص الصلاحية. حاجزان لا حاجزٌ واحد.
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $tk2);
check(!isset($tk2[1]), '★ ولا يُمنح رمزَ حمايةٍ أصلًا — فلا يملك تكوينَ طلبٍ مقبول');

list($c, $h, $b) = fx_req($SCR, $jar2, array(
    'add_rate' => '1', 'csrf_token' => isset($tk2[1]) ? $tk2[1] : '',
    'currency_code' => $CODE,
    'rate_to_base' => '9.99', 'effective_from' => '2091-08-01',
));
$msg = fx_msg($h);
$refused = ($c === 403) || (strpos($msg, 'لا توجد صلاحية') !== false);
check($refused, "ومحاولتُه تُردّ صراحةً لا صمتًا (رمز {$c})");
$r = $db->query("SELECT COUNT(*) c FROM fin_fx_rates WHERE currency_code='{$CODE}' AND effective_from='2091-08-01'");
check(intval($r->fetch_assoc()['c']) === 0, 'ولا صفَّ كُتب');

// ═══════════════════════════════════════════════════════════════════════════
head('⑥ الكنس');

$db->query("DELETE FROM ems_business_events WHERE event_no LIKE '{$MARK}-%'");
$db->query("DELETE FROM fin_fx_rates   WHERE currency_code = '{$CODE}'");
$db->query("DELETE FROM fin_currencies WHERE code = '{$CODE}'");
@unlink($jar); @unlink($jar2);

$r = $db->query("SELECT COUNT(*) c FROM fin_currencies WHERE code='{$CODE}'");
check(intval($r->fetch_assoc()['c']) === 0, 'لا أثرَ لعملة البرهان');
$r = $db->query("SELECT COUNT(*) c FROM ems_business_events WHERE event_no LIKE 'FXP%'");
check(intval($r->fetch_assoc()['c']) === 0, 'ولا حدثَ برهانٍ في الدفتر');

fwrite(STDOUT, "\n══════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
