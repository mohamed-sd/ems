<?php
/**
 * tools/fix_closed_reconcile.php — مصالحةُ مصدرَي الإغلاق
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ البندُ الحاجبُ في تكليفِ حملةِ الأدلة (2026-08-13)
 *
 * يُظهر الفارقَ **بالمعرِّفاتِ لا بالأرقام** بين ثلاثةِ مصادر:
 *   【أ】 المصفوفةُ المثبَّتةُ بيدٍ في `tools/fix_status_report.php`
 *   【ب】 «مُغلقٌ بشاهد» في `docs/fix_progress/INJ_findings_state.tsv`
 *   【ج】 **المِسبارُ على القرص** — `includes/fix_closure_source.php`
 *
 * و【ج】 وحدَه خارجَ الحلقة: 【ب】 مُشتَقٌّ من 【أ】، فمقارنتُهما تقيس اتّساقَ
 * النسخِ لا صدقَ الحكم.
 *
 * التشغيل:
 *   php tools/fix_closed_reconcile.php            (ساكنٌ — ذكرٌ فقط)
 *   php tools/fix_closed_reconcile.php --live     (يُشغّل كلَّ مِسبارٍ · بطيء)
 *   php tools/fix_closed_reconcile.php --live --md=<path>
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/includes/fix_closure_source.php';

$LIVE = in_array('--live', $argv, true);
$MD = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $MD = substr($a, 5); } }

$lines = array();
$say = function ($s) use (&$lines) { fwrite(STDOUT, $s . "\n"); $lines[] = $s; };

$say('══════════════════════════════════════════════════════════════════');
$say(' مصالحةُ مصدرَي الإغلاق' . ($LIVE ? ' — **حيًّا**' : ' — ساكنًا'));
$say('══════════════════════════════════════════════════════════════════');
$say('');

/* ── 【أ】 المصفوفةُ المثبَّتة ─────────────────────────────────────────────── */
$A = array();
$statusTool = $ROOT . '/tools/fix_status_report.php';
if (is_file($statusTool)) {
    $src = (string) file_get_contents($statusTool);
    $s = strpos($src, '$CLOSED = array(');
    if ($s !== false) {
        /* نهايةُ المصفوفةِ: أوّلُ `\n);` بعد بدايتها */
        $e = strpos($src, "\n);", $s);
        $blk = $e === false ? substr($src, $s) : substr($src, $s, $e - $s);
        if (preg_match_all("~'(INJ-\\d{4})'~", $blk, $m)) {
            $A = array_values(array_unique($m[1]));
        }
    }
}
sort($A);

/* ── 【ب】 ملفُّ الحالة ───────────────────────────────────────────────────── */
$B = array();
$tsv = $ROOT . '/docs/fix_progress/INJ_findings_state.tsv';
if (is_file($tsv)) {
    foreach (file($tsv) as $ln) {
        $p = explode("\t", rtrim($ln, "\r\n"));
        if (count($p) >= 4 && trim($p[3]) === 'مُغلقٌ بشاهد') { $B[] = trim($p[0]); }
    }
}
$B = array_values(array_unique($B));
sort($B);

/* ── 【ج】 القرص ─────────────────────────────────────────────────────────── */
if ($LIVE) { $say('── تشغيلُ المسابير (قد يطول)'); }
$res = ems_fix_closed_ids($ROOT, $LIVE, $LIVE ? $say : null);
$C = $LIVE ? $res['closed'] : $res['mentioned'];
sort($C);
if ($LIVE) { $say(''); }

$say('【أ】 مصفوفةٌ مثبَّتةٌ في fix_status_report.php : ' . count($A));
$say('【ب】 «مُغلقٌ بشاهد» في INJ_findings_state.tsv : ' . count($B));
$say('【ج】 ' . ($LIVE ? 'مِسبارٌ يذكره **وهو أخضرُ الآن**' : 'مِسبارٌ يذكره (ذكرٌ لا حكم)')
     . '   : ' . count($C));
$say('');

$show = function ($title, array $ids, $note = '') use ($say) {
    $say('── ' . $title . ' : ' . count($ids) . ($note !== '' ? '   ' . $note : ''));
    if (!$ids) { return; }
    foreach (array_chunk($ids, 8) as $ch) { $say('     ' . implode(' · ', $ch)); }
};

$show('في 【أ】 دون 【ج】', array_values(array_diff($A, $C)),
      '← أُعلن مُغلقًا ولا مِسبارَ' . ($LIVE ? ' أخضرَ' : '') . ' يذكره');
$show('في 【ج】 دون 【أ】', array_values(array_diff($C, $A)),
      '← مِسبارٌ يذكره ولم يُعلَن');
$show('في 【ب】 دون 【ج】', array_values(array_diff($B, $C)));
$say('');

/* ── الحكمُ ────────────────────────────────────────────────────────────────── */
$onlyA = array_diff($A, $C);
$say('══ الحكم');
if (!$onlyA) {
    $say('  ✔ كلُّ ما أُعلن مُغلقًا يذكره مِسبارٌ' . ($LIVE ? ' أخضرُ' : '') . ' — لا إعلانَ بلا شاهد.');
} else {
    $say('  ✘ ' . count($onlyA) . ' معرِّفًا أُعلن مُغلقًا بلا مِسبارٍ'
         . ($LIVE ? ' أخضرَ' : '') . ' يذكره — يُفحص ويُحسم بشاهدٍ لا بالثقة.');
}
if ($LIVE && $res['red']) {
    $say('');
    $say('── معرِّفاتٌ يذكرها مِسبارٌ **أحمرُ** (لا تُحسَب مُغلقة): ' . count($res['red']));
    $i = 0;
    foreach ($res['red'] as $id => $files) {
        $say('     ' . $id . '  ⇐ ' . $files);
        if (++$i >= 40) { $say('     … (' . (count($res['red']) - 40) . ' أخرى)'); break; }
    }
}

if ($MD !== null) {
    $dir = dirname($ROOT . '/' . ltrim($MD, '/'));
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    $path = (strpos($MD, ':') !== false || $MD[0] === '/') ? $MD : ($ROOT . '/' . $MD);
    file_put_contents($path, "```\n" . implode("\n", $lines) . "\n```\n");
    fwrite(STDOUT, "\n   · كُتب: {$MD}\n");
}

exit($onlyA ? 1 : 0);
