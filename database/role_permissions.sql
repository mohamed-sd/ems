-- ═══════════════════════════════════════════════════════════════════════════
-- جدول صلاحيات الأدوار - Role Permissions
-- ⭐ أهم جدول في النظام - يحدد بالضبط ماذا يمكن لكل دور أن يفعل
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id`        INT PRIMARY KEY AUTO_INCREMENT,
  `role_id`   INT NOT NULL,
  `module_id` INT NOT NULL,
  `can_view`  BOOLEAN DEFAULT FALSE COMMENT '👁️ يمكن العرض',
  `can_add`   BOOLEAN DEFAULT FALSE COMMENT '➕ يمكن الإضافة',
  `can_edit`  BOOLEAN DEFAULT FALSE COMMENT '✏️ يمكن التعديل',
  `can_delete` BOOLEAN DEFAULT FALSE COMMENT '🗑️ يمكن الحذف',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_role_module` (`role_id`, `module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════
-- فهرسة إضافية لتحسين الأداء
-- ═══════════════════════════════════════════════════════════════════════════
CREATE INDEX `idx_role_id` ON `role_permissions` (`role_id`);
CREATE INDEX `idx_module_id` ON `role_permissions` (`module_id`);
