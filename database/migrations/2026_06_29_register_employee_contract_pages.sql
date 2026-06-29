-- Migration: تسجيل صفحات عقود/بطاقات الموظفين كموديولات لإغلاق ثغرة fail-open — 2026-06-29
-- هذه الصفحات تُضمّن insidebar.php الذي يستدعي enforce_current_page_view_permission،
-- فبمجرّد تسجيلها كموديول يُفرَض حارس العرض المركزي تلقائياً (لا حاجة لكود في كل صفحة).
-- المنح: عقود الموظفين → الدور 4 (HR) كل الصلاحيات؛ بقية أدوار مشاهدي صفحة الموظفين → عرض فقط
--        (يحفظ التصفّح ويقصر الكتابة على HR عبر حارس POST في الملف). الصفحات العارضة → عرض فقط.
-- idempotent.
SET NAMES utf8mb4;

-- ════════ 1) Employees/employee_contracts.php (كتابة عقود — حساسة) ════════
INSERT INTO `modules` (`code`,`name`,`owner_role_id`,`is_link`,`icon`,`display_order`)
SELECT 'Employees/employee_contracts.php','عقود الموظفين',4,'0','fa fa-file-contract',0
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code`='Employees/employee_contracts.php');
SET @m_ec := (SELECT id FROM `modules` WHERE `code`='Employees/employee_contracts.php' ORDER BY id LIMIT 1);
-- HR (الدور 4): كل الصلاحيات
INSERT INTO `role_permissions` (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
SELECT 4,@m_ec,1,1,1,1 WHERE NOT EXISTS (SELECT 1 FROM `role_permissions` WHERE role_id=4 AND module_id=@m_ec);
-- بقية مشاهدي صفحة الموظفين: عرض فقط (لا كتابة)
INSERT INTO `role_permissions` (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
SELECT DISTINCT rp.role_id,@m_ec,1,0,0,0
FROM `role_permissions` rp JOIN `modules` m ON m.id=rp.module_id
WHERE m.`code`='Employees/employees.php' AND rp.can_view=1 AND rp.role_id<>4
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` x WHERE x.role_id=rp.role_id AND x.module_id=@m_ec);

-- ════════ 2) الصفحات العارضة (عرض فقط لكل مشاهدي صفحة الموظفين) ════════
-- employee_contracts_details.php
INSERT INTO `modules` (`code`,`name`,`owner_role_id`,`is_link`,`icon`,`display_order`)
SELECT 'Employees/employee_contracts_details.php','تفاصيل عقد الموظف',4,'0','fa fa-file-alt',0
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code`='Employees/employee_contracts_details.php');
SET @m_d := (SELECT id FROM `modules` WHERE `code`='Employees/employee_contracts_details.php' ORDER BY id LIMIT 1);
INSERT INTO `role_permissions` (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
SELECT DISTINCT rp.role_id,@m_d,1,0,0,0 FROM `role_permissions` rp JOIN `modules` m ON m.id=rp.module_id
WHERE m.`code`='Employees/employees.php' AND rp.can_view=1
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` x WHERE x.role_id=rp.role_id AND x.module_id=@m_d);

-- employee_profile.php
INSERT INTO `modules` (`code`,`name`,`owner_role_id`,`is_link`,`icon`,`display_order`)
SELECT 'Employees/employee_profile.php','بطاقة الموظف',4,'0','fa fa-id-card',0
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code`='Employees/employee_profile.php');
SET @m_p := (SELECT id FROM `modules` WHERE `code`='Employees/employee_profile.php' ORDER BY id LIMIT 1);
INSERT INTO `role_permissions` (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
SELECT DISTINCT rp.role_id,@m_p,1,0,0,0 FROM `role_permissions` rp JOIN `modules` m ON m.id=rp.module_id
WHERE m.`code`='Employees/employees.php' AND rp.can_view=1
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` x WHERE x.role_id=rp.role_id AND x.module_id=@m_p);

-- showcontractemployee.php
INSERT INTO `modules` (`code`,`name`,`owner_role_id`,`is_link`,`icon`,`display_order`)
SELECT 'Employees/showcontractemployee.php','عرض عقد الموظف',4,'0','fa fa-file',0
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code`='Employees/showcontractemployee.php');
SET @m_s := (SELECT id FROM `modules` WHERE `code`='Employees/showcontractemployee.php' ORDER BY id LIMIT 1);
INSERT INTO `role_permissions` (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
SELECT DISTINCT rp.role_id,@m_s,1,0,0,0 FROM `role_permissions` rp JOIN `modules` m ON m.id=rp.module_id
WHERE m.`code`='Employees/employees.php' AND rp.can_view=1
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` x WHERE x.role_id=rp.role_id AND x.module_id=@m_s);
