-- M-00 (الإدارة التنفيذية) — اللحاق بالجداول الأصلية (CMP03_FOLLOWUP) + السقوف
-- ═══════════════════════════════════════════════════════════════════════════
-- السجلات الخمسة التي تملكها الإدارة التنفيذية (M-00 §8-1) تخرج من المخزن
-- البيني cmp03_screen_rows إلى جداولها المخصصة بأعمدة مفصلة، ويضاف:
--   • exec_dept_caps — سقوف الإدارات النقدية (أساس BR-CEO-05: الرفع الآلي)
--   • contracts.signing_authority_ref — مرجع سلطة التوقيع (BR-CEO-01)
--   • قوادح BR-CEO-08 — «لا رجعية في القرار الموقَّع»: منع بنيوي في القاعدة
--     لتعديل أعمدة القرار بعد وقوعه؛ والتغيير صف جديد يشير للأصل.
-- عزل المستأجر: company_id في كل جدول، والبذور is_seed=1 لا تنشر وقائع.

-- ── ① سقوف الإدارات ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `exec_dept_caps` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL COMMENT 'الكيان المالك',
  `dept_name` VARCHAR(80) NOT NULL COMMENT 'اسم الإدارة كما يرد في الطلبات',
  `cap_amount` DECIMAL(18,2) NOT NULL COMMENT 'السقف النقدي — ما فوقه يُرفع آليًّا',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `effective_from` DATE NOT NULL COMMENT 'بداية السريان',
  `effective_to` DATE DEFAULT NULL COMMENT 'نهاية السريان (NULL = مفتوح)',
  `authority_ref` VARCHAR(120) DEFAULT NULL COMMENT 'سند الاعتماد — قرار الموازنة',
  `note` VARCHAR(255) DEFAULT NULL,
  `is_seed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT DEFAULT NULL,
  `created_by_name` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept_cap` (`company_id`, `dept_name`, `currency`, `effective_from`),
  KEY `ix_cap_live` (`company_id`, `effective_from`, `effective_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-00 §5-1: سقوف الإدارات — أساس الرفع الآلي BR-CEO-05';

-- ── ② اعتمادات المدير التنفيذي (الاعتماد الأعلى) ─────────────────────────
CREATE TABLE IF NOT EXISTS `exec_approvals` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `request_no` VARCHAR(40) NOT NULL COMMENT 'رقم الطلب',
  `received_date` DATE DEFAULT NULL COMMENT 'تاريخ الورود',
  `doc_type` VARCHAR(80) DEFAULT NULL COMMENT 'نوع المستند',
  `document` VARCHAR(255) DEFAULT NULL COMMENT 'المستند',
  `requesting_dept` VARCHAR(80) DEFAULT NULL COMMENT 'الإدارة الطالبة',
  `raise_reason` VARCHAR(255) DEFAULT NULL COMMENT 'سبب الرفع للأعلى',
  `amount` DECIMAL(18,2) DEFAULT NULL COMMENT 'القيمة',
  `currency` VARCHAR(8) DEFAULT NULL,
  `dept_cap` DECIMAL(18,2) DEFAULT NULL COMMENT 'سقف الإدارة لحظة الرفع',
  `overage` DECIMAL(18,2) DEFAULT NULL COMMENT 'التجاوز',
  `prior_approvers` VARCHAR(255) DEFAULT NULL COMMENT 'المعتمِدون قبلي',
  `deadline` VARCHAR(60) DEFAULT NULL COMMENT 'المهلة المعلنة',
  `decision` VARCHAR(30) DEFAULT NULL COMMENT 'قراري: اعتماد/اعتماد بشرط/رد/تأجيل',
  `decision_reason` VARCHAR(300) DEFAULT NULL COMMENT 'سبب القرار أو الشرط',
  `decision_date` DATE DEFAULT NULL,
  `approver_name` VARCHAR(120) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `authority_ref` VARCHAR(120) DEFAULT NULL COMMENT 'مرجع التفويض أو سلطة أصلية',
  `status` VARCHAR(40) NOT NULL DEFAULT 'قيد المراجعة',
  `source_request_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ربط الطلب الحقيقي requests.id',
  `source_kind` VARCHAR(30) DEFAULT NULL COMMENT 'منشأ الصف: يدوي/رفع آلي/طلب',
  `is_seed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT DEFAULT NULL,
  `created_by_name` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_exap_live` (`company_id`, `status`, `received_date`),
  KEY `ix_exap_src` (`source_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-00 §8-2: الاعتماد الأعلى — الجدول الأصلي لشاشة ceo_approvals';

-- ── ③ سجل توقيع العقود والالتزامات ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `exec_contract_signings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `contract_no` VARCHAR(60) NOT NULL COMMENT 'رقم العقد',
  `contract_kind` VARCHAR(80) DEFAULT NULL COMMENT 'نوع العقد',
  `other_party` VARCHAR(190) DEFAULT NULL COMMENT 'الطرف الآخر',
  `party_type` VARCHAR(30) DEFAULT NULL COMMENT 'عميل/مورد/موظف/ممول',
  `amount` DECIMAL(18,2) DEFAULT NULL,
  `currency` VARCHAR(8) DEFAULT NULL,
  `duration` VARCHAR(40) DEFAULT NULL COMMENT 'المدة',
  `work_model` VARCHAR(40) DEFAULT NULL COMMENT 'نموذج العمل',
  `contract_unit` VARCHAR(40) DEFAULT NULL COMMENT 'وحدة التعاقد',
  `units_count` VARCHAR(80) DEFAULT NULL COMMENT 'عدد الوحدات',
  `bond_required` VARCHAR(10) DEFAULT NULL COMMENT 'الكفالة المطلوبة',
  `bond_value` VARCHAR(60) DEFAULT NULL COMMENT 'قيمة الكفالة',
  `legal_review` VARCHAR(190) DEFAULT NULL COMMENT 'المراجعة القانونية',
  `financial_review` VARCHAR(190) DEFAULT NULL COMMENT 'المراجعة المالية',
  `signed_by_us` VARCHAR(120) DEFAULT NULL COMMENT 'الموقّع عنّا',
  `signer_capacity` VARCHAR(80) DEFAULT NULL COMMENT 'صفة الموقّع عنّا',
  `authority_ref` VARCHAR(120) DEFAULT NULL COMMENT 'مرجع سلطته — BR-CEO-01',
  `signing_date` DATE DEFAULT NULL,
  `other_signer` VARCHAR(120) DEFAULT NULL COMMENT 'الموقّع عن الطرف الآخر',
  `other_signer_capacity` VARCHAR(80) DEFAULT NULL COMMENT 'صفته',
  `other_authority_doc` VARCHAR(120) DEFAULT NULL COMMENT 'مستند تخويله',
  `registry_recorded` VARCHAR(10) NOT NULL DEFAULT 'لا' COMMENT 'سُجّل في السجل الموحَّد؟',
  `status` VARCHAR(40) NOT NULL DEFAULT 'قيد المراجعة',
  `contract_id` INT DEFAULT NULL COMMENT 'ربط العقد الحقيقي contracts.id',
  `is_seed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT DEFAULT NULL,
  `created_by_name` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_excs_live` (`company_id`, `status`, `signing_date`),
  KEY `ix_excs_contract` (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-00 §8-2: سجل التوقيع — الجدول الأصلي لشاشة ceo_contracts';

-- ── ④ قرارات فتح المشاريع ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `exec_project_charters` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `decision_no` VARCHAR(40) NOT NULL COMMENT 'رقم القرار',
  `project_name` VARCHAR(190) DEFAULT NULL,
  `client` VARCHAR(190) DEFAULT NULL COMMENT 'العميل',
  `contract_ref` VARCHAR(60) DEFAULT NULL COMMENT 'العقد',
  `sites_text` VARCHAR(255) DEFAULT NULL COMMENT 'الموقع أو المواقع',
  `work_model` VARCHAR(40) DEFAULT NULL,
  `work_unit` VARCHAR(40) DEFAULT NULL COMMENT 'وحدة العمل',
  `contracted_qty` VARCHAR(80) DEFAULT NULL COMMENT 'الكمية المتعاقدة',
  `planned_start` DATE DEFAULT NULL COMMENT 'تاريخ البدء المخطط',
  `duration` VARCHAR(40) DEFAULT NULL,
  `equipment_needed` VARCHAR(190) DEFAULT NULL COMMENT 'المعدات المطلوبة',
  `operators_needed` VARCHAR(80) DEFAULT NULL COMMENT 'المشغّلون المطلوبون',
  `equipment_source` VARCHAR(80) DEFAULT NULL COMMENT 'مصدر المعدات',
  `financing_need` VARCHAR(190) DEFAULT NULL COMMENT 'احتياج التمويل',
  `cost_center` VARCHAR(60) DEFAULT NULL COMMENT 'مركز التكلفة',
  `site_manager` VARCHAR(120) DEFAULT NULL COMMENT 'مدير الموقع المعيَّن',
  `manager_powers` VARCHAR(255) DEFAULT NULL COMMENT 'صلاحياته',
  `cert_operations` VARCHAR(190) DEFAULT NULL COMMENT 'إفادة التشغيل',
  `cert_sales` VARCHAR(190) DEFAULT NULL COMMENT 'إفادة المبيعات',
  `cert_workforce` VARCHAR(190) DEFAULT NULL COMMENT 'إفادة القوى',
  `cert_finance` VARCHAR(190) DEFAULT NULL COMMENT 'إفادة المالية',
  `cert_fleet` VARCHAR(190) DEFAULT NULL COMMENT 'إفادة الأسطول',
  `cert_financing` VARCHAR(190) DEFAULT NULL COMMENT 'إفادة التمويل',
  `approver_name` VARCHAR(120) DEFAULT NULL COMMENT 'المعتمِد — الاسم والصفة',
  `approval_date` VARCHAR(80) DEFAULT NULL COMMENT 'تاريخ الاعتماد أو حالة التأجيل',
  `status` VARCHAR(40) NOT NULL DEFAULT 'قيد الإفادات',
  `project_id` INT DEFAULT NULL COMMENT 'المشروع المولَّد project.id (الأثر الخماسي)',
  `cost_center_id` INT DEFAULT NULL COMMENT 'مركز التكلفة المولَّد fin_cost_centers.id',
  `is_seed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT DEFAULT NULL,
  `created_by_name` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_expc_live` (`company_id`, `status`, `planned_start`),
  KEY `ix_expc_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-00 §8-2: قرار فتح المشروع — الجدول الأصلي لشاشة project_charter';

-- ── ⑤ القرارات والمخاطر العليا ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `exec_decisions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `decision_no` VARCHAR(40) NOT NULL COMMENT 'رقم القرار',
  `raised_date` DATE DEFAULT NULL COMMENT 'تاريخ الرفع',
  `raising_dept` VARCHAR(80) DEFAULT NULL COMMENT 'الجهة الرافعة',
  `issue_type` VARCHAR(60) DEFAULT NULL COMMENT 'نوع القضية',
  `issue_desc` VARCHAR(300) DEFAULT NULL COMMENT 'وصف القضية',
  `est_impact` VARCHAR(60) DEFAULT NULL COMMENT 'الأثر المقدَّر',
  `currency` VARCHAR(8) DEFAULT NULL,
  `options_text` VARCHAR(400) DEFAULT NULL COMMENT 'الخيارات المطروحة',
  `chosen_option` VARCHAR(190) DEFAULT NULL COMMENT 'الخيار المختار',
  `choice_reason` VARCHAR(300) DEFAULT NULL COMMENT 'مبرر الاختيار',
  `assigned_dept` VARCHAR(80) DEFAULT NULL COMMENT 'الجهة المكلَّفة بالتنفيذ',
  `exec_deadline` VARCHAR(40) DEFAULT NULL COMMENT 'مهلة التنفيذ — BR-CEO-04',
  `followup_date` DATE DEFAULT NULL COMMENT 'تاريخ المتابعة',
  `approver_name` VARCHAR(120) DEFAULT NULL,
  `decision_date` DATE DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'قيد الحسم',
  `is_seed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT DEFAULT NULL,
  `created_by_name` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_exdc_live` (`company_id`, `status`, `raised_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-00 §8-2: سجل القرارات العليا — الجدول الأصلي لشاشة ceo_risk';

-- ── ⑥ لقطات لوحة المدير التنفيذي ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `exec_board_snapshots` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `period` VARCHAR(20) NOT NULL COMMENT 'الفترة',
  `active_contracts` VARCHAR(40) DEFAULT NULL COMMENT 'العقود النافذة',
  `portfolio_value` VARCHAR(60) DEFAULT NULL COMMENT 'قيمة المحفظة',
  `recognized_revenue` VARCHAR(60) DEFAULT NULL COMMENT 'الإيراد المعترف',
  `collection` VARCHAR(60) DEFAULT NULL COMMENT 'التحصيل',
  `overdue_receivables` VARCHAR(60) DEFAULT NULL COMMENT 'الذمم المتأخرة',
  `expected_cashflow` VARCHAR(60) DEFAULT NULL COMMENT 'التدفق المتوقع',
  `financing_commitments` VARCHAR(60) DEFAULT NULL COMMENT 'التزامات التمويل',
  `working_equipment` VARCHAR(40) DEFAULT NULL COMMENT 'المعدات العاملة',
  `readiness_pct` VARCHAR(20) DEFAULT NULL COMMENT 'نسبة الجاهزية',
  `approved_units` VARCHAR(40) DEFAULT NULL COMMENT 'الوحدات المعتمدة',
  `margin_pct` VARCHAR(20) DEFAULT NULL COMMENT 'الهامش',
  `open_risks` VARCHAR(20) DEFAULT NULL COMMENT 'المخاطر المفتوحة',
  `pending_approvals` VARCHAR(20) DEFAULT NULL COMMENT 'الاعتمادات المعلَّقة',
  `last_updated` VARCHAR(30) DEFAULT NULL COMMENT 'آخر تحديث',
  `status` VARCHAR(40) NOT NULL DEFAULT 'معتمد',
  `is_seed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT DEFAULT NULL,
  `created_by_name` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_board_period` (`company_id`, `period`, `is_seed`),
  KEY `ix_exbs_live` (`company_id`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-00 §8-2: لقطات المؤشرات العليا — الجدول الأصلي لشاشة ceo_board';

-- ── ⑦ BR-CEO-01: مرجع سلطة التوقيع على العقد الحقيقي ─────────────────────
ALTER TABLE `contracts`
  ADD COLUMN IF NOT EXISTS `signing_authority_ref` VARCHAR(120) DEFAULT NULL
    COMMENT 'BR-CEO-01: سلطة أصلية أو مرجع تفويض موثق — يُلزم عند الانتقال إلى موقَّع';

-- ── ⑧ قوادح BR-CEO-08: لا رجعية في القرار الموقَّع (منع بنيوي) ──────────
-- التغيير المشروع = صف جديد يشير للأصل؛ تعديل أعمدة القرار بعد وقوعه يُرفض
-- من القاعدة نفسها مهما كان مسار الوصول. تقدّم الحالة (متابعة/إغلاق) مباح.
DROP TRIGGER IF EXISTS `trg_exap_immutable`;
CREATE TRIGGER `trg_exap_immutable` BEFORE UPDATE ON `exec_approvals`
FOR EACH ROW
BEGIN
  IF OLD.decision IS NOT NULL AND OLD.decision <> '' AND (
       NOT (NEW.decision <=> OLD.decision)
    OR NOT (NEW.decision_reason <=> OLD.decision_reason)
    OR NOT (NEW.decision_date <=> OLD.decision_date)
    OR NOT (NEW.amount <=> OLD.amount)
    OR NOT (NEW.request_no <=> OLD.request_no)
    OR NOT (NEW.authority_ref <=> OLD.authority_ref)
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'BR-CEO-08: القرار الموقع لا يعدل — التغيير قرار جديد يشير للأصل';
  END IF;
END;

DROP TRIGGER IF EXISTS `trg_excs_immutable`;
CREATE TRIGGER `trg_excs_immutable` BEFORE UPDATE ON `exec_contract_signings`
FOR EACH ROW
BEGIN
  IF OLD.signing_date IS NOT NULL AND (
       NOT (NEW.signing_date <=> OLD.signing_date)
    OR NOT (NEW.amount <=> OLD.amount)
    OR NOT (NEW.contract_no <=> OLD.contract_no)
    OR NOT (NEW.signed_by_us <=> OLD.signed_by_us)
    OR NOT (NEW.authority_ref <=> OLD.authority_ref)
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'BR-CEO-08: العقد الموقع لا يعدل — الإنهاء أو التعليق قرار موثق جديد';
  END IF;
END;

DROP TRIGGER IF EXISTS `trg_exdc_immutable`;
CREATE TRIGGER `trg_exdc_immutable` BEFORE UPDATE ON `exec_decisions`
FOR EACH ROW
BEGIN
  IF OLD.decision_date IS NOT NULL AND (
       NOT (NEW.chosen_option <=> OLD.chosen_option)
    OR NOT (NEW.choice_reason <=> OLD.choice_reason)
    OR NOT (NEW.decision_date <=> OLD.decision_date)
    OR NOT (NEW.assigned_dept <=> OLD.assigned_dept)
    OR NOT (NEW.exec_deadline <=> OLD.exec_deadline)
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'BR-CEO-08: القرار المحسوم لا يعدل — قرار لاحق يعدله بمرجع الأصل';
  END IF;
END;

DROP TRIGGER IF EXISTS `trg_expc_immutable`;
CREATE TRIGGER `trg_expc_immutable` BEFORE UPDATE ON `exec_project_charters`
FOR EACH ROW
BEGIN
  IF OLD.approval_date IS NOT NULL AND OLD.approval_date <> ''
     AND OLD.status IN ('مفتوح', 'مغلق') AND (
       NOT (NEW.decision_no <=> OLD.decision_no)
    OR NOT (NEW.approval_date <=> OLD.approval_date)
    OR NOT (NEW.site_manager <=> OLD.site_manager)
    OR NOT (NEW.contract_ref <=> OLD.contract_ref)
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'BR-CEO-08: قرار الفتح المعتمد لا يعدل — الإغلاق قرار مشابه بمحضر تصفية';
  END IF;
END;
