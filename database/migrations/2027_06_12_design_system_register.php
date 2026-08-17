<?php
/**
 * 2027_06_11_design_system_register.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تسجيلُ شاشةِ «النظام التصميمي — مرجع حي» ثلاثيَّ الأقفال
 * (S12: مبنيّةٌ وممنوحةٌ ونشطةُ الرابط — وإلا لا يصلها أحد):
 *   ① modules — السجلُّ والمالكُ (الدور 15 كأخواتِها في Governance/)
 *   ② role_permissions — عرضٌ وقرارٌ للدور 15 · وعرضٌ للرقابة (9 · 20 · 33)
 *   ③ nav_items — رابطٌ في بابِ GOV للدور 15 بمجموعةِ لوحةِ الناقلِ نفسِها
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

$CODE = 'Governance/design_system.php';

echo "\n▐ ① السجلُّ modules\n";
$mid = $one("SELECT id FROM modules WHERE code='{$CODE}'");
if ($mid === null) {
    $conn->query("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                  VALUES ('النظام التصميمي — مرجع حي', '{$CODE}', 15, '0', 0, 'fa fa-palette', 611)");
    $mid = $conn->insert_id;
    if (!$mid) { exit("   ✗ إدراجُ الوحدة: {$conn->error}\n"); }
    echo "   ✔ سُجِّلت الوحدةُ #{$mid}\n";
} else {
    echo "   · مسجَّلةٌ سلفًا #{$mid}\n";
}
$mid = (int) $mid;

echo "\n▐ ② المنحُ role_permissions\n";
$GRANTS = array(
    array(15, 1, 0, 1, 0),  // الصلاحياتُ والحوكمة: عرضٌ وتسجيلُ قرار
    array(9,  1, 0, 0, 0),  // الإدارةُ التنفيذية: اطلاع
    array(20, 1, 0, 0, 0),  // المراجعُ والمدققُ المالي: اطلاع
    array(33, 1, 0, 0, 0),  // المراجعُ الداخليُّ المستقل: اطلاع
);
$gi = $conn->prepare("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                      SELECT ?,?,?,?,?,? FROM DUAL
                       WHERE NOT EXISTS (SELECT 1 FROM role_permissions WHERE role_id=? AND module_id=?)");
foreach ($GRANTS as $g2) {
    list($rid, $v, $a, $e, $d) = $g2;
    $gi->bind_param('iiiiiiii', $rid, $mid, $v, $a, $e, $d, $rid, $mid);
    $gi->execute();
}
$gi->close();
printf("   ✔ منحٌ قائمة: %s من 4\n", $one("SELECT COUNT(*) FROM role_permissions WHERE module_id={$mid}"));

echo "\n▐ ③ الرابطُ nav_items — بابُ GOV للدور 15 بمجموعةِ لوحةِ الناقل\n";
$grp = $one("SELECT group_id FROM nav_items WHERE route='Governance/bus_board.php' AND role_id=15 AND active=1 LIMIT 1");
$grpSql = ($grp === null) ? 'NULL' : (int) $grp;
$exists = $one("SELECT COUNT(*) FROM nav_items WHERE role_id=15 AND route='{$CODE}'");
if ((int) $exists === 0) {
    $conn->query("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
                  VALUES (15, 'GOV', {$grpSql}, {$mid}, 'النظام التصميمي', '{$CODE}', 'fa fa-palette', 91, '{$CODE}', 1)");
    echo $conn->error ? "   ✗ {$conn->error}\n" : "   ✔ أُدرج الرابطُ في المجموعةِ {$grpSql}\n";
} else {
    $conn->query("UPDATE nav_items SET active=1, module_id={$mid}, permission_code='{$CODE}' WHERE role_id=15 AND route='{$CODE}'");
    echo "   · الرابطُ قائمٌ — فُعِّل وأُسند\n";
}

printf("\n   · الأقفالُ الثلاثة: وحدة=%s · منح=%s · رابط نشط=%s\n",
    $one("SELECT COUNT(*) FROM modules WHERE code='{$CODE}'"),
    $one("SELECT COUNT(*) FROM role_permissions WHERE module_id={$mid} AND can_view=1"),
    $one("SELECT COUNT(*) FROM nav_items WHERE route='{$CODE}' AND active=1"));
echo "✔ الشاشةُ مسجَّلةٌ ثلاثيَّ الأقفال\n";
