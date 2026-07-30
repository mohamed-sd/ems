<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-19 — اختبار قبول: وثائقُ المورد وحسابُه البنكيُّ الموثَّق وسجلُّ التدقيق
 *        (UX-05 §5.1-① · ENT-02 §7)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/supplier_documents_test.php
 *
 * ما يُثبته:
 *   ① **محورٌ ثالثٌ لا جدولٌ ثانٍ**: وثائقُ المورد تسكن `equipment_documents`
 *      بمحور `supplier` — فتنبيهُ الانتهاء وفهرسُه يُورَثان بلا نسخةٍ تتباعد.
 *   ② **وثيقةٌ نظاميةٌ بلا تاريخِ صلاحيةٍ مرفوضة** (422) — «تنبيهٌ آليٌّ قبل
 *      الانتهاء» بلا تاريخٍ وعدٌ لا يُنفَّذ · ونوعٌ خارج القائمة 422 · وتكرارٌ 409.
 *   ③ **التنبيهُ من التاريخ لا من الحالة**: منتهيةٌ · تُوشك · وناقصةٌ — ثلاثتُها
 *      محسوبةٌ من `expiry_date` و`alert_days` لا من `status` البشريّ.
 *   ④ **توثيقٌ بلا مستندٍ دعوى**: 422 خدمةً و**`CHECK`** بنيويًّا.
 *   ⑤ **البوابةُ بعلَمها**: `monitor` تقيس وتُعلن **وتمرّ**، و`enforce` **ترفض
 *      اعتمادَ التسوية 422 بأسبابه المسمّاة** — نمطُ E-08 نفسُه.
 *   ⑥ **سجلٌّ واحدٌ يُقرأ**: `auditOf` تقرأ `activity_logs` بمرشِّح نطاق المورد.
 *
 * البذرُ معزول: موردٌ ووثائقُه في 2096 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '2', 'company_id' => 4, 'name' => 'M19 documents test');

require_once dirname(__DIR__) . '/app/Services/Supplier/SupplierDocumentService.php';
require_once dirname(__DIR__) . '/app/Services/Settlement/SettlementService.php';

use App\Services\Supplier\SupplierDocumentService as SDS;
use App\Services\Settlement\SettlementService as SVC;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999861;
$MARK  = 'M19T' . getmypid();
$PER   = '2096-05';

$teardown = function () use ($conn, $MARK, $PER) {
    $conn->query("DELETE r FROM fin_requests r JOIN settlements s ON s.id = r.settlement_id
                   WHERE s.party_name LIKE '%{$MARK}%'");
    $conn->query("DELETE sl FROM settlement_lines sl JOIN settlements s ON s.id = sl.settlement_id
                   WHERE s.party_name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM settlements WHERE party_name LIKE '%{$MARK}%'");
    $orphan = "SELECT id FROM (SELECT id, source_ref FROM ems_business_events) be
                WHERE be.source_ref LIKE 'STL-%'
                  AND NOT EXISTS (SELECT 1 FROM (SELECT settlement_no FROM settlements) s
                                   WHERE s.settlement_no = be.source_ref)";
    $conn->query("DELETE FROM fin_financial_events WHERE root_event_id IN ({$orphan})");
    $conn->query("DELETE FROM ems_business_events WHERE id IN ({$orphan})");
    $conn->query("DELETE FROM fin_dues WHERE period_ref = '{$PER}'");
    $conn->query("DELETE d FROM equipment_documents d JOIN suppliers s ON s.id = d.subject_id
                   WHERE d.subject_type='supplier' AND s.name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM suppliers WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-19 — وثائقُ المورد وحسابُه الموثَّق ══\n");

head('البذر — موردٌ بلا وثيقةٍ ولا حساب');
$conn->query("INSERT INTO suppliers (company_id, name, phone, created_at)
              VALUES ({$CO}, 'موردُ {$MARK}', '0000000000', NOW())");
$SUP = intval($conn->insert_id);
check($SUP > 0, 'موردٌ مبذور');

// ═══ ① محورٌ ثالثٌ لا جدولٌ ثانٍ ═══
head('① **محورٌ ثالثٌ لا جدولٌ ثانٍ** — الوثائقُ في جدولها العام');

// التواريخُ **نسبيةٌ باليوم** عمدًا: البوابةُ الحيّةُ تقيس بـ«اليوم»، وتواريخُ
// مطلقةٌ في المستقبل تجعل الاختبارَ يقيس ما لا يقيسه المسارُ الحي.
$TODAY   = date('Y-m-d');
$SOON    = date('Y-m-d', strtotime('+10 days'));    // يُوشك (بتنبيه 30)
$EXPIRED = date('Y-m-d', strtotime('-30 days'));    // منتهية
$LONGAGO = date('Y-m-d', strtotime('-200 days'));   // تاريخُ قياسٍ ماضٍ

$r = SDS::saveDocument($conn, $gate, $CO, $SUP, array(
    'doc_type' => 'سجل تجاري', 'doc_no' => 'CR-' . $MARK,
    'issuer' => 'وزارة التجارة', 'expiry_date' => $SOON, 'alert_days' => 30), $ACTOR);
check($r['ok'], 'سجلٌّ تجاريٌّ حُفظ (' . $r['reason'] . ')');
$row = $conn->query("SELECT subject_type, subject_id FROM equipment_documents
                      WHERE doc_no='CR-{$MARK}'")->fetch_assoc();
check($row && (string) $row['subject_type'] === 'supplier' && intval($row['subject_id']) === $SUP,
      '★ وسكنت **`equipment_documents` بمحور `supplier`** — لا جدولَ ثانيًا لوثائقَ هي وثائق');

// ═══ ② حراسةُ الكتابة ═══
head('② **وثيقةٌ نظاميةٌ بلا تاريخِ صلاحيةٍ مرفوضة**');

$r = SDS::saveDocument($conn, $gate, $CO, $SUP, array(
    'doc_type' => 'شهادة ضريبية', 'doc_no' => 'TX-' . $MARK), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'وعدٌ لا يُنفَّذ') !== false,
      '★ شهادةٌ ضريبيةٌ بلا تاريخِ انتهاء ⇒ **422** — «تنبيهٌ آليٌّ» بلا تاريخٍ وعدٌ لا يُنفَّذ');

$r = SDS::saveDocument($conn, $gate, $CO, $SUP, array(
    'doc_type' => 'استمارة', 'doc_no' => 'X1', 'expiry_date' => $SOON), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'ونوعُ وثيقةٍ خارج وثائق المورد ⇒ 422');

$r = SDS::saveDocument($conn, $gate, $CO, $SUP, array(
    'doc_type' => 'سجل تجاري', 'doc_no' => 'CR-' . $MARK, 'expiry_date' => $SOON), $ACTOR);
check(!$r['ok'] && $r['code'] === 409, 'وتكرارُ (نوع × رقم) ⇒ **409** (UQ القائم)');

$r = SDS::saveDocument($conn, $gate, $CO, $SUP, array(
    'doc_type' => 'شهادة ضريبية', 'doc_no' => 'TX-' . $MARK,
    'expiry_date' => $EXPIRED, 'alert_days' => 15), $ACTOR);
check($r['ok'], 'وشهادةٌ ضريبيةٌ بتاريخٍ: تُقبل');

// ═══ ③ التنبيهُ من التاريخ ═══
head('③ **التنبيهُ من التاريخ لا من الحالة**');

$conn->query("UPDATE equipment_documents SET status='سارية' WHERE doc_no='TX-{$MARK}'");
$st = SDS::documentState($gate, $SUP, $TODAY);
check(count($st['expired']) === 1 && $st['expired'][0]['doc_type'] === 'شهادة ضريبية',
      '★ الضريبيةُ (انتهت ' . $EXPIRED . ') **منتهيةٌ** ولو كانت `status` تقول «سارية»');
check(count($st['expiring']) === 1 && $st['expiring'][0]['doc_type'] === 'سجل تجاري',
      '★ والتجاريُّ (ينتهي ' . $SOON . ' بتنبيه 30) **يُوشك** اليوم');
check(count($st['missing']) === 0, 'ولا وثيقةَ نظاميةً ناقصةً — كلتاهما مكتوبة');

$st2 = SDS::documentState($gate, $SUP, $LONGAGO);
check(count($st2['expired']) === 0 && count($st2['expiring']) === 0,
      'وفي ' . $LONGAGO . ': **لا منتهيةَ ولا موشكة** — القياسُ بتاريخه لا بيومنا');

// ═══ ④ توثيقٌ بلا مستندٍ دعوى ═══
head('④ **توثيقٌ بلا مستندٍ دعوى** — خدمةً وقيدًا');

$r = SDS::verifyBank($conn, $gate, $CO, $SUP, array(
    'bank_name' => 'بنك الاختبار', 'bank_account_no' => '1234567890'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'دعوى') !== false,
      'توثيقٌ بلا مستندٍ ⇒ **422**');
$r = SDS::verifyBank($conn, $gate, $CO, $SUP, array(
    'bank_name' => 'بنك الاختبار', 'bank_doc_ref' => 'شهادة بنكية 2096/5'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'وبلا رقمِ حساب ⇒ 422');

$conn->query("UPDATE suppliers SET bank_verified_at = NOW(), bank_account_no = NULL,
              bank_doc_ref = NULL WHERE id = {$SUP}");
$v = $conn->query("SELECT bank_verified_at FROM suppliers WHERE id={$SUP}")->fetch_assoc();
check($v['bank_verified_at'] === null,
      '★ ووسمُ التوثيق مباشرةً بلا حسابٍ ومستند **يرفضه CHECK** — بنيويًّا لا بفحصٍ يُنسى');

$r = SDS::verifyBank($conn, $gate, $CO, $SUP, array(
    'bank_name' => 'بنك الاختبار', 'bank_account_no' => '1234567890',
    'bank_doc_ref' => 'شهادة بنكية 2096/5'), $ACTOR);
check($r['ok'], 'وبالحساب والمستند معًا: يُوثَّق');

// ═══ ⑤ البوابةُ بعلَمها ═══
head('⑤ **البوابةُ بعلَمها** — monitor تُعلن وتمرّ · enforce ترفض');

$st = $conn->prepare("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
                      amount, currency, period_ref, created_by, created_at)
                      VALUES (?, 'supplier', ?, 'hours', 'credit', 900, 'USD', ?, 1, NOW())");
$ref = (string) $SUP;
$st->bind_param('iss', $CO, $ref, $PER);
$st->execute(); $st->close();

$res = SVC::generate($gate, $conn, 'supplier', $SUP, '2096-05-01', '2096-05-31', 901);
check($res['ok'], 'تسويةٌ وُلّدت للمورد (' . $res['reason'] . ')');
$SID = intval($res['settlement_id']);
$conn->query("UPDATE settlements SET state='review' WHERE id={$SID}");

$g = SDS::gateFor($gate, $SUP);
check(count($g['reasons']) === 1 && mb_strpos($g['reasons'][0], 'منتهية') !== false,
      'البوابةُ تقيس سببًا واحدًا: **وثيقةٌ منتهية** (والحسابُ صار موثَّقًا)');

// `ems_env_all()` تُخزَّن **ساكنةً** فقلبُ العلَم داخل العملية لا يُقرأ —
// فالقلبُ في `.env` والقياسُ في **عمليةٍ جديدة** (نمطُ عدّة E-08 نفسِها).
$ENV_FILE = dirname(__DIR__) . '/.env';
$ENV_BAK  = is_readable($ENV_FILE) ? file_get_contents($ENV_FILE) : '';
register_shutdown_function(function () use ($ENV_FILE, $ENV_BAK) {
    if ($ENV_BAK !== '') { file_put_contents($ENV_FILE, $ENV_BAK); }
});
$setMode = function ($mode) use ($ENV_FILE, $ENV_BAK) {
    $out = preg_replace('/^EMS_SUPPLIER_DOC_GATE=.*$/m', 'EMS_SUPPLIER_DOC_GATE=' . $mode, $ENV_BAK);
    if (strpos($out, 'EMS_SUPPLIER_DOC_GATE=' . $mode) === false) {
        $out = rtrim($ENV_BAK) . "\nEMS_SUPPLIER_DOC_GATE=" . $mode . "\n";
    }
    file_put_contents($ENV_FILE, $out);
};
$probe = function ($php) {
    $f = sys_get_temp_dir() . '/ems_m19_probe_' . getmypid() . '.php';
    file_put_contents($f, "<?php\n"
        . "error_reporting(E_ALL & ~E_DEPRECATED);\n"
        . "require '" . str_replace('\\', '/', dirname(__DIR__)) . "/config.php';\n"
        . "while (ob_get_level() > 0) { ob_end_clean(); }\n"
        . "\$_SESSION['user'] = array('id'=>1,'role'=>'2','company_id'=>4,'name'=>'m19 probe');\n"
        . "require '" . str_replace('\\', '/', dirname(__DIR__)) . "/app/Services/Supplier/SupplierDocumentService.php';\n"
        . "require '" . str_replace('\\', '/', dirname(__DIR__)) . "/app/Services/Settlement/SettlementService.php';\n"
        . "\$gate = ems_tenant_db(); \$conn = \$GLOBALS['conn'];\n" . $php . "\n");
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($f) . ' 2>&1');
    @unlink($f);
    return trim((string) $out);
};

$setMode('enforce');
$o = $probe("echo \\App\\Services\\Supplier\\SupplierDocumentService::mode();");
check($o === 'enforce', 'العلَمُ يُقرأ من `.env` في عمليةٍ جديدة (وُجد ' . $o . ')');
$o = $probe("\$g = \\App\\Services\\Supplier\\SupplierDocumentService::gateFor(\$gate, {$SUP});"
          . " echo \$g['blocked'] ? 'blocked' : 'passed';");
check($o === 'blocked', '★ وفي enforce: **محجوب** بأسبابه');
$o = $probe("\$r = \\App\\Services\\Settlement\\SettlementService::approve(\$gate, \$conn, {$SID}, 902);"
          . " echo \$r['ok'] ? 'ok' : (\$r['code'] . '|' . \$r['reason']);");
check(strpos($o, '422|') === 0 && mb_strpos($o, 'منتهية') !== false,
      '★★ واعتمادُ التسوية **يُرفض 422 بأسبابه المسمّاة** — لا «حدث خطأ»');
$stq = $conn->query("SELECT state FROM settlements WHERE id={$SID}")->fetch_assoc();
check((string) $stq['state'] === 'review', 'والتسويةُ **لم تتغير حالتُها** بالرفض');

$setMode('monitor');
$o = $probe("\$g = \\App\\Services\\Supplier\\SupplierDocumentService::gateFor(\$gate, {$SUP});"
          . " echo (\$g['blocked'] ? 'blocked' : 'passed') . ':' . count(\$g['reasons']);");
check($o === 'passed:1',
      '★ وفي monitor: **يُقاس ويُعلَن ويمرّ** — لا يتجمّد مسارٌ حيٌّ في يومه الأول');
$o = $probe("\$r = \\App\\Services\\Settlement\\SettlementService::approve(\$gate, \$conn, {$SID}, 902);"
          . " echo \$r['ok'] ? 'ok' : (\$r['code'] . '|' . \$r['reason']);");
check($o === 'ok', '★★ والاعتمادُ يقع في monitor رغم الوثيقة المنتهية (وُجد ' . $o . ')');

// ═══ ⑥ سجلٌّ واحدٌ يُقرأ ═══
head('⑥ **سجلٌّ واحدٌ يُقرأ** — لا سجلَّ تدقيقٍ ثانٍ');

$log = SDS::auditOf($conn, $CO, $SUP, 50);
check(count($log) > 0, 'سجلُّ تدقيق المورد يقرأ من `activity_logs` (' . count($log) . ' صفًّا)');
$hasBank = false;
foreach ($log as $l) {
    if ((string) $l['screen_name'] === 'suppliers' && (string) $l['action_type'] === 'verify_bank') { $hasBank = true; }
}
check($hasBank, '★ وواقعةُ **توثيق الحساب** فيه بقيم قبل/بعد — «من غيّر ماذا ومتى»');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
