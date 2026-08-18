<?php
/**
 * 2027_06_25_authority_caps_register.php — تسجيلُ شاشةِ حدودِ المبالغ (قرار ⑥)
 * ① modules ② صلاحياتُ العرضِ للحوكمةِ (15) والمالكِ (9) — والكتابةُ بالقادحِ للمالكِ
 * ③ nav_items للدورَين في بابِ GOV
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
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };
$CODE = 'Governance/authority_caps.php';

$mid = $one("SELECT id FROM modules WHERE code='{$CODE}'");
if ($mid === null) {
    $conn->query("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                  VALUES ('حدود المبالغ', '{$CODE}', 15, '0', 0, 'fa fa-scale-balanced', 610)");
    $mid = (int) $conn->insert_id;
    echo "✔ module #{$mid}\n";
} else { $mid = (int) $mid; echo "· module قائم #{$mid}\n"; }

foreach (array(9, 15) as $rid) {
    $conn->query("INSERT IGNORE INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                  VALUES ({$rid}, {$mid}, 1, " . ($rid === 9 ? 1 : 0) . ", " . ($rid === 9 ? 1 : 0) . ", 0)");
}
echo "✔ عرضٌ للحوكمةِ والمالكِ — والكتابةُ للمالكِ (والقادحُ يفرضها بنيويًّا)\n";

foreach (array(9, 15) as $rid) {
    $exists = (int) $one("SELECT COUNT(*) FROM nav_items WHERE role_id={$rid} AND route='{$CODE}'");
    if ($exists === 0) {
        $grp = $one("SELECT group_id FROM nav_items WHERE role_id={$rid} AND route LIKE 'Governance/%' AND active=1 AND group_id IS NOT NULL LIMIT 1");
        $g = $grp !== null ? (int) $grp : 'NULL';
        $conn->query("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
                      VALUES ({$rid}, 'GOV', {$g}, {$mid}, 'حدود المبالغ', '{$CODE}', 'fa fa-scale-balanced', 615, '{$CODE}', 1)");
        echo "✔ رابطُ الدورِ {$rid}\n";
    } else { echo "· رابطُ الدورِ {$rid} قائم\n"; }
}
printf("· تحقق: view=%s\n", $one("SELECT COUNT(*) FROM role_permissions WHERE module_id={$mid} AND can_view=1"));
