-- update0013 · سدُّ ثغراتِ التغطيةِ التي كشفها الفاحصُ العكسي
-- ═══════════════════════════════════════════════════════════════════════════
-- كشف `u13_reverse_audit` تغطيةً 46.2٪: بوابةُ القبولِ كانت تفحص **ما بُني**،
-- والوثائقُ تعلن عوائلَ بنودٍ لا أثرَ حيًّا لها. وهذه الجداولُ تُعطيها أثرَها.
--
-- ◆ ولماذا جداولُ لا نصٌّ في وثيقة: كلُّ بندٍ من هذه العوائلِ يحمل في الوثيقةِ
--   دعوى «منفَّذٌ بشاهدِه» أو «متاحةٌ ومسجَّلة». والدعوى بلا صفٍّ يقابلها **لا
--   تُقاس** — فيصير الإعلانُ حكمًا على الورقِ لا حكمًا في النظام.
--
-- المصدر: FIN-ACC-01 §٤-٨ · FIN-CTRL-01 §٤-٣ · §٤-٦ · FIN-MGR-01 §٤-٦
--         · FIN-TRE-01 §٤-٢ · §٤-٤ · §٤-٨ · IAF-01 §٤-١ · §٤-٥ · §٤-٨
--
-- idempotent: CREATE TABLE IF NOT EXISTS.

-- ═══ ① الحدودُ الصريحة — «ما لا يملكه» في الوثائقِ الخمس ═════════════════
-- FACC-0045..0057 (13) · FCTRL (9) · FMGR (8) · FTRE (12) · IAF (13) = 55
-- ◆ العمودُ الحاكمُ `enforced_by`: الحدُّ بلا مُنفِذٍ **دعوى لا قيد** — وهو
--   الدرسُ نفسُه المستفادُ من مصفوفةِ فصلِ الواجبات.
CREATE TABLE IF NOT EXISTS `gov_authority_limits` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `doc_code`     VARCHAR(24)  NOT NULL COMMENT 'الوثيقةُ التي تُعلن الحد',
  `code`         VARCHAR(24)  NOT NULL COMMENT 'LIMIT-01 ..',
  `seq`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `subject_role` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'من لا يملك — كما تسميه الوثيقة',
  `role_ids`     VARCHAR(60)  NOT NULL DEFAULT '' COMMENT 'أدوارُه مفصولةً بفاصلة',
  `forbidden`    VARCHAR(300) NOT NULL COMMENT 'الفعلُ الممنوع',
  `enforced_by`  VARCHAR(200) NOT NULL DEFAULT '' COMMENT '◆ المُنفِذُ الحي — والفارغُ دعوى لا قيد',
  `enforce_kind` ENUM('service','guard','schema','permission','manual','none') NOT NULL DEFAULT 'none',
  `accept_test`  VARCHAR(300) NOT NULL DEFAULT '',
  `doc_ref`      VARCHAR(24)  NOT NULL DEFAULT '',
  `active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lim` (`company_id`, `doc_code`, `code`),
  KEY `ix_enf` (`enforce_kind`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='الوثائقُ الخمس — الحدودُ الصريحةُ «ما لا يملكه» بمُنفِذِ كلٍّ';

-- ═══ ② مراحلُ الدوراتِ — الدفعُ والقبضُ والمراجعةُ والمحاسبة ═════════════
-- FTRE-0059 (15) · FTRE-0060 (9) · IAF-0044 (11) · FACC-0037 (8)
-- ◆ «تُنفَّذ بترتيبها ولا تُقفز» — والترتيبُ عمودٌ لا تعليقٌ في نص.
CREATE TABLE IF NOT EXISTS `fin_cycle_stages` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `cycle_kind`  ENUM('payment','receipt','audit','accountant') NOT NULL,
  `seq`         SMALLINT UNSIGNED NOT NULL COMMENT 'ترتيبُ المرحلةِ — ولا تُقفز',
  `stage_ar`    VARCHAR(200) NOT NULL,
  `owner_hint`  VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'من يملك المرحلة',
  `doc_code`    VARCHAR(24)  NOT NULL DEFAULT '',
  `doc_ref`     VARCHAR(24)  NOT NULL DEFAULT '',
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stage` (`company_id`, `cycle_kind`, `seq`),
  KEY `ix_cycle` (`cycle_kind`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-TRE-01 §4-4 · IAF-01 §4-5 · FIN-ACC-01 §4-5 — مراحلُ الدوراتِ بترتيبها';

-- ═══ ③ مؤشراتُ جودةِ المحاسبةِ الاثنا عشرَ (FCTRL-0047..0058) ═══════════
-- ◆ عيبٌ كشفه الفاحصُ العكسي: شاشةُ «مؤشرات جودة المحاسبة» كانت تقرأ
--   `fin_obl_rules WHERE family='OR'` — وتلك **قواعدُ محرّكِ الالتزامات** لا
--   مؤشراتُ الجودة. جدولٌ يحمل غيرَ ما تدّعي الشاشةُ أسوأُ من شاشةٍ فارغة.
CREATE TABLE IF NOT EXISTS `fin_quality_kpis` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `code`        VARCHAR(12)  NOT NULL COMMENT 'KPI-01..KPI-12',
  `seq`         TINYINT UNSIGNED NOT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `threshold`   VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'حدُّه',
  `owner_role`  VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'مالكُه',
  `cadence`     VARCHAR(60)  NOT NULL DEFAULT '' COMMENT 'دوريةُ قياسه',
  `source_sql`  VARCHAR(500) NOT NULL DEFAULT ''
                COMMENT 'FCTRL-0047 — محسوبٌ من القيودِ لا من إدخالٍ يدوي',
  `doc_ref`     VARCHAR(24)  NOT NULL DEFAULT '',
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kpi` (`company_id`, `code`), KEY `ix_seq` (`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-CTRL-01 §4-3 — مؤشراتُ جودةِ المحاسبةِ الاثنا عشر';

-- ═══ ④ الأدوارُ الثمانيةُ داخلَ وحدةِ الخزينة (FTRE-0002..0009) ══════════
CREATE TABLE IF NOT EXISTS `fin_treasury_roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  `code`        VARCHAR(12)  NOT NULL COMMENT 'TRE-R01..TRE-R08',
  `seq`         TINYINT UNSIGNED NOT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `role_id`     INT UNSIGNED NULL COMMENT 'الدورُ المقابلُ في roles إن وُجد',
  `scope_note`  VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'حدودُه وفصلُ واجباتِه عن غيره',
  `doc_ref`     VARCHAR(24)  NOT NULL DEFAULT '',
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trole` (`company_id`, `code`), KEY `ix_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-TRE-01 §4-2 — الأدوارُ الثمانيةُ داخلَ وحدةِ الخزينة';
