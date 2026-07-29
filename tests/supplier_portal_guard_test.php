<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-20 — اختبار قبول عزل مشرف المورد (SupplierPortalGuard)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/supplier_portal_guard_test.php
 *
 * ما يُثبته:
 *   ① البنية: users.supplier_entity_id قائمٌ بفهرسه وFK→suppliers.
 *   ② الهوية: isRestricted للدور 8 وحده — والسوبر وسائرُ الأدوار يمرّون.
 *   ③ النطاق: supplierOf من العمود الصريح ثم من ربط الموظف · scope
 *      fail-closed (مقيَّدٌ بلا مورد = 0 لا fail-open).
 *   ④ 403 مسجَّلة: طلبُ موردٍ أجنبي يُرفض ويترك صفَّ تدقيقٍ (N-02) —
 *      والحسابُ غيرُ المربوط يُرفض بعلاجٍ مسمًّى.
 *   ⑤ بوابة 404: isPortalScreen تقبل شاشاتِ البوابة وترفض ما سواها —
 *      وfilterNavItems يضيّق سايدبار الدور 8 الفعلي ولا يمسّ غيرَ المقيَّد.
 *   ⑥ حلُّ العقد: supplierOfContract يعيد موردَ العقد وnull لغير الموجود.
 *   ⑦ الوصلُ المصدري: الطبقةُ المركزية (gateScreen في permissions_helper ·
 *      filterNavItems في unified_nav) + الشاشاتُ السبع + نقاطُ AJAX الأربع
 *      + إلزامُ الربط في users.php.
 *
 * البذرُ معزول: مورّدان وموظفٌ وحسابان بوسم H20SPG_<pid> تُكنس في النهاية.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Services/Portal/SupplierPortalGuard.php';

use App\Services\Portal\SupplierPortalGuard as SPG;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$MARK = 'H20SPG_' . getmypid();
$auditFloor = intval($conn->query("SELECT COALESCE(MAX(id),0) m FROM activity_logs")->fetch_assoc()['m']);

$seed = array('suppliers' => array(), 'employees' => array(), 'users' => array());
$teardown = function () use ($conn, $MARK, &$seed, &$auditFloor) {
    foreach (array('users', 'employees', 'suppliers') as $t) {
        foreach ($seed[$t] as $id) { $conn->query("DELETE FROM {$t} WHERE id = " . intval($id)); }
    }
    $conn->query("DELETE FROM activity_logs WHERE id > {$auditFloor}
                    AND module_name = 'permissions' AND action_type = 'denied_403'");
};
register_shutdown_function($teardown);

fwrite(STDOUT, "\n══ H-20 — عزلُ مشرف المورد ══\n");

// ═══ البذر المعزول ═══
$conn->query("INSERT INTO suppliers (company_id, name, phone) VALUES ({$CO}, '{$MARK}_A', '0900000001')");
$supA = intval($conn->insert_id);
$conn->query("INSERT INTO suppliers (company_id, name, phone) VALUES ({$CO}, '{$MARK}_B', '0900000002')");
$supB = intval($conn->insert_id);
if ($supA > 0) { $seed['suppliers'][] = $supA; }
if ($supB > 0) { $seed['suppliers'][] = $supB; }
$conn->query("INSERT INTO employees (company_id, name, phone, supplier_id) VALUES ({$CO}, '{$MARK}_EMP', '0900000003', {$supB})");
$empB = intval($conn->insert_id);
if ($empB > 0) { $seed['employees'][] = $empB; }

// حسابٌ صريحُ الربط بالمورد A · وحسابٌ بلا ربطٍ صريحٍ لكن موظفُه تابعٌ للمورد B
$conn->query("INSERT INTO users (company_id, name, username, password, phone, role, status, created_at, updated_at, supplier_entity_id)
              VALUES ({$CO}, '{$MARK}_U1', '{$MARK}_u1', 'x', '0', '8', 'active', NOW(), NOW(), {$supA})");
$usrA = intval($conn->insert_id);
$conn->query("INSERT INTO users (company_id, name, username, password, phone, role, status, created_at, updated_at, employee_id)
              VALUES ({$CO}, '{$MARK}_U2', '{$MARK}_u2', 'x', '0', '8', 'active', NOW(), NOW(), {$empB})");
$usrB = intval($conn->insert_id);
$conn->query("INSERT INTO users (company_id, name, username, password, phone, role, status, created_at, updated_at)
              VALUES ({$CO}, '{$MARK}_U3', '{$MARK}_u3', 'x', '0', '8', 'active', NOW(), NOW())");
$usrN = intval($conn->insert_id);
foreach (array($usrA, $usrB, $usrN) as $u) { if ($u > 0) { $seed['users'][] = $u; } }
check($supA > 0 && $supB > 0 && $empB > 0 && $usrA > 0 && $usrB > 0 && $usrN > 0, 'بذرٌ معزول: مورّدان وموظفٌ وثلاثةُ حسابات');

$sessA = array('id' => $usrA, 'role' => '8', 'company_id' => $CO);
$sessB = array('id' => $usrB, 'role' => '8', 'company_id' => $CO);
$sessN = array('id' => $usrN, 'role' => '8', 'company_id' => $CO);
$sessMgr = array('id' => 1, 'role' => '2', 'company_id' => $CO);
$sessSuper = array('id' => 1, 'role' => '-1', 'company_id' => 0);

// ═══ ① البنية ═══
head('① البنية — العمودُ والفهرسُ وFK');
$r = $conn->query("SHOW COLUMNS FROM users LIKE 'supplier_entity_id'");
check($r && $r->num_rows === 1, 'users.supplier_entity_id قائم');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.KEY_COLUMN_USAGE
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
                     AND COLUMN_NAME = 'supplier_entity_id' AND REFERENCED_TABLE_NAME = 'suppliers'");
check($r && intval($r->fetch_assoc()['c']) === 1, 'FK → suppliers قائم');

// ═══ ② الهوية ═══
head('② isRestricted — الدورُ 8 وحده');
check(SPG::isRestricted(array('role' => '8')), 'الدور 8 مقيَّد');
check(SPG::isRestricted(array('role' => 8)), 'الدور 8 عدديًّا مقيَّد');
check(!SPG::isRestricted($sessMgr), 'مدير الموردين (2) غيرُ مقيَّد');
check(!SPG::isRestricted($sessSuper), 'السوبر (-1) غيرُ مقيَّد');
check(!SPG::isRestricted(array()), 'جلسةٌ بلا دور غيرُ مقيَّدة');

// ═══ ③ النطاق ═══
head('③ supplierOf / scope — الصريحُ ثم الموظفُ ثم fail-closed');
check(SPG::supplierOf($conn, $sessA) === $supA, 'الربطُ الصريح: supplierOf = المورد A');
check(SPG::supplierOf($conn, $sessB) === $supB, 'رِدفُ الموظف: supplierOf من employees.supplier_id = المورد B');
check(SPG::supplierOf($conn, $sessN) === null, 'حسابٌ بلا ربط: null');
check(SPG::scope($conn, $sessMgr) === null, 'غيرُ المقيَّد: scope=null (يرى الكل)');
check(SPG::scope($conn, $sessA) === $supA, 'المقيَّدُ المربوط: scope=مورده');
check(SPG::scope($conn, $sessN) === 0, 'المقيَّدُ غيرُ المربوط: scope=0 (fail-closed لا fail-open)');

// ═══ ④ 403 مسجَّلة ═══
head('④ assertSupplier — موردُه يمرّ وغيرُه 403 بأثر تدقيق');
$v = SPG::assertSupplier($conn, $sessA, $supA, $MARK);
check($v['ok'] === true, 'طلبُ موردِه: يمرّ');
$v = SPG::assertSupplier($conn, $sessA, 0, $MARK);
check($v['ok'] === true && $v['supplier_id'] === $supA, 'طلبُ القائمة (0): يمرّ ويعيد موردَه للحقن');
$v = SPG::assertSupplier($conn, $sessMgr, $supB, $MARK);
check($v['ok'] === true, 'غيرُ المقيَّد بأي معرّف: يمرّ');
$before403 = intval($conn->query("SELECT COUNT(*) c FROM activity_logs WHERE id > {$auditFloor}
    AND module_name='permissions' AND action_type='denied_403'")->fetch_assoc()['c']);
$v = SPG::assertSupplier($conn, $sessA, $supB, $MARK);
check($v['ok'] === false && $v['code'] === 403, 'موردٌ أجنبي: 403');
$v2 = SPG::assertSupplier($conn, $sessN, $supA, $MARK);
check($v2['ok'] === false && $v2['code'] === 403 && mb_strpos($v2['reason'], 'مربوط') !== false,
      'حسابٌ بلا ربط: 403 برسالةِ علاجٍ مسمّاة');
$after403 = intval($conn->query("SELECT COUNT(*) c FROM activity_logs WHERE id > {$auditFloor}
    AND module_name='permissions' AND action_type='denied_403'")->fetch_assoc()['c']);
check($after403 - $before403 === 2, 'الرفضان تركا صفَّي تدقيقٍ (N-02) — «محاولاتُ الرفض مسجَّلة» §10-③');
$row = $conn->query("SELECT new_value FROM activity_logs WHERE id > {$auditFloor}
    AND module_name='permissions' AND action_type='denied_403'
    AND new_value LIKE '%cross_supplier_attempt%' ORDER BY id DESC LIMIT 1")->fetch_assoc();
check($row && strpos($row['new_value'], strval($supB)) !== false, 'صفُّ التجاوز يحمل المعرّفَ المحاوَل');

// ═══ ⑤ بوابة 404 وترشيح السايدبار ═══
head('⑤ isPortalScreen / filterNavItems — البوابةُ أضيقُ عمدًا');
check(SPG::isPortalScreen('Suppliers/suppliers.php'), 'شاشةُ موردِه داخل البوابة');
check(SPG::isPortalScreen('ems/Finance/supplier_statement_fin.php'), 'كشفُه داخل البوابة (بمسارٍ مسبوق)');
check(SPG::isPortalScreen('main/role_board.php'), 'لوحتُه داخل البوابة');
check(SPG::isPortalScreen('chats/index.php'), 'مراسلاتُه داخل البوابة (بادئة)');
check(!SPG::isPortalScreen('Clients/clients.php'), 'العملاء خارج البوابة');
check(!SPG::isPortalScreen('main/users.php'), 'شاشةُ الصلاحيات خارج البوابة');
check(!SPG::isPortalScreen('main/user_profile.php'), 'بطاقةُ مستخدمٍ بمعرّفٍ حر خارج البوابة');
check(!SPG::isPortalScreen('Finance/payments_fin.php'), 'الخزينة خارج البوابة');
check(!SPG::isPortalScreen('FinRequests/request_form.php'), 'الطلبات المالية خارج البوابة');

$navRows = array();
$rs = $conn->query("SELECT route FROM nav_items WHERE role_id = 8 AND active = 1");
while ($rw = $rs->fetch_assoc()) { $navRows[] = $rw; }
$kept = SPG::filterNavItems($sessA, $navRows);
$keptRoutes = array_map(function ($x) { return $x['route']; }, $kept);
check(count($navRows) >= 8 && count($kept) < count($navRows), 'سايدبارُ الدور 8 ضاق فعلًا (' . count($navRows) . '→' . count($kept) . ')');
check(in_array('Suppliers/suppliers.php', $keptRoutes, true)
   && in_array('Finance/supplier_statement_fin.php', $keptRoutes, true)
   && in_array('main/role_board.php', $keptRoutes, true), 'أبوابُه الباقية: لوحتُه وموردُه وكشفُه');
check(!in_array('Settings/settings.php', $keptRoutes, true)
   && !in_array('Finance/payments_fin.php', $keptRoutes, true)
   && !in_array('main/project_users.php', $keptRoutes, true), 'الإعداداتُ والخزينةُ والمشرفون أُخفوا');
check(count(SPG::filterNavItems($sessMgr, $navRows)) === count($navRows), 'غيرُ المقيَّد لا يُرشَّح');

// ═══ ⑥ حلُّ العقد ═══
head('⑥ supplierOfContract — عقدٌ إلى موردِه');
$cr = $conn->query("SELECT id, supplier_id FROM supplierscontracts WHERE supplier_id IS NOT NULL AND supplier_id > 0 LIMIT 1");
if ($cr && ($crow = $cr->fetch_assoc())) {
    check(SPG::supplierOfContract($conn, intval($crow['id'])) === intval($crow['supplier_id']),
          'عقدٌ حقيقي #' . $crow['id'] . ' → مورده #' . $crow['supplier_id']);
} else {
    ok('لا عقودَ موردين في القاعدة — الفرعُ مغطًّى بالحالة السالبة');
}
check(SPG::supplierOfContract($conn, 99999999) === null, 'عقدٌ غيرُ موجود → null');
check(SPG::supplierOfContract($conn, 0) === null, 'معرّفٌ صفري → null');

// ═══ ⑦ الوصلُ المصدري ═══
head('⑦ الوصلُ المصدري — الطبقةُ والشاشاتُ والنقاط');
$root = dirname(__DIR__);
$mustContain = array(
    'includes/permissions_helper.php'                    => 'SupplierPortalGuard::gateScreen',
    'includes/unified_nav.php'                           => 'SupplierPortalGuard::filterNavItems',
    'Suppliers/suppliers.php'                            => 'SupplierPortalGuard::enforce',
    'Suppliers/suppliers_details.php'                    => 'SupplierPortalGuard::enforce',
    'Suppliers/supplier_profile.php'                     => 'SupplierPortalGuard::enforce',
    'Suppliers/supplierscontracts.php'                   => 'SupplierPortalGuard::enforce',
    'Suppliers/supplierscontracts_details.php'           => 'supplierOfContract',
    'Suppliers/showcontractsuppliers.php'                => 'supplierOfContract',
    'Finance/supplier_statement_fin.php'                 => 'SupplierPortalGuard::enforce',
    'Suppliers/get_supplier_contract_equipments.php'     => 'enforceJson',
    'Suppliers/get_mine_contracts.php'                   => 'isRestricted',
    'Suppliers/get_project_hours.php'                    => 'isRestricted',
    'Suppliers/supplier_contract_actions_handler.php'    => 'isRestricted',
    'main/users.php'                                     => 'supplier_entity_id',
);
foreach ($mustContain as $f => $needle) {
    $src = @file_get_contents($root . '/' . $f);
    check($src !== false && strpos($src, $needle) !== false, "وصل {$f} ({$needle})");
}
// إلزامُ الربط عند الحفظ في users.php (رفضُ دور 8 بلا مورد)
$src = file_get_contents($root . '/main/users.php');
check(strpos($src, "يجب ربطه بمورد") !== false, 'users.php يُلزم ربطَ حساب الدور 8 بمورد عند الحفظ');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
