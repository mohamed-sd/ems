-- =====================================================================
--  الموجة 2 — Part A: إعادة تسمية أعمدة المفاتيح/الوصف (driver_* → employee_*)
--  تغيير تجميلي بحت (لا منطق/بيانات). نُفّذ مع تحويل كامل لمراجع الكود (شامل API).
--  ⚠️ timesheet.operator لم يُمسّ (يحمل operations.id لا شخصاً).
--  جدول الأرشيف drivers_legacy_backup تُرك كما هو.
--  MariaDB 10.4 · DB: equipation_manage
-- =====================================================================
SET NAMES utf8mb4;

-- 1) أعمدة المفاتيح (تشير إلى employees.id)
ALTER TABLE `equipment_drivers` CHANGE `driver_id` `employee_id` INT(11) NOT NULL;
ALTER TABLE `drivercontracts`   CHANGE `driver_id` `employee_id` INT(11) NOT NULL;
ALTER TABLE `timesheet`         CHANGE `driver`    `employee_id` VARCHAR(20) NOT NULL;

-- 2) أعمدة الوصف داخل employees
ALTER TABLE `employees` CHANGE `driver_code`   `employee_code`   VARCHAR(50)  NULL;
ALTER TABLE `employees` CHANGE `driver_status` `employee_status` VARCHAR(50)  NULL DEFAULT 'نشط';
ALTER TABLE `employees` CHANGE `driver_photo`  `employee_photo`  VARCHAR(255) NULL;

-- =====================================================================
--  Part B (إعادة تسمية مجلد Drivers/ → Employees/ وملفّاته + modules.code)
--  مؤجَّلة — انظر الملخّص (خطر تعارض مع عمليات استرجاع/تراجع الملفّات).
-- =====================================================================
