-- ═══════════════════════════════════════════════════════════════════════════
-- H-11 · لقطةُ العقد الثابتة ببصمتها — Insert-only — 2026-07-30
-- البطاقة: docs/specs/H-11_contract_snapshots.md · المصدر: ENT-01 §2
-- ───────────────────────────────────────────────────────────────────────────
-- «تُثبَّت القيمُ المستخدمة في الاحتساب لقطةً غيرَ قابلةٍ للتعديل — ببصمةٍ
-- محسوبةٍ من مضمونها» و«الإبطالُ من تاريخ سريانها فقط».
-- الجدولُ Insert-only عمدًا: لا updated_at ولا أعمدةَ حذفٍ — الصفُّ يُدرج
-- ويُبطل (valid=0 بأعمدته الثلاثة) ولا يُمسّ مضمونُه أبدًا.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `contract_snapshots` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `contract_id` INT NOT NULL,
  `as_of_date` DATE NOT NULL COMMENT 'تاريخُ الاحتساب الذي أُخذت له اللقطة',
  `snapshot_json` MEDIUMTEXT NOT NULL COMMENT 'المضمونُ القانوني: الرأسُ + المكوّناتُ + القواعدُ بتوزيعها + التحمّل — فرزٌ ثابت',
  `fingerprint` CHAR(40) NOT NULL COMMENT 'sha1 من المضمون القانوني — كشفُ التلاعب بالمقارنة',
  `amendment_ref` INT NULL DEFAULT NULL COMMENT 'آخرُ ملحقٍ ساري — NULL معلَنًا حتى تُبنى H-10 (لا اختراع)',
  `valid` TINYINT NOT NULL DEFAULT 1,
  `invalidated_at` DATETIME NULL DEFAULT NULL,
  `invalidated_from` DATE NULL DEFAULT NULL COMMENT 'تاريخُ سريان الإبطال — ما قبله يبقى محكومًا بلقطته',
  `invalidation_reason` VARCHAR(160) NULL DEFAULT NULL,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_cs_contract_asof` (`contract_id`, `as_of_date`, `valid`),
  KEY `ix_cs_company` (`company_id`),
  KEY `ix_cs_fingerprint` (`fingerprint`),
  CONSTRAINT `fk_cs_contract` FOREIGN KEY (`contract_id`) REFERENCES `employee_contracts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
