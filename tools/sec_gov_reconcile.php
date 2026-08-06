<?php
/**
 * tools/sec_gov_reconcile.php — مصالحة حزامي nav09 وSEC-GOV على شاشات الحوكمة
 * ───────────────────────────────────────────────────────────────────────────
 * التعارض: وثيقة NAV-09 تُبقي رابط شاشة حوكمةٍ في قائمة دورٍ غير مالكها،
 * وهجرة التحصين صفّرت صلاحيته صراحةً — فيرى nav09_verify «can_view=0 صريحة»
 * خرقًا، ويرى sec_perm_checks أيَّ صفٍّ للدور خرقًا.
 * الحل البنيوي: **غياب الصف** — فحص nav09 يرصد الصفرَ الصريح وحده، وفحص
 * SEC-GOV يرصد وجودَ الصف، والحارس المركزي fail-closed يمنع بلا صف،
 * والمولد يخفي الرابط عرضًا. النسخة الأصلية في sec_perm_backup_20260806.
 *
 * idempotent — يمسح صفوف الأدوار غير المالكة وغير (1·15) على شاشات الحوكمة
 * حين تكون كلُّ راياتها صفرًا (أثر التصفير لا منحة حية).
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$GOV = array('main/users.php', 'main/all_assistants.php', 'Settings/roles.php',
             'Settings/role_permissions.php', 'Settings/modules.php');
$in = "'" . implode("','", array_map(function ($p) use ($conn) { return $conn->real_escape_string($p); }, $GOV)) . "'";

$sql = "DELETE rp FROM role_permissions rp
          JOIN modules m ON m.id = rp.module_id
         WHERE TRIM(LEADING '/' FROM m.code) IN ($in)
           AND rp.role_id NOT IN (1, 15)
           AND m.owner_role_id <> rp.role_id
           AND rp.can_view = 0 AND rp.can_add = 0 AND rp.can_edit = 0 AND rp.can_delete = 0";
mysqli_query($conn, $sql) or die(mysqli_error($conn) . "\n");
fwrite(STDOUT, "مُحيت صفوفُ التصفير اليتيمة: " . mysqli_affected_rows($conn) . "\n");

/* الرؤية المؤقتة التي مُنحت للدور 4 على users.php أثناء المصالحة تُمحى معها —
   الغيابُ منعٌ عند الحارس والوثيقةُ محفوظةُ العدد والمولدُ يُخفي عرضًا. */
$sql = "DELETE rp FROM role_permissions rp
          JOIN modules m ON m.id = rp.module_id
         WHERE TRIM(LEADING '/' FROM m.code) = 'main/users.php'
           AND rp.role_id = 4
           AND rp.can_add = 0 AND rp.can_edit = 0 AND rp.can_delete = 0";
mysqli_query($conn, $sql) or die(mysqli_error($conn) . "\n");
fwrite(STDOUT, "صفُّ الدور 4 على users.php: " . mysqli_affected_rows($conn) . " (غيابٌ = منعٌ محروس)\n");
