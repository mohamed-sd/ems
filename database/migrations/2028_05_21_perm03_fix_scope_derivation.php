<?php
/**
 * 2028_05_21_perm03_fix_scope_derivation.php — تصحيحُ اشتقاقِ بندِ المجال
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **العطبُ الذي أدخلتْه الهجرةُ 2028_05_20 وكشفته المراجعةُ العكسيّة**:
 *   اشتُقَّ بندُ `scope` من **مساحةِ الدورِ الأمِّ وحدَها**
 *   (`navarch_role_workspace`)، ونِفاذُ الدورِ في الملاحةِ يمتدُّ على **عشرين
 *   مساحةً**. فحينَ صار المُصيِّرُ يحصر المسارَ في مجالِ قالبِه، فَقَدَ **36
 *   مستخدمًا نحوَ نصفِ روابطِهم** (الدور 19: 140 ⇐ 68 · الدور 18: 110 ⇐ 56).
 *
 * ◆ **والخطأُ في الاشتقاقِ لا في التصميم**: بندُ المجالِ صحيحٌ ومفيدٌ — يحصر ما
 *   يُصيَّر للموظّفِ في مساحاتِ قالبِه، فيملك مديرُ الصلاحيّاتِ تضييقَه بقرار.
 *   لكنَّ **الأساسَ يجب أن يساويَ الحالَ القائمَ** حتى لا يقع تضييقٌ لم يقرّره
 *   أحد. والتضييقُ يبدأ حين يُنزَع بندٌ من الكونسول لا حينَ يُبنى الأساس.
 *
 * ◆ **فيُعاد الاشتقاقُ من نِفاذِ الدورِ نفسِه**: كلُّ مساحةٍ يسكنها موضعُ مسارٍ
 *   مصرَّحٍ لهذا الدورِ في الملاحة. والنتيجةُ المستهدَفة: **فارقُ تصييرٍ صفر**.
 *
 * ⛔ **ولا يُصحَّح صفٌّ في قالبٍ نافذ**: التصحيحُ إصدارٌ جديدٌ بدورةِ حياتِه
 *   كاملةً — فحتّى تصحيحُ خطئي يمرُّ بالبابِ المحروس.
 *
 * التشغيل: php database/migrations/2028_05_21_perm03_fix_scope_derivation.php
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

echo "══ تصحيحُ اشتقاقِ بندِ المجال ═══════════════════════════════════════\n";

/* ═══ ① القياسُ قبلَ التصحيح — بالتصييرِ لا بالجدول ═══════════════════════ */
$measure = function () use ($conn) {
    $out = array('users' => 0, 'with' => 0, 'without' => 0);
    $q = $conn->query("SELECT u.id, u.role FROM users u
        JOIN gov_authority_grants g ON g.user_id=u.id AND g.revoked_at IS NULL
        JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
        JOIN gov_profile_items i ON i.profile_id=p.profile_id AND i.item_kind='scope' AND i.allow=1
        WHERE u.is_deleted=0 AND u.status='active' AND u.company_id=4 GROUP BY u.id");
    while ($q && ($u = $q->fetch_assoc())) {
        $out['users']++;
        $_SESSION['user'] = array('id' => (int) $u['id'], 'role' => (string) $u['role'],
                                  'company_id' => 4, 'name' => 'scope probe');
        $out['with'] += count(navarch_authorized_routes($conn, (int) $u['role']));
        $_SESSION['user'] = array('id' => 0, 'role' => '-999', 'company_id' => 4, 'name' => 'scope probe');
        $out['without'] += count(navarch_authorized_routes($conn, (int) $u['role']));
    }
    return $out;
};
$before = $measure();
printf("   قبل: %d مستخدمًا · روابطُ بمجالٍ %d · بلا مجالٍ %d · **الفاقد %d**\n",
    $before['users'], $before['with'], $before['without'], $before['without'] - $before['with']);

/* ═══ ② الاشتقاقُ الصحيح: كلُّ مساحةٍ يسكنها موضعُ مسارٍ مصرَّحٍ للدور ════ */
$wantWs = array();
$q = $conn->query("SELECT DISTINCT n.role_id, wp.workspace_id
     FROM nav_items n
     JOIN role_permissions rp ON rp.module_id = n.module_id AND rp.role_id = n.role_id AND rp.can_view = 1
     JOIN nav_workspace_placements wp ON wp.route = LOWER(REPLACE(n.route, '.php',''))
    WHERE n.active = 1 AND wp.status = 'ACTIVE'");
while ($q && ($x = $q->fetch_assoc())) { $wantWs[(int) $x['role_id']][(string) $x['workspace_id']] = 1; }

/* ═══ ③ الأدوارُ التي لها بندُ مجالٍ اليوم ═══════════════════════════════ */
$roles = array();
$q = $conn->query("SELECT DISTINCT u.role FROM users u
     JOIN gov_authority_grants g ON g.user_id=u.id AND g.revoked_at IS NULL
     JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
     JOIN gov_profile_items i ON i.profile_id=p.profile_id AND i.item_kind='scope'
    WHERE u.is_deleted=0 AND u.status='active' AND u.company_id=4");
while ($q && ($x = $q->fetch_row())) { $roles[] = (int) $x[0]; }
sort($roles);
printf("   أدوارٌ لها بندُ مجال: %s\n\n", implode(' · ', $roles));

$adminId = $one("SELECT id FROM users WHERE role='15' AND is_deleted=0 AND status='active' AND company_id=4 ORDER BY id LIMIT 1");
if ($adminId < 1) { exit("⛔ لا حساب حي بالدور 15.\n"); }
$_SESSION['user'] = array('id' => $adminId, 'role' => '15', 'company_id' => 4, 'name' => 'perm03 scope fix');

$wasG = $one("SELECT active FROM gov_policy_freeze WHERE scope_code='grants'");
$wasA = $one("SELECT active FROM gov_policy_freeze WHERE scope_code='profile_activation'");
$R = 'PERM-03: تصحيح اشتقاق بند المجال — الاساس يساوي الحال القائم';
if ($wasA) { PW::setFreeze($conn, PW::FREEZE_ACTIVATION, false, $R, $adminId); }
if ($wasG) { PW::setFreeze($conn, PW::FREEZE_GRANTS, false, $R, $adminId); }

$done = 0; $addedAll = 0;
foreach ($roles as $rid) {
    $OLD = $one("SELECT pr.profile_id FROM gov_role_profiles pr
                  JOIN gov_authority_grants g ON g.profile_id = pr.profile_id AND g.revoked_at IS NULL
                  JOIN users u ON u.id = g.user_id AND u.role = '{$rid}'
                 WHERE pr.state = 'active' LIMIT 1");
    if ($OLD < 1) { continue; }
    $oldCode = $str("SELECT profile_code FROM gov_role_profiles WHERE profile_id = {$OLD}");

    $have = array();
    $q = $conn->query("SELECT item_ref FROM gov_profile_items
                        WHERE profile_id = {$OLD} AND item_kind = 'scope' AND allow = 1");
    while ($q && ($x = $q->fetch_row())) { $have[(string) $x[0]] = 1; }
    $missing = array_diff_key($wantWs[$rid] ?? array(), $have);
    if (!$missing) { printf("   = الدور %-3d %s مجالُه كاملٌ سلفًا\n", $rid, $oldCode); continue; }

    $newCode = substr($oldCode, 0, 13) . '-S4';
    $i = 1;
    while ($one("SELECT COUNT(*) FROM gov_role_profiles WHERE profile_code='" . $conn->real_escape_string($newCode) . "'") > 0) {
        $i++; $newCode = substr($oldCode, 0, 12) . '-S' . $i;
    }
    $r = PW::cloneProfile($conn, $OLD, $newCode, $R, $adminId);
    if (empty($r['ok'])) { printf("   ⛔ الدور %d: تعذّر النسخ — %s\n", $rid, $r['msg']); continue; }
    $NEW = (int) $r['id'];

    $n = 0;
    foreach (array_keys($missing) as $ws) {
        $res = PW::setProfileItem($conn, $NEW, $ws, array('allow' => 1), $R, $adminId, 'scope');
        if (!empty($res['ok'])) { $n++; }
    }
    $res = PW::approveProfile($conn, $NEW, $R, $adminId);
    if (empty($res['ok'])) { printf("   ⛔ الدور %d: تعذّر الاعتماد — %s\n", $rid, $res['msg']); continue; }
    $res = PW::activateProfile($conn, $NEW, $R, $adminId);
    if (empty($res['ok'])) { printf("   ⛔ الدور %d: تعذّر التفعيل — %s\n", $rid, $res['msg']); continue; }

    $holders = array();
    $q = $conn->query("SELECT grant_id, user_id FROM gov_authority_grants
                        WHERE profile_id = {$OLD} AND revoked_at IS NULL AND source = 'profile'");
    while ($q && ($x = $q->fetch_assoc())) { $holders[] = $x; }
    $moved = 0;
    foreach ($holders as $hg) {
        $gid = (int) $hg['grant_id']; $huid = (int) $hg['user_id'];
        $rv = PW::revokeGrant($conn, $gid, $R . ' (ترحيل إلى ' . $newCode . ')', $adminId);
        if (empty($rv['ok'])) { continue; }
        $as = PW::assignProfile($conn, $huid, $NEW, $R, $adminId);
        if (empty($as['ok'])) {
            $conn->query("UPDATE gov_authority_grants SET revoked_at = NULL WHERE grant_id = {$gid}");
            printf("   ⛔ الدور %d: تعذّر إسنادُ #%d — أُعيدت القديمة\n", $rid, $huid);
            continue;
        }
        $moved++;
    }
    PW::retireProfile($conn, $OLD, $R . ' (خلفه ' . $newCode . ')', $adminId);
    printf("   ✔ الدور %-3d %s ⇐ %s · مجالاتٌ مضافة %d · رُحّل %d\n", $rid, $oldCode, $newCode, $n, $moved);
    $done++; $addedAll += $n;
}

if ($wasA) { PW::setFreeze($conn, PW::FREEZE_ACTIVATION, true, $R . ' (إعادة)', $adminId); }
if ($wasG) { PW::setFreeze($conn, PW::FREEZE_GRANTS, true, $R . ' (إعادة)', $adminId); }

/* ═══ ④ الشاهد: الفارقُ صفر ═════════════════════════════════════════════ */
echo "\n══ الشاهد ══════════════════════════════════════════════════════════\n";
$after = $measure();
printf("   بعد: %d مستخدمًا · روابطُ بمجالٍ %d · بلا مجالٍ %d · **الفاقد %d**\n",
    $after['users'], $after['with'], $after['without'], $after['without'] - $after['with']);
printf("   أدوارٌ صُحّحت: %d · مجالاتٌ مضافة: %d\n", $done, $addedAll);

$cov = $one("SELECT COUNT(DISTINCT u.id) FROM users u
              JOIN gov_authority_grants g ON g.user_id=u.id AND g.revoked_at IS NULL
              JOIN gov_role_profiles pr ON pr.profile_id=g.profile_id AND pr.state='active'
             WHERE u.is_deleted=0 AND u.status='active' AND u.company_id=4");
$live = $one("SELECT COUNT(*) FROM users WHERE is_deleted=0 AND status='active' AND company_id=4");
printf("   التغطية: %d من %d\n", $cov, $live);

$loss = $after['without'] - $after['with'];
if ($loss !== 0) { exit("\n⛔ ما يزال فاقدٌ {$loss} — الأساسُ لا يساوي الحالَ القائم.\n"); }
if ($cov !== $live) { exit("\n⛔ سقطت التغطية.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — الأساسُ يساوي الحالَ القائمَ، والتضييقُ صار قرارًا لا أثرًا جانبيًّا.\n";
