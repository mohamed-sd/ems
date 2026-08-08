<?php
/**
 * tools/u12_flash_sink.php — تحويلُ الذيلِ إلى المصبِّ الواحد
 * ═══════════════════════════════════════════════════════════════════════════
 * ما بقيَ من نداءاتِ التحويلِ يبني وجهتَه بأشكالٍ لا يحكمها تفكيكٌ آمنٌ: شرطيّةٌ
 * داخلَ النصِّ · نصٌّ مُقحَمٌ ({$m}) · لواحقُ متغيّرة. فبدلَ أن نخمّن التعبيرَ
 * نمرّره كما هو على المصبِّ الواحدِ ems_gov_redirect الذي يفصل msg وقتَ التنفيذ.
 *
 * التبديلُ الوحيدُ: رمزُ `header` ⇐ `ems_gov_redirect` — للنداءاتِ التي يحمل
 * معاملُها «msg=» فقط. لا يُمسُّ حرفٌ من التعبير، فلا مجالَ لفسادِ معنى.
 *
 * التشغيل: php tools/u12_flash_sink.php [--dry]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$dry = in_array('--dry', $argv, true);
$BACKUP = $ROOT . '/storage/backups/u12_sink_' . date('Ymd_His');
if (!$dry) { @mkdir($BACKUP, 0777, true); }

$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');

function sk_text($t) { return is_array($t) ? $t[1] : $t; }

$php = PHP_BINARY;
$stat = array('files' => 0, 'calls' => 0, 'reverted' => 0, 'locked' => 0);
$locked = array();

foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $src = (string) file_get_contents($f);
        $rel = str_replace('\\', '/', substr($f, strlen($ROOT) + 1));
        if (strpos($src, 'insidebar') === false) { continue; }
        if (strpos($src, 'msg=') === false) { continue; }

        $orig = $src;
        $toks = @token_get_all($src);
        if (!$toks) { continue; }
        $offs = array(); $p = 0;
        foreach ($toks as $i => $t) { $offs[$i] = $p; $p += strlen(sk_text($t)); }

        $edits = array();
        for ($i = 0, $n = count($toks); $i < $n; $i++) {
            $t = $toks[$i];
            if (!is_array($t) || $t[0] !== T_STRING || strtolower($t[1]) !== 'header') { continue; }
            $prev = $i - 1;
            while ($prev >= 0 && is_array($toks[$prev])
                && in_array($toks[$prev][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) { $prev--; }
            if ($prev >= 0) {
                $pt = $toks[$prev];
                if (is_array($pt) && in_array($pt[0], array(T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION), true)) { continue; }
                if (!is_array($pt) && $pt === '$') { continue; }
            }
            $j = $i + 1;
            while ($j < $n && is_array($toks[$j]) && $toks[$j][0] === T_WHITESPACE) { $j++; }
            if ($j >= $n || sk_text($toks[$j]) !== '(') { continue; }

            $depth = 0; $close = -1;
            for ($k = $j; $k < $n; $k++) {
                $s = sk_text($toks[$k]);
                if (is_array($toks[$k])) { continue; }
                if ($s === '(') { $depth++; }
                elseif ($s === ')') { $depth--; if ($depth === 0) { $close = $k; break; } }
            }
            if ($close < 0) { continue; }

            $arg = '';
            for ($k = $j + 1; $k < $close; $k++) { $arg .= sk_text($toks[$k]); }
            if (strpos($arg, 'msg=') === false) { continue; }
            /* ترويسةٌ ليست تحويلًا (Content-Type وأخواتُها) لا تُمسّ */
            if (stripos($arg, 'Location:') === false) { continue; }
            /* معاملٌ ثانٍ (replace/http_code) لا يقبله المصبُّ — يُترك */
            $d2 = 0; $hasComma = false;
            for ($k = $j + 1; $k < $close; $k++) {
                $s = sk_text($toks[$k]);
                if (is_array($toks[$k])) { continue; }
                if ($s === '(' || $s === '[') { $d2++; }
                elseif ($s === ')' || $s === ']') { $d2--; }
                elseif ($s === ',' && $d2 === 0) { $hasComma = true; break; }
            }
            if ($hasComma) { continue; }

            $edits[] = array($offs[$i], $offs[$i] + strlen($t[1]), 'ems_gov_redirect');
        }

        if (!$edits) { continue; }
        for ($e = count($edits) - 1; $e >= 0; $e--) {
            list($s0, $s1, $r) = $edits[$e];
            $src = substr($src, 0, $s0) . $r . substr($src, $s1);
        }
        if (strpos($src, 'permissions_helper.php') === false) {
            $src = preg_replace('~(<\?php\s*\n)~', "$1require_once __DIR__ . '/../includes/permissions_helper.php';\n", $src, 1);
        }

        if ($dry) { $stat['files']++; $stat['calls'] += count($edits); continue; }
        if (!is_writable($f)) { $stat['locked']++; $locked[] = $rel; continue; }

        @copy($f, $BACKUP . '/' . str_replace(array('/', '\\'), '__', $rel));
        if (@file_put_contents($f, $src) === false) { $stat['locked']++; $locked[] = $rel; continue; }
        $lint = array(); $rc = 0;
        exec('"' . $php . '" -l ' . escapeshellarg($f) . ' 2>&1', $lint, $rc);
        if ($rc !== 0) { file_put_contents($f, $orig); $stat['reverted']++; continue; }
        $stat['files']++;
        $stat['calls'] += count($edits);
    }
}

echo 'المصبُّ الواحدُ — ems_gov_redirect' . ($dry ? '  [تشغيلٌ جافّ]' : '') . "\n";
echo str_repeat('═', 52), "\n";
echo "ملفاتٌ عولجت: {$stat['files']}\n";
echo "نداءاتٌ حُوّلت: {$stat['calls']}\n";
echo "تراجعٌ لفسادِ صياغة: {$stat['reverted']}\n";
echo "ملفاتٌ مقفلةٌ خارجيًّا: {$stat['locked']}\n";
foreach ($locked as $l) { echo '  · ' . $l . "\n"; }
if (!$dry) { echo "\nالنسخُ الاحتياطية: " . substr($BACKUP, strlen($ROOT) + 1) . "\n"; }
exit(0);
