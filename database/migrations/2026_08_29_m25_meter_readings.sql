-- ═══════════════════════════════════════════════════════════════════════════
-- M-25 · قراءاتُ العدّادات — 2026-07-30
-- البطاقة: docs/specs/M-25_meter_readings.md
-- المصدر: UX-10 §8 (Schema حرفيًّا · وقيدُ «value ≥ آخرِ قراءة» وقاعدةُ التصفير)
--         · §8.3-F2 · UX-04 §3 (الوقاية بعدّاد الساعات)
-- ───────────────────────────────────────────────────────────────────────────
-- **بناءٌ بجانب القائم**: `equipments.operating_hours` و`opening_meter` لا
-- تُحذفان — الأولى تبقى **مرآةً** يحدّثها التسجيل (كي لا تكذب الشاشاتُ القديمة)
-- والثانيةُ تصير **بدايةَ السلسلة** حين تُكتب.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── سلسلةُ القراءات ────────────────────────────────────────────────────────
-- `chain_no`: «تصفيرٌ بقرارٍ موثَّقٍ **يفتح سلسلةً جديدة**» (§8.3-F2 نصًّا).
-- فالرتابةُ («value ≥ آخرِ قراءة») تُفرض **داخل السلسلة**، وعدّادٌ استُبدل أو
-- صُفّر يبدأ من رقمه الجديد بلا تزوير تاريخٍ ولا حذفِ ماضٍ.
--
-- ⚠ ولا `CHECK` على الرتابة: گوتشا مثبَتة — `CHECK` **لا يرى صفوفًا أخرى**.
-- فالحارسُ في الخدمة داخل معاملةٍ واحدة (نمطُ «Σ الأبناء ≤ الأب» نفسُه)،
-- والمفتاحُ الفريدُ يحمل ما يقدر عليه: قراءةً واحدةً لكل (معدة × نوع × يوم).
CREATE TABLE IF NOT EXISTS `meter_readings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `equipment_id` INT NOT NULL,
  `meter_type` ENUM('hour','km') NOT NULL DEFAULT 'hour' COMMENT 'UX-10 §8 نصًّا — لا ثالثَ لهما',
  `chain_no` INT NOT NULL DEFAULT 1 COMMENT 'سلسلةُ العدّاد — التصفيرُ الموثَّق يزيدها',
  `reading_date` DATE NOT NULL,
  `value` DECIMAL(18,2) NOT NULL,
  `delta` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'الفارقُ عن سابقتها في السلسلة — NULL لأولها',
  `source` ENUM('manual','inspection','timesheet','reset') NOT NULL DEFAULT 'manual',
  `source_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجعُ الواقعة: TS-‹id› · INS-‹id›',
  `is_reset` TINYINT NOT NULL DEFAULT 0,
  `reset_reason` VARCHAR(255) NULL DEFAULT NULL,
  `reset_doc_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'مستندُ قرار التصفير — إلزاميٌّ متى صُفّر',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `recorded_by` INT NULL DEFAULT NULL,
  `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meter_reading_day` (`equipment_id`, `meter_type`, `reading_date`),
  KEY `ix_meter_latest` (`equipment_id`, `meter_type`, `chain_no`, `reading_date`),
  KEY `ix_meter_co` (`company_id`, `reading_date`),
  CONSTRAINT `fk_meter_reading_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_meter_value` CHECK (`value` >= 0),
  CONSTRAINT `ck_meter_reset_doc` CHECK (
      `is_reset` = 0 OR (`reset_doc_ref` IS NOT NULL AND CHAR_LENGTH(TRIM(`reset_doc_ref`)) > 0)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── تسجيلُ شاشة «قراءات العدّادات» — الوحدة 155 (بعد 154) ──────────────────
-- الملكيةُ لإدارة الأسطول (7 — مالكةُ سجل المعدات) والصيانةُ (11) عرضًا:
-- «الأسطولُ يملك عدّاداتِها إدخالًا» و«الوقائيةُ تقرؤها» (UX-10 §قاعدة القراءة).
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 155, 'قراءات العدّادات', 'Equipments/meter_readings.php', 7, 0, 0, 'fa fa-gauge-high', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Equipments/meter_readings.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 155, 1, r.a, r.e, 0
  FROM (SELECT 7  AS rid, 1 AS a, 1 AS e      -- الأسطول: يسجّل ويصفّر بقرار
        UNION ALL SELECT 11, 1, 0             -- الصيانة: تسجّل قراءةً ولا تصفّر
        UNION ALL SELECT 3,  0, 0) r          -- التشغيل: عرضًا
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 155);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 155, 'قراءات العدّادات', 'Equipments/meter_readings.php',
       'fa fa-gauge-high', 54, NULL, 'Equipments/meter_readings.php', 1
  FROM (SELECT 7 AS rid UNION ALL SELECT 11 UNION ALL SELECT 3) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Equipments/meter_readings.php');
