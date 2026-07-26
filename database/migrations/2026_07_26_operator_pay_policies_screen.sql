-- ═══════════════════════════════════════════════════════════════════════════
-- تسجيل شاشة «سياسات مستحقات المشغّلين» (UX-02 §8.2) — 2026-07-26
-- ───────────────────────────────────────────────────────────────────────────
-- التسجيل واجبٌ (الحارس المركزي يفترض السماح لشاشةٍ بلا صفّ module).
-- المنح على نمط m128 القائمة: 17 كاملًا · 18-22 عرضًا.
--
-- السايدبار: صفوف nav_items الست القائمة لـ«قواعد مستحقات المشغّلين» تُحوَّل
-- إلى الشاشة الجديدة (السياساتُ هي الشاشةَ الأولى الآن — §8.2 «تُعاد جدولَ
-- سياسات»). الشاشةُ القديمة تبقى مسجَّلةً (m128) ويُوصل إليها من رابطٍ داخل
-- الجديدة — فهي المسارُ الساقط حين لا سياسة، لا شاشةَ يوميّة.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 'سياسات مستحقات المشغّلين', 'Finance/operator_pay_policies_fin.php', 17, '0', 'fa fa-scale-balanced', 0
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code` = 'Finance/operator_pay_policies_fin.php');

-- المدير المالي: كامل الصلاحية
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 17, m.`id`, 1, 1, 1, 0 FROM `modules` m
WHERE m.`code` = 'Finance/operator_pay_policies_fin.php'
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` p WHERE p.`role_id` = 17 AND p.`module_id` = m.`id`);

-- الأدوار المالية الفرعية: عرضٌ فقط (نمط m128)
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.`id`, m.`id`, 1, 0, 0, 0
FROM `modules` m
JOIN `roles` r ON r.`id` IN (18, 19, 20, 21, 22)
WHERE m.`code` = 'Finance/operator_pay_policies_fin.php'
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` p WHERE p.`role_id` = r.`id` AND p.`module_id` = m.`id`);

-- تحويل صفوف السايدبار الست إلى الشاشة الجديدة (الاسم كما هو — الشاشةُ خلفَه تبدّلت)
UPDATE `nav_items` n
  JOIN `modules` m ON m.`code` = 'Finance/operator_pay_policies_fin.php'
   SET n.`route` = 'Finance/operator_pay_policies_fin.php',
       n.`module_id` = m.`id`
 WHERE n.`route` = 'Finance/operator_pay_fin.php';
