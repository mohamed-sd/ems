<?php
/**
 * tools/fix_inj0219_tests.php — INJ-0219: اختبارُ قبولِ الحكمِ نفسِه، سلبًا وإيجابًا
 *
 * ⇐ شواهدُ أحكامٍ: INJ-0219
 * ═══════════════════════════════════════════════════════════════════════════
 * نصُّ اختبارِ القبولِ في السجلِّ الجامع (العمود «اختبار القبول»):
 *   «خصمٌ لا يصير «معتمدًا» إلا بمرورِه في سلسلة الموافقات بيدين مختلفتين؛
 *    وكلُّ خصمٍ معتمدٍ يشير إلى مقترحه ويظهر في مقاصّات المسيّر.»
 *
 * ◆ ولا يُقاس غيرُ هذا النصّ: التحققُ المضادُّ في السجلِّ نفسِه (العمود 23) نقض
 *   **الإصلاحَ المقترَح** لا الملاحظة — «الإصلاحُ المقترحُ يستهدف جدولًا خاطئًا»
 *   (`payroll_deductions` يكتبها OffsetService من سلفِ العاملين، والشاشةُ تكتب
 *   `scr_deductions`). فمِسبارٌ يفتّش عن عمودِ حالةٍ في `payroll_deduction%`
 *   يشهد على غيرِ بابِه — وهو ما كان يفعله المِسبارُ الأولُ فأعلن «مفتوح» بحقٍّ
 *   وبدليلٍ خاطئ.
 *
 * ◆ ولا يُبلَّغ نجاحٌ إلا بجسٍّ فعليّ: كلُّ منعٍ يُحاوَل خرقُه، وكلُّ سماحٍ
 *   يُحاوَل نيلُه. قيدٌ في `information_schema` غيرُ مجسوسٍ ادعاءٌ.
 *
 * التشغيل: php tools/fix_inj0219_tests.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);

$CO = 4;
/* ثلاثُ أيدٍ متمايزةٍ ويدُ منشئٍ رابعة — الأدوار '-1' لتطابق كلَّ خطوةٍ فيُختبر
   حاجزُ **اليد** وحدَه مجرَّدًا من حاجزِ الدور. */
$U_CREATOR = 900901;
$U_H1 = 900911; $U_H2 = 900912; $U_H3 = 900913;
$_SESSION['user'] = array('id' => $U_H1, 'role' => '-1', 'company_id' => $CO, 'name' => 'مِسبار INJ-0219');

require_once dirname(__DIR__) . '/includes/cmp03_local_store.php';
require_once dirname(__DIR__) . '/includes/approval_workflow.php';
require_once dirname(__DIR__) . '/includes/self_approval_guard.php';
require_once dirname(__DIR__) . '/app/Services/Workforce/AttendanceService.php';

use App\Services\Workforce\AttendanceService as AS_;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CANON = 'deductions.php';
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }
/** يدٌ حاضرةٌ في الجلسة — الحرّاسُ يقرؤون منها لا من وسيط. */
function hand($uid, $role = '-1') { $_SESSION['user']['id'] = (int) $uid; $_SESSION['user']['role'] = (string) $role; }

$PROBE = 'PROBE-INJ0219-';
$teardown = function () use ($conn, $PROBE, $CO) {
    /* الترتيبُ يتبع اتجاهَ القيود (RESTRICT): الابنُ قبلَ الأب */
    $conn->query("DELETE FROM scr_deductions WHERE company_id = {$CO} AND no_decision LIKE '{$PROBE}%'");
    $conn->query("DELETE FROM approval_steps WHERE request_id IN
                    (SELECT id FROM approval_requests WHERE entity_type='scr_deductions'
                      AND payload LIKE '%{$PROBE}%')");
    $conn->query("DELETE FROM approval_requests WHERE entity_type='scr_deductions' AND payload LIKE '%{$PROBE}%'");
    $conn->query("DELETE FROM deduction_proposals WHERE company_id = {$CO} AND source_ref LIKE '{$PROBE}%'");
};
$teardown();
register_shutdown_function($teardown);

echo "══════════════════════════════════════════════════════════════════════\n";
echo " INJ-0219 — «لا يصير معتمدًا إلا بسلّمٍ ويدين مختلفتين»\n";
echo ' ' . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n";

/* ══ ① البابُ الخلفيُّ: الميلادُ معتمدًا ═══════════════════════════════════ */
head('① لا مستندَ يُولد معتمدًا (الطبقةُ المشتركة — 404 مُنادٍ)');
hand($U_CREATOR);
$ok = cmp03_store_insert($conn, $CO, $CANON,
    array('رقم القرار' => $PROBE . 'BORN', 'الحالة' => 'معتمد', 'كود الموظف' => 'مِسبار'),
    'معتمد', $U_CREATOR, 'مِسبار المنشئ');
check($ok, 'الحفظُ نفسُه نجح (الحطُّ لا يُفقد المستخدمَ عملَه)');
$notice = cmp03_store_notice();
check($notice !== '', 'البلاغُ مُعلَنٌ لا مبتلَع: ' . mb_substr($notice, 0, 58) . '…');
$rs = $conn->query("SELECT id, status, status_label, created_by FROM scr_deductions
                     WHERE company_id={$CO} AND no_decision='{$PROBE}BORN' LIMIT 1");
$born = $rs ? $rs->fetch_assoc() : null;
check($born !== null, 'الصفُّ موجود');
check($born && $born['status'] === 'مسودة', "الحالةُ حُطّت إلى «مسودة» (كانت «معتمد») — المقيس: «" . ($born['status'] ?? '؟') . '»');
check($born && $born['status_label'] === 'مسودة', 'وعمودُ العرضِ حُطَّ معها — لا عمودان يتناقضان');
$ROW = $born ? (int) $born['id'] : 0;

/* ══ ② القيدُ البنيويّ — الطبقةُ ليست الحاجزَ الوحيد ═════════════════════ */
head('② القيدُ في القاعدةِ يرفض «معتمد» بلا سند (تخطّي الطبقةِ كلِّها)');
$direct = $conn->query("INSERT INTO scr_deductions
    (company_id, no_decision, status, is_seed, created_by, created_by_name, created_at, updated_at)
    VALUES ({$CO}, '{$PROBE}DIRECT', 'معتمد', 0, {$U_CREATOR}, 'مِسبار', NOW(), NOW())");
check($direct === false, 'إدراجٌ مباشرٌ بـSQL رُفض — ' . mb_substr($conn->error, 0, 52));
$upd = $conn->query("UPDATE scr_deductions SET status='معتمد' WHERE id={$ROW}");
check($upd === false, 'وترقيةُ صفٍّ قائمٍ إلى «معتمد» بلا سندٍ رُفضت أيضًا');

/* ══ ③ المقترحُ إلزاميٌّ ومصدرُه الآلة ══════════════════════════════════ */
head('③ مقترحُ الخصمِ سندٌ لا زخرفة');
$gate = ems_tenant_db();
$res = AS_::proposeDeduction($gate, $CO, array(
    'person_id' => 1, 'period' => '2026-08', 'source' => 'penalty',
    'source_ref' => $PROBE . 'SRC', 'proposed_amount' => 250.00, 'proposed_by' => $U_CREATOR));
check(!empty($res['ok']) && !empty($res['ded_id']), 'أُنشئ مقترحٌ #' . (int) ($res['ded_id'] ?? 0) . ' حالتُه Proposed');
$DED = (int) ($res['ded_id'] ?? 0);
$rs = $conn->query("SELECT state, proposed_by FROM deduction_proposals WHERE ded_id={$DED}");
$dp = $rs ? $rs->fetch_assoc() : null;
check($dp && $dp['state'] === 'Proposed', 'الحالةُ الأولى Proposed حصرًا');
check($dp && (int) $dp['proposed_by'] === $U_CREATOR, 'يدُ المقترحِ مسجَّلةٌ — بها يُقاس منعُ اعتمادِ الذات');

/* ══ ④ وسطُ الآلةِ صار موصولًا — وبأيدٍ مختلفة ══════════════════════════ */
head('④ Proposed → Reviewed → Approved: موصولٌ ومحكوم');
$r = AS_::transitionDeduction($gate, $CO, $DED, 'Approved', $U_H1, array('approvals_ref' => 'X'));
check(empty($r['ok']) && $r['code'] === 409, 'الطفرُ Proposed→Approved مرفوضٌ 409 — قائمةُ سماحٍ لا منع');
$r = AS_::transitionDeduction($gate, $CO, $DED, 'Reviewed', $U_CREATOR);
check(empty($r['ok']) && $r['code'] === 403, 'المقترحُ نفسُه لا ينقل حالةَ ما اقترح — 403');
$r = AS_::reviewDeduction($gate, $CO, $DED, $U_H1);
check(!empty($r['ok']) && $r['state'] === 'Reviewed', 'المراجعةُ بيدٍ ثانيةٍ نجحت');
$r = AS_::approveDeduction($gate, $CO, $DED, $U_H1, 'REQ-X');
check(empty($r['ok']) && $r['code'] === 403, '**من راجع لا يعتمد** — 403 لليدِ نفسِها');
$r = AS_::approveDeduction($gate, $CO, $DED, $U_H2, '');
check(empty($r['ok']) && $r['code'] === 422, 'اعتمادٌ بلا مرجعِ سلّمٍ مرفوضٌ 422');
$r = AS_::approveDeduction($gate, $CO, $DED, $U_H2, 'REQ-PROBE');
check(!empty($r['ok']) && $r['state'] === 'Approved', 'الاعتمادُ بيدٍ ثالثةٍ ومرجعِ سلّمٍ نجح — Approved بلغت');
$rs = $conn->query("SELECT state FROM deduction_proposals WHERE ded_id={$DED}");
check($rs && $rs->fetch_row()[0] === 'Approved', 'ومقاصّةُ المسيّرِ صارت قابلةَ البلوغ (postDeduction يشترط Approved)');
$dbl = $conn->query("UPDATE deduction_proposals SET approved_by={$U_H1} WHERE ded_id={$DED}");
check($dbl === false, 'وقيدُ القاعدةِ يرفض جعلَ المراجعِ معتمِدًا بحرفٍ مباشر');

/* ══ ⑤ السلّمُ: ثلاثُ خطواتٍ بثلاثِ أيدٍ ═════════════════════════════════ */
head('⑤ سلّمُ الموافقاتِ — ثلاثُ خطواتٍ لا تمشيها يدٌ واحدة');
$rules = $conn->query("SELECT COUNT(*) FROM approval_workflow_rules
                        WHERE entity_type='scr_deductions' AND action='approve' AND is_active=1")->fetch_row()[0];
check((int) $rules === 3, "خطواتٌ مسجَّلةٌ: {$rules} (المطلوب 3) — لا سقوطَ على احتياطِ «خطوةٍ للسوبر»");

$payload = array(
    'summary' => array('table' => 'scr_deductions', 'operation' => 'approve', 'probe' => $PROBE . 'BORN'),
    'operations' => array(array(
        'db_action' => 'update', 'table' => 'scr_deductions',
        'where' => array('id' => $ROW, 'company_id' => $CO),
        'data' => array('status' => 'معتمد', 'status_label' => 'معتمد', 'proposal_ref' => $DED,
                        'approval_request_ref' => '{{request_id}}', 'approved_by' => '{{final_approver}}',
                        'approved_at' => '{{now}}', 'approved_date' => '{{today}}'),
    )),
);
hand($U_H1);
$res = approval_create_request('scr_deductions', $ROW, 'approve', $payload, $U_H1, $conn);
check(!empty($res['success']), 'أُنشئ طلبُ سلّمٍ #' . (int) ($res['request_id'] ?? 0) . ' — ' . $res['message']);
$REQ = (int) ($res['request_id'] ?? 0);
$rs = $conn->query("SELECT COUNT(*) FROM approval_steps WHERE request_id={$REQ}");
check($rs && (int) $rs->fetch_row()[0] === 3, 'ثلاثُ خطواتٍ أُنشئت للطلب');
$rs = $conn->query("SELECT status FROM scr_deductions WHERE id={$ROW}");
check($rs && $rs->fetch_row()[0] === 'مسودة', 'والصفُّ ما زال «مسودة» — إنشاءُ الطلبِ ليس اعتمادًا');

/* الخطوةُ ① اعتُمدت تلقائيًّا لمنشئِ الطلبِ (يدُ U_H1). فمحاولةُ اليدِ نفسِها للخطوةِ ② */
$res = approval_approve_request($REQ, $U_H1, $conn, 'اليدُ نفسُها مرتين');
check(empty($res['success']) && mb_strpos($res['message'], 'خطوتين') !== false,
    'اليدُ نفسُها مُنعت من خطوةٍ ثانيةٍ — «' . mb_substr($res['message'], 0, 46) . '…»');
$rs = $conn->query("SELECT status FROM scr_deductions WHERE id={$ROW}");
check($rs && $rs->fetch_row()[0] === 'مسودة', 'ولم يُكتب شيءٌ بعد المنع');

hand($U_H2);
$res = approval_approve_request($REQ, $U_H2, $conn, 'الخطوةُ الثانية');
check(!empty($res['success']), 'يدٌ ثانيةٌ اعتمدت الخطوةَ ② — ' . $res['message']);
$rs = $conn->query("SELECT status FROM scr_deductions WHERE id={$ROW}");
check($rs && $rs->fetch_row()[0] === 'مسودة', 'وبخطوتين من ثلاثٍ لا يزال «مسودة» — لا اعتمادَ بسلّمٍ ناقص');

hand($U_H3);
$res = approval_approve_request($REQ, $U_H3, $conn, 'الخطوةُ الثالثة');
check(!empty($res['success']), 'يدٌ ثالثةٌ أكملت السلّمَ — ' . $res['message']);

/* ══ ⑥ الوجهُ الموجب: الاعتمادُ وقع بسندِه كاملًا ═══════════════════════ */
head('⑥ عند الاكتمال: «معتمد» بسندٍ كاملٍ كتبه منفّذُ السلّم');
$rs = $conn->query("SELECT status, status_label, proposal_ref, approval_request_ref, approved_by, approved_at,
                           approved_date, created_by FROM scr_deductions WHERE id={$ROW}");
$fin = $rs ? $rs->fetch_assoc() : null;
check($fin && $fin['status'] === 'معتمد', 'الحالةُ «معتمد» — المقيس: «' . ($fin['status'] ?? '؟') . '»');
check($fin && (int) $fin['proposal_ref'] === $DED, 'يشير إلى مقترحه #' . $DED . ' (الشقُّ الثاني من اختبارِ القبول)');
check($fin && (int) $fin['approval_request_ref'] === $REQ, 'ويشير إلى طلبِ سلّمِه #' . $REQ);
check($fin && (int) $fin['approved_by'] === $U_H3, 'والمعتمِدُ هو صاحبُ الخطوةِ الأخيرة (#' . ($fin['approved_by'] ?? '؟') . ') — رمزٌ حلَّه المحرّكُ لا الشاشة');
check($fin && (int) $fin['approved_by'] !== (int) $fin['created_by'], 'ويخالف المنشئَ (#' . ($fin['created_by'] ?? '؟') . ') — يدان مختلفتان');
check($fin && !empty($fin['approved_at']), 'ولحظةُ الاعتمادِ مكتوبة: ' . ($fin['approved_at'] ?? '—'));
check($fin && !empty($fin['approved_date']) && $fin['approved_date'] !== '0000-00-00',
    'وعمودُ التاريخِ (DATE) قُبل بلا اقتطاع: ' . ($fin['approved_date'] ?? '—'));
$rs = $conn->query("SELECT COUNT(DISTINCT approved_by) FROM approval_steps WHERE request_id={$REQ} AND status='approved'");
$hands = $rs ? (int) $rs->fetch_row()[0] : 0;
check($hands === 3, "أيدٍ متمايزةٌ مشت السلّمَ: {$hands} (المطلوب 3)");

/* ══ ⑦ لا رجعيةَ للمعتمد ═══════════════════════════════════════════════ */
head('⑦ وما اعتُمد لا يُعدَّل (CS-11) — الحاجزان معًا');
$u = cmp03_store_update($conn, $CO, $CANON, $ROW, array('قيمة الخصم' => '999999'), $U_H3, 'مِسبار');
check(empty($u['ok']) && mb_strpos((string) $u['msg'], 'رجعية') !== false, 'الطبقةُ ترفض تعديلَ المعتمد — ' . mb_substr((string) $u['msg'], 0, 44));

/* ══ ⑧ حارسُ منعِ اعتمادِ الذات على هذا الجدول ══════════════════════════ */
head('⑧ «من أنشأ لا يعتمد» — الحارسُ يقرأ المنشئَ من الصفِّ');
hand($U_CREATOR);
$deny = ems_assert_not_self_approval($conn, 'scr_deductions', 'id', $ROW, 'مِسبار INJ-0219', $CO);
check($deny !== null && (int) $deny['code'] === 403, 'المنشئُ مُنع 403 — ' . mb_substr((string) ($deny['reason'] ?? ''), 0, 44));
hand($U_H2);
$allow = ems_assert_not_self_approval($conn, 'scr_deductions', 'id', $ROW, 'مِسبار INJ-0219', $CO);
check($allow === null, 'ويدٌ أخرى تمرّ — حارسٌ يمنع الجميعَ عطلٌ آخر');

/* ══ ⑨ الرمزُ الذي لا يُحَلُّ لا يُخمَّن ══════════════════════════════════ */
head('⑨ سندٌ ناقصٌ لا يُكتب بقيمةٍ مخترعة');
$fake = array('id' => 0, 'requested_by' => $U_H1,
    'payload' => json_encode(array('operations' => array(array('db_action' => 'update', 'table' => 'scr_deductions',
        'where' => array('id' => $ROW), 'data' => array('approved_by' => '{{final_approver}}')))), JSON_UNESCAPED_UNICODE));
$res = approval_execute_payload($fake, $conn);
check(empty($res['success']) && mb_strpos($res['message'], 'لا يُخمَّن') !== false,
    'رمزٌ بلا خطوةٍ معتمَدةٍ ⇒ رفضٌ معلَنٌ لا كتابةٌ بـNULL — ' . mb_substr($res['message'], 0, 40));

echo "\n" . str_repeat('═', 70) . "\n";
printf("ناجحٌ: %d · فاشلٌ: %d\n", $PASS, $FAIL);
echo str_repeat('═', 70) . "\n";
exit($FAIL === 0 ? 0 : 1);
