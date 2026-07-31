<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * P-02 — اختبار قبول: بنودُ المبيعات وفصلُها عن خطة الموارد (PLAN-03 §2 · §4)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/contract_lines_test.php
 *
 * ما يُثبته:
 *   ★ **برهانُ P-02**: **عقدُ طنٍّ بخطة معدات ⇒ قيمةُ العقد لم تتضاعف**.
 *   ① **التزامُ طاقةٍ لا يصير بندَ بيع** ⇒ 422 باسم النوع.
 *   ② **ولا بندَ خاضعٍ بلا رمزٍ ضريبيّ** (422 · و`CHECK` يرفض الكتابةَ المباشرة).
 *   ③ **والسريانُ لا يتداخل** ⇒ 409 · **والتغييرُ نسخةٌ تُخلِف**:
 *      ملحقٌ في منتصف المدة ⇒ **نسختان والمقارنةُ التاريخية لم تتغير**.
 *   ④ **ولا تُجمع عملتان في رقم**.
 *
 * البذرُ معزول: عقدٌ ومشروعٌ والتزاماتٌ في 2082 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'P02 lines test');

require_once dirname(__DIR__) . '/app/Services/Contract/ContractLineService.php';

use App\Services\Contract\ContractLineService as CLS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999021;
$MARK  = 'P02T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE l FROM client_contract_lines l JOIN contracts c ON c.id = l.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE o FROM contract_operational_sites o JOIN contracts c ON c.id = o.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contract_commitments WHERE commitment_code LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ P-02 — بنودُ المبيعات وفصلُها عن خطة الموارد ══\n");

head('البذر — عقدُ طنٍّ (20,000 طن) **وخطةُ معداتٍ (6 معدات)** معًا');

$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($conn->insert_id);
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
              actual_start, actual_end, first_party, second_party, contract_status, project_id, created_at)
              VALUES ({$CO}, '2082-01-01', 365, '2082-01-01', '2082-12-31',
                      'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, NOW())");
$CID = intval($conn->insert_id);

$mkCmt = function ($code, $type, $unit, $qty) use ($conn, $CO, $CID, $MARK) {
    $u = $unit === null ? 'NULL' : "'{$unit}'";
    $conn->query("INSERT INTO contract_commitments (company_id, commitment_code, party_scope,
                  contract_ref, commitment_type, unit_type, qty, period, obliged_party,
                  shortfall_rule, surplus_rule, note, created_by, created_at, updated_at)
                  VALUES ({$CO}, '{$code}-{$MARK}', 'client', {$CID}, '{$type}', {$u}, {$qty},
                          'contract', 'company', 'invoice_actual', 'same_price', 'بذرُ {$MARK}', 1, NOW(), NOW())");
    if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};
$C_TON = $mkCmt('TON', 'total_qty', 'ton', 20000);       // كميةٌ تُفوتَر
$C_EQP = $mkCmt('EQP', 'equipment_count', null, 6);      // **طاقةٌ لا تُفوتَر**
$C_AVL = $mkCmt('AVL', 'daily_availability_hours', 'hour', 20); // طاقةٌ كذلك
check($PRJ > 0 && $CID > 0 && $C_TON > 0 && $C_EQP > 0 && $C_AVL > 0,
      "عقدٌ #{$CID} · التزامُ 20000 طن · **وخطةُ 6 معداتٍ و20 ساعةَ إتاحة**");

$split = CLS::commitmentsSplit($gate, $CID);
check(count($split['billable']) === 1 && count($split['capacity']) === 2,
      '★ الفرزُ الآلي: **بندُ بيعٍ واحدٌ محتمَل · والتزاما طاقةٍ مستبعَدان**');

$TC = intval($conn->query("SELECT id FROM fin_tax_codes WHERE company_id={$CO} AND active=1 LIMIT 1")
                  ->fetch_assoc()['id']);

// ═══ ① الطاقةُ لا تصير بندَ بيع ═══
head('① **التزامُ طاقةٍ لا يصير بندَ بيع**');

$r = CLS::add($conn, $gate, $CO, $CID, array(
    'source_commitment_id' => $C_EQP, 'pricing_model' => 'day',
    'qty_contracted' => 6, 'unit_price' => 3500, 'tax_status' => 'taxable', 'tax_code_id' => $TC,
    'valid_from' => '2082-01-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'عددُ المعدات') !== false
      && mb_strpos($r['reason'], 'يضاعف الإيراد') !== false,
      '★★ **عددُ المعدات ⇒ 422 باسمه** ونصُّه يقول لماذا: ' . mb_substr($r['reason'], 0, 70));

$r = CLS::add($conn, $gate, $CO, $CID, array(
    'source_commitment_id' => $C_AVL, 'pricing_model' => 'hour',
    'qty_contracted' => 20, 'unit_price' => 100, 'tax_status' => 'exempt',
    'valid_from' => '2082-01-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'ساعاتُ الإتاحة') !== false,
      '★ وساعاتُ الإتاحة كذلك ⇒ **422**');

// ═══ ② الضريبةُ بمرجعها ═══
head('② **ولا بندَ خاضعٍ بلا رمزٍ ضريبيّ**');

$r = CLS::add($conn, $gate, $CO, $CID, array(
    'source_commitment_id' => $C_TON, 'pricing_model' => 'ton',
    'qty_contracted' => 20000, 'unit_price' => 4.75, 'tax_status' => 'taxable',
    'valid_from' => '2082-01-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'بلا رمزٍ ضريبيّ') !== false,
      '★★ خاضعٌ بلا رمزٍ ⇒ **422** — «الضريبةُ سطرٌ بمرجعها»');

$r = CLS::add($conn, $gate, $CO, $CID, array(
    'source_commitment_id' => $C_TON, 'pricing_model' => 'ton',
    'qty_contracted' => 20000, 'unit_price' => 4.75, 'tax_status' => 'taxable',
    'tax_code_id' => 999999, 'valid_from' => '2082-01-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'غيرُ مسجَّل') !== false,
      '★ ورمزٌ غيرُ مسجَّلٍ ⇒ **422** — ولا يُخترع');

$r = CLS::add($conn, $gate, $CO, $CID, array(
    'source_commitment_id' => $C_TON, 'pricing_model' => 'ton',
    'description' => 'نقلُ خامٍ بالطن', 'qty_contracted' => 20000, 'unit_price' => 4.75,
    'currency' => 'USD', 'tax_status' => 'taxable', 'tax_code_id' => $TC,
    'valid_from' => '2082-01-01'), $ACTOR);
check($r['ok'], 'وبرمزٍ مسجَّلٍ يُقبل — بندُ الطن #' . intval($r['line_id']));
$L_TON = (int) $r['line_id'];

$badTax = $conn->query("UPDATE client_contract_lines SET tax_status='taxable', tax_code_id=NULL
                         WHERE id={$L_TON}");
$q = $conn->query("SELECT tax_code_id FROM client_contract_lines WHERE id={$L_TON}")->fetch_assoc();
check($q['tax_code_id'] !== null,
      '★★ ومحوُ الرمز عن بندٍ خاضعٍ **يرفضه `CHECK`** — بنيويًّا لا بفحصٍ يُنسى');

// ═══ ★ برهانُ P-02 ═══
head('★ **برهانُ P-02: قيمةُ العقد لم تتضاعف**');

$v = CLS::contractValue($gate, $CID);
check(count($v['by_currency']) === 1 && isset($v['by_currency']['USD'])
      && abs($v['by_currency']['USD'] - 95000.0) < 0.005,
      '★★★ القيمةُ **95,000 USD** = 20,000 طن × 4.75 — **ولا شيءَ من خطة المعدات**');
check(count($v['lines']) === 1, 'وبندُ بيعٍ واحدٌ لا ثلاثة');
check(count($v['excluded']) === 2
      && mb_strpos((string) $v['excluded'][0]['why'], 'طاقةٌ لا تُفوتَر') !== false,
      '★★ **والتزاما الطاقة مُعلَنان مستبعَدَين بسببهما** — لا يُخفيان ولا يُحسبان');
check(mb_strpos($v['note'], 'استُبعد 2 التزامَ طاقة') !== false, 'والبيانُ يقولها نصًّا: ' . $v['note']);

// ولو حُسبت الطاقةُ خطأً لكانت القيمةُ 95,000 + (6 × 3,500) = 116,000
$naive = 95000.0 + (6 * 3500.0);
check(abs($v['by_currency']['USD'] - $naive) > 0.005,
      '★★★ **والقيمةُ الساذجةُ (116,000) لم تقع** — وهذا هو عينُ ما تمنعه P-02');

// ═══ ③ السريانُ والنسختان ═══
head('③ **والسريانُ لا يتداخل — والتغييرُ نسخةٌ تُخلِف**');

$dup = CLS::add($conn, $gate, $CO, $CID, array(
    'source_commitment_id' => $C_TON, 'pricing_model' => 'ton',
    'qty_contracted' => 20000, 'unit_price' => 5.00, 'currency' => 'USD',
    'tax_status' => 'taxable', 'tax_code_id' => $TC, 'valid_from' => '2082-06-01'), $ACTOR);
check(!$dup['ok'] && $dup['code'] === 409 && (int) $dup['line_id'] === $L_TON,
      '★★ بندٌ يتداخل سريانُه ⇒ **409 بمرجع القائم**');

$rp = CLS::reprice($conn, $gate, $CO, $L_TON, 4.75, '2082-01-01', $ACTOR);
check(!$rp['ok'] && $rp['code'] === 422 && mb_strpos($rp['reason'], 'ولا تعديلَ رجعيًّا') !== false,
      '★ وإعادةُ تسعيرٍ بسريانٍ غيرِ لاحقٍ ⇒ **422**');

$rp = CLS::reprice($conn, $gate, $CO, $L_TON, 5.50, '2082-07-01', $ACTOR, 'ملحقُ ' . $MARK);
check($rp['ok'] && (int) $rp['new_line_id'] > 0, '★ وملحقٌ في منتصف المدة يُنشئ **نسخةً**');
$L_NEW = (int) $rp['new_line_id'];

$old = $conn->query("SELECT valid_to, state, unit_price FROM client_contract_lines
                      WHERE id={$L_TON}")->fetch_assoc();
check((string) $old['valid_to'] === '2082-06-30' && (string) $old['state'] === 'superseded'
      && abs((float) $old['unit_price'] - 4.75) < 0.00005,
      '★★ **والقديمةُ مُغلقةٌ في 2082-06-30 بسعرها 4.75 لم يُمسّ** — «المقارنةُ التاريخية لم تتغير»');

$vJan = CLS::contractValue($gate, $CID, '2082-03-01');
check(count($vJan['lines']) === 1 && abs($vJan['by_currency']['USD'] - 95000.0) < 0.005,
      '★★★ وقيمةُ **آذار (قبل الملحق) 95,000** — بحكم النسخة القديمة');
$vAug = CLS::contractValue($gate, $CID, '2082-08-01');
check(count($vAug['lines']) === 1 && abs($vAug['by_currency']['USD'] - 110000.0) < 0.005,
      '★★★ وقيمةُ **آب (بعده) 110,000** = 20,000 × 5.50 — والنسختان لا تُجمعان');

// ═══ ④ لا تُجمع عملتان ═══
head('④ **ولا تُجمع عملتان في رقم**');

$sdg = CLS::add($conn, $gate, $CO, $CID, array(
    'pricing_model' => 'lump_sum', 'description' => 'تعبئةٌ مقطوعةٌ بالجنيه',
    'qty_contracted' => 1, 'unit_price' => 250000, 'currency' => 'SDG',
    'tax_status' => 'exempt', 'valid_from' => '2082-01-01'), $ACTOR);
check($sdg['ok'], 'أُضيف بندٌ مقطوعٌ بعملةٍ أخرى');

$v2 = CLS::contractValue($gate, $CID, '2082-08-01');
check(count($v2['by_currency']) === 2
      && abs($v2['by_currency']['USD'] - 110000.0) < 0.005
      && abs($v2['by_currency']['SDG'] - 250000.0) < 0.005,
      '★★ القيمةُ **بعملتين منفصلتين** (110,000 USD · 250,000 SDG) لا رقمٌ واحد');
check(mb_strpos($v2['note'], 'تعدُّدُ عملاتٍ: لا يُجمع في رقم') !== false,
      '★ والبيانُ يُعلن التعدُّدَ صراحةً');

// ═══ العزل ═══
head('العزلُ محفوظ');
$_SESSION['user']['company_id'] = 1;
$otherGate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::fromSession());
$leak = $otherGate->selectOne('client_contract_lines', array('where' => array('id' => $L_NEW)));
check($leak === null, '★ بندُ شركةٍ لا يُقرأ من نطاقٍ آخر — صفرُ تسريب');
$_SESSION['user']['company_id'] = $CO;

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
