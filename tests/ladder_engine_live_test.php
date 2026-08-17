<?php
/**
 * tests/ladder_engine_live_test.php — (ج) بطلبِ اعتمادٍ **حقيقيٍّ** لا بوجودِ جداول
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تصحيحُ المالك (2026-08-19 · ثانيًا): «ليس الإغلاقُ «الجداولُ موجودة» — بل
 *   **طلبُ اعتمادٍ حقيقيٌّ يقرأ `gov_ladders` ← يولّد خطواتِه ← يفرض اليدَ
 *   والسقفَ والترتيبَ ← يسجّل أثرَه ← ويُرفض المرورُ عبر المصدرِ القديم**».
 *
 * ◆ فهذا يُنشئ طلبًا فعليًّا بالمحرّكِ نفسِه ويقيس ستَّ دعاوى:
 *   ① السلسلةُ تُولَّد من **السلّم**: عددُ الخطواتِ وترتيبُها وأدوارُها تطابق
 *      `gov_ladder_steps` حرفًا — لا من جدولٍ آخر.
 *   ② **اليدُ مفروضة**: خطوةُ الاعتمادِ لدورِها هي، ودورٌ آخرُ لا يطابقها.
 *   ③ **الترتيبُ مفروض**: الخطوةُ التاليةُ هي أولُ معلَّقةٍ بالترتيبِ لا أيَّ خطوة.
 *   ④ **السقفُ مقروءٌ من السلّم** لا من ثابتٍ في الكود.
 *   ⑤ **الأثرُ مسجَّل**: صفوفٌ في `approval_requests` و`approval_steps`.
 *   ⑥ **المصدرُ القديمُ مقفل**: إدراجُ قاعدةٍ فيه **يُرفض**.
 *
 * ◆ ويُنظِّف أثرَه كاملًا — لا يترك طلبًا في الدفتر.
 * التشغيل: php tests/ladder_engine_live_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/tests/ladder_engine_live_test.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once $ROOT . '/includes/approval_workflow.php';

$pass = 0; $fail = 0;
function ok($c, $m, $d = '') { global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$m}\n"; } else { $fail++; echo "  ✘ FAIL: {$m}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }

echo "════ (ج) محرّكُ الاعتمادِ يقرأ السلّمَ — بطلبٍ حقيقيّ ════\n\n";

/* ── اختيارُ سلّمٍ حيٍّ بأكثرِ من خطوةٍ ليُقاس الترتيبُ واليد ── */
$lad = $conn->query("SELECT entity_type, ladder_code, COUNT(*) steps
                       FROM v_approval_rules_effective
                      WHERE role_required <> -99
                      GROUP BY entity_type, ladder_code HAVING steps >= 2
                      ORDER BY steps DESC LIMIT 1")->fetch_assoc();
if (!$lad) { exit("لا سلّمَ بخطوتَين فأكثرَ للقياس\n"); }
$ENTITY = $lad['entity_type'];
echo "▐ السلّمُ المقيس: {$lad['ladder_code']} · {$ENTITY} · {$lad['steps']} خطوات\n\n";

/* خطواتُ السلّمِ كما في الوثيقة */
$ladderSteps = array();
$r = $conn->query("SELECT step_order, role_required, may_approve, actor_code
                     FROM v_approval_rules_effective
                    WHERE entity_type = '" . $conn->real_escape_string($ENTITY) . "'
                      AND role_required <> -99 ORDER BY step_order");
while ($r && ($x = $r->fetch_assoc())) { $ladderSteps[] = $x; }

/* ── ⑥ المصدرُ القديمُ مقفل (يُقاس أولًا فلا يلوّث الطلبَ لاحقًا) ── */
echo "▐ ⑥ المصدرُ القديمُ مقفل\n";
$try = @$conn->query("INSERT INTO approval_workflow_rules (entity_type, action, role_required, step_order, is_active)
                      VALUES ('__ladder_test__', 'approve', '1', 1, 1)");
ok($try === false, 'إدراجُ قاعدةٍ **نشطةٍ** في المصدرِ القديمِ مرفوض', $try ? 'مرَّ — عيب!' : '');
echo "      رسالةُ القيد: " . mb_substr((string) $conn->error, 0, 80) . "\n";
$activeOld = (int) $conn->query("SELECT COUNT(*) c FROM approval_workflow_rules WHERE is_active=1")->fetch_assoc()['c'];
ok($activeOld === 0, 'صفرُ قاعدةٍ نشطةٍ في المصدرِ القديم — لا مصدرانِ نشطان', "بقي {$activeOld}");

/* ── إنشاءُ طلبٍ حقيقيٍّ بالمحرّك ── */
echo "\n▐ ①+⑤ السلسلةُ تُولَّد من السلّمِ وتُسجَّل\n";
$uid = (int) $conn->query("SELECT MIN(id) i FROM users WHERE company_id = 4")->fetch_assoc()['i'];
$_SESSION['user'] = array('id' => $uid, 'role' => '-1', 'company_id' => 4, 'name' => 'ladder-test');
$before = (int) $conn->query("SELECT COUNT(*) c FROM approval_requests")->fetch_assoc()['c'];

$res = approval_create_request($ENTITY, 999999, 'approve',
        array('probe' => 'ladder_engine_live_test'), $uid, $conn);
$reqId = 0;
if (is_array($res) && isset($res['request_id'])) { $reqId = (int) $res['request_id']; }
elseif (is_numeric($res)) { $reqId = (int) $res; }
if (!$reqId) {
    $q = $conn->query("SELECT id FROM approval_requests WHERE entity_type = '" . $conn->real_escape_string($ENTITY) . "'
                         AND entity_id = 999999 ORDER BY id DESC LIMIT 1");
    if ($q && ($x = $q->fetch_assoc())) { $reqId = (int) $x['id']; }
}
ok($reqId > 0, "طلبٌ حقيقيٌّ أُنشئ (#{$reqId})", is_array($res) ? json_encode($res, JSON_UNESCAPED_UNICODE) : (string) $res);

if ($reqId > 0) {
    $gen = array();
    $r = $conn->query("SELECT step_order, role_required, status FROM approval_steps
                        WHERE request_id = {$reqId} ORDER BY step_order");
    while ($r && ($x = $r->fetch_assoc())) { $gen[] = $x; }

    ok(count($gen) === count($ladderSteps),
       'عددُ الخطواتِ المولَّدةِ = عددُ خطواتِ السلّم (' . count($gen) . '/' . count($ladderSteps) . ')');

    /* ① الترتيبُ والأدوارُ تطابق الوثيقةَ حرفًا */
    $match = true; $detail = '';
    foreach ($ladderSteps as $i => $ls) {
        if (!isset($gen[$i])) { $match = false; $detail = "الخطوة " . ($i + 1) . " غائبة"; break; }
        if ((int) $gen[$i]['step_order'] !== (int) $ls['step_order']
            || (string) $gen[$i]['role_required'] !== (string) $ls['role_required']) {
            $match = false;
            $detail = "الخطوة {$ls['step_order']}: السلّم دور {$ls['role_required']} · المولَّد دور {$gen[$i]['role_required']}";
            break;
        }
    }
    ok($match, '**الترتيبُ والأدوارُ** تطابق السلّمَ حرفًا', $detail);
    foreach ($ladderSteps as $ls) {
        echo "      · خطوة {$ls['step_order']}: {$ls['actor_code']} ⇐ دور {$ls['role_required']}"
           . ((int) $ls['may_approve'] === 1 ? ' ◆ صاحبُ الاعتماد' : ' (إدخالٌ/مراجعة)') . "\n";
    }

    /* ── ③ الترتيبُ مفروض: التاليةُ أولُ معلَّقةٍ بالترتيب ── */
    echo "\n▐ ③ الترتيبُ مفروض\n";
    $next = approval_get_next_pending_step($reqId, $conn);
    $firstPending = null;
    foreach ($gen as $g) { if ($g['status'] === 'pending') { $firstPending = $g; break; } }
    ok($next && $firstPending && (int) $next['step_order'] === (int) $firstPending['step_order'],
       'الخطوةُ التاليةُ هي **أولُ معلَّقةٍ بالترتيب** لا أيَّ خطوة',
       $next ? "المحرّك: {$next['step_order']}" : 'لا خطوة');

    /* ── ② اليدُ مفروضة ── */
    echo "\n▐ ② اليدُ مفروضة\n";
    if ($next) {
        $needed = (string) $next['role_required'];
        $other = null;
        foreach ($ladderSteps as $ls) { if ((string) $ls['role_required'] !== $needed) { $other = (string) $ls['role_required']; break; } }
        ok(approval_user_can_match_role($needed, $needed) === true,
           "صاحبُ اليدِ (دور {$needed}) **يطابق** خطوتَه");
        if ($other !== null) {
            ok(approval_user_can_match_role($needed, $other) === false,
               "دورٌ آخرُ (دور {$other}) **لا يطابق** خطوةَ غيرِه — فاليدُ مفروضةٌ لا معلَنة");
        }
    }

    /* ── ④ السقفُ من السلّمِ لا من ثابتٍ في الكود ── */
    echo "\n▐ ④ السقفُ مقروءٌ من السلّم\n";
    $cap = $conn->query("SELECT cap_kind, cap_amount, cap_currency, cap_state
                           FROM v_approval_rules_effective
                          WHERE entity_type = '" . $conn->real_escape_string($ENTITY) . "' LIMIT 1")->fetch_assoc();
    ok($cap !== null, 'السقفُ يُقرأ مع السلسلةِ من المنظرِ نفسِه',
       $cap ? "kind={$cap['cap_kind']} · amount=" . ($cap['cap_amount'] ?? '—') . " · state={$cap['cap_state']}" : '');
    echo "      · نوعُ السقف: {$cap['cap_kind']} · القيمة: " . ($cap['cap_amount'] ?? '—')
       . " · الحال: {$cap['cap_state']}\n";
    $hardcoded = preg_match_all('~cap_amount\s*[=>]\s*[0-9]{3,}~', (string) @file_get_contents($ROOT . '/includes/approval_workflow.php'));
    ok($hardcoded === 0, 'صفرُ سقفٍ مثبَّتٍ في كودِ المحرّك', "وُجد {$hardcoded}");

    /* ── التنظيف ── */
    $conn->query("DELETE FROM approval_steps WHERE request_id = {$reqId}");
    $conn->query("DELETE FROM approval_requests WHERE id = {$reqId}");
    $after = (int) $conn->query("SELECT COUNT(*) c FROM approval_requests")->fetch_assoc()['c'];
    echo "\n";
    ok($after === $before, "دفترُ التنفيذِ عاد كما كان ({$before}) — لا أثرَ للاختبار");
}
$conn->query("DELETE FROM approval_workflow_rules WHERE entity_type = '__ladder_test__'");

echo "\n══════════════════════════════════════\n";
echo "  النتيجة: {$pass} نجاح · {$fail} فشل\n";
exit($fail === 0 ? 0 : 1);
