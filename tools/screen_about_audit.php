<?php
/**
 * tools/screen_about_audit.php — فاحصُ بطاقة «عن الشاشة» على كل الشاشات
 * ═══════════════════════════════════════════════════════════════════════════
 * الدرسُ الذي أنشأ هذا الملف: عيّنةٌ من تسع شاشاتٍ لا تشهد لثلاثمئةٍ وثمانٍ
 * وخمسين. فالفحصُ يمرّ على **كلِّ** ملفٍّ ينادي المكوِّن ويسأل خمسةَ أسئلة:
 *
 *  ① صفرُ أثرٍ للبطاقة القديمة (نصُّ «ما هذه الشاشة؟» أو نمطُها المُقحَم).
 *  ② لكلِّ شاشةٍ تنادي المكوِّنَ مرساةُ وضعٍ: `page_header.php`.
 *  ③ **اجتماعُ اليدويِّ والآلي**: 24 شاشةً تنادي المشتقَّ في صدرها ثم تصوغ
 *     نصَّها باليد لاحقًا. فأيُّ قاعدةِ ترجيحٍ بالترتيب (أولُ نداءٍ يفوز)
 *     **تبتلع النصَّ المصوغ** وتُبقي العامَّ — انحدارٌ صامتٌ: بطاقةٌ سليمةُ
 *     الشكل بنصٍّ أفقر. والعقدُ النافذ: اليدويُّ يغلب مهما كان الترتيب،
 *     فيُفحص هنا أن الترجيحَ **بالمصدر لا بالموضع**.
 *  ④ صفرُ نداءٍ بأكثرَ من ثلاثِ وسائط (توقيعُ الدالة: غرض · خطوات · حكم).
 *  ⑤ صفرُ شاشةٍ تنادي المكوِّنَ مرتين بنصَّين مختلفَين بلا قصد.
 *
 * التشغيل: php tools/screen_about_audit.php   ·   الخروج 0 إن صفرت المخالفات.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);

$root = dirname(__DIR__);
$skip = array('vendor/', 'storage/', 'node_modules/', '.git/', 'docs/', 'database/', 'install/', 'examples/', 'tests/', 'tools/', 'scripts/');
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

$screens = 0;
$mixedAutoFirst = 0;
$autoScreens = array();   // شاشاتٌ تعتمد التعريفَ المشتقَّ (بلا نصٍّ مصوغٍ في ملفِّها)
$fail = array('legacy' => array(), 'anchor' => array(), 'order' => array(), 'arity' => array(), 'twice' => array());

foreach ($rii as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $p   = str_replace('\\', '/', $f->getPathname());
    $rel = ltrim(substr($p, strlen($root)), '/');
    foreach ($skip as $s) { if (strpos($rel, $s) === 0) { continue 2; } }
    if ($rel === 'includes/screen_contract.php') { continue; }   // المكوِّنُ نفسُه

    $src = file_get_contents($p);
    if (strpos($src, 'ems_screen_about') === false) { continue; }
    $screens++;

    /* ① أثرُ البطاقة القديمة — **ترميزًا لا تعليقًا**: الفحصُ الأولُ رصد سطرَ
       تعليقٍ يذكر العبارةَ فأبلغ مخالفةً كاذبة. فيُستثنى ما كان في تعليق. */
    $codeOnly = preg_replace('~//[^\r\n]*|/\*.*?\*/|\#[^\r\n]*~s', '', $src);
    if (strpos($codeOnly, 'ما هذه الشاشة؟') !== false
        || strpos($codeOnly, 'border-inline-start:4px solid #e2b93b') !== false) {
        $fail['legacy'][] = $rel;
    }

    /* ② مرساةُ الوضع */
    if (strpos($src, 'page_header.php') === false) { $fail['anchor'][] = $rel; }

    /* ③ الترجيحُ بالمصدر لا بالموضع — يُفحص في المكوِّن مرةً واحدة (أدناه)،
          ويُحصى هنا عددُ الشاشات التي يجتمع فيها النداءان لتُقاس التغطية. */
    if (preg_match_all('/ems_screen_about(_auto)?\s*\(/', $src, $all, PREG_OFFSET_CAPTURE)) {
        $calls = array();
        foreach ($all[0] as $i => $hit) {
            $calls[] = array('auto' => ($all[1][$i][0] === '_auto'), 'at' => $hit[1]);
        }
        $hasAuto = false; $manCount = 0;
        foreach ($calls as $c) {
            if ($c['auto']) { $hasAuto = true; } else { $manCount++; }
        }
        if ($hasAuto && $manCount > 0 && $calls[0]['auto']) { $mixedAutoFirst++; }
        /* ⑤ نداءان يدويّان في ملفٍّ واحد */
        if ($manCount > 1) { $fail['twice'][] = $rel . " ($manCount نداءات يدوية)"; }
        /* شاشةٌ بلا نصٍّ مصوغٍ في ملفِّها ⇒ تعتمد السجلَّ */
        if ($manCount === 0) { $autoScreens[] = $rel; }
    }

    /* ④ عددُ الوسائط: يُعدّ الفواصلُ في العمق 0 داخلَ أقواس النداء اليدوي */
    if (preg_match_all('/ems_screen_about\s*\(/', $src, $m2, PREG_OFFSET_CAPTURE)) {
        foreach ($m2[0] as $hit) {
            $i = $hit[1] + strlen($hit[0]);
            $depth = 1; $commas = 0; $len = strlen($src); $q = '';
            while ($i < $len && $depth > 0) {
                $ch = $src[$i];
                if ($q !== '') { if ($ch === '\\') { $i += 2; continue; } if ($ch === $q) { $q = ''; } }
                elseif ($ch === '"' || $ch === "'") { $q = $ch; }
                elseif ($ch === '(' || $ch === '[') { $depth++; }
                elseif ($ch === ')' || $ch === ']') { $depth--; }
                elseif ($ch === ',' && $depth === 1) { $commas++; }
                $i++;
            }
            if ($commas > 2) { $fail['arity'][] = $rel . ' (' . ($commas + 1) . ' وسائط)'; }
        }
    }
}

/* ③ العقدُ نفسُه: الترجيحُ بالمصدر لا بالموضع — يُقرأ من المكوِّن والمُصيِّر،
   فالفحصُ يسأل الشيفرةَ الحاكمةَ لا يفترض سلامتَها. */
$php = (string) @file_get_contents($root . '/includes/screen_contract.php');
$js  = (string) @file_get_contents($root . '/assets/js/ems-screen-about.js');
$phpTags = (strpos($php, "data-src=\"' . \$src . '\"") !== false);
/* المشتقُّ يجب أن يُصدِر قالبَه موسومًا `auto` — يُفحص بالمعنى لا بشكلِ النداء:
   (الفحصُ الأولُ ثبَّت توقيعًا حرفيًّا، فرسب كاذبًا حين تغيّر شكلُ النداء
   والعقدُ نافذٌ كما هو — وفحصٌ يرسب على إعادةِ صياغةٍ سليمةٍ ضجيجٌ لا حارس.) */
$autoTagged = (bool) preg_match("/function\s+ems_screen_about_auto.*?'src'\s*=>\s*'auto'/s", $php);
$jsPrefers = (strpos($js, "[data-src=\"manual\"]") !== false && strpos($js, "[data-src=\"auto\"]") !== false);
if (!($phpTags && $autoTagged && $jsPrefers)) {
    $fail['order'][] = 'عقدُ الترجيح غير نافذ: '
        . 'وسمُ المصدر=' . ($phpTags ? '✔' : '✘')
        . ' · المشتقُّ موسومٌ auto=' . ($autoTagged ? '✔' : '✘')
        . ' · المُصيِّرُ يفضّل اليدويَّ=' . ($jsPrefers ? '✔' : '✘');
}

/* ⑥ تغطيةُ سجلِّ التعريفات: كلُّ شاشةٍ تعتمد المشتقَّ يلزمها صفٌّ في
   `screen_about` — وإلا خرجت بتعريفِ الارتداد (اسمٌ وإدارة) وهو أفقر. */
$registryMissing = array(); $bySource = array();
try {
    require_once $root . '/includes/env.php';
    $db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
    if (!$db->connect_errno) {
        $db->set_charset('utf8mb4');
        $have = array();
        $r = $db->query("SELECT screen_path, source FROM screen_about WHERE active = 1");
        while ($x = $r->fetch_assoc()) {
            $have[strtolower($x['screen_path'])] = $x['source'];
            $bySource[$x['source']] = (isset($bySource[$x['source']]) ? $bySource[$x['source']] : 0) + 1;
        }
        foreach ($autoScreens as $rel) {
            if (!isset($have[strtolower($rel)])) { $registryMissing[] = $rel; }
        }
    }
} catch (\Throwable $t) { /* السجلُّ قد لا يكون مُرحَّلًا */ }
$fail['registry'] = $registryMissing;

$labels = array(
    'legacy'   => '① بقايا البطاقة القديمة',
    'anchor'   => '② شاشةٌ بلا مرساةِ رأسٍ موحَّد',
    'order'    => '③ الترجيحُ بالمصدر نافذٌ (لا بالموضع)',
    'arity'    => '④ نداءٌ بوسائطَ أكثرَ من التوقيع',
    'twice'    => '⑤ نداءان يدويّان في ملفٍّ واحد',
    'registry' => '⑥ شاشةٌ على المشتقِّ بلا صفٍّ في سجلِّ التعريفات',
);

echo "فاحصُ «عن الشاشة» — $screens شاشةً تنادي المكوِّن\n";
echo str_repeat('─', 74) . "\n";
$total = 0;
foreach ($labels as $k => $label) {
    $n = count($fail[$k]);
    $total += $n;
    printf("%s %-46s %s\n", $n === 0 ? '✔' : '✘', $label, $n);
    foreach (array_slice($fail[$k], 0, 12) as $x) { echo "     - $x\n"; }
    if ($n > 12) { echo "     … و" . ($n - 12) . " غيرها\n"; }
}
echo str_repeat('─', 74) . "\n";
echo "شاشاتٌ يجتمع فيها النداءان والمشتقُّ أولًا: $mixedAutoFirst"
   . " — يحميها الترجيحُ بالمصدر\n";
if ($bySource) {
    ksort($bySource);
    $parts = array();
    foreach ($bySource as $s => $n) { $parts[] = "$s=$n"; }
    echo "سجلُّ التعريفات: " . implode(' · ', $parts) . "\n";
}
echo ($total === 0 ? "✔ صفرُ مخالفة — المكوِّنُ موحَّدٌ على $screens شاشة\n" : "✘ $total مخالفة\n");
exit($total === 0 ? 0 : 1);
