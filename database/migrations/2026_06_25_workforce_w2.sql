-- ════════════════════════════════════════════════════════════════════════════
-- 2026-06-25 — طبقة القوى التشغيلية (EQUIP-OPE-S04) · الموجة 2 (العقد + التخصيص L4)
-- ════════════════════════════════════════════════════════════════════════════
-- Bolt-on: CREATE TABLE فقط. صفر ALTER. الربط بالإرث بالقيمة. للمراجعة قبل التطبيق.
-- المالية: حقول قيمةٍ + عمود تعليقٍ مرجعيٍّ (*_finance_note) — قرار 5.
-- التخصيص (L4): طبقة إثراءٍ فوق equipment_drivers القائم (قرار 9) — لا يكرّر العامل↔الآلية.
-- ════════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- ── عقد العامل التشغيلي (8.3) — مستقلٌّ تماماً عن drivercontracts (قرار 1) ──────
CREATE TABLE IF NOT EXISTS `worker_contract` (
  `id`                 INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`         INT(11)      DEFAULT NULL,
  `worker_id`          INT(11)      NOT NULL,
  `code`               VARCHAR(50)  DEFAULT NULL COMMENT 'كود العقد — يدوي (قرار 12)',
  `contract_type`      ENUM('سنوي','غير محدّد','مشروع','موسمي','مؤقت','بالساعة','بالإنتاج','استشاري/إشرافي','احتياطي','تغطية مؤقتة','تجاري مؤقت') NOT NULL,
  `wage`               DECIMAL(12,2) DEFAULT NULL COMMENT 'مالي — إدخال يدوي',
  `wage_finance_note`  VARCHAR(255) DEFAULT NULL COMMENT 'تعليق مرجعي للإدارة المالية مستقبلاً',
  `wage_method`        ENUM('شهري','بالساعة','بالوردية/اليوم','بالإنتاج','مقطوع') NOT NULL DEFAULT 'شهري',
  `date_start`         DATE         DEFAULT NULL,
  `date_end`           DATE         DEFAULT NULL,
  `state`              ENUM('مسودة','نافذ','منتهٍ') NOT NULL DEFAULT 'مسودة',
  `rotation_pattern`   ENUM('بلا','شهران+شهر','ثلاثة أشهر+15 يوم','مخصّص') NOT NULL DEFAULT 'بلا',
  `work_days`          INT(11)      DEFAULT NULL,
  `leave_days`         INT(11)      DEFAULT NULL,
  `next_rotation_date` DATE         DEFAULT NULL,
  `planned_backup_id`  INT(11)      DEFAULT NULL COMMENT '→ worker_profile.id',
  `monthly_hours_base` INT(11)      DEFAULT NULL COMMENT 'أساس توزيع المتغيّر (مثال 300)',
  `fixed_wage_ratio`   DECIMAL(5,2) DEFAULT NULL COMMENT 'نسبة الأجر الثابت % (مثال 30)',
  `billable_downtime`  ENUM('استعداد العميل','+ عطل الصيانة','حسب الحدث') DEFAULT NULL,
  `allow_housing`      DECIMAL(12,2) DEFAULT NULL,
  `allow_food`         DECIMAL(12,2) DEFAULT NULL,
  `allow_site`         DECIMAL(12,2) DEFAULT NULL,
  `allow_transport`    DECIMAL(12,2) DEFAULT NULL,
  `allow_finance_note` VARCHAR(255) DEFAULT NULL COMMENT 'تعليق مرجعي للبدلات — للمالية مستقبلاً',
  `leave_terms`        VARCHAR(255) DEFAULT NULL,
  `coverage_terms`     VARCHAR(255) DEFAULT NULL,
  `termination_terms`  VARCHAR(255) DEFAULT NULL,
  `created_by`         INT(11)      DEFAULT NULL,
  `created_at`         TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wc_worker` (`worker_id`),
  KEY `idx_wc_company` (`company_id`),
  KEY `idx_wc_state` (`state`),
  KEY `idx_wc_planned_backup` (`planned_backup_id`),
  CONSTRAINT `fk_wc_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── تخصيص العامل (8.4 · L4) — طبقة إثراءٍ فوق equipment_drivers ────────────────
CREATE TABLE IF NOT EXISTS `worker_allocation` (
  `id`                  INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id`          INT(11)      DEFAULT NULL,
  `worker_id`           INT(11)      NOT NULL,
  `equipment_driver_id` INT(11)      DEFAULT NULL COMMENT 'بالقيمة إلى equipment_drivers.id (L4 القائم)',
  `operation_id`        INT(11)      DEFAULT NULL COMMENT 'بالقيمة إلى operations.id (L3)',
  `allocated_qty`       DECIMAL(10,2) DEFAULT NULL COMMENT 'سقف وحدات/ساعات العامل (اختياري)',
  `state`               ENUM('مخطّط','معتمد','نشط','منتهٍ') NOT NULL DEFAULT 'مخطّط',
  `crew_role`           ENUM('فرد','قائد طاقم','عضو طاقم') NOT NULL DEFAULT 'فرد',
  `lead_allocation_id`  INT(11)      DEFAULT NULL COMMENT 'تخصيص قائد الطاقم → worker_allocation.id',
  `active_backup_id`    INT(11)      DEFAULT NULL COMMENT '→ worker_profile.id عند الإجازة/التغطية',
  `coverage_reason`     ENUM('غياب مفاجئ','إجازة/مرض','تبديل وردية','توسّع مؤقت','بدء مشروع','طوارئ') DEFAULT NULL,
  `expected_end_date`   DATE         DEFAULT NULL,
  `source_type`         ENUM('شركة','مورد','مقاول') DEFAULT NULL,
  `notes`               TEXT         DEFAULT NULL,
  `created_by`          INT(11)      DEFAULT NULL,
  `created_at`          TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wa_worker` (`worker_id`),
  KEY `idx_wa_company` (`company_id`),
  KEY `idx_wa_operation` (`operation_id`),
  KEY `idx_wa_state` (`state`),
  KEY `idx_wa_lead` (`lead_allocation_id`),
  CONSTRAINT `fk_wa_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker_profile` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wa_lead`   FOREIGN KEY (`lead_allocation_id`) REFERENCES `worker_allocation` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── تسجيل الموديولات والصلاحيات (بيانات) ──────────────────────────────────────
-- الملكية لإدارة الموارد البشرية = الدور 4 (تحقّق عكسيٌّ من جدول roles).
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 'عقود العاملين', 'Workforce/worker_contract.php', 4, '1', 'fa fa-file-signature', 61
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code` = 'Workforce/worker_contract.php');

INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 'تخصيص العاملين', 'Workforce/worker_allocation.php', 4, '1', 'fa fa-diagram-project', 62
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code` = 'Workforce/worker_allocation.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 4, m.id, 1, 1, 1, 1 FROM `modules` m
WHERE m.code IN ('Workforce/worker_contract.php','Workforce/worker_allocation.php')
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` rp WHERE rp.role_id = 4 AND rp.module_id = m.id);

-- ════════════════════════════════════════════════════════════════════════════
-- التراجع:
--   DROP TABLE IF EXISTS `worker_allocation`, `worker_contract`;
--   DELETE rp FROM `role_permissions` rp JOIN `modules` m ON m.id=rp.module_id
--     WHERE m.code IN ('Workforce/worker_contract.php','Workforce/worker_allocation.php');
--   DELETE FROM `modules` WHERE `code` IN ('Workforce/worker_contract.php','Workforce/worker_allocation.php');
-- ════════════════════════════════════════════════════════════════════════════
