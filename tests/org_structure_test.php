<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0004 · الموجة ① — اختبار قبول: بنية ORG-01 (ORG-01→ORG-05)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/org_structure_test.php
 *
 * ما يُثبته (ORG-01 §7 والخطة موجة ①):
 *   ① الجداول الأحد عشر قائمة والمنظر v_org_unit_heads معها.
 *   ② البذور: 14 وحدة (§1.1) · 13 نوع تكليف (5 مركزية + 8 موقعية كلها
 *      requires_functional_line=1) · 9 أنواع أذونات · 24 صف مصفوفة موافقات.
 *   ③ القيد الحرج: «مدير حركة واحد نشط لكل موقع» بفهرس فريد مشروط —
 *      الثاني النشط يُرفض من القاعدة نفسها، والمعلَّق لا يُحسب.
 *   ④ لا تكليف مفتوح المدة: valid_to لا يقبل NULL.
 *   ⑤ ORG-02: head_person_id مشتق — يظهر في المنظر مع التكليف النافذ
 *      ويختفي بإنهائه، ولا عمود يُكتب في org_units.
 *
 * البذر معزول بعلامة ويُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$MARK = 'ORG1T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE l FROM assignment_reporting_lines l JOIN org_assignments a ON a.asg_id = l.asg_id
                   WHERE a.decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE g FROM assignment_audit g JOIN org_assignments a ON a.asg_id = g.asg_id
                   WHERE a.decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE c FROM assignment_capabilities c JOIN org_assignments a ON a.asg_id = c.asg_id
                   WHERE a.decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM org_assignments WHERE decision_ref LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0004 موجة ① — بنية ORG-01 ══\n");

head('① الجداول والمنظر');
$tables = array('org_units','org_assignment_types','org_assignments','assignment_capabilities',
    'assignment_reporting_lines','assignment_audit','permit_types','permit_requests',
    'permit_required_approvals','permit_approval_actions','permit_status_history');
foreach ($tables as $t) {
    $r = $conn->query("SELECT 1 FROM information_schema.tables
                       WHERE table_schema = DATABASE() AND table_name = '{$t}'");
    check($r && $r->num_rows === 1, "الجدول {$t} قائم");
}
$r = $conn->query("SELECT 1 FROM information_schema.views
                   WHERE table_schema = DATABASE() AND table_name = 'v_org_unit_heads'");
check($r && $r->num_rows === 1, 'المنظر v_org_unit_heads قائم');
$r = $conn->query("SELECT 1 FROM information_schema.columns
                   WHERE table_schema = DATABASE() AND table_name = 'org_units'
                     AND column_name = 'head_person_id'");
check($r && $r->num_rows === 0, 'org_units بلا عمود head_person_id — الاشتقاق لا الكتابة (ORG-02)');

/* ══ **إحصاءٌ مجمَّدٌ لا ثابت.** كانت هذه الفحوصُ تشترط أعدادًا بعينها (14 وحدةً
     · 8 أنواعٍ موقعيةٍ · 9 أنواعِ أذوناتٍ · 24 صفَّ مصفوفة) — وهي **أعدادُ
     البذرةِ يومَ كُتب الفاحص**. والهيكلُ التنظيميُّ ينمو بطبيعته: صارت الوحداتُ
     22 · والأنواعُ الموقعيةُ 9 (التاسعُ `site_movement_mgr` **بقرارِ مالكٍ
     صريحٍ** في هجرة `2027_02_13`) · وأنواعُ الأذوناتِ 20.
     فكانت أربعةُ فحوصٍ ترسب **لأن النظامَ نما**، لا لأن حكمًا انكسر — وهذا
     يُقرأ عطلًا فيُطارَد، أو يُهمَل فيُهمَل معه ما يجاوره.
   ⇒ **الحكمُ يُقاس والإحصاءُ يُعلَن**:
     · الأرقامُ الموثَّقةُ تصير **حدًّا أدنى** — نموٌّ يمرُّ، و**نقصٌ عن الموثَّقِ
       يرسب** (فحذفُ نوعٍ أو وحدةٍ عطلٌ حقيقيّ).
     · والثابتُ الحقيقيُّ يُقاس بنفسِه: «كلُّ موقعيٍّ يلزمه خطٌّ وظيفيّ» =
       `sf === s` لا `sf === 8` — فيصدُق عند كلِّ عددٍ ويرسب عند أولِ مخالف.
       (وهو الآن **9/9 صحيح** — وكان يرسب على رقمٍ لا على حكم.) */
head('② البذور — حدٌّ أدنى موثَّقٌ وثوابتُ بنيةٍ تُقاس بنفسها');
$r = $conn->query("SELECT COUNT(*) c FROM org_units WHERE company_id = {$CO}")->fetch_assoc();
check(intval($r['c']) >= 14, "الوحدات ≥ 14 الموثَّقة (وُجد {$r['c']})");
$r = $conn->query("SELECT SUM(level='central') c, SUM(level='site') s,
                          SUM(level='site' AND requires_functional_line=1) sf
                   FROM org_assignment_types")->fetch_assoc();
check(intval($r['c']) >= 5, "الأنواع المركزية ≥ 5 الموثَّقة (وُجد {$r['c']})");
check(intval($r['s']) >= 8, "الأنواع الموقعية ≥ 8 الموثَّقة (وُجد {$r['s']})");
check(intval($r['sf']) === intval($r['s']),
    'كل موقعي requires_functional_line=1 (§2⑦) — ' . intval($r['sf']) . '/' . intval($r['s']));
$r = $conn->query("SELECT COUNT(*) c FROM permit_types")->fetch_assoc();
check(intval($r['c']) >= 9, "أنواع الأذونات ≥ 9 الموثَّقة (وُجد {$r['c']})");
$r = $conn->query("SELECT COUNT(*) c FROM permit_required_approvals")->fetch_assoc();
check(intval($r['c']) >= 24, "مصفوفة الموافقات ≥ 24 صفًّا الموثَّقة (وُجد {$r['c']})");
$r = $conn->query("SELECT COUNT(*) c FROM permit_required_approvals r
                   JOIN permit_types p ON p.permit_type_code = r.permit_type_code
                   WHERE r.seq_no = 1 AND r.approver_role <> 'movement'
                     AND p.permit_type_code IN ('equipment_site_entry','equipment_service_entry',
                         'equipment_service_exit','equipment_site_exit','worker_final_exit')")->fetch_assoc();
check(intval($r['c']) === 0, 'الحركة والتشغيل أول موافق حيث نصّت §5');

head('③ القيد الحرج — مدير حركة واحد نشط لكل موقع');
$unitId = intval($conn->query("SELECT unit_id FROM org_units WHERE company_id={$CO} AND unit_code='movement'")->fetch_assoc()['unit_id']);
$siteId = intval($conn->query("SELECT id FROM sites WHERE company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$ins = function ($person, $state, $from = '2026-08-01', $to = '2027-07-31', $type = 'site_movement_mgr') use ($conn, $CO, $unitId, $siteId, $MARK) {
    return $conn->query("INSERT INTO org_assignments (company_id, person_id, assignment_type_code,
        org_unit_id, scope_type, scope_id, valid_from, valid_to, decided_by_person_id, decision_ref, state)
        VALUES ({$CO}, {$person}, '{$type}', {$unitId}, 'site', {$siteId},
                '{$from}', '{$to}', 1, '{$MARK}', '{$state}')");
};
check($ins(9001, 'active') === true, 'الأول النشط يُقبل');
$dup = $ins(9002, 'active', '2026-09-01');
check($dup === false && $conn->errno === 1062, "الثاني النشط للموقع نفسه يُرفض من القاعدة (errno={$conn->errno})");
check($ins(9003, 'suspended', '2026-10-01') === true, 'المعلَّق لا يحسبه القيد — النشط وحده');
check($ins(9004, 'active', '2026-08-01', '2027-07-31', 'site_maintenance_officer') === true,
      'نوع موقعي آخر للموقع نفسه يُقبل — القيد على مدير الحركة وحده');

head('④ لا تكليف مفتوح المدة');
$r = $conn->query("INSERT INTO org_assignments (company_id, person_id, assignment_type_code,
    org_unit_id, scope_type, scope_id, valid_from, valid_to, decided_by_person_id, decision_ref, state)
    VALUES ({$CO}, 9005, 'site_operators_officer', {$unitId}, 'site', {$siteId},
            '2026-08-01', NULL, 1, '{$MARK}', 'active')");
check($r === false, 'valid_to = NULL يُرفض — لا تكليف مفتوح المدة (§2④)');

head('⑤ ORG-02 — الرأس مشتق من التكليف النافذ');
$maintUnit = intval($conn->query("SELECT unit_id FROM org_units WHERE company_id={$CO} AND unit_code='maintenance'")->fetch_assoc()['unit_id']);
$conn->query("INSERT INTO org_assignments (company_id, person_id, assignment_type_code,
    org_unit_id, scope_type, scope_id, valid_from, valid_to, decided_by_person_id, decision_ref, state)
    VALUES ({$CO}, 9100, 'maintenance_mgr', {$maintUnit}, 'site_group', 0,
            CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 1, '{$MARK}', 'active')");
$asg = intval($conn->insert_id);
$r = $conn->query("SELECT head_person_id FROM v_org_unit_heads WHERE unit_id = {$maintUnit}")->fetch_assoc();
check(intval($r['head_person_id']) === 9100, 'رأس الصيانة يُشتق من التكليف النافذ');
$conn->query("UPDATE org_assignments SET state = 'ended' WHERE asg_id = {$asg}");
$r = $conn->query("SELECT head_person_id FROM v_org_unit_heads WHERE unit_id = {$maintUnit}")->fetch_assoc();
check($r['head_person_id'] === null, 'إنهاء التكليف يُسقط الرأس في اللحظة نفسها — لا عمود مكتوب');

head('⑥ المفتاح الطبيعي للتكليف');
$dup2 = $ins(9006, 'ended', '2026-08-01');
check($dup2 === false && $conn->errno === 1062, 'UQ(company, type, scope, from) يمنع الازدواج');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
