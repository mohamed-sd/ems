-- ============================================================
-- نظام اعتماد ساعات العمل - Timesheet Approvals System
-- ============================================================
-- يُنفَّذ هذا الملف مرة واحدة ولا يُعدِّل أي جداول موجودة
-- ============================================================

-- -----------------------------------------------------------
-- جدول اعتمادات التايمشيت (هرمي: 4 مستويات)
-- Level 1 = مدير المشاريع (role 1)
-- Level 2 = مدير الموردين (role 2)
-- Level 3 = مدير الأسطول (role 3)
-- Level 4 = مدير المشغلين (role 4)  ← الاعتماد النهائي
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `timesheet_approvals` (
  `id`               int(11)      NOT NULL AUTO_INCREMENT,
  `timesheet_id`     int(11)      NOT NULL COMMENT 'FK → timesheet.id',
  `company_id`       int(11)      DEFAULT NULL,
  `approval_level`   tinyint(1)   NOT NULL COMMENT '1..4',
  `approved_by`      int(11)      NOT NULL COMMENT 'FK → users.id',
  `approved_by_name` varchar(255) NOT NULL,
  `approved_at`      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`           tinyint(1)   NOT NULL DEFAULT 1 COMMENT '1=اعتمد, 0=رُفض',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ts_level` (`timesheet_id`, `approval_level`),
  KEY `idx_ts_id`    (`timesheet_id`),
  KEY `idx_company`  (`company_id`),
  KEY `idx_level`    (`approval_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
  COMMENT='اعتمادات ساعات العمل الهرمية';

-- -----------------------------------------------------------
-- جدول ملاحظات التايمشيت  (مرفقة بسجل وعمود محدد)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `timesheet_approval_notes` (
  `id`               int(11)      NOT NULL AUTO_INCREMENT,
  `timesheet_id`     int(11)      NOT NULL COMMENT 'FK → timesheet.id',
  `company_id`       int(11)      DEFAULT NULL,
  `column_name`      varchar(100) NOT NULL COMMENT 'اسم العمود التقني',
  `column_label`     varchar(255) NOT NULL COMMENT 'عنوان العمود بالعربية',
  `note_text`        text         NOT NULL,
  `created_by`       int(11)      NOT NULL COMMENT 'FK → users.id',
  `created_by_name`  varchar(255) NOT NULL,
  `created_at`       datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`           tinyint(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_ts_id`    (`timesheet_id`),
  KEY `idx_company`  (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
  COMMENT='ملاحظات اعتماد ساعات العمل';
