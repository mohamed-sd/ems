-- ═══════════════════════════════════════════════════════════════════════════
-- وثائقُ المعدة والمشغّل بتواريخ انتهائها — UX-10 §8.1 (2026-07-27)
-- ───────────────────────────────────────────────────────────────────────────
-- نصُّ الوثيقة: «الوثائق: doc_id (PK) · equipment_id (FK) · doc_type
-- (استمارة · تأمين · فحص دوري) · expiry_date · alert_days · file_ref ·
-- status — فهرس (expiry_date) للتنبيه الآلي قبلها بمدة alert_days».
--
-- المقيسُ قبل البناء (2026-07-27):
--   • 25 من 30 رخصةَ مشغّلٍ منتهيةٌ — وأصحابُها يعملون، ولا تنبيهَ ولا وسم.
--   • equipments فيها وثيقةٌ واحدةٌ (رخصة) بخمسة حقولٍ متناثرة — لا ملفّ.
--   • لا جدولَ وثائقَ للمعدةِ إطلاقًا (استمارة/تأمين/فحص دوري).
--
-- قرارات المالك:
--   ① الرخصةُ المنتهية: تحذيرٌ ظاهرٌ والحفظُ يمرّ — المنعُ يُفعَّل بعلَمٍ لاحقًا.
--   ② جدولٌ جديدٌ تُرحَّل إليه حقولُ equipments الخمسة صفًّا أولًا؛ القديمةُ
--     تبقى قراءةً حتى يطمئن.
--   ③ النطاق: الوثائقُ والتنبيهات فقط (قراءاتُ العدّاد جلسةٌ لاحقة).
--
-- توسيعٌ مقصود عن نص UX-10: subject_type يشمل المشغّل (operator) — فرخصُ
-- المشغّلين وثائقُ انتهاءٍ بالمعنى نفسِه، والمقيسُ يثبت أنها الألمُ الأكبر
-- (25 منتهية). النصُّ عدّد المعدةَ ولم يمنع غيرَها؛ والتعميمُ يوحّد آليةَ
-- التنبيه بدل آليتين. **معلَنٌ لكاتب الوثيقة.**
--
-- التطبيق عبر database/migrate.php حصرًا بعميل utf8mb4.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `equipment_documents` (
  `doc_id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,

  -- صاحبُ الوثيقة: معدةٌ أو مشغّل (توسيعٌ معلَن — انظر الرأس)
  `subject_type`  ENUM('equipment','operator') NOT NULL DEFAULT 'equipment',
  `subject_id`    INT UNSIGNED NOT NULL
      COMMENT 'equipments.id أو employees.id بحسب subject_type — مرجعٌ مرن',

  `doc_type`      ENUM('استمارة','تأمين','فحص دوري','رخصة قيادة','رخصة تشغيل','تصريح','أخرى')
      NOT NULL COMMENT 'UX-10 §8.1 + رخصُ المشغّلين (التوسيع المعلَن)',
  `doc_no`        VARCHAR(100) NULL,
  `issuer`        VARCHAR(255) NULL COMMENT 'جهةُ الإصدار',
  `issue_date`    DATE NULL,
  `expiry_date`   DATE NULL COMMENT 'NULL = وثيقةٌ لا تنتهي (نادر — تُعلَن)',
  `alert_days`    SMALLINT UNSIGNED NOT NULL DEFAULT 30
      COMMENT 'التنبيهُ قبل الانتهاء بهذه المدة (§8.1)',
  `file_ref`      VARCHAR(255) NULL COMMENT 'مسارُ المرفق',
  `status`        ENUM('سارية','منتهية','قيد التجديد','ملغاة') NOT NULL DEFAULT 'سارية'
      COMMENT 'حالةٌ يديرها البشر — والانتهاءُ الفعلي يُحسب من expiry_date لا منها',
  `note`          VARCHAR(200) NULL,

  `migrated_from` VARCHAR(40) NULL
      COMMENT 'نسبُ الترحيل: equipments.license / equipment_operators.license — NULL للجديد',
  `created_by`    INT UNSIGNED NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`    TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`    DATETIME NULL,
  `deleted_by`    INT UNSIGNED NULL,

  PRIMARY KEY (`doc_id`),
  KEY `ix_expiry`  (`company_id`, `expiry_date`) COMMENT 'فهرسُ التنبيه الآلي (§8.1)',
  KEY `ix_subject` (`company_id`, `subject_type`, `subject_id`),
  UNIQUE KEY `uq_doc` (`company_id`, `subject_type`, `subject_id`, `doc_type`, `doc_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='UX-10 §8.1 — وثائقُ المعدة والمشغّل بتواريخ انتهائها وتنبيهها';

-- ═══ الترحيل صفًّا أولًا (عطالةً عبر migrated_from) ═══

-- ① رخصُ المعدات من الحقول الخمسة القائمة في equipments (القديمةُ تبقى قراءةً)
INSERT INTO `equipment_documents`
  (`company_id`, `subject_type`, `subject_id`, `doc_type`, `doc_no`, `issuer`,
   `expiry_date`, `alert_days`, `status`, `note`, `migrated_from`, `created_at`)
SELECT e.`company_id`, 'equipment', e.`id`,
       CASE WHEN e.`document_type` LIKE '%تأمين%' THEN 'تأمين'
            WHEN e.`document_type` LIKE '%استمار%' THEN 'استمارة'
            ELSE 'رخصة تشغيل' END,
       NULLIF(e.`license_number`, ''), NULLIF(e.`license_authority`, ''),
       e.`license_expiry_date`, 30,
       CASE WHEN e.`license_expiry_date` IS NOT NULL AND e.`license_expiry_date` < CURDATE()
            THEN 'منتهية' ELSE 'سارية' END,
       CONCAT('مُرحَّلة من بطاقة المعدة',
              CASE WHEN NULLIF(e.`insurance_status`,'') IS NOT NULL
                   THEN CONCAT(' · حالة التأمين القديمة: ', e.`insurance_status`) ELSE '' END),
       'equipments.license', NOW()
FROM `equipments` e
WHERE (NULLIF(e.`license_number`, '') IS NOT NULL OR e.`license_expiry_date` IS NOT NULL)
  AND NOT EXISTS (SELECT 1 FROM `equipment_documents` d
                   WHERE d.`company_id` = e.`company_id` AND d.`subject_type` = 'equipment'
                     AND d.`subject_id` = e.`id` AND d.`migrated_from` = 'equipments.license');

-- ② رخصُ المشغّلين من equipment_operators (سجلُّ التأهيل يبقى كما هو)
INSERT INTO `equipment_documents`
  (`company_id`, `subject_type`, `subject_id`, `doc_type`, `doc_no`, `issuer`,
   `issue_date`, `expiry_date`, `alert_days`, `file_ref`, `status`, `note`,
   `migrated_from`, `created_at`)
SELECT eo.`company_id`, 'operator', eo.`employee_id`, 'رخصة قيادة',
       NULLIF(eo.`license_number`, ''), NULLIF(eo.`license_issuer`, ''),
       eo.`license_issue_date`, eo.`license_expiry_date`, 30,
       NULLIF(eo.`license_photo`, ''),
       CASE WHEN eo.`license_expiry_date` IS NOT NULL AND eo.`license_expiry_date` < CURDATE()
            THEN 'منتهية' ELSE 'سارية' END,
       CONCAT('مُرحَّلة من سجل التأهيل',
              CASE WHEN NULLIF(eo.`license_type`,'') IS NOT NULL
                   THEN CONCAT(' · ', eo.`license_type`) ELSE '' END),
       'equipment_operators.license', NOW()
FROM `equipment_operators` eo
WHERE (NULLIF(eo.`license_number`, '') IS NOT NULL OR eo.`license_expiry_date` IS NOT NULL)
  AND NOT EXISTS (SELECT 1 FROM `equipment_documents` d
                   WHERE d.`company_id` = eo.`company_id` AND d.`subject_type` = 'operator'
                     AND d.`subject_id` = eo.`employee_id`
                     AND d.`migrated_from` = 'equipment_operators.license');
