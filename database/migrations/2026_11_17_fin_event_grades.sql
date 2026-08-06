-- AC-E01-05 «لا إقفالَ لمبدئي» — درجة الأثر جدولًا جانبيًّا (تفويض إكمال الـ66)
-- ═══════════════════════════════════════════════════════════════════════════
-- كان البند مفتوحًا وحيدًا في تدقيق الحزمة: «عمود درجة الأثر لم يُبنَ — DDL
-- مؤجل بعد نافذة الظل». عمودٌ على fin_financial_events = ALTER محظور بنص
-- ق-01 (صفر ALTER على قائم)؛ والحسم المعماري: جدولٌ جانبيٌّ CREATE-only يحمل
-- الدرجة بمرجع الحدث — الدلالة نفسها بلا مساس المخطط المجمد، ويُطوى عمودًا
-- في الجدول الأم بعد النافذة إن شاء المالك (الترحيل عندئذ INSERT..SELECT).
-- الافتراض: كل أثرٍ نهائي (final) ما لم يُوسم مبدئيًّا (provisional) —
-- والفترة لا تُقفل وفيها موسومٌ مبدئيًّا لم يُرقَّ (حارس الإقفال ④).

CREATE TABLE IF NOT EXISTS `fin_event_grades` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL COMMENT 'الكيان المالك — EN-03',
  `event_id` INT NOT NULL COMMENT 'الحدث المالي fin_financial_events.id',
  `grade` ENUM('provisional','final') NOT NULL DEFAULT 'provisional'
      COMMENT 'مبدئي: تقديري لا يُقفل عليه ماليًّا · نهائي: مؤكد',
  `reason` VARCHAR(300) DEFAULT NULL COMMENT 'علة الوسم المبدئي (تقدير · بانتظار مستند …)',
  `finalized_at` DATETIME DEFAULT NULL COMMENT 'لحظة الترقية إلى نهائي',
  `finalized_by` INT DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_grade` (`event_id`) COMMENT 'درجة واحدة لكل حدث',
  KEY `ix_feg_live` (`company_id`, `grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='AC-E01-05: درجة أثر الحدث المالي — المبدئي لا يُقفل عليه';
