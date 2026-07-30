<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-04 — اختبار قبول: كشفُ حساب العميل بطبقاته (ENT-03 §6 · §4)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/client_statement_test.php
 *
 * ما يُثبته:
 *   ① **الطبقاتُ الخمسُ كلٌّ في مكانه**: مستخلصاتٌ · فواتيرُ · تحصيلاتٌ ·
 *      محتجزٌ · مقدمةٌ — و**المحتجزُ لا يُخلط بالذمة الجارية** (§4).
 *   ② **كلُّ صفٍّ برابط مصدره** — و`orphan` **يُعلَن ولا يُخفى**.
 *   ③ **الرصيدُ من الفواتير لا من المستخلصات** — وإلا احتُسب الدَّينُ مرتين.
 *   ④ **الفاتورةُ الملغاةُ بصفرها ومعلَنةً** — لا تُحذف من الكشف ولا تُحتسب.
 *   ⑤ **الفترةُ تحكم**: مستخلصُ شهرٍ خارج المدى لا يدخل.
 *   ⑥ **التخصيصُ ظاهرٌ**: تحصيلٌ بلا ذمّةٍ مخصَّصةٍ **يُعلَن في نصّ سطره**.
 *   ⑦ **العزلُ محفوظ**: كشفُ عميلٍ لا يحمل صفَّ غيره.
 *
 * البذرُ معزول: عميلان ومستخلصاتٌ وفواتيرُ وتحصيلاتٌ في 2098 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'M04 statement test');

require_once dirname(__DIR__) . '/app/Services/Revenue/TaxInvoiceService.php';
require_once dirname(__DIR__) . '/app/Services/Revenue/ClientStatementService.php';

use App\Services\Revenue\TaxInvoiceService as TIS;
use App\Services\Revenue\ClientStatementService as CSS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999881;
$MARK  = 'M04T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE p FROM fin_payments p JOIN fin_receivables r ON r.id = p.receivable_id
                   WHERE r.doc_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM fin_receivables WHERE doc_ref LIKE '{$MARK}%'");
    $conn->query("DELETE t FROM tax_invoices t JOIN claims c ON c.id = t.claim_id
                   WHERE c.claim_no LIKE '{$MARK}%'");
    $conn->query("DELETE FROM claims WHERE claim_no LIKE '{$MARK}%'");
    $conn->query("DELETE FROM clients WHERE client_name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-04 — كشفُ حساب العميل بطبقاته ══\n");

head('البذر — عميلان ومستخلصاتٌ وفواتيرُ وتحصيل');

$mkClient = function ($sfx) use ($conn, $CO, $MARK) {
    $conn->query("INSERT INTO clients (company_id, client_code, client_name, created_at)
                  VALUES ({$CO}, 'C-{$MARK}-{$sfx}', 'عميلُ {$MARK}-{$sfx}', NOW())");
    return intval($conn->insert_id);
};
$CL_A = $mkClient('A');
$CL_B = $mkClient('B');
check($CL_A > 0 && $CL_B > 0, 'عميلان مبذوران');

$mn = 0;
$mkClaim = function ($client, $sfx, $net, $ret, $state) use ($conn, $CO, $MARK, &$mn) {
    $mn++;
    $from = sprintf('2098-%02d-01', $mn);
    $to   = date('Y-m-t', strtotime($from));
    $gross = $net + $ret;
    $ok = $conn->query("INSERT INTO claims (company_id, claim_no, client_id, period_from, period_to,
                  currency, gross_amount, retention_amount, net_amount, state, version, created_at)
                  VALUES ({$CO}, '{$MARK}-{$sfx}', {$client}, '{$from}', '{$to}',
                          'USD', {$gross}, {$ret}, {$net}, '{$state}', 1, NOW())");
    if (!$ok) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};
$C1 = $mkClaim($CL_A, 'A1', 1000, 50, 'approved');   // 2098-01
$C2 = $mkClaim($CL_A, 'A2', 2000, 0,  'approved');   // 2098-02
$C3 = $mkClaim($CL_B, 'B1', 5000, 0,  'approved');   // 2098-03 — لعميلٍ آخر
$COUT = $mkClaim($CL_A, 'AX', 700, 0, 'approved');   // 2098-04 — خارج المدى المقروء
check($C1 > 0 && $C2 > 0 && $C3 > 0 && $COUT > 0, 'وأربعةُ مستخلصات');

$i1 = TIS::issueForClaim($conn, $gate, $CO, $C1, array(), $ACTOR);
$i2 = TIS::issueForClaim($conn, $gate, $CO, $C2, array(), $ACTOR);
check($i1['ok'] && $i2['ok'], 'وفاتورتان ضريبيتان');

// تحصيلٌ مخصَّصٌ لذمّة الفاتورة الأولى
$conn->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
              amount, collected, state, created_at)
              VALUES ({$CO}, {$CL_A}, 'invoice', '{$MARK}-R1', 1000, 0, 'open', NOW())");
$R1 = intval($conn->insert_id);
$okP = $conn->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type,
                     party_ref, receivable_id, amount, currency, method, state, created_at)
                     VALUES ({$CO}, '{$MARK}-P1', 'collection', 'client', {$CL_A}, {$R1},
                             400, 'USD', 'bank', 'executed', '2098-01-20 10:00:00')");
check($okP, 'وتحصيلٌ 400 مخصَّصٌ لذمّة الفاتورة ' . ($okP ? '' : $conn->error));

// ═══ ① الطبقاتُ الخمس ═══
head('① **الطبقاتُ الخمسُ كلٌّ في مكانه** (§6)');

$s = CSS::build($gate, $CL_A, '2098-01-01', '2098-03-31');
check(count($s['layers']['claims']['rows']) === 2,
      'المستخلصاتُ سطران (وُجد ' . count($s['layers']['claims']['rows']) . ')');
check(count($s['layers']['invoices']['rows']) === 2, 'والفواتيرُ سطران');
check(count($s['layers']['collections']['rows']) === 1, 'والتحصيلُ سطرٌ واحد');
check(count($s['layers']['retention']['rows']) === 1
      && abs($s['totals']['retention'] - 50.0) < 0.005,
      '★ و**المحتجزُ في طبقته** 50 — «لا يُنسى ولا يُخلط بالذمة الجارية» (§4)');
check(abs($s['totals']['claims'] - 3000.0) < 0.005,
      'ومجموعُ المستخلصات 1000 + 2000 = **3000**');
check(abs($s['totals']['collections'] + 400.0) < 0.005,
      'والتحصيلُ **سالبٌ** −400 (ينقص الدَّين)');

// ═══ ② كلُّ صفٍّ برابط مصدره ═══
head('② **كلُّ صفٍّ برابط مصدره** — و`orphan` يُعلَن');

$linked = 0; $orphans = 0;
foreach ($s['layers'] as $l) {
    foreach ($l['rows'] as $r) {
        if ($r['orphan']) { $orphans++; } elseif ($r['link'] !== null) { $linked++; }
    }
}
check($orphans === 0 && $s['orphans'] === 0, 'لا صفَّ يتيمًا في البذر السليم');
check($linked === count($s['layers']['claims']['rows']) + count($s['layers']['invoices']['rows'])
                 + count($s['layers']['collections']['rows']) + count($s['layers']['retention']['rows']),
      '★ وكلُّ صفٍّ يحمل **رابطًا إلى شاشة مصدره**');

$row = $s['layers']['invoices']['rows'][0];
check(mb_strpos((string) $row['link'], 'tax_invoices.php?open=') !== false,
      'ورابطُ الفاتورة يشير إلى **شاشة الفاتورة بمعرّفها**');

// ═══ ③ الرصيدُ من الفواتير ═══
head('③ **الرصيدُ من الفواتير لا من المستخلصات**');

check(abs($s['totals']['invoices'] - 3000.0) < 0.005, 'مجموعُ الفواتير 3000');
check(abs($s['totals']['balance'] - 2600.0) < 0.005,
      '★★ والرصيدُ 3000 − 400 = **2600** — لا 5600 (المستخلصُ اعترافٌ سابقٌ على المطالبة)');

// ═══ ④ الملغاةُ بصفرها ومعلَنة ═══
head('④ **الفاتورةُ الملغاةُ بصفرها ومعلَنة**');

$r = TIS::cancel($conn, $gate, $CO, intval($i2['invoice_id']), 'خطأٌ في البيانات — اختبار', $ACTOR);
check($r['ok'], 'أُلغيت الفاتورةُ الثانيةُ ضريبيًّا');
$s2 = CSS::build($gate, $CL_A, '2098-01-01', '2098-03-31');
check(count($s2['layers']['invoices']['rows']) === 2,
      '★ والملغاةُ **باقيةٌ في الكشف سطرًا** — لا تُحذف');
check(abs($s2['totals']['invoices'] - 1000.0) < 0.005,
      '★ و**بصفرها في المجموع** (1000 لا 3000) — تُعلَن ولا تُحتسب');
$found = false;
foreach ($s2['layers']['invoices']['rows'] as $x) {
    if (mb_strpos((string) $x['description'], 'ملغاةٌ ضريبيًّا') !== false) { $found = true; }
}
check($found, 'والإلغاءُ **مكتوبٌ في نصّ السطر**');

// ═══ ⑤ الفترةُ تحكم ═══
head('⑤ **الفترةُ تحكم**');
check(abs($s['totals']['claims'] - 3000.0) < 0.005,
      'مستخلصُ 2098-04 (700) **لم يدخل** مدى يناير→مارس');
$sWide = CSS::build($gate, $CL_A, '2098-01-01', '2098-12-31');
check(abs($sWide['totals']['claims'] - 3700.0) < 0.005,
      'وبمدًى أوسع: **3700** — الفترةُ هي الفارق لا الصدفة');

// ═══ ⑥ التخصيصُ ظاهر ═══
head('⑥ **التخصيصُ ظاهرٌ في الكشف لا صامتًا** (§4)');
$col = $s['layers']['collections']['rows'][0];
check(mb_strpos((string) $col['description'], $MARK . '-R1') !== false,
      '★ سطرُ التحصيل **يسمّي الذمّةَ التي خُصّص لها**');

$conn->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type,
              party_ref, receivable_id, amount, currency, method, state, created_at)
              VALUES ({$CO}, '{$MARK}-P2', 'collection', 'client', {$CL_A}, {$R1},
                      100, 'USD', 'cash', 'draft', '2098-02-10 10:00:00')");
$s3 = CSS::build($gate, $CL_A, '2098-01-01', '2098-03-31');
check(count($s3['layers']['collections']['rows']) === 2,
      'وتحصيلٌ مسودةٌ يظهر **بحالته** — الكشفُ يُري ما وقع لا ما اعتُمد فقط');

// ═══ ⑦ العزل ═══
head('⑦ **العزلُ محفوظ**');
$sB = CSS::build($gate, $CL_B, '2098-01-01', '2098-12-31');
check(count($sB['layers']['claims']['rows']) === 1
      && abs($sB['totals']['claims'] - 5000.0) < 0.005,
      'كشفُ العميل الآخر **يحمل مستخلصَه وحدَه** (5000)');
check(count($sB['layers']['collections']['rows']) === 0,
      'ولا يحمل تحصيلَ غيره — «لا يرى أحدٌ ما ليس له»');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
