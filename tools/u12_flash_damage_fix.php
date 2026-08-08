<?php
/**
 * tools/u12_flash_damage_fix.php — ردُّ الإقحامِ الذي ابتلعه الترحيل
 * ═══════════════════════════════════════════════════════════════════════════
 * الفسادُ: نصٌّ كان مزدوجَ الاقتباسِ يُقحِم متغيّرًا («تم توليد $n حدث ✅») أو
 * يصل تعبيرًا («❌ " . $err . "») صار نصًّا مفردَ الاقتباس — فالمستخدمُ يقرأ
 * «$n» حرفًا لا رقمًا. والفسادُ الثاني: وصلٌ مفردُ الاقتباسِ هُرِّبت أقواسُه
 * («أُجّلت \' . $days . \' يومًا») فصار الوصلُ نصًّا.
 *
 * الردُّ بحالتين لا ثالثةَ لهما:
 *   ① الجسمُ فيه «\'» ⇒ الأصلُ وصلٌ مفردُ الاقتباس ⇒ يُنزع التهريبُ ويُترك
 *      الوصلُ كما كان (بلا غلافٍ خارجيّ).
 *   ② غيرُ ذلك ⇒ الأصلُ نصٌّ مزدوجُ الاقتباس ⇒ يُعاد غلافُه مزدوجًا، فيعود
 *      الإقحامُ «$س» و«{$س}» والوصلُ «" . س . "» إلى معناه.
 *
 * وبعد كلِّ ملفٍ: فحصُ صياغةٍ وتراجعٌ عند الفساد.
 *
 * التشغيل: php tools/u12_flash_damage_fix.php [--dry]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$dry = in_array('--dry', $argv, true);
$BACKUP = $ROOT . '/storage/backups/u12_damagefix_' . date('Ymd_His');
if (!$dry) { @mkdir($BACKUP, 0777, true); }

$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');

$php = PHP_BINARY;
$files = 0; $fixed = 0; $revert = 0; $locked = 0;
$log = array();

foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $src = (string) file_get_contents($f);
        if (strpos($src, 'ems_gov_flash_redirect') === false) { continue; }
        $rel = str_replace('\\', '/', substr($f, strlen($ROOT) + 1));
        $orig = $src;

        $toks = @token_get_all($src);
        if (!$toks) { continue; }
        $offs = array(); $p = 0;
        foreach ($toks as $i => $t) { $offs[$i] = $p; $p += strlen(is_array($t) ? $t[1] : $t); }

        $edits = array();
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
            if ($s === '' || $s[0] !== "'") { continue; }
            $body = substr($s, 1, -1);

            $hasVar = preg_match('~\$\{?[A-Za-z_]~', $body) === 1;
            $hasCat = (strpos($body, '" . ') !== false) || (strpos($body, ' . "') !== false);
            if (!$hasVar && !$hasCat) { continue; }

            /* أولًا يُنزع التهريبُ الذي أضافه المُرحِّل، فيعود نصُّ المصدرِ كما
               كتبه الإنسانُ قبل الترحيل. ثم يُختار الغلافُ من شكلِ ما ظهر: */
            $raw = str_replace(array("\\'", '\\\\'), array("'", '\\'), $body);

            if (strpos($raw, '" . ') !== false || strpos($raw, ' . "') !== false) {
                /* ① وصلٌ يفتح ويغلق باقتباسٍ مزدوج ⇒ الغلافُ مزدوج. */
                $repl = '"' . $raw . '"'; $kind = '①';
            } elseif (strpos($raw, "' . ") !== false || strpos($raw, " . '") !== false) {
                /* ② وصلٌ يفتح ويغلق باقتباسٍ مفرد ⇒ الغلافُ مفرد. */
                $repl = "'" . $raw . "'"; $kind = '②';
            } else {
                /* ③ إقحامُ متغيّرٍ بلا وصلٍ ⇒ الغلافُ مزدوجٌ ليعود الإقحام. */
                $repl = '"' . $raw . '"'; $kind = '③';
            }
            $edits[] = array($offs[$i], strlen($s), $repl, $rel . ':' . $t[2] . ' ' . $kind);
        }

        if (!$edits) { continue; }
        for ($e = count($edits) - 1; $e >= 0; $e--) {
            $src = substr($src, 0, $edits[$e][0]) . $edits[$e][2] . substr($src, $edits[$e][0] + $edits[$e][1]);
        }
        if ($dry) {
            $files++; $fixed += count($edits);
            foreach ($edits as $e) { $log[] = $e[3]; }
            continue;
        }
        if (!is_writable($f)) { $locked++; continue; }
        @copy($f, $BACKUP . '/' . str_replace(array('/', '\\'), '__', $rel));
        if (@file_put_contents($f, $src) === false) { $locked++; continue; }
        $lint = array(); $rc = 0;
        exec('"' . $php . '" -l ' . escapeshellarg($f) . ' 2>&1', $lint, $rc);
        if ($rc !== 0) {
            file_put_contents($f, $orig);
            $revert++;
            $log[] = $rel . ' — تراجعٌ: ' . trim(implode(' ', $lint));
            continue;
        }
        $files++; $fixed += count($edits);
        foreach ($edits as $e) { $log[] = $e[3]; }
    }
}

echo 'ردُّ الإقحامِ الذي ابتلعه الترحيل' . ($dry ? '  [تشغيلٌ جافّ]' : '') . "\n";
echo str_repeat('═', 54), "\n";
echo "ملفاتٌ عولجت: {$files}\n";
echo "مواضعُ رُدّت: {$fixed}\n";
echo "تراجعٌ: {$revert}  ·  مقفلٌ: {$locked}\n\n";
foreach ($log as $l) { echo '  · ' . $l . "\n"; }
if (!$dry) { echo "\nالنسخُ الاحتياطية: " . substr($BACKUP, strlen($ROOT) + 1) . "\n"; }
exit(0);
