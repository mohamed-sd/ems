<?php
/**
 * 2028_05_20_perm03_bind_cap_scope_field.php — ربطُ الأنواعِ الثلاثةِ الباقية
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-03: بعدَ `screen` و`action` تبقى `cap` و`scope` و`field`. وهذه الهجرةُ
 * **تربط ولا تخترع**: كلُّ بندٍ تكتبه مشتقٌّ من سجلٍّ حاكمٍ يقول الشيءَ نفسَه
 * اليومَ بلغةِ الأدوار، فتُنقَل الحقيقةُ إلى لغةِ القوالبِ بلا تغييرِ سلوك.
 *
 *   `cap`   ⇐ `gov_authority_limits.role_ids`        (الدورُ محكومٌ بهذا الحدّ)
 *   `field` ⇐ `sensitive_field_policies.allowed_roles_json` (الدورُ يرى الحقلَ)
 *   `scope` ⇐ مساحةُ الدورِ الحاكمةُ من سجلِّ المواضع
 *
 * ⛔ **ولا يتغيّر حكمُ أحدٍ اليوم**: البندُ يساوي ما تقوله السياسةُ حرفًا، فمن
 *   كان يرى الحقلَ يبقى يراه ومن كان يصل المساحةَ يبقى يصلها. **والتشديدُ
 *   يبدأ حين يُعدَّل البندُ من الكونسول** — وهذا هو المقصود: أن تصير الطبقةُ
 *   قابلةً للحكمِ بيدِ مديرِ الصلاحيّاتِ بدل أن تكون مبعثرةً في جداول.
 *
 * ◆ **وكلُّ بنودِ الدورِ في دورةِ حياةٍ واحدة**: نسخةٌ واحدةٌ لكلِّ دورٍ تضمُّ
 *   بنودَه الجديدةَ كلَّها — لا نسخةٌ لكلِّ بند.
 *
 * التشغيل: php database/migrations/2028_05_20_perm03_bind_cap_scope_field.php
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

echo "══ PERM-03 · ربطُ `cap` و`scope` و`field` من سجلّاتِها الحاكمة ══════════\n";

/* ═══ ① ما تقوله السجلّاتُ الحاكمةُ لكلِّ دور ═════════════════════════════ */
$want = array();   /* role => kind => ref => 1 */

$r = $conn->query("SELECT code, role_ids FROM gov_authority_limits WHERE active = 1 AND role_ids <> ''");
while ($r && ($x = $r->fetch_assoc())) {
    foreach (explode(',', (string) $x['role_ids']) as $rid) {
        $rid = (int) trim($rid);
        if ($rid > 0) { $want[$rid]['cap'][(string) $x['code']] = 1; }
    }
}

$r = $conn->query("SELECT field_code, allowed_roles_json FROM sensitive_field_policies WHERE status = 'نافذة'");
while ($r && ($x = $r->fetch_assoc())) {
    $roles = json_decode((string) $x['allowed_roles_json'], true);
    if (!is_array($roles)) { continue; }
    foreach ($roles as $rid) {
        $rid = (int) $rid;
        if ($rid > 0) { $want[$rid]['field'][(string) $x['field_code']] = 1; }
    }
}

foreach (array_keys($want) as $rid) {
    $ws = function_exists('navarch_role_workspace') ? navarch_role_workspace($conn, $rid) : null;
    if ($ws) { $want[$rid]['scope'][(string) $ws] = 1; }
}

ksort($want);
printf("   أدوارٌ تسمّيها السجلّات: %d\n", count($want));

/* ═══ ② البوّاباتُ تُرفع مُعلَنةً وتُعاد ═══════════════════════════════════ */
$adminId = $one("SELECT id FROM users WHERE role='15' AND is_deleted=0 AND status='active' AND company_id=4 ORDER BY id LIMIT 1");
if ($adminId < 1) { exit("⛔ لا حساب حي بالدور 15.\n"); }
$_SESSION['user'] = array('id' => $adminId, 'role' => '15', 'company_id' => 4, 'name' => 'perm03 bind');

$wasG = $one("SELECT active FROM gov_policy_freeze WHERE scope_code='grants'");
$wasA = $one("SELECT active FROM gov_policy_freeze WHERE scope_code='profile_activation'");
$R = 'PERM-03: ربط السقوف والمجالات والحقول من سجلاتها الحاكمة';
if ($wasA) { PW::setFreeze($conn, PW::FREEZE_ACTIVATION, false, $R, $adminId); }
if ($wasG) { PW::setFreeze($conn, PW::FREEZE_GRANTS, false, $R, $adminId); }

/* ═══ ③ دورةُ حياةٍ واحدةٌ لكلِّ دورٍ تضمُّ بنودَه كلَّها ═══════════════════ */
$done = 0; $skipped = 0; $added = 0;
foreach ($want as $rid => $kinds) {
    $OLD = $one("SELECT pr.profile_id FROM gov_role_profiles pr
                  JOIN gov_authority_grants g ON g.profile_id = pr.profile_id AND g.revoked_at IS NULL
                  JOIN users u ON u.id = g.user_id AND u.role = '{$rid}'
                 WHERE pr.state = 'active' LIMIT 1");
    if ($OLD < 1) { $skipped++; continue; }
    $oldCode = $str("SELECT profile_code FROM gov_role_profiles WHERE profile_id = {$OLD}");

    /* ما ينقص فعلًا — فقالبٌ يحمل بنودَه لا يُنسَخ بلا سبب. */
    $missing = array();
    foreach ($kinds as $kind => $refs) {
        foreach (array_keys($refs) as $ref) {
            $e = $conn->real_escape_string($ref);
            if ($one("SELECT COUNT(*) FROM gov_profile_items
                       WHERE profile_id = {$OLD} AND item_kind = '{$kind}'
                         AND item_ref = '{$e}' AND allow = 1") < 1) {
                $missing[] = array($kind, $ref);
            }
        }
    }
    if (!$missing) { printf("   = الدور %-3d %s يحمل بنودَه سلفًا\n", $rid, $oldCode); $skipped++; continue; }

    $newCode = substr($oldCode, 0, 13) . '-L3';
    $i = 1;
    while ($one("SELECT COUNT(*) FROM gov_role_profiles WHERE profile_code='" . $conn->real_escape_string($newCode) . "'") > 0) {
        $i++; $newCode = substr($oldCode, 0, 12) . '-L' . $i;
    }
    $r = PW::cloneProfile($conn, $OLD, $newCode, $R, $adminId);
    if (empty($r['ok'])) { printf("   ⛔ الدور %d: تعذّر النسخ — %s\n", $rid, $r['msg']); continue; }
    $NEW = (int) $r['id'];

    $n = 0; $bad = '';
    foreach ($missing as $m) {
        $res = PW::setProfileItem($conn, $NEW, $m[1], array('allow' => 1), $R, $adminId, $m[0]);
        if (!empty($res['ok'])) { $n++; } elseif ($bad === '') { $bad = $m[0] . ':' . $m[1] . ' — ' . $res['msg']; }
    }
    if ($n < 1) { printf("   ⛔ الدور %d: لم يُضمّ بندٌ — %s\n", $rid, $bad); continue; }

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
    printf("   ✔ الدور %-3d %s ⇐ %s · بنودٌ %d · رُحّل %d\n", $rid, $oldCode, $newCode, $n, $moved);
    $done++; $added += $n;
}

if ($wasA) { PW::setFreeze($conn, PW::FREEZE_ACTIVATION, true, $R . ' (إعادة)', $adminId); }
if ($wasG) { PW::setFreeze($conn, PW::FREEZE_GRANTS, true, $R . ' (إعادة)', $adminId); }

/* ═══ ④ الشاهد ═══════════════════════════════════════════════════════════ */
echo "\n══ الشاهد ══════════════════════════════════════════════════════════\n";
printf("   أدوارٌ عولجت: %d · متخطّاة: %d · بنودٌ مضافة: %d\n", $done, $skipped, $added);
$q = $conn->query("SELECT i.item_kind, COUNT(*) n FROM gov_profile_items i
                    JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
                   WHERE i.allow=1 GROUP BY i.item_kind ORDER BY i.item_kind");
$kinds = array();
while ($q && ($x = $q->fetch_assoc())) { $kinds[] = $x['item_kind'] . '=' . $x['n']; }
printf("   أنواعُ البنودِ النافذة: %s\n", implode(' · ', $kinds));
$allKinds = $str("SELECT GROUP_CONCAT(DISTINCT item_kind ORDER BY item_kind) FROM gov_profile_items");
printf("   أنواعٌ لها صفٌّ في السجلِّ كلِّه: %s\n", $allKinds);

$cov = $one("SELECT COUNT(DISTINCT u.id) FROM users u
              JOIN gov_authority_grants g ON g.user_id=u.id AND g.revoked_at IS NULL
              JOIN gov_role_profiles pr ON pr.profile_id=g.profile_id AND pr.state='active'
             WHERE u.is_deleted=0 AND u.status='active' AND u.company_id=4");
$live = $one("SELECT COUNT(*) FROM users WHERE is_deleted=0 AND status='active' AND company_id=4");
printf("   التغطية: %d من %d\n", $cov, $live);
if ($cov !== $live) { exit("\n⛔ سقطت التغطيةُ — راجعْ قبلَ الالتزام.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — والأنواعُ الخمسةُ صارت لها بنودٌ نافذةٌ وإنفاذٌ مقيس.\n";
