-- ═══════════════════════════════════════════════════════════════════════════
-- update0005 · الموجة ② · CAP-07/08/09/10 — الدفترُ والتغطيةُ البديلة
-- الجداولُ الأربعةُ التي تُبنى بأسمائها حرفيًّا (DEC-CAP-C) — لا مقابلَ لها:
--   capacity_consumption_ledger   — دفترُ استهلاك القدرات (§13.2) Insert-only
--   capacity_financial_event_links — روابطُ الحدث المالي Append-only (§13.2)
--   substitute_coverages          — سلّمُ التغطية البديلة (§6/§16)
--   coverage_settlement_lines     — بنودُ تسوية التغطية بالأطراف الأربعة (§7)
-- المفتاحُ الحاكم (§3.1 من البرومت): سجلُّ الوحدة مرقَّمُ النسخ هو unit_entries —
--   UQ(unit_record_id=entry_id, unit_record_version=revision_no,
--      effect_type, effect_target_type, effect_target_ref) يمنع الخصمَ مرتين.
-- الحصانةُ بنمط immutable_key القائم في TenantRegistry — لا triggers (عُرف المستودع).
-- idempotent: كلُّ CREATE بـIF NOT EXISTS.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── CAP-07 · دفترُ استهلاك القدرات — أسطرٌ لا رصيدٌ يُعدَّل ─────────────────
CREATE TABLE IF NOT EXISTS capacity_consumption_ledger (
  led_id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id                INT NOT NULL,
  unit_record_id            INT NOT NULL COMMENT 'سجلُّ الوحدة القانوني — unit_entries.id (§13.2)',
  unit_record_version       SMALLINT NOT NULL COMMENT 'النسخة — unit_entries.revision_no؛ التصحيحُ نسخةٌ جديدةٌ بأسطرها',
  contract_obligation_id    INT UNSIGNED NULL COMMENT 'التزامُ نوع المعدة — contract_commitments.id (الهجين DEC-CAP-C)',
  supplier_share_id         INT UNSIGNED NULL COMMENT 'حصةُ المورد — op_containers.id درجة «مورد»',
  contract_seat_id          INT UNSIGNED NULL COMMENT 'المقعدُ التعاقدي — op_containers.id درجة «معدة» بseat_no',
  equipment_assignment_id   INT UNSIGNED NULL COMMENT 'فترةُ إسناد المعدة — seat_assignments.id',
  supplier_contract_line_id INT NULL COMMENT 'بندُ عقد المورد الذي يُحتسب به — supplier_contract_lines.id',
  operator_assignment_id    INT UNSIGNED NULL COMMENT 'تكليفُ المشغّل — unit_party_awards.id',
  coverage_id               BIGINT UNSIGNED NULL COMMENT 'إن كانت تغطيةً بديلة — substitute_coverages.cov_id (§12.1-⑦)',
  effect_target_type        ENUM('client','supplier','operator') NOT NULL COMMENT 'طرفُ الأثر (§13.2)',
  effect_target_ref         VARCHAR(60) NOT NULL COMMENT 'مرجعُ الطرف — لا يكون فارغًا فالمفتاحُ عليه',
  measure_code              ENUM('hour','ton','trip','meter') NOT NULL COMMENT 'المقياس — فلا يُخصم الطنُّ من حصة ساعات (C30)',
  qty                       DECIMAL(18,3) NOT NULL COMMENT 'الكميةُ بمقياسها — موجبةٌ دائمًا والعكسُ بسطرِ effect_type=reversal',
  operational_hours         DECIMAL(18,3) NULL COMMENT 'زمنُ التشغيل مستقلًّا — للجاهزية والتكلفة في عقود الكمية (C30)',
  analytical_output_qty     DECIMAL(18,3) NULL COMMENT 'الإنتاجُ التحليليُّ مستقلًّا',
  effect_type               ENUM('client_obligation','supplier_share','operator_entitlement','exceptional_coverage','reversal') NOT NULL,
  role_snapshot             ENUM('primary','standby') NULL COMMENT 'دورُ المعدة لحظةَ الواقعة — لقطةٌ لا إحالة (§12.1-⑥)',
  unit_decision_snapshot_id INT UNSIGNED NULL COMMENT 'سلسلةُ القرارات كاملةً — unit_approvals سلسلة round_no للنسخة',
  period                    CHAR(7) NOT NULL COMMENT 'YYYY-MM — فترةُ الاستهلاك',
  reverses_led_id           BIGINT UNSIGNED NULL COMMENT 'مرجعُ السطر المعكوس — والأصلُ باقٍ (C26)',
  created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by                INT NULL,
  PRIMARY KEY (led_id),
  UNIQUE KEY uq_ledger_no_double (unit_record_id, unit_record_version, effect_type, effect_target_type, effect_target_ref),
  KEY ix_led_share_period (supplier_share_id, period),
  KEY ix_led_obl_period (contract_obligation_id, period),
  KEY ix_led_company_period (company_id, period),
  KEY ix_led_coverage (coverage_id),
  KEY ix_led_reverses (reverses_led_id),
  CONSTRAINT fk_led_reverses FOREIGN KEY (reverses_led_id) REFERENCES capacity_consumption_ledger (led_id),
  CONSTRAINT ck_led_qty_positive CHECK (qty >= 0),
  CONSTRAINT ck_led_reversal_ref CHECK (
    (effect_type = 'reversal' AND reverses_led_id IS NOT NULL)
    OR (effect_type <> 'reversal' AND reverses_led_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CAP-01 §13 — دفترُ استهلاك القدرات: سجلٌّ قانونيٌّ Insert-only؛ الرصيدُ نتيجةٌ لا مصدر؛ المفتاحُ يمنع الخصمَ مرتين';

-- ── CAP-08 · روابطُ الحدث المالي — Append-only ──────────────────────────────
-- الحدثُ الماليُّ يُنشأ بعد COMMIT والنشر، فلا يُكتب مرجعُه في سطرٍ لا يُعدَّل.
CREATE TABLE IF NOT EXISTS capacity_financial_event_links (
  lnk_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id   INT NOT NULL,
  led_id       BIGINT UNSIGNED NOT NULL COMMENT 'سطرُ الدفتر',
  fin_event_id INT NOT NULL COMMENT 'fin_financial_events.id — الحدثُ الماليُّ المولَّد بعد النشر',
  journal_ref  VARCHAR(60) NULL COMMENT 'مرجعُ القيد إن رُحِّل',
  linked_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (lnk_id),
  UNIQUE KEY uq_led_fin (led_id, fin_event_id),
  KEY ix_lnk_fin (fin_event_id),
  CONSTRAINT fk_lnk_led FOREIGN KEY (led_id) REFERENCES capacity_consumption_ledger (led_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CAP-01 §13.2 — جدولُ ربطٍ Append-only بين سطر الدفتر والحدث المالي؛ UQ(led,fin) يمنع الربطَ مرتين';

-- ── CAP-09 · التغطياتُ البديلة — سلّمُ §6 بدرجاته ──────────────────────────
CREATE TABLE IF NOT EXISTS substitute_coverages (
  cov_id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id           INT NOT NULL,
  level                ENUM('own_standby','cross_supplier','source_change') NOT NULL
                       COMMENT 'الدرجة: احتياطيُّ المورد نفسِه · تغطيةُ موردٍ آخر · تبديلُ مصدر التوريد (§6)',
  covered_seat_id      INT UNSIGNED NOT NULL COMMENT 'المقعدُ المغطى — op_containers.id (والموردُ المتعطل من شجرته)',
  covering_supplier_id INT NOT NULL COMMENT 'الموردُ المغطِّي — suppliers.id (في own_standby هو المتعطلُ نفسُه)',
  covering_equipment_id INT NULL COMMENT 'المعدةُ البديلة إن عُيّنت',
  reason_code          ENUM('breakdown','scheduled_maintenance','relocation_exit','document_expired','operator_shortage') NOT NULL
                       COMMENT '§6.1-①: سببٌ من قائمةٍ محكومة — لا تغطيةَ بلا سبب',
  reason_ref           VARCHAR(60) NULL COMMENT 'مرجعُ بلاغٍ أو أمرِ عملٍ حيث ينطبق',
  valid_from           DATE NOT NULL,
  valid_to             DATE NOT NULL COMMENT '§6.1-②: إلزاميٌّ — لا تغطيةَ مفتوحةَ المدة؛ والتمديدُ قرارٌ جديد',
  estimated_hours      DECIMAL(10,2) NULL COMMENT '§6.1-⑤: الأثرُ يُحسب قبل الاعتماد ويُعرض على الموافقين',
  approvals_ref        VARCHAR(120) NULL COMMENT 'مرجعُ سلسلة الموافقات بدرجتها',
  state                ENUM('draft','pending_approvals','approved','active','ended','rejected') NOT NULL DEFAULT 'draft',
  note                 VARCHAR(255) NULL,
  created_by           INT NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (cov_id),
  KEY ix_cov_seat (company_id, covered_seat_id, valid_from),
  KEY ix_cov_supplier (company_id, covering_supplier_id, state),
  CONSTRAINT fk_cov_seat FOREIGN KEY (covered_seat_id) REFERENCES op_containers (id) ON DELETE RESTRICT,
  CONSTRAINT ck_cov_dates CHECK (valid_to >= valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CAP-01 §6 — التغطيةُ البديلةُ بدرجاتها: سببٌ محكومٌ ومدةٌ مغلقةٌ وموافقاتٌ بالدرجة؛ ولا تُعدَّل الحصةُ الأصلية';

-- ── CAP-10 · بنودُ تسوية التغطية — الأطرافُ الأربعة (§7) ────────────────────
CREATE TABLE IF NOT EXISTS coverage_settlement_lines (
  ln_id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id     INT NOT NULL,
  cov_id         BIGINT UNSIGNED NOT NULL,
  party          ENUM('client','failed_supplier','covering_supplier','operator') NOT NULL COMMENT 'الطرف (§7)',
  effect         ENUM('billable','gap_kept','exceptional_line','entitlement') NOT NULL
                 COMMENT 'billable=يُفوتر كاملًا · gap_kept=العجزُ باقٍ بجزائه · exceptional_line=بندُ تغطيةٍ مستقلٌّ بسعره · entitlement=استحقاقُ المشغّل بعقده',
  qty            DECIMAL(18,3) NOT NULL DEFAULT 0,
  measure_code   ENUM('hour','ton','trip','meter') NULL,
  amount         DECIMAL(18,2) NULL COMMENT 'القيمةُ إن سُعِّرت — بسعرِ التغطية المتفق لا بحصةٍ تُرفع',
  currency       VARCHAR(8) NULL,
  settlement_ref VARCHAR(60) NULL COMMENT 'مرجعُ التسوية التي قُرئ فيها البند',
  note           VARCHAR(200) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ln_id),
  KEY ix_csl_cov (cov_id, party),
  KEY ix_csl_company (company_id, settlement_ref),
  CONSTRAINT fk_csl_cov FOREIGN KEY (cov_id) REFERENCES substitute_coverages (cov_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CAP-01 §7 — محاسبةُ التغطية: بندٌ ظاهرٌ باسمه ومرجعِه لكل طرفٍ — لا سطرٌ مدموج';
