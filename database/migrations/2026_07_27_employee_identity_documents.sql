-- ═══════════════════════════════════════════════════════════════════════════
-- هوياتُ الموظفين في ملف الوثائق الموحّد — 2026-07-27 (طلب المالك)
-- ───────────────────────────────────────────────────────────────────────────
-- سؤالُ المالك: «هل يوجد تاريخُ انتهاءٍ ثانٍ في النظام غير رخصة المشغّل؟»
-- المسحُ الكامل للمخطّط أجاب: **نعم — و`employees.identity_expiry_date`
-- أخطرُها على الموارد البشرية**: 26 موظفًا لهم تاريخُ هويةٍ، و**الستةُ
-- والعشرون كلُّهم منتهون** (أقدمُها 1972) — وكلُّهم بحالةٍ نشطة.
--
-- ومسحٌ آخرُ للتوثيق (خارج نطاق هذا الترحيل، مرفوعٌ للمالك):
--   suppliers.identity_expiry_date  · 6 من 12 منتهية
--   transfer_permits.expiry_date    · 6 من 12 منتهية
--   contracts.actual_end            · 2 منتهٍ و3 توشك
--   supplierscontracts.actual_end   · 5 منتهٍ و2 يوشك
--   fin_funding_schedules.due_date  · 20 مستحقًّا فات
--   fleet_equipment_compliance/protection · 1 لكلٍّ
--
-- وتحقّقٌ مقيس: `employees.license_expiry_date` **مطابقٌ حرفيًّا** لـ
-- `equipment_operators.license_expiry_date` في 26/26 صفًّا — نسخةٌ لا مصدرٌ
-- ثانٍ، فلا تُرحَّل مرتين (ورخصُ القيادة مرحَّلةٌ سلفًا من سجل التأهيل).
--
-- التطبيق عبر database/migrate.php حصرًا بعميل utf8mb4.
-- ═══════════════════════════════════════════════════════════════════════════

-- توسيعُ قاموس الأنواع بوثائق الأفراد
ALTER TABLE `equipment_documents`
  MODIFY COLUMN `doc_type`
    ENUM('استمارة','تأمين','فحص دوري','رخصة قيادة','رخصة تشغيل','تصريح','هوية','جواز سفر','عقد عمل','أخرى')
    NOT NULL COMMENT 'UX-10 §8.1 + وثائقُ الأفراد (رخصة/هوية/جواز/عقد) — التوسيع المعلَن';

-- ترحيلُ الهويات صفًّا أولًا بعطالة (migrated_from)
INSERT INTO `equipment_documents`
  (`company_id`, `subject_type`, `subject_id`, `doc_type`, `doc_no`,
   `expiry_date`, `alert_days`, `status`, `note`, `migrated_from`, `created_at`)
SELECT e.`company_id`, 'operator', e.`id`, 'هوية',
       NULLIF(e.`identity_number`, ''),
       e.`identity_expiry_date`, 30,
       CASE WHEN e.`identity_expiry_date` < CURDATE() THEN 'منتهية' ELSE 'سارية' END,
       'مُرحَّلة من ملف الموظف (هوية)',
       'employees.identity', NOW()
FROM `employees` e
WHERE e.`identity_expiry_date` IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `equipment_documents` d
                   WHERE d.`company_id` = e.`company_id` AND d.`subject_type` = 'operator'
                     AND d.`subject_id` = e.`id` AND d.`migrated_from` = 'employees.identity');
