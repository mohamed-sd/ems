<?php
/**
 * tests/n21_ownership_confidentiality_test.php — N-21 سرية الملكية (البوابة ①)
 * ═══════════════════════════════════════════════════════════════════════════
 * الثوابت المختبرة (PLAN-04 §1.3 · FIN-01 §1.1 · شروط إغلاق البوابة ①):
 *   ① fail-closed: بلا صلاحية لا يُجلب الحقل أصلًا — وسطر منع مسجَّل.
 *   ② بصلاحية فردية: القراءة تقع **وسطر اطّلاع يُسجَّل** (لا الكتابة وحدها).
 *   ③ صلاحية قيمة الشراء (الأشد) بلا سبب ومدة ⇒ 422 — وCHECK بنيوي يسندها.
 *   ④ أعمدة المالك في equipments مهجورة: الكتابة فيها تُرفض بنيويًّا (Trigger).
 *   ⑤ صفر قيمة مالك باقية في الجدول التشغيلي — والبيانات في السجل المقيَّد.
 *   ⑥ فحص تسريب بعشرين محاولة عبر التصفية الخادمية: صفر عمود محجوب يمرّ.
 *   ⑦ صفر مرجع لأعمدة المالك في شاشات التشغيل الثلاث والتصدير (فحص قارئين).
 * التشغيل: php tests/n21_ownership_confidentiality_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__) . '/app/Core/OwnershipDomainGuard.php';

use App\Core\OwnershipDomainGuard as ODG;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$U_NOGRANT = 999201; $U_OWNER = 999202; $U_VALUE = 999203;

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

$teardown = function () use ($conn) {
    $conn->query("DELETE FROM ownership_access_grants WHERE person_id IN (999201,999202,999203)");
    $conn->query("DELETE FROM sensitive_read_log WHERE person_id IN (999201,999202,999203)");
};
register_shutdown_function($teardown);
$teardown();

$EQ = (int) $conn->query("SELECT equipment_id FROM equipment_ownership_registry WHERE company_id={$CO} LIMIT 1")->fetch_assoc()['equipment_id'];
check($EQ > 0, "معدة مرحَّلة للسجل المقيَّد: #{$EQ}");

head('① fail-closed — بلا صلاحية لا جلب أصلًا');
$r = ODG::fetchEquipmentOwnership($conn, $CO, $U_NOGRANT, $EQ);
check(empty($r['allowed']) && in_array(ODG::PERM_OWNER, $r['denied'], true), 'صفر حقل جُلب — لا جلب ثم إخفاء');
$q = $conn->query("SELECT COUNT(*) c FROM sensitive_read_log WHERE person_id={$U_NOGRANT} AND result='denied'")->fetch_assoc();
check((int) $q['c'] >= 1, 'المنع مسجَّل بسطر اطّلاع');

head('② الصلاحية الفردية والاطّلاع');
$g = ODG::grant($conn, $CO, $U_OWNER, ODG::PERM_OWNER, 1);
check($g['ok'], 'مُنح كود رؤية المالك فرديًّا');
$r = ODG::fetchEquipmentOwnership($conn, $CO, $U_OWNER, $EQ);
check(!empty($r['allowed']) && array_key_exists('actual_owner_name', $r['allowed']), 'القراءة تقع بالصلاحية');
check(!array_key_exists('purchase_value', $r['allowed']), 'وقيمة الشراء لا تُجلب — كود آخر أشد');
$q = $conn->query("SELECT COUNT(*) c FROM sensitive_read_log WHERE person_id={$U_OWNER} AND element_code='ownership.owner' AND result='allowed'")->fetch_assoc();
check((int) $q['c'] === 1, 'سطر اطّلاع مسجَّل للقراءة — «التسرب يقع بالاطّلاع لا بالتغيير»');

head('③ قيمة الشراء — الأشد بمدة وسبب');
$g = ODG::grant($conn, $CO, $U_VALUE, ODG::PERM_VALUE, 1);
check(!$g['ok'] && $g['code'] === 422, 'منح بلا سبب ومدة ⇒ 422');
$e = $conn->query("INSERT INTO ownership_access_grants (company_id, person_id, permission_code, granted_by) VALUES ({$CO},{$U_VALUE},'ownership.purchase_value',1)");
check($e === false, 'وCHECK البنيوي يرفض الالتفاف على الخدمة: ' . mb_substr((string) $conn->error, 0, 50));
$g = ODG::grant($conn, $CO, $U_VALUE, ODG::PERM_VALUE, 1, 'مراجعة ائتمانية Q3', date('Y-m-d'), date('Y-m-d', strtotime('+14 days')));
check($g['ok'], 'وبسبب ومدة يقع المنح');
$r = ODG::fetchEquipmentOwnership($conn, $CO, $U_VALUE, $EQ);
check(array_key_exists('purchase_value', $r['allowed']), 'قيمة الشراء تُقرأ بمنحتها المؤقتة');

head('④⑤ الهجر البنيوي لأعمدة المالك التشغيلية');
$e = $conn->query("UPDATE equipments SET actual_owner_name='تسريب مقصود' WHERE id={$EQ}");
check($e === false && strpos((string) $conn->error, 'N-21') !== false, 'الكتابة في العمود المهجور تُرفض بنيويًّا (Trigger): ' . mb_substr((string) $conn->error, 0, 60));
$q = $conn->query("SELECT COUNT(*) c FROM equipments WHERE actual_owner_name IS NOT NULL OR owner_type IS NOT NULL OR owner_phone IS NOT NULL OR owner_supplier_relation IS NOT NULL")->fetch_assoc();
check((int) $q['c'] === 0, 'صفر قيمة مالك باقية في الجدول التشغيلي');
$q = $conn->query("SELECT COUNT(*) c FROM equipment_ownership_registry")->fetch_assoc();
check((int) $q['c'] > 0, "والبيانات محفوظة في السجل المقيَّد ({$q['c']} صفًّا) — نُقلت لا مُحيت");

head('⑥ فحص تسريب بعشرين محاولة — التصفية الخادمية');
$leaks = 0;
for ($i = 0; $i < 20; $i++) {
    $rows = array(array('id' => $i, 'code' => 'EQ' . $i, 'actual_owner_name' => 'سري', 'purchase_value' => 99999, 'acquisition_cost' => 1234, 'name' => 'معدة'));
    $filtered = ODG::filterColumns($conn, $CO, $U_NOGRANT, $rows);
    foreach (array('actual_owner_name', 'purchase_value', 'acquisition_cost') as $col) {
        if (array_key_exists($col, $filtered[0])) { $leaks++; }
    }
}
check($leaks === 0, "عشرون محاولة تجاوز: صفر عمود محجوب مرّ (تسريبات: {$leaks})");
$rows2 = ODG::filterColumns($conn, $CO, $U_OWNER, array(array('actual_owner_name' => 'x', 'purchase_value' => 1)));
check(array_key_exists('actual_owner_name', $rows2[0]) && !array_key_exists('purchase_value', $rows2[0]), 'وصاحب كود المالك يرى المالك دون القيمة — التصفية بالكود لا بالكل');

head('⑦ صفر مرجع في شاشات التشغيل والتصدير');
$files = array('Equipments/equipments.php', 'Equipments/equipments_fleet.php', 'Equipments/equipments_drivers.php', 'app/Services/Excel/ExcelRegistry.php');
$refs = 0;
foreach ($files as $f) {
    $src = file_get_contents(dirname(__DIR__) . '/' . $f);
    foreach (array("\$S('actual_owner_name')", "data.actual_owner_name", "row['actual_owner_name']", "new Column('actual_owner_name'", "name=\"actual_owner_name\"") as $needle) {
        if (strpos($src, $needle) !== false) { $refs++; echo "    ! {$f}: {$needle}\n"; }
    }
}
check($refs === 0, "صفر قارئ/كاتب لأعمدة المالك في الشاشات الثلاث والتصدير ({$refs})");

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
