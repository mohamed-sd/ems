-- ═══════════════════════════════════════════════════════════════════════════
-- NAV-01 §5 · سطحُ البلاغات — «بلاغاتُ إدارتي» في g5 لكل دورٍ — 2026-08-02
-- «عنصرٌ إلزاميٌّ في المجموعة ⑤ لكل إدارةٍ بلا استثناء» — update0006 B-02
-- ═══════════════════════════════════════════════════════════════════════════

-- ① تسجيلُ الشاشة في سجل الشاشات (modules) إن لم تكن
SET @mod_id = (SELECT id FROM modules WHERE code = 'Tickets/dept' LIMIT 1);
INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
SELECT 'بلاغاتُ إدارتي', 'Tickets/dept', 24, 1, 0, 'fa fa-bell', 990
WHERE @mod_id IS NULL;

-- ② الرابطُ في g5 (المتابعةُ والاستثناءات) لكل دورٍ نشطٍ في التنقل —
--    ولا يُكرَّر لدورٍ يملكه (idempotent عبر UQ(role_id, route))
INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, counter_source, permission_code, active)
SELECT lg.owner_role_id,
       'APPR',
       lg.id,
       (SELECT id FROM modules WHERE code = 'Tickets/dept' LIMIT 1),
       'بلاغاتُ إدارتي',
       'Tickets/dept_tickets.php',
       'fa fa-bell',
       5,
       'dept_tickets_late',
       NULL,
       1
FROM link_groups lg
WHERE lg.group_code = 'g5' AND lg.is_active = 1
  AND NOT EXISTS (SELECT 1 FROM nav_items n
                  WHERE n.role_id = lg.owner_role_id
                    AND n.route = 'Tickets/dept_tickets.php');
