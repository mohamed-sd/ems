-- ═══════════════════════════════════════════════════════════════════════════
-- H-07 · عقدُ المورد الحديث ببنوده وبندِ الاستعداد — 2026-07-30
-- البطاقة: docs/specs/H-07_supplier_contract_lines.md
-- المصدر: CON-03 §2-②④ (النماذجُ والوحدات · التسعيرُ و«أساسُ احتساب الاستعداد
--         إن استُحق») · §6 (المواصفةُ التنفيذية) · §6-التوافق (الترحيلُ قراءةً)
-- ───────────────────────────────────────────────────────────────────────────
-- **بناءٌ بجانب القائم** (N-04 مرحلة ①): `supplierscontracts` (7 صفوفٍ حية)
-- و`suppliercontractequipments` (8) لا تُمسّان ولا تُحذفان — تبقيان **الكاتبَ**،
-- ويُسقَط هنا رأسُ العقد وبنودُه **قراءةً** بوصلة مصدرٍ (source_table/source_id).
--
-- ولا `supplier_quotas` ولا `quota_containers`: هرمُ الحصص L2/L3 استوعبته
-- `op_containers` في H-06 — «لا جدولين لحصةٍ واحدة» بنصّ CON-03 §6-التوافق.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① رأسُ عقد المورد (CON-03 §6 حرفيًّا + وصلةُ الترحيل) ──────────────────
-- `state`: ENUM عربيٌّ **مطابقٌ حرفًا بحرف** لـContractStateMachine::ALL —
-- CON-03 لا تعدّد حالاتٍ للمورد، فاختراعُ مفرداتٍ ثانيةٍ للعقد نفسِه تلفيقٌ
-- ويكسر «الاسمَ في موضعين»؛ والآلةُ القائمة (H-02) تُعاد استعمالُها لا تُستنسخ.
-- (migrate.php يفرض utf8mb4 على اتصاله — گوتشا ازدواج ترميز ENUM العربية محكومة.)
CREATE TABLE IF NOT EXISTS `supplier_contracts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL COMMENT 'عزلُ المستأجر (TenantRegistry)',
  `supplier_id` INT NOT NULL COMMENT 'الموردُ — شريكُ الطاقة داخل هرم الحصص',
  `client_contract_id` INT NULL DEFAULT NULL COMMENT 'وصلةُ L1 — عقدُ العميل الذي تُقتطع منه الحصة (CON-03 §1)',
  `project_id` INT NULL DEFAULT NULL COMMENT 'المشروعُ المشمول — يُقرأ ولا يُملك هنا',
  `start_date` DATE NULL DEFAULT NULL,
  `end_date` DATE NULL DEFAULT NULL,
  `currency` VARCHAR(8) NULL DEFAULT NULL COMMENT 'رمزٌ لاتيني (USD·SDG·EUR·SAR) — التسميةُ العربية تبقى في المصدر',
  `state` ENUM('مسودة','تفاوض','معتمد','موقَّع','نافذ','قيد التنفيذ','معلَّق','معدَّل','مجدَّد','منتهٍ','مقفل','مصفّى')
      NOT NULL DEFAULT 'مسودة' COMMENT 'مفرداتُ ContractStateMachine نفسُها — لا قاموسَ ثانٍ',
  `version` INT NOT NULL DEFAULT 1 COMMENT 'قفلٌ تفاؤلي — 409 عند الانحراف',
  `source_table` VARCHAR(64) NULL DEFAULT NULL COMMENT 'وصلةُ الترحيل — غيرُ الفارغ = مرحَّلٌ محصَّنٌ 423',
  `source_id` INT NULL DEFAULT NULL,
  `notes` VARCHAR(255) NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0 COMMENT 'إخفاءٌ ناعم — لا حذفَ صلب',
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_contract_party` (`supplier_id`, `client_contract_id`, `start_date`),
  UNIQUE KEY `uq_sup_contract_source` (`source_table`, `source_id`, `company_id`),
  KEY `ix_sup_contract_co_state` (`company_id`, `state`),
  KEY `ix_sup_contract_client` (`client_contract_id`),
  CONSTRAINT `fk_sup_contract_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sup_contract_client` FOREIGN KEY (`client_contract_id`) REFERENCES `contracts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ② بنودُ العقد: نموذجٌ · وحدةٌ · سعرٌ · **أساسُ استعداد** (CON-03 §6) ────
-- `unit` هي التسميةُ التي يقرؤها EffectFanout::CONTRACT_UNIT (ساعة · طن ·
-- نقلة · متر طولي) — فالبندُ يتكلم لغةَ محرّك الفوترة لا لغةً ثالثة.
-- والقيدان أدناه يجعلان «قاعدةَ استحقاقٍ بلا سعرٍ مكتوب» **مستحيلةً بنيويًّا**
-- لا مرفوضةً بفحصِ شاشةٍ وحدَه (§6-Validation).
CREATE TABLE IF NOT EXISTS `supplier_contract_lines` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `contract_id` INT NOT NULL COMMENT 'رأسُ عقد المورد — البندُ ابنُه',
  `work_model` ENUM('hour','ton','trip','meter') NOT NULL COMMENT 'نماذجُ §2-② الأربعة — ما خرج عنها 422',
  `unit` VARCHAR(32) NOT NULL COMMENT 'تسميةُ الوحدة كما يقرؤها محرّكُ الفوترة',
  `unit_price` DECIMAL(18,2) NOT NULL COMMENT 'سعرُ الوحدة — ≤ 0 مرفوضٌ 422',
  `currency` VARCHAR(8) NULL DEFAULT NULL COMMENT 'عملةُ البند — الفارغُ يرتدّ لعملة الرأس (تناظرُ الموروث)',
  `standby_basis` ENUM('none','rate','percent') NOT NULL DEFAULT 'none'
      COMMENT '«أساسُ احتساب الاستعداد إن استُحق» — none = لا استعدادَ مشترطًا',
  `standby_rate` DECIMAL(18,4) NULL DEFAULT NULL
      COMMENT 'rate = معدلُ الساعة · percent = نسبةٌ من unit_price',
  `valid_from` DATE NULL DEFAULT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  `state` ENUM('active','replaced','ended') NOT NULL DEFAULT 'active',
  `source_table` VARCHAR(64) NULL DEFAULT NULL,
  `source_id` INT NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_line_model_unit` (`contract_id`, `work_model`, `unit`),
  KEY `ix_sup_line_co` (`company_id`, `contract_id`),
  CONSTRAINT `fk_sup_line_contract` FOREIGN KEY (`contract_id`) REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_sup_line_price` CHECK (`unit_price` > 0),
  CONSTRAINT `ck_sup_line_standby` CHECK (
      (`standby_basis` = 'none'  AND `standby_rate` IS NULL) OR
      (`standby_basis` <> 'none' AND `standby_rate` IS NOT NULL AND `standby_rate` > 0)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ الترحيلُ **قراءةً**: رؤوسُ الموروث السبعة ────────────────────────────
-- مرآةٌ صادقةٌ لا إعادةُ حكم: الحالةُ تُقرأ من أعمدة المصدر الحية
-- (إنهاءٌ ← «منتهٍ» · توقفٌ بلا استئنافٍ ← «معلَّق» · status=1 ← «نافذ»)
-- ولا يُخترع تصنيفٌ من التواريخ. والعملةُ من خريطة EffectFanout::CONTRACT_CURRENCY.
INSERT INTO `supplier_contracts`
    (`company_id`, `supplier_id`, `client_contract_id`, `project_id`,
     `start_date`, `end_date`, `currency`, `state`, `source_table`, `source_id`, `notes`)
SELECT sc.`company_id`, sc.`supplier_id`,
       sc.`project_contract_id`, sc.`project_id`,
       COALESCE(sc.`actual_start`, sc.`contract_signing_date`), sc.`actual_end`,
       CASE TRIM(COALESCE(sc.`price_currency_contract`, ''))
            WHEN 'دولار' THEN 'USD' WHEN 'جنيه' THEN 'SDG'
            WHEN 'يورو'  THEN 'EUR' WHEN 'ريال' THEN 'SAR' ELSE NULL END,
       CASE WHEN sc.`termination_type` IS NOT NULL AND TRIM(sc.`termination_type`) <> '' THEN 'منتهٍ'
            WHEN sc.`pause_date` IS NOT NULL AND sc.`resume_date` IS NULL THEN 'معلَّق'
            WHEN sc.`status` = 1 THEN 'نافذ'
            ELSE 'مسودة' END,
       'supplierscontracts', sc.`id`,
       CONCAT('مرحَّلٌ قراءةً من supplierscontracts#', sc.`id`, ' — الكتابةُ تبقى في المصدر')
  FROM `supplierscontracts` sc
 WHERE sc.`supplier_id` IS NOT NULL
   AND EXISTS (SELECT 1 FROM (SELECT `id` FROM `suppliers`) s WHERE s.`id` = sc.`supplier_id`)
   AND (sc.`project_contract_id` IS NULL
        OR EXISTS (SELECT 1 FROM (SELECT `id` FROM `contracts`) c WHERE c.`id` = sc.`project_contract_id`))
   AND NOT EXISTS (
        SELECT 1 FROM (SELECT `source_table`, `source_id` FROM `supplier_contracts`) h
         WHERE h.`source_table` = 'supplierscontracts' AND h.`source_id` = sc.`id`);

-- ── ④ الترحيلُ **قراءةً**: البنودُ المشتقةُ صدقًا وحدَها ────────────────────
-- الاشتقاقُ من `suppliercontractequipments` بالجمع على (عقد × نموذج × وحدة):
--   · وحدةٌ لا يعرفها محرّكُ الفوترة (أو فارغة) ⇒ **لا بند** — «لا تسعير ملفَّق».
--   · سعرٌ ≤ 0 ⇒ لا بند.
--   · سعرانِ مختلفانِ لنفس (النموذج × الوحدة) ⇒ **لا بند ويُعلَن في التقرير** —
--     النموذجُ الجديد يسعّر بالنموذج لا بنوع المعدة، فاختيارُ أحد السعرين اختراع.
-- و`standby_basis='none'` لكل مرحَّلٍ ليس افتراضًا بل **حقيقةً مقيسة**:
-- الموروثُ لا يحمل مفهومَ الاستعداد إطلاقًا (صفرُ عمودٍ وصفرُ قيمة).
INSERT INTO `supplier_contract_lines`
    (`company_id`, `contract_id`, `work_model`, `unit`, `unit_price`, `currency`,
     `standby_basis`, `valid_from`, `state`, `source_table`, `source_id`)
SELECT h.`company_id`, h.`id`, x.`wm`, x.`unit_label`, x.`price`,
       CASE TRIM(COALESCE(x.`cur_label`, ''))
            WHEN 'دولار' THEN 'USD' WHEN 'جنيه' THEN 'SDG'
            WHEN 'يورو'  THEN 'EUR' WHEN 'ريال' THEN 'SAR' ELSE h.`currency` END,
       'none', h.`start_date`, 'active', 'suppliercontractequipments', x.`src_id`
  FROM `supplier_contracts` h
  JOIN (
        SELECT sce.`contract_id` AS legacy_id,
               CASE TRIM(sce.`equip_unit`)
                    WHEN 'ساعة' THEN 'hour'  WHEN 'طن'   THEN 'ton'
                    WHEN 'نقلة' THEN 'trip'  WHEN 'متر'  THEN 'meter'
                    WHEN 'متر طولي' THEN 'meter' ELSE NULL END AS wm,
               TRIM(sce.`equip_unit`) AS unit_label,
               MIN(sce.`equip_price`) AS price,
               COUNT(DISTINCT sce.`equip_price`) AS price_variants,
               MIN(sce.`id`) AS src_id,
               MIN(TRIM(COALESCE(sce.`equip_price_currency`, ''))) AS cur_label
          FROM `suppliercontractequipments` sce
         WHERE sce.`equip_price` > 0
         GROUP BY sce.`contract_id`, 2, 3
       ) x ON x.`legacy_id` = h.`source_id`
 WHERE h.`source_table` = 'supplierscontracts'
   AND x.`wm` IS NOT NULL
   AND x.`price_variants` = 1
   AND NOT EXISTS (
        SELECT 1 FROM (SELECT `contract_id`, `work_model`, `unit` FROM `supplier_contract_lines`) l
         WHERE l.`contract_id` = h.`id` AND l.`work_model` = x.`wm` AND l.`unit` = x.`unit_label`);

-- ── ⑤ تسجيلُ شاشة «بنود عقد المورد» — الوحدة 153 (بعد 152) ────────────────
-- الملكيةُ للدور 2 (مالكُ الموروثتين 25 «عقود الموردين» و26 «ملف عقد المورد»).
-- والماليةُ (17) عرضًا: البنودُ منبعُ تسعيرِ استحقاقِ المورد الذي تقرؤه.
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 153, 'بنود عقد المورد', 'Suppliers/supplier_contract_lines.php', 2, 0, 0, 'fa fa-file-invoice-dollar', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Suppliers/supplier_contract_lines.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 153, 1, r.a, r.e, 0
  FROM (SELECT 2  AS rid, 1 AS a, 1 AS e      -- مالكُ عقود الموردين: يُنشئ ويعدّل
        UNION ALL SELECT 17, 0, 0) r          -- المالية: عرضًا (تقرأ السعرَ ولا تعرّفه)
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 153);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 153, 'بنود عقد المورد', 'Suppliers/supplier_contract_lines.php',
       'fa fa-file-invoice-dollar', 52, NULL, 'Suppliers/supplier_contract_lines.php', 1
  FROM (SELECT 2 AS rid UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Suppliers/supplier_contract_lines.php');
