<?php
/**
 * tools/repair01_w5_apply.php — أداةُ المرحلةِ الخامسة: أثرُ الأصلِ والقوى
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **عاديّةُ التشغيل**: كلُّ كتابةٍ مفتاحُها **عملٌ** (متطلَّبٌ · شاشةٌ · حقُّ
 *   استخدامٍ بحبّتِه · أصلٌ × شهر) لا رقمُ صفّ. فإعادةُ التشغيلِ لا تضاعف.
 *
 * ◆ **والقياسُ قبلَ الكتابة**: لا صفَّ برأي — كلُّ حكمٍ يحمل `*_rule` باسمِ
 *   قاعدتِه، وكلُّ رقمٍ مشتقٌّ من صفوفٍ حيّةٍ أو من القرص.
 *
 * ◆ **ولا يُدهَس حقٌّ متزامن**: `asset_ownership_shares` يُنقل إلى سجلِّ حقِّ
 *   الاستخدامِ **كما هو**، ومجموعُ الحصصِ في نافذةِ البدايةِ يُقاس ويُوسَم.
 *   النافذةُ التي تتجاوز المئةَ تبقى مقروءةً بوسمِها — وحسمُها عند مالكِها.
 *
 * ◆ **والمشتقُّ يُعاد بناؤه في كلِّ تشغيل**: `asset_readiness` و`wf_coverage`
 *   مشتقّانِ لا حقيقةَ أصليّةً فيهما — فتفريغُهما وإعادةُ اشتقاقِهما آمنٌ،
 *   بخلافِ `asset_use_right` الذي يحمل قرارًا فيُحدَّث بمفتاحِه ولا يُفرَّغ.
 *
 * التشغيل:
 *   php tools/repair01_w5_apply.php            # قياسٌ وكتابة
 *   php tools/repair01_w5_apply.php --report   # قياسٌ بلا كتابة
 *   php tools/repair01_w5_apply.php --revert   # إرجاعُ ما كتبته هذه الأداةُ وحدَها
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
require_once $ROOT . '/tools/lib/repair01_w3_contracts.php';
require_once $ROOT . '/app/Services/Fleet/AssetLifecycleService.php';
require_once $ROOT . '/tools/lib/repair01_w5_scan.php';
require_once $ROOT . '/tools/lib/repair01_w5_contracts.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
\App\Services\Fleet\AssetLifecycleService::setEventConnection($conn);

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
$one = function ($sql) use ($conn) { return repair01_w5_one($conn, $sql); };

/* ═══════════════════════════════════════════════════════════════════════════
   أسطحُ النموِّ — الستّةُ التي تبنيها هذه المرحلةُ وتختمها بموجتِها
   ═══════════════════════════════════════════════════════════════════════════
   ⛔ `origin` = `W05` بالضبط (RPR-PATCH-02): أساسُ السجلِّ مُجمَّدٌ والنموُّ
      مسموحٌ **مختومًا وحدَه** — وحاجبا `W3-14` و`W4-15` يسقطان على نموٍّ بلا ختم. */
function repair01_w5_new_surfaces()
{
    return array(
        array('route' => 'Fleet/asset_intake.php',      'ar' => 'طلب إدخال الأصل',            'icon' => 'fa fa-truck-ramp-box',
              'group' => 'دورة الأصل — الدخول', 'sort' => 10, 'owner' => 'DEP-04', 'role' => 'مسؤول الأسطول',
              'sibling' => 'Equipments/equipments.php',
              'cycle' => array('stage' => '1', 'stage_name' => 'دخول الأصل', 'out' => 'طلب إدخال معتمد',
                               'next' => 'التحقق من المصدر', 'cons' => 'الصيانة · المالية', 'fin' => 'لا أثر مباشر')),
        array('route' => 'Fleet/asset_use_rights.php',  'ar' => 'حق الاستخدام التشغيلي',      'icon' => 'fa fa-handshake',
              'group' => 'دورة الأصل — الدخول', 'sort' => 20, 'owner' => 'DEP-04', 'role' => 'مسؤول التمويل',
              'sibling' => 'Equipments/equipments.php',
              'cycle' => array('stage' => '2', 'stage_name' => 'حق الاستخدام', 'out' => 'سجل حق استخدام بفترته',
                               'next' => 'الإسناد للموقع', 'cons' => 'المالية · التمويل', 'fin' => 'وجهة الإيراد والالتزام')),
        array('route' => 'Fleet/asset_assignments.php', 'ar' => 'إسناد الأصل وحركته',          'icon' => 'fa fa-map-location-dot',
              'group' => 'دورة الأصل — الحركة', 'sort' => 30, 'owner' => 'DEP-04', 'role' => 'مسؤول الحركة',
              'sibling' => 'Fleet/readiness_board.php',
              'cycle' => array('stage' => '3', 'stage_name' => 'الحركة والاستخدام', 'out' => 'أمر إسناد بفترته',
                               'next' => 'الرقابة الفنية', 'cons' => 'التشغيل · الموقع', 'fin' => 'وجهة تحميل التكلفة')),
        array('route' => 'Fleet/asset_readiness.php',   'ar' => 'الجاهزية والملخص الشهري',    'icon' => 'fa fa-gauge-high',
              'group' => 'دورة الأصل — الرقابة الفنية', 'sort' => 40, 'owner' => 'DEP-04', 'role' => 'مسؤول الأسطول',
              'sibling' => 'Fleet/readiness_board.php',
              'cycle' => array('stage' => '4', 'stage_name' => 'الرقابة الفنية', 'out' => 'تقرير جاهزية مشتق',
                               'next' => 'قرار الخروج أو الاستمرار', 'cons' => 'التشغيل · المالية', 'fin' => 'أساس الإهلاك بالساعات')),
        array('route' => 'Fleet/asset_exit.php',        'ar' => 'خروج الأصل',                 'icon' => 'fa fa-right-from-bracket',
              'group' => 'دورة الأصل — الخروج', 'sort' => 50, 'owner' => 'DEP-04', 'role' => 'المدير المالي',
              'sibling' => 'Fleet/readiness_board.php',
              'cycle' => array('stage' => '5', 'stage_name' => 'الخروج', 'out' => 'محضر خروج بمرجعه',
                               'next' => 'إقفال الأصل أو عودته', 'cons' => 'المالية · التمويل', 'fin' => 'إيقاف الإهلاك وإثبات الاستبعاد')),
        array('route' => 'Workforce/wf_coverage.php',   'ar' => 'لوحة الاحتياج والتغطية', 'icon' => 'fa fa-people-group',
              'group' => 'الاحتياج والتغطية', 'sort' => 15, 'owner' => 'DEP-13', 'role' => 'مسؤول القوى التشغيلية',
              'sibling' => 'Workforce/workforce_requirement.php',
              'cycle' => array('stage' => '2', 'stage_name' => 'الاحتياج والتغطية', 'out' => 'سطر فجوة مشتق',
                               'next' => 'الترشيح والاختيار', 'cons' => 'الموارد البشرية · التشغيل', 'fin' => 'لا أثر مباشر')),
    );
}

echo "══ REPAIR01 · W05 — " . ($REVERT ? 'إرجاع' : ($REPORT ? 'قياسٌ بلا كتابة' : 'قياسٌ وكتابة')) . " ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⓪ الإرجاع — يُفرِّغ ما كتبته هذه الأداةُ وحدَها
   ═══════════════════════════════════════════════════════════════════════════ */
if ($REVERT) {
    foreach (repair01_w5_new_surfaces() as $s) {
        $rt = $esc($s['route']);
        $conn->query("DELETE FROM nav_items WHERE route = '$rt'");
        $conn->query("DELETE FROM nav_canonical WHERE route = '$rt'");
        $slugR = strtolower(preg_replace('/[^a-z0-9]+/i', '_', str_replace('.php', '', $s['route'])));
        $conn->query("DELETE FROM link_groups WHERE group_code LIKE 'n9o_w5_" . $esc(substr($slugR, 0, 30)) . "_r%'");
        $conn->query("DELETE FROM role_permissions WHERE module_id IN (SELECT id FROM modules WHERE code = '$rt')");
        $conn->query("DELETE FROM modules WHERE code = '$rt'");
        $conn->query("DELETE FROM gov_screen_cycle WHERE screen_file = '" . $esc(basename($s['route'])) . "'");
        $conn->query("DELETE FROM repair01_screen_registry WHERE route = '$rt' AND origin = 'W05'");
        echo "  ✔ نُزع سطحُ النموّ " . $s['route'] . "\n";
    }
    foreach (array('repair01_w5_sidebar', 'repair01_w5_scope', 'repair01_w5_decisions',
                   'asset_use_right', 'asset_readiness', 'wf_coverage',
                   'asset_assignment', 'asset_exit', 'asset_inspection_order',
                   'asset_source_check', 'asset_intake') as $t) {
        if ($conn->query("DELETE FROM `$t`")) { echo "  ✔ فُرِّغ $t\n"; }
    }
    $conn->query("DELETE FROM repair01_events WHERE contract_stage = 'W05' AND wave = 'W05'");
    echo "  ✔ عقودُ الأثرِ المكتوبةُ في W05 نُزعت\n";
    $conn->query("UPDATE equipments SET lifecycle_state = '', lifecycle_rule = '', intake_id = NULL
                   WHERE lifecycle_rule <> '' OR intake_id IS NOT NULL");
    echo "  ✔ حالةُ دورةِ الأصلِ في الكرتِ أُفرِغت\n";
    echo "\nالحكم: رجعت ✔ (والجداولُ والقيودُ تُنزع بهجرةِ التراجع)\n";
    exit(0);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① تسجيلُ أسطحِ النموِّ — قبلَ السايدبارِ لأنّها جزءٌ من مقامِه
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① أسطحُ النموِّ — ستّةُ أسطحٍ مختومةٍ بموجتِها ──────────────────\n";
/* أثرُ محاولةٍ سابقةٍ في مصفوفةِ الدراسةِ المُجمَّدةِ يُنزَع — انظر W5-D-13 */
$W("DELETE FROM gov_screen_cycle WHERE inputs_note = 'RPR-W05'");
$newN = 0; $navN = 0; $permN = 0;
$maxSid = (int) preg_replace('/\D/', '', (string) $one("SELECT screen_id FROM repair01_screen_registry
                                                          ORDER BY screen_id DESC LIMIT 1"));
foreach (repair01_w5_new_surfaces() as $s) {
    $rt = $esc($s['route']); $file = basename($s['route']);
    if (!is_file($ROOT . '/' . $s['route'])) { echo "  ⚠ لا ملفَّ على القرصِ: " . $s['route'] . " — لا يُسجَّل\n"; continue; }

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

    /* ⓒ السجلُّ المعياريُّ للتنقُّل — الاسمُ والمجموعةُ والترتيبُ من دورةِ العمل */
    $sid = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    if ($sid === '') { $maxSid++; $sid = 'SCR-' . str_pad((string) $maxSid, 4, '0', STR_PAD_LEFT); }
    $W("INSERT INTO nav_canonical (route, canonical_ar, level_no, level_name, group_name, sort_no,
                                   status, decision_state, application_state, decision_source,
                                   derivation, retirement_status, screen_id)
        VALUES ('$rt','" . $esc($s['ar']) . "',2,'العمليات','" . $esc($s['group']) . "'," . (int) $s['sort'] . ",
                'APPROVED','APPROVED','DEPLOYED','RPR-W05 · دورة الأصل والقوى (2026-08-25)',
                'دورةُ الإدارةِ المالكةِ في الحزمة','ACTIVE','" . $esc($sid) . "')
        ON DUPLICATE KEY UPDATE canonical_ar=VALUES(canonical_ar), group_name=VALUES(group_name),
          sort_no=VALUES(sort_no), status=VALUES(status), screen_id=VALUES(screen_id)");

    /* ⓓ **مجموعةُ الدورةِ لا مجموعةُ الشقيق** (خطوةُ السايدبارِ ③):
         المسارُ يُصيَّر لأدوارٍ كثيرة، ومجموعةُ الشقيقِ تختلف بينها — فالمسارُ
         الواحدُ يظهر تحت **سبعةِ أسماءِ مجموعاتٍ** (مقيسٌ فعلًا: `asset_intake`
         تحت «الأعيان · المعدات · الموارد المتاحة …»). وذلك يخالف بندَ «مسارٌ
         واحدٌ ⇒ مجموعةٌ واحدة» ويُسقط بوّابةَ `U3` عند أوّلِ التزام.
         فلكلِّ دورٍ **مجموعتُه هو باسمِ الدورةِ نفسِه** — الاسمُ واحدٌ عبرَ
         الأدوارِ والمُعرِّفُ لكلِّ دور.
       ⛔ والبادئةُ `n9o_` إلزاميّة: `nav09_verify` يقارن `n9s%` صفًّا صفًّا
         فيسقط على زيادة، و`nav09_sweep_others` يكنس ما ليس `n9s%` ولا `n9o%`
         إلى «أخرى — للمراجعة». فبادئةٌ مبتكرةٌ تعمل اليومَ وتُبتلع عند أوّلِ كنس. */
    if ($modId > 0) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', str_replace('.php', '', $s['route'])));
        $sib = $conn->query("SELECT n.role_id, n.door, g.stage_no, g.stage_title, g.display_order
                               FROM nav_items n
                               LEFT JOIN link_groups g ON g.id = n.group_id
                              WHERE n.route = '" . $esc($s['sibling']) . "' AND n.active = 1
                              GROUP BY n.role_id, n.door, g.stage_no, g.stage_title, g.display_order");
        while ($sib && $sx = $sib->fetch_assoc()) {
            $rid  = (int) $sx['role_id'];
            $code = 'n9o_w5_' . substr($slug, 0, 30) . '_r' . $rid;
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

    /* ⓔ ⛔ **ولا يُكتب صفٌّ في `gov_screen_cycle`** — وهذا حدٌّ مقيسٌ لا اختيار:
         `G0-07` يقيس مقامَ الأسطحِ الحيَّ من مصفوفةِ الدورةِ ويقارنه بـ٦٦٤
         المُجمَّدةِ في `repair01_surfaces`، و`G0-06` يشترط أن يكون كلُّ مسمّى
         إدارةٍ حيٍّ فيها مُجسَّرًا في `repair01_dept_crosswalk`. فصفٌّ جديدٌ
         هناك **يُنمّي مقامَ الدراسةِ المُجمَّد** ويُدخل مسمّى إدارةٍ بلا جسر —
         وقد أسقط الحاجبَين فعلًا قبل أن يُنزع (W5-D-13).
         والنموُّ محلُّه `repair01_screen_registry` مختومًا وحدَه؛ وشريطُ الدورةِ
         في الترويسةِ أثرُ الدراسةِ لا شرطُ عملِ الشاشة. */

    /* ⓕ سجلُّ الشاشاتِ — بختمِ الموجةِ لا بلا ختم */
    $guard = repair01_w5_guard_of($ROOT, $s['route']);
    $W("INSERT INTO repair01_screen_registry
        (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
         lifecycle, lifecycle_rule, parent_screen_id, parent_rule, visibility_class, visibility_rule,
         on_disk, origin, guard_kind, guard_evidence, w2_why, src_ref)
        VALUES ('" . $esc($sid) . "','" . $esc($file) . "','$rt','W5_NEW_SURFACE_ROUTE',
                '" . $esc($s['owner']) . "','" . $esc($s['role']) . "','W5_REQUIREMENT_OWNER',
                'LIVE_UNREGISTERED','W5_GROWTH_OUTSIDE_STUDY_MATRIX','','','MENU_ITEM','NAV_ITEMS_ACTIVE',
                1,'W05','" . $esc($guard['kind']) . "','" . $esc($guard['evidence']) . "',
                '" . $esc($s['ar']) . " (" . $esc($file) . ")','RPR-W05 · دورة الأصل والقوى')
        ON DUPLICATE KEY UPDATE owner_code=VALUES(owner_code), owner_role=VALUES(owner_role),
          visibility_class=VALUES(visibility_class), guard_kind=VALUES(guard_kind),
          guard_evidence=VALUES(guard_evidence), origin='W05', on_disk=1");
    $newN++;
}
printf("  أسطحٌ مسجَّلةٌ بختمِ W05 %d · بنودُ قائمةٍ نشِطة %d · منحٌ %d\n\n", $newN, $navN, $permN);

/* ═══════════════════════════════════════════════════════════════════════════
   ② نطاقُ المرحلة — ٤١ متطلَّبًا إلى مِرساتِها المُثبَتةِ قياسًا
   ═══════════════════════════════════════════════════════════════════════════ */
echo "② نطاقُ المرحلة ───────────────────────────────────────────────\n";
$W("DELETE FROM repair01_w5_scope");
$ANCH = repair01_w5_anchors();
$anchored = 0; $notBuilt = 0; $unproven = array(); $ownerMismatch = array();

$rq = $conn->query("SELECT requirement_id, unit, group_name, surface, src_ref
                      FROM repair01_requirements WHERE stage_no = 5 ORDER BY unit, seq");
while ($rq && $q = $rq->fetch_assoc()) {
    $rid = $q['requirement_id'];
    $dept = preg_match('/^(\d{2})\s/u', $q['unit'], $mm) ? 'DEP-' . $mm[1] : '';
    if (!isset($ANCH[$rid])) {
        $W("INSERT INTO repair01_w5_scope
            (requirement_id,unit,group_name,surface,anchor_screen_id,anchor_route,anchor_probe,
             owner_measured,owner_expected,owner_verdict,map_rule,map_why,wave_stage,src_ref)
            VALUES ('" . $esc($rid) . "','" . $esc($q['unit']) . "','" . $esc($q['group_name']) . "',
                    '" . $esc($q['surface']) . "','','','','','" . $esc($dept) . "','UNKNOWN',
                    'W5_DECISION:W5-D-01','متطلب خارج خريطة المراسي — يحسم بقرار المالك','',
                    '" . $esc($q['src_ref']) . "')");
        $notBuilt++; continue;
    }
    $a = $ANCH[$rid];
    $p = repair01_w5_prove_anchor($conn, $ROOT, $a);
    $ownerV = ($p['sid'] === '') ? 'NOT_BUILT' : (($p['owner'] === $dept) ? 'MATCH' : 'MISMATCH');
    if ($ownerV === 'MISMATCH') { $ownerMismatch[] = $rid . '⇐' . ($p['owner'] !== '' ? $p['owner'] : 'بلا مالك'); }
    if ($p['verdict'] === 'ANCHORED') { $anchored++; }
    elseif ($p['verdict'] === 'NOT_BUILT') { $notBuilt++; }
    else { $unproven[] = $rid . ' (' . $p['verdict'] . ')'; }

    $W("INSERT INTO repair01_w5_scope
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
$codes = repair01_w5_scope_codes($conn);
$codesSql = "'" . implode("','", array_map($esc, $codes)) . "'";
$tabMap = repair01_w3_entity_tab_map($ROOT);
$W("DELETE FROM repair01_w5_sidebar");

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
    else { $s4src = ''; $s4no = 0; $s4v = 'NO_ORDER_SOURCE'; $s4r = 'W5_DECISION:W5-D-02'; }

    /* ─ ⑤ الأبُ والتبويب: القرارُ يحدّد الموضعَ — والبلوغُ يُقاس قبلَ الخفض ─ */
    $s5v = 'NO_PARENT'; $s5r = 'REGISTRY_NO_PARENT'; $s5why = '';
    if ($x['parent_screen_id'] !== '') {
        $tab = isset($tabMap[strtolower($rt)]) ? $tabMap[strtolower($rt)] : null;
        $renders = repair01_w3_renders_tabs($ROOT, $rt);
        $parentRoute = (string) $one("SELECT route FROM repair01_screen_registry WHERE screen_id='" . $esc($x['parent_screen_id']) . "'");
        $w5Demoted = ($x['visibility_class'] === 'TAB_CHILD'
                      && (string) $one("SELECT visibility_rule FROM repair01_screen_registry
                                         WHERE screen_id = '" . $esc($sid) . "'") === 'W5_TAB_PROVEN_BY_ENTITY_TABS');
        $childRoles  = $w5Demoted ? repair01_w3_hidden_roles($conn, $rt) : repair01_w3_nav_roles($ROOT, $conn, $rt);
        $parentRoles = $parentRoute !== '' ? repair01_w3_nav_roles($ROOT, $conn, $parentRoute) : array();
        $lost = array_values(array_diff($childRoles, $parentRoles));
        if ($x['visibility_class'] !== 'MENU_ITEM' && !$w5Demoted) { $s5v = 'ALREADY_TAB'; $s5r = 'REGISTRY_' . $x['visibility_class']; }
        elseif ($tab === null)  { $s5v = 'TAB_CLAIM_UNPROVEN';   $s5r = 'NO_ROW_IN_ENTITY_TABS';  $s5why = 'ادعاء اب بلا مرساة مقيسة في سجل تبويبات الكيانات'; $s5Blocked++; }
        elseif ($renders === 0) { $s5v = 'TAB_BAR_NOT_RENDERED'; $s5r = 'DISK_NO_ems_entity_tabs'; $s5why = 'الشاشة لا تطبع شريط الرحلة فالخفض يقطع الطريق'; $s5Blocked++; }
        elseif ($lost)          { $s5v = 'DEMOTION_LOSES_ROLES'; $s5r = 'ROLE_REACH_MEASURED'; $s5why = 'ادوار تفقد البلوغ في السايدبار المصير: ' . implode('،', $lost); $s5Blocked++; }
        else {
            $renderedIn = $childRoles ? implode(',', array_map('intval', $childRoles)) : '-1';
            $W("INSERT INTO gov_nav_hidden_log (role_id, nav_id, route, label_ar, group_before, sort_before, doc_code, reachable)
                SELECT role_id, id, route, label_ar, group_id, sort_order, 'RPR-W05',
                       CASE WHEN role_id IN ($renderedIn) THEN 'TAB_IN_PARENT' ELSE 'NOT_RENDERED' END
                  FROM nav_items WHERE ($navPred) AND active = 1
                ON DUPLICATE KEY UPDATE doc_code = 'RPR-W05',
                       reachable = CASE WHEN nav_items.role_id IN ($renderedIn) THEN 'TAB_IN_PARENT' ELSE 'NOT_RENDERED' END");
            $W("UPDATE nav_items SET active = 0 WHERE ($navPred) AND active = 1");
            $W("UPDATE repair01_screen_registry SET visibility_class = 'TAB_CHILD',
                    visibility_rule = 'W5_TAB_PROVEN_BY_ENTITY_TABS' WHERE screen_id = '" . $esc($sid) . "'");
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
    $W("INSERT INTO repair01_w5_sidebar
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
   ④ حقُّ الاستخدامِ التشغيليّ — يُنقل بحبّتِه ويُوسَم تزامنُه
   ═══════════════════════════════════════════════════════════════════════════ */
echo "④ حقُّ الاستخدامِ التشغيليّ ─────────────────────────────────────\n";
$windows = repair01_w5_ownership_windows($conn);
$conc = repair01_w5_concurrent_windows($windows);
printf("  نوافذُ مقيسة %d · متزامنٌ فوقَ المئة %d\n", count($windows), count($conc));

$dupes = repair01_w5_ownership_dupes($conn);
$collapsed = repair01_w5_ownership_collapsed($dupes);
printf("  حبّةٌ بصفَّين %d · صفوفٌ تُطوى حتمًا %d\n", count($dupes), $collapsed);

$grantN = 0; $openClaims = 0;
if (!$REPORT) {
    foreach (repair01_w5_ownership_rows($conn) as $sh) {
        $g = $gateFor($sh['company_id']);
        $holderRef = ((int) $sh['financier_entity_id'] > 0) ? (int) $sh['financier_entity_id'] : (int) $sh['op_id'];
        $name = (string) $sh['model_code'];
        $dk = (int) $sh['asset_id'] . '|' . $holderRef . '|' . $sh['valid_from'];
        $note = isset($dupes[$dk])
            ? $dupes[$dk]['note']
            : 'محمولٌ من سجلِّ حصصِ الملكيّةِ الحيّ — والتزامنُ مقيسٌ في نافذةِ البداية';
        $r = \App\Services\Fleet\AssetLifecycleService::grantUseRight($g, array(
            'equipment_id'    => (int) $sh['asset_id'],
            'holder_kind'     => ((int) $sh['financier_entity_id'] > 0) ? 'financier' : 'company',
            'holder_ref_id'   => $holderRef,
            'holder_name'     => ($name !== '' ? $name : 'حائزٌ بلا اسمٍ في السجلّ'),
            'percent'         => (float) $sh['percent'],
            'valid_from'      => (string) $sh['valid_from'],
            'valid_to'        => ($sh['valid_to'] !== null && $sh['valid_to'] !== '') ? (string) $sh['valid_to'] : '',
            'doc_ref'         => (string) $sh['doc_ref'],
            'source_register' => 'asset_ownership_shares',
            'source_row_ref'  => 'share#' . (int) $sh['share_id'],
            'concurrency_note' => $note,
        ));
        if ($r['ok']) { $grantN++; if ($r['rule'] === 'W5_CONCURRENT_CLAIM_OPEN') { $openClaims++; } }
    }
}
$openLive = (int) $one("SELECT COUNT(*) FROM asset_use_right WHERE concurrency_rule = 'W5_CONCURRENT_CLAIM_OPEN'");
printf("  حقوقٌ منقولة %d · حقٌّ متزامنٌ مفتوحٌ %d (المقيسُ في السجلّ %d)\n\n", $grantN, $openClaims, $openLive);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ حالةُ الأصلِ المشتقّة — تُكتب في الكرتِ بقاعدتِها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑤ حالةُ الأصلِ المشتقّة ────────────────────────────────────────\n";
$lc = repair01_w5_lifecycle_measure($conn);
$byState = array();
foreach ($lc as $eq => $v) {
    $byState[$v['state']] = (isset($byState[$v['state']]) ? $byState[$v['state']] : 0) + 1;
    $W("UPDATE equipments SET lifecycle_state = '" . $esc($v['state']) . "',
             lifecycle_rule = '" . $esc($v['rule']) . "'
         WHERE id = " . (int) $eq . "
           AND (lifecycle_state <> '" . $esc($v['state']) . "' OR lifecycle_rule <> '" . $esc($v['rule']) . "')");
}
$parts = array(); foreach ($byState as $k => $n) { $parts[] = "$k $n"; }
printf("  أصولٌ مقيسة %d · %s\n\n", count($lc), implode(' · ', $parts));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ الجاهزيّةُ الشهريّة — مشتقّةٌ بالكامل لا تُدخَل
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑥ الجاهزيّةُ الشهريّة ──────────────────────────────────────────\n";
$W("DELETE FROM asset_readiness");
$measure = repair01_w5_readiness_measure($conn);
$rdN = 0; $rdOrphan = 0;
foreach ($measure as $k => $m) {
    if (!$m['resolved']) { $rdOrphan++; continue; }
    if ($REPORT) { continue; }
    $g = $gateFor($m['company']);
    $id = \App\Services\Fleet\AssetLifecycleService::writeReadiness($g, $m['equipment'], $m['period'], $m);
    if ($id > 0) { $rdN++; }
}
$avg = (string) $one("SELECT ROUND(AVG(readiness_pct),2) FROM asset_readiness");
printf("  صفوفُ (أصل × شهر) المقيسة %d · مكتوبة %d · **بأصلٍ لا صفَّ له** %d · متوسّطُ الجاهزيّة %s٪\n\n",
    count($measure), $rdN, $rdOrphan, $avg !== '' ? $avg : '—');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ تغطيةُ القوى — المشتقُّ سجلٌّ والمُدخَلُ مرآةٌ بفارقِها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑦ تغطيةُ القوى ────────────────────────────────────────────────\n";
$vocab = repair01_w5_coverage_vocab($conn);
printf("  مفرداتُ حالةِ الاحتياجِ الحيّة %d · بلا جسرٍ %d%s\n",
    count($vocab['live']), count($vocab['unmapped']),
    $vocab['unmapped'] ? ' ⇐ ' . implode('، ', $vocab['unmapped']) : '');
$W("DELETE FROM wf_coverage");
$cov = repair01_w5_coverage_measure($conn);
$covN = 0; $covVar = 0;
foreach ($cov as $c) {
    if ($c['variance_rule'] === 'W5_COVERAGE_VARIANCE_OPEN') { $covVar++; }
    $ok = $W("INSERT INTO wf_coverage
        (company_id,requirement_id,project_id,worker_category,required_qty,available_qty,gap_qty,surplus_qty,
         coverage_state,derivation_rule,declared_state,declared_gap,variance_rule,variance_note,derived_at)
        VALUES (" . (int) $c['company'] . "," . (int) $c['id'] . "," . ($c['project'] > 0 ? (int) $c['project'] : 'NULL') . ",
                '" . $esc($c['category']) . "'," . (int) $c['required'] . "," . (int) $c['available'] . ",
                " . (int) $c['gap'] . "," . (int) $c['surplus'] . ",'" . $esc($c['state']) . "','" . $esc($c['rule']) . "',
                '" . $esc($c['declared_state']) . "'," . (int) $c['declared_gap'] . ",'" . $esc($c['variance_rule']) . "',
                '" . $esc('المُعلَنُ حيًّا «' . $c['declared_state_ar'] . '» بعجزٍ ' . $c['declared_gap']
                          . ' — والمشتقُّ ' . $c['state'] . ' بعجزٍ ' . $c['gap']) . "',NOW())
        ON DUPLICATE KEY UPDATE gap_qty=VALUES(gap_qty), surplus_qty=VALUES(surplus_qty),
          coverage_state=VALUES(coverage_state), variance_rule=VALUES(variance_rule), derived_at=NOW()");
    if ($ok) { $covN++; }
}
printf("  سطورُ تغطيةٍ مشتقّة %d · فارقٌ مفتوحٌ بين المشتقِّ والمُعلَن %d\n\n", $covN, $covVar);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ عقودُ الأثرِ — لكلِّ حدثٍ يصدر من النطاق
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑧ عقودُ الأثر ─────────────────────────────────────────────────\n";
$narr = repair01_w5_contract_narrative();
$W("DELETE FROM repair01_events WHERE contract_stage = 'W05' AND wave = 'W05'");
$ents = "'" . implode("','", array_map($esc, repair01_w5_entity_types())) . "'";
$liveKeys = array();
$r = $conn->query("SELECT DISTINCT event_key, entity_type FROM ems_business_events WHERE entity_type IN ($ents)");
while ($r && $x = $r->fetch_assoc()) { $liveKeys[$x['event_key']] = $x['entity_type']; }
$liveN = count($liveKeys);
foreach (repair01_w5_stage_events() as $ek) { if (!isset($liveKeys[$ek])) { $liveKeys[$ek] = 'asset_intake'; } }

$written = 0; $missing = array(); $inherited = 0;
foreach ($liveKeys as $ek => $ent) {
    if (!isset($narr[$ek])) { $missing[] = $ek; continue; }
    /* ⚠ **عقدٌ واحدٌ لكلِّ حدثٍ لا عقدٌ لكلِّ مرحلة** — المرحلةُ ترث ولا تكرّر (W04 §٥) */
    $prior = (int) $one("SELECT COUNT(*) FROM repair01_events
                          WHERE event_code = '" . $esc($ek) . "' AND contract_status = 'RECORDED'
                            AND contract_stage <> '' AND contract_stage <> 'W05'");
    if ($prior > 0) { $inherited++; continue; }
    $c5 = $narr[$ek];
    $m = repair01_w3_measure_consumers($conn, $ek);
    $consumers = $m['consumers']; $effects = $m['effects']; $retry = $m['retry'];
    if (!$consumers) {
        $consumers = $c5['consumers']; $effects = $c5['effects'];
        $retry = 'بلا اشتراك مسجل — الحدث لم يطلق بعد';
    }
    $W("INSERT INTO repair01_events
        (event_code,name,wave,source_unit,source_screen,idempotency_key,consumers,effect_type,retry_policy,src_ref,
         trigger_rule,min_payload,consumer_list,consumer_effect,preconditions,failure_policy,compensation,
         contract_status,contract_rule,contract_stage)
        VALUES ('" . $esc($ek) . "','" . $esc($c5['name']) . "','W05','" . $esc($c5['unit']) . "',
                '" . $esc($c5['screen']) . "','" . $esc($c5['idem']) . "','" . $esc(implode(' · ', $consumers)) . "',
                '" . $esc($c5['effect']) . "','" . $esc($retry) . "',
                '" . $esc('قياسٌ حيّ: ' . $ent . ' › ' . $ek . ' + ' . $c5['src']) . "',
                '" . $esc($c5['trigger']) . "','" . $esc($c5['payload']) . "','" . $esc(implode("\n", $consumers)) . "',
                '" . $esc(implode("\n", $effects)) . "','" . $esc($c5['pre']) . "','" . $esc($c5['fail']) . "',
                '" . $esc($c5['comp']) . "','RECORDED','LIVE_EVENT_KEY_MEASURED','W05')");
    $written++;
}
printf("  أحداثٌ حيّةٌ مقيسةٌ في النطاق %d · أحداثُ النطاق %d · عقدٌ مكتوبٌ هنا %d · موروثٌ %d · بلا عقدٍ %d%s\n\n",
    $liveN, count($liveKeys), $written, $inherited, count($missing), $missing ? ' ⇐ ' . implode('، ', $missing) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑨ قراراتُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
$noOrder = (int) $one("SELECT COUNT(*) FROM repair01_w5_sidebar WHERE s4_verdict = 'NO_ORDER_SOURCE'");
$offMap  = (int) $one("SELECT COUNT(*) FROM repair01_w5_scope WHERE map_rule = 'W5_DECISION:W5-D-01'");
$mismN   = (int) $one("SELECT COUNT(*) FROM repair01_w5_scope WHERE owner_verdict = 'MISMATCH'");
$gapN    = (int) $one("SELECT COUNT(*) FROM repair01_w5_scope WHERE anchor_screen_id = ''");
$sodOrd  = (int) $one("SELECT COUNT(*) FROM asset_inspection_order o
                        JOIN asset_intake i ON i.id = o.intake_id
                       WHERE o.ordered_by = i.requested_by AND o.ordered_by IS NOT NULL");
$sodVer  = (int) $one("SELECT COUNT(*) FROM asset_source_check c
                        JOIN asset_intake i ON i.id = c.intake_id
                       WHERE c.verified_by = i.requested_by AND c.verified_by IS NOT NULL");
$openLive = (int) $one("SELECT COUNT(*) FROM asset_use_right WHERE concurrency_rule = 'W5_CONCURRENT_CLAIM_OPEN'");
$covVarN = (int) $one("SELECT COUNT(*) FROM wf_coverage WHERE variance_rule = 'W5_COVERAGE_VARIANCE_OPEN'");
$finAsset = (int) $one("SELECT COUNT(DISTINCT event_key) FROM ems_business_events WHERE entity_type = 'fin_asset'");

$DEC = array(
    array('W5-D-01', 'متطلَّبٌ في النطاقِ بلا مِرساةٍ مُعلَنةٍ ولا صفٍّ في دفترِ الفجوات',
        'يُسجَّل بمؤشِّرٍ إلى هذا القرارِ ولا تُخترع له شاشةٌ — والحسمُ في موجةِ الإدارةِ المالكة',
        'اختراعُ سطحٍ هنا يثبّت اسمًا لم يقرّه مالكُه ويُدخله السجلَّ المعياريَّ بلا سند',
        $offMap),
    array('W5-D-02', 'شاشةٌ في النطاقِ بلا موضعٍ من دورةِ عملٍ ولا صفٍّ معياريّ',
        'ترتيبُها يبقى كما هو ويُوسَم NO_ORDER_SOURCE — ولا يُخترع لها رقمُ ترتيبٍ يدويّ',
        'رقمُ ترتيبٍ مخترعٌ ترتيبٌ يدويٌّ موازٍ للسجلّ — وهو المحظورُ نفسُه في §٥',
        $noOrder),
    array('W5-D-03', 'حصصُ استخدامٍ متزامنةٌ على أصلٍ واحدٍ يبلغ مجموعُها في النافذةِ فوقَ المئة',
        'تُنقل كما هي إلى `asset_use_right` بحبّتِها (أصل × حائز × بدايةِ فترة)، ويُقاس مجموعُ المتزامنِ في نافذةِ البدايةِ ويُكتب في الصفِّ مع وسمِه `W5_CONCURRENT_CLAIM_OPEN` — ولا يُدهَس ولا يُمنَع في القاعدة. وحسمُه عند مالكِه (التمويل · W11)',
        'المتطلَّبُ `FLEET-09` يقول «الملكيّةُ متعاقبةٌ لا متزامنة»، والمقيسُ ٦٦ تقاطعًا على ١٨ أصلًا و٥٨ نافذةً منها **واحدةٌ فوقَ المئة (٢٠٠٪)**. ودهسُ الصفوفِ يمحو الواقعةَ قبل أن تُراجَع (W3-D-04)؛ ومنعُها بقيدٍ يجعل الحاجبَ **أعمى بالبناء** — و`CHECK` لا يقرأ صفًّا آخرَ أصلًا. فالقياسُ في البوّابةِ لا في المخطَّط',
        $openLive),
    array('W5-D-04', 'عجزُ الاحتياجِ وحالتُه مُدخَلانِ في `workforce_requirement` ويخالفان حسابَهما',
        'يُشتقُّ سطرُ التغطيةِ في سجلٍّ مستقلٍّ (`wf_coverage`) بقاعدةٍ مكتوبةٍ في الصفّ، ويبقى المُدخَلُ **مرآةً بفارقِها** موسومةً `W5_COVERAGE_VARIANCE_OPEN` — ولا يُدهَس عمودٌ حيّ',
        'المقيس: ٢٠ صفًّا · ١٩ عجزُه يخالف حسابَه · ١٤ حالتُه تخالف حسابَها. ودهسُ العمودِ يمحو ما أدخله المستخدمُ قبل أن يُراجَع، والاكتفاءُ بالمُدخَلِ يجعل «الجاهزيّةَ تُدخَل» وهي بنصِّ المتطلَّبِ **تُشتقّ**. فالسجلّانِ معًا: مشتقٌّ يحكم ومُعلَنٌ يبقى دليلًا',
        $covVarN),
    array('W5-D-10', 'سطحُ متطلَّبٍ مبنيٌّ تحت إدارةٍ غيرِ الإدارةِ المالكةِ للمتطلَّب',
        'يُسجَّل `MISMATCH` في دفترِ النطاقِ ولا يُنقل مالكُه هنا — نقلُ الملكيّةِ حكمُ W01 ومقامُه ٦٦٤ سطحًا لا ٤١ متطلَّبًا',
        'تغييرُ `owner_code` من داخلِ مرحلةٍ نطاقيّةٍ يكسر مقامَ الملكيّةِ المُغلقَ في W01 ويجعل الحكمَ يتبع مَن قاسه آخِرًا',
        $mismN),
    array('W5-D-11', 'نموُّ سجلِّ الشاشاتِ يُدخل أسطحًا في مقامِ مرحلةٍ مُغلقةٍ تشترك في الإدارةِ نفسِها',
        'تُنعَش الدفاترُ المشتقّةُ للمرحلةِ السابقةِ **بأداتِها هي** (`repair01_w3_apply.php` — عاديّةُ التشغيل) ولا يُمَسُّ حاجبٌ ولا يُكتب في دفترِها من هنا. والحاجبُ `W3-09` يعيد اشتقاقَ مقامِه فيصير ٧٢/٧٢ صدقًا لا ترقيعًا',
        'حاجبُ `W3-09` **يعيد الاشتقاقَ ولا يقرأ ثابتًا** — فهو لم يُخطئ حين احمرَّ: ستّةُ أسطحٍ في نطاقِه بلا سبعِ خطوات. وتعديلُه كان سيُعمي مرحلةً مُغلقةً لتيسيرِ مرحلةٍ تستفيد منه (‏محظورُ `_CONTEXT`)؛ والكتابةُ في دفترِه من هنا تجعل مالكَ الرقمِ اثنَين. فالعلاجُ إنعاشٌ بأداةِ صاحبِه',
        6),
    array('W5-D-05', 'حدثُ الإهلاكِ يمسُّ الأصلَ ومالكُه المالية — أيُعقَد هنا؟',
        'لا: `expense.depreciation.recorded` على `fin_asset` خارجَ كياناتِ هذه المرحلة — سطحُه `Equipments/fin_assets.php` تحت `DEP-03` في السجلِّ ومالكُ حدثِه المالية. وعقدُه في موجتِها (W10/W11)',
        'كتابةُ عقدٍ لحدثٍ لا تملكه المرحلةُ تُنشئ مرجعَين لحدثٍ واحدٍ ثمّ لا يُعرف أيُّهما يحكم — وهو العطبُ نفسُه الذي منعته W04 بقاعدةِ «ترث ولا تكرّر»',
        $finAsset),
    array('W5-D-06', 'التركيبةُ الممنوعةُ في دورةِ الأصل: طالبُ الإدخالِ يحقّق مصدرَه أو يأمر بتفتيشِه',
        'تُقاس وتُقفَل اتّجاهًا: العددُ المقيسُ لا يزيد على المُعلَنِ هنا — والمنعُ الحيُّ بمحرّكِ قواعدَ مركزيٍّ في W13',
        'الطالبُ والمحقِّقُ شخصٌ واحدٌ يعني أنَّ «التحقُّقَ من المصدر» شهادةُ الطالبِ لنفسِه — وهي تُبطل الحارسَ الذي يشترطه `FLEET-04` قبل الكرت',
        $sodOrd + $sodVer),
    array('W5-D-08', 'عمودُ `timesheet.operator` مُعرِّفُ تشغيلةٍ لا مُعرِّفُ أصل — فعلى أيِّ كيانٍ تُشتقُّ الجاهزيّة؟',
        'الاشتقاقُ يعبر **جسرَ التشغيلة**: `timesheet.operator → operations.id → operations.equipment → equipments.id`. والصفُّ الذي لا تحمل تشغيلتُه أصلًا قائمًا يُقاس ويُوسَم `W5_OPERATION_HAS_NO_ASSET` **ولا يُكتب له صفُّ جاهزيّة** — فجاهزيّةُ أصلٍ لا وجودَ له رقمٌ بلا معنى. والعددُ مُعلَنٌ هنا وتعيد البوّابةُ اشتقاقَه',
        'الشاشةُ الحيّةُ نفسُها تُثبت الجسر: `Timesheet/timesheet.php` تضمُّ `JOIN operations o ON t.operator = o.id`. والمقيس: ٢٠٦ قيمةٍ متمايزة — ٢٠٥ تُحَلُّ تشغيلةً · **٨ فقط** لها صفٌّ في `equipments` بالمُعرِّفِ نفسِه · و١٤ لها صفٌّ في `employees`. فالقراءةُ المباشرةُ تُسند ساعاتِ ١٠٨ أصولٍ إلى ٨ — **لا تُخطئ الرقمَ بل الكيان**. وبالجسرِ تُحَلُّ ٦٥٣ من ٦٥٤ صفًّا إلى ٣٢٠ شهرَ أصلٍ على ١٠٨ أصول',
        $rdOrphan),
    array('W5-D-13', 'أين يُسجَّل سطحُ النموِّ — وهل يدخل مصفوفةَ الدورةِ الحاكمة؟',
        'يُسجَّل في `repair01_screen_registry` مختومًا `origin=W05` وفي `nav_canonical` و`nav_items` و`modules` و`role_permissions` — **ولا يُكتب له صفٌّ في `gov_screen_cycle`**. وحالتُه `LIVE_UNREGISTERED` بقاعدةِ `W5_GROWTH_OUTSIDE_STUDY_MATRIX`: مبنيٌّ ومحروسٌ وموصولٌ وبالغٌ، وخارجَ مصفوفةِ الدراسةِ المُجمَّدة',
        'مقيسٌ بالكسرِ لا بالتقدير: كتابةُ الصفوفِ الستّةِ في `gov_screen_cycle` أسقطت `G0-07` (مقامُ الأسطحِ الحيُّ صار ٦٧٠ مقابل ٦٦٤ المُجمَّدةِ في `repair01_surfaces`) و`G0-06` (مسمّى إدارةٍ حيٌّ بلا جسرٍ في `repair01_dept_crosswalk`). فمصفوفةُ الدورةِ **مقامُ دراسةٍ مُجمَّدٌ يقيسه W00**، لا سجلُّ تشغيلٍ ينمو. والنموُّ محلُّه سجلُّ الشاشاتِ وحدَه',
        6),
    array('W5-D-12', 'حبّةُ حقِّ الاستخدامِ الواحدةُ يحملها صفّانِ في السجلِّ الحيّ',
        'النقلُ إلى الحبّةِ الصحيحةِ (أصل × حائز × بدايةِ فترة) **يطوي** الصفَّين في واحد، والمطويُّ يُقاس ويُعلَن هنا **ويُكتب نصُّه في `concurrency_note` بالصفِّ الناجي** بمُعرِّفَي الصفَّين وحصّتَيهما — فلا يُفقد صمتًا. وحسمُ أيِّ الحصّتَين هي الصحيحةُ عند مالكِ سجلِّ الحصص (التمويل · W11)',
        'المقيس: مجموعةٌ واحدةٌ — الأصل ٤ · المموِّل ٥٩ · من 2026-07-01 بصفَّين (٢٠٪ و٣٠٪). و`FLEET-09` ينصُّ على «صفٍّ واحدٍ = حصّةِ مالكٍ واحدٍ في فترةٍ واحدة»، فالحبّةُ لا تقبل صفَّين. واختراعُ مفتاحٍ يقبلهما (بإضافةِ `share_id`) يُبقي المخالفةَ ويُسمّيها بنيةً؛ والطيُّ الصامتُ يمحو نصفَ الدليل. فالطيُّ **بنصِّه** هو الحدُّ الصادق',
        $collapsed),
    array('W5-D-09', 'سجلُّ التوقُّفِ (W04) يخلط فضاءَي مفاتيحَ في عمودِ `equipment_id`',
        'يُقاس ويُعلَن ولا يُدهَس هنا: كلُّ صفٍّ يُترجَم في اشتقاقِ W05 بحسبِ **حاكمِه** لا بحسبِ عمودِه — والحاكمُ `unit_time_log` مُعرِّفُ أصلٍ حقيقيّ، والحاكمُ `timesheet` مُعرِّفُ تشغيلةٍ يعبر الجسر. وتصحيحُ العمودِ نفسِه يغيّر `occurrence_key` لصفوفٍ في مقامٍ **مُغلقٍ في W04**، فمحلُّه عند مالكِ توحيدِ `timesheet` و`unit_entries` (W10)',
        'المقيس: `ops_stop_register` ٣٧٦ صفًّا — حاكمُه `unit_time_log` ١٦٤ صفًّا بصفرِ يتيم، وحاكمُه `timesheet` ٢١٢ صفًّا **منها ١٢٨ يتيمًا**. وتعديلُ مقامٍ أغلقته مرحلةٌ سابقةٌ من داخلِ مرحلةٍ تستفيد منه هو عينُ ما منعه `_CONTEXT`. فالواجبُ: القياسُ والإعلانُ والترجمةُ عند الاستعمال',
        128),
    array('W5-D-07', 'أسطحُ دورةِ الأصلِ غيرُ المبنيّة — أتُبنى في W05؟',
        'نعم: ستّةُ أسطحٍ تُبنى وتُختَم `origin=W05` (‏RPR-PATCH-02) — طلبُ الإدخالِ ومحاضرُه · حقُّ الاستخدام · الإسنادُ والحركة · الجاهزيّةُ المشتقّة · الخروج · لوحةُ تغطيةِ القوى. وما مالكُه إدارةٌ أخرى (الحوكمةُ · التمويلُ · الرئاسة) يبقى مؤجَّلًا بموجتِه المسجَّلةِ في `wave_stage`',
        'الجدارُ الذي أجّل أسطحَ W04 (‏مقامٌ مُجمَّدٌ بثابتٍ رقميّ) رُفع خارجَ أيِّ مرحلةٍ بقرارِ مالكٍ صريح. والتأجيلُ الباقي **موضوعيٌّ لا تقنيّ**: سطحٌ مالكُه إدارةٌ أخرى يُبنى في موجتِها وإلّا ثبّتنا اسمًا لم يقرّه مالكُه',
        $gapN),
);
foreach ($DEC as $d) {
    $W("INSERT INTO repair01_w5_decisions (decision_id,question,ruling,rationale,scope_rows)
        VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "','" . $esc($d[3]) . "'," . (int) $d[4] . ")
        ON DUPLICATE KEY UPDATE question=VALUES(question), ruling=VALUES(ruling),
          rationale=VALUES(rationale), scope_rows=VALUES(scope_rows)");
}
printf("⑨ القرارات: %d مسجَّلة (W5-D-03 نطاقُه %d · W5-D-04 نطاقُه %d · W5-D-06 نطاقُه %d · W5-D-07 نطاقُه %d)\n\n",
    count($DEC), $openLive, $covVarN, $sodOrd + $sodVer, $gapN);

echo "الحكم: " . ($REPORT ? "قياسٌ تمّ (بلا كتابة) ✔\n" : "الكتابةُ تمّت ✔\n");
exit(0);
