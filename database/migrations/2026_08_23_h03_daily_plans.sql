-- ═══════════════════════════════════════════════════════════════════════════
-- H-03 · دورةُ التوزيع اليومية — خطةُ عمل الغد — 2026-07-30
-- البطاقة: docs/specs/H-03_daily_plan.md · المصدر: UX-03 §2.2 · OPM-01 §6
-- ───────────────────────────────────────────────────────────────────────────
-- «احتياجُ الغد (معدة×وردية) ← توزيعُ المشغّلين بتحذير تعارضٍ فوري ←
--  اعتمادُ الحركة ← فتحُ يوم الغد» — و«لا يُفتح تسجيلٌ لموقعٍ ناقص التخصيص».
-- الفجوةُ المقيسة: لا خطةَ يوميةَ إطلاقًا، والاشتقاقُ الآلي للمشغّل ينتظرها نصًّا.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `daily_plans` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `project_id` INT NOT NULL,
  `plan_date` DATE NOT NULL,
  `state` ENUM('draft','approved','opened','closed') NOT NULL DEFAULT 'draft'
      COMMENT 'الدورة: توزيعٌ (draft) ← اعتمادُ الحركة ← فتحُ الغد ← إقفالُ يومه',
  `reopen_reason` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT NULL DEFAULT NULL,
  `approved_by` INT NULL DEFAULT NULL,
  `approved_at` DATETIME NULL DEFAULT NULL,
  `opened_at` DATETIME NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dp_project_date` (`project_id`, `plan_date`) COMMENT 'خطةٌ واحدةٌ ليومِ المشروع',
  KEY `ix_dp_company` (`company_id`),
  KEY `ix_dp_state_date` (`state`, `plan_date`),
  CONSTRAINT `fk_dp_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `daily_plan_lines` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `plan_id` INT NOT NULL,
  `equipment_container_id` INT UNSIGNED NOT NULL COMMENT 'حاويةُ المعدة — مصدرُ الاحتياج (OPM-01 §4)',
  `equipment_id` INT UNSIGNED NULL DEFAULT NULL,
  `shift_no` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `operator_employee_id` INT NULL DEFAULT NULL,
  `operator_container_id` INT UNSIGNED NULL DEFAULT NULL
      COMMENT '«لا تخصيصَ خارج حاوية» — حاويةُ المشغّل من سلسلة معدته حصرًا',
  `note` VARCHAR(200) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dpl_need` (`plan_id`, `equipment_container_id`, `shift_no`) COMMENT 'احتياجُ (معدة×وردية) لا يتكرر',
  KEY `ix_dpl_company` (`company_id`),
  KEY `ix_dpl_operator` (`operator_employee_id`),
  KEY `ix_dpl_equipment` (`equipment_id`, `shift_no`),
  CONSTRAINT `fk_dpl_plan` FOREIGN KEY (`plan_id`) REFERENCES `daily_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dpl_eq_container` FOREIGN KEY (`equipment_container_id`) REFERENCES `op_containers` (`id`),
  CONSTRAINT `fk_dpl_op_container` FOREIGN KEY (`operator_container_id`) REFERENCES `op_containers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تسجيلُ شاشة «خطة عمل الغد — التوزيع» (الوحدة 152 — «مساحة التوزيع» UX-03 §3)
-- لمالكها إدارة التشغيل (3) — ومديرُ الموقع (5) عرضًا (يقرأ غدَه).
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 152, 'خطة عمل الغد', 'Operations/daily_plan.php', 3, 0, 0, 'fa fa-calendar-day', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Operations/daily_plan.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 152, 1, r.a, r.e, 0
  FROM (SELECT 3 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 5, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 152);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'DAILY', NULL, 152, 'خطة عمل الغد', 'Operations/daily_plan.php',
       'fa fa-calendar-day', 15, NULL, 'Operations/daily_plan.php', 1
  FROM (SELECT 3 AS rid UNION ALL SELECT 5) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Operations/daily_plan.php');
