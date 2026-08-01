-- ═══════════════════════════════════════════════════════════════════════════
-- E-04 · E-05 · E-20 · E-22 · E-23 — تحسيناتُ التنقل والعدّادات — 2026-08-01
-- القاعدة: **إخفاءٌ وإعادةُ توجيهٍ لا حذف** (عقد العمل ④).
-- ═══════════════════════════════════════════════════════════════════════════

-- ── E-04 (SPEC-01 #21): متابعةُ الانحراف تبويبٌ من الميزانية — صفُّها في
--    سايدبار 17 يُخفى (رابطُها من شاشة الميزانية · والشاشةُ نفسُها باقية)
UPDATE `nav_items` SET `active` = 0
 WHERE `role_id` = 17 AND `route` = 'Finance/variance_monitor_fin.php' AND `active` = 1;

-- ── E-05 (SPEC-01 #15): «المعاونون» من سايدبار المالية إلى مدير الصلاحيات ──
UPDATE `nav_items` SET `active` = 0
 WHERE `role_id` = 17 AND `route` = 'main/project_users.php' AND `active` = 1;

INSERT INTO `nav_items` (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`,
                         `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT 15, 'REC', NULL,
       (SELECT id FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'main/project_users.php' LIMIT 1),
       'المعاونون', 'main/project_users.php', 'fa fa-users-gear', 20, NULL, 'main/project_users.php', 1
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `nav_items`) n
                    WHERE n.`role_id` = 15 AND n.`route` = 'main/project_users.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 15, m.id, 1, 1, 1, 0 FROM `modules` m
 WHERE m.`code` = 'main/project_users.php'
   AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
                    WHERE rp.`role_id` = 15 AND rp.`module_id` = m.id);

-- ── E-20 (UX-01 §10): تعميمُ counter_source على العناصر ذات الصناديق ───────
-- المفاتيحُ من قاموس role_board نفسِه — «المفتاحُ نفسُه المستعمل في
-- nav_items.counter_source» (لا مفاتيحَ تُخترع بلا مستهلك)
UPDATE `nav_items` SET `counter_source` = 'tickets_open'
 WHERE `route` = 'Tickets/tickets_list.php'
   AND (`counter_source` IS NULL OR `counter_source` = '');

UPDATE `nav_items` SET `counter_source` = 'finreq_pending'
 WHERE `route` IN ('FinRequests/finance_gateway.php', 'Finance/approvals_inbox.php')
   AND (`counter_source` IS NULL OR `counter_source` = '');

UPDATE `nav_items` SET `counter_source` = 'units_pending_approval'
 WHERE `route` = 'Operations/operations_room.php'
   AND (`counter_source` IS NULL OR `counter_source` = '');

UPDATE `nav_items` SET `counter_source` = 'claims_unbilled'
 WHERE `route` = 'Contracts/claims.php'
   AND (`counter_source` IS NULL OR `counter_source` = '');

-- ── E-22 (SPEC-00 §2-①): «مركزُ التقارير» خمسةُ صفوفِ modules لكودٍ واحد —
--    القانونيُّ **أدنى id (4)** (وهو ما يفوز به الحارسُ المركزيُّ أصلًا):
--    تُنقل الصلاحياتُ والروابطُ إليه، والصفوفُ المكررةُ تبقى **يتيمةً موثَّقة**
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT rp.`role_id`, 4, MAX(rp.`can_view`), MAX(rp.`can_add`), MAX(rp.`can_edit`), MAX(rp.`can_delete`)
  FROM `role_permissions` rp
 WHERE rp.`module_id` IN (6, 28, 31, 32)
   AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `role_permissions`) x
                    WHERE x.`role_id` = rp.`role_id` AND x.`module_id` = 4)
 GROUP BY rp.`role_id`;

UPDATE `nav_items` SET `module_id` = 4
 WHERE `route` = 'Reports/reports.php' AND `module_id` IN (6, 28, 31, 32);

-- ── E-23 (SPEC-01 §36): تعبئةُ nav_redirects للمسارات المدموجة بعدّاد hits ──
INSERT INTO `nav_redirects` (`old_route`, `new_route`, `active`, `hits`)
SELECT * FROM (
    SELECT 'Oprators/oprators.php'            o, 'Operations/operations_room.php'  n, 1 a, 0 h UNION ALL
    SELECT 'Oprators/select_project.php',        'Operations/operations_room.php',    1,   0   UNION ALL
    SELECT 'Reports/timesheetdeliy.php',         'Reports/daily_units_report.php',    1,   0   UNION ALL
    SELECT 'Finance/variance_monitor_fin.php',   'Finance/budget_form_fin.php',       1,   0   UNION ALL
    SELECT 'Maintenance/breakdowns.php',         'Tickets/tickets_list.php',          1,   0
) seed
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `nav_redirects`) nr
                    WHERE nr.`old_route` = seed.o);
