-- ═══════════════════════════════════════════════════════════════════════════
-- H-08-① · تسجيلُ شاشة «سجل العقود الموحّد» — 2026-07-30
-- الوحدة 151 (بعد 150 «مواقع التنفيذ») — رقمٌ صريحٌ لا MAX+1.
-- الملكيةُ للقوى العاملة (4): بيتُ عقود الأشخاص (CON-01) — وبجوار كاتبها
-- القديم «عقود العاملين» في باب السجلات؛ عرضُ بقية الأدوار يتوسّع مع
-- الشرائح ②③④ حين تعرض الشاشةُ المكوّناتِ والحوافزَ والتحمّل.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 151, 'سجل العقود الموحّد', 'Workforce/contract_registry.php', 4, 0, 0, 'fa fa-file-signature', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Workforce/contract_registry.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 4, 151, 1, 1, 1, 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = 4 AND rp.`module_id` = 151);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT 4, 'REC', NULL, 151, 'سجل العقود الموحّد', 'Workforce/contract_registry.php',
       'fa fa-file-signature', 55, NULL, 'Workforce/contract_registry.php', 1
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = 4 AND n.`route` = 'Workforce/contract_registry.php');
