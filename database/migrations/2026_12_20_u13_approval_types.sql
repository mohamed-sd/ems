-- update0013 · البند ② — أنواعُ الاعتمادِ الأربعةُ وسقوفُها
-- ═══════════════════════════════════════════════════════════════════════════
-- المصدر: FIN-ACC-01 §٤-٧ (FACC-0040..FACC-0044) · PROP-01 §٤-١ البند ١
--         · FIN-MGR-01 FMGR-0004 · PROP-01 CEO-Y0120
--
-- الحكمُ الحاكم — FACC-0044:
--   «لا يُعتبر أيٌّ منها بديلًا عن الآخر · ◆ ولا يُجمع اثنان في شخصٍ واحدٍ
--    حيث يتعارضان.»
-- وشاهدُ القبول — PROP-01 §٧-٢ البند ٣: «صفرُ طلبٍ يُنفَّذ باعتمادٍ واحدٍ من
--   الأربعة.»
--
-- ◆ لماذا محورٌ جديدٌ ولا يُوسَّع `fin_approvals.level`:
--   العمودُ القائمُ `level` يخلط **موضعَ المعتمِد** (محاسبُ إدارةٍ · مديرُ إدارةٍ ·
--   مديرٌ ماليٌّ …) بـ**نوعِ الاعتماد**. والوثيقةُ تفصلهما: المديرُ الماليُّ قد
--   يملك APR-3 ولا يملك APR-2 أبدًا (FMGR-0004). فالنوعُ محورٌ مستقلٌّ عن الموضع،
--   ودمجُهما هو بعينِه ما جاءت الوثيقةُ لتنقضه. والجدولُ القديمُ يبقى عاملًا.
--
-- idempotent: CREATE TABLE IF NOT EXISTS.

-- ═══ ① الأنواعُ الأربعة (APR-1..APR-4) ═══════════════════════════════════
CREATE TABLE IF NOT EXISTS `fin_approval_types` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = مرجعٌ عامٌّ لكلِّ الكيانات',
  `code`          VARCHAR(8)   NOT NULL COMMENT 'APR-1..APR-4',
  `seq`           TINYINT UNSIGNED NOT NULL COMMENT 'ترتيبُ السلسلةِ — ولا يُقفز',
  `title`         VARCHAR(120) NOT NULL,
  `owner_label`   VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'صاحبُه كما تسميه الوثيقة',
  `question`      VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'السؤالُ الذي يجيبه',
  `rule_text`     VARCHAR(400) NOT NULL DEFAULT '',
  `allowed_roles` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'أدوارٌ مفصولةٌ بفاصلة — فارغٌ = بلا قيدِ دور',
  `needs_cap`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'أيشترط سقفًا ماليًّا؟ (APR-3 وحدَه)',
  `doc_ref`       VARCHAR(24)  NOT NULL DEFAULT '',
  `active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_apr` (`company_id`, `code`),
  KEY `ix_seq` (`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-ACC-01 §4-7 — أنواعُ الاعتمادِ الأربعةُ ولا يُغني أحدُها عن الآخر';

-- ═══ ② الأزواجُ المتعارضة — «ولا يُجمع اثنان في شخصٍ واحدٍ حيث يتعارضان» ══
CREATE TABLE IF NOT EXISTS `fin_approval_conflicts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `apr_a`      VARCHAR(8)   NOT NULL,
  `apr_b`      VARCHAR(8)   NOT NULL,
  `rule_text`  VARCHAR(400) NOT NULL DEFAULT '',
  `doc_ref`    VARCHAR(24)  NOT NULL DEFAULT '',
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pair` (`company_id`, `apr_a`, `apr_b`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-ACC-01 FACC-0044 — أزواجُ الاعتمادِ التي لا تُجمع في شخصٍ واحد';

-- ═══ ③ سقوفُ السلطة — APR-3 لا يُمنح بلا سقفٍ معلَن ═══════════════════════
-- CEO-Y0120: «ويصل الرئيسَ كلُّ اعتمادٍ ماليٍّ تجاوز سقفَ المديرِ الماليِّ
--   والنائبِ المختصِّ · ولا يُنفَّذ قبلَ قرارِه.»
CREATE TABLE IF NOT EXISTS `fin_authority_caps` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT UNSIGNED NOT NULL,
  `scope_kind`     ENUM('role','user','dept') NOT NULL DEFAULT 'role',
  `scope_ref`      VARCHAR(80)  NOT NULL COMMENT 'رقمُ الدورِ أو المستخدمِ أو اسمُ الإدارة',
  `apr_code`       VARCHAR(8)   NOT NULL DEFAULT 'APR-3',
  `max_amount`     DECIMAL(18,2) NOT NULL,
  `currency`       VARCHAR(8)   NOT NULL DEFAULT 'USD',
  `escalates_to_role` INT UNSIGNED NULL COMMENT 'من يقرر فوقَ السقف — وأعلاها الرئيسُ التنفيذي',
  `effective_from` DATE         NULL,
  `effective_to`   DATE         NULL,
  `authority_ref`  VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'مرجعُ التفويضِ الموثَّق',
  `active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`     INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cap` (`company_id`, `scope_kind`, `scope_ref`, `apr_code`),
  KEY `ix_live` (`company_id`, `apr_code`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-ACC-01 APR-3 + PROP-01 CEO-Y0120 — سقوفُ سلطةِ الالتزامِ والدفع';

-- ═══ ④ سلسلةُ الاعتمادِ الحيةُ لكلِّ مستند ═══════════════════════════════
-- صفٌّ لكلِّ نوعٍ على كلِّ مستند — والمفتاحُ الفريدُ يمنع تكرارَ النوعِ الواحد.
-- ◆ وهذا الجدولُ هو الشاهدُ على «صفرُ طلبٍ يُنفَّذ باعتمادٍ واحدٍ من الأربعة».
CREATE TABLE IF NOT EXISTS `fin_approval_chain` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT UNSIGNED NOT NULL,
  `source_kind`     VARCHAR(40)  NOT NULL,
  `source_ref`      VARCHAR(120) NOT NULL,
  `apr_code`        VARCHAR(8)   NOT NULL,
  `decision`        ENUM('approved','rejected','escalated') NOT NULL,
  `actor_user_id`   INT UNSIGNED NOT NULL,
  `actor_role_id`   INT UNSIGNED NULL,
  `actor_capacity`  VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'الصفةُ التي اعتُمد بها',
  `amount`          DECIMAL(18,2) NULL,
  `currency`        VARCHAR(8)   NOT NULL DEFAULT 'USD',
  `cap_at_decision` DECIMAL(18,2) NULL COMMENT 'السقفُ النافذُ لحظةَ القرار — يُجمَّد ولا يُقرأ لاحقًا',
  `reason_code`     VARCHAR(60)  NOT NULL DEFAULT '' COMMENT 'عند الرفضِ — رمزٌ محكوم (BR-03)',
  `note`            VARCHAR(400) NOT NULL DEFAULT '',
  `decided_at`      DATETIME     NOT NULL,
  `created_by`      INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doc_type` (`company_id`, `source_kind`, `source_ref`, `apr_code`),
  KEY `ix_doc` (`company_id`, `source_kind`, `source_ref`),
  KEY `ix_actor` (`actor_user_id`),
  KEY `ix_when` (`company_id`, `decided_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIN-ACC-01 §4-7 — سلسلةُ الاعتمادِ الحيةُ بأنواعِها الأربعة';
