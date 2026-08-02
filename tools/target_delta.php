<?php
/**
 * T-03/T-05 · مصالحةُ المستهدف بالحي — update0007
 *   ① السجلُّ الفريد (173) مقابل nav routes الحية + الملفات على القرص.
 *   ② الظهوراتُ (332) مقابل nav_items الحية → قرارٌ لكل ظهور.
 * المخرج: docs/nav07/target_delta.csv + ملخصٌ رقمي.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/target_order_read.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$root = dirname(__DIR__);
@mkdir("$root/docs/nav07", 0777, true);

$live = array();
$r = mysqli_query($conn, "SELECT route, role_id, label_ar FROM nav_items WHERE active=1");
while ($x = mysqli_fetch_assoc($r)) $live[strtolower(trim($x['route']))][] = $x;

$fh = fopen("$root/docs/nav07/target_delta.csv", 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, array('الإدارة','الشاشة','المسار','المصدر','الحكم'));
$stat = array();
foreach (target_appearances() as $a) {
    $role = target_dept_role($a['dept']);
    $route = $a['route'] === '—' ? '' : $a['route'];
    $isNew = mb_strpos($a['source'], '★') !== false;
    $verdict = '';
    if ($a['dept'] === 'مساحة عملي') {
        $verdict = $isNew ? 'build:workspace' : 'workspace-ok';
    } elseif ($isNew) {
        // جديدٌ — أهو مبنيٌّ عندنا فعلًا؟ (بلاغات إدارتي = dept_inbox)
        if (mb_strpos($a['name'], 'بلاغات إدارتي') !== false) $verdict = 'link:dept_inbox';
        else $verdict = 'build:new';
    } elseif ($route !== '') {
        $roles = array_column($live[strtolower($route)] ?? array(), 'role_id');
        if (in_array($role, array_map('intval', $roles), true)) $verdict = 'ok:placed';
        elseif ($roles) $verdict = 'move:to-role-' . $role;
        else $verdict = 'revive:role-' . $role;
    } else { $verdict = 'tab-or-report'; }
    $stat[$verdict === '' ? '?' : preg_replace('/:.*/', '', $verdict)] =
        ($stat[preg_replace('/:.*/', '', $verdict)] ?? 0) + 1;
    fputcsv($fh, array($a['dept'], $a['name'], $route, $a['source'], $verdict));
}
fclose($fh);
foreach ($stat as $k => $v) echo "  $k: $v\n";

// T-03: الفريدة مقابل القرص والسجل
$missFile = 0; $offNav = 0;
foreach (array_slice(target_sheet(19), 2) as $r2) {
    $p = trim($r2[5] ?? '');
    if ($p === '' || $p === '—') continue;
    if (!is_file("$root/$p")) $missFile++;
}
echo "فريدةٌ بلا ملفٍّ على القرص: $missFile\n";
