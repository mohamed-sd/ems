-- ═══════════════════════════════════════════════════════════════════════════
-- M-01 · دفترُ الدفعة المقدَّمة — 2026-07-29 (اسمُ الملف 07_31 لتسلسلٍ أبجديٍّ بعد 07_30)
-- ───────────────────────────────────────────────────────────────────────────
-- **الخللُ الذي يوقفه** (مقيسٌ على القاعدة الحيّة): كان
-- `claim_helpers.php` يخصم `advance_recovery_pct` من أساس كل فترة **بلا رصيدٍ
-- ولا سقفٍ ولا قبضٍ ابتدائي** — فالخصمُ يتكرر **إلى الأبد** حتى بعد تصفية الدفعة.
-- والعقد 5 نسبتُه 10٪ و**خُصم منه 12.50 فعلًا**، و**صفرُ سجلٍّ لقبض أيِّ دفعةٍ
-- مقدَّمةٍ في النظام كلِّه**. أي أن النظامَ كان يسترد دَينًا لم يُقرضه.
--
-- ── الدفعةُ المقدَّمة سلفةٌ تُستردّ لا إيرادٌ يُعترف به ────────────────────
-- فقبضُها **يغيّر متى يُقبض لا ما كُسب** ⇒ `publishFact` بلا إسقاطٍ في الدفتر
-- (القاعدة ③). ولو نُشر لها قيدُ إيرادٍ لتضخّم الربحُ بمقدارها — وهو الفخُّ
-- نفسُه الذي كلّف عطبَ ردّ الضمان (إيرادُ العقد 5: 124.95 ← 131.20).
--
-- ── ولماذا لا جدولَ استهلاكٍ ثالثًا ───────────────────────────────────────
-- الاستهلاكُ **مسجَّلٌ سلفًا** في `claim_lines` بـ`source_kind='advance_recovery'`
-- (مبالغُ سالبة) — تمامًا كالاحتجاز في `retention`/`retention_release`. فالرصيدُ
-- = Σ المقبوض − Σ المستهلَك، بصيغةٍ واحدةٍ على مصدرين قائمين. وجدولٌ ثالثٌ
-- يكرّر ما هو مسجَّلٌ = مصدرانِ للرقم نفسِه = انفصامٌ مؤجَّل (المبدأ ④).
--
-- ── الحالةُ الافتراضيةُ توقف النزيف فورًا ─────────────────────────────────
-- عقدٌ بلا قبضٍ مسجَّل ⇒ رصيدُه **صفر** ⇒ **لا بندَ استهلاكٍ يُولَّد** — فالخللُ
-- يتوقف من اللحظة الأولى بلا انتظار قرار، والـ12.50 المخصومةُ سلفًا **لا تُمسّ**
-- (قاعدةُ «لا مسحَ ولا تعديلَ لقيدٍ مرحَّل») بل تُرصد في تقرير مطابقةٍ يُقفل بقرار.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `contract_advances` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `contract_id` INT UNSIGNED NOT NULL,
  `advance_no` VARCHAR(32) NOT NULL COMMENT 'ADV-سنة-تسلسل — ترقيمٌ خادميٌّ لكل شركة',

  `amount` DECIMAL(18,2) NOT NULL COMMENT 'المقبوضُ فعلًا — موجبٌ دائمًا. لا يُشتق من نسبةٍ ولا يُقدَّر (قاعدةُ عدم التلفيق)',
  `currency` VARCHAR(16) NOT NULL,
  `received_date` DATE NOT NULL COMMENT 'تاريخُ القبض الفعلي',
  `doc_ref` VARCHAR(120) NOT NULL COMMENT 'مرجعُ سند القبض — إلزام: لا سلفةَ بلا مستند',
  `note` VARCHAR(255) DEFAULT NULL,

  `state` ENUM('recorded','cancelled') NOT NULL DEFAULT 'recorded'
    COMMENT 'القبضُ واقعةٌ لا دورةُ اعتماد — والإلغاءُ حالةٌ لا حذف',
  `event_id` INT DEFAULT NULL COMMENT 'حقيقةُ القبض في الجذر المحايد (publishFact — لا قيدَ إيراد)',

  `recorded_by` INT UNSIGNED DEFAULT NULL,
  `recorded_at` DATETIME DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME DEFAULT NULL,
  `deleted_by` INT UNSIGNED DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_advance_no` (`company_id`, `advance_no`),
  -- العطالة: سندُ قبضٍ واحدٌ لا يُسجَّل مرتين على العقد نفسِه
  UNIQUE KEY `uq_advance_doc` (`company_id`, `contract_id`, `doc_ref`),
  KEY `ix_contract` (`company_id`, `contract_id`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='M-01 — دفعاتٌ مقدَّمةٌ مقبوضةٌ فعلًا؛ الاستهلاكُ في claim_lines';
