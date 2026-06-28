-- ════════════════════════════════════════════════════════════════════════════
-- 2026-06-25 — طبقة القوى التشغيلية (EQUIP-OPE-S04) · الموجة 5
-- السجل التشغيلي المجمَّع (8.9) — VIEW محسوبٌ للقراءة فقط (نقطة الحقيقة الواحدة)
-- ════════════════════════════════════════════════════════════════════════════
-- يعتمد على v_worker_billable_hours (الموجة 3). صفر ALTER. للمراجعة قبل التطبيق.
-- ════════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

CREATE OR REPLACE VIEW `v_worker_worklog` AS
SELECT
  wp.id           AS worker_id,
  wp.employee_id  AS employee_id,
  e.name          AS worker_name,
  wp.worker_category,
  wp.state        AS worker_state,
  (SELECT COUNT(DISTINCT a.operation_id) FROM worker_allocation a WHERE a.worker_id = wp.id) AS operations_count,
  (SELECT COALESCE(SUM(b.billable_baseline),0) FROM v_worker_billable_hours b WHERE b.worker_id = wp.id) AS total_billable_hours,
  (SELECT COUNT(*) FROM worker_leave_absence la WHERE la.worker_id = wp.id) AS leave_absence_count,
  (SELECT COUNT(*) FROM worker_movement m WHERE m.worker_id = wp.id) AS movement_count,
  (SELECT COUNT(*) FROM worker_evaluation ev WHERE ev.worker_id = wp.id) AS evaluation_count,
  (SELECT COALESCE(SUM(ev.amount),0) FROM worker_evaluation ev WHERE ev.worker_id = wp.id AND ev.incentive_penalty_type = 'حافز') AS incentive_total,
  (SELECT COALESCE(SUM(ev.amount),0) FROM worker_evaluation ev WHERE ev.worker_id = wp.id AND ev.incentive_penalty_type = 'جزاء') AS penalty_total
FROM worker_profile wp
LEFT JOIN employees e ON e.id = wp.employee_id;  /* FUTURE-MERGE: employee_id<->worker_id bridge — to be collapsed */

-- الملكية لإدارة الموارد البشرية = الدور 4 (تحقّق عكسيٌّ من جدول roles).
INSERT INTO `modules` (`name`,`code`,`owner_role_id`,`is_link`,`icon`,`display_order`)
SELECT 'السجل التشغيلي','Workforce/worker_worklog.php',4,'1','fa fa-clock-rotate-left',68
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code`='Workforce/worker_worklog.php');

INSERT INTO `role_permissions` (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
SELECT 4, m.id, 1,0,0,0 FROM `modules` m
WHERE m.code='Workforce/worker_worklog.php'
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` rp WHERE rp.role_id=4 AND rp.module_id=m.id);

-- التراجع:
--   DROP VIEW IF EXISTS `v_worker_worklog`;
--   DELETE rp FROM role_permissions rp JOIN modules m ON m.id=rp.module_id WHERE m.code='Workforce/worker_worklog.php';
--   DELETE FROM modules WHERE code='Workforce/worker_worklog.php';
