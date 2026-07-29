<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-12 — اختبار قبول عقد الحدث المالي FES §3.1/§7.2
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/fes_event_contract_test.php
 *
 * ما يُثبته:
 *   ① البنية: أعمدةُ العقد + جدولُ الآثار بقيده الفريد المركّب.
 *   ② التعبئةُ الرجعية: fes_status بخريطتها · الفترةُ لكل حدث · الطرفُ الموحّد ·
 *      آثارُ الأنواع القابلة للنسب (والمُسقَطُ معلَنٌ لا صامت).
 *   ③ الناشر: حدثٌ جديد يخرج Published بطرفه وفترته وبصمة مستنده وأثره.
 *   ④ الآلة: قائمةُ السماح (انتقالٌ باطل 409 باسمه) · القفلُ التفاؤلي (نسخةٌ
 *      باليةٌ 409) · ختمُ الفاعلين · مرآةُ state/event_status.
 *   ⑤ عطالةُ الأثر: تكرارُ (event × effect × party × بند) يُرفض 1062.
 *
 * البذرُ معزول (FESCT_<pid> · كيانات 98xxxx) ويُكنس أثرُه في النهاية (§3).
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/app/Core/EventValidationException.php';
require_once dirname(__DIR__) . '/app/Core/ServerId.php';
require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Finance/EventStateMachine.php';

use App\Core\EventPublisher;
use App\Core\TenantContext;
use App\Core\TenantDb;
use App\Services\Finance\EventStateMachine as SM;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli(ems_env('DB_HOST'), 'root', '', ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$conn->set_charset('utf8mb4');

$CO = 4; $ACTOR = 1;
$MARK = 'FESCT_' . getmypid();
$ENT = 980000 + (getmypid() % 1000);

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE fe FROM fin_event_effects fe
                    JOIN fin_financial_events e ON e.id = fe.event_id
                   WHERE e.source_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM fin_financial_events WHERE source_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM ems_business_events WHERE source_ref LIKE '{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-12 — عقدُ الحدث المالي FES ══\n");

// ═══ ① البنية ═══
head('① البنية — أعمدةُ العقد وجدولُ الآثار');
$cols = array();
$q = $conn->query("SHOW COLUMNS FROM fin_financial_events");
while ($r = $q->fetch_assoc()) { $cols[$r['Field']] = $r; }
foreach (array('source_line_id','source_doc_version','event_version','causation_id',
               'fiscal_period_id','due_date','party_type','party_id','contract_line_id',
               'approved_by','approved_at','posted_by','posted_at','fes_status') as $c) {
    check(isset($cols[$c]), "العمود {$c} قائم");
}
preg_match_all("/'([^']+)'/", $cols['fes_status']['Type'], $em);
check(count($em[1]) === 14, 'fes_status أربعَ عشرةَ حالةً حصرًا: ' . count($em[1]));
$q = $conn->query("SHOW INDEX FROM fin_event_effects WHERE Key_name='uq_effect'");
check($q && $q->num_rows === 5, 'UQ المركّب الخماسي على الآثار (حدث × أثر × طرف × بند)');

// ═══ ② التعبئة الرجعية ═══
head('② التعبئةُ الرجعية — بخريطتها المقيسة');
$m = $conn->query("SELECT
    SUM(state='posted'   AND fes_status<>'Posted') p_bad,
    SUM(state='draft'    AND event_status='active' AND fes_status<>'Draft' AND fes_status NOT IN ('Published')) d_bad,
    SUM(state IN ('fin_review','audited','dept_review','dept_approved') AND fes_status<>'UnderReview') r_bad,
    SUM(event_status='reversed' AND fes_status<>'Reversed') v_bad,
    SUM(fiscal_period_id IS NULL) no_period
    FROM fin_financial_events WHERE COALESCE(is_deleted,0)=0")->fetch_assoc();
check(intval($m['p_bad']) === 0, 'كلُّ مرحَّلٍ Posted');
check(intval($m['d_bad']) === 0, 'كلُّ مسودةٍ Draft (أو Published لوليد الناشر)');
check(intval($m['r_bad']) === 0, 'كلُّ ما في المراجعة UnderReview');
check(intval($m['v_bad']) === 0, 'كلُّ معكوسٍ Reversed');
check(intval($m['no_period']) === 0, 'صفرُ حدثٍ بلا فترةٍ مالية');

$m = $conn->query("SELECT
    SUM((supplier_entity_id>0 OR customer_entity_id>0 OR operator_employee_id>0)
        AND party_type IS NULL) party_gap,
    SUM(event_type IN ('revenue','receivable') AND customer_entity_id>0
        AND party_type<>'customer') rev_bad
    FROM fin_financial_events WHERE COALESCE(is_deleted,0)=0")->fetch_assoc();
check(intval($m['party_gap']) === 0, 'الطرفُ الموحّد معبّأٌ حيث مرجعٌ معمور');
check(intval($m['rev_bad']) === 0, 'الإيرادُ ذو العميل طرفُه العميل (أسبقيةُ النوع عند التعدد)');

// الآثار: الأنواع الخمسة القابلة للنسب مغطّاة، والمُسقَط معلَن
$m = $conn->query("SELECT
    SUM(e.event_type IN ('revenue','receivable','payable','payroll','settlement') AND x.event_id IS NULL) core_gap,
    SUM(e.event_type='enterprise' AND x.event_id IS NOT NULL) ent_effects
    FROM fin_financial_events e
    LEFT JOIN (SELECT DISTINCT event_id FROM fin_event_effects) x ON x.event_id = e.id
    WHERE COALESCE(e.is_deleted,0)=0 AND e.source_ref NOT LIKE '{$MARK}%'")->fetch_assoc();
check(intval($m['core_gap']) === 0, 'أثرٌ لكل حدثٍ من الأنواع الخمسة القابلة للنسب');
check(intval($m['ent_effects']) === 0, 'وأحداثُ enterprise التاريخية بلا أثرٍ مشتقٍّ (إسقاطٌ معلَنٌ في البطاقة)');

// ═══ ③ الناشر ═══
head('③ الناشرُ يكتب العقدَ كاملًا (Published · طرف · فترة · بصمة · أثر)');
$ev = array(
    'event_key'     => 'revenue.unit.recognized',
    'category'      => 'financial',
    'source_module' => 'sales',
    'company_id'    => $CO,
    'entity_type'   => 'unit_entry',
    'entity_id'     => $ENT,
    'occurred_at'   => '2026-07-15 10:00:00',
    'created_by'    => $ACTOR,
    'payload'       => array('marker' => $MARK),
    'legacy_event_type' => 'revenue',
    'amount'        => 500.00,
    'currency'      => 'SDG',
    'customer_entity_id' => 1,
    'source_ref'    => $MARK . '_P1',
    'source_line_id' => 3,
    'source_doc_version' => 2,
    'causation_id'  => $MARK . '-CAUSE',
    'due_date'      => '2026-08-15',
);
$r = EventPublisher::publish($conn, $ev);
check(!$r['duplicate'] && $r['id'] > 0, "نُشر الحدث #{$r['id']}");
$row = $conn->query("SELECT * FROM fin_financial_events WHERE id={$r['id']}")->fetch_assoc();
check($row['fes_status'] === 'Published', 'وُلد Published (الناشرُ هو خطوةُ النشر)');
check($row['party_type'] === 'customer' && intval($row['party_id']) === 1, 'الطرفُ الموحّد اشتُقّ: customer#1');
check($row['fiscal_period_id'] !== null, 'الفترةُ المالية خُتمت: #' . $row['fiscal_period_id']);
check(intval($row['source_line_id']) === 3 && intval($row['source_doc_version']) === 2,
    'بصمةُ المستند: سطر 3 × نسخة 2');
check($row['causation_id'] === $MARK . '-CAUSE' && $row['due_date'] === '2026-08-15',
    'السببيةُ والاستحقاق محمولان');
check(intval($row['event_version']) === 1, 'نسخةُ الحدث تبدأ 1');
$eff = $conn->query("SELECT * FROM fin_event_effects WHERE event_id={$r['id']}")->fetch_assoc();
check($eff && $eff['effect_type'] === 'client_receivable' && $eff['party_type'] === 'customer',
    'الأثرُ وُلد مع رأسه: client_receivable للعميل');

// ═══ ④ الآلة ═══
head('④ آلةُ الحالات — سماحٌ · قفلٌ تفاؤلي · ختمُ فاعلين · مرايا');
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));
$eid = intval($r['id']);

$t = SM::transition($gate, $conn, $eid, 'Posted', $ACTOR);
check(!$t['ok'] && $t['code'] === 409 && strpos($t['reasons'][0], 'Published') !== false,
    'Published → Posted خارج القائمة: يُرفض 409 باسمه');

$t = SM::transition($gate, $conn, $eid, 'UnderReview', $ACTOR);
check($t['ok'] && $t['version'] === 2, 'Published → UnderReview: نسخة 2');

// قفلُ التزامن: محاولةٌ بنسخةٍ بالية (1) بعد أن صارت 2
$t = SM::transition($gate, $conn, $eid, 'Approved', $ACTOR, 1);
check(!$t['ok'] && $t['code'] === 409 && strpos($t['reasons'][0], 'تعارض') !== false,
    'نسخةٌ باليةٌ تُرفض 409 — الأولُ يمضي والثاني Conflict (§7.3)');

$t = SM::transition($gate, $conn, $eid, 'Approved', 77);
check($t['ok'], 'UnderReview → Approved بالنسخة الحية');
$row = $conn->query("SELECT * FROM fin_financial_events WHERE id={$eid}")->fetch_assoc();
check(intval($row['approved_by']) === 77 && $row['approved_at'] !== null, 'خُتم approved_by/at');
check($row['state'] === 'approved', 'المرآةُ القديمة لحقت: state=approved');

$t = SM::transition($gate, $conn, $eid, 'Posted', 78);
check($t['ok'], 'Approved → Posted');
$row = $conn->query("SELECT * FROM fin_financial_events WHERE id={$eid}")->fetch_assoc();
check(intval($row['posted_by']) === 78 && $row['posted_at'] !== null, 'خُتم posted_by/at');

$t = SM::transition($gate, $conn, $eid, 'Reversed', $ACTOR);
check($t['ok'], 'Posted → Reversed');
$row = $conn->query("SELECT event_status, fes_status FROM fin_financial_events WHERE id={$eid}")->fetch_assoc();
check($row['event_status'] === 'reversed', 'مرآةُ العكس: event_status=reversed');
$t = SM::transition($gate, $conn, $eid, 'Closed', $ACTOR);
check(!$t['ok'], 'Reversed نهائية: لا انتقالَ منها');

// ═══ ⑤ عطالة الأثر ═══
head('⑤ عطالةُ الأثر — UQ المركّب');
$okq = $conn->query("INSERT INTO fin_event_effects
    (company_id, event_id, effect_type, party_type, party_id, contract_line_id, amount, status)
    VALUES ({$CO}, {$eid}, 'client_receivable', 'customer', 1, 0, 500.00, 'active')");
check($okq === false && $conn->errno === 1062,
    'تكرارُ الأثر نفسِه للطرف نفسِه يُرفض 1062: ' . $conn->errno);

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
