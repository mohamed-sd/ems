<?php
/**
 * 2028_05_29_quick_flag_group_roles_down.php — عكسُ الاستيفاء
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **ولا يُعكس إلّا ما لا يعكسه سابقُه**: صفوفُ `active=0 · is_quick=1 ·
 *   sort_order=900` يحذفها عكسُ 2028_05_28 أصلًا، والرايةُ كلُّها تسقط بإسقاطِ
 *   العمود. فما يبقى لهذه الجولةِ: **رفعُ رايةٍ عن صفوفِ الأدوارِ الأربعةِ التي
 *   جاءت بلاطاتُها من المجموعات** — وذلك بردِّها إلى ما كانت عليه قبلَها.
 * ◆ **وتُميَّز بالفرعِ الذي بذرها**: ما ليس في الفرعِ الإرثيِّ (`modules.is_quick`)
 *   ولا صفًّا ساكنًا وُلد هنا ⇒ رايةٌ رفعتها هذه الجولةُ وحدَها.
 * ═══════════════════════════════════════════════════════════════════════════
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
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };
$GLOBALS['conn'] = $conn;
require_once $ROOT . '/includes/dynamic_nav.php';

$has = $one("SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nav_items' AND COLUMN_NAME = 'is_quick'");
if ($has === 0) { echo "لا عمودَ is_quick — لا شيءَ يُعكس\n"; exit(0); }

$norm = function ($s) {
    $s = preg_replace('~^(\.\./)+~', '', (string) $s);
    $s = preg_replace('~[?#].*$~', '', $s);
    return strtolower(trim(preg_replace('~\.php$~i', '', $s), '/'));
};

$mq = array();
$r = $conn->query('SELECT id FROM modules WHERE is_quick = 1');
while ($r && ($x = $r->fetch_row())) { $mq[(int) $x[0]] = true; }

$roles = array();
$r = $conn->query('SELECT id FROM roles ORDER BY id');
while ($r && ($x = $r->fetch_row())) { $roles[] = (int) $x[0]; }

$cleared = 0;
foreach ($roles as $rid) {
    $keep = array();
    foreach (getDynamicNavLinks($conn, (string) $rid) as $l) {
        if (!empty($mq[(int) $l['id']])) { $keep[$norm((string) $l['code'])] = true; }
    }
    $q = $conn->query("SELECT id, route FROM nav_items
                        WHERE role_id = {$rid} AND is_quick = 1 AND NOT (active = 0 AND sort_order = 900)");
    $ids = array();
    while ($q && ($x = $q->fetch_assoc())) {
        if (!isset($keep[$norm($x['route'])])) { $ids[] = (int) $x['id']; }
    }
    if ($ids) {
        $conn->query('UPDATE nav_items SET is_quick = 0 WHERE id IN (' . implode(',', $ids) . ')');
        $cleared += $conn->affected_rows;
    }
}
printf("   رايات رُفعت عنها: %d\n", $cleared);
printf("   رايات باقية: %d\n", $one("SELECT COUNT(*) FROM nav_items WHERE is_quick = 1"));

$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` = '2028_05_29_quick_flag_group_roles.php'");
echo "✔ عُكس.\n";
