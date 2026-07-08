-- ═══════════════════════════════════════════════════════════════════════════
-- K6 (ADR-07 · جسر المنصب) — «الصلاحية على المنصب» طبقةً فوق نظام الأدوار
-- القائم **دون كسره**: users.role يبقى الفاعل؛ position_id جسرٌ nullable
-- والشاشات القائمة لا تُجبر على التبني (الاستهلاك التدريجي موثَّق في الجسر).
-- «المنصب» تنظيميٌّ وصولي (يمنح دور نظام) — يختلف عن job_titles (مسمّيات HR)
-- ويرتبط بها اختياريًا. users.role_id القديم شبه الفارغ أثريٌّ ولا يُمسّ.
-- Rollback: database/backups/K6_down_positions_bridge.sql (مُختبَر على نسخة).
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE `positions` (
  `id`           INT          NOT NULL AUTO_INCREMENT,
  `company_id`   INT          NOT NULL,
  `name`         VARCHAR(120) NOT NULL COMMENT 'اسم المنصب التنظيمي (مثال: مدير حركة موقع MB1)',
  `role_id`      INT          NOT NULL COMMENT 'دور النظام الذي يمنحه المنصب — جسرٌ إلى roles.id لا بديل عنه',
  `job_title_id` INT          NULL DEFAULT NULL COMMENT 'ربط اختياري بالمسمى الوظيفي HR (job_titles.id)',
  `description`  VARCHAR(255) NULL DEFAULT NULL,
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `is_deleted`   TINYINT(1)   NOT NULL DEFAULT 0,
  `deleted_at`   DATETIME     NULL DEFAULT NULL,
  `deleted_by`   INT          NULL DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_positions_company` (`company_id`),
  KEY `idx_positions_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='K6/ADR-07: المناصب — طبقة الصلاحية على المنصب فوق الأدوار';

ALTER TABLE `users`
  ADD COLUMN `position_id` INT NULL DEFAULT NULL
    COMMENT 'جسر المنصب (ADR-07/K6) — nullable: NULL = السلوك القائم عبر role كما هو'
    AFTER `role_id`,
  ADD KEY `idx_users_position` (`position_id`);
