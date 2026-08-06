<?php
/**
 * tools/e01_convert_sweep.php — كنسُ التحويل المالي على السلسلة (E-01×E-02)
 * ───────────────────────────────────────────────────────────────────────────
 * يمرّ على طابور التحويل (سلسلةٌ في sales_approved بجسرها) دفعةً دفعةً عبر
 * **الخدمة الواحدة** UnitConversionService — كلُّ يومٍ معاملتُه: مروحةُ أثرٍ
 * ثم ختمُ السلسلة. المتعذّر يبقى بأسبابه (لا تلفيق) ويُصدَّر تقريرًا.
 *
 * الاستعمال:  php tools/e01_convert_sweep.php [--apply] [--limit=N]
 *   بلا --apply: عدّ الطابور وتفصيل الجاهز/المتعذّر دون كتابة.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Finance/fin_helpers.php';
require_once __DIR__ . '/../app/Services/Finance/UnitConversionService.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$APPLY = in_array('--apply', $argv, true);
$LIMIT = 0;
foreach ($argv as $a) { if (strpos($a, '--limit=') === 0) { $LIMIT = intval(substr($a, 8)); } }

fwrite(STDOUT, "════ كنس التحويل E-01 — " . ($APPLY ? 'تنفيذ' : 'معاينة (--apply للتنفيذ)') . " ════\n");

$report = array();
$totConv = 0; $totEff = 0; $totSkip = 0; $totFail = 0; $reasonTally = array();
$batchNo = 0;

/* طابورُ CLI يقرأ السلسلةَ مباشرةً (نمطُ أدوات الأحزمة — لا جلسةَ هنا فبوابةُ
   الجلسة fail-closed)؛ العزلُ يتولاه convertBatch ببوابة شركةِ كلِّ صف. */
function sweep_queue_ts_ids(mysqli $conn, $limit, $afterUeId)
{
    // ترقيمٌ بمفتاح ue.id (keyset): المتعذّر يبقى في الحالة نفسها فلولا
    // الترقيم لدار الكنسُ على الصفوف ذاتها إلى الأبد.
    // التحويل على السكّة = **الحالة** لا الروابط: صفٌّ sales_approved له مالٌ
    // سابق (انحراف سكّتين) يدخل الطابورَ ليُتبنّى ويُختم — لا يُستثنى فيبقى منحرفًا.
    $sql = "SELECT ue.id, CAST(SUBSTRING(ue.sync_uuid, 4) AS UNSIGNED) AS ts_id
              FROM unit_entries ue
             WHERE ue.state = 'sales_approved' AND ue.sync_uuid LIKE 'ts:%'
               AND ue.id > " . intval($afterUeId) . "
             ORDER BY ue.id ASC
             LIMIT " . intval($limit);
    $out = array('ids' => array(), 'last' => $afterUeId);
    $r = mysqli_query($conn, $sql);
    if (!$r) { fwrite(STDOUT, "✘ طابور: " . mysqli_error($conn) . "\n"); return $out; }
    while ($x = mysqli_fetch_row($r)) {
        $out['ids'][] = intval($x[1]);
        $out['last'] = intval($x[0]);
    }
    return $out;
}
$unbridged = 0;
$r = mysqli_query($conn, "SELECT COUNT(*) FROM unit_entries WHERE state='sales_approved' AND (sync_uuid IS NULL OR sync_uuid='')");
if ($r && ($x = mysqli_fetch_row($r))) { $unbridged = (int) $x[0]; }

$afterUeId = 0;
while (true) {
    $batchNo++;
    $page = sweep_queue_ts_ids($conn, 500, $afterUeId);
    $tsIds = $page['ids'];
    $afterUeId = $page['last'];
    if (!$tsIds) { break; }
    if ($LIMIT > 0) { $tsIds = array_slice($tsIds, 0, max(0, $LIMIT - ($totConv + $totSkip + $totFail))); }
    if (!$tsIds) { break; }

    if (!$APPLY) {
        fwrite(STDOUT, "طابور الدفعة {$batchNo}: " . count($tsIds) . " يومًا بجسر · {$unbridged} بلا جسر\n");
        break; // معاينة: دفعة واحدة تكفي للعدّ
    }

    $res = \App\Services\Finance\UnitConversionService::convertBatch($conn, $tsIds, 0);
    $conv = $res['converted'];
    $totConv += $conv; $totEff += $res['effects'];
    foreach ($res['rows'] as $tsId => $r) {
        $status = $r['converted'] ? 'converted' : ($r['ok'] ? 'skipped' : 'failed');
        if ($status === 'skipped') { $totSkip++; }
        if ($status === 'failed')  { $totFail++; }
        if (!$r['converted']) {
            $key = mb_substr((string) $r['reason'], 0, 80);
            $reasonTally[$key] = ($reasonTally[$key] ?? 0) + 1;
        }
        $report[] = array($tsId, $status, $r['effects'], $r['adopted'], $r['reason']);
    }
    fwrite(STDOUT, "دفعة {$batchNo}: حُوّل {$conv}/" . count($tsIds) . " (آثار {$res['effects']})\n");

    if ($LIMIT > 0 && ($totConv + $totSkip + $totFail) >= $LIMIT) { break; }
}

if ($APPLY && $report) {
    $f = fopen(__DIR__ . '/../docs/E01_CONVERSION_SWEEP_ar.csv', 'w');
    fwrite($f, "\xEF\xBB\xBF");
    fputcsv($f, array('timesheet_id', 'result', 'effects', 'adopted', 'reason'));
    foreach ($report as $row) { fputcsv($f, $row); }
    fclose($f);
}

fwrite(STDOUT, "──────────────────────────────────────────────\n");
fwrite(STDOUT, "حُوّل: {$totConv} يومًا · آثار: {$totEff} · متعذّر: {$totSkip} · فشل: {$totFail}\n");
if ($reasonTally) {
    arsort($reasonTally);
    fwrite(STDOUT, "أعلى أسباب التعذّر:\n");
    $i = 0;
    foreach ($reasonTally as $why => $n) {
        fwrite(STDOUT, "  {$n} × {$why}\n");
        if (++$i >= 6) { break; }
    }
}
if ($APPLY) { fwrite(STDOUT, "التقرير: docs/E01_CONVERSION_SWEEP_ar.csv\n"); }
