-- ═══════════════════════════════════════════════════════════════════════════
-- تسجيل اللوحة العامة للأدوار + منحها للأدوار الثمانية — 2026-07-26
-- ───────────────────────────────────────────────────────────────────────────
-- شاشةٌ واحدة (main/role_board.php) تصيّر لوحةَ أيِّ دورٍ من إعداده — بديلُ
-- ثمانية ملفات. التسجيل بمنحٍ صريحةٍ واجبٌ (الحارس يفترض السماح لغير
-- المسجَّل)؛ عرضٌ فقط لكل دور. is_link='0' — الوصولُ عبر «الرئيسية» المحوِّلة.
-- الأدوار: 24 البلاغات · 12 المبيعات · 2 الموردون · 3 الأسطول · 4 HR ·
-- 5 الموقع · 6 الحركة · 15 الصلاحيات (+ فروعُها ترث عبر اللوحة نفسها:
-- 7·8·10·11 تُمنح عرضًا لأنها تفتح لوحةَ أبيها من هذه الشاشة).
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 'لوحة الدور', 'main/role_board.php', 15, '0', 'fa fa-gauge-high', 0
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code` = 'main/role_board.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.`id`, m.`id`, 1, 0, 0, 0
FROM `modules` m
JOIN `roles` r ON r.`id` IN (2, 3, 4, 5, 6, 7, 8, 10, 11, 12, 15, 24)
WHERE m.`code` = 'main/role_board.php'
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` p WHERE p.`role_id` = r.`id` AND p.`module_id` = m.`id`);
