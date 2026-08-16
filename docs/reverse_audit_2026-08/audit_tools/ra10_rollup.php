<?php
/**
 * ra10_rollup.php — الأرقامُ الختاميةُ للملخصِ التنفيذي (قراءةٌ فقط)
 * يقارن طرائقَ العدِّ الثلاثَ على المقامِ نفسِه (595) حتى لا يُنسب التقدُّمُ إلى تغيُّرِ المقام.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
$ROOT = 'C:/wamp64/www/ems';
$EV = $ROOT . '/docs/reverse_audit_2026-08/evidence';
require_once $ROOT . '/includes/fix_closure_source.php';

$state = [];
foreach (array_slice(file($ROOT . '/docs/fix_progress/INJ_findings_state.tsv', FILE_IGNORE_NEW_LINES), 1) as $l) {
    $p = explode("\t", $l); $id = trim($p[0]);
    if ($id !== '') { $state[$id] = ['sev' => trim($p[1]), 'blk' => trim($p[2]), 'state' => trim($p[3])]; }
}
$sweep = json_decode(file_get_contents($EV . '/witness_sweep.json'), true);
$green = [];
foreach ($sweep['verdicts'] as $id => $v) { if (!empty($v['green'])) { $green[$id] = 1; } }
$mentioned = array_flip(ems_fix_closed_ids($ROOT, false)['mentioned']);

$R = file($ROOT . '/docs/fix_2026-08/master_register.tsv', FILE_IGNORE_NEW_LINES);
$h = array_map('trim', str_getcsv($R[2], "\t")); $ix = array_flip($h);
$decOpen = []; $kind = []; $dept = []; $ext = [];
for ($i = 3; $i < count($R); $i++) {
    if (trim($R[$i]) === '') { continue; }
    $c = str_getcsv($R[$i], "\t"); $id = trim($c[0]);
    if (!preg_match('/^INJ-\d+$/', $id)) { continue; }
    if (mb_strpos(trim((string) $c[$ix['تصنيف الفجوة']]), 'قرار') !== false) { $decOpen[$id] = 1; }
    $kind[$id] = trim((string) $c[$ix['نوع الفجوة']]);
    $dept[$id] = trim((string) $c[$ix['الإدارة']]);
    $ext[$id]  = trim((string) $c[$ix['يمنع العرض الخارجي']]);
}

require_once __DIR__ . '/ra_effort_model.php';

$m = ['mentioned' => 0, 'impl' => 0, 'green' => 0, 'triple' => 0];
$openBySev = []; $effBySev = []; $effByKind = []; $openByKind = [];
$blkOpen = 0; $extOpen = 0; $effTotal = 0.0;
foreach ($state as $id => $s) {
    $d = !isset($decOpen[$id]); $i = in_array($s['state'], ['مُغلقٌ بشاهد', 'مُغلق', 'مُغطًّى'], true); $g = isset($green[$id]);
    if (isset($mentioned[$id])) { $m['mentioned']++; }
    if ($i) { $m['impl']++; }
    if ($g) { $m['green']++; }
    if ($d && $i && $g) { $m['triple']++; continue; }
    $k = $kind[$id] ?? '';
    $d1 = ra_days($k, $s['sev']);
    $openBySev[$s['sev']] = ($openBySev[$s['sev']] ?? 0) + 1;
    $effBySev[$s['sev']] = ($effBySev[$s['sev']] ?? 0) + $d1;
    $openByKind[$k] = ($openByKind[$k] ?? 0) + 1;
    $effByKind[$k] = ($effByKind[$k] ?? 0) + $d1;
    if (mb_strpos($s['blk'], 'نعم') === 0) { $blkOpen++; }
    if (mb_strpos((string) ($ext[$id] ?? ''), 'نعم') === 0) { $extOpen++; }
    $effTotal += $d1;
}
$T = count($state);
$P = fn($n) => round($n / $T * 100, 1);

echo "══ طرائقُ العدِّ على المقامِ نفسِه ($T) ══\n";
printf("  ① «مذكورٌ في وثيقةِ تصحيح»        : %3d = %s٪   ← الطريقةُ التي أنتجت ادعاءَ 45.7٪\n", $m['mentioned'], $P($m['mentioned']));
printf("  ② «تنفيذٌ مُغلق» (سجلُّ الحالة)      : %3d = %s٪\n", $m['impl'], $P($m['impl']));
printf("  ③ «شاهدٌ أخضرُ حيٌّ» (أُعيد تشغيلُه) : %3d = %s٪\n", $m['green'], $P($m['green']));
printf("  ④ «الثلاثةُ معًا» — الحكمُ المعتمد   : %3d = %s٪\n", $m['triple'], $P($m['triple']));
printf("\n  الفارقُ بين ① و④ = %d بندًا — هذا مقدارُ التضخُّمِ في الادعاءِ السابق\n", $m['mentioned'] - $m['triple']);

echo "\n══ المفتوحُ وجهدُه ══\n";
ksort($openBySev);
foreach ($openBySev as $s => $n) { printf("  %-3s مفتوح=%3d   جهد=%6.1f يوم-شخص\n", $s, $n, $effBySev[$s]); }
printf("  الإجمالي: مفتوح=%d · جهدٌ خام=%.1f يوم-شخص\n", $T - $m['triple'], $effTotal);
printf("  يمنع الإطلاق (مفتوح)=%d · يمنع العرض الخارجي (مفتوح)=%d\n", $blkOpen, $extOpen);

echo "\n══ المفتوحُ حسب نوعِ الفجوة ══\n";
arsort($effByKind);
foreach ($effByKind as $k => $e) { printf("  %-28s مفتوح=%3d  جهد=%6.1f\n", $k ?: '(بلا نوع)', $openByKind[$k], $e); }

/* الجهدُ بعد خصمِ ما لا يحتاج كودًا */
$noCode = ($openByKind['Missing Evidence'] ?? 0);
printf("\n  منها %d بندَ «دليلٍ غائب» — لا تمسُّ المنتجَ (كتابةُ شاهدٍ فقط)\n", $noCode);

/* ══ سيناريوهاتُ الإغلاقِ الثلاثةُ بالجهد ══ */
$effBlk = 0.0; $effExt = 0.0; $effP01 = 0.0;
foreach ($state as $id => $s) {
    $d = !isset($decOpen[$id]); $i = in_array($s['state'], ['مُغلقٌ بشاهد', 'مُغلق', 'مُغطًّى'], true); $g = isset($green[$id]);
    if ($d && $i && $g) { continue; }
    $e1 = ra_days($kind[$id] ?? '', $s['sev']);
    if (mb_strpos($s['blk'], 'نعم') === 0) { $effBlk += $e1; }
    if (mb_strpos((string) ($ext[$id] ?? ''), 'نعم') === 0) { $effExt += $e1; }
    if ($s['sev'] === 'P0' || $s['sev'] === 'P1') { $effP01 += $e1; }
}
$BLOCKERS_LIVE = 34.0;  // B8..B13 المكتشفةُ حيًّا في هذه الجولة (تقديرُ الموجاتِ 1·3·4)
echo "\n══ سيناريوهاتُ الإغلاق (يوم-شخص) ══\n";
printf("  ① رفعُ البوابةِ فقط: 6 P0 + حواجبُ الأمنِ B2·B3 + الحواجبُ الحيّةُ B8..B13   ≈ %.0f\n", 34.0 + 6.0 + $BLOCKERS_LIVE);
printf("  ② جاهزيةٌ داخليةٌ حقيقية: ① + كلُّ P1 + ردمُ فجوةِ الدليل (%d بندًا)        ≈ %.0f\n", $noCode, 34.0 + 6.0 + $BLOCKERS_LIVE + $effP01 - 34.0 + $effByKind['Missing Evidence']);
printf("  ③ الإغلاقُ الكامل (كلُّ الـ%d المفتوح)                                       ≈ %.0f\n", $T - $m['triple'], $effTotal);
echo "\n  الجهدُ الحاجبُ للإطلاق (202 بندًا) = " . round($effBlk, 1) . " · الحاجبُ للعرضِ الخارجي (221) = " . round($effExt, 1) . "\n";
echo "  التحويلُ إلى زمنٍ تقويميّ: اقسِم على (عددُ المهندسين × 0.7 كفاءة).\n";
