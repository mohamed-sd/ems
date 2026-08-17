<?php
/**
 * tools/actions_col_gate.php — بوابةُ إثباتِ جولةِ «الإجراءاتُ أوّلًا».
 *
 * أربعةُ أحكامٍ على كلِّ جدولٍ هدف:
 *   G1  عمودُ «الإجراءات» في الموضعِ صفرٍ بين الرؤوسِ غيرِ المحقونة.
 *   G2  عددُ خلايا كلِّ صفٍّ = عددُ الرؤوسِ غيرِ المحقونة (لا إزاحةَ صامتة).
 *   G3  لا رأسَ عدّادٍ تسلسليٍّ (`#`/`م`) باقيًا في الجدول.
 *   G4  الملفُّ يُحلَّل بلا خطأٍ نحويّ.
 *
 * ⚠ بوابةٌ لا تُجرَّب معطوبةً لا تُصدَّق. فـ`--selftest` يفسد نسخةً في الذاكرة
 *    ويتأكد أن كلَّ حكمٍ من الأربعةِ يسقط فعلًا — قبل أن يُقرأ مرورُه دليلًا.
 */

require __DIR__ . '/actions_col_lib.php';

$ROOT = dirname(__DIR__);
$targets = json_decode(file_get_contents(__DIR__ . '/actions_col_targets.json'), true);
$selftest = in_array('--selftest', $argv, true);
$verbose  = in_array('-v', $argv, true);

/** يفحص نصَّ ملفٍّ ويُرجع أحكامَ جداولِه الهدف. */
function acg_judge($src)
{
    $sh = acl_php_shadow($src);
    $out = [];
    foreach (acl_find_elements($sh, 'table', 0, strlen($sh)) as $ti => $tb) {
        if ($tb['oe'] === null) continue;
        $rows = acl_find_elements($sh, 'tr', $tb['os'], $tb['oe']);
        if (!$rows) continue;
        $hIdx = null; $head = null;
        foreach ($rows as $ri => $r) {
            if ($r['oe'] === null) continue;
            $cells = acl_row_cells($sh, $r['os'], $r['oe']);
            if (!$cells) continue;
            foreach ($cells as $c) if (strcasecmp(substr($sh, $c['s'] + 1, 2), 'th') === 0) { $hIdx = $ri; $head = $cells; break 2; }
        }
        if ($head === null) continue;

        $plain = []; $k = -1; $ctr = -1;
        foreach ($head as $i => $c) {
            if (acl_is_injected_head($src, $c)) continue;
            $txt = acl_cell_text($src, $c);
            $plain[] = $txt;
            if ($k < 0 && acl_is_actions($txt)) $k = count($plain) - 1;
            if ($ctr < 0 && acl_is_counter_head($txt)) $ctr = count($plain) - 1;
        }
        if ($k < 0) continue;                       // ليس جدولًا هدفًا

        $expect = count($plain);
        $scan = $sh; $hStart = $rows[$hIdx]['s']; $rws = $rows;
        if (count($rows) < 2) {
            $raw = acl_find_elements($src, 'tr', $tb['os'], $tb['oe']);
            if (count($raw) > count($rows)) { $scan = $src; $rws = $raw; }
        }
        $bad = []; $nrows = 0; $autoCtr = false;
        foreach ($rws as $r) {
            if ($r['s'] === $hStart || $r['oe'] === null) continue;
            $cells = acl_row_cells($scan, $r['os'], $r['oe']);
            if (!$cells) continue;
            if (count($cells) === 1) {
                $open = substr($src, $cells[0]['s'], $cells[0]['os'] - $cells[0]['s']);
                if (preg_match('/colspan/i', $open)) continue;
            }
            $nrows++;
            if (count($cells) !== $expect) { $bad[] = count($cells) . '≠' . $expect; continue; }
            /* G3 يحكم على **العدّادِ الآليّ** لا على كلِّ عمودٍ رأسُه «#».
               عمودُ `#` الذي يطبع `id` من السجلِّ بيانٌ حقيقيٌّ يبقى؛ والذي
               يطبع `$i++` زخرفةٌ تُحذَف. والفرقُ في الخليةِ لا في الرأس. */
            if ($ctr >= 0 && isset($cells[$ctr])) {
                $body = substr($src, $cells[$ctr]['os'], $cells[$ctr]['oe'] - $cells[$ctr]['os']);
                if (acl_is_counter_cell($body)) $autoCtr = true;
            }
        }
        $out[] = ['i' => $ti + 1, 'k' => $k, 'ctr' => $ctr, 'cols' => $expect,
                  'rows' => $nrows, 'bad' => $bad, 'autoCtr' => $autoCtr,
                  'G1' => ($k === 0), 'G2' => empty($bad), 'G3' => !$autoCtr];
    }
    return $out;
}

/* ───────── تجربةُ البوابةِ معطوبةً ───────── */
if ($selftest) {
    $probe = "<table><thead><tr><th>الإجراءات</th><th>الاسم</th></tr></thead>"
           . "<tbody><tr><td>ز</td><td>ن</td></tr></tbody></table>";
    $cases = [
        'سليم'                 => [$probe, true,  true,  true],
        'G1 الإجراءاتُ ليست أوّلًا' => [str_replace('<tr><th>الإجراءات</th><th>الاسم</th>', '<tr><th>الاسم</th><th>الإجراءات</th>', $probe), false, true, true],
        'G2 خليةٌ زائدة'        => [str_replace('<td>ن</td>', '<td>ن</td><td>س</td>', $probe), true, false, true],
        /* العدّادُ في التجربةِ لا بدَّ أن يكون **آليًّا** (`$i++`) لا رقمًا مطبوعًا،
           وإلا اختُبر حكمٌ غيرُ الحكمِ الذي تحرسه البوابة. */
        'G3 عدّادٌ آليٌّ باقٍ'     => [str_replace('<tr><th>الإجراءات</th>', '<tr><th>الإجراءات</th><th>#</th>',
                                       str_replace('<td>ز</td>', '<td>ز</td><td><?php echo $i++; ?></td>', $probe)), true, true, false],
        'G3 عمودُ # بيانٌ حقيقيّ'  => [str_replace('<tr><th>الإجراءات</th>', '<tr><th>الإجراءات</th><th>#</th>',
                                       str_replace('<td>ز</td>', '<td>ز</td><td><?php echo $row["id"]; ?></td>', $probe)), true, true, true],
    ];
    $fail = 0;
    foreach ($cases as $name => $c) {
        $j = acg_judge($c[0]);
        if (!$j) { echo "  ✘ $name — لم يُقرأ جدولٌ أصلًا\n"; $fail++; continue; }
        $t = $j[0];
        $ok = ($t['G1'] === $c[1] && $t['G2'] === $c[2] && $t['G3'] === $c[3]);
        printf("  %s %-24s G1=%s G2=%s G3=%s (المنتظَر %s%s%s)\n", $ok ? '✔' : '✘', $name,
            $t['G1'] ? 'ص' : 'خ', $t['G2'] ? 'ص' : 'خ', $t['G3'] ? 'ص' : 'خ',
            $c[1] ? 'ص' : 'خ', $c[2] ? 'ص' : 'خ', $c[3] ? 'ص' : 'خ');
        if (!$ok) $fail++;
    }
    echo $fail ? "\n✘ البوابةُ لا تُصدَّق — $fail حالةً لم تسقط كما يجب\n" : "\n✔ البوابةُ تسقط حين يجب أن تسقط\n";
    exit($fail ? 1 : 0);
}

/* ───────── الحكمُ على النطاق ───────── */
$php = PHP_BINARY;
$n = ['tbl' => 0, 'g1' => 0, 'g2' => 0, 'g3' => 0, 'g4' => 0, 'files' => 0];
$fails = [];
foreach ($targets as $rel) {
    $path = $ROOT . '/' . $rel;
    if (!is_file($path)) { $fails[] = "$rel — مفقود"; continue; }
    $src = file_get_contents($path);
    $j = acg_judge($src);
    if (!$j) continue;
    $n['files']++;

    $lint = [];
    exec(escapeshellarg($php) . ' -l ' . escapeshellarg($path) . ' 2>&1', $lint, $rc);
    if ($rc !== 0) { $fails[] = "$rel — G4 خطأٌ نحويّ: " . implode(' ', $lint); } else $n['g4']++;

    foreach ($j as $t) {
        $n['tbl']++;
        if ($t['G1']) $n['g1']++; else $fails[] = "$rel tbl#{$t['i']} — G1 موضعُ الإجراءات {$t['k']} لا 0";
        if ($t['G2']) $n['g2']++; else $fails[] = "$rel tbl#{$t['i']} — G2 إزاحة: " . implode(',', array_slice($t['bad'], 0, 3));
        if ($t['G3']) $n['g3']++; else $fails[] = "$rel tbl#{$t['i']} — G3 عدّادٌ آليٌّ باقٍ في الموضع {$t['ctr']}";
        if ($verbose) printf("  %-46s tbl#%d k=%d cols=%d rows=%d\n", $rel, $t['i'], $t['k'], $t['cols'], $t['rows']);
    }
}
echo "══════════ بوابةُ «الإجراءاتُ أوّلًا» ══════════\n";
printf("ملفات   : %d\n", $n['files']);
printf("جداول   : %d\n", $n['tbl']);
printf("G1 الصدارة        : %d/%d\n", $n['g1'], $n['tbl']);
printf("G2 لا إزاحة       : %d/%d\n", $n['g2'], $n['tbl']);
printf("G3 لا عدّاد        : %d/%d\n", $n['g3'], $n['tbl']);
printf("G4 نحوٌ سليم       : %d/%d\n", $n['g4'], $n['files']);
if ($fails) {
    echo "\n── إخفاقات (" . count($fails) . ") ──\n";
    foreach ($fails as $f) echo "  $f\n";
    exit(1);
}
echo "\n✔ صفرُ إخفاق\n";
