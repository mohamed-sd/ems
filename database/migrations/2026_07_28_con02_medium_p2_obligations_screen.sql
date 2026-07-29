-- ═══════════════════════════════════════════════════════════════════════════
-- CON-02 · المستوى المتوسط — المرحلة ② : شاشةُ المصفوفة وفصلُ اليدين — 2026-07-28
-- ───────────────────────────────────────────────────────────────────────────
-- **البوابةُ الحاجبة (ق-24).** الشاشةُ أولًا، ثم يملأ البشرُ، ثم يُفعَّل القارئ.
-- ولا يُقلب `EMS_ATTRIBUTION_MATRIX` إلى `on` قبل أن تُملأ العقودُ وتُجاز —
-- فالعقدُ بلا مصفوفةٍ يُرفض `423` (ق-2) والعقودُ التسعةُ اليومَ بلا مصفوفة.
--
-- ═══ ما يضيفه هذا الملف ═══
--   ① **أعمدةُ الإجازة على `contract_obligations`** — ق-18 ينصّ أن «12 تملأ ·
--      19 تُجيز · **ولا تسري مصفوفةٌ حتى تُجاز**»، وجدولُ دفعة الأساس بُني بلا
--      أيِّ عمودِ إجازة. فبغيرِ هذه الأعمدة يكون فصلُ اليدين شعارًا بلا بنية:
--      المحلِّلُ (المرحلة ③) لا يملك ما يميّز به الصفَّ النافذَ من المسودة.
--   ② **تسجيلُ الشاشة** (الوحدة 145) بمنحٍ يُجسّد فصلَ اليدين في **طبقة المنح**
--      لا في الكود وحده — على قالب `2026_07_28_settlements_screen.sql` حرفيًّا:
--        · المبيعات (12)        → can_add  = **الملء والتعديل**
--        · مدير المالية (19)    → can_edit = **الإجازة**
--        · التشغيل (1) والمالي (17) ومحاسبُها (18) → عرضًا
--      فلا يجتمع الملءُ والإجازةُ لدورٍ واحد.
--
-- ═══ دلالةُ الحالة (مقصودةٌ ومغلقة) ═══
--   `draft`    : صفٌّ يملؤه 12 — قابلٌ للتعديل والحذف، **ولا يقرؤه محلِّل**.
--   `approved` : أجازه 19 — **نافذٌ وغيرُ قابلٍ للتعديل**. وتغييرُ الملتزم بعده
--                **صفٌّ جديدٌ بسريانه** لا تعديلُ القائم (§6 الملاحق: لا رجعية).
--   ولا حالةَ ثالثةٌ عمدًا: «مرفوض» يُعبَّر عنه بحذفٍ ناعمٍ للمسودة، فالرفضُ
--   ليس حالةً يعيش فيها صفٌّ بل نهايةَ مسودةٍ لم تُجَز.
--
-- إضافيٌّ محض: ثلاثةُ أعمدةٍ بقيمٍ افتراضيةٍ وصفوفُ تسجيلٍ بنمط
-- `INSERT ... WHERE NOT EXISTS` — ولا صفَّ بياناتٍ قائمٌ يتغيّر (الجدولُ فارغ).
-- الرجوع: إسقاطُ الأعمدة الثلاثة، وحذفُ صفوف الوحدة 145 من
--         `modules` و`role_permissions` و`nav_items`.
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── ① أعمدةُ الإجازة: «ولا تسري مصفوفةٌ حتى تُجاز» (ق-18) ────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'contract_obligations'
                  AND COLUMN_NAME = 'approval_state'),
    'ALTER TABLE `contract_obligations`
       ADD COLUMN `approval_state` ENUM(''draft'',''approved'') NOT NULL DEFAULT ''draft''
           COMMENT ''مسودةٌ يملؤها 12 · ومُجازةٌ يعتمدها 19 — والمحلِّلُ لا يقرأ إلا المُجاز (ق-18)'' AFTER `effect_on_billing`,
       ADD COLUMN `approved_by` INT NULL
           COMMENT ''مَن أجاز — الدور 19 حصرًا (تفرضه المنحُ والشاشة)'' AFTER `approval_state`,
       ADD COLUMN `approved_at` DATETIME NULL
           COMMENT ''لحظةُ الإجازة — وبها يصير الصفُّ نافذًا وغيرَ قابلٍ للتعديل'' AFTER `approved_by`,
       ADD KEY `ix_obligation_effective` (`client_contract_id`, `approval_state`, `valid_from`, `valid_to`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;


-- ── ② تسجيلُ شاشة المصفوفة — الوحدة 145 (بعد 144 «تسويات الموردين») ─────────
--    رقمٌ صريحٌ لا MAX+1 (عُرفُ المشروع: الوحدةُ رقمٌ ثابتٌ يُشار إليه في المنح).
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 145, 'مصفوفة التزامات العقد', 'Contracts/contract_obligations.php', 12, 0, 0, 'fa fa-scale-balanced', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/contract_obligations.php');

--    فصلُ اليدين في طبقة المنح: can_add = الملء · can_edit = الإجازة.
--    والشاشةُ تقرأ العمودين بهذا المعنى حرفيًّا كما تفعل `Suppliers/settlements.php`.
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 145, 1, r.a, r.e, r.d
  FROM (SELECT 12 AS rid, 1 AS a, 0 AS e, 1 AS d   -- المبيعات: تملأ وتحذف مسودتها ولا تُجيز
        UNION ALL SELECT 19, 0, 1, 0               -- مدير المالية: يُجيز ولا يملأ
        UNION ALL SELECT 1,  0, 0, 0               -- التشغيل: يقرأ (لوحةُ الإسناد تستند إليها)
        UNION ALL SELECT 17, 0, 0, 0
        UNION ALL SELECT 18, 0, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 145);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 145, 'مصفوفة التزامات العقد', 'Contracts/contract_obligations.php',
       'fa fa-scale-balanced', 47, NULL, 'Contracts/contract_obligations.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 19 UNION ALL SELECT 1
        UNION ALL SELECT 17 UNION ALL SELECT 18) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`module_id` = 145);
