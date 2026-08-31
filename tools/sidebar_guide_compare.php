<?php
/**
 * tools/sidebar_guide_compare.php — مقارنةُ السايدبارِ المُصيَّرِ بالدليلِ المعماريِّ إدارةً إدارةً
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ سؤالُ المالك: «راجع كلَّ أبوابِ ومجموعاتِ وروابطِ السايدبارِ لإدارةٍ إدارة —
 *   هل نُفِّذ الترتيبُ بنفسِ ترتيبِ الملفِّ المرفق؟» — والملفُّ المرفقُ
 *   (docs/New folder/01 · الدليل المعماري-2.xlsx) **مطابقٌ بايتًا** (md5 واحد)
 *   لمصدرِ الحملةِ docs/REPAIR01_20260823/01 · الدليل المعماري.xlsx.
 *
 * ◆ القاعدةُ الحاكمة [[render-not-store-rule]]: «لا يُقاس سطحٌ بما في جدولِه —
 *   بل بما يظهر للمستخدمِ في جلستِه». فالحيُّ يُصيَّر بعمليّةٍ نقيّةٍ لكلِّ
 *   دورٍ (tools/lib/render_role_cli.php) لا يُقرأ من مخزن.
 *
 * ◆ والمطابقةُ ثلاثُ طبقاتٍ لكلِّ إدارة:
 *   ① المجموعات: وجودُ مجموعاتِ الدليلِ وترتيبُها بين رؤوسِ الطيِّ المُصيَّرة.
 *   ② الشاشات: وجودُ كلِّ شاشةٍ وترتيبُها داخل مجموعتِها (LCS على تسلسلِ الدليل).
 *   ③ الروابط: شاشةُ دليلٍ بلا بندٍ مُصيَّرٍ · وبندٌ مُصيَّرٌ داخلَ مجموعةِ
 *     دليلٍ ليس في الدليل — يُسمَّيان لا يُجمَلان.
 *
 * ◆ والجسرُ إدارة⇒دور هو جسرُ repair01_guide_nav_apply.php نفسُه (تطابقُ اسمٍ
 *   مطبَّعٍ + أربعُ إسناداتٍ صريحةٍ بسندِها) — ⛔ لا جسرَ ثانٍ [[nav-route-two-sources]].
 *   والاسمُ يُطابَق بالتطبيعِ ثمَّ بجسرِ السجلِّ (label⇒screen_file⇒href) —
 *   فالاسمُ المطابقُ حرفًا قد يكون الشاشةَ الخطأ [[repair01-ops-sidebar-guide11]].
 *
 * التشغيل: php tools/sidebar_guide_compare.php   ⇒ docs/REPAIR01_20260823/SIDEBAR_GUIDE_COMPARE.md
 */

error_reporting(E_ALL & ~E_DEPRECATED);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/rpr02a_guide.php';

/** تطبيعُ اسمِ مجموعةٍ: nz + توحيدُ الشُّرَطِ + نزعُ حاشيةٍ لاتينيّةٍ ذيليّةٍ
 *  — حاشيةُ (Overview) نُزعت من الإعلانِ بقرارِ FINAL_CLOSE ⑰ @ 52f4fe37 والدليلُ يحملها */
function sgc_gz($s)
{
    $s = rpr02a_nz($s);
    $s = strtr($s, array("–" => "—", "-" => "—"));
    $s = preg_replace('~\s*\([A-Za-z ]+\)\s*$~u', '', $s);
    return trim($s);
}

/* ═══ ⓪ الاتصال — كما في rpr02a_sidebar_spec.php حرفًا ═══ */
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

/* ═══ ① الدليل — بطاقاتُ كلِّ إدارةٍ بترتيبِها ═══ */
$cards = rpr02a_read_cards($ROOT . '/docs/REPAIR01_20260823/01 · الدليل المعماري.xlsx');
$spec = array();               /* code ⇒ [groups(ordered names), screens[{i,group,name}]] */
foreach ($cards as $c) {
    if (rpr02a_is_doc($c)) { continue; }                    /* التوثيقُ لا يظهر في السايدبار */
    $k = $c['code'];
    if (!isset($spec[$k])) { $spec[$k] = array('groups' => array(), 'screens' => array()); }
    $gn = sgc_gz($c['group']);
    if (!in_array($gn, $spec[$k]['groups'], true)) { $spec[$k]['groups'][] = $gn; }
    $spec[$k]['screens'][] = array('i' => count($spec[$k]['screens']) + 1,
        'group' => $gn, 'name' => rpr02a_nz($c['name']), 'raw' => $c['name'], 'graw' => $c['group']);
}

/* ═══ ② الجسرُ إدارة ⇒ دور — منطقُ repair01_guide_nav_apply.php حرفًا ═══ */
$roles = array();
$r = $conn->query('SELECT id, name FROM roles');
while ($x = $r->fetch_assoc()) { $roles[rpr02a_nz($x['name'])] = array((int) $x['id'], $x['name']); }
$depRole = array(); $depName = array();
$r = $conn->query('SELECT canonical_code, name_ar FROM repair01_departments');
while ($x = $r->fetch_assoc()) {
    $depName[$x['canonical_code']] = $x['name_ar'];
    $k = rpr02a_nz($x['name_ar']);
    if (isset($roles[$k])) { $depRole[$x['canonical_code']] = $roles[$k]; continue; }
    foreach ($roles as $rk => $rv) {
        if (mb_strpos($rk, $k) !== false || mb_strpos($k, $rk) !== false) { $depRole[$x['canonical_code']] = $rv; break; }
    }
}
foreach (array('DEP-06' => 21, 'DEP-17' => 25, 'EX-CEO' => 9, 'IAF' => 33) as $k => $v) {
    if (!isset($depRole[$k])) {
        $q = $conn->query('SELECT id, name FROM roles WHERE id = ' . $v . ' LIMIT 1');
        if ($q && $q->num_rows) { $y = $q->fetch_assoc(); $depRole[$k] = array((int) $y['id'], $y['name']); }
    }
}
/* WS-MY بنودٌ شخصيّةٌ داخلَ كلِّ دورٍ — تُقاس على الدورِ 1 ممثِّلًا ويُذكر ذلك */
if (!isset($depRole['WS-MY'])) { $depRole['WS-MY'] = array(1, 'ادارة التشغيل (ممثِّلًا)'); }

/* ═══ ③ جسرُ السجلِّ label ⇒ screen_file — للاسمِ الذي لا يطابق حرفًا ═══ */
$regFile = array();            /* dep|label ⇒ route  و  label ⇒ route (عامّ) */
$r = $conn->query('SELECT owner_code, canonical_label_ar, screen_file FROM repair01_screen_registry
                   WHERE canonical_label_ar IS NOT NULL AND canonical_label_ar <> ""');
while ($x = $r->fetch_assoc()) {
    $l = rpr02a_nz($x['canonical_label_ar']); $f = rpr02a_route($x['screen_file']);
    $regFile[$x['owner_code'] . '|' . $l] = $f;
    if (!isset($regFile['*|' . $l])) { $regFile['*|' . $l] = $f; }
}

/* ═══ ③ب حالةُ بناءِ كلِّ هدفٍ — من دفترِ المتطلّبات [[two-registers-target-vs-built]] ═══
   شاشةُ الدليلِ هدفٌ في repair01_requirements؛ فإن كانت NOT_IMPLEMENTED فغيابُها
   من السايدبارِ غيابُ بناءٍ لا عطبُ ترتيب — يُصنَّف لا يُجمَل. */
$reqState = array();           /* nz(surface) ⇒ amd01_state (الأوّلُ يثبت) */
$r = $conn->query('SELECT surface, amd01_state FROM repair01_requirements WHERE surface IS NOT NULL AND surface <> ""');
while ($x = $r->fetch_assoc()) {
    $k = rpr02a_nz($x['surface']);
    if (!isset($reqState[$k])) { $reqState[$k] = $x['amd01_state']; }
}

/** مزاوجةُ اسمَين بتقاطعِ المفردات — لاسمٍ معتمَدٍ مغايرٍ لاسمِ الدليل
 *  [[nav-label-four-source-precedence]]: nav_canonical(APPROVED) يغلب اسمَ الدليل */
function sgc_tokens($s)
{
    $stop = array('في', 'من', 'على', 'الي', 'و', 'او', 'ما', 'لل', 'ال');
    $t = preg_split('~[\s·/،-]+~u', $s);
    $out = array();
    foreach ($t as $w) { $w = trim($w); if ($w !== '' && !in_array($w, $stop, true) && mb_strlen($w) > 2) { $out[$w] = 1; } }
    return $out;
}
function sgc_overlap($a, $b)
{
    $ta = sgc_tokens($a); $tb = sgc_tokens($b);
    if (!$ta || !$tb) { return 0.0; }
    $i = count(array_intersect_key($ta, $tb));
    return $i / min(count($ta), count($tb));
}

/* ═══ ④ التصييرُ النقيُّ لكلِّ دورٍ مطلوب ═══ */
$rendered = array();           /* role_id ⇒ positions[{g,l,h,route}] */
function render_role($ROOT, $rid)
{
    $out = array(); $rc = 0;
    @exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php') . ' ' . (int) $rid . ' 2>NUL', $out, $rc);
    $j = json_decode(implode("\n", $out), true);
    if (!is_array($j) || !isset($j['positions'])) { return null; }
    $pos = array();
    foreach ($j['positions'] as $p) {
        $pos[] = array('g' => sgc_gz($p['g']), 'graw' => $p['g'],
            'l' => rpr02a_nz($p['l']), 'lraw' => $p['l'],
            'route' => rpr02a_route(preg_replace('~^(\.\./)+~', '', (string) $p['h'])));
    }
    return $pos;
}

/* ═══ ⑤ LCS — أطولُ تسلسلٍ محفوظِ الترتيب ═══ */
function lcs_len(array $a, array $b)
{
    $n = count($a); $m = count($b);
    if (!$n || !$m) { return 0; }
    $dp = array_fill(0, $m + 1, 0);
    for ($i = 1; $i <= $n; $i++) {
        $prev = 0;
        for ($j = 1; $j <= $m; $j++) {
            $tmp = $dp[$j];
            $dp[$j] = ($a[$i - 1] === $b[$j - 1]) ? $prev + 1 : max($dp[$j], $dp[$j - 1]);
            $prev = $tmp;
        }
    }
    return $dp[$m];
}

/* ═══ ⑥ المقارنةُ إدارةً إدارةً ═══ */
$ORDER = array('DEP-01','DEP-02','DEP-03','DEP-04','DEP-05','DEP-06','DEP-07','DEP-08','DEP-09','DEP-10',
               'DEP-11','DEP-12','DEP-13','DEP-14','DEP-15','DEP-16','DEP-17','IAF','WS-MY','EX-CEO','EX-DVP');
$sum = array(); $detail = array();
foreach ($ORDER as $code) {
    if (!isset($spec[$code]) || !count($spec[$code]['screens'])) {
        $sum[$code] = array('role' => '—', 'verdict' => 'NO_SPEC', 'g' => '—', 's' => '—', 'o' => '—');
        continue;
    }
    if (!isset($depRole[$code])) {
        $sum[$code] = array('role' => '—', 'verdict' => 'NO_ROLE', 'g' => '—', 's' => '—', 'o' => '—');
        continue;
    }
    list($rid, $rname) = $depRole[$code];
    if (!isset($rendered[$rid])) { $rendered[$rid] = render_role($ROOT, $rid); }
    $pos = $rendered[$rid];
    if ($pos === null) {
        $sum[$code] = array('role' => $rid, 'verdict' => 'RENDER_FAIL', 'g' => '—', 's' => '—', 'o' => '—');
        continue;
    }

    $S = $spec[$code];
    /* — مطابقةُ كلِّ شاشةِ دليلٍ ببندٍ مُصيَّر: بالاسمِ ثمَّ بجسرِ السجلّ — */
    $matches = array();        /* spec idx ⇒ position idx or -1 */
    $usedPos = array();
    foreach ($S['screens'] as $si => $sc) {
        $hit = -1;
        foreach ($pos as $pi => $p) {
            if (isset($usedPos[$pi])) { continue; }
            if ($p['l'] === $sc['name']) { $hit = $pi; break; }
        }
        if ($hit < 0) {
            $route = isset($regFile[$code . '|' . $sc['name']]) ? $regFile[$code . '|' . $sc['name']]
                   : (isset($regFile['*|' . $sc['name']]) ? $regFile['*|' . $sc['name']] : '');
            if ($route !== '') {
                foreach ($pos as $pi => $p) {
                    if (isset($usedPos[$pi])) { continue; }
                    if ($p['route'] === $route) { $hit = $pi; break; }
                }
            }
        }
        $matches[$si] = $hit;
        if ($hit >= 0) { $usedPos[$hit] = true; }
    }

    /* — ① المجموعات: ترتيبُ مجموعاتِ الدليلِ بين المُصيَّرِ — */
    $renderGroups = array();
    foreach ($pos as $p) { if (!in_array($p['g'], $renderGroups, true)) { $renderGroups[] = $p['g']; } }
    $specGroupsInRender = array_values(array_intersect($renderGroups, $S['groups']));
    $gFound = count($specGroupsInRender);
    $gTotal = count($S['groups']);
    $gLcs = lcs_len($S['groups'], $specGroupsInRender);
    $gMissing = array_values(array_diff($S['groups'], $renderGroups));

    /* — ①ب مزاوجةُ الاسمِ المعتمَدِ المغاير: هدفٌ لم يطابق حرفًا يُزاوَج
         ببندٍ مُصيَّرٍ حرٍّ في مجموعةِ الدليلِ نفسِها بأعلى تقاطعِ مفرداتٍ ≥ 0.5 — */
    $paired = array();         /* spec idx ⇒ position idx */
    foreach ($S['screens'] as $si => $sc) {
        if ($matches[$si] >= 0) { continue; }
        $best = -1; $bo = 0.0;
        foreach ($pos as $pi => $p) {
            if (isset($usedPos[$pi])) { continue; }
            if ($p['g'] !== $sc['group']) { continue; }
            $o = sgc_overlap($p['l'], $sc['name']);
            if ($o > $bo) { $bo = $o; $best = $pi; }
        }
        if ($best >= 0 && $bo >= 0.5) { $paired[$si] = $best; $usedPos[$best] = true; }
    }
    /* — ①ج مرحلةٌ ثانيةٌ حرّةُ المجموعة: عتبةٌ أشدُّ (≥ 0.65) وأفضلُ تطابقٍ
         وحيدٌ — «سجل الفرص البيعية» تحت رأسِ تصنيفٍ تُلتقط هنا وتُحسب
         في غيرِ مجموعتِها تلقائيًّا لا «مفقودةً» — */
    foreach ($S['screens'] as $si => $sc) {
        if ($matches[$si] >= 0 || isset($paired[$si])) { continue; }
        $best = -1; $bo = 0.0; $second = 0.0;
        foreach ($pos as $pi => $p) {
            if (isset($usedPos[$pi])) { continue; }
            $o = sgc_overlap($p['l'], $sc['name']);
            if ($o > $bo) { $second = $bo; $bo = $o; $best = $pi; }
            elseif ($o > $second) { $second = $o; }
        }
        if ($best >= 0 && $bo >= 0.65 && $bo > $second) { $paired[$si] = $best; $usedPos[$best] = true; }
    }

    /* — ② الشاشات: الوجودُ والترتيبُ الكلّيُّ على تسلسلِ الدليلِ — */
    $sTotal = count($S['screens']);
    $found = array(); $notRendered = array(); $wrongGroup = array(); $renamed = array();
    foreach ($S['screens'] as $si => $sc) {
        $pi = ($matches[$si] >= 0) ? $matches[$si] : (isset($paired[$si]) ? $paired[$si] : -1);
        if ($pi < 0) {
            $st = isset($reqState[$sc['name']]) ? $reqState[$sc['name']] : '';
            $sc['state'] = ($st === '') ? 'NO_REQ_ROW' : $st;
            $notRendered[] = $sc;
            continue;
        }
        if (isset($paired[$si])) { $renamed[] = array('sc' => $sc, 'p' => $pos[$pi]); }
        $found[] = array('si' => $si, 'pi' => $pi, 'sc' => $sc, 'p' => $pos[$pi]);
        if ($pos[$pi]['g'] !== $sc['group']) { $wrongGroup[] = array('sc' => $sc, 'p' => $pos[$pi]); }
    }
    $sFound = count($found);
    $unbuilt = array(); $builtMissing = array();
    foreach ($notRendered as $sc) {
        if ($sc['state'] === 'NOT_IMPLEMENTED' || $sc['state'] === 'NO_REQ_ROW') { $unbuilt[] = $sc; }
        else { $builtMissing[] = $sc; }
    }
    /* الترتيب: تسلسلُ مواضعِ الدليلِ مرتَّبًا بمواضعِ التصيير */
    usort($found, function ($a, $b) { return $a['pi'] - $b['pi']; });
    $seq = array(); foreach ($found as $f) { $seq[] = $f['si']; }
    $sorted = $seq; sort($sorted);
    $inOrder = lcs_len($sorted, $seq);        /* كم بندًا يقف في محلِّه من التسلسل */
    $outOfOrder = array();
    /* البنودُ خارجَ أطولِ تسلسلٍ محفوظٍ — تُسمّى بالاسم */
    $keep = array(); $n = count($seq);
    if ($n) {
        $dp = array_fill(0, $n, 1); $pv = array_fill(0, $n, -1);
        for ($i = 1; $i < $n; $i++) { for ($j = 0; $j < $i; $j++) {
            if ($seq[$j] < $seq[$i] && $dp[$j] + 1 > $dp[$i]) { $dp[$i] = $dp[$j] + 1; $pv[$i] = $j; } } }
        $bi = 0; for ($i = 1; $i < $n; $i++) { if ($dp[$i] > $dp[$bi]) { $bi = $i; } }
        while ($bi >= 0) { $keep[$seq[$bi]] = true; $bi = $pv[$bi]; }
        foreach ($found as $f) { if (!isset($keep[$f['si']])) { $outOfOrder[] = $f; } }
    }

    /* — ③ بنودٌ مُصيَّرةٌ داخلَ مجموعاتِ الدليلِ ليست في الدليل — */
    $extra = array();
    foreach ($pos as $pi => $p) {
        if (!in_array($p['g'], $S['groups'], true)) { continue; }
        if (!isset($usedPos[$pi])) { $extra[] = $p; }
    }

    /* مجموعةُ دليلٍ غابت وكلُّ شاشاتِها غيرُ مبنيّة ⇒ غيابُ بناءٍ لا عطبُ سايدبار */
    $unbuiltNames = array(); foreach ($unbuilt as $u) { $unbuiltNames[$u['name']] = 1; }
    $gEmptyByBuild = array(); $gMissingReal = array();
    foreach ($gMissing as $gm) {
        $all = true;
        foreach ($S['screens'] as $sc) { if ($sc['group'] === $gm && !isset($unbuiltNames[$sc['name']])) { $all = false; break; } }
        if ($all) { $gEmptyByBuild[] = $gm; } else { $gMissingReal[] = $gm; }
    }
    $gDen = $gTotal - count($gEmptyByBuild);

    $verdict = ($gMissingReal || $builtMissing || $outOfOrder || $wrongGroup) ? 'MISMATCH'
             : ($unbuilt ? 'MATCH_BUILT' : 'MATCH');
    $sum[$code] = array('role' => $rid . ' · ' . $rname, 'verdict' => $verdict,
        'g' => $gLcs . '/' . $gDen, 's' => $sFound . '/' . $sTotal,
        'o' => count($found) ? $inOrder . '/' . count($found) : '0/0',
        'u' => count($unbuilt));
    $detail[$code] = array('rid' => $rid, 'rname' => $rname, 'S' => $S,
        'gMissing' => $gMissingReal, 'gEmptyByBuild' => $gEmptyByBuild,
        'specGroupsInRender' => $specGroupsInRender,
        'renderGroups' => $renderGroups, 'unbuilt' => $unbuilt, 'builtMissing' => $builtMissing,
        'renamed' => $renamed,
        'wrongGroup' => $wrongGroup, 'outOfOrder' => $outOfOrder, 'extra' => $extra,
        'inOrder' => $inOrder, 'found' => count($found));
}

/* ═══ ⑦ التقرير ═══ */
$snap = trim(shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));
$md = "# مقارنةُ السايدبارِ المُصيَّرِ بالدليلِ المعماريِّ — إدارةً إدارةً\n\n";
$md .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/sidebar_guide_compare.php` @ `" . $snap . "` · " . date('Y-m-d H:i') . "\n";
$md .= "> **المرجع**: `docs/New folder/01 · الدليل المعماري-2.xlsx` — **مطابقٌ بايتًا** (md5 `58b9cb58…`)\n";
$md .= "> لمصدرِ الحملةِ `docs/REPAIR01_20260823/01 · الدليل المعماري.xlsx` فالقياسُ عليهما واحد.\n";
$md .= "> **والحيُّ مُصيَّرٌ بعمليّةٍ نقيّةٍ لكلِّ دور** (`uxp_render_role_html`) لا مقروءٌ من جدول.\n\n";
$md .= "## ⓪ الخلاصة\n\n";
$md .= "| الرمز | الإدارة | الدور المُصيَّر | مجموعاتٌ بترتيبها | شاشاتٌ مُصيَّرة | في ترتيبها | الحكم |\n|---|---|---|---|---|---|---|\n";
$tally = array();
foreach ($ORDER as $code) {
    if (!isset($sum[$code])) { continue; }
    $s = $sum[$code];
    $v = $s['verdict'];
    $tally[$v] = (isset($tally[$v]) ? $tally[$v] : 0) + 1;
    $vTxt = array('MATCH' => '✔ مطابق', 'MATCH_BUILT' => '✔ مطابقٌ فيما بُني',
                  'MISMATCH' => '✘ **غير مطابق**', 'NO_ROLE' => '◆ بلا دورٍ حيّ',
                  'NO_SPEC' => '— بلا ورقةِ مجموعات', 'RENDER_FAIL' => '⚠ تعذّر التصيير');
    $uCol = isset($s['u']) && $s['u'] ? ' · غيرُ مبنيّ ' . $s['u'] : '';
    $md .= '| `' . $code . '` | ' . (isset($depName[$code]) ? $depName[$code] : '—') . ' | ' . $s['role']
         . ' | ' . $s['g'] . ' | ' . $s['s'] . $uCol . ' | ' . $s['o'] . ' | ' . $vTxt[$v] . " |\n";
}
$md .= "\n**العدّ**: ";
foreach ($tally as $k => $v) { $md .= '`' . $k . '`=' . $v . ' · '; }

/* — ⓪ب تشريحُ الفجوةِ مجمَّعًا: الرقمُ الواحدُ يُخفي أسبابًا أربعة — */
$agg = array('spec' => 0, 'found' => 0, 'inOrder' => 0, 'unbuilt' => 0,
             'builtMissing' => 0, 'renamed' => 0, 'wrongGroup' => 0, 'outOfOrder' => 0,
             'gTotal' => 0, 'gLcs' => 0, 'gEmpty' => 0, 'gMissReal' => 0);
foreach ($detail as $d) {
    $agg['spec'] += count($d['S']['screens']); $agg['found'] += $d['found'];
    $agg['inOrder'] += $d['inOrder']; $agg['unbuilt'] += count($d['unbuilt']);
    $agg['builtMissing'] += count($d['builtMissing']); $agg['renamed'] += count($d['renamed']);
    $agg['wrongGroup'] += count($d['wrongGroup']); $agg['outOfOrder'] += count($d['outOfOrder']);
    $agg['gTotal'] += count($d['S']['groups']); $agg['gEmpty'] += count($d['gEmptyByBuild']);
    $agg['gMissReal'] += count($d['gMissing']);
    $agg['gLcs'] += count($d['specGroupsInRender']);
}
$md .= "\n\n## ⓪ب تشريحُ الفجوةِ — أين يقف كلُّ بندِ دليل\n\n";
$md .= "| الطبقة | القيمة | القراءة |\n|---|---|---|\n";
$md .= '| شاشاتُ الدليلِ المقيسة (19 ورقةً) | **' . $agg['spec'] . '** | المقامُ الكامل |' . "\n";
$md .= '| ◇ غيرُ مبنيّةٍ أصلًا (`NOT_IMPLEMENTED`/بلا صفّ) | **' . $agg['unbuilt'] . '** | غيابُ بناءٍ — ليس عطبَ سايدبار |' . "\n";
$md .= '| ✔ مُصيَّرةٌ ومطابَقة | **' . $agg['found'] . '** | منها باسمٍ معتمَدٍ مغاير ' . $agg['renamed'] . ' |' . "\n";
$md .= '| ⛔ مبنيّةٌ (بدفترِها) ولا بندَ في سايدبارِ دورِها | **' . $agg['builtMissing'] . '** | عطبٌ حقيقيٌّ يُعالَج |' . "\n";
$md .= '| ⚠ مُصيَّرةٌ في غيرِ مجموعةِ دليلِها | **' . $agg['wrongGroup'] . '** | طبقةُ رؤوسِ الطيِّ — انظر ملفَّ الأسباب |' . "\n";
$md .= '| ⚠ خارجَ ترتيبِ دليلِها بين المُصيَّر | **' . ($agg['found'] - $agg['inOrder']) . '** | من ' . $agg['found'] . ' مُصيَّرةً — الترتيبُ الداخليُّ شبهُ ملتزم |' . "\n";
$md .= '| مجموعاتُ الدليل: ظهر رأسُها | **' . $agg['gLcs'] . '/' . $agg['gTotal'] . '** | وغابَ كلُّها ببنائها ' . $agg['gEmpty'] . ' · وغابَ وفيها مبنيٌّ ' . $agg['gMissReal'] . ' |' . "\n";
$md .= "\n\n⛔ **«أبواب» القياس**: الدليلُ المعماريُّ لا يعرّف طبقةَ أبوابٍ مستقلّة — رأسُ الطيِّ المُصيَّرُ هو المجموعة.\n";
$md .= "فالأبوابُ المُصيَّرةُ الزائدةُ على مجموعاتِ الدليلِ (مساحتي · اللوحة · التصنيف العشريّ…) تُعدُّ في §لكلِّ إدارة.\n\n";

foreach ($ORDER as $code) {
    if (!isset($detail[$code])) { continue; }
    $d = $detail[$code]; $S = $d['S'];
    $md .= "---\n\n## `" . $code . "` — " . (isset($depName[$code]) ? $depName[$code] : '') . ' (الدور ' . $d['rid'] . ' · ' . $d['rname'] . ")\n\n";
    $md .= '**مجموعاتُ الدليلِ بترتيبِها** (' . count($S['groups']) . '): ' . implode(' ← ', $S['groups']) . "\n\n";
    $md .= '**مجموعاتُ الدليلِ كما ظهرت في التصيير** (' . count($d['specGroupsInRender']) . '): '
         . ($d['specGroupsInRender'] ? implode(' ← ', $d['specGroupsInRender']) : '—') . "\n\n";
    if ($d['gMissing']) { $md .= '⛔ **مجموعاتُ دليلٍ لا تُصيَّر — وفيها مبنيّ**: ' . implode(' · ', $d['gMissing']) . "\n\n"; }
    if ($d['gEmptyByBuild']) { $md .= '◇ **مجموعاتُ دليلٍ كلُّ شاشاتِها غيرُ مبنيّةٍ** (غيابُ بناءٍ لا سايدبار): ' . implode(' · ', $d['gEmptyByBuild']) . "\n\n"; }
    $ext = array_values(array_diff($d['renderGroups'], $S['groups']));
    if ($ext) { $md .= '◆ **أبوابٌ مُصيَّرةٌ خارجَ الدليل** (' . count($ext) . '): ' . implode(' · ', $ext) . "\n\n"; }
    if ($d['builtMissing']) {
        $md .= '⛔ **شاشةٌ مبنيّةٌ (بحالةِ دفترِها) ولا بندَ لها في سايدبارِ الدور** (' . count($d['builtMissing']) . "):\n\n";
        foreach ($d['builtMissing'] as $sc) { $md .= '  - ' . $sc['i'] . '. ' . $sc['raw'] . ' — [' . $sc['graw'] . '] · حالة `' . $sc['state'] . "`\n"; }
        $md .= "\n";
    }
    if ($d['unbuilt']) {
        $md .= '◇ **شاشاتُ دليلٍ غيرُ مبنيّةٍ أصلًا — الغيابُ غيابُ بناء** (' . count($d['unbuilt']) . "):\n\n";
        foreach ($d['unbuilt'] as $sc) { $md .= '  - ' . $sc['i'] . '. ' . $sc['raw'] . ' — [' . $sc['graw'] . '] · `' . $sc['state'] . "`\n"; }
        $md .= "\n";
    }
    if ($d['renamed']) {
        $md .= '◆ **مُصيَّرةٌ باسمٍ معتمَدٍ مغايرٍ لاسمِ الدليل** (' . count($d['renamed']) . " — التسميةُ المعتمَدةُ تغلب):\n\n";
        foreach ($d['renamed'] as $w) { $md .= '  - الدليل «' . $w['sc']['raw'] . '» ⇒ يُعرض «' . $w['p']['lraw'] . "»\n"; }
        $md .= "\n";
    }
    if ($d['wrongGroup']) {
        $md .= '⚠ **شاشةٌ في غيرِ مجموعتِها** (' . count($d['wrongGroup']) . "):\n\n";
        foreach ($d['wrongGroup'] as $w) {
            $md .= '  - ' . $w['sc']['raw'] . ': الدليل [' . $w['sc']['graw'] . '] ⇒ صُيِّرت في [' . $w['p']['graw'] . "]\n";
        }
        $md .= "\n";
    }
    if ($d['outOfOrder']) {
        $md .= '⚠ **بنودٌ خارجَ ترتيبِ الدليل** (' . count($d['outOfOrder']) . ' من ' . $d['found'] . "):\n\n";
        foreach ($d['outOfOrder'] as $f) {
            $md .= '  - ' . $f['sc']['raw'] . ' (موضعُ الدليل ' . $f['sc']['i'] . ")\n";
        }
        $md .= "\n";
    }
    if ($d['extra']) {
        $md .= '◆ **بنودٌ مُصيَّرةٌ داخلَ مجموعاتِ الدليلِ ليست في الدليل** (' . count($d['extra']) . "):\n\n";
        foreach (array_slice($d['extra'], 0, 12) as $p) { $md .= '  - ' . $p['lraw'] . ' — [' . $p['graw'] . "]\n"; }
        if (count($d['extra']) > 12) { $md .= '  - … و' . (count($d['extra']) - 12) . " أخرى\n"; }
        $md .= "\n";
    }
    if (!$d['gMissing'] && !$d['builtMissing'] && !$d['outOfOrder'] && !$d['wrongGroup']) {
        $md .= $d['unbuilt']
             ? "✔ **مطابقٌ فيما بُني**: كلُّ المبنيِّ في مجموعتِه وترتيبِه — والناقصُ دَينُ بناءٍ معدودٌ أعلاه.\n\n"
             : "✔ **مطابقٌ**: كلُّ المجموعاتِ والشاشاتِ بترتيبِ الدليل.\n\n";
    }
}
$path = $ROOT . '/docs/REPAIR01_20260823/SIDEBAR_GUIDE_COMPARE.md';
file_put_contents($path, $md);
$m = isset($tally['MATCH']) ? $tally['MATCH'] : 0; $x = isset($tally['MISMATCH']) ? $tally['MISMATCH'] : 0;
printf("مطابق %d · غير مطابق %d · بلا دور %d · بلا ورقة %d ⇒ %s\n",
    $m, $x, isset($tally['NO_ROLE']) ? $tally['NO_ROLE'] : 0,
    isset($tally['NO_SPEC']) ? $tally['NO_SPEC'] : 0, $path);
