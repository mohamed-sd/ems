-- ═══════════════════════════════════════════════════════════════════════════
-- M-27 · M-28 · M-29 · M-26 — شاشاتُ التشغيل والجاهزية (193–196) — 2026-08-01
-- المصدر: SPEC-03 بطاقات 1 · 5 · 6 · UX-10 §5.2 — قراءةٌ وقفزٌ بلا أثرٍ مالي.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT * FROM (
    SELECT 193 i, 'غرفة عمليات التشغيل' n, 'Operations/operations_room.php'   c, 1 r, 0 l, 1 q, 'fa fa-tower-control' ic, 0 d UNION ALL
    SELECT 194,   'مساحة التوزيع',         'Operations/distribution_space.php',  6,   0,   1,   'fa fa-table-cells',      0   UNION ALL
    SELECT 195,   'سجل الوحدات اليومية',   'Reports/daily_units_report.php',     1,   0,   1,   'fa fa-table-list',       0   UNION ALL
    SELECT 196,   'لوحة الجاهزية',         'Fleet/readiness_board.php',          3,   0,   1,   'fa fa-heart-pulse',      0
) m
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `modules`) x WHERE x.`code` = m.c);

-- التشغيل (1) · الموقع (5) · الحركة (6) · الأسطول (3) — كلٌّ بشاشات عمله
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT p.rid, p.mid, 1, 0, 0, 0
  FROM (
    SELECT 1 rid, 193 mid UNION ALL SELECT 5, 193 UNION ALL SELECT 6, 193 UNION ALL
    SELECT 1, 194 UNION ALL SELECT 5, 194 UNION ALL SELECT 6, 194 UNION ALL
    SELECT 1, 195 UNION ALL SELECT 5, 195 UNION ALL SELECT 17, 195 UNION ALL
    SELECT 3, 196 UNION ALL SELECT 1, 196
  ) p
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
                    WHERE rp.`role_id` = p.rid AND rp.`module_id` = p.mid);

INSERT INTO `nav_items` (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`,
                         `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT p.rid, 'DAILY', NULL, p.mid, m.`name`, m.`code`, m.`icon`, p.so, NULL, m.`code`, 1
  FROM (
    SELECT 1 rid, 193 mid, 5 so UNION ALL SELECT 5, 193, 5 UNION ALL SELECT 6, 193, 5 UNION ALL
    SELECT 6, 194, 6 UNION ALL SELECT 1, 194, 6 UNION ALL
    SELECT 1, 195, 7 UNION ALL SELECT 17, 195, 90 UNION ALL
    SELECT 3, 196, 5 UNION ALL SELECT 1, 196, 8
  ) p
  JOIN (SELECT * FROM `modules`) m ON m.`id` = p.mid
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `nav_items`) n
                    WHERE n.`role_id` = p.rid AND n.`route` = m.`code`);
