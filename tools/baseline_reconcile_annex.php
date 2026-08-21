<?php
/**
 * tools/baseline_reconcile_annex.php — INJ-FIX-02 · وضعُ التحقُّقِ من ادعاءاتِ الملحق
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **هذا امتدادُ `baseline_reconcile.php` لا أداةٌ مستقلة**: يستعمل دالتَي
 *   التطبيعِ نفسَيهما ونفسَ مجلَّدِ الاستخراج، ويضيف قياسًا واحدًا لم يكن فيه —
 *   **مطابقةُ دفترِ الدورةِ بالقرصِ لا بدفترٍ آخر**.
 *
 * ◆ **ولماذا القرصُ لا الدفتر**: التدقيقُ في INJ-FIX-02 طابق `gov_screen_cycle`
 *   بأسماءِ السجلِّ الموحَّدِ (٦١٣) فأخرج «١٨٤ ملفًّا بلا وجود». والسجلُّ الموحَّدُ
 *   **نفسُه دفتر** — وفيه عمودُ «على القرص» يشهد أن بعضَ صفوفِه ليست ملفات.
 *   فمطابقةُ دفترٍ بدفترٍ تخلط غيابَ الملفِّ بغيابِ الصفّ. والقرصُ لا يدّعي.
 *
 * ◆ ولا يكتب شيئًا في القاعدةِ ولا في الكود — قراءةٌ محضة.
 *
 * التشغيل: php tools/baseline_reconcile_annex.php [--json]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$D    = $ROOT . '/docs/baseline_20260821/extract/';
$JSON = in_array('--json', $argv, true);

require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$pw = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($h, $u, $pw, ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* ══ خريطةُ القرصِ غيرُ الحساسةِ للحالة — ويندوز ═══════════════════════════ */
$DISK = array();               /* lower(rel) => rel الحقيقي */
$SKIP = array('vendor', 'node_modules', '.git', '.claude', 'logs', 'storage');
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
    $top = (strpos($rel, '/') !== false) ? substr($rel, 0, strpos($rel, '/')) : '';
    if ($top !== '' && in_array($top, $SKIP, true)) { continue; }
    $DISK[mb_strtolower($rel)] = $rel;
}
/* فهرسٌ ثانٍ بالاسمِ المجرَّدِ وحدَه — فصفوفُ الدفترِ كثيرًا ما تحمل الاسمَ بلا مجلَّد */
$BASE = array();
foreach ($DISK as $lk => $rel) {
    $b = mb_strtolower(basename($rel));
    if (!isset($BASE[$b])) { $BASE[$b] = array(); }
    $BASE[$b][] = $rel;
}

function ann_norm($r)
{
    $r = str_replace('\\', '/', trim((string) $r));
    $r = preg_replace('#^(\./|\.\./|/)+#', '', $r);
    $r = preg_replace('/[?\#].*$/', '', $r);          /* ?view= وما بعده ليس ملفًّا */
    return $r;
}

/* ══ ① دفترُ الدورة — gov_screen_cycle ═════════════════════════════════════ */
$rows = array();
$q = $conn->query("SELECT id, dept_name, layer_name, stage_order, stage_name,
                          screen_title, screen_file
                     FROM `gov_screen_cycle`");
while ($q && $x = $q->fetch_assoc()) { $rows[] = $x; }
$TOTAL = count($rows);

/* ══ ② التكييفُ الآليُّ لكلِّ صفّ ══════════════════════════════════════════ */
$stat = array('ON_DISK' => 0, 'ON_DISK_BY_BASENAME' => 0, 'FREE_TEXT_ID' => 0,
              'MISSING' => 0, 'EMPTY' => 0);
$missFiles = array();     /* file => ظهورات */
$missDept  = array();
$freeText  = array();
$ambig     = array();

foreach ($rows as $r) {
    $raw = trim((string) $r['screen_file']);
    if ($raw === '') { $stat['EMPTY']++; continue; }
    $n = ann_norm($raw);

    /* معرِّفٌ نصًّا حرًّا: لا ينتهي بـ.php ولا يشبه مسارًا */
    if (!preg_match('/\.php$/i', $n)) {
        $stat['FREE_TEXT_ID']++;
        $freeText[$n] = ($freeText[$n] ?? 0) + 1;
        continue;
    }
    if (isset($DISK[mb_strtolower($n)])) { $stat['ON_DISK']++; continue; }

    $b = mb_strtolower(basename($n));
    if (isset($BASE[$b])) {
        $stat['ON_DISK_BY_BASENAME']++;
        if (count($BASE[$b]) > 1) { $ambig[$n] = $BASE[$b]; }
        continue;
    }
    $stat['MISSING']++;
    $missFiles[$n] = ($missFiles[$n] ?? 0) + 1;
    $d = $r['dept_name'] ?: '—';
    $missDept[$d] = ($missDept[$d] ?? 0) + 1;
}

/* ══ ③ قياساتٌ أخرى في الدفترِ نفسِه — NF-12 و NF-16 ═══════════════════════ */
$noOrder = 0; $stages = array();
foreach ($rows as $r) {
    if (trim((string) $r['stage_order']) === '') { $noOrder++; }
    $s = trim((string) $r['stage_name']);
    if ($s !== '') { $stages[$s] = ($stages[$s] ?? 0) + 1; }
}
arsort($stages);
$bloated = array();
foreach ($stages as $s => $c) { if ($c >= 9) { $bloated[$s] = $c; } }

/* ══ ④ الإخراج ════════════════════════════════════════════════════════════ */
$out = array(
    'cycle_rows'            => $TOTAL,
    'on_disk_exact'         => $stat['ON_DISK'],
    'on_disk_by_basename'   => $stat['ON_DISK_BY_BASENAME'],
    'free_text_id'          => $stat['FREE_TEXT_ID'],
    'empty_file'            => $stat['EMPTY'],
    'missing_appearances'   => $stat['MISSING'],
    'missing_files'         => count($missFiles),
    'missing_by_dept'       => $missDept,
    'ambiguous_basename'    => count($ambig),
    'no_stage_order'        => $noOrder,
    'stage_count'           => count($stages),
    'bloated_stages'        => $bloated,
);
if ($JSON) {
    $out['missing_list'] = $missFiles;
    $out['free_text_list'] = $freeText;
    if (!is_dir($D)) { mkdir($D, 0777, true); }
    file_put_contents($D . 'annex_cycle_vs_disk.json',
        json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "↦ {$D}annex_cycle_vs_disk.json\n";
}

arsort($missDept);
echo "══ INJ-FIX-02 · دفترُ الدورةِ مقيسًا بالقرصِ لا بدفترٍ آخر ══\n\n";
printf("صفوفُ الدفتر                       : %d\n", $TOTAL);
printf("  ◆ موجودٌ بالمسارِ حرفًا            : %d\n", $stat['ON_DISK']);
printf("  ◆ موجودٌ بالاسمِ المجرَّدِ في مجلَّدٍ آخر: %d  (منها %d ملتبسٌ بأكثرَ من موضع)\n",
    $stat['ON_DISK_BY_BASENAME'], count($ambig));
printf("  ◆ معرِّفٌ نصًّا حرًّا (ليس ملفًّا)     : %d\n", $stat['FREE_TEXT_ID']);
printf("  ◆ خانةٌ فارغة                     : %d\n", $stat['EMPTY']);
printf("  ◆ **غائبٌ عن القرصِ يقينًا**       : %d ظهورًا · %d ملفًّا\n",
    $stat['MISSING'], count($missFiles));
echo "\n── الغائبُ بالإدارة ──\n";
foreach ($missDept as $d => $c) { printf("  %-42s %d\n", $d, $c); }
echo "\n── قياساتٌ أخرى في الدفترِ نفسِه ──\n";
printf("  ظهوراتٌ بلا رقمِ ترتيب (NF-12)     : %d\n", $noOrder);
printf("  عددُ المراحل                      : %d\n", count($stages));
printf("  مراحلُ متخَمةٌ (≥9 شاشات) (NF-16)  : %d — أقصاها %s (%d)\n",
    count($bloated), key($bloated) ?: '—', reset($bloated) ?: 0);
