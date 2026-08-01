<?php
/**
 * tests/financing_deviations_test.php — أوراق الانحراف الثلاث (البوابة ③)
 * ═══════════════════════════════════════════════════════════════════════════
 * F7: ① عقد بلا حركة في الدفتر يظهر بقرار مطلوب · ② قسط متجاوز استحقاقه
 * (فروق السداد) · ③ حصة مفتوحة لعملية منتهية (الخروج غير المسجَّل) ·
 * لا يُغلق صف بلا قرار ومستند (422 + CHECK بنيوي) · الرصد Insert-only عاطل.
 * التشغيل: php tests/financing_deviations_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__) . '/app/Services/Financing/FinancingService.php';

use App\Services\Financing\FinancingService as FS;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $MARK = 'FDVT';
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM financing_deviations WHERE subject_ref IN (SELECT CONCAT('op:',op_id) FROM financing_operations WHERE op_code LIKE '{$MARK}%')");
    $conn->query("DELETE d FROM financing_deviations d WHERE d.subject_ref IN (SELECT CONCAT('inst:',i.inst_id) FROM financing_installments i JOIN financing_operations o ON o.op_id=i.op_id WHERE o.op_code LIKE '{$MARK}%')");
    $conn->query("DELETE d FROM financing_deviations d WHERE d.subject_ref IN (SELECT CONCAT('share:',s.share_id) FROM asset_ownership_shares s JOIN financing_operations o ON o.op_id=s.op_id WHERE o.op_code LIKE '{$MARK}%')");
    $conn->query("DELETE s FROM asset_ownership_shares s JOIN financing_operations o ON o.op_id=s.op_id WHERE o.op_code LIKE '{$MARK}%'");
    $conn->query("DELETE i FROM financing_installments i JOIN financing_operations o ON o.op_id=i.op_id WHERE o.op_code LIKE '{$MARK}%'");
    $conn->query("DELETE FROM financing_operations WHERE op_code LIKE '{$MARK}%'");
    $conn->query("DELETE FROM entity_roles WHERE doc_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM legal_entities WHERE legal_name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

$conn->query("INSERT INTO legal_entities (legal_name, country, registry_authority, commercial_reg) VALUES ('ممول {$MARK}','SD','السجل التجاري','{$MARK}-1')");
$FIN = (int) $conn->insert_id;
$conn->query("INSERT INTO entity_roles (entity_id, role, valid_from, doc_ref) VALUES ({$FIN},'financier',CURDATE(),'{$MARK}-r')");

// ① عملية نافذة بلا أي حركة (no_ledger)
$conn->query("INSERT INTO financing_operations (company_id, op_code, financier_entity_id, model_code, currency, capital, outstanding_balance, installments_no, state, created_by)
              VALUES ({$CO},'{$MARK}-OP1',{$FIN},'murabaha','USD',5000,5000,0,'active',1)");
// ② عملية بقسط متجاوز
$conn->query("INSERT INTO financing_operations (company_id, op_code, financier_entity_id, model_code, currency, capital, outstanding_balance, installments_no, state, created_by)
              VALUES ({$CO},'{$MARK}-OP2',{$FIN},'murabaha','USD',6000,6000,2,'paying',1)");
$OP2 = (int) $conn->insert_id;
$conn->query("INSERT INTO financing_installments (op_id, seq_no, due_date, amount_total, currency, state) VALUES ({$OP2},1,'2026-07-01',3000,'USD','due')");
$conn->query("INSERT INTO financing_installments (op_id, seq_no, due_date, amount_total, currency, state) VALUES ({$OP2},2,'2026-09-01',3000,'USD','scheduled')");
$conn->query("UPDATE financing_installments SET state='paid', paid_date='2026-06-01' WHERE op_id={$OP2} AND seq_no=2"); // حركة تخرج OP2 من ورقة ①
// ③ عملية مقفلة وحصتها مفتوحة (خروج غير مسجَّل)
$conn->query("INSERT INTO financing_operations (company_id, op_code, financier_entity_id, model_code, currency, capital, outstanding_balance, state, created_by)
              VALUES ({$CO},'{$MARK}-OP3',{$FIN},'musharaka','USD',8000,0,'settled',1)");
$OP3 = (int) $conn->insert_id;
$conn->query("INSERT INTO asset_ownership_shares (company_id, asset_kind, asset_id, financier_entity_id, op_id, percent, valid_from, created_by)
              VALUES ({$CO},'equipment',999731,{$FIN},{$OP3},100.00,'2025-01-01',1)");

$n = FS::deviationSweep($conn, $CO);
check($n >= 3, "المسح ولّد الأوراق الثلاث ({$n} صفًّا)");
$q = $conn->query("SELECT COUNT(*) c FROM financing_deviations WHERE dev_type='no_ledger' AND subject_ref LIKE 'op:%' AND state='open' AND description LIKE '%{$MARK}-OP1%'")->fetch_assoc();
check((int) $q['c'] === 1, '① عقد موقَّع بلا حركة في الدفتر — يظهر بقرار مطلوب (F7)');
$q = $conn->query("SELECT COUNT(*) c FROM financing_deviations d JOIN financing_installments i ON CONCAT('inst:',i.inst_id)=d.subject_ref WHERE d.dev_type='payment_gap' AND i.op_id={$OP2}")->fetch_assoc();
check((int) $q['c'] === 1, '② قسط متجاوز استحقاقه — فروق السداد بترجيح سببها');
$q = $conn->query("SELECT dev_id FROM financing_deviations d JOIN asset_ownership_shares s ON CONCAT('share:',s.share_id)=d.subject_ref WHERE d.dev_type='unrecorded_exit' AND s.op_id={$OP3}")->fetch_assoc();
check($q !== null, '③ حصة مفتوحة وعمليتها منتهية — الخروج غير المسجَّل');
$DEV = (int) $q['dev_id'];

$n2 = FS::deviationSweep($conn, $CO);
check($n2 === 0, 'وإعادة المسح عاطلة — Insert-only بالمفتاح');

$r = FS::closeDeviation($conn, $CO, $DEV, '', '', 6);
check(!$r['ok'] && $r['code'] === 422, 'إغلاق بلا قرار ومستند ⇒ 422');
$e = $conn->query("UPDATE financing_deviations SET state='closed' WHERE dev_id={$DEV}");
check($e === false, 'وCHECK الجدول يرفض الإغلاق الصامت حتى بالالتفاف');
$r = FS::closeDeviation($conn, $CO, $DEV, 'بيع الحصة معتمد بمحضر لجنة', 'SALE-2026-77', 6);
check($r['ok'], 'وبقرار ومستند يُغلق: ' . $r['reason']);
$q = $conn->query("SELECT state, decision, decision_doc_ref FROM financing_deviations WHERE dev_id={$DEV}")->fetch_assoc();
check($q['state'] === 'closed' && $q['decision'] !== '' && $q['decision_doc_ref'] === 'SALE-2026-77', 'القرار ومستنده على الصف — الانحراف يُغلق بقرار لا يُترك');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
