<?php
/** قياسُ توزّعِ الدَينِ: داخلَ النطاقِ المحميِّ مقابلَ خارجَه — قراءةٌ فقط */
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$W = 'inheader\.php|fin_analysis_shell\.php|eng01_screen_view\.php|u13_screen_kit\.php|dept_gov_space\.php|dept_risk_space\.php';
$scope = array();
foreach (file(__DIR__ . '/uxw_scope.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    $ln = trim($ln);
    if ($ln !== '' && $ln[0] !== '#') { $scope[$ln] = true; }
}
$in = array('n' => 0, 'style' => 0, 'declared' => 0, 'color' => 0);
$out = array('n' => 0, 'style' => 0, 'declared' => 0, 'color' => 0);
$worst = array();
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    $p = str_replace('\\', '/', $f->getPathname());
    if (!preg_match('/\.php$/', $p)) continue;
    if (preg_match('#/(vendor|node_modules|database|docs|tests|tools|\.git|\.claude|storage|app/|includes/)#', $p)) continue;
    $s = @file_get_contents($f->getPathname());
    if ($s === false || !preg_match('/(require|include)(_once)?\s*[( ].*(' . $W . ')/u', $s)) continue;
    $rel = str_replace($ROOT . '/', '', $p);
    $st = preg_match_all('/style\s*=\s*["\']/u', $s);
    $dec = preg_match_all('/data-allow-style/u', $s);
    $co = preg_match_all('/#[0-9a-fA-F]{6}\b/u', $s);
    $t = isset($scope[$rel]) ? 'in' : 'out';
    if ($t === 'in') { $in['n']++; $in['style'] += $st; $in['declared'] += $dec; $in['color'] += $co; }
    else {
        $out['n']++; $out['style'] += $st; $out['declared'] += $dec; $out['color'] += $co;
        if ($st + $co > 0) { $worst[$rel] = $st + $co; }
    }
}
printf("داخلَ النطاقِ المحميّ : %3d شاشة · أنماط %5d (منها مصرَّحٌ بها %d) · ألوان %5d\n",
    $in['n'], $in['style'], $in['declared'], $in['color']);
printf("خارجَ النطاق        : %3d شاشة · أنماط %5d · ألوان %5d\n", $out['n'], $out['style'], $out['color']);
printf("متوسطُ الدَينِ للشاشةِ غيرِ المرحَّلة: %.1f نمطًا و%.1f لونًا\n",
    $out['n'] ? $out['style'] / $out['n'] : 0, $out['n'] ? $out['color'] / $out['n'] : 0);
arsort($worst);
echo "\nأثقلُ عشرِ شاشاتٍ غيرِ مرحَّلة (نمط+لون):\n";
$i = 0;
foreach ($worst as $k => $v) { printf("  %-46s %d\n", $k, $v); if (++$i >= 10) break; }
printf("\nشاشاتٌ غيرُ مرحَّلةٍ بلا دَينٍ إطلاقًا: %d\n", $out['n'] - count($worst));
