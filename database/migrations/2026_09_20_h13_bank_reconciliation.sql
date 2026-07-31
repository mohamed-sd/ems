-- ═══════════════════════════════════════════════════════════════════════════
-- H-13 · المطابقةُ البنكية — 2026-07-31
-- البطاقة: docs/specs/H-13_bank_reconciliation.md
-- المصدر: SPEC-01 #19: «جداولُ 15.2-ب الجديدة — **استيرادٌ Idempotent بمفتاح
--         السطر** · **المضاهاةُ الآلية بقاعدتها** · **قرارُ الفرق يولّد قيدَ
--         تسويةٍ بمرجعه** · **فحصُ الإقفال يمنع وفرقٌ مفتوح**» · «المضاهاةُ
--         الآلية بقواعد (**المبلغ + التاريخ ± أيامٍ + المرجع**)» · «أثر: **قيودُ
--         التسوية فقط — بمرجع الفرق**».
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: `fin_bank_statement_lines` قائمٌ بـ**11 عمودًا وصفّين** —
-- **بلا رأسِ كشفٍ** (فلا يُعرف أيُّ كشفٍ ولا رصيدُ افتتاحه وإقفاله) · **وبلا
-- مرجعٍ بنكيّ** (فلا مفتاحَ استيرادٍ عاطل) · و`matched_payment_id` **مضاهاةٌ
-- بعمودٍ واحد** لا تحمل قاعدتَها ولا فرقَها ولا قرارَها.
-- والقديمُ **يبقى كما هو** ويُوصَل بالجديد قراءةً — لا يُحذف ولا يُرحَّل قسرًا.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① رأسُ الكشف — «أيُّ كشفٍ ومن أين وإلى أين» ─────────────────────────────
CREATE TABLE IF NOT EXISTS `bank_statements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `bank_account_id` INT UNSIGNED NOT NULL,
  `statement_ref` VARCHAR(60) NOT NULL COMMENT 'مرجعُ الكشف من البنك — جزءُ مفتاح العطالة',
  `period_from` DATE NOT NULL,
  `period_to` DATE NOT NULL,
  `opening_balance` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `closing_balance` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `lines_count` INT NOT NULL DEFAULT 0,
  `state` ENUM('imported','matching','reconciled','closed') NOT NULL DEFAULT 'imported',
  `closed_at` DATETIME NULL DEFAULT NULL,
  `closed_by` INT UNSIGNED NULL DEFAULT NULL,
  `note` VARCHAR(200) NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_statement` (`company_id`, `bank_account_id`, `statement_ref`)
      COMMENT 'كشفٌ واحدٌ لمرجعه في الحساب — إعادةُ الاستيراد تُعيده لا تُكرره',
  KEY `ix_stmt_period` (`company_id`, `bank_account_id`, `period_from`, `period_to`),
  CONSTRAINT `ck_stmt_span` CHECK (`period_to` >= `period_from`),
  -- «فحصُ الإقفال يمنع وفرقٌ مفتوح» — والإقفالُ يلزمه وقتُه ومقفِلُه
  CONSTRAINT `ck_stmt_closed` CHECK (
      `state` <> 'closed' OR (`closed_at` IS NOT NULL AND `closed_by` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SPEC-01 #19 — رأسُ كشف البنك: مرجعُه ومداه ورصيداه';

-- ── ② أسطرُ الكشف — «استيرادٌ Idempotent بمفتاح السطر» ─────────────────────
CREATE TABLE IF NOT EXISTS `bank_statement_lines` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `statement_id` INT UNSIGNED NOT NULL,
  `line_no` INT NOT NULL COMMENT 'ترتيبُ السطر في الكشف كما ورد',
  `txn_date` DATE NOT NULL,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  `direction` ENUM('deposit','withdrawal') NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `running_balance` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'الرصيدُ كما ورد في الكشف',
  `bank_ref` VARCHAR(80) NOT NULL COMMENT 'المرجعُ البنكيُّ للحركة — **جزءُ مفتاح السطر**',
  `line_key` VARCHAR(64) NOT NULL
      COMMENT 'بصمةُ السطر (كشف × مرجع × تاريخ × اتجاه × مبلغ) — «Idempotent بمفتاح السطر»',
  `match_state` ENUM('unmatched','matched','difference','no_counterpart') NOT NULL DEFAULT 'unmatched',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_line_key` (`company_id`, `line_key`)
      COMMENT 'إعادةُ استيراد الملف نفسِه **لا تُنشئ سطرًا ثانيًا**',
  KEY `ix_bank_line_stmt` (`statement_id`, `line_no`),
  KEY `ix_bank_line_match` (`company_id`, `match_state`, `txn_date`),
  CONSTRAINT `fk_bank_line_stmt` FOREIGN KEY (`statement_id`)
      REFERENCES `bank_statements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_bank_line_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ المضاهاة — «سطرُ النظام ‖ سطرُ البنك ‖ حالةُ المضاهاة» ────────────────
CREATE TABLE IF NOT EXISTS `bank_recon_matches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `statement_line_id` INT UNSIGNED NOT NULL,
  `payment_id` INT NULL DEFAULT NULL COMMENT 'سطرُ النظام (fin_payments) — NULL = بلا نظير',
  `match_kind` ENUM('auto','manual','none') NOT NULL DEFAULT 'auto'
      COMMENT '«المضاهاةُ الآلية بقاعدتها» — واليدويةُ تُوسم فيُعرف من قرّر',
  `rule_note` VARCHAR(200) NULL DEFAULT NULL COMMENT 'القاعدةُ التي طابقت: مرجعٌ أو (مبلغ + تاريخ ± أيام)',
  `bank_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `system_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `difference` DECIMAL(18,2) GENERATED ALWAYS AS (ROUND(`bank_amount` - `system_amount`, 2)) STORED
      COMMENT '**مولَّدٌ لا يُكتب** — فلا ينحرف الفرقُ عن طرفيه',
  `state` ENUM('matched','open_difference','resolved','rejected') NOT NULL DEFAULT 'matched',
  `difference_reason` VARCHAR(255) NULL DEFAULT NULL COMMENT '«فتحُ فرقٍ **بسبب**»',
  `adjustment_event_id` INT NULL DEFAULT NULL COMMENT '«قيدُ تسويةٍ **بمرجع الفرق**»',
  `decided_by` INT UNSIGNED NULL DEFAULT NULL,
  `decided_at` DATETIME NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recon_line` (`statement_line_id`)
      COMMENT 'مضاهاةٌ واحدةٌ لكل سطرِ بنك — ولا سطرَ يُطابَق مرتين',
  KEY `ix_recon_payment` (`company_id`, `payment_id`),
  KEY `ix_recon_state` (`company_id`, `state`),
  CONSTRAINT `fk_recon_line` FOREIGN KEY (`statement_line_id`)
      REFERENCES `bank_statement_lines` (`id`) ON DELETE CASCADE,
  -- «فتحُ فرقٍ **بسبب**» — وفرقٌ مفتوحٌ بلا سببٍ دعوى
  CONSTRAINT `ck_recon_diff_reason` CHECK (
      `state` <> 'open_difference' OR (`difference_reason` IS NOT NULL AND `difference_reason` <> '')),
  -- والحسمُ قرارٌ يُسجَّل بحاسمه
  CONSTRAINT `ck_recon_decided` CHECK (
      `state` NOT IN ('resolved','rejected') OR `decided_by` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ④ لا شاشةَ جديدة — **الشاشةُ قائمةٌ وحدةً 103** ────────────────────────
-- گوتشا مقيسةٌ أثناء البناء: كِدتُ أسجّل «المطابقة البنكية» وحدةً 170، والقياسُ
-- كشف أن `Finance/bank_reconciliation_fin.php` **مسجَّلةٌ سلفًا وحدةً 103**
-- (الدور 17). فلا وحدةَ ثانيةً لغرضٍ واحد — والشاشةُ القائمةُ **تتوسّع**.
-- والدرسُ المتكرر: القياسُ قبل التسجيل، لا افتراضُ الغياب من غياب الجداول.
SELECT 'H-13: الشاشةُ قائمةٌ وحدةً 103 — لا تسجيلَ جديد' AS note;
