-- ═══════════════════════════════════════════════════════════════════════════
-- P-09 · مفاتيحُ ربط الخطة بالفعلي — 2026-08-01
-- البطاقة: docs/specs/P-09_plan_actual_keys.md
-- المصدر: الملحق §3-`P-09`: «**مفاتيحُ ربط الخطة بالفعلي**: `contract_line_id` ·
--         `plan_period_id` · `operational_site_id` **على الوحدة وسطر المستخلص**» ·
--         §4 شرطُ إغلاق الموجة: «`P-12` تعرض **الأرقام الأربعة** (مخططٌ ·
--         منفَّذٌ · مفوترٌ · محصَّل) لعقدٍ رائد» — **ولا سبيلَ إليها بلا هذه المفاتيح**.
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء:
--   · `unit_entries` (**145 صفًّا حيًّا**) تعرف `contract_id` و`project_id`
--     و`equipment_id` — **ولا تعرف أيَّ بندِ بيعٍ نفّذت، ولا أيَّ شهرٍ مخطَّطٍ
--     تخصّ، ولا أيَّ نطاقٍ تشغيليٍّ وقعت فيه**.
--   · `claim_lines` (5 صفوف) مثلُها: `source_kind`/`source_ref` و`work_date`
--     **وصفرُ وصلٍ بالخطة**.
--   · ⇒ «المخطَّطُ» و«المنفَّذُ» و«المفوتَر» **ثلاثةُ أرقامٍ لا تلتقي على مفتاح**،
--     فمقارنتُها **تخمينٌ بالتاريخ والعقد** لا وصلٌ محكم.
--   · و**اكتشافٌ**: `fin_financial_events.contract_line_id` **موجودٌ منذ
--     2026-08-08** وتعليقُه: «FK يُربط عند بناء سجل العقود الموحّد» —
--     **و NULL في الأحداث التسعة والخمسين كلِّها**. فهو **وعدٌ فارغٌ** انتظر
--     `client_contract_lines` (P-02) **سنةً**، وهذه المهمةُ تُعطيه مرجعَه.
--
-- ⚠ الأعمدةُ **كلُّها تقبل NULL**: الوصلُ **إضافةٌ لا شرط**، فلا تنكسر وحدةٌ
--   قائمةٌ ولا مستخلصٌ قديمٌ لأن مفتاحَه لم يُملأ بعد. **والفجوةُ تُعلَن عدًّا**
--   في `PlanActualLinkService::coverage()` لا تُخفى بقيمةٍ افتراضية.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① الوحدةُ تعرف ما نفّذت ولأيِّ شهرٍ وفي أيِّ نطاق ───────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'unit_entries' AND COLUMN_NAME = 'contract_line_id'),
    'ALTER TABLE `unit_entries`
       ADD COLUMN `contract_line_id` INT UNSIGNED NULL DEFAULT NULL
           COMMENT ''بندُ البيع المنفَّذ (P-02) — NULL = غيرُ موصولٍ بعد'' AFTER `contract_id`,
       ADD COLUMN `plan_period_id` INT UNSIGNED NULL DEFAULT NULL
           COMMENT ''شهرُ الخطة (P-03) الذي تخصّه'' AFTER `contract_line_id`,
       ADD COLUMN `operational_site_id` INT UNSIGNED NULL DEFAULT NULL
           COMMENT ''نطاقُ العقد التشغيلي (P-01)'' AFTER `plan_period_id`,
       ADD KEY `ix_ue_plan_keys` (`contract_line_id`, `plan_period_id`),
       ADD KEY `ix_ue_site` (`operational_site_id`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ② وسطرُ المستخلص يعرفها كذلك — «الفوترةُ تُنسَب لا تُجمَع» ──────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'claim_lines' AND COLUMN_NAME = 'contract_line_id'),
    'ALTER TABLE `claim_lines`
       ADD COLUMN `contract_line_id` INT UNSIGNED NULL DEFAULT NULL
           COMMENT ''بندُ البيع المفوتَر (P-02)'' AFTER `source_ref`,
       ADD COLUMN `plan_period_id` INT UNSIGNED NULL DEFAULT NULL
           COMMENT ''شهرُ الخطة (P-03)'' AFTER `contract_line_id`,
       ADD COLUMN `operational_site_id` INT UNSIGNED NULL DEFAULT NULL
           COMMENT ''نطاقُ العقد التشغيلي (P-01)'' AFTER `plan_period_id`,
       ADD KEY `ix_cl_plan_keys` (`contract_line_id`, `plan_period_id`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ③ ووعدُ 2026-08-08 يجد مرجعَه أخيرًا ───────────────────────────────────
-- `fin_financial_events.contract_line_id` كُتب تعليقُه: «FK يُربط عند بناء
-- سجل العقود الموحّد» — والسجلُّ صار `client_contract_lines` في P-02.
-- **ولا FK يُفرَض هنا**: الأحداثُ سجلُّ حقائقَ لا يُقيَّد بجدولٍ قد يُحذف صفُّه،
-- والوصلُ يُملأ بالخدمة عدًّا وبتقريرٍ — لا بقيدٍ يكسر النشرَ القائم.
ALTER TABLE `fin_financial_events`
  MODIFY COLUMN `contract_line_id` INT DEFAULT NULL
  COMMENT 'بندُ البيع (P-02 · `client_contract_lines`) — **وُصل مرجعُه في P-09 بعد أن كان وعدًا فارغًا**';

-- ── ④ تسجيلُ شاشة «ربط الخطة بالفعلي» — الوحدة 178 ─────────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 178, 'ربط الخطة بالفعلي', 'Contracts/plan_actual_link.php', 12, 0, 0, 'fa fa-link', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/plan_actual_link.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 178, 1, r.a, r.e, 0
  FROM (SELECT 12 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 19, 0, 0
        UNION ALL SELECT 17, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 178);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 178, 'ربط الخطة بالفعلي', 'Contracts/plan_actual_link.php',
       'fa fa-link', 77, NULL, 'Contracts/plan_actual_link.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 19 UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/plan_actual_link.php');
