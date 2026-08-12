<?php
/**
 * tests/attribution_board_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP للوحة الإسناد اليومي (CON-02 §7-④ · ق-4 · ق-6 · ق-25).
 * من **الشاشة الحقيقية** بدورَين:
 *   ① التشغيل (1) يفتح اليومَ ويرى **الزمنَ بلا مسؤولٍ أحمرَ** بعدّاده
 *   ② بنودُ القائمة من **مصفوفة العقد** لا من قائمةٍ عامةٍ ثابتة (§5)
 *   ③ الإسنادُ من الشاشة يكتب الأحكامَ الثلاثةَ لقطةً
 *   ④ عقدٌ بلا مصفوفةٍ ⇒ لافتةُ 423 وقفلُ الإسناد (ق-2)
 *   ⑤ الاعتراضُ من التشغيل · و**الحسمُ للمالية وحدَها** (ق-25)
 * ثم يكنس كلَّ ما بذر.
 *
 * التشغيل: php tests/attribution_board_http_proof.php   (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$BASE = 'http://localhost/ems';
$TMP  = sys_get_temp_dir();
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

function ab_req($url, $jar, $post = null) {
    /* الرمزُ المركزيُّ: الحارسُ يقبله في الحقولِ أو الترويسة، والمسبارُ
       يبني الحقولَ يدويًّا فلا يحمله ⇒ 403 صامتٌ يُقرأ فشلًا في المنتج. */
    if ($post !== null) {
        require_once __DIR__ . '/_http_flash.php';
        $post = ems_http_with_csrf($post, $url, $jar);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}
function ab_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = ab_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return ab_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}
function ab_msg($headers) {
    if (preg_match('~Location:\s*(\S+)~i', $headers, $m)) {
        $q = parse_url(trim($m[1]), PHP_URL_QUERY);
        parse_str((string) $q, $p);
        return isset($p['msg']) ? $p['msg'] : '';
    }
    return '';
}

require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_error) { fwrite(STDERR, "DB: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

$CO   = 4;
$TAG  = 'ABP' . getmypid();
$DAY  = '2026-06-11';
$ETYPE = 'ABPTYPE';
$SCR  = $BASE . '/Approvals/attribution_board.php';
$OPSU = 'محمد';                          // الدور 1
$FINM = 'fin.deptmgr@equipation.sd';     // الدور 19

$cleanup = function () use ($db, $TAG) {
    $db->query("DELETE FROM unit_time_log WHERE entry_id IN (SELECT id FROM (SELECT id FROM unit_entries WHERE note LIKE '{$TAG}%') x)");
    $db->query("DELETE FROM unit_entries WHERE note LIKE '{$TAG}%'");
    $db->query("DELETE FROM contract_obligations WHERE client_contract_id IN
                (SELECT id FROM (SELECT id FROM contracts WHERE project_id IN
                 (SELECT id FROM (SELECT id FROM project WHERE name LIKE '{$TAG}%') p)) c)");
    $db->query("DELETE FROM contractequipments WHERE contract_id IN
                (SELECT id FROM (SELECT id FROM contracts WHERE project_id IN
                 (SELECT id FROM (SELECT id FROM project WHERE name LIKE '{$TAG}%') p)) c)");
    $db->query("DELETE FROM contracts WHERE project_id IN (SELECT id FROM (SELECT id FROM project WHERE name LIKE '{$TAG}%') p)");
    $db->query("DELETE FROM project WHERE name LIKE '{$TAG}%'");
    $db->query("DELETE FROM equipments WHERE name LIKE '{$TAG}%'");
    $db->query("DELETE FROM employees WHERE name LIKE '{$TAG}%'");
    $db->query("DELETE FROM suppliers WHERE name LIKE '{$TAG}%'");
    $db->query("DELETE FROM clients WHERE client_name LIKE '{$TAG}%'");
};
$cleanup();

function seed($db, $sql) {
    if (!$db->query($sql)) { throw new \RuntimeException('بذر: ' . $db->error . ' ← ' . substr($sql, 0, 140)); }
    return intval($db->insert_id);
}

fwrite(STDOUT, "══ برهانُ لوحة الإسناد اليومي — CON-02 §7-④ ══\n");

try {
head('① بذرُ يومِ عملٍ بواقعتين: واحدةٌ بمصفوفةٍ وأخرى بلا');
$CL  = seed($db, "INSERT INTO clients (company_id, client_name, phone, status) VALUES ($CO,'$TAG-عميل','0',1)");
$SUP = seed($db, "INSERT INTO suppliers (company_id, name, phone, status) VALUES ($CO,'$TAG-مورد','0',1)");
$PRJ = seed($db, "INSERT INTO project (company_id, name, client, client_id, location, total, status)
                  VALUES ($CO,'$TAG-مشروع','$TAG-عميل',$CL,'اختبار','0',1)");
$EQ  = seed($db, "INSERT INTO equipments (company_id, name, code, type, suppliers, status)
                  VALUES ($CO,'$TAG-معدة','$TAG-c','$ETYPE','$SUP',1)");
$EMP = seed($db, "INSERT INTO employees (company_id, name, phone, employee_status, status)
                  VALUES ($CO,'$TAG-مشغل','0','نشط',1)");
$CON = seed($db, "INSERT INTO contracts (company_id, project_id, contract_signing_date, price_currency_contract,
                  actual_start, actual_end, status) VALUES ($CO,$PRJ,'2026-01-01','جنيه','2026-01-01','2026-12-31',1)");
$CON0 = seed($db, "INSERT INTO contracts (company_id, project_id, contract_signing_date, price_currency_contract,
                   actual_start, actual_end, status) VALUES ($CO,$PRJ,'2026-01-01','جنيه','2026-01-01','2026-12-31',1)");
foreach (array(array('fuel','client','billable_standby'), array('equipment_readiness','supplier','non_billable')) as $m) {
    seed($db, "INSERT INTO contract_obligations (company_id, client_contract_id, obligation_type, obligor,
               effect_on_billing, approval_state, approved_by, approved_at, valid_from)
               VALUES ($CO,$CON,'{$m[0]}','{$m[1]}','{$m[2]}','approved',74,NOW(),'2026-01-01')");
}
$E1 = seed($db, "INSERT INTO unit_entries (company_id, entry_no, entry_date, project_id, contract_id, equipment_id,
      operator_employee_id, supplier_entity_id, unit_type, qty, record_basis, capacity_flag, state, note, sync_uuid)
      VALUES ($CO,'$TAG-E1','$DAY',$PRJ,$CON,$EQ,$EMP,$SUP,'hour',6.00,'contract',0,'submitted','$TAG w1','$TAG-u1')");
$E2 = seed($db, "INSERT INTO unit_entries (company_id, entry_no, entry_date, project_id, contract_id, equipment_id,
      operator_employee_id, supplier_entity_id, unit_type, qty, record_basis, capacity_flag, state, note, sync_uuid)
      VALUES ($CO,'$TAG-E2','$DAY',$PRJ,$CON0,$EQ,$EMP,$SUP,'hour',5.00,'contract',0,'submitted','$TAG w2','$TAG-u2')");
$LW = seed($db, "INSERT INTO unit_time_log (company_id, log_date, project_id, equipment_id, operator_employee_id,
      supplier_entity_id, hours, ops_state, resp_party, entry_id, cause_note)
      VALUES ($CO,'$DAY',$PRJ,$EQ,$EMP,$SUP,6.00,'actual_work','none',$E1,'$TAG')");
$LF = seed($db, "INSERT INTO unit_time_log (company_id, log_date, project_id, equipment_id, operator_employee_id,
      supplier_entity_id, hours, ops_state, resp_party, entry_id, cause_note)
      VALUES ($CO,'$DAY',$PRJ,$EQ,$EMP,$SUP,1.50,'fuel_logistics_stop','company',$E1,'$TAG')");
$LX = seed($db, "INSERT INTO unit_time_log (company_id, log_date, project_id, equipment_id, operator_employee_id,
      supplier_entity_id, hours, ops_state, resp_party, entry_id, cause_note)
      VALUES ($CO,'$DAY',$PRJ,$EQ,$EMP,$SUP,2.00,'tech_breakdown','company',$E2,'$TAG')");
ok("يومُ $DAY: واقعة #$E1 (عقد بمصفوفة) وواقعة #$E2 (عقد #$CON0 بلا مصفوفة)");

head('② التشغيل (1) يفتح اللوحة');
$jar1 = $TMP . '/ab_ops.txt';
ab_login($OPSU, $jar1);
list($c, $h, $b) = ab_req($SCR . '?day=' . $DAY, $jar1);
check($c === 200, "اللوحةُ تُفتح للدور 1 (HTTP {$c})");
check(strpos($b, 'الكاتبُ يقترح والمشرفُ يعتمد') !== false, 'قاعدةُ ق-4 معلنةٌ نصًّا على الشاشة');
check(preg_match('~(\d+)\s*فترةَ توقفٍ بلا بندٍ مُسنَد~u', $b, $m) === 1 && (int) $m[1] === 2,
      '**الزمنُ بلا مسؤولٍ معدودٌ ومعلَن**: ' . (isset($m[1]) ? $m[1] : '؟') . ' فترتين');
check(strpos($b, 'atb-row-missing') !== false, 'وسطورُه حمراءُ في الجدول');
// حالةُ الحارس تُقرأ من العَلَم لا تُفترض: ق-24 تنصّ على أن العَلَم **يُقلب**
// بعد ملء المصفوفات، فتأكيدٌ يشترط «مطفأً» يصير كاذبًا يومَ ينجح المشروع.
// والمُختبَرُ هو **تطابقُ الشاشة مع الحارس**، لا قيمةُ العَلَم بعينها.
require_once dirname(__DIR__) . '/app/Services/Contract/AttributionService.php';
$attEnforced = \App\Services\Contract\AttributionService::enforced();
$attLabel = $attEnforced ? 'مُفعَّل (422/423)' : 'رصدٌ فقط — لم يُقلب العَلَم بعد';
check(strpos($b, $attLabel) !== false,
      'وحالةُ الحارس معلنةٌ صراحةً ومطابقةٌ للعَلَم: «' . $attLabel . '»');

head('③ بنودُ القائمة من مصفوفة العقد لا من قائمةٍ عامة');
check(strpos($b, 'الوقودُ والزيوت') !== false, 'بندُ «الوقود» معروضٌ (من المصفوفة)');
check(strpos($b, 'جاهزيةُ المعدة والصيانة') !== false, 'وبندُ «جاهزية المعدة»');
check(strpos($b, 'الإعاشةُ والمعسكر') === false,
      '**والبنودُ غيرُ المُجازةِ للعقد غائبةٌ** — القائمةُ من العقد لا من التسعة كلِّها');

head('④ عقدٌ بلا مصفوفة ⇒ لافتةُ 423 وقفلُ الإسناد (ق-2)');
check(strpos($b, 'عقدٌ بلا مصفوفةٍ مُجازة (423)') !== false, 'لافتةُ 423 ظاهرةٌ على الواقعة الثانية');
check(strpos($b, 'contract_obligations.php?contract=' . $CON0) !== false,
      'وفيها رابطٌ مباشرٌ إلى شاشة ملء المصفوفة — الرفضُ يدلّ على العلاج');

head('⑤ الإسنادُ من الشاشة يكتب الأحكامَ الثلاثةَ لقطةً');
list($c, $h, $b2) = ab_req($SCR . '?day=' . $DAY, $jar1, array(
    'atb_action' => 'decide', 'entry_id' => $E1,
    'oblig' => array($LW => '', $LF => 'fuel'),
));
$msg = ab_msg($h);
check(strpos($msg, '✅') !== false, "اعتُمد الإسناد: {$msg}");
$r = $db->query("SELECT obligation_type, billable, supplier_countable, operator_countable, decided_by
                   FROM unit_time_log WHERE id=$LF")->fetch_assoc();
check($r['obligation_type'] === 'fuel', 'البندُ `fuel` مكتوب');
check((int) $r['billable'] === 1 && (int) $r['supplier_countable'] === 1 && (int) $r['operator_countable'] === 1,
      '**الأحكامُ الثلاثةُ مشتقةٌ من المصفوفة**: يُفوتر ✔ للمورد ✔ للمشغّل ✔ (A1 من الشاشة)');
check((int) $r['decided_by'] === 4, 'ومختومةٌ بمَن قرّر (المستخدم 4)');

list($c, $h, $b3) = ab_req($SCR . '?day=' . $DAY, $jar1);
check(preg_match('~(\d+)\s*فترةَ توقفٍ بلا بندٍ مُسنَد~u', $b3, $m) === 1 && (int) $m[1] === 1,
      'وعدّادُ الحجب نزل إلى 1 (بقيت واقعةُ الـ423)');

head('⑥ الاعتراض: التشغيل يعترض · والحسمُ للمالية وحدَها (ق-25)');
list($c, $h, $b4) = ab_req($SCR . '?day=' . $DAY, $jar1, array(
    'atb_action' => 'object', 'entry_id' => $E1, 'line_id' => $LF,
    'reason' => 'الوقودُ كان بعهدتنا ذلك اليوم', 'ref' => 'محضر-' . $TAG,
));
check(strpos(ab_msg($h), '✅') !== false, 'التشغيلُ سجّل الاعتراض');
$o = $db->query("SELECT objection_state, objection_reason, objection_ref FROM unit_time_log WHERE id=$LF")->fetch_assoc();
check($o['objection_state'] === 'objected' && $o['objection_ref'] === 'محضر-' . $TAG,
      'العلمُ والسببُ والمرجعُ محفوظة');

// التشغيلُ يحاول الحسم — يُرفض
list($c, $h, $b5) = ab_req($SCR . '?day=' . $DAY, $jar1, array(
    'atb_action' => 'resolve', 'entry_id' => $E1, 'line_id' => $LF, 'new_oblig' => '',
));
$msg = ab_msg($h);
check(strpos($msg, '❌') !== false, "**التشغيلُ لا يحسم اعتراضَه**: {$msg}");
$o = $db->query("SELECT objection_state FROM unit_time_log WHERE id=$LF")->fetch_assoc();
check($o['objection_state'] === 'objected', 'والاعتراضُ ما زال مفتوحًا فعلًا');

// المالية تحسم
$jar2 = $TMP . '/ab_fin.txt';
ab_login($FINM, $jar2);
list($c, $h, $b6) = ab_req($SCR . '?day=' . $DAY, $jar2);
check(strpos($b6, 'atb-resolve') !== false, 'زرُّ الحسم ظاهرٌ للمالية');
check(strpos($b6, 'name="atb_action" value="decide"') !== false
      && strpos($b6, 'اعتماد الإسناد') === false,
      '**ولا زرَّ اعتمادِ إسنادٍ للمالية** — فصلُ اليدين');
list($c, $h, $b7) = ab_req($SCR . '?day=' . $DAY, $jar2, array(
    'atb_action' => 'resolve', 'entry_id' => $E1, 'line_id' => $LF, 'new_oblig' => '',
));
check(strpos(ab_msg($h), '✅') !== false, 'المالية حسمت الاعتراض');
$o = $db->query("SELECT objection_state FROM unit_time_log WHERE id=$LF")->fetch_assoc();
check($o['objection_state'] === 'resolved', 'والحالةُ صارت `resolved`');

} catch (\Throwable $t) {
    bad('استثناء: ' . $t->getMessage() . ' @ ' . $t->getLine());
}

head('الكنس');
if (isset($E1)) {
    $db->query("DELETE FROM fin_event_links WHERE event_id IN (SELECT id FROM (SELECT id FROM fin_financial_events
                WHERE root_event_id IN (SELECT id FROM (SELECT id FROM ems_business_events
                 WHERE entity_type='unit_entry' AND entity_id IN ($E1,$E2)) r)) f)");
    $db->query("DELETE FROM fin_financial_events WHERE root_event_id IN (SELECT id FROM
                (SELECT id FROM ems_business_events WHERE entity_type='unit_entry' AND entity_id IN ($E1,$E2)) r)");
    $db->query("DELETE FROM ems_business_events WHERE entity_type='unit_entry' AND entity_id IN ($E1,$E2)");
}
$cleanup();
$left = (int) $db->query("SELECT COUNT(*) c FROM unit_entries WHERE note LIKE '{$TAG}%'")->fetch_assoc()['c'];
check($left === 0, 'صفرُ بقايا');
@unlink($TMP . '/ab_ops.txt'); @unlink($TMP . '/ab_fin.txt');

fwrite(STDOUT, "\n" . str_repeat('═', 60) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
