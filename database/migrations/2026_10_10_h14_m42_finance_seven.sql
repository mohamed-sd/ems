-- ═══════════════════════════════════════════════════════════════════════════
-- H-14 + M-42 · السايدبارُ الماليُّ السباعي + صندوقُ الاعتمادات الموحّد — 2026-08-01
-- المصدر: UX-02 §6 «سبعُ مجموعاتٍ لا 35 رابطًا» بأسمائها نصًّا · §5 (الصندوقُ
--         الموحّد في «عملي اليوم») — والقاعدة: **إخفاءٌ وإعادةُ تصنيفٍ لا حذف**.
-- ═══════════════════════════════════════════════════════════════════════════

-- ① المجموعاتُ السبع (UX-02 §6 حرفيًّا) — تُنشأ لمالكها الدور 17
INSERT INTO `link_groups` (`name`, `owner_role_id`, `icon`, `display_order`, `is_active`)
SELECT * FROM (
    SELECT 'عملي اليوم'            n, 17 r, 'fa fa-sun'                  i, 110 d, 1 a UNION ALL
    SELECT 'العملاء والتحصيل',        17,   'fa fa-handshake',              120,   1   UNION ALL
    SELECT 'الموردون والمدفوعات',     17,   'fa fa-truck-field',            130,   1   UNION ALL
    SELECT 'الخزينة والبنوك',         17,   'fa fa-vault',                  140,   1   UNION ALL
    SELECT 'المحاسبة العامة',         17,   'fa fa-book',                   150,   1   UNION ALL
    SELECT 'التخطيط والتحليل',        17,   'fa fa-chart-line',             160,   1   UNION ALL
    SELECT 'الإعدادات والتدقيق',      17,   'fa fa-user-shield',            170,   1
) g
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `link_groups`) lg
                    WHERE lg.`name` = g.n AND lg.`owner_role_id` = 17);

-- ② شاشةُ M-42 — صندوقُ الاعتمادات الموحّد (192)
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 192, 'صندوق الاعتمادات الموحد', 'Finance/approvals_inbox.php', 17, 0, 1, 'fa fa-inbox', 0
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `modules`) m
                    WHERE m.`code` = 'Finance/approvals_inbox.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 192, 1, 0, 0, 0
  FROM (SELECT 17 rid UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 22) r
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
                    WHERE rp.`role_id` = r.rid AND rp.`module_id` = 192);

INSERT INTO `nav_items` (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`,
                         `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT 17, 'APPR', (SELECT id FROM (SELECT * FROM `link_groups`) x
                     WHERE x.`name`='عملي اليوم' AND x.`owner_role_id`=17 LIMIT 1),
       192, 'صندوق الاعتمادات الموحد', 'Finance/approvals_inbox.php', 'fa fa-inbox', 5,
       NULL, 'Finance/approvals_inbox.php', 1
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `nav_items`) n
                    WHERE n.`role_id`=17 AND n.`route`='Finance/approvals_inbox.php');

-- ③ إعادةُ تصنيف روابط الدور 17 إلى السبع — بالمسار لا بالحدس؛
--    وما لم يُذكر يذهب إلى أقرب بابه (اجتهادٌ مدوَّن في السجل)
UPDATE `nav_items` n
   SET n.`group_id` = (
    SELECT id FROM (SELECT * FROM `link_groups`) g
     WHERE g.`owner_role_id` = 17 AND g.`is_active` = 1
       AND g.`name` = CASE
        -- ① عملي اليوم
        WHEN n.`route` IN ('Finance/cfo_daily_board_fin.php','FinRequests/accountant_desk.php',
                           'FinRequests/dept_inbox.php','FinRequests/finance_gateway.php',
                           'Approvals/attribution_board.php','Finance/approvals_inbox.php')
            THEN 'عملي اليوم'
        -- ② العملاء والتحصيل
        WHEN n.`route` LIKE 'Contracts/%' OR n.`route` = 'Finance/dues_fin.php'
            THEN 'العملاء والتحصيل'
        -- ③ الموردون والمدفوعات (ومستحقاتُ الأطراف الداخلية معها — اجتهاد)
        WHEN n.`route` LIKE 'Suppliers/%' OR n.`route` = 'Finance/supplier_statement_fin.php'
             OR n.`route` LIKE 'Workforce/%'
            THEN 'الموردون والمدفوعات'
        -- ④ الخزينة والبنوك
        WHEN n.`route` IN ('Finance/payments_fin.php','Finance/bank_reconciliation_fin.php',
                           'Finance/currencies_fin.php','Finance/cash_forecast_fin.php')
            THEN 'الخزينة والبنوك'
        -- ⑤ المحاسبة العامة
        WHEN n.`route` IN ('Finance/journal_form_fin.php','Finance/events_list_fin.php',
                           'Finance/accounts_fin.php','Finance/periods_fin.php',
                           'Finance/financial_statements_fin.php','Finance/import_events_fin.php',
                           'Finance/unit_records_fin.php','Finance/assets_fin.php',
                           'Finance/periodic_events_fin.php')
            THEN 'المحاسبة العامة'
        -- ⑥ التخطيط والتحليل (والضرائبُ والأصولُ فيه نصًّا §6)
        WHEN n.`route` IN ('Finance/budget_form_fin.php','Finance/variance_monitor_fin.php',
                           'Finance/cost_report_fin.php','Finance/management_accounting_fin.php',
                           'Finance/funding_fin.php','Finance/executive_dashboard_fin.php',
                           'Finance/tax_fin.php','FinRequests/cycle_time_board.php',
                           'FinRequests/requests_reports.php','Transport/transfer_tariffs.php')
            THEN 'التخطيط والتحليل'
        -- ⑦ الإعدادات والتدقيق — «للمخوّلين فقط»
        WHEN n.`route` IN ('Settings/settings.php','Finance/accountants_fin.php',
                           'FinRequests/routing_admin.php','FinRequests/effect_map.php',
                           'Finance/maintenance_provision_fin.php',
                           'Finance/operator_pay_policies_fin.php','main/project_users.php')
            THEN 'الإعدادات والتدقيق'
        ELSE 'المحاسبة العامة'
    END LIMIT 1)
 WHERE n.`role_id` = 17;

-- ④ المجموعاتُ الماليةُ القديمة تُخفى **ولا تُحذف** (بعد أن خلت من روابطها)
UPDATE `link_groups` SET `is_active` = 0
 WHERE `owner_role_id` = 17
   AND `name` NOT IN ('عملي اليوم','العملاء والتحصيل','الموردون والمدفوعات',
                      'الخزينة والبنوك','المحاسبة العامة','التخطيط والتحليل',
                      'الإعدادات والتدقيق');
