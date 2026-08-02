<?php
/**
 * update0005 · الموجة ③ · CAP-14/15/16/17 — قلبُ الأرصدة والقيودُ في القاعدة
 * ───────────────────────────────────────────────────────────────────────────
 * CAP-16 (DEC-CAP-C): قيودُ الدرجات في القاعدة بفهارسَ فريدةٍ مشروطةٍ على
 *   أعمدةٍ مولَّدة:
 *   · UQ(obl_id, seat_no) للمقاعد — عمودٌ مولَّد seat_obl_uq_key.
 *   · تطابقُ التزامِ المقعد والتزامِ حصته — قيدُ FK مركّب
 *     (parent_id, obl_id) → op_containers(id, obl_id): لا ينتمي مقعدٌ إلى
 *     التزامٍ غيرِ التزامِ أبيه (C21) — قيدٌ في القاعدة لا فحصُ تطبيق.
 *   · منعُ تخصيصين مفتوحين فعّالين لمقعدٍ واحد — عمودٌ مولَّد على
 *     seat_assignments (والاحتياطيُّ غيرُ المفعَّل خارج القيد — C4)؛
 *     والتداخلُ المدَّدُ يبقى بحارس الخدمة 409 (لا فهرسَ مدًى في MySQL).
 * CAP-17: حالةُ خطة التغطية على الالتزام (plan_state) ومرجعُ الاستثناء —
 *   وقيدُ Σ الواعي بالحالة في SigmaGuard.
 * CAP-15: جدولُ فروق الظل capacity_shadow_diffs — ميزانُ «صفرِ فرقٍ ١٤ يومًا».
 * idempotent بالكامل.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');

function w3_exists($conn, $sql) {
    $r = $conn->query($sql);
    return $r && intval($r->fetch_row()[0]) > 0;
}
function w3_col($conn, $t, $c) {
    return w3_exists($conn, "SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = '{$t}' AND column_name = '{$c}'");
}
function w3_idx($conn, $t, $i) {
    return w3_exists($conn, "SELECT COUNT(*) FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = '{$t}' AND index_name = '{$i}'");
}
function w3_fk($conn, $name) {
    return w3_exists($conn, "SELECT COUNT(*) FROM information_schema.table_constraints
        WHERE constraint_schema = DATABASE() AND constraint_name = '{$name}' AND constraint_type = 'FOREIGN KEY'");
}
function w3_run($conn, $guard, $ddl, $label) {
    if ($guard) { echo "  = {$label} قائم\n"; return; }
    if (!$conn->query($ddl)) { fwrite(STDERR, "تعذر {$label}: {$conn->error}\n"); exit(1); }
    echo "  + {$label}\n";
}

// ═══ CAP-17 · حالةُ خطة التغطية على الالتزام ═══
echo "── CAP-17 · contract_commitments: حالةُ الخطة ومرجعُ الاستثناء\n";
w3_run($conn, w3_col($conn, 'contract_commitments', 'plan_state'),
    "ALTER TABLE contract_commitments ADD COLUMN plan_state
       ENUM('draft','partial','submitted','approved') NOT NULL DEFAULT 'draft'
       COMMENT 'CAP-01 §5-②: حالةُ خطة التغطية — المسودةُ والجزئيةُ Σ≤ والمعتمدةُ Σ= أو استثناءٌ موقَّع' AFTER standby_hours_treatment",
    'contract_commitments.plan_state');
w3_run($conn, w3_col($conn, 'contract_commitments', 'sigma_exception_ref'),
    "ALTER TABLE contract_commitments ADD COLUMN sigma_exception_ref VARCHAR(120) NULL
       COMMENT 'CAP-01 §5-②: مرجعُ قرار الاستثناء الموقَّع — إلزاميٌّ لاعتمادٍ بفجوةٍ ظاهرة (C16)' AFTER plan_state",
    'contract_commitments.sigma_exception_ref');

// ═══ CAP-16 · obl_id على الشجرة + القيود ═══
echo "── CAP-16 · op_containers: obl_id وقيودُ الدرجات\n";
w3_run($conn, w3_col($conn, 'op_containers', 'obl_id'),
    "ALTER TABLE op_containers ADD COLUMN obl_id INT UNSIGNED NULL
       COMMENT 'CAP-01 §16: التزامُ نوع المعدة (contract_commitments.id) — مضافٌ صراحةً ليصحَّ قيدُ التطابق (C21)' AFTER contract_item_id",
    'op_containers.obl_id');
if (w3_idx($conn, 'op_containers', 'ix_oc_id_obl')) {
    $conn->query("ALTER TABLE op_containers DROP KEY ix_oc_id_obl");
    echo "  - ix_oc_id_obl أُسقط (يلزم فريدٌ لمرجع FK المركّب)\n";
}
w3_run($conn, w3_idx($conn, 'op_containers', 'uq_oc_id_obl'),
    "ALTER TABLE op_containers ADD UNIQUE KEY uq_oc_id_obl (id, obl_id)",
    'uq_oc_id_obl (مرجعُ القيد المركّب — فريدٌ حكمًا لأن id مفتاح)');
w3_run($conn, w3_fk($conn, 'fk_oc_parent_obl'),
    "ALTER TABLE op_containers ADD CONSTRAINT fk_oc_parent_obl
       FOREIGN KEY (parent_id, obl_id) REFERENCES op_containers (id, obl_id)",
    'fk_oc_parent_obl (تطابقُ التزامِ الابن والتزامِ أبيه — C21 بنيويًّا)');
w3_run($conn, w3_col($conn, 'op_containers', 'seat_obl_uq_key'),
    "ALTER TABLE op_containers ADD COLUMN seat_obl_uq_key VARCHAR(40) GENERATED ALWAYS AS (
        IF(seat_no IS NOT NULL AND obl_id IS NOT NULL AND is_deleted = 0,
           CONCAT(obl_id, ':', seat_no), NULL)) STORED
       COMMENT 'CAP-01 §16: UQ(obl_id, seat_no) — فهرسٌ فريدٌ مشروطٌ على عمودٍ مولَّد'",
    'op_containers.seat_obl_uq_key');
w3_run($conn, w3_idx($conn, 'op_containers', 'uq_seat_per_obl'),
    "ALTER TABLE op_containers ADD UNIQUE KEY uq_seat_per_obl (seat_obl_uq_key)",
    'uq_seat_per_obl');

// منعُ تخصيصين مفتوحين فعّالين لمقعد — والاحتياطيُّ غيرُ المفعَّل خارج القيد (C4)
echo "── CAP-16 · seat_assignments: قيدُ التخصيص المفتوح الواحد\n";
w3_run($conn, w3_col($conn, 'seat_assignments', 'active_open_seat_key'),
    "ALTER TABLE seat_assignments ADD COLUMN active_open_seat_key VARCHAR(40) GENERATED ALWAYS AS (
        IF(state = 'active' AND date_to IS NULL
           AND (assignment_role <> 'احتياطي' OR activation_state = 'active'),
           CONCAT(company_id, ':', container_id), NULL)) STORED
       COMMENT 'CAP-01 §4-⑥/C4: تخصيصٌ مفتوحٌ فعّالٌ واحدٌ لكل مقعد — والاحتياطيُّ pending خارج القيد؛ التداخلُ المدَّدُ بحارس الخدمة'",
    'seat_assignments.active_open_seat_key');
w3_run($conn, w3_idx($conn, 'seat_assignments', 'uq_sa_active_open'),
    "ALTER TABLE seat_assignments ADD UNIQUE KEY uq_sa_active_open (active_open_seat_key)",
    'uq_sa_active_open');

// ═══ CAP-15 · فروقُ الظل — ميزانُ الأربعة عشر يومًا ═══
echo "── CAP-15 · capacity_shadow_diffs\n";
w3_run($conn,
    w3_exists($conn, "SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'capacity_shadow_diffs'"),
    "CREATE TABLE capacity_shadow_diffs (
        diff_id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_id      INT NOT NULL,
        container_id    INT UNSIGNED NOT NULL,
        stored_consumed DECIMAL(16,2) NOT NULL COMMENT 'العمودُ المخزَّن لحظةَ القياس',
        ledger_consumed DECIMAL(16,2) NOT NULL COMMENT 'المحسوبُ من الدفتر والإعكاسات',
        diff_qty        DECIMAL(16,2) NOT NULL COMMENT 'الفرق — والحدُّ صفرٌ لا نسبة',
        noted_on        DATE NOT NULL COMMENT 'يومُ الرصد بساعة القاعدة',
        detail          VARCHAR(200) NULL,
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (diff_id),
        UNIQUE KEY uq_shadow_daily (container_id, noted_on),
        KEY ix_shadow_day (company_id, noted_on)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='CAP-01 · EMS_CAPACITY_SOURCE: فروقُ الظل بين العمود المخزَّن والدفتر — لا قلبَ قبل صفرِ فرقٍ ١٤ يومًا متصلة (نمطُ EMS_PERM_SOURCE)'",
    'capacity_shadow_diffs');

echo "تمت هجرة الموجة ③\n";
