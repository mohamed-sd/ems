<?php
/**
 * tools/navarch/shadow.php — `NEW_NAV_SHADOW` والمقارنةُ بأعدادِها الستّة (‏§30)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **نصُّ §30**: «قبل قلب السايدبار أنشئ Render ثانٍ `NEW_NAV_SHADOW`
 *   **للمستخدم نفسه والWorkspace نفسها**، ثم قارن Old/New وسجّل: مُزال ·
 *   منقول · مُسيَّق · مُبقًى · مُحوَّل · يحتاج قرارًا. **ولا يغيّر Shadow
 *   تجربةَ المستخدم**.»
 *
 * ◆ **والقديمُ يُقرأ من الأساسِ لا يُعاد تصييرُه**: `NAV_ARCH_BASELINE.json`
 *   لقطةٌ واحدةٌ على التزامٍ واحد (§5) — فلو أُعيد تصييرُ القديمِ الآنَ لقُورن
 *   تصييرُ اليومِ بحكمِ الأمس، وهو خلطُ لقطتَين.
 *
 * ◆ **ومصيرُ كلِّ رابطٍ يُقرأ من السجلَّاتِ الحاكمةِ لا يُخمَّن**: الموضعُ من
 *   `nav_workspace_placements`، والعابرُ من `nav_cross_domain_register`،
 *   والإرثُ من `nav_legacy_disposition` — فما اختفى **له سطرٌ يقول لماذا
 *   وكيف يُوصَل إليه** (§4: لا إخفاءَ بلا تصنيفٍ وبديلِ وصولٍ ودليلٍ ومصير).
 *
 * التشغيل: php tools/navarch/shadow.php [--ws=DEP-11] [--list]
 *   ⇒ docs/REPAIR01_20260823/navarch/SHADOW_NAV_COMPARISON.json + .md
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
require_once $ROOT . '/includes/navarch_renderer.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$onlyWs = ''; $list = in_array('--list', $argv, true);
foreach ($argv as $a) { if (strpos($a, '--ws=') === 0) { $onlyWs = substr($a, 5); } }

$OUT = $ROOT . '/docs/REPAIR01_20260823/navarch';
$BL  = json_decode(file_get_contents($OUT . '/NAV_ARCH_BASELINE.json'), true);
$BLID = $BL['baseline_id'];

/* مصيرُ كلِّ ظهورٍ من السجلَّاتِ الحاكمة */
$fate = array();
$r = $conn->query("SELECT current_workspace w, current_route rt, action, disposition,
                          access_replacement ap, evidence ev FROM nav_legacy_disposition");
while ($x = $r->fetch_assoc()) {
    $fate[$x['w'] . '|' . $x['rt']] = array('src' => 'LEGACY', 'action' => $x['action'],
        'why' => $x['disposition'], 'access' => $x['ap'], 'ev' => $x['ev']);
}
$r = $conn->query("SELECT consumer_workspace w, route rt, need_case, remedy, access_path ap,
                          governing_source ev FROM nav_cross_domain_register");
while ($x = $r->fetch_assoc()) {
    $fate[$x['w'] . '|' . $x['rt']] = array('src' => 'CROSS', 'action' => $x['need_case'],
        'why' => $x['need_case'], 'access' => $x['ap'], 'ev' => $x['ev']);
}
$r = $conn->query("SELECT workspace_id w, route rt, placement_type pt, reason_code rc,
                          governing_source ev, status FROM nav_workspace_placements");
while ($x = $r->fetch_assoc()) {
    $k = $x['w'] . '|' . $x['rt'];
    if (!isset($fate[$k]) || $x['status'] === 'ACTIVE') {
        $fate[$k] = array('src' => 'PLACEMENT', 'action' => $x['pt'], 'why' => $x['rc'],
                          'access' => null, 'ev' => $x['ev']);
    }
}

/* الأدوارُ الممثِّلة */
$wsRole = array();
$r = $conn->query("SELECT workspace_id, role_id FROM nav_ws_roles WHERE binding='PRIMARY'");
while ($x = $r->fetch_assoc()) { $wsRole[$x['workspace_id']] = (int) $x['role_id']; }

/* ═══ المقارنةُ لكلِّ مساحة ═══════════════════════════════════════════════ */
$SUM = array(); $DET = array();
$G = array('removed' => 0, 'moved' => 0, 'contextualized' => 0, 'retained' => 0,
           'redirected' => 0, 'needs_decision' => 0, 'old' => 0, 'new' => 0);

foreach ($BL['snapshot'] as $ws => $s) {
    if ($s['rendered'] === null) { continue; }
    if ($onlyWs !== '' && $ws !== $onlyWs) { continue; }
    $rid = isset($wsRole[$ws]) ? $wsRole[$ws] : (int) $s['role_id'];

    /* القديمُ من الأساسِ — بترتيبِ العرضِ ومجموعتِه */
    $old = array(); $oldGroup = array();
    foreach ($s['items'] as $it) { $old[$it['route']] = $it; $oldGroup[$it['route']] = $it['group']; }

    /* الجديدُ بالظلّ */
    $new = navarch_render($conn, $ws, $rid);
    $newSet = array(); $newGroup = array();
    foreach ($new['groups'] as $g) {
        foreach ($g['items'] as $i) { $newSet[$i['route']] = $i; $newGroup[$i['route']] = $g['label']; }
    }
    foreach ($new['shell'] as $i)    { $newSet[$i['route']] = $i; $newGroup[$i['route']] = '⌂ القشرةُ العامّة'; }
    foreach ($new['personal'] as $i) { $newSet[$i['route']] = $i; $newGroup[$i['route']] = '⌂ مساحةُ عملي'; }

    $c = array('removed' => 0, 'moved' => 0, 'contextualized' => 0, 'retained' => 0,
               'redirected' => 0, 'needs_decision' => 0);
    foreach ($old as $rt => $it) {
        $f = isset($fate[$ws . '|' . $rt]) ? $fate[$ws . '|' . $rt] : null;
        $act = $f ? (string) $f['action'] : '';
        $verdict = '';
        if (isset($newSet[$rt])) {
            /* بقي ظاهرًا — أفي مجموعتِه أم انتقل؟ (‏والقشرةُ/الشخصيُّ انتقالٌ بحكمٍ) */
            $verdict = ($newGroup[$rt] === $oldGroup[$rt]) ? 'retained' : 'moved';
        } elseif ($act === 'REDIRECT' || $act === 'REPLACE') {
            $verdict = 'redirected';
        } elseif ($act === 'ESCALATE' || (!$f && !isset($newSet[$rt]))) {
            $verdict = $act === 'ESCALATE' ? 'needs_decision' : 'removed';
        } elseif (in_array($act, array('A_PROJECTION', 'C_CONTEXTUAL_ACTION', 'CONTEXTUALIZE',
                                       'MOVE_TO_PARENT'), true)) {
            $verdict = 'contextualized';
        } else {
            $verdict = 'removed';
        }
        $c[$verdict]++;
        $DET[] = array('ws' => $ws, 'route' => $rt, 'label' => $it['label'],
                       'old_group' => $oldGroup[$rt],
                       'new_group' => isset($newGroup[$rt]) ? $newGroup[$rt] : '—',
                       'verdict' => $verdict, 'action' => $act,
                       'why' => $f ? $f['why'] : 'NO_GOVERNING_ROW',
                       'access' => $f && $f['access'] ? $f['access'] : '—',
                       'evidence' => $f ? $f['ev'] : '—');
    }
    /* ما ظهر جديدًا ولم يكن في القديم — ⛔ ولا يُبتلَع */
    $added = array();
    foreach ($newSet as $rt => $i) { if (!isset($old[$rt])) { $added[] = $rt; } }

    $SUM[$ws] = array('role' => $rid, 'old' => count($old), 'new' => count($newSet),
                      'added' => count($added), 'added_list' => $added) + $c
              + array('blocked' => $new['counters']);
    foreach ($c as $k => $v) { $G[$k] += $v; }
    $G['old'] += count($old); $G['new'] += count($newSet);
}

/* ═══ الإخراج ═══════════════════════════════════════════════════════════════ */
echo "══ NEW_NAV_SHADOW — المقارنةُ بأعدادِ §30 الستّة · الأساس {$BLID} ══\n";
printf("  القديم **%d** ⇒ الجديد **%d** (‏فرق %+d)\n", $G['old'], $G['new'], $G['new'] - $G['old']);
/* ◆ **والفارقُ عن 1,189 مُعلَنٌ لا مبتلَع**: مقامُ المقارنةِ زوجُ (‏مساحة، مسار)
 *   لا الظهورُ المفرد، و12 ظهورًا كرَّرت مسارَها في مساحتِها نفسِها (‏10 مسارات)
 *   فانطوت. **وهي فائضُ ظهورٍ مقيسٌ** يقيّده `classify.php` باسمِه.
 *   [[report-self-contradiction-sweep]]: رقمانِ في تقريرٍ واحدٍ بلا سببٍ مكتوبٍ
 *   يُقرآنِ تناقضًا — فيُعلَن الفارقُ في صدرِ التقريرِ لا في حاشيته. */
$blSum = 0;
foreach ($BL['snapshot'] as $ss) { if ($ss['rendered'] !== null) { $blSum += $ss['rendered']; } }
if ($onlyWs === '' && $blSum !== $G['old']) {
    printf("  ◆ ظهوراتُ الأساس %d · وأزواجُ (‏مساحة، مسار) %d — والفارقُ **%d ظهورًا كرَّر مسارَه**\n",
        $blSum, $G['old'], $blSum - $G['old']);
}
printf("  مُبقًى %d · منقول %d · مُسيَّق %d · مُحوَّل %d · مُزال %d · يحتاج قرارًا %d\n",
    $G['retained'], $G['moved'], $G['contextualized'], $G['redirected'], $G['removed'],
    $G['needs_decision']);
$sumSix = $G['retained'] + $G['moved'] + $G['contextualized'] + $G['redirected']
        + $G['removed'] + $G['needs_decision'];
printf("  ◆ والمقامُ مغلق: %d = %d ✔\n", $sumSix, $G['old']);

echo "\n  ── لكلِّ مساحة ──\n";
printf("  %-8s %5s %5s %6s %5s %5s %5s %5s %5s %5s\n",
    'المساحة', 'قديم', 'جديد', 'مُبقًى', 'منقول', 'سياق', 'حوّل', 'أُزيل', 'قرار', 'جديد+');
foreach ($SUM as $ws => $x) {
    printf("  %-8s %5d %5d %6d %5d %5d %5d %5d %5d %5d\n", $ws, $x['old'], $x['new'],
        $x['retained'], $x['moved'], $x['contextualized'], $x['redirected'], $x['removed'],
        $x['needs_decision'], $x['added']);
}

if ($list) {
    echo "\n  ── تفصيلُ ما لم يبقَ — ولكلٍّ سببٌ وبديلُ وصول ──\n";
    foreach ($DET as $d) {
        if ($d['verdict'] === 'retained') { continue; }
        printf("  %-8s %-42s %-15s %-32s %s\n", $d['ws'], mb_substr($d['route'], 0, 40),
            $d['verdict'], mb_substr($d['why'], 0, 30), mb_substr($d['access'], 0, 46));
    }
}

$doc = array('baseline_id' => $BLID, 'totals' => $G, 'per_workspace' => $SUM, 'detail' => $DET);
file_put_contents($OUT . '/SHADOW_NAV_COMPARISON.json',
    json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$md = array();
$md[] = '# `SHADOW_NAV_COMPARISON` — الظلُّ قبلَ القلب (‏§30)';
$md[] = '';
$md[] = '> ⛔ **ولا يغيّر الظلُّ تجربةَ المستخدم**: `includes/navarch_renderer.php`';
$md[] = '> لا تستدعيه شاشةٌ حيّة. والقديمُ **يُقرأ من الأساس** `' . $BLID . '` لا';
$md[] = '> يُعاد تصييرُه — فلا تُقارَن لقطتان.';
$md[] = '';
$md[] = '| | القديم | الجديد | مُبقًى | منقول | مُسيَّق | مُحوَّل | مُزال | يحتاج قرارًا |';
$md[] = '|---|---:|---:|---:|---:|---:|---:|---:|---:|';
$md[] = '| **الكلّ** | ' . $G['old'] . ' | ' . $G['new'] . ' | ' . $G['retained'] . ' | '
      . $G['moved'] . ' | ' . $G['contextualized'] . ' | ' . $G['redirected'] . ' | '
      . $G['removed'] . ' | ' . $G['needs_decision'] . ' |';
foreach ($SUM as $ws => $x) {
    $md[] = '| `' . $ws . '` | ' . $x['old'] . ' | ' . $x['new'] . ' | ' . $x['retained'] . ' | '
          . $x['moved'] . ' | ' . $x['contextualized'] . ' | ' . $x['redirected'] . ' | '
          . $x['removed'] . ' | ' . $x['needs_decision'] . ' |';
}
$md[] = '';
$md[] = '**والمقامُ مغلق**: ' . $sumSix . ' = ' . $G['old'] . ' — كلُّ ظهورٍ قديمٍ له حكمٌ واحدٌ لا صفرَ ولا اثنان.';
$md[] = '';
file_put_contents($OUT . '/SHADOW_NAV_COMPARISON.md', implode("\n", $md) . "\n");
echo "\n  ⇒ {$OUT}/SHADOW_NAV_COMPARISON.md\n";
exit($sumSix === $G['old'] ? 0 : 1);
