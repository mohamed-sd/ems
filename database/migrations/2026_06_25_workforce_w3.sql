-- ════════════════════════════════════════════════════════════════════════════
-- 2026-06-25 — طبقة القوى التشغيلية (EQUIP-OPE-S04) · الموجة 3
-- الإجازات/الغياب (8.6+8.13) · التحرّك/النقل (8.11+8.12) · السكن · Views (L5/الحالة)
-- ════════════════════════════════════════════════════════════════════════════
-- Bolt-on: CREATE TABLE/VIEW فقط. صفر ALTER. للمراجعة قبل التطبيق.
-- ملاحظة: VIEWs محسوبةٌ للقراءة فقط (نقطة الحقيقة الواحدة) — لا تخزينٌ مكرَّر.
-- ════════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- ── السكن (مرجعي · 8.11) ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `housing_unit` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) DEFAULT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `project_id` INT(11) DEFAULT NULL COMMENT 'بالقيمة إلى project.id',
  `capacity`   INT(11) DEFAULT NULL,
  `location`   VARCHAR(255) DEFAULT NULL,
  `notes`      VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hu_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── الإجازات والغياب الموحّدة (8.6 + 8.13) ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `worker_leave_absence` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `company_id`       INT(11) DEFAULT NULL,
  `worker_id`        INT(11) NOT NULL,
  `event_class`      ENUM('مخطّط','طارئ') NOT NULL DEFAULT 'مخطّط' COMMENT 'مخطّط=إجازة/تناوب · طارئ=غياب',
  `event_type`       VARCHAR(40) NOT NULL COMMENT 'تبادلية·اعتيادية·مأمورية | غياب مفاجئ·انقطاع·هروب·مرض·إصابة·أسري·وفاة',
  `date_from`        DATE DEFAULT NULL,
  `date_to`          DATE DEFAULT NULL,
  `substitute_id`    INT(11) DEFAULT NULL COMMENT '→ worker_profile.id',
  `rotation_pattern` VARCHAR(40) DEFAULT NULL,
  `next_due_date`    DATE DEFAULT NULL,
  `coverage_impact`  ENUM('مغطًّى','فجوة جزئية','فجوة حرجة') DEFAULT NULL,
  `outcome`          ENUM('عودة للعمل','تحويل لإجازة','إنهاء وتسوية') DEFAULT NULL,
  `state`            ENUM('مطلوب','معتمد','مفتوح','مُغطًّى','منتهٍ','مغلق') NOT NULL DEFAULT 'مطلوب',
  `reason`           VARCHAR(255) DEFAULT NULL,
  `notes`            TEXT DEFAULT NULL,
  `created_by`       INT(11) DEFAULT NULL,
  `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wla_worker` (`worker_id`),
  KEY `idx_wla_company` (`company_id`),
  KEY `idx_wla_state` (`state`),
  KEY `idx_wla_dates` (`date_from`,`date_to`),
  CONSTRAINT `fk_wla_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── التحرّك والنقل الموحّد (8.11 + 8.12) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `worker_movement` (
  `id`                   INT(11) NOT NULL AUTO_INCREMENT,
  `company_id`           INT(11) DEFAULT NULL,
  `worker_id`            INT(11) NOT NULL,
  `direction`            ENUM('التحاق أول','عودة من إجازة','مغادرة لإجازة/مأمورية','نقل بين مشاريع','مغادرة نهائية') NOT NULL,
  `allocation_id`        INT(11) DEFAULT NULL COMMENT '→ worker_allocation.id (قيمة)',
  `origin`               VARCHAR(150) DEFAULT NULL,
  `destination_project_id` INT(11) DEFAULT NULL COMMENT 'بالقيمة إلى project.id',
  `transport_mode`       ENUM('بري','جوي','ترتيب مورد') DEFAULT NULL,
  `departure_date`       DATE DEFAULT NULL,
  `expected_arrival`     DATE DEFAULT NULL,
  `actual_arrival`       DATE DEFAULT NULL,
  `received_by`          INT(11) DEFAULT NULL COMMENT 'بالقيمة إلى employees.id (مشرف الموقع)',
  `housing_unit_id`      INT(11) DEFAULT NULL COMMENT '→ housing_unit.id',
  `site_zone`            VARCHAR(150) DEFAULT NULL,
  `safety_kit_received`  TINYINT(1) DEFAULT 0,
  `custody_received`     TINYINT(1) DEFAULT NULL COMMENT 'مؤجّل (S09) — يبقى فارغاً الآن',
  `ready_date`           DATE DEFAULT NULL,
  `transfer_type`        ENUM('مؤقت','دائم','إعادة تخصيص') DEFAULT NULL COMMENT 'للنقل بين المشاريع',
  `from_project_id`      INT(11) DEFAULT NULL,
  `to_project_id`        INT(11) DEFAULT NULL,
  `state`                ENUM('مسودة','أمرٌ صادر','في الطريق','وصل','مستلَم بالموقع','جاهزٌ للعمل','ملغى') NOT NULL DEFAULT 'مسودة',
  `notes`                TEXT DEFAULT NULL,
  `created_by`           INT(11) DEFAULT NULL,
  `created_at`           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wm_worker` (`worker_id`),
  KEY `idx_wm_company` (`company_id`),
  KEY `idx_wm_state` (`state`),
  CONSTRAINT `fk_wm_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

-- ── VIEW: الساعات المؤهَّلة (8.8 · L5) — من timesheet القائم دون لمسه ───────────
-- جسر: timesheet.employee_id = employees.id = worker_profile.employee_id (قيمة)
-- timesheet.operator = operations.id (مؤكَّدٌ عكسياً)
CREATE OR REPLACE VIEW `v_worker_billable_hours` AS
SELECT
  wp.id                                   AS worker_id,
  wp.employee_id                          AS employee_id,
  t.date                                  AS work_date,
  CAST(t.operator AS UNSIGNED)            AS operation_id,
  COALESCE(SUM(t.executed_hours),0)       AS productive_hours,
  COALESCE(SUM(t.standby_hours),0)        AS standby_hours,
  COALESCE(SUM(t.hr_fault),0)             AS worker_downtime,
  COALESCE(SUM(t.maintenance_fault),0)    AS maintenance_downtime,
  GREATEST(COALESCE(SUM(t.executed_hours),0) + COALESCE(SUM(t.standby_hours),0) - COALESCE(SUM(t.hr_fault),0), 0) AS billable_baseline
FROM worker_profile wp
JOIN timesheet t ON CAST(t.employee_id AS UNSIGNED) = wp.employee_id
GROUP BY wp.id, wp.employee_id, t.date, CAST(t.operator AS UNSIGNED);

-- ── VIEW: الحالة الميدانية المحسوبة (8.1) — تكتمل تدريجياً ─────────────────────
CREATE OR REPLACE VIEW `v_worker_presence` AS
SELECT
  wp.id AS worker_id,
  CASE
    WHEN wp.state = 'منتهٍ' THEN 'منتهٍ'
    WHEN EXISTS (SELECT 1 FROM worker_leave_absence la
                 WHERE la.worker_id = wp.id AND la.state IN ('معتمد','مفتوح','مُغطًّى')
                   AND (la.date_from IS NULL OR la.date_from <= CURDATE())
                   AND (la.date_to   IS NULL OR la.date_to   >= CURDATE())) THEN 'خارج الموقع/إجازة'
    WHEN EXISTS (SELECT 1 FROM worker_movement m
                 WHERE m.worker_id = wp.id AND m.state IN ('أمرٌ صادر','في الطريق')) THEN 'في الطريق'
    WHEN EXISTS (SELECT 1 FROM worker_allocation a
                 WHERE a.worker_id = wp.id AND a.state = 'نشط') THEN 'داخل الموقع'
    WHEN EXISTS (SELECT 1 FROM worker_allocation a
                 WHERE a.worker_id = wp.id AND a.state IN ('مخطّط','معتمد')) THEN 'بانتظار التحرّك'
    ELSE 'بانتظار التخصيص'
  END AS presence_state
FROM worker_profile wp;

-- ── تسجيل الموديولات والصلاحيات ───────────────────────────────────────────────
-- الملكية لإدارة الموارد البشرية = الدور 4 (تحقّق عكسيٌّ من جدول roles).
INSERT INTO `modules` (`name`,`code`,`owner_role_id`,`is_link`,`icon`,`display_order`)
SELECT 'الإجازات والغياب','Workforce/worker_leave_absence.php',4,'1','fa fa-plane-departure',63
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code`='Workforce/worker_leave_absence.php');
INSERT INTO `modules` (`name`,`code`,`owner_role_id`,`is_link`,`icon`,`display_order`)
SELECT 'التحرّك والنقل','Workforce/worker_movement.php',4,'1','fa fa-route',64
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code`='Workforce/worker_movement.php');
INSERT INTO `modules` (`name`,`code`,`owner_role_id`,`is_link`,`icon`,`display_order`)
SELECT 'وحدات السكن','Workforce/housing_units.php',4,'1','fa fa-building',69
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code`='Workforce/housing_units.php');

INSERT INTO `role_permissions` (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
SELECT 4, m.id, 1,1,1,1 FROM `modules` m
WHERE m.code IN ('Workforce/worker_leave_absence.php','Workforce/worker_movement.php','Workforce/housing_units.php')
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` rp WHERE rp.role_id=4 AND rp.module_id=m.id);

-- ════════════════════════════════════════════════════════════════════════════
-- التراجع:
--   DROP VIEW IF EXISTS `v_worker_presence`, `v_worker_billable_hours`;
--   DROP TABLE IF EXISTS `worker_movement`, `worker_leave_absence`, `housing_unit`;
--   DELETE rp FROM role_permissions rp JOIN modules m ON m.id=rp.module_id
--     WHERE m.code IN ('Workforce/worker_leave_absence.php','Workforce/worker_movement.php','Workforce/housing_units.php');
--   DELETE FROM modules WHERE code IN ('Workforce/worker_leave_absence.php','Workforce/worker_movement.php','Workforce/housing_units.php');
-- ════════════════════════════════════════════════════════════════════════════
