-- ═══════════════════════════════════════════════════════════════════════════
-- أنماط الحوكمة الموسَّعة ③④ (PLAN-05 البوابة ④ · LEG-01 §3 §5 §9)
-- ───────────────────────────────────────────────────────────────────────────
-- بقية طبقة LEG-01: علاقات الملكية (قيد المئة المشروط) · التراخيص · الكفالات
-- (الصادرة والواردة — مفصولة عن المحتجَز النقدي P-06) — والنمطان ③④ أعلام
-- تفعيل على governance_flags (external_accounts · signing_caps · joint_signing
-- · guarantees · licenses) لا بنية جديدة: «الترقية بين الأنماط بلا هجرة بيانات».
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `entity_ownership` (
  `own_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_type` ENUM('person','entity') NOT NULL,
  `owner_id` INT NOT NULL COMMENT 'users.id للشخص أو legal_entities.entity_id للكيان',
  `owned_entity_id` INT UNSIGNED NOT NULL,
  `percent` DECIMAL(5,2) NOT NULL,
  `ownership_kind` ENUM('shares','stocks','partnership') NOT NULL DEFAULT 'shares',
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  `doc_ref` VARCHAR(120) NULL DEFAULT NULL,
  `recorded_percent` DECIMAL(5,2) NULL DEFAULT NULL,
  `corrected_percent` DECIMAL(5,2) NULL DEFAULT NULL,
  `correction_reason` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`own_id`),
  KEY `ix_eo_owned` (`owned_entity_id`, `valid_from`),
  KEY `ix_eo_owner` (`owner_type`, `owner_id`),
  CONSTRAINT `fk_eo_owned` FOREIGN KEY (`owned_entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_eo_pct` CHECK (`percent` > 0 AND `percent` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='LEG-01 §3: علاقات الملكية بنسبة ومدة — Σ=100 عند ownership_completeness=full وحده (الخدمة) ولا تعديل بأثر رجعي';

CREATE TABLE IF NOT EXISTS `entity_licenses` (
  `lic_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_id` INT UNSIGNED NOT NULL,
  `lic_type` VARCHAR(80) NOT NULL,
  `issuer` VARCHAR(120) NULL DEFAULT NULL,
  `lic_no` VARCHAR(80) NOT NULL,
  `issue_date` DATE NULL DEFAULT NULL,
  `expiry_date` DATE NOT NULL,
  `alert_days` INT UNSIGNED NOT NULL DEFAULT 30,
  `file_ref` VARCHAR(160) NULL DEFAULT NULL,
  `state` ENUM('active','expired','renewed','revoked') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`lic_id`),
  KEY `ix_el_expiry` (`expiry_date`, `state`),
  CONSTRAINT `fk_el_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='LEG-01 §5: التراخيص بتواريخ انتهائها وتنبيهاتها';

CREATE TABLE IF NOT EXISTS `guarantees` (
  `gtee_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `direction` ENUM('issued','received') NOT NULL COMMENT 'صادرة منا (التزام محتمل) · واردة إلينا (حق محتمل)',
  `entity_id` INT UNSIGNED NOT NULL,
  `counterparty_id` INT UNSIGNED NULL DEFAULT NULL,
  `gtee_type` VARCHAR(80) NOT NULL,
  `bank` VARCHAR(120) NULL DEFAULT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `currency` VARCHAR(8) NOT NULL,
  `issue_date` DATE NULL DEFAULT NULL,
  `expiry_date` DATE NOT NULL,
  `alert_days` INT UNSIGNED NOT NULL DEFAULT 30,
  `auto_renew` TINYINT(1) NOT NULL DEFAULT 0,
  `fees` DECIMAL(18,2) NULL DEFAULT NULL,
  `state` ENUM('active','released','called','expired') NOT NULL DEFAULT 'active',
  `doc_ref` VARCHAR(120) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`gtee_id`),
  KEY `ix_g_expiry` (`expiry_date`, `state`),
  CONSTRAINT `fk_g_entity` FOREIGN KEY (`entity_id`) REFERENCES `legal_entities` (`entity_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='LEG-01 §5: الكفالات وخطابات الضمان — التزام/حق محتمل خارج الميزانية، مفصول عن المحتجَز النقدي (P-06)';
