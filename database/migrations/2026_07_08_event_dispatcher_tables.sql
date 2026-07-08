-- ═══════════════════════════════════════════════════════════════════════════
-- K4 (ADR-10 قاعدة §8.1-5/7) — بنية الموزّع: سجلّ مستهلكين بمؤشر موضعٍ مستقل
-- لكل مستهلك + تتبّع محاولات التسليم + الرسائل الميتة (Dead-Letter).
-- جداول جديدة صرفة (لا مساس بأي قائم). Rollback: backups/K4_down_event_dispatcher_tables.sql
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE `ems_event_consumers` (
  `consumer`        VARCHAR(64)     NOT NULL COMMENT 'اسم المستهلك المسجَّل (finance, analytics, …)',
  `enabled`         TINYINT(1)      NOT NULL DEFAULT 1,
  `cursor_event_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'آخر حدثٍ عولج بنجاح/تُجووز — مستقل لكل مستهلك',
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`consumer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='K4: سجلّ مستهلكي الناقل المؤسسي — Cursor مستقل لكل مستهلك';

CREATE TABLE `ems_event_deliveries` (
  `consumer`   VARCHAR(64)     NOT NULL,
  `event_id`   BIGINT UNSIGNED NOT NULL,
  `attempts`   INT UNSIGNED    NOT NULL DEFAULT 1,
  `last_error` VARCHAR(500)    NULL DEFAULT NULL,
  `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`consumer`, `event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='K4: محاولات تسليمٍ جارية (تُحذف عند النجاح أو تنتقل للرسائل الميتة)';

CREATE TABLE `ems_event_dead_letter` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `consumer`   VARCHAR(64)     NOT NULL,
  `event_id`   BIGINT UNSIGNED NOT NULL,
  `attempts`   INT UNSIGNED    NOT NULL,
  `last_error` VARCHAR(500)    NULL DEFAULT NULL,
  `failed_at`  DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dlq_consumer_event` (`consumer`, `event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='K4: الحدث السام يُعزل هنا بعد استنفاد المحاولات — الطابور لا يتجمّد خلفه';
