-- ═══════════════════════════════════════════════════════════════════════════
-- H-12 · ترقيةُ دفتر الحدث إلى عقد FES §3.1/§7.2 — 2026-07-30
-- البطاقة: docs/specs/H-12_fes_event_contract.md
-- ───────────────────────────────────────────────────────────────────────────
-- **الفجوة المقيسة**: `fin_financial_events` بلا مفتاحٍ مركّب (سطر المستند ×
-- نسخته) ولا قفلٍ تفاؤلي ولا حالات FES الأربعَ عشرة ولا فترةٍ ولا استحقاقٍ ولا
-- طرفٍ موحّدٍ ولا تدقيقِ فاعلين — وآثارُ الحدث مبثوثةٌ أحداثًا منفصلةً بلا جدولٍ
-- يمثّلها (§3.2).
--
-- **قراراتُ التعبئة** (مقيسة — البطاقة §3):
--   • fes_status من `state` القائم بخريطةٍ حرفية (45 حدثًا: 34 مسودة · 1 مراجعة
--     مالية · 1 مدقَّق · 9 مرحَّل) + event_status='reversed' → Reversed.
--   • الطرفُ الموحّد: العمودُ الوحيدُ المعمور، وعند التعدد (3 أحداث إيرادٍ
--     قديمة) الأسبقيةُ لنوع الحدث (إيراد → عميل). صفرُ اختراع.
--   • الفترةُ من فترات `fin_financial_periods` الشهرية بتاريخ الوقوع (قيس:
--     45/45 تغطّيها فترات 2026).
--   • approved_by/posted_by للتاريخي تبقى NULL — الفاعلُ القديم غيرُ مسجَّلٍ
--     ولا يُخترع (عقيدة ⑦).
--   • الآثارُ تُبذر للأنواع القابلة للنسب الصادق فقط، والباقي (enterprise
--     ذو المروحة القائمة · مصروفاتٌ بلا أي مرجع) يُترك معلَنًا في البطاقة.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① أعمدةُ العقد على رأس الحدث (ALTER إضافي) ──────────────────────────────
ALTER TABLE `fin_financial_events`
  ADD COLUMN `source_line_id` INT NOT NULL DEFAULT 0
      COMMENT 'H-12 (FES §3.1): سطرُ المستند المصدر — 0 لمستندٍ بلا سطور'
      AFTER `entity_id`,
  ADD COLUMN `source_doc_version` INT NOT NULL DEFAULT 1
      COMMENT 'H-12: نسخةُ المستند المصدر — النسخةُ الأحدث تُنشئ حدثًا وتعلّم القديمَ Superseded'
      AFTER `source_line_id`,
  ADD COLUMN `event_version` INT NOT NULL DEFAULT 1
      COMMENT 'H-12 (FES §7.3): قفلٌ تفاؤلي — كلُّ انتقالٍ يفحصها ويرفعها، والمتزامنان: الأولُ يمضي والثاني Conflict'
      AFTER `schema_version`,
  ADD COLUMN `causation_id` VARCHAR(64) DEFAULT NULL
      COMMENT 'H-12 (FES §3.1): معرّفُ الحدث المسبِّب — خيطُ السببية (بجانب correlation_id خيطِ الترابط)'
      AFTER `correlation_id`,
  ADD COLUMN `fiscal_period_id` INT DEFAULT NULL
      COMMENT 'H-12: الفترةُ المالية للحدث — تُختم عند النشر، ولا نشرَ في فترةٍ مقفلة (إنفاذُه في M-39)'
      AFTER `occurred_at`,
  ADD COLUMN `due_date` DATE DEFAULT NULL
      COMMENT 'H-12 (FES §3.1): تاريخُ الاستحقاق — فهرسُ أعمار الذمم'
      AFTER `fiscal_period_id`,
  ADD COLUMN `party_type` VARCHAR(16) DEFAULT NULL
      COMMENT 'H-12 (FES §4.1): الطرفُ الموحّد — customer·supplier·operator·employee·owner_dept'
      AFTER `operator_employee_id`,
  ADD COLUMN `party_id` INT DEFAULT NULL
      COMMENT 'H-12: معرّفُ الطرف في جدوله بحسب party_type'
      AFTER `party_type`,
  ADD COLUMN `contract_line_id` INT DEFAULT NULL
      COMMENT 'H-12: بندُ العقد — FK يُربط عند بناء سجل العقود الموحّد (H-08)'
      AFTER `contract_id`,
  ADD COLUMN `approved_by` INT DEFAULT NULL
      COMMENT 'H-12 (FES §3.1): معتمِدُ الحدث — تدقيقُ الفاعلين'
      AFTER `created_by`,
  ADD COLUMN `approved_at` DATETIME DEFAULT NULL AFTER `approved_by`,
  ADD COLUMN `posted_by` INT DEFAULT NULL AFTER `approved_at`,
  ADD COLUMN `posted_at` DATETIME DEFAULT NULL AFTER `posted_by`,
  ADD COLUMN `fes_status` ENUM('Draft','Published','ValidationFailed','UnderReview',
      'ReturnedToSource','Rejected','Approved','PostingFailed','RetryPending',
      'Posted','Reversed','Superseded','CancelledBeforePosting','Closed')
      NOT NULL DEFAULT 'Draft'
      COMMENT 'H-12 (FES §7.2): آلةُ حالات الحدث الأربعَ عشرة — يحكمها EventStateMachine حصرًا'
      AFTER `event_status`,
  ADD INDEX `ix_ffe_fes_status` (`company_id`, `fes_status`),
  ADD INDEX `ix_ffe_party` (`party_type`, `party_id`),
  ADD INDEX `ix_ffe_due` (`company_id`, `due_date`),
  ADD INDEX `ix_ffe_causation` (`causation_id`),
  ADD INDEX `ix_ffe_source_line` (`company_id`, `entity_type`, `entity_id`, `source_line_id`, `source_doc_version`),
  ADD CONSTRAINT `fk_ffe_period` FOREIGN KEY (`fiscal_period_id`)
      REFERENCES `fin_financial_periods` (`id`);

-- ── ② جدولُ آثار الحدث المستقل (FES §3.2 — جديدٌ إضافي) ─────────────────────
CREATE TABLE IF NOT EXISTS `fin_event_effects` (
  `effect_id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`       INT NOT NULL,
  `event_id`         INT NOT NULL,
  `effect_type`      ENUM('client_receivable','supplier_accrual','operator_due',
                          'project_cost','equip_cost','payment','receipt','settlement',
                          'depreciation','tax_return','finance_installment','adjustment_reversal')
                     NOT NULL COMMENT 'FES §4.1: القيمُ الحصرية الاثنتا عشرة',
  `party_type`       VARCHAR(16) DEFAULT NULL,
  `party_id`         INT DEFAULT NULL,
  `contract_line_id` INT DEFAULT NULL,
  `amount`           DECIMAL(18,2) NOT NULL,
  `base_amount`      DECIMAL(18,2) DEFAULT NULL COMMENT 'المعادلُ الموحّد — NULL = سعرٌ غيرُ مُدخَل (معلَن)',
  `status`           ENUM('active','reversed') NOT NULL DEFAULT 'active'
                     COMMENT 'الأثرُ يُبطل بعكس حدثه لا بمحوه',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`effect_id`),
  UNIQUE KEY `uq_effect` (`event_id`, `effect_type`, `party_type`, `party_id`, `contract_line_id`),
  KEY `ix_eff_company_party` (`company_id`, `party_type`, `party_id`),
  KEY `ix_eff_type` (`company_id`, `effect_type`),
  CONSTRAINT `fk_eff_event` FOREIGN KEY (`event_id`)
      REFERENCES `fin_financial_events` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='H-12 (FES §3.2): آثارُ الحدث — الحدثُ الواحد قد يولّد آثارًا لعدة أطراف';

-- ── ③ التعبئةُ الرجعية ──────────────────────────────────────────────────────
-- fes_status من الحالة القائمة (خريطةٌ حرفية — الحالاتُ العشرُ القديمة تبقى تعمل)
UPDATE `fin_financial_events`
   SET `fes_status` = CASE
         WHEN `event_status` = 'reversed' THEN 'Reversed'
         WHEN `state` = 'draft'         THEN 'Draft'
         WHEN `state` IN ('dept_review','dept_approved','fin_review','audited') THEN 'UnderReview'
         WHEN `state` = 'approved'      THEN 'Approved'
         WHEN `state` IN ('posted','settled') THEN 'Posted'
         WHEN `state` = 'rejected'      THEN 'Rejected'
         WHEN `state` = 'closed'        THEN 'Closed'
         ELSE 'Draft' END
 WHERE `fes_status` = 'Draft';

-- الطرفُ الموحّد: العمودُ الوحيدُ المعمور — وعند التعدد الأسبقيةُ لنوع الحدث
UPDATE `fin_financial_events`
   SET `party_type` = CASE
         WHEN `event_type` IN ('revenue','receivable') AND COALESCE(`customer_entity_id`,0) > 0 THEN 'customer'
         WHEN `event_type` = 'payroll' AND COALESCE(`operator_employee_id`,0) > 0 THEN 'operator'
         WHEN COALESCE(`supplier_entity_id`,0) > 0 AND COALESCE(`customer_entity_id`,0) = 0
              AND COALESCE(`operator_employee_id`,0) = 0 THEN 'supplier'
         WHEN COALESCE(`customer_entity_id`,0) > 0 AND COALESCE(`supplier_entity_id`,0) = 0
              AND COALESCE(`operator_employee_id`,0) = 0 THEN 'customer'
         WHEN COALESCE(`operator_employee_id`,0) > 0 AND COALESCE(`supplier_entity_id`,0) = 0
              AND COALESCE(`customer_entity_id`,0) = 0 THEN 'operator'
         WHEN COALESCE(`supplier_entity_id`,0) > 0 THEN 'supplier'
         ELSE NULL END
 WHERE `party_type` IS NULL;

UPDATE `fin_financial_events`
   SET `party_id` = CASE `party_type`
         WHEN 'customer' THEN `customer_entity_id`
         WHEN 'supplier' THEN `supplier_entity_id`
         WHEN 'operator' THEN `operator_employee_id`
         ELSE NULL END
 WHERE `party_id` IS NULL AND `party_type` IS NOT NULL;

-- الفترةُ المالية الشهرية بتاريخ الوقوع (وإلا تاريخ الإنشاء)
UPDATE `fin_financial_events` e
  JOIN `fin_financial_periods` p
    ON p.`company_id` = e.`company_id` AND p.`period_type` = 'month'
   AND DATE(COALESCE(e.`occurred_at`, e.`created_at`)) BETWEEN p.`start_date` AND p.`end_date`
   SET e.`fiscal_period_id` = p.`id`
 WHERE e.`fiscal_period_id` IS NULL;

-- ── ④ بذرُ الآثار للأنواع القابلة للنسب الصادق (والباقي معلَنٌ في البطاقة) ────
INSERT INTO `fin_event_effects`
    (`company_id`, `event_id`, `effect_type`, `party_type`, `party_id`,
     `contract_line_id`, `amount`, `base_amount`, `status`)
SELECT e.`company_id`, e.`id`,
       CASE
         WHEN e.`event_type` IN ('revenue','receivable') THEN 'client_receivable'
         WHEN e.`event_type` = 'payable' THEN 'supplier_accrual'
         WHEN e.`event_type` = 'payroll' THEN 'operator_due'
         WHEN e.`event_type` = 'settlement' THEN 'settlement'
         WHEN e.`event_type` = 'expense' AND COALESCE(e.`supplier_entity_id`,0) > 0 THEN 'supplier_accrual'
         WHEN e.`event_type` = 'expense' AND COALESCE(e.`project_id`,0) > 0 THEN 'project_cost'
         WHEN e.`event_type` = 'expense' AND COALESCE(e.`equipment_id`,0) > 0 THEN 'equip_cost'
       END,
       e.`party_type`, e.`party_id`, e.`contract_line_id`, e.`amount`, NULL,
       IF(e.`event_status` = 'reversed', 'reversed', 'active')
  FROM `fin_financial_events` e
 WHERE COALESCE(e.`is_deleted`, 0) = 0
   AND ( e.`event_type` IN ('revenue','receivable','payable','payroll','settlement')
      OR (e.`event_type` = 'expense'
          AND (COALESCE(e.`supplier_entity_id`,0) > 0
            OR COALESCE(e.`project_id`,0) > 0
            OR COALESCE(e.`equipment_id`,0) > 0)) )
   AND NOT EXISTS (SELECT 1 FROM `fin_event_effects` x WHERE x.`event_id` = e.`id`);
