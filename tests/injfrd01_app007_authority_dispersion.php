<?php
/**
 * tests/injfrd01_app007_authority_dispersion.php
 *   شاهدُ FR-APP-007 — مستوى سلطةٍ واحدٌ لا خمسةُ أسطحٍ متفرقة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ بنصِّه**: «**مستوى سلطةٍ واحدٌ مسمًّى** يقرأ منه محرّكا الصلاحياتِ
 *   والاعتماد: **المنحُ والسقوفُ والتفويضُ والإنابةُ والنطاق**» · ومعيارُ القبول
 *   «**فحصُ شجرةٍ يُخرج صفرَ استعلامٍ خارجَ الخدمةِ الواحدة**».
 *
 * ◆ **والحالُ مقيسٌ بأسطحِه الخمسة** — لا بوصفٍ عامّ:
 *   المنحُ **82 ملفًّا** · السقوفُ 2 · التفويضُ 0 · الإنابةُ 7 · النطاقُ 1
 *   ⇒ **92 ملفَّ إنتاجٍ متمايزًا يستعلم عن سلطةٍ مباشرةً**.
 *
 * ◆ **ولا تُوحَّد الاثنتان والتسعون دفعةً**: توحيدُ مستوى السلطةِ يغيّر **من
 *   يملك ماذا** على نظامٍ ماليٍّ حيّ — وهو عينُ ما يمنعه بروتوكولُ القلب.
 *   والمعيارُ يطلب **فحصًا يمسح**، وهو ما يقدّمه هذا الشاهدُ مع **سقّاطةٍ**
 *   تمنع ازديادَ التفرُّق.
 *
 * ◆ **والسقّاطةُ تُشدُّ عندَ الانخفاض** — فسقّاطةٌ لا تُشدُّ تصير سقفًا يُنسى.
 *
 * التشغيل: php tests/injfrd01_app007_authority_dispersion.php [--retighten] [--list]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$BASE = $ROOT . '/docs/INJFIX01/evidence/APP-007_authority_baseline.json';

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

$list = in_array('--list', $argv, true);
$retighten = in_array('--retighten', $argv, true);
echo "══ FR-APP-007 — مستوى سلطةٍ واحدٌ لا أسطحٌ متفرقة ══\n";

/* ◆ **الأسطحُ الخمسةُ بأسمائِها من نصِّ المطلبِ نفسِه** — لا قائمةٌ من عندي */
$AX = array(
    'المنح'    => array('role_permissions', 'gov_profile_items',
                        'ownership_access_grants', 'sensitive_access_grants'),
    'السقوف'   => array('approval_thresholds', 'fin_authority', 'authority_limits'),
    'التفويض'  => array('delegation', 'gov_delegation', 'approval_delegation'),
    'الإنابة'  => array('substitute', 'gov_substitute', 'approval_substitutes'),
    'النطاق'   => array('fin_party_scope_registry', 'fin_project_scope', 'space_scope'),
);
$SKIP = array('/vendor/', '/node_modules/', '/.git/', '/docs/', '/storage/',
              '/tests/', '/tools/', '/database/');

$hit = array(); $all = array(); $scanned = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
    $pp = str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname());
    $sk = false;
    foreach ($SKIP as $k) { if (strpos($pp, $k) !== false) { $sk = true; break; } }
    if ($sk) { continue; }
    $scanned++;
    $src = (string) @file_get_contents($pp);
    if ($src === '') { continue; }
    $rel = str_replace($ROOT . '/', '', $pp);
    foreach ($AX as $ax => $terms) {
        foreach ($terms as $t) {
            if (preg_match('~(FROM|JOIN)\s+`?' . preg_quote($t, '~') . '~i', $src)) {
                $hit[$ax][$rel] = true;
                $all[$rel] = true;
                break;
            }
        }
    }
}
printf("  مُسِح: %d ملفَّ إنتاج\n\n", $scanned);
foreach ($AX as $ax => $_) {
    printf("     %-10s %d ملفًّا\n", $ax, isset($hit[$ax]) ? count($hit[$ax]) : 0);
}
$now = count($all);
printf("  **ملفاتٌ متمايزةٌ تستعلم عن سلطةٍ مباشرةً: %d**\n", $now);
chk($scanned > 0 && $now > 0, '**المقامُ غيرُ صفريّ** — ثمَّ أسطحٌ تُقاس',
    "{$scanned} ملفًّا مُسِح · {$now} يستعلم");

if ($list) {
    $i = 0;
    foreach (array_keys($all) as $r) { echo "     · {$r}\n"; if (++$i >= 15) { break; } }
    if ($now > 15) { echo "     … و" . ($now - 15) . " غيرُها\n"; }
}

/* ── معيارُ القبولِ لا يُدَّعى ─────────────────────────────────────────────── */
chk($now === 0,
    'FR-APP-007 · **صفرُ استعلامٍ خارجَ الخدمةِ الواحدة**',
    $now === 0 ? 'كلُّها عبرَ الخدمة'
        : "**غيرُ متحقِّق**: {$now} ملفًّا يستعلم عن السلطةِ مباشرةً — وتوحيدُها "
          . 'يغيّر **من يملك ماذا** على نظامٍ حيّ');

/* ── السقّاطة — لا تزداد وتُشدُّ عندَ الانخفاض ─────────────────────────────── */
echo "\n── السقّاطة ──\n";
if ($retighten || !is_file($BASE)) {
    if (!is_dir(dirname($BASE))) { mkdir(dirname($BASE), 0777, true); }
    $per = array();
    foreach ($AX as $ax => $_) { $per[$ax] = isset($hit[$ax]) ? count($hit[$ax]) : 0; }
    file_put_contents($BASE, json_encode(array(
        'req' => 'FR-APP-007', 'distinct' => $now, 'per_axis' => $per,
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "  ↦ شُدَّ خطُّ الأساسِ إلى {$now}\n";
}
$bl = json_decode((string) file_get_contents($BASE), true);
$blN = (int) ($bl['distinct'] ?? 0);
chk($now <= $blN, '**لا يزداد التفرُّق**', "{$now} ≤ {$blN}");
chk($now >= $blN, 'وخطُّ الأساسِ مطابقٌ — **والانخفاضُ يطلب شدَّ السقّاطة**',
    $now < $blN ? "انخفض إلى {$now}: شُدَّ بـ--retighten" : 'مطابق');

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
echo "◆ **ولا يُوحَّد المستوى بيدِ منفِّذ**: توحيدُ المنحِ والسقوفِ والتفويضِ\n";
echo "  والإنابةِ والنطاقِ في خدمةٍ واحدةٍ يغيّر **من يملك ماذا** على نظامٍ ماليٍّ\n";
echo "  حيّ — ويمرُّ ببروتوكولِ القلبِ بقرارِ مالك. والسقّاطةُ تمنع ازديادَ الدَّين.\n";
exit($bad === 0 ? 0 : 1);
