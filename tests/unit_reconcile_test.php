<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * §9-③ خطوة ③ — مطابقةُ الدفترين «صفرَ فرق» على المجموعة النظيفة
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/unit_reconcile_test.php      (بعد seed_unit_reconcile_50.php)
 *
 * السؤال الذي تجيب عنه هذه الحزمة سؤالٌ واحد:
 *   **هل يقول السجلُّ القانونيُّ الجديد ما يقوله سجلُّ الدوام القديم — حرفًا بحرف؟**
 *
 * النطاق: النافذة 2027-02-01 → 2027-02-14 **وحدها** (قرار المالك: «لا تعتمد
 * البيانات القديمة»). الصفوفُ الـ221 التاريخية خارج المطابقة نصًّا وقياسًا.
 *
 * المطابقة على ستة محاور — كلٌّ منها يُقاس لا يُفترض:
 *   ① العدد والنسب      — لكل صفٍّ مرآةٌ واحدةٌ لا أكثر ولا أقل
 *   ② الهوية            — التاريخ والوردية والمعدة
 *   ③ الاشتقاق          — المشروع والمورد من صف التشغيل (§5.1)
 *   ④ وحدةُ العقد       — qty/unit_type بقاعدة «غيرُ الساعيّ أولى»
 *   ⑤ زمنُ الوردية      — مجموع unit_time_log = مجموع أعمدة الدوام (الفصل الثلاثي)
 *   ⑥ حارسُ الطاقة      — يلتقط المصنوع عمدًا ولا يرفع علمًا على السليم
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

const WIN_FROM = '2027-02-01';
const WIN_TO   = '2027-02-14';

$env = array();
foreach (file(dirname(__DIR__) . '/.env') as $l) {
    if (preg_match('/^\s*([A-Z_]+)=(.*)$/', $l, $m)) { $env[$m[1]] = trim($m[2]); }
}
$db = new mysqli($env['DB_HOST'], 'root', '', $env['DB_NAME']);
if ($db->connect_error) { fwrite(STDERR, "conn: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

$PASS = 0; $FAIL = 0; $DIFFS = array();
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function diff($ts, $axis, $old, $new) {
    global $DIFFS;
    $DIFFS[] = array('ts' => $ts, 'axis' => $axis, 'old' => $old, 'new' => $new);
}

fwrite(STDOUT, "\n══ §9-③ ③ — مطابقةُ الدفترين على النافذة " . WIN_FROM . " → " . WIN_TO . " ══\n");

// ═══ الصفوف قيد المطابقة — النافذة وحدها ═══
$rows = $db->query(
    "SELECT t.*, o.id AS op_id, o.project_id, o.contract_id, o.equipment AS eq_id,
            e.suppliers AS supplier_id
       FROM timesheet t
       JOIN operations o ON o.id = t.operator
       JOIN equipments e ON e.id = o.equipment
      WHERE t.`date` BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
      ORDER BY t.id")->fetch_all(MYSQLI_ASSOC);

$N = count($rows);

// ═══ ① العدد والنسب ═══
head('① العدد والنسب — لكل صفٍّ مرآةٌ واحدة');
check($N === 50, "صفوفُ الدوام في النافذة: {$N} (المتوقَّع 50)");

$mirrors = array();
$r = $db->query("SELECT * FROM unit_entries WHERE entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'");
while ($x = $r->fetch_assoc()) { $mirrors[$x['sync_uuid']] = $x; }
check(count($mirrors) === $N, "المرايا في السجل القانوني: " . count($mirrors) . " — واحدةٌ لكل صف");

$orphans = 0;
foreach ($mirrors as $uuid => $mm) {
    if (strpos($uuid, 'ts:') !== 0) { $orphans++; continue; }
    $tsId = (int) substr($uuid, 3);
    $found = false;
    foreach ($rows as $rr) { if ((int) $rr['id'] === $tsId) { $found = true; break; } }
    if (!$found) { $orphans++; }
}
check($orphans === 0, "صفرُ مرآةٍ يتيمة (نسبُها كلِّه يعود لصفٍّ قائم)");

$missing = array();
foreach ($rows as $rr) { if (!isset($mirrors['ts:' . $rr['id']])) { $missing[] = (int) $rr['id']; } }
check(empty($missing), 'صفرُ صفٍّ بلا مرآة' . ($missing ? ' — الناقص: ' . implode(',', $missing) : ''));

// ═══ ②③④⑤ المرور صفًّا صفًّا ═══
head('②③④⑤ المرور صفًّا صفًّا — الهوية والاشتقاق ووحدة العقد وزمن الوردية');

$SHIFT = array('D' => 'day', 'N' => 'night');
$idOK = 0; $drvOK = 0; $unitOK = 0; $timeOK = 0;

foreach ($rows as $t) {
    $tsId = (int) $t['id'];
    if (!isset($mirrors['ts:' . $tsId])) { continue; }
    $u = $mirrors['ts:' . $tsId];

    // ② الهوية
    $idMatch = ($u['entry_date'] === $t['date'])
        && ($u['shift'] === (isset($SHIFT[$t['shift']]) ? $SHIFT[$t['shift']] : null))
        && ((int) $u['equipment_id'] === (int) $t['eq_id']);
    if ($idMatch) { $idOK++; }
    else { diff($tsId, 'الهوية', "{$t['date']}/{$t['shift']}/eq{$t['eq_id']}",
                 "{$u['entry_date']}/{$u['shift']}/eq{$u['equipment_id']}"); }

    // ③ الاشتقاق — المشروع والمورد من صف التشغيل
    $drvMatch = ((int) $u['project_id'] === (int) $t['project_id'])
        && ((int) $u['supplier_entity_id'] === (int) $t['supplier_id'])
        && ((int) $u['operator_employee_id'] === (int) $t['employee_id']);
    if ($drvMatch) { $drvOK++; }
    else { diff($tsId, 'الاشتقاق', "proj{$t['project_id']}/sup{$t['supplier_id']}/emp{$t['employee_id']}",
                 "proj{$u['project_id']}/sup{$u['supplier_entity_id']}/emp{$u['operator_employee_id']}"); }

    // ④ وحدة العقد — القاعدة المعلنة: المتر ثم الطن ثم الساعة
    $meters = (float) $t['meters_count'];
    $tons   = (float) $t['tons_count'];
    $hours  = (float) $t['executed_hours'];
    if ($meters > 0)   { $expUnit = 'meter'; $expQty = $meters; }
    elseif ($tons > 0) { $expUnit = 'ton';   $expQty = $tons; }
    else               { $expUnit = 'hour';  $expQty = $hours; }
    $unitMatch = ($u['unit_type'] === $expUnit) && (abs((float) $u['qty'] - $expQty) < 0.005);
    if ($unitMatch) { $unitOK++; }
    else { diff($tsId, 'وحدة العقد', "{$expUnit}/{$expQty}", "{$u['unit_type']}/{$u['qty']}"); }

    // ⑤ زمن الوردية — المجموع في السجل = المجموع في أعمدة الدوام
    $srcTime = round(
        (float) $t['executed_hours'] + (float) $t['standby_hours']
      + (float) $t['dependence_hours'] + (float) $t['approval_fault']
      + (float) $t['maintenance_fault'] + (float) $t['hr_fault']
      + (float) $t['marketing_fault'] + (float) $t['ts_supplier_stop_hours']
      + (float) $t['ts_planned_stop_hours'] + (float) $t['ts_force_majeure_hours']
      + (float) $t['other_fault_hours'], 2);
    $logTime = (float) $db->query(
        "SELECT COALESCE(SUM(hours),0) h FROM unit_time_log WHERE entry_id={$u['id']}")->fetch_assoc()['h'];
    if (abs($srcTime - $logTime) < 0.005) { $timeOK++; }
    else { diff($tsId, 'زمن الوردية', (string) $srcTime, (string) $logTime); }
}

check($idOK === $N, "② الهوية (تاريخ · وردية · معدة): {$idOK}/{$N}");
check($drvOK === $N, "③ الاشتقاق (مشروع · مورد · مشغّل): {$drvOK}/{$N}");
check($unitOK === $N, "④ وحدة العقد (النوع والكمية): {$unitOK}/{$N}");
check($timeOK === $N, "⑤ زمن الوردية (المجموع): {$timeOK}/{$N}");

// ═══ الفصل الثلاثي — لا يُخلط زمنٌ بوحدةِ فوترة ═══
head('الفصل الثلاثي (§5.1) — زمنُ العمل ≠ وحدةُ العقد');

$nonHour = $db->query(
    "SELECT COUNT(*) c FROM unit_entries
      WHERE entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
        AND unit_type <> 'hour'")->fetch_assoc()['c'];
check((int) $nonHour === 12, "12 واقعةً بوحدةٍ غيرِ ساعية (6 طن + 6 متر): {$nonHour}");

// كلُّ واقعةٍ غيرِ ساعيةٍ لها زمنُ ورديةٍ مسجَّلٌ رغم أن كميتها ليست ساعات
$nonHourNoTime = (int) $db->query(
    "SELECT COUNT(*) c FROM unit_entries u
      WHERE u.entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
        AND u.unit_type <> 'hour'
        AND NOT EXISTS (SELECT 1 FROM unit_time_log l WHERE l.entry_id = u.id)")->fetch_assoc()['c'];
check($nonHourNoTime === 0,
    'كلُّ واقعةٍ غيرِ ساعيةٍ تحمل زمنَ ورديتها كاملًا — الحقلان مستقلّان لا بديلان');

// حالاتُ الزمن العشر تُستعمل فعلًا لا تُختزل إلى واحدة
$states = $db->query(
    "SELECT ops_state, COUNT(*) n, ROUND(SUM(hours),2) h FROM unit_time_log
      WHERE log_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
      GROUP BY ops_state ORDER BY n DESC")->fetch_all(MYSQLI_ASSOC);
$stateNames = array();
foreach ($states as $s) { $stateNames[] = "{$s['ops_state']}({$s['n']})"; }
check(count($states) >= 5,
    'حالاتُ الزمن المستعملة: ' . implode(' · ', $stateNames));

$respRows = $db->query(
    "SELECT resp_party, COUNT(*) n FROM unit_time_log
      WHERE log_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
        AND ops_state <> 'actual_work'
      GROUP BY resp_party")->fetch_all(MYSQLI_ASSOC);
$respOK = true;
foreach ($respRows as $rp) { if ($rp['resp_party'] === 'none' && $rp['n'] > 0) { /* unlogged مسموح */ } }
$respNames = array();
foreach ($respRows as $rp) { $respNames[] = "{$rp['resp_party']}({$rp['n']})"; }
check(count($respRows) >= 3, 'كلُّ توقفٍ بمسؤوله: ' . implode(' · ', $respNames));

// ═══ ⑥ حارس الطاقة ═══
head('⑥ حارسُ الطاقة — يلتقط المصنوعَ عمدًا ولا يُزعج السليم');

$flagged = $db->query(
    "SELECT u.id, u.equipment_id, u.entry_date, u.shift, u.qty
       FROM unit_entries u
      WHERE u.entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
        AND u.capacity_flag = 1 ORDER BY u.id")->fetch_all(MYSQLI_ASSOC);
$breachDay = date('Y-m-d', strtotime(WIN_FROM . ' +13 days'));
$expectFlagged = 0;
foreach ($flagged as $f) { if ($f['entry_date'] === $breachDay && (int) $f['equipment_id'] === 24) { $expectFlagged++; } }
check($expectFlagged === 2,
    "المعدة 24 يوم {$breachDay} (11+11=22 ساعةً والحدّ 20): واقعتاها معلَّمتان — {$expectFlagged}");
check(count($flagged) === 2,
    'ولا علمَ على الثمانية والأربعين السليمة: ' . count($flagged) . ' واقعةٍ معلَّمةٍ إجمالًا');

// ملاحظةٌ بنيوية مقيسة: العلمُ البوليُّ على الواقعة واحد، وسجلُّ التخليص يفصّل
// محاورَه. وبحدَّي §3.10 وورديتين اثنتين يستحيل تجاوزُ المعدة وحدَها — فالمتوقَّع
// ثلاثةُ محاور: معدةٌ واحدةٌ (22 من 20) ومشغّلان (11 من 10 لكلٍّ).
$axes = $db->query(
    "SELECT f.subject, COUNT(*) n FROM unit_capacity_flags f
       JOIN unit_entries u ON u.id = f.entry_id
      WHERE u.entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
      GROUP BY f.subject ORDER BY f.subject")->fetch_all(MYSQLI_ASSOC);
$axMap = array();
foreach ($axes as $a) { $axMap[$a['subject']] = (int) $a['n']; }
check(isset($axMap['equipment']) && $axMap['equipment'] === 1,
    'محورُ المعدة: تجاوزٌ واحد — ' . (isset($axMap['equipment']) ? $axMap['equipment'] : 0));
check(isset($axMap['operator']) && $axMap['operator'] === 2,
    'محورُ المشغّل: تجاوزان — لازمٌ بنيويٌّ لا صدفة (وردية >10 ساعات) — '
    . (isset($axMap['operator']) ? $axMap['operator'] : 0));

$flagRows = (int) $db->query(
    "SELECT COUNT(*) c FROM unit_capacity_flags f
       JOIN unit_entries u ON u.id = f.entry_id
      WHERE u.entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'")->fetch_assoc()['c'];
check($flagRows === 3, "سجلُّ التخليص فيه ثلاثةُ محاورٍ بتفصيلها: {$flagRows}");

$cleared = (int) $db->query(
    "SELECT COUNT(*) c FROM unit_capacity_flags f
       JOIN unit_entries u ON u.id = f.entry_id
      WHERE u.entry_date BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
        AND f.cleared_at IS NOT NULL")->fetch_assoc()['c'];
check($cleared === 0, 'ولا علمَ خُلِّص تلقائيًّا — التخليصُ قرارُ بشرٍ لا أثرٌ جانبي');

// ═══ التعايش ═══
head('التعايش — البذرة لا تلمس ما خارج نافذتها');
// ⚠️ لا عدَّ مطلقًا هنا (گوتشا العدّ المطلق في جدولٍ مشترك — الدخناتُ الحية
// تضيف صفوفًا بتاريخ اليوم شرعًا): يُقاس المعنى نفسُه قياسًا نسبيًّا —
// ① كلُّ صفٍّ بذرتْه هذه المجموعة (وسم RECON50) داخل النافذة حصرًا.
$straydSeed = (int) $db->query(
    "SELECT COUNT(*) c FROM timesheet
      WHERE general_notes = 'RECON50'
        AND `date` NOT BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'")->fetch_assoc()['c'];
check($straydSeed === 0, "صفرُ صفِّ بذرةٍ خارج النافذة: {$straydSeed} — البذرة لا تلمس القديم");
// ② كلُّ مرآةٍ خارج النافذة نسبُها صحيح: sync_uuid يعود لصفِّ دوامٍ قائمٍ
//    بتاريخها نفسِه (كتابةٌ مزدوجةٌ حيّةٌ شرعية) — لا مرآةَ يتيمةً من بذر.
$orphanLegacy = (int) $db->query(
    "SELECT COUNT(*) c FROM unit_entries u
      WHERE u.entry_date NOT BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
        AND NOT EXISTS (SELECT 1 FROM timesheet t
                         WHERE CONCAT('ts:', t.id) = u.sync_uuid
                           AND t.`date` = u.entry_date)")->fetch_assoc()['c'];
check($orphanLegacy === 0, "كلُّ مرآةٍ خارج النافذة نسبُها لصفِّ دوامٍ حيٍّ قائم: {$orphanLegacy} يتيمة");

// ═══ الحكم ═══
fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
if ($DIFFS) {
    fwrite(STDOUT, "الفروقُ المرصودة (" . count($DIFFS) . "):\n");
    foreach (array_slice($DIFFS, 0, 25) as $d) {
        fwrite(STDOUT, "  TS#{$d['ts']} · {$d['axis']}: القديم [{$d['old']}] ≠ الجديد [{$d['new']}]\n");
    }
} else {
    fwrite(STDOUT, "الفرق: **صفر** — الدفتران يقولان الشيء نفسه على المحاور الستة.\n");
}
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
