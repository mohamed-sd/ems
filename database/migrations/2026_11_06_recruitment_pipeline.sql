-- ═══════════════════════════════════════════════════════════════════════════
-- دورةُ التوظيف العشرُ الخطوات (NAV-02 §12-① · TARGET «دورة التوظيف» ★) — 2026-08-02
-- «القائمةُ تبدأ من الإجازات — أي من منتصف دورة الخدمة»: عشرُ خطواتٍ من
-- طلب الشاغر إلى فترة التجربة — والمباشرةُ تربط بموظفٍ حقيقي.
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `rec_vacancies` (
  `vac_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `vacancy_no`  VARCHAR(30) NOT NULL,
  `job_title_id` INT NULL COMMENT 'job_titles — قاموسُ المسميات (SEC-01)',
  `title_text`  VARCHAR(120) NOT NULL,
  `org_unit_id` INT NULL COMMENT 'org_units — الإدارةُ الطالبة',
  `site_scope`  VARCHAR(80) NULL,
  `headcount`   INT NOT NULL DEFAULT 1,
  `reason`      VARCHAR(255) NULL,
  `state`       ENUM('draft','open','filled','cancelled') NOT NULL DEFAULT 'open',
  `posted_at`   DATE NULL COMMENT '② نشرُ الوظيفة',
  `created_by`  INT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`vac_id`),
  UNIQUE KEY `uq_vac` (`company_id`, `vacancy_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='① طلبُ الشاغر — أولُ الدورة العشرية';

CREATE TABLE IF NOT EXISTS `rec_applications` (
  `app_id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `vac_id`     INT UNSIGNED NOT NULL,
  `applicant_name`  VARCHAR(120) NOT NULL,
  `applicant_phone` VARCHAR(40) NULL,
  `cv_ref`     VARCHAR(190) NULL COMMENT '③ السيرةُ الذاتية — مرجعُ الملف',
  `stage`      ENUM('received','screening','interview','practical_test','offer',
                    'offer_accepted','contracting','onboarded','probation','confirmed',
                    'rejected','withdrawn') NOT NULL DEFAULT 'received'
               COMMENT 'الخطواتُ ③→⑩ — والرفضُ والانسحابُ خروجان معلَنان',
  `stage_note` VARCHAR(255) NULL,
  `interview_at`  DATETIME NULL,
  `test_score`    DECIMAL(5,2) NULL COMMENT '⑥ الاختبارُ العمليُّ للمشغّل',
  `offer_ref`     VARCHAR(120) NULL,
  `employee_id`   INT NULL COMMENT '⑨ المباشرة — يربط بموظفٍ حقيقيٍّ في employees',
  `probation_end` DATE NULL COMMENT '⑩ نهايةُ فترة التجربة',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`app_id`),
  KEY `ix_app_vac` (`vac_id`, `stage`),
  CONSTRAINT `fk_app_vac` FOREIGN KEY (`vac_id`) REFERENCES `rec_vacancies`(`vac_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rec_stage_log` (
  `log_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `app_id`   INT UNSIGNED NOT NULL,
  `from_stage` VARCHAR(30) NULL,
  `to_stage`   VARCHAR(30) NOT NULL,
  `note`     VARCHAR(255) NULL,
  `by_person` INT NULL,
  `at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `ix_rsl_app` (`app_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سجلُّ التقدم — Insert-only: من فعل ماذا ومتى';
