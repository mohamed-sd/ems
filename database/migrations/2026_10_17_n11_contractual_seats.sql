-- ═══════════════════════════════════════════════════════════════════════════
-- N-11 المقعد التعاقدي (PLAN-04 §2.1 · PLAN-05 البوابة ①) — توسعة لا استبدال
-- ───────────────────────────────────────────────────────────────────────────
-- ① المقعد **هو** مستوى «معدة» في op_containers مضافًا إليه seat_no وseat_kind
--    (PLAN-04 §2.1-⑤: «توسعةٌ لا مستوًى جديد») + الساعات الشهرية والسعر والعملة.
--    seat_role موجود سلفًا (role_kind: أساسية/احتياطية) — لا يُكرر.
-- ② حاوية النوع: قيمة ENUM جديدة «نوع» بين «مورد» و«معدة» — المستوى الوحيد
--    المضاف. قيد Σ حاويات النوع ≤ حاوية موردها يحرسه ck_container_alloc القائم
--    (الأب يحمل allocated_qty) — لا قيد جديد.
-- ③ تعاقب المعدات: seat_assignments (مقعد × معدة × من/إلى × سبب الاستبدال ×
--    صفة الإسناد × السائقون وعددهم) — وقيد عدم التداخل في الخدمة (409).
-- ④ فجوة المقاعد: محسوبة (seats_contracted/filled/gap) في الخدمة — لا عمود
--    مخزَّن لرقم يُحسب («لا مصدران للرقم الواحد»).
-- عميل الهجرة utf8mb4 (migrate.php يفرضه) — شرط تعديل ENUM العربية.
-- ═══════════════════════════════════════════════════════════════════════════

-- ② مستوى «نوع» في سلّم الحاويات (idempotent: MODIFY بنفس القائمة آمن التكرار)
ALTER TABLE `op_containers`
  MODIFY `level` ENUM('رئيسية','مورد','نوع','معدة','مشغّل')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

-- ① أعمدة المقعد على مستوى «معدة» (idempotent)
SET @n = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='op_containers' AND COLUMN_NAME='seat_no');
SET @ddl = IF(@n=0, 'ALTER TABLE `op_containers`
  ADD COLUMN `seat_no` SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT ''N-11: رقم المقعد التعاقدي — فريد داخل العقد لمستوى معدة'' AFTER `role_kind`,
  ADD COLUMN `seat_kind` ENUM(''contractual_seat'',''operational_resource_slot'',''supplier_allocation'') NULL DEFAULT NULL COMMENT ''N-11: نوع المقعد — يُشتق من فصل بند البيع عن خطة الموارد (PLAN-03 §4) لا يُصنَّف مستقلًّا'' AFTER `seat_no`,
  ADD COLUMN `seat_equipment_type_id` INT UNSIGNED NULL DEFAULT NULL COMMENT ''N-11: نوع المعدة المطلوب للمقعد (equipments_types.id)'' AFTER `seat_kind`,
  ADD COLUMN `contract_hours_monthly` DECIMAL(10,2) NULL DEFAULT NULL COMMENT ''N-11: الساعات التعاقدية الشهرية للمقعد'' AFTER `seat_equipment_type_id`,
  ADD COLUMN `seat_unit_price` DECIMAL(14,4) NULL DEFAULT NULL COMMENT ''N-11: سعر وحدة المقعد'' AFTER `contract_hours_monthly`,
  ADD COLUMN `seat_currency` VARCHAR(8) NULL DEFAULT NULL COMMENT ''N-11: عملة سعر المقعد'' AFTER `seat_unit_price`', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- فرادة رقم المقعد داخل العقد (NULL لغير المقاعد لا يعيق)
SET @n = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='op_containers' AND INDEX_NAME='uq_seat_no');
SET @ddl = IF(@n=0, 'ALTER TABLE `op_containers` ADD UNIQUE KEY `uq_seat_no` (`company_id`,`contract_id`,`seat_no`)', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- ③ تعاقب المعدات على المقعد
CREATE TABLE IF NOT EXISTS `seat_assignments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `container_id` INT UNSIGNED NOT NULL COMMENT 'حاوية المقعد (op_containers.level=معدة بseat_no)',
  `equipment_id` INT UNSIGNED NOT NULL COMMENT 'المعدة الفعلية الجالسة في المقعد',
  `date_from` DATE NOT NULL,
  `date_to` DATE NULL DEFAULT NULL COMMENT 'NULL = جالسة حتى الآن',
  `replace_reason` VARCHAR(200) NULL DEFAULT NULL COMMENT 'سبب الاستبدال — إلزامي لغير الأول (تحرسه الخدمة)',
  `assignment_role` ENUM('أساسي','احتياطي','مؤقت') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'أساسي' COMMENT 'صفة الإسناد',
  `drivers_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'عدد السائقين على المعدة في هذا المقعد',
  `drivers_json` JSON NULL DEFAULT NULL COMMENT 'قائمة employee_id للسائقين — مراجع لا نسخ',
  `state` ENUM('active','ended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_sa_seat` (`company_id`,`container_id`,`date_from`),
  KEY `ix_sa_equipment` (`company_id`,`equipment_id`,`date_from`),
  CONSTRAINT `fk_sa_container` FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_sa_dates` CHECK (`date_to` IS NULL OR `date_to` >= `date_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='N-11: تعاقب المعدات على المقعد التعاقدي — لا تداخل فترتين لمعدتين في مقعد (تحرسه الخدمة 409)';
