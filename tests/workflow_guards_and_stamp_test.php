<?php
/**
 * tests/workflow_guards_and_stamp_test.php — حارسٌ واحدٌ · سلطةٌ محكومة · بصمةٌ مولَّدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0310 · INJ-0313 · INJ-0486 · INJ-0574 · INJ-0124 · INJ-0143
 *
 * · INJ-0310: «الإقفالُ **من أيِّ الشاشتين** يُرفض **بالرسالة نفسها** إن لم يكن
 *   للأمر متحمِّلٌ ومراكزُ تكلفةٍ لكل بند».
 * · INJ-0313: «إضافةُ تصريحٍ … **ويجتاز الأمرُ حارسَ التجهيز**».
 * · INJ-0486: «إنشاءُ عنصرِ عملٍ بلا `evidence_required` أو بلا `verifier_user_id`
 *   يُرفض … **وبمتحقِّقٍ = المنفِّذِ يُرفض أيضًا**».
 * · INJ-0574: «لكلِّ درجةٍ من الأربعِ يوجد **دورٌ واحدٌ على الأقلِّ** يستطيع القبول».
 * · INJ-0124: «يعرض بصمةً **مولَّدةً** … **ولا يوجد فيها فورمُ إدخالٍ يدوي**».
 * · INJ-0143: «ملاحظةٌ حرجةٌ **تحجب التوقيعَ** … **ولا يعتمد الحجبُ على أيِّ
 *   مطابقةٍ نصية**».
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/Transport/trs_helpers.php';
require_once $ROOT . '/app/Services/Work/WorkItemService.php';
require_once $ROOT . '/app/Services/Risk/RiskService.php';
require_once $ROOT . '/app/Services/Contract/ContractLifecycleActions.php';

use App\Services\Work\WorkItemService as WIS;
use App\Services\Risk\RiskService as RSK;
use App\Services\Contract\ContractLifecycleActions as CLA;

$conn = $GLOBALS['conn'];
$CO = 4; $TAG = 'WFG-TEST-FAMILY';
$PASS = 0; $FAIL = 0; $NOTES = array();
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ حارسٌ واحدٌ · سلطةٌ محكومة · بصمةٌ مولَّدة');
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => $CO, 'name' => 'شاهد');
$gate = ems_tenant_db();

$sweep = function () use ($conn, $TAG) {
    $conn->query("DELETE FROM contract_notes WHERE note LIKE '%{$TAG}%'");
    $conn->query("DELETE FROM transfer_permits WHERE authority LIKE '%{$TAG}%'");
    $r = $conn->query("SELECT COUNT(*) FROM contract_notes WHERE note LIKE '%{$TAG}%'");
    return ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
};
$ok($sweep() === 0, 'الكنسُ القبليُّ نظيفٌ بالعائلة');

/* ── ① INJ-0310 · حارسُ الإقفالِ دالّةٌ واحدةٌ للشاشتين ──────────────────── */
$ok(function_exists('trs_close_gate'), 'حارسُ الإقفالِ **دالّةٌ مشتركةٌ** في `trs_helpers.php`');
$svcSrc = (string) @file_get_contents($ROOT . '/app/Services/Transport/TransferDeliveryService.php');
$ok(strpos($svcSrc, 'trs_close_gate(') !== false,
    '**وخدمةُ الإقفالِ تنادِيها** — فالرسالةُ واحدةٌ لأنَّ المصدرَ واحد');
$g0 = trs_close_gate($gate, 0);
$ok(empty($g0['ok']) && (int) $g0['code'] === 422, 'وأمرٌ غيرُ صالحٍ يُردُّ ٤٢٢');
/* أمرٌ حيٌّ: الرسالةُ تسمّي الناقصَ لا ترفض عمياءَ */
$oid = 0;
$r = $conn->query("SELECT id FROM transfer_orders WHERE company_id = {$CO} ORDER BY id DESC LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $oid = (int) $x[0]; }
if ($oid > 0) {
    $g1 = trs_close_gate($gate, $oid);
    $ok(isset($g1['reason']) && $g1['reason'] !== '',
        'وأمرٌ حيٌّ يُقاس ورسالتُه **تسمّي الناقص**: ' . mb_substr((string) $g1['reason'], 0, 56));
    $ok(empty($g1['ok']) ? (mb_strpos((string) $g1['reason'], 'TRS-422-CLOSE') !== false) : true,
        'والرمزُ محكومٌ `TRS-422-CLOSE` — لا رسالةٌ حرّة');
}

/* ── ② INJ-0313 · حارسُ التجهيزِ يقرأ النافذَ لا المُعلَن ─────────────────── */
$ok(function_exists('trs_readiness_gate'), 'وحارسُ التجهيزِ دالّةٌ مشتركةٌ كذلك');
$formSrc = (string) @file_get_contents($ROOT . '/Transport/transfer_order_form.php');
$ok(strpos($formSrc, 'trs_readiness_gate(') !== false,
    '**وشاشةُ الأمرِ تنادِيها** بدل العدِّ المباشرِ على الحالة');
$hlp = (string) @file_get_contents($ROOT . '/Transport/trs_helpers.php');
$ok(strpos($hlp, 'expiry_date IS NULL OR expiry_date >= CURDATE()') !== false,
    '**وتصريحٌ منتهي الصلاحيةِ لا يُحسب نافذًا** ولو كانت حالتُه `valid` — التاريخُ حكمٌ والحالةُ بيان');
if ($oid > 0) {
    $rdy0 = trs_readiness_gate($gate, $oid);
    $ok(isset($rdy0['valid']), 'ويُرجع عددَ التصاريحِ النافذةِ لا نعم/لا (' . (int) $rdy0['valid'] . ')');
}

/* ── ③ INJ-0486 · الحارسُ السباعيُّ صار سبعةً ──────────────────────────────── */
$base = array('company_id' => $CO, 'source_type' => 'SRC-01', 'source_ref' => $TAG,
    'owner_user_id' => 1, 'title' => 'عنصرُ شاهدٍ ' . $TAG, 'due_at' => date('Y-m-d H:i:s'),
    'deliverable' => 'مخرَجٌ', 'assigned_user_id' => 1, 'org_unit_id' => 1);
$w1 = WIS::create($conn, $base);
$ok(empty($w1['ok']) && mb_strpos((string) $w1['reason'], 'الدليلُ المطلوب') !== false,
    '**وعنصرٌ بلا دليلٍ مطلوبٍ يُرفض** — والرسالةُ تبيّن الناقص');
$ok(empty($w1['ok']) && mb_strpos((string) $w1['reason'], 'المتحقِّق') !== false,
    'وبلا متحقِّقٍ يُرفض كذلك');
$w2 = WIS::create($conn, array_merge($base, array('evidence_required' => 'صورة', 'verifier_user_id' => 1)));
$ok(empty($w2['ok']) && mb_strpos((string) $w2['reason'], 'SELFVERIFY') !== false,
    '**وبمتحقِّقٍ = المنفِّذِ يُرفض** — ولا يشهد أحدٌ على عملِه');

/* ── ④ INJ-0574 · لكلِّ درجةٍ دورٌ يستطيع القبول ──────────────────────────── */
require_once $ROOT . '/Risk/_risk_authority.php';
$rank = array('risk_owner' => 1, 'owner_with_analyst' => 2, 'analyst' => 2, 'deputy' => 3, 'ceo' => 4);
$roles = array();
$rr = $conn->query('SELECT id FROM roles WHERE status = 1');
while ($rr && ($x = $rr->fetch_row())) { $roles[] = (string) $x[0]; }
$uncovered = array();
foreach (RSK::AUTHORITY_MATRIX as $level => $need) {
    $needRank = isset($rank[$need]) ? $rank[$need] : 99;
    $found = false;
    foreach ($roles as $rid) {
        $a = risk_actor_authority_map($rid);
        if ($a !== '' && isset($rank[$a]) && $rank[$a] >= $needRank) { $found = true; break; }
    }
    if (!$found) { $uncovered[] = $level . ' (يلزم ' . $need . ')'; }
}
$ok(empty($uncovered),
    '**ولكلِّ درجةٍ من الأربعِ دورٌ يستطيع قبولَها** — بالرتبةِ لا بالاسم',
    implode(' · ', $uncovered));
$hasDeputy = false;
foreach ($roles as $rid) { if (risk_actor_authority_map($rid) === 'deputy') { $hasDeputy = true; break; } }
$NOTES[] = 'رتبةُ `deputy` لا يحملها دورٌ (' . ($hasDeputy ? 'تحمَّلت' : 'لم تُحمَّل')
         . ') — و«مرتفع» يقبله الرئيسُ برتبتِه الأعلى (٤ ≥ ٣). قرارٌ مُسجَّلٌ في OWNER_DECISIONS_WORKFLOW.';

/* ── ⑤ INJ-0124 · بصمةٌ مولَّدةٌ ولا فورمَ إدخال ────────────────────────────── */
$rs = (string) @file_get_contents($ROOT . '/Portal/release_stamp.php');
$ok(strpos($rs, 'release_stamp_compute') !== false, 'وبصمةُ الإصدارِ **تُحسب في الخادم**');
$ok(preg_match('~name="cmp03_action" value="add"~', $rs) === 0
    && preg_match("~name='cmp03_action' value='add'~", $rs) === 0,
    '**ولا فورمَ إدخالٍ يدويٍّ في الشاشة**');
$ok(strpos($rs, 'REL-422-NOMANUAL') !== false,
    'ومن أرسل الفورمَ القديمَ يُردُّ برمزٍ يُقرأ — لا بابَ خلفيّ');
/* ◆ والمقياسُ **الشفرةُ لا التعليق**: نصُّ `if (false)` يرد في التوثيقِ شرحًا —
     فيُقاس البناءُ الفعليُّ `if (false) {` في أوّلِ سطرِه لا ذكرُه في تعليق. */
$ok(preg_match("~^\s*if \(false\) \{~m", $rs) === 0,
    'ولا شفرةَ ميتةً خلف `if (false)` — ما لا يُستعمل يُحذف');
/* والبصمةُ تتغيّر بتغيّرِ مصدرِها */
if (function_exists('release_stamp_compute')) {
    $s1 = release_stamp_compute($conn, $ROOT);
    $ok(!empty($s1['stamp']) && strlen((string) $s1['stamp']) === 16,
        'وطولُها ستةَ عشرَ حرفًا: ' . (string) $s1['stamp']);
    $ok((int) $s1['migrations'] > 0 && (int) $s1['tables'] > 0,
        'وتُشتقُّ من الهجراتِ (' . (int) $s1['migrations'] . ') والجداولِ (' . (int) $s1['tables'] . ')');
}

/* ── ⑥ INJ-0143 · الحجبُ بالعمودِ لا بالنص ────────────────────────────────── */
$hasSev = false;
$q = $conn->query("SHOW COLUMNS FROM contract_notes LIKE 'severity'");
if ($q && $q->fetch_row()) { $hasSev = true; }
$ok($hasSev, 'وملاحظةُ العقدِ صار لها عمودُ خطورةٍ محكومٌ بتعداد');
$cla = (string) @file_get_contents($ROOT . '/app/Services/Contract/ContractLifecycleActions.php');
$ok(strpos($cla, "severity = 'critical' AND note_state = 'open'") !== false,
    "**والحجبُ يقرأ العمودَينِ** — لا يبحث في نصِّ الملاحظةِ عن كلمة");
$ok(strpos($cla, 'CNOTE-423') !== false && strpos($cla, "\$code === 'sign'") !== false,
    'وموضعُه **التوقيعُ وحدَه** برمزٍ محكوم — فلا يُجمَّد العقدُ في كلِّ خطوة');
/* حيًّا: ملاحظةٌ حرجةٌ تحجب، وإغلاقُها بلا مستندٍ يُردُّ من القاعدة */
$cid = 0;
$rc = $conn->query("SELECT id FROM contracts WHERE company_id = {$CO} AND contract_status = 'معتمد' LIMIT 1");
if ($rc && ($cx = $rc->fetch_row())) { $cid = (int) $cx[0]; }
if ($cid > 0) {
    $conn->query("INSERT INTO contract_notes (company_id, contract_id, note, user_id, severity, note_state, created_at)
                  VALUES ({$CO}, {$cid}, 'ملاحظةُ شاهدٍ {$TAG}', 1, 'critical', 'open', NOW())");
    $nid = (int) $conn->insert_id;
    $sg = CLA::run($conn, $gate, $CO, 'customer', $cid, 'sign', '', 1, 1);
    $ok((int) $sg['code'] === 423 && mb_strpos((string) $sg['reason'], 'CNOTE-423') !== false,
        '**وتوقيعٌ بملاحظةٍ حرجةٍ مفتوحةٍ يُردُّ ٤٢٣** (' . (int) $sg['code'] . ')');
    $conn->query("UPDATE contract_notes SET note_state = 'closed' WHERE id = {$nid}");
    $ok($conn->errno !== 0 && mb_strpos((string) $conn->error, 'CNOTE-422') !== false,
        '**والقاعدةُ تردُّ إغلاقًا بلا مستندٍ ومعتمِد** بقادحٍ لا بفحصِ واجهة');
    $conn->query("UPDATE contract_notes SET note_state = 'closed', closure_doc_ref = 'DOC-{$TAG}',
                  closed_by = 1, closed_at = NOW() WHERE id = {$nid}");
    $chk = $conn->query("SELECT note_state FROM contract_notes WHERE id = {$nid}");
    $cs = $chk ? $chk->fetch_row() : null;
    $ok($cs && (string) $cs[0] === 'closed', 'وبمستندٍ ومعتمِدٍ يُغلق');
}

$left = $sweep();
$ok($left === 0, 'والكنسُ البعديُّ نظيفٌ', "بقي {$left}");

$say('');
foreach ($NOTES as $n) { $say('  ◆ ' . $n); }
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
