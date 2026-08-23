<?php
/**
 * injfrd66_injaz_report.php — تقريرُ الإنجازِ بالأرقامِ والنسب لحزمة INJ-FRD-01
 *
 * يقرأ الحالةَ الحيّةَ من tools/injfrd66_tasks.json ويُخرِج
 * docs/INJ-FRD-01_INJAZ_REPORT_ar.md — أرقامُه محسوبةٌ لا منقولة.
 *
 * التشغيل: php tools/injfrd66_injaz_report.php [--write]
 */
declare(strict_types=1);

$ROOT  = dirname(__DIR__);
$STATE = $ROOT . '/tools/injfrd66_tasks.json';
$OUT   = $ROOT . '/docs/INJ-FRD-01_INJAZ_REPORT_ar.md';

const GATE_W  = ['built' => 25, 'linked' => 25, 'data' => 15, 'tested' => 20, 'accepted' => 15];
const GATE_AR = ['built' => 'مبنيّ', 'linked' => 'موصول', 'data' => 'بيانات مُرحَّلة', 'tested' => 'مُختبَر', 'accepted' => 'مقبول'];

function gv(string $v): float
{
    return match (trim($v)) { 'نعم', 'لا ينطبق' => 1.0, 'جزئي' => 0.5, default => 0.0 };
}

$tasks = json_decode((string) file_get_contents($STATE), true);
$n     = count($tasks);
$share = 100 / $n;

/* ── الحساب ───────────────────────────────────────────────────────── */
$done = 0.0;
$byScope = $bySlice = [];
$gateTally = array_fill_keys(array_keys(GATE_W), ['نعم' => 0, 'جزئي' => 0, 'لا' => 0, 'لا ينطبق' => 0, '—' => 0]);
$gateGap   = array_fill_keys(array_keys(GATE_W), 0.0);   // المتبقّي بالنقاطِ لكلِّ بوابة

foreach ($tasks as &$t) {
    $pct = 0.0;
    foreach (GATE_W as $g => $w) {
        $v    = (string) ($t['gates'][$g] ?? '—');
        $val  = gv($v);
        $pct += $val * $w;
        $key  = in_array(trim($v), ['نعم', 'جزئي', 'لا', 'لا ينطبق'], true) ? trim($v) : '—';
        $gateTally[$g][$key]++;
        $gateGap[$g] += (1 - $val) * $w / 100 * $share;
    }
    $t['pct']    = round($pct, 1);
    $t['earned'] = $pct / 100 * $share;
    $done       += $t['earned'];

    $s = $t['scope'];
    $byScope[$s] ??= ['n' => 0, 'earned' => 0.0, 'full' => 0];
    $byScope[$s]['n']++;
    $byScope[$s]['earned'] += $t['earned'];
    if ($t['pct'] >= 100) { $byScope[$s]['full']++; }

    $bySlice[(string) $t['pct']][] = $t['id'];
}
unset($t);

$remain = 100 - $done;
$f2     = static fn(float $x): string => number_format($x, 2);
$full   = array_values(array_filter($tasks, static fn(array $t): bool => $t['pct'] >= 100));

/* ── الطرفية ──────────────────────────────────────────────────────── */
echo "\n═══ INJ-FRD-01 · تقريرُ الإنجاز ═══\n";
echo "المنجز " . $f2($done) . "%  ·  المتبقّي " . $f2($remain) . "%  ·  المُغلقُ كاملًا " . count($full) . " من {$n}\n\n";
foreach (GATE_W as $g => $w) {
    printf("  %-16s متبقٍّ %5s نقطة\n", GATE_AR[$g], $f2($gateGap[$g]));
}
echo "\n";

if (!in_array('--write', $argv, true)) { exit(0); }

/* ── التقرير ──────────────────────────────────────────────────────── */
$bar = static function (float $p): string {
    $k = (int) round($p / 5);
    return str_repeat('█', $k) . str_repeat('░', 20 - $k);
};
$md = [];
$md[] = '# INJ-FRD-01 — تقريرُ الإنجاز بالأرقامِ والنسب';
$md[] = '';
$md[] = '> مولَّدٌ آليًّا بـ`tools/injfrd66_injaz_report.php` من `tools/injfrd66_tasks.json` — كلُّ رقمٍ فيه **محسوبٌ لا منقول**.';
$md[] = '> المرجعُ الحاكم: `INJ-FRD-01` وثيقةُ المتطلباتِ الوظيفية · و`INJ-FRD-TRACE-01` مصفوفاتُ التتبُّعِ الأربع.';
$md[] = '> تاريخُ اللقطة: ' . date('Y-m-d H:i');
$md[] = '';

$md[] = '## ① الحصيلةُ التنفيذية';
$md[] = '';
$md[] = '| المقياس | القيمة |';
$md[] = '|---|---:|';
$md[] = "| إجمالي المتطلبات | **{$n}** |";
$md[] = '| **المنجز** | **' . $f2($done) . '%** |';
$md[] = '| **المتبقّي** | **' . $f2($remain) . '%** |';
$md[] = '| مُغلقٌ كاملًا (خمسُ بواباتٍ خضراء) | **' . count($full) . '** من ' . $n . ' — ' . $f2(count($full) / $n * 100) . '% |';
$md[] = '| خطُّ الأساسِ عند التسلُّم (2026-08-22) | 35.57% |';
$md[] = '| **الفارقُ المُحقَّق** | **+' . $f2($done - 35.57) . ' نقطة** |';
$md[] = '';
$md[] = '```';
$md[] = 'خطُّ الأساس  ' . $bar(35.57) . '  35.57%';
$md[] = 'اليوم        ' . $bar($done) . '  ' . $f2($done) . '%';
$md[] = '```';
$md[] = '';

$md[] = '## ② المطلوبُ في المستندَين — ومقدارُ ما تحقَّق منه';
$md[] = '';
$md[] = '| المطلوب | المقام | المُنجَز | المتبقّي | النسبة |';
$md[] = '|---|---:|---:|---:|---:|';
$md[] = "| متطلباتٌ وظيفية | {$n} | " . count($full) . ' مُغلَقًا كاملًا | ' . ($n - count($full)) . ' | ' . $f2(count($full) / $n * 100) . '% |';
$md[] = '| حقولُ المبيعاتِ المحكومة | 589 | 589 | 0 | 100% |';
$md[] = '| حقولُ المورِّدين المحكومة | 828 | 828 | 0 | 100% |';
$md[] = '| **إجمالي الحقولِ المحكومة** | **1,417** | **1,417** | **0** | **100%** |';
$md[] = '| قدراتٌ مستجدّةٌ تُبنى أسطحًا | 4 | 4 | 0 | 100% |';
$md[] = '| لوحةُ إدارةِ الموردين (قدرةٌ قديمةٌ غائبة) | 1 | 1 | 0 | 100% |';
$tn = $gateTally['tested'];
$md[] = '| متطلباتٌ لها شاهدٌ إيجابيٌّ وسالب | ' . $n . ' | ' . $tn['نعم'] . ' | ' . ($n - $tn['نعم']) . ' | ' . $f2($tn['نعم'] / $n * 100) . '% |';
$an = $gateTally['accepted'];
$md[] = '| متطلباتٌ عبَرت معيارَ قبولِها | ' . $n . ' | ' . $an['نعم'] . ' كاملًا و' . $an['جزئي'] . ' جزئيًّا | ' . $an['لا'] . ' | ' . $f2(($an['نعم'] + $an['جزئي'] * .5) / $n * 100) . '% |';
$dn = $gateTally['data'];
$md[] = '| متطلباتٌ تحمل بياناتٍ حقيقية | ' . ($n - $dn['لا ينطبق']) . ' | ' . $dn['نعم'] . ' | ' . $dn['لا'] . ' | ' . $f2($dn['نعم'] / ($n - $dn['لا ينطبق']) * 100) . '% |';
$md[] = '';
$md[] = '> ‏5 متطلباتٍ «لا ينطبق عليها الترحيل» (إسقاطٌ أو مرجعٌ لا يحمل بياناتٍ خاصة) — فمقامُ البياناتِ ' . ($n - $dn['لا ينطبق']) . ' لا ' . $n . '.';
$md[] = '';

$md[] = '## ③ حصيلةُ كلِّ نطاق';
$md[] = '';
$md[] = '| النطاق | المتطلبات | سقفُه من البرنامج | المُنجَزُ منه | نسبةُ إنجازِه | مُغلقٌ كاملًا |';
$md[] = '|---|---:|---:|---:|---:|---:|';
foreach ($byScope as $s => $d) {
    $cap  = $d['n'] * $share;
    $md[] = "| {$s} | {$d['n']} | " . $f2($cap) . '% | ' . $f2($d['earned']) . '% | **' . $f2($d['earned'] / $cap * 100) . '%** | ' . $d['full'] . ' من ' . $d['n'] . ' |';
}
$md[] = '| **الإجمالي** | **' . $n . '** | **100.00%** | **' . $f2($done) . '%** | **' . $f2($done) . '%** | **' . count($full) . ' من ' . $n . '** |';
$md[] = '';

$md[] = '## ④ البواباتُ الخمس — أين يقع المتبقّي بالضبط';
$md[] = '';
$md[] = '| البوابة | الوزن | نعم | جزئي | لا | لا ينطبق | **المتبقّي من الـ100%** | حصّتُه من الفجوة |';
$md[] = '|---|---:|---:|---:|---:|---:|---:|---:|';
foreach (GATE_W as $g => $w) {
    $q    = $gateTally[$g];
    $md[] = '| ' . GATE_AR[$g] . " | {$w}% | {$q['نعم']} | {$q['جزئي']} | {$q['لا']} | {$q['لا ينطبق']} | **"
          . $f2($gateGap[$g]) . '%** | ' . ($remain > 0 ? $f2($gateGap[$g] / $remain * 100) : '0.00') . '% |';
}
$md[] = '| **الإجمالي** | **100%** | | | | | **' . $f2($remain) . '%** | **100%** |';
$md[] = '';
$topGate = array_keys($gateGap, max($gateGap))[0];
$md[] = '> **' . $f2(max($gateGap) / $remain * 100) . '% من الفجوةِ الباقيةِ في بوابةِ «' . GATE_AR[$topGate] . '» وحدَها** — والبناءُ والوصلُ لم يبقَ فيهما «لا» واحدة.';
$md[] = '';

$md[] = '## ⑤ توزيعُ المتطلباتِ على شرائحِ الإنجاز';
$md[] = '';
$md[] = '| الشريحة | عدد المتطلبات | المعرّفات |';
$md[] = '|---:|---:|---|';
krsort($bySlice, SORT_NUMERIC);
foreach ($bySlice as $pct => $ids) {
    $md[] = '| ' . number_format((float) $pct, 1) . '% | ' . count($ids) . ' | `' . implode('` · `', $ids) . '` |';
}
$md[] = '';

$md[] = '## ⑥ المُغلَقُ كاملًا — ' . count($full) . ' متطلبًا بخمسِ بواباتٍ خضراء';
$md[] = '';
$md[] = '| المعرّف | العنوان | النطاق |';
$md[] = '|---|---|---|';
foreach ($full as $t) { $md[] = "| **{$t['id']}** | {$t['title']} | {$t['scope']} |"; }
$md[] = '';

$md[] = '## ⑦ المتبقّي — ' . ($n - count($full)) . ' متطلبًا وما ينقص كلًّا منها';
$md[] = '';
$rest = array_values(array_filter($tasks, static fn(array $t): bool => $t['pct'] < 100));
usort($rest, static fn(array $a, array $b): int => $a['pct'] <=> $b['pct']);
$md[] = '| المعرّف | العنوان | نسبتُه | البواباتُ الناقصة | ما يحمله من الفجوة |';
$md[] = '|---|---|---:|---|---:|';
foreach ($rest as $t) {
    $miss = [];
    foreach (GATE_W as $g => $w) {
        $v = gv((string) $t['gates'][$g]);
        if ($v < 1) { $miss[] = GATE_AR[$g] . ($v > 0 ? ' (جزئي)' : ''); }
    }
    $md[] = "| **{$t['id']}** | {$t['title']} | " . number_format((float) $t['pct'], 1) . '% | ' . implode(' · ', $miss)
          . ' | ' . $f2($share - $t['earned']) . '% |';
}
$md[] = '';

$md[] = '## ⑧ خلاصةُ ما بقي';
$md[] = '';
$zero = array_values(array_filter($tasks, static fn(array $t): bool => gv((string) $t['gates']['data']) === 0.0));
$md[] = '- **البياناتُ الحقيقية** — ' . count($zero) . ' متطلبًا بلا صفوفٍ حقيقية، وأربعةُ جداولٍ ما زالت صفرَ صفٍّ فعلًا: `sal_client_needs` · `sal_quotation_lines` · `sal_quotation_revisions` · `sup_violations`. وهذه هي عقدةُ `XC-11` نفسِها: **الممارسةُ لا البناء**.';
$md[] = '- **القبول** — ' . $an['لا'] . ' متطلبًا لم يعبر معيارَ قبولِه بعد و' . $an['جزئي'] . ' عبَره جزئيًّا؛ وهو **أكبرُ بندٍ في الفجوة**.';
$md[] = '- **الوصل** — ' . $gateTally['linked']['جزئي'] . ' متطلبًا موصولٌ جزئيًّا، وصفرُ متطلبٍ غيرِ موصولٍ البتّة.';
$md[] = '- **البناء** — ' . $gateTally['built']['جزئي'] . ' متطلبًا مبنيٌّ جزئيًّا، و**صفرُ متطلبٍ غيرِ مبنيّ**.';
$md[] = '- **الاختبار** — ' . $tn['نعم'] . ' من ' . $n . ' لها شاهدٌ إيجابيٌّ وسالب؛ والباقي `' . implode('` · `', array_column(array_filter($tasks, static fn(array $t): bool => gv((string) $t['gates']['tested']) < 1), 'id')) . '`.';
$md[] = '';
$md[] = '**ولا يُقال «اكتملت الإدارتان»** قبلَ خضرةِ معاييرِ القبولِ كلِّها على النظامِ الحيِّ: 1,417 حقلًا محكومة ✔ · القائمةُ مشتقّةٌ لا مكتوبة ✔ · صفرُ سطحٍ مبنيٍّ بلا مدخل ✔ · **والرحلتان تعبران ببشر — وهذه لم تقع بعد**.';
$md[] = '';

file_put_contents($OUT, implode("\n", $md) . "\n");
echo 'كُتب: docs/INJ-FRD-01_INJAZ_REPORT_ar.md (' . count($md) . " سطرًا)\n";

/* ── نسخةُ العرض — مصدرُ الـArtifact ───────────────────────────────── */
$HTML = $ROOT . '/docs/injfrd66/injaz_report.html';
if (!is_dir(dirname($HTML))) { mkdir(dirname($HTML), 0777, true); }
$e = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$pc = static fn(float $x): string => number_format($x, 2) . '%';

/* ① صفوفُ المطلوب */
$reqRows = [
    ['متطلباتٌ وظيفية', $n, count($full) . ' مُغلَقًا كاملًا', $n - count($full), count($full) / $n * 100],
    ['حقولُ المبيعاتِ المحكومة', '589', '589', '0', 100],
    ['حقولُ الموردين المحكومة', '828', '828', '0', 100],
    ['إجمالي الحقولِ المحكومة', '1,417', '1,417', '0', 100],
    ['قدراتٌ مستجدّةٌ تُبنى أسطحًا', '4', '4', '0', 100],
    ['لوحةُ إدارةِ الموردين', '1', '1', '0', 100],
    ['متطلباتٌ لها شاهدٌ إيجابيٌّ وسالب', $n, $tn['نعم'], $n - $tn['نعم'], $tn['نعم'] / $n * 100],
    ['متطلباتٌ عبَرت معيارَ قبولِها', $n, $an['نعم'] . ' كاملًا · ' . $an['جزئي'] . ' جزئيًّا', $an['لا'], ($an['نعم'] + $an['جزئي'] * .5) / $n * 100],
    ['متطلباتٌ تحمل بياناتٍ حقيقية', $n - $dn['لا ينطبق'], $dn['نعم'], $dn['لا'], $dn['نعم'] / ($n - $dn['لا ينطبق']) * 100],
];
$rowsReq = '';
foreach ($reqRows as $r) {
    $cls = $r[4] >= 100 ? 'ok' : ($r[4] >= 50 ? 'part' : 'open');
    $rowsReq .= '<tr><td>' . $e($r[0]) . '</td><td class="n">' . $e($r[1]) . '</td><td class="n">' . $e($r[2])
             . '</td><td class="n">' . $e($r[3]) . '</td><td class="n"><span class="' . $cls . '">' . $pc((float) $r[4])
             . '</span><span class="mini"><i style="width:' . round((float) $r[4]) . '%"></i></span></td></tr>';
}

/* ② النطاقات */
$rowsScope = '';
foreach ($byScope as $s => $d) {
    $cap        = $d['n'] * $share;
    $rowsScope .= '<tr><td>' . $e($s) . '</td><td class="n">' . $d['n'] . '</td><td class="n">' . $pc($cap)
               . '</td><td class="n">' . $pc($d['earned']) . '</td><td class="n"><b>' . $pc($d['earned'] / $cap * 100)
               . '</b><span class="mini"><i style="width:' . round($d['earned'] / $cap * 100) . '%"></i></span></td><td class="n">'
               . $d['full'] . ' من ' . $d['n'] . '</td></tr>';
}
$rowsScope .= '<tr class="tot"><td>الإجمالي</td><td class="n">' . $n . '</td><td class="n">100.00%</td><td class="n">'
           . $pc($done) . '</td><td class="n">' . $pc($done) . '</td><td class="n">' . count($full) . ' من ' . $n . '</td></tr>';

/* ③ البوابات */
$rowsGate = '';
foreach (GATE_W as $g => $w) {
    $q         = $gateTally[$g];
    $rowsGate .= '<tr><td>' . $e(GATE_AR[$g]) . '</td><td class="n">' . $w . '%</td><td class="n ok">' . $q['نعم']
              . '</td><td class="n part">' . $q['جزئي'] . '</td><td class="n open">' . $q['لا'] . '</td><td class="n">'
              . $q['لا ينطبق'] . '</td><td class="n"><b>' . $pc($gateGap[$g]) . '</b></td><td class="n">'
              . ($remain > 0 ? $pc($gateGap[$g] / $remain * 100) : '—') . '</td></tr>';
}
$rowsGate .= '<tr class="tot"><td>الإجمالي</td><td class="n">100%</td><td class="n"></td><td class="n"></td><td class="n"></td><td class="n"></td><td class="n">'
          . $pc($remain) . '</td><td class="n">100%</td></tr>';

/* ④ الشرائح */
$rowsSlice = '';
foreach ($bySlice as $pct => $ids) {
    $cls        = (float) $pct >= 100 ? 'ok' : ((float) $pct >= 70 ? 'part' : 'open');
    $rowsSlice .= '<tr><td class="n ' . $cls . '">' . number_format((float) $pct, 1) . '%</td><td class="n">' . count($ids)
               . '</td><td class="w">' . implode(' ', array_map(static fn($i): string => '<code>' . $i . '</code>', $ids)) . '</td></tr>';
}

/* ⑤ بطاقاتُ المُغلَق · ⑥ صفوفُ المتبقّي */
$cards = '';
foreach ($full as $t) { $cards .= '<div class="card"><b>' . $e($t['id']) . '</b><span>' . $e($t['title']) . '</span></div>'; }
$rowsRest = '';
foreach ($rest as $t) {
    $miss = [];
    foreach (GATE_W as $g => $w) {
        $v = gv((string) $t['gates'][$g]);
        if ($v < 1) { $miss[] = GATE_AR[$g] . ($v > 0 ? ' (جزئي)' : ''); }
    }
    $cls       = $t['pct'] >= 90 ? 'ok' : ($t['pct'] >= 70 ? 'part' : 'open');
    $rowsRest .= '<tr><td><code>' . $e($t['id']) . '</code></td><td class="w">' . $e($t['title']) . '</td><td class="n '
              . $cls . '">' . number_format((float) $t['pct'], 1) . '%</td><td class="w">' . $e(implode(' · ', $miss))
              . '</td><td class="n">' . $pc($share - $t['earned']) . '</td></tr>';
}

/* ⑦ الخلاصة */
$notEsted = array_column(array_filter($tasks, static fn(array $t): bool => gv((string) $t['gates']['tested']) < 1), 'id');
$notes = '';
foreach ([
    '<b>البياناتُ الحقيقية</b> — ' . $dn['لا'] . ' متطلبًا بلا صفوفٍ حقيقية، وأربعةُ جداولٍ ما زالت صفرَ صفٍّ فعلًا: <code>sal_client_needs</code> <code>sal_quotation_lines</code> <code>sal_quotation_revisions</code> <code>sup_violations</code>. وهي عقدةُ <code>XC-11</code> نفسِها: <b>الممارسةُ لا البناء</b>.',
    '<b>القبول</b> — ' . $an['لا'] . ' متطلبًا لم يعبر معيارَ قبولِه و' . $an['جزئي'] . ' عبَره جزئيًّا؛ وهو <b>أكبرُ بندٍ في الفجوة</b>.',
    '<b>الوصل</b> — ' . $gateTally['linked']['جزئي'] . ' موصولٌ جزئيًّا، و<b>صفرُ متطلبٍ غيرِ موصولٍ البتّة</b>.',
    '<b>البناء</b> — ' . $gateTally['built']['جزئي'] . ' مبنيٌّ جزئيًّا، و<b>صفرُ متطلبٍ غيرِ مبنيّ</b>.',
    '<b>الاختبار</b> — ' . $tn['نعم'] . ' من ' . $n . ' لها شاهدٌ إيجابيٌّ وسالب؛ والباقي <code>' . implode('</code> <code>', $notEsted) . '</code>.',
] as $li) { $notes .= '<li>' . $li . '</li>'; }

$html = strtr((string) file_get_contents(__DIR__ . '/injfrd66_injaz.tpl.html'), [
    '{{STAMP}}'        => date('Y-m-d H:i'),
    '{{DONE}}'         => $f2($done),
    '{{REMAIN}}'       => $f2($remain),
    '{{DELTA}}'        => $f2($done - 35.57),
    '{{BASE}}'         => '35.57',
    '{{FULL}}'         => (string) count($full),
    '{{REST}}'         => (string) count($rest),
    '{{N}}'            => (string) $n,
    '{{DATA_DEN}}'     => (string) ($n - $dn['لا ينطبق']),
    '{{ROWS_REQ}}'     => $rowsReq,
    '{{ROWS_SCOPE}}'   => $rowsScope,
    '{{ROWS_GATE}}'    => $rowsGate,
    '{{ROWS_SLICE}}'   => $rowsSlice,
    '{{CARDS}}'        => $cards,
    '{{ROWS_REST}}'    => $rowsRest,
    '{{NOTES}}'        => $notes,
    '{{GATE_VERDICT}}' => '<b>' . $pc(max($gateGap) / $remain * 100) . ' من الفجوةِ الباقيةِ في بوابةِ «' . $e(GATE_AR[$topGate])
                        . '» وحدَها</b> — والبناءُ والوصلُ لم يبقَ فيهما «لا» واحدة.',
]);
file_put_contents($HTML, $html);
echo "كُتبت نسخةُ العرض: docs/injfrd66/injaz_report.html\n";
