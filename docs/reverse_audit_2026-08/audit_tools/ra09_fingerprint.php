<?php
/**
 * ra09_fingerprint.php — يتحقَّقُ أنَّ التدقيقَ لم يمسَّ بنيةَ النظام
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لا يحسبُ البصمةَ بنفسِه — ينادي `ra00_baseline.php --schema-only` ويقارن.
 *   (تعريفانِ للبصمةِ الواحدةِ يتفرّقانِ حتمًا؛ المصدرُ الحاسبُ واحدٌ هو ra00.)
 * ◆ رمزُ الخروج: 0 مطابقٌ لخطِّ الأساس · 1 اختلفت البنيةُ فالنتائجُ تحتاج خطَّ أساسٍ جديدًا.
 */
declare(strict_types=1);

const BASE_SCHEMA = 'd66f94e28c28107e34c3db71ed9a925f176328ce';
const BASE_TRIG   = '601bca3ea9729271d7ea8986ee2d3b53db36fdde';

$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/ra00_baseline.php') . ' --schema-only';
$out = shell_exec($cmd);
$j = json_decode((string) $out, true);
if (!is_array($j) || !isset($j['schema_sha1'], $j['trigger_sha1'])) {
    fwrite(STDERR, "تعذّر قراءةُ مُخرَجِ ra00:\n$out\n");
    exit(2);
}

$okS = $j['schema_sha1'] === BASE_SCHEMA;
$okT = $j['trigger_sha1'] === BASE_TRIG;
printf("لحظةُ القياس : %s\n", $j['at']);
printf("بصمةُ المخطط : %s  %s\n", $j['schema_sha1'], $okS ? '✔ مطابقةٌ لخطِّ الأساس' : '✖ اختلفت — البنيةُ تغيّرت');
printf("بصمةُ القوادح: %s  %s\n", $j['trigger_sha1'], $okT ? '✔ مطابقةٌ لخطِّ الأساس' : '✖ اختلفت — القوادحُ تغيّرت');
echo ($okS && $okT)
    ? "\nالحكم: التدقيقُ قراءةٌ فقط — لم تتحرَّك البنية.\n"
    : "\nالحكم: البنيةُ تحرَّكت — لا تُعتمد النتائجُ إلا بخطِّ أساسٍ جديدٍ مُعلَن.\n";
exit(($okS && $okT) ? 0 : 1);
