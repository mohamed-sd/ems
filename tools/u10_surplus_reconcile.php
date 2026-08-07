<?php
/**
 * tools/u10_surplus_reconcile.php — مصالحة ورقة الزائد (U10-G43 · DEF-008)
 * ───────────────────────────────────────────────────────────────────────────
 * نسخة الفريق (1,261 صفًّا في CMP03_EXTRAS_DECISION_ar.csv) يُملأ حكمُ كلِّ
 * صفٍّ فيها بمرجعية قرار المالك القائم **ق-17 (2026-08-06): «الزائد إبقاءٌ
 * شاملٌ — لا حذف»** + الحكم التصنيفي المقترح من محرك CMP:
 *   «يدخل المستند»  → يدخل المستند (ق-17)
 *   «مرشح للإلغاء» → يبقى موسومًا «شبهة ازدواج» — لا حذف (ق-17) ويُراجَع وظيفيًّا
 * الشاهد: صفر صف بلا حكم. المخرج: docs/update0010/SURPLUS_RECONCILIATION_ar.csv
 * php tools/u10_surplus_reconcile.php
 */
if (PHP_SAPI !== 'cli') { die("CLI only\n"); }
$ROOT = dirname(__DIR__);
$in = $ROOT . '/docs/CMP03_EXTRAS_DECISION_ar.csv';
$out = $ROOT . '/docs/update0010/SURPLUS_RECONCILIATION_ar.csv';
$fi = fopen($in, 'r');
$fo = fopen($out, 'w');
fwrite($fo, "\xEF\xBB\xBF");
$header = fgetcsv($fi);
$header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
$header[] = 'الحكم النافذ (مصالحة U10 · ق-17)';
fputcsv($fo, $header);
$cnt = array('enter' => 0, 'keep_flag' => 0, 'preset' => 0, 'other' => 0);
while (($row = fgetcsv($fi)) !== false) {
    $prop = isset($row[5]) ? trim((string) $row[5]) : '';
    $ownerCol = isset($row[7]) ? trim((string) $row[7]) : '';
    if ($ownerCol !== '') {
        $verdict = $ownerCol . ' (قرار سابق مثبت)';
        $cnt['preset']++;
    } elseif (mb_strpos($prop, 'يدخل') !== false) {
        $verdict = 'يدخل المستند — ق-17 إبقاء شامل';
        $cnt['enter']++;
    } elseif (mb_strpos($prop, 'الإلغاء') !== false || mb_strpos($prop, 'إلغاء') !== false) {
        $verdict = 'يبقى موسومًا «شبهة ازدواج» — لا حذف (ق-17) ويُراجع وظيفيًّا عند توحيد شاشته';
        $cnt['keep_flag']++;
    } else {
        $verdict = 'يبقى — لا حذف (ق-17)';
        $cnt['other']++;
    }
    $row[] = $verdict;
    fputcsv($fo, $row);
}
fclose($fi);
fclose($fo);
$total = array_sum($cnt);
fwrite(STDOUT, "══ مصالحة الزائد — $total صفًّا ══\n");
fwrite(STDOUT, "  يدخل المستند: {$cnt['enter']}\n  يبقى موسومًا (شبهة ازدواج): {$cnt['keep_flag']}\n  قرار سابق مثبت: {$cnt['preset']}\n  إبقاء عام: {$cnt['other']}\n");
fwrite(STDOUT, "الشاهد: صفر صف بلا حكم ✔\nالمخرج: $out\n");
