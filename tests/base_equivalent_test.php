<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-40 — اختبار قبول المعادل الموحّد (FES §3.3)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/base_equivalent_test.php
 *
 * ما يُثبته:
 *   ① صفرُ حركةٍ بلا معادل: الدفترُ (كان 55) · الذممُ (كانت 6) · الدفعاتُ
 *      (كانت بلا عمودٍ أصلًا) · القيودُ (كانت 9 تنتظر السعر) · الآثار.
 *   ② القيدُ البنيوي: معادلٌ لا يساوي ROUND(amount×fx) يُرفض 3819.
 *   ③ الناشرُ يحسب الثلاثية عند النشر (سعرُ SDG النافذ 0.000185) — رأسًا وأثرًا.
 *   ④ إعادةُ التقييم الدورية: فرقُ سعرٍ على رصيدٍ مفتوحٍ يُكشف بتقريره،
 *      وبلا حساب «فروق عملة» يُعلَن skipped_no_account (لا يُخترع حساب)،
 *      وبوجوده يولَّد قيدُ فرقٍ مسودةٌ متوازنٌ **بلا مساس الأصل**.
 *
 * البذرُ معزول (M40T_<pid> · عملة TSX تجريبية) ويُكنس كلُّه (§3).
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/_guard_env.php';   // ems_test_is_check_violation: رقمُ القيدِ بالمحرِّك
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
require_once dirname(__DIR__) . '/includes/fx.php';
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
$MARK = 'M40T_' . getmypid();
$ENT = 950000 + (getmypid() % 1000);

$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));
fin_gate_override($gate);

$teardown = function () use ($conn, $MARK, $CO) {
    $conn->query("DELETE fe FROM fin_event_effects fe JOIN fin_financial_events e ON e.id=fe.event_id
                   WHERE e.source_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM fin_financial_events WHERE source_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM ems_business_events WHERE source_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM fin_dues WHERE currency='TSX' AND company_id={$CO}");
    $conn->query("DELETE FROM fin_fx_rates WHERE currency_code='TSX' AND company_id={$CO}");
    $conn->query("DELETE FROM fin_currencies WHERE code='TSX' AND company_id={$CO}");
    $conn->query("DELETE jl FROM fin_journal_lines jl JOIN fin_journal_entries je ON je.id=jl.entry_id
                   WHERE je.memo LIKE '%إعادةُ تقييمٍ دورية%' AND je.state='draft' AND je.created_by={$CO}999");
    $conn->query("DELETE FROM fin_journal_entries WHERE memo LIKE '%إعادةُ تقييمٍ دورية%' AND state='draft' AND created_by={$CO}999");
    $conn->query("DELETE FROM fin_chart_of_accounts WHERE code='5900' AND company_id={$CO} AND name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-40 — المعادلُ الموحّد ══\n");

// ═══ ① التغطية ═══
head('① صفرُ حركةٍ بلا معادل — بعد التعبئة الرجعية');
$m = $conn->query("SELECT
    (SELECT COUNT(*) FROM fin_financial_events WHERE COALESCE(is_deleted,0)=0 AND fx_rate IS NULL) ev,
    (SELECT COUNT(*) FROM fin_financial_events WHERE COALESCE(is_deleted,0)=0 AND base_amount IS NULL) evb,
    (SELECT COUNT(*) FROM fin_dues WHERE fx_rate IS NULL) dues,
    (SELECT COUNT(*) FROM fin_payments WHERE fx_rate IS NULL) pays,
    (SELECT COUNT(*) FROM fin_journal_entries WHERE COALESCE(is_deleted,0)=0 AND fx_rate IS NULL) jrn,
    (SELECT COUNT(*) FROM fin_event_effects fe JOIN fin_financial_events e ON e.id=fe.event_id
      WHERE fe.base_amount IS NULL AND e.fx_rate IS NOT NULL) eff")->fetch_assoc();
check(intval($m['ev']) === 0, 'الدفتر: صفرٌ بلا سعر (كان 55)');
check(intval($m['evb']) === 0, 'الدفتر: صفرٌ بلا معادل');
check(intval($m['dues']) === 0, 'الذمم: صفرٌ بلا سعر (كانت 6)');
check(intval($m['pays']) === 0, 'الدفعات: صفرٌ بلا سعر');
check(intval($m['jrn']) === 0, 'القيود: صفرٌ بلا سعر (كانت 9 تنتظر SDG→USD)');
check(intval($m['eff']) === 0, 'الآثار: معادلُ كلِّ أثرٍ من سعر رأسه');

// عيّنةُ صحة: قيدُ 110,400 SDG معادلُه 20.42 دولارًا (× 0.000185)
$s = $conn->query("SELECT fx_rate, base_amount FROM fin_journal_entries WHERE entry_no='FIN-JV-0009'")->fetch_assoc();
check($s && abs((float) $s['base_amount'] - round(110400 * (float) $s['fx_rate'], 2)) < 0.01,
    'عيّنة: FIN-JV-0009 معادلُه = ROUND(110400×' . $s['fx_rate'] . ') = ' . $s['base_amount']);

// ═══ ② القيد البنيوي ═══
head('② معادلٌ مخالفٌ للمعادلة يُرفض بنيويًّا');
$r1 = EventPublisher::publish($conn, array(
    'event_key' => 'revenue.unit.recognized', 'category' => 'financial',
    'source_module' => 'sales', 'company_id' => $CO,
    'entity_type' => 'unit_entry', 'entity_id' => $ENT,
    'occurred_at' => date('Y-m-d') . ' 09:00:00', 'created_by' => $ACTOR,
    'payload' => array('marker' => $MARK), 'legacy_event_type' => 'revenue',
    'amount' => 1000.00, 'currency' => 'SDG', 'customer_entity_id' => 1,
    'source_ref' => $MARK . '_1',
));
check($r1['id'] > 0, "نُشر حدثُ الاختبار #{$r1['id']}");
$okq = $conn->query("UPDATE fin_financial_events SET base_amount = 999999.99 WHERE id = " . intval($r1['id']));
/* رقمُ خرقِ `CHECK` **يختلف بالمحرِّك**: 3819 في MySQL و4025 في MariaDB — وكان
   الشرطُ 3819 حرفيًّا على مارياDB فيرسب **والقيدُ يمنع فعلًا**. (`_guard_env.php`) */
check($okq === false && ems_test_is_check_violation($conn->errno),
    'تحريفُ المعادل عن ROUND(amount×fx) يُرفض CHECK: '
    . ems_test_check_errno_label($conn->errno));

// ═══ ③ الناشر ═══
head('③ الناشرُ يحسب الثلاثية عند النشر');
$row = $conn->query("SELECT amount, fx_rate, base_amount FROM fin_financial_events WHERE id=" . intval($r1['id']))->fetch_assoc();
check($row['fx_rate'] !== null && abs((float) $row['fx_rate'] - 0.000185) < 0.0000001,
    'السعرُ النافذ التُقط: ' . $row['fx_rate']);
check((float) $row['base_amount'] === round(1000 * (float) $row['fx_rate'], 2),
    'والمعادلُ محسوبٌ: ' . $row['base_amount']);
$eff = $conn->query("SELECT base_amount FROM fin_event_effects WHERE event_id=" . intval($r1['id']))->fetch_assoc();
check($eff && $eff['base_amount'] !== null && (float) $eff['base_amount'] === (float) $row['base_amount'],
    'وأثرُه يحمل معادلَه: ' . $eff['base_amount']);

// ═══ ④ إعادة التقييم ═══
head('④ إعادةُ التقييم الدورية — قيدُ فرقٍ بمرجعه ولا مساسَ بالأصل');
// عملةُ اختبارٍ TSX مسجَّلةٌ أولًا (حارسُ F-04 البنيوي: fin_dues.currency FK
// إلى سجل العملات — عملةٌ غيرُ مسجَّلةٍ تُرفض 1452 بحق)
$conn->query("INSERT INTO fin_currencies (company_id, code, name_ar, decimals, is_base, active, sort_order)
              VALUES ({$CO}, 'TSX', 'عملة اختبار M40', 2, 0, 1, 99)");
// سعرُ التثبيت 2.0 (قبل عشرة أيام) وسعرُ اليوم 3.0 — التواريخُ من PHP حصرًا
// (گوتشا مقيسة: CURDATE() في MySQL قد يسبق date() في PHP بمنطقةٍ زمنية)
$d10 = date('Y-m-d', strtotime('-10 days'));
$d0  = date('Y-m-d');
$conn->query("INSERT INTO fin_fx_rates (company_id, currency_code, rate_to_base, effective_from, created_by)
              VALUES ({$CO}, 'TSX', 2.0, '{$d10}', {$ACTOR})");
$conn->query("INSERT INTO fin_fx_rates (company_id, currency_code, rate_to_base, effective_from, created_by)
              VALUES ({$CO}, 'TSX', 3.0, '{$d0}', {$ACTOR})");
// رصيدٌ مفتوح: استحقاقٌ (credit) 100 TSX ثُبّت بمعادل 200
// الطرفُ يُستنسخ من ذمّةٍ قائمة (مفاتيحُ fin_dues الأجنبية ترفض طرفًا وهميًّا)
$tpl = $conn->query("SELECT party_type, party_ref, due_type FROM fin_dues
    WHERE company_id={$CO} AND direction='credit' LIMIT 1")->fetch_assoc();
if (!$tpl) { $tpl = $conn->query("SELECT party_type, party_ref, due_type FROM fin_dues LIMIT 1")->fetch_assoc(); }
$okq = $conn->query("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
        amount, currency, fx_rate, base_amount, created_by)
    VALUES ({$CO}, '" . $conn->real_escape_string($tpl['party_type']) . "', '"
          . $conn->real_escape_string($tpl['party_ref']) . "', '"
          . $conn->real_escape_string($tpl['due_type']) . "', 'credit',
            100.00, 'TSX', 2.0, 200.00, {$ACTOR})");
check($okq === true, 'بُذر رصيدٌ مفتوحٌ 100 TSX بمعادلٍ مثبَّت 200 (خطأ: ' . $conn->errno . ')');

$rv = ems_fx_revalue_open_dues($conn, $CO, date('Y-m-d'), intval($CO . '999'));
check(!empty($rv['diffs']), 'الفرقُ مكشوفٌ بتقريره: ' . count($rv['diffs']) . ' رصيد');
$d0 = $rv['diffs'][0] ?? array();
check(($d0['new_base'] ?? 0) == 300.0 && ($d0['diff'] ?? 0) == 100.0,
    'معادلُ اليوم 300 والفرقُ +100');
check($rv['status'] === 'skipped_no_account' && strpos($rv['reason'], '5900') !== false,
    'بلا حساب «فروق عملة»: يُعلَن ولا يُخترع — ' . $rv['status']);

// بحساب 5900 يولَّد القيد
$conn->query("INSERT INTO fin_chart_of_accounts (company_id, code, name, account_type, is_postable, active, created_by)
              VALUES ({$CO}, '5900', 'فروق عملة {$MARK}', 'expense', 1, 1, {$ACTOR})");
$rv2 = ems_fx_revalue_open_dues($conn, $CO, date('Y-m-d'), intval($CO . '999'));
check($rv2['status'] === 'entry_created' && intval($rv2['journal_id']) > 0,
    'قيدُ الفرق وُلد: #' . $rv2['journal_id'] . ' — ' . mb_substr($rv2['reason'], 0, 60));
$j = $conn->query("SELECT total_debit, total_credit, state FROM fin_journal_entries WHERE id=" . intval($rv2['journal_id']))->fetch_assoc();
check($j && (float) $j['total_debit'] === 100.0 && $j['state'] === 'draft', 'متوازنٌ 100 مسودةً — للمراجعة قبل الترحيل');
$due = $conn->query("SELECT fx_rate, base_amount FROM fin_dues WHERE currency='TSX' AND company_id={$CO}")->fetch_assoc();
check((float) $due['fx_rate'] === 2.0 && (float) $due['base_amount'] === 200.0,
    '★ الأصلُ لم يُمسّ: سعرُه المثبَّت ومعادلُه كما كانا (التصحيحُ بقيدٍ لا بتعديل)');

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
