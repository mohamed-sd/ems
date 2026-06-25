-- إضافة شاشة «شجرة العميل» إلى التنقّل + استنساخ صلاحيات map_page (الدور 6: مدير حركة وتشغيل)
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 'شجرة العميل', 'movement/client_tree.php', 6, '1', 'fa fa-sitemap', 16
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code`='movement/client_tree.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT rp.`role_id`, nm.`id`, rp.`can_view`, 0, 0, 0
FROM `role_permissions` rp
JOIN `modules` src ON src.`code`='movement/map_page.php'
JOIN `modules` nm  ON nm.`code`='movement/client_tree.php'
WHERE rp.`module_id`=src.`id`
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` x WHERE x.`role_id`=rp.`role_id` AND x.`module_id`=nm.`id`);
