<?php
/**
 * tools/actions_col_first.php — جعلُ عمودِ «الإجراءات» أوّلَ عمودٍ في الجدول،
 * وحذفُ عمودِ العدّادِ التسلسليِّ الآليِّ (`#` / `م`) حيثما وُجد.
 *
 * النطاقُ: الصفحاتُ الثمانُ ومئةٌ التي فيها جدولٌ بعمودٍ مسمًّى «الإجراءات».
 *
 * القاعدةُ الحاكمة: **لا يُنقَل رأسٌ إلا ومعه خليتُه في كلِّ صف.** فإن عجز
 * الماسحُ عن مطابقةِ عددِ الخلايا بعددِ الرؤوسِ في صفٍّ واحدٍ رُفض الجدولُ كلُّه
 * ووُسم يدويًّا. لأنَّ نقلَ رأسٍ بلا خليتِه يزيح كلَّ الأعمدةِ بعدَه صامتًا:
 * يُقرأ «الحالة» في خانةِ «المبلغ» ولا يُرمى خطأ.
 *
 * الاستعمال:
 *   php tools/actions_col_first.php --check          ← جردٌ بلا لمس
 *   php tools/actions_col_first.php --apply          ← تنفيذٌ مع لقطةِ رجوع
 *   php tools/actions_col_first.php --check --file=X ← ملفٌّ واحد
 *   php tools/actions_col_first.php --no-counter     ← انقلْ فقط، لا تحذفِ العدّاد
 */

require __DIR__ . '/actions_col_lib.php';

$ROOT  = dirname(__DIR__);
$STAMP = 'actions_col_20260817';
$BK    = $ROOT . '/storage/backups/' . $STAMP;

$apply     = in_array('--apply', $argv, true);
$check     = in_array('--check', $argv, true) || !$apply;
$noCounter = in_array('--no-counter', $argv, true);
$only      = null;
foreach ($argv as $a) if (strpos($a, '--file=') === 0) $only = substr($a, 7);

$listFile = __DIR__ . '/actions_col_targets.json';
if (!is_file($listFile)) { fwrite(STDERR, "قائمةُ الأهدافِ مفقودة: $listFile\n"); exit(2); }
$targets = json_decode(file_get_contents($listFile), true);
if ($only) $targets = array_values(array_filter($targets, fn($t) => $t === $only));

/* ─────────────── تحليلُ ملفٍّ واحد ─────────────── */
function acl_plan_file($ROOT, $rel, $noCounter)
{
    $path = $ROOT . '/' . $rel;
    $src  = file_get_contents($path);
    $sh   = acl_php_shadow($src);
    $plan = ['rel' => $rel, 'tables' => [], 'edits' => []];

    $tables = acl_find_elements($sh, 'table', 0, strlen($sh));
    foreach ($tables as $ti => $tb) {
        if ($tb['oe'] === null) continue;
        $rows = acl_find_elements($sh, 'tr', $tb['os'], $tb['oe']);
        if (!$rows) continue;

        /* صفُّ الرأس: أولُ صفٍّ فيه th */
        $hIdx = null; $head = null;
        foreach ($rows as $ri => $r) {
            if ($r['oe'] === null) continue;
            $cells = acl_row_cells($sh, $r['os'], $r['oe']);
            if (!$cells) continue;
            $anyTh = false;
            foreach ($cells as $c) if (strcasecmp(substr($sh, $c['s'] + 1, 2), 'th') === 0) $anyTh = true;
            if ($anyTh) { $hIdx = $ri; $head = $cells; break; }
        }
        if ($head === null) continue;

        /* موضعُ عمودِ الإجراءات + عددُ الرؤوسِ المحقونة */
        $k = -1; $inj = 0; $ctrIdx = -1;
        foreach ($head as $i => $c) {
            $txt = acl_cell_text($src, $c);
            if (acl_is_injected_head($src, $c)) { $inj++; continue; }
            if ($k < 0 && acl_is_actions($txt)) $k = $i;
            if ($ctrIdx < 0 && acl_is_counter_head($txt)) $ctrIdx = $i;
        }
        if ($k < 0) continue;                       // لا عمودَ إجراءاتٍ في هذا الجدول
        $expect = count($head) - $inj;

        $t = ['i' => $ti + 1, 'k' => $k, 'cols' => count($head), 'inj' => $inj,
              'expect' => $expect, 'ctr' => $ctrIdx, 'rows' => 0, 'ph' => 0,
              'bad' => 0, 'badInfo' => [], 'ok' => true, 'why' => ''];

        if ($k === 0 && ($ctrIdx < 0 || $noCounter)) { $t['why'] = 'مطابقٌ سلفًا'; $t['ok'] = false; $plan['tables'][] = $t; continue; }

        /* ── الوضعُ الخام ──
           صفوفُ بعضِ الجداولِ تُطبَع من داخلِ PHP: `echo '<tr><td>' . $x . '</td>'`.
           فالظلُّ يُخفيها كلَّها ولا يُرى إلا صفُّ الرأس. عندئذٍ نُعيد المسحَ على
           النصِّ الخامِ نفسِه — والحارسُ الذي يقارن عددَ الخلايا بعددِ الرؤوسِ يبقى
           قائمًا، فما لا يتطابق يُرفض كما هو. */
        $scan = $sh;
        $headStart = $rows[$hIdx]['s'];
        if (count($rows) < 2) {
            $rawRows = acl_find_elements($src, 'tr', $tb['os'], $tb['oe']);
            if (count($rawRows) > count($rows)) {
                $scan = $src; $rows = $rawRows; $t['raw'] = true;
                /* صفُّ الرأسِ يُعرَف بموضعِه لا برقمِه — الترقيمُ تغيّر بتغيّرِ المسح */
                $hIdx = null;
                foreach ($rows as $ri => $r) if ($r['s'] === $headStart) { $hIdx = $ri; break; }
                if ($hIdx === null) { $t['ok'] = false; $t['why'] = 'صفُّ الرأسِ ضاع في المسحِ الخام'; $plan['tables'][] = $t; continue; }
            }
        }

        $cellMoves = [];   // خلايا كلِّ صفٍّ صالحٍ للنقل
        $colspans  = [];

        foreach ($rows as $ri => $r) {
            if ($ri === $hIdx || $r['oe'] === null) continue;
            $cells = acl_row_cells($scan, $r['os'], $r['oe']);
            if ($cells === null) { $t['ok'] = false; $t['why'] = 'خليةٌ غيرُ مغلَقة'; break; }
            if (!$cells) continue;
            $t['rows']++;

            /* صفُّ «لا بيانات» — خليةٌ واحدةٌ بـcolspan */
            if (count($cells) === 1) {
                $open = substr($src, $cells[0]['s'], $cells[0]['os'] - $cells[0]['s']);
                if (preg_match('/colspan\s*=\s*["\']?(\d+)/i', $open, $m)) {
                    $t['ph']++; $colspans[] = [$cells[0]['s'], $cells[0]['os'], intval($m[1])];
                    continue;
                }
            }
            if (count($cells) !== $expect) {
                $t['bad']++;
                if (count($t['badInfo']) < 3) $t['badInfo'][] = count($cells) . '≠' . $expect;
                continue;
            }
            $cellMoves[] = $cells;
        }

        if ($t['bad'] > 0) { $t['ok'] = false; $t['why'] = 'صفوفٌ لا تطابق عددَ الرؤوس: ' . implode(',', $t['badInfo']); }
        if ($t['rows'] === 0) { $t['ok'] = false; $t['why'] = 'لا صفوفَ جسمٍ مقروءة'; }

        /* كلُّ مدًى يُنقَل أو يُحذَف يجب أن يكون متوازنَ الاقتباسِ والوسوم */
        if ($t['ok']) {
            foreach (array_merge([$head], $cellMoves) as $cells) {
                foreach ([$k, $ctrIdx] as $idx) {
                    if ($idx < 0 || !isset($cells[$idx])) continue;
                    $span = substr($src, $cells[$idx]['s'], $cells[$idx]['e'] - $cells[$idx]['s']);
                    if (!acl_span_safe($span)) {
                        $t['ok'] = false;
                        $t['why'] = 'مدًى غيرُ متوازنٍ لا يصحُّ نقلُه: ' . mb_substr(trim(preg_replace('/\s+/', ' ', $span)), 0, 60);
                        break 2;
                    }
                }
            }
        }

        /* هل خليةُ العدّادِ عدّادٌ آليٌّ فعلًا؟ */
        $killCtr = false;
        if (!$noCounter && $ctrIdx >= 0 && $t['ok'] && $cellMoves) {
            $probe = $cellMoves[0][$ctrIdx];
            $body  = substr($src, $probe['os'], $probe['oe'] - $probe['os']);
            $killCtr = acl_is_counter_cell($body);
            $t['ctrBody'] = trim(preg_replace('/\s+/', ' ', $body));
            $t['ctrKill'] = $killCtr;
        }

        $t['moves'] = count($cellMoves);
        $t['killCtr'] = $killCtr;
        $plan['tables'][] = $t;
        if ($t['ok']) {
            $plan['edits'][] = ['head' => $head, 'rows' => $cellMoves, 'k' => $k,
                                'ctr' => $killCtr ? $ctrIdx : -1, 'spans' => $colspans,
                                'need' => ($k !== 0) || $killCtr];
        }
    }
    $plan['src'] = $src;
    return $plan;
}

/* ─────────────── تطبيقُ الخطةِ على نصِّ الملف ─────────────── */
function acl_rewrite($src, $edits, &$err = null)
{
    /* بدايةُ الحذفِ: ابتلعِ المسافةَ البيضاءَ التي تسبق الخليةَ حتى رأسِ السطر،
       فلا يبقى سطرٌ فارغٌ مكانَ عمودٍ رُفِع. */
    $delStart = function ($pos) use ($src) {
        $p = $pos - 1;
        while ($p >= 0 && ($src[$p] === ' ' || $src[$p] === "\t")) $p--;
        return ($p >= 0 && $src[$p] === "\n") ? $p : $pos;
    };
    /* إزاحةُ سطرِ خليةٍ ما — لتُطبَع المنقولةُ بمحاذاةِ أخواتِها */
    $indentOf = function ($pos) use ($src) {
        $ind = ''; $q = $pos - 1;
        while ($q >= 0 && ($src[$q] === ' ' || $src[$q] === "\t")) { $ind = $src[$q] . $ind; $q--; }
        return ($q >= 0 && $src[$q] === "\n") ? $ind : null;
    };

    $ops = [];
    foreach ($edits as $e) {
        if (!$e['need']) continue;
        $k = $e['k']; $ctr = $e['ctr'];
        foreach (array_merge([$e['head']], $e['rows']) as $cells) {
            if (!isset($cells[$k])) continue;
            $act    = $cells[$k];
            $actTxt = substr($src, $act['s'], $act['e'] - $act['s']);
            $first  = $cells[0];

            if ($k !== 0) {
                /* ① ارفعِ الخليةَ من موضعِها القديم */
                $ops[] = [$delStart($act['s']), $act['e'], ''];

                /* ② ضَعْها في الصدارة.
                   وإن كان عمودُ العدّادِ هو الأولَ وسيُحذَف، **فاستبدلْه بها**
                   بدل الإدراجِ قبلَه ثم حذفِه: عمليتان على مدًى واحدٍ تتنازعان،
                   والثانيةُ تبتلع ما كتبته الأولى. (هذا ما وقع فعلًا في
                   company/team.php فخرج «<tr>جراءات</th>» — فصار الاستبدالُ
                   عمليةً واحدةً لا اثنتين.) */
                if ($ctr === 0) {
                    $ops[] = [$first['s'], $first['e'], $actTxt];
                } else {
                    $ind  = $indentOf($first['s']);
                    $lead = ($ind !== null) ? ($actTxt . "\n" . $ind) : ($actTxt . ' ');
                    $ops[] = [$first['s'], $first['s'], $lead];
                }
            }

            /* ③ احذفْ عمودَ العدّادِ إن لم يكن قد استُبدل في ② */
            if ($ctr >= 0 && isset($cells[$ctr]) && !($ctr === 0 && $k !== 0)) {
                $c = $cells[$ctr];
                $ops[] = [$delStart($c['s']), $c['e'], ''];
            }
        }
        /* ④ صحّحِ الـcolspan في صفوفِ «لا بيانات» — عمودٌ نقص فالمدى نقص */
        if ($ctr >= 0) {
            foreach ($e['spans'] as $sp) {
                list($s0, $s1, $n) = $sp;
                $open = substr($src, $s0, $s1 - $s0);
                $new  = preg_replace('/(colspan\s*=\s*["\']?)(\d+)/i', '${1}' . max(1, $n - 1), $open, 1);
                $ops[] = [$s0, $s1, $new];
            }
        }
    }

    /* حارسُ التداخل: عمليتان تتقاطعان تعنيان نصًّا مأكولًا لا ينبّه عنه أحد.
       نرفض الملفَّ كلَّه بدل أن نكتب فيه تلفًا صامتًا. */
    usort($ops, fn($a, $b) => ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]));
    for ($i = 1; $i < count($ops); $i++) {
        $prev = $ops[$i - 1]; $cur = $ops[$i];
        if ($cur[0] < $prev[1] || ($cur[0] === $prev[0] && $prev[0] !== $prev[1])) {
            $err = sprintf('تداخلُ عمليتين عند %d..%d و %d..%d', $prev[0], $prev[1], $cur[0], $cur[1]);
            return null;
        }
    }

    /* التطبيقُ من الآخِرِ إلى الأوّل فلا تنزاح المواضع */
    for ($i = count($ops) - 1; $i >= 0; $i--) {
        $o = $ops[$i];
        $src = substr_replace($src, $o[2], $o[0], $o[1] - $o[0]);
    }
    return $src;
}

/* ─────────────── التشغيل ─────────────── */
$sum = ['files' => 0, 'tbl' => 0, 'okTbl' => 0, 'skipTbl' => 0, 'badTbl' => 0,
        'moved' => 0, 'ctr' => 0, 'touched' => 0];
$manual = [];

if ($apply && !is_dir($BK)) @mkdir($BK, 0777, true);

foreach ($targets as $rel) {
    $plan = acl_plan_file($ROOT, $rel, $noCounter);
    if (!$plan['tables']) continue;
    $sum['files']++;
    $lines = [];
    foreach ($plan['tables'] as $t) {
        $sum['tbl']++;
        if ($t['ok']) {
            $sum['okTbl']++;
            if ($t['k'] !== 0) $sum['moved']++;
            if (!empty($t['killCtr'])) $sum['ctr']++;
            $lines[] = sprintf('    tbl#%d  k=%d/%d  صفوف=%d  نائبة=%d  %s%s',
                $t['i'], $t['k'], $t['cols'] - 1, $t['rows'], $t['ph'],
                $t['k'] !== 0 ? '⇒ نقل' : '⇒ مكانُه صحيح',
                !empty($t['killCtr']) ? ' + حذفُ العدّاد' : '');
        } elseif ($t['why'] === 'مطابقٌ سلفًا') {
            $sum['skipTbl']++;
        } else {
            $sum['badTbl']++;
            $manual[] = "$rel tbl#{$t['i']} — {$t['why']}";
            $lines[] = sprintf('    tbl#%d  ⚠ يدويّ — %s', $t['i'], $t['why']);
        }
    }
    $need = false;
    foreach ($plan['edits'] as $e) if ($e['need']) $need = true;
    if (!$need && !$lines) continue;
    echo $rel . "\n";
    foreach ($lines as $l) echo $l . "\n";

    if ($apply && $need) {
        $err = null;
        $out = acl_rewrite($plan['src'], $plan['edits'], $err);
        if ($out === null) {
            echo "    ✘ رُفض الملفُّ — $err\n";
            $manual[] = "$rel — $err";
            $sum['badTbl']++;
            continue;
        }
        if ($out !== $plan['src']) {
            $bkPath = $BK . '/' . $rel;
            @mkdir(dirname($bkPath), 0777, true);
            file_put_contents($bkPath, $plan['src']);
            file_put_contents($ROOT . '/' . $rel, $out);
            $sum['touched']++;
        }
    }
}

echo "\n══════════ الخلاصة ══════════\n";
echo 'ملفاتٌ فيها جدولٌ هدف : ' . $sum['files'] . "\n";
echo 'جداولُ هدف            : ' . $sum['tbl'] . "\n";
echo '  مطابقةٌ سلفًا        : ' . $sum['skipTbl'] . "\n";
echo '  قابلةٌ للتحويل      : ' . $sum['okTbl'] . "\n";
echo '  تحتاج يدًا          : ' . $sum['badTbl'] . "\n";
echo 'نقلُ عمودِ إجراءات     : ' . $sum['moved'] . "\n";
echo 'حذفُ عمودِ عدّاد       : ' . $sum['ctr'] . "\n";
if ($apply) echo 'ملفاتٌ كُتِبت          : ' . $sum['touched'] . '  (لقطةٌ في storage/backups/' . $STAMP . ')' . "\n";
if ($manual) { echo "\n── يدويّ ──\n"; foreach ($manual as $m) echo "  $m\n"; }
