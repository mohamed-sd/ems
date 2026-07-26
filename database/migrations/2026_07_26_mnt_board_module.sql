-- ═══════════════════════════════════════════════════════════════════════════
-- تسجيل لوحة إدارة الصيانة — 2026-07-26
-- ───────────────────────────────────────────────────────────────────────────
-- الشاشة الثانية على قالب لوحة الدور (بعد المدير المالي). التسجيل واجبٌ لأن
-- الحارس المركزي «يفترض السماح للجميع» لشاشةٍ بلا صفِّ moduleٍ — والتسجيل
-- بمنحٍ صريحةٍ يقصرها على دورَي الصيانة: 13 كاملًا و14 عرضًا (نمط الوحدة).
-- لا صفَّ في nav_items: الوصولُ عبر «الرئيسية» المحوِّلة (نص UX-01 §4) —
-- لا رابطَ سايدبارٍ إضافيًّا للوحةٍ هي الرئيسيةُ نفسُها.
-- is_link='0' فلا تظهر في مصادر القوائم القديمة أيضًا.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 'لوحة إدارة الصيانة', 'Maintenance/dashboard_mnt.php', 13, '0', 'fa fa-gauge-high', 0
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code` = 'Maintenance/dashboard_mnt.php');

-- منح الدورين — عطالة بمنع الازدواج
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 13, m.`id`, 1, 1, 1, 0 FROM `modules` m
WHERE m.`code` = 'Maintenance/dashboard_mnt.php'
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` p WHERE p.`role_id` = 13 AND p.`module_id` = m.`id`);

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 14, m.`id`, 1, 0, 0, 0 FROM `modules` m
WHERE m.`code` = 'Maintenance/dashboard_mnt.php'
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` p WHERE p.`role_id` = 14 AND p.`module_id` = m.`id`);
