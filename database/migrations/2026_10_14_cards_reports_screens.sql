-- ═══════════════════════════════════════════════════════════════════════════
-- M-47 · M-48 · M-07 — البطاقتان الأمّان وتقريرُ الهامش (200–202) — 2026-08-01
-- المصدر: UX-08 §5 · CON-02 §7 · UX-06 §5 · ENT-03 §5 — تجميعُ Views
--         لمصادرَ قائمةٍ بلا جدولٍ جديد.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT * FROM (
    SELECT 200 i, 'بطاقة العقد الأم'   n, 'Contracts/contract_card.php'   c, 12 r, 0 l, 1 q, 'fa fa-file-contract' ic, 0 d UNION ALL
    SELECT 201,   'بطاقة الموظف',         'Employees/employee_card.php',     4,    0,   1,   'fa fa-id-card',          0   UNION ALL
    SELECT 202,   'هامش الواقعة والعقد',  'Reports/margin_report.php',       17,   0,   1,   'fa fa-percent',          0
) m
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `modules`) x WHERE x.`code` = m.c);

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT p.rid, p.mid, 1, 0, 0, 0
  FROM (
    SELECT 12 rid, 200 mid UNION ALL SELECT 17, 200 UNION ALL SELECT 19, 200 UNION ALL SELECT 1, 200 UNION ALL
    SELECT 4, 201 UNION ALL SELECT 15, 201 UNION ALL SELECT 1, 201 UNION ALL SELECT 5, 201 UNION ALL
    SELECT 17, 202 UNION ALL SELECT 19, 202 UNION ALL SELECT 12, 202 UNION ALL SELECT 22, 202
  ) p
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
                    WHERE rp.`role_id` = p.rid AND rp.`module_id` = p.mid);

-- توحيدُ اسم رئيسية الدور 25 (قاعدةُ nav الموحّد — كان باسم اللوحة في 2026_10_13)
UPDATE `nav_items` SET `label_ar` = 'الرئيسية'
 WHERE `role_id` = 25 AND `door` = 'HOME' AND `label_ar` <> 'الرئيسية';
