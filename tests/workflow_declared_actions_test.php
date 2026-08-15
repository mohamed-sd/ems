<?php
/**
 * tests/workflow_declared_actions_test.php — فعلٌ مُعلَنٌ صار له وجود
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0391 · INJ-0404 · INJ-0264
 *
 * · INJ-0391: «اسحب قبولًا بسببٍ مكتوب: **تمتلئ الأعمدةُ**، وتعود حالةُ الخطرِ
 *   إلى `reassessment`، ويُنشر الحدث».
 * · INJ-0404: «كلُّ صفٍّ في `personal_notifications` بـ`requires_action=1` يحمل
 *   `task_item_id` **غيرَ فارغٍ خلال الثانيةِ نفسِها**، ويظهر عنصرُه في
 *   `Portal/my_tasks.php`».
 * · INJ-0264: «محاولةُ إغلاقٍ إداريٍّ لبلاغِ شكوى أو سلامةٍ **تُرفض ٤٠٣ برسالةٍ
 *   تسمّي السياسة**؛ والإغلاقُ الإداريُّ الخاطئ **يُعكَس بزرٍّ ينتج حركةً
 *   مرتبطةً بالأصل**».
 *
 * ◆ الجامعُ بينها: **حقلٌ يُصيَّر ولا يُكتب** — والشاشةُ التي تعرض حقلًا لا
 *   يُكتب أبدًا تكذب على قارئها. فإمّا يُبنى الفعلُ أو يُنزع العرض.
 * ◆ والوسمُ عائليٌّ ثابتٌ والكنسُ به، وما يُلمس من بياناتِ الإنتاجِ يُعاد.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/app/Services/Risk/RiskService.php';
require_once $ROOT . '/app/Services/Work/WorkItemService.php';

use App\Services\Risk\RiskService as RSK;
use App\Services\Work\WorkItemService as WIS;

$conn = $GLOBALS['conn'];
$CO   = 4;
$TAG  = 'WFD-TEST-FAMILY';
$PASS = 0; $FAIL = 0; $NOTES = array();
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ فعلٌ مُعلَنٌ صار له وجود');

$sweep = function () use ($conn, $TAG) {
    $conn->query("DELETE FROM work_items WHERE title LIKE '%{$TAG}%' OR source_ref IN
                  (SELECT CONCAT('notification#', id) FROM personal_notifications WHERE title LIKE '%{$TAG}%')");
    $conn->query("DELETE FROM personal_notifications WHERE title LIKE '%{$TAG}%'");
    $r = $conn->query("SELECT COUNT(*) FROM personal_notifications WHERE title LIKE '%{$TAG}%'");
    return ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
};
$ok($sweep() === 0, 'الكنسُ القبليُّ نظيفٌ بالعائلة');

/* ── ① INJ-0404 · التنبيهُ يتطلب فعلًا ⇒ مهمتُه في اللحظةِ نفسِها ─────────── */
$nid = WIS::notifyUser($conn, $CO, 1, 'إخطارٌ يتطلب فعلًا ' . $TAG, 'جسمُ الإخطار', 'x.php', 1, 1);
$ok((int) $nid > 0, "تنبيهٌ «يتطلب فعلًا» أُنشئ (#{$nid})");
$row = null;
if ($nid > 0) {
    $rr = $conn->query("SELECT requires_action, task_item_id, created_at FROM personal_notifications WHERE id = " . (int) $nid);
    $row = $rr ? $rr->fetch_assoc() : null;
}
$ok($row && (int) $row['requires_action'] === 1 && (int) $row['task_item_id'] > 0,
    '**ويحمل `task_item_id` غيرَ فارغ** — لا وعدَ بفعلٍ بلا مهمة');
if ($row && (int) $row['task_item_id'] > 0) {
    $tr = $conn->query("SELECT id, title, status, assigned_user_id, source_ref, created_at
                          FROM work_items WHERE id = " . (int) $row['task_item_id']);
    $task = $tr ? $tr->fetch_assoc() : null;
    $ok($task !== null, 'والمهمةُ موجودةٌ فعلًا في `work_items` — لا مفتاحَ معلَّق');
    $ok($task && (string) $task['source_ref'] === ('notification#' . (int) $nid),
        'وتشير إلى تنبيهِها بمرجعٍ صريح');
    $ok($task && (int) $task['assigned_user_id'] === 1,
        'ومُسنَدةٌ لصاحبِ الإخطار — فتظهر في «مهامي»');
    /* «خلال الثانيةِ نفسِها» — يُقاس بفارقِ الوقتِ لا بالظنّ */
    $dt = ($task && $row) ? abs(strtotime((string) $task['created_at']) - strtotime((string) $row['created_at'])) : 999;
    $ok($dt <= 1, "**وفي الثانيةِ نفسِها** (فارقٌ {$dt}ث) — لا مهمةً تلحق لاحقًا");
}
/* والعطالة: تنبيهانِ لا يولّدان مهمتين لتنبيهٍ واحد */
$nid2 = WIS::notifyUser($conn, $CO, 1, 'إخطارٌ ثانٍ ' . $TAG, 'جسم', 'y.php', 0, 1);
$rr2 = $conn->query("SELECT requires_action, task_item_id FROM personal_notifications WHERE id = " . (int) $nid2);
$row2 = $rr2 ? $rr2->fetch_assoc() : null;
$ok($row2 && (int) $row2['requires_action'] === 0 && empty($row2['task_item_id']),
    'وتنبيهٌ **لا يتطلب فعلًا** لا يولّد مهمةً — فلا ضجيجَ في «مهامي»');

/* ── ② INJ-0391 · سحبُ القبولِ يملأ أعمدتَه ────────────────────────────────── */
$acc = null;
$ra = $conn->query("SELECT id, risk_id, note FROM risk_acceptances
                     WHERE company_id = {$CO} AND withdrawn_by IS NULL ORDER BY id DESC LIMIT 1");
if ($ra) { $acc = $ra->fetch_assoc(); }
if (!$acc) {
    $NOTES[] = 'لا قبولَ نافذًا في البياناتِ لقياسِ السحبِ حيًّا — قِيس بالبنيةِ وحدَها';
    $ok(method_exists('App\\Services\\Risk\\RiskService', 'withdrawAcceptance'),
        'فعلُ سحبِ القبولِ مبنيٌّ في الخدمة');
} else {
    $accId = (int) $acc['id'];
    $riskId = (int) $acc['risk_id'];
    $stBefore = '';
    $sr = $conn->query("SELECT state FROM risk_register WHERE id = {$riskId}");
    if ($sr && ($sx = $sr->fetch_row())) { $stBefore = (string) $sx[0]; }

    $w0 = RSK::withdrawAcceptance($conn, $CO, $accId, '', 1);
    $ok(empty($w0['ok']) && (int) $w0['code'] === 422, '**ولا سحبَ بلا سببٍ مكتوب**');

    $w1 = RSK::withdrawAcceptance($conn, $CO, $accId, 'شاهدٌ ' . $TAG, 1);
    $ok(!empty($w1['ok']), 'وسحبٌ بسببٍ يمرُّ (' . (int) $w1['code'] . ')', $w1['reason']);
    $ar = $conn->query("SELECT withdrawn_by, withdrawn_at FROM risk_acceptances WHERE id = {$accId}");
    $arow = $ar ? $ar->fetch_assoc() : null;
    $ok($arow && (int) $arow['withdrawn_by'] > 0 && !empty($arow['withdrawn_at']),
        '**وتمتلئ الأعمدةُ** `withdrawn_by/at` — بعد أن كانت تُصيَّر ولا تُكتب أبدًا');
    $sr2 = $conn->query("SELECT state FROM risk_register WHERE id = {$riskId}");
    $stAfter = ($sr2 && ($sx2 = $sr2->fetch_row())) ? (string) $sx2[0] : '';
    $ok($stAfter === 'reassessment' || $stBefore === 'closed',
        "وتعود حالةُ الخطرِ إلى `reassessment` (الآن «{$stAfter}»)");
    $w2 = RSK::withdrawAcceptance($conn, $CO, $accId, 'ثانٍ', 1);
    $ok(empty($w2['ok']), 'ولا يُسحب قبولٌ مسحوبٌ — العطالةُ بالعمودِ نفسِه');

    /* ◆ ما مُسَّ من بياناتِ الإنتاجِ يُعاد */
    $conn->query("UPDATE risk_acceptances SET withdrawn_by = NULL, withdrawn_at = NULL,
                  note = REPLACE(note, ' · سُحب: شاهدٌ {$TAG}', '') WHERE id = {$accId}");
    $conn->query("UPDATE risk_register SET state = '" . $conn->real_escape_string($stBefore)
                . "' WHERE id = {$riskId}");
    $chk = $conn->query("SELECT withdrawn_by FROM risk_acceptances WHERE id = {$accId}");
    $crow = $chk ? $chk->fetch_row() : null;
    $ok($crow && $crow[0] === null, 'وأُعيد صفُّ القبولِ إلى حالتِه — فالشاهدُ لا يترك أثرًا');
}

/* ── ③ INJ-0264 · سياسةُ الإقفالِ وفعلُ العكس ─────────────────────────────── */
$ac = (string) @file_get_contents($ROOT . '/Tickets/admin_close.php');
$ok(strpos($ac, 'closure_policy') !== false && strpos($ac, 'TKT-403-CLOSEPOL') !== false,
    '**وسياسةُ الإقفالِ تحكم الإغلاقَ الإداريَّ برمزٍ يسمّيها**');
$ok(strpos($ac, "'reporter_confirm', 'committee'") !== false,
    'وأنواعُ «تأكيدِ المبلِّغ» و«قرارِ اللجنة» لا تُغلق إداريًّا');
$ok(strpos($ac, 'areverse_tk') !== false && strpos($ac, 'admin_close_reversed') !== false,
    '**وللإغلاقِ الخاطئِ زرُّ نقضٍ** ينتج حركةً مرتبطةً بالأصل');
$ok(strpos($ac, "action_type = 'admin_close'") !== false,
    'والمرحلةُ السابقةُ **تُقرأ من سجلِّ التدقيق** لا تُخمَّن');
$ok(strpos($ac, 'TKT-409-NOTRACE') !== false,
    'وبلا أثرٍ يحمل المرحلةَ السابقةِ يُردُّ ٤٠٩ — ولا يُخمَّن إلى أين يُردّ');

/* ── ④ والشاشتانِ تعرضان البابَ ─────────────────────────────────────────── */
$ra2 = (string) @file_get_contents($ROOT . '/Risk/risk_acceptance.php');
$ok(strpos($ra2, 'risk_accept_withdraw') !== false,
    'وشاشةُ القبولِ تعرض زرَّ السحبِ للنافذِ وحدَه');
$rax = (string) @file_get_contents($ROOT . '/Risk/risk_actions.php');
$ok(strpos($rax, "case 'risk_accept_withdraw'") !== false,
    'ونقطةُ الأفعالِ تحمل بابَه');

$leftAfter = $sweep();
$ok($leftAfter === 0, 'والكنسُ البعديُّ نظيفٌ', "بقي {$leftAfter}");

$say('');
foreach ($NOTES as $n) { $say('  ◆ ' . $n); }
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
