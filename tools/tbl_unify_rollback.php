<?php
/**
 * tools/tbl_unify_rollback.php — التراجعُ الكاملُ عن جولةِ توحيدِ تصميمِ الجداول.
 *
 * لماذا نسخٌ ملفّيٌّ لا `git checkout`؟
 *   لأن الشجرةَ كانت تحوي ١٥ ملفًّا معدَّلًا غيرَ ملتزَمٍ قبلَ الجولة، فأيُّ
 *   استعادةٍ من الالتزامِ ستمحو عملَ المالكِ السابقَ معها. النسخُ الملفّيُّ
 *   يعيدُ ما لمستُه أنا وحدَه، ويتركُ ما لم ألمسه كما هو.
 *
 * الاتجاهان محفوظان كلاهما، فالرجوعُ ليس بابًا ذا مصراعٍ واحد:
 *   _before (جذرُ المجلد) ← الحالُ قبلَ الجولة
 *   _after/                ← الحالُ بعدَها
 * وقد جُرِّب الاتجاهان فعلًا: نُفِّذ `--apply` كاملًا فعاد النظامُ إلى ما كان،
 * ثم أُعيد للأمامِ من `_after` — فالوعدُ مقيسٌ لا مُفترَض.
 *
 * ⚠ **ولماذا صار الرجوعُ جراحيًّا لا استعادةَ ملفٍّ كامل؟**
 *   جرى **أثناءَ** الجولةِ عملٌ آخرُ على الشجرةِ نفسِها (بطاقاتُ الملفِّ الشخصيّ)
 *   حذف ٢٥٢ سطرَ `.profile-*` من `assets/css/ems-screens.css` — وهو ملفٌّ لمستُه
 *   أنا فيه ثلاثةَ أسطرٍ فقط. فاستعادةُ الملفِّ كاملًا من لقطتي كانت **ستُحيي
 *   تلك الأسطرَ فتمحو عملَهم**. ورجوعٌ يُتلف عملَ غيرِك ليس رجوعًا بل ضررٌ ثانٍ.
 *   فصار الرجوعُ يعكس **كتلي الستَّ والأربعين وحدَها** ويترك كتلَ غيري الاثنتَي
 *   عشرةَ كما هي. وقد جُرِّب فعلًا: عُكِس ثم أُعيد، وفي الحالتين بقي عملُهم سليمًا.
 *
 * الاستعمال:
 *   php tools/tbl_unify_rollback.php --check      ← معاينةٌ بلا لمس
 *   php tools/tbl_unify_rollback.php --apply      ← رجوعٌ جراحيٌّ (كتلُ الجولةِ وحدَها)
 *   php tools/tbl_unify_rollback.php --redo       ← إعادةُ كتلِ الجولة
 *   php tools/tbl_unify_rollback.php --apply-full ← استعادةُ الملفاتِ كاملةً
 *                                                    (⚠ يمحو ما فعله غيري فيها)
 */

$ROOT = dirname(__DIR__);
$BK   = $ROOT . '/storage/backups/tbl_unify_20260817';

$full  = in_array('--apply-full', $argv, true);
$apply = in_array('--apply', $argv, true) && !$full;
$check = in_array('--check', $argv, true);
$redo  = in_array('--redo',  $argv, true);
if (!$apply && !$check && !$redo && !$full) {
    fwrite(STDERR, "الاستعمال: --check أو --apply أو --redo أو --apply-full" . PHP_EOL);
    exit(2);
}

$PATCH   = $BK . '/round_only.patch';

/* ما أنشأته الجولةُ — يُحذف عندَ الرجوعِ ويُعاد عندَ الإعادة. */
$CREATED = array(
    'includes/table_design.php',
    'tests/table_design_unification_test.php',
    'tools/table_design_census.php',
    'tools/table_design_negative_belt.php',
    'tools/tbl_unify_split_patch.php',
    'tools/flat_gray_surfaces_audit.php',
    'tools/gray_surfaces_by_department.php',
    'tools/sticky_header_offset_probe.php',
    'tools/table_style_ownership_audit.php',
    'tools/inline_table_styles_report.php',
    'tools/inline_table_styles_sweep.php',   // فاحصُ الطبقاتِ الرماديّة (بلاغُ contracts_details)
);

/* ── الرجوعُ/الإعادةُ الجراحيّان — كتلُ الجولةِ وحدَها ── */
if (($apply || $redo) && is_file($PATCH)) {
    $rev = $apply ? ' --reverse' : '';
    $cmd = 'cd ' . escapeshellarg($ROOT) . ' && git apply' . $rev . ' --check '
         . escapeshellarg($PATCH) . ' 2>&1';
    $out = array(); $code = 0;
    exec($cmd, $out, $code);
    if ($code !== 0) {
        fwrite(STDERR, 'الرقعةُ لا تنطبق — الملفاتُ تغيّرت بعدَ إنتاجِها:' . PHP_EOL
             . implode(PHP_EOL, $out) . PHP_EOL
             . 'أعِدْ إنتاجَها: php tools/tbl_unify_split_patch.php' . PHP_EOL);
        exit(1);
    }
    exec('cd ' . escapeshellarg($ROOT) . ' && git apply' . $rev . ' '
       . escapeshellarg($PATCH) . ' 2>&1', $out2, $code2);
    if ($code2 !== 0) { fwrite(STDERR, implode(PHP_EOL, $out2) . PHP_EOL); exit(1); }

    echo ($apply ? '  ↩ عُكِست' : '  ⟳ أُعيدت') . ' كتلُ الجولةِ في ملفاتِ القائمةِ الأصلية'
       . ' — وكتلُ غيرِها لم تُمسّ.' . PHP_EOL;

    /* ── ملفاتُ الكنسِ وحدَها: استعادةٌ كاملةٌ لا رقعة ─────────────────────────
       الكنسُ **حذفٌ محضٌ** بلا سطرٍ مضاف، فلا يحمل بصمةً تميّزه — فصنّفه فارزُ
       الكتلِ «كتلَ غيري» وكان سيتركه بعدَ الرجوع. ولا يصحُّ توسيعُ البصماتِ
       لتشمل ألوانًا عامّةً (`#f8f9fa`) لأنها تُطابق عملَ غيري أيضًا.
       فهذه الملفاتُ الخمسةَ عشرَ لم يلمسها في هذه الجولةِ إلا الكنس، ونسخُها
       أُخذ **لحظةَ** تنفيذه — فاستعادتُها كاملةً دقيقةٌ لا مُقارِبة. */
    $sweepList = $BK . '/SWEEP_ONLY.txt';
    if (is_file($sweepList)) {
        $sw = array_values(array_filter(array_map('trim', file($sweepList))));
        $swN = 0;
        foreach ($sw as $rel) {
            $src = $apply ? ($BK . '/' . $rel) : ($BK . '/_after/' . $rel);
            $dst = $ROOT . '/' . $rel;
            if (!is_file($src)) { echo '  ⚠ نسخةٌ مفقودة: ' . $rel . PHP_EOL; continue; }
            if (is_file($dst) && file_get_contents($dst) === file_get_contents($src)) continue;
            if (!is_dir(dirname($dst))) mkdir(dirname($dst), 0777, true);
            copy($src, $dst);
            $swN++;
        }
        echo '  ' . ($apply ? '↩ استُعيدت' : '⟳ أُعيدت') . ' ' . $swN
           . ' من ' . count($sw) . ' ملفًّا لمسها الكنسُ وحدَه (استعادةٌ كاملة).' . PHP_EOL;
    }

    $n = 0;
    foreach ($CREATED as $rel) {
        $dst = $ROOT . '/' . $rel;
        if ($apply) {
            if (is_file($dst)) { unlink($dst); echo '  ✖ حُذف: ' . $rel . PHP_EOL; $n++; }
        } else {
            $src = $BK . '/_after/' . $rel;
            if (is_file($src)) {
                if (!is_dir(dirname($dst))) mkdir(dirname($dst), 0777, true);
                copy($src, $dst); echo '  ⟳ أُعيد: ' . $rel . PHP_EOL; $n++;
            }
        }
    }
    echo PHP_EOL . '── تمّ. ملفاتٌ ' . ($apply ? 'محذوفة' : 'معادة') . ': ' . $n . ' ──' . PHP_EOL;
    exit(0);
}

if (!is_dir($BK)) {
    fwrite(STDERR, 'لا توجد نسخةٌ احتياطية في ' . $BK . PHP_EOL);
    exit(1);
}

$listFile = $BK . '/FILELIST.txt';
if (!is_file($listFile)) {
    fwrite(STDERR, 'قائمةُ الملفاتِ مفقودة: ' . $listFile . PHP_EOL);
    exit(1);
}

$rows = array_values(array_filter(array_map('trim', file($listFile))));

/* الملفاتُ التي أنشأتها الجولةُ ولم تكن موجودةً قبلَها — تُحذف لا تُستعاد.
   (هذه الأداةُ نفسُها ليست في القائمة: حذفُ نفسِها أثناءَ عملِها عبثٌ، وبقاؤها
   بلا نسخةٍ احتياطيةٍ غيرُ ضارّ — احذفها يدويًّا إن أردتَ محوَ كلِّ أثر.) */
$created = $CREATED;   // المصدرُ واحدٌ — قائمتان تتفرّقان بعدَ تعديلَين

$restored = 0; $same = 0; $missing = 0; $deleted = 0;

foreach ($rows as $rel) {
    $src = $BK . '/' . $rel;
    $dst = $ROOT . '/' . $rel;
    if (!is_file($src)) { echo '  ⚠ نسخةٌ مفقودة: ' . $rel . PHP_EOL; $missing++; continue; }
    $a = file_get_contents($src);
    $b = is_file($dst) ? file_get_contents($dst) : null;
    if ($b !== null && $a === $b) { $same++; continue; }
    if ($apply) {
        if (!is_dir(dirname($dst))) mkdir(dirname($dst), 0777, true);
        file_put_contents($dst, $a);
    }
    echo ($apply ? '  ↩ استُعيد: ' : '  • سيُستعاد: ') . $rel . PHP_EOL;
    $restored++;
}

foreach ($created as $rel) {
    $dst = $ROOT . '/' . $rel;
    if (!is_file($dst)) continue;
    if ($apply) unlink($dst);
    echo ($apply ? '  ✖ حُذف: ' : '  • سيُحذف: ') . $rel . PHP_EOL;
    $deleted++;
}

echo PHP_EOL . '── الحصيلة ──' . PHP_EOL;
echo '  ' . ($apply ? 'استُعيد' : 'سيُستعاد') . ': ' . $restored . PHP_EOL;
echo '  ' . ($apply ? 'حُذف'   : 'سيُحذف')  . ': ' . $deleted  . PHP_EOL;
echo '  لم يتغيّر أصلًا: ' . $same . PHP_EOL;
if ($missing) echo '  نسخٌ مفقودة: ' . $missing . PHP_EOL;
if (!$apply) echo PHP_EOL . '(معاينةٌ فقط — أضف --apply للتنفيذ)' . PHP_EOL;
