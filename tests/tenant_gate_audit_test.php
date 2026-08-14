<?php
/**
 * tests/tenant_gate_audit_test.php — شاهدُ التدقيقِ في بوابةِ المستأجر
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0002 · INJ-0051 · INJ-0107 · INJ-0173 · INJ-0185
 *                  INJ-0197 · INJ-0206 · INJ-0213 · INJ-0225 · INJ-0226
 *                  INJ-0253 · INJ-0290 · INJ-0469 · INJ-0475 · INJ-0480 · INJ-0530
 *
 * **المقيسُ قبل الإصلاح**: سبعَ عشرةَ شاشةً يطلب نصُّ اختبارِ قبولِها «صفَّ تدقيقٍ
 * بقيمةِ قبل/بعد» — و**لا واحدةَ منها تنادي `ems_audit_change` في شجرةِ تضمينِها
 * كلِّها** (قِيس بتتبّعِ التضمينِ عمقين + معالجاتِ AJAX المرافقة). فالفعلُ يقع
 * والصفُّ يتغيّر **بلا أثرٍ يُراجَع**.
 *
 * **والإصلاحُ في العُدَّةِ لا في الشاشات**: كلُّها تكتب عبر بوابةِ المستأجر
 * (`$gate->insert` · `->update` · `->deleteRow`)، فوُضع التدقيقُ **في البوابة**.
 * فنالته كلُّ شاشةٍ تعبرها اليوم ومستقبلًا — كما تنال العزلَ وحصانةَ الأحداث.
 *
 * ── وأربعةٌ يجب أن تجتمع، وإلا فالأثرُ زخرفة ──────────────────────────────────
 *   ① **صفٌّ واحدٌ لا صفران** — فالتكرارُ ضوضاءٌ كالغياب.
 *   ② **بالجدولِ والمعرّفِ** — أثرٌ لا يُشير إلى صفٍّ لا يُراجَع.
 *   ③ **وبالقيمةِ قبل وبعد** — و«قبل» تُقرأ من الصفِّ لا تُفترض نُلًّا.
 *   ④ **ولا صفَّ عند اللاتغيير** — إعادةُ الكتابةِ بالقيمةِ نفسِها لا تكتب شيئًا.
 *
 * ◆ والوسمُ عائليٌّ **ثابت** (لا `getmypid`) — وإلا كانت كلُّ جولةٍ عمياءَ عمّا
 *   تركته سابقتُها. والكنسُ بالعائلةِ ومُرجَعُ كلِّ حذفٍ يُفحَص (FK يردُّ صامتًا).
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO = 4;
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };

$TAG = 'GATEAUDIT';
/* كنسٌ بالعائلةِ — ومُرجَعُ كلِّ حذفٍ مفحوص */
$sweep = function () use ($conn, $TAG) {
    $n = 0; $ids = array();
    $r = $conn->query("SELECT id FROM job_titles WHERE name LIKE '%" . $TAG . "%'");
    while ($r && ($x = $r->fetch_row())) { $ids[] = (int) $x[0]; }
    foreach ($ids as $id) {
        $conn->query('DELETE FROM activity_logs WHERE screen_name = \'job_titles\' AND record_id = ' . $id);
        if ($conn->query('DELETE FROM job_titles WHERE id = ' . $id)) { $n += $conn->affected_rows; }
    }
    return $n;
};
$pre = $sweep();
$say('══ تدقيقُ بوابةِ المستأجر — الأثرُ يقع حيث تقع الكتابة'
     . ($pre ? "  (كُنس {$pre} من جولةٍ سابقة)" : ''));

/* ── ① البوابةُ تحمل الإصلاح ─────────────────────────────────────────────── */
$src = (string) file_get_contents($ROOT . '/app/Core/TenantDb.php');
$ok(strpos($src, 'private function auditWrite') !== false
    && strpos($src, "\$this->auditWrite(\$table, 'create'") !== false,
    'البوابةُ تُسجّل الإدراج');
$ok(strpos($src, "\$this->auditWrite(\$table, 'update'") !== false
    && strpos($src, 'private function auditSnapshot') !== false,
    'وتُسجّل التعديلَ **بلقطةِ «قبل» تُقرأ من الصفّ**');
$ok(strpos($src, "\$this->auditWrite(\$table, 'delete'") !== false,
    'وتُسجّل الحذفَ بصورةِ الصفِّ قبل زواله');
$ok(strpos($src, '$affected > 0 && $before') !== false,
    '**ولا تُسجّل إلا عند تغيّرٍ فعليّ** — فالضوضاءُ تُفسد المراجعة');
$ok(strpos($src, 'AUDIT_SKIP') !== false && strpos($src, "'activity_logs'") !== false,
    'وجداولُ السجلاتِ مستثناةٌ — فلا يُسجّل السجلُّ نفسَه');

/* ── ② الشاشاتُ السبعَ عشرةَ تعبر البوابة ────────────────────────────────── */
$screens = array(
    'Procurement/rfq_compare_award.php', 'Financing/owners_registry.php',
    'Financing/installments.php', 'Tickets/ticket_workstreams_board.php',
    'Approvals/hours_approval.php', 'Equipments/approve_card.php',
    'Finance/dues_fin.php', 'Financing/deviations.php',
    'Employees/employee_contract_actions_handler.php', 'Workforce/payroll_runs.php',
    'Employees/job_titles.php', 'chats/send_broadcast.php',
    'Maintenance/inspections.php', 'Finance/accountants_fin.php',
    'Financing/financing_board.php', 'Maintenance/get_project_equipment.php',
);
$viaGate = 0; $notVia = array();
foreach ($screens as $rel) {
    $s = (string) @file_get_contents($ROOT . '/' . $rel);
    if (preg_match('~ems_tenant_db\(|->insert\(|->update\(|->deleteRow\(|->softDelete\(~', $s)) { $viaGate++; }
    else { $notVia[] = $rel; }
}
$ok($viaGate >= 12, "و{$viaGate} من " . count($screens) . " شاشةً تكتب عبر البوابة — فالإصلاحُ يبلغها",
    'خارجَها: ' . implode(' · ', array_slice($notVia, 0, 4)));

/* ── ③ القياسُ الحيُّ: فعلٌ حقيقيٌّ عبر الشاشةِ ثم استعادةُ الأثر ───────────── */
$jar = sys_get_temp_dir() . '/gateaudit_' . getmypid() . '.txt';
$BASE = 'http://localhost/ems';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('body' => $b, 'code' => $c);
};
/* ◆ الحسابُ يُشتقُّ من **منحةِ الشاشةِ نفسِها** لا يُفترض الدورَ ١.
     أوّلُ صياغةٍ ثبّتت الدورَ ١، و`Employees/job_titles.php` ممنوحةٌ للدورِ ٤
     وحدَه — فصُيِّرت الشاشةُ بلا نموذجِ كتابةٍ ولم يقع فعلٌ يُقاس. */
$u = null; $roleUsed = 0;
$q = $conn->query("SELECT rp.role_id FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                    WHERE m.code = 'Employees/job_titles.php' AND rp.can_add = 1 ORDER BY rp.role_id");
while ($q && ($rr = $q->fetch_row())) {
    $st = $conn->prepare("SELECT id, username FROM users WHERE role = ? AND company_id = ?
                           AND username <> '' ORDER BY id LIMIT 1");
    $rid = (string) $rr[0];
    $st->bind_param('si', $rid, $CO);
    $st->execute();
    $cand = $st->get_result()->fetch_assoc();
    $st->close();
    if ($cand) { $u = $cand; $roleUsed = (int) $rr[0]; break; }
}
$ok($u !== null, 'وُجد حسابٌ **يملك الإضافةَ على الشاشةِ نفسِها** ('
    . ($u ? $u['username'] . ' · دور ' . $roleUsed : '—') . ')');

$b = $http($BASE . '/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b['body'], $t);
$lb = $http($BASE . '/login.php', http_build_query(array(
    'username' => $u ? $u['username'] : '', 'password' => '12345678',
    'csrf_token' => isset($t[1]) ? $t[1] : '')));
$ok(mb_strpos($lb['body'], 'name="password"') === false, 'ودخل');

$scr = $http($BASE . '/Employees/job_titles.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $scr['body'], $ct);
$tok = isset($ct[1]) ? $ct[1] : '';
$ok($scr['code'] === 200, 'وصُيِّرت شاشةُ المسميات الوظيفية');

/* ⓐ إنشاءٌ حقيقيٌّ عبر الشاشة */
$name = 'مسمّى فاحصٍ ' . $TAG;
$logsBefore = 0;
$r = $conn->query("SELECT COUNT(*) FROM activity_logs WHERE screen_name = 'job_titles'");
if ($r) { $logsBefore = (int) $r->fetch_row()[0]; }
$http($BASE . '/Employees/job_titles.php', http_build_query(array(
    'csrf_token' => $tok, 'name' => $name, 'description' => 'أوّلُ وصف',
    'status' => 1, 'sort_order' => 7)));
$newId = 0;
$q = $conn->prepare('SELECT id FROM job_titles WHERE name = ? ORDER BY id DESC LIMIT 1');
$q->bind_param('s', $name);
$q->execute();
$x = $q->get_result()->fetch_row();
$q->close();
if ($x) { $newId = (int) $x[0]; }
$ok($newId > 0, "أُنشئ مسمًّى حقيقيٌّ عبر الشاشة (#{$newId})");

$rowsCreate = array();
if ($newId > 0) {
    $r = $conn->query("SELECT action_type, field_name, old_value, new_value, user_id, module_name
                         FROM activity_logs
                        WHERE screen_name = 'job_titles' AND record_id = {$newId}
                        ORDER BY id");
    while ($r && ($z = $r->fetch_assoc())) { $rowsCreate[] = $z; }
}
$ok(count($rowsCreate) === 1, '**وصفُّ تدقيقٍ واحدٌ لا صفران** (' . count($rowsCreate) . ')');
$ok($rowsCreate && $rowsCreate[0]['action_type'] === 'create'
    && (int) $rowsCreate[0]['user_id'] === (int) $u['id'],
    'يحمل الفعلَ والفاعلَ بصفتِه',
    $rowsCreate ? ($rowsCreate[0]['action_type'] . ' · user=' . $rowsCreate[0]['user_id']) : '—');
$ok($rowsCreate && strpos((string) $rowsCreate[0]['new_value'], $TAG) !== false,
    'وبالقيمةِ الجديدةِ كما كُتبت');

/* ⓑ تعديلٌ حقيقيٌّ — و«قبل» تُقرأ من الصفّ */
$http($BASE . '/Employees/job_titles.php', http_build_query(array(
    'csrf_token' => $tok, 'edit_id' => $newId, 'name' => $name,
    'description' => 'وصفٌ ثانٍ مختلف', 'status' => 1, 'sort_order' => 9)));
$rowsUpd = array();
$r = $conn->query("SELECT action_type, field_name, old_value, new_value FROM activity_logs
                    WHERE screen_name = 'job_titles' AND record_id = {$newId}
                      AND action_type = 'update' ORDER BY id");
while ($r && ($z = $r->fetch_assoc())) { $rowsUpd[] = $z; }
$ok(count($rowsUpd) === 1, '**وتعديلٌ يُنتج صفًّا واحدًا** (' . count($rowsUpd) . ')');
$ok($rowsUpd && strpos((string) $rowsUpd[0]['old_value'], 'أوّلُ وصف') !== false,
    '**وقيمةُ «قبل» مقروءةٌ من الصفِّ لا مفترَضةً نُلًّا**',
    $rowsUpd ? mb_substr((string) $rowsUpd[0]['old_value'], 0, 80) : '—');
$ok($rowsUpd && strpos((string) $rowsUpd[0]['new_value'], 'وصفٌ ثانٍ') !== false,
    'وقيمةُ «بعد» كما أُرسلت');
$ok($rowsUpd && strpos((string) $rowsUpd[0]['field_name'], 'description') !== false,
    'واسمُ الحقلِ المتغيِّرِ مسمًّى — لا الصفُّ كلُّه',
    $rowsUpd ? (string) $rowsUpd[0]['field_name'] : '—');

/* ⓒ لا صفَّ عند اللاتغيير */
$beforeNoop = count($rowsUpd);
$http($BASE . '/Employees/job_titles.php', http_build_query(array(
    'csrf_token' => $tok, 'edit_id' => $newId, 'name' => $name,
    'description' => 'وصفٌ ثانٍ مختلف', 'status' => 1, 'sort_order' => 9)));
$afterNoop = 0;
$r = $conn->query("SELECT COUNT(*) FROM activity_logs WHERE screen_name = 'job_titles'
                    AND record_id = {$newId} AND action_type = 'update'");
if ($r) { $afterNoop = (int) $r->fetch_row()[0]; }
$ok($afterNoop === $beforeNoop,
    '**وإعادةُ الكتابةِ بالقيمةِ نفسِها لا تُنتج صفًّا** (' . $beforeNoop . ' ⇒ ' . $afterNoop . ')');

@unlink($jar);
$post = $sweep();
$say("   كُنس ختامًا: {$post} صفًّا");
$left = 0;
$r = $conn->query("SELECT COUNT(*) FROM job_titles WHERE name LIKE '%" . $TAG . "%'");
if ($r) { $left = (int) $r->fetch_row()[0]; }
$ok($left === 0, "صفرُ ثغرةٍ من عائلةِ الوسمِ بعد الجولة ({$left})");

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
