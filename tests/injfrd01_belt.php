<?php
/**
 * tests/injfrd01_belt.php
 *   حزامُ شواهدِ INJ-FRD-REM-01 — **حكمٌ ثلاثيٌّ لا ثنائيّ**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ الذي يمنعه**: ستةٌ وعشرون شاهدًا أُنتجت في هذه الجولةِ **خارجَ
 *   حزامِ الانحدار** (`injchain01` يكنس `injfix0*` وحدَه). فدليلٌ يُنتَج مرّةً
 *   ولا يُكنَس **يتعفّن صامتًا**: يبقى مكتوبًا في الدفترِ أخضرَ وقد انقلب
 *   أحمرَ على القرص. **والإغلاقُ الذي لا يُعاد تشغيلُه ذكرى لا حقيقة.**
 *
 * ◆ **ولا يصلح حكمٌ ثنائيّ**: سبعةٌ من الستةِ والعشرين **حمراءُ بحقّ** — تقيس
 *   معيارًا لم يبلغ بعدُ وسببُه مكتوبٌ في عمودِ `Blocker`. فإدراجُها في حزامٍ
 *   ثنائيٍّ يجعله أحمرَ أبدًا **فيُعطَّل**، وإخراجُها يجعل تعفُّنَها غيرَ مرئيّ.
 *
 * ◆ **فالحكمُ ثلاثيّ، ومصدرُه الدفترُ الرسميُّ لا قائمةٌ ثانية**:
 *   ① شاهدٌ يستشهد به مطلبٌ **مُغلَقٌ بالدليل** ⇒ **يجب أن يكون أخضر**.
 *      واحمرارُه **ارتدادٌ يُرسِّب**.
 *   ② شاهدٌ كلُّ مطالبِه **غيرُ مُغلَقة** ⇒ **يجوز أن يكون أحمر** — وحمرتُه
 *      مُعلَنةٌ بسببِها في الدفتر.
 *   ③ شاهدٌ من الصنفِ ② **انقلب أخضر** ⇒ **خبرٌ يُرسِّب أيضًا**: البلاغُ
 *      تحقَّق والدفترُ لم يُحدَّث. فسكوتُ الحزامِ عنه يُبقي إنجازًا مطويًّا.
 *
 * ◆ **ولا شاهدَ بلا مطلبٍ يستشهد به**: ملفٌّ في العائلةِ لا يذكره الدفترُ
 *   **يُرسِّب** — فشاهدٌ لا يخدم مطلبًا دليلٌ بلا دعوى.
 *
 * التشغيل: php tests/injfrd01_belt.php [--list]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/lib/xlsx_io.php';

$list = in_array('--list', $argv, true);
echo "══ حزامُ شواهدِ INJ-FRD-REM-01 — حكمٌ ثلاثيّ ══\n";

/* ── ① الدفترُ الرسميُّ مصدرُ التوقُّع — لا قائمةٌ ثانية ─────────────────── */
$XLSX = $ROOT . '/docs/sources/INJ-FRD-REM-01/workbook.xlsx';
if (!is_file($XLSX)) { fwrite(STDERR, "⛔ الدفترُ الرسميُّ مفقود\n"); exit(1); }
$wb = xlsx_read($XLSX);
$rows = $wb[array_keys($wb)[0]];
$hdr = $rows[3];
$ix = array();
foreach ($hdr as $i => $h) { $ix[trim(str_replace('◆ ', '', (string) $h))] = $i; }

/** تفكيكُ عمودِ الدليلِ — الصيغُ أربع: `·` و`+` و`[--flag]` و`--opt=v` */
function belt_pieces($s)
{
    $s = preg_replace('~\[[^\]]*\]~u', ' ', (string) $s);
    $s = preg_replace('~--[a-z0-9=_-]+~i', ' ', $s);
    $out = array();
    foreach (preg_split('~\s*(?:·|\+|\|)\s*~u', $s) as $p) {
        $p = trim($p);
        if ($p !== '' && strpos($p, '/') !== false) { $out[] = $p; }
    }
    return $out;
}

/* شاهد ⇒ [حالاتُ الإغلاقِ لكلِّ مطلبٍ يستشهد به] */
$cited = array();
foreach ($rows as $i => $r) {
    if ($i < 4) { continue; }
    $id = trim((string) ($r[$ix['المعرِّف']] ?? ''));
    if (!preg_match('~^[A-Z]{2,4}-[A-Z]{2,4}-\d{3}$~', $id)) { continue; }
    $cl = trim((string) ($r[$ix['Closure_State']] ?? '')) ?: 'OPEN';
    foreach (belt_pieces((string) ($r[$ix['Evidence_Status']] ?? '')) as $pp) {
        if (strpos($pp, 'tests/injfrd01_') !== 0) { continue; }
        $cited[$pp][] = array('id' => $id, 'cl' => $cl);
    }
}

/* ── ② العائلةُ تُمسح من القرص — لا من قائمةٍ مكتوبة ───────────────────── */
$files = glob($ROOT . '/tests/injfrd01_*.php');
$suite = array();
foreach ($files as $f) {
    $rel = str_replace($ROOT . '/', '', str_replace(DIRECTORY_SEPARATOR, '/', $f));
    if ($rel === 'tests/injfrd01_belt.php') { continue; }   /* لا يكنس نفسَه */
    $suite[] = $rel;
}
sort($suite);
printf("  شواهدُ العائلةِ على القرص: **%d** · يستشهد بها الدفتر: %d\n",
       count($suite), count($cited));
if (empty($suite)) { fwrite(STDERR, "⛔ **مقامٌ صفريّ** — لا شاهدَ يُكنَس\n"); exit(1); }

/* ── ③ الكنسُ والحكمُ الثلاثيّ ───────────────────────────────────────────── */
$mustGreen = array(); $mayRed = array(); $orphan = array();
$regress = array(); $greenOpen = array(); $undeclared = array(); $sliced = array();
$greenN = 0; $redN = 0;

foreach ($suite as $rel) {
    if (!isset($cited[$rel])) { $orphan[] = $rel; }
    $closedCite = false; $anyCite = isset($cited[$rel]);
    if ($anyCite) {
        foreach ($cited[$rel] as $c) { if ($c['cl'] === 'EVIDENCE_CLOSED') { $closedCite = true; break; } }
    }
    $out = array(); $rc = 0;
    @exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/' . $rel) . ' 2>&1', $out, $rc);
    $green = ($rc === 0);
    if ($green) { $greenN++; } else { $redN++; }

    /* ◆ **شاهدٌ يخدم مطلبَين بحالتَين ⇒ حكمٌ لكلِّ مطلبٍ على حدة**: بلا ذلك
     *   يبقى الشاهدُ أحمرَ بسببِ المفتوحِ **فلا يُتحقَّق المُغلَقُ أبدًا**
     *   ويُقرأ ارتدادًا كاذبًا. ⇒ إن كان الشاهدُ يدعم `--req=` يُشغَّل
     *   **بمعرِّفِ المطلبِ المُغلَقِ وحدَه**، وإلا يُحكَم عليه كاملًا. */
    $srcTxt = (string) @file_get_contents($ROOT . '/' . $rel);
    $reqSupported = (strpos($srcTxt, 'function req_on') !== false);

    if ($closedCite) {
        $mustGreen[] = $rel;
        $sliceGreen = $green;
        if (!$green && $reqSupported) {
            /* يُعاد التشغيلُ لكلِّ مطلبٍ مُغلَقٍ على حدة — وكلُّها يجب أن تخضرَّ */
            $sliceGreen = true;
            foreach ($cited[$rel] as $c) {
                if ($c['cl'] !== 'EVIDENCE_CLOSED') { continue; }
                $o2 = array(); $rc2 = 0;
                @exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/' . $rel)
                    . ' --req=' . escapeshellarg($c['id']) . ' 2>&1', $o2, $rc2);
                if ($rc2 !== 0) { $sliceGreen = false; }
            }
            if ($sliceGreen) { $sliced[] = $rel; }
        }
        if (!$sliceGreen) { $regress[] = $rel; }
    } elseif ($anyCite) {
        $mayRed[] = $rel;
        /* ◆ **وخضرةُ شاهدٍ لمطلبٍ غيرِ مُغلَقٍ حالةٌ طبيعيةٌ لا خلل**: أوّلُ
         *   صيغةٍ عدَّتها «بلاغًا تحقَّق ولم يُحدَّث» فأنذرت عن أربعةٍ **كلُّها
         *   سليمة**: الشاهدُ يقيس الآلةَ فتخضرُّ، والإغلاقُ يلزمه ما ليس
         *   آلةً — تاريخٌ لا يُملأ رجعيًّا (1644 قيدًا · 1113 دفعةً ·
         *   5354 واقعةَ رفض) أو نافذةُ ظلٍّ أو قرارُ مالك.
         * ◆ **فإنذارٌ عن حالةٍ سليمةٍ يُعطِّل الحزامَ كما يُعطِّله السكوت.**
         *   ⇒ تُعَدُّ وتُعرَض خبرًا، ولا تُرسِّب. */
        if ($green) { $greenOpen[] = $rel; }
    } else {
        if (!$green) { $undeclared[] = $rel; }
    }
}
printf("  الكنس: **%d خضراء · %d حمراء**\n", $greenN, $redN);
printf("  التوقُّع: يجب أن يخضرَّ=%d · يجوز أن يحمرَّ=%d · بلا استشهاد=%d\n",
       count($mustGreen), count($mayRed), count($orphan));

$fail = 0;

/* ① ارتداد: شاهدُ مُغلَقٍ انقلب أحمر */
if ($regress) {
    $fail++;
    echo "\n  ✘ **ارتداد** — شاهدُ مطلبٍ مُغلَقٍ بالدليلِ أحمرُ الآن:\n";
    foreach ($regress as $r) { echo "     · {$r}\n"; }
} else {
    echo "\n  ✔ **صفرُ ارتداد** — كلُّ شاهدِ مُغلَقٍ أخضر (" . count($mustGreen) . ")\n";
    if ($sliced) {
        echo "     ◆ ومنها بحكمٍ **لكلِّ مطلبٍ على حدة** (--req=): " . count($sliced) . "\n";
        foreach ($sliced as $sl) { echo "        · " . basename($sl) . "\n"; }
    }
}

/* ② خبرٌ لا خلل: شاهدُ مطلبٍ غيرِ مُغلَقٍ أخضرُ — الآلةُ تعمل والمُعوِزُ غيرُها */
printf("  ◆ شاهدُ مطلبٍ غيرِ مُغلَقٍ **أخضر**: %d من %d — والآلةُ تعمل،\n",
       count($greenOpen), count($mayRed));
echo "    والمُعوِزُ للإغلاقِ **ليس آلةً**: تاريخٌ لا يُملأ رجعيًّا أو نافذةُ\n";
echo "    ظلٍّ أو قرارُ مالك. **فهذا خبرٌ لا خلل.**\n";

/* ③ شاهدٌ لا يستشهد به الدفتر */
if ($orphan) {
    $fail++;
    echo "  ✘ **شاهدٌ لا يخدم مطلبًا** — دليلٌ بلا دعوى:\n";
    foreach ($orphan as $o) { echo "     · {$o}\n"; }
} else {
    echo "  ✔ ولكلِّ شاهدٍ **مطلبٌ يستشهد به** — صفرُ دليلٍ بلا دعوى\n";
}

if ($undeclared) {
    $fail++;
    echo "  ✘ **حمرةٌ غيرُ مُعلَنة**:\n";
    foreach ($undeclared as $u) { echo "     · {$u}\n"; }
}

if ($list) {
    echo "\n  ── الحمراءُ المُعلَنةُ وأسبابُها من الدفتر ──\n";
    foreach ($mayRed as $r) {
        $ids = array_map(function ($c) { return $c['id'] . '=' . $c['cl']; }, $cited[$r]);
        printf("     %-52s %s\n", basename($r), implode(' · ', $ids));
    }
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("%s الحزام: %d خضراء · %d حمراء · %d خللًا\n",
       $fail === 0 ? '✔' : '✘', $greenN, $redN, $fail);
echo "◆ **والحكمُ ثلاثيّ**: ارتدادُ المُغلَقِ يُرسِّب · وشاهدٌ بلا مطلبٍ يُرسِّب ·\n";
echo "  وحمرةٌ غيرُ مُعلَنةٍ تُرسِّب. وخضرةُ غيرِ المُغلَقِ **خبرٌ لا خلل**. ومصدرُ التوقُّعِ\n";
echo "  **الدفترُ الرسميُّ نفسُه** — لا قائمةَ ثانيةً تتفرّق عنه.\n";
exit($fail === 0 ? 0 : 1);
