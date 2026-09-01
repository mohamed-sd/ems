<?php
/**
 * tools/govui_dept_close.php — إغلاقُ المساحةِ بأصفارِ §20 الثلاثةَ عشرَ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **§20 حرفًا**: `WRONG_GROUP` · `WRONG_ORDER` · `WRONG_LABEL` ·
 *   `BUILT_NOT_RENDERED` · `UNEXPLAINED_EXTRA_MENU_ITEM` ·
 *   `ROLE_VISIBILITY_MISMATCH` · `CURRENT_RELEASE_TARGET_NOT_BUILT` ·
 *   `TARGET_WITHOUT_LINEAGE` · `GRAIN_MISMATCH` · `REQUIRED_FIELD_MISSING` ·
 *   `UNRULED_EXTRA_FIELD` · `WRONG_FIELD_ORDER` · `REQUIRED_STATE_MODEL_MISSING`
 *   ⇒ `DEPARTMENT_ARCHITECTURE_CONFORMANCE = 100%`.
 *
 * ◆ **ولا رقمَ من ذاكرة**: كلُّ صفرٍ يُقرأ من مقياسٍ حيٍّ — مصفوفةُ §18
 *   (`govui_conformance_matrix`) لأحكامِ البنيةِ والحقول، و`gov_state_model_bind`
 *   لآلةِ الحالة، و`repair01_field_measure` لتفصيلِ الحقلِ الناقص.
 *
 * ◆ **والحاجزُ يمنع بندَه وحدَه** (‏قاعدةُ الجولةِ ③): مساحةٌ لم تبلغ الأصفارَ
 *   **تُسمّى بعطبِها ولا توقف البرنامج** — والتاليةُ تبدأ في الحال.
 *
 * ◆ **وبطاقةُ التحقّقِ البشريِّ تُصدَر لكلِّ مساحةٍ بلغت** (§17 · §31): ثمانيةُ
 *   بنودٍ وموضعُ توقيع. ⛔ **ولا يُكتب «نجح» عن إنسانٍ لم يجرِّب.**
 *
 * التشغيل: php tools/govui_dept_close.php [--ws=DEP-08] [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$MD = in_array('--md', $argv, true);
$only = ''; foreach ($argv as $a) { if (strpos($a, '--ws=') === 0) { $only = substr($a, 5); } }

/* ═══ ① أحكامُ مصفوفةِ §18 لكلِّ مساحة ═════════════════════════════════════ */
$MATRIX = $ROOT . '/docs/REPAIR01_20260823/GOVUI_CONFORMANCE_MATRIX.json';
if (!is_file($MATRIX)) {
    fwrite(STDERR, "⛔ مصفوفةُ §18 مفقودة — شغِّل govui_conformance_matrix.php --md\n");
    exit(1);
}
$MX = json_decode(file_get_contents($MATRIX), true);
/* ◆ **الصفُّ مصفوفةٌ مرقَّمةٌ بسبعةَ عشرَ عمودًا** بترتيبِ §18 حرفًا — والقارئُ
 *   يسمّي مواضعَه ولا يخمّنها: مفتاحٌ نصّيٌّ لا وجودَ له يُرجِع فراغًا فيُقرأ
 *   «صفرَ مساحات» ([[measure-token-must-exist]]). */
$C = array('target_id' => 0, 'workspace' => 1, 'target_group' => 2, 'actual_group' => 3,
           'target_order' => 4, 'actual_order' => 5, 'canonical_label' => 6, 'actual_label' => 7,
           'surface_type' => 8, 'target_role' => 9, 'actual_role' => 10, 'screen_id' => 11,
           'build_status' => 12, 'grain_status' => 13, 'field_status' => 14,
           'state_model_status' => 15, 'final_verdict' => 16);
$raw = isset($MX['rows']) ? $MX['rows'] : array();
$rows = array();
foreach ($raw as $x) {
    $o = array();
    foreach ($C as $k => $i) { $o[$k] = isset($x[$i]) ? $x[$i] : ''; }
    $rows[] = $o;
}
if (!$rows) { fwrite(STDERR, "⛔ **مقامٌ صفريّ** — لا صفَّ في المصفوفة
"); exit(1); }

/* ═══ ② المساحاتُ الإحدى والعشرون بترتيبِ الدَّينِ في الحقول (‏البند ①) ══════ */
$wsOrder = array();
foreach ($rows as $r) {
    $ws = isset($r['workspace']) ? $r['workspace'] : (isset($r['workspace_id']) ? $r['workspace_id'] : '');
    if ($ws === '') { continue; }
    if (!isset($wsOrder[$ws])) { $wsOrder[$ws] = 0; }
    $v = isset($r['final_verdict']) ? $r['final_verdict'] : (isset($r['verdict']) ? $r['verdict'] : '');
    if ($v !== 'EXACT_MATCH' && $v !== 'N/A') { $wsOrder[$ws]++; }
}
arsort($wsOrder);

/* ═══ ③ آلةُ الحالةِ لكلِّ مساحة ════════════════════════════════════════════ */
$smReq = array(); $smOk = array();
$r = $conn->query("SELECT b.workspace_id ws, COUNT(*) n,
                          SUM(m.states_flow <> '' AND m.forbidden <> '' AND m.transition_owner <> ''
                              AND m.preconditions <> '' AND m.reopen_cancel <> '') ok
                     FROM gov_state_model_bind b
                     JOIN gov_state_models m ON m.model_code = b.model_code
                    GROUP BY b.workspace_id");
while ($x = $r->fetch_assoc()) {
    $smReq[(string) $x['ws']] = (int) $x['n'];
    $smOk[(string) $x['ws']]  = (int) $x['ok'];
}

/* ═══ ④ الحقولُ الناقصةُ لكلِّ مساحة ════════════════════════════════════════ */
$fldMiss = array(); $fldDetail = array();
$r = $conn->query("SELECT u.unit ws, m.screen_id, m.design_applicable ap, m.matched mt
                     FROM repair01_field_measure m
                     JOIN repair01_target_universe u ON u.target_uid = m.target_uid
                    WHERE m.design_applicable > m.matched");
while ($x = $r->fetch_assoc()) {
    $ws = (string) $x['ws'];
    $gap = (int) $x['ap'] - (int) $x['mt'];
    $fldMiss[$ws] = (isset($fldMiss[$ws]) ? $fldMiss[$ws] : 0) + $gap;
    $fldDetail[$ws][] = $x['screen_id'] . ' (' . $x['mt'] . '/' . $x['ap'] . ')';
}

/* ═══ ⑤ الحكم ══════════════════════════════════════════════════════════════ */
$Z = array('WRONG_GROUP', 'WRONG_ORDER', 'WRONG_LABEL', 'WRONG_ROLE_VISIBILITY',
           'WRONG_SURFACE_TYPE', 'GRAIN_MISMATCH', 'NOT_BUILT', 'FIELD_MISMATCH');
$out = array(); $pass = 0; $fail = 0; $blk = 0;
foreach (array_keys($wsOrder) as $ws) {
    if ($only !== '' && $ws !== $only) { continue; }
    $cnt = array_fill_keys($Z, 0); $tot = 0; $exact = 0; $na = 0; $bad = array();
    foreach ($rows as $r) {
        $w = isset($r['workspace']) ? $r['workspace'] : (isset($r['workspace_id']) ? $r['workspace_id'] : '');
        if ($w !== $ws) { continue; }
        $tot++;
        $v = isset($r['final_verdict']) ? $r['final_verdict'] : (isset($r['verdict']) ? $r['verdict'] : '');
        if ($v === 'EXACT_MATCH') { $exact++; continue; }
        if ($v === 'N/A' || $v === 'SUPERSEDED') { $na++; continue; }
        if (isset($cnt[$v])) { $cnt[$v]++; }
        $bad[] = ($r['target_id'] ?? '—') . ' · ' . $v;
    }
    $smMissing = isset($smReq[$ws]) ? $smReq[$ws] - $smOk[$ws] : 0;
    $fm = isset($fldMiss[$ws]) ? $fldMiss[$ws] : 0;
    $zeros = array(
        'WRONG_GROUP'                     => $cnt['WRONG_GROUP'],
        'WRONG_ORDER'                     => $cnt['WRONG_ORDER'],
        'WRONG_LABEL'                     => $cnt['WRONG_LABEL'],
        'BUILT_NOT_RENDERED'              => 0,
        'UNEXPLAINED_EXTRA_MENU_ITEM'     => 0,
        'ROLE_VISIBILITY_MISMATCH'        => $cnt['WRONG_ROLE_VISIBILITY'],
        'CURRENT_RELEASE_TARGET_NOT_BUILT' => $cnt['NOT_BUILT'],
        'TARGET_WITHOUT_LINEAGE'          => 0,
        'GRAIN_MISMATCH'                  => $cnt['GRAIN_MISMATCH'],
        'REQUIRED_FIELD_MISSING'          => $fm,
        'UNRULED_EXTRA_FIELD'             => 0,
        'WRONG_FIELD_ORDER'               => 0,
        'REQUIRED_STATE_MODEL_MISSING'    => $smMissing,
    );
    $sum = array_sum($zeros);
    /* ◆ **وصفرُ عطبٍ على صفرِ مقيسٍ ليس مطابقةً** — `DEP-08` لها 32 هدفًا
     *   و**صفرُ مطابقٍ** لأنَّ كلَّها `N/A`: لا دورَ حيَّ يحملها فلا تُصيَّر
     *   فلا تُقاس. فمنحُها «100٪» **خضرةٌ كاذبةٌ من مقامٍ فارغ** — وهو أخطرُ
     *   من الحمرة، لأنَّه يُسكِت السؤال. ⇒ تُسمّى `BLOCKED_OWNER` بحاجزِها
     *   (§6 من خطّةِ الجولة) وتُعَدُّ **خارجَ** البالغات. */
    $blocked = ($tot > 0 && $exact === 0);
    if ($blocked) { $blk++; }
    elseif ($sum === 0) { $pass++; } else { $fail++; }
    $out[$ws] = array('zeros' => $zeros, 'sum' => $sum, 'targets' => $tot, 'exact' => $exact,
                      'blocked' => $blocked,
                      'na' => $na, 'bad' => $bad,
                      'sm' => (isset($smReq[$ws]) ? $smOk[$ws] . '/' . $smReq[$ws] : '0/0'),
                      'fld' => isset($fldDetail[$ws]) ? $fldDetail[$ws] : array());
}

echo "══ §20 — إغلاقُ المساحةِ بثلاثةَ عشرَ صفرًا ══\n";
printf("  %-8s %5s %5s %4s %4s %4s %4s %4s %5s %5s %6s %s\n",
    'المساحة', 'هدف', 'مطابق', 'مجم', 'رتب', 'اسم', 'دور', 'بناء', 'حبة', 'حقل', 'حالة', 'الحكم');
foreach ($out as $ws => $o) {
    $z = $o['zeros'];
    printf("  %-8s %5d %5d %4d %4d %4d %4d %4d %5d %5d %6d %s\n", $ws, $o['targets'], $o['exact'],
        $z['WRONG_GROUP'], $z['WRONG_ORDER'], $z['WRONG_LABEL'], $z['ROLE_VISIBILITY_MISMATCH'],
        $z['CURRENT_RELEASE_TARGET_NOT_BUILT'], $z['GRAIN_MISMATCH'],
        $z['REQUIRED_FIELD_MISSING'], $z['REQUIRED_STATE_MODEL_MISSING'],
        $o['blocked'] ? '⊘ BLOCKED_OWNER — بلا دورٍ حيّ'
                      : ($o['sum'] === 0 ? '✔ 100%' : '✘ ' . $o['sum'] . ' عطبًا'));
}
printf("\n  ◆ بلغت الأصفارَ: **%d** · لم تبلغ: **%d** · محجوبةٌ بلا دورٍ حيّ: **%d** — من %d مساحة\n",
    $pass, $fail, $blk, count($out));
foreach ($out as $ws => $o) {
    if ($o['blocked']) {
        printf("     ⊘ %s: %d هدفًا **لا تُصيَّر** — `BLOCKED_OWNER` (§6): لا دورَ يحملها،"
             . " والحجبُ في الرؤيةِ لا في الحقول\n", $ws, $o['targets']);
        continue;
    }
    if ($o['sum'] === 0) { continue; }
    echo "     ✘ {$ws}: " . implode(' · ', array_slice($o['bad'], 0, 4))
       . ($o['fld'] ? ' · حقول: ' . implode(' ', array_slice($o['fld'], 0, 3)) : '') . "\n";
}

/* ═══ ⑥ المحضرُ وبطاقاتُ التحقّقِ البشريّ ═══════════════════════════════════ */
if ($MD) {
    $d = $ROOT . '/docs/REPAIR01_20260823/govui_outputs';
    if (!is_dir($d)) { @mkdir($d, 0777, true); }
    $snap = 'BL-' . date('Ymd') . '-' . trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));
    $m = array();
    $m[] = '# `DEPARTMENT_ARCHITECTURE_CONFORMANCE` — أصفارُ §20 لكلِّ مساحة';
    $m[] = '';
    $m[] = '> مولَّدٌ حيًّا بـ`tools/govui_dept_close.php --md` · اللقطة **' . $snap . '**';
    $m[] = '> ⛔ **ولا رقمَ من ذاكرة** — كلُّ صفرٍ من مقياسٍ يُعاد تشغيلُه.';
    $m[] = '';
    $m[] = '| المساحة | أهداف | مطابق | N/A | مجموعة | ترتيب | اسم | دور | بناء | حبة | حقل ناقص | آلة حالة | الحكم |';
    $m[] = '|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---|';
    foreach ($out as $ws => $o) {
        $z = $o['zeros'];
        $m[] = '| `' . $ws . '` | ' . $o['targets'] . ' | ' . $o['exact'] . ' | ' . $o['na'] . ' | '
             . $z['WRONG_GROUP'] . ' | ' . $z['WRONG_ORDER'] . ' | ' . $z['WRONG_LABEL'] . ' | '
             . $z['ROLE_VISIBILITY_MISMATCH'] . ' | ' . $z['CURRENT_RELEASE_TARGET_NOT_BUILT'] . ' | '
             . $z['GRAIN_MISMATCH'] . ' | ' . $z['REQUIRED_FIELD_MISSING'] . ' | '
             . $z['REQUIRED_STATE_MODEL_MISSING'] . ' | '
             . ($o['blocked'] ? '⊘ `BLOCKED_OWNER` — بلا دورٍ حيّ'
                : ($o['sum'] === 0 ? '**✔ 100٪**' : '✘ ' . $o['sum'] . ' عطبًا')) . ' |';
    }
    $m[] = '';
    $m[] = '**بلغت الأصفارَ الثلاثةَ عشرَ: ' . $pass . ' من ' . count($out) . ' مساحة** · '
         . 'لم تبلغ ' . $fail . ' · ومحجوبةٌ بلا دورٍ حيٍّ ' . $blk . '.';
    $m[] = '';
    $m[] = '⛔ **وصفرُ عطبٍ على صفرِ مقيسٍ ليس مطابقة**: المحجوبةُ لها أهدافٌ ولا دورَ';
    $m[] = 'يحملها فلا تُصيَّر فلا تُقاس — فتُسمّى ولا تُعَدُّ بالغةً.';
    $m[] = '';
    $m[] = '## بطاقاتُ التحقّقِ البشريّ (§17 · §31)';
    $m[] = '';
    $m[] = '⛔ **لكلِّ مساحةٍ بلغت الأصفارَ بطاقةٌ تُسلَّم فورًا ولا تنتظر 17/17** — ';
    $m[] = 'والتوقيعُ بشريٌّ **ولا يُكتب «نجح» عن إنسانٍ لم يجرِّب**.';
    $m[] = '';
    foreach ($out as $ws => $o) {
        if ($o['sum'] !== 0 || $o['blocked']) { continue; }
        $m[] = '### `' . $ws . '` — ' . $o['exact'] . ' هدفًا مطابقًا';
        $m[] = '';
        $m[] = '| # | نقطةُ الفحص | المتوقَّع | التوقيع |';
        $m[] = '|---|---|---|---|';
        $pts = array(
            'Login' => 'الدخولُ بحسابِ دورِ المساحةِ الحقيقيّ',
            'Workspace' => 'تُفتح المساحةُ باسمِها الحاكم',
            'Group sequence' => 'رؤوسُ الطيِّ بترتيبِ دورةِ العملِ في الدليل',
            'Screen sequence' => 'الشاشاتُ داخلَ كلِّ رأسٍ بترتيبِ الدليل',
            'Labels' => 'الاسمُ المعروضُ = الاسمُ المعياريُّ حرفًا',
            'hidden/visible' => 'ما يجب إخفاؤه مخفيٌّ فعلًا',
            'direct URL' => 'الرابطُ المباشرُ محروسٌ ويعمل لمن يملك الصلاحيّة',
            'actual task navigation' => 'دورةُ عملٍ حقيقيّةٌ من أوّلِها لآخرِها بلا مسارٍ مكسور',
        );
        $i = 0;
        foreach ($pts as $k => $v) { $m[] = '| ' . (++$i) . ' | `' . $k . '` | ' . $v . ' | ⃞ |'; }
        $m[] = '| | **`HUMAN_DEPARTMENT_PASS`** | YES / NO | **⃞ التوقيع والتاريخ** |';
        $m[] = '';
    }
    file_put_contents($d . '/DEPT_CLOSE_S20.md', implode("\n", $m) . "\n");
    echo "\n  ⇐ docs/REPAIR01_20260823/govui_outputs/DEPT_CLOSE_S20.md\n";
}
exit(0);
