-- ═══════════════════════════════════════════════════════════════════════════
-- ACT-01 §8 · عقدُ الفعل وخريطةُ الأثر — الجداول الستة — 2026-08-02
-- المصدر: docs/sources/ACT-01.docx §8 (المواصفة التنفيذية)
-- «لا يُبنى زرٌّ في النظام إلا وله عقدُ فعلٍ مسجَّل» — والفحوصُ تقرأ من هنا.
-- ═══════════════════════════════════════════════════════════════════════════

-- ① actions — سجلُّ الأفعال وعقدُها العشري
CREATE TABLE IF NOT EXISTS `actions` (
  `action_code`   VARCHAR(80)  NOT NULL COMMENT 'كودٌ فريدٌ للفعل — مفتاحُ كل ما بعده',
  `name_ar`       VARCHAR(160) NOT NULL,
  `module_id`     INT NULL COMMENT 'modules.id — الشاشةُ الأم (NULL لفعلٍ عابرٍ للشاشات)',
  `placement`     ENUM('header','row','tab','bulk','context') NOT NULL DEFAULT 'row',
  `handler_class`  VARCHAR(160) NULL COMMENT 'الخدمةُ المنفِّذة — ولا فعلَ ينفّذ منطقًا في الشاشة',
  `handler_method` VARCHAR(120) NULL,
  `handler_path`   VARCHAR(190) NULL COMMENT 'مسارُ المعالج الإجرائي (المستخرَجُ من action_guard) حين لا صنفَ له',
  `is_write`      TINYINT(1) NOT NULL DEFAULT 0,
  `guards_json`   TEXT NULL COMMENT 'الحرّاسُ بترتيب الفحص المعلن — وفعلُ كتابةٍ بلا حرّاس يُرفض',
  `precondition_expr` VARCHAR(255) NULL COMMENT 'الشرطُ المسبق — يُفحص في الخادم لا بإخفاء الزر',
  `reverse_action_code` VARCHAR(80) NULL COMMENT 'فعلُ العكس — إلزاميٌّ لكل فعلٍ ماليٍّ أو تعاقدي',
  `is_financial`  TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ماليٌّ أو تعاقديٌّ — يستوجب عكسًا',
  `owner_doc`     VARCHAR(40) NULL,
  `active`        TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`action_code`),
  KEY `ix_act_module` (`module_id`),
  KEY `ix_act_write` (`is_write`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ACT-01 §8: سجلُّ الأفعال — ولا زرَّ في واجهةٍ بلا صفٍّ هنا';

-- ② action_writes — ما يكتبه الفعل (منه يُعرف أثرُ تغيير المخطط على الأفعال)
CREATE TABLE IF NOT EXISTS `action_writes` (
  `w_id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action_code` VARCHAR(80) NOT NULL,
  `table_name`  VARCHAR(80) NOT NULL,
  `operation`   ENUM('insert','update','delete','none') NOT NULL DEFAULT 'update',
  PRIMARY KEY (`w_id`),
  UNIQUE KEY `uq_aw` (`action_code`, `table_name`, `operation`),
  CONSTRAINT `fk_aw_action` FOREIGN KEY (`action_code`) REFERENCES `actions`(`action_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ③ action_events — ما يُنشر بعد النجاح
CREATE TABLE IF NOT EXISTS `action_events` (
  `e_id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action_code`  VARCHAR(80) NOT NULL,
  `event_name`   VARCHAR(120) NOT NULL,
  `is_conditional` TINYINT(1) NOT NULL DEFAULT 0,
  `condition_expr` VARCHAR(255) NULL,
  `no_event_reason` VARCHAR(255) NULL COMMENT 'فعلُ كتابةٍ بلا حدثٍ يحتاج تعليلًا مكتوبًا',
  PRIMARY KEY (`e_id`),
  UNIQUE KEY `uq_ae` (`action_code`, `event_name`),
  CONSTRAINT `fk_ae_action` FOREIGN KEY (`action_code`) REFERENCES `actions`(`action_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ④ event_consumers — من يستهلك كلَّ حدث (وحدثٌ بلا صفٍّ هنا يمنع الدمج)
CREATE TABLE IF NOT EXISTS `event_consumers` (
  `c_id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_name`     VARCHAR(120) NOT NULL,
  `consumer_class`  VARCHAR(160) NOT NULL,
  `consumer_method` VARCHAR(120) NULL,
  `produces`       ENUM('write','notify','dashboard_refresh') NOT NULL DEFAULT 'write'
                   COMMENT 'مستهلكٌ لا يُنتج أثرًا مرئيًّا أو مسجَّلًا يُراجَع',
  `active`         TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`c_id`),
  UNIQUE KEY `uq_ec` (`event_name`, `consumer_class`),
  KEY `ix_ec_event` (`event_name`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ⑤ action_impacts — خريطةُ الأثر مُنمذَجةً لا موصوفة
CREATE TABLE IF NOT EXISTS `action_impacts` (
  `i_id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action_code`   VARCHAR(80) NOT NULL,
  `impacted_type` ENUM('org_unit','person','party','screen') NOT NULL,
  `impacted_ref`  VARCHAR(120) NOT NULL,
  `effect`        ENUM('notify','counter','data_change','state_change') NOT NULL,
  `latency`       ENUM('sync','async') NOT NULL DEFAULT 'async',
  PRIMARY KEY (`i_id`),
  KEY `ix_ai_action` (`action_code`),
  CONSTRAINT `fk_ai_action` FOREIGN KEY (`action_code`) REFERENCES `actions`(`action_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ⑥ action_execution_log — سماحًا أو منعًا · Insert-only · منه يُقاس أيُّ حارسٍ يعيق العمل
CREATE TABLE IF NOT EXISTS `action_execution_log` (
  `r_id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `action_code` VARCHAR(80) NOT NULL,
  `person_id`   INT NULL,
  `subject_ref` VARCHAR(120) NULL,
  `result`      ENUM('allowed','denied') NOT NULL,
  `denied_by_guard` VARCHAR(60) NULL,
  `at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip`          VARCHAR(45) NULL,
  PRIMARY KEY (`r_id`),
  KEY `ix_ael_action` (`action_code`, `result`, `at`),
  KEY `ix_ael_company` (`company_id`, `at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ACT-01 §8: Insert-only — لا تعديلَ ولا حذف';
