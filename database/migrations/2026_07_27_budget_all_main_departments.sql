-- ═══════════════════════════════════════════════════════════════════════════
-- «الميزانيةُ في كلِّ الإداراتِ الرئيسية» — استكمالُ الخمسِ الناقصة — 2026-07-27
-- ───────────────────────────────────────────────────────────────────────────
-- قياسٌ قبل العمل: الإداراتُ الرئيسيةُ ثلاثَ عشرةَ (roles.parent_role_id IS NULL)،
-- وثمانٍ منها تملك قسمًا في `fin_budgets.dept_module`. والخمسُ الباقيات —
--   5  إدارة الموقع            6  إدارة الحركة والتشغيل
--   15 إدارة الصلاحيات         23 إدارة النقل والترحيل
--   24 إدارة البلاغات
-- كنَّ مضمومةً إلى بندٍ واحدٍ اسمُه `general` عنوانُه في جدول التوجيه نصًّا:
-- «طلبات عامة (النقل والحركة والمواقع)» ومديرُه **17 المالية**. فكانت الإدارةُ
-- تُنفِق ولا ترفع موازنةً، لأن قسمَها ليس لها بل للمالية.
--
-- قرارُ المالك (2026-07-27): «يجب أن تكون الميزانيةُ في كل الإدارات الرئيسية».
-- فتُعطى كلُّ واحدةٍ قسمَها باسمها، ومديرُها يملكه رفعًا — تمامًا كالصيانةِ
-- والأسطولِ والمشتريات، بلا استثناء.
--
-- ملاحظتان في المفردات:
--   · `movement` و`transport` موجودتان أصلًا في `fin_financial_events` — فأُعيد
--     استعمالُ الاسمَين نفسِهما لا اختراعُ مرادفٍ ثانٍ لهما.
--   · `sites` · `tickets` · `admin` جديدةٌ وتُضاف إلى الدفتر كذلك ليقبلَ أثرَها.
--
-- وحتى لا تنتهيَ شاشةٌ بطريقٍ مسدود (الدستور §2): مَن صار مديرَ قسمٍ يملك
-- طلباتِه اعتمادًا يحتاج «موافقات إدارتي» — وهي مفقودةٌ عند الخمسِ جميعًا. فتُمنح.
-- وبندُ `general` يبقى كما هو لم يُمَسّ: طريقٌ إضافيٌّ لا بديل.
--
-- كلُّ ما دون إضافةٌ محضة — لا حذفَ ولا تعديلَ صفٍّ قائم.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① المفردات: خمسةُ أقسامٍ جديدة ─────────────────────────────────────────
ALTER TABLE `fin_budgets`
  MODIFY COLUMN `dept_module` ENUM(
    'sales','suppliers','workforce','procurement','warehouse','maintenance',
    'projects','revenue','assets','treasury','general',
    'sites','movement','transport','tickets','admin'
  ) NOT NULL;

ALTER TABLE `fin_request_routing`
  MODIFY COLUMN `source_module` ENUM(
    'sales','suppliers','workforce','procurement','warehouse','maintenance',
    'projects','revenue','assets','treasury','general',
    'sites','movement','transport','tickets','admin'
  ) NOT NULL;

ALTER TABLE `fin_requests`
  MODIFY COLUMN `source_module` ENUM(
    'sales','suppliers','workforce','procurement','warehouse','maintenance',
    'projects','revenue','assets','treasury','general',
    'sites','movement','transport','tickets','admin'
  ) NOT NULL;

-- الدفترُ يعرف movement/transport أصلًا — تُضاف الثلاثُ الباقية فقط
ALTER TABLE `fin_financial_events`
  MODIFY COLUMN `source_module` ENUM(
    'sales','suppliers','workforce','procurement','warehouse','maintenance',
    'projects','revenue','assets','treasury','movement','finance','transport',
    'system','sites','tickets','admin'
  ) NOT NULL;

-- ── ② الملكية: كلُّ إدارةٍ مديرُها يملك قسمَها ──────────────────────────────
-- (تُبذر لكل شركةٍ لها صفوفُ توجيهٍ أصلًا — لا تُخترع شركةٌ جديدة)
INSERT INTO `fin_request_routing`
    (`company_id`, `source_module`, `module_label`, `requester_roles`,
     `reviewer_role_id`, `manager_role_id`, `is_active`, `created_at`, `updated_at`)
SELECT co.company_id, d.src, d.lbl, d.reqs, d.mgr, d.mgr, 1, NOW(), NOW()
  FROM (SELECT DISTINCT `company_id` FROM `fin_request_routing`) co
  CROSS JOIN (
        SELECT 'sites'     AS src, 'المواقع'              AS lbl, '5'  AS reqs, 5  AS mgr
  UNION ALL SELECT 'movement',     'الحركة والتشغيل',            '6',        6
  UNION ALL SELECT 'transport',    'النقل والترحيل',             '23',       23
  UNION ALL SELECT 'tickets',      'البلاغات',                   '24',       24
  UNION ALL SELECT 'admin',        'الإدارة والصلاحيات',         '15',       15
  ) d
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `fin_request_routing`) x
     WHERE x.`company_id` = co.company_id AND x.`source_module` = d.src);

-- ── ③ الصلاحيات: شاشةُ الميزانية إنشاءً وتعديلًا لمديري الخمس ──────────────
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, m.id, 1, 1, 1, 0
  FROM (SELECT 5 AS rid UNION ALL SELECT 6 UNION ALL SELECT 15 UNION ALL SELECT 24) r
  CROSS JOIN (SELECT id FROM `modules` WHERE `code` = 'Finance/budget_form_fin.php' LIMIT 1) m
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) p
     WHERE p.`role_id` = r.rid AND p.`module_id` = m.id);

-- النقلُ (23) كانت لها الشاشةُ عرضًا فقط — فلا ترفع موازنةً. تُرفع إلى إنشاءٍ وتعديل.
UPDATE `role_permissions` p
  JOIN `modules` m ON m.`id` = p.`module_id`
   SET p.`can_add` = 1, p.`can_edit` = 1
 WHERE m.`code` = 'Finance/budget_form_fin.php' AND p.`role_id` = 23;

-- ── ④ الصلاحيات: صندوقُ موافقات الإدارة (وإلا فالاعتمادُ بلا شاشة) ──────────
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, m.id, 1, 0, 0, 0
  FROM (SELECT 5 AS rid UNION ALL SELECT 6 UNION ALL SELECT 15
        UNION ALL SELECT 23 UNION ALL SELECT 24) r
  CROSS JOIN (SELECT id FROM `modules` WHERE `code` = 'FinRequests/dept_inbox.php' LIMIT 1) m
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) p
     WHERE p.`role_id` = r.rid AND p.`module_id` = m.id);

-- البلاغاتُ (24) وحدَها بلا بابٍ إلى الطلبات المالية أصلًا — يُفتح لها
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 24, m.id, 1, IF(m.`code` = 'FinRequests/request_form.php', 1, 0), 0, 0
  FROM `modules` m
 WHERE m.`code` IN ('FinRequests/request_form.php', 'FinRequests/my_requests.php')
   AND NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) p
     WHERE p.`role_id` = 24 AND p.`module_id` = m.id);

-- ── ⑤ الطريق: روابطُ السايدبار (صلاحيةٌ بلا رابطٍ = شاشةٌ لا يُهتدى إليها) ───
-- الميزانيةُ في باب «المتابعة والموافقات» — عملٌ ينتظر قرارًا لا تقريرٌ يُقرأ
INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`,
     `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'APPR', NULL, m.id, 'الميزانية والانحراف', 'Finance/budget_form_fin.php',
       'fa fa-chart-pie', 20, NULL, 'Finance/budget_form_fin.php', 1
  FROM (SELECT 5 AS rid UNION ALL SELECT 6 UNION ALL SELECT 15 UNION ALL SELECT 24) r
  CROSS JOIN (SELECT id FROM `modules` WHERE `code` = 'Finance/budget_form_fin.php' LIMIT 1) m
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Finance/budget_form_fin.php');

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`,
     `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'APPR', NULL, m.id, 'موافقات إدارتي', 'FinRequests/dept_inbox.php',
       'fa fa-inbox', 10, NULL, 'FinRequests/dept_inbox.php', 1
  FROM (SELECT 5 AS rid UNION ALL SELECT 6 UNION ALL SELECT 15
        UNION ALL SELECT 23 UNION ALL SELECT 24) r
  CROSS JOIN (SELECT id FROM `modules` WHERE `code` = 'FinRequests/dept_inbox.php' LIMIT 1) m
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'FinRequests/dept_inbox.php');

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`,
     `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT 24, 'DAILY', NULL, m.id,
       IF(m.`code` = 'FinRequests/request_form.php', 'طلب مالي جديد', 'طلباتي المالية'),
       m.`code`,
       IF(m.`code` = 'FinRequests/request_form.php', 'fa fa-file-circle-plus', 'fa fa-list-check'),
       IF(m.`code` = 'FinRequests/request_form.php', 20, 30),
       NULL, m.`code`, 1
  FROM `modules` m
 WHERE m.`code` IN ('FinRequests/request_form.php', 'FinRequests/my_requests.php')
   AND NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = 24 AND n.`route` = m.`code`);
