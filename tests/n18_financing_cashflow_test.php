<?php
/**
 * tests/n18_financing_cashflow_test.php — N-18 التمويل والتدفق (البوابة ③)
 * ═══════════════════════════════════════════════════════════════════════════
 * ① قسط مستحق يظهر في التدفق المتوقع مقابل خطة التحصيل (F6-تدفق) · ② العجز
 * يُكشف بتاريخه ومقداره قبل وقوعه · ③ لا تُجمع عملتان في رقم · ④ F8: أصل
 * 800 ساعة على مشروعين ⇒ التكلفة بنسبة الساعات لا بالتساوي · ⑤ لا ساعات
 * لأصول مموَّلة ⇒ صفر تحميل (لا يُحمَّل مشروع كلفة أصول لم يستعملها) ·
 * ⑥ المخرج Proposed كلفة مجرَّدة بلا كشف الممول.
 * التشغيل: php tests/n18_financing_cashflow_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__) . '/app/Services/Financing/FinancingService.php';
require_once dirname(__DIR__) . '/app/Services/Financing/CashflowService.php';

use App\Services\Financing\FinancingService as FS;
use App\Services\Financing\CashflowService as CF;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $MARK = 'N18T'; $EQ_F = 999818;
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

$teardown = function () use ($conn, $MARK, $EQ_F) {
    $conn->query("DELETE i FROM financing_installments i JOIN financing_operations o ON o.op_id=i.op_id WHERE o.op_code LIKE '{$MARK}%'");
    $conn->query("DELETE FROM asset_ownership_shares WHERE asset_id = {$EQ_F}");
    $conn->query("DELETE FROM financing_operations WHERE op_code LIKE '{$MARK}%'");
    $conn->query("DELETE FROM entity_roles WHERE doc_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM legal_entities WHERE legal_name LIKE '%{$MARK}%'");
    $conn->query("DELETE cc FROM container_consumption cc JOIN op_containers c ON c.id=cc.container_id WHERE c.container_no LIKE '{$MARK}%'");
    for ($i = 0; $i < 5; $i++) { $conn->query("DELETE FROM op_containers WHERE container_no LIKE '{$MARK}%' AND id NOT IN (SELECT * FROM (SELECT parent_id FROM op_containers WHERE container_no LIKE '{$MARK}%' AND parent_id IS NOT NULL) x)"); }
    $conn->query("DELETE p FROM contract_payment_schedule p JOIN contracts c ON c.id=p.contract_id WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

// بذر ممول + عملية بقسطين قادمين (30 يومًا) بعملة معزولة TSY
$conn->query("INSERT INTO legal_entities (legal_name, country, registry_authority, commercial_reg) VALUES ('ممول {$MARK}','SD','السجل التجاري','{$MARK}-F1')");
$FIN = (int) $conn->insert_id;
$conn->query("INSERT INTO entity_roles (entity_id, role, valid_from, doc_ref) VALUES ({$FIN},'financier',CURDATE(),'{$MARK}-r')");
$r = FS::createOperation($conn, $CO, array('op_code' => $MARK . '-OP', 'financier_entity_id' => $FIN, 'model_code' => 'murabaha', 'currency' => 'TSY', 'capital' => 24000, 'installments_no' => 2), 1);
$OP = $r['op_id'];
FS::generateInstallments($conn, $CO, $OP, date('Y-m-d', strtotime('+10 days')));

head('①②③ — الأقساط في التدفق مقابل التحصيل');
$o = CF::financingOutlook($conn, $CO);
$b30 = null;
foreach ($o['buckets'][30] as $row) { if ($row['currency'] === 'TSY') { $b30 = $row; } }
check($b30 !== null && (float) $b30['installments_due'] === 12000.0, '① القسط المستحق (12000 TSY) في نافذة 30 يومًا مقابل التحصيل');
check((float) $b30['expected_collection'] === 0.0 && (float) $b30['gap'] === -12000.0, '② العجز مكشوف قبل وقوعه: −12000');
$def = null;
foreach ($o['deficits'] as $d) { if ($d['currency'] === 'TSY') { $def = $d; break; } }
check($def !== null && $def['shortfall'] >= 12000.0, 'والعجز بتاريخه ومقداره ومحفّزه: ' . ($def ? $def['trigger'] . ' في ' . $def['date'] : '—'));
check(!isset($b30['total_mixed']), '③ ولا جمع لعملتين في رقم — الصفوف لكل عملة');

// خطة تحصيل تغطي القسط الأول — العجز يتقلص
$conn->query("INSERT INTO project (company_id, name, client, location, total) VALUES ({$CO},'مشروع {$MARK}','ع','م','0')");
$PRJ = (int) $conn->insert_id;
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days, actual_start, actual_end, first_party, second_party, contract_status, project_id, price_currency_contract, created_at)
              VALUES ({$CO},'2026-08-01',60,'2026-08-01','2026-09-30','طرف {$MARK}','ع','قيد التنفيذ',{$PRJ},'دولار',NOW())");
$CID = (int) $conn->insert_id;
$conn->query("INSERT INTO contract_payment_schedule (company_id, contract_id, version, effective_from, seq, pattern, payment_kind, amount_expected, currency, due_date, state, created_by)
              VALUES ({$CO},{$CID},1,'2026-08-01',1,'monthly_claim','monthly_settlement',20000,'TSY','" . date('Y-m-d', strtotime('+8 days')) . "','not_due',1)");
$o = CF::financingOutlook($conn, $CO);
$b30 = null;
foreach ($o['buckets'][30] as $row) { if ($row['currency'] === 'TSY') { $b30 = $row; } }
check($b30 !== null && (float) $b30['gap'] === 8000.0, 'وبخطة تحصيل 20000 تصير الفجوة +8000 — المقارنة حية بالعملة الواحدة');

head('④⑤⑥ — F8: توزيع التكلفة بنسبة الساعات');
// أصل مموَّل يعمل على مشروعين: 600 ساعة و200 ساعة (Σ 800)
FS::applyShares($conn, $CO, 'equipment', $EQ_F, array(array('financier' => $FIN, 'percent' => 100.00, 'from' => '2026-01-01')), null, 1);
$conn->query("INSERT INTO project (company_id, name, client, location, total) VALUES ({$CO},'مشروع {$MARK} ب','ع','م','0')");
$PRJ2 = (int) $conn->insert_id;
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, contract_item_id, unit_type, cap_qty, project_id) VALUES ({$CO},'{$MARK}-M','رئيسية',NULL,{$CID},NULL,'hour',1000,{$PRJ})");
$M = (int) $conn->insert_id;
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type, cap_qty, equipment_id, project_id) VALUES ({$CO},'{$MARK}-E1','معدة',{$M},{$CID},'hour',600,{$EQ_F},{$PRJ})");
$E1 = (int) $conn->insert_id;
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type, cap_qty, equipment_id, project_id) VALUES ({$CO},'{$MARK}-E2','معدة',{$M},{$CID},'hour',400,{$EQ_F},{$PRJ2})");
$E2 = (int) $conn->insert_id;
$conn->query("INSERT INTO container_consumption (company_id, container_id, source_kind, source_ref, qty, unit_type, consumed_on, idem_key)
              VALUES ({$CO},{$E1},'manual',1,600,'hour','2026-08-10','{$MARK}:h1'), ({$CO},{$E2},'manual',2,200,'hour','2026-08-12','{$MARK}:h2')");

$r = CF::allocateFinancingCost($conn, $CO, '2026-08', array('amount' => 4000, 'currency' => 'TSY'));
check($r['ok'] && $r['total_hours'] === 800.0, 'F8: 800 ساعة مقروءة من دفتر الاستهلاك — لا تقدير');
$byP = array();
foreach ($r['lines'] as $l) { $byP[$l['project_id']] = $l; }
check(isset($byP[$PRJ]) && (float) $byP[$PRJ]['amount'] === 3000.0, 'المشروع أ: 600/800 × 4000 = 3000 — بنسبة الساعات لا بالتساوي');
check(isset($byP[$PRJ2]) && (float) $byP[$PRJ2]['amount'] === 1000.0, 'المشروع ب: 200/800 × 4000 = 1000');
check($byP[$PRJ]['state'] === 'Proposed' && strpos(json_encode($byP[$PRJ], JSON_UNESCAPED_UNICODE), 'financier') === false,
      '⑥ السطور Proposed (بوابة الاستحقاق) — كلفة مجرَّدة **بلا كشف الممول**');
$r = CF::allocateFinancingCost($conn, $CO, '2031-01', array('amount' => 4000, 'currency' => 'TSY'));
check($r['ok'] && count($r['lines']) === 0, '⑤ فترة بلا ساعات لأصول مموَّلة ⇒ صفر تحميل معلَنًا');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
