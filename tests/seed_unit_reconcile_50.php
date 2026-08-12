<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * §9-③ خطوة ③ — بذرةُ خمسين صفًّا نظيفًا للمطابقة (قرار المالك 2026-07-26)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/seed_unit_reconcile_50.php [--purge]
 *
 * «كلُّ الداتا حتى الآن تجريبية — أضِف خمسين صفًّا جديدًا وطابقهم، ولا تعتمد
 *  البيانات القديمة» (المالك). فهذه البذرةُ تبني مجموعةً مقصودةَ التصميم في
 *  نافذةٍ زمنيةٍ معزولةٍ لا تلامس صفًّا قائمًا، والمطابقةُ تقع عليها **وحدها**.
 *
 * الكتابة عبر الشاشة الحقيقية (HTTP POST إلى Timesheet/timesheet.php) لا عبر
 * إدراجٍ مباشر: فخطّافُ الكتابة المزدوجة يعمل طبيعيًّا كما يعمل لمستخدمٍ حقيقي،
 * ولا يُختبَر مسارٌ غيرُ المسار الذي سيُستعمل.
 *
 * تصميم المجموعة (مقصودٌ لا عشوائي — كلُّ فئةٍ تختبر قاعدةً):
 *   • 34 صفًّا بالساعة  — الحالة الغالبة، بتوزيع زمنٍ حقيقيٍّ على حالاته
 *   •  6 صفوفٍ بالطن    — وحدةُ فوترةٍ غيرُ ساعية (نقل)
 *   •  6 صفوفٍ بالمتر   — وحدةُ فوترةٍ غيرُ ساعية (ثقب)
 *   •  2 صفٌّ بلا إنتاج  — يومُ توقفٍ كامل (الكمية صفر والزمنُ كلُّه أعطال)
 *   •  2 صفٌّ متجاوزُ طاقة — معدةٌ واحدةٌ بورديتين 11+11 = 22 ساعة (الحدّ 20)
 *   المجموع 50. ولا تكرارَ على (معدة × تاريخ × وردية) — نظيفةٌ بالتصميم.
 *
 * ⚠️ لا تمسّ الصفوف الـ221 القديمة بحرف. و`--purge` يمحو ما بذرَته هي فقط
 *    (بالنافذة الزمنية) فتُعاد بلا بقايا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

const BASE      = 'http://localhost/ems';
const WIN_FROM  = '2027-02-01';
const WIN_TO    = '2027-02-14';
const LOGIN_U   = 'محمد';
const LOGIN_P   = '12345678';

$JAR = sys_get_temp_dir() . '/ems_seed50_cookies.txt';
@unlink($JAR);

// ── اتصال قراءةٍ/تنظيفٍ مباشر (خارج التطبيق) ──
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

// ═══ الكنس — النافذة وحدها ═══
function purge(mysqli $db) {
    /* تقريرُ الكنسِ يُجمَع مرةً ويُعلَن — والكنسُ يُقاس لا يُوعَد */
    $swept = array();
    $from = WIN_FROM; $to = WIN_TO;
    $ids = array();
    $r = $db->query("SELECT id FROM timesheet WHERE `date` BETWEEN '{$from}' AND '{$to}'");
    while ($x = $r->fetch_row()) { $ids[] = (int) $x[0]; }
    $mir = array();
    if ($ids) {
        $in = implode(',', $ids);
        $r = $db->query("SELECT id FROM unit_entries WHERE sync_uuid IN
                          (SELECT CONCAT('ts:', id) FROM timesheet WHERE id IN ({$in}))");
        while ($x = $r->fetch_row()) { $mir[] = (int) $x[0]; }
    }
    // مرايا النافذة ولو انقطع نسبُها (entry_date داخل النافذة)
    $r = $db->query("SELECT id FROM unit_entries WHERE entry_date BETWEEN '{$from}' AND '{$to}'");
    while ($x = $r->fetch_row()) { $mir[] = (int) $x[0]; }
    $mir = array_values(array_unique($mir));

    if ($mir) {
        $inM = implode(',', $mir);
        /* ═══════════════════════════════════════════════════════════════════
         * ◆ **الأبناءُ يُشتقّون من القاعدةِ لا يُسمَّون بيدٍ.**
         * كان هنا ثلاثةُ أسماءٍ مكتوبةٍ يدويًّا و`unit_match_overrides` **غائبٌ**
         * — فحين أنشأ `unit_reconcile_test` صفوفَ تجاوزٍ صار هذا الكنسُ يموت
         * بـ«Cannot delete or update a parent row» في **منتصفِ** التنظيف، فتبقى
         * النافذةُ نصفَ نظيفةٍ وتتضاعف عدّاداتُ الفاحص (1⇒3 · 2⇒3 · 3⇒6).
         * وهو فخٌّ **دائريّ**: الفاحصُ يُنشئ ما يمنع بذرتَه من التنظيف، فكلُّ
         * جولةٍ تُفسد التي بعدها. ولا يُحَلُّ بإضافةِ اسمٍ رابعٍ — لأنَّ خامسًا
         * سيُضاف غدًا فيعود العطبُ صامتًا.
         * ═══════════════════════════════════════════════════════════════════ */
        ems_fk_delete_where($db, 'ems_business_events',
            "entity_type='unit_entry' AND entity_id IN ({$inM})", $swept);
        $swept = array();
        if (!ems_fk_delete($db, 'unit_entries', $mir, $swept)) {
            fwrite(STDERR, "✘ كنسُ المرايا لم يكتمل — أُوقف البذرَ صونًا للنافذة\n");
            exit(1);
        }
        fwrite(STDOUT, '  · كُنست المرايا وذريّتُها: ' . ems_fk_sweep_report($swept) . "\n");
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

// ═══ خريطة صفوف التشغيل — معداتٌ متمايزة (المفتاح على المعدة لا التشغيل) ═══
// op => [equipment, project, contract, supplier, screen_type]
$OPS = array(
    4  => array('eq' => 6,  'proj' => 2,  'type' => 1),
    5  => array('eq' => 7,  'proj' => 2,  'type' => 1),
    8  => array('eq' => 4,  'proj' => 2,  'type' => 1),
    12 => array('eq' => 8,  'proj' => 4,  'type' => 1),
    13 => array('eq' => 10, 'proj' => 4,  'type' => 1),
    18 => array('eq' => 18, 'proj' => 4,  'type' => 1),
    32 => array('eq' => 5,  'proj' => 4,  'type' => 1),
    34 => array('eq' => 21, 'proj' => 4,  'type' => 1),
    39 => array('eq' => 24, 'proj' => 10, 'type' => 1),
    40 => array('eq' => 25, 'proj' => 10, 'type' => 1),
    41 => array('eq' => 26, 'proj' => 10, 'type' => 1),
    2  => array('eq' => 3,  'proj' => 1,  'type' => 2),  // نقل — طن
    14 => array('eq' => 11, 'proj' => 4,  'type' => 2),  // نقل — طن
    33 => array('eq' => 13, 'proj' => 4,  'type' => 3),  // ثقب — متر
);
$EMPS = array(3, 4, 5, 6, 7, 8, 9, 10, 11, 12);

/**
 * بناءُ صفٍّ واحد. التوزيعُ الزمنيُّ محسوبٌ لا مخترَع: مجموعُ حقول الأعطال
 * يساوي total_fault_hours بالضبط (شرطُ الشاشة)، والوردية = عمل+استعداد+أعطال.
 */
function mkRow($op, $eq, $day, $shift, $type, $empId, array $spec) {
    $work    = isset($spec['work'])    ? (float) $spec['work']    : 0.0;
    $standby = isset($spec['standby']) ? (float) $spec['standby'] : 0.0;
    $mnt     = isset($spec['mnt'])     ? (float) $spec['mnt']     : 0.0;  // عطل صيانة
    $hr      = isset($spec['hr'])      ? (float) $spec['hr']      : 0.0;  // عطل مشغّل
    $sup     = isset($spec['sup'])     ? (float) $spec['sup']     : 0.0;  // توقف مورد
    $plan    = isset($spec['plan'])    ? (float) $spec['plan']    : 0.0;  // توقف مخطط
    $faults  = round($mnt + $hr + $sup + $plan, 2);
    $shiftH  = round($work + $standby + $faults, 2);

    $row = array(
        'operator'    => (string) $op,
        'employee_id' => (string) $empId,
        'shift'       => $shift,
        'date'        => $day,
        'type'        => (string) $type,
        'user_id'     => '1',
        'shift_hours'      => (string) $shiftH,
        'executed_hours'   => (string) $work,
        'standby_hours'    => (string) $standby,
        'total_work_hours' => (string) round($work + $standby, 2),
        'operator_hours'   => (string) $work,
        'maintenance_fault'   => (string) $mnt,
        'hr_fault'            => (string) $hr,
        'ts_supplier_stop_hours' => (string) $sup,
        'ts_planned_stop_hours'  => (string) $plan,
        'total_fault_hours'   => (string) $faults,
        'marketing_fault' => '0', 'approval_fault' => '0', 'other_fault_hours' => '0',
        'ts_force_majeure_hours' => '0', 'dependence_hours' => '0',
        'tons_count' => '0', 'trips_count' => '0', 'meters_count' => '0',
        'drilling_holes_count' => '0', 'drilling_depth' => '0',
        'general_notes' => 'RECON50',
    );
    if (isset($spec['tons'])) {
        $row['tons_count']     = (string) $spec['tons'];
        $row['trips_count']    = (string) $spec['trips'];
        $row['transport_type'] = 'خام';
    }
    // ⚠️ المفتاح holes لا meters: الأمتار محسوبةٌ (ثقوب × عمق) لا مُدخَلة —
    //    الشاشة تحسبها بجافاسكربت وحقلُها readonly، فتُرسَل محسوبةً من هنا.
    if (isset($spec['holes'])) {
        $row['drilling_holes_count'] = (string) $spec['holes'];
        $row['drilling_depth']       = (string) $spec['depth'];
        $row['meters_count']         = (string) round($spec['holes'] * $spec['depth'], 2);
        $row['meters_type']          = 'عمودي';
    }
    return $row;
}

// ═══ تأليف الخمسين ═══
$plan = array();
$hourOps = array(4, 5, 8, 12, 13, 18, 32, 34, 39, 40, 41);
$days = array();
for ($d = 0; $d < 14; $d++) { $days[] = date('Y-m-d', strtotime(WIN_FROM . " +{$d} days")); }

// ① 34 صفًّا بالساعة — توزيعٌ حتميٌّ (لا عشوائية: البذرةُ تُعاد فتُعطي المطابق)
$profiles = array(
    array('work' => 9.0, 'standby' => 1.0),
    array('work' => 8.0, 'standby' => 0.5, 'mnt' => 1.5),
    array('work' => 10.0),
    array('work' => 7.0, 'standby' => 2.0, 'hr' => 1.0),
    array('work' => 9.5, 'sup' => 0.5),
    array('work' => 6.0, 'standby' => 1.0, 'plan' => 3.0),
);
for ($n = 0; $n < 34; $n++) {
    $op = $hourOps[$n % count($hourOps)];
    $day = $days[intdiv($n, 4) % 14];
    $shift = ($n % 2 === 0) ? 'D' : 'N';
    $plan[] = array($op, $day, $shift, 1, null, $profiles[$n % count($profiles)]);
}

// ② 6 صفوفٍ بالطن (نقل)
$tonSpecs = array(
    array('work' => 9.0, 'tons' => 120.5, 'trips' => 14),
    array('work' => 8.0, 'standby' => 1.0, 'tons' => 96.0,  'trips' => 11),
    array('work' => 10.0, 'tons' => 140.0, 'trips' => 16),
    array('work' => 7.5, 'mnt' => 1.5, 'tons' => 88.25, 'trips' => 10),
    array('work' => 9.0, 'tons' => 115.0, 'trips' => 13),
    array('work' => 8.5, 'standby' => 0.5, 'tons' => 102.75, 'trips' => 12),
);
foreach ($tonSpecs as $k => $s) {
    $op = ($k % 2 === 0) ? 2 : 14;
    $plan[] = array($op, $days[$k], ($k % 2 === 0) ? 'D' : 'N', 2, null, $s);
}

// ③ 6 صفوفٍ بالمتر (ثقب) — المعدة 13 وحدها، فتتوزع على أيامٍ متمايزة
$mtrSpecs = array(
    array('work' => 9.0, 'holes' => 12, 'depth' => 3.5),
    array('work' => 8.0, 'standby' => 1.0, 'holes' => 10, 'depth' => 4.0),
    array('work' => 10.0, 'holes' => 15, 'depth' => 3.0),
    array('work' => 7.0, 'mnt' => 2.0, 'holes' => 9,  'depth' => 3.5),
    array('work' => 9.5, 'holes' => 13, 'depth' => 3.25),
    array('work' => 8.5, 'sup' => 1.0, 'holes' => 11, 'depth' => 3.75),
);
foreach ($mtrSpecs as $k => $s) {
    $plan[] = array(33, $days[$k], ($k % 2 === 0) ? 'D' : 'N', 3, null, $s);
}

// ④ صفّان بلا إنتاج — يومُ توقفٍ كامل (الكمية صفر والزمنُ كلُّه عطل)
$plan[] = array(4,  $days[12], 'N', 1, null, array('work' => 0.0, 'mnt' => 10.0));
$plan[] = array(5,  $days[12], 'N', 1, null, array('work' => 0.0, 'sup' => 8.0, 'plan' => 2.0));

// ⑤ صفّان يصنعان تجاوزَ طاقةٍ مقصودًا — المعدة 24 يومًا واحدًا بورديتين 11+11=22 (الحد 20)
// ملاحظةٌ بنيوية مكتشفة بالقياس: بحدَّي §3.10 (معدة 20 · مشغّل 10) وورديتين
// اثنتين لا غير، **يستحيل تجاوزُ المعدة دون تجاوز مشغّلٍ معها** — إذ يلزم
// أكثرُ من عشر ساعاتٍ في وردية. فالمتوقَّعُ ثلاثةُ أعلام: معدةٌ ومشغّلان.
$plan[] = array(39, $days[13], 'D', 1, null, array('work' => 11.0));
$plan[] = array(39, $days[13], 'N', 1, null, array('work' => 11.0));

// ── إسنادُ المشغّلين: مشغّلٌ واحدٌ لا يعمل معدتين في اليوم نفسه ──
// (الإسنادُ العشوائيُّ السابق ولّد خمسةَ تجاوزاتٍ عرَضيةٍ على محور المشغّل —
//  التقطها الحارسُ بحقّ، وكانت عيبَ بذرةٍ لا عيبَ نظام.)
$byDay = array();
foreach ($plan as $k => $p) { $byDay[$p[1]][] = $k; }
foreach ($byDay as $day => $keys) {
    if (count($keys) > count($EMPS)) {
        fwrite(STDERR, "FATAL: يوم {$day} فيه " . count($keys) . " صفًّا وعندنا " . count($EMPS) . " مشغّلين\n");
        exit(1);
    }
    foreach (array_values($keys) as $slot => $k) { $plan[$k][4] = $EMPS[$slot]; }
}

// حارسُ الإسناد: لا مشغّلَ مرتين في يوم
$opSeen = array();
foreach ($plan as $p) {
    $k = $p[4] . '@' . $p[1];
    if (isset($opSeen[$k])) { fwrite(STDERR, "FATAL: المشغّل {$p[4]} مُسنَدٌ مرتين يوم {$p[1]}\n"); exit(1); }
    $opSeen[$k] = true;
}

// ── حارسُ التصميم: لا تكرارَ على (معدة × تاريخ × وردية) ──
$seen = array();
foreach ($plan as $p) {
    $k = $OPS[$p[0]]['eq'] . '|' . $p[1] . '|' . $p[2];
    if (isset($seen[$k]) && !($p[0] === 39 && $p[1] === $days[13])) {
        fwrite(STDERR, "FATAL: تصميمٌ مكرر على المفتاح {$k}\n"); exit(1);
    }
    $seen[$k] = true;
}
if (count($plan) !== 50) {
    fwrite(STDERR, "FATAL: المخطط " . count($plan) . " صفًّا لا 50\n"); exit(1);
}

// ═══ التنفيذ ═══
fwrite(STDOUT, "\n══ بذرةُ الخمسين — النافذة " . WIN_FROM . " → " . WIN_TO . " ══\n");
list($tsBefore) = array((int) $db->query("SELECT COUNT(*) c FROM timesheet")->fetch_assoc()['c']);
list($purged, $purgedMir) = purge($db);
if ($purged || $purgedMir) { fwrite(STDOUT, "كُنست بقايا سابقة: {$purged} صف · {$purgedMir} مرآة\n"); }

// الدخول
list($code, $page) = req(BASE . '/login.php');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $page, $m);
$csrf = isset($m[1]) ? $m[1] : '';
list($code, $body) = req(BASE . '/login.php', array('username' => LOGIN_U, 'password' => LOGIN_P, 'csrf_token' => $csrf));
if ($code !== 200) { fwrite(STDERR, "FATAL: فشل الدخول ({$code})\n"); exit(1); }

// رمز الشاشة
list($code, $page) = req(BASE . '/Timesheet/timesheet.php?type=1');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $page, $m);
$csrfTs = isset($m[1]) ? $m[1] : $csrf;

$okN = 0; $failN = 0; $capN = 0;
foreach ($plan as $idx => $p) {
    list($op, $day, $shift, $type, $emp, $spec) = $p;
    $row = mkRow($op, $OPS[$op]['eq'], $day, $shift, $type, $emp, $spec);
    $row['csrf_token'] = $csrfTs;
    list($c, $b) = req(BASE . '/Timesheet/timesheet.php?type=' . $type, $row);
    /* ◆ **تجاوزُ الطاقةِ نجاحٌ مقصودٌ لا فشل.** المخططُ يصنع عمدًا زوجَ ورديّاتٍ
         يتجاوز الحدَّ اليوميَّ (11+11=22 والحدُّ 20) لأن `unit_reconcile_test`
         يشترط وجودَه نصًّا: «المعدة 24 يوم 2027-02-14 … واقعتاها معلَّمتان».
         والشاشةُ تحفظ الصفَّ **وتُنذر**، فرسالتُها «حُفظ مع تجاوز طاقة» —
         وكان يُعدُّ فشلًا فتخرج البذرةُ بـ1 مع أن بيانتَها تامّةٌ (50 صفًّا
         و50 مرآة)، فيُقرأ رمزُ خروجِها عطلًا وهو حكمٌ يعمل. */
    $saved   = ($c === 200 && strpos($b, 'تم الحفظ بنجاح') !== false);
    $savedCap = ($c === 200 && strpos($b, 'حُفظ مع تجاوز طاقة') !== false);
    if ($saved || $savedCap) {
        $okN++;
        if ($savedCap) {
            $capN++;
            fwrite(STDOUT, "  ◆ صف #" . ($idx + 1) . " op={$op} {$day} {$shift}: حُفظ **بتجاوزٍ مقصود** — يقيسه الفاحص\n");
        }
    } else {
        $failN++;
        preg_match("/alert\('([^']{0,120})/", $b, $em);
        fwrite(STDOUT, "  ✘ صف #" . ($idx + 1) . " op={$op} {$day} {$shift}: " . (isset($em[1]) ? $em[1] : "HTTP {$c}") . "\n");
    }
}

$tsAfter = (int) $db->query("SELECT COUNT(*) c FROM timesheet")->fetch_assoc()['c'];
$inWin = (int) $db->query("SELECT COUNT(*) c FROM timesheet WHERE `date` BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'")->fetch_assoc()['c'];
$mirN  = (int) $db->query("SELECT COUNT(*) c FROM unit_entries WHERE entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'")->fetch_assoc()['c'];

fwrite(STDOUT, "\nحُفظ {$okN} (منها {$capN} بتجاوزٍ مقصود) · فشل {$failN}\n");
fwrite(STDOUT, "timesheet: {$tsBefore} → {$tsAfter} (في النافذة {$inWin})\n");
fwrite(STDOUT, "المرايا في unit_entries: {$mirN}\n");
fwrite(STDOUT, "\nالمطابقة:  php tests/unit_reconcile_test.php\n");
exit($failN === 0 ? 0 : 1);
