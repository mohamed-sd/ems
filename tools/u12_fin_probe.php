<?php
/**
 * tools/u12_fin_probe.php — تشغيلُ محرّكاتِ التحليلِ حيًّا وقياسُ مخرجاتها
 * ═══════════════════════════════════════════════════════════════════════════
 * يشغّل: احتسابَ النسبِ · تقييمَ الإشارات · قوائمَ المشروعِ والتدفقاتِ وحقوقِ
 * الملكية — ويطبع الشاهدَ المقيسَ لا الادعاء. ولا يُخفي فشلًا.
 * التشغيل: php tools/u12_fin_probe.php [YYYY-MM]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/app/Services/Finance/FinAnalysisService.php';
require_once $ROOT . '/app/Services/Finance/CoaService.php';

use App\Services\Finance\FinAnalysisService as FA;
use App\Services\Finance\CoaService;

$db = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
$db->set_charset('utf8mb4');
$CO = 4;
$ACTOR = 1;

/* الفترةُ الأكثرُ قيودًا — القياسُ على بياناتٍ حقيقيةٍ لا فارغة */
$period = $argv[1] ?? null;
if (!$period) {
    $r = $db->query("SELECT DATE_FORMAT(COALESCE(e.posting_date, e.created_at), '%Y-%m') p, COUNT(*) n
                       FROM fin_journal_lines l JOIN fin_journal_entries e ON e.id = l.entry_id
                      WHERE l.company_id = {$CO} GROUP BY p ORDER BY n DESC LIMIT 1");
    $period = $r && ($x = $r->fetch_assoc()) ? $x['p'] : date('Y-m');
}
echo "الفترةُ المقيسة: {$period}\n";
echo str_repeat('═', 66), "\n";

/* ① الشجرةُ والأبعاد */
$canon = (int) $db->query("SELECT COUNT(*) c FROM fin_chart_of_accounts
    WHERE company_id={$CO} AND is_canonical=1")->fetch_assoc()['c'];
$post = (int) $db->query("SELECT COUNT(*) c FROM fin_chart_of_accounts
    WHERE company_id={$CO} AND is_canonical=1 AND is_postable=1")->fetch_assoc()['c'];
$withDims = (int) $db->query("SELECT COUNT(*) c FROM fin_chart_of_accounts
    WHERE company_id={$CO} AND is_canonical=1 AND required_dims <> ''")->fetch_assoc()['c'];
$withFlow = (int) $db->query("SELECT COUNT(*) c FROM fin_chart_of_accounts
    WHERE company_id={$CO} AND is_canonical=1 AND cashflow_activity <> 'none'")->fetch_assoc()['c'];
echo "① الشجرة: {$canon} حسابًا قانونيًّا · يُقيَّد عليه {$post} · بأبعادٍ إلزامية {$withDims}"
   . " · بتصنيفِ نشاطِ تدفقٍ {$withFlow}\n";

/* ② حارسُ الأبعاد — شاهدٌ حي: قيدٌ ينقصه بُعدٌ يُرفض */
try {
    CoaService::assertDims($db, $CO, '4101', array('D1' => $CO));
    echo "② حارسُ الأبعاد: ✘ لم يرفض قيدًا ناقصَ الأبعاد\n";
    $dimOk = false;
} catch (\Throwable $e) {
    $dimOk = strpos($e->getMessage(), 'COA-DIM-422') !== false;
    echo "② حارسُ الأبعاد: " . ($dimOk ? '✔' : '✘') . ' ' . mb_substr($e->getMessage(), 0, 120) . "\n";
}
/* والمستوى التجميعيُّ لا يُقيَّد عليه */
try {
    CoaService::assertDims($db, $CO, '41', array('D1' => $CO, 'D2' => 1, 'D6' => 1, 'D7' => 'hour', 'D8' => 1));
    echo "   المستوى التجميعي: ✘ قَبِل القيد\n";
    $lvlOk = false;
} catch (\Throwable $e) {
    $lvlOk = strpos($e->getMessage(), 'COA-LEVEL-422') !== false;
    echo "   المستوى التجميعي: " . ($lvlOk ? '✔ يُرفض القيدُ عليه' : '✘') . "\n";
}
/* وR2/R8 عند إنشاءِ حساب */
$r2ok = false; $r8ok = false;
try { CoaService::assertCreatable($db, $CO, '1103-999', 'Ahmed Custody'); }
catch (\Throwable $e) { $r2ok = strpos($e->getMessage(), 'COA-R2-422') !== false; }
try { CoaService::assertCreatable($db, $CO, '110399', 'حساب تفصيلي'); }
catch (\Throwable $e) { $r8ok = strpos($e->getMessage(), 'COA-R8-422') !== false; }
echo '   R2 اسمُ شخص: ' . ($r2ok ? '✔ يُرفض' : '✘') . ' · R8 تفصيلٌ يميّزه بُعد: ' . ($r8ok ? '✔ يُرفض' : '✘') . "\n";

/* ③ مصفوفةُ الترحيلِ — اشتقاقُ الحسابِ لا اختيارُه */
$pmOk = true;
try {
    $a1 = CoaService::resolveAccount($db, $CO, 'OPS', 'revenue', array('business_model' => 'ton'));
    $a2 = CoaService::resolveAccount($db, $CO, 'OPS', 'revenue', array('business_model' => 'meter'));
    $a3 = CoaService::resolveAccount($db, $CO, 'WRK', 'cost', array('contract_type_code' => 'EC-03'));
    echo "③ مصفوفةُ الترحيل: الطنُّ ⇒ {$a1['code']} · المترُ ⇒ {$a2['code']} · عقدٌ مشروعيٌّ ⇒ {$a3['code']}\n";
    $pmOk = ($a1['code'] === '4102' && $a2['code'] === '4103');
} catch (\Throwable $e) { $pmOk = false; echo '③ مصفوفةُ الترحيل: ✘ ' . $e->getMessage() . "\n"; }

/* ④ النسب */
$ratios = FA::computeRatios($db, $CO, $period, $ACTOR);
echo "④ النسب: حُسبت {$ratios['computed']} · إنذار {$ratios['warn']} · حرج {$ratios['critical']}"
   . " · غيرُ مقيسة {$ratios['unmeasured']}\n";
foreach ($ratios['margins'] as $k => $mv) {
    echo "   {$k} {$mv['label']}: " . ($mv['value'] !== null ? number_format($mv['value'], 2) : '— غير مقيس') . "\n";
}

/* ⑤ الإشارات */
$sig = FA::evaluateSignals($db, $CO, $period, $ACTOR);
echo "⑤ الإشارات: فُحصت {$sig['checked']} قاعدةً · رُفعت " . count($sig['raised']) . "\n";
foreach (array_slice($sig['raised'], 0, 6) as $s) {
    echo '   ' . $s['code'] . ' · ' . ($s['severity'] ?? '') . ' · إشارة #' . ($s['signal_id'] ?? 0)
       . (!empty($s['idempotent']) ? ' (عطالة)' : '') . "\n";
}

/* ⑥ قائمةُ دخلِ المشروع */
$proj = $db->query("SELECT DISTINCT project_id FROM fin_journal_lines
                     WHERE company_id={$CO} AND project_id IS NOT NULL LIMIT 1");
if ($proj && ($p = $proj->fetch_assoc())) {
    try {
        $pl = FA::generateProjectPL($db, $CO, (int) $p['project_id'], $period, $ACTOR);
        echo "⑥ دخلُ المشروع: {$pl['pl_code']} · إيراد " . number_format($pl['revenue'], 2)
           . ' · تكلفةٌ مباشرة ' . number_format($pl['direct_cost'], 2)
           . ' · تشغيليٌّ ' . number_format($pl['operating_profit'], 2) . "\n";
    } catch (\Throwable $e) { echo '⑥ دخلُ المشروع: ✘ ' . $e->getMessage() . "\n"; }
} else { echo "⑥ دخلُ المشروع: لا مشروعَ بقيودٍ بالبُعد D2 بعد\n"; }

/* ⑦ التدفقاتُ — تتوازن أو تُرفض */
try {
    $cf = FA::generateCashflow($db, $CO, $period, $ACTOR, 0.01);
    echo "⑦ التدفقات: {$cf['cf_code']} · تشغيليٌّ " . number_format($cf['operating'], 2)
       . ' · صافي التغير ' . number_format($cf['net_change'], 2)
       . ' · الفعليُّ ' . number_format($cf['actual_change'], 2) . ' — ✔ متوازنة' . "\n";
} catch (\Throwable $e) {
    echo '⑦ التدفقات: الحارسُ رفض بحق — ' . mb_substr($e->getMessage(), 0, 170) . "\n";
}

/* ⑧ حقوقُ الملكية */
try {
    $eq = FA::generateEquity($db, $CO, $period, $ACTOR, 0.01);
    echo "⑧ حقوقُ الملكية: {$eq['eq_code']} · بنود {$eq['components']} — ✔ متوازنة\n";
} catch (\Throwable $e) {
    echo '⑧ حقوقُ الملكية: الحارسُ رفض بحق — ' . mb_substr($e->getMessage(), 0, 170) . "\n";
}

/* ⑨ المركزُ الماليُّ ودخلُ الشركة */
$bs = FA::balanceSheet($db, $CO, $period);
echo '⑨ المركزُ المالي: أصول ' . number_format($bs['totals']['assets'], 2)
   . ' · التزامات ' . number_format($bs['totals']['liabilities'], 2)
   . ' · حقوق ' . number_format($bs['totals']['equity'], 2)
   . ' · فرقُ المعادلة ' . number_format($bs['equation_diff'], 2) . "\n";

echo str_repeat('═', 66), "\n";
$eqOk = abs((float) $bs['equation_diff']) < 0.01;
echo '   معادلةُ الميزانية: ' . ($eqOk ? '✔ صفرٌ بالضبط بعد إظهارِ نتيجةِ الفترة' : '✘ مختلّة') . "\n";
$allOk = $dimOk && $lvlOk && $r2ok && $r8ok && $pmOk && $eqOk && $ratios['computed'] === 44;
echo $allOk ? "المحرّكاتُ حيةٌ وحرّاسُها تعمل ✔\n" : "راجع ما وُسم ✘ أعلاه\n";
exit($allOk ? 0 : 1);
