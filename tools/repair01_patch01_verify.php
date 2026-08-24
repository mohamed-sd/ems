<?php
/**
 * tools/repair01_patch01_verify.php — فحصٌ نصيٌّ مثبِتٌ لبنودِ RPR-PATCH-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ §٥ من الأمرِ يشترط ظهورَ البنودِ الأربعةِ **فعليًّا عند الفحصِ النصيِّ لا
 *   عند افتراضِها**. فهذا الفاحصُ يفتح كلَّ ملفِّ مرحلةٍ ويبحث عن مراسٍ بنيويّةٍ
 *   لا عن عباراتٍ عامّة — والمرساةُ التي تطابقها جملةٌ عابرةٌ مرساةٌ كاذبة.
 * ◆ يفصل المراحلَ النطاقيّةَ عن غيرِها: W01 وW02 وW15 لا تأخذ ①②③.
 *
 * التشغيل: php tools/repair01_patch01_verify.php
 * الخروج : 0 كلُّ البنودِ ظاهرة · 1 بندٌ غائب
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$DIR = dirname(__DIR__) . '/docs/REPAIR01_20260823/plan/';
$files = glob($DIR . 'W*.prompt.md');
sort($files);

/* المراحلُ النطاقيّة = التي تحمل قسمَ رحلةِ الإثبات. تُكتشف ولا تُفترض. */
$CHECKS = array(
    '①رحلة'  => array('needle' => array('### ٦-أ · رحلةُ الإثبات', 'لا تُقبل المرحلةُ ببناءِ أسطحِها', 'أثرًا تجاريًّا مقيسًا عند كلِّ مستهلك')),
    '②آلة'   => array('needle' => array('_STATE_MACHINES.md', 'الانتقالاتُ الممنوعةُ صراحةً', 'قاعدةُ إعادةِ الفتح')),
    '②سود'   => array('needle' => array('_SOD.md', 'Reconciler/Closer', 'التركيبةُ الممنوعةُ صراحةً')),
    '②عقد'   => array('needle' => array('عقدُ أثرٍ مسجَّلٌ لكلِّ حدث', 'مفتاحُ منعِ التكرار', 'حدثٌ بلا عقدِ أثرٍ مسجَّلٍ لا يُنفَّذ')),
    '③ملاحة' => array('needle' => array('السايدبارُ قبل الشاشات', 'ترتيبٌ يدويٌّ موازٍ للسجلّ', 'Canonical Screen_ID')),
);
$T4 = array('مستخدمٌ حقيقيٌّ بصلاحيتِه الفعليّة', 'ثلاثةُ أشخاصٍ مختلفين', 'مسارٌ سالبٌ بشريّ',
            'سجلِّ المحاولات', 'لا يُعلَن أخضرَ ببذورِ بياناتٍ ولا بسكربت');

$rows = array(); $fail = 0; $scopeN = 0;
foreach ($files as $f) {
    $bn = basename($f);
    $txt = file_get_contents($f);
    $isScope = (mb_strpos($txt, '### ٦-أ · رحلةُ الإثبات') !== false);
    $r = array('file' => $bn, 'scope' => $isScope, 'res' => array(), 'miss' => array());
    if ($isScope) {
        $scopeN++;
        foreach ($CHECKS as $k => $c) {
            $ok = true;
            foreach ($c['needle'] as $nd) { if (mb_strpos($txt, $nd) === false) { $ok = false; $r['miss'][] = "$k←«" . mb_substr($nd, 0, 28) . "»"; } }
            $r['res'][$k] = $ok;
            if (!$ok) { $fail++; }
        }
        /* المهمّةُ الأولى يجب أن تكون الملاحة */
        if (!preg_match('/## ٤ · المهامّ\s*\n1\. \*\*السايدبارُ قبل الشاشات/u', $txt)) {
            $r['res']['③أولى'] = false; $r['miss'][] = '③ليست المهمّةَ رقم 1'; $fail++;
        } else { $r['res']['③أولى'] = true; }
        /* الرحلةُ مذكورةٌ في تعريفِ الإنجازِ وفي التسليم */
        $r['res']['①إنجاز'] = (mb_strpos($txt, '**رحلةُ §٦-أ تعبر**') !== false);
        if (!$r['res']['①إنجاز']) { $r['miss'][] = '①غائبةٌ من §٨'; $fail++; }
    } else {
        foreach ($CHECKS as $k => $c) { $r['res'][$k] = null; }
    }
    /* ④ في مرحلةِ الأساسِ وحدَها */
    if (strpos($bn, 'W15_') === 0) {
        $ok = true;
        foreach ($T4 as $nd) { if (mb_strpos($txt, $nd) === false) { $ok = false; $r['miss'][] = "④←«" . mb_substr($nd, 0, 28) . "»"; } }
        $r['res']['④بشري'] = $ok;
        if (!$ok) { $fail++; }
    }
    $rows[] = $r;
}

echo "\n═══════ فحصٌ نصيٌّ مثبِتٌ — RPR-PATCH-01 ═══════\n";
printf("%-30s %-7s %-7s %-7s %-7s %-7s %-8s %-8s\n", 'الملفّ', '①رحلة', '②آلة', '②سود', '②عقد', '③ملاحة', '③أولى', '①إنجاز');
echo str_repeat('─', 92) . "\n";
foreach ($rows as $r) {
    $c = function ($k) use ($r) { return array_key_exists($k, $r['res']) ? ($r['res'][$k] === null ? '  —' : ($r['res'][$k] ? '  ✔' : '  ✘')) : '  —'; };
    printf("%-30s %-7s %-7s %-7s %-7s %-7s %-8s %-8s%s\n", mb_substr($r['file'], 0, 30),
        $c('①رحلة'), $c('②آلة'), $c('②سود'), $c('②عقد'), $c('③ملاحة'), $c('③أولى'), $c('①إنجاز'),
        (isset($r['res']['④بشري']) ? ('   ④' . ($r['res']['④بشري'] ? '✔' : '✘')) : ''));
    if ($r['miss']) { echo "     ناقص: " . implode(' · ', $r['miss']) . "\n"; }
}
echo str_repeat('─', 92) . "\n";
printf("ملفّات: %d · نطاقيّة: %d · بنودٌ غائبة: %d\n", count($files), $scopeN, $fail);
echo ($fail === 0 ? "الحكم: كلُّ بنودِ RPR-PATCH-01 ظاهرةٌ نصًّا ✔\n" : "الحكم: بندٌ غائب ✘\n");
exit($fail === 0 ? 0 : 1);
