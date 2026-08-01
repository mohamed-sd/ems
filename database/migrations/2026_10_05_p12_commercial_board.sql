-- ═══════════════════════════════════════════════════════════════════════════
-- P-12 · اللوحةُ التجارية للعقود — 2026-08-01
-- البطاقة: docs/specs/P-12_commercial_board.md
-- المصدر: الملحق §3-`P-12`: «**لوحةُ العقد التجارية**: المخططُ · المنفَّذُ ·
--         المفوترُ · المحصَّل **في سطرٍ واحدٍ لكل عقدٍ نافذ**، **وكلُّ فجوةٍ
--         بمالكها**» · §4 **شرطُ إغلاق الموجة**.
-- ───────────────────────────────────────────────────────────────────────────
-- ⚠ **ولا جدولَ جديدٌ في هذه المهمة**: الأرقامُ الأربعةُ **كلُّها لها بيوتٌ
--   قائمة** — `contract_monthly_plan` و`unit_entries` و`claims`
--   و`fin_receivables`. وجدولُ «لوحة» يخزّنها **يفتح مصدرًا ثانيًا للرقم
--   الواحد**، وهو عينُ ما مُنع في `M-01` و`P-06` و`P-08`.
--   **فاللوحةُ قراءةٌ لا تخزين** — والهجرةُ تسجّل الشاشةَ فقط.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 181, 'اللوحة التجارية للعقود', 'Contracts/commercial_board.php', 12, 0, 1, 'fa fa-chart-line', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/commercial_board.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 181, 1, 0, 0, 0
  FROM (SELECT 12 AS rid UNION ALL SELECT 17 UNION ALL SELECT 19
        UNION ALL SELECT 20 UNION ALL SELECT 22) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 181);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 181, 'اللوحة التجارية للعقود', 'Contracts/commercial_board.php',
       'fa fa-chart-line', 80, NULL, 'Contracts/commercial_board.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 17 UNION ALL SELECT 19
        UNION ALL SELECT 20 UNION ALL SELECT 22) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/commercial_board.php');
