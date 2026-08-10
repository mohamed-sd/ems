<?php
/**
 * tools/fix_cs12_declare.php — إلزامُ كلِّ كتلةِ catch بالإفصاح (CS-12 · AC-F10)
 * ═══════════════════════════════════════════════════════════════════════════
 * الجولةُ الثانيةُ بعد `fix_cs12_apply.php`. تلك عالجت الشكلَ الآمنَ الواحد
 * (إسنادُ سقّاطةٍ بسيطة)؛ وهذه تُلزم **ما بقي** بالإفصاح دون مسِّ مسارِ التحكُّم.
 *
 * ◆ لا يُغيَّر سطرٌ واحدٌ من المنطق: يُدرَج نداءٌ واحدٌ في أولِ الكتلة. السلوكُ
 *   قبلُ وبعدُ متطابق، والمكسبُ أن القرارَ صار مُعلَنًا ومقروءًا في السجل.
 *
 * ◆ اختيارُ الدالةِ **بشهادةِ الكود لا بالتخمين**:
 *     • جسمٌ فيه إسنادُ سقّاطةٍ → `ems_catch_log` (الأثرُ يُفحص بعدها).
 *     • جسمٌ تعليقٌ محضٌ أو لا أثرَ له → `ems_catch_ignored` **والسببُ يُؤخذ
 *       من تعليقِ المبرمجِ نفسِه** إن وُجد؛ فهو أعرفُ بنيّته من أيِّ توليد.
 *     • بلا تعليقٍ ولا سقّاطة → `ems_catch_ignored` بسببٍ يقول الحقيقةَ:
 *       «بلا سببٍ معلَن — يحتاج مراجعة». ولا يُلفَّق تبرير: بقاءُ الوسمِ
 *       ظاهرًا في السجلِّ هو ما يجعل الدَّينَ مرئيًّا بدل أن يُدفن تحت عبارةٍ
 *       مطمئنةٍ كتبتها آلة.
 *
 * التشغيل: php tools/fix_cs12_declare.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
require_once $ROOT . '/tools/fix_lib.php';
require_once $ROOT . '/tools/fix_checks.php';

$scope = array('app/Services/', 'app/Core/', 'includes/');
$stamp = 'cs12d_' . gmdate('Ymd_His');
$backupDir = $ROOT . '/storage/backups/' . $stamp;

/** هل الكتلةُ ممتثلةٌ سلفًا؟ نسخةٌ من منطقِ الفاحصِ — مصدرٌ واحدٌ للحكم. */
function cs12_compliant(array $c)
{
    $b = trim($c['body']);
    if ($b === '') { return false; }
    if (preg_match('/\b(throw|return|exit|die|http_response_code)\b/i', $b)) { return true; }
    if (preg_match('/\brollback\b/i', $b)) { return true; }
    if (preg_match('/\bems_catch_(log|ignored)\s*\(/', $b)) {
        if (preg_match('/\bems_catch_ignored\s*\(/', $b)) { return true; }
        if (preg_match_all('/\$(\w+)\s*=/', $b, $vm)) {
            $tail = (string) ($c['after'] ?? '');
            foreach (array_unique($vm[1]) as $v) {
                if (in_array($v, array('e', 't', 'ex'), true)) { continue; }
                if (preg_match('/\$' . preg_quote($v, '/') . '\b[\s\S]{0,160}?\b(return|throw)\b/', $tail)) { return true; }
            }
        }
        return false;
    }
    $touchesExc = (bool) preg_match('/\$(e|t|ex|err|exc|throwable)\b/i', $b);
    return $touchesExc && (bool) preg_match(
        '/\b(last_error|attempts|is_dead|failed_at|->exec\s*\(|->query\s*\(|->prepare\s*\(|->update\s*\(|->insert\s*\()/i', $b);
}

$files = 0; $blocks = 0; $skipped = 0; $byKind = array('log' => 0, 'ignored' => 0);

foreach (fix_php_files($ROOT) as $rel) {
    $in = false;
    foreach ($scope as $d) { if (strpos($rel, $d) === 0) { $in = true; break; } }
    if (!$in || $rel === 'includes/catch_log.php') { continue; }

    $abs = $ROOT . '/' . $rel;
    $src = (string) @file_get_contents($abs);
    if ($src === '' || strpos($src, 'catch') === false) { continue; }

    $fileBlocks = 0;
    $new = preg_replace_callback(
        '/catch\s*\(([^)]*?)\$(\w+)\s*\)\s*\{/u',
        function ($m) use ($src, &$fileBlocks, &$byKind) {
            $types = $m[1]; $var = $m[2];
            // موضعُ هذه الكتلةِ بعينها في المصدرِ لاستخراج جسمها
            static $seenAt = 0;
            $pos = strpos($src, $m[0], $seenAt);
            if ($pos === false) { return $m[0]; }
            $seenAt = $pos + 1;

            // استخراجُ الجسمِ بموازنةِ الأقواس
            $i = $pos + strlen($m[0]); $depth = 1; $body = ''; $len = strlen($src);
            for (; $i < $len && $depth > 0; $i++) {
                $ch = $src[$i];
                if ($ch === '{') { $depth++; }
                elseif ($ch === '}') { $depth--; if ($depth === 0) { break; } }
                $body .= $ch;
            }
            $after = substr($src, $i + 1, 220);
            if (cs12_compliant(array('body' => $body, 'after' => $after))) { return $m[0]; }

            $trim = trim($body);
            $hasSentinel = (bool) preg_match('/\$\w+\s*=/', $trim);
            // تعليقُ المبرمجِ سببًا — هو أعرفُ بنيّتِه من أيِّ توليد
            $why = '';
            if (preg_match('~^\s*(?://\s*(.+)$|/\*+\s*(.+?)\s*\*/)~mu', $trim, $cm)) {
                $why = trim(($cm[1] ?? '') !== '' ? $cm[1] : ($cm[2] ?? ''));
            }

            $fileBlocks++;
            if ($hasSentinel) {
                $byKind['log']++;
                $call = 'ems_catch_log($' . $var . ', __METHOD__);';
            } else {
                $byKind['ignored']++;
                $q = $why !== '' ? str_replace("'", "\\'", mb_substr($why, 0, 120)) : '';
                $call = 'ems_catch_ignored($' . $var . ', __METHOD__, \'' . $q . '\');';
            }
            return 'catch (' . $types . '$' . $var . ') { ' . $call;
        },
        $src
    );

    if ($new === null || $fileBlocks === 0) { continue; }

    if (strpos($new, 'catch_log.php') === false) {
        $needle = "<?php\n";
        $pos = strpos($new, $needle);
        if ($pos === false) { echo "⚠ لا وسمَ فتحٍ في {$rel}\n"; $skipped++; continue; }
        $up = str_repeat('/..', substr_count($rel, '/'));
        $new = substr($new, 0, $pos + strlen($needle))
             . "require_once __DIR__ . '" . $up . "/includes/catch_log.php';\n"
             . substr($new, $pos + strlen($needle));
    }

    try { token_get_all($new, TOKEN_PARSE); }
    catch (\ParseError $e) { echo "✘ {$rel} — يكسر التركيب، يُترك: " . $e->getMessage() . "\n"; $skipped++; continue; }

    if ($apply) {
        $bdir = $backupDir . '/' . dirname($rel);
        if (!is_dir($bdir)) { @mkdir($bdir, 0777, true); }
        @copy($abs, $backupDir . '/' . $rel);
        file_put_contents($abs, $new);
    }
    $files++; $blocks += $fileBlocks;
}

echo "\n" . str_repeat('═', 70) . "\n";
printf("%s: %d ملفًّا · %d كتلة (تسجيلٌ %d · تجاهلٌ مُعلَنٌ %d) · متروكٌ %d\n",
       $apply ? 'طُبِّق' : 'سيُطبَّق', $files, $blocks, $byKind['log'], $byKind['ignored'], $skipped);
if ($apply) { echo "النسخُ الاحتياطيّ: storage/backups/{$stamp}\n"; }
echo str_repeat('═', 70) . "\n";
