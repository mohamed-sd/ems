<?php
/**
 * update0004 · الموجة ⑪ — حزام بنية TKT-01 (TKT-01→06)
 * التشغيل: php tests/tkt_structure_test.php
 * يثبت: توسيع القائم بلا هدم · الطبائع الثمانية · closure_policy · المسارات
 * الشرطية · حقلا السرية · head_state المشتق وخرط الموروث · الجداول الثمانية.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

fwrite(STDOUT, "\n══ update0004 موجة ⑪ — بنية TKT-01 ══\n");

head('① الجداول الثمانية الجديدة');
foreach (array('ticket_type_workstreams','ticket_workstreams','ticket_holds','ticket_escalations',
    'ticket_participants','ticket_responses','ticket_effects','ticket_communications') as $t) {
    $r = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}'");
    check($r && $r->num_rows === 1, $t);
}

head('② التوسيع لا الهدم');
$r = $conn->query("SELECT COUNT(*) c FROM tickets")->fetch_assoc();
check(intval($r['c']) >= 19, 'صفوف tickets الحية باقية (' . $r['c'] . ')');
foreach (array('ticket_watchers','ticket_escalation_rules','ticket_events','ticket_sla_policies') as $t) {
    $r = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}'");
    check($r && $r->num_rows === 1, "السلف {$t} باقٍ");
}

head('③ الطبائع الثمانية وسياسات الإغلاق');
$r = $conn->query("SELECT COUNT(DISTINCT nature) c FROM ticket_types WHERE nature IS NOT NULL")->fetch_assoc();
check(intval($r['c']) === 8, 'الطبائع الثمانية كلها ممثلة (' . $r['c'] . ')');
$r = $conn->query("SELECT COUNT(*) c FROM ticket_types WHERE nature IS NULL")->fetch_assoc();
check(intval($r['c']) === 0, 'لا نوع بلا طبيعة — الخرط شامل');
$r = $conn->query("SELECT closure_policy FROM ticket_types WHERE code='safety_emergency'")->fetch_assoc();
check($r && $r['closure_policy'] === 'committee', 'طارئ السلامة: لا إغلاق آلي (committee)');
$r = $conn->query("SELECT COUNT(*) c FROM ticket_types WHERE nature IN ('incident','emergency') AND closure_policy='auto'")->fetch_assoc();
check(intval($r['c']) === 0, 'صفر حادثة أو طارئ بإغلاق آلي (§5-⑥)');

head('④ المسارات الشرطية');
$r = $conn->query("SELECT COUNT(*) c FROM ticket_type_workstreams")->fetch_assoc();
check(intval($r['c']) >= 20, 'تعريفات المسارات مبذورة (' . $r['c'] . ')');
$r = $conn->query("SELECT COUNT(*) c FROM ticket_type_workstreams WHERE activation_mode='conditional' AND trigger_event='StockUnavailable'")->fetch_assoc();
check(intval($r['c']) >= 1, 'مسار المشتريات شرطي بحدث StockUnavailable — لا يُفتح بالإنشاء');
$r = $conn->query("SELECT COUNT(*) c FROM ticket_types tt WHERE NOT EXISTS
                   (SELECT 1 FROM ticket_type_workstreams w WHERE w.ticket_type_id=tt.id)")->fetch_assoc();
check(intval($r['c']) === 0, 'لا نوع بلا مسار — فلا بلاغ بلا مالك');
$r = $conn->query("SELECT COUNT(*) c FROM ticket_type_workstreams w JOIN ticket_types t ON t.id=w.ticket_type_id
                   WHERE t.category='equipment' AND t.nature='incident' AND w.workstream_type='maintenance'")->fetch_assoc();
check(intval($r['c']) >= 1, 'عطل المعدة له مسار صيانة إلزامي');

head('⑤ رأس البلاغ الموسع');
foreach (array('head_state','confidentiality','operational_summary','private_details','source_screen',
    'source_entity_type','is_anonymous','duplicate_of_ticket_id','recurrence_group_id','site_id','shift_no') as $col) {
    $r = $conn->query("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tickets' AND column_name='{$col}'");
    check($r && $r->num_rows === 1, "العمود {$col}");
}
$r = $conn->query("SELECT COUNT(*) c FROM tickets WHERE stage IN ('done','closed','cancelled') AND head_state='open'")->fetch_assoc();
check(intval($r['c']) === 0, 'خرط الموروث: المقفل قديمًا head_state=closed');

head('⑥ UQ المسار على (البلاغ×النوع×التسلسل)');
$tk = intval($conn->query("SELECT id FROM tickets ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$conn->query("DELETE FROM ticket_workstreams WHERE tk_id={$tk} AND workstream_type='__t11'");
$ok1 = $conn->query("INSERT INTO ticket_workstreams (tk_id, workstream_type, seq_no, mandatory) VALUES ({$tk}, '__t11', 1, 0)");
$ok2 = $conn->query("INSERT INTO ticket_workstreams (tk_id, workstream_type, seq_no, mandatory) VALUES ({$tk}, '__t11', 2, 0)");
$dup = $conn->query("INSERT INTO ticket_workstreams (tk_id, workstream_type, seq_no, mandatory) VALUES ({$tk}, '__t11', 1, 0)");
check($ok1 === true && $ok2 === true, 'مساران للنوع نفسه بتسلسلين — مقبول (فحص ثم إصلاح)');
check($dup === false && $conn->errno === 1062, 'وتكرار التسلسل يُرفض');
$conn->query("DELETE FROM ticket_workstreams WHERE tk_id={$tk} AND workstream_type='__t11'");

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
