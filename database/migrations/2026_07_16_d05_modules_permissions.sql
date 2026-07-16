-- D05 — تسجيل شاشات بوابة الطلب المالي في modules + صلاحيات الأدوار
-- الملكية (السايدبار): 13 يرى شاشات الطالب والصندوق (و14 يرثها لأن أباه 13)،
-- 18 يملك مكتب المحاسب، 17 يملك بوابة المالية (و18/19/20 بالصلاحيات).

-- (إدراجٌ عاطل: يتخطى الموجود — الملف قابلٌ لإعادة التشغيل بعد فشل FK الأول)
INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
SELECT t.name, t.code, t.owner_role_id, '1', 0, t.icon, t.display_order
FROM (
    SELECT 'طلب مالي جديد' AS name, 'FinRequests/request_form.php' AS code, 13 AS owner_role_id, 'fa fa-file-circle-plus' AS icon, 181 AS display_order UNION ALL
    SELECT 'طلباتي المالية', 'FinRequests/my_requests.php', 13, 'fa fa-list-check', 182 UNION ALL
    SELECT 'صندوق طلبات الإدارة', 'FinRequests/dept_inbox.php', 13, 'fa fa-inbox', 183 UNION ALL
    SELECT 'مكتب محاسب الإدارة', 'FinRequests/accountant_desk.php', 18, 'fa fa-calculator', 184 UNION ALL
    SELECT 'بوابة الطلبات المالية', 'FinRequests/finance_gateway.php', 17, 'fa fa-building-columns', 185
) t
WHERE NOT EXISTS (SELECT 1 FROM modules m2 WHERE m2.code = t.code);

-- صلاحيات الوصول (role_permissions): view/add/edit/delete
-- السوبر (-1) ليس صفًّا في roles ولا يمرّ عبر role_permissions — تتجاوزه الشاشات صراحةً.
INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT r.role_id, m.id, r.v, r.a, r.e, r.d
FROM modules m
JOIN (
    SELECT 'FinRequests/request_form.php' AS code, 13 AS role_id, 1 AS v, 1 AS a, 1 AS e, 0 AS d UNION ALL
    SELECT 'FinRequests/request_form.php', 14, 1, 1, 1, 0 UNION ALL
    SELECT 'FinRequests/my_requests.php', 13, 1, 0, 0, 0 UNION ALL
    SELECT 'FinRequests/my_requests.php', 14, 1, 0, 0, 0 UNION ALL
    SELECT 'FinRequests/dept_inbox.php', 13, 1, 0, 1, 0 UNION ALL
    SELECT 'FinRequests/dept_inbox.php', 14, 1, 0, 1, 0 UNION ALL
    SELECT 'FinRequests/accountant_desk.php', 18, 1, 0, 1, 0 UNION ALL
    SELECT 'FinRequests/accountant_desk.php', 17, 1, 0, 1, 0 UNION ALL
    SELECT 'FinRequests/accountant_desk.php', 19, 1, 0, 0, 0 UNION ALL
    SELECT 'FinRequests/finance_gateway.php', 17, 1, 0, 0, 0 UNION ALL
    SELECT 'FinRequests/finance_gateway.php', 18, 1, 0, 0, 0 UNION ALL
    SELECT 'FinRequests/finance_gateway.php', 19, 1, 0, 0, 0 UNION ALL
    SELECT 'FinRequests/finance_gateway.php', 20, 1, 0, 0, 0 UNION ALL
    SELECT 'FinRequests/finance_gateway.php', 21, 1, 0, 0, 0 UNION ALL
    SELECT 'FinRequests/finance_gateway.php', 22, 1, 0, 0, 0
) r ON r.code = m.code
WHERE m.code LIKE 'FinRequests/%'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.role_id AND rp.module_id = m.id
  );
