<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * N-01 — إثباتُ منع التكرار بإعادةِ إرسالٍ فعلية (لا فحصَ بنيةٍ فقط)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/idempotency_resend_test.php
 *
 * كلُّ قسمٍ يعيد الإرسالَ **فعليًّا** ويثبت صفرَ صفٍّ جديد:
 *   ① الدفتر المالي: publish ×2 → duplicate=true · صفٌّ واحد · أثرٌ واحد.
 *   ② الجذر المحايد: publishFact ×2 → المعرّفُ نفسُه · صفٌّ واحد.
 *   ③ القيدُ الآلي: fin_auto_journal ×2 → القيدُ نفسُه · قيدٌ حيٌّ واحد.
 *   ④ دفترُ عطالة المروحة: تكرارُ (أب × نوع أثر) يُرفض 1062 بنيويًّا —
 *      كان فحصًا تطبيقيًّا وحدَه قبل N-01.
 *
 * ومصفوفةُ التغطية الكاملة (كلُّ مسارٍ مالي وبرهانُه القائم) في:
 * docs/reports/N-01_IDEMPOTENCY_MATRIX.md
 *
 * البذرُ معزول (N1RST_<pid> · كيانات 97xxxx) ويُكنس في النهاية (§3).
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
// سياق مستأجر للبوابة (EMS_TENANT_GATE=enforce): الحزم تحاكي جلسة حقيقية لا GUEST
$_SESSION['user'] = array('id' => 1, 'role' => '19', 'company_id' => 4, 'name' => 'fes test');
require_once dirname(__DIR__) . '/app/Core/EventValidationException.php';
require_once dirname(__DIR__) . '/app/Core/ServerId.php';
require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/Finance/fin_helpers.php';

use App\Core\EventPublisher;
use App\Core\TenantContext;
use App\Core\TenantDb;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$CO = 4; $ACTOR = 1;
$MARK = 'N1RST_' . getmypid();
$ENT = 970000 + (getmypid() % 1000);

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE jl FROM fin_journal_lines jl JOIN fin_journal_entries je ON je.id=jl.entry_id
                   WHERE je.memo LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_journal_entries WHERE memo LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_event_links WHERE target_table='{$MARK}'");
    $conn->query("DELETE FROM fin_financial_events WHERE source_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM ems_business_events WHERE source_ref LIKE '{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ N-01 — إعادةُ الإرسال الفعلية على المسارات المالية ══\n");

// ═══ ① الدفتر المالي ═══
head('① EventPublisher::publish ×2 — الدفترُ لا يتكرر ولا أثرُه');
$ev = array(
    'event_key' => 'revenue.unit.recognized', 'category' => 'financial',
    'source_module' => 'sales', 'company_id' => $CO,
    'entity_type' => 'unit_entry', 'entity_id' => $ENT,
    'occurred_at' => '2026-07-16 09:00:00', 'created_by' => $ACTOR,
    'payload' => array('marker' => $MARK), 'legacy_event_type' => 'revenue',
    'amount' => 250.00, 'currency' => 'SDG', 'customer_entity_id' => 1,
    'source_ref' => $MARK . '_L1',
);
$r1 = EventPublisher::publish($conn, $ev);
$r2 = EventPublisher::publish($conn, $ev);
check(!$r1['duplicate'] && $r1['id'] > 0, "الأولى نُشرت #{$r1['id']}");
check($r2['duplicate'] === true && intval($r2['id']) === intval($r1['id']),
    'الثانية عادت duplicate=true بالمعرّف القائم نفسِه');
$c = $conn->query("SELECT COUNT(*) c FROM fin_financial_events WHERE source_ref='{$MARK}_L1'")->fetch_assoc();
check(intval($c['c']) === 1, 'DB: صفٌّ واحدٌ في الدفتر');
$c = $conn->query("SELECT COUNT(*) c FROM fin_event_effects WHERE event_id={$r1['id']}")->fetch_assoc();
check(intval($c['c']) === 1, 'DB: أثرٌ واحدٌ لا اثنان');
$c = $conn->query("SELECT COUNT(*) c FROM ems_business_events WHERE source_ref='{$MARK}_L1'")->fetch_assoc();
check(intval($c['c']) === 1, 'DB: حقيقةٌ واحدةٌ على الجذر');

// ═══ ② الجذر المحايد ═══
head('② EventPublisher::publishFact ×2 — الحقيقةُ لا تتكرر');
$fact = array(
    'event_key' => 'request.submitted', 'category' => 'operational',
    'source_module' => 'finance', 'company_id' => $CO,
    'entity_type' => 'fin_request', 'entity_id' => $ENT,
    'occurred_at' => '2026-07-16 09:05:00', 'created_by' => $ACTOR,
    'payload' => array('marker' => $MARK), 'source_ref' => $MARK . '_F1',
);
$f1 = EventPublisher::publishFact($conn, $fact);
$f2 = EventPublisher::publishFact($conn, $fact);
check($f1 !== null && $f1['id'] > 0, "الحقيقة الأولى #{$f1['id']}");
check($f2 !== null && intval($f2['id']) === intval($f1['id']), 'الإعادة ترجع معرّفَ الحقيقة القائمة');
$c = $conn->query("SELECT COUNT(*) c FROM ems_business_events WHERE source_ref='{$MARK}_F1'")->fetch_assoc();
check(intval($c['c']) === 1, 'DB: صفٌّ واحدٌ على الجذر');

// ═══ ③ القيد الآلي ═══
head('③ fin_auto_journal ×2 — القيدُ لا يتضاعف بإعادة الإرسال');
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));
fin_gate_override($gate);
$evRow = $conn->query("SELECT * FROM fin_financial_events WHERE source_ref='{$MARK}_L1'")->fetch_assoc();
$evRow['event_no'] = $MARK . '-EV';
// نثبّت هوية الاختبار في البيان عبر الحدث (الدالة تبني البيان من event_no)
$j1 = fin_auto_journal($conn, $CO, $evRow, $ACTOR);
$j2 = fin_auto_journal($conn, $CO, $evRow, $ACTOR);
check($j1 > 0, "القيد الأول #{$j1}");
check($j2 === $j1, "الإعادة ترجع القيدَ نفسَه: #{$j2}");
$c = $conn->query("SELECT COUNT(*) c FROM fin_journal_entries
    WHERE event_id=" . intval($evRow['id']) . " AND COALESCE(is_deleted,0)=0")->fetch_assoc();
check(intval($c['c']) === 1, 'DB: قيدٌ حيٌّ واحدٌ للحدث');
fin_gate_override(null);

// ═══ ④ دفتر عطالة المروحة ═══
head('④ fin_event_links — المفتاحُ الفريد الجديد يرفض التكرار بنيويًّا');
$okq = $conn->query("INSERT INTO fin_event_links
    (company_id, parent_kind, parent_ref, effect_type, target_table, target_id)
    VALUES ({$CO}, 'unit_record', {$ENT}, 'revenue_event', '{$MARK}', 1)");
check($okq === true, 'الرابط الأول قُبل');
$okq = $conn->query("INSERT INTO fin_event_links
    (company_id, parent_kind, parent_ref, effect_type, target_table, target_id)
    VALUES ({$CO}, 'unit_record', {$ENT}, 'revenue_event', '{$MARK}', 2)");
check($okq === false && $conn->errno === 1062,
    'تكرارُ (أب × نوع أثر) يُرفض 1062 — كان فحصًا تطبيقيًّا وحدَه: ' . $conn->errno);
$okq = $conn->query("INSERT INTO fin_event_links
    (company_id, parent_kind, parent_ref, effect_type, target_table, target_id)
    VALUES ({$CO}, 'unit_record', {$ENT}, 'supplier_due', '{$MARK}', 3)");
check($okq === true, 'ونوعُ أثرٍ آخرُ للأب نفسِه يمرّ — المفتاحُ على النوع لا الأب وحدَه');

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
