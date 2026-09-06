<?php
/**
 * 2028_05_19_perm03_layers_and_recorder.php — إتمامُ الطبقاتِ المعلَنة (PERM-03)
 * ═══════════════════════════════════════════════════════════════════════════
 * ثلاثةُ أشقٍّ في هجرةٍ واحدةٍ لأنّها تُغلق بندًا واحدًا: «طبقةٌ مُعلَنةٌ وغيرُ
 * منفَّذةٍ تُقرأ ضمانًا وهي فراغ».
 *
 *   ① **شاشةُ تسجيلِ قرارِ الاعتماد** — `ApprovalGate` مبنيّةٌ وكاملةُ الحرّاسِ
 *      ولم تكن **تُنادى من الإنتاج** قطُّ، فخمسُ تركيباتِ فصلِ واجباتٍ تُعلن
 *      نقطةَ إنفاذِها فيها والنقطةُ لا تُبلَغ. فتُسجَّل الشاشةُ وتُبلَغ.
 *   ② **بنودُ الأفعالِ في قوالبِ الماليّة** — نوعُ البندِ `action` صار منفَّذًا،
 *      فيُربط كلُّ دورٍ بالفعلِ الذي يخصُّه من مفرداتِ `gov_authority_limits`.
 *   ③ **والإذنُ يمرُّ بدورةِ الحياةِ لا بصفٍّ يدويّ** — كما في PERM-02.
 *
 * ⛔ **والفعلُ يُسنَد لصاحبِه وحدَه**: نوعُ الاعتمادِ له مالكٌ مُعلَنٌ في
 *   `fin_approval_types.allowed_roles`، فمن ليس صاحبَه لا يأخذ فعلَه — وإلّا
 *   صار البندُ توسعةً بثوبِ إتمامٍ للطبقة.
 *
 * التشغيل: php database/migrations/2028_05_19_perm03_layers_and_recorder.php
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

$SCREEN = 'Finance/acc_approval_record.php';
$SNAME  = 'تسجيل قرار الاعتماد';

echo "══ PERM-03 · إتمامُ الطبقاتِ المعلَنة ═══════════════════════════════════\n";
if (!is_file($ROOT . '/' . $SCREEN)) { exit("⛔ الملفُّ غيرُ موجود: {$SCREEN}\n"); }

/* ═══ ① سجلُّ الوحداتِ وتصريحُ الظهور ═════════════════════════════════════ */
$code = $conn->real_escape_string($SCREEN);
$MID = $one("SELECT id FROM modules WHERE code = '{$code}' LIMIT 1");
if ($MID < 1) {
    $st = $conn->prepare("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                          VALUES (?,?,?, '0', 0, 'fa fa-stamp', 616)");
    $r18 = 18;
    $st->bind_param('ssi', $SNAME, $SCREEN, $r18);
    $st->execute(); $MID = (int) $conn->insert_id; $st->close();
    echo "   + سجلُّ الوحدات: #{$MID}\n";
} else {
    echo "   = سجلُّ الوحدات: #{$MID} قائم\n";
}

/* ◆ **ومالكو الأنواعِ الأربعةِ هم أصحابُ الشاشة**: من ليس صاحبَ نوعٍ لا يسجّل
     قرارًا، فلا يُعطى بابَه. والأدوارُ تُقرأ من `fin_approval_types` لا تُخترع. */
$ROLES = array();
$rq = $conn->query("SELECT allowed_roles FROM fin_approval_types
                     WHERE active = 1 AND code LIKE 'APR-%' AND allowed_roles <> ''");
while ($rq && ($x = $rq->fetch_row())) {
    foreach (explode(',', (string) $x[0]) as $rid) {
        $rid = (int) trim($rid);
        if ($rid > 0) { $ROLES[$rid] = 1; }
    }
}
$ROLES = array_keys($ROLES);
sort($ROLES);
echo "   أصحابُ الأنواعِ المُعلَنون: " . implode(' · ', $ROLES) . "\n";

foreach ($ROLES as $rid) {
    if ($one("SELECT COUNT(*) FROM role_permissions WHERE role_id={$rid} AND module_id={$MID}") < 1) {
        $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                      VALUES ({$rid}, {$MID}, 1, 1, 0, 0)");
    }
    if ($one("SELECT COUNT(*) FROM nav_items WHERE role_id={$rid} AND route='{$code}'") < 1) {
        $st = $conn->prepare("INSERT INTO nav_items
              (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
              VALUES (?, 'FIN', NULL, ?, ?, ?, 'fa fa-stamp', 40, ?, 1)");
        $st->bind_param('iisss', $rid, $MID, $SNAME, $SCREEN, $SCREEN);
        $st->execute(); $st->close();
    }
}
echo "   ✔ صُرِّح بالظهورِ لكلِّ صاحبِ نوع.\n";

/* ═══ ② الفعلُ المقابلُ لكلِّ دورٍ من مالكِ نوعِه ══════════════════════════ */
$ACT = array(1 => 'fin.approve.need', 2 => 'fin.approve.budget',
             3 => 'fin.approve.commit', 4 => 'fin.approve.execute');
$roleActions = array();
$rq = $conn->query("SELECT seq, allowed_roles FROM fin_approval_types
                     WHERE active = 1 AND code LIKE 'APR-%' AND allowed_roles <> ''");
while ($rq && ($x = $rq->fetch_assoc())) {
    $a = $ACT[(int) $x['seq']] ?? null;
    if ($a === null) { continue; }
    foreach (explode(',', (string) $x['allowed_roles']) as $rid) {
        $rid = (int) trim($rid);
        if ($rid > 0) { $roleActions[$rid][$a] = 1; }
    }
}

/* ═══ ③ الإذنُ بدورةِ الحياة ═══════════════════════════════════════════════ */
$adminId = $one("SELECT id FROM users WHERE role='15' AND is_deleted=0 AND status='active' AND company_id=4 ORDER BY id LIMIT 1");
if ($adminId < 1) { exit("⛔ لا حساب حي بالدور 15.\n"); }
$_SESSION['user'] = array('id' => $adminId, 'role' => '15', 'company_id' => 4, 'name' => 'perm03 migration');

$wasG = $one("SELECT active FROM gov_policy_freeze WHERE scope_code='grants'");
$wasA = $one("SELECT active FROM gov_policy_freeze WHERE scope_code='profile_activation'");
$R = 'PERM-03: اتمام طبقتي الفعل والشاشة لاصحاب انواع الاعتماد';
if ($wasA) { PW::setFreeze($conn, PW::FREEZE_ACTIVATION, false, $R, $adminId); }
if ($wasG) { PW::setFreeze($conn, PW::FREEZE_GRANTS, false, $R, $adminId); }

$done = 0; $skipped = 0;
foreach ($ROLES as $rid) {
    $OLD = $one("SELECT pr.profile_id FROM gov_role_profiles pr
                  JOIN gov_authority_grants g ON g.profile_id = pr.profile_id AND g.revoked_at IS NULL
                  JOIN users u ON u.id = g.user_id AND u.role = '{$rid}'
                 WHERE pr.state = 'active' LIMIT 1");
    if ($OLD < 1) { printf("   - الدور %d: لا قالب نافذ بحامل، يُتخطّى\n", $rid); $skipped++; continue; }
    $oldCode = $str("SELECT profile_code FROM gov_role_profiles WHERE profile_id = {$OLD}");

    $has = $one("SELECT COUNT(*) FROM gov_profile_items
                  WHERE profile_id = {$OLD} AND item_ref = '{$code}' AND allow = 1");
    if ($has > 0) { printf("   = الدور %d: %s يحوي الشاشةَ سلفًا\n", $rid, $oldCode); $skipped++; continue; }

    $newCode = substr($oldCode, 0, 13) . '-P3';
    $i = 1;
    while ($one("SELECT COUNT(*) FROM gov_role_profiles WHERE profile_code='" . $conn->real_escape_string($newCode) . "'") > 0) {
        $i++; $newCode = substr($oldCode, 0, 12) . '-P3' . $i;
    }
    $r = PW::cloneProfile($conn, $OLD, $newCode, $R, $adminId);
    if (empty($r['ok'])) { printf("   ⛔ الدور %d: تعذّر النسخ — %s\n", $rid, $r['msg']); continue; }
    $NEW = (int) $r['id'];

    $r = PW::setProfileItem($conn, $NEW, $SCREEN,
        array('allow' => 1, 'can_add' => 1, 'can_edit' => 0, 'can_delete' => 0), $R, $adminId, 'screen');
    if (empty($r['ok'])) { printf("   ⛔ الدور %d: تعذّر ضمُّ الشاشة — %s\n", $rid, $r['msg']); continue; }

    $acts = array_keys($roleActions[$rid] ?? array());
    foreach ($acts as $a) {
        $r = PW::setProfileItem($conn, $NEW, $a, array('allow' => 1), $R, $adminId, 'action');
        if (empty($r['ok'])) { printf("   ⛔ الدور %d: تعذّر ضمُّ الفعل %s — %s\n", $rid, $a, $r['msg']); }
    }

    $r = PW::approveProfile($conn, $NEW, $R, $adminId);
    if (empty($r['ok'])) { printf("   ⛔ الدور %d: تعذّر الاعتماد — %s\n", $rid, $r['msg']); continue; }
    $r = PW::activateProfile($conn, $NEW, $R, $adminId);
    if (empty($r['ok'])) { printf("   ⛔ الدور %d: تعذّر التفعيل — %s\n", $rid, $r['msg']); continue; }

    $holders = array();
    $q = $conn->query("SELECT grant_id, user_id FROM gov_authority_grants
                        WHERE profile_id = {$OLD} AND revoked_at IS NULL AND source = 'profile'");
    while ($q && ($x = $q->fetch_assoc())) { $holders[] = $x; }
    $moved = 0;
    foreach ($holders as $hg) {
        $gid = (int) $hg['grant_id']; $huid = (int) $hg['user_id'];
        $rv = PW::revokeGrant($conn, $gid, $R . ' (ترحيل إلى ' . $newCode . ')', $adminId);
        if (empty($rv['ok'])) { printf("   ⛔ الدور %d: تعذّر سحبُ #%d\n", $rid, $gid); continue; }
        $as = PW::assignProfile($conn, $huid, $NEW, $R, $adminId);
        if (empty($as['ok'])) {
            $conn->query("UPDATE gov_authority_grants SET revoked_at = NULL WHERE grant_id = {$gid}");
            printf("   ⛔ الدور %d: تعذّر إسنادُ #%d — أُعيدت القديمة\n", $rid, $huid);
            continue;
        }
        $moved++;
    }
    PW::retireProfile($conn, $OLD, $R . ' (خلفه ' . $newCode . ')', $adminId);
    printf("   ✔ الدور %-3d %s ⇐ %s · أفعال: %s · رُحّل %d\n",
        $rid, $oldCode, $newCode, ($acts ? implode(',', $acts) : 'لا فعل'), $moved);
    $done++;
}

if ($wasA) { PW::setFreeze($conn, PW::FREEZE_ACTIVATION, true, $R . ' (إعادة)', $adminId); }
if ($wasG) { PW::setFreeze($conn, PW::FREEZE_GRANTS, true, $R . ' (إعادة)', $adminId); }

/* ═══ ④ الشاهد ═══════════════════════════════════════════════════════════ */
echo "\n══ الشاهد ══════════════════════════════════════════════════════════\n";
printf("   أدوارٌ عولجت: %d · متخطّاة: %d\n", $done, $skipped);
$prodCallers = 0;
foreach (array($ROOT . '/' . $SCREEN) as $f) {
    if (strpos((string) @file_get_contents($f), 'ApprovalGate::record') !== false) { $prodCallers++; }
}
printf("   نقطةُ الإنفاذِ تُنادى من الإنتاج: %s\n", $prodCallers > 0 ? 'نعم' : 'لا ⛔');
$actItems = $one("SELECT COUNT(*) FROM gov_profile_items i
                   JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
                  WHERE i.item_kind='action' AND i.allow=1");
printf("   بنودُ أفعالٍ نافذة: %d\n", $actItems);
$kinds = $str("SELECT GROUP_CONCAT(DISTINCT i.item_kind ORDER BY i.item_kind) FROM gov_profile_items i
                JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active' WHERE i.allow=1");
printf("   أنواعُ البنودِ المنفَّذةُ الآن: %s\n", $kinds);

$cov = $one("SELECT COUNT(DISTINCT u.id) FROM users u
              JOIN gov_authority_grants g ON g.user_id=u.id AND g.revoked_at IS NULL
              JOIN gov_role_profiles pr ON pr.profile_id=g.profile_id AND pr.state='active'
             WHERE u.is_deleted=0 AND u.status='active' AND u.company_id=4");
$live = $one("SELECT COUNT(*) FROM users WHERE is_deleted=0 AND status='active' AND company_id=4");
printf("   التغطية: %d من %d\n", $cov, $live);
if ($cov !== $live) { exit("\n⛔ سقطت التغطيةُ — راجعْ قبلَ الالتزام.\n"); }
if ($prodCallers < 1) { exit("\n⛔ نقطةُ الإنفاذِ ما تزال بلا نداءٍ إنتاجيّ.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — والطبقةُ المُعلَنةُ صارت مُنفَّذةً ومقيسة.\n";
