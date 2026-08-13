<?php
/**
 * tests/risk_treatment_evidence_test.php — شاهدُ دليلِ إنجازِ المعالجة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0576
 *
 * **العيب**: دليلُ التنفيذِ يُجمع بنافذةِ `prompt()` نصًّا حرًّا، والخادمُ يقبله
 * إن لم يكن فارغًا — فنقطةٌ واحدةٌ «.» تُغلق معالجةَ خطرٍ ويجد المتحقِّقُ رمزًا
 * لا يُخبره بشيء. ولا موضعَ في الجدولِ يحمل **مرفقًا** ولا **مرجعًا**.
 *
 * **الإصلاح** ثلاثةُ أطراف:
 *   ① عمودان (`done_attachment` · `done_ref`) بهجرةٍ قابلةٍ للإعادة.
 *   ② شرطٌ في الخادم: نصٌّ مقروءٌ (عشرةُ محارفَ فأكثر وفيها ستةُ حروفٍ/أرقام)
 *      **أو** مرفقٌ ومرجعٌ معًا — برمزٍ مميِّزٍ `RSK-EVID-422`.
 *   ③ نموذجٌ بثلاثةِ حقولٍ بدلَ `prompt()`، ورابطُ المرفقِ يظهر في شاشةِ التحقق.
 *
 * ── والطرفانِ يُقاسان: الرفضُ **والقبول** ──────────────────────────────────
 * فاحصٌ يقيس الرفضَ وحدَه يمرُّ على إصلاحٍ يرفض كلَّ شيء. فيُزرع سطرُ معالجةٍ
 * حقيقيٌّ، ويُجرَّب عليه: «.» ⇒ 422 · مرفقٌ ومرجعٌ ⇒ 200 · ورابطُ المرفقِ يظهر.
 * ثم يُكنس بعائلةِ وسمِه ويُفحص مُرجَعُ الحذف.
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
$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };

$TAG = 'EVIDPROBE';
$sweep = function () use ($conn, $TAG) {
    $n = 0;
    $r = $conn->query("SELECT id FROM risk_treatments WHERE plan_ar LIKE '%" . $TAG . "%'");
    $ids = array();
    while ($r && ($x = $r->fetch_row())) { $ids[] = (int) $x[0]; }
    foreach ($ids as $id) {
        $conn->query("DELETE FROM ems_business_events
                       WHERE source_module = 'risk' AND entity_id = " . $id
                     . " AND entity_type IN ('risk_treatment','treatment')");
        if ($conn->query('DELETE FROM risk_treatments WHERE id = ' . $id)) { $n += $conn->affected_rows; }
    }
    return $n;
};
$pre = $sweep();
$say('══ INJ-0576 · دليلُ الإنجازِ يُقرأ أو يُوثَّق — لا نقطةٌ واحدة'
     . ($pre ? "  (كُنس {$pre} من جولةٍ سابقة)" : ''));

/* ── ① العمودان قائمان ─────────────────────────────────────────────────────── */
$cols = array();
$r = $conn->query('SHOW COLUMNS FROM risk_treatments');
while ($r && ($x = $r->fetch_row())) { $cols[] = $x[0]; }
$ok(in_array('done_attachment', $cols, true) && in_array('done_ref', $cols, true),
    'عمودا المرفقِ والمرجعِ قائمان في `risk_treatments`',
    'الموجود: ' . implode(' · ', $cols));

/* ── ② حسابُ دورٍ يكتب + سطرُ معالجةٍ مزروع ────────────────────────────────── */
$user = ''; $uidUsed = 0; $roleUsed = 0;
foreach (array(28, 29, 30) as $rid) {
    $st = $conn->prepare("SELECT id, username FROM users WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id");
    $rr = (string) $rid; $st->bind_param('si', $rr, $CO); $st->execute();
    $res = $st->get_result();
    while ($res && ($x = $res->fetch_row())) { $user = (string) $x[1]; $uidUsed = (int) $x[0]; $roleUsed = $rid; break; }
    $st->close();
    if ($user !== '') { break; }
}
$ok($user !== '', "وُجد حسابُ دورِ المخاطر ({$user} · دور {$roleUsed})");

$riskId = 0;
$r = $conn->query("SELECT id FROM risk_register WHERE company_id = {$CO} AND merged_into_id IS NULL ORDER BY id DESC LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $riskId = (int) $x[0]; }
$ok($riskId > 0, "ووُجد خطرٌ حيٌّ يُعلَّق عليه إجراءٌ (#{$riskId})");

$tid = 0;
if ($riskId > 0) {
    $st = $conn->prepare("INSERT INTO risk_treatments (company_id, risk_id, ttype, plan_ar,
                             action_owner_user_id, due_date, state, created_by)
                          VALUES (?, ?, 'تقليل', ?, ?, CURDATE(), 'in_progress', ?)");
    $plan = 'خطةُ فاحصٍ · ' . $TAG;
    $st->bind_param('iisii', $CO, $riskId, $plan, $uidUsed, $uidUsed);
    if ($st->execute()) { $tid = (int) $conn->insert_id; }
    $st->close();
}
$ok($tid > 0, "وزُرع إجراءُ معالجةٍ قيدَ التنفيذ (#{$tid})", $conn->error);

/* ── ③ القياسُ على HTTP ────────────────────────────────────────────────────── */
$jar = sys_get_temp_dir() . '/evid_' . getmypid() . '.txt';
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
$b = $http($BASE . '/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b['body'], $t);
$lb = $http($BASE . '/login.php', http_build_query(array(
    'username' => $user, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
$ok(mb_strpos($lb['body'], 'name="password"') === false, 'ودخل');

$scr = $http($BASE . '/Risk/risk_treatments.php');
$h = $scr['body'];
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $h, $ct);
$tok = isset($ct[1]) ? $ct[1] : '';
$ok($scr['code'] === 200 && mb_strpos($h, 'name="password"') === false, 'وصُيِّرت شاشةُ الإجراءات');
$ok(strpos($h, 'id="treatDoneDlg"') !== false && strpos($h, 'name="done_attachment"') !== false
    && strpos($h, 'name="done_ref"') !== false,
    '**والإدخالُ نموذجٌ بثلاثةِ حقولٍ** لا نافذةَ `prompt()`');
$ok(strpos($h, "prompt('دليل الإنجاز") === false,
    'ولا أثرَ لـ`prompt()` في مسارِ الإنجاز');

$send = function ($fields) use ($http, $BASE, $tok, $tid) {
    return $http($BASE . '/Risk/risk_actions.php', http_build_query(array_merge(array(
        'do' => 'treatment_progress', 'csrf_token' => $tok,
        'treatment_id' => $tid, 'state' => 'done'), $fields)));
};

/* ⓐ دليلٌ نصيٌّ «.» ⇒ 422 */
$dot = $send(array('done_evidence' => '.'));
$ok($dot['code'] === 422, '**دليلٌ «.» يُردُّ 422**', 'الرمزُ ' . $dot['code']);
$ok(strpos($dot['body'], 'RSK-EVID-422') !== false, 'برمزٍ مميِّزٍ `RSK-EVID-422`',
    mb_substr(strip_tags($dot['body']), 0, 120));
$stateNow = '';
$r = $conn->query('SELECT state FROM risk_treatments WHERE id = ' . $tid);
if ($r && ($x = $r->fetch_row())) { $stateNow = (string) $x[0]; }
$ok($stateNow === 'in_progress', 'ولم تتغيَّر حالةُ الإجراء', 'صارت ' . $stateNow);

/* ⓑ مرفقٌ حقيقيٌّ ومرجعٌ ⇒ 200 */
$att = '../uploads/risk/evidence_' . $tid . '.pdf';
$good = $send(array('done_evidence' => '.', 'done_attachment' => $att, 'done_ref' => 'WO-' . $tid));
$ok($good['code'] === 200, '**ومرفقٌ ومرجعٌ يُقبلان بـ200**',
    'الرمزُ ' . $good['code'] . ' · ' . mb_substr(strip_tags($good['body']), 0, 100));
$row = null;
$r = $conn->query('SELECT state, done_attachment, done_ref FROM risk_treatments WHERE id = ' . $tid);
if ($r) { $row = $r->fetch_assoc(); }
$ok($row && $row['state'] === 'done' && $row['done_attachment'] === $att && $row['done_ref'] === 'WO-' . $tid,
    'ويُخزَّنان كما أُرسلا وتصير الحالةُ `done`',
    $row ? ($row['state'] . ' · ' . $row['done_attachment']) : 'لا صف');

/* ⓒ ورابطُ المرفقِ يظهر في شاشةِ التحقق */
$after = $http($BASE . '/Risk/risk_treatments.php');
$ok(strpos($after['body'], htmlspecialchars($att, ENT_QUOTES, 'UTF-8')) !== false
    || strpos($after['body'], $att) !== false,
    '**ورابطُ المرفقِ يظهر في شاشةِ التحقق**');
$ok(strpos($after['body'], 'WO-' . $tid) !== false, 'والمرجعُ معه');

/* ⓓ ونصٌّ مقروءٌ بلا مرفقٍ يُقبل — فالشرطُ ليس «مرفقٌ دائمًا» */
$conn->query("UPDATE risk_treatments SET state='in_progress', done_attachment=NULL, done_ref=NULL WHERE id={$tid}");
$txt = $send(array('done_evidence' => 'نُفِّذ التدريبُ الميدانيُّ وسُلِّمت المحاضر'));
$ok($txt['code'] === 200, 'ونصٌّ مقروءٌ وحدَه يُقبل — فالشرطُ بديلٌ لا تراكميّ',
    'الرمزُ ' . $txt['code']);

@unlink($jar);
$post = $sweep();
$say("   كُنس ختامًا: {$post} صفًّا");
$left = 0;
$r = $conn->query("SELECT COUNT(*) FROM risk_treatments WHERE plan_ar LIKE '%" . $TAG . "%'");
if ($r && ($x = $r->fetch_row())) { $left = (int) $x[0]; }
$ok($left === 0, "صفرُ ثغرةٍ من عائلةِ الوسمِ بعد الجولة ({$left})");

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
