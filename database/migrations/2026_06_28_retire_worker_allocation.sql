-- Migration: تقاعد طبقة التخصيص worker_allocation والاعتماد على equipment_drivers — 2026-06-28
-- القرار: إزالة فكرة «التخصيص» كجدولٍ مستقل (worker_allocation) والاعتماد على جدول
-- equipment_drivers (العامل↔الآلية) كمصدرٍ وحيدٍ لكل ما يخصّ الإسناد.
-- worker_allocation كان فارغاً (0 صف) فلا ترحيل بيانات.
SET NAMES utf8mb4;

-- 1) إعادة بناء الـViews لتعتمد على equipment_drivers بدل worker_allocation:
--    v_worker_presence: «داخل الموقع» = وجود إسناد نشط في equipment_drivers (status=1).
--    v_worker_worklog: operations_count = عدد عمليات آليات الموظف النشطة (عبر operations.equipment).
--    (التعريفات الكاملة طُبِّقت عبر CREATE OR REPLACE VIEW — انظر سكربت التطبيق.)

-- 2) إسقاط مفاتيح worker_allocation ثم الجدول:
ALTER TABLE `worker_allocation` DROP FOREIGN KEY `fk_wa_lead`;
ALTER TABLE `worker_allocation` DROP FOREIGN KEY `fk_wa_emp`;
DROP TABLE IF EXISTS `worker_allocation`;

-- 3) إزالة موديول الشاشة من القائمة والصلاحيات (الشاشة أصبحت تحويلةً لوحدة الحركة):
DELETE rp FROM `role_permissions` rp JOIN `modules` m ON m.id = rp.module_id
  WHERE m.code = 'Workforce/worker_allocation.php';
DELETE FROM `modules` WHERE code = 'Workforce/worker_allocation.php';

-- ملاحظات الكود المصاحبة:
--   PlanningService.ems_planning_available  → يعدّ equipment_drivers (status=1) لكل مشروع/فئة.
--   QuotaService.ems_quota_current_for_operation → يعدّ سائقي آلية العملية النشطين.
--   worker_worklog.php مؤشّر «مخصَّصون» → COUNT(DISTINCT employee_id) FROM equipment_drivers WHERE status=1.
--   worker_movement.php → حُذف حقل «تخصيص مرتبط» (allocation_id) من النموذج/الحفظ/العرض.
--   Workforce/worker_allocation.php → تحويلةٌ إلى movement/movement_operations.php.
