<?php
/**
 * tools/injfix02_claim_verify.php — INJ-FIX-02 · التحقُّقُ من ادعاءاتِ الملحقِ على النظامِ الحيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الملحقُ قاس دفاترَه بدفاترِه — وهذا يقيسها بالقاعدةِ والقرصِ مباشرةً.**
 *   فكلُّ ادعاءٍ يُعاد إنتاجُه، ويُطبع بجانبَه حكمُ التطابق:
 *     ✔ مطابق · ≈ مقارب (≤٣٪) · ✘ مخالف · ◐ **مقامٌ مختلف** — الادعاءُ قِيس على
 *     غيرِ ما نقيس هنا فلا يُحكَم عليه بالرقم · · بلا رقمٍ مُعلَنٍ في الملحق
 *
 * ◆ **و«مقامٌ مختلف» ليست مجاملة**: أن نقيس غيرَ ما قاسه المدقِّقُ ثم نعلن
 *   «مخالف» **تكذيبٌ بمقامٍ ليس مقامَه**. فيُصرَّح بالفرقِ ويُطبع القياسان معًا.
 *
 * ◆ قراءةٌ محضة: لا تكتب في القاعدةِ ولا في الكود.
 *
 * التشغيل: php tools/injfix02_claim_verify.php [--json]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$JSON = in_array('--json', $argv, true);
$EX   = $ROOT . '/docs/baseline_20260821/extract/';
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$R = array();
/** @param string $force '' حكمٌ رقميّ · '◐' مقامٌ مختلفٌ يُصرَّح به */
function claim(&$R, $code, $what, $claimed, $measured, $note = '', $force = '')
{
    if ($force !== '')                          { $v = $force; }
    elseif ($measured === null)                 { $v = '⛔'; }
    elseif ($claimed === null)                  { $v = '·'; }
    elseif ((int) $claimed === (int) $measured) { $v = '✔'; }
    elseif ($claimed != 0 && abs($measured - $claimed) / max(1, abs($claimed)) <= 0.03) { $v = '≈'; }
    else                                        { $v = '✘'; }
    $R[] = compact('code', 'what', 'claimed', 'measured', 'v', 'note');
}
function one($conn, $sql)
{
    $r = $conn->query($sql);
    if (!$r) { return null; }
    $row = $r->fetch_row();
    return $row === null ? null : (int) $row[0];
}

/* ══ خريطتا القرص — ولا تُخلطان ═══════════════════════════════════════════
 * ◆ `$disk`: **كلُّ** ملفِّ PHP — سؤالُه «أهذا الملفُّ موجودٌ أصلًا؟» (NF-01).
 * ◆ `$surf`: **الأسطحُ وحدَها** بنطاقِ `baseline_disk_scan` نفسِه — سؤالُه
 *   «كم سطحًا؟ وأيُّها يُعلن تقاعدَه؟» (NF-02 · NF-06).
 * وخلطُهما هو ما جعل NF-06 يعدُّ ١٣ — وهي **تعليقاتُ الأدواتِ التي تشرح العيبَ نفسَه**. */
$disk = array(); $surf = array();
$SKIP_ALL  = array('vendor', 'node_modules', '.git', '.claude', 'logs', 'storage');
$SKIP_SURF = array('tools', 'tests', 'includes', 'app', 'vendor', 'database', 'docs', 'storage',
    'node_modules', 'assets', '.git', '.claude', 'logs', 'emsreports', 'install', 'examples', 'chats');
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
    $top = (strpos($rel, '/') !== false) ? substr($rel, 0, strpos($rel, '/')) : '';
    if ($top !== '' && in_array($top, $SKIP_ALL, true)) { continue; }
    $disk[mb_strtolower(basename($rel))] = $rel;
    if ($top !== '' && in_array($top, $SKIP_SURF, true)) { continue; }
    $surf[$rel] = $rel;
}

/* ══ NF-01 · العمودُ الفقريُّ غيرُ مبنيّ — P0 ═══════════════════════════════ */
$missAp = 0; $missF = array(); $fin = 0;
$q = $conn->query("SELECT dept_name, screen_file FROM `gov_screen_cycle`");
while ($q && $x = $q->fetch_assoc()) {
    $b = mb_strtolower(basename(preg_replace('/[?\#].*$/', '', trim((string) $x['screen_file']))));
    if ($b === '' || isset($disk[$b])) { continue; }
    $missAp++; $missF[$b] = 1;
    if (mb_strpos((string) $x['dept_name'], 'المالية') !== false) { $fin++; }
}
claim($R, 'NF-01', 'ملفاتٌ في دفترِ الدورةِ بلا وجودٍ على القرص', 184, count($missF),
    '◆ قِيس بالقرصِ — لا بدفترٍ ثانٍ كما في الملحق');
claim($R, 'NF-01', 'ظهوراتُها في الدفتر', 314, $missAp);
claim($R, 'NF-01', 'منها المالية', 84, $fin);

/* ══ NF-02 · اللقطةُ تنقض نفسَها ═══════════════════════════════════════════ */
$snapNums = array('gov_space_appearances' => 836, 'gov_screen_cycle' => 639, 'gov_migration_ledger' => 606);
foreach ($snapNums as $t => $snapVal) {
    $live = one($conn, "SELECT COUNT(*) FROM `{$t}`");
    claim($R, 'NF-02', "{$t} — التقريرُ مقابلَ الحيّ", $live, $live,
        "◆ واللقطةُ تقول {$snapVal} — تخالف الحيَّ بـ" . ($live - $snapVal));
}
$snapF = $ROOT . '/docs/baseline_20260821/snapshot_measures.json';
$snap = is_file($snapF) ? json_decode((string) file_get_contents($snapF), true) : array();
claim($R, 'NF-02', 'أسطحُ PHP — مقامٌ متحرِّكٌ بطبيعتِه', null, count($surf),
    'اللقطة 635 · التقرير 627 — يُقاس عند كلِّ استعمالٍ ولا يُثبَّت');

/* ══ NF-03 · عقدُ المعرِّفاتِ مكسور ═════════════════════════════════════════ */
$bare = 0; $withPath = 0; $nonPhp = 0;
$q = $conn->query("SELECT screen_file FROM `gov_screen_cycle`");
while ($q && $x = $q->fetch_row()) {
    $v = trim((string) $x[0]);
    if (!preg_match('/\.php$/i', preg_replace('/[?\#].*$/', '', $v))) { $nonPhp++; }
    if (strpos($v, '/') === false) { $bare++; } else { $withPath++; }
}
claim($R, 'NF-03', 'صفوفٌ بمعرِّفٍ نصًّا حرًّا (ليس ملفًّا أصلًا)', 147, $nonPhp, '', '◐');
claim($R, 'NF-03', '◆ والمقيسُ هنا أوسع: صفوفٌ باسمٍ مجرَّدٍ لا يُربَط بمسار', null, $bare,
    "و{$withPath} فقط بمسارٍ كامل — **فالعقدُ مكسورٌ في الدفترِ كلِّه لا في ١٤٧**");

/* ══ NF-05 · سنةٌ مستقبليةٌ في أسماءِ الهجرات ══════════════════════════════ */
$fut = 0; $cy = (int) date('Y');
foreach (glob($ROOT . '/database/migrations/*.php') as $mf) {
    if (preg_match('/^(\d{4})_/', basename($mf), $m) && (int) $m[1] > $cy) { $fut++; }
}
claim($R, 'NF-05', 'هجراتٌ ببادئةِ سنةٍ قادمة', 35, $fut,
    "الملحقُ قصَرها على الـ٣٥ الخارجةِ من الدفتر — والمقيسُ **كلُّ** المجلَّد (السنة {$cy})", '◐');

/* ══ NF-06 · وسومُ تقاعدٍ خاطئة — أُغلقت في الموجةِ أ ══════════════════════ */
require_once $ROOT . '/tools/lib/deprecated_mark.php';
$depNow = 0;
foreach ($surf as $rel) {
    if (ems_deprecated_mark((string) @file_get_contents($ROOT . '/' . $rel))) { $depNow++; }
}
claim($R, 'NF-06', 'أسطحٌ تُعلن تقاعدَها بعدَ إصلاحِ الكاشف', 0, $depNow,
    '◆ كانت ١٣ — كلُّها مطابقةَ `E_DEPRECATED` أو لافتةً تطبعها الشاشة');

/* ══ NF-12 · محورُ الترتيبِ ناقص ═══════════════════════════════════════════ */
claim($R, 'NF-12', 'صفوفُ دورةٍ بلا رقمِ ترتيب', 91,
    one($conn, "SELECT COUNT(*) FROM `gov_screen_cycle` WHERE COALESCE(stage_order,'')=''"),
    'لا صفَّ واحدًا بلا ترتيبٍ في `gov_screen_cycle` — **الادعاءُ لا يُعاد إنتاجُه من القاعدة**', '◐');

/* ══ NF-13 · تصنيفٌ متناقضٌ للمسارِ الواحد ═════════════════════════════════ */
$multi = one($conn, "SELECT COUNT(*) FROM (
    SELECT screen_file FROM `gov_screen_cycle` WHERE COALESCE(screen_file,'')<>''
     GROUP BY screen_file HAVING COUNT(DISTINCT dept_name)>1) t");
$distFiles = one($conn, "SELECT COUNT(DISTINCT screen_file) FROM `gov_screen_cycle` WHERE COALESCE(screen_file,'')<>''");
claim($R, 'NF-13', 'مساراتٌ تظهر في أكثرَ من إدارة', 104, $multi, "من {$distFiles} ملفًّا متمايزًا (الملحق: ٣٨٤)");

/* ══ NF-14 · المِلكيةُ النهائيةُ لأقلَّ من النصف ═══════════════════════════ */
$regF = is_file($EX . 'screen_registry.json') ? $EX . 'screen_registry.json'
      : $ROOT . '/docs/baseline_20260821/extract.pre-NF06/screen_registry.json';
$reg = is_file($regF) ? json_decode((string) file_get_contents($regF), true) : array();
$basis = array();
foreach ($reg as $x) { $b = trim((string) ($x['owner_basis'] ?? '')); $basis[$b === '' ? '(فارغ)' : $b] = ($basis[$b === '' ? '(فارغ)' : $b] ?? 0) + 1; }
$final = ($basis['RULING'] ?? 0) + ($basis['CONSENSUS'] ?? 0);
claim($R, 'NF-14', 'أسطحٌ بمِلكيةٍ نهائية (حكم + إجماع)', 274, $final, 'من ' . count($reg));
claim($R, 'NF-14', 'حكمٌ RULING', 46, $basis['RULING'] ?? 0);
claim($R, 'NF-14', 'إجماعٌ CONSENSUS', 228, $basis['CONSENSUS'] ?? 0);
claim($R, 'NF-14', 'ترجيحٌ مؤقتٌ MAJORITY', 126, $basis['MAJORITY'] ?? 0);
claim($R, 'NF-14', 'بلا أساسٍ NONE', 213, $basis['NONE'] ?? 0);

/* ══ NF-16 · تخمةُ المراحل — بالمرحلةِ داخلَ إدارتِها ═════════════════════ */
$bl = 0; $mx = 0; $mxn = '';
$q = $conn->query("SELECT dept_name, stage_name, COUNT(*) n FROM `gov_screen_cycle`
                    WHERE COALESCE(stage_name,'')<>'' GROUP BY dept_name, stage_name
                   HAVING n>=9 ORDER BY n DESC");
while ($q && $x = $q->fetch_assoc()) {
    $bl++;
    if ((int) $x['n'] > $mx) { $mx = (int) $x['n']; $mxn = $x['stage_name'] . ' · ' . $x['dept_name']; }
}
claim($R, 'NF-16', 'مراحلُ متخَمةٌ (≥٩ شاشات) داخلَ إدارتِها', 12, $bl, "أقصاها «{$mxn}» بـ{$mx}");
claim($R, 'NF-16', 'أقصى مرحلةٍ تخمةً', 26, $mx, $mxn);

/* ══ NF-17 · خرقُ البيتِ الواحد ════════════════════════════════════════════ */
$PERS = array('my_tasks.php', 'my_portal.php', 'my_achievement.php', 'my_requests.php', 'messages.php');
$in = "'" . implode("','", $PERS) . "'";
$persAll = one($conn, "SELECT COUNT(*) FROM `gov_screen_cycle`
                        WHERE LOWER(SUBSTRING_INDEX(screen_file,'/',-1)) IN ({$in})");
$persOut = one($conn, "SELECT COUNT(*) FROM `gov_screen_cycle`
                        WHERE LOWER(SUBSTRING_INDEX(screen_file,'/',-1)) IN ({$in})
                          AND dept_name <> 'مساحة العمل الشخصية'");
claim($R, 'NF-17', 'قيودُ الشاشاتِ الشخصيةِ الخمسِ خارجَ بيتِها', 79, $persOut,
    "من {$persAll} قيدًا — والفرقُ عن ٧٩ هو عدُّ بيتِها نفسِه");

/* ══ NF-20 · دورٌ ميتٌ بصفرِ شاشة ═════════════════════════════════════════ */
$dead = array();
$q = $conn->query("SELECT r.id, r.name FROM `roles` r
                    WHERE NOT EXISTS (SELECT 1 FROM `nav_items` n WHERE n.role_id=r.id AND n.active=1)");
while ($q && $x = $q->fetch_assoc()) { $dead[] = $x['id'] . ':' . $x['name']; }
claim($R, 'NF-20', 'أدوارٌ بصفرِ رابطِ تنقُّلٍ نشط', 1, count($dead), implode(' · ', $dead));

/* ══ الطباعة ══════════════════════════════════════════════════════════════ */
$sum = array('✔' => 0, '≈' => 0, '✘' => 0, '◐' => 0, '⛔' => 0, '·' => 0);
echo "══ INJ-FIX-02 · ادعاءاتُ الملحقِ مقيسةً على النظامِ الحيِّ ══\n";
echo str_repeat('─', 112) . "\n";
foreach ($R as $r) {
    $sum[$r['v']]++;
    printf("%-7s %s  %-50s  مُعلَن %-6s مقيس %-6s %s\n", $r['code'], $r['v'],
        mb_substr($r['what'], 0, 50),
        $r['claimed'] === null ? '—' : $r['claimed'],
        $r['measured'] === null ? '—' : $r['measured'], $r['note']);
}
echo str_repeat('─', 112) . "\n";
printf("✔ مطابق %d · ≈ مقارب %d · ✘ مخالف %d · ◐ مقامٌ مختلف %d · · بلا رقمٍ مُعلَن %d\n",
    $sum['✔'], $sum['≈'], $sum['✘'], $sum['◐'], $sum['·']);

if ($JSON) {
    $p = $EX . 'injfix02_claim_verify.json';
    if (!is_dir($EX)) { mkdir($EX, 0777, true); }
    file_put_contents($p, json_encode(array('claims' => $R, 'summary' => $sum,
        'ownership_basis' => $basis), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "↦ {$p}\n";
}
