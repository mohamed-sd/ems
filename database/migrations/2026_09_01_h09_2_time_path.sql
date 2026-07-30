-- ═══════════════════════════════════════════════════════════════════════════
-- H-09-② · المسارُ الزمني (ثابتٌ · بدلاتٌ · تناسبٌ · غيابٌ · إضافي) — 2026-07-30
-- البطاقة: docs/specs/H-09_2_time_path.md
-- المصدر: ENT-01 §3-① (المؤسسي الزمني للدائمين: «الزمنُ وفترةُ الخدمة (شهرٌ ·
--         يومٌ · ساعةٌ إضافية) والمكوّناتُ الشهرية») · §4 («من اللقطة … **×
--         مدةِ الاستحقاق في الفترة**» · «الإضافي … **بمعدّلاتها من العقد لا من
--         اجتهاد**» · «خصمُ … **الغياب** … كلٌّ بمرجعه؛ **ولا خصمَ بلا مستند**»)
-- ───────────────────────────────────────────────────────────────────────────
-- **المقيسُ الذي أملى هذا التصميم:**
--   · **لا جدولَ حضورٍ في النظام إطلاقًا** — لا يوميَّ حضورٍ ولا ساعاتِ إضافي
--     للدائمين (سجلُّ الدوام تشغيليٌّ للمعدات لا للموظفين المؤسسيين).
--   · `worker_leave_absence` (صفٌّ واحدٌ حي) هو **المصدرُ الوحيد** للغياب،
--     و`event_type` فيه **نصٌّ حرٌّ** لا يقول أيُّ نوعٍ يُخصم وأيُّه مدفوع.
--
-- فالتناسبُ يُشتقّ (لا مصدرَ يلزمه)، والغيابُ يحتاج **كتالوجًا محكومًا**،
-- والإضافيُّ يحتاج **مدخلًا بمستنده** — وما لا مصدرَ له يُعلَن ولا يُخترع.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① كتالوجُ أنواع الغياب — «أيُّ نوعٍ يُخصم؟» سؤالٌ لا يجيب عنه الموروث ──
-- بلا هذا الكتالوج يبقى الخصمُ اجتهادًا: نوعٌ غيرُ مصنَّفٍ **لا يُخصم صامتًا**
-- ولا يُخصم تخمينًا — يُعلَن سطرًا بحالته وينتظر تصنيفَ مالكه.
CREATE TABLE IF NOT EXISTS `payroll_absence_types` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `event_type` VARCHAR(40) NOT NULL COMMENT 'يطابق worker_leave_absence.event_type حرفيًّا',
  `deducts` TINYINT NOT NULL DEFAULT 0 COMMENT '1 = غيابٌ يُخصم · 0 = إجازةٌ مدفوعة',
  `deduct_percent` DECIMAL(5,2) NOT NULL DEFAULT 100.00 COMMENT 'نسبةُ الخصم من أجر اليوم',
  `label_ar` VARCHAR(80) NULL DEFAULT NULL,
  `active` TINYINT NOT NULL DEFAULT 1,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_absence_type` (`company_id`, `event_type`),
  CONSTRAINT `ck_absence_pct` CHECK (`deduct_percent` >= 0 AND `deduct_percent` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ② مدخلاتُ الزمن اليدوية — **بمستندها إلزامًا** ─────────────────────────
-- «ولا خصمَ بلا مستند» (§4) — والقاعدةُ نفسُها تُفرض على الزيادة: ساعةُ إضافيٍّ
-- بلا مرجعٍ رقمٌ يزيد أجرًا بلا سند. فـ`doc_ref` **NOT NULL + CHECK**.
-- وUQ(دورة × شخص × نوع) يمنع الإدخالَ المزدوج بنيويًّا.
CREATE TABLE IF NOT EXISTS `payroll_time_inputs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `run_id` INT NOT NULL,
  `person_id` INT NOT NULL,
  `kind` ENUM('overtime_hours','unpaid_days','night_shifts') NOT NULL,
  `qty` DECIMAL(12,2) NOT NULL,
  `doc_ref` VARCHAR(120) NOT NULL COMMENT 'مرجعُ المستند — إلزاميٌّ بنيويًّا',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_time_input` (`run_id`, `person_id`, `kind`),
  KEY `ix_time_input_co` (`company_id`, `run_id`),
  CONSTRAINT `fk_time_input_run` FOREIGN KEY (`run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_time_input_qty` CHECK (`qty` > 0),
  CONSTRAINT `ck_time_input_doc` CHECK (CHAR_LENGTH(TRIM(`doc_ref`)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ سطرُ الاحتساب يحمل أثرَ التناسب ونوعَه ───────────────────────────────
-- «كلُّ رقمٍ ينقر لمصدره» (ENT-01 §6): أيامُ الاستحقاق وأيامُ الفترة تُخزَّن
-- على السطر فيُرى **لماذا** صار المبلغُ جزءًا من الشهر لا كلَّه.
ALTER TABLE `payroll_lines`
  ADD COLUMN `line_kind` ENUM('component','overtime','absence_deduction') NOT NULL DEFAULT 'component'
    COMMENT 'نوعُ السطر — الخصمُ سطرٌ ظاهرٌ لا نقصٌ صامتٌ في مبلغٍ آخر'
    AFTER `component_ref`,
  ADD COLUMN `entitled_days` DECIMAL(6,2) NULL DEFAULT NULL COMMENT 'أيامُ الاستحقاق في الفترة'
    AFTER `qty`,
  ADD COLUMN `period_days` DECIMAL(6,2) NULL DEFAULT NULL COMMENT 'أيامُ الفترة كاملةً'
    AFTER `entitled_days`;

-- ── ④ بذرُ الكتالوج من الأنواع **المستعمَلة فعلًا** — بلا تصنيفٍ مخترَع ────
-- تُبذر بـ`deducts = 0` (لا خصم) لأن **الافتراضَ الآمن ألّا يُخصم من أحدٍ
-- بلا قرار**؛ ومالكُ الطبقة يقلبها حين يقرّر. والقلبُ قرارٌ لا اجتهادُ باذر.
INSERT INTO `payroll_absence_types` (`company_id`, `event_type`, `deducts`, `deduct_percent`, `label_ar`)
SELECT DISTINCT w.`company_id`, TRIM(w.`event_type`), 0, 100.00,
       CONCAT('مبذورٌ من الموروث — يحتاج قرارَ التصنيف: ', TRIM(w.`event_type`))
  FROM `worker_leave_absence` w
 WHERE w.`event_type` IS NOT NULL AND TRIM(w.`event_type`) <> ''
   AND NOT EXISTS (
       SELECT 1 FROM (SELECT `company_id`, `event_type` FROM `payroll_absence_types`) t
        WHERE t.`company_id` = w.`company_id` AND t.`event_type` = TRIM(w.`event_type`));
