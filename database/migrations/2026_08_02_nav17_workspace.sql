-- ═══════════════════════════════════════════════════════════════════════════
-- update0004 · الموجة ⑰ · NAV-07 — «مساحة عملي» فوق الإدارات كلها
-- موديول واحد + رابط HOME (g1) لكل الأدوار — تُحل به عشر روابط مكررة في
-- أربع عشرة قائمة (NAV-01 §3) — والروابط المكررة تُعالج بيانات في ⑱.
-- ═══════════════════════════════════════════════════════════════════════════
INSERT INTO modules (name, code, icon, display_order)
SELECT 'مساحة عملي', 'main/my_workspace.php', 'fa fa-user-circle', 5
WHERE NOT EXISTS (SELECT 1 FROM modules m WHERE m.code = 'main/my_workspace.php');

INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT r.id, m.id, 1, 0, 0, 0
FROM roles r
JOIN modules m ON m.code = 'main/my_workspace.php'
WHERE NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.module_id = m.id);

INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order)
SELECT r.id, 'HOME', lg.id, m.id, 'مساحة عملي', 'main/my_workspace.php', 'fa fa-user-circle', 1
FROM roles r
JOIN modules m ON m.code = 'main/my_workspace.php'
LEFT JOIN link_groups lg ON lg.owner_role_id = r.id AND lg.group_code = 'g1'
WHERE NOT EXISTS (SELECT 1 FROM nav_items n WHERE n.role_id = r.id AND n.route = 'main/my_workspace.php');
