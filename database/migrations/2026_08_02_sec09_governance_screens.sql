-- ═══════════════════════════════════════════════════════════════════════════
-- update0004 · الموجة ⑨ · SEC-26 — تسجيل شاشتي حوكمة الصلاحيات
-- (مركز الحوكمة بمجموعاته الثماني + معالج الإحدى عشرة خطوة)
-- الدور 15 (الحوكمة) كاملًا · والباب GOV (NAV-01 §9.12).
-- ═══════════════════════════════════════════════════════════════════════════
INSERT INTO modules (name, code, icon, display_order)
SELECT t.name, t.code, t.icon, t.ord
FROM (SELECT 'مركز حوكمة الصلاحيات' name, 'admin/sec_governance.php' code, 'fa fa-shield-alt' icon, 310 ord UNION ALL
      SELECT 'معالج إعداد الموظف', 'admin/sec_employee_wizard.php', 'fa fa-user-plus', 311) t
WHERE NOT EXISTS (SELECT 1 FROM modules m WHERE m.code = t.code);

INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT 15, m.id, 1, 1, 1, 0
FROM modules m
WHERE m.code IN ('admin/sec_governance.php', 'admin/sec_employee_wizard.php')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = 15 AND rp.module_id = m.id);

INSERT INTO nav_items (role_id, door, module_id, label_ar, route, icon, sort_order)
SELECT 15, 'GOV', m.id, t.label, t.route, t.icon, t.ord
FROM (SELECT 'مركز حوكمة الصلاحيات' label, 'admin/sec_governance.php' route, 'fa fa-shield-alt' icon, 10 ord UNION ALL
      SELECT 'معالج إعداد الموظف', 'admin/sec_employee_wizard.php', 'fa fa-user-plus', 11) t
JOIN modules m ON m.code = t.route
WHERE NOT EXISTS (SELECT 1 FROM nav_items n WHERE n.role_id = 15 AND n.route = t.route);
