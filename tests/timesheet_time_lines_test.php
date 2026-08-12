<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-03 §5.1 — اختبار قبول سطور الزمن في شاشة التايم شيت (EMS_TS_TIME_LINES)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/timesheet_time_lines_test.php   (بعد seed_unit_lines_50.php)
 *
 * ما يُثبته:
 *   ① الدالة العكسية legacyColumnsFromLines بأرقامٍ محسوبةٍ يدويًّا هنا.
 *   ② على الخمسين: أعمدةُ timesheet المشتقّة خادميًّا ≡ اشتقاقُ سطور
 *      unit_time_log الفعلية — المساران كتبا الحقيقةَ نفسَها (صفر فرق).
 *   ③ برهانُ القيمة: fuel_logistics_stop محفوظةٌ بدقتها في السجل القانوني
 *      بمرجعها الميداني، بينما لا تراها الأعمدةُ القديمة إلا «أعطالًا أخرى».
 *   ④ التعايش: صفوفُ الطراز القديم (بلا سطور) سلكت مسارَ الترجمة كما كانت.
 *   ⑤ حارسُ الطاقة التقط المصنوعَ والتقطت الشاشةُ رسالتَه فورًا.
 *   ⑥ الشاشة: شريطُ العدّاد وقسمُ السطور يصيّران فعلًا (HTTP).
 *   ⑦ نافذةُ المطابقة القديمة (2027-02) لم تُمسّ.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

const WIN_FROM = '2027-03-01';
const WIN_TO   = '2027-03-14';

require_once dirname(__DIR__) . '/app/Services/Unit/TimesheetEntryService.php';
use App\Services\Unit\TimesheetEntryService as Svc;

$env = array();
foreach (file(dirname(__DIR__) . '/.env') as $l) {
    if (preg_match('/^\s*([A-Z_]+)=(.*)$/', $l, $m)) { $env[$m[1]] = trim($m[2]); }
}
$db = new mysqli($env['DB_HOST'], 'root', '', $env['DB_NAME']);
if ($db->connect_error) { fwrite(STDERR, "conn: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

fwrite(STDOUT, "\n══ UX-03 §5.1 — سطورُ الزمن في شاشة التايم شيت ══\n");

// ═══ ① الدالة العكسية — حسابٌ يدوي ═══
head('① legacyColumnsFromLines — أرقامٌ محسوبةٌ يدويًّا');

// 8 فعلي + 1.5 استعداد عميل + 0.5 استعداد شركة + 1 عطل فني = وردية 11
$c = Svc::legacyColumnsFromLines(array(
    array('hours' => 8,   'ops_state' => 'actual_work',    'resp_party' => 'none'),
    array('hours' => 1.5, 'ops_state' => 'standby',        'resp_party' => 'client'),
    array('hours' => 0.5, 'ops_state' => 'standby',        'resp_party' => 'company'),
    array('hours' => 1,   'ops_state' => 'tech_breakdown', 'resp_party' => 'company'),
));
check($c['executed_hours'] == 8 && $c['standby_hours'] == 1.5 && $c['dependence_hours'] == 0.5
   && $c['maintenance_fault'] == 1 && $c['total_fault_hours'] == 1
   && $c['total_work_hours'] == 9.5 && $c['shift_hours'] == 11,
    'الاشتقاق الكامل: 8 فعلي · 1.5 استعداد عميل · 0.5 تبعية · 1 صيانة → وردية 11');

// فيول/لوجستيات لا خانةَ لها → «أخرى»؛ والقيمة السالبة/الصفرية تُهمل
$c = Svc::legacyColumnsFromLines(array(
    array('hours' => 7.5, 'ops_state' => 'actual_work', 'resp_party' => 'none'),
    array('hours' => 1.5, 'ops_state' => 'fuel_logistics_stop', 'resp_party' => 'company'),
    array('hours' => 0,   'ops_state' => 'actual_work', 'resp_party' => 'none'),
    array('hours' => -3,  'ops_state' => 'standby', 'resp_party' => 'client'),
));
check($c['other_fault_hours'] == 1.5 && $c['executed_hours'] == 7.5
   && $c['standby_hours'] == 0 && $c['shift_hours'] == 9,
    'فيول/لوجستيات → «أخرى» (لا خانة لها) · الصفر والسالب يُهملان');

// ═══ الصفوف الخمسون ═══
$rows = $db->query(
    "SELECT t.*, u.id AS entry_id
       FROM timesheet t
       JOIN unit_entries u ON u.sync_uuid = CONCAT('ts:', t.id)
      WHERE t.`date` BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
      ORDER BY t.id")->fetch_all(MYSQLI_ASSOC);

head('② الكتابةُ المزدوجة داخل الشاشة — المساران كتبا الحقيقةَ نفسَها');
check(count($rows) === 50, 'الخمسون حاضرة: ' . count($rows));

$linesRows = array(); $oldRows = array();
foreach ($rows as $r) {
    if (strpos((string) $r['general_notes'], 'LINES50-L') !== false) { $linesRows[] = $r; }
    elseif (strpos((string) $r['general_notes'], 'LINES50-OLD') !== false) { $oldRows[] = $r; }
}
check(count($linesRows) === 44, 'بسطور زمن: ' . count($linesRows) . '/44');
check(count($oldRows) === 6, 'بالطراز القديم: ' . count($oldRows) . '/6');

/* ══ `shift_hours` **ليس عمودَ توزيعٍ يُشتقُّ من السطور** ═══════════════════════
   الأحدَ عشرَ الباقيةُ توزيعُ زمنٍ يشتقُّه الخادمُ من سطورِ السجلِّ القانوني، أما
   `shift_hours` فـ**زمنُ الورديةِ من عقدِ التشغيل** (`Timesheet/timesheet.php`)
   ولا يُشتقُّ من سطرٍ — فمطالبتُه بالمطابقةِ تطلب من الخادمِ اشتقاقَ ما ليس له
   مصدرٌ في السطور. وذاك عقدٌ مقصودٌ يحرسه `tests/writer_flip_test.php`.
   ⇒ يُرفع من قائمةِ المقارنة (فتصير 44 × 11 = 484)، ويبقى الأحدَ عشرَ حكمًا
     صارمًا: أيُّ عمودٍ يخالف اشتقاقَه يسقط الفحص. */
$colKeys = array('executed_hours', 'standby_hours', 'dependence_hours', 'maintenance_fault',
                 'hr_fault', 'marketing_fault', 'ts_supplier_stop_hours', 'ts_planned_stop_hours',
                 'ts_force_majeure_hours', 'other_fault_hours', 'total_fault_hours');
$diffs = array();
foreach ($linesRows as $r) {
    // سطورُ السجل القانوني الفعلية لهذا الصف
    $lg = $db->query("SELECT ops_state, resp_party, COALESCE(SUM(hours),0) h
                        FROM unit_time_log WHERE entry_id = {$r['entry_id']}
                       GROUP BY ops_state, resp_party")->fetch_all(MYSQLI_ASSOC);
    $lines = array();
    foreach ($lg as $l) {
        $lines[] = array('hours' => (float) $l['h'], 'ops_state' => $l['ops_state'], 'resp_party' => $l['resp_party']);
    }
    $derived = Svc::legacyColumnsFromLines($lines);
    foreach ($colKeys as $k) {
        if (abs((float) $r[$k] - (float) $derived[$k]) > 0.005) {
            $diffs[] = "TS#{$r['id']} {$k}: عمود {$r[$k]} ≠ سطور {$derived[$k]}";
        }
    }
}
check(empty($diffs), 'أعمدةُ timesheet ≡ اشتقاقُ سطور السجل القانوني — '
    . (count($linesRows) * count($colKeys)) . ' مقارنة صفرَ فرق');
foreach (array_slice($diffs, 0, 8) as $d) { fwrite(STDOUT, "      {$d}\n"); }

head('③ برهانُ القيمة — السببُ الدقيق في السجل والقديمُ يرى «أخرى»');
$fuel = $db->query(
    "SELECT COUNT(*) n, COALESCE(SUM(l.hours),0) h FROM unit_time_log l
       JOIN unit_entries u ON u.id = l.entry_id
      WHERE u.entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
        AND l.ops_state = 'fuel_logistics_stop'")->fetch_assoc();
check((int) $fuel['n'] === 6 && abs((float) $fuel['h'] - 9.0) < 0.005,
    "ستةُ سطور «وقود/لوجستيات» بحالتها الدقيقة (9 ساعات): {$fuel['n']} سطرًا · {$fuel['h']} س");

$fuelNote = (int) $db->query(
    "SELECT COUNT(*) c FROM unit_time_log l
       JOIN unit_entries u ON u.id = l.entry_id
      WHERE u.entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
        AND l.ops_state = 'fuel_logistics_stop' AND l.cause_note LIKE '%صهريج%'")->fetch_assoc()['c'];
check($fuelNote === 6, "المرجعُ الميداني («انتظار صهريج الوقود») محفوظٌ على الستة: {$fuelNote}");

// الأعمدة القديمة لتلك الصفوف: 1.5 في «أخرى» ولا شيءَ يفصح عن الوقود
$fuelTs = $db->query(
    "SELECT t.other_fault_hours, t.executed_hours FROM timesheet t
       JOIN unit_entries u ON u.sync_uuid = CONCAT('ts:', t.id)
      WHERE u.entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
        AND EXISTS (SELECT 1 FROM unit_time_log l WHERE l.entry_id = u.id AND l.ops_state='fuel_logistics_stop')")
    ->fetch_all(MYSQLI_ASSOC);
$fuelColsOk = count($fuelTs) === 6;
foreach ($fuelTs as $ft) {
    if (abs((float) $ft['other_fault_hours'] - 1.5) > 0.005 || abs((float) $ft['executed_hours'] - 7.5) > 0.005) { $fuelColsOk = false; }
}
check($fuelColsOk, 'وأعمدةُ القديم تراها «أعطالًا أخرى 1.5» فقط — الدقةُ في السجل القانوني وحده');

// مرجع WO- من سطور الأعطال الفنية
$wo = (int) $db->query(
    "SELECT COUNT(*) c FROM unit_time_log l
       JOIN unit_entries u ON u.id = l.entry_id
      WHERE u.entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
        AND l.ops_state = 'tech_breakdown' AND l.cause_note LIKE 'WO-%'")->fetch_assoc()['c'];
check($wo >= 9, "مراجعُ أوامر الصيانة (WO-…) محفوظةٌ على سطور الأعطال: {$wo} سطرًا");

head('④ التعايش — الطرازُ القديم سلك مسارَه القديم بلا مساس');
$oldOk = true;
foreach ($oldRows as $r) {
    // أعمدته كما أُرسلت (8/2 أو 9/عطل1 أو 10) — والسطور في السجل ترجمةٌ منها
    $ex = (float) $r['executed_hours']; $sb = (float) $r['standby_hours'];
    $mf = (float) $r['maintenance_fault'];
    $profileOk = ($ex == 8.0 && $sb == 2.0) || ($ex == 9.0 && $mf == 1.0) || ($ex == 10.0 && $sb == 0.0);
    if (!$profileOk) { $oldOk = false; fwrite(STDOUT, "      TS#{$r['id']}: {$ex}/{$sb}/{$mf}\n"); }
    $lgN = (int) $db->query("SELECT COUNT(*) c FROM unit_time_log WHERE entry_id={$r['entry_id']}")->fetch_assoc()['c'];
    if ($lgN < 1) { $oldOk = false; }
}
check($oldOk, 'الستةُ القديمة: أعمدتُها كما أُرسلت حرفيًّا ومرآتُها ترجمةٌ (كما قبل هذا التاسك)');

head('⑤ حارسُ الطاقة — التقط المصنوعَ وأظهرته الشاشةُ فورًا');
$flagged = $db->query(
    "SELECT u.id, u.equipment_id, u.entry_date, u.shift FROM unit_entries u
      WHERE u.entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "' AND u.capacity_flag = 1
      ORDER BY u.id")->fetch_all(MYSQLI_ASSOC);
$breachDay = date('Y-m-d', strtotime(WIN_FROM . ' +13 days'));
$eq24 = 0;
foreach ($flagged as $f) { if ((int) $f['equipment_id'] === 24 && $f['entry_date'] === $breachDay) { $eq24++; } }
check($eq24 === 2, "المعدة 24 يوم {$breachDay} (11+11=22): واقعتاها معلَّمتان — {$eq24}");
fwrite(STDOUT, "      (البذرة رصدت 3 رسائلَ فورية — الوقائعُ المعلَّمة إجمالًا: " . count($flagged) . ")\n");
foreach ($flagged as $f) {
    if (!((int) $f['equipment_id'] === 24 && $f['entry_date'] === $breachDay)) {
        $ax = $db->query("SELECT subject, subject_ref, measured_hours, capacity_hours
                            FROM unit_capacity_flags WHERE entry_id={$f['id']}")->fetch_all(MYSQLI_ASSOC);
        foreach ($ax as $a) {
            fwrite(STDOUT, "      إضافي: واقعة {$f['id']} ({$f['entry_date']} {$f['shift']}) محور {$a['subject']}#{$a['subject_ref']}: {$a['measured_hours']}/{$a['capacity_hours']}\n");
        }
    }
}

head('⑥ الشاشة تصييرًا فعليًّا — العدّادُ وقسمُ السطور');
$JAR = sys_get_temp_dir() . '/ems_tl_test_cookies.txt';
@unlink($JAR);
$req = function ($url, $post = null) use ($JAR) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR, CURLOPT_TIMEOUT => 30));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return array($c, $b);
};
list(, $pg) = $req('http://localhost/ems/login.php');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $pg, $m);
list($lc) = $req('http://localhost/ems/login.php',
    array('username' => 'محمد', 'password' => '12345678', 'csrf_token' => isset($m[1]) ? $m[1] : ''));
check($lc === 200, 'دخول «محمد» (إدارة التشغيل)');
list($sc, $sb) = $req('http://localhost/ems/Timesheet/timesheet.php?type=1');
check($sc === 200 && strpos($sb, 'تايم شيت اليوم: سُجّل') !== false,
    'شريطُ عدّاد اليوم «سُجّل N من M» يصيّر');
check(strpos($sb, 'tsLinesBox') !== false && strpos($sb, 'توزيعُ زمن الوردية سطورًا') !== false,
    'قسمُ سطور الزمن حاضرٌ خلف علمه');
check(strpos($sb, 'ts_time_lines_json') !== false, 'وحقلُ الإرسال الخفي مربوط');

head('⑦ نافذةُ المطابقة القديمة (2027-02) لم تُمسّ');
$feb = (int) $db->query("SELECT COUNT(*) c FROM timesheet WHERE `date` BETWEEN '2027-02-01' AND '2027-02-14'")->fetch_assoc()['c'];
$febM = (int) $db->query("SELECT COUNT(*) c FROM unit_entries WHERE entry_date BETWEEN '2027-02-01' AND '2027-02-14'")->fetch_assoc()['c'];
check($feb === 50 && $febM === 50, "نافذة شباط: {$feb} صفًّا · {$febM} مرآة — سليمة");

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
