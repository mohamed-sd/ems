<?php
/**
 * cron_capture_chain.php — ملتقِطُ مخرَجِ كرونِ سلسلةِ الوحدة (E-02)
 * ═══════════════════════════════════════════════════════════════════════════
 * لماذا وُجد:
 *   كانت مهمّتا EMS_E02_ChainSLA و EMS_E02_ChainWeekly تمرّان بـ«cmd /c … >> log»
 *   لأجلِ إعادةِ التوجيهِ الصدفيّة. و«cmd» برنامجُ كونسولٍ يفتح نافذةً سوداءَ
 *   مرئيةً في كلِّ نبضةٍ تحتَ وضعِ Interactive. وWindows Script Host معطَّلٌ على
 *   هذا الجهاز فلا مُطلِقَ VBS مخفيًّا. فبقيت طريقةٌ واحدةٌ تُبقي اللوجَ حيًّا
 *   بلا صَدَفة: التقاطُ المخرَجِ داخلَ PHP نفسِه.
 *
 * كيف يُستعمل — يُحقن قبلَ السكربتِ الهدفِ لا يُنادى مباشرةً:
 *   php-win.exe -d auto_prepend_file=<هذا الملف> <سكربتُ الكرون>
 *
 * ما يلتقطه: كلَّ ما كان يذهب إلى STDOUT — ومنه الأخطاءُ المعروضة، لأنّ
 *   display_errors=1 في CLI تكتب إلى STDOUT لا STDERR. والأخطاءُ القاتلةُ
 *   تُلحق صراحةً في المُغلِق أدناه لأنّ العَرضَ وحدَه قد يسبقُ إفراغَ المخزَن.
 * ═══════════════════════════════════════════════════════════════════════════
 */

// لا يعمل إلا في سطرِ الأوامر — لئلّا يبتلع مخرَجَ صفحةِ ويب لو حُقن سهوًا.
if (PHP_SAPI !== 'cli') {
    return;
}

$__ems_cron_log = __DIR__ . '/../storage/logs/cron_unit_chain.log';

$__ems_cron_dir = dirname($__ems_cron_log);
if (!is_dir($__ems_cron_dir)) {
    @mkdir($__ems_cron_dir, 0775, true);
}

// المخزَنُ الخارجيُّ: يُفتح أولًا فيبتلع ما تُفرِغه المخازنُ الداخليةُ فيه.
ob_start();

register_shutdown_function(static function () use ($__ems_cron_log) {
    // اسحبْ كلَّ الطبقات من الداخلِ إلى الخارجِ حفاظًا على ترتيبِ النص.
    $out = '';
    while (ob_get_level() > 0) {
        $chunk = ob_get_clean();
        if ($chunk === false) {
            break;
        }
        $out = $chunk . $out;
    }

    // الخطأُ القاتلُ لا يمرُّ دائمًا بالمخزَن — ألحِقْه صراحةً.
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $out .= sprintf(
            "\n[FATAL] %s in %s:%d\n",
            $err['message'],
            $err['file'],
            $err['line']
        );
    }

    if ($out === '') {
        return;
    }

    @file_put_contents($__ems_cron_log, $out, FILE_APPEND | LOCK_EX);
});
