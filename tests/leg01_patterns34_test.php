<?php
/**
 * tests/leg01_patterns34_test.php — أنماط الحوكمة الموسَّعة ③④ (البوابة ④)
 * ═══════════════════════════════════════════════════════════════════════════
 * G4 قيد المئة المشروط: full يفرض 100 · partial ≤100 يُقبل موسومًا · unknown
 * يُسجَّل بلا فرض · G5 البيع ينهي ويفتح بالتاريخ نفسه وΣ باقٍ · لا أثر رجعي
 * 423 · G6 تضارب المصالح مكشوف تنبيهًا لا منعًا · التراخيص والكفالات بموعديها
 * (ExpiringSoon + انتهاء آلي يسقط الصلاحية) · G7/G8 الأنماط أعلام لنطاقها.
 * التشغيل: php tests/leg01_patterns34_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__) . '/app/Core/EntityGovernanceService.php';

use App\Core\EntityGovernanceService as EG;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$MARK = 'LP34T';
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE o FROM entity_ownership o JOIN legal_entities e ON e.entity_id=o.owned_entity_id WHERE e.legal_name LIKE '%{$MARK}%'");
    $conn->query("DELETE l FROM entity_licenses l JOIN legal_entities e ON e.entity_id=l.entity_id WHERE e.legal_name LIKE '%{$MARK}%'");
    $conn->query("DELETE g FROM guarantees g JOIN legal_entities e ON e.entity_id=g.entity_id WHERE e.legal_name LIKE '%{$MARK}%'");
    $conn->query("DELETE f FROM governance_flags f WHERE f.reason LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_notifications WHERE title LIKE '%{$MARK}%' OR title LIKE '%ExpiringSoon%'");
    $conn->query("DELETE FROM entity_roles WHERE doc_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM legal_entities WHERE legal_name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

foreach (array(array('full', 'F'), array('partial', 'P'), array('unknown', 'U')) as $mo) {
    $conn->query("INSERT INTO legal_entities (legal_name, country, registry_authority, commercial_reg, ownership_completeness)
                  VALUES ('كيان {$mo[0]} {$MARK}','SD','السجل التجاري','{$MARK}-{$mo[1]}','{$mo[0]}')");
    ${'E_' . strtoupper($mo[0])} = (int) $conn->insert_id;
}

head('G4 — قيد المئة المشروط لا المطلق');
$r = EG::addOwnership($conn, array('owner_type' => 'entity', 'owner_id' => 1, 'owned_entity_id' => $E_FULL, 'percent' => 60, 'valid_from' => '2026-01-01'));
check($r['ok'], 'حصة 60 على كيان كامل تمر (لم تبلغ 100 بعد — النقص يُرى في الشجرة)');
$r = EG::addOwnership($conn, array('owner_type' => 'entity', 'owner_id' => 2, 'owned_entity_id' => $E_FULL, 'percent' => 41, 'valid_from' => '2026-01-01'));
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], '+1.00') !== false, 'G4: كيان full بمجموع 101 ⇒ 422 بالفارق');
EG::addOwnership($conn, array('owner_type' => 'entity', 'owner_id' => 2, 'owned_entity_id' => $E_FULL, 'percent' => 40, 'valid_from' => '2026-01-01'));
$r = EG::addOwnership($conn, array('owner_type' => 'entity', 'owner_id' => 3, 'owned_entity_id' => $E_PARTIAL, 'percent' => 60, 'valid_from' => '2026-01-01'));
check($r['ok'], 'G4: كيان partial بحصص 60٪ يُقبل — لا يُفرض المئة (بنك/مدرجة)');
$r = EG::addOwnership($conn, array('owner_type' => 'entity', 'owner_id' => 3, 'owned_entity_id' => $E_UNKNOWN, 'percent' => 25, 'valid_from' => '2026-01-01'));
check($r['ok'] && mb_strpos($r['reason'], 'النقص') !== false, 'G4: المجهول يُسجَّل المعلوم موسومًا — لا بيانات ملفَّقة');

head('G5·G6 — البيع والتضارب');
$OWN = (int) $conn->query("SELECT own_id FROM entity_ownership WHERE owned_entity_id={$E_FULL} AND percent=40 LIMIT 1")->fetch_assoc()['own_id'];
$r = EG::transferOwnership($conn, $OWN, 'entity', 5, '2026-06-01', '');
check(!$r['ok'] && $r['code'] === 422, 'بيع بلا مستند ⇒ 422');
$r = EG::transferOwnership($conn, $OWN, 'entity', 5, '2026-06-01', 'SALE-' . $MARK);
check($r['ok'], 'G5: البائع أُنهي 2026-05-31 والمشتري فُتح 2026-06-01');
$tree = EG::treeAt($conn, $E_FULL, '2026-07-01');
$sum = 0; foreach ($tree as $t) { $sum += (float) $t['percent']; }
check(abs($sum - 100.0) < 0.005 && count($tree) === 2, 'وΣ عند أي لحظة باقٍ 100 (شجرة ' . count($tree) . ' فرعين)');
$r = EG::transferOwnership($conn, $OWN, 'entity', 6, '2026-03-01', 'X');
check(!$r['ok'] && $r['code'] === 423, 'تعديل علاقة منتهية بأثر رجعي ⇒ 423');
$r = EG::addOwnership($conn, array('owner_type' => 'person', 'owner_id' => 6, 'owned_entity_id' => $E_PARTIAL, 'percent' => 10, 'valid_from' => '2026-01-01'));
check($r['ok'] && $r['conflict_detected'] === true, 'G6: موظف يملك حصة في كيان — تضارب مكشوف تنبيهًا والقرار للحوكمة لا للنظام');

head('التراخيص والكفالات — ExpiryMonitor');
$conn->query("INSERT INTO entity_licenses (entity_id, lic_type, lic_no, expiry_date, alert_days) VALUES ({$E_FULL}, 'سجل تجاري', '{$MARK}-L1', DATE_ADD(CURDATE(), INTERVAL 10 DAY), 30)");
$conn->query("INSERT INTO entity_licenses (entity_id, lic_type, lic_no, expiry_date, alert_days) VALUES ({$E_FULL}, 'رخصة نشاط', '{$MARK}-L2', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 30)");
$conn->query("INSERT INTO guarantees (direction, entity_id, gtee_type, amount, currency, expiry_date, alert_days) VALUES ('issued', {$E_FULL}, 'ضمان حسن تنفيذ', 50000, 'USD', DATE_ADD(CURDATE(), INTERVAL 5 DAY), 30)");
$n = EG::expirySweep($conn);
check($n >= 2, "ExpiringSoon: تنبيهان مبكران على الأقل ({$n}) — الكفالة المنتهية بلا تجديد تُسقط حقًّا");
$q = $conn->query("SELECT state FROM entity_licenses WHERE lic_no='{$MARK}-L2'")->fetch_assoc();
check($q['state'] === 'expired', 'والمنتهي عُلّم آليًّا — لا متابعة يدوية');

head('G7·G8 — الأنماط أعلام لنطاقها');
$r = EG::enablePattern($conn, 3, 'entity', $E_FULL, $MARK . ' فتح بوابة عميل', 1);
check($r['ok'] && $r['elements'] === 2, 'النمط ③ لكيانٍ واحد: internal_parties + external_accounts');
$q = $conn->query("SELECT COUNT(*) c FROM governance_flags WHERE scope_type='entity' AND scope_id={$E_FULL} AND enabled=1")->fetch_assoc();
check((int) $q['c'] === 2, 'الأعلام لنطاقه وحده');
$q = $conn->query("SELECT COUNT(*) c FROM governance_flags WHERE scope_type='entity' AND scope_id={$E_PARTIAL} AND enabled=1")->fetch_assoc();
check((int) $q['c'] === 0, 'G7: وبقية الكيانات على النمط ① كما هي — التفعيل لا يعدو نطاقه');
$r = EG::enablePattern($conn, 4, 'contract', 7, $MARK . ' حوكمة كاملة لعقد كبير', 1);
check($r['ok'] && $r['elements'] === 6, 'G8: عقد واحد يرقى للنمط ④ (6 عناصر) — صفر هجرة بيانات');
$r = EG::enablePattern($conn, 1, 'entity', $E_FULL, 'x', 1);
check(!$r['ok'], 'والنمط ① هو الافتراض لا يُفعَّل — كله مطفأ أصلًا');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
