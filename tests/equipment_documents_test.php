<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-10 §8.1 — اختبار قبول وثائق المعدة والمشغّل وتنبيهاتها
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/equipment_documents_test.php
 *
 * ما يُثبته:
 *   ① الترحيل صفًّا أولًا: 39 وثيقةً من المصدرين بعطالة (إعادةُ up لا تكرّر).
 *   ② بذرُ 50 وثيقةً جديدةً **عبر الشاشة HTTP** (قاعدة المالك: قِس على الجديد) —
 *      متنوعةً: منتهية · توشك · سارية · بلا انتهاء، للمعدات والمشغّلين.
 *   ③ التصنيفُ بالحقيقة: «الوضع الفعلي» يُحسب من expiry_date لا من الحالة.
 *   ④ القيدُ الفريد يمنع الوثيقةَ المكررة.
 *   ⑤ التحذيرُ حيّ: قائمةُ السائقين توسم المنتهيةَ رخصتُهم · ورسالةُ حفظ
 *      التايم شيت تحمل التنبيه **والحفظُ يمرّ** (قرار المالك: تحذيرٌ لا منع).
 *   ⑥ مؤشّرا لوحة الأسطول (3) يقرآن أرقامًا صادقة — والفاسدُ القديم
 *      (meter_readings الغائب) لم يعد.
 *   ⑦ العزل: شركةٌ أخرى لا ترى وثائقَ الشركة 4.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

const BASE = 'http://localhost/ems';
const SEED_MARK = 'DOC50';

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

$JAR = sys_get_temp_dir() . '/ems_docs_test.txt';
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

// عطالة الحزمة: كنسُ بذرتها هي فقط (المرحَّل يبقى)
$db->query("DELETE FROM equipment_documents WHERE note LIKE '" . SEED_MARK . "%'");
register_shutdown_function(function () use ($db) {
    $db->query("DELETE FROM equipment_documents WHERE note LIKE '" . SEED_MARK . "%'");
});

fwrite(STDOUT, "\n══ UX-10 §8.1 — وثائقُ المعدة والمشغّل وتنبيهاتُها ══\n");

// ═══ ① الترحيل ═══
head('① الترحيل صفًّا أولًا — من المصدرين وبعطالة');
$mig = $db->query("SELECT migrated_from, COUNT(*) n FROM equipment_documents
    WHERE migrated_from IS NOT NULL GROUP BY migrated_from")->fetch_all(MYSQLI_ASSOC);
$m = array();
foreach ($mig as $x) { $m[$x['migrated_from']] = (int) $x['n']; }
check(($m['equipments.license'] ?? 0) === 13, 'رخصُ المعدات: ' . ($m['equipments.license'] ?? 0) . '/13');
check(($m['equipment_operators.license'] ?? 0) === 26, 'رخصُ المشغّلين: ' . ($m['equipment_operators.license'] ?? 0) . '/26');
check(($m['employees.identity'] ?? 0) === 26, 'هوياتُ الموظفين: ' . ($m['employees.identity'] ?? 0) . '/26');
$expiredMig = (int) $db->query("SELECT COUNT(*) c FROM equipment_documents
    WHERE migrated_from IS NOT NULL AND status='منتهية'")->fetch_assoc()['c'];
check($expiredMig === 63, "الموسومةُ منتهيةً عند الترحيل: {$expiredMig}/63 — الأزمةُ المقيسة صارت مرئية");
// النسخةُ لا تُرحَّل مرتين: employees.license_expiry_date مطابقٌ لسجل التأهيل 26/26
$dupCheck = (int) $db->query("SELECT COUNT(*) c FROM employees e
    JOIN equipment_operators eo ON eo.employee_id = e.id
    WHERE e.company_id=4 AND e.license_expiry_date IS NOT NULL AND eo.license_expiry_date IS NOT NULL
      AND e.license_expiry_date <> eo.license_expiry_date")->fetch_assoc()['c'];
check($dupCheck === 0, 'employees.license_expiry_date نسخةٌ مطابقةٌ لسجل التأهيل — فلم تُرحَّل مرتين');

// ═══ ② بذر 50 عبر الشاشة ═══
head('② بذرُ 50 وثيقةً جديدةً عبر الشاشة الحقيقية (لا اعتمادَ على المرحَّل)');
check(login('يسن'), 'دخول «يسن» (الأسطول 3 — مالك الشاشة)');
list($c, $pg) = req(BASE . '/Equipments/equipment_documents.php');
check($c === 200 && strpos($pg, 'وثائق المعدات والمشغّلين') !== false, "الشاشة تصيّر (HTTP {$c})");
check(strpos($pg, 'وثيقة جديدة') !== false, 'نموذجُ الإضافة ظاهرٌ لمالك الشاشة');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $pg, $mm);
$csrf = $mm[1] ?? '';

// المواد: معداتٌ ومشغّلون حقيقيون
$eqIds = array();
$r = $db->query("SELECT id FROM equipments WHERE company_id=4 ORDER BY id LIMIT 10");
while ($x = $r->fetch_row()) { $eqIds[] = (int) $x[0]; }
$empIds = array();
$r = $db->query("SELECT id FROM employees WHERE company_id=4 AND status=1 ORDER BY id LIMIT 10");
while ($x = $r->fetch_row()) { $empIds[] = (int) $x[0]; }

$today = date('Y-m-d');
$mkDate = function ($offsetDays) { return date('Y-m-d', strtotime('+' . $offsetDays . ' days')); };
// 50 = 20 منتهية + 12 توشك (داخل مهلتها) + 14 سارية بعيدة + 4 بلا انتهاء
$plan = array();
for ($i = 0; $i < 50; $i++) {
    $isOp = ($i % 2 === 1);
    $subject = $isOp ? $empIds[$i % count($empIds)] : $eqIds[$i % count($eqIds)];
    if ($i < 20)      { $exp = $mkDate(-30 - $i); $alert = 30; }      // منتهية
    elseif ($i < 32)  { $exp = $mkDate(5 + ($i % 20)); $alert = 30; } // توشك (≤30 يومًا)
    elseif ($i < 46)  { $exp = $mkDate(120 + $i); $alert = 30; }      // سارية بعيدة
    else              { $exp = ''; $alert = 30; }                      // بلا انتهاء
    $types = array('استمارة', 'تأمين', 'فحص دوري', 'تصريح');
    $plan[] = array(
        'subject_type' => $isOp ? 'operator' : 'equipment',
        'subject_id' => (string) $subject,
        'doc_type' => $isOp ? 'رخصة قيادة' : $types[$i % 4],
        'doc_no' => 'DOC-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
        'expiry_date' => $exp, 'alert_days' => (string) $alert,
        'status' => 'سارية', 'note' => SEED_MARK . ' وثيقة اختبار ' . ($i + 1),
        'add_doc' => '1', 'csrf_token' => $csrf,
    );
}
$okN = 0;
foreach ($plan as $p) {
    list($c, $b) = req(BASE . '/Equipments/equipment_documents.php', $p);
    if ($c === 200) { $okN++; }
}
$seeded = (int) $db->query("SELECT COUNT(*) c FROM equipment_documents WHERE note LIKE '" . SEED_MARK . "%'")->fetch_assoc()['c'];
check($seeded === 50, "حُفظت 50/50 عبر الشاشة: {$seeded}");

// ═══ ③ التصنيف بالحقيقة ═══
head('③ الوضعُ الفعلي يُحسب من التاريخ لا من عمود الحالة');
$cats = $db->query("SELECT
    SUM(expiry_date IS NOT NULL AND expiry_date < '{$today}') expired,
    SUM(expiry_date >= '{$today}' AND expiry_date <= DATE_ADD('{$today}', INTERVAL alert_days DAY)) soon,
    SUM(expiry_date > DATE_ADD('{$today}', INTERVAL alert_days DAY)) ok_far,
    SUM(expiry_date IS NULL) noend
    FROM equipment_documents WHERE note LIKE '" . SEED_MARK . "%'")->fetch_assoc();
check((int) $cats['expired'] === 20 && (int) $cats['soon'] === 12
   && (int) $cats['ok_far'] === 14 && (int) $cats['noend'] === 4,
    "التوزيع كما صُمّم: منتهية {$cats['expired']}/20 · توشك {$cats['soon']}/12 · سارية {$cats['ok_far']}/14 · بلا انتهاء {$cats['noend']}/4");
// كلُّها أُدخلت بحالة «سارية» — فالفعليُّ من التاريخ وحده (البرهان أعلاه)
$allSariya = (int) $db->query("SELECT COUNT(*) c FROM equipment_documents
    WHERE note LIKE '" . SEED_MARK . "%' AND status='سارية'")->fetch_assoc()['c'];
check($allSariya === 50, 'وكلُّها بحالةٍ إدارية «سارية» — التصنيفُ الفعلي من التاريخ لا منها');

// والشاشة تعرضها كذلك
list(, $pg2) = req(BASE . '/Equipments/equipment_documents.php');
check(strpos($pg2, 'منتهية منذ') !== false && strpos($pg2, 'توشك —') !== false,
    'الشاشة تعرض «منتهية منذ …» و«توشك — …» من الحقيقة');

// ═══ ④ القيد الفريد ═══
head('④ الوثيقةُ المكررة تُرفض');
$dup = $plan[0]; // نفسُ الصاحب والنوع والرقم
list($c, $b) = req(BASE . '/Equipments/equipment_documents.php', $dup);
$still = (int) $db->query("SELECT COUNT(*) c FROM equipment_documents WHERE note LIKE '" . SEED_MARK . "%'")->fetch_assoc()['c'];
check($still === 50, "التكرار لم يكتب صفًّا: {$still}/50");

// ═══ ⑤ التحذير حيّ في التايم شيت ═══
head('⑤ التحذير: القائمةُ توسم — والحفظُ يمرّ (قرار المالك)');
check(login('محمد'), 'دخول «محمد» (إدارة التشغيل)');
// قائمة السائقين لتشغيلٍ سائقُه منتهي الرخصة (المرحَّلة تكفي: 25 منتهية)
list($c, $dr) = req(BASE . '/Timesheet/get_drivers.php?operation_id=4&shift=D', null,
    array('X-Requested-With: XMLHttpRequest', 'Referer: ' . BASE . '/Timesheet/timesheet.php?type=1'));
check(strpos($dr, 'رخصة منتهية') !== false, 'قائمةُ السائقين توسم «⚠ رخصة منتهية» بتاريخها');

// حفظُ يومٍ لمشغّلٍ منتهي الرخصة — يمرّ برسالةٍ فيها التنبيه
list(, $pg3) = req(BASE . '/Timesheet/timesheet.php?type=1');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $pg3, $m3);
$db->query("DELETE FROM unit_entries WHERE sync_uuid IN (SELECT CONCAT('ts:', id) FROM timesheet WHERE `date`='2027-06-01')");
$db->query("DELETE FROM unit_time_log WHERE log_date='2027-06-01'");
$db->query("DELETE FROM timesheet WHERE `date`='2027-06-01'");
$post = array(
    'operator' => '4', 'employee_id' => '3', 'shift' => 'D', 'date' => '2027-06-01',
    'type' => '1', 'user_id' => '1', 'csrf_token' => $m3[1] ?? '', 'general_notes' => SEED_MARK,
    'ts_time_lines_json' => json_encode(array(array('hours' => 8, 'ops_state' => 'actual_work', 'resp_party' => 'none'))),
    'executed_hours' => '0', 'standby_hours' => '0', 'shift_hours' => '0', 'total_work_hours' => '0',
    'operator_hours' => '0', 'total_fault_hours' => '0', 'maintenance_fault' => '0', 'hr_fault' => '0',
    'marketing_fault' => '0', 'ts_supplier_stop_hours' => '0', 'ts_planned_stop_hours' => '0',
    'ts_force_majeure_hours' => '0', 'other_fault_hours' => '0', 'dependence_hours' => '0',
    'tons_count' => '0', 'trips_count' => '0', 'meters_count' => '0',
    'drilling_holes_count' => '0', 'drilling_depth' => '0',
);
list($c, $b) = req(BASE . '/Timesheet/timesheet.php?type=1', $post);
check(strpos($b, 'تم الحفظ بنجاح') !== false, 'الحفظُ مرّ (تحذيرٌ لا منع)');
check(strpos($b, 'رخصةُ قيادة هذا المشغّل منتهية') !== false, 'ورسالتُه تحمل تنبيهَ الرخصة المنتهية بتاريخها');
$savedRow = $db->query("SELECT t.id FROM timesheet t WHERE t.`date`='2027-06-01'")->fetch_assoc();
check($savedRow !== null, 'والصفُّ محفوظٌ فعلًا في القاعدة');

// ═══ ⑥ مؤشرا اللوحة ═══
head('⑥ لوحةُ الأسطول (3): المؤشران صادقان والفاسدُ القديم زال');
check(login('يسن'), 'دخول «يسن» (الأسطول)');
list($c, $board) = req(BASE . '/main/role_board.php');
$srcExpired = (int) $db->query("SELECT COUNT(*) c FROM equipment_documents
    WHERE company_id=4 AND COALESCE(is_deleted,0)=0 AND status<>'ملغاة'
      AND expiry_date IS NOT NULL AND expiry_date < CURDATE()")->fetch_assoc()['c'];
check($c === 200 && strpos($board, 'وثائقُ منتهيةٌ') !== false,
    "مؤشر «وثائق منتهية» يصيّر (المصدر: {$srcExpired})");
check(strpos($board, 'وثائقُ توشك') !== false, 'ومؤشر «توشك على الانتهاء» يصيّر');
check(strpos($board, 'عدّاداتٌ بلا قراءةٍ حديثة') === false,
    'والمؤشرُ الفاسد (meter_readings الغائب) لم يعد يُعرض');

// ═══ ⑦ لوحةُ الموارد البشرية (طلب المالك) ═══
head('⑦ لوحةُ الموارد البشرية (4): تنبيهُ الرخص والهويات المنتهية');
check(login('اروينا'), 'دخول «اروينا» (الموارد البشرية 4)');
list($c, $hb) = req(BASE . '/main/role_board.php');
check($c === 200 && mb_strpos($hb, 'لوحة ادارة الموارد البشرية') !== false, "اللوحة تصيّر (HTTP {$c})");

$srcLic = (int) $db->query("SELECT COUNT(*) c FROM equipment_documents
    WHERE company_id=4 AND COALESCE(is_deleted,0)=0 AND status<>'ملغاة'
      AND subject_type='operator' AND doc_type='رخصة قيادة'
      AND expiry_date IS NOT NULL AND expiry_date < CURDATE()")->fetch_assoc()['c'];
$srcId = (int) $db->query("SELECT COUNT(*) c FROM equipment_documents
    WHERE company_id=4 AND COALESCE(is_deleted,0)=0 AND status<>'ملغاة'
      AND subject_type='operator' AND doc_type='هوية'
      AND expiry_date IS NOT NULL AND expiry_date < CURDATE()")->fetch_assoc()['c'];

check(mb_strpos($hb, 'رخصُ قيادةٍ منتهية') !== false, "بطاقةُ «رخصُ قيادةٍ منتهية» ظاهرة (المصدر {$srcLic})");
check(mb_strpos($hb, 'هوياتٌ منتهية') !== false, "بطاقةُ «هوياتٌ منتهية» ظاهرة (المصدر {$srcId})");
// الرقمُ المعروض = المصدرُ نفسُه (لا تقريبَ ولا تلفيق)
$licShown = false; $idShown = false;
$pL = mb_strpos($hb, 'رخصُ قيادةٍ منتهية');
if ($pL !== false) { $licShown = (mb_strpos(mb_substr($hb, max(0, $pL - 400), 420), '>' . $srcLic . '<') !== false); }
$pI = mb_strpos($hb, 'هوياتٌ منتهية');
if ($pI !== false) { $idShown = (mb_strpos(mb_substr($hb, max(0, $pI - 400), 420), '>' . $srcId . '<') !== false); }
check($licShown && $idShown, "الرقمان المعروضان = المصدر حرفيًّا ({$srcLic} · {$srcId})");

// «توشك» مهمّةٌ تختفي عند الصفر — سلوكٌ مقصودٌ موثَّق في main/role_board.php
$srcSoon = (int) $db->query("SELECT COUNT(*) c FROM equipment_documents
    WHERE company_id=4 AND COALESCE(is_deleted,0)=0 AND status<>'ملغاة' AND subject_type='operator'
      AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL alert_days DAY)")->fetch_assoc()['c'];
$soonShown = mb_strpos($hb, 'وثائقُ أفرادٍ توشك') !== false;
check(($srcSoon > 0) === $soonShown,
    "مهمّةُ «توشك» تتبع مصدرَها ({$srcSoon}) — والصفريُّ يختفي بالتصميم لا بالعطب");

// والبطاقةُ تقود إلى بابٍ مفتوح (الحارس المركزي)
list($dc, $dpg) = req(BASE . '/Equipments/equipment_documents.php');
check($dc === 200 && mb_strpos($dpg, 'وثائق المعدات والمشغّلين') !== false,
    'والدور 4 يفتح شاشةَ الوثائق التي تقود إليها البطاقة (منحٌ لا بابٌ مغلق)');

// ═══ ⑧ العزل ═══
head('⑧ العزل بين الشركات');
$co1 = (int) $db->query("SELECT COUNT(*) c FROM equipment_documents WHERE company_id<>4")->fetch_assoc()['c'];
check($co1 === 0, "كلُّ الوثائق لشركتها (لا تسرّبَ لغيرها): {$co1} خارجها");

// تنظيف صف الدخنة
$tsId = $savedRow ? (int) $savedRow['id'] : 0;
if ($tsId) {
    $db->query("DELETE FROM ems_business_events WHERE entity_type='unit_entry' AND entity_id IN (SELECT id FROM unit_entries WHERE sync_uuid='ts:{$tsId}')");
    $db->query("DELETE FROM unit_time_log WHERE entry_id IN (SELECT id FROM unit_entries WHERE sync_uuid='ts:{$tsId}')");
    $db->query("DELETE FROM unit_capacity_flags WHERE entry_id IN (SELECT id FROM unit_entries WHERE sync_uuid='ts:{$tsId}')");
    $db->query("DELETE FROM unit_entries WHERE sync_uuid='ts:{$tsId}'");
    $db->query("DELETE FROM ems_business_events WHERE entity_type='timesheet' AND entity_id={$tsId}");
    $db->query("DELETE FROM timesheet WHERE id={$tsId}");
}

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
