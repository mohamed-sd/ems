<?php
/**
 * tools/repair01_w7_apply.php — قياسٌ وكتابةٌ للمرحلةِ السابعة (الصيانة والنقل)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السايدبارُ قبل الشاشات** (§٤ · RPR-PATCH-01 ③): الخطواتُ السبعُ بترتيبها
 *   على أسطحِ النطاقِ **قبل** أن يُبنى سطحٌ منها — وأسطحُ النموِّ تُسجَّل أوّلًا
 *   لأنّها جزءٌ من مقامِ السايدبار.
 *
 * ⛔ `origin` = `W07` بالضبط (RPR-PATCH-02): أساسُ السجلِّ (٦٥١) مُجمَّدٌ،
 *   والنموُّ مسموحٌ **مختومًا وحدَه** — وحاجبا `W3-14` و`W4-15` يسقطان على
 *   نموٍّ بلا ختم.
 *
 * ⛔ **ولا يُكتب صفٌّ في `gov_screen_cycle`**: `G0-07` يقيس مقامَ الأسطحِ الحيَّ
 *   ويقارنه بـ٦٦٤ المُجمَّدة، فصفٌّ جديدٌ هناك يُنمّي مقامَ الدراسةِ المُجمَّد
 *   ويُسقط الحاجبَ (‏W5-D-13 — وقع فعلًا في W05 قبل أن يُنزع).
 *
 * ◆ **وقواعدُ السلامةِ تُبذَر سجلًّا لا مصفوفة** (`DEC-OPEN-12` ③): «لا قائمةَ
 *   صلبةً واحدةً لكلِّ المعدّات». والبذرةُ هنا **صفوفُ بياناتٍ في القاعدة**
 *   يعدّلها المالكُ بلا لمسِ شيفرة.
 *
 * التشغيل: php tools/repair01_w7_apply.php [--report] [--revert]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
require_once $ROOT . '/tools/lib/repair01_w3_contracts.php';
require_once $ROOT . '/tools/lib/repair01_w7_scan.php';
require_once $ROOT . '/tools/lib/repair01_w7_contracts.php';
require_once $ROOT . '/app/Services/Maintenance/MaintenanceCycleService.php';
require_once $ROOT . '/app/Services/Ui/UiLabelRegistry.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }
use App\Services\Maintenance\MaintenanceCycleService as MCS;

$REPORT = in_array('--report', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w7_one($conn, $sql); };
$W = function ($sql) use ($conn, $REPORT) {
    if ($REPORT) { return true; }
    if ($conn->query($sql) === true) { return true; }
    echo '  ✘ ' . $conn->error . "\n  ⇐ " . mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 180) . "\n";
    return false;
};

echo "══ REPAIR01 · W07 — " . ($REVERT ? 'إرجاع' : ($REPORT ? 'قياسٌ بلا كتابة' : 'قياسٌ وكتابة')) . " ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⓪ الإرجاع — يُفرِّغ ما كتبته هذه الأداةُ وحدَها
   ═══════════════════════════════════════════════════════════════════════════ */
if ($REVERT) {
    foreach (repair01_w7_new_surfaces() as $s) {
        $rt = $esc($s['route']);
        $conn->query("DELETE FROM nav_items WHERE route = '$rt'");
        $conn->query("DELETE FROM nav_canonical WHERE route = '$rt'");
        $conn->query("DELETE FROM role_permissions WHERE module_id IN (SELECT id FROM modules WHERE code = '$rt')");
        $conn->query("DELETE FROM modules WHERE code = '$rt'");
        $conn->query("DELETE FROM repair01_screen_registry WHERE route = '$rt' AND origin = 'W07'");
        $conn->query("DELETE FROM gov_screen_cycle WHERE screen_file = '" . $esc(basename($s['route'])) . "'");
        echo "  ✔ نُزع سطحُ النموّ " . $s['route'] . "\n";
    }
    /* ⛔ **بالبادئةِ لا بالمسار**: مُعرِّفُ المجموعةِ مشتقٌّ من `screen_id` لا من
         المسار، فحذفُه بنمطِ المسارِ يترك صفوفًا يتيمةً. والبادئةُ `n9o_w7_`
         مملوكةٌ لهذه الموجةِ وحدَها. */
    $conn->query("DELETE FROM link_groups WHERE group_code LIKE 'n9o_w7\\_%'");
    echo "  ✔ مجموعاتُ الملاحةِ المكتوبةُ في W07 نُزعت\n";
    foreach (array('repair01_w7_sidebar', 'repair01_w7_scope', 'repair01_w7_decisions',
                   'repair01_w7_states', 'repair01_w7_sod',
                   'mnt_return_cert', 'mnt_repeat_repair', 'mnt_part_request',
                   'mnt_external_repair', 'mnt_daily_care', 'mnt_kpi_period', 'mnt_safety_rule',
                   'trp_origin_handover', 'trp_trip_leg', 'trp_damage_claim',
                   'trp_closure', 'trp_kpi_period') as $t) {
        if ($conn->query("DELETE FROM `$t`")) { echo "  ✔ فُرِّغ $t\n"; }
    }
    $conn->query("DELETE FROM repair01_events WHERE contract_stage = 'W07' AND wave = 'W07'");
    echo "  ✔ عقودُ الأثرِ المكتوبةُ في W07 نُزعت\n";
    $conn->query("UPDATE mnt_order SET safety_severity = NULL, safety_rule_ref = '', safety_system_key = '',
                        ops_impact = NULL, ops_impact_rule = '', failure_kind = '',
                        lockout_state = 'none', lockout_at = NULL, lockout_by = NULL,
                        w7_state_rule = '', w7_cert_id = NULL
                   WHERE safety_rule_ref <> '' OR w7_state_rule <> ''");
    $conn->query("UPDATE equipments SET w7_readiness_state = NULL, w7_readiness_rule = '',
                        w7_operating_limits = '', w7_cert_id = NULL
                   WHERE w7_readiness_rule <> ''");
    echo "  ✔ محاورُ التصنيفِ الأربعةُ أُفرِغت من الأوامرِ والكروت\n";
    $conn->query("DELETE FROM repair01_w6_code_dict WHERE src_ref LIKE 'RPR-W07%'");
    echo "  ✔ مفرداتُ القاموسِ المكتوبةُ في W07 نُزعت\n";
    echo "\nالحكم: رجعت ✔ (والجداولُ والقيودُ تُنزع بهجرةِ التراجع)\n";
    exit(0);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① أسطحُ النموِّ — قبلَ السايدبارِ لأنّها جزءٌ من مقامِه
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① أسطحُ النموِّ — أحدَ عشرَ سطحًا مختومًا بموجتِها ────────────────\n";
$newN = 0; $navN = 0; $permN = 0; $labelN = 0; $missing = array();
$maxSid = (int) preg_replace('/\D/', '', (string) $one("SELECT screen_id FROM repair01_screen_registry
                                                          ORDER BY screen_id DESC LIMIT 1"));
foreach (repair01_w7_new_surfaces() as $s) {
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

    /* ⓒ **المسمّى يُسجَّل قبل أن يُصيَّر** (‏W06 · W07_FEEDBACK): الاسمُ المشكولُ
         أو الحاملُ مصطلحًا تقنيًّا **يُردُّ ويُقيَّد رفضُه**، و`W6-05` يسقط على
         اسمٍ مُصيَّرٍ خارجَ `repair01_ui_labels`. فالتسجيلُ **قبل** كتابةِ الصفِّ
         المعياريِّ لا بعده — ولو انعكس الترتيبُ صُيِّر اسمٌ لم يُقرَّ بعد. */
    if (!$REPORT) {
        $lr = \App\Services\Ui\UiLabelRegistry::register($conn, 'screen:' . strtolower($s['route']), $s['ar'], array(
            'allowed_context' => 'SIDEBAR SCREEN_TITLE',
            'source_table' => 'nav_canonical', 'source_column' => 'canonical_ar',
            'source_key' => $s['route'], 'owner_code' => $s['owner'],
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W7_NEW_SURFACE_LABEL', 'origin' => 'W07',
            'src_ref' => 'RPR-W07 §٤ · سطحُ نموٍّ مختوم', 'caller' => 'repair01_w7_apply.php',
        ));
        if (!$lr['ok']) { echo '  ⚠ رُدَّ مسمّى ' . $s['route'] . ' — ' . $lr['code'] . ': ' . $lr['detail'] . "\n"; }
        else { $labelN++; }
        $gr = \App\Services\Ui\UiLabelRegistry::register($conn, 'group:w7:' . strtolower($s['group']), $s['group'], array(
            'allowed_context' => 'SIDEBAR', 'source_table' => 'nav_canonical', 'source_column' => 'group_name',
            'source_key' => $s['group'], 'owner_code' => $s['owner'],
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W7_CYCLE_GROUP_LABEL', 'origin' => 'W07',
            'src_ref' => 'RPR-W07 §٤ · مجموعةُ دورةِ العمل', 'caller' => 'repair01_w7_apply.php',
        ));
        if ($gr['ok']) { $labelN++; }
    }

    /* ⓓ السجلُّ المعياريُّ للتنقُّل — الاسمُ والمجموعةُ والترتيبُ من دورةِ العمل */
    $sid = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    if ($sid === '') { $maxSid++; $sid = 'SCR-' . str_pad((string) $maxSid, 4, '0', STR_PAD_LEFT); }
    $W("INSERT INTO nav_canonical (route, canonical_ar, level_no, level_name, group_name, sort_no,
                                   status, decision_state, application_state, decision_source,
                                   derivation, retirement_status, screen_id)
        VALUES ('$rt','" . $esc($s['ar']) . "',2,'العمليات','" . $esc($s['group']) . "'," . (int) $s['sort'] . ",
                'APPROVED','APPROVED','DEPLOYED','RPR-W07 · الصيانة والنقل (2026-08-25)',
                'دورةُ الإدارةِ المالكةِ في الحزمة','ACTIVE','" . $esc($sid) . "')
        ON DUPLICATE KEY UPDATE canonical_ar=VALUES(canonical_ar), group_name=VALUES(group_name),
          sort_no=VALUES(sort_no), status=VALUES(status), screen_id=VALUES(screen_id)");

    /* ⓔ **مجموعةُ الدورةِ لا مجموعةُ الشقيق** (خطوةُ السايدبارِ ③) — والبادئةُ
         `n9o_` إلزاميّةٌ وإلّا كنسها `nav09_sweep_others` إلى «أخرى». */
    if ($modId > 0) {
        /* ⛔ **مُعرِّفُ المجموعةِ يُشتقُّ من `screen_id` لا من المسار**، و`link_groups.group_code`
             **`VARCHAR(40)`**. والاشتقاقُ من المسارِ يعطي
             `n9o_w7_transport_transfer_origin_hand_r1` — أربعين محرفًا بالضبط —
             فيُبتَر الدورُ ويتصادم `r1` و`r10` و`r11` **في مفتاحٍ واحد**، ثمَّ
             تفشل قراءةُ المُعرِّفِ بالكودِ الكاملِ فلا يُكتب بندُ قائمةٍ أصلًا.
             وهو بترٌ بتحذيرٍ لا خطأ: الاستعلامُ ينجح والصفُّ يُكتب ناقصًا.
             والمُعرِّفُ من `screen_id` ثمانيةَ عشرَ محرفًا وفريدٌ بالبناء. */
        $gkey = 'n9o_w7_' . strtolower(str_replace('-', '', $sid));
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

    /* ⓕ **مصفوفةُ الدورةِ الحيّة — والجدارُ الثاني رُفع، فالصفُّ يُكتب** ─────
         W05 وقفت هنا: `G0-07` كان يقارن `COUNT(repair01_surfaces)` بـ
         `COUNT(gov_screen_cycle)` مساواةً، فصفٌّ جديدٌ يُسقطه؛ وتركُ الشاشةِ بلا
         صفٍّ يرفع `RP-01` و`RP-02` في سقّاطةِ ما قبلَ الالتزام — **بناءٌ ممنوعٌ
         في الاتّجاهَين**. ورفع المالكُ الجدارَ في `RPR-PATCH-03..08`: `G0-07`
         صار يقارن **الأساسَ بالأساس** (`ملفُّ أساسٍ مفقودٌ 0 · حيٌّ ≥ أساس`)،
         فالنموُّ مسموحٌ والأساسُ محفوظ. والمقيسُ اليومَ: أساس 664 · حيٌّ 670.
       ⛔ **واسمُ الإدارةِ من جسرِ المسمّياتِ لا مخترَعًا**: `G0-06` يشترط أن يكون
         كلُّ مسمّى إدارةٍ حيٍّ في `gov_screen_cycle` مُجسَّرًا في
         `repair01_dept_crosswalk` — واسمٌ جديدٌ يُسقطه. */
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
    $guard = repair01_w7_guard_of($ROOT, $s['route']);
    $W("INSERT INTO repair01_screen_registry
        (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
         lifecycle, lifecycle_rule, parent_screen_id, parent_rule, visibility_class, visibility_rule,
         on_disk, origin, guard_kind, guard_evidence, w2_why, src_ref)
        VALUES ('" . $esc($sid) . "','" . $esc($file) . "','$rt','W7_NEW_SURFACE_ROUTE',
                '" . $esc($s['owner']) . "','" . $esc($s['role']) . "','W7_REQUIREMENT_OWNER',
                'LIVE_UNREGISTERED','W7_GROWTH_OUTSIDE_STUDY_MATRIX','','','MENU_ITEM','NAV_ITEMS_ACTIVE',
                1,'W07','" . $esc($guard['kind']) . "','" . $esc($guard['evidence']) . "',
                '" . $esc($s['ar']) . " (" . $esc($file) . ")','RPR-W07 · الصيانة والنقل')
        ON DUPLICATE KEY UPDATE owner_code=VALUES(owner_code), owner_role=VALUES(owner_role),
          visibility_class=VALUES(visibility_class), guard_kind=VALUES(guard_kind),
          guard_evidence=VALUES(guard_evidence), origin='W07', on_disk=1");
    $newN++;
}
printf("  أسطحٌ مسجَّلةٌ بختمِ W07 %d · بنودُ قائمةٍ نشِطة %d · منحٌ %d · مسمّياتٌ مسجَّلة %d · بلا ملفٍّ %d%s\n\n",
    $newN, $navN, $permN, $labelN, count($missing), $missing ? ' ⇐ ' . implode('، ', $missing) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ② نطاقُ المرحلة — ٢٦ متطلَّبًا إلى مِرساتِها المُثبَتةِ قياسًا
   ═══════════════════════════════════════════════════════════════════════════ */
echo "② نطاقُ المرحلة ───────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w7_scope");
$ANCH = repair01_w7_anchors();
$anchored = 0; $notBuilt = 0; $unproven = array(); $ownerMismatch = array();

$rq = $conn->query("SELECT requirement_id, unit, group_name, surface, src_ref
                      FROM repair01_requirements WHERE stage_no = 7 ORDER BY unit, seq");
while ($rq && $q = $rq->fetch_assoc()) {
    $rid = $q['requirement_id'];
    $dept = preg_match('/^(\d{2})\s/u', $q['unit'], $mm) ? 'DEP-' . $mm[1] : '';
    if (!isset($ANCH[$rid])) {
        $W("INSERT INTO repair01_w7_scope
            (requirement_id,unit,group_name,surface,anchor_screen_id,anchor_route,anchor_probe,
             owner_measured,owner_expected,owner_verdict,map_rule,map_why,wave_stage,src_ref)
            VALUES ('" . $esc($rid) . "','" . $esc($q['unit']) . "','" . $esc($q['group_name']) . "',
                    '" . $esc($q['surface']) . "','','','','','" . $esc($dept) . "','UNKNOWN',
                    'W7_DECISION:W7-D-01','متطلب خارج خريطة المراسي — يحسم بقرار المالك','',
                    '" . $esc($q['src_ref']) . "')");
        $notBuilt++; continue;
    }
    $a = $ANCH[$rid];
    $p = repair01_w7_prove_anchor($conn, $ROOT, $a);
    $ownerV = ($p['sid'] === '') ? 'NOT_BUILT' : (($p['owner'] === $dept) ? 'MATCH' : 'MISMATCH');
    if ($ownerV === 'MISMATCH') { $ownerMismatch[] = $rid . '⇐' . ($p['owner'] !== '' ? $p['owner'] : 'بلا مالك'); }
    if ($p['verdict'] === 'ANCHORED') { $anchored++; }
    elseif ($p['verdict'] === 'NOT_BUILT') { $notBuilt++; }
    else { $unproven[] = $rid . ' (' . $p['verdict'] . ')'; }

    $W("INSERT INTO repair01_w7_scope
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
   ③ السايدبار — الخطواتُ السبعُ بترتيبها على أسطحِ النطاق
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③ السايدبارُ — سبعُ خطواتٍ على أسطحِ النطاق ────────────────────\n";
$codes = repair01_w7_scope_codes($conn);
$codesSql = "'" . implode("','", array_map($esc, $codes)) . "'";
$tabMap = repair01_w3_entity_tab_map($ROOT);
$W("DELETE FROM repair01_w7_sidebar");

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
    if ($x['visibility_class'] === 'MENU_ITEM' && $navN2 > 0)      { $s1v = 'KEEP_APPROVED_MENU'; $s1r = 'REGISTRY_MENU_ITEM'; }
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
    else { $s4src = ''; $s4no = 0; $s4v = 'NO_ORDER_SOURCE'; $s4r = 'W7_DECISION:W7-D-02'; }

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
            $renderedIn = $childRoles ? implode(',', array_map('intval', $childRoles)) : '-1';
            $W("INSERT INTO gov_nav_hidden_log (role_id, nav_id, route, label_ar, group_before, sort_before, doc_code, reachable)
                SELECT role_id, id, route, label_ar, group_id, sort_order, 'RPR-W07',
                       CASE WHEN role_id IN ($renderedIn) THEN 'TAB_IN_PARENT' ELSE 'NOT_RENDERED' END
                  FROM nav_items WHERE ($navPred) AND active = 1
                ON DUPLICATE KEY UPDATE doc_code = 'RPR-W07',
                       reachable = CASE WHEN nav_items.role_id IN ($renderedIn) THEN 'TAB_IN_PARENT' ELSE 'NOT_RENDERED' END");
            $W("UPDATE nav_items SET active = 0 WHERE ($navPred) AND active = 1");
            $W("UPDATE repair01_screen_registry SET visibility_class = 'TAB_CHILD',
                    visibility_rule = 'W7_TAB_PROVEN_BY_ENTITY_TABS' WHERE screen_id = '" . $esc($sid) . "'");
            $s5v = 'DEMOTED_TO_TAB'; $s5r = 'TAB_PROVEN_AND_REACHABLE';
            $s5why = 'تبويب «' . $tab['tab'] . '» في ' . $tab['parent'] . ' — وكل ادوار الابن في الاب';
            $s5Demoted++; $navN2 = 0;
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
    $W("INSERT INTO repair01_w7_sidebar
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
   ④ سجلُّ تصنيفِ السلامة — بذرةُ DEC-OPEN-12 ③ (بيانٌ لا شيفرة)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "④ قواعدُ تصنيفِ السلامة ──────────────────────────────────────\n";
$SYS = array(
    array('brakes',     'الفرامل',                       'safety_critical'),
    array('steering',   'التوجيه',                       'safety_critical'),
    array('control',    'أنظمة التحكم الحرجة',           'safety_critical'),
    array('lifting',    'الرفع وتثبيت الأحمال',          'safety_critical'),
    array('estop',      'الحماية والإيقاف الطارئ',       'safety_critical'),
    array('fire',       'الإطفاء ومنع الحريق',           'safety_critical'),
    array('structural', 'الهيكل الإنشائي والميكانيكي',   'safety_critical'),
    array('hydraulic',  'المجموعة الهيدروليكية',         'major'),
    array('engine',     'المحرك ومجموعة القدرة',         'major'),
    array('electrical', 'المنظومة الكهربائية',           'major'),
    array('tyres',      'الإطارات والجنوط',              'major'),
    array('cabin',      'الكابينة والتجهيزات',           'minor'),
    array('paint',      'الدهان والمظهر',                'minor'),
);
$companies = array();
$rc = $conn->query("SELECT DISTINCT company_id FROM equipments WHERE company_id > 0");
while ($rc && $x = $rc->fetch_row()) { $companies[] = (int) $x[0]; }
$srN = 0;
foreach ($companies as $cid) {
    foreach ($SYS as $sy) {
        $crit = ($sy[2] === 'safety_critical');
        $W("INSERT INTO mnt_safety_rule
            (company_id, equipment_type, system_key, system_ar, default_severity,
             requires_cert, requires_lockout, approver_kind, rule_ref, active, src_ref)
            VALUES ($cid,'','" . $esc($sy[0]) . "','" . $esc($sy[1]) . "','" . $esc($sy[2]) . "',
                    " . (($sy[2] === 'minor') ? 0 : 1) . "," . ($crit ? 1 : 0) . ",
                    '" . ($crit ? 'technical_authority' : 'technician') . "',
                    'W7_SAFETY_RULE_BASE','1','RPR-W07 §٤ · DEC-OPEN-12 قرار المالك')
            ON DUPLICATE KEY UPDATE system_ar=VALUES(system_ar), default_severity=VALUES(default_severity),
              requires_cert=VALUES(requires_cert), requires_lockout=VALUES(requires_lockout),
              approver_kind=VALUES(approver_kind), rule_ref=VALUES(rule_ref)");
        $srN++;
    }
}
printf("  كيانات %d × أنظمة %d = صفوفُ قاعدةٍ مبذورة %d · حرِجٌ للسلامة %d\n\n",
    count($companies), count($SYS), (int) $one("SELECT COUNT(*) FROM mnt_safety_rule"),
    (int) $one("SELECT COUNT(*) FROM mnt_safety_rule WHERE default_severity = 'safety_critical'"));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ قاموسُ عرضِ الرموز — الرمزُ يُسجَّل قبل أن يُعرَض (W6-09)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑤ قاموسُ عرضِ رموزِ المرحلة ──────────────────────────────────\n";
$DICT = array(
    array('minor', 'عطل بسيط', 'بسيط'), array('major', 'عطل رئيسي', 'رئيسي'),
    array('safety_critical', 'حرج للسلامة', 'حرج للسلامة'),
    array('low', 'أثر منخفض', 'منخفض'), array('medium', 'أثر متوسط', 'متوسط'),
    array('high', 'أثر مرتفع', 'مرتفع'), array('critical', 'أثر حرج', 'حرج'),
    array('none', 'لا منع تشغيل', 'لا منع'), array('locked_out', 'ممنوع التشغيل', 'ممنوع'),
    array('released', 'رفع المنع', 'رفع المنع'),
    array('operational', 'تعمل', 'تعمل'),
    array('operational_restricted', 'تعمل بقيد', 'تعمل بقيد'),
    array('stopped', 'متوقفة', 'متوقفة'), array('prohibited', 'محظور تشغيلها', 'محظورة'),
    array('ready_after_approval', 'جاهزة بعد الاعتماد', 'جاهزة بالاعتماد'),
    array('draft', 'مسودة', 'مسودة'), array('submitted', 'مرفوع للاعتماد', 'مرفوع'),
    array('approved', 'معتمد', 'معتمد'), array('rejected', 'مردود', 'مردود'),
    array('expired', 'منتهي الصلاحية', 'منتهٍ'), array('superseded', 'أخلفته شهادة', 'أخلفته شهادة'),
    array('reopened', 'أعيد فتحه', 'أعيد فتحه'), array('cancelled', 'ملغى', 'ملغى'),
    array('pass', 'ناجح', 'ناجح'), array('conditional', 'ناجح بشرط', 'بشرط'), array('fail', 'مخفق', 'مخفق'),
    array('issued', 'صرف', 'صرف'), array('partially_issued', 'صرف جزئي', 'صرف جزئي'),
    array('pending', 'معلق', 'معلق'), array('matched', 'مطابق', 'مطابق'), array('variance', 'به فارق', 'به فارق'),
    array('normal', 'عادي', 'عادي'), array('urgent', 'عاجل', 'عاجل'),
    array('external_referral', 'إحالة خارجية', 'إحالة خارجية'),
    array('warranty_claim', 'مطالبة ضمان', 'مطالبة ضمان'),
    array('accepted', 'مقبولة', 'مقبولة'), array('partial', 'مقبولة جزئيا', 'جزئيا'),
    array('sent', 'أرسل', 'أرسل'), array('in_progress', 'قيد التنفيذ', 'قيد التنفيذ'),
    array('received', 'استلم', 'استلم'), array('closed', 'مقفل', 'مقفل'), array('open', 'مفتوح', 'مفتوح'),
    array('ok', 'سليم', 'سليم'), array('abnormal', 'غير طبيعي', 'غير طبيعي'),
    array('not_done', 'لم ينفذ', 'لم ينفذ'), array('na', 'لا ينطبق', 'لا ينطبق'),
    array('failed', 'أخفق', 'أخفق'), array('completed', 'اكتمل', 'اكتمل'), array('blocked', 'محجوب', 'محجوب'),
    array('planned', 'مخطط', 'مخطط'), array('in_transit', 'في الطريق', 'في الطريق'),
    array('arrived', 'وصل', 'وصل'), array('handed_over', 'سلم للتالية', 'سلم'),
    array('repeat_within_validity', 'تكرار خلال الصلاحية', 'تكرار'),
    array('high_cost', 'كلفة مرتفعة', 'كلفة مرتفعة'), array('manual', 'قرار يدوي', 'يدوي'),
    array('in_analysis', 'قيد التحليل', 'قيد التحليل'), array('root_found', 'حدد السبب الجذري', 'حدد السبب'),
    array('under_review', 'قيد المراجعة', 'قيد المراجعة'), array('settled', 'سويت', 'سويت'),
    array('carrier', 'الناقل', 'الناقل'), array('driver', 'السائق', 'السائق'),
    array('client', 'العميل', 'العميل'), array('company', 'الشركة', 'الشركة'),
    array('third_party', 'طرف ثالث', 'طرف ثالث'), array('undetermined', 'غير محدد', 'غير محدد'),
    array('insurance', 'التأمين', 'التأمين'), array('internal', 'داخلي', 'داخلي'),
    array('equipment', 'معدة', 'معدة'), array('category', 'فئة', 'فئة'),
);
$dictN = 0;
foreach ($DICT as $d) {
    if ($W("INSERT INTO repair01_w6_code_dict
        (raw_code, display_ar, display_short, code_family, allowed_context, why, src_ref)
        VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "','ENUM','SCREEN',
                'قيمة ENUM في جداول RPR-W07 — لاتينية في السجل لانها تقارن في CHECK وعربية في العرض',
                'RPR-W07 §٥ · قاموس عرض الرموز')
        ON DUPLICATE KEY UPDATE display_ar=VALUES(display_ar), display_short=VALUES(display_short)")) { $dictN++; }
}
printf("  مفرداتٌ مسجَّلةٌ %d · إجماليُّ القاموس %d\n\n", $dictN,
    (int) $one("SELECT COUNT(*) FROM repair01_w6_code_dict"));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ تصنيفُ الأوامرِ القائمة — أربعةُ محاورَ تُملأ من قواعدِها لا يدًا
   ═══════════════════════════════════════════════════════════════════════════
   ⚠ **والتصنيفُ لا يُخترع**: أمرٌ بلا نظامٍ مصابٍ مُعلَنٍ يبقى `minor` بقاعدةِ
     `W7_SAFETY_NO_SYSTEM_MATCH` **مكتوبةً في الصفّ** — فالغيابُ يُرى ويُصلَح،
     ولا يُستَر بتصنيفٍ مخترَع.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑥ تصنيفُ الأوامرِ القائمة ────────────────────────────────────\n";
MCS::setThresholdConnection($conn);
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
$gates = array();
$gateFor = function ($cid) use (&$gates, $conn) {
    $cid = (int) $cid;
    if (!isset($gates[$cid])) {
        $gates[$cid] = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($cid, 0, '', true));
    }
    return $gates[$cid];
};
/* جسرُ رمزِ العطلِ إلى النظامِ المصاب — من `failure_codes` الحيِّ لا من قائمةٍ هنا */
$fcSys = array();
$rf = $conn->query("SELECT id, name FROM failure_codes");
while ($rf && $x = $rf->fetch_assoc()) {
    $nm = (string) $x['name'];
    foreach ($SYS as $sy) {
        if ($sy[1] !== '' && mb_strpos($nm, mb_substr($sy[1], 0, 5)) !== false) { $fcSys[(int) $x['id']] = $sy[0]; break; }
    }
}
$clsN = 0; $critN = 0; $noSys = 0;
$ro = $conn->query("SELECT id, company_id, equipment_id, failure_code_id, downtime_hours, maint_type
                      FROM mnt_order WHERE is_deleted = 0");
while ($ro && $o = $ro->fetch_assoc()) {
    $g = $gateFor($o['company_id']);
    $sys = isset($fcSys[(int) $o['failure_code_id']]) ? $fcSys[(int) $o['failure_code_id']] : '';
    if ($sys === '') { $noSys++; }
    $cls = MCS::classifySafety($g, (int) $o['equipment_id'], $sys);
    $imp = MCS::computeOpsImpact($o['downtime_hours']);
    if ($imp['impact'] === null) { echo "  ⚠ لا عتبةَ أثرٍ تشغيليٍّ في السجلّ — التصنيفُ يتوقّف\n"; break; }
    if ($cls['severity'] === 'safety_critical') { $critN++; }
    $W("UPDATE mnt_order SET
            failure_kind = '" . $esc((string) $o['maint_type']) . "',
            safety_severity = '" . $esc($cls['severity']) . "',
            safety_rule_ref = '" . $esc($cls['rule_ref']) . "',
            safety_system_key = '" . $esc($sys) . "',
            ops_impact = '" . $esc($imp['impact']) . "',
            ops_impact_rule = '" . $esc($imp['rule']) . "',
            w7_state_rule = 'W7_ORDER_CLASSIFIED_BACKFILL'
          WHERE id = " . (int) $o['id']);
    $clsN++;
}
printf("  أوامرُ صُنِّفت %d · حرِجٌ للسلامة %d · بلا نظامٍ مصابٍ مُعلَن %d\n\n", $clsN, $critN, $noSys);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ المؤشّراتُ المشتقّة — تُعاد بناءً في كلِّ تشغيلٍ ولا تُدخَل
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑦ المؤشّراتُ المشتقّة ────────────────────────────────────────\n";
$W("DELETE FROM mnt_kpi_period");
$kN = 0;
/* ⛔ **والأصلُ المعدومُ لا يُشتقُّ له مؤشِّر**: `mnt_order.equipment_id` يشير إلى
     أصولٍ لم تعد في `equipments` (‏حذفٌ صلبٌ ماضٍ)، واشتقاقُ سطرِ جاهزيّةٍ لأصلٍ
     لا وجودَ له **يُنمّي المقامَ برقمٍ لا مرجعَ له**. والضمُّ يُقصيها،
     **وعددُها يُعلَن في `W7-D-07` لا يُسكَت عنه** (‏«لا سقفَ صامتًا»). */
$rk = $conn->query("
    SELECT o.company_id, DATE_FORMAT(COALESCE(o.work_end, o.created_at), '%Y-%m') period,
           o.equipment_id,
           COUNT(*) orders, SUM(COALESCE(o.downtime_hours,0)) downtime,
           SUM(CASE WHEN o.plan_id IS NOT NULL THEN 1 ELSE 0 END) pm_done,
           SUM(COALESCE(o.total_cost,0)) cost
      FROM mnt_order o
      JOIN equipments e ON e.id = o.equipment_id
     WHERE o.is_deleted = 0 AND o.equipment_id > 0
     GROUP BY o.company_id, period, o.equipment_id");
while ($rk && $k = $rk->fetch_assoc()) {
    $eq = (int) $k['equipment_id']; $per = (string) $k['period']; $cid = (int) $k['company_id'];
    $run = (float) $one("SELECT COALESCE(SUM(executed_hours),0) FROM asset_readiness
                          WHERE company_id = $cid AND equipment_id = $eq AND period = '" . $esc($per) . "'");
    $rd  = (float) $one("SELECT COALESCE(AVG(readiness_pct),0) FROM asset_readiness
                          WHERE company_id = $cid AND equipment_id = $eq AND period = '" . $esc($per) . "'");
    $due = (int) $one("SELECT COUNT(*) FROM mnt_plan
                        WHERE company_id = $cid AND equipment_id = $eq
                          AND next_due_date IS NOT NULL AND DATE_FORMAT(next_due_date,'%Y-%m') <= '" . $esc($per) . "'");
    $f = MCS::kpiFormula((int) $k['orders'], (float) $k['downtime'], (int) $k['pm_done'], $due, (float) $k['cost'], $run);
    $W("INSERT INTO mnt_kpi_period
        (company_id, period, scope_kind, scope_ref, breakdowns, mtbf_hours, mttr_hours, readiness_pct,
         pm_done, pm_due, pm_compliance_pct, cost_per_hour, derivation_rule, derived_from, derived_at)
        VALUES ($cid,'" . $esc($per) . "','equipment',$eq," . (int) $k['orders'] . ",
                " . $f['mtbf'] . "," . $f['mttr'] . "," . round($rd, 2) . ",
                " . (int) $k['pm_done'] . ",$due," . $f['pm_compliance'] . "," . $f['cost_per_hour'] . ",
                'W7_KPI_FROM_ORDERS_AND_READINESS',
                'mnt_order · mnt_plan · asset_readiness · MaintenanceCycleService::kpiFormula',NOW())
        ON DUPLICATE KEY UPDATE mtbf_hours=VALUES(mtbf_hours), mttr_hours=VALUES(mttr_hours),
          readiness_pct=VALUES(readiness_pct), pm_compliance_pct=VALUES(pm_compliance_pct),
          cost_per_hour=VALUES(cost_per_hour), derived_at=NOW()");
    $kN++;
}
$W("DELETE FROM trp_kpi_period");
$tN = 0;
$rt2 = $conn->query("
    SELECT o.company_id, DATE_FORMAT(COALESCE(o.arrival_datetime, o.planned_date, o.request_date), '%Y-%m') period,
           COUNT(*) n, SUM(CASE WHEN o.stage = 'closed' THEN 1 ELSE 0 END) closed,
           AVG(CASE WHEN o.departure_datetime IS NOT NULL AND o.arrival_datetime IS NOT NULL
                    THEN TIMESTAMPDIFF(HOUR, o.departure_datetime, o.arrival_datetime) END) hrs,
           SUM(COALESCE(o.actual_cost_usd,0)) cost, SUM(COALESCE(o.distance_km,0)) km
      FROM transfer_orders o WHERE o.is_deleted = 0
     GROUP BY o.company_id, period HAVING period IS NOT NULL");
while ($rt2 && $t = $rt2->fetch_assoc()) {
    $cid = (int) $t['company_id']; $per = (string) $t['period'];
    $inc = (int) $one("SELECT COUNT(*) FROM trp_damage_claim c
                         JOIN transfer_orders o ON o.id = c.order_id
                        WHERE c.company_id = $cid
                          AND DATE_FORMAT(COALESCE(o.arrival_datetime, o.planned_date, o.request_date),'%Y-%m') = '" . $esc($per) . "'");
    $km = (float) $t['km']; $cost = (float) $t['cost'];
    $onTime = (float) $one("SELECT ROUND(100 * AVG(CASE WHEN o.arrival_datetime IS NULL THEN NULL
                                    WHEN DATE(o.arrival_datetime) <= o.planned_date THEN 1 ELSE 0 END), 2)
                              FROM transfer_orders o
                             WHERE o.company_id = $cid AND o.is_deleted = 0
                               AND DATE_FORMAT(COALESCE(o.arrival_datetime, o.planned_date, o.request_date),'%Y-%m') = '" . $esc($per) . "'");
    $byC = (string) $one("SELECT GROUP_CONCAT(CONCAT(carrier_type,' ',n) SEPARATOR ' · ') FROM (
                             SELECT carrier_type, COUNT(*) n FROM transfer_orders
                              WHERE company_id = $cid AND is_deleted = 0
                                AND DATE_FORMAT(COALESCE(arrival_datetime, planned_date, request_date),'%Y-%m') = '" . $esc($per) . "'
                              GROUP BY carrier_type) z");
    $W("INSERT INTO trp_kpi_period
        (company_id, period, orders_total, orders_closed, avg_trip_hours, on_time_pct, incidents,
         total_cost, cost_per_km, by_carrier, derivation_rule, derived_from, derived_at)
        VALUES ($cid,'" . $esc($per) . "'," . (int) $t['n'] . "," . (int) $t['closed'] . ",
                " . round((float) $t['hrs'], 2) . "," . round($onTime, 2) . ",$inc,
                " . round($cost, 2) . "," . ($km > 0 ? round($cost / $km, 4) : 0) . ",
                '" . $esc($byC !== '' ? $byC : 'لا توزيع مقيس') . "',
                'W7_TRP_KPI_FROM_ORDERS_AND_CLAIMS',
                'transfer_orders · transfer_delivery_docs · transfer_cost_lines · trp_damage_claim',NOW())
        ON DUPLICATE KEY UPDATE orders_total=VALUES(orders_total), orders_closed=VALUES(orders_closed),
          avg_trip_hours=VALUES(avg_trip_hours), on_time_pct=VALUES(on_time_pct),
          incidents=VALUES(incidents), total_cost=VALUES(total_cost), cost_per_km=VALUES(cost_per_km),
          by_carrier=VALUES(by_carrier), derived_at=NOW()");
    $tN++;
}
printf("  أسطرُ مؤشّراتِ الصيانة %d · أسطرُ تقريرِ الترحيل %d\n\n", $kN, $tN);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ عقودُ الأثرِ — لكلِّ حدثٍ يصدر من النطاق
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑧ عقودُ الأثر ─────────────────────────────────────────────────\n";
$narr = repair01_w7_contract_narrative();
$W("DELETE FROM repair01_events WHERE contract_stage = 'W07' AND wave = 'W07'");
$ents = "'" . implode("','", array_map($esc, repair01_w7_entity_types())) . "'";
$liveKeys = array();
$r = $conn->query("SELECT DISTINCT event_key, entity_type FROM ems_business_events WHERE entity_type IN ($ents)");
while ($r && $x = $r->fetch_assoc()) { $liveKeys[$x['event_key']] = $x['entity_type']; }
$liveN = count($liveKeys);
foreach (repair01_w7_stage_events() as $ek) { if (!isset($liveKeys[$ek])) { $liveKeys[$ek] = 'mnt_order'; } }

$written = 0; $missingC = array(); $inherited = 0;
foreach ($liveKeys as $ek => $ent) {
    /* ⚠ **عقدٌ واحدٌ لكلِّ حدثٍ لا عقدٌ لكلِّ مرحلة** — المرحلةُ ترث ولا تكرّر.
       والفحصُ **قبلَ** فحصِ السرد: `expense.maintenance.recorded` حدثٌ حيٌّ على
       `mnt_order` وعقدُه مكتوبٌ في W03 بمالكِه المشترَك (‏14 الصيانة / 05 المالية).
       وترتيبُ الفحصَين معكوسًا كان يعُدُّه «بلا عقد» وهو موثَّقٌ سلفًا. */
    $prior = (int) $one("SELECT COUNT(*) FROM repair01_events
                          WHERE event_code = '" . $esc($ek) . "' AND contract_status = 'RECORDED'
                            AND contract_stage <> '' AND contract_stage <> 'W07'");
    if ($prior > 0) { $inherited++; continue; }
    if (!isset($narr[$ek])) { $missingC[] = $ek; continue; }
    $c7 = $narr[$ek];
    $m = repair01_w3_measure_consumers($conn, $ek);
    $consumers = $m['consumers']; $effects = $m['effects']; $retry = $m['retry'];
    if (!$consumers) {
        $consumers = $c7['consumers']; $effects = $c7['effects'];
        $retry = 'بلا اشتراك مسجل — الحدث لم يطلق بعد';
    }
    $W("INSERT INTO repair01_events
        (event_code,name,wave,source_unit,source_screen,idempotency_key,consumers,effect_type,retry_policy,src_ref,
         trigger_rule,min_payload,consumer_list,consumer_effect,preconditions,failure_policy,compensation,
         contract_status,contract_rule,contract_stage)
        VALUES ('" . $esc($ek) . "','" . $esc($c7['name']) . "','W07','" . $esc($c7['unit']) . "',
                '" . $esc($c7['screen']) . "','" . $esc($c7['idem']) . "','" . $esc(implode(' · ', $consumers)) . "',
                '" . $esc($c7['effect']) . "','" . $esc($retry) . "',
                '" . $esc('قياسٌ حيّ: ' . $ent . ' › ' . $ek . ' + ' . $c7['src']) . "',
                '" . $esc($c7['trigger']) . "','" . $esc($c7['payload']) . "','" . $esc(implode("\n", $consumers)) . "',
                '" . $esc(implode("\n", $effects)) . "','" . $esc($c7['pre']) . "','" . $esc($c7['fail']) . "',
                '" . $esc($c7['comp']) . "','RECORDED','LIVE_EVENT_KEY_MEASURED','W07')");
    $written++;
}
printf("  أحداثٌ حيّةٌ مقيسةٌ في النطاق %d · أحداثُ النطاق %d · عقدٌ مكتوبٌ هنا %d · موروثٌ %d · بلا عقدٍ %d%s\n\n",
    $liveN, count($liveKeys), $written, $inherited, count($missingC),
    $missingC ? ' ⇐ ' . implode('، ', $missingC) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑨ آلاتُ الحالة — لكلِّ كيانٍ رئيسيّ · والممنوعُ صراحةً بسببِه
   ═══════════════════════════════════════════════════════════════════════════
   ⛔ **الكيانُ بلا آلةِ حالةٍ لا يُغلَق** (§٧). و«الممنوعُ صراحةً» **صفٌّ يُكتب**
      لا سكوتٌ عمّا لم يُذكَر — والسكوتُ لا يُميَّز عن السهو.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑨ آلاتُ الحالة ───────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w7_states");
/* entity | from | to | allowed | owner | pre | doc | gate | reopen | correct | forbid_reason */
$STM = array(
  /* ── أمرُ العمل ─────────────────────────────────────────────────────── */
  array('mnt_order', 'open', 'closed', 1, 'مسؤول الصيانة',
        'شهادة اعادة خدمة معتمدة على الامر — والاقفال اثرها لا فعل مستقل',
        'شهادة اعادة الخدمة', 'اعتماد الشهادة من مخول فني أو مدير الصيانة',
        'اعادة الفتح بامر جديد على العقدة نفسها وواقعة تكرار — لا يعاد فتح المقفل',
        'التصحيح واقعة جديدة: امر جديد او شهادة خلف — ولا تعديل رجعي', ''),
  array('mnt_order', 'open', 'cancelled', 1, 'مسؤول الصيانة',
        'سبب الغاء مكتوب وعدم وجود صرف قطع منفذ على الامر',
        'محضر الغاء امر عمل', 'اعتماد مدير الصيانة',
        'الملغى لا يعاد فتحه — والحاجة الجديدة امر جديد',
        'الالغاء واقعة تكتب ولا تمحو ما قيد عليه', ''),
  array('mnt_order', 'open', 'closed_without_cert', 0, '', '', '', '', '', '',
        'الشهادة وحدها تعيد المعدة وتقفل الامر (MNT-14) — واقفال بلا شهادة يعيد اصلا لم يشهد له احد'),
  array('mnt_order', 'closed', 'open', 0, '', '', '', '', '', '',
        'اعادة فتح امر مقفل تفتح بابا لصرف قطع على تكلفة اغلقت — والعلاج امر جديد بواقعة تكرار'),
  array('mnt_order', 'safety_critical', 'minor', 0, '', '', '', '', '', '',
        'تخفيض تصنيف السلامة ينقض حكم مخول فني بلا مراجعة (DEC-OPEN-12 ④) — والتصعيد وحده مسموح'),
  /* ── شهادةُ إعادةِ الخدمة ──────────────────────────────────────────── */
  array('mnt_return_cert', 'draft', 'submitted', 1, 'فني الصيانة المخول',
        'امر العمل منجز فنيا واختبار موثق وتاريخ انجاز مكتوب',
        'شهادة اعادة الخدمة', 'رفع الفني — والاعتماد مرحلة تالية',
        'المسودة تعدل قبل الرفع — وبعده تخلف بشهادة جديدة',
        'خطا في المسودة يصحح قبل الرفع؛ وبعده يصحح بشهادة خلف', ''),
  array('mnt_return_cert', 'submitted', 'approved', 1, 'مدير الصيانة أو المخول الفني',
        'المعتمد غير المنشئ · نتيجة الاختبار غير مخفقة · والحرج للسلامة يعتمده مخول فني',
        'شهادة اعادة الخدمة المعتمدة', 'توقيع مدير الصيانة أو المخول الفني',
        'المعتمدة لا يعاد فتحها — والتدهور اللاحق امر عمل جديد',
        'شهادة خلف تصدر والقديمة توسم superseded ولا تحذف', ''),
  array('mnt_return_cert', 'submitted', 'rejected', 1, 'مدير الصيانة',
        'سبب رد مكتوب في الصف', 'محضر رد شهادة', 'قرار مدير الصيانة',
        'المردودة تفتح شهادة جديدة بعد استيفاء الملحوظة',
        'الرد يكتب بسببه ولا يمحى — والتصحيح شهادة جديدة', ''),
  array('mnt_return_cert', 'submitted', 'approved_by_creator', 0, '', '', '', '', '', '',
        'من اصلح لا يشهد على نفسه — وفصل الواجبات ينفذ برد SOD_SELF_APPROVAL لا يعلن'),
  array('mnt_return_cert', 'draft', 'approved', 0, '', '', '', '', '', '',
        'القفز فوق الرفع يلغي مرحلة المراجعة — والترتيب هو الذي يمنع القفز'),
  array('mnt_return_cert', 'approved', 'draft', 0, '', '', '', '', '', '',
        'المستند الرسمي الصادر لا يعود مسودة — والتصحيح شهادة خلف'),
  /* ── واقعةُ إعادةِ الإصلاح ─────────────────────────────────────────── */
  array('mnt_repeat_repair', 'open', 'in_analysis', 1, 'مهندس الصيانة',
        'واقعة تكرار قائمة بعقدتها وتاريخها', 'ملف تحليل السبب الجذري',
        'تكليف مهندس الصيانة', 'المغلق يعاد فتحه بتكرار جديد لا بتعديل',
        'التحليل يضاف ولا يستبدل — والتاريخ لا يمحى', ''),
  array('mnt_repeat_repair', 'in_analysis', 'root_found', 1, 'مهندس الصيانة',
        'سبب جذري مكتوب', 'تقرير السبب الجذري', 'مراجعة مدير الصيانة',
        'المغلق يعاد فتحه بتكرار جديد', 'السبب يعدل بتقرير مراجع لا بمحو', ''),
  array('mnt_repeat_repair', 'root_found', 'closed', 1, 'مدير الصيانة',
        'سبب جذري وقرار مكتوبان معا (chk_mrr_close)', 'قرار اغلاق التحليل',
        'اعتماد مدير الصيانة', 'التكرار الجديد واقعة جديدة',
        'قرار الاغلاق يكتب ولا يمحى', ''),
  array('mnt_repeat_repair', 'open', 'closed', 0, '', '', '', '', '', '',
        'اغلاق بلا تحليل يحول الواقعة الى رقم بلا معنى — والقفز فوق التحليل يفرغ MNT-15'),
  /* ── طلبُ صرفِ القطع ───────────────────────────────────────────────── */
  array('mnt_part_request', 'submitted', 'issued', 1, 'أمين المخزن',
        'رقم سند صرف من المخازن مكتوب (chk_mpr_issue) وامر العمل غير مقفل',
        'سند صرف مخزني', 'اعتماد امين المخزن',
        'الطلب المصروف لا يعاد فتحه — والحاجة الجديدة طلب جديد',
        'فارق الاستلام يوسم variance ولا يعدل السند', ''),
  array('mnt_part_request', 'submitted', 'rejected', 1, 'أمين المخزن',
        'سبب رد مكتوب', 'محضر رد طلب صرف', 'قرار امين المخزن',
        'المردود يفتح طلبا جديدا', 'الرد يكتب بسببه', ''),
  array('mnt_part_request', 'closed_order', 'issued', 0, '', '', '', '', '', '',
        'لا صرف لامر مقفل (MNT-10 نصا) — والصرف عليه يحمل تكلفة على حساب اغلق'),
  /* ── أمرُ الترحيل ──────────────────────────────────────────────────── */
  array('transfer_orders', 'planned', 'ready', 1, 'مسؤول النقل والترحيل',
        'تصاريح غير منتهية بسماح السجل وبنود تجهيز مكتملة',
        'اذن مغادرة', 'بوابة authorizeDeparture — Fail-Closed',
        'الاذن الملغى حدث ايقاف مسجل لا محو',
        'التصحيح بند تجهيز جديد او تصريح جديد — لا تعديل رجعي', ''),
  array('transfer_orders', 'ready', 'in_transit', 1, 'السائق أو المرافق',
        'حدث مغادرة مسجل — والحالة تتقدم بالاحداث لا بالتعديل اليدوي',
        'سجل احداث الرحلة', 'حدث مسجل في transfer_events',
        'العودة الى ready حدث ايقاف مسجل', 'الحدث يضاف ولا يعدل', ''),
  array('transfer_orders', 'in_transit', 'arrived', 1, 'مستلم الحمولة',
        'محضر استلام بمرجعه وقراءة عداد الزامية (METER_REQUIRED)',
        'محضر الاستلام', 'قيد المحضر في transfer_delivery_docs',
        'المحضر الخاطئ يصحح بمحضر جديد', 'قراءة العداد تصحح بقراءة جديدة في سلسلتها', ''),
  array('transfer_orders', 'arrived', 'closed', 1, 'معتمد الإقفال',
        'اقفال معتمد وقراءة عداد مرحلة ومعتمد غير المنشئ',
        'محضر اقفال امر الترحيل', 'اعتماد الاقفال approveClosure',
        'اعادة الفتح بسبب مكتوب في trp_closure.reopen_reason',
        'التصحيح بند تكلفة جديد او اعادة فتح بسببها', ''),
  array('transfer_orders', 'planned', 'in_transit', 0, '', '', '', '', '', '',
        'القفز فوق الاذن يخرج حمولة بتصريح منته او تجهيز ناقص (TRP-05 · TRP-06)'),
  array('transfer_orders', 'in_transit', 'closed', 0, '', '', '', '', '', '',
        'لا اقفال قبل محضر الاستلام (TRP-12 نصا) — والاقفال بلا محضر يقفل امرا لم يسلم'),
  array('transfer_orders', 'closed', 'in_transit', 0, '', '', '', '', '', '',
        'المرحلة لا تتراجع (STAGE_BACKWARD_FORBIDDEN) — والحاجة الجديدة امر جديد'),
  /* ── مرحلةُ الرحلة ─────────────────────────────────────────────────── */
  array('trp_trip_leg', 'planned', 'in_transit', 1, 'سائق المرحلة',
        'المرحلة السابقة مسلمة (PREVIOUS_LEG_NOT_HANDED_OVER) ووقت بدء مكتوب',
        'سجل مراحل الرحلة', 'قيد البدء في المرحلة',
        'المرحلة الملغاة تفتح مرحلة بتسلسل جديد', 'وقت البدء يصحح بقيد مراجع', ''),
  array('trp_trip_leg', 'in_transit', 'handed_over', 1, 'سائق المرحلة',
        'وقت انتهاء لا يسبق وقت البدء (chk_ttl_span)', 'محضر تسليم مرحلة',
        'قيد الانتهاء والتسليم', 'المرحلة المسلمة لا يعاد فتحها',
        'الفارق يوثق في حدث رحلة لا بتعديل الاوقات', ''),
  array('trp_trip_leg', 'planned', 'handed_over', 0, '', '', '', '', '', '',
        'تسليم مرحلة لم تبدا يعني حمولة تحركت بلا سجل — والقفز يخفي المسافة والوقت'),
  /* ── إقفالُ أمرِ الترحيل ───────────────────────────────────────────── */
  array('trp_closure', 'draft', 'submitted', 1, 'مسؤول النقل والترحيل',
        'محضر استلام قائم (chk_tcl_doc) والتكلفة مشتقة من البنود',
        'محضر اقفال امر الترحيل', 'رفع مسؤول النقل',
        'المسودة تعدل قبل الرفع', 'التكلفة تصحح ببند جديد ويعاد الاشتقاق', ''),
  array('trp_closure', 'submitted', 'approved', 1, 'معتمد الإقفال',
        'المعتمد غير المنشئ (SOD_SELF_APPROVAL) وقراءة العداد مرحلة (METER_NOT_POSTED)',
        'محضر اقفال معتمد', 'اعتماد approveClosure',
        'اعادة الفتح بسبب مكتوب في reopen_reason',
        'بند تكلفة جديد بعد الاعتماد يوجب اعادة فتح لا تعديلا', ''),
  array('trp_closure', 'approved', 'reopened', 1, 'معتمد الإقفال',
        'سبب اعادة فتح مكتوب (chk_tcl_reop)', 'محضر اعادة فتح اقفال',
        'اعتماد معتمد الاقفال', 'اعادة الفتح واقعة تكتب ولا تمحو الاعتماد',
        'التصحيح بعد اعادة الفتح يمر بالاعتماد ثانية', ''),
  array('trp_closure', 'draft', 'approved', 0, '', '', '', '', '', '',
        'القفز فوق الرفع يلغي مرحلة المراجعة ويجعل المنشئ معتمدا فعليا'),
  /* ── مطالبةُ التلف ─────────────────────────────────────────────────── */
  array('trp_damage_claim', 'submitted', 'under_review', 1, 'مسؤول النقل والترحيل',
        'مرجع واقعة ووصف تلف مكتوبان (chk_tdc_evid)', 'ملف مطالبة تلف',
        'احالة للمراجعة', 'المغلقة تفتح مطالبة جديدة على واقعة جديدة',
        'قيمة المطالبة تعدل قبل التسوية وتوثق بعدها', ''),
  array('trp_damage_claim', 'under_review', 'settled', 1, 'معتمد التسوية',
        'قرار تسوية مكتوب ومعتمد مسجل (chk_tdc_set) وقاعدة متحمل من العقد',
        'محضر تسوية مطالبة', 'اعتماد معتمد التسوية',
        'المسواة لا يعاد فتحها — والخلاف مطالبة جديدة',
        'قيمة التسوية تكتب ولا تمحى', ''),
  array('trp_damage_claim', 'submitted', 'settled', 0, '', '', '', '', '', '',
        'تسوية بلا مراجعة تجعل قيمة الذمة قرار شخص واحد — والمراجعة هي الضابط'),
);
$stN = 0; $stForbid = 0;
foreach ($STM as $s) {
    if ((int) $s[3] === 0) { $stForbid++; }
    $W("INSERT INTO repair01_w7_states
        (entity, from_state, to_state, allowed, owner_role, `precondition`, official_doc,
         approval_gate, reopen_rule, correct_rule, forbid_reason, src_ref)
        VALUES ('" . $esc($s[0]) . "','" . $esc($s[1]) . "','" . $esc($s[2]) . "'," . (int) $s[3] . ",
                '" . $esc($s[4]) . "','" . $esc($s[5]) . "','" . $esc($s[6]) . "','" . $esc($s[7]) . "',
                '" . $esc($s[8]) . "','" . $esc($s[9]) . "','" . $esc($s[10]) . "',
                'RPR-W07 §٧ · W07_STATE_MACHINES.md')
        ON DUPLICATE KEY UPDATE allowed=VALUES(allowed), owner_role=VALUES(owner_role),
          `precondition`=VALUES(`precondition`), forbid_reason=VALUES(forbid_reason)");
    $stN++;
}
printf("  انتقالاتٌ مسجَّلة %d · منها ممنوعٌ صراحةً %d · كياناتٌ %d\n\n", $stN, $stForbid,
    (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w7_states"));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑩ فصلُ الواجبات — ستّةُ أدوارٍ وتركيبةٌ ممنوعةٌ صراحةً ومنفَّذةٌ برمزِ ردّ
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑩ فصلُ الواجبات ─────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w7_sod");
$SOD = array(
  array('W7_RETURN_TO_SERVICE', 'اعادة المعدة للخدمة بشهادة معتمدة',
        'فني الصيانة المنفذ', 'مشرف الصيانة الفني', 'مدير الصيانة أو المخول الفني',
        'فني الصيانة المنفذ', 'مدير الصيانة',
        'المنفذ لا يكون المعتمد · والمشغل لا يكون معتمد الحرج للسلامة',
        'SOD_SELF_APPROVAL · SAFETY_CRITICAL_NEEDS_TECHNICAL_AUTHORITY',
        'DEC-OPEN-12', 'مشرف الصيانة الفني',
        'المعدات داخل الكيان القانوني للمستخدم', 'التفويض بسند مكتوب لا بعرف'),
  array('W7_SAFETY_ESCALATION', 'تصعيد تصنيف السلامة',
        'الفني أو مهندس الصيانة أو مسؤول السلامة', 'مهندس الصيانة', 'مسؤول السلامة',
        'الفني المنفذ', 'مدير الصيانة',
        'لا احد يخفض تصنيفا صعده غيره · والتخفيض ممنوع مطلقا حتى تتم المراجعة',
        'SEVERITY_DOWNGRADE_FORBIDDEN',
        'DEC-OPEN-12', 'مسؤول السلامة',
        'اوامر العمل داخل الكيان', 'التصعيد بسبب مكتوب وفاعل معروف'),
  array('W7_PART_ISSUE', 'صرف قطع الغيار لامر عمل',
        'فني الصيانة', 'مشرف الصيانة', 'مدير الصيانة',
        'امين المخزن', 'امين المخزن',
        'الطالب لا يصرف لنفسه · ولا صرف لامر مقفل',
        'ORDER_CLOSED_NO_ISSUE',
        'MNT-10', 'مشرف المخزن',
        'اوامر العمل غير المقفلة داخل الكيان', 'الصرف بسند وبمستلم عهدة معروف'),
  array('W7_TRP_DEPARTURE', 'الاذن بمغادرة حمولة الترحيل',
        'مسؤول النقل', 'مسؤول السلامة أو مراقب التصاريح', 'مسؤول النقل والترحيل',
        'السائق أو المرافق', 'مسؤول النقل والترحيل',
        'من جهز الحمولة لا يجيز مغادرتها بتصريح منته',
        'PERMIT_EXPIRED · HANDOVER_INCOMPLETE · HANDOVER_NOT_STARTED',
        'TRP-05', 'مشرف الحركة',
        'اوامر الترحيل داخل الكيان', 'الاذن واقعة بفاعلها ووقتها'),
  array('W7_TRP_CLOSURE', 'اقفال امر الترحيل واعتماده',
        'مسؤول النقل والترحيل', 'مراجع التكلفة', 'معتمد الاقفال',
        'مسؤول النقل والترحيل', 'معتمد الاقفال',
        'من انشا الاقفال لا يعتمده · ولا اعتماد بلا ترحيل قراءة العداد',
        'SOD_SELF_APPROVAL · METER_NOT_POSTED · NO_CLOSURE_WITHOUT_DELIVERY_DOC',
        'TRP-12', 'مدير النقل',
        'اوامر الترحيل ذات محضر استلام', 'الاعتماد بسند صلاحية مكتوب'),
  array('W7_TRP_DAMAGE_CLAIM', 'تسوية مطالبة تلف أو حادث',
        'مسؤول النقل والترحيل', 'مراجع المطالبات', 'معتمد التسوية',
        'مسؤول النقل والترحيل', 'المالية',
        'من فتح المطالبة لا يقرر تسويتها · ولا تحديد متحمل بلا قاعدة عقد',
        'LIABLE_WITHOUT_CONTRACT_RULE · CLAIM_WITHOUT_INCIDENT_EVIDENCE',
        'TRP-10', 'مدير النقل',
        'المطالبات على اوامر داخل الكيان', 'التسوية بقرار مكتوب ومعتمد مسجل'),
);
$sodN = 0;
foreach ($SOD as $d) {
    $W("INSERT INTO repair01_w7_sod
        (process_key, process_name, initiator_role, reviewer_role, approver_role, executor_role,
         closer_role, forbidden_combo, enforced_by, authority_rule_id, deputy_role, scope_rule,
         delegation, effective_date, src_ref)
        VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "','" . $esc($d[3]) . "',
                '" . $esc($d[4]) . "','" . $esc($d[5]) . "','" . $esc($d[6]) . "','" . $esc($d[7]) . "',
                '" . $esc($d[8]) . "','" . $esc($d[9]) . "','" . $esc($d[10]) . "','" . $esc($d[11]) . "',
                '" . $esc($d[12]) . "', CURDATE(), 'RPR-W07 §٧ · W07_SOD.md')
        ON DUPLICATE KEY UPDATE forbidden_combo=VALUES(forbidden_combo), enforced_by=VALUES(enforced_by),
          approver_role=VALUES(approver_role), scope_rule=VALUES(scope_rule)");
    $sodN++;
}
printf("  عملياتٌ حرِجةٌ مسجَّلة %d · كلُّها بتركيبةٍ ممنوعةٍ ورمزِ ردٍّ ينفّذها\n\n", $sodN);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑪ قراراتُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑪ قراراتُ المرحلة ────────────────────────────────────────────\n";
$noOrder  = (int) $one("SELECT COUNT(*) FROM repair01_w7_sidebar WHERE s4_verdict = 'NO_ORDER_SOURCE'");
$offMap   = (int) $one("SELECT COUNT(*) FROM repair01_w7_scope WHERE map_rule = 'W7_DECISION:W7-D-01'");
$mismN    = (int) $one("SELECT COUNT(*) FROM repair01_w7_scope WHERE owner_verdict = 'MISMATCH'");
$noSysN   = (int) $one("SELECT COUNT(*) FROM mnt_order WHERE is_deleted = 0 AND safety_system_key = ''");
$deadStates = (int) $one("SELECT COUNT(DISTINCT state) FROM mnt_order WHERE is_deleted = 0");
$cmp03Permits = (int) $one("SELECT COUNT(*) FROM transfer_permits WHERE is_deleted = 0");

$DEC = array(
    array('W7-D-01', 'متطلَّبٌ في النطاقِ بلا مِرساةٍ مُعلَنةٍ ولا صفٍّ في دفترِ الفجوات',
        'يُسجَّل بمؤشِّرٍ إلى هذا القرارِ ولا تُخترع له شاشةٌ — والحسمُ في موجةِ الإدارةِ المالكة',
        'اختراعُ سطحٍ هنا يثبّت اسمًا لم يقرّه مالكُه ويُدخله السجلَّ المعياريَّ بلا سند', $offMap),
    array('W7-D-02', 'سطحٌ في النطاقِ بلا مصدرِ ترتيبٍ في السجلّ',
        'يُوسَم NO_ORDER_SOURCE ولا يُرتَّب بالأبجديّةِ ولا بتاريخِ الإنشاء — والترتيبُ من السجلِّ وحدَه',
        'ترتيبٌ يدويٌّ موازٍ للسجلِّ محظورٌ نصًّا في §٥ — والوسمُ يُبقي الفجوةَ مرئيّةً حتى تُحسَم', $noOrder),
    array('W7-D-03', 'سطحٌ في النطاقِ تحت مالكٍ يخالف مالكَ المتطلَّب',
        'يُعلَن بعددِه ولا يُدهَس — والملكيّةُ حُسمت في W01 والمخالفةُ هنا مرآةٌ لا تصحيح',
        'دهسُ المالكِ في W07 ينقض حكمًا مُغلقًا في W01 بلا قرارِ مالك', $mismN),
    array('W7-D-04', 'أمرُ عملٍ قائمٌ بلا نظامٍ مصابٍ مُعلَنٍ يربطه بقاعدةِ سلامة',
        'يُصنَّف minor بقاعدةِ W7_SAFETY_NO_SYSTEM_MATCH **مكتوبةً في الصفّ** — ولا يُخترع له تصنيف',
        'تصنيفُ سلامةٍ مخترَعٌ أخطرُ من غيابِه: الغيابُ يُرى ويُصلَح، والمخترَعُ يُقرأ حكمًا ويُبنى عليه إذنُ تشغيل', $noSysN),
    array('W7-D-05', 'حالةُ أمرِ العملِ نصٌّ عربيٌّ حرٌّ مبتورٌ في عمودٍ بعرضِ عشرين محرفًا',
        'تُترك كما هي في W07 ويُضاف محورُ قاعدةٍ مستقلٌّ (w7_state_rule) — ولا تُدهَس القيمُ القائمة',
        'المقيسُ حيًّا: حالاتٌ متمايزةٌ في mnt_order.state أكثرُها جملةٌ مبتورةٌ عند عشرين محرفًا (بترٌ بتحذير 1265). وتوحيدُها آلةَ حالةٍ يمحو وقائعَ قائمةً قبل أن تُراجَع — وآلةُ الحالةِ الجديدةُ تُبنى على mnt_return_cert وحدَه', $deadStates),
    array('W7-D-07', 'أمرُ صيانةٍ يشير إلى أصلٍ لم يعد في سجلِّ المعدّات',
        'يُقصى من اشتقاقِ مؤشّرِ الفترةِ ويُعلَن بعددِه — ولا يُشتقُّ سطرُ جاهزيّةٍ لأصلٍ لا وجودَ له',
        'المقيسُ حيًّا: أوامرُ صيانةٍ حيّةٌ على مُعرِّفاتِ أصولٍ ليست في `equipments` (حذفٌ صلبٌ ماضٍ). واشتقاقُ مؤشِّرٍ لها يُنمّي مقامَ المؤشّراتِ برقمٍ لا مرجعَ له، ويجعل «متوسّطَ الجاهزيّة» يُقسَم على أصولٍ لا تُقاس. والإقصاءُ **مُعلَنٌ بعددِه** لا صامت — و«لا سقفَ يُطبَّق بلا إعلان». ⛔ ولا تُحذف الأوامرُ: هي دليلُ صيانةٍ وقعت، والحذفُ يمحو الواقعةَ قبل أن تُراجَع',
        (int) $one("SELECT COUNT(*) FROM mnt_order o LEFT JOIN equipments e ON e.id = o.equipment_id
                     WHERE o.is_deleted = 0 AND o.equipment_id > 0 AND e.id IS NULL")),
    array('W7-D-06', 'شاشةُ التصاريحِ تخزّن في المخزنِ البينيِّ وبوّابةُ المغادرةِ تقرأ السجلَّ الحيّ',
        'يُضاف السجلُّ الحيُّ مُصيَّرًا في الشاشةِ نفسِها بوسمِ من يحجب المغادرة — والهجرةُ الكاملةُ للمخزنِ مؤجَّلةٌ بقرارِ مالك',
        'المخزنُ البينيُّ cmp03_screen_rows لا تقرؤه بوّابةُ المغادرة، فالتصريحُ المنتهي يُعرَض ساريًا ويمنع المغادرةَ صامتًا. والتصييرُ يجعل مصدرَ المنعِ مرئيًّا في شاشتِه', $cmp03Permits),
);
foreach ($DEC as $d) {
    $W("INSERT INTO repair01_w7_decisions (decision_id, question, ruling, rationale, scope_rows)
        VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "','" . $esc($d[3]) . "'," . (int) $d[4] . ")
        ON DUPLICATE KEY UPDATE question=VALUES(question), ruling=VALUES(ruling),
          rationale=VALUES(rationale), scope_rows=VALUES(scope_rows)");
    printf("  %s · صفوفٌ %d\n", $d[0], $d[4]);
}

echo "\n" . str_repeat('─', 78) . "\n";
printf("W07 apply: أسطحُ نموٍّ %d · نطاقٌ %d · سايدبار %d · قواعدُ سلامة %d · مؤشّرات %d+%d · عقود %d · قرارات %d\n",
    $newN, $anchored + $notBuilt + count($unproven), $scopeScreens,
    (int) $one("SELECT COUNT(*) FROM mnt_safety_rule"), $kN, $tN, $written, count($DEC));
echo $REPORT ? "الحكم: قياسٌ بلا كتابة\n" : "الحكم: كُتب ✔\n";
exit(0);
