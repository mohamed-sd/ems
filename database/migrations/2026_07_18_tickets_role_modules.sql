-- ═══════════════════════════════════════════════════════════════════════════
-- S12 — إدارة البلاغات (T1.2): الدور 24 + وحدتا شاشتَي المرجعية + الصلاحيات
--
-- دفتر القرارات (2026-07-18):
--   • D1: دورٌ واحد «مدير البلاغات» (24) — level 1 بلا أب، يرى كل تذاكر شركته،
--     ويفوّض مشرفين لاحقًا عبر آلية المساعدين القائمة (CRUD لكل وحدة).
--   • «منسق البلاغات» أُسقط كدورٍ ثابت — يُنشأ كمساعدٍ مفوَّض عند الحاجة.
--   • الوحدتان 130/131 (تحقق MAX حي 2026-07-18: آخر مستخدم = 129).
--   • role_scope='gloable' كإملاء العمود القائم حرفيًا (توافق تاريخي مقصود).
-- idempotent: حارس NOT EXISTS يمنع Duplicate عند إعادة التطبيق.
-- التزامن الإلزامي: includes/roles.php (الثابت + EMS_ROLE_NAMES) في نفس الدفعة،
-- وإلا سجّل حارسُ المطابقة role_constant_mismatch في كل جلسة.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① الدور 24 · مدير البلاغات ──────────────────────────────────────────────
INSERT INTO `roles` (`id`, `name`, `parent_role_id`, `level`, `role_scope`, `status`)
SELECT 24, 'مدير البلاغات', NULL, 1, 'gloable', '1'
WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `id` = 24);

-- ── ② الوحدتان 130/131 · شاشتا المرجعية (المرحلة 1) ─────────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 130, 'أنواع البلاغات والتوجيه', 'Tickets/ticket_types_config.php', 24, '1', 'fa fa-route', 1
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `id` = 130);

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 131, 'التصنيف الفنّي للبلاغات', 'Tickets/ticket_categories_config.php', 24, '1', 'fa fa-tags', 2
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `id` = 131);

-- ── ③ صلاحيات الدور 24 على وحدتَيه (CRUD كامل — D1) ─────────────────────────
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 24, 130, 1, 1, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `role_permissions` WHERE `role_id` = 24 AND `module_id` = 130);

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 24, 131, 1, 1, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `role_permissions` WHERE `role_id` = 24 AND `module_id` = 131);

-- ═══════════════════════════════════════════════════════════════════════════
-- ROLLBACK (نفّذ يدويًا فقط عند الطلب):
--   DELETE FROM role_permissions WHERE role_id = 24 AND module_id IN (130, 131);
--   DELETE FROM modules WHERE id IN (130, 131);
--   DELETE FROM roles WHERE id = 24;
--   + إعادة includes/roles.php (حذف الثابت والاسم 24)
-- ═══════════════════════════════════════════════════════════════════════════
