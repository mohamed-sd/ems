<?php
/**
 * tools/fix_permgov_state_apply.php — نقلُ حالةِ ما أُغلق بشاهدٍ إلى ملفِّ الحالات
 * ═══════════════════════════════════════════════════════════════════════════
 * لا يُغيّر إلا ما أعلنته الحملةُ **«مُغلقٌ بشاهد»** — أي ما قِيست شروطُه كلُّها
 * ونجحت. ولا يلمس بندًا واحدًا سواه، ولا يكتب شيئًا في وضعِ العرض.
 *
 *   php tools/fix_permgov_state_apply.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$APPLY = in_array('--apply', $argv, true);

/* الحصيلةُ من الحملةِ نفسِها — تُشتقُّ بتشغيلِها لا تُنسخ يدويًّا */
$md = $ROOT . '/docs/fix_2026-08/PERM_GOV_CAMPAIGN_2026-08-14.md';
if (!is_file($md)) { exit("التقريرُ غيرُ موجود — شغّل الحملةَ بـ--md أوّلًا\n"); }
$s = (string) file_get_contents($md);

$closed = array();
foreach (preg_split('~\r?\n~', $s) as $ln) {
    if (strpos($ln, '| INJ-') !== 0) { continue; }
    if (mb_strpos($ln, '**مُغلقٌ بشاهد**') === false) { continue; }
    if (preg_match('~\|\s*(INJ-\d{4})\s*\|~', $ln, $m)) { $closed[$m[1]] = true; }
}
echo '  أعلنت الحملةُ إغلاقَ: ' . count($closed) . " بندًا\n";
if (!$closed) { exit(0); }

$path = $ROOT . '/docs/fix_progress/INJ_findings_state.tsv';
$lines = file($path);
$changed = 0; $already = 0;
$out = array();
foreach ($lines as $ln) {
    $p = explode("\t", rtrim($ln, "\r\n"));
    if (count($p) < 4 || strpos($p[0], 'INJ-') !== 0) { $out[] = rtrim($ln, "\r\n"); continue; }
    $id = trim($p[0]);
    if (isset($closed[$id])) {
        if (trim($p[3]) === 'مُغلقٌ بشاهد') { $already++; }
        else { $p[3] = 'مُغلقٌ بشاهد'; $changed++; }
    }
    $out[] = implode("\t", $p);
}
echo "  سيُغيَّر: {$changed} · مُغلقٌ سلفًا: {$already}\n";
if (!$APPLY) { exit("  (عرضٌ فقط — أضف --apply)\n"); }
file_put_contents($path, implode("\n", $out) . "\n");
echo "  ✔ كُتب ملفُّ الحالات\n";
exit(0);
