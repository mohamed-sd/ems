<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-03 §8.1 — اختبار قبول قلب الكاتب (EMS_TS_WRITER=service)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/writer_flip_test.php     (يتطلب العلمَين on/service)
 *
 * بذرٌ وتحققٌ معًا عبر الشاشة الحقيقية HTTP — نافذة 2027-04-01→07 معزولة:
 *   ① جديد: الخدمةُ تكتب أولًا وصفُّ timesheet نسخةٌ مربوطة sync_uuid —
 *      والأعمدةُ مطابقةٌ للسطور (صفر فرق).
 *   ② مفتاحُ العطالة: تكرارُ (معدة×يوم×وردية) يُرفض بمرجعه — **صفرُ صفٍّ
 *      جديدٍ في الدفترين** (البابُ الذي دخل منه التكرار أربعَ مراتٍ أُغلق).
 *   ③ تعديلٌ قبل اعتماد الموقع: الدفتران يتحدّثان معًا (refreshEntry).
 *   ④ قفلُ النسخة: بعد اعتماد الموقع (L1) الشاشةُ ترفض التعديل (423)
 *      وطلبُ التعديل (aprovment) يرفض كذلك — نصُّ §8.2 بقرار المالك.
 *   ⑤ فجوةُ المرآة القديمة أُصلحت: تعديلُ صفٍّ في وضع legacy ينتقل الآن
 *      عبر mirrorFromTimesheet→refreshEntry (كان يقف عند 200 صامتًا).
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

const BASE = 'http://localhost/ems';
const WIN_FROM = '2027-04-01';
const WIN_TO   = '2027-04-07';

require_once dirname(__DIR__) . '/app/Services/Unit/TimesheetEntryService.php';
use App\Services\Unit\TimesheetEntryService as Svc;

$env = array();
foreach (file(dirname(__DIR__) . '/.env') as $l) {
    if (preg_match('/^\s*([A-Z_]+)=(.*)$/', $l, $m)) { $env[$m[1]] = trim($m[2]); }
}
$db = new mysqli($env['DB_HOST'], 'root', '', $env['DB_NAME']);
$db->set_charset('utf8mb4');

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

if (($env['EMS_TS_WRITER'] ?? 'legacy') !== 'service') {
    fwrite(STDOUT, "EMS_TS_WRITER ليس service — الحزمة تخصّ الوضعَ المقلوب. تخطٍّ ناجح.\n");
    exit(0);
}

// ── HTTP ──
$JAR = sys_get_temp_dir() . '/ems_flip_cookies.txt';
@unlink($JAR);
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

// ── كنسُ النافذة (عطالة الحزمة) ──
$purge = function () use ($db) {
    $ids = array(); $mir = array();
    $r = $db->query("SELECT id FROM timesheet WHERE `date` BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'");
    while ($x = $r->fetch_row()) { $ids[] = (int) $x[0]; }
    $r = $db->query("SELECT id FROM unit_entries WHERE entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'");
    while ($x = $r->fetch_row()) { $mir[] = (int) $x[0]; }
    if ($mir) {
        $in = implode(',', $mir);
        $db->query("DELETE FROM ems_business_events WHERE entity_type='unit_entry' AND entity_id IN ({$in})");
        $db->query("DELETE FROM unit_approvals WHERE entry_id IN ({$in})");
        $db->query("DELETE FROM unit_capacity_flags WHERE entry_id IN ({$in})");
        $db->query("DELETE FROM unit_time_log WHERE entry_id IN ({$in})");
        $db->query("DELETE FROM unit_entries WHERE id IN ({$in})");
    }
    if ($ids) {
        $in = implode(',', $ids);
        $db->query("DELETE FROM ems_business_events WHERE entity_type='timesheet' AND entity_id IN ({$in})");
        $db->query("DELETE FROM timesheet_approval_notes WHERE timesheet_id IN ({$in})");
        $db->query("DELETE FROM timesheet_approvals WHERE timesheet_id IN ({$in})");
        $db->query("DELETE FROM timesheet_failure_hours WHERE timesheet_id IN ({$in})");
        $db->query("DELETE FROM timesheet WHERE id IN ({$in})");
    }
};
$purge();
register_shutdown_function($purge);

fwrite(STDOUT, "\n══ UX-03 §8.1 — قلبُ الكاتب: الخدمةُ أولًا والقديمُ نسخة ══\n");

check(login('محمد'), 'دخول «محمد» (إدارة التشغيل)');
list(, $pg) = req(BASE . '/Timesheet/timesheet.php?type=1');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $pg, $m);
$csrf = $m[1] ?? '';

$mkPost = function ($op, $emp, $day, $shift, array $lines) use ($csrf) {
    return array(
        'operator' => (string) $op, 'employee_id' => (string) $emp,
        'shift' => $shift, 'date' => $day, 'type' => '1', 'user_id' => '1',
        'csrf_token' => $csrf, 'general_notes' => 'FLIP-TEST',
        'ts_time_lines_json' => json_encode($lines, JSON_UNESCAPED_UNICODE),
        'executed_hours' => '0', 'standby_hours' => '0', 'shift_hours' => '0',
        'total_work_hours' => '0', 'operator_hours' => '0', 'total_fault_hours' => '0',
        'maintenance_fault' => '0', 'hr_fault' => '0', 'marketing_fault' => '0',
        'ts_supplier_stop_hours' => '0', 'ts_planned_stop_hours' => '0',
        'ts_force_majeure_hours' => '0', 'other_fault_hours' => '0', 'dependence_hours' => '0',
        'tons_count' => '0', 'trips_count' => '0', 'meters_count' => '0',
        'drilling_holes_count' => '0', 'drilling_depth' => '0',
    );
};
$L = function ($h, $s, $r, $n = '') { return array('hours' => $h, 'ops_state' => $s, 'resp_party' => $r, 'cause_note' => $n); };

// ═══ ① جديد — الخدمة أولًا والنسخة مربوطة ═══
head('① الحفظ الجديد: الخدمةُ الأصلُ والقديمُ نسخةٌ مربوطة');
$day1 = WIN_FROM;
list($c, $b) = req(BASE . '/Timesheet/timesheet.php?type=1',
    $mkPost(4, 3, $day1, 'D', array($L(8.0, 'actual_work', 'none'), $L(2.0, 'standby', 'client', 'استعداد'))));
check($c === 200 && strpos($b, 'المصدر: السجل القانوني') !== false,
    'الحفظ نجح برسالة المصدر الجديد');

$ts1 = $db->query("SELECT t.*, u.id AS eid, u.qty, u.unit_type, u.state, u.source_ref
    FROM timesheet t JOIN unit_entries u ON u.sync_uuid=CONCAT('ts:',t.id)
    WHERE t.`date`='{$day1}' AND t.general_notes='FLIP-TEST'")->fetch_assoc();
check($ts1 !== null, 'الدفتران مكتوبان ومربوطان (sync_uuid)');
check($ts1 && $ts1['source_ref'] === 'TS-' . $ts1['id'], "النسبُ صادق: source_ref={$ts1['source_ref']}");
check($ts1 && (float) $ts1['executed_hours'] == 8.0 && (float) $ts1['standby_hours'] == 2.0
    && (float) $ts1['shift_hours'] == 10.0 && (float) $ts1['qty'] == 8.0,
    'النسخةُ القديمة مشتقةٌ صفرَ فرق (8 فعلي · 2 استعداد · وردية 10)');
$lg1 = (int) $db->query("SELECT COUNT(*) c FROM unit_time_log WHERE entry_id={$ts1['eid']}")->fetch_assoc()['c'];
check($lg1 === 2, "سطرا الزمن في السجل القانوني: {$lg1}");

// ═══ ② مفتاح العطالة يغلق باب التكرار ═══
head('② التكرار (معدة×يوم×وردية) يُرفض بمرجعه — صفرُ كتابة');
$tsBefore = (int) $db->query("SELECT COUNT(*) c FROM timesheet")->fetch_assoc()['c'];
$ueBefore = (int) $db->query("SELECT COUNT(*) c FROM unit_entries")->fetch_assoc()['c'];
list($c, $b) = req(BASE . '/Timesheet/timesheet.php?type=1',
    $mkPost(4, 4, $day1, 'D', array($L(9.0, 'actual_work', 'none'))));
check(strpos($b, 'مسجَّلٌ سلفًا') !== false && strpos($b, 'UNT-') !== false,
    'رُفض برسالةٍ تحمل مرجعَ الصف القائم');
$tsAfter = (int) $db->query("SELECT COUNT(*) c FROM timesheet")->fetch_assoc()['c'];
$ueAfter = (int) $db->query("SELECT COUNT(*) c FROM unit_entries")->fetch_assoc()['c'];
check($tsAfter === $tsBefore && $ueAfter === $ueBefore,
    'وصفرُ صفٍّ جديدٍ في الدفترين — البابُ الذي دخل منه التكرارُ أربعَ مراتٍ أُغلق');

// ═══ ③ تعديل قبل الاعتماد — الدفتران يتحدثان ═══
head('③ التعديل قبل اعتماد الموقع: refreshEntry يحدّث الدفترين');
$post3 = $mkPost(4, 3, $day1, 'D', array($L(7.0, 'actual_work', 'none'), $L(1.0, 'standby', 'client'),
    $L(2.0, 'tech_breakdown', 'company', 'WO-7001')));
$post3['id'] = (string) $ts1['id'];
list($c, $b) = req(BASE . '/Timesheet/timesheet.php?type=1', $post3);
check($c === 200 && strpos($b, 'تم الحفظ بنجاح') !== false, 'التعديل قُبل (الحالة قبل القفل)');
$ts3 = $db->query("SELECT t.executed_hours, t.maintenance_fault, u.qty,
    (SELECT COUNT(*) FROM unit_time_log l WHERE l.entry_id=u.id) ln,
    (SELECT COUNT(*) FROM unit_time_log l WHERE l.entry_id=u.id AND l.cause_note='WO-7001') wo
    FROM timesheet t JOIN unit_entries u ON u.sync_uuid=CONCAT('ts:',t.id)
    WHERE t.id={$ts1['id']}")->fetch_assoc();
check((float) $ts3['executed_hours'] == 7.0 && (float) $ts3['maintenance_fault'] == 2.0,
    'النسخة القديمة تحدّثت (7 فعلي · 2 عطل)');
check((float) $ts3['qty'] == 7.0 && (int) $ts3['ln'] === 3 && (int) $ts3['wo'] === 1,
    'والسجل القانوني تحدّث: كمية 7 · 3 سطور · مرجع WO-7001');

// ═══ ④ قفل النسخة بعد اعتماد الموقع ═══
head('④ قفلُ النسخة (§8.2): اعتمادُ الموقع يمنع التعديلَ وطلبَه');
list(, $pgA) = req(BASE . '/Approvals/hours_approval.php');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $pgA, $mA);
list($c, $aj) = req(BASE . '/Approvals/hours_approval_handler.php',
    array('action' => 'approve', 'ids' => (string) $ts1['id'], 'csrf_token' => $mA[1] ?? ''),
    array('X-Requested-With: XMLHttpRequest'));
$j = json_decode($aj, true);
check(is_array($j) && !empty($j['success']) && (int) $j['approved'] === 1, 'اعتمادُ الموقع (L1) تم');
$st4 = $db->query("SELECT state FROM unit_entries WHERE id={$ts1['eid']}")->fetch_assoc();
check($st4 && $st4['state'] === 'site_approved', "حالة الواقعة: {$st4['state']}");

list($c, $b) = req(BASE . '/Timesheet/timesheet.php?type=1', $post3);
check(strpos($b, 'قفلُ نسخة') !== false, 'الشاشة ترفض التعديل: «قفلُ نسخة — التعديلُ بالإعادة الرسمية»');
$ts4 = $db->query("SELECT executed_hours FROM timesheet WHERE id={$ts1['id']}")->fetch_assoc();
check((float) $ts4['executed_hours'] == 7.0, 'ولم يتغيّر حرفٌ في الدفترين');

// طلب التعديل القديم (aprovment) يُرفض كذلك
list($c, $b) = req(BASE . '/Timesheet/aprovment.php?id=' . $ts1['id'] . '&type=1&t=1',
    array('t' => '1', 'time_notes' => 'محاولة تعديل بعد الاعتماد', 'csrf_token' => $csrf));
check(strpos($b, 'قفلُ نسخة') !== false, 'وطلبُ التعديل القديم يُرفض بالنصّ نفسه');

// ═══ ⑤ فجوة المرآة أُصلحت (وضع legacy) ═══
head('⑤ فجوةُ المرآة: التعديل ينتقل الآن عبر mirrorFromTimesheet');
// صفٌّ من نافذة آذار (كُتب بالكاتب القديم) حالتُه قبل القفل — نعدّل عموده يدويًّا
// ثم نستدعي المرآة كما يفعل المسار القديم: يجب أن تنتقل الكمية.
$m5 = $db->query("SELECT t.id, u.id eid, u.qty FROM timesheet t
    JOIN unit_entries u ON u.sync_uuid=CONCAT('ts:',t.id)
    WHERE t.`date` BETWEEN '2027-03-01' AND '2027-03-14' AND u.state='submitted'
      AND u.unit_type='hour' ORDER BY t.id LIMIT 1")->fetch_assoc();
check($m5 !== null, 'صفُّ آذارٍ حيٌّ قبل القفل موجود');
if ($m5) {
    $db->query("UPDATE timesheet SET executed_hours = 5.5, total_work_hours = 5.5, shift_hours = 5.5,
                standby_hours = 0, total_fault_hours = 0, maintenance_fault = 0, hr_fault = 0,
                marketing_fault = 0, ts_supplier_stop_hours = 0, ts_planned_stop_hours = 0,
                ts_force_majeure_hours = 0, other_fault_hours = 0, dependence_hours = 0
                WHERE id = {$m5['id']}");
    require_once dirname(__DIR__) . '/config.php';
    require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
    require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
    require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
    require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
    $conn2 = $GLOBALS['conn'];
    $gctx = \App\Core\TenantContext::forSystem(4, 1, '', true);
    $g = new \App\Core\TenantDb($conn2, $gctx);
    $mr = Svc::mirrorFromTimesheet($conn2, $g, (int) $m5['id'], 1);
    // ⚠️ config.php يدوس $db — يُعاد الاتصال (گوتشا موثقة)
    $db = new mysqli($env['DB_HOST'], 'root', '', $env['DB_NAME']);
    $db->set_charset('utf8mb4');
    $q5 = $db->query("SELECT qty, (SELECT ROUND(COALESCE(SUM(hours),0),2) FROM unit_time_log l
                       WHERE l.entry_id=unit_entries.id) th FROM unit_entries WHERE id={$m5['eid']}")->fetch_assoc();
    check(!empty($mr['ok']) && !empty($mr['existing']) && (float) $q5['qty'] == 5.5 && (float) $q5['th'] == 5.5,
        "المرآة أنعشت الواقعة: كمية {$q5['qty']} · زمن {$q5['th']} (كانت {$m5['qty']})");
}

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
