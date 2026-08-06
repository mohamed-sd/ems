<?php
/**
 * tools/e02_bridge_backfill.php — جسرُ الموروث بين السكّتين (E-02 · ردم UAT-B07)
 * ───────────────────────────────────────────────────────────────────────────
 * 5,378 صفَّ سلسلةٍ في sales_approved بلا sync_uuid (دفعة ردمٍ لم تجسّر) —
 * فلا يبلغها التحويلُ المالي. المطابقة تصاعديةُ المفاتيح، حتميةٌ، ولا تلفيق:
 *   L1: (معدة × تاريخ) فريدةُ المقابل في timesheet ⇒ جسر.
 *   L2: الغامضُ في L1 يُعاد بمفتاح (معدة × تاريخ × مشغّل) ⇒ فريدُه يُجسَّر.
 *   الباقي غامضٌ يُصدَّر تقريرًا (docs/E02_BRIDGE_AMBIGUOUS_ar.csv) ولا يُخمَّن.
 * ⚠️ گوتشا (معدة×تاريخ×وردية) فيها 5,986 تصادمًا تاريخيًّا — لذلك لا UNIQUE
 *    ولا مطابقةَ عمياء: الادعاء يفحص أن صفَّ الدوام غيرُ مُطالَبٍ بجسرٍ آخر.
 * الشفاء المرافق: رأسُ مرآةٍ صفريُّ الكمية يُملأ من عمود وحدته في الدوام —
 * إعادةُ إنتاجٍ لما كانت الكتابةُ المزدوجةُ ستكتبه، لا اختراعُ رقم.
 *
 * الاستعمال:  php tools/e02_bridge_backfill.php [--apply] [--limit=N]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$APPLY = in_array('--apply', $argv, true);
$LIMIT = 0;
foreach ($argv as $a) { if (strpos($a, '--limit=') === 0) { $LIMIT = intval(substr($a, 8)); } }

fwrite(STDOUT, "════ جسر الموروث E-02 — " . ($APPLY ? 'تنفيذ' : 'معاينة (--apply للتنفيذ)') . " ════\n");

/* صفوف الدوام المُطالَبة بجسرٍ سلفًا — لا تُمنح مرتين */
$claimed = array();
$r = mysqli_query($conn, "SELECT sync_uuid FROM unit_entries WHERE sync_uuid LIKE 'ts:%'");
while ($x = mysqli_fetch_row($r)) { $claimed[intval(substr($x[0], 3))] = true; }
fwrite(STDOUT, "جسورٌ قائمة: " . count($claimed) . "\n");

/* المرشّحون */
$sql = "SELECT ue.id, ue.company_id, ue.equipment_id, ue.entry_date, ue.unit_type, ue.qty,
               ue.operator_employee_id, ue.entry_no
          FROM unit_entries ue
         WHERE ue.state = 'sales_approved' AND (ue.sync_uuid IS NULL OR ue.sync_uuid = '')
         ORDER BY ue.id" . ($LIMIT > 0 ? " LIMIT " . $LIMIT : "");
$rows = array();
$r = mysqli_query($conn, $sql) or die('query: ' . mysqli_error($conn));
while ($x = mysqli_fetch_assoc($r)) { $rows[] = $x; }
fwrite(STDOUT, "بلا جسر: " . count($rows) . "\n\n");

$matchSt = $conn->prepare(
    "SELECT t.id, t.executed_hours, t.tons_count, t.meters_count, t.employee_id, t.shift
       FROM timesheet t
       JOIN operations op ON op.id = t.operator
      WHERE op.equipment = ? AND t.`date` = ? AND t.company_id = ?");
$qtyCol = array('hour' => 'executed_hours', 'ton' => 'tons_count', 'meter' => 'meters_count');

$bridged = 0; $healed = 0; $ambiguous = array(); $nomatch = 0; $lvl = array(1 => 0, 2 => 0, 3 => 0, 4 => 0);
foreach ($rows as $ue) {
    $eq = intval($ue['equipment_id']); $dt = strval($ue['entry_date']); $co = intval($ue['company_id']);
    $matchSt->bind_param('isi', $eq, $dt, $co);
    $matchSt->execute();
    $res = $matchSt->get_result();
    $cands = array();
    while ($t = $res->fetch_assoc()) { if (empty($claimed[intval($t['id'])])) { $cands[] = $t; } }

    $pick = null; $level = 0;
    if (count($cands) === 1) { $pick = $cands[0]; $level = 1; }
    elseif (count($cands) > 1) {
        // L2: تضييق بالمشغّل
        $emp = intval($ue['operator_employee_id']);
        $sub = array();
        foreach ($cands as $t) { if (intval($t['employee_id']) === $emp && $emp > 0) { $sub[] = $t; } }
        if (count($sub) === 1) { $pick = $sub[0]; $level = 2; }
        // L3: تضييقٌ بالكمية — كميةُ الصف بوحدة نوعه تساوي عمودَ الدوام المقابل
        // (مطابقةٌ حتميةٌ لا تخمين: الفريدُ بالقيمة الموجبة وحدَه يُجسَّر)
        if ($pick === null && isset($qtyCol[$ue['unit_type']]) && (float) $ue['qty'] > 0) {
            $qv = round((float) $ue['qty'], 2);
            $sub3 = array();
            foreach ($cands as $t) {
                if (abs(round((float) $t[$qtyCol[$ue['unit_type']]], 2) - $qv) < 0.005) { $sub3[] = $t; }
            }
            if (count($sub3) === 1) { $pick = $sub3[0]; $level = 3; }
        }
        // L4: تضييقٌ بالوردية — ue.shift الثنائية ↔ نصُّ ts النظيف
        // (day=صباحية · night=مسائية — قِيست القيم قبل الاعتماد: لا سواهما)
        if ($pick === null) {
            $want = ($ue['shift'] === 'night') ? 'مسائية' : 'صباحية';
            $sub4 = array();
            foreach ($cands as $t) {
                if (trim((string) $t['shift']) === $want) { $sub4[] = $t; }
            }
            if (count($sub4) === 1) { $pick = $sub4[0]; $level = 4; }
        }
    }

    if ($pick === null) {
        if (count($cands) === 0) { $nomatch++; }
        else { $ambiguous[] = array($ue['id'], $ue['entry_no'], $dt, $eq, count($cands)); }
        continue;
    }

    $tsId = intval($pick['id']);
    $claimed[$tsId] = true; // لا يُمنح لغيره داخل الدفعة نفسها
    $lvl[$level]++;
    $bridged++;

    // شفاء رأسٍ صفري من عمود وحدته
    $heal = null;
    if ((float) $ue['qty'] <= 0 && isset($qtyCol[$ue['unit_type']])) {
        $v = (float) $pick[$qtyCol[$ue['unit_type']]];
        if ($v > 0) { $heal = $v; $healed++; }
    }

    if ($APPLY) {
        if ($heal !== null) {
            $st = $conn->prepare("UPDATE unit_entries SET sync_uuid = ?, qty = ?
                                   WHERE id = ? AND (sync_uuid IS NULL OR sync_uuid = '')");
            $uuid = 'ts:' . $tsId; $ueId = intval($ue['id']);
            $st->bind_param('sdi', $uuid, $heal, $ueId);
        } else {
            $st = $conn->prepare("UPDATE unit_entries SET sync_uuid = ?
                                   WHERE id = ? AND (sync_uuid IS NULL OR sync_uuid = '')");
            $uuid = 'ts:' . $tsId; $ueId = intval($ue['id']);
            $st->bind_param('si', $uuid, $ueId);
        }
        if (!$st->execute()) { fwrite(STDOUT, "✘ UE-" . $ue['id'] . ": " . $st->error . "\n"); $bridged--; }
        $st->close();
    }
}
$matchSt->close();

if ($ambiguous) {
    $f = fopen(__DIR__ . '/../docs/E02_BRIDGE_AMBIGUOUS_ar.csv', 'w');
    fwrite($f, "\xEF\xBB\xBF");
    fputcsv($f, array('unit_entry_id', 'entry_no', 'entry_date', 'equipment_id', 'candidates'));
    foreach ($ambiguous as $a) { fputcsv($f, $a); }
    fclose($f);
}

fwrite(STDOUT, "الجسر: {$bridged} (L1={$lvl[1]} · L2={$lvl[2]} · L3كمية={$lvl[3]} · L4وردية={$lvl[4]}) · شفاءُ رأسٍ صفري: {$healed}\n");
fwrite(STDOUT, "غامض: " . count($ambiguous) . " (docs/E02_BRIDGE_AMBIGUOUS_ar.csv) · بلا مقابل: {$nomatch}\n");
fwrite(STDOUT, $APPLY ? "✔ نُفِّذ\n" : "— معاينة فقط\n");
