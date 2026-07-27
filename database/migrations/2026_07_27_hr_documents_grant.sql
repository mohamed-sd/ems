-- ═══════════════════════════════════════════════════════════════════════════
-- منحُ الموارد البشرية شاشةَ الوثائق — 2026-07-27
-- ───────────────────────────────────────────────────────────────────────────
-- بطاقاتُ لوحة الدور 4 تقود إلى `Equipments/equipment_documents.php`؛ فبلا
-- منحٍ يقود المؤشرُ إلى بابٍ مغلق (الحارسُ المركزي يطرده). والموارد البشرية
-- **مالكةُ وثائق الأفراد** (رخصٌ وهويات) فتُمنح الإضافةَ والتعديل لا العرضَ
-- وحده — فالتجديدُ عملُها.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 4, m.`id`, 1, 1, 1, 0 FROM `modules` m
WHERE m.`code` = 'Equipments/equipment_documents.php'
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` p WHERE p.`role_id` = 4 AND p.`module_id` = m.`id`);

-- ورابطٌ في سايدبار الموارد البشرية (باب REC — سجلات)
INSERT INTO `nav_items` (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `permission_code`, `active`)
SELECT 4, 'REC', NULL, m.`id`, 'وثائق المعدات والمشغّلين', 'Equipments/equipment_documents.php',
       'fa fa-file-shield', 62, 'Equipments/equipment_documents.php', 1
FROM `modules` m
WHERE m.`code` = 'Equipments/equipment_documents.php'
  AND NOT EXISTS (SELECT 1 FROM `nav_items` n
                   WHERE n.`role_id` = 4 AND n.`route` = 'Equipments/equipment_documents.php');
