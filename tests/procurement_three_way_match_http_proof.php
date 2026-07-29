<?php
/**
 * tests/procurement_three_way_match_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP للمطابقة الثلاثية من شاشة أمر الشراء الحقيقية (UX-09 §8.2).
 * يبذر أمرًا باستلامٍ كامل، ثم:
 *   ① يُدخل فاتورةً بفرقٍ فوق السماح → تقف بلا دَين ورسالةٌ تشرح الفرق
 *   ② يُدخل الفاتورة الصحيحة → تُطابَق ويُفتح دَينُ المورد باسمه في سجله
 *   ③ يعيد الإدخال → لا ذمّةَ ثانية
 * ثم يكنس كلَّ ما بذر — لا يمسّ أمرًا حقيقيًّا ولا يترك ذمّةً.
 *
 * التشغيل: php tests/procurement_three_way_match_http_proof.php (يتطلب Apache)
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

function tw_req($url, $jar, $post = null) {
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

$env = array();
foreach (file(dirname(__DIR__) . '/.env') as $l) {
    if (preg_match('/^\s*([A-Z_]+)=(.*)$/', $l, $m)) { $env[$m[1]] = trim($m[2]); }
}
mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($db->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$db->set_charset('utf8mb4');

$MARK = 'TW' . getmypid();
$CODE = 'TST-' . $MARK;
$CO = 4; $SUP = 3; $AMOUNT = 4000.00;

$db->query("INSERT INTO proc_order (company_id, code, supplier_id, project_id, currency, fx_rate,
                total_amount, base_amount, state, received_pct, created_by, created_at)
            VALUES ({$CO}, '{$CODE}', {$SUP}, 2, 'SDG', 1, {$AMOUNT}, {$AMOUNT}, 'استلام نهائي', 100, 1, NOW())");
$OID = $db->insert_id;
$db->query("INSERT INTO proc_order_line (company_id, order_id, item_name, qty, unit_price, subtotal, created_at)
            VALUES ({$CO}, {$OID}, 'فلتر هواء', 10, 400, {$AMOUNT}, NOW())");
$db->query("INSERT INTO proc_receipt_custody (company_id, code, holder_name, receipt_date, supplier_id,
            order_id, state, created_by, created_at)
            VALUES ({$CO}, 'TST-RC-{$MARK}', 'أمين المستودع', CURDATE(), {$SUP}, {$OID}, 'مستلَمة', 1, NOW())");
$RC = $db->insert_id;
$db->query("INSERT INTO proc_receipt_line (company_id, custody_id, item_name, qty, created_at)
            VALUES ({$CO}, {$RC}, 'فلتر هواء', 10, NOW())");

$cleanup = function () use ($db, $OID) {
    $db->query("DELETE FROM fin_dues WHERE period_ref='PO-" . intval($OID) . "'");
    $db->query("DELETE FROM fin_financial_events WHERE entity_type IN ('proc_order','proc_order_invoice') AND entity_id=" . intval($OID));
    $db->query("DELETE FROM ems_business_events WHERE entity_type IN ('proc_order','proc_order_invoice') AND entity_id=" . intval($OID));
    $db->query("DELETE rl FROM proc_receipt_line rl JOIN proc_receipt_custody rc ON rc.id=rl.custody_id WHERE rc.order_id=" . intval($OID));
    $db->query("DELETE FROM proc_receipt_custody WHERE order_id=" . intval($OID));
    $db->query("DELETE FROM proc_order_line WHERE order_id=" . intval($OID));
    $db->query("DELETE FROM proc_order WHERE id=" . intval($OID));
};
register_shutdown_function($cleanup);
if ($OID <= 0) { fwrite(STDERR, "FATAL: seed failed\n"); exit(1); }
fwrite(STDOUT, "  (بُذر أمرٌ #{$OID} · {$CODE} · 10 قطع بـ 4000 · مستلَمٌ بالكامل · السماح 80)\n");

$jar = $TMP . '/tw_' . $MARK . '.txt';
@unlink($jar);
list($c, $h, $b) = tw_req($BASE . '/login.php', $jar);
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
tw_req($BASE . '/login.php', $jar, array('username' => 'مشتريات', 'password' => '12345678',
    'csrf_token' => isset($m[1]) ? $m[1] : ''));

list($code, $h, $page) = tw_req($BASE . '/Procurement/orders_proc.php?edit_id=' . $OID, $jar);
check($code === 200, "شاشةُ أمر الشراء تُفتح (HTTP {$code})");
check(strpos($page, 'المطابقة الثلاثية') !== false, 'وقسمُ الفاتورة والمطابقة ظاهرٌ فيها');
check(strpos($page, 'لم تُطابَق') !== false, 'وحالتُها الابتدائية «لم تُطابَق»');
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $page, $tk);
$token = isset($tk[1]) ? $tk[1] : '';

$dues = function () use ($db, $OID) {
    return (int) $db->query("SELECT COUNT(*) FROM fin_dues WHERE period_ref='PO-" . intval($OID) . "'")->fetch_row()[0];
};
check($dues() === 0, 'ولا ذمّةَ بعد');

// ═══ ① فرقٌ فوق السماح ═════════════════════════════════════════════════════
head('① فاتورةٌ بفرقٍ فوق السماح');

list($code, $h, $b) = tw_req($BASE . '/Procurement/orders_proc.php', $jar, array(
    'action' => 'match_invoice', 'id' => $OID, 'csrf_token' => $token,
    'invoice_no' => 'INV-' . $MARK . '-BAD', 'invoice_date' => date('Y-m-d'),
    'invoice_amount' => 4600.00,
));
check($code === 302, "المطابقةُ نُفِّذت (HTTP {$code})");
$po = $db->query("SELECT match_state, invoice_amount FROM proc_order WHERE id=" . intval($OID))->fetch_assoc();
check($po && $po['match_state'] === 'var_pending', 'حالةُ المطابقة «فرقٌ ينتظر قرارًا»');
check($dues() === 0, '★ ولا دَينَ — 600 فوق سماح 80');
preg_match('~Location:\s*(\S+)~i', $h, $loc);
check(isset($loc[1]) && strpos(urldecode($loc[1]), 'فرقٌ فوق السماح') !== false,
    'والرسالةُ تشرح للمستخدم سببَ الوقف');

// ═══ ② الفاتورة الصحيحة ═══════════════════════════════════════════════════
head('② الفاتورة الصحيحة → دَينُ المورد');

list($code, $h, $b) = tw_req($BASE . '/Procurement/orders_proc.php', $jar, array(
    'action' => 'match_invoice', 'id' => $OID, 'csrf_token' => $token,
    'invoice_no' => 'INV-' . $MARK . '-OK', 'invoice_date' => date('Y-m-d'),
    'invoice_amount' => 4000.00,
));
check($code === 302, "المطابقةُ الثانية نُفِّذت (HTTP {$code})");
$po = $db->query("SELECT match_state, invoice_no FROM proc_order WHERE id=" . intval($OID))->fetch_assoc();
check($po && $po['match_state'] === 'matched', 'حالةُ الأمر صارت «مطابَقة»');
check($dues() === 1, '★ وفُتح دَينُ المورد');

$due = $db->query("SELECT * FROM fin_dues WHERE period_ref='PO-" . intval($OID) . "'")->fetch_assoc();
if ($due) {
    check((string)$due['party_type'] === 'proc_supplier', '★ باسم مورد المشتريات في سجله (لا يُخلط بمورد الآليات)');
    check(intval($due['party_ref']) === $SUP, 'والمرجعُ المورد نفسُه');
    check(abs((float)$due['amount'] - 4000.00) < 0.01, 'بقيمة الفاتورة 4000');
    check((string)$due['direction'] === 'credit' && (string)$due['settlement_state'] === 'pending',
        'دائنٌ بانتظار السداد');
}
$ev = $db->query("SELECT COUNT(*) FROM fin_financial_events WHERE entity_type='proc_order_invoice' AND entity_id=" . intval($OID))->fetch_row()[0];
check((int)$ev === 1, 'وحدثُ الاستحقاق في الدفتر');

list($code, $h, $page2) = tw_req($BASE . '/Procurement/orders_proc.php?edit_id=' . $OID, $jar);
check(strpos($page2, 'مطابَقة') !== false, 'والشاشةُ تعرض الحالة «مطابَقة»');

// ═══ ③ لا ازدواج ══════════════════════════════════════════════════════════
head('③ إعادةُ الإدخال لا تُقيّد مرتين');

tw_req($BASE . '/Procurement/orders_proc.php', $jar, array(
    'action' => 'match_invoice', 'id' => $OID, 'csrf_token' => $token,
    'invoice_no' => 'INV-' . $MARK . '-OK', 'invoice_date' => date('Y-m-d'),
    'invoice_amount' => 4000.00,
));
check($dues() === 1, 'ذمّةٌ واحدةٌ بعد مطابقتين');
$ev2 = $db->query("SELECT COUNT(*) FROM fin_financial_events WHERE entity_type='proc_order_invoice' AND entity_id=" . intval($OID))->fetch_row()[0];
check((int)$ev2 === 1, 'وحدثٌ واحدٌ في الدفتر');

$cleanup();
check((int) $db->query("SELECT COUNT(*) FROM proc_order WHERE id=" . intval($OID))->fetch_row()[0] === 0,
    'كُنس ما بُذر — لا أثرَ للاختبار');

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
