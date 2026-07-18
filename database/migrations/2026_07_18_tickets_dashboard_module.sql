-- ═══════════════════════════════════════════════════════════════════════════
-- S12 — لوحة برج المراقبة (المرحلة 8) — T8.1
-- وحدة 137: شاشةٌ مستقلّةٌ للقراءة فقط (KPI · الاختناقات · تقارير الترتيب)،
-- تُبنى كليًّا من بياناتٍ متولّدةٍ سلفًا (طوابع الزمن · سجلّ التحويلات ·
-- مواعيد الاستحقاق) — صفر جداولٍ جديدة وصفر كتابة.
-- idempotent: حارس NOT EXISTS.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 137, 'لوحة برج المراقبة', 'Tickets/ticket_dashboard.php', 24, '1', 'fa fa-gauge-high', 7
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `id` = 137);

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 24, 137, 1, 0, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `role_permissions` WHERE `role_id` = 24 AND `module_id` = 137);

-- ═══════════════════════════════════════════════════════════════════════════
-- ROLLBACK (يدويًا):
--   DELETE FROM role_permissions WHERE role_id = 24 AND module_id = 137;
--   DELETE FROM modules WHERE id = 137;
-- ═══════════════════════════════════════════════════════════════════════════
