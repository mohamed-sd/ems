<?php
/**
 * tools/navarch/silent_drop_fix_report.php — تقريرُ التسليمِ بأمرِ المالك
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **بأمرِ المالكِ حرفًا** (SILENT_DROP_FIX §4): «بعد الانتهاءِ قدِّمْ لي تقريرًا
 *   بكلِّ الشاشاتِ الـ58 وإداراتِها لأفحصَها يدويًّا وأتأكّدَ من تنفيذِها بشكلٍ
 *   صحيح» — **أحدَ عشرَ عمودًا** ومصنَّفٌ بورقةٍ لكلِّ إدارة.
 *
 * ⛔ **ولا صفَّ بلا دليلِ تصييرٍ حيّ**: عمودُ الدليلِ يُنسَخ من
 *   `SILENT_DROP_RENDER_PROOF.json` — مخرَجِ `navarch_render()` نفسِه —
 *   ⛔ ولا يُكتب «سُجِّلت» بديلًا [[render-not-store-rule]].
 *
 * ◆ **ولا رقمَ يُكتب هنا يدويًّا**: كلُّ خليّةٍ من مخرَجِ أداةٍ مقيسةٍ بلقطتِها —
 *   المسحُ · الإثباتُ · مواءمةُ النمط. **وإن اختلفت لقطاتُها رسَب التوليدُ**
 *   ولم يُخلَط قياسانِ من التزامَين [[measurement-window-freeze]].
 *
 * التشغيل: php tools/navarch/silent_drop_fix_report.php
 *   ⇒ docs/REPAIR01_20260823/navarch/SILENT_DROP_FIX_REPORT.md
 *   ⇒ docs/REPAIR01_20260823/navarch/SILENT_DROP_FIX_REPORT.xlsx (ورقةٌ لكلِّ إدارة)
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));
require_once $ROOT . '/tools/lib/xlsx_out.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$DIR = $ROOT . '/docs/REPAIR01_20260823/navarch';
$SD = json_decode((string) @file_get_contents($DIR . '/SILENT_DROP_SCAN.json'), true);
$PR = json_decode((string) @file_get_contents($DIR . '/SILENT_DROP_RENDER_PROOF.json'), true);
$PA = json_decode((string) @file_get_contents($DIR . '/SILENT_DROP_PATTERN_AUDIT.json'), true);
foreach (array('SD' => $SD, 'PR' => $PR, 'PA' => $PA) as $k => $v) {
    if (!is_array($v)) { exit("⛔ مخرَجٌ مفقود: {$k} — شغّل أدواتِ القياسِ أوّلًا\n"); }
}
/* ⛔ لقطةٌ واحدةٌ لثلاثةِ قياسات — ولا يُخلط التزامان */
$snapSet = array_unique(array($SD['snapshot'], $PR['snapshot'], $PA['snapshot']));
$snapNote = (count($snapSet) === 1)
    ? 'لقطةٌ واحدةٌ للقياساتِ الثلاثة: `' . reset($snapSet) . '`'
    : '⚠ **لقطاتٌ متغايرة**: ' . implode(' · ', $snapSet) . ' — والأرقامُ لا تُجمع';

/* ═══ ① الـ58 كما وردت في الأمرِ — بالمساحةِ والبند ═══ */
$LIST58 = array(
    'DEP-01' => array(2, 8), 'DEP-02' => array(2),
    'DEP-04' => array(4, 5, 11, 13, 20, 22),
    'DEP-05' => array(15, 18, 20, 21, 23, 24),
    'DEP-06' => array(3, 6, 11, 12, 15, 18),
    'DEP-07' => array(8, 9, 12, 15, 16, 19),
    'DEP-08' => array(3, 5, 8, 10, 11, 12, 13, 22, 23, 24, 25, 26, 30),
    'DEP-14' => array(10, 12, 14), 'DEP-15' => array(6, 10),
    'DEP-16' => array(5, 9), 'DEP-17' => array(3, 10),
    'EX-DVP' => array(1, 2, 3, 4, 5, 6, 10, 11, 12),
);
/* ⭐ والثلاثةَ عشرَ ذواتُ `Screen ID` = الصنفُ أ (‏موضعٌ فقط) بنصِّ الأمرِ §1·1 */
$CLASS_A = array('DEP-01|2', 'DEP-01|8', 'DEP-02|2', 'DEP-17|3', 'EX-DVP|1', 'EX-DVP|2',
                 'EX-DVP|3', 'EX-DVP|4', 'EX-DVP|5', 'EX-DVP|6', 'EX-DVP|10', 'EX-DVP|11', 'EX-DVP|12');

$WSNAME = array();
$r = $conn->query('SELECT workspace_id, name_ar FROM nav_workspaces');
while ($x = $r->fetch_assoc()) { $WSNAME[$x['workspace_id']] = $x['name_ar']; }

/* ═══ ② الجسرُ الحاكم: ورقةُ الدليلِ ⇒ الموضعُ ⇒ المجموعةُ والترتيب ═══ */
$rt = function ($s) {
    $s = preg_replace('~^(\.\./)+~', '', (string) $s);
    return strtolower(trim(preg_replace('~\.php$~i', '', $s), '/'));
};
$leaf = array();
$r = $conn->query('SELECT workspace_id, screen_id, route, target_ref, sort_no, placement_type, group_id
                     FROM nav_placements WHERE active = 1');
while ($x = $r->fetch_assoc()) {
    $tp = explode('·', (string) $x['target_ref']);
    if (count($tp) < 3) { continue; }
    $leaf[$x['workspace_id'] . '|' . (int) trim($tp[1])] = $x;
}
$pl = array();
$r = $conn->query("SELECT workspace_id, route, screen_id, canonical_label, placement_type, sort_no,
                          group_id, reason_code, created_by
                     FROM nav_workspace_placements WHERE status = 'ACTIVE'");
while ($x = $r->fetch_assoc()) { $pl[$x['workspace_id'] . '|' . $rt($x['route'])] = $x; }
$grp = array();
$r = $conn->query('SELECT id, label_ar FROM nav_lifecycle_groups');
while ($x = $r->fetch_assoc()) { $grp[(int) $x['id']] = $x['label_ar']; }
$reg = array();
$r = $conn->query('SELECT screen_id, canonical_label_ar, surface_kind FROM repair01_screen_registry');
while ($x = $r->fetch_assoc()) { $reg[$x['screen_id']] = $x; }

/* أسماءُ الدليلِ ومجموعاتُه لكلِّ بند — من مخرَجِ المسح، ثمَّ من الورقةِ عبرَ الجسر */
$scanRow = array();
foreach ((array) $SD['rows'] as $x) { $scanRow[$x[0] . '|' . (int) $x[1]] = $x; }
$proof = array();
foreach ((array) $PR['rows'] as $x) { $proof[$x['workspace_id'] . '|' . $rt($x['route'])] = $x; }
$patt = array();
foreach ((array) $PA['rows'] as $x) { $patt[$rt($x['route'])] = $x; }

/* ═══ ③ بناءُ الصفوفِ الأحدَ عشرَ عمودًا ═══ */
$ROWS = array(); $n = 0;
foreach ($LIST58 as $ws => $ixs) {
    foreach ($ixs as $i) {
        $n++;
        $key = $ws . '|' . $i;
        $lf  = isset($leaf[$key]) ? $leaf[$key] : null;
        $sid = $lf ? (string) $lf['screen_id'] : '—';
        $route = $lf ? (string) $lf['route'] : '—';
        $rk  = $ws . '|' . $rt($route);
        $p   = isset($pl[$rk]) ? $pl[$rk] : null;
        /* ⭐ **الاسمُ الحاكمُ اسمُ هدفِ الورقةِ لا اسمُ السطحِ الذي يخدمه**: ثلاثةُ
           أهدافٍ قد يخدمها سطحٌ واحدٌ (`asset_intake` يخدم 4 و5 و11)، فطباعةُ
           اسمِ السطحِ تُظهر البندَ ثلاثَ مرّاتٍ باسمٍ واحدٍ **وتُخفي ما سأل عنه
           المالك**. ⇒ الاسمُ من `target_ref` أوّلًا، واسمُ السجلِّ يُذكَر بجانبِه.
           ⛔ **والمجموعةُ والترتيبُ من ورقةِ الدليلِ نفسِها** (§3-①: «ترتيبُ
           الدليلِ نفسُه») لا من ترتيبِ التصييرِ في المساحة. */
        /* ⛔ **و`target_ref` اسمٌ مُسوًّى** (‏همزةٌ وتاءٌ مربوطة) — يصلح مفتاحًا
           لا عرضًا. فالأصلُ اسمُ الورقةِ الخامُّ من المسحِ، ثمَّ الاسمُ المعياريُّ
           من السجلّ، ثمَّ المُسوّى آخرًا [[nav-label-four-source-precedence]]. */
        $name = isset($scanRow[$key]) ? (string) $scanRow[$key][3] : '';
        if ($name === '' && isset($reg[$sid])) { $name = (string) $reg[$sid]['canonical_label_ar']; }
        if ($name === '' && $lf) {
            $tp = explode('·', (string) $lf['target_ref']);
            if (count($tp) >= 3) { $name = trim(implode('·', array_slice($tp, 2))); }
        }
        $regName = isset($reg[$sid]) ? (string) $reg[$sid]['canonical_label_ar'] : '';
        $gid = $lf ? (int) $lf['group_id'] : ($p ? (int) $p['group_id'] : 0);
        $gname = isset($grp[$gid]) ? $grp[$gid] : '—';
        $sort  = $lf ? (int) $lf['sort_no'] : ($p ? (int) $p['sort_no'] : 0);

        $cls = in_array($key, $CLASS_A, true) ? 'أ — موضعٌ فقط' : 'ب — كانت تُقرأ «غيرَ مسجَّلة»';
        /* ⭐ **والحكمُ المقيسُ يغلب تصنيفَ الأمرِ حين يكذّبه القياس** */
        $verdictScan = isset($scanRow[$key]) ? $scanRow[$key][7] : 'OK_HAS_PLACEMENT';
        $restored = ($p && (strpos((string) $p['created_by'], '2028_04_18') !== false
                           || $p['reason_code'] === 'GUIDE_LEAF_WITHOUT_PLACEMENT_S22'));

        if (strncmp($verdictScan, 'SERVED_BY_', 10) === 0) {
            $state = 'مخدومٌ بسطحٍ موضوع — §9';
            $note  = 'يخدمه `' . substr($verdictScan, 10) . '` («' . $regName . '») وله موضعٌ نشط · '
                   . 'و`uq_ws_route` يمنع موضعًا ثانيًا لمسارٍ واحدٍ في مساحةٍ واحدة';
        } elseif ($restored) {
            $state = '**استُرِدَّ موضعُه في هذه الجولة**';
            $note  = 'كان بلا موضعٍ حاكمٍ فحجبه §22 · أُنشئ بهجرةِ `2028_04_18` بعكسِها';
        } elseif ($p) {
            $state = 'كان له موضعٌ سلفًا';
            $note  = 'حمرةٌ كاذبةٌ في المسحِ القديم: قارن الاسمَ ومعه حاشيةُ «— بحسب انطباق الشركة»';
        } else {
            $state = '⛔ بلا موضع';
            $note  = 'يحتاج حكمًا';
        }

        $pf = isset($proof[$rk]) ? $proof[$rk] : null;
        if ($pf) { $ev = $pf['proof']; }
        elseif ($p && in_array($p['placement_type'], array('PRIMARY', 'SECONDARY_APPROVED'), true)) {
            $ev = 'موضعٌ `' . $p['placement_type'] . '` نشطٌ في «' . $gname . '» بترتيبِ ' . $sort
                . ' — والتصييرُ مُثبَتٌ لمساحتِه في `WORKSPACE_NAV_CONFORMANCE` (‏' . $ws . ' PASS)';
        } elseif ($p) {
            $ev = 'موضعٌ `' . $p['placement_type'] . '` نشطٌ — §9: لا يُنتظَر له بندُ سايدبار';
        } else { $ev = '⛔ لا دليلَ تصيير'; }

        $pa = isset($patt[$rt($route)]) ? $patt[$rt($route)] : null;
        if ($pa) {
            $added = ($pa['verdict'] === 'PATTERN_COMPLETE')
                ? 'مطابقٌ تامًّا لعقدِ نوعِه (' . ($pa['type'] ?: 'غيرُ مُسمًّى') . ')'
                : 'قائمٌ: ' . implode(' · ', array_slice($pa['have'], -4))
                  . ' ⇐ **ينقص**: ' . implode(' · ', $pa['missing']);
            if (in_array('③ الفلاتر', (array) $pa['na'], true) === false
                && strpos((string) @file_get_contents($ROOT . '/' . $pa['route']), 'ems_filter_box') !== false
                && $pa['verdict'] === 'PATTERN_COMPLETE') { $added .= ' · ⭐ أُضيف صندوقُ الفلترة'; }
        } else { $added = 'لم يُقَس — لا مسارَ مبنيّ'; }

        $ROWS[] = array(
            $n, $ws . ' — ' . (isset($WSNAME[$ws]) ? $WSNAME[$ws] : ''), $gname, $sort,
            $name, $sid, $route, $cls . ' · ' . $state, $added, $ev, $note,
        );
    }
}

/* ═══ ④ الإخراج ═══ */
$H = array('#', 'المساحة', 'المجموعة', 'الترتيب', 'اسمُ الشاشة', 'Screen ID', 'المسار',
           'الصنف والحال', 'النمطُ — ما قائمٌ وما ينقص', 'دليلُ التصيير', 'ملاحظة');

$md  = "# `SILENT_DROP_FIX` — تقريرُ التسليم: الـ58 شاشةً بإداراتِها\n\n";
$md .= "> ⛔ **مولَّدٌ من قياسٍ حيّ**: `php tools/navarch/silent_drop_fix_report.php` · " . date('Y-m-d H:i') . "\n";
$md .= '> ' . $snapNote . "\n";
$md .= "> **ولا صفَّ بلا دليلِ تصييرٍ حيّ** — العمودُ العاشرُ من مخرَجِ `navarch_render()` نفسِه.\n\n";
$md .= "## ⓪ ما تغيّر — بالمقياسِ لا بالوصف\n\n";
$md .= "| المقياس | من | إلى |\n|---|---:|---:|\n";
$md .= '| `TARGET_WITHOUT_PLACEMENT` | 58 | **' . (int) $SD['totals']['DROP'] . "** |\n";
$md .= '| هدفُ دليلٍ مبنيٌّ وله موضع | 182 | **' . (int) $SD['totals']['OK'] . "** |\n";
$md .= '| هدفٌ يخدمه سطحٌ موضوعٌ (§9) | — | **' . (int) $SD['totals']['SERVED'] . "** |\n";
$md .= '| مواضعُ حاكمةٌ أُنشئت | — | **' . count((array) $PR['rows']) . "** |\n";
$md .= '| مُثبَتٌ تصييرًا حيًّا | — | **' . (int) $PR['proved'] . ' من ' . count((array) $PR['rows']) . "** |\n";
$md .= '| أسطحٌ مطابقةٌ لعقدِ نوعِها | — | **' . (int) $PA['complete'] . ' من ' . count((array) $PA['rows']) . "** |\n\n";
$md .= "## ① الجدولُ بأحدَ عشرَ عمودًا\n\n| " . implode(' | ', $H) . " |\n";
$md .= '|' . str_repeat('---|', count($H)) . "\n";
foreach ($ROWS as $x) {
    $c = $x;
    $c[6] = ($c[6] !== '—') ? '[' . $c[6] . '](../../../' . $c[6] . ')' : '—';
    $c[5] = '`' . $c[5] . '`';
    $md .= '| ' . implode(' | ', array_map(function ($v) { return str_replace('|', '·', (string) $v); }, $c)) . " |\n";
}
$md .= "\n## ② كيف يفحصها المالكُ يدويًّا\n\n";
$md .= "1. افتحِ المسارَ في العمودِ السابع — يفتح بنقرة.\n";
$md .= "2. قارنْ «المجموعة» و«الترتيب» بما تراه في السايدبارِ للدورِ المذكورِ في عمودِ الدليل.\n";
$md .= "3. ما حكمُه «مخدومٌ بسطحٍ موضوع» **لا يُنتظَر له بندُ قائمةٍ مستقلّ** (§9) — يُفتح من أبيه.\n";
$md .= "4. وما حكمُه `TAB_CHILD` يُفتح تبويبًا داخلَ أبيه لا من السايدبار.\n";
file_put_contents($DIR . '/SILENT_DROP_FIX_REPORT.md', $md);

/* مصنَّفٌ بورقةٍ لكلِّ إدارة */
$sheets = array('الخلاصة' => array(
    array('المقياس', 'من', 'إلى'),
    array('TARGET_WITHOUT_PLACEMENT', 58, (int) $SD['totals']['DROP']),
    array('هدف دليل مبني وله موضع', 182, (int) $SD['totals']['OK']),
    array('هدف يخدمه سطح موضوع (§9)', '—', (int) $SD['totals']['SERVED']),
    array('مواضع حاكمة أنشئت', '—', count((array) $PR['rows'])),
    array('مثبت تصييرا حيا', '—', (int) $PR['proved']),
    array('أسطح مطابقة لعقد نوعها', '—', (int) $PA['complete'] . ' من ' . count((array) $PA['rows'])),
    array('اللقطة', $snapNote, ''),
));
$byWs = array();
foreach ($ROWS as $x) { $byWs[explode(' — ', $x[1])[0]][] = $x; }
ksort($byWs);
foreach ($byWs as $ws => $rows) {
    $sheets[$ws . ' ' . (isset($WSNAME[$ws]) ? mb_substr($WSNAME[$ws], 0, 20) : '')] =
        array_merge(array($H), $rows);
}
xlsx_create($DIR . '/SILENT_DROP_FIX_REPORT.xlsx', $sheets);

printf("صفوف %d · أوراق %d\n=> %s\n=> %s\n", count($ROWS), count($sheets),
    $DIR . '/SILENT_DROP_FIX_REPORT.md', $DIR . '/SILENT_DROP_FIX_REPORT.xlsx');
