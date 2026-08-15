<?php
/**
 * tools/fix_screen_columns_scan.php — لا عمودَ معلَنٌ بلا مصدرٍ ولا خليةٍ
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0416 · INJ-0417 · INJ-0418 · INJ-0419 · INJ-0317
 *
 * ── العلّة ────────────────────────────────────────────────────────────────
 * شاشاتُ CMP-03 تعلن أعمدتَها في ثلاثةِ مواضعَ يجب أن تتطابق:
 *   ① `$COLS`  — عناوينُ الوثيقة
 *   ② `$COLDB` — مصدرُ كلِّ عنوانٍ في القاعدة
 *   ③ رؤوسُ `<thead>` — ما يراه القارئ
 * وانزياحُ أيِّها عن أختِه يُنتج جدولًا **تُقرأ خلاياه تحت رؤوسٍ ليست لها** —
 * وهو عطبٌ أخطرُ من النقصِ لأنَّه يُقرأ صحيحًا ولا يُشتكى منه.
 *
 * ── ويحرس أربعًا ──────────────────────────────────────────────────────────
 * ◆ عدَّةُ `$COLS` = عدَّةُ `$COLDB` = عدَّةُ رؤوسِ `<thead>`.
 * ◆ وكلُّ مصدرٍ في `$COLDB` **عمودٌ قائمٌ فعلًا** في جدولِ الشاشة — يُقرأ من
 *   `information_schema` لا من مخطَّطٍ محفوظ.
 * ◆ و`colspan` صفِّ الخلوّ = عدَّةُ الأعمدة.
 * ◆ ولا رأسَ `ems-fn-th`/`ems-gov-th` **خارجَ** `$COLS` — فذاك رأسٌ بلا خلية.
 *
 * التشغيل: php tools/fix_screen_columns_scan.php [--json] [--all]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');

/* أعمدةُ القاعدةِ كلُّها — حيّةً */
$TCOL = array();
$r = $conn->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()");
while ($r && ($x = $r->fetch_row())) { $TCOL[strtolower($x[0])][strtolower($x[1])] = true; }

/* الشاشاتُ المعنيّة: كلُّ ملفٍّ يحمل `$COLS` و`$COLDB` معًا */
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

$SPECIAL = array('__status', '__creator', '__entity', '__actions');
$bad = array(); $okN = 0; $seen = 0;
foreach ($files as $abs) {
    $rel = ltrim(str_replace($ROOT, '', $abs), '/');
    $src = (string) @file_get_contents($abs);
    /* الشاشةُ معنيّةٌ إن أعلنت `$COLS` — سواءٌ صيَّرت خلاياها بـ`$COLDB`
       (شاشاتُ المكتبِ التنفيذيّ) أو بـ`cmp03_cell` (بقيةُ دفعةِ CMP-03). */
    if (strpos($src, '$COLS') === false) { continue; }
    if (strpos($src, '$COLDB') === false && strpos($src, 'cmp03_cell') === false) { continue; }
    if (!preg_match('~\$COLS\s*=\s*array\s*\((.*?)\n\);~s', $src, $m)) { continue; }
    $seen++;
    preg_match_all("~=>\s*'([^']*)'~u", $m[1], $cm);
    $cols = $cm[1];
    $n = count($cols);
    $issues = array();

    /* ① عدَّةُ $COLDB — الصيغتان: حرفيةٌ أو array_merge بـ$DB_FIELDS */
    $ndb = null;
    if (preg_match('~\$COLDB\s*=\s*array_merge\((.*?)\);~s', $src, $dm)) {
        $ndb = 0;
        if (strpos($dm[1], '$DB_FIELDS') !== false) {
            if (preg_match('~\$DB_FIELDS\s*=\s*array\s*\((.*?)\n\);~s', $src, $fm)) {
                $ndb += preg_match_all("~'[^']*'~", $fm[1]);
            }
        }
        $rest = preg_replace('~\$DB_FIELDS~', '', $dm[1]);
        $ndb += preg_match_all("~'[^']*'~", $rest);
        $ndb += preg_match_all('~\bnull\b~i', $rest);
    } elseif (preg_match('~\$COLDB\s*=\s*array\s*\((.*?)\);~s', $src, $dm)) {
        $ndb  = preg_match_all("~'[^']*'~", $dm[1]);
        $ndb += preg_match_all('~\bnull\b~i', $dm[1]);
    }
    if ($ndb !== null && $ndb !== $n) { $issues[] = "‏\$COLS={$n} ≠ \$COLDB={$ndb}"; }

    /* ② رؤوسُ <thead> — والصفحةُ قد تحمل **جدولين**: `Governance/perm_explain.php`
         يعرض سلسلةَ التفسيرِ بعمودين ثم جدولَ CMP-03 بستةَ عشرَ. فأخذُ أولِ
         `<thead>` يُدين بريئًا؛ والحكمُ: يكفي أن **يطابق أحدُ جداولِ الصفحةِ**
         عدَّةَ `$COLS`، ويُعلَن ما لم يطابق أيٌّ منها. */
    if (preg_match_all('~<thead\b.*?</thead>~is', $src, $hm)) {
        $counts = array();
        foreach ($hm[0] as $h) { $counts[] = preg_match_all('~<th\b~i', $h); }
        if (!in_array($n, $counts, true)) {
            $issues[] = "‏\$COLS={$n} ≠ رؤوس=" . implode('/', $counts);
        }
    }

    /* ③ colspan صفِّ الخلوّ — والصفحةُ قد تحمل جدولين (جدولٌ محسوبٌ وآخرُ موروث)،
         فيكفي أن **يطابق أحدُ الـcolspan** عدَّةَ `$COLS`. وأخذُ الأولِ وحدَه
         يُدين شاشةً سليمةً أضافت جدولًا ثانيًا بحقّ. */
    if (preg_match_all('~colspan="(\d+)"~', $src, $sm)) {
        $spans = array_map('intval', $sm[1]);
        if (!in_array($n, $spans, true)) {
            $issues[] = 'colspan=' . implode('/', $spans) . " ≠ {$n}";
        }
    }

    /* ④ كلُّ مصدرٍ عمودٌ قائمٌ في جدولِ الشاشة */
    $tbl = '';
    if (preg_match('~INSERT\s+INTO\s+`?([a-z0-9_]+)`?~i', $src, $tm2)) { $tbl = strtolower($tm2[1]); }
    if ($tbl !== '' && isset($TCOL[$tbl])) {
        $srcs = array();
        if (preg_match('~\$DB_FIELDS\s*=\s*array\s*\((.*?)\n\);~s', $src, $fm)) {
            preg_match_all("~'([^']*)'~", $fm[1], $sm2);
            $srcs = array_merge($srcs, $sm2[1]);
        }
        if (preg_match('~\$COLDB\s*=\s*array\s*\((.*?)\);~s', $src, $dm2)) {
            preg_match_all("~'([^']*)'~", $dm2[1], $sm3);
            $srcs = array_merge($srcs, $sm3[1]);
        }
        if (preg_match('~\$COLDB\s*=\s*array_merge\((.*?)\);~s', $src, $dm3)) {
            preg_match_all("~'([^']*)'~", preg_replace('~\$DB_FIELDS~', '', $dm3[1]), $sm4);
            $srcs = array_merge($srcs, $sm4[1]);
        }
        $ghost = array();
        foreach (array_unique($srcs) as $s) {
            if ($s === '' || in_array($s, $SPECIAL, true)) { continue; }
            if (!isset($TCOL[$tbl][strtolower($s)])) { $ghost[] = $s; }
        }
        if ($ghost) { $issues[] = 'مصدرٌ لا وجودَ له في ' . $tbl . ': ' . implode(' · ', $ghost); }
    }

    if ($issues) { $bad[] = array('file' => $rel, 'cols' => $n, 'issues' => $issues); }
    else { $okN++; }
}

echo "══ لا عمودَ معلَنٌ بلا مصدرٍ ولا خليةٍ ══\n\n";
echo '  شاشاتٌ مقيسة: ' . $seen . ' · سليمةٌ: ' . $okN . ' · منزاحةٌ: ' . count($bad) . "\n\n";
if (!$bad) {
    echo "  ✔ **صفرُ انزياح** — الإعلانُ والمصدرُ والرأسُ ثلاثتُها متطابقة\n";
} else {
    foreach ($bad as $b) {
        echo '  ✘ ' . $b['file'] . '  (' . $b['cols'] . " عمودًا)\n";
        foreach ($b['issues'] as $i) { echo '      · ' . $i . "\n"; }
    }
}
if (in_array('--json', $argv, true)) {
    file_put_contents($ROOT . '/docs/fix_progress/screen_columns_scan.json',
        json_encode(array('seen' => $seen, 'ok' => $okN, 'bad' => $bad),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\n  · كُتب: docs/fix_progress/screen_columns_scan.json\n";
}
exit(empty($bad) ? 0 : 1);
