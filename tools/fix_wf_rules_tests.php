<?php
/**
 * tools/fix_wf_rules_tests.php — سلاليمُ الاعتماد: كلُّ منعٍ يُحاوَل خرقُه
 * ═══════════════════════════════════════════════════════════════════════════
 * ثلاثةُ أعطالٍ قِيست في محرّكِ الاعتماد، ولكلٍّ هنا اختبارٌ يحاول خرقَه:
 *   ① **صدقٌ خلوًّا**: `COUNT(status <> 'approved') = 0` صادقٌ على صفرِ خطوة،
 *      فطلبٌ بلا توقيعٍ يُقرأ «كلُّ خطواتِه معتمدة» ⇒ تُنفَّذ حمولتُه.
 *   ② **سلّمٌ مخترَع**: نوعُ كيانٍ بلا قاعدةٍ مسجَّلةٍ يسقط على «خطوةٍ للسوبر».
 *   ③ **يدٌ تمشي السلّمَ كلَّه**: مطابقةُ السوبرِ لأيِّ خطوةٍ + اعتمادُ الخطوةِ
 *      الأولى تلقائيًّا لمنشئِ الطلب.
 *
 * ◆ ولا يكفي أن يُقال «أُصلح»: يُبنى طلبٌ بلا خطواتٍ فعلًا ويُحاوَل إتمامُه.
 *
 * التشغيل: php tools/fix_wf_rules_tests.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);

$CO = 4;
$U_A = 900801; $U_B = 900802;
$_SESSION['user'] = array('id' => $U_A, 'role' => '-1', 'company_id' => $CO, 'name' => 'مِسبار WF');
require_once dirname(__DIR__) . '/includes/approval_workflow.php';

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }
function hand($uid, $role = '-1') { $_SESSION['user']['id'] = (int) $uid; $_SESSION['user']['role'] = (string) $role; }

$TAG = 'WFPROBE';
$teardown = function () use ($conn, $TAG) {
    $conn->query("DELETE FROM approval_steps WHERE request_id IN
                    (SELECT id FROM approval_requests WHERE entity_type LIKE '{$TAG}%')");
    $conn->query("DELETE FROM approval_requests WHERE entity_type LIKE '{$TAG}%'");
    $conn->query("DELETE FROM approval_workflow_rules WHERE entity_type LIKE '{$TAG}%'");
    $conn->query("DELETE FROM guard_denials WHERE attempted_ref LIKE '{$TAG}%'");
};
$teardown();
register_shutdown_function($teardown);

echo "══════════════════════════════════════════════════════════════════════\n";
echo " سلاليمُ الاعتماد — اختبارٌ سلبيٌّ لثلاثةِ أعطال\n";
echo ' ' . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n";

/* ══ ① طلبٌ بلا خطواتٍ لا يكتمل ═════════════════════════════════════════ */
head('① «صفرُ خطوةٍ» ليس اكتمالًا — الصدقُ الخلويُّ أُغلق');
$conn->query("INSERT INTO approval_requests (entity_type, entity_id, action, payload, requested_by, current_step, status, created_at)
              VALUES ('{$TAG}_stepless', 1, 'approve', '{\"operations\":[]}', {$U_A}, 1, 'pending', NOW())");
$REQ0 = (int) $conn->insert_id;
check($REQ0 > 0, 'أُنشئ طلبٌ بلا أيِّ خطوةٍ #' . $REQ0 . ' (يحاكي أربعةَ صفوفٍ قائمةٍ في القاعدة)');
$cnt = $conn->query("SELECT COUNT(*) FROM approval_steps WHERE request_id={$REQ0}")->fetch_row()[0];
check((int) $cnt === 0, 'وخطواتُه صفر');
check(approval_are_all_steps_approved($REQ0, $conn) === false,
    '«أكلُّ الخطواتِ معتمدة؟» تُجيب **لا** على صفرِ خطوة — وكانت تُجيب نعم');
$res = approval_finalize_if_completed($REQ0, $conn);
check(empty($res['success']) && mb_strpos($res['message'], 'بلا أيِّ خطوة') !== false,
    'وإتمامُه مرفوضٌ برسالةٍ تفرّق «لم يُبنَ» من «لم يكتمل» — ' . mb_substr($res['message'], 0, 46));
$st = $conn->query("SELECT status, executed_at FROM approval_requests WHERE id={$REQ0}")->fetch_assoc();
check($st['status'] === 'pending' && $st['executed_at'] === null,
    'ولم تُنفَّذ حمولتُه ولم يُوسَم معتمدًا (status=' . $st['status'] . ')');

/* ══ ② الاحتياطُ يُسجَّل في وضعِ المراقبة ═══════════════════════════════ */
head('② «سلّمٌ بلا قاعدةٍ مسجَّلة» يُسجَّل دَينًا لا يمضي صامتًا');
$before = (int) $conn->query("SELECT COUNT(*) FROM guard_denials WHERE guard_code='approval_rules_missing'")->fetch_row()[0];
$rules = approval_get_workflow_rules($TAG . '_norules', 'approve', $conn);
check(count($rules) === 1, 'الاحتياطُ أعاد خطوةً واحدةً (وضعُ المراقبةِ لا يوقف المسار)');
$after = (int) $conn->query("SELECT COUNT(*) FROM guard_denials WHERE guard_code='approval_rules_missing'")->fetch_row()[0];
check($after === $before + 1, 'وسُجِّل سطرُ دَينٍ واحدٌ في guard_denials — الدَّينُ صار مقيسًا');
$row = $conn->query("SELECT attempted_ref, reason_code FROM guard_denials
                      WHERE guard_code='approval_rules_missing' ORDER BY deny_id DESC LIMIT 1")->fetch_assoc();
check(strpos((string) $row['attempted_ref'], $TAG) === 0 && $row['reason_code'] === 'fallback_default',
    'والسطرُ يسمّي الزوجَ وسببَه: ' . $row['attempted_ref'] . ' / ' . $row['reason_code']);

/* الزوجُ المسمّى في الاحتياطِ يُسجَّل بسببٍ مختلفٍ — فلا يُخلط المعروفُ بالمجهول */
$before = $after;
approval_get_workflow_rules('driver', 'activate_driver', $conn);
$row = $conn->query("SELECT reason_code FROM guard_denials
                      WHERE guard_code='approval_rules_missing' ORDER BY deny_id DESC LIMIT 1")->fetch_assoc();
check($row['reason_code'] === 'fallback_named', 'والزوجُ المسمّى يُسجَّل `fallback_named` لا `fallback_default`');
$conn->query("DELETE FROM guard_denials WHERE guard_code='approval_rules_missing' AND attempted_ref='driver:activate_driver'");

/* ══ ③ القاعدةُ المسجَّلةُ تغلب الاحتياطَ ════════════════════════════════ */
head('③ القاعدةُ المسجَّلةُ تغلب الاحتياطَ — ولا تُسجَّل دَينًا');
for ($i = 1; $i <= 3; $i++) {
    $st = $conn->prepare("INSERT INTO approval_workflow_rules (entity_type, action, role_required, step_order, is_active, created_at)
                          VALUES (?, 'approve', ?, ?, 1, NOW())");
    $et = $TAG . '_real'; $rr = (string) (3 + $i);
    $st->bind_param('ssi', $et, $rr, $i);
    $st->execute(); $st->close();
}
$before = (int) $conn->query("SELECT COUNT(*) FROM guard_denials WHERE guard_code='approval_rules_missing'")->fetch_row()[0];
$rules = approval_get_workflow_rules($TAG . '_real', 'approve', $conn);
check(count($rules) === 3, 'ثلاثُ خطواتٍ قُرئت من السجل (لا من الشيفرة)');
$after = (int) $conn->query("SELECT COUNT(*) FROM guard_denials WHERE guard_code='approval_rules_missing'")->fetch_row()[0];
check($after === $before, 'وصفرُ سطرِ دَينٍ — الدَّينُ يُسجَّل عند الغيابِ لا عند الحضور');

/* ══ ④ «لا يدَ تمشي خطوتين» فوق سلّمٍ حقيقيّ ═════════════════════════════ */
head('④ ثلاثُ خطواتٍ ⇒ ثلاثُ أيدٍ — ولو كان الفاعلُ سوبرًا يطابق الكلَّ');
$payload = array('summary' => array('probe' => $TAG),
                 'operations' => array(array('db_action' => 'update', 'table' => 'approval_requests',
                                             'where' => array('id' => '{{request_id}}'),
                                             'data' => array('rejection_reason' => 'مِسبار'))));
hand($U_A);
$res = approval_create_request($TAG . '_real', 77, 'approve', $payload, $U_A, $conn);
check(!empty($res['success']), 'أُنشئ طلبٌ #' . (int) ($res['request_id'] ?? 0) . ' — ' . $res['message']);
$REQ = (int) ($res['request_id'] ?? 0);
$n = (int) $conn->query("SELECT COUNT(*) FROM approval_steps WHERE request_id={$REQ}")->fetch_row()[0];
check($n === 3, 'وثلاثُ خطواتٍ أُنشئت له');
$ap = (int) $conn->query("SELECT COUNT(*) FROM approval_steps WHERE request_id={$REQ} AND status='approved'")->fetch_row()[0];
check($ap === 1, 'والخطوةُ الأولى اعتُمدت تلقائيًّا لمنشئِه (سوبرٌ يطابق أيَّ دور) — ' . $ap);
$res = approval_approve_request($REQ, $U_A, $conn, 'اليدُ نفسُها');
check(empty($res['success']) && mb_strpos($res['message'], 'خطوتين') !== false,
    'واليدُ نفسُها مُنعت من الثانية ولو كانت سوبرًا — ' . mb_substr($res['message'], 0, 44));
$stt = $conn->query("SELECT status FROM approval_requests WHERE id={$REQ}")->fetch_row()[0];
check($stt === 'pending', 'والطلبُ ما زال معلَّقًا — سوبرٌ واحدٌ لا يُكمل سلّمًا من ثلاث');

/* ══ ⑤ السجلُّ نُظِّف مُعلَنًا لا محوًا ═══════════════════════════════════ */
head('⑤ سجلُّ القواعد: صفرُ نصِّ ملاحظةٍ نشطًا — والمعطَّلُ باقٍ مقروءًا');
$bad = (int) $conn->query("SELECT COUNT(*) FROM approval_workflow_rules
                            WHERE is_active=1 AND (entity_type LIKE '% %' OR CHAR_LENGTH(entity_type) > 40)")->fetch_row()[0];
check($bad === 0, 'صفرُ قاعدةٍ نشطةٍ محتواها نصُّ ملاحظة');
$off = (int) $conn->query("SELECT COUNT(*) FROM approval_workflow_rules
                            WHERE is_active=0 AND action LIKE '%معطَّل: نصُّ ملاحظة%'")->fetch_row()[0];
check($off === 20, 'والعشرون معطَّلةٌ **باقيةٌ** ببصمةٍ تُعلن السبب (لا حذف) — ' . $off);
$total = (int) $conn->query('SELECT COUNT(*) FROM approval_workflow_rules')->fetch_row()[0];
check($total >= 23, 'ومجموعُ الصفوفِ لم ينقص: ' . $total);

echo "\n" . str_repeat('═', 70) . "\n";
printf("ناجحٌ: %d · فاشلٌ: %d\n", $PASS, $FAIL);
echo str_repeat('═', 70) . "\n";
exit($FAIL === 0 ? 0 : 1);
