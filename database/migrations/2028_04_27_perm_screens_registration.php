<?php
/**
 * 2028_04_27_perm_screens_registration.php — تسجيلُ عشرِ شاشاتِ الصلاحياتِ في دور 15
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الوصفةُ مقيسةٌ لا مُخترَعة**: أُخذت شاشةٌ تظهر فعلًا لدور 15
 *   (`Governance/policies.php`) وعُدَّ كلُّ صفٍّ يذكر مسارَها، فظهر أنّ البندَ
 *   الظاهرَ يحتاج **عشرةَ سجلّاتٍ لا أربعة**.
 *
 * ◆ **والسجلُّ الحاكمُ للتصييرِ `nav_workspace_placements`** لا `nav_items`:
 *   `navarch_render()` يأخذ البنودَ منه (`status='ACTIVE'`) و`nav_items` تُرشِّح
 *   بالصلاحيةِ لا تُنشئ موضعًا. وقياسي الأوّلُ أعماه أنّ **صيغةَ المسارِ فيه
 *   تخالف كلَّ الجداول**: صغيرةٌ وبلا `.php` (`governance/policies`).
 *
 * ◆ **وحقولُ البرومبت خالفت المخطَّط** فأُخذ المخطَّط: `nav_items` عمودُها
 *   `label_ar`/`route` لا `label`/`link`، و`screen_about` عمودُها `screen_path`
 *   و`description` لا `screen_code`/`description_ar`.
 *
 * ◆ **ومجموعةُ الدليلِ لا تُخترَع**: `nav_placements.group_id` ترقيمُ دليلٍ
 *   (49..54 لـDEP-08) والقسمةُ قرارُ مالكٍ لا اجتهادُ مبرمج — فتُوضَع الشاشاتُ
 *   في **52** حيثُ تسكن أصلًا `settings/roles.php` و`guards.php` و
 *   `sod_conflicts.php` و`perm_explain.php` (عنقودُ الأدوارِ والصلاحيات).
 *   والمجموعةُ الجديدةُ في `link_groups` وحدَها — وهي مجموعةُ **السايدبار**.
 *
 * ◆ **والأيقونةُ بأسلوبِ البيت**: `fa fa-…` (2,700 صفًّا) لا `fas fa-…` (4).
 *
 * ⛔ الهجرةُ **مُعادةُ التشغيل**: كلُّ إدراجٍ مسبوقٌ بفحصِ وجود.
 * ⛔ و`gov_space_appearances.id` و`nav_placements.id` ليسا AUTO_INCREMENT —
 *   يُشتقّان من `MAX(id)+1` صراحةً.
 *
 * ◆ **والسجلُّ التاسعُ لم يظهر في المسحِ الأوّل**: `nav_targets` مرجعُ
 *   `fk_np_target`، ولا يُفهرَس بمسارٍ بل بـ`target_id` — فمسحُ أعمدةِ
 *   المساراتِ أعماه. ظهر بالقياسِ الحيِّ حين رسب الإدراجُ بمفتاحٍ أجنبيّ.
 * ⛔ **و`source_doc` فيه يقول الحقيقة**: هذه الشاشاتُ من هذه الجولةِ، لا
 *   صفوفًا في «01 · الدليل المعماري.xlsx» — فادّعاءُ ورقةِ المالكِ تزوير.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
$t0 = microtime(true);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail: " . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

const PERM_ROLE   = 15;
const PERM_WS     = 'DEP-08';       // مساحةُ عملِ الدور 15 (nav_ws_roles)
const PERM_GGROUP = 52;             // مجموعةُ الدليلِ — عنقودُ الأدوارِ والصلاحيات
const PERM_DOOR   = 'SET';          // بابُ الإعدادات (مثلُ Governance/policies.php)

/** الشاشاتُ العشر — التاسعةُ الأخيرةُ واجهةٌ، والعاشرةُ مُعالِجٌ بلا ملاحة. */
$SCREENS = array(
    array('route' => 'Governance/perm_dashboard.php',     'label' => 'لوحة الصلاحيات',    'icon' => 'fa fa-th-large',    'nav' => true),
    array('route' => 'Governance/perm_roles.php',         'label' => 'الأدوار',           'icon' => 'fa fa-user-tag',    'nav' => true),
    array('route' => 'Governance/perm_modules.php',       'label' => 'الوحدات والشاشات',  'icon' => 'fa fa-cubes',       'nav' => true),
    array('route' => 'Governance/perm_matrix.php',        'label' => 'مصفوفة الصلاحيات',  'icon' => 'fa fa-table',       'nav' => true),
    array('route' => 'Governance/perm_link_groups.php',   'label' => 'مجموعات السايدبار', 'icon' => 'fa fa-layer-group', 'nav' => true),
    array('route' => 'Governance/perm_nav_items.php',     'label' => 'عناصر الملاحة',     'icon' => 'fa fa-compass',     'nav' => true),
    array('route' => 'Governance/perm_screen_guide.php',  'label' => 'دليل الشاشات',      'icon' => 'fa fa-book',        'nav' => true),
    array('route' => 'Governance/perm_reports.php',       'label' => 'صلاحيات التقارير',  'icon' => 'fa fa-file-shield', 'nav' => true),
    array('route' => 'Governance/perm_system_status.php', 'label' => 'حالة النظام',       'icon' => 'fa fa-heartbeat',   'nav' => true),
    /* ⛔ مُعالِجُ AJAX: يُسجَّل في `modules` و`role_permissions` **فقط** — حارسُه
       يسأل عن الصلاحيةِ بالمسار، ولا رابطَ له في قائمةٍ ولا موضعَ في دليل. */
    array('route' => 'Governance/perm_quick_update.php',  'label' => 'تحديث صلاحية سريع', 'icon' => 'fa fa-bolt',        'nav' => false),
);

$DESC = array(
    'Governance/perm_dashboard.php'     => 'لوحةُ توجيهٍ لمنظومةِ الصلاحيات: بطاقاتُ إحصاءٍ حيّةٍ للأدوارِ والوحداتِ والصلاحياتِ الممنوحةِ ومجموعاتِ السايدبارِ وبنودِ الملاحة، وكلُّ بطاقةٍ مدخلٌ إلى شاشتِها.',
    'Governance/perm_roles.php'         => 'سجلُّ الأدوارِ في النظام: الاسمُ والدورُ الأبُ والمستوى ونطاقُ الدورِ وحالتُه. منه تُضاف الأدوارُ وتُعدَّل وتُعطَّل.',
    'Governance/perm_modules.php'       => 'سجلُّ الوحداتِ (الشاشات): الاسمُ والكودُ (مسارُ الشاشة) والدورُ المالكُ والأيقونةُ وترتيبُ العرض. الكودُ هنا هو مفتاحُ الصلاحيةِ الذي تسأل عنه كلُّ شاشة.',
    'Governance/perm_matrix.php'        => 'مصفوفةُ الصلاحيات: صفٌّ لكلِّ وحدةٍ وأربعةُ أعمدةٍ (عرض · إضافة · تعديل · حذف) لدورٍ يُختار. الحفظُ يكتب في `role_permissions`.',
    'Governance/perm_link_groups.php'   => 'مجموعاتُ روابطِ السايدبار: الاسمُ والدورُ المالكُ والأيقونةُ وترتيبُ الظهورِ وحالةُ التفعيل. المجموعةُ وعاءُ بنودِ الملاحة.',
    'Governance/perm_nav_items.php'     => 'بنودُ الملاحة: الدورُ والمجموعةُ والبابُ والتسميةُ والمسارُ والأيقونةُ والترتيبُ وحالةُ التفعيل. هذا ما يُصيَّر في السايدبار.',
    'Governance/perm_screen_guide.php'  => 'دليلُ الشاشات: عنوانُ كلِّ شاشةٍ ووصفُها الظاهرُ في بطاقةِ «عن الشاشة». السجلاتُ تُنشأ آليًّا وتُحرَّر هنا.',
    'Governance/perm_reports.php'       => 'صلاحياتُ التقارير: مصفوفةُ (دورٌ × تقرير) — الصفُّ الموجودُ في `report_role_permissions` يعني السماح، وحذفُه يعني المنع.',
    'Governance/perm_system_status.php' => 'حالةُ منظومةِ الصلاحيات: مؤشراتُ التغطيةِ والأدوارِ بلا صلاحياتٍ والوحداتِ بلا ربطٍ والبنودِ اليتيمة — لوحةُ مراقبةٍ للقراءةِ وحدَها.',
    'Governance/perm_quick_update.php'  => 'مُعالِجُ تحديثٍ سريعٍ لصلاحياتِ التقارير — نقطةُ نهايةٍ تُستدعى من شاشةِ صلاحياتِ التقاريرِ وتُرجع JSON.',
);

$SRC = 'PERM-SCR-01 · إعادةُ بناءِ شاشاتِ الإدارةِ العليا داخلَ دور 15';

function one($conn, $sql, $types = '', $args = array()) {
    $st = $conn->prepare($sql);
    if (!$st) { echo "  !! prepare: " . $conn->error . "\n  SQL: $sql\n"; return false; }
    if ($types !== '') { $st->bind_param($types, ...$args); }
    $ok = $st->execute();
    if (!$ok) { echo "  !! execute: " . $st->error . "\n"; }
    $st->close();
    return $ok;
}
function scalar($conn, $sql) {
    $r = $conn->query($sql);
    return $r ? $r->fetch_row()[0] : null;
}

/* ═══ ① مجموعةُ السايدبارِ الجديدةُ لدور 15 ═══════════════════════════════ */
$GROUP_NAME = 'إدارة الصلاحيات والأدوار';
$gid = scalar($conn, "SELECT id FROM link_groups WHERE name = '" . $conn->real_escape_string($GROUP_NAME)
    . "' AND owner_role_id = " . PERM_ROLE . " LIMIT 1");
if (!$gid) {
    /* الترتيبُ 34 — تِلوَ «الهيكل والأدوار» (33) بنصِّ الطلب. */
    one($conn, "INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, is_active)
                VALUES (?, 'perm_admin_r15', " . PERM_ROLE . ", 'fa fa-user-shield', 34, 1)", 's', array($GROUP_NAME));
    $gid = $conn->insert_id;
    echo "+ link_groups: «$GROUP_NAME» = $gid\n";
} else {
    echo "= link_groups: «$GROUP_NAME» موجودةٌ سلفًا = $gid\n";
}

/* ═══ ② لكلِّ شاشةٍ عشرةُ سجلّات ═══════════════════════════════════════ */
/* ⛔ **المقطعُ الأخيرُ لا كلُّ الأرقام**: `preg_replace('/\D/','')` على
   `NT-DEP-08-032` يعطي «08032» فيقفز الترقيمُ إلى 8033. */
$lastSeg = function ($s) { $p = explode('-', (string) $s); return (int) end($p); };
$scrNo = $lastSeg(scalar($conn, "SELECT MAX(screen_id) FROM nav_placements WHERE screen_id LIKE 'SCR-%'"));
$ntNo  = $lastSeg(scalar($conn, "SELECT MAX(target_id) FROM nav_targets WHERE workspace_id = '" . PERM_WS . "'"));
$rowNo = (int) scalar($conn, "SELECT COALESCE(MAX(row_no),0) FROM nav_targets WHERE workspace_id = '" . PERM_WS . "'");
$sortNo = (int) scalar($conn, "SELECT COALESCE(MAX(sort_no),0) FROM nav_placements WHERE workspace_id = '" . PERM_WS . "' AND group_id = " . PERM_GGROUP);
$navSort = (int) scalar($conn, "SELECT COALESCE(MAX(sort_order),0) FROM nav_items WHERE role_id = " . PERM_ROLE);

/* ⛔ **يُعَدُّ النجاحُ لا المحاولة**: عدّادٌ يزيد قبلَ التحقّقِ من نتيجةِ
   `execute()` أخرج «nav_placements 9» وكلُّ التسعِ رسبت بمفتاحٍ أجنبيّ. */
$made = array('nav_targets' => 0, 'modules' => 0, 'role_permissions' => 0, 'nav_items' => 0,
              'nav_placements' => 0, 'nav_workspace_placements' => 0, 'nav_canonical' => 0,
              'nav_route_group' => 0, 'gov_space_appearances' => 0, 'screen_about' => 0);
$failed = 0;

foreach ($SCREENS as $i => $s) {
    $route = $s['route'];
    $label = $s['label'];
    $esc   = $conn->real_escape_string($route);

    /* ── ⑴ modules — الكودُ هو مفتاحُ الصلاحية ── */
    $mid = scalar($conn, "SELECT id FROM modules WHERE code = '$esc' LIMIT 1");
    if (!$mid) {
        one($conn, "INSERT INTO modules (name, code, owner_role_id, group_id, is_link, is_quick, icon, display_order, owner_dept_note)
                    VALUES (?, ?, " . PERM_ROLE . ", NULL, '0', 0, ?, ?, '" . PERM_WS . "')",
            'sssi', array($label, $route, $s['icon'], $i + 1));
        $mid = $conn->insert_id; $made['modules']++;
    }

    /* ── ⑵ role_permissions — الدورُ 15 يملك الأربعة (والمُعالِجُ كتابةً فقط منطقيًّا) ── */
    if (!scalar($conn, "SELECT id FROM role_permissions WHERE role_id = " . PERM_ROLE . " AND module_id = " . (int) $mid)) {
        if (one($conn, "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                    VALUES (" . PERM_ROLE . ", " . (int) $mid . ", 1, 1, 1, 1)")) { $made['role_permissions']++; } else { $failed++; }
    }

    /* ── ⑶ screen_about — بطاقةُ «عن الشاشة» ── */
    if (!scalar($conn, "SELECT id FROM screen_about WHERE screen_path = '$esc' LIMIT 1")) {
        if (one($conn, "INSERT INTO screen_about (screen_path, title_ar, description, source, active, created_at)
                    VALUES (?, ?, ?, 'authored', 1, NOW())",
            'sss', array($route, $label, $DESC[$route]))) { $made['screen_about']++; } else { $failed++; }
    }

    if (empty($s['nav'])) {
        echo "· $route — مُعالِجٌ: modules + role_permissions فقط\n";
        continue;
    }

    $lower = strtolower($route);

    /* ⛔ **الهويّةُ تُستردُّ لا تُولَّد في كلِّ تشغيل**: فحصُ الوجودِ على معرِّفٍ
       مُولَّدٍ من `MAX+1` يجعل كلَّ إعادةِ تشغيلٍ تخلق هدفًا جديدًا — قِيس:
       `nav_targets` صار 19 بدل 9. فالمفتاحُ **الشاشةُ** لا الرقم. */
    $prev = $conn->query("SELECT screen_id, target_id FROM nav_placements
                           WHERE route = '" . $conn->real_escape_string($lower) . "' LIMIT 1");
    $prevRow = $prev ? $prev->fetch_assoc() : null;
    if ($prevRow) {
        $screenId = (string) $prevRow['screen_id'];
        $targetId = (string) $prevRow['target_id'];
        $sortNo++; $navSort++;
    } else {
        $scrNo++; $ntNo++; $sortNo++; $navSort++; $rowNo++;
        $screenId = 'SCR-' . str_pad((string) $scrNo, 4, '0', STR_PAD_LEFT);
        $targetId = 'NT-' . PERM_WS . '-' . str_pad((string) $ntNo, 3, '0', STR_PAD_LEFT);
    }

    /* ── ⑸ nav_targets — مرجعُ `fk_np_target`. سجلٌّ تاسعٌ لم يظهر في مسحِ
       الأعمدةِ لأنّه يُفهرَس بـ`target_id` لا بمسار.
       ⛔ **و`source_doc` يقول الحقيقةَ**: هذه أسطحُ إدارةٍ أضافتها هذه الجولةُ،
       وليست صفوفًا في «01 · الدليل المعماري.xlsx» — فادّعاءُ الدليلِ تزوير. */
    if (!scalar($conn, "SELECT COUNT(*) FROM nav_targets WHERE target_id = '" . $conn->real_escape_string($targetId) . "'")) {
        if (one($conn, "INSERT INTO nav_targets (target_id, source_doc, sheet_code, row_no, canonical_title,
                          workspace_id, group_key, target_order, visibility_class, active)
                        VALUES (?, ?, '" . PERM_WS . "', ?, ?, '" . PERM_WS . "', 'الادوار والصلاحيات', ?, 'MENU_ITEM', 1)",
            'ssisi', array($targetId, $SRC, $rowNo, $label, $rowNo))) { $made['nav_targets']++; } else { $failed++; }
    }

    /* ── ⑷ nav_items — بندُ السايدبارِ المُصيَّر ── */
    if (!scalar($conn, "SELECT id FROM nav_items WHERE role_id = " . PERM_ROLE . " AND route = '$esc' LIMIT 1")) {
        if (one($conn, "INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active, created_at, updated_at)
                    VALUES (" . PERM_ROLE . ", '" . PERM_DOOR . "', " . (int) $gid . ", " . (int) $mid . ", ?, ?, ?, ?, ?, 1, NOW(), NOW())",
            'sssis', array($label, $route, $s['icon'], $i + 1, $route))) { $made['nav_items']++; } else { $failed++; }
    }

    /* ── ⑸ nav_placements — ورقةُ الدليل. ⛔ المسارُ هنا **بحروفٍ صغيرة** ── */
    if (!scalar($conn, "SELECT id FROM nav_placements WHERE workspace_id = '" . PERM_WS . "' AND route = '" . $conn->real_escape_string($lower) . "' LIMIT 1")) {
        $pid = (int) scalar($conn, "SELECT COALESCE(MAX(id),0)+1 FROM nav_placements");
        if (one($conn, "INSERT INTO nav_placements (id, workspace_id, screen_id, route, target_ref, target_id, group_id, sort_no, placement_type, source_ref, active)
                    VALUES (?, '" . PERM_WS . "', ?, ?, ?, ?, " . PERM_GGROUP . ", ?, 'MENU_ITEM', ?, 1)",
            'issssis', array($pid, $screenId, $lower, PERM_WS . '·' . $ntNo . '·' . $label, $targetId, $sortNo, $SRC))) { $made['nav_placements']++; } else { $failed++; }
    }

    /* ── ⑹ nav_workspace_placements — **السجلُّ الحاكمُ للتصيير** ────────────
       `includes/navarch_renderer.php::navarch_render()` يأخذ البنودَ من هنا
       حصرًا (`status='ACTIVE'`)، و`nav_items` تُرشِّح بالصلاحيةِ لا تُنشئ موضعًا.
       ⛔ **وصيغةُ المسارِ هنا تخالف كلَّ الجداولِ الأخرى**: صغيرةٌ **وبلا `.php`**
       (`governance/policies`) — ولهذا أعمى قياسي الأوّلُ هذا السجلَّ حين بحث
       عن `Governance/policies.php`. والتطبيعُ في `navarch_norm_route()`. */
    $navrRoute = strtolower(preg_replace('/\.php$/', '', $route));
    if (!scalar($conn, "SELECT COUNT(*) FROM nav_workspace_placements
                         WHERE workspace_id = '" . PERM_WS . "' AND route = '" . $conn->real_escape_string($navrRoute) . "'")) {
        $wpId = 'WP-' . strtoupper(bin2hex(random_bytes(8)));
        $wpSort = (int) scalar($conn, "SELECT COALESCE(MAX(sort_no),0)+1 FROM nav_workspace_placements WHERE workspace_id = '" . PERM_WS . "'");
        if (one($conn, "INSERT INTO nav_workspace_placements
                         (placement_id, screen_id, workspace_id, group_id, placement_type, sort_no, route,
                          canonical_label, governing_source, source_ref, reason_code, effective_from,
                          status, version, created_by, created_at)
                        VALUES (?, ?, '" . PERM_WS . "', " . PERM_GGROUP . ", 'PRIMARY', ?, ?, ?,
                                'NAV-ARCH-02 §9 — الشاشةُ جزءٌ أصيلٌ من دورةِ المساحةِ المالكة',
                                ?, 'GUIDE_OWNED_LIFECYCLE_S9', CURDATE(), 'ACTIVE', 1, 'PERM-SCR-01', NOW())",
            'ssisss', array($wpId, $screenId, $wpSort, $navrRoute, $label, $SRC))) {
            $made['nav_workspace_placements']++;
        } else { $failed++; }
    }

    /* ── ⑺ nav_canonical — الاسمُ المعتمَدُ وموضعُه ── */
    if (!scalar($conn, "SELECT id FROM nav_canonical WHERE route = '$esc' LIMIT 1")) {
        if (one($conn, "INSERT INTO nav_canonical
                     (route, canonical_ar, level_no, level_name, group_name, sort_no, status, decision_state,
                      application_state, provisional_reversible, policy_domain, derivation, retirement_status,
                      placement_kind, space_class, screen_id, created_at, decision_source)
                    VALUES (?, ?, 2, 'العمليات', ?, ?, 'APPROVED', 'APPROVED', 'DEPLOYED', 1,
                            'NAVIGATION_NAMING_POSITION', ?, 'ACTIVE', 'SINGLE', '', ?, NOW(), ?)",
            'sssisss', array($route, $label, $GROUP_NAME, $i + 1, $SRC, $screenId, $SRC))) { $made['nav_canonical']++; } else { $failed++; }
    }

    /* ── ⑺ nav_route_group — رأسُ الطيِّ بالمسارِ وحدَه ── */
    if (!scalar($conn, "SELECT COUNT(*) FROM nav_route_group WHERE route = '$esc'")) {
        if (one($conn, "INSERT INTO nav_route_group (route, group_code, basis, updated_at) VALUES (?, 'GOVERNANCE', ?, NOW())",
            'ss', array($route, $SRC))) { $made['nav_route_group']++; } else { $failed++; }
    }

    /* ── ⑻ gov_space_appearances — حارسُ الوصولِ بالمسار. ⛔ id ليس AUTO ── */
    if (!scalar($conn, "SELECT COUNT(*) FROM gov_space_appearances WHERE route = '$esc'")) {
        $aid = (int) scalar($conn, "SELECT COALESCE(MAX(id),0)+1 FROM gov_space_appearances");
        if (one($conn, "INSERT INTO gov_space_appearances
                     (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
                      src_class, src_ownership, src_decision, src_note, spaces_count,
                      cls, ownership, decision, basis, rule_step, view_fields, updated_at)
                    VALUES (?, 'إدارة الصلاحيات', 'CONTROL', '', ?, ?, 'إدارة الصلاحيات', 'PLATFORM_SHARED',
                            'PERM-SCR-01', 'VALID', 'CONFIRMED', ?, 1,
                            'OWNED', 'VALID', 'CONFIRMED', ?, 1, '', NOW())",
            'issss', array($aid, $label, $route, $SRC, 'الشاشةُ سطحُ إدارةِ الصلاحياتِ ومالكُها الدورُ 15'))) { $made['gov_space_appearances']++; } else { $failed++; }
    }

    echo "+ $route — $screenId · $targetId\n";
}

echo "\n── ما أُنشئ (نجاحًا لا محاولةً) ──\n";
foreach ($made as $t => $n) { printf("   %-24s %d\n", $t, $n); }
printf("   %-24s %d\n", '** رسب **', $failed);

/* ── الحكمُ بالقياسِ لا بالعدّاد: يُعاد فحصُ ما في القاعدةِ فعلًا ── */
echo "\n── المقيسُ في القاعدةِ الآن (باستثناءِ perm_explain السابقِ للجولة) ──\n";
$verify = array(
    'modules'               => "SELECT COUNT(*) FROM modules WHERE code LIKE 'Governance/perm\_%' AND code <> 'Governance/perm_explain.php'",
    'role_permissions'      => "SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id WHERE rp.role_id = " . PERM_ROLE . " AND m.code LIKE 'Governance/perm\_%' AND m.code <> 'Governance/perm_explain.php'",
    'screen_about'          => "SELECT COUNT(*) FROM screen_about WHERE screen_path LIKE 'Governance/perm\_%' AND screen_path <> 'Governance/perm_explain.php'",
    'nav_items'             => "SELECT COUNT(*) FROM nav_items WHERE role_id = " . PERM_ROLE . " AND route LIKE 'Governance/perm\_%' AND route <> 'Governance/perm_explain.php'",
    'nav_targets'           => "SELECT COUNT(*) FROM nav_targets WHERE source_doc LIKE 'PERM-SCR-01%'",
    'nav_placements'        => "SELECT COUNT(*) FROM nav_placements WHERE route LIKE 'governance/perm\_%' AND route <> 'governance/perm_explain.php'",
    /* ⛔ صيغةُ المسارِ هنا صغيرةٌ وبلا `.php` — ولا تُقاس بصيغةِ الجداولِ الأخرى. */
    'nav_workspace_placements' => "SELECT COUNT(*) FROM nav_workspace_placements WHERE workspace_id = '" . PERM_WS . "' AND route LIKE 'governance/perm\_%' AND route <> 'governance/perm_explain'",
    'nav_canonical'         => "SELECT COUNT(*) FROM nav_canonical WHERE route LIKE 'Governance/perm\_%' AND route <> 'Governance/perm_explain.php'",
    'nav_route_group'       => "SELECT COUNT(*) FROM nav_route_group WHERE route LIKE 'Governance/perm\_%' AND route <> 'Governance/perm_explain.php'",
    'gov_space_appearances' => "SELECT COUNT(*) FROM gov_space_appearances WHERE route LIKE 'Governance/perm\_%' AND route <> 'Governance/perm_explain.php'",
);
$bad = 0;
foreach ($verify as $t => $sql) {
    $got  = (int) scalar($conn, $sql);
    $want = in_array($t, array('modules', 'role_permissions', 'screen_about'), true) ? 10 : 9;
    if ($got !== $want) { $bad++; }
    printf("   %-24s %2d / %2d  %s\n", $t, $got, $want, $got === $want ? 'PASS' : 'FAIL');
}
echo "\n" . ($bad === 0 && $failed === 0 ? "✔ التسجيلُ تامٌّ — عشرةُ سجلّاتٍ لكلِّ شاشة\n" : "✘ ناقصٌ: $bad مقياسًا · $failed إدراجًا راسبًا\n");

/* ⛔ الدفترُ يكتبه **المُشغِّلُ** لا الهجرة (`checksum`/`status`/`applied_at`
   حقولٌ لا تملكها الهجرةُ) — والدالّةُ يعرّفها `migrate.php` عند التضمين. */
if (function_exists('ems_migration_recorded')) {
    ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
} else {
    echo "◆ شُغِّلت خارجَ المُشغِّل — لا قيدَ في الدفتر (أعِدْ عبر `php database/migrate.php up`).\n";
}
