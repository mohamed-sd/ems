<?php
/**
 * tools/dept_suite_manifest_gen.php — مولِّدُ مانيفستاتِ عُدّةِ فحصِ الإدارات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **يولِّد بياناتٍ لا حكمًا**: لكلِّ مساحةِ عملٍ مانيفستٌ بعمقِ **التصيير**
 *   (عرضٌ + رصدُ تحذيرِ المفسِّر)، مصدرُ أسطحِه `repair01_screen_registry`
 *   حيث `on_disk=1` — **لا اجتهادَ في العضوية**، كما في مانيفستِ المبيعات.
 *
 * ⛔ **ولا يُولَّد `crud` تخمينًا**: حمولةُ النموذجِ تختلف حقلًا حقلًا لكلِّ شاشة،
 *   وتخمينُها يُنتج فشلًا كاذبًا يُفسِد الأساس. فتُترك الخانةُ **مصرَّحًا بها
 *   فارغةً** ويُكتب في رأسِ الملفِّ كيف تُعمَّق — والعمقُ يُضاف شاشةً شاشة.
 *
 * ◆ **ولا يُكتب `must_contain` مخمَّنًا**: علامةٌ خاطئةٌ تقلب سطحًا سليمًا فاشلًا.
 *   والمحرّكُ بلا علامةٍ يفحص: HTTP 200 · وصفرَ تحذيرِ PHP في الجسد — وهو
 *   بالضبطِ ما يكشف الانحدار.
 *
 * ⛔ **ولا يدهس مانيفستًا مؤلَّفًا بيدٍ**: أيُّ ملفٍّ قائمٍ يُتخطّى إلّا بـ`--force`.
 *
 * التشغيل:
 *   php tools/dept_suite_manifest_gen.php            ← يولّد الناقصَ فقط
 *   php tools/dept_suite_manifest_gen.php --only=DEP-05
 *   php tools/dept_suite_manifest_gen.php --force    ← يعيد توليدَ المولَّدِ سلفًا
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('تعذّر الاتصال: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

$ONLY = null; $FORCE = false;
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--only=([\w-]+)$/', $a, $m)) { $ONLY = $m[1]; }
    if ($a === '--force') { $FORCE = true; }
}

/* ═══ ① المساحاتُ وحساباتُها — من خريطةِ الإداراتِ المقيسة ════════════════ */
$mapPath = $ROOT . '/docs/injint01/dept_map.json';
if (!is_file($mapPath)) { exit("⛔ لا خريطةَ إدارات — شغّلْ: php tools/injint01/dept_map.php\n"); }
$MAP = json_decode(file_get_contents($mapPath), true);
if (!is_array($MAP)) { exit("⛔ خريطةُ الإداراتِ غيرُ مقروءة\n"); }

/* الاسمُ اللاتينيُّ للملفِّ — والمحرّكُ يقبل [a-z_] فقط. */
$SLUG = array(
    'DEP-01' => 'sales',       'DEP-02' => 'suppliers',   'DEP-03' => 'financing',
    'DEP-04' => 'fleet',       'DEP-05' => 'finance',     'DEP-06' => 'treasury',
    'DEP-07' => 'hr',          'DEP-08' => 'permissions', 'DEP-09' => 'risk',
    'DEP-10' => 'tickets',     'DEP-11' => 'operations',  'DEP-12' => 'site',
    'DEP-13' => 'workforce',   'DEP-14' => 'maintenance', 'DEP-15' => 'transport',
    'DEP-16' => 'procurement', 'DEP-17' => 'warehouse',   'EX-CEO' => 'exec',
    'EX-DVP' => 'deputy',      'IAF'    => 'audit',       'PLTF'   => 'platform',
    'WS-MY'  => 'myspace',
);

/* ◆ **حساباتٌ بديلةٌ بالدورِ نفسِه**: ثلاثةُ حساباتٍ تختارها خريطةُ الإداراتِ
     ترفض كلمةَ الفحصِ الموحّدة، فيُستعمل بدلَها حسابٌ آخرُ **في المساحةِ نفسِها
     وبالدورِ نفسِه** — فالمقياسُ لا يتغيّر. مقيسٌ بمسبارِ دخولٍ لا بافتراض،
     ويُعاد قياسُه إن تغيّرت الحسابات. */
$USER_OVERRIDE = array(
    'DEP-15' => 'مشرف النقل',
    'DEP-16' => 'مشرف المشتريات',
    'IAF'    => 'مراجع',
);

/* ═══ ② مجموعةُ كلِّ شاشةٍ — من مصدرِ الاسمِ الحاكمِ إن وُجد ═══════════════ */
$GROUP = array();
$g = $conn->query("SELECT m.code, COALESCE(NULLIF(lg.name,''), '') gname
                     FROM modules m
                     LEFT JOIN link_groups lg ON lg.id = m.group_id");
while ($g && ($x = $g->fetch_assoc())) {
    if ($x['gname'] !== '' && !isset($GROUP[$x['code']])) { $GROUP[$x['code']] = $x['gname']; }
}

/* ═══ ③ التوليد ═══════════════════════════════════════════════════════════ */
$outDir = $ROOT . '/tests/dept_suite';
$made = 0; $skipped = 0; $empty = 0;
/* ◆ **وما يُسقَط يُسمّى ولا يختفي**: الإسقاطُ الصامتُ يُخفي عجزَ التغطية. */
$DROP = array('api' => array(), 'admin' => array(), 'missing' => array(), 'moved' => array());
$q = function ($s) { return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), $s) . "'"; };

foreach ($MAP as $key => $ws) {
    $code = isset($ws['workspace']) ? (string) $ws['workspace'] : (string) $key;
    if ($code === '' || $code === '—') { continue; }
    if ($ONLY !== null && $code !== $ONLY) { continue; }
    if (!isset($SLUG[$code])) { echo "  ⚠ لا اسمَ لاتينيًّا للمساحة {$code} — تُخطّى\n"; continue; }
    $slug = $SLUG[$code];
    $path = $outDir . '/manifest_' . $slug . '.php';

    /* ⛔ لا يُدهَس المؤلَّفُ بيد. */
    if (is_file($path) && !$FORCE) { $skipped++; continue; }
    if (is_file($path) && $FORCE && strpos((string) file_get_contents($path), 'مولَّدٌ آليًّا') === false) {
        echo "  ⛔ {$slug}: مؤلَّفٌ بيدٍ — لا يُدهَس ولو بـ--force\n"; $skipped++; continue;
    }

    $user = isset($USER_OVERRIDE[$code])
        ? $USER_OVERRIDE[$code]
        : (isset($ws['user']) ? (string) $ws['user'] : '');
    $ar   = !empty($ws['role_names']) ? implode(' · ', (array) $ws['role_names']) : $code;
    if ($user === '') { echo "  ⚠ {$code}: لا حسابَ حيًّا — تُخطّى\n"; $skipped++; continue; }

    /* الأسطحُ من السجلِّ الحاكم — الموجودةُ على القرصِ وحدَها. */
    $st = $conn->prepare("SELECT route, canonical_label_ar, screen_file
                            FROM repair01_screen_registry
                           WHERE owner_code = ? AND on_disk = 1
                             AND route IS NOT NULL AND route <> ''
                           ORDER BY route");
    $st->bind_param('s', $code);
    $st->execute();
    $res = $st->get_result();
    $screens = array();
    while ($res && ($r = $res->fetch_assoc())) {
        $route = ltrim(str_replace('\\', '/', (string) $r['route']), '/');
        if ($route === '' || substr($route, -4) !== '.php') { continue; }
        if (isset($screens[$route])) { continue; }

        /* ⛔ **ونقطةُ API ليست سطحًا يُصيَّر**: `api/controllers/*.php` تُصادَق
             بـBearer لا بجلسة، فترُدُّ 403 بحقٍّ — وعدُّها فشلًا **أحمرُ كاذب**.
             مقيسٌ: أخرجت `timesheet.php` و`employees.php` فشلًا في المالية والموارد. */
        if (strpos($route, 'api/') === 0) { $DROP['api'][] = $code . ' :: ' . $route; continue; }

        /* ⛔ **وكونسولُ الأدمِن بابُه غيرُ بابِ المستأجر**: `admin/*` له مصادقتُه،
             فحسابُ إدارةٍ يُطرَد إلى الدخولِ أو يُردُّ 403 — وهو **حكمٌ صحيحٌ**
             لا عطبٌ. مقيسٌ: ثمانيةُ أسطحٍ في أربعِ إداراتٍ خرجت فشلًا كاذبًا. */
        if (strpos($route, 'admin/') === 0) { $DROP['admin'][] = $code . ' :: ' . $route; continue; }

        /* ⛔ **و`on_disk=1` لا يعني أنّ الملفَّ عند `route`**: السجلُّ يحمل
             `admin/sec_governance.php` والملفُّ في `main/`. فيُتحقَّق من الوجودِ
             عند المسارِ نفسِه، ويُحاوَل حلُّه بالاسمِ قبلَ الإسقاط. */
        if (!is_file($ROOT . '/' . $route)) {
            $alt = null;
            foreach (glob($ROOT . '/*/' . basename($route)) as $cand) {
                $rel = str_replace('\\', '/', substr($cand, strlen($ROOT) + 1));
                if (strpos($rel, 'admin/') === 0 || strpos($rel, 'storage/') === 0
                    || strpos($rel, 'docs/') === 0 || strpos($rel, 'tools/') === 0) { continue; }
                $alt = $rel; break;
            }
            if ($alt === null) { $DROP['missing'][] = $code . ' :: ' . $route; continue; }
            $DROP['moved'][] = $code . ' :: ' . $route . ' ⇒ ' . $alt;
            $route = $alt;
            if (isset($screens[$route])) { continue; }
        }
        $label = trim((string) $r['canonical_label_ar']);
        if ($label === '') { $label = basename($route, '.php'); }
        $grp = isset($GROUP[$route]) ? $GROUP[$route] : dirname($route);
        if ($grp === '.' || $grp === '') { $grp = 'عام'; }
        $screens[$route] = array('label' => $label, 'group' => $grp);
    }
    $st->close();

    if (!$screens) { echo "  ⚠ {$code}: صفرُ سطحٍ على القرص — لا مانيفست\n"; $empty++; continue; }

    /* ── بناءُ نصِّ الملف ─────────────────────────────────────────────── */
    $n = count($screens);
    $b  = "<?php\n";
    $b .= "/**\n";
    $b .= " * tests/dept_suite/manifest_{$slug}.php — مانيفستُ «{$ar}»  ({$code})\n";
    $b .= " * ═══════════════════════════════════════════════════════════════════════════\n";
    $b .= " * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما\n";
    $b .= " *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.\n";
    $b .= " *\n";
    $b .= " * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='{$code}'`\n";
    $b .= " *   و`on_disk=1` — **{$n} سطحًا**. لا اجتهادَ في العضوية.\n";
    $b .= " *\n";
    $b .= " * ◆ **عمقُ الفحصِ الآن: التصييرُ وحدَه** — لكلِّ سطحٍ يُقاس:\n";
    $b .= " *   ① `HTTP 200` أم ردٌّ بحارسٍ أم خطأُ خادم · ② صفرُ تحذيرِ PHP في الجسد.\n";
    $b .= " *   وهذا يكشف **الانحدارَ** (سطحٌ كان يُصيَّر فصار يُردُّ أو يشتكي) وهو أكثرُ\n";
    $b .= " *   ما يقع بعدَ التعديلات.\n";
    $b .= " *\n";
    $b .= " * ⛔ **ولا `crud` مخمَّنٌ هنا**: حمولةُ كلِّ نموذجٍ تختلف حقلًا حقلًا، وتخمينُها\n";
    $b .= " *   يُنتج فشلًا كاذبًا يُفسِد خطَّ الأساس. فالخانةُ **فارغةٌ مصرَّحٌ بها**.\n";
    $b .= " *\n";
    $b .= " * ◆ **كيف تُعمَّق شاشةٌ** (انسخِ النمطَ من `manifest_sales.php`):\n";
    $b .= " *   ① أضِفْ للسطحِ `'crud' => array('add' => array('post' => array(...),\n";
    $b .= " *      'verify' => array('table' => '...', 'where' => \"...='{MARK}'\")), ...)`.\n";
    $b .= " *   ② أضِفْ جدولَ ذلك السطحِ إلى `sweep` بعمودِ وسمِه النصّيّ — وإلّا\n";
    $b .= " *      بقيت صفوفُ الفحصِ في قاعدتك.\n";
    $b .= " *   ③ وأضِفْ `guard` (اختبارٌ سالبٌ) وإلّا لم تقس أنّ الحارسَ يمنع.\n";
    $b .= " *\n";
    $b .= " * التشغيل: php tests/dept_suite/run.php --dept={$slug} --quick\n";
    $b .= " * الأساس:  php tests/dept_suite/run.php --dept={$slug} --save-baseline\n";
    $b .= " * ═══════════════════════════════════════════════════════════════════════════\n";
    $b .= " */\n\n";
    $b .= "return array(\n\n";
    $b .= "'dept'    => " . $q($slug) . ",\n";
    $b .= "'dept_ar' => " . $q($ar) . ",\n";
    $b .= "'user'    => " . $q($user) . ",\n";
    $b .= "'pass'    => '12345678',\n\n";
    $b .= "// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.\n";
    $b .= "//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.\n";
    $b .= "'sweep'    => array(),\n";
    $b .= "'sweep_by' => array(),\n\n";
    $b .= "'screens' => array(\n";

    $lastGrp = null;
    foreach ($screens as $route => $s) {
        if ($s['group'] !== $lastGrp) {
            $b .= "\n// ── " . $s['group'] . " " . str_repeat('─', max(1, 60 - mb_strlen($s['group']))) . "\n";
            $lastGrp = $s['group'];
        }
        $b .= "array(\n";
        $b .= "  'route' => " . $q($route) . ", 'label' => " . $q($s['label']) . ",\n";
        $b .= "  'group' => " . $q($s['group']) . ",\n";
        $b .= "  'view'  => array(),\n";
        $b .= "),\n";
    }
    $b .= "\n),\n";
    $b .= ");\n";

    file_put_contents($path, $b);
    $made++;
    printf("  ✔ %-26s %-9s %3d سطحًا · الحساب: %s\n", 'manifest_' . $slug . '.php', $code, $n, $user);
}

echo "\nمانيفستاتٌ وُلِّدت: {$made} · تُخطّيت (قائمةٌ أو بلا حساب): {$skipped} · بلا أسطح: {$empty}\n";

/* ═══ ④ ما أُسقط — بالاسمِ والسبب ═════════════════════════════════════════ */
$why = array(
    'api'     => 'نقطةُ API تُصادَق بـBearer لا بجلسة — تردُّ 403 بحقٍّ فلا تُقاس سطحًا',
    'admin'   => 'كونسولُ الأدمِن له مصادقتُه — حسابُ إدارةٍ يُردُّ بحقٍّ فلا يُقاس',
    'missing' => 'مسجَّلٌ `on_disk=1` ولا ملفَّ عند مسارِه ولا نظيرَ له — عطبُ سجلٍّ يُبلَّغ',
    'moved'   => 'مسارُ السجلِّ خطأٌ والملفُّ في مكانٍ آخر — صُحِّح في المانيفست',
);
$anyDrop = false;
foreach ($DROP as $k => $list) {
    if (!$list) { continue; }
    $anyDrop = true;
    printf("\n── %s (%d) — %s\n", $k, count($list), $why[$k]);
    foreach (array_slice($list, 0, 12) as $line) { echo "     {$line}\n"; }
    if (count($list) > 12) { echo "     … و" . (count($list) - 12) . " غيرُها\n"; }
}
if (!$anyDrop) { echo "\nلم يُسقَط سطحٌ واحد.\n"; }

echo "\nالتالي: php tests/dept_suite/run.php --dept=<الاسم> --quick   ثمّ  --save-baseline\n";
