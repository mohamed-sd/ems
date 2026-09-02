<?php
/**
 * tools/legacy_extra_inventory.php — جردُ الروابطِ الإرثيّةِ في كلِّ مساحةٍ · ورقةٌ لكلِّ إدارة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ سؤالُ المالك: «أعِدَّ قائمةً بكلِّ الإرثِ القديمِ في كلِّ الإداراتِ — ورقةٌ لكلِّ إدارة».
 *   والإرثُ هنا: **بندٌ مُصيَّرٌ في سايدبارِ الدورِ ولا يقابله هدفٌ في ورقةِ الدليل**
 *   — وهو مقياسُ §20 `UNEXPLAINED_EXTRA_MENU_ITEM` الذي لم يُقَس بعد.
 *
 * ◆ [[render-not-store-rule]]: المقامُ **الشجرةُ المُصيَّرةُ حرفًا** بعمليّةٍ نقيّةٍ
 *   لكلِّ دور (`tools/lib/render_role_cli.php`) — ⛔ لا صفوفُ جدول.
 *
 * ◆ **والمطابقةُ بالمسارِ أوّلًا ثمَّ بالاسم**: البندُ يُعَدُّ هدفَ دليلٍ إن طابق
 *   `route` موضعًا في `nav_placements` لمساحةِ الدور، أو طابق اسمُه اسمَ هدفٍ في
 *   ورقةِ الإدارة — فالاسمُ المعتمَدُ قد يغاير اسمَ الدليل
 *   [[nav-label-four-source-precedence]]، والمسارُ هويّةٌ أثبت.
 *
 * ◆ **وأربعةُ أصنافٍ لا صنفان** — فالزائدُ ليس كلُّه إرثًا:
 *   `ANCHOR`    مرساةُ الدستورِ §6 — «الرئيسية» و«المراسلات»: أوّلُ ما يُرى في
 *               كلِّ سايدبار، **مقيسةٌ في 18 مساحةً من 18** فهي منصّةٌ لا إرث.
 *   `PERSONAL`  بندُ مساحةِ عملي (ورقة WS-MY) — يظهر لكلِّ دورٍ بحكمٍ قائم.
 *   `SHARED`    مسارٌ هدفُ دليلٍ في مساحةٍ أخرى — استعارةٌ مشروعةٌ لا إرث.
 *   `LEGACY`    ما لا هذا ولا ذاك — **وهو المطلوبُ حسمُه**.
 *
 * ◆ ⛔ **وعدُّ المرساةِ إرثًا أخضرُ كاذبٌ معكوس**: يضخّم الرقمَ بما لا يُحسَم
 *   — فالمرساتان بنصِّ الدستورِ لا تُخفيان ولا تُضافان إلى ورقةِ إدارة.
 *
 * التشغيل: php tools/legacy_extra_inventory.php
 *   ⇒ docs/REPAIR01_20260823/LEGACY_EXTRA_INVENTORY.xlsx  (ورقةٌ لكلِّ مساحة)
 *   ⇒ docs/REPAIR01_20260823/LEGACY_EXTRA_INVENTORY.md    (الخلاصة)
 */

error_reporting(E_ALL & ~E_DEPRECATED);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/rpr02a_guide.php';
require_once $ROOT . '/tools/lib/xlsx_out.php';

ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$nz = 'rpr02a_nz';

/* مرساتا الدستورِ §6 — مقيستان في 18 مساحةٍ من 18 */
$ANCHOR = array('main/role_board' => 1, 'chats/index' => 1);
$rt = function ($s) {
    $s = preg_replace('~^(\.\./)+~', '', (string) $s);
    $s = preg_replace('~[?#].*$~', '', $s);
    return strtolower(trim(preg_replace('~\.php$~i', '', $s), '/'));
};

/* ═══ ① أهدافُ الدليلِ لكلِّ مساحةٍ — بالمسارِ وبالاسم ═══ */
$byWsRoute = array();   /* ws ⇒ route ⇒ [group, sort, target_ref] */
$byWsName  = array();   /* ws ⇒ nz(label) ⇒ 1 */
$routeAnyWs = array();  /* route ⇒ ws المالكةُ الأولى (للاستعارة) */
$r = $conn->query("SELECT p.workspace_id, p.route, p.target_ref, p.sort_no, g.label_ar
                     FROM nav_placements p
                     LEFT JOIN nav_lifecycle_groups g ON g.id = p.group_id
                    WHERE p.active = 1");
while ($x = $r->fetch_assoc()) {
    $ws = (string) $x['workspace_id'];
    if ((string) $x['route'] !== '') {
        $k = $rt($x['route']);
        $byWsRoute[$ws][$k] = array($x['label_ar'], $x['sort_no'], $x['target_ref']);
        if (!isset($routeAnyWs[$k])) { $routeAnyWs[$k] = $ws; }
    }
    /* اسمُ الهدفِ من `target_ref` بصيغةِ DEP-xx·n·الاسم */
    $tr = (string) $x['target_ref'];
    if ($tr !== '' && preg_match('~·\s*\d+\s*·\s*(.+)$~u', $tr, $m)) {
        $byWsName[$ws][$nz($m[1])] = 1;
    }
}

/* ═══ ② المساحاتُ وأدوارُها ═══ */
$wsRole = array(); $wsName = array();
$r = $conn->query("SELECT w.workspace_id, w.name_ar, w.kind, wr.role_id, ro.name rname
                     FROM nav_workspaces w
                     LEFT JOIN nav_ws_roles wr ON wr.workspace_id = w.workspace_id AND wr.binding = 'PRIMARY'
                     LEFT JOIN roles ro ON ro.id = wr.role_id
                    WHERE w.active = 1 ORDER BY w.workspace_id");
while ($x = $r->fetch_assoc()) {
    $wsName[$x['workspace_id']] = $x['name_ar'];
    if ($x['role_id'] !== null) { $wsRole[$x['workspace_id']] = array((int) $x['role_id'], $x['rname']); }
}

/* ═══ ③ التصييرُ النقيُّ ═══ */
function render_role_pos($ROOT, $rid)
{
    $out = array();
    @exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php') . ' ' . (int) $rid . ' 2>NUL', $out);
    $j = json_decode(implode("\n", $out), true);
    return (is_array($j) && isset($j['positions'])) ? $j['positions'] : null;
}

/* ═══ ④ الفرزُ لكلِّ مساحة ═══ */
$sheets = array(); $sum = array(); $grand = array('T' => 0, 'G' => 0, 'A' => 0, 'P' => 0, 'S' => 0, 'L' => 0);
$HDR = array('#', 'رأسُ الطيِّ المعروض', 'اسمُ الرابطِ المعروض', 'المسار',
             'الصنف', 'مساحةُ الهدفِ الأصليّة', 'الحكمُ المقترَح', 'قرارُ المالك');

foreach ($wsRole as $ws => $role) {
    list($rid, $rname) = $role;
    $pos = render_role_pos($ROOT, $rid);
    if ($pos === null) { continue; }
    $rows = array(); $i = 0;
    $cntG = 0; $cntA = 0; $cntP = 0; $cntS = 0; $cntL = 0;
    foreach ($pos as $p) {
        $route = $rt($p['h']);
        $lbl   = (string) $p['l'];
        $head  = (string) $p['g'];
        /* هدفُ دليلٍ لهذه المساحة؟ */
        if (isset($byWsRoute[$ws][$route]) || isset($byWsName[$ws][$nz($lbl)])) { $cntG++; continue; }
        /* مرساةُ الدستور؟ */
        if (isset($ANCHOR[$route])) {
            $cntA++;
            $rows[] = array(++$i, $head, $lbl, $route, 'ANCHOR', 'المنصّة',
                'يبقى — مرساةُ الدستورِ §6: أوّلُ ما يُرى في كلِّ سايدبار', '');
            continue;
        }
        /* بندُ مساحةِ عملي؟ */
        if (isset($byWsRoute['WS-MY'][$route])) {
            $cntP++;
            $rows[] = array(++$i, $head, $lbl, $route, 'PERSONAL', 'WS-MY',
                'يبقى — بندٌ شخصيٌّ يظهر لكلِّ دورٍ بحكمٍ قائم', '');
            continue;
        }
        /* هدفُ دليلٍ في مساحةٍ أخرى ⇒ استعارة */
        if (isset($routeAnyWs[$route])) {
            $own = $routeAnyWs[$route]; $cntS++;
            $rows[] = array(++$i, $head, $lbl, $route, 'SHARED', $own . ' — ' . (isset($wsName[$own]) ? $wsName[$own] : ''),
                'يُراجَع — شاشةُ إدارةٍ أخرى تظهر هنا: أتُبقى استعارةً أم تُخفى؟', '');
            continue;
        }
        $cntL++;
        $rows[] = array(++$i, $head, $lbl, $route, 'LEGACY', '—',
            'يُحسَم — لا هدفَ لها في أيِّ ورقة: تُضاف إلى الدليل أم تُخفى بحكم؟', '');
    }
    $tot = count($pos);
    $sum[$ws] = array($wsName[$ws], $rid . ' · ' . $rname, $tot, $cntG, $cntA, $cntP, $cntS, $cntL);
    $grand['T'] += $tot; $grand['G'] += $cntG; $grand['A'] += $cntA;
    $grand['P'] += $cntP; $grand['S'] += $cntS; $grand['L'] += $cntL;
    if ($rows) {
        array_unshift($rows, $HDR);
        $sheets[$ws . ' ' . mb_substr($wsName[$ws], 0, 20)] = $rows;
    }
}

/* ═══ ⑤ ورقةُ الخلاصةِ أوّلًا ═══ */
$idx = array(array('المساحة', 'الاسم', 'الدورُ المُصيَّر', 'بنودٌ مُصيَّرة',
                   'هدفُ دليل', 'مرساة', 'شخصيّ', 'مستعار', 'إرثٌ يُحسَم'));
foreach ($sum as $ws => $s) {
    $idx[] = array($ws, $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7]);
}
$idx[] = array('—', 'الإجمالي', '—', $grand['T'], $grand['G'], $grand['A'], $grand['P'], $grand['S'], $grand['L']);
$sheets = array('00 الخلاصة' => $idx) + $sheets;

$xlsx = $ROOT . '/docs/REPAIR01_20260823/LEGACY_EXTRA_INVENTORY.xlsx';
xlsx_create($xlsx, $sheets);

/* ═══ ⑥ خلاصةٌ نصّيّة ═══ */
$snap = trim(shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));
$md  = "# جردُ الروابطِ الزائدةِ على الدليل — كلُّ مساحةٍ بورقتِها\n\n";
$md .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/legacy_extra_inventory.php` @ `" . $snap . "` · " . date('Y-m-d H:i') . "\n";
$md .= "> **المقامُ الشجرةُ المُصيَّرةُ حرفًا** بعمليّةٍ نقيّةٍ لكلِّ دور — لا صفوفُ جدول.\n";
$md .= "> **والمصنَّف**: [`LEGACY_EXTRA_INVENTORY.xlsx`](LEGACY_EXTRA_INVENTORY.xlsx) — ورقةٌ لكلِّ مساحةٍ فيها زائد.\n\n";
$md .= "## الأصنافُ الأربعة\n\n";
$md .= "| الصنف | ما هو | الحكم |\n|---|---|---|\n";
$md .= "| `ANCHOR` | **مرساتا الدستورِ §6** — الرئيسية والمراسلات | **تبقى** — مقيستان في 18 مساحةً من 18 |\n";
$md .= "| `PERSONAL` | بندُ **مساحة عملي** (ورقة `WS-MY`) | **يبقى** — يظهر لكلِّ دورٍ بحكمٍ قائم |\n";
$md .= "| `SHARED` | مسارٌ **هدفُ دليلٍ في مساحةٍ أخرى** | **يُراجَع** — استعارةٌ قد تكون مشروعة |\n";
$md .= "| `LEGACY` | **لا هدفَ له في أيِّ ورقة** | ⭐ **يُحسَم** — يُضاف إلى الدليل أم يُخفى بحكم |\n\n";
$md .= "## الخلاصة\n\n";
$md .= "| المساحة | الاسم | الدور | مُصيَّر | هدفُ دليل | مرساة | شخصيّ | مستعار | **إرثٌ يُحسَم** |\n";
$md .= "|---|---|---|---:|---:|---:|---:|---:|---:|\n";
foreach ($sum as $ws => $s) {
    $md .= '| `' . $ws . '` | ' . $s[0] . ' | ' . $s[1] . ' | ' . $s[2] . ' | ' . $s[3]
         . ' | ' . $s[4] . ' | ' . $s[5] . ' | ' . $s[6] . ' | **' . $s[7] . "** |\n";
}
$md .= '| — | **الإجمالي** | — | **' . $grand['T'] . '** | **' . $grand['G'] . '** | **' . $grand['A']
     . '** | **' . $grand['P'] . '** | **' . $grand['S'] . '** | **' . $grand['L'] . "** |\n\n";
$md .= "⛔ **ولا يُحذف رابطٌ بلا حكم** — المقياسُ `UNEXPLAINED_EXTRA_MENU_ITEM = 0` يعني\n";
$md .= "**صفرَ رابطٍ زائدٍ بلا حكمٍ مكتوب**، لا صفرَ رابطٍ زائد.\n";
file_put_contents($ROOT . '/docs/REPAIR01_20260823/LEGACY_EXTRA_INVENTORY.md', $md);

printf("مساحات %d · مُصيَّر %d · هدفُ دليل %d · مرساة %d · شخصيّ %d · مستعار %d · إرث %d\n=> %s\n",
    count($sum), $grand['T'], $grand['G'], $grand['A'], $grand['P'], $grand['S'], $grand['L'], $xlsx);
