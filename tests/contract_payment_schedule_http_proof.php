<?php
/**
 * tests/contract_payment_schedule_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP للشاشة **176** — خطةُ دفع العقد (P-05 · PLAN-03 §3.5)
 * من الشاشة الحقيقية بمسؤول المبيعات (12):
 *   ① الشاشةُ تُفتح و**قاعدةُ عدم الخلط معلَنةٌ نصًّا**
 *   ② والتوليدُ من الشاشة **بمقدمٍ مستهلَك** يقع — والأسطرُ من الجدول لا بالقسمة
 *   ③ و**التعبئةُ بلا نصِّ عقدٍ يرفضها الخادم**
 *   ④ والقبضُ من الشاشة: **المستهلَكُ يدخل دفترَ السلف والإيرادُ لا يدخله**
 *   ⑤ والنسخةُ الجديدةُ تُفتح **والقديمةُ تظهر مختومةً في الشاشة نفسِها**
 * ثم يكنس كلَّ ما بذر.
 *
 * التشغيل: php tests/contract_payment_schedule_http_proof.php   (يتطلب Apache حيًّا)
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

function ps_req($url, $jar, $post = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60,
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
function ps_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = ps_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return ps_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}
function ps_msg($headers) {
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

$CO   = 4;
$MARK = 'P05H' . getmypid();

$teardown = function () use ($db, $MARK) {
    $db->query("DELETE s FROM contract_payment_schedule s JOIN contracts c ON c.id = s.contract_id
                 WHERE c.first_party LIKE '%{$MARK}%'");
    $db->query("DELETE a FROM contract_advances a JOIN contracts c ON c.id = a.contract_id
                 WHERE c.first_party LIKE '%{$MARK}%'");
    $db->query("DELETE p FROM contract_monthly_plan p JOIN contracts c ON c.id = p.contract_id
                 WHERE c.first_party LIKE '%{$MARK}%'");
    $db->query("DELETE l FROM client_contract_lines l JOIN contracts c ON c.id = l.contract_id
                 WHERE c.first_party LIKE '%{$MARK}%'");
    $db->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $db->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ برهانُ HTTP — الشاشة 176 · خطةُ دفع العقد ══\n");

head('البذر — عقدٌ ببندٍ وجدولٍ شهريٍّ من أربعة أشهر');
$db->query("INSERT INTO project (company_id, name, client, location, total)
            VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($db->insert_id);
$db->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
            actual_start, actual_end, first_party, second_party, contract_status, project_id,
            price_currency_contract, retention_pct, created_at)
            VALUES ({$CO}, '2085-01-01', 120, '2085-01-01', '2085-04-30',
                    'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'دولار', 5.00, NOW())");
$CID = intval($db->insert_id);
$db->query("INSERT INTO client_contract_lines
    (company_id, contract_id, line_no, pricing_model, description, qty_contracted,
     unit_price, currency, valid_from, valid_to, tax_status, state, created_at)
    VALUES ({$CO}, {$CID}, 1, 'ton', 'نقلُ خامٍ — بندُ {$MARK}', 4000,
            10.0000, 'USD', '2085-01-01', '2085-04-30', 'exempt', 'active', NOW())");
$LID = intval($db->insert_id);
foreach (array('2085-01' => 1000, '2085-02' => 1500, '2085-03' => 0, '2085-04' => 1500) as $mm => $q) {
    $kind = ($q == 0) ? 'shutdown' : 'normal';
    $db->query("INSERT INTO contract_monthly_plan
        (company_id, contract_id, line_id, plan_version, effective_from, period_month,
         qty_planned, month_kind, created_at)
        VALUES ({$CO}, {$CID}, {$LID}, 1, '2085-01-01', '{$mm}', {$q}, '{$kind}', NOW())");
}
$db->query("UPDATE client_contract_lines SET qty_planned_total=4000 WHERE id={$LID}");
check($CID > 0 && $LID > 0, "عقدٌ #{$CID} · بندٌ #{$LID} (4,000 × 10 = 40,000 USD · آذارُ توقف)");

$jar = $TMP . "/ps_{$MARK}.txt";
ps_login('مبيعات', $jar);
list($c, $h, $b) = ps_req($BASE . '/Contracts/contract_payment_schedule.php?contract=' . $CID, $jar);
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $tk);
$TOK = isset($tk[1]) ? $tk[1] : '';

head('① الشاشةُ تُفتح و**قاعدةُ عدم الخلط معلَنةٌ نصًّا**');
check($c === 200 && mb_strpos($b, 'خطة دفع العقد') !== false, 'الشاشةُ 176 تُفتح بالدور 12 (200)');
check(mb_strpos($b, 'يقلب التزامًا إلى إيرادٍ') !== false,
      '★ والقاعدةُ مكتوبةٌ على الشاشة — لا في التوثيق وحدَه');
check(mb_strpos($b, 'ليست قيمةَ العقد') !== false,
      '★ و«Σ الخطة ليست قيمةَ العقد» معلَنةٌ فلا تُقرأ خطأً');

head('③ و**التعبئةُ بلا نصِّ عقدٍ يرفضها الخادم**');
list($c, $h, $b2) = ps_req($BASE . '/Contracts/contract_payment_schedule.php', $jar, array(
    'ps_action' => 'generate', 'contract_id' => $CID, 'pattern' => 'advance_then_monthly',
    'due_days' => 30, 'adv_type' => 'mobilization', 'adv_percent' => '10',
    'adv_treatment' => 'revenue', 'adv_basis_text' => '', 'csrf_token' => $TOK));
$msg = ps_msg($h);
check(mb_strpos($msg, '422') !== false && mb_strpos($msg, 'نصُّ العقد') !== false, '★★ ' . $msg);
$n = intval($db->query("SELECT COUNT(*) c FROM contract_payment_schedule WHERE contract_id={$CID}")
                 ->fetch_assoc()['c']);
check($n === 0, 'وصفرُ سطرٍ كُتب');

head('② والتوليدُ **بمقدمٍ مستهلَك** يقع من الشاشة');
list($c, $h, $b2) = ps_req($BASE . '/Contracts/contract_payment_schedule.php', $jar, array(
    'ps_action' => 'generate', 'contract_id' => $CID, 'pattern' => 'advance_then_monthly',
    'due_days' => 30, 'adv_type' => 'recoverable', 'adv_percent' => '25',
    'adv_due' => '2085-01-05', 'csrf_token' => $TOK));
$msg = ps_msg($h);
check(mb_strpos($msg, 'وُلّدت خطةُ') !== false, '★★ ' . $msg);
$rows = $db->query("SELECT payment_kind, COUNT(*) n FROM contract_payment_schedule
                     WHERE contract_id={$CID} AND effective_to IS NULL GROUP BY payment_kind");
$by = array();
while ($x = $rows->fetch_assoc()) { $by[$x['payment_kind']] = intval($x['n']); }
check(isset($by['monthly_settlement']) && $by['monthly_settlement'] === 3,
      '★★ **ثلاثُ تسوياتٍ لا أربع** — وآذارُ التوقفُ لا دفعةَ له');
check(isset($by['advance']) && $by['advance'] === 1 && isset($by['retention_release']),
      'ومقدمٌ واحدٌ وردُّ محتجزٍ واحد');

list($c, $h, $b) = ps_req($BASE . '/Contracts/contract_payment_schedule.php?contract=' . $CID, $jar);
check(mb_strpos($b, '10000.00') !== false, '★ والشاشةُ تُري مقدمًا **10,000** = 25% من 40,000');
check(mb_strpos($b, '15000.00') !== false, 'وتسويةَ شباطَ **15,000** — من الجدول لا بالقسمة');
check(mb_strpos($b, 'التزام') !== false, 'وسطرُ المقدم موسومٌ **التزامًا** على الشاشة');

head('④ والقبضُ من الشاشة: **المستهلَكُ يدخل دفترَ السلف والإيرادُ لا يدخله**');
$ADV = intval($db->query("SELECT id FROM contract_payment_schedule
                           WHERE contract_id={$CID} AND payment_kind='advance'")->fetch_assoc()['id']);
list($c, $h, $b2) = ps_req($BASE . '/Contracts/contract_payment_schedule.php', $jar, array(
    'ps_action' => 'receive', 'contract_id' => $CID, 'row_id' => $ADV,
    'amount' => '10000', 'received_date' => '2085-01-05',
    'doc_ref' => 'RCPT-A-' . $MARK, 'csrf_token' => $TOK));
check(mb_strpos(ps_msg($h), 'دخل دفترَ السلف') !== false, '★ ' . ps_msg($h));
$nAdv = intval($db->query("SELECT COUNT(*) c FROM contract_advances WHERE contract_id={$CID}")
                    ->fetch_assoc()['c']);
check($nAdv === 1, 'و`contract_advances` صارت صفًّا واحدًا — **مصدرٌ واحدٌ للرقم**');

list($c, $h, $b2) = ps_req($BASE . '/Contracts/contract_payment_schedule.php', $jar, array(
    'ps_action' => 'add_row', 'contract_id' => $CID, 'kind' => 'advance',
    'row_pattern' => 'partial_advance', 'amount' => '3000', 'currency' => 'USD',
    'due_date' => '2085-01-10', 'row_adv_type' => 'non_refundable_booking',
    'note' => 'حجزٌ ' . $MARK, 'csrf_token' => $TOK));
check(mb_strpos(ps_msg($h), 'أُضيف سطرُ') !== false, 'وأُضيف سطرُ **رسومِ حجزٍ غيرِ مستردة**');
$BK = intval($db->query("SELECT id FROM contract_payment_schedule
                          WHERE contract_id={$CID} AND advance_type='non_refundable_booking'
                            AND effective_to IS NULL")->fetch_assoc()['id']);
list($c, $h, $b2) = ps_req($BASE . '/Contracts/contract_payment_schedule.php', $jar, array(
    'ps_action' => 'receive', 'contract_id' => $CID, 'row_id' => $BK,
    'amount' => '3000', 'received_date' => '2085-01-10',
    'doc_ref' => 'RCPT-B-' . $MARK, 'csrf_token' => $TOK));
check(mb_strpos(ps_msg($h), 'لا يدخل دفترَ السلف') !== false, '★★ ' . ps_msg($h));
$nAdv2 = intval($db->query("SELECT COUNT(*) c FROM contract_advances WHERE contract_id={$CID}")
                     ->fetch_assoc()['c']);
check($nAdv2 === 1,
      '★★★ و`contract_advances` **ما زالت صفًّا واحدًا** — الإيرادُ لم يدخلها فلا يُستقطع من مستخلص');

list($c, $h, $b) = ps_req($BASE . '/Contracts/contract_payment_schedule.php?contract=' . $CID, $jar);
check(mb_strpos($b, 'مقبوضٌ التزامًا') !== false && mb_strpos($b, 'لا يُجمعان') !== false,
      '★ والشاشةُ تفرز المقبوضَ **التزامًا وإيرادًا لا يُجمعان**');

head('⑤ والنسخةُ الجديدةُ تُفتح **والقديمةُ تظهر مختومة**');
list($c, $h, $b2) = ps_req($BASE . '/Contracts/contract_payment_schedule.php', $jar, array(
    'ps_action' => 'new_version', 'contract_id' => $CID,
    'effective_from' => '2085-03-01', 'csrf_token' => $TOK));
check(mb_strpos(ps_msg($h), 'مختومةٌ في') !== false, '★ ' . ps_msg($h));
list($c, $h, $b) = ps_req($BASE . '/Contracts/contract_payment_schedule.php?contract=' . $CID, $jar);
check(mb_strpos($b, '2085-02-28') !== false && mb_strpos($b, 'نافذة') !== false,
      '★★ ولوحُ النسخ يُري **1 مختومةً في 2085-02-28** و**2 نافذة**');
$v1 = intval($db->query("SELECT COUNT(*) c FROM contract_payment_schedule
                          WHERE contract_id={$CID} AND version=1")->fetch_assoc()['c']);
check($v1 > 0, "والنسخةُ 1 **باقيةٌ بـ{$v1} سطرًا** — «القديمةُ محفوظة»");

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
