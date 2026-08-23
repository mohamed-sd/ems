<?php
/**
 * tests/injfrd66_xc02_detector_test.php — شاهدُ XC-02 ①: كاشفُ الصيغةِ المحادثية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **إيجابيٌّ**: كلُّ فعلِ جماعةٍ حقيقيٍّ يُرصَد.
 * ◆ **سالبٌ**  : كلُّ اسمٍ يبدأ بالنونِ **لا** يُرصَد — وهذه هي الإصاباتُ الثمانُ
 *   الكاذبةُ التي كان النمطُ القديمُ `^ن[\x{0600}-\x{06FF}]+\s` يرصدها، وهي
 *   مقروءةٌ من `gov_screen_cycle` الحيِّ لا مُختلَقةً في الشاهد.
 * ◆ والشاهدُ يستخرج النمطَ من `tools/uxui_gates.php` نفسِه — فلا نسخةَ ثانيةٌ
 *   تتفرَّق عن الأصل (قانونُ «عدّادٌ وعارضٌ في ملفَّين يتفرّقان»).
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

/* ── النمطُ من مصدرِه الواحدِ لا من نسخةٍ يُعاد بناؤها هنا ─────────────── */
require_once $ROOT . '/includes/conv_form_detect.php';
$CONVERSATIONAL = ems_conv_pattern();

$nOk = 0; $nBad = 0;
$check = static function (string $label, string $s, bool $want) use ($CONVERSATIONAL, &$nOk, &$nBad): void {
    $got = (bool) preg_match($CONVERSATIONAL, $s);
    if ($got === $want) { $nOk++; printf("   ✔ %-8s «%s»\n", $label, $s); }
    else { $nBad++; printf("   ✘ %-8s «%s» — توقّعتُ %s فجاء %s\n", $label, $s,
        $want ? 'رصدًا' : 'صمتًا', $got ? 'رصدًا' : 'صمتًا'); }
};

echo "① إيجابيٌّ — أفعالُ جماعةٍ يجب أن تُرصَد:\n";
foreach (array('نبدأ من العميل', 'نراجع السجلات', 'نوقّع العقد', 'نفوتر ونحصّل',
               'نتفاوض ونسعّر', 'نحاسبه ونصرف', 'نوزّع الحاويات') as $s) {
    $check('رصد', $s, true);
}

echo "\n② سالبٌ — أسماءٌ تبدأ بالنونِ يجب ألّا تُرصَد (الإصاباتُ الكاذبةُ الثمان):\n";
foreach (array('نماذج العمل ووحدات القياس', 'ناقلُ الأحداث', 'نشاطي الأخير',
               'نماذج التمويل', 'نظام التشغيل', 'نطاق العمل') as $s) {
    $check('صمت', $s, false);
}

/* ── ③ السالبُ الحيُّ: دفترُ الدورةِ نفسُه ─────────────────────────────── */
echo "\n③ سالبٌ حيّ — دفترُ الدورةِ الحيُّ يجب أن يعطيَ صفرَ إصابة:\n";
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$hits = array();
$res = @mysqli_query($conn, "SELECT id, stage_name, group_name, screen_title FROM gov_screen_cycle");
while ($res && ($x = mysqli_fetch_assoc($res))) {
    foreach (array('stage_name', 'group_name', 'screen_title') as $c) {
        if (preg_match($CONVERSATIONAL, (string) $x[$c])) { $hits[] = "#{$x['id']} {$c}=«{$x[$c]}»"; }
    }
}
if ($hits) { $nBad++; printf("   ✘ %d إصابةً في الدفتر:\n", count($hits)); foreach ($hits as $h) { echo "      {$h}\n"; } }
else { $nOk++; echo "   ✔ صفرُ صيغةٍ محادثيةٍ في دفتر الدورة الحي\n"; }

printf("\n%s  ناجح %d · راسب %d\n", $nBad === 0 ? '✔ XC-02①' : '✘ XC-02①', $nOk, $nBad);
exit($nBad === 0 ? 0 : 1);
