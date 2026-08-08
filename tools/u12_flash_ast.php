<?php
/**
 * tools/u12_flash_ast.php — UI-DEF-06: نزعُ الرسالةِ من الرابطِ بقراءةِ الرموز
 * ═══════════════════════════════════════════════════════════════════════════
 * لماذا أداةٌ ثانية: المُرحِّلُ الأولُ (u12_p4_flash_migrate) يطابق بتعبيرٍ نمطيٍّ
 * فيمسك الأشكالَ البسيطةَ ويعجز عن المركَّبةِ الحيةِ في الحوزة:
 *     header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode($ok ? '…' : '…'));
 *     header("Location: X?msg=" . rawurlencode($msg) . ($c > 0 ? '&contract=' . $c : ''));
 * وهذه لا يحكمها تعبيرٌ نمطيٌّ بأمان. فالأداةُ هنا تقرأ الملفَّ رموزًا
 * (token_get_all) وتفكّك نداءَ header إلى أجزاءِ وصلٍ في المستوى الأعلى، ثم:
 *   · ما قبلَ «?msg=» وجهةٌ · وما بعدَه رسالةٌ · وما بقيَ لواحقُ رابطٍ.
 *   · اللاحقةُ تعود للوجهةِ عبر ems_flash_to (تقلب «&» أولَ معاملٍ إلى «?»).
 * فيصير النداءُ: ems_gov_flash_redirect(الوجهة, الرسالة, الرمز, '');
 *
 * الحارساتُ: نسخةٌ احتياطية · فحصُ صياغةٍ بعد كلِّ ملفٍ وتراجعٌ عند الفساد ·
 * ولا يمسُّ نداءً لا يحمل رسالةً · ولا يحذف سطرًا واحدًا.
 *
 * التشغيل: php tools/u12_flash_ast.php [--dry] [--file=مسار]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$dry = in_array('--dry', $argv, true);
$only = null;
foreach ($argv as $a) { if (strpos($a, '--file=') === 0) { $only = substr($a, 7); } }

$BACKUP = $ROOT . '/storage/backups/u12_flashast_' . date('Ymd_His');
if (!$dry) { @mkdir($BACKUP, 0777, true); }

$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');

/** رمزٌ محكومٌ يُشتق من نصِّ الرسالةِ — والمتغيّرُ يأخذ رمزًا عامًّا */
function fa_code($msg)
{
    $m = (string) $msg;
    if ($m === '') { return 'GOV-INFO-200'; }
    if (mb_strpos($m, '✅') !== false || mb_strpos($m, 'تم ') !== false) { return 'GOV-OK-200'; }
    if (mb_strpos($m, 'صلاحي') !== false) { return 'GOV-PERM-403'; }
    if (mb_strpos($m, 'نطاق') !== false) { return 'GOV-SCOPE-403'; }
    if (mb_strpos($m, 'غير موجود') !== false || mb_strpos($m, 'غير صحيح') !== false) { return 'GOV-REF-404'; }
    if (mb_strpos($m, 'تعذّر') !== false || mb_strpos($m, 'تعذر') !== false
        || mb_strpos($m, '❌') !== false) { return 'GOV-FAIL-409'; }
    return 'GOV-INFO-200';
}

/** نصُّ الرمزِ كما هو في المصدر */
function fa_text($t) { return is_array($t) ? $t[1] : $t; }

/**
 * يفكّك قائمةَ رموزٍ إلى أجزاءِ وصلٍ في المستوى الأعلى (نقطةُ الوصلِ خارجَ أيِّ
 * قوسٍ أو قوسٍ مربّعٍ أو نصٍّ). يُرجع مصفوفةَ نصوصٍ مقلَّمةً، أو null إن تعذّر.
 */
function fa_split_concat(array $toks)
{
    $parts = array(); $cur = ''; $depth = 0;
    foreach ($toks as $t) {
        $s = fa_text($t);
        if (!is_array($t)) {
            if ($s === '(' || $s === '[' || $s === '{') { $depth++; }
            elseif ($s === ')' || $s === ']' || $s === '}') { $depth--; }
            elseif ($s === '"' || $s === '`') { return null; }  // نصٌّ مُقحَمٌ — يُترك ليدٍ بشرية
            elseif ($s === '.' && $depth === 0) { $parts[] = trim($cur); $cur = ''; continue; }
            elseif ($s === '?' && $depth === 0) { return null; } // شرطيٌّ بلا أقواسٍ — يُترك
        } elseif ($t[0] === T_START_HEREDOC) {
            return null;
        }
        $cur .= $s;
    }
    $parts[] = trim($cur);
    return $parts;
}

/** أهو نصٌّ حرفيٌّ مفردُ الاقتباس؟ يُرجع محتواه أو null */
function fa_literal($expr)
{
    $e = trim($expr);
    if (strlen($e) < 2) { return null; }
    $q = $e[0];
    if (($q !== "'" && $q !== '"') || substr($e, -1) !== $q) { return null; }
    $inner = substr($e, 1, -1);
    /* اقتباسٌ داخليٌّ غيرُ مهروبٍ ⇒ ليس نصًّا واحدًا */
    if (preg_match('~(?<!\\\\)' . preg_quote($q, '~') . '~', $inner)) { return null; }
    return $q === "'"
        ? str_replace(array("\\'", '\\\\'), array("'", '\\'), $inner)
        : $inner;
}

/** يُغلّف نصًّا في اقتباسٍ مفرد */
function fa_quote($s) { return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $s) . "'"; }

$php = PHP_BINARY;
$stat = array('files' => 0, 'calls' => 0, 'reverted' => 0, 'left' => 0);
$leftDetail = array();

$files = array();
if ($only !== null) {
    $files[] = (strpos($only, ':') === false ? $ROOT . '/' . $only : $only);
} else {
    foreach ($dirs as $d) { foreach (glob($ROOT . '/' . $d . '/*.php') as $f) { $files[] = $f; } }
}

foreach ($files as $f) {
    $src = (string) @file_get_contents($f);
    if ($src === '') { continue; }
    $rel = str_replace('\\', '/', substr($f, strlen($ROOT) + 1));
    if (strpos($src, 'insidebar') === false) { continue; }
    if (strpos($src, '?msg=') === false) { continue; }

    $orig = $src;
    $toks = @token_get_all($src);
    if (!$toks) { continue; }

    /* موازينُ الإزاحةِ: نصُّ كلِّ رمزٍ متتابعٌ فالمواضعُ تُبنى بالتراكم */
    $offs = array(); $p = 0;
    foreach ($toks as $i => $t) { $offs[$i] = $p; $p += strlen(fa_text($t)); }

    $edits = array();   // [بداية, نهاية, بديل]
    $nLeft = 0;

    for ($i = 0, $n = count($toks); $i < $n; $i++) {
        $t = $toks[$i];
        if (!is_array($t) || $t[0] !== T_STRING || strtolower($t[1]) !== 'header') { continue; }
        /* استبعادُ ->header و::header و function header */
        $prev = $i - 1;
        while ($prev >= 0 && is_array($toks[$prev]) && in_array($toks[$prev][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) { $prev--; }
        if ($prev >= 0) {
            $pt = $toks[$prev];
            if (is_array($pt) && in_array($pt[0], array(T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION), true)) { continue; }
            if (!is_array($pt) && $pt === '$') { continue; }
        }
        $j = $i + 1;
        while ($j < $n && is_array($toks[$j]) && $toks[$j][0] === T_WHITESPACE) { $j++; }
        if ($j >= $n || fa_text($toks[$j]) !== '(') { continue; }

        /* القوسُ المُطابِق */
        $depth = 0; $k = $j; $close = -1;
        for (; $k < $n; $k++) {
            $s = fa_text($toks[$k]);
            if (!is_array($toks[$k])) {
                if ($s === '(') { $depth++; }
                elseif ($s === ')') { $depth--; if ($depth === 0) { $close = $k; break; } }
            }
        }
        if ($close < 0) { continue; }

        $argToks = array_slice($toks, $j + 1, $close - $j - 1);
        $argSrc = '';
        foreach ($argToks as $at) { $argSrc .= fa_text($at); }
        if (strpos($argSrc, '?msg=') === false) { continue; }

        /* الفاصلةُ المنقوطةُ بعد الإغلاق */
        $m2 = $close + 1;
        while ($m2 < $n && is_array($toks[$m2]) && $toks[$m2][0] === T_WHITESPACE) { $m2++; }
        if ($m2 >= $n || fa_text($toks[$m2]) !== ';') { $nLeft++; continue; }

        $parts = fa_split_concat($argToks);
        if ($parts === null) { $nLeft++; continue; }

        /* الجزءُ الحاملُ لـ «?msg=» */
        $hit = -1;
        foreach ($parts as $pi => $pv) { if (strpos($pv, '?msg=') !== false) { $hit = $pi; break; } }
        if ($hit < 0) { $nLeft++; continue; }
        $lit = fa_literal($parts[$hit]);
        if ($lit === null || strpos($lit, '?msg=') === false) { $nLeft++; continue; }

        list($left, $right) = explode('?msg=', $lit, 2);
        $left = preg_replace('~^\s*Location:\s*~i', '', $left);

        /* الوجهةُ = أجزاءُ ما قبلَ + النصفُ الأيسر (بعد نزعِ «Location:») */
        $tgt = array();
        for ($x = 0; $x < $hit; $x++) {
            $pv = $parts[$x];
            $pl = fa_literal($pv);
            if ($pl !== null) { $pl = preg_replace('~^\s*Location:\s*~i', '', $pl); $tgt[] = fa_quote($pl); }
            else { $tgt[] = $pv; }
        }
        if ($left !== '' || !$tgt) { $tgt[] = fa_quote($left); }
        $target = implode(' . ', $tgt);

        /* الرسالةُ واللواحق */
        $msgExpr = null; $extras = array(); $codeHint = '';
        if ($right !== '') {
            $msgExpr = fa_quote(urldecode(str_replace('+', ' ', $right)));
            $codeHint = urldecode(str_replace('+', ' ', $right));
            for ($x = $hit + 1; $x < count($parts); $x++) { $extras[] = $parts[$x]; }
        } else {
            $next = isset($parts[$hit + 1]) ? $parts[$hit + 1] : null;
            if ($next === null) { $nLeft++; continue; }
            /* urlencode(X) / rawurlencode(X) ⇒ X — والرسالةُ نصٌّ لا رابط */
            if (preg_match('~^(?:raw)?urlencode\s*\((.*)\)$~su', $next, $mm)) {
                $msgExpr = trim($mm[1]);
                $l2 = fa_literal($msgExpr);
                if ($l2 !== null) { $codeHint = $l2; }
                else { $codeHint = $msgExpr; }
            } else {
                $nLeft++; continue;
            }
            for ($x = $hit + 2; $x < count($parts); $x++) { $extras[] = $parts[$x]; }
        }

        /* اللواحقُ تعود للوجهةِ نظيفةً */
        if ($extras) {
            $ex = implode(' . ', $extras);
            $exLit = fa_literal($ex);
            if ($exLit !== null && $exLit === '') { /* لاحقةٌ فارغة */ }
            else { $target = 'ems_flash_to(' . $target . ', ' . $ex . ')'; }
        }

        $code = fa_code($codeHint);
        $repl = 'ems_gov_flash_redirect(' . $target . ', ' . $msgExpr . ', ' . fa_quote($code) . ", '');";
        $edits[] = array($offs[$i], $offs[$m2] + 1, $repl);
    }

    if (!$edits) {
        if ($nLeft > 0) { $stat['left']++; $leftDetail[] = $rel . " — {$nLeft} نداءً لم يُفكَّك"; }
        continue;
    }

    /* التطبيقُ من الآخرِ إلى الأولِ حتى لا تنزاحَ المواضع */
    for ($e = count($edits) - 1; $e >= 0; $e--) {
        list($s0, $s1, $r) = $edits[$e];
        $src = substr($src, 0, $s0) . $r . substr($src, $s1);
    }
    if (strpos($src, 'permissions_helper.php') === false) {
        $src = preg_replace('~(<\?php\s*\n)~', "$1require_once __DIR__ . '/../includes/permissions_helper.php';\n", $src, 1);
    }

    if ($dry) { $stat['files']++; $stat['calls'] += count($edits); continue; }
    if (!is_writable($f)) { $stat['left']++; $leftDetail[] = $rel . ' — ملفٌّ غيرُ قابلٍ للكتابة'; continue; }

    @copy($f, $BACKUP . '/' . str_replace(array('/', '\\'), '__', $rel));
    if (@file_put_contents($f, $src) === false) {
        $stat['left']++; $leftDetail[] = $rel . ' — تعذّرت الكتابة'; continue;
    }
    $lint = array(); $rc = 0;
    exec('"' . $php . '" -l ' . escapeshellarg($f) . ' 2>&1', $lint, $rc);
    if ($rc !== 0) {
        file_put_contents($f, $orig);
        $stat['reverted']++;
        $leftDetail[] = $rel . ' — تراجعٌ: ' . trim(implode(' ', $lint));
        continue;
    }
    $stat['files']++;
    $stat['calls'] += count($edits);
    if ($nLeft > 0) { $leftDetail[] = $rel . " — بقيَ {$nLeft} نداءً لم يُفكَّك"; }
}

echo 'UI-DEF-06 — تفكيكُ نداءاتِ header بقراءةِ الرموز' . ($dry ? '  [تشغيلٌ جافّ]' : '') . "\n";
echo str_repeat('═', 62), "\n";
echo "ملفاتٌ عولجت: {$stat['files']}\n";
echo "نداءاتٌ حُوّلت: {$stat['calls']}\n";
echo "تراجعٌ لفسادِ صياغة: {$stat['reverted']}\n";
echo "ملفاتٌ بقيت: {$stat['left']}\n";
if ($leftDetail) {
    echo "\nالتفصيل:\n";
    foreach (array_slice($leftDetail, 0, 30) as $s) { echo '  · ' . $s . "\n"; }
    if (count($leftDetail) > 30) { echo '  … و' . (count($leftDetail) - 30) . " أخرى\n"; }
}
if (!$dry) { echo "\nالنسخُ الاحتياطية: " . substr($BACKUP, strlen($ROOT) + 1) . "\n"; }
exit(0);
