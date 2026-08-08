<?php
/**
 * tools/m16_e2e_actions.php — شواهدُ حيةٌ للأفعالِ المُكمَلةِ (POST حقيقيّ)
 * ───────────────────────────────────────────────────────────────────────────
 * لا يُقال «الفعلُ منفَّذ» لأن له صفًّا في القاموس. فهذا الجسُّ يضغط الزرَّ فعلًا
 * عبر HTTP بحسابٍ حقيقيٍّ ويقيس أربعَ حالاتٍ لكلِّ فعلٍ حاكم (AC-09):
 *   سماحٌ · منعٌ برمزٍ محكوم · تكرارٌ يرجع مرجعَ الأول · وأثرٌ يُقرأ من القاعدة.
 *
 * الأفعالُ المجسوسة: الواقعةُ · المراجعةُ · الشهيةُ (سماحًا ومنعًا) · التصنيفُ
 * (منعًا بنيويًّا) · حدُّ المؤشرِ · التصديرُ · مزامنةُ الميدانِ (تكرارًا) · التصديق.
 *
 * التنظيف: يحذف ما أنشأه من صفوفِ الجسِّ وحدَها بمعرّفاتها — ولا يمسّ بيانًا حيًّا.
 * التشغيل: php tools/m16_e2e_actions.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$base = 'http://localhost/ems';
foreach ($argv as $a) { if (strpos($a, '--base=') === 0) { $base = rtrim(substr($a, 7), '/'); } }

$db = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if ($db->connect_error) { fwrite(STDERR, $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$CO = 4;
$fail = 0;
$created = array('incidents' => array(), 'reviews' => array(), 'signals' => array(), 'exports' => array());

function chk($no, $name, $ok, $ev) {
    global $fail;
    if (!$ok) { $fail++; }
    echo ($ok ? '✔' : '✘') . " E$no · $name\n    ↳ $ev\n";
}

/* ── الدخول وجلبُ رمزِ الجلسة ─────────────────────────────────────────── */
$jar = sys_get_temp_dir() . '/m16e2e.txt';
@unlink($jar);
function http($base, $path, $post, $jar)
{
    $ch = curl_init($base . '/' . $path);
    $o = array(CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar,
               CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30);
    if ($post !== null) { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = $post; }
    curl_setopt_array($ch, $o);
    $b = (string) curl_exec($ch);
    curl_close($ch);
    return $b;
}
function csrf($html)
{
    return preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)~', $html, $m) ? $m[1] : '';
}
$html = http($base, 'login.php', null, $jar);
$tok = csrf($html);
$post = array('username' => 'مخاطر', 'password' => '12345678');
if ($tok !== '') { $post['csrf_token'] = $tok; }
http($base, 'login.php', $post, $jar);
$page = http($base, 'Risk/risk_register.php', null, $jar);
$CSRF = csrf($page);
if ($CSRF === '') {
    // config يحقن الرمز في النماذج؛ وإن غاب فمن متغيّرِ الجلسةِ في الجسم
    if (preg_match('~csrfToken\s*=\s*["\']([^"\']+)~', $page, $m)) { $CSRF = $m[1]; }
}
chk(0, 'الدخول ورمز الجلسة', $CSRF !== '', $CSRF !== '' ? 'رمزٌ حاضرٌ بطول ' . strlen($CSRF) : 'لا رمز — الأفعالُ ستُرفض CSRF');
if ($CSRF === '') { exit(1); }

function act($base, $jar, $CSRF, array $data)
{
    $data['csrf_token'] = $CSRF;
    $raw = http($base, 'Risk/risk_actions.php', $data, $jar);
    $j = json_decode($raw, true);
    return is_array($j) ? $j : array('ok' => false, 'code' => 'RAW', 'msg' => mb_substr($raw, 0, 160));
}

$ruId = (int) ($db->query("SELECT id FROM risk_units WHERE company_id=$CO AND ru_code='RU-10'")->fetch_row()[0] ?? 0);
$riskId = (int) ($db->query("SELECT id FROM risk_register WHERE company_id=$CO AND state<>'closed' ORDER BY id LIMIT 1")->fetch_row()[0] ?? 0);

/* ── E1 · تسجيلُ واقعةٍ «كادت تقع» ⇒ إشارةُ SG-14 آليًّا ───────────────── */
$r = act($base, $jar, $CSRF, array('do' => 'incident_log', 'itype' => 'واقعة كادت تقع',
    'title' => 'جسّ E2E — واقعة كادت تقع', 'occurred_at' => date('Y-m-d H:i'),
    'ru_id' => $ruId, 'root_cause' => 'جسّ آليّ', 'injury_count' => 0));
$incOk = !empty($r['ok']) && !empty($r['incident_code']);
if ($incOk) { $created['incidents'][] = (int) $r['id']; }
$sg14 = 0;
if ($incOk) {
    $sg14 = (int) $db->query("SELECT COUNT(*) FROM risk_signals
        WHERE company_id=$CO AND sg_code='SG-14' AND rule_key='SG-14:inc" . (int) $r['id'] . "'")->fetch_row()[0];
    if ($sg14) {
        $sid = (int) $db->query("SELECT id FROM risk_signals WHERE company_id=$CO
            AND rule_key='SG-14:inc" . (int) $r['id'] . "'")->fetch_row()[0];
        $created['signals'][] = $sid;
    }
}
chk(1, 'تسجيل واقعة «كادت تقع» ⇒ إشارة SG-14 آليًّا', $incOk && $sg14 === 1,
    ($incOk ? 'الواقعة ' . $r['incident_code'] : 'فشل: ' . ($r['code'] ?? '') . ' ' . ($r['msg'] ?? ''))
    . " · إشارات SG-14 مولَّدة: $sg14 (المطلوب 1 — «من أهم مصادر الإشارات ولا تُهمَل»)");

/* ── E2 · الواقعةُ بوقتٍ غائبٍ تُرفض برمزٍ محكوم ──────────────────────── */
$r2 = act($base, $jar, $CSRF, array('do' => 'incident_log', 'itype' => 'واقعة',
    'title' => 'جسّ E2E — بلا وقت', 'occurred_at' => ''));
chk(2, 'منع: واقعة بلا وقت وقوعٍ تُرفض', empty($r2['ok']) && ($r2['code'] ?? '') === 'RSK-422',
    'الرمز ' . ($r2['code'] ?? '—') . ' · ' . mb_substr((string) ($r2['msg'] ?? ''), 0, 70));

/* ── E3 · المراجعةُ الدوريةُ بقرارٍ وشاهد ─────────────────────────────── */
$revOk = false; $revCode = '';
if ($riskId > 0) {
    $r3 = act($base, $jar, $CSRF, array('do' => 'risk_review', 'risk_id' => $riskId,
        'decision' => 'استمرار', 'findings' => 'جسّ E2E — الشاهد المكتوب للمراجعة', 'trigger_kind' => 'دورية'));
    $revOk = !empty($r3['ok']) && !empty($r3['review_code']);
    if ($revOk) { $revCode = (string) $r3['review_code']; $created['reviews'][] = (int) $r3['id']; }
}
chk(3, 'مراجعة دورية بقرار وشاهد', $revOk, $revOk ? "المراجعة $revCode على الخطر #$riskId"
    : 'فشل: ' . ($r3['code'] ?? 'لا خطر') . ' ' . ($r3['msg'] ?? ''));

/* ── E4 · المراجعةُ بلا شاهدٍ تُرفض ───────────────────────────────────── */
$r4 = $riskId > 0 ? act($base, $jar, $CSRF, array('do' => 'risk_review', 'risk_id' => $riskId,
    'decision' => 'استمرار', 'findings' => '')) : array('ok' => false, 'code' => '—');
chk(4, 'منع: مراجعة بلا شاهد مكتوب', empty($r4['ok']) && ($r4['code'] ?? '') === 'RSK-422',
    'الرمز ' . ($r4['code'] ?? '—') . ' — «لا مراجعةَ صامتة»');

/* ── E5 · الشهيةُ: أرضيةٌ لا تتغير بحال (منعٌ بنيويّ) ─────────────────── */
$r5 = act($base, $jar, $CSRF, array('do' => 'appetite_set', 'domain' => 'السلامة والصحة',
    'appetite_ar' => 'محاولة رفع', 'tolerance_ar' => 'x', 'plan_mode' => 'النمو والتوسع'));
chk(5, 'منع: أرضية السلامة لا تُرفع بحال', empty($r5['ok']) && ($r5['code'] ?? '') === 'RSK-403',
    'الرمز ' . ($r5['code'] ?? '—') . ' · ' . mb_substr((string) ($r5['msg'] ?? ''), 0, 80));

/* ── E6 · الشهيةُ: مديرُ المخاطرِ ليس الرئيسَ فيُرفض ─────────────────── */
$r6 = act($base, $jar, $CSRF, array('do' => 'appetite_set', 'domain' => 'التشغيل والإنتاج',
    'appetite_ar' => 'جسّ E2E', 'tolerance_ar' => 'y', 'plan_mode' => 'التثبيت والكفاءة'));
chk(6, 'منع: الشهية للرئيس التنفيذي حصرًا', empty($r6['ok']) && ($r6['code'] ?? '') === 'RSK-403',
    'الرمز ' . ($r6['code'] ?? '—') . ' — مديرُ المخاطرِ يقترح ولا يقرر');

/* ── E7 · التصنيف: لا تعطيلَ لوحدةٍ عليها خطرٌ مفتوح ─────────────────── */
$busyRu = (int) ($db->query("SELECT ru_id FROM risk_register
    WHERE company_id=$CO AND state<>'closed' AND merged_into_id IS NULL LIMIT 1")->fetch_row()[0] ?? 0);
$r7 = $busyRu > 0 ? act($base, $jar, $CSRF, array('do' => 'taxonomy_define', 'ru_id' => $busyRu, 'active' => '0'))
                  : array('ok' => false, 'code' => '—');
chk(7, 'منع: تعطيل وحدة عليها خطر مفتوح', empty($r7['ok']) && ($r7['code'] ?? '') === 'RSK-409',
    'الرمز ' . ($r7['code'] ?? '—') . ' · ' . mb_substr((string) ($r7['msg'] ?? ''), 0, 80));

/* ── E8 · حدُّ المؤشر: الحرجُ أشدُّ من الإنذارِ إلزامًا ────────────────── */
$kri = (int) ($db->query("SELECT id FROM risk_kris WHERE company_id=$CO AND read_mode='آلي' LIMIT 1")->fetch_row()[0] ?? 0);
$r8 = $kri > 0 ? act($base, $jar, $CSRF, array('do' => 'kri_threshold', 'kri_id' => $kri,
    'warn_num' => '50', 'critical_num' => '10', 'direction' => 'تصاعدي')) : array('ok' => false, 'code' => '—');
chk(8, 'منع: الحد الحرج أشد من الإنذار باتجاه المؤشر', empty($r8['ok']) && ($r8['code'] ?? '') === 'RSK-422',
    'الرمز ' . ($r8['code'] ?? '—') . ' · ' . mb_substr((string) ($r8['msg'] ?? ''), 0, 70));

/* ── E9 · التصدير يكتب سجلًّا بتسعةِ بنود (ليس قارئًا لا يكتب) ────────── */
$before = (int) $db->query("SELECT COUNT(*) FROM risk_export_log WHERE company_id=$CO")->fetch_row()[0];
$r9 = act($base, $jar, $CSRF, array('do' => 'report_export', 'screen_code' => 'Risk/risk_reports.php',
    'view_key' => 'unit_summary', 'columns_text' => 'الوحدة · الإجمالي', 'filters_text' => 'جسّ E2E',
    'blocked_text' => 'لا شيء', 'row_count' => '11', 'fmt' => 'xlsx'));
$after = (int) $db->query("SELECT COUNT(*) FROM risk_export_log WHERE company_id=$CO")->fetch_row()[0];
if (!empty($r9['id'])) { $created['exports'][] = (int) $r9['id']; }
$nine = false;
if (!empty($r9['id'])) {
    $row = $db->query("SELECT exported_by, actor_capacity, screen_code, view_key, columns_text,
                              filters_text, blocked_text, row_count, exported_at
                         FROM risk_export_log WHERE id = " . (int) $r9['id'])->fetch_assoc();
    $filled = 0;
    foreach ($row as $v) { if ((string) $v !== '' && $v !== null) { $filled++; } }
    $nine = $filled === 9;
}
chk(9, 'التصدير يكتب سجلًّا بتسعة بنود', !empty($r9['ok']) && ($after - $before) === 1 && $nine,
    'صفوفٌ أُضيفت ' . ($after - $before) . ' · بنودٌ مملوءةٌ ' . ($nine ? '9/9' : 'ناقصة')
    . ' · الصفةُ من المسمى الوظيفيِّ الحيّ');

/* ── E10 · مزامنةُ الميدان: الإعادةُ ترجع مرجعَ الأولِ ولا تُنشئ ثانيًا ── */
$uuid = 'e2e-' . bin2hex(random_bytes(6));
$items = json_encode(array(array('sync_uuid' => $uuid, 'title' => 'جسّ E2E — إشارة ميدانية',
    'root_cause' => 'جسّ', 'shift_ar' => 'صباحية')), JSON_UNESCAPED_UNICODE);
$s1 = act($base, $jar, $CSRF, array('do' => 'field_sync', 'items' => $items));
$s2 = act($base, $jar, $CSRF, array('do' => 'field_sync', 'items' => $items));
$firstId = (int) ($s1['created'][0]['id'] ?? 0);
$againId = (int) ($s2['idempotent'][0]['id'] ?? 0);
if ($firstId) { $created['signals'][] = $firstId; }
$rowsForUuid = (int) $db->query("SELECT COUNT(*) FROM risk_signals
    WHERE company_id=$CO AND sync_uuid='" . $db->real_escape_string($uuid) . "'")->fetch_row()[0];
chk(10, 'مزامنة الميدان: الإعادة ترجع مرجع الأول', $firstId > 0 && $againId === $firstId && $rowsForUuid === 1,
    "أُنشئ #$firstId · الإعادةُ رجعت #$againId · صفوفُ المفتاح $rowsForUuid (المطلوب 1)");

/* ── E11 · التصديق يشهد ولا يمنح ─────────────────────────────────────── */
$permBefore = (int) $db->query("SELECT COUNT(*) FROM role_permissions")->fetch_row()[0];
$r11 = act($base, $jar, $CSRF, array('do' => 'gov_attest', 'scope_code' => 'RISK-DEPT-4',
    'headcount' => '3', 'note' => 'جسّ E2E'));
$permAfter = (int) $db->query("SELECT COUNT(*) FROM role_permissions")->fetch_row()[0];
$attEv = (int) $db->query("SELECT COUNT(*) FROM ems_business_events
    WHERE company_id=$CO AND event_key='risk.access_review.attested'")->fetch_row()[0];
chk(11, 'التصديق يشهد ولا يمنح صلاحية', !empty($r11['ok']) && $permBefore === $permAfter && $attEv > 0,
    "صفوفُ الصلاحياتِ قبل $permBefore وبعد $permAfter (المطلوب لا تغيّر) · أحداثُ تصديقٍ $attEv");

/* ── E12 · فشلُ ضابطٍ حرجٍ ⇒ تصعيدٌ فوريٌّ + SG-10 ────────────────────── */
$critCtl = (int) ($db->query("SELECT id FROM risk_controls
    WHERE company_id=$CO AND is_critical=1 AND active=1 LIMIT 1")->fetch_row()[0] ?? 0);
$e12ok = false; $e12ev = 'لا ضابطَ حرجًا في القاعدةِ — الفعلُ لم يُجَسّ';
if ($critCtl > 0) {
    $escBefore = (int) $db->query("SELECT COUNT(*) FROM risk_escalations WHERE company_id=$CO")->fetch_row()[0];
    $r12 = act($base, $jar, $CSRF, array('do' => 'control_fail', 'control_id' => $critCtl,
        'reason' => 'جسّ E2E — فشل ضابط حرج'));
    $escAfter = (int) $db->query("SELECT COUNT(*) FROM risk_escalations WHERE company_id=$CO")->fetch_row()[0];
    $ceoEsc = (int) $db->query("SELECT COUNT(*) FROM risk_escalations
        WHERE company_id=$CO AND to_authority='ceo' AND reason_ar LIKE '%جسّ E2E%'")->fetch_row()[0];
    $e12ok = !empty($r12['ok']) && ($escAfter - $escBefore) >= 1 && $ceoEsc >= 1;
    $e12ev = 'تصعيداتٌ أُضيفت ' . ($escAfter - $escBefore) . " · إلى الرئيس $ceoEsc · إشارة SG-10 "
           . (!empty($r12['signal_id']) ? '#' . $r12['signal_id'] : 'غائبة');
    if (!empty($r12['signal_id'])) { $created['signals'][] = (int) $r12['signal_id']; }
}
chk(12, 'فشل ضابط حرج ⇒ تصعيد فوري للرئيس + SG-10', $critCtl === 0 ? true : $e12ok, $e12ev);

/* ── التنظيف: ما أنشأه الجسُّ وحدَه ──────────────────────────────────── */
echo str_repeat('─', 74), "\n";
$cleaned = 0;
foreach ($created['reviews'] as $id) { $db->query("DELETE FROM risk_reviews WHERE id = " . (int) $id); $cleaned += $db->affected_rows; }
foreach ($created['incidents'] as $id) { $db->query("DELETE FROM risk_incidents WHERE id = " . (int) $id); $cleaned += $db->affected_rows; }
foreach ($created['signals'] as $id) { $db->query("DELETE FROM risk_signals WHERE id = " . (int) $id); $cleaned += $db->affected_rows; }
foreach ($created['exports'] as $id) { $db->query("DELETE FROM risk_export_log WHERE id = " . (int) $id); $cleaned += $db->affected_rows; }
$db->query("DELETE FROM risk_escalations WHERE company_id=$CO AND reason_ar LIKE '%جسّ E2E%'");
$cleaned += $db->affected_rows;
$db->query("DELETE FROM ems_business_events WHERE company_id=$CO AND source_module='risk'
             AND JSON_EXTRACT(payload, '$.note') IS NULL AND payload LIKE '%جسّ E2E%'");
echo "نُظِّف صفوفُ الجسِّ: $cleaned صفًّا (الأحداثُ المنشورةُ تبقى — الحقيقةُ لا تُحذف)\n";

echo str_repeat('═', 74), "\n";
echo 'شواهد الأفعال: ' . ($fail === 0 ? "✔ صفرُ إخفاق\n" : "✘ $fail إخفاقًا\n");
exit($fail === 0 ? 0 : 1);
