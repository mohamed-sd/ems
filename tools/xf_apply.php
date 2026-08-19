<?php
/**
 * tools/xf_apply.php — منفِّذُ خطةِ «البياناتِ الإضافية» (XF-01)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ ينفّذ خطةً ولّدها `xf_plan.php` **بعدَ مراجعةِ المالك**، بأربعِ خطواتٍ
 *   مرتّبةٍ لكلِّ شاشة — والترتيبُ ملزِمٌ لأن اللاحقةَ تفترض السابقة:
 *     ① الهجرة   — أعمدةُ NEW **اختياريةً** (NULL) في جداولِها · ملفٌّ واحدٌ للخطةِ كلِّها
 *     ② السجل    — صفُّ الشاشةِ في `includes/extra_fields_generated.php`
 *     ③ الرؤوس   — `ems_xf_th_attrs()` في كلِّ `<th>` مخطَّط
 *     ④ الوصلُ في الشاشة — الاستدعاءُ · خلايا الصفِّ · قسمُ «المزيد» · جمعُ POST
 *
 * ◆ **العقدُ الحاكم: خطوةٌ لا يقينَ فيها لا تُنفَّذ.** الشاشاتُ الـ293 غيرُ
 *   متشابهةِ البنية، وتعديلٌ أعمى بمُطابِقٍ نمطيٍّ يكسر ملفًّا عاملًا. فكلُّ
 *   خطوةٍ لها **مرساةٌ صريحةٌ**، وغيابُها أو تعدُّدُها ⇒ **تُتخطّى وتُعلَن بسببها**
 *   ولا يُخمَّن موضع. والمُتخطّى يخرج قائمةَ عملٍ يدويٍّ دقيقةً لا صمتًا.
 *
 * ◆ **وكلُّ ملفٍّ يُلمس: نسخةٌ احتياطيةٌ قبلَه، و`php -l` بعدَه، وارتدادٌ فوريٌّ
 *   عند أيِّ خطأِ صياغة.** فلا يبقى ملفٌّ مكسورٌ في الشجرةِ بحالٍ من الأحوال.
 *
 * ◆ **والمعاينةُ هي الافتراض** — `--apply` وحدَه يكتب.
 *
 * التشغيل:
 *   php tools/xf_apply.php --plan=docs/xf_plans/<slug>.json                 (معاينة)
 *   php tools/xf_apply.php --plan=docs/xf_plans/<slug>.json --apply         (تنفيذ)
 *   ... [--screen=<مسار>]   لتنفيذِ شاشةٍ واحدةٍ من خطةٍ واسعة
 *   ... [--only=migration|registry|heads|wire]   لتنفيذِ خطوةٍ بعينِها
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/xf_lib.php';

$PLAN = ''; $APPLY = false; $ONLY_SCREEN = ''; $ONLY_STEP = '';
foreach ($argv as $a) {
    if (strpos($a, '--plan=') === 0)        { $PLAN = substr($a, 7); }
    elseif (strpos($a, '--screen=') === 0)  { $ONLY_SCREEN = ltrim(str_replace(chr(92), '/', substr($a, 9)), '/'); }
    elseif (strpos($a, '--only=') === 0)    { $ONLY_STEP = substr($a, 7); }
    elseif ($a === '--apply')               { $APPLY = true; }
}
if ($PLAN === '') { echo "الاستعمال: php tools/xf_apply.php --plan=<خطة.json> [--apply] [--screen=…] [--only=…]\n"; exit(1); }
$planPath = (strpos($PLAN, ':') !== false || $PLAN[0] === '/') ? $PLAN : $ROOT . '/' . $PLAN;
if (!is_file($planPath)) { exit("⛔ لا خطةَ: {$PLAN}\n"); }
$plan = json_decode(file_get_contents($planPath), true);
if (!$plan || empty($plan['screens'])) { exit("⛔ خطةٌ فارغةٌ أو تالفة\n"); }

$step = function ($s) use ($ONLY_STEP) { return $ONLY_STEP === '' || $ONLY_STEP === $s; };
$MODE = $APPLY ? 'تنفيذ' : 'معاينة';
echo "════ منفِّذُ خطةِ «البياناتِ الإضافية» · وضعُ {$MODE} ════\n";
echo "  الخطة: {$PLAN}\n";

/* ═══ تصفيةُ الشاشاتِ القابلةِ للتنفيذ ═══════════════════════════════════════
 * ◆ تُنفَّذ الشاشةُ إن كان فيها عمودٌ واحدٌ على الأقلِّ بحكمِ BIND أو NEW.
 *   وMANUAL يُنقل كما هو إلى تقريرِ العملِ اليدويّ — **ولا يُسقَط بصمت**. */
$work = array(); $manualAll = 0;
$blockedNew = 0; $blockedScreens = array(); $skippedNoWork = 0;
foreach ($plan['screens'] as $s) {
    if ($ONLY_SCREEN !== '' && $s['screen'] !== $ONLY_SCREEN) { continue; }
    $cols = array();
    foreach ($s['columns'] as $c) {
        if ($c['verdict'] === 'MANUAL') { $manualAll++; continue; }
        if ($c['verdict'] === 'BOUND')  { continue; }        /* موصولٌ سلفًا — لا عمل */
        $cols[] = $c;
    }
    if (!$cols) { $skippedNoWork++; continue; }
    if (empty($s['table']) || empty($s['table_exists'])) {
        echo "  ⚠ {$s['screen']} — جدولٌ غيرُ مؤكَّد؛ أعِد التخطيطَ بـ`--table=` (تُخطّى)\n";
        continue;
    }

    /* ═══════════════════════════════════════════════════════════════════════
     * حارسُ الجدولِ المؤكَّد — **لا عمودَ يُنشأ في جدولٍ استنبطته أداة**
     * ───────────────────────────────────────────────────────────────────────
     * ◆ **المقيسُ على الشجرةِ الحيّة**: استنباطُ الجدولِ خالف اسمَ الشاشةِ في
     *   **121 من 160** شاشة (75.6%)، وبعضُه فادحٌ لا لبسَ فيه:
     *     `Equipments/equipments.php`  ⇐ `supplierscontracts`
     *     `Employees/employees.php`    ⇐ `drivercontracts`
     *     `Contracts/collections.php`  ⇐ `role_permissions`
     *   وبديلٌ أذكى (تتبُّعُ حلقةِ الصفوفِ إلى استعلامِها) لم يرفع الموافقةَ إلا
     *   من 24.4% إلى **27.5%** — فرقٌ داخلَ الضجيج. ⇒ **الاستنباطُ لا يُصلَح،
     *   فيُحاط.**
     *
     * ◆ **والفرقُ بين النوعَين قاطعٌ في الأثر**:
     *     · `BIND` يطبع خليةً من `$row` — وأسوأُ خطئِه **خليةٌ فارغة**. لا ضرر.
     *     · `NEW`  يُنشئ عمودًا في جدول — وخطؤه **تلويثُ مخطَّطٍ** في جدولٍ
     *       بريء، لا يُكشف إلا متأخّرًا ولا يُنظَّف بسهولة.
     *   فيُمنع الثاني وحدَه، ويمضي الأوّل. ومنعٌ شاملٌ كان سيُعطِّل ما لا خطرَ فيه.
     *
     * ◆ والتأكيدُ **فعلُ إنسانٍ لا رايةٌ تُضبَط**: `php tools/xf_plan.php
     *   --screen=<الشاشة> --table=<الجدول>` — يقرؤها المخطِّطُ فيرفع
     *   `table_confirmed`. فلا يمرُّ إنشاءُ عمودٍ إلا بعينٍ رأت الجدول.
     * ═══════════════════════════════════════════════════════════════════════ */
    if (empty($s['table_confirmed'])) {
        $newCols = array();
        foreach ($cols as $c) { if ($c['verdict'] === 'NEW') { $newCols[] = $c['key']; } }
        if ($newCols) {
            $blockedNew += count($newCols);
            $blockedScreens[$s['screen']] = array('table' => $s['table'], 'cols' => $newCols);
            $cols = array_values(array_filter($cols, function ($c) { return $c['verdict'] !== 'NEW'; }));
            if (!$cols) { continue; }
        }
    }

    $work[$s['screen']] = array('table' => $s['table'], 'cols' => $cols);
}
echo "  شاشاتٌ قابلةٌ للتنفيذ: " . count($work) . " · بلا عملٍ آليّ: {$skippedNoWork} · أعمدةٌ يدويةٌ مُرحَّلة: {$manualAll}\n";

/* ◆ **المحجوبُ يُعلَن بأسمائِه لا برقمٍ مجمَل** — فقائمةُ المنعِ هي بعينِها
     قائمةُ العملِ المطلوبِ من المالك: كلُّ سطرٍ شاشةٌ وجدولُها المرجَّحُ والأعمدةُ
     المنتظرة. وإخفاؤها خلفَ عددٍ كان سيجعل المنعَ يبدو عطلًا لا حراسة. */
if ($blockedNew) {
    echo "\n  ⛔ حُجب إنشاءُ {$blockedNew} عمودًا في " . count($blockedScreens) . " شاشةً — جدولُها مُستنبَطٌ لا مؤكَّد.\n";
    echo "     (الوصلُ بمصدرٍ قائمٍ `BIND` مضى — المحجوبُ هو إنشاءُ الأعمدةِ وحدَه.)\n";
    $shown = 0;
    foreach ($blockedScreens as $sc => $b) {
        if ($shown++ >= 12) { echo "     … و" . (count($blockedScreens) - 12) . " شاشةً أخرى في الخطة.\n"; break; }
        printf("     · %-44s جدولٌ مرجَّح: %-24s (%s)\n", $sc, $b['table'], implode(' · ', $b['cols']));
    }
    echo "     للرفع: php tools/xf_plan.php --screen=<الشاشة> --table=<الجدولُ الصحيح>\n";
}

if (!$work) { echo "\nلا شيءَ يُنفَّذ.\n"; exit(0); }

/* ═══ ① الهجرة — عمودٌ اختياريٌّ لكلِّ NEW، مجموعةً بجدولها ══════════════════ */
$byTable = array();
foreach ($work as $screen => $w) {
    foreach ($w['cols'] as $c) {
        if ($c['verdict'] !== 'NEW') { continue; }
        $byTable[$w['table']][$c['key']] = $c;
    }
}
$migFile = '';
if ($byTable && $step('migration')) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', basename($planPath, '.json')));
    $seq  = date('Y_m_d', strtotime('+1 year'));   /* عرفُ التسميةِ في هذا المستودع */
    $migRel = 'database/migrations/' . $seq . '_xf_' . $slug . '.php';
    $migFile = $ROOT . '/' . $migRel;
    $body = xf_build_migration($byTable, $migRel);
    $nNew = 0; foreach ($byTable as $t => $cs) { $nNew += count($cs); }
    echo "  ① هجرة: {$nNew} عمودًا في " . count($byTable) . " جدولًا ⇐ {$migRel}\n";
    foreach ($byTable as $t => $cs) { echo "       {$t}: " . implode(' · ', array_keys($cs)) . "\n"; }
    if ($APPLY) {
        if (is_file($migFile)) { echo "     ⚠ الملفُّ موجودٌ — لا يُدهَس (احذفه أو غيّر اسمَ الخطة)\n"; }
        else { file_put_contents($migFile, $body); echo "     ✔ كُتب — شغّله: php {$migRel}\n"; }
    }
} elseif ($step('migration')) {
    echo "  ① هجرة: لا عمودَ جديدًا — كلُّ المخطَّطِ وصلٌ بمصدرٍ قائم\n";
}

/* ═══ ② السجل — ملفٌّ **مولَّدٌ منفصل** لا يُلمس فيه المكتوبُ يدويًّا ══════════ */
if ($step('registry')) {
    $genRel = 'includes/extra_fields_generated.php';
    $genPath = $ROOT . '/' . $genRel;
    $existing = array();
    if (is_file($genPath)) { $existing = (array) (include $genPath); }
    $added = 0;
    foreach ($work as $screen => $w) {
        if (isset($existing[$screen])) { continue; }   /* لا يُدهَس صفٌّ قائم */
        $existing[$screen] = xf_registry_entry($screen, $w);
        $added++;
    }
    echo "  ② السجل: +{$added} شاشةً ⇐ {$genRel} (المجموع " . count($existing) . ")\n";
    if ($APPLY && $added) { file_put_contents($genPath, xf_render_registry($existing)); echo "     ✔ كُتب\n"; }
}

/* ═══ ③④ الرؤوسُ والوصلُ في ملفِّ الشاشة ═══════════════════════════════════ */
$okHeads = 0; $okWire = 0; $todo = array();
foreach ($work as $screen => $w) {
    $path = $ROOT . '/' . $screen;
    if (!is_file($path)) { $todo[$screen][] = 'الملفُّ غيرُ موجود'; continue; }
    $src = file_get_contents($path);
    $orig = $src;
    $notes = array();

    /* ── ③ الرؤوس: تسميةٌ ⇒ سمةُ وصل ─────────────────────────────────────── */
    if ($step('heads')) {
        $done = 0;
        foreach ($w['cols'] as $c) {
            $src = xf_bind_head($src, $c['label'], $c['key'], $done);
        }
        if ($done !== count($w['cols'])) {
            $notes[] = 'رؤوسٌ لم تُوصَل: ' . (count($w['cols']) - $done) . ' من ' . count($w['cols'])
                     . ' (تسميةٌ لم تُطابَق حرفيًّا — قد تكون مُصيَّرةً بمتغيّر)';
        }
        if ($done) { $okHeads++; }
    }

    /* ── ④ الوصلُ: الاستدعاءُ · خلايا الصفِّ · «المزيد» · جمعُ POST ─────────── */
    if ($step('wire')) {
        $r = xf_wire_screen($src, $screen);
        $src = $r['src'];
        foreach ($r['notes'] as $n) { $notes[] = $n; }
        if (!$r['notes']) { $okWire++; }
    }

    if ($src === $orig) {
        if ($notes) { $todo[$screen] = $notes; }
        continue;
    }
    echo "  ③④ {$screen}" . ($notes ? ' — بملاحظات' : '') . "\n";
    if ($APPLY) {
        $bak = $path . '.xfbak';
        if (!is_file($bak)) { copy($path, $bak); }
        file_put_contents($path, $src);
        /* ◆ **الفحصُ بعدَ الكتابةِ لا قبلَها** — والارتدادُ فوريّ */
        $out = array(); $code = 1;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            copy($bak, $path);
            echo "     ✗ خطأُ صياغةٍ بعدَ التعديل — **ارتُدَّ إلى النسخةِ الأصلية**: " . implode(' ', $out) . "\n";
            $todo[$screen][] = 'التعديلُ الآليُّ أنتج خطأَ صياغةٍ فارتُدَّ — يلزم عملٌ يدويّ';
            continue;
        }
        echo "     ✔ كُتب (نسخةٌ احتياطيةٌ: " . basename($bak) . ")\n";
    }
    if ($notes) { $todo[$screen] = $notes; }
}

/* ═══ التقريرُ الختاميّ — والمُتخطّى يُعلَن ولا يُبتلع ═══════════════════════ */
echo "──────────────────────────────────────────────────────────\n";
echo "  رؤوسٌ وُصلت في: {$okHeads} شاشةً · وصلٌ كاملٌ في: {$okWire} شاشةً\n";
if ($todo) {
    echo "  عملٌ يدويٌّ مطلوبٌ في " . count($todo) . " شاشةً:\n";
    foreach ($todo as $s => $ns) { foreach ($ns as $n) { echo "     · {$s} — {$n}\n"; } }
}
if (!$APPLY) { echo "\n◆ معاينةٌ فقط — لم يُكتب حرف. أضف `--apply` للتنفيذ.\n"; }
else {
    echo "\n◆ بعدَ التنفيذ:\n";
    if ($migFile) { echo "   ① شغّل الهجرة ثم سجّلها:\n"
                       . "      php " . str_replace($ROOT . '/', '', str_replace(chr(92), '/', $migFile)) . "\n"
                       . "      php database/migrate.php mark-applied " . basename($migFile) . "\n"
                       . "      php database/migrate.php dump-schema\n"; }
    echo "   ② افحص شاشةً في المتصفّح · ثم: php tools/xf_gate.php\n";
    echo "   ③ النسخُ الاحتياطية `*.xfbak` — احذفها بعد الرضا، أو استرجع بها.\n";
}

/* ═══════════════════════════════════════════════════════════════════════════
 * الدوالُّ المساعدة
 * ═══════════════════════════════════════════════════════════════════════════ */

/** وصلُ رأسٍ واحدٍ بتسميتِه الحرفية — ولا يُلمس رأسٌ موصولٌ سلفًا. */
function xf_bind_head($src, $label, $key, &$done)
{
    $hit = false;
    $out = preg_replace_callback(
        '/<th\b([^>]*\bdata-(?:fn|gov)\b[^>]*)>(.*?)<' . '\/th>/su',
        function ($m) use ($label, $key, &$hit) {
            if ($hit) { return $m[0]; }
            if (strpos($m[1], 'data-fn-src') !== false || strpos($m[1], 'ems_xf_th_attrs') !== false) { return $m[0]; }
            if (xf_norm($m[2]) !== $label) { return $m[0]; }
            $hit = true;
            return '<th' . $m[1] . '<?php echo ems_xf_th_attrs($XF_SCREEN, ' . var_export($key, true) . '); ?>>'
                 . $m[2] . '</th>';
        },
        $src
    );
    if ($hit) { $done++; return $out; }
    return $src;
}

/**
 * وصلُ ملفِّ الشاشة — أربعُ مراسٍ، وكلُّ مرساةٍ غائبةٍ أو متعدّدةٍ تُعلَن ولا تُخمَّن.
 * ◆ ولا يُعدَّل ملفٌّ موصولٌ سلفًا: تكرارُ الإدراجِ يُنشئ نداءَين لكلِّ صف.
 */
function xf_wire_screen($src, $screen)
{
    $notes = array();

    /* ── ④-أ الاستدعاءُ وهويةُ الشاشة ─────────────────────────────────────── */
    if (strpos($src, 'extra_fields.php') === false) {
        $anchor = null;
        foreach (array("include '../config.php';", 'include "../config.php";',
                       "include_once '../config.php';", "require_once __DIR__ . '/../config.php';") as $a) {
            if (substr_count($src, $a) === 1) { $anchor = $a; break; }
        }
        if ($anchor === null) {
            $notes[] = 'لا مرساةَ استدعاءٍ وحيدةٍ (`include ../config.php`) — أضف السطرَين يدويًّا';
        } else {
            $src = str_replace($anchor, $anchor . "\n"
                 . "require_once __DIR__ . '/../includes/extra_fields.php'; // XF-01\n"
                 . '$XF_SCREEN = ' . var_export($screen, true) . ";\n", $src);
        }
    }

    /* ── ④-ب خلايا الصفّ ──────────────────────────────────────────────────
       المرساةُ: `echo "</tr>";` وحيدةً داخلَ حلقةٍ متغيّرُها معروف. */
    if (strpos($src, 'ems_xf_tds(') === false) {
        $closers = array('echo "</tr>";', "echo '</tr>';");
        $found = null; $cnt = 0;
        foreach ($closers as $c) { $n = substr_count($src, $c); $cnt += $n; if ($n === 1) { $found = $c; } }
        $var = '';
        if (preg_match('/foreach\s*\(\s*\$[a-z_][a-z0-9_]*\s+as\s+(\$[a-z_][a-z0-9_]*)\s*\)/i', $src, $vm)) { $var = $vm[1]; }
        if ($found === null || $cnt !== 1) {
            $notes[] = 'لا مرساةَ إغلاقِ صفٍّ وحيدةً (`echo "</tr>";`) — اطبع `ems_xf_tds()` يدويًّا آخرَ الصفّ';
        } elseif ($var === '') {
            $notes[] = 'متغيّرُ حلقةِ الصفوفِ لم يُعرَف — اطبع `ems_xf_tds()` يدويًّا آخرَ الصفّ';
        } else {
            $src = str_replace($found,
                'echo ems_xf_tds($XF_SCREEN, ' . $var . ", array('conn' => \$conn)); // XF-01\n                            " . $found,
                $src);
        }
    }

    /* ── ④-ج قسمُ «المزيد» ────────────────────────────────────────────────
       المرساةُ: أوّلُ `<div class="pu-form-actions">` أو `form-actions`. */
    if (strpos($src, 'ems_xf_render_form(') === false) {
        $a = null;
        foreach (array('<div class="pu-form-actions">', '<div class="form-actions">') as $cand) {
            if (substr_count($src, $cand) === 1) { $a = $cand; break; }
        }
        if ($a === null) {
            $notes[] = 'لا مرساةَ أزرارِ نموذجٍ وحيدةً — نادِ `ems_xf_render_form($XF_SCREEN)` يدويًّا قبلَها';
        } else {
            $src = str_replace($a, "<?php ems_xf_render_form(\$XF_SCREEN); // XF-01 ?>\n                " . $a, $src);
        }
    }

    /* ── ④-د جمعُ POST ودمجُه ─────────────────────────────────────────────
       ◆ **لا يُعدَّل نداءُ الكتابةِ آليًّا**: الشاشاتُ تكتب بطرقٍ مختلفة (بوابةُ
         مستأجرٍ · خدمةٌ · محضَّرةٌ يدوية)، ودمجٌ أعمى في محضَّرةٍ يُزيح
         `bind_param` فيمحو نصوصًا — وهي گوتشا مسجَّلةٌ في هذا المستودع.
         فيُعلَن الصنيعُ المطلوبُ حرفيًّا ويُترك للمراجع. */
    if (strpos($src, 'ems_xf_collect(') === false) {
        $notes[] = 'جمعُ POST يدويٌّ: أضف `$xf_values = ems_xf_collect($XF_SCREEN, $_POST);` '
                 . 'ثم `array_merge(<حمولتك>, $xf_values)` في الإدراجِ والتعديل';
    }

    return array('src' => $src, 'notes' => $notes);
}

/** صفُّ السجلِّ لشاشةٍ من صفوفِ الخطة. */
function xf_registry_entry($screen, $w)
{
    $cols = array();
    foreach ($w['cols'] as $c) {
        $e = array('key' => $c['key'], 'label' => $c['label']);
        if ($c['verdict'] === 'NEW') {
            $e['kind'] = 'own';
            $e['type'] = isset($c['type']) ? $c['type'] : 'text';
            $e['max']  = isset($c['max']) && $c['max'] ? (int) $c['max'] : 255;
            if (isset($e['type']) && $e['type'] === 'textarea') { $e['wide'] = true; }
        } elseif (isset($c['type']) && $c['type'] === 'derived') {
            $e['kind'] = 'derived';
            $e['render'] = isset($c['render']) ? $c['render'] : 'datetime';
        } else {
            $e['kind'] = 'existing';
        }
        $e['icon'] = isset($c['icon']) ? $c['icon'] : 'fas fa-circle-info';
        $cols[] = $e;
    }
    return array(
        'table' => $w['table'],
        'title' => 'بيانات إضافية',
        'hint'  => 'كلُّها اختياريةٌ — احفظ بالحدِّ الأدنى الآن، وأكملْ هذه متى توفّرت.',
        'group' => array('key' => 'extra', 'label' => 'بيانات إضافية', 'default' => 'hidden'),
        'columns' => $cols,
    );
}

/** كتابةُ السجلِّ المولَّدِ ملفَّ PHP يُعيد مصفوفة. */
function xf_render_registry($reg)
{
    $h = "<?php\n"
       . "/**\n"
       . " * includes/extra_fields_generated.php — **ملفٌّ مولَّدٌ · لا يُحرَّر يدويًّا**\n"
       . " * ───────────────────────────────────────────────────────────────────────────\n"
       . " * يكتبه `tools/xf_apply.php` ويدمجه `ems_xf_registry()` فوقَ السجلِّ المكتوبِ\n"
       . " * يدويًّا في `includes/extra_fields.php`. **والمكتوبُ يدويًّا يغلب** — فشاشةٌ\n"
       . " * ضُبطت بيدٍ لا يدهسها تشغيلٌ آليٌّ لاحق.\n"
       . " */\n"
       . "return ";
    return $h . var_export($reg, true) . ";\n";
}

/** نصُّ ملفِّ الهجرة — بالنمطِ الحارسِ نفسِه المعتمَدِ في هذا المستودع. */
function xf_build_migration($byTable, $migRel)
{
    $nCols = 0; foreach ($byTable as $t => $cs) { $nCols += count($cs); }
    $s  = "<?php\n";
    $s .= "/**\n";
    $s .= " * " . basename($migRel) . " — أعمدةٌ **اختياريةٌ** مولَّدةٌ عن خطةِ XF-01\n";
    $s .= " * ═══════════════════════════════════════════════════════════════════════════\n";
    $s .= " * ◆ ولّدها `tools/xf_apply.php` عن خطةٍ راجعها المالك. وكلُّ عمودٍ هنا\n";
    $s .= " *   **NULL DEFAULT NULL بلا استثناء** — لأن هذه بياناتٌ تُستكمَل لاحقًا،\n";
    $s .= " *   والحدُّ الأدنى لإضافةِ سجلٍّ لا يتغيّر بها.\n";
    $s .= " * ◆ idempotent بالنمطِ الحارس (`information_schema` قبلَ كلِّ `ADD COLUMN`).\n";
    $s .= " * ◆ المجموع: {$nCols} عمودًا في " . count($byTable) . " جدولًا.\n";
    $s .= " * ═══════════════════════════════════════════════════════════════════════════\n";
    $s .= " */\n";
    $s .= "if (php_sapi_name() !== 'cli') { exit(\"CLI فقط\\n\"); }\n";
    $s .= "error_reporting(E_ALL & ~E_DEPRECATED);\n";
    $s .= "mb_internal_encoding('UTF-8');\n";
    $s .= "\$ROOT = dirname(dirname(__DIR__));\n";
    $s .= "require_once \$ROOT . '/includes/env.php';\n";
    $s .= "\$host = ems_env('DB_HOST'); \$port = 3306;\n";
    $s .= "if (strpos(\$host, ':') !== false) { list(\$host, \$port) = explode(':', \$host); \$port = (int) \$port; }\n";
    $s .= "mysqli_report(MYSQLI_REPORT_OFF);\n";
    $s .= "\$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');\n";
    $s .= "\$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');\n";
    $s .= "\$conn = new mysqli(\$host, \$u, \$p, ems_env('DB_NAME'), \$port);\n";
    $s .= "if (\$conn->connect_errno) { exit(\"تعذّر الاتصال: {\$conn->connect_error}\\n\"); }\n";
    $s .= "\$conn->set_charset('utf8mb4');\n";
    $s .= "\$DB = ems_env('DB_NAME');\n\n";
    $s .= "\$PLAN = " . var_export(xf_plain_plan($byTable), true) . ";\n\n";
    $s .= <<<'PHPBODY'
function xf_has_col($conn, $DB, $t, $c) {
    $st = $conn->prepare("SELECT COUNT(*) c FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if (!$st) { return false; }
    $st->bind_param('sss', $DB, $t, $c);
    $st->execute();
    $n = (int) $st->get_result()->fetch_assoc()['c'];
    $st->close();
    return $n > 0;
}

$added = 0; $already = 0; $failed = array();
foreach ($PLAN as $table => $cols) {
    if (!$conn->query("SELECT 1 FROM `{$table}` LIMIT 0")) {
        $failed[] = "{$table} — لا جدولَ بهذا الاسم"; continue;
    }
    foreach ($cols as $name => $spec) {
        if (xf_has_col($conn, $DB, $table, $name)) { $already++; continue; }
        $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$spec[0]} "
             . "COMMENT '" . $conn->real_escape_string($spec[1]) . "'";
        if ($conn->query($sql)) { $added++; }
        else { $failed[] = "{$table}.{$name} — " . $conn->error; }
    }
}

/* الشاهدُ المُشغَّل: يُقرأ من القاعدةِ بعدَ العمل */
$ok = 0; $bad = array();
foreach ($PLAN as $table => $cols) {
    foreach ($cols as $name => $spec) {
        $r = $conn->query("SELECT IS_NULLABLE n FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($DB) . "'
                              AND TABLE_NAME = '" . $conn->real_escape_string($table) . "'
                              AND COLUMN_NAME = '" . $conn->real_escape_string($name) . "'");
        $x = $r ? $r->fetch_assoc() : null;
        if (!$x)                    { $bad[] = "{$table}.{$name}: غيرُ موجود"; }
        elseif ($x['n'] !== 'YES')  { $bad[] = "{$table}.{$name}: ليس NULL — والاختياريُّ لا يكون إلزاميًّا"; }
        else                        { $ok++; }
    }
}
$tot = 0; foreach ($PLAN as $t => $c) { $tot += count($c); }
echo "════ أعمدةٌ اختياريةٌ (XF-01) ════\n";
echo "  المطلوبُ: {$tot} · أُنشئ: {$added} · كان قائمًا: {$already}\n";
foreach ($failed as $f) { echo "  ⚠ {$f}\n"; }
echo "  المُثبَتُ حيًّا: {$ok}/{$tot} عمودًا NULLable\n";
foreach ($bad as $b) { echo "  ✗ {$b}\n"; }
if ($bad || $failed) { echo "✗ لم تكتمل\n"; exit(1); }
echo "✔ كلُّها اختياريةٌ — ولا حدَّ أدنى تغيَّر\n";
PHPBODY;
    return $s . "\n";
}

/** الخطةُ في شكلٍ بسيطٍ يُحقن في الهجرة: جدول ⇒ عمود ⇒ [DDL, تعليقٌ عربيّ]. */
function xf_plain_plan($byTable)
{
    $out = array();
    foreach ($byTable as $t => $cols) {
        foreach ($cols as $k => $c) {
            $max = isset($c['max']) && $c['max'] ? (int) $c['max'] : 255;
            switch (isset($c['type']) ? $c['type'] : 'text') {
                case 'textarea': $ddl = 'VARCHAR(' . max(255, $max) . ') NULL DEFAULT NULL'; break;
                case 'date':     $ddl = 'DATE NULL DEFAULT NULL'; break;
                case 'int':      $ddl = 'INT NULL DEFAULT NULL'; break;
                default:         $ddl = 'VARCHAR(' . $max . ') NULL DEFAULT NULL';
            }
            $out[$t][$k] = array($ddl, $c['label']);
        }
    }
    return $out;
}
