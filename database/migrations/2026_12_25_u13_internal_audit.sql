-- update0013 · البند ⑧ — المراجعةُ الداخليةُ المستقلة
-- ═══════════════════════════════════════════════════════════════════════════
-- المصدر: IAF-01 §٤-١ .. §٤-٥ · PROP-01 §٤-١ البند ٧ · CEO-Y0119 · CEO-Y0125
--
-- الأحكامُ الحاكمة:
--   IAF-0001  ◆ وظيفةُ ضمانٍ **مستقلة** وليست وحدةً داخلَ الإدارةِ المالية
--             وإن راجعت أعمالَها جوهريًّا.
--   IAF-0004  ولا تتبع الماليةَ ولا رئيسَ الحساباتِ ولا الحوكمةَ في إصدارِ أحكامها.
--   IAF-0006  ولا تشارك في إعدادِ قيدٍ ولا اعتمادِ دفعٍ ولا تنفيذِه ولا مطابقةٍ
--             ولا إقفال — **ولا تصبح جزءًا مما ستراجعه لاحقًا**.
--   IAF-0043  ◆ **ولا كتابةَ لها على السجلاتِ التشغيليةِ أو الماليةِ الأصلية** —
--             قراءةٌ مستقلةٌ فقط. فجداولُها منفصلةٌ ولا تلمس جدولًا ماليًّا.
--   IAF-0044  والدورةُ لا تُقفز: ميثاقٌ ← كونٌ رقابيٌّ ← خطةٌ ← برنامجٌ ← مهمةٌ ←
--             أوراقُ عملٍ ← ملاحظةٌ ← ردُّ الإدارةِ ← خطةُ معالجةٍ ← محضرُ إغلاقٍ
--             ← تقريرٌ للجهةِ المشرفة.
--   §٢-٢      ولا تُغلق ملاحظةٌ **بلا دليلٍ يقبله المراجعُ** — ولا تُغلق من
--             الإدارةِ نفسِها · والتصعيدُ آليٌّ بالمهلةِ ولا يملك أحدٌ منعَه.
--   CEO-Y0125 ولا يملك الرئيسُ إغلاقَ ملاحظةٍ بلا دليلٍ يقبله المراجع.
--
-- idempotent: CREATE TABLE IF NOT EXISTS.

-- ═══ ① الميثاقُ والاستقلال ═══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `iaf_charter` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `version`        VARCHAR(20)  NOT NULL,
  `functional_line` ENUM('board','audit_committee','ceo') NOT NULL DEFAULT 'ceo'
                   COMMENT 'IAF-0002 — مجلسٌ أو لجنةٌ · وعند عدمهما الرئيسُ بميثاقٍ مؤقت',
  `admin_line`     VARCHAR(120) NOT NULL DEFAULT 'الرئيس التنفيذي — إداريًّا فقط',
  `purpose`        VARCHAR(600) NOT NULL DEFAULT '',
  `authority`      VARCHAR(600) NOT NULL DEFAULT '',
  `independence`   VARCHAR(600) NOT NULL DEFAULT '',
  `not_following`  VARCHAR(300) NOT NULL DEFAULT 'لا المالية ولا رئيس الحسابات ولا الحوكمة',
  `approved_by`    INT UNSIGNED NULL,
  `approved_at`    DATETIME     NULL,
  `state`          ENUM('draft','approved','superseded') NOT NULL DEFAULT 'draft',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_ch` (`company_id`, `version`), KEY `ix_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='IAF-01 §4-1 — ميثاقُ المراجعةِ والاستقلال';

-- إقرارُ الاستقلالِ وتعارضِ المصالح — IAF-0009 «سنويٌّ لكل مراجعٍ وقبلَ كل تكليف».
CREATE TABLE IF NOT EXISTS `iaf_independence` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `auditor_id`  INT UNSIGNED NOT NULL,
  `scope_ref`   VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'فارغٌ = الإقرارُ السنويّ',
  `declared_at` DATETIME     NOT NULL,
  `has_conflict` TINYINT(1)  NOT NULL DEFAULT 0,
  `conflict_note` VARCHAR(400) NOT NULL DEFAULT '',
  `valid_until` DATE         NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ind` (`company_id`, `auditor_id`, `scope_ref`),
  KEY `ix_valid` (`company_id`, `valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='IAF-0009 — إقرارُ الاستقلالِ سنويًّا وقبلَ كل تكليف';

-- ═══ ② الكونُ الرقابيُّ والخطة ═══════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `iaf_universe` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `area_code`   VARCHAR(40)  NOT NULL,
  `area_name`   VARCHAR(200) NOT NULL,
  `owner_dept`  VARCHAR(120) NOT NULL DEFAULT '',
  `risk_score`  DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'IAF-0014 — التقييمُ السنويُّ للمخاطر',
  `last_audited` DATE        NULL,
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_area` (`company_id`, `area_code`),
  KEY `ix_risk` (`company_id`, `risk_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='IAF-0013 — سجلُّ الكونِ الرقابي';

CREATE TABLE IF NOT EXISTS `iaf_plan` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `plan_year`   SMALLINT UNSIGNED NOT NULL,
  `charter_id`  INT UNSIGNED NOT NULL COMMENT 'IAF-0044 — لا خطةَ بلا ميثاق',
  `title`       VARCHAR(200) NOT NULL DEFAULT '',
  `basis`       VARCHAR(300) NOT NULL DEFAULT 'مبنيةٌ على المخاطر',
  `approved_by` INT UNSIGNED NULL,
  `approved_at` DATETIME     NULL,
  `state`       ENUM('draft','approved','closed') NOT NULL DEFAULT 'draft',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_plan` (`company_id`, `plan_year`),
  KEY `ix_charter` (`charter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='IAF-0015 — خطةُ المراجعةِ السنويةُ المبنيةُ على المخاطر';

-- ═══ ③ المهمةُ وأوراقُ العمل ═════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `iaf_engagements` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `engagement_no` VARCHAR(40) NOT NULL,
  `plan_id`      INT UNSIGNED NOT NULL COMMENT 'IAF-0044 — لا مهمةَ بلا خطة',
  `area_code`    VARCHAR(40)  NOT NULL,
  `title`        VARCHAR(200) NOT NULL,
  `lead_auditor` INT UNSIGNED NOT NULL,
  `audit_kind`   ENUM('financial','operational','it','compliance','fraud') NOT NULL DEFAULT 'operational',
  `started_at`   DATE         NULL,
  `ended_at`     DATE         NULL,
  `state`        ENUM('planned','fieldwork','reporting','closed') NOT NULL DEFAULT 'planned',
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_eng` (`company_id`, `engagement_no`),
  KEY `ix_plan` (`plan_id`), KEY `ix_area` (`area_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='IAF-01 §4-5 — مهامُّ المراجعة';

-- IAF-0037: «سحبُ الأدلةِ وحفظُ نسخِ مراجعةٍ **غيرِ قابلةٍ للتعديل**».
CREATE TABLE IF NOT EXISTS `iaf_workpapers` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL,
  `engagement_id` INT UNSIGNED NOT NULL,
  `wp_ref`        VARCHAR(60)  NOT NULL,
  `title`         VARCHAR(200) NOT NULL DEFAULT '',
  `evidence_hash` CHAR(64)     NOT NULL DEFAULT '' COMMENT 'بصمةُ النسخةِ — تُثبت عدمَ التعديل',
  `captured_at`   DATETIME     NOT NULL,
  `captured_by`   INT UNSIGNED NOT NULL,
  `frozen`        TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'غيرُ قابلةٍ للتعديلِ بعد الالتقاط',
  PRIMARY KEY (`id`), UNIQUE KEY `uq_wp` (`company_id`, `engagement_id`, `wp_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='IAF-0037 — أوراقُ العملِ ونسخُ الأدلةِ المجمَّدة';

-- ═══ ④ الملاحظةُ ودورتُها — قلبُ الاستقلال ══════════════════════════════
-- «لا تُغلق ملاحظةٌ بلا دليلٍ يقبله المراجعُ — ولا تُغلق من الإدارةِ نفسِها»
-- فعمودُ `closed_by` يُقيَّد بالمراجعِ وحدَه، و`evidence_accepted` شرطُ الإغلاق.
CREATE TABLE IF NOT EXISTS `iaf_findings` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `finding_no`     VARCHAR(40)  NOT NULL,
  `engagement_id`  INT UNSIGNED NOT NULL,
  `area_code`      VARCHAR(40)  NOT NULL DEFAULT '',
  `auditee_dept`   VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'الإدارةُ المُراجَعة',
  `auditee_user_id` INT UNSIGNED NULL,
  `title`          VARCHAR(300) NOT NULL,
  `detail`         MEDIUMTEXT   NULL,
  `severity`       ENUM('critical','high','medium','low') NOT NULL DEFAULT 'medium',
  `raised_by`      INT UNSIGNED NOT NULL COMMENT 'المراجعُ الداخليُّ حصرًا',
  `raised_at`      DATETIME     NOT NULL,
  -- ردُّ الإدارةِ إلزاميٌّ بمهلة (BF-15 · IAF-0039)
  `response_due`   DATE         NULL,
  `response_text`  MEDIUMTEXT   NULL,
  `responded_by`   INT UNSIGNED NULL,
  `responded_at`   DATETIME     NULL,
  -- خطةُ المعالجة
  `action_plan`    MEDIUMTEXT   NULL,
  `action_owner`   INT UNSIGNED NULL,
  `action_due`     DATE         NULL,
  -- الإغلاق: بدليلٍ يقبله المراجعُ وحدَه
  `evidence_ref`   VARCHAR(300) NOT NULL DEFAULT '',
  `evidence_accepted` TINYINT(1) NOT NULL DEFAULT 0
                   COMMENT '◆ لا إغلاقَ بلا قبولِ المراجعِ للدليل — ولو من الرئيس',
  `accepted_by`    INT UNSIGNED NULL COMMENT 'المراجعُ الذي قَبِل الدليل',
  `closed_by`      INT UNSIGNED NULL COMMENT '◆ المراجعُ حصرًا — لا الإدارةُ المُراجَعة',
  `closed_at`      DATETIME     NULL,
  `state`          ENUM('open','responded','in_remediation','evidence_submitted','closed','escalated')
                   NOT NULL DEFAULT 'open',
  -- التصعيدُ آليٌّ بالمهلةِ ولا يملك أحدٌ منعَه
  `escalated_at`   DATETIME     NULL,
  `escalated_to`   ENUM('ceo','board','audit_committee') NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_find` (`company_id`, `finding_no`),
  KEY `ix_state` (`company_id`, `state`, `severity`),
  KEY `ix_eng` (`engagement_id`),
  KEY `ix_due` (`company_id`, `action_due`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='IAF-01 §4-5 — ملاحظاتُ المراجعةِ ودورتُها';

-- ═══ ⑤ سجلُّ الاطّلاعِ الحساس — الوظيفةُ الرقابيةُ مراقَبةٌ أيضًا ═══════
-- OBL-0127: «ولكل إدارةٍ ماليةٍ مساحةُ مخاطرٍ ومساحةُ حوكمةٍ نطاقيتان — **بما
--   فيها المراجعةُ الداخلية** · فالوظيفةُ الرقابيةُ مراقَبةٌ أيضًا.»
CREATE TABLE IF NOT EXISTS `iaf_access_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `auditor_id` INT UNSIGNED NOT NULL,
  `scope_kind` VARCHAR(60)  NOT NULL COMMENT 'ما اطُّلع عليه',
  `scope_ref`  VARCHAR(160) NOT NULL DEFAULT '',
  `purpose`    VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'مرجعُ المهمةِ التي تُبرِّر الاطّلاع',
  `engagement_id` INT UNSIGNED NULL,
  `accessed_at` DATETIME    NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_aud` (`company_id`, `auditor_id`, `accessed_at`),
  KEY `ix_scope` (`scope_kind`, `scope_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='IAF-0036 + OBL-0127 — سجلُّ اطّلاعِ المراجعِ نفسِه';
