-- ═══════════════════════════════════════════════════════════════════════════
-- CON-02 · المستوى المتوسط — المرحلة ③ : لوحةُ الإسناد اليومي — 2026-07-28
-- ───────────────────────────────────────────────────────────────────────────
-- **ق-6**: «لوحةُ الإسناد اليومي شاشةٌ جديدةٌ في `Approvals/` مملوكةٌ للدور 1
-- (إدارة التشغيل)». وهي **نقطةُ القرار** التي تنصّ عليها ق-4: «الكاتبُ يقترح
-- والمشرفُ يعتمد» — فما يكتبه الكاتبُ في شاشة الدوام مقترحٌ، وما يعتمده المشرفُ
-- هنا هو الإسنادُ الذي يقرؤه المال.
--
-- **الملكيةُ والصلاحيات:**
--   · إدارةُ التشغيل (1)       → can_add  = **الإسنادُ والاعتماد**
--   · مديرُ الإدارة المالية (19) → can_edit = **حسمُ الاعتراض** (ق-25: نفسُ من
--     يُجيز المصفوفةَ والجزاءات — فلا يُخترع مالكُ قرارٍ جديد)
--   · المبيعات (12) والمالي (17) → عرضًا
-- والشاشةُ تقرأ العمودين بهذا المعنى حرفيًّا (نمطُ `settlements` و`obligations`).
--
-- **الباب**: الاعتمادات (`APPR`) — قرارٌ يوميٌّ ينتظر صاحبَه، لا سجلٌّ ولا تقرير.
--   ⚠️ الرمزُ `APPR` لا `APR`: على `nav_items` قيدُ `chk_nav_door` يحصر الأبواب
--   في الستة (`HOME·DAILY·APPR·REC·REP·SET`)، فأيُّ رمزٍ آخرَ يرفضه المخطط.
-- **لا عدّاد**: `counter_source` يبقى NULL — لا مُنتِجَ للعدّادات اليوم إلا خريطةُ
--   `$badges` بالمسار، وقيمةٌ بلا منتِجٍ وسمٌ ميتٌ يُصان بلا فائدة. يُضاف حين
--   يُبنى عدّادُ «وقائعُ بزمنٍ بلا مسؤول».
--
-- إضافيٌّ محض: صفوفُ تسجيلٍ بنمط `INSERT ... WHERE NOT EXISTS` — لا DDL ولا
-- صفَّ بياناتٍ يتغيّر.
-- الرجوع: حذفُ صفوف الوحدة 146 من `modules` و`role_permissions` و`nav_items`.
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 146, 'لوحة الإسناد اليومي', 'Approvals/attribution_board.php', 1, 0, 0, 'fa fa-scale-unbalanced', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Approvals/attribution_board.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 146, 1, r.a, r.e, 0
  FROM (SELECT 1  AS rid, 1 AS a, 0 AS e   -- التشغيل: يُسنِد ويعتمد
        UNION ALL SELECT 19, 0, 1          -- مدير المالية: يحسم الاعتراض (ق-25)
        UNION ALL SELECT 12, 0, 0
        UNION ALL SELECT 17, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 146);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'APPR', NULL, 146, 'لوحة الإسناد اليومي', 'Approvals/attribution_board.php',
       'fa fa-scale-unbalanced', 12, NULL, 'Approvals/attribution_board.php', 1
  FROM (SELECT 1 AS rid UNION ALL SELECT 19 UNION ALL SELECT 12 UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`module_id` = 146);
