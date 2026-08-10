<?php
/**
 * tools/fix_cs12_manual.php — أسبابُ التجاهلِ الباقيةُ، مكتوبةً بيدٍ لا مولَّدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ما بقي بعد الجولاتِ الثلاثِ الآليةِ كتلٌ متعددةُ الأسطرِ لا نصَّ فيها يُستخرَج.
 * وهذه بالذاتِ **لا تُترك لآلة**: السببُ حكمُ نطاقٍ لا نمطُ نص.
 *
 * ◆ والنمطُ الجامعُ لأكثرِها واحد: **حلقةٌ على عناصرَ مستقلة** — فشلُ عنصرٍ
 *   لا يصحُّ أن يُسقط الدفعةَ كلَّها، فيُحصى ويُستأنَف. وهذا تجاهلٌ صائبٌ
 *   بشرطِ إعلانه، وهو ما يجري هنا.
 *
 * المفتاح: ملفٌّ ⇐ [ترتيبُ الظهورِ داخلَه ⇐ السبب]. والترتيبُ يُحسب على
 * نداءاتِ `ems_catch_ignored(..., '')` الفارغةِ وحدَها.
 *
 * التشغيل: php tools/fix_cs12_manual.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);

$REASONS = array(
    'app/Services/Contract/ContractSignedEffects.php' => array(
        'إنشاءُ حاويةِ العقدِ عند التوقيع فشل — التوقيعُ قائمٌ والحاويةُ تُستدرَك بإعادةِ التشغيل',
        'تسجيلُ التزامِ العقدِ فشل — التوقيعُ قائمٌ والالتزامُ يُستدرَك بإعادةِ التشغيل',
    ),
    'app/Services/Contract/PriceAdjustmentService.php' => array(
        'مراجعةُ سعرِ بندٍ واحدٍ فشلت — بقيةُ البنودِ تُراجَع، والفاشلُ يبقى بسعرِه القديم',
    ),
    'app/Services/Finance/PeriodicEventService.php' => array(
        'مخصصُ صيانةِ معدةٍ واحدةٍ فشل — بقيةُ المعداتِ تستمرّ، والفاشلُ يُستدرَك بالدورةِ التالية',
        'استحقاقُ قسطِ تمويلٍ واحدٍ فشل — بقيةُ الأقساطِ تستمرّ، والفاشلُ يُستدرَك بالدورةِ التالية',
    ),
    'app/Services/Finance/UnitConversionService.php' => array(
        'تحويلُ سلسلةِ وحدةٍ واحدةٍ فشل — بقيةُ السلاسلِ تُحوَّل، والفاشلةُ تبقى غيرَ محوَّلة',
    ),
    'app/Services/Operations/DailyPlanService.php' => array(
        'سطرُ الخطةِ موجودٌ سلفًا (مفتاحٌ مكرَّر) — يُحصى قائمًا لا منشأً، وهذا عينُ المطلوب',
    ),
    'app/Services/Portal/CapacityService.php' => array(
        'خطةُ قدرةٍ واحدةٍ فشلت — تُحصى متخطاةً وتستمرُّ البقية',
    ),
    'app/Services/Portal/EvaluationService.php' => array(
        'إصدارُ شهادةِ تقييمٍ فشل — التقييمُ محفوظٌ والشهادةُ تُعاد بطلبٍ جديد',
    ),
    'app/Services/Procurement/MntProcBridgeService.php' => array(
        'جسرُ الصيانةِ إلى المشترياتِ فشل — أمرُ الصيانةِ قائمٌ والطلبُ يُنشأ يدويًّا',
    ),
    'app/Services/Procurement/ProcReorderService.php' => array(
        'طلبُ إعادةِ طلبٍ آليٍّ فشل — حدُّ إعادةِ الطلبِ يبقى قائمًا فيُعاد في الدورةِ التالية',
    ),
    'app/Services/Risk/RiskService.php' => array(
        'مزامنةُ إشارةٍ ميدانيةٍ واحدةٍ فشلت — بقيةُ الدفعةِ تُزامَن، والفاشلةُ تُعاد بمعرِّفها',
    ),
    'app/Services/Risk/RiskSignalEngine.php' => array(
        'مِرقابُ إشاراتٍ واحدٌ فشل — بقيةُ المراقيبِ تعمل، ونتيجتُه تغيب من تقريرِ الجولة',
    ),
    'includes/timesheet_event_hook.php' => array(
        'مروحةُ أثرِ التايم شيت فشلت — الساعاتُ محفوظةٌ والأثرُ يُستدرَك بإعادةِ المروحة',
    ),
);

$done = 0; $files = 0; $missed = array();
foreach ($REASONS as $rel => $list) {
    $abs = $ROOT . '/' . $rel;
    if (!is_file($abs)) { $missed[] = $rel . ' (غيرُ موجود)'; continue; }
    $src = (string) file_get_contents($abs);
    $i = 0;
    $new = preg_replace_callback(
        '/ems_catch_ignored\s*\(\s*\$(\w+)\s*,\s*__METHOD__\s*,\s*\'\'\s*\)/u',
        function ($m) use ($list, &$i, &$done) {
            if (!isset($list[$i])) { $i++; return $m[0]; }
            $why = str_replace("'", "\\'", $list[$i]);
            $i++; $done++;
            return "ems_catch_ignored(\${$m[1]}, __METHOD__, '{$why}')";
        },
        $src
    );
    if ($new === null || $new === $src) { $missed[] = $rel . ' (لا موضعَ فارغ)'; continue; }
    try { token_get_all($new, TOKEN_PARSE); }
    catch (\ParseError $e) { $missed[] = $rel . ' (يكسر التركيب)'; continue; }
    if ($apply) { file_put_contents($abs, $new); }
    $files++;
}

echo ($apply ? 'طُبِّق' : 'سيُطبَّق') . ": {$files} ملفًّا · {$done} سببًا\n";
foreach ($missed as $m) { echo "  ⚠ {$m}\n"; }
