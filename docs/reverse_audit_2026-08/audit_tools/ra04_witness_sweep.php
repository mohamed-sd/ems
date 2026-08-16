<?php
/**
 * ra04_witness_sweep.php — إعادةُ تشغيلِ كلِّ الشواهدِ والبواباتِ من الصفر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ القاعدة (رقم 3 من التكليف): النتائجُ الخضراءُ السابقةُ **ادعاءاتٌ** تُعاد.
 * ◆ عيبُ الاقترانِ المرصود: ملفُّ مِسبارٍ واحدٌ يشهد لعدةِ معرِّفاتٍ ورمزُ خروجِه
 *   واحدٌ للجميع — فهنا يُقرأ **سطرُ كلِّ معرِّفٍ** من المخرَجِ (✔/✘ INJ-####)
 *   إضافةً إلى رمزِ الخروج، ويُسجَّلان معًا ولا يُخلطان.
 * ◆ المخرَج التقدّمي: evidence/witness_sweep.jsonl (سطرٌ لكلِّ ملفٍّ فورَ انتهائه)
 *   والخلاصة: evidence/witness_sweep.json + إعادةُ بصمةِ المخططِ في آخرِ سطر.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
$ROOT = 'C:/wamp64/www/ems';
$EV   = $ROOT . '/docs/reverse_audit_2026-08/evidence';
$PHP  = 'C:/wamp64/bin/php/php8.2.30/php.exe';
@mkdir($EV, 0777, true);
$JL = fopen($EV . '/witness_sweep.jsonl', 'w');

require_once $ROOT . '/includes/fix_closure_source.php';
$mentions = ems_fix_mentions($ROOT);          /* معرِّف ⇒ ملفاتُ مسابيرِه */
$files = [];
foreach ($mentions as $id => $fs) { foreach ($fs as $f) { $files[$f][] = $id; } }

/* بواباتٌ تُشغَّل ولو لم تحمل وسمَ معرِّف — نتيجتُها طبقةٌ مستقلة */
$gates = [
    'tools/fix_gate.php', 'tools/u13_gate.php', 'tools/m10_ac_gate.php',
    'tools/m14_ac_gate.php', 'tools/m16_ac_gate.php', 'tools/release_gate.php',
    'tools/guard_adoption_gate.php', 'tools/screen_registry_gate.php',
    'tools/uxr_visual_gate.php', 'tools/fix_ui_gate.php',
    'tools/fix_negative_tests.php', 'tools/fix_action_tests.php',
    'tools/fix_rf02_smoke.php',
];
foreach ($gates as $g) {
    $abs = str_replace('\\', '/', $ROOT . '/' . $g);
    if (is_file($abs) && !isset($files[$abs])) { $files[$abs] = []; }
}

ksort($files);
$summary = ['started_at' => date('c'), 'files' => [], 'per_id' => [], 'schema_after' => null];
$n = 0; $total = count($files);

foreach ($files as $f => $ids) {
    $n++;
    $rel = str_replace($ROOT . '/', '', $f);
    $t0 = microtime(true);
    $out = []; $code = 1;
    @exec(escapeshellarg($PHP) . ' ' . escapeshellarg($f) . ' 2>&1', $out, $code);
    $dur = round(microtime(true) - $t0, 1);
    $txt = implode("\n", $out);

    /* أحكامُ الأسطر: ✔/✘ ملتصقةٌ بمعرِّفٍ في السطرِ نفسِه */
    $lineVerdicts = [];
    foreach ($out as $ln) {
        if (preg_match_all('/(✔|✘)[^\n]{0,120}?(INJ-\d{4})|(INJ-\d{4})[^\n]{0,120}?(✔|✘)/u', $ln, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $mark = $m[1] !== '' ? $m[1] : $m[4];
                $id   = $m[2] !== '' ? $m[2] : $m[3];
                /* الرسوبُ يغلب النجاحَ إن تكرر المعرِّفُ في الملف */
                if (!isset($lineVerdicts[$id]) || $mark === '✘') { $lineVerdicts[$id] = $mark; }
            }
        }
    }
    /* عدّاد ناجح/فاشل إن أعلنه الملف */
    $pf = null;
    if (preg_match('/ناجحٌ?:\s*(\d+)\s*·\s*فاشلٌ?:\s*(\d+)/u', $txt, $m)) { $pf = [(int)$m[1], (int)$m[2]]; }
    elseif (preg_match('/النتيجة:\s*(\d+)\s*\/\s*(\d+)/u', $txt, $m)) { $pf = [(int)$m[1], (int)$m[2] - (int)$m[1]]; }

    $rec = [
        'file' => $rel, 'exit' => $code, 'seconds' => $dur,
        'declared_ids' => $ids, 'line_verdicts' => $lineVerdicts,
        'pass_fail' => $pf, 'tail' => array_slice($out, -6),
    ];
    $summary['files'][$rel] = $rec;
    fwrite($JL, json_encode($rec, JSON_UNESCAPED_UNICODE) . "\n");
    fflush($JL);
    fwrite(STDERR, sprintf("[%d/%d] %s exit=%d (%ss)\n", $n, $total, $rel, $code, $dur));

    /* حكمُ كلِّ معرِّف: سطرُه إن وُجد، وإلا رمزُ خروجِ ملفِّه (مع وسمِ المصدر) */
    foreach ($ids as $id) {
        $src = isset($lineVerdicts[$id]) ? 'line' : 'exit';
        $ok  = $src === 'line' ? ($lineVerdicts[$id] === '✔') : ($code === 0);
        if (!isset($summary['per_id'][$id])) { $summary['per_id'][$id] = []; }
        $summary['per_id'][$id][] = ['file' => $rel, 'ok' => $ok, 'source' => $src];
    }
}

/* حكمُ المعرِّفِ الجامع: أخضرُ إن شهد له ملفٌّ واحدٌ ناجحٌ على الأقل (كما في المصدر) */
$verdict = [];
foreach ($summary['per_id'] as $id => $ws) {
    $green = array_filter($ws, fn($w) => $w['ok']);
    $verdict[$id] = ['green' => count($green) > 0,
                     'witness_count' => count($ws),
                     'green_count' => count($green),
                     'by_line' => count(array_filter($ws, fn($w) => $w['source'] === 'line')) > 0];
}
ksort($verdict);
$summary['verdicts'] = $verdict;
$summary['green_total'] = count(array_filter($verdict, fn($v) => $v['green']));
$summary['red_total']   = count($verdict) - $summary['green_total'];

/* إعادةُ بصمةِ المخطط — إثباتُ أن البنيةَ لم تُمَسّ */
$o = []; @exec(escapeshellarg($PHP) . ' ' . escapeshellarg($ROOT . '/docs/reverse_audit_2026-08/audit_tools/ra00_baseline.php') . ' --schema-only 2>&1', $o);
$summary['schema_after'] = json_decode(trim(implode("\n", $o)), true);
$summary['finished_at'] = date('c');

file_put_contents($EV . '/witness_sweep.json', json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
fclose($JL);
printf("انتهى: %d ملفًّا · معرِّفات خضراء %d · حمراء %d\n",
    $total, $summary['green_total'], $summary['red_total']);
