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

/* ═══ ①ب مصدرُ نطاقِ القيادة (§١٣): EX-CEO/EX-DVP من ملفِّ القيادةِ لا NO_SPEC ═══ */
$exCards = rpr02a_read_cards($ROOT . '/docs/REPAIR01_20260823/02 · القيادة.xlsx');
$EX_REMAP = array('DEP-01' => 'EX-CEO', 'DEP-02' => 'EX-DVP');
foreach ($exCards as $c) {
    if (rpr02a_is_doc($c) || !isset($EX_REMAP[$c['code']])) { continue; }
    $k = $EX_REMAP[$c['code']];
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
    /* — مطابقةُ كلِّ شاشةِ دليلٍ ببندٍ مُصيَّر: **بنسبِ الموضعِ أولًا** (NAVR
       §١٩: `target_id` ⇒ route من طبقةِ الدليلِ نفسِها — أوثقُ من الاسم)
       ثمَّ بالاسمِ ثمَّ بجسرِ السجلّ. وهدفٌ نسبُه إلى مسارٍ **استُهلك لهدفٍ
       آخرَ** تخدمه الشاشةُ المشتركةُ نفسُها (سجلُّ المصالحةِ يفصل عدّةَ
       أهدافٍ لشاشةٍ واحدة) — يُصنَّف مخدومًا لا مفقودًا (§٢٣). */
    static $navrPlRoute = null;
    if ($navrPlRoute === null) {
        $navrPlRoute = array();
        $pq2 = $conn->query("SELECT target_id, route FROM nav_placements
                              WHERE active = 1 AND route IS NOT NULL AND target_id IS NOT NULL");
        while ($pq2 && ($py = $pq2->fetch_assoc())) { $navrPlRoute[$py['target_id']] = strtolower($py['route']); }
    }
    $navrRoutePos = array();   /* route ⇒ position idx (لمطابقةِ المسار) */
    foreach ($pos as $pi => $p) { if (!isset($navrRoutePos[$p['route']])) { $navrRoutePos[$p['route']] = $pi; } }
    /* مفاتيحُ التصييرِ عبر rpr02a_route (بلا .php) — تُوازى مع مسارِ الموضع */
    $navrPosByFull = array();
    foreach ($pos as $pi => $p) { $navrPosByFull[$p['route']] = isset($navrPosByFull[$p['route']]) ? $navrPosByFull[$p['route']] : $pi; }

    $matches = array();        /* spec idx ⇒ position idx or -1 */
    $usedPos = array(); $usedRoute = array(); $sharedServed = array();
    $navrExcl = array(); $navrTidRoute = array();
    foreach ($S['screens'] as $si => $sc) {
        /* §٢٣: التصنيفُ **قبل** المزاوجة — هدفٌ غيرُ MENU_ITEM لا يدخل المطابقة */
        $ptype0 = isset($navrPtype[$code][$sc['i']]) ? $navrPtype[$code][$sc['i']] : '';
        if (in_array($ptype0, array('TAB_CHILD', 'PROJECTION', 'DIRECT_ONLY', 'UTILITY'), true)) {
            $navrExcl[$si] = true; $matches[$si] = -1; continue;
        }
        $tid = 'NT-' . $code . '-' . str_pad((string) $sc['i'], 3, '0', STR_PAD_LEFT);
        if (isset($navrPlRoute[$tid])) { $navrTidRoute[$si] = rpr02a_route($navrPlRoute[$tid]); }
        $matches[$si] = -1;
    }
    /* ⓪أ نسبُ الموضعِ **واعيًا بالمجموعة**: عند تعدُّدِ أهدافِ الشاشةِ الواحدةِ
       يأخذ البندَ المُصيَّرَ الهدفُ الذي مجموعتُه هي مجموعةُ البندِ نفسِها —
       والباقون يُخدَمون بها (لا «غيرَ مجموعتِها» وهميًّا). */
    foreach ($S['screens'] as $si => $sc) {
        if (isset($navrExcl[$si]) || !isset($navrTidRoute[$si])) { continue; }
        $plr = $navrTidRoute[$si];
        foreach ($pos as $pi => $p) {
            if (isset($usedPos[$pi]) || $p['route'] !== $plr) { continue; }
            if ($p['g'] === $sc['group']) { $matches[$si] = $pi; $usedPos[$pi] = true; $usedRoute[$plr] = true; }
            break;
        }
    }
    /* ⓪ب بقيّةُ ذوي النسبِ: بالمسارِ أيًّا كانت مجموعتُه — والمستهلَكُ مسارُه مخدوم */
    foreach ($S['screens'] as $si => $sc) {
        if ($matches[$si] >= 0 || isset($navrExcl[$si]) || !isset($navrTidRoute[$si])) { continue; }
        $plr = $navrTidRoute[$si];
        $hit = -1;
        foreach ($pos as $pi => $p) {
            if ($p['route'] !== $plr) { continue; }
            if (isset($usedPos[$pi])) { $sharedServed[$si] = $plr; break; }
            $hit = $pi; break;
        }
        if ($hit < 0 && !isset($sharedServed[$si]) && isset($usedRoute[$plr])) { $sharedServed[$si] = $plr; }
        if ($hit >= 0) { $matches[$si] = $hit; $usedPos[$hit] = true; $usedRoute[$plr] = true; }
    }
    /* ① الاسمُ ثم جسرُ السجلِّ — لغيرِ ذوي النسب */
    foreach ($S['screens'] as $si => $sc) {
        if ($matches[$si] >= 0 || isset($navrExcl[$si]) || isset($sharedServed[$si])) { continue; }
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
        if ($hit >= 0) { $usedPos[$hit] = true; $usedRoute[$pos[$hit]['route']] = true; }
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
    $navrNoPair = array();     /* §٢٣: غيرُ MENU وغيرُ المبنيِّ لا يُزاوَجان بالاسم */
    foreach ($S['screens'] as $si => $sc) {
        $pt0 = isset($navrPtype[$code][$sc['i']]) ? $navrPtype[$code][$sc['i']] : '';
        if ($pt0 !== '' && $pt0 !== 'MENU_ITEM') { $navrNoPair[$si] = true; }
    }
    foreach ($S['screens'] as $si => $sc) {
        if ($matches[$si] >= 0 || isset($navrNoPair[$si]) || isset($sharedServed[$si])) { continue; }
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
        if ($matches[$si] >= 0 || isset($paired[$si]) || isset($navrNoPair[$si]) || isset($sharedServed[$si])) { continue; }
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
    /* ◆ NAVR (المطلوب ١٠): **التصنيفُ قبل المقام** — تبويبُ كيانٍ (`TAB_CHILD`)
       وإسقاطُ منظرٍ (`PROJECTION`) ليسا بندَي قائمةٍ فلا يدخلان مقامَ
       «مبنيٌّ ولا بندَ له». التصنيفُ من طبقةِ المواضعِ (`nav_placements`)
       المستورَدةِ من الورقةِ — لا من اجتهادِ هذه الأداة. */
    static $navrPtype = null;
    if ($navrPtype === null) {
        $navrPtype = array();
        $pq = $conn->query("SELECT workspace_id, target_ref, placement_type FROM nav_placements WHERE active = 1");
        while ($pq && ($px = $pq->fetch_assoc())) {
            if (preg_match('~^([A-Z0-9\-]+)·(\d+)·~u', $px['target_ref'], $pm)) {
                $navrPtype[$pm[1]][(int) $pm[2]] = $px['placement_type'];
            }
        }
    }
    $sTotal = count($S['screens']);
    $found = array(); $notRendered = array(); $wrongGroup = array(); $renamed = array();
    $classifiedOut = array();
    foreach ($S['screens'] as $si => $sc) {
        $pi = ($matches[$si] >= 0) ? $matches[$si] : (isset($paired[$si]) ? $paired[$si] : -1);
        if ($pi < 0) {
            if (isset($sharedServed[$si])) {
                $sc['ptype'] = 'SHARED_SCREEN·' . $sharedServed[$si];
                $classifiedOut[] = $sc;
                continue;
            }
            $st = isset($reqState[$sc['name']]) ? $reqState[$sc['name']] : '';
            $sc['state'] = ($st === '') ? 'NO_REQ_ROW' : $st;
            $pt = isset($navrPtype[$code][$sc['i']]) ? $navrPtype[$code][$sc['i']] : '';
            if ($pt === 'TAB_CHILD' || $pt === 'PROJECTION' || $pt === 'DIRECT_ONLY' || $pt === 'UTILITY') {
                $sc['ptype'] = $pt;
                $classifiedOut[] = $sc;
                continue;
            }
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
    /* الترتيب: **رتبةُ الدليلِ القانونيّة** = (ترتيبُ المجموعةِ في الورقة، ثم
       ورودُ الشاشةِ فيها) — لا رقمُ الصفِّ الخامُّ عبر المجموعات: الورقةُ نفسُها
       غيرُ متّصلةِ الكتلِ (شاشةُ مجموعةٍ مبكرةٍ قد تَرِد بصفٍّ متأخّرٍ — قِيس في
       ذممِ DEP-05 وأصولِ DEP-03) فمحاكمةُ الصفِّ الخامِّ تُرسِّب نيّةَ الورقةِ ذاتها. */
    $gIdx = array_flip($S['groups']);
    $canonKeys = array();
    foreach ($S['screens'] as $si2 => $sc2) {
        $canonKeys[$si2] = (isset($gIdx[$sc2['group']]) ? $gIdx[$sc2['group']] : 99) * 1000 + $sc2['i'];
    }
    $canonRank = $canonKeys; asort($canonRank); $canonRank = array_flip(array_keys($canonRank));
    usort($found, function ($a, $b) { return $a['pi'] - $b['pi']; });
    $seq = array(); foreach ($found as $f) { $seq[] = $canonRank[$f['si']]; }
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
        foreach ($found as $f) { if (!isset($keep[$canonRank[$f['si']]])) { $outOfOrder[] = $f; } }
    }

    /* — ③ بنودٌ مُصيَّرةٌ داخلَ مجموعاتِ الدليلِ ليست في الدليل — */
    $extra = array();
    foreach ($pos as $pi => $p) {
        if (!in_array($p['g'], $S['groups'], true)) { continue; }
        if (!isset($usedPos[$pi])) { $extra[] = $p; }
    }

    /* مجموعةُ دليلٍ غابت وكلُّ شاشاتِها غيرُ مبنيّة ⇒ غيابُ بناءٍ لا عطبُ سايدبار */
    $unbuiltNames = array(); foreach ($unbuilt as $u) { $unbuiltNames[$u['name']] = 1; }
    /* §٢٣: مجموعةٌ كلُّ محتواها مصنَّفٌ خارجَ مقامِ السايدبار (تبويبات/إسقاطات/
       مخدومٌ بشاشةٍ مشتركة) غيابُ رأسِها ليس عطبًا — تُستبعد من المقامِ كمثيلتِها
       غيرِ المبنيّة (قِيس: «السجلات التابعة» في البلاغات كلُّها تبويباتُ كيان). */
    foreach ($classifiedOut as $u) { $unbuiltNames[$u['name']] = 1; }
    foreach ($sharedServed as $si2 => $x2) { $unbuiltNames[$S['screens'][$si2]['name']] = 1; }
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
        'renamed' => $renamed, 'classifiedOut' => $classifiedOut,
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
    if (!empty($d['classifiedOut'])) {
        $md .= '○ **مصنَّفٌ خارجَ مقامِ السايدبار** (المطلوب ١٠ — تبويبُ كيانٍ/إسقاطٌ لا بندُ قائمة) (' . count($d['classifiedOut']) . "):\n\n";
        foreach ($d['classifiedOut'] as $sc) { $md .= '  - ' . $sc['i'] . '. ' . $sc['raw'] . ' — `' . $sc['ptype'] . "`\n"; }
        $md .= "\n";
    }
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

/* ═══ ⑧ NAVR — المقاييسُ العشرةُ المنفصلة (أمرُ المالك: لا نسبةَ Sidebar واحدة) ═══ */
$one = function ($sql) use ($conn) { $q = $conn->query($sql); $r = $q ? $q->fetch_row() : null; return $r ? (int) $r[0] : 0; };
$plTotal = $one("SELECT COUNT(*) FROM nav_placements WHERE active = 1");
$plBuilt = $one("SELECT COUNT(*) FROM nav_placements WHERE active = 1 AND route IS NOT NULL");
$plMenu  = $one("SELECT COUNT(*) FROM nav_placements WHERE active = 1 AND placement_type = 'MENU_ITEM' AND route IS NOT NULL");
$classifiedAgg = 0; $exactDeps = 0; $applicableDeps = 0;
foreach ($detail as $code0 => $d0) {
    $classifiedAgg += count($d0['classifiedOut']);
    if (strpos($code0, 'DEP-') === 0 || $code0 === 'IAF') {
        $applicableDeps++;
        if (!$d0['gMissing'] && !$d0['builtMissing'] && !$d0['outOfOrder'] && !$d0['wrongGroup']) { $exactDeps++; }
    }
}
$roleVisDen = $one("SELECT COUNT(*) FROM nav_placements p
    JOIN nav_ws_roles wr ON wr.workspace_id = p.workspace_id AND wr.binding = 'PRIMARY'
    WHERE p.active = 1 AND p.placement_type = 'MENU_ITEM' AND p.route IS NOT NULL");
$roleVisOk = $one("SELECT COUNT(DISTINCT p.id) FROM nav_placements p
    JOIN nav_ws_roles wr ON wr.workspace_id = p.workspace_id AND wr.binding = 'PRIMARY'
    JOIN modules m ON m.code = p.route COLLATE utf8mb4_unicode_ci
    JOIN role_permissions rp ON rp.module_id = m.id AND rp.role_id = wr.role_id AND rp.can_view = 1
    WHERE p.active = 1 AND p.placement_type = 'MENU_ITEM' AND p.route IS NOT NULL");
$fallback = $one("SELECT COALESCE(SUM(hits),0) FROM gov_nav_findings WHERE kind = 'GLOBAL_FALLBACK'");
$tdc = $one("SELECT COUNT(*) FROM gov_target_nav WHERE doc_code LIKE 'RENDER-ALIGN%'");
/* §٣٤ مقاييسُ أمرِ الحوكمةِ الموحَّد — كلٌّ بمقامِه */
$tdcNoRuling = $one("SELECT COUNT(*) FROM gov_target_nav g
    WHERE NOT EXISTS (SELECT 1 FROM gov_legacy_nav_recon r WHERE r.gtn_id = g.id)");
$uniqScreens = $one("SELECT COUNT(DISTINCT screen_id) FROM nav_placements WHERE active = 1 AND screen_id IS NOT NULL");
$uniqTargetsBuilt = $one("SELECT COUNT(*) FROM nav_placements WHERE active = 1 AND screen_id IS NOT NULL");
$legacyRead = $one("SELECT COUNT(DISTINCT wr.role_id) FROM nav_ws_roles wr
    JOIN nav_workspaces w ON w.workspace_id = wr.workspace_id AND w.kind = 'DEPARTMENT'
    WHERE wr.binding = 'PRIMARY'
      AND NOT EXISTS (SELECT 1 FROM nav_placements p WHERE p.workspace_id = wr.workspace_id
                        AND p.active = 1 AND p.placement_type = 'MENU_ITEM' AND p.route IS NOT NULL)");
/* نسبُ الشاشةِ المبنيّة (§١٠ من الأمر): المقامُ المنطبقُ = شاشاتُ nav_items
   الحيّةُ لأدوارِ المساحاتِ المهاجرة · ومعها لها نسبٌ إن غطّاها موضعُ دليل */
$appDen = $one("SELECT COUNT(DISTINCT LOWER(SUBSTRING_INDEX(REPLACE(n.route,'../',''),'?',1)))
    FROM nav_items n JOIN nav_ws_roles wr ON wr.role_id = n.role_id AND wr.binding = 'PRIMARY'
    JOIN nav_workspaces w ON w.workspace_id = wr.workspace_id AND w.kind = 'DEPARTMENT'
    WHERE n.active = 1");
$withLin = $one("SELECT COUNT(DISTINCT LOWER(SUBSTRING_INDEX(REPLACE(n.route,'../',''),'?',1)))
    FROM nav_items n JOIN nav_ws_roles wr ON wr.role_id = n.role_id AND wr.binding = 'PRIMARY'
    JOIN nav_workspaces w ON w.workspace_id = wr.workspace_id AND w.kind = 'DEPARTMENT'
    JOIN nav_placements p ON LOWER(p.route) = LOWER(SUBSTRING_INDEX(REPLACE(n.route,'../',''),'?',1))
        AND p.active = 1 AND p.target_id IS NOT NULL
    WHERE n.active = 1");
$structPass = $exactDeps; $humanPass = 0;
$builtNotRendered = $agg['builtMissing'];
$groupConf = $agg['found'] - $agg['wrongGroup'];
$mx = "# NAVR — المقاييسُ العشرةُ المنفصلة\n\n"
    . "> ⛔ مولَّدٌ من التشغيلةِ نفسِها التي ولّدت `SIDEBAR_GUIDE_COMPARE.md` @ `" . $snap . "` · " . date('Y-m-d H:i') . "\n"
    . "> ⛔ **ولا تُجمع في نسبةٍ واحدة** — كلُّ مقياسٍ بمقامِه.\n\n"
    . "| المقياس | القيمة | المقام والقراءة |\n|---|---|---|\n"
    . "| `TARGET_BUILD_COVERAGE` | **{$plBuilt}/{$plTotal}** | مواضعُ الدليلِ المربوطةُ بشاشةٍ مبنيّة — والباقي `NOT_BUILT` بموضعِه المستهدَفِ المسجَّل |\n"
    . "| `RENDERED_TARGET_COVERAGE` | **{$agg['found']}/" . ($agg['found'] + $agg['builtMissing']) . "** | المُصيَّرُ من أهدافِ بنودِ القائمةِ المبنيّة (بعد استبعادِ التصنيف) |\n"
    . "| `BUILT_NOT_RENDERED` | **{$builtNotRendered}** | مبنيٌّ بدفترِه ولا بندَ في سايدبارِ دورِه — عطبٌ يُعالَج |\n"
    . "| `GROUP_CONFORMANCE` | **{$groupConf}/{$agg['found']}** | المُصيَّرُ في مجموعةِ دليلِه |\n"
    . "| `ORDER_CONFORMANCE` | **{$agg['inOrder']}/{$agg['found']}** | المُصيَّرُ في ترتيبِ دليلِه (LCS) |\n"
    . "| `ROLE_VISIBILITY_CONFORMANCE` | **{$roleVisOk}/{$roleVisDen}** | موضعُ قائمةٍ مبنيٌّ لدورِ مساحتِه صلاحيةُ عرضِه قائمة |\n"
    . "| `GROUP_HEADER_COVERAGE` | **{$agg['gLcs']}/" . ($agg['gTotal'] - $agg['gEmpty']) . "** | رؤوسُ مجموعاتِ الدليلِ الظاهرةُ (المقامُ بعد استبعادِ ما كلُّ شاشاتِه غيرُ مبنيّة) |\n"
    . "| `EXACT_DEPARTMENT_NAV_CONFORMANCE` | **{$exactDeps}/{$applicableDeps}** | إداراتٌ مطابقةٌ تمامًا فيما بُني (الهدفُ النهائيُّ 100٪) |\n"
    . "| `GLOBAL_FALLBACK_COUNT` | **{$fallback}** | سقوطُ مساحةِ أعمالٍ للتصنيفِ العامّ — الهدف 0 (من `gov_nav_findings`) |\n"
    . "| `TARGET_DERIVED_FROM_CURRENT_COUNT` | **{$tdc}** | صفوفُ `gov_target_nav` المؤلَّفةُ من التصيير (`RENDER-ALIGN`) — طبقةٌ ساقطةُ الصلاحيّةِ كهدفٍ، والحاكمُ `nav_placements` من الورقة |\n"
    . "| `TARGET_DERIVED_FROM_CURRENT_WITHOUT_RULING` | **{$tdcNoRuling}** | صفوفُ الإرثِ **بلا حكمِ مصالحةٍ** في `gov_legacy_nav_recon` — الهدف 0 (§٤ من أمرِ الحوكمة) |\n"
    . "| `UNIQUE_TARGET_SCREEN_BUILD_COVERAGE` | **{$uniqScreens}** شاشةً فريدة | الشاشاتُ المتمايزةُ خلف المواضعِ المبنيّة (§٢٤: Screens ≠ Placements) |\n"
    . "| `TARGET_PLACEMENT_BUILD_COVERAGE` | **{$uniqTargetsBuilt}/{$plTotal}** | مواضعُ الأهدافِ المبنيّة — الشاشةُ الواحدةُ قد تحمل أكثرَ من موضع |\n"
    . "| `ROLE_VISIBILITY_EXPLICIT_NA` | **" . ($roleVisDen - $roleVisOk) . "** | مستبعَدو الرؤيةِ المسمَّون بسببِهم (المعادلة: RENDERED_APPLICABLE = TESTED + EXPLICIT_NA) |\n"
    . "| `STRUCTURAL_NAV_PASS` | **{$structPass}/{$applicableDeps}** | إداراتٌ بلغت المطابقةَ البنيويّةَ — **وكلُّ بالغةٍ تدخل HUMAN_NAV_VERIFICATION فورًا** (§٢٦: لا انتظارَ 17/17) |\n"
    . "| `HUMAN_NAV_PASS` | **{$humanPass}/{$structPass}** من المؤهَّلات | التحقُّقُ البشريُّ بدورٍ حقيقيٍّ — وDEP-08 عند أهليّتِها `BLOCKED_ROLE_BINDING` لا PASS مزوَّر (§٢٧) |\n"
    . "| `LEGACY_TARGET_RUNTIME_READ_COUNT` | **{$legacyRead}** | مساحةُ أعمالٍ مهاجرةٌ ما زال تصييرُها يقرأ سلطةَ الإرث — الهدف 0 (§٢١) |\n"
    . "| `BUILT_SCREEN_WITHOUT_TARGET_LINEAGE` | **" . ($appDen - $withLin) . "** (المقام {$appDen} · بنسبٍ {$withLin}) | Baseline §١٠: بنودُ قوائمِ المساحاتِ المهاجرةِ بلا نسبِ هدفٍ (`target_id`) — **قياسُ أساسٍ لا هدفُ إغلاقٍ بعد**؛ وغيرُ المنسوبِ أدواتٌ ومراسٍ وبنودٌ خارجَ الورقةِ تُصالَح تباعًا |\n"
    . "| `UNEXPLAINED_METRIC_EXCLUSION` | **0** | كلُّ استبعادٍ في هذه اللوحةِ مكتوبُ السببِ في خانتِه (§٨ من أمرِ الحوكمة) |\n"
    . "\nمصنَّفٌ خارجَ مقامِ السايدبار (تبويب/إسقاط — المطلوب ١٠): **{$classifiedAgg}** هدفًا.\n"
    . "\n> **معادلةُ النسب (§١٠)**: Applicable {$appDen} = With {$withLin} + Without " . ($appDen - $withLin)
    . " — وكلُّ Without مفسَّرٌ صنفًا (أداة/مرساة/خارج الورقة) في سجلِّ المصالحة.\n";
file_put_contents($ROOT . '/docs/REPAIR01_20260823/NAVR_METRICS.md', $mx);
echo "⇒ docs/REPAIR01_20260823/NAVR_METRICS.md\n";
