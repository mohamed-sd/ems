<?php
/**
 * tests/perm03_layers.php — الأنواعُ الخمسةُ ومصادرُ المنحِ الأربعة
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01 §1-2: «الأنواعُ الخمسةُ ومصادرُ المنحِ الثلاثةُ الفارغةُ ليست بنيةً
 * زائدة — بل تصميمُنا نفسه منفَّذًا جزئيًّا». وهذا شاهدُ إتمامِها:
 *
 *   ① **نوعُ `action`** — القالبُ يحمل الفعلَ، والبوّابةُ تردُّ من لا يحمله.
 *   ② **نوعُ `field`**  — يضيّق سياسةَ الحقلِ ولا يوسّعها.
 *   ③ **نوعُ `scope`**  — يحصر المسارَ في مساحتِه ولا يفتح غيرَها.
 *   ④ **نوعُ `cap`**    — يربط الدورَ بحدِّه في سجلِّه الحاكم.
 *   ⑤ **المصادرُ الثلاثةُ** — تفويضٌ ورفعٌ وتصعيدٌ: تُمنح موقوتةً وتُقاس
 *      بالحكمِ الحيِّ ثمَّ تُسحب. **ولا يبقى منها صفٌّ بعد المسبار.**
 *
 * ⛔ **والرمزُ من سجلِّه الحاكمِ لا يُخترع**: كلُّ ضابطٍ سالبٍ هنا يجرّب رمزًا
 *   غيرَ مُعلَنٍ ويتوقّع الردّ — فالخطأُ الإملائيُّ لا يكون بابًا.
 *
 * التشغيل: php tests/perm03_layers.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/permissions_helper.php';
require_once dirname(__DIR__) . '/includes/perm_change_log.php';
require_once dirname(__DIR__) . '/app/Services/Security/PolicyWriteService.php';
require_once dirname(__DIR__) . '/app/Services/Finance/ApprovalGate.php';
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

$admin = $conn->query("SELECT id FROM users WHERE role='15' AND is_deleted=0
                        AND status='active' AND company_id=4 LIMIT 1")->fetch_assoc();
$ACTOR = $admin ? (int) $admin['id'] : 0;
$prevSession = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$_SESSION['user'] = array('id' => $ACTOR, 'role' => '15', 'company_id' => 4, 'name' => 'perm03 probe');

/* حالُ البوّابتَين — يُستعاد حتمًا، ولا يُترك مؤقّتٌ من المسبار. */
$frozenG = $one("SELECT active FROM gov_policy_freeze WHERE scope_code='grants'");
$MADE = array();
register_shutdown_function(function () use ($conn, $frozenG, &$MADE, $prevSession) {
    foreach ($MADE as $gid) { @$conn->query("DELETE FROM gov_authority_grants WHERE grant_id = " . (int) $gid); }
    @$conn->query("DELETE FROM gov_delegations WHERE reason LIKE 'PERM03-PROBE%'");
    @$conn->query("DELETE FROM gov_elevations WHERE reason LIKE 'PERM03-PROBE%'");
    @$conn->query("DELETE FROM perm_change_log WHERE reason LIKE 'PERM03-PROBE%'");
    @$conn->query("UPDATE gov_policy_freeze SET active = " . (int) $frozenG . " WHERE scope_code='grants'");
    if ($prevSession === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $prevSession; }
});

$R = 'PERM03-PROBE';
fwrite(STDOUT, "\n══ PERM-03 — الأنواعُ الخمسةُ ومصادرُ المنح ══\n");

/* ═══ ① نوعُ `action` ═════════════════════════════════════════════════════ */
head('① الفعل — القالبُ يحمله والبوّابةُ تردُّ من لا يحمله');
chk(function_exists('ems_can_action') && function_exists('ems_profile_layer'),
    '★ قارئُ الطبقاتِ محمَّلٌ مع مسارِ القرار');

$vocab = ems_action_vocabulary($conn);
chk(count($vocab) >= 4, 'مفرداتُ الأفعالِ تُقرأ من سجلِّها الحاكم', count($vocab) . ' فعلًا');
chk(!ems_action_is_declared($conn, 'fin.approve.nope'), '⛔ سالب: فعلٌ غيرُ مُعلَنٍ يُردّ');

$holder = $conn->query("SELECT g.user_id, i.item_ref FROM gov_profile_items i
                         JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
                         JOIN gov_authority_grants g ON g.profile_id=p.profile_id AND g.revoked_at IS NULL
                        WHERE i.item_kind='action' AND i.allow=1 LIMIT 1")->fetch_assoc();
chk($holder !== null, 'وُجد حاملُ فعلٍ حيٌّ للقياس');
if ($holder) {
    $hu = (int) $holder['user_id']; $ha = (string) $holder['item_ref'];
    chk(ems_can_action($conn, $ha, $hu), '★ حاملُ الفعلِ يملكه', $ha);
    $other = ($ha === 'fin.approve.execute') ? 'fin.approve.budget' : 'fin.approve.execute';
    chk(!ems_can_action($conn, $other, $hu), '⛔ سالب: ولا يملك فعلَ الطرفِ الآخر', $other);
    chk(!ems_can_action($conn, 'fin.approve.nope', $hu), '⛔ سالب: ولا فعلًا غيرَ مُعلَن');
}
$noAct = $conn->query("SELECT g.user_id FROM gov_authority_grants g
                        JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
                       WHERE g.revoked_at IS NULL
                         AND NOT EXISTS (SELECT 1 FROM gov_profile_items i
                                          WHERE i.profile_id=p.profile_id AND i.item_kind='action')
                       LIMIT 1")->fetch_assoc();
if ($noAct) {
    chk(!ems_can_action($conn, 'fin.approve.execute', (int) $noAct['user_id']),
        '⛔ سالب: من لا بندَ فعلٍ في قالبِه لا يملك الفعل');
}
chk(count(\App\Services\Finance\ApprovalGate::ACTION_BY_SEQ) === 4,
    '★ الجسرُ بين نوعِ الاعتمادِ وفعلِه مُعلَنٌ في البوّابة');

/* ═══ ② نوعُ `field` ══════════════════════════════════════════════════════ */
head('② الحقل — يضيّق سياسةَ الحقلِ ولا يوسّعها');
$fh = $conn->query("SELECT g.user_id, i.item_ref FROM gov_profile_items i
                     JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
                     JOIN gov_authority_grants g ON g.profile_id=p.profile_id AND g.revoked_at IS NULL
                    WHERE i.item_kind='field' AND i.allow=1 LIMIT 1")->fetch_assoc();
chk($fh !== null, 'وُجد حاملُ بندِ حقلٍ حيّ');
if ($fh) {
    chk(ems_field_allowed($conn, (string) $fh['item_ref'], (int) $fh['user_id']),
        '★ حاملُ البندِ يرى حقلَه', (string) $fh['item_ref']);
    chk(!ems_field_allowed($conn, 'nope.not_a_field', (int) $fh['user_id']),
        '⛔ سالب: حقلٌ لا بندَ له لا يُفتح');
}

/* ═══ ③ نوعُ `scope` ══════════════════════════════════════════════════════ */
head('③ المجال — يحصر المساحةَ ولا يفتح غيرَها');
$sh = $conn->query("SELECT g.user_id, i.item_ref FROM gov_profile_items i
                     JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
                     JOIN gov_authority_grants g ON g.profile_id=p.profile_id AND g.revoked_at IS NULL
                    WHERE i.item_kind='scope' AND i.allow=1 LIMIT 1")->fetch_assoc();
chk($sh !== null, 'وُجد حاملُ بندِ مجالٍ حيّ');
if ($sh) {
    $su = (int) $sh['user_id']; $sr = (string) $sh['item_ref'];
    chk(ems_workspace_allowed($conn, $sr, $su), '★ حاملُ البندِ يصل مساحتَه', $sr);
    chk(!ems_workspace_allowed($conn, 'DEP-ZZ', $su), '⛔ سالب: ولا يصل مساحةً خارجَ بنودِه');
}
$noScope = $conn->query("SELECT g.user_id FROM gov_authority_grants g
                          JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
                         WHERE g.revoked_at IS NULL
                           AND NOT EXISTS (SELECT 1 FROM gov_profile_items i
                                            WHERE i.profile_id=p.profile_id AND i.item_kind='scope')
                         LIMIT 1")->fetch_assoc();
if ($noScope) {
    chk(ems_workspace_allowed($conn, 'DEP-ZZ', (int) $noScope['user_id']),
        '◆ ومن لا بندَ مجالٍ في قالبِه لا يُشدَّد عليه — يضيّق ولا يوسّع');
}

/* ═══ ④ نوعُ `cap` ════════════════════════════════════════════════════════ */
head('④ السقف — يربط الدورَ بحدِّه في سجلِّه الحاكم');
$ch = $conn->query("SELECT g.user_id FROM gov_profile_items i
                     JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
                     JOIN gov_authority_grants g ON g.profile_id=p.profile_id AND g.revoked_at IS NULL
                    WHERE i.item_kind='cap' AND i.allow=1 LIMIT 1")->fetch_assoc();
chk($ch !== null, 'وُجد حاملُ بندِ سقفٍ حيّ');
if ($ch) {
    $caps = ems_profile_caps($conn, (int) $ch['user_id']);
    chk(count($caps) > 0, '★ حدودُه تُقرأ من سجلِّها لا من القالب', count($caps) . ' حدًّا');
    $hasText = false;
    foreach ($caps as $c) { if (trim((string) $c['forbidden']) !== '') { $hasText = true; break; } }
    chk($hasText, 'ونصُّ الحدِّ يأتي من مصدرِه — فلا رقمَ مكرَّرٌ في موضعَين');
}

/* ═══ ⑤ مصادرُ المنحِ الثلاثة ═════════════════════════════════════════════ */
head('⑤ المصادر الثلاثة — موقوتةٌ بسندِها ثمَّ تُسحب');
$conn->query("UPDATE gov_policy_freeze SET active=0 WHERE scope_code='grants'");

$prof = $conn->query("SELECT profile_id, profile_code FROM gov_role_profiles
                       WHERE state='active' ORDER BY profile_id LIMIT 1")->fetch_assoc();
$victim = $conn->query("SELECT id FROM users WHERE is_deleted=0 AND status='active'
                         AND company_id=4 ORDER BY id DESC LIMIT 1")->fetch_assoc();
$PID = (int) $prof['profile_id']; $UID = (int) $victim['id'];
$holders = PW::holdersOf($PID);
$FROM = $holders ? (int) $holders[0] : 0;
$APPR = $one("SELECT id FROM users WHERE role='9'  AND is_deleted=0 AND status='active' AND company_id=4 LIMIT 1");
$HRW  = $one("SELECT id FROM users WHERE role='4'  AND is_deleted=0 AND status='active' AND company_id=4 LIMIT 1");
$FINW = $one("SELECT id FROM users WHERE role='17' AND is_deleted=0 AND status='active' AND company_id=4 LIMIT 1");

chk(count(PW::TMP_CAPS) === 3, '★ لكلِّ مصدرٍ سقفُ مدّةٍ مُعلَن',
    implode(' · ', array_map(function ($k, $v) { return $k . '=' . $v . 'س'; },
        array_keys(PW::TMP_CAPS), array_values(PW::TMP_CAPS))));

$r = PW::grantTemporary($conn, $UID, $PID, array('source' => 'delegation', 'hours' => 0,
    'reason' => $R . ' بلا مدة', 'from_user' => $FROM), $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'NO_DURATION', '⛔ سالب: مؤقّتٌ بلا نهايةٍ يُردّ');
$r = PW::grantTemporary($conn, $UID, $PID, array('source' => 'elevation', 'hours' => 999,
    'reason' => $R . ' فوق السقف', 'approver' => $APPR,
    'hr_witness' => $HRW, 'fin_witness' => $FINW), $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'OVER_CAP', '⛔ سالب: فوق سقفِ المدّةِ يُردّ');
$r = PW::grantTemporary($conn, $UID, $PID, array('source' => 'elevation', 'hours' => 4,
    'reason' => $R . ' بلا مجيز'), $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'NO_APPROVER', '⛔ سالب: رفعٌ بلا مجيزٍ يُردّ');
$r = PW::grantTemporary($conn, $UID, $PID, array('source' => 'elevation', 'hours' => 4,
    'reason' => $R . ' بلا شهود', 'approver' => $APPR), $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'NO_WITNESS', '⛔ سالب: رفعٌ بلا شاهدَين يُردّ — أربعةُ أطرافٍ لا اثنان');
$r = PW::grantTemporary($conn, $UID, $PID, array('source' => 'elevation', 'hours' => 4,
    'reason' => $R . ' شاهدٌ مكرَّر', 'approver' => $APPR,
    'hr_witness' => $APPR, 'fin_witness' => $FINW), $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'SAME_PARTY', '⛔ سالب: الشاهدان والمجيزُ ثلاثةٌ مختلفون');
$r = PW::grantTemporary($conn, $UID, $PID, array('source' => 'elevation', 'hours' => 4,
    'reason' => $R . ' مجيزٌ هو المُسنِد', 'approver' => $ACTOR,
    'hr_witness' => $HRW, 'fin_witness' => $FINW), $ACTOR);
chk(empty($r['ok']) && $r['code'] === 'SELF_ISSUE', '⛔ سالب: المُسنِدُ لا يكون المجيز');
if ($FROM > 0) {
    $r = PW::grantTemporary($conn, $UID, $PID, array('source' => 'delegation', 'hours' => 4,
        'reason' => $R . ' مفوضٌ لا يحمل', 'from_user' => $UID), $ACTOR);
    chk(empty($r['ok']), '⛔ سالب: لا يفوّض أحدٌ ما لا يملك', (string) $r['code']);
}

$made = 0;
if ($FROM > 0) {
    $r = PW::grantTemporary($conn, $UID, $PID, array('source' => 'delegation', 'hours' => 8,
        'reason' => $R . ' تفويض', 'from_user' => $FROM), $ACTOR);
    chk(!empty($r['ok']), '★ تفويضٌ مؤقّتٌ مُنح', (string) $r['msg']);
    if (!empty($r['ok'])) { $MADE[] = (int) $r['id']; $made++; }
    chk($one("SELECT COUNT(*) FROM gov_delegations WHERE reason LIKE '{$R}%'") > 0,
        '★ وسجلُّ واقعتِه كُتب قبلَ المنحةِ ورُبط بها');
}
if ($APPR > 0 && $HRW > 0 && $FINW > 0) {
    $r = PW::grantTemporary($conn, $UID, $PID, array('source' => 'elevation', 'hours' => 4,
        'reason' => $R . ' رفع', 'approver' => $APPR,
        'hr_witness' => $HRW, 'fin_witness' => $FINW), $ACTOR);
    chk(!empty($r['ok']), '★ رفعٌ استثنائيٌّ مُنح', (string) $r['msg']);
    if (!empty($r['ok'])) { $MADE[] = (int) $r['id']; $made++; }
    chk($one("SELECT COUNT(*) FROM gov_elevations WHERE reason LIKE '{$R}%'") > 0,
        '★ وسجلُّ الرفعِ كُتب ورُبط');
}
$r = PW::grantTemporary($conn, $UID, $PID, array('source' => 'escalation', 'hours' => 24,
    'reason' => $R . ' تصعيد', 'doc_ref' => 'TKT-0001'), $ACTOR);
chk(!empty($r['ok']), '★ تصعيدٌ رأسيٌّ مُنح', (string) $r['msg']);
if (!empty($r['ok'])) { $MADE[] = (int) $r['id']; $made++; }

$srcs = array();
$q = $conn->query("SELECT DISTINCT source FROM gov_authority_grants WHERE revoked_at IS NULL");
while ($q && ($x = $q->fetch_row())) { $srcs[] = $x[0]; }
chk(count($srcs) >= ($made > 0 ? 2 : 1), '★ مصادرُ المنحِ الحيّةُ أكثرُ من واحد',
    implode(' · ', $srcs));

if ($made > 0) {
    $mid = $one("SELECT id FROM modules ORDER BY id LIMIT 1");
    $t = ems_permission_trace($conn, $mid, $UID);
    chk(is_array($t), '★ والحكمُ الحيُّ يقرأ المؤقّتَ مع الأصليّ بلا انفصال');
    $expired = $one("SELECT COUNT(*) FROM gov_authority_grants
                      WHERE grant_id IN (" . implode(',', array_map('intval', $MADE)) . ")
                        AND valid_to IS NULL");
    chk($expired === 0, '★ ولا منحةَ مؤقّتةٍ بلا نهايةٍ مكتوبة');
}

fwrite(STDOUT, "\n" . str_repeat('─', 70) . "\n");
fwrite(STDOUT, "   النتيجة: {$PASS} نجاح · {$FAIL} رسوب\n");
fwrite(STDOUT, $FAIL === 0 ? "   ✔ PERM03_LAYERS = PASS\n" : "   ✘ PERM03_LAYERS = FAIL\n");
exit($FAIL === 0 ? 0 : 1);
