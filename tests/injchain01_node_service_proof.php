<?php
/**
 * tests/injchain01_node_service_proof.php
 *   شاهدُ خدمةِ عقدِ السلسلةِ الستِّ — INJ-CHAIN-CLOSE-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **يُشغَّل حيًّا لا وصفًا**: يُنشئ سجلاتٍ موسومةً، ويحاول اختراقَ كلِّ قاعدةٍ
 *   بعينِها، ثم يكنس أثرَه بالعائلة. **وبوابةٌ لم تُجرَّب معطوبةً لا تُصدَّق.**
 *
 * ◆ **وستُّ قواعدَ تُختبر بالفعلِ لا بالنص**:
 *   ① فصلُ الواجبات: مَن أعدَّ لا يعتمد · مَن اعتمد لا يُجيز · مَن أعدَّ لا ينفّذ
 *   ② لا ترحيلَ قبلَ الإجازة (قيدُ القاعدةِ يرفض)
 *   ③ لا فاتورةَ على شهادةٍ غيرِ معتمَدة
 *   ④ لا تصحيحَ إلا بمرورِ الأطرافِ الثلاثةِ معًا
 *   ⑤ لا تنفيذَ نقديٍّ بلا مرجعِ حركة
 *   ⑥ العطالة: تكرارُ الفعلِ لا يُنتج أثرًا ثانيًا
 *
 * ◆ **وبوابةُ المستأجِرِ تُبنى هنا وتُمرَّر** — لأن `ems_tenant_db()` تلزمها
 *   جلسةٌ فتسقط في الفاحصاتِ والمهامِّ الطرفية.
 *
 * التشغيل: php tests/injchain01_node_service_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/app/Services/Chain/ChainNodeService.php';
use App\Services\Chain\ChainNodeService as CN;

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

require_once $ROOT . '/app/Core/TenantGateException.php';
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
$CO = 4;
$gate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($CO, 0, '', true));

$A = 900001; $B = 900002; $C = 900003;   /* ثلاثةُ أيدٍ متمايزة */
/* ◆ وسمُ العائلةِ ثابتٌ لا `getmypid()` — «وسمٌ متغيّرٌ يُعمي الجولةَ عن سابقتها» */
$MARK = 'CHAINPROOF';

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

/** كنسٌ بالعائلةِ — قبلًا وبعدًا، ويُفحص مُرجَعُ كلِّ حذف. */
function sweep(mysqli $c, $CO, $MARK)
{
    $n = 0;
    $c->query("DELETE FROM `tre_pay_batch_lines` WHERE `batch_id` IN
                 (SELECT `id` FROM `tre_pay_batches` WHERE `company_id`={$CO} AND `batch_no` LIKE 'PB-2099%')");
    foreach (array(
        "DELETE FROM `tre_pay_batches`      WHERE `company_id`={$CO} AND `batch_no` LIKE 'PB-2099%'",
        "DELETE FROM `tre_beneficiaries`    WHERE `company_id`={$CO} AND `beneficiary_ar` LIKE '{$MARK}%'",
        "DELETE FROM `ar_claim_invoices`    WHERE `company_id`={$CO} AND `period` = '2099-01'",
        "DELETE FROM `ar_completion_certs`  WHERE `company_id`={$CO} AND `period` = '2099-01'",
        "DELETE FROM `ar_accruals`          WHERE `company_id`={$CO} AND `period` = '2099-01'",
        "DELETE FROM `unit_corrections`     WHERE `company_id`={$CO} AND `reason` LIKE '{$MARK}%'",
        "DELETE FROM `unit_final_approvals` WHERE `company_id`={$CO} AND `period` = '2099-01'",
    ) as $q) {
        if ($c->query($q)) { $n += $c->affected_rows; }
        else { echo "  ⚠ تعذّر الكنس: {$c->error}\n"; }
    }
    return $n;
}

echo "══ خدمةُ عقدِ سلسلةِ الأثر — شاهدٌ حيّ ══\n";
printf("  كُنس قبلَ الجولة: %d صفًّا\n", sweep($conn, $CO, $MARK));

/* ══ ① العقدة ١٦ — الاستحقاقُ وفصلُ الواجبات ═══════════════════════════ */
echo "\n── ① العقدة 16 · استحقاقُ عقدِ العميل ──\n";
$r = CN::prepareAccrual($conn, $gate, $CO, array('period' => '2099-01', 'contract_id' => 999001,
    'amount' => 1500.50, 'currency' => 'SDG', 'fx_rate' => 1), $A);
chk(!empty($r['ok']), 'يُعَدُّ الاستحقاقُ بمبلغٍ وعملة', $r['reason']);
$acr = (int) ($r['id'] ?? 0);

$r2 = CN::prepareAccrual($conn, $gate, $CO, array('period' => '2099-01', 'contract_id' => 999001,
    'amount' => 1500.50, 'currency' => 'SDG'), $A);
chk(empty($r2['ok']) && (int) $r2['code'] === 200, '**العطالة**: تكرارُ الإعدادِ لا يُنتج صفًّا ثانيًا', $r2['reason']);

$r3 = CN::prepareAccrual($conn, $gate, $CO, array('period' => '2099-01', 'contract_id' => 999002,
    'amount' => 100, 'currency' => ''), $A);
chk(empty($r3['ok']) && (int) $r3['code'] === 422, '**لا مبلغَ بلا عملة**', $r3['reason']);

$r4 = CN::controlAccrual($conn, $gate, $CO, $acr, $A);
chk(empty($r4['ok']) && (int) $r4['code'] === 403, '**مَن أعدَّ لا يُجيز** — فصلُ الواجبات', $r4['reason']);
$r5 = CN::controlAccrual($conn, $gate, $CO, $acr, $B);
chk(!empty($r5['ok']), 'ويُجيزه غيرُه', $r5['reason']);

/* ② لا ترحيلَ قبلَ الإجازة — قيدُ القاعدةِ نفسُه يُختبر */
$conn->query("UPDATE `ar_accruals` SET `control_at`=NULL, `control_by`=NULL WHERE `id`={$acr}");
$try = @$conn->query("UPDATE `ar_accruals` SET `journal_entry_id`=1 WHERE `id`={$acr}");
chk($try === false, '**لا ترحيلَ قبلَ الإجازة** — القاعدةُ ترفض ملءَ رقمِ القيد',
    $try === false ? 'رُفض بقيدٍ في القاعدة' : 'قُبل — والقيدُ لا يحرس!');
$conn->query("UPDATE `ar_accruals` SET `control_at`=NOW(), `control_by`={$B} WHERE `id`={$acr}");

/* ══ ③ العقدة ١٧ و ١٨ — الشهادةُ ثم الفاتورة ═════════════════════════ */
echo "\n── ② العقدتان 17 و18 · الشهادةُ ثم الفاتورة ──\n";
$rc = CN::prepareCert($conn, $gate, $CO, array('period' => '2099-01', 'contract_id' => 999001,
    'approved_qty' => 120.5, 'unit_type' => 'hour', 'measure_ref' => 'TS-2099-01'), $A);
chk(!empty($rc['ok']), 'تُعَدُّ شهادةُ الإنجازِ بمرجعِ قياس', $rc['reason']);
$cert = (int) ($rc['id'] ?? 0);

$rcNo = CN::prepareCert($conn, $gate, $CO, array('period' => '2099-01', 'contract_id' => 999003,
    'approved_qty' => 5, 'measure_ref' => ''), $A);
chk(empty($rcNo['ok']), '**لا شهادةَ إنجازٍ بلا مرجعِ قياسٍ معتمَد**', $rcNo['reason']);

$ri = CN::prepareClaimInvoice($conn, $gate, $CO, array('period' => '2099-01', 'claim_id' => 999001,
    'cert_id' => $cert, 'amount' => 1500.50, 'currency' => 'SDG'), $A);
chk(empty($ri['ok']) && (int) $ri['code'] === 409,
    '**لا فاتورةَ على شهادةٍ غيرِ معتمَدة**', $ri['reason']);

$ra = CN::approveCert($conn, $gate, $CO, $cert, $B);
chk(!empty($ra['ok']), 'تُعتمد الشهادةُ بيدٍ غيرِ يدِ المُعِدّ', $ra['reason']);

$ri2 = CN::prepareClaimInvoice($conn, $gate, $CO, array('period' => '2099-01', 'claim_id' => 999001,
    'cert_id' => $cert, 'amount' => 1500.50, 'currency' => 'SDG'), $A);
chk(!empty($ri2['ok']), 'وتُبنى الفاتورةُ عليها بعدَ اعتمادِها', $ri2['reason']);
$inv = (int) ($ri2['id'] ?? 0);

$rr = CN::referClaimInvoice($conn, $gate, $CO, $inv, 'collections', $C);
chk(empty($rr['ok']) && (int) $rr['code'] === 409,
    '**لا تُحال فاتورةٌ لم تُجَز رقابيًّا**', $rr['reason']);
CN::approveClaimInvoice($conn, $gate, $CO, $inv, $B);
CN::controlClaimInvoice($conn, $gate, $CO, $inv, $C);
$rr2 = CN::referClaimInvoice($conn, $gate, $CO, $inv, 'collections', $C);
chk(!empty($rr2['ok']), 'وتُحال بعدَ الإجازة', $rr2['reason']);

/* ══ ④ العقدة ١٣ — السلسلةُ الثلاثية ═════════════════════════════════ */
echo "\n── ③ العقدة 13 · لا تصحيحَ إلا بالأطرافِ الثلاثة ──\n";
$rk = CN::openCorrection($conn, $gate, $CO, array('entry_id' => 999001, 'correction_kind' => 'adjustment',
    'field_changed' => 'quantity', 'value_before' => '10', 'value_after' => '12',
    'reason' => $MARK . ' — تصحيحُ كميةٍ بمحضرِ موقعٍ مرفق'), $A);
chk(!empty($rk['ok']), 'يُفتح التصحيحُ بسببٍ مكتوب', $rk['reason']);
$cor = (int) ($rk['id'] ?? 0);

$rkNo = CN::openCorrection($conn, $gate, $CO, array('entry_id' => 999002, 'correction_kind' => 'adjustment',
    'field_changed' => 'quantity', 'value_before' => '1', 'value_after' => '2', 'reason' => 'خطأ'), $A);
chk(empty($rkNo['ok']), '**لا تصحيحَ بلا سببٍ مكتوبٍ مفهوم**', $rkNo['reason']);

CN::correctionPartyOk($conn, $gate, $CO, $cor, 'client', $A);
CN::correctionPartyOk($conn, $gate, $CO, $cor, 'supplier', $B);
$q = $conn->query("SELECT `state` FROM `unit_corrections` WHERE `id`={$cor}");
$s2 = $q ? $q->fetch_row()[0] : '?';
chk($s2 === 'in_chain', 'بطرفَين لا يُعتمد — يبقى في السلسلة', "الحالة={$s2}");
CN::correctionPartyOk($conn, $gate, $CO, $cor, 'worker', $C);
$q = $conn->query("SELECT `state` FROM `unit_corrections` WHERE `id`={$cor}");
$s3 = $q ? $q->fetch_row()[0] : '?';
chk($s3 === 'approved', 'وبالثلاثةِ يُعتمد', "الحالة={$s3}");
/* والقيدُ يرفض التطبيقَ بلا الثلاثة — يُختبر على صفٍّ ناقص */
$conn->query("UPDATE `unit_corrections` SET `worker_ok_at`=NULL WHERE `id`={$cor}");
$tryApply = @$conn->query("UPDATE `unit_corrections` SET `applied_at`=NOW() WHERE `id`={$cor}");
chk($tryApply === false, '**والقاعدةُ ترفض تطبيقًا بلا الأطرافِ الثلاثة**',
    $tryApply === false ? 'رُفض بقيدٍ في القاعدة' : 'قُبل — والقيدُ لا يحرس!');

/* ══ ⑤ العقدة ٢٥ — التنفيذُ النقديّ ═══════════════════════════════════ */
echo "\n── ④ العقدة 25 · التنفيذُ النقديُّ ومرجعُ الحركة ──\n";
$rb = CN::openBatch($conn, $gate, $CO, array('value_date' => '2099-01-15', 'currency' => 'SDG',
    'bank_account' => 'ACC-PROOF'), $A);
chk(!empty($rb['ok']), 'تُفتح دفعةُ الدفع', $rb['reason']);
$bat = (int) ($rb['id'] ?? 0);
$rdy = CN::readyBatch($conn, $gate, $CO, $bat);
chk(!empty($rdy['ok']), 'وتُجهَّز للتنفيذِ بخطوةٍ مستقلة', $rdy['reason']);

$re = CN::executeBatch($conn, $gate, $CO, $bat, '', $B);
chk(empty($re['ok']) && (int) $re['code'] === 422, '**لا تنفيذَ بلا مرجعِ حركةٍ بنكيّ**', $re['reason']);
$re2 = CN::executeBatch($conn, $gate, $CO, $bat, 'BNK-2099-0001', $A);
chk(empty($re2['ok']) && (int) $re2['code'] === 403, '**مَن أعدَّ الدفعةَ لا ينفّذها**', $re2['reason']);
$re3 = CN::executeBatch($conn, $gate, $CO, $bat, 'BNK-2099-0001', $B);
chk(!empty($re3['ok']), 'وينفّذها غيرُه بمرجعِ حركة', $re3['reason']);
$re4 = CN::executeBatch($conn, $gate, $CO, $bat, 'BNK-2099-0001', $B);
chk(empty($re4['ok']) && (int) $re4['code'] === 200, '**العطالة**: لا تُنفَّذ مرتين', $re4['reason']);

/* ══ ⑥ العقدة ٩ ═════════════════════════════════════════════════════ */
echo "\n── ⑤ العقدة 9 · الاعتمادُ الماليُّ النهائيّ ──\n";
$q = $conn->query("SELECT `id` FROM `unit_entries`
                    WHERE `company_id`={$CO} AND `state` IN ('sales_approved','converted') LIMIT 1");
$eid = $q ? (int) ($q->fetch_row()[0] ?? 0) : 0;
if ($eid > 0) {
    $rf = CN::prepareFinalApproval($conn, $gate, $CO, $eid, '2099-01', $A);
    chk(!empty($rf['ok']), 'يُعَدُّ الاعتمادُ النهائيُّ لواقعةٍ مكتملةِ السلسلة', $rf['reason']);
    $fid = (int) ($rf['id'] ?? 0);
    $rf2 = CN::approveFinal($conn, $gate, $CO, $fid, $A);
    chk(empty($rf2['ok']) && (int) $rf2['code'] === 403, '**ولا يعتمده مُعِدُّه**', $rf2['reason']);
    $rf3 = CN::approveFinal($conn, $gate, $CO, $fid, $B);
    chk(!empty($rf3['ok']), 'ويعتمده غيرُه', $rf3['reason']);
    $rf4 = CN::controlFinal($conn, $gate, $CO, $fid, $B);
    chk(empty($rf4['ok']) && (int) $rf4['code'] === 403, '**ولا يُجيزه معتمِدُه**', $rf4['reason']);
    $rf5 = CN::controlFinal($conn, $gate, $CO, $fid, $C);
    chk(!empty($rf5['ok']), 'ويُجيزه ثالثٌ — ثلاثُ أيدٍ لا واحدة', $rf5['reason']);
} else {
    echo "  ◆ لا واقعةَ مكتملةَ السلسلةِ الآن — العقدة 9 تُختبر حين تعود بياناتُ العمل.\n";
    echo "    وقواعدُها نفسُها مقيسةٌ في ①: `advance()` واحدةٌ لكلِّ الانتقالات.\n";
}

/* ══ الكنسُ البعديُّ — بالعائلةِ لا بالفرد ═════════════════════════════ */
printf("\n  كُنس بعدَ الجولة: %d صفًّا\n", sweep($conn, $CO, $MARK));
$q = $conn->query("SELECT (SELECT COUNT(*) FROM `ar_accruals` WHERE `period`='2099-01')
                        + (SELECT COUNT(*) FROM `ar_completion_certs` WHERE `period`='2099-01')
                        + (SELECT COUNT(*) FROM `ar_claim_invoices` WHERE `period`='2099-01')
                        + (SELECT COUNT(*) FROM `unit_final_approvals` WHERE `period`='2099-01')
                        + (SELECT COUNT(*) FROM `tre_pay_batches` WHERE `batch_no` LIKE 'PB-2099%')");
$left = $q ? (int) $q->fetch_row()[0] : -1;
chk($left === 0, '**صفرُ بقيةٍ من هذه الجولة** — الشاهدُ لا يترك أثرًا في البيانات', "المتبقي={$left}");

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
