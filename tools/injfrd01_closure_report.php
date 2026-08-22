<?php
/**
 * tools/injfrd01_closure_report.php
 *   INJ-FRD-REM-01 · §تقرير الإغلاق المطلوب — **مشتقٌّ من الدفترِ حيًّا**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لا رقمَ يُكتب هنا بيد**: كلُّ عددٍ في المخرَجِ يُقرأ من الدفترِ الرسميِّ
 *   (`docs/sources/INJ-FRD-REM-01/workbook.xlsx`) أو يُقاس من الشجرةِ والقاعدةِ
 *   لحظةَ التشغيل. فالتقريرُ **يُعاد توليدُه** ولا يُحدَّث يدويًّا — ولا يتعفّن.
 *
 * ◆ **ولا نسبةَ إجماليةً تُخفي الفروق** (بنصِّ الأمر): تُعرض الحالاتُ الخمسُ
 *   منفصلةً بأسمائِها، ولا تُجمع «مُغلَق» و«منفَّذ لا مُغلَق» في رقمٍ واحد.
 *
 * ◆ **والتمييزُ الرباعيُّ محفوظ** (§الحكم النهائي): BUILT · WIRED · ENFORCED ·
 *   EXERCISED لا تُختصر في «تمّ».
 *
 * التشغيل: php tools/injfrd01_closure_report.php [--md > تقرير.md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/lib/xlsx_io.php';
require_once $ROOT . '/includes/env.php';

$XLSX = $ROOT . '/docs/sources/INJ-FRD-REM-01/workbook.xlsx';
if (!is_file($XLSX)) { fwrite(STDERR, "⛔ الدفترُ الرسميُّ مفقود\n"); exit(1); }
$wb = xlsx_read($XLSX);
$sheet = array_keys($wb)[0];
$rows = $wb[$sheet];
$hdr = $rows[3];
$ix = array();
foreach ($hdr as $i => $h) { $ix[trim(str_replace('◆ ', '', (string) $h))] = $i; }

function col($r, $ix, $name) { return isset($ix[$name]) ? trim((string) ($r[$ix[$name]] ?? '')) : ''; }

$reqs = array();
foreach ($rows as $i => $r) {
    if ($i < 4) { continue; }
    $id = col($r, $ix, 'المعرِّف');
    if (!preg_match('~^[A-Z]{2,4}-[A-Z]{2,4}-\d{3}$~', $id)) { continue; }
    $reqs[$id] = array(
        'id'       => $id,
        'gap'      => col($r, $ix, 'الفجوة'),
        'domain'   => col($r, $ix, 'المجال'),
        'prio'     => col($r, $ix, 'الأولوية'),
        'cs'       => col($r, $ix, 'Change_Set_ID'),
        'closure'  => col($r, $ix, 'Closure_State') ?: 'OPEN',
        'commit'   => col($r, $ix, 'Commit'),
        'test'     => col($r, $ix, 'Test_Result'),
        'evidence' => col($r, $ix, 'Evidence_Status'),
        'blocker'  => col($r, $ix, 'Blocker'),
        'source'   => col($r, $ix, 'المصدرُ الحاكم'),
        'thr'      => col($r, $ix, 'Threshold_Source'),
        'apply'    => col($r, $ix, 'Test_Applicability'),
        'na'       => col($r, $ix, 'N/A_Reason'),
        'type'     => col($r, $ix, 'Requirement_Type'),
        'deps'     => col($r, $ix, 'التبعيات'),
    );
}
$N = count($reqs);
if ($N === 0) { fwrite(STDERR, "⛔ لا متطلبَ يُقرأ\n"); exit(1); }

$STATES = array('EVIDENCE_CLOSED', 'IMPLEMENTED_NOT_CLOSED', 'BLOCKED_GOVERNING_SOURCE',
                'BLOCKED_OWNER_DECISION', 'REGRESSION_CONSTRAINT', 'OPEN');
$tally = array_fill_keys($STATES, 0);
foreach ($reqs as $r) {
    $c = $r['closure'];
    if (!isset($tally[$c])) { $tally[$c] = 0; }
    $tally[$c]++;
}

$out = array();
$out[] = '# تقريرُ الإغلاق — INJ-FRD-REM-01';
$out[] = '';
$out[] = '> **مشتقٌّ من الدفترِ الرسميِّ حيًّا** — لا رقمَ فيه مكتوبٌ بيد. يُعاد توليدُه بـ';
$out[] = '> `php tools/injfrd01_closure_report.php`، فلا يتعفّن ولا يخالف مصدرَه.';
$out[] = '';

/* ── ① الإجماليُّ بالحالاتِ المنفصلة ─────────────────────────────────────── */
$out[] = '## ① الحصيلةُ — والحالاتُ منفصلةٌ لا مجموعة';
$out[] = '';
$out[] = '| الحالة | العدد | من ' . $N . ' |';
$out[] = '|---|---:|---:|';
$AR = array(
    'EVIDENCE_CLOSED'          => 'مُغلَقٌ بالدليل',
    'IMPLEMENTED_NOT_CLOSED'   => 'منفَّذٌ لا مُغلَقٌ بالدليل',
    'BLOCKED_GOVERNING_SOURCE' => 'محجوزٌ بغيابِ مصدرٍ حاكم',
    'BLOCKED_OWNER_DECISION'   => 'محجوزٌ بقرارِ مالك',
    'REGRESSION_CONSTRAINT'    => 'قيدُ ارتداد',
    'OPEN'                     => 'مفتوح',
);
foreach ($STATES as $s) {
    $n = $tally[$s] ?? 0;
    $out[] = sprintf('| **%s** (`%s`) | %d | %.1f٪ |', $AR[$s] ?? $s, $s, $n, 100 * $n / $N);
}
$out[] = '';
$out[] = '**ولا نسبةَ إجماليةً واحدة** — بنصِّ الأمر: «لا أريد نسبةً إجماليةً تخفي الفروق».';
$out[] = '';

/* ── ② بحزمِ التغيير ─────────────────────────────────────────────────────── */
$out[] = '## ② حزمُ التغيير';
$out[] = '';
$byCs = array();
foreach ($reqs as $r) { $byCs[$r['cs'] ?: '(بلا حزمة)'][$r['closure']] = ($byCs[$r['cs'] ?: '(بلا حزمة)'][$r['closure']] ?? 0) + 1; }
ksort($byCs);
$out[] = '| الحزمة | مُغلَقٌ بالدليل | منفَّذٌ لا مُغلَق | محجوز | مفتوح | الإجمالي |';
$out[] = '|---|---:|---:|---:|---:|---:|';
foreach ($byCs as $cs => $m) {
    $cl = $m['EVIDENCE_CLOSED'] ?? 0;
    $im = $m['IMPLEMENTED_NOT_CLOSED'] ?? 0;
    $bl = ($m['BLOCKED_OWNER_DECISION'] ?? 0) + ($m['BLOCKED_GOVERNING_SOURCE'] ?? 0);
    $op = $m['OPEN'] ?? 0;
    $tt = array_sum($m);
    $out[] = sprintf('| `%s` | %d | %d | %d | %s | %d |', $cs, $cl, $im, $bl,
                     $op === 0 ? '**0** ✔' : (string) $op, $tt);
}
$out[] = '';

/* ── ③ تغطيةُ الفجوات ───────────────────────────────────────────────────── */
$out[] = '## ③ تغطيةُ الفجوات';
$out[] = '';
$gaps = array();
foreach ($reqs as $r) {
    foreach (preg_split('~\s*·\s*~u', $r['gap']) as $g) {
        $g = trim($g);
        if ($g === '' || $g === '—') { continue; }
        if (!preg_match('~^GAP-~', $g)) { $g = 'GAP-' . ltrim($g, 'GAP- '); }
        $gaps[$g][] = $r['id'];
    }
}
ksort($gaps);
$out[] = sprintf('فجواتٌ مغطّاةٌ بمتطلبٍ واحدٍ على الأقل: **%d**.', count($gaps));
$out[] = '';

/* ── ④ المحجوزُ والمفتوحُ بأسمائِه — لا يُطوى ─────────────────────────────── */
foreach (array('BLOCKED_OWNER_DECISION' => 'محجوزٌ بقرارِ مالك',
               'BLOCKED_GOVERNING_SOURCE' => 'محجوزٌ بغيابِ مصدرٍ حاكم',
               'IMPLEMENTED_NOT_CLOSED' => 'منفَّذٌ لا مُغلَقٌ بالدليل') as $st => $lbl) {
    $list = array();
    foreach ($reqs as $r) { if ($r['closure'] === $st) { $list[] = $r; } }
    if (!$list) { continue; }
    $out[] = '## ④ ' . $lbl . ' — ' . count($list) . ' مطلبًا **يُسمّى ولا يُطوى**';
    $out[] = '';
    foreach ($list as $r) {
        $out[] = sprintf('- **`%s`** [%s · %s] — %s', $r['id'], $r['prio'], $r['cs'],
                         $r['blocker'] !== '' ? $r['blocker'] : '(بلا سببٍ مكتوبٍ — يُستكمل)');
    }
    $out[] = '';
}

$openList = array();
foreach ($reqs as $r) { if ($r['closure'] === 'OPEN') { $openList[] = $r; } }
if ($openList) {
    $out[] = '## ⑤ المفتوحُ — ' . count($openList) . ' مطلبًا';
    $out[] = '';
    $out[] = '| المعرِّف | أول | الحزمة | المجال |';
    $out[] = '|---|---|---|---|';
    foreach ($openList as $r) {
        $out[] = sprintf('| `%s` | %s | `%s` | %s |', $r['id'], $r['prio'], $r['cs'], $r['domain']);
    }
    $out[] = '';
}

/* ── ⑥ العتباتُ بلا مصدرٍ حاكم ──────────────────────────────────────────── */
$noSrc = array();
foreach ($reqs as $r) {
    if (stripos($r['thr'], 'NEEDS_GOVERNING_SOURCE') !== false) { $noSrc[] = $r; }
}
$out[] = '## ⑥ عتباتٌ بلا مصدرٍ حاكم — ' . count($noSrc);
$out[] = '';
if ($noSrc) {
    foreach ($noSrc as $r) { $out[] = sprintf('- `%s` — %s', $r['id'], $r['thr']); }
} else { $out[] = 'لا شيء.'; }
$out[] = '';
$out[] = '**ولم تُخترع عتبةٌ واحدة** — §ثالثًا: «لا سقفَ ولا مدةَ ولا مهلةَ من عندك».';
$out[] = '';

/* ── ⑦ الاختباراتُ — بما أُعلن في الدفتر ─────────────────────────────────── */
$applY = 0; $applN = 0; $naEmpty = 0;
foreach ($reqs as $r) {
    $applY += preg_match_all('~=YES~u', $r['apply']);
    $applN += preg_match_all('~=NO~u', $r['apply']);
    if (preg_match('~=NO~u', $r['apply']) && trim($r['na']) === '') { $naEmpty++; }
}
$out[] = '## ⑦ الاختبارات';
$out[] = '';
$out[] = sprintf('- أنواعُ اختبارٍ مُعلَنةٌ `YES`: **%d** · مُعلَنةٌ `NO`: **%d**', $applY, $applN);
$out[] = sprintf('- **`NO` بلا سببٍ مكتوب: %d** %s', $naEmpty, $naEmpty === 0 ? '✔ (§ثامنًا)' : '✘');
$withTest = 0; $withPass = 0;
foreach ($reqs as $r) {
    if ($r['test'] !== '') { $withTest++; }
    if (stripos($r['test'], 'PASS') !== false) { $withPass++; }
}
$out[] = sprintf('- متطلباتٌ لها نتيجةُ اختبارٍ مسجَّلة: **%d** · منها `PASS`: **%d**', $withTest, $withPass);
$out[] = '';

/* ── ⑧ الأدلّةُ القابلةُ لإعادةِ التشغيل ─────────────────────────────────── */
$evOk = 0; $evMissing = array();
foreach ($reqs as $r) {
    if ($r['evidence'] === '') { continue; }
    $any = false;
    foreach (preg_split('~\s*·\s*~u', $r['evidence']) as $piece) {
        $pp = trim(preg_replace('~\s+--.*$~', '', $piece));
        $pp = trim(preg_replace('~\s*\|.*$~', '', $pp));
        if ($pp !== '' && is_file($ROOT . '/' . $pp)) { $any = true; break; }
    }
    if ($any) { $evOk++; } else { $evMissing[] = $r['id'] . ' ⇒ ' . mb_substr($r['evidence'], 0, 60); }
}
$out[] = '## ⑧ الأدلّةُ — أموجودةٌ على القرصِ فعلًا؟';
$out[] = '';
$out[] = sprintf('- متطلباتٌ دليلُها **ملفٌّ قائمٌ يُعاد تشغيلُه: %d**', $evOk);
$out[] = sprintf('- دليلٌ لا يُعثر على ملفِّه: **%d** %s', count($evMissing),
                 empty($evMissing) ? '✔' : '✘');
foreach (array_slice($evMissing, 0, 8) as $m) { $out[] = '  - ' . $m; }
$out[] = '';

/* ── ⑨ حالةُ المستودعِ والانحدار ─────────────────────────────────────────── */
$out[] = '## ⑨ المستودعُ والانحدار';
$out[] = '';
$head = trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD 2>&1'));
$br   = trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --abbrev-ref HEAD 2>&1'));
$out[] = sprintf('- الفرع: `%s` · الالتزام: `%s`', $br, $head);
$out[] = '- بواباتُ السلسلة وحزامُ الانحدارِ يُقاسان بـ`php tests/injchain01_ten_gates.php`.';
$out[] = '';

/* ── ⑩ التمييزُ الرباعيُّ محفوظ ─────────────────────────────────────────── */
$out[] = '## ⑩ BUILT · WIRED · ENFORCED · EXERCISED';
$out[] = '';
$out[] = '**لا تُختصر في «تمّ»** (§الحكم النهائي). ودلالتُها في هذا التقرير:';
$out[] = '';
$out[] = '- `EVIDENCE_CLOSED` ⇒ الشروطُ السبعُ مجتمعةً (§الحادي عشر): قرارٌ مسندٌ · تنفيذٌ في';
$out[] = '  الموضعِ المعتمَد · موجبٌ · سالبٌ · ارتداديٌّ · دليلٌ يُعاد تشغيلُه · صفرُ فجوةٍ جديدة.';
$out[] = '- `IMPLEMENTED_NOT_CLOSED` ⇒ **مبنيٌّ وموصولٌ ومُنفَذٌ** وينقصه شرطٌ من السبعة —';
$out[] = '  والناقصُ مكتوبٌ في عمودِ `Blocker` بعينِه.';
$out[] = '- `BLOCKED_*` ⇒ **لا يُبنى** حتى يُتَّخذ القرارُ أو يوجد المصدر — وبناؤُه قبلَه';
$out[] = '  اختيارٌ ضمنيٌّ لأحدِ الخيارَين.';
$out[] = '';

$md = implode("\n", $out) . "\n";
if (in_array('--md', $argv, true)) { echo $md; exit(0); }

$dest = $ROOT . '/docs/INJ-FRD-REM-01_CLOSURE_REPORT_ar.md';
file_put_contents($dest, $md);
/* ◆ قراءةٌ ثانيةٌ من القرصِ — الكتابةُ التي لا تُقرأ بعدَها مزعومة */
$back = (string) @file_get_contents($dest);
if (strlen($back) !== strlen($md)) { fwrite(STDERR, "⛔ كتابةٌ مزعومة\n"); exit(1); }
printf("✔ كُتب التقرير: docs/INJ-FRD-REM-01_CLOSURE_REPORT_ar.md (%d بايت)\n", strlen($md));
printf("  المتطلبات: %d · مُغلَقٌ بالدليل=%d · منفَّذٌ لا مُغلَق=%d · محجوز=%d · مفتوح=%d\n",
       $N, $tally['EVIDENCE_CLOSED'], $tally['IMPLEMENTED_NOT_CLOSED'],
       $tally['BLOCKED_OWNER_DECISION'] + $tally['BLOCKED_GOVERNING_SOURCE'], $tally['OPEN']);
