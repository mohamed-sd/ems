-- ═══════════════════════════════════════════════════════════════════════════
-- M-09 · شروطُ تعديل السعر (وقودٌ · تضخمٌ · صرف) — 2026-07-30
-- البطاقة: docs/specs/M-09_price_adjustment_terms.md
-- المصدر: CON-02 §2-③ (الثلاثةُ المسمّاةُ نصًّا) · §6-الملاحق («تغييرُ السعر
--         ملحقٌ بسريان — والوقائعُ السابقة بنصّها النافذ يومَها · لا رجعية»)
-- ───────────────────────────────────────────────────────────────────────────
-- **بناءٌ بجانب القائم**: `contractequipments.equip_price` يبقى **الأساس**
-- ولا يُمسّ أبدًا — والتعديلُ طبقةٌ بتاريخها فوقه، فسعرُ الواقعة = الأساسُ +
-- المراجعاتُ المعتمَدةُ الساريةُ إلى يومها. وهكذا تصير «لا رجعية» حسابًا لا وعدًا.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① الشرطُ كما يُكتب في العقد (CON-02 §2-③) ──────────────────────────────
-- المحفِّزاتُ **الثلاثةُ المسمّاةُ نصًّا** لا أكثر: وقودٌ · تضخمٌ · صرف.
-- والمعادلةُ مكتوبةٌ بأعمدةٍ لا بصيغةٍ حرة: عتبةٌ تُفعّل · نسبةُ تمريرٍ تُمرّر ·
-- سقفٌ يقصّ — فكلُّ رقمٍ في النتيجة يُردّ إلى بندٍ في العقد.
CREATE TABLE IF NOT EXISTS `contract_price_terms` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `contract_id` INT NOT NULL COMMENT 'عقدُ العميل — منبعُ التسعير (CON-02 §1)',
  `contract_item_id` INT NULL DEFAULT NULL COMMENT 'سطرُ contractequipments — NULL = كلُّ بنود العقد',
  `trigger_kind` ENUM('fuel','inflation','fx') NOT NULL COMMENT 'وقودٌ · تضخمٌ · صرف — قائمةُ §2-③ نصًّا',
  `index_code` VARCHAR(32) NOT NULL COMMENT 'رمزُ المؤشر — وللصرف رمزُ العملة (المصدرُ fin_fx_rates)',
  `base_index` DECIMAL(20,8) NOT NULL COMMENT 'القيمةُ المرجعيةُ يومَ التعاقد',
  `base_date` DATE NULL DEFAULT NULL,
  `threshold_percent` DECIMAL(6,3) NOT NULL DEFAULT 0.000 COMMENT 'عتبةُ التفعيل — دونها لا تعديل',
  `pass_through_percent` DECIMAL(6,3) NOT NULL DEFAULT 100.000 COMMENT 'كم من تغيّر المؤشر يدخل السعر',
  `cap_percent` DECIMAL(6,3) NULL DEFAULT NULL COMMENT 'سقفُ المراجعة الواحدة — NULL = بلا سقفٍ مكتوب',
  `periodicity` ENUM('monthly','quarterly','semiannual','annual') NOT NULL DEFAULT 'quarterly',
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  `state` ENUM('active','ended') NOT NULL DEFAULT 'active',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_term_scope` (`contract_id`, `contract_item_id`, `trigger_kind`, `valid_from`),
  KEY `ix_price_term_co` (`company_id`, `contract_id`, `state`),
  CONSTRAINT `fk_price_term_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_price_term_base` CHECK (`base_index` > 0),
  CONSTRAINT `ck_price_term_pass` CHECK (`pass_through_percent` > 0 AND `pass_through_percent` <= 100),
  CONSTRAINT `ck_price_term_threshold` CHECK (`threshold_percent` >= 0),
  CONSTRAINT `ck_price_term_cap` CHECK (`cap_percent` IS NULL OR `cap_percent` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ② قراءةُ المؤشر **بمرجع مستندها** ─────────────────────────────────────
-- `source_ref` NOT NULL: «لا رقمَ بلا مرجع» قيدًا بنيويًّا لا فحصَ شاشة —
-- فمؤشرُ وقودٍ أو تضخمٍ بلا مستندٍ رقمٌ مخترعٌ يحرّك مالًا.
-- **ولا صفَّ للصرف هنا**: مصدرُه `fin_fx_rates` وحدَه (لا مصدرَي حقيقةٍ لرقمٍ واحد).
CREATE TABLE IF NOT EXISTS `contract_price_index_readings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `index_code` VARCHAR(32) NOT NULL,
  `reading_date` DATE NOT NULL,
  `value` DECIMAL(20,8) NOT NULL,
  `source_ref` VARCHAR(160) NOT NULL COMMENT 'مرجعُ المستند — إلزاميٌّ بنيويًّا',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_index_reading` (`company_id`, `index_code`, `reading_date`),
  CONSTRAINT `ck_price_index_value` CHECK (`value` > 0),
  CONSTRAINT `ck_price_index_ref` CHECK (CHAR_LENGTH(TRIM(`source_ref`)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ سجلُّ المراجعات — Insert-only بعطالته ────────────────────────────────
-- UQ(شرط × مفتاح الدورة): إعادةُ التقييم للدورة نفسِها **عاطلةٌ** فلا تولّد
-- ملحقًا ثانيًا — نمطُ N-01 (مفتاحُ منع التكرار في المنبع لا فحصٌ تطبيقي).
-- ولكلِّ نتيجةٍ اسمُها: المطبَّقُ والمقصوصُ ودونَ العتبة و**بلا قراءةٍ** —
-- فالامتناعُ يُسجَّل بسببه ولا يختفي.
CREATE TABLE IF NOT EXISTS `contract_price_revisions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `term_id` INT NOT NULL,
  `contract_id` INT NOT NULL,
  `contract_item_id` INT NULL DEFAULT NULL,
  `period_key` VARCHAR(16) NOT NULL COMMENT 'مفتاحُ الدورة (2026-07 · 2026-Q3 · 2026-H1 · 2026)',
  `as_of_date` DATE NOT NULL,
  `effective_from` DATE NOT NULL COMMENT 'من هنا يسري السعرُ الجديد — ولا رجعيةَ قبله',
  `index_value` DECIMAL(20,8) NULL DEFAULT NULL COMMENT 'NULL = لا قراءةَ (مُعلَنٌ لا مخترع)',
  `index_source` VARCHAR(160) NULL DEFAULT NULL,
  `delta_percent` DECIMAL(10,4) NULL DEFAULT NULL COMMENT 'فارقُ المؤشر عن أساسه',
  `applied_percent` DECIMAL(10,4) NULL DEFAULT NULL COMMENT 'بعد التمرير والسقف',
  `old_price` DECIMAL(14,4) NULL DEFAULT NULL,
  `new_price` DECIMAL(14,4) NULL DEFAULT NULL,
  `outcome` ENUM('amended','below_threshold','capped','no_reading','no_base_price') NOT NULL,
  `amendment_id` INT UNSIGNED NULL DEFAULT NULL,
  `approved_by` INT NULL DEFAULT NULL,
  `approved_at` DATETIME NULL DEFAULT NULL,
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_revision_period` (`term_id`, `period_key`),
  KEY `ix_price_revision_live` (`company_id`, `contract_id`, `effective_from`),
  CONSTRAINT `fk_price_revision_term` FOREIGN KEY (`term_id`) REFERENCES `contract_price_terms` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_price_revision_amd` FOREIGN KEY (`amendment_id`) REFERENCES `contract_amendments` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ④ تسجيلُ شاشة «شروط تعديل السعر» — الوحدة 154 (بعد 153) ───────────────
-- الملكيةُ للعقود (12 — مالكةُ المصفوفة 145 والجزاءات 147) والمشاريعُ (1) عرضًا.
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 154, 'شروط تعديل السعر', 'Contracts/price_terms.php', 12, 0, 0, 'fa fa-arrow-trend-up', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/price_terms.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 154, 1, r.a, r.e, 0
  FROM (SELECT 12 AS rid, 1 AS a, 1 AS e      -- العقود: تكتب الشرطَ وتعتمد المراجعة
        UNION ALL SELECT 1,  0, 0             -- المشاريع: عرضًا
        UNION ALL SELECT 17, 0, 0) r          -- المالية: عرضًا (تقرأ أثرَ السعر)
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 154);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 154, 'شروط تعديل السعر', 'Contracts/price_terms.php',
       'fa fa-arrow-trend-up', 53, NULL, 'Contracts/price_terms.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 1 UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/price_terms.php');
