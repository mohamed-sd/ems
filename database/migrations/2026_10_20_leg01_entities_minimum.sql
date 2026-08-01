-- ═══════════════════════════════════════════════════════════════════════════
-- LEG-01 الحد الأدنى (PLAN-05 §3-⑥ · LEG-01 §9) — النمط ① داخلي محض حصرًا
-- ───────────────────────────────────────────────────────────────────────────
-- «الكيانات وis_tenant وentity_roles والتفويض بالسقوف وسجل التوقيعات — بالنمط ①
-- فقط؛ بلا حسابات خارجية ولا كفالات ولا توقيع مشترك».
-- بناء إضافي فوق company_id القائم: كيانات المجموعة تُبذر من admin_companies
-- وحد العزل يُقرأ من tenants (entity_id = is_tenant=1) — ولا يُشتق من صفة.
-- entity_ownership/licenses/guarantees مؤجلة لأنماط الحوكمة الموسعة (البوابة ④).
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `legal_entities` (
  `entity_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `legal_name` VARCHAR(255) NOT NULL,
  `legal_form` VARCHAR(80) NULL DEFAULT NULL,
  `country` VARCHAR(60) NOT NULL DEFAULT 'SD',
  `registry_authority` VARCHAR(120) NOT NULL DEFAULT 'السجل التجاري',
  `commercial_reg` VARCHAR(80) NOT NULL,
  `tax_no` VARCHAR(80) NULL DEFAULT NULL,
  `base_currency` VARCHAR(8) NOT NULL DEFAULT 'SDG' COMMENT 'عملة الدفاتر (functional_currency)',
  `is_tenant` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'كيانات المجموعة المستأجرة — حد العزل من tenants حصرًا',
  `ownership_completeness` ENUM('full','partial','unknown') NOT NULL DEFAULT 'unknown' COMMENT 'قيد المئة يُفرض عند full وحده',
  `state` ENUM('active','suspended','liquidation','closed') NOT NULL DEFAULT 'active',
  `registered_address` VARCHAR(255) NULL DEFAULT NULL,
  `founded_date` DATE NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`entity_id`),
  UNIQUE KEY `uq_le_registry` (`country`, `registry_authority`, `commercial_reg`) COMMENT 'الفرادة بالثلاثة معًا — الرقم قد يتكرر في دولتين',
  KEY `ix_le_tenant` (`is_tenant`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='LEG-01 §2: الكيانات القانونية — سجل واحد لا يتكرر، ولا عمود صفات نصي ولا JSON';

CREATE TABLE IF NOT EXISTS `entity_roles` (
  `role_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_id` INT UNSIGNED NOT NULL,
  `role` ENUM('holding','operating','project','client','supplier','financier','government') NOT NULL,
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  `doc_ref` VARCHAR(120) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_er_entity_role` (`entity_id`, `role`, `valid_from`),
  KEY `ix_er_role` (`role`, `valid_to`),
  CONSTRAINT `fk_er_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='LEG-01 §2-②: صفات الكيان جدول علاقة مؤرَّخ — لا حقل نصي';

CREATE TABLE IF NOT EXISTS `tenants` (
  `tenant_id` INT UNSIGNED NOT NULL COMMENT '= company_id القائم (حد العزل) — يقابل entity_id للمستأجرة وحدها',
  `entity_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`),
  UNIQUE KEY `uq_tenants_entity` (`entity_id`),
  CONSTRAINT `fk_tenants_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='LEG-01 §2-②-ب: حد العزل يُقرأ من هنا حصرًا — ولا يُشتق من أي صفة أخرى';

CREATE TABLE IF NOT EXISTS `signing_authorities` (
  `auth_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `person_id` INT NOT NULL COMMENT 'users.id',
  `entity_id` INT UNSIGNED NOT NULL COMMENT 'الكيان المفوِّض — التفويض بالصفة والكيان معًا',
  `capacity_id` INT NULL DEFAULT NULL COMMENT 'user_capacities.id — الصفة (H-15)',
  `auth_type` ENUM('general','financial','contractual','banking','operational') NOT NULL DEFAULT 'general',
  `amount_cap` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'السقف المالي — NULL = بلا سقف (تفويض عام بقرار)',
  `currency` VARCHAR(8) NULL DEFAULT NULL,
  `scope_type` VARCHAR(40) NULL DEFAULT NULL COMMENT 'project · department · doc_type',
  `scope_id` INT NULL DEFAULT NULL,
  `joint_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'التوقيع المشترك — مطفأ في النمط ①',
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NULL DEFAULT NULL COMMENT 'ينتهي بانتهاء مدته آليًّا — الحارس يقرأ التاريخ',
  `doc_ref` VARCHAR(120) NULL DEFAULT NULL,
  `state` ENUM('active','revoked') NOT NULL DEFAULT 'active',
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`auth_id`),
  KEY `ix_sa_person` (`person_id`, `entity_id`, `state`),
  KEY `ix_sa_expiry` (`valid_to`),
  CONSTRAINT `fk_sa_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='LEG-01 §4: التفويض بالتوقيع — لا اعتماد بلا تفويض نافذ ساري';

CREATE TABLE IF NOT EXISTS `approval_signatures` (
  `sig_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `document_type` VARCHAR(60) NOT NULL,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `step` VARCHAR(40) NOT NULL DEFAULT 'approve' COMMENT 'الخطوة/الحلقة — فلا يُسجَّل توقيع مرتين لخطوة',
  `person_id` INT NOT NULL,
  `capacity_id` INT NULL DEFAULT NULL,
  `auth_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'مرجع التفويض — NULL فقط لما قبل تفعيل الحارس',
  `amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'المبلغ الذي اعتُمد تحته',
  `at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` VARCHAR(45) NULL DEFAULT NULL,
  `result` ENUM('signed','denied') NOT NULL DEFAULT 'signed',
  PRIMARY KEY (`sig_id`),
  UNIQUE KEY `uq_sig_step` (`document_type`, `document_id`, `person_id`, `step`),
  KEY `ix_sig_person` (`person_id`, `at`),
  CONSTRAINT `fk_sig_auth` FOREIGN KEY (`auth_id`) REFERENCES `signing_authorities` (`auth_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='LEG-01 §6-③: الاعتماد توقيع — Insert-only ولا تعديل ولا حذف؛ يلف الاعتمادات القائمة لا يوازيها';

CREATE TABLE IF NOT EXISTS `governance_flags` (
  `flag_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `element_code` VARCHAR(80) NOT NULL COMMENT 'external_accounts · signing_caps · joint_signing · guarantees · licenses …',
  `scope_type` ENUM('entity','contract') NOT NULL,
  `scope_id` INT UNSIGNED NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `reason` VARCHAR(255) NULL DEFAULT NULL,
  `set_by` INT NULL DEFAULT NULL,
  `set_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`flag_id`),
  UNIQUE KEY `uq_gf_element_scope` (`element_code`, `scope_type`, `scope_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='LEG-01 §7: أعلام التفعيل لكل عنصر على الكيان والعقد — الافتراض النمط ① (كله مطفأ)';

-- ── بذر كيانات المجموعة من admin_companies (المستأجرون) — بناء فوق القائم ──
INSERT INTO `legal_entities` (`legal_name`, `country`, `registry_authority`, `commercial_reg`, `is_tenant`, `ownership_completeness`, `state`)
SELECT c.`name`, 'SD', 'السجل التجاري',
       CONCAT('TEN-', c.`id`) , 1, 'full', 'active'
  FROM `admin_companies` c
 WHERE NOT EXISTS (SELECT 1 FROM `legal_entities` le WHERE le.`commercial_reg` = CONCAT('TEN-', c.`id`) AND le.`country` = 'SD');

INSERT IGNORE INTO `tenants` (`tenant_id`, `entity_id`)
SELECT c.`id`, le.`entity_id`
  FROM `admin_companies` c
  JOIN `legal_entities` le ON le.`commercial_reg` = CONCAT('TEN-', c.`id`) AND le.`country` = 'SD' AND le.`is_tenant` = 1;

INSERT IGNORE INTO `entity_roles` (`entity_id`, `role`, `valid_from`)
SELECT t.`entity_id`, 'operating', CURDATE() FROM `tenants` t;
