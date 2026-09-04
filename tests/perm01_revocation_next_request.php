<?php
/**
 * tests/perm01_revocation_next_request.php — السحبُ يسري في أوّلِ طلب (PERM-01 §7-②)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلبُ نصًّا**: «سُحبت صلاحيةٌ ومستخدمُها متّصل — فمتى يسري السحب؟ ولا
 *   يُقبل انتظارُ خروجِه، خصوصًا في المالية … ويُختبر: سُحبت عند لحظةٍ ⇒ **أولُ
 *   طلبٍ بعدها منع**».
 *
 * ◆ **والقياسُ على مسارِ القرارِ نفسِه** لا على استعلامٍ موازٍ: تُنادى
 *   `get_module_permissions` قبلَ السحبِ وبعدَه مباشرةً — فإن بقي السماحُ فثمّةَ
 *   مخبأٌ يُبقي قرارًا ميّتًا حيًّا.
 *
 * ⛔ **والحالةُ تُستعاد حتمًا**: الاستعادةُ في `register_shutdown_function` فلا
 *   تُعلَّق على نتيجةِ الفحصِ ولا على اكتمالِ السكربت — سحبٌ لا يُعاد يقطع
 *   مستخدمًا حيًّا عن عملِه.
 *
 * ⛔ **والضابطُ الموجبُ شرطٌ**: منعٌ يقع على كلِّ حالٍ ليس أثرَ سحبٍ بل تعطيلًا.
 *
 * التشغيل: php tests/perm01_revocation_next_request.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/permissions_helper.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function chk($c, $m, $d = '') { $c ? ok($m . ($d !== '' ? " — {$d}" : '')) : bad($m . ($d !== '' ? " — {$d}" : '')); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

fwrite(STDOUT, "\n══ PERM-01 §7-② — السحبُ يسري في أوّلِ طلب ══\n");

head('البذر — فاعلٌ مغطًّى وشاشةٌ داخلَ قالبِه');
$row = $conn->query("
  SELECT u.id uid, u.role rid, g.grant_id gid, m.id mid, m.code
    FROM users u
    JOIN gov_authority_grants g ON g.user_id = u.id AND g.revoked_at IS NULL
         AND (g.valid_to IS NULL OR g.valid_to > NOW())
    JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
    JOIN gov_profile_items i ON i.profile_id = p.profile_id AND i.item_kind = 'screen' AND i.allow = 1
    JOIN modules m ON m.code = i.item_ref
   WHERE u.is_deleted = 0 AND u.status = 'active' AND u.company_id = 4
   ORDER BY u.id LIMIT 1")->fetch_assoc();
chk($row !== null, 'وُجد الزوجُ المطلوب — وبلا زوجٍ لا معنى للقياس',
    $row ? ('مستخدم #' . $row['uid'] . ' · منحة #' . $row['gid'] . ' · ' . $row['code']) : 'لا شيء');
if (!$row) { fwrite(STDOUT, "\nالنتيجة: {$PASS} نجاح · {$FAIL} رسوب\n"); exit(1); }

$GID = (int) $row['gid'];
$MID = (int) $row['mid'];
$_SESSION['user'] = array('id' => (int) $row['uid'], 'role' => (string) $row['rid'],
                          'company_id' => 4, 'name' => 'revocation probe');

/* ⛔ الاستعادةُ أوّلًا — تُسجَّل قبلَ أيِّ تغييرٍ فلا يُترك سحبٌ قائمًا. */
register_shutdown_function(static function () use ($conn, $GID) {
    $conn->query("UPDATE gov_authority_grants
                     SET revoked_at = NULL,
                         reason = REPLACE(reason, ' | سحب اختبار — يعاد فورا', '')
                   WHERE grant_id = {$GID}");
    $conn->query("DELETE FROM perm_change_log WHERE source_screen = 'tests/perm01_revocation'");
});

head('① **قبل السحب** — الضابطُ الموجب');
$before = get_module_permissions($conn, $MID);
chk(!empty($before['can_view']), '★ الشاشةُ مفتوحةٌ بقالبِه',
    'can_view=' . var_export(!empty($before['can_view']), true));

head('② **السحب** — ثمَّ أوّلُ طلبٍ مباشرةً');
$conn->query("UPDATE gov_authority_grants
                 SET revoked_at = NOW(), reason = CONCAT(reason, ' | سحب اختبار — يعاد فورا')
               WHERE grant_id = {$GID} AND revoked_at IS NULL");
$affected = $conn->affected_rows;
chk($affected === 1, 'سُحبت المنحةُ فعلًا — وبلا سحبٍ لا معنى للقياس', "صفوف={$affected}");

$after = get_module_permissions($conn, $MID);
chk(empty($after['can_view']),
    '★★ **أوّلُ طلبٍ بعدَ السحبِ منعٌ** — لا مخبأَ يُبقي قرارًا ميّتًا حيًّا',
    'can_view=' . var_export(!empty($after['can_view']), true));

/* وبعدَ السحبِ صار غيرَ مغطًّى ⇒ يسقط للمسارِ القائم؛ فالمنعُ هنا يعني أنَّ
   الجدولَ القديمَ أيضًا لا يمنحه — أو أنَّ الحكمَ صار منعًا. ويُسمّى أيُّهما. */
$legacy = (int) $conn->query("SELECT COUNT(*) FROM role_permissions
                               WHERE role_id = " . (int) $row['rid'] . "
                                 AND module_id = {$MID} AND can_view = 1")->fetch_row()[0];
fwrite(STDOUT, '  ◆ وبعدَ السحبِ صار غيرَ مغطًّى — والجدولُ القديمُ '
    . ($legacy ? 'يمنحه هذه الشاشة (فالمنعُ لو وقع فمن حكمٍ آخر)' : 'لا يمنحه إيّاها') . "\n");

head('③ **الاستعادة** — وأوّلُ طلبٍ بعدها سماح');
$conn->query("UPDATE gov_authority_grants
                 SET revoked_at = NULL, reason = REPLACE(reason, ' | سحب اختبار — يعاد فورا', '')
               WHERE grant_id = {$GID}");
$back = get_module_permissions($conn, $MID);
chk(!empty($back['can_view']),
    '★ والإعادةُ تسري في أوّلِ طلبٍ أيضًا — فالأثرُ لحظيٌّ في الاتّجاهَين',
    'can_view=' . var_export(!empty($back['can_view']), true));

head('④ **الأثر** — سجلُّ التغييرِ يكتب');
require_once dirname(__DIR__) . '/includes/perm_change_log.php';
$wrote = ems_perm_change_log($conn, 'grant', 'revoke', array(
    'subject_kind' => 'user', 'subject_id' => (int) $row['uid'],
    'before' => 'نافذة', 'after' => 'مسحوبة',
    'reason' => 'شاهد اختبار السحب', 'source' => 'tests/perm01_revocation'));
chk($wrote, '★ سطرُ الأثرِ كُتب — «من سحب ومتى ولماذا» له جواب');
$n = (int) $conn->query("SELECT COUNT(*) FROM perm_change_log
                          WHERE source_screen = 'tests/perm01_revocation'")->fetch_row()[0];
chk($n === 1, 'ويُقرأ من السجلّ', "صفوف={$n}");

fwrite(STDOUT, "\n──────────────────────────────────────────────────────────\n");
fwrite(STDOUT, "النتيجة: {$PASS} نجاح · {$FAIL} رسوب\n");
fwrite(STDOUT, $FAIL === 0
    ? "✔ REVOCATION_NEXT_REQUEST_TEST = PASS\n"
    : "✘ أثرُ السحبِ غيرُ مُثبَت\n");
exit($FAIL === 0 ? 0 : 1);
