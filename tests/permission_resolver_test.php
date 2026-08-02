<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0004 · الموجة ⑦ — حزام الاشتقاق: S1·S3·S5·S6·S12·S16·S17 (SEC-14→18)
 * التشغيل: php tests/permission_resolver_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/app/Services/Security/PermissionResolver.php';
require_once dirname(__DIR__) . '/app/Services/Security/PositionService.php';
require_once dirname(__DIR__) . '/app/Services/Security/PermissionExplainService.php';
require_once dirname(__DIR__) . '/app/Services/Security/ExpiryJob.php';
require_once dirname(__DIR__) . '/app/Services/Org/AssignmentService.php';

use App\Services\Security\PermissionResolver as PR;
use App\Services\Security\PositionService as PS;
use App\Services\Security\PermissionExplainService as PEX;
use App\Services\Security\ExpiryJob as EJ;
use App\Services\Org\AssignmentService as ASV;

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$MARK = 'PR7T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    foreach (array(
        "DELETE s FROM permission_approval_steps s JOIN permission_change_requests r ON r.req_id=s.req_id WHERE r.reason LIKE '%{$MARK}%'",
        "DELETE FROM permission_change_requests WHERE reason LIKE '%{$MARK}%'",
        "DELETE FROM effective_permissions WHERE person_id IN (SELECT person_id FROM persons WHERE full_name LIKE '%{$MARK}%')",
        "DELETE FROM permission_audit_events WHERE person_id IN (SELECT person_id FROM persons WHERE full_name LIKE '%{$MARK}%')",
        "DELETE FROM permission_exceptions WHERE reason LIKE '%{$MARK}%'",
        "DELETE FROM person_positions WHERE person_id IN (SELECT person_id FROM persons WHERE full_name LIKE '%{$MARK}%')",
        "DELETE FROM person_relationships WHERE person_id IN (SELECT person_id FROM persons WHERE full_name LIKE '%{$MARK}%')",
        "DELETE tp FROM template_permissions tp JOIN permission_template_versions v ON v.ver_id=tp.template_version_id WHERE v.change_reason LIKE '%{$MARK}%'",
        "DELETE FROM permission_template_versions WHERE change_reason LIKE '%{$MARK}%'",
        "DELETE l FROM assignment_reporting_lines l JOIN org_assignments a ON a.asg_id=l.asg_id WHERE a.decision_ref LIKE '%{$MARK}%'",
        "DELETE g FROM assignment_audit g JOIN org_assignments a ON a.asg_id=g.asg_id WHERE a.decision_ref LIKE '%{$MARK}%'",
        "DELETE c FROM assignment_capabilities c JOIN org_assignments a ON a.asg_id=c.asg_id WHERE a.decision_ref LIKE '%{$MARK}%'",
        "DELETE FROM org_assignments WHERE decision_ref LIKE '%{$MARK}%'",
        "DELETE FROM persons WHERE full_name LIKE '%{$MARK}%'",
    ) as $q) { $conn->query($q); }
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0004 موجة ⑦ — الاشتقاق ══\n");

// ── البذر: محتوى قوالب منشور (عائلة الصيانة · مستوى المنفذ · مسمى فني) ──
$mkVersion = function ($kind, $key, array $perms) use ($conn, $MARK) {
    $t = $conn->query("SELECT tpl_id FROM permission_templates WHERE tpl_kind='{$kind}' AND key_code='{$key}'")->fetch_assoc();
    $tid = intval($t['tpl_id']);
    $v = intval($conn->query("SELECT COALESCE(MAX(version),0)+1 v FROM permission_template_versions WHERE tpl_id={$tid}")->fetch_assoc()['v']);
    $conn->query("INSERT INTO permission_template_versions (tpl_id, version, state, change_reason, effective_from)
                  VALUES ({$tid}, {$v}, 'published', '{$MARK}', CURDATE())");
    $ver = intval($conn->insert_id);
    foreach ($perms as $p) {
        $stmt = $conn->prepare("INSERT INTO template_permissions (template_version_id, dimension, permission_code, scope_rule, amount_cap, effect)
                                VALUES (?, ?, ?, ?, ?, ?)");
        $dim = $p[0]; $code = $p[1]; $sr = $p[2]; $cap = $p[3]; $ef = $p[4];
        $stmt->bind_param('isssds', $ver, $dim, $code, $sr, $cap, $ef);
        $stmt->execute();
        $stmt->close();
    }
    return $ver;
};
$mkVersion('family', 'fam_maintenance', array(
    array('visibility', 'mnt.order.view', null, null, 'grant'),
    array('action', 'mnt.order.execute', null, null, 'grant'),
    array('approval', 'mnt.order.approve', null, 100000.00, 'grant'),
));
$mkVersion('level', 'lvl_executor', array(
    array('action', 'timesheet.entry.create', null, null, 'grant'),
    array('action', 'mnt.order.approve', null, null, 'deny'), // المنفذ لا يعتمد — منع صريح
));
$mkVersion('level', 'lvl_supervisor', array(
    array('approval', 'mnt.order.approve', null, 50000.00, 'grant'), // S16: سقف أقل
));
$mkVersion('title', 'jt_3', array(
    array('visibility', 'mnt.parts.view', null, null, 'grant'),
));

$conn->query("INSERT INTO persons (full_name) VALUES ('فني {$MARK}')");
$P = intval($conn->insert_id);
$conn->query("INSERT INTO person_relationships (person_id, company_id, relation_code, valid_from) VALUES ({$P}, {$CO}, 'rel_employee', CURDATE())");
$SITE = intval($conn->query("SELECT id FROM sites WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$SITE_B = intval($conn->query("SELECT id FROM sites WHERE company_id={$CO} ORDER BY id LIMIT 1 OFFSET 1")->fetch_assoc()['id']);

head('S1 — الاشتقاق: موظف بطبقاته السبع = الصلاحيات آليًّا');
$conn->query("INSERT INTO person_positions (person_id, company_id, relation_code, family_code, level_code,
    title_code, scope_type, scope_id, valid_from) VALUES
    ({$P}, {$CO}, 'rel_employee', 'fam_maintenance', 'lvl_executor', 'jt_3', 'site', {$SITE}, CURDATE())");
$n = PR::rebuild($conn, $P, $CO);
check($n >= 3, "المشتق بُني آليًّا ({$n} صفًّا) بلا نقرة صلاحية واحدة");
$r = PR::resolve($conn, $P, $CO, 'mnt.order.view', 'site', $SITE);
check($r['allowed'], 'يرى أوامر الصيانة في موقعه (من قالب العائلة)');
$r = PR::resolve($conn, $P, $CO, 'timesheet.entry.create', 'site', $SITE);
check($r['allowed'], 'يدخل ساعاته (من قالب المستوى)');

head('S2/النطاق — لا يرى مواقع أخرى');
$r = PR::resolve($conn, $P, $CO, 'mnt.order.view', 'site', $SITE_B);
check(!$r['allowed'], 'موقع آخر → مرفوض (نطاق المركز يقيّد القالب)');

head('المنع يغلب المنح — deny المنفذ فوق grant العائلة');
$r = PR::resolve($conn, $P, $CO, 'mnt.order.approve', 'site', $SITE);
check(!$r['allowed'] && mb_strpos($r['reason'], 'المنع يغلب') !== false, 'الاعتماد ممنوع: deny مستواه يغلب grant عائلته');

head('422 — صلاحية بلا نطاق');
$r = PR::resolve($conn, $P, $CO, 'mnt.order.view', null, null);
check($r['code'] === 422, 'بلا نطاق → 422 بنيويًّا');

head('S12 — التفسير');
$x = PEX::explain($conn, $P, $CO, 'mnt.order.view', 'site:' . $SITE);
check($x['allowed'] === true && count($x['sources']) >= 1 && $x['sources'][0]['kind'] === 'family',
    'التفسير: المصدر والنطاق في استجابة واحدة');
$x = PEX::explain($conn, $P, $CO, 'mnt.order.approve', 'site:' . $SITE);
check($x['allowed'] === false && $x['reason'] === 'deny' && $x['source']['kind'] === 'level',
    'وعند المنع: مصدر المنع وحكمه (§4.2-⑦)');

head('S16 — السقف الأقل يسري');
$conn->query("INSERT INTO persons (full_name) VALUES ('مشرف {$MARK}')");
$P2 = intval($conn->insert_id);
$conn->query("INSERT INTO person_relationships (person_id, company_id, relation_code, valid_from) VALUES ({$P2}, {$CO}, 'rel_employee', CURDATE())");
$conn->query("INSERT INTO person_positions (person_id, company_id, relation_code, family_code, level_code,
    title_code, scope_type, scope_id, valid_from) VALUES
    ({$P2}, {$CO}, 'rel_employee', 'fam_maintenance', 'lvl_supervisor', 'jt_12', 'site', {$SITE}, CURDATE())");
$r = PR::resolve($conn, $P2, $CO, 'mnt.order.approve', 'site', $SITE);
check($r['allowed'] && floatval($r['amount_cap']) === 50000.00,
    'قالبان بسقفين 100,000 و50,000 → النافذ 50,000 (وُجد ' . var_export($r['amount_cap'], true) . ')');

head('S17 — التقاطع لا الاتحاد: تكليف الموقع يقيّد قالب كل المواقع');
$decider = intval($conn->query("SELECT id FROM users WHERE role='1' AND company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$unitId = intval($conn->query("SELECT unit_id FROM org_units WHERE company_id={$CO} AND unit_code='maintenance'")->fetch_assoc()['unit_id']);
$asg = ASV::create($conn, array('company_id' => $CO, 'person_id' => $P2, 'assignment_type_code' => 'site_maintenance_officer',
    'org_unit_id' => $unitId, 'scope_type' => 'site', 'scope_id' => $SITE,
    'valid_from' => date('Y-m-d'), 'valid_to' => date('Y-m-d', strtotime('+90 days')),
    'decided_by_person_id' => $decider, 'decision_ref' => $MARK,
    'capabilities' => array(array('code' => 'unit.chain.approve.tech')),
    'reporting_lines' => array(
        array('line_type' => 'operational', 'reports_to_assignment_id' => 0),
        array('line_type' => 'functional', 'reports_to_assignment_id' => 0))));
// خطا التبعية يحتاجان مرجعا صحيحا — أعد بمرجع ذاتي بسيط إن فشل
if (!$asg['ok']) {
    $conn->query("INSERT INTO org_assignments (company_id, person_id, assignment_type_code, org_unit_id,
        scope_type, scope_id, valid_from, valid_to, decided_by_person_id, decision_ref, state)
        VALUES ({$CO}, {$P2}, 'maintenance_mgr', {$unitId}, 'site', {$SITE},
        CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), {$decider}, '{$MARK}', 'active')");
    $aid = intval($conn->insert_id);
    $conn->query("INSERT INTO assignment_capabilities (asg_id, capability_code) VALUES ({$aid}, 'unit.chain.approve.tech')");
}
$r = PR::resolve($conn, $P2, $CO, 'unit.chain.approve.tech', 'site', $SITE);
check($r['allowed'], 'الاعتماد الفني في موقع تكليفه — يُقبل');
$r = PR::resolve($conn, $P2, $CO, 'unit.chain.approve.tech', 'site', $SITE_B);
check(!$r['allowed'] && mb_strpos($r['reason'], 'التقاطع') !== false,
    'وفي موقع آخر → مرفوض: النطاقان يتقاطعان ولا يتحدان');

head('S3 — الترقية بعد الموافقات والقالب يتغير لا الصلاحيات فردًا');
$oldP = intval($conn->query("SELECT p_id FROM person_positions WHERE person_id={$P} AND state='active' LIMIT 1")->fetch_assoc()['p_id']);
$sub = PS::submit($conn, array('person_id' => $P, 'company_id' => $CO, 'relation_code' => 'rel_employee',
    'family_code' => 'fam_maintenance', 'level_code' => 'lvl_supervisor', 'title_code' => 'jt_12',
    'scope_type' => 'site', 'scope_id' => $SITE, 'valid_from' => date('Y-m-d'),
    'is_primary' => 1, 'end_previous_p_id' => $oldP, // الترقية تُنهي مركز المنفذ — وإلا بقي deny مستواه يغلب
    'reason' => 'ترقية ' . $MARK, 'created_by' => $decider));
check($sub['ok'] && $sub['code'] === 201, "الترقية أُرسلت (طلب #{$sub['req_id']} · مركز معلق #{$sub['p_id']})");
$act = PS::activate($conn, $sub['req_id'], $decider);
check(!$act['ok'] && $act['code'] === 409, 'لا تفعيل قبل اكتمال الموافقات');
$conn->query("UPDATE permission_approval_steps SET decision='approve', at=NOW(), approver_person_id={$decider} WHERE req_id=" . intval($sub['req_id']));
$act = PS::activate($conn, $sub['req_id'], $decider);
check($act['ok'], 'اكتملت الموافقات → التفعيل الآلي (⑪)');
$r = PR::resolve($conn, $P, $CO, 'mnt.order.approve', 'site', $SITE);
check($r['allowed'], 'وبالترقية صار يعتمد (قالب المشرف) — القالب تغيّر لا صلاحيات فردية');

head('S6 — النقل: القديمة تسقط فورًا');
$sub2 = PS::submit($conn, array('person_id' => $P, 'company_id' => $CO, 'relation_code' => 'rel_employee',
    'family_code' => 'fam_warehouse', 'level_code' => 'lvl_officer', 'title_code' => 'jt_5',
    'scope_type' => 'site', 'scope_id' => $SITE_B, 'valid_from' => date('Y-m-d'),
    'is_primary' => 1, 'reason' => 'نقل ' . $MARK, 'created_by' => $decider));
check(!$sub2['ok'] && $sub2['code'] === 422 && mb_strpos($sub2['reason'], 'نقل بلا إنهاء') !== false,
    'نقل بلا إنهاء القديم → 422');

head('S5 — السقوط الآلي');
$conn->query("INSERT INTO permission_exceptions (company_id, person_id, permission_code, scope_rule, reason, valid_from, valid_to)
              VALUES ({$CO}, {$P}, 'export.sensitive.x', 'site:{$SITE}', '{$MARK}', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 HOUR))");
$res = EJ::run($conn, $CO);
check($res['exceptions'] >= 1, "ExpiryJob أسقط الاستثناء المنقضي ({$res['exceptions']})");
$r = PR::resolve($conn, $P, $CO, 'export.sensitive.x', 'site', $SITE);
check(!$r['allowed'], 'والصلاحية سقطت في اللحظة نفسها');
$aud = $conn->query("SELECT COUNT(*) c FROM permission_audit_events WHERE person_id={$P} AND event_type='expired'")->fetch_assoc();
check(intval($aud['c']) >= 1, 'PermissionExpired سطر في سجل التدقيق المستقل (S27)');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
