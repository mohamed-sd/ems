<?php
/**
 * S-13 · ربطُ شاشات الموجة ⑤ في قوائم مالكيها + إحياءُ صفَّي الموازنة — update0007
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

function seed_link($conn, $role, $route, $label, $icon, $namePat = '')
{
    $g = null;
    if ($namePat !== '') {
        $q = mysqli_query($conn, "SELECT id FROM link_groups WHERE owner_role_id=$role AND is_active=1
                                  AND name LIKE '%" . mysqli_real_escape_string($conn, $namePat) . "%'
                                  ORDER BY display_order LIMIT 1");
        if ($q && mysqli_num_rows($q)) $g = mysqli_fetch_assoc($q)['id'];
    }
    if (!$g) {
        $q = mysqli_query($conn, "SELECT id FROM link_groups WHERE owner_role_id=$role AND is_active=1
                                  ORDER BY display_order LIMIT 1 OFFSET 1");
        if ($q && mysqli_num_rows($q)) $g = mysqli_fetch_assoc($q)['id']; else return false;
    }
    $el = mysqli_real_escape_string($conn, $label);
    $er = mysqli_real_escape_string($conn, $route);
    mysqli_query($conn, "INSERT INTO nav_items (role_id, door, group_id, label_ar, route, icon, sort_order, active)
                         VALUES ($role, 'DAILY', $g, '$el', '$er', '$icon', 9, 1)
                         ON DUPLICATE KEY UPDATE active=1, group_id=$g, label_ar='$el'");
    return true;
}

seed_link($conn, 23, 'Transport/transfer_in_transit.php', 'الحركة في الطريق', 'fa fa-truck-moving', 'الحركة');
seed_link($conn, 23, 'Transport/transfer_arrival.php', 'الوصول والتسليم', 'fa fa-flag-checkered', 'الوصول');
seed_link($conn, 23, 'Transport/transfer_close_cost.php', 'إقفال الأمر وتحميل التكلفة', 'fa fa-lock', 'الإغلاق');
seed_link($conn, 25, 'Procurement/wh_transfer.php', 'التحويل بين المخازن', 'fa fa-random');
seed_link($conn, 25, 'Procurement/wh_returns.php', 'المرتجعات', 'fa fa-undo');
seed_link($conn, 25, 'Procurement/wh_count.php', 'الجرد والتسويات', 'fa fa-clipboard-list', 'الجرد');
seed_link($conn, 16, 'Procurement/rfq_compare_award.php', 'مقارنة العروض والترسية', 'fa fa-balance-scale');
seed_link($conn, 13, 'Maintenance/return_to_service.php', 'العودة للخدمة', 'fa fa-check-circle', 'الإغلاق');

mysqli_query($conn, "UPDATE nav_items SET active=1 WHERE route IN ('Finance/budget_dept.php','Finance/budget_master.php')");
echo "أُحيي من صفوف الموازنة: " . mysqli_affected_rows($conn) . "\n";
$r = mysqli_query($conn, "SELECT COUNT(*) c FROM nav_items WHERE active=1");
echo "الحية: " . mysqli_fetch_assoc($r)['c'] . "\n";
