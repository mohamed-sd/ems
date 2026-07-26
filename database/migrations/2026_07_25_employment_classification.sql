-- ═══════════════════════════════════════════════════════════════════════════
-- تصنيف الموظف (Employment Classification) — مسار التوظيف من التقديم للاعتماد
-- ───────────────────────────────────────────────────────────────────────────
-- محورٌ ثالثٌ مستقلّ عن المحورين القائمين، وهذا الفصل مقصود:
--   • `status` (tinyint)      — حالة النظام: مفعَّل/موقوف.
--   • `employee_status`       — الحالة التشغيلية اليومية: نشط/معلق/في إجازة/…
--   • `employment_classification` (جديد) — مسار التوظيف: مرشح←متدرب←مقبول،
--     ومنتهاه مستقيل أو مفصول.
-- دمجُها في حقلٍ واحد يُفقد القدرة على قول «متدرّبٌ في إجازة» — وهو الدرس نفسه
-- المدفوع سلفًا في نموذج حالة الحركة ثنائيّ المحور (op_state ≠ equipment_health).
--
-- ترتيب الـENUM يتبع مسار الحياة لا الأبجدية، كي يفرز الجدولُ منطقيًّا.
--
-- ⚠️ ENUM عربية: التطبيق بعميل utf8mb4 عبر database/migrate.php حصرًا، وإلا
-- تضاعف ترميز القيم ولم يطابقها التطبيق.
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── ① العمود ───────────────────────────────────────────────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees'
                   AND COLUMN_NAME='employment_classification'),
    "ALTER TABLE `employees` ADD COLUMN `employment_classification`
       ENUM('مرشح','متدرب','مقبول','مستقيل','مفصول') NULL DEFAULT NULL
       COMMENT 'مسار التوظيف — مستقل عن employee_status التشغيلية'
       AFTER `employee_status`",
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ② فهرس الفلترة ─────────────────────────────────────────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees'
                   AND INDEX_NAME='ix_employment_classification'),
    'ALTER TABLE `employees` ADD KEY `ix_employment_classification` (`employment_classification`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ═══════════════════════════════════════════════════════════════════════════
-- ③ إصلاح عطبٍ قائم في `employee_status` (قرار المستخدم 2026-07-25)
-- ───────────────────────────────────────────────────────────────────────────
-- القيمة الافتراضية للعمود و8 صفوف مخزَّنة حرفيًّا كثلاث علامات استفهام
-- (0x3F3F3F): عربيةٌ كُتبت عبر اتّصالٍ غير utf8mb4 فاستُبدلت محارفها. أثرها أن
-- تلك الصفوف لا تطابق أيَّ خيارٍ في قائمة الشاشة، فتظهر فارغةً عند التعديل
-- وتُفسد الفلترة. تُعاد إلى «نشط» — وهي القيمة الغالبة (48 صفًّا) والافتراض
-- المقصود أصلًا.
-- ═══════════════════════════════════════════════════════════════════════════
UPDATE `employees` SET `employee_status` = 'نشط' WHERE `employee_status` = '???';

ALTER TABLE `employees`
  MODIFY COLUMN `employee_status` VARCHAR(50) NULL DEFAULT 'نشط';

-- ═══════════════════════════════════════════════════════════════════════════
-- ④ تعبئة أوّليّة للصفوف القائمة (بيانات عرضٍ لا عقد)
-- ───────────────────────────────────────────────────────────────────────────
-- • المرتبطون بحساب مستخدم (30 صفًّا) → «مقبول» حصرًا: موظفٌ يدخل النظام لا
--   يصحّ وسمُه «مرشح» أو «مفصول» — تناقضٌ ظاهرٌ في الشاشة.
-- • الباقون (27) يُوزَّعون على الخمسة بـ`id % 5`: توزيعٌ متنوّعٌ **حتميّ** لا
--   عشوائيّ، فتُعيد المهاجرة نفس النتيجة عند إعادة التشغيل (RAND() كان يجعل
--   الترحيل غير قابلٍ للتكرار وهو شرطٌ في هذا المشروع).
-- • الشرط `IS NULL` يمنع دهسَ أيّ تصنيفٍ يُدخله المستخدم لاحقًا.
-- ═══════════════════════════════════════════════════════════════════════════
UPDATE `employees` e
   SET e.`employment_classification` = 'مقبول'
 WHERE e.`employment_classification` IS NULL
   AND EXISTS (SELECT 1 FROM `users` u WHERE u.`employee_id` = e.`id`);

UPDATE `employees` e
   SET e.`employment_classification` = ELT((e.`id` % 5) + 1,
       'مرشح', 'متدرب', 'مقبول', 'مستقيل', 'مفصول')
 WHERE e.`employment_classification` IS NULL;

-- اتّساقٌ مع الحقيقة القائمة: الصفّ الموسوم «مفصول» في الحالة التشغيلية
-- تصنيفُه «مفصول» أيضًا مهما وقع عليه التوزيع.
UPDATE `employees`
   SET `employment_classification` = 'مفصول'
 WHERE `employee_status` = 'مفصول';
