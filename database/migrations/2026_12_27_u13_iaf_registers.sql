-- update0013 · البند ⑧ تتمة — سجلَّا اختصاصاتِ المراجعةِ وصلاحياتِها وتقييمُ جودتها
-- ═══════════════════════════════════════════════════════════════════════════
-- المصدر: IAF-01 §٤-٣ (IAF-0012..IAF-0031) · §٤-٤ (IAF-0032..IAF-0043)
--         · §٤-١ (IAF-0008 · IAF-0031)
--
-- ◆ لماذا تُسجَّل الاختصاصاتُ والصلاحياتُ جداولَ لا نصًّا في وثيقة:
--   IAF-01 يعلن «الاختصاصات ٢٠» و«الصلاحياتُ في النظام ١٢» ولكلٍّ اختبارُ قبولٍ
--   مكتوب — «منفَّذٌ بشاهدِه» و«متاحةٌ ومسجَّلة». والشاهدُ لا يُقاس على نصٍّ في
--   ملفِّ Word؛ يُقاس على صفٍّ يُقارَن بالحيّ. فتسجيلُها هنا هو ما يجعل دعوى
--   «منفَّذٌ» قابلةً للفحص.
--
-- idempotent: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS `iaf_competencies` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code`        VARCHAR(12)  NOT NULL COMMENT 'IAF-C01 ..',
  `seq`         TINYINT UNSIGNED NOT NULL,
  `title`       VARCHAR(300) NOT NULL COMMENT 'الاختصاصُ كما تسميه الوثيقة',
  `accept_test` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'شاهدُ قبولِه',
  `doc_ref`     VARCHAR(24)  NOT NULL DEFAULT '',
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_c` (`company_id`, `code`), KEY `ix_seq` (`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='IAF-01 §4-3 — اختصاصاتُ المراجعةِ العشرون';

CREATE TABLE IF NOT EXISTS `iaf_authorities` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `code`        VARCHAR(12)  NOT NULL COMMENT 'IAF-A01 ..',
  `seq`         TINYINT UNSIGNED NOT NULL,
  `title`       VARCHAR(300) NOT NULL,
  `mode`        ENUM('read','write_own','forbidden') NOT NULL DEFAULT 'read'
                COMMENT 'IAF-0043 — ولا كتابةَ على السجلاتِ الأصلية بحال',
  `accept_test` VARCHAR(400) NOT NULL DEFAULT '',
  `doc_ref`     VARCHAR(24)  NOT NULL DEFAULT '',
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_a` (`company_id`, `code`), KEY `ix_seq` (`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='IAF-01 §4-4 — صلاحياتُ المراجعِ داخلَ النظامِ الاثنتا عشرة';

CREATE TABLE IF NOT EXISTS `iaf_quality_reviews` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `review_no`   VARCHAR(40)  NOT NULL,
  `kind`        ENUM('internal','external') NOT NULL DEFAULT 'internal',
  `period_label` VARCHAR(60) NOT NULL DEFAULT '',
  `scope_label` VARCHAR(300) NOT NULL DEFAULT '',
  `conformance` ENUM('conforms','partially_conforms','does_not_conform') NULL,
  `findings_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `summary`     VARCHAR(800) NOT NULL DEFAULT '',
  `reviewed_by` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'الجهةُ المقيِّمة — داخليةٌ أو خارجية',
  `reviewed_at` DATETIME     NOT NULL,
  `next_due`    DATE         NULL,
  `created_by`  INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_q` (`company_id`, `review_no`),
  KEY `ix_when` (`company_id`, `reviewed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='IAF-0008 · IAF-0031 — تقييمُ جودةِ المراجعةِ الدوري';
