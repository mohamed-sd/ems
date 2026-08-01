<?php
/**
 * tests/fin26_role_test.php — FIN-26 «إدارة التمويل» دورًا كاملًا (DEC-01 ②)
 * ═══════════════════════════════════════════════════════════════════════════
 * الثوابت المختبرة (ثوابت لا أعداد):
 *   ① الدور 26 قائم باسمه ونشط — وحارس ثوابت roles.php يمرّ له (الاسم مطابق).
 *   ② الثلاثية: صف HOME بلوحته (214) + عناصر FIN (210·211) + REP (212)
 *      + GOV قراءةً (206–208) — كلها على موديولات حية بcan_view.
 *   ③ صلاحياته الأربع كاملة على 210/211 — وعرضًا فقط على 206–208 و212.
 *   ④ مستخدم الدور **بلا منحة** لا يرى باب FIN (لا يُصيَّر — fail-closed).
 *   ⑤ وبمنحة فردية نافذة يراه.
 *   ⑥ العلمان (NAV_UNIFIED · ROLE_BOARD) يشملان 26 ولوحته من roleBoardRoute.
 *   ⑦ الامتثال: منح can_delete في الجدول لا يقابله زر حذف في شاشتي التمويل
 *      (SCR-02 §3-⑤) — والشاشتان خلف بوابة المجال المقيَّد لا check_page_permissions.
 * التشغيل: php tests/fin26_role_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__) . '/includes/unified_nav.php';
require_once dirname(__DIR__) . '/includes/role_board.php';
require_once dirname(__DIR__) . '/app/Core/OwnershipDomainGuard.php';

use App\Core\OwnershipDomainGuard as ODG;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$U_NOGRANT = 999301; $U_GRANTED = 999302;

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

$teardown = function () use ($conn) {
    $conn->query("DELETE FROM ownership_access_grants WHERE person_id IN (999301, 999302)");
    $conn->query("DELETE FROM sensitive_read_log WHERE person_id IN (999301, 999302)");
};
register_shutdown_function($teardown);
$teardown();

head('① الدور 26 وحارس الثوابت');
$r = $conn->query("SELECT name, status FROM roles WHERE id = 26")->fetch_assoc();
check($r !== null, 'الدور 26 قائم في roles');
check($r && trim($r['name']) === 'إدارة التمويل', 'باسمه «إدارة التمويل»');
check($r && (strval($r['status']) === '1'), 'نشط');
check(defined('EMS_ROLE_FINANCING_MGR') && EMS_ROLE_FINANCING_MGR === '26', 'ثابت EMS_ROLE_FINANCING_MGR = 26 (نص لا عدد)');
check(isset(EMS_ROLE_NAMES['26']) && $r && EMS_ROLE_NAMES['26'] === trim($r['name']),
    'حارس ADR-07 يمرّ للدور 26: اسم الفهرس يطابق القاعدة الحية حرفيًّا');

head('② الثلاثية — HOME بلوحته وأبوابه على موديولات حية');
$nav = array();
$q = $conn->query("SELECT n.door, n.route, n.module_id, m.id mid FROM nav_items n
                    LEFT JOIN modules m ON m.id = n.module_id
                   WHERE n.role_id = 26 AND n.active = 1");
while ($x = $q->fetch_assoc()) { $nav[$x['route']] = $x; }
check(isset($nav['Financing/financing_board.php']) && $nav['Financing/financing_board.php']['door'] === 'HOME',
    'صف HOME «الرئيسية» يفتح لوحته (الشاشة 214)');
check(isset($nav['Financing/financiers_registry.php']) && $nav['Financing/financiers_registry.php']['door'] === 'FIN', 'FIN: سجل الممولين (210)');
check(isset($nav['Financing/financing_operation_new.php']) && $nav['Financing/financing_operation_new.php']['door'] === 'FIN', 'FIN: إنشاء عملية تمويل (211)');
check(isset($nav['Reports/approval_lag_report.php']) && $nav['Reports/approval_lag_report.php']['door'] === 'REP', 'REP: مؤشر 212');
foreach (array('Governance/entities_registry.php' => 206, 'Governance/signing_authority.php' => 207,
               'Governance/licenses_guarantees.php' => 208) as $rt => $mid) {
    check(isset($nav[$rt]) && $nav[$rt]['door'] === 'GOV' && intval($nav[$rt]['module_id']) === $mid, "GOV قراءةً: {$rt} ({$mid})");
}
$dead = 0;
foreach ($nav as $x) { if ($x['mid'] === null) { $dead++; } }
check($dead === 0, 'صفر عنصر قائمة على موديول ميت');

head('③ الصلاحيات — أربعُها على شاشتي بابه وعرضًا على غيرها');
$perm = array();
$q = $conn->query("SELECT module_id, can_view, can_add, can_edit, can_delete FROM role_permissions WHERE role_id = 26");
while ($x = $q->fetch_assoc()) { $perm[intval($x['module_id'])] = $x; }
foreach (array(210, 211) as $m) {
    check(isset($perm[$m]) && $perm[$m]['can_view'] == 1 && $perm[$m]['can_add'] == 1
        && $perm[$m]['can_edit'] == 1 && $perm[$m]['can_delete'] == 1, "الشاشة {$m}: الصلاحيات الأربع كاملة");
}
foreach (array(206, 207, 208, 212) as $m) {
    check(isset($perm[$m]) && $perm[$m]['can_view'] == 1 && $perm[$m]['can_add'] == 0
        && $perm[$m]['can_edit'] == 0 && $perm[$m]['can_delete'] == 0, "الشاشة {$m}: عرضًا فقط (can_view وحدها)");
}

head('④ بلا منحة لا يُصيَّر باب FIN — fail-closed');
$_SESSION['user'] = array('id' => $U_NOGRANT, 'role' => '26', 'company_id' => $CO, 'name' => 'FIN26 Probe');
$items = getUnifiedNavItems($conn, 26);
$doors = array_unique(array_map(function ($i) { return $i['door']; }, $items));
check(!in_array('FIN', $doors, true), 'مستخدم الدور 26 بلا منحة: باب FIN غير مُصيَّر أصلًا');
check(in_array('HOME', $doors, true) && in_array('GOV', $doors, true), 'وسائر أبوابه (HOME · GOV) ظاهرة — الحجب على FIN وحده');

head('⑤ وبمنحة فردية نافذة يراه');
$g = ODG::grant($conn, $CO, $U_GRANTED, ODG::PERM_OWNER, 1, 'حزمة fin26 — منحة مسبار');
check($g['ok'], 'مُنح المسبار ownership.owner_view');
$_SESSION['user'] = array('id' => $U_GRANTED, 'role' => '26', 'company_id' => $CO, 'name' => 'FIN26 Probe G');
$items = getUnifiedNavItems($conn, 26);
$fin = array_values(array_filter($items, function ($i) { return $i['door'] === 'FIN'; }));
check(count($fin) === 2, 'بالمنحة: باب FIN يُصيَّر بشاشتيه (210 · 211)');
unset($_SESSION['user']);

head('⑥ العلمان واللوحة');
check(unifiedNavEnabled('26'), 'EMS_NAV_UNIFIED_ROLES يشمل 26 — المصدر الموحّد');
check(roleBoardEnabled('26'), 'EMS_ROLE_BOARD_ROLES يشمل 26');
check(roleBoardRoute('26') === 'Financing/financing_board.php', 'roleBoardRoute(26) → لوحة التمويل (الدخول يهبط عليها)');

head('⑦ الامتثال — SCR-02 §3-⑤ وبوابة المجال لا check_page_permissions');
foreach (array('Financing/financiers_registry.php', 'Financing/financing_operation_new.php', 'Financing/financing_board.php') as $f) {
    $src = file_get_contents(dirname(__DIR__) . '/' . $f);
    check(strpos($src, 'OwnershipDomainGuard') !== false && strpos($src, 'check_page_permissions') === false,
        "{$f}: بوابة المجال المقيَّد لا check_page_permissions");
    check(stripos($src, "value=\"delete\"") === false && strpos($src, 'btn-delete') === false
        && strpos($src, 'DELETE FROM') === false,
        "{$f}: لا زر حذف ولا فعل حذف — المنح في الجدول لا الزر في الشاشة");
}
$src = file_get_contents(dirname(__DIR__) . '/Governance/ownership_grants.php');
check(strpos($src, 'OwnershipDomainGuard') !== false && strpos($src, 'AuthorityGuard::sign') !== false,
    'شاشة المنح 213: المنح عبر الخدمة وكل قرار بسطر توقيع');
check(strpos($src, "state = 'revoked'") !== false && strpos($src, 'DELETE FROM ownership_access_grants') === false,
    'والإلغاء تعليم حالة لا حذف صف — السجل باقٍ للتدقيق');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
