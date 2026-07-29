<?php
/**
 * tests/maintenance_cost_publish_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP للطريق كاملًا: **إقفالُ أمر صيانةٍ من الشاشة الحقيقية يُوصل تكلفته
 * إلى الدفتر المالي** (UX-04 §8.2 · FES §7 و§8) — لا بزرِّ سحبٍ يضغطه أحد.
 *
 * يبذر أمرَه الخاص في نافذةٍ معزولة (كودٌ موسومٌ برقم العملية)، يُقفله بالشاشة
 * كما يفعل مدير الصيانة تمامًا، يتتبّع الأثر حتى القيد، ثم **يكنس ما بذره**
 * — فلا يمسّ أمرًا حقيقيًّا ولا يترك حركةً ماليةً لم يقرّرها أحد.
 *
 * التشغيل: php tests/maintenance_cost_publish_http_proof.php   (يتطلب Apache حيًّا)
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

function mc_req($url, $jar, $post = null) {
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

// ── بذرُ أمرٍ جاهزٍ للإقفال (بشروط الإقفال الثلاثة مملوءةً سلفًا) ──────────
$MARK = 'HTTPCOST' . getmypid();
$CODE = 'TST-' . $MARK;
$COST = 246.75;
$CO = 4;

$rc = $db->query("SELECT id FROM mnt_lookup LIMIT 1");
$rootCause = ($rc && ($r = $rc->fetch_row())) ? intval($r[0]) : null;

$st = $db->prepare(
    "INSERT INTO mnt_order (company_id, code, equipment_id, project_id, maint_type, priority,
         state, external_cost, total_cost, actions_taken, root_cause_id, inspection_result,
         created_by, created_at)
     VALUES (?, ?, 9, 4, 'علاجية', 'عادية', 'تنفيذ', ?, ?, 'إصلاحٌ اختباري', ?, 'ناجح', 1, NOW())");
$st->bind_param('isddi', $CO, $CODE, $COST, $COST, $rootCause);
$st->execute();
$OID = $db->insert_id;
$st->close();

$cleanup = function () use ($db, $OID) {
    $db->query("DELETE FROM fin_financial_events WHERE entity_type='mnt_order' AND entity_id=" . intval($OID));
    $db->query("DELETE FROM ems_business_events WHERE entity_type='mnt_order' AND entity_id=" . intval($OID));
    $db->query("DELETE FROM mnt_order WHERE id=" . intval($OID));
};
register_shutdown_function($cleanup);

if ($OID <= 0) { fwrite(STDERR, "FATAL: seed failed\n"); exit(1); }
fwrite(STDOUT, "  (بُذر أمرٌ اختباري #{$OID} بكود {$CODE} وتكلفة {$COST})\n\n");

// ── الدخول بحساب الصيانة وإقفالُ الأمر من الشاشة ──────────────────────────
$jar = $TMP . '/mc_' . $MARK . '.txt';
@unlink($jar);
list($c, $h, $b) = mc_req($BASE . '/login.php', $jar);
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
mc_req($BASE . '/login.php', $jar, array('username' => 'صيانة', 'password' => '12345678',
    'csrf_token' => isset($m[1]) ? $m[1] : ''));

list($code, $h, $page) = mc_req($BASE . '/Maintenance/orders.php?id=' . $OID, $jar);
check($code === 200, "شاشةُ الأمر تُفتح بحساب الصيانة (HTTP {$code})");
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $page, $tk);

$ledgerBefore = (int) $db->query("SELECT COUNT(*) FROM fin_financial_events WHERE entity_type='mnt_order' AND entity_id=" . $OID)->fetch_row()[0];
check($ledgerBefore === 0, 'قبل الإقفال: لا أثرَ ماليًّا للأمر');

list($code, $h, $b) = mc_req($BASE . '/Maintenance/orders.php', $jar, array(
    'action' => 'save_order', 'id' => $OID, 'csrf_token' => isset($tk[1]) ? $tk[1] : '',
    'equipment_id' => 9, 'project_id' => 4, 'source' => 'بلاغ',
    'maint_type' => 'علاجية', 'priority' => 'عادية',
    'root_cause_id' => $rootCause, 'actions_taken' => 'إصلاحٌ اختباري',
    'inspection_result' => 'ناجح', 'external_cost' => $COST,
    'state' => 'إغلاق',
));
check($code === 302, "الإقفالُ نُفِّذ وأُعيد التوجيه (HTTP {$code})");

$row = $db->query("SELECT state, total_cost FROM mnt_order WHERE id=" . $OID)->fetch_assoc();
check($row && $row['state'] === 'إغلاق', 'حالةُ الأمر صارت «إغلاق»');

// ── الأثرُ بلغ الدفتر — بلا أن يضغط أحدٌ زرًّا ─────────────────────────────
$ev = $db->query("SELECT * FROM fin_financial_events WHERE entity_type='mnt_order' AND entity_id=" . $OID)->fetch_assoc();
check($ev !== null, '★ التكلفةُ بلغت الدفترَ لحظةَ الإقفال — بلا زرِّ سحب');
if ($ev) {
    check(abs((float)$ev['amount'] - $COST) < 0.01, "المبلغُ {$COST} بحرفه");
    check((string)$ev['source_ref'] === $CODE, 'المرجعُ كودُ الأمر — الخيطُ إلى مستنده');
    check(intval($ev['equipment_id']) === 9, 'المعدةُ محفوظةٌ (فتصل تكلفةُ ساعتها)');
    check(intval($ev['project_id']) === 4, 'والمشروعُ محفوظٌ (فتصحّ ربحيتُه)');
    check(!empty($ev['root_event_id']), 'وللصفِّ جذرٌ في سجل الحقائق');
}

// ── حفظٌ ثانٍ للأمر المقفل لا يضاعف ────────────────────────────────────────
list($code2, $h2, $b2) = mc_req($BASE . '/Maintenance/orders.php', $jar, array(
    'action' => 'save_order', 'id' => $OID, 'csrf_token' => isset($tk[1]) ? $tk[1] : '',
    'equipment_id' => 9, 'project_id' => 4, 'source' => 'بلاغ',
    'maint_type' => 'علاجية', 'priority' => 'عادية',
    'root_cause_id' => $rootCause, 'actions_taken' => 'إصلاحٌ اختباري',
    'inspection_result' => 'ناجح', 'external_cost' => $COST, 'state' => 'إغلاق',
));
$n = (int) $db->query("SELECT COUNT(*) FROM fin_financial_events WHERE entity_type='mnt_order' AND entity_id=" . $OID)->fetch_row()[0];
check($n === 1, "حفظٌ ثانٍ للأمر المقفل → يبقى صفٌّ واحدٌ في الدفتر (وُجد {$n})");

// ── وزرُّ الاستيراد لم يعد يرى الأمر مرشَّحًا ──────────────────────────────
list($code3, $h3, $imp) = mc_req($BASE . '/Finance/import_events_fin.php', $jar);
check(strpos($imp, $CODE) === false || $code3 !== 200,
    'الأمرُ المنشورُ لم يعد يظهر مرشَّحًا في شاشة الاستيراد (لا سحبَ مكرر)');

$cleanup();
check((int) $db->query("SELECT COUNT(*) FROM mnt_order WHERE id=" . $OID)->fetch_row()[0] === 0,
    'كُنس ما بُذر — لا أثرَ للاختبار في البيانات');

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
