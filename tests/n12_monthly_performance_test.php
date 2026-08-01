<?php
/**
 * tests/n12_monthly_performance_test.php — N-12 الأداء الشهري والإسناد (البوابة ①)
 * ═══════════════════════════════════════════════════════════════════════════
 * الثوابت المختبرة (PLAN-04 §2.2 · PLAN-05 §11):
 *   ① المنفَّذ **مجمَّع من container_consumption** — لا يُدخل يدويًّا.
 *   ② السبب من القائمة المحكومة حصرًا — نص حر يُرفض 422.
 *   ③ سبب بلا بند التزام مُجاز مقابل → 422 («سبب بلا بند لا يُقبل»).
 *   ④ الطرف المتحمل يُشتق من البند آليًّا — لا يُكتب حرًّا (لا معامل bearer أصلًا).
 *   ⑤ سبب «أخرى» بلا بند صريح → 422 — لا افتراض صامتًا.
 *   ⑥ إقفال شهر وفيه عجز بلا إسناد → 422 · وبعد الإسناد يقع.
 *   ⑦ الأحكام الثلاثة تُشتق: مسنَد للعميل → يُفوتر ويستحقه المورد ·
 *      مسنَد للمورد → لا يُفوتر ولا يستحقه.
 *   ⑧ **ليس مصدر كمية الفوترة**: صفر قارئ لجدول monthly_performance في
 *      مسارات المستخلص والفوترة (فحص قارئين كنمط containers_structure_test).
 * التشغيل: php tests/n12_monthly_performance_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'N12 test');
require_once dirname(__DIR__) . '/app/Services/MonthlyPerformanceService.php';

use App\Services\MonthlyPerformanceService as MPS;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
mysqli_report(MYSQLI_REPORT_OFF);
$gate = ems_tenant_db();
$CO = 4; $MARK = 'N12T';

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE d FROM monthly_performance_downtime d JOIN monthly_performance m ON m.id = d.perf_id
                   JOIN op_containers c ON c.id = m.container_id WHERE c.container_no LIKE '{$MARK}%'");
    $conn->query("DELETE m FROM monthly_performance m JOIN op_containers c ON c.id = m.container_id WHERE c.container_no LIKE '{$MARK}%'");
    $conn->query("DELETE cc FROM container_consumption cc JOIN op_containers c ON c.id = cc.container_id WHERE c.container_no LIKE '{$MARK}%'");
    for ($i = 0; $i < 5; $i++) { $conn->query("DELETE FROM op_containers WHERE container_no LIKE '{$MARK}%' AND id NOT IN (SELECT * FROM (SELECT parent_id FROM op_containers WHERE container_no LIKE '{$MARK}%' AND parent_id IS NOT NULL) x)"); }
    $conn->query("DELETE FROM op_containers WHERE container_no LIKE '{$MARK}%'");
    $conn->query("DELETE o FROM contract_obligations o JOIN contracts c ON c.id = o.client_contract_id WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

// ── البذرة: عقد معزول (لا تنازعه التزامات حية) + مقعد + التزامات مُجازة + استهلاك ──
$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروع {$MARK}', 'عميل {$MARK}', 'موقع {$MARK}', '0')");
$PRJ = (int) $conn->insert_id;
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days, actual_start, actual_end,
              first_party, second_party, contract_status, project_id, price_currency_contract, created_at)
              VALUES ({$CO}, '2026-06-01', 90, '2026-06-01', '2026-08-31', 'طرف {$MARK}', 'عميل {$MARK}', 'قيد التنفيذ', {$PRJ}, 'دولار', NOW())");
$CID = (int) $conn->insert_id;
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, contract_item_id, unit_type, cap_qty, project_id)
              VALUES ({$CO}, '{$MARK}-M', 'رئيسية', NULL, {$CID}, NULL, 'hour', 600.00, 10)");
$MAIN = (int) $conn->insert_id;
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type, cap_qty, supplier_id)
              VALUES ({$CO}, '{$MARK}-S', 'مورد', {$MAIN}, {$CID}, 'hour', 600.00, 1)");
$SUP = (int) $conn->insert_id;
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type, cap_qty, role_kind, seat_no, seat_kind, contract_hours_monthly)
              VALUES ({$CO}, '{$MARK}-E', 'معدة', {$SUP}, {$CID}, 'hour', 600.00, 'أساسية', 91, 'contractual_seat', 300.00)");
$SEAT = (int) $conn->insert_id;

// التزامات مُجازة: جاهزية المعدة على المورد · الطريق على العميل (سريان يغطي 2026-07)
$conn->query("INSERT INTO contract_obligations (company_id, client_contract_id, obligation_type, obligor, effect_on_billing, approval_state, approved_by, approved_at, valid_from, created_by)
              VALUES ({$CO}, {$CID}, 'equipment_readiness', 'supplier', 'non_billable', 'approved', 19, NOW(), '2026-01-01', 999120)");
$conn->query("INSERT INTO contract_obligations (company_id, client_contract_id, obligation_type, obligor, effect_on_billing, approval_state, approved_by, approved_at, valid_from, created_by)
              VALUES ({$CO}, {$CID}, 'access_road', 'client', 'billable_standby', 'approved', 19, NOW(), '2026-01-01', 999120)");

// استهلاك حي على المقعد: 180 ساعة + 40 طنًّا في 2026-07
$conn->query("INSERT INTO container_consumption (company_id, container_id, source_kind, source_ref, qty, unit_type, consumed_on, idem_key)
              VALUES ({$CO}, {$SEAT}, 'manual', 1, 120.00, 'hour', '2026-07-05', '{$MARK}:c1'),
                     ({$CO}, {$SEAT}, 'manual', 2, 60.00, 'hour', '2026-07-18', '{$MARK}:c2'),
                     ({$CO}, {$SEAT}, 'manual', 3, 40.00, 'ton', '2026-07-18', '{$MARK}:c3')");

head('① السجل مشتق مجمَّع من دفتر الاستهلاك');
$r = MPS::openOrRefresh($gate, $CO, $SEAT, '2026-07', array(), 1);
check($r['ok'] && abs($r['executed_hours'] - 180.0) < 0.005, "المنفَّذ 180 ساعة مجمَّعًا لا مُدخلًا (الفعلي: {$r['executed_hours']})");
$PID = $r['perf_id'];
$q = $conn->query("SELECT contract_hours, tons, shortfall_hours, completion_pct FROM monthly_performance WHERE id={$PID}")->fetch_assoc();
check(abs((float) $q['contract_hours'] - 300.0) < 0.005, 'التعاقدية 300 من المقعد نفسه — لا إدخال ثانٍ');
check(abs((float) $q['tons'] - 40.0) < 0.005, 'الإنتاج (طن) مجمَّع معه');
check(abs((float) $q['shortfall_hours'] - 120.0) < 0.005, 'العجز محسوب مولَّدًا (300-180=120) — لا مصدر ثانٍ للرقم');
check(abs((float) $q['completion_pct'] - 60.0) < 0.01, 'نسبة الإنجاز محسوبة 60%');

head('②③④⑤ الإسناد المحكوم');
$r = MPS::addDowntime($gate, $CO, $PID, 'سبب حر', 10, array(), 1);
check(!$r['ok'] && $r['code'] === 422, 'سبب نصي حر ⇒ 422 — القائمة المحكومة حصرًا');
$r = MPS::addDowntime($gate, $CO, $PID, 'hr_delay', 10, array(), 1);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'بلا بندٍ مقابل') !== false,
      'سبب ببند غير مُجاز (operators لا التزام له) ⇒ 422: سبب بلا بند لا يُقبل');
$r = MPS::addDowntime($gate, $CO, $PID, 'other', 5, array(), 1);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'صريح') !== false, 'سبب «أخرى» بلا بند صريح ⇒ 422');

$r = MPS::addDowntime($gate, $CO, $PID, 'maintenance_downtime', 70, array(), 1);
check($r['ok'] && $r['bearer'] === 'supplier', 'تعطل صيانة ⇒ الطرف المتحمل supplier مُشتقًّا من بند الجاهزية — لا كتابة حرة');
$r2 = MPS::addDowntime($gate, $CO, $PID, 'unexecuted_loss', 50, array(), 1);
check($r2['ok'] && $r2['bearer'] === 'client', 'فاقد غير منفَّذ ⇒ client من بند الطريق');
$r = MPS::addDowntime($gate, $CO, $PID, 'maintenance_downtime', 5, array(), 1);
check(!$r['ok'] && $r['code'] === 409, 'تكرار السبب لنفس الشهر ⇒ 409');

head('⑥ الإقفال — لا شهر يُقفل وفيه ساعة بلا طرف');
// العجز 120 والمسنَد حتى الآن 120 (70+50) — نختبر الرفض أولًا بإزالة الإسناد مؤقتًا... بل نبني الرفض قبل الاكتمال:
$conn->query("DELETE FROM monthly_performance_downtime WHERE perf_id={$PID} AND reason_code='unexecuted_loss'");
$r = MPS::closeMonth($gate, $CO, $PID, 6);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'بلا طرفٍ متحمل') !== false,
      'عجز 120 ومسنَد 70 ⇒ الإقفال يُرفض 422: ' . mb_substr($r['reason'], 0, 70));
$r = MPS::addDowntime($gate, $CO, $PID, 'unexecuted_loss', 50, array(), 1);
check($r['ok'], 'أُعيد إسناد الفارق (50 للعميل)');
$r = MPS::closeMonth($gate, $CO, $PID, 6);
check($r['ok'], 'وباكتمال الإسناد يقع الإقفال: ' . $r['reason']);
$r = MPS::addDowntime($gate, $CO, $PID, 'holidays_downtime', 3, array(), 1);
check(!$r['ok'] && $r['code'] === 423, 'الكتابة بعد الإقفال ⇒ 423');
$r = MPS::closeMonth($gate, $CO, $PID, 6);
check($r['ok'] && mb_strpos($r['reason'], 'عاطل') !== false, 'إقفال مكرر فعل عاطل');

head('⑦ الأحكام الثلاثة تُشتق من الإسناد');
$imp = MPS::billingImplications($gate, $CO, $PID);
$byReason = array();
foreach ($imp as $i) { $byReason[$i['reason']] = $i; }
check($byReason['unexecuted_loss']['billable'] === true && $byReason['unexecuted_loss']['supplier_entitled'] === true,
      'مسنَد للعميل (billable_standby): يُفوتر ويستحقه المورد');
check($byReason['maintenance_downtime']['billable'] === false && $byReason['maintenance_downtime']['supplier_penalized'] === true,
      'مسنَد للمورد: لا يُفوتر ولا يستحقه ويُجزى');

head('⑧ ليس مصدر كمية الفوترة — صفر قارئ في مسارات الفوترة');
$billingFiles = array_merge(
    glob(dirname(__DIR__) . '/Finance/*.php') ?: array(),
    glob(dirname(__DIR__) . '/Clients/*.php') ?: array(),
    array_filter(glob(dirname(__DIR__) . '/app/Services/*.php') ?: array(), function ($f) {
        return stripos(basename($f), 'claim') !== false || stripos(basename($f), 'invoice') !== false
            || stripos(basename($f), 'Attribution') !== false || stripos(basename($f), 'Fanout') !== false;
    })
);
$readers = 0;
foreach ($billingFiles as $f) {
    $src = @file_get_contents($f);
    if ($src !== false && strpos($src, 'monthly_performance') !== false) { $readers++; }
}
check($readers === 0, "صفر قارئ لسجل الأداء في مسارات الفوترة والمستخلص: {$readers} — «لا يُفوتر منه رقم»");

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
