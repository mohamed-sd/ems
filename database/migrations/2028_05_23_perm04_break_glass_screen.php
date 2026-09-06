<?php
/**
 * 2028_05_23_perm04_break_glass_screen.php — بابُ الطوارئِ يُبلَغ من الواجهة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **العلّةُ المقيسة**: `PolicyWriteService::openException()` مبنيٌّ ومحروسٌ
 *   بخمسةِ قيودٍ ومُختبَرٌ بواحدٍ وعشرين تأكيدًا — و**صفرُ شاشةٍ تناديه**.
 *   والنظامُ مغلقٌ افتراضيًّا ولا حسابَ سوبرَ حيّ. فقفلٌ خاطئٌ واحدٌ يترك
 *   صاحبَه بلا مخرج. وهذا نصُّ ما اشترطه ق-٢ قبلَ الإغلاقِ الافتراضيّ.
 *
 * ◆ **والمُشغِّلُ الدورُ 15 والمجيزُ غيرُه**: الخدمةُ تردُّ المجيزَ إن كان الطالبَ
 *   أو من الدورِ 15 — فالشاشةُ تُسجِّل والحوكمةُ تُجيز، ولا يجتمعان.
 *
 * التشغيل: php database/migrations/2028_05_23_perm04_break_glass_screen.php
 * العكس:   php database/migrations/2028_05_23_perm04_break_glass_screen_down.php
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
require_once $ROOT . '/includes/navarch_renderer.php';
require_once $ROOT . '/app/Services/Security/PolicyWriteService.php';
while (ob_get_level() > 0) { ob_end_clean(); }

use App\Services\Security\PolicyWriteService as PW;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = @$conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };
$str = function ($s) use ($conn) { $r = @$conn->query($s); $x = $r ? $r->fetch_row() : null; return $x ? (string) $x[0] : ''; };

$SCREEN = 'Governance/break_glass_open.php';
$SNAME  = 'فتح الطوارئ الموقوت';
$WS = 'DEP-08'; $GID = 1360; $ROLE = 15;
$SCR = 'SCR-0953'; $TARGET = 'NT-DEP-08-046';

echo "══ PERM-04 · بابُ الطوارئ ═══════════════════════════════════════════\n";
if (!is_file($ROOT . '/' . $SCREEN)) { exit("⛔ الملفُّ غيرُ موجود.\n"); }

/* ① سجلُّ الوحدات */
$code = $conn->real_escape_string($SCREEN);
$MID = $one("SELECT id FROM modules WHERE code = '{$code}' LIMIT 1");
if ($MID < 1) {
    $st = $conn->prepare("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                          VALUES (?,?,?, '0', 0, 'fa fa-triangle-exclamation', 617)");
    $st->bind_param('ssi', $SNAME, $SCREEN, $ROLE);
    $st->execute(); $MID = (int) $conn->insert_id; $st->close();
    echo "   + وحدة #{$MID}\n";
} else { echo "   = وحدة #{$MID} قائمة\n"; }

/* ② تصريحُ الظهورِ وبندُ الملاحة */
if ($one("SELECT COUNT(*) FROM role_permissions WHERE role_id={$ROLE} AND module_id={$MID}") < 1) {
    $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                  VALUES ({$ROLE}, {$MID}, 1, 1, 1, 0)");
    echo "   + صُرِّح بالظهور\n";
}
if ($one("SELECT COUNT(*) FROM nav_items WHERE role_id={$ROLE} AND route='{$code}'") < 1) {
    $st = $conn->prepare("INSERT INTO nav_items
          (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
          VALUES (?, 'GOV', 5480, ?, ?, ?, 'fa fa-triangle-exclamation', 93, ?, 1)");
    $lbl = 'فتح الطوارئ';
    $st->bind_param('iisss', $ROLE, $MID, $lbl, $SCREEN, $SCREEN);
    $st->execute(); $st->close();
    echo "   + بندُ ملاحة\n";
}

/* ③ المواضع */
$lower = strtolower(str_replace('.php', '', $SCREEN));
$withPhp = strtolower($SCREEN);
if ($one("SELECT COUNT(*) FROM nav_targets WHERE target_id='{$TARGET}'") < 1) {
    $src = 'PERM-04 — باب الطوارئ كان مبنيا بلا شاشة تبلغه';
    $gk = 'الادوار والصلاحيات'; $lbl = 'فتح الطوارئ'; $row = 46;
    $st = $conn->prepare("INSERT INTO nav_targets
          (target_id, source_doc, sheet_code, row_no, canonical_title,
           workspace_id, group_key, target_order, visibility_class, active)
          VALUES (?,?,?,?,?,?,?,?,'MENU_ITEM',1)");
    $st->bind_param('sssisssi', $TARGET, $src, $WS, $row, $lbl, $WS, $gk, $row);
    $st->execute(); $st->close();
}
if ($one("SELECT COUNT(*) FROM nav_placements WHERE route='" . $conn->real_escape_string($withPhp) . "'") < 1) {
    $ref = 'قياس حي: openException مبني ومختبر وصفر شاشة تناديه';
    $tref = $WS . '·46·فتح الطوارئ'; $sort = 24;
    $st = $conn->prepare("INSERT INTO nav_placements
          (workspace_id, screen_id, route, target_ref, target_id, group_id,
           sort_no, placement_type, source_ref, active)
          VALUES (?,?,?,?,?,?,?,'MENU_ITEM',?,1)");
    $st->bind_param('sssssiis', $WS, $SCR, $withPhp, $tref, $TARGET, $GID, $sort, $ref);
    $st->execute(); $st->close();
}
if ($one("SELECT COUNT(*) FROM nav_workspace_placements WHERE route='" . $conn->real_escape_string($lower) . "'") < 1) {
    $pid = 'WP-' . strtoupper(substr(md5($WS . '|' . $lower), 0, 16));
    $src = 'PERM-04 — باب الطوارئ'; $ref = 'openException بلا شاشة'; $lbl = 'فتح الطوارئ';
    $by = 'migrations/2028_05_23_perm04_break_glass_screen'; $sort = 73;
    $st = $conn->prepare("INSERT INTO nav_workspace_placements
          (placement_id, screen_id, workspace_id, group_id, placement_type, sort_no,
           route, canonical_label, governing_source, source_ref, reason_code,
           effective_from, status, version, created_by, legacy_ref, created_at)
          VALUES (?,?,?,?,'PRIMARY',?,?,?,?,?,'GUIDE_OWNED_LIFECYCLE_S9',
                  CURDATE(),'ACTIVE',1,?,?,NOW())");
    $st->bind_param('sssiisssssi', $pid, $SCR, $WS, $GID, $sort, $lower, $lbl, $src, $ref, $by, $GID);
    $st->execute(); $st->close();
    echo "   + مواضعُ الدليلِ والمساحة\n";
}

/* ④ الإذنُ بدورةِ الحياة */
$adminId = $one("SELECT id FROM users WHERE role='15' AND is_deleted=0 AND status='active' AND company_id=4 ORDER BY id LIMIT 1");
if ($adminId < 1) { exit("⛔ لا حساب حي بالدور 15.\n"); }
$_SESSION['user'] = array('id' => $adminId, 'role' => '15', 'company_id' => 4, 'name' => 'perm04 migration');

$OLD = $one("SELECT pr.profile_id FROM gov_role_profiles pr
              JOIN gov_authority_grants g ON g.profile_id = pr.profile_id AND g.revoked_at IS NULL
              JOIN users u ON u.id = g.user_id AND u.role = '15'
             WHERE pr.state = 'active' LIMIT 1");
$has = $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id = {$OLD} AND item_ref = '{$code}' AND allow = 1");
if ($has > 0) {
    echo "   = القالبُ يحوي الشاشةَ سلفًا\n";
} else {
    $oldCode = $str("SELECT profile_code FROM gov_role_profiles WHERE profile_id = {$OLD}");
    $wasG = $one("SELECT active FROM gov_policy_freeze WHERE scope_code='grants'");
    $wasA = $one("SELECT active FROM gov_policy_freeze WHERE scope_code='profile_activation'");
    $R = 'PERM-04: باب الطوارئ يبلغ من الواجهة';
    if ($wasA) { PW::setFreeze($conn, PW::FREEZE_ACTIVATION, false, $R, $adminId); }
    if ($wasG) { PW::setFreeze($conn, PW::FREEZE_GRANTS, false, $R, $adminId); }

    $newCode = substr($oldCode, 0, 13) . '-B5';
    $i = 1;
    while ($one("SELECT COUNT(*) FROM gov_role_profiles WHERE profile_code='" . $conn->real_escape_string($newCode) . "'") > 0) {
        $i++; $newCode = substr($oldCode, 0, 12) . '-B' . $i;
    }
    $r = PW::cloneProfile($conn, $OLD, $newCode, $R, $adminId);
    if (empty($r['ok'])) { exit("⛔ تعذّر النسخ: {$r['msg']}\n"); }
    $NEW = (int) $r['id'];
    $r = PW::setProfileItem($conn, $NEW, $SCREEN,
        array('allow' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0), $R, $adminId, 'screen');
    if (empty($r['ok'])) { exit("⛔ تعذّر الضمّ: {$r['msg']}\n"); }
    $r = PW::approveProfile($conn, $NEW, $R, $adminId);
    if (empty($r['ok'])) { exit("⛔ تعذّر الاعتماد: {$r['msg']}\n"); }
    $r = PW::activateProfile($conn, $NEW, $R, $adminId);
    if (empty($r['ok'])) { exit("⛔ تعذّر التفعيل: {$r['msg']}\n"); }

    $holders = array();
    $q = $conn->query("SELECT grant_id, user_id FROM gov_authority_grants
                        WHERE profile_id = {$OLD} AND revoked_at IS NULL AND source = 'profile'");
    while ($q && ($x = $q->fetch_assoc())) { $holders[] = $x; }
    foreach ($holders as $hg) {
        $gid = (int) $hg['grant_id']; $huid = (int) $hg['user_id'];
        $rv = PW::revokeGrant($conn, $gid, $R . ' (ترحيل)', $adminId);
        if (empty($rv['ok'])) { continue; }
        $as = PW::assignProfile($conn, $huid, $NEW, $R, $adminId);
        if (empty($as['ok'])) {
            $conn->query("UPDATE gov_authority_grants SET revoked_at = NULL WHERE grant_id = {$gid}");
            exit("⛔ تعذّر الإسنادُ للمستخدم #{$huid} — أُعيدت القديمة.\n");
        }
    }
    PW::retireProfile($conn, $OLD, $R . ' (خلفه ' . $newCode . ')', $adminId);
    if ($wasA) { PW::setFreeze($conn, PW::FREEZE_ACTIVATION, true, $R . ' (إعادة)', $adminId); }
    if ($wasG) { PW::setFreeze($conn, PW::FREEZE_GRANTS, true, $R . ' (إعادة)', $adminId); }
    printf("   ✔ %s ⇐ %s · رُحّل %d\n", $oldCode, $newCode, count($holders));
}

/* ⑤ الشاهد */
echo "\n══ الشاهد ══════════════════════════════════════════════════════════\n";
$fail = 0;
$t = ems_permission_trace($conn, $MID, $adminId);
$v = !empty($t['perms']['can_view']); if (!$v) { $fail++; }
printf("   الحارسُ يفتحها للدور 15: %s\n", $v ? 'نعم' : 'لا ⛔');
$other = $one("SELECT id FROM users WHERE role='18' AND is_deleted=0 AND status='active' AND company_id=4 LIMIT 1");
$leak = 0;
if ($other > 0) { $t2 = ems_permission_trace($conn, $MID, $other); if (!empty($t2['perms']['can_view'])) { $leak++; } }
printf("   الضابطُ السالب — دورٌ آخر يفتحها: %d\n", $leak);
if ($leak > 0) { $fail++; }
$tree = navarch_render($conn, $WS, $ROLE, array('include_shell' => false));
$seen = false;
foreach ($tree['groups'] as $g) { foreach ($g['items'] as $it) { if ($it['route'] === $lower) { $seen = true; } } }
printf("   الرابطُ يُصيَّر: %s\n", $seen ? 'نعم' : 'لا ⛔');
if (!$seen) { $fail++; }
$callers = 0;
foreach (array($ROOT . '/' . $SCREEN) as $f) {
    if (strpos((string) @file_get_contents($f), 'openException') !== false) { $callers++; }
}
printf("   الشاشةُ تنادي المنفذَ المحروس: %s\n", $callers > 0 ? 'نعم' : 'لا ⛔');
if ($callers < 1) { $fail++; }

if ($fail > 0) { exit("\n⛔ الشاهدُ لم يتحقّق ({$fail}).\n"); }
require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — والنظامُ المغلقُ افتراضيًّا صار له مخرجٌ محكومٌ يُبلَغ.\n";
