<?php
/**
 * tests/transfer_tariff_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP للشاشة **168** — تعرفةُ الترحيل وتسعيرُ الأوامر (M-52 · ENT-02 §3-④)
 * من الشاشة الحقيقية بمدير النقل (23):
 *   ① الشاشةُ تُفتح وتُعلن **الأوامرَ المسلَّمةَ بلا تسعير** عددًا — الفجوةُ ظاهرة
 *   ② والتسعيرُ **بلا تعرفةٍ يُرفض من الخادم** بسببه
 *   ③ وتعرفةٌ تُكتب من الشاشة، ثم التسعيرُ يقع **بمبلغٍ محسوبٍ لا مُدخَل**
 *   ④ وتسعيرٌ ثانٍ بلا حجّةٍ **يُرفض 409**
 *   ⑤ والصفُّ المكتوب يحمل مرجعَ تعرفته — «المبلغُ يُقرأ من مصدره»
 * ثم يكنس كلَّ ما بذر.
 *
 * التشغيل: php tests/transfer_tariff_http_proof.php   (يتطلب Apache حيًّا)
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

function tt_req($url, $jar, $post = null) {
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
function tt_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = tt_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return tt_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}
function tt_msg($headers) {
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
$MARK = 'M52H' . getmypid();

$teardown = function () use ($db, $MARK) {
    $db->query("DELETE l FROM transfer_lines l JOIN transfer_orders o ON o.id = l.order_id
                 WHERE o.notes LIKE '%{$MARK}%'");
    $db->query("DELETE FROM transfer_orders WHERE notes LIKE '%{$MARK}%'");
    $db->query("DELETE FROM transfer_tariffs WHERE note LIKE '%{$MARK}%'");
    $db->query("DELETE FROM trs_locations WHERE code LIKE '%{$MARK}%'");
    $db->query("DELETE FROM suppliers WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ برهانُ HTTP — الشاشة 168 · تعرفةُ الترحيل ══\n");

head('البذر — موردٌ ومساران وأمرٌ مسلَّمٌ محمَّلٌ عليه');
$db->query("INSERT INTO suppliers (company_id, name, phone, created_at)
            VALUES ({$CO}, 'موردُ {$MARK}', '0100000000', NOW())");
$SUP = intval($db->insert_id);
$db->query("INSERT INTO trs_locations (company_id, code, name, location_type, created_at, updated_at)
            VALUES ({$CO}, 'HF-{$MARK}', 'مبدأُ {$MARK}', 'base', NOW(), NOW())");
$LF = intval($db->insert_id);
$db->query("INSERT INTO trs_locations (company_id, code, name, location_type, created_at, updated_at)
            VALUES ({$CO}, 'HT-{$MARK}', 'منتهى {$MARK}', 'project', NOW(), NOW())");
$LT = intval($db->insert_id);
$TY = intval($db->query("SELECT id FROM transfer_types WHERE company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$db->query("INSERT INTO transfer_orders
    (company_id, order_no, transfer_type_id, direction, source_module,
     from_location_id, to_location_id, request_date, planned_date, arrival_datetime,
     cost_bearer, charge_supplier_id, priority, stage, notes, created_at, updated_at)
    VALUES ({$CO}, 'HTO-{$MARK}', {$TY}, 'mob', 'fleet',
            {$LF}, {$LT}, '2091-06-01', '2091-06-05', '2091-06-06 10:00:00',
            'company', {$SUP}, 'normal', 'arrived', 'أمرُ {$MARK}', NOW(), NOW())");
$ORD = intval($db->insert_id);
check($SUP > 0 && $ORD > 0, "موردٌ #{$SUP} وأمرٌ مسلَّمٌ #{$ORD}");

$jar = $TMP . "/tt_{$MARK}.txt";
tt_login('transport_mgr', $jar);
list($c, $h, $b) = tt_req($BASE . '/Transport/transfer_tariffs.php', $jar);
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $tk);
$TOK = isset($tk[1]) ? $tk[1] : '';

head('① الشاشةُ تُفتح والفجوةُ **ظاهرةٌ لا مضمَرة**');
check($c === 200 && mb_strpos($b, 'تعرفة الترحيل') !== false, 'الشاشةُ 168 تُفتح بالدور 23 (200)');
check(mb_strpos($b, 'بلا تسعير') !== false,
      '★ ولوحُ «المسلَّمُ بلا تسعير» يعلنها — لا يدخل أيٌّ منها تسويةً');
check(mb_strpos($b, 'HTO-' . $MARK) !== false, 'والأمرُ المبذورُ ظاهرٌ في اللوح');

head('② والتسعيرُ **بلا تعرفةٍ يُرفض من الخادم**');
list($c, $h, $b2) = tt_req($BASE . '/Transport/transfer_tariffs.php', $jar, array(
    'tar_action' => 'price', 'order_id' => $ORD, 'csrf_token' => $TOK));
$msg = tt_msg($h);
check(mb_strpos($msg, '422') !== false && mb_strpos($msg, 'لا تعرفةَ مكتوبةً منطبقة') !== false,
      '★★ ' . $msg);
$q = $db->query("SELECT tariff_amount FROM transfer_orders WHERE id={$ORD}")->fetch_assoc();
check($q['tariff_amount'] === null, 'ولا رقمَ كُتب على الأمر');

head('③ وتعرفةٌ تُكتب من الشاشة ثم يقع التسعير');
list($c, $h, $b2) = tt_req($BASE . '/Transport/transfer_tariffs.php', $jar, array(
    'tar_action' => 'add', 'supplier_id' => $SUP, 'transfer_type_id' => 0,
    'from_location_id' => $LF, 'to_location_id' => $LT,
    'pricing_model' => 'per_trip', 'rate' => '1350', 'currency' => 'SDG',
    'effective_from' => '2091-01-01', 'note' => 'تعرفةُ ' . $MARK, 'csrf_token' => $TOK));
check(mb_strpos(tt_msg($h), 'أُضيفت التعرفة') !== false, 'أُضيفت التعرفةُ من الشاشة');

list($c, $h, $b2) = tt_req($BASE . '/Transport/transfer_tariffs.php', $jar, array(
    'tar_action' => 'price', 'order_id' => $ORD, 'csrf_token' => $TOK));
$msg = tt_msg($h);
check(mb_strpos($msg, 'سُعّر الأمرُ بـ1350') !== false, '★★ ' . $msg);

head('④ وتسعيرٌ ثانٍ بلا حجّةٍ **يُرفض 409**');
list($c, $h, $b2) = tt_req($BASE . '/Transport/transfer_tariffs.php', $jar, array(
    'tar_action' => 'price', 'order_id' => $ORD, 'csrf_token' => $TOK));
check(mb_strpos(tt_msg($h), '409') !== false, '★ ' . tt_msg($h));

head('⑤ والصفُّ يحمل مرجعَ تعرفته');
$q = $db->query("SELECT tariff_id, tariff_amount, tariff_currency, tariff_note, priced_at
                   FROM transfer_orders WHERE id={$ORD}")->fetch_assoc();
check((int) $q['tariff_id'] > 0 && abs((float) $q['tariff_amount'] - 1350.0) < 0.005
      && (string) $q['tariff_currency'] === 'SDG' && $q['priced_at'] !== null,
      '★★ مبلغٌ 1350 **بمرجع تعرفته #' . intval($q['tariff_id']) . '** ووقتِ تسعيره');
check(mb_strpos((string) $q['tariff_note'], 'بالنقلة') !== false,
      'والبيانُ يقول كيف حُسب: ' . (string) $q['tariff_note']);

list($c, $h, $b) = tt_req($BASE . '/Transport/transfer_tariffs.php', $jar);
check(mb_strpos($b, '1350') !== false, '★ والشاشةُ تُري المبلغَ ببيانه بعد التسعير');

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
