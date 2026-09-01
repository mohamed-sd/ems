<?php
/**
 * tools/navarch/silent_drop_sidebar_report.php — أين تظهر الـ58 في السايدبار؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **سؤالُ المالكِ حرفًا**: «أين تقريرُ بناءِ ووضعِ الشاشاتِ الـ58 في السايدبار».
 *   والتقريرُ السابقُ يجيب عن **الموضعِ في السجل**؛ وهذا يجيب عن **الظهورِ في
 *   الشاشة** — وهما سؤالان لا واحد [[render-not-store-rule]].
 *
 * ◆ **والمقامُ التصييرُ الحيُّ**: يُستدعى `navarch_render()` نفسُه لكلِّ مساحةٍ
 *   بدورِها، ثمَّ يُبحَث عن مسارِ كلِّ هدفٍ في الشجرةِ الناتجة — ⛔ ولا يُقرأ
 *   جدولٌ ويُسمَّى ذلك ظهورًا.
 *
 * ◆ **وثلاثةُ مِسمارٍ لا مِسمارٌ واحد** (§9 · §10 · §11): بندُ الدورةِ في
 *   السايدبار · والشخصيُّ في «مساحتي» · والتبويبُ **داخلَ أبيه**. فهدفٌ حكمُه
 *   `TAB_CHILD` أو `DIRECT_ONLY` أو `EXECUTIVE_PROJECTION` **لا يُنتظَر له بندُ
 *   سايدبارٍ أصلًا**، وظهورُه في أبيه هو الصحيحُ لا الناقص.
 *
 * التشغيل: php tools/navarch/silent_drop_sidebar_report.php
 *   ⇒ docs/REPAIR01_20260823/navarch/SILENT_DROP_SIDEBAR_REPORT.md + .xlsx
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
require_once $ROOT . '/includes/navarch_renderer.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

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

$WS = array();
$r = $conn->query('SELECT workspace_id, name_ar FROM nav_workspaces');
while ($x = $r->fetch_assoc()) { $WS[$x['workspace_id']] = $x['name_ar']; }

/* الدورُ المُصيِّرُ لكلِّ مساحةٍ — مربوطًا، وإلّا فأوسعُ دورٍ مصرَّحٍ بمساراتِها */
$wsRole = array(); $wsWhy = array();
$r = $conn->query("SELECT workspace_id, role_id, binding FROM nav_ws_roles
                    ORDER BY (binding = 'PRIMARY') DESC, role_id");
while ($x = $r->fetch_assoc()) {
    if (isset($wsRole[$x['workspace_id']])) { continue; }
    $wsRole[$x['workspace_id']] = (int) $x['role_id'];
    $wsWhy[$x['workspace_id']] = 'دورٌ مربوطٌ (' . $x['binding'] . ')';
}
$authByRole = array();
$r = $conn->query('SELECT role_id, route FROM nav_items WHERE active = 1');
while ($x = $r->fetch_assoc()) { $authByRole[(int) $x['role_id']][navarch_norm_route($x['route'])] = true; }
$plByWs = array();
$r = $conn->query("SELECT workspace_id, route FROM nav_workspace_placements WHERE status = 'ACTIVE'");
while ($x = $r->fetch_assoc()) { $plByWs[$x['workspace_id']][] = navarch_norm_route($x['route']); }
foreach ($plByWs as $w => $routes) {
    if (isset($wsRole[$w])) { continue; }
    $best = 0; $bestN = -1;
    foreach ($authByRole as $rid => $set) {
        $n = 0;
        foreach ($routes as $rt) { if (isset($set[$rt])) { $n++; } }
        if ($n > $bestN) { $bestN = $n; $best = (int) $rid; }
    }
    if ($bestN > 0) {
        $wsRole[$w] = $best;
        $wsWhy[$w] = '⚠ لا دورَ مربوطٌ لها — قِيس بالدورِ ' . $best . ' المصرَّحِ بـ'
                   . $bestN . ' من ' . count($routes) . ' مسارًا';
    }
}

/* ═══ ② التصييرُ الحيُّ لكلِّ مساحةٍ مرّةً واحدة ═══ */
$tree = array();
foreach (array_keys($LIST58) as $w) {
    $rid = isset($wsRole[$w]) ? $wsRole[$w] : 0;
    $tree[$w] = $rid > 0 ? navarch_render($conn, $w, $rid, array('include_shell' => true)) : null;
}

/* ═══ ③ الجسرُ: بندُ الورقةِ ⇒ المسارُ والصنف ═══ */
$leaf = array();
$r = $conn->query('SELECT workspace_id, screen_id, route, target_ref, placement_type, sort_no, group_id
                     FROM nav_placements WHERE active = 1');
while ($x = $r->fetch_assoc()) {
    $tp = explode('·', (string) $x['target_ref']);
    if (count($tp) < 3) { continue; }
    $leaf[$x['workspace_id'] . '|' . (int) trim($tp[1])] = $x;
}
$plType = array();
$r = $conn->query("SELECT workspace_id, route, placement_type, canonical_label, group_id, sort_no
                     FROM nav_workspace_placements WHERE status = 'ACTIVE'");
while ($x = $r->fetch_assoc()) { $plType[$x['workspace_id'] . '|' . navarch_norm_route($x['route'])] = $x; }
$scan = array();
$SD = json_decode((string) @file_get_contents($ROOT . '/docs/REPAIR01_20260823/navarch/SILENT_DROP_SCAN.json'), true);
foreach ((array) (isset($SD['rows']) ? $SD['rows'] : array()) as $x) { $scan[$x[0] . '|' . (int) $x[1]] = $x; }
$reg = array();
$r = $conn->query('SELECT screen_id, canonical_label_ar FROM repair01_screen_registry');
while ($x = $r->fetch_assoc()) { $reg[$x['screen_id']] = $x['canonical_label_ar']; }

/* ═══ ④ الحكمُ لكلِّ هدفٍ: أيظهر في السايدبار؟ ═══ */
$ROWS = array(); $n = 0; $cnt = array();
foreach ($LIST58 as $ws => $ixs) {
    foreach ($ixs as $i) {
        $n++;
        $k = $ws . '|' . $i;
        $lf = isset($leaf[$k]) ? $leaf[$k] : null;
        $route = $lf ? navarch_norm_route($lf['route']) : '';
        $sid = $lf ? (string) $lf['screen_id'] : '—';
        $name = isset($scan[$k]) ? (string) $scan[$k][3]
              : (isset($reg[$sid]) ? $reg[$sid] : '');
        $R = isset($tree[$ws]) ? $tree[$ws] : null;
        $pt = isset($plType[$ws . '|' . $route]) ? (string) $plType[$ws . '|' . $route]['placement_type'] : '—';

        $where = ''; $grp = '—'; $pos = ''; $verdict = ''; $why = '';
        if ($R !== null) {
            foreach ($R['groups'] as $g) {
                foreach ($g['items'] as $j => $it) {
                    if ($it['route'] !== $route) { continue; }
                    $verdict = '✅ يظهر في السايدبار'; $where = 'سايدبارُ ' . $ws;
                    $grp = $g['label']; $pos = ($j + 1) . ' من ' . count($g['items']);
                    $why = 'بندُ دورةٍ `' . $it['placement_type'] . '` — §9';
                }
            }
            if ($verdict === '') {
                foreach ($R['personal'] as $j => $it) {
                    if ($it['route'] !== $route) { continue; }
                    $verdict = '✅ يظهر في «مساحتي»'; $where = 'مِسمارُ مساحتي';
                    $grp = (string) $it['group']; $pos = (string) ($j + 1);
                    $why = 'بندٌ شخصيٌّ — §11: خارجَ مقامِ دورةِ الإدارة';
                }
            }
            if ($verdict === '') {
                foreach ($R['blocked'] as $b) {
                    if ($b['route'] !== $route) { continue; }
                    if ($b['why'] === 'NOT_A_SIDEBAR_TYPE') {
                        $verdict = '⬜ لا يظهر — ولا يُنتظَر';
                        $where = 'يُفتح من أبيه أو بالرابط';
                        $why = '§9: صنفُه `' . $b['type'] . '` **لا يدخل السايدبارَ بحكمِ نوعِه**';
                    } elseif ($b['why'] === 'NO_PERMISSION') {
                        $verdict = '⬜ لا يظهر — ولا يُنتظَر';
                        $where = 'يُفتح من أبيه تبويبًا';
                        $why = 'تبويبٌ تابعٌ (`' . $b['type'] . '`) — ولا صفَّ تفويضٍ له في `nav_items`';
                    } else {
                        $verdict = '⛔ محجوب'; $why = 'حجبُ المُصيِّر: ' . $b['why'];
                    }
                }
            }
        }
        if ($verdict === '') {
            /* لا أثرَ في شجرةِ مساحتِه — أهو هدفٌ يخدمه سطحٌ موضوعٌ في مكانِه؟ */
            $v = isset($scan[$k]) ? (string) $scan[$k][7] : '';
            if (strncmp($v, 'SERVED_BY_', 10) === 0) {
                $srv = substr($v, 10);
                $sname = isset($reg[$srv]) ? $reg[$srv] : $srv;
                $verdict = '🔗 يظهر باسمِ السطحِ الذي يخدمه';
                $where = '«' . $sname . '»';
                $why = '§9: هدفٌ يخدمه سطحٌ موضوعٌ · و`uq_ws_route` يمنع بندًا ثانيًا لمسارٍ واحد';
                if (isset($plType[$ws . '|' . $route]) && $R !== null) {
                    foreach ($R['groups'] as $g) {
                        foreach ($g['items'] as $j => $it) {
                            if ($it['route'] !== $route) { continue; }
                            $grp = $g['label']; $pos = ($j + 1) . ' من ' . count($g['items']);
                        }
                    }
                }
            } else {
                $verdict = '⛔ لا أثرَ له'; $why = 'لا صفَّ موضعٍ ولا حجبَ مسمّى';
            }
        }
        $cnt[$verdict] = (isset($cnt[$verdict]) ? $cnt[$verdict] : 0) + 1;
        $ROWS[] = array($n, $ws . ' — ' . (isset($WS[$ws]) ? $WS[$ws] : ''), $name, $sid,
                        $route !== '' ? $route . '.php' : '—', $pt, $verdict, $where, $grp, $pos, $why);
    }
}

/* ═══ ⑤ الإخراج ═══ */
$H = array('#', 'المساحة', 'اسمُ الشاشة', 'Screen ID', 'المسار', 'صنفُ الموضع',
           'أيظهر في السايدبار؟', 'أين', 'المجموعة', 'الترتيب', 'الحكمُ ولماذا');
$snap = trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));

$md  = "# الـ58 في السايدبار — **بتصييرٍ حيٍّ لا بقراءةِ جدول**\n\n";
$md .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/navarch/silent_drop_sidebar_report.php` @ `" . $snap . "` · " . date('Y-m-d H:i') . "\n";
$md .= "> **المُصيِّرُ** `includes/navarch_renderer.php::navarch_render` — هو نفسُه الذي يرسم سايدبارَ المستخدم.\n\n";
$md .= "## ⓪ الخلاصة\n\n| الحكم | العدد |\n|---|---:|\n";
arsort($cnt);
foreach ($cnt as $k => $v) { $md .= '| ' . $k . ' | **' . $v . "** |\n"; }
$md .= '| **الإجمالي** | **' . count($ROWS) . "** |\n\n";
$md .= "**وثلاثةُ مِسمارٍ لا مِسمارٌ واحد** (§9 · §10 · §11): بندُ الدورةِ في السايدبار ·\n";
$md .= "والشخصيُّ في «مساحتي» · والتبويبُ **داخلَ أبيه**. فما حكمُه «لا يظهر — ولا يُنتظَر»\n";
$md .= "**ليس نقصًا**: صنفُه في ورقةِ الدليلِ نفسِها يمنعه من أن يكون بندَ قائمة.\n\n";

$md .= "## ① الدورُ الذي قِيس به كلُّ مساحة\n\n| المساحة | الدور | الحكم |\n|---|---:|---|\n";
foreach ($LIST58 as $ws => $_) {
    $md .= '| `' . $ws . '` | ' . (isset($wsRole[$ws]) ? $wsRole[$ws] : '—') . ' | '
         . (isset($wsWhy[$ws]) ? $wsWhy[$ws] : '⛔ لا دورَ يُصيِّرها') . " |\n";
}

$md .= "\n## ② الجدول\n\n| " . implode(' | ', $H) . " |\n" . '|' . str_repeat('---|', count($H)) . "\n";
foreach ($ROWS as $x) {
    $c = $x;
    $c[4] = ($c[4] !== '—') ? '[' . $c[4] . '](../../../' . $c[4] . ')' : '—';
    $c[3] = '`' . $c[3] . '`';
    $md .= '| ' . implode(' | ', array_map(function ($v) { return str_replace('|', '·', (string) $v); }, $c)) . " |\n";
}

$dir = $ROOT . '/docs/REPAIR01_20260823/navarch';
file_put_contents($dir . '/SILENT_DROP_SIDEBAR_REPORT.md', $md);

$sheets = array('الخلاصة' => array_merge(array(array('الحكم', 'العدد')),
    array_map(function ($k, $v) { return array($k, $v); }, array_keys($cnt), array_values($cnt))));
$byWs = array();
foreach ($ROWS as $x) { $byWs[explode(' — ', $x[1])[0]][] = $x; }
ksort($byWs);
foreach ($byWs as $ws => $rs) {
    $sheets[$ws . ' ' . (isset($WS[$ws]) ? mb_substr($WS[$ws], 0, 20) : '')] = array_merge(array($H), $rs);
}
xlsx_create($dir . '/SILENT_DROP_SIDEBAR_REPORT.xlsx', $sheets);

echo "══ الـ58 في السايدبار — تصييرٌ حيّ ══\n";
foreach ($cnt as $k => $v) { printf("  %-34s %2d\n", $k, $v); }
printf("\n  الإجمالي %d\n  => %s\n  => %s\n", count($ROWS),
    $dir . '/SILENT_DROP_SIDEBAR_REPORT.md', $dir . '/SILENT_DROP_SIDEBAR_REPORT.xlsx');
