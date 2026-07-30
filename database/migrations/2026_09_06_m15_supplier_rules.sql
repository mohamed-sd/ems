-- ═══════════════════════════════════════════════════════════════════════════
-- M-15 · قواعدُ التحميل المسعَّرة وقواعدُ الجزاء — 2026-07-30
-- البطاقة: docs/specs/M-15_supplier_rules.md
-- المصدر: CON-03 §2-⑥ («ما يُحمَّل عليه من مصروفاتنا: وقودٌ · قطعُ غيارٍ ·
--         صيانةٌ · ترحيلٌ · رواتبُ مشغّليه · سلفٌ — **بأسعارٍ وقواعدَ مكتوبة**»)
--         · §2-⑦ (الجزاءاتُ **وسقوفُها**) · §4 («وله أن **يشدّد جزاءَه لا أن
--         يعكس إسنادًا**») · §6 (Schema + Validation: «**قاعدةُ تحميلٍ بلا سعرٍ
--         مكتوب → 422**») · ENT-02 §3 («تُحتسب بقواعدها … **ولا تتجاوز سقفَها
--         التعاقدي**»)
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: الجدولان **معدومان**؛ فالتحميلُ يقع بلا قاعدةِ تسعيرٍ
-- مكتوبة والجزاءاتُ إدخالٌ يدوي (دليلُ الكتالوج). و`contract_penalty_rules`
-- القائم **جزاءاتُ عقد العميل** (M-15 نظيرُها للمورد) ولا يُخلط.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① قواعدُ التحميل المسعَّرة (CON-03 §6 حرفيًّا) ─────────────────────────
-- التحميلاتُ الستُّ نصًّا · وطرائقُ التسعير الثلاث · **وسقفٌ**.
-- والقيدُ أدناه يجعل «قاعدةَ تحميلٍ بلا سعرٍ مكتوب» **مستحيلةً بنيويًّا**:
-- `cost` لا تحتاج معدلًا (التكلفةُ كما هي)، و`cost_plus`/`fixed` **يلزمهما معدلٌ
-- موجب** — فلا قاعدةَ تُكتب ثم يُسأل عنها «بكم؟».
CREATE TABLE IF NOT EXISTS `supplier_charge_rules` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `contract_id` INT NOT NULL COMMENT 'عقدُ المورد الحديث (H-07)',
  `charge_type` ENUM('fuel','spares','maintenance','transport','operator_payroll','advance')
      NOT NULL COMMENT 'التحميلاتُ الستُّ في §2-⑥ نصًّا',
  `pricing` ENUM('cost','cost_plus','fixed') NOT NULL DEFAULT 'cost'
      COMMENT 'بسعر التكلفة · تكلفةٌ مضافةٌ بنسبتها · مبلغٌ ثابت',
  `rate` DECIMAL(10,3) NULL DEFAULT NULL
      COMMENT 'cost_plus = نسبةٌ مئوية · fixed = مبلغٌ للوحدة/الحدث',
  `cap` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'سقفُ التحميل الواحد — NULL = بلا سقفٍ مكتوب',
  `currency` VARCHAR(8) NULL DEFAULT NULL,
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  `state` ENUM('active','ended') NOT NULL DEFAULT 'active',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_charge_rule` (`contract_id`, `charge_type`, `valid_from`),
  KEY `ix_charge_rule_co` (`company_id`, `contract_id`, `state`),
  CONSTRAINT `fk_sup_charge_rule_contract` FOREIGN KEY (`contract_id`)
      REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_charge_rule_rate` CHECK (
      (`pricing` = 'cost') OR (`rate` IS NOT NULL AND `rate` > 0)),
  CONSTRAINT `ck_charge_rule_cap` CHECK (`cap` IS NULL OR `cap` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ② قواعدُ الجزاء بسقوفها (CON-03 §6 · §2-⑦) ────────────────────────────
-- **اجتهادٌ مدوَّن:** §6 يسمّي العمودَ `formula_json`. والمنفَّذُ هنا **معادلةٌ
-- بأعمدةٍ محكومة** (عتبةٌ · معدلٌ · سقفٌ) و`formula_note` **توثيقًا لا تقييمًا** —
-- لأن صيغةً حرّةً تُقيَّم وقتَ التشغيل **لا تُحرَس بقيدٍ ولا تُقصّ بسقف**، وهو
-- عكسُ «ولا تتجاوز سقفَها التعاقدي» (ENT-02 §3) ونظيرُ ما قرّرته M-09 حرفيًّا.
--
-- و`inherits_attribution` تجسيدُ §4: «وله أن **يشدّد جزاءَه لا أن يعكس إسنادًا**»
-- — فالافتراضُ 1 (يرث إسنادَ CON-02)، وقلبُه إلى 0 يلزمه سببٌ مكتوب.
CREATE TABLE IF NOT EXISTS `supplier_penalty_rules` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `contract_id` INT NOT NULL,
  `kind` ENUM('shortfall','readiness','coverage','delay') NOT NULL
      COMMENT 'عجزٌ · جاهزيةٌ · تغطيةٌ · تأخر — قائمةُ §6 نصًّا',
  `threshold` DECIMAL(12,3) NULL DEFAULT NULL
      COMMENT 'الحدُّ الذي دونه يُفعَّل الجزاء (نسبةُ جاهزيةٍ دنيا · ساعاتُ إحلال …)',
  `rate` DECIMAL(12,3) NOT NULL COMMENT 'معدلُ الجزاء لكل وحدةِ عجزٍ أو نقطةِ نقص',
  `rate_basis` ENUM('per_unit','percent_of_base') NOT NULL DEFAULT 'per_unit',
  `cap_percent` DECIMAL(5,2) NULL DEFAULT NULL
      COMMENT 'سقفُ الجزاء كنسبةٍ من الأساس — NULL = بلا سقفٍ مكتوب (يُعلَن)',
  `periodicity` ENUM('daily','monthly','contract') NOT NULL DEFAULT 'monthly',
  `inherits_attribution` TINYINT NOT NULL DEFAULT 1
      COMMENT '1 = يرث إسنادَ CON-02 · 0 يلزمه سببٌ مكتوب (§4: يشدّد لا يعكس)',
  `override_reason` VARCHAR(255) NULL DEFAULT NULL,
  `currency` VARCHAR(8) NULL DEFAULT NULL,
  `formula_note` VARCHAR(255) NULL DEFAULT NULL
      COMMENT 'توثيقُ الصيغة نصًّا — **لا يُقيَّم**: الحسابُ من الأعمدة المحكومة',
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  `state` ENUM('active','ended') NOT NULL DEFAULT 'active',
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_penalty_rule` (`contract_id`, `kind`, `valid_from`),
  KEY `ix_penalty_rule_co` (`company_id`, `contract_id`, `state`),
  CONSTRAINT `fk_sup_penalty_rule_contract` FOREIGN KEY (`contract_id`)
      REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_penalty_rule_rate` CHECK (`rate` > 0),
  CONSTRAINT `ck_penalty_rule_cap` CHECK (
      `cap_percent` IS NULL OR (`cap_percent` > 0 AND `cap_percent` <= 100)),
  -- «يشدّد لا يعكس»: نقضُ الإسناد الموروث **يلزمه سببٌ مكتوب** بنيويًّا
  CONSTRAINT `ck_penalty_rule_override` CHECK (
      `inherits_attribution` = 1
      OR (`override_reason` IS NOT NULL AND CHAR_LENGTH(TRIM(`override_reason`)) > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ تسجيلُ شاشة «قواعد التحميل والجزاء» — الوحدة 159 ────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 159, 'قواعد تحميل المورد وجزاءاته', 'Suppliers/supplier_rules.php', 2, 0, 0, 'fa fa-scale-unbalanced', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Suppliers/supplier_rules.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 159, 1, r.a, r.e, 0
  FROM (SELECT 2  AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 17, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 159);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 159, 'قواعد تحميل المورد وجزاءاته', 'Suppliers/supplier_rules.php',
       'fa fa-scale-unbalanced', 58, NULL, 'Suppliers/supplier_rules.php', 1
  FROM (SELECT 2 AS rid UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Suppliers/supplier_rules.php');
