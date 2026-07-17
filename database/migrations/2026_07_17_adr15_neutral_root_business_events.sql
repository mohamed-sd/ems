-- ═══════════════════════════════════════════════════════════════════════════
-- ADR-15 (REV-04 §6.2): الجذر المحايد — ems_business_events
-- ───────────────────────────────────────────────────────────────────────────
-- المشكلة: الأحداث تُكتب في دفترٍ مالي (fin_financial_events)، فكل مستهلكٍ
-- جديد «يستعير» من المالية. الحل: جدولُ جذرٍ رقيقٌ محايد تُكتب فيه الحقيقة،
-- ويبقى الدفتر المالي إسقاطًا/مستهلكًا كما هو — لا يتحرك مؤشره ولا صفوفه.
--
-- القناة الوحيدة للكتابة: EventPublisher (T_RESTRICTED في سجل البوابة).
-- خلف علم EMS_EVENT_ROOT (off = سلوك اليوم حرفيًا · publish = كتابة مزدوجة
-- ذرّية: الحقيقة ثم إسقاطها المالي في نفس معاملة المستدعي).
--
-- انحرافات موثَّقة عن مخطط المواصفة الرقيق (تكميلٌ لا تعارض):
--   • quantity DECIMAL(18,4) لا (14,3) — بلا فقدٍ مقابل الإسقاط المالي.
--   • أعمدة العقد §9 (event_no/category/source_module/idempotency/…) لأن
--     الناشر يفرضها أصلًا، فيصير سيل الحقائق ذاتيَّ الوصف لكل إدارة.
--   • amount/currency وصفيان (حقيقة D05 نقدية بطبيعتها) — لا قرار فيهما.
--   • أعمدة ADR-18 (event_status/reverses_event_id) من اليوم الأول.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `ems_business_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `event_no` VARCHAR(30) NOT NULL COMMENT 'BE-nnnn خادمي لكل شركة (ems_sequences — نطاق ems_business_events:BE:{company})',
  `event_uuid` CHAR(26) NOT NULL COMMENT 'ULID يُسَكّ عند النشر (مواصفة ADR-15)؛ الردم القديم LEGACY+id',
  `event_key` VARCHAR(80) NOT NULL COMMENT 'domain.entity.action — نفس مفردات عقد §9',
  `category` VARCHAR(20) NOT NULL COMMENT 'التصنيفات السبعة (عقد §9)',
  `source_module` VARCHAR(20) NOT NULL COMMENT 'VARCHAR لا ENUM — إدارة جديدة بلا DDL (درس ENUM الدفتر)',
  `source_ref` VARCHAR(60) DEFAULT NULL,
  `entity_type` VARCHAR(40) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(18,4) DEFAULT NULL,
  `unit` VARCHAR(16) DEFAULT NULL,
  `amount` DECIMAL(16,2) DEFAULT NULL COMMENT 'قيمة الحقيقة إن كانت نقدية — وصفٌ لا قرار مالي',
  `currency` VARCHAR(8) DEFAULT NULL,
  `project_id` INT DEFAULT NULL,
  `contract_id` INT DEFAULT NULL,
  `equipment_id` INT DEFAULT NULL,
  `supplier_entity_id` INT DEFAULT NULL,
  `customer_entity_id` INT DEFAULT NULL,
  `operator_employee_id` INT DEFAULT NULL,
  `event_status` ENUM('recorded','corrected','reversed') NOT NULL DEFAULT 'recorded' COMMENT 'ADR-18: العكسي = نفس الأثر بكميةٍ سالبة',
  `reverses_event_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ADR-18: id الحقيقة المنقوضة',
  `occurred_at` DATETIME NOT NULL COMMENT 'لحظة الوقوع الفعلي UTC',
  `payload` JSON DEFAULT NULL,
  `correlation_id` VARCHAR(64) DEFAULT NULL COMMENT 'سلسلة الأثر طرفًا لطرف — المشتق يرث الجذر',
  `idempotency_key` VARCHAR(64) DEFAULT NULL COMMENT 'عطالة المصدر — نفس مفتاح الإسقاط المالي (كتابة مزدوجة متسقة)',
  `schema_version` SMALLINT UNSIGNED DEFAULT 1,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ebe_no` (`company_id`, `event_no`),
  UNIQUE KEY `uq_ebe_uuid` (`event_uuid`),
  UNIQUE KEY `uq_ebe_idempotency` (`idempotency_key`),
  KEY `ix_company_id` (`company_id`, `id`),
  KEY `ix_ebe_key` (`company_id`, `event_key`),
  KEY `ix_ebe_entity` (`entity_type`, `entity_id`),
  KEY `ix_ebe_module` (`company_id`, `source_module`),
  KEY `ix_ebe_corr` (`correlation_id`),
  KEY `ix_ebe_occurred` (`company_id`, `occurred_at`),
  KEY `ix_ebe_reverses` (`reverses_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ADR-15: الجذر المحايد — سجل الحقائق المؤسسي append-only؛ القناة: EventPublisher حصرًا؛ الدفتر المالي إسقاطه الأول';

-- ── عمود الربط على الإسقاط المالي ──────────────────────────────────────────
-- NULL دلاليٌّ مقصود: صفٌّ يدويٌّ (شاشات المالية القديمة) أو سابقٌ للجذر.
ALTER TABLE `fin_financial_events`
  ADD COLUMN `root_event_id` BIGINT UNSIGNED DEFAULT NULL
    COMMENT 'ADR-15: الحقيقة الأم في ems_business_events — NULL = يدوي/سابق للجذر'
    AFTER `event_uuid`,
  ADD KEY `ix_ffe_root` (`root_event_id`),
  ADD CONSTRAINT `fk_ffe_root` FOREIGN KEY (`root_event_id`)
    REFERENCES `ems_business_events` (`id`);

-- ── الردم الصادق للصفوف القديمة ────────────────────────────────────────────
-- الصفوف العشرة القاعدية يدويةٌ سابقةٌ للعقد (FIN-EV-…، بلا event_key/entity).
-- لا نُزوّر مفاتيح أحداثٍ لم تقع؛ الحقيقة الصادقة الوحيدة عنها هي:
-- «سُجّل حدثٌ مالي» finance.event.recorded — والكيان هو صف الدفتر نفسه،
-- والحمولة تحمل legacy=true فيميّزها كل مستهلكٍ مستقبلي عن السيل الحي.
INSERT INTO `ems_business_events`
  (`company_id`, `event_no`, `event_uuid`, `event_key`, `category`, `source_module`,
   `source_ref`, `entity_type`, `entity_id`, `quantity`, `unit`, `amount`, `currency`,
   `project_id`, `contract_id`, `equipment_id`, `supplier_entity_id`, `customer_entity_id`,
   `operator_employee_id`, `occurred_at`, `payload`, `correlation_id`, `idempotency_key`,
   `created_by`, `created_at`)
SELECT
  f.`company_id`,
  CONCAT('BE-', LPAD(ROW_NUMBER() OVER (PARTITION BY f.`company_id` ORDER BY f.`id`), 4, '0')),
  CONCAT('LEGACY', LPAD(f.`id`, 20, '0')),
  'finance.event.recorded', 'financial', f.`source_module`,
  f.`source_ref`, 'fin_financial_event', f.`id`,
  f.`quantity`, f.`unit`, f.`amount`, f.`currency`,
  f.`project_id`, f.`contract_id`, f.`equipment_id`, f.`supplier_entity_id`, f.`customer_entity_id`,
  f.`operator_employee_id`,
  COALESCE(f.`occurred_at`, f.`created_at`),
  JSON_OBJECT('legacy', TRUE, 'event_type', f.`event_type`, 'event_no', f.`event_no`),
  f.`correlation_id`,
  CONCAT('backfill:ffe:', f.`id`),
  f.`created_by`, f.`created_at`
FROM `fin_financial_events` f
WHERE NOT EXISTS (
  SELECT 1 FROM `ems_business_events` b
  WHERE b.`idempotency_key` = CONCAT('backfill:ffe:', f.`id`)
);

UPDATE `fin_financial_events` f
JOIN `ems_business_events` b ON b.`idempotency_key` = CONCAT('backfill:ffe:', f.`id`)
SET f.`root_event_id` = b.`id`
WHERE f.`root_event_id` IS NULL;

-- ── بذر متتالية BE لكل شركةٍ رُدمت — الناشر يواصل بعد آخر رقمٍ مردوم ──────
INSERT IGNORE INTO `ems_sequences` (`scope`, `next_val`)
SELECT CONCAT('ems_business_events:BE:', `company_id`), 0
FROM `ems_business_events` GROUP BY `company_id`;

UPDATE `ems_sequences` s
JOIN (
  SELECT CONCAT('ems_business_events:BE:', `company_id`) AS sc, COUNT(*) AS c
  FROM `ems_business_events` GROUP BY `company_id`
) x ON x.sc = s.`scope`
SET s.`next_val` = GREATEST(s.`next_val`, x.c);
