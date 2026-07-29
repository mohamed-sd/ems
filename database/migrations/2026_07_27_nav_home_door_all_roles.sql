-- ═══════════════════════════════════════════════════════════════════════════
-- باب «① الرئيسية» لكل دور في المصدر الموحّد — 2026-07-27
-- ───────────────────────────────────────────────────────────────────────────
-- الدستور §6: «① الرئيسية — أول ما يُفتح: لوحة الدور (§7)». وقد صارت لكل
-- إدارةٍ لوحتُها فعلًا، فينتقل الرابطُ من ثابتٍ مكتوبٍ في insidebar.php إلى
-- صفٍّ في `nav_items` — تحقيقًا لقاعدة «مصدرٌ واحدٌ محكوم» (§6 · UX-01 §10.2).
--
-- قرارا المالك (2026-07-27):
--   ① التسمية موحَّدةٌ «الرئيسية» لكل الأدوار — والأسماءُ المعتمدة في
--      UX-01 §9 («لوحة المدير المالي» 53 · «لوحة المشتريات» 79 · «لوحة
--      الرحلات» 127) تبقى في `modules` وفي عنوان الصفحة نفسها فلا تضيع.
--   ② الدورُ الفرعي يحمل صفًّا صريحًا بلوحة أبيه — لا وراثةً وقتَ التشغيل
--      وحدها؛ فالمصدرُ يجب أن يكون مكتفيًا بذاته.
--
-- المسار = مخرَجُ roleBoardRoute() حرفيًّا (تحقُّقٌ مقيسٌ 2026-07-27):
--   roleBoardGenericConfig تحوي تسعةَ أدوارٍ فقط (1·2·3·4·5·6·12·15·24)،
--   والفرعيةُ الأربعة (7→1 · 8→2 · 10→3 · 11→3) ترث فتمرّ من الملف نفسه؛
--   أما 13·14·16·17·18–22·23 فتخرج منه بتحويلٍ إلى dashboard ثم إلى لوحتها.
--   فتصويبُ الكلِّ إلى main/role_board.php كان يعني تحويلًا مزدوجًا لعشرة
--   أدوارٍ وتعطُّلَ تمييز الرابط النشط (insidebar.php يقارن pathname).
--
-- الصلاحيات: لا تُمسّ — منحُ العرض قائمةٌ سلفًا على الوحدات الخمس
--   (67→16 · 104→17..22 · 113→23 · 138→13,14 · 139→1..8,10,11,12,15,24).
--
-- idempotent: ON DUPLICATE KEY على uq_nav_role_route (role_id, route).
-- الرجوع: احذف صفوف door='HOME' وأعد سطرَ الرئيسية الثابت في insidebar.php.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① توحيدُ الثمانية القائمة على الاسم والأيقونة الموحَّدَين ──────────────
UPDATE `nav_items`
   SET `label_ar` = 'الرئيسية',
       `icon`     = 'fa fa-house'
 WHERE `door` = 'HOME';

-- ── ② بذرُ الخمسة عشر الباقية — كلٌّ بلوحته من roleBoardRoute() ───────────
INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
VALUES
    -- اللوحة العامة الواحدة (تُصيَّر من إعداد الدور) — تسعةٌ أصيلة
    ( 1, 'HOME', NULL, 139, 'الرئيسية', 'main/role_board.php', 'fa fa-house', 10, NULL, 'main/role_board.php', 1),
    ( 2, 'HOME', NULL, 139, 'الرئيسية', 'main/role_board.php', 'fa fa-house', 10, NULL, 'main/role_board.php', 1),
    ( 3, 'HOME', NULL, 139, 'الرئيسية', 'main/role_board.php', 'fa fa-house', 10, NULL, 'main/role_board.php', 1),
    ( 4, 'HOME', NULL, 139, 'الرئيسية', 'main/role_board.php', 'fa fa-house', 10, NULL, 'main/role_board.php', 1),
    ( 5, 'HOME', NULL, 139, 'الرئيسية', 'main/role_board.php', 'fa fa-house', 10, NULL, 'main/role_board.php', 1),
    ( 6, 'HOME', NULL, 139, 'الرئيسية', 'main/role_board.php', 'fa fa-house', 10, NULL, 'main/role_board.php', 1),
    (12, 'HOME', NULL, 139, 'الرئيسية', 'main/role_board.php', 'fa fa-house', 10, NULL, 'main/role_board.php', 1),
    (15, 'HOME', NULL, 139, 'الرئيسية', 'main/role_board.php', 'fa fa-house', 10, NULL, 'main/role_board.php', 1),
    (24, 'HOME', NULL, 139, 'الرئيسية', 'main/role_board.php', 'fa fa-house', 10, NULL, 'main/role_board.php', 1),
    -- الفرعيةُ الأربعة ترث اللوحة العامة نفسها (7→1 · 8→2 · 10→3 · 11→3)
    ( 7, 'HOME', NULL, 139, 'الرئيسية', 'main/role_board.php', 'fa fa-house', 10, NULL, 'main/role_board.php', 1),
    ( 8, 'HOME', NULL, 139, 'الرئيسية', 'main/role_board.php', 'fa fa-house', 10, NULL, 'main/role_board.php', 1),
    (10, 'HOME', NULL, 139, 'الرئيسية', 'main/role_board.php', 'fa fa-house', 10, NULL, 'main/role_board.php', 1),
    (11, 'HOME', NULL, 139, 'الرئيسية', 'main/role_board.php', 'fa fa-house', 10, NULL, 'main/role_board.php', 1),
    -- الصيانة ولوحتُها المخصصة — والفرعيُّ (14) يرث أباه (13)
    (13, 'HOME', NULL, 138, 'الرئيسية', 'Maintenance/dashboard_mnt.php', 'fa fa-house', 10, NULL, 'Maintenance/dashboard_mnt.php', 1),
    (14, 'HOME', NULL, 138, 'الرئيسية', 'Maintenance/dashboard_mnt.php', 'fa fa-house', 10, NULL, 'Maintenance/dashboard_mnt.php', 1)
ON DUPLICATE KEY UPDATE
    `door`            = VALUES(`door`),
    `module_id`       = VALUES(`module_id`),
    `label_ar`        = VALUES(`label_ar`),
    `icon`            = VALUES(`icon`),
    `sort_order`      = VALUES(`sort_order`),
    `permission_code` = VALUES(`permission_code`),
    `active`          = VALUES(`active`);

-- ── ③ استكمالُ عقد الصلاحية على الثمانية القائمة (كانت تحمله سلفًا) ───────
UPDATE `nav_items`
   SET `permission_code` = `route`
 WHERE `door` = 'HOME'
   AND (`permission_code` IS NULL OR `permission_code` = '');
