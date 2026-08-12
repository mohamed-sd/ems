<?php
/**
 * tools/session_time_report.php — تقريرُ زمنِ جلسةٍ في جدولٍ واحدٍ (+PDF)
 * ═══════════════════════════════════════════════════════════════════════════
 * يقرأ سجلَّ جلسةِ Claude Code (‎.jsonl‎) ويُخرج جدولًا زمنيًّا واحدًا: كلُّ رسالةٍ
 * برقمِها وتاريخِها وساعتِها والفاصلِ عن سابقتها — بلا ذكرِ محتواها. الغرضُ
 * قياسُ **كم من الزمنِ عملنا** لا ماذا عملنا.
 *
 * ◆ زمنُ العملِ يُقاس بالكتلِ من **كلِّ قيدٍ** في السجل لا من رسالةٍ إلى رسالة:
 *   القياسُ بالرسائلِ يُسقط ما جرى من عملٍ بعد آخرِ رسالةٍ في كلِّ كتلة — وهو
 *   غالبًا أطولُ ما فيها. (قِيس الفرقُ مرةً: 11 س 58 د مقابل 17 س 59 د.)
 * ◆ والحدُّ الفاصلُ بين «عملٍ متصل» و«انقطاع» مُعلَنٌ في الحاشيةِ لا مخفيّ.
 *
 * التشغيل:
 *   php tools/session_time_report.php --list
 *   php tools/session_time_report.php <session-id|path.jsonl> [خيارات]
 * الخيارات:
 *   --title="عنوان الغلاف"     افتراضيًّا: تقرير زمن الجلسة
 *   --out=<مسار-بلا-امتداد>    افتراضيًّا: docs/fix_2026-08/<العنوان>
 *   --tz=Africa/Khartoum        المنطقةُ الزمنية
 *   --cut=7200                  الحدُّ الفاصلُ بالثواني (افتراضيًّا ساعتان)
 *   --no-pdf                    يكتفي بـmd وhtml
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

$PROJ_DIR = getenv('USERPROFILE')
    ? getenv('USERPROFILE') . '/.claude/projects/C--wamp64-www-ems'
    : getenv('HOME') . '/.claude/projects/C--wamp64-www-ems';

$opt = array('title' => 'تقرير زمن الجلسة', 'out' => null,
             'tz' => 'Africa/Khartoum', 'cut' => 7200, 'pdf' => true);
$target = null;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--list') { $opt['list'] = true; }
    elseif ($a === '--no-pdf') { $opt['pdf'] = false; }
    elseif (strpos($a, '--title=') === 0) { $opt['title'] = substr($a, 8); }
    elseif (strpos($a, '--out=') === 0)   { $opt['out'] = substr($a, 6); }
    elseif (strpos($a, '--tz=') === 0)    { $opt['tz'] = substr($a, 5); }
    elseif (strpos($a, '--cut=') === 0)   { $opt['cut'] = (int) substr($a, 6); }
    elseif ($target === null) { $target = $a; }
}

/* ── سردُ الجلساتِ المتاحةِ بأحدثِها ─────────────────────────────────────── */
if (!empty($opt['list']) || $target === null) {
    $files = glob($PROJ_DIR . '/*.jsonl');
    if (!$files) { exit("لا سجلاتِ جلساتٍ في {$PROJ_DIR}\n"); }
    usort($files, function ($a, $b) { return filemtime($b) - filemtime($a); });
    echo "جلساتُ هذا المشروع (الأحدثُ أولًا):\n";
    foreach (array_slice($files, 0, 15) as $f) {
        printf("  %s  %8.1f م.ب  %s\n", date('Y-m-d H:i', filemtime($f)),
            filesize($f) / 1048576, basename($f, '.jsonl'));
    }
    echo "\nالتشغيل: php tools/session_time_report.php <session-id>\n";
    exit(0);
}

$path = is_file($target) ? $target : $PROJ_DIR . '/' . $target . '.jsonl';
if (!is_file($path)) { exit("لا سجلَّ بهذا المعرّف: {$target}\n"); }

$TZ = new DateTimeZone($opt['tz']);
$CUT = max(60, $opt['cut']);

/* ── القراءة: كلُّ القيود (للزمن) ورسائلُ المستخدمِ الحقيقيةُ (للجدول) ──── */
$allTs = array(); $msgs = array();
$fh = fopen($path, 'r');
while (($line = fgets($fh)) !== false) {
    $j = json_decode($line, true);
    if (!is_array($j) || empty($j['timestamp'])) { continue; }
    $allTs[] = strtotime($j['timestamp']);
    if (($j['type'] ?? '') !== 'user') { continue; }
    if (!empty($j['isMeta']) || !empty($j['isCompactSummary'])) { continue; }
    $txt = '';
    $c = $j['message']['content'] ?? null;
    if (is_string($c)) { $txt = $c; }
    elseif (is_array($c)) {
        foreach ($c as $p) { if (is_array($p) && ($p['type'] ?? '') === 'text') { $txt .= $p['text']; } }
    }
    $txt = trim($txt);
    /* تُستبعد نتائجُ الأدواتِ والتذكيراتُ وإشعاراتُ المهام — ليست رسائلَ بشر */
    if ($txt === '' || strpos($txt, '<system-reminder>') === 0
        || strpos($txt, '<task-notification>') === 0 || strpos($txt, 'Caveat:') === 0) { continue; }
    $msgs[] = (new DateTime($j['timestamp']))->setTimezone($TZ);
}
fclose($fh);
if (!$msgs) { exit("لا رسائلَ في السجل\n"); }
sort($allTs);

$AR_DAY = array('Sun' => 'الأحد', 'Mon' => 'الاثنين', 'Tue' => 'الثلاثاء', 'Wed' => 'الأربعاء',
                'Thu' => 'الخميس', 'Fri' => 'الجمعة', 'Sat' => 'السبت');
function fmt($s)
{
    if ($s === null) { return '—'; }
    if ($s < 60)   { return $s . ' ث'; }
    if ($s < 3600) { return intdiv($s, 60) . ' د'; }
    return intdiv($s, 3600) . ' س ' . intdiv($s % 3600, 60) . ' د';
}

/* ── الجدولُ الزمنيُّ الواحد ──────────────────────────────────────────────── */
$md  = "\n## الجدولُ الزمنيُّ لجلسةِ العمل\n\n";
$md .= "| # | اليوم | التاريخ | الساعة | الفاصلُ عن السابقة | الحالة |\n";
$md .= "|---|---|---|---|---|---|\n";
$prev = null;
foreach ($msgs as $i => $t) {
    $gap = ($prev === null) ? null : ($t->getTimestamp() - $prev);
    if ($gap === null)   { $state = 'بدايةُ الجلسة'; }
    elseif ($gap > $CUT) { $state = 'استئنافٌ بعد انقطاع'; }
    else                 { $state = 'عملٌ متصل'; }
    $md .= sprintf("| رسالة %d | %s | %s | %s | %s | %s |\n",
        $i + 1, $AR_DAY[$t->format('D')], $t->format('Y-m-d'), $t->format('H:i'), fmt($gap), $state);
    $prev = $t->getTimestamp();
}

/* ── الحصيلة: العملُ بالكتلِ من كلِّ قيد ─────────────────────────────────── */
$work = 0; $idle = 0; $blocks = 1; $bs = $allTs[0]; $be = $allTs[0];
foreach ($allTs as $ts) {
    if (($ts - $be) > $CUT) { $work += $be - $bs; $idle += $ts - $be; $blocks++; $bs = $ts; }
    $be = $ts;
}
$work += $be - $bs;
$span = $allTs[count($allTs) - 1] - $allTs[0];
$first = $msgs[0]; $last = $msgs[count($msgs) - 1];

$md .= "\n\n## الحصيلة\n\n| البند | القيمة |\n|---|---|\n";
$md .= '| أولُ رسالة | ' . $AR_DAY[$first->format('D')] . ' ' . $first->format('Y-m-d')
     . ' — الساعة ' . $first->format('H:i') . " |\n";
$md .= '| آخرُ رسالة | ' . $AR_DAY[$last->format('D')] . ' ' . $last->format('Y-m-d')
     . ' — الساعة ' . $last->format('H:i') . " |\n";
$md .= '| عددُ الرسائل | ' . count($msgs) . " |\n";
$md .= '| عددُ جلساتِ العمل | ' . $blocks . " |\n";
$md .= '| **زمنُ العملِ الفعليّ** | **' . fmt($work) . "** |\n";
$md .= '| زمنُ الانقطاع | ' . fmt($idle) . " |\n";
$md .= '| المدى الكليّ | ' . fmt($span) . " |\n";
$md .= "\n> التوقيتُ بمنطقةِ " . $opt['tz'] . '. «زمنُ العملِ الفعليّ» يُقاس بكتلِ النشاطِ من كلِّ قيدٍ في السجل؛ '
     . 'وما تجاوز فاصلُه ' . fmt($CUT) . " عُدَّ انقطاعًا.\n";

/* ── الإخراج ─────────────────────────────────────────────────────────────── */
$base = $opt['out'] !== null ? $opt['out'] : ($ROOT . '/docs/fix_2026-08/' . $opt['title']);
$dir = dirname($base);
if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
file_put_contents($base . '.md', $md);
echo 'كُتب: ' . $base . ".md\n";

$conv = $ROOT . '/scripts/md_to_pdf_html.php';
if (is_file($conv)) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($conv) . ' '
         . escapeshellarg($base . '.md') . ' ' . escapeshellarg($base . '.html') . ' '
         . escapeshellarg($opt['title']) . ' ' . escapeshellarg('الجدولُ الزمنيُّ لجلسةِ العمل');
    @exec($cmd . ' 2>&1', $o, $rc);
    echo ($rc === 0 ? 'كُتب: ' : 'تعذّر: ') . $base . ".html\n";
}

if ($opt['pdf'] && is_file($base . '.html')) {
    $chromes = array(
        'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
        'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
    );
    foreach ($chromes as $ch) {
        if (!is_file($ch)) { continue; }
        $abs = str_replace('\\', '/', realpath($base . '.html'));
        @exec(escapeshellarg($ch) . ' --headless --disable-gpu --no-pdf-header-footer'
            . ' --print-to-pdf=' . escapeshellarg(str_replace('\\', '/', $base) . '.pdf')
            . ' ' . escapeshellarg('file:///' . $abs) . ' 2>&1', $po, $prc);
        break;
    }
    echo (is_file($base . '.pdf') ? 'كُتب: ' : 'تعذّر: ') . $base . ".pdf\n";
}
exit(0);
