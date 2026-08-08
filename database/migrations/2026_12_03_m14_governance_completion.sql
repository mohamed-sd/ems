-- update0012 · م3 — M-14 الحوكمة والالتزام: جداولُ الأفعالِ المعلَنةِ غيرِ المبنية
-- ═══════════════════════════════════════════════════════════════════════════
-- المرجع: M-14 — الحوكمة والالتزام v1 (docs/update0012) — 33 شاشةً و41 فعلًا.
-- الأفعالُ المبنيةُ (bound_page: deleg.grant · glass.break · found.close ·
-- sm.define · name.merge ...) لا تُمسّ؛ وهذه الهجرةُ تبني جداولَ الأربعةِ
-- المعلَنةِ غيرِ المبنية:
--   approval.reject / approval.return → gov_approval_decisions (القرارُ يمرُّ
--       بخدمة مصدره — وهذا سجلُّ القرارِ بأسبابه المحكومة «approvals · reasons»)
--   denial.review → gov_denial_reviews («المنعُ المتكرر يكشف: إما حاجةً
--       لاستثناءٍ أو خطأً في تصنيف الحماية أو محاولةَ تجاوز» §7-2)
--   org.change → org_structure_versions («الوحداتُ والمسمياتُ تتغير بقرارٍ
--       مرجعيٍّ · والتكليفاتُ القائمةُ تُراجَع» — والعكسُ رجوعٌ لنسخةٍ بقرار)
--
-- عدمُ الرجعية §9-4: append-only بالخدمة — التصحيحُ صفٌّ جديدٌ بمرجعه.
-- النمط الحارس: CREATE IF NOT EXISTS — idempotent.

-- ═══ ① gov_approval_decisions — قراراتُ الصندوق الموحَّد بأسبابها المحكومة ═══
-- «المستندُ يعود لمُنشئه بسببٍ من قائمةٍ محكومة — ويُقاس السببُ في تحليل
--  الاختناقات» (approval.reject) · «يعود قابلًا للتعديل والمهلةُ تتوقف حتى
--  إعادة الرفع» (approval.return — بلا عكسٍ بطبيعته: إعادةُ الرفع دورةٌ جديدة).
CREATE TABLE IF NOT EXISTS `gov_approval_decisions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `decision_code` VARCHAR(16) NOT NULL COMMENT 'APD-000001',
  `source_kind` ENUM('fin_request','supplier_settlement','journal_entry','period_close','other') NOT NULL
      COMMENT 'الصندوقُ الموحَّد يجمع من مصادرَ أربعة — والقرارُ بخدمة مصدره',
  `source_ref` VARCHAR(64) NOT NULL COMMENT 'مرجعُ المستند في مصدره',
  `decision` ENUM('rejected','returned','withdrawn_decision') NOT NULL
      COMMENT 'rejected: رفضٌ بسبب · returned: إعادةٌ للتصحيح',
  `reason_code` VARCHAR(32) NOT NULL DEFAULT ''
      COMMENT 'السببُ المحكوم: RSN-BUDGET · RSN-DOCS · RSN-AUTH · RSN-DUP · RSN-DATA · RSN-OTHER',
  `reason_note` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'بيانُ السبب — إلزاميٌّ مع RSN-OTHER',
  `ring_no` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'الحلقةُ في السلسلة عند القرار',
  `decided_by` INT NOT NULL COMMENT 'المعتمِدُ صاحبُ القرار',
  `decided_capacity` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'صفتُه من المسمى الحي — لا الاسم',
  `authority_ref` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '§9-1 مرجعُ التفويض',
  `parent_ref` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'المرجعُ الأب — المستندُ المقرَّرُ فيه',
  `event_id` INT NULL COMMENT 'ApprovalRejected/ApprovalReturned في الممر المحايد',
  `state` ENUM('effective','superseded') NOT NULL DEFAULT 'effective',
  `superseded_by_ref` VARCHAR(16) NOT NULL DEFAULT '' COMMENT 'إعادةُ رفعٍ بعد التصحيح — دورةٌ جديدة',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `idempotency_key` VARCHAR(96) NOT NULL COMMENT '(المصدرُ×المرجعُ×القرارُ×الحلقة)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_apd_code` (`company_id`, `decision_code`),
  UNIQUE KEY `uq_apd_idem` (`company_id`, `idempotency_key`),
  KEY `ix_apd_source` (`company_id`, `source_kind`, `source_ref`),
  KEY `ix_apd_reason` (`company_id`, `reason_code`) COMMENT 'السببُ يُقاس في تحليل الاختناقات'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-14 approval.reject/return: القرارُ بسببٍ محكومٍ يُقاس — وسجلُّه لا يُعدَّل';

-- ═══ ② gov_denial_reviews — مراجعةُ المحاولات الممنوعة (denial.review) ═══════
-- «المنعُ المتكرر يكشف: إما حاجةً لاستثناءٍ أو خطأً في تصنيف الحماية أو
--  محاولةَ تجاوز» — والعكسُ إغلاقُ المراجعة بقرار.
CREATE TABLE IF NOT EXISTS `gov_denial_reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `review_code` VARCHAR(16) NOT NULL COMMENT 'DNR-000001',
  `denial_id` INT NOT NULL COMMENT 'guard_denials.deny_id — المحاولةُ المرصودة',
  `guard_code` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'رمزُ الحارس الذي منع',
  `classification` ENUM('يحتاج استثناءً','خطأ تصنيف حماية','محاولة تجاوز','عابر — لا إجراء') NOT NULL
      COMMENT 'التصنيفُ الثلاثي + العابر — §7-2 denial.review',
  `decision_note` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'قرارُ المراجعة وتسبيبه',
  `follow_up_ref` VARCHAR(64) NOT NULL DEFAULT ''
      COMMENT 'الأثرُ التالي: رقمُ طلب استثناءٍ أو تصحيحِ تصنيفٍ أو بلاغ',
  `state` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `reviewed_by` INT NOT NULL COMMENT '§9-1 المُنشئ — المراجِع',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_by` INT NULL,
  `closed_at` DATETIME NULL,
  `authority_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `parent_ref` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'المرجعُ الأب — رقمُ المحاولة',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dnr_code` (`company_id`, `review_code`),
  UNIQUE KEY `uq_dnr_denial` (`company_id`, `denial_id`) COMMENT 'مراجعةٌ واحدةٌ للمحاولة — والتحديثُ عليها',
  KEY `ix_dnr_state` (`company_id`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-14 denial.review: المنعُ المتكرر يُراجَع ويُصنَّف — لا يُترك صامتًا';

-- ═══ ③ org_structure_versions — نسخُ الهيكل التنظيمي (org.change) ═══════════
-- «الوحداتُ والمسمياتُ تتغير بقرارٍ مرجعيٍّ · والتكليفاتُ القائمةُ تُراجَع ·
--  والقوالبُ تُعاد اشتقاقها» — والعكسُ رجوعٌ لنسخةٍ سابقةٍ بقرار.
CREATE TABLE IF NOT EXISTS `org_structure_versions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `version_code` VARCHAR(16) NOT NULL COMMENT 'ORG-000001',
  `change_kind` ENUM('إنشاء وحدة','تعديل وحدة','تعطيل وحدة','نقل تبعية','تعديل مسمى','رجوع لنسخة') NOT NULL,
  `unit_id` INT NULL COMMENT 'org_units.unit_id المتأثرة',
  `decision_ref` VARCHAR(64) NOT NULL COMMENT 'قرارُ الإنشاء أو التعديل — مرجعيٌّ إلزامي',
  `effective_date` DATE NOT NULL,
  `snapshot_json` MEDIUMTEXT NOT NULL COMMENT 'لقطةُ الهيكل قبل التغيير — أساسُ الرجوع',
  `change_json` TEXT NULL COMMENT 'ما تغيّر بالضبط — قبلَ وبعد',
  `assignments_review_note` VARCHAR(255) NOT NULL DEFAULT ''
      COMMENT 'التكليفاتُ القائمةُ تُراجَع — نتيجةُ المراجعة',
  `state` ENUM('applied','reverted') NOT NULL DEFAULT 'applied',
  `reverted_by_ref` VARCHAR(16) NOT NULL DEFAULT '' COMMENT 'العكس: نسخةُ الرجوع التي نقضتها',
  `changed_by` INT NOT NULL COMMENT '§9-1 المُنشئ',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT NULL COMMENT '§9-1 المعتمِد',
  `approved_at` DATETIME NULL,
  `authority_ref` VARCHAR(120) NOT NULL DEFAULT '',
  `parent_ref` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'النسخةُ السابقة في السلسلة',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orgv_code` (`company_id`, `version_code`),
  KEY `ix_orgv_unit` (`company_id`, `unit_id`, `effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-14 org.change: كلُّ تغييرِ هيكلٍ نسخةٌ بلقطتها وقرارها — والرجوعُ بقرارٍ لا محوًا';
