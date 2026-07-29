<?php
/**
 * tests/procurement_expense_gate_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 * حارس «لا مصروفَ عن أمرِ شراءٍ لم تصل بضاعتُه» (UX-09 §2 · §5.1 · §8.2 · FES §7).
 *
 * كان زرُّ السحب في شاشة الاستيراد ينشر مصروفًا عن **أي** أمرٍ فيه مبلغ — بلا
 * فحصِ حالة. المقيس قبل البوابة: 286,700 من 417,150 (≈69٪) على أوامرَ لم تُستلم،
 * منها مسودتان لم تُعتمدا. الحرّاسُ الخمسة:
 *   ① البوابة: مسودة/مؤكَّد لا يولّدان أثرًا · استلامٌ نهائي يولّده
 *   ② الزناد: تسجيلُ الاستلام يقدّم الحالة وينشر — لا زرَّ سحب
 *   ③ العطالة: مفتاح (proc:order:{id}) واحدٌ للقناتين — لا ازدواج
 *   ④ الأبعاد: المشروع والمعادل الموحّد يصلان الدفتر (كانا مفقودين)
 *   ⑤ الحقول العشرون قائمةٌ بأنواعها
 *
 * يبذر أوامرَه ويكنسها — لا يمسّ أمرًا حقيقيًّا.
 * التشغيل: php tests/procurement_expense_gate_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';

while (ob_get_level() > 0) { ob_end_clean(); }   // config.php يفتح مخزنَ CSRF

// سياقُ المستأجر: `ems_tenant_db()` يُبنى من الجلسة **ويُخزَّن ساكنًا** — وCLI بلا
// جلسةٍ يجعل `scopedQuery` ترفض fail-closed («no tenant in context») بينما
// selectOne يمرّ، فتبدو الدالةُ معطوبةً وهي سليمةٌ في الطلب الحقيقي. تُهيَّأ
// الجلسةُ **قبل** أول استعمالٍ للبوابة فيصحّ القياس. (قِيس 2026-07-27.)
$_SESSION['user'] = array('id' => 1, 'role' => '16', 'company_id' => 4, 'name' => 'proc gate test');

require_once dirname(__DIR__) . '/Procurement/proc_helpers.php';

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$CO = 4; $ACTOR = 1;
$MARK = 'PGATE' . getmypid();

$scalar = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    $v = $r ? $r->fetch_row() : null;
    return $v ? $v[0] : 0;
};

// ═══ ⑤ الحقول العشرون ═════════════════════════════════════════════════════
head('⑤ الحقول العشرون على أمر الشراء');

$cols = array();
$r = $conn->query("SHOW COLUMNS FROM proc_order");
while ($x = $r->fetch_assoc()) { $cols[$x['Field']] = $x; }

$expected = array(
    'expected_delivery_date', 'sent_at', 'sent_by', 'late_alerted_at',
    'received_pct', 'first_receipt_at', 'final_receipt_at', 'closed_at', 'closed_by',
    'invoice_no', 'invoice_date', 'invoice_amount', 'match_state', 'matched_at', 'matched_by',
    'project_id', 'base_amount', 'tax_amount', 'due_date', 'event_id',
);
check(count($expected) === 20, 'القائمةُ عشرون حقلًا بالضبط');
$missing = array();
foreach ($expected as $c) { if (!isset($cols[$c])) { $missing[] = $c; } }
check(!$missing, 'العشرون كلُّها مضافةٌ فعلًا' . ($missing ? ' — الناقص: ' . implode('، ', $missing) : ''));
check(isset($cols['base_amount']) && stripos($cols['base_amount']['Type'], 'decimal(18,2)') !== false,
    'المعادلُ الموحّد Decimal(18,2) كما يوجب FES §3.3');
check(isset($cols['received_pct']) && $cols['received_pct']['Null'] === 'NO',
    'نسبةُ الاستلام غيرُ فارغةٍ (افتراضُها صفر — لا نسبةَ مجهولة)');
check(isset($cols['match_state']) && strtolower((string)$cols['match_state']['Default']) === 'unmatched',
    'حالةُ المطابقة تبدأ unmatched (§8.2)');

// ═══ ① البوابة ═════════════════════════════════════════════════════════════
head('① البوابة: لا مصروفَ قبل الاستلام');

check(proc_order_expense_states() === array('استلام نهائي', 'مطابَق', 'مغلق'),
    'حالاتُ المصروف ثلاثٌ: استلامٌ نهائي · مطابَق · مغلق');

$mkOrder = function ($state, $amount, $fx = 1, $currency = 'SDG', $project = null) use ($conn, $CO, $ACTOR, $MARK) {
    static $i = 0; $i++;
    $code = 'TST-' . $MARK . '-' . $i;
    $base = round($amount * $fx, 2);
    $st = $conn->prepare(
        "INSERT INTO proc_order (company_id, code, supplier_id, project_id, currency, fx_rate,
             total_amount, base_amount, state, created_by, created_at)
         VALUES (?, ?, 3, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $st->bind_param('isisdddsi', $CO, $code, $project, $currency, $fx, $amount, $base, $state, $ACTOR);
    $st->execute();
    $id = $conn->insert_id;
    $st->close();
    return array($id, $code);
};

$seeded = array();
$reg = function ($pair) use (&$seeded) { $seeded[] = $pair[0]; return $pair; };

list($draftId, $draftCode)   = $reg($mkOrder('مسودة', 1000.00));
list($confId,  $confCode)    = $reg($mkOrder('مؤكَّد', 2000.00));
list($partId,  $partCode)    = $reg($mkOrder('استلام أولي', 3000.00));
list($finalId, $finalCode)   = $reg($mkOrder('استلام نهائي', 4000.00, 1, 'SDG', 2));
list($fxId,    $fxCode)      = $reg($mkOrder('استلام نهائي', 100.00, 600, 'USD', 2));

$cleanup = function () use ($conn, &$seeded) {
    foreach ($seeded as $oid) {
        $conn->query("DELETE FROM fin_financial_events WHERE entity_type='proc_order' AND entity_id=" . intval($oid));
        $conn->query("DELETE FROM ems_business_events WHERE entity_type='proc_order' AND entity_id=" . intval($oid));
        $conn->query("DELETE FROM proc_order WHERE id=" . intval($oid));
    }
};
register_shutdown_function($cleanup);

$ledger0 = (int) $scalar("SELECT COUNT(*) FROM fin_financial_events");

check(proc_publish_order_cost($conn, $draftId, $ACTOR, 'test') === 'skipped',
    'مسودةٌ بمبلغ 1000 → لا أثرَ ماليًّا (كانت تُنشر قبل البوابة)');
check(proc_publish_order_cost($conn, $confId, $ACTOR, 'test') === 'skipped',
    'أمرٌ مؤكَّدٌ لم تصل بضاعتُه → لا أثر');
check(proc_publish_order_cost($conn, $partId, $ACTOR, 'test') === 'skipped',
    'استلامٌ أوليٌّ (جزئي) → لا أثرَ بعد — الاستحقاقُ باكتمال الوصول');
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events") === $ledger0,
    'صفر صفِّ دفترٍ من الثلاثة مجتمعةً');

check(proc_publish_order_cost($conn, $finalId, $ACTOR, 'test') === 'published',
    'استلامٌ نهائيٌّ بمبلغ 4000 → يُنشر أثرُه');

// ═══ ④ الأبعاد ════════════════════════════════════════════════════════════
head('④ الأبعاد تصل الدفتر');

$ev = $conn->query("SELECT * FROM fin_financial_events WHERE entity_type='proc_order' AND entity_id=" . intval($finalId))->fetch_assoc();
check($ev !== null, 'صفُّ الدفتر موجود');
if ($ev) {
    check(abs((float)$ev['amount'] - 4000.00) < 0.01, 'المبلغُ 4000 بحرفه');
    check(intval($ev['project_id']) === 2, '★ المشروعُ وصل الدفترَ (كان مفقودًا — لا عمودَ له أصلًا)');
    check((string)$ev['source_ref'] === $finalCode, 'المرجعُ كودُ الأمر');
    check(!empty($ev['root_event_id']), 'وللصفِّ جذرٌ في سجل الحقائق');
}

proc_publish_order_cost($conn, $fxId, $ACTOR, 'test');
$root = $conn->query("SELECT payload FROM ems_business_events WHERE entity_type='proc_order' AND entity_id=" . intval($fxId))->fetch_assoc();
$pl = $root ? json_decode((string)$root['payload'], true) : array();
check(is_array($pl) && isset($pl['base_amount']) && abs((float)$pl['base_amount'] - 60000.00) < 0.01,
    '★ المعادلُ الموحّد محسوبٌ ومحفوظ: 100 USD × 600 = 60,000 (FES §3.3)');
check(isset($pl['received_pct']) && isset($pl['state']),
    'والحمولةُ تحمل حالةَ الأمر ونسبةَ استلامه للتدقيق');

// ═══ ③ العطالة ════════════════════════════════════════════════════════════
head('③ العطالة: مفتاحٌ واحدٌ للقناتين');

$before = (int) $scalar("SELECT COUNT(*) FROM fin_financial_events");
check(proc_publish_order_cost($conn, $finalId, $ACTOR, 'receipt') === 'duplicate',
    'إعادةُ النشر من الاستلام → duplicate');
check(proc_publish_order_cost($conn, $finalId, $ACTOR, 'import_events_fin') === 'duplicate',
    'وزرُّ الاستيراد بعده → duplicate');
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events") === $before, 'صفر صفٍّ جديد');
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events WHERE entity_type='proc_order' AND entity_id=" . intval($finalId)) === 1,
    'صفٌّ واحدٌ للأمر بعد ثلاث محاولات');

$rootKey = $conn->query("SELECT idempotency_key FROM ems_business_events WHERE entity_type='proc_order' AND entity_id=" . intval($finalId))->fetch_assoc();
check($rootKey && (string)$rootKey['idempotency_key'] === 'proc:order:' . $finalId,
    'المفتاحُ هو مفتاحُ الاستيراد نفسُه (proc:order:{id}) — فلا تتضاعف القناتان');

check(!empty($conn->query("SELECT event_id FROM proc_order WHERE id=" . intval($finalId))->fetch_assoc()['event_id']),
    'ومرجعُ الحدث كُتب على الأمر (قراءةً بمرجعه §5.1-③)');

// ═══ ② الزناد ═════════════════════════════════════════════════════════════
head('② الزناد: الاستلام يقدّم الحالة وينشر');

// أمرٌ مؤكَّدٌ ببندٍ واحدٍ ثم استلامٌ كامل
list($trigId, $trigCode) = $reg($mkOrder('مؤكَّد', 5000.00, 1, 'SDG', 2));
$conn->query("INSERT INTO proc_order_line (company_id, order_id, item_name, qty, unit_price, subtotal, created_at)
              VALUES ({$CO}, {$trigId}, 'صنفٌ اختباري', 10, 500, 5000, NOW())");
$rcRes = $conn->query("INSERT INTO proc_receipt_custody (company_id, code, holder_name, receipt_date, supplier_id,
              order_id, state, created_by, created_at)
              VALUES ({$CO}, 'TST-RC-{$MARK}', 'مستلِمٌ اختباري', CURDATE(), 3, {$trigId}, 'مستلَمة', {$ACTOR}, NOW())");
$rcId = $conn->insert_id;
$conn->query("INSERT INTO proc_receipt_line (company_id, custody_id, item_name, qty, created_at)
              VALUES ({$CO}, {$rcId}, 'صنفٌ اختباري', 10, NOW())");

$sync = proc_sync_order_receipt($conn, $trigId, $ACTOR);
check(abs($sync['pct'] - 100.0) < 0.01, 'النسبةُ محسوبةٌ من الكميات: 10 من 10 = 100٪');
check($sync['state'] === 'استلام نهائي', 'الحالةُ تقدّمت تلقائيًّا إلى «استلام نهائي»');
check($sync['publish'] === 'published', '★ والأثرُ الماليُّ نُشر لحظتَها — بلا زرِّ سحب');
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events WHERE entity_type='proc_order' AND entity_id=" . intval($trigId)) === 1,
    'صفٌّ واحدٌ في الدفتر للأمر');

// استلامٌ جزئي: النسبةُ دون المئة فلا نشر
list($halfId, $halfCode) = $reg($mkOrder('مؤكَّد', 6000.00, 1, 'SDG', 2));
$conn->query("INSERT INTO proc_order_line (company_id, order_id, item_name, qty, unit_price, subtotal, created_at)
              VALUES ({$CO}, {$halfId}, 'صنفٌ نصفي', 10, 600, 6000, NOW())");
$conn->query("INSERT INTO proc_receipt_custody (company_id, code, holder_name, receipt_date, supplier_id,
              order_id, state, created_by, created_at)
              VALUES ({$CO}, 'TST-RCH-{$MARK}', 'مستلِمٌ اختباري', CURDATE(), 3, {$halfId}, 'مستلَمة', {$ACTOR}, NOW())");
$rcHalf = $conn->insert_id;
$conn->query("INSERT INTO proc_receipt_line (company_id, custody_id, item_name, qty, created_at)
              VALUES ({$CO}, {$rcHalf}, 'صنفٌ نصفي', 4, NOW())");

$sync2 = proc_sync_order_receipt($conn, $halfId, $ACTOR);
check(abs($sync2['pct'] - 40.0) < 0.01, 'استلامُ 4 من 10 → 40٪ (لا 100 بالجملة)');
check($sync2['state'] === 'استلام أولي', 'والحالةُ «استلام أولي» لا «نهائي»');
check($sync2['publish'] === 'none',
    '★ ولا أثرَ ماليًّا — البضاعةُ لم تكتمل');
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events WHERE entity_type='proc_order' AND entity_id=" . intval($halfId)) === 0,
    'صفر صفِّ دفترٍ للأمر الجزئي');

// النظافة
$conn->query("DELETE FROM proc_receipt_line WHERE custody_id IN ({$rcId}, {$rcHalf})");
$conn->query("DELETE FROM proc_receipt_custody WHERE id IN ({$rcId}, {$rcHalf})");
$conn->query("DELETE FROM proc_order_line WHERE order_id IN ({$trigId}, {$halfId})");
$cleanup();
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events") === $ledger0,
    'بعد الكنس: الدفترُ كما كان قبل الاختبار');

// ═══ الحالةُ الحيّة بعد التصحيح ═════════════════════════════════════════════
head('الحالة الحيّة بعد تصحيح المالك');

check((int) $scalar("SELECT COUNT(*) FROM proc_order po JOIN fin_financial_events fe
        ON fe.entity_type='proc_order' AND fe.entity_id=po.id
      WHERE po.state IN ('مسودة','مؤكَّد') AND COALESCE(po.is_deleted,0)=0") === 0,
    '★ صفر مصروفٍ في الدفتر على أمرٍ لم تصل بضاعتُه');
check((int) $scalar("SELECT COUNT(*) FROM proc_order WHERE base_amount IS NULL AND COALESCE(is_deleted,0)=0") === 0,
    'كلُّ أمرٍ يحمل معادلَه الموحّد');
check((int) $scalar("SELECT COUNT(*) FROM proc_order po
      WHERE po.state='استلام نهائي' AND COALESCE(po.is_deleted,0)=0
        AND NOT EXISTS (SELECT 1 FROM proc_receipt_custody rc
                          JOIN proc_receipt_line rl ON rl.custody_id=rc.id
                         WHERE rc.order_id=po.id AND COALESCE(rc.is_deleted,0)=0)") === 0,
    '★ لا أمرَ «مستلَمٌ نهائيًّا» بلا سجل استلامٍ يوثّقه');

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
