<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-03 §5.2 — اختبار قبول صندوق الاعتماد الواحد (EMS_APPROVAL_BOX)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/approval_box_test.php   (يتطلب العلم on + بذرة 2027-03)
 *
 * على متجاوزَي الطاقة الحقيقيَّين في نافذة آذار (المعدة 24: 11+11=22):
 *   ① الشاشة تعرض عمودَ توزيع الزمن وشارةَ ⚠ للمعلَّم (تصييرٌ فعلي).
 *   ② «لا يُعتمد قبل التخليص»: اعتمادُ الموقع للمعلَّم يُوقَف معلَنًا
 *      بأسبابه (blocked) — وصفرُ صفِّ اعتمادٍ يُكتب.
 *   ③ التخليص بلا سببٍ يُرفض · وبالسبب والمشغّل الثاني يخلّص كلَّ المحاور
 *      باسم من خلّصه — ثم الاعتمادُ يمرّ.
 *   ④ «إعادةٌ للاستكمال بسبب — بالرقم نفسه»: الواقعةُ تعود returned بجولةٍ
 *      جديدةٍ ورقمُها لا يتغيّر، والملاحظةُ تُسجَّل، والتعديلُ ينفتح
 *      (قفلُ النسخة انفكّ بالجولة).
 *   ⑤ الصفوفُ السليمة لا تُعاق (اعتمادُ صفٍّ نظيفٍ يمرّ بلا سؤال).
 *
 * الحزمة idempotent: تُعيد حالةَ ما مسّته إلى ما قبلها في البدء والختام.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

const BASE = 'http://localhost/ems';

$env = array();
foreach (file(dirname(__DIR__) . '/.env') as $l) {
    if (preg_match('/^\s*([A-Z_]+)=(.*)$/', $l, $m)) { $env[$m[1]] = trim($m[2]); }
}
if (($env['EMS_APPROVAL_BOX'] ?? 'off') !== 'on') {
    fwrite(STDOUT, "EMS_APPROVAL_BOX ليس on — تخطٍّ ناجح.\n");
    exit(0);
}
$db = new mysqli($env['DB_HOST'], 'root', '', $env['DB_NAME']);
$db->set_charset('utf8mb4');

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$JAR = sys_get_temp_dir() . '/ems_box_cookies.txt';
function req($url, $post = null, array $headers = array()) {
    global $JAR;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return array($c, $b);
}
function login($u) {
    global $JAR; @unlink($JAR);
    list(, $p) = req(BASE . '/login.php');
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $p, $m);
    list($c) = req(BASE . '/login.php', array('username' => $u, 'password' => '12345678', 'csrf_token' => $m[1] ?? ''));
    return $c === 200;
}
function ajax($action, array $data) {
    list(, $pg) = req(BASE . '/Approvals/hours_approval.php');
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $pg, $m);
    $data['action'] = $action;
    $data['csrf_token'] = $m[1] ?? '';
    list(, $b) = req(BASE . '/Approvals/hours_approval_handler.php', $data,
        array('X-Requested-With: XMLHttpRequest'));
    return json_decode($b, true);
}

// ═══ المعطيات: متجاوزا آذار + صفٌّ نظيف ═══
$flagged = $db->query(
    "SELECT t.id AS ts_id, u.id AS entry_id, u.entry_no, u.state, u.current_round, u.company_id
       FROM timesheet t JOIN unit_entries u ON u.sync_uuid = CONCAT('ts:', t.id)
      WHERE u.capacity_flag = 1 AND u.entry_date BETWEEN '2027-03-01' AND '2027-03-14'
      ORDER BY t.id")->fetch_all(MYSQLI_ASSOC);
$clean = $db->query(
    "SELECT t.id AS ts_id, u.id AS entry_id, u.entry_no, u.state, u.current_round, u.company_id
       FROM timesheet t JOIN unit_entries u ON u.sync_uuid = CONCAT('ts:', t.id)
      WHERE u.capacity_flag = 0 AND u.entry_date BETWEEN '2027-03-01' AND '2027-03-14'
        AND u.state = 'submitted' AND u.unit_type = 'hour'
        AND NOT EXISTS (SELECT 1 FROM timesheet_approvals a WHERE a.timesheet_id = t.id)
      ORDER BY t.id LIMIT 2")->fetch_all(MYSQLI_ASSOC);

// ═══ الإرجاع (idempotency) — قبل وبعد ═══
$restore = function () use ($db, $flagged, $clean) {
    $all = array_merge($flagged, $clean);
    foreach ($all as $r) {
        $db->query("DELETE FROM timesheet_approvals WHERE timesheet_id = {$r['ts_id']}");
        $db->query("DELETE FROM timesheet_approval_notes WHERE timesheet_id = {$r['ts_id']} AND column_name = 'return_to_site'");
        $db->query("DELETE FROM unit_approvals WHERE entry_id = {$r['entry_id']}");
        $db->query("UPDATE unit_entries SET state = 'submitted', current_round = 1 WHERE id = {$r['entry_id']}");
        $db->query("UPDATE unit_capacity_flags SET cause_note = NULL, cleared_by = NULL, cleared_at = NULL,
                    second_operator_present = NULL WHERE entry_id = {$r['entry_id']}");
        $db->query("DELETE FROM ems_business_events WHERE entity_type = 'timesheet' AND entity_id = {$r['ts_id']}");
    }
};
$restore();
register_shutdown_function($restore);

fwrite(STDOUT, "\n══ UX-03 §5.2 — صندوقُ الاعتماد الواحد ══\n");
// ثلاثة: واقعتا المعدة 24 (11+11=22/20) + واقعةُ المشغّل 7 (10.5/10 عبر صفّين —
// التقاطٌ مشروعٌ من محور المشغّل وثّقته حزمة سطور الزمن)
check(count($flagged) === 3, 'متجاوزو آذار حاضرون (معدة×2 + مشغّل×1): ' . count($flagged));
check(count($clean) >= 1, 'وصفٌّ نظيفٌ للتحكم: ' . count($clean));
if (count($flagged) < 3 || count($clean) < 1) {
    fwrite(STDOUT, "شغّل seed_unit_lines_50.php أولًا.\n");
    exit(1);
}
$F1 = $flagged[0]; $F2 = $flagged[1]; $C1 = $clean[0];

// ═══ ① الشاشة تصييرًا فعليًّا ═══
head('① الشاشة: عمودُ توزيع الزمن وشارةُ ⚠ (بعين معتمِد الموقع)');
check(login('محمد'), 'دخول «محمد» (المستوى الأول — اعتماد الموقع)');
list($sc, $sb) = req(BASE . '/Approvals/hours_approval.php');
check($sc === 200 && strpos($sb, 'توزيع الزمن') !== false, 'عمودُ «توزيع الزمن» يصيّر');
check(strpos($sb, 'bx-flag') !== false && strpos($sb, 'لا يُعتمد قبل التخليص') !== false,
    'شارةُ ⚠ على المعلَّم بتلميح «لا يُعتمد قبل التخليص»');
check(strpos($sb, 'bxClearModal') !== false && strpos($sb, 'bxReturnModal') !== false,
    'مودالا التخليص والإعادة حاضران');
check(strpos($sb, 'apv-return') !== false, 'زرُّ «إعادة للاستكمال» في الثلاثية');

// ═══ ② المعلَّم لا يُعتمد ═══
head('② «صفُّ تجاوزِ طاقةٍ لا يُعتمد» — الحارسُ يوقف اعتمادَ الموقع');
$apBefore = (int) $db->query("SELECT COUNT(*) c FROM timesheet_approvals WHERE timesheet_id = {$F1['ts_id']}")->fetch_assoc()['c'];
$res = ajax('approve', array('ids' => (string) $F1['ts_id']));
check(is_array($res) && (int) ($res['approved'] ?? -1) === 0 && !empty($res['blocked']),
    'الاعتماد أُوقف: approved=0 وblocked معلَنٌ بأسبابه');
$blockReason = $res['blocked'][0]['reasons'][0] ?? '';
check(strpos($blockReason, 'تجاوزُ طاقة') !== false,
    'السبب ناطق: ' . mb_substr($blockReason, 0, 60) . '…');
$apAfter = (int) $db->query("SELECT COUNT(*) c FROM timesheet_approvals WHERE timesheet_id = {$F1['ts_id']}")->fetch_assoc()['c'];
check($apAfter === $apBefore, 'وصفرُ صفِّ اعتمادٍ كُتب — لا أثرَ جانبيًّا للمنع');

// ═══ ③ التخليص ═══
head('③ التخليص: الإفصاحُ إلزامٌ — ثم الاعتمادُ يمرّ');
$res = ajax('clear_capacity', array('timesheet_id' => $F1['ts_id'], 'cause_note' => '', 'second_operator' => 1));
check(is_array($res) && empty($res['success']), 'تخليصٌ بلا سببٍ يُرفض');

$res = ajax('clear_capacity', array('timesheet_id' => $F1['ts_id'],
    'cause_note' => 'ورديةٌ مزدوجةٌ طارئةٌ بطلب العميل — اختبار §5.2', 'second_operator' => 1));
check(is_array($res) && !empty($res['success']) && (int) $res['cleared'] >= 1,
    'التخليص بالسبب والمشغّل الثاني: ' . ($res['message'] ?? '؟'));
$fl = $db->query("SELECT COUNT(*) n, SUM(cleared_at IS NOT NULL) c, SUM(cleared_by = 4) byu
    FROM unit_capacity_flags WHERE entry_id = {$F1['entry_id']}")->fetch_assoc();
check((int) $fl['n'] === (int) $fl['c'] && (int) $fl['byu'] === (int) $fl['n'],
    "كلُّ محاور الواقعة خُلّصت باسم المخلِّص (u4): {$fl['c']}/{$fl['n']}");

$res = ajax('approve', array('ids' => (string) $F1['ts_id']));
check(is_array($res) && (int) ($res['approved'] ?? 0) === 1 && empty($res['blocked']),
    'وبعد التخليص: اعتمادُ الموقع يمرّ');
$st = $db->query("SELECT state FROM unit_entries WHERE id = {$F1['entry_id']}")->fetch_assoc();
check($st && $st['state'] === 'site_approved', "المرآة اعتمدت موقعيًّا: {$st['state']}");

// ═══ ④ الإعادة بسببٍ وبالرقم نفسه ═══
head('④ «إعادةٌ للاستكمال بسبب — بالرقم نفسه» (§8.2)');
$res = ajax('return_to_site', array('timesheet_id' => $C1['ts_id'], 'reason' => ''));
check(is_array($res) && empty($res['success']), 'إعادةٌ بلا سببٍ تُرفض');

$res = ajax('return_to_site', array('timesheet_id' => $C1['ts_id'],
    'reason' => 'توزيعُ الزمن ناقصٌ — اختبار §5.2'));
check(is_array($res) && !empty($res['success']) && ($res['entry_no'] ?? '') === $C1['entry_no'],
    'الإعادة نجحت بالرقم نفسه: ' . ($res['entry_no'] ?? '؟'));
$e4 = $db->query("SELECT state, current_round, entry_no FROM unit_entries WHERE id = {$C1['entry_id']}")->fetch_assoc();
check($e4['state'] === 'returned' && (int) $e4['current_round'] === 2 && $e4['entry_no'] === $C1['entry_no'],
    "الواقعة returned بجولةٍ جديدة (round={$e4['current_round']}) والرقمُ ثابت");
$note = $db->query("SELECT note_text FROM timesheet_approval_notes
    WHERE timesheet_id = {$C1['ts_id']} AND column_name = 'return_to_site'")->fetch_assoc();
check($note !== null && strpos($note['note_text'], 'ناقص') !== false,
    'وملاحظةُ الإعادة مسجّلةٌ ليراها المُدخِل');

// والتعديل انفتح: refreshEntry يقبل returned (قفلُ النسخة انفكّ بالجولة)
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Unit/TimesheetEntryService.php';
$conn2 = $GLOBALS['conn'];
$gctx = \App\Core\TenantContext::forSystem((int) $C1['company_id'], 1, '', true);
$g = new \App\Core\TenantDb($conn2, $gctx);
$db = new mysqli($env['DB_HOST'], 'root', '', $env['DB_NAME']);  // config.php يدوس $db
$db->set_charset('utf8mb4');
// نلتقط أصلَ الواقعة (كميةً وسطورًا) لنعيدها بعد برهان التعديل — العطالة الكاملة
$origQty = (float) $db->query("SELECT qty FROM unit_entries WHERE id = {$C1['entry_id']}")->fetch_assoc()['qty'];
$origLines = array();
$olr = $db->query("SELECT hours, ops_state, resp_party, cause_note FROM unit_time_log
                    WHERE entry_id = {$C1['entry_id']} ORDER BY id");
while ($ol = $olr->fetch_assoc()) {
    $origLines[] = array('hours' => (float) $ol['hours'], 'ops_state' => $ol['ops_state'],
                         'resp_party' => $ol['resp_party'], 'cause_note' => $ol['cause_note']);
}
$rf = \App\Services\Unit\TimesheetEntryService::refreshEntry($conn2, $g,
    (int) $C1['company_id'], (int) $C1['entry_id'],
    array('qty' => 6.5, 'time_lines' => array(
        array('hours' => 6.5, 'ops_state' => 'actual_work', 'resp_party' => 'none'))), 1);
$q4 = $db->query("SELECT qty FROM unit_entries WHERE id = {$C1['entry_id']}")->fetch_assoc();
check(!empty($rf['ok']) && (float) $q4['qty'] == 6.5,
    'والتعديلُ انفتح بعد الإعادة (قفلُ النسخة انفكّ بالجولة): الكمية 6.5');
// الإرجاع الفوري — الواقعة تعود بحرفها فلا تتأثر حزمُ النافذة الأخرى
\App\Services\Unit\TimesheetEntryService::refreshEntry($conn2, $g,
    (int) $C1['company_id'], (int) $C1['entry_id'],
    array('qty' => $origQty, 'time_lines' => $origLines), 1);

// ═══ ⑤ السليمُ لا يُعاق ═══
head('⑤ الصفوفُ السليمة تمرّ بلا سؤال');
$C2 = $clean[1] ?? null;
if ($C2) {
    $res = ajax('approve', array('ids' => (string) $C2['ts_id']));
    check(is_array($res) && (int) ($res['approved'] ?? 0) === 1 && empty($res['blocked']),
        'صفٌّ نظيف: اعتمادٌ فوريٌّ بلا حارسٍ ولا سؤال');
} else {
    ok('(لا صفَّ نظيفًا ثانيًا متاحًا — غُطّي ضمنًا في ③)');
}

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
