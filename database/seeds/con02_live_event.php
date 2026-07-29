<?php
/**
 * database/seeds/con02_live_event.php
 * ═══════════════════════════════════════════════════════════════════════════
 * المرحلة (ج) — **واقعةٌ حيةٌ جديدة**: لمسةُ التغيير بالأرقام.
 *
 * ينشئ يومَ عملٍ حقيقيًّا على عقد العرض بتاريخ اليوم فيه:
 *     ٦ ساعاتِ تشغيلٍ فعليّ · ١.٥ ساعةِ توقفِ وقود · ٠.٥ ساعةِ عطلٍ فنيّ
 * ويمرّره بالسلسلة كاملةً حتى يتولّد قيدُ الإيراد، ثم يعرض **الحكمَ القديم
 * والحكمَ الجديد جنبًا إلى جنبٍ بالمال**.
 *
 * ⚠️ الحكمُ القديم **محسوبٌ بمحرّك النظام نفسِه لا بيدي**: تُستدعى
 *    `EffectFanout::resolveRuling()` — وهي عينُ الدالة التي يستدعيها المحرّكُ
 *    حين تكون لقطةُ الإسناد NULL (أي «سطرٌ ما قبل المصفوفة») — فالمقارنةُ
 *    بين حكمين حقيقيين لا بين حقيقةٍ وتقدير. وهي **قراءةٌ خالصةٌ بلا كتابة**.
 *
 * ⚠️ وكلُّ فرقٍ يظهر هنا مصدرُه **فرضُ تجربةٍ معلن**: بندُ الوقود مبذورٌ على
 *    «العميل» ولا نعرف نصَّ العقد الموقَّع. فاقرأ الرقمَ: «هذا ما سيحدث **لو**
 *    كان الوقودُ على العميل».
 *
 * ويثبت الحارسَ حيًّا: يحاول اعتمادَ الموقع **قبل** الإسناد فيجب أن يُرفض بـ422.
 *
 * التشغيل: php database/seeds/con02_live_event.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__, 2) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'con02 live event');

require_once dirname(__DIR__, 2) . '/app/Services/Contract/AttributionService.php';
require_once dirname(__DIR__, 2) . '/app/Services/EffectFanout.php';
require_once dirname(__DIR__, 2) . '/app/Services/Unit/TimesheetEntryService.php';

use App\Services\Contract\AttributionService as ATT;
use App\Services\EffectFanout as FAN;
use App\Services\Unit\TimesheetEntryService as TES;

$MANIFEST = __DIR__ . '/con02_seed_manifest.json';
$SEED_TAG = 'CON02-SEED-20260728';
$COMPANY  = 4;
$ACTOR    = 1;
$WORKDATE = '2026-07-28';   // اليوم
$OPERATION = 13;            // معدة HX 340 SL · العقد 5 · المشروع 4 · المورد 5
$EMPLOYEE  = 7;

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();

function say($m)  { fwrite(STDOUT, $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── " . $m . "\n"); }
function die_with($m) { fwrite(STDERR, "\n✘ " . $m . "\n"); exit(1); }
function money($n, $cur) { return number_format((float) $n, 2) . ' ' . $cur; }

if (!file_exists($MANIFEST)) { die_with('لا بيانَ جرد — شغّل con02_seed.php أولًا.'); }
$manifest = json_decode(file_get_contents($MANIFEST), true);
$PILOT = (int) $manifest['pilot'];

if (!ATT::enforced()) {
    die_with('العَلَم EMS_ATTRIBUTION_MATRIX ما زال off — اقلبه إلى on أولًا.');
}
function track(&$mf, $t, $id) { if ((int) $id > 0) { $mf['inserted'][$t][] = (int) $id; } }

$root = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                   ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($root->connect_error) { die_with('root: ' . $root->connect_error); }
$root->set_charset('utf8mb4');

// ═══════════════════════════════════════════════════════════════════════════
// ① صفُّ الدوام الحي
// ═══════════════════════════════════════════════════════════════════════════
head("① صفُّ الدوام — العقد #{$PILOT} · {$WORKDATE}");

$dup = $root->query("SELECT id FROM timesheet WHERE operator='{$OPERATION}' AND date='{$WORKDATE}' AND shift='D'");
if ($dup && $dup->num_rows > 0) {
    die_with('يوجد صفُّ دوامٍ لهذه المعدة في هذا اليوم — أزِل البذرَ أولًا (con02_rollback.php).');
}

$HOURS_WORK = 6.0;
$HOURS_FUEL = 1.5;
$HOURS_TECH = 0.5;
$TOTAL = $HOURS_WORK + $HOURS_FUEL + $HOURS_TECH;

// ⚠️ الدوامُ القديم **بلا عمودٍ للوقود** (EffectFanout يصرّح: «لا عمودَ له بعد»).
//    فساعاتُ الوقود تصل عبر سطور الزمن الصريحة إلى `unit_time_log` — وهو
//    المصدرُ القانونيُّ الذي تقرأ منه المروحةُ (EMS_UNIT_FANOUT_SOURCE=legal).
$ins = $root->prepare(
    "INSERT INTO timesheet (company_id, operator, employee_id, shift, date, shift_hours,
        executed_hours, standby_hours, dependence_hours, total_work_hours,
        hr_fault, maintenance_fault, marketing_fault, approval_fault, other_fault_hours,
        ts_supplier_stop_hours, ts_planned_stop_hours, ts_force_majeure_hours,
        total_fault_hours, operator_hours, tons_count, trips_count, meters_count,
        general_notes, type, user_id, status)
     VALUES (?, ?, ?, 'D', ?, ?, ?, 0, 0, ?, 0, ?, 0, 0, 0, 0, 0, 0, ?, ?, 0, 0, 0, ?, '1', ?, 1)");
$op = (string) $OPERATION; $emp = (string) $EMPLOYEE;
$shiftHours = 10.0; $note = '[' . $SEED_TAG . '] واقعةٌ تجريبيةٌ حية — CON-02 المرحلة (ج)';
$ins->bind_param('isssddddddsi', $COMPANY, $op, $emp, $WORKDATE, $shiftHours,
    $HOURS_WORK, $TOTAL, $HOURS_TECH, $HOURS_TECH, $TOTAL, $note, $ACTOR);
if (!$ins->execute()) { die_with('إدراج الدوام: ' . $ins->error); }
$TS = (int) $root->insert_id;
$ins->close();
track($manifest, 'timesheet', $TS);
say("   صفُّ الدوام #{$TS} — {$HOURS_WORK}س عمل · {$HOURS_FUEL}س وقود · {$HOURS_TECH}س عطلٌ فني");

// ═══════════════════════════════════════════════════════════════════════════
// ② مرآةُ السجل القانوني بسطور الزمن الثلاثة
// ═══════════════════════════════════════════════════════════════════════════
head('② المرآةُ القانونية — سطورُ الزمن الثلاثة');

$timeLines = array(
    array('ops_state' => 'actual_work',         'hours' => $HOURS_WORK, 'resp_party' => 'none',
          'cause_note' => $SEED_TAG . ' تشغيلٌ فعليّ'),
    array('ops_state' => 'fuel_logistics_stop', 'hours' => $HOURS_FUEL, 'resp_party' => 'none',
          'cause_note' => $SEED_TAG . ' نفادُ وقودٍ في الموقع'),
    array('ops_state' => 'tech_breakdown',      'hours' => $HOURS_TECH, 'resp_party' => 'none',
          'cause_note' => $SEED_TAG . ' عطلٌ فنيٌّ في المعدة'),
);
$mir = TES::mirrorFromTimesheet($conn, $gate, $TS, $ACTOR, $timeLines);
if (!$mir['ok']) { die_with('المرآة: ' . json_encode($mir, JSON_UNESCAPED_UNICODE)); }
$ENTRY = (int) $mir['entry_id'];
track($manifest, 'unit_entries', $ENTRY);

$lines = $gate->scopedQuery(array('scope' => array('l' => 'unit_time_log')),
    "SELECT l.id, l.ops_state, l.hours, l.obligation_type, l.billable,
            l.supplier_countable, l.operator_countable
       FROM unit_time_log l WHERE {TENANT_SCOPE} AND l.entry_id = ? ORDER BY l.id",
    array($ENTRY));
foreach ($lines as $l) { track($manifest, 'unit_time_log', $l['id']); }
say("   الواقعة #{$ENTRY} · " . count($lines) . " سطرَ زمنٍ · الأحكامُ كلُّها NULL بعد (لم يُقرَّر إسنادٌ)");

// ═══════════════════════════════════════════════════════════════════════════
// ③ إثباتُ الحارس حيًّا — اعتمادُ الموقع قبل الإسناد يجب أن يُرفض بـ422
// ═══════════════════════════════════════════════════════════════════════════
head('③ الحارسُ حيًّا — اعتمادُ الموقع قبل الإسناد');

$guard = TES::approve($conn, $gate, $COMPANY, $ENTRY, 'site', $ACTOR);
if ($guard['ok'] || (int) $guard['code'] !== 422) {
    die_with('الحارسُ لم يعمل: ' . json_encode($guard, JSON_UNESCAPED_UNICODE));
}
say('   ✔ رُفض بالرمز ' . $guard['code'] . ' — ونصُّ الرفض:');
foreach ($guard['reasons'] as $r) { say('       • ' . $r); }

// ═══════════════════════════════════════════════════════════════════════════
// ④ قرارُ الإسناد
// ═══════════════════════════════════════════════════════════════════════════
head('④ قرارُ الإسناد — بندُ التزامٍ لكل سطرِ توقف');

$assign = array();
foreach ($lines as $l) {
    if ($l['ops_state'] === 'fuel_logistics_stop')  { $assign[(int) $l['id']] = 'fuel'; }
    elseif ($l['ops_state'] === 'tech_breakdown')   { $assign[(int) $l['id']] = 'equipment_readiness'; }
    else                                            { $assign[(int) $l['id']] = null; }
}
$dec = ATT::decide($conn, $gate, $COMPANY, $ENTRY, $assign, $ACTOR);
if (!$dec['ok']) { die_with('الإسناد: ' . json_encode($dec, JSON_UNESCAPED_UNICODE)); }
say('   قُرِّر ' . $dec['decided'] . ' سطرًا.');

$lines = $gate->scopedQuery(array('scope' => array('l' => 'unit_time_log')),
    "SELECT l.id, l.ops_state, l.hours, l.obligation_type, l.billable,
            l.supplier_countable, l.operator_countable
       FROM unit_time_log l WHERE {TENANT_SCOPE} AND l.entry_id = ? ORDER BY l.id",
    array($ENTRY));

// ═══════════════════════════════════════════════════════════════════════════
// ⑤ الحكمُ القديم — بمحرّك النظام نفسِه · قراءةٌ خالصة
// ═══════════════════════════════════════════════════════════════════════════
// حين تكون لقطةُ الإسناد NULL يستدعي المحرّكُ `resolveRuling` على سياسة
// الساعة. فهذا **حرفيًّا** ما كان يحدث قبل المصفوفة.
$polClient = FAN::hourPolicy($gate, $COMPANY, 'client',   $PILOT, $WORKDATE);
$polSupp   = FAN::hourPolicy($gate, $COMPANY, 'supplier', $PILOT, $WORKDATE);

$oldBillableHrs = 0.0; $newBillableHrs = 0.0;
$oldSuppHrs = 0.0;     $newSuppHrs = 0.0;
$rows = array();
foreach ($lines as $l) {
    $h = (float) $l['hours'];
    $st = (string) $l['ops_state'];
    // القديم: بلا بندٍ (فالمصفوفةُ لم تكن موجودة) — القاعدةُ العامة
    $oc = FAN::resolveRuling($polClient, $st, null);
    $os = FAN::resolveRuling($polSupp,   $st, null);
    $oldBill = in_array($oc['ruling'], array('full', 'pct'), true);
    $oldSupp = in_array($os['ruling'], array('full', 'pct'), true);
    if ($oldBill) { $oldBillableHrs += $h; }
    if ($oldSupp) { $oldSuppHrs += $h; }
    // الجديد: اللقطةُ المقرَّرة
    $newBill = ((int) $l['billable'] === 1);
    $newSupp = ((int) $l['supplier_countable'] === 1);
    if ($newBill) { $newBillableHrs += $h; }
    if ($newSupp) { $newSuppHrs += $h; }
    $rows[] = array('state' => $st, 'hours' => $h, 'oblig' => $l['obligation_type'],
        'old_bill' => $oldBill, 'new_bill' => $newBill,
        'old_supp' => $oldSupp, 'new_supp' => $newSupp,
        'old_rule' => $oc['ruling'], 'new_op' => (int) $l['operator_countable'] === 1);
}

// ═══════════════════════════════════════════════════════════════════════════
// ⑥ السلسلةُ الكاملة ثم المروحة
// ═══════════════════════════════════════════════════════════════════════════
head('⑥ السلسلةُ الكاملة → قيدُ الإيراد');

foreach (array('site', 'client', 'supplier', 'operator', 'fleet', 'sales') as $stage) {
    $r = TES::approve($conn, $gate, $COMPANY, $ENTRY, $stage, $ACTOR);
    if (!$r['ok'] && (int) $r['code'] === 409) { continue; }   // مرحلةٌ لا تنطبق
    if (!$r['ok']) { say("   ⚠ {$stage}: " . json_encode($r['reasons'] ?? array(), JSON_UNESCAPED_UNICODE)); continue; }
    say("   {$stage} → " . $r['state']);
    if ($r['state'] === 'sales_approved') { break; }
}

// التحويلُ المالي — نفسُ ما تفعله شاشةُ المالية (Finance/unit_records_fin.php)
$fan = null;
$gate->runInTransaction(function ($g) use (&$fan, $conn, $TS, $ACTOR) {
    $fan = FAN::forTimesheetId($conn, $g, $TS, $ACTOR);
}, 'con02 live event fanout');

say('   المروحة: ' . count($fan['effects']) . ' أثرًا · ' . count($fan['skipped']) . ' متروكًا');
foreach ($fan['effects'] as $e) {
    say(sprintf('       ✔ %-16s #%-5s %s', $e['effect'], $e['target_id'],
        isset($e['amount']) ? money($e['amount'], $e['currency'] ?? '') : ''));
}
foreach ($fan['skipped'] as $s) {
    say('       ○ ' . $s['effect'] . ' — ' . $s['reason']);
}

// تتبّعُ كلِّ ما تولّد لبابِ الرجوع
$gen = $root->query("SELECT id, root_event_id FROM fin_financial_events
                      WHERE entity_type='timesheet' AND entity_id={$TS}");
$rootIds = array();
while ($g = $gen->fetch_assoc()) {
    track($manifest, 'fin_financial_events', $g['id']);
    if (!empty($g['root_event_id'])) { $rootIds[(int) $g['root_event_id']] = true; }
}
$lk = $root->query("SELECT id FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref={$TS}");
while ($g = $lk->fetch_assoc()) { track($manifest, 'fin_event_links', $g['id']); }
$aw = $root->query("SELECT id FROM unit_party_awards WHERE source_kind='timesheet' AND source_ref={$TS}");
while ($g = $aw->fetch_assoc()) { track($manifest, 'unit_party_awards', $g['id']); }
// ⚠️ أثرُ `supplier_due` يكتب صفًّا في `fin_dues` مربوطًا بالقيد — وهو **لا يظهر
//    في عدّادات الجداول الرئيسية**، فنسيانُ تتبّعه يترك ذمّةَ موردٍ يتيمةً بعد
//    التراجع. (قِيس فعلًا: صفّان يتيمان قبل هذا السطر.)
$fdIds = array();
foreach ($manifest['inserted']['fin_financial_events'] ?? array() as $fid) { $fdIds[] = (int) $fid; }
if (!empty($fdIds)) {
    $fd = $root->query("SELECT id FROM fin_dues WHERE event_id IN (" . implode(',', $fdIds) . ")");
    while ($g = $fd->fetch_assoc()) { track($manifest, 'fin_dues', $g['id']); }
}
$ap = $root->query("SELECT id FROM unit_approvals WHERE entry_id={$ENTRY}");
while ($g = $ap->fetch_assoc()) { track($manifest, 'unit_approvals', $g['id']); }
$be = $root->query("SELECT id FROM ems_business_events
                     WHERE (entity_type='unit_entry' AND entity_id={$ENTRY})
                        OR (entity_type='timesheet' AND entity_id={$TS})");
while ($g = $be->fetch_assoc()) { track($manifest, 'ems_business_events', $g['id']); }
foreach (array_keys($rootIds) as $rid) { track($manifest, 'ems_business_events', $rid); }

// ═══════════════════════════════════════════════════════════════════════════
// ⑦ الجدولُ — القديمُ مقابلَ الجديد
// ═══════════════════════════════════════════════════════════════════════════
$rev = null;
$rr = $root->query("SELECT fe.id, fe.amount, fe.currency, fe.quantity, fe.unit
                      FROM fin_financial_events fe
                      JOIN fin_event_links l ON l.target_id = fe.id
                       AND l.parent_kind='timesheet' AND l.parent_ref={$TS}
                       AND l.effect_type='revenue_event'
                     WHERE fe.event_type='revenue' LIMIT 1");
if ($rr) { $rev = $rr->fetch_assoc(); }

$price = 0.0; $cur = '';
if ($rev && (float) $rev['quantity'] > 0) {
    $price = round((float) $rev['amount'] / (float) $rev['quantity'], 4);
    $cur = (string) $rev['currency'];
}

$AR_ST = array('actual_work' => 'تشغيلٌ فعليّ', 'fuel_logistics_stop' => 'توقفُ وقود',
               'tech_breakdown' => 'عطلٌ فنيّ');
$yn = function ($b) { return $b ? 'نعم' : 'لا'; };

head('⑦ الحكمُ القديم مقابلَ الحكم الجديد');
say('');
say('   السطر                 │ القديم (بلا مصفوفة)          │ الجديد (بالمصفوفة)');
say('   ──────────────────────┼──────────────────────────────┼──────────────────────────────');
foreach ($rows as $r) {
    $label = (isset($AR_ST[$r['state']]) ? $AR_ST[$r['state']] : $r['state']) . ' ' . rtrim(rtrim(number_format($r['hours'], 1), '0'), '.') . 'س';
    say(sprintf("   %-21s │ فوترة:%-4s مورد:%-4s        │ فوترة:%-4s مورد:%-4s مشغّل:%-4s",
        $label, $yn($r['old_bill']), $yn($r['old_supp']),
        $yn($r['new_bill']), $yn($r['new_supp']), $yn($r['new_op'])));
}
say('   ──────────────────────┴──────────────────────────────┴──────────────────────────────');
say('');
say(sprintf('   ساعاتٌ مفوترةٌ للعميل      القديم: %s س          الجديد: %s س',
    rtrim(rtrim(number_format($oldBillableHrs, 2), '0'), '.'),
    rtrim(rtrim(number_format($newBillableHrs, 2), '0'), '.')));

if ($rev) {
    $oldAmount = round($oldBillableHrs * $price, 2);
    $newAmount = round((float) $rev['amount'], 2);
    say(sprintf('   سعرُ الساعة في العقد        %s', money($price, $cur)));
    say('');
    say('   ┌───────────────────────────────┬──────────────────┐');
    say(sprintf('   │ الإيرادُ بالحكم القديم        │ %-16s │', money($oldAmount, $cur)));
    say(sprintf('   │ الإيرادُ بالحكم الجديد        │ %-16s │', money($newAmount, $cur)));
    say('   ├───────────────────────────────┼──────────────────┤');
    say(sprintf('   │ **الفرق**                     │ %-16s │', money($newAmount - $oldAmount, $cur)));
    say('   └───────────────────────────────┴──────────────────┘');
    say('');
    say('   قيدُ الإيراد الفعليُّ المتولّد: #' . $rev['id'] . ' — ' . money($rev['amount'], $cur)
        . ' عن ' . rtrim(rtrim((string) $rev['quantity'], '0'), '.') . ' ' . $rev['unit']);
    $manifest['live_event'] = array(
        'ts' => $TS, 'entry' => $ENTRY, 'work_date' => $WORKDATE, 'contract' => $PILOT,
        'revenue_event' => (int) $rev['id'], 'currency' => $cur, 'unit_price' => $price,
        'old_billable_hours' => $oldBillableHrs, 'new_billable_hours' => $newBillableHrs,
        'old_amount' => $oldAmount, 'new_amount' => $newAmount,
        'delta' => round($newAmount - $oldAmount, 2),
    );
} else {
    say('   ⚠ لم يتولّد قيدُ إيراد — راجع «المتروك» أعلاه.');
    $manifest['live_event'] = array('ts' => $TS, 'entry' => $ENTRY, 'revenue_event' => null);
}

say('');
say('   ⚠️ الفرقُ أعلاه ناتجٌ عن **فرضِ تجربةٍ معلن** (الوقودُ على العميل)، لا عن بندِ عقدٍ موقَّع.');
say('      اقرأه: «هذا ما سيحدث لو كان الوقودُ على العميل».');

file_put_contents($MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
say("\n   بيانُ الجرد حُدِّث — الرجوع: php database/seeds/con02_rollback.php\n");
