<?php
/**
 * includes/route_redirect.php — تحويل المسارات بعدّاد (NAV-08 · SCR-01 §5)
 * ───────────────────────────────────────────────────────────────────────────
 * يوحّد نمط رصّاصات التحويل القائم (نموذج Maintenance/breakdowns.php):
 * الملف الميت يستدعي هذه الدالة فتَعُدّ النقرة في nav_redirects وتحوّل —
 * «والحذف النهائي قرار مالك بعد أن يثبت العداد صفره مدة كافية».
 *
 * الاستعمال في رصاصة التحويل:
 *     include '../config.php';
 *     require_once '../includes/route_redirect.php';
 *     ems_route_redirect($conn, 'OldDir/old_screen.php', '../New/new_screen.php');
 */
if (!function_exists('ems_route_redirect')) {
    function ems_route_redirect(\mysqli $conn, $oldRoute, $newLocation)
    {
        $stmt = $conn->prepare(
            "UPDATE nav_redirects SET hits = hits + 1, last_hit_at = NOW()
              WHERE old_route = ? AND active = 1");
        $stmt->bind_param('s', $oldRoute);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            // مسار غير مسجَّل — يسجَّل صفًّا فلا يضيع أثر النقرة (جدولي لا كودي)
            $stmt->close();
            $stmt = $conn->prepare(
                "INSERT IGNORE INTO nav_redirects (old_route, new_route, active, hits, last_hit_at)
                 VALUES (?, ?, 1, 1, NOW())");
            $new = ltrim(str_replace('..', '', (string) $newLocation), '/');
            $stmt->bind_param('ss', $oldRoute, $new);
            $stmt->execute();
        }
        $stmt->close();
        header('Location: ' . $newLocation);
        exit();
    }
}
