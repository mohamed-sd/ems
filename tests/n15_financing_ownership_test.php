<?php
/**
 * tests/n15_financing_ownership_test.php — N-15 التمويل والملكية (البوابة ③)
 * ═══════════════════════════════════════════════════════════════════════════
 * F1 المرابحة أصل مملوك + حصة 100% وجدول أقساط مولَّد · F2 الإجارة حق استخدام
 * لا أصل (المحاور الخمسة) · F3 حصص 101% ⇒ 422 بالفارق · F4 الخروج بمستند
 * والأولى تُنهى والثانية تُفتح بالتاريخ نفسه وΣ يبقى 100 · F5 التصحيح الموثق
 * بلا محو · F6 القسط حدث بمفتاح (العملية×القسط) وتكراره عاطل · مثال EQ16
 * الثلاثي (FIN-01 §5) · عملية بلا نموذج 422 · الأقساط تولَّد لا تُدخل.
 * التشغيل: php tests/n15_financing_ownership_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__) . '/app/Services/Financing/FinancingService.php';

use App\Core\EventPublisher;
use App\Services\Financing\FinancingService as FS;

EventPublisher::$rootModeOverride = 'publish';

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $MARK = 'N15T'; $ASSET = 999716;
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

$teardown = function () use ($conn, $MARK, $ASSET) {
    $conn->query("DELETE FROM financing_deviations WHERE company_id=4 AND subject_ref IN (SELECT CONCAT('op:',op_id) FROM financing_operations WHERE op_code LIKE '{$MARK}%')");
    $conn->query("DELETE FROM fin_financial_events WHERE idempotency_key LIKE 'fininst:%' AND entity_type='financing_installment'");
    $conn->query("DELETE FROM ems_business_events WHERE entity_type='financing_installment'");
    $conn->query("DELETE i FROM financing_installments i JOIN financing_operations o ON o.op_id=i.op_id WHERE o.op_code LIKE '{$MARK}%'");
    $conn->query("DELETE FROM asset_ownership_shares WHERE asset_id = {$ASSET}");
    $conn->query("DELETE fa FROM financed_assets fa JOIN financing_operations o ON o.op_id=fa.op_id WHERE o.op_code LIKE '{$MARK}%'");
    $conn->query("DELETE FROM financing_operations WHERE op_code LIKE '{$MARK}%'");
    $conn->query("DELETE FROM entity_roles WHERE doc_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM legal_entities WHERE legal_name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

// بذر ممولَين كيانين (LEG-01 — لا سجل موازيًا)
$conn->query("INSERT INTO legal_entities (legal_name, country, registry_authority, commercial_reg) VALUES ('الممول أ {$MARK}','SD','السجل التجاري','{$MARK}-F1')");
$FA = (int) $conn->insert_id;
$conn->query("INSERT INTO legal_entities (legal_name, country, registry_authority, commercial_reg) VALUES ('الممول ب {$MARK}','SD','السجل التجاري','{$MARK}-F2')");
$FB = (int) $conn->insert_id;
$conn->query("INSERT INTO entity_roles (entity_id, role, valid_from, doc_ref) VALUES ({$FA},'financier',CURDATE(),'{$MARK}-r1'),({$FB},'financier',CURDATE(),'{$MARK}-r2')");

head('النماذج — المحاور الخمسة والمعالجة المكتوبة');
$q = $conn->query("SELECT COUNT(*) c FROM financing_models WHERE policy_doc_ref <> '' AND active=1")->fetch_assoc();
check((int) $q['c'] >= 4, 'أربعة نماذج بقاموسها وسياستها المحاسبية المكتوبة (' . $q['c'] . ')');
$q = $conn->query("SELECT accounting_recognition, legal_owner_effect FROM financing_models WHERE model_code='ijara_op'")->fetch_assoc();
check($q['accounting_recognition'] === 'right_of_use' && $q['legal_owner_effect'] === 'stays',
      'F2: الإجارة التشغيلية حق استخدام والملكية لا تنتقل — **والأصول لا تتضخم**');
$q = $conn->query("SELECT depreciation_bearer FROM financing_models WHERE model_code='musharaka'")->fetch_assoc();
check($q['depreciation_bearer'] === 'by_share_pct', 'المشاركة: الإهلاك بنسبة الحصة النافذة');

head('F1 — عملية مرابحة نافذة');
$r = FS::createOperation($conn, $CO, array('op_code' => $MARK . '-OP1', 'financier_entity_id' => $FA, 'model_code' => 'murabaha', 'currency' => 'USD', 'capital' => 100000, 'purchase_value' => 90000, 'down_payment' => 20000, 'profit_amount' => 10000, 'installments_no' => 12), 1);
check($r['ok'], 'F1: مرابحة نافذة — الرصيد 90000 (100000-20000+10000): ' . $r['reason']);
$OP1 = $r['op_id'];
$r = FS::createOperation($conn, $CO, array('op_code' => $MARK . '-OPX', 'financier_entity_id' => $FA, 'model_code' => 'leasing_new', 'currency' => 'USD', 'capital' => 1), 1);
check(!$r['ok'] && $r['code'] === 422, 'نموذج غير معرَّف ⇒ 422 — ولا يُفترض نموذج أبدًا');

$r = FS::generateInstallments($conn, $CO, $OP1, '2026-09-01');
check($r['ok'] && $r['created'] === 12, 'جدول الأقساط مولَّد من العملية (12) — ولا يُدخل يدويًّا');
$r2 = FS::generateInstallments($conn, $CO, $OP1, '2026-09-01');
check($r2['created'] === 0, 'وإعادة التوليد عاطلة بالمفتاح');

head('مثال FIN-01 §5 — EQ16 بثلاث حصص عبر الزمن');
$r = FS::applyShares($conn, $CO, 'equipment', $ASSET, array(
    array('financier' => $FA, 'percent' => 100.00, 'from' => '2022-03-07', 'op_id' => $OP1, 'model_code' => 'murabaha')), null, 1);
check($r['ok'] && $r['sum_at_date'] === 100.0, 'المرحلة ①: الممول أ 100% (مرابحة)');
$r = FS::applyShares($conn, $CO, 'equipment', $ASSET, array(
    array('financier' => $FA, 'percent' => 60.00, 'from' => '2023-04-09', 'doc_ref' => 'SALE-2023-114'),
    array('financier' => $FB, 'percent' => 40.00, 'from' => '2023-04-09', 'doc_ref' => 'SALE-2023-114')), '2023-04-08', 1);
check($r['ok'] && $r['sum_at_date'] === 100.0, 'F4: بيع حصة — الأولى أُنهيت 2023-04-08 والجديدتان فُتحتا 2023-04-09 وΣ = 100');
$sh = FS::sharesAt($conn, $CO, 'equipment', $ASSET, '2023-06-15');
check(count($sh) === 2 && (float) $sh[0]['percent'] === 60.0, 'الفترة الوسطى 60/40 — والإهلاك يُحمَّل عليهما لا على واحد');
$sh = FS::sharesAt($conn, $CO, 'equipment', $ASSET, '2022-12-01');
check(count($sh) === 1 && (float) $sh[0]['percent'] === 100.0, 'والتاريخ يقرأ نسخته — لا فجوة ولا تراكب');

head('F3 — قيد المئة');
$r = FS::applyShares($conn, $CO, 'equipment', $ASSET, array(
    array('financier' => $FA, 'percent' => 61.00, 'from' => '2024-07-01', 'doc_ref' => 'X'),
    array('financier' => $FB, 'percent' => 40.00, 'from' => '2024-07-01', 'doc_ref' => 'X')), '2024-06-30', 1);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], '+1.00') !== false, 'F3: المجموع 101 ⇒ 422 بالفارق (+1.00) ولا تُحفظ');
$r = FS::applyShares($conn, $CO, 'equipment', $ASSET, array(
    array('financier' => $FB, 'percent' => 100.00, 'from' => '2024-07-01')), '2024-06-30', 1);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'مستند') !== false, 'F4: بيع بلا مستند ⇒ 422');

head('F5 — التصحيح الموثق بلا محو');
$sid = (int) $conn->query("SELECT share_id FROM asset_ownership_shares WHERE asset_id={$ASSET} AND percent=40.00 LIMIT 1")->fetch_assoc()['share_id'];
$r = FS::correctShare($conn, $CO, $sid, 35.00, 'خطأ تسجيل — العقد ينص 35%', 35.00, 1);
check($r['ok'], 'صُحّحت الحصة');
$q = $conn->query("SELECT recorded_percent, corrected_percent, correction_reason, approved_percent, percent FROM asset_ownership_shares WHERE share_id={$sid}")->fetch_assoc();
check((float) $q['recorded_percent'] === 40.0 && (float) $q['approved_percent'] === 35.0 && $q['correction_reason'] !== '',
      'F5: المسجَّلة (40) والمصححة والسبب والحكم (35) — والأصل محفوظ بلا محو');

head('F6 — القسط حدثٌ بمفتاحه');
$r = FS::publishInstallmentDue($conn, $CO, $OP1, 1, 1);
check($r['ok'] && !$r['duplicate'], 'InstallmentDue منشور بمفتاح (العملية×القسط)');
$r2 = FS::publishInstallmentDue($conn, $CO, $OP1, 1, 1);
check($r2['ok'] && $r2['duplicate'] && $r2['event_id'] === $r['event_id'], 'F6: تكراره يعيد المرجع القائم — 409 دلاليًّا لا حدث ثانٍ');

$r = FS::payInstallment($conn, $CO, $OP1, 1, '2026-09-05', 'PAY-991', 601.5, 4511250.00);
check($r['ok'], 'سُدّد بسعر يوم السداد مثبَّتًا (فرق محقق بسطره)');
$q = $conn->query("SELECT outstanding_balance FROM financing_operations WHERE op_id={$OP1}")->fetch_assoc();
check(abs((float) $q['outstanding_balance'] - 82500.0) < 0.01, 'والرصيد نقص بقيمة القسط (90000−7500=82500)');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
