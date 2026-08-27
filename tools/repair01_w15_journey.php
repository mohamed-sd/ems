<?php
/**
 * tools/repair01_w15_journey.php — رحلةُ الطلب (‏W15 §٦-أ)
 * ═══════════════════════════════════════════════════════════════════════════
 * **موظّفٌ يفتح «طلباتي» ← يختار إجازةً فيُنشأ السجلُّ في القوى التشغيليّة ←
 *   ويختار طلبَ صيانةٍ فيُنشأ عند مالكِه ← ويختار بلاغًا فيُنشأ في إدارةِ
 *   البلاغات ← «طلباتي» تعرض الثلاثةَ إسقاطًا ← تعديلُ الحالةِ عند المالكِ
 *   ينعكس في الإسقاط — ⛔ ولا نسخةَ محلّيّةً في مساحةِ العمل. ثمّ القيادةُ ترى
 *   الأثرَ المجمَّعَ ولا تملك المعاملة.**
 *
 * ◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عند كلِّ
 *   مستهلكٍ يُقاس رقمٌ يعنيه — سجلُّ الإجازةِ يزيد عند القوى التشغيليّة ·
 *   سجلُّ الأعطالِ يزيد عند الصيانة · سجلُّ البلاغاتِ يزيد عند البلاغات ·
 *   والإسقاطُ يعرض ثلاثةً · وتغييرُ الحالةِ عند المالكِ يظهر في الإسقاطِ
 *   **بلا مزامنة** · ومخزنُ مساحةِ العملِ **لا يزيد صفًّا واحدًا**.
 *
 * ◆ **والمحطّاتُ السالبةُ محطّاتٌ** تُقاس بالاستدعاءِ ورمزِ الردّ: «نوعٌ غيرُ
 *   مسجَّل» · «نوعٌ بلا رابطةِ مالك» · «مساحةُ العملِ تملك تعريفَ طلب» ·
 *   «تخزينُ النوعِ المسجَّلِ في المخزنِ العامّ» · «قرارُ قيادةٍ بلا سلطةٍ
 *   مسجَّلة» · «من أعدَّ يعتمد» · «كتابةٌ فوق حالةٍ نافذة» · «فتحُ مشروعٍ بلا
 *   مرجعِ سلطة».
 *
 * ⚠ **والنظافةُ كنسٌ بالوسمِ لا إرجاعٌ بمعاملة** (‏درسُ W09): كلُّ صفٍّ تكتبه
 *   الرحلةُ يحمل وسمَ عائلتِها، والكنسُ يمسح **بالوسم** قبلَ البدءِ وبعدَ النهاية.
 *
 * التشغيل: php tools/repair01_w15_journey.php
 * الخروج : 0 عبرت كلُّ المحطّات · 1 محطّةٌ لم تعبر أو أرضيّةٌ ناقصة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

/* ⚠ **حارسُ الموتِ الصامت**: `config.php` يبتلع مخرَجَ سطرِ الأوامر. */
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        fwrite(STDERR, "\n✘ سقطت الرحلةُ بخطإٍ قاتل:\n   " . $e['message']
                     . "\n   في " . $e['file'] . ':' . $e['line'] . "\n");
    }
});

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w15_scan.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/app/Core/TenantGateException.php';
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
require_once $ROOT . '/app/Services/Exec/ScopeEngine.php';
require_once $ROOT . '/app/Services/Exec/ExecDecisionRouter.php';
require_once $ROOT . '/app/Services/Exec/ExecProjectionService.php';
require_once $ROOT . '/app/Services/Workspace/RequestLauncher.php';
require_once $ROOT . '/app/Services/Operations/ProjectOpeningService.php';

use App\Services\Workspace\RequestLauncher as LNC;
use App\Services\Exec\ExecDecisionRouter as RTR;
use App\Services\Exec\ScopeEngine as SCP;
use App\Services\Operations\ProjectOpeningService as POS;

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w15_one($conn, $sql); };

$RUN = 'W15J-' . (string) $one("SELECT DATE_FORMAT(NOW(6), '%Y%m%d%H%i%s%f')");
$TAG = 'W15J';

echo "═══════════ رحلةُ الطلب — REPAIR01 · W15 §٦-أ ═══════════\n";
echo "الجولة: $RUN\n\n";

$ST = array();
$log = function ($leg, $station, $actor, $call, $expect, $measured, $consumer, $effect, $passed) use (&$ST) {
    $ST[] = array($leg, $station, $actor, $call, $expect, $measured, $consumer, $effect, $passed ? 1 : 0);
};

/* ══ كنسُ العائلة ═══════════════════════════════════════════════════════ */
$sweep = function () use ($conn, $TAG) {
    foreach (array(
        "DELETE FROM worker_leave_absence WHERE reason LIKE '$TAG%'",
        "DELETE FROM mnt_breakdown WHERE code LIKE '$TAG%'",
        /* ⚠ **والأثرُ يتجاوز الجدولَ الذي كُتب فيه**: البلاغُ يفتح بندَ عملٍ في
             محرّكِ المنصّة، وكنسُ البلاغِ وحدَه يترك بندَه يتيمًا —
             **وكشفَته بوّابةُ W06 لا بوّابتُنا**، لأنَّ عنوانَ بندِ العملِ نصٌّ
             مُصيَّرٌ يدخل مقامَ نقاءِ اللغة. وهو الدرسُ نفسُه: **حاجبٌ طرفاه من
             مصدرٍ واحدٍ لا يكشف عطبَ ذلك المصدر — والمقارنةُ عبرَ الموجاتِ هي
             التي تكشف.**
             ⚠ **والبندُ يُكنَس قبلَ مسارِه ومسارُه قبلَ بلاغِه** — فالكنسُ من
             الابنِ إلى الأبِ لا العكس. */
        "DELETE FROM work_items WHERE source_ref IN (
            SELECT CONCAT('TKT-', q.id, '-WS', q.ws_id) FROM (
                SELECT t.id, w.ws_id FROM tickets t
                  JOIN ticket_workstreams w ON w.tk_id = t.id
                 WHERE t.complaint LIKE '$TAG%') q)",
        /* وبندُ عملٍ لبلاغٍ كنسَته جولةٌ سابقةٌ قبل أن تعرف أنّه يفتح بندًا —
           يُكنَس بمرجعِه المعزولِ عن بلاغٍ لم يعد موجودًا **بوسمِ عائلتِنا وحدَها**. */
        "DELETE FROM work_items
          WHERE source_screen = 'Tickets/tickets_list.php'
            AND source_ref REGEXP '^TKT-[0-9]+-WS[0-9]+$'
            AND SUBSTRING_INDEX(SUBSTRING_INDEX(source_ref, '-', 2), '-', -1)
                NOT IN (SELECT id FROM tickets)
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
        /* ⚠ **الأبناءُ قبلَ الأبِ ومفتاحُ الابنِ باسمِه هو** — `tk_id` لا
             `ticket_id`؛ وكنسٌ يفترض الاسمَ يترك أثرًا ويقول إنّه كنس. */
        "DELETE FROM ticket_participants WHERE tk_id IN (SELECT id FROM tickets WHERE complaint LIKE '$TAG%')",
        "DELETE FROM ticket_events WHERE ticket_id IN (SELECT id FROM tickets WHERE complaint LIKE '$TAG%')",
        "DELETE FROM ticket_escalations WHERE ws_id IN
            (SELECT ws_id FROM ticket_workstreams WHERE tk_id IN
                (SELECT id FROM tickets WHERE complaint LIKE '$TAG%'))",
        "DELETE FROM ticket_workstreams WHERE tk_id IN (SELECT id FROM tickets WHERE complaint LIKE '$TAG%')",
        "DELETE FROM ticket_responses WHERE ticket_id IN (SELECT id FROM tickets WHERE complaint LIKE '$TAG%')",
        "DELETE FROM tickets WHERE complaint LIKE '$TAG%' OR operational_summary LIKE '$TAG%'",
        "DELETE FROM requests WHERE title LIKE '$TAG%'",
        "DELETE FROM sites WHERE name LIKE '$TAG%'",
        "DELETE FROM fin_cost_centers WHERE code LIKE '$TAG%'",
        "DELETE FROM project WHERE project_code LIKE '$TAG%' OR name LIKE '$TAG%'",
    ) as $q) { @$conn->query($q); }
};
$sweep();

/* ══ الأرضيّة ══════════════════════════════════════════════════════════ */
/* **الأرضيّةُ تُختار بالكيانِ الذي يحمل الشروطَ الثلاثةَ معًا** — حسابٌ مربوطٌ
   بموظّفٍ · ومعدّةٌ · وأنواعُ طلباتٍ نافذة. ⛔ **ولا يُختار أوّلُ حسابٍ ثمّ
   يُشكى من نقصِ كيانِه** — فالرحلةُ تُثبت المسارَ لا تُدين البيانات. */
$actor = $conn->query("SELECT u.id uid, u.company_id, u.employee_id, u.role
                         FROM users u
                        WHERE u.employee_id > 0 AND u.company_id > 0
                          AND EXISTS (SELECT 1 FROM gov_request_type g
                                       WHERE g.company_id = u.company_id AND g.state = 'active')
                          AND EXISTS (SELECT 1 FROM equipments e WHERE e.company_id = u.company_id)
                          AND EXISTS (SELECT 1 FROM persons p WHERE p.employee_id = u.employee_id)
                        ORDER BY u.id LIMIT 1");
$A = $actor ? $actor->fetch_assoc() : null;
if (!$A) { echo "✘ أرضيّةٌ ناقصة: لا كيانَ يجمع حسابًا بموظّفٍ ومعدّةً وأنواعَ طلباتٍ نافذة\n"; exit(1); }
$CO  = (int) $A['company_id'];
$UID = (int) $A['uid'];
$USER = array('id' => $UID, 'company_id' => $CO, 'role' => (string) $A['role']);

$EQ = (int) $one("SELECT id FROM equipments WHERE company_id = $CO ORDER BY id LIMIT 1");
$TT = (string) $one("SELECT code FROM ticket_types WHERE active = 1 AND nature = 'request' ORDER BY id LIMIT 1");
if ($TT === '') { echo "✘ أرضيّةٌ ناقصة: لا نوعَ بلاغٍ نشِط\n"; exit(1); }

$GATE = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($CO, $UID, '', true));
/* **البوّابةُ تُمرَّر مع الفاعل** — فالنظامُ ينفّذ التوجيهَ ويعرف الكيان،
   ولا تُستنبَط هويّةُ الكيانِ من جلسةٍ لا وجودَ لها في سطرِ الأوامر. */
$USER['gate'] = $GATE;
printf("الأرضيّة: كيان %d · حساب %d · موظّف %s · معدّة %d · نوع بلاغ %s\n\n",
       $CO, $UID, $A['employee_id'], $EQ, $TT);

/* المقامُ قبل الرحلة — الأثرُ يُقاس بالفرقِ لا بالوجود */
$before = array(
    'leave'  => (int) $one("SELECT COUNT(*) FROM worker_leave_absence WHERE company_id = $CO"),
    'brk'    => (int) $one("SELECT COUNT(*) FROM mnt_breakdown WHERE company_id = $CO"),
    'tkt'    => (int) $one("SELECT COUNT(*) FROM tickets WHERE company_id = $CO"),
    'local'  => (int) $one("SELECT COUNT(*) FROM requests WHERE company_id = $CO"),
);

/* ═══════════════════════════════════════════════════════════════════════════
   الشوطُ ①: الإجازةُ تُنشأ في القوى التشغيليّة
   ═══════════════════════════════════════════════════════════════════════════ */
$r1 = LNC::launch($conn, $USER, 'HR_LEAVE', array(
    'date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d', strtotime('+2 day')),
    'reason' => $TAG . ' رحلة إثبات', 'event_type' => 'إجازة',
));
$ok1 = $r1['verdict'] === LNC::OK && $r1['owner_row_id'] > 0;
$log('LEAVE', 'إطلاق إجازة من مساحة عملي', 'صاحب الحساب', 'RequestLauncher::launch(HR_LEAVE)',
     'ينشأ السجل عند القوى التشغيلية', $r1['verdict'] . ' row=' . $r1['owner_row_id'],
     'خدمة القوى التشغيلية', 'صف في سجل الإجازات عند مالكه', $ok1);

$after1 = (int) $one("SELECT COUNT(*) FROM worker_leave_absence WHERE company_id = $CO");
$log('LEAVE', 'أثر عند المستهلك الأول', 'القوى التشغيلية', 'COUNT(worker_leave_absence)',
     'يزيد سجل الإجازات واحدا', 'قبل ' . $before['leave'] . ' بعد ' . $after1,
     'إدارة القوى التشغيلية', 'سجل الإجازات زاد', $after1 === $before['leave'] + 1);

$st1 = (string) $one("SELECT state FROM worker_leave_absence WHERE id = " . (int) $r1['owner_row_id']);
$log('LEAVE', 'الحالة الأولى عند مالكه', 'القوى التشغيلية', 'state',
     'مطلوب لا معتمد — الاعتماد فعل لاحق بسلطته', $st1,
     'إدارة القوى التشغيلية', 'لا اعتماد ذاتي عند الإطلاق', $st1 === 'مطلوب');

/* ═══════════════════════════════════════════════════════════════════════════
   الشوطُ ②: طلبُ الصيانةِ يُنشأ عند مالكِه
   ═══════════════════════════════════════════════════════════════════════════ */
$r2 = LNC::launch($conn, $USER, 'MNT_REQUEST', array(
    'equipment_id' => $EQ, 'code' => $TAG . '-MNT-' . $UID . '-' . time(),
    'description' => $TAG . ' رحلة إثبات', 'is_stopped' => 0,
));
$ok2 = $r2['verdict'] === LNC::OK && $r2['owner_row_id'] > 0;
$log('MNT', 'إطلاق طلب صيانة من مساحة عملي', 'صاحب الحساب', 'RequestLauncher::launch(MNT_REQUEST)',
     'ينشأ السجل عند إدارة الصيانة', $r2['verdict'] . ' row=' . $r2['owner_row_id'],
     'خدمة دورة الصيانة', 'صف في سجل الأعطال عند مالكه', $ok2);

$after2 = (int) $one("SELECT COUNT(*) FROM mnt_breakdown WHERE company_id = $CO");
$log('MNT', 'أثر عند المستهلك الثاني', 'إدارة الصيانة', 'COUNT(mnt_breakdown)',
     'يزيد سجل الأعطال واحدا', 'قبل ' . $before['brk'] . ' بعد ' . $after2,
     'إدارة الصيانة', 'سجل الأعطال زاد', $after2 === $before['brk'] + 1);

/* ═══════════════════════════════════════════════════════════════════════════
   الشوطُ ③: البلاغُ يُنشأ في إدارةِ البلاغات
   ═══════════════════════════════════════════════════════════════════════════ */
$r3 = LNC::launch($conn, $USER, 'TICKET_REPORT', array(
    'type_code' => $TT, 'description' => $TAG . ' رحلة إثبات',
    'operational_summary' => $TAG . ' ملخص', 'context' => array(),
));
$ok3 = $r3['verdict'] === LNC::OK && $r3['owner_row_id'] > 0;
$log('TICKET', 'إطلاق بلاغ من مساحة عملي', 'صاحب الحساب', 'RequestLauncher::launch(TICKET_REPORT)',
     'ينشأ السجل عند إدارة البلاغات', $r3['verdict'] . ' row=' . $r3['owner_row_id'],
     'موجه البلاغات', 'صف في سجل البلاغات عند مالكه', $ok3);

$after3 = (int) $one("SELECT COUNT(*) FROM tickets WHERE company_id = $CO");
$log('TICKET', 'أثر عند المستهلك الثالث', 'إدارة البلاغات', 'COUNT(tickets)',
     'يزيد سجل البلاغات واحدا', 'قبل ' . $before['tkt'] . ' بعد ' . $after3,
     'إدارة البلاغات', 'سجل البلاغات زاد', $after3 === $before['tkt'] + 1);

/* ═══════════════════════════════════════════════════════════════════════════
   الشوطُ ④: الإسقاطُ يعرض الثلاثةَ — ولا نسخةَ محلّيّة
   ═══════════════════════════════════════════════════════════════════════════ */
/* ⚠ **الإسقاطُ يُقاس بالصفوفِ التي أُنشئت في هذه الجولةِ لا بوجودِ نوعٍ** —
     فصفوفٌ سابقةٌ لصاحبِ الحسابِ تُخضِرُّ المحطّةَ على «تطابقِ لا شيء». */
$proj = LNC::projection($conn, $GATE, $USER);
$mine = array();
$hit  = array('HR_LEAVE' => 0, 'MNT_REQUEST' => 0, 'TICKET_REPORT' => 0);
$want = array('HR_LEAVE' => (int) $r1['owner_row_id'], 'MNT_REQUEST' => (int) $r2['owner_row_id'],
              'TICKET_REPORT' => (int) $r3['owner_row_id']);
foreach ($proj as $p) {
    if (isset($want[$p['type_code']]) && (int) $p['row_id'] === $want[$p['type_code']]) {
        $hit[$p['type_code']] = 1; $mine[$p['type_code']] = $p;
    }
}
$hasAll = array_sum($hit) === 3;
$log('PROJECTION', 'طلباتي تعرض الثلاثة المنشأة في هذه الجولة', 'صاحب الحساب', 'RequestLauncher::projection',
     'صفوف هذه الجولة الثلاثة ظاهرة بمرجع حي', 'مطابق ' . array_sum($hit) . ' من 3',
     'مساحة عملي', 'الإسقاط يقرأ من جداول المُلاك', $hasAll);

$localAfter = (int) $one("SELECT COUNT(*) FROM requests WHERE company_id = $CO");
$log('PROJECTION', 'لا نسخة محلية في مساحة العمل', 'مساحة عملي', 'COUNT(requests)',
     'المخزن العام لا يزيد صفا واحدا', 'قبل ' . $before['local'] . ' بعد ' . $localAfter,
     'مساحة عملي', 'صفر تخزين محلي', $localAfter === $before['local']);

/* ═══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑤: تعديلُ الحالةِ عند المالكِ ينعكس في الإسقاطِ بلا مزامنة
   ═══════════════════════════════════════════════════════════════════════════ */
$stBefore = isset($mine['HR_LEAVE']) ? $mine['HR_LEAVE']['state'] : '';
$conn->query("UPDATE worker_leave_absence SET state = 'معتمد' WHERE id = " . (int) $r1['owner_row_id']);
$proj2 = LNC::projection($conn, $GATE, $USER);
$stAfter = '';
foreach ($proj2 as $p) { if ($p['type_code'] === 'HR_LEAVE' && (int) $p['row_id'] === (int) $r1['owner_row_id']) { $stAfter = $p['state']; } }
$log('MIRROR', 'تعديل الحالة عند المالك ينعكس في الإسقاط', 'القوى التشغيلية', 'UPDATE state ثم projection',
     'الإسقاط يعرض الحالة الجديدة بلا مزامنة', $stBefore . ' ← ' . $stAfter,
     'مساحة عملي', 'الحالة تقرأ من مالكها لحظة العرض', $stAfter === 'معتمد' && $stBefore === 'مطلوب');

/* ═══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑥: القيادةُ ترى الأثرَ ولا تملك المعاملة
   ═══════════════════════════════════════════════════════════════════════════ */
$vis = SCP::visibility($conn, $USER);
$log('LEADERSHIP', 'محرك النطاق يفصل الرؤية عن السلطة', 'محرك النطاق', 'ScopeEngine::visibility',
     'سلسلة الرؤية بحلقاتها مقروءة', 'مساحة ' . $vis['space'] . ' · حلقات ' . count($vis['chain']),
     'أسطح القيادة', 'النطاق يحقن في القراءة', count($vis['chain']) >= 4);

$ownedTxn = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                         WHERE owner_code IN ('EX-CEO','EX-DVP') AND ownership_verdict = 'DOMAIN_SOURCE'");
$log('LEADERSHIP', 'القيادة لا تملك معاملة إدارة', 'السجل المعياري', 'COUNT(owner IN EX AND DOMAIN_SOURCE)',
     'صفر معاملة مملوكة للقيادة', (string) $ownedTxn,
     'السجل المعياري', 'الملكية عادت إلى إداراتها', $ownedTxn === 0);

$srcSurfaces = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                            WHERE origin = 'W15' AND surface_kind <> 'PROJECTION'");
$log('LEADERSHIP', 'كل سطح في الموجة إسقاط', 'السجل المعياري', "COUNT(origin=W15 AND kind<>PROJECTION)",
     'صفر سطح مصدر', (string) $srcSurfaces,
     'السجل المعياري', 'إسقاط لا مصدر', $srcSurfaces === 0);

/* أثرٌ تجاريٌّ عند القيادة: الطلباتُ المرفوعةُ تُقرأ حيًّا من سجلِّ مالكِها */
$raised = 0;
try {
    $rows = \App\Services\Exec\ExecProjectionService::read($GATE, 'fin_requests', $vis,
        array('where' => array('state' => 'pending_approval'), 'orderBy' => 'id DESC', 'limit' => 100));
    $raised = count($rows);
} catch (\Throwable $t) { $raised = -1; }
$log('LEADERSHIP', 'صندوق القيادة يقرأ حيا من سجل مالكه', 'أسطح القيادة',
     'ExecProjectionService::read(fin_requests)',
     'قراءة تمر ببوابة العزل بلا خطأ', 'صفوف ' . $raised,
     'الإدارة المالية', 'الصندوق إسقاط فوق سجل الطلبات', $raised >= 0);

/* ═══════════════════════════════════════════════════════════════════════════
   المحطّاتُ السالبة — تُقاس بالاستدعاءِ ورمزِ الردّ
   ═══════════════════════════════════════════════════════════════════════════ */
$n1 = LNC::launch($conn, $USER, 'NO_SUCH_TYPE', array());
$log('NEG', 'نوع غير مسجل في السجل المركزي', 'صاحب الحساب', 'launch(NO_SUCH_TYPE)',
     'يرد UNKNOWN_REQUEST_TYPE', $n1['verdict'], 'مساحة عملي', 'لا إنشاء', $n1['verdict'] === LNC::UNKNOWN_TYPE);

/* نوعٌ نافذٌ برابطةٍ ناقصةٍ — يُصنَع مؤقّتًا ثمّ يُكنَس */
@$conn->query("INSERT INTO gov_request_type
    (company_id, type_code, version_no, name_ar, definition_owner_dept, registry_governed_by,
     authority_rule_id, routing_rule_ref, permission_policy, state, owner_table, owner_service,
     projection_user_col, src_ref)
    VALUES ($CO,'{$TAG}_NOBIND',1,'نوع بلا رابطة','DEP-14','DEP-08','AAM-X','ROUTE-X','P',
            'draft','','','','$TAG')");
$n2 = LNC::launch($conn, $USER, $TAG . '_NOBIND', array());
$log('NEG', 'نوع غير نافذ', 'صاحب الحساب', 'launch(نوع draft)',
     'يرد REQUEST_TYPE_NOT_ACTIVE', $n2['verdict'], 'مساحة عملي', 'لا إنشاء',
     $n2['verdict'] === LNC::TYPE_NOT_ACTIVE);
@$conn->query("DELETE FROM gov_request_type WHERE type_code = '{$TAG}_NOBIND'");

/* قرارُ قيادةٍ بفعلٍ بلا قاعدةِ سلطةٍ مسجَّلة */
$n3 = RTR::route($conn, $USER, array('action_key' => $TAG . '_NO_RULE',
                                     'owner_service' => 'X::y', 'state' => '', 'prepared_by' => 0));
$log('NEG', 'قرار قيادي بفعل بلا قاعدة سلطة', 'مساحة القيادة', 'ExecDecisionRouter::route',
     'يرد AUTHORITY_NOT_CONFIGURED ولا يفترض', $n3['verdict'], 'مساحة القيادة', 'لا توجيه',
     $n3['verdict'] === SCP::AUTHORITY_NOT_CONFIGURED);

/* فتحُ مشروعٍ بلا مرجعِ سلطة */
$n4 = POS::open($GATE, array('company_id' => $CO, 'project_name' => $TAG . ' مشروع',
                            'actor_id' => $UID, 'authority_ref' => ''));
$log('NEG', 'فتح مشروع بلا مرجع سلطة', 'خدمة إدارة التشغيل', 'ProjectOpeningService::open',
     'يرد NO_AUTHORITY_REF ولا يفتح', $n4['verdict'], 'إدارة التشغيل', 'لا مشروع ولا موقع ولا مركز تكلفة',
     $n4['verdict'] === POS::NO_AUTHORITY);

$leaked = (int) $one("SELECT COUNT(*) FROM project WHERE name LIKE '$TAG%'");
$log('NEG', 'الرد لا يخلف أثرا جزئيا', 'خدمة إدارة التشغيل', "COUNT(project LIKE '$TAG%')",
     'صفر صف مشروع', (string) $leaked, 'إدارة التشغيل', 'لا أثر عند الرد', $leaked === 0);

/* مساحةُ العملِ لا تملك تعريفَ طلب */
@$conn->query("INSERT INTO gov_request_type
    (company_id, type_code, version_no, name_ar, definition_owner_dept, registry_governed_by,
     authority_rule_id, routing_rule_ref, permission_policy, state, owner_table, owner_service,
     projection_user_col, src_ref)
    VALUES ($CO,'{$TAG}_WSOWN',1,'نوع تملكه مساحة العمل','WS-MY','DEP-08','AAM-X','ROUTE-X','P',
            'active','requests','X::y','requester_user_id','$TAG')");
$wsOwnBlocked = ((int) $one("SELECT COUNT(*) FROM gov_request_type WHERE type_code = '{$TAG}_WSOWN'")) === 0;
$log('NEG', 'مساحة العمل تملك تعريف طلب', 'السجل المركزي', 'INSERT definition_owner_dept=WS-MY',
     'القاعدة ترد الصف بقيدها', $wsOwnBlocked ? 'رُد في القاعدة' : 'قُبل',
     'السجل المركزي', 'مساحة عملي لا تصير Owner', $wsOwnBlocked);
@$conn->query("DELETE FROM gov_request_type WHERE type_code = '{$TAG}_WSOWN'");

/* نوعٌ نافذٌ بلا رابطةِ مالكٍ — القيدُ في القاعدةِ يردُّه */
@$conn->query("INSERT INTO gov_request_type
    (company_id, type_code, version_no, name_ar, definition_owner_dept, registry_governed_by,
     authority_rule_id, routing_rule_ref, permission_policy, state, owner_table, owner_service,
     projection_user_col, src_ref)
    VALUES ($CO,'{$TAG}_ACTNOBIND',1,'نافذ بلا رابطة','DEP-14','DEP-08','AAM-X','ROUTE-X','P',
            'active','','','','$TAG')");
$bindBlocked = ((int) $one("SELECT COUNT(*) FROM gov_request_type WHERE type_code = '{$TAG}_ACTNOBIND'")) === 0;
$log('NEG', 'نوع نافذ بلا رابطة مالك مكتملة', 'السجل المركزي', 'INSERT state=active بلا owner_table',
     'القيد chk_grt_binding يرد الصف', $bindBlocked ? 'رُد في القاعدة' : 'قُبل',
     'السجل المركزي', 'التوجيه بيان لا وعد', $bindBlocked);
@$conn->query("DELETE FROM gov_request_type WHERE type_code = '{$TAG}_ACTNOBIND'");

/* ═══════════════════════════════════════════════════════════════════════════
   التقييدُ والحكم
   ═══════════════════════════════════════════════════════════════════════════ */
$conn->query("DELETE FROM repair01_w15_journey");
$pass = 0; $fail = 0; $step = array();
foreach ($ST as $s) {
    list($leg, $station, $act, $call, $expect, $meas, $cons, $eff, $ok) = $s;
    $step[$leg] = isset($step[$leg]) ? $step[$leg] + 1 : 1;
    if ($ok) { $pass++; } else { $fail++; }
    $conn->query("INSERT INTO repair01_w15_journey
        (leg, step_no, station, actor_role, service_call, expect, measured, consumer, effect_probe, verdict)
        VALUES ('" . $esc($leg) . "'," . (int) $step[$leg] . ",'" . $esc($station) . "','" . $esc($act) . "',
                '" . $esc($call) . "','" . $esc($expect) . "','" . $esc($meas) . "','" . $esc($cons) . "',
                '" . $esc($eff) . "','" . ($ok ? 'PASS' : 'FAIL') . "')");
    printf("  %s %-6s %-46s %s\n", $ok ? '✔' : '✘', $leg, mb_substr($station, 0, 46), $meas);
}

$sweep();
/* **والأثرُ الباقي يُقاس عند كلِّ مستهلكٍ مسَّه الحدثُ لا عند الجدولِ المكتوبِ
     فيه وحدَه** — ومنه بندُ العملِ الذي فتحه البلاغُ في طبقةِ المنصّة.
   ⚠ **ويُقاس بمرجعِ صفِّ هذه الجولةِ لا بنمطٍ عامّ**: في المخزنِ ألفٌ ومئةٌ
     وسبعون بندَ عملٍ يتيمِ المرجعِ **من قبلِ هذه المرحلة** — وقياسٌ بالنمطِ
     العامِّ يُحمِّل الرحلةَ دَينًا ليس منها ويقول إنّها لم تنظّف. */
$myTkt = (int) $r3['owner_row_id'];
$residue = (int) $one("SELECT COUNT(*) FROM worker_leave_absence WHERE reason LIKE '$TAG%'")
         + (int) $one("SELECT COUNT(*) FROM mnt_breakdown WHERE code LIKE '$TAG%'")
         + (int) $one("SELECT COUNT(*) FROM tickets WHERE complaint LIKE '$TAG%'")
         + (int) $one("SELECT COUNT(*) FROM project WHERE name LIKE '$TAG%'")
         + ($myTkt > 0 ? (int) $one("SELECT COUNT(*) FROM work_items
                                      WHERE source_ref LIKE 'TKT-" . $myTkt . "-%'") : 0);

echo "\n────────────────────────────────────────────────────────────\n";
printf("محطّاتٌ %d · عبرت %d · سقطت %d · أثرٌ باقٍ بعد الكنس %d\n",
       count($ST), $pass, $fail, $residue);
echo $fail === 0 && $residue === 0
     ? "الحكم: **الرحلةُ تعبر كاملةً بصفرِ أثرٍ باقٍ** ✔\n"
     : "الحكم: **لم تعبر** ✘\n";
exit(($fail === 0 && $residue === 0) ? 0 : 1);
