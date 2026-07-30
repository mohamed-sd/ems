-- ═══════════════════════════════════════════════════════════════════════════
-- M-04 · كشفُ حساب العميل بطبقاته — 2026-07-31
-- البطاقة: docs/specs/M-04_client_statement.md
-- المصدر: ENT-03 §6 («تفاصيل: **كشفُ العميل بطبقاته** (مستخلصاتٌ · فواتيرُ ·
--         تحصيلاتٌ · محتجزٌ · مقدمةٌ · رصيد) **وكلُّ رقمٍ برابط مصدره**»)
--         · §4 (المحتجزُ «**لا يُنسى ولا يُخلط بالذمة الجارية**» · والمقدمةُ
--         «**ورصيدُها المتبقي ظاهرٌ دائمًا**» · والتحصيلُ «**والتخصيصُ ظاهرٌ
--         في الكشف لا صامتًا**»)
-- ───────────────────────────────────────────────────────────────────────────
-- **لا جدولَ جديدًا — قراءةٌ خالصة** (نظيرُ M-14 للمورد حرفيًّا): الطبقاتُ
-- الخمسُ كلُّها لها مصدرٌ حيٌّ اليوم — `claims` · `tax_invoices` (M-03) ·
-- `fin_payments` بـ`direction='collection'` · `claims.retention_amount` ·
-- `contract_advances`. فالهجرةُ **تسجيلُ شاشةٍ فقط**.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 165, 'كشف حساب العميل', 'Contracts/client_statement.php', 12, 0, 0, 'fa fa-receipt', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/client_statement.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 165, 1, 0, 0, 0
  FROM (SELECT 12 AS rid UNION ALL SELECT 17 UNION ALL SELECT 19 UNION ALL SELECT 22) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 165);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 165, 'كشف حساب العميل', 'Contracts/client_statement.php',
       'fa fa-receipt', 64, NULL, 'Contracts/client_statement.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 17 UNION ALL SELECT 19 UNION ALL SELECT 22) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/client_statement.php');
