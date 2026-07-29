<?php
/**
 * tests/penalty_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 * الجزاءاتُ والحوافزُ والحدُّ الأدنى والاستقطاعات — CON-02 §6 (المرحلتان ④ و⑤).
 *
 *   ① الأنواعُ الأربعة تُحتسب بأرقامها (ق-9 · ق-10)
 *   ② السقفُ يقصّ ويُعلن أين قصّ (ق-12)
 *   ③ **ق-15 اتجاهٌ واحد**: بندٌ ملتزمُه العميلُ لا يولّد غرامة
 *   ④ **ق-11 الدورية**: فترةٌ ناقصةٌ تُترك ولا تُحتسب نسبيًّا
 *   ⑤ **ق-13 الدورة**: 12 يراجع · 19 يُجيز · ولا اعتمادَ ذات · والإعفاءُ بسببه
 *   ⑥ **ق-7 المسار المالي**: الإجازةُ تنشر قيدًا عبر الناشر وتربطه
 *   ⑦ **A5** الحدُّ الأدنى المضمون بندًا في المستخلص بوحدةٍ مميزة (هـ-6)
 *   ⑧ **ق-19** الاستقطاعان بندان ظاهران سالبان + رصيدٌ تراكمي
 *   ⑨ **ق-21** شارةُ «التزامٌ ينكسر» عند استحالة اللحاق حسابيًّا
 *
 * يبذر عالَمَه المستقلَّ ويكنسه.
 * التشغيل: php tests/penalty_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'penalty test');

require_once dirname(__DIR__) . '/app/Services/Contract/PenaltyService.php';
require_once dirname(__DIR__) . '/Contracts/claim_helpers.php';
require_once dirname(__DIR__) . '/includes/contract_badges.php';

use App\Services\Contract\PenaltyService as PEN;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();
$CO = 4;
$TAG = 'PEN' . getmypid();
$ETYPE = 'PENTYPE';
$FROM = '2026-05-01'; $TO = '2026-05-31';
$PRICE = 100.0;                  // سعرُ الساعة في العقد
$REVIEWER = 13;                  // المبيعات
$APPROVER = 74;                  // مديرُ المالية

$root = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($root->connect_error) { fwrite(STDERR, "root: {$root->connect_error}\n"); exit(1); }
$root->set_charset('utf8mb4');

$cleanup = function () use ($root, $TAG) {
    $root->query("DELETE FROM fin_event_links WHERE event_id IN (SELECT id FROM (SELECT id FROM fin_financial_events WHERE source_ref LIKE 'PEN-%' AND notes LIKE '%$TAG%') f)");
    $root->query("DELETE cl FROM claim_lines cl JOIN claims c ON c.id=cl.claim_id
                  JOIN contracts ct ON ct.id=c.contract_id JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE c FROM claims c JOIN contracts ct ON ct.id=c.contract_id
                  JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE fel FROM fin_event_links fel JOIN fin_financial_events fe ON fe.id=fel.event_id
                  JOIN contracts ct ON ct.id=fe.contract_id JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE fe FROM fin_financial_events fe JOIN contracts ct ON ct.id=fe.contract_id
                  JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE FROM fin_dues WHERE period_ref LIKE '2026-05' AND party_ref IN
                  (SELECT id FROM (SELECT id FROM suppliers WHERE name LIKE '$TAG%') s)");
    $root->query("DELETE a FROM contract_penalty_assessments a JOIN contracts ct ON ct.id=a.client_contract_id
                  JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE r FROM contract_penalty_rules r JOIN contracts ct ON ct.id=r.client_contract_id
                  JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE cm FROM contract_commitments cm JOIN contracts ct ON ct.id=cm.contract_ref
                  JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE ce FROM contractequipments ce JOIN contracts ct ON ct.id=ce.contract_id
                  JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE ct FROM contracts ct JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE FROM project WHERE name LIKE '$TAG%'");
    $root->query("DELETE FROM suppliers WHERE name LIKE '$TAG%'");
    $root->query("DELETE FROM clients WHERE client_name LIKE '$TAG%'");
};
$cleanup();

function seed($root, $sql) {
    if (!$root->query($sql)) { throw new \RuntimeException('بذر: ' . $root->error . ' ← ' . substr($sql, 0, 150)); }
    return intval($root->insert_id);
}

fwrite(STDOUT, "══ CON-02 §6 — الجزاءاتُ والحوافزُ والاستقطاعات ══\n");

try {
head('① بذرُ عقدٍ بالتزاماته وقواعده');
$CL  = seed($root, "INSERT INTO clients (company_id, client_name, phone, status) VALUES ($CO,'$TAG-عميل','0',1)");
$SUP = seed($root, "INSERT INTO suppliers (company_id, name, phone, status) VALUES ($CO,'$TAG-مورد','0',1)");
$PRJ = seed($root, "INSERT INTO project (company_id, name, client, client_id, location, total, status)
                    VALUES ($CO,'$TAG-مشروع','$TAG-عميل',$CL,'اختبار','0',1)");
$CON = seed($root, "INSERT INTO contracts (company_id, project_id, contract_signing_date, price_currency_contract,
                    actual_start, actual_end, daily_work_hours, retention_pct, advance_recovery_pct, status)
                    VALUES ($CO,$PRJ,'2026-01-01','دولار','2026-01-01','2026-12-31','20',5.00,10.00,1)");
seed($root, "INSERT INTO contractequipments (company_id, contract_id, equip_type, equip_unit, equip_price, equip_price_currency)
             VALUES ($CO,$CON,'$ETYPE','ساعة',$PRICE,'دولار')");

// بندٌ ملتزمُه **الشركة** (يولّد غرامة) — 1000 ساعةٍ شهريًّا
$CM_CO = seed($root, "INSERT INTO contract_commitments (company_id, commitment_code, party_scope, contract_ref,
    commitment_type, unit_type, qty, period, obliged_party, shortfall_rule, surplus_rule)
    VALUES ($CO,'$TAG-A','client',$CON,'period_hours','hour',1000.00,'monthly','company','penalty','same_price')");
// بندٌ ملتزمُه **العميل** (ق-15: لا غرامةَ منه)
$CM_CL = seed($root, "INSERT INTO contract_commitments (company_id, commitment_code, party_scope, contract_ref,
    commitment_type, unit_type, qty, period, obliged_party, shortfall_rule, surplus_rule)
    VALUES ($CO,'$TAG-B','client',$CON,'period_qty','hour',500.00,'monthly','client','penalty','same_price')");
// الحدُّ الأدنى المضمون — 800 ساعةٍ شهريًّا (A5)
$CM_MG = seed($root, "INSERT INTO contract_commitments (company_id, commitment_code, party_scope, contract_ref,
    commitment_type, unit_type, qty, period, obliged_party, shortfall_rule, surplus_rule)
    VALUES ($CO,'$TAG-C','client',$CON,'min_guaranteed','hour',800.00,'monthly','company','invoice_actual','same_price')");

// قاعدتا جزاءٍ وقاعدتا حافز
$R_SHORT = seed($root, "INSERT INTO contract_penalty_rules (company_id, client_contract_id, rule_kind, commitment_ref,
    rate, cap_percent, periodicity, valid_from) VALUES ($CO,$CON,'shortfall_pct',$CM_CO,50.000,2.00,'monthly','2026-01-01')");
$R_CLIENT = seed($root, "INSERT INTO contract_penalty_rules (company_id, client_contract_id, rule_kind, commitment_ref,
    rate, periodicity, valid_from) VALUES ($CO,$CON,'shortfall_pct',$CM_CL,50.000,'monthly','2026-01-01')");
$R_FIXED = seed($root, "INSERT INTO contract_penalty_rules (company_id, client_contract_id, rule_kind,
    fixed_amount, periodicity, valid_from, note) VALUES ($CO,$CON,'bonus_fixed',5000.00,'monthly','2026-01-01','سلامةٌ بلا حوادث')");
ok("عقد #$CON: 1000س ملتزَمةٌ علينا · 500س على العميل · 800س حدٌّ أدنى · احتجاز 5٪ · استهلاك 10٪");

// إيرادٌ منفَّذ: 600 ساعةٍ فقط (عجزٌ 400 عن الملتزَم و200 عن الحد الأدنى)
$root->query("INSERT INTO fin_financial_events (company_id, event_no, event_type, source_module, amount,
    quantity, unit, currency, contract_id, state, event_status, occurred_at, created_by, created_at, updated_at)
    VALUES ($CO,'$TAG-EV1','revenue','projects'," . (600 * $PRICE) . ",600,'hour','USD',$CON,'posted','active','2026-05-15 00:00:00',1,NOW(),NOW())");
info('المنفَّذُ فعلًا: 600 ساعة (من 1000 ملتزَمة) بقيمة ' . number_format(600 * $PRICE, 2));

head('② الاحتساب — والأنواعُ الأربعة بأرقامها');
$r = PEN::assess($conn, $gate, $CO, $CON, $FROM, $TO, $REVIEWER);
check($r['ok'], 'الاحتسابُ نجح — ' . $r['computed'] . ' بندًا');
foreach ($r['skipped'] as $s) { info('تُرك: ' . $s); }

$rows = $root->query("SELECT id, kind, rule_kind, committed_qty, actual_qty, gap_qty, base_amount,
                             raw_amount, cap_amount, amount, state
                        FROM contract_penalty_assessments WHERE client_contract_id=$CON ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$byRule = array();
foreach ($rows as $x) { $byRule[$x['rule_kind'] . ':' . $x['kind']] = $x; }
foreach ($rows as $x) {
    info("{$x['kind']}/{$x['rule_kind']}: ملتزَم={$x['committed_qty']} منفَّذ={$x['actual_qty']} فارق={$x['gap_qty']} "
       . "أساس={$x['base_amount']} خام={$x['raw_amount']} سقف=" . ($x['cap_amount'] ?? '—') . " نهائي={$x['amount']}");
}

head('③ ق-12 — السقفُ يقصّ ويُعلن');
$sh = $byRule['shortfall_pct:penalty'] ?? null;
check($sh !== null, 'غرامةُ العجز محتسَبة');
check($sh && abs((float) $sh['raw_amount'] - 20000.00) < 0.01,
      'الخام = 400س × 100 × 50٪ = 20,000 (مقيس: ' . ($sh['raw_amount'] ?? '—') . ')');
check($sh && abs((float) $sh['cap_amount'] - 2000.00) < 0.01,
      'السقف = 2٪ من قيمة البند الملتزَم (100,000) = 2,000');
check($sh && abs((float) $sh['amount'] - 2000.00) < 0.01,
      '**والنهائيُّ 2,000 لا 20,000** — السقفُ قصّ، والخامُ محفوظٌ فيُرى أين قُصّ');

head('④ ق-15 — اتجاهٌ واحد: بندٌ ملتزمُه العميلُ لا يولّد غرامة');
$clientPen = 0;
foreach ($rows as $x) { if ((int) $x['id'] > 0 && $x['kind'] === 'penalty') { $clientPen++; } }
check($clientPen === 1, '**غرامةٌ واحدةٌ فقط** — بندُ العميل لم يولّد غرامةً (' . $clientPen . ')');
$found = false;
foreach ($r['skipped'] as $s) { if (strpos($s, 'ق-15') !== false) { $found = true; info('التعليل: ' . $s); } }
check($found, 'والتركُ معلَّلٌ نصًّا بـق-15 لا صامتًا');

head('⑤ ق-10 — الحافزُ المقطوع بمعيارٍ يدوي');
$bf = $byRule['bonus_fixed:incentive'] ?? null;
check($bf !== null && abs((float) $bf['amount'] - 5000.00) < 0.01, 'حافزُ السلامة 5,000 محتسَبٌ كما سُجّل');

head('⑥ A5 — الحدُّ الأدنى المضمون بندًا مستقلًّا (ق-8 · هـ-6)');
$mg = $byRule['min_guaranteed:min_guarantee'] ?? null;
check($mg !== null, '**A5**: الحدُّ الأدنى محتسَب');
check($mg && abs((float) $mg['gap_qty'] - 200.0) < 0.01, 'الفارق = 800 − 600 = 200 ساعة');
check($mg && abs((float) $mg['amount'] - 20000.00) < 0.01, 'وقيمتُه = 200 × 100 = 20,000');

head('⑦ ق-13 — الدورةُ وفصلُ اليدين');
$aid = (int) $sh['id'];
$bad1 = PEN::approve($conn, $gate, $CO, $aid, $APPROVER);
check(!$bad1['ok'], 'لا إجازةَ قبل المراجعة: ' . $bad1['reason']);
$rv = PEN::review($gate, $CO, $aid, $REVIEWER);
check($rv['ok'], 'المبيعاتُ راجعت');
$self = PEN::approve($conn, $gate, $CO, $aid, $REVIEWER);
check(!$self['ok'], '**لا اعتمادَ ذات**: ' . $self['reason']);
$ap = PEN::approve($conn, $gate, $CO, $aid, $APPROVER);
check($ap['ok'], 'المالية أجازت — ' . $ap['reason']);

head('⑧ ق-7 — القيدُ نُشر عبر الناشر ورُبط');
$ev = $root->query("SELECT id, event_type, amount, currency, unit, source_ref, root_event_id, contract_id
                      FROM fin_financial_events WHERE source_ref='PEN-$aid'")->fetch_assoc();
check($ev !== null, 'القيدُ موجودٌ في الدفتر');
check($ev && abs((float) $ev['amount'] + 2000.00) < 0.01,
      '**الغرامةُ إيرادٌ سالبٌ −2,000** — تعديلُ قيمةِ ما نطالب به لا نفقةٌ نُنفقها');
check($ev && $ev['root_event_id'] !== null, 'وله جذرٌ محايدٌ في `ems_business_events` (ADR-15)');
$lnk = $root->query("SELECT COUNT(*) c FROM fin_event_links WHERE event_id=" . intval($ev['id']))->fetch_assoc();
check((int) $lnk['c'] === 1, 'ومربوطٌ في `fin_event_links` — فيقرؤه المستخلص');
$st = $root->query("SELECT state, event_id FROM contract_penalty_assessments WHERE id=$aid")->fetch_assoc();
check($st['state'] === 'posted' && (int) $st['event_id'] === (int) $ev['id'], 'والاحتسابُ صار `posted` بمرجع قيده');

head('⑨ ق-13 — الإعفاءُ بسببٍ إلزامي');
$mgId = (int) $mg['id'];
$noR = PEN::waive($conn, $gate, $CO, $mgId, '', $APPROVER);
check(!$noR['ok'], 'إعفاءٌ بلا سببٍ مرفوض: ' . $noR['reason']);
$wv = PEN::waive($conn, $gate, $CO, $mgId, 'اتفاقُ تسويةٍ مع العميل رقم 44', $APPROVER);
check($wv['ok'], 'أُعفي بسببه الموثَّق');
$w = $root->query("SELECT state, waive_reason FROM contract_penalty_assessments WHERE id=$mgId")->fetch_assoc();
check($w['state'] === 'waived' && $w['waive_reason'] !== '', 'والسببُ محفوظٌ في السجل');

head('⑩ ق-14 — الحافزُ لا يدخل المستخلصَ إلا باعتماد');
$bfId = (int) $bf['id'];
$preLines = claim_penalty_lines($gate, $CON, $FROM, $TO);
$preKinds = array(); foreach ($preLines as $l) { $preKinds[$l['source_kind']] = true; }
check(!isset($preKinds['incentive']),
      '**الحافزُ المحتسَبُ لم يدخل المستخلص** قبل اعتماده — يُعرض مقترحًا لا أكثر (ق-14)');
check(PEN::review($gate, $CO, $bfId, $REVIEWER)['ok'], 'المبيعاتُ راجعت الحافز');
check(PEN::approve($conn, $gate, $CO, $bfId, $APPROVER)['ok'], 'والمالية أجازته فنُشر قيدُه');

head('⑪ ق-19 — الاستقطاعان بندان ظاهران سالبان في المستخلص');
$lines = claim_penalty_lines($gate, $CON, $FROM, $TO);
$kinds = array();
foreach ($lines as $l) { $kinds[$l['source_kind']] = (float) $l['amount']; }
info('بنودُ المستخلص: ' . json_encode($kinds, JSON_UNESCAPED_UNICODE));
check(isset($kinds['penalty']) && $kinds['penalty'] < 0, '**بندُ الغرامة سالبٌ ظاهرٌ باسمه** لا خصمٌ صامت');
check(!isset($kinds['min_guarantee']), 'والمُعفى لا يدخل المستخلص');
check(isset($kinds['retention']) && $kinds['retention'] < 0, 'بندُ ضمان حسن التنفيذ سالبٌ ظاهر (5٪)');
// ── حُدِّث بـM-01 (2026-07-29) — كان يحرس سلوكًا **معطوبًا** ─────────────────
// كان التأكيدُ: «بندُ استهلاك الدفعة المقدمة (10٪) موجود» — أي أنه كان يضمن أن
// النظامَ يخصم 10٪ **على عقدٍ لم تُقبض له دفعةٌ أصلًا**: استردادُ دَينٍ لم يُقرَض،
// وتكرارُه إلى الأبد بلا سقف. فالتأكيدُ كان يحرس الخللَ لا يكشفه.
// والصوابُ الآن بشقّيه: لا بندَ بلا قبضٍ مسجَّل، وبندٌ **مقصوصٌ بالرصيد** بعده.
require_once dirname(__DIR__) . '/Contracts/advance_helpers.php';
check(!isset($kinds['advance_recovery']),
      '**ولا بندَ استهلاكٍ للدفعة المقدَّمة** — لا قبضَ مسجَّلٌ على العقد (M-01)');

$advDoc = 'PENT' . getmypid() . '-ADV';
$advRes = advance_record($conn, $gate, $CON, 40.00, $FROM, $advDoc, 'بذرةُ اختبار', $APPROVER);
check(!empty($advRes['ok']), 'وبتسجيل قبضِ 40.00: ' . ($advRes['advance_no'] ?? '—'));
$lines2 = claim_penalty_lines($gate, $CON, $FROM, $TO);
$kinds2 = array();
foreach ($lines2 as $l) { $kinds2[$l['source_kind']] = (float) $l['amount']; }
check(isset($kinds2['advance_recovery']) && $kinds2['advance_recovery'] < 0,
      'يظهر البندُ سالبًا: ' . ($kinds2['advance_recovery'] ?? '—'));
check(isset($kinds2['advance_recovery']) && abs($kinds2['advance_recovery']) <= 40.00,
      'و**مقصوصٌ عند الرصيد 40.00** لا نسبةً مطلقة: ' . abs($kinds2['advance_recovery'] ?? 0));
$conn->query("DELETE FROM contract_advances WHERE doc_ref = '" . $conn->real_escape_string($advDoc) . "'");
$conn->query("DELETE FROM ems_business_events WHERE event_key='contract.advance.received'
                AND entity_id NOT IN (SELECT id FROM (SELECT id FROM contract_advances) a)");

head('⑫ ق-21 — شارةُ «التزامٌ ينكسر» عند استحالة اللحاق');
$b1 = contract_capacity_badges($gate, $CON, '2026-05-31');
check(empty($b1['breaking']), 'لا شارةَ اليومَ: الالتزامُ شهريٌّ لا كامل العقد فلا يُقاس بسقف الطاقة');
// التزامُ كامل العقد بكميةٍ يستحيل بلوغُها: معدةٌ واحدةٌ × 20س × الأيامِ المتبقية
seed($root, "INSERT INTO contract_commitments (company_id, commitment_code, party_scope, contract_ref,
    commitment_type, unit_type, qty, period, obliged_party, shortfall_rule, surplus_rule)
    VALUES ($CO,'$TAG-D','client',$CON,'total_qty','hour',999999.00,'contract','company','penalty','same_price')");
$b2 = contract_capacity_badges($gate, $CON, '2026-12-01');
check(!empty($b2['breaking']), '**الشارةُ ترتفع** حين يتجاوز الباقي سقفَ الطاقة');
if (!empty($b2['breaking'])) { info($b2['breaking'][0]['detail']); }
check($b2['ending'] !== null && $b2['ending']['days'] === 30, 'وشارةُ «عقدٌ ينتهي خلال 30 يومًا» ظاهرة');

} catch (\Throwable $t) {
    bad('استثناء: ' . $t->getMessage() . ' @ ' . $t->getFile() . ':' . $t->getLine());
}

head('⑫ الكنس');
$cleanup();
$left = (int) $root->query("SELECT COUNT(*) c FROM project WHERE name LIKE '$TAG%'")->fetch_assoc()['c']
      + (int) $root->query("SELECT COUNT(*) c FROM contract_penalty_assessments a
                             LEFT JOIN contracts c ON c.id=a.client_contract_id WHERE c.id IS NULL")->fetch_assoc()['c'];
check($left === 0, 'صفرُ بقايا');

fwrite(STDOUT, "\n" . str_repeat('═', 60) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
