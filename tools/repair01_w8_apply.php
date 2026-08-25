<?php
/**
 * tools/repair01_w8_apply.php — تنفيذُ المرحلةِ الثامنة (المبيعات والموردون)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **انحدارٌ لا إعادةُ بناء** (§٢ · §19): الوحدتانِ مرجعيّتان. فهذه الأداةُ
 *   **لا تبني سطحًا** ولا تسجّل نموًّا في `repair01_screen_registry` — وكلُّ
 *   ما تكتبه إمّا **دفترُ قياسٍ** وإمّا **إصلاحٌ بمتطلَّبٍ كاشفٍ مسجَّل**.
 *
 * ◆ **السايدبارُ قبل الشاشات** (§٤ · RPR-PATCH-01 ③): الخطواتُ السبعُ بترتيبها
 *   قبل أيِّ لمسةٍ على سطح.
 *
 * ◆ **والمُعلَنُ بعددِه لا يُدهَس**: أربعةُ سقوطاتِ انحدارٍ سببُها **واقعٌ قائمٌ
 *   لا آلةٌ ناقصة** (‏بقايا فاحصٍ · بياناتُ تأهيلٍ ناقصة · فضاءُ مفاتيحَ قديم)،
 *   وتُسجَّل قراراتٍ **بعددِها المقيسِ** — والبوّابةُ تسقط لحظةَ يتحرَّك العدد.
 *   وهو نمطُ `W5-D-09` و`W7-D-04` نفسُه: **يُعلَن ولا يُخمَّن ولا يُمحى**.
 *
 * ⛔ **ولا حذفَ صفٍّ ولا إعادةَ ترقيم** — والبقايا دليلٌ يُقرأ لا قمامةٌ تُكنس.
 *
 * التشغيل: php tools/repair01_w8_apply.php
 * التراجع: php tools/repair01_w8_apply.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
require_once $ROOT . '/tools/lib/repair01_w8_scan.php';
require_once $ROOT . '/tools/lib/repair01_w8_contracts.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$REVERT = in_array('--revert', array_slice($argv, 1), true);
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w8_one($conn, $sql); };
$W = function ($sql) use ($conn) {
    if ($conn->query($sql) === false) { echo "  ⚠ " . $conn->error . "\n   ⇐ " . mb_substr($sql, 0, 140) . "\n"; return false; }
    return true;
};
function say($s) { echo "  $s\n"; }

/* ═══════════════════════════════════════════════════════════════════════════
   التراجع — يعكس التبعيّة: ما كتبته الأداةُ يُنزع، وما لم تُنشئه يبقى
   ═══════════════════════════════════════════════════════════════════════════ */
if ($REVERT) {
    echo "══ تراجعُ W08 — نزعُ ما كتبته هذه الأداة ══\n\n";
    $W("DELETE FROM repair01_w8_sidebar");
    $W("DELETE FROM repair01_w8_scope");
    $W("DELETE FROM repair01_w8_states");
    $W("DELETE FROM repair01_w8_sod");
    $W("DELETE FROM repair01_w8_thresholds");
    $W("DELETE FROM repair01_w8_decisions");
    $W("DELETE FROM repair01_w8_journey");
    $W("DELETE FROM repair01_w8_fixes");
    /* عقودُ الأثرِ تعود إلى ما قبلَ الوسم — ولا يُحذف صفُّ الحدثِ نفسُه */
    $W("UPDATE repair01_events SET contract_status='NONE', contract_rule='', contract_stage='',
          trigger_rule='', min_payload='', consumer_list=NULL, consumer_effect=NULL,
          preconditions='', failure_policy='', compensation='' WHERE contract_stage='W08'");
    /* الإصلاحاتُ البيانيّةُ تُنزع بمفتاحِها المكتوبِ لا بمسحٍ عامّ */
    $W("UPDATE claims SET receivable_id = NULL WHERE receivable_id IS NOT NULL
          AND EXISTS (SELECT 1 FROM fin_receivables r WHERE r.id = claims.receivable_id
                        AND r.doc_type='invoice' AND r.doc_ref = claims.invoice_no)");
    $W("UPDATE repair01_screen_registry SET owner_code='', owner_role='', owner_rule='W2_DECISION:W2-D-01'
         WHERE owner_rule = 'W8_OWNER_FROM_W2D01'");
    say('تُرك خارجَ التراجع: `repair01_w8_regression` — شوطُ الأساسِ دليلُ ما قبلَ اللمس.');
    say('و`repair01_target_gaps.wave_stage` يُعاد بتشغيلِ `php tools/repair01_w2_apply.php` وحدَه.');
    echo "\nتمَّ التراجع.\n";
    exit(0);
}

echo "══ REPAIR01 · W08 — المبيعاتُ والموردون: انحدارٌ لا إعادةُ بناء ══\n\n";

$REQN = (int) $one("SELECT COUNT(*) FROM repair01_requirements WHERE stage_no = 8");

/* ═══════════════════════════════════════════════════════════════════════════
   ① دفترُ النطاق — كلُّ متطلَّبٍ بمِرساةٍ مُثبَتةٍ من القرص
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① دفترُ النطاقِ — المِرساةُ مُثبَتةٌ لا مُعلَنة ──────────────────\n";
$ANCH = repair01_w8_anchors();
$W("DELETE FROM repair01_w8_scope");
$proven = 0; $gaps = 0; $unproven = 0; $mismatch = 0;

$rs = $conn->query("SELECT requirement_id, unit, group_name, surface, src_ref
                      FROM repair01_requirements WHERE stage_no = 8 ORDER BY unit, seq");
while ($rs && $r = $rs->fetch_assoc()) {
    $rid = $r['requirement_id'];
    $a = isset($ANCH[$rid]) ? $ANCH[$rid] : array('route' => '', 'probe' => '', 'kind' => 'GAP',
                                                  'why' => 'لا مرساة معلنة لهذا المتطلب');
    $p = repair01_w8_prove_anchor($conn, $ROOT, $a);
    $expected = preg_match('/^(\d{2})\s/u', $r['unit'], $m) ? 'DEP-' . $m[1] : '';
    $verdict = ($p['owner'] === '' || $expected === '') ? '' : ($p['owner'] === $expected ? 'MATCH' : 'MISMATCH');
    if ($verdict === 'MISMATCH') { $mismatch++; }
    if ($p['verdict'] === 'ANCHORED') { $proven++; }
    elseif ($p['verdict'] === 'NOT_BUILT') { $gaps++; }
    else { $unproven++; }
    $W("INSERT INTO repair01_w8_scope
          (requirement_id,unit,group_name,surface,anchor_screen_id,anchor_route,anchor_probe,
           owner_measured,owner_expected,owner_verdict,map_rule,map_why,wave_stage,src_ref)
        VALUES ('" . $esc($rid) . "','" . $esc($r['unit']) . "','" . $esc($r['group_name']) . "','"
        . $esc($r['surface']) . "','" . $esc($p['sid']) . "','" . $esc($a['route']) . "','"
        . $esc($a['probe']) . "','" . $esc($p['owner']) . "','" . $esc($expected) . "','"
        . $esc($verdict) . "','" . $esc($p['rule']) . "','" . $esc($a['why']) . "','W08','"
        . $esc($r['src_ref']) . "')");
}
printf("  متطلَّباتُ المرحلة %d · مِرساةٌ مُثبَتةٌ %d · فجوةٌ مُعلَنةٌ %d · لم تُثبَت %d · مالكٌ مخالفٌ %d\n\n",
       $REQN, $proven, $gaps, $unproven, $mismatch);

/* ═══════════════════════════════════════════════════════════════════════════
   ② السايدبار — الخطواتُ السبعُ بترتيبها قبلَ أيِّ سطح
   ═══════════════════════════════════════════════════════════════════════════ */
echo "② السايدبارُ — سبعُ خطواتٍ على أسطحِ النطاق ────────────────────\n";
$codes = repair01_w8_scope_codes($conn);
$codesSql = "'" . implode("','", array_map($esc, $codes)) . "'";
$tabMap = repair01_w3_entity_tab_map($ROOT);
$W("DELETE FROM repair01_w8_sidebar");

$s1Off = 0; $s2Fixed = 0; $s2Pending = 0; $s3Fixed = 0; $s3Pending = 0;
$s5Demoted = 0; $s5Blocked = 0; $s6Fixed = 0; $s7Linked = 0; $scopeScreens = 0;

$rows = array();
$rs = $conn->query("SELECT screen_id, route, owner_code, visibility_class, parent_screen_id
                      FROM repair01_screen_registry
                     WHERE owner_code IN ($codesSql) AND on_disk = 1 AND route IS NOT NULL
                     ORDER BY owner_code, route");
while ($rs && $x = $rs->fetch_assoc()) { $rows[] = $x; }

foreach ($rows as $x) {
    $scopeScreens++;
    $sid = $x['screen_id']; $rt = $x['route']; $rtE = $esc($rt);
    $navPred = repair01_w3_nav_pred($conn, $rt);
    $navN2  = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active=1");
    $labels = (string) $one("SELECT GROUP_CONCAT(DISTINCT label_ar SEPARATOR ' | ') FROM nav_items
                              WHERE ($navPred) AND active=1");
    $can = $conn->query("SELECT canonical_ar, group_name, sort_no, status FROM nav_canonical WHERE route='$rtE' LIMIT 1");
    $can = $can ? $can->fetch_assoc() : null;
    $grpLive = (string) $one("SELECT GROUP_CONCAT(DISTINCT g.name SEPARATOR ' | ') FROM nav_items n
                               LEFT JOIN link_groups g ON g.id = n.group_id
                              WHERE ($navPred) AND n.active=1");

    /* ─ ① التعطيل: غيرُ المعتمدِ في المستهدَفِ لا يبقى بندًا — بعذرٍ لا بحذف ─ */
    if ($x['visibility_class'] === 'MENU_ITEM' && $navN2 > 0)       { $s1v = 'KEEP_APPROVED_MENU'; $s1r = 'REGISTRY_MENU_ITEM'; }
    elseif ($x['visibility_class'] === 'MENU_ITEM' && $navN2 === 0) { $s1v = 'MENU_WITHOUT_ROW';   $s1r = 'REGISTRY_VS_LIVE'; $s1Off++; }
    elseif ($navN2 > 0)                                            { $s1v = 'ROW_WITHOUT_MENU';    $s1r = 'REGISTRY_VS_LIVE'; }
    else                                                           { $s1v = 'NOT_A_MENU_ITEM';     $s1r = 'REGISTRY_' . $x['visibility_class']; }

    /* ─ ② الاسمُ على التسميةِ المعياريّة ─ */
    $s2v = 'NO_CANONICAL_ROW'; $s2r = 'NAV_CANONICAL_MISSING';
    $labelCanon = $can ? (string) $can['canonical_ar'] : '';
    if ($can && $labels !== '') {
        if ($labels === $labelCanon) { $s2v = 'ALIGNED'; $s2r = 'LABEL_EQUALS_CANONICAL'; }
        elseif ($can['status'] === 'APPROVED') {
            $W("UPDATE nav_items SET label_ar = '" . $esc($labelCanon) . "'
                 WHERE ($navPred) AND label_ar <> '" . $esc($labelCanon) . "'");
            $s2v = 'CORRECTED_TO_CANONICAL'; $s2r = 'CANONICAL_APPROVED_WINS'; $s2Fixed++;
        } else { $s2v = 'PENDING_OWNER_NAME'; $s2r = 'CANONICAL_' . $can['status']; $s2Pending++; }
    } elseif ($can && $labels === '') { $s2v = 'NOT_A_MENU_ITEM'; $s2r = 'NO_ACTIVE_ROW'; }

    /* ─ ③ المجموعةُ على مجموعةِ الدورة ─ */
    $grpCanon = $can ? (string) $can['group_name'] : '';
    $s3v = 'NO_CANONICAL_ROW'; $s3r = 'NAV_CANONICAL_MISSING';
    if ($can && $grpLive !== '') {
        if ($grpLive === $grpCanon) { $s3v = 'ALIGNED'; $s3r = 'GROUP_EQUALS_CANONICAL'; }
        elseif ($can['status'] === 'APPROVED') { $s3v = 'RENDERED_FROM_CANONICAL'; $s3r = 'RENDERER_READS_CANONICAL'; $s3Fixed++; }
        else { $s3v = 'PENDING_OWNER_GROUP'; $s3r = 'CANONICAL_' . $can['status']; $s3Pending++; }
    } elseif ($can) { $s3v = 'NOT_A_MENU_ITEM'; $s3r = 'NO_ACTIVE_ROW'; }

    /* ─ ④ الترتيبُ على موضعِ دورةِ العملِ لا على الأبجديّةِ ولا على الإنشاء ─ */
    $stageOrder = (string) $one("SELECT MIN(stage_order) FROM repair01_surfaces WHERE screen_id = '" . $esc($sid) . "'");
    if ($stageOrder !== '' && $stageOrder !== null) { $s4src = 'SURFACE_STAGE_ORDER'; $s4no = (int) $stageOrder; $s4v = 'CYCLE_ORDER'; $s4r = 'W00_CYCLE_REGISTER'; }
    elseif ($can) { $s4src = 'NAV_CANONICAL_SORT_NO'; $s4no = (int) $can['sort_no']; $s4v = 'CANONICAL_ORDER'; $s4r = 'NAV_CANONICAL_SORT'; }
    else { $s4src = ''; $s4no = 0; $s4v = 'NO_ORDER_SOURCE'; $s4r = 'W8_DECISION:W8-D-02'; }

    /* ─ ⑤ الأبُ والتبويب: القرارُ يحدّد الموضعَ — والبلوغُ يُقاس قبلَ الخفض ─ */
    $s5v = 'NO_PARENT'; $s5r = 'REGISTRY_NO_PARENT'; $s5why = '';
    if ($x['parent_screen_id'] !== '') {
        $tab = isset($tabMap[strtolower($rt)]) ? $tabMap[strtolower($rt)] : null;
        $renders = repair01_w3_renders_tabs($ROOT, $rt);
        $parentRoute = (string) $one("SELECT route FROM repair01_screen_registry WHERE screen_id='" . $esc($x['parent_screen_id']) . "'");
        $childRoles  = repair01_w3_nav_roles($ROOT, $conn, $rt);
        $parentRoles = $parentRoute !== '' ? repair01_w3_nav_roles($ROOT, $conn, $parentRoute) : array();
        $lost = array_values(array_diff($childRoles, $parentRoles));
        if ($x['visibility_class'] !== 'MENU_ITEM') { $s5v = 'ALREADY_TAB'; $s5r = 'REGISTRY_' . $x['visibility_class']; }
        elseif ($tab === null)  { $s5v = 'TAB_CLAIM_UNPROVEN';   $s5r = 'NO_ROW_IN_ENTITY_TABS';  $s5why = 'ادعاء اب بلا مرساة مقيسة في سجل تبويبات الكيانات'; $s5Blocked++; }
        elseif ($renders === 0) { $s5v = 'TAB_BAR_NOT_RENDERED'; $s5r = 'DISK_NO_ems_entity_tabs'; $s5why = 'الشاشة لا تطبع شريط الرحلة فالخفض يقطع الطريق'; $s5Blocked++; }
        elseif ($lost)          { $s5v = 'DEMOTION_LOSES_ROLES'; $s5r = 'ROLE_REACH_MEASURED'; $s5why = 'ادوار تفقد البلوغ في السايدبار المصير: ' . implode('،', $lost); $s5Blocked++; }
        else {
            /* ⛔ **ولا يُخفَض بندٌ في مرحلةِ انحدار** (§٢ «لا إعادةَ بناء»):
               الخفضُ إلى تبويبٍ **تغييرُ بنيةِ ملاحةٍ حيّة** لا إصلاحُ عطب،
               ولا يكشفه متطلَّبٌ من متطلَّباتِ النطاقِ الأربعةِ والستّين —
               فيسقط تحت §٤-٤ «ما لا يكشفه المستهدَفُ يبقى كما هو».

               ⚠ **وقد جُرِّب ثمَّ أُرجع**: خفضُ ستّةِ أسطحٍ أسقط بوّابةَ الحفظِ
               في خطّافِ الالتزام — الدورُ 12 فقد `Projects/projects.php` من
               سايدبارِه المُصيَّر. وحارسُ `DEMOTION_LOSES_ROLES` الموروثُ من W07
               قارن أدوارَ الابنِ بأدوارِ الأبِ **فأجازه**، بينما سجلُّ الإخفاءِ
               نفسُه وسم ثلاثةَ أدوارٍ `NOT_RENDERED` — فالحارسُ يقيس عضويّةَ
               الأدوارِ ولا يقيس **التصييرَ الفعليّ**، وبوّابةُ الحفظِ تقيسه.

               ⇒ الحكمُ يُقاس ويُسجَّل، **ولا يُكتب شيء**. والخفضُ إن لزم قرارُ
               مالكٍ في مرحلةِ بناءٍ يملك أدواتِ إثباتِ البلوغِ على المُصيَّر. */
            $s5v = 'TAB_ELIGIBLE_NOT_DEMOTED'; $s5r = 'W8_REGRESSION_STAGE_NO_NAV_CHANGE';
            $s5why = 'تبويب «' . $tab['tab'] . '» في ' . $tab['parent']
                   . '» — مؤهل للخفض ولم يخفض: مرحلة انحدار لا تغير بنية ملاحة حية';
            $s5Blocked++;
        }
    }

    /* ─ ⑥ الظهورُ بالصلاحيةِ لا بالإخفاء + حارسُ عرضٍ على الخادم ─ */
    $permRows  = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active=1");
    $permCoded = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active=1
                              AND permission_code IS NOT NULL AND permission_code <> ''");
    if ($permRows > 0 && $permCoded < $permRows) {
        $W("UPDATE nav_items SET permission_code = '" . $rtE . "'
             WHERE ($navPred) AND active=1 AND (permission_code IS NULL OR permission_code = '')
               AND module_id IS NOT NULL AND module_id > 0");
        $permCoded = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active=1
                                  AND permission_code IS NOT NULL AND permission_code <> ''");
        $s6Fixed++; $s6v = 'PERMISSION_CODE_FILLED'; $s6r = 'ROUTE_IS_MODULE_CODE';
    } elseif ($permRows === 0) { $s6v = 'NOT_A_MENU_ITEM'; $s6r = 'NO_ACTIVE_ROW'; }
    else { $s6v = 'PERMISSION_GATED'; $s6r = 'ALL_ROWS_CODED'; }
    $guardKind = (string) $one("SELECT guard_kind FROM repair01_screen_registry WHERE screen_id='" . $esc($sid) . "'");

    /* ─ ⑦ الربطُ بـCanonical Screen_ID ─ */
    $s7 = 0; $s7v = 'NO_CANONICAL_ROW'; $s7r = 'NAV_CANONICAL_MISSING';
    if ($can) { $W("UPDATE nav_canonical SET screen_id = '" . $esc($sid) . "' WHERE route = '$rtE'");
                $s7 = 1; $s7v = 'LINKED'; $s7r = 'ROUTE_TO_SCREEN_ID'; $s7Linked++; }

    $vis = (string) $one("SELECT visibility_class FROM repair01_screen_registry WHERE screen_id='" . $esc($sid) . "'");
    $W("INSERT INTO repair01_w8_sidebar
        (screen_id,route,owner_code,s1_verdict,s1_rule,s2_label_live,s2_label_canon,s2_verdict,s2_rule,
         s3_group_live,s3_group_canon,s3_verdict,s3_rule,s4_order_src,s4_order_no,s4_verdict,s4_rule,
         s5_parent,s5_verdict,s5_rule,s5_why,s6_visibility,s6_perm_rows,s6_perm_coded,s6_guard_kind,
         s6_verdict,s6_rule,s7_linked,s7_verdict,s7_rule,measured_at)
        VALUES ('" . $esc($sid) . "','" . $rtE . "','" . $esc($x['owner_code']) . "',
                '" . $esc($s1v) . "','" . $esc($s1r) . "','" . $esc(mb_substr($labels, 0, 180)) . "','" . $esc($labelCanon) . "',
                '" . $esc($s2v) . "','" . $esc($s2r) . "','" . $esc(mb_substr($grpLive, 0, 180)) . "','" . $esc($grpCanon) . "',
                '" . $esc($s3v) . "','" . $esc($s3r) . "','" . $esc($s4src) . "'," . (int) $s4no . ",
                '" . $esc($s4v) . "','" . $esc($s4r) . "','" . $esc($x['parent_screen_id']) . "',
                '" . $esc($s5v) . "','" . $esc($s5r) . "','" . $esc($s5why) . "','" . $esc($vis) . "',
                $permRows,$permCoded,'" . $esc($guardKind) . "','" . $esc($s6v) . "','" . $esc($s6r) . "',
                $s7,'" . $esc($s7v) . "','" . $esc($s7r) . "',NOW())
        ON DUPLICATE KEY UPDATE s1_verdict=VALUES(s1_verdict), s2_verdict=VALUES(s2_verdict),
          s3_verdict=VALUES(s3_verdict), s4_verdict=VALUES(s4_verdict), s5_verdict=VALUES(s5_verdict),
          s6_verdict=VALUES(s6_verdict), s7_verdict=VALUES(s7_verdict), measured_at=NOW()");
}
printf("  أسطحُ النطاقِ المبنيّة %d · ① بلا بندٍ %d · ② صُحِّح %d/ينتظر %d · ③ يُصيَّر %d/ينتظر %d · ⑤ خُفِض %d/مُنع %d · ⑥ مُلئ %d · ⑦ %d/%d\n\n",
    $scopeScreens, $s1Off, $s2Fixed, $s2Pending, $s3Fixed, $s3Pending, $s5Demoted, $s5Blocked, $s6Fixed, $s7Linked, $scopeScreens);

/* ═══════════════════════════════════════════════════════════════════════════
   ③ العتباتُ — من السجلِّ لا من الشيفرة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③ عتباتُ المرحلةِ — تُقرأ ولا تُكتب ───────────────────────────\n";
$TH = array(
 array('w8_retention_pct_default', 5.0000, 'نسبة مئوية', 'نسبةُ الاحتجاز الافتراضيّة على المستخلص',
   'الاحتجازُ خصمُ مطالبةٍ لا خصمُ اعترافٍ — ونسبتُه تتغيّر بالعقدِ فلا تُكتب في شيفرةِ المستخلص', 'W8-D-05'),
 array('w8_advance_recovery_pct', 20.0000, 'نسبة مئوية', 'سقفُ استقطاعِ المقدَّمِ من المستخلصِ الواحد',
   'السقفُ الأقلُّ من النسبةِ والرصيد — ورقمٌ صلبٌ في الشيفرةِ يمنع ضبطَه بالعقد', 'W8-D-05'),
 array('w8_claim_dispute_days', 14.0000, 'يوم', 'مهلةُ اعتراضِ العميلِ على المستخلص',
   'المهلةُ شرطٌ تعاقديٌّ يختلف بالعقدِ — ومقارنةُ أيّامٍ صلبةٌ في خدمةٍ تجمّد ما يجب أن يُضبط', 'W8-D-05'),
 array('w8_supplier_aging_bucket', 30.0000, 'يوم', 'عرضُ شريحةِ تقادُمِ أرصدةِ الموردين',
   'شرائحُ التقادُمِ سياسةٌ ماليّةٌ تُراجَع — ورقمُها في شيفرةِ اللوحةِ يجمّد المراجعة', 'W8-D-05'),
 array('w8_supplier_readiness_min', 85.0000, 'نسبة مئوية', 'حدُّ الجاهزيةِ الأدنى لمعدّاتِ المورد',
   'الحدُّ التزامٌ تعاقديٌّ يختلف بالعقد — و`supplier_capacity.min_readiness_percent` يقرأه صفًّا صفًّا', 'W8-D-05'),
 array('w8_eval_pass_score', 60.0000, 'نقطة', 'درجةُ اجتيازِ تقييمِ المورد',
   'درجةُ الاجتيازِ قرارُ حوكمةٍ تُراجَع دوريًّا — ولا تُكتب في خدمةِ التقييم', 'W8-D-05'),
);
$W("DELETE FROM repair01_w8_thresholds");
foreach ($TH as $t) {
    $W("INSERT INTO repair01_w8_thresholds (threshold_key,value_num,unit_ar,title_ar,why,decision_ref,src_ref)
        VALUES ('" . $esc($t[0]) . "'," . (float) $t[1] . ",'" . $esc($t[2]) . "','" . $esc($t[3]) . "','"
        . $esc($t[4]) . "','" . $esc($t[5]) . "','W08 §٥ — عتبةٌ من السجلِّ لا من الشيفرة')");
}
say('عتباتٌ مسجَّلة: ' . count($TH));
echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ④ آلاتُ الحالةِ — كيانٌ بلا آلةِ حالةٍ لا يُغلَق (§٧)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "④ آلاتُ الحالةِ لكيانَي الرحلتَين ─────────────────────────────\n";
$W("DELETE FROM repair01_w8_states");
$ST = array();
$add = function ($entity, $from, $to, $allowed, $owner, $pre, $doc, $gate, $reopen, $correct, $forbid, $src) use (&$ST) {
    $ST[] = array($entity, $from, $to, $allowed, $owner, $pre, $doc, $gate, $reopen, $correct, $forbid, $src);
};

/* ── الفرصةُ البيعية ─────────────────────────────────────────────────── */
$add('opportunities', 'new', 'qualified', 1, 'مسؤول المبيعات',
  'عميل قائم في السجل · احتياج معلن · جهة اتصال معروفة', 'ورقة تاهيل الفرصة', 'مدير المبيعات',
  'الفرصة المغلقة تفتح بواقعة جديدة لا بتعديل حالتها', 'التصحيح تعديل حقول الفرصة بسجل تدقيقه', '', 'SAL-04');
$add('opportunities', 'qualified', 'quoted', 1, 'مسؤول المبيعات',
  'عرض واحد على الاقل مصدر على الفرصة', 'عرض سعر مرقم', 'مدير المبيعات',
  'الرجوع الى مؤهلة مسموح ما لم يوقع عقد', 'التصحيح باصدار عرض جديد لا بتعديل المصدر', '', 'SAL-07');
$add('opportunities', 'quoted', 'won', 1, 'مدير المبيعات',
  'عرض فائز معلن · مراجعة ما قبل العقد مكتملة', 'محضر الترسية', 'مدير المبيعات',
  'الفوز لا يرجع — والخسارة بعده واقعة فسخ عقد', 'التصحيح بواقعة مستقلة بسببها', '', 'SAL-09');
$add('opportunities', 'quoted', 'lost', 1, 'مدير المبيعات',
  'سبب الخسارة مكتوب', 'محضر الخسارة', 'مدير المبيعات',
  'الفرصة الخاسرة تفتح فرصة جديدة لا تعاد', 'التصحيح بتعديل السبب بسجل تدقيقه', '', 'SAL-04');
$add('opportunities', 'new', 'won', 0, '', '', '', '', '', '',
  'لا فوز بلا عرض — والترسية على فرصة بلا عرض تخلق عقدا بلا سعر متفاوض عليه', 'SAL-07');
$add('opportunities', 'lost', 'won', 0, '', '', '', '', '', '',
  'الخسارة واقعة معلنة للعميل — وقلبها يمحو وقائع قائمة بدل ان يفتح فرصة جديدة', 'SAL-04');

/* ── عرضُ السعر ──────────────────────────────────────────────────────── */
$add('quotations', 'draft', 'issued', 1, 'مسؤول المبيعات',
  'بند واحد على الاقل · عملة وصلاحية · فرصة قائمة', 'عرض سعر مرقم', 'مدير المبيعات',
  'العرض المصدر يعدل باصدار جديد لا بتحرير الاول', 'التصحيح باصدار نسخة تحمل رقم المراجعة', '', 'SAL-07');
$add('quotations', 'issued', 'negotiating', 1, 'مسؤول المبيعات',
  'جولة تفاوض مسجلة بنتيجتها', 'محضر التفاوض', 'مدير المبيعات',
  'التفاوض يعاد فتحه بجولة جديدة', 'التصحيح بجولة مستقلة لا بتعديل السابقة', '', 'SAL-09');
$add('quotations', 'negotiating', 'accepted', 1, 'مدير المبيعات',
  'نسخة فائزة محددة · صلاحية العرض قائمة', 'قبول العميل', 'مدير المبيعات',
  'القبول لا يرجع — والعدول بعده واقعة على العقد', 'التصحيح باشعار للعميل بمرجعه', '', 'SAL-07');
$add('quotations', 'issued', 'expired', 1, 'النظام',
  'تجاوز تاريخ الصلاحية بلا قبول', 'اشعار انتهاء الصلاحية', 'مدير المبيعات',
  'المنتهي يعاد اصداره بنسخة جديدة بتاريخها', 'التصحيح بتمديد موثق قبل الانتهاء لا بعده', '', 'SAL-07');
$add('quotations', 'draft', 'accepted', 0, '', '', '', '', '', '',
  'لا قبول لعرض لم يصدر — والقبول على مسودة يوقع عقدا على سعر لم يره العميل', 'SAL-07');
$add('quotations', 'expired', 'accepted', 0, '', '', '', '', '', '',
  'الصلاحية شرط تعاقدي — وقبول عرض منته يلزم الشركة بسعر سقط', 'SAL-07');

/* ── عقدُ العميل — اثنتا عشرةَ حالةً (H-02) ───────────────────────────── */
$add('contracts', 'مسودة', 'تفاوض', 1, 'مسؤول المبيعات',
  'عرض مقبول · اطراف معروفة', 'مسودة العقد', 'مدير المبيعات',
  'الرجوع الى مسودة مسموح قبل الاعتماد', 'التصحيح بتحرير المسودة بسجل تدقيقها', '', 'SAL-11');
$add('contracts', 'تفاوض', 'معتمد', 1, 'مدير المبيعات',
  'مراجعة ما قبل العقد مجتازة · بنود العقد مكتملة · مصفوفة الالتزامات معبأة', 'محضر الاعتماد', 'الحوكمة والقانوني',
  'الرجوع الى تفاوض بسبب مكتوب', 'التصحيح بملحق بعد التوقيع لا بتعديل النص', '', 'SAL-10');
$add('contracts', 'معتمد', 'موقَّع', 1, 'الرئيس التنفيذي او مفوضه',
  'مستند توقيع محفوظ · اطراف موقعة · خط اساس مختوم', 'العقد الموقَّع', 'سلطة التوقيع المسجلة',
  'التوقيع لا يرجع — والعدول فسخ بواقعته', 'التصحيح بملحق مرقم بمرجع العقد', '', 'SAL-11');
$add('contracts', 'موقَّع', 'نافذ', 1, 'مدير المبيعات',
  'تاريخ السريان حل · حاوية رئيسية مفتوحة', 'اشعار النفاذ', 'مدير المبيعات',
  'النفاذ لا يرجع الى موقَّع — والايقاف تعليق بواقعته', 'التصحيح بتعديل تاريخ السريان بملحق', '', 'SAL-16');
$add('contracts', 'نافذ', 'قيد التنفيذ', 1, 'إدارة التشغيل',
  'اول قيد تنفيذ معتمد على العقد', 'كشف الاداء الشهري', 'مدير التشغيل',
  'الرجوع الى نافذ بلا معنى — والتوقف تعليق', 'التصحيح بالغاء قيد التنفيذ بسببه', '', 'SAL-17');
$add('contracts', 'قيد التنفيذ', 'معلَّق', 1, 'مدير المبيعات',
  'سبب التعليق مكتوب · pause_state_before محفوظة', 'محضر التعليق', 'مدير المبيعات',
  'الرفع يعيد الحالة الى pause_state_before لا الى حالة يختارها الفاعل', 'التصحيح بتعديل سبب التعليق بسجل تدقيقه', '', 'SAL-11');
$add('contracts', 'معلَّق', 'قيد التنفيذ', 1, 'مدير المبيعات',
  'سبب التعليق زال · pause_state_before قائمة', 'محضر رفع التعليق', 'مدير المبيعات',
  'التعليق يعاد بواقعة جديدة', 'التصحيح بواقعة رفع مستقلة', '', 'SAL-11');
$add('contracts', 'قيد التنفيذ', 'معدَّل', 1, 'مدير المبيعات',
  'ملحق معتمد بمرجعه · اثر الملحق على السعر او الكمية او المدة مقيس', 'ملحق العقد', 'الحوكمة والقانوني',
  'الملحق لا يلغى — والتراجع ملحق معاكس', 'التصحيح بملحق ثان بمرجع الاول', '', 'SAL-19');
$add('contracts', 'قيد التنفيذ', 'مجدَّد', 1, 'مدير المبيعات',
  'دورة التزام جديدة معلنة · موافقة العميل محفوظة', 'ملحق التجديد', 'مدير المبيعات',
  'التجديد يفتح دورة التزام جديدة لا يمدد القديمة صامتا', 'التصحيح بملحق تجديد ثان', '', 'SAL-15');
$add('contracts', 'قيد التنفيذ', 'منتهٍ', 1, 'مدير المبيعات',
  'تجاوز actual_end · لا التزام تنفيذ قائم', 'اشعار الانتهاء', 'مدير المبيعات',
  'الانتهاء يرجع بتجديد بملحقه', 'التصحيح بتعديل actual_end بملحق', '', 'SAL-19');
$add('contracts', 'منتهٍ', 'مقفل', 1, 'الإدارة المالية',
  'كل مستخلص مفوتر · رصيد المحتجز مردود او مبرر · لا اعتراض مفتوح', 'محضر الاقفال', 'المدير المالي',
  'اعادة الفتح بقرار مخول بسببه المكتوب ولا تمحو الاقفال', 'التصحيح باشعار دائن او مدين بمرجعه', '', 'SAL-19');
$add('contracts', 'مقفل', 'مصفّى', 1, 'الإدارة المالية',
  'لا ذمة مفتوحة · لا ضمان قائم · مخالصة الطرفين محفوظة', 'المخالصة النهائية', 'المدير المالي',
  'المصفى لا يفتح — والنزاع بعده مطالبة مستقلة', 'التصحيح بمطالبة قانونية مستقلة', '', 'SAL-19');
$add('contracts', 'مسودة', 'نافذ', 0, '', '', '', '', '', '',
  'لا نفاذ بلا توقيع — والقفز يخلق التزاما تشغيليا على مستند لم يوقعه احد', 'SAL-11');
$add('contracts', 'مقفل', 'قيد التنفيذ', 0, '', '', '', '', '', '',
  'الاقفال حسم مالي — واعادته الى التنفيذ تفتح مستخلصات على فترة اقفلت دفاترها', 'SAL-19');
$add('contracts', 'مصفّى', 'معدَّل', 0, '', '', '', '', '', '',
  'المصفى انتهت ذمته بمخالصة — وتعديله يعيد التزاما ابراه الطرفان', 'SAL-19');

/* ── المستخلص ────────────────────────────────────────────────────────── */
$add('claims', 'draft', 'submitted', 1, 'مسؤول المبيعات',
  'بند واحد على الاقل · فترة لا تتداخل مع مستخلص حي · عقد غير مقفل', 'المستخلص الشهري', 'مدير المبيعات',
  'المسودة تعدل بحرية قبل التقديم', 'التصحيح بتحرير البنود قبل التقديم', '', 'SAL-18');
$add('claims', 'submitted', 'invoiced', 1, 'مدير المبيعات',
  'مجيز غير مقدمه · مبلغ مطابق لمجموع بنوده · عقد قائم', 'الفاتورة الضريبية', 'مدير المبيعات',
  'المفوتر لا يرجع الى مقدم — والتصحيح اشعار', 'التصحيح باشعار دائن او مدين بمرجعه', '', 'SAL-18');
$add('claims', 'invoiced', 'collected', 1, 'إدارة الخزينة',
  'تخصيص قبض على ذمة المستخلص · مبلغ محصل موجب', 'سند القبض', 'أمين الخزينة',
  'التحصيل الجزئي حالة رصيد لا حالة مستخلص', 'التصحيح بعكس التخصيص بواقعته', '', 'SAL-18');
$add('claims', 'draft', 'cancelled', 1, 'مسؤول المبيعات',
  'سبب الالغاء مكتوب · لم يفوتر', 'محضر الالغاء', 'مدير المبيعات',
  'الملغى لا يعاد — ويعاد انشاء مستخلص جديد بفترته', 'التصحيح بمستخلص جديد', '', 'SAL-18');
$add('claims', 'draft', 'invoiced', 0, '', '', '', '', '', '',
  'لا فوترة بلا تقديم — والقفز يلغي يد المراجعة فيصير المقدم هو المجيز', 'SAL-18');
$add('claims', 'invoiced', 'cancelled', 0, '', '', '', '', '', '',
  'المفوتر فتح ذمة على العميل — والغاؤه يمحو ذمة قائمة بدل ان يعكسها باشعار', 'SAL-18');
$add('claims', 'collected', 'draft', 0, '', '', '', '', '', '',
  'المحصل مال دخل الخزينة — واعادته الى مسودة تفصل القبض عن سنده', 'SAL-18');

/* ── المورد ──────────────────────────────────────────────────────────── */
$add('suppliers', 'registered', 'qualified', 1, 'مسؤول الموردين',
  'سجل تجاري ورقم ضريبي وهوية سارية وحساب بنكي موثق', 'ملف التاهيل', 'مدير الموردين',
  'التاهيل يسقط بانتهاء مستند ويعاد بتجديده', 'التصحيح برفع المستند الناقص', '', 'SUP-03');
$add('suppliers', 'qualified', 'suspended', 1, 'الحوكمة والالتزام',
  'مخالفة مسجلة او مستند منته', 'محضر الايقاف', 'مدير الحوكمة',
  'الرفع بزوال السبب بواقعته', 'التصحيح بواقعة رفع مستقلة', '', 'SUP-26');
$add('suppliers', 'qualified', 'blacklisted', 1, 'الحوكمة والالتزام',
  'قرار حوكمة مسبب · لا عقد حي', 'قرار الادراج', 'الرئيس التنفيذي',
  'الرفع بقرار المستوى نفسه لا بدونه', 'التصحيح بقرار رفع مسبب', '', 'SUP-26');
$add('suppliers', 'qualified', 'registered', 0, '', '', '', '', '', '',
  'التاهيل لا ينزع بارجاع الحالة — وسقوطه واقعة ايقاف بسببها المكتوب، والارجاع الصامت يمحو تاريخ التاهيل', 'SUP-03');
$add('suppliers', 'blacklisted', 'qualified', 0, '', '', '', '', '', '',
  'الادراج قرار حوكمة — ورفعه بمسؤول الموردين وحده يجعل المراقَب رافعا للمراقبة عنه', 'SUP-26');

/* ── عقدُ المورد ─────────────────────────────────────────────────────── */
$add('supplier_contracts', 'مسودة', 'معتمد', 1, 'مدير الموردين',
  'مورد مؤهل · بنود العقد مكتملة · احتياج تغطية معلن من المبيعات', 'محضر الترسية', 'الحوكمة والالتزام',
  'الرجوع الى مسودة قبل التوقيع', 'التصحيح بتحرير البنود قبل الاعتماد', '', 'SUP-06');
$add('supplier_contracts', 'معتمد', 'موقَّع', 1, 'الرئيس التنفيذي او مفوضه',
  'مستند توقيع محفوظ · ضمان حسن التنفيذ مستلم او مبرر', 'عقد التوريد الموقَّع', 'سلطة التوقيع المسجلة',
  'التوقيع لا يرجع — والعدول فسخ بواقعته', 'التصحيح بملحق مرقم', '', 'SUP-08');
$add('supplier_contracts', 'موقَّع', 'نافذ', 1, 'مدير الموردين',
  'تاريخ السريان حل · حصص المورد مسندة', 'اشعار النفاذ', 'مدير الموردين',
  'النفاذ لا يرجع — والايقاف تعليق بواقعته', 'التصحيح بملحق تاريخ', '', 'SUP-12');
$add('supplier_contracts', 'نافذ', 'قيد التنفيذ', 1, 'إدارة التشغيل',
  'اول وحدة اداء معتمدة على العقد', 'كشف الاداء الشهري', 'مدير التشغيل',
  'الرجوع بلا معنى — والتوقف تعليق', 'التصحيح بالغاء القيد بسببه', '', 'SUP-17');
$add('supplier_contracts', 'قيد التنفيذ', 'منتهٍ', 1, 'مدير الموردين',
  'تجاوز تاريخ الانتهاء · لا خانة مشغولة', 'اشعار الانتهاء', 'مدير الموردين',
  'الانتهاء يرجع بتجديد بملحقه', 'التصحيح بملحق تمديد', '', 'SUP-28');
$add('supplier_contracts', 'منتهٍ', 'مقفل', 1, 'الإدارة المالية',
  'كل تسوية معتمدة · سلف مستردة · ضمان مردود او محصل', 'محضر الاقفال التعاقدي', 'المدير المالي',
  'اعادة الفتح بقرار مخول بسببه', 'التصحيح بتسوية معدلة باصدار جديد', '', 'SUP-21');
$add('supplier_contracts', 'مقفل', 'مصفّى', 1, 'الإدارة المالية',
  'لا ذمة مفتوحة · مخالصة الطرفين محفوظة', 'المخالصة النهائية', 'المدير المالي',
  'المصفى لا يفتح', 'التصحيح بمطالبة مستقلة', '', 'SUP-28');
$add('supplier_contracts', 'قيد التنفيذ', 'مصفّى', 0, '', '', '', '', '', '',
  'لا تصفية قبل اقفال — والقفز يبرئ ذمة لم تحسب', 'SUP-21');
$add('supplier_contracts', 'مسودة', 'نافذ', 0, '', '', '', '', '', '',
  'لا نفاذ بلا توقيع — والقفز يسند خانات على مستند لم يوقعه احد', 'SUP-08');

/* ── تسويةُ المورد ───────────────────────────────────────────────────── */
$add('settlements', 'draft', 'review', 1, 'محاسب الموردين',
  'فترة مقفلة الاداء · طرف قائم في سجله · مكونات الاستحقاق مشتقة', 'مسودة كشف الحساب', 'مدير الموردين',
  'المسودة تعدل بحرية قبل المراجعة', 'التصحيح بتحرير المكونات', '', 'SUP-22');
$add('settlements', 'review', 'approved', 1, 'مدير الموردين',
  'لا اعتراض مفتوح · معتمد غير معدها · صافي مطابق لاجمالي ناقص التحميلات', 'كشف الحساب المعتمد', 'مدير الموردين',
  'الرجوع الى مراجعة باعتراض مسجل', 'التصحيح باصدار تسوية معدلة', '', 'SUP-22');
$add('settlements', 'approved', 'payment_requested', 1, 'محاسب الموردين',
  'صافي موجب · حساب بنكي موثق للمورد', 'طلب الدفع', 'المدير المالي',
  'الطلب يلغى بواقعته ويعاد', 'التصحيح بطلب جديد بمرجع التسوية', '', 'SUP-23');
$add('settlements', 'approved', 'closed', 1, 'الإدارة المالية',
  'صافي سالب · ذمة مدينة مفتوحة على المورد بمرجعها', 'اشعار الذمة المدينة', 'المدير المالي',
  'الاقفال يفتح باسترداد مسجل', 'التصحيح بتسوية معدلة', '', 'SUP-22');
$add('settlements', 'payment_requested', 'paid', 1, 'إدارة الخزينة',
  'سند صرف منفذ · مبلغ مطابق', 'سند الصرف', 'أمين الخزينة',
  'المدفوع لا يرجع — والاسترداد ذمة مدينة جديدة', 'التصحيح بواقعة استرداد', '', 'SUP-23');
$add('settlements', 'draft', 'paid', 0, '', '', '', '', '', '',
  'لا صرف بلا اعتماد — والقفز يصرف مالا على رقم لم تره يد ثانية', 'SUP-23');
$add('settlements', 'approved', 'draft', 0, '', '', '', '', '', '',
  'المعتمد حسم — واعادته الى مسودة تمحو قرار الاعتماد بدل ان تعكسه باصدار جديد', 'SUP-22');

/* ── إقفالُ عقدِ المورد ───────────────────────────────────────────────── */
$add('supplier_contract_closures', 'open', 'computed', 1, 'محاسب الموردين',
  'فترة السريان منتهية · مكونات الاقفال مشتقة من العقد والاداء', 'مسودة الاقفال', 'مدير الموردين',
  'يعاد الحساب ما لم يعتمد', 'التصحيح باعادة الاشتقاق', '', 'SUP-21');
$add('supplier_contract_closures', 'computed', 'approved', 1, 'المدير المالي',
  'سلف مستردة · ضمان محسوم · لا مخالفة مفتوحة بلا جزاء', 'محضر الاقفال التعاقدي', 'المدير المالي',
  'اعادة الفتح بقرار مخول بسببه', 'التصحيح باقفال معدل باصدار جديد', '', 'SUP-21');
$add('supplier_contract_closures', 'open', 'approved', 0, '', '', '', '', '', '',
  'لا اعتماد قبل حساب — واعتماد اقفال بلا مكوناته يبرئ ذمة بلا رقم', 'SUP-21');

foreach ($ST as $s) {
    $W("INSERT INTO repair01_w8_states
        (entity,from_state,to_state,allowed,owner_role,precondition,official_doc,approval_gate,
         reopen_rule,correct_rule,forbid_reason,src_ref)
        VALUES ('" . $esc($s[0]) . "','" . $esc($s[1]) . "','" . $esc($s[2]) . "'," . (int) $s[3] . ",'"
        . $esc($s[4]) . "','" . $esc($s[5]) . "','" . $esc($s[6]) . "','" . $esc($s[7]) . "','"
        . $esc($s[8]) . "','" . $esc($s[9]) . "','" . $esc($s[10]) . "','W08 · " . $esc($s[11]) . "')
        ON DUPLICATE KEY UPDATE allowed=VALUES(allowed), owner_role=VALUES(owner_role)");
}
$stEnt = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w8_states");
$stAll = (int) $one("SELECT COUNT(*) FROM repair01_w8_states WHERE allowed=1");
$stNo  = (int) $one("SELECT COUNT(*) FROM repair01_w8_states WHERE allowed=0");
printf("  كياناتٌ %d · انتقالٌ مسموحٌ %d · ممنوعٌ صراحةً %d\n\n", $stEnt, $stAll, $stNo);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ فصلُ الواجبات — والتركيبةُ الممنوعةُ برمزِ ردٍّ لا بإعلان
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑤ فصلُ الواجباتِ — ستّةُ أدوارٍ وتركيبةٌ ممنوعة ────────────────\n";
$W("DELETE FROM repair01_w8_sod");
/* ⛔ **و`enforced_by` يحمل المقيسَ لا المرغوب** (§٧ «لا إعلانَ بلا تنفيذ»):
     الوحدتانِ مرجعيّتانِ ولا تحملان رموزَ ردٍّ رمزيّةً على نمطِ W07 — بل
     حرّاسًا يعيدون `code` مرقَّمًا و`status='blocked'`. فالوسمُ هنا **يُثبَت
     بالاستدعاءِ لا بمطابقةِ نصّ**: `ENFORCED:` يُستدعى حارسُه في الفحصِ السلبيِّ
     ويجب أن يردّ، و`NOT_ENFORCED:` مُعلَنٌ في `W8-D-10` بعددِه.
     ⛔ **ولا تُكتب عبارةُ الرفضِ العربيّةُ مِرساةً** — نصُّ الحالةِ يطابقها
     فيُخضِرُّ كذبًا (‏_CONTEXT §قواعد القياس ③). */
$SOD = array(
 array('sal.contract.sign', 'توقيعُ عقدِ العميل',
   'مسؤول المبيعات', 'الحوكمة والقانوني', 'الرئيس التنفيذي او مفوضه', 'مدير المبيعات', 'الإدارة المالية',
   'مُعِدُّ العقدِ يوقّعه · ومراجعُ ما قبلِ العقدِ يعتمده',
   'ENFORCED: ContractStateMachine::transition ⇒ code 403 (سلطة التوقيع الاصلية · BR-CEO-01) · code 422 (انتقال خارج قائمة السماح)',
   'AUTH-SAL-01', 'نائب الرئيس التنفيذي', 'عقود الكيان القانوني الواحد', 'تفويض مكتوب بسقف قيمة', 'SAL-11'),
 array('sal.claim.approve', 'إجازةُ المستخلصِ وفوترتُه',
   'مسؤول المبيعات', 'إدارة التشغيل', 'مدير المبيعات', 'الإدارة المالية', 'إدارة الخزينة',
   'مُقدِّمُ المستخلصِ يجيزه · ومن يعتمد الوحداتِ يجيز مستخلصَها',
   'ENFORCED: claim_approve ⇒ status blocked عند submitted_by = uid (فصل اليدين) · وعلى عقد مقفل',
   'AUTH-SAL-02', 'نائب مدير المبيعات', 'مستخلصات عقود الادارة', 'تفويض مكتوب بسقف مبلغ', 'SAL-18'),
 array('sal.retention.release', 'ردُّ ضمانِ حسنِ التنفيذ',
   'مدير المبيعات', 'الإدارة المالية', 'الدور المخول بالرد', 'إدارة الخزينة', 'الإدارة المالية',
   'من احتجز يردّ · ومن يطالب بالرد يجيزه',
   'ENFORCED: claim_retention_release ⇒ code 403 لغير الدور المخول · code 409 على العطالة · code 422 قبل تجاوز actual_end',
   'AUTH-SAL-03', 'المدير المالي', 'عقود تجاوزت نهايتها', 'لا تفويض — قرار شخصي موثق', 'SAL-19'),
 array('sup.qualify', 'تأهيلُ المورِّدِ قانونيًّا وائتمانيًّا',
   'مسؤول الموردين', 'الحوكمة والالتزام', 'مدير الموردين', 'مسؤول الموردين', 'الحوكمة والالتزام',
   'من يسجّل المورّدَ يؤهّله · ومن يؤهّله يتعاقد معه',
   'NOT_ENFORCED: v_supplier_qualification مشتق ولا يستشيره اي مسار كتابة ⇒ W8-D-10',
   'AUTH-SUP-01', 'نائب مدير الموردين', 'موردو الكيان القانوني الواحد', 'تفويض مكتوب', 'SUP-03'),
 array('sup.contract.sign', 'توقيعُ عقدِ التوريد',
   'مسؤول الموردين', 'الحوكمة والالتزام', 'الرئيس التنفيذي او مفوضه', 'مدير الموردين', 'الإدارة المالية',
   'مُرشِّحُ المورّدِ يوقّع عقدَه · ومقيّمُ الأداءِ يرسي عليه',
   'NOT_ENFORCED: SupplierContractService::transition يمنع الانتقال غير المشروع (422) والعقد المرحل (423) — ولا يقارن الموقع بالمرشح ⇒ W8-D-10',
   'AUTH-SUP-02', 'نائب الرئيس التنفيذي', 'عقود التوريد', 'تفويض مكتوب بسقف قيمة', 'SUP-08'),
 array('sup.settlement.approve', 'اعتمادُ تسويةِ المورد',
   'محاسب الموردين', 'مدير الموردين', 'مدير الموردين', 'الإدارة المالية', 'إدارة الخزينة',
   'مُعِدُّ التسويةِ يعتمدها · ومن يعتمدها يصرفها',
   'ENFORCED: SettlementService::approve ⇒ code 403 عند prepared_by = userId · code 409 من غير review · code 423 باعتراض مفتوح',
   'AUTH-SUP-03', 'نائب مدير الموردين', 'تسويات موردي الادارة', 'تفويض مكتوب بسقف مبلغ', 'SUP-22'),
 array('sup.payment.request', 'طلبُ صرفِ مستحقِّ المورد',
   'محاسب الموردين', 'الإدارة المالية', 'المدير المالي', 'إدارة الخزينة', 'الإدارة المالية',
   'طالبُ الصرفِ ينفّذه · ومعتمدُ التسويةِ يعتمد صرفَها',
   'NOT_ENFORCED: لا انتقال الى payment_requested في SettlementService — والمسار محروس بصلاحية الشاشة وحدها ⇒ W8-D-10',
   'AUTH-SUP-04', 'نائب المدير المالي', 'صرف مستحقات الموردين', 'تفويض مكتوب بسقف مبلغ', 'SUP-23'),
 array('sup.violation.waive', 'إسقاطُ مخالفةٍ أو جزاءٍ على المورد',
   'مسؤول الموردين', 'الحوكمة والالتزام', 'مدير الحوكمة', 'محاسب الموردين', 'الحوكمة والالتزام',
   'مُسجِّلُ المخالفةِ يسقطها · ومدير الموردين يسقط جزاءَ مورّدِه',
   'NOT_ENFORCED: التسجيل can_add والاسقاط can_edit بلا مقارنة فاعلين ⇒ W8-D-10',
   'AUTH-SUP-05', 'الرئيس التنفيذي', 'مخالفات موردي الكيان', 'لا تفويض لمدير الموردين', 'SUP-26'),
);
foreach ($SOD as $s) {
    $W("INSERT INTO repair01_w8_sod
        (process_key,process_name,initiator_role,reviewer_role,approver_role,executor_role,closer_role,
         forbidden_combo,enforced_by,authority_rule_id,deputy_role,scope_rule,delegation,effective_date,src_ref)
        VALUES ('" . $esc($s[0]) . "','" . $esc($s[1]) . "','" . $esc($s[2]) . "','" . $esc($s[3]) . "','"
        . $esc($s[4]) . "','" . $esc($s[5]) . "','" . $esc($s[6]) . "','" . $esc($s[7]) . "','"
        . $esc($s[8]) . "','" . $esc($s[9]) . "','" . $esc($s[10]) . "','" . $esc($s[11]) . "','"
        . $esc($s[12]) . "','2026-08-26','W08 · " . $esc($s[13]) . "')
        ON DUPLICATE KEY UPDATE forbidden_combo=VALUES(forbidden_combo), enforced_by=VALUES(enforced_by)");
}
say('عملياتٌ حرِجةٌ بفصلِ واجبات: ' . count($SOD));
echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ عقودُ الأثرِ — حدثٌ بلا عقدٍ لا يُنفَّذ
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑥ عقودُ الأثرِ لأحداثِ النطاقِ الحيّة ───────────────────────────\n";
$NAR = repair01_w8_contract_narrative();
$written = 0;
foreach ($NAR as $code => $c) {
    $codeE = $esc($code);
    $exists = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE event_code = '$codeE'");
    $vals = "'" . $codeE . "','" . $esc($c['name']) . "','B','" . $esc($c['unit']) . "','" . $esc($c['screen']) . "','"
          . $esc($c['idem']) . "','" . $esc(implode(' · ', $c['consumers'])) . "','" . $esc($c['effect']) . "','"
          . $esc('at-least-once بمفتاح منع التكرار — والاعادة لا تضاعف الاثر') . "','" . $esc($c['src']) . "','"
          . $esc($c['trigger']) . "','" . $esc($c['payload']) . "','" . $esc(implode(' · ', $c['consumers'])) . "','"
          . $esc(implode(' · ', $c['effects'])) . "','" . $esc($c['pre']) . "','" . $esc($c['fail']) . "','"
          . $esc($c['comp']) . "','RECORDED','W8_CONTRACT_RECORDED','W08'";
    if ($exists > 0) {
        $W("UPDATE repair01_events SET
              name='" . $esc($c['name']) . "', source_unit='" . $esc($c['unit']) . "',
              source_screen='" . $esc($c['screen']) . "', idempotency_key='" . $esc($c['idem']) . "',
              consumers='" . $esc(implode(' · ', $c['consumers'])) . "', effect_type='" . $esc($c['effect']) . "',
              retry_policy='" . $esc('at-least-once بمفتاح منع التكرار — والاعادة لا تضاعف الاثر') . "',
              trigger_rule='" . $esc($c['trigger']) . "', min_payload='" . $esc($c['payload']) . "',
              consumer_list='" . $esc(implode(' · ', $c['consumers'])) . "',
              consumer_effect='" . $esc(implode(' · ', $c['effects'])) . "',
              preconditions='" . $esc($c['pre']) . "', failure_policy='" . $esc($c['fail']) . "',
              compensation='" . $esc($c['comp']) . "', contract_status='RECORDED',
              contract_rule='W8_CONTRACT_RECORDED', contract_stage='W08'
            WHERE event_code='$codeE'");
    } else {
        $W("INSERT INTO repair01_events
             (event_code,name,wave,source_unit,source_screen,idempotency_key,consumers,effect_type,
              retry_policy,src_ref,trigger_rule,min_payload,consumer_list,consumer_effect,
              preconditions,failure_policy,compensation,contract_status,contract_rule,contract_stage)
            VALUES ($vals)");
    }
    $written++;
}
printf("  عقودُ أثرٍ مكتوبةٌ في هذه المرحلة: %d\n\n", $written);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ الإصلاحاتُ — وكلُّ إصلاحٍ بمتطلَّبِه الكاشف
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑦ الإصلاحاتُ — لا إصلاحَ بلا متطلَّبٍ كاشف ─────────────────────\n";
$W("DELETE FROM repair01_w8_fixes");
/* ⛔ **والإصلاحُ يُسجَّل ولو أُعيد التشغيل**: الأداةُ عاديّةُ التشغيل، وشرطُ
     `أُصلح الآن > 0` يمحو الدفترَ في الشوطِ الثاني فيصير «تعديلٌ بلا متطلَّبٍ
     كاشف» صفرًا **لأنَّ الدفترَ فرغ** لا لأنَّ الإصلاحَ لم يقع. فالدليلُ
     يُقرأ من شوطِ الأساسِ المحفوظِ في `repair01_w8_regression` — وهو الوحيدُ
     الذي لا يُدهَس. */
$BASE = function ($checkKey) use ($one, $esc) {
    $r = $one("SELECT CONCAT(check_key,': ',measured,' من ',denominator,' في شوطِ الأساس')
                 FROM repair01_w8_regression WHERE phase='BASELINE' AND check_key='" . $esc($checkKey) . "'");
    return $r !== null ? (string) $r : ($checkKey . ': لا شوطَ أساسٍ مسجَّل');
};
$FIX = function ($key, $kind, $target, $what, $rev, $why, $ev) use ($W, $esc) {
    $W("INSERT INTO repair01_w8_fixes (fix_key,kind,target,what,revealed_by,reveal_why,evidence)
        VALUES ('" . $esc($key) . "','" . $esc($kind) . "','" . $esc($target) . "','" . $esc($what) . "','"
        . $esc($rev) . "','" . $esc($why) . "','" . $esc($ev) . "')
        ON DUPLICATE KEY UPDATE what=VALUES(what), evidence=VALUES(evidence)");
};

/* ─ ① مالكُ الشاشةِ الذي أجّله W02 إلى موجةِ إدارتِه ─────────────────── */
$ownerless = array();
$rs = $conn->query("SELECT screen_id, route FROM repair01_screen_registry
                     WHERE on_disk=1 AND owner_code='' AND route LIKE 'Suppliers/%'");
while ($rs && $x = $rs->fetch_assoc()) { $ownerless[] = $x; }
foreach ($ownerless as $x) {
    $W("UPDATE repair01_screen_registry
           SET owner_code='DEP-02', owner_role='إدارة الموردين', owner_rule='W8_OWNER_FROM_W2D01'
         WHERE screen_id='" . $esc($x['screen_id']) . "'");
}
$ownedNow  = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_rule = 'W8_OWNER_FROM_W2D01'");
$ownedList = (string) $one("SELECT GROUP_CONCAT(route SEPARATOR '، ') FROM repair01_screen_registry WHERE owner_rule = 'W8_OWNER_FROM_W2D01'");
{
    $FIX('W8-FIX-01', 'REGISTRY', 'repair01_screen_registry.owner_code',
      'أُسنِد DEP-02 مالكًا لـ' . $ownedNow . ' شاشةٍ في مجلَّدِ الموردين كانت بلا مالكٍ في أيِّ مصدر: ' . $ownedList,
      'SUP-30',
      'قرارُ W02 (‏W2-D-01) أجّل حسمَ المالكِ صراحةً إلى «موجةِ الإدارةِ التي تظهر فيها» — وهي هذه. '
      . 'ولوحةُ إدارةِ الموردين (SUP-30) تُشتقُّ من أسطحِ الوحدةِ، وسطحٌ بلا مالكٍ يسقط من مقامِها صامتًا.',
      $BASE('XCUT_SCOPE_OWNER'));
}
say('① مالكُ الشاشة: أُسنِد ' . count($ownerless));

/* ─ ② موجةُ صفِّ الفجوةِ تُشتقُّ من السجلِّ لا من خريطةٍ صلبة ─────────── */
$gapFixed = 0;
$rs = $conn->query("SELECT g.id, g.unit, g.wave_stage,
                      (SELECT MAX(r.stage_no) FROM repair01_requirements r
                        WHERE r.unit LIKE CONCAT(SUBSTRING(g.unit,5),' %')) AS st
                      FROM repair01_target_gaps g WHERE g.origin_stage='W02' AND g.wave_stage<>''");
$gapRows = array();
while ($rs && $x = $rs->fetch_assoc()) { $gapRows[] = $x; }
foreach ($gapRows as $x) {
    if ($x['st'] === null) { continue; }
    $want = 'W' . str_pad((string) (int) $x['st'], 2, '0', STR_PAD_LEFT);
    if ($want === $x['wave_stage']) { continue; }
    $W("UPDATE repair01_target_gaps SET wave_stage='" . $esc($want) . "' WHERE id=" . (int) $x['id']);
    $gapFixed++;
}
$gapTotal   = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE origin_stage='W02' AND wave_stage<>''");
$gapAligned = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps g WHERE g.origin_stage='W02' AND g.wave_stage<>''
     AND g.wave_stage = CONCAT('W', LPAD((SELECT MAX(r.stage_no) FROM repair01_requirements r
          WHERE r.unit LIKE CONCAT(SUBSTRING(g.unit,5),' %')), 2, '0'))");
{
    $FIX('W8-FIX-02', 'TOOL', 'tools/lib/repair01_w2_scan.php · repair01_w2_wave_for_code()',
      'صارت الموجةُ تُشتقُّ من `repair01_requirements.stage_no` بدل خريطةٍ صلبةٍ في الشيفرة، '
      . 'وصفوفُ الفجواتِ الموسومةُ على مرحلةِ وحدتِها الآن ' . $gapAligned . ' من ' . $gapTotal . '.',
      'SAL-20',
      'الخريطةُ الصلبةُ كُتبت **قبلَ إقحامِ W06 (نقاءُ لغةِ الواجهة) مرحلةً سادسة**، فصارت مزاحةً بواحدةٍ '
      . 'من DEP-14 فصاعدًا: خمسُ فجواتٍ من وحدتَي هذه المرحلةِ موسومةٌ `W07`، وثلاثُ فجواتِ المشترياتِ '
      . 'موسومةٌ `W08`. فمرحلةٌ تقرأ فجواتِها بـ`wave_stage=W08` تبني أسطحَ إدارةٍ أخرى وتترك أسطحَها.',
      $BASE('XCUT_GAP_WAVE_TRUTH'));
}
say('② موجةُ صفِّ الفجوة: أُعيد وسمُ ' . $gapFixed);

/* ─ ③ وصلُ المستخلصِ بالذمّةِ التي فتحها — من تطابقٍ مُثبَتٍ واحدٍ لواحد ─ */
$claimLinked = 0;
if (repair01_w8_col_exists($conn, 'claims', 'receivable_id')) {
    /* ⛔ لا يُكتب إلّا حيث الذمّةُ **واحدةٌ بالضبط** — وتعدُّدُ المطابقاتِ يُترك */
    $W("UPDATE claims c
          JOIN (SELECT r.doc_ref, MIN(r.id) rid, COUNT(*) n FROM fin_receivables r
                 WHERE r.doc_type='invoice' AND COALESCE(r.is_deleted,0)=0
                 GROUP BY r.doc_ref HAVING COUNT(*)=1) m ON m.doc_ref = c.invoice_no
           SET c.receivable_id = m.rid
         WHERE c.receivable_id IS NULL AND COALESCE(c.is_deleted,0)=0
           AND c.invoice_no IS NOT NULL AND c.invoice_no <> ''");
    $claimLinked = (int) $conn->affected_rows;
}
$claimLinkedNow = (int) $one("SELECT COUNT(*) FROM claims c JOIN fin_receivables r ON r.id = c.receivable_id
     WHERE COALESCE(c.is_deleted,0)=0 AND r.doc_type='invoice' AND r.doc_ref = c.invoice_no");
{
    $FIX('W8-FIX-03', 'DATA', 'claims.receivable_id',
      'الموصولُ الآن ' . $claimLinkedNow . ' مستخلصًا مفوترًا بذمّتِه في `fin_receivables` من تطابقِ '
      . '`doc_ref = invoice_no` **حيث المطابقةُ واحدةٌ بالضبط** — ولم يُكتب حيث تعدَّدت.',
      'SAL-18',
      'كلُّ المستخلصاتِ الـ٢٨٥ في حالةِ `invoiced` ولها `invoice_no`، و`fin_receivables` تحمل نظيرَها '
      . 'الـ٢٨٥ — **والوصلةُ بينهما نصٌّ لا مُعرِّف**: `claims.receivable_id` فارغٌ كلُّه و`source_doc_id` '
      . 'في الذمّةِ يشير إلى فضاءِ مفاتيحَ قديمٍ لا يصيب مستخلصًا واحدًا. ورابطٌ نصّيٌّ ينفكُّ بأوّلِ إعادةِ '
      . 'ترقيمِ فاتورة، ومحطّةُ «الأثرُ يصل الماليّة» في رحلةِ العميلِ تصير دعوى.',
      $BASE('SAL_CLAIM_FIN_HANDOVER'));
}
say('③ وصلُ المستخلصِ بذمّتِه: ' . $claimLinked);

/* ─ ④ رفضُ السياسةِ يُصنَّف `blocked` لا `failed` ────────────────────── */
$saSrc = @file_get_contents($ROOT . '/Contracts/claim_helpers.php');
$saFixed = ($saSrc !== false && strpos($saSrc, "\$out['status'] = 'blocked';\n                \$out['code']   = isset(\$__sa['code'])") !== false);
$FIX('W8-FIX-04', 'CODE', 'Contracts/claim_helpers.php · claim_approve()',
  'حارسُ «من أنشأ لا يعتمد» صار يكتب `status=blocked` ويحمل رمزَ الحارسِ (`403`) بدل تركِ `status` على '
  . '`failed` — والمقيسُ الآن: ' . ($saFixed ? 'مُطبَّق' : 'غيرُ مُطبَّق'),
  'SAL-18',
  'كشفه بناءُ **محطّةِ الرحلةِ السالبةِ ⑬**: فرعُ حارسِ «من أنشأ لا يعتمد» كان وحدَه يكتب السببَ '
  . 'ويترك `status` على `failed`، وكلُّ رفضٍ آخرَ في الدالّةِ يكتب `blocked`. و`failed` عند المُنادي '
  . '**عطبُ نظام** و`blocked` **قرارُ سياسة**؛ فخلطُهما يُظهر منعًا مفهومًا في صورةِ خطأٍ تقنيّ. '
  . 'والأخطرُ أنَّ الفرعَ المُصاب هو **الواقعُ عمليًّا**: المُنشئُ هو الرافعُ في كلِّ مستخلصٍ حيٍّ إلّا '
  . 'واحدًا، بينما الفرعُ السليمُ (`submitted_by`) هو وحدَه الذي يغطّيه `tests/claims_test.php` — '
  . 'فكان الحارسُ الأكثرُ وقوعًا خارجَ أيِّ قياس. '
  . '⚠ **وتصحيحُ رواية**: أوّلُ قراءةٍ للمحطّةِ أعطت `failed` لسببٍ آخرَ تمامًا — `claim_gate()` يبني '
  . 'بوّابةَ المستأجِرِ من الجلسةِ فترمي في سطرِ الأوامرِ **قبلَ أيِّ حارس**، فكان الفشلُ فشلَ سياقٍ '
  . 'لا حكمَ سياسة. وبعد بذرِ سياقِ الفاعلِ بلغت المحطّةُ الحارسَ فعلًا، فصار الإصلاحُ **مقيسًا** لا مقروءًا.',
  'محطّةُ الرحلة client-13 بسياقِ فاعلٍ مبذور: status=blocked · code=403');
say('④ تصنيفُ رفضِ السياسة: ' . ($saFixed ? 'مُطبَّق' : 'غيرُ مُطبَّق'));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ القرارات — والمُعلَنُ بعددِه المقيسِ لا بتقدير
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n⑧ قراراتُ المرحلة ─────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w8_decisions");
$D = function ($id, $q, $ruling, $why, $rows) use ($W, $esc) {
    $W("INSERT INTO repair01_w8_decisions (decision_id,question,ruling,rationale,scope_rows)
        VALUES ('" . $esc($id) . "','" . $esc($q) . "','" . $esc($ruling) . "','" . $esc($why) . "'," . (int) $rows . ")
        ON DUPLICATE KEY UPDATE ruling=VALUES(ruling), rationale=VALUES(rationale), scope_rows=VALUES(scope_rows)");
};

$d01 = (int) $one("SELECT COUNT(*) FROM repair01_w8_scope WHERE map_rule = 'W8_TARGET_GAP'");
$D('W8-D-01', 'ما حكمُ متطلَّبٍ في النطاقِ بلا سطحٍ مبنيٍّ ولا نظيرٍ على القرص؟',
   'يُوسَم `W8_TARGET_GAP` في دفترِ النطاقِ ويبقى مُعلَنًا — ولا يُبنى له سطحٌ في مرحلةِ انحدارٍ نصُّها «لا إعادةَ بناء».',
   'ستّةٌ من هذه الفجواتِ أسطحُ هندسةِ نظمٍ (خريطةُ ترحيلٍ · قاموسُ قواعدِ استنتاجٍ · تتبُّعُ قيمٍ رجعيّةٍ · '
 . 'تقريرُ اكتمالِ بيانات) لا أسطحُ أعمالٍ يوميّة، ولا نظيرَ لها على القرصِ في الوحدتَين. و§19 يأمر بالانحدارِ '
 . 'لا بالبناء، و§٤-٤ يقول «ما لا يكشفه المستهدَفُ يبقى كما هو» — والمستهدَفُ كشف غيابَها ولم يكشف عطبًا فيها.', $d01);

$d02 = (int) $one("SELECT COUNT(*) FROM repair01_w8_sidebar WHERE s4_verdict = 'NO_ORDER_SOURCE'");
$D('W8-D-02', 'ما ترتيبُ سطحٍ لا مصدرَ ترتيبٍ له في السجلِّ ولا صفَّ ملاحةٍ معياريًّا؟',
   'يُوسَم `NO_ORDER_SOURCE` ولا يُرتَّب بالأبجديّةِ ولا بتاريخِ الإنشاء — والترتيبُ من السجلِّ وحدَه (‏§٥).',
   'ترتيبٌ يدويٌّ موازٍ للسجلِّ محظورٌ نصًّا، ومَلءُ الفراغِ بالأبجديّةِ يصنع مصدرَ ترتيبٍ ثانيًا يتفرّق عن الأوّل.', $d02);

$d03 = (int) $one("SELECT COUNT(*) FROM repair01_w8_scope WHERE owner_verdict = 'MISMATCH'");
$D('W8-D-03', 'ما حكمُ سطحٍ يخدم متطلَّبَ إدارةٍ وهو مسجَّلٌ تحت إدارةٍ أخرى؟',
   'يُعلَن `MISMATCH` في دفترِ النطاقِ ولا يُدهَس — والملكيّةُ حُسمت في W01، والمخالفةُ هنا مرآةٌ لا تصحيح.',
   'أسطحُ المستخلصِ والفوترةِ والتسوياتِ والاستحقاقاتِ مسجَّلةٌ تحت الماليّةِ لأنّها **تُنفَّذ** هناك، '
 . 'ومتطلَّبُها مملوكٌ للمبيعاتِ والموردين لأنّهما **يملكان الدورة**. وتغييرُ المالكِ هنا يدهس حكمَ W01 '
 . 'بحجّةِ نصِّ متطلَّب — والوجهانِ صادقان: أحدُهما ملكيّةُ الشاشةِ والآخرُ ملكيّةُ الدورة.', $d03);

$d04 = (int) repair01_w8_one($conn, "SELECT COUNT(*) FROM contract_monthly_plan t
        WHERE NOT EXISTS (SELECT 1 FROM contracts c WHERE c.id=t.contract_id)");
$D('W8-D-04', 'ما حكمُ سطرِ خطِّ أساسٍ شهريٍّ على عقدٍ لم يعد في السجلّ؟',
   'يُعلَن بعددِه ويبقى — ولا يُحذف. والفحصُ `SAL_PLAN_CONTRACT_FK` يقيسه، والبوّابةُ تسقط لحظةَ يزيد العدد.',
   'الأسطرُ على مُعرِّفاتٍ صغيرةٍ (‏٠ و١٠..٢١) من فضاءِ مفاتيحَ سابقٍ للسجلِّ الحيّ. وحذفُها يمحو دليلَ '
 . 'تخطيطٍ وقع، وإعادةُ ربطِها تخترع عقدًا لم يُقَس. فتُعلَن بعددِها ويُقفَل اتّجاهُها.', $d04);

$d05 = 6;
$D('W8-D-05', 'أينَ تُكتب أرقامُ سياسةِ الوحدتَين (‏احتجازٌ · استقطاعُ مقدَّمٍ · مهلةُ اعتراضٍ · شرائحُ تقادُمٍ · جاهزيّةٌ دنيا · درجةُ اجتياز)؟',
   'في `repair01_w8_thresholds` تُقرأ ولا تُكتب — وستُّ عتباتٍ مسجَّلةٌ بعذرِ كلٍّ منها ومرجعِه.',
   'عتبةٌ رقميّةٌ صلبةٌ في الشيفرةِ محظورةٌ نصًّا (§٥). وهذه الستُّ كلُّها أرقامٌ تختلف بالعقدِ أو تُراجَع '
 . 'دوريًّا — وكتابتُها في خدمةٍ تجمّد ما غايتُه أن يُضبط.', $d05);

$d06 = (int) repair01_w8_one($conn, "SELECT COUNT(*) FROM settlements t WHERE t.party_type='supplier'
        AND NOT EXISTS (SELECT 1 FROM suppliers s WHERE s.id=t.party_ref)");
$D('W8-D-06', 'ما حكمُ تسويةٍ حيّةٍ تشير إلى مورِّدٍ لا صفَّ له؟',
   'تُعلَن بعددِها المقيسِ وتبقى — ولا تُحذف. وكلُّها `draft`/`review` أنشأها فاحصٌ سلبيٌّ ثمَّ نزع مورِّدَه.',
   'كلُّ التسوياتِ المعتمدةِ فما فوق (‏٢٥٢) تشير إلى مورِّدٍ قائمٍ بصفرِ يتيم — واليتيمُ محصورٌ في المسوَّداتِ '
 . 'التي أنشأها `tests/employee_settlement_test.php` و`tests/h15_capacity_test.php` باسمِ «موردُ {MARK}». '
 . 'فهذه **بقايا حزامٍ سلبيٍّ في بياناتٍ حيّة** لا عطبٌ في الوحدةِ المرجعيّة — والحذفُ يمحو دليلَ '
 . 'التلوُّثِ نفسِه. تُعلَن بعددِها، والفحصُ `SUP_SETTLE_SUPPLIER_FK` يقفل اتّجاهَها.', $d06);

$d07 = (int) repair01_w8_one($conn, "SELECT COUNT(*) FROM settlements
        WHERE party_type='supplier' AND net_direction='receivable'");
$D('W8-D-07', 'دورةُ «السالبِ يفتح ذمّةً مدينةً» — أمبنيّةٌ هي أم ممارَسة؟',
   'مبنيّةٌ **ولم تُمارَس**: صفرُ تسويةٍ معتمدةٍ سالبةٍ في النظامِ كلِّه؛ والسالبُ كلُّه مسوَّداتٌ ومراجعاتٌ من بقايا الفاحص. '
 . 'والقبولُ النهائيُّ في W15/W16 برحلةِ موظّفٍ حقيقيٍّ يعتمد تسويةً سالبةً بصلاحيتِه — لا ببذورِ بياناتٍ ولا بسكربت.',
   'وسمُ الاتّجاهِ يعمل بصفرِ مخالفةٍ على ٤٦٨ صفًّا (`SUP_SETTLE_DIRECTION`)، وحسابُ الصافي كذلك — فالآلةُ تصنّف. '
 . 'لكنَّ **فتحَ الذمّة** لم يقع مرّةً واحدةً لأنَّ الاعتمادَ لم يمرَّ على سالبٍ قطّ. وملءُ `receivable_due_id` '
 . 'على مسوَّداتِ فاحصٍ **تلفيقُ دليل** — وهو نمطُ `W7-D-08` نفسُه: الآلةُ قائمةٌ والمُعوِزُ ممارسة.', $d07);

$d08 = (int) repair01_w8_one($conn, "SELECT COUNT(*) FROM supplier_contracts sc
        JOIN v_supplier_qualification q ON q.supplier_id = sc.supplier_id
       WHERE COALESCE(sc.is_deleted,0)=0
         AND sc.state IN ('موقَّع','نافذ','قيد التنفيذ','معدَّل','مجدَّد')
         AND COALESCE(q.missing_count,0) > 0");
$D('W8-D-08', 'ما حكمُ عقدِ توريدٍ حيٍّ على مورِّدٍ ناقصِ التأهيل؟',
   'يُعلَن بعددِه ولا يُفسَخ ولا يُجمَّد — والنقصُ **بياناتٌ لم تُدخَل** لا آلةٌ غائبة. والإنفاذُ يبدأ من العقدِ '
 . 'التالي عبر `SUPPLIER_NOT_QUALIFIED` في مصفوفةِ فصلِ الواجبات، لا رجعيًّا على عشرين عقدًا قائمًا.',
   'المقيسُ أنَّ **صفرَ مورِّدٍ من واحدٍ وسبعين** يحمل رقمًا ضريبيًّا أو حسابًا بنكيًّا موثَّقًا — فالنقصُ '
 . 'شاملٌ ومنهجيّ، لا استثناءً في عشرينَ عقدًا. وفسخُ عقودٍ حيّةٍ لنقصِ حقلٍ إداريٍّ قرارُ مالكٍ لا قرارُ '
 . 'أداةِ إصلاح، وتجميدُها يوقف تشغيلًا قائمًا. فالحكمُ إعلانٌ بعددٍ وإنفاذٌ إلى الأمام.', $d08);

$d09 = count($NAR);
$D('W8-D-09', 'أيُّ الأحداثِ يُكتب لها عقدُ أثرٍ في هذه المرحلة؟',
   'ما تنشره شيفرةُ الوحدتَين **فعلًا** وحدَه — عشرةُ أحداثٍ مُثبَتٌ ناشرُ كلٍّ منها من القرص. '
 . 'ولا يُخترَع اسمُ حدثٍ «مستهدَفٍ» لم يُطلَق قطّ.',
   'عقدُ أثرٍ لحدثٍ لا تنشره الشيفرةُ يصير أدبًا: لا مستهلكَ يُقاس ولا أثرَ تجاريًّا يُثبَت، والبوّابةُ '
 . 'تخضرُّ على تطابقِ لا شيء. و`project.chartered` مستثنًى: عقدُه مكتوبٌ في W03 بمالكِه المشترَك — '
 . '**عقدٌ واحدٌ لكلِّ حدثٍ لا عقدٌ لكلِّ مرحلة** (نمطُ `expense.maintenance.recorded` في W07).', $d09);

$d10 = (int) $one("SELECT COUNT(*) FROM repair01_w8_sod WHERE enforced_by LIKE 'NOT_ENFORCED:%'");
$D('W8-D-10', 'ما حكمُ تركيبةٍ ممنوعةٍ في مصفوفةِ فصلِ الواجباتِ لا حارسَ لها في الشيفرة؟',
   'تُكتب في المصفوفةِ موسومةً `NOT_ENFORCED` **بسببِها المقيس**، وتُعلَن هنا بعددِها — ولا تُحذف من المصفوفةِ '
 . 'لتخضرَّ البوّابةُ، ولا يُدَّعى لها حارسٌ لا وجودَ له. والإنفاذُ قرارُ مالكٍ لأنَّه يمنع فعلًا يقع اليومَ.',
   'أربعٌ من ثمانٍ منفَّذةٌ ومُثبَتةٌ **بالاستدعاء** في الفحصِ السلبيّ: توقيعُ العقدِ (403 بسلطةِ التوقيع) · '
 . 'إجازةُ المستخلصِ (فصلُ اليدين على `submitted_by`) · ردُّ الضمانِ (403 بالدورِ و409 بالعطالة) · اعتمادُ '
 . 'التسويةِ (403 على `prepared_by`). وأربعٌ بلا حارس: التأهيلُ **مشتقٌّ ولا يُستشار**، وتوقيعُ عقدِ التوريدِ '
 . 'يمنع الانتقالَ غيرَ المشروعِ ولا يقارن الموقِّعَ بالمرشِّح، وطلبُ الدفعِ بلا انتقالٍ في الخدمةِ أصلًا، '
 . 'وإسقاطُ المخالفةِ بصلاحيةِ شاشةٍ واحدةٍ للتسجيلِ والإسقاط. '
 . '⛔ **وكتابةُ رمزِ ردٍّ رمزيٍّ في وحدةٍ مرجعيّةٍ إعادةُ بناءٍ يحظرها §٢** — والمرحلةُ انحدارٌ يكشف ولا يعيد البناء.', $d10);

$qLines = (int) repair01_w8_one($conn, "SELECT COUNT(*) FROM sal_quotation_lines");
$qRevs  = (int) repair01_w8_one($conn, "SELECT COUNT(*) FROM sal_quotation_revisions");
$D('W8-D-11', 'ما حكمُ سطحٍ مبنيٍّ ومحروسٍ ومربوطٍ ومقامُه صفر؟',
   'يُعلَن **مبنيًّا ولم يُمارَس** بعددِه المقيسِ — ومحطّتُه في الرحلةِ تعبر بالإعلانِ وحدَه، ونزعُ الإعلانِ يُسقطها. '
 . 'والمقيسُ: بنودُ عروضٍ ' . $qLines . ' · جولاتُ تفاوضٍ ' . $qRevs . '.',
   '`Clients/quotation_lines.php` و`Clients/quotation_negotiation.php` سطحانِ قائمانِ على القرصِ بحارسٍ ومِرساةٍ '
 . 'مُثبَتة، وجدولاهما `sal_quotation_lines` و`sal_quotation_revisions` **خاليانِ تمامًا**. فالآلةُ مبنيّةٌ '
 . 'والمُعوِزُ ممارسة: لم يُسجَّل بندُ عرضٍ ولا جولةُ تفاوضٍ واحدةٌ بعدُ. و«صفرُ مخالفةٍ من صفرِ صفوف» ليس نجاحًا — '
 . 'وهو الصنفُ الذي أوقع `W1-08` وحواجبَ W07 الخمسة، فحُرِس هنا بحارسِ خلاءٍ مربوطٍ بهذا القرار. '
 . 'والقبولُ البشريُّ في W15 يُدخل بندَ عرضٍ وجولةَ تفاوضٍ بمستخدمٍ حقيقيّ — لا ببذورِ بياناتٍ ولا بسكربت.',
   $qLines + $qRevs);

$d12 = (int) repair01_w8_one($conn, "SELECT COUNT(*) FROM settlements
        WHERE party_type='supplier' AND prepared_by IS NOT NULL AND approved_by IS NOT NULL
          AND prepared_by = approved_by");
$D('W8-D-12', 'ما حكمُ تسويةٍ معتمَدةٍ معتمِدُها هو مُعِدُّها والحارسُ اليومَ يمنع ذلك؟',
   'تُعلَن بعددِها المقيسِ وتبقى — والحارسُ في `SettlementService::approve` قائمٌ ومُثبَتٌ بالاستدعاءِ (`403`)، '
 . 'فالمخالفُ **سابقٌ للحارس** لا خرقٌ له. ولا تُعاد كتابةُ `approved_by` لصفوفٍ اعتُمدت فعلًا بيدٍ واحدةٍ — '
 . 'فذلك تزويرُ سجلِّ اعتماد.',
   'كلُّ الصفوفِ الـ' . $d12 . ' اعتُمدت بين 2024-10-31 و2026-06-30، والحارسُ يقارن `prepared_by` بالمعتمِدِ اليوم '
 . 'ويردُّ `403` — مُثبَتًا بالاستدعاءِ في محطّةِ رحلةِ المورد ⑧. وهو نمطُ التصحيحِ نفسُه الذي وقع في '
 . '`claim_approve` («كان الختمُ يُكتب بلا مقارنةِ المُعِدِّ بالمعتمِد»). والعلاجُ الرجعيُّ ليس شيفرةً: '
 . 'مراجعةُ اعتمادٍ بشريّةٌ في W15، أو قرارُ مالكٍ يُبرئ الماضي صراحةً.',
   $d12);

$decN = (int) $one("SELECT COUNT(*) FROM repair01_w8_decisions");
printf("  قراراتُ المرحلة: %d\n", $decN);

echo "\n───────────────────────────────────────────────────────────────\n";
echo "تمَّ تنفيذُ W08. التالي:\n";
echo "  php tools/repair01_w8_regression.php --after\n";
echo "  php tools/repair01_w8_journey.php\n";
echo "  php tools/repair01_w8_gate.php\n";
exit(0);
