<?php
/**
 * tools/injfrd01_gov003_doc_currency.php
 *   FR-GOV-003 · CHG-GOV-EVIDENCE-01 — وثيقةٌ واحدةٌ حاليةٌ لكلِّ موضوع
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلب** (الدفتر · GAP-55 · P2): «كلُّ وثيقةٍ إمّا **حاليةٌ وحيدةٌ
 *   لموضوعِها** وإمّا موسومةٌ تاريخيةً» · ومعيارُ قبولِه: «**صفرُ وثيقتَين باسمِ
 *   الحاليةِ لموضوعٍ واحد**».
 *
 * ◆ **والعطبُ مقيسٌ لا مزعوم**: أربعٌ وعشرون ملفًّا كلُّها باسم
 *   `ARCHITECTURE_CURRENT_SYSTEM` — و«CURRENT» في الاسمِ نفسِه ادّعاءُ حضور.
 *   والأحدثُ v26 وحدَه هو الحاضر.
 *
 * ◆ **ولا يُحذف ملفٌّ ولا يُعاد تسميتُه**: الوسمُ يُضاف **في صدرِ الوثيقةِ
 *   نفسِها** فيقرؤه الإنسانُ والفاحصُ معًا. والحذفُ يقطع إحالاتٍ تاريخية.
 *
 * ◆ **والموضوعُ يُشتقُّ من الاسمِ لا يُكتب**: ما قبلَ `_v<رقم>_ar.md` هو
 *   الموضوع — فتنطبق القاعدةُ على كلِّ عائلةِ نسخٍ في `docs/` لا على
 *   المعماريةِ وحدَها.
 *
 * التشغيل: php tools/injfrd01_gov003_doc_currency.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
$MARK  = 'وثيقةٌ تاريخيةٌ — لا تُقرأ حاضرًا';

/* عائلاتُ النسخ: الموضوعُ ⇐ [رقم => مسار] */
$fam = array();
foreach (glob($ROOT . '/docs/*_v[0-9]*_ar.md') as $f) {
    $b = basename($f);
    if (!preg_match('~^(.+)_v(\d+)_ar\.md$~', $b, $m)) { continue; }
    $fam[$m[1]][(int) $m[2]] = $f;
}

echo "════ FR-GOV-003 · وثيقةٌ واحدةٌ حاليةٌ لكلِّ موضوع ════\n";
echo $apply ? "  الوضع: **تطبيق**\n\n" : "  الوضع: عرضٌ فقط (مرِّر --apply)\n\n";

$claim = 0; $marked = 0; $topics = 0;
foreach ($fam as $topic => $versions) {
    if (count($versions) < 2) { continue; }
    $topics++;
    krsort($versions);
    $latest = array_key_first($versions);
    printf("── %s · %d نسخةً · الحاليةُ v%d\n", $topic, count($versions), $latest);
    foreach ($versions as $v => $path) {
        if ($v === $latest) { continue; }
        $src = (string) file_get_contents($path);
        if (preg_match('~تاريخي|مؤرشف|SUPERSEDED|لا يُقرأ حاضرًا|مُتجاوَز~u', $src)) { continue; }
        $claim++;
        if (!$apply) { continue; }
        /* الوسمُ في **أوّلِ سطرٍ** — فيراه القارئُ قبلَ أن يقرأ رقمًا */
        $hdr = "> ⛔ **{$MARK}** — نُسخت هذه الوثيقةُ عند إصدارِها v{$v}،\n"
             . "> والحاليةُ الوحيدةُ لموضوعِها هي **v{$latest}**. تُقرأ للتاريخِ ولا يُبنى\n"
             . "> عليها قرارٌ ولا يُنقل منها رقمٌ إلى تقرير. (FR-GOV-003)\n\n";
        file_put_contents($path, $hdr . $src);
        $marked++;
    }
}
printf("\n① عائلاتُ نسخٍ: %d · ادّعاءُ حضورٍ بلا وسم: **%d**", $topics, $claim);
if ($apply) { printf(" · وُسِم: %d", $marked); }
echo "\n";

if ($apply) {
    /* ◆ **قراءةٌ ثانيةٌ إلزامٌ** — الكتابةُ التي لا تُقرأ بعدَها كتابةٌ مزعومة */
    $left = 0;
    foreach ($fam as $topic => $versions) {
        if (count($versions) < 2) { continue; }
        krsort($versions);
        $latest = array_key_first($versions);
        foreach ($versions as $v => $path) {
            if ($v === $latest) { continue; }
            if (!preg_match('~تاريخي|مؤرشف|SUPERSEDED|لا يُقرأ حاضرًا|مُتجاوَز~u',
                            (string) file_get_contents($path))) { $left++; }
        }
    }
    printf("② القراءةُ الثانية: باقٍ بلا وسم = **%d**\n", $left);
    if ($left > 0) { exit("⛔ كتابةٌ مزعومة — أُوقِف\n"); }
}
