<?php
/**
 * tools/actions_col_counter_sweep.php — كنسُ العدّادِ التسلسليِّ الآليِّ من **كلِّ**
 * جداولِ الصفحاتِ المئةِ والثمان، لا من جداولِ عمودِ الإجراءاتِ وحدَها.
 *
 * العدّادُ الآليُّ رقمٌ يُولَد في العرضِ (`$i++`) لا بيانٌ في السجل. وعمودُ `#`
 * الذي يطبع `id` أو رقمَ مستندٍ حقيقيٍّ **يبقى** — الفرقُ في الخليةِ لا في الرأس.
 *
 *   php tools/actions_col_counter_sweep.php --check
 *   php tools/actions_col_counter_sweep.php --apply
 */

require __DIR__ . '/actions_col_lib.php';

$ROOT  = dirname(__DIR__);
$BK    = $ROOT . '/storage/backups/actions_col_20260817';
$apply = in_array('--apply', $argv, true);
$targets = json_decode(file_get_contents(__DIR__ . '/actions_col_targets.json'), true);

$found = 0; $files = 0; $done = 0; $manual = [];

foreach ($targets as $rel) {
    $path = $ROOT . '/' . $rel;
    $src  = file_get_contents($path);
    $sh   = acl_php_shadow($src);
    $hits = [];

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

        /* موضعُ رأسِ العدّادِ بين الرؤوسِ غيرِ المحقونة */
        $plainIdx = -1; $ctr = -1; $ctrCell = null;
        foreach ($head as $c) {
            if (acl_is_injected_head($src, $c)) continue;
            $plainIdx++;
            if ($ctr < 0 && acl_is_counter_head(acl_cell_text($src, $c))) { $ctr = $plainIdx; $ctrCell = $c; }
        }
        if ($ctr < 0) continue;
        $expect = $plainIdx + 1;

        $scan = $sh; $rws = $rows; $hStart = $rows[$hIdx]['s'];
        if (count($rows) < 2) {
            $raw = acl_find_elements($src, 'tr', $tb['os'], $tb['oe']);
            if (count($raw) > count($rows)) { $scan = $src; $rws = $raw; }
        }
        foreach ($rws as $r) {
            if ($r['s'] === $hStart || $r['oe'] === null) continue;
            $cells = acl_row_cells($scan, $r['os'], $r['oe']);
            if (!$cells || count($cells) === 1) continue;
            if (count($cells) !== $expect) { $manual[] = "$rel tbl#" . ($ti + 1) . " — خلايا " . count($cells) . "≠$expect"; break; }
            $body = substr($src, $cells[$ctr]['os'], $cells[$ctr]['oe'] - $cells[$ctr]['os']);
            if (!acl_is_counter_cell($body)) break;              // `#` بيانٌ حقيقيّ — يبقى
            $span = substr($src, $cells[$ctr]['s'], $cells[$ctr]['e'] - $cells[$ctr]['s']);
            if (!acl_span_safe($span) || !acl_span_safe(substr($src, $ctrCell['s'], $ctrCell['e'] - $ctrCell['s']))) {
                $manual[] = "$rel tbl#" . ($ti + 1) . ' — مدًى غيرُ متوازن'; break;
            }
            $hits[] = ['th' => $ctrCell, 'td' => $cells[$ctr], 'tbl' => $ti + 1];
            $found++;
            break;
        }
    }
    if (!$hits) continue;
    $files++;
    echo $rel . "\n";
    foreach ($hits as $h) echo '    tbl#' . $h['tbl'] . "  ⇒ حذفُ عمودِ العدّاد\n";

    if ($apply) {
        $ops = [];
        foreach ($hits as $h) foreach (['th', 'td'] as $kk) {
            $c = $h[$kk];
            $p = $c['s'] - 1;
            while ($p >= 0 && ($src[$p] === ' ' || $src[$p] === "\t")) $p--;
            $from = ($p >= 0 && $src[$p] === "\n") ? $p : $c['s'];
            $ops[] = [$from, $c['e']];
        }
        usort($ops, fn($a, $b) => $a[0] <=> $b[0]);
        for ($i = 1; $i < count($ops); $i++) if ($ops[$i][0] < $ops[$i - 1][1]) { echo "    ✘ تداخل — تُرك\n"; continue 2; }
        $bk = $BK . '/' . $rel;
        if (!is_file($bk)) { @mkdir(dirname($bk), 0777, true); file_put_contents($bk, $src); }
        for ($i = count($ops) - 1; $i >= 0; $i--) $src = substr_replace($src, '', $ops[$i][0], $ops[$i][1] - $ops[$i][0]);
        file_put_contents($path, $src);
        $done++;
    }
}
echo "\n══════════ كنسُ العدّاد ══════════\n";
echo "أعمدةُ عدّادٍ آليٍّ وُجدت : $found في $files ملفًّا\n";
if ($apply) echo "ملفاتٌ كُتِبت            : $done\n";
if ($manual) { echo "\n── تُركت (لا يُقاس صفُّها) ──\n"; foreach (array_unique($manual) as $m) echo "  $m\n"; }
