-- ═══════════════════════════════════════════════════════════════════════════
-- محرّك تفريع الأثر (D05 §6.1) — خريطة الآثار التصريحية fin_effect_map
-- ───────────────────────────────────────────────────────────────────────────
-- الوثيقة: «لكل مصدرٍ خريطةُ آثارٍ معرَّفةٌ مسبقًا: ما الذي يتولّد، وفي أي
-- جدول، وبأي اتجاه». فالقواعد **بياناتٌ تُضبط من شاشة** لا كودٌ مدفون —
-- وهذا هو المعيار (Rules as Data): تفعيل أثرٍ أو ضبط معدّله بلا نشر كود.
--
-- ⚠️ قاعدة عدم التلفيق: أثرٌ لا تتوفّر مدخلاته الحقيقية يبقى is_active=0
-- ويُسجَّل «غير متاح» في السجل — ولا يُخترع له رقمٌ مالي أبدًا.
--   • employee_due: يحتاج مصدر تكليفِ مشغّلٍ بمعدةٍ وتاريخ. الموجود اليوم
--     `equipment_operators` جدولُ **تأهيلٍ** (رُخَص) بلا equipment_id — فلا
--     يُشتقّ منه مشغّلُ وحدةٍ بعينها. يُفعَّل يوم يوجد مصدر التكليف.
--   • maintenance_provision: مخصّص صيانةٍ لكل وحدةٍ منتَجة — معدّله قرارٌ
--     محاسبيٌّ (param_value)؛ صفرٌ = معطّل حتى يقرّره المدير المالي.
--
-- لا جداول أثرٍ جديدة: fin_financial_events · fin_dues · fin_cost_records
-- كلها قائمة، وfin_event_links يحمل أصلًا parent_kind='unit_record' وكل
-- أنواع الآثار المطلوبة — فصفر تعديلٍ على ENUM.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `fin_effect_map` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `source_kind` VARCHAR(30) NOT NULL COMMENT 'نوع المصدر: unit_record (وحدة معتمدة) — يتوسّع لاحقًا',
  `effect_type` VARCHAR(40) NOT NULL COMMENT 'يوافق fin_event_links.effect_type',
  `effect_label` VARCHAR(80) NOT NULL COMMENT 'التسمية المعروضة في شجرة الأثر',
  `target_table` VARCHAR(40) NOT NULL COMMENT 'الجدول الذي يُكتب فيه الأثر',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = معلن غير متاح/معطّل — يُسجَّل ولا يُلفَّق',
  `param_value` DECIMAL(14,4) DEFAULT NULL COMMENT 'معامل الأثر إن لزم (مثال: مخصّص الصيانة للوحدة)',
  `unavailable_reason` VARCHAR(200) DEFAULT NULL COMMENT 'سبب التعطيل — يظهر للمستخدم بدل الصمت',
  `display_order` SMALLINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_effect_map` (`company_id`, `source_kind`, `effect_type`),
  KEY `ix_effect_source` (`company_id`, `source_kind`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='D05 §6.1: خريطة تفريع الأثر — قواعد المروحة بياناتٍ لا كودًا';

-- بذر الخريطة لكل شركةٍ لها مستخدمون (إدراج عاطل)
INSERT INTO `fin_effect_map`
  (`company_id`, `source_kind`, `effect_type`, `effect_label`, `target_table`, `is_active`, `param_value`, `unavailable_reason`, `display_order`)
SELECT c.id, m.source_kind, m.effect_type, m.effect_label, m.target_table, m.is_active, m.param_value, m.unavailable_reason, m.display_order
FROM (SELECT DISTINCT company_id AS id FROM users WHERE company_id IS NOT NULL) c
CROSS JOIN (
    SELECT 'unit_record' AS source_kind, 'revenue_event' AS effect_type, 'إيراد على العميل بسعر عقده' AS effect_label,
           'fin_financial_events' AS target_table, 1 AS is_active, NULL AS param_value,
           NULL AS unavailable_reason, 1 AS display_order
    UNION ALL SELECT 'unit_record', 'supplier_due', 'مستحق المورد بسعر عقده (التوأم الثاني)',
           'fin_dues', 1, NULL, NULL, 2
    UNION ALL SELECT 'unit_record', 'cost_record', 'تكلفة على المعدة والمشروع',
           'fin_cost_records', 1, NULL, NULL, 3
    UNION ALL SELECT 'unit_record', 'employee_due', 'مستحق المشغّل عن وحدته',
           'fin_dues', 0, NULL,
           'لا مصدر تكليفِ مشغّلٍ بمعدةٍ وتاريخ: equipment_operators جدول تأهيلٍ (رُخَص) لا تكليف. يُفعَّل يوم يتوفّر المصدر — ولا تُلفَّق مستحقات.', 4
    UNION ALL SELECT 'unit_record', 'metric_update', 'مخصّص صيانةٍ محمَّلٌ على المعدة',
           'fin_cost_records', 0, 0.0000,
           'معدّل مخصّص الصيانة للوحدة قرارٌ محاسبي — اضبط param_value بقيمةٍ موجبة لتفعيله.', 5
) m
WHERE NOT EXISTS (
    SELECT 1 FROM `fin_effect_map` e
    WHERE e.company_id = c.id AND e.source_kind = m.source_kind AND e.effect_type = m.effect_type
);
