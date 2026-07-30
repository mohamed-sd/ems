-- ═══════════════════════════════════════════════════════════════════════════
-- H-05 · الموقع/المنجم كيانًا مستقلًّا في الهرم السباعي — 2026-07-30
-- البطاقة: docs/specs/H-05_sites_entity.md · المصدر: OPM-01 §2-③
-- ───────────────────────────────────────────────────────────────────────────
-- **الفجوة المقيسة**: لا جدولَ مواقع — «المشروعُ هو الموقعُ ضمنًا»، والهرمُ
-- ينصّ: المشروعُ قد يضم عدةَ مواقعَ، والموقعُ قد تكون له عدةُ عقود.
-- **التعبئة الصادقة**: موقعٌ افتراضيٌّ واحدٌ لكل مشروعٍ باسمه (is_default=1)
-- وكلُّ عقدٍ يُربط بموقع مشروعه الافتراضي — صفرُ تغييرٍ دلاليٍّ على القائم.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `sites` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `project_id` INT NOT NULL,
  `name` VARCHAR(190) NOT NULL,
  `site_kind` ENUM('mine','site') NOT NULL DEFAULT 'site'
      COMMENT 'H-05: «المنجمُ حالةٌ من الموقع لا فرقَ في المعالجة» — تمييزٌ عرضيٌّ؛ التعريب في الشاشة',
  `responsible_employee_id` INT NULL DEFAULT NULL
      COMMENT 'مسؤولُ الموقع — مدخلُ E-07/H-03',
  `location_text` VARCHAR(255) NULL DEFAULT NULL,
  `lat` DECIMAL(10,7) NULL DEFAULT NULL,
  `lng` DECIMAL(10,7) NULL DEFAULT NULL,
  `status` TINYINT NOT NULL DEFAULT 1,
  `is_default` TINYINT NOT NULL DEFAULT 0
      COMMENT 'موقعُ الترحيل الرجعي: المشروعُ كان الموقعَ ضمنًا',
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_site_name` (`company_id`, `project_id`, `name`),
  KEY `ix_sites_project` (`project_id`),
  KEY `ix_sites_company` (`company_id`),
  CONSTRAINT `fk_sites_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`),
  CONSTRAINT `fk_sites_resp` FOREIGN KEY (`responsible_employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- موقعٌ افتراضيٌّ لكل مشروعٍ قائم (الاسمُ اسمُ المشروع — لا اختراع)
INSERT INTO `sites` (`company_id`, `project_id`, `name`, `site_kind`, `status`, `is_default`)
SELECT p.`company_id`, p.`id`, p.`name`, 'site', 1, 1
FROM `project` p
WHERE COALESCE(p.`is_deleted`, 0) = 0
  AND NOT EXISTS (SELECT 1 FROM `sites` s WHERE s.`project_id` = p.`id` AND s.`is_default` = 1);

-- ربطُ العقود بموقع مشروعها الافتراضي (NULL يبقى مسموحًا حتى إلزام H-03)
ALTER TABLE `contracts`
  ADD COLUMN `site_id` INT NULL DEFAULT NULL
      COMMENT 'H-05: موقعُ التنفيذ (الهرم ③) — إلزامُه يأتي مع H-03' AFTER `project_id`,
  ADD KEY `ix_contracts_site` (`site_id`),
  ADD CONSTRAINT `fk_contracts_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`);

UPDATE `contracts` c
JOIN `sites` s ON s.`project_id` = c.`project_id` AND s.`is_default` = 1
SET c.`site_id` = s.`id`
WHERE c.`site_id` IS NULL;
