-- ═══════════════════════════════════════════════════════════════════════════
-- تسجيلُ شاشة العملات وأسعار الصرف — 2026-07-28
-- ───────────────────────────────────────────────────────────────────────────
-- الوحدةُ 143 (بعد 142 «المستخلصات») — الرقمُ صريحٌ لا MAX+1 كي يبقى الترحيلُ
-- حتميًّا لو أُعيد على قاعدةٍ أخرى.
--
-- **الملكية**: سعرُ الصرف قرارٌ ماليٌّ محض — المديرُ المالي (17) ومديرُ الإدارة
-- المالية (19) لهما الضبط، ومحاسبُها (18) وأمينُ الخزينة (20) والمراجعُ (21)
-- عرضًا، والقارئُ المالي (22) عرضًا. ولا يُمنح خارج الأسرة المالية: رقمٌ واحدٌ
-- خاطئٌ هنا يعيد تقييمَ الدفتر كلِّه.
--
-- **الباب**: الإعدادات (SET) — السعرُ ضبطٌ يُدخَل كلما تغيّر لا مستندَ دورةٍ ولا
-- تقرير (الدستور §6). والاسمُ «العملات وأسعار الصرف» يقول المهمةَ لا الماهية.
--
-- **لا عدّاد على الرابط**: العدّادُ يعني «ينتظر قرارَك» (UX-01 المبدأ ⑦)، وما
-- ينتظر سعرًا يُعرض **داخل الشاشة** شارةً لأنه ليس طابورَ موافقات.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 143, 'العملات وأسعار الصرف', 'Finance/currencies_fin.php', 17, 0, 0, 'fa fa-money-bill-transfer', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Finance/currencies_fin.php');

-- الصلاحيات: 17 و19 ضبطًا · 18 و20 و21 و22 عرضًا. ولا حذفَ لأحد —
-- سعرٌ حُذف يترك صفوفًا مقيَّمةً بسعرٍ لا أثرَ له (التصحيحُ تعديلُ الصفّ).
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 143, 1, r.a, r.e, 0
  FROM (SELECT 17 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 19, 1, 1
        UNION ALL SELECT 18, 0, 0
        UNION ALL SELECT 20, 0, 0
        UNION ALL SELECT 21, 0, 0
        UNION ALL SELECT 22, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 143);

-- صفُّ السايدبار الموحّد — باب الإعدادات، بكود الشاشة لفحص can_view
INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'SET', NULL, 143, 'العملات وأسعار الصرف', 'Finance/currencies_fin.php',
       'fa fa-money-bill-transfer', 60, NULL, 'Finance/currencies_fin.php', 1
  FROM (SELECT 17 AS rid UNION ALL SELECT 18 UNION ALL SELECT 19
        UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Finance/currencies_fin.php');
