-- ═══════════════════════════════════════════════════════════════════════════
-- استكمالُ رابط الموازنة لإدارتَي الموردين والأسطول — 2026-07-27
-- ───────────────────────────────────────────────────────────────────────────
-- سهوٌ في ترحيل دورة الرفع: مُنح الدوران 2 (الموردون) و3 (الأسطول) صلاحيةَ
-- الإنشاء والتعديل **ولم يُبذر لهما صفُّ سايدبار** — فالشاشةُ تعمل بالرابط
-- المباشر ولا طريقَ إليها من القائمة. (قِيس بحساب «مصعب» دور 2: الصلاحيةُ
-- والنطاقُ والإنشاءُ كلُّها تعمل — والرابطُ وحده مفقود.)
--
-- قاعدةُ المالك: «الميزانيةُ في كل الإدارات الرئيسية» — وهذان أوّلُ ما يُستكمل
-- لأنهما يملكان قسمَيهما (suppliers · assets) بالفعل في جدول التوجيه.
--
-- الباب APPR («المتابعة والموافقات») كسائر الأدوار — الموازنةُ عملٌ ينتظر قرارًا
-- لا تقريرٌ يُقرأ (UX-01 §8).
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'APPR', NULL, m.id, 'الميزانية والانحراف', 'Finance/budget_form_fin.php',
       'fa fa-chart-pie', 40, NULL, 'Finance/budget_form_fin.php', 1
  FROM (SELECT 2 AS rid UNION ALL SELECT 3) r
  CROSS JOIN (SELECT id FROM `modules` WHERE `code` = 'Finance/budget_form_fin.php' LIMIT 1) m
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Finance/budget_form_fin.php');
