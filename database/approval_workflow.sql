-- Multi-Level Approval Workflow Schema
-- تاريخ الإنشاء: 2026-03-03

CREATE TABLE IF NOT EXISTS approval_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    payload LONGTEXT NOT NULL,
    requested_by INT NOT NULL,
    current_step INT DEFAULT 1,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL,
    approved_at DATETIME NULL,
    rejected_at DATETIME NULL,
    executed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_approval_entity (entity_type, entity_id),
    INDEX idx_approval_status (status),
    INDEX idx_approval_user (requested_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    role_required VARCHAR(100) NOT NULL,
    step_order INT NOT NULL,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_approval_steps_request
        FOREIGN KEY (request_id) REFERENCES approval_requests(id)
        ON DELETE CASCADE,
    INDEX idx_approval_steps_request (request_id),
    INDEX idx_approval_steps_status (status),
    INDEX idx_approval_steps_order (step_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول مرن لتعريف مراحل الموافقة لكل نوع عملية بدون تعديل المنطق البرمجي
CREATE TABLE IF NOT EXISTS approval_workflow_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    role_required VARCHAR(100) NOT NULL,
    step_order INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_workflow_rule (entity_type, action, step_order),
    INDEX idx_workflow_rule_lookup (entity_type, action, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- قواعد افتراضية (يمكن تعديلها من قاعدة البيانات لاحقاً)
INSERT INTO approval_workflow_rules (entity_type, action, role_required, step_order)
SELECT * FROM (
    SELECT 'project' AS entity_type, 'update' AS action, '1,-1' AS role_required, 1 AS step_order
    UNION ALL SELECT 'project', 'deactivate', '1,-1', 1
    UNION ALL SELECT 'project', 'delete', '-1', 1
    UNION ALL SELECT 'contract', 'renewal', '1,-1', 1
    UNION ALL SELECT 'contract', 'settlement', '1,-1', 1
    UNION ALL SELECT 'contract', 'pause', '1,-1', 1
    UNION ALL SELECT 'contract', 'resume', '1,-1', 1
    UNION ALL SELECT 'contract', 'terminate', '1,-1', 1
    UNION ALL SELECT 'contract', 'merge', '-1', 1
    UNION ALL SELECT 'contract', 'update_project_info', '1,-1', 1
    UNION ALL SELECT 'contract', 'update_services', '1,-1', 1
    UNION ALL SELECT 'contract', 'update_parties', '1,-1', 1
    UNION ALL SELECT 'contract', 'update_payment', '1,-1', 1
    UNION ALL SELECT 'contract', 'complete', '1,-1', 1
    UNION ALL SELECT 'timesheet', 'approve', '7,8,-1', 1
    UNION ALL SELECT 'timesheet', 'reject', '7,8,-1', 1
    UNION ALL SELECT 'driver', 'deactivate_driver', '3,-1', 1
    UNION ALL SELECT 'driver', 'reactivate_driver', '3,-1', 1
    UNION ALL SELECT 'equipment', 'deactivate_equipment', '4,-1', 1
    UNION ALL SELECT 'equipment', 'reactivate_equipment', '4,-1', 1
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM approval_workflow_rules r
    WHERE r.entity_type = seed.entity_type
      AND r.action = seed.action
      AND r.step_order = seed.step_order
);
