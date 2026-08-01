<?php
/**
 * tests/split_depreciation_fx_test.php — الإهلاك المقسَّم وفروق الصرف (البوابة ③)
 * ═══════════════════════════════════════════════════════════════════════════
 * F11: أصل بحصتين 60/40 ⇒ قيد إهلاك **بسطرين** بنسبة الحصة النافذة في الفترة
 * (الوظيفة القائمة عُدّلت ولم تُجاوَر) · وبلا حصص: سطر واحد للمستأجر (السلوك
 * القائم لا يتغير) · Σ السطور = القسط بالضبط.
 * F12: رصيد تمويل مفتوح بعملة تخالف الدفاتر يُعاد تقييمه — **فرق غير محقق
 * بسطره** في fin_fx_differences، عاطل بالفترة، ولا يُدمج في تكلفة التمويل.
 * التشغيل: php tests/split_depreciation_fx_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
$_SESSION['user'] = array('id' => 1, 'role' => '19', 'company_id' => 4, 'name' => 'split dep test');
require_once dirname(__DIR__) . '/app/Services/Finance/DepreciationService.php';
require_once dirname(__DIR__) . '/app/Services/Financing/FinancingService.php';

use App\Core\EventPublisher;
use App\Services\Finance\DepreciationService as DS;
use App\Services\Financing\FinancingService as FS;

EventPublisher::$rootModeOverride = 'publish';

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate = ems_tenant_db();
$CO = 4; $MARK = 'SDXT';
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE d FROM fin_depreciation d JOIN fin_assets a ON a.id=d.asset_id WHERE a.code LIKE '{$MARK}%'");
    $conn->query("DELETE FROM fin_financial_events WHERE idempotency_key LIKE 'dep:%' AND source_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM ems_business_events WHERE source_ref LIKE '{$MARK}%'");
    $conn->query("DELETE s FROM asset_ownership_shares s JOIN fin_assets a ON a.id=s.asset_id AND s.asset_kind='fin_asset' WHERE a.code LIKE '{$MARK}%'");
    $conn->query("DELETE FROM fin_assets WHERE code LIKE '{$MARK}%'");
    $conn->query("DELETE FROM fin_fx_differences WHERE note LIKE 'finrev:%' AND source_kind='revaluation' AND note LIKE 'finrev:%'");
    $conn->query("DELETE i FROM financing_installments i JOIN financing_operations o ON o.op_id=i.op_id WHERE o.op_code LIKE '{$MARK}%'");
    $conn->query("DELETE FROM financing_operations WHERE op_code LIKE '{$MARK}%'");
    $conn->query("DELETE FROM entity_roles WHERE doc_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM legal_entities WHERE legal_name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

// بذر أصل قابل للإهلاك (خط مستقيم 12000 على 10 سنوات = 100 شهريًّا)
$dep_state = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_assets' AND COLUMN_NAME='state'")->fetch_assoc();
$conn->query("INSERT INTO fin_assets (company_id, code, name, method, acquisition_cost, salvage_value, useful_life_months, acquisition_date, accumulated_depreciation, state, created_by)
              VALUES ({$CO}, '{$MARK}-A1', 'أصل مشترك {$MARK}', 'straight_line', 12000, 0, 120, '2026-01-15', 0, 'active', 1)");
if ($conn->error) { echo "بذر الأصل: {$conn->error}\n"; }
$AID = (int) $conn->insert_id;

// ممولان بحصتي 60/40 على الأصل (fin_asset)
$conn->query("INSERT INTO legal_entities (legal_name, country, registry_authority, commercial_reg) VALUES ('ممول {$MARK} أ','SD','السجل التجاري','{$MARK}-1')");
$FA = (int) $conn->insert_id;
$conn->query("INSERT INTO legal_entities (legal_name, country, registry_authority, commercial_reg) VALUES ('ممول {$MARK} ب','SD','السجل التجاري','{$MARK}-2')");
$FB = (int) $conn->insert_id;
$conn->query("INSERT INTO entity_roles (entity_id, role, valid_from, doc_ref) VALUES ({$FA},'financier',CURDATE(),'{$MARK}-r1'),({$FB},'financier',CURDATE(),'{$MARK}-r2')");
$conn->query("INSERT INTO asset_ownership_shares (company_id, asset_kind, asset_id, financier_entity_id, percent, valid_from, created_by)
              VALUES ({$CO},'fin_asset',{$AID},{$FA},60.00,'2026-01-01',1), ({$CO},'fin_asset',{$AID},{$FB},40.00,'2026-01-01',1)");

head('F11 — قيد بسطرين بنسبة الحصة النافذة');
$asset = $conn->query("SELECT * FROM fin_assets WHERE id={$AID}")->fetch_assoc();
$r = DS::postOne($conn, $gate, $CO, $asset, '2026-06', '2026-06-30', 1, 'screen');
check($r['ok'], 'قسط 2026-06 احتُسب: ' . $r['reason'] . ' (المبلغ ' . $r['amount'] . ')');
$row = $conn->query("SELECT basis_json FROM fin_depreciation WHERE id={$r['row_id']}")->fetch_assoc();
$basis = json_decode($row['basis_json'], true);
$split = isset($basis['ownership_split']) ? $basis['ownership_split'] : array();
check(count($split) === 2, 'F11: **سطران** لا سطر واحد لمالك واحد (' . count($split) . ')');
$sum = 0.0; $p60 = null; $p40 = null;
foreach ($split as $l) { $sum += $l['amount']; if ((float) $l['pct'] === 60.0) { $p60 = $l; } if ((float) $l['pct'] === 40.0) { $p40 = $l; } }
check($p60 !== null && $p40 !== null && abs($p60['amount'] - round($r['amount'] * 0.6, 2)) < 0.011,
      'السطر الأول 60% (' . ($p60 ? $p60['amount'] : '—') . ') والثاني 40% (' . ($p40 ? $p40['amount'] : '—') . ')');
check(abs($sum - $r['amount']) < 0.005, 'Σ السطرين = القسط بالضبط (فرق التقريب ملحق بالأكبر)');
$ev = $conn->query("SELECT payload FROM fin_financial_events WHERE id={$r['event_id']}")->fetch_assoc();
$pl = json_decode((string) $ev['payload'], true);
check(isset($pl['lines']) && count($pl['lines']) === 2, 'وحدث القيد يحمل السطرين في حمولته — للمُقيِّد لا للتقدير');

// بلا حصص: السلوك القائم (سطر tenant واحد)
$conn->query("INSERT INTO fin_assets (company_id, code, name, method, acquisition_cost, salvage_value, useful_life_months, acquisition_date, accumulated_depreciation, state, created_by)
              VALUES ({$CO}, '{$MARK}-A2', 'أصل مملوك {$MARK}', 'straight_line', 6000, 0, 60, '2026-01-15', 0, 'active', 1)");
$AID2 = (int) $conn->insert_id;
$asset2 = $conn->query("SELECT * FROM fin_assets WHERE id={$AID2}")->fetch_assoc();
$r2 = DS::postOne($conn, $gate, $CO, $asset2, '2026-06', '2026-06-30', 1, 'screen');
$basis2 = json_decode($conn->query("SELECT basis_json FROM fin_depreciation WHERE id={$r2['row_id']}")->fetch_assoc()['basis_json'], true);
check(count($basis2['ownership_split']) === 1 && $basis2['ownership_split'][0]['bearer_kind'] === 'tenant',
      'وأصل بلا حصص مسجَّلة: سطر واحد للمستأجر — السلوك القائم لا يتغير');

head('F12 — إعادة تقييم الرصيد المفتوح (فرق غير محقق بسطره)');
$conn->query("INSERT INTO financing_operations (company_id, op_code, financier_entity_id, model_code, currency, capital, outstanding_balance, state, created_by)
              VALUES ({$CO}, '{$MARK}-OP', {$FA}, 'fixed_yield', 'USD', 10000, 10000, 'active', 1)");
$OP = (int) $conn->insert_id;
$r = FS::revalueOutstanding($conn, $CO, '2026-08', array('USD' => array('old' => 600.0, 'new' => 650.0)), 'SDG', 1);
check($r['ok'] && $r['created'] === 1, 'رصيد 10000 USD مفتوح أُعيد تقييمه (600→650)');
$q = $conn->query("SELECT kind, amount, rate_from, rate_to FROM fin_fx_differences WHERE source_kind='revaluation' AND source_ref={$OP} AND note LIKE 'finrev:%'")->fetch_assoc();
check($q && $q['kind'] === 'unrealized' && abs((float) $q['amount'] - 500000.0) < 0.01,
      'فرق **غير محقق** بسطره: 10000 × (650−600) = 500000 — لا مدموجًا في تكلفة التمويل');
$r2 = FS::revalueOutstanding($conn, $CO, '2026-08', array('USD' => array('old' => 600.0, 'new' => 650.0)), 'SDG', 1);
check($r2['created'] === 0, 'وإعادة التقييم للفترة نفسها عاطلة — لا فرق مكرر');
$q = $conn->query("SELECT COUNT(*) c FROM fin_fx_differences WHERE source_kind='revaluation' AND source_ref={$OP} AND note LIKE 'finrev:%'")->fetch_assoc();
check((int) $q['c'] === 1, 'سطر واحد للفترة');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
