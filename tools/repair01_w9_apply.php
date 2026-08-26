<?php
/**
 * tools/repair01_w9_apply.php — قياسٌ وكتابةٌ للمرحلةِ التاسعة (المشتريات والمخازن)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السايدبارُ قبل الشاشات** (§٤ · RPR-PATCH-01 ③): الخطواتُ السبعُ بترتيبها
 *   على أسطحِ النطاقِ — وأسطحُ النموِّ تُسجَّل أوّلًا لأنّها جزءٌ من مقامِه.
 *
 * ⛔ `origin` = `W09` بالضبط (RPR-PATCH-02): أساسُ السجلِّ (٦٥١) مُجمَّدٌ،
 *   والنموُّ مسموحٌ **مختومًا وحدَه**.
 *
 * ◆ **والتأجيلُ يُكتب سجلًّا لا سردًا** (§⑧): `DEC-OPEN-15` مفتوحٌ بأمرِ المالك،
 *   وكلُّ بندٍ مؤجَّلٍ يحمل **ما بُني** و**ما ينتظر** و**خطوةَ الاستئناف**
 *   و**استعلامَ إثباتٍ** يقيس أنَّ الانتظارَ ما زال قائمًا.
 *
 * التشغيل: php tools/repair01_w9_apply.php [--report] [--revert]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w9_scan.php';
require_once $ROOT . '/app/Services/Ui/UiLabelRegistry.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$REPORT = in_array('--report', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w9_one($conn, $sql); };
$W = function ($sql) use ($conn, $REPORT) {
    if ($REPORT) { return true; }
    if ($conn->query($sql) === true) { return true; }
    echo '  ✘ ' . $conn->error . "\n  ⇐ " . mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 180) . "\n";
    return false;
};

echo "══ REPAIR01 · W09 — " . ($REVERT ? 'إرجاع' : ($REPORT ? 'قياسٌ بلا كتابة' : 'قياسٌ وكتابة')) . " ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⓪ الإرجاع — يُفرِّغ ما كتبته هذه الأداةُ وحدَها
   ═══════════════════════════════════════════════════════════════════════════ */
if ($REVERT) {
    foreach (repair01_w9_new_surfaces() as $s) {
        $rt = $esc($s['route']);
        $conn->query("DELETE FROM nav_items WHERE route = '$rt'");
        $conn->query("DELETE FROM nav_canonical WHERE route = '$rt'");
        $conn->query("DELETE FROM role_permissions WHERE module_id IN (SELECT id FROM modules WHERE code = '$rt')");
        $conn->query("DELETE FROM modules WHERE code = '$rt'");
        $conn->query("DELETE FROM repair01_screen_registry WHERE route = '$rt' AND origin = 'W09'");
        $conn->query("DELETE FROM gov_screen_cycle WHERE screen_file = '" . $esc(basename($s['route'])) . "'");
    }
    $conn->query("DELETE FROM link_groups WHERE group_code LIKE 'n9o_w9\\_%'");
    foreach (array('repair01_w9_scope', 'repair01_w9_sidebar', 'repair01_w9_decisions',
                   'repair01_w9_deferred', 'repair01_w9_states', 'repair01_w9_sod',
                   'repair01_w9_thresholds', 'repair01_w9_fixes', 'repair01_w9_journey') as $t) {
        $conn->query("DELETE FROM `$t`");
    }
    $conn->query("DELETE FROM repair01_events WHERE wave = 'W09'");
    $conn->query("DELETE FROM repair01_w6_code_dict WHERE src_ref LIKE 'RPR-W09%'");
    echo "الحكم: رجعت ✔ (والجداولُ تُنزع بهجرةِ التراجع)\n";
    exit(0);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① أسطحُ النموِّ — عشرةُ أسطحٍ مختومةٌ بموجتِها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① أسطحُ النموِّ — عشرةُ أسطحٍ مختومةٌ بـW09 ──────────────────────\n";
$newN = 0; $navN = 0; $permN = 0; $labelN = 0; $missing = array();
$maxSid = (int) preg_replace('/\D/', '', (string) $one("SELECT screen_id FROM repair01_screen_registry
                                                          ORDER BY screen_id DESC LIMIT 1"));
foreach (repair01_w9_new_surfaces() as $s) {
    $rt = $esc($s['route']); $file = basename($s['route']);
    if (!is_file($ROOT . '/' . $s['route'])) { $missing[] = $s['route']; continue; }

    /* ⓐ الموديول — مرجعُ الصلاحيةِ والاسم */
    $modId = (int) $one("SELECT id FROM modules WHERE code = '$rt' LIMIT 1");
    if ($modId === 0) {
        $ownerRole = (int) $one("SELECT owner_role_id FROM modules WHERE code = '" . $esc($s['sibling']) . "' LIMIT 1");
        $W("INSERT INTO modules (name, code, owner_role_id, is_link, icon, display_order, owner_dept_note)
            VALUES ('" . $esc($s['ar']) . "','$rt'," . ($ownerRole > 0 ? $ownerRole : 'NULL') . ",'0',
                    '" . $esc($s['icon']) . "'," . (int) $s['sort'] . ",'" . $esc($s['owner']) . "')");
        $modId = (int) $one("SELECT id FROM modules WHERE code = '$rt' LIMIT 1");
    }

    /* ⓑ المنحُ — لكلِّ دورٍ يرى الشقيقَ اليوم؛ فالبلوغُ يُقاس ولا يُخترع */
    if ($modId > 0) {
        $sibMod = (int) $one("SELECT id FROM modules WHERE code = '" . $esc($s['sibling']) . "' LIMIT 1");
        if ($sibMod > 0) {
            $W("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                SELECT rp.role_id, $modId, 1, rp.can_add, rp.can_edit, 0
                  FROM role_permissions rp WHERE rp.module_id = $sibMod AND rp.can_view = 1
                ON DUPLICATE KEY UPDATE can_view = 1");
            $permN += (int) $one("SELECT COUNT(*) FROM role_permissions WHERE module_id = $modId");
        }
    }

    /* ⓒ **المسمّى يُسجَّل قبل أن يُصيَّر** (‏W06): الاسمُ المشكولُ أو الحاملُ
         مصطلحًا تقنيًّا يُردُّ ويُقيَّد رفضُه، و`W6-05` يسقط على اسمٍ مُصيَّرٍ
         خارجَ `repair01_ui_labels`. فالتسجيلُ **قبل** كتابةِ الصفِّ المعياريّ. */
    if (!$REPORT) {
        $lr = \App\Services\Ui\UiLabelRegistry::register($conn, 'screen:' . strtolower($s['route']), $s['ar'], array(
            'allowed_context' => 'SIDEBAR SCREEN_TITLE',
            'source_table' => 'nav_canonical', 'source_column' => 'canonical_ar',
            'source_key' => $s['route'], 'owner_code' => $s['owner'],
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W9_NEW_SURFACE_LABEL', 'origin' => 'W09',
            'src_ref' => 'RPR-W09 §٤ · سطحُ نموٍّ مختوم', 'caller' => 'repair01_w9_apply.php',
        ));
        if (!$lr['ok']) { echo '  ⚠ رُدَّ مسمّى ' . $s['route'] . ' — ' . $lr['code'] . ': ' . $lr['detail'] . "\n"; }
        else { $labelN++; }
        $gr = \App\Services\Ui\UiLabelRegistry::register($conn, 'group:w9:' . strtolower($s['group']), $s['group'], array(
            'allowed_context' => 'SIDEBAR', 'source_table' => 'nav_canonical', 'source_column' => 'group_name',
            'source_key' => $s['group'], 'owner_code' => $s['owner'],
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W9_CYCLE_GROUP_LABEL', 'origin' => 'W09',
            'src_ref' => 'RPR-W09 §٤ · مجموعةُ دورةِ العمل', 'caller' => 'repair01_w9_apply.php',
        ));
        if ($gr['ok']) { $labelN++; }
    }

    /* ⓓ السجلُّ المعياريُّ للتنقُّل */
    $sid = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    if ($sid === '') { $maxSid++; $sid = 'SCR-' . str_pad((string) $maxSid, 4, '0', STR_PAD_LEFT); }
    $W("INSERT INTO nav_canonical (route, canonical_ar, level_no, level_name, group_name, sort_no,
                                   status, decision_state, application_state, decision_source,
                                   derivation, retirement_status, screen_id)
        VALUES ('$rt','" . $esc($s['ar']) . "',2,'العمليات','" . $esc($s['group']) . "'," . (int) $s['sort'] . ",
                'APPROVED','APPROVED','DEPLOYED','RPR-W09 · المشتريات والمخازن (2026-08-26)',
                'دورةُ الإدارةِ المالكةِ في الحزمة','ACTIVE','" . $esc($sid) . "')
        ON DUPLICATE KEY UPDATE canonical_ar=VALUES(canonical_ar), group_name=VALUES(group_name),
          sort_no=VALUES(sort_no), status=VALUES(status), screen_id=VALUES(screen_id)");

    /* ⓔ **مجموعةُ الدورةِ لا مجموعةُ الشقيق** — والمُعرِّفُ من `screen_id`
         لا من المسار: `link_groups.group_code` عرضُه أربعون محرفًا، والاشتقاقُ
         من المسارِ يبتُر ويتصادم (‏عطبُ W07 المقيسُ · RPR-W07 §٤). */
    if ($modId > 0) {
        $gkey = 'n9o_w9_' . strtolower(str_replace('-', '', $sid));
        $sib = $conn->query("SELECT n.role_id, n.door, g.stage_no, g.stage_title, g.display_order
                               FROM nav_items n
                               LEFT JOIN link_groups g ON g.id = n.group_id
                              WHERE n.route = '" . $esc($s['sibling']) . "' AND n.active = 1
                              GROUP BY n.role_id, n.door, g.stage_no, g.stage_title, g.display_order");
        while ($sib && $sx = $sib->fetch_assoc()) {
            $rid  = (int) $sx['role_id'];
            $code = $gkey . '_r' . $rid;
            $gid  = (int) $one("SELECT id FROM link_groups WHERE group_code = '" . $esc($code) . "' LIMIT 1");
            if ($gid === 0) {
                $W("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order,
                                             stage_no, stage_title, is_active)
                    VALUES ('" . $esc($s['group']) . "','" . $esc($code) . "',$rid,'" . $esc($s['icon']) . "',
                            " . ((int) $sx['display_order'] + 1) . "," . (int) $sx['stage_no'] . ",
                            '" . $esc((string) $sx['stage_title']) . "',1)");
                $gid = (int) $one("SELECT id FROM link_groups WHERE group_code = '" . $esc($code) . "' LIMIT 1");
            } else {
                $W("UPDATE link_groups SET name = '" . $esc($s['group']) . "', is_active = 1,
                        stage_no = " . (int) $sx['stage_no'] . " WHERE id = $gid");
            }
            if ($gid <= 0) { continue; }
            $W("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon,
                                       sort_order, permission_code, active)
                VALUES ($rid,'" . $esc($sx['door']) . "',$gid,$modId,'" . $esc($s['ar']) . "','$rt',
                        '" . $esc($s['icon']) . "'," . (int) $s['sort'] . ",'$rt',1)
                ON DUPLICATE KEY UPDATE label_ar=VALUES(label_ar), icon=VALUES(icon), group_id=VALUES(group_id),
                  sort_order=VALUES(sort_order), permission_code=VALUES(permission_code),
                  module_id=VALUES(module_id), active=1");
        }
        $navN += (int) $one("SELECT COUNT(*) FROM nav_items WHERE route = '$rt' AND active = 1");
    }

    /* ⓕ مصفوفةُ الدورةِ الحيّة — واسمُ الإدارةِ من جسرِ المسمّياتِ لا مخترَعًا */
    if ($modId > 0) {
        $deptAr = (string) $one("SELECT legacy_name FROM repair01_dept_crosswalk
                                  WHERE canonical_code = '" . $esc($s['owner']) . "' ORDER BY id LIMIT 1");
        if ($deptAr === '') { echo '  ⚠ لا جسرَ مسمًّى للإدارة ' . $s['owner'] . " — الصفُّ لا يُكتب\n"; }
        else {
            $W("INSERT INTO gov_screen_cycle
                (company_id, dept_name, layer_name, stage_order, stage_name, group_name, screen_title,
                 screen_file, inputs_note, output_doc, resp_role, next_state, consumers, fin_impact, stage_kind)
                VALUES (0,'" . $esc($deptAr) . "','" . $esc($s['group']) . "','" . (int) $s['sort'] . "',
                        '" . $esc($s['group']) . "','" . $esc($s['group']) . "',
                        '" . $esc($s['ar']) . "','" . $esc($file) . "',
                        '" . $esc('متطلبات: ' . $s['req']) . "','" . $esc($s['doc']) . "',
                        '" . $esc($s['role']) . "','" . $esc($s['next']) . "','" . $esc($s['cons']) . "',
                        '" . $esc($s['fin']) . "','canonical')");
        }
    }

    /* ⓖ سجلُّ الشاشاتِ — بختمِ الموجةِ لا بلا ختم */
    $guard = repair01_w9_guard_of($ROOT, $s['route']);
    $W("INSERT INTO repair01_screen_registry
        (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
         lifecycle, lifecycle_rule, parent_screen_id, parent_rule, visibility_class, visibility_rule,
         on_disk, origin, guard_kind, guard_evidence, w2_why, src_ref)
        VALUES ('" . $esc($sid) . "','" . $esc($file) . "','$rt','W9_NEW_SURFACE_ROUTE',
                '" . $esc($s['owner']) . "','" . $esc($s['role']) . "','W9_REQUIREMENT_OWNER',
                'LIVE_UNREGISTERED','W9_GROWTH_OUTSIDE_STUDY_MATRIX','','','MENU_ITEM','NAV_ITEMS_ACTIVE',
                1,'W09','" . $esc($guard['kind']) . "','" . $esc($guard['evidence']) . "',
                '" . $esc($s['ar']) . " (" . $esc($file) . ")','RPR-W09 · المشتريات والمخازن')
        ON DUPLICATE KEY UPDATE owner_code=VALUES(owner_code), owner_role=VALUES(owner_role),
          visibility_class=VALUES(visibility_class), guard_kind=VALUES(guard_kind),
          guard_evidence=VALUES(guard_evidence), origin='W09', on_disk=1");
    $newN++;
}
printf("  أسطحٌ مسجَّلةٌ بختمِ W09 %d · بنودُ قائمةٍ نشِطة %d · منحٌ %d · مسمّياتٌ مسجَّلة %d · بلا ملفٍّ %d%s\n\n",
    $newN, $navN, $permN, $labelN, count($missing), $missing ? ' ⇐ ' . implode('، ', $missing) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ② نطاقُ المرحلة — ٣٤ متطلَّبًا إلى مِرساتِها المُثبَتةِ قياسًا
   ═══════════════════════════════════════════════════════════════════════════ */
echo "② نطاقُ المرحلة ───────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w9_scope");
$ANCH = repair01_w9_anchors();
$anchored = 0; $unproven = array(); $ownerMismatch = array(); $deferredReq = array();
foreach (repair01_w9_deferred_rows() as $d) { $deferredReq[$d['requirement_id']] = true; }

$rq = $conn->query("SELECT requirement_id, unit, group_name, surface, src_ref
                      FROM repair01_requirements WHERE stage_no = 9 ORDER BY unit, seq");
while ($rq && $q = $rq->fetch_assoc()) {
    $rid = $q['requirement_id'];
    $dept = preg_match('/^(\d{2})\s/u', $q['unit'], $mm) ? 'DEP-' . $mm[1] : '';
    if (!isset($ANCH[$rid])) {
        $unproven[] = $rid . ' (بلا مِرساةٍ مُعلَنة)';
        continue;
    }
    $a = $ANCH[$rid];
    $pr = repair01_w9_prove_anchor($conn, $ROOT, $a);
    if ($pr['verdict'] === 'ANCHORED') { $anchored++; }
    else { $unproven[] = $rid . ' (' . $pr['verdict'] . ')'; }

    $verdictOwner = ($pr['owner'] !== '' && $dept !== '' && $pr['owner'] !== $dept) ? 'MISMATCH' : 'MATCH';
    if ($verdictOwner === 'MISMATCH') { $ownerMismatch[] = $rid . ' ' . $pr['owner'] . ' بدل ' . $dept; }
    $build = isset($deferredReq[$rid]) ? 'DEFERRED'
           : (in_array($a['route'], array_column(repair01_w9_new_surfaces(), 'route'), true) ? 'BUILT_W09' : 'LIVE');

    $W("INSERT INTO repair01_w9_scope
        (requirement_id,unit,group_name,surface,anchor_screen_id,anchor_route,anchor_probe,
         owner_measured,owner_expected,owner_verdict,build_verdict,map_rule,map_why,src_ref)
        VALUES ('" . $esc($rid) . "','" . $esc($q['unit']) . "','" . $esc($q['group_name']) . "',
                '" . $esc($q['surface']) . "','" . $esc($pr['sid']) . "','" . $esc($a['route']) . "',
                '" . $esc($a['probe']) . "','" . $esc($pr['owner']) . "','" . $esc($dept) . "',
                '" . $esc($verdictOwner) . "','" . $esc($build) . "','" . $esc($pr['rule']) . "',
                '" . $esc($a['why']) . "','" . $esc($q['src_ref']) . "')");
}
printf("  مُثبَتٌ من القرص %d · غيرُ مُثبَتٍ %d%s · مالكٌ مخالفٌ %d\n\n",
    $anchored, count($unproven), $unproven ? ' ⇐ ' . implode('، ', array_slice($unproven, 0, 3)) : '',
    count($ownerMismatch));

/* ═══════════════════════════════════════════════════════════════════════════
   ②-ب · الخطوةُ السابعةُ لأسطحِ النطاقِ القائمة — الربطُ بالسجلِّ المعياريّ
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **سطحٌ في النطاقِ بلا صفٍّ معياريٍّ لا اسمَ له ولا ترتيبَ ولا رابط** —
     والخطوةُ ⑦ توجب ربطَ كلِّ بندٍ بـ`Canonical Screen_ID`. والمقيسُ هنا كشف
     سطحًا واحدًا (`Procurement/warehouses.php` · `WH-02`) بلا صفٍّ أصلًا:
     ترتيبُه صفرٌ واسمُه المعياريُّ فارغٌ ورابطُه مفقود — **وهو تبويبٌ داخلَ
     أبٍ لا بندُ قائمة، لكنَّ التبويبَ اسمٌ مُصيَّرٌ أيضًا فيلزمه سجلُّه.**
   ◆ **والاسمُ والمجموعةُ والترتيبُ من `repair01_requirements`** لا مخترَعةً —
     فالتسميةُ المعياريّةُ مصدرُها المتطلَّبُ نفسُه (خطوةُ السايدبار ②). */
echo "②-ب ربطُ أسطحِ النطاقِ بالسجلِّ المعياريّ ──────────────────────\n";
$linkFix = 0;
$rq2 = $conn->query("SELECT requirement_id, surface, group_name, seq FROM repair01_requirements
                      WHERE stage_no = 9 ORDER BY unit, seq");
$seen = array();
while ($rq2 && $q2 = $rq2->fetch_assoc()) {
    $rid = (string) $q2['requirement_id'];
    if (!isset($ANCH[$rid]) || $ANCH[$rid]['route'] === '') { continue; }
    $rt = $ANCH[$rid]['route'];
    if (isset($seen[$rt])) { continue; }
    $seen[$rt] = true;
    $rtE = $esc($rt);
    $has = (int) $one("SELECT COUNT(*) FROM nav_canonical WHERE route = '$rtE'");
    if ($has > 0) { continue; }
    $sid = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");
    if ($sid === '') { echo "  ⚠ $rt بلا مُعرِّفٍ في سجلِّ الشاشات — لا يُربَط\n"; continue; }
    $vis = (string) $one("SELECT visibility_class FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");
    $sortNo = (int) preg_replace('/\D/', '', $rid);
    if (!$REPORT) {
        $lr2 = \App\Services\Ui\UiLabelRegistry::register($conn, 'screen:' . strtolower($rt), (string) $q2['surface'], array(
            'allowed_context' => 'SIDEBAR SCREEN_TITLE',
            'source_table' => 'nav_canonical', 'source_column' => 'canonical_ar',
            'source_key' => $rt, 'owner_code' => 'DEP-17',
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W9_SCOPE_SURFACE_LABEL', 'origin' => 'W09',
            'src_ref' => 'RPR-W09 §٢-ب · ربطُ سطحٍ قائمٍ بالسجلِّ المعياريّ',
            'caller' => 'repair01_w9_apply.php',
        ));
        if (!$lr2['ok']) { echo '  ⚠ رُدَّ مسمّى ' . $rt . ' — ' . $lr2['code'] . "\n"; }
    }
    $W("INSERT INTO nav_canonical (route, canonical_ar, level_no, level_name, group_name, sort_no,
                                   status, decision_state, application_state, decision_source,
                                   derivation, retirement_status, screen_id, placement_kind)
        VALUES ('$rtE','" . $esc($q2['surface']) . "',2,'العمليات','" . $esc($q2['group_name']) . "'," . $sortNo . ",
                'APPROVED','APPROVED','DEPLOYED','RPR-W09 · ربط سطح النطاق بالسجل المعياري (2026-08-26)',
                'التسمية المعيارية من repair01_requirements.surface','ACTIVE','" . $esc($sid) . "',
                '" . $esc($vis === 'TAB_CHILD' ? 'TAB' : 'MENU_ITEM') . "')");
    echo "  ✔ $rt ⇐ " . $q2['surface'] . " · مجموعة " . $q2['group_name'] . " · ترتيب $sortNo\n";
    $linkFix++;
}
printf("  أسطحٌ رُبطت بالسجلِّ المعياريّ %d\n\n", $linkFix);

/* ═══════════════════════════════════════════════════════════════════════════
   ③ الخطواتُ السبعُ للسايدبار — على أسطحِ النطاقِ كلِّها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③ السايدبارُ — سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح ─────────────\n";
$W("DELETE FROM repair01_w9_sidebar");
$routes = array();
foreach ($ANCH as $rid => $a) { if ($a['route'] !== '') { $routes[$a['route']] = true; } }
$sbN = 0; $sbBad = 0;
foreach (array_keys($routes) as $rt) {
    $rtE = $esc($rt);
    $reg = $conn->query("SELECT screen_id, owner_code, visibility_class, guard_kind
                           FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");
    $reg = $reg ? $reg->fetch_assoc() : null;
    if (!$reg) { $sbBad++; continue; }
    $sid = (string) $reg['screen_id'];

    $can = $conn->query("SELECT canonical_ar, group_name, sort_no, status FROM nav_canonical WHERE route='$rtE' LIMIT 1");
    $can = $can ? $can->fetch_assoc() : null;
    $live = $conn->query("SELECT n.label_ar, g.name AS gname, n.sort_order, n.active
                            FROM nav_items n LEFT JOIN link_groups g ON g.id = n.group_id
                           WHERE n.route = '$rtE' ORDER BY n.active DESC LIMIT 1");
    $live = $live ? $live->fetch_assoc() : null;

    $s1 = $live ? ((int) $live['active'] === 1 ? 'ACTIVE_APPROVED' : 'DISABLED_WITH_REASON') : 'NO_NAV_ITEM';
    $s2live = $live ? (string) $live['label_ar'] : '';
    $s2can  = $can ? (string) $can['canonical_ar'] : '';
    $s2 = ($s2can !== '' && $s2live === $s2can) ? 'LABEL_MATCH' : ($s2can === '' ? 'NO_CANONICAL' : 'LABEL_DRIFT');
    $s3live = $live ? (string) $live['gname'] : '';
    $s3can  = $can ? (string) $can['group_name'] : '';
    $s3 = ($s3can !== '' && $s3live === $s3can) ? 'GROUP_MATCH' : ($s3can === '' ? 'NO_CANONICAL' : 'GROUP_DRIFT');
    $s4no = $can ? (int) $can['sort_no'] : 0;
    $s4 = $s4no > 0 ? 'ORDER_FROM_REGISTRY' : 'NO_ORDER_SOURCE';
    $s5 = ((string) $reg['visibility_class'] === 'TAB_CHILD') ? 'TAB_IN_PARENT' : 'MENU_ITEM';
    $permRows = (int) $one("SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                             WHERE m.code = '$rtE' AND rp.can_view = 1");
    $guard = repair01_w9_guard_of($ROOT, $rt);
    $s6 = ($guard['kind'] !== 'NONE' && $permRows > 0) ? 'GUARDED_AND_GRANTED'
        : ($guard['kind'] === 'NONE' ? 'NO_SERVER_GUARD' : 'NO_GRANT');
    $s7 = ($can && (string) $can['canonical_ar'] !== '' && $sid !== '') ? 1 : 0;

    $W("INSERT INTO repair01_w9_sidebar
        (screen_id,route,owner_code,s1_verdict,s1_rule,s2_label_live,s2_label_canon,s2_verdict,s2_rule,
         s3_group_live,s3_group_canon,s3_verdict,s3_rule,s4_order_src,s4_order_no,s4_verdict,s4_rule,
         s5_parent,s5_verdict,s5_rule,s5_why,s6_visibility,s6_perm_rows,s6_guard_kind,s6_verdict,s6_rule,
         s7_linked,s7_verdict,s7_rule,measured_at)
        VALUES ('" . $esc($sid) . "','$rtE','" . $esc($reg['owner_code']) . "',
                '" . $esc($s1) . "','W9_S1_ACTIVE_BY_TARGET',
                '" . $esc($s2live) . "','" . $esc($s2can) . "','" . $esc($s2) . "','W9_S2_LABEL_FROM_REQUIREMENT',
                '" . $esc($s3live) . "','" . $esc($s3can) . "','" . $esc($s3) . "','W9_S3_GROUP_FROM_CYCLE',
                'nav_canonical.sort_no'," . $s4no . ",'" . $esc($s4) . "','W9_S4_ORDER_FROM_REGISTRY',
                '','" . $esc($s5) . "','W9_S5_PARENT_FROM_DECISION','موضعُ السطحِ من قرارِ الورقةِ لا من الذوق',
                '" . $esc((string) $reg['visibility_class']) . "'," . $permRows . ",
                '" . $esc($guard['kind']) . "','" . $esc($s6) . "','W9_S6_GUARD_AND_GRANT',
                " . $s7 . ",'" . ($s7 ? 'LINKED' : 'NOT_LINKED') . "','W9_S7_CANONICAL_SCREEN_ID',NOW())");
    $sbN++;
}
printf("  أسطحٌ مقيسةٌ بسبعِ خطوات %d · بلا صفٍّ في السجلّ %d\n\n", $sbN, $sbBad);

/* ═══════════════════════════════════════════════════════════════════════════
   ④ العتباتُ — من السجلِّ لا من الشيفرة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "④ العتبات ────────────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w9_thresholds");
$TH = array(
    array('PRC_DIRECT_PURCHASE_CAP', 50000, 'وحدة عملة الأساس', 'حد الشراء المباشر بلا محضر ترسية',
          'قيمة مبدئية قابلة للضبط — DEC-OPEN-07 مفتوح من نوع عتبة فيبنى المحرك قابل الضبط ولا ينتظر الرقم',
          'DEC-OPEN-07'),
    array('PRC_EMERGENCY_CAP', 15000, 'وحدة عملة الأساس', 'حد الشراء الطارئ',
          'قيمة مبدئية قابلة للضبط — تقرأ ولا تكتب في الشيفرة', 'DEC-OPEN-07'),
    array('PRC_MATCH_TOLERANCE_PCT', 2, 'نسبة مئوية', 'سماح فرق المطابقة الثلاثية',
          'الفرق دون هذه النسبة يمر والباقي يلزمه قرار مسبب', 'W9-D-05'),
    array('PRC_EVAL_WEIGHT_ONTIME', 0.5, 'معامل', 'وزن الالتزام بالموعد في درجة المورد',
          'مجموع الأوزان الثلاثة واحد صحيح ومكتوب في قاعدة الاشتقاق', 'W9-D-06'),
    array('PRC_EVAL_WEIGHT_REJECT', 0.3, 'معامل', 'وزن نسبة الرفض في درجة المورد',
          'مجموع الأوزان الثلاثة واحد صحيح ومكتوب في قاعدة الاشتقاق', 'W9-D-06'),
    array('PRC_EVAL_WEIGHT_VARIANCE', 0.2, 'معامل', 'وزن فروق الفاتورة في درجة المورد',
          'مجموع الأوزان الثلاثة واحد صحيح ومكتوب في قاعدة الاشتقاق', 'W9-D-06'),
    array('WH_COUNT_DIFF_TOLERANCE_PCT', 1, 'نسبة مئوية', 'سماح فرق الجرد قبل التحقيق',
          'الفرق فوقها يلزمه تحقيق لا تسوية مباشرة', 'W9-D-07'),
    array('WH_CLOSE_LAG_DAYS', 5, 'يوم', 'مهلة الإقفال الشهري بعد نهاية الشهر',
          'بعدها ينبه النظام ولا يقفل تلقائيا — الإقفال إثبات لا جدولة', 'W9-D-07'),
);
foreach ($TH as $t) {
    $W("INSERT INTO repair01_w9_thresholds (threshold_key,value_num,unit_ar,title_ar,why,decision_ref,src_ref)
        VALUES ('" . $esc($t[0]) . "'," . (float) $t[1] . ",'" . $esc($t[2]) . "','" . $esc($t[3]) . "',
                '" . $esc($t[4]) . "','" . $esc($t[5]) . "','RPR-W09 §٤')");
}
printf("  عتباتٌ مسجَّلة %d\n\n", count($TH));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ آلاتُ الحالة — لكلِّ كيانٍ ممنوعٌ صريحٌ بسبب
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑤ آلاتُ الحالة ───────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w9_states");
$ST = array(
    /* الحزمة */
    array('proc_package', 'draft', 'closed', 1, 'مسؤول المشتريات', 'عضو واحد على الأقل بسبب ضم مكتوب',
          'خطة شراء الفترة', 'اعتماد مدير المشتريات', 'تفتح بقرار مكتوب قبل إصدار طلب العروض',
          'التصحيح بضم أو فك عضو ما دامت في التحرير', ''),
    array('proc_package', 'closed', 'draft', 0, '', '', '', '', '', '',
          'الحزمة المقفلة صدر عنها طلب عروض فإرجاعها للتحرير يفك سند الطلب'),
    array('proc_package', 'draft', 'cancelled', 1, 'مسؤول المشتريات', 'لا طلب عروض صادر عنها',
          'محضر إلغاء حزمة', 'اعتماد مدير المشتريات', 'لا تفتح — تنشأ حزمة جديدة',
          'التصحيح بإلغاء وإنشاء لا بتعديل ملغاة', ''),
    /* طلب العروض */
    array('proc_rfq', 'draft', 'issued', 1, 'مسؤول المشتريات', 'حزمة مقفلة وموعد فتح معلن ودعوة واحدة على الأقل',
          'طلب عروض مرسل', 'اعتماد مدير المشتريات', 'يفتح بتمديد الموعد بقرار مكتوب',
          'التصحيح بتمديد الموعد لا بتعديل الشروط بعد الإرسال', ''),
    array('proc_rfq', 'issued', 'opened', 1, 'لجنة فتح المظاريف', 'بلوغ موعد الفتح المعلن',
          'محضر فتح مظاريف', 'توقيع اللجنة', 'لا يعاد الفتح — المحضر واحد',
          'التصحيح بإثبات ملاحظة في المحضر لا بإعادة الفتح', ''),
    array('proc_rfq', 'issued', 'awarded', 0, '', '', '', '', '', '',
          'الترسية قبل فتح المظاريف تجعل المعايير وصفا للفائز لا حكما عليه'),
    array('proc_rfq', 'opened', 'awarded', 1, 'لجنة الترسية', 'محضر ترسية بمعايير معلنة قبل الفتح',
          'محضر ترسية', 'اعتماد صاحب الصلاحية', 'يفتح بإلغاء الترسية بقرار مكتوب',
          'التصحيح بمحضر تعديل ترسية لا بكتابة فوق المحضر', ''),
    /* العرض */
    array('proc_offer', 'received', 'evaluated', 1, 'لجنة الترسية', 'فتح المظاريف وقع',
          'ورقة تقييم العرض', 'توقيع اللجنة', 'يعاد التقييم بقرار مكتوب',
          'التصحيح بورقة تقييم جديدة تحمل المرجع', ''),
    array('proc_offer', 'received', 'awarded', 0, '', '', '', '', '', '',
          'الترسية على عرض لم يقيم تتخطى المقارنة التي هي غاية طلب العروض'),
    /* محضر الترسية */
    array('proc_award', 'draft', 'approved', 1, 'صاحب صلاحية الاعتماد', 'الفائز له عرض والمعايير معلنة والفائز غير الأدنى بسبب مكتوب',
          'محضر ترسية معتمد', 'بوابة فصل الواجبات', 'يفتح بمحضر إلغاء ترسية',
          'التصحيح بمحضر جديد يحمل مرجع السابق', ''),
    array('proc_award', 'approved', 'draft', 0, '', '', '', '', '', '',
          'المحضر المعتمد صدر عنه أمر شراء فإرجاعه للمسودة يفك سند الأمر'),
    /* أمر الشراء */
    array('proc_order', 'draft', 'issued', 1, 'مسؤول المشتريات', 'محضر ترسية معتمد أو سبب شراء مباشر دون الحد',
          'أمر شراء', 'اعتماد صاحب الصلاحية', 'يفتح بتعديل بمساره الحوكمي',
          'التصحيح بسطر تعديل لا بكتابة فوق الأمر', ''),
    array('proc_order', 'issued', 'closed', 1, 'مسؤول المشتريات', 'مطابقة ثلاثية مقبولة لكل فاتورة',
          'محضر إقفال أمر', 'اعتماد مدير المشتريات', 'يفتح بقرار مكتوب لتعديل لاحق',
          'التصحيح بإشعار مدين أو دائن لا بتعديل المقفل', ''),
    array('proc_order', 'closed', 'issued', 0, '', '', '', '', '', '',
          'الأمر المقفل رحلت مطابقته للمالية فإرجاعه يفك قيدا مرحلا'),
    /* طلب الصرف */
    array('proc_issue_request', 'draft', 'approved', 1, 'أمين المخزن', 'بند واحد على الأقل وخفض المعتمد بسبب مكتوب',
          'طلب صرف معتمد', 'بوابة فصل الواجبات', 'يفتح بإرجاع للتعديل قبل الصرف',
          'التصحيح بتعديل الكميات ما دام لم يصرف', ''),
    array('proc_issue_request', 'approved', 'issued', 1, 'أمين المخزن', 'المصروف لا يتجاوز المعتمد والفترة غير مقفلة',
          'سند صرف', 'توقيع المستلم', 'لا يفتح — المرتجع بمستند مرتجع',
          'التصحيح بسند مرتجع لا بتعديل السند', ''),
    array('proc_issue_request', 'draft', 'issued', 0, '', '', '', '', '', '',
          'الصرف على طلب غير معتمد يتخطى بوابة الاعتماد التي هي غاية الطلب'),
    /* التحويل */
    array('proc_transfer', 'draft', 'in_transit', 1, 'أمين المخزن المرسل', 'بند واحد على الأقل ومخزنان مختلفان',
          'أمر تحويل مرسل', 'اعتماد أمين المخزن', 'يفتح بإلغاء الإرسال قبل الاستلام',
          'التصحيح بإلغاء وإنشاء أمر جديد', ''),
    array('proc_transfer', 'in_transit', 'received', 1, 'أمين المخزن المستلم', 'مستلم غير المرسل وفرق الاستلام بسبب مكتوب',
          'محضر استلام تحويل', 'توقيع المستلم', 'لا يفتح — الفرق بمحضر',
          'التصحيح بمحضر فرق لا بتعديل الكميات', ''),
    array('proc_transfer', 'draft', 'received', 0, '', '', '', '', '', '',
          'الاستلام قبل الإرسال يخلق رصيدا في الوجهة بلا خصم من المصدر'),
    /* الجرد */
    array('proc_count_session', 'draft', 'reviewed', 1, 'مراجع الجرد', 'بند واحد على الأقل',
          'كشف جرد', 'توقيع المراجع', 'يفتح بإرجاع للعد',
          'التصحيح بإعادة عد بند لا بكتابة فوق الكشف', ''),
    array('proc_count_session', 'reviewed', 'approved', 1, 'مدير المخازن', 'كل فرق بقرار تسوية مسبب ومعتمد غير العاد',
          'محضر جرد معتمد', 'بوابة فصل الواجبات', 'يفتح بقرار مكتوب قبل الإقفال',
          'التصحيح بجلسة جرد جديدة تحمل مرجع السابقة', ''),
    array('proc_count_session', 'draft', 'approved', 0, '', '', '', '', '', '',
          'اعتماد جرد بلا مراجعة يفقد الطبقة التي تكشف خطأ العد'),
    /* الإقفال */
    array('proc_wh_close', 'open', 'closed', 1, 'مدير المخازن', 'جرد الفترة معتمد والمعادلة تنطبق',
          'كشف إقفال شهري', 'اعتماد المالية', 'يفتح بقرار إعادة فتح فترة من المالية',
          'التصحيح بقيد تسوية في الفترة التالية لا بتعديل المقفلة', ''),
    array('proc_wh_close', 'closed', 'open', 0, '', '', '', '', '', '',
          'إعادة فتح فترة مقفلة تغير رقما وقع عليه في القوائم — الفتح بقرار مالية لا بفعل مخزن'),
);
foreach ($ST as $s) {
    $W("INSERT INTO repair01_w9_states
        (entity,from_state,to_state,allowed,owner_role,precondition,official_doc,approval_gate,
         reopen_rule,correct_rule,forbid_reason,src_ref)
        VALUES ('" . $esc($s[0]) . "','" . $esc($s[1]) . "','" . $esc($s[2]) . "'," . (int) $s[3] . ",
                '" . $esc($s[4]) . "','" . $esc($s[5]) . "','" . $esc($s[6]) . "','" . $esc($s[7]) . "',
                '" . $esc($s[8]) . "','" . $esc($s[9]) . "','" . $esc($s[10]) . "','RPR-W09 §٧')");
}
$stN = (int) $one("SELECT COUNT(*) FROM repair01_w9_states");
$stE = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w9_states");
$stF = (int) $one("SELECT COUNT(*) FROM repair01_w9_states WHERE allowed = 0");
printf("  كيانات %d · انتقالات %d · ممنوعٌ صراحةً %d\n\n", $stE, $stN, $stF);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ فصلُ الواجبات — بستّةِ أدوارٍ وتركيبةٍ ممنوعةٍ ورمزِ ردٍّ يُنفِّذها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑥ فصلُ الواجبات ─────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w9_sod");
$SOD = array(
    array('prc.award.approve', 'اعتماد محضر الترسية', 'مسؤول المشتريات', 'لجنة الترسية',
          'صاحب صلاحية الاعتماد', 'مسؤول المشتريات', 'مدير المشتريات',
          'من فتح المظاريف لا يعتمد المحضر', 'SAME_ACTOR_AWARD_AND_APPROVE', 'AAM-PRC-01',
          'نائب مدير المشتريات', 'ضمن حدود صلاحية الاعتماد المسجلة', 'بتفويض مكتوب ومؤقت'),
    array('prc.order.anchor', 'إسناد أمر الشراء إلى سنده', 'مسؤول المشتريات', 'مدير المشتريات',
          'صاحب صلاحية الاعتماد', 'مسؤول المشتريات', 'مدير المشتريات',
          'أمر بلا محضر وبلا سبب مباشرة مكتوب', 'PO_WITHOUT_AWARD_NEEDS_REASON', 'AAM-PRC-02',
          'نائب مدير المشتريات', 'الشراء المباشر دون الحد المسجل وحده', 'بتفويض مكتوب ومؤقت'),
    array('prc.order.amend', 'تعديل أمر الشراء', 'مسؤول المشتريات', 'مدير المشتريات',
          'صاحب صلاحية الاعتماد', 'مسؤول المشتريات', 'مدير المشتريات',
          'تعديل بلا مسار حوكمي مكتوب', 'PO_AMEND_WITHOUT_GOV_PATH', 'AAM-PRC-03',
          'نائب مدير المشتريات', 'التعديل داخل حدود الأمر الأصلي', 'بتفويض مكتوب ومؤقت'),
    array('prc.invoice.match', 'قرار فرق المطابقة الثلاثية', 'مسؤول المشتريات', 'المحاسب',
          'مدير المالية', 'مسؤول المشتريات', 'مدير المالية',
          'من اعتمد محضر الترسية لا يقرر فرق فاتورته', 'SAME_ACTOR_APPROVE_AND_MATCH', 'AAM-PRC-04',
          'نائب مدير المالية', 'الفرق خارج العتبة المسجلة', 'بتفويض مكتوب ومؤقت'),
    array('wh.issue.approve', 'اعتماد طلب الصرف', 'الجهة الطالبة', 'أمين المخزن',
          'مدير المخازن', 'أمين المخزن', 'مدير المخازن',
          'من طلب لا يعتمد طلبه', 'SAME_ACTOR_REQUEST_AND_APPROVE', 'AAM-WH-01',
          'نائب مدير المخازن', 'ضمن أصناف المخزن نفسه', 'بتفويض مكتوب ومؤقت'),
    array('wh.hazmat.issue', 'صرف صنف خطر', 'الجهة الطالبة', 'مسؤول السلامة',
          'مدير المخازن', 'أمين المخزن', 'مدير المخازن',
          'صرف صنف يوجب تصريحا بلا مرجع تصريح', 'HAZMAT_ISSUE_NEEDS_PERMIT', 'AAM-WH-02',
          'نائب مسؤول السلامة', 'الأصناف المسجلة في ضوابط الخطر وحدها', 'لا تفويض في السلامة الحرجة'),
    array('wh.transfer.receive', 'استلام التحويل بين المخازن', 'أمين المخزن المرسل', 'أمين المخزن المستلم',
          'مدير المخازن', 'أمين المخزن المستلم', 'مدير المخازن',
          'من أرسل التحويل لا يستلمه', 'SAME_ACTOR_SEND_AND_RECEIVE', 'AAM-WH-03',
          'نائب أمين المخزن', 'التحويل بين مخزنين مختلفين', 'بتفويض مكتوب ومؤقت'),
    array('wh.count.approve', 'اعتماد الجرد', 'العاد', 'مراجع الجرد',
          'مدير المخازن', 'أمين المخزن', 'مدير المخازن',
          'من عد لا يعتمد جرده', 'SAME_ACTOR_COUNT_AND_APPROVE', 'AAM-WH-04',
          'نائب مدير المخازن', 'جلسة الجرد بمخزنها', 'بتفويض مكتوب ومؤقت'),
    array('wh.month.close', 'الإقفال الشهري للمخازن', 'أمين المخزن', 'مدير المخازن',
          'مدير المالية', 'مدير المخازن', 'مدير المالية',
          'إقفال بمعادلة لا تنطبق أو بجرد غير معتمد', 'CLOSE_UNBALANCED', 'AAM-WH-05',
          'نائب مدير المالية', 'الفترة والمخزن معا', 'لا تفويض في الإقفال'),
);
foreach ($SOD as $s) {
    $W("INSERT INTO repair01_w9_sod
        (process_key,process_name,initiator_role,reviewer_role,approver_role,executor_role,closer_role,
         forbidden_combo,enforced_by,authority_rule_id,deputy_role,scope_rule,delegation,effective_date,src_ref)
        VALUES ('" . $esc($s[0]) . "','" . $esc($s[1]) . "','" . $esc($s[2]) . "','" . $esc($s[3]) . "',
                '" . $esc($s[4]) . "','" . $esc($s[5]) . "','" . $esc($s[6]) . "','" . $esc($s[7]) . "',
                '" . $esc($s[8]) . "','" . $esc($s[9]) . "','" . $esc($s[10]) . "','" . $esc($s[11]) . "',
                '" . $esc($s[12]) . "','2026-08-26','RPR-W09 §٧')");
}
printf("  عملياتٌ حرِجة %d\n\n", count($SOD));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ عقودُ الأثر — لكلِّ حدثٍ مستهلكونَ بالاسمِ لا «كلُّ المستهلكين»
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑦ عقودُ الأثر ────────────────────────────────────────────────\n";
$W("DELETE FROM repair01_events WHERE wave = 'W09'");
$EV = array(
    array('PRC_PACKAGE_JOINED', 'ضم طلب شراء إلى حزمة', 'proc_package_member', 'Procurement/proc_packages.php',
          'ضم طلب معتمد إلى حزمة في التحرير', 'package_id · request_id · join_reason',
          'المشتريات · المالية', 'المشتريات تبني خطة الشراء والمالية تقرأ القيمة المتوقعة',
          'الحزمة في التحرير والطلب غير مضموم', 'إعادة بلا أثر', 'w9:PRC_PACKAGE_JOINED', 'يرفع ولا يبتلع', 'فك العضوية'),
    array('PRC_RFQ_INVITED', 'دعوة مورد لطلب عروض', 'proc_rfq_invite', 'Procurement/proc_rfq.php',
          'دعوة مورد قبل فتح المظاريف', 'rfq_id · supplier_id · channel',
          'المشتريات · الموردون', 'المشتريات تقيس عدد المدعوين والمورد يستلم الدعوة',
          'الطلب لم تفتح مظاريفه', 'إعادة بلا أثر', 'w9:PRC_RFQ_INVITED', 'يرفع ولا يبتلع', 'إلغاء الدعوة'),
    array('PRC_OFFER_RECEIVED', 'استلام عرض مورد', 'proc_offer', 'Procurement/proc_offers.php',
          'تسجيل عرض من مورد مدعو', 'rfq_id · offer_id · late',
          'المشتريات · لجنة الترسية', 'المشتريات تعد العروض واللجنة تقارن',
          'المورد مدعو والطلب لم يرس', 'إعادة بلا أثر', 'w9:PRC_OFFER_RECEIVED', 'يرفع ولا يبتلع', 'سحب العرض'),
    array('PRC_RFQ_OPENED', 'فتح مظاريف طلب العروض', 'proc_rfq', 'Procurement/proc_rfq.php',
          'بلوغ موعد الفتح المعلن', 'rfq_id · opened_at · opened_by',
          'المشتريات · لجنة الترسية · المراجعة الداخلية', 'اللجنة تبدأ المقارنة والمراجعة تقرأ زمن الفتح',
          'موعد الفتح بلغ ولم يفتح قبله', 'لا إعادة — الفتح واحد', 'w9:PRC_RFQ_OPENED', 'يرفع ولا يبتلع', 'محضر ملاحظة'),
    array('PRC_AWARD_DRAFTED', 'تحرير محضر الترسية', 'proc_award', 'Procurement/proc_award_minutes.php',
          'ترسية بعد فتح المظاريف بمعايير معلنة', 'rfq_id · award_id · is_lowest',
          'المشتريات · المراجعة الداخلية', 'المشتريات تعد لإصدار الأمر والمراجعة تقرأ سبب غير الأدنى',
          'الطلب مفتوح المظاريف والفائز له عرض', 'إعادة بلا أثر', 'w9:PRC_AWARD_DRAFTED', 'يرفع ولا يبتلع', 'إلغاء المحضر'),
    array('PRC_AWARD_APPROVED', 'اعتماد محضر الترسية', 'proc_award', 'Procurement/proc_award_minutes.php',
          'اعتماد من غير فاتح المظاريف', 'award_id · approved_by',
          'المشتريات · المالية · المراجعة الداخلية', 'المشتريات تصدر الأمر والمالية ترصد الالتزام',
          'المحضر مسودة والمعتمد غير فاتح المظاريف', 'إعادة بلا أثر', 'w9:PRC_AWARD_APPROVED', 'يرفع ولا يبتلع', 'محضر إلغاء ترسية'),
    array('PRC_ORDER_ANCHORED', 'إسناد أمر الشراء إلى محضره', 'proc_order', 'Procurement/proc_po_amendments.php',
          'ربط الأمر بمحضر ترسية معتمد', 'order_id · award_id',
          'المشتريات · المالية · المراجعة الداخلية', 'المراجعة تقرأ السند التنافسي والمالية تربط الالتزام',
          'المحضر معتمد ومورد الأمر هو الفائز', 'إعادة بلا أثر', 'w9:PRC_ORDER_ANCHORED', 'يرفع ولا يبتلع', 'فك الإسناد'),
    array('PRC_ORDER_DIRECT', 'أمر شراء مباشر معلل', 'proc_order', 'Procurement/proc_po_amendments.php',
          'أمر بلا محضر بسبب مكتوب دون الحد', 'order_id · direct_reason',
          'المشتريات · المراجعة الداخلية', 'المراجعة ترصد الشراء المباشر وتقيس تكراره',
          'المبلغ دون حد الشراء المباشر المسجل', 'إعادة بلا أثر', 'w9:PRC_ORDER_DIRECT', 'يرفع ولا يبتلع', 'نزع التعليل'),
    array('PRC_ORDER_AMENDED', 'تعديل أمر شراء', 'proc_po_amendment', 'Procurement/proc_po_amendments.php',
          'تعديل بمسار حوكمي على أمر غير مقفل', 'order_id · seq_no · kind',
          'المشتريات · المالية · المراجعة الداخلية', 'المالية تعدل الالتزام والمراجعة تقرأ المسار',
          'الأمر غير مقفل والمسار مكتوب', 'إعادة بلا أثر', 'w9:PRC_ORDER_AMENDED', 'يرفع ولا يبتلع', 'رد التعديل'),
    array('PRC_DELIVERY_LOGGED', 'تسجيل حدث توريد', 'proc_delivery_event', 'Procurement/proc_delivery_track.php',
          'وعد أو شحن أو وصول أو تأخر', 'order_id · kind · delay_days',
          'المشتريات · المخازن · تقييم الموردين', 'المخازن تستعد والتقييم يقيس الالتزام بالموعد',
          'الأمر قائم والتأخر بسبب مكتوب', 'إعادة بلا أثر', 'w9:PRC_DELIVERY_LOGGED', 'يرفع ولا يبتلع', 'حذف الحدث'),
    array('PRC_INVOICE_MATCHED', 'مطابقة فاتورة بأمرها', 'proc_invoice_match', 'Procurement/po_match.php',
          'مطابقة ثلاثية بين الأمر والاستلام والفاتورة', 'order_id · match_id · verdict',
          'المشتريات · المالية · تقييم الموردين', 'المالية تفرج عن الدفع والتقييم يقيس نسبة الفروق',
          'الأمر قائم وعتبة السماح مسجلة', 'إعادة بلا أثر', 'w9:PRC_INVOICE_MATCHED', 'يرفع ولا يبتلع', 'إلغاء المطابقة'),
    array('PRC_VARIANCE_DECIDED', 'قرار فرق المطابقة', 'proc_invoice_match', 'Procurement/po_match.php',
          'قبول أو رفض فرق خارج العتبة بسبب مكتوب', 'match_id · decision · reason',
          'المالية · المراجعة الداخلية', 'المالية تدفع أو تحجز والمراجعة تقرأ سبب القبول',
          'الفرق خارج العتبة والمقرر غير معتمد المحضر', 'إعادة بلا أثر', 'w9:PRC_VARIANCE_DECIDED', 'يرفع ولا يبتلع', 'نقض القرار'),
    array('PRC_SUPPLIER_EVALUATED', 'تقييم أداء مورد لفترة', 'proc_supplier_eval', 'Procurement/proc_supplier_eval.php',
          'اشتقاق درجة المورد من وقائع الفترة', 'supplier_id · period_ym · score',
          'المشتريات · لجنة التأهيل · القيادة', 'لجنة التأهيل تراجع القائمة والقيادة تقرأ الاتجاه',
          'أوزان التقييم مسجلة في السجل', 'إعادة تحسب من جديد', 'w9:PRC_SUPPLIER_EVALUATED', 'يرفع ولا يبتلع', 'حذف السطر'),
    array('WH_RECEIPT_LINE_ADDED', 'بند سند إدخال بالفحص', 'proc_receipt_line', 'Procurement/wh_receipt.php',
          'إدخال بند بفحصه وبيانات تتبعه بالانطباق', 'receipt_id · line_id · rejected',
          'المخازن · المشتريات · المالية', 'المخزون يزيد بالمقبول والمطابقة تقرأ قيمة الاستلام',
          'الوارد يساوي المقبول زائد المرفوض وبيانات التتبع مقدمة إن وجبت', 'إعادة بلا أثر',
          'w9:WH_RECEIPT_LINE_ADDED', 'يرفع ولا يبتلع', 'سند مرتجع للمورد'),
    array('WH_ISSUE_REQUEST_APPROVED', 'اعتماد طلب صرف', 'proc_issue_request', 'Procurement/wh_issue_requests.php',
          'اعتماد من غير الطالب وخفض المعتمد بسبب', 'request_id',
          'المخازن · الجهة الطالبة', 'المخزن يحجز والجهة تعرف المعتمد لها',
          'المعتمد لا يتجاوز المطلوب', 'إعادة بلا أثر', 'w9:WH_ISSUE_REQUEST_APPROVED', 'يرفع ولا يبتلع', 'إرجاع للتعديل'),
    array('WH_ISSUED', 'صرف مقابل طلب معتمد', 'proc_issue_request', 'Procurement/issue_proc.php',
          'صرف لا يتجاوز المعتمد في فترة غير مقفلة', 'request_id · issue_id · lines',
          'المخازن · الجهة الطالبة · المالية · الصيانة', 'المخزون ينقص والتكلفة تحمل على الجهة والأمر',
          'الطلب معتمد والفترة مفتوحة والتصريح حاضر للخطر', 'إعادة بلا أثر', 'w9:WH_ISSUED', 'يرفع ولا يبتلع', 'سند مرتجع'),
    array('WH_TRANSFER_SENT', 'إرسال تحويل بين مخزنين', 'proc_transfer', 'Procurement/wh_transfer.php',
          'خصم من المصدر لحظة الإرسال', 'transfer_id · qty',
          'المخازن', 'رصيد المصدر ينقص ورصيد الطريق يظهر',
          'مخزنان مختلفان وبند واحد على الأقل', 'إعادة بلا أثر', 'w9:WH_TRANSFER_SENT', 'يرفع ولا يبتلع', 'إلغاء الإرسال'),
    array('WH_TRANSFER_RECEIVED', 'استلام تحويل', 'proc_transfer', 'Procurement/wh_transfer.php',
          'إضافة للوجهة بمستلم غير المرسل', 'transfer_id · qty',
          'المخازن · المالية', 'رصيد الوجهة يزيد ورصيد الطريق يصفر والفرق يقيد',
          'التحويل في الطريق والمستلم غير المرسل', 'إعادة بلا أثر', 'w9:WH_TRANSFER_RECEIVED', 'يرفع ولا يبتلع', 'محضر فرق'),
    array('WH_COUNT_APPROVED', 'اعتماد جلسة جرد', 'proc_count_session', 'Procurement/wh_count.php',
          'اعتماد بعد تسوية كل فرق بسبب مكتوب', 'session_id',
          'المخازن · المالية · المراجعة الداخلية', 'التسويات ترحل والإقفال ينفتح والمراجعة تقرأ الفروق',
          'كل فرق بقرار مسبب والمعتمد غير العاد', 'إعادة بلا أثر', 'w9:WH_COUNT_APPROVED', 'يرفع ولا يبتلع', 'إعادة فتح الجلسة'),
    array('WH_MONTH_CLOSED', 'إقفال شهري لمخزن', 'proc_wh_close', 'Procurement/wh_month_close.php',
          'إقفال بمعادلة تنطبق وجرد معتمد', 'warehouse_id · period_ym · close_id',
          'المخازن · المالية', 'الفترة ترفض الحركة ورصيد الإقفال يرحل للمالية',
          'جرد الفترة معتمد والمعادلة تنطبق', 'لا إعادة — الفتح بقرار مالية', 'w9:WH_MONTH_CLOSED', 'يرفع ولا يبتلع', 'قيد تسوية في التالية'),
);
foreach ($EV as $e) {
    $W("INSERT INTO repair01_events
        (event_code,name,wave,source_unit,source_screen,idempotency_key,consumers,effect_type,
         retry_policy,src_ref,trigger_rule,min_payload,consumer_list,consumer_effect,preconditions,
         failure_policy,compensation,contract_status,contract_rule,contract_stage)
        VALUES ('" . $esc($e[0]) . "','" . $esc($e[1]) . "','W09','" . $esc($e[2]) . "','" . $esc($e[3]) . "',
                '" . $esc($e[10]) . "','" . $esc($e[6]) . "','fact','" . $esc($e[9]) . "','RPR-W09 §٧',
                '" . $esc($e[4]) . "','" . $esc($e[5]) . "','" . $esc($e[6]) . "','" . $esc($e[7]) . "',
                '" . $esc($e[8]) . "','" . $esc($e[11]) . "','" . $esc($e[12]) . "','COMPLETE',
                'W9_EVENT_CONTRACT','W09')");
}
printf("  عقودُ أثرٍ مكتوبة %d\n\n", count($EV));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ المؤجَّلُ — موضعُ السؤالِ المفتوحِ محفوظًا
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑧ المؤجَّلُ بحاجبٍ مفتوح ──────────────────────────────────────\n";
/* ⚠ **`consumed` يعيش خارجَ الشيفرةِ فيُلتقَط قبل المسحِ ويُعاد بعد الإدراج.**
     ═════════════════════════════════════════════════════════════════════
     الاستهلاكُ **واقعةٌ يكتبها `repair01_w9_resume.php` بإثباتٍ مقيس**، لا
     قيمةٌ في مصفوفةٍ هنا. ومسحُ الجدولِ وإعادةُ بنائه بـ`consumed=0` **يُرجع
     الحاجبَ المُغلَقَ مفتوحًا** ويُسقط `W9-24` — وهو العطبُ نفسُه الذي أهلك
     أحكامَ W01 حين مسحَها `repair01_ingest` وأعاد بناءَها من لقطةٍ مجمَّدة.
     فالنصُّ يُعاد اشتقاقُه من المكتبة، **والواقعةُ تبقى.** */
$keepConsumed = array();
$rk = $conn->query("SELECT defer_key, consumed, consumed_at FROM repair01_w9_deferred WHERE consumed = 1");
while ($rk && $kx = $rk->fetch_assoc()) { $keepConsumed[$kx['defer_key']] = (string) $kx['consumed_at']; }

$W("DELETE FROM repair01_w9_deferred");
foreach (repair01_w9_deferred_rows() as $d) {
    $done = isset($keepConsumed[$d['defer_key']]) ? 1 : 0;
    $at   = $done ? "'" . $esc($keepConsumed[$d['defer_key']]) . "'" : 'NULL';
    $W("INSERT INTO repair01_w9_deferred
        (defer_key,requirement_id,blocked_by,part_built,part_waiting,resume_step,probe_sql,
         consumed,consumed_at)
        VALUES ('" . $esc($d['defer_key']) . "','" . $esc($d['requirement_id']) . "',
                '" . $esc($d['blocked_by']) . "','" . $esc($d['part_built']) . "',
                '" . $esc($d['part_waiting']) . "','" . $esc($d['resume_step']) . "',
                '" . $esc($d['probe_sql']) . "'," . $done . "," . $at . ")");
}
if ($keepConsumed) { printf("  ↷ استهلاكٌ محفوظٌ عبرَ إعادةِ البناء %d\n", count($keepConsumed)); }
$dfN = (int) $one("SELECT COUNT(*) FROM repair01_w9_deferred WHERE consumed = 0");
printf("  بنودٌ مؤجَّلةٌ بحاجبِها %d · الحاجب DEC-OPEN-15\n\n", $dfN);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑨ قراراتُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑨ قراراتُ المرحلة ────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w9_decisions");
$mismatchN = count($ownerMismatch);
$noBasis = count(repair01_w9_orders_without_basis($conn));
$DEC = array(
    array('W9-D-01', 'هل تنعقد المرحلة وحاجبها البنيوي مفتوح',
          'تنعقد بأمر المالك المكتوب في 2026-08-26: احفظ مكانه واكمل المرحلة بدونه. والبنية تبنى كاملة وتفحص سلبيا والفئات وحدها تنتظر',
          'الاغلاق الجزئي ممنوع بالامر التنفيذي فلا تعلن هذه المرحلة مغلقة. والبوابة W9-24 تقفل الاتجاهين: تاجيل بلا اعلان خرق واعلان بعد الجواب تقادم',
          3),
    array('W9-D-02', 'هل يمد supplier_rfqs ليخدم طلب عروض الشراء ام يبنى كيان مستقل',
          'كيان مستقل proc_rfq. حبة القائم طلب × عقد عميل وحبة المطلوب طلب × حزمة شراء',
          'client_contract_id مملوء في صفوف supplier_rfqs الثلاثة و request_id خاو فيها كلها. و rfq_lines.commitment_id غير قابل للعدم ومفتاحه الفريد rfq_id مع commitment_id فمده لحزمة شراء يوجب نزع قيد حي على تسعة صفوف. حبتان مختلفتان فكيانان',
          3),
    array('W9-D-03', 'ماذا عن أمر الشراء بلا سند تنافسي في الشجرة الحية',
          'يعلن ويقاس ولا يصحح باثر رجعي. وامر جديد بلا محضر او سبب مباشرة يرد',
          'award_minute_id و rfq_id كانا صفرا في اثنين وعشرين امرا من اثنين وعشرين قبل هذه المرحلة. واسناد سند باثر رجعي لامر مضى يخترع تنافسا لم يقع',
          $noBasis),
    array('W9-D-04', 'ماذا عن سطح المالك المخالف داخل النطاق',
          'يعلن ولا يدهس. ورفع الملكية قرار الادارة المالكة لا قرار هذه الموجة',
          'السطح تحت مالك غير مالك متطلبه يقاس ويكتب في repair01_w9_scope بحكم MISMATCH. وتغيير المالك يغير من يرى الشاشة فهو قرار حوكمة لا تصحيح تقني',
          $mismatchN),
    array('W9-D-05', 'ما سماح فرق المطابقة الثلاثية',
          'اثنان بالمئة قيمة مبدئية مسجلة في repair01_w9_thresholds تقرا ولا تكتب في الشيفرة',
          'العتبة من نوع ضبط لا بنية فتبنى قابلة الضبط ولا تنتظر رقم المالك. والفرق فوقها لا يمر بلا قرار مسبب',
          1),
    array('W9-D-06', 'كيف تشتق درجة المورد',
          'ثلاثة اوزان مسجلة مجموعها واحد صحيح: الالتزام بالموعد والرفض والفروق. والقاعدة تكتب في كل سطر',
          'رقم بلا قاعدة اشتقاق لا يراجع ولا يعترض عليه. وكتابة القاعدة في السطر تجعل التقييم قابلا للنقض بالحجة',
          3),
    array('W9-D-07', 'ما ضوابط الجرد والاقفال',
          'سماح فرق الجرد واحد بالمئة ومهلة الاقفال خمسة ايام — كلاهما في السجل',
          'الفرق فوق السماح يلزمه تحقيق لا تسوية مباشرة. والمهلة تنبه ولا تقفل تلقائيا لان الاقفال اثبات لا جدولة',
          2),
    array('W9-D-09', 'ست بوابات تقيس سجلات بنيت ولم تمارس بعد — أتمر خضراء على مجموعة خاوية',
          'الخلاء معلن هنا بنصه فتمر البوابات مرة واحدة. وقبولها النهائي مؤجل الى W16 برحلة موظف حقيقي لا برحلة اداة',
          'الحزم وطلبات العروض والمحاضر واحداث التوريد وارصدة الحالة وجلسات الجرد والاقفال كلها اليوم صفر صف في القاعدة الحية. '
        . 'وبوابة تقارن صفرا بصفر تمر على تطابق لا شيء — وهو نمط وقع مرتين في هذه الحملة (W1-08 و W7-10 حتى W7-14). '
        . 'فالصفر يمر معلنا بقرار وحده ويسقط بلا اعلان. والرحلة تمارس الست فعلا ثم تكنس اثرها فالبناء مثبت وظيفيا لا مدعى',
          6),
    array('W9-D-10', 'ماذا عن سطح النطاق الذي لا مصدر ترتيب له',
          'يربط بالسجل المعياري لا يعلن. والاسم والمجموعة والترتيب من repair01_requirements لا مخترعة',
          'المقيس كشف سطحا واحدا هو Procurement/warehouses.php بلا صف في nav_canonical اصلا: '
        . 'لا اسم معياري ولا ترتيب ولا رابط. وهو تبويب داخل اب لا بند قائمة لكن التبويب اسم مصير ايضا فيلزمه سجله. '
        . 'والخطوة السابعة توجب ربط كل بند فالربط تنفيذ للخطوة لا استثناء منها',
          0),
    array('W9-D-08', 'ماذا عن فجوات المستهدف الثلاث في هذه الموجة',
          'rfq.php يبنى باسم proc_rfq.php و po.php يخدمه orders_proc.php الحي و central_proc_budget.php خارج متطلبات المرحلة',
          'الفجوة اسم مستهدف لا اسم ملف ملزم. و po.php هدف يخدمه سطح حي فدمج لا بناء. و central_proc_budget.php لا يقابله متطلب في الاربعة والثلاثين فيبقى فجوة معلنة لا تبنى بلا متطلب',
          3),
);
foreach ($DEC as $d) {
    $W("INSERT INTO repair01_w9_decisions (decision_id,question,ruling,rationale,scope_rows)
        VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "','" . $esc($d[3]) . "'," . (int) $d[4] . ")");
}
printf("  قرارات %d\n\n", count($DEC));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑩ الإصلاحاتُ — كلٌّ بمتطلَّبِه الكاشف
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑩ الإصلاحاتُ بكاشفِها ────────────────────────────────────────\n";
$W("DELETE FROM repair01_w9_fixes");
$FIX = array(
    array('W9-FIX-01', 'SCHEMA', 'proc_order.award_minute_id · direct_reason',
          'اضافة عمودي السند التنافسي وسبب الشراء المباشر الى امر الشراء',
          'PRC-11', 'امر الشراء لا امر بلا محضر ترسية او سبب مباشرة مكتوب — ولم يكن في الجدول موضع لايهما',
          'المقيس قبل الاصلاح: award_minute_id و rfq_id صفر في 22 من 22 امرا'),
    array('W9-FIX-02', 'SCHEMA', 'proc_receipt_line qty_received · qty_accepted · qty_rejected',
          'فصل الوارد عن المقبول عن المرفوض في بند سند الادخال',
          'WH-05', 'بنود سند الادخال كل بند بكميته الواردة والمقبولة والمرفوضة — وكان العمود qty وحده',
          'المقيس قبل الاصلاح: عمود qty واحد بلا تمييز والفحص غير قابل للقياس'),
    array('W9-FIX-03', 'SCHEMA', 'proc_count_session · proc_count_line',
          'بناء جلسة الجرد وبنودها — وشاشة wh_count كانت تستعلم proc_item و proc_warehouse وحدهما',
          'WH-15', 'جلسة جرد × مخزن براس وبنود فروق تابعة — ولم يكن في القاعدة جدول يحفظ جلسة',
          'المقيس قبل الاصلاح: صفر جدول جرد و wh_count.php يقرا الاصناف والمخازن ولا يكتب جلسة'),
    array('W9-FIX-04', 'SCHEMA', 'proc_transfer · proc_transfer_line',
          'بناء راس امر التحويل وبنوده — وشاشة wh_transfer كانت تكتب في proc_stock_move مباشرة',
          'WH-13', 'امر تحويل × مخزنين امر واحد — والكتابة المباشرة في الحركات تمحو المرسل غير المستلم',
          'المقيس قبل الاصلاح: صفر جدول تحويل و wh_transfer.php يمس proc_stock_move وحده'),
    array('W9-FIX-05', 'SCHEMA', 'proc_issue_request · proc_issue_request_line',
          'فصل طلب الصرف عن سند الصرف',
          'WH-08', 'طلب صرف × جهة طلب واحد وبنوده غير بنود السند — وخلطهما يمحو طلب ولم يصرف',
          'المقيس قبل الاصلاح: صفر جدول طلب صرف — الصرف يبدا من السند مباشرة'),
    array('W9-FIX-06', 'SCHEMA', 'proc_stock_state',
          'بعد الحالة في الرصيد: صالح ومحجوز ومحجور وتالف ومنته',
          'WH-06', 'ارصدة المخزون بحالاتها صنف × مخزن × حالة سطر رصيد مشتق — والرصيد كان رقما واحدا',
          'المقيس قبل الاصلاح: proc_stock_move بلا بعد حالة والمتاح يجمع التالف مع الصالح'),
    array('W9-FIX-08', 'CODE', 'Procurement/wh_count.php',
          'وصل شاشة الجرد بجلسة الجرد وبنودها — كانت تستعلم proc_item و proc_warehouse وحدهما وتكتب تسوية مباشرة في الحركات',
          'WH-15', 'جلسة جرد × مخزن براس وبنود فروق تابعة — والشاشة كانت بلا كيان يحمل الجلسة فلا عاد ولا مراجع ولا معتمد',
          'المقيس قبل الوصل: مرساة WH-15 و WH-16 غير مثبتة — الملف لا يمس proc_count_session'),
    array('W9-FIX-09', 'CODE', 'Procurement/wh_transfer.php',
          'وصل شاشة التحويل بامر التحويل وبنوده — كانت تكتب في proc_stock_move مباشرة',
          'WH-13', 'امر تحويل × مخزنين امر واحد — والكتابة المباشرة تمحو المرسل غير المستلم ورصيد الطريق وفرق الاستلام',
          'المقيس قبل الوصل: مرساة WH-13 و WH-14 غير مثبتة — الملف لا يمس proc_transfer'),
    array('W9-FIX-10', 'CODE', 'Procurement/stock_proc.php',
          'وصل شاشة الارصدة ببعد الحالة',
          'WH-06', 'صنف × مخزن × حالة — والرصيد الواحد يجمع التالف مع الصالح فيصير المتاح كذبا يصرف عليه',
          'المقيس قبل الوصل: مرساة WH-06 غير مثبتة — الملف لا يمس proc_stock_state'),
    array('W9-FIX-11', 'CODE', 'Procurement/po_match.php',
          'وصل شاشة المطابقة بسجل المطابقة الثلاثية',
          'PRC-15', 'مطابقة × فاتورة × امر — و match_state في راس الامر لا يتسع لامر بفاتورتين',
          'المقيس قبل الوصل: مرساة PRC-15 غير مثبتة — الملف لا يمس proc_invoice_match'),
    array('W9-FIX-12', 'CODE', 'Procurement/warehouse_board.php',
          'وصل لوحة المخازن بحركة المخزن نفسها',
          'WH-01', 'لوحة المخازن مؤشر × مخزن × فترة قراءة حية — وكانت تقرا المستندات ولا تمس proc_stock_move',
          'المقيس قبل الوصل: مرساة WH-01 غير مثبتة — الملف يقرا proc_custody و proc_issue و proc_item و proc_receipt_custody'),
    array('W9-FIX-13', 'CODE', 'Procurement/receipt_custody_proc.php',
          'وصل شاشة العهد بدفتر العهد proc_custody — كانت تقرا سند الاستلام من المورد وحده',
          'WH-12', 'عهدة × مستلم سطر عهدة بمرتجعها — والمرتجع والمستهلك والمتبقي بلا سطح يعرضها',
          'المقيس قبل الوصل: مرساة WH-12 غير مثبتة — الملف يقرا proc_receipt_custody ولا يمس proc_custody'),
    array('W9-FIX-07', 'REGISTRY', 'app/Core/TenantRegistry.php',
          'تسجيل سبعة عشر جدولا جديدا في سجل المستاجر بنوع كل جدول وحذفه الناعم',
          'PRC-04', 'حزمة شراء × فترة — وجدول غير مسجل في TenantRegistry يرد عند اول وصول عبر TenantDb',
          'المقيس: 17 جدولا اضيفت الى الخريطة والرؤوس بحذف ناعم والابناء بلا'),
);
foreach ($FIX as $f) {
    $W("INSERT INTO repair01_w9_fixes (fix_key,kind,target,what,revealed_by,reveal_why,evidence)
        VALUES ('" . $esc($f[0]) . "','" . $esc($f[1]) . "','" . $esc($f[2]) . "','" . $esc($f[3]) . "',
                '" . $esc($f[4]) . "','" . $esc($f[5]) . "','" . $esc($f[6]) . "')");
}
printf("  إصلاحاتٌ بكاشفِها %d\n\n", count($FIX));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑪ مفرداتُ القاموس — الرمزُ يُقارَن والمعروضُ عربيّ
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑪ قاموسُ عرضِ الرموز ─────────────────────────────────────────\n";
$W("DELETE FROM repair01_w6_code_dict WHERE src_ref LIKE 'RPR-W09%'");
$DICT = array(
    'draft' => 'مسودة', 'issued' => 'صادر', 'closed' => 'مقفل', 'opened' => 'فتحت مظاريفه',
    'awarded' => 'رست', 'cancelled' => 'ملغى', 'received' => 'مستلم', 'evaluated' => 'مقيم',
    'approved' => 'معتمد', 'submitted' => 'مقدم', 'pending' => 'قيد النظر', 'in_transit' => 'في الطريق',
    'reviewed' => 'روجع', 'open' => 'مفتوح',
    'offered' => 'قدم عرضا', 'declined' => 'اعتذر', 'silent' => 'لم يرد',
    'QTY' => 'كمية', 'PRICE' => 'سعر', 'DATE' => 'موعد', 'CANCEL' => 'الغاء', 'ITEM' => 'صنف',
    'PROMISED' => 'وعد', 'SHIPPED' => 'شحن', 'ARRIVED' => 'وصل', 'DELAYED' => 'تاخر', 'PARTIAL' => 'جزئي',
    'MATCHED' => 'مطابق', 'VARIANCE' => 'بفرق', 'BLOCKED' => 'محجوب',
    'GOOD' => 'صالح', 'RESERVED' => 'محجوز', 'QUARANTINE' => 'محجور', 'DAMAGED' => 'تالف', 'EXPIRED' => 'منته',
    'FULL' => 'شامل', 'CYCLE' => 'دوري', 'SPOT' => 'مفاجئ',
    'ADJUST' => 'تسوية', 'INVESTIGATE' => 'تحقيق', 'WRITE_OFF' => 'اعدام',
    'BLOCK' => 'منع', 'WARN_OVERRIDE' => 'تنبيه بتجاوز', 'FEFO' => 'الاقدم صلاحية اولا',
    'FIFO' => 'الاقدم دخولا اولا', 'FREE' => 'باختيار امين المخزن',
);
$dN = 0;
foreach ($DICT as $raw => $ar) {
    if ($W("INSERT INTO repair01_w6_code_dict (raw_code, display_ar, src_ref)
            VALUES ('" . $esc($raw) . "','" . $esc($ar) . "','RPR-W09 §١١')
            ON DUPLICATE KEY UPDATE display_ar = VALUES(display_ar)")) { $dN++; }
}
printf("  مفرداتٌ مسجَّلة %d\n\n", $dN);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑫ مصفوفةُ المواضعِ المُصيَّرة — سطرٌ لكلِّ سطحِ نموّ
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **حاجبُ ما قبلَ الالتزامِ `U1` يرفض مسارًا مُصيَّرًا بلا صفٍّ في المصفوفة**
     (`docs/uxui_matrix_20260818.csv`). والصفُّ **يُشتقُّ من السجلِّ لا يُكتب
     يدًا** — فالمصفوفةُ إسقاطٌ لا مصدرٌ ثانٍ للحقيقة، ونسخةٌ يدويّةٌ تتفرّق
     عن `nav_canonical` عند أوّلِ تسمية. */
echo "⑫ مصفوفةُ المواضعِ المُصيَّرة ─────────────────────────────────\n";
$CSV = $ROOT . '/docs/uxui_matrix_20260818.csv';
if (!is_file($CSV)) {
    echo "  ⚠ لا مصفوفةَ على القرص — الخطوةُ تُتخطّى\n\n";
} else {
    $raw = file($CSV, FILE_IGNORE_NEW_LINES);
    $have = array(); $maxN = 0;
    foreach ($raw as $ln) {
        if (preg_match('~^(\d+),([^,]+),~', $ln, $mm)) {
            $have[mb_strtolower(trim($mm[2]))] = true;
            if ((int) $mm[1] > $maxN) { $maxN = (int) $mm[1]; }
        }
    }
    $q = function ($s) { return '"' . str_replace('"', '""', (string) $s) . '"'; };
    $added = 0; $append = '';
    foreach (repair01_w9_new_surfaces() as $s) {
        if (isset($have[mb_strtolower($s['route'])])) { continue; }
        $rtE = $esc($s['route']);
        $deptAr = (string) $one("SELECT legacy_name FROM repair01_dept_crosswalk
                                  WHERE canonical_code = '" . $esc($s['owner']) . "' ORDER BY id LIMIT 1");
        $depts = (string) $one("SELECT consumers FROM gov_screen_cycle
                                 WHERE screen_file = '" . $esc(basename($s['route'])) . "' LIMIT 1");
        $def = 'تعرض ' . $s['ar'] . ' في دورة ' . $s['group'] . ' لدى ' . $deptAr
             . '. المستند الناتج ' . $s['doc'] . ' والخطوة التالية ' . $s['next'] . '.';
        $maxN++;
        $append .= implode(',', array(
            $maxN, $s['route'], $q($s['ar']), $q($s['ar']), $q(''), '—', $q($def),
            $q($deptAr), $q('2 — العمليات'), $q($s['group']), (int) $s['sort'],
            $q('شاشةٌ مستقلة'), max(1, substr_count((string) $depts, '·') + 1), $q($depts),
            $q('قدرةٌ ثبت غيابُها فبُنيت في موضعِها المعياريّ'), 'APPROVED',
            $q('دورةُ الإدارةِ المالكةِ في الحزمة — RPR-W09'), '—', '—', 'ACTIVE', '—',
            $q($s['ar']), $q($s['group']), $q('موضعُه من دورةِ العمل — قرارُ الورقة'), $q($s['group']),
        )) . "\n";
        $added++;
    }
    if ($added > 0 && !$REPORT) { file_put_contents($CSV, $append, FILE_APPEND); }
    printf("  أسطرٌ أُضيفت %d · قائمةٌ سلفًا %d\n\n",
        $added, count(repair01_w9_new_surfaces()) - $added);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ⑬ تصنيفُ المساحةِ لأسطحِ النموّ — سلّمُ الحسمِ السداسيّ
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **حاجبُ `NF-24` يرفض مسارَ تنقُّلٍ نشِطًا خارجَ سجلِّ تصنيفِ المساحات**
     (`gov_space_appearances`). والسطحُ المملوكُ لإدارةٍ واحدةٍ يُحسَم عند
     **الخطوةِ الأولى** من السلّم: المساحةُ هي الإدارةُ المالكةُ في السجلِّ
     المعياريّ — فلا ظهورَ في مساحتَين ولا حاجةَ لبقيّةِ السلّم.
   ◆ **واسمُ الإدارةِ من جسرِ المسمّياتِ لا مخترَعًا** (`G0-06`). */
echo "⑬ تصنيفُ مساحةِ أسطحِ النموّ ──────────────────────────────────\n";
$clsN = 0; $clsHave = 0;
$maxCls = (int) $one("SELECT COALESCE(MAX(id),0) FROM gov_space_appearances");
foreach (repair01_w9_new_surfaces() as $s) {
    $rtE = $esc($s['route']);
    if ((int) $one("SELECT COUNT(*) FROM gov_space_appearances WHERE route = '$rtE'") > 0) {
        $clsHave++; continue;
    }
    $deptAr = (string) $one("SELECT legacy_name FROM repair01_dept_crosswalk
                              WHERE canonical_code = '" . $esc($s['owner']) . "' ORDER BY id LIMIT 1");
    if ($deptAr === '') { echo '  ⚠ لا جسرَ مسمًّى لـ' . $s['owner'] . " — لا يُصنَّف\n"; continue; }
    /* ⛔ `id` مفتاحٌ أوّليٌّ بلا ترقيمٍ تلقائيّ — يُحسَب صراحةً (درسُ W05) */
    $maxCls++;
    $W("INSERT INTO gov_space_appearances
        (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
         src_class, src_ownership, src_decision, src_note, spaces_count, cls, ownership,
         decision, basis, rule_step, view_fields)
        VALUES ($maxCls,'" . $esc($deptAr) . "','DEPARTMENT','','" . $esc($s['ar']) . "','$rtE',
                '" . $esc($deptAr) . "','BUSINESS_DEPARTMENT','RPR-W09','VALID','CONFIRMED',
                'سطحُ نموٍّ مختومٌ W09 — صُنِّف بسلّمِ الحسمِ السداسيّ',1,'OWNED','VALID','CONFIRMED',
                'المساحةُ هي الإدارةُ المالكةُ للسطحِ في السجلِّ المعياريّ (" . $esc($s['owner']) . ")',1,'')");
    $clsN++;
}
printf("  أسطحٌ صُنِّفت %d · مصنَّفةٌ سلفًا %d\n\n", $clsN, $clsHave);

echo "───────────────────────────────────────────────────────────────\n";
echo "الحكم: " . ($REPORT ? 'قياسٌ بلا كتابة ✔' : 'كُتب ✔') . "\n";
exit(0);
