<?php
/**
 * ملف مساعد لجلب الروابط الديناميكية من جدول modules
 * Dynamic Navigation Links Helper
 * 
 * @version 1.0
 * @date 2026-03-04
 */

/**
 * التحقق من وجود عمود icon في جدول modules.
 *
 * @param object $conn mysqli connection object
 * @return bool
 */
function dynamicNavHasIconColumn($conn) {
    static $has_icon_column = null;

    if ($has_icon_column !== null) {
        return $has_icon_column;
    }

    $result = mysqli_query($conn, "SHOW COLUMNS FROM modules LIKE 'icon'");
    $has_icon_column = $result && mysqli_num_rows($result) > 0;

    return $has_icon_column;
}

/**
 * جلب روابط الصفحات بناءً على دور المستخدم الحالي (الأدوار النشطة فقط)
 * يتم عرض الصفحات التابعة للدور مباشرة أو الصفحات التابعة لأدوار فرعية
 * Get modules/pages based on current user role (active roles only)
 * Shows pages assigned to role directly OR pages assigned to child roles
 * 
 * @param object $conn mysqli connection object
 * @param int $roleId user's role ID
 * @return array array of modules with id, name, code, owner_role_id, icon
 */
function getDynamicNavLinks($conn, $roleId) {
    $links = array();
    $icon_select = dynamicNavHasIconColumn($conn)
        ? "COALESCE(NULLIF(TRIM(m.icon), ''), 'fa fa-link') AS icon"
        : "'fa fa-link' AS icon";
    
    // التحقق من أن roleId صحيح
    $roleId = intval($roleId);
    
    // استعلام لجلب جميع الروابط للأدوار النشطة فقط
    // يعرض الصفحات التابعة للدور الحالي وأيضاً الصفحات التابعة للدور الأب إن وجد
    // Fetch pages assigned to current role AND pages assigned to parent role (if exists)
    $query = "SELECT m.id, m.name, m.code, m.owner_role_id, $icon_select
              FROM modules m
              INNER JOIN roles mr ON m.owner_role_id = mr.id
              WHERE m.owner_role_id IN (
                  SELECT id FROM roles WHERE id = $roleId
                  UNION
                  SELECT parent_role_id FROM roles WHERE id = $roleId AND parent_role_id IS NOT NULL
              )
              AND (mr.status = '1' OR mr.status = 1)
              AND is_link = '1'
              ORDER BY m.id ASC";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $links[] = $row;
        }
    }
    
    return $links;
}

/**
 * طباعة روابط التنقل الديناميكية كـ HTML
 * Print dynamic navigation links as HTML
 * 
 * @param array $links array of module links
 * @param string $basePrefix prefix for links (e.g., '../' for subdirectories)
 * @return void
 */
function printDynamicNavLinks($links, $basePrefix = '../') {
    if (empty($links)) {
        return;
    }
    
    foreach ($links as $link) {
        // التحقق من وجود الحقول المطلوبة
        if (!isset($link['code']) || !isset($link['name'])) {
            continue;
        }
        
        $code = htmlspecialchars($link['code'], ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($link['name'], ENT_QUOTES, 'UTF-8');
        $icon = !empty($link['icon']) ? $link['icon'] : 'fa fa-link';
        $icon = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
        
        // تحديد البادئة (prefix) بناءً على موقع الملف الحالي
        $href = $basePrefix . $code;
        
        echo '<li><a href="' . $href . '"><i class="' . $icon . '"></i> <span>' . $name . '</span></a></li>' . "\n";
    }
}

/**
 * جلب وطباعة روابط التنقل الديناميكية بشكل مدمج
 * Get and print all navigation links for current user
 * 
 * @param object $conn mysqli connection object
 * @param int $roleId user's role ID
 * @param string $basePrefix prefix for links
 * @return void
 */
function renderDynamicNavigation($conn, $roleId, $basePrefix = '../') {
    $links = getDynamicNavLinks($conn, $roleId);
    printDynamicNavLinks($links, $basePrefix);
}

?>
