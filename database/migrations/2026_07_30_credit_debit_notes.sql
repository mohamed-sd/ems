-- ═══════════════════════════════════════════════════════════════════════════
-- M-02 · الإشعارُ الدائن/المدين — 2026-07-29 (اسمُ الملف 07_30 لتسلسلٍ أبجديٍّ بعد 07_29)
-- ───────────────────────────────────────────────────────────────────────────
-- **الفجوةُ التي يسدّها**: تصحيحُ مستخلصٍ مفوترٍ كان **مستحيلًا نظاميًّا** — لا
-- سبيلَ إلا العبثُ بالبيانات، وهو ما تمنعه القاعدةُ الأولى («لا مسحَ ولا تعديلَ
-- لقيدٍ مرحَّل؛ التصحيحُ بحركةٍ عاكسةٍ موثَّقةٍ بمرجعها»).
--
-- **الفاتورةُ الصادرة لا تُعدَّل** — تُصحَّح بإشعارٍ دائنٍ أو مدين. فهذا الجدولُ
-- **لا يمسّ `claims` ولا `claim_lines` أبدًا**: يشير إليهما ويتحرك بجانبهما.
--
-- ── الاتجاهان بمعناهما المحاسبي ───────────────────────────────────────────
--   `credit` (دائن)  → **يُنقص** ذمّةَ العميل: بولغ في الفوترة أو رُدّ عملٌ.
--   `debit`  (مدين)  → **يزيدها**: نقصت الفاتورةُ عن المستحق.
-- والمبلغُ **موجبٌ دائمًا** والاتجاهُ يحمل الإشارة — فلا سالبٌ في عمودٍ يُجمع.
--
-- ── ولماذا لا قيدَ إيرادٍ جديدًا (القاعدة ③) ───────────────────────────────
-- «المروحةُ تعترف والمستخلصُ يفوتر» (قرارُ المالك 2026-07-27). فالإشعارُ يغيّر
-- **متى يُقبض ومقدارَ ما يُطالَب به** لا **ما كُسب** ⇒ `publishFact` بلا إسقاطٍ
-- في الدفتر، وأثرُه على `fin_receivables` وحدَها. وتصحيحُ **ما كُسب** له مسارُه
-- الآخرُ القائم (`attribution.reversed`) — مساران لسؤالين، وخلطُهما يضاعف الإيراد.
--
-- ── فصلُ اليدين ───────────────────────────────────────────────────────────
-- `prepared_by` ≠ `approved_by` بنيويًّا في الخدمة (نظيرُ `SettlementService`
-- و`claim_approve`). والسببُ والمستندُ إلزامان: إشعارٌ يحرّك ذمّةً بلا سببٍ
-- مكتوبٍ ومستندٍ لا يُدقَّق بعد سنتين.
--
-- ── العطالة ───────────────────────────────────────────────────────────────
-- `uq_note_no` يمنع رقمًا مكررًا، و`uq_note_idem` يمنع **إصدارَ الإشعار نفسِه
-- مرتين** لنفس (المستخلص × السطر × الاتجاه × مفتاح العطالة) — والمفتاحُ يوفّره
-- المنادي، فالطلبُ المكرَّر يُرجع القائمَ ولا يفتح ذمّةً ثانية.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `credit_debit_notes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `note_no` VARCHAR(32) NOT NULL COMMENT 'CDN-سنة-تسلسل — ترقيمٌ خادميٌّ لكل شركة',
  `note_kind` ENUM('credit','debit') NOT NULL
    COMMENT 'credit=يُنقص ذمّةَ العميل · debit=يزيدها. المبلغُ موجبٌ دائمًا والاتجاهُ يحمل الإشارة',

  -- المرجعُ إلى الأصل — ولا يُعدَّل الأصلُ أبدًا
  `claim_id` INT UNSIGNED NOT NULL COMMENT 'المستخلصُ الأصلي — مرجعٌ لا يُمسّ',
  `claim_line_id` INT UNSIGNED DEFAULT NULL COMMENT 'سطرُه بعينه إن كان الإشعارُ على سطر — NULL = على المستخلص كلِّه',
  `receivable_id` INT DEFAULT NULL COMMENT 'الذمّةُ التي يتحرك بها — تُملأ عند الإجازة',
  `invoice_no` VARCHAR(64) DEFAULT NULL COMMENT 'رقمُ الفاتورة الأصلية — نسخةٌ للقراءة',

  `currency` VARCHAR(16) NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL COMMENT 'موجبٌ دائمًا — الاتجاهُ في note_kind',

  -- السببُ والمستند — إلزامان
  `reason` VARCHAR(255) NOT NULL COMMENT 'سببُ الإشعار — إلزام',
  `doc_ref` VARCHAR(120) NOT NULL COMMENT 'مرجعُ المستند المؤيِّد — إلزام',

  `state` ENUM('draft','review','approved','cancelled') NOT NULL DEFAULT 'draft',
  `idem_key` VARCHAR(64) DEFAULT NULL COMMENT 'مفتاحُ العطالة من المنادي — يمنع إصدارَ الإشعار نفسِه مرتين',

  `prepared_by` INT UNSIGNED DEFAULT NULL,
  `submitted_by` INT UNSIGNED DEFAULT NULL,
  `submitted_at` DATETIME DEFAULT NULL,
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `event_id` INT DEFAULT NULL COMMENT 'حقيقةُ الإشعار في الجذر المحايد',
  `version` INT NOT NULL DEFAULT 1 COMMENT 'قفلٌ تفاؤليّ — نظيرُ claims.version',

  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME DEFAULT NULL,
  `deleted_by` INT UNSIGNED DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_note_no` (`company_id`, `note_no`),
  UNIQUE KEY `uq_note_idem` (`company_id`, `claim_id`, `note_kind`, `idem_key`),
  KEY `ix_claim` (`company_id`, `claim_id`),
  KEY `ix_state` (`company_id`, `state`),
  KEY `ix_receivable` (`company_id`, `receivable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-02 — إشعاراتٌ دائنة/مدينة تصحّح فاتورةً صادرةً بلا أن تمسّها';
