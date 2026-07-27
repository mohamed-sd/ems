-- ═══════════════════════════════════════════════════════════════════════════
-- تسجيلُ شاشة «وثائق المعدات والمشغّلين» — 2026-07-27
-- ───────────────────────────────────────────────────────────────────────────
-- التسجيلُ واجبٌ أمنيًّا (الحارس المركزي يفترض السماح لشاشةٍ بلا صفّ module).
-- الملكية: الأسطول (3) كاملًا · إدارة التشغيل (1) عرضًا — والفرعيُّ يرث.
-- السايدبار: باب REC (سجلات) للدورين.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 'وثائق المعدات والمشغّلين', 'Equipments/equipment_documents.php', 3, '0', 'fa fa-file-shield', 0
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code` = 'Equipments/equipment_documents.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 3, m.`id`, 1, 1, 1, 0 FROM `modules` m
WHERE m.`code` = 'Equipments/equipment_documents.php'
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` p WHERE p.`role_id` = 3 AND p.`module_id` = m.`id`);

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 1, m.`id`, 1, 0, 0, 0 FROM `modules` m
WHERE m.`code` = 'Equipments/equipment_documents.php'
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` p WHERE p.`role_id` = 1 AND p.`module_id` = m.`id`);

-- السايدبار الموحّد — باب REC للدورين (sort بعد سجلات الأسطول القائمة)
INSERT INTO `nav_items` (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `active`)
SELECT r.rid, 'REC', NULL, m.`id`, 'وثائق المعدات والمشغّلين', 'Equipments/equipment_documents.php', 'fa fa-file-shield', 62, 1
FROM `modules` m
JOIN (SELECT 3 AS rid UNION ALL SELECT 1) r
WHERE m.`code` = 'Equipments/equipment_documents.php'
  AND NOT EXISTS (SELECT 1 FROM `nav_items` n
                   WHERE n.`role_id` = r.rid AND n.`route` = 'Equipments/equipment_documents.php');
