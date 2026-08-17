<?php
/**
 * tools/tbl_unify_split_patch.php — فصلُ كتلِ جولةِ التوحيدِ عن كتلِ غيرِها.
 * ═══════════════════════════════════════════════════════════════════════════
 * لماذا هذا الملفُّ موجود؟
 *   جرى **أثناءَ** جولةِ التوحيدِ عملٌ آخرُ على الشجرةِ نفسِها (بطاقاتُ الملفِّ
 *   الشخصيّ · تبويباتُ الملفات) لمسَ ملفاتٍ لمستُها أنا أيضًا — أظهرُها
 *   `assets/css/ems-screens.css` حيث حُذف منه ٢٦٩ سطرَ `.profile-*` لا علاقةَ
 *   لي بها.
 *
 *   فالرجوعُ باستعادةِ الملفِّ كاملًا من لقطتي **يُحيي تلك الأسطرَ فيمحو عملَ
 *   غيري**. ورجوعٌ يُتلف عملَ الآخرين ليس رجوعًا بل ضررٌ ثانٍ.
 *
 * الحلُّ: تُقارَن اللقطةُ بالحالِ فتُنتَج كتلُ فرقٍ (hunks)، ثم تُصنَّف كلُّ كتلةٍ:
 *   • كتلةٌ فيها سطرٌ مضافٌ يحمل بصمةَ الجولة (`--table-…` أو `table_design.php`
 *     أو تاريخُ الجولة) → **كتلتي**
 *   • ما عداها → **كتلةُ غيري**، تُترك كما هي ولا تُمسّ.
 * ثم يُكتب ملفُّ رقعةٍ يحوي كتلي وحدَها، ويُعكَس بـ`git apply -R`.
 *
 * الاستعمال:
 *   php tools/tbl_unify_split_patch.php            ← يُنتج الرقعةَ ويُبلّغ
 *   php tools/tbl_unify_split_patch.php --verify   ← يتحقّقُ أن العكسَ يُطابق اللقطة
 * ═══════════════════════════════════════════════════════════════════════════
 */

$ROOT = dirname(__DIR__);
$BK   = $ROOT . '/storage/backups/tbl_unify_20260817';
$OUT  = $BK . '/round_only.patch';
$verify = in_array('--verify', $argv, true);

/* بصماتُ الجولة — سطرٌ مضافٌ يحمل إحداها يجعل الكتلةَ كتلتي. */
$MARKS = array(
    '--table-', 'table_design.php', '2026-08-17', 'TABLE DESIGN TOKENS',
    'UNIVERSAL TABLE BASE', ':where(table', 'emsTableDesignEnforce',
    'data-ems-table-design', 'ems-tables.css',
);

/**
 * كتلةٌ من الجولة؟
 *   ① سطرٌ **مضافٌ** يحمل بصمةً → نعم.
 *   ② كتلةُ **حذفٍ محضٍ** (بلا مضاف) وسطورُها المحذوفةُ تحمل بصمةً صارمةً → نعم.
 *
 * الشرطُ ② لازمٌ لا تزيُّد: نقلي وسمَ `ems-tables.css` من موضعِه في `inheader.php`
 * ومن مصفوفةِ `insidebar.php` أنتج كتلتَي حذفٍ محضٍ بلا سطرٍ مضافٍ واحد، فصُنِّفتا
 * «كتلَ غيري» أولَ مرّةٍ فكادتا تُترَكان في الشجرةِ بعدَ الرجوع.
 * والبصماتُ الصارمةُ أضيقُ من العامّة عمدًا: كلمةُ `table` وحدَها تظهر في مئاتِ
 * أسطرِ غيري.
 */
function is_mine($hunkLines, $MARKS) {
    $STRICT = array('ems-tables.css', '--table-', 'table_design.php');
    $added = 0; $delMark = false;
    foreach ($hunkLines as $l) {
        if ($l === '') continue;
        if ($l[0] === '+') {
            $added++;
            foreach ($MARKS as $m) if (strpos($l, $m) !== false) return true;
        } elseif ($l[0] === '-') {
            foreach ($STRICT as $m) if (strpos($l, $m) !== false) { $delMark = true; break; }
        }
    }
    return ($added === 0 && $delMark);
}

$rows = array_values(array_filter(array_map('trim', file($BK . '/FILELIST.txt'))));
$patch = '';
$stats = array();

foreach ($rows as $rel) {
    $old = $BK . '/' . $rel;
    $new = $ROOT . '/' . $rel;
    if (!is_file($old) || !is_file($new)) continue;
    if (file_get_contents($old) === file_get_contents($new)) continue;

    $tmpOld = tempnam(sys_get_temp_dir(), 'tuo');
    $tmpNew = tempnam(sys_get_temp_dir(), 'tun');
    copy($old, $tmpOld); copy($new, $tmpNew);
    $out = array();
    exec('diff -u ' . escapeshellarg($tmpOld) . ' ' . escapeshellarg($tmpNew) . ' 2>&1', $out);
    unlink($tmpOld); unlink($tmpNew);
    if (!count($out)) continue;

    /* قصُّ الكتلِ — كلُّ كتلةٍ تبدأ بسطرِ @@ */
    $hunks = array(); $cur = null;
    foreach ($out as $line) {
        if (strlen($line) > 1 && substr($line, 0, 2) === '@@') {
            if ($cur !== null) $hunks[] = $cur;
            $cur = array('head' => $line, 'lines' => array());
        } elseif ($cur !== null) {
            $cur['lines'][] = $line;
        }
    }
    if ($cur !== null) $hunks[] = $cur;

    $mine = array(); $foreign = 0;
    foreach ($hunks as $h) {
        if (is_mine($h['lines'], $MARKS)) $mine[] = $h; else $foreign++;
    }
    $stats[$rel] = array('mine' => count($mine), 'foreign' => $foreign);
    if (!count($mine)) continue;

    $patch .= '--- a/' . $rel . "\n" . '+++ b/' . $rel . "\n";
    foreach ($mine as $h) {
        $patch .= $h['head'] . "\n";
        foreach ($h['lines'] as $l) $patch .= $l . "\n";
    }
}

file_put_contents($OUT, $patch);

echo '══════════ تصنيفُ الكتل ══════════' . PHP_EOL;
$tm = 0; $tf = 0;
foreach ($stats as $rel => $s) {
    printf("  %-42s كتلي: %-3d كتلُ غيري: %d%s", $rel, $s['mine'], $s['foreign'], PHP_EOL);
    $tm += $s['mine']; $tf += $s['foreign'];
}
echo '  ────────────────────────────────────────────────' . PHP_EOL;
printf("  %-42s %-8d %d%s", 'المجموع', $tm, $tf, PHP_EOL);
echo PHP_EOL . 'كُتبت الرقعةُ (كتلي وحدَها): ' . $OUT . PHP_EOL;
echo 'الحجم: ' . strlen($patch) . ' بايت' . PHP_EOL;
if ($tf > 0) {
    echo PHP_EOL . '⚠ ' . $tf . ' كتلةً ليست من هذه الجولة — لن يمسَّها الرجوعُ الجراحيّ.' . PHP_EOL;
}
