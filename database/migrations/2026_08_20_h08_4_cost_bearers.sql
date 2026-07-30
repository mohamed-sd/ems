-- ═══════════════════════════════════════════════════════════════════════════
-- H-08-④ · جهاتُ التحمّل بنسبها — Σ=100 رفضًا للحفظ (خاتمةُ H-08) — 2026-07-30
-- البطاقة: docs/specs/H-08_4_cost_bearers.md · المصدر: CON-01 §3.3/§7.1
-- ───────────────────────────────────────────────────────────────────────────
-- «لكل مكوّنٍ وحافزٍ جهتُه — ومجموعُ نسب التحمّل لكل عنصرٍ مئةٌ بالمئة».
-- قيدُ Σ في الخدمة (گوتشا CHECK لا يرى صفوفًا أخرى): طيٌّ ناعمٌ + إدراجٌ في
-- معاملةٍ واحدة، وΣ ≠ 100.00 يُرفض قبل أي كتابة — لا حفظَ جزئيًّا أبدًا.
-- الحذفُ ناعمٌ عمدًا: استبدالُ الجهات قرارُ تحميلٍ يُراجَع فلا يُمحى أثرُه.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `cost_bearers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `owner_type` ENUM('component','rule') NOT NULL COMMENT 'المالك: مكوّنُ أجرٍ أو قاعدةُ حافز (§7.1)',
  `owner_id` INT NOT NULL,
  `bearer_type` ENUM('project','client_contract','dept','company') NOT NULL
      COMMENT 'جهاتُ §3.3 الأربع: مشروعٌ · عقدُ عميل · إدارةٌ داخلية · كيانُ الشركة',
  `bearer_id` INT NULL DEFAULT NULL COMMENT 'NULL لجهة company (صاحبُ العمل نفسُه)',
  `percent` DECIMAL(5,2) NOT NULL,
  `created_by` INT NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_cb_owner` (`owner_type`, `owner_id`),
  KEY `ix_cb_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
