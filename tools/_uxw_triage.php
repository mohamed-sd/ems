<?php
/**
 * tools/_uxw_triage.php — ترتيبُ الشاشاتِ غيرِ المرحَّلةِ بالشدّة (مسبارُ قراءةٍ فقط)
 * يُخرِج CSV: مسار,أنماط,ألوان,جدول_محلي,حقول_بلا_عنوان,مصطلح,الشدّة,المجلد
 */
define('EMS_CLI', true);
$ROOT = str_replace(chr(92), '/', dirname(__DIR__));
$WRAPPERS = 'inheader\.php|fin_analysis_shell\.php|eng01_screen_view\.php|u13_screen_kit\.php|dept_gov_space\.php|dept_risk_space\.php';
$TERMS = array('خارج الوثيقة', 'بانتظار المالك', 'إضافات للمالك', 'Activation Pattern',
               'Visibility Guard', 'الرسم لا يعرض محاور افتراضية', 'نراجع السجلات', 'نبدأ من هنا');
$scope = array();
foreach (file(__DIR__ . '/uxw_scope.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    $ln = trim($ln);
    if ($ln !== '' && $ln[0] !== '#') $scope[str_replace(chr(92),'/',$ln)] = true;
}
$rows = array();
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    $p = str_replace(chr(92), '/', $f->getPathname());
    if (!preg_match('/\.php$/', $p)) continue;
    if (preg_match('#/(vendor|node_modules|database|docs|tests|tools|\.git|\.claude|storage|app/|includes/)#', $p)) continue;
    $s = @file_get_contents($f->getPathname());
    if ($s === false) continue;
    if (!preg_match('/(require|include)(_once)?\s*[( ].*(' . $WRAPPERS . ')/u', $s)) continue;
    $rel = str_replace($ROOT . '/', '', $p);
    if (isset($scope[$rel])) continue;
    $inline = preg_match_all('/style\s*=\s*["\']/u', $s);
    $colors = preg_match_all('/#[0-9a-fA-F]{6}\b/u', $s);
    $ldt    = preg_match('/\.DataTable\s*\(\s*\{/u', $s) ? 1 : 0;
    $term   = 0; foreach ($TERMS as $t) if (mb_stripos($s, $t) !== false) { $term = 1; break; }
    /* حقولٌ بلا عنوان: input/select/textarea بلا id ولا aria-label ولا placeholder */
    $nolabel = 0;
    if (preg_match_all('/<(input|select|textarea)\b[^>]*>/ui', $s, $m)) {
        foreach ($m[0] as $tag) {
            if (preg_match('/type\s*=\s*["\'](hidden|submit|button|reset|checkbox|radio)["\']/ui', $tag)) continue;
            if (preg_match('/aria-label|placeholder|\bid\s*=/ui', $tag)) continue;
            $nolabel++;
        }
    }
    $sev = $inline + $colors + $ldt * 5 + $nolabel * 2 + $term * 10;
    $dir = dirname($rel); if ($dir === '.') $dir = '(root)';
    $rows[] = array($rel, $inline, $colors, $ldt, $nolabel, $term, $sev, $dir);
}
usort($rows, function ($a, $b) { return $b[6] <=> $a[6]; });
$mode = $argv[1] ?? 'csv';
if ($mode === 'dirs') {
    $agg = array();
    foreach ($rows as $r) {
        if (!isset($agg[$r[7]])) $agg[$r[7]] = array(0,0,0,0);
        $agg[$r[7]][0]++; $agg[$r[7]][1] += $r[1]; $agg[$r[7]][2] += $r[2]; $agg[$r[7]][3] += $r[6];
    }
    uasort($agg, function ($a, $b) { return $b[3] <=> $a[3]; });
    printf("%-34s %5s %7s %7s %8s\n", 'المجلد', 'شاشات', 'أنماط', 'ألوان', 'الشدّة');
    foreach ($agg as $d => $a) printf("%-34s %5d %7d %7d %8d\n", $d, $a[0], $a[1], $a[2], $a[3]);
    printf("%-34s %5d %7d %7d %8d\n", 'الإجمالي', count($rows),
        array_sum(array_column($rows,1)), array_sum(array_column($rows,2)), array_sum(array_column($rows,6)));
    exit(0);
}
echo "file,inline,colors,localdt,nolabel,term,severity,dir\n";
foreach ($rows as $r) echo implode(',', $r) . "\n";
