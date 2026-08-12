<?php
/**
 * tests/claims_screen_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP لشاشة المستخلصات (UX-08 §5.2 · الدستور §5/§6).
 * يبذر بيئةً كاملة (عميل → مشروع → عقد → تسعير → تشغيل → دوامٌ معتمد)، ثم من
 * **الشاشة الحقيقية**:
 *   ① يولّد المستخلص بزرِّه — بلا إدخالِ كميةٍ واحدة
 *   ② يفتح بنوده فيراها برابط أصلها
 *   ③ يعتمده فتتولّد فاتورةٌ ضريبيةٌ وتُفتح ذمّةُ العميل
 *   ④ يعيد التوليد للفترة نفسها فيُرفض بمرجع القائم
 *   ⑤ الصلاحية: دورٌ بلا can_add لا يولّد
 * ثم يكنس كلَّ ما بذر.
 *
 * التشغيل: php tests/claims_screen_http_proof.php   (يتطلب Apache حيًّا)
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

function cs_req($url, $jar, $post = null) {
    $GLOBALS['__ems_last_url'] = $url;   // لحلِّ Location النسبيّ عند قراءةِ الوميض
    $GLOBALS['__ems_last_jar'] = $jar;   // الجرَّةُ وسيطٌ صريحٌ — يُحفَظ للقراءةِ التالية
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
function cs_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = cs_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return cs_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}
/**
 * رسالةُ الشاشةِ لا تسكن العنوانَ دائمًا: `ems_gov_flash_redirect` يخزّنها في
 * الجلسةِ ويوجّه **بعنوانٍ مجرَّدٍ بلا `?msg=`**. فقراءةُ المُعامَلِ وحدَها تجد
 * فراغًا وتحكم على منتجٍ سليمٍ بالفشل. والمساعدُ المشترَكُ يقرأ الاثنين.
 */
function cs_msg($headers) {
    require_once __DIR__ . '/_http_flash.php';
    $dir = isset($GLOBALS['__ems_last_url'])
        ? preg_replace('~/[^/]*(\?.*)?$~', '', (string) $GLOBALS['__ems_last_url'])
        : '';
    return ems_flash_or_msg($headers, $dir, function ($u) {
        list(, , $b) = cs_req($u, $GLOBALS['__ems_last_jar'], null);
        return (string) $b;
    });
}

$env = array();
foreach (file(dirname(__DIR__) . '/.env') as $l) {
    if (preg_match('/^\s*([A-Z_]+)=(.*)$/', $l, $m)) { $env[$m[1]] = trim($m[2]); }
}
mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($db->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$db->set_charset('utf8mb4');

$CO = 4; $MARK = 'CSH' . getmypid();
$db->query("INSERT INTO clients (company_id, client_code, client_name, status, created_at)
            VALUES ({$CO}, 'TSTC-{$MARK}', 'عميلٌ اختباري {$MARK}', 'active', NOW())");
$CLIENT = $db->insert_id;
$db->query("INSERT INTO project (company_id, client_id, name) VALUES ({$CO}, {$CLIENT}, 'مشروعٌ اختباري {$MARK}')");
$PROJ = $db->insert_id;
$db->query("INSERT INTO contracts (company_id, project_id, price_currency_contract, created_at)
            VALUES ({$CO}, {$PROJ}, 'جنيه', NOW())");
$CONTRACT = $db->insert_id;
$db->query("INSERT INTO contractequipments (company_id, contract_id, equip_type, equip_unit, equip_price, equip_price_currency, created_at)
            VALUES ({$CO}, {$CONTRACT}, 'حفار-{$MARK}', 'ساعة', 100.00, 'جنيه', NOW())");
$db->query("INSERT INTO operations (company_id, equipment, equipment_type, project_id, contract_id, op_state)
            VALUES ({$CO}, 'EQ-{$MARK}', 'حفار-{$MARK}', {$PROJ}, {$CONTRACT}, 'تعمل')");
$OP = $db->insert_id;
$tsIds = array();
foreach (array(array('2027-11-02', 8.0), array('2027-11-03', 7.0)) as $d) {
    $db->query("INSERT INTO timesheet (company_id, operator, `date`, executed_hours, tons_count, meters_count, status)
                VALUES ({$CO}, {$OP}, '{$d[0]}', {$d[1]}, 0, 0, 1)");
    $tsIds[] = $db->insert_id;
}

// ── التحويلُ المالي مبذورًا: قيدُ إيرادٍ معترَفٌ به لكل يومٍ + رابطُه ────────
// مدخلُ المستخلص بعد تصحيح 2026-07-28 — «لا بندَ إلا ليومٍ حوّلته المالية».
require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
$revIds = array();
foreach (array(array($tsIds[0], '2027-11-02', 8.0), array($tsIds[1], '2027-11-03', 7.0)) as $s) {
    $r = \App\Core\EventPublisher::publish($db, array(
        'event_key' => 'revenue.unit.recognized', 'category' => 'financial',
        'source_module' => 'movement', 'company_id' => $CO,
        'entity_type' => 'timesheet', 'entity_id' => intval($s[0]),
        'occurred_at' => $s[1] . ' 00:00:00', 'created_by' => 1,
        'idempotency_key' => 'fanout:ts:' . intval($s[0]) . ':revenue',
        'legacy_event_type' => 'revenue', 'amount' => round($s[2] * 100.0, 2), 'currency' => 'SDG',
        'quantity' => $s[2], 'unit' => 'hour', 'source_ref' => 'TS-' . intval($s[0]),
        'project_id' => $PROJ, 'customer_entity_id' => $CLIENT, 'contract_id' => $CONTRACT,
        'notes' => 'بذرُ برهان شاشة المستخلصات',
        'payload' => array('unit_price' => 100.0, 'qty' => $s[2], 'unit' => 'hour'),
    ));
    $eid = intval($r['id']);
    $revIds[] = $eid;
    $db->query("INSERT INTO fin_event_links (company_id, parent_kind, parent_ref, effect_type,
                target_table, target_id, event_id)
                VALUES ({$CO}, 'timesheet', " . intval($s[0]) . ", 'revenue_event',
                        'fin_financial_events', {$eid}, {$eid})");
}

$cleanup = function () use ($db, $CLIENT, $PROJ, $CONTRACT, $OP, $tsIds, $revIds) {
    $db->query("DELETE r FROM fin_receivables r JOIN claims c ON c.invoice_no = r.doc_ref WHERE c.contract_id=" . intval($CONTRACT));
    $db->query("DELETE FROM fin_financial_events WHERE entity_type='claim' AND entity_id IN (SELECT id FROM claims WHERE contract_id=" . intval($CONTRACT) . ")");
    $db->query("DELETE FROM ems_business_events WHERE entity_type='claim' AND entity_id IN (SELECT id FROM claims WHERE contract_id=" . intval($CONTRACT) . ")");
    $db->query("DELETE FROM claim_lines WHERE claim_id IN (SELECT id FROM claims WHERE contract_id=" . intval($CONTRACT) . ")");
    $db->query("DELETE FROM claims WHERE contract_id=" . intval($CONTRACT));
    // الإسقاطُ قبل الجذر: الروابطُ ثم القيودُ ثم حقائقُها
    $db->query("DELETE FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref IN ("
        . implode(',', array_map('intval', $tsIds)) . ")");
    foreach ($revIds as $eid) { $db->query("DELETE FROM fin_financial_events WHERE id=" . intval($eid)); }
    $db->query("DELETE FROM ems_business_events WHERE entity_type='timesheet' AND entity_id IN ("
        . implode(',', array_map('intval', $tsIds)) . ")");
    $db->query("DELETE FROM timesheet WHERE id IN (" . implode(',', array_map('intval', $tsIds)) . ")");
    $db->query("DELETE FROM operations WHERE id=" . intval($OP));
    $db->query("DELETE FROM contractequipments WHERE contract_id=" . intval($CONTRACT));
    $db->query("DELETE FROM contracts WHERE id=" . intval($CONTRACT));
    $db->query("DELETE FROM project WHERE id=" . intval($PROJ));
    $db->query("DELETE FROM clients WHERE id=" . intval($CLIENT));
};
register_shutdown_function($cleanup);
fwrite(STDOUT, "  (بُذرت بيئةٌ: عقد#{$CONTRACT} · 100/ساعة · يومان معتمدان = 15 ساعة = 1,500)\n");

// ── الدخول بحساب المبيعات (المالك: دور 12) ────────────────────────────────
$jar = $TMP . '/cs_' . $MARK . '.txt';
cs_login('مبيعات', $jar);
list($code, $h, $page) = cs_req($BASE . '/Contracts/claims.php', $jar);
check($code === 200, "شاشةُ المستخلصات تُفتح بحساب المبيعات (HTTP {$code})");
check(strpos($page, 'توليد مستخلص') !== false, 'وفيها نموذجُ التوليد');
check(strpos($page, 'حوّلتها المالية') !== false, 'وسطرُ الرحلة يبدأ من الأيام التي حوّلتها المالية');
/* ◆ **رمزان لا رمز.** الشاشةُ تحقن رمزَها `clm_csrf` **والرمزَ المركزيَّ**
     `csrf_token` معًا، والحارسُ المركزيُّ (ADR-05) يقيس **المركزيَّ** حصرًا.
     فطلبٌ يحمل رمزَ الشاشةِ وحدَه يُردُّ **403** بلا وميضٍ ولا جسمٍ يُقرأ —
     فيُقرأ صمتُه «لم يُنفَّذ الفعل» والفعلُ **لم يبدأ أصلًا**. */
preg_match('~name="clm_csrf"\s+value="([^"]+)"~', $page, $tk);
$csrf = isset($tk[1]) ? $tk[1] : '';
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $page, $gk);
$gcsrf = isset($gk[1]) ? $gk[1] : '';
check($csrf !== '', 'ورمزُ الحماية مضمَّن');

// ═══ ① التوليد ════════════════════════════════════════════════════════════
head('① التوليد من الشاشة — بلا إدخالِ كمية');

list($code, $h, $b) = cs_req($BASE . '/Contracts/claims.php', $jar, array(
    'action' => 'generate', 'clm_csrf' => $csrf, 'csrf_token' => $gcsrf,
    'contract_id' => $CONTRACT, 'period_from' => '2027-11-01', 'period_to' => '2027-11-30',
));
check($code === 302, "التوليدُ نُفِّذ (HTTP {$code})");
$msg = cs_msg($h);
check(strpos($msg, 'وُلّد المستخلص') !== false, 'ورسالةُ النجاح: ' . mb_substr($msg, 0, 60));

$claim = $db->query("SELECT * FROM claims WHERE contract_id=" . intval($CONTRACT))->fetch_assoc();
check($claim !== null, 'والمستخلصُ محفوظ');
check($claim && intval($claim['client_id']) === $CLIENT, '★ بعميلِه المشتقِّ بالسلسلة');
check($claim && abs((float)$claim['gross_amount'] - 1500.00) < 0.01,
    'وإجماليه 15 ساعة × 100 = 1,500 (وُجد ' . ($claim['gross_amount'] ?? '-') . ')');
$nLines = (int) $db->query("SELECT COUNT(*) FROM claim_lines WHERE claim_id=" . intval($claim['id']))->fetch_row()[0];
check($nLines === 2, "وبندان لليومين (وُجد {$nLines})");

// ═══ ② البنود ═════════════════════════════════════════════════════════════
head('② البنود برابط أصلها');

list($code, $h, $page) = cs_req($BASE . '/Contracts/claims.php?open=' . intval($claim['id']), $jar);
check($code === 200 && strpos($page, 'بنود المستخلص') !== false, 'صفحةُ البنود تُفتح');
check(strpos($page, 'دوام #' . $tsIds[0]) !== false, '★ وكلُّ بندٍ يعرض رقمَ صفّ الدوام مصدرَه');

// ═══ ③ يدان: المبيعاتُ ترفع والمالية تُجيز ═════════════════════════════════
head('③ الرفعُ من المبيعات ثم الإجازةُ من المالية → فاتورةٌ + ذمّة');

// المبيعاتُ لا تُجيز ما أنشأت (can_edit سُحبت من الدور 12)
list($code, $h, $b) = cs_req($BASE . '/Contracts/claims.php', $jar, array(
    'action' => 'approve', 'clm_csrf' => $csrf, 'csrf_token' => $gcsrf, 'id' => intval($claim['id']), 'tax_code' => 'VAT15',
));
check(strpos(cs_msg($h), 'من أنشأه') !== false,
    '★ المبيعاتُ مُنعت من الإجازة: ' . mb_substr(cs_msg($h), 0, 60));

list($code, $h, $b) = cs_req($BASE . '/Contracts/claims.php', $jar, array(
    'action' => 'submit', 'clm_csrf' => $csrf, 'csrf_token' => $gcsrf, 'id' => intval($claim['id']),
));
check(strpos(cs_msg($h), 'بانتظار إجازة المالية') !== false, 'ورفعتها للمالية: ' . mb_substr(cs_msg($h), 0, 60));

// يدُ المالية: مديرُ الإدارة المالية (19) هو صاحبُ الإجازة في المنح
$fjar = $TMP . '/csf_' . $MARK . '.txt';
cs_login('fin.deptmgr@equipation.sd', $fjar);
list($code, $h, $fpage) = cs_req($BASE . '/Contracts/claims.php', $fjar);
check($code === 200, 'ومديرُ الإدارة المالية يفتح الشاشة (HTTP ' . $code . ')');
preg_match('~name="clm_csrf"\s+value="([^"]+)"~', $fpage, $ftk);
$fcsrf = isset($ftk[1]) ? $ftk[1] : '';
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $fpage, $fgk);
$fgcsrf = isset($fgk[1]) ? $fgk[1] : '';
$evBefore = (int) $db->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0];
list($code, $h, $b) = cs_req($BASE . '/Contracts/claims.php', $fjar, array(
    'action' => 'approve', 'clm_csrf' => $fcsrf, 'csrf_token' => $fgcsrf, 'id' => intval($claim['id']), 'tax_code' => 'VAT15',
));
check($code === 302, "الإجازةُ نُفِّذت (HTTP {$code})");
$msg = cs_msg($h);
check(strpos($msg, 'فاتورة') !== false && strpos($msg, 'ذمّة') !== false,
    'والرسالةُ تذكر الفاتورةَ والذمّة');

$claim = $db->query("SELECT * FROM claims WHERE id=" . intval($claim['id']))->fetch_assoc();
check((string)$claim['state'] === 'invoiced', 'وحالتُه «مفوتر»');
check(!empty($claim['invoice_no']), 'وله رقمُ فاتورة (' . $claim['invoice_no'] . ')');
check(abs((float)$claim['tax_amount'] - 225.00) < 0.01, 'وضريبةُ 15٪ من 1,500 = 225');

// ★★ لا قيدَ إيرادٍ ثانيًا: الاعترافُ في المروحة والفوترةُ هنا
check((int) $db->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0] === $evBefore,
    '★★ والدفترُ لم يزدد صفًّا — لا إيرادَ مزدوجًا عن الساعات نفسِها');
$fact = $db->query("SELECT event_key FROM ems_business_events
                     WHERE entity_type='claim' AND entity_id=" . intval($claim['id']))->fetch_assoc();
check($fact && (string)$fact['event_key'] === 'billing.claim.invoiced',
    'وحقيقةُ الفوترة في الجذر وحده (' . ($fact ? $fact['event_key'] : '—') . ')');

$rc = $db->query("SELECT * FROM fin_receivables WHERE doc_ref='" . $db->real_escape_string($claim['invoice_no']) . "'")->fetch_assoc();
check($rc !== null, '★ وذمّةٌ مدينةٌ مفتوحة');
check($rc && abs((float)$rc['amount'] - 1725.00) < 0.01, 'بقيمة 1,500 + 225 ضريبة');
check($rc && intval($rc['customer_entity_id']) === $CLIENT, 'باسم العميل المشتقّ');

// ═══ ④ لا تكرار ═══════════════════════════════════════════════════════════
head('④ إعادةُ التوليد للفترة نفسها');

list($code, $h, $b) = cs_req($BASE . '/Contracts/claims.php', $jar, array(
    'action' => 'generate', 'clm_csrf' => $csrf, 'csrf_token' => $gcsrf,
    'contract_id' => $CONTRACT, 'period_from' => '2027-11-01', 'period_to' => '2027-11-30',
));
$msg = cs_msg($h);
check(strpos($msg, 'قائم') !== false, 'تُرفض بمرجع المستخلص القائم');
check((int) $db->query("SELECT COUNT(*) FROM claims WHERE contract_id=" . intval($CONTRACT))->fetch_row()[0] === 1,
    'ومستخلصٌ واحدٌ فقط في القاعدة');

// ═══ ⑤ الصلاحية ═══════════════════════════════════════════════════════════
head('⑤ الصلاحية: العرضُ لا يعني التوليد');

$jar2 = $TMP . '/cs2_' . $MARK . '.txt';
cs_login('مديرمالي', $jar2);
list($code, $h, $page2) = cs_req($BASE . '/Contracts/claims.php', $jar2);
check($code === 200, 'المديرُ الماليُّ يفتح الشاشة عرضًا');
check(strpos($page2, 'توليد مستخلص الفترة') === false, '★ ولا يرى نموذجَ التوليد (بلا can_add)');
list($code, $h, $b) = cs_req($BASE . '/Contracts/claims.php', $jar2, array(
    'action' => 'generate', 'clm_csrf' => $csrf, 'csrf_token' => $gcsrf,
    'contract_id' => $CONTRACT, 'period_from' => '2027-12-01', 'period_to' => '2027-12-31',
));
check(strpos(cs_msg($h), 'صلاحية') !== false, 'ومحاولةُ التوليد تُرفض بالصلاحية');

$cleanup();
check((int) $db->query("SELECT COUNT(*) FROM claims WHERE contract_id=" . intval($CONTRACT))->fetch_row()[0] === 0,
    'كُنس ما بُذر — لا أثرَ للاختبار');

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
