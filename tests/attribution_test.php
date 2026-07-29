<?php
/**
 * tests/attribution_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 * حالاتُ القبول §8.1 حرفًا بحرف — CON-02 «عقود مشاريع العملاء والالتزامات».
 *
 *   A1 · مسؤوليةُ العميل (وقودٌ 90د)  ⇒ استعدادٌ **مفوتر** يُحتسب للمورد والمشغّل
 *   A2 · مسؤوليةُ المورد (عطلٌ 30د)   ⇒ لا يُفوتر ولا يُحتسب للمورد · والمشغّل حاضر
 *   A3 · الإسنادُ الإلزامي            ⇒ 422 لا تُقفل واقعةٌ بزمنٍ بلا مسؤول
 *   A4 · الاعتراضُ الموثَّق            ⇒ علمٌ وسببٌ ومرجعٌ يحسمه الدور 19
 *   A6 · الملحقُ لا رجعيَّ            ⇒ نصُّ اليومِ لا يغيّر حكمَ الأمس
 *
 * وبرهانُ المال معه: **ما كان يُفوتر وما صار يُفوتر** بالأرقام على عالَمٍ مبذور.
 * (A5 «الحدُّ الأدنى المضمون بندًا في المستخلص» في حزمة الجزاءات — المرحلة ④.)
 *
 * يبذر عالَمَه المستقلَّ ويكنسه — لا يمسّ صفًّا حقيقيًّا.
 * التشغيل: php tests/attribution_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'attribution test');

require_once dirname(__DIR__) . '/app/Services/Contract/AttributionService.php';
require_once dirname(__DIR__) . '/app/Services/EffectFanout.php';
require_once dirname(__DIR__) . '/app/Services/Unit/TimesheetEntryService.php';

use App\Services\Contract\AttributionService as ATT;
use App\Services\EffectFanout as FAN;
use App\Services\Unit\TimesheetEntryService as TES;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();
$CO   = 4;
$TAG  = 'ATTR' . getmypid();
$DATE = '2026-07-15';
$OLD  = '2026-01-01';   // سريانُ المصفوفة الأولى
$NEW  = '2026-08-01';   // سريانُ الملحق (بعد الواقعة) — A6
$ETYPE = 'ATTRTYPE';
$P_CLIENT = 100.0;      // سعرُ ساعة العميل

// اتصالُ جذرٍ للبذر (البوابةُ ترفض جداولَ غيرَ مستأجَرةٍ وكتاباتٍ خامًا)
$root = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($root->connect_error) { fwrite(STDERR, "root: {$root->connect_error}\n"); exit(1); }
$root->set_charset('utf8mb4');

$ids = array();
function sq($root, $sql, &$bucket) {
    if (!$root->query($sql)) { throw new \RuntimeException('بذر: ' . $root->error . ' ← ' . substr($sql, 0, 160)); }
    $id = intval($root->insert_id);
    $bucket[] = $id;
    return $id;
}

$cleanup = function () use ($root, $TAG, &$ids) {
    // الإسقاطُ قبل الجذر (ADR-15) ثم عالَمُ البذر
    foreach (array('client_ids','supplier_ids') as $k) { /* placeholder */ }
    $root->query("DELETE FROM unit_time_log WHERE cause_note LIKE '{$TAG}%'");
    $root->query("DELETE ua FROM unit_approvals ua JOIN unit_entries e ON e.id=ua.entry_id WHERE e.note LIKE '{$TAG}%'");
    $root->query("DELETE FROM unit_time_log WHERE entry_id IN (SELECT id FROM (SELECT id FROM unit_entries WHERE note LIKE '{$TAG}%') x)");
    $root->query("DELETE FROM unit_entries WHERE note LIKE '{$TAG}%'");
    $root->query("DELETE FROM contract_obligations WHERE client_contract_id IN
                  (SELECT id FROM (SELECT id FROM contracts WHERE project_id IN
                   (SELECT id FROM (SELECT id FROM project WHERE name LIKE '{$TAG}%') p)) c)");
    $root->query("DELETE FROM contract_hour_policies WHERE note LIKE '{$TAG}%'");
    $root->query("DELETE FROM contractequipments WHERE contract_id IN
                  (SELECT id FROM (SELECT id FROM contracts WHERE project_id IN
                   (SELECT id FROM (SELECT id FROM project WHERE name LIKE '{$TAG}%') p) ) c)");
    $root->query("DELETE FROM operations WHERE reason LIKE '{$TAG}%'");
    $root->query("DELETE FROM contracts WHERE project_id IN (SELECT id FROM (SELECT id FROM project WHERE name LIKE '{$TAG}%') p)");
    $root->query("DELETE FROM project WHERE name LIKE '{$TAG}%'");
    $root->query("DELETE FROM equipments WHERE name LIKE '{$TAG}%'");
    $root->query("DELETE FROM employees WHERE name LIKE '{$TAG}%'");
    $root->query("DELETE FROM suppliers WHERE name LIKE '{$TAG}%'");
    $root->query("DELETE FROM clients WHERE client_name LIKE '{$TAG}%'");
};
$cleanup();

fwrite(STDOUT, "══ CON-02 §8.1 — حالاتُ القبول A1…A6 ══\n");

try {

// ═══════════════════════════════════════════════════════════════════════════
head('① بذرُ عالَمٍ مستقل + مصفوفةِ التزاماتٍ مُجازة');
// ═══════════════════════════════════════════════════════════════════════════
$CL  = sq($root, "INSERT INTO clients (company_id, client_name, phone, status) VALUES ($CO,'$TAG-عميل','0',1)", $ids);
$SUP = sq($root, "INSERT INTO suppliers (company_id, name, phone, status) VALUES ($CO,'$TAG-مورد','0',1)", $ids);
$PRJ = sq($root, "INSERT INTO project (company_id, name, client, client_id, location, total, status)
                  VALUES ($CO,'$TAG-مشروع','$TAG-عميل',$CL,'اختبار','0',1)", $ids);
$EQ  = sq($root, "INSERT INTO equipments (company_id, name, code, type, suppliers, status)
                  VALUES ($CO,'$TAG-معدة','$TAG-c','$ETYPE','$SUP',1)", $ids);
$EMP = sq($root, "INSERT INTO employees (company_id, name, phone, employee_status, status)
                  VALUES ($CO,'$TAG-مشغل','0','نشط',1)", $ids);
$CON = sq($root, "INSERT INTO contracts (company_id, project_id, contract_signing_date,
                  price_currency_contract, actual_start, actual_end, status)
                  VALUES ($CO,$PRJ,'2026-01-01','جنيه','2026-01-01','2026-12-31',1)", $ids);
sq($root, "INSERT INTO contractequipments (company_id, contract_id, equip_type, equip_unit, equip_price, equip_price_currency)
           VALUES ($CO,$CON,'$ETYPE','ساعة',$P_CLIENT,'جنيه')", $ids);
$OP = sq($root, "INSERT INTO operations (company_id, project_id, equipment, equipment_type, equipment_category,
                 contract_id, supplier_id, `start`, `end`, reason, days, status)
                 VALUES ($CO,'$PRJ','$EQ','$ETYPE','اختبار','$CON','$SUP','2026-01-01','2026-12-31','$TAG بذر','30',1)", $ids);
ok("العالَم مبذور: مشروع#$PRJ عقد#$CON معدة#$EQ مورد#$SUP مشغّل#$EMP");

// المصفوفةُ **مُجازة** — لا تسري مسودة (ق-18)
$mx = array(
    array('fuel',                'client',   'billable_standby'),
    array('equipment_readiness', 'supplier', 'non_billable'),
    array('operators',           'operator', 'non_billable'),
    array('force_majeure',       'none',     'non_billable'),
);
foreach ($mx as $m) {
    sq($root, "INSERT INTO contract_obligations
        (company_id, client_contract_id, obligation_type, obligor, effect_on_billing,
         approval_state, approved_by, approved_at, valid_from)
        VALUES ($CO,$CON,'{$m[0]}','{$m[1]}','{$m[2]}','approved',74,NOW(),'$OLD')", $ids);
}
ok('المصفوفةُ مُجازةٌ بأربعة بنود: الوقودُ على العميل · الجاهزيةُ على المورد · المشغّلون على المشغّل · القاهرةُ بلا طرف');

$matrix = ATT::matrixFor($gate, $CON, $DATE);
check(count($matrix) === 4, 'matrixFor أعادت المصفوفةَ النافذةَ بتاريخ الواقعة (' . count($matrix) . ' بنود)');
check(isset($matrix['fuel']) && $matrix['fuel']['obligor'] === 'client', 'الوقودُ ملتزمُه العميل');

// المسودةُ لا تسري — برهانٌ لا دعوى
sq($root, "INSERT INTO contract_obligations
    (company_id, client_contract_id, obligation_type, obligor, effect_on_billing, approval_state, valid_from)
    VALUES ($CO,$CON,'utilities','client','billable_standby','draft','$OLD')", $ids);
$m2 = ATT::matrixFor($gate, $CON, $DATE);
check(!isset($m2['utilities']), '**المسودةُ لا تسري** — المصفوفةُ ما زالت 4 لا 5 (ق-18)');

// ═══════════════════════════════════════════════════════════════════════════
head('② بذرُ الواقعة: 6س تشغيل · 1.5س توقفُ وقود (90د) · 0.5س عطلٌ فني (30د)');
// ═══════════════════════════════════════════════════════════════════════════
$ENTRY = sq($root, "INSERT INTO unit_entries
    (company_id, entry_no, entry_date, project_id, contract_id, equipment_id, operator_employee_id,
     supplier_entity_id, unit_type, qty, record_basis, capacity_flag, state, note, sync_uuid)
    VALUES ($CO,'$TAG-E1','$DATE',$PRJ,$CON,$EQ,$EMP,$SUP,'hour',6.00,'contract',0,'submitted','$TAG واقعة','$TAG-uuid')", $ids);
$L = array();
$L['work'] = sq($root, "INSERT INTO unit_time_log (company_id, log_date, project_id, equipment_id,
    operator_employee_id, supplier_entity_id, hours, ops_state, resp_party, entry_id, cause_note)
    VALUES ($CO,'$DATE',$PRJ,$EQ,$EMP,$SUP,6.00,'actual_work','none',$ENTRY,'$TAG تشغيل')", $ids);
$L['fuel'] = sq($root, "INSERT INTO unit_time_log (company_id, log_date, project_id, equipment_id,
    operator_employee_id, supplier_entity_id, hours, ops_state, resp_party, entry_id, cause_note)
    VALUES ($CO,'$DATE',$PRJ,$EQ,$EMP,$SUP,1.50,'fuel_logistics_stop','company',$ENTRY,'$TAG وقود')", $ids);
$L['tech'] = sq($root, "INSERT INTO unit_time_log (company_id, log_date, project_id, equipment_id,
    operator_employee_id, supplier_entity_id, hours, ops_state, resp_party, entry_id, cause_note)
    VALUES ($CO,'$DATE',$PRJ,$EQ,$EMP,$SUP,0.50,'tech_breakdown','company',$ENTRY,'$TAG عطل')", $ids);
ok("الواقعة #{$ENTRY} بثلاثة سطور: تشغيل 6س · وقود 1.5س · عطل 0.5س");

// ── قياسُ ما كان يُفوتر **قبل** الإسناد ──
FAN::$fanoutSourceOverride = 'legal';
$before = FAN::partyAward($gate, ctxFor($conn, $gate, $CO, $ENTRY, $CON, $DATE, $P_CLIENT), 'client');
info('قبل الإسناد: الساعاتُ المفوترة = ' . $before['award_qty'] . 'س  ⇒  ' . ($before['award_qty'] * $P_CLIENT) . ' جنيه');

// ═══════════════════════════════════════════════════════════════════════════
head('③ A3 — الإسنادُ الإلزامي: لا تُقفل واقعةٌ بزمنِ توقفٍ بلا مسؤول');
// ═══════════════════════════════════════════════════════════════════════════
$chk = ATT::assertAttributable($conn, $gate, $CO, $ENTRY);
check(!$chk['ok'] && $chk['code'] === 422, "**A3 ينجح**: الرفضُ 422 قبل الإسناد (code={$chk['code']})");
check(count($chk['reasons']) === 2, 'سطرا التوقف كلاهما مُعلَّلٌ بذاته (' . count($chk['reasons']) . ' سببًا)');
info('السبب: ' . $chk['reasons'][0]);

// عقدٌ بلا مصفوفةٍ ⇒ 423 لا ارتدادَ للافتراضي (ق-2)
$CON2 = sq($root, "INSERT INTO contracts (company_id, project_id, contract_signing_date,
                   price_currency_contract, actual_start, actual_end, status)
                   VALUES ($CO,$PRJ,'2026-01-01','جنيه','2026-01-01','2026-12-31',1)", $ids);
$root->query("UPDATE unit_entries SET contract_id=$CON2 WHERE id=$ENTRY");
$chk423 = ATT::assertAttributable($conn, $gate, $CO, $ENTRY);
check(!$chk423['ok'] && $chk423['code'] === 423, "**عقدٌ بلا مصفوفة ⇒ 423** لا ارتدادَ صامتًا (code={$chk423['code']})");
info('السبب: ' . $chk423['reasons'][0]);
$root->query("UPDATE unit_entries SET contract_id=$CON WHERE id=$ENTRY");

// بندٌ خارج قائمة العقد ⇒ 422
$bad = ATT::decide($conn, $gate, $CO, $ENTRY, array($L['fuel'] => 'catering_camp'), 4);
check(!$bad['ok'] && $bad['code'] === 422, '**بندٌ خارج مصفوفة العقد ⇒ 422** (' . $bad['code'] . ')');

// ═══════════════════════════════════════════════════════════════════════════
head('④ A1 — مسؤوليةُ العميل: وقودٌ 90د ⇒ استعدادٌ مفوتر');
// ═══════════════════════════════════════════════════════════════════════════
$res = ATT::decide($conn, $gate, $CO, $ENTRY, array(
    $L['work'] => null, $L['fuel'] => 'fuel', $L['tech'] => 'equipment_readiness',
), 4);
check($res['ok'] && $res['decided'] === 3, "الإسنادُ وقع على السطور الثلاثة ({$res['decided']})");

$f = $root->query("SELECT obligation_type, billable, supplier_countable, operator_countable, decided_by, decided_at
                     FROM unit_time_log WHERE id={$L['fuel']}")->fetch_assoc();
check($f['obligation_type'] === 'fuel', 'A1: البندُ المُسنَد `fuel`');
check((int) $f['billable'] === 1,           '**A1 ينجح**: 90د وقودٍ صارت **مفوترةً** (billable=1) بعد أن كانت `none`');
check((int) $f['supplier_countable'] === 1, 'A1: وتُحتسب للمورد');
check((int) $f['operator_countable'] === 1, 'A1: وتُحتسب للمشغّل');
check($f['decided_at'] !== null && (int) $f['decided_by'] === 4, 'A1: اللقطةُ مختومةٌ بمَن قرّر ومتى (هـ-3)');

// ═══════════════════════════════════════════════════════════════════════════
head('⑤ A2 — مسؤوليةُ المورد: عطلٌ 30د ⇒ لا يُفوتر ولا يُحتسب له · والمشغّل حاضر');
// ═══════════════════════════════════════════════════════════════════════════
$t = $root->query("SELECT obligation_type, billable, supplier_countable, operator_countable
                     FROM unit_time_log WHERE id={$L['tech']}")->fetch_assoc();
check($t['obligation_type'] === 'equipment_readiness', 'A2: البندُ المُسنَد `equipment_readiness`');
check((int) $t['billable'] === 0,           '**A2 ينجح**: لا تُفوتر على العميل (billable=0)');
check((int) $t['supplier_countable'] === 0, 'A2: ولا تُحتسب للمورد — فهو المقصّر');
check((int) $t['operator_countable'] === 1, 'A2: **والمشغّل حاضرٌ فتُحتسب له** — لا يسقط حقُّه بذنب غيره');

// ── برهانُ المال: الفرقُ بالأرقام ──
head('⑥ برهانُ المال — ما كان يُفوتر وما صار يُفوتر');
$after = FAN::partyAward($gate, ctxFor($conn, $gate, $CO, $ENTRY, $CON, $DATE, $P_CLIENT), 'client');
$deltaH = round($after['award_qty'] - $before['award_qty'], 2);
info('بعد الإسناد: الساعاتُ المفوترة = ' . $after['award_qty'] . 'س  ⇒  ' . ($after['award_qty'] * $P_CLIENT) . ' جنيه');
info('الفرق: +' . $deltaH . 'س  ⇒  +' . round($deltaH * $P_CLIENT, 2) . ' جنيه على هذه الواقعة وحدَها');
check(abs($deltaH - 1.5) < 0.001,
      '**الفرقُ 1.5س بالضبط** = ساعتا الوقود الـ90 دقيقة — لا أكثرَ ولا أقل');
check(abs($after['award_qty'] - 7.5) < 0.001, 'المفوترُ = 6س تشغيل + 1.5س استعدادٍ مفوتر = 7.5س');
$snapUsed = false;
foreach ($after['snapshot']['lines'] as $ln) {
    if ($ln['state'] === 'fuel_logistics_stop') {
        $snapUsed = ($ln['scope'] === 'attribution_snapshot');
        info("سطرُ الوقود في اللقطة: ruling={$ln['ruling']} · scope={$ln['scope']} · بند={$ln['obligation_type']}");
    }
}
check($snapUsed, 'واللقطةُ تعلن مصدرَ حكمها `attribution_snapshot` — تدقيقٌ رجعيٌّ ممكن');

// ═══════════════════════════════════════════════════════════════════════════
head('⑦ A6 — الملحقُ لا رجعيّ: نصُّ الغدِ لا يغيّر حكمَ الأمس');
// ═══════════════════════════════════════════════════════════════════════════
sq($root, "INSERT INTO contract_obligations
    (company_id, client_contract_id, obligation_type, obligor, effect_on_billing,
     approval_state, approved_by, approved_at, valid_from)
    VALUES ($CO,$CON,'fuel','company','non_billable','approved',74,NOW(),'$NEW')", $ids);
ok("ملحقٌ مُجاز: الوقودُ يصير على الشركة من {$NEW}");

$mOld = ATT::matrixFor($gate, $CON, $DATE);
$mNew = ATT::matrixFor($gate, $CON, '2026-09-01');
check($mOld['fuel']['obligor'] === 'client',  "A6: بتاريخ الواقعة ({$DATE}) الوقودُ ما زال **على العميل**");
check($mNew['fuel']['obligor'] === 'company', "A6: وبعد السريان ({$NEW}) صار **على الشركة**");

$f2 = $root->query("SELECT billable FROM unit_time_log WHERE id={$L['fuel']}")->fetch_assoc();
check((int) $f2['billable'] === 1, '**A6 ينجح**: لقطةُ الواقعة لم تتغيّر — الملحقُ لا يمسّ ما فُوتر (§6)');
$after2 = FAN::partyAward($gate, ctxFor($conn, $gate, $CO, $ENTRY, $CON, $DATE, $P_CLIENT), 'client');
check(abs($after2['award_qty'] - $after['award_qty']) < 0.001,
      'وفوترةُ الواقعة نفسُها بعد الملحق: ' . $after2['award_qty'] . 'س — صفرُ فرق');

// ═══════════════════════════════════════════════════════════════════════════
head('⑧ A4 — الاعتراضُ الموثَّق: علمٌ وسببٌ ومرجعٌ يحسمه الدور 19');
// ═══════════════════════════════════════════════════════════════════════════
$noReason = ATT::object($conn, $gate, $CO, $L['fuel'], '', 'REF-1', 13);
check(!$noReason['ok'] && $noReason['code'] === 422, 'اعتراضٌ بلا سببٍ مرفوض — السببُ إلزامي');

$obj = ATT::object($conn, $gate, $CO, $L['fuel'], 'الوقودُ كان بعهدتنا ذلك اليوم', 'محضر-2026-07-15', 13);
check($obj['ok'], 'الاعتراضُ سُجّل');
$o = $root->query("SELECT objection_state, objection_reason, objection_ref, billable
                     FROM unit_time_log WHERE id={$L['fuel']}")->fetch_assoc();
check($o['objection_state'] === 'objected', 'A4: العلمُ مرفوع');
check($o['objection_reason'] !== '' && $o['objection_ref'] === 'محضر-2026-07-15', 'A4: السببُ والمرجعُ محفوظان');

// البندُ المعترَضُ عليه **لا يجمّد بقيةَ الواقعة** (ق-25)
$other = $root->query("SELECT objection_state FROM unit_time_log WHERE id={$L['tech']}")->fetch_assoc();
check($other['objection_state'] === 'none', 'A4: **البكتاتُ الأخرى تمضي** — الاعتراضُ لا يجمّد الواقعة (ق-25)');
$stillOk = ATT::assertAttributable($conn, $gate, $CO, $ENTRY);
check($stillOk['ok'], 'والواقعةُ ما زالت قابلةً للاعتماد رغم الاعتراض المفتوح');

// الحسمُ من الدور 19 — وتغييرُ البند يجعله «تجاوزًا»
$rs = ATT::resolve($conn, $gate, $CO, $L['fuel'], 'operators', 74);
check($rs['ok'] && !empty($rs['overridden']), 'A4: حُسم الاعتراضُ بتغيير البند ⇒ تجاوزٌ مسجَّل');
$o2 = $root->query("SELECT objection_state, obligation_type, billable, operator_countable
                      FROM unit_time_log WHERE id={$L['fuel']}")->fetch_assoc();
check($o2['objection_state'] === 'resolved', 'A4: الحالةُ صارت `resolved`');
check($o2['obligation_type'] === 'operators', 'A4: والبندُ صار `operators` بقرار الحسم');
check((int) $o2['billable'] === 0 && (int) $o2['operator_countable'] === 0,
      'A4: وأحكامُه أُعيد اشتقاقُها من المصفوفة (المشغّل ملتزمٌ ⇒ لا فوترةَ ولا احتساب)');

// ═══════════════════════════════════════════════════════════════════════════
head('⑨ الأحداثُ الثلاثةُ نُشرت عبر الناشر حصرًا (v8 §15-أ)');
// ═══════════════════════════════════════════════════════════════════════════
$ev = $root->query("SELECT event_key, COUNT(*) c FROM ems_business_events
                     WHERE entity_type='unit_entry' AND entity_id=$ENTRY
                       AND event_key LIKE 'attribution.%' GROUP BY event_key")->fetch_all(MYSQLI_ASSOC);
$keys = array(); foreach ($ev as $e) { $keys[$e['event_key']] = (int) $e['c']; }
info('الأحداث: ' . json_encode($keys, JSON_UNESCAPED_UNICODE));
check(isset($keys['attribution.decided']), '`attribution.decided` منشور');
check(isset($keys['attribution.objected']), '`attribution.objected` منشور');
check(isset($keys['attribution.overridden']), '`attribution.overridden` منشور');

} catch (\Throwable $t) {
    bad('استثناء: ' . $t->getMessage() . ' @ ' . $t->getFile() . ':' . $t->getLine());
}

// ═══════════════════════════════════════════════════════════════════════════
head('⑩ الكنس — الإسقاطُ قبل الجذر ثم عالَمُ البذر');
// ═══════════════════════════════════════════════════════════════════════════
FAN::$fanoutSourceOverride = null;
if (isset($ENTRY) && $ENTRY > 0) {
    $root->query("DELETE FROM fin_event_links WHERE event_id IN
                  (SELECT id FROM (SELECT id FROM fin_financial_events WHERE root_event_id IN
                   (SELECT id FROM (SELECT id FROM ems_business_events
                     WHERE entity_type='unit_entry' AND entity_id=$ENTRY) r)) f)");
    $root->query("DELETE FROM fin_financial_events WHERE root_event_id IN
                  (SELECT id FROM (SELECT id FROM ems_business_events
                    WHERE entity_type='unit_entry' AND entity_id=$ENTRY) r)");
    $root->query("DELETE FROM ems_business_events WHERE entity_type='unit_entry' AND entity_id=$ENTRY");
}
$cleanup();
$left = (int) $root->query("SELECT COUNT(*) c FROM unit_entries WHERE note LIKE '{$TAG}%'")->fetch_assoc()['c']
      + (int) $root->query("SELECT COUNT(*) c FROM project WHERE name LIKE '{$TAG}%'")->fetch_assoc()['c']
      + (int) $root->query("SELECT COUNT(*) c FROM contract_obligations WHERE company_id=$CO
                             AND client_contract_id NOT IN (SELECT id FROM (SELECT id FROM contracts) c2)")->fetch_assoc()['c'];
check($left === 0, 'صفرُ بقايا');

fwrite(STDOUT, "\n" . str_repeat('═', 60) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);


/**
 * سياقُ المروحة من المصدر القانوني — مبنيٌّ يدويًّا لأن `resolveTimesheet`
 * يحتاج صفَّ دوامٍ حيًّا، وهذه الحزمةُ تختبر **الإسناد** لا جسرَ الدوام.
 * القيمُ المقروءةُ من `unit_time_log` هي عينُها التي يقرؤها المسارُ القانوني.
 */
function ctxFor($conn, $gate, $CO, $entryId, $contractId, $date, $price)
{
    $q = $conn->query("SELECT ops_state, obligation_type, billable, supplier_countable, operator_countable,
                              COALESCE(SUM(hours),0) h
                         FROM unit_time_log WHERE company_id=$CO AND entry_id=" . intval($entryId) . "
                        GROUP BY ops_state, obligation_type, billable, supplier_countable, operator_countable");
    $lines = array(); $states = array();
    while ($r = $q->fetch_assoc()) {
        $k = ($r['ops_state'] === 'unlogged') ? 'other' : $r['ops_state'];
        if (!isset($states[$k])) { $states[$k] = 0.0; }
        $states[$k] += (float) $r['h'];
        $lines[] = array(
            'state' => $k, 'hours' => (float) $r['h'],
            'obligation_type' => ($r['obligation_type'] === null || $r['obligation_type'] === '') ? null : $r['obligation_type'],
            'billable' => ($r['billable'] === null) ? null : intval($r['billable']),
            'supplier_countable' => ($r['supplier_countable'] === null) ? null : intval($r['supplier_countable']),
            'operator_countable' => ($r['operator_countable'] === null) ? null : intval($r['operator_countable']),
        );
    }
    return array(
        'company_id' => $CO, 'work_date' => $date, 'states' => $states, 'state_lines' => $lines,
        'client' => array('ok' => true, 'unit' => 'hour', 'qty' => 0.0, 'price' => $price,
                          'currency' => 'SDG', 'contract_id' => $contractId),
        'supplier' => array('ok' => true, 'unit' => 'hour', 'qty' => 0.0, 'price' => 0.0,
                            'currency' => 'SDG', 'contract_id' => $contractId),
    );
}
