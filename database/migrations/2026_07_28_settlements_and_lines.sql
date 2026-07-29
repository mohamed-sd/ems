-- ═══════════════════════════════════════════════════════════════════════════
-- التسوية الموحّدة: المورد والعامل بخدمةٍ واحدة — 2026-07-28
-- ───────────────────────────────────────────────────────────────────────────
-- UX-02 §15.3 آلةُ الحالات نصًّا: `— → Draft (توليد)` ثم `Draft → Review` ثم
-- `Review → (بندٌ Objected)` ثم `Review → Approved → PaymentRequested` ثم
-- `PaymentRequested → Paid`. و§15.4: «مرجعُ التسوية إلزاميٌّ في طلب دفع الأطراف»
-- و«لا اعتمادَ ذاتٍ: created_by ≠ approver بنيويًّا».
-- وUX-05 §2.2: «الاستحقاق الأولي ← التحميلات الست … كلٌّ من مصدره المرجعي آليًّا».
--
-- **قرارات المالك (2026-07-28):**
--   ① الصافي السالب → **تُفتح ذمّةٌ مدينةٌ على الطرف** (لا ترحيلٌ ولا إهمال).
--      والمقيسُ أن هذه حالةٌ **حيّةٌ اليوم**: المورد 1 مستحقُّه 59,400 وعليه
--      تحميلُ وقودٍ 120,000 ⇒ صافيه −60,600. فالسالبُ ليس فرضًا نظريًّا.
--   ② الإعدادُ لإدارة الموردين (2) والإجازةُ لمدير الإدارة المالية (19) — فصلُ يدين.
--   ③ الإرثُ المدفوعُ بلا تسوية يُوسم ويُستثنى — لا تُلفَّق له تسويةٌ رجعية.
--   ④ يُبنى الآن ويُدخَل سعرُ الجنيه لاحقًا فتُقيَّم الجنيهيةُ تلقائيًّا.
--
-- **مصدرُ البنود (مقيسٌ لا مفترَض)**: `fin_dues` هو دفترُ الطرف فعلًا —
-- `direction='credit'` استحقاقٌ له و`'debit'` تحميلٌ عليه (وتحميلُ الوقود
-- مسجَّلٌ فيه اليوم بـ120,000)، و`period_ref` يجمع الفترة. فالتسويةُ تقرأ منه
-- ومن مصدرَي التحميل المباشرَين (صرفُ القطع وأمرُ الصيانة) بعمودِ التحميل الصريح.
--
-- **الأربعةُ بلا مصدر** (نقل · سلف · جزاءات — والوقودُ يدويٌّ اليوم): تدخل
-- بابَها الطبيعيَّ `fin_dues` بـ`direction='debit'` ونوعِها — فالهيكلُ مفتوحٌ
-- بلا قسمٍ فارغٍ في الشاشة (قرارُ النطاق 2026-07-28).
--
-- إضافيٌّ محض: جدولان جديدان وأعمدةٌ Nullable — ولا عمودَ قائمٌ يُمسّ.
-- الرجوع: إسقاطُ الجدولين (البنودُ ثم الرأس) والأعمدةِ المضافة.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `settlements` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `company_id`      INT NOT NULL COMMENT 'عزل المستأجر',
  `settlement_no`   VARCHAR(32) NOT NULL COMMENT 'STL-سنة-رقم',
  `party_type`      ENUM('supplier','employee') NOT NULL
                    COMMENT 'الخدمةُ واحدةٌ للطرفين (UX-02 §15.3) — والعاملُ توأمُ المورد',
  `party_ref`       INT NOT NULL COMMENT 'suppliers.id أو employees.id بحسب النوع',
  `party_name`      VARCHAR(191) NULL COMMENT 'لقطةُ الاسم وقتَ التوليد — للكشف التاريخي',
  `period_from`     DATE NOT NULL,
  `period_to`       DATE NOT NULL,

  `currency`        VARCHAR(8) NOT NULL COMMENT 'عملةُ التسوية — كلُّ بنودها بها',
  `fx_rate`         DECIMAL(20,8) NULL COMMENT 'سعرُ الصرف لعملة الأساس (FES §3.3)',
  `base_amount`     DECIMAL(18,2) NULL COMMENT 'صافي التسوية بعملة الأساس — NULL أي بانتظار سعر',

  `gross_amount`    DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الاستحقاق الأولي (Σ البنود المستحقة)',
  `charges_amount`  DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الσ التحميلات (موجبةً)',
  `net_amount`      DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الصافي = الأولي − التحميلات (قد يكون سالبًا)',
  `net_direction`   ENUM('payable','receivable') NOT NULL DEFAULT 'payable'
                    COMMENT 'payable=له علينا · receivable=علينا له دَينٌ (الصافي سالب — قرارُ المالك ①)',

  `state`           ENUM('draft','review','approved','payment_requested','paid','cancelled')
                    NOT NULL DEFAULT 'draft'
                    COMMENT 'آلةُ UX-02 §15.3 بحروفها — والاعتراضُ وسمُ بندٍ لا حالةَ رأس',
  `open_objections` INT NOT NULL DEFAULT 0 COMMENT 'عدّادُ البنود المعترَض عليها المفتوحة (§15.3)',

  `payment_request_id` INT NULL COMMENT 'طلبُ الدفع المولَّد آليًّا عند الاعتماد (§15.3)',
  `receivable_due_id`  INT NULL COMMENT 'الذمّةُ المدينة المولَّدة حين الصافي سالب',
  `event_id`           INT NULL COMMENT 'حدثُ FES بمفتاح رقمها (§15.3)',

  `prepared_by`     INT NULL, `prepared_at` DATETIME NULL,
  `approved_by`     INT NULL, `approved_at` DATETIME NULL,
  `paid_at`         DATETIME NULL,
  `notes`           VARCHAR(255) NULL,

  `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`      DATETIME NULL,
  `deleted_by`      INT NULL,
  `created_by`      INT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settlement_no` (`company_id`, `settlement_no`),
  UNIQUE KEY `uq_settlement_party_period`
      (`company_id`, `party_type`, `party_ref`, `period_from`, `period_to`)
      COMMENT 'تسويةٌ واحدةٌ لكل (طرف × فترة) — إعادةُ التوليد ترجع 409 بمرجع القائم (§15.4)',
  KEY `ix_settlement_state` (`state`),
  KEY `ix_settlement_party` (`party_type`, `party_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='تسويةُ الطرف: الاستحقاق الأولي ← التحميلات ← الصافي (UX-02 §15.3 · UX-05 §2.2)';

CREATE TABLE IF NOT EXISTS `settlement_lines` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `company_id`     INT NOT NULL,
  `settlement_id`  INT NOT NULL,
  `line_kind`      ENUM('entitlement','charge') NOT NULL
                   COMMENT 'entitlement=مستحقٌّ له · charge=تحميلٌ عليه',
  `charge_type`    VARCHAR(20) NULL
                   COMMENT 'للتحميل: fuel · parts · maintenance · transport · advance · penalty',
  `source_kind`    VARCHAR(20) NOT NULL
                   COMMENT 'مصدرُ البند: due (دفتر الطرف) · parts (صرف) · maintenance (أمر)',
  `source_ref`     VARCHAR(60) NOT NULL COMMENT 'معرّفُ الأصل — به يُفتح المستندُ الأصلي',
  `description`    VARCHAR(255) NULL,
  `work_date`      DATE NULL COMMENT 'تاريخُ الواقعة — به يُختار سعرُ الصرف',
  `amount`         DECIMAL(18,2) NOT NULL COMMENT 'المبلغ بعملته (موجبٌ دائمًا — والاتجاهُ من line_kind)',
  `currency`       VARCHAR(8) NOT NULL,
  `fx_rate`        DECIMAL(20,8) NULL,
  `base_amount`    DECIMAL(18,2) NULL,

  `objected`       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'اعتراضُ الطرف — والتسويةُ لا تتجمد (§15.3)',
  `objection_note` VARCHAR(255) NULL COMMENT 'السببُ إلزاميٌّ عند الاعتراض',
  `objected_by`    INT NULL,
  `objected_at`    DATETIME NULL,
  `resolved_at`    DATETIME NULL COMMENT 'حسمُ الاعتراض — بعده يعود البندُ محتسبًا',

  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_line_source` (`settlement_id`, `source_kind`, `source_ref`)
      COMMENT 'لا يُحمَّل مصدرٌ مرتين في التسوية الواحدة',
  KEY `ix_line_settlement` (`settlement_id`),
  KEY `ix_line_objected` (`objected`),
  CONSTRAINT `fk_line_settlement` FOREIGN KEY (`settlement_id`)
      REFERENCES `settlements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='بنودُ التسوية — كلُّ بندٍ برابط أصله (UX-05 §5.2)';

-- ── نسبةُ التحميل إلى المورد: عمودٌ صريحٌ يُختار عند الصرف (قرارُ المالك) ───
--    السببُ المقيس: `proc_issue.supplier_id` يحمل **بائعَ** القطعة من سجل
--    `proc_supplier`، لا مورّدَ الآليات المُحمَّل من سجل `suppliers` — والمعرّفاتُ
--    متصادمة (المورد 1 «مورد 1» هناك و«شركة النيل لقطع الغيار» هنا). ونسبةُ
--    التحميل بالمعدة اشتقاقًا رفضها المالك لأن القطعة قد تُصرف لمعدةٍ بخلاف مالكها.
ALTER TABLE `proc_issue`
  ADD COLUMN `charge_supplier_id` INT NULL
      COMMENT 'مورّدُ الآليات (suppliers.id) الذي يُحمَّل ثمنَ الصرف — فارغٌ أي على الشركة'
      AFTER `supplier_id`;

ALTER TABLE `mnt_order`
  ADD COLUMN `charge_supplier_id` INT NULL
      COMMENT 'مورّدُ الآليات الذي تُحمَّل عليه تكلفةُ الأمر — يُفعّل حين cost_party=خارجي'
      AFTER `cost_party`;

-- ── وسمُ الإرث: دُفِع قبل سريان «لا دفعَ بلا تسوية» (قرارُ المالك ③) ────────
ALTER TABLE `fin_dues`
  ADD COLUMN `pre_settlement_legacy` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT 'صفٌّ دُفِع قبل سريان قاعدة «لا دفعَ بلا تسوية» — مستثنًى صراحةً لا ملفَّقٌ له مستند'
      AFTER `settlement_state`,
  ADD COLUMN `settlement_id` INT NULL
      COMMENT 'التسويةُ التي احتسبت هذا الصفَّ — فلا يُحتسب في تسويتين'
      AFTER `pre_settlement_legacy`;

-- الصفوفُ المدفوعةُ اليوم بلا أيِّ تسوية (المقيس: المورد 3 · صفّان · 141,600)
UPDATE `fin_dues`
   SET `pre_settlement_legacy` = 1
 WHERE `settlement_state` = 'paid'
   AND `settlement_id` IS NULL;
