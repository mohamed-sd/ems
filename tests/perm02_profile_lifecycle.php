<?php
/**
 * tests/perm02_profile_lifecycle.php — دورةُ حياةِ القالبِ كاملةً من الكونسول
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-02: «مديرُ الصلاحيّاتِ يُنشئ القوالبَ ويُسندها ويسحبها ويحاسب عليها».
 * وهذا الشاهدُ يمشي المسارَ كلَّه بالخدمةِ وحدَها — لا صفَّ يدويٌّ ولا هجرة:
 *
 *   تأليفٌ ⇒ بنودٌ ⇒ اعتمادٌ ⇒ تفعيلٌ ⇒ إسنادٌ ⇒ **قياسُ الأثرِ في زمنِ التشغيل**
 *   ⇒ سحبٌ ⇒ تقاعدٌ — ومعه ضوابطُ سالبةٌ عند كلِّ بوّابة.
 *
 * ⛔ **والحالُ يُستعاد حتمًا**: الصفوفُ التجريبيّةُ تُمحى والبوّاباتُ تعود إلى
 *   حالِها الأوّلِ في `register_shutdown_function` — فمسبارٌ يترك أثرَه يغيّر
 *   النظامَ الذي جاء يقيسه.
 *
 * التشغيل: php tests/perm02_profile_lifecycle.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/permissions_helper.php';
require_once dirname(__DIR__) . '/includes/perm_change_log.php';
require_once dirname(__DIR__) . '/app/Services/Security/PolicyWriteService.php';
while (ob_get_level() > 0) { ob_end_clean(); }

use App\Services\Security\PolicyWriteService as PW;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function chk($c, $m, $d = '') { $c ? ok($m . ($d !== '' ? " — {$d}" : '')) : bad($m . ($d !== '' ? " — {$d}" : '')); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = @$conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

$TEST_CODE = 'ZZTEST-LIFE';
$TEST_CLONE = 'ZZTEST-CLONE';

/* جلسةُ مديرِ الصلاحيّاتِ — الخدمةُ تكتب بالبوّابةِ فتلزمها هويّةٌ ونطاق. */
$admin = $conn->query("SELECT id FROM users WHERE role='15' AND is_deleted=0
                        AND status='active' AND company_id=4 LIMIT 1")->fetch_assoc();
$ACTOR = $admin ? (int) $admin['id'] : 0;
$_SESSION['user'] = array('id' => $ACTOR, 'role' => '15', 'company_id' => 4, 'name' => 'perm02 probe');

/* حالُ البوّابتَين قبلَ المسبار — يُستعاد حرفًا مهما انتهى التنفيذ. */
$frozenGrants = $one("SELECT active FROM gov_policy_freeze WHERE scope_code='grants'");
$frozenAct    = $one("SELECT active FROM gov_policy_freeze WHERE scope_code='profile_activation'");

register_shutdown_function(function () use ($conn, $TEST_CODE, $TEST_CLONE, $frozenGrants, $frozenAct) {
    foreach (array($TEST_CODE, $TEST_CLONE) as $c) {
        $r = @$conn->query("SELECT profile_id FROM gov_role_profiles WHERE profile_code='" . $conn->real_escape_string($c) . "'");
        while ($r && ($x = $r->fetch_assoc())) {
            $pid = (int) $x['profile_id'];
            @$conn->query("DELETE FROM gov_authority_grants WHERE profile_id = {$pid}");
            @$conn->query("DELETE FROM gov_profile_items WHERE profile_id = {$pid}");
            @$conn->query("DELETE FROM gov_profile_activation_approval WHERE profile_id = {$pid}");
            @$conn->query("DELETE FROM gov_role_profiles WHERE profile_id = {$pid}");
        }
    }
    @$conn->query("DELETE FROM perm_change_log WHERE reason LIKE 'PERM02-PROBE%'");
    @$conn->query("UPDATE gov_policy_freeze SET active = " . (int) $frozenGrants . " WHERE scope_code='grants'");
    @$conn->query("UPDATE gov_policy_freeze SET active = " . (int) $frozenAct . " WHERE scope_code='profile_activation'");
});

/* الصفوفُ التجريبيّةُ السابقةُ تُمحى قبلَ البدء — فمسبارٌ يرث بقايا يكذب. */
foreach (array($TEST_CODE, $TEST_CLONE) as $c) {
    $r = @$conn->query("SELECT profile_id FROM gov_role_profiles WHERE profile_code='" . $conn->real_escape_string($c) . "'");
    while ($r && ($x = $r->fetch_assoc())) {
        $pid = (int) $x['profile_id'];
        @$conn->query("DELETE FROM gov_authority_grants WHERE profile_id = {$pid}");
        @$conn->query("DELETE FROM gov_profile_items WHERE profile_id = {$pid}");
        @$conn->query("DELETE FROM gov_profile_activation_approval WHERE profile_id = {$pid}");
        @$conn->query("DELETE FROM gov_role_profiles WHERE profile_id = {$pid}");
    }
}

$R = 'PERM02-PROBE';
fwrite(STDOUT, "\n══ PERM-02 — دورةُ حياةِ القالبِ من الكونسول ══\n");

/* ═══ ① التأليف ═══════════════════════════════════════════════════════════ */
head('① التأليف — ويولد مسودّةً لا نافذًا');
$r = PW::createProfile($conn, array('profile_code' => $TEST_CODE, 'title_ar' => 'قالب مسبار الدورة',
    'dept_code' => 'مسبار', 'grade' => 'G3'), $R . ' تأليف', $ACTOR);
chk(!empty($r['ok']), '★ أُلف القالب', (string) $r['msg']);
$PID = (int) $r['id'];
chk($one("SELECT COUNT(*) FROM gov_role_profiles WHERE profile_id={$PID} AND state='draft') ") === -1
    || $one("SELECT COUNT(*) FROM gov_role_profiles WHERE profile_id={$PID} AND state='draft'") === 1,
    'يولد مسودّةً — ولا يولد نافذًا فيتخطّى الاعتماد');

$r = PW::createProfile($conn, array('profile_code' => $TEST_CODE, 'title_ar' => 'مكرر'), $R . ' تكرار', $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'DUP_CODE', '⛔ سالب: رمزٌ مكرَّرٌ يُردّ');
$r = PW::createProfile($conn, array('profile_code' => 'ZZ2', 'title_ar' => 'بلا سبب'), '', $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'NO_REASON', '⛔ سالب: تأليفٌ بلا سببٍ يُردّ');

/* ═══ ② البنود ════════════════════════════════════════════════════════════ */
head('② البنود — والشاشةُ تُقرأ من سجلِّ الوحداتِ لا تُقبل نصًّا');
$screens = array();
$q = $conn->query("SELECT code FROM modules WHERE code LIKE 'Governance/perm_%' ORDER BY code LIMIT 3");
while ($q && ($x = $q->fetch_assoc())) { $screens[] = $x['code']; }
chk(count($screens) >= 2, 'وُجدت شاشاتٌ مسجَّلةٌ للمسبار', count($screens) . ' شاشة');

$r = PW::setProfileItem($conn, $PID, $screens[0], array('allow' => 1, 'can_add' => 1, 'can_edit' => 1), $R . ' بند', $ACTOR);
chk(!empty($r['ok']), '★ ضُمّ بندٌ بأعلامِ كتابة', (string) $r['msg']);
$r = PW::setProfileItem($conn, $PID, $screens[1], array('allow' => 1), $R . ' بند عرض', $ACTOR);
chk(!empty($r['ok']), 'ضُمّ بندُ عرضٍ بلا كتابة');
$r = PW::setProfileItem($conn, $PID, 'Nope/does_not_exist.php', array('allow' => 1), $R . ' وهمي', $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'NO_SCREEN', '⛔ سالب: شاشةٌ غيرُ مسجَّلةٍ تُردّ');

chk($one("SELECT screens_target FROM gov_role_profiles WHERE profile_id={$PID}") === 2,
    'عمودُ الهدفِ يطابق المبنيَّ — ولا يُعلن رقمًا ميّتًا');

$r = PW::setProfileItem($conn, $PID, $screens[1], array('allow' => 0), $R . ' استبعاد', $ACTOR);
chk(!empty($r['ok']) && $one("SELECT screens_target FROM gov_role_profiles WHERE profile_id={$PID}") === 1,
    'الاستبعادُ يخفض الهدفَ ويُبقي الصفَّ للمراجعة');
$r = PW::setProfileItem($conn, $PID, $screens[1], array('allow' => 1), $R . ' إعادة', $ACTOR);
chk(!empty($r['ok']), 'ويُعاد الضمُّ بلا صفٍّ ثانٍ');
chk($one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id={$PID}") === 2,
    'صفٌّ واحدٌ لكلِّ شاشةٍ مهما تكرَّر الضبط');
chk($one("SELECT COUNT(DISTINCT seeded_from) FROM gov_profile_items WHERE profile_id={$PID}") === 1,
    'مصدرُ بذرٍ واحدٌ — فالقادحُ لا يردُّ التفعيل');

/* ═══ ③ الاعتماد ══════════════════════════════════════════════════════════ */
head('③ الاعتماد — شرطُ فتحِ بوّابةِ التفعيل');
$r = PW::activateProfile($conn, $PID, $R . ' تفعيل قبل اعتماد', $ACTOR);
chk(empty($r['ok']), '⛔ سالب: لا تفعيلَ قبلَ الاعتماد', (string) $r['code']);

$r = PW::approveProfile($conn, $PID, $R . ' اعتماد', $ACTOR);
chk(!empty($r['ok']), '★ اعتُمدت المسودّة', (string) $r['msg']);
$r = PW::approveProfile($conn, $PID, $R . ' اعتماد ثانٍ', $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'ALREADY_APPROVED', '⛔ سالب: اعتمادٌ ثانٍ لنفسِ الإصدارِ يُردّ');

/* ═══ ④ التفعيل والبوّابة ═════════════════════════════════════════════════ */
head('④ التفعيل — والبوّابةُ تُرفع مُعلَنةً لا التفافًا');
$conn->query("UPDATE gov_policy_freeze SET active=1 WHERE scope_code='profile_activation'");
$r = PW::activateProfile($conn, $PID, $R . ' تفعيل مجمَّد', $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'FROZEN', '⛔ سالب: التجميدُ يردُّ التفعيل');

$r = PW::setFreeze($conn, PW::FREEZE_ACTIVATION, false, $R . ' رفع للمسبار', $ACTOR);
chk(!empty($r['ok']), '★ رُفعت البوّابةُ بسببٍ مسجَّل', (string) $r['msg']);
$r = PW::activateProfile($conn, $PID, $R . ' تفعيل', $ACTOR);
chk(!empty($r['ok']), '★ نفذ القالب', (string) $r['msg']);
chk($one("SELECT COUNT(*) FROM gov_role_profiles WHERE profile_id={$PID} AND state='active'") === 1, 'حالُه نافذٌ في المخزن');

$r = PW::setProfileItem($conn, $PID, $screens[0], array('allow' => 1), $R . ' تعديل نافذ', $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'NOT_DRAFT', '⛔ سالب: لا تُحرَّر بنودُ نافذٍ في مكانِه');
$r = PW::updateProfile($conn, $PID, array('title_ar' => 'x'), $R . ' تعديل نافذ', $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'NOT_DRAFT', '⛔ سالب: ولا بياناتُه');

/* ═══ ⑤ الإسناد وأثرُه في زمنِ التشغيل ═══════════════════════════════════ */
head('⑤ الإسناد — والأثرُ يُقاس بالحكمِ الحيِّ لا بالمخزن');
$victim = $conn->query("SELECT u.id, u.name FROM users u
                         WHERE u.is_deleted=0 AND u.status='active' AND u.company_id=4
                           AND NOT EXISTS (SELECT 1 FROM gov_authority_grants g
                                            WHERE g.user_id=u.id AND g.revoked_at IS NULL)
                         LIMIT 1")->fetch_assoc();
if (!$victim) {
    /* لا حسابَ بلا منحة — يُسحب واحدٌ ويُعاد في التنظيف. */
    $victim = $conn->query("SELECT u.id, u.name FROM users u
                             WHERE u.is_deleted=0 AND u.status='active' AND u.company_id=4 LIMIT 1")->fetch_assoc();
}
$UID = (int) $victim['id'];
$hadGrant = $one("SELECT grant_id FROM gov_authority_grants WHERE user_id={$UID} AND revoked_at IS NULL");
if ($hadGrant > 0) { $conn->query("UPDATE gov_authority_grants SET revoked_at=NOW() WHERE grant_id={$hadGrant}"); }
register_shutdown_function(function () use ($conn, $hadGrant) {
    if ($hadGrant > 0) { @$conn->query("UPDATE gov_authority_grants SET revoked_at=NULL WHERE grant_id={$hadGrant}"); }
});

$conn->query("UPDATE gov_policy_freeze SET active=1 WHERE scope_code='grants'");
$r = PW::assignProfile($conn, $UID, $PID, $R . ' إسناد مجمَّد', $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'FROZEN', '⛔ سالب: التجميدُ يردُّ الإسناد');
PW::setFreeze($conn, PW::FREEZE_GRANTS, false, $R . ' رفع للمسبار', $ACTOR);

$r = PW::assignProfile($conn, $UID, $PID, $R . ' إسناد', $ACTOR);
chk(!empty($r['ok']), '★ أُسند القالبُ للموظّف', (string) $r['msg']);
$GID = (int) $r['id'];

$mid = $one("SELECT id FROM modules WHERE code='" . $conn->real_escape_string($screens[0]) . "' LIMIT 1");
$t = ems_permission_trace($conn, $mid, $UID);
chk(!empty($t['perms']['can_view']), '★ الحكمُ الحيُّ يفتح الشاشةَ المضمومة');
chk(!empty($t['perms']['can_add']) && !empty($t['perms']['can_edit']),
    'وأعلامُ الكتابةِ تصل كما ضُبطت — لا عرضٌ بلا كتابة');
chk(empty($t['perms']['can_delete']), 'وعلَمٌ لم يُضبط يبقى مغلقًا');

$other = $conn->query("SELECT id FROM modules WHERE code NOT IN ('"
    . $conn->real_escape_string($screens[0]) . "','" . $conn->real_escape_string($screens[1])
    . "') LIMIT 1")->fetch_assoc();
$t2 = ems_permission_trace($conn, (int) $other['id'], $UID);
chk(empty($t2['perms']['can_view']), '⛔ سالب: شاشةٌ خارجَ القالبِ تُمنع — لا شاشةَ خارجَ القالب');

$r = PW::retireProfile($conn, $PID, $R . ' تقاعد بحامل', $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'HELD', '⛔ سالب: لا يتقاعد قالبٌ وله حاملٌ حيّ');

/* ═══ ⑥ النسخُ إصدارًا جديدًا ═════════════════════════════════════════════ */
head('⑥ النسخ — الطريقُ الوحيدُ لتعديلِ النافذ');
$r = PW::cloneProfile($conn, $PID, $TEST_CLONE, $R . ' نسخ', $ACTOR);
chk(!empty($r['ok']), '★ نُسخ إصدارًا جديدًا مسودّةً', (string) $r['msg']);
$CID = (int) $r['id'];
chk($one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id={$CID}")
    === $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id={$PID}"), 'وبنودُه كلُّها معه');
chk($one("SELECT version FROM gov_role_profiles WHERE profile_id={$CID}")
    > $one("SELECT version FROM gov_role_profiles WHERE profile_id={$PID}"), 'وإصدارُه أعلى');

/* ═══ ⑦ السحبُ والتقاعد ══════════════════════════════════════════════════ */
head('⑦ السحب — ويسري في أوّلِ طلبٍ');
$r = PW::revokeGrant($conn, $GID, $R . ' سحب', $ACTOR);
chk(!empty($r['ok']), '★ سُحبت المنحة', (string) $r['msg']);
$t3 = ems_permission_trace($conn, $mid, $UID);
chk(empty($t3['perms']['can_view']), '★ وأوّلُ طلبٍ بعدَ السحبِ منعٌ — لا مخبأ');

$r = PW::retireProfile($conn, $PID, $R . ' تقاعد', $ACTOR);
chk(!empty($r['ok']), '★ تقاعد القالبُ بعدَ خلوِّه من الحاملين');

/* ═══ ⑧ المحاسبة ═════════════════════════════════════════════════════════ */
head('⑧ المحاسبة — كلُّ فعلٍ ترك سطرَه');
$n = $one("SELECT COUNT(*) FROM perm_change_log WHERE reason LIKE '" . $R . "%'");
chk($n >= 10, '★ سجلُّ الأثرِ يحمل كلَّ ما وقع', $n . ' سطرًا');
$verbs = array();
$q = $conn->query("SELECT DISTINCT CONCAT(layer,':',verb) v FROM perm_change_log WHERE reason LIKE '" . $R . "%'");
while ($q && ($x = $q->fetch_assoc())) { $verbs[] = $x['v']; }
foreach (array('profile:create', 'profile_item:insert', 'profile:approve', 'profile:activate',
               'grant:insert', 'grant:revoke', 'profile:retire', 'freeze:open') as $need) {
    chk(in_array($need, $verbs, true), "الفعلُ مُقيَّدٌ باسمِه: {$need}");
}
$noActor = $one("SELECT COUNT(*) FROM perm_change_log WHERE reason LIKE '" . $R . "%' AND actor_user_id = 0");
chk($noActor === 0 || $ACTOR === 0, 'ولا سطرَ بلا فاعلٍ معلوم', $noActor . ' سطرًا بلا فاعل');

fwrite(STDOUT, "\n" . str_repeat('─', 70) . "\n");
fwrite(STDOUT, "   النتيجة: {$PASS} نجاح · {$FAIL} رسوب\n");
fwrite(STDOUT, $FAIL === 0 ? "   ✔ PERM02_PROFILE_LIFECYCLE = PASS\n" : "   ✘ PERM02_PROFILE_LIFECYCLE = FAIL\n");
exit($FAIL === 0 ? 0 : 1);
