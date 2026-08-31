<?php
/**
 * tools/lib/navr_bridge.php — جسرُ حملةِ NAVR الواحد: دليل ⇄ سجلّ ⇄ دور
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **جسرٌ واحدٌ لا جسران** [[nav-route-two-sources]]: هذا استخراجُ جسرِ
 *   `tools/sidebar_guide_compare.php` حرفًا (إدارة⇒دور · label⇒screen_file ·
 *   حالةُ البناء · تطبيعُ المجموعة) مكتبةً يستهلكها القياسُ والاستيرادُ
 *   والمقاييسُ معًا — فلا يتفرّق منطقان بأوّلِ تعديل.
 * ◆ ويعتمد `rpr02a_guide.php` لقراءةِ بطاقاتِ الدليل.
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/rpr02a_guide.php';

/** تطبيعُ اسمِ مجموعةٍ — كما في sidebar_guide_compare::sgc_gz حرفًا. */
function navr_gz($s)
{
    $s = rpr02a_nz($s);
    $s = strtr($s, array("–" => "—", "-" => "—"));
    $s = preg_replace('~\s*\([A-Za-z ]+\)\s*$~u', '', $s);
    return trim($s);
}

/** مواصفةُ الدليل: code ⇒ groups(مرتّبة) + screens[{i,group,name,raw,type}] */
function navr_guide_spec($ROOT)
{
    $cards = rpr02a_read_cards($ROOT . '/docs/REPAIR01_20260823/01 · الدليل المعماري.xlsx');
    $spec = array();
    foreach ($cards as $c) {
        if (rpr02a_is_doc($c)) { continue; }
        $k = $c['code'];
        if (!isset($spec[$k])) { $spec[$k] = array('groups' => array(), 'screens' => array()); }
        $gn = navr_gz($c['group']);
        if (!in_array($gn, $spec[$k]['groups'], true)) {
            $spec[$k]['groups'][] = $gn;
            /* اسمُ العرضِ الخامُّ من أوّلِ ورودٍ — المفتاحُ مطبَّعٌ والعرضُ بحرفِ الورقة */
            $spec[$k]['group_labels'][$gn] = trim((string) $c['group']);
        }
        $spec[$k]['screens'][] = array(
            'i' => count($spec[$k]['screens']) + 1,
            'group' => $gn, 'name' => rpr02a_nz($c['name']),
            'raw' => $c['name'], 'graw' => $c['group'], 'type' => (string) $c['type'],
        );
    }
    return $spec;
}

/** جسرُ إدارة ⇒ دور — منطقُ repair01_guide_nav_apply/sidebar_guide_compare حرفًا. */
function navr_dep_roles(mysqli $conn)
{
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
    /* الإسناداتُ الأربعُ الصريحةُ بسندِها — كما في أداةِ القياس */
    foreach (array('DEP-06' => 21, 'DEP-17' => 25, 'EX-CEO' => 9, 'IAF' => 33) as $k => $v) {
        if (!isset($depRole[$k])) {
            $q = $conn->query('SELECT id, name FROM roles WHERE id = ' . $v . ' LIMIT 1');
            if ($q && $q->num_rows) { $y = $q->fetch_assoc(); $depRole[$k] = array((int) $y['id'], $y['name']); }
        }
    }
    return array('roles' => $depRole, 'names' => $depName);
}

/** تطبيعُ مسارٍ إلى مفتاحِ المُصيِّر (كـ`uxuiNavBaseRoute` حرفًا): كاملُ
 *  المجلّدِ + `.php` · صغيرُ الحرف · بلا `../` ولا `?#`. */
function navr_route_key($r)
{
    $r = preg_replace('~^(\.\./)+~', '', trim((string) $r));
    $r = preg_replace('/[?#].*$/u', '', $r);
    return strtolower(trim($r, '/'));
}

/** جسرُ السجلّ: label ⇒ route الكامل (بمداه dep| ثم العامّ *|) + screen_id.
 *  ◆ **`route` لا `screen_file`**: الملفُّ اسمٌ مجرّدٌ (810/813 بلا مجلّد)
 *    ومفاتيحُ المُصيِّرِ كاملةُ المسار — وجسرُ أداةِ المقارنةِ القديمُ كان
 *    احتياطُه الملفّيُّ لا يطابق أبدًا بهذا (كشفٌ NAVR-B1). */
function navr_label_routes(mysqli $conn)
{
    $regFile = array(); $regId = array();
    $r = $conn->query('SELECT screen_id, owner_code, canonical_label_ar, route
                         FROM repair01_screen_registry
                        WHERE canonical_label_ar IS NOT NULL AND canonical_label_ar <> ""
                          AND route IS NOT NULL AND route <> ""');
    while ($x = $r->fetch_assoc()) {
        $l = rpr02a_nz($x['canonical_label_ar']); $f = navr_route_key($x['route']);
        $regFile[$x['owner_code'] . '|' . $l] = $f;
        $regId[$x['owner_code'] . '|' . $l] = $x['screen_id'];
        if (!isset($regFile['*|' . $l])) { $regFile['*|' . $l] = $f; $regId['*|' . $l] = $x['screen_id']; }
    }
    return array('file' => $regFile, 'id' => $regId);
}

/** حالةُ بناءِ الهدف من دفترِ المتطلّبات [[two-registers-target-vs-built]]. */
function navr_req_state(mysqli $conn)
{
    $reqState = array();
    $r = $conn->query('SELECT surface, amd01_state FROM repair01_requirements
                        WHERE surface IS NOT NULL AND surface <> ""');
    while ($x = $r->fetch_assoc()) {
        $k = rpr02a_nz($x['surface']);
        if (!isset($reqState[$k])) { $reqState[$k] = $x['amd01_state']; }
    }
    return $reqState;
}

/** تقاطعُ مفرداتِ اسمَين — كما في أداةِ القياس (للاسمِ المعتمَدِ المغاير). */
function navr_tokens($s)
{
    $stop = array('في', 'من', 'على', 'الي', 'و', 'او', 'ما', 'لل', 'ال');
    $t = preg_split('~[\s·/،-]+~u', $s);
    $out = array();
    foreach ($t as $w) { $w = trim($w); if ($w !== '' && !in_array($w, $stop, true) && mb_strlen($w) > 2) { $out[$w] = 1; } }
    return $out;
}
function navr_overlap($a, $b)
{
    $ta = navr_tokens($a); $tb = navr_tokens($b);
    if (!$ta || !$tb) { return 0.0; }
    $i = count(array_intersect_key($ta, $tb));
    return $i / min(count($ta), count($tb));
}

/**
 * حلُّ شاشةِ دليلٍ إلى هويّتِها المبنيّة: [screen_id, route, how] أو [null,null,'UNRESOLVED'].
 * الترتيب: label بمدى الإدارةِ ⇒ label عامّ ⇒ تقاطعُ مفرداتٍ ≥0.65 وحيدُ الأفضليّةِ
 * على تسمياتِ سجلِّ الإدارةِ نفسِها.
 */
function navr_resolve_screen(mysqli $conn, $code, $name, array $bridge)
{
    /* ⓪ **جسرُ الفصلِ الرسميُّ أوّلًا**: `repair01_target_universe` سجلُّ
       مصالحةِ هدف⇒شاشة بحكمٍ مكتوبٍ (MATCHED/MERGED_INTO بشاهدِه) — يغلب
       كلَّ مطابقةِ اسمٍ لأنه قرارٌ مسجَّلٌ لا اجتهادُ تطبيع. */
    static $tu = null; static $regRoute = null;
    if ($tu === null) {
        $tu = array(); $regRoute = array();
        $r = $conn->query("SELECT unit, name_norm, screen_id FROM repair01_target_universe
                            WHERE verdict IN ('MATCHED','MERGED_INTO') AND screen_id IS NOT NULL AND screen_id <> ''");
        while ($x = $r->fetch_assoc()) { $tu[$x['unit'] . '|' . rpr02a_nz($x['name_norm'])] = $x['screen_id']; }
        $r = $conn->query("SELECT screen_id, route FROM repair01_screen_registry
                            WHERE route IS NOT NULL AND route <> ''");
        while ($x = $r->fetch_assoc()) { $regRoute[$x['screen_id']] = navr_route_key($x['route']); }
    }
    $tk = $code . '|' . $name;
    if (isset($tu[$tk]) && isset($regRoute[$tu[$tk]])) {
        return array($tu[$tk], $regRoute[$tu[$tk]], 'TARGET_UNIVERSE');
    }
    $k1 = $code . '|' . $name; $k2 = '*|' . $name;
    if (isset($bridge['file'][$k1])) { return array($bridge['id'][$k1], $bridge['file'][$k1], 'LABEL_DEP'); }
    if (isset($bridge['file'][$k2])) { return array($bridge['id'][$k2], $bridge['file'][$k2], 'LABEL_ANY'); }
    static $byDep = null;
    if ($byDep === null) {
        $byDep = array();
        $r = $conn->query('SELECT screen_id, owner_code, canonical_label_ar, route
                             FROM repair01_screen_registry
                            WHERE canonical_label_ar IS NOT NULL AND canonical_label_ar <> ""
                              AND route IS NOT NULL AND route <> ""');
        while ($x = $r->fetch_assoc()) {
            $byDep[$x['owner_code']][] = array($x['screen_id'], rpr02a_nz($x['canonical_label_ar']), navr_route_key($x['route']));
        }
    }
    $best = null; $bo = 0.0; $second = 0.0;
    foreach ((array) ($byDep[$code] ?? array()) as $row) {
        $o = navr_overlap($row[1], $name);
        if ($o > $bo) { $second = $bo; $bo = $o; $best = $row; }
        elseif ($o > $second) { $second = $o; }
    }
    if ($best !== null && $bo >= 0.65 && $bo > $second) { return array($best[0], $best[2], 'OVERLAP_DEP'); }
    return array(null, null, 'UNRESOLVED');
}
