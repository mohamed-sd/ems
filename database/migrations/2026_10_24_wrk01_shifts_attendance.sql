-- ═══════════════════════════════════════════════════════════════════════════
-- WRK-01 النواة (PLAN-05 §3-⑦ البوابة ② · WRK-01 §8)
-- ───────────────────────────────────────────────────────────────────────────
-- «الفترة هي وحدة الحقيقة» — الفترات بمشغّليها والرموز الأحد عشر (العشرة + ST)
-- ومصفوفة الأثر الخماسي ودورة اعتماد الخصم — بلا شاشات موسَّعة (البوابة ④).
-- القاموس يُوسَّع لا يُستبدل: payroll_absence_types هو قاموس الرموز — تُضاف
-- إليه الأعمدة والرموز، وattendance_days سجل اليوم الذي يشير إليه.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `shift_patterns` (
  `pattern_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `name_ar` VARCHAR(120) NOT NULL,
  `shifts_per_day` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `base_hours` DECIMAL(5,2) NOT NULL,
  `overtime_hours` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `crosses_midnight` TINYINT(1) NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`pattern_id`),
  UNIQUE KEY `uq_sp_name` (`company_id`, `name_ar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WRK-01 §2: أنماط الورديات — قاموس يُضاف إليه بقرار، والمواعيد معرَّفة لا مثبَّتة في الكود';

CREATE TABLE IF NOT EXISTS `shift_period_defs` (
  `def_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pattern_id` INT UNSIGNED NOT NULL,
  `shift_no` TINYINT UNSIGNED NOT NULL,
  `period_no` TINYINT UNSIGNED NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `base_hours` DECIMAL(5,2) NOT NULL,
  `overtime_hours` DECIMAL(5,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`def_id`),
  UNIQUE KEY `uq_spd` (`pattern_id`, `shift_no`, `period_no`),
  CONSTRAINT `fk_spd_pattern` FOREIGN KEY (`pattern_id`) REFERENCES `shift_patterns` (`pattern_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WRK-01 §2.1: فترات النمط بمواعيدها وساعاتها الأساسية والإضافية';

CREATE TABLE IF NOT EXISTS `shift_period_logs` (
  `log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `work_date` DATE NOT NULL,
  `equipment_id` INT NOT NULL,
  `shift_no` TINYINT UNSIGNED NOT NULL,
  `period_no` TINYINT UNSIGNED NOT NULL,
  `operator_person_id` INT NOT NULL COMMENT 'مشغّل واحد لكل فترة إلزامًا — NOT NULL بنيوي',
  `qty` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `unit` VARCHAR(16) NOT NULL DEFAULT 'ton',
  `run_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `standby_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `stop_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `stop_reason_code` VARCHAR(40) NULL DEFAULT NULL COMMENT 'من stop_reason_codes (N-12) — توقف بلا سبب 422 في الخدمة',
  `site_id` INT NULL DEFAULT NULL,
  `state` ENUM('logged','approved') NOT NULL DEFAULT 'logged',
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  UNIQUE KEY `uq_spl_key` (`work_date`, `equipment_id`, `shift_no`, `period_no`) COMMENT 'مفتاح (معدة×تاريخ×وردية×فترة) — يمنع تكرار المزامنة (وشرط N-08)',
  KEY `ix_spl_operator` (`operator_person_id`, `work_date`),
  KEY `ix_spl_company` (`company_id`, `work_date`),
  CONSTRAINT `fk_spl_reason` FOREIGN KEY (`stop_reason_code`) REFERENCES `stop_reason_codes` (`code`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WRK-01 §2.1: سجل الفترة — وحدة الحقيقة؛ المعدة ثابتة للوردية والمشغّل يتغير بالفترة';

CREATE TABLE IF NOT EXISTS `attendance_policies` (
  `policy_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `name_ar` VARCHAR(120) NOT NULL,
  `applies_to_json` JSON NOT NULL COMMENT 'محددات §1: نوع الموظف · مقر/مشروع · العقد · نمط الوردية · الوظيفة · الموقع',
  `grace_minutes` INT UNSIGNED NULL DEFAULT NULL COMMENT 'سماح المقر (8:15) — NULL للمشاريع (لا تأخر مكتبي)',
  `missing_punch_rule` VARCHAR(60) NULL DEFAULT NULL COMMENT 'half_day_unless_corrected للمقر · NULL للمشاريع (الإثبات بكشف الموقع)',
  `late_rule` VARCHAR(60) NULL DEFAULT NULL COMMENT 'monthly_total للمقر — بإجمالي زمن التأخير لا بعدد المرات',
  `partial_permission_limit` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'الإذن الجزئي: مرتان شهريًّا',
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`policy_id`),
  UNIQUE KEY `uq_ap_name` (`company_id`, `name_ar`, `valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WRK-01 §1: سياستان لا سياسة واحدة — ولا سياسة افتراضية صامتة (بلا مطابقة → 422)';

CREATE TABLE IF NOT EXISTS `attendance_days` (
  `att_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `person_id` INT NOT NULL,
  `att_date` DATE NOT NULL,
  `status_code` VARCHAR(4) NOT NULL COMMENT 'من قاموس payroll_absence_types.code حصرًا (تحرسه الخدمة)',
  `policy_id` INT UNSIGNED NULL DEFAULT NULL,
  `reference_doc` VARCHAR(120) NULL DEFAULT NULL,
  `stop_reason_code` VARCHAR(40) NULL DEFAULT NULL COMMENT 'لحالة ST — الفوترة والاستحقاق يُقرآن من الإسناد',
  `classified_by` INT NULL DEFAULT NULL,
  `classified_at` DATETIME NULL DEFAULT NULL,
  `auto_reclassified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = صُنّف A2 آليًّا بعد 48 ساعة وإشعار',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`att_id`),
  UNIQUE KEY `uq_att_day` (`person_id`, `att_date`),
  KEY `ix_att_company` (`company_id`, `att_date`, `status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WRK-01 §3: سجل اليوم — يشير إلى القاموس ولا يوازيه';

-- ── توسعة القاموس القائم (لا قاموسان لشيء واحد) — أعمدة الرمز والأثر الخماسي ──
SET @n = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payroll_absence_types' AND COLUMN_NAME='code');
SET @ddl = IF(@n=0, 'ALTER TABLE `payroll_absence_types`
  ADD COLUMN `code` VARCHAR(4) NULL DEFAULT NULL COMMENT ''WRK-01 §3: رمز الحالة (1·0·10·11·ST·S·M·A1·A2·EM·UP)'' AFTER `event_type`,
  ADD COLUMN `pay_effect` ENUM(''full'',''none'',''per_contract'',''per_policy'',''stops_accrual'',''per_hr'',''deduct_daily'') NULL DEFAULT NULL COMMENT ''أثر الراتب'',
  ADD COLUMN `incentive_base` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''يدخل أساس الحافز؟'',
  ADD COLUMN `presence` ENUM(''site'',''off'',''transit'',''mission'') NULL DEFAULT NULL COMMENT ''التواجد'',
  ADD COLUMN `billable` ENUM(''yes'',''no'',''by_attribution'') NULL DEFAULT NULL COMMENT ''الفوترة — ST بالإسناد'',
  ADD COLUMN `supplier_due` ENUM(''yes'',''no'',''by_attribution'',''per_contract'') NULL DEFAULT NULL COMMENT ''استحقاق المورد'',
  ADD COLUMN `conduct_violation` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''A2: مخالفة سلوكية تُسجَّل — أثر ثانٍ مستقل''', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @n = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payroll_absence_types' AND INDEX_NAME='uq_absence_code');
SET @ddl = IF(@n=0, 'ALTER TABLE `payroll_absence_types` ADD UNIQUE KEY `uq_absence_code` (`company_id`, `code`)', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- الرموز الأحد عشر بأثرها الخماسي (WRK-01 §3–§4) — للشركة 4
INSERT IGNORE INTO `payroll_absence_types`
  (`company_id`, `event_type`, `code`, `deducts`, `deduct_percent`, `label_ar`, `pay_effect`, `incentive_base`, `presence`, `billable`, `supplier_due`, `conduct_violation`, `active`) VALUES
  (4, 'WRK:عمل',            '1',  0, 0,   'يوم عمل',                'full',          1, 'site',    'yes', 'yes', 0, 1),
  (4, 'WRK:إجازة ميدانية',  '0',  0, 0,   'إجازة ميدانية/تناوب',    'per_contract',  0, 'off',     'no',  'no',  0, 1),
  (4, 'WRK:وصول',           '10', 0, 0,   'يوم وصول',               'per_contract',  0, 'transit', 'no',  'per_contract', 0, 1),
  (4, 'WRK:مغادرة',         '11', 0, 0,   'يوم مغادرة',             'per_contract',  0, 'transit', 'no',  'per_contract', 0, 1),
  (4, 'WRK:انتظار',         'ST', 0, 0,   'انتظار تشغيلي',          'full',          0, 'site',    'by_attribution', 'by_attribution', 0, 1),
  (4, 'WRK:مرضية',          'S',  0, 0,   'إجازة مرضية بتقرير',     'per_policy',    0, 'off',     'no',  'no',  0, 1),
  (4, 'WRK:مأمورية',        'M',  0, 0,   'مأمورية عمل',            'full',          0, 'mission', 'no',  'per_contract', 0, 1),
  (4, 'WRK:غياب مبرر',      'A1', 0, 0,   'غياب مبرَّر — لا يُخصم', 'full',          0, 'off',     'no',  'no',  0, 1),
  (4, 'WRK:غياب غير مبرر',  'A2', 1, 100, 'غياب غير مبرَّر — يوم بيوم + مخالفة', 'deduct_daily', 0, 'off', 'no', 'no', 1, 1),
  (4, 'WRK:طارئة',          'EM', 0, 0,   'حالة طارئة — بتقدير الموارد البشرية', 'per_hr', 0, 'off', 'no', 'no', 0, 1),
  (4, 'WRK:بلا أجر',        'UP', 0, 0,   'إجازة بلا أجر — توقف الاستحقاق بمدتها', 'stops_accrual', 0, 'off', 'no', 'no', 0, 1);

CREATE TABLE IF NOT EXISTS `deduction_proposals` (
  `ded_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `person_id` INT NOT NULL,
  `period` CHAR(7) NOT NULL,
  `source` ENUM('late','missing_punch','leave_no_balance','unexcused','penalty','advance_installment') NOT NULL,
  `source_ref` VARCHAR(120) NOT NULL COMMENT 'المستند/اليوم المصدر — لا خصم بلا مصدر (M-11)',
  `proposed_amount` DECIMAL(14,2) NOT NULL,
  `is_voluntary` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'الاستقطاعات الاختيارية (سلف · نيابة) تخضع لحد ثلث الصافي — والجزاءات والغياب خارجه (DEC ②)',
  `state` ENUM('Proposed','Reviewed','Approved','Posted','Waived') NOT NULL DEFAULT 'Proposed',
  `reviewed_by` INT NULL DEFAULT NULL,
  `approvals_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'مرجع سلّم GOV-01',
  `posted_run_id` INT NULL DEFAULT NULL,
  `waiver_ref` INT NULL DEFAULT NULL COMMENT 'قرار الإعفاء المستقل (waivers_reversals) — والأصل باقٍ',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ded_id`),
  UNIQUE KEY `uq_dp_source` (`person_id`, `period`, `source`, `source_ref`),
  KEY `ix_dp_state` (`company_id`, `period`, `state`),
  CONSTRAINT `ck_dp_posted_needs_approval` CHECK (`state` <> 'Posted' OR `approvals_ref` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WRK-01 §6: لا خصم يُرحَّل مباشرة — Proposed ثم سلّم GOV-01 ثم Posted (CHECK بنيوي)';

-- ── نمطا وردية معياريان + السياستان (لا افتراض صامتًا) — للشركة 4 ──
INSERT IGNORE INTO `shift_patterns` (`company_id`, `name_ar`, `shifts_per_day`, `base_hours`, `overtime_hours`, `crosses_midnight`) VALUES
  (4, 'وردية واحدة 8 ساعات (فترتان)', 1, 8, 0, 0),
  (4, 'وردية واحدة 10 ساعات (فترتان)', 1, 8, 2, 0),
  (4, 'ورديتان 8+8 (فترتان لكلٍّ)', 2, 16, 0, 1),
  (4, 'ورديتان 10+10 (فترتان لكلٍّ)', 2, 16, 4, 1),
  (4, 'ثلاث ورديات 8×3', 3, 24, 0, 1);

INSERT IGNORE INTO `shift_period_defs` (`pattern_id`, `shift_no`, `period_no`, `start_time`, `end_time`, `base_hours`, `overtime_hours`)
SELECT p.pattern_id, 1, 1, '06:00:00', '12:00:00', 6, 0 FROM shift_patterns p WHERE p.company_id=4 AND p.name_ar='وردية واحدة 8 ساعات (فترتان)';
INSERT IGNORE INTO `shift_period_defs` (`pattern_id`, `shift_no`, `period_no`, `start_time`, `end_time`, `base_hours`, `overtime_hours`)
SELECT p.pattern_id, 1, 2, '14:00:00', '16:00:00', 2, 0 FROM shift_patterns p WHERE p.company_id=4 AND p.name_ar='وردية واحدة 8 ساعات (فترتان)';
INSERT IGNORE INTO `shift_period_defs` (`pattern_id`, `shift_no`, `period_no`, `start_time`, `end_time`, `base_hours`, `overtime_hours`)
SELECT p.pattern_id, 1, 1, '06:00:00', '12:00:00', 6, 0 FROM shift_patterns p WHERE p.company_id=4 AND p.name_ar='وردية واحدة 10 ساعات (فترتان)';
INSERT IGNORE INTO `shift_period_defs` (`pattern_id`, `shift_no`, `period_no`, `start_time`, `end_time`, `base_hours`, `overtime_hours`)
SELECT p.pattern_id, 1, 2, '14:00:00', '18:00:00', 2, 2 FROM shift_patterns p WHERE p.company_id=4 AND p.name_ar='وردية واحدة 10 ساعات (فترتان)';

INSERT IGNORE INTO `attendance_policies` (`company_id`, `name_ar`, `applies_to_json`, `grace_minutes`, `missing_punch_rule`, `late_rule`, `partial_permission_limit`, `valid_from`) VALUES
  (4, 'سياسة المقر', JSON_OBJECT('employee_scope', 'hq'), 15, 'half_day_unless_corrected', 'monthly_total', 2, '2026-08-01'),
  (4, 'سياسة المشاريع والمواقع', JSON_OBJECT('employee_scope', 'field'), NULL, NULL, NULL, 2, '2026-08-01');
