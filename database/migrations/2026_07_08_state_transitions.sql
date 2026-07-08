-- ═══════════════════════════════════════════════════════════════════════════
-- K7 (ADR-08 · محرّك الحالات المركزي — النطاق الضيّق المُقرّ 2026-07-08):
-- سجل تدقيق انتقالات الحالات append-only. **للوحدات الجديدة والمتبنّين وقت
-- هجرتهم حصرًا** — الأنظمة الحية الثلاثة (الموافقات الأربع · approval_* العام
-- في movement/Oprators · fin_event_flow) لا تُمسّ (خطة §9.1).
-- جدول جديد صرف. Rollback: backups/K7_down_state_transitions.sql
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE `ems_state_transitions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workflow`      VARCHAR(64)     NOT NULL COMMENT 'اسم تعريف سير العمل',
  `entity_table`  VARCHAR(64)     NOT NULL,
  `entity_id`     BIGINT UNSIGNED NOT NULL,
  `company_id`    INT             NULL DEFAULT NULL,
  `from_state`    VARCHAR(40)     NOT NULL COMMENT 'أكواد لاتينية (ADR-08 — الجديد فقط)',
  `to_state`      VARCHAR(40)     NOT NULL,
  `action`        VARCHAR(64)     NOT NULL,
  `actor_user_id` INT             NOT NULL,
  `actor_role`    VARCHAR(10)     NOT NULL COMMENT 'الدور الفعّال لحظة الانتقال (من جسر K6)',
  `actor_source`  VARCHAR(20)     NOT NULL DEFAULT 'role' COMMENT 'role|position — مصدر الدور الفعّال',
  `note`          VARCHAR(500)    NULL DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_st_entity` (`entity_table`, `entity_id`),
  KEY `idx_st_workflow_time` (`workflow`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='K7: سجل انتقالات محرك الحالات — append-only، لا يُعدَّل ولا يُحذف منه';
