-- ════════════════════════════════════════════════════════════════════════════
-- 2026-06-25 — طبقة القوى التشغيلية (EQUIP-OPE-S04) · الموجة 1
-- ════════════════════════════════════════════════════════════════════════════
-- نهج Bolt-on: CREATE TABLE/VIEW فقط. صفر ALTER على أي جدولٍ قائم. الربط بالإرث
-- بالقيمة (لا FK مفروضٌ على employees/suppliers/project). FK داخليٌّ بين جداول الطبقة.
-- قابلية تراجعٍ كاملة: حذف هذه الجداول يعيد النظام لحالته الأصلية.
--
-- ⚠️ للمراجعة قبل التطبيق — لا يُطبَّق تلقائياً. خذ نسخةً احتياطيةً أولاً.
-- MariaDB 10.4 · DB: equipation_manage · utf8mb4_unicode_ci
-- ملاحظة دمجٍ مستقبلي: الهدف لاحقاً دمج worker_profile داخل employees وإلغاء جسر
-- employee_id — انظر Workforce/FUTURE_MERGE_NOTES.md
-- ════════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- ── 1) سجل العامل التشغيلي (8.1) — امتداد 1:1 لـ employees ─────────────────────
CREATE TABLE IF NOT EXISTS `worker_profile` (
  `id`                     INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`             INT(11)      DEFAULT NULL,
  `employee_id`            INT(11)      NOT NULL COMMENT 'جسر بالقيمة إلى employees.id (1:1)',
  `code`                   VARCHAR(50)  DEFAULT NULL COMMENT 'كود العامل — يدوي (قرار 12)',
  `worker_category`        VARCHAR(40)  NOT NULL COMMENT 'مشغّل/سائق · فني · مهندس · مشرف · مراقب · عمالة مساندة',
  `source_type`            ENUM('شركة','مورد','مقاول') NOT NULL DEFAULT 'شركة',
  `workforce_class`        ENUM('أساسي','احتياطي','بديل مؤقت','تغطية إجازة','تجاري مؤقت') NOT NULL DEFAULT 'أساسي',
  `job_grade`              VARCHAR(40)  DEFAULT NULL COMMENT 'مساعد مشغّل · مشغّل · مشغّل أول · مشرف وردية · قائد طاقم',
  `state`                  ENUM('مرشّح','مسجّل','مؤهّل','متعاقد','مخصّص','في إجازة','منتهٍ') NOT NULL DEFAULT 'مسجّل',
  `medical_fitness_status` ENUM('لائق للعمل','لائق بشروط','موقوف طبيًّا','يحتاج إعادة تقييم') DEFAULT NULL COMMENT 'يُحدَّث من فحص 8.2 لاحقاً',
  `fitness_conditions`     VARCHAR(255) DEFAULT NULL,
  `primary_backup_id`      INT(11)      DEFAULT NULL COMMENT 'البديل الأساسي → worker_profile.id',
  `is_replaceable`         TINYINT(1)   DEFAULT 1,
  `supplier_id`            INT(11)      DEFAULT NULL COMMENT 'بالقيمة إلى suppliers.id عند مورد/مقاول',
  `notes`                  TEXT         DEFAULT NULL,
  `created_by`             INT(11)      DEFAULT NULL,
  `created_at`             TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_worker_employee` (`employee_id`),
  KEY `idx_wp_company` (`company_id`),
  KEY `idx_wp_state` (`state`),
  KEY `idx_wp_category` (`worker_category`),
  KEY `idx_wp_primary_backup` (`primary_backup_id`),
  CONSTRAINT `fk_wp_primary_backup` FOREIGN KEY (`primary_backup_id`) REFERENCES `worker_profile` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── 2) المهارات والرخص والاعتمادات (8.2) — يشمل الترقية/التدرّج ─────────────────
CREATE TABLE IF NOT EXISTS `worker_qualification` (
  `id`                     INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`             INT(11)      DEFAULT NULL,
  `worker_id`              INT(11)      NOT NULL,
  `record_type`            ENUM('مؤهل','رخصة','خبرة','ترقية') NOT NULL,
  `title`                  VARCHAR(255) DEFAULT NULL COMMENT 'اسم الشهادة/الرخصة/الدرجة',
  `issuer`                 VARCHAR(255) DEFAULT NULL,
  `equipment_type`         VARCHAR(100) DEFAULT NULL COMMENT 'نوع المعدة المرتبط بالرخصة',
  `issue_date`             DATE         DEFAULT NULL,
  `expiry_date`            DATE         DEFAULT NULL,
  `accreditation_category` ENUM('مهارة معدة','اعتماد فني','دورة','شهادة','سلامة','فحص طبي','اعتماد موقع','تصريح') DEFAULT NULL,
  `proficiency_level`      ENUM('مبتدئ','متوسط','متقدم','خبير') DEFAULT NULL,
  `is_critical`            TINYINT(1)   DEFAULT 0 COMMENT 'يمنع التخصيص عند انتهائه',
  `alert_lead_days`        INT(11)      DEFAULT 30,
  `document`               VARCHAR(255) DEFAULT NULL,
  `decision_ref`           VARCHAR(255) DEFAULT NULL COMMENT 'قرار الترقية/التدرّج وتاريخه',
  `created_by`             INT(11)      DEFAULT NULL,
  `created_at`             TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wq_worker` (`worker_id`),
  KEY `idx_wq_company` (`company_id`),
  KEY `idx_wq_expiry` (`expiry_date`),
  KEY `idx_wq_critical` (`is_critical`),
  CONSTRAINT `fk_wq_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── 3) بدائل العامل (8.1) — الاحتياطي/المؤقت ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `worker_backup` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `company_id`       INT(11) DEFAULT NULL,
  `worker_id`        INT(11) NOT NULL,
  `backup_worker_id` INT(11) NOT NULL,
  `backup_type`      ENUM('احتياطي','مؤقت') NOT NULL DEFAULT 'احتياطي',
  `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_backup` (`worker_id`,`backup_worker_id`,`backup_type`),
  KEY `idx_wb_company` (`company_id`),
  KEY `idx_wb_backup` (`backup_worker_id`),
  CONSTRAINT `fk_wb_worker`  FOREIGN KEY (`worker_id`)        REFERENCES `worker_profile` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wb_backup`  FOREIGN KEY (`backup_worker_id`) REFERENCES `worker_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── 4) المواقع المحظورة طبياً (8.1/8.2) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `worker_restricted_site` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) DEFAULT NULL,
  `worker_id`  INT(11) NOT NULL,
  `project_id` INT(11) NOT NULL COMMENT 'بالقيمة إلى project.id',
  `reason`     VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_restricted` (`worker_id`,`project_id`),
  KEY `idx_wrs_company` (`company_id`),
  CONSTRAINT `fk_wrs_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── 5) تسجيل الموديول والصلاحيات (بيانات لا بنية) ─────────────────────────────
-- إلزاميٌّ: check_page_permissions يفشل مفتوحاً إن لم يُسجَّل الموديول.
-- الملكية لإدارة الموارد البشرية = الدور 4 (تحقّق عكسيٌّ من جدول roles).
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 'سجل العامل التشغيلي', 'Workforce/worker_register.php', 4, '1', 'fa fa-people-group', 60
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code` = 'Workforce/worker_register.php');

-- منح الدور 4 (إدارة الموارد البشرية) كل صلاحيات الشاشة
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 4, m.id, 1, 1, 1, 1
FROM `modules` m
WHERE m.code = 'Workforce/worker_register.php'
  AND NOT EXISTS (
    SELECT 1 FROM `role_permissions` rp WHERE rp.role_id = 4 AND rp.module_id = m.id
  );

-- ════════════════════════════════════════════════════════════════════════════
-- التراجع (Rollback) — عند الحاجة فقط:
--   DROP TABLE IF EXISTS `worker_restricted_site`, `worker_backup`, `worker_qualification`, `worker_profile`;
--   DELETE rp FROM `role_permissions` rp JOIN `modules` m ON m.id = rp.module_id WHERE m.code = 'Workforce/worker_register.php';
--   DELETE FROM `modules` WHERE `code` = 'Workforce/worker_register.php';
-- ════════════════════════════════════════════════════════════════════════════
