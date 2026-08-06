<?php
/**
 * m00_events_test — إثباتُ الأحداث الأربعة (M-00 §11) من نقاطها الحقيقية.
 *
 * ① contract.signed  : سَوقُ عقدِ تجربةٍ عبر آلة الحالات حتى «موقَّع» ثم فحصُ الجذر.
 * ② exec.approval.granted : طلبٌ يقرّره حاملُه التنفيذي (دور 9) اعتمادًا نهائيًّا.
 * ③④ يُثبتان عبر الشاشة (cURL) في m00_events_http_test — هنا الخدمتان فقط.
 *
 * التشغيل: php tools/m00_events_test.php
 */

if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
error_reporting(E_ALL);
ini_set('display_errors', '1');

ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Core/TenantDb.php';
require_once __DIR__ . '/../app/Services/Contract/ContractStateMachine.php';
require_once __DIR__ . '/../app/Services/Work/RequestService.php';
ob_end_clean();

use App\Services\Contract\ContractStateMachine as CSM;
use App\Services\Work\RequestService;

$CO = 4; $EXEC = 881; // شركة الاختبار وحساب التنفيذ (دور 9)
$pass = 0; $fail = 0;
function ok($cond, $label) {
    global $pass, $fail;
    if ($cond) { $pass++; fwrite(STDOUT, "  ✅ $label\n"); }
    else { $fail++; fwrite(STDOUT, "  ❌ $label\n"); }
}
function factRow(mysqli $conn, $key, $like) {
    $st = $conn->prepare("SELECT id, event_key, idempotency_key, payload
                            FROM ems_business_events
                           WHERE event_key = ? AND idempotency_key LIKE ?
                           ORDER BY id DESC LIMIT 1");
    $st->bind_param('ss', $key, $like);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    return $r;
}

require_once __DIR__ . '/../app/Core/TenantContext.php';
require_once __DIR__ . '/../app/Core/TenantRegistry.php';
$gate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($CO, 0, '', true));

fwrite(STDOUT, "═══ ① contract.signed من نقطة الخنق ═══\n");
// عقدُ تجربةٍ في «مسودة» يُساق عبر المسار الشرعي: تفاوض → معتمد → موقَّع
$dr = $conn->query("SELECT id, contract_status FROM contracts
                     WHERE company_id = {$CO} AND contract_status IN ('مسودة','تفاوض','معتمد')
                       AND COALESCE(is_deleted,0) = 0 ORDER BY id DESC LIMIT 1");
$c = $dr ? $dr->fetch_assoc() : null;
if (!$c) {
    // شركة الاختبار بلا عقد قابلٍ — عقدُ تجربةٍ موسومٌ يُساق المسارَ كاملًا
    $ex = $conn->query("SELECT project_id FROM contracts
                         WHERE company_id = {$CO} AND COALESCE(is_deleted,0) = 0
                         ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $mark = 'UAT-M00-SIGN-' . date('His');
    $nid = (int) $gate->insert('contracts', array(
        'first_party' => 'Equipation (تجربة)',
        'second_party' => $mark,
        'project_id' => (int) ($ex['project_id'] ?? 0),
        'contract_status' => 'مسودة',
        'contract_signing_date' => date('Y-m-d'),
    ));
    $c = $nid > 0 ? array('id' => $nid, 'contract_status' => 'مسودة') : null;
    if ($c) { fwrite(STDOUT, "  أُنشئ عقد تجربة #{$nid} ({$mark})\n"); }
}
if (!$c) {
    fwrite(STDOUT, "  ⚠ لا عقد قابلًا للسَّوق في co4 — يُتخطى ①\n");
} else {
    $cid = (int) $c['id'];
    $cur = $c['contract_status'];
    $path = array('مسودة' => array('تفاوض', 'معتمد', 'موقَّع'),
                  'تفاوض' => array('معتمد', 'موقَّع'),
                  'معتمد' => array('موقَّع'));
    fwrite(STDOUT, "  العقد #{$cid} من «{$cur}»\n");
    $lastFrom = $cur;
    foreach ($path[$cur] as $to) {
        $r = CSM::transition($conn, $gate, $CO, $cid, $to, 'اختبار M-00 §11', $EXEC);
        ok(!empty($r['ok']), "انتقال {$lastFrom} ← {$to}" . (empty($r['ok']) ? ' — ' . $r['reason'] : ''));
        if ($to !== 'موقَّع') { $lastFrom = $to; }
        if (empty($r['ok'])) { break; }
    }
    $f = factRow($conn, 'contract.signed', 'contract_signed:' . $cid . ':%');
    ok($f !== null, 'حقيقة contract.signed في الجذر المحايد');
    if ($f) {
        $p = json_decode((string) $f['payload'], true) ?: array();
        ok(($p['second_party'] ?? '') !== '', 'الحمولة تحمل الطرف الثاني: ' . ($p['second_party'] ?? '؟'));
        ok(($p['from'] ?? '') === 'معتمد', 'مصدر الانتقال «معتمد»');
    }
    // العطالة: إعادة النشر بنفس المفتاح لا تضاعف
    $n1 = $conn->query("SELECT COUNT(*) c FROM ems_business_events
                         WHERE event_key='contract.signed' AND entity_id={$cid}")->fetch_assoc();
    ok((int) $n1['c'] === 1, 'نشرة واحدة لا أكثر (عطالة)');
}

fwrite(STDOUT, "═══ ② exec.approval.granted عند القرار النهائي التنفيذي ═══\n");
// طلبٌ حاملُه التنفيذي مباشرةً ثم قرار «approve» نهائي بيده
$tp = $conn->query("SELECT code FROM request_types
                     WHERE COALESCE(approval_chain,'') = '' ORDER BY display_order LIMIT 1");
$t = $tp ? $tp->fetch_assoc() : null;
$typeCode = $t ? $t['code'] : '';
if ($typeCode === '') {
    $tp = $conn->query("SELECT code FROM request_types ORDER BY display_order LIMIT 1");
    $t = $tp ? $tp->fetch_assoc() : null;
    $typeCode = $t ? $t['code'] : '';
}
if ($typeCode === '') {
    fwrite(STDOUT, "  ⚠ لا قاموس أنواع — يُتخطى ②\n");
} else {
    $sub = RequestService::submit($conn, array(
        'company_id' => $CO, 'request_type_code' => $typeCode,
        'title' => 'اختبار حقيقة الاعتماد التنفيذي §11',
        'details' => 'سطر تجربة', 'requester_user_id' => 100,
        'org_unit_id' => 1,
    ));
    ok(!empty($sub['ok']), 'تقديم الطلب — ' . ($sub['request_no'] ?? ($sub['reason'] ?? '؟')));
    if (!empty($sub['ok'])) {
        $rid = (int) $sub['id'];
        $noEsc = $conn->real_escape_string($sub['request_no']);
        // سُق السلسلة كاملةً بيد التنفيذي: كل جولةٍ تُسلَّم الخطوةُ له ثم يقرّر،
        // حتى يكون قرارُه الأخير هو الاعتماد النهائي (نقطة الحدث §11)
        $dec = array('ok' => false, 'reason' => 'لم يُقرَّر');
        for ($round = 1; $round <= 6; $round++) {
            $conn->query("UPDATE requests SET current_holder_user_id = {$EXEC} WHERE id = {$rid}");
            $conn->query("UPDATE approval_links SET approver_user_id = {$EXEC}
                          WHERE source_ref = '{$noEsc}' AND status='pending'") or fwrite(STDOUT, '  ⚠ ' . $conn->error . "\n");
            $dec = RequestService::decide($conn, $rid, 'approve', $EXEC, 'قرار تنفيذي — جولة ' . $round);
            if (empty($dec['ok']) || $dec['status'] === 'approved') { break; }
        }
        ok(!empty($dec['ok']) && $dec['status'] === 'approved', 'القرار النهائي approve بيد u881' . (empty($dec['ok']) ? ' — ' . ($dec['reason'] ?? '') : ' (جولات ' . $round . ')'));
        $f2 = factRow($conn, 'exec.approval.granted', 'exec_approval:req:' . $rid . ':%');
        ok($f2 !== null, 'حقيقة exec.approval.granted في الجذر');
        if ($f2) {
            $p2 = json_decode((string) $f2['payload'], true) ?: array();
            ok((int) ($p2['approved_by'] ?? 0) === $EXEC, 'الحمولة تسمّي المعتمِد التنفيذي');
            ok(($p2['request_no'] ?? '') === ($sub['request_no'] ?? '-'), 'الحمولة تحمل رقم الطلب');
        }
        // نظافة: أغلق طلب التجربة كي لا يلوث صناديق العمل
        $conn->query("UPDATE requests SET status='cancelled', status_reason='اختبار §11 — أُقفل'
                      WHERE id = {$rid}");
        $conn->query("UPDATE work_items SET status='cancelled', status_reason='اختبار §11'
                      WHERE source_type='SRC-05' AND parent_ref = '" . $conn->real_escape_string($sub['request_no']) . "'");
    }
}

fwrite(STDOUT, "═══ الحصيلة: {$pass} ناجح · {$fail} فاشل ═══\n");
exit($fail > 0 ? 1 : 0);
