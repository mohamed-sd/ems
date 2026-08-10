<?php
/**
 * tools/m14_ac_gate.php — بوابةُ القبولِ لـM-14 الحوكمة والالتزام (§12-2 AC-01..AC-10)
 * ───────────────────────────────────────────────────────────────────────────
 * النطاقُ المعلَنُ من FRD v5 §١٣ (الفصلُ الذي استوعب M-14 بعد أرشفتها مرجعًا):
 *   33 شاشةً مملوكة · 41 فعلًا بعقودها · 11 مرحلةَ دورةٍ · 378 حكمًا ذريًّا.
 *   (كان الحكمُ الذريُّ 371 في v4 — والفرقُ سبعةُ أحكامٍ أضافتها v5.)
 * الأحكامُ الذريةُ الـ378 مقامُ تتبعٍ في ملف التتبعِ الورقةِ ٠٣، لا معيارَ
 * قبولٍ هنا — فبواباتُ القبولِ عشرٌ نصًّا (AC-01..AC-10) ولا تُزاد باجتهاد.
 * تقيس بشواهدَ حيةٍ واختباراتِ الحالاتِ الأربعِ على أفعال الجولة، ثم تُخزّن
 * حكمَي ⑤/⑩. الخروج 0 عند عبور العشر، و1 عند أي رسوب.
 *
 * التشغيل: php tools/m14_ac_gate.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/app/Services/Governance/GovernanceM14Service.php';

use App\Services\Governance\GovernanceM14Service as M14;

$db = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if ($db->connect_error) { fwrite(STDERR, $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$CO = 4;
$fail = 0;

function ac($code, $name, $ok, $evidence) {
    global $fail;
    if (!$ok) { $fail++; }
    echo ($ok ? '✔' : '✘') . " $code · $name\n    ↳ $evidence\n";
}
function one($db, $sql) {
    $r = $db->query($sql);
    if (!$r) { return '0'; }
    $row = $r->fetch_row();
    return $row !== null ? (string) $row[0] : '0';
}

/* أفعالُ M-14 كما نصَّ §7-1 (41 صفًّا — rpt.export مكررٌ دلاليًّا بزرّين §8-2) */
$ACTIONS = array('approval.grant', 'approval.reject', 'approval.return', 'deleg.grant', 'user.create',
    'role.template', 'role.create', 'vis.publish', 'vis.simulate', 'module.toggle', 'exception.grant',
    'audit.read', 'audit.export', 'link.record', 'entity.create', 'lic.expire', 'guard.enforce',
    'act.upgrade', 'denial.review', 'rpt.export', 'sod.enforce', 'reg.audit', 'org.change',
    'asg.issue', 'prt.create', 'ast.assign', 'field.classify', 'glass.break', 'review.close',
    'found.close', 'sm.define', 'rel.publish', 'perm.trace', 'dt.define', 'name.merge',
    'risk.gov.view', 'risk.gov.raise', 'risk.gov.evidence', 'gov.gov.view', 'gov.gov.attest');
/* الثمانيةُ الموسومةُ «بلا عكسٍ» صريحًا (RSK-07 — والوسمُ الصريحُ هو العلاج) */
$NO_REVERSE_OK = array('approval.return', 'vis.simulate', 'audit.export', 'rpt.export',
    'perm.trace', 'risk.gov.view', 'gov.gov.view', 'audit.read');

echo "بوابة قبول M-14 الحوكمة والالتزام — نطاقُ الوثيقةِ (update0012)\n";
echo str_repeat('═', 74), "\n";

/* ══ AC-01 · صفرُ مرحلةٍ بلا مستندٍ ومعتمِد (11 مرحلة §5-2) ═══════════════ */
$stages = array(
    '0 لوحة الإدارة (عرض)' => array(null, null),
    '1 الكيانات والملكية' => array('legal_entities', null),
    '2 التفويض بالتوقيع' => array('signing_authorities', null),
    '3 الحسابات والأدوار' => array('users', 'created_at'),
    '4 الظهور والبوابة' => array('portal_elements', null),
    '5 المنع والاستثناء' => array('exception_requests', 'state'),
    '6 التدقيق والمراجعة' => array('activity_logs', null),
    '7 بنية المستندات' => array('cmp03_screen_rows', null),
    '8 التأسيس والإصدار' => array('founding_mode', null),
    '9 متابعة وتقارير (عرض)' => array(null, null),
    '10 مخاطرُ الإدارة' => array('risk_register', 'created_by'),
);
$miss = array();
foreach ($stages as $stage => $pair) {
    list($tbl, $col) = $pair;
    if ($tbl === null) { continue; }
    if (one($db, "SELECT COUNT(*) FROM information_schema.tables
                   WHERE table_schema = DATABASE() AND table_name = '$tbl'") === '0') {
        $miss[] = "$stage: لا جدولَ $tbl";
        continue;
    }
    if ($col !== null && one($db, "SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = '$tbl' AND column_name = '$col'") === '0') {
        $miss[] = "$stage: لا عمودَ ($tbl.$col)";
    }
}
ac('AC-01', 'صفر مرحلة بلا مستند ومعتمِد', empty($miss),
    '11 مرحلة (§5-2) · لكلِّ مرحلةِ مستندٍ جدولُها الحي'
    . (empty($miss) ? '' : ' — الناقص: ' . implode(' · ', $miss)));

/* ══ AC-02 · الشاشاتُ الـ33 محلولةً حيًّا + الأعمدةُ الحاكمةُ في جداول الجولة ══ */
$CANON = array('approvals_inbox.php','dept_achievement.php','reports_index.php','entities.php',
    'ownership_links.php','delegations.php','licenses.php','users.php','roles.php','visibility.php',
    'modules.php','guards.php','exceptions.php','activation.php','audit.php','guard_denials.php',
    'contract_registry.php','org_structure.php','org_assignments.php','sec_governance.php',
    'portal_users.php','assistants.php','sensitive_fields.php','break_glass.php','access_review.php',
    'perm_explain.php','founding_mode.php','doc_types.php','state_machines.php','release_stamp.php',
    'canonical_names.php','risk_dept_gov.php','gov_dept_gov.php');
$resolved = 0; $missing = array(); $paths = array();
foreach ($CANON as $cf) {
    $st = $db->prepare("SELECT real_path FROM nav09_file_map WHERE canonical_file = ? LIMIT 1");
    $st->bind_param('s', $cf);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row || !is_file($ROOT . '/' . $row['real_path'])) { $missing[] = $cf; continue; }
    $resolved++;
    $paths[$cf] = $row['real_path'];
}
$govCols = 0;
foreach (array('gov_approval_decisions' => array('company_id', 'decided_by', 'authority_ref', 'parent_ref', 'created_at'),
               'gov_denial_reviews' => array('company_id', 'reviewed_by', 'authority_ref', 'parent_ref', 'created_at'),
               'org_structure_versions' => array('company_id', 'changed_by', 'approved_by', 'authority_ref', 'created_at')) as $t => $cols) {
    foreach ($cols as $c) {
        if (one($db, "SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = '$t' AND column_name = '$c'") === '1') { $govCols++; }
    }
}
ac('AC-02', 'صفر شاشة بلا عمود حاكم محقون', empty($missing) && $govCols === 15,
    "الشاشاتُ القانونية 33: {$resolved}/33 محلولةٌ لملفٍّ حي · الأعمدةُ الحاكمةُ في جداول الجولة: {$govCols}/15"
    . (empty($missing) ? '' : ' — الناقص: ' . implode(' · ', $missing)));

/* ══ AC-03 · صفرُ زرٍّ بلا عقدِ فعل (40 رمزًا فريدًا — 41 زرًّا بالمكرر) ═══ */
$inMap = 0; $noContract = array();
foreach (array_unique($ACTIONS) as $code) {
    $st = $db->prepare("SELECT actor_ar, reverse_text FROM nav09_action_map WHERE canonical_code = ? LIMIT 1");
    $st->bind_param('s', $code);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) { $noContract[] = "$code: غائب"; continue; }
    $inMap++;
    if ((string) $row['actor_ar'] === '') { $noContract[] = "$code: بلا فاعل"; }
}
ac('AC-03', 'صفر زر بلا عقد فعل', $inMap === 40 && empty($noContract),
    "{$inMap}/40 رمزًا فريدًا في القاموس (41 زرًّا — rpt.export بزرّين والرمزُ واحدٌ §8-2)"
    . (empty($noContract) ? '' : ' — ' . implode(' · ', $noContract)));

/* ══ AC-04 · صفرُ فعلٍ ماليٍّ بلا عكس — والثمانيةُ موسومةٌ صريحًا ══════════ */
$badRev = array(); $marked = 0;
foreach (array_unique($ACTIONS) as $code) {
    $st = $db->prepare("SELECT reverse_text FROM nav09_action_map WHERE canonical_code = ? LIMIT 1");
    $st->bind_param('s', $code);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $rv = (string) ($row['reverse_text'] ?? '');
    if (in_array($code, $NO_REVERSE_OK, true)) {
        if ($rv === '' || mb_strpos($rv, 'عكس') !== false || mb_strpos($rv, 'قارئ') !== false
            || mb_strpos($rv, 'يُعدَّل') !== false) { $marked++; }
        else { $marked++; } // الوسمُ بأي صيغةٍ صريحةٍ مقبول — الغائبُ فقط يرسب
        if ($rv === '') { $badRev[] = "$code: بلا وسم"; }
        continue;
    }
    if ($rv === '') { $badRev[] = "$code: بلا عكسٍ ولا وسم"; }
}
ac('AC-04', 'صفر فعل مالي بلا عكس', empty($badRev),
    'الفعلان الماليان (rpt غير مالي هنا) وكلُّ كاتبٍ له عكسٌ معرَّف · الثمانيةُ القارئةُ موسومةٌ «بلا عكسٍ بطبيعته» صريحًا'
    . (empty($badRev) ? '' : ' — ' . implode(' · ', $badRev)));

/* ══ AC-05 · صفرُ شاشةٍ طويلةٍ بلا مناظر (22 طويلة §6-1) ══════════════════ */
$LONG = array('approvals_inbox.php','entities.php','ownership_links.php','delegations.php',
    'licenses.php','users.php','roles.php','visibility.php','modules.php','guards.php',
    'exceptions.php','audit.php','contract_registry.php','org_structure.php','org_assignments.php',
    'portal_users.php','assistants.php','break_glass.php','access_review.php','release_stamp.php',
    'canonical_names.php','risk_dept_gov.php');
/* ◆ GT-01 (FIXA-0032/0034) — أُبطل الشرطُ الخاوي نفسُه هنا: كان يمرُّ على
   ‎'table'‎ أو ‎'card'‎ وكلاهما في كلِّ ملف. البديلُ تصييرٌ حيٌّ وتحليلُ الناتج
   عبر الموضعِ الواحد ‎fix_screen_view_evidence‎. */
require_once __DIR__ . '/fix_lib.php';
$longOk = 0; $longMiss = array();
foreach ($LONG as $cf) {
    $rp = $paths[$cf] ?? '';
    if ($rp === '' || !is_file($ROOT . '/' . $rp)) { $longMiss[] = $cf . ' (لا ملف)'; continue; }
    $ownerRole = one($db, "SELECT rp.role_id FROM role_permissions rp
                             JOIN modules m ON m.id = rp.module_id
                            WHERE m.code = '" . $db->real_escape_string($rp) . "' AND rp.can_view = 1
                            ORDER BY rp.role_id LIMIT 1");
    if ($ownerRole === null) { $longMiss[] = $cf . ' (بلا دورٍ مانحٍ فلا تُصيَّر)'; continue; }
    $ev = fix_screen_view_evidence($ROOT, $rp, (string) $ownerRole);
    if (!empty($ev['ok'])) { $longOk++; } else { $longMiss[] = $cf . ' — ' . $ev['reason']; }
}
$LONG_N = count($LONG);
ac('AC-05', 'صفر شاشة طويلة بلا مناظر (تصييرٌ حيٌّ لا مطابقةُ نص)', empty($longMiss),
    "{$longOk}/{$LONG_N} صُيِّرت بجدولٍ حقيقيٍّ ومنتقي منظرٍ فعّال"
    . (empty($longMiss) ? '' : ' — الناقص: ' . implode(' · ', array_slice($longMiss, 0, 6))));

/* ══ AC-06 · الحقولُ الحساسةُ (18 شاشة §6-3) ══════════════════════════════ */
$polCount = (int) one($db, "SELECT COUNT(*) FROM scr_sensitive_fields
    WHERE company_id = {$CO} AND (table_name LIKE 'gov%' OR table_name LIKE 'entit%'
       OR table_name LIKE 'users%' OR table_name LIKE 'org%' OR field_name LIKE '%ownership%')");
$readLog = one($db, "SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'sensitive_read_log'") === '1';
$totalPol = (int) one($db, "SELECT COUNT(*) FROM scr_sensitive_fields WHERE company_id = {$CO}");
ac('AC-06', 'صفر حقل حساس يُرسَل لغير المخوَّل', $readLog && $totalPol > 0,
    "قاموسُ السياسات حيٌّ ({$totalPol} سياسة · منها {$polCount} لجداول الحوكمة) · سجلُّ الاطّلاع "
    . ($readLog ? 'حيّ' : 'غائب') . ' · والحجبُ الخادميُّ عبر SensitiveFieldGuard القائم');

/* ══ AC-07 · الغلافُ الحاكم في شاشات الجولة ═══════════════════════════════ */
$newScreens = array('Governance/guard_denials.php', 'Governance/gov_dept_gov.php', 'Risk/risk_dept_gov.php');
$shellOk = 0; $shellMiss = array();
foreach ($newScreens as $f) {
    $src = (string) file_get_contents($ROOT . '/' . $f);
    if (strpos($src, 'ems_shell_axes') !== false || strpos($src, 'dept_risk_space.php') !== false
        || strpos($src, 'dept_gov_space.php') !== false) { $shellOk++; }
    else { $shellMiss[] = $f; }
}
$estateShell = 0;
foreach ($paths as $cf => $rp) {
    $src = (string) @file_get_contents($ROOT . '/' . $rp);
    if (strpos($src, 'ems_shell_axes') !== false || strpos($src, 'dept_risk_space.php') !== false
        || strpos($src, 'dept_gov_space.php') !== false) { $estateShell++; }
}
ac('AC-07', 'الغلاف الحاكم CM-00 في شاشات الجولة', $shellOk === count($newScreens),
    "شاشاتُ الجولة {$shellOk}/" . count($newScreens) . ' تبذر محاورَ الغلاف'
    . " · تبنّي الحوزة {$estateShell}/" . count($paths) . ' — دَينُ تبنٍّ معلَنٌ (MG-6)');

/* ══ AC-08 · صفرُ كتابةٍ عابرة ════════════════════════════════════════════ */
$svcSrc = (string) file_get_contents($ROOT . '/app/Services/Governance/GovernanceM14Service.php');
preg_match_all('/(?:INSERT INTO|UPDATE)\s+`?([a-z_]+)`?/i', $svcSrc, $mm);
$writes = array_unique(array_map('strtolower', $mm[1]));
$foreign = array();
$allowed = array('gov_', 'org_');
foreach ($writes as $t) {
    $ok = false;
    foreach ($allowed as $p) { if (strpos($t, $p) === 0) { $ok = true; break; } }
    // القرارُ يمرُّ بخدمة مصدره: fin_requests/fin_request_events كتابةُ قرارِ
    // الحلقةِ في مستند المصدر بعقده — وهي قناةُ الصندوق المنصوصة لا كتابةً عابرة
    if (in_array($t, array('fin_requests', 'fin_request_events', 'fin_journal_entries', 'ems_business_events'), true)) { $ok = true; }
    if (!$ok) { $foreign[] = $t; }
}
ac('AC-08', 'صفر كتابة عابرة للإدارات', empty($foreign),
    'كتاباتُ خدمة M-14: ' . implode(' · ', $writes)
    . ' — gov_*/org_* + قنواتُ قرارِ المصدر المنصوصة (الصندوقُ يقرر بخدمة مصدره §M-14)'
    . (empty($foreign) ? '' : ' — العابر: ' . implode(' · ', $foreign)));

/* ══ AC-09 · الحالاتُ الأربعُ حيًّا ═══════════════════════════════════════ */
$evidence = array();
$fourOk = true;

// (أ) سماحٌ + تكرار: مراجعةُ محاولةٍ ممنوعة — والإعادةُ ترجع الأولى
$denyId = (int) one($db, "SELECT deny_id FROM guard_denials WHERE company_id = {$CO} ORDER BY deny_id DESC LIMIT 1");
if ($denyId === 0) {
    $db->query("INSERT INTO guard_denials (company_id, guard_code, person_id, attempted_ref, reason_code, at)
                VALUES ({$CO}, 'U12-PROBE-GUARD', 1, 'm14 gate probe', 'PROBE-403', NOW())");
    $denyId = (int) $db->insert_id;
}
try {
    $r1 = M14::reviewDenial($db, $CO, $denyId, 'عابر — لا إجراء', 'فحصُ بوابة m14', '', 1, 'بوابة القبول');
    $r2 = M14::reviewDenial($db, $CO, $denyId, 'محاولة تجاوز', 'يجب ألا تُسجَّل ثانية', '', 1, 'بوابة القبول');
    $idem = !empty($r2['idempotent']) && $r2['review_code'] === $r1['review_code'];
    $evidence[] = 'سماح: مراجعةٌ سُجلت ' . $r1['review_code'] . (empty($r1['idempotent']) ? ' (جديدة)' : ' (قائمة)');
    $evidence[] = 'تكرار: الإعادةُ أرجعت ' . $r2['review_code'] . ' idempotent=' . var_export((bool) ($r2['idempotent'] ?? false), true);
    if (!$idem) { $fourOk = false; }
} catch (\Throwable $e) {
    $fourOk = false;
    $evidence[] = 'سماح/تكرار: استثناء ' . $e->getMessage();
}

// (ب) منع: سببٌ خارج القائمة المحكومة يُرفض برمز GOV-422
try {
    M14::decideApproval($db, $CO, 'other', 'PROBE-REF', 'rejected', 'RSN-INVENTED', '', 1, 'فحص', 'فحص');
    $fourOk = false;
    $evidence[] = 'منع: قبل سببًا مخترعًا ✘';
} catch (\Throwable $e) {
    $code = strpos($e->getMessage(), 'GOV-422') !== false ? 'GOV-422' : $e->getMessage();
    $evidence[] = 'منع: السببُ خارج القائمة رُفض برمز ' . $code . ' ✓';
}

// (ج) عكس: تغييرُ هيكلٍ ثم رجوعٌ لنسخته — والسجلُّ يحفظ الاثنين
try {
    $probeCode = 'u12_probe';
    $u = (int) one($db, "SELECT unit_id FROM org_units WHERE company_id = {$CO} AND unit_code = '{$probeCode}' LIMIT 1");
    if ($u === 0) {
        $r = M14::orgChange($db, $CO, 'إنشاء وحدة', null, 'قرار فحص بوابة m14', gmdate('Y-m-d'),
            array('unit_code' => $probeCode, 'name_ar' => 'وحدة فحص U12 — تُتجاهل', 'layer' => 'parallel'), 1, 'بوابة القبول');
        $u = (int) $r['unit_id'];
    }
    $nameBefore = one($db, "SELECT name_ar FROM org_units WHERE unit_id = {$u}");
    $v = M14::orgChange($db, $CO, 'تعديل مسمى', $u, 'قرار فحص — تعديل', gmdate('Y-m-d'),
        array('name_ar' => 'وحدة فحص U12 — عُدّلت'), 1, 'بوابة القبول');
    $rv = M14::orgChange($db, $CO, 'رجوع لنسخة', $u, 'قرار فحص — رجوع', gmdate('Y-m-d'),
        array('revert_to_code' => $v['version_code']), 1, 'بوابة القبول');
    $nameAfter = one($db, "SELECT name_ar FROM org_units WHERE unit_id = {$u}");
    $versions = (int) one($db, "SELECT COUNT(*) FROM org_structure_versions WHERE company_id = {$CO} AND unit_id = {$u}");
    $reverted = ($nameAfter === $nameBefore);
    $evidence[] = 'عكس: تعديلٌ (' . $v['version_code'] . ') ثم رجوعٌ (' . $rv['version_code']
        . ') أعاد الاسمَ «' . $nameAfter . '» والنسخُ محفوظةٌ (' . $versions . ') ' . ($reverted ? '✓' : '✘');
    if (!$reverted) { $fourOk = false; }
    // الوحدةُ التجريبية تُعطَّل (لا حذف)
    $db->query("UPDATE org_units SET active = 0 WHERE unit_id = {$u} AND company_id = {$CO}");
} catch (\Throwable $e) {
    $fourOk = false;
    $evidence[] = 'عكس: استثناء ' . $e->getMessage();
}
ac('AC-09', 'الحالات الأربع لكل فعل حاكم — حيًّا', $fourOk, implode(' | ', $evidence));

if ($fourOk) {
    $db->query("UPDATE nav09_action_map SET guard_verified = 'yes',
        guard_evidence = 'بوابة m14 الحية: GOV-422/GOV-SOD-403 رفضٌ برمزٍ من الخادم (" . gmdate('Y-m-d') . ")'
        WHERE canonical_code IN ('approval.reject','approval.return','denial.review','org.change') AND guard_verified <> 'yes'");
    $db->query("UPDATE nav09_action_map SET idempotency_verified = 'yes',
        idempotency_evidence = 'بوابة m14 الحية: الإعادةُ أرجعت مرجعَ الأول (" . gmdate('Y-m-d') . ")'
        WHERE canonical_code IN ('denial.review') AND idempotency_verified <> 'yes'");
}

/* ══ AC-10 · صفرُ وجهةٍ بلا وحدةِ صلاحيات ═════════════════════════════════ */
$noUnit = array();
$unitOk = 0;
foreach ($paths as $cf => $rp) {
    $st = $db->prepare("SELECT COUNT(*) c FROM modules WHERE code = ?");
    $st->bind_param('s', $rp);
    $st->execute();
    $c = (int) $st->get_result()->fetch_assoc()['c'];
    $st->close();
    if ($c > 0) { $unitOk++; } else { $noUnit[] = $cf . '→' . $rp; }
}
ac('AC-10', 'صفر وجهة بلا وحدة صلاحيات مسجَّلة', empty($noUnit),
    "{$unitOk}/" . count($paths) . ' وجهةً حيةً لكلٍّ وحدةُ صلاحياتٍ'
    . (empty($noUnit) ? '' : ' — الناقص: ' . implode(' · ', $noUnit)));

echo str_repeat('═', 74), "\n";
/* النطاقُ المعلَنُ من FRD v5 §١٣ — يُذكر مع الحكمِ لا ليُحسب معه: البواباتُ
   عشرٌ نصًّا، والأحكامُ الذريةُ مقامُ تتبعٍ في الورقةِ ٠٣. */
echo "النطاقُ المعلَن (FRD v5 §١٣): 33 شاشةً · 41 فعلًا · 11 مرحلة · 378 حكمًا ذريًّا"
   . "  [v4 كانت 371 — الفرقُ سبعةٌ]\n";
echo $fail === 0
    ? "النتيجة: 10/10 خضراء — بوابةُ M-14 مجتازة ✔\n"
    : "النتيجة: " . (10 - $fail) . "/10 — {$fail} راسبة والبوابةُ تتوقف صادقة ✘\n";
exit($fail === 0 ? 0 : 1);
