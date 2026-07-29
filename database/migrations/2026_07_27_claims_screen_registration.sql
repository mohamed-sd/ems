-- ═══════════════════════════════════════════════════════════════════════════
-- تسجيلُ شاشة المستخلصات: وحدةٌ وصلاحياتٌ وصفُّ سايدبار — 2026-07-27
-- ───────────────────────────────────────────────────────────────────────────
-- الوحدةُ 142 (بعد 141 «وثائق المعدات والمشغّلين») — الرقمُ صريحٌ لا MAX+1 كي
-- يبقى الترحيلُ حتميًّا لو أُعيد على قاعدةٍ أخرى.
--
-- الملكيةُ والصلاحيات على قاعدة UX-08 §4: المستخلصُ عملُ **إدارة المبيعات
-- والعقود** (دور 12) — لها الكامل؛ والماليةُ (17) ومديرُها (19) ومحاسبُها (18)
-- عرضًا لأن الذمّةَ والفاتورةَ بيتُهما (UX-02 دورة ②)؛ والتشغيل (1) عرضًا لأن
-- الوحداتِ مصدرُه.
--
-- الباب: **السجلات الرئيسية** (REC) — المستخلصُ مستندُ دورةٍ لا إعدادٌ ولا تقرير
-- (الدستور §6). والاسمُ «المستخلصات» بنمط «اسمُ جمعٍ مجرّد» (§4.1).
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 142, 'المستخلصات', 'Contracts/claims.php', 12, 0, 0, 'fa fa-file-invoice-dollar', 0
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/claims.php');

-- الصلاحيات: المبيعاتُ كاملةً · والباقون عرضًا
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 142, 1, r.a, r.e, 0
  FROM (SELECT 12 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 1,  0, 0
        UNION ALL SELECT 17, 0, 0
        UNION ALL SELECT 18, 0, 0
        UNION ALL SELECT 19, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 142);

-- صفُّ السايدبار الموحّد — باب السجلات الرئيسية، بكود الشاشة لفحص can_view
INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 142, 'المستخلصات', 'Contracts/claims.php', 'fa fa-file-invoice-dollar', 45, NULL, 'Contracts/claims.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 1 UNION ALL SELECT 17
        UNION ALL SELECT 18 UNION ALL SELECT 19) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/claims.php');
