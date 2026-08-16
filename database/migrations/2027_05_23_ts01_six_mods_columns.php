<?php
/**
 * 2027_05_23_ts01_six_mods_columns.php
 * ═══════════════════════════════════════════════════════════════════════════
 * إكمالُ «الستةِ تعديلاتٍ على القائم» — الأعمدةُ الأربعةَ عشرَ الباقية
 *
 * مسبارُ الحزمةِ يقيس أعمدةَ TS-01 على النظائرِ الحيّةِ بأسمائِها، والباقي:
 *   contract_amendments 0/7 · contract_commitments 0/4 · container_consumption 0/3.
 * تُضاف وتُردم من الحيِّ (لا حدسًا) وتُفرض صيغُها:
 *
 * ① contract_amendments (الحاويةُ السنوية):
 *   container_key من الحاويةِ الرئيسيةِ لعقدِ الملحق · capacity_units من
 *   سعتِها (F-04 صعودًا — والمصدرُ يُذكر في capacity_source لا يُدَّعى) ·
 *   work_model/unit_of_measure من نوعِ وحدتِها · actual_start/end من العقد.
 * ② contract_commitments (حاويةُ النوع):
 *   container_key من حاويةِ موردِ النوع · slot_monthly_basis من مقاعدِها
 *   (F-02: اليوميُّ×30) · renewal_months من مدةِ سريانِها ·
 *   **type_capacity بقادحِ F-03**: primary_units × slot_monthly_basis ×
 *   renewal_months — «قادحٌ يفرضه ومحاولةُ إدخالِ قيمةٍ مخالفةٍ تُرفض» (تُداس).
 * ③ container_consumption (دفترُ الاستهلاك — الطبقاتُ صفوفًا):
 *   layer (يومي/شهري/سنوي) · share_key (مفتاحُ الحصة: الحاوية) ·
 *   gap_units (فجوةُ السقف: المتبقي بعد الصف) + رصيدا قبل/بعد بقادحٍ
 *   يحسبهما من الحاويةِ — فالدفترُ يقرأ رصيدَه ولا يُمليه.
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
$addCol = function (string $t, string $c, string $def) use ($conn): void {
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($c) . "'");
    if ($r && $r->num_rows) { echo "  · $t.$c قائم\n"; return; }
    if (!$conn->query("ALTER TABLE `$t` ADD COLUMN `$c` $def")) { exit("  ✘ $t.$c: {$conn->error}\n"); }
    echo "  ✔ $t.$c\n";
};

echo "══ الأعمدةُ الأربعةَ عشرَ وردمُها وصيغُها ══\n\n─ ① contract_amendments ─\n";
$addCol('contract_amendments', 'container_key',   "VARCHAR(32) NULL COMMENT 'مفتاحُ الحاويةِ السنوية — من op_containers الرئيسية'");
$addCol('contract_amendments', 'capacity_units',  "DECIMAL(14,2) NULL COMMENT 'سعةُ الحاوية — محسوبةٌ صعودًا (F-04) لا مُدخَلة'");
$addCol('contract_amendments', 'work_model',      "ENUM('hourly','tonnage','metering') NULL COMMENT 'نموذجُ العمل'");
$addCol('contract_amendments', 'unit_of_measure', "ENUM('hour','ton','meter') NULL COMMENT 'الوحدة'");
$addCol('contract_amendments', 'actual_start',    "DATE NULL COMMENT 'البدايةُ التنفيذيةُ من العقد'");
$addCol('contract_amendments', 'actual_end',      "DATE NULL COMMENT 'النهايةُ التنفيذية'");
$addCol('contract_amendments', 'capacity_source', "VARCHAR(64) NULL COMMENT 'مصدرُ السعة — يُذكر لا يُدَّعى'");
$conn->query("UPDATE contract_amendments a
              JOIN contracts k ON k.id = a.contract_id
              LEFT JOIN op_containers c ON c.contract_id = k.id AND c.level = 'رئيسية' AND c.is_deleted = 0
              SET a.container_key   = COALESCE(a.container_key, c.container_no),
                  a.capacity_units  = COALESCE(a.capacity_units, c.cap_qty),
                  a.work_model      = COALESCE(a.work_model, CASE c.unit_type WHEN 'hour' THEN 'hourly' WHEN 'ton' THEN 'tonnage' WHEN 'meter' THEN 'metering' END),
                  a.unit_of_measure = COALESCE(a.unit_of_measure, CASE WHEN c.unit_type IN ('hour','ton','meter') THEN c.unit_type END),
                  a.actual_start    = COALESCE(a.actual_start, k.actual_start),
                  a.actual_end      = COALESCE(a.actual_end, k.actual_end),
                  a.capacity_source = COALESCE(a.capacity_source, 'op_containers.cap_qty (F-04 صعودًا)')
              WHERE COALESCE(a.is_deleted, 0) = 0");
echo '  ✔ رُدم ' . $conn->affected_rows . " ملحقًا من الحاويةِ الرئيسيةِ والعقد\n";

echo "\n─ ② contract_commitments + قادحُ F-03 ─\n";
$addCol('contract_commitments', 'container_key',      "VARCHAR(32) NULL COMMENT 'حاويةُ النوع — من op_containers مستوى مورد/نوع'");
$addCol('contract_commitments', 'slot_monthly_basis', "DECIMAL(8,2) NULL COMMENT 'أساسُ الخانةِ الشهري (F-02: اليومي×30)'");
$addCol('contract_commitments', 'renewal_months',     "DECIMAL(6,2) NULL COMMENT 'أشهرُ التجديد — من مدةِ السريان'");
$addCol('contract_commitments', 'type_capacity',      "DECIMAL(14,2) NULL COMMENT 'F-03: العددُ×الأساسُ الشهري×الأشهر — قادحٌ يفرضه ولا يُدخَل'");
$conn->query("DROP TRIGGER IF EXISTS trg_cmt_f03_ins");
$conn->query("DROP TRIGGER IF EXISTS trg_cmt_f03_upd");
$B = "IF NEW.slot_monthly_basis IS NOT NULL AND NEW.renewal_months IS NOT NULL THEN
        SET NEW.type_capacity = COALESCE(NEW.primary_units_contracted, 0) * NEW.slot_monthly_basis * NEW.renewal_months;
      END IF;";
if (!$conn->query("CREATE TRIGGER trg_cmt_f03_ins BEFORE INSERT ON contract_commitments FOR EACH ROW BEGIN $B END")
 || !$conn->query("CREATE TRIGGER trg_cmt_f03_upd BEFORE UPDATE ON contract_commitments FOR EACH ROW BEGIN $B END")) {
    exit("  ✘ قادحُ F-03: {$conn->error}\n");
}
echo "  ✔ قادحا F-03\n";
/* الردم: أساسُ الخانةِ من مقاعدِ حاويةِ العقدِ الحيّة (600 للورديتين) · والأشهرُ من السريان */
$conn->query("UPDATE contract_commitments m
              LEFT JOIN contracts k ON k.id = CAST(m.contract_ref AS UNSIGNED)
              LEFT JOIN (SELECT contract_id, container_no, cap_qty FROM op_containers
                          WHERE level='مورد' AND is_deleted=0 GROUP BY contract_id, container_no, cap_qty) sup
                     ON sup.contract_id = k.id
              LEFT JOIN (SELECT c.contract_id, AVG(c.monthly_basis) mb FROM op_containers c
                          WHERE c.level='معدة' AND c.is_deleted=0 AND c.monthly_basis IS NOT NULL
                          GROUP BY c.contract_id) seat ON seat.contract_id = k.id
              SET m.container_key      = COALESCE(m.container_key, sup.container_no),
                  m.slot_monthly_basis = COALESCE(m.slot_monthly_basis, seat.mb),
                  m.renewal_months     = COALESCE(m.renewal_months,
                        NULLIF(ROUND(DATEDIFF(COALESCE(m.valid_to, k.actual_end), COALESCE(m.valid_from, k.actual_start)) / 30.0, 2), 0))
              WHERE COALESCE(m.is_deleted, 0) = 0");
echo '  ✔ رُدم ' . $conn->affected_rows . " التزامًا (والقادحُ حسب type_capacity حيثُ اكتملت مدخلاتُه)\n";
/* إثباتُ الدوس */
$cid = (int) $one("SELECT id FROM contract_commitments WHERE slot_monthly_basis IS NOT NULL AND renewal_months IS NOT NULL AND COALESCE(is_deleted,0)=0 LIMIT 1");
if ($cid) {
    $conn->query("UPDATE contract_commitments SET type_capacity = 999999 WHERE id = $cid");
    $row = $conn->query("SELECT primary_units_contracted u, slot_monthly_basis b, renewal_months m, type_capacity t FROM contract_commitments WHERE id=$cid")->fetch_assoc();
    $want = round((float) $row['u'] * (float) $row['b'] * (float) $row['m'], 2);
    $okF = abs((float) $row['t'] - $want) < 0.02;
    echo '  اختبارُ الدوس F-03: ' . ($okF ? "✔ 999999 ⇐ $want" : "✘ {$row['t']} ≠ $want") . "\n";
    if (!$okF) { exit(1); }
}

echo "\n─ ③ container_consumption — الطبقاتُ صفوفًا والرصيدُ بقادح ─\n";
$addCol('container_consumption', 'layer',          "ENUM('daily','monthly','annual') NOT NULL DEFAULT 'daily' COMMENT 'طبقةُ الاستهلاك — الصفُّ يعرف طبقتَه'");
$addCol('container_consumption', 'share_key',      "VARCHAR(64) NULL COMMENT 'مفتاحُ الحصةِ المستهلَكة (الحاوية/الخانة)'");
$addCol('container_consumption', 'gap_units',      "DECIMAL(14,2) NULL COMMENT 'فجوةُ السقفِ بعد الصف — بقادحٍ لا إدخالًا'");
$addCol('container_consumption', 'balance_before', "DECIMAL(14,2) NULL COMMENT 'الرصيدُ قبلَ الصف — بقادح'");
$addCol('container_consumption', 'balance_after',  "DECIMAL(14,2) NULL COMMENT 'الرصيدُ بعدَ الصف — بقادح'");
$conn->query("DROP TRIGGER IF EXISTS trg_cc_balance_ins");
$ok = $conn->query("CREATE TRIGGER trg_cc_balance_ins BEFORE INSERT ON container_consumption FOR EACH ROW
BEGIN
    DECLARE v_cap DECIMAL(14,2); DECLARE v_used DECIMAL(14,2); DECLARE v_key VARCHAR(64);
    SELECT cap_qty, consumed_qty, container_no INTO v_cap, v_used, v_key
      FROM op_containers WHERE id = NEW.container_id;
    SET NEW.share_key      = COALESCE(NEW.share_key, v_key);
    SET NEW.balance_before = v_cap - COALESCE(v_used, 0);
    SET NEW.balance_after  = NEW.balance_before - COALESCE(NEW.qty, 0);
    SET NEW.gap_units      = GREATEST(0, -NEW.balance_after);
END");
if (!$ok) { exit("  ✘ قادحُ الرصيد: {$conn->error}\n"); }
echo "  ✔ قادحُ الرصيدِ قبل/بعد وفجوةِ السقف\n";
/* إثباتٌ حيٌّ مُرجَع */
$anyC = (int) $one("SELECT id FROM op_containers WHERE is_deleted=0 AND cap_qty>0 LIMIT 1");
$conn->begin_transaction();
$conn->query("INSERT INTO container_consumption (company_id, container_id, source_kind, source_ref, qty, unit_type, consumed_on, idem_key, created_at)
              VALUES (4, $anyC, 'probe', 'MIG-PROBE', 3, 'hour', CURDATE(), CONCAT('mig-', $anyC), NOW())");
$row = $conn->query("SELECT layer, share_key, balance_before, balance_after FROM container_consumption WHERE source_ref='MIG-PROBE'")->fetch_assoc();
$okB = $row && $row['balance_before'] !== null && $row['balance_after'] !== null && $row['share_key'] !== null;
echo '  إثباتُ الرصيد: ' . ($okB ? "✔ قبل {$row['balance_before']} ⇐ بعد {$row['balance_after']} (حصة {$row['share_key']} · طبقة {$row['layer']})" : '✘') . "\n";
$conn->rollback();
if (!$okB) { exit(1); }
echo "\n✔ تمّت\n";
