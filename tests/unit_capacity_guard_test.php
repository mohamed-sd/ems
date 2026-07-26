<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * D02 §3.10 · §4.3 · §14⑬ — اختبار قبول حارس الطاقة اليومية
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/unit_capacity_guard_test.php
 *
 * فحصُ القبول الملزم (§14⑬): «إعادة تشغيل بيانات الواقع … يجب أن يلتقطها
 * الحارس كلَّها ويمنع اعتمادها قبل المراجعة».
 *
 * الواقعة الحيّة التي يمرّ بها هذا الاختبار — مقيسةٌ من timesheet لا مفترضة:
 *   المعدة 12 · شركة 4 · يوم 2026-04-27 · ثلاثة صفوف (222 · 223 · 224)
 *     • وردية نهارية — الموظف 3 —  8 ساعات عمل (+2 عطل)
 *     • وردية ليلية  — الموظف 4 — 10 ساعات
 *     • وردية ليلية  — الموظف 3 — 10 ساعات   ← الليلية المكرّرة
 *   فالمجموع 28 ساعةً للمعدة (طاقتها 20)، و18 للموظف 3 (طاقته 10).
 *   ولا شيء في النظام يلتقط أيًّا منهما اليوم.
 *
 * يُعاد تشغيلها هنا واقعةً حقيقيةً في المصدر الجديد — لا جدولًا فارغًا نجح
 * فيه CREATE. وقراءةُ timesheet قراءةٌ محضة: 220 صفًّا قبل وبعد.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Unit/CapacityGuard.php';

use App\Core\TenantContext;
use App\Core\TenantDb;
use App\Services\Unit\CapacityGuard;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)   { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m)  { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$CO   = 4;
$EQ   = 12;
$EMP_A = 3;   // عمل الورديتين — النهارية والليلية المكرّرة
$EMP_B = 4;
$DAY  = '2026-04-27';
$MARK = 'D02CAP_' . getmypid();
$ACTOR = 1;

$conn = $GLOBALS['conn'];

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM unit_capacity_flags WHERE entry_id IN
                    (SELECT id FROM unit_entries WHERE entry_no LIKE '{$MARK}%')");
    $conn->query("DELETE FROM unit_time_log  WHERE sync_uuid LIKE '{$MARK}%'");
    $conn->query("DELETE FROM unit_entries   WHERE entry_no LIKE '{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ D02 §3.10 — حارس الطاقة اليومية ══\n");

$ctx = TenantContext::forSystem($CO, $ACTOR, '', true);
$db  = new TenantDb($conn, $ctx);

$tsBefore = (int) $conn->query("SELECT COUNT(*) c FROM timesheet")->fetch_assoc()['c'];

// ═══ ① §14⑬ — إعادة تشغيل بيانات الواقع (قراءةٌ محضة) ═══
head('① إعادة تشغيل بيانات الواقع — هل يلتقط معيارُ الحارس التجاوزَ القائم؟');

$scan = CapacityGuard::scanLegacyTimesheet($conn, $CO);

// ⚠️ نطاقُ هذا الفحص هو البياناتُ التاريخية وحدها. مجموعاتُ البذر اللاحقة
// (مطابقةُ §9-③ في 2027-02 مثلًا) تضيف تجاوزاتٍ مقصودةً إلى المسح، فالعدُّ
// المطلق عبر كل الجدول يكسر الحزمةَ بلا خللٍ حقيقي. القصدُ الأصلي — «هل
// يلتقط المعيارُ التجاوزَ القائم في بيانات الواقع؟» — يُقاس على نطاقه.
$LEGACY_BEFORE = '2027-01-01';
$onlyLegacy = function (array $rows) use ($LEGACY_BEFORE) {
    $out = array();
    foreach ($rows as $r) { if ($r['d'] < $LEGACY_BEFORE) { $out[] = $r; } }
    return $out;
};
$scanEq = $onlyLegacy($scan['equipment']);
$scanOp = $onlyLegacy($scan['operator']);

$eqHit = null;
foreach ($scanEq as $row) {
    if ((int) $row['subject_ref'] === $EQ && $row['d'] === $DAY) { $eqHit = $row; }
}
check($eqHit !== null, "التقط تجاوز المعدة {$EQ} يوم {$DAY} — التجاوزُ الحيُّ لم يعد صامتًا");
check($eqHit !== null && (float) $eqHit['measured'] === 28.00,
    'قاسه 28 ساعةً بالضبط (لا تقريبًا ولا تقديرًا) — الطاقة 20');
check($eqHit !== null && (int) $eqHit['rows_n'] === 3, 'من ثلاثة صفوفٍ حيّة');
check(count($scanEq) === 1, 'تجاوزُ آلاتٍ واحدٌ في البيانات التاريخية — مطابقٌ للمقيس');

$empHit = null;
foreach ($scanOp as $row) {
    if ((int) $row['subject_ref'] === $EMP_A && $row['d'] === $DAY) { $empHit = $row; }
}
check($empHit !== null && (float) $empHit['measured'] === 18.00,
    "التقط الموظف {$EMP_A} في اليوم نفسه: 18 ساعةً وطاقتُه 10");
check(count($scanOp) === 17,
    'التقط سبعة عشر تجاوزًا للمشغّلين تاريخيًّا — العدد المقيس فعلًا (§3.10 ذكر 22؛ المقيس 17)');

// ═══ ② الواقعة الحقيقية تُعاد في المصدر الجديد ═══
head('② الواقعة تُدخَل — والحارس «لا يمنع إدخالَه» (§3.10)');

$mkEntry = function ($suffix, $empId, $qty) use ($db, $DAY, $EQ, $MARK) {
    return $db->insert('unit_entries', array(
        'entry_no'             => $MARK . $suffix,
        'entry_date'           => $DAY,
        'project_id'           => 1,
        'equipment_id'         => $EQ,
        'operator_employee_id' => $empId,
        'unit_type'            => 'hour',
        'qty'                  => $qty,
        'shift'                => ($suffix === '-D' ? 'day' : 'night'),
        'state'                => 'submitted',
    ));
};
$mkSpan = function ($suffix, $entryId, $empId, $shift, $from, $to, $hours) use ($db, $DAY, $EQ, $MARK) {
    return $db->insert('unit_time_log', array(
        'log_date'             => $DAY,
        'shift'                => $shift,
        'project_id'           => 1,
        'equipment_id'         => $EQ,
        'operator_employee_id' => $empId,
        'time_from'            => $from,
        'time_to'              => $to,
        'hours'                => $hours,
        'ops_state'            => 'actual_work',
        'resp_party'           => 'none',
        'entry_id'             => $entryId,
        'sync_uuid'            => $MARK . $suffix,
    ));
};

$eD  = $mkEntry('-D',  $EMP_A,  8.00);   // نهارية — الموظف 3
$eN1 = $mkEntry('-N1', $EMP_B, 10.00);   // ليلية  — الموظف 4
$eN2 = $mkEntry('-N2', $EMP_A, 10.00);   // ليلية مكرّرة — الموظف 3 ثانيةً

$mkSpan('-D',  $eD,  $EMP_A, 'day',   '06:00:00', '14:00:00',  8.00);
$mkSpan('-N1', $eN1, $EMP_B, 'night', '18:00:00', '23:59:00', 10.00);
$mkSpan('-N2', $eN2, $EMP_A, 'night', '18:00:00', '23:59:00', 10.00);

check($eD > 0 && $eN1 > 0 && $eN2 > 0,
    "الوقائع الثلاث حُفظت (#{$eD} · #{$eN1} · #{$eN2}) — التجاوز لم يُمنع إدخالُه");

$measuredEq = CapacityGuard::measuredHours($conn, $CO, $DAY, 'equipment', $EQ);
check($measuredEq === 28.00, "قياسُ المعدة في المصدر الجديد = 28.00 (مطابقٌ للحيّ)");
$measuredOp = CapacityGuard::measuredHours($conn, $CO, $DAY, 'operator', $EMP_A);
check($measuredOp === 18.00, "قياسُ الموظف {$EMP_A} = 18.00 (مطابقٌ للحيّ)");

// ═══ ③ رفع العلم ═══
head('③ الحارس يقيس ويرفع العلم على المحورين المستقلّين');

$breaches = CapacityGuard::evaluate($conn, $db, array(
    'id' => $eN2, 'company_id' => $CO, 'entry_date' => $DAY,
    'equipment_id' => $EQ, 'operator_employee_id' => $EMP_A,
));
check(count($breaches) === 2, 'رُفع علمان: المعدة والمشغّل — محوران مستقلّان لا واحد');

$byAxis = array();
foreach ($breaches as $b) { $byAxis[$b['subject']] = $b; }
check(isset($byAxis['equipment']) && $byAxis['equipment']['measured'] === 28.00
      && $byAxis['equipment']['capacity'] === 20.0, 'علم المعدة: 28 من 20');
check(isset($byAxis['operator']) && $byAxis['operator']['measured'] === 18.00
      && $byAxis['operator']['capacity'] === 10.0, 'علم المشغّل: 18 من 10');
check(isset($byAxis['equipment']) && $byAxis['equipment']['duplicate'] === true,
    'فحصُ التكرار كشف الوردية الليلية المكرّرة — النمطُ الذي وصفه §3.10');
check(isset($byAxis['equipment']) && $byAxis['equipment']['overlap'] === true,
    'فحصُ التداخل كشف تقاطعَ الورديتين الليليتين');

$flag = $conn->query("SELECT capacity_flag FROM unit_entries WHERE id={$eN2}")->fetch_assoc();
check((int) $flag['capacity_flag'] === 1, 'capacity_flag = 1 على الواقعة (§3.1)');
$still = $conn->query("SELECT COUNT(*) c FROM unit_entries WHERE id={$eN2}")->fetch_assoc();
check((int) $still['c'] === 1, 'والواقعة محفوظةٌ لم تُرفض — «يحذّر ويحفظ أوليًّا»');

// ═══ ④ المنع — شرطُ اعتماد الموقع ═══
head('④ اعتماد الموقع ② ممنوعٌ حتى المراجعة (§4.3)');

$verdict = CapacityGuard::assertSiteApprovable($conn, $CO, $eN2);
check($verdict['ok'] === false, '⛔ الاعتماد مرفوض — الحارس التقط التجاوز ومنعه');
check(count($verdict['reasons']) === 2, 'سببان معلنان — علمٌ لكل محور');
$all = implode(' | ', $verdict['reasons']);
check(strpos($all, 'السبب غير مُدخَل') !== false, 'الرفض يعلن: السبب غير مُدخَل');
check(strpos($all, 'مشغّلٍ ثانٍ') !== false, 'الرفض يعلن: وجودُ مشغّلٍ ثانٍ لم يُحدَّد');
check(strpos($all, 'اعتمادُ المسؤول') !== false, 'الرفض يعلن: اعتمادُ المسؤول لم يقع');
check(strpos($all, '28.00') !== false && strpos($all, '18.00') !== false,
    'الرفض يحمل الرقمين المقيسين — لا رسالةً عامة');

// ═══ ⑤ لا تخليصَ بالالتفاف ═══
head('⑤ التخليص إعلانٌ صريحٌ — لا ختمٌ شامل');

$r = CapacityGuard::clear($conn, $db, $CO, $eN2, 'equipment', '', true, $ACTOR);
check($r === false, 'تخليصٌ بلا سببٍ: مرفوض');
$r = CapacityGuard::clear($conn, $db, $CO, $eN2, 'equipment', 'وردية إضافية معتمدة', null, $ACTOR);
check($r === false, 'تخليصٌ بلا إعلانِ مشغّلٍ ثانٍ: مرفوض');
$r = CapacityGuard::clear($conn, $db, $CO, $eN2, 'equipment', 'وردية إضافية معتمدة', true, 0);
check($r === false, 'تخليصٌ بلا مسؤولٍ معتمِد: مرفوض');

$r = CapacityGuard::clear($conn, $db, $CO, $eN2, 'equipment',
    'ورديةٌ ليليةٌ ثانيةٌ معتمدةٌ بأمر عمل — مشغّلٌ مستقلٌّ لكلٍّ', true, $ACTOR);
check($r === true, 'تخليصُ محور المعدة بالإعلانات الأربعة: نجح');

$verdict = CapacityGuard::assertSiteApprovable($conn, $CO, $eN2);
check($verdict['ok'] === false, '⛔ ما زال ممنوعًا — محور المشغّل مفتوح');
check(count($verdict['reasons']) === 1, 'بقي سببٌ واحد: لا يُخلَّص المشغّل بتخليص المعدة');
check(strpos($verdict['reasons'][0], 'المشغّل') !== false, 'والسببُ الباقي هو المشغّل نفسه');

$r = CapacityGuard::clear($conn, $db, $CO, $eN2, 'operator',
    'الموظف عمل ورديتين متتاليتين بموافقة مدير الموقع', false, $ACTOR);
check($r === true, 'تخليصُ محور المشغّل: نجح');

$verdict = CapacityGuard::assertSiteApprovable($conn, $CO, $eN2);
check($verdict['ok'] === true, '✔ الاعتماد مسموحٌ الآن — بعد المراجعة الكاملة لا قبلها');

$row = $conn->query("SELECT cause_note, second_operator_present, cleared_by, cleared_at,
                            overlap_found, duplicate_found
                       FROM unit_capacity_flags WHERE entry_id={$eN2} AND subject='equipment'")->fetch_assoc();
check($row['cleared_by'] !== null && $row['cleared_at'] !== null, 'المخلِّص ووقتُه مسجَّلان — أثرُ تدقيق');
check((int) $row['second_operator_present'] === 1, 'إعلانُ المشغّل الثاني محفوظٌ كما أُعلن');
check((int) $row['duplicate_found'] === 1 && (int) $row['overlap_found'] === 1,
    'نتيجتا الفحصين محفوظتان مع التخليص — لا تُمحى بالاعتماد');

// ═══ ⑥ لا إنذارَ كاذب ═══
head('⑥ الحارس لا يرفع علمًا بلا تجاوز');

$clean = $db->insert('unit_entries', array(
    'entry_no' => $MARK . '-OK', 'entry_date' => '2026-04-28', 'project_id' => 1,
    'equipment_id' => 999001, 'operator_employee_id' => 999002,
    'unit_type' => 'hour', 'qty' => 9.00, 'state' => 'submitted',
));
$b = CapacityGuard::evaluate($conn, $db, array(
    'id' => $clean, 'company_id' => $CO, 'entry_date' => '2026-04-28',
    'equipment_id' => 999001, 'operator_employee_id' => 999002,
));
check(count($b) === 0, 'تسع ساعاتٍ ضمن الطاقة: صفر علم');
$f = $conn->query("SELECT capacity_flag FROM unit_entries WHERE id={$clean}")->fetch_assoc();
check((int) $f['capacity_flag'] === 0, 'capacity_flag = 0');
$v = CapacityGuard::assertSiteApprovable($conn, $CO, $clean);
check($v['ok'] === true, 'واعتمادُ الموقع مسموحٌ فورًا — الحارس لا يعطّل السليم');

// ═══ ⑦ التعايش ═══
head('⑦ التعايش — المسار الحيّ لم يُمسّ');
$tsAfter = (int) $conn->query("SELECT COUNT(*) c FROM timesheet")->fetch_assoc()['c'];
check($tsAfter === $tsBefore, "timesheet: {$tsBefore} → {$tsAfter} صفًّا (قراءةٌ محضة)");
$live = $conn->query("SELECT SUM(total_work_hours) h FROM timesheet
                       WHERE company_id={$CO} AND operator='{$EQ}' AND date='{$DAY}'")->fetch_assoc();
check((float) $live['h'] === 28.0, 'وصفوفُ الواقعة الحيّة كما هي: 28 ساعة');

// ═══ التنظيف ═══
head('التنظيف');
$teardown();
$left = (int) $conn->query("SELECT COUNT(*) c FROM unit_entries WHERE entry_no LIKE '{$MARK}%'")->fetch_assoc()['c'];
// الأعلامُ تُقاس يتيمةً لا مطلقةً: العدُّ المطلق كان يفترض أن الجدول ملكُ هذه
// الحزمة وحدها، فكسرَته أعلامُ مجموعة المطابقة المشروعة (§9-③). المعنى المقصود
// — «مضت مع الواقعة بـCASCADE» — يُقاس بغياب علمٍ بلا واقعة.
$leftF = (int) $conn->query(
    "SELECT COUNT(*) c FROM unit_capacity_flags f
      WHERE NOT EXISTS (SELECT 1 FROM unit_entries u WHERE u.id = f.entry_id)")->fetch_assoc()['c'];
check($left === 0 && $leftF === 0, 'teardown: صفر بقايا (والأعلام مضت مع الواقعة بـCASCADE)');

fwrite(STDOUT, "\n" . str_repeat('═', 50) . "\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
