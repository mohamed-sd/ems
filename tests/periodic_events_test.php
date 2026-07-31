<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-41 — اختبار قبول: الدورياتُ الثلاثُ بمفاتيحها (SPEC-01 #23 · #30 · #22)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/periodic_events_test.php
 *
 * ما يُثبته:
 *   ① **مخصصُ الصيانة**: بلا قاعدةٍ مكتوبةٍ **لا مخصص** · والأخصُّ يغلب ·
 *      و**الكميةُ من المعتمَد وحدَه** (وحدةٌ مسودةٌ لا تُخصَّص) · وبمفتاح
 *      (المعدة × الفترة) لا يتكرر.
 *   ② **قسطُ التمويل**: **لا قسطَ قبل استحقاقه** · وحدثٌ واحدٌ بمفتاح
 *      (الالتزام × القسط) · و**`UNIQUE` يرفض قسطًا مكرَّرًا بنيويًّا**.
 *   ③ **الإقرارُ الضريبي**: الصافي = المخرجات − المدخلات يدويًّا · **بمفتاح
 *      الفترة** · وإقرارٌ ثانٍ ⇒ **409** · و**الصافي عمودٌ مولَّدٌ لا يُكتب**.
 *   ④ **وقفلُ الفترة يحكم الثلاثَ** — 423 بلا كتابة.
 *
 * البذرُ معزول: معدةٌ والتزامٌ وحركاتٌ ضريبيةٌ في 2087 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '17', 'company_id' => 4, 'name' => 'M41 periodic test');

require_once dirname(__DIR__) . '/app/Services/Finance/PeriodicEventService.php';

use App\Services\Finance\PeriodicEventService as PES;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999411;
$MARK  = 'M41T' . getmypid();
$PER   = '2087-05';

$teardown = function () use ($conn, $CO, $MARK, $PER) {
    $conn->query("DELETE FROM fin_financial_events WHERE idempotency_key LIKE 'mprov:%:{$PER}'
                    OR idempotency_key LIKE 'taxret:{$CO}:{$PER}'
                    OR idempotency_key IN (SELECT * FROM (SELECT CONCAT('fund:', f.id, ':', s.installment_no)
                         FROM fin_funding_schedules s JOIN fin_funding_facilities f ON f.id=s.facility_id
                        WHERE f.facility_no LIKE '%{$MARK}%') z)");
    $conn->query("DELETE FROM ems_business_events WHERE idempotency_key LIKE 'mprov:%:{$PER}'
                    OR idempotency_key LIKE 'taxret:{$CO}:{$PER}'");
    $conn->query("DELETE FROM fin_maint_provisions WHERE period_ref='{$PER}'");
    $conn->query("DELETE FROM fin_maint_provision_rules WHERE note LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_tax_returns WHERE period_ref='{$PER}'");
    $conn->query("DELETE FROM fin_tax_transactions WHERE period_ref='{$PER}'");
    $conn->query("DELETE s FROM fin_funding_schedules s JOIN fin_funding_facilities f ON f.id=s.facility_id
                   WHERE f.facility_no LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_funding_facilities WHERE facility_no LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM unit_entries WHERE entry_no LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_financial_periods WHERE company_id={$CO} AND fiscal_year=2087");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-41 — الدورياتُ الثلاثُ بمفاتيحها ══\n");

head('البذر — معدةٌ بوحداتٍ معتمدةٍ ومسودة · والتزامٌ بقسطين · وحركتان ضريبيتان');

// معدةٌ حقيقيةٌ من الأسطول (لا تُنشأ معدةٌ اختبارية — القراءةُ تكفي)
$EQ = intval($conn->query("SELECT id FROM equipments ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$mkUnit = function ($state, $qty, $type, $n) use ($conn, $CO, $EQ, $MARK, $PER) {
    $conn->query("INSERT INTO unit_entries (company_id, entry_no, entry_date, equipment_id,
                  unit_type, qty, record_basis, state, created_at, updated_at)
                  VALUES ({$CO}, 'UE-{$MARK}-{$n}', '{$PER}-10', {$EQ}, '{$type}', {$qty},
                          'contract', '{$state}', NOW(), NOW())");
    if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};
$U1 = $mkUnit('sales_approved', 40, 'hour', 1);   // معتمدة
$U2 = $mkUnit('parties_approved', 20, 'hour', 2); // معتمدة
$U3 = $mkUnit('submitted', 100, 'hour', 3);       // **مسودةٌ لا تُخصَّص**
check($EQ > 0 && $U1 > 0 && $U2 > 0 && $U3 > 0,
      "معدةٌ #{$EQ} بثلاث وحدات (40 + 20 معتمدة · و100 غيرُ معتمدة)");

$conn->query("INSERT INTO fin_funding_facilities (company_id, facility_no, facility_type, purpose,
              lender_name, principal, profit_rate, currency, start_date, state, created_at, updated_at)
              VALUES ({$CO}, 'FAC-{$MARK}', 'loan', 'equipment', 'ممولُ {$MARK}', 100000, 10, 'SDG',
                      '{$PER}-01', 'active', NOW(), NOW())");
$FAC = intval($conn->insert_id);
$mkInst = function ($no, $due, $p, $pr) use ($conn, $CO, $FAC) {
    $conn->query("INSERT INTO fin_funding_schedules (company_id, facility_id, installment_no,
                  due_date, principal_due, profit_due, paid_amount, state, created_at)
                  VALUES ({$CO}, {$FAC}, {$no}, '{$due}', {$p}, {$pr}, 0, 'due', NOW())");
    if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};
$I1 = $mkInst(1, $PER . '-15', 5000, 500);   // مستحقٌّ
$I2 = $mkInst(2, '2099-01-15', 5000, 500);   // **لم يحن بعد**
check($FAC > 0 && $I1 > 0 && $I2 > 0, "التزامٌ #{$FAC} بقسطين (مستحقٌّ ومؤجَّل)");

$TC = intval($conn->query("SELECT id FROM fin_tax_codes WHERE company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$conn->query("INSERT INTO fin_tax_transactions (company_id, tax_code_id, direction, base_amount,
              tax_rate, source_ref, period_ref, state, created_at)
              VALUES ({$CO}, {$TC}, 'output', 200000, 15, 'INV-{$MARK}', '{$PER}', 'draft', NOW())");
$conn->query("INSERT INTO fin_tax_transactions (company_id, tax_code_id, direction, base_amount,
              tax_rate, source_ref, period_ref, state, created_at)
              VALUES ({$CO}, {$TC}, 'input', 80000, 15, 'PO-{$MARK}', '{$PER}', 'draft', NOW())");
check($TC > 0, 'حركتان ضريبيتان: مخرجاتٌ على 200000 ومدخلاتٌ على 80000 بنسبة 15٪');

// ═══ ① مخصصُ الصيانة ═══
head('① **مخصصُ الصيانة** — بلا قاعدةٍ لا مخصص');

$r = PES::runProvisions($conn, $gate, $CO, $PER, $ACTOR, 'screen', array($EQ));
$sk = $r['skipped'] ? $r['skipped'][0] : array('code' => 0, 'reason' => '');
check($r['ok'] && $r['posted'] === 0 && (int) $sk['code'] === 422
      && mb_strpos((string) $sk['reason'], 'لا قاعدةَ مخصصٍ مكتوبةً') !== false,
      '★★ بلا قاعدةٍ ⇒ **صفرُ مخصصٍ وسببٌ مكتوب** — «لا كتابةَ يدويةً على الدفتر»');

// قاعدةٌ عامةٌ (الأعمّ) ثم قاعدةُ المعدة بعينها — والأخصُّ يغلب
$conn->query("INSERT INTO fin_maint_provision_rules (company_id, basis, rate, currency,
              effective_from, state, note, created_at)
              VALUES ({$CO}, 'hour', 3, 'SDG', '2087-01-01', 'active', 'عامةُ {$MARK}', NOW())");
$RG = intval($conn->insert_id);
$conn->query("INSERT INTO fin_maint_provision_rules (company_id, equipment_id, basis, rate, currency,
              effective_from, state, note, created_at)
              VALUES ({$CO}, {$EQ}, 'hour', 7, 'SDG', '2087-01-01', 'active', 'خاصةُ {$MARK}', NOW())");
$RS = intval($conn->insert_id);
check($RG > 0 && $RS > 0, 'قاعدتان: الأعمُّ 3/ساعة وقاعدةُ المعدة 7/ساعة');

$rule = PES::provisionRule($gate, $EQ, 0, $PER . '-31');
check($rule && (int) $rule['id'] === $RS,
      '★ **والأخصُّ يغلب**: قاعدةُ المعدة #' . $RS . ' لا العامة');

$r = PES::runProvisions($conn, $gate, $CO, $PER, $ACTOR, 'screen', array($EQ));
check($r['ok'] && $r['posted'] === 1 && abs($r['total'] - 420.0) < 0.005,
      '★★ المخصص **420** = (40 + 20 معتمدةً) × 7 — **و100 غيرِ المعتمدة لم تدخل**');

$row = $conn->query("SELECT * FROM fin_maint_provisions WHERE equipment_id={$EQ}
                      AND period_ref='{$PER}'")->fetch_assoc();
check($row && (int) $row['rule_id'] === $RS && abs((float) $row['qty'] - 60.0) < 0.005
      && (int) $row['event_id'] > 0,
      '★ والصفُّ يحمل قاعدتَه (60 ساعةً معتمدة) **ومرجعَ حدثه**');

$r2 = PES::runProvisions($conn, $gate, $CO, $PER, $ACTOR, 'screen', array($EQ));
check($r2['ok'] && $r2['posted'] === 0, '★ وتشغيلٌ ثانٍ ⇒ صفرُ مخصصٍ جديد');
$n = intval($conn->query("SELECT COUNT(*) c FROM fin_maint_provisions
                           WHERE equipment_id={$EQ} AND period_ref='{$PER}'")->fetch_assoc()['c']);
$ev = intval($conn->query("SELECT COUNT(*) c FROM fin_financial_events
                            WHERE idempotency_key='mprov:{$EQ}:{$PER}'")->fetch_assoc()['c']);
check($n === 1 && $ev === 1, '★★ **صفٌّ واحدٌ وحدثٌ واحد** بعد تشغيلين — بمفتاح (المعدة × الفترة)');

// ═══ ② قسطُ التمويل ═══
head('② **قسطُ التمويل** — لا قسطَ قبل استحقاقه');

$r = PES::accrueInstallments($conn, $gate, $CO, $PER . '-20', $ACTOR, array($I1, $I2));
check($r['ok'] && $r['posted'] === 1 && abs($r['total'] - 5500.0) < 0.005,
      '★★ قسطٌ واحدٌ **5500** (أصلٌ 5000 + ربحٌ 500) — **والمؤجَّلُ إلى 2099 لم يُعترف به**');
$q = $conn->query("SELECT event_id, accrued_at FROM fin_funding_schedules WHERE id={$I1}")->fetch_assoc();
check((int) $q['event_id'] > 0 && $q['accrued_at'] !== null, 'والقسطُ يحمل حدثَه ووقتَ اعترافه');
$q2 = $conn->query("SELECT event_id FROM fin_funding_schedules WHERE id={$I2}")->fetch_assoc();
check($q2['event_id'] === null, '★ والمؤجَّلُ **بلا حدث** — «لحظةَ استحقاقها» لا قبلها');

$ev = $conn->query("SELECT event_key, idempotency_key, amount FROM fin_financial_events
                     WHERE idempotency_key='fund:{$FAC}:1'")->fetch_assoc();
check($ev && (string) $ev['event_key'] === 'payable.finance_installment.accrued'
      && abs((float) $ev['amount'] - 5500.0) < 0.005,
      '★★ `payable.finance_installment.accrued` بمفتاح **fund:' . $FAC . ':1**');

$r = PES::accrueInstallments($conn, $gate, $CO, $PER . '-20', $ACTOR, array($I1, $I2));
check($r['ok'] && $r['posted'] === 0, '★ وتشغيلٌ ثانٍ ⇒ صفرُ قسطٍ جديد (العمودُ يحرس)');

$dup = $conn->query("INSERT INTO fin_funding_schedules (company_id, facility_id, installment_no,
                     due_date, principal_due, profit_due, paid_amount, state, created_at)
                     VALUES ({$CO}, {$FAC}, 1, '{$PER}-15', 5000, 500, 0, 'due', NOW())");
check($dup === false,
      '★★ وقسطٌ مكرَّرٌ بالرقم نفسِه **يرفضه `UNIQUE`** — «بمفتاح (الالتزام × القسط)» بنيويًّا');

// ═══ ③ الإقرارُ الضريبي ═══
head('③ **الإقرارُ الضريبي** — بمفتاح الفترة');

$r = PES::fileTaxReturn($conn, $gate, $CO, $PER, $ACTOR);
check($r['ok'] && abs($r['net'] - 18000.0) < 0.005,
      '★★ الصافي **18000** = مخرجاتُ 30000 − مدخلاتُ 12000 (15٪ من 200000 و80000)');
$ret = $conn->query("SELECT * FROM fin_tax_returns WHERE period_ref='{$PER}'")->fetch_assoc();
check($ret && abs((float) $ret['output_tax'] - 30000.0) < 0.005
      && abs((float) $ret['input_tax'] - 12000.0) < 0.005
      && abs((float) $ret['net_tax'] - 18000.0) < 0.005
      && (int) $ret['lines_count'] === 2 && (string) $ret['state'] === 'filed',
      '★ والإقرارُ بطبقاته الأربع وعدّادِ حركاته (2) وحالتِه `filed`');

$bad = $conn->query("UPDATE fin_tax_returns SET net_tax=999 WHERE period_ref='{$PER}'");
$ret2 = $conn->query("SELECT net_tax FROM fin_tax_returns WHERE period_ref='{$PER}'")->fetch_assoc();
check($bad === false || abs((float) $ret2['net_tax'] - 18000.0) < 0.005,
      '★★ و**الصافي عمودٌ مولَّدٌ لا يُكتب** — كتابتُه ترفضها البنية');

$r2 = PES::fileTaxReturn($conn, $gate, $CO, $PER, $ACTOR);
check(!$r2['ok'] && $r2['code'] === 409, '★ وإقرارٌ ثانٍ للفترة ⇒ **409**');
$evn = intval($conn->query("SELECT COUNT(*) c FROM fin_financial_events
                             WHERE idempotency_key='taxret:{$CO}:{$PER}'")->fetch_assoc()['c']);
check($evn === 1, 'وحدثٌ واحدٌ بمفتاح الفترة');

$st = $conn->query("SELECT COUNT(*) c FROM fin_tax_transactions
                     WHERE period_ref='{$PER}' AND state='filed'")->fetch_assoc();
check((int) $st['c'] === 2, '★ والحركتان وُسمتا `filed` — فلا تدخلان إقرارًا ثانيًا');

// ═══ ④ قفلُ الفترة ═══
head('④ **وقفلُ الفترة يحكم الثلاثَ**');
$conn->query("INSERT INTO fin_financial_periods (company_id, fiscal_year, period_type, period_no,
              start_date, end_date, state, posting_allowed, created_at)
              VALUES ({$CO}, 2087, 'month', 6, '2087-06-01', '2087-06-30', 'closed', 0, NOW())");
$r = PES::runProvisions($conn, $gate, $CO, '2087-06', $ACTOR, 'screen');
check(!$r['ok'] && $r['code'] === 423, '★ المخصصُ ⇒ **423** في فترةٍ مقفلة');
$r = PES::fileTaxReturn($conn, $gate, $CO, '2087-06', $ACTOR);
check(!$r['ok'] && $r['code'] === 423, '★ والإقرارُ ⇒ **423**');
$I3 = $mkInst(3, '2087-06-15', 1000, 100);
$r = PES::accrueInstallments($conn, $gate, $CO, '2087-06-30', $ACTOR, array($I3));
check($r['ok'] && $r['posted'] === 0 && $r['skipped'] && (int) $r['skipped'][0]['code'] === 423,
      '★★ والقسطُ المستحقُّ في فترةٍ مقفلةٍ **يُتخطّى بـ423 ولا يُكسر التشغيل**');

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
