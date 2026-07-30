-- ═══════════════════════════════════════════════════════════════════════════
-- M-17 · التقييمُ الدوريُّ للمورد — 2026-07-31
-- البطاقة: docs/specs/M-17_supplier_evaluation.md
-- المصدر: CON-03 §4-التقييم («دوريٌّ **بمؤشراتٍ من سجلات النظام لا انطباعًا**:
--         الجاهزيةُ · الالتزامُ بالتغطية · نسبةُ التوقفات المسندة إليه ·
--         جودةُ المشغّلين · الحوادثُ — **ونتيجتُه شرطٌ في التجديد** وفي ترجيح
--         عروضه القادمة») · §5 (شاشةُ «تقييم المورد»: «كلُّ رقمٍ ينقر لمصدره»)
--         · UX-05 §5.1-⑦ («مؤشراتُ أدائه **من سجلاته** … لا انطباعًا»)
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: الجدولان **معدومان** (`worker_evaluation` تقييمُ موظفٍ
-- لا موردٍ ولا يُخلط). والمصادرُ الخمسةُ **كلُّها حيّةٌ قائمة**:
--   · الجاهزيةُ والتغطية  ← `supplier_capacity` + `unit_time_log` (M-16)
--   · التوقفاتُ المسندة   ← `unit_time_log.resp_party = 'supplier'`
--   · جودةُ المشغّلين     ← `unit_time_log.ops_state = 'operator_stop'`
--   · الحوادثُ            ← `tickets` بنوع `safety_incident` على معداته
-- فالناقصُ **الأوزانُ المكتوبة والنتيجةُ المحفوظة** لا مصادرُ القياس.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① الأوزانُ المكتوبة — «لا نتيجةَ بلا وزنٍ مكتوب» ───────────────────────
CREATE TABLE IF NOT EXISTS `supplier_evaluation_weights` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `indicator` ENUM('readiness','coverage','attributed_stops','operator_quality','incidents')
      NOT NULL COMMENT 'مؤشراتُ §4-التقييم الخمسةُ نصًّا',
  `weight` DECIMAL(5,2) NOT NULL COMMENT 'وزنُ المؤشر — وΣ الأوزان = 100 (تفرضه الخدمة)',
  `scale_max` DECIMAL(10,2) NULL DEFAULT NULL
      COMMENT 'مقياسُ المؤشرات العددية (الحوادث): العددُ الذي تبلغ عنده النتيجةُ صفرًا — NULL = بلا مقياسٍ مكتوب فلا يُقاس',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_eval_weight` (`company_id`, `indicator`),
  CONSTRAINT `ck_sup_eval_weight` CHECK (`weight` > 0 AND `weight` <= 100),
  CONSTRAINT `ck_sup_eval_scale` CHECK (`scale_max` IS NULL OR `scale_max` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ② رأسُ التقييم — والنتيجةُ محسوبةٌ لا مكتوبةٌ بيد ──────────────────────
CREATE TABLE IF NOT EXISTS `supplier_evaluations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `supplier_id` INT NOT NULL,
  `contract_id` INT NULL DEFAULT NULL COMMENT 'عقدُ المورد إن قُصد بعينه — والتقييمُ للمورد أصلًا',
  `period_from` DATE NOT NULL,
  `period_to` DATE NOT NULL,
  `score` DECIMAL(5,2) NULL DEFAULT NULL
      COMMENT 'النتيجةُ من 100 — **محسوبةٌ من المؤشرات** ولا تُكتب يدًا (§4: لا انطباعًا)',
  `weight_measured` DECIMAL(5,2) NOT NULL DEFAULT 0
      COMMENT 'مجموعُ أوزان المؤشرات **المقيسة فعلًا** — التغطيةُ تُعلَن ولا تُخفى خلف نسبةٍ مطبَّعة',
  `state` ENUM('draft','decided') NOT NULL DEFAULT 'draft',
  `renewal_flag` ENUM('eligible','conditional','not_eligible') NULL DEFAULT NULL
      COMMENT 'أثرُ النتيجة على التجديد — «ونتيجتُه **شرطٌ في التجديد**»',
  `decision_note` VARCHAR(255) NULL DEFAULT NULL,
  `generated_by` INT NULL DEFAULT NULL,
  `decided_by` INT NULL DEFAULT NULL,
  `decided_at` DATETIME NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_eval_period` (`supplier_id`, `period_from`, `period_to`),
  KEY `ix_sup_eval` (`company_id`, `supplier_id`, `state`, `period_to`),
  CONSTRAINT `fk_sup_eval_supplier` FOREIGN KEY (`supplier_id`)
      REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_sup_eval_period` CHECK (`period_to` >= `period_from`),
  -- المعتمَدُ يلزمه قرارُ تجديدٍ ومقرِّرٌ: «اعتمادٌ بلا قرار» مستحيلٌ بنيويًّا
  CONSTRAINT `ck_sup_eval_decided` CHECK (
      `state` <> 'decided' OR (`renewal_flag` IS NOT NULL AND `decided_by` IS NOT NULL)),
  -- ومنعُ التجديد **يلزمه سببٌ مكتوب**: قرارٌ يقطع رزقًا لا يكون صامتًا
  CONSTRAINT `ck_sup_eval_reason` CHECK (
      `renewal_flag` IS NULL OR `renewal_flag` <> 'not_eligible'
      OR (`decision_note` IS NOT NULL AND `decision_note` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ سطرُ المؤشر — «كلُّ رقمٍ ينقر لمصدره» (§5) ──────────────────────────
CREATE TABLE IF NOT EXISTS `supplier_evaluation_lines` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `evaluation_id` INT NOT NULL,
  `indicator` ENUM('readiness','coverage','attributed_stops','operator_quality','incidents') NOT NULL,
  `measurable` TINYINT NOT NULL DEFAULT 1 COMMENT '0 = بلا مصدرٍ في الفترة — يُعلَن ولا يُقدَّر',
  `measured_value` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'القياسُ الخام كما قُرئ من السجل',
  `basis_value` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'الأساسُ الذي قُسم عليه (زمنٌ مخططٌ · مقياسٌ مكتوب)',
  `ratio` DECIMAL(6,4) NULL DEFAULT NULL COMMENT 'نسبةُ الإجادة (0..1) — الأعلى أفضل',
  `weight` DECIMAL(5,2) NOT NULL,
  `earned` DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'الوزنُ × النسبة',
  `source_note` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مصدرُ الرقم بلغة المهمة — لا رقمَ بلا مصدر',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_eval_line` (`evaluation_id`, `indicator`),
  CONSTRAINT `fk_sup_eval_line` FOREIGN KEY (`evaluation_id`)
      REFERENCES `supplier_evaluations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_sup_eval_ratio` CHECK (`ratio` IS NULL OR (`ratio` >= 0 AND `ratio` <= 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ④ تسجيلُ شاشة «تقييم المورد» — الوحدة 161 (CON-03 §5) ─────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 161, 'تقييم المورد الدوري', 'Suppliers/supplier_evaluation.php', 2, 0, 0, 'fa fa-star-half-stroke', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Suppliers/supplier_evaluation.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 161, 1, r.a, r.e, 0
  FROM (SELECT 2  AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 17, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 161);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 161, 'تقييم المورد الدوري', 'Suppliers/supplier_evaluation.php',
       'fa fa-star-half-stroke', 60, NULL, 'Suppliers/supplier_evaluation.php', 1
  FROM (SELECT 2 AS rid UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Suppliers/supplier_evaluation.php');
