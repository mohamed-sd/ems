-- ═══════════════════════════════════════════════════════════════════════════
-- بذرة مجموعات الروابط — تنظيمٌ أوّليّ لدورين نموذجيّين
-- ───────────────────────────────────────────────────────────────────────────
-- ليست عقدًا ولا سلوكًا: مجرّد تنظيمٍ أوّليّ يجعل الميزة مرئيةً فور التشغيل،
-- وكلّه قابلٌ للتغيير من شاشة admin/permissions/link_groups.php.
--
-- اختير دوران متقابلان عمدًا:
--   • ادارة التشغيل (1) — 8 روابط: يُظهر الحالة المختلطة (مجموعاتٌ ومفردات
--     معًا)، فـ«مركز التقارير» و«الإعدادات» تبقيان في المستوى الأعلى.
--   • إدارة المالية (17) — 30 رابطًا: الحالة التي وُلدت الميزة لأجلها.
--
-- الإسناد بالكود لا بالمعرّف الرقمي: معرّفات الشاشات تختلف بين النسخ، أما
-- الكود فهو المسار الفعليّ وثابت. وكلّ عبارةٍ هنا idempotent (تُعيد الإسناد
-- لنفس القيمة إن أُعيد التشغيل).
--
-- التطبيق عبر database/migrate.php حصرًا.
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── ① مجموعات ادارة التشغيل (الدور 1) ──────────────────────────────────────
INSERT INTO `link_groups` (`name`, `owner_role_id`, `icon`, `display_order`, `is_active`)
SELECT * FROM (SELECT 'علاقات العملاء' AS n, 1 AS r, 'fa fa-handshake' AS i, 10 AS o, 1 AS a) AS t
WHERE NOT EXISTS (SELECT 1 FROM `link_groups` WHERE `name` = 'علاقات العملاء' AND `owner_role_id` = 1);

INSERT INTO `link_groups` (`name`, `owner_role_id`, `icon`, `display_order`, `is_active`)
SELECT * FROM (SELECT 'الأصول والتشغيل' AS n, 1 AS r, 'fa fa-tractor' AS i, 20 AS o, 1 AS a) AS t
WHERE NOT EXISTS (SELECT 1 FROM `link_groups` WHERE `name` = 'الأصول والتشغيل' AND `owner_role_id` = 1);

INSERT INTO `link_groups` (`name`, `owner_role_id`, `icon`, `display_order`, `is_active`)
SELECT * FROM (SELECT 'النظام والمتابعة' AS n, 1 AS r, 'fa fa-user-shield' AS i, 30 AS o, 1 AS a) AS t
WHERE NOT EXISTS (SELECT 1 FROM `link_groups` WHERE `name` = 'النظام والمتابعة' AND `owner_role_id` = 1);

UPDATE `modules` m
  JOIN `link_groups` g ON g.`name` = 'علاقات العملاء' AND g.`owner_role_id` = 1
   SET m.`group_id` = g.`id`
 WHERE m.`owner_role_id` = 1
   AND m.`code` IN ('Clients/clients.php', 'Projects/projects.php', 'Contracts/contracts.php');

UPDATE `modules` m
  JOIN `link_groups` g ON g.`name` = 'الأصول والتشغيل' AND g.`owner_role_id` = 1
   SET m.`group_id` = g.`id`
 WHERE m.`owner_role_id` = 1
   AND m.`code` IN ('Equipments/equipments_types.php');

UPDATE `modules` m
  JOIN `link_groups` g ON g.`name` = 'النظام والمتابعة' AND g.`owner_role_id` = 1
   SET m.`group_id` = g.`id`
 WHERE m.`owner_role_id` = 1
   AND m.`code` IN ('main/users.php', 'ActivityLogs/activity_logs.php');

-- ── ② مجموعات إدارة المالية (الدور 17) ─────────────────────────────────────
INSERT INTO `link_groups` (`name`, `owner_role_id`, `icon`, `display_order`, `is_active`)
SELECT * FROM (SELECT 'اللوحات والتقارير' AS n, 17 AS r, 'fa fa-chart-pie' AS i, 10 AS o, 1 AS a) AS t
WHERE NOT EXISTS (SELECT 1 FROM `link_groups` WHERE `name` = 'اللوحات والتقارير' AND `owner_role_id` = 17);

INSERT INTO `link_groups` (`name`, `owner_role_id`, `icon`, `display_order`, `is_active`)
SELECT * FROM (SELECT 'الحسابات والقيود' AS n, 17 AS r, 'fa fa-book' AS i, 20 AS o, 1 AS a) AS t
WHERE NOT EXISTS (SELECT 1 FROM `link_groups` WHERE `name` = 'الحسابات والقيود' AND `owner_role_id` = 17);

INSERT INTO `link_groups` (`name`, `owner_role_id`, `icon`, `display_order`, `is_active`)
SELECT * FROM (SELECT 'الذمم والمدفوعات' AS n, 17 AS r, 'fa fa-hand-holding-dollar' AS i, 30 AS o, 1 AS a) AS t
WHERE NOT EXISTS (SELECT 1 FROM `link_groups` WHERE `name` = 'الذمم والمدفوعات' AND `owner_role_id` = 17);

INSERT INTO `link_groups` (`name`, `owner_role_id`, `icon`, `display_order`, `is_active`)
SELECT * FROM (SELECT 'الميزانية والتكاليف' AS n, 17 AS r, 'fa fa-scale-balanced' AS i, 40 AS o, 1 AS a) AS t
WHERE NOT EXISTS (SELECT 1 FROM `link_groups` WHERE `name` = 'الميزانية والتكاليف' AND `owner_role_id` = 17);

INSERT INTO `link_groups` (`name`, `owner_role_id`, `icon`, `display_order`, `is_active`)
SELECT * FROM (SELECT 'بوابة الطلبات' AS n, 17 AS r, 'fa fa-file-invoice-dollar' AS i, 50 AS o, 1 AS a) AS t
WHERE NOT EXISTS (SELECT 1 FROM `link_groups` WHERE `name` = 'بوابة الطلبات' AND `owner_role_id` = 17);

INSERT INTO `link_groups` (`name`, `owner_role_id`, `icon`, `display_order`, `is_active`)
SELECT * FROM (SELECT 'الأصول والتمويل' AS n, 17 AS r, 'fa fa-building-columns' AS i, 60 AS o, 1 AS a) AS t
WHERE NOT EXISTS (SELECT 1 FROM `link_groups` WHERE `name` = 'الأصول والتمويل' AND `owner_role_id` = 17);

UPDATE `modules` m
  JOIN `link_groups` g ON g.`name` = 'اللوحات والتقارير' AND g.`owner_role_id` = 17
   SET m.`group_id` = g.`id`
 WHERE m.`owner_role_id` = 17
   AND m.`code` IN ('Finance/cfo_daily_board_fin.php', 'Finance/executive_dashboard_fin.php',
                    'Finance/financial_statements_fin.php', 'Finance/cost_report_fin.php',
                    'Finance/management_accounting_fin.php');

UPDATE `modules` m
  JOIN `link_groups` g ON g.`name` = 'الحسابات والقيود' AND g.`owner_role_id` = 17
   SET m.`group_id` = g.`id`
 WHERE m.`owner_role_id` = 17
   AND m.`code` IN ('Finance/accounts_fin.php', 'Finance/journal_form_fin.php',
                    'Finance/events_list_fin.php', 'Finance/import_events_fin.php',
                    'Finance/accountants_fin.php', 'Finance/unit_records_fin.php',
                    'Finance/periods_fin.php');

UPDATE `modules` m
  JOIN `link_groups` g ON g.`name` = 'الذمم والمدفوعات' AND g.`owner_role_id` = 17
   SET m.`group_id` = g.`id`
 WHERE m.`owner_role_id` = 17
   AND m.`code` IN ('Finance/dues_fin.php', 'Finance/supplier_statement_fin.php',
                    'Finance/payments_fin.php', 'Finance/bank_reconciliation_fin.php');

UPDATE `modules` m
  JOIN `link_groups` g ON g.`name` = 'الميزانية والتكاليف' AND g.`owner_role_id` = 17
   SET m.`group_id` = g.`id`
 WHERE m.`owner_role_id` = 17
   AND m.`code` IN ('Finance/budget_form_fin.php', 'Finance/variance_monitor_fin.php',
                    'Finance/tax_fin.php', 'Finance/maintenance_provision_fin.php',
                    'Finance/operator_pay_fin.php');

UPDATE `modules` m
  JOIN `link_groups` g ON g.`name` = 'بوابة الطلبات' AND g.`owner_role_id` = 17
   SET m.`group_id` = g.`id`
 WHERE m.`owner_role_id` = 17
   AND m.`code` LIKE 'FinRequests/%';

UPDATE `modules` m
  JOIN `link_groups` g ON g.`name` = 'الأصول والتمويل' AND g.`owner_role_id` = 17
   SET m.`group_id` = g.`id`
 WHERE m.`owner_role_id` = 17
   AND m.`code` IN ('Finance/funding_fin.php', 'Finance/cash_forecast_fin.php',
                    'Finance/assets_fin.php');
