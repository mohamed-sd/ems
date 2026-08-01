-- ═══════════════════════════════════════════════════════════════════════════
-- N-12 الأداء الشهري والإسناد (PLAN-04 §2.2 · PLAN-05 البوابة ①)
-- ───────────────────────────────────────────────────────────────────────────
-- سجل أداء شهري لكل (مقعد × شهر) — **مشتق مجمَّع فوق container_consumption**:
-- «يفسّر ويصنّف ويُثبت المسؤولية ويغذّي الجزاءات والجاهزية، ولا يحلّ محلّ
-- الوحدات ولا يُفوتر منه رقم» (PLAN-04 §2.2 — قاعدة ملزمة).
--
-- ① stop_reason_codes: أسباب التعطل الستة قائمةً محكومة — **لكل سببٍ بند
--    التزامٍ مقابلٌ إلزامي** (obligation_type) ومنه يُشتق الطرف المتحمل آليًّا
--    من contract_obligations المُجازة؛ وسبب «أخرى» يُلزم ببندٍ صريحٍ عند الإدخال.
-- ② monthly_performance: مجموعات الزمن والإنتاج (اقتصاد الأعمدة: نسبة الإنجاز
--    والإجمالي محسوبان لا مخزَّنان — لا مصدرين للرقم).
-- ③ monthly_performance_downtime: الساعات بسببها وبندها وطرفها المتحمل
--    (لقطة مشتقة وقت التسجيل بمرجع بندها — لا كتابة حرة).
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `stop_reason_codes` (
  `code` VARCHAR(40) NOT NULL,
  `name_ar` VARCHAR(120) NOT NULL,
  `obligation_type` ENUM('fuel','access_road','loading_equipment','equipment_readiness','operators','permits_safety','utilities','catering_camp','force_majeure') NULL DEFAULT NULL
    COMMENT 'بند الالتزام المقابل الافتراضي — NULL لسبب «أخرى» فيُلزم ببند صريح عند الإدخال',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='N-12: أسباب التعطل الستة — قائمة محكومة لا نص حر، وكل سبب ببنده المقابل';

INSERT IGNORE INTO `stop_reason_codes` (`code`, `name_ar`, `obligation_type`) VALUES
  ('unexecuted_loss',      'فاقد غير منفَّذ',        'access_road'),
  ('maintenance_downtime', 'تعطل صيانة',             'equipment_readiness'),
  ('holidays_downtime',    'إجازات وعطل',            'force_majeure'),
  ('hr_delay',             'تأخير موارد بشرية',      'operators'),
  ('reliability_downtime', 'تعطل اعتمادية',          'equipment_readiness'),
  ('other',                'أخرى',                    NULL);

CREATE TABLE IF NOT EXISTS `monthly_performance` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `contract_id` INT UNSIGNED NOT NULL,
  `container_id` INT UNSIGNED NOT NULL COMMENT 'حاوية المقعد (op_containers · level=معدة · seat_no)',
  `period` CHAR(7) NOT NULL COMMENT 'YYYY-MM',
  -- ① الزمن
  `contract_hours` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'التعاقدية (من contract_hours_monthly للمقعد)',
  `executed_hours` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'المنفَّذة — مجمَّعة من container_consumption',
  `executed_base_hours` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'الأساسية المنفَّذة (دون الإضافي)',
  `standby_hours` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'الاستعداد',
  `available_hours` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'المتاحة',
  `shortfall_hours` DECIMAL(10,2) GENERATED ALWAYS AS (GREATEST(`contract_hours` - `executed_hours` - `standby_hours`, 0)) STORED COMMENT 'العجز عن التعاقد — محسوب',
  `completion_pct` DECIMAL(6,2) GENERATED ALWAYS AS (IF(`contract_hours` > 0, ROUND(`executed_hours` / `contract_hours` * 100, 2), NULL)) STORED COMMENT 'نسبة الإنجاز — محسوبة',
  -- ④ الإنتاج
  `trips` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `tons` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `meters` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `fuel_consumed` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'وقود مستهلك',
  `state` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `closed_by` INT UNSIGNED NULL DEFAULT NULL,
  `closed_at` DATETIME NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mp_seat_period` (`company_id`, `container_id`, `period`),
  KEY `ix_mp_contract` (`company_id`, `contract_id`, `period`),
  CONSTRAINT `fk_mp_container` FOREIGN KEY (`container_id`) REFERENCES `op_containers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_mp_hours` CHECK (`contract_hours` >= 0 AND `executed_hours` >= 0 AND `standby_hours` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='N-12: سجل الأداء الشهري (مقعد × شهر) — مشتق مجمَّع، ليس مصدر كمية الفوترة (PLAN-04 §2.2)';

CREATE TABLE IF NOT EXISTS `monthly_performance_downtime` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `perf_id` INT UNSIGNED NOT NULL,
  `reason_code` VARCHAR(40) NOT NULL COMMENT 'من stop_reason_codes حصرًا',
  `hours` DECIMAL(10,2) NOT NULL,
  `obligation_id` INT NOT NULL COMMENT 'بند الالتزام المقابل — إلزامي (سبب بلا بند لا يُقبل)',
  `bearer_party` ENUM('client','company','supplier','operator','none') NOT NULL COMMENT 'الطرف المتحمل — مُشتق من البند وقت التسجيل، لا يُكتب حرًّا',
  `effect_on_billing` ENUM('billable_standby','non_billable','per_clause') NOT NULL DEFAULT 'per_clause' COMMENT 'لقطة أثر البند على الفوترة',
  `note` VARCHAR(200) NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mpd_reason` (`perf_id`, `reason_code`),
  KEY `ix_mpd_company` (`company_id`, `perf_id`),
  CONSTRAINT `fk_mpd_perf` FOREIGN KEY (`perf_id`) REFERENCES `monthly_performance` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_mpd_reason` FOREIGN KEY (`reason_code`) REFERENCES `stop_reason_codes` (`code`) ON DELETE RESTRICT,
  CONSTRAINT `fk_mpd_obligation` FOREIGN KEY (`obligation_id`) REFERENCES `contract_obligations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_mpd_hours` CHECK (`hours` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='N-12: ساعات التعطل بسببها وبندها وطرفها المتحمل — الإسناد بالساعات لا بالعلامة';
