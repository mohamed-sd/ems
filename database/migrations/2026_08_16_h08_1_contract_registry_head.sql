-- ═══════════════════════════════════════════════════════════════════════════
-- H-08-① · سجلُّ العقود الموحّد — رأسُ العقد + ترحيلُ الجداول الثلاثة قراءةً
-- 2026-07-30 · البطاقة: docs/specs/H-08_1_contract_registry_head.md
-- المصدر: CON-01 §2/§3.1/§4/§7.1 · الشريحة بنص PLAN-01 §5.2-①
-- ───────────────────────────────────────────────────────────────────────────
-- **بناءٌ بجانب القائم** (N-04 مرحلة ①): الجداولُ الموروثةُ الثلاثة
-- (worker_contract · drivercontracts · سياساتُ المشغّلين في contract_hour_policies)
-- لا تُمسّ ولا تُحذف — تُسقَط رؤوسُها هنا قراءةً بوصلة مصدرٍ (source_table/source_id)
-- والكتابةُ تبقى في مصدرها القديم حتى تكتمل الشرائح ②③④ وتمرّ مطابقةُ فترة.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① الكتالوج المحكوم: نماذجُ الأجر الخمسةَ عشرَ (CON-01 §3.1 نصًّا) ──────
-- «اختيارٌ مستقلٌّ داخل العقد لا يُشتق من المسمّى» و«نموذجٌ غيرُ مذكورٍ → 422».
-- الرمزُ لاتينيٌّ (گوتشا الترميز) والتعريبُ عمودٌ؛ calc_path لأن النموذج
-- «يحدد مسارَ الاحتساب (زمنيٌّ أو تشغيلي)».
CREATE TABLE IF NOT EXISTS `pay_models` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(32) NOT NULL,
  `label_ar` VARCHAR(64) NOT NULL,
  `calc_path` ENUM('time','production','mixed','other') NOT NULL DEFAULT 'other',
  `is_active` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pay_model_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pay_models` (`code`, `label_ar`, `calc_path`)
SELECT s.code, s.label_ar, s.calc_path FROM (
  SELECT 'fixed_only'        AS code, 'ثابت فقط'      AS label_ar, 'time'       AS calc_path
  UNION ALL SELECT 'fixed_allowances',  'ثابت وبدلات',   'time'
  UNION ALL SELECT 'fixed_incentive',   'ثابت وحافز',    'mixed'
  UNION ALL SELECT 'incentive_only',    'حافز فقط',      'production'
  UNION ALL SELECT 'hourly',            'بالساعة',       'time'
  UNION ALL SELECT 'daily',             'باليوم',        'time'
  UNION ALL SELECT 'per_shift',         'بالوردية',      'time'
  UNION ALL SELECT 'per_trip',          'بالنقلة',       'production'
  UNION ALL SELECT 'per_ton',           'بالطن',         'production'
  UNION ALL SELECT 'per_meter',         'بالمتر',        'production'
  UNION ALL SELECT 'lump_sum',          'مقطوع',         'other'
  UNION ALL SELECT 'commission',        'عمولة',         'production'
  UNION ALL SELECT 'performance_bonus', 'مكافأة أداء',   'production'
  UNION ALL SELECT 'composite',         'مركّب',         'mixed'
  UNION ALL SELECT 'other',             'أخرى',          'other'
) s
WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `pay_models`) pm WHERE pm.`code` = s.code);

-- ── ② رأسُ العقد الموحّد (CON-01 §7.1 حرفيًّا + وصلةُ الترحيل) ─────────────
-- state: حالاتُ جدول §4 الثماني عشرة (العنوانُ يعدّ 17 والجدولُ يسمّي 18 —
-- أُخذ الجدولُ لأنه الأدق؛ مدوَّنٌ في البطاقة). لاتينيةٌ عمدًا والتعريب في الشاشة.
-- category: فئاتُ §2 الأربع — supplier_worker تسجيلٌ تشغيليٌّ بلا عقدِ أجرٍ معنا
-- (استحقاقُه في عقد مورده CON-03)، تُقبل بنيويًّا ولا يُرحَّل إليها شيء.
CREATE TABLE IF NOT EXISTS `employee_contracts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL COMMENT 'صاحبُ العمل — عزلُ المستأجر (TenantRegistry)',
  `employee_id` INT NOT NULL COMMENT 'سجلُّ الأشخاص القائم — «العقدُ يشير إليه ولا ينسخ»',
  `category` ENUM('permanent','project','operator','supplier_worker') NOT NULL,
  `relation_type` VARCHAR(50) NULL DEFAULT NULL COMMENT 'طبيعةُ الارتباط — يحمل نوعَ الموروث نصًّا عند الترحيل',
  `project_id` INT NULL DEFAULT NULL COMMENT 'فئةُ «مشروع» مرتبطةٌ بمشروع عميلٍ ومدتِه (CON-01 §2)',
  `start_date` DATE NULL DEFAULT NULL,
  `end_date` DATE NULL DEFAULT NULL,
  `probation_end` DATE NULL DEFAULT NULL,
  `pay_model_id` INT NOT NULL COMMENT '«اختيارٌ مستقلٌّ لا يُشتق من الوظيفة» — من الكتالوج المحكوم حصرًا',
  `currency` VARCHAR(8) NULL DEFAULT NULL COMMENT 'NULL حيث لم يسجَّل — لا تلفيق',
  `state` ENUM('draft','completed','validated','approved','rejected','accepted','declined',
               'signed','active','confirmed','amended','suspended','seconded',
               'expired','terminated','settled','closed','archived')
          NOT NULL DEFAULT 'draft',
  `state_before_hold` VARCHAR(20) NULL DEFAULT NULL
      COMMENT 'ما قبل التعليق/الإعارة — العودةُ إلى حيث كان لا إلى حالةٍ مفترضة (قياسُ pause_state_before)',
  `hold_reason` VARCHAR(255) NULL DEFAULT NULL,
  `signed_file_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'النسخةُ الموقَّعة — ثابتةٌ لا تُستبدل (إلزامُها مع H-10)',
  `version` INT NOT NULL DEFAULT 1 COMMENT 'قفلٌ تفاؤلي — 409 عند التزاحم',
  `source_table` VARCHAR(32) NULL DEFAULT NULL
      COMMENT 'الترحيلُ قراءةً: مصدرُ الصف — الكتابةُ تبقى فيه حتى إقفال القديم بمطابقةٍ (N-04)',
  `source_id` INT NULL DEFAULT NULL COMMENT 'معرّفُ الصف في مصدره (لرؤوس سياسات المشغّلين: معرّفُ المشغّل — إسقاطُ مجموعة)',
  `created_by` INT NULL DEFAULT NULL,
  `approved_by` INT NULL DEFAULT NULL,
  `approved_at` DATETIME NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ec_person_company_start` (`employee_id`, `company_id`, `start_date`),
  UNIQUE KEY `uq_ec_source` (`source_table`, `source_id`, `company_id`)
      COMMENT 'عطالةُ الترحيل — والشركةُ في المفتاح لأن رأسَ سياسات المشغّل معرّفُه معرّفُ المشغّل داخل شركته',
  KEY `ix_ec_state_end` (`state`, `end_date`) COMMENT 'فهرسُ التنبيه (state, end_date) — CON-01 §7.1',
  KEY `ix_ec_company` (`company_id`),
  CONSTRAINT `fk_ec_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `fk_ec_project` FOREIGN KEY (`project_id`) REFERENCES `project` (`id`),
  CONSTRAINT `fk_ec_pay_model` FOREIGN KEY (`pay_model_id`) REFERENCES `pay_models` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ الترحيلُ قراءةً — التعبئةُ الصادقة (لا اختراع · عاطلةٌ بحارس المصدر) ──

-- ③-أ worker_contract (الكاتبُ الحيُّ اليوم — صفٌّ واحدٌ مقيس):
--   الفئة: contract_type المشروعية (مشروع·مؤقت·موسمي·تغطية مؤقتة·تجاري مؤقت) → project
--          وسواها → permanent (فئاتُ CON-01 §2 — والمشغّلُ بيتُه سياساتُ المشغّلين ③-ج).
--   النموذج: wage_method كما هو (شهري→fixed_only/fixed_allowances إن وُجد بدلٌ مسجَّل ·
--            بالساعة→hourly · بالوردية/اليوم→per_shift · بالإنتاج→composite · مقطوع→lump_sum).
--   الحالة: **مرآةُ المسجَّل** بلا إعادة حكم (مسودة→draft · نافذ→active · منتهٍ→expired)
--          — وما جاوز تاريخُه يُدوَّن في تقرير المطابقة لا يُغيَّر صامتًا.
INSERT INTO `employee_contracts`
    (`company_id`, `employee_id`, `category`, `relation_type`, `start_date`, `end_date`,
     `pay_model_id`, `currency`, `state`, `version`, `source_table`, `source_id`, `created_by`, `created_at`)
SELECT wc.`company_id`, wc.`employee_id`,
       CASE WHEN wc.`contract_type` IN ('مشروع','مؤقت','موسمي','تغطية مؤقتة','تجاري مؤقت')
            THEN 'project' ELSE 'permanent' END,
       CONCAT('worker_contract:', wc.`contract_type`),
       wc.`date_start`, wc.`date_end`,
       (SELECT pm.`id` FROM `pay_models` pm WHERE pm.`code` =
          CASE wc.`wage_method`
            WHEN 'شهري' THEN CASE WHEN COALESCE(wc.`allow_housing`, wc.`allow_food`,
                                               wc.`allow_site`, wc.`allow_transport`) IS NOT NULL
                                  THEN 'fixed_allowances' ELSE 'fixed_only' END
            WHEN 'بالساعة'        THEN 'hourly'
            WHEN 'بالوردية/اليوم' THEN 'per_shift'
            WHEN 'بالإنتاج'       THEN 'composite'
            WHEN 'مقطوع'          THEN 'lump_sum'
            ELSE 'other' END),
       NULL,  -- worker_contract لا يسجّل عملةً — لا تلفيق
       CASE wc.`state` WHEN 'نافذ' THEN 'active' WHEN 'منتهٍ' THEN 'expired' ELSE 'draft' END,
       1, 'worker_contract', wc.`id`, wc.`created_by`, COALESCE(wc.`created_at`, NOW())
FROM `worker_contract` wc
JOIN `employees` e ON e.`id` = wc.`employee_id`
WHERE wc.`company_id` IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `employee_contracts`) ec
                   WHERE ec.`source_table` = 'worker_contract' AND ec.`source_id` = wc.`id`);

-- ③-ب drivercontracts (صفرُ صفٍّ مقيس — «شبهُ ميتة» ج-03): الخريطةُ مكتوبةٌ للبنية
--   فتعملُ يومَ يظهر صفٌّ تاريخيٌّ؛ اليوم أثرُها صفر. النموذجُ other (بنيتُها عريضةٌ
--   بلا نموذجِ أجرٍ معلن — لا تخمين)، والحالةُ من status الثنائي (1 نشط → active).
INSERT INTO `employee_contracts`
    (`company_id`, `employee_id`, `category`, `relation_type`, `project_id`, `start_date`, `end_date`,
     `pay_model_id`, `currency`, `state`, `version`, `source_table`, `source_id`, `created_at`)
SELECT dc.`company_id`, dc.`employee_id`, 'operator',
       'drivercontract',
       dc.`project_id`,
       COALESCE(dc.`actual_start`, dc.`contract_signing_date`), dc.`actual_end`,
       (SELECT pm.`id` FROM `pay_models` pm WHERE pm.`code` = 'other'),
       NULLIF(dc.`price_currency_contract`, ''),
       CASE WHEN dc.`status` = 1 THEN 'active' ELSE 'draft' END,
       1, 'drivercontracts', dc.`id`, dc.`created_at`
FROM `drivercontracts` dc
JOIN `employees` e ON e.`id` = dc.`employee_id`
WHERE dc.`company_id` IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `employee_contracts`) ec
                   WHERE ec.`source_table` = 'drivercontracts' AND ec.`source_id` = dc.`id`);

-- ③-ج سياساتُ المشغّلين (contract_hour_policies · party_scope='operator'):
--   «تصير عرضًا لعقدِ نموذجٍ تشغيليٍّ هنا — لا جدولين لقاعدةٍ واحدة» (CON-01 §7.1).
--   رأسٌ واحدٌ لكل (شركة × مشغّل) — إسقاطُ مجموعةٍ فsource_id = معرّفُ المشغّل.
--   النموذج: أساسٌ واحدٌ → خريطتُه (ton→per_ton · meter→per_meter · actual/standby→hourly)
--            وتعددُ الأسس → composite (مركّب — من القائمة الخمسة عشرة نصًّا).
--   الحالة: وجودُ اعتمادٍ (approved_at) → active وإلا draft — سياسةٌ لم تُعتمد ليست نافذة.
INSERT INTO `employee_contracts`
    (`company_id`, `employee_id`, `category`, `relation_type`, `start_date`, `end_date`,
     `pay_model_id`, `currency`, `state`, `version`, `source_table`, `source_id`, `created_by`, `created_at`)
SELECT g.`company_id`, g.`operator_id`, 'operator', 'operator_policy',
       g.`mn_from`,
       CASE WHEN g.`n_open` > 0 THEN NULL ELSE g.`mx_to` END,  -- سياسةٌ مفتوحةُ السريان = عقدٌ مفتوح
       (SELECT pm.`id` FROM `pay_models` pm WHERE pm.`code` =
          CASE WHEN g.`n_basis` > 1 THEN 'composite'
               WHEN g.`one_basis` = 'ton'   THEN 'per_ton'
               WHEN g.`one_basis` = 'meter' THEN 'per_meter'
               WHEN g.`one_basis` IN ('actual','standby') THEN 'hourly'
               ELSE 'other' END),
       CASE WHEN g.`n_curr` = 1 THEN g.`one_curr` ELSE NULL END,
       CASE WHEN g.`has_approved` > 0 THEN 'active' ELSE 'draft' END,
       1, 'fin_operator_policies', g.`operator_id`, g.`mn_created_by`, NOW()
FROM (
  SELECT chp.`company_id`, chp.`operator_id`,
         MIN(chp.`effective_from`) AS mn_from, MAX(chp.`effective_to`) AS mx_to,
         SUM(CASE WHEN chp.`effective_to` IS NULL THEN 1 ELSE 0 END) AS n_open,
         COUNT(DISTINCT chp.`pay_basis`) AS n_basis, MIN(chp.`pay_basis`) AS one_basis,
         COUNT(DISTINCT chp.`currency`) AS n_curr,  MIN(chp.`currency`) AS one_curr,
         MAX(CASE WHEN chp.`approved_at` IS NOT NULL THEN 1 ELSE 0 END) AS has_approved,
         MIN(chp.`created_by`) AS mn_created_by
  FROM `contract_hour_policies` chp
  JOIN `employees` e ON e.`id` = chp.`operator_id`
  WHERE chp.`party_scope` = 'operator' AND chp.`is_deleted` = 0
    AND chp.`operator_id` IS NOT NULL
  GROUP BY chp.`company_id`, chp.`operator_id`
) g
WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `employee_contracts`) ec
                   WHERE ec.`source_table` = 'fin_operator_policies'
                     AND ec.`source_id` = g.`operator_id`
                     AND ec.`company_id` = g.`company_id`);
