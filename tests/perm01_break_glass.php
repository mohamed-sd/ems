<?php
/**
 * tests/perm01_break_glass.php — الفتحُ الاضطراريُّ موصولٌ ومقيَّد (م-2 · ق-٢)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الحالُ قبلَ هذا البناء**: الآليّةُ مبنيّةٌ بالكامل — خدمةٌ تكتب الاستثناءَ
 *   وسطرَ تدقيقِه، ومهمّةٌ دوريّةٌ تُنهي المنتهي، و**371 حدثَ تدقيقٍ مسجَّلًا** —
 *   و`get_module_permissions` **لا تقرأ جدولَ الاستثناءاتِ إطلاقًا**. فهي
 *   مبنيّةٌ ولا تفتح شيئًا، والنظامُ صار مغلقًا افتراضيًّا. وذلك نصُّ ما حذّر
 *   منه §7-④: «ولا يُنقَل مستخدمٌ إلى الإغلاقِ الافتراضيِّ قبلَ وجودِه».
 *
 * ⛔ **ويُقاس بشروطِ القبولِ الخمسةِ نصًّا**: الحارسُ يقرأ · الحيُّ يفتح ولا يغلق
 *   غيرَه · المنتهي لا يفتح · `never` يُرفض · والسقوفُ والمجيزون مقيَّدون.
 * ⛔ **وكلُّ صفٍّ يُصنَع يُحذف** في `register_shutdown_function` — واستثناءٌ
 *   يُترك مفتوحًا بابٌ لم يأذن به أحد.
 *
 * التشغيل: php tests/perm01_break_glass.php
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

/* ⛔ التنظيفُ يُسجَّل قبلَ أيِّ إنشاء. */
register_shutdown_function(static function () use ($conn) {
    $conn->query("DELETE FROM permission_exceptions WHERE reason LIKE '%شاهد كسر الزجاج%'");
    $conn->query("DELETE FROM perm_change_log WHERE reason LIKE '%شاهد كسر الزجاج%'");
});

fwrite(STDOUT, "\n══ م-2 — الفتحُ الاضطراريُّ يفتح ولا يغلق ══\n");

head('① البنيةُ — الحارسُ يقرأ جدولَ الاستثناءات');
chk(function_exists('ems_break_glass_open'), '★ قارئُ الاستثناءِ مُعلَن');
$helper = (string) @file_get_contents(dirname(__DIR__) . '/includes/permissions_helper.php');
$pf = strpos($helper, 'function get_module_permissions(');
$pe = $pf === false ? false : strpos($helper, "\nfunction ", $pf + 10);
$body = $pf === false ? '' : substr($helper, $pf, ($pe === false ? strlen($helper) : $pe) - $pf);
chk($body !== '' && strpos($body, 'ems_break_glass_open') !== false,
    '★★ **قرارُ فتحِ الشاشةِ يستشير الفتحَ الاضطراريّ** — وهو نصُّ قبولِ ق-٢');

/* ⛔ **وآليّةٌ لا يبلغها المحتاجُ إليها ليست آليّةَ طوارئ**: بعدَ الوصلِ بقيت
     بلا شاشةٍ في الإنتاجِ تنادِيها، فكان الفتحُ يلزمه من يشغّل PHP. */
$callers = array();
$rootDir = dirname(__DIR__);
$skipDirs = array('/tests/', '/tools/', '/docs/', '/vendor/', '/storage/', '/.git/', '/database/');
$itC = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootDir,
        FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS));
foreach ($itC as $fC) {
    $pC = $fC->getPathname();
    if (substr($pC, -4) !== '.php') { continue; }
    foreach ($skipDirs as $sC) { if (strpos($pC, $sC) !== false) { continue 2; } }
    if (strpos($pC, 'PolicyWriteService.php') !== false) { continue; }
    if (strpos((string) @file_get_contents($pC), 'openException') !== false) {
        $callers[] = str_replace($rootDir . '/', '', $pC);
    }
}
chk(count($callers) >= 1,
    '★★ **وللآليّةِ يدٌ في الإنتاج** — شاشةٌ تنادي المنفذَ لا اختباراتٌ وحدَها',
    $callers ? implode(' · ', array_slice($callers, 0, 3)) : 'لا مُنادي');

head('② الحرّاسُ يردّون — والقيودُ الخمسة');
$subject = $conn->query("SELECT u.id, u.role FROM users u
                          WHERE u.is_deleted=0 AND u.status='active' AND u.company_id=4
                            AND u.role <> '15' ORDER BY u.id LIMIT 1")->fetch_assoc();
$app15 = $conn->query("SELECT id FROM users WHERE role='15' AND is_deleted=0
                        AND status='active' AND company_id=4 LIMIT 1")->fetch_assoc();
$appGov = $conn->query("SELECT id FROM users WHERE role='9' AND is_deleted=0
                         AND status='active' AND company_id=4 LIMIT 1")->fetch_assoc();
$appFin = $conn->query("SELECT id FROM users WHERE role='19' AND is_deleted=0
                         AND status='active' AND company_id=4 LIMIT 1")->fetch_assoc();
chk($subject && $app15 && $appGov, 'وُجد فاعلٌ ومجيزون للقياس',
    $subject ? ('طالب #' . $subject['id'] . ' · مجيز #' . ($appGov['id'] ?? '—')) : 'لا شيء');

$never = $conn->query("SELECT guard_code FROM guard_override_policies
                        WHERE overridable='never' LIMIT 1")->fetch_assoc();
$r = PW::openException($conn, (int) $subject['id'], $never['guard_code'],
        array('approver_gov' => (int) $appGov['id'], 'reason' => 'شاهد كسر الزجاج'));
chk(!$r['ok'] && $r['code'] === 'GUARD_NEVER',
    '★★ **حارسُ never يُرفض** — ولا كسرَ له مهما كان السبب', $r['code'] . ' · ' . $never['guard_code']);

$r = PW::openException($conn, (int) $subject['id'], 'Clients/clients.php',
        array('approver_gov' => (int) $app15['id'], 'reason' => 'شاهد كسر الزجاج'));
chk(!$r['ok'] && $r['code'] === 'ROLE15_APPROVER',
    '★★ **الدورُ 15 لا يجيز بحال**', $r['code']);

$r = PW::openException($conn, (int) $subject['id'], 'Clients/clients.php',
        array('approver_gov' => (int) $subject['id'], 'reason' => 'شاهد كسر الزجاج'));
chk(!$r['ok'] && $r['code'] === 'SELF_APPROVE', '★ والطالبُ لا يجيز لنفسِه', $r['code']);

$r = PW::openException($conn, (int) $subject['id'], 'Finance/depr_run.php',
        array('approver_gov' => (int) $appGov['id'], 'reason' => 'شاهد كسر الزجاج'));
chk(!$r['ok'] && $r['code'] === 'DUAL_REQUIRED',
    '★★ **الماليّةُ لا تُستثنى بل تشتدّ** — ثنائيّةُ مجيزَين', $r['code']);

$r = PW::openException($conn, (int) $subject['id'], 'Clients/clients.php',
        array('approver_gov' => (int) $appGov['id'], 'hours' => 12, 'reason' => 'شاهد كسر الزجاج'));
chk(!$r['ok'] && $r['code'] === 'OVER_CAP', '★★ **السقفُ 4 ساعاتٍ بمجيزٍ واحد**', $r['code']);

$r = PW::openException($conn, (int) $subject['id'], 'Clients/clients.php',
        array('approver_gov' => (int) $appGov['id'], 'reason' => ''));
chk(!$r['ok'] && $r['code'] === 'NO_REASON', '★ ولا فتحَ بلا سببٍ مكتوب', $r['code']);

head('③ **الفتحُ يعمل** — والحارسُ يراه');
/* شاشةٌ خارجَ قالبِ الفاعلِ يقينًا — فالمنعُ قبلَ الفتحِ شرطُ القياس. */
$closed = $conn->query("SELECT m.id, m.code FROM modules m
                         WHERE m.code LIKE '%.php'
                           AND NOT EXISTS(SELECT 1 FROM gov_authority_grants g
                                 JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
                                 JOIN gov_profile_items i ON i.profile_id=p.profile_id
                                      AND i.item_kind='screen' AND i.allow=1 AND i.item_ref=m.code
                                WHERE g.user_id=" . (int) $subject['id'] . " AND g.revoked_at IS NULL)
                           AND m.code NOT LIKE 'Finance/%' AND m.code NOT LIKE 'Governance/%'
                         ORDER BY m.id LIMIT 1")->fetch_assoc();
chk($closed !== null, 'وُجدت شاشةٌ مغلقةٌ عليه للقياس', $closed ? $closed['code'] : 'لا شيء');

$prev = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$asSubject = function () use (&$subject) {
    $_SESSION['user'] = array('id' => (int) $subject['id'], 'role' => (string) $subject['role'],
                              'company_id' => 4, 'name' => 'break glass probe');
};
$asSubject();
$before = !empty(get_module_permissions($conn, (int) $closed['id'])['can_view']);
chk($before === false, '★ الشاشةُ ممنوعةٌ عليه قبلَ الفتح — وبلا منعٍ لا معنى للقياس');

/* الفتحُ يُكتب بجلسةِ الحوكمةِ (البوّابةُ تحقن النطاق). */
$_SESSION['user'] = array('id' => (int) $appGov['id'], 'role' => '9', 'company_id' => 4, 'name' => 'gov');
$r = PW::openException($conn, (int) $subject['id'], $closed['code'],
        array('approver_gov' => (int) $appGov['id'], 'hours' => 4, 'reason' => 'شاهد كسر الزجاج'));
chk($r['ok'], '★★ **الفتحُ الاضطراريُّ يُكتب من المنفذ**', $r['code'] . ' · ' . $r['msg']);
$EX = (int) $r['id'];

$asSubject();
$after = !empty(get_module_permissions($conn, (int) $closed['id'])['can_view']);
chk($after === true, '★★★ **والحارسُ فتح بالاستثناء** — الآليّةُ صارت تفتح فعلًا',
    'can_view=' . var_export($after, true));

head('④ يفتح ولا يغلق غيرَه · والمنتهي لا يفتح');
$other = $conn->query("SELECT g.user_id, u.role, m.id mid FROM gov_authority_grants g
                        JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
                        JOIN gov_profile_items i ON i.profile_id=p.profile_id
                             AND i.item_kind='screen' AND i.allow=1
                        JOIN modules m ON m.code=i.item_ref
                        JOIN users u ON u.id=g.user_id AND u.is_deleted=0 AND u.status='active'
                       WHERE g.revoked_at IS NULL AND g.user_id <> " . (int) $subject['id'] . "
                       LIMIT 1")->fetch_assoc();
if ($other) {
    $_SESSION['user'] = array('id' => (int) $other['user_id'], 'role' => (string) $other['role'],
                              'company_id' => 4, 'name' => 'other');
    $stillOk = !empty(get_module_permissions($conn, (int) $other['mid'])['can_view']);
    chk($stillOk, '★★ **استثناءُ غيرِه لم يغلق عليه شيئًا** — يفتح ولا يغلق');
}

/* المنتهي: يُقدَّم `valid_to` إلى الماضي فيجب أن يُغلق فورًا. */
$conn->query("UPDATE permission_exceptions SET valid_to = DATE_SUB(NOW(), INTERVAL 1 MINUTE)
               WHERE ex_id = {$EX}");
$asSubject();
$expired = !empty(get_module_permissions($conn, (int) $closed['id'])['can_view']);
chk($expired === false,
    '★★ **المنتهي لا يفتح** — ولا يُنتظَر مرورُ المهمّةِ الدوريّة',
    'can_view=' . var_export($expired, true));

/* والمسحوبُ لا يفتح. */
$conn->query("UPDATE permission_exceptions SET valid_to = DATE_ADD(NOW(), INTERVAL 1 HOUR),
                     state = 'revoked' WHERE ex_id = {$EX}");
$asSubject();
$revoked = !empty(get_module_permissions($conn, (int) $closed['id'])['can_view']);
chk($revoked === false, '★ والمسحوبُ لا يفتح ولو بقي وقتُه', 'can_view=' . var_export($revoked, true));

if ($prev === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $prev; }

head('⑤ **المنتهي تنهيه المهمّةُ الدوريّة** — لا الحارسُ وحدَه');
/* ◆ **نصُّ قبولِ ق-٢**: «المنتهي **تنهيه المهمّةُ الدوريّة**». والحارسُ يمنعه
     بشرطِ `valid_to` فورًا (فحصٌ سابق) — لكنَّ الحالةَ تبقى `active` في المخزنِ
     حتى تمرَّ المهمّة، فيُقرأ السجلُّ «استثناءٌ حيٌّ» وهو منتهٍ. فيُختبر الشقّان.
   ⛔ **وتشغيلُ المهمّةِ لا يُفترَض من تعليقٍ**: نُودِيت فعلًا وقِيست الحالةُ قبلَها
     وبعدَها — ومطابقةُ عبارةٍ في شرحٍ أخضرُ كاذب. */
require_once dirname(__DIR__) . '/app/Services/Security/ExpiryJob.php';
$exp = $conn->query("SELECT ex_id FROM permission_exceptions
                      WHERE reason LIKE '%شاهد كسر الزجاج%' LIMIT 1")->fetch_assoc();
chk($exp !== null, 'وُجد استثناءُ الشاهدِ للقياس', $exp ? ('#' . $exp['ex_id']) : 'لا شيء');
if ($exp) {
    $EXID = (int) $exp['ex_id'];
    $conn->query("UPDATE permission_exceptions
                     SET state='active', valid_to = DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                   WHERE ex_id = {$EXID}");
    $before = (string) $conn->query("SELECT state FROM permission_exceptions
                                      WHERE ex_id={$EXID}")->fetch_row()[0];
    chk($before === 'active', 'الحالُ قبلَ المهمّة: حيٌّ ومنتهٍ — وبلا ذلك لا معنى للقياس',
        "state={$before}");

    \App\Services\Security\ExpiryJob::run($conn);

    $after = (string) $conn->query("SELECT state FROM permission_exceptions
                                     WHERE ex_id={$EXID}")->fetch_row()[0];
    chk($after === 'expired',
        '★★ **المهمّةُ الدوريّةُ أنهت المنتهي فعلًا** — نُودِيت وقِيس أثرُها',
        "state={$before} ⇐ {$after}");
}

head('⑥ الأثرُ مكتوب');
$n = (int) $conn->query("SELECT COUNT(*) FROM perm_change_log
                          WHERE reason LIKE '%شاهد كسر الزجاج%'")->fetch_row()[0];
chk($n >= 1, '★ سطرُ أثرٍ لكلِّ فتحٍ اضطراريّ', "صفوف={$n}");

fwrite(STDOUT, "\n──────────────────────────────────────────────────────────\n");
fwrite(STDOUT, "النتيجة: {$PASS} نجاح · {$FAIL} رسوب\n");
fwrite(STDOUT, $FAIL === 0 ? "✔ BREAK_GLASS = PASS\n" : "✘ الفتحُ الاضطراريُّ غيرُ موصولٍ أو غيرُ مقيَّد\n");
exit($FAIL === 0 ? 0 : 1);
