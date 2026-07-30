-- ═══════════════════════════════════════════════════════════════════════════
-- M-19 · وثائقُ المورد والحسابُ البنكيُّ الموثَّق وسجلُّ التدقيق — 2026-07-31
-- البطاقة: docs/specs/M-19_supplier_documents.md
-- المصدر: UX-05 §5.1-① («الهوية والوثائق **بتواريخ صلاحيتها** (**تنبيهٌ آلي
--         قبل الانتهاء**) — والحقولُ النظامية **أعمدةٌ واجبة** (السجل التجاري ·
--         الضريبي · **الحساب البنكي الموثَّق**)») · ENT-02 §7 (المستندُ شرطٌ)
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: `suppliers` يحمل `commercial_registration` و
-- `identity_expiry_date` **فقط** — ولا رقمَ ضريبيًّا ولا حسابًا بنكيًّا
-- ألبتة، ولا وثيقةَ موردٍ واحدةً بتاريخ صلاحيةٍ وتنبيه.
-- و`equipment_documents` **جدولُ وثائقٍ عامٌّ قائم** (1886 صفًّا · `subject_type`
-- بمحورين · `alert_days` وفهرسُ الانتهاء) — فالمصيرُ **توسيعُه بمحورٍ ثالث**
-- لا جدولٌ ثانٍ لوثائقَ هي وثائق.
-- وسجلُّ التدقيق: `activity_logs` **قائمٌ ومكتوبٌ فيه** عبر `ems_audit_change`
-- (N-02) — فـ`supplier_audit_log` **قراءةٌ عليه** لا جدولٌ ثانٍ لسجلٍّ واحد.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① محورٌ ثالثٌ لجدول الوثائق العام + نوعا المورد النظاميان ──────────────
ALTER TABLE `equipment_documents`
  MODIFY COLUMN `subject_type` ENUM('equipment','operator','supplier')
      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'equipment'
      COMMENT 'محورُ الوثيقة — والموردُ محورٌ ثالثٌ (M-19) لا جدولٌ ثانٍ',
  MODIFY COLUMN `doc_type` ENUM('استمارة','تأمين','فحص دوري','رخصة قيادة','رخصة تشغيل',
      'تصريح','هوية','جواز سفر','عقد عمل','سجل تجاري','شهادة ضريبية','شهادة بنكية','أخرى')
      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
      COMMENT 'UX-10 §8.1 + وثائقُ الأفراد + **وثائقُ المورد النظامية** (UX-05 §5.1-①)';

-- ── ② الحقولُ النظاميةُ الواجبة على المورد ─────────────────────────────────
ALTER TABLE `suppliers`
  ADD COLUMN `tax_number` VARCHAR(100) NULL DEFAULT NULL
      COMMENT 'الرقمُ الضريبي — حقلٌ نظاميٌّ واجب (UX-05 §5.1-①)' AFTER `commercial_registration`,
  ADD COLUMN `bank_name` VARCHAR(150) NULL DEFAULT NULL AFTER `tax_number`,
  ADD COLUMN `bank_account_no` VARCHAR(60) NULL DEFAULT NULL AFTER `bank_name`,
  ADD COLUMN `bank_iban` VARCHAR(60) NULL DEFAULT NULL AFTER `bank_account_no`,
  ADD COLUMN `bank_doc_ref` VARCHAR(120) NULL DEFAULT NULL
      COMMENT 'مستندُ التوثيق (شهادةٌ بنكيةٌ أو شيكٌ ملغًى) — **توثيقٌ بلا مستندٍ دعوى**' AFTER `bank_iban`,
  ADD COLUMN `bank_verified_at` DATETIME NULL DEFAULT NULL AFTER `bank_doc_ref`,
  ADD COLUMN `bank_verified_by` INT NULL DEFAULT NULL AFTER `bank_verified_at`;

-- **التوثيقُ يلزمه حسابٌ ومستند**: «الحساب البنكي **الموثَّق**» — وتوثيقٌ بلا
-- رقمِ حسابٍ أو بلا مستندٍ وسمٌ في خانةٍ لا توثيق.
ALTER TABLE `suppliers`
  ADD CONSTRAINT `ck_sup_bank_verified` CHECK (
      `bank_verified_at` IS NULL
      OR (`bank_account_no` IS NOT NULL AND `bank_account_no` <> ''
          AND `bank_doc_ref` IS NOT NULL AND `bank_doc_ref` <> ''));

-- ── ③ تسجيلُ شاشة «وثائق المورد وحسابه البنكي» — الوحدة 163 ────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 163, 'وثائق المورد وحسابه البنكي', 'Suppliers/supplier_documents.php', 2, 0, 0, 'fa fa-id-card', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Suppliers/supplier_documents.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 163, 1, r.a, r.e, 0
  FROM (SELECT 2  AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 17, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 163);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 163, 'وثائق المورد وحسابه البنكي', 'Suppliers/supplier_documents.php',
       'fa fa-id-card', 62, NULL, 'Suppliers/supplier_documents.php', 1
  FROM (SELECT 2 AS rid UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Suppliers/supplier_documents.php');
