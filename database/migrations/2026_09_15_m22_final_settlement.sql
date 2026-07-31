-- ═══════════════════════════════════════════════════════════════════════════
-- M-22 · التصفيةُ المحسوبة لإنهاء عقد الموظف — 2026-07-31
-- البطاقة: docs/specs/M-22_final_settlement.md
-- المصدر: ENT-01 §5-التصفية («إنهاءُ العقد (CON-01 §4) يفتح **دورةَ تصفيةٍ
--         خاصة**: **المستحقُّ حتى تاريخ الأثر** · **رصيدُ الإجازات** · **نهايةُ
--         الخدمة بقاعدتها من اللقطة** · **تسويةُ السلف والعهد** — بحدثٍ ماليٍّ
--         واحدٍ بمفتاح (**العقد × التصفية**) لا يتكرر») · §6 (شاشةُ التصفية:
--         «بنودُ التصفية **محسوبةً من اللقطة** · الرصيدُ الصافي بعد المقاصّة»)
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: `worker_settlement` **إدخالٌ يدويٌّ خالص** (`net_amount`
-- عمودٌ يُكتب و`settlement_basis` نصٌّ حر · صفرُ صف)، و`end_of_service` في
-- `fin_dues.due_type` **تصنيفُ دفعةٍ لا احتساب**.
-- والمصادرُ الأربعةُ **حيّةٌ قائمة**: `fin_dues` · `worker_leave_absence` ·
-- **`pay_components` بعلَمَي `in_eos` و`in_leave_pay`** (H-08) · و`employee_advances`.
-- **فالناقصُ قاعدتا الأيام والمحرّك** لا مصادرُ القياس.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① قاعدتا الأيام على العقد — «بقاعدتها» (§5) ────────────────────────────
ALTER TABLE `employee_contracts`
  ADD COLUMN `eos_days_per_year` DECIMAL(5,2) NULL DEFAULT NULL
      COMMENT 'أيامُ نهاية الخدمة لكل سنةِ خدمة — NULL = لم تُكتب فلا تُحتسب (تُعلَن)' AFTER `currency`,
  ADD COLUMN `leave_days_per_year` DECIMAL(5,2) NULL DEFAULT NULL
      COMMENT 'أيامُ الإجازة المستحقة لكل سنة — NULL = لم تُكتب فلا تُحتسب' AFTER `eos_days_per_year`;

ALTER TABLE `employee_contracts`
  ADD CONSTRAINT `ck_ec_eos_days` CHECK (`eos_days_per_year` IS NULL OR `eos_days_per_year` > 0),
  ADD CONSTRAINT `ck_ec_leave_days` CHECK (`leave_days_per_year` IS NULL OR `leave_days_per_year` > 0);

-- ── ② رأسُ التصفية — «بمفتاح (العقد × التصفية)» ────────────────────────────
CREATE TABLE IF NOT EXISTS `employee_final_settlements` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `contract_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `effective_date` DATE NOT NULL COMMENT 'تاريخُ الأثر — «المستحقُّ **حتى تاريخ الأثر**»',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `service_years` DECIMAL(6,3) NOT NULL DEFAULT 0,
  `dues_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `leave_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `eos_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `advances_offset` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'موجبٌ دائمًا — ويُطرح في الصافي',
  `advances_remaining` DECIMAL(18,2) NOT NULL DEFAULT 0
      COMMENT 'ما لم تسعه المقاصّة — «لا يُقاصّ أكثرُ من المستحق» ويبقى رصيدًا مفتوحًا يُعلَن',
  `net_amount` DECIMAL(18,2) NOT NULL DEFAULT 0
      COMMENT '**محسوبٌ لا مُدخَل**: المستحقُّ + الإجازةُ + نهايةُ الخدمة − السلف',
  `recognized_amount` DECIMAL(18,2) NOT NULL DEFAULT 0
      COMMENT 'ما تعترف به التصفيةُ **جديدًا** (إجازةٌ + نهايةُ خدمة) — والمستحقُّ السابقُ اعتُرف به في مصدره',
  `snapshot_id` INT NULL DEFAULT NULL COMMENT 'لقطةُ العقد التي احتُسب منها (H-11) — «من اللقطة» إسنادًا لا دعوى',
  `snapshot_fingerprint` VARCHAR(64) NULL DEFAULT NULL COMMENT 'بصمتُها ساعةَ الاحتساب — يُكشف أيُّ تلاعبٍ بمقارنتها',
  `net_due_ref` INT NULL DEFAULT NULL COMMENT 'مرجعُ الحدث المالي الواحد (fin_dues) — «لا يتكرر»',
  `basis_json` TEXT NULL DEFAULT NULL COMMENT 'لقطةُ القواعد والأسس لحظةَ الاحتساب — لا اشتقاقٌ لاحق',
  `state` ENUM('draft','approved','cancelled') NOT NULL DEFAULT 'draft',
  `clearance_doc` VARCHAR(120) NULL DEFAULT NULL COMMENT 'مرفقُ الإخلاء (§6)',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `prepared_by` INT NULL DEFAULT NULL,
  `approved_by` INT NULL DEFAULT NULL,
  `approved_at` DATETIME NULL DEFAULT NULL,
  `cancel_reason` VARCHAR(255) NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_final_settlement` (`contract_id`) COMMENT '«بمفتاح (العقد × التصفية) لا يتكرر»',
  KEY `ix_final_settlement` (`company_id`, `employee_id`, `state`),
  CONSTRAINT `fk_fs_contract` FOREIGN KEY (`contract_id`)
      REFERENCES `employee_contracts` (`id`) ON DELETE CASCADE,
  -- **فصلُ اليدين بنيويًّا**: لا يعتمد المرءُ ما أعدّ
  CONSTRAINT `ck_fs_hands` CHECK (
      `approved_by` IS NULL OR `prepared_by` IS NULL OR `approved_by` <> `prepared_by`),
  -- والاعتمادُ يلزمه **مرفقُ إخلاءٍ ومعتمِدٌ** (§6)
  CONSTRAINT `ck_fs_approved` CHECK (
      `state` <> 'approved'
      OR (`approved_by` IS NOT NULL AND `clearance_doc` IS NOT NULL AND `clearance_doc` <> '')),
  CONSTRAINT `ck_fs_cancel` CHECK (
      `state` <> 'cancelled' OR (`cancel_reason` IS NOT NULL AND `cancel_reason` <> '')),
  CONSTRAINT `ck_fs_offset` CHECK (`advances_offset` >= 0 AND `advances_remaining` >= 0),
  -- «الرصيدُ الصافي **بعد المقاصّة**» ولا يكون سالبًا: المقاصّةُ محدودةٌ بالمستحق
  CONSTRAINT `ck_fs_net` CHECK (`net_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ بنودُ التصفية — «كلُّ رقمٍ بمصدره» ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `employee_final_settlement_lines` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `settlement_id` INT NOT NULL,
  `line_type` ENUM('dues','leave','eos','advance_offset') NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `qty` DECIMAL(12,3) NULL DEFAULT NULL COMMENT 'أيامٌ أو سنواتٌ بحسب البند',
  `rate` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'الأجرُ اليوميُّ المحسوبُ من الأساس',
  `amount` DECIMAL(18,2) NOT NULL,
  `computable` TINYINT NOT NULL DEFAULT 1 COMMENT '0 = بلا قاعدةٍ مكتوبةٍ — يُعلَن ولا يُقدَّر',
  `source_note` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fs_line` (`settlement_id`, `line_type`),
  CONSTRAINT `fk_fs_line` FOREIGN KEY (`settlement_id`)
      REFERENCES `employee_final_settlements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ④ الذمّةُ تُسمّى باسمها — «مصدرُ الخصم/الاستحقاق يُسمّى» (نظيرُ M-18) ───
-- بلا هذه القيمة تُكتب ذمّةُ التصفية تحت `settlement` فتختلط بتسويات المسيّر،
-- أو تحت `other` فتضيع — والاسمُ الصريحُ هو ما يجعل الأثرَ قابلًا للتتبّع.
ALTER TABLE `fin_dues`
  MODIFY COLUMN `source_doc_type` ENUM(
    'proc_issue','mnt_order','transfer_order','penalty_assessment','settlement',
    'supplier_closure','employee_closure','legacy_no_ref','pending_source'
  ) NULL DEFAULT NULL;

-- ── ⑤ تسجيلُ شاشة «تصفية إنهاء خدمة الموظف» — الوحدة 167 ───────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 167, 'تصفية إنهاء خدمة الموظف', 'Workforce/final_settlement.php', 4, 0, 0, 'fa fa-user-minus', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Workforce/final_settlement.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 167, 1, r.a, r.e, 0
  FROM (SELECT 4 AS rid, 1 AS a, 0 AS e
        UNION ALL SELECT 17, 0, 1) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 167);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 167, 'تصفية إنهاء خدمة الموظف', 'Workforce/final_settlement.php',
       'fa fa-user-minus', 66, NULL, 'Workforce/final_settlement.php', 1
  FROM (SELECT 4 AS rid UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Workforce/final_settlement.php');
