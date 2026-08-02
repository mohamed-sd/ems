<?php
/**
 * update0005 · الموجة ⑥ · CAP-31/33 — مفاتيحُ §12.1 الثمانيةُ لقطةً على unit_entries
 * ───────────────────────────────────────────────────────────────────────────
 * «التخصيصاتُ تتغير بعد شهر … ولو حُلّت المراجعُ عند التسوية من الوضع الحالي
 * لأنتجت غيرَ ما وقع فعلًا. فتُجلب المراجعُ آليًّا عند الإدخال · وتُعرض للتأكيد ·
 * وتُثبَّت لقطةً عند الاعتماد ولا تُحلّ ثانيةً أبدًا» (§12.1 · C29).
 * revision_no القائمُ هو النسخة — لا عمودَ نسخةٍ جديدًا (§3.1 من البرومت).
 * idempotent.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');

function w6_col($conn, $c) {
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'unit_entries' AND column_name = '{$c}'");
    return $r && intval($r->fetch_row()[0]) > 0;
}
function w6_add($conn, $c, $ddl) {
    if (w6_col($conn, $c)) { echo "  = unit_entries.{$c} قائم\n"; return; }
    if (!$conn->query("ALTER TABLE unit_entries ADD COLUMN {$ddl}")) {
        fwrite(STDERR, "تعذر {$c}: {$conn->error}\n"); exit(1);
    }
    echo "  + unit_entries.{$c}\n";
}

// §12.1 — المفاتيحُ الثمانيةُ المثبَّتة في سجل الوحدة
w6_add($conn, 'cap_obligation_id',
    "cap_obligation_id INT UNSIGNED NULL COMMENT '§12.1-①: التزامُ النوع المستهلَك — contract_commitments.id (لقطة)' AFTER event_id");
w6_add($conn, 'cap_supplier_share_id',
    "cap_supplier_share_id INT UNSIGNED NULL COMMENT '§12.1-②: حصةُ المورد المنفَّذُ منها — op_containers درجة «مورد»' AFTER cap_obligation_id");
w6_add($conn, 'cap_seat_id',
    "cap_seat_id INT UNSIGNED NULL COMMENT '§12.1-③: المقعدُ التعاقدي — op_containers درجة «معدة»' AFTER cap_supplier_share_id");
w6_add($conn, 'cap_assignment_id',
    "cap_assignment_id INT UNSIGNED NULL COMMENT '§12.1-④: فترةُ إسناد المعدة — seat_assignments.id' AFTER cap_seat_id");
w6_add($conn, 'cap_supplier_line_id',
    "cap_supplier_line_id INT NULL COMMENT '§12.1-⑤: بندُ عقد المورد الذي يُحتسب به' AFTER cap_assignment_id");
w6_add($conn, 'cap_role_snapshot',
    "cap_role_snapshot ENUM('primary','standby') NULL COMMENT '§12.1-⑥: أساسيةٌ أم احتياطيةٌ مفعَّلة لحظةَ الواقعة — ولو تغيّر الدورُ لاحقًا' AFTER cap_supplier_line_id");
w6_add($conn, 'cap_coverage_id',
    "cap_coverage_id BIGINT UNSIGNED NULL COMMENT '§12.1-⑦: إن كانت تغطيةً بديلة — substitute_coverages.cov_id' AFTER cap_role_snapshot");
w6_add($conn, 'cap_measure_code',
    "cap_measure_code ENUM('hour','ton','trip','meter') NULL COMMENT '§12.1-⑧: المقياس — فلا يُخصم الطنُّ من حصة ساعات' AFTER cap_coverage_id");
w6_add($conn, 'cap_context_state',
    "cap_context_state ENUM('proposed','confirmed','locked') NULL COMMENT '§12.1: مقترحةٌ عند الإدخال · مؤكدةٌ من المستخدم · مقفلةٌ لقطةً عند الاعتماد فلا تُحلّ ثانيةً (C29)' AFTER cap_measure_code");

echo "تمت هجرة الموجة ⑥\n";
