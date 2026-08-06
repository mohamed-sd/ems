-- RENTAL-CORE (المبيعات) — الثلاثيُّ الذي يفصل نظامَ التأجير عن نظامِ البيع
-- ═══════════════════════════════════════════════════════════════════════════
-- ① تقويمُ الأسطول والحجز  — التأجيرُ يؤجّر الأصلَ نفسه مرارًا، فبلا تقويمٍ لا
--    يُعرف ما يُوعَد به. الحجزُ «عمليةٌ ناعمة» تسبق العقد وتحجز النافذة الزمنية.
-- ② دفترُ الأسعار بالشرائح — التأجيرُ يبيع الزمن، فالسعرُ دالّةُ مدة لا رقمٌ واحد.
-- ③ الاستغلال — محسوبٌ من بياناتٍ قائمة (لا جدول): تخزينُ المشتقّ يُعفّنه.
--
-- قراراتُ تصميمٍ مبنيةٌ على قياس القاعدة الحيّة (2026-08-06) لا على العرف العام:
--   • مفتاحُ التسعير `equipments_types.id` لا `fleet_model` — لأن equipments.type
--     ممتلئٌ ومربوطٌ (216/219) بينما model_id صفرٌ عمليًّا (1/219).
--   • شرائحُ المدة بالأيام على توزيعهم الفعلي: متوسطُ التشغيل 74 يومًا ومتوسطُ
--     العقد 163 — فالشرائحُ ليست يومية/أسبوعية (سوق المياومة) بل ممتدة.
--   • لا عمودَ OEC في مؤشرات الاستغلال كمقياسٍ أول: 212 من 219 معدةً مورَّدةٌ لا
--     مملوكة، فمردودُ رأس المال مقياسٌ مضلِّل — والهامشُ (عميل − مورد) هو الصادق.
-- عزلُ المستأجر: company_id في كل جدول + ثلاثيُّ الحذف الناعم (عقد البوابة).

-- ── ① حجوزاتُ الأسطول ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fleet_reservations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL COMMENT 'الكيان المالك',
  `reservation_no` VARCHAR(40) NOT NULL COMMENT 'رقم الحجز — فريد داخل الشركة',
  `equipment_id` INT DEFAULT NULL COMMENT 'حجزُ معدةٍ بعينها (يمنع التعارض فعليًّا)',
  `equipment_type_id` INT DEFAULT NULL COMMENT 'أو حجزُ فئةٍ بعددٍ — قبل تحديد الآلة',
  `qty` INT NOT NULL DEFAULT 1 COMMENT 'العددُ المحجوز حين يكون الحجزُ بالفئة',
  `client_id` INT DEFAULT NULL,
  `opportunity_id` INT DEFAULT NULL COMMENT 'الفرصةُ التي وُلد منها الحجز',
  `quotation_id` INT DEFAULT NULL COMMENT 'العرضُ المرتبط',
  `contract_id` INT DEFAULT NULL COMMENT 'يُملأ عند التحويل لعقد',
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `state` ENUM('مبدئي','مؤكَّد','محوَّل لعقد','منتهٍ','ملغى') NOT NULL DEFAULT 'مبدئي',
  `hold_until` DATETIME DEFAULT NULL COMMENT 'مهلةُ الحجز المبدئي — بعدها يسقط',
  `purpose` VARCHAR(160) DEFAULT NULL COMMENT 'الغرض/الموقع',
  `note` VARCHAR(255) DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME DEFAULT NULL,
  `deleted_by` INT DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_res_no` (`company_id`, `reservation_no`),
  KEY `ix_res_eq` (`company_id`, `equipment_id`, `start_date`, `end_date`),
  KEY `ix_res_type` (`company_id`, `equipment_type_id`, `start_date`, `end_date`),
  KEY `ix_res_state` (`company_id`, `state`, `start_date`),
  KEY `ix_res_opp` (`company_id`, `opportunity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='حجوزاتُ الأسطول — النافذةُ الزمنية المحجوزة قبل العقد (RENTAL-CORE ①)';

-- ── ② دفترُ الأسعار ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rate_books` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `book_code` VARCHAR(40) NOT NULL COMMENT 'كودُ الدفتر — فريد داخل الشركة',
  `name` VARCHAR(160) NOT NULL,
  `currency` ENUM('USD','SDG') NOT NULL DEFAULT 'USD',
  `client_id` INT DEFAULT NULL COMMENT 'دفترٌ خاصٌّ بعميل — NULL يعني الدفترَ العام',
  `valid_from` DATE NOT NULL,
  `valid_to` DATE DEFAULT NULL COMMENT 'NULL = مفتوح',
  `state` ENUM('مسودة','معتمد','منتهٍ') NOT NULL DEFAULT 'مسودة',
  `approved_by` INT DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME DEFAULT NULL,
  `deleted_by` INT DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_book_code` (`company_id`, `book_code`),
  KEY `ix_book_live` (`company_id`, `state`, `valid_from`, `valid_to`),
  KEY `ix_book_client` (`company_id`, `client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='دفاترُ الأسعار — رأسُ الدفتر بسريانه وعملته (RENTAL-CORE ②)';

CREATE TABLE IF NOT EXISTS `rate_book_lines` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `book_id` INT NOT NULL,
  `equipment_type_id` INT NOT NULL COMMENT 'فئةُ المعدة — equipments_types.id',
  `work_model` ENUM('hour','day','shift','month','ton','meter','trip','cbm') NOT NULL DEFAULT 'hour',
  `tier_from_days` INT NOT NULL DEFAULT 1 COMMENT 'بدايةُ شريحة المدة بالأيام',
  `tier_to_days` INT DEFAULT NULL COMMENT 'نهايتُها — NULL = ما فوق',
  `unit_price` DECIMAL(14,2) NOT NULL COMMENT 'سعرُ الوحدة في هذه الشريحة',
  `min_hire_days` INT NOT NULL DEFAULT 1 COMMENT 'الحدُّ الأدنى لمدة الإيجار',
  `min_hours_per_day` DECIMAL(6,2) DEFAULT NULL COMMENT 'الحدُّ الأدنى للساعات اليومية المفوترة',
  `mobilization_fee` DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'رسمُ التعبئة/الترحيل',
  `operator_included` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'أالمشغّلُ ضمن السعر؟',
  `fuel_included` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'أالوقودُ ضمن السعر؟',
  `note` VARCHAR(255) DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME DEFAULT NULL,
  `deleted_by` INT DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_tier` (`company_id`, `book_id`, `equipment_type_id`, `work_model`, `tier_from_days`),
  KEY `ix_line_book` (`company_id`, `book_id`),
  KEY `ix_line_lookup` (`company_id`, `equipment_type_id`, `work_model`, `tier_from_days`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='بنودُ دفتر الأسعار — سعرٌ لكل (فئة × نموذج عمل × شريحة مدة) (RENTAL-CORE ②)';
