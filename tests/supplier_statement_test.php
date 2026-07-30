<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-14 — اختبار قبول: كشفُ حساب المورد بطبقاته وروابطِ المصدر (ENT-02 §3 · §6)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/supplier_statement_test.php
 *
 * معيارُ القبول النصّي (§7): «**وقراءةُ المورد كشفَه ففهمُه بندًا بندًا حتى
 * مستنده**» — و§6: «كشفُ المورد **بطبقاته** و**كلُّ رقمٍ برابط مصدره**».
 *
 * ما يُثبته:
 *   ① الطبقاتُ الخمسُ قائمةٌ بأسمائها من سلسلة القيمة.
 *   ② **كلُّ رقمٍ برابط مصدره**: صفرُ صفٍّ بلا رابطٍ في بذرٍ سليم.
 *   ③ **ومن لا مصدرَ له يُعلَن `orphan` ولا يُخفى** (إخفاءُ رقمٍ بلا مصدرٍ يكذب).
 *   ④ التصنيفُ الصحيح: استحقاقٌ موجبٌ · تحميلٌ سالب · جزاءٌ في طبقته ·
 *      قسطُ سلفةٍ في طبقتها لا في التحميلات · وسدادٌ في طبقته.
 *   ⑤ المجاميعُ متسقة: الصافي = استحقاقٌ + تحميلاتٌ + جزاءاتٌ + سلف · والرصيدُ
 *      بعد السداد.
 *   ⑥ **الفترةُ تحكم**: ما خرج عنها لا يدخل الكشف.
 *   ⑦ «تبويبُ اللقطة يعرض الأسعارَ التي احتُسب بها» — وبلا عقدٍ نافذٍ **يُعلَن**.
 *   ⑧ الشاشةُ توصل الخدمةَ ولا تجمع بيدها.
 *
 * البذرُ معزول: تسوياتٌ M14T وفترةُ 2053 — تُكنس كاملةً.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Settlement/SupplierStatementService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Settlement\SupplierStatementService as SST;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 999811;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

$teardown = function () use ($conn) {
    $conn->query("DELETE FROM settlement_lines
                   WHERE settlement_id IN (SELECT id FROM settlements WHERE settlement_no LIKE 'M14T%')");
    $conn->query("DELETE FROM settlements WHERE settlement_no LIKE 'M14T%'");
    $conn->query("DELETE FROM fin_payments WHERE payment_no LIKE 'M14T%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-14 — كشفُ حساب المورد بطبقاته ══\n");

head('البذر — تسويةٌ بأربعة أنواع بنودٍ وسدادٌ');
$SUP = intval($conn->query("SELECT id FROM suppliers WHERE company_id={$CO} ORDER BY id DESC LIMIT 1")->fetch_assoc()['id']);
check($SUP > 0, "موردٌ من السجل الحي #{$SUP}");

$okH = $conn->query("INSERT INTO settlements (company_id, settlement_no, party_type, party_ref,
    party_name, period_from, period_to, currency, gross_amount, charges_amount, net_amount,
    state, prepared_by) VALUES ({$CO}, 'M14T-1', 'supplier', {$SUP}, 'M14T', '2053-06-01',
    '2053-06-30', 'SDG', 10000, 2300, 7700, 'approved', {$ACTOR})");
if (!$okH) { bad('تعذّر بذرُ التسوية: ' . $conn->error); }
$STL = intval($conn->insert_id);

/** سطرُ تسويةٍ — ويُعلن فشلَه بدل أن يمضي صامتًا. */
$line = function ($kind, $chargeType, $srcKind, $srcRef, $desc, $amount)
        use ($conn, $CO, $STL) {
    $ct = $chargeType === null ? 'NULL' : "'" . $conn->real_escape_string($chargeType) . "'";
    $ok = $conn->query("INSERT INTO settlement_lines (company_id, settlement_id, line_kind,
        charge_type, source_kind, source_ref, description, work_date, amount, currency, base_amount)
        VALUES ({$CO}, {$STL}, '{$kind}', {$ct}, '{$srcKind}', '{$srcRef}',
        '" . $conn->real_escape_string($desc) . "', '2053-06-15', {$amount}, 'SDG', {$amount})");
    if (!$ok) { bad('تعذّر بذرُ السطر: ' . $conn->error); }
};
$line('entitlement', null, 'due', '901', 'استحقاقُ ساعات', 10000);
$line('charge', 'fuel', 'due', '902', 'وقودٌ بسند الصرف', 1200);
$line('charge', 'penalty', 'due', '903', 'جزاءُ جاهزية', 500);
$line('charge', 'advance', 'supplier_advance', '904', 'قسطُ سلفة — سند M14T/ADV', 600);
// سطرٌ **بلا مصدر** — يجب أن يُعلَن لا أن يُخفى
$line('charge', 'transport', '', '', 'تحميلُ ترحيلٍ بلا مستند', 300);

$okP = $conn->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type,
    party_ref, method, amount, currency, fx_rate, base_amount, paid_at, state, created_by)
    VALUES ({$CO}, 'M14T-PAY-1', 'disbursement', 'supplier', {$SUP}, 'bank', 5000, 'SDG', 1.0, 5000,
    '2053-06-25 10:00:00', 'executed', {$ACTOR})");
if (!$okP) { bad('تعذّر بذرُ السداد: ' . $conn->error); }

// خارجَ الفترة — لا يدخل
$okO = $conn->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type,
    party_ref, method, amount, currency, fx_rate, base_amount, paid_at, state, created_by)
    VALUES ({$CO}, 'M14T-PAY-OUT', 'disbursement', 'supplier', {$SUP}, 'bank', 9999, 'SDG', 1.0, 9999,
    '2053-08-10 10:00:00', 'executed', {$ACTOR})");
if (!$okO) { bad('تعذّر بذرُ السداد خارجَ الفترة: ' . $conn->error); }
ok('بُذر: 5 بنودٍ وسدادان (أحدُهما خارجَ الفترة)');

// ═══ ① الطبقات ═══
head('① الطبقاتُ الخمسُ بأسمائها');
$st = SST::build($gate, $SUP, '2053-06-01', '2053-06-30');
check(count($st['layers']) === 5, 'خمسُ طبقاتٍ (استحقاقٌ · تحميلاتٌ · جزاءاتٌ · سلفٌ · سداد)');
foreach (array('entitlement','charges','penalties','advances','payments') as $k) {
    if (!isset($st['layers'][$k])) { bad('طبقةٌ ناقصة: ' . $k); }
}
ok('وكلُّها حاضرةٌ بمفاتيحها');

// ═══ ④ التصنيف ═══
head('④ التصنيفُ الصحيح لكل بند');
check(count($st['layers']['entitlement']['rows']) === 1
      && abs($st['layers']['entitlement']['total'] - 10000.0) < 0.005,
      'الاستحقاقُ سطرٌ **موجبٌ** 10000');
check(count($st['layers']['penalties']['rows']) === 1
      && abs($st['layers']['penalties']['total'] + 500.0) < 0.005,
      'والجزاءُ في **طبقته** سالبًا (−500) لا في التحميلات');
check(count($st['layers']['advances']['rows']) === 1
      && abs($st['layers']['advances']['total'] + 600.0) < 0.005,
      'وقسطُ السلفة في **طبقتها** سالبًا (−600) لا في التحميلات');
check(count($st['layers']['charges']['rows']) === 2
      && abs($st['layers']['charges']['total'] + 1500.0) < 0.005,
      'والتحميلاتُ الباقيةُ سطران بمجموع −1500 (وقود 1200 + ترحيل 300)');
check(count($st['layers']['payments']['rows']) === 1
      && abs($st['layers']['payments']['total'] + 5000.0) < 0.005,
      'والسدادُ سطرٌ واحدٌ −5000');

// ═══ ⑥ الفترة ═══
head('⑥ الفترةُ تحكم');
$outRow = false;
foreach ($st['layers']['payments']['rows'] as $r) {
    if (strpos($r['description'], 'M14T-PAY-OUT') !== false) { $outRow = true; }
}
check(!$outRow, 'سدادُ أغسطس **لم يدخل** كشفَ يونيو');

// ═══ ② و③ الروابط واليتيم ═══
head('② «كلُّ رقمٍ برابط مصدره» · ③ ومن لا مصدرَ له **يُعلَن**');
$linked = 0; $orphans = 0;
foreach ($st['layers'] as $L) {
    foreach ($L['rows'] as $r) {
        if ($r['orphan']) { $orphans++; }
        elseif ($r['link'] !== null) { $linked++; }
    }
}
check($orphans === 1, "سطرٌ واحدٌ بلا مصدرٍ **مُعلَنٌ** ({$orphans}) — لا مخفيّ");
check(intval($st['orphans']) === 1, 'والعدّادُ يرفعه إلى رأس الكشف ليُرى');
check($linked >= 5, "وبقيةُ الأسطر برابطها ({$linked}) — «بندًا بندًا حتى مستنده»");

$orphanRow = null;
foreach ($st['layers']['charges']['rows'] as $r) { if ($r['orphan']) { $orphanRow = $r; } }
check($orphanRow !== null && $orphanRow['link'] === null
      && abs($orphanRow['amount'] + 300.0) < 0.005,
      'واليتيمُ يظهر بمبلغه (−300) موسومًا — **إخفاؤه يكذب وإظهارُه يُصلَح**');

// الرابطُ يشير إلى **مصدر السطر** (الذمّة) لا إلى وعائه (التسوية) — والوعاءُ
// يظهر في عمود السياق. فالمصدرُ هو ما «يُقرأ منه المبلغ» (§3).
$entRow = $st['layers']['entitlement']['rows'][0];
check($entRow['link'] !== null && strpos($entRow['link'], 'dues_fin.php?id=') !== false,
      'ورابطُ الاستحقاق يشير إلى **مصدره** (الذمّة) لا إلى وعائه');
check(strpos($entRow['context'], 'M14T-1') !== false,
      'ووعاؤه (رقمُ التسوية وحالتُها) في عمود السياق');

// ═══ ⑤ المجاميع ═══
head('⑤ المجاميعُ متسقة');
$expectedNet = 10000 - 1500 - 500 - 600;      // = 7400
check(abs($st['totals']['net'] - $expectedNet) < 0.005,
      "الصافي = 10000 − 1500 − 500 − 600 = {$expectedNet} — آليًّا " . $st['totals']['net']);
check(abs($st['totals']['balance'] - ($expectedNet - 5000)) < 0.005,
      'والرصيدُ بعد السداد = ' . ($expectedNet - 5000) . ' — آليًّا ' . $st['totals']['balance']);

// ═══ ⑦ اللقطة ═══
head('⑦ «تبويبُ اللقطة يعرض الأسعارَ التي احتُسب بها»');
$snap = SST::priceSnapshot($gate, $SUP, '2053-06-30');
check(is_array($snap), 'اللقطةُ تُقرأ من بنود عقد المورد (H-07)');
$live = SST::priceSnapshot($gate,
    intval($conn->query("SELECT supplier_id FROM supplier_contracts WHERE state='نافذ'
                          ORDER BY id LIMIT 1")->fetch_assoc()['supplier_id']), '2026-04-15');
check(count($live) > 0 && isset($live[0]['unit_price']),
      'وموردٌ بعقدٍ نافذٍ بتاريخه: أسعارُه ظاهرةٌ بوحدتها وأساسِ استعدادها');
check(count($snap) === 0,
      'وبلا عقدٍ نافذٍ بتاريخ الكشف: **صفرُ سطرٍ — يُعلَن ولا تُخترع أسعار**');

// ═══ ⑧ الوصل ═══
head('⑧ الشاشةُ توصل الخدمةَ ولا تجمع بيدها');
$src = file_get_contents(dirname(__DIR__) . '/Finance/supplier_statement_fin.php');
check(strpos($src, 'SupplierStatementService::build') !== false,
      'الكشفُ يُبنى بالخدمة لا باستعلاماتٍ في الشاشة');
check(strpos($src, 'priceSnapshot') !== false, 'وتبويبُ اللقطة موصولٌ');
check(strpos($src, 'بلا مصدر') !== false, 'واليتيمُ **يُعرض موسومًا** في الشاشة');

// ═══ ⑨ العزل ═══
head('⑨ العزلُ محفوظ');
$other = SST::build($gate, 999999, '2053-06-01', '2053-06-30');
$totalRows = 0;
foreach ($other['layers'] as $L) { $totalRows += count($L['rows']); }
check($totalRows === 0, 'موردٌ غيرُ موجود ⇒ كشفٌ فارغٌ لا تسريب');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
