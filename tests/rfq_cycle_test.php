<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-21 — اختبار قبول: دورةُ عروض الموردين (UX-05 §2.1 · §8.2 · §8.3)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/rfq_cycle_test.php
 *
 * ما يُثبته (سيناريو §8.3 حرفيًّا):
 *   ① **بنودُ RFQ من الالتزامات اشتقاقًا لا إدخالًا** — و**عقدٌ بلا التزامات
 *      ⇒ 422**، ولا يُفتح طلبٌ فارغ.
 *   ② **عرضٌ بعد الإقفال ⇒ 423** (حالةً وتاريخًا).
 *   ③ **موردٌ يقرأ عرضَ غيره ⇒ 403 مسجَّلة** — ولا يُرجَع فارغًا صامتًا.
 *   ④ **ترسيةٌ جزئيةٌ 12k + 8k = 20k** · **ومحاولةُ 21k ⇒ 409 بقيمة المتاح**
 *      · و**`CHECK` يرفض تجاوزَ العدّاد بنيويًّا**.
 *   ⑤ **ولا تُرسى كميةٌ بلا عرضٍ مقدَّم**.
 *   ⑥ **ولا حدثَ ماليًّا**: الأحداثُ حقائقُ محايدةٌ في الجذر — **صفرُ صفٍّ في
 *      `fin_financial_events`**.
 *
 * البذرُ معزول: عقدٌ والتزامٌ 20000 طن ومورّدان — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '2', 'company_id' => 4, 'name' => 'H21 RFQ test');

require_once dirname(__DIR__) . '/app/Services/Procurement/RFQService.php';

use App\Services\Procurement\RFQService as RFQ;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999211;
$MARK  = 'H21T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM ems_business_events WHERE idempotency_key IN (
                    SELECT CONCAT('rfq_open:', r.id) FROM supplier_rfqs r WHERE r.rfq_no LIKE '%{$MARK}%'
                    UNION SELECT CONCAT('rfq_sent:', r.id) FROM supplier_rfqs r WHERE r.rfq_no LIKE '%{$MARK}%')");
    $conn->query("DELETE a FROM rfq_awards a JOIN supplier_rfqs r ON r.id = a.rfq_id
                   WHERE r.note LIKE '%{$MARK}%'");
    $conn->query("DELETE q FROM rfq_quotes q JOIN supplier_rfqs r ON r.id = q.rfq_id
                   WHERE r.note LIKE '%{$MARK}%'");
    $conn->query("DELETE l FROM rfq_lines l JOIN supplier_rfqs r ON r.id = l.rfq_id
                   WHERE r.note LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM supplier_rfqs WHERE note LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contract_commitments WHERE commitment_code LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM suppliers WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-21 — دورةُ عروض الموردين (RFQ) ══\n");

head('البذر — عقدٌ بالتزام 20,000 طن ومورّدان');

$CID = 990000 + (getmypid() % 9000);   // مرجعُ عقدٍ معزولٌ لا يصادم القائم
$conn->query("INSERT INTO contract_commitments (company_id, commitment_code, party_scope,
              contract_ref, commitment_type, unit_type, qty, period, obliged_party,
              shortfall_rule, surplus_rule, note, created_by, created_at, updated_at)
              VALUES ({$CO}, 'CMT-{$MARK}', 'client', {$CID}, 'total_qty', 'ton', 20000,
                      'contract', 'company', 'invoice_actual', 'same_price',
                      'نقلُ {$MARK}', 1, NOW(), NOW())");
if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); }

$mkSup = function ($n) use ($conn, $CO, $MARK) {
    $conn->query("INSERT INTO suppliers (company_id, name, phone, created_at)
                  VALUES ({$CO}, 'موردُ {$MARK}-{$n}', '0100000000', NOW())");
    return intval($conn->insert_id);
};
$S1 = $mkSup('الشرقية');
$S2 = $mkSup('الصحراء');
check($S1 > 0 && $S2 > 0, "التزامُ 20000 طن للعقد {$CID} · ومورّدان #{$S1} و#{$S2}");

// ═══ ① الاشتقاق ═══
head('① **بنودُ RFQ من الالتزامات اشتقاقًا لا إدخالًا**');

$r = RFQ::openFromContract($conn, $gate, $CO, 987654, '2084-12-31', $ACTOR, 'طلبُ ' . $MARK);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'عقدٌ بلا التزاماتٍ') !== false,
      '★★ عقدٌ بلا التزاماتٍ ⇒ **422** — «ولا يُفتح طلبٌ فارغ»');

$r = RFQ::openFromContract($conn, $gate, $CO, $CID, '2084-12-31', $ACTOR, 'طلبُ ' . $MARK);
check($r['ok'] && $r['lines'] === 1, '★ فُتح الطلبُ بـ**بندٍ واحدٍ مشتقّ**: ' . $r['reason']);
$RID = (int) $r['rfq_id'];
$conn->query("UPDATE supplier_rfqs SET note='طلبُ {$MARK}' WHERE id={$RID}");

$lines = RFQ::linesOf($gate, $RID);
$LID = (int) $lines[0]['id'];
check(abs((float) $lines[0]['qty_required'] - 20000.0) < 0.005
      && (string) $lines[0]['unit_type'] === 'ton'
      && mb_strpos((string) $lines[0]['description'], 'CMT-' . $MARK) !== false,
      '★★ والبندُ **20000 طن بمرجع التزامه** — لا كميةٌ كُتبت بيد');

// ═══ ② الإقفال يحكم ═══
head('② **عرضٌ بعد الإقفال ⇒ 423**');

$q = RFQ::submitQuote($conn, $gate, $CO, $LID, $S1, array('unit_price' => 4.75, 'qty_offered' => 12000), $ACTOR);
check(!$q['ok'] && $q['code'] === 423 && mb_strpos($q['reason'], 'لا عرضَ إلا على مرسَلٍ مفتوح') !== false,
      '★ عرضٌ على **مسودةٍ لم تُرسل** ⇒ **423**');

$s = RFQ::send($conn, $gate, $CO, $RID, $ACTOR);
check($s['ok'], 'أُرسل الطلبُ للمؤهلين');

$q1 = RFQ::submitQuote($conn, $gate, $CO, $LID, $S1,
    array('unit_price' => 4.75, 'qty_offered' => 12000, 'readiness_days' => 3), $ACTOR);
$q2 = RFQ::submitQuote($conn, $gate, $CO, $LID, $S2,
    array('unit_price' => 4.90, 'qty_offered' => 10000, 'readiness_days' => 0), $ACTOR);
check($q1['ok'] && $q2['ok'], 'وقُدّم عرضان (4.75 لـ12000 · و4.90 لـ10000)');

$q3 = RFQ::submitQuote($conn, $gate, $CO, $LID, $S1,
    array('unit_price' => 4.60, 'qty_offered' => 12000, 'readiness_days' => 2), $ACTOR);
check($q3['ok'] && (int) $q3['quote_id'] === (int) $q1['quote_id'],
      '★ وإعادةُ التقديم **استبدالٌ لا تكديس** — العرضُ نفسُه بسعرٍ محدَّث');
$n = intval($conn->query("SELECT COUNT(*) c FROM rfq_quotes WHERE line_id={$LID}")->fetch_assoc()['c']);
check($n === 2, 'وعرضان اثنان لا ثلاثة');

$conn->query("UPDATE supplier_rfqs SET due_date='2020-01-01' WHERE id={$RID}");
$qLate = RFQ::submitQuote($conn, $gate, $CO, $LID, $S2, array('unit_price' => 4.0, 'qty_offered' => 5000), $ACTOR);
check(!$qLate['ok'] && $qLate['code'] === 423 && mb_strpos($qLate['reason'], 'بعد الإقفال') !== false,
      '★★ وعرضٌ **بعد موعد الإقفال** ⇒ **423** ولو كان الطلبُ مرسَلًا');
$conn->query("UPDATE supplier_rfqs SET due_date='2084-12-31' WHERE id={$RID}");

// ═══ ③ العزلُ متبادل ═══
head('③ **موردٌ يقرأ عرضَ غيره ⇒ 403 مسجَّلة**');

$mine = RFQ::quotesForSupplier($gate, $RID, $S1);
check($mine['ok'] && count($mine['rows']) === 1 && (int) $mine['rows'][0]['supplier_id'] === $S1,
      'المورد #' . $S1 . ' يقرأ **عرضَه وحدَه**');
$cross = RFQ::quotesForSupplier($gate, $RID, $S1, $S2);
check(!$cross['ok'] && $cross['code'] === 403 && mb_strpos($cross['reason'], 'لا يقرأ موردٌ عرضَ غيره') !== false,
      '★★ ومحاولةُ قراءة عرض الآخر ⇒ **403** — **لا فراغٌ صامت**');

$cmp = RFQ::comparison($gate, $LID);
check(count($cmp) === 2 && $cmp[0]['is_cheapest'] === true,
      '★ وجدولُ المقارنة **لمدير الموردين** يرتّب بالمعايير الثلاثة ويسم الأرخص');

// ═══ ④ الترسيةُ الجزئية ═══
head('④ **ترسيةٌ جزئيةٌ 12k + 8k = 20k** — ومحاولةُ 21k ⇒ 409');

$aw = RFQ::award($conn, $gate, $CO, $RID, array(array('line_id' => $LID, 'supplier_id' => $S1, 'qty' => 12000)), $ACTOR);
check(!$aw['ok'] && $aw['code'] === 423, '★ الترسيةُ **قبل الإقفال** ⇒ **423**');

$cl = RFQ::close($conn, $gate, $CO, $RID, $ACTOR);
check($cl['ok'], 'أُقفل الطلب');

$aw = RFQ::award($conn, $gate, $CO, $RID, array(
    array('line_id' => $LID, 'supplier_id' => $S1, 'qty' => 12000),
    array('line_id' => $LID, 'supplier_id' => $S2, 'qty' => 9000),
), $ACTOR);
check(!$aw['ok'] && $aw['code'] === 409 && mb_strpos($aw['reason'], 'والمتاحُ 20000') !== false,
      '★★ 12k + 9k = **21k ⇒ 409 بقيمة المتاح 20000** — والفحصُ **قبل أي كتابة**');
$n = intval($conn->query("SELECT COUNT(*) c FROM rfq_awards WHERE line_id={$LID}")->fetch_assoc()['c']);
check($n === 0, 'وصفرُ ترسيةٍ كُتبت في المحاولة المرفوضة');

$aw = RFQ::award($conn, $gate, $CO, $RID, array(
    array('line_id' => $LID, 'supplier_id' => $S1, 'qty' => 12000),
    array('line_id' => $LID, 'supplier_id' => $S2, 'qty' => 8000),
), $ACTOR);
check($aw['ok'] && $aw['awarded'] === 2, '★★ و**12k + 8k = 20k** ترسيةً جزئيةً بموردين: ' . $aw['reason']);

$l = RFQ::lineOf($gate, $LID);
check(abs((float) $l['qty_awarded'] - 20000.0) < 0.005,
      '★ وعدّادُ البند **20000 = المطلوب بالضبط**');

$bad = $conn->query("UPDATE rfq_lines SET qty_awarded=25000 WHERE id={$LID}");
$l2 = RFQ::lineOf($gate, $LID);
check(abs((float) $l2['qty_awarded'] - 20000.0) < 0.005,
      '★★ وكتابةُ عدّادٍ يجاوز المطلوبَ **يرفضها `CHECK`** — «Σ ≤ الالتزام» بنيويًّا');

// ═══ ⑤ لا ترسيةَ بلا عرض ═══
head('⑤ **ولا تُرسى كميةٌ بلا عرضٍ مقدَّم**');
$S3 = $mkSup('بلا-عرض');
$conn->query("UPDATE rfq_lines SET qty_awarded=19000 WHERE id={$LID}");
$aw = RFQ::award($conn, $gate, $CO, $RID, array(
    array('line_id' => $LID, 'supplier_id' => $S3, 'qty' => 1000)), $ACTOR);
check(!$aw['ok'] && mb_strpos($aw['reason'], 'لا عرضَ لهذا المورد') !== false,
      '★★ موردٌ بلا عرضٍ ⇒ **رفضٌ بسببه** — ولا يُخترع له سعر');
$conn->query("UPDATE rfq_lines SET qty_awarded=20000 WHERE id={$LID}");

// ═══ ⑥ ولا حدثَ ماليًّا ═══
head('⑥ **ولا حدثَ ماليًّا قبل التنفيذ الفعلي**');
$fin = intval($conn->query("SELECT COUNT(*) c FROM fin_financial_events
                             WHERE entity_type='supplier_rfq' AND entity_id={$RID}")->fetch_assoc()['c']);
$root = intval($conn->query("SELECT COUNT(*) c FROM ems_business_events
                              WHERE entity_type='supplier_rfq' AND entity_id={$RID}")->fetch_assoc()['c']);
check($fin === 0 && $root >= 2,
      '★★ **صفرُ حدثٍ ماليّ** و' . $root . ' حقيقةً محايدةً في الجذر — «FES يبدأ من الوحدات»');

$mc = RFQ::markContracted($conn, $gate, $CO, $RID, $ACTOR);
check($mc['ok'], 'والانتقالُ Awarded → Contracted يقع');
$st = $conn->query("SELECT state FROM supplier_rfqs WHERE id={$RID}")->fetch_assoc();
check((string) $st['state'] === 'contracted', 'والحالةُ `contracted`');

// ═══ العزل ═══
head('العزلُ محفوظ');
$_SESSION['user']['company_id'] = 1;
$otherGate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::fromSession());
$leak = $otherGate->selectOne('supplier_rfqs', array('where' => array('id' => $RID)));
check($leak === null, '★ طلبُ شركةٍ لا يُقرأ من نطاقٍ آخر — صفرُ تسريب');
$_SESSION['user']['company_id'] = $CO;

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
