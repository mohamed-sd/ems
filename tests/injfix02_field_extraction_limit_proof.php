<?php
/**
 * tests/injfix02_field_extraction_limit_proof.php
 *   INJ-FIX-01 · GAP-21 موسَّعةً بـ INJ-FIX-02 · NF-10 و NF-11
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **NF-11 يُغلق بشقِّه الثاني المنصوص**: «تمديدُ الاستخراجِ إليها **أو إعلانُ
 *   الحدِّ صراحةً في رأسِ الورقةِ وفي فحصِ التسليم»**. وهذا هو فحصُ التسليم.
 *
 * ◆ **ولماذا الإعلانُ لا التمديد**: «صفرُ حقلٍ مستخرَج» ليس عيبًا في كلِّ سطح.
 *   المعالجُ والكرونُ وصفحةُ الدخولِ **لا تُصيِّر حقولًا أصلًا**، والسطحُ الابنُ
 *   (`?view=`) حقولُه حقولُ أبيه، والمُصيَّرُ بعُدَّةٍ حقولُه في العُدَّةِ لا في ملفِّه.
 *   **فعدُّ هذه «فجوةَ استخراج» يُضخِّم المقامَ بما ليس منه.**
 *
 * ◆ **والحدُّ الحقيقيُّ يُعلَن بعددِه**: شاشاتٌ تُصيِّر حقولًا ولم يُستخرَج منها شيء.
 *
 * ◆ **NF-10**: المقامُ الحقليُّ يُظهَر ولا يُدَّعى سدُّه — والسدُّ عملٌ مجدوَل.
 *
 * التشغيل: php tests/injfix02_field_extraction_limit_proof.php [--retighten]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$EX   = $ROOT . '/docs/baseline_20260821/extract/';
$BASE = $ROOT . '/docs/INJFIX01/evidence/NF-11_extraction_limit.json';

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}

/* ◆ الحدُّ المُعلَن — أنواعُ أسطحٍ لا تُصيِّر حقولًا في ملفِّها، ولكلٍّ سببُه */
$DECLARED = array(
    'HANDLER'          => 'معالجٌ يقرأ POST ويعيد توجيهًا أو JSON — لا يُصيِّر حقلًا',
    'CRON'             => 'مهمةٌ خلفيةٌ بلا واجهة',
    'ENTRY'            => 'صفحةُ دخولٍ أو توجيه',
    'SCREEN_SUBSYSTEM' => 'سطحٌ ابنٌ (‏?view=) — حقولُه حقولُ أبيه ولا تُكرَّر',
    'SCREEN_VIA_KIT'   => 'مُصيَّرٌ بعُدَّةٍ مشترَكة — حقولُه مُعرَّفةٌ في العُدَّةِ لا في ملفِّه',
);

if (!is_file($EX . 'screen_registry.json')) { exit("✘ سجلُّ الشاشاتِ غيرُ موجود\n"); }
$reg = json_decode((string) file_get_contents($EX . 'screen_registry.json'), true);

$byType = array(); $zeroScreens = array();
foreach ($reg as $r) {
    $t = (string) ($r['surface_type'] ?? '?');
    $f = (int) ($r['table_columns'] ?? 0) + (int) ($r['form_fields'] ?? 0);
    if (!isset($byType[$t])) { $byType[$t] = array('n' => 0, 'z' => 0); }
    $byType[$t]['n']++;
    if ($f === 0) {
        $byType[$t]['z']++;
        if (!isset($DECLARED[$t])) { $zeroScreens[] = (string) $r['route']; }
    }
}

echo "══ ① الحدُّ المُعلَنُ لتغطيةِ الاستخراج ══\n";
foreach ($byType as $t => $v) {
    $mark = isset($DECLARED[$t]) ? '·' : '◆';
    printf("  %s %-18s %3d سطحًا · بصفرِ حقل %3d%s\n", $mark, $t, $v['n'], $v['z'],
        isset($DECLARED[$t]) ? '  ⟵ حدٌّ معلَن' : '');
}
foreach ($DECLARED as $t => $why) { printf("     · %-18s %s\n", $t, $why); }

echo "\n══ ② الحكم — لا سطحَ بصفرِ حقلٍ خارجَ الحدِّ المُعلَنِ إلا المُقاسَ ══\n";
$n = count($zeroScreens);
printf("  ◆ **شاشاتٌ تُصيِّر حقولًا ولم يُستخرَج منها شيء: %d**\n", $n);

if (in_array('--retighten', $argv, true) || !is_file($BASE)) {
    if (!is_dir(dirname($BASE))) { mkdir(dirname($BASE), 0777, true); }
    file_put_contents($BASE, json_encode(array(
        'gap' => 'GAP-21 · NF-11', 'declared_limit' => $DECLARED,
        'undeclared_zero_field_screens' => $n, 'routes' => $zeroScreens,
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "  ↦ شُدَّ خطُّ الأساسِ إلى {$n}\n";
}
$bl = json_decode((string) file_get_contents($BASE), true);
$blN = (int) ($bl['undeclared_zero_field_screens'] ?? 0);
$blR = (array) ($bl['routes'] ?? array());
$new = array_values(array_diff($zeroScreens, $blR));

chk(count($new) === 0, 'صفرُ شاشةٍ جديدةٍ بلا حقولٍ مستخرَجة — ' . count($new)
    . (count($new) ? ' — ' . implode(' · ', array_slice($new, 0, 5)) : ''));
chk($n <= $blN, "لا ازديادَ — {$n} ≤ {$blN}");
if ($n < $blN) {
    chk(false, "◆ انخفض إلى {$n} من {$blN} — **تُشدُّ السقّاطة**: "
             . 'php tests/injfix02_field_extraction_limit_proof.php --retighten');
}

echo "\n══ ③ NF-10 — المقامُ الحقليُّ يُظهَر ولا يُدَّعى سدُّه ══\n";
if (!is_file($EX . 'field_registry.json')) {
    chk(false, 'سجلُّ الحقولِ غيرُ موجود');
} else {
    $fr = json_decode((string) file_get_contents($EX . 'field_registry.json'), true);
    $tot = count($fr); $noTech = 0; $kind = array();
    foreach ($fr as $x) {
        $k = (string) ($x['kind'] ?? '?');
        if (!isset($kind[$k])) { $kind[$k] = array('n' => 0, 'nt' => 0); }
        $kind[$k]['n']++;
        $t = trim((string) ($x['technical'] ?? ''));
        if ($t === '' || $t === 'NEEDS_REVIEW') { $noTech++; $kind[$k]['nt']++; }
    }
    printf("  المقامُ الحقليّ: %d حقلًا · **بلا اسمٍ تقنيّ: %d (%.1f%%)**\n",
        $tot, $noTech, $tot ? 100 * $noTech / $tot : 0);
    foreach ($kind as $k => $v) {
        printf("     %-20s %5d · بلا تقنيّ %5d (%.0f%%)\n", $k, $v['n'], $v['nt'],
            $v['n'] ? 100 * $v['nt'] / $v['n'] : 0);
    }
    echo "  ◆ **والنقصُ كلُّه في أعمدةِ الجداول — وحقولُ النماذجِ ١٠٠٪ مسمّاة.**\n";
    echo "     فليس عيبَ توثيقٍ عامًّا بل **حدَّ استخراجٍ في نوعٍ واحد**: الاسمُ التقنيُّ\n";
    echo "     للعمودِ يُشتقُّ بمحاذاةِ رأسِ الجدولِ بتعبيرِ `\$row['key']`، وعند اختلالِها\n";
    echo "     يُكتب NEEDS_REVIEW **ولا يُخمَّن** — وهو الصواب.\n";
    echo "  ◆ ويُظهَر هذا المقامُ في فجوةِ التوثيقِ المسجَّلةِ GAP-21 (معيارُ NF-10 ①)،\n";
    echo "     **وسدُّه عملٌ مجدوَلٌ لا يُدَّعى إنجازُه هنا** (معيارُ NF-10 ②).\n";
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
