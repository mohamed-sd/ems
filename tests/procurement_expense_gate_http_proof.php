<?php
/**
 * tests/procurement_expense_gate_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP للطريق كاملًا: **تسجيلُ الاستلام من الشاشة الحقيقية يقدّم حالةَ
 * الأمر ويُوصل تكلفته إلى الدفتر** — ولا يفعل ذلك قبل وصول البضاعة.
 * (UX-09 §2 · §5.1-③ · §8.2 · FES §7)
 *
 * يبذر أمرَه ببنده، ثم يسجّل استلامًا **جزئيًّا** فيتأكد ألا أثرَ مالي، ثم
 * يكمّله فيتأكد أن الأثر نُشر مرةً واحدةً بأبعاده — ثم يكنس كلَّ ما بذر.
 * لا يمسّ أمرًا حقيقيًّا ولا يترك حركةً ماليةً.
 *
 * التشغيل: php tests/procurement_expense_gate_http_proof.php  (يتطلب Apache حيًّا)
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

function pg_req($url, $jar, $post = null) {
    /* الرمزُ المركزيُّ: الحارسُ يقبله في الحقولِ أو الترويسة، والمسبارُ
       يبني الحقولَ يدويًّا فلا يحمله ⇒ 403 صامتٌ يُقرأ فشلًا في المنتج. */
    if ($post !== null) {
        require_once __DIR__ . '/_http_flash.php';
        $post = ems_http_with_csrf($post, $url, $jar);
    }
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

$MARK = 'PHTTP' . getmypid();
$CODE = 'TST-' . $MARK;
$CO = 4;
$AMOUNT = 8400.00;

$db->query("INSERT INTO proc_order (company_id, code, supplier_id, project_id, currency, fx_rate,
                total_amount, base_amount, state, created_by, created_at)
            VALUES ({$CO}, '{$CODE}', 3, 2, 'SDG', 1, {$AMOUNT}, {$AMOUNT}, 'مؤكَّد', 1, NOW())");
$OID = $db->insert_id;
$db->query("INSERT INTO proc_order_line (company_id, order_id, item_name, qty, unit_price, subtotal, created_at)
            VALUES ({$CO}, {$OID}, 'فلتر زيت', 20, 420, {$AMOUNT}, NOW())");

$cleanup = function () use ($db, $OID, $MARK) {
    $db->query("DELETE rl FROM proc_receipt_line rl JOIN proc_receipt_custody rc ON rc.id=rl.custody_id WHERE rc.order_id=" . intval($OID));
    $db->query("DELETE FROM proc_receipt_custody WHERE order_id=" . intval($OID));
    $db->query("DELETE FROM fin_financial_events WHERE entity_type='proc_order' AND entity_id=" . intval($OID));
    $db->query("DELETE FROM ems_business_events WHERE entity_type='proc_order' AND entity_id=" . intval($OID));
    $db->query("DELETE FROM proc_order_line WHERE order_id=" . intval($OID));
    $db->query("DELETE FROM proc_order WHERE id=" . intval($OID));
};
register_shutdown_function($cleanup);

if ($OID <= 0) { fwrite(STDERR, "FATAL: seed failed\n"); exit(1); }
fwrite(STDOUT, "  (بُذر أمرٌ اختباري #{$OID} · {$CODE} · 20 قطعة بـ {$AMOUNT} · حالتُه «مؤكَّد»)\n");

// الدخول بحساب المشتريات
$jar = $TMP . '/pg_' . $MARK . '.txt';
@unlink($jar);
list($c, $h, $b) = pg_req($BASE . '/login.php', $jar);
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
pg_req($BASE . '/login.php', $jar, array('username' => 'مشتريات', 'password' => '12345678',
    'csrf_token' => isset($m[1]) ? $m[1] : ''));

/* §15.6: الوجهةُ المخزنيةُ **بلا مخزنِ إدخالٍ** رصيدٌ لا يتحرك — فالشاشةُ
   ترفضها برسالةٍ صريحة («حدد مخزن الإدخال»)، وكان البذرُ يُعلن الوجهةَ مخزنًا
   ولا يسمّي المخزن. والمخزنُ **يُقاس** من `proc_warehouse` لا يُفترض رقمًا. */
$wh = $db->query("SELECT id FROM proc_warehouse WHERE company_id={$CO} AND type='مخزن'
                   AND COALESCE(is_deleted,0)=0 AND status=1 ORDER BY id LIMIT 1")->fetch_assoc();
$WH = $wh ? intval($wh['id']) : 0;
check($WH > 0, 'ومخزنُ إدخالٍ قائمٌ لِتُقبَل الوجهةُ المخزنية (#' . $WH . ')');
list($code, $h, $page) = pg_req($BASE . '/Procurement/receipt_custody_proc.php', $jar);
check($code === 200, "شاشةُ الاستلام تُفتح بحساب المشتريات (HTTP {$code})");
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $page, $tk);
$token = isset($tk[1]) ? $tk[1] : '';

$evCount = function () use ($db, $OID) {
    return (int) $db->query("SELECT COUNT(*) FROM fin_financial_events WHERE entity_type='proc_order' AND entity_id=" . intval($OID))->fetch_row()[0];
};
$orderState = function () use ($db, $OID) {
    $r = $db->query("SELECT state, received_pct, event_id FROM proc_order WHERE id=" . intval($OID))->fetch_assoc();
    return $r ? $r : array('state' => null, 'received_pct' => null, 'event_id' => null);
};

check($evCount() === 0, 'قبل أي استلام: لا أثرَ ماليًّا للأمر');

// ═══ ① استلامٌ جزئي (8 من 20) ═════════════════════════════════════════════
head('① استلامٌ جزئي — لا مصروف');

list($code, $h, $b) = pg_req($BASE . '/Procurement/receipt_custody_proc.php', $jar, array(
    'action' => 'save', 'csrf_token' => $token,
    'holder_name' => 'أمين المستودع', 'receipt_date' => date('Y-m-d'),
    'supplier_id' => 3, 'order_id' => $OID,
    'receipt_location' => 'المستودع', 'expected_destination' => 'مخزن', 'warehouse_id' => $WH, 'state' => 'مستلَمة',
    'line_item_name' => array('فلتر زيت'), 'line_qty' => array(8), 'line_item_id' => array(''),
));
check($code === 302, "الاستلامُ الجزئي حُفظ (HTTP {$code})");
$st = $orderState();
check(abs((float)$st['received_pct'] - 40.0) < 0.01, "نسبةُ الاستلام 8 من 20 = 40٪ (وُجد {$st['received_pct']})");
check($st['state'] === 'استلام أولي', "الحالةُ صارت «استلام أولي» (وُجد «{$st['state']}»)");
check($evCount() === 0, '★ ولا أثرَ ماليًّا — البضاعةُ لم تكتمل');

// ═══ ② إكمالُ الاستلام (20 من 20) ════════════════════════════════════════
head('② اكتمالُ الاستلام — المصروف يُنشر');

$rcId = (int) $db->query("SELECT id FROM proc_receipt_custody WHERE order_id=" . intval($OID) . " ORDER BY id DESC LIMIT 1")->fetch_row()[0];
list($code, $h, $b) = pg_req($BASE . '/Procurement/receipt_custody_proc.php', $jar, array(
    'action' => 'save', 'id' => $rcId, 'csrf_token' => $token,
    'holder_name' => 'أمين المستودع', 'receipt_date' => date('Y-m-d'),
    'supplier_id' => 3, 'order_id' => $OID,
    'receipt_location' => 'المستودع', 'expected_destination' => 'مخزن', 'warehouse_id' => $WH, 'state' => 'مستلَمة',
    'line_item_name' => array('فلتر زيت'), 'line_qty' => array(20), 'line_item_id' => array(''),
));
check($code === 302, "تعديلُ الاستلام إلى الكمية الكاملة حُفظ (HTTP {$code})");
$st = $orderState();
check(abs((float)$st['received_pct'] - 100.0) < 0.01, "نسبةُ الاستلام صارت 100٪ (وُجد {$st['received_pct']})");
check($st['state'] === 'استلام نهائي', "الحالةُ تقدّمت إلى «استلام نهائي» (وُجد «{$st['state']}»)");
check($evCount() === 1, '★ التكلفةُ بلغت الدفترَ لحظةَ اكتمال الاستلام — بلا زرِّ سحب');
check(!empty($st['event_id']), 'ومرجعُ الحدث كُتب على الأمر');

$ev = $db->query("SELECT * FROM fin_financial_events WHERE entity_type='proc_order' AND entity_id=" . intval($OID))->fetch_assoc();
if ($ev) {
    check(abs((float)$ev['amount'] - $AMOUNT) < 0.01, "المبلغُ {$AMOUNT} بحرفه");
    check(intval($ev['project_id']) === 2, 'والمشروعُ وصل معه (البُعد المضاف)');
    check((string)$ev['source_ref'] === $CODE, 'والمرجعُ كودُ الأمر');
}

// ═══ ③ لا ازدواج ══════════════════════════════════════════════════════════
head('③ الحفظُ المتكرر لا يضاعف');

pg_req($BASE . '/Procurement/receipt_custody_proc.php', $jar, array(
    'action' => 'save', 'id' => $rcId, 'csrf_token' => $token,
    'holder_name' => 'أمين المستودع', 'receipt_date' => date('Y-m-d'),
    'supplier_id' => 3, 'order_id' => $OID,
    'receipt_location' => 'المستودع', 'expected_destination' => 'مخزن', 'warehouse_id' => $WH, 'state' => 'مستلَمة',
    'line_item_name' => array('فلتر زيت'), 'line_qty' => array(20), 'line_item_id' => array(''),
));
check($evCount() === 1, 'حفظٌ ثالثٌ للاستلام → يبقى صفٌّ واحدٌ في الدفتر');

list($code3, $h3, $imp) = pg_req($BASE . '/Finance/import_events_fin.php', $jar);
check($code3 !== 200 || strpos($imp, $CODE) === false,
    'وزرُّ الاستيراد لم يعد يرى الأمر مرشَّحًا');

$cleanup();
check((int) $db->query("SELECT COUNT(*) FROM proc_order WHERE id=" . intval($OID))->fetch_row()[0] === 0,
    'كُنس ما بُذر — لا أثرَ للاختبار في البيانات');

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
