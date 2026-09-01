<?php
/**
 * tools/govui_conformance_matrix.php — مصفوفةُ المطابقةِ بسبعةَ عشرَ عمودًا (§18)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **بنصِّ §18**: صفٌّ لكلِّ هدفٍ بأعمدةِ `Target_ID · Workspace · Target_Group ·
 *   Actual_Group · Target_Order · Actual_Order · Canonical_Label · Actual_Label ·
 *   Surface_Type · Target_Role · Actual_Role · Screen_ID · Build_Status ·
 *   Grain_Status · Field_Status · State_Model_Status · Final_Verdict`.
 * ◆ **والأحكامُ الأحدَ عشرَ حصرًا** — لا حكمَ خارجَها:
 *   `EXACT_MATCH` · `WRONG_GROUP` · `WRONG_ORDER` · `WRONG_LABEL` ·
 *   `WRONG_ROLE_VISIBILITY` · `WRONG_SURFACE_TYPE` · `FIELD_MISMATCH` ·
 *   `GRAIN_MISMATCH` · `NOT_BUILT` · `N/A` · `SUPERSEDED`.
 *
 * ◆ **والفعليُّ من التصييرِ الحيِّ** (§16): المجموعةُ والترتيبُ والاسمُ والدورُ
 *   تُقرأ من سايدبارِ دورِ المساحةِ المُصيَّرِ في عمليّةٍ نقيّة — ⛔ لا من جدول.
 * ◆ **وأسبقيّةُ الحكمِ مُعلَنة**: البنيةُ قبلَ المحتوى — فسطحٌ في مجموعةٍ خاطئةٍ
 *   لا يُقال فيه «حقولُه ناقصة» وموضعُه هو العطب.
 *
 * التشغيل: php tools/govui_conformance_matrix.php [--md] [--ws=DEP-10]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$NULLDEV = (DIRECTORY_SEPARATOR === '/') ? '/dev/null' : 'NUL';
require_once $ROOT . '/tools/govui_lib.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$MD = in_array('--md', $argv, true);
$only = ''; foreach ($argv as $a) { if (strpos($a, '--ws=') === 0) { $only = substr($a, 5); } }
$base = function ($s) { return strtolower(ltrim(preg_replace('~[?#].*$~', '', preg_replace('~^(\.\./)+~', '', trim((string) $s))), '/')); };

/* ── ① السجلُّ الحاكمُ ── */
$reg = array();
$r = $conn->query("SELECT * FROM govui_target_registry ORDER BY workspace_id, screen_order");
while ($x = $r->fetch_assoc()) { $reg[$x['target_id']] = $x; }
if (!$reg) { exit("⛔ شغّلْ tools/govui_target_registry.php أوّلًا\n"); }

/* ── ② الأدوارُ الحيّة ── */
$wsRole = array();
$r = $conn->query("SELECT w.workspace_id, w.role_id, ro.name FROM nav_ws_roles w JOIN roles ro ON ro.id = w.role_id WHERE w.binding = 'PRIMARY'");
while ($x = $r->fetch_assoc()) { $wsRole[$x['workspace_id']] = array((int) $x['role_id'], $x['name']); }
$PROBE = 24;
if (!isset($wsRole['WS-MY'])) { $wsRole['WS-MY'] = array($PROBE, 'مسبارٌ مُعلَن (الدور ' . $PROBE . ') — «مساحتي» تُحقن في كلِّ دور'); }

/* ── ③ التصييرُ الحيّ: المسارُ ⇒ [الاسم · القسمُ المقروء · ترتيبُه فيه] ── */
$live = array();
foreach ($wsRole as $ws => $rr) {
    if ($only !== '' && $ws !== $only) { continue; }
    $j = json_decode((string) shell_exec(escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php') . ' ' . $rr[0] . ' 2>' . $NULLDEV), true);
    if (!is_array($j) || !isset($j['positions'])) { continue; }
    $seq = array();
    foreach ($j['positions'] as $p) {
        $b = $base($p['h']);
        $isVar = (strpbrk((string) $p['h'], '#?') !== false);
        if ($isVar && isset($live[$ws][$b])) { continue; }
        $sub  = (string) (isset($p['s']) ? $p['s'] : '');
        $head = (string) $p['g'];
        $sec  = $sub !== '' ? $sub : $head;
        $seq[$sec] = (isset($seq[$sec]) ? $seq[$sec] : 0) + 1;
        $live[$ws][$b] = array('l' => (string) $p['l'], 'sub' => $sub, 'head' => $head,
                               'sec' => $sec, 'ord' => $seq[$sec]);
    }
}

/* ── ③ب مالكُ بندِ المسارِ — ومَن ليس مالكَه لا يُقاس بندُه في القائمة ──
   ◆ **الفخُّ المقيس**: ابنُ تبويبٍ يتقاسم مسارَ أبيه يُصيَّر ببندِ الأبِ واسمِه
     ومجموعتِه — فمقارنتُه بحاكمِه تُخرج `WRONG_LABEL` و`WRONG_GROUP` **كاذبَين
     ثمانيةَ عشرَ وستّةَ عشرَ صفًّا**. واسمُه الحاكمُ يُقاس **في تبويبِ صفحتِه**
     لا في القائمة (§8) — فحكمُه هنا `N/A` بسببِه المكتوب. */
$routeOwn = array();
foreach ($reg as $tid => $g) {
    if ($g['built_route'] === '') { continue; }
    $routeOwn[$g['workspace_id'] . '|' . $base($g['built_route'])][] = $tid;
}
$notOwner = array();
foreach ($routeOwn as $k => $ids) {
    if (count($ids) < 2) { continue; }
    $menus = array();
    foreach ($ids as $t) { if (in_array($reg[$t]['surface_type'], array('MENU_ITEM', 'LANDING_PAGE'), true)) { $menus[] = $t; } }
    sort($ids); sort($menus);
    $own = $menus ? $menus[0] : $ids[0];
    foreach ($ids as $t) { if ($t !== $own) { $notOwner[$t] = $own; } }
}

/* ◆ **ومجموعةُ الموضعِ المخزَّنةُ تُقاس أيضًا**: فبندٌ بلا عنوانٍ فرعيٍّ مُصيَّرٍ
   لا يعني أنَّ موضعَه المسجَّلَ صحيحٌ — والمخزنُ يُسأل حيث يصمت التصيير. */
$grpStore = array();
$r = $conn->query("SELECT id, label_ar FROM nav_lifecycle_groups");
while ($x = $r->fetch_assoc()) { $grpStore[(int) $x['id']] = $x['label_ar']; }

/* ── ④ الحقولُ والحبّةُ وآلةُ الحالة ── */
$fld = array();
$r = $conn->query("SELECT screen_id, matched, design_applicable FROM repair01_field_measure");
while ($x = $r->fetch_assoc()) { $fld[$x['screen_id']] = array((int) $x['matched'], (int) $x['design_applicable']); }

/* ── أحكامُ الكونِ المكتوبة: هدفٌ **دُمج أو أُسقط بحكمٍ وشاهد** ───────────────
 * ◆ **العطبُ المصحَّح**: `repair01_field_measure` **لا تقيس إلّا `MATCHED`**
 *   بنصِّها («فسطحٌ لم يُطابَق لا ملفَّ له يُقاس عليه»). فهدفٌ حكمُه
 *   `MERGED_INTO` **لا صفَّ له في دفترِ الحقول**، وكانت المصفوفةُ تقرأ غيابَ
 *   القياسِ **نقصًا في الحقول** فتحكم عليه `FIELD_MISMATCH`.
 *   ⛔ **وذاك اتّهامٌ بما لم يُقَس** — وحقولُه مقيسةٌ فعلًا على السطحِ الذي
 *   دُمج فيه. قِيس: 39 هدفًا كلُّها بهذا الحكم.
 * ⇒ **والحكمُ الصحيحُ `N/A` بحكمٍ مكتوب** — وهو أحدُ الأحدَ عشرَ المسموحة،
 *   ولا يُقبل إلّا **بشاهدٍ مسجَّلٍ في الكون**. */
$ruled = array();
$r = $conn->query("SELECT requirement_id, screen_id, verdict, verdict_witness
                     FROM repair01_target_universe
                    WHERE verdict IN ('MERGED_INTO','TAB_CHILD','PROJECTION','NOT_APPLICABLE','RETIRED_TARGET')
                      AND verdict_witness <> ''");
while ($r && $x = $r->fetch_assoc()) {
    if ((string) $x['requirement_id'] !== '') { $ruled['R:' . $x['requirement_id']] = $x['verdict']; }
    if ((string) $x['screen_id'] !== '')      { $ruled['S:' . $x['screen_id']]      = $x['verdict']; }
}
$grn = array();
$r = $conn->query("SELECT screen_id, grain_entity, grain_multi, grain_measured FROM repair01_screen_registry");
while ($x = $r->fetch_assoc()) { $grn[$x['screen_id']] = $x; }

/* ── ⑤ الحكمُ صفًّا صفًّا ── */
$rows = array(); $byWs = array();
foreach ($reg as $tid => $g) {
    $ws = $g['workspace_id'];
    if ($only !== '' && $ws !== $only) { continue; }
    $sid = (string) $g['target_screen_id'];
    $b = $g['built_route'] !== '' ? $base($g['built_route']) : '';
    $rn = ($b !== '' && isset($live[$ws][$b])) ? $live[$ws][$b] : null;
    $hasRole = isset($wsRole[$ws]);

    $tGroup = (string) $g['canonical_group_label'];
    /* ◆ **والقسمُ المُصيَّرُ يُؤكِّد المجموعةَ حين يوجد وحدَه**: بندٌ يقع تحتَ رأسِ
       الطيِّ مباشرةً بلا عنوانٍ فرعيٍّ (صفحاتُ الهبوطِ وما ثُبِّت قسمُه) **لا
       يُصرِّح السايدبارُ بمجموعتِه** — فلا يُحكم عليه بمجموعةِ رأسِ الطيِّ، فتلك
       تصنيفٌ عامٌّ لا مجموعةُ دورةٍ [[nav-group-key-is-route-not-role]].
       ◆ **ومجموعةُ الدليلِ قد تُصيَّر رأسَ طيٍّ أو عنوانًا فرعيًّا** — قِيس في
         `EX-CEO`: «صناديق القرار» رأسُ طيٍّ وتحتَه عناوينُ أدقّ. فالمطابقةُ
         تقبل **أيَّهما**، وما خالفهما معًا هو `WRONG_GROUP` حقًّا. */
    $aSub   = $rn ? (string) $rn['sub'] : '';
    $aGroup = $rn ? ($aSub !== '' ? $aSub : '— بلا عنوانٍ فرعيٍّ · تحتَ رأسِ الطيِّ «' . $rn['head'] . '»') : '—';
    $tOrder = (int) $g['screen_order'];
    $aOrder = $rn ? $rn['ord'] : null;
    $cLabel = (string) $g['canonical_screen_label'];
    $aLabel = $rn ? $rn['l'] : '—';
    $gLabelStore = isset($grpStore[$g['group_id']]) ? $grpStore[$g['group_id']] : $tGroup;
    /* ── حكمُ المجموعةِ: مَن يُصرِّح بها ولمن تُسنَد ──
       ① عنوانٌ فرعيٌّ مُصيَّرٌ ⇒ السايدبارُ يُصرِّح: يطابق الفرعيُّ **أو** رأسُ الطيِّ.
       ② بلا فرعيٍّ (بندٌ تحتَ رأسِ الطيِّ مباشرةً) ⇒ **السايدبارُ لا يُصرِّح**
          بمجموعةِ دورةٍ، فيُسأل موضعُه المخزَّنُ — وهو الحاكمُ المستوردُ من الورقة.
       ③ **وصفحةُ الهبوطِ خارجَ الدورةِ بنصِّ الدليلِ نفسِه** — فلا يُطالَب
          السايدبارُ بأن يضعها تحتَ عنوانِ مرحلةٍ ليست منها (§8 · `LANDING_PAGE`). */
    $gLabelStoreTmp = isset($grpStore[$g['group_id']]) ? $grpStore[$g['group_id']] : '';
    $groupOk = true;
    if ($rn !== null) {
        $hit = (govui_gz($rn['sub']) === govui_gz($tGroup)) || (govui_gz($rn['head']) === govui_gz($tGroup));
        /* ◆ **وعنوانٌ فرعيٌّ يساوي رأسَ طيِّه ليس تصريحًا بمجموعةِ دورة** — هو
           اسمُ القسمِ العامِّ مكرَّرًا، فيُعامَل معاملةَ «بلا فرعيّ». */
        $subAsserts = ($rn['sub'] !== '' && govui_gz($rn['sub']) !== govui_gz($rn['head']));
        if ($g['surface_type'] === 'LANDING_PAGE' || !$subAsserts) {
            $groupOk = $hit || (govui_gz($gLabelStoreTmp) === govui_gz($tGroup));
        } else {
            $groupOk = $hit;
        }
    }
    $tRole  = (string) $g['required_roles'];
    $aRole  = $rn ? $wsRole[$ws][1] : ($hasRole ? '— لا يُصيَّر لدورِ مساحتِه' : 'BLOCKED_ROLE_BINDING');

    /* حالاتُ الأعمدةِ الأربعِ الأخيرة */
    $build = ($g['surface_type'] === 'NOT_BUILT') ? 'NOT_BUILT' : 'BUILT';
    if ($sid !== '' && isset($grn[$sid])) {
        $gs = ($grn[$sid]['grain_multi'] == 1) ? 'MULTI_GRAIN'
            : ($grn[$sid]['grain_entity'] !== '' ? 'MEASURED' : 'NO_GRAIN');
    } else { $gs = $build === 'BUILT' ? 'NO_GRAIN' : 'N/A'; }
    if ($sid !== '' && isset($fld[$sid])) {
        $fs = $fld[$sid][0] . '/' . $fld[$sid][1];
        $fOk = ($fld[$sid][1] > 0 && $fld[$sid][0] >= $fld[$sid][1]);
    } else { $fs = '— غيرُ مقيسٍ (لا صفَّ في دفترِ حقولِ المبنيّ)'; $fOk = false; }
    $sms = ($g['state_model_ref'] !== '' && $g['state_model_ref'] !== '—') ? $g['state_model_ref'] : '—';

    /* ── الحكمُ النهائيُّ بأسبقيّةٍ مُعلَنة: البنيةُ قبلَ المحتوى ── */
    if ($build === 'NOT_BUILT') { $v = 'NOT_BUILT'; }
    elseif (!$hasRole) { $v = 'N/A'; }
    elseif (in_array($g['surface_type'], array('TAB_CHILD', 'DIRECT_ONLY', 'UTILITY', 'PROJECTION'), true) && $rn === null) {
        $v = 'N/A';                                   /* لا يُنتظَر له بندٌ في القائمة (§8) */
    } elseif (isset($notOwner[$tid])) {
        $v = 'N/A';                                   /* بندُ القائمةِ لأبيه — واسمُه في تبويبِ صفحتِه (§8) */
    } elseif ($g['surface_type'] === 'TAB_CHILD') {
        /* ◆ **والسجلُّ التابعُ يُقاس في صفحةِ أبيه لا في القائمة** (§8): تسعةٌ
           وثلاثون منها بمسارٍ مستقلٍّ تُصيَّر بنودًا تحتَ تصنيفٍ عامّ — والحكمُ
           في ظهورِها مرفوعٌ بحاجزِ `OA-11` (نزعُ رابطٍ حيٍّ قرارُ مالك)، فلا
           يُحمَّل اسمُها ولا مجموعتُها حكمَ السايدبار. */
        /* وحتّى ابنُ التبويبِ لا يُتَّهم بنقصٍ لم يُقَس: إن كان حكمُ الكونِ
           دمجًا أو تبويبًا بشاهدٍ فحقولُه تُقاس على سطحِ مرساتِه. */
        if (!isset($fld[$sid]) && $sid !== '' && isset($ruled['S:' . $sid])) {
            $v  = 'N/A';
            $fs = '— بحكمِ الكونِ المكتوب: ' . $ruled['S:' . $sid] . ' — والحقولُ تُقاس على سطحِ المرساة';
        } else {
            $v = ($gs === 'MULTI_GRAIN') ? 'GRAIN_MISMATCH' : (!$fOk ? 'FIELD_MISMATCH' : 'EXACT_MATCH');
        }
    } elseif (!isset($fld[$sid]) && $sid !== ''
              && (isset($ruled['S:' . $sid]) || (isset($g['requirement_id']) && isset($ruled['R:' . $g['requirement_id']])))) {
        /* هدفٌ بحكمٍ مكتوبٍ في الكون (دمجٌ أو تبويبٌ أو إسقاط) — حقولُه تُقاس
           على سطحِ مرساتِه، ولا يُتَّهم بنقصٍ لم يُقَس. */
        $v = 'N/A';
        $fs = '— بحكمِ الكونِ المكتوب: ' . (isset($ruled['S:' . $sid]) ? $ruled['S:' . $sid]
              : $ruled['R:' . $g['requirement_id']]) . ' — والحقولُ تُقاس على سطحِ المرساة';
    } elseif ($rn === null) { $v = 'WRONG_ROLE_VISIBILITY'; }
    elseif (!$groupOk) { $v = 'WRONG_GROUP'; }
    elseif (rpr02a_nz($aLabel) !== rpr02a_nz($cLabel)) { $v = 'WRONG_LABEL'; }
    elseif ($gs === 'MULTI_GRAIN') { $v = 'GRAIN_MISMATCH'; }
    elseif (!$fOk) { $v = 'FIELD_MISMATCH'; }
    else { $v = 'EXACT_MATCH'; }

    $rows[] = array($tid, $ws, $tGroup, $aGroup, $tOrder, $aOrder === null ? '—' : $aOrder,
        $cLabel, $aLabel, $g['surface_type'], $tRole, $aRole, $sid === '' ? '—' : $sid,
        $build, $gs, $fs, $sms, $v);
    $byWs[$ws][$v] = (isset($byWs[$ws][$v]) ? $byWs[$ws][$v] : 0) + 1;
}

/* ⛔ حارسُ المفردات: لا حكمَ خارجَ الأحدَ عشرَ */
$ALLOWED = array('EXACT_MATCH', 'WRONG_GROUP', 'WRONG_ORDER', 'WRONG_LABEL', 'WRONG_ROLE_VISIBILITY',
    'WRONG_SURFACE_TYPE', 'FIELD_MISMATCH', 'GRAIN_MISMATCH', 'NOT_BUILT', 'N/A', 'SUPERSEDED');
foreach ($rows as $x) { if (!in_array($x[16], $ALLOWED, true)) { exit("⛔ حكمٌ خارجَ الأحدَ عشرَ: {$x[16]}\n"); } }

$tot = array();
foreach ($byWs as $ws => $m) { foreach ($m as $v => $n) { $tot[$v] = (isset($tot[$v]) ? $tot[$v] : 0) + $n; } }
ksort($tot);
printf("مصفوفةُ §18: %d صفًّا · %d مساحةً\n", count($rows), count($byWs));
foreach ($tot as $v => $n) { printf("  %-24s %d\n", $v, $n); }

if ($MD) {
    $snap = 'BL-' . date('Ymd') . '-' . trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));
    $md = "# `GOVUI_CONFORMANCE_MATRIX` — مصفوفةُ المطابقةِ بسبعةَ عشرَ عمودًا (§18)\n\n"
        . "> مولَّدةٌ حيًّا بـ`tools/govui_conformance_matrix.php` · اللقطة **{$snap}** · " . date('Y-m-d H:i') . "\n"
        . "> **الفعليُّ من التصييرِ الحيِّ** لدورِ كلِّ مساحةٍ في عمليّةٍ نقيّة — ⛔ لا من جدول (§16).\n"
        . "> **والأحكامُ الأحدَ عشرَ حصرًا** — وحارسُ مفرداتٍ في الأداةِ يرفض ما خرج عنها.\n\n"
        . "## الحصادُ بالحكم\n\n| الحكم | العدد |\n|---|---|\n";
    foreach ($tot as $v => $n) { $md .= "| `{$v}` | **{$n}** |\n"; }
    $md .= "| **المجموع** | **" . count($rows) . "** |\n\n## لوحةُ المساحات\n\n| المساحة | " . implode(' | ', $ALLOWED) . " |\n|" . str_repeat('---|', 12) . "\n";
    foreach ($byWs as $ws => $m) {
        $md .= "| {$ws} ";
        foreach ($ALLOWED as $v) { $md .= '| ' . (isset($m[$v]) ? $m[$v] : '·') . ' '; }
        $md .= "|\n";
    }
    $md .= "\n## الصفوفُ السبعةَ عشرَ عمودًا\n\n"
        . "| `Target_ID` | `Workspace` | `Target_Group` | `Actual_Group` | `T_Ord` | `A_Ord` | `Canonical_Label` | `Actual_Label` | `Surface_Type` | `Target_Role` | `Actual_Role` | `Screen_ID` | `Build` | `Grain` | `Field` | `State_Model` | `Final_Verdict` |\n"
        . "|" . str_repeat('---|', 17) . "\n";
    foreach ($rows as $x) {
        $c = array();
        foreach ($x as $i => $v) { $c[] = str_replace('|', '¦', (string) $v); }
        $md .= '| ' . implode(' | ', $c) . " |\n";
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_CONFORMANCE_MATRIX.md', $md);
    echo "⇐ docs/REPAIR01_20260823/GOVUI_CONFORMANCE_MATRIX.md\n";
}
file_put_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_CONFORMANCE_MATRIX.json',
    json_encode(array('by_ws' => $byWs, 'totals' => $tot, 'rows' => $rows), JSON_UNESCAPED_UNICODE));
