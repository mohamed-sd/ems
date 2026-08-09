-- update0013 · البند ⑤ — الطبقاتُ الثلاثُ واختبارُ التجنبِ ومحرّكُ الالتزامات
-- ═══════════════════════════════════════════════════════════════════════════
-- المصدر: FIN-OBL-01 §٤-٥ · §٤-٦ · §٤-١١ · §٤-١٢ · §٤-١٣ · §٤-١٦ · §٤-١٩
--         · §٤-٢٠ · §٤-٢٢ · §٤-٢٣
--
-- الأحكامُ التي تبني هذه الجداول:
--   OBL-0021/OR-01  ◆ الالتزامُ يُنشأ عند **اعتمادِ العقدِ** لا عند أولِ دفعة —
--                   والعقدُ النافذُ يولّد جدولَ استحقاقٍ لكلِّ مدتِه فورًا.
--   OR-02           جدولُ الاستحقاقِ يحمل تاريخَ كلِّ استحقاقٍ **بيومه** لا شهرًا مجملًا.
--   OR-03           التصنيفُ قصيرًا أو طويلًا بتاريخِ الاستحقاقِ آليًّا · ويُعاد كلَّ إقفال.
--   OR-05           المستحقُّ غيرُ المدفوعِ يتحول إلى **ذمةٍ دائنة** ويظهر في أعمارها.
--   OR-06           الالتزامُ يخفض المتاحَ: المتاحُ = المعتمدُ − المنفَّذُ − الملتزَمُ به.
--   OR-07           تعديلُ العقدِ **لا يحذف** الجدولَ القديم — يُغلقه ويُنشئ جديدًا يشير إليه.
--   OR-08           إنهاءُ العقدِ يُغلق ما لم يستحقَّ بعدُ — والمستحقُّ قبلَه يبقى.
--   OR-10/OBL-0051  ◆ **المحرّكُ لا يُنشئ قيدًا** بل جدولًا معلَنًا — والقيدُ عند
--                   الاستحقاقِ أو الاستلامِ أو الدفع. فلا عمودَ قيدٍ هنا بحال.
--   OBL-0137        الطبقاتُ الثلاثُ **لا تُدمج ولا تُقفز** — وتُعرض بأعمدةٍ مستقلة.
--   OBL-0204        العقدُ الواحدُ يحمل **التزامين**: الحجمَ يسقط بالعجزِ والجزاءَ لا
--                   يسقط — ولا يُدمجان في رقمٍ واحدٍ بحال.
--   SY-02/SY-03     عقدٌ اثنا عشرَ شهرًا يبدأ يومَ عشرين يمسُّ **ثلاثةَ عشرَ** إقفالًا
--                   محاسبيًّا و**اثني عشرَ** تعاقديًّا — ولا يُخلطان.
--   SY-04/SY-05     الفترةُ الكسريةُ بالتناسبِ اليوميِّ وتُوسَم صريحًا.
--
-- idempotent: CREATE TABLE IF NOT EXISTS.

-- ═══ ① المراجع: الطبقاتُ · التجنبُ · الأنواعُ · القواعدُ · التنبيهات ═════
CREATE TABLE IF NOT EXISTS `fin_obl_layers` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code`      VARCHAR(4)   NOT NULL COMMENT 'L1 · L2 · L3',
  `seq`       TINYINT UNSIGNED NOT NULL,
  `title`     VARCHAR(120) NOT NULL,
  `birth`     VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'متى تنشأ',
  `rule_text` VARCHAR(400) NOT NULL DEFAULT '',
  `sides`     VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'أثرُها على جانبي الإيرادِ والمصروف',
  `doc_ref`   VARCHAR(24)  NOT NULL DEFAULT '',
  `active`    TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_l` (`company_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-11 — الطبقاتُ الثلاثُ للاعتراف';

CREATE TABLE IF NOT EXISTS `fin_obl_avoidance_tests` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code`     VARCHAR(6)   NOT NULL COMMENT 'AV-1..AV-5',
  `seq`      TINYINT UNSIGNED NOT NULL COMMENT 'تُطبَّق بالترتيبِ ولا تُقفز',
  `question` VARCHAR(300) NOT NULL,
  `outcome`  VARCHAR(600) NOT NULL DEFAULT '',
  `doc_ref`  VARCHAR(24)  NOT NULL DEFAULT '',
  `active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_av` (`company_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-5 — اختبارُ التجنبِ الخماسي';

CREATE TABLE IF NOT EXISTS `fin_obl_types` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code`       VARCHAR(6)   NOT NULL COMMENT 'OB-01..OB-08',
  `title`      VARCHAR(160) NOT NULL,
  `born_when`  VARCHAR(200) NOT NULL DEFAULT '',
  `accounts`   VARCHAR(200) NOT NULL DEFAULT '',
  `formula`    VARCHAR(400) NOT NULL DEFAULT '',
  `term_rule`  VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'قصيرٌ أو طويلٌ بحسبِ ماذا',
  `posts_entry` TINYINT(1)  NOT NULL DEFAULT 0
                COMMENT 'OR-10 — صفرٌ دائمًا: المحرّكُ لا يُنشئ قيدًا',
  `doc_ref`    VARCHAR(24)  NOT NULL DEFAULT '',
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_ob` (`company_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-16 — أنواعُ الالتزامِ الثمانية';

CREATE TABLE IF NOT EXISTS `fin_obl_rules` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `family`    ENUM('OR','SY','AR','SR','IN') NOT NULL COMMENT 'الالتزام · التناظر · الاستحقاق · المورد · التوريث',
  `code`      VARCHAR(8)   NOT NULL,
  `rule_text` VARCHAR(700) NOT NULL,
  `accept_test` VARCHAR(400) NOT NULL DEFAULT '',
  `doc_ref`   VARCHAR(24)  NOT NULL DEFAULT '',
  `active`    TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_r` (`company_id`, `code`), KEY `ix_fam` (`family`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-13/4-16/4-19/4-20/4-21 — قواعدُ المحرّك';

CREATE TABLE IF NOT EXISTS `fin_obl_alerts` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code`      VARCHAR(6)   NOT NULL COMMENT 'AL-01..AL-12',
  `title`     VARCHAR(200) NOT NULL,
  `fires_when` VARCHAR(300) NOT NULL DEFAULT '',
  `destination` VARCHAR(300) NOT NULL DEFAULT '',
  `risk_if_ignored` VARCHAR(400) NOT NULL DEFAULT '',
  `lead_days` SMALLINT UNSIGNED NOT NULL DEFAULT 7 COMMENT 'مهلةُ الإطلاقِ قبلَ الحدث',
  `doc_ref`   VARCHAR(24)  NOT NULL DEFAULT '',
  `active`    TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_al` (`company_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-22 — التنبيهاتُ الاثنا عشر';

CREATE TABLE IF NOT EXISTS `fin_obl_recognition` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `contract_kind` VARCHAR(120) NOT NULL COMMENT 'نوعُ العقدِ كما تسميه الوثيقة',
  `standard`  VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'المعيارُ الحاكم',
  `trigger_text` VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'متى يتحقق',
  `layers_text`  VARCHAR(700) NOT NULL DEFAULT '' COMMENT 'الطبقاتُ الثلاثُ لهذا النوع',
  `guard_text`   VARCHAR(400) NOT NULL DEFAULT '',
  `doc_ref`   VARCHAR(24)  NOT NULL DEFAULT '',
  `active`    TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_rec` (`company_id`, `contract_kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-12 — شرطُ الاعترافِ بمعيارِ كلِّ نوع';

-- ═══ ② نتيجةُ اختبارِ التجنبِ لكلِّ عقد — الاثنا عشرَ عمودًا الإلزامية ═══
-- OBL-0200: «ولا يُترك عقدٌ بلا نتيجةِ اختبارٍ مسجَّلة».
-- OBL-0204: العمودان `volume_*` و`penalty_*` مستقلانِ **ولا يُدمجان بحال**.
CREATE TABLE IF NOT EXISTS `fin_obl_avoidance` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT UNSIGNED NOT NULL,
  `contract_kind`   VARCHAR(40)  NOT NULL COMMENT 'client · supplier · lease · employee · financing · po …',
  `contract_ref`    VARCHAR(120) NOT NULL,
  `contract_value`  DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `currency`        VARCHAR(8)   NOT NULL DEFAULT 'USD',
  -- ① AV-1
  `cancellable`     TINYINT(1)   NOT NULL COMMENT '◆ أالعقدُ قابلٌ للإلغاءِ من طرفنا؟',
  -- ② AV-2
  `cancel_cost`     DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ تكلفةُ الإلغاءِ أو الشرطُ الجزائي',
  `unavoidable`     DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ المبلغُ غيرُ القابلِ للتجنب',
  -- ③ AV-3
  `unavoidable_pct` DECIMAL(6,3) NOT NULL DEFAULT 0.000 COMMENT '◆ نسبتُه من قيمةِ العقد',
  `recognition_candidate` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '◆ أمرشَّحٌ للاعتراف؟',
  -- التزامان لا واحد (OBL-0204)
  `volume_obligation`  DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ التزامُ الحجمِ — يسقط بالعجز',
  `penalty_obligation` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ التزامُ الجزاءِ — لا يسقط',
  -- ④ AV-4 · ⑤ AV-5
  `special_standard` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '◆ المعيارُ الخاصُّ الموجِبُ للاعتراف',
  `onerous`         TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '◆ أعقدٌ مُثقِلٌ؟',
  `expected_benefit` DECIMAL(18,2) NULL COMMENT 'المنافعُ المتوقعةُ — يُقاس بها الإثقال',
  -- الشهادة
  `verdict`         ENUM('disclose_only','disclose_with_penalty','recognition_candidate','recognize','onerous')
                    NOT NULL COMMENT '◆ نتيجةُ اختبارِ التجنب',
  `decided_by`      INT UNSIGNED NOT NULL COMMENT '◆ ومن قرَّرها',
  `decided_at`      DATETIME     NOT NULL COMMENT '◆ تاريخُ نتيجةِ الاختبار',
  `next_review_at`  DATE         NULL COMMENT '◆ المراجعةُ القادمةُ للنتيجة',
  `steps_json`      VARCHAR(900) NOT NULL DEFAULT '' COMMENT 'أثرُ الخطواتِ الخمسِ بترتيبها',
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract` (`company_id`, `contract_kind`, `contract_ref`),
  KEY `ix_verdict` (`company_id`, `verdict`),
  KEY `ix_review` (`next_review_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-5/§4-6 — اختبارُ التجنبِ بأعمدتِه الاثني عشرَ الإلزامية';

-- ═══ ③ سجلُّ الالتزامات — رأسُ كلِّ التزامٍ مولَّد ═══════════════════════
CREATE TABLE IF NOT EXISTS `fin_obl_register` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `obligation_no`  VARCHAR(40)  NOT NULL,
  `ob_type`        VARCHAR(6)   NOT NULL COMMENT 'OB-01..OB-08',
  `side`           ENUM('payable','receivable') NOT NULL DEFAULT 'payable'
                   COMMENT 'SY-01 — القاعدةُ نفسُها على الجانبين والفرقُ في الاتجاه',
  `contract_kind`  VARCHAR(40)  NOT NULL,
  `contract_ref`   VARCHAR(120) NOT NULL,
  `counterparty`   VARCHAR(200) NOT NULL DEFAULT '',
  `currency`       VARCHAR(8)   NOT NULL DEFAULT 'USD',
  `total_value`    DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `start_date`     DATE         NOT NULL,
  `end_date`       DATE         NOT NULL,
  -- SY-02/SY-03: مقياسان مختلفان لا خطأٌ في أحدهما
  `accounting_periods` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '◆ عددُ الفتراتِ المحاسبية',
  `contract_periods`   SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '◆ عددُ الفتراتِ التعاقدية',
  `proration_basis` VARCHAR(60) NOT NULL DEFAULT 'daily' COMMENT '◆ أساسُ حسابِ الكسر',
  -- الأبعادُ التسعةُ موروثةٌ من العقد (OR-09)
  `project_id`     INT UNSIGNED NULL,
  `site_id`        INT UNSIGNED NULL,
  `equipment_id`   INT UNSIGNED NULL,
  `cost_center`    VARCHAR(60)  NOT NULL DEFAULT '',
  `party_type`     VARCHAR(16)  NOT NULL DEFAULT '',
  `party_id`       INT UNSIGNED NULL,
  `dims_json`      VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الأبعادُ التسعةُ كما وُرِّثت',
  -- الحالة
  `state`          ENUM('active','superseded','terminated','closed') NOT NULL DEFAULT 'active',
  `supersedes_id`  BIGINT UNSIGNED NULL COMMENT 'OR-07 — الجدولُ القديمُ يُغلق ويشير إليه الجديد',
  `amendment_ref`  VARCHAR(120) NOT NULL DEFAULT '',
  `terminated_at`  DATE         NULL,
  `generated_at`   DATETIME     NOT NULL,
  `generated_by`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_no` (`company_id`, `obligation_no`),
  KEY `ix_contract` (`company_id`, `contract_kind`, `contract_ref`, `state`),
  KEY `ix_type` (`ob_type`, `state`),
  KEY `ix_super` (`supersedes_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-16 — سجلُّ الالتزاماتِ المولَّدةِ عند نفاذِ العقد';

-- ═══ ④ جدولُ الاستحقاقاتِ — الطبقاتُ الثلاثُ بأعمدةٍ مستقلة ═════════════
-- ◆ لا عمودَ `journal_entry_id` هنا عمدًا: OR-10 «المحرّكُ لا يُنشئ قيدًا بل
--   جدولًا معلَنًا» — ووجودُ العمودِ دعوةٌ لخرقِ الحكم.
CREATE TABLE IF NOT EXISTS `fin_obl_schedule` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT UNSIGNED NOT NULL,
  `obligation_id`   BIGINT UNSIGNED NOT NULL,
  `period_no`       SMALLINT UNSIGNED NOT NULL COMMENT 'تسلسلُ الفترةِ داخلَ الجدول',
  `period_start`    DATE         NOT NULL,
  `period_end`      DATE         NOT NULL,
  `due_date`        DATE         NOT NULL COMMENT 'OR-02 — بيومِه لا شهرًا مجملًا',
  -- SY-05: الكسريةُ تُوسَم صريحًا
  `is_partial`      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '◆ أفترةٌ كسرية؟',
  `partial_days`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `month_days`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `proration_basis` VARCHAR(60)  NOT NULL DEFAULT '' COMMENT '◆ أساسُ حسابِ الكسر',
  -- الطبقاتُ الثلاثُ — مستقلةٌ ولا تُدمج (OBL-0137 · OBL-0157)
  `l1_commitment`   DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ L1 الارتباطُ — القيمةُ الكلية',
  `l1_remaining`    DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ الارتباطُ المتبقي غيرُ المنفَّذ',
  `l2_recognized`   DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ L2 المعترَفُ به في الفترة',
  `l2_cumulative`   DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ المعترَفُ به تراكميًّا',
  `l3_open`         DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ L3 الذمةُ القائمة',
  `settled`         DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ المسدَّدُ أو المحصَّل',
  `gap_l1_l2`       DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '◆ الفرقُ بين الارتباطِ والمعترَفِ به',
  `recognition_rule` VARCHAR(300) NOT NULL DEFAULT '' COMMENT '◆ شرطُ الاعترافِ المطبَّقُ ومعيارُه',
  -- OR-03: يُعاد كلَّ إقفال
  `term_class`      ENUM('short','long') NOT NULL DEFAULT 'short' COMMENT '◆ التصنيفُ قصيرٌ أو طويل',
  `reclassified_at` DATETIME     NULL,
  -- OR-05: المتأخرُ يُرحَّل إلى الذمم
  `state`           ENUM('scheduled','recognized','invoiced','settled','overdue','moved_to_payables','closed','cancelled')
                    NOT NULL DEFAULT 'scheduled',
  `moved_at`        DATETIME     NULL,
  `close_reason`    VARCHAR(300) NOT NULL DEFAULT '',
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_period` (`obligation_id`, `period_no`),
  KEY `ix_due` (`company_id`, `due_date`, `state`),
  KEY `ix_term` (`company_id`, `term_class`, `state`),
  KEY `ix_obl` (`obligation_id`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-13 — جدولُ الاستحقاقاتِ بأعمدتِه الثلاثةَ عشرَ الإلزامية';

-- ═══ ⑤ سجلُّ التنبيهاتِ المُطلَقة ════════════════════════════════════════
-- OBL-0125: «التنبيهُ المُهمَلُ بعد مهلتِه ينشر إشارةَ خطرٍ — فالتنبيهُ الذي
--   لا يُصعَّد لا يُنذر».
CREATE TABLE IF NOT EXISTS `fin_obl_alert_log` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT UNSIGNED NOT NULL,
  `alert_code`   VARCHAR(6)   NOT NULL,
  `obligation_id` BIGINT UNSIGNED NULL,
  `schedule_id`  BIGINT UNSIGNED NULL,
  `subject_ref`  VARCHAR(120) NOT NULL DEFAULT '',
  `to_user_id`   INT UNSIGNED NULL,
  `to_role_id`   INT UNSIGNED NULL,
  `work_item_id` BIGINT UNSIGNED NULL,
  `fired_at`     DATETIME     NOT NULL,
  `due_at`       DATETIME     NULL COMMENT 'مهلةُ التصرفِ — بعدها يُصعَّد للمخاطر',
  `state`        ENUM('open','acted','escalated','closed') NOT NULL DEFAULT 'open',
  `escalated_at` DATETIME     NULL,
  `created_by`   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fire` (`company_id`, `alert_code`, `subject_ref`),
  KEY `ix_state` (`company_id`, `state`, `due_at`),
  KEY `ix_obl` (`obligation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-OBL-01 §4-22 — سجلُّ التنبيهاتِ وتصعيدِ المُهمَل';
