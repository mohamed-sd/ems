-- D05 §12.3 — تسجيل شاشة تقارير الطلبات + صلاحيات عائلة المالية (إدراج عاطل)

INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
SELECT 'تقارير الطلبات المالية', 'FinRequests/requests_reports.php', 17, '1', 0, 'fa fa-chart-column', 189
WHERE NOT EXISTS (SELECT 1 FROM modules m2 WHERE m2.code = 'FinRequests/requests_reports.php');

INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT r.role_id, m.id, 1, 0, 0, 0
FROM modules m
JOIN (
    SELECT 17 AS role_id UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL
    SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22
) r
WHERE m.code = 'FinRequests/requests_reports.php'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.role_id AND rp.module_id = m.id
  );
