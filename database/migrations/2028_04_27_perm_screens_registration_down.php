<?php
/**
 * 2028_04_27_perm_screens_registration_down.php — عكسُ تسجيلِ شاشاتِ الصلاحيات
 * ◆ يحذف ما أنشأته الهجرةُ **بالمسارِ لا بالمعرِّف**: عشرةُ مساراتٍ باسمِها
 *   الصريح، ومجموعةُ السايدبارِ إن خلت من بنودها.
 * ⛔ ولا يُنشئ هذا الملفُّ شيئًا — عكسٌ محضٌ [[rpr0-migration-ledger-gate]].
 * ⛔ ولا يمسُّ `settings/roles.php` ولا أيَّ شاشةٍ سابقةٍ في مجموعةِ الدليل 52.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');

$ROUTES = array(
    'Governance/perm_dashboard.php', 'Governance/perm_roles.php', 'Governance/perm_modules.php',
    'Governance/perm_matrix.php', 'Governance/perm_link_groups.php', 'Governance/perm_nav_items.php',
    'Governance/perm_screen_guide.php', 'Governance/perm_reports.php', 'Governance/perm_system_status.php',
    'Governance/perm_quick_update.php',
);
$in = array(); $lo = array();
foreach ($ROUTES as $r) { $in[] = "'" . $conn->real_escape_string($r) . "'"; $lo[] = "'" . $conn->real_escape_string(strtolower($r)) . "'"; }
$IN = implode(',', $in); $LO = implode(',', $lo);

$n = 0;
foreach (array(
    "DELETE rp FROM role_permissions rp JOIN modules m ON m.id = rp.module_id WHERE m.code IN ($IN)" => 'role_permissions',
    "DELETE FROM nav_items WHERE route IN ($IN)"                                                     => 'nav_items',
    "DELETE FROM nav_placements WHERE route IN ($LO)"                                                => 'nav_placements',
    "DELETE FROM nav_canonical WHERE route IN ($IN)"                                                 => 'nav_canonical',
    "DELETE FROM nav_route_group WHERE route IN ($IN)"                                               => 'nav_route_group',
    "DELETE FROM gov_space_appearances WHERE route IN ($IN)"                                         => 'gov_space_appearances',
    "DELETE FROM screen_about WHERE screen_path IN ($IN)"                                            => 'screen_about',
    "DELETE FROM modules WHERE code IN ($IN)"                                                        => 'modules',
) as $sql => $label) {
    $conn->query($sql);
    printf("- %-24s %d صفًّا\n", $label, $conn->affected_rows);
    $n += max(0, $conn->affected_rows);
}

/* المجموعةُ تُحذف **إن خلت** — فبندٌ آخرُ قد يكون أُسند إليها لاحقًا. */
$g = $conn->query("SELECT id FROM link_groups WHERE name = 'إدارة الصلاحيات والأدوار' AND owner_role_id = 15 LIMIT 1");
if ($g && ($row = $g->fetch_assoc())) {
    $c = $conn->query('SELECT COUNT(*) c FROM nav_items WHERE group_id = ' . (int) $row['id'])->fetch_assoc();
    if ((int) $c['c'] === 0) {
        $conn->query('DELETE FROM link_groups WHERE id = ' . (int) $row['id']);
        echo "- link_groups: حُذفت المجموعةُ " . $row['id'] . "\n";
    } else {
        echo "= link_groups: المجموعةُ " . $row['id'] . " فيها " . $c['c'] . " بندًا — تُركت\n";
    }
}

echo "◆ المجموع: $n صفًّا\n";
$conn->query("DELETE FROM `schema_migrations` WHERE `filename` = '2028_04_27_perm_screens_registration.php'");
echo '- قيدُ الدفتر: ' . $conn->affected_rows . "\n";
