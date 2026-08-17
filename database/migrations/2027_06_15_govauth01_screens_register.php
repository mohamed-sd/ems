<?php
/**
 * 2027_06_15_govauth01_screens_register.php
 * ═══════════════════════════════════════════════════════════════════════════
 * GOV-AUTH-01 §8-3 ⑤ — تسجيلُ الشاشاتِ الثلاثِ ثلاثيَّ الأقفال (S12):
 * القوالبُ (auth_profiles) · المنحُ (auth_grants) · جلساتُ النيابة (impersonations)
 * المالكُ الدورُ 15 كأخواتِها · وعرضٌ للرقابة (9 · 20 · 33) · وتعديلٌ (سحبُ
 * المنحِ) للحوكمةِ وحدَها في auth_grants.
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

$SCREENS = array(
    // code · name · icon · order · nav label · can_edit for role 15
    array('Governance/auth_profiles.php', 'قوالب الصلاحيات المعيارية', 'fa fa-id-card', 612, 'قوالب الصلاحيات', 0),
    array('Governance/auth_grants.php', 'منح الصلاحية', 'fa fa-key', 613, 'منح الصلاحية', 1),
    array('Governance/impersonations.php', 'جلسات النيابة', 'fa fa-user-shield', 614, 'جلسات النيابة', 0),
);
$grp = $one("SELECT group_id FROM nav_items WHERE route='Governance/bus_board.php' AND role_id=15 AND active=1 LIMIT 1");
$grpSql = ($grp === null) ? 'NULL' : (int) $grp;

foreach ($SCREENS as $ix => $S) {
    list($code, $name, $icon, $ord, $label, $edit15) = $S;
    echo "\n▐ {$name}\n";
    $codeQ = $conn->real_escape_string($code);
    $mid = $one("SELECT id FROM modules WHERE code='{$codeQ}'");
    if ($mid === null) {
        $conn->query("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                      VALUES ('" . $conn->real_escape_string($name) . "', '{$codeQ}', 15, '0', 0, '{$icon}', {$ord})");
        $mid = $conn->insert_id;
        if (!$mid) { echo "   ✗ الوحدة: {$conn->error}\n"; continue; }
    }
    $mid = (int) $mid;
    $GRANTS = array(array(15, 1, 0, $edit15, 0), array(9, 1, 0, 0, 0), array(20, 1, 0, 0, 0), array(33, 1, 0, 0, 0));
    foreach ($GRANTS as $g2) {
        list($rid, $v, $a, $e, $d) = $g2;
        $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                      SELECT {$rid}, {$mid}, {$v}, {$a}, {$e}, {$d} FROM DUAL
                       WHERE NOT EXISTS (SELECT 1 FROM role_permissions WHERE role_id={$rid} AND module_id={$mid})");
    }
    $navOrd = 92 + $ix;
    $conn->query("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
                  SELECT 15, 'GOV', {$grpSql}, {$mid}, '" . $conn->real_escape_string($label) . "', '{$codeQ}', '{$icon}', {$navOrd}, '{$codeQ}', 1 FROM DUAL
                   WHERE NOT EXISTS (SELECT 1 FROM nav_items WHERE role_id=15 AND route='{$codeQ}')");
    printf("   ✔ وحدة=%s · منح=%s · رابط=%s\n",
        $one("SELECT COUNT(*) FROM modules WHERE code='{$codeQ}'"),
        $one("SELECT COUNT(*) FROM role_permissions WHERE module_id={$mid} AND can_view=1"),
        $one("SELECT COUNT(*) FROM nav_items WHERE route='{$codeQ}' AND active=1"));
}
echo "\n✔ الشاشاتُ الثلاثُ مسجَّلةٌ ثلاثيَّ الأقفال\n";
