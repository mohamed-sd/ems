<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * قياسُ فجوة المشغّل على بياناتٍ جديدةٍ حصرًا — 2027-05 (طلب المالك)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/seed_operator_gap_50.php [--purge]
 *
 * «لا تستخرج تاسكًا من الإدخالات القديمة — أدخِل 50 حقلًا جديدًا وقِس عليهم.»
 *
 * السؤالان المقيسان:
 *   ① هل ما زال يمكن حفظُ يومِ عملٍ **بلا مشغّل**؟ (فجوة 7% التاريخية)
 *   ② `operator_hours` — هل يُملأ؟ وهل يوافق ساعاتِ التشغيل؟
 *
 * المنهج: 50 صفًّا عبر الشاشة الحقيقية HTTP كما يفعل مديرُ الموقع — منها
 * 12 صفًّا **بلا اختيار مشغّل** (السلوكُ الواقعي حين يُنسى الحقلُ غيرُ الإلزامي)
 * و38 بمشغّل. ثم نقرأ الناتجَ من الدفترين ونحكم بالأرقام لا بالافتراض.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

const BASE     = 'http://localhost/ems';
const WIN_FROM = '2027-05-01';
const WIN_TO   = '2027-05-14';

$JAR = sys_get_temp_dir() . '/ems_gap50_cookies.txt';
@unlink($JAR);

$env = array();
foreach (file(dirname(__DIR__) . '/.env') as $l) {
    if (preg_match('/^\s*([A-Z_]+)=(.*)$/', $l, $m)) { $env[$m[1]] = trim($m[2]); }
}
$db = new mysqli($env['DB_HOST'], 'root', '', $env['DB_NAME']);
$db->set_charset('utf8mb4');

function req($url, $post = null) {
    global $JAR;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR, CURLOPT_TIMEOUT => 30));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return array($c, $b);
}

function purge(mysqli $db) {
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
    return array(count($ids), count($mir));
}

if (in_array('--purge', $argv, true)) {
    list($t, $u) = purge($db);
    fwrite(STDOUT, "كُنست النافذة: {$t} صفَّ دوامٍ · {$u} مرآة\n");
    exit(0);
}

$OPS = array(4, 5, 8, 12, 13, 18, 32, 34, 40, 41);   // معداتٌ متمايزة
$EQ  = array(4 => 6, 5 => 7, 8 => 4, 12 => 8, 13 => 10, 18 => 18, 32 => 5, 34 => 21, 40 => 25, 41 => 26);
$EMPS = array(3, 4, 5, 6, 7, 8, 9, 10, 11, 12);
$days = array();
for ($d = 0; $d < 14; $d++) { $days[] = date('Y-m-d', strtotime(WIN_FROM . " +{$d} days")); }

// ── الخطة: 50 صفًّا · 12 منها بلا مشغّل (المواضع محدَّدةٌ لا عشوائية) ──
$noOperatorAt = array(2, 5, 9, 13, 17, 21, 26, 31, 36, 41, 45, 48);
$plan = array();
for ($n = 0; $n < 50; $n++) {
    $op    = $OPS[$n % 10];
    $day   = $days[intdiv($n, 4) % 14];
    $shift = ($n % 2 === 0) ? 'D' : 'N';
    $plan[] = array(
        'op' => $op, 'day' => $day, 'shift' => $shift,
        'emp' => in_array($n, $noOperatorAt, true) ? '' : (string) $EMPS[$n % 10],
        'work' => 8.0 + ($n % 3),          // 8 · 9 · 10
        'standby' => ($n % 4 === 0) ? 1.0 : 0.0,
    );
}
// حارسُ المفتاح
$seen = array();
foreach ($plan as $p) {
    $k = $EQ[$p['op']] . '|' . $p['day'] . '|' . $p['shift'];
    if (isset($seen[$k])) { fwrite(STDERR, "FATAL: تصادم {$k}\n"); exit(1); }
    $seen[$k] = true;
}

fwrite(STDOUT, "\n══ قياسُ فجوة المشغّل — 50 صفًّا جديدًا (" . WIN_FROM . " → " . WIN_TO . ") ══\n");
list($pt, $pm) = purge($db);
if ($pt || $pm) { fwrite(STDOUT, "كُنست بقايا: {$pt} صف · {$pm} مرآة\n"); }

list(, $pg) = req(BASE . '/login.php');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $pg, $m);
list($lc) = req(BASE . '/login.php', array('username' => 'محمد', 'password' => '12345678', 'csrf_token' => $m[1] ?? ''));
if ($lc !== 200) { fwrite(STDERR, "FATAL: فشل الدخول\n"); exit(1); }
list(, $pg) = req(BASE . '/Timesheet/timesheet.php?type=1');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $pg, $m);
$csrf = $m[1] ?? '';

$okN = 0; $failN = 0; $rejectedNoEmp = 0;
foreach ($plan as $idx => $p) {
    $lines = array(array('hours' => $p['work'], 'ops_state' => 'actual_work', 'resp_party' => 'none'));
    if ($p['standby'] > 0) { $lines[] = array('hours' => $p['standby'], 'ops_state' => 'standby', 'resp_party' => 'client'); }
    $row = array(
        'operator' => (string) $p['op'], 'employee_id' => $p['emp'],
        'shift' => $p['shift'], 'date' => $p['day'], 'type' => '1', 'user_id' => '1',
        'csrf_token' => $csrf, 'general_notes' => 'GAP50',
        'ts_time_lines_json' => json_encode($lines, JSON_UNESCAPED_UNICODE),
        // operator_hours يُترك كما تتركه الشاشةُ فعلًا (تحسبه بجافاسكربت من الساعات)
        'operator_hours' => (string) $p['work'],
        'executed_hours' => '0', 'standby_hours' => '0', 'shift_hours' => '0',
        'total_work_hours' => '0', 'total_fault_hours' => '0',
        'maintenance_fault' => '0', 'hr_fault' => '0', 'marketing_fault' => '0',
        'ts_supplier_stop_hours' => '0', 'ts_planned_stop_hours' => '0',
        'ts_force_majeure_hours' => '0', 'other_fault_hours' => '0', 'dependence_hours' => '0',
        'tons_count' => '0', 'trips_count' => '0', 'meters_count' => '0',
        'drilling_holes_count' => '0', 'drilling_depth' => '0',
    );
    list($c, $b) = req(BASE . '/Timesheet/timesheet.php?type=1', $row);
    $saved = ($c === 200 && (strpos($b, 'تم الحفظ') !== false || strpos($b, 'تجاوز طاقة') !== false));
    if ($saved) { $okN++; }
    else {
        $failN++;
        if ($p['emp'] === '') { $rejectedNoEmp++; }
        preg_match("/alert\('([^']{0,100})/", $b, $em);
        if ($failN <= 4) { fwrite(STDOUT, "  ✘ #" . ($idx + 1) . ($p['emp'] === '' ? ' [بلا مشغّل]' : '') . ": " . ($em[1] ?? "HTTP {$c}") . "\n"); }
    }
}

fwrite(STDOUT, "\nحُفظ {$okN} · فشل {$failN}" . ($rejectedNoEmp ? " (منها {$rejectedNoEmp} رُفض لغياب المشغّل)" : '') . "\n");

// ═══ القياس ═══
$W = "`date` BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'";
fwrite(STDOUT, "\n══ النتائج على البيانات الجديدة وحدها ══\n");

$tot = (int) $db->query("SELECT COUNT(*) c FROM timesheet WHERE {$W}")->fetch_assoc()['c'];
$noEmp = (int) $db->query("SELECT COUNT(*) c FROM timesheet WHERE {$W} AND (employee_id IS NULL OR employee_id='' OR employee_id='0')")->fetch_assoc()['c'];
fwrite(STDOUT, "\n① فجوةُ المشغّل:\n");
fwrite(STDOUT, "   حُوول حفظُ 12 صفًّا بلا مشغّل · رُفض منها: {$rejectedNoEmp}\n");
fwrite(STDOUT, "   الناتج: {$noEmp} من {$tot} صفًّا محفوظًا بلا مشغّل ("
    . ($tot ? round($noEmp * 100 / $tot, 1) : 0) . "%)\n");
fwrite(STDOUT, "   الحكم: " . ($noEmp > 0
    ? "**الفجوة قائمة** — النظامُ يقبل يومَ عملٍ بلا مشغّل بلا اعتراض"
    : "**الفجوة مُغلقة** — النظامُ رفض كلَّ حفظٍ بلا مشغّل") . "\n");

// ── الشرطُ الجديد: هل اختفت الآلياتُ بلا سائقٍ من القائمة؟ ──
fwrite(STDOUT, "\n④ شرطُ «ولها سائقٌ يعمل عليها» في قائمة الآليات:\n");
$noDrv = $db->query("SELECT o.id, e.name FROM operations o JOIN equipments e ON e.id=o.equipment
    WHERE o.company_id=4 AND o.status='1' AND o.equipment_category='أساسي'
      AND NOT EXISTS (SELECT 1 FROM equipment_drivers ed JOIN employees d ON d.id=ed.employee_id
                       WHERE ed.equipment_id=o.equipment AND ed.status=1 AND d.status=1)")
    ->fetch_all(MYSQLI_ASSOC);
fwrite(STDOUT, "   آلياتٌ نشطةٌ بلا سائقٍ مرتبط: " . count($noDrv) . "\n");
foreach ($noDrv as $nd) { fwrite(STDOUT, "     • تشغيل {$nd['id']} — {$nd['name']} (لن تظهر في القائمة)\n"); }
$listed = (int) $db->query("SELECT COUNT(*) c FROM operations o
    WHERE o.company_id=4 AND o.status='1' AND o.equipment_category='أساسي'
      AND EXISTS (SELECT 1 FROM equipment_drivers ed JOIN employees d ON d.id=ed.employee_id
                   WHERE ed.equipment_id=o.equipment AND ed.status=1 AND d.status=1)")->fetch_assoc()['c'];
fwrite(STDOUT, "   المعروضةُ في القائمة الآن: {$listed} (وكلُّها لها خيارُ سائقٍ مضمون)\n");

$mirNoEmp = (int) $db->query("SELECT COUNT(*) c FROM unit_entries
    WHERE entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "' AND operator_employee_id IS NULL")->fetch_assoc()['c'];
$mirTot = (int) $db->query("SELECT COUNT(*) c FROM unit_entries
    WHERE entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'")->fetch_assoc()['c'];
fwrite(STDOUT, "   وفي السجل القانوني: {$mirNoEmp} من {$mirTot} واقعةً بلا مشغّل\n");

fwrite(STDOUT, "\n② عمود operator_hours:\n");
$oh = $db->query("SELECT
    SUM(COALESCE(operator_hours,0) > 0) filled,
    SUM(COALESCE(operator_hours,0) = 0) zero,
    SUM(COALESCE(operator_hours,0) <> COALESCE(executed_hours,0)) diff
    FROM timesheet WHERE {$W}")->fetch_assoc();
fwrite(STDOUT, "   مملوء: {$oh['filled']} · صفر: {$oh['zero']} · يخالف ساعاتِ التشغيل: {$oh['diff']}\n");
$ohNoEmp = (int) $db->query("SELECT COUNT(*) c FROM timesheet
    WHERE {$W} AND COALESCE(operator_hours,0) > 0
      AND (employee_id IS NULL OR employee_id='' OR employee_id='0')")->fetch_assoc()['c'];
fwrite(STDOUT, "   ⚠ صفوفٌ فيها ساعاتُ مشغّلٍ **بلا مشغّل**: {$ohNoEmp}"
    . ($ohNoEmp ? "  ← ساعاتٌ مستحقّةٌ لا يُعرف صاحبُها" : '') . "\n");

fwrite(STDOUT, "\n③ أثرُ ذلك على المستحق (محرّك السياسات):\n");
fwrite(STDOUT, "   OperatorDue يردّ «لا مشغّلَ على الواقعة» لكل واقعةٍ بلا مشغّل\n");
fwrite(STDOUT, "   ⇒ {$mirNoEmp} يومَ عملٍ بلا مستحقٍّ محسوب.\n");
exit(0);
