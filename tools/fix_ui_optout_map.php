<?php
/**
 * tools/fix_ui_optout_map.php — خارطةُ الخروجِ من عُدَّةِ الجداولِ المركزية
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ حملةُ عيوبِ الواجهةِ الثمانيةِ والستين (2026-08-13)
 *
 * ── السؤالُ الذي تجيبه ──────────────────────────────────────────────────────
 * `assets/js/ui-unification.js` يُهيِّئ **كلَّ** جدولٍ في الصفحةِ إلا ما خرج
 * صراحةً بـ`no-datatable` أو `data-no-dt` (السطر 322-324). والعيوبُ الغالبةُ
 * في السجلِّ («بلا بحثٍ ولا فرزٍ ولا ترقيم») هي **أثرُ ذلك الخروج**.
 *
 * والخروجُ **ليس كلُّه خطأً**: جدولٌ يُهيَّأ يدويًّا بـ`.DataTable({…})` **يجب**
 * أن يخرج، وإلا تصادمت تهيئتان على جدولٍ واحد. فالتمييزُ لازمٌ قبل أيِّ إصلاح:
 *   · **خروجٌ + تهيئةٌ يدويةٌ في الملفِّ نفسِه** ⇒ مشروعٌ، لا يُمَسّ.
 *   · **خروجٌ بلا تهيئةٍ يدوية**              ⇒ جدولٌ جامدٌ فعلًا — وهو المرشَّح.
 *
 * ── والمسحُ يُقصي النسخَ والفروع ────────────────────────────────────────────
 * `.claude/worktrees/` و`storage/backups/` و`vendor/` — وإلا خضرةٌ كاذبة.
 * (وقد وقعتُ في عكسِها: مسحٌ بمسارٍ خاطئٍ أعطى **صفرًا** في 7,116 ملفًّا بينما
 *  الباحثُ المباشرُ يجد 102 و105. فالصفرُ يُشَكُّ فيه كما يُشَكُّ في الكثرة.)
 *
 * التشغيل: php tools/fix_ui_optout_map.php [--out=<tsv>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$OUT = 'docs/fix_progress/ui_optout_map.tsv';
foreach ($argv as $a) { if (strpos($a, '--out=') === 0) { $OUT = substr($a, 6); } }

$skip = '~/(\.claude|storage/backups|vendor|node_modules|\.git|tests|tools|database|docs)/~';
$files = array();
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = str_replace('\\', '/', $f->getPathname());
    if (substr($p, -4) !== '.php') { continue; }
    if (preg_match($skip, $p)) { continue; }
    $files[] = substr($p, strlen($ROOT) + 1);
}
sort($files);

$rows = array();
$stat = array('optout' => 0, 'manual' => 0, 'legit' => 0, 'frozen' => 0, 'tables' => 0);
foreach ($files as $rel) {
    $s = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($s === '') { continue; }
    $nTables = preg_match_all('~<table\b~i', $s);
    if ($nTables > 0) { $stat['tables'] += $nTables; }
    $a = preg_match_all('~data-no-dt~', $s);
    $b = preg_match_all('~\bno-datatable\b~', $s);
    if ($a + $b === 0) { continue; }
    $stat['optout']++;
    /* تهيئةٌ يدويةٌ في الملفِّ نفسِه — الشرطُ الذي يجعل الخروجَ مشروعًا */
    $manual = preg_match('~\.DataTable\s*\(\s*\{~', $s) ? 1 : 0;
    if ($manual) { $stat['manual']++; $stat['legit']++; }
    else { $stat['frozen']++; }
    $rows[] = array($rel, $a, $b, $manual ? 'manual' : 'frozen', $nTables);
}

echo "══ خارطةُ الخروجِ من عُدَّةِ الجداول\n\n";
echo "  ملفاتٌ حيّةٌ مسحًا           : " . count($files) . "\n";
echo "  عناصرُ <table> فيها          : " . $stat['tables'] . "\n";
echo "  ملفاتٌ تخرج صراحةً           : " . $stat['optout'] . "\n";
echo "  ── منها بتهيئةٍ يدويةٍ (مشروع): " . $stat['legit'] . "\n";
echo "  ── **بلا تهيئةٍ يدوية (جامد)** : " . $stat['frozen'] . "\n\n";

$path = $ROOT . '/' . ltrim($OUT, '/');
@mkdir(dirname($path), 0777, true);
$o = "file\tdata_no_dt\tno_datatable\tkind\ttables\n";
foreach ($rows as $r) { $o .= implode("\t", $r) . "\n"; }
file_put_contents($path, $o);
echo "  · كُتبت الخارطة: {$OUT} (" . count($rows) . " صفًّا)\n";

$frozen = array();
foreach ($rows as $r) { if ($r[3] === 'frozen') { $frozen[] = $r[0]; } }
echo "\n── عيّنةٌ من الجامد (" . count($frozen) . "):\n";
foreach (array_slice($frozen, 0, 14) as $x) { echo "     {$x}\n"; }
