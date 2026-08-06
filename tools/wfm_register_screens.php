<?php
/**
 * tools/wfm_register_screens.php — تسجيل شاشات مساحة عملي الجديدة في modules
 * ───────────────────────────────────────────────────────────────────────────
 * الحارس المركزي fail-closed (caf65ec): الشاشة غير المسجَّلة تُرفض — فكل
 * شاشة جديدة تُسجَّل ويُمنح ظهورُها. شاشات مساحة عملي **شخصية** (المحتوى
 * معزول بالمستخدم داخل المحرّك) فالرؤية لكل الأدوار — كعرف Portal/my_tasks.
 * idempotent: لا يكرر تسجيلًا ولا منحًا.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$SCREENS = array(
    array('طلباتي', 'Portal/my_requests.php'),
    array('التنبيهات', 'Portal/notifications.php'),
    array('لوحتي', 'Portal/my_kpi.php'),
    // الموجة ١ (2026-08-06): ورقة الإدارات الـ17 المعيَّرة — المحتوى يعزل نفسه
    // بوحدة دور الجلسة (خريطة الـ17) فالرؤية لكل الأدوار كعرف مساحة عملي.
    array('ورقة الإدارة', 'Portal/dept_board.php'),
);

foreach ($SCREENS as $s) {
    list($name, $code) = $s;
    $st = $conn->prepare("SELECT id FROM modules WHERE code = ? LIMIT 1");
    $st->bind_param('s', $code);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if ($row) {
        $mid = intval($row['id']);
        fwrite(STDOUT, "= {$code} مسجَّلة (#{$mid})\n");
    } else {
        $st = $conn->prepare("INSERT INTO modules (name, code, owner_role_id) VALUES (?, ?, NULL)");
        $st->bind_param('ss', $name, $code);
        $st->execute() or die($st->error . "\n");
        $mid = $st->insert_id;
        $st->close();
        fwrite(STDOUT, "+ سُجّلت {$code} (#{$mid})\n");
    }
    // منح الرؤية لكل الأدوار — مساحة شخصية (كعرف my_tasks #246)
    $r = mysqli_query($conn, "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
        SELECT r.id, {$mid}, 1, 0, 0, 0 FROM roles r
        WHERE NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.module_id = {$mid})");
    fwrite(STDOUT, "  منح رؤية: " . mysqli_affected_rows($conn) . " دورًا\n");
}
fwrite(STDOUT, "✔ اكتمل التسجيل\n");
