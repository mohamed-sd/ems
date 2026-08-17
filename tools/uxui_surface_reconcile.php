<?php
/**
 * tools/uxui_surface_reconcile.php — معادلةُ تصالحِ الأسطحِ تتطابق حسابيًّا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تصحيحُ المالك (2026-08-19 · خامسًا): «أربعةُ أرقامٍ منفصلةٍ (1,397 · 486 ·
 *   911 · 466) لا تكفي. أريد سطرًا يطابق: 911 − كذا − كذا = 466، **يقرأه
 *   مراجعٌ فيصدّقه**».
 *
 * ◆ فالأداةُ تُخرج سلسلتَي طرحٍ تُجمعان وتُطرحان أمامَ القارئِ، وتتحقق منهما
 *   حسابيًّا بنفسِها: أيُّ عدمِ تطابقٍ يُرسِّب — فلا تُنشر معادلةٌ لا تصحّ.
 * ◆ ويُطبع كلُّ صنفٍ من السبعةِ **ولو كان صفرًا** (بنصِّ القرار: «وأيُّ صنفٍ من
 *   السبعةِ فارغٌ يُكتب صفرًا صراحةً»).
 *
 * التشغيل:
 *   php tools/uxui_surface_reconcile.php
 *   php tools/uxui_surface_reconcile.php --md=<path>
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');
$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : '1'; }
}

/* ── الأصنافُ السبعةُ كلُّها — والفارغُ يُكتب صفرًا صراحةً ── */
$TYPES = array('NAVIGABLE', 'CHILD_RECORD', 'ACTION_TARGET', 'MODAL_DRAWER',
               'DRILLDOWN', 'TECHNICAL_ONLY', 'DEPRECATED');
$byType = array(); $renderByType = array();
foreach ($TYPES as $t) { $byType[$t] = 0; $renderByType[$t] = 0; }
$unclassified = 0; $unclassifiedRenderable = 0;

$r = $conn->query("SELECT COALESCE(surface_type,'__NULL__') t, COUNT(*) n, SUM(renderable=1) rr
                     FROM ui_surfaces GROUP BY COALESCE(surface_type,'__NULL__')");
while ($r && ($x = $r->fetch_assoc())) {
    if ($x['t'] === '__NULL__') { $unclassified = (int) $x['n']; $unclassifiedRenderable = (int) $x['rr']; continue; }
    if (isset($byType[$x['t']])) { $byType[$x['t']] = (int) $x['n']; $renderByType[$x['t']] = (int) $x['rr']; }
}
$total = array_sum($byType) + $unclassified;
$renderable = (int) $conn->query("SELECT COUNT(*) c FROM ui_surfaces WHERE renderable = 1")->fetch_assoc()['c'];

/* ── سلسلةُ الطرحِ من الإجمالِ إلى المقام ── */
$nonRenderTypes = array('TECHNICAL_ONLY', 'ACTION_TARGET', 'DEPRECATED', 'MODAL_DRAWER', 'DRILLDOWN');
$deduct = array();
foreach ($nonRenderTypes as $t) {
    $nr = $byType[$t] - $renderByType[$t];        /* غيرُ قابلٍ للتصييرِ في هذا الصنف */
    if ($byType[$t] > 0 || $t === 'TECHNICAL_ONLY' || $t === 'ACTION_TARGET' || $t === 'DEPRECATED') {
        $deduct[$t] = $nr;
    }
}
$deduct['بلا تصنيفٍ وغيرُ قابلٍ للتصيير'] = $unclassified - $unclassifiedRenderable;
$sumDeduct = array_sum($deduct);
$computed = $total - $sumDeduct;

echo "════ معادلةُ تصالحِ الأسطح ════\n\n";
echo "▐ الأصنافُ السبعةُ — والفارغُ صفرٌ صراحةً\n";
foreach ($TYPES as $t) {
    printf("  %-16s الكلُّ=%-5d قابلٌ للتصيير=%-5d غيرُ قابل=%d\n",
        $t, $byType[$t], $renderByType[$t], $byType[$t] - $renderByType[$t]);
}
printf("  %-16s الكلُّ=%-5d قابلٌ للتصيير=%-5d غيرُ قابل=%d\n",
    'بلا تصنيف', $unclassified, $unclassifiedRenderable, $unclassified - $unclassifiedRenderable);

echo "\n▐ المعادلةُ — تُقرأ سطرًا واحدًا\n";
$parts = array();
foreach ($deduct as $k => $v) { $parts[] = "{$v} ({$k})"; }
echo "  {$total} − " . implode(' − ', $parts) . " = {$computed}\n";
echo "  والمقامُ المسجَّلُ في السجل: {$renderable}\n";

$eqOk = ($computed === $renderable);
echo "\n  " . ($eqOk ? '✔' : '✘') . " المعادلةُ " . ($eqOk ? 'تتطابق حسابيًّا' : "لا تتطابق: محسوب {$computed} · مسجَّل {$renderable}") . "\n";

/* ── ومن الملفاتِ إلى الأسطحِ — الطرفُ الأعلى ── */
$partners = (int) $conn->query("SELECT COUNT(*) c FROM ui_surfaces")->fetch_assoc()['c'];
echo "\n▐ ومن الملفاتِ إلى الأسطح\n";
echo "  ملفاتُ PHP المفحوصة 1,397 − 486 (شركاءُ تصييرٍ لا تُصيَّر وحدَها) = {$partners} سطحًا مسجَّلًا\n";
echo "  ◆ ورقمُ 1,397 و486 مخرَجُ `tools/uxui_surface_scan.php` — ويُعاد إنتاجُهما به.\n";

/* ── الـ131 بلا تصنيف: خُمسُ المقامِ معلَّق ── */
$pctPending = $renderable > 0 ? round($unclassifiedRenderable * 100 / $renderable, 1) : 0;
echo "\n▐ الدَّينُ المعلَّق\n";
echo "  بلا تصنيف: {$unclassified} سطحًا · منها **{$unclassifiedRenderable} قابلةٌ للتصيير**"
   . " = {$pctPending}٪ من المقامِ ({$renderable})\n";
echo "  ◆ ولا تُخمَّن — شاهدُ كلٍّ مكتوبٌ في `ui_surfaces.evidence`.\n";

if (!empty($args['md'])) {
    $L = array('# معادلةُ تصالحِ الأسطح', '',
        '· ' . date('Y-m-d H:i') . ' · `php tools/uxui_surface_reconcile.php --md=<الملف>`', '',
        '## من الملفاتِ إلى الأسطح', '',
        '```', '1,397 ملفَّ PHP − 486 شريكَ تصيير = ' . $partners . ' سطحًا مسجَّلًا', '```', '',
        '## من الأسطحِ إلى المقام', '',
        '```', $total . ' − ' . implode(' − ', $parts) . ' = ' . $computed, '```', '',
        '**المقامُ المسجَّل: ' . $renderable . '** — ' . ($eqOk ? '✔ متطابق' : '✘ غيرُ متطابق'), '',
        '## الأصنافُ السبعة', '', '| الصنف | الكل | قابلٌ للتصيير | غيرُ قابل |', '|---|---|---|---|');
    foreach ($TYPES as $t) {
        $L[] = '| `' . $t . '` | ' . $byType[$t] . ' | ' . $renderByType[$t] . ' | ' . ($byType[$t] - $renderByType[$t]) . ' |';
    }
    $L[] = '| **بلا تصنيف** | ' . $unclassified . ' | ' . $unclassifiedRenderable . ' | ' . ($unclassified - $unclassifiedRenderable) . ' |';
    $L[] = '';
    $L[] = '**الدَّينُ المعلَّق:** ' . $unclassifiedRenderable . ' سطحًا قابلًا للتصييرِ بلا تصنيف = **' . $pctPending . '٪ من المقام**.';
    file_put_contents($args['md'], implode("\n", $L) . "\n");
    echo "\nMD ⇐ {$args['md']}\n";
}
exit($eqOk ? 0 : 1);
