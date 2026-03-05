-- إنشاء جدول modules (الصفحات)
CREATE TABLE IF NOT EXISTS `modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'اسم الصفحة',
  `code` varchar(255) NOT NULL COMMENT 'رابط الصفحة مثل Clients/clients.php',
  `owner_role_id` int(11) NOT NULL COMMENT 'معرف الدور المسؤول عن الصفحة',
  `icon` varchar(100) DEFAULT 'fa fa-link' COMMENT 'أيقونة FontAwesome',
  `status` tinyint(1) DEFAULT 1 COMMENT '1 = نشط، 0 = معطل',
  `sort_order` int(11) DEFAULT 0 COMMENT 'ترتيب العرض',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`owner_role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول الصفحات والروابط حسب الأدوار';

-- ================================================================
-- بيانات تجريبية (يمكن حذفها أو تعديلها حسب احتياجاتك)
-- ================================================================

-- روابط للمسؤول الأعلى (role_id = -1)
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `icon`, `status`, `sort_order`) VALUES
('إضافة مشروع', 'Projects/add_project.php', -1, 'fa fa-plus-circle', 1, 1),
('قائمة المشاريع', 'Projects/view_projects.php', -1, 'fa fa-list-alt', 1, 2),
('إضافة عميل', 'Projects/add_client.php', -1, 'fa fa-user-plus', 1, 3),
('قائمة العملاء', 'Clients/clients.php', -1, 'fa fa-users', 1, 4),
('المشاريع', 'Projects/oprationprojects.php', -1, 'fa fa-folder-open', 1, 5),
('الموردين', 'Suppliers/suppliers.php', -1, 'fa fa-truck-loading', 1, 6),
('الآليات', 'Equipments/equipments.php', -1, 'fa fa-tractor', 1, 7),
('المشغلين', 'Drivers/drivers.php', -1, 'fa fa-id-card', 1, 8),
('التشغيل', 'Oprators/oprators.php', -1, 'fa fa-cogs', 1, 9),
('ساعات العمل', 'Timesheet/timesheet_type.php', -1, 'fa fa-business-time', 1, 10),
('ساعات اليوم', 'Timesheet/view_timesheet.php', -1, 'fa fa-calendar-days', 1, 11),
('المستخدمين', 'main/users.php', -1, 'fa fa-users-cog', 1, 12),
('التقارير', 'Reports/new_reports.php', -1, 'fa fa-chart-pie', 1, 13),
('تقارير العقود', 'Reports/reports.php', -1, 'fa fa-chart-pie', 1, 14);

-- روابط مدير المشاريع (role_id = 1)
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `icon`, `status`, `sort_order`) VALUES
('قائمة العملاء', 'Clients/clients.php', 1, 'fa fa-users', 1, 1),
('المشاريع', 'Projects/project_mines.php', 1, 'fa fa-list-alt', 1, 2),
('المستخدمين', 'main/users.php', 1, 'fa fa-users-cog', 1, 3),
('تقارير العقود', 'Reports/reports.php', 1, 'fa fa-chart-pie', 1, 4),
('أنواع المعدات', 'Equipments/equipments_types.php', 1, 'fa fa-screwdriver-wrench', 1, 5);

-- روابط مدير الموردين (role_id = 2)
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `icon`, `status`, `sort_order`) VALUES
('الموردين', 'Suppliers/suppliers.php', 2, 'fa fa-truck-loading', 1, 1),
('التقارير', 'Reports/reports.php', 2, 'fa fa-chart-pie', 1, 2);

-- روابط مدير المشغلين (role_id = 3)
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `icon`, `status`, `sort_order`) VALUES
('الآليات', 'Equipments/equipments.php', 3, 'fa fa-tractor', 1, 1),
('المشغلين', 'Drivers/drivers.php', 3, 'fa fa-id-card', 1, 2),
('التقارير', 'Reports/reports.php', 3, 'fa fa-chart-pie', 1, 3),
('طلبات الموافقات', 'Approvals/requests.php', 3, 'fa fa-check-double', 1, 4);

-- روابط مدير الأسطول (role_id = 4)
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `icon`, `status`, `sort_order`) VALUES
('الآليات', 'Equipments/equipments.php', 4, 'fa fa-tractor', 1, 1),
('التشغيل', 'Oprators/oprators.php', 4, 'fa fa-cogs', 1, 2),
('التقارير', 'Reports/reports.php', 4, 'fa fa-chart-pie', 1, 3),
('طلبات الموافقات', 'Approvals/requests.php', 4, 'fa fa-check-double', 1, 4);

-- روابط مدير الموقع (role_id = 5)
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `icon`, `status`, `sort_order`) VALUES
('المشرفين', 'main/project_users.php', 5, 'fa fa-users-cog', 1, 1),
('ساعات العمل', 'Timesheet/timesheet_type.php', 5, 'fa fa-business-time', 1, 2),
('ساعات اليوم', 'Timesheet/view_timesheet.php', 5, 'fa fa-calendar-days', 1, 3),
('التقارير', 'Reports/reports.php', 5, 'fa fa-chart-pie', 1, 4);

-- روابط مدخل الساعات (role_id = 6)
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `icon`, `status`, `sort_order`) VALUES
('ساعات العمل', 'Timesheet/timesheet_type.php', 6, 'fa fa-business-time', 1, 1),
('ساعات اليوم', 'Timesheet/view_timesheet.php', 6, 'fa fa-calendar-days', 1, 2);

-- روابط مراجع ساعات المورد والمشغل (role_id = 7, 8)
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `icon`, `status`, `sort_order`) VALUES
('ساعات العمل', 'Timesheet/timesheet_type.php', 7, 'fa fa-business-time', 1, 1),
('ساعات العمل', 'Timesheet/timesheet_type.php', 8, 'fa fa-business-time', 1, 1);

-- روابط مراجع الأعطال (role_id = 9)
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `icon`, `status`, `sort_order`) VALUES
('ساعات العمل', 'Timesheet/timesheet_type.php', 9, 'fa fa-business-time', 1, 1);

-- روابط مدير الحركة والتشغيل (role_id = 10)
INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `icon`, `status`, `sort_order`) VALUES
('التشغيل', 'Oprators/oprators.php', 10, 'fa fa-cogs', 1, 1),
('الآليات', 'Equipments/equipments.php', 10, 'fa fa-tractor', 1, 2);
