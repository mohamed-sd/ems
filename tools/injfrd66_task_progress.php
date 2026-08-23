<?php
/**
 * injfrd66_task_progress.php — عدّادُ إنجازِ حزمة INJ-FRD-01
 *
 * المصدرُ الوحيدُ للحالة: tools/injfrd66_tasks.json
 * والمخرَجُ: docs/INJ-FRD-01_TASK_REGISTER_ar.md مولَّدٌ لا مكتوبٌ يدويًّا.
 *
 * التشغيل:
 *   php tools/injfrd66_task_progress.php                                  عرضُ الحصيلة
 *   php tools/injfrd66_task_progress.php --write                          توليدُ التقرير
 *   php tools/injfrd66_task_progress.php --set=SAL-06:built=نعم,data=نعم  تعليمُ بواباتٍ منجزة
 */
declare(strict_types=1);

$ROOT  = dirname(__DIR__);
$STATE = $ROOT . '/tools/injfrd66_tasks.json';
$OUT   = $ROOT . '/docs/INJ-FRD-01_TASK_REGISTER_ar.md';

/* ── أوزانُ البوابات الخمس — مجموعُها مئة لكلِّ متطلب ────────────────── */
const GATE_W  = ['built' => 25, 'linked' => 25, 'data' => 15, 'tested' => 20, 'accepted' => 15];
const GATE_AR = [
    'built'    => 'مبنيّ',
    'linked'   => 'موصول',
    'data'     => 'بيانات مُرحَّلة',
    'tested'   => 'مُختبَر',
    'accepted' => 'مقبول',
];

/* قيمةُ البوابة: «لا ينطبق» استيفاءٌ لا نقص · و«—» غيرُ محسومٍ فلا يُحتسب */
function gate_value(string $v): float
{
    return match (trim($v)) {
        'نعم', 'لا ينطبق' => 1.0,
        'جزئي'            => 0.5,
        default           => 0.0,
    };
}

$tasks = json_decode((string) file_get_contents($STATE), true);
if (!is_array($tasks)) {
    fwrite(STDERR, "تعذّرت قراءةُ الحالة: {$STATE}\n");
    exit(1);
}

/* ── ‎--set: تعليمُ بوابةٍ منجزةٍ من سطر الأوامر ─────────────────────── */
foreach ($argv as $a) {
    if (!preg_match('/^--set=([A-Z]{2,3}-\d{2}):(.+)$/u', $a, $m)) {
        continue;
    }
    [$id, $pairs] = [$m[1], $m[2]];
    $hit = false;
    foreach ($tasks as &$t) {
        if ($t['id'] !== $id) {
            continue;
        }
        $hit = true;
        foreach (explode(',', $pairs) as $p) {
            $kv = array_map('trim', explode('=', $p, 2));
            $k  = $kv[0];
            $v  = $kv[1] ?? '';
            if (!isset(GATE_W[$k])) {
                fwrite(STDERR, "بوابةٌ مجهولة: {$k}\n");
                exit(1);
            }
            $t['gates'][$k] = $v;
        }
    }
    unset($t);
    if (!$hit) {
        fwrite(STDERR, "متطلبٌ مجهول: {$id}\n");
        exit(1);
    }
    file_put_contents($STATE, json_encode($tasks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "حُدِّث {$id}: {$pairs}\n";
}

/* ── ‎--measured: تحديثُ نصِّ القياسِ من سطر الأوامر ────────────────────
   ◆ **ولماذا من الأداةِ لا باليد**: `measured` هو ما يشرح **لماذا** استقرَّت
     البوابةُ على قيمتِها. وحكمٌ يتغيّر ونصُّه القديمُ باقٍ **يكذب بصمت** —
     يقرؤه القادمُ فيظنُّ أنَّ القياسَ لم يُعَد. والملفُّ مولَّدٌ لا يُحرَّر يدًا.
   الصيغة: --measured=SUP-14:النصُّ الجديد                                  */
foreach ($argv as $a) {
    if (!preg_match('/^--measured=([A-Z]{2,3}-\d{2}):(.+)$/us', $a, $m)) {
        continue;
    }
    [$id, $txt] = [$m[1], $m[2]];
    $hit = false;
    foreach ($tasks as &$t) {
        if ($t['id'] !== $id) { continue; }
        $hit = true;
        $t['measured'] = $txt;
    }
    unset($t);
    if (!$hit) {
        fwrite(STDERR, "متطلبٌ مجهول: {$id}\n");
        exit(1);
    }
    file_put_contents($STATE, json_encode($tasks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "حُدِّث قياسُ {$id}\n";
}

/* ── الحساب ────────────────────────────────────────────────────────── */
$n         = count($tasks);
$sum       = 0.0;
$byScope   = [];
$gateTally = array_fill_keys(array_keys(GATE_W), ['نعم' => 0, 'جزئي' => 0, 'لا' => 0, 'لا ينطبق' => 0, '—' => 0]);

foreach ($tasks as &$t) {
    $pct = 0.0;
    foreach (GATE_W as $g => $w) {
        $v    = (string) ($t['gates'][$g] ?? '—');
        $pct += gate_value($v) * $w;
        $key  = in_array(trim($v), ['نعم', 'جزئي', 'لا', 'لا ينطبق'], true) ? trim($v) : '—';
        $gateTally[$g][$key]++;
    }
    $t['pct']    = round($pct, 1);
    $t['share']  = round(100 / $n, 4);                 // وزنُ المتطلبِ من البرنامج
    $t['earned'] = round($pct / 100 * (100 / $n), 4);
    $sum        += $t['earned'];

    $s            = $t['scope'];
    $byScope[$s] ??= ['n' => 0, 'earned' => 0.0];
    $byScope[$s]['n']++;
    $byScope[$s]['earned'] += $t['earned'];
}
unset($t);

$done      = round($sum, 2);
$remaining = round(100 - $sum, 2);
$fmt       = static fn(float $x): string => number_format($x, 2);

/* ── العرض على الطرفية ─────────────────────────────────────────────── */
echo "\n═══ INJ-FRD-01 · حصيلةُ الإنجاز ═══\n";
echo "المتطلبات: {$n}   |   المنجز: " . $fmt($done) . '%   |   المتبقّي: ' . $fmt($remaining) . "%\n\n";
foreach ($byScope as $s => $d) {
    printf("  %-10s %2d متطلبًا · منجزٌ %s%% من %s%%\n", $s, $d['n'], $fmt($d['earned']), $fmt($d['n'] * 100 / $n));
}
echo "\nالبوابات:\n";
foreach (GATE_W as $g => $w) {
    $q = $gateTally[$g];
    printf("  %-16s (وزن %2d) نعم %2d · جزئي %2d · لا %2d · لا ينطبق %2d · غير محسوم %2d\n",
        GATE_AR[$g], $w, $q['نعم'], $q['جزئي'], $q['لا'], $q['لا ينطبق'], $q['—']);
}
echo "\n";

if (!in_array('--write', $argv, true)) {
    exit(0);
}

/* ── توليدُ التقرير ─────────────────────────────────────────────────── */
$bar  = static function (float $p): string {
    $full = (int) round($p / 5);
    return str_repeat('█', $full) . str_repeat('░', 20 - $full);
};
$mark = static fn(array $t): string => $t['pct'] >= 100 ? '✅' : ($t['pct'] > 0 ? '◐' : '☐');

$md   = [];
$md[] = '# INJ-FRD-01 — سجلُّ التاسكات المطلوبة وحصيلةُ الإنجاز';
$md[] = '';
$md[] = '> **مولَّدٌ آليًّا** بـ`tools/injfrd66_task_progress.php` من `tools/injfrd66_tasks.json` — لا يُحرَّر يدويًّا.';
$md[] = '> المرجعُ الحاكم: `INJ-FRD-01` وثيقةُ المتطلبات الوظيفية · و`INJ-FRD-TRACE-01` مصفوفاتُ التتبّع الأربع.';
$md[] = '';
$md[] = '## ① الحصيلة';
$md[] = '';
$md[] = '| المقياس | القيمة |';
$md[] = '|---|---|';
$md[] = "| إجمالي المتطلبات | {$n} |";
$md[] = '| **المنجز** | **' . $fmt($done) . '%** |';
$md[] = '| **المتبقّي** | **' . $fmt($remaining) . '%** |';
$md[] = '| وزنُ المتطلبِ الواحد من البرنامج | ' . number_format(100 / $n, 4) . '% |';
$md[] = '';
$md[] = '```';
$md[] = 'المنجز  ' . $bar($done) . '  ' . $fmt($done) . '%';
$md[] = '```';
$md[] = '';
$md[] = '### حصيلةُ كلِّ نطاق';
$md[] = '';
$md[] = '| النطاق | عدد المتطلبات | سقفُه من البرنامج | المنجزُ منه | نسبةُ إنجازِه |';
$md[] = '|---|---:|---:|---:|---:|';
foreach ($byScope as $s => $d) {
    $cap  = $d['n'] * 100 / $n;
    $md[] = "| {$s} | {$d['n']} | " . $fmt($cap) . '% | ' . $fmt($d['earned']) . '% | ' . $fmt($d['earned'] / $cap * 100) . '% |';
}
$md[] = '';
$md[] = '### أوزانُ البوابات الخمس';
$md[] = '';
$md[] = '| البوابة | وزنُها داخل المتطلب | نعم | جزئي | لا | لا ينطبق | غير محسوم |';
$md[] = '|---|---:|---:|---:|---:|---:|---:|';
foreach (GATE_W as $g => $w) {
    $q    = $gateTally[$g];
    $md[] = '| ' . GATE_AR[$g] . " | {$w}% | {$q['نعم']} | {$q['جزئي']} | {$q['لا']} | {$q['لا ينطبق']} | {$q['—']} |";
}
$md[] = '';
$md[] = '> «لا ينطبق» استيفاءٌ لا نقص — إسقاطٌ أو مرجعٌ لا يحمل بياناتٍ خاصةً بسببٍ مسجَّل · و«—» غيرُ محسومٍ فلا يُحتسب حتى يُقاس.';
$md[] = '';
$md[] = '### المقاماتُ الحاكمةُ بعد تحديث المرجع';
$md[] = '';
$md[] = '| المقام | القيمة | البيان |';
$md[] = '|---|---:|---|';
$md[] = '| المبيعات — أوراق المرجع | 26 | ثابت |';
$md[] = '| المبيعات — حقول النظام | 589 | من 604 خليةَ رأسٍ بعد استبعاد 15 خليةَ شرح |';
$md[] = '| الموردون — أوراق المرجع | 34 | ‎+5 أوراقٍ عن السابق |';
$md[] = '| الموردون — حقول النظام | 828 | ‎+111 حقلًا |';
$md[] = '| **إجمالي الحقول المحكومة** | **1,417** | صفرُ حقلٍ بلا حكم |';
$md[] = '| قدراتٌ محكومةٌ مستجدّة | 5 | منها 4 تُبنى أسطحًا · وقاموسُ الاستنتاج مرجعٌ لا سطح |';
$md[] = '| حالاتُ الاختبار | 208 | منها 66 سالبةً و52 مطابقةَ حقولٍ و24 للعملياتِ الحرجة |';
$md[] = '| حالاتٌ نجحت | 0 | لم يُنفَّذ أيُّ اختبارٍ بعد |';
$md[] = '';

/* ── الفروقُ بين حكم الوثيقة والقياس الحي ────────────────────────── */
$deltas = array_values(array_filter($tasks, static fn(array $t): bool => !empty($t['measured']) && str_contains((string) $t['measured'], 'تصحيح')));
if ($deltas) {
    $md[] = '## ② الفروقُ بين حكمِ الوثيقةِ والقياسِ الحيِّ على النظام';
    $md[] = '';
    $md[] = 'قِيست هذه المواضعُ على القاعدةِ والقرصِ مباشرةً فخالف القياسُ حكمَ مصفوفة الإغلاق. **حكمُ الوثيقةِ باقٍ في الحساب** حتى يُعتمد التصحيح — وهذه المواضعُ أوّلُ ما يُراجَع:';
    $md[] = '';
    $md[] = '| المعرّف | حكمُ الوثيقة | ما قِيس فعلًا |';
    $md[] = '|---|---|---|';
    foreach ($deltas as $t) {
        $md[] = "| **{$t['id']}** {$t['title']} | {$t['reason']} | " . str_replace('**تصحيح جوهري:** ', '', str_replace('**تصحيح جزئي:** ', '', str_replace('**تصحيح:** ', '', (string) $t['measured']))) . ' |';
    }
    $md[] = '';
}

/* ── ترتيبُ التنفيذ ─────────────────────────────────────────────────── */
$order = [
    ['كنسُ دفتر الدورة من الصيغ الممنوعة ثم اشتقاقُ القائمة الجانبية من الجدول المستهدف — فلا يُقاس تنقّلٌ قبل ذلك', 'XC-02 ← XC-01'],
    ['تصحيحُ مواضع الشاشات القائمة وأسمائها', 'SAL-03 · SAL-21 · SUP-05 · SUP-11'],
    ['بناءُ ملفات الأمّهات وتبويباتها في الإدارتين، وتحويلُ المسارات القديمة لا حذفُها', 'SAL-01 · SAL-02 · SAL-07 · SAL-11 · SAL-13 · SAL-14 · SAL-19 · SUP-01 · SUP-02 · SUP-03 · SUP-08 · SUP-10 · SUP-13 · SUP-18 · SUP-25'],
    ['بناءُ القدرات المستجدّة الأربع في الموردين ولوحةِ الإدارة', 'SUP-07 · SUP-16 · SUP-22 · SUP-26 · SUP-28'],
    ['تشغيلُ واجهات الجداول المبنيّة صفرَ صف', 'SAL-06 · SAL-08 · SAL-09 · SUP-23'],
    ['تفعيلُ أزرار الإنشاء ببواباتها وإنفاذُ الشرط في طبقة الخدمة لا الواجهة', 'XC-07 · XC-08 · XC-09'],
    ['إسنادُ حساباتٍ لدور إدارة الموردين — فلا تُمارَس إدارةٌ بلا مستخدم', 'XC-05'],
    ['تفعيلُ حجب السلّم بتدرّجٍ على رحلةٍ واحدةٍ ثم التعميم', 'XC-03'],
    ['تمريرُ رحلةِ إيرادٍ ورحلةِ موردٍ حقيقيتين بحساب موظف', 'XC-11'],
    ['تشغيلُ بوابتَي الأعمدة على المقامين المحدَّثين ثم إصدارُ لقطةِ قياسٍ جديدة', 'XC-06 · XC-10'],
    ['المداخلُ والحدود — سطحٌ مبنيٌّ بلا مدخلٍ يُوصَل أو يُعلَن خدميًّا', 'XC-04 · XC-12'],
];
$md[] = '## ③ ترتيبُ التنفيذ — تبعيةٌ لا جدولُ انتظار';
$md[] = '';
$md[] = 'ما لا يعتمد على سابقه يُنفَّذ بالتوازي:';
$md[] = '';
$md[] = '| # | الخطوة | التاسكات |';
$md[] = '|---:|---|---|';
foreach ($order as $k => $o) {
    $md[] = '| ' . ($k + 1) . " | {$o[0]} | `{$o[1]}` |";
}
$md[] = '';

/* ── جداولُ النطاقات ──────────────────────────────────────────────── */
$i = 0;
foreach (array_keys($byScope) as $scope) {
    $i++;
    $md[] = '## ' . ['','④','⑤','⑥','⑦'][$i] . ' سجلُّ التاسكات — ' . $scope;
    $md[] = '';
    $md[] = '| | المعرّف | العنوان | السطح | مبنيّ | موصول | بيانات | مُختبَر | مقبول | نسبتُه | من البرنامج |';
    $md[] = '|---|---|---|---|:-:|:-:|:-:|:-:|:-:|---:|---:|';
    foreach ($tasks as $t) {
        if ($t['scope'] !== $scope) {
            continue;
        }
        $g    = $t['gates'];
        $md[] = '| ' . $mark($t) . " | **{$t['id']}** | {$t['title']} | " . str_replace('|', '/', (string) $t['surface']) . ' | '
              . "{$g['built']} | {$g['linked']} | {$g['data']} | {$g['tested']} | {$g['accepted']} | "
              . $fmt((float) $t['pct']) . '% | ' . number_format((float) $t['earned'], 3) . ' من ' . number_format((float) $t['share'], 3) . ' |';
    }
    $md[] = '';
}

/* ── التفصيل ────────────────────────────────────────────────────────── */
$md[] = '## ⑦ تفصيلُ كلِّ تاسك — المتطلبُ ومعيارُ قبولِه';
$md[] = '';
foreach ($tasks as $t) {
    $md[] = '### ' . $mark($t) . " {$t['id']} — {$t['title']}  ·  " . $fmt((float) $t['pct']) . '%';
    $md[] = '';
    $md[] = "- **النطاق:** {$t['scope']}  ·  **المصدر:** {$t['source']}  ·  **حقولُه في المرجع:** {$t['fields']}";
    $md[] = "- **المتطلب:** {$t['text']}";
    $md[] = "- **السطح:** {$t['surface']}";
    $md[] = "- **معيار القبول:** {$t['criterion']}";
    $md[] = "- **حالتُه في مصفوفة الإغلاق:** {$t['reason']}";
    if (!empty($t['measured'])) {
        $md[] = "- **قياسٌ حيٌّ على القاعدة والقرص:** {$t['measured']}";
    }
    $md[] = '';
}

$md[] = '---';
$md[] = '';
$md[] = '## تحديثُ التقرير عند إنجاز تاسك';
$md[] = '';
$md[] = '```bash';
$md[] = 'php tools/injfrd66_task_progress.php --set=SAL-06:built=نعم,linked=نعم,data=نعم --write';
$md[] = '```';
$md[] = '';
$md[] = 'القيمُ المقبولة لكلِّ بوابة: `نعم` · `جزئي` · `لا` · `لا ينطبق` · `—`.';
$md[] = '';

file_put_contents($OUT, implode("\n", $md) . "\n");
echo 'كُتب التقرير: docs/INJ-FRD-01_TASK_REGISTER_ar.md (' . count($md) . " سطرًا)\n";

/* ── لوحةُ العرض — مصدرُ الـArtifact ───────────────────────────────── */
$HTML = $ROOT . '/docs/injfrd66/task_register.html';
if (!is_dir(dirname($HTML))) {
    mkdir(dirname($HTML), 0777, true);
}
$payload = [];
foreach ($tasks as $t) {
    $payload[] = [
        'id'       => $t['id'],
        'scope'    => $t['scope'],
        'title'    => $t['title'],
        'source'   => $t['source'],
        'fields'   => $t['fields'],
        'text'     => $t['text'],
        'surface'  => $t['surface'],
        'crit'     => $t['criterion'],
        'reason'   => $t['reason'],
        'measured' => $t['measured'] ?? '',
        'gates'    => $t['gates'],
        'pct'      => $t['pct'],
        'earned'   => $t['earned'],
    ];
}
$tpl = file_get_contents(__DIR__ . '/injfrd66_register.tpl.html');
$html = strtr($tpl, [
    '{{DATA}}'      => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    '{{DONE}}'      => $fmt($done),
    '{{REMAIN}}'    => $fmt($remaining),
    '{{N}}'         => (string) $n,
    '{{SHARE}}'     => number_format(100 / $n, 4),
    '{{SCOPES}}'    => json_encode(array_map(
        static fn(string $s, array $d): array => ['name' => $s, 'n' => $d['n'], 'cap' => round($d['n'] * 100 / $n, 2), 'earned' => round($d['earned'], 2)],
        array_keys($byScope),
        array_values($byScope)
    ), JSON_UNESCAPED_UNICODE),
    '{{GATES}}'     => json_encode(array_map(
        static fn(string $g): array => ['key' => $g, 'ar' => GATE_AR[$g], 'w' => GATE_W[$g], 'tally' => $gateTally[$g]],
        array_keys(GATE_W)
    ), JSON_UNESCAPED_UNICODE),
    '{{STAMP}}'     => date('Y-m-d H:i'),
]);
file_put_contents($HTML, $html);
echo "كُتبت اللوحة: docs/injfrd66/task_register.html\n";
