-- ═══════════════════════════════════════════════════════════════════════════
-- K3-pre (عقد §9 — استكمال الحقول الإلزامية التي لم تشملها قائمة §8.4-1):
-- Entity/Entity ID/Occurred At/Payload إلزامية في العقد ولا أعمدة لها،
-- + المرجع السياقي Operator (→employees SSOT)، + قيمتا جسرٍ محوكمتان للENUMين
-- القديمين (إضافة في الذيل فقط — فورية وغير كاسرة؛ شاشات المالية القديمة تفلتر
-- بقيمها المحددة فلا ترى أحداث العقد = عزل يمنع العدّ المزدوج).
-- المبدأ: الحدث يحمل مراجع رقمية لا نسخ بيانات؛ contracts وأمثاله تبقى SSOT.
-- Rollback: database/backups/K3_down_event_contract_fields_b.sql (مُختبَر على نسخة).
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE `fin_financial_events`
  ADD COLUMN `entity_type` VARCHAR(40) NULL DEFAULT NULL
    COMMENT 'Entity (عقد §9 إلزامي): نوع الكيان الموضوع timesheet/mnt_order/… — يفرضه الناشر'
    AFTER `source_ref`,
  ADD COLUMN `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Entity ID (عقد §9 إلزامي): معرّف رقمي حصرًا — لا مفاتيح نصية (ADR-09)'
    AFTER `entity_type`,
  ADD COLUMN `operator_employee_id` INT NULL DEFAULT NULL
    COMMENT 'Operator (عقد §9 سياقي): مرجع رقمي إلى employees.id — SSOT الأشخاص'
    AFTER `customer_entity_id`,
  ADD COLUMN `occurred_at` DATETIME NULL DEFAULT NULL
    COMMENT 'Occurred At (عقد §9 إلزامي): لحظة الوقوع الفعلي UTC — تُميَّز عن created_at'
    AFTER `state`,
  ADD COLUMN `payload` JSON NULL DEFAULT NULL
    COMMENT 'Payload (عقد §9 إلزامي): الحمولة التفصيلية JSON — يفرضها الناشر',
  ADD KEY `idx_ffe_entity` (`entity_type`, `entity_id`);

-- قيمتا الجسر المحوكمتان (إضافة ذيلية فقط — instant):
ALTER TABLE `fin_financial_events`
  MODIFY COLUMN `event_type` ENUM('revenue','expense','payable','receivable','payroll','settlement','enterprise') NOT NULL
    COMMENT 'قديم متوافق؛ أحداث العقد = enterprise والدلالة الكاملة في event_key/category — لا توسّع آخر (الحوكمة في سجل الأنواع)',
  MODIFY COLUMN `source_module` ENUM('sales','suppliers','workforce','procurement','warehouse','maintenance','projects','revenue','assets','treasury','movement','finance','transport','system') NOT NULL
    COMMENT 'أضيفت movement/finance/transport/system في الذيل (K3) — ناشرو الناقل';
