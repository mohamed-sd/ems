<?php
/**
 * tests/unit_chain_e2e_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ السلسلة كاملةً من الشاشات الحقيقية:
 *   ① يومُ دوامٍ يُكتب بالكاتب الحي (الخدمة + المرآة القانونية)
 *   ② الاعتماداتُ الأربعة بحسابات أصحابها الحقيقيين (الأدوار 1·2·3·4) عبر HTTP
 *   ③ بوابةُ التحويل: لا مالَ بعد الاعتماد الرابع (EMS_UNIT_CONVERT_GATE=on)
 *   ④ المدير الماليُّ يحوّل من «وحدات الأطراف» — فتُولَّد المروحة
 *   ⑤ الفحصُ الموضوعي: مستحقُّ المورد · مستحقُّ المشغّل · الإيراد · التكلفة ·
 *      أحكامُ الأطراف الثلاثة
 *   ⑥ ذمّةُ العميل: هل تُولَّد من المروحة أم من المستخلص؟ (المسار الحقيقي)
 * ثم يكنس كلَّ ما بذر — لا يمسّ بياناتك.
 *
 * التشغيل: php tests/unit_chain_e2e_proof.php   (يتطلب Apache + MySQL حيَّين)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Unit/TimesheetEntryService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Unit\TimesheetEntryService;

$BASE = 'http://localhost/ems';
$TMPD = sys_get_temp_dir();
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; echo "  ✔ {$m}\n"; }
function bad($m) { global $FAIL; $FAIL++; echo "  ✘ FAIL: {$m}\n"; }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { echo "\n── {$m}\n"; }
function info($m) { echo "     · {$m}\n"; }

// ── طبقة HTTP ─────────────────────────────────────────────────────────────
function hx($url, $jar, $post = null, $ajax = false) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60,
    ));
    // معالجاتُ AJAX محروسةٌ بترويسة الطلب غير المتزامن (config.php §الحارس)
    if ($ajax) { curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Requested-With: XMLHttpRequest')); }
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}
function tok($body, $field = 'csrf_token') {
    return preg_match('~name="' . $field . '"\s+value="([^"]+)"~', $body, $m) ? $m[1] : '';
}
function login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = hx($BASE . '/login.php', $jar);
    return hx($BASE . '/login.php', $jar, array(
        'username' => $user, 'password' => '12345678', 'csrf_token' => tok($b)));
}
function loc_msg($h) {
    if (preg_match('~Location:\s*(\S+)~i', $h, $m)) {
        parse_str((string) parse_url(trim($m[1]), PHP_URL_QUERY), $p);
        return isset($p['msg']) ? $p['msg'] : '';
    }
    return '';
}
/* ◆ **رسالةُ الشاشةِ وميضُ جلسةٍ لا مُعامَلُ URL**: `ems_gov_flash_redirect`
     (`includes/permissions_helper.php:139`) يخزّن النصَّ في
     `$_SESSION['ems_flash_gov']` ثم يوجّه **بعنوانٍ مجرَّد**. فكان `loc_msg`
     يعود فارغًا **دائمًا**، فيُقرأ صمتُ المُعامَلِ فشلًا في الفعل — والفعلُ
     ناجحٌ ومالُه مولَّدٌ. فيُتبَع التوجيهُ ويُقرأ النصُّ من الصفحةِ التالية،
     وهو عينُ ما يراه المستخدم. */
function flash_text($h, $jar, $dir) {
    global $BASE;
    if (!preg_match('~Location:\s*(\S+)~i', $h, $m)) { return ''; }
    $to = trim($m[1]);
    if (strpos($to, 'http') !== 0) { $to = rtrim($dir, '/') . '/' . ltrim($to, '/'); }
    list($c, $hh, $b) = hx($to, $jar);
    // نصُّ الوميضِ يُصيَّر داخل الصفحة — يُستخرَج مُنقًّى من الوسوم
    $plain = preg_replace('~\s+~u', ' ', strip_tags($b));
    return (string) $plain;
}

// ── قاعدة البيانات ────────────────────────────────────────────────────────
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_APP_USER'), ems_env('DB_APP_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "FATAL: db connect (app)\n"); exit(1); }
$conn->set_charset('utf8mb4');
$root = new mysqli(ems_env('DB_HOST'), 'root', '', ems_env('DB_NAME'));
if ($root->connect_errno) { fwrite(STDERR, "FATAL: db connect (root)\n"); exit(1); }
$root->set_charset('utf8mb4');

$CO = 4;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, 0, '', true));
$TAG = 'E2E' . getmypid();
$WORK_DATE = '2026-07-20';
$ETYPE = 77;                 // نوع معدةٍ لا يصطدم بالبيانات الحقيقية
$P_CLIENT = 3500;            // سعر ساعة عقد العميل  (جنيه)
$P_SUPPLIER = 2200;          // سعر ساعة عقد المورد  (جنيه)
$H_WORK = 8.0;               // ساعاتُ تشغيلٍ فعلية
$H_STANDBY = 1.0;            // ساعةُ استعداد
$R_OP_ACTUAL = 150;          // معدّلُ المشغّل عن الساعة الفعلية
$R_OP_STANDBY = 50;          // معدّلُه عن ساعة الاستعداد

function snap($c) {
    $g = function ($sql) use ($c) { return intval($c->query($sql)->fetch_row()[0]); };
    return array(
        'events' => $g("SELECT COUNT(*) FROM fin_financial_events"),
        'dues'   => $g("SELECT COUNT(*) FROM fin_dues"),
        'costs'  => $g("SELECT COUNT(*) FROM fin_cost_records"),
        'links'  => $g("SELECT COUNT(*) FROM fin_event_links"),
        'roots'  => $g("SELECT COUNT(*) FROM ems_business_events"),
        'awards' => $g("SELECT COUNT(*) FROM unit_party_awards"),
        'recv'   => $g("SELECT COUNT(*) FROM fin_receivables"),
        'claims' => $g("SELECT COUNT(*) FROM claims"),
    );
}
$c0 = snap($root);

$seed = array('client' => array(), 'supplier' => array(), 'project' => array(), 'equip' => array(),
              'employee' => array(), 'contracts' => array(), 'ce' => array(),
              'sup_contracts' => array(), 'sce' => array(), 'ops' => array(),
              'ts' => array(), 'policies' => array(), 'claims' => array());
function sq($root, $sql, &$bucket) {
    if (!$root->query($sql)) { throw new \RuntimeException('فشل بذر: ' . $root->error . ' ← ' . substr($sql, 0, 140)); }
    $id = intval($root->insert_id);
    if ($id <= 0) { throw new \RuntimeException('بذرٌ بلا معرّفٍ وليد ← ' . substr($sql, 0, 140)); }
    $bucket[] = $id;
    return $id;
}

$TS = 0; $ENTRY = 0;
try {

// ═══════════════════════════════════════════════════════════════════════════
head('① بذرُ عالَمٍ كاملٍ مستقل (عميل · مشروع · مورد · معدة · مشغّل · عقدان)');
// ═══════════════════════════════════════════════════════════════════════════
/* ◆ **البذرُ يملأ كلَّ عمودٍ يحمل قيدًا فريدًا.** `clients` عليه
     `uq_clients_company_code (company_id, client_code)`، والبذرُ كان يُغفل
     `client_code` فيأخذ `''` — فأولُ بذرةٍ تمرُّ **وكلُّ ما بعدها يُردّ**
     بـ`Duplicate entry '4-'`. والقيدُ محقٌّ: عميلانِ بلا رمزٍ في شركةٍ واحدةٍ
     لا يتمايزان. فيُوسَم الرمزُ بوسمِ الجولةِ كبقيةِ العالَمِ المبذور. */
$CL  = sq($root, "INSERT INTO clients (company_id, client_name, client_code, phone, status)
                  VALUES ($CO, '$TAG-عميل', '$TAG-CL', '000', 1)", $seed['client']);
$SUP = sq($root, "INSERT INTO suppliers (company_id, name, phone, status)
                  VALUES ($CO, '$TAG-مورد', '000', 1)", $seed['supplier']);
$PRJ = sq($root, "INSERT INTO project (company_id, name, client, client_id, location, total, status)
                  VALUES ($CO, '$TAG-مشروع', '$TAG-عميل', $CL, 'اختبار', '0', 1)", $seed['project']);
$EQ  = sq($root, "INSERT INTO equipments (company_id, name, code, type, suppliers, status)
                  VALUES ($CO, '$TAG-معدة', '$TAG-code', '$ETYPE', '$SUP', 1)", $seed['equip']);
$EMP = sq($root, "INSERT INTO employees (company_id, name, phone, employee_status, status)
                  VALUES ($CO, '$TAG-مشغل', '000', 'نشط', 1)", $seed['employee']);

$CCON = sq($root, "INSERT INTO contracts (company_id, project_id, contract_signing_date, price_currency_contract, status)
                   VALUES ($CO, $PRJ, '2026-01-01', 'جنيه', 1)", $seed['contracts']);
sq($root, "INSERT INTO contractequipments (company_id, contract_id, equip_type, equip_unit, equip_price, equip_price_currency)
           VALUES ($CO, $CCON, '$ETYPE', 'ساعة', $P_CLIENT, 'جنيه')", $seed['ce']);

$SCON = sq($root, "INSERT INTO supplierscontracts (company_id, supplier_id, project_id, contract_signing_date,
                   price_currency_contract, actual_start, actual_end, status)
                   VALUES ($CO, $SUP, $PRJ, '2026-01-01', 'جنيه', '2026-01-01', '2026-12-31', 1)", $seed['sup_contracts']);
sq($root, "INSERT INTO suppliercontractequipments (company_id, contract_id, equip_type, equip_unit, equip_price, equip_price_currency)
           VALUES ($CO, $SCON, '$ETYPE', 'ساعة', $P_SUPPLIER, 'جنيه')", $seed['sce']);

$OP = sq($root, "INSERT INTO operations (company_id, project_id, equipment, equipment_type, equipment_category,
                 contract_id, supplier_id, `start`, `end`, reason, days, status)
                 VALUES ($CO, '$PRJ', '$EQ', '$ETYPE', 'اختبار', '$CCON', '$SUP',
                         '2026-01-01', '2026-12-31', 'بذر برهان السلسلة', '30', 1)", $seed['ops']);

// سياساتُ المشغّل (UX-02 §8.2) — بلا سياسةٍ لا مستحقَّ مشغّل (قاعدة عدم التلفيق)
sq($root, "INSERT INTO contract_hour_policies (company_id, party_scope, operator_id, pay_basis, work_model,
           rate, currency, ruling, effective_from, effective_to, note, policy_state)
           VALUES ($CO, 'operator', $EMP, 'actual', 'hour', $R_OP_ACTUAL, 'SDG', 'full',
                   '2026-07-01', '2026-07-31', '$TAG', 'active')", $seed['policies']);
sq($root, "INSERT INTO contract_hour_policies (company_id, party_scope, operator_id, pay_basis, work_model,
           rate, currency, ruling, effective_from, effective_to, note, policy_state)
           VALUES ($CO, 'operator', $EMP, 'standby', 'hour', $R_OP_STANDBY, 'SDG', 'full',
                   '2026-07-01', '2026-07-31', '$TAG', 'active')", $seed['policies']);
ok("العالَم مبذور: عميل#$CL مشروع#$PRJ مورد#$SUP معدة#$EQ مشغّل#$EMP عقدُ عميل#$CCON عقدُ مورد#$SCON تشغيل#$OP");
info("عقد العميل: ساعة × $P_CLIENT جنيه · عقد المورد: ساعة × $P_SUPPLIER جنيه · سياسةُ المشغّل: فعلي $R_OP_ACTUAL · استعداد $R_OP_STANDBY");

// ═══════════════════════════════════════════════════════════════════════════
head('② كتابةُ يوم الدوام بالكاتب الحي + مرآتُه في السجل القانوني');
// ═══════════════════════════════════════════════════════════════════════════
$gate->runInTransaction(function ($g) use (&$TS, $CO, $OP, $EMP, $WORK_DATE, $ETYPE, $H_WORK, $H_STANDBY, $TAG) {
    $TS = intval($g->insert('timesheet', array(
        'operator' => strval($OP), 'employee_id' => strval($EMP), 'shift' => 'ص',
        'date' => $WORK_DATE, 'type' => strval($ETYPE), 'time_notes' => $TAG,
        'executed_hours' => $H_WORK, 'standby_hours' => $H_STANDBY,
        'total_work_hours' => $H_WORK + $H_STANDBY, 'shift_hours' => $H_WORK + $H_STANDBY,
        'meters_count' => 0, 'tons_count' => 0, 'operator_hours' => $H_WORK,
        'status' => 1,
    )));
});
$seed['ts'][] = $TS;
check($TS > 0, "صفُّ الدوام الحي مكتوب: TS-$TS ($H_WORK ساعة تشغيل + $H_STANDBY استعداد · المشغّل #$EMP)");

$mir = TimesheetEntryService::mirrorFromTimesheet($conn, $gate, $TS, 4);
check(!empty($mir['ok']), 'المرآةُ القانونية أُنشئت (unit_entries + unit_time_log)');
$ENTRY = isset($mir['entry_id']) ? intval($mir['entry_id']) : 0;
$er = $root->query("SELECT entry_no, state, unit_type, qty, capacity_flag FROM unit_entries WHERE id=$ENTRY")->fetch_assoc();
info("الواقعة: {$er['entry_no']} · حالة {$er['state']} · {$er['qty']} {$er['unit_type']} · علمُ طاقة=" . intval($er['capacity_flag']));
$tl = $root->query("SELECT ops_state, SUM(hours) h FROM unit_time_log WHERE entry_id=$ENTRY GROUP BY ops_state ORDER BY ops_state")->fetch_all(MYSQLI_ASSOC);
$tlTxt = array(); foreach ($tl as $l) { $tlTxt[] = $l['ops_state'] . '=' . rtrim(rtrim($l['h'], '0'), '.'); }
info('سطورُ الزمن: ' . implode(' · ', $tlTxt));
check(count($tl) === 2, 'زمنُ الوردية موزَّعٌ على حالتيه (تشغيل فعلي · استعداد)');

// ═══════════════════════════════════════════════════════════════════════════
head('③ الاعتماداتُ الخمسة من الشاشة الحقيقية بحسابات أصحابها');
// ═══════════════════════════════════════════════════════════════════════════
/* ◆ **اليدُ الخامسةُ أُضيفت لأنها وُصِلت في المنتج** (قرارُ المالك 2026-08-12):
     كانت مرحلةُ `sales` مبنيةً في `TimesheetEntryService` بلا مُستهلِك، فما بلغ
     يومٌ `sales_approved` من الشاشات — و«وحدات الأطراف» تشترطها للتحويل، فكان
     هذا الفاحصُ يرسب عند ⑤ **بحقٍّ**: الطابورُ خالٍ لأن السلسلةَ لم تكتمل.
     والمواصفةُ تنصُّ على خمسِ أيدٍ (`SPEC_TIMESHEET_CYCLE §TS-13`). */
$approvers = array(
    1 => array('محمد',  'مدير المشاريع / التشغيل'),
    2 => array('مصعب',  'مدير الموردين'),
    3 => array('يسن',   'مدير الأسطول'),
    4 => array('اروينا', 'مدير المشغلين'),
    5 => array('مشرف المبيعات', 'مدير المبيعات — بوابةُ §④ التجارية'),
);
foreach ($approvers as $lvl => $who) {
    $jar = $TMPD . "/e2e_ap{$lvl}_" . getmypid() . '.txt';
    login($who[0], $jar);
    list($c, $h, $b) = hx($BASE . '/Approvals/hours_approval.php', $jar);
    $t = tok($b);
    list($c2, $h2, $b2) = hx($BASE . '/Approvals/hours_approval_handler.php', $jar,
        array('action' => 'approve', 'ids' => strval($TS), 'csrf_token' => $t), true);
    $j = json_decode($b2, true);
    $okAppr = is_array($j) && !empty($j['success']) && intval($j['approved']) === 1;
    check($okAppr, "المستوى $lvl ({$who[1]} · {$who[0]}): " . (is_array($j) ? (string) $j['message'] : trim(substr($b2, 0, 120))));
    @unlink($jar);
}
$lv = $root->query("SELECT COUNT(*) n FROM timesheet_approvals WHERE timesheet_id=$TS AND status=1")->fetch_assoc();
check(intval($lv['n']) === 5, 'خمسةُ اعتماداتٍ مسجَّلةٌ في السلسلة');
$mst = $root->query("SELECT state FROM unit_entries WHERE id=$ENTRY")->fetch_assoc();
info('حالةُ الواقعة في السجل القانوني بعد المرآة: ' . $mst['state']);

// ═══════════════════════════════════════════════════════════════════════════
head('④ بوابةُ التحويل: هل خُلق مالٌ بمجرد اكتمالِ السلسلة؟');
// ═══════════════════════════════════════════════════════════════════════════
$c1 = snap($root);
$preLinks = intval($root->query("SELECT COUNT(*) FROM fin_event_links
                                  WHERE parent_kind='timesheet' AND parent_ref=$TS")->fetch_row()[0]);
check($c1['dues'] === $c0['dues'] && $c1['costs'] === $c0['costs'] && $preLinks === 0,
    'لا مستحقَّ ولا تكلفةَ ولا رابطَ أثرٍ باكتمالِ السلسلة — EMS_UNIT_CONVERT_GATE=on يحجز المال لختم المالية');
// الحقيقةُ المحايدة تُسقِط صفًّا دفتريًّا بمبلغ 0 (وصفُ الواقعة لا مالُها)
$factProj = $root->query("SELECT event_type, amount, quantity, unit FROM fin_financial_events
                           WHERE entity_type='timesheet' AND entity_id=$TS")->fetch_assoc();
if ($factProj) {
    info("إسقاطُ الحقيقة في الدفتر: {$factProj['event_type']} · مبلغ {$factProj['amount']} · {$factProj['quantity']} {$factProj['unit']} (وصفٌ لا مال)");
}
$rootFact = $root->query("SELECT event_key FROM ems_business_events
                           WHERE entity_type='timesheet' AND entity_id=$TS")->fetch_all(MYSQLI_ASSOC);
$keys = array(); foreach ($rootFact as $f) { $keys[] = $f['event_key']; }
check(in_array('equipment.hour_logged', $keys, true),
    'حقيقةُ الجذر نُشرت على الناقل: ' . implode(' · ', $keys));

// ═══════════════════════════════════════════════════════════════════════════
head('⑤ المدير الماليُّ يحوّل من شاشة «وحدات الأطراف»');
// ═══════════════════════════════════════════════════════════════════════════
$fjar = $TMPD . '/e2e_fin_' . getmypid() . '.txt';
login('مديرمالي', $fjar);   // دور 17 — المدير المالي (الحسابُ الحقيقي في القاعدة)
/* ◆ **الطابورُ صفحةٌ لا كونٌ**: `unit_records_fin.php:128` يطلبه بـ`limit => 200`
     بلا مدًى زمنيٍّ افتراضيّ، وفي القاعدة **1500 واقعةٍ** بلغت `sales_approved`.
     فيومُ البذرِ الجديدُ يقع خارج الصفحةِ الأولى — **والشاشةُ محقّةٌ**، والفاحصُ
     كان يقرأ غيابَه عن صفحةٍ غيابًا عن الطابور. فيُطلَب الطابورُ بنطاقِ مشروعِ
     البذرِ نفسِه (مُرشِّحٌ تدعمه الشاشةُ سلفًا: `q_project`) — قياسٌ محدَّدٌ لا
     صيدٌ في مئتين. */
$qs = '?q_project=' . $PRJ . '&q_period=' . date('Y-m', strtotime($WORK_DATE));
list($c, $h, $b) = hx($BASE . '/Finance/unit_records_fin.php' . $qs, $fjar);
check(strpos($b, 'TS-' . $TS) !== false || strpos($b, 'value="' . $TS . '"') !== false,
    "اليومُ ظاهرٌ في طابور التحويل أمام المدير المالي (TS-$TS · مشروع#$PRJ)");
$ftok = tok($b);
check($ftok !== '', 'رمزُ الحماية مُحقَنٌ في الشاشة');
list($cc, $hh, $bb) = hx($BASE . '/Finance/unit_records_fin.php', $fjar,
    array('action' => 'convert_units', 'ids' => strval($TS), 'csrf_token' => $ftok));
/* ◆ **العلامةُ في المسارِ والرسالةُ في الوميض**: الشاشةُ تُلحق `✅` بعنوانِ
     التحويلِ عبر `ems_flash_to(..., '+✅')` — مُعامَلًا في الـURL — وتضع النصَّ
     في وميضِ الجلسة. فيُقاس الاثنان في موضعِهما. */
$hasMark = (strpos($hh, '✅') !== false) || (strpos(rawurldecode($hh), '✅') !== false);
$fflash  = flash_text($hh, $fjar, $BASE . '/Finance');
$saidOne = (mb_strpos($fflash, 'اعتُمدت أحكامُ 1 يومًا') !== false);
info('علامةُ الترويسة: ' . ($hasMark ? '✅' : '—') . ' · وميضُ الشاشة: '
    . ($saidOne ? 'اعتُمدت أحكامُ 1 يومًا' : mb_substr(trim($fflash), 0, 120)));
/* ◆ الشاهدُ هو **علامةُ فرعِ النجاحِ في الترويسة**: الشاشةُ لا تُلحق `✅` إلا
     من فرعِ `$okCount > 0` (`unit_records_fin.php:92`) — فوجودُها إعلانُ الشاشةِ
     نفسِها أنها حوّلت يومًا. ونصُّ الوميضِ يُطبع إعلامًا لا حكمًا، لأن تصييرَه
     يمرُّ بطبقةِ التنبيهاتِ الموحَّدةِ وليست هي المفحوصَ هنا. والمالُ المولَّدُ
     يُقاس موضوعيًّا في §⑥ صفًّا صفًّا. */
check($hasMark, 'التحويلُ نُفِّذ من الشاشة الحقيقية — علامةُ فرعِ النجاحِ في ترويسةِ التوجيه');
@unlink($fjar);

// ═══════════════════════════════════════════════════════════════════════════
head('⑥ الفحصُ الموضوعي: ما وُلِّد فعلًا في المالية؟');
// ═══════════════════════════════════════════════════════════════════════════
$links = $root->query("SELECT effect_type, target_table, target_id FROM fin_event_links
                        WHERE parent_kind='timesheet' AND parent_ref=$TS ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$byType = array();
foreach ($links as $l) { $byType[$l['effect_type']] = $l; }
info('الآثارُ المربوطة: ' . implode(' · ', array_keys($byType)));

// (أ) إيرادُ العميل
$rev = null;
if (isset($byType['revenue_event'])) {
    $rev = $root->query("SELECT id, event_type, amount, currency, quantity, unit, customer_entity_id,
                                supplier_entity_id, operator_employee_id, project_id
                           FROM fin_financial_events WHERE id=" . intval($byType['revenue_event']['target_id']))->fetch_assoc();
}
check($rev !== null, 'أثرُ الإيراد مولَّد (fin_financial_events)');
if ($rev) {
    info("الإيراد: {$rev['amount']} {$rev['currency']} · {$rev['quantity']} {$rev['unit']}");
    check(abs((float) $rev['amount'] - ($H_WORK * $P_CLIENT)) < 0.01,
        'مبلغُ الإيراد = 8 ساعات مفوترة × 3500 = ' . number_format($H_WORK * $P_CLIENT, 2) . ' (ساعةُ الاستعداد استُبعدت بسياسة العقد لا بالصمت)');
    check(intval($rev['customer_entity_id']) === $CL,
        'قيدُ الإيراد يسمّي مَن يُحصَّل منه (customer_entity_id = العميل #' . $CL . ')');
}

// (ب) مستحقُّ المورد
$sd = null;
if (isset($byType['supplier_due'])) {
    $sd = $root->query("SELECT id, party_type, party_ref, due_type, direction, amount, currency, period_ref, event_id
                          FROM fin_dues WHERE id=" . intval($byType['supplier_due']['target_id']))->fetch_assoc();
}
check($sd !== null, 'مستحقُّ المورد مولَّد (fin_dues)');
if ($sd) {
    info("مستحقُّ المورد: {$sd['amount']} {$sd['currency']} · {$sd['party_type']}#{$sd['party_ref']} · {$sd['due_type']}/{$sd['direction']} · فترة {$sd['period_ref']}");
    check(intval($sd['party_ref']) === $SUP && abs((float) $sd['amount'] - ($H_WORK * $P_SUPPLIER)) < 0.01,
        'المبلغ = 8 × 2200 = ' . number_format($H_WORK * $P_SUPPLIER, 2) . ' باسم المورد الصحيح');
}

// (ج) مستحقُّ المشغّل
$ed = null;
if (isset($byType['employee_due'])) {
    $ed = $root->query("SELECT id, party_type, party_ref, due_type, amount, currency, period_ref
                          FROM fin_dues WHERE id=" . intval($byType['employee_due']['target_id']))->fetch_assoc();
}
check($ed !== null, 'مستحقُّ المشغّل مولَّد (fin_dues · party_type=employee)');
if ($ed) {
    $expect = $H_WORK * $R_OP_ACTUAL + $H_STANDBY * $R_OP_STANDBY;
    info("مستحقُّ المشغّل: {$ed['amount']} {$ed['currency']} · {$ed['party_type']}#{$ed['party_ref']} · {$ed['due_type']}");
    check(intval($ed['party_ref']) === $EMP && abs((float) $ed['amount'] - $expect) < 0.01,
        "المبلغ = (8×$R_OP_ACTUAL) + (1×$R_OP_STANDBY) = " . number_format($expect, 2)
        . ' — المشغّل يستحقُّ عن ساعة استعدادٍ لا يُفوتَر بها العميل (§8.2)');
}

// (د) التكلفة والربحية
$cr = null;
if (isset($byType['cost_record'])) {
    $cr = $root->query("SELECT cost_type, qty, unit, unit_cost, total_cost, revenue, profit, currency
                          FROM fin_cost_records WHERE id=" . intval($byType['cost_record']['target_id']))->fetch_assoc();
}
check($cr !== null, 'سجلُّ التكلفة مولَّد (fin_cost_records)');
if ($cr) {
    info("التكلفة: {$cr['total_cost']} · الإيراد: {$cr['revenue']} · الربح: {$cr['profit']} {$cr['currency']} ({$cr['cost_type']})");
    check(abs((float) $cr['profit'] - (($H_WORK * $P_CLIENT) - ($H_WORK * $P_SUPPLIER))) < 0.01,
        'الربحُ محسوبٌ في القاعدة = ' . number_format(($H_WORK * $P_CLIENT) - ($H_WORK * $P_SUPPLIER), 2));
}

// (هـ) أحكامُ الأطراف الثلاثة
$aws = $root->query("SELECT party, party_ref, award_unit_type, award_qty, qty_due, entitlement_state,
                            unit_price, currency, policy_rule, unavailable_reason
                       FROM unit_party_awards WHERE source_kind='timesheet' AND source_ref=$TS
                      ORDER BY FIELD(party,'client','supplier','operator')")->fetch_all(MYSQLI_ASSOC);
check(count($aws) === 3, 'ثلاثةُ أحكامٍ مكتوبة (عميل · مورد · مشغّل) — بطاقاتُ UX-02 §8.1');
foreach ($aws as $a) {
    info("حكمُ {$a['party']}#{$a['party_ref']}: {$a['qty_due']} {$a['award_unit_type']} × {$a['unit_price']} {$a['currency']}"
        . " · {$a['entitlement_state']} · {$a['policy_rule']}" . ($a['unavailable_reason'] ? " · تعذّر: {$a['unavailable_reason']}" : ''));
}

// (و) ذمّةُ العميل — هل تولّدها المروحة؟
$clientDue = $root->query("SELECT COUNT(*) n FROM fin_dues d
                            JOIN fin_event_links l ON l.target_table='fin_dues' AND l.target_id=d.id
                           WHERE l.parent_kind='timesheet' AND l.parent_ref=$TS
                             AND d.party_type NOT IN ('supplier','employee','proc_supplier')")->fetch_assoc();
check(intval($clientDue['n']) === 0,
    'المروحةُ لا تفتح ذمّةَ عميلٍ (fin_dues لا يحمل نوعَ طرفٍ «عميل» أصلًا) — الذمّةُ بابُها المستخلص');

// ═══════════════════════════════════════════════════════════════════════════
head('⑦ ذمّةُ العميل: المبيعاتُ تُنشئ وترفع · والمالية تُجيز');
// ═══════════════════════════════════════════════════════════════════════════
$sjar = $TMPD . '/e2e_sales_' . getmypid() . '.txt';
login('مبيعات', $sjar);
list($c, $h, $b) = hx($BASE . '/Contracts/claims.php', $sjar);
$ctok = tok($b, 'clm_csrf');
/* ◆ **رمزان لا رمز**: الشاشةُ تحقن رمزَها `clm_csrf` **والرمزَ المركزيَّ**
     `csrf_token` معًا، والحارسُ المركزيُّ (ADR-05) يقيس المركزيَّ حصرًا.
     فطلبٌ يحمل رمزَ الشاشةِ وحدَه يُردُّ **403 «فشل التحقق الأمني»** بلا
     وميضٍ ولا جسمٍ — فيُقرأ صمتُه «لم يُولَّد مستخلص» والفعلُ لم يبدأ أصلًا. */
$gtok = tok($b);
check($ctok !== '', 'شاشةُ المستخلصات مفتوحةٌ لمسؤول المبيعات (دور 12)');
list($cg, $hg, $bg) = hx($BASE . '/Contracts/claims.php', $sjar, array(
    'action' => 'generate', 'contract_id' => strval($CCON),
    'period_from' => '2026-07-01', 'period_to' => '2026-07-31',
    'clm_csrf' => $ctok, 'csrf_token' => $gtok));
$gflash = flash_text($hg, $sjar, $BASE . '/Contracts');
if (preg_match('~(تعذّر[^.]{0,180}|لا يوجد[^.]{0,180}|وُلِّد[^.]{0,180}|أُنشئ[^.]{0,180})~u', $gflash, $gm)) {
    info('توليدُ المستخلص: ' . trim($gm[1]));
} else {
    info('توليدُ المستخلص: (لا وميضَ مقروء · طولُ الصفحة ' . strlen($gflash) . ')');
}
$clm = $root->query("SELECT id, claim_no, state, gross_amount FROM claims
                      WHERE contract_id=$CCON ORDER BY id DESC LIMIT 1")->fetch_assoc();
if ($clm) { $seed['claims'][] = intval($clm['id']); }
check($clm && abs((float) $clm['gross_amount'] - ($H_WORK * $P_CLIENT)) < 0.01,
    'المستخلصُ وُلّد من اليوم المحوَّل نفسِه بإجمالي ' . ($clm ? $clm['gross_amount'] : '—'));
if ($clm) {
    $lnk = $root->query("SELECT event_id, qty, unit_price, amount FROM claim_lines
                          WHERE claim_id=" . intval($clm['id']))->fetch_assoc();
    check($lnk && $rev && intval($lnk['event_id']) === intval($rev['id']),
        'بندُ المستخلص مرجعُه قيدُ الإيراد نفسُه (event_id=' . ($lnk ? $lnk['event_id'] : '—') . ') — لا إيرادٌ ثانٍ');
    check($lnk && abs((float) $lnk['qty'] - (float) $rev['quantity']) < 0.01,
        'وكميتُه هي الكميةُ المحكومة نفسُها — فلا يفترق المستخلصُ عن الإيراد');
}

// المبيعاتُ لا تُجيز ما أنشأت (فصلُ اليدين في المنح)
if ($clm) {
    list($cx, $hx_, $bx) = hx($BASE . '/Contracts/claims.php', $sjar, array(
        'action' => 'approve', 'id' => strval($clm['id']), 'clm_csrf' => $ctok, 'csrf_token' => $gtok));
    /* الرسالةُ وميضُ جلسةٍ — تُقرأ من الصفحةِ التالية لا من مُعامَلٍ لا وجودَ له */
    $denied = flash_text($hx_, $sjar, $BASE . '/Contracts');
    $deniedHit = (mb_strpos($denied, 'لا تُجيز ما أنشأت') !== false)
        || (mb_strpos($denied, 'ليست لك') !== false)
        || (mb_strpos($denied, '❌') !== false)
        || (mb_strpos($denied, 'صلاحية') !== false);
    $stAfter = $root->query("SELECT state FROM claims WHERE id=" . intval($clm['id']))->fetch_assoc();
    // الحكمُ الحقيقيُّ: الحالةُ لم تتقدّم إلى إجازةٍ بيدِ من أنشأ
    check($stAfter && $stAfter['state'] !== 'approved' && $stAfter['state'] !== 'invoiced',
        'المبيعاتُ مُنعت من الإجازة — الحالةُ باقيةٌ «' . ($stAfter ? $stAfter['state'] : '—') . '»'
        . ($deniedHit ? ' (والرسالةُ تُعلن السبب)' : ''));

    list($cs, $hs, $bs) = hx($BASE . '/Contracts/claims.php', $sjar, array(
        'action' => 'submit', 'id' => strval($clm['id']), 'clm_csrf' => $ctok, 'csrf_token' => $gtok));
    info('رفعُ المبيعات للمالية: ' . loc_msg($hs));
    $st = $root->query("SELECT state, submitted_by FROM claims WHERE id=" . intval($clm['id']))->fetch_assoc();
    check($st && $st['state'] === 'review' && !empty($st['submitted_by']),
        'المستخلصُ في مراجعة المالية باسم رافعه (u' . ($st ? $st['submitted_by'] : '—') . ')');
}
@unlink($sjar);

// يدُ المالية: الإجازةُ تفتح الذمّة
if ($clm) {
    $fjar2 = $TMPD . '/e2e_fin2_' . getmypid() . '.txt';
    login('fin.deptmgr@equipation.sd', $fjar2);   // دور 19 — يدُ الإجازة المالية
    list($c2, $h2, $b2) = hx($BASE . '/Contracts/claims.php', $fjar2);
    $ctok2 = tok($b2, 'clm_csrf');
    $gtok2 = tok($b2);
    check($ctok2 !== '', 'الشاشةُ مفتوحةٌ لمدير الإدارة المالية (دور 19)');
    list($ca, $ha, $ba) = hx($BASE . '/Contracts/claims.php', $fjar2, array(
        'action' => 'approve', 'id' => strval($clm['id']), 'clm_csrf' => $ctok2, 'csrf_token' => $gtok2));
    info('إجازةُ المالية: ' . loc_msg($ha));
    $c2r = $root->query("SELECT c.claim_no, c.state, c.invoice_no, c.receivable_id, c.approved_by,
                                r.customer_entity_id, r.amount, r.outstanding, r.state AS rstate
                           FROM claims c LEFT JOIN fin_receivables r ON r.id = c.receivable_id
                          WHERE c.id=" . intval($clm['id']))->fetch_assoc();
    check($c2r && !empty($c2r['receivable_id']),
        'ذمّةُ العميل فُتحت بختم المالية (fin_receivables) — فاتورة ' . ($c2r ? $c2r['invoice_no'] : '—'));
    if ($c2r && !empty($c2r['receivable_id'])) {
        info("الذمّة: عميل#{$c2r['customer_entity_id']} · {$c2r['amount']} · غيرُ محصَّل {$c2r['outstanding']} · {$c2r['rstate']}");
        check(intval($c2r['customer_entity_id']) === $CL, 'الذمّةُ باسم عميل المشروع الصحيح');
    }
    @unlink($fjar2);
}

// ── ⑦-أ ازدواجُ الإيراد: هل يُقيَّد اليومُ الواحد إيرادًا مرتين؟ ──
// المروحةُ تنشر revenue.unit.recognized والمستخلصُ ينشر revenue.claim.approved،
// وكلاهما يُسقِط صفًّا بـevent_type='revenue' في الدفتر عن **الساعات نفسها**.
$revRows = $root->query("SELECT id, event_type, entity_type, entity_id, amount, source_ref
                           FROM fin_financial_events
                          WHERE event_type='revenue' AND project_id=$PRJ ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$revSum = 0.0;
foreach ($revRows as $r) {
    $revSum += (float) $r['amount'];
    info("قيدُ إيراد: #{$r['id']} · {$r['entity_type']}#{$r['entity_id']} · {$r['amount']} · {$r['source_ref']}");
}
check(count($revRows) === 1,
    'قيدُ إيرادٍ واحدٌ لليوم الواحد في الدفتر — المجموع ' . number_format($revSum, 2)
    . ' مقابل ' . number_format($H_WORK * $P_CLIENT, 2) . ' المستحقة فعلًا'
    . (count($revRows) > 1 ? ' [' . count($revRows) . ' قيودٍ = ازدواج]' : ''));

// ── ⑦-ب هل يشترط المستخلصُ الاعتمادَ التشغيلي؟ يومٌ بلا اعتمادٍ واحد ──
// (فحصٌ متعمَّد: `claim_billable_units` تشترط `ts.status=1` وحدها)
$TS2 = 0;
$gate->runInTransaction(function ($g) use (&$TS2, $OP, $EMP, $ETYPE, $TAG) {
    $TS2 = intval($g->insert('timesheet', array(
        'operator' => strval($OP), 'employee_id' => strval($EMP), 'shift' => 'ص',
        'date' => '2026-08-10', 'type' => strval($ETYPE), 'time_notes' => $TAG . '-NOAPPR',
        'executed_hours' => 5, 'standby_hours' => 0, 'total_work_hours' => 5, 'shift_hours' => 5,
        'meters_count' => 0, 'tons_count' => 0, 'operator_hours' => 5, 'status' => 1,
    )));
});
$seed['ts'][] = $TS2;
$sjar2 = $TMPD . '/e2e_sales2_' . getmypid() . '.txt';
login('مبيعات', $sjar2);
list($c3, $h3, $b3) = hx($BASE . '/Contracts/claims.php', $sjar2);
list($cn, $hn, $bn) = hx($BASE . '/Contracts/claims.php', $sjar2, array(
    'action' => 'generate', 'contract_id' => strval($CCON),
    'period_from' => '2026-08-01', 'period_to' => '2026-08-31',
    'clm_csrf' => tok($b3, 'clm_csrf'), 'csrf_token' => tok($b3)));
info('توليدُ مستخلصٍ لشهرٍ فيه يومٌ بلا اعتمادٍ واحد (TS-' . $TS2 . '): ' . loc_msg($hn));
$clm2 = $root->query("SELECT id, claim_no, gross_amount FROM claims
                       WHERE contract_id=$CCON AND period_from='2026-08-01' ORDER BY id DESC LIMIT 1")->fetch_assoc();
if ($clm2) { $seed['claims'][] = intval($clm2['id']); }
check($clm2 === null,
    'المستخلصُ يرفض يومًا لم يكتمل اعتمادُه ولم تحوّله المالية — فلا تتخطّى ذمّةُ العميل السلسلة'
    . ($clm2 ? ' [وُلّد فعلًا بإجمالي ' . $clm2['gross_amount'] . ' من صفٍّ بصفر اعتماد]' : ''));
@unlink($sjar2);

} catch (\Throwable $t) {
    bad('استثناء غير متوقع: ' . $t->getMessage());
    echo $t->getTraceAsString() . "\n";
}

// ═══════════════════════════════════════════════════════════════════════════
head('⑧ الكنس: الإسقاطُ قبل الجذر ثم عالَمُ البذر');
// ═══════════════════════════════════════════════════════════════════════════
/* ◆ **الفاتورةُ الضريبيةُ تُحذف قبل مستخلصِها.** إجازةُ المالية تُصدر صفًّا في
     `tax_invoices` مربوطًا بـ`fk_tax_invoice_claim`، فحذفُ المستخلصِ **يُردّ**
     — والكنسُ لا يفحص مُرجَعَ حذفِه، فيُبلَع الردُّ ويبقى المستخلصُ وذمّتُه.
     ولأن عدّادَ `claims` **داخلٌ** في لقطةِ خطِّ الأساس ظهر الانزياحُ لاحقًا
     (301 ← 302) بلا سببٍ معلَن. فيُحذف الأبعدُ فرعًا أولًا ويُعلَن كلُّ ردّ. */
$clmErr = array();
$cq = function ($sql) use ($root, &$clmErr) {
    if (!$root->query($sql)) { $clmErr[] = mb_substr($root->error, 0, 60); }
};
foreach ($seed['claims'] as $id) {
    $cq("DELETE FROM tax_invoices WHERE claim_id=$id");
    $root->query("DELETE FROM fin_receivables WHERE id IN (SELECT receivable_id FROM claims WHERE id=$id AND receivable_id IS NOT NULL)");
    // ⚠️ الإسقاطُ قبل الجذر: fin_financial_events.root_event_id يشير إلى الحقيقة
    $root->query("DELETE FROM fin_financial_events WHERE entity_type='claim' AND entity_id=$id");
    $root->query("DELETE FROM ems_business_events WHERE entity_type='claim' AND entity_id=$id");
    $root->query("DELETE FROM claim_lines WHERE claim_id=$id");
    $root->query("DELETE FROM claims WHERE id=$id");
}
foreach ($seed['ts'] as $id) {
    $root->query("DELETE FROM fin_cost_records WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref=$id AND target_table='fin_cost_records')");
    $root->query("DELETE FROM fin_dues WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref=$id AND target_table='fin_dues')");
    $root->query("DELETE FROM unit_party_awards WHERE source_kind='timesheet' AND source_ref=$id");
    $root->query("DELETE FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref=$id");
    /* الرابطُ يُحذف قبلَ حدثِه: `fk_fel_event` (RESTRICT) يردُّ حذفَ حدثٍ
       ما زال رابطٌ يشير إليه — هجرة 2027_02_18. */
    $root->query("DELETE FROM fin_financial_events WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref=$id AND target_table='fin_financial_events')");
    // إسقاطُ الحقيقة المحايدة (equipment.hour_logged) — قبل جذرها لا بعده
    $root->query("DELETE FROM fin_financial_events WHERE entity_type='timesheet' AND entity_id=$id");
    $root->query("DELETE FROM ems_business_events WHERE entity_type='timesheet' AND entity_id=$id");
    $root->query("DELETE FROM timesheet_approvals WHERE timesheet_id=$id");
    $root->query("DELETE FROM timesheet_approval_notes WHERE timesheet_id=$id");
    $root->query("DELETE FROM timesheet_failure_hours WHERE timesheet_id=$id");
    $root->query("DELETE FROM timesheet WHERE id=$id");
}
if ($ENTRY > 0) {
    $root->query("DELETE FROM ems_business_events WHERE entity_type='unit_entry' AND entity_id=$ENTRY");
    $root->query("DELETE FROM unit_capacity_flags WHERE entry_id=$ENTRY");
    $root->query("DELETE FROM unit_approvals WHERE entry_id=$ENTRY");
    $root->query("DELETE FROM unit_time_log WHERE entry_id=$ENTRY");
    $root->query("DELETE FROM unit_entries WHERE id=$ENTRY");
}
foreach ($seed['policies'] as $id) { $root->query("DELETE FROM contract_hour_policies WHERE id=$id"); }
foreach ($seed['sce'] as $id) { $root->query("DELETE FROM suppliercontractequipments WHERE id=$id"); }
foreach ($seed['ce'] as $id)  { $root->query("DELETE FROM contractequipments WHERE id=$id"); }
foreach ($seed['sup_contracts'] as $id) { $root->query("DELETE FROM supplierscontracts WHERE id=$id"); }
foreach ($seed['contracts'] as $id) { $root->query("DELETE FROM contracts WHERE id=$id"); }
foreach ($seed['ops'] as $id) { $root->query("DELETE FROM operations WHERE id=$id"); }
foreach ($seed['equip'] as $id) { $root->query("DELETE FROM equipments WHERE id=$id"); }
foreach ($seed['employee'] as $id) { $root->query("DELETE FROM employees WHERE id=$id"); }
foreach ($seed['project'] as $id) { $root->query("DELETE FROM project WHERE id=$id"); }
foreach ($seed['supplier'] as $id) { $root->query("DELETE FROM suppliers WHERE id=$id"); }
foreach ($seed['client'] as $id) { $root->query("DELETE FROM clients WHERE id=$id"); }

$cf = snap($root);
$clean = true; $drift = array();
foreach ($c0 as $k => $v) { if ($cf[$k] !== $v) { $clean = false; $drift[] = $k . ': ' . $v . ' ← ' . $cf[$k]; } }
if (!empty($clmErr)) { info('ردودُ كنسِ المستخلصات: ' . implode(' · ', array_unique($clmErr))); }
check($clean, 'العدّاداتُ عادت لخط الأساس' . ($clean ? '' : ' — انزياح: ' . implode(' · ', $drift)));

/* ══ **شاهدُ النظافةِ كان أعمى عمّا يُسرِّبه.** العدّاداتُ الثمانيةُ أعلاه ماليةٌ
     وحدَها — ولا واحدَ منها يعدُّ العميلَ والموردَ والمشروعَ والمعدةَ والمشغّل.
     وحذفُ الكنسِ لا يُفحَص مُرجَعُه، فحذفٌ يردُّه مفتاحٌ أجنبيٌّ **يُبلَع صامتًا**
     ويُعلَن «العدّاداتُ عادت» — وقد وقع فعلًا: جولةٌ سابقةٌ تركت عالَمَها الجذريَّ
     كلَّه (خمسةَ صفوفٍ)، ومنها عميلٌ بـ`client_code=''` **حجزَ المفتاحَ الفريدَ
     فمنع كلَّ جولةٍ بعده**. فيُقاس التسريبُ بوسمِ الجولةِ لا بعدَّادٍ مالي. */
$leaks = array();
foreach (array('clients' => 'client_name', 'suppliers' => 'name', 'project' => 'name',
               'equipments' => 'name', 'employees' => 'name') as $tbl => $col) {
    $q = $root->query("SELECT COUNT(*) c FROM {$tbl} WHERE {$col} LIKE '{$TAG}%'");
    $n = $q ? (int) $q->fetch_row()[0] : -1;
    if ($n !== 0) { $leaks[] = "{$tbl}={$n}"; }
}
check(empty($leaks), 'وعالَمُ البذرِ الجذريُّ مكنوسٌ بوسمِه (' . $TAG . ')'
    . ($leaks ? ' — تسريب: ' . implode(' · ', $leaks) : ''));

echo "\n" . str_repeat('═', 60) . "\n";
echo "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL === 0 ? 0 : 1);
