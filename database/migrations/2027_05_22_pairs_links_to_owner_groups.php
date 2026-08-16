<?php
/**
 * 2027_05_22_pairs_links_to_owner_groups.php
 * ═══════════════════════════════════════════════════════════════════════════
 * روابطُ الأزواجِ الأربعةِ وُضعت (05_19) في مجموعاتِ n9s فكسرت مطابقةَ
 * الورقةِ حرفًا — تُنقل إلى مجموعاتِ مالكٍ n9o_mydept (المعفاةِ بالتصميم)
 * فيبقى الظهورُ ويعود nav09 حرفًا.
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
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? $r->fetch_row()[0] : null; };

$moved = 0;
foreach (array(12 => array('Risk/risk_dept_sal.php', 'Governance/gov_dept_sal.php'),
               2  => array('Risk/risk_dept_sup.php', 'Governance/gov_dept_sup.php')) as $role => $routes) {
    $gc = 'n9o_mydept_r' . $role;
    $gid = (int) $one("SELECT id FROM link_groups WHERE group_code='$gc'");
    if (!$gid) {
        $conn->query("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                      VALUES ('متابعةُ إدارتي — المخاطرُ والحوكمة', '$gc', $role, 'fa fa-scale-balanced', 71, 7, 'متابعةُ إدارتي', 1)");
        $gid = (int) $conn->insert_id;
    }
    foreach ($routes as $rt) {
        $conn->query("UPDATE nav_items ni JOIN link_groups lg ON lg.id=ni.group_id
                      SET ni.group_id=$gid
                      WHERE ni.role_id=$role AND ni.route='" . $conn->real_escape_string($rt) . "'
                        AND lg.group_code LIKE 'n9s%'");
        $moved += $conn->affected_rows;
    }
}
echo "نُقل $moved رابطًا إلى مجموعاتِ المالك\n✔ تمّت\n";
