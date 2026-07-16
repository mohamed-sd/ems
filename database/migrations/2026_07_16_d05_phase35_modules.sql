-- D05 مراحل 3+5 — تسجيل شاشتي خريطة الأثر وتوجيه الإدارات + صلاحياتهما (إدراج عاطل)

INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
SELECT t.name, t.code, t.owner_role_id, '1', 0, t.icon, t.display_order
FROM (
    SELECT 'خريطة تفريع الأثر' AS name, 'FinRequests/effect_map.php' AS code, 17 AS owner_role_id, 'fa fa-diagram-project' AS icon, 186 AS display_order UNION ALL
    SELECT 'توجيه الطلبات المالية', 'FinRequests/routing_admin.php', 17, 'fa fa-route', 187
) t
WHERE NOT EXISTS (SELECT 1 FROM modules m2 WHERE m2.code = t.code);

INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT r.role_id, m.id, r.v, r.a, r.e, r.d
FROM modules m
JOIN (
    SELECT 'FinRequests/effect_map.php' AS code, 17 AS role_id, 1 AS v, 0 AS a, 0 AS e, 0 AS d UNION ALL
    SELECT 'FinRequests/effect_map.php', 18, 1, 0, 0, 0 UNION ALL
    SELECT 'FinRequests/effect_map.php', 19, 1, 0, 0, 0 UNION ALL
    SELECT 'FinRequests/effect_map.php', 20, 1, 0, 0, 0 UNION ALL
    SELECT 'FinRequests/effect_map.php', 21, 1, 0, 0, 0 UNION ALL
    SELECT 'FinRequests/effect_map.php', 22, 1, 0, 0, 0 UNION ALL
    SELECT 'FinRequests/routing_admin.php', 17, 1, 1, 1, 0
) r ON r.code = m.code
WHERE m.code IN ('FinRequests/effect_map.php', 'FinRequests/routing_admin.php')
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.role_id AND rp.module_id = m.id
  );
