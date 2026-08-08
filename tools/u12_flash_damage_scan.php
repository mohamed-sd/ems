<?php
/**
 * tools/u12_flash_damage_scan.php — كشفُ ما أفسده الترحيلُ الآليّ
 * ═══════════════════════════════════════════════════════════════════════════
 * المُرحِّلاتُ حوّلت وجهةَ التحويلِ ونصَّه إلى نصوصٍ مفردةِ الاقتباس. وهذا سليمٌ
 * حين كان الأصلُ نصًّا حرفيًّا، وفاسدٌ حين كان نصًّا مزدوجَ الاقتباسِ يحمل
 * إقحامًا: «$متغيّر» أو «{$مصفوفة['مفتاح']}» أو وصلًا مهروبَ الاقتباس. فالنصُّ
 * المفردُ لا يُقحِم شيئًا — يطبع الدولارَ حرفًا.
 *
 * هذه الأداةُ تقرأ الرموزَ (لا التعبيرَ النمطيَّ) وتُبلّغ عن كلِّ نصٍّ مفردِ
 * الاقتباسِ داخلَ نداءِ ems_gov_flash_redirect يحمل أثرَ إقحامٍ ضائع.
 *
 * التشغيل: php tools/u12_flash_damage_scan.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');

$hits = array();
$scanned = 0;

foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $src = (string) file_get_contents($f);
        if (strpos($src, 'ems_gov_flash_redirect') === false) { continue; }
        $rel = str_replace('\\', '/', substr($f, strlen($ROOT) + 1));
        $scanned++;
        $toks = @token_get_all($src);
        if (!$toks) { continue; }

        $inCall = 0; $depth = 0;
        for ($i = 0, $n = count($toks); $i < $n; $i++) {
            $t = $toks[$i];
            if (is_array($t) && $t[0] === T_STRING && $t[1] === 'ems_gov_flash_redirect') {
                $inCall = 1; $depth = 0; continue;
            }
            if (!$inCall) { continue; }
            if (!is_array($t)) {
                if ($t === '(') { $depth++; }
                elseif ($t === ')') { $depth--; if ($depth <= 0) { $inCall = 0; } }
                continue;
            }
            if ($t[0] !== T_CONSTANT_ENCAPSED_STRING) { continue; }
            $s = $t[1];
            if ($s === '' || $s[0] !== "'") { continue; }   // المفردُ وحدَه محلُّ الشبهة
            $body = substr($s, 1, -1);
            $why = null;
            if (preg_match('~\$\{?[A-Za-z_]~', $body))       { $why = 'إقحامُ متغيّرٍ ضائع'; }
            elseif (strpos($body, '" . ') !== false)          { $why = 'وصلٌ ضائعٌ داخلَ نص'; }
            elseif (strpos($body, ' . "') !== false)          { $why = 'وصلٌ ضائعٌ داخلَ نص'; }
            elseif (strpos($body, '{$') !== false)            { $why = 'إقحامٌ مُعقَّفٌ ضائع'; }
            if ($why !== null) {
                $hits[] = array($rel, $t[2], $why, mb_substr($body, 0, 90));
            }
        }
    }
}

echo "كشفُ ما أفسده الترحيلُ الآليّ — إقحامٌ ضاع في نصٍّ مفردِ الاقتباس\n";
echo str_repeat('═', 68), "\n";
echo "ملفاتٌ فُحصت: {$scanned}\n";
echo "مواضعُ مشبوهة: " . count($hits) . "\n\n";
foreach ($hits as $hh) {
    echo '  ✘ ' . $hh[0] . ':' . $hh[1] . ' — ' . $hh[2] . "\n";
    echo '      ' . $hh[3] . "\n";
}
exit(count($hits) === 0 ? 0 : 1);
