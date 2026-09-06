<?php
/**
 * 2028_05_18_perm02_console_screens.php — تسجيلُ شاشتَي كونسولِ الصلاحيات
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-02: مديرُ الصلاحيّاتِ كان يملك **إسنادَ** قالبٍ ولا يملك **صنعَه** ولا
 * **محاسبةَ** أحدٍ عليه. فبُنيت شاشتان:
 *   `Governance/auth_profile_edit.php` — بنّاءُ القالبِ ودورةُ حياتِه
 *   `Governance/perm_audit_log.php`    — سجلُّ من غيّر ماذا لمن ومتى ولماذا
 *
 * ◆ **والتسجيلُ طبقتان لا واحدة**، وهذا لبُّ هذه الهجرة:
 *   ① **طبقةُ الملاحةِ والسجلّات** (`modules` · `nav_items` · مواضعُ الدليل
 *      والمساحة · و`role_permissions` الذي ما يزال **يُصرِّح بتصييرِ الرابط**)
 *      — كتابةُ **موضعٍ وهويّة**، تُكتب هنا مباشرةً.
 *   ② **طبقةُ الإذنِ الحاكمة** (`gov_profile_items` في قالبِ الدور 15) —
 *      **لا تُكتب هنا بصفٍّ يدويّ**. بل تمرُّ بدورةِ الحياةِ الكاملةِ عبر
 *      `PolicyWriteService`: نسخُ إصدارٍ جديدٍ ⇐ ضمُّ البندَين ⇐ اعتمادٌ ⇐
 *      تفعيلٌ ⇐ ترحيلُ الحاملين ⇐ تقاعدُ القديم.
 *
 * ⛔ **ولماذا لا يُكتب البندُ مباشرةً**: «لا تمسّ جداولَ حيّةً بصفٍّ يدويٍّ ولا
 *   بهجرةٍ لتسكيرِ بند» (PERM-01-DEC §0-3). ومنحُ قدرةٍ جديدةٍ لدورٍ **تغييرُ
 *   سياسةٍ** يستحقّ إصدارًا واعتمادًا وأثرًا — لا سطرَ `INSERT`.
 *   وهذه الهجرةُ **أوّلُ استعمالٍ حقيقيٍّ** للبابِ الذي بُني، فهي شاهدُه أيضًا.
 *
 * ⛔ **وترحيلُ الحاملِ خطوتان لا واحدة** (سحبٌ ثمَّ إسناد)، وبينهما يكون
 *   الحسابُ **بلا قالب** — والنظامُ مغلقٌ افتراضيًّا. فإن فشل الإسنادُ تُعاد
 *   المنحةُ القديمةُ فورًا، ولا يُترك أحدٌ خارجَ النظام.
 *
 * التشغيل: php database/migrations/2028_05_18_perm02_console_screens.php
 * العكس:   php database/migrations/2028_05_18_perm02_console_screens_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/permissions_helper.php';
require_once $ROOT . '/includes/perm_change_log.php';
require_once $ROOT . '/app/Services/Security/PolicyWriteService.php';
while (ob_get_level() > 0) { ob_end_clean(); }

use App\Services\Security\PolicyWriteService as PW;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = @$conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };
$str = function ($s) use ($conn) { $r = @$conn->query($s); $x = $r ? $r->fetch_row() : null; return $x ? (string) $x[0] : ''; };

$WS = 'DEP-08'; $GID = 1360; $ROLE = 15;

$SCREENS = array(
    array('code' => 'Governance/auth_profile_edit.php', 'name' => 'بناء قوالب الصلاحيات',
          'icon' => 'fa fa-sitemap', 'order' => 614, 'scr' => 'SCR-0951',
          'label' => 'بناء القوالب', 'target' => 'NT-DEP-08-044', 'row_no' => 44,
          'ws_sort' => 71, 'plc_sort' => 22, 'nav_sort' => 91,
          'perm' => array('can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1)),
    array('code' => 'Governance/perm_audit_log.php', 'name' => 'محاسبة الصلاحيات',
          'icon' => 'fa fa-clipboard-check', 'order' => 615, 'scr' => 'SCR-0952',
          'label' => 'محاسبة الصلاحيات', 'target' => 'NT-DEP-08-045', 'row_no' => 45,
          'ws_sort' => 72, 'plc_sort' => 23, 'nav_sort' => 92,
          'perm' => array('can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0)),
);

$SRC = 'PERM-02 — كونسول الصلاحيات: تأليف القالب ومحاسبته كانا بلا شاشة';
$REF = 'قياس حي: gov_profile_items بلا كاتب و perm_change_log بلا قارئ';

echo "══ PERM-02 · تسجيلُ شاشتَي الكونسول ═══════════════════════════════════\n";

/* ═══ ① الملفّاتُ موجودةٌ قبلَ تسجيلِها ═══════════════════════════════════ */
foreach ($SCREENS as $s) {
    if (!is_file($ROOT . '/' . $s['code'])) {
        exit("⛔ الملفُّ غيرُ موجود: {$s['code']} — لا يُسجَّل بابٌ بلا غرفة.\n");
    }
}
echo "   ① الملفّان موجودان.\n";

/* ═══ ② سجلُّ الوحدات — الهويّة ═══════════════════════════════════════════ */
echo "\n══ ② سجلُّ الوحدات ══════════════════════════════════════════════════\n";
foreach ($SCREENS as $i => $s) {
    $code = $conn->real_escape_string($s['code']);
    $mid = $one("SELECT id FROM modules WHERE code = '{$code}' LIMIT 1");
    if ($mid < 1) {
        $st = $conn->prepare("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                              VALUES (?,?,?, '0', 0, ?, ?)");
        $st->bind_param('ssisi', $s['name'], $s['code'], $ROLE, $s['icon'], $s['order']);
        $st->execute(); $mid = (int) $conn->insert_id; $st->close();
        printf("   + %-42s وحدة #%d\n", $s['code'], $mid);
    } else {
        printf("   = %-42s وحدة #%d قائمة\n", $s['code'], $mid);
    }
    $SCREENS[$i]['module_id'] = $mid;
}

/* ═══ ③ التصريحُ بتصييرِ الرابط ═══════════════════════════════════════════
   ◆ **و`role_permissions` ما يزال قارئَ الملاحة**: `navarch_authorized_routes()`
     تسأله، فبندٌ بلا صفٍّ فيه **لا يُصرَّح بتصييرِ رابطِه** ولو فتحه الحارس.
     فهذه كتابةُ **تصريحِ ظهورٍ** لا كتابةُ إذنٍ — والإذنُ في ⑥ بالخدمة. */
echo "\n══ ③ تصريحُ الظهورِ للدورِ {$ROLE} ═════════════════════════════════════\n";
foreach ($SCREENS as $s) {
    $mid = (int) $s['module_id'];
    if ($one("SELECT COUNT(*) FROM role_permissions WHERE role_id={$ROLE} AND module_id={$mid}") < 1) {
        $p = $s['perm'];
        $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                      VALUES ({$ROLE}, {$mid}, {$p['can_view']}, {$p['can_add']}, {$p['can_edit']}, {$p['can_delete']})");
        printf("   + %-42s صرح بالظهور\n", $s['code']);
    } else {
        printf("   = %-42s مصرح سلفا\n", $s['code']);
    }
}

/* ═══ ④ بندُ الملاحةِ للدور ══════════════════════════════════════════════ */
echo "\n══ ④ بنودُ الملاحة ═════════════════════════════════════════════════\n";
foreach ($SCREENS as $s) {
    $route = $conn->real_escape_string($s['code']);
    if ($one("SELECT COUNT(*) FROM nav_items WHERE role_id={$ROLE} AND route='{$route}'") < 1) {
        $st = $conn->prepare("INSERT INTO nav_items
              (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
              VALUES (?, 'GOV', ?, ?, ?, ?, ?, ?, ?, 1)");
        $gid5480 = 5480;
        $st->bind_param('iiisssis', $ROLE, $gid5480, $s['module_id'], $s['label'],
                        $s['code'], $s['icon'], $s['nav_sort'], $s['code']);
        $st->execute(); $st->close();
        printf("   + %-42s بند ملاحة\n", $s['code']);
    } else {
        printf("   = %-42s بند قائم\n", $s['code']);
    }
}

/* ═══ ⑤ مواضعُ الدليلِ والمساحةِ — السجلُّ الحاكمُ للتصيير ══════════════ */
echo "\n══ ⑤ المواضع ═══════════════════════════════════════════════════════\n";
foreach ($SCREENS as $s) {
    $lower   = strtolower(str_replace('.php', '', $s['code']));
    $withPhp = strtolower($s['code']);
    $tref    = $WS . '·' . $s['row_no'] . '·' . $s['label'];

    if ($one("SELECT COUNT(*) FROM nav_targets WHERE target_id='" . $conn->real_escape_string($s['target']) . "'") < 1) {
        $gk = 'الادوار والصلاحيات';
        $st = $conn->prepare("INSERT INTO nav_targets
              (target_id, source_doc, sheet_code, row_no, canonical_title,
               workspace_id, group_key, target_order, visibility_class, active)
              VALUES (?,?,?,?,?,?,?,?,'MENU_ITEM',1)");
        $st->bind_param('sssisssi', $s['target'], $SRC, $WS, $s['row_no'], $s['label'],
                        $WS, $gk, $s['row_no']);
        $st->execute(); $st->close();
        printf("   + nav_targets %s\n", $s['target']);
    }
    if ($one("SELECT COUNT(*) FROM nav_placements WHERE route='" . $conn->real_escape_string($withPhp) . "'") < 1) {
        $st = $conn->prepare("INSERT INTO nav_placements
              (workspace_id, screen_id, route, target_ref, target_id, group_id,
               sort_no, placement_type, source_ref, active)
              VALUES (?,?,?,?,?,?,?,'MENU_ITEM',?,1)");
        $st->bind_param('sssssiis', $WS, $s['scr'], $withPhp, $tref, $s['target'],
                        $GID, $s['plc_sort'], $REF);
        $st->execute(); $st->close();
        printf("   + nav_placements %s\n", $withPhp);
    }
    if ($one("SELECT COUNT(*) FROM nav_workspace_placements WHERE route='" . $conn->real_escape_string($lower) . "'") < 1) {
        $pid = 'WP-' . strtoupper(substr(md5($WS . '|' . $lower), 0, 16));
        $st = $conn->prepare("INSERT INTO nav_workspace_placements
              (placement_id, screen_id, workspace_id, group_id, placement_type, sort_no,
               route, canonical_label, governing_source, source_ref, reason_code,
               effective_from, status, version, created_by, legacy_ref, created_at)
              VALUES (?,?,?,?,'PRIMARY',?,?,?,?,?,'GUIDE_OWNED_LIFECYCLE_S9',
                      CURDATE(),'ACTIVE',1,?,?,NOW())");
        $by = 'migrations/2028_05_18_perm02_console_screens';
        $st->bind_param('sssiisssssi', $pid, $s['scr'], $WS, $GID, $s['ws_sort'],
                        $lower, $s['label'], $SRC, $REF, $by, $GID);
        $st->execute(); $st->close();
        printf("   + nav_workspace_placements %s\n", $lower);
    }
}

/* ═══ ⑥ الإذنُ الحاكم — بدورةِ الحياةِ لا بصفٍّ يدويّ ═══════════════════ */
echo "\n══ ⑥ الإذنُ الحاكمُ عبرَ `PolicyWriteService` ══════════════════════════\n";

$adminId = $one("SELECT id FROM users WHERE role='15' AND is_deleted=0 AND status='active' AND company_id=4 ORDER BY id LIMIT 1");
if ($adminId < 1) { exit("⛔ لا حساب حي بالدور 15 — لا يُنفَّذ ترحيلُ قالبٍ بلا صاحبِه.\n"); }
$_SESSION['user'] = array('id' => $adminId, 'role' => '15', 'company_id' => 4, 'name' => 'perm02 migration');

$OLD = $one("SELECT pr.profile_id FROM gov_role_profiles pr
              JOIN gov_authority_grants g ON g.profile_id = pr.profile_id AND g.revoked_at IS NULL
              JOIN users u ON u.id = g.user_id AND u.role = '15'
             WHERE pr.state = 'active' LIMIT 1");
if ($OLD < 1) { exit("⛔ لا قالب نافذ لحاملي الدور 15.\n"); }
$oldCode = $str("SELECT profile_code FROM gov_role_profiles WHERE profile_id = {$OLD}");
printf("   القالبُ الحالي: %s (#%d)\n", $oldCode, $OLD);

$already = $one("SELECT COUNT(*) FROM gov_profile_items
                  WHERE profile_id = {$OLD} AND item_ref = 'Governance/auth_profile_edit.php' AND allow = 1");
if ($already > 0) {
    echo "   = القالبُ الحاليُّ يحوي الشاشتَين سلفًا — لا حاجةَ لإصدارٍ جديد.\n";
} else {
    /* البوّاباتُ تُرفع مُعلَنةً وتُعاد إلى حالِها الأوّلِ في النهاية. */
    $wasG = $one("SELECT active FROM gov_policy_freeze WHERE scope_code='grants'");
    $wasA = $one("SELECT active FROM gov_policy_freeze WHERE scope_code='profile_activation'");
    $R = 'PERM-02: كونسول الصلاحيات — تأليف القالب ومحاسبته';
    if ($wasA) { PW::setFreeze($conn, PW::FREEZE_ACTIVATION, false, $R, $adminId); }
    if ($wasG) { PW::setFreeze($conn, PW::FREEZE_GRANTS, false, $R, $adminId); }

    $newCode = substr($oldCode, 0, 14) . '-V2';
    $i = 2;
    while ($one("SELECT COUNT(*) FROM gov_role_profiles WHERE profile_code='" . $conn->real_escape_string($newCode) . "'") > 0) {
        $i++; $newCode = substr($oldCode, 0, 14) . '-V' . $i;
    }

    $r = PW::cloneProfile($conn, $OLD, $newCode, $R, $adminId);
    if (empty($r['ok'])) { exit("⛔ تعذّر النسخ: {$r['msg']}\n"); }
    $NEW = (int) $r['id'];
    printf("   ⓐ نُسخ إصدارًا جديدًا: %s (#%d)\n", $newCode, $NEW);

    foreach ($SCREENS as $s) {
        $r = PW::setProfileItem($conn, $NEW, $s['code'], array(
            'allow' => 1, 'can_add' => $s['perm']['can_add'],
            'can_edit' => $s['perm']['can_edit'], 'can_delete' => $s['perm']['can_delete']), $R, $adminId);
        if (empty($r['ok'])) { exit("⛔ تعذّر ضمُّ {$s['code']}: {$r['msg']}\n"); }
        printf("   ⓑ ضُمّ %s\n", $s['code']);
    }

    $r = PW::approveProfile($conn, $NEW, $R, $adminId);
    if (empty($r['ok'])) { exit("⛔ تعذّر الاعتماد: {$r['msg']}\n"); }
    echo "   ⓒ اعتُمد الإصدارُ الجديد.\n";

    $r = PW::activateProfile($conn, $NEW, $R, $adminId);
    if (empty($r['ok'])) { exit("⛔ تعذّر التفعيل: {$r['msg']}\n"); }
    echo "   ⓓ نفذ الإصدارُ الجديد.\n";

    /* ⓔ ترحيلُ الحاملين — واحدًا واحدًا، ولا يُترك أحدٌ بلا قالب. */
    $holders = array();
    $q = $conn->query("SELECT grant_id, user_id FROM gov_authority_grants
                        WHERE profile_id = {$OLD} AND revoked_at IS NULL");
    while ($q && ($x = $q->fetch_assoc())) { $holders[] = $x; }
    foreach ($holders as $hg) {
        $gid = (int) $hg['grant_id']; $huid = (int) $hg['user_id'];
        $r = PW::revokeGrant($conn, $gid, $R . ' (ترحيل إلى ' . $newCode . ')', $adminId);
        if (empty($r['ok'])) { exit("⛔ تعذّر سحبُ المنحة #{$gid}: {$r['msg']}\n"); }
        $r = PW::assignProfile($conn, $huid, $NEW, $R, $adminId);
        if (empty($r['ok'])) {
            /* ⛔ الاستعادةُ فورًا — النظامُ مغلقٌ افتراضيًّا فلا يُترك حسابٌ خارجَه. */
            $conn->query("UPDATE gov_authority_grants SET revoked_at = NULL WHERE grant_id = {$gid}");
            exit("⛔ تعذّر الإسنادُ للمستخدم #{$huid}: {$r['msg']} — أُعيدت المنحةُ القديمة.\n");
        }
        printf("   ⓔ رُحّل المستخدم #%d إلى %s\n", $huid, $newCode);
    }

    $r = PW::retireProfile($conn, $OLD, $R . ' (خلفه ' . $newCode . ')', $adminId);
    printf("   ⓕ %s\n", !empty($r['ok']) ? ('تقاعد ' . $oldCode) : ('لم يتقاعد ' . $oldCode . ': ' . $r['msg']));

    if ($wasA) { PW::setFreeze($conn, PW::FREEZE_ACTIVATION, true, $R . ' (إعادة بعد الترحيل)', $adminId); }
    if ($wasG) { PW::setFreeze($conn, PW::FREEZE_GRANTS, true, $R . ' (إعادة بعد الترحيل)', $adminId); }
    echo "   ⓖ أُعيدت البوّاباتُ إلى حالِها الأوّل.\n";
}

/* ═══ ⑦ الشاهد — بالحكمِ الحيِّ وبالمُصيَّرِ لا بالمخزن ═══════════════════ */
echo "\n══ ⑦ الشاهد ═══════════════════════════════════════════════════════\n";
$fail = 0;
foreach ($SCREENS as $s) {
    $t = ems_permission_trace($conn, (int) $s['module_id'], $adminId);
    $v = !empty($t['perms']['can_view']);
    if (!$v) { $fail++; }
    printf("   الحارس  %-42s %s\n", $s['code'], $v ? 'يفتح' : 'يمنع ⛔');
}
require_once $ROOT . '/includes/navarch_renderer.php';
$tree = navarch_render($conn, $WS, $ROLE, array('include_shell' => false));
$routes = array();
foreach ($tree['groups'] as $g) { foreach ($g['items'] as $it) { $routes[$it['route']] = $g['label']; } }
foreach ($SCREENS as $s) {
    $r = strtolower(str_replace('.php', '', $s['code']));
    $seen = isset($routes[$r]);
    if (!$seen) { $fail++; }
    printf("   الرابط  %-42s %s%s\n", $r, $seen ? 'يظهر' : 'غائب ⛔',
           $seen ? ' تحت: ' . $routes[$r] : '');
}
/* الضابطُ السالب: دورٌ آخرُ لا يراهما ولا يفتحهما. */
$other = $one("SELECT id FROM users WHERE role='18' AND is_deleted=0 AND status='active' AND company_id=4 LIMIT 1");
$leak = 0;
if ($other > 0) {
    foreach ($SCREENS as $s) {
        $t = ems_permission_trace($conn, (int) $s['module_id'], $other);
        if (!empty($t['perms']['can_view'])) { $leak++; }
    }
}
printf("   الضابطُ السالب — دورٌ آخر يفتحهما: %d (المستهدف صفر)\n", $leak);
if ($leak > 0) { $fail++; }

if ($fail > 0) { exit("\n⛔ الشاهدُ لم يتحقّق ({$fail}) — راجعْ قبلَ الالتزام.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — والإذنُ مرّ بدورةِ الحياةِ لا بصفٍّ يدويّ.\n";
