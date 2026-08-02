<?php
/**
 * update0005 · الموجة ② · CAP-12 — خرطُ container_consumption القائم إلى الدفتر
 * ───────────────────────────────────────────────────────────────────────────
 * «صفرُ استهلاكٍ موروثٍ بلا سطرٍ في الدفتر» — كلُّ صفٍّ حيٍّ في
 * container_consumption يُخرَط سطرًا في capacity_consumption_ledger بمفتاحه:
 *   · unit_record_id من source_ref (source_kind=unit_entry)
 *   · unit_record_version من revision_no للسجل الحي، وإلا من لاحقة idem_key
 *     (entry:N:rV) — والمتعذّرُ يُعلَن ولا يُلفَّق.
 *   · الأثرُ بحسب درجة الحاوية: مشغّل→operator · مورد→supplier · غيرُهما→client.
 *   · السالبُ (ردٌّ موروث) يُعلَن ولا يُخرَط عكسًا ملفَّقًا (لا أصلَ له في الدفتر).
 * idempotent: الوجودُ يُفحص بمفتاح الدفتر قبل الإدراج.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');

$rows = $conn->query(
    "SELECT cc.*, oc.level, oc.contract_id, oc.supplier_id, oc.operator_employee_id,
            oc.parent_id, oc.seat_no
       FROM container_consumption cc
       JOIN op_containers oc ON oc.id = cc.container_id");
$done = 0; $skipped = 0; $declared = array();
while ($r = $rows->fetch_assoc()) {
    if ((float) $r['qty'] < 0) {
        $declared[] = "صف #{$r['id']}: كميةٌ سالبةٌ (ردٌّ موروثٌ بلا أصلٍ في الدفتر) — يُعلَن ولا يُلفَّق عكسًا";
        $skipped++;
        continue;
    }
    if ((string) $r['source_kind'] !== 'unit_entry') {
        $declared[] = "صف #{$r['id']}: مصدرٌ {$r['source_kind']} بلا سجلِّ وحدةٍ — يُعلَن";
        $skipped++;
        continue;
    }
    if (!in_array((string) $r['unit_type'], array('hour', 'ton', 'trip', 'meter'), true)) {
        $declared[] = "صف #{$r['id']}: مقياسٌ {$r['unit_type']} خارج مقاييس §16 الأربعة — يُعلَن";
        $skipped++;
        continue;
    }
    $entryId = (int) $r['source_ref'];
    // النسخة: من السجل الحي أولًا ثم من لاحقة idem_key (entry:N:rV)
    $version = null;
    $e = $conn->query("SELECT revision_no FROM unit_entries WHERE id = {$entryId}");
    if ($e && ($x = $e->fetch_assoc())) { $version = (int) $x['revision_no']; }
    if ($version === null && preg_match('/:r(\d+)$/', (string) $r['idem_key'], $m)) { $version = (int) $m[1]; }
    if ($version === null) {
        $declared[] = "صف #{$r['id']}: لا نسخةَ للسجل {$entryId} (السجلُّ ميتٌ وidem_key بلا لاحقة) — يُعلَن";
        $skipped++;
        continue;
    }
    // الأثرُ بحسب درجة الحاوية
    switch ((string) $r['level']) {
        case 'مشغّل':
            $effect = 'operator_entitlement'; $target = 'operator';
            $ref = $r['operator_employee_id'] !== null ? 'emp:' . (int) $r['operator_employee_id'] : 'container:' . (int) $r['container_id'];
            break;
        case 'مورد':
            $effect = 'supplier_share'; $target = 'supplier';
            $ref = $r['supplier_id'] !== null ? 'sup:' . (int) $r['supplier_id'] : 'container:' . (int) $r['container_id'];
            break;
        default:
            $effect = 'client_obligation'; $target = 'client';
            $ref = 'contract:' . (int) $r['contract_id'];
    }
    $period = substr((string) $r['consumed_on'], 0, 7);
    $stmt = $conn->prepare(
        "INSERT INTO capacity_consumption_ledger
           (company_id, unit_record_id, unit_record_version, contract_seat_id,
            effect_target_type, effect_target_ref, measure_code, qty,
            effect_type, period, created_by)
         SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL
          WHERE NOT EXISTS (SELECT 1 FROM capacity_consumption_ledger x
                             WHERE x.unit_record_id = ? AND x.unit_record_version = ?
                               AND x.effect_type = ? AND x.effect_target_type = ? AND x.effect_target_ref = ?)");
    $co = (int) $r['company_id']; $cid = (int) $r['container_id'];
    $mq = (string) $r['unit_type']; $q = (float) $r['qty'];
    $stmt->bind_param('iiiisssdssiisss',
        $co, $entryId, $version, $cid, $target, $ref, $mq, $q, $effect, $period,
        $entryId, $version, $effect, $target, $ref);
    if (!$stmt->execute()) { fwrite(STDERR, "فشل خرط صف #{$r['id']}: {$stmt->error}\n"); exit(1); }
    if ($stmt->affected_rows > 0) { $done++; } else { $skipped++; }
    $stmt->close();
}
echo "خُرط {$done} سطرًا · وتُرك {$skipped} (قائمٌ أو معلَن)\n";
foreach ($declared as $d) { echo "  ◄ {$d}\n"; }

// القبول: صفرُ استهلاكٍ موروثٍ صالحٍ بلا سطرٍ في الدفتر
$orphans = intval($conn->query(
    "SELECT COUNT(*) n FROM container_consumption cc
      WHERE cc.qty >= 0 AND cc.source_kind = 'unit_entry'
        AND cc.unit_type IN ('hour','ton','trip','meter')
        AND NOT EXISTS (SELECT 1 FROM capacity_consumption_ledger l
                         WHERE l.unit_record_id = cc.source_ref)")->fetch_assoc()['n']);
if ($orphans > 0) { fwrite(STDERR, "بقي {$orphans} استهلاكًا موروثًا بلا سطرِ دفتر\n"); exit(1); }
echo "القبول: صفرُ استهلاكٍ موروثٍ بلا سطرٍ في الدفتر ✔\n";
