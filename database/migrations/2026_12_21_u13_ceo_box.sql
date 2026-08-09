-- update0013 · البند ③ — موافقةُ الرئيسِ على التكليفِ وصندوقُه الجديد
-- ═══════════════════════════════════════════════════════════════════════════
-- المصدر: PROP-01 §٥ (CEO-Y0119..CEO-Y0125) · §٤-١ البندان ٧ و٨
--         · FIN-ACC-01 FACC-0039 · FIN-CTRL-01 · FIN-TRE-01 · IAF-0045
--
-- الأحكامُ الحاكمة:
--   CEO-Y0119  يصل الرئيسَ تقريرُ المراجعةِ الداخليةِ **مباشرةً غيرَ مفلترٍ** —
--              ولا يمرُّ بالماليةِ ولا بالحوكمةِ ولا بمن يُراجَع.
--   CEO-Y0120  ويصله كلُّ اعتمادٍ تجاوز سقفَ المديرِ الماليِّ والنائب — ولا
--              يُنفَّذ قبلَ قرارِه.
--   CEO-Y0121  ◆ ولا يسري تكليفٌ قياديٌّ أو رقابيٌّ قبلَ موافقتِه الموثَّقة ·
--              **والموافقةُ سجلٌّ لا رسالة** · ولا يمنح التكليفُ صلاحيةً واحدةً قبلها.
--   CEO-Y0122  ويُفحص تعارضُ الواجباتِ واستقلالُ الوظيفةِ الرقابيةِ **آليًّا قبلَ
--              العرض** — والطلبُ الذي يُنشئ تعارضًا لا يُعرض حتى يُحسم.
--   CEO-Y0123  والمسائلُ المحجوزةُ تُعرض بآراءِ الماليةِ والحوكمةِ والمخاطرِ
--              والمراجعةِ — ولا تُعرض برأيٍ واحد.
--   CEO-Y0124  وقرارُه يُنفَّذ في بيتِ حقيقتِه لا في مكتبِه — **صفرُ قيدٍ مصدرُه
--              شاشاتُ مكتبِ الرئيس**.
--   CEO-Y0125  ولا يملك إغلاقَ ملاحظةِ مراجعةٍ بلا دليلٍ يقبله المراجعُ.
--
-- ◆ القائمُ لا يُكرَّر: `exec_approvals` (الاعتماداتُ العليا) و`exec_decisions`
--   (القراراتُ العليا) مبنيّان في M-00 ويبقيان مصدرَ الحقيقةِ لشاشتيهما.
--   الجديدُ هنا ثلاثةٌ لا رابعَ لها: **التكليفُ** و**تقريرُ المراجعة** و**آراءُ
--   المسألةِ المحجوزة**.
--
-- idempotent: CREATE TABLE IF NOT EXISTS + حارسُ الأعمدة.

-- ═══ ① سجلُّ موافقاتِ التكليف — «الموافقةُ سجلٌّ لا رسالة» ════════════════
CREATE TABLE IF NOT EXISTS `exec_assignments` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT UNSIGNED NOT NULL,
  `assignment_no`   VARCHAR(40)  NOT NULL,
  `subject_user_id` INT UNSIGNED NOT NULL COMMENT 'المكلَّف',
  `subject_name`    VARCHAR(160) NOT NULL DEFAULT '',
  `role_id`         INT UNSIGNED NOT NULL COMMENT 'المسمّى المكلَّفُ به',
  `role_name`       VARCHAR(120) NOT NULL DEFAULT '',
  `assignment_kind` ENUM('leadership','oversight','other') NOT NULL DEFAULT 'leadership'
                    COMMENT 'قياديٌّ أو رقابيٌّ — وما عداهما لا يحتاج موافقةَ الرئيس',
  `scope_note`      VARCHAR(300) NOT NULL DEFAULT '',
  `requested_by`    INT UNSIGNED NOT NULL,
  `requested_at`    DATETIME     NOT NULL,
  -- CEO-Y0122: الفحصُ الآليُّ قبلَ العرض — والطلبُ المتعارضُ لا يُعرض
  `conflict_state`  ENUM('clean','conflict','waived') NOT NULL DEFAULT 'clean',
  `conflict_detail` VARCHAR(600) NOT NULL DEFAULT '',
  `checked_at`      DATETIME     NULL,
  -- CEO-Y0121: السريانُ بالموافقةِ وحدَها
  `state`           ENUM('draft','blocked','presented','approved','rejected','revoked')
                    NOT NULL DEFAULT 'draft',
  `decided_by`      INT UNSIGNED NULL COMMENT 'الرئيسُ التنفيذيُّ حصرًا',
  `decided_at`      DATETIME     NULL,
  `decision_reason` VARCHAR(400) NOT NULL DEFAULT '',
  `authority_ref`   VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'مرجعُ الموافقةِ الموثَّق',
  `effective_from`  DATE         NULL,
  `effective_to`    DATE         NULL,
  `revoked_at`      DATETIME     NULL,
  `revoke_reason`   VARCHAR(400) NOT NULL DEFAULT '',
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_no` (`company_id`, `assignment_no`),
  KEY `ix_live` (`company_id`, `subject_user_id`, `role_id`, `state`),
  KEY `ix_state` (`company_id`, `state`, `requested_at`),
  KEY `ix_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PROP-01 CEO-Y0121/0122 — سجلُّ موافقاتِ التكليفِ ولا سريانَ قبلَه';

-- ═══ ② مصفوفةُ فصلِ الواجبات — البنيةُ هنا والمحتوى في البند ④ ══════════
-- CEO-Y0122 يحتاجها الآنَ ليفحص قبلَ العرض · والأزواجُ الثلاثةَ عشرَ تُبذر في ④.
CREATE TABLE IF NOT EXISTS `sec_sod_pairs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `code`       VARCHAR(12)  NOT NULL COMMENT 'SOD-01..SOD-13',
  `func_a`     VARCHAR(160) NOT NULL COMMENT 'الوظيفةُ الأولى',
  `func_b`     VARCHAR(160) NOT NULL COMMENT 'ما لا تُجمع معه',
  `roles_a`    VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'أدوارُ الوظيفةِ الأولى مفصولةً بفاصلة',
  `roles_b`    VARCHAR(120) NOT NULL DEFAULT '',
  `why`        VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'لماذا لا تُجمعان',
  `severity`   ENUM('block','warn') NOT NULL DEFAULT 'block' COMMENT 'block = قيدٌ بنيويٌّ يرفض التكليف',
  `doc_ref`    VARCHAR(24)  NOT NULL DEFAULT '',
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sod` (`company_id`, `code`),
  KEY `ix_active` (`active`, `severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PROP-01 §4-2 + FIN-ACC-01 §4-9 — أزواجُ فصلِ الواجباتِ قيدًا بنيويًّا';

-- ═══ ③ تقاريرُ المراجعةِ الداخليةِ الواصلةُ للرئيس ═══════════════════════
-- CEO-Y0119: تصل **مباشرةً غيرَ مفلترة**. والعمودُ `delivery_path` شاهدٌ يُفحص:
--   قيمتُه الوحيدةُ المقبولةُ 'direct' — وأيُّ وسيطٍ يُسجَّل فيُكشف بالمسح.
CREATE TABLE IF NOT EXISTS `exec_audit_reports` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `report_no`      VARCHAR(40)  NOT NULL,
  `title`          VARCHAR(300) NOT NULL,
  `period_label`   VARCHAR(60)  NOT NULL DEFAULT '',
  `scope_label`    VARCHAR(300) NOT NULL DEFAULT '',
  `overall_opinion` VARCHAR(300) NOT NULL DEFAULT '',
  `findings_total` INT UNSIGNED NOT NULL DEFAULT 0,
  `findings_critical` INT UNSIGNED NOT NULL DEFAULT 0,
  `closure_rate`   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `overdue_escalated` INT UNSIGNED NOT NULL DEFAULT 0,
  `issued_by`      INT UNSIGNED NOT NULL COMMENT 'المراجعُ الداخليُّ المستقل',
  `issued_at`      DATETIME     NOT NULL,
  `delivery_path`  ENUM('direct','via_finance','via_governance','via_auditee')
                   NOT NULL DEFAULT 'direct'
                   COMMENT 'CEO-Y0119 — direct وحدَها مقبولة، وما عداها خرقٌ يُكشف',
  `received_at`    DATETIME     NULL COMMENT 'وقتُ وصولِه صندوقَ الرئيس',
  `read_at`        DATETIME     NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rep` (`company_id`, `report_no`),
  KEY `ix_path` (`delivery_path`),
  KEY `ix_time` (`company_id`, `issued_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PROP-01 CEO-Y0119 — تقاريرُ المراجعةِ تصل الرئيسَ غيرَ مفلترة';

-- ═══ ④ آراءُ المسائلِ المحجوزة — «ولا تُعرض برأيٍ واحد» ══════════════════
-- CEO-Y0123. والمسألةُ نفسُها في `exec_decisions` القائمِ من M-00 — فلا يُكرَّر.
CREATE TABLE IF NOT EXISTS `exec_matter_opinions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`  INT UNSIGNED NOT NULL,
  `matter_ref`  VARCHAR(60)  NOT NULL COMMENT 'مرجعُ المسألةِ في exec_decisions',
  `opinion_of`  ENUM('finance','governance','risk','internal_audit') NOT NULL,
  `has_opinion` TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 = لا رأيَ لها في هذه المسألة',
  `opinion_text` VARCHAR(800) NOT NULL DEFAULT '',
  `given_by`    INT UNSIGNED NULL,
  `given_at`    DATETIME     NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_op` (`company_id`, `matter_ref`, `opinion_of`),
  KEY `ix_matter` (`company_id`, `matter_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PROP-01 CEO-Y0123 — آراءُ الجهاتِ الأربعِ على المسألةِ المحجوزة';
