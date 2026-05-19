-- ════════════════════════════════════════════════════════════════════════════
-- Activity Logs Table — EMS Centralized Activity Tracking
-- Migration: create_activity_logs
-- Date: 2026-05-19
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Context identifiers
  `company_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
  `project_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
  `contract_id`      BIGINT UNSIGNED NULL DEFAULT NULL,

  -- Actor
  `user_id`          BIGINT UNSIGNED NULL DEFAULT NULL,
  `role_id`          BIGINT UNSIGNED NULL DEFAULT NULL,
  `role_name`        VARCHAR(255)     NULL DEFAULT NULL,

  -- Request metadata
  `session_id`       VARCHAR(255)     NULL DEFAULT NULL,
  `ip_address`       VARCHAR(45)      NULL DEFAULT NULL,
  `user_agent`       TEXT             NULL DEFAULT NULL,

  -- Navigation context
  `screen_name`      VARCHAR(255)     NULL DEFAULT NULL,
  `module_name`      VARCHAR(255)     NULL DEFAULT NULL,

  -- Action
  `action_type`      VARCHAR(50)      NULL DEFAULT NULL,
  `button_name`      VARCHAR(255)     NULL DEFAULT NULL,
  `field_name`       VARCHAR(255)     NULL DEFAULT NULL,

  -- Target record
  `record_id`        BIGINT UNSIGNED  NULL DEFAULT NULL,

  -- Diff payload
  `old_value`        JSON             NULL DEFAULT NULL,
  `new_value`        JSON             NULL DEFAULT NULL,

  -- HTTP context
  `url`              TEXT             NULL DEFAULT NULL,
  `http_method`      VARCHAR(10)      NULL DEFAULT NULL,
  `request_payload`  JSON             NULL DEFAULT NULL,
  `response_status`  INT              NULL DEFAULT NULL,

  `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  -- Performance indexes
  INDEX `idx_company_created`      (`company_id`,   `created_at`),
  INDEX `idx_user_created`         (`user_id`,       `created_at`),
  INDEX `idx_role_created`         (`role_id`,       `created_at`),
  INDEX `idx_action_created`       (`action_type`,   `created_at`),
  INDEX `idx_module_screen_created`(`module_name`,   `screen_name`, `created_at`),
  INDEX `idx_record_module`        (`record_id`,     `module_name`),
  INDEX `idx_created_at`           (`created_at`),
  INDEX `idx_screen_name`          (`screen_name`),
  INDEX `idx_module_name`          (`module_name`),
  INDEX `idx_action_type`          (`action_type`),
  INDEX `idx_record_id`            (`record_id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سجل نشاطات المستخدمين — Activity Tracking Log';

-- ════════════════════════════════════════════════════════════════════════════
-- Register the activity_logs page in the modules navigation table.
-- owner_role_id = -1 (super-admin). Adjust the role ID or insert multiple
-- rows if other roles should also see the screen.
-- Run only once; INSERT IGNORE prevents duplicate errors.
-- ════════════════════════════════════════════════════════════════════════════
-- Insert without optional columns that may not exist yet.
-- If your modules table has display_order / status, add them manually.
INSERT IGNORE INTO `modules`
    (`name`, `code`, `owner_role_id`, `is_link`, `icon`)
VALUES
    ('سجل النشاطات', 'ActivityLogs/activity_logs.php', -1, '1', 'fa fa-history');
