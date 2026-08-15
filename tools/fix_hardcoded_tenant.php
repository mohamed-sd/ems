<?php
/**
 * tools/fix_hardcoded_tenant.php — لا رقمَ شركةٍ صلبٌ في سطحٍ
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0203 · INJ-0408 · INJ-0425 · INJ-0579
 *
 * يستبدل احتياطيَّ «الشركة ٤» المرمَّزَ بنطاقٍ مشتقٍّ من السياق:
 *     `$co = $company_id ?: 4;`                    ⇒ `ems_scope_company($conn)`
 *     `if ($company_id <= 0) { $company_id = 4; }` ⇒ `ems_scope_company($conn)`
 *     `$co = $is_super && $company_id <= 0 ? 4 : $company_id;` ⇒ نفسُه
 *
 * ◆ ويعمل بوضعين: `--scan` يقيس ويرسب على ما بقي (حارسٌ دائم)،
 *   و`--apply` يحوّل. والتحويلُ يُفحص نحويًّا قبل الكتابة.
 * ◆ ولا يمسّ `tools/` ولا `tests/`: أدواتُ الصيانةِ تعمل بلا جلسة، ونطاقُها
 *   يُمرَّر إليها صراحةً — فليست أسطحًا.
 *
 * التشغيل: php tools/fix_hardcoded_tenant.php [--scan|--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT  = str_replace('\\', '/', dirname(__DIR__));
$APPLY = in_array('--apply', $argv, true);

/* الأنماطُ الثلاثةُ المرصودة — كلٌّ بمرساتِه واستبدالِه */
$PATTERNS = array(
    /* $co = $company_id ?: 4;   ·   $co = $company_id ? $company_id : 4; */
    '~\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*\$company_id\s*\?:\s*4\s*;~'
        => '$$1 = ems_scope_company($conn);',
    /* $co = $is_super_admin && $company_id <= 0 ? 4 : $company_id;  (وبأيِّ اسمِ علم) */
    '~\$([A-Za-z_][A-Za-z0-9_]*)(\s*)=\s*\$[A-Za-z_][A-Za-z0-9_]*\s*&&\s*\$company_id\s*<=\s*0\s*\?\s*4\s*:\s*\(?\s*(?:\(int\)\s*)?\$company_id\s*\)?\s*;~'
        => '$$1$2= ems_scope_company($conn);',
    /* if ([$flag &&] $company_id <= 0) { $company_id = 4; } */
    '~if\s*\((?:\s*\$[A-Za-z_][A-Za-z0-9_]*\s*&&)?\s*\$company_id\s*<=\s*0\s*\)\s*\{\s*\$company_id\s*=\s*4;\s*\}~'
        => '$company_id = ems_scope_company($conn);',
);

$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = str_replace('\\', '/', $f->getPathname());
    if (substr($p, -4) !== '.php') { continue; }
    foreach (array('/.claude/', '/storage/backups/', '/vendor/', '/node_modules/', '/tests/', '/tools/') as $sk) {
        if (strpos($p, $sk) !== false) { continue 2; }
    }
    $files[] = $p;
}
sort($files);

$hits = array(); $done = 0;
foreach ($files as $abs) {
    $rel = ltrim(str_replace($ROOT, '', $abs), '/');
    $src = (string) @file_get_contents($abs);
    if ($src === '' || strpos($src, 'company_id') === false) { continue; }
    /* ◆ الحكمُ على **الشِّفرةِ المنفَّذةِ** لا على التعليق: توثيقُ النمطِ في شرحٍ
         ليس ارتكابَه — وفاحصٌ يخلط بينهما يُدين وثيقتَه نفسَها. */
    $code = '';
    foreach (@token_get_all($src) as $t) {
        if (is_array($t) && in_array($t[0], array(T_COMMENT, T_DOC_COMMENT), true)) { continue; }
        $code .= is_array($t) ? $t[1] : $t;
    }
    $inCode = 0;
    foreach (array_keys($PATTERNS) as $re) { $inCode += (int) preg_match_all($re, $code); }
    if ($inCode === 0) { continue; }
    $out = $src; $n = 0;
    foreach ($PATTERNS as $re => $to) {
        $c = 0;
        $r = preg_replace($re, $to, $out, -1, $c);
        /* ◆ `preg_replace` تُرجع null على نمطٍ معطوب — ولا يُكتب null فوق ملف */
        if ($r === null) { echo "  ✘ نمطٌ معطوبٌ عند {$rel}\n"; continue; }
        $out = $r; $n += $c;
    }
    if ($n === 0) { continue; }
    $hits[] = array('file' => $rel, 'n' => $n);
    if (!$APPLY) { continue; }

    /* المُوصِلُ يُحمَّل مرةً — بعد `config.php` أو في أعلى الملفِّ المنطقيّ */
    if (strpos($out, "includes/tenant_scope.php") === false) {
        $inc = "require_once __DIR__ . '/../includes/tenant_scope.php';   // نطاقُ الكيانِ من السياقِ لا من رقمٍ صلب\n";
        if (substr_count($rel, '/') === 0) {
            $inc = "require_once __DIR__ . '/includes/tenant_scope.php';   // نطاقُ الكيانِ من السياقِ لا من رقمٍ صلب\n";
        }
        if (preg_match('~^(.*?(?:include|require)(?:_once)?\s*[\'"][^\'"]*config\.php[\'"]\s*;\s*\n)~s', $out, $m)) {
            $out = $m[1] . $inc . substr($out, strlen($m[1]));
        } else {
            $out = preg_replace('~^(<\?php\s*\n)~', '$1' . $inc, $out, 1);
        }
    }
    $tmp = sys_get_temp_dir() . '/tsc_' . md5($rel) . '.php';
    file_put_contents($tmp, $out);
    $o = array(); $rc = 1;
    @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $o, $rc);
    @unlink($tmp);
    if ($rc !== 0) { echo "  ✘ {$rel}: رسب الفحصُ النحويّ — " . implode(' ', $o) . "\n"; continue; }
    if (strlen($out) < strlen($src) * 0.9) { echo "  ✘ {$rel}: ناتجٌ منكمشٌ — لا كتابة\n"; continue; }
    file_put_contents($abs, $out);
    $done++;
    echo "  ✔ {$rel} — {$n} موضعًا\n";
}

echo "\n══ رقمُ الشركةِ الصلبُ في الأسطح ══\n";
echo '  أسطحٌ مصابة: ' . count($hits) . ' · مواضعُ: '
   . array_sum(array_column($hits, 'n')) . ($APPLY ? (' · حُوّل: ' . $done) : '') . "\n\n";
if (!$hits) {
    echo "  ✔ **صفرُ رقمِ شركةٍ صلبٍ** — النطاقُ من السياقِ في كلِّ سطح\n";
} else {
    foreach ($hits as $x) { echo '  · ' . str_pad($x['file'], 46) . $x['n'] . "\n"; }
}
exit(empty($hits) ? 0 : 1);
