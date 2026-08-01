<?php
/**
 * tests/n10_guaranteed_minimum_test.php — N-10 الحد الأدنى المضمون (البوابة ②)
 * ═══════════════════════════════════════════════════════════════════════════
 * الثوابت: ① بند مستقل باسمه (guaranteed_min) لا تضخيم كميات · ② عجز صفر =
 * لا بند · ③ عاطل (بند واحد لكل مستخلص) · ④ معتمد → 423 · ⑤ لا حد في العقد
 * = لا بند · ⑥ المتنازَع لا يُحسب منفَّذًا · ⑦ الإجماليات تتبع البند.
 * التشغيل: php tests/n10_guaranteed_minimum_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'N10 test');
require_once dirname(__DIR__) . '/app/Services/Revenue/GuaranteedMinimumService.php';

use App\Services\Revenue\GuaranteedMinimumService as GM;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate = ems_tenant_db();
$CO = 4; $MARK = 'N10T';
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE l FROM claim_lines l JOIN claims c ON c.id = l.claim_id WHERE c.claim_no LIKE '{$MARK}%'");
    $conn->query("DELETE FROM claims WHERE claim_no LIKE '{$MARK}%'");
    $conn->query("DELETE l FROM client_contract_lines l JOIN contracts c ON c.id = l.contract_id WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

// بذر: عقد بحد مضمون 300 ساعة/شهر + مستخلص شهر بـ250 ساعة منفَّذة
$conn->query("INSERT INTO project (company_id, name, client, location, total) VALUES ({$CO},'مشروع {$MARK}','ع','م','0')");
$PRJ = (int) $conn->insert_id;
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days, actual_start, actual_end,
              first_party, second_party, contract_status, project_id, price_currency_contract, hours_monthly_target, created_at)
              VALUES ({$CO},'2026-06-01',90,'2026-06-01','2026-08-31','طرف {$MARK}','ع','قيد التنفيذ',{$PRJ},'دولار',300,NOW())");
$CID = (int) $conn->insert_id;
$conn->query("INSERT INTO claims (company_id, claim_no, contract_id, client_id, project_id, period_from, period_to, currency, gross_amount, net_amount, state, created_by)
              VALUES ({$CO},'{$MARK}-01',{$CID},1,{$PRJ},'2026-07-01','2026-07-31','USD',2500.00,2500.00,'draft',1)");
$CLAIM = (int) $conn->insert_id;
$conn->query("INSERT INTO claim_lines (company_id, claim_id, source_kind, source_ref, unit_type, qty, unit_price, amount, work_date)
              VALUES ({$CO},{$CLAIM},'timesheet',900001,'hour',200.00,10.00,2000.00,'2026-07-15'),
                     ({$CO},{$CLAIM},'timesheet',900002,'hour',50.00,10.00,500.00,'2026-07-20')");

$r = GM::appendLine($conn, $gate, $CO, $CLAIM, 1);
check($r['ok'] && $r['code'] === 201 && abs($r['shortfall'] - 50.0) < 0.005, '① عجز 50 ساعة (300 مضمونة − 250 منفَّذة) بندًا مستقلًّا');
$q = $conn->query("SELECT source_kind, qty, unit_price, amount FROM claim_lines WHERE claim_id={$CLAIM} AND source_kind='guaranteed_min'")->fetch_assoc();
check($q && (float) $q['qty'] === 50.0 && (float) $q['amount'] === 500.0, 'البند باسمه guaranteed_min: 50 × 10 = 500 — لا مدسوسًا في كميات التشغيل');
$q = $conn->query("SELECT SUM(qty) t FROM claim_lines WHERE claim_id={$CLAIM} AND source_kind='timesheet'")->fetch_assoc();
check((float) $q['t'] === 250.0, '② كميات التشغيل لم تُمسّ (250 كما هي)');
$q = $conn->query("SELECT gross_amount, net_amount FROM claims WHERE id={$CLAIM}")->fetch_assoc();
check((float) $q['gross_amount'] === 3000.0, '⑦ الإجمالي تبع البند: 2500 + 500 = 3000');

$r2 = GM::appendLine($conn, $gate, $CO, $CLAIM, 1);
check($r2['ok'] && $r2['code'] === 200 && mb_strpos($r2['reason'], 'عاطل') !== false, '③ إعادة الاستدعاء عاطلة — بند واحد لا اثنان');
$q = $conn->query("SELECT COUNT(*) c FROM claim_lines WHERE claim_id={$CLAIM} AND source_kind='guaranteed_min'")->fetch_assoc();
check((int) $q['c'] === 1, 'وصف البنود واحد');
$q = $conn->query("SELECT gross_amount FROM claims WHERE id={$CLAIM}")->fetch_assoc();
check((float) $q['gross_amount'] === 3000.0, 'والإجمالي لم يتضاعف');

// ④ معتمد → 423
$conn->query("UPDATE claims SET state='approved' WHERE id={$CLAIM}");
$r = GM::appendLine($conn, $gate, $CO, $CLAIM, 1);
check(!$r['ok'] && $r['code'] === 423, '④ مستخلص معتمد ⇒ 423 — التصحيح بإشعار لا بتعديل');
$conn->query("UPDATE claims SET state='draft' WHERE id={$CLAIM}");

// ⑤ عقد بلا حد → لا بند
$conn->query("UPDATE contracts SET hours_monthly_target=0 WHERE id={$CID}");
$conn->query("DELETE FROM claim_lines WHERE claim_id={$CLAIM} AND source_kind='guaranteed_min'");
$r = GM::appendLine($conn, $gate, $CO, $CLAIM, 1);
check($r['ok'] && $r['code'] === 200 && mb_strpos($r['reason'], 'لا حدَّ') !== false, '⑤ عقد بلا حد مضمون — لا بند ولا افتراض');
$conn->query("UPDATE contracts SET hours_monthly_target=240 WHERE id={$CID}");

// ② منفَّذ ≥ مضمون → لا بند
$r = GM::appendLine($conn, $gate, $CO, $CLAIM, 1);
check($r['ok'] && $r['code'] === 200 && $r['shortfall'] === 0.0, '② منفَّذ 250 ≥ مضمون 240 — لا بند بصفر');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
