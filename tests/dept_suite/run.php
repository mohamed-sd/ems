<?php
/**
 * tests/dept_suite/run.php — مُشغِّلُ فحصِ الإدارة
 * ═══════════════════════════════════════════════════════════════════════════
 *   php tests/dept_suite/run.php                     ← افحص المبيعاتِ وقارِن بالأساس
 *   php tests/dept_suite/run.php --quick             ← تصييرٌ فقط (بلا كتابةٍ في القاعدة)
 *   php tests/dept_suite/run.php --save-baseline     ← اجعل نتيجةَ اليومِ خطَّ الأساس
 *   php tests/dept_suite/run.php --dept=sales        ← إدارةٌ أخرى (حين تُكتب مانيفستُها)
 *   php tests/dept_suite/run.php --sweep-only        ← نظِّف بقايا تشغيلةٍ انهارت
 *
 * رمزُ الخروج: 0 لا انكسارَ عن الأساس · 1 انكسر شيءٌ · 2 عطبُ عُدّةٍ (قاعدةٌ/خادم).
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/engine.php';

$opt  = getopt('', array('dept::', 'quick', 'save-baseline', 'sweep-only', 'base::', 'user::', 'verbose'));
$dept = isset($opt['dept']) && $opt['dept'] !== false ? preg_replace('/[^a-z_]/', '', $opt['dept']) : 'sales';

$mfPath = __DIR__ . '/manifest_' . $dept . '.php';
if (!is_file($mfPath)) {
    fwrite(STDERR, "⛔ لا مانيفستَ للإدارة «{$dept}» — المتوقَّع: {$mfPath}\n");
    exit(2);
}
$MF = require $mfPath;

$ctx = ds_ctx(array(
    'base'  => isset($opt['base']) && $opt['base'] !== false ? $opt['base'] : 'http://localhost/ems',
    'quick' => isset($opt['quick']),
    'verbose' => isset($opt['verbose']),
));

$W = 78;
function ds_line($ch = '─') { echo str_repeat($ch, 78) . "\n"; }
function ds_h($t) { echo "\n"; ds_line('═'); echo "  {$t}\n"; ds_line('═'); }

// ══════════════════════════════════════════════════════════════════════════
// وضعُ الكنسِ وحدَه
// ══════════════════════════════════════════════════════════════════════════
if (isset($opt['sweep-only'])) {
    ds_h('كنسُ بقايا الفحص — ' . $MF['dept_ar']);
    $before = ds_residue($ctx, $MF['sweep']);
    ds_sweep_all($ctx, $MF);
    $after = ds_residue($ctx, $MF['sweep']);
    echo "  صفوفٌ تحمل البادئةَ «" . DS_MARK_PREFIX . "» قبلَ الكنس: {$before}\n";
    echo "  وبعدَه: {$after}\n";
    echo ($after === 0 ? "  ✅ نظيف.\n" : "  ⚠ بقي {$after} صفًّا — راجعْ يدويًّا.\n");
    exit($after === 0 ? 0 : 1);
}

// ══════════════════════════════════════════════════════════════════════════
// التشغيل
// ══════════════════════════════════════════════════════════════════════════
ds_h('فحصُ ' . $MF['dept_ar'] . ($ctx['quick'] ? '  ·  الوضعُ السريع (بلا كتابة)' : ''));
echo "  الأسطح: " . count($MF['screens']) . "  ·  المستخدم: " . $MF['user']
   . "  ·  " . date('Y-m-d H:i') . "\n\n";

// ① بقايا سابقة
$res0 = ds_residue($ctx, $MF['sweep']);
if ($res0 > 0) {
    echo "  ⚠ وُجدت {$res0} صفًّا من تشغيلةٍ سابقةٍ لم تُكنس — تُنظَّف الآن.\n";
    ds_sweep_all($ctx, $MF);
}

// ② الدخول
list($okLogin, $why) = ds_login($ctx, $MF['user'], $MF['pass']);
if (!$okLogin) {
    fwrite(STDERR, "⛔ فشل الدخول: {$why}\n");
    fwrite(STDERR, "   تأكّد أن Apache يعمل وأن العنوانَ صحيحٌ: {$ctx['base']}\n");
    exit(2);
}
echo "  ✔ دخولٌ حقيقيٌّ بدور «" . $MF['user'] . "»\n";

// ③ الأساسات: تُبذر في الوضعِ الكامل، وتُستعار بالقراءةِ في السريع
if (!$ctx['quick']) {
    ds_fixtures_build($ctx);
    echo "  ✔ بُذرت أساساتُ الفحص: عميلٌ ومشروعٌ وعقدٌ وفرصةٌ وعرضٌ وموقع"
       . " (عقد #" . $ctx['fix']['CONTRACT'] . ")\n";
} else {
    ds_fixtures_borrow($ctx);
    echo "  ✔ استُعيرت أساساتٌ قائمةٌ بالقراءةِ وحدَها (عقد #" . $ctx['fix']['CONTRACT']
       . ") — لا كتابةَ في هذا الوضع\n";
}
echo "\n";

// ④ الجولة
$result = ds_run($MF, $ctx);
$result['fixtures'] = $ctx['fix'];

// ⑤ الكنس
$swept = ds_sweep_all($ctx, $MF);
$resid = ds_residue($ctx, $MF['sweep']);
$result['residue_after'] = $resid;

// ══════════════════════════════════════════════════════════════════════════
// التقرير
// ══════════════════════════════════════════════════════════════════════════
$ICON = array('PASS' => '✅', 'FAIL' => '🔴', 'NA' => '⚪', 'DENY' => '🔒', 'SKIP' => '⏭');
$OPAR = array('VIEW' => 'عرض', 'ADD' => 'إضافة', 'EDIT' => 'تعديل', 'DELETE' => 'حذف', 'GUARD' => 'حارس');

list($tally, $byop) = ds_tally($result);

ds_h('① النتيجةُ بالعملية');
printf("  %-10s %8s %8s %8s %8s %8s\n", 'العملية', 'ناجح', 'فاشل', 'غيرُ موجود', 'محجوب', 'متعذّر');
ds_line();
foreach (array('VIEW', 'ADD', 'EDIT', 'DELETE', 'GUARD') as $op) {
    if (!isset($byop[$op])) { continue; }
    $b = $byop[$op];
    printf("  %-10s %8d %8d %11d %8d %8d\n", $OPAR[$op], $b['PASS'], $b['FAIL'], $b['NA'], $b['DENY'], $b['SKIP']);
}
ds_line();
printf("  %-10s %8d %8d %11d %8d %8d\n", 'الإجمالي', $tally['PASS'], $tally['FAIL'], $tally['NA'], $tally['DENY'], $tally['SKIP']);

// ── الفاشلُ بالاسم ────────────────────────────────────────────────────────
$fails = array();
foreach ($result['screens'] as $route => $s) {
    foreach ($s['ops'] as $o) {
        if ($o['status'] === 'FAIL') { $fails[] = array($route, $s['label'], $o['op'], $o['note']); }
    }
}
if (!empty($fails)) {
    ds_h('② الفاشلُ الآن — بالاسمِ والسبب (' . count($fails) . ')');
    foreach ($fails as $f) {
        echo "  🔴 {$f[1]}\n";
        echo "     {$f[0]}  ·  " . $OPAR[$f[2]] . "\n";
        echo "     السبب: {$f[3]}\n\n";
    }
} else {
    ds_h('② الفاشلُ الآن');
    echo "  ✅ لا شيء.\n";
}

// ══════════════════════════════════════════════════════════════════════════
// ③ الفرقُ عن خطِّ الأساس — لبُّ الأداة
// ══════════════════════════════════════════════════════════════════════════
$baseDir = __DIR__ . '/baseline';
$resDir  = __DIR__ . '/results';
@mkdir($baseDir, 0777, true); @mkdir($resDir, 0777, true);
$basePath = $baseDir . '/' . $dept . '.json';
$baseline = is_file($basePath) ? json_decode(file_get_contents($basePath), true) : null;

$exit = 0;
// في الوضعِ السريعِ تُقارَن **عمليةُ العرضِ وحدَها** — الكتابةُ لم تُجرَّب أصلًا،
// ومقارنةُ ما لم يُقَس تُنتج «انكسر» كاذبًا بعددِ ما تُخُطِّي.
$diff = ds_diff($result, $baseline, $ctx['quick'] ? array('VIEW') : array());

if ($diff === null) {
    ds_h('③ الفرقُ عن خطِّ الأساس');
    echo "  ℹ لا خطَّ أساسٍ محفوظًا بعد.\n";
    echo "     شغّل هذا الأمرَ لتثبيتِ حالةِ اليومِ أساسًا يُقاس عليه ما بعده:\n\n";
    echo "     php tests/dept_suite/run.php --save-baseline\n";
} else {
    $nb = count($diff['broke']); $nf = count($diff['fixed']); $nc = count($diff['changed']);
    ds_h('③ الفرقُ عن خطِّ الأساس (' . $baseline['at'] . ')');
    if ($nb === 0 && $nf === 0 && $nc === 0 && empty($diff['added']) && empty($diff['gone'])) {
        echo "  ✅ **لا شيءَ تغيّر.** الإدارةُ كما تركتَها ({$diff['same']} عمليةً مطابقة).\n";
    } else {
        if ($nb > 0) {
            echo "\n  🔴 **انكسر منذ آخرِ أساس ({$nb})** — هذا ما أحدثه تعديلُك:\n\n";
            foreach ($diff['broke'] as $b) {
                echo "     ▸ {$b['label']}  ·  " . $OPAR[$b['op']] . "\n";
                echo "       {$b['route']}\n";
                echo "       كان: " . $ICON[$b['was']] . " {$b['was']}   ⇒   صار: " . $ICON[$b['is']] . " {$b['is']}\n";
                echo "       {$b['note']}\n\n";
            }
            $exit = 1;
        }
        if ($nf > 0) {
            echo "\n  🟢 **تحسّن ({$nf})**:\n";
            foreach ($diff['fixed'] as $b) {
                echo "     ▸ {$b['label']} · " . $OPAR[$b['op']]
                   . "  ({$b['was']} ⇒ {$b['is']})\n";
            }
        }
        if ($nc > 0) {
            echo "\n  🔵 **تبدّل بلا حكمٍ أفضلَ أو أسوأ ({$nc})**:\n";
            foreach ($diff['changed'] as $b) {
                echo "     ▸ {$b['label']} · " . $OPAR[$b['op']] . "  ({$b['was']} ⇒ {$b['is']})\n";
            }
        }
        if (!empty($diff['added'])) {
            echo "\n  ⚪ **أسطحٌ جديدةٌ لم تكن في الأساس (" . count($diff['added']) . ")**:\n";
            foreach ($diff['added'] as $a) { echo "     ▸ {$a['label']} · {$a['route']}\n"; }
        }
        if (!empty($diff['gone'])) {
            echo "\n  ⚫ **أسطحٌ كانت في الأساسِ واختفت (" . count($diff['gone']) . ")**:\n";
            foreach ($diff['gone'] as $a) { echo "     ▸ {$a['label']} · {$a['route']}\n"; }
        }
        echo "\n  (وبقي {$diff['same']} عمليةً بلا تغيير.)\n";
    }
}

// ══════════════════════════════════════════════════════════════════════════
// ④ الحفظ
// ══════════════════════════════════════════════════════════════════════════
$stamp   = date('Ymd_His');
$resPath = $resDir . '/' . $dept . '_' . $stamp . '.json';
file_put_contents($resPath, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

if (isset($opt['save-baseline'])) {
    if ($ctx['quick']) {
        echo "\n  ⚠ لم يُحفظ الأساسُ: الوضعُ السريعُ لا يفحص الكتابةَ فيصير أساسًا ناقصًا.\n";
    } else {
        file_put_contents($basePath, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "\n  ✔ حُفظت حالةُ اليومِ خطَّ أساس: tests/dept_suite/baseline/{$dept}.json\n";
    }
}

ds_h('⑤ النظافةُ والملفّات');
echo "  بقايا في القاعدة بعد الكنس: " . ($resid === 0 ? "صفر ✅" : "{$resid} ⚠") . "\n";
echo "  نتيجةُ هذه التشغيلة: tests/dept_suite/results/" . basename($resPath) . "\n";
if ($resid > 0) {
    echo "  لتنظيفِ ما بقي:  php tests/dept_suite/run.php --sweep-only\n";
    $exit = $exit ?: 1;
}
echo "\n";

exit($exit);
