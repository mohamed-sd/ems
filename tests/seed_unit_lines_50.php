<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-03 §5.1 — بذرةُ خمسين صفًّا عبر الشاشة **بسطور الزمن** (EMS_TS_TIME_LINES)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/seed_unit_lines_50.php [--purge]
 *
 * النافذة 2027-03-01 → 2027-03-14 (معزولةٌ عن نافذة المطابقة 2027-02).
 * كلُّ صفٍّ يُرسَل عبر HTTP إلى الشاشة الحقيقية كما يفعل مديرُ الموقع:
 *
 *   • 38 صفًّا بالساعة **بسطور زمن** — الأعمدةُ التسعة تُرسل أصفارًا عمدًا
 *     ليثبت أن الخادمَ يشتقها من السطور (لا من POST).
 *   • 6 منها بسطر «وقود/لوجستيات» (fuel_logistics_stop) — الحالةُ التي
 *     **لا خانةَ قديمةً لها**: تهبط إلى «أعطال أخرى» في timesheet ويحفظها
 *     السجلُّ القانوني بدقتها. هذا برهانُ قيمة التاسك كلِّه.
 *   • 4 طن + 4 متر بسطورها.
 *   • 2 توقفٌ كامل (صفر عمل) بسطور توقفٍ خالصة.
 *   • 2 تجاوزُ طاقةٍ مقصود (11+11 للمعدة 24).
 *   • 6 صفوف **بالطراز القديم** (بلا سطور — أعمدةٌ مباشرة): برهانُ التعايش.
 *   المجموع 50 · لا تصادمَ مفاتيح (معدة×يوم×وردية) · لا مشغّلَ مرتين في يوم.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

const BASE     = 'http://localhost/ems';
const WIN_FROM = '2027-03-01';
const WIN_TO   = '2027-03-14';
const LOGIN_U  = 'محمد';
const LOGIN_P  = '12345678';

$JAR = sys_get_temp_dir() . '/ems_seed_lines_cookies.txt';
@unlink($JAR);

$env = array();
foreach (file(dirname(__DIR__) . '/.env') as $l) {
    if (preg_match('/^\s*([A-Z_]+)=(.*)$/', $l, $m)) { $env[$m[1]] = trim($m[2]); }
}
$db = new mysqli($env['DB_HOST'], 'root', '', $env['DB_NAME']);
if ($db->connect_error) { fwrite(STDERR, "conn: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

function req($url, $post = null, array $headers = array()) {
    global $JAR;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30,
    ));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, $body);
}

require_once __DIR__ . '/_fk_sweep.php';

function purge(mysqli $db) {
    /* تقريرُ الكنسِ يُجمَع مرةً ويُعلَن — والكنسُ يُقاس لا يُوعَد */
    $swept = array();
    $from = WIN_FROM; $to = WIN_TO;
    $ids = array();
    $r = $db->query("SELECT id FROM timesheet WHERE `date` BETWEEN '{$from}' AND '{$to}'");
    while ($x = $r->fetch_row()) { $ids[] = (int) $x[0]; }
    $mir = array();
    $r = $db->query("SELECT id FROM unit_entries WHERE entry_date BETWEEN '{$from}' AND '{$to}'");
    while ($x = $r->fetch_row()) { $mir[] = (int) $x[0]; }
    if ($mir) {
        $inM = implode(',', $mir);
        /* ◆ **الأبناءُ من القاعدةِ لا بيدٍ** — كانت هنا ثلاثةُ أسماءٍ مكتوبةٍ
             يدويًّا و`unit_match_overrides` غائبٌ، فمات الكنسُ في **منتصفه**
             بـ«Cannot delete or update a parent row» فبقيت النافذةُ نصفَ نظيفةٍ.
             وحذفُ حقيقةِ الأعمالِ نفسِه يجرُّ `fin_financial_events.root_event_id`
             — فالإسقاطُ الماليُّ يُكنَس قبل الحقيقة (ADR-15). انظر `_fk_sweep.php`. */
        ems_fk_delete_where($db, 'ems_business_events',
            "entity_type='unit_entry' AND entity_id IN ({$inM})", $swept);
        if (!ems_fk_delete($db, 'unit_entries', $mir, $swept)) {
            fwrite(STDERR, "✘ كنسُ المرايا لم يكتمل — أُوقف البذرَ صونًا للنافذة\n");
            exit(1);
        }
    }
    if ($ids) {
        $in = implode(',', $ids);
        ems_fk_delete_where($db, 'ems_business_events',
            "entity_type='timesheet' AND entity_id IN ({$in})", $swept);
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

// خريطة التشغيلات (نفس بذرة المطابقة — معداتٌ متمايزة)
$OPS = array(
    4  => array('eq' => 6,  'type' => 1), 5  => array('eq' => 7,  'type' => 1),
    8  => array('eq' => 4,  'type' => 1), 12 => array('eq' => 8,  'type' => 1),
    13 => array('eq' => 10, 'type' => 1), 18 => array('eq' => 18, 'type' => 1),
    32 => array('eq' => 5,  'type' => 1), 34 => array('eq' => 21, 'type' => 1),
    39 => array('eq' => 24, 'type' => 1), 40 => array('eq' => 25, 'type' => 1),
    41 => array('eq' => 26, 'type' => 1),
    2  => array('eq' => 3,  'type' => 2), 14 => array('eq' => 11, 'type' => 2),
    33 => array('eq' => 13, 'type' => 3),
);
$EMPS = array(3, 4, 5, 6, 7, 8, 9, 10, 11, 12);
$days = array();
for ($d = 0; $d < 14; $d++) { $days[] = date('Y-m-d', strtotime(WIN_FROM . " +{$d} days")); }

$mkLine = function ($h, $state, $resp, $note = '') {
    return array('hours' => $h, 'ops_state' => $state, 'resp_party' => $resp, 'cause_note' => $note);
};

// ═══ تأليف الخمسين ═══
$plan = array();   // [op, day, shift, type, lines|null, extra_post]
$hourOps = array(4, 5, 8, 12, 13, 18, 32, 34, 40, 41);

// ① 26 صفًّا بالساعة بسطورٍ متنوعة (6 قوالب تدور)
//    (26+6+4+4+2+2+6 = 50)
$lineProfiles = array(
    array($mkLine(9.0, 'actual_work', 'none'), $mkLine(1.0, 'standby', 'client', 'استعداد بطلب العميل')),
    array($mkLine(8.0, 'actual_work', 'none'), $mkLine(1.5, 'tech_breakdown', 'company', 'WO-6001'), $mkLine(0.5, 'standby', 'company', 'انتظار تعليمات')),
    array($mkLine(10.0, 'actual_work', 'none')),
    array($mkLine(7.0, 'actual_work', 'none'), $mkLine(2.0, 'standby', 'client'), $mkLine(1.0, 'operator_stop', 'operator', 'تأخر مشغّل')),
    array($mkLine(9.5, 'actual_work', 'none'), $mkLine(0.5, 'supplier_stop', 'supplier', 'تأخر مورد')),
    array($mkLine(6.0, 'actual_work', 'none'), $mkLine(1.0, 'standby', 'client'), $mkLine(3.0, 'planned_stop', 'planned', 'صيانة مجدولة')),
);
for ($n = 0; $n < 26; $n++) {
    $op = $hourOps[$n % count($hourOps)];
    $day = $days[intdiv($n, 4) % 14];
    $shift = ($n % 2 === 0) ? 'D' : 'N';
    $plan[] = array($op, $day, $shift, 1, $lineProfiles[$n % count($lineProfiles)], array());
}

// ② 6 صفوف فيها «وقود/لوجستيات» — الحالة بلا خانةٍ قديمة (برهان القيمة)
for ($n = 0; $n < 6; $n++) {
    $op = $hourOps[$n];
    $day = $days[8 + ($n % 3)];
    $shift = ($n % 2 === 0) ? 'N' : 'D';
    $plan[] = array($op, $day, $shift, 1, array(
        $mkLine(7.5, 'actual_work', 'none'),
        $mkLine(1.5, 'fuel_logistics_stop', 'company', 'انتظار صهريج الوقود'),
    ), array());
}

// ③ 4 طن + 4 متر بسطورها
$tonSpecs = array(
    array(110.0, 13), array(95.5, 11), array(130.25, 15), array(88.0, 10),
);
foreach ($tonSpecs as $k => $s) {
    $op = ($k % 2 === 0) ? 2 : 14;
    $plan[] = array($op, $days[$k], ($k % 2 === 0) ? 'D' : 'N', 2,
        array($mkLine(8.0 + $k * 0.5, 'actual_work', 'none'), $mkLine(1.0, 'standby', 'client')),
        array('tons_count' => $s[0], 'trips_count' => $s[1], 'transport_type' => 'خام'));
}
$mtrSpecs = array(array(11, 3.5), array(9, 4.0), array(14, 3.0), array(12, 3.25));
foreach ($mtrSpecs as $k => $s) {
    $plan[] = array(33, $days[$k + 4], ($k % 2 === 0) ? 'D' : 'N', 3,
        array($mkLine(8.5, 'actual_work', 'none'), $mkLine(0.5, 'tech_breakdown', 'company', 'WO-6100')),
        array('drilling_holes_count' => $s[0], 'drilling_depth' => $s[1],
              'meters_count' => round($s[0] * $s[1], 2), 'meters_type' => 'عمودي'));
}

// ④ توقفان كاملان — سطورُ توقفٍ خالصة
$plan[] = array(4, $days[12], 'N', 1, array(
    $mkLine(10.0, 'tech_breakdown', 'company', 'WO-6200 عطل محرك')), array());
$plan[] = array(5, $days[12], 'N', 1, array(
    $mkLine(6.0, 'supplier_stop', 'supplier', 'انتظار قطعة من المورد'),
    $mkLine(4.0, 'planned_stop', 'planned')), array());

// ⑤ تجاوزُ طاقةٍ مقصود — المعدة 24 (11+11=22 والحد 20)
$plan[] = array(39, $days[13], 'D', 1, array($mkLine(11.0, 'actual_work', 'none')), array());
$plan[] = array(39, $days[13], 'N', 1, array($mkLine(11.0, 'actual_work', 'none')), array());

// ⑥ 6 صفوف بالطراز القديم — بلا سطور (التعايش)
$legacyProfiles = array(
    array('executed_hours' => 8.0, 'standby_hours' => 2.0),
    array('executed_hours' => 9.0, 'maintenance_fault' => 1.0, 'total_fault_hours' => 1.0),
    array('executed_hours' => 10.0),
);
for ($n = 0; $n < 6; $n++) {
    $op = $hourOps[($n + 4) % count($hourOps)];
    $day = $days[11 - intdiv($n, 2)];
    $shift = ($n % 2 === 0) ? 'D' : 'N';
    $plan[] = array($op, $day, $shift, 1, null, $legacyProfiles[$n % 3]);
}

// ── حارسا التصميم ──
$seen = array();
foreach ($plan as $p) {
    $k = $OPS[$p[0]]['eq'] . '|' . $p[1] . '|' . $p[2];
    if (isset($seen[$k])) { fwrite(STDERR, "FATAL: تصادم مفتاح {$k}\n"); exit(1); }
    $seen[$k] = true;
}
if (count($plan) !== 50) { fwrite(STDERR, "FATAL: " . count($plan) . " لا 50\n"); exit(1); }

// إسناد المشغّلين — لا مشغّلَ مرتين في يوم
$byDay = array();
foreach ($plan as $k => $p) { $byDay[$p[1]][] = $k; }
foreach ($byDay as $day => $keys) {
    if (count($keys) > count($EMPS)) { fwrite(STDERR, "FATAL: يوم {$day} مزدحم\n"); exit(1); }
    foreach (array_values($keys) as $slot => $k) { $plan[$k]['emp'] = $EMPS[$slot]; }
}

// ═══ التنفيذ ═══
fwrite(STDOUT, "\n══ بذرةُ سطور الزمن — النافذة " . WIN_FROM . " → " . WIN_TO . " ══\n");
list($purged, $purgedMir) = purge($db);
if ($purged || $purgedMir) { fwrite(STDOUT, "كُنست بقايا: {$purged} صف · {$purgedMir} مرآة\n"); }

list(, $page) = req(BASE . '/login.php');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $page, $m);
list($code) = req(BASE . '/login.php', array('username' => LOGIN_U, 'password' => LOGIN_P, 'csrf_token' => $m[1] ?? ''));
if ($code !== 200) { fwrite(STDERR, "FATAL: فشل الدخول\n"); exit(1); }
list(, $page) = req(BASE . '/Timesheet/timesheet.php?type=1');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $page, $m);
$csrf = $m[1] ?? '';

$okN = 0; $failN = 0; $capacityMsgs = 0;
foreach ($plan as $idx => $p) {
    list($op, $day, $shift, $type, $lines, $extra) = array($p[0], $p[1], $p[2], $p[3], $p[4], $p[5]);
    $emp = $p['emp'];
    $row = array(
        'operator' => (string) $op, 'employee_id' => (string) $emp,
        'shift' => $shift, 'date' => $day, 'type' => (string) $type, 'user_id' => '1',
        'csrf_token' => $csrf, 'general_notes' => 'LINES50',
        // الأعمدة الزمنية أصفارًا عمدًا حين تُرسل سطور — الخادم يشتقها
        'executed_hours' => '0', 'standby_hours' => '0', 'shift_hours' => '0',
        'total_work_hours' => '0', 'operator_hours' => '0', 'total_fault_hours' => '0',
        'maintenance_fault' => '0', 'hr_fault' => '0', 'marketing_fault' => '0',
        'ts_supplier_stop_hours' => '0', 'ts_planned_stop_hours' => '0',
        'ts_force_majeure_hours' => '0', 'other_fault_hours' => '0', 'dependence_hours' => '0',
        'tons_count' => '0', 'trips_count' => '0', 'meters_count' => '0',
        'drilling_holes_count' => '0', 'drilling_depth' => '0',
    );
    foreach ($extra as $k => $v) { $row[$k] = (string) $v; }
    if (is_array($lines)) {
        $row['ts_time_lines_json'] = json_encode($lines, JSON_UNESCAPED_UNICODE);
        $row['general_notes'] = 'LINES50-L';
    } else {
        // الطراز القديم: الأعمدة مباشرة والمجاميع متسقة
        $ex = (float) ($extra['executed_hours'] ?? 0);
        $sb = (float) ($extra['standby_hours'] ?? 0);
        $ft = (float) ($extra['total_fault_hours'] ?? 0);
        $row['total_work_hours'] = (string) ($ex + $sb);
        $row['shift_hours'] = (string) ($ex + $sb + $ft);
        $row['operator_hours'] = (string) $ex;
        $row['general_notes'] = 'LINES50-OLD';
    }
    list($c, $b) = req(BASE . '/Timesheet/timesheet.php?type=' . $type, $row);
    if ($c === 200 && (strpos($b, 'تم الحفظ بنجاح') !== false || strpos($b, 'تجاوز طاقة') !== false)) {
        $okN++;
        if (strpos($b, 'تجاوز طاقة') !== false) { $capacityMsgs++; }
    } else {
        $failN++;
        preg_match("/alert\('([^']{0,120})/", $b, $em);
        fwrite(STDOUT, "  ✘ #" . ($idx + 1) . " op={$op} {$day} {$shift}: " . ($em[1] ?? "HTTP {$c}") . "\n");
    }
}

$inWin = (int) $db->query("SELECT COUNT(*) c FROM timesheet WHERE `date` BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'")->fetch_assoc()['c'];
$mirN  = (int) $db->query("SELECT COUNT(*) c FROM unit_entries WHERE entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'")->fetch_assoc()['c'];
fwrite(STDOUT, "\nحُفظ {$okN} · فشل {$failN} · رسائلُ تجاوز طاقةٍ فورية: {$capacityMsgs}\n");
fwrite(STDOUT, "في النافذة: {$inWin} صفًّا · {$mirN} مرآة\n");
fwrite(STDOUT, "\nالاختبار:  php tests/timesheet_time_lines_test.php\n");
exit($failN === 0 ? 0 : 1);
