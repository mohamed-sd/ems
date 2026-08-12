<?php
/**
 * tests/attribution_reversal_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 * **خطأُ 409 والتصحيحُ بعكس الحدث** — CON-02 §8 (قرارُ المالك 2026-07-28).
 *
 *   ① تعديلُ إسنادٍ **بلا قيدٍ ماليٍّ مرتبط** ⇒ **يمرّ** — لا حارسَ بلا سبب
 *   ② وتعديلُه **بقيدٍ مرتبط** ⇒ **409** — لا يُكتب فوق حكمٍ ماليٍّ قائم
 *   ③ والحدُّ **وجودُ القيد** لا حالةُ الواقعة: `sales_approved` بلا قيدٍ تمرّ
 *   ④ والعكسُ **بيد الدور 19 وحدَه** (ق-13)
 *   ⑤ والعكسُ ⇒ **سطرٌ عاكسٌ جديدٌ بساعاتٍ سالبة · والأصلُ باقٍ بحاله**
 *   ⑥ وحدثُ العكس منشورٌ بـ**`reverses_event_id` صحيح** يشير إلى حدث القرار
 *   ⑦ ولا يُعكس حدثٌ مرتين (عطالة)
 *   ⑧ و**لا تُعاد المروحةُ تلقائيًّا** — توليدُ المال تحكمه بوابةُ التحويل
 *
 * يبذر عالَمَه المستقلَّ ويكنسه — لا يمسّ صفًّا حقيقيًّا.
 * التشغيل: php tests/attribution_reversal_test.php
 * ═══════════════════════════════════════════════════════════════════════════
  * ⇐ شواهدُ أحكامٍ: FIXA-0008-ب
 * (رُبطت بمراجعةٍ خصمٍ 2026-08-12 — الحجّةُ وسببُ قبولِها في docs/fix_progress/BINDINGS.md)
*/

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'reversal test');

require_once dirname(__DIR__) . '/app/Services/Contract/AttributionService.php';
require_once dirname(__DIR__) . '/app/Services/EffectFanout.php';

use App\Services\Contract\AttributionService as ATT;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();
$CO    = 4;
$TAG   = 'REV' . getmypid();
$DATE  = '2026-07-15';
$OLD   = '2026-01-01';
$ETYPE = 'REVTYPE';
$FINMGR = 74;   // الدور 19
$OPS    = 4;    // الدور 1
$TSREF  = 990000 + getmypid();   // مرجعُ دوامٍ وهميٌّ لجسر `ts:` — لا يمسّ صفًّا حقيقيًّا

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

$cleanup = function () use ($root, $TAG, $TSREF, $CO) {
    // ⚠️ ADR-15: الإسقاطُ يُحذف **قبل** الجذر — لا العكس
    $root->query("DELETE FROM fin_event_links WHERE company_id=$CO AND parent_kind='timesheet' AND parent_ref=$TSREF");
    $root->query("DELETE be FROM ems_business_events be JOIN unit_entries e ON e.id=be.entity_id
                  WHERE be.entity_type='unit_entry' AND e.note LIKE '{$TAG}%'");
    $root->query("DELETE FROM unit_time_log WHERE entry_id IN (SELECT id FROM (SELECT id FROM unit_entries WHERE note LIKE '{$TAG}%') x)");
    $root->query("DELETE FROM unit_time_log WHERE cause_note LIKE '{$TAG}%'");
    $root->query("DELETE FROM unit_entries WHERE note LIKE '{$TAG}%'");
    $root->query("DELETE FROM contract_obligations WHERE client_contract_id IN
                  (SELECT id FROM (SELECT id FROM contracts WHERE project_id IN
                   (SELECT id FROM (SELECT id FROM project WHERE name LIKE '{$TAG}%') p)) c)");
    $root->query("DELETE FROM contractequipments WHERE contract_id IN
                  (SELECT id FROM (SELECT id FROM contracts WHERE project_id IN
                   (SELECT id FROM (SELECT id FROM project WHERE name LIKE '{$TAG}%') p)) c)");
    $root->query("DELETE FROM contracts WHERE project_id IN (SELECT id FROM (SELECT id FROM project WHERE name LIKE '{$TAG}%') p)");
    $root->query("DELETE FROM project WHERE name LIKE '{$TAG}%'");
    $root->query("DELETE FROM equipments WHERE name LIKE '{$TAG}%'");
    $root->query("DELETE FROM employees WHERE name LIKE '{$TAG}%'");
    $root->query("DELETE FROM suppliers WHERE name LIKE '{$TAG}%'");
    $root->query("DELETE FROM clients WHERE client_name LIKE '{$TAG}%'");
};
$cleanup();

fwrite(STDOUT, "══ CON-02 §8 — الـ409 والتصحيحُ بعكس الحدث ══\n");

try {

head('① بذرُ عالَمٍ مستقلٍّ بمصفوفةٍ مُجازة');
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
           VALUES ($CO,$CON,'$ETYPE','ساعة',100.0,'جنيه')", $ids);
foreach (array(array('fuel','client','billable_standby'),
               array('equipment_readiness','supplier','non_billable'),
               array('operators','operator','non_billable')) as $m) {
    sq($root, "INSERT INTO contract_obligations
        (company_id, client_contract_id, obligation_type, obligor, effect_on_billing,
         approval_state, approved_by, approved_at, valid_from)
        VALUES ($CO,$CON,'{$m[0]}','{$m[1]}','{$m[2]}','approved',$FINMGR,NOW(),'$OLD')", $ids);
}
ok("العالَم مبذور: عقد#$CON بمصفوفةٍ مُجازةٍ من ثلاثة بنود");

// واقعةٌ (أ) **بلا قيد** · وواقعةٌ (ب) **بقيدٍ مرتبطٍ** عبر جسر `ts:`
$EA = sq($root, "INSERT INTO unit_entries (company_id, entry_no, entry_date, project_id, contract_id,
      equipment_id, operator_employee_id, supplier_entity_id, unit_type, qty, record_basis,
      capacity_flag, state, note, sync_uuid)
      VALUES ($CO,'$TAG-EA','$DATE',$PRJ,$CON,$EQ,$EMP,$SUP,'hour',6.00,'contract',0,'sales_approved','$TAG واقعة-أ','$TAG-ua')", $ids);
$EB = sq($root, "INSERT INTO unit_entries (company_id, entry_no, entry_date, project_id, contract_id,
      equipment_id, operator_employee_id, supplier_entity_id, unit_type, qty, record_basis,
      capacity_flag, state, note, sync_uuid)
      VALUES ($CO,'$TAG-EB','$DATE',$PRJ,$CON,$EQ,$EMP,$SUP,'hour',6.00,'contract',0,'submitted','$TAG واقعة-ب','ts:$TSREF')", $ids);

$LA = sq($root, "INSERT INTO unit_time_log (company_id, log_date, project_id, equipment_id,
      operator_employee_id, supplier_entity_id, hours, ops_state, resp_party, entry_id, cause_note)
      VALUES ($CO,'$DATE',$PRJ,$EQ,$EMP,$SUP,1.50,'fuel_logistics_stop','company',$EA,'$TAG وقود-أ')", $ids);
$LB = sq($root, "INSERT INTO unit_time_log (company_id, log_date, project_id, equipment_id,
      operator_employee_id, supplier_entity_id, hours, ops_state, resp_party, entry_id, cause_note)
      VALUES ($CO,'$DATE',$PRJ,$EQ,$EMP,$SUP,2.00,'fuel_logistics_stop','company',$EB,'$TAG وقود-ب')", $ids);
info("واقعة أ#$EA (حالتُها `sales_approved` وبلا قيد) · واقعة ب#$EB (بقيدٍ عبر `ts:$TSREF`)");

head('② تعديلُ إسنادٍ بلا قيدٍ مرتبط ⇒ يمرّ (ولو كانت الحالةُ sales_approved)');
$r = ATT::decide($conn, $gate, $CO, $EA, array($LA => 'fuel'), $OPS);
check($r['ok'] && $r['decided'] === 1, 'الإسنادُ الأولُ مرّ على الواقعة أ (' . $r['code'] . ')');
$r2 = ATT::decide($conn, $gate, $CO, $EA, array($LA => 'equipment_readiness'), $OPS);
check($r2['ok'], '**والتعديلُ يمرّ كذلك** — لا حارسَ بلا سببٍ ماليّ (' . $r2['code'] . ')');
$a = $root->query("SELECT obligation_type, billable FROM unit_time_log WHERE id=$LA")->fetch_assoc();
check($a['obligation_type'] === 'equipment_readiness', 'والبندُ تبدّل فعلًا إلى `equipment_readiness`');
check((int) $a['billable'] === 0, 'وحكمُه تبدّل معه (billable 1→0) — تصحيحٌ مباشرٌ نظيف');
ok('**③ والحدُّ ليس حالةَ الواقعة**: `sales_approved` بلا قيدٍ مرّت — لا `sales_approved` تحجب');

head('④ الإسنادُ على واقعةٍ ولّدت مالًا ⇒ 409');
// أولًا قرارٌ سليم **قبل** ظهور القيد — فيصير لنا حدثُ قرارٍ يُعكس لاحقًا
$rb = ATT::decide($conn, $gate, $CO, $EB, array($LB => 'fuel'), $OPS);
check($rb['ok'] && $rb['decided'] === 1, 'قرارُ الإسناد على الواقعة ب وقع قبل القيد');
$decEvt = intval($root->query("SELECT id FROM ems_business_events
                                WHERE company_id=$CO AND entity_type='unit_entry' AND entity_id=$EB
                                  AND event_key='attribution.decided' ORDER BY id DESC LIMIT 1")->fetch_assoc()['id']);
check($decEvt > 0, "وحدثُ القرار مدوَّنٌ في الجذر #{$decEvt}");

// الآن يقع المال: رابطُ إيرادٍ على جسر الدوام (نمطُ المروحة حرفيًّا)
$root->query("INSERT INTO fin_event_links (company_id, parent_kind, parent_ref, effect_type, target_table, target_id, event_id)
              VALUES ($CO,'timesheet',$TSREF,'revenue_event','fin_financial_events',NULL,NULL)");
$root->query("INSERT INTO fin_event_links (company_id, parent_kind, parent_ref, effect_type, target_table, target_id, event_id)
              VALUES ($CO,'timesheet',$TSREF,'supplier_due','fin_dues',NULL,NULL)");

$blocked = ATT::decide($conn, $gate, $CO, $EB, array($LB => 'equipment_readiness'), $OPS);
check(!$blocked['ok'] && $blocked['code'] === 409, '**409**: لا يُكتب فوق حكمٍ ماليٍّ قائم (' . $blocked['code'] . ')');
check(isset($blocked['posted']) && $blocked['posted']['count'] === 2, 'والحارسُ عدّ القيدين (' . ($blocked['posted']['count'] ?? '؟') . ')');
check(strpos($blocked['reasons'][0], 'قيدُ إيراد') !== false
      && strpos($blocked['reasons'][0], 'مستحقُّ مورد') !== false,
      'والرسالةُ تسمّي **ما وقع** بالاسم: قيدُ إيرادٍ ومستحقُّ مورد');
check(strpos($blocked['reasons'][0], 'بعكس الحدث') !== false,
      'وتدلّ على **العلاج**: التصحيحُ بعكس الحدث');
$b = $root->query("SELECT obligation_type FROM unit_time_log WHERE id=$LB")->fetch_assoc();
check($b['obligation_type'] === 'fuel', '**ولم يُمسّ السطرُ بحرف** — ما زال `fuel`');

// وحسمُ الاعتراض يرتدّ بالـ409 نفسِه قبل أن يبدّل الحالة
$root->query("UPDATE unit_time_log SET objection_state='objected', objection_reason='$TAG اعتراض' WHERE id=$LB");
$rr = ATT::resolve($conn, $gate, $CO, $LB, 'equipment_readiness', $FINMGR);
check(!$rr['ok'] && $rr['code'] === 409, 'وحسمُ الاعتراض يرتدّ بـ409 كذلك (' . $rr['code'] . ')');
$st = $root->query("SELECT objection_state FROM unit_time_log WHERE id=$LB")->fetch_assoc();
check($st['objection_state'] === 'objected',
      '**وحالةُ الاعتراض لم تُبدَّل** — الحارسُ قبل الكتابة لا بعدها');
$root->query("UPDATE unit_time_log SET objection_state='none', objection_reason=NULL WHERE id=$LB");

head('⑤ العكسُ بيد الدور 19 وحدَه (ق-13)');
foreach (array('12' => 'المبيعات', '1' => 'التشغيل', '17' => 'محاسبٌ مالي') as $role => $who) {
    $x = ATT::reverse($conn, $gate, $CO, $LB, 'equipment_readiness', 'سببٌ موثَّق', $OPS, $role);
    check(!$x['ok'] && $x['code'] === 403, "دورُ {$who} ({$role}) لا يعكس — 403");
}
$noReason = ATT::reverse($conn, $gate, $CO, $LB, 'equipment_readiness', '   ', $FINMGR, '19');
check(!$noReason['ok'] && $noReason['code'] === 422, 'وسببُ العكس إلزاميٌّ — لا يُبطل قيدٌ بلا تعليل');
$rows0 = intval($root->query("SELECT COUNT(*) n FROM unit_time_log WHERE entry_id=$EB")->fetch_assoc()['n']);
check($rows0 === 1, 'وصفرُ سطرٍ كُتب في المحاولات المرفوضة (' . $rows0 . ' سطر)');

head('⑥ العكسُ من 19 ⇒ سطرٌ عاكسٌ جديدٌ والأصلُ باقٍ');
$origBefore = $root->query("SELECT hours, obligation_type, billable, supplier_countable, operator_countable
                              FROM unit_time_log WHERE id=$LB")->fetch_assoc();
$rev = ATT::reverse($conn, $gate, $CO, $LB, 'equipment_readiness', 'الوقودُ كان بعهدتنا — محضر 12', $FINMGR, '19');
check($rev['ok'] && $rev['code'] === 200, 'وقع العكسُ بيد 19 (' . $rev['code'] . ')');

$origAfter = $root->query("SELECT hours, obligation_type, billable, supplier_countable, operator_countable
                             FROM unit_time_log WHERE id=$LB")->fetch_assoc();
check($origAfter !== null, '**والأصلُ باقٍ** — لم يُمحَ');
check($origAfter == $origBefore,
      '**وبحاله حرفًا بحرف**: ' . $origBefore['hours'] . 'س · ' . $origBefore['obligation_type']
      . ' · billable=' . $origBefore['billable'] . ' — من قرأ التقريرَ أمسِ يجد ما قرأه');

$revId = intval($rev['reversal_line_id']);
check($revId > 0, 'وأُضيف **سطرٌ عاكسٌ جديد** #' . $revId);
$rl = $root->query("SELECT hours, obligation_type, billable, supplier_countable, operator_countable,
                           objection_ref, cause_note, decided_by, entry_id
                      FROM unit_time_log WHERE id=$revId")->fetch_assoc();
check(abs((float) $rl['hours'] + (float) $origBefore['hours']) < 0.005,
      '**بساعاتٍ سالبةٍ تلغي الأصلَ جمعًا**: ' . $rl['hours'] . ' + ' . $origBefore['hours'] . ' = 0');
check($rl['obligation_type'] === $origBefore['obligation_type']
      && $rl['billable'] === $origBefore['billable'],
      'وبلقطة الأصل نفسِها — فالعكسُ يلغي **ما وقع** لا ما نتمناه');
check($rl['objection_ref'] === 'REV:' . $LB, 'وموسومٌ بمرجعه `REV:' . $LB . '` — صفرُ عمودٍ جديد');
check(intval($rl['decided_by']) === $FINMGR, 'ومختومٌ بمن عكسه (' . $rl['decided_by'] . ')');
check(strpos($rl['cause_note'], 'محضر 12') !== false, 'وسببُه الموثَّقُ في سطره: ' . $rl['cause_note']);
check(intval($rl['entry_id']) === $EB, 'وهو من الواقعة نفسِها');

$corrId = intval($rev['correction_line_id']);
check($corrId > 0, 'وسطرُ **التصحيح** #' . $corrId . ' بالحكم الجديد — عكسٌ ثم إعادةُ تسجيل');
$cl = $root->query("SELECT hours, obligation_type, billable, objection_ref
                      FROM unit_time_log WHERE id=$corrId")->fetch_assoc();
check(abs((float) $cl['hours'] - (float) $origBefore['hours']) < 0.005, 'بساعاتٍ موجبةٍ كالأصل (' . $cl['hours'] . ')');
check($cl['obligation_type'] === 'equipment_readiness', 'وبالبند المصحَّح `equipment_readiness`');
check((int) $cl['billable'] === 0, 'وبحكمه المشتقِّ من المصفوفة (billable=0) — لا حكمَ ملفَّق');

$sum = (float) $root->query("SELECT COALESCE(SUM(hours),0) h FROM unit_time_log WHERE entry_id=$EB")->fetch_assoc()['h'];
check(abs($sum - (float) $origBefore['hours']) < 0.005,
      '**ومجموعُ ساعات الواقعة لم يتغيّر** (' . number_format($sum, 2) . 'س) — العكسُ لا يخلق زمنًا ولا يُفنيه');

head('⑦ حدثُ العكس منشورٌ بـ`reverses_event_id` صحيح');
check(intval($rev['event_id']) > 0, 'حدثُ العكس مدوَّنٌ في الجذر #' . intval($rev['event_id']));
check(intval($rev['reverses_event_id']) === $decEvt,
      '**و`reverses_event_id` يشير إلى حدث القرار** #' . $decEvt . ' — النسبُ في الجذر لا في عمودٍ يُصان');
$be = $root->query("SELECT event_key, reverses_event_id, idempotency_key, payload
                      FROM ems_business_events WHERE id=" . intval($rev['event_id']))->fetch_assoc();
check($be['event_key'] === 'attribution.reversed',
      'ومفتاحُه `attribution.reversed` — لا `overridden` (حسمُ اعتراضٍ لم يمسّ مالًا)');
check(intval($be['reverses_event_id']) === $decEvt, 'والنسبُ مكتوبٌ في الصفّ نفسِه (' . $be['reverses_event_id'] . ')');
check($be['idempotency_key'] === 'attribution.reversed:line:' . $LB, 'وبمفتاح عطالةٍ صريح: ' . $be['idempotency_key']);
$pl = json_decode($be['payload'], true);
check(isset($pl['reversed_line_id']) && intval($pl['reversed_line_id']) === $LB
      && intval($pl['reversal_line_id']) === $revId,
      'وحمولتُه تحمل طرفَي النسب: الأصل #' . $LB . ' ← العاكس #' . $revId);

head('⑧ لا يُعكس حدثٌ مرتين · ولا تُعاد المروحةُ تلقائيًّا');
$again = ATT::reverse($conn, $gate, $CO, $LB, 'equipment_readiness', 'محاولةٌ ثانية', $FINMGR, '19');
check($again['ok'] && intval($again['reversal_line_id']) === $revId,
      'المحاولةُ الثانيةُ تعيد العاكسَ القائم #' . intval($again['reversal_line_id']) . ' بلا كتابة');
$rows1 = intval($root->query("SELECT COUNT(*) n FROM unit_time_log WHERE entry_id=$EB")->fetch_assoc()['n']);
check($rows1 === 3, 'وسطورُ الواقعة ثلاثةٌ لا أربعة: أصلٌ + عاكسٌ + تصحيح (' . $rows1 . ')');
$evs = intval($root->query("SELECT COUNT(*) n FROM ems_business_events
                             WHERE company_id=$CO AND entity_type='unit_entry' AND entity_id=$EB
                               AND event_key='attribution.reversed'")->fetch_assoc()['n']);
check($evs === 1, 'وحدثُ عكسٍ واحدٌ في الجذر لا اثنان (' . $evs . ')');

$fe = intval($root->query("SELECT COUNT(*) n FROM fin_financial_events
                            WHERE company_id=$CO AND contract_id=$CON")->fetch_assoc()['n']);
check($fe === 0, '**وصفرُ قيدٍ ماليٍّ جديدٍ وُلد من العكس** — توليدُ المال تحكمه بوابةُ التحويل، والسطرُ العاكسُ يترك الحسابَ متسقًا حتى دورتها');

} catch (\Throwable $t) {
    bad('استثناء: ' . $t->getMessage() . ' @ ' . $t->getFile() . ':' . $t->getLine());
}

head('⑨ الكنس');
$cleanup();
$left = intval($root->query("SELECT COUNT(*) n FROM unit_entries WHERE note LIKE '{$TAG}%'")->fetch_assoc()['n']);
$leftLinks = intval($root->query("SELECT COUNT(*) n FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref=$TSREF")->fetch_assoc()['n']);
check($left === 0 && $leftLinks === 0, 'صفرُ بقايا');

fwrite(STDOUT, "\n" . str_repeat('═', 60) . "\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
