<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-38 — اختبار قبول أعمدة دفتر القيود (UX-02 §15.2-أ · SPEC-01 #13)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/journal_head_columns_test.php
 * رمز الخروج: 0 = أخضر · 1 = فشل.
 *
 * ما يُثبته:
 *   ① الأعمدةُ السبعة قائمةٌ على الرأس بأنواعها + cost_center_id FK على السطور.
 *   ② التعبئةُ الرجعية: صفرُ صفٍّ بلا txn_date وصفرُ صفٍّ بلا currency —
 *      وfx_rate/base_amount NULL **معلَنان** (سعرُ SDG→USD غيرُ مُدخَل).
 *   ③ **المسارُ المرفوض بنيويًّا** (والفحصُ على المُرجَع لا الاستثناء —
 *      config.php يضبط mysqli على عدم الرمي):
 *      قيدٌ غيرُ متوازنٍ يُرفض (ck_je_balanced) · معادلٌ بلا سعرٍ أو بحسابٍ
 *      خاطئٍ يُرفض (ck_je_fx_pair) · مركزُ تكلفةٍ لا وجودَ له يُرفض (FK).
 *   ④ المسارُ السليم: القيدُ الآلي fin_auto_journal يحمل أعمدتَه السبعةَ
 *      ومركزَ سطره من الدليل (تطابقُ اسم المشروع).
 *
 * البذرُ معزول: كياناتٌ بعلامة JHCT_<pid> ويُكنس أثرُها في النهاية (أمر §3).
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
// سياق مستأجر للبوابة (EMS_TENANT_GATE=enforce): الحزم تحاكي جلسة حقيقية لا GUEST
$_SESSION['user'] = array('id' => 1, 'role' => '19', 'company_id' => 4, 'name' => 'fes test');
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/Finance/fin_helpers.php';

use App\Core\TenantContext;
use App\Core\TenantDb;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$CO = 4; $ACTOR = 1;
$MARK = 'JHCT_' . getmypid();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE jl FROM fin_journal_lines jl
                    JOIN fin_journal_entries je ON je.id = jl.entry_id
                   WHERE je.memo LIKE '%{$MARK}%' OR je.entry_no LIKE '{$MARK}%'");
    $conn->query("DELETE FROM fin_journal_entries WHERE memo LIKE '%{$MARK}%' OR entry_no LIKE '{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-38 — أعمدةُ دفتر القيود ══\n");

// ═══ ① البنية ═══
head('① الأعمدةُ السبعة + FK المركز');
$cols = array();
$q = $conn->query("SHOW COLUMNS FROM fin_journal_entries");
while ($r = $q->fetch_assoc()) { $cols[$r['Field']] = $r; }
foreach (array('txn_date', 'request_no', 'request_owner', 'request_group', 'currency', 'fx_rate', 'base_amount') as $c) {
    check(isset($cols[$c]), "العمود {$c} قائم");
}
check(isset($cols['txn_date']) && $cols['txn_date']['Null'] === 'NO', 'txn_date مُلزَم (بعد التعبئة)');
check(isset($cols['currency']) && $cols['currency']['Null'] === 'NO', 'currency مُلزَم (بعد التعبئة)');
check(isset($cols['fx_rate']) && $cols['fx_rate']['Null'] === 'YES', 'fx_rate يقبل NULL — الغيابُ معلَنٌ لا مخترَع');

$lcols = array();
$q = $conn->query("SHOW COLUMNS FROM fin_journal_lines");
while ($r = $q->fetch_assoc()) { $lcols[$r['Field']] = $r; }
check(isset($lcols['cost_center_id']), 'cost_center_id قائم على السطور');
check(isset($lcols['cost_center']), 'والنصُّ الحرُّ القديم باقٍ في الجدول (يُسقَط من النماذج لا من البنية)');
$fk = $conn->query("SELECT COUNT(*) c FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_journal_lines'
      AND COLUMN_NAME='cost_center_id' AND REFERENCED_TABLE_NAME='fin_cost_centers'")->fetch_assoc();
check(intval($fk['c']) === 1, 'FK → fin_cost_centers مربوط');

// ═══ ② التعبئة الرجعية ═══
head('② التعبئةُ الرجعية — صفرُ فجوةٍ صامتة');
$r = $conn->query("SELECT COUNT(*) c FROM fin_journal_entries WHERE txn_date IS NULL")->fetch_assoc();
check(intval($r['c']) === 0, 'صفرُ صفٍّ بلا txn_date');
$r = $conn->query("SELECT COUNT(*) c FROM fin_journal_entries WHERE currency IS NULL OR currency=''")->fetch_assoc();
check(intval($r['c']) === 0, 'صفرُ صفٍّ بلا currency');
$r = $conn->query("SELECT COUNT(*) c FROM fin_journal_entries
    WHERE (fx_rate IS NULL) <> (base_amount IS NULL)")->fetch_assoc();
check(intval($r['c']) === 0, 'تزاوجُ fx/base محفوظ: لا معادلَ بلا سعرٍ ولا سعرَ بلا معادل');

// ═══ ③ المسار المرفوض — القيود البنيوية بمحاولة خرق فعلية ═══
head('③ المرفوضُ بنيويًّا — الفحصُ على المُرجَع (mysqli لا يرمي)');
$no1 = $MARK . '-UNBAL';
$okq = $conn->query("INSERT INTO fin_journal_entries
    (company_id, entry_no, posting_date, txn_date, currency, total_debit, total_credit, memo, state)
    VALUES ({$CO}, '{$no1}', CURDATE(), CURDATE(), 'SDG', 100.00, 90.00, '{$MARK} غير متوازن', 'draft')");
check($okq === false, 'قيدٌ غيرُ متوازنٍ (100≠90) يُرفض بنيويًّا: ' . $conn->errno);

$no2 = $MARK . '-FXBAD';
$okq = $conn->query("INSERT INTO fin_journal_entries
    (company_id, entry_no, posting_date, txn_date, currency, fx_rate, base_amount, total_debit, total_credit, memo, state)
    VALUES ({$CO}, '{$no2}', CURDATE(), CURDATE(), 'SDG', 2.0, 999.99, 100.00, 100.00, '{$MARK} معادل خاطئ', 'draft')");
check($okq === false, 'معادلٌ لا يساوي ROUND(المبلغ×السعر) يُرفض: ' . $conn->errno);

$no3 = $MARK . '-FXORPH';
$okq = $conn->query("INSERT INTO fin_journal_entries
    (company_id, entry_no, posting_date, txn_date, currency, base_amount, total_debit, total_credit, memo, state)
    VALUES ({$CO}, '{$no3}', CURDATE(), CURDATE(), 'SDG', 200.00, 100.00, 100.00, '{$MARK} معادل يتيم', 'draft')");
check($okq === false, 'معادلٌ بلا سعرِ صرفٍ يُرفض (لا رقمَ من عدم): ' . $conn->errno);

// قيدٌ سليمٌ يقبله ck ثم سطرٌ بمركزٍ وهمي يرفضه FK
$no4 = $MARK . '-OKHEAD';
$okq = $conn->query("INSERT INTO fin_journal_entries
    (company_id, entry_no, posting_date, txn_date, currency, fx_rate, base_amount, total_debit, total_credit, memo, state)
    VALUES ({$CO}, '{$no4}', CURDATE(), CURDATE(), 'SDG', 2.0, 200.00, 100.00, 100.00, '{$MARK} سليم', 'draft')");
check($okq === true, 'والقيدُ المتوازنُ بثلاثيةٍ متسقة يُقبل');
$headId = intval($conn->insert_id);

$okq = $conn->query("INSERT INTO fin_journal_lines
    (company_id, entry_id, account_id, debit, credit, cost_center_id, memo)
    SELECT {$CO}, {$headId}, id, 100.00, 0, 999999, '{$MARK} مركز وهمي'
      FROM fin_chart_of_accounts WHERE company_id={$CO} AND is_postable=1 LIMIT 1");
check($okq === false, 'مركزُ تكلفةٍ لا وجودَ له في الدليل يُرفض FK: ' . $conn->errno);

// ═══ ④ المسار السليم — القيد الآلي يحمل أعمدته ═══
head('④ القيدُ الآلي fin_auto_journal يستنتج حقولَه (SPEC-01 #13)');
$ctx = TenantContext::forSystem($CO, $ACTOR, '', true);
$gate = new TenantDb($conn, $ctx);
fin_gate_override($gate);

$event = array(
    'id' => 999901, 'event_no' => $MARK . '-EV', 'event_type' => 'revenue',
    'amount' => 150.00, 'currency' => 'SDG', 'fx_rate' => null,
    'source_module' => 'projects', 'project_id' => 1, 'equipment_id' => null,
    'supplier_entity_id' => null, 'occurred_at' => '2026-07-15 09:00:00',
);
$jid = fin_auto_journal($conn, $CO, $event, $ACTOR);
check($jid > 0, "القيدُ الآلي وُلد: #{$jid}");
if ($jid > 0) {
    // وسمُ الصف للكنس (entry_no مولَّدٌ تسلسليًّا لا يحمل العلامة — لكن memo يحملها)
    $je = $conn->query("SELECT * FROM fin_journal_entries WHERE id={$jid}")->fetch_assoc();
    check($je['txn_date'] === '2026-07-15', "txn_date من وقوع الحدث لا من يوم التوليد: {$je['txn_date']}");
    check($je['currency'] === 'SDG', 'العملة محمولة: SDG');
    check(($je['fx_rate'] === null) === ($je['base_amount'] === null),
        'الثلاثية متزاوجة (السعرُ إن غاب غاب معادلُه معلَنًا)');
    $jl = $conn->query("SELECT cost_center_id FROM fin_journal_lines WHERE entry_id={$jid} LIMIT 1")->fetch_assoc();
    check($jl && intval($jl['cost_center_id']) > 0,
        'مركزُ التكلفة اشتُقّ من الدليل بتطابق اسم المشروع #1: cc=' . ($jl['cost_center_id'] ?? '—'));
    // كنسٌ فوري لهذا القيد (لم يوسم entry_no)
    $conn->query("DELETE FROM fin_journal_lines WHERE entry_id={$jid}");
    $conn->query("DELETE FROM fin_journal_entries WHERE id={$jid}");
}
fin_gate_override(null);

// ═══ التعايش ═══
head('التعايش — القيودُ التسعةُ التاريخية لم تُمسّ قيمًا');
$r = $conn->query("SELECT COUNT(*) c FROM fin_journal_entries
    WHERE id BETWEEN 23 AND 31 AND state='posted'
      AND ROUND(total_debit,2)=ROUND(total_credit,2)")->fetch_assoc();
check(intval($r['c']) === 9, 'التسعةُ المرحَّلة قائمةٌ متوازنةٌ كما كانت: ' . $r['c']);

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
