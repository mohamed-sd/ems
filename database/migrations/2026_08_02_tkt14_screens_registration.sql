-- ═══════════════════════════════════════════════════════════════════════════
-- update0004 · الموجة ⑭ · TKT-17 — تسجيل شاشتي المسارات وبرج المراقبة
-- للدور 24 (مدير البلاغات) — والقائمة في NAV-01 §9.10 (باب DAILY/REP).
-- ═══════════════════════════════════════════════════════════════════════════
INSERT INTO modules (name, code, icon, display_order)
SELECT t.name, t.code, t.icon, t.ord
FROM (SELECT 'لوحة مسارات البلاغات' name, 'Tickets/ticket_workstreams_board.php' code, 'fa fa-code-branch' icon, 320 ord UNION ALL
      SELECT 'برج المراقبة', 'Tickets/watchtower.php', 'fa fa-broadcast-tower', 321 UNION ALL
      SELECT 'بلاغ سياقي جديد', 'Tickets/ticket_contextual_open.php', 'fa fa-bullhorn', 322) t
WHERE NOT EXISTS (SELECT 1 FROM modules m WHERE m.code = t.code);

INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT 24, m.id, 1, 1, 1, 0
FROM modules m
WHERE m.code IN ('Tickets/ticket_workstreams_board.php', 'Tickets/watchtower.php', 'Tickets/ticket_contextual_open.php')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = 24 AND rp.module_id = m.id);

-- الفتح السياقي متاح لكل الأدوار الفاعلة (البلاغ يخص الشخص لا الإدارة — NAV-01 §3⑦)
INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
SELECT r.id, m.id, 1, 1, 0, 0
FROM roles r
JOIN modules m ON m.code = 'Tickets/ticket_contextual_open.php'
WHERE NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.module_id = m.id);

INSERT INTO nav_items (role_id, door, module_id, label_ar, route, icon, sort_order)
SELECT 24, t.door, m.id, t.label, t.route, t.icon, t.ord
FROM (SELECT 'DAILY' door, 'لوحة المسارات المتوازية' label, 'Tickets/ticket_workstreams_board.php' route, 'fa fa-code-branch' icon, 15 ord UNION ALL
      SELECT 'REP', 'برج المراقبة والمؤشرات', 'Tickets/watchtower.php', 'fa fa-broadcast-tower', 15) t
JOIN modules m ON m.code = t.route
WHERE NOT EXISTS (SELECT 1 FROM nav_items n WHERE n.role_id = 24 AND n.route = t.route);
