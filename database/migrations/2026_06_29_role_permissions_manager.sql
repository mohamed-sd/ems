-- Migration: دور «مدير الصلاحيات» + صفحاته + صلاحياته — 2026-06-29
-- دور عُلوي (مثل مدير المشاريع) يملك صفحتي إدارة المستخدمين والمعاونين بكامل الصلاحيات.
-- idempotent: لا يكرّر عند إعادة التنفيذ.
SET NAMES utf8mb4;

-- 1) الدور العُلوي
INSERT INTO `roles` (`name`, `parent_role_id`, `level`, `role_scope`, `status`)
SELECT 'مدير الصلاحيات', NULL, 1, 'gloable', '1'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `name` = 'مدير الصلاحيات');

SET @rid := (SELECT `id` FROM `roles` WHERE `name` = 'مدير الصلاحيات' LIMIT 1);

-- 2) صفحاته (موديولات تظهر في قائمته الجانبية)
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 'إدارة الصلاحيات', 'main/users.php', @rid, '1', 'fa fa-users-cog', 10
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code` = 'main/users.php' AND `owner_role_id` = @rid);

INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 'إدارة المعاونين', 'main/project_users.php', @rid, '1', 'fa fa-users-cog', 20
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code` = 'main/project_users.php' AND `owner_role_id` = @rid);

-- 3) كل الصلاحيات (عرض/إضافة/تعديل/حذف) على كل موديولات هذين المسارين.
-- نمنحها على كل صفوف الكود (لا موديولات الدور فقط) لأن الحارس المركزي
-- (enforce_current_page_view_permission → get_module_id_by_script_path) يحلّ الموديول
-- بأدنى id بصرف النظر عن owner_role_id؛ فلو مُنحت على موديول الدور فقط (الأعلى id) لرُفض الوصول.
-- القائمة الجانبية تظل تعرض موديولات owner_role_id=@rid فقط، فلا تكرار في القائمة.
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT @rid, m.`id`, 1, 1, 1, 1
FROM `modules` m
WHERE m.`code` IN ('main/users.php', 'main/project_users.php')
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` rp WHERE rp.`role_id` = @rid AND rp.`module_id` = m.`id`);
