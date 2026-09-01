<?php
/**
 * tools/govui_dept_plan.php — **خُطّةُ إغلاقِ حقولِ مساحةٍ — مقترَحةً لا مُنفَّذة**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما يُؤتمَت وما لا يُؤتمَت**: الخريطةُ (حقلٌ ⇐ عمود) يعرفها المخطَّطُ
 *   (`govui_field_map_probe`)، والتطبيقُ تعرفه الأداةُ (`govui_field_close`).
 *   **والباقي ثلاثةُ قراراتٍ متكرِّرة**: أيُّ جدولٍ يحمل الحبّةَ · أيُمَدُّ أم
 *   يُبنى · وأينَ تُوضع الشبكة. وهذه تُقترَح هنا **بقاعدةٍ معلَنة** ثمَّ
 *   ⛔ **تُراجَع بيدٍ قبل التشغيل** — فاختيارُ الجدولِ حكمُ ملكيّةٍ لا حسابُ درجة.
 *
 * ◆ **القاعدةُ المقترِحة**:
 *   ① سطحٌ فيه `<table id="emsList_…">` ⇒ الشبكةُ محلَّه (لا بطاقةَ تُزاد).
 *   ② وإلّا تُقترَح بطاقةُ سجلٍّ بمعرِّفٍ من اسمِ الجدول.
 *   ③ الجدولُ: أعلى المرشَّحين درجةً إن بلغ **نصفَ** حقولِ الورقةِ المنطبقة
 *      (‏فالتعليقاتُ حينئذٍ من الورقةِ يقينًا) ⇒ **يُمَدّ**؛ وإلّا **يُبنى**
 *      باسمٍ من بادئةِ المساحةِ وسبيكةِ الملفّ.
 *   ⛔ **ولا يُمَدُّ جدولٌ عامٌّ مشتركٌ بدرجةٍ عارضة**: قائمةُ الممنوعِ معلَنةٌ
 *      أدناه، فجدولُ معدّاتٍ لا يصير بيتَ حقولِ سطحٍ ماليّ لأنَّ ستَّ كلماتٍ
 *      تشابهت.
 *
 * التشغيل: php tools/govui_dept_plan.php --unit=DEP-05 --prefix=fin --out=<file.json>
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/lib/rpr02_field_lib.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');

$UNIT = null; $PREFIX = 'gf'; $OUT = null;
foreach ($argv as $a) {
    if (strpos($a, '--unit=') === 0)        { $UNIT = substr($a, 7); }
    elseif (strpos($a, '--prefix=') === 0)  { $PREFIX = substr($a, 9); }
    elseif (strpos($a, '--out=') === 0)     { $OUT = substr($a, 6); }
}
if ($UNIT === null || $OUT === null) { exit("الاستعمال: --unit=<UNIT> --prefix=<p> --out=<file.json>\n"); }

/**
 * **جداولُ البنيةِ التحتيّةِ لا تُمَدُّ بحقولِ سطح** — تشابهُ ستِّ كلماتٍ لا
 * يجعل جدولَ معدّاتٍ بيتًا لحقولِ سطحٍ ماليّ. والقائمةُ **معلَنةٌ لا مخفيّة**.
 */
$FORBID = array('equipments', 'employees', 'users', 'clients', 'suppliers', 'contracts',
                'admin_companies', 'activity_logs', 'modules', 'nav_canonical', 'roles',
                'ems_business_events', 'fin_financial_events', 'admin_audit_log');

/* أعمدةٌ مسمّاةٌ بتعليقاتِها */
$byTable = array();
$q = $conn->query("SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_COMMENT k
                     FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_COMMENT <> ''");
while ($q && $r = $q->fetch_assoc()) { $byTable[$r['t']][] = array('col' => $r['c'], 'label' => $r['k']); }

$exists = array();
$q = $conn->query("SELECT TABLE_NAME t FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
while ($q && $r = $q->fetch_assoc()) { $exists[$r['t']] = 1; }

/* أسطحُ المساحةِ الناقصةُ حقولُها — من قياسِ الأثرِ المثبَّت */
$rows = array();
$st = $conn->prepare("SELECT fm.requirement_id, fm.screen_id, fm.artifact_path,
                             fm.design_applicable, fm.matched, sr.canonical_label_ar, sr.route
                        FROM repair01_field_measure fm
                        JOIN repair01_screen_registry sr ON sr.screen_id = fm.screen_id
                       WHERE fm.unit = ? AND fm.matched < fm.design_applicable
                       ORDER BY (fm.design_applicable - fm.matched) DESC");
$st->bind_param('s', $UNIT); $st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $rows[] = $x; }
if (!$rows) { exit("لا سطحَ ناقصًا في " . $UNIT . " بحسبِ القياسِ المثبَّت.\n"); }

$plan = array();
foreach ($rows as $r0) {
    $req = $r0['requirement_id'];
    $st2 = $conn->prepare("SELECT field_name, field_type FROM repair01_fields WHERE requirement_id = ? ORDER BY id");
    $st2->bind_param('s', $req); $st2->execute();
    $rs2 = $st2->get_result();
    $fields = array();
    while ($y = $rs2->fetch_assoc()) { $fields[] = $y; }
    $st2->close();
    $appl = 0;
    foreach ($fields as $f) { if ($f['field_type'] !== 'AUDIT') { $appl++; } }
    if (!$appl) { continue; }

    /* أفضلُ مرشَّحٍ بدرجتِه */
    $best = ''; $bestN = 0;
    foreach ($byTable as $t => $cols) {
        if (in_array($t, $FORBID, true)) { continue; }
        $bagStr = array(); $bagTok = array();
        foreach ($cols as $c0) {
            $nv = fm_norm($c0['label']);
            if ($nv !== '') { $bagStr[$nv] = 1; }
            foreach (fm_tok($c0['label'], $FM_STOP) as $tk) { $bagTok[$tk] = 1; }
        }
        $n = 0;
        foreach ($fields as $f) {
            if ($f['field_type'] === 'AUDIT') { continue; }
            if (fm_hit(fm_tok($f['field_name'], $FM_STOP), $bagTok, $bagStr,
                       fm_norm($f['field_name'])) !== '') { $n++; }
        }
        if ($n > $bestN) { $bestN = $n; $best = $t; }
    }

    $route = ltrim(str_replace(chr(92), '/', (string) $r0['route']), '/');
    $slug  = preg_replace('~[^a-z0-9_]+~', '_', strtolower(basename($route, '.php')));
    $slug  = preg_replace('~^(' . preg_quote($PREFIX, '~') . '|exec|sup|fin|gov)_~', '', $slug);
    $mk    = false;
    if ($best === '' || $bestN * 2 < $appl) {
        $best = $PREFIX . '_' . $slug;
        $mk   = !isset($exists[$best]);
    }

    /* موضعُ الشبكة: جدولٌ بمعرِّفٍ قائمٍ أوّلًا، وإلّا بطاقةٌ تُقترَح */
    $src = (string) @file_get_contents($ROOT . '/' . $route);
    $gid = ''; $needCard = true;
    if (preg_match('~<table\s+id="(emsList_[a-z0-9_]+)"~i', $src, $m)) { $gid = $m[1]; $needCard = false; }
    if ($gid === '') { $gid = 'emsList_' . $best; }

    $e = array('dept' => $UNIT, 'req' => $req, 'route' => $route, 'table' => $best,
               'anchor' => '<table id="' . $gid . '"',
               'rows' => "ems_w14_guide_rows('" . $best . "')",
               'grid_id' => $gid,
               'empty' => 'لا سطر مسجل بعد في ' . trim((string) $r0['canonical_label_ar']),
               '_score' => $bestN . '/' . $appl, '_card' => $needCard ? 1 : 0);
    if ($mk) { $e['create'] = true; $e['grain'] = trim((string) $r0['canonical_label_ar']); }
    $plan[] = $e;

    printf("%-9s %-46s %-30s %s%s%s\n", $req, $route, $best,
           $mk ? 'يُبنى' : ('يُمَدّ ' . $bestN . '/' . $appl),
           $needCard ? ' · بطاقةٌ تُزاد' : ' · شبكةٌ محلَّ جدولٍ قائم',
           isset($exists[$best]) || $mk ? '' : ' · x جدولٌ غيرُ موجود');
}
file_put_contents($OUT, json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\n  ✔ كُتبت الخُطّةُ المقترَحةُ (" . count($plan) . " سطحًا): " . $OUT . "\n";
echo "  ⛔ راجِعْها قبل التشغيل — اختيارُ الجدولِ حكمُ ملكيّةٍ لا حسابُ درجة.\n";
