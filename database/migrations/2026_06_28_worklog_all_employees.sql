-- 2026-06-28 — السجل التشغيلي المجمَّع: المصدر = كل الموظفين (employees) وليس العاملين فقط.
-- قبل التوحيد كان v_worker_worklog/v_worker_presence مقيَّدَين بـ is_workforce=1 (subset «العاملون»).
-- المطلوب: أن يعكس السجل التشغيلي جدول الموظفين بالكامل (نزيل قيد is_workforce).
-- مُحدَّث: الإسناد صار من equipment_drivers (بعد تقاعد worker_allocation) — operations_count
--          و«داخل الموقع» يُشتقّان من equipment_drivers النشط (status=1) عبر operations.equipment.
-- Views فقط — صفر تغييرٍ على البيانات أو على الجداول. آمنة لإعادة التنفيذ (CREATE OR REPLACE).

CREATE OR REPLACE VIEW `v_worker_worklog` AS
SELECT
    `wp`.`id`                       AS `employee_id`,
    `wp`.`name`                     AS `worker_name`,
    COALESCE(`wp`.`worker_category`, 'موظف') AS `worker_category`,
    COALESCE(`wp`.`workforce_state`, '-')    AS `worker_state`,
    (SELECT COUNT(DISTINCT `o`.`id`) FROM `equipment_drivers` `ed` JOIN `operations` `o` ON `o`.`equipment` = `ed`.`equipment_id` WHERE `ed`.`employee_id` = `wp`.`id` AND `ed`.`status` = 1) AS `operations_count`,
    (SELECT COALESCE(SUM(`b`.`billable_baseline`), 0) FROM `v_worker_billable_hours` `b` WHERE `b`.`employee_id` = `wp`.`id`) AS `total_billable_hours`,
    (SELECT COUNT(0) FROM `worker_leave_absence` `la` WHERE `la`.`employee_id` = `wp`.`id`) AS `leave_absence_count`,
    (SELECT COUNT(0) FROM `worker_movement` `m` WHERE `m`.`employee_id` = `wp`.`id`) AS `movement_count`,
    (SELECT COUNT(0) FROM `worker_evaluation` `ev` WHERE `ev`.`employee_id` = `wp`.`id`) AS `evaluation_count`,
    (SELECT COALESCE(SUM(`ev`.`amount`), 0) FROM `worker_evaluation` `ev` WHERE `ev`.`employee_id` = `wp`.`id` AND `ev`.`incentive_penalty_type` = 'حافز') AS `incentive_total`,
    (SELECT COALESCE(SUM(`ev`.`amount`), 0) FROM `worker_evaluation` `ev` WHERE `ev`.`employee_id` = `wp`.`id` AND `ev`.`incentive_penalty_type` = 'جزاء') AS `penalty_total`
FROM `employees` `wp`;

CREATE OR REPLACE VIEW `v_worker_presence` AS
SELECT
    `wp`.`id` AS `employee_id`,
    (CASE
        WHEN `wp`.`workforce_state` = 'منتهٍ' THEN 'منتهٍ'
        WHEN EXISTS (SELECT 1 FROM `worker_leave_absence` `la` WHERE `la`.`employee_id` = `wp`.`id` AND `la`.`state` IN ('معتمد','مفتوح','مُغطًّى') AND (`la`.`date_from` IS NULL OR `la`.`date_from` <= CURDATE()) AND (`la`.`date_to` IS NULL OR `la`.`date_to` >= CURDATE())) THEN 'خارج الموقع/إجازة'
        WHEN EXISTS (SELECT 1 FROM `worker_movement` `m` WHERE `m`.`employee_id` = `wp`.`id` AND `m`.`state` IN ('أمرٌ صادر','في الطريق')) THEN 'في الطريق'
        WHEN EXISTS (SELECT 1 FROM `equipment_drivers` `ed` WHERE `ed`.`employee_id` = `wp`.`id` AND `ed`.`status` = 1) THEN 'داخل الموقع'
        ELSE 'بانتظار التخصيص'
    END) AS `presence_state`
FROM `employees` `wp`;
