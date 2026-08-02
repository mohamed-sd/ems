<?php
/**
 * update0005 · الموجة ① · CAP-01/02/03 — حقولُ الالتزام والاحتياطي (CAP-01 §8)
 * ───────────────────────────────────────────────────────────────────────────
 * CAP-01: حقولُ §8.1 السبعةُ على التزام نوع المعدة — contract_commitments يحمل
 *         السياسةَ كاملةً (المصدرُ الواحد · القاعدة ⑦)، وop_containers درجةُ
 *         «نوع» تحمل الأعدادَ الثلاثةَ وحدَها لأن الشجرةَ تفرض Σ لا السياسة.
 *         والمفتاح UQ(contract_id, equipment_type_code, valid_from) بفهرسٍ
 *         مشروطٍ على عمودٍ مولَّد (نمطُ active_site_mgr_key القائم — DEC-CAP-C).
 * CAP-02: حقولُ §8.2 على supplier_contract_lines + مرجعُ الالتزام
 *         contract_obligation_ref — «لا حصةَ بلا التزامٍ في عقدٍ نافذ».
 * CAP-03: خطةُ المعدات الأولية §8.3 على seat_assignments — والاحتياطيُّ صفرُ
 *         ساعاتٍ قبل التفعيل بCHECK بنيوي.
 * ملاحظة: measure_code بقيم §16 الأربع (hour·ton·trip·meter) — نمطُ
 *         supplier_contract_lines.work_model القائم لا units_of_measure
 *         (فذاك قاموسُ شركةٍ بأكوادٍ محليةٍ لا مقياسُ نظام).
 * idempotent بالكامل — الفحص عبر information_schema قبل كل DDL.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');

function col_exists($conn, $table, $col) {
    $r = $conn->query("SELECT COUNT(*) n FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = '{$table}' AND column_name = '{$col}'");
    return $r && intval($r->fetch_assoc()['n']) > 0;
}
function idx_exists($conn, $table, $idx) {
    $r = $conn->query("SELECT COUNT(*) n FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = '{$table}' AND index_name = '{$idx}'");
    return $r && intval($r->fetch_assoc()['n']) > 0;
}
function chk_exists($conn, $name) {
    $r = $conn->query("SELECT COUNT(*) n FROM information_schema.check_constraints
        WHERE constraint_schema = DATABASE() AND constraint_name = '{$name}'");
    return $r && intval($r->fetch_assoc()['n']) > 0;
}
function add_col($conn, $table, $col, $ddl) {
    if (col_exists($conn, $table, $col)) { echo "  = {$table}.{$col} قائم\n"; return; }
    if (!$conn->query("ALTER TABLE {$table} ADD COLUMN {$ddl}")) {
        fwrite(STDERR, "تعذر {$table}.{$col}: {$conn->error}\n"); exit(1);
    }
    echo "  + {$table}.{$col}\n";
}
function add_ddl($conn, $guard, $ddl, $label) {
    if ($guard) { echo "  = {$label} قائم\n"; return; }
    if (!$conn->query($ddl)) { fwrite(STDERR, "تعذر {$label}: {$conn->error}\n"); exit(1); }
    echo "  + {$label}\n";
}

// ═══ CAP-01 · contract_commitments — السياسةُ كاملةً (§8.1) ═══
echo "── CAP-01 · contract_commitments\n";
add_col($conn, 'contract_commitments', 'equipment_type_code',
    "equipment_type_code VARCHAR(40) NULL COMMENT 'CAP-01 §8.1: نوعُ المعدة — الصفُّ ذو القيمة التزامُ نوعٍ خاضعٌ لمفتاح UQ' AFTER commitment_type");
add_col($conn, 'contract_commitments', 'primary_units_contracted',
    "primary_units_contracted SMALLINT UNSIGNED NULL COMMENT 'CAP-01 §8.1: عددُ الأساسية المتعاقد عليها — وحدَه يدخل Σ الالتزام' AFTER equipment_type_code");
add_col($conn, 'contract_commitments', 'standby_units_required',
    "standby_units_required SMALLINT UNSIGNED NULL COMMENT 'CAP-01 §8.1: الاحتياطياتُ التي ألزم العميلُ بها — التزامٌ لا خيار' AFTER primary_units_contracted");
add_col($conn, 'contract_commitments', 'standby_units_allowed',
    "standby_units_allowed SMALLINT UNSIGNED NULL COMMENT 'CAP-01 §8.1: السقفُ الأقصى المسموح — وعليه يُقاس (StandbyCapService)' AFTER standby_units_required");
add_col($conn, 'contract_commitments', 'qty_per_primary_unit_month',
    "qty_per_primary_unit_month DECIMAL(14,2) NULL COMMENT 'CAP-01 §8.1: كميةُ الوحدة الأساسية شهريًّا بمقياسها — ومنها تُشتق الكمياتُ كلُّها' AFTER standby_units_allowed");
add_col($conn, 'contract_commitments', 'measure_code',
    "measure_code ENUM('hour','ton','trip','meter') NULL COMMENT 'CAP-01 §16: مقياسُ الكمية — فلا يُخصم الطنُّ من حصة ساعات (C30)' AFTER qty_per_primary_unit_month");
add_col($conn, 'contract_commitments', 'standby_compensation_type',
    "standby_compensation_type ENUM('none','fixed_allowance','readiness_allowance','billed_on_activation') NULL DEFAULT NULL COMMENT 'CAP-01 §8.1: مقابلُ الاحتياطي — NULL = لم يُنَصَّ، ولا يُفترض (DEC-CAP-A)' AFTER measure_code");
add_col($conn, 'contract_commitments', 'standby_activation_rule',
    "standby_activation_rule VARCHAR(255) NULL COMMENT 'CAP-01 §8.1: متى يُفعَّل الاحتياطيُّ وبإذن من ولأي مدة' AFTER standby_compensation_type");
add_col($conn, 'contract_commitments', 'standby_hours_treatment',
    "standby_hours_treatment ENUM('within_obligation','separate_line') NULL COMMENT 'CAP-01 §8.1: ساعاتُ الاحتياطي المفعَّل — ضمن الالتزام أم بندًا مستقلًّا' AFTER standby_activation_rule");
add_col($conn, 'contract_commitments', 'valid_from',
    "valid_from DATE NULL COMMENT 'CAP-01 §5-④: الالتزامُ مؤرَّخ — والتعديلُ فترةٌ جديدةٌ لا مسٌّ بالماضي' AFTER standby_hours_treatment");
add_col($conn, 'contract_commitments', 'valid_to',
    "valid_to DATE NULL AFTER valid_from");
add_col($conn, 'contract_commitments', 'obl_type_uq_key',
    "obl_type_uq_key VARCHAR(130) GENERATED ALWAYS AS (
        IF(equipment_type_code IS NOT NULL AND is_deleted = 0,
           CONCAT(company_id, ':', contract_ref, ':', equipment_type_code, ':', IFNULL(DATE_FORMAT(valid_from, '%Y-%m-%d'), 'open')),
           NULL)) STORED
     COMMENT 'CAP-01: فهرسٌ فريدٌ مشروطٌ على عمودٍ مولَّد — UQ(contract, equipment_type_code, valid_from) للأحياء ذوي النوع (DEC-CAP-C)'");
add_ddl($conn, idx_exists($conn, 'contract_commitments', 'uq_obl_type_from'),
    "ALTER TABLE contract_commitments ADD UNIQUE KEY uq_obl_type_from (obl_type_uq_key)",
    'uq_obl_type_from');

// ═══ CAP-01 · op_containers درجة «نوع» — الأعدادُ الثلاثة ═══
echo "── CAP-01 · op_containers (درجة نوع)\n";
add_col($conn, 'op_containers', 'primary_units_contracted',
    "primary_units_contracted SMALLINT UNSIGNED NULL COMMENT 'CAP-01 §8.1: أساسياتُ درجة «نوع» — الشجرةُ تفرض Σ والسياسةُ في contract_commitments' AFTER contract_hours_monthly");
add_col($conn, 'op_containers', 'standby_units_required',
    "standby_units_required SMALLINT UNSIGNED NULL COMMENT 'CAP-01 §8.1: الاحتياطيُّ المطلوب لدرجة «نوع»' AFTER primary_units_contracted");
add_col($conn, 'op_containers', 'standby_units_allowed',
    "standby_units_allowed SMALLINT UNSIGNED NULL COMMENT 'CAP-01 §8.1: سقفُ الاحتياطي لدرجة «نوع» — StandbyCapService يقيس عليه' AFTER standby_units_required");

// ═══ CAP-02 · supplier_contract_lines — حقولُ §8.2 ═══
echo "── CAP-02 · supplier_contract_lines\n";
add_col($conn, 'supplier_contract_lines', 'contract_obligation_ref',
    "contract_obligation_ref INT UNSIGNED NULL COMMENT 'CAP-01 §8.2: التزامُ نوع المعدة في عقد العميل (contract_commitments.id) — لا حصةَ بلا التزامٍ في عقدٍ نافذ' AFTER contract_id");
add_col($conn, 'supplier_contract_lines', 'equipment_type_code',
    "equipment_type_code VARCHAR(40) NULL COMMENT 'CAP-01 §8.2: نوعُ المعدة الملتزَم به' AFTER contract_obligation_ref");
add_col($conn, 'supplier_contract_lines', 'primary_units_committed',
    "primary_units_committed SMALLINT UNSIGNED NULL COMMENT 'CAP-01 §8.2: عددُ الأساسية التي التزم المورد بتوفيرها' AFTER equipment_type_code");
add_col($conn, 'supplier_contract_lines', 'standby_units_required',
    "standby_units_required SMALLINT UNSIGNED NULL COMMENT 'CAP-01 §8.2: الاحتياطياتُ المطلوبةُ منه' AFTER primary_units_committed");
add_col($conn, 'supplier_contract_lines', 'standby_units_allowed',
    "standby_units_allowed SMALLINT UNSIGNED NULL COMMENT 'CAP-01 §8.2: سقفُه الأقصى — والقيدُ: المسجَّلُ ≤ هذا الرقم (C17)' AFTER standby_units_required");
add_col($conn, 'supplier_contract_lines', 'replacement_sla_hours',
    "replacement_sla_hours DECIMAL(8,2) NULL COMMENT 'CAP-01 §8.2: مهلةُ الإحلال بالساعات — تُقاس من لحظة التعطل لا التغطية (§7)' AFTER standby_units_allowed");
add_col($conn, 'supplier_contract_lines', 'standby_activation_terms',
    "standby_activation_terms VARCHAR(255) NULL COMMENT 'CAP-01 §8.2: شروطُ تفعيل احتياطيّه' AFTER replacement_sla_hours");
add_col($conn, 'supplier_contract_lines', 'standby_payment_terms',
    "standby_payment_terms VARCHAR(255) NULL COMMENT 'CAP-01 §8.2: مقابلُ احتياطيّه إن وُجد — NULL = لم يُنَصَّ ولا يُفترض (DEC-CAP-A)' AFTER standby_activation_terms");
add_ddl($conn, idx_exists($conn, 'supplier_contract_lines', 'ix_sup_line_obl'),
    "ALTER TABLE supplier_contract_lines ADD KEY ix_sup_line_obl (contract_obligation_ref)",
    'ix_sup_line_obl');

// ═══ CAP-03 · seat_assignments — خطةُ المعدات الأولية (§8.3) ═══
echo "── CAP-03 · seat_assignments\n";
add_col($conn, 'seat_assignments', 'planned_qty_month',
    "planned_qty_month DECIMAL(16,2) NULL COMMENT 'CAP-01 §8.3: الحصةُ الشهريةُ الأولية بمقياسها — والاحتياطيُّ صفرٌ قبل التفعيل' AFTER assignment_role");
add_col($conn, 'seat_assignments', 'planned_qty_total',
    "planned_qty_total DECIMAL(16,2) NULL COMMENT 'CAP-01 §8.3: الحصةُ الإجمالية المخططة' AFTER planned_qty_month");
add_col($conn, 'seat_assignments', 'measure_code',
    "measure_code ENUM('hour','ton','trip','meter') NULL COMMENT 'CAP-01 §16: مقياسُ الخطة' AFTER planned_qty_total");
add_col($conn, 'seat_assignments', 'activation_state',
    "activation_state ENUM('active','pending') NOT NULL DEFAULT 'active' COMMENT 'CAP-01 §8.3: حالةُ التفعيل — الاحتياطيُّ pending حتى يُفعَّل بحدثٍ له سببٌ ومعتمِد (§4-④)' AFTER measure_code");
add_col($conn, 'seat_assignments', 'supplier_contract_line_id',
    "supplier_contract_line_id INT NULL COMMENT 'CAP-01 §8.3: بندُ عقد المورد الذي تُحتسب به (supplier_contract_lines.id)' AFTER activation_state");
add_ddl($conn, chk_exists($conn, 'ck_sa_standby_zero'),
    "ALTER TABLE seat_assignments ADD CONSTRAINT ck_sa_standby_zero CHECK (
        activation_state = 'active'
        OR (COALESCE(planned_qty_month, 0) = 0 AND COALESCE(planned_qty_total, 0) = 0))",
    'ck_sa_standby_zero (الاحتياطيُّ صفرُ ساعاتٍ قبل التفعيل)');
add_ddl($conn, idx_exists($conn, 'seat_assignments', 'ix_sa_supplier_line'),
    "ALTER TABLE seat_assignments ADD KEY ix_sa_supplier_line (supplier_contract_line_id)",
    'ix_sa_supplier_line');

echo "تمت هجرة الموجة ① — CAP-01/02/03\n";
