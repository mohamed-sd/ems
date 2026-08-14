<?php
/**
 * tools/fix_permgov_classify.php — تصنيفُ الـ١٤٩ **بنمطِ اختبارِ قبولِها**
 * ═══════════════════════════════════════════════════════════════════════════
 * الحكمُ الحاكم: «لا تُغلق بندًا بشاهدٍ أضيقَ من نصِّ اختبارِه». فالتصنيفُ يقرأ
 * **نصَّ اختبارِ القبولِ** (العمود ٢١) ويُسند نمطَ القياسِ منه — لا من نوعِ
 * الثغرةِ ولا من الشاشة.
 *
 * ◆ والترتيبُ **مقصود**: الأخصُّ قبلَ الأعمّ. فنصُّ «من أدخل الساعاتِ لا يعتمدها»
 *   يذكر «اعتماد» و«صلاحية» معًا — ولو فُحص بنمطِ الصلاحيةِ العامِّ لابتلعه.
 * ◆ وكلُّ بندٍ لا يطابق نمطًا معلَنًا يُصنَّف `OTHER` ويُفرَز يدويًّا — ولا
 *   يُحشر في أقربِ نمطٍ ليبدو مقيسًا.
 *
 *   php tools/fix_permgov_classify.php [--tsv=<مسار>] [--pattern=<رمز>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$TSV = null; $ONLY = null;
foreach ($argv as $a) {
    if (strpos($a, '--tsv=') === 0) { $TSV = substr($a, 6); }
    if (strpos($a, '--pattern=') === 0) { $ONLY = substr($a, 10); }
}

/* ── أنماطُ القياسِ وآلياتُها المبنيةُ في المستودع ─────────────────────────── */
$PATTERNS = array(
    /* ① فصلُ واجبات — «من فعل كذا لا يفعل كذا» */
    'SOD' => array(
        'label' => 'فصلُ واجبات',
        'tool'  => 'includes/sod_guard.php',
        're'    => '~من\s+(سجّل|أدخل|نفّذ|أنشأ|أعدّ|قدّم|رفع)|منشئُ|مُدخِلُ|لا يعتمد المرءُ|نفسُه لا يستطيع|لا يستطيع اعتمادَ|ثم حاول بتَّه~u',
    ),
    /* ② سقفُ تفويضٍ وتصعيد */
    'CAP' => array(
        'label' => 'سقفُ تفويضٍ وتصعيد',
        'tool'  => 'authority_delegations · approval_chain',
        're'    => '~سقف|يُصعَّد|تصعيد|مرجعُ تفويض|فوق سقفِ|صاحبِ سقفٍ أعلى~u',
    ),
    /* ③ كسرُ الزجاجِ ومنحٌ مؤقّت */
    'BREAK_GLASS' => array(
        'label' => 'كسرُ الزجاجِ ومنحٌ مؤقّت',
        'tool'  => 'permission_exceptions · cron_permissions',
        're'    => '~كسر الزجاج|كسرِ زجاج|permission_exceptions|يُسقَط تلقائيًّا|valid_to~u',
    ),
    /* ④ حجبُ حقلٍ أو عمود */
    'FIELD_MASK' => array(
        'label' => 'حجبُ حقلٍ أو عمود',
        'tool'  => 'app/Services/Governance/FieldGovernor.php',
        're'    => '~حقلٍ حساس|الحقول الحساسة|يُخفي قيمتَه|يُسقطه من ملف التصدير|العمودُ غائبٌ|بلا ذلك العمود|لا يظهر الحقل~u',
    ),
    /* ⑤ سجلُّ تدقيقٍ أو اطّلاع */
    'AUDIT' => array(
        'label' => 'سجلُّ تدقيقٍ أو اطّلاع',
        'tool'  => 'includes/audit_trail.php · activity_logs',
        're'    => '~سطرَ تدقيق|سجل التدقيق|activity_logs|صفَّ اطّلاع|سجل الاطلاع|read_log|يُسجَّل الرفضُ|قبل وبعد|old_value~u',
    ),
    /* ⑥ عزلُ نطاقٍ أو كيان */
    'SCOPE' => array(
        'label' => 'عزلُ نطاقٍ أو كيان',
        'tool'  => 'TenantDb · fin_project_scope',
        're'    => '~نطاقين|كيانٍ آخر|شركةٍ أخرى|لا يراه في صندوق|عدّادين مختلفين|خارج نطاقه|لا يرى بيانات~u',
    ),
    /* ⑦ ظهورٌ في القائمة */
    'NAV' => array(
        'label' => 'ظهورٌ في القائمة',
        'tool'  => 'tools/fix_nav_href_probe.php',
        're'    => '~سايدبار|القائمةِ الجانبية|يفتح .{0,40}من سايدبار|تعرض المرحلتان|في قائمة الدور~u',
    ),
    /* ⑧ تصدير */
    'EXPORT' => array(
        'label' => 'تصدير',
        'tool'  => 'excel.php · ExcelRegistry',
        're'    => '~can_export|التصدير|ملفِّ التصدير|يُصدَّر|Excel~u',
    ),
    /* ⑨ رفضُ كتابةٍ بلا أثر — النمطُ الذي تقيسه الأداةُ القائمة */
    'DENY_WRITE' => array(
        'label' => 'رفضُ كتابةٍ بلا أثر',
        'tool'  => 'tools/fix_covered_campaign.php',
        're'    => '~يعيد ٤٠٣|يُعيد 403|يُردُّ 403|GOV-PERM-403|بلا can_edit|ولا يُدرج|لا يُنشئ صفًّا|صفرُ صفٍّ|ولا يتغيّر~u',
    ),
    /* ⑩ حالةٌ محكومةٌ لا تُملى من النموذج */
    'STATE_GUARD' => array(
        'label' => 'حالةٌ محكومةٌ لا تُملى',
        'tool'  => 'معالجُ POST — الحالةُ تُحسب لا تُقرأ',
        're'    => '~يبقى الصفُّ|تبقى الحالة|محسوبةً من|لا من المُدخَل|الحقلُ غيرُ موجودٍ في نموذجه|بنفسه غيرُ ممكنة|لا يُقبل نصٌّ حرٌّ~u',
    ),
);

/* ── الحالاتُ والسجل ──────────────────────────────────────────────────────── */
$state = array();
foreach (file($ROOT . '/docs/fix_progress/INJ_findings_state.tsv') as $ln) {
    $p = explode("\t", rtrim($ln, "\r\n"));
    if (count($p) >= 4 && strpos($p[0], 'INJ-') === 0) { $state[trim($p[0])] = trim($p[3]); }
}
$FAMILY = array('Permission Gap', 'Governance Gap');
$rows = array();
$fh = fopen($ROOT . '/docs/fix_2026-08/master_register.tsv', 'r');
$n = 0;
while (($l = fgets($fh)) !== false) {
    $n++; if ($n <= 3) { continue; }
    $c = explode("\t", rtrim($l, "\r\n"));
    if (count($c) < 22 || strpos($c[0], 'INJ-') !== 0) { continue; }
    if (!in_array(trim($c[9]), $FAMILY, true)) { continue; }
    $id = trim($c[0]);
    $st = isset($state[$id]) ? $state[$id] : 'غيرُ مقيس';
    if ($st === 'مُغلقٌ بشاهد') { continue; }
    $test = trim($c[20]);
    $pat = 'OTHER';
    foreach ($PATTERNS as $k => $def) { if (preg_match($def['re'], $test)) { $pat = $k; break; } }
    $rows[$id] = array('id' => $id, 'dept' => trim($c[3]), 'scr' => trim($c[4]), 'url' => trim($c[5]),
        'type' => trim($c[9]), 'sev' => trim($c[10]), 'test' => $test, 'pattern' => $pat,
        'half' => (trim($c[10]) === 'P0' || trim($c[10]) === 'P1') ? '①' : '②');
}
fclose($fh);

$byPat = array(); $byPatHalf = array();
foreach ($rows as $r) {
    $byPat[$r['pattern']] = (isset($byPat[$r['pattern']]) ? $byPat[$r['pattern']] : 0) + 1;
    $k = $r['pattern'] . '|' . $r['half'];
    $byPatHalf[$k] = (isset($byPatHalf[$k]) ? $byPatHalf[$k] : 0) + 1;
}
arsort($byPat);

echo "══ تصنيفُ النطاقِ بنمطِ اختبارِ القبول ══\n\n";
echo "  النطاق: " . count($rows) . " بندًا\n\n";
printf("  %-13s %-30s %5s %5s %5s\n", 'الرمز', 'النمط', 'الكل', '①', '②');
echo '  ' . str_repeat('─', 62) . "\n";
foreach ($byPat as $k => $v) {
    $lab = isset($PATTERNS[$k]) ? $PATTERNS[$k]['label'] : 'غيرُ مصنَّف — يُفرَز يدويًّا';
    $h1 = isset($byPatHalf[$k . '|①']) ? $byPatHalf[$k . '|①'] : 0;
    $h2 = isset($byPatHalf[$k . '|②']) ? $byPatHalf[$k . '|②'] : 0;
    printf("  %-13s %-30s %5d %5d %5d\n", $k, mb_substr($lab, 0, 28), $v, $h1, $h2);
}
echo "\n  الآلياتُ المبنيةُ التي يستعملها كلُّ نمط:\n";
foreach ($PATTERNS as $k => $def) {
    if (!isset($byPat[$k])) { continue; }
    printf("     %-13s %s\n", $k, $def['tool']);
}

if ($ONLY !== null) {
    echo "\n── بنودُ {$ONLY}:\n";
    foreach ($rows as $r) {
        if ($r['pattern'] !== $ONLY) { continue; }
        printf("  %-10s %s %-4s %-26s %s\n", $r['id'], $r['half'], $r['sev'],
            mb_substr($r['dept'], 0, 24), mb_substr($r['test'], 0, 90));
    }
}

if ($TSV !== null) {
    $out = "id\thalf\tsev\tdept\tscr\turl\tpattern\ttest\n";
    foreach ($rows as $r) {
        $out .= implode("\t", array($r['id'], $r['half'], $r['sev'], $r['dept'], $r['scr'],
            $r['url'], $r['pattern'], str_replace(array("\t", "\n"), ' ', $r['test']))) . "\n";
    }
    $path = (strpos($TSV, ':') !== false) ? $TSV : ($ROOT . '/' . $TSV);
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $out);
    echo "\n  · كُتب: {$TSV}\n";
}
exit(0);
