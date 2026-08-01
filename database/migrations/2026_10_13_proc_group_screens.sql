-- ═══════════════════════════════════════════════════════════════════════════
-- M-49 · M-50 — بطاقةُ مورد المشتريات ولوحةُ أمين المستودع دورًا — 2026-08-01
-- المصدر: UX-09 §5 (البطاقةُ بتبويباتها السبعة) · §6 (لوحةُ أمين المستودع
--         دورًا مستقلًّا) — والدورُ 25 جديدٌ بثابته في includes/roles.php.
-- ═══════════════════════════════════════════════════════════════════════════

-- ① الدورُ 25 — أمينُ المستودع (حارسُ ADR-07 حُدّث بثابته واسمه معًا)
INSERT INTO `roles` (`id`, `name`)
SELECT 25, 'أمين المستودع'
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `roles`) r WHERE r.`id` = 25);

-- ② الشاشتان 198 · 199
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT * FROM (
    SELECT 198 i, 'بطاقة مورد المشتريات' n, 'Procurement/supplier_card_proc.php' c, 16 r, 0 l, 1 q, 'fa fa-id-card-clip' ic, 0 d UNION ALL
    SELECT 199,   'لوحة أمين المستودع',     'Procurement/warehouse_board.php',      25,   0,   1,   'fa fa-warehouse',      0
) m
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `modules`) x WHERE x.`code` = m.c);

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT p.rid, p.mid, 1, 0, 0, 0
  FROM (SELECT 16 rid, 198 mid UNION ALL SELECT 16, 199 UNION ALL SELECT 25, 199 UNION ALL
        SELECT 25, 198) p
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
                    WHERE rp.`role_id` = p.rid AND rp.`module_id` = p.mid);

-- ③ صلاحياتُ أمين المستودع على شاشات عمله القائمة (عرضًا وعملًا لا إدارةً)
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 25, m.id, 1, 1, 1, 0
  FROM `modules` m
 WHERE m.`code` IN ('Procurement/stock_proc.php', 'Procurement/receipt_custody_proc.php',
                    'Procurement/issue_proc.php', 'Procurement/reordering_proc.php',
                    'Procurement/items_proc.php')
   AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
                    WHERE rp.`role_id` = 25 AND rp.`module_id` = m.id);

-- ④ سايدبارُ الدور 25 — لوحتُه وشاشاتُ يومه
INSERT INTO `nav_items` (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`,
                         `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT 25, p.door, NULL, m.id,
       CASE WHEN p.door = 'HOME' THEN 'الرئيسية' ELSE m.`name` END, -- توحيدُ اسم الرئيسية
       m.`code`, m.`icon`, p.so, NULL, m.`code`, 1
  FROM (
    SELECT 'HOME' door, 'Procurement/warehouse_board.php' code, 1 so UNION ALL
    SELECT 'DAILY', 'Procurement/issue_proc.php', 2 UNION ALL
    SELECT 'DAILY', 'Procurement/receipt_custody_proc.php', 3 UNION ALL
    SELECT 'REC',   'Procurement/stock_proc.php', 4 UNION ALL
    SELECT 'REC',   'Procurement/items_proc.php', 5 UNION ALL
    SELECT 'SET',   'Procurement/reordering_proc.php', 6
  ) p
  JOIN (SELECT * FROM `modules`) m ON m.`code` = p.code
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `nav_items`) n
                    WHERE n.`role_id` = 25 AND n.`route` = p.code);
