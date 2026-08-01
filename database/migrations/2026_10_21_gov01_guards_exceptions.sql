-- ═══════════════════════════════════════════════════════════════════════════
-- GOV-01 التصنيف والاستثناءات (PLAN-05 §3-⑦ · GOV-01 §10)
-- ───────────────────────────────────────────────────────────────────────────
-- «لا حماية بلا صنف معلن — ولا يُقلب علم حماية إلى الإنفاذ قبل تصنيفها».
-- تمييز الأعلام الثلاثة (GOV-01 §10): أعلام .env تحسم هل الحارس يعمل ·
-- guard_policies يحسم صنف الحماية وهل تُستثنى · governance_flags (LEG-01)
-- يحسم عناصر الحوكمة المفعَّلة — ولا تُخلط.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `guard_policies` (
  `guard_code` VARCHAR(64) NOT NULL,
  `name_ar` VARCHAR(160) NOT NULL,
  `owner_doc` VARCHAR(40) NULL DEFAULT NULL COMMENT 'وثيقة البيت',
  `guard_class` ENUM('absolute','exception_allowed','advisory') NOT NULL,
  `default_risk` ENUM('normal','operational','financial','high','legal_forbidden') NOT NULL DEFAULT 'normal',
  `env_flag_name` VARCHAR(64) NULL DEFAULT NULL COMMENT 'اسم العلم في .env',
  `classified_by` INT NULL DEFAULT NULL,
  `classified_at` DATETIME NULL DEFAULT NULL,
  `reason` VARCHAR(255) NULL DEFAULT NULL COMMENT 'سبب إلزامي لأي تغيير صنف',
  PRIMARY KEY (`guard_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GOV-01 §10: قاموس تصنيف الحمايات — الصنف يتغير بقرار حوكمة لا بتعديل إعداد';

-- تصنيف الحمايات السبع (GOV-01 §3) + بوابة الاستحقاق (منع مطلق — §10-⑤)
INSERT IGNORE INTO `guard_policies` (`guard_code`, `name_ar`, `owner_doc`, `guard_class`, `default_risk`, `env_flag_name`, `classified_by`, `classified_at`, `reason`) VALUES
  ('tenant.isolation',      'عزل الكيانات',            'ADR-02',  'absolute',          'legal_forbidden', 'EMS_TENANT_GATE',       1, NOW(), 'تسرّب بين الكيانات لا يُستثنى — GOV-01 §3'),
  ('driver.doc.expiry',     'وثائق السائقين',          'E-08',    'exception_allowed', 'high',            'EMS_DOC_EXPIRY_GUARD',  1, NOW(), 'تُقلب اليوم بمسار استثناء — لا تنتظر تنظيف 34 وثيقة'),
  ('container.share.cap',   'بوابة الحصص',             'H-01',    'exception_allowed', 'financial',       'EMS_CONTAINER_GATE_MODE', 1, NOW(), 'تجاوز السقف بثلاث موافقات — أثر مالي عالٍ'),
  ('supplier.doc.gate',     'وثائق الموردين',          'M-19',    'exception_allowed', 'financial',       'EMS_SUPPLIER_DOC_GATE', 1, NOW(), 'تُستثنى بموافقتين — البوابة ②'),
  ('baseline.billing.gate', 'بوابة خط الأساس',         'P-10',    'exception_allowed', 'financial',       'EMS_BASELINE_GATE',     1, NOW(), 'لا فوترة قبل القفل — استثناء العقود والمالية'),
  ('downtime.attribution',  'إسناد التوقف',            'N-12',    'absolute',          'high',            'EMS_RESP_PARTY_STRICT', 1, NOW(), 'الإسناد إلزامي ولا واقعة تُقفل بلا مسؤول'),
  ('rotation.share.transfer','انتقال الحصة بالتناوب',  'N-05',    'advisory',          'normal',          'EMS_ROTATION_AUTOTRANSFER', 1, NOW(), 'قرار تفعيل تشغيلي لا حماية'),
  ('price.adjustment',      'تعديل السعر',             'M-09',    'exception_allowed', 'financial',       'EMS_PRICE_ADJUST',      1, NOW(), 'لا تعديل خارج آلية معرَّفة — ثلاث موافقات لأثره الإيرادي'),
  ('entitlement.gate',      'بوابة الاستحقاق المالي',  'POL-01',  'absolute',          'financial',       NULL,                    1, NOW(), 'لا استثناء لهذه البوابة — صنفها منع مطلق (GOV-01 §10-⑤)');

CREATE TABLE IF NOT EXISTS `exception_requests` (
  `req_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `guard_code` VARCHAR(64) NOT NULL,
  `requester_person_id` INT NOT NULL,
  `reason` VARCHAR(500) NOT NULL,
  `risk_level` ENUM('normal','operational','financial','high','legal_forbidden') NOT NULL COMMENT 'محسوب — يُرفع لا يُخفض إلا بقرار',
  `scope_type` ENUM('person','operation','equipment','contract','period') NOT NULL,
  `scope_id` VARCHAR(64) NOT NULL,
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NOT NULL COMMENT 'إلزامي — لا استثناء مفتوح المدة',
  `one_time` TINYINT(1) NOT NULL DEFAULT 0,
  `documents_json` JSON NULL DEFAULT NULL,
  `expected_impact` VARCHAR(255) NULL DEFAULT NULL,
  `state` ENUM('Draft','Pending','Approved','Rejected','Active','Expired','Revoked') NOT NULL DEFAULT 'Pending',
  `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `closed_reason` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`req_id`),
  KEY `ix_exr_guard` (`guard_code`, `state`, `valid_to`),
  KEY `ix_exr_company` (`company_id`, `state`),
  CONSTRAINT `fk_exr_guard` FOREIGN KEY (`guard_code`) REFERENCES `guard_policies` (`guard_code`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GOV-01 §7: طلبات الاستثناء — بمدة ونطاق وسبب ومستندات، ولا استثناء عام';

CREATE TABLE IF NOT EXISTS `exception_approvals` (
  `app_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `req_id` INT UNSIGNED NOT NULL,
  `approver_person_id` INT NOT NULL,
  `approver_role` VARCHAR(60) NOT NULL COMMENT 'الدور — لا دور يتكرر في طلب واحد (تحرسه الخدمة 409)',
  `auth_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'مرجع التفويض (LEG-01)',
  `seq_no` TINYINT UNSIGNED NOT NULL,
  `decision` ENUM('approve','reject') NOT NULL,
  `reason` VARCHAR(255) NULL DEFAULT NULL,
  `at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`app_id`),
  UNIQUE KEY `uq_exa_seq` (`req_id`, `seq_no`),
  CONSTRAINT `fk_exa_req` FOREIGN KEY (`req_id`) REFERENCES `exception_requests` (`req_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GOV-01 §7: موافقات الاستثناء بالتسلسل — approver ≠ requester ولا دور مكرر';

CREATE TABLE IF NOT EXISTS `exception_usages` (
  `usage_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `req_id` INT UNSIGNED NOT NULL,
  `operation_ref` VARCHAR(120) NOT NULL,
  `person_id` INT NOT NULL,
  `at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`usage_id`),
  KEY `ix_exu_req` (`req_id`, `at`),
  CONSTRAINT `fk_exu_req` FOREIGN KEY (`req_id`) REFERENCES `exception_requests` (`req_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GOV-01 §7-⑤: كل عبور باستثناء يُسجَّل — Insert-only';

CREATE TABLE IF NOT EXISTS `waivers_reversals` (
  `ovr_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `action` ENUM('waive','reverse','suspend','reduce') NOT NULL,
  `source_type` VARCHAR(60) NOT NULL COMMENT 'مرجع الأصل — إلزامي',
  `source_id` BIGINT UNSIGNED NOT NULL,
  `amount_before` DECIMAL(18,2) NULL DEFAULT NULL,
  `amount_after` DECIMAL(18,2) NULL DEFAULT NULL,
  `reason` VARCHAR(500) NOT NULL,
  `approvals_ref` VARCHAR(120) NULL DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ovr_id`),
  KEY `ix_wr_source` (`source_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GOV-01 §8: الإعفاء والعكس والتعليق والتخفيض — Insert-only ولا حذف للأصل أبدًا';

CREATE TABLE IF NOT EXISTS `guard_denials` (
  `deny_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `guard_code` VARCHAR(64) NOT NULL,
  `person_id` INT NOT NULL,
  `attempted_ref` VARCHAR(120) NULL DEFAULT NULL,
  `reason_code` VARCHAR(80) NULL DEFAULT NULL,
  `at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`deny_id`),
  KEY `ix_gd_guard` (`guard_code`, `at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GOV-01 §9: سجل المنع — مقياس ملاءمة الحماية لا سجل مخالفات المستخدمين';

CREATE TABLE IF NOT EXISTS `unit_state_changes` (
  `chg_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `scope_type` ENUM('unit','equipment','site','contract') NOT NULL,
  `scope_id` INT UNSIGNED NOT NULL,
  `date_from` DATE NOT NULL,
  `date_to` DATE NOT NULL,
  `field_changed` ENUM('time_state','responsible_party','quantity','classification') NOT NULL,
  `value_before` VARCHAR(120) NOT NULL,
  `value_after` VARCHAR(120) NOT NULL,
  `reason` VARCHAR(500) NOT NULL,
  `doc_ref` VARCHAR(120) NOT NULL COMMENT 'المستند المؤيد إلزامي',
  `estimated_impact_json` JSON NOT NULL COMMENT 'الأثر المقدَّر لكل طرف — قبل الإرسال',
  `state` ENUM('Draft','Pending','Approved','Rejected','Applied','Reversed') NOT NULL DEFAULT 'Pending',
  `requested_by` INT NOT NULL,
  `applied_at` DATETIME NULL DEFAULT NULL,
  `reversal_ref` VARCHAR(120) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`chg_id`),
  KEY `ix_usc_scope` (`company_id`, `scope_type`, `scope_id`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GOV-01 §6: تغيير حالة الوحدات — ولا Applied إلا من Approved، والمقيَّد يُعكس لا يُعدَّل';

CREATE TABLE IF NOT EXISTS `change_approvals` (
  `step_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chg_id` INT UNSIGNED NOT NULL,
  `seq_no` TINYINT UNSIGNED NOT NULL COMMENT '1=مدير الحركة · 2=الإدارة المعنية · 3=المالية · 4=الإدارة العامة',
  `approver_person_id` INT NOT NULL,
  `role` VARCHAR(60) NOT NULL,
  `auth_id` INT UNSIGNED NULL DEFAULT NULL,
  `decision` ENUM('approve','reject') NOT NULL,
  `reason` VARCHAR(255) NULL DEFAULT NULL,
  `at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`step_id`),
  UNIQUE KEY `uq_ca_seq` (`chg_id`, `seq_no`),
  CONSTRAINT `fk_ca_chg` FOREIGN KEY (`chg_id`) REFERENCES `unit_state_changes` (`chg_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GOV-01 §6-④: سلّم الموافقات الرباعي — لا تُفتح خطوة قبل اكتمال ما قبلها';
