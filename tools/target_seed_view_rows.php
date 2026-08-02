<?php
/**
 * T-02-ب · بذرُ مصفوفة العرض من الترتيب المستهدف — update0007
 * idempotent (UQ screen×dept + ON DUPLICATE UPDATE).
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/target_order_read.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

// المسارُ من سجل الشاشات الفريدة (19): الاسم → المسار
$routes = array();
foreach (array_slice(target_sheet(19), 2) as $r) {
    $n = trim($r[1] ?? ''); $p = trim($r[5] ?? '');
    if ($n !== '' && $p !== '' && $p !== '—') $routes[$n] = $p;
}
$st = mysqli_prepare($conn,
  "INSERT INTO screen_view_rows (screen_name, route, dept, role_id, role_kind, scope_text, angle, columns_text, filters_text, active)
   VALUES (?,?,?,?,?,?,?,?,?,1)
   ON DUPLICATE KEY UPDATE route=VALUES(route), role_id=VALUES(role_id), role_kind=VALUES(role_kind),
     scope_text=VALUES(scope_text), angle=VALUES(angle), columns_text=VALUES(columns_text), filters_text=VALUES(filters_text)");
$n = 0;
foreach (target_view_rows() as $v) {
    $kind = (mb_strpos($v['role_kind'], 'مالك') !== false) ? 'owner' : 'viewer';
    $role = target_dept_role($v['dept']);
    $route = $routes[$v['screen']] ?? null;
    mysqli_stmt_bind_param($st, 'sssisssss', $v['screen'], $route, $v['dept'], $role, $kind,
                           $v['scope'], $v['angle'], $v['columns'], $v['filters']);
    if (mysqli_stmt_execute($st)) $n++;
    else fwrite(STDERR, "✘ {$v['screen']}×{$v['dept']}: " . mysqli_error($conn) . "\n");
}
$r = mysqli_query($conn, "SELECT role_kind, COUNT(*) c FROM screen_view_rows GROUP BY role_kind");
echo "بُذر/حُدّث: $n\n";
while ($x = mysqli_fetch_assoc($r)) echo "  {$x['role_kind']}: {$x['c']}\n";
