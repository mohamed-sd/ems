<?php
/**
 * tools/xf_plan.php — مُخطِّطُ تعميمِ «البياناتِ الإضافية» (XF-01) · **قراءةٌ فقط**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ ما يفعله: يمسح شاشةً (أو مجلَّدًا أو النظامَ كلَّه)، ويصنّف كلَّ رأسِ عمودٍ
 *   محقونٍ **بلا وصلٍ بمصدر** إلى BIND · NEW · MANUAL، ثم يكتب:
 *     · خطةً آليةً  `docs/xf_plans/<slug>.json`   ← يقرؤها `xf_apply.php`
 *     · ورقةَ مالكٍ `docs/xf_plans/<slug>.csv`    ← تُفتح بالأكسل وتُراجَع
 *
 * ◆ **ولا يكتب حرفًا في النظام**: لا هجرةَ ولا تعديلَ ملفٍّ ولا سطرًا في القاعدة.
 *   فالخطةُ تُقرأ وتُراجَع، والتنفيذُ أمرٌ ثانٍ منفصلٌ بقرارٍ منفصل.
 *
 * ◆ **والصمتُ ممنوع**: كلُّ عمودٍ يخرج بحكمٍ **وسببِه** — ولا يُسقَط عمودٌ من
 *   العدِّ بلا إعلان. فخطةٌ تقول «12 عمودًا» وقد تخطّت 30 بصمتٍ أسوأُ من لا خطة.
 *
 * التشغيل:
 *   php tools/xf_plan.php --screen=Clients/clients.php
 *   php tools/xf_plan.php --dir=Suppliers/
 *   php tools/xf_plan.php --all
 *   ... [--table=<جدول>]  لتصحيحِ الجدولِ المستنبَطِ لشاشةٍ واحدة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/xf_lib.php';

/* ── الوسائط ─────────────────────────────────────────────────────────────── */
$SCREEN = ''; $DIR = ''; $ALL = false; $TABLE = '';
foreach ($argv as $a) {
    if (strpos($a, '--screen=') === 0) { $SCREEN = substr($a, 9); }
    elseif (strpos($a, '--dir=') === 0) { $DIR = substr($a, 6); }
    elseif (strpos($a, '--table=') === 0) { $TABLE = substr($a, 8); }
    elseif ($a === '--all') { $ALL = true; }
}
if ($SCREEN === '' && $DIR === '' && !$ALL) {
    echo "الاستعمال: php tools/xf_plan.php --screen=<مسار> | --dir=<مجلَّد> | --all [--table=<جدول>]\n";
    exit(1);
}

$conn = xf_db($ROOT);
if (!$conn) { exit("⛔ تعذّر الاتصالُ بالقاعدة — ولا تُصنَّف أعمدةٌ بلا قراءةِ الجداولِ الحيّة\n"); }

/* ── نطاقُ المسح ─────────────────────────────────────────────────────────── */
if ($SCREEN !== '') {
    $rel = ltrim(str_replace(chr(92), '/', $SCREEN), '/');
    $p = $ROOT . '/' . $rel;
    if (!is_file($p)) { exit("⛔ لا ملفَّ: {$rel}\n"); }
    $screens = array($rel => xf_extract_heads($p));
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', preg_replace('/\.php$/', '', $rel)));
} else {
    $screens = xf_scan_repo($ROOT, $DIR);
    $slug = ($DIR !== '') ? strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($DIR, '/'))) : 'all';
}

/* ── التصنيف ─────────────────────────────────────────────────────────────── */
$plan = array('generated_for' => ($SCREEN !== '' ? $SCREEN : ($DIR !== '' ? $DIR : 'ALL')), 'screens' => array());
$tot = array('BIND' => 0, 'NEW' => 0, 'MANUAL' => 0, 'BOUND' => 0);
$noTable = 0; $doneScreens = 0;

foreach ($screens as $rel => $heads) {
    if (!$heads) { $doneScreens++; continue; }   /* مسجَّلةٌ ومُنجَزةٌ — تُعدُّ ولا تُخطَّط */
    /* ◆ **الجدولُ يُقاس من حلقةِ الصفوفِ لا من أكثرِ `FROM` تكرارًا**:
         `xf_guess_table()` كان المستعمَلَ هنا، وهو **مقيسُ الخطأِ في 121 من 160
         شاشةً (75.6%)** بنصِّ رأسِ `xf_resolve_table()` نفسِه — ومن أمثلتِه
         المسمّاةِ هناك: `Employees/employees.php` ⇐ `drivercontracts`.
         والبديلُ الصحيحُ كان **مكتوبًا وغيرَ مُستدعًى**، فبقيت الخطةُ كلُّها
         قائمةً على استنباطٍ خاطئ. ⛔ وخطرُه ليس تجميليًّا: عليه يُنشئ المنفِّذُ
         أعمدةَ `NEW`، فجدولٌ خاطئٌ ⇒ عمودٌ يُزرع في جدولٍ لا علاقةَ له.
       ⛔ **ولا يُرفَع `loop` إلى «مؤكَّد»**: بنصِّ ذيلِ `xf_resolve_table()`
         «الإشارةُ مرجَّحةٌ لا مؤكَّدة … والتأكيدُ فعلُ إنسانٍ لا استنباطُ أداة».
         فيبقى التأكيدُ لِما مرَّره المالكُ بـ`--table=` وحدَه، ويُحجَب إنشاءُ
         `NEW` فيما سواه كما كان. والمُسجَّلُ هنا **درجةُ الثقةِ ودليلُها**
         ليُراجَعا في الورقة، لا ليُفتَح بهما باب. */
    $resolved = array('table' => '', 'confidence' => 'unknown', 'evidence' => '');
    if ($SCREEN !== '' && $TABLE !== '') {
        $resolved = array('table' => $TABLE, 'confidence' => 'owner', 'evidence' => 'مررها المالكُ بـ--table=');
    } elseif (function_exists('xf_resolve_table')) {
        $resolved = xf_resolve_table($ROOT . '/' . $rel);
    } else {
        $resolved['table'] = xf_guess_table($ROOT . '/' . $rel);
        $resolved['confidence'] = $resolved['table'] === '' ? 'unknown' : 'guessed';
    }
    $table = (string) $resolved['table'];
    $cols  = xf_table_columns($conn, $table);
    if ($table === '' || !$cols) { $noTable++; }
    $entry = array('screen' => $rel, 'table' => $table,
                   'table_confirmed' => ($resolved['confidence'] === 'owner'),
                   'table_confidence' => $resolved['confidence'],
                   'table_evidence' => (string) $resolved['evidence'],
                   'table_exists' => (bool) $cols, 'columns' => array());
    $seen = array();
    foreach ($heads as $h) {
        $c = xf_classify($h['label'], $h['gov'], $cols, ($cols ? $table : ''));
        /* ◆ تسميةٌ مكرّرةٌ في الشاشةِ نفسِها ⇒ الثانيةُ MANUAL: عمودانِ بمفتاحٍ
             واحدٍ يكتب أحدُهما فوقَ الآخر، والخطةُ لا تُخفي التصادم. */
        $k = isset($c['key']) ? $c['key'] : '';
        if ($k !== '' && isset($seen[$k])) {
            $c = array('verdict' => 'MANUAL', 'key' => $k,
                       'why' => "تسميتانِ في الشاشةِ تؤولانِ إلى `{$k}` — تصادمُ مفتاحٍ يلزمه قرار");
        } elseif ($k !== '') { $seen[$k] = 1; }

        $entry['columns'][] = array_merge(array('label' => $h['label'], 'gov' => $h['gov']), $c);
        $tot[$c['verdict']]++;
    }
    $plan['screens'][] = $entry;
}

/* ── الكتابة ─────────────────────────────────────────────────────────────── */
$outDir = $ROOT . '/docs/xf_plans';
if (!is_dir($outDir)) { @mkdir($outDir, 0775, true); }
$jsonPath = $outDir . '/' . $slug . '.json';
$csvPath  = $outDir . '/' . $slug . '.csv';
file_put_contents($jsonPath, json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$fh = fopen($csvPath, 'w');
fwrite($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));           /* BOM — الأكسل يقرأ العربيةَ به */
fputcsv($fh, array('الشاشة', 'الجدول', 'تسمية العمود', 'الحكم', 'العمود المقترح', 'النوع', 'السبب', 'قرار المالك'));
foreach ($plan['screens'] as $s) {
    foreach ($s['columns'] as $c) {
        fputcsv($fh, array($s['screen'], $s['table'], $c['label'], $c['verdict'],
                           isset($c['key']) ? $c['key'] : '', isset($c['type']) ? $c['type'] : '',
                           $c['why'], ''));
    }
}
fclose($fh);

/* ── الخلاصةُ المُعلَنة ───────────────────────────────────────────────────── */
$n = array_sum($tot);
echo "════ خطةُ تعميمِ «البياناتِ الإضافية» ════\n";
echo "  النطاق: " . $plan['generated_for'] . " · شاشات تحتاج عملًا: " . count($plan['screens'])
   . ($doneScreens ? " · مُنجَزةٌ سلفًا: {$doneScreens}" : '') . " · أعمدة: {$n}\n";
printf("  BIND   %5d  (وصلٌ بمصدرٍ قائم — **بلا هجرة**)\n", $tot['BIND']);
printf("  NEW    %5d  (عمودٌ اختياريٌّ جديد — هجرة)\n", $tot['NEW']);
printf("  BOUND  %5d  (موصولٌ سلفًا بسياقِ الحوكمة — لا عمل)\n", $tot['BOUND']);
printf("  MANUAL %5d  (قرارُ مالكٍ — لا تخمينَ أداة)\n", $tot['MANUAL']);
if ($noTable) { echo "  ⚠ {$noTable} شاشةً بلا جدولٍ مؤكَّد — استعمل `--screen=… --table=…` لتصحيحِها\n"; }
echo "  الخطة: docs/xf_plans/{$slug}.json\n";
echo "  الورقة: docs/xf_plans/{$slug}.csv\n";
echo "✔ لم يُكتب حرفٌ في النظامِ ولا في القاعدة — التنفيذُ أمرٌ منفصل:\n";
echo "    php tools/xf_apply.php --plan=docs/xf_plans/{$slug}.json          (معاينة)\n";
echo "    php tools/xf_apply.php --plan=docs/xf_plans/{$slug}.json --apply  (تنفيذ)\n";
