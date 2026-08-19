<?php
/**
 * tools/xf_effort.php — قياسُ حجمِ العملِ وقابليةِ الفرزِ الآليّ (XF-01)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لا يُقدَّر شيءٌ هنا — كلُّ رقمٍ يُقرأ من الشجرةِ الحيّةِ ومن القاعدة:
 *   ① حجمُ العمل: كم شاشةً يصلها المنفِّذُ **كاملًا**، وكم **جزئيًّا**، وكم يرفض —
 *     بفحصِ **مراسي** كلِّ شاشةٍ واحدةً واحدةً لا بمتوسِّطٍ مفترَض.
 *   ② الفرزُ الآليُّ «أساسيّ/إضافيّ»: يُقاس بمطابقةِ **قيدِ القاعدة** (NOT NULL
 *     بلا افتراض) مع **`required` في النموذجِ الحيّ**. فإن تطابقا كان الفرزُ
 *     الآليُّ ممكنًا، وإن تفرّقا فالمقياسُ الواحدُ لا يكفي وحدَه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/xf_lib.php';
$conn = xf_db($ROOT);
if (!$conn) { exit("⛔ لا اتصال\n"); }

$plan = json_decode(file_get_contents($ROOT . '/docs/xf_plans/all.json'), true);
if (!$plan) { exit("⛔ لا خطة — شغّل: php tools/xf_plan.php --all\n"); }

/* ═══ ① حجمُ العمل — بفحصِ مراسي كلِّ شاشة ═══════════════════════════════ */
$A = array('full' => 0, 'partial' => 0, 'headsOnly' => 0, 'noTable' => 0, 'noWork' => 0);
$anchor = array('include' => 0, 'row' => 0, 'form' => 0);
$colsFull = 0; $colsPartial = 0; $screensWithWork = array();

foreach ($plan['screens'] as $s) {
    $work = 0;
    foreach ($s['columns'] as $c) { if ($c['verdict'] === 'BIND' || $c['verdict'] === 'NEW') { $work++; } }
    if (!$work) { $A['noWork']++; continue; }
    if (empty($s['table']) || empty($s['table_exists'])) { $A['noTable']++; continue; }

    $src = @file_get_contents($ROOT . '/' . $s['screen']);
    if ($src === false) { $A['noTable']++; continue; }

    $hasInc = false;
    foreach (array("include '../config.php';", 'include "../config.php";',
                   "include_once '../config.php';", "require_once __DIR__ . '/../config.php';") as $a) {
        if (substr_count($src, $a) === 1) { $hasInc = true; break; }
    }
    $rowCnt = substr_count($src, 'echo "</tr>";') + substr_count($src, "echo '</tr>';");
    $hasVar = (bool) preg_match('/foreach\s*\(\s*\$[a-z_][a-z0-9_]*\s+as\s+(\$[a-z_][a-z0-9_]*)\s*\)/i', $src);
    $hasRow = ($rowCnt === 1 && $hasVar);
    $hasForm = (substr_count($src, '<div class="pu-form-actions">') === 1
             || substr_count($src, '<div class="form-actions">') === 1);

    if ($hasInc) { $anchor['include']++; }
    if ($hasRow) { $anchor['row']++; }
    if ($hasForm) { $anchor['form']++; }

    $screensWithWork[$s['screen']] = $work;
    if ($hasInc && $hasRow && $hasForm) { $A['full']++; $colsFull += $work; }
    elseif ($hasInc && $hasRow)         { $A['partial']++; $colsPartial += $work; }
    else                                { $A['headsOnly']++; $colsPartial += $work; }
}

echo "════ ① حجمُ العمل — مقيسٌ بمراسي كلِّ شاشةٍ على حدة ════\n";
echo "  شاشاتٌ فيها عملٌ آليّ: " . count($screensWithWork) . " · أعمدتُها: " . array_sum($screensWithWork) . "\n";
printf("  · يصلها المنفِّذُ **كاملًا** (٣ مراسٍ): %3d شاشةً · %3d عمودًا\n", $A['full'], $colsFull);
printf("  · جزئيًّا (رأس+خلية، والنموذجُ يدويّ): %3d شاشةً\n", $A['partial']);
printf("  · رؤوسٌ فقط (الباقي يدويّ):            %3d شاشةً\n", $A['headsOnly']);
printf("  · جدولٌ غيرُ مؤكَّدٍ (تلزمها --table=):   %3d شاشةً\n", $A['noTable']);
echo "  توفُّرُ المراسي: استدعاءٌ {$anchor['include']} · صفٌّ {$anchor['row']} · نموذجٌ {$anchor['form']}"
   . " (من " . count($screensWithWork) . ")\n";
echo "  ملاحظة: جمعُ POST يدويٌّ **دائمًا** — فكلُّ شاشةٍ فيها عمودٌ NEW تلزمها لمسةٌ بشرية.\n";

/* عددُ الشاشاتِ التي فيها NEW (أي تحتاج جمعَ POST يدويًّا) */
$withNew = 0;
foreach ($plan['screens'] as $s) {
    foreach ($s['columns'] as $c) { if ($c['verdict'] === 'NEW') { $withNew++; break; } }
}
echo "  شاشاتٌ فيها عمودٌ NEW (تلزمها لمسةُ POST): {$withNew}\n";

/* ═══ ② هل يُفرَز «أساسيّ/إضافيّ» آليًّا؟ — يُقاس لا يُفترَض ══════════════ */
echo "\n════ ② الفرزُ الآليُّ «أساسيّ/إضافيّ» — مقيسٌ على النماذجِ الحيّة ════\n";

/* ◆ الفرضية: العمودُ **أساسيٌّ** إن كان `NOT NULL` بلا افتراضٍ في القاعدة.
     تُختبَر بمطابقتِها مع `required` المكتوبةِ في النموذجِ فعلًا. */
$tables = array();
foreach ($plan['screens'] as $s) { if (!empty($s['table'])) { $tables[$s['table']] = 1; } }

$tot = array('nn' => 0, 'null' => 0);
foreach (array_keys($tables) as $t) {
    $cols = xf_table_columns($conn, $t);
    foreach ($cols as $n => $c) {
        if (in_array($n, array('id', 'company_id', 'created_at', 'updated_at', 'is_deleted',
                               'deleted_at', 'deleted_by', 'created_by'), true)) { continue; }
        if ($c['Null'] === 'NO' && ($c['Default'] === null || $c['Default'] === '') && $c['Extra'] !== 'auto_increment') { $tot['nn']++; }
        else { $tot['null']++; }
    }
}
$den = $tot['nn'] + $tot['null'];
printf("  أعمدةُ %d جدولًا (بعدَ طرحِ أعمدةِ الحوكمة): %d\n", count($tables), $den);
printf("  · NOT NULL بلا افتراضٍ  ⇒ مرشَّحٌ «أساسيّ»: %5d (%.1f%%)\n", $tot['nn'], $den ? 100 * $tot['nn'] / $den : 0);
printf("  · يقبل NULL أو له افتراض ⇒ «إضافيّ»:      %5d (%.1f%%)\n", $tot['null'], $den ? 100 * $tot['null'] / $den : 0);

/* ── الاختبارُ الحاسم: هل يطابق قيدُ القاعدةِ ما كتبه المطوّرُ `required`؟ ── */
$agree = 0; $dbOnly = 0; $formOnly = 0; $checked = 0; $samples = array();
foreach ($plan['screens'] as $s) {
    if (empty($s['table']) || empty($s['table_exists'])) { continue; }
    $src = @file_get_contents($ROOT . '/' . $s['screen']);
    if ($src === false) { continue; }
    /* حقولُ النموذجِ الحيّة: name="…" مع/بلا required */
    if (!preg_match_all('/<(input|select|textarea)\b([^>]*)>/i', $src, $m, PREG_SET_ORDER)) { continue; }
    $cols = xf_table_columns($conn, $s['table']);
    foreach ($m as $x) {
        if (!preg_match('/\bname="([a-z_][a-z0-9_]*)"/i', $x[2], $nm)) { continue; }
        $name = $nm[1];
        if (!isset($cols[$name])) { continue; }
        if (preg_match('/\btype="(hidden|submit|button)"/i', $x[2])) { continue; }
        $c = $cols[$name];
        $dbEssential   = ($c['Null'] === 'NO' && ($c['Default'] === null || $c['Default'] === ''));
        $formRequired  = (bool) preg_match('/\brequired\b/i', $x[2]);
        $checked++;
        if ($dbEssential === $formRequired) { $agree++; }
        elseif ($dbEssential) { $dbOnly++;   if (count($samples) < 4) { $samples[] = "القاعدةُ تلزمه والنموذجُ لا: {$s['table']}.{$name}"; } }
        else                  { $formOnly++; if (count($samples) < 8) { $samples[] = "النموذجُ يلزمه والقاعدةُ لا: {$s['table']}.{$name}"; } }
    }
}
echo "\n  ── المطابقةُ بين قيدِ القاعدةِ و`required` في النموذجِ الحيّ ──\n";
printf("  حقولٌ قِيست: %d · متطابقة: %d (%.1f%%)\n", $checked, $agree, $checked ? 100 * $agree / $checked : 0);
printf("  · القاعدةُ تلزمه والنموذجُ لا يلزمه: %d\n", $dbOnly);
printf("  · النموذجُ يلزمه والقاعدةُ لا تلزمه: %d\n", $formOnly);
foreach ($samples as $s2) { echo "      · {$s2}\n"; }
if ($checked === 0) { echo "  ◌ غيرُ مقيس — لا حقلَ طابق اسمُه عمودًا\n"; }
