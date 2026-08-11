<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-05 — اختبار قبول: إحكامُ التحصيل (ENT-03 §4 · §7)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/collection_control_test.php
 *
 * ما يُثبته:
 *   ① **لا قبضَ بلا مرجع** → 422 خدمةً و**`CHECK`** بنيويًّا · و`legacy_no_ref`
 *      **وسمُ الموروث** يُعلَن ولا يُقبل لجديد.
 *   ② **ولا يُقبض مرتين**: (مرجع × مبلغ × يوم) → **409 بمرجع القائم**، و**UQ**
 *      يرفض الكتابةَ المباشرة.
 *   ③ **أقدمُ فاتورةٍ أولًا**: دفعةٌ تغطي الأقدمَ كاملةً وتفيض على التالية —
 *      **وكلُّ تخصيصٍ سطرٌ بأساسه** لا عمودٌ واحدٌ يُخمَّن.
 *   ④ **مرجعٌ صريحٌ يتقدّم على الأقدمية** — «ما لم يحدد العميلُ مرجعًا صريحًا».
 *   ⑤ **الارتداد**: `invoiced → partially_collected → collected` (§4).
 *   ⑥ **والفائضُ يُعلَن ولا يُبتلع**.
 *
 * البذرُ معزول: عميلٌ وذممُه ومستخلصُه في 2099 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '17', 'company_id' => 4, 'name' => 'M05 collection test');

require_once dirname(__DIR__) . '/app/Services/Revenue/CollectionService.php';

use App\Services\Revenue\CollectionService as COL;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999891;
$MARK   = 'M05T' . getmypid();
$FAMILY = 'M05T';   // عائلةُ وسمِ هذا الفاحصِ — يُكنَس بها ما تركته جولاتٌ ميتة

$teardown = function () use ($conn, $MARK, $FAMILY) {
    $conn->query("DELETE a FROM fin_collection_allocations a
                    JOIN fin_receivables r ON r.id = a.receivable_id
                   WHERE r.doc_ref LIKE '{$MARK}%'");
    /* ◆ **عطلُ كنسٍ مقيسٌ ومُكلِّف**: كان الحذفُ بـ`bank_ref LIKE '{MARK}%'`
         وحدَه — والصفُّ الذي يصنعه الفحصُ لإثباتِ الثغرةِ (`{MARK}-LEAK`)
         `bank_ref` فيه **NULL بالتصميم**، فلا يطابقه الشرطُ أبدًا. فتراكمت
         **ستةٌ وعشرون** صفَّ تحصيلٍ بلا مرجعٍ بنكيٍّ في قاعدةٍ ماليةٍ حقيقيةٍ
         على مدى جولاتٍ، وحجبت قيدَ `ck_collection_bank_ref` عن الإضافةِ في
         هجرةِ الترميمِ الجماعيّ — أي أن **كنسًا ناقصًا منع حارسًا ماليًّا من
         العمل**. (حُذفت في `2027_02_14` بعد إثباتِ أنها بلا تخصيصٍ ولا حدث.)
       ⇒ الحذفُ بـ`payment_no` أيضًا — وهو وسمُ الصفِّ نفسِه لا حقلٌ اختياريّ. */
    $conn->query("DELETE FROM fin_payments WHERE bank_ref LIKE '{$MARK}%'
                    OR payment_no LIKE '{$MARK}%'");
    $conn->query("DELETE FROM fin_receivables WHERE doc_ref LIKE '{$MARK}%'");
    /* ◆ **الفاتورةُ الضريبيةُ تُكنَس قبل مستخلصِها**: `seed_source_invoice`
         تُنشئ صفًّا في `tax_invoices` (INJ-0036: لا ذمّةَ بلا فاتورةٍ تقابلها)،
         و`fk_tax_invoice_claim` يردُّ حذفَ المستخلص — والحذفُ لا يُفحَص مُرجَعُه
         فيُبلَع الردُّ ويبقى المستخلص. */
    $conn->query("DELETE FROM tax_invoices WHERE claim_id IN
                    (SELECT id FROM claims WHERE claim_no LIKE '{$FAMILY}%')");
    $conn->query("DELETE FROM claims WHERE claim_no LIKE '{$MARK}%'");
    $conn->query("DELETE FROM clients WHERE client_name LIKE '%{$MARK}%'");
    /* ══ **الكنسُ يمسح عائلةَ الفاحصِ كلَّها لا جولتَه وحدَها.** الوسمُ يحمل
         رقمَ العملية، فكلُّ جولةٍ تكنس **صفوفَها هي** وتترك صفوفَ الجولاتِ
         الميتة. وعلى `claims` قيدٌ فريدٌ `uq_claim_period`
         (شركة × عقد × من × إلى) والبذرُ يُغفل `contract_id` فيأخذ 0 والفترةَ
         ثابتةً — **فمستخلصُ أيِّ جولةٍ ناجيةٍ يحجز مفتاحَ كلِّ جولةٍ بعده**.
         فيُردُّ إدراجُ المستخلصِ صامتًا، ويحمل `insert_id` **معرّفَ الإدراجِ
         السابقِ الناجحِ** فيمرُّ `$CLM > 0` كذبًا، ثم يرسب الارتدادُ — فيُقرأ
         عطلٌ في خدمةٍ ماليةٍ **سليمةٍ تمامًا** (صفُّ البقايا نفسُه حالتُه
         `collected` — أي أن الارتدادَ عمل في جولتِه). */
    $conn->query("DELETE FROM tax_invoices WHERE claim_id IN
                    (SELECT id FROM claims WHERE claim_no LIKE '{$FAMILY}%')");
    $conn->query("DELETE a FROM fin_collection_allocations a
                    JOIN fin_receivables r ON r.id = a.receivable_id
                   WHERE r.doc_ref LIKE '{$FAMILY}%'");
    $conn->query("DELETE FROM fin_payments WHERE bank_ref LIKE '{$FAMILY}%'
                    OR payment_no LIKE '{$FAMILY}%'");
    $conn->query("DELETE FROM fin_receivables WHERE doc_ref LIKE '{$FAMILY}%'");
    $conn->query("DELETE FROM claims WHERE claim_no LIKE '{$FAMILY}%'");
    $conn->query("DELETE FROM clients WHERE client_name LIKE '%{$FAMILY}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-05 — إحكامُ التحصيل ══\n");

head('البذر — عميلٌ وذمّتان ومستخلصٌ مفوتَر');

$conn->query("INSERT INTO clients (company_id, client_code, client_name, created_at)
              VALUES ({$CO}, 'C-{$MARK}', 'عميلُ {$MARK}', NOW())");
$CLI = intval($conn->insert_id);
check($CLI > 0, 'عميلٌ مبذور');

$mkRecv = function ($ref, $amount, $due) use ($conn, $CO, $CLI, $MARK) {
    /* INJ-0036: لا ذمّةَ بلا فاتورةٍ صادرةٍ تقابلها */
    require_once __DIR__ . '/_source_doc_seed.php';
    $ti = seed_source_invoice($conn, $CO, $MARK . '-' . $ref, $CLI, $amount, 'USD', 0);
    $ok = $conn->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type,
                        doc_ref, source_doc_id, amount, collected, state, due_date, created_at)
                        VALUES ({$CO}, {$CLI}, 'invoice', '{$MARK}-{$ref}', {$ti}, {$amount}, 0, 'open',
                                '{$due}', NOW())");
    if (!$ok) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};
$R_OLD = $mkRecv('OLD', 1000, '2099-01-31');   // الأقدم
$R_NEW = $mkRecv('NEW', 2000, '2099-03-31');
check($R_OLD > 0 && $R_NEW > 0, 'وذمّتان: أقدمُ 1000 وأحدثُ 2000');

// مستخلصٌ مربوطٌ بالذمّة الأقدم — لاختبار الارتداد
$clmIns = $conn->query("INSERT INTO claims (company_id, claim_no, client_id, period_from, period_to,
              currency, gross_amount, retention_amount, net_amount, state, version,
              invoice_no, receivable_id, created_at)
              VALUES ({$CO}, '{$MARK}-CL', {$CLI}, '2099-01-01', '2099-01-31',
                      'USD', 1000, 0, 1000, 'invoiced', 1, '{$MARK}-OLD', {$R_OLD}, NOW())");
/* ◆ `insert_id` **لا يُصفَّر بإدراجٍ فاشل** — فيحمل معرّفَ آخرِ إدراجٍ ناجحٍ
     (الذمّة) فيمرُّ `$CLM > 0` كذبًا ويُقرأ العطلُ بعد أسطر. فيُفحَص المُرجَع. */
$clmOk = ($clmIns !== false) && ($conn->affected_rows === 1);
$CLM = $clmOk ? intval($conn->insert_id) : 0;
check($CLM > 0, 'ومستخلصٌ «مفوتر» مربوطٌ بالذمّة الأقدم'
    . ($CLM > 0 ? '' : ' — رُدَّ الإدراج: ' . mb_substr((string) $conn->error, 0, 90)));

// ═══ ① لا قبضَ بلا مرجع ═══
head('① **لا قبضَ بلا مرجع** (§4 · §7)');

$r = COL::record($conn, $gate, $CO, array(
    'client_id' => $CLI, 'amount' => 100, 'received_on' => '2099-02-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'المرجعُ البنكيُّ') !== false,
      '★ قبضٌ بلا مرجعٍ ⇒ **422** بنصّ «قبضٌ بمرجعٍ بنكيٍّ أو سند»');

$r = COL::record($conn, $gate, $CO, array(
    'client_id' => $CLI, 'amount' => 100, 'bank_ref' => 'legacy_no_ref',
    'received_on' => '2099-02-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'وسمُ الموروث') !== false,
      'و`legacy_no_ref` **وسمُ الموروث** لا يُكتب لجديد');

$leak = $conn->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type,
                      party_ref, amount, currency, method, state, created_at)
                      VALUES ({$CO}, '{$MARK}-LEAK', 'collection', 'client', {$CLI},
                              50, 'USD', 'cash', 'draft', NOW())");
check(!$leak, '★ وكتابةُ تحصيلٍ مباشرةً بلا مرجع **يرفضها CHECK** — بنيويًّا لا بفحصٍ يُنسى');

// ═══ ③ أقدمُ فاتورةٍ أولًا ═══
head('③ **أقدمُ فاتورةٍ أولًا** — والتخصيصُ سطرٌ يُرى (§4)');

$r = COL::record($conn, $gate, $CO, array(
    'client_id' => $CLI, 'amount' => 1500, 'bank_ref' => $MARK . '-BR1',
    'received_on' => '2099-02-10', 'currency' => 'USD'), $ACTOR);
check($r['ok'], 'قبضُ 1500 سُجّل (' . $r['reason'] . ')');
$P1 = intval($r['payment_id']);
check(count($r['allocations']) === 2, 'وخُصّص على **ذمّتين** (وُجد ' . count($r['allocations']) . ')');
check($r['allocations'][0]['receivable_id'] === $R_OLD
      && abs($r['allocations'][0]['amount'] - 1000.0) < 0.005,
      '★ **الأقدمُ أولًا**: 1000 كاملةً للذمّة الأقدم');
check($r['allocations'][1]['receivable_id'] === $R_NEW
      && abs($r['allocations'][1]['amount'] - 500.0) < 0.005,
      '★ والفائضُ 500 للتالية — لا يُبتلع ولا يُخمَّن');
check((string) $r['allocations'][0]['basis'] === 'oldest_first', 'وأساسُ التخصيص **مكتوبٌ في السطر**');

$alloc = COL::allocationsOf($gate, $P1);
check(count($alloc) === 2, 'والتخصيصُ **مقروءٌ من جدوله** — ظاهرٌ لا صامت');

$ro = $conn->query("SELECT collected, outstanding, state FROM fin_receivables WHERE id={$R_OLD}")->fetch_assoc();
check(abs((float) $ro['collected'] - 1000.0) < 0.005 && abs((float) $ro['outstanding']) < 0.005
      && (string) $ro['state'] === 'collected',
      '★ والذمّةُ الأقدمُ **مُقفلةٌ محصَّلة** والمتبقي صفر (`outstanding` مولَّد)');
$rn = $conn->query("SELECT collected, outstanding, state FROM fin_receivables WHERE id={$R_NEW}")->fetch_assoc();
check(abs((float) $rn['outstanding'] - 1500.0) < 0.005 && (string) $rn['state'] === 'partial',
      'والأحدثُ **جزئيةٌ** ومتبقيها 1500');

// ═══ ⑤ الارتداد ═══
head('⑤ **الارتداد**: `invoiced → collected` (§4)');

$cs = $conn->query("SELECT state FROM claims WHERE id={$CLM}")->fetch_assoc();
check((string) $cs['state'] === 'collected',
      '★★ ومستخلصُ الذمّة الأقدم **ارتدَّ إلى «collected»** — «والرصيدُ وعمرُه يتحدثان فورًا»');
$touched = false;
foreach ($r['claims_touched'] as $c) { if ((int) $c['claim_id'] === $CLM) { $touched = true; } }
check($touched, 'والخدمةُ **تُعلن أيَّ مستخلصٍ ارتدّ** — لا أثرٌ صامت');

// ═══ ② ولا يُقبض مرتين ═══
head('② **ولا يُقبض مرتين** (§7)');

$r2 = COL::record($conn, $gate, $CO, array(
    'client_id' => $CLI, 'amount' => 1500, 'bank_ref' => $MARK . '-BR1',
    'received_on' => '2099-02-10'), $ACTOR);
check(!$r2['ok'] && $r2['code'] === 409 && intval($r2['payment_id']) === $P1,
      '★ (مرجع × مبلغ × يوم) نفسُها ⇒ **409 بمرجع القائم**');

$dup = $conn->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type,
                     party_ref, amount, currency, method, bank_ref, received_on, state, created_at)
                     VALUES ({$CO}, '{$MARK}-DUP', 'collection', 'client', {$CLI},
                             1500, 'USD', 'bank', '{$MARK}-BR1', '2099-02-10', 'draft', NOW())");
check(!$dup, 'وكتابةٌ مباشرةٌ بالمفتاح نفسِه **يرفضها UQ**');

// ═══ ④ المرجعُ الصريح ═══
head('④ **مرجعٌ صريحٌ يتقدّم على الأقدمية**');

$R_THIRD = $mkRecv('THIRD', 700, '2098-12-31');   // **أقدمُ من الجميع**
$r = COL::record($conn, $gate, $CO, array(
    'client_id' => $CLI, 'amount' => 200, 'bank_ref' => $MARK . '-BR2',
    'received_on' => '2099-02-15', 'receivable_id' => $R_NEW), $ACTOR);
check($r['ok'] && count($r['allocations']) === 1
      && $r['allocations'][0]['receivable_id'] === $R_NEW,
      '★ ذمّةٌ محددةٌ ⇒ **التخصيصُ لها** ولو وُجد أقدمُ منها — «ما لم يحدد العميلُ مرجعًا صريحًا»');
check((string) $r['allocations'][0]['basis'] === 'explicit', 'وأساسُه **مرجعٌ صريح** مكتوبٌ في السطر');

// ═══ ⑥ الفائضُ يُعلَن ═══
head('⑥ **والفائضُ يُعلَن ولا يُبتلع**');

$r = COL::record($conn, $gate, $CO, array(
    'client_id' => $CLI, 'amount' => 5000, 'bank_ref' => $MARK . '-BR3',
    'received_on' => '2099-02-20'), $ACTOR);
check($r['ok'], 'قبضٌ 5000 يفوق ذممَ العميل');
check(abs($r['allocated'] - 2000.0) < 0.005 && abs($r['unallocated'] - 3000.0) < 0.005,
      '★ خُصّص 2000 (700 + 1300) و**بقي 3000 معلَنًا** — «رقمٌ يختفي أسوأُ من رقمٍ معلَن»');
check(mb_strpos((string) $r['reason'], 'بلا ذمّةٍ يُخصَّص لها') !== false,
      'والإعلانُ **نصٌّ يُقرأ** لا صمتٌ في سجل');

$cs = $conn->query("SELECT state FROM claims WHERE id={$CLM}")->fetch_assoc();
check((string) $cs['state'] === 'collected', 'وحالةُ المستخلص لم تنكسر بالقبض الفائض');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
