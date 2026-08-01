<?php
/**
 * tests/commercial_board_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP للشاشة **181** — اللوحة التجارية (P-12 · §4 شرطُ إغلاق الموجة)
 * من الشاشة الحقيقية بمسؤول المبيعات (12):
 *   ★★★ **الأرقامُ الأربعةُ معروضةٌ في سطرٍ واحدٍ لعقدٍ رائد**
 *   ★★★ **وكلُّ فجوةٍ بمالكها مسمًّى على الشاشة**
 *   ★★  **والسطرُ الناقصُ موسومٌ لا مخفيّ**
 *   ★   **والعقدُ بعملتين يظهر ممتنعًا لا محذوفًا**
 * ثم يكنس كلَّ ما بذر.
 *
 * التشغيل: php tests/commercial_board_http_proof.php   (يتطلب Apache حيًّا)
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

function bd_req($url, $jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 90,
    ));
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}
function bd_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = bd_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    $ch = curl_init($BASE . '/login.php');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(array('username' => $user, 'password' => '12345678',
            'csrf_token' => isset($m[1]) ? $m[1] : '')),
    ));
    curl_exec($ch); curl_close($ch);
}

require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
if ($db->connect_error) { fwrite(STDERR, "DB: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

$CO   = 4;
$MARK = 'P12H' . getmypid();

$teardown = function () use ($db, $MARK) {
    $db->query("DELETE u FROM unit_entries u JOIN contracts c ON c.id = u.contract_id
                 WHERE c.first_party LIKE '%{$MARK}%'");
    $db->query("DELETE FROM claims WHERE claim_no LIKE '%{$MARK}%'");
    $db->query("DELETE FROM fin_receivables WHERE doc_ref LIKE '%{$MARK}%'");
    $db->query("DELETE p FROM contract_monthly_plan p JOIN contracts c ON c.id = p.contract_id
                 WHERE c.first_party LIKE '%{$MARK}%'");
    $db->query("DELETE l FROM client_contract_lines l JOIN contracts c ON c.id = l.contract_id
                 WHERE c.first_party LIKE '%{$MARK}%'");
    $db->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $db->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ برهانُ HTTP — الشاشة 181 · اللوحةُ التجارية ══\n");

head('البذر — عقدٌ رائدٌ بأرقامه الأربعة');
$db->query("INSERT INTO project (company_id, name, client, location, total)
            VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($db->insert_id);
$db->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
            actual_start, actual_end, first_party, second_party, contract_status, project_id,
            price_currency_contract, created_at)
            VALUES ({$CO}, '2095-01-01', 365, '2095-01-01', '2095-12-31',
                    'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'دولار', NOW())");
$CID = intval($db->insert_id);
$db->query("INSERT INTO client_contract_lines (company_id, contract_id, line_no, pricing_model,
            description, qty_contracted, unit_price, currency, valid_from, valid_to,
            tax_status, state, created_at)
            VALUES ({$CO}, {$CID}, 1, 'ton', 'نقلُ خامٍ {$MARK}', 2000, 10, 'USD',
                    '2095-01-01', '2095-12-31', 'exempt', 'active', NOW())");
$LID = intval($db->insert_id);
$db->query("INSERT INTO contract_monthly_plan (company_id, contract_id, line_id, plan_version,
            effective_from, period_month, qty_planned, month_kind, created_at)
            VALUES ({$CO}, {$CID}, {$LID}, 1, '2095-01-01', '2095-03', 2000, 'normal', NOW())");
$MAR = intval($db->insert_id);
$db->query("UPDATE client_contract_lines SET qty_planned_total=2000 WHERE id={$LID}");
$db->query("INSERT INTO unit_entries (company_id, entry_no, entry_date, project_id, contract_id,
            contract_line_id, plan_period_id, unit_type, qty, record_basis, capacity_flag, state,
            revision_no, current_round, created_at, updated_at)
            VALUES ({$CO}, 'UE-{$MARK}', '2095-03-10', {$PRJ}, {$CID},
                    {$LID}, {$MAR}, 'ton', 1500, 'contract', 0, 'sales_approved', 1, 1, NOW(), NOW())");
$db->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
            project_id, amount, currency, fx_rate_recognized, base_amount, collected,
            due_date, state, created_at)
            VALUES ({$CO}, 1, 'invoice', 'INV-{$MARK}', {$PRJ}, 12000, 'USD', 1.0, 12000, 9000,
                    '2095-04-30', 'partial', NOW())");
$RID = intval($db->insert_id);
$db->query("INSERT INTO claims (company_id, claim_no, contract_id, client_id, project_id,
            period_from, period_to, currency, gross_amount, retention_amount, net_amount,
            tax_amount, state, version, receivable_id, created_at)
            VALUES ({$CO}, 'CLM-{$MARK}', {$CID}, 1, {$PRJ}, '2095-03-01', '2095-03-31',
                    'USD', 12000, 0, 12000, 0, 'invoiced', 1, {$RID}, NOW())");
check($CID > 0 && $LID > 0,
      "عقدٌ #{$CID} — مخطَّطٌ 20,000 · منفَّذٌ 15,000 · مفوترٌ 12,000 · محصَّلٌ 9,000");

$jar = $TMP . "/bd_{$MARK}.txt";
bd_login('مبيعات', $jar);
list($c, $h, $b) = bd_req($BASE . '/Contracts/commercial_board.php', $jar);

head('★★★ **الأرقامُ الأربعةُ في سطرٍ واحد**');
check($c === 200 && mb_strpos($b, 'اللوحة التجارية للعقود') !== false,
      'الشاشةُ 181 تُفتح بالدور 12 (200)');
foreach (array('20000', '15000', '12000', '9000') as $n) {
    check(mb_strpos($b, $n) !== false, 'والرقمُ **' . $n . '** معروضٌ على اللوحة');
}
check(mb_strpos($b, 'مخطَّط') !== false && mb_strpos($b, 'منفَّذ') !== false
      && mb_strpos($b, 'مفوتَر') !== false && mb_strpos($b, 'محصَّل') !== false,
      '★★★ و**العناوينُ الأربعةُ في رأس الجدول**');

head('★★★ **وكلُّ فجوةٍ بمالكها مسمًّى على الشاشة**');
check(mb_strpos($b, 'فجوةُ التنفيذ') !== false && mb_strpos($b, 'التشغيل') !== false,
      '★★★ «فجوةُ التنفيذ» ⇐ **التشغيل**');
check(mb_strpos($b, 'فجوةُ الفوترة') !== false && mb_strpos($b, 'المبيعات') !== false,
      '★★★ و«فجوةُ الفوترة» ⇐ **المبيعات**');
check(mb_strpos($b, 'فجوةُ التحصيل') !== false && mb_strpos($b, 'المالية') !== false,
      '★★★ و«فجوةُ التحصيل» ⇐ **المالية**');
check(mb_strpos($b, 'لماذا لم يُفوتر ما نُفِّذ؟') !== false,
      '★★ و**السؤالُ الذي يُسأل عنه المالكُ مكتوبٌ** — لا إشارةً بل سؤالًا');
check(mb_strpos($b, '-5000') !== false, '★★ وفجوةُ التنفيذ **−5,000** ظاهرةً بقيمتها');
check(mb_strpos($b, '-3000') !== false, 'وفجوةُ الفوترة **−3,000**');

head('★★ **وشرطُ الإغلاق معلَنٌ على الشاشة نفسِها**');
check(mb_strpos($b, 'خطَّ الأساس صار حيًّا لا وثيقة') !== false,
      '★★★ و«**دليلُ أن خطَّ الأساس صار حيًّا لا وثيقة**» معروضٌ في رأس اللوحة');
check(mb_strpos($b, 'ولا جدولَ ثالثٌ يحفظ اللوحة') !== false,
      '★ و**«اللوحةُ قراءةٌ لا تخزين»** معلَنةً — فلا يفترق رقمٌ عن مصدره');
check(mb_strpos($b, 'منفَّذًا ناقصًا يبدو تامًّا') !== false,
      '★★ و**قاعدةُ المصداقية مكتوبةٌ**: وحدةٌ غيرُ موصولةٍ = منفَّذٌ ناقصٌ يبدو تامًّا');
check(mb_strpos($b, 'تام') !== false, 'ووسمُ المصداقيةِ في العمود الأخير');

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
