<?php
/**
 * tests/contract_state_machine_test.php — H-02
 * ═══════════════════════════════════════════════════════════════════════════
 * آلةُ حالات العقد (OPM-01 §3).
 *
 * ما يُثبته:
 *   ① الحالاتُ الاثنتا عشرة في ENUM **حرفًا بحرف** كما في الآلة — وENUM يبتلع
 *      المخالفَ صامتًا، فالمطابقةُ نصيّةٌ لا استحسان.
 *   ② **قائمةُ سماحٍ لا قائمةُ منع**: كلُّ انتقالٍ غيرِ مذكورٍ **مرفوض** —
 *      يُفحص الجدولُ الكامل (12×12 = 144 خانة) لا عيّنةٌ منه.
 *   ③ **الحارسُ في الخدمة لا في الشاشة**: انتقالٌ غيرُ مشروعٍ يُردّ بـ422
 *      بأسبابه المسمّاة، **ولا صفَّ يتغيّر**.
 *   ④ النهائيةُ بلا رجوع: `مصفّى` لا يخرج منها شيء (423).
 *   ⑤ **التعليقُ يحفظ ما قبله والاستئنافُ يعود إليه** — لا إلى حالةٍ مفترَضة.
 *      وسببُ التعليق إلزام.
 *   ⑥ **«نافذ» تعريفٌ واحدٌ محسوم** (مدخلُ H-01) — و«معلَّق» ليس نافذًا.
 *   ⑦ **وراثةُ الحالة**: تعليقٌ يجمّد ما تحته · وانتهاءٌ يُقفله · ولا فرعَ نشطٌ
 *      تحت عقدٍ غيرِ نافذ.
 *   ⑧ الاشتقاقُ الابتدائيُّ على العقود التسعة مطابقٌ لشواهده.
 *   ⑨ التقريرُ لا يقارن بمفرداتٍ ميتة.
 *
 * يعمل على عقدٍ حقيقيٍّ ويعيد حالتَه بالضبط. لا يُنشأ عقدٌ وهمي.
 * التشغيل: php tests/contract_state_machine_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'csm test');
require_once dirname(__DIR__) . '/app/Services/Contract/ContractStateMachine.php';

use App\Services\Contract\ContractStateMachine as CSM;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();
$CO = 4;

fwrite(STDOUT, "\n══ H-02 — آلةُ حالات العقد ══\n");

// ═══ ① القاموسُ حرفًا بحرف ═══
head('① الحالاتُ الاثنتا عشرة — نصًّا لا استحسانًا');
$col = $conn->query("SHOW COLUMNS FROM contracts LIKE 'contract_status'")->fetch_assoc();
preg_match_all("/'([^']+)'/", $col['Type'], $m);
check(strpos($col['Type'], 'enum') === 0, 'العمودُ صار ENUM لا نصًّا حرًّا: ' . substr($col['Type'], 0, 24) . '…');
check($m[1] === CSM::ALL,
    'وقيمُه = ثوابتُ الآلة حرفًا بحرف (' . count($m[1]) . ') — فلا ابتلاعَ صامت');
check(count(CSM::ALL) === 12, 'اثنتا عشرة حالة: ' . count(CSM::ALL));

// ═══ ② قائمةُ سماحٍ لا قائمةُ منع ═══
head('② كلُّ انتقالٍ غيرِ مذكورٍ مرفوضٌ — الجدولُ الكامل 12×12');
$allowed = 0; $denied = 0; $leaks = array();
foreach (CSM::ALL as $from) {
    foreach (CSM::ALL as $to) {
        if (CSM::canTransition($from, $to)) {
            $allowed++;
            // كلُّ مسموحٍ يجب أن يكون منصوصًا في الخريطة
            if (!in_array($to, CSM::TRANSITIONS[$from], true)) { $leaks[] = "{$from}→{$to}"; }
        } else { $denied++; }
    }
}
check(empty($leaks), 'لا انتقالَ مسموحٌ خارج الخريطة' . ($leaks ? ' (' . implode(',', $leaks) . ')' : ''));
info("مسموح {$allowed} · مرفوض {$denied} من " . (12 * 12));
check($denied > $allowed * 4, 'والمرفوضُ أضعافُ المسموح — قائمةُ سماحٍ لا منع');
check(!CSM::canTransition(CSM::DRAFT, CSM::RUNNING), 'مسودة ← قيد التنفيذ: مرفوض (لا قفزَ فوق التوقيع)');
check(!CSM::canTransition(CSM::SIGNED, CSM::RUNNING), 'وموقَّع ← قيد التنفيذ: مرفوض (النفاذُ أولًا)');
check(CSM::canTransition(CSM::SIGNED, CSM::EFFECTIVE), 'وموقَّع ← نافذ: مشروع');
check(!CSM::canTransition(CSM::ENDED, CSM::RUNNING), 'ومنتهٍ ← قيد التنفيذ: مرفوض (لا إحياءَ لمنتهٍ)');
check(!CSM::canTransition('حالةٌ مخترَعة', CSM::RUNNING), 'وحالةٌ لا وجودَ لها: مرفوضة');

// ═══ ⑥ «نافذ» — التعريفُ الواحد ═══
head('⑥ «نافذ» — مدخلُ H-01 بتعريفٍ واحد');
check(CSM::isEffective(CSM::EFFECTIVE) && CSM::isEffective(CSM::RUNNING)
      && CSM::isEffective(CSM::AMENDED) && CSM::isEffective(CSM::RENEWED),
    'النافذةُ أربع: نافذ · قيد التنفيذ · معدَّل · مجدَّد');
check(!CSM::isEffective(CSM::SUSPENDED), '**و«معلَّق» ليس نافذًا** — التعليقُ يجمّد فلا تُفتح تحته حاوية');
check(!CSM::isEffective(CSM::SIGNED) && !CSM::isEffective(CSM::ENDED)
      && !CSM::isEffective(CSM::CLOSED) && !CSM::isEffective(CSM::SETTLED),
    'وما قبلها وما بعدها ليست نافذة');
$eff = (int) $conn->query("SELECT COUNT(*) c FROM contracts WHERE company_id={$CO}
                            AND contract_status IN ('نافذ','قيد التنفيذ','معدَّل','مجدَّد')")->fetch_assoc()['c'];
info("العقودُ النافذةُ حيًّا: {$eff} — وهي وحدَها التي تُفتح لها حاوياتٌ في H-01");

// ═══ ⑦ وراثةُ الحالة ═══
head('⑦ وراثةُ الحالة — تنزل ولا تصعد');
$i = CSM::inheritedState(CSM::SUSPENDED);
check(empty($i['active']) && mb_strpos($i['reason'], 'معلَّق') !== false,
    'تعليقٌ يجمّد ما تحته: ' . $i['reason']);
$i = CSM::inheritedState(CSM::ENDED);
check(empty($i['active']) && mb_strpos($i['reason'], 'مُقفلٌ للتسجيل ويبقى للقراءة') !== false,
    'وانتهاءٌ يُقفل للتسجيل ويُبقي للقراءة: ' . $i['reason']);
$i = CSM::inheritedState(CSM::SIGNED);
check(empty($i['active']) && mb_strpos($i['reason'], 'ولا فرعَ نشطٌ تحت عقدٍ غيرِ نافذ') !== false,
    'ولا فرعَ نشطٌ تحت غيرِ نافذ: ' . $i['reason']);
check(!empty(CSM::inheritedState(CSM::RUNNING)['active']), 'والنافذُ فرعُه نشط');
$src = file_get_contents(dirname(__DIR__) . '/app/Services/Contract/ContractStateMachine.php');
check(mb_strpos($src, 'ولا يُغيَّر العقدُ من فرعه') !== false,
    'والاتجاهُ واحدٌ منصوصٌ في الخدمة — لا تُغيَّر حالةُ العقد من فرعه');

// ═══ ③④⑤ الانتقالُ الحيّ على عقدٍ حقيقي ═══
head('③ الحارسُ في الخدمة — ولا صفَّ يتغيّر عند الرفض');
$C = $conn->query("SELECT id, contract_status, pause_state_before, pause_date, resume_date
                     FROM contracts WHERE company_id={$CO} AND contract_status='نافذ'
                     ORDER BY id LIMIT 1")->fetch_assoc();
if (!$C) { bad('لا عقدَ نافذٌ للاختبار'); }
else {
    $CID = (int) $C['id'];
    $restore = function () use ($conn, $CID, $C) {
        $pb = ($C['pause_state_before'] === null) ? 'NULL'
              : "'" . $conn->real_escape_string($C['pause_state_before']) . "'";
        $pd = ($C['pause_date'] === null) ? 'NULL' : "'" . $C['pause_date'] . "'";
        $rd = ($C['resume_date'] === null) ? 'NULL' : "'" . $C['resume_date'] . "'";
        $conn->query("UPDATE contracts SET contract_status='" . $conn->real_escape_string($C['contract_status'])
                     . "', pause_state_before={$pb}, pause_date={$pd}, resume_date={$rd} WHERE id={$CID}");
        $conn->query("DELETE FROM ems_business_events
                       WHERE event_key='contract.state.changed' AND entity_id={$CID}");
    };
    $restore();
    register_shutdown_function($restore);
    info("العقدُ #{$CID} — حالتُه «{$C['contract_status']}»");

    $r = CSM::transition($conn, $gate, $CO, $CID, CSM::SETTLED, 'قفزةٌ غيرُ مشروعة', 1);
    check(empty($r['ok']) && $r['code'] === 422, 'نافذ ← مصفّى: 422 — ' . mb_substr($r['reason'], 0, 90));
    check(mb_strpos($r['reason'], 'والمشروعُ من هنا') !== false, 'والرسالةُ تسمّي المشروعَ بدله');
    $now = $conn->query("SELECT contract_status FROM contracts WHERE id={$CID}")->fetch_assoc();
    check((string) $now['contract_status'] === 'نافذ', 'ولا صفَّ تغيّر — الرفضُ قبل الكتابة');

    $r = CSM::transition($conn, $gate, $CO, $CID, CSM::RUNNING, 'اكتمل التخصيص', 1);
    check(!empty($r['ok']) && !empty($r['changed']), 'ونافذ ← قيد التنفيذ يقع');
    $now = $conn->query("SELECT contract_status FROM contracts WHERE id={$CID}")->fetch_assoc();
    check((string) $now['contract_status'] === 'قيد التنفيذ', 'والسجلُّ يحمله');
    $ev = (int) $conn->query("SELECT COUNT(*) c FROM ems_business_events
                               WHERE event_key='contract.state.changed' AND entity_id={$CID}")
                     ->fetch_assoc()['c'];
    check($ev === 1, "وحقيقتُه في الجذر: {$ev}");

    $r = CSM::transition($conn, $gate, $CO, $CID, CSM::RUNNING, '', 1);
    check(!empty($r['ok']) && empty($r['changed']), 'والانتقالُ إلى الحالة نفسِها: 200 بلا تغيير (عطالة)');

    // ═══ ⑤ التعليقُ والاستئناف ═══
    head('⑤ التعليقُ يحفظ ما قبله — والاستئنافُ يعود إليه');
    $r = CSM::suspend($conn, $gate, $CO, $CID, '', 1);
    check(empty($r['ok']) && $r['code'] === 422 && mb_strpos($r['reason'], 'سببُ التعليق إلزامي') !== false,
        'تعليقٌ بلا سبب: مرفوض — ' . mb_substr($r['reason'], 0, 70));
    $r = CSM::suspend($conn, $gate, $CO, $CID, 'خلافٌ على الكميات', 1);
    check(!empty($r['ok']), 'وبسببه يقع');
    $row = $conn->query("SELECT contract_status, pause_state_before, pause_date
                           FROM contracts WHERE id={$CID}")->fetch_assoc();
    check((string) $row['contract_status'] === 'معلَّق', 'الحالةُ «معلَّق»');
    check((string) $row['pause_state_before'] === 'قيد التنفيذ',
        '**وما قبله محفوظ**: ' . $row['pause_state_before'] . ' — فلا يُخمَّن عند العودة');
    check($row['pause_date'] !== null, 'وبتاريخه');

    // التعليقُ لا يمرّ من الباب العام
    $r = CSM::transition($conn, $gate, $CO, $CID, CSM::EFFECTIVE, 'محاولةُ التفاف', 1);
    check(empty($r['ok']) && mb_strpos($r['reason'], 'يُستأنَف أولًا') !== false,
        'ولا يخرج من التعليق إلا بالاستئناف: ' . mb_substr($r['reason'], 0, 70));

    $r = CSM::resume($conn, $gate, $CO, $CID, 'حُسم الخلاف', 1);
    check(!empty($r['ok']) && $r['to'] === 'قيد التنفيذ',
        '**والاستئنافُ يعود إلى حيث كان** لا إلى «نافذ»: ' . $r['to']);
    $row = $conn->query("SELECT contract_status, resume_date FROM contracts WHERE id={$CID}")->fetch_assoc();
    check((string) $row['contract_status'] === 'قيد التنفيذ' && $row['resume_date'] !== null,
        'والسجلُّ يحمله بتاريخ الاستئناف');

    // ═══ ④ النهائيةُ بلا رجوع ═══
    head('④ النهائيةُ بلا رجوع');
    $conn->query("UPDATE contracts SET contract_status='مصفّى' WHERE id={$CID}");
    foreach (array(CSM::RUNNING, CSM::CLOSED, CSM::EFFECTIVE) as $t) {
        $r = CSM::transition($conn, $gate, $CO, $CID, $t, 'محاولة', 1);
        check(empty($r['ok']) && $r['code'] === 423, "مصفّى ← {$t}: 423 نهائيةٌ بلا رجوع");
    }
    $r = CSM::suspend($conn, $gate, $CO, $CID, 'محاولة', 1);
    check(empty($r['ok']), 'ولا تُعلَّق مصفّاة');
    $restore();
    check((string) $conn->query("SELECT contract_status FROM contracts WHERE id={$CID}")
                        ->fetch_assoc()['contract_status'] === $C['contract_status'],
        'وأُعيدت حالةُ العقد كما كانت بالضبط');
}

// ═══ ⑧ الاشتقاقُ الابتدائي ═══
head('⑧ الاشتقاقُ الابتدائيُّ مطابقٌ لشواهده');
$rows = $conn->query("SELECT id, contract_status, status, pause_date, resume_date, actual_end,
                        (SELECT COUNT(*) FROM operations o WHERE o.contract_id=c.id) ops
                        FROM contracts c WHERE company_id={$CO} ORDER BY id")->fetch_all(MYSQLI_ASSOC);
check(count($rows) === 9, 'العقودُ التسعة: ' . count($rows));
$nulls = 0; $wrong = array();
foreach ($rows as $r) {
    if ($r['contract_status'] === null) { $nulls++; continue; }
    $exp = null;
    if ((int) $r['status'] === 0 && $r['pause_date'] !== null && $r['resume_date'] === null) { $exp = 'معلَّق'; }
    elseif ($r['actual_end'] !== null && $r['actual_end'] < date('Y-m-d')) { $exp = 'منتهٍ'; }
    elseif ((int) $r['ops'] > 0) { $exp = 'قيد التنفيذ'; }
    else { $exp = 'نافذ'; }
    if ((string) $r['contract_status'] !== $exp) { $wrong[] = $r['id'] . ':' . $r['contract_status'] . '≠' . $exp; }
}
check($nulls === 0, "ولا عقدَ بلا حالة: {$nulls}");
check(empty($wrong), 'وكلُّ حالةٍ مطابقةٌ لشاهدها' . ($wrong ? ' (' . implode(' · ', $wrong) . ')' : ''));
$dist = array();
foreach ($rows as $r) { $dist[(string) $r['contract_status']] = (isset($dist[(string) $r['contract_status']]) ? $dist[(string) $r['contract_status']] : 0) + 1; }
info('التوزيع: ' . json_encode($dist, JSON_UNESCAPED_UNICODE));

// ═══ ⑨ لا مفرداتٍ ميتة ═══
head('⑨ التقريرُ لا يقارن بمفرداتٍ ميتة');
$rep = file_get_contents(dirname(__DIR__) . '/emsreports/reports/_report_template.php');
foreach (array("'paused'", "'terminated'", "'merged'") as $dead) {
    check(mb_strpos($rep, "contract_status=" . $dead) === false,
        "لا مقارنةَ بـ{$dead} — قيمةٌ لا يمكن أن توجد في ENUM");
}
check(mb_strpos($rep, "contract_status='معلَّق'") !== false, 'ويقارن بالمفردة الحيّة «معلَّق»');
check(mb_strpos($rep, 'ContractStateMachine::ALL') !== false,
    'ومرشِّحُه يُبنى من الآلة نفسِها — فلا قائمةٌ ثانيةٌ تفترق عنها');

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
