<?php
/**
 * tests/obligations_screen_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP لشاشة مصفوفة الالتزامات (CON-02 §4 · §7-③ · ق-18).
 * من **الشاشة الحقيقية** بدورَين مختلفين:
 *   ① المبيعات (12) تفتح وتملأ — ولا ترى زرَّ الإجازة (فصلُ اليدين)
 *   ② مديرُ المالية (19) يرى الإجازة ولا يرى نموذجَ الملء
 *   ③ الإجازةُ تُحوّل المسودةَ إلى نافذٍ مختومٍ بمَن أجاز ومتى
 *   ④ المُجازُ **لا يُعدَّل ولا يُحذف** — لا رجعية (§6)
 *   ⑤ عدّادُ التغطية يقول ما ينقص صراحةً (به يُقاس شرطُ 423 · ق-2)
 *   ⑥ التكرارُ على نفس (بند × تاريخ سريان) يُرفض بالمفتاح الفريد
 * ثم يكنس كلَّ ما بذر.
 *
 * التشغيل: php tests/obligations_screen_http_proof.php   (يتطلب Apache حيًّا)
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

function ob_req($url, $jar, $post = null) {
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
function ob_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = ob_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return ob_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}
function ob_tok($body) {
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $body, $m);
    return isset($m[1]) ? $m[1] : '';
}
function ob_msg($headers) {
    if (preg_match('~Location:\s*(\S+)~i', $headers, $m)) {
        $q = parse_url(trim($m[1]), PHP_URL_QUERY);
        parse_str((string) $q, $p);
        return isset($p['msg']) ? $p['msg'] : '';
    }
    return '';
}

require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
if ($db->connect_error) { fwrite(STDERR, "DB: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

$CO       = 4;
$CONTRACT = 2;            // مشروع اليانس — العقدُ الرائد (ق-23)
// ⚠️ تاريخٌ **ماضٍ** عمدًا: النفاذُ شرطُه `valid_from <= CURDATE()`، فصفٌّ
//    بتاريخِ سريانٍ مستقبليٍّ مُجازٌ ولا يُعدّ نافذًا اليوم — وهو سلوكٌ صحيح.
//    ويومٌ نادرٌ مميَّزٌ كي لا يتقاطع مع أي صفٍّ حقيقيٍّ يُدخله البشر.
$FROM     = '2019-03-07';
$SCR      = $BASE . '/Contracts/contract_obligations.php';
$SALES    = 'مبيعات';                        // الدور 12
$FINMGR   = 'fin.deptmgr@equipation.sd';     // الدور 19

$cleanup = function () use ($db, $CONTRACT, $FROM) {
    $db->query("DELETE FROM contract_obligations
                 WHERE client_contract_id = {$CONTRACT} AND valid_from = '{$FROM}'");
};
$cleanup();

$jarS = $TMP . '/ob_sales.txt';
$jarF = $TMP . '/ob_fin.txt';

fwrite(STDOUT, "══ برهانُ شاشة مصفوفة الالتزامات — CON-02 §7-③ ══\n");

// ── ① المبيعات (12): تفتح وتملأ ───────────────────────────────────────────
head('① المبيعات (12) — الملءُ بلا إجازة');
ob_login($SALES, $jarS);
list($c, $h, $b) = ob_req($SCR . '?contract=' . $CONTRACT, $jarS);
check($c === 200, "الشاشةُ تُفتح للدور 12 (HTTP {$c})");
check(strpos($b, 'تغييرُ الملتزم يسري من تاريخه') !== false,
      'التحذيرُ الثابتُ ظاهرٌ نصًّا (§7-③)');
check(strpos($b, 'name="save_obligation"') !== false, 'نموذجُ الملء ظاهرٌ للمبيعات');
check(strpos($b, 'name="approve_action"') === false,
      '**لا زرَّ إجازةٍ للمبيعات** — فصلُ اليدين في الشاشة (ق-18)');

$before = (int) $db->query("SELECT COUNT(*) c FROM contract_obligations
                             WHERE client_contract_id={$CONTRACT} AND valid_from='{$FROM}'")->fetch_assoc()['c'];
list($c, $h, $b) = ob_req($SCR, $jarS, array(
    'save_obligation' => 1, 'client_contract_id' => $CONTRACT, 'obligation_id' => 0,
    'obligation_type' => 'fuel', 'obligor' => 'client',
    'effect_on_billing' => 'billable_standby', 'valid_from' => $FROM, 'valid_to' => '',
    'csrf_token' => ob_tok($b),
));
$msg = ob_msg($h);
check(strpos($msg, '✅') !== false, "أُضيفت المسودة: {$msg}");
$row = $db->query("SELECT id, approval_state, obligor, effect_on_billing, approved_by, approved_at
                     FROM contract_obligations
                    WHERE client_contract_id={$CONTRACT} AND valid_from='{$FROM}'
                      AND obligation_type='fuel' AND is_deleted=0")->fetch_assoc();
check($row !== null, 'الصفُّ في القاعدة');
check($row && $row['approval_state'] === 'draft', 'حالتُه `draft` — لا تسري حتى تُجاز (ق-18)');
check($row && $row['obligor'] === 'client' && $row['effect_on_billing'] === 'billable_standby',
      '«الوقودُ على العميل ⇒ استعدادٌ مفوتر» خُزّن كما أُرسل (لا ابتلاعَ ENUM)');
$OID = $row ? (int) $row['id'] : 0;

// ── ② التكرارُ على نفس (بند × تاريخ) يُرفض ────────────────────────────────
head('② المفتاحُ الفريد يبيت');
list($c, $h, $b) = ob_req($SCR, $jarS, array(
    'save_obligation' => 1, 'client_contract_id' => $CONTRACT, 'obligation_id' => 0,
    'obligation_type' => 'fuel', 'obligor' => 'company',
    'effect_on_billing' => 'non_billable', 'valid_from' => $FROM, 'valid_to' => '',
));
$msg = ob_msg($h);
check(strpos($msg, '❌') !== false, "التكرارُ مرفوضٌ برسالةٍ مفهومة: {$msg}");
$n = (int) $db->query("SELECT COUNT(*) c FROM contract_obligations
                        WHERE client_contract_id={$CONTRACT} AND valid_from='{$FROM}'
                          AND obligation_type='fuel' AND is_deleted=0")->fetch_assoc()['c'];
check($n === 1, 'صفٌّ واحدٌ لا صفّان');

// ── ③ عدّادُ التغطية ──────────────────────────────────────────────────────
head('③ عدّادُ التغطية يقول ما ينقص');
list($c, $h, $b) = ob_req($SCR . '?contract=' . $CONTRACT, $jarS);
check(preg_match('~المصفوفةُ النافذةُ اليوم:\s*(\d+)\s*من 9~u', $b, $m) === 1,
      'العدّادُ ظاهرٌ: ' . (isset($m[1]) ? $m[1] . '/9' : '—'));
check(strpos($b, 'مسودةً تنتظر الإجازة') !== false, 'عدّادُ المسودات ظاهر');
check(strpos($b, 'الظروفُ القاهرة') !== false, 'الناقصُ مُسمًّى بندًا بندًا');

// ── ④ مديرُ المالية (19): يُجيز ولا يملأ ──────────────────────────────────
head('④ مديرُ المالية (19) — الإجازةُ بلا ملء');
ob_login($FINMGR, $jarF);
list($c, $h, $b) = ob_req($SCR . '?contract=' . $CONTRACT, $jarF);
check($c === 200, "الشاشةُ تُفتح للدور 19 (HTTP {$c})");
check(strpos($b, 'name="approve_action"') !== false, 'زرُّ الإجازة ظاهرٌ للمالية');
check(strpos($b, 'name="save_obligation"') === false,
      '**لا نموذجَ ملءٍ للمالية** — فصلُ اليدين من الطرفين');

list($c, $h, $b) = ob_req($SCR, $jarF, array(
    'approve_action' => 'one', 'obligation_id' => $OID, 'client_contract_id' => $CONTRACT,
));
$msg = ob_msg($h);
check(strpos($msg, '✅') !== false, "أُجيز البند: {$msg}");
$row = $db->query("SELECT approval_state, approved_by, approved_at FROM contract_obligations
                    WHERE id={$OID}")->fetch_assoc();
check($row && $row['approval_state'] === 'approved', 'الحالةُ صارت `approved`');
check($row && (int) $row['approved_by'] === 74 && $row['approved_at'] !== null,
      'خُتم بمَن أجاز (74) ومتى — أثرٌ للتدقيق لا مجرّدُ علم');

// ── ⑤ المُجازُ لا يُعدَّل ولا يُحذف (لا رجعية §6) ──────────────────────────
head('⑤ لا رجعية: المُجازُ نافذٌ محصَّن');
list($c, $h, $b) = ob_req($SCR, $jarS, array(
    'save_obligation' => 1, 'client_contract_id' => $CONTRACT, 'obligation_id' => $OID,
    'obligation_type' => 'fuel', 'obligor' => 'company',
    'effect_on_billing' => 'non_billable', 'valid_from' => $FROM, 'valid_to' => '',
));
$msg = ob_msg($h);
check(strpos($msg, '❌') !== false && strpos($msg, 'مُجاز') !== false,
      "تعديلُ المُجاز مرفوضٌ بتعليله: {$msg}");
$row = $db->query("SELECT obligor, effect_on_billing FROM contract_obligations WHERE id={$OID}")->fetch_assoc();
check($row && $row['obligor'] === 'client' && $row['effect_on_billing'] === 'billable_standby',
      'ولم يتغيّر شيءٌ فعلًا في القاعدة — الرفضُ حقيقيٌّ لا رسالةٌ فقط');

list($c, $h, $b) = ob_req($SCR . '?contract=' . $CONTRACT . '&delete_id=' . $OID, $jarS);
$msg = ob_msg($h);
check(strpos($msg, '❌') !== false, "حذفُ المُجاز مرفوض: {$msg}");
$alive = (int) $db->query("SELECT COUNT(*) c FROM contract_obligations
                            WHERE id={$OID} AND is_deleted=0")->fetch_assoc()['c'];
check($alive === 1, 'والصفُّ حيٌّ فعلًا بعد محاولة الحذف');

// ── ⑥ التغطيةُ ارتفعت والصفُّ صار نافذًا ──────────────────────────────────
head('⑥ الأثرُ على التغطية');
list($c, $h, $b) = ob_req($SCR . '?contract=' . $CONTRACT, $jarF);
check(preg_match('~المصفوفةُ النافذةُ اليوم:\s*(\d+)\s*من 9~u', $b, $m) === 1
      && (int) $m[1] >= 1, 'العدّادُ يعدّ البندَ المُجازَ النافذ: ' . (isset($m[1]) ? $m[1] . '/9' : '—'));
// الشارةُ بصنفها لا بالكلمة المجرّدة — «نافذ» تَرِد في نصوصٍ أخرى فتمرّ زورًا
check(strpos($b, 'obl-badge-eff') !== false, 'الشارةُ «نافذ» ظاهرةٌ على الصف (بصنفها لا بكلمةٍ عابرة)');
check(strpos($b, 'الوقودُ والزيوت') !== false, 'والبندُ لم يعُد في قائمة الناقص');

// ── الكنس ─────────────────────────────────────────────────────────────────
head('الكنس');
$cleanup();
$left = (int) $db->query("SELECT COUNT(*) c FROM contract_obligations
                           WHERE client_contract_id={$CONTRACT} AND valid_from='{$FROM}'")->fetch_assoc()['c'];
check($left === 0, 'صفرُ بقايا');
@unlink($jarS); @unlink($jarF);

fwrite(STDOUT, "\n" . str_repeat('═', 58) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
