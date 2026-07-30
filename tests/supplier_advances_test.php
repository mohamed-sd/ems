<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-12 — اختبار قبول: بوابةُ سلفيات الموردين (ENT-02 §3 · §7)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/supplier_advances_test.php
 *
 * ما يُثبته:
 *   ① **بوابةٌ واحدةٌ بمستندٍ إلزامي**: سلفةٌ بلا سند → 422 · وبأنواعها الثلاثة
 *      نصًّا (نقدًا · نيابةً · **عهدةً**).
 *   ② «فصلُ اليدين»: من أعدّ لا يعتمد → 403.
 *   ③ **الرصيدُ مولَّدٌ لا يُكتب**: كتابتُه ترفض الصفَّ كلَّه.
 *   ④ **القسطُ بندٌ في التسوية بمستنده** — «المقاصّةُ الظاهرة».
 *   ⑤ **الاستردادُ باعتماد التسوية لا بتوليدها**: المسودةُ نيّةٌ والمعتمَدةُ واقعة.
 *   ⑥ **UQ(سلفة × تسوية)**: إعادةُ التطبيق لا تسترد مرتين.
 *   ⑦ الرصيدُ ينقص حتى يصفر فتُقفل السلفة `settled` ولا تعود بندًا.
 *   ⑧ الوصلان في `SettlementService` (التوليدُ يقرأ · والاعتمادُ يسترد).
 *
 * البذرُ معزول: سلفٌ بمستندات M12T وتسوياتٌ M12T — تُكنس كاملةً.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Settlement/SupplierAdvanceService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Settlement\SupplierAdvanceService as SAS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $AUTHOR = 999996; $APPROVER = 999997;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $AUTHOR, '', true));

$teardown = function () use ($conn) {
    $conn->query("DELETE r FROM supplier_advance_recoveries r
                   JOIN supplier_advance_requests a ON a.id = r.advance_id
                  WHERE a.doc_ref LIKE 'M12T%'");
    $conn->query("DELETE FROM supplier_advance_requests WHERE doc_ref LIKE 'M12T%'");
    // ⚠ كنسُ **كل** أسطر التسوية لا أسطرَ السلف وحدَها: القيدُ من السطر إلى
    //   رأسه يمنع حذفَ الرأس ما بقي سطرٌ — فتنجو التسويةُ ويصطدم التشغيلُ
    //   التالي بمفتاح `settlement_no` الفريد **صامتًا** (config.php لا يرمي).
    $conn->query("DELETE FROM settlement_lines
                   WHERE settlement_id IN (SELECT id FROM settlements WHERE settlement_no LIKE 'M12T%')");
    $conn->query("DELETE FROM settlements WHERE settlement_no LIKE 'M12T%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-12 — بوابةُ سلفيات الموردين ══\n");

head('البذر');
$SUP = intval($conn->query("SELECT id FROM suppliers WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
check($SUP > 0, "موردٌ من السجل الحي #{$SUP}");

/** تسويةٌ صوريةٌ ببندِ سلفةٍ — تُرجع معرّفَها، و**تُعلن فشلَها** بدل أن يمضي صامتًا. */
$mkSettlement = function ($no, $from, $to, $advId, $amount) use ($conn, $CO, $SUP, $AUTHOR) {
    $okH = $conn->query("INSERT INTO settlements (company_id, settlement_no, party_type, party_ref,
        party_name, period_from, period_to, currency, gross_amount, charges_amount, net_amount,
        state, prepared_by) VALUES ({$CO}, '{$no}', 'supplier', {$SUP}, 'M12T', '{$from}', '{$to}',
        'SDG', 5000, {$amount}, " . (5000 - $amount) . ", 'review', {$AUTHOR})");
    if (!$okH) { bad('تعذّر بذرُ التسوية ' . $no . ': ' . $conn->error); return 0; }
    $sid = intval($conn->insert_id);
    $okL = $conn->query("INSERT INTO settlement_lines (company_id, settlement_id, line_kind, charge_type,
        source_kind, source_ref, description, work_date, amount, currency, base_amount)
        VALUES ({$CO}, {$sid}, 'charge', 'advance', 'supplier_advance', '{$advId}',
        'قسطُ سلفة', '{$to}', {$amount}, 'SDG', {$amount})");
    if (!$okL) { bad('تعذّر بذرُ سطر التسوية: ' . $conn->error); return 0; }
    return $sid;
};

// ═══ ① البوابةُ والمستند ═══
head('① «بوابةٌ واحدةٌ … بمستندٍ وجدولِ استرداد»');
$r = SAS::open($conn, $gate, $CO, array(
    'supplier_id' => $SUP, 'amount' => 1000, 'doc_ref' => '', 'issued_date' => '2051-01-05'), $AUTHOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], 'لا يُحمَّل') !== false,
      'سلفةٌ **بلا سند** → 422 بنصّ §3');

$r = SAS::open($conn, $gate, $CO, array(
    'supplier_id' => $SUP, 'advance_type' => 'gift', 'amount' => 1000,
    'doc_ref' => 'M12T/X', 'issued_date' => '2051-01-05'), $AUTHOR);
check(!$r['ok'] && $r['code'] === 422, 'ونوعٌ خارج الثلاثة → 422');

$made = array();
foreach (array('cash' => 900, 'on_behalf' => 300, 'custody' => 600) as $type => $amt) {
    $r = SAS::open($conn, $gate, $CO, array(
        'supplier_id' => $SUP, 'advance_type' => $type, 'amount' => $amt,
        'doc_ref' => 'M12T/' . $type, 'issued_date' => '2051-01-05',
        'installments_count' => 3, 'installment_amount' => round($amt / 3, 2)), $AUTHOR);
    if (!empty($r['ok'])) { $made[$type] = intval($r['advance_id']); }
}
check(count($made) === 3, 'والأنواعُ الثلاثةُ تُفتح (نقدًا · نيابةً · **عهدةً**)');
$ADV = $made['cash'];

// ═══ ③ الرصيدُ مولَّد ═══
head('③ «الرصيدُ ظاهرٌ دائمًا» — ومولَّدٌ لا يُكتب');
$b = $conn->query("SELECT balance, recovered FROM supplier_advance_requests WHERE id={$ADV}")->fetch_assoc();
check(abs(floatval($b['balance']) - 900.0) < 0.005, 'الرصيدُ = 900 (المبلغ − المستردّ) مولَّدًا');
$conn->query("INSERT INTO supplier_advance_requests (company_id, supplier_id, advance_type, amount,
              doc_ref, issued_date, installments_count, installment_amount, recovered, state, balance)
              VALUES ({$CO}, {$SUP}, 'cash', 100, 'M12T/RAW', '2051-01-05', 1, 100, 0, 'active', 100)");
$leak = intval($conn->query("SELECT COUNT(*) n FROM supplier_advance_requests
                              WHERE doc_ref='M12T/RAW'")->fetch_assoc()['n']);
check($leak === 0, 'وكتابةُ `balance` ترفض الصفَّ كلَّه — لا انحرافَ بين رصيدٍ وحركته');

// ═══ ② فصلُ اليدين ═══
head('② «فصلُ اليدين»');
$r = SAS::approve($conn, $gate, $CO, $ADV, $AUTHOR);
check(!$r['ok'] && $r['code'] === 403, 'من أعدّ لا يعتمد → **403**');
$r = SAS::approve($conn, $gate, $CO, $ADV, $APPROVER);
check($r['ok'], 'ومعتمِدٌ آخر: تمرّ');
foreach ($made as $t => $id) { if ($id !== $ADV) { SAS::approve($conn, $gate, $CO, $id, $APPROVER); } }

// ═══ ④ القسطُ بندًا في التسوية ═══
head('④ القسطُ **بندٌ في التسوية بمستنده**');
$lines = SAS::chargeLines($gate, $SUP, '2051-02-01', '2051-02-28');
check(count($lines) === 3, 'ثلاثةُ بنودٍ (سلفةٌ لكل نوع)');
$cashLine = null;
foreach ($lines as $l) { if (strpos($l['description'], 'M12T/cash') !== false) { $cashLine = $l; } }
check($cashLine && abs($cashLine['amount'] - 300.0) < 0.005
      && $cashLine['charge_type'] === 'advance' && $cashLine['source_kind'] === 'supplier_advance',
      'وقسطُ النقدية 300 (900÷3) بنوعه ومصدره');
check($cashLine && strpos($cashLine['description'], 'الرصيدُ قبلَه 900') !== false,
      'والبيانُ يحمل **سندَه ورصيدَه** — «المقاصّةُ الظاهرة»');

// ═══ ⑤ الاستردادُ بالاعتماد لا بالتوليد ═══
head('⑤ الاستردادُ **باعتماد التسوية** لا بتوليدها');
$b = $conn->query("SELECT recovered FROM supplier_advance_requests WHERE id={$ADV}")->fetch_assoc();
check(abs(floatval($b['recovered']) - 0.0) < 0.005,
      'بعد التوليد (بندٌ ظاهر): المستردُّ **صفر** — المسودةُ نيّةٌ لا واقعة');

// ⚠ لكل تسويةٍ **فترتُها الخاصة**: `uq_settlement_party_period` يمنع تسويتين
//   للطرف نفسِه بالفترة نفسِها — وهو قيدٌ سليمٌ يحرس «تسويةٌ واحدةٌ للفترة».
$STL = $mkSettlement('M12T-STL-1', '2051-02-01', '2051-02-28', $ADV, 300);
check($STL > 0, "تسويةٌ صوريةٌ ببندِ السلفة (#{$STL})");
$rec = SAS::applyRecoveries($conn, $gate, $CO, $STL, $APPROVER);
check($rec['applied'] === 1 && abs($rec['total'] - 300.0) < 0.005, 'وباعتمادها: استُرد 300');
$b = $conn->query("SELECT recovered, balance, state FROM supplier_advance_requests WHERE id={$ADV}")->fetch_assoc();
check(abs(floatval($b['recovered']) - 300.0) < 0.005 && abs(floatval($b['balance']) - 600.0) < 0.005
      && $b['state'] === 'active', 'والرصيدُ 900 − 300 = **600** والحالةُ نشطة');

// ═══ ⑥ العطالة ═══
head('⑥ **UQ(سلفة × تسوية)** — إعادةُ التطبيق لا تسترد مرتين');
$rec2 = SAS::applyRecoveries($conn, $gate, $CO, $STL, $APPROVER);
$b2 = $conn->query("SELECT recovered FROM supplier_advance_requests WHERE id={$ADV}")->fetch_assoc();
check($rec2['applied'] === 0 && abs(floatval($b2['recovered']) - 300.0) < 0.005,
      'إعادةُ التطبيق: صفرُ استردادٍ جديد والمستردُّ ما زال 300');
$cnt = intval($conn->query("SELECT COUNT(*) n FROM supplier_advance_recoveries
                             WHERE advance_id={$ADV} AND settlement_id={$STL}")->fetch_assoc()['n']);
check($cnt === 1, 'وصفُّ الاسترداد واحدٌ لا اثنان');

// ═══ ⑦ حتى الصفر ═══
head('⑦ «الرصيدُ ينقص» حتى يصفر');
foreach (array(3, 4) as $i) {          // مارسُ وأبريل — لكلٍّ فترتُه
    $s = $mkSettlement('M12T-STL-' . $i, '2051-0' . $i . '-01', '2051-0' . $i . '-28', $ADV, 300);
    if ($s > 0) { SAS::applyRecoveries($conn, $gate, $CO, $s, $APPROVER); }
}
$b3 = $conn->query("SELECT balance, state FROM supplier_advance_requests WHERE id={$ADV}")->fetch_assoc();
check(abs(floatval($b3['balance']) - 0.0) < 0.005 && $b3['state'] === 'settled',
      'ثلاثةُ أقساطٍ ⇒ الرصيدُ صفرٌ والحالةُ **settled**');
$after = SAS::chargeLines($gate, $SUP, '2051-05-01', '2051-05-31');
$stillCash = false;
foreach ($after as $l) { if (strpos($l['description'], 'M12T/cash') !== false) { $stillCash = true; } }
check(!$stillCash, 'والمستردَّةُ لا تعود بندًا في تسويةٍ لاحقة');

// ═══ ⑧ الوصلان ═══
head('⑧ الوصلان في موضعيهما');
$src = file_get_contents(dirname(__DIR__) . '/app/Services/Settlement/SettlementService.php');
check(strpos($src, 'SupplierAdvanceService::chargeLines') !== false,
      '① التوليدُ يقرأ أقساطَ السلف بندًا (collectLines)');
check(strpos($src, 'SupplierAdvanceService::applyRecoveries') !== false,
      '② والاعتمادُ يطبّق الاسترداد (approve)');
$bal = SAS::openBalance($gate, $SUP);
check(abs($bal - 900.0) < 0.005,
      "«رصيدُها ظاهرٌ في بطاقته دائمًا»: المفتوحُ = 300 (نيابةً) + 600 (عهدة) = {$bal}");

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
