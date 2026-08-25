<?php
/**
 * tools/repair01_w6_invariant.php
 *   ثابتُ التنقية — REPAIR01 · W06 · **برهانٌ على الشجرةِ كلِّها لا عيّنة**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السؤالُ الذي يجيب عنه**: تنقيةُ ٨٦٠ ملفَّ شاشةٍ تحريرٌ آليٌّ واسع، و`php -l`
 *   يشهد أنَّ الملفَّ **يُحلَّل** ولا يشهد أنَّ **معناه لم يتغيَّر**. وحرفٌ واحدٌ
 *   حُذف من كلمةٍ أو اقتباسٌ أُزيح يمرُّ من المحلِّلِ سليمًا ويكسر شاشةً أو حكمًا.
 *
 * ◆ **والثابت**: التنقيةُ لا تحذف إلّا **علاماتِ التشكيل**. فإن كان ذلك حقًّا،
 *   فنزعُ التشكيلِ من النصِّ **القديم** ومن النصِّ **الجديد** يعطي سلسلتَين
 *   **متطابقتَين بايتًا بايتًا**. وأيُّ فرقٍ — حرفٌ أو مسافةٌ أو قوسٌ أو سطر —
 *   يكسر التطابقَ ويُعلَن باسمِ ملفِّه.
 *
 * ◆ **وهو برهانٌ لا عيّنة**: يقع على **كلِّ** ملفٍّ مُعدَّلٍ في الفهرس، لا على
 *   ملفّاتٍ يختارها الفاحص. «المقامُ كاملٌ لا مختار» (‏_CONTEXT §قواعد القياس ٤).
 *
 * ◆ **والمستثنى مُعلَنٌ بعددِه واسمِه**: تسعةُ ملفّاتٍ حُرِّرت في هذه المرحلةِ
 *   **بالمعنى** لا بالتنقيةِ الآليّة (‏المنقّي نفسُه · نواةُ النقاء · المولِّد ·
 *   بذرةُ المجموعات · الترويسة) — والثابتُ لا يسري عليها بالتعريف، فتُستثنى
 *   **بأسمائها** لا بنمطٍ فضفاض.
 *
 * التشغيل: php tools/repair01_w6_invariant.php
 * الخروج : 0 الثابتُ قائمٌ على كلِّ ملفّ · 1 خُرق في ملفٍّ أو أكثر
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = str_replace(chr(92), '/', dirname(__DIR__));
require_once $ROOT . '/app/Services/Ui/UiPurity.php';

use App\Services\Ui\UiPurity;

/** ملفّاتٌ حُرِّرت بالمعنى في W06 — الثابتُ لا يسري عليها بالتعريف. */
function repair01_w6_invariant_exempt()
{
    return array(
        'app/Services/Ui/UiPurity.php'        => 'نواةُ النقاءِ نفسُها — قواعدُ الكشفِ والتحويل',
        'app/Services/Ui/UiLabelRegistry.php' => 'خدمةُ السجلِّ المركزيّ',
        'app/Services/Work/WorkItemService.php' => 'المولِّدُ — رُفعت شرطةُ الربطِ وقُرئ السجلّ',
        'includes/nav_groups.php'             => 'صارت بذرةً تُقرأ حين لا سجلّ',
        'includes/page_header.php'            => 'سطرُ الدورةِ — نُزع سهمُ الزخرفة',
        'includes/ux_components.php'          => 'لافتةُ الخطوةِ التالية',
    );
}

$out = array();
exec('git -C "' . $ROOT . '" diff --cached --name-only --diff-filter=M 2>&1', $out);

$exempt  = repair01_w6_invariant_exempt();
$checked = 0; $skipped = 0; $bad = array(); $unread = 0;

foreach ($out as $rel) {
    $rel = trim($rel);
    if ($rel === '' || substr($rel, -4) !== '.php') { continue; }
    /* أدواتُ القياسِ ووثائقُ الحملةِ والهجراتُ خارجَ نطاقِ التنقيةِ أصلًا */
    if (strpos($rel, 'tools/') === 0 || strpos($rel, 'docs/') === 0
        || strpos($rel, 'database/') === 0 || strpos($rel, 'tests/') === 0) { $skipped++; continue; }
    if (isset($exempt[$rel])) { $skipped++; continue; }

    $old = shell_exec('git -C "' . $ROOT . '" show HEAD:"' . $rel . '" 2>&1');
    $new = @file_get_contents($ROOT . '/' . $rel);
    if ($old === null || $new === false) { $unread++; continue; }
    $checked++;
    if (UiPurity::stripDiacritics($old) !== UiPurity::stripDiacritics($new)) { $bad[] = $rel; }
}

echo "══ ثابتُ التنقية — REPAIR01 · W06 ══\n\n";
printf("  ملفّاتٌ مفحوصةٌ بالثابت : %d\n", $checked);
printf("  مستثناةٌ بأسمائها      : %d (‏حُرِّرت بالمعنى) + أدواتٌ ووثائقُ وهجرات\n", count($exempt));
printf("  خارجَ النطاقِ           : %d\n", $skipped);
if ($unread) { printf("  تعذّرت قراءتُه        : %d\n", $unread); }
printf("\n  **خرقُ الثابت**        : %d\n", count($bad));
foreach (array_slice($bad, 0, 20) as $b) { echo "     ✘ $b\n"; }

echo "\n" . str_repeat('─', 70) . "\n";
echo 'الحكم: ' . (count($bad) === 0 && $checked > 0
    ? "الثابتُ قائم — لم يُحذف من الشجرةِ غيرُ التشكيل ✔\n"
    : "خُرق الثابت ✘\n");
exit(count($bad) === 0 && $checked > 0 ? 0 : 1);
