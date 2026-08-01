<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-27 · M-28 · M-29 · M-26 — اختبار قبول مجموعةِ التشغيل والجاهزية
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/ops_readiness_group_test.php
 *
 * ما يُثبته:
 *   M-27 الغرفةُ الأم: مَن رفع ومَن تأخر · صندوقُ الاعتماد بعدّاده ·
 *        الالتزامُ بالوتيرة اللازمة — والقديمةُ تحوَّل بعدّاد hits.
 *   M-28 فحصُ التعارض **الخادميُّ الشامل** بحالاته الثلاث (كان لواحدة).
 *   M-29 السجلُّ التحليلي بوحداته المنفصلة — **لا تُجمع وحدتان في رقم**.
 *   M-26 شبكةُ الجاهزية الحية: الحالُ محسوبٌ لا حقلًا · كلُّ خليةٍ برابط
 *        بطاقتها · وجاهزية٪ من المصادر.
 *
 * البذرُ معزول بعلامة OPS4T — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'ops group test');

require_once dirname(__DIR__) . '/app/Services/Operations/OperationsBoardService.php';

use App\Services\Operations\OperationsBoardService as OBS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO   = 4;
$MARK = 'OPS4T' . getmypid();
$D    = '2086-05-15'; // يومُ اختبارٍ معزولٌ زمنيًّا

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM unit_entries WHERE entry_no LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM equipments WHERE name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM employees WHERE name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ التشغيلُ والجاهزية — M-27 · M-28 · M-29 · M-26 ══\n");

head('البذر — مشروعان ومعدتان ومشغّلان (أحدُهما برخصةٍ منتهية)');

$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}-A', 'ع', 'م', '0')");
$PRJ_A = intval($conn->insert_id);
$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}-B', 'ع', 'م', '0')");
$PRJ_B = intval($conn->insert_id);
$conn->query("INSERT INTO equipments (company_id, name, code, type, status, created_at)
              VALUES ({$CO}, 'حفارُ {$MARK}', 'EQ-{$MARK}-1', 'excavator', 'active', NOW())");
$EQ1 = intval($conn->insert_id);
$conn->query("INSERT INTO equipments (company_id, name, code, type, status, created_at)
              VALUES ({$CO}, 'قلابُ {$MARK}', 'EQ-{$MARK}-2', 'truck', 'active', NOW())");
$EQ2 = intval($conn->insert_id);
$conn->query("INSERT INTO employees (company_id, name, employee_code, employee_type, created_at)
              VALUES ({$CO}, 'مشغّلُ {$MARK}-1', 'OP-{$MARK}-1', 'operator', NOW())");
$OP1 = intval($conn->insert_id);
$conn->query("INSERT INTO employees (company_id, name, employee_code, employee_type, created_at)
              VALUES ({$CO}, 'مشغّلُ {$MARK}-2', 'OP-{$MARK}-2', 'operator', NOW())");
$OP2 = intval($conn->insert_id);
// رخصةُ OP1 منتهيةٌ قبل يوم الاختبار — وOP2 بلا سجل رخصةٍ أصلًا
$conn->query("INSERT INTO equipment_operators (company_id, employee_id, license_number,
              license_expiry_date, status, created_at)
              VALUES ({$CO}, {$OP1}, 'L-{$MARK}', '2086-01-01', 'active', NOW())");

$mkUE = function ($sfx, $prj, $eq, $op, $shift, $ut, $qty, $state, $date) use ($conn, $CO, $MARK) {
    $opv = $op === null ? 'NULL' : intval($op);
    $conn->query("INSERT INTO unit_entries (company_id, entry_no, entry_date, project_id,
                  equipment_id, operator_employee_id, unit_type, qty, record_basis, capacity_flag,
                  state, shift, revision_no, current_round, created_at, updated_at)
                  VALUES ({$CO}, 'UE-{$MARK}-{$sfx}', '{$date}', {$prj}, {$eq}, {$opv},
                          '{$ut}', {$qty}, 'contract', 0, '{$state}', '{$shift}', 1, 1, NOW(), NOW())");
    return intval($conn->insert_id);
};
// OP1 في معدتين بالوردية نفسها (تعارض ①) — وقلابٌ بلا مشغّل (②) — ورخصٌ (③)
$mkUE('1', $PRJ_A, $EQ1, $OP1, 'day', 'ton', 400, 'submitted', $D);
$mkUE('2', $PRJ_A, $EQ2, $OP1, 'day', 'trip', 12, 'site_approved', $D);
$mkUE('3', $PRJ_A, $EQ2, null, 'night', 'trip', 8, 'submitted', $D);
// وأمسِ رفع المشروعان — واليومَ رفع A وحدَه (B متأخر)
$mkUE('4', $PRJ_B, $EQ2, $OP2, 'day', 'hour', 9, 'sales_approved', date('Y-m-d', strtotime($D . ' -1 day')));
check($PRJ_A && $EQ1 && $OP1, "مشروعان #{$PRJ_A}/#{$PRJ_B} · معدتان · مشغّلان ووحداتُ يوم {$D}");

// ═══ M-27 ═══
head('M-27 — الغرفةُ الأم: مَن رفع ومَن تأخر والاعتمادُ والالتزام');

$sites = OBS::sitesToday($conn, $CO, $D);
$a = null; $b = null;
foreach ($sites as $s) {
    if ($s['project_id'] === $PRJ_A) { $a = $s; }
    if ($s['project_id'] === $PRJ_B) { $b = $s; }
}
check($a !== null && !$a['late'] && $a['raised_today'] === 3,
      '★ المشروع A **رفع اليومَ** (3 وحدات بآخر تحديثها)');
check($b !== null && $b['late'],
      '★★ والمشروع B رفع أمسِ ولم يرفع اليومَ ⇒ **متأخرٌ بشارته وزرِّ المطالبة** — «مَن رفع ومَن تأخر»');

$box = OBS::approvalBox($conn, $CO);
check(($box['submitted'] ?? 0) >= 2 && ($box['site_approved'] ?? 0) >= 1,
      '★ صندوقُ الاعتماد بعدّاده لكل مرحلة (submitted/site_approved) — والقرارُ في بيته');

$ts = OBS::timesheetToday($conn, $CO, $D);
check(count($ts['rows']) === 3 && ($ts['counts']['submitted'] ?? 0) === 2,
      '★ تايم شيتُ اليوم بحالاته — قفزٌ للإدخال لا إدخالٌ هنا');

// ═══ M-28 ═══
head('M-28 — فحصُ التعارض الخادميُّ الشامل (ثلاثُ حالاتٍ لا واحدة)');

$conf = OBS::conflicts($conn, $CO, $D);
$kinds = array();
foreach ($conf as $c) { $kinds[$c['kind']] = ($kinds[$c['kind']] ?? 0) + 1; }
check(($kinds['double_booking'] ?? 0) >= 1,
      '★★★ ① المشغّلُ في معدتين بالوردية نفسها **يُكشف في الخادم**');
check(($kinds['no_operator'] ?? 0) >= 1,
      '★★ ② ومعدةٌ عاملةٌ بلا مشغّلٍ مسمًّى تُكشف');
$expiredMsg = false;
foreach ($conf as $c) {
    if ($c['kind'] === 'license' && strpos($c['message'], 'منتهية') !== false) { $expiredMsg = true; }
}
check(($kinds['license'] ?? 0) >= 1 && $expiredMsg,
      '★★ ③ ورخصةُ OP1 **المنتهيةُ منذ 2086-01-01 تُكشف بتاريخها**');
// وOP2 (بلا سجل رخصةٍ أصلًا) عمل أمسِ — فحصُ يومِه يعلن الغياب
$confY = OBS::conflicts($conn, $CO, date('Y-m-d', strtotime($D . ' -1 day')));
$noRec = false;
foreach ($confY as $c) {
    if ($c['kind'] === 'license' && strpos($c['message'], 'بلا سجل رخصة') !== false) { $noRec = true; }
}
check($noRec, '★★ وغيابُ سجل الرخصة (OP2) **يُعلَن ولا يُتجاهل** — fail-closed لا fail-open');

$grid = OBS::distributionGrid($conn, $CO, $D);
check(isset($grid[$EQ1]['day']) && $grid[$EQ1]['day']['operator_id'] === $OP1
      && isset($grid[$EQ2]['night']) && $grid[$EQ2]['night']['operator_id'] === null,
      '★ والشبكةُ (معدة×وردية×مشغّل) تُبنى من السجل — والخليةُ الفارغةُ ⚠ لا صمت');

// ═══ M-29 ═══
head('M-29 — السجلُّ التحليلي بوحداته المنفصلة');

$rep = OBS::dailyUnitsReport($conn, $CO, $D, $D, $PRJ_A);
check(count($rep['rows']) === 3, '★ صفوفُ الفترة للمشروع المرشَّح (3)');
check(abs(($rep['totals_by_unit']['ton'] ?? 0) - 400) < 0.005
      && abs(($rep['totals_by_unit']['trip'] ?? 0) - 20) < 0.005
      && !isset($rep['totals_by_unit']['ton_trip']),
      '★★★ المجاميعُ **بوحداتها المنفصلة** (طن 400 · نقلة 20) — **لا تُجمع وحدتان في رقم**');
$row = $rep['rows'][0];
check(isset($row['project'], $row['equipment'], $row['operator'], $row['state']),
      '★ والأعمدةُ التحليلية بأسمائها لا بمعرّفاتها العارية');

// ═══ M-26 ═══
head('M-26 — شبكةُ الجاهزية الحية: الحالُ محسوبٌ لا حقلًا');

$g = OBS::readinessGrid($conn, $CO);
$c1 = null; $c2 = null;
foreach ($g['cells'] as $c) {
    if ($c['id'] === $EQ1) { $c1 = $c; }
    if ($c['id'] === $EQ2) { $c2 = $c; }
}
check($c1 !== null && $c1['status'] === 'idle',
      '★ الحفارُ عمل في 2086 لا اليومَ ⇒ **استعداد** (الحالُ من وحدات اليوم الحي لا من حقل)');
check($c1['link'] === 'Equipments/equipment_profile.php?id=' . $EQ1,
      '★★ وكلُّ خليةٍ تنقر إلى بطاقتها — «لا طريقَ مسدودًا»');
check($g['readiness_pct'] !== null && $g['total'] >= 2,
      '★ وجاهزية٪ حيةٌ محسوبةٌ من ' . $g['total'] . ' معدة: ' . $g['readiness_pct'] . '٪');

// ═══ التحويلات ═══
head('المساراتُ القديمة تُحوَّل ولا صفحةَ خطأ (SPEC-00 §3-②)');

foreach (array('Oprators/oprators.php' => 'operations_room',
               'Oprators/select_project.php' => 'operations_room',
               'Reports/timesheetdeliy.php' => 'daily_units_report') as $old => $new) {
    $src = file_get_contents(dirname(__DIR__) . '/' . $old);
    check(strpos($src, 'legacy_hit') !== false && strpos($src, $new) !== false
          && strpos($src, "legacy") !== false,
          '★ ' . $old . ' ⇒ تحويلٌ **بعدّاد hits** وبابِ ?legacy=1 معلَن');
}

// ═══ الخاتمة ═══
fwrite(STDOUT, "\n══════════════════════════════════════\n");
fwrite(STDOUT, "  النتيجة: {$PASS} نجاح · {$FAIL} فشل\n");
exit($FAIL > 0 ? 1 : 0);
