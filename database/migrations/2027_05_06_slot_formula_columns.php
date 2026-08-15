<?php
/**
 * 2027_05_06_slot_formula_columns.php
 * ═══════════════════════════════════════════════════════════════════════════
 * F-01/F-02 قادحَين على خانةِ الآلية — والدورُ يُشتق من عقدِ الورديات
 *
 * الحكم (TS-01):
 *   F-01: daily_hours_basis = CASE slot_role WHEN 'primary_two_shifts' THEN 20
 *         WHEN 'primary_one_shift' THEN 12 ELSE reserve_basis END — «قادحٌ ولا يُقبل من الشاشة»
 *   F-02: monthly_basis = daily_hours_basis × 30 — «قادحٌ يحسبه ويرفض قيمةً مخالفة»
 *
 * ◆ النظيرُ الحيُّ للخانة صفوفُ `op_containers` بمستوى «معدة» (TS-01 نفسُها
 *   توجب المطابقةَ لا الإنشاء). تُضاف الأعمدةُ الأربعةُ (slot_role ·
 *   daily_hours_basis · monthly_basis · unit_margin لصالحِ F-12) والقادحان
 *   يحسبان **ويدوسان** أيَّ قيمةٍ أُدخلت.
 * ◆ والردمُ من الحيّ: `contracts.equip_shifts_contract` (2 ⇐ ورديتان · 1 ⇐
 *   واحدة) — فالمقاعدُ بلا `shift_no` والعقدُ هو حاملُ الورديات.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? $r->fetch_row()[0] : null; };
$hasCol = function (string $c) use ($conn): bool {
    $r = $conn->query("SHOW COLUMNS FROM op_containers LIKE '" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};

echo "══ F-01/F-02: أعمدةُ الخانةِ وقادحاها ══\n\n";
foreach (array(
    array('slot_role',         "ENUM('primary_two_shifts','primary_one_shift','reserve') NULL COMMENT 'دورُ الخانة — مصدرُ F-01'"),
    array('daily_hours_basis', "DECIMAL(5,2) NULL COMMENT 'F-01: 20 للورديتين · 12 للواحدة — قادحٌ يحسبه ولا يُقبل من الشاشة'"),
    array('monthly_basis',     "DECIMAL(8,2) NULL COMMENT 'F-02: اليوميُّ × 30 — قادحٌ يحسبه'"),
    array('unit_margin',       "DECIMAL(12,4) NULL COMMENT 'هامشُ الخانة — مدخلُ F-12'"),
) as [$c, $def]) {
    if ($hasCol($c)) { echo "  · $c قائم\n"; continue; }
    if (!$conn->query("ALTER TABLE op_containers ADD COLUMN `$c` $def")) { exit("  ✘ $c: {$conn->error}\n"); }
    echo "  ✔ $c\n";
}

$conn->query("DROP TRIGGER IF EXISTS trg_opc_f0102_ins");
$conn->query("DROP TRIGGER IF EXISTS trg_opc_f0102_upd");
$B = "IF NEW.slot_role IS NOT NULL THEN
        SET NEW.daily_hours_basis = CASE NEW.slot_role
            WHEN 'primary_two_shifts' THEN 20
            WHEN 'primary_one_shift'  THEN 12
            ELSE COALESCE(NEW.daily_hours_basis, 0) END;
        SET NEW.monthly_basis = NEW.daily_hours_basis * 30;
      END IF;";
if (!$conn->query("CREATE TRIGGER trg_opc_f0102_ins BEFORE INSERT ON op_containers FOR EACH ROW BEGIN $B END")
 || !$conn->query("CREATE TRIGGER trg_opc_f0102_upd BEFORE UPDATE ON op_containers FOR EACH ROW BEGIN $B END")) {
    exit("  ✘ القادحان: {$conn->error}\n");
}
echo "  ✔ قادحا F-01/F-02\n";

/* الردمُ من عقدِ الورديات */
$conn->query("UPDATE op_containers c
              JOIN contracts k ON k.id = c.contract_id
              SET c.slot_role = CASE
                    WHEN COALESCE(k.equip_shifts_contract, 0) >= 2 THEN 'primary_two_shifts'
                    WHEN COALESCE(k.equip_shifts_contract, 0) = 1 THEN 'primary_one_shift'
                    ELSE c.slot_role END
              WHERE c.level = 'معدة' AND c.is_deleted = 0 AND c.slot_role IS NULL");
echo '  ✔ رُدم دورُ ' . $conn->affected_rows . " خانةً من عقدِ الورديات\n";

/* الإثبات: المُدخَلُ يُداس */
$sid = (int) $one("SELECT id FROM op_containers WHERE level='معدة' AND slot_role='primary_two_shifts' AND is_deleted=0 LIMIT 1");
if ($sid) {
    $conn->query("UPDATE op_containers SET daily_hours_basis = 999, monthly_basis = 999 WHERE id = $sid");
    $row = $conn->query("SELECT daily_hours_basis, monthly_basis FROM op_containers WHERE id = $sid")->fetch_assoc();
    $okF = ((float) $row['daily_hours_basis'] === 20.0 && (float) $row['monthly_basis'] === 600.0);
    echo '  اختبارُ الدوس: ' . ($okF ? '✔ 999 ⇐ 20 و600' : "✘ {$row['daily_hours_basis']}/{$row['monthly_basis']}") . "\n";
    if (!$okF) { exit(1); }
}
$r = $conn->query("SELECT slot_role, COUNT(*), MIN(daily_hours_basis), MIN(monthly_basis) FROM op_containers
                   WHERE level='معدة' AND is_deleted=0 AND slot_role IS NOT NULL GROUP BY slot_role");
while ($x = $r->fetch_row()) { echo "  {$x[0]}: {$x[1]} خانة · يومي {$x[2]} · شهري {$x[3]}\n"; }
echo "\n✔ تمّت\n";
