<?php
/**
 * tools/repair01_w4_apply.php — أداةُ المرحلةِ الرابعة: الحقيقةُ الميدانية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **عاديّةُ التشغيل**: كلُّ كتابةٍ `INSERT … ON DUPLICATE KEY UPDATE` أو
 *   `UPDATE` بشرطٍ يطابق الحالةَ المستهدَفة، ومفاتيحُها **أعمالٌ** (متطلَّبٌ ·
 *   شاشةٌ · واقعةُ توقّفٍ) لا أرقامَ صفوف. فإعادةُ التشغيلِ لا تضاعف ولا تتراجع.
 *
 * ◆ **والقياسُ قبلَ الكتابة**: لا صفَّ برأي — كلُّ حكمٍ يحمل `*_rule` باسمِ
 *   قاعدتِه، وكلُّ رقمٍ مشتقٌّ من صفوفٍ حيّةٍ أو من القرص.
 *
 * ◆ **ولا يُدهَس تسجيلٌ ثانٍ للتوقّف**: الواقعةُ تُدَّعى بمفتاحِها، وأوّلُ سجلٍّ
 *   يدّعيها حاكمٌ والثاني **مرآةٌ بفارقِها** — والقيمُ في السجلَّين تبقى كما هي
 *   دليلًا. الدهسُ يمحو الواقعةَ قبل أن تُراجَع (W3-D-04).
 *
 * التشغيل:
 *   php tools/repair01_w4_apply.php            # قياسٌ وكتابة
 *   php tools/repair01_w4_apply.php --report   # قياسٌ بلا كتابة
 *   php tools/repair01_w4_apply.php --revert   # إرجاعُ ما كتبته هذه الأداةُ وحدَها
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
require_once $ROOT . '/tools/lib/repair01_w3_contracts.php';
require_once $ROOT . '/tools/lib/repair01_w4_scan.php';
require_once $ROOT . '/tools/lib/repair01_w4_contracts.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
require_once $ROOT . '/app/Services/Operations/SiteDayService.php';
\App\Services\Operations\SiteDayService::setEventConnection($conn);
/* بوابةُ عزلٍ لكلِّ كيانٍ — تُبنى مرّةً وتُعاد بالمفتاح (سياقٌ خادميٌّ صريح) */
$GATES = array();
$gateFor = function ($companyId) use ($conn, &$GATES) {
    $companyId = (int) $companyId;
    if (!isset($GATES[$companyId])) {
        $GATES[$companyId] = new \App\Core\TenantDb($conn,
            \App\Core\TenantContext::forSystem($companyId, 0, '', true));
    }
    return $GATES[$companyId];
};

$REPORT = in_array('--report', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$W = function ($sql) use ($conn, $REPORT) {
    if ($REPORT) { return true; }
    if ($conn->query($sql) === false) { echo '  ⚠ ' . $conn->error . "\n"; return false; }
    return true;
};
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w4_one($conn, $sql); };

echo "══ REPAIR01 · W04 — " . ($REVERT ? 'إرجاع' : ($REPORT ? 'قياسٌ بلا كتابة' : 'قياسٌ وكتابة')) . " ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⓪ الإرجاع — يُفرِّغ ما كتبته هذه الأداةُ وحدَها
   ═══════════════════════════════════════════════════════════════════════════ */
if ($REVERT) {
    foreach (array('repair01_w4_sidebar', 'repair01_w4_scope', 'repair01_w4_decisions',
                   'ops_stop_source', 'ops_stop_register') as $t) {
        if ($conn->query("DELETE FROM `$t`")) { echo "  ✔ فُرِّغ $t\n"; }
    }
    $conn->query("DELETE FROM repair01_events WHERE contract_stage = 'W04' AND wave = 'W04'");
    echo "  ✔ عقودُ الأثرِ المكتوبةُ في W04 نُزعت\n";
    $conn->query("UPDATE timesheet SET stop_register_role = 'NONE', stop_occurrence_key = ''
                   WHERE stop_register_role <> 'NONE'");
    echo "  ✔ وسمُ دورِ `timesheet` في واقعةِ التوقّفِ أُفرِغ\n";
    $conn->query("UPDATE unit_entries SET site_day_id = NULL WHERE site_day_id IS NOT NULL");
    echo "  ✔ وصلُ القيدِ باليومِ الميدانيِّ أُفرِغ\n";
    echo "\nالحكم: رجعت ✔ (والجداولُ والقيودُ تُنزع بهجرةِ التراجع)\n";
    exit(0);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① نطاقُ المرحلة — ٢٨ متطلَّبًا إلى مِرساتِها المُثبَتةِ قياسًا
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① نطاقُ المرحلة ─────────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w4_scope");
$ANCH = repair01_w4_anchors();
$anchored = 0; $notBuilt = 0; $unproven = array(); $ownerMismatch = array();

$rq = $conn->query("SELECT requirement_id, unit, group_name, surface, src_ref
                      FROM repair01_requirements WHERE stage_no = 4 ORDER BY unit, seq");
while ($rq && $q = $rq->fetch_assoc()) {
    $rid = $q['requirement_id'];
    $dept = preg_match('/^(\d{2})\s/u', $q['unit'], $mm) ? 'DEP-' . $mm[1] : '';
    if (!isset($ANCH[$rid])) {
        /* متطلَّبٌ خارجَ الخريطة: لا يُخترع له سطحٌ — يُسجَّل بقرارٍ صريح */
        $W("INSERT INTO repair01_w4_scope
            (requirement_id,unit,group_name,surface,anchor_screen_id,anchor_route,anchor_probe,
             owner_measured,owner_expected,owner_verdict,map_rule,map_why,wave_stage,src_ref)
            VALUES ('" . $esc($rid) . "','" . $esc($q['unit']) . "','" . $esc($q['group_name']) . "',
                    '" . $esc($q['surface']) . "','','','','','" . $esc($dept) . "','UNKNOWN',
                    'W4_DECISION:W4-D-01','متطلب خارج خريطة المراسي — يحسم بقرار المالك','',
                    '" . $esc($q['src_ref']) . "')");
        $notBuilt++; continue;
    }
    $a = $ANCH[$rid];
    $p = repair01_w4_prove_anchor($conn, $ROOT, $a);
    $ownerV = ($p['sid'] === '') ? 'NOT_BUILT' : (($p['owner'] === $dept) ? 'MATCH' : 'MISMATCH');
    if ($ownerV === 'MISMATCH') { $ownerMismatch[] = $rid . '⇐' . ($p['owner'] !== '' ? $p['owner'] : 'بلا مالك'); }
    if ($p['verdict'] === 'ANCHORED') { $anchored++; }
    elseif ($p['verdict'] === 'NOT_BUILT') { $notBuilt++; }
    else { $unproven[] = $rid . ' (' . $p['verdict'] . ')'; }

    $W("INSERT INTO repair01_w4_scope
        (requirement_id,unit,group_name,surface,anchor_screen_id,anchor_route,anchor_probe,
         owner_measured,owner_expected,owner_verdict,map_rule,map_why,wave_stage,src_ref)
        VALUES ('" . $esc($rid) . "','" . $esc($q['unit']) . "','" . $esc($q['group_name']) . "',
                '" . $esc($q['surface']) . "','" . $esc($p['sid']) . "','" . $esc($a['route']) . "',
                '" . $esc($a['probe']) . "','" . $esc($p['owner']) . "','" . $esc($dept) . "','" . $esc($ownerV) . "',
                '" . $esc($p['rule']) . "','" . $esc($a['why']) . "',
                '" . $esc(isset($a['wave']) ? $a['wave'] : '') . "','" . $esc($q['src_ref']) . "')
        ON DUPLICATE KEY UPDATE anchor_screen_id=VALUES(anchor_screen_id), map_rule=VALUES(map_rule),
          owner_measured=VALUES(owner_measured), owner_verdict=VALUES(owner_verdict), map_why=VALUES(map_why)");
}
printf("  مِرساةٌ مُثبَتةٌ قياسًا %d · لم يُبنَ %d · مِرساةٌ لم تُثبَت %d%s\n",
    $anchored, $notBuilt, count($unproven), $unproven ? ' ⇐ ' . implode('، ', $unproven) : '');
printf("  مالكُ السطحِ يخالف مالكَ المتطلَّب: %d%s\n\n", count($ownerMismatch),
    $ownerMismatch ? ' ⇐ ' . implode('، ', array_slice($ownerMismatch, 0, 6)) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ② السايدبار — الخطواتُ السبعُ بترتيبها على أسطحِ النطاق
   ═══════════════════════════════════════════════════════════════════════════ */
echo "② السايدبارُ — سبعُ خطواتٍ على أسطحِ النطاق ─────────────────────\n";
$codes = repair01_w4_scope_codes($conn);
$codesSql = "'" . implode("','", array_map($esc, $codes)) . "'";
$tabMap = repair01_w3_entity_tab_map($ROOT);
$W("DELETE FROM repair01_w4_sidebar");

$s2Fixed = 0; $s2Pending = 0; $s3Fixed = 0; $s3Pending = 0;
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
    $navN   = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active=1");
    $labels = (string) $one("SELECT GROUP_CONCAT(DISTINCT label_ar SEPARATOR ' | ') FROM nav_items
                              WHERE ($navPred) AND active=1");
    $can = $conn->query("SELECT canonical_ar, group_name, sort_no, status FROM nav_canonical WHERE route='$rtE' LIMIT 1");
    $can = $can ? $can->fetch_assoc() : null;
    $grpLive = (string) $one("SELECT GROUP_CONCAT(DISTINCT g.name SEPARATOR ' | ') FROM nav_items n
                               LEFT JOIN link_groups g ON g.id = n.group_id
                              WHERE ($navPred) AND n.active=1");

    /* ─ ① التعطيل: غيرُ المعتمدِ في المستهدَفِ لا يبقى بندًا — بعذرٍ لا بحذف ─ */
    if ($x['visibility_class'] === 'MENU_ITEM' && $navN > 0)      { $s1v = 'KEEP_APPROVED_MENU'; $s1r = 'REGISTRY_MENU_ITEM'; }
    elseif ($x['visibility_class'] === 'MENU_ITEM' && $navN === 0) { $s1v = 'MENU_WITHOUT_ROW';   $s1r = 'REGISTRY_VS_LIVE'; }
    elseif ($navN > 0)                                            { $s1v = 'ROW_WITHOUT_MENU';    $s1r = 'REGISTRY_VS_LIVE'; }
    else                                                          { $s1v = 'NOT_A_MENU_ITEM';     $s1r = 'REGISTRY_' . $x['visibility_class']; }

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
    else { $s4src = ''; $s4no = 0; $s4v = 'NO_ORDER_SOURCE'; $s4r = 'W4_DECISION:W4-D-02'; }

    /* ─ ⑤ الأبُ والتبويب: القرارُ يحدّد الموضعَ — والبلوغُ يُقاس قبلَ الخفض ─ */
    $s5v = 'NO_PARENT'; $s5r = 'REGISTRY_NO_PARENT'; $s5why = '';
    if ($x['parent_screen_id'] !== '') {
        $tab = isset($tabMap[strtolower($rt)]) ? $tabMap[strtolower($rt)] : null;
        $renders = repair01_w3_renders_tabs($ROOT, $rt);
        $parentRoute = (string) $one("SELECT route FROM repair01_screen_registry WHERE screen_id='" . $esc($x['parent_screen_id']) . "'");
        $w4Demoted = ($x['visibility_class'] === 'TAB_CHILD'
                      && (string) $one("SELECT visibility_rule FROM repair01_screen_registry
                                         WHERE screen_id = '" . $esc($sid) . "'") === 'W4_TAB_PROVEN_BY_ENTITY_TABS');
        $childRoles  = $w4Demoted ? repair01_w3_hidden_roles($conn, $rt) : repair01_w3_nav_roles($ROOT, $conn, $rt);
        $parentRoles = $parentRoute !== '' ? repair01_w3_nav_roles($ROOT, $conn, $parentRoute) : array();
        $lost = array_values(array_diff($childRoles, $parentRoles));
        $undo = function () use ($conn, $W, $esc, $sid, $navPred) {
            $W("UPDATE nav_items n JOIN gov_nav_hidden_log h ON h.nav_id = n.id AND h.doc_code = 'RPR-W04'
                   SET n.active = 1 WHERE " . str_replace('`route`', 'n.`route`', $navPred));
            $W("UPDATE repair01_screen_registry SET visibility_class = 'MENU_ITEM',
                    visibility_rule = 'W4_TAB_DEMOTION_REVERTED' WHERE screen_id = '" . $esc($sid) . "'");
            $W("DELETE FROM gov_nav_hidden_log WHERE doc_code = 'RPR-W04' AND " . $navPred);
        };
        if ($x['visibility_class'] !== 'MENU_ITEM' && !$w4Demoted) { $s5v = 'ALREADY_TAB'; $s5r = 'REGISTRY_' . $x['visibility_class']; }
        elseif ($tab === null)  { if ($w4Demoted) { $undo(); } $s5v = 'TAB_CLAIM_UNPROVEN';   $s5r = 'NO_ROW_IN_ENTITY_TABS';  $s5why = 'ادعاء اب بلا مرساة مقيسة في سجل تبويبات الكيانات'; $s5Blocked++; }
        elseif ($renders === 0) { if ($w4Demoted) { $undo(); } $s5v = 'TAB_BAR_NOT_RENDERED'; $s5r = 'DISK_NO_ems_entity_tabs'; $s5why = 'الشاشة لا تطبع شريط الرحلة فالخفض يقطع الطريق'; $s5Blocked++; }
        elseif ($lost)          { if ($w4Demoted) { $undo(); $s5r = 'ROLE_REACH_MEASURED_REVERTED'; } else { $s5r = 'ROLE_REACH_MEASURED'; }
                                  $s5v = 'DEMOTION_LOSES_ROLES'; $s5why = 'ادوار تفقد البلوغ في السايدبار المصير: ' . implode('،', $lost); $s5Blocked++; }
        else {
            $renderedIn = $childRoles ? implode(',', array_map('intval', $childRoles)) : '-1';
            $W("INSERT INTO gov_nav_hidden_log (role_id, nav_id, route, label_ar, group_before, sort_before, doc_code, reachable)
                SELECT role_id, id, route, label_ar, group_id, sort_order, 'RPR-W04',
                       CASE WHEN role_id IN ($renderedIn) THEN 'TAB_IN_PARENT' ELSE 'NOT_RENDERED' END
                  FROM nav_items WHERE ($navPred) AND active = 1
                ON DUPLICATE KEY UPDATE doc_code = 'RPR-W04',
                       reachable = CASE WHEN nav_items.role_id IN ($renderedIn) THEN 'TAB_IN_PARENT' ELSE 'NOT_RENDERED' END");
            $W("UPDATE nav_items SET active = 0 WHERE ($navPred) AND active = 1");
            $W("UPDATE repair01_screen_registry SET visibility_class = 'TAB_CHILD',
                    visibility_rule = 'W4_TAB_PROVEN_BY_ENTITY_TABS' WHERE screen_id = '" . $esc($sid) . "'");
            $s5v = 'DEMOTED_TO_TAB'; $s5r = 'TAB_PROVEN_AND_REACHABLE';
            $s5why = 'تبويب «' . $tab['tab'] . '» في ' . $tab['parent'] . ' — وكل ادوار الابن في الاب';
            $s5Demoted++; $navN = 0;
        }
    }

    /* ─ ⑥ الظهورُ بالصلاحيةِ لا بالإخفاء + حارسُ عرضٍ على الخادم ─ */
    $permRows  = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active=1");
    $permCoded = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active=1
                              AND permission_code IS NOT NULL AND permission_code <> ''");
    if ($permRows > 0 && $permCoded < $permRows) {
        $W("UPDATE nav_items SET permission_code = '" . $rtE . "'
             WHERE ($navPred) AND active=1 AND (permission_code IS NULL OR permission_code = '')");
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
    $W("INSERT INTO repair01_w4_sidebar
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
printf("  أسطحُ النطاقِ المبنيّة %d · ② صُحِّح %d/ينتظر %d · ③ يُصيَّر %d/ينتظر %d · ⑤ خُفِض %d/مُنع %d · ⑥ مُلئ %d · ⑦ %d/%d\n\n",
    $scopeScreens, $s2Fixed, $s2Pending, $s3Fixed, $s3Pending, $s5Demoted, $s5Blocked, $s6Fixed, $s7Linked, $scopeScreens);

/* ═══════════════════════════════════════════════════════════════════════════
   ③ تصنيفُ القيدِ اليوميّ — يُعاد اشتقاقُه في كلِّ تشغيل
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③ تصنيفُ القيدِ اليوميّ ────────────────────────────────────────\n";
$W("UPDATE unit_entries SET field_kind = 'FIELD_DAILY', field_kind_rule = 'W4_HAS_EQUIPMENT'
     WHERE equipment_id IS NOT NULL
       AND (field_kind <> 'FIELD_DAILY' OR field_kind_rule <> 'W4_HAS_EQUIPMENT')");
$W("UPDATE unit_entries SET field_kind = 'CONTRACT_PROJECTION', field_kind_rule = 'W4_NO_EQUIPMENT_NO_ENTERER'
     WHERE equipment_id IS NULL AND entered_by IS NULL AND shift IS NULL
       AND (field_kind <> 'CONTRACT_PROJECTION' OR field_kind_rule <> 'W4_NO_EQUIPMENT_NO_ENTERER')");
$cls = repair01_w4_classify_entries($conn);
printf("  قيدٌ ميدانيٌّ %d · إسقاطُ التزامٍ تعاقديّ %d · بلا قاعدةٍ %d · تصنيفٌ يخالف المقيسَ %d\n",
    $cls['field'], $cls['projection'], count($cls['unruled']), count($cls['mislabeled']));
printf("  قيدٌ ميدانيٌّ بلا وردية: %d\n\n", repair01_w4_daily_without_shift($conn));

/* ═══════════════════════════════════════════════════════════════════════════
   ④ واقعةُ التوقّف — سجلٌّ واحدٌ بمفتاحٍ، والثاني مرآةٌ بفارقِها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "④ واقعةُ التوقّف ───────────────────────────────────────────────\n";
$vocab = repair01_w4_shift_vocab($conn);
printf("  مفرداتُ الوردية الحيّة %d · بلا جسرٍ %d%s\n",
    count($vocab['live']), count($vocab['unmapped']),
    $vocab['unmapped'] ? ' ⇐ ' . implode('، ', $vocab['unmapped']) : '');

$occ = repair01_w4_stop_occurrences($conn);
$dbl = repair01_w4_double_registered($occ);
$reg = 0; $mir = 0;
if (!$REPORT) {
    foreach ($occ as $k => $o) {
        /* الحاكمُ: `unit_time_log` حيثما وُجد — فهو وحدَه يحمل المسؤولَ
           والالتزامَ وقابليّةَ الفوترة. والتايم شيت حاكمٌ حيث انفرد. */
        $authIsUtl = $o['utl_hours'] > 0;
        $first = array(
            'company_id' => $o['company'], 'occurrence_key' => $k, 'stop_date' => $o['date'],
            'shift' => $o['shift'], 'equipment_id' => $o['equipment'], 'project_id' => $o['project'],
            'ops_state' => $authIsUtl ? $o['state'] : 'timesheet_fault',
            'hours' => $authIsUtl ? round($o['utl_hours'], 2) : round($o['ts_hours'], 2),
            'resp_party' => $o['resp'], 'obligation_type' => $o['oblig'], 'billable' => $o['billable'],
            'register_name' => $authIsUtl ? 'unit_time_log' : 'timesheet',
            'authority_rule' => $authIsUtl ? 'W4_UTL_CARRIES_ATTRIBUTION' : 'W4_TIMESHEET_SOLE_REGISTER',
            'authority_ref' => $authIsUtl ? $o['utl_ref'] : ('timesheet#' . implode(',', array_slice($o['ts_ids'], 0, 3))),
        );
        $g = $gateFor($o['company']);
        $r1 = \App\Services\Operations\SiteDayService::registerStop($g, $first);
        if ($r1['ok']) { $reg++; }
        /* والوجهُ الثاني — مرآةٌ بفارقِها، ولا صفَّ ثانٍ للواقعة */
        if ($authIsUtl && $o['ts_hours'] > 0) {
            $r2 = \App\Services\Operations\SiteDayService::registerStop($g, array_merge($first, array(
                'register_name' => 'timesheet', 'hours' => round($o['ts_hours'], 2),
                'authority_ref' => 'timesheet#' . implode(',', array_slice($o['ts_ids'], 0, 3)),
                'variance_note' => 'ساعات عطل التايم شيت مقابل ساعات توقف السجل الزمني للحبة نفسها',
            )));
            if ($r2['ok']) { $mir++; }
            if (!$REPORT && $o['ts_ids']) {
                $W("UPDATE timesheet SET stop_register_role = 'MIRROR', stop_occurrence_key = '" . $esc($k) . "'
                     WHERE id IN (" . implode(',', array_map('intval', $o['ts_ids'])) . ")");
            }
        } elseif (!$authIsUtl && $o['ts_ids']) {
            $W("UPDATE timesheet SET stop_register_role = 'AUTHORITY', stop_occurrence_key = '" . $esc($k) . "'
                 WHERE id IN (" . implode(',', array_map('intval', $o['ts_ids'])) . ")");
        }
    }
}
$openVar = (int) $one("SELECT COUNT(*) FROM ops_stop_source WHERE variance_rule = 'W4_MIRROR_VARIANCE_OPEN'");
printf("  وقائعُ التوقّفِ المقيسة %d · مزدوجةُ التسجيلِ %d · سُجِّل %d · مرآةٌ %d · فارقٌ مفتوحٌ %d\n\n",
    count($occ), count($dbl), $reg, $mir, $openVar);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ عقودُ الأثرِ — لكلِّ حدثٍ يصدر من النطاق
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑤ عقودُ الأثر ──────────────────────────────────────────────────\n";
$narr = repair01_w4_contract_narrative();
$W("DELETE FROM repair01_events WHERE contract_stage = 'W04' AND wave = 'W04'");
$ents = "'" . implode("','", array_map($esc, repair01_w4_entity_types())) . "'";
$liveKeys = array();
$r = $conn->query("SELECT DISTINCT event_key, entity_type FROM ems_business_events WHERE entity_type IN ($ents)");
while ($r && $x = $r->fetch_assoc()) { $liveKeys[$x['event_key']] = $x['entity_type']; }
/* وأحداثُ العمودِ الفقريِّ التي تصدر عن هذه المرحلةِ تُعقد قبل أوّلِ إطلاق */
foreach (repair01_w4_stage_events() as $ek) { if (!isset($liveKeys[$ek])) { $liveKeys[$ek] = 'site_day'; } }

$written = 0; $missing = array(); $inherited = 0;
foreach ($liveKeys as $ek => $ent) {
    if (!isset($narr[$ek])) { $missing[] = $ek; continue; }
    /* ⚠ **عقدٌ واحدٌ لكلِّ حدثٍ لا عقدٌ لكلِّ مرحلة**: سبعةٌ من أحداثِ هذا النطاقِ
       عُقدت في W03 بوصفِها أحداثَ كيانٍ أمّ. وكتابةُ عقدٍ ثانٍ لها هنا تُنشئ
       **مرجعَين لحدثٍ واحد** — ثمّ لا يُعرف أيُّهما يحكم؛ وأسوأُ منه أنّها
       تُعمي حاجبَ W03-13: حذفُ عقدِ W03 يمرُّ لأنَّ عقدَ W04 يسدُّ مكانَه.
       فالمرحلةُ **ترث ولا تكرّر** — وتكتب لما لا عقدَ له. */
    $prior = (int) $one("SELECT COUNT(*) FROM repair01_events
                          WHERE event_code = '" . $esc($ek) . "' AND contract_status = 'RECORDED'
                            AND contract_stage <> '' AND contract_stage <> 'W04'");
    if ($prior > 0) { $inherited++; continue; }
    $c4 = $narr[$ek];
    $m = repair01_w3_measure_consumers($conn, $ek);
    $consumers = $m['consumers']; $effects = $m['effects']; $retry = $m['retry'];
    if (!$consumers) {                                   /* حدثٌ لم يُطلَق بعد — مستهلكوه مُعلَنون في العقد */
        $consumers = $c4['consumers']; $effects = $c4['effects'];
        $retry = 'بلا اشتراك مسجل — الحدث لم يطلق بعد';
    }
    $W("INSERT INTO repair01_events
        (event_code,name,wave,source_unit,source_screen,idempotency_key,consumers,effect_type,retry_policy,src_ref,
         trigger_rule,min_payload,consumer_list,consumer_effect,preconditions,failure_policy,compensation,
         contract_status,contract_rule,contract_stage)
        VALUES ('" . $esc($ek) . "','" . $esc($c4['name']) . "','W04','" . $esc($c4['unit']) . "',
                '" . $esc($c4['screen']) . "','" . $esc($c4['idem']) . "','" . $esc(implode(' · ', $consumers)) . "',
                '" . $esc($c4['effect']) . "','" . $esc($retry) . "',
                '" . $esc('قياسٌ حيّ: ' . $ent . ' › ' . $ek . ' + ' . $c4['src']) . "',
                '" . $esc($c4['trigger']) . "','" . $esc($c4['payload']) . "','" . $esc(implode("\n", $consumers)) . "',
                '" . $esc(implode("\n", $effects)) . "','" . $esc($c4['pre']) . "','" . $esc($c4['fail']) . "',
                '" . $esc($c4['comp']) . "','RECORDED','LIVE_EVENT_KEY_MEASURED','W04')");
    $written++;
}
printf("  أحداثُ النطاق %d · عقدٌ مكتوبٌ هنا %d · موروثٌ من مرحلةٍ سابقة %d · بلا عقدٍ %d%s\n\n",
    count($liveKeys), $written, $inherited, count($missing), $missing ? ' ⇐ ' . implode('، ', $missing) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ قراراتُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
$noOrder = (int) $one("SELECT COUNT(*) FROM repair01_w4_sidebar WHERE s4_verdict = 'NO_ORDER_SOURCE'");
$offMap  = (int) $one("SELECT COUNT(*) FROM repair01_w4_scope WHERE map_rule = 'W4_DECISION:W4-D-01'");
$mismN   = (int) $one("SELECT COUNT(*) FROM repair01_w4_scope WHERE owner_verdict = 'MISMATCH'");
$gapN    = (int) $one("SELECT COUNT(*) FROM repair01_w4_scope WHERE anchor_screen_id = ''");
$sodUnit = (int) $one("SELECT COUNT(*) FROM unit_entries WHERE entered_by = qty_decided_by AND qty_decided_by IS NOT NULL");
$sodAppr = (int) $one("SELECT COUNT(*) FROM unit_approvals a JOIN unit_entries e ON e.id = a.entry_id
                        WHERE a.actor_id = e.entered_by AND a.actor_id IS NOT NULL");
$varN    = (int) $one("SELECT COUNT(*) FROM ops_stop_source WHERE variance_rule = 'W4_MIRROR_VARIANCE_OPEN'");
$projN   = (int) $one("SELECT COUNT(*) FROM unit_entries WHERE field_kind = 'CONTRACT_PROJECTION'");

$DEC = array(
    array('W4-D-01', 'متطلَّبٌ في النطاقِ بلا مِرساةٍ مُعلَنةٍ ولا صفٍّ في دفترِ الفجوات',
        'يُسجَّل بمؤشِّرٍ إلى هذا القرارِ ولا تُخترع له شاشةٌ ولا صفُّ فجوة — والحسمُ في موجةِ الإدارةِ المالكة',
        'اختراعُ سطحٍ هنا يثبّت اسمًا لم يقرّه مالكُه ويُدخله السجلَّ المعياريَّ بلا سند',
        $offMap),
    array('W4-D-02', 'شاشةٌ في النطاقِ بلا موضعٍ من دورةِ عملٍ ولا صفٍّ معياريّ',
        'ترتيبُها يبقى كما هو ويُوسَم NO_ORDER_SOURCE — ولا يُخترع لها رقمُ ترتيبٍ يدويّ',
        'رقمُ ترتيبٍ مخترعٌ ترتيبٌ يدويٌّ موازٍ للسجلّ — وهو المحظورُ نفسُه في §٥',
        $noOrder),
    array('W4-D-03', 'واقعةُ توقّفٍ واحدةٌ يدّعيها سجلّانِ حيّانِ بساعاتٍ مختلفة',
        'أوّلُ سجلٍّ بمفتاحِ الواقعةِ حاكمٌ — و`unit_time_log` يسبق لأنّه وحدَه يحمل المسؤولَ والالتزامَ وقابليّةَ الفوترة. والثاني يُسجَّل مرآةً بفارقِها ولا يُنشئ صفًّا ثانيًا. والفارقُ المفتوحُ يُحسم عند مالكِ الانحرافِ (W11) ولا يُدهَس هنا',
        'دهسُ أحدِ السجلَّين يمحو الواقعةَ قبل أن تُراجَع (W3-D-04)؛ وجمعُهما يحتسب الساعةَ مرّتين فيضاعف الاستحقاقَ والخصم. والمفتاحُ يمنع الازدواجَ بلا محوٍ ولا جمع',
        $varN),
    array('W4-D-04', 'خمسةُ صفوفٍ في `unit_entries` بلا وردية ولا معدةٍ ولا مُدخِلٍ وتواريخُها 2091..2094',
        'تُصنَّف `CONTRACT_PROJECTION` بقاعدةٍ مقيسةٍ مكتوبةٍ في الصفِّ نفسِه، ويُعفيها القيدُ من شرطِ الوردية — والبوّابةُ تعيد اشتقاقَ التصنيفِ فلا يُوسَم قيدٌ ميدانيٌّ إسقاطًا',
        'استثناؤها بالاختيارِ «مقامٌ مختار» (§قواعد القياس ٤)، ودهسُها بوردية مخترعةٍ يصنع حقيقةً ميدانيّةً لم تقع. والتصنيفُ بقاعدةٍ يبقي المقامَ كاملًا والاستثناءَ مقروءًا',
        $projN),
    array('W4-D-05', 'سطحُ متطلَّبٍ مبنيٌّ تحت إدارةٍ غيرِ الإدارةِ المالكةِ للمتطلَّب',
        'يُسجَّل `MISMATCH` في دفترِ النطاقِ ولا يُنقل مالكُه هنا — نقلُ الملكيّةِ حكمُ W01 ومقامُه ٦٦٤ سطحًا لا ٢٨ متطلَّبًا',
        'تغييرُ `owner_code` من داخلِ مرحلةٍ نطاقيّةٍ يكسر مقامَ الملكيّةِ المُغلقَ في W01 ويجعل الحكمَ يتبع من قاسه آخِرًا',
        $mismN),
    array('W4-D-06', 'التركيبةُ الممنوعةُ في فصلِ الواجباتِ: مُدخِلٌ يحكم على كميّتِه أو يعتمد قيدَه',
        'تُقاس وتُقفَل اتّجاهًا: العددُ المقيسُ لا يزيد على المُعلَنِ هنا — والتصحيحُ الرجعيُّ قرارُ مالكٍ لا فعلُ أداة',
        'دهسُ الصفوفِ يمحو الواقعةَ قبل أن تُراجَع؛ والقفلُ يمنع نموَّها. والمنعُ الحيُّ في `sec_sod_pairs` غيرُ قائمٍ لهذا النطاقِ بعد (W13)',
        $sodUnit + $sodAppr),
    array('W4-D-07', 'أسطحُ النطاقِ غيرُ المبنيّةِ — أيُبنى سطحٌ جديدٌ في هذه المرحلة؟',
        'لا يُبنى ملفُّ شاشةٍ جديدٌ في W04: مقامُ سجلِّ الشاشاتِ مُجمَّدٌ بثابتٍ في حاجبِ `W3-14` (٦٥١) ومقامُ الأسطحِ بثابتٍ (٦٦٤) — فبناءُ سطحٍ يُسقِط بوّابةَ مرحلةٍ مُغلقة. والعمودُ الفقريُّ يُبنى الآن (`site_day` · `ops_stop_register`) والأسطحُ تتبعه بموجاتِها المسجَّلةِ في `repair01_w4_scope.wave_stage`',
        'حاجبُ «المخزنُ لم يُمَسّ» ثابتُه رقمٌ لا قاعدة، فهو يجمّد مقامًا **يُفترض أن ينمو**. والبناءُ فوقه يخيّر بين إسقاطِ بوّابةٍ مُغلقةٍ وبين ترقيعِها — وكلاهما أسوأُ من تأجيلِ السطحِ بموجتِه المسجَّلة',
        $gapN),
);
foreach ($DEC as $d) {
    $W("INSERT INTO repair01_w4_decisions (decision_id,question,ruling,rationale,scope_rows)
        VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "','" . $esc($d[3]) . "'," . (int) $d[4] . ")
        ON DUPLICATE KEY UPDATE question=VALUES(question), ruling=VALUES(ruling),
          rationale=VALUES(rationale), scope_rows=VALUES(scope_rows)");
}
printf("⑥ القرارات: %d مسجَّلة (W4-D-03 نطاقُه %d · W4-D-05 نطاقُه %d · W4-D-06 نطاقُه %d · W4-D-07 نطاقُه %d)\n\n",
    count($DEC), $varN, $mismN, $sodUnit + $sodAppr, $gapN);

echo "الحكم: " . ($REPORT ? "قياسٌ تمّ (بلا كتابة) ✔\n" : "الكتابةُ تمّت ✔\n");
exit(0);
