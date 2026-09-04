<?php
/**
 * tests/perm01_policy_write_service.php — بابُ الكتابةِ واحدٌ ومحروس (م-0)
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01-DEC §0-3: «كلُّ كتابةٍ في جداولِ الصلاحيّاتِ تمرُّ بمنفذِ الكتابةِ
 * المحروس» · وقبولُ ق-١: «صفرُ شاشاتٍ أخرى تكتب في سجلِّ المنح».
 *
 * ◆ **والبابُ يُقاس ببنيتِه وبسلوكِه معًا**: أن يوجد المنفذُ لا يكفي إن بقيت
 *   أبوابٌ أخرى مفتوحةً — فيُمسح الإنتاجُ بحثًا عن كاتبٍ مباشرٍ في سجلِّ المنح.
 * ⛔ **والتجميدُ يُختبَر بأنّه يَرُدّ**: بوّابةٌ لا تُجرَّب ليست بوّابة.
 * ⛔ **والحالُ يُستعاد حتمًا**: يُرفع التجميدُ لحظةً لاختبارِ الإسنادِ الناجح،
 *   ثمَّ يُعاد في `register_shutdown_function` — وتركُ التجميدِ مرفوعًا يفتح
 *   بابَ منحٍ لم يأذن به المالك.
 *
 * التشغيل: php tests/perm01_policy_write_service.php
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

/* جلسةُ فاعلٍ من إدارةِ الصلاحيات — الخدمةُ تكتب بالبوّابةِ فتحتاج نطاقًا. */
$admin = $conn->query("SELECT id, role, company_id FROM users
                        WHERE role='15' AND is_deleted=0 AND status='active' AND company_id=4
                        LIMIT 1")->fetch_assoc();
$_SESSION['user'] = $admin
    ? array('id'=>(int)$admin['id'],'role'=>'15','company_id'=>4,'name'=>'policy write probe')
    : array('id'=>0,'role'=>'15','company_id'=>4,'name'=>'policy write probe');

fwrite(STDOUT, "\n══ م-0 — منفذُ كتابةِ السياسةِ الواحد ══\n");

head('① البنيةُ — بابٌ واحدٌ وصفرُ أبوابٍ أخرى');
chk(class_exists('App\\Services\\Security\\PolicyWriteService'), '★ المنفذُ مُعلَن');
$root = dirname(__DIR__);
$skip = array('/tests/','/tools/','/docs/','/vendor/','/storage/','/.git/','/database/','/node_modules/');
$writers = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,
        FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS));
foreach ($it as $f) {
    $p = $f->getPathname();
    if (substr($p, -4) !== '.php') { continue; }
    foreach ($skip as $s) { if (strpos($p, $s) !== false) { continue 2; } }
    if (strpos($p, 'PolicyWriteService.php') !== false) { continue; }
    $src = (string) @file_get_contents($p);
    if (preg_match('~(INSERT\s+INTO|UPDATE)\s+`?gov_authority_grants`?~i', $src)) {
        $writers[] = str_replace($root . '/', '', $p);
    }
}
chk(count($writers) === 0,
    '★★ **صفرُ ملفِّ إنتاجٍ يكتب في سجلِّ المنحِ خارجَ المنفذ**',
    $writers ? implode(' · ', array_slice($writers, 0, 4)) : 'صفر');

head('② الحرّاسُ يردّون — ولا كتابةَ بلا شرط');
$prof = $conn->query("SELECT profile_id FROM gov_role_profiles WHERE state='active' LIMIT 1")->fetch_assoc();
$draft = $conn->query("SELECT profile_id FROM gov_role_profiles WHERE state='draft' LIMIT 1")->fetch_assoc();
$granted = $conn->query("SELECT g.user_id FROM gov_authority_grants g
                          JOIN users u ON u.id=g.user_id AND u.is_deleted=0 AND u.status='active'
                         WHERE g.revoked_at IS NULL LIMIT 1")->fetch_assoc();

$r = PW::assignProfile($conn, (int) $granted['user_id'], (int) $prof['profile_id'], '');
chk(!$r['ok'] && $r['code'] === 'NO_REASON', '★ إسنادٌ بلا سببٍ يُردّ', $r['code']);

$frozen = PW::isFrozen(PW::FREEZE_GRANTS);
chk($frozen === true, '★ التجميدُ مقروءٌ ونافذٌ الآن', $frozen ? 'مجمَّد' : 'مفتوح');
$r = PW::assignProfile($conn, (int) $granted['user_id'], (int) $prof['profile_id'], 'شاهد اختبار');
chk(!$r['ok'] && $r['code'] === 'FROZEN',
    '★★ **التجميدُ يردُّ الإسنادَ** — وهو نصُّ قبولِ ق-١', $r['code']);

head('③ الإسنادُ يعمل — والتجميدُ يُرفع لحظةً ثمَّ يُعاد');
/* ⛔ الاستعادةُ تُسجَّل قبلَ الرفع. */
$RESTORE = array();
register_shutdown_function(static function () use ($conn, &$RESTORE) {
    $conn->query("UPDATE gov_policy_freeze SET active=1 WHERE scope_code='grants'");
    foreach ($RESTORE as $gid) { $conn->query("DELETE FROM gov_authority_grants WHERE grant_id=" . (int) $gid); }
    $conn->query("DELETE FROM perm_change_log WHERE source_screen LIKE 'PolicyWriteService::%'
                    AND reason = 'شاهد م-0 — يحذف فورا'");
});

/* فاعلٌ حيٌّ بلا منحةٍ نافذة — وإن لم يوجد فلا معنى للقياس. */
$free = $conn->query("SELECT u.id FROM users u
                       WHERE u.is_deleted=0 AND u.status='active' AND u.company_id=4
                         AND NOT EXISTS(SELECT 1 FROM gov_authority_grants g
                                         WHERE g.user_id=u.id AND g.revoked_at IS NULL)
                       LIMIT 1")->fetch_assoc();
if (!$free) {
    /* كلُّ الأحياءِ ممنوحون — فيُسحب واحدٌ لحظةً ليُعاد إسنادُه. */
    $g0 = $conn->query("SELECT grant_id, user_id FROM gov_authority_grants
                         WHERE revoked_at IS NULL LIMIT 1")->fetch_assoc();
    $free = array('id' => $g0['user_id']);
    $GID0 = (int) $g0['grant_id'];
    register_shutdown_function(static function () use ($conn, $GID0) {
        $conn->query("UPDATE gov_authority_grants SET revoked_at=NULL WHERE grant_id={$GID0}");
    });
    $conn->query("UPDATE gov_authority_grants SET revoked_at=NOW() WHERE grant_id={$GID0}");
}
chk($free !== null, 'وُجد فاعلٌ بلا منحةٍ نافذةٍ للقياس', $free ? ('#' . $free['id']) : 'لا شيء');

$conn->query("UPDATE gov_policy_freeze SET active=0 WHERE scope_code='grants'");
chk(PW::isFrozen(PW::FREEZE_GRANTS) === false, 'رُفع التجميدُ لحظةَ القياس');

$before = $one("SELECT COUNT(*) FROM perm_change_log");
$r = PW::assignProfile($conn, (int) $free['id'], (int) $prof['profile_id'], 'شاهد م-0 — يحذف فورا', 0);
chk($r['ok'], '★★ **الإسنادُ يعمل من المنفذ**', $r['code'] . ' · ' . $r['msg']);
if ($r['ok']) { $RESTORE[] = $r['id']; }
$after = $one("SELECT COUNT(*) FROM perm_change_log");
chk($after === $before + 1, '★★ **وسطرُ الأثرِ كُتب في المعاملةِ نفسِها**', "قبل={$before} بعد={$after}");

/* قالبٌ ثانٍ للفاعلِ نفسِه يُردّ — قالبٌ واحدٌ لكلِّ موظّف. */
$r2 = PW::assignProfile($conn, (int) $free['id'], (int) $prof['profile_id'], 'محاولة ثانية');
chk(!$r2['ok'] && $r2['code'] === 'ALREADY_GRANTED',
    '★★ **قالبٌ واحدٌ لكلِّ فاعل** — والثاني يُردّ', $r2['code']);

if ($draft) {
    $r3 = PW::assignProfile($conn, (int) $free['id'], (int) $draft['profile_id'], 'مسودة');
    chk(!$r3['ok'], '★ ولا تُسنَد مسودّة', $r3['code']);
}

head('④ السحبُ يعمل ويكتب أثرَه');
if (!empty($RESTORE)) {
    $rv = PW::revokeGrant($conn, (int) $RESTORE[0], 'شاهد م-0 — يحذف فورا', 0);
    chk($rv['ok'], '★ السحبُ من المنفذِ يعمل', $rv['code'] . ' · ' . $rv['msg']);
    $rv2 = PW::revokeGrant($conn, (int) $RESTORE[0], 'مرة ثانية');
    chk(!$rv2['ok'] && $rv2['code'] === 'ALREADY_REVOKED', '★ ولا يُسحب المسحوبُ مرّتَين', $rv2['code']);
}

$conn->query("UPDATE gov_policy_freeze SET active=1 WHERE scope_code='grants'");
chk(PW::isFrozen(PW::FREEZE_GRANTS) === true, '★ وأُعيد التجميدُ كما كان');

fwrite(STDOUT, "\n──────────────────────────────────────────────────────────\n");
fwrite(STDOUT, "النتيجة: {$PASS} نجاح · {$FAIL} رسوب\n");
fwrite(STDOUT, $FAIL === 0 ? "✔ POLICY_WRITE_SERVICE = PASS\n" : "✘ بابُ الكتابةِ غيرُ محروس\n");
exit($FAIL === 0 ? 0 : 1);
