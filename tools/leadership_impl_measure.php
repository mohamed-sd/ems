<?php
/**
 * tools/leadership_impl_measure.php — هل نُفِّذ ملفُّ القيادةِ التنفيذيّة؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الهدفُ من الورقةِ والمبنيُّ من النظامِ سجلّان لا واحد**: المصمَّمُ يُقرأ من
 *   `docs/REPAIR01_20260823/02 · القيادة-3.xlsx` (ورقة 99) حرفًا، والمبنيُّ من
 *   `repair01_screen_registry` والقرصِ و`gov_field_class` — ⛔ **ولا يُنقَل رقمٌ
 *   من ورقةِ مراجعةٍ سابقة**: رقمُ ٢٦٫٧٪ في «12 · مراجعة القيادة» قياسٌ على
 *   لقطةِ BL-20260823 وقد بُني بعدَها كثير.
 *
 * ◆ **والحقلُ يُعلَن بثلاثِ آليّاتٍ لا واحدة** — فقارئٌ واحدٌ يُخرِج أصفارًا كاذبة:
 *   ① `$GUIDE_COLS` خريطةُ رأسٍ ومصدرِ خليّة · ② `$U13` مولَّدٌ وأعمدتُه من
 *   `gov_field_class` (‏الحقلُ بلا صنفٍ لا يُصيَّر — OBL-0052) · ③ `<th>` يدويّ.
 *   وكلُّ سطرٍ يُعلن **أيُّ قارئٍ أطلقه**، فالصفرُ يُنسَب ولا يُترك مبهمًا.
 *
 * ◆ **والمصفوفةُ محورٌ ثالثٌ مستقلٌّ عن الشاشات**: سبعَ عشرةَ قاعدةً وسياستا
 *   التجزئةِ والصرفِ ومفردتا Waivability وRESERVED_AUTHORITY — تُقاس بوجودِ
 *   مخزنِ قواعدَ بحقولِ القاعدةِ لا بوجودِ شاشةٍ تعرضها.
 *
 * التشغيل: php tools/leadership_impl_measure.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/vendor/autoload.php';

$XLSX = $ROOT . '/docs/REPAIR01_20260823/02 · القيادة-3.xlsx';
if (!is_file($XLSX)) { exit("⛔ ملفُّ التصميمِ غيرُ موجود: $XLSX\n"); }

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit('تعذّر الاتصال: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');

function lm_norm($s)
{
    $s = str_replace(array('ـ', "\xE2\x80\x8F", "\xE2\x80\x8E"), '', (string) $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

/* ═══ ① الهدف — يُقرأ من الورقة حرفًا ═══════════════════════════════════ */
$rd = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($XLSX);
$rd->setReadDataOnly(true);
$ws = $rd->load($XLSX)->getSheet(3);
$target = array();
for ($r = 5; $r <= 42; $r++) {
    $space = lm_norm($ws->getCell([1, $r])->getValue());
    $name  = lm_norm($ws->getCell([3, $r])->getValue());
    if ($space === '' || $name === '') { continue; }
    $target[] = array(
        'space'  => $space,
        'order'  => (int) $ws->getCell([2, $r])->getValue(),
        'name'   => $name,
        'group'  => lm_norm($ws->getCell([4, $r])->getValue()),
        'fields' => (int) $ws->getCell([6, $r])->getValue(),
    );
}
printf("◆ الهدفُ من الورقة: %d سطحًا · %d حقلًا مصمَّمًا\n",
       count($target), array_sum(array_column($target, 'fields')));

/* ═══ ② المبنيّ — السجلُّ الحاكمُ ثم القرص ═══════════════════════════════ */
$built = array();
$rs = $db->query("SELECT screen_id, screen_file, route, canonical_label_ar, lifecycle, on_disk
                    FROM repair01_screen_registry WHERE canonical_label_ar <> ''");
while ($x = $rs->fetch_assoc()) { $built[lm_norm($x['canonical_label_ar'])][] = $x; }
printf("◆ المبنيُّ المفتَّشُ: %d تسميةً في `repair01_screen_registry` · وقرصُ `Portal/`\n\n", count($built));

/* قارئُ الحقولِ الثلاثيّ — والصفرُ يُنسَب إلى قارئه */
function lm_count_fields($path, $db)
{
    if (!is_file($path)) { return array(0, 'لا ملف'); }
    $src = file_get_contents($path);

    /* ① $GUIDE_COLS — 'الرأس' => 'gNN' */
    if (preg_match('/\$GUIDE_COLS\s*=\s*array\s*\((.*?)\n\s*\);/s', $src, $m)) {
        $n = preg_match_all("/=>\s*'g\d+'/", $m[1]);
        if ($n > 0) { return array($n, 'GUIDE_COLS'); }
    }
    /* ② $U13 مولَّد — الأعمدةُ من gov_field_class */
    if (strpos($src, 'u13_screen_kit') !== false
        && preg_match("/'screen'\s*=>\s*'([^']+)'/", $src, $m)) {
        $sc = $db->real_escape_string($m[1]);
        $q = $db->query("SELECT COUNT(*) FROM gov_field_class WHERE screen_code='$sc' AND active=1");
        return array($q ? (int) $q->fetch_row()[0] : 0, 'gov_field_class:' . $m[1]);
    }
    /* ③ <th> يدويّ */
    $n = preg_match_all('/<th\b/i', $src);
    if ($n > 0) { return array($n, '<th>'); }
    return array(0, 'لا قارئ أطلق');
}

$rows = array(); $surf = 0; $fldBuilt = 0; $fldTarget = 0;
foreach ($target as $t) {
    $key = lm_norm($t['name']);
    $hit = null;
    if (isset($built[$key])) {
        foreach ($built[$key] as $c) {           /* المبنيُّ على القرصِ يسبق الشبح */
            if ((int) $c['on_disk'] === 1) { $hit = $c; break; }
        }
        if ($hit === null) { $hit = $built[$key][0]; }
    }
    $path = ''; $fn = 0; $reader = '—';
    /* ⛔ لا يُوقَف الفحصُ عند أوّلِ مرشَّح: تسميةٌ واحدةٌ قد يحملها سجلّان
       (‏«قرارات الهيكل التنظيمي» في `admin/org_structure.php` و
       `Portal/ceo_org_decisions.php`) — فيُجرَّب كلُّ مرشَّحٍ حتى يَنفُذ مسار. */
    foreach (($built[$key] ?? array()) as $c) {
        foreach (array($c['route'], 'Portal/' . $c['screen_file'], $c['screen_file']) as $cand) {
            if ($cand !== '' && $cand !== null && is_file($ROOT . '/' . $cand)) {
                $path = $ROOT . '/' . $cand;
                $hit = $c;
                break 2;
            }
        }
    }
    if ($path !== '') { list($fn, $reader) = lm_count_fields($path, $db); }
    if ($path !== '') { $surf++; }
    $fldBuilt += $fn; $fldTarget += $t['fields'];
    $rows[] = array(
        'space' => (mb_strpos($t['space'], 'نواب') !== false ? 'نائب' : 'رئيس'),
        'order' => $t['order'], 'name' => $t['name'],
        'want' => $t['fields'], 'got' => $fn, 'reader' => $reader,
        'file' => $hit ? $hit['screen_file'] : '—',
        'life' => $hit ? $hit['lifecycle'] : 'لا سجلّ',
        'on'   => ($path !== ''),
    );
}

printf("%-6s %-3s %-42s %-7s %-7s %-24s %s\n", 'مساحة', '#', 'السطح', 'مصمَّم', 'مبنيّ', 'القارئ', 'الملف');
echo str_repeat('-', 132) . "\n";
foreach ($rows as $r) {
    printf("%-6s %-3d %-42s %-7d %-7s %-24s %s%s\n",
        $r['space'], $r['order'], mb_substr($r['name'], 0, 40),
        $r['want'], $r['on'] ? $r['got'] : '—', $r['reader'], $r['file'],
        $r['on'] ? '' : '  ⛔ لا ملف');
}
echo str_repeat('-', 132) . "\n";
printf("◆ الأسطح: %d من %d مبنيّةٌ على القرص (%.1f%%)\n", $surf, count($target), 100 * $surf / count($target));
printf("◆ الحقول: %d مبنيًّا مقابل %d مصمَّمًا (%.1f%%)\n", $fldBuilt, $fldTarget, 100 * $fldBuilt / max(1, $fldTarget));

$short = array();
foreach ($rows as $r) { if ($r['on'] && $r['got'] < $r['want']) { $short[] = $r; } }
printf("◆ أسطحٌ حقولُها دونَ المصمَّم: %d\n", count($short));

/* ⛔ «غيرُ مبنيٍّ» بعد فحصِ سجلٍّ واحدٍ حكمٌ سابقٌ لأوانه — يُفتَّش الاسمُ في
   الشجرةِ كلِّها (شيفرةً وملاحةً) قبل الحكم، ويُعلَن ما فُتِّش. */
$absent = array();
foreach ($rows as $r) { if (!$r['on']) { $absent[] = $r['name']; } }
if ($absent) {
    echo "\n◆ شاهدُ الغياب — يُفتَّش اسمُ السطحِ في شجرةِ الشيفرةِ وفي جدولِ الملاحة:\n";
    $names = lm_scan($ROOT, $absent);
    foreach ($absent as $nm) {
        $inCode = count($names[$nm]);
        $esc = $db->real_escape_string($nm);
        $q = $db->query("SELECT COUNT(*) FROM modules WHERE name = '$esc'");
        $inNav = $q ? (int) $q->fetch_row()[0] : -1;
        printf("   %-44s شيفرة: %d%s · `modules`: %d\n", mb_substr($nm, 0, 42), $inCode,
            $inCode ? ' (' . implode(' · ', array_slice($names[$nm], 0, 2)) . ')' : '', $inNav);
    }
}

/* ═══ ③ المصفوفة — محورٌ مستقلّ ═══════════════════════════════════════════ */
echo "\n◆ محورُ مصفوفةِ السلطة (AAM) — يُقاس بمخزنِ القواعدِ لا بشاشةٍ تعرضها:\n";
$probes = array(
    'جدولٌ باسمِ AAM'                  => "SELECT COUNT(*) FROM information_schema.TABLES
                                             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE '%aam%'",
    'سقوفُ الإدارات `exec_dept_caps`'  => 'SELECT COUNT(*) FROM exec_dept_caps',
    'صفوفُ `fin_approval_matrix`'      => 'SELECT COUNT(*) FROM fin_approval_matrix',
);
foreach ($probes as $label => $sql) {
    $q = @$db->query($sql);
    printf("   %-40s %s\n", $label, $q ? (int) $q->fetch_row()[0] : 'تعذّر القياس');
}

/* أعمدةُ القاعدةِ في الورقة — كم منها له عمودٌ في أقربِ مخزنٍ قائم؟ */
$need = array(
    'event_type' => 'نوع المعاملة', 'min_amount' => 'الحد الأدنى', 'max_amount' => 'الحد الأعلى',
    'required_level' => 'المستوى المطلوب', 'waivability' => 'Waivability', 'joint_approval' => 'اعتماد مشترك',
    'reviewer' => 'المراجع', 'approver' => 'المعتمد', 'window_from' => 'نافذة من', 'window_to' => 'حتى',
    'priority' => 'أولوية التعارض', 'during_delegation' => 'تسري أثناء الإنابة', 'fx_basis' => 'أساس التحويل',
);
$have = array();
$rs = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_approval_matrix'");
while ($x = $rs->fetch_row()) { $have[$x[0]] = 1; }
$ok = 0; $miss = array();
foreach ($need as $col => $ar) { if (isset($have[$col])) { $ok++; } else { $miss[] = $ar; } }
printf("   %-40s %d من %d\n", 'أعمدةُ القاعدةِ في `fin_approval_matrix`', $ok, count($need));
printf("   %-40s %s\n", 'الناقصُ منها', implode(' · ', $miss));

/* المفرداتُ الحاكمةُ — قيمٌ منفَّذةٌ أم أسماءُ حقولٍ معروضة؟ */
echo "\n◆ مفرداتُ الملفِّ الحاكمةُ — أهي قيمٌ منفَّذةٌ أم أسماءُ حقولٍ معروضة؟\n";
/* ⛔ ولا `shell_exec('grep')`: صدفةُ ويندوز لا تعرفه فيرجع فارغًا — وفراغُ
   القارئِ يُقرأ «صفرَ نتيجةٍ» فيصير أخضرَ كاذبًا. فالمسحُ بـPHP ذاتِها. */
function lm_scan($root, array $toks)
{
    $hit = array_fill_keys($toks, array());
    $skip = array('docs', 'storage', 'vendor', 'node_modules', '.git', 'logs', 'database');
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            function ($f, $k, $i) use ($skip) {
                return !($f->isDir() && in_array($f->getFilename(), $skip, true));
            }
        )
    );
    foreach ($it as $f) {
        if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
        /* ⛔ ولا يعُدُّ الكاشفُ مفرداتِ نفسِه: ملفُّ القياسِ يحمل المفرداتِ
           ليبحثَ عنها، فعَدُّه يجعل «١ ملفًّا» إيجابًا كاذبًا. */
        if (realpath($f->getPathname()) === realpath(__FILE__)) { continue; }
        $src = file_get_contents($f->getPathname());
        foreach ($toks as $t) {
            if (strpos($src, $t) !== false) {
                $hit[$t][] = str_replace($root . DIRECTORY_SEPARATOR, '', $f->getPathname());
            }
        }
    }
    return $hit;
}
$toks = array('WAIVABLE', 'NON_WAIVABLE', 'RESERVED_AUTHORITY', 'AAM-SPLIT', 'Aggregation_Key', 'Waivability');
$found = lm_scan($ROOT, $toks);
foreach ($toks as $t) {
    $n = count($found[$t]);
    printf("   %-20s %d ملفًّا%s\n", $t, $n,
        $n ? '  ← ' . implode(' · ', array_slice($found[$t], 0, 3)) : '');
}

/* ═══ ④ المدى — أتُحمل الأسطحُ بيانًا أم قوالبَ فارغة؟ ═══════════════════ */
echo "\n◆ محورُ الامتلاء — جداولُ الأسطحِ التنفيذيةِ وعددُ صفوفِها:\n";
$rs = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'exec\\_%' ORDER BY TABLE_NAME");
$empty = 0; $filled = 0; $emptyList = array();
while ($x = $rs->fetch_row()) {
    $q = @$db->query('SELECT COUNT(*) FROM `' . $x[0] . '`');
    $n = $q ? (int) $q->fetch_row()[0] : -1;
    if ($n === 0) { $empty++; $emptyList[] = $x[0]; } elseif ($n > 0) { $filled++; }
}
printf("   جداولُ `exec_*`: %d فيها صفوف · %d فارغةٌ تمامًا\n", $filled, $empty);
if ($emptyList) { echo '   الفارغة: ' . implode(' · ', $emptyList) . "\n"; }
