<?php
/**
 * tests/cap_state_guard_test.php — شاهدُ السقفِ على الحالةِ وحقلِ الاعتمادِ المالي
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0053 · INJ-0089
 *
 * ── INJ-0053 · «فوقَ السقفِ لا تكون نافذة» ──────────────────────────────────
 * نصُّ القبول: «إنشاءُ عمليةٍ فوق سقف الدور **لا يجعلها نافذةً** بل معلَّقةً
 * بتصعيدٍ لمن يعلوه؛ و**كلُّ عمليةٍ نافذةٍ تحمل مرجعَ تفويضِ معتمِدها**».
 * والمقيسُ قبلَه: `FinancingService::createOperation` تُنشئ كلَّ عمليةٍ بحالةِ
 * `'active'` مهما بلغ رأسُ المال، ولا موضعَ في الجدولِ يحمل مرجعَ التفويض.
 * والحكمُ وُضع **في الخدمةِ** لا في الشاشة — فهي مَخنَقُ الإنشاءِ لكلِّ نافذة.
 *
 * ── INJ-0089 · «الحقلُ غيرُ موجودٍ في نموذجه» ───────────────────────────────
 * نصُّ القبول: «محاولةُ الطالبِ ضبطَ الحالة المالية بنفسه غيرُ ممكنةٍ (الحقلُ
 * غيرُ موجودٍ في نموذجه)». وكانت في نموذجِ الطلبِ قائمةٌ منسدلةٌ مفتوحةٌ لكلِّ
 * من يكتب — فيُعلن الطالبُ طلبَه «معتمدًا ماليًّا» بنفسه.
 * ◆ والنزعُ من الواجهةِ وحدَه لا يكفي: الطلبُ يُصنَع بأداةٍ خارجَ المتصفح.
 *   فيُقاس **الطرفان**: غيابُ الحقلِ من المُخرَج، **وتجاهُلُه في الخادم**.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/app/Core/AuthorityGuard.php';
require_once $ROOT . '/app/Services/Financing/FinancingService.php';

$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };

$TAG = 'CAPSTATE';
/* ── الكنسُ **بترتيبِ الأبناءِ قبل الآباء** ومُرجَعُ كلِّ حذفٍ مفحوص ──────────────
     وقع الفخُّ فعلًا: حُذف `signing_authorities` قبل `approval_signatures`
     المشيرةِ إليه، فردَّ المفتاحُ الأجنبيُّ **صامتًا** وبقي تفويضُ الجولةِ
     السابقة — فالتقطه الحارسُ في الجولةِ التالية وأعطى مرجعًا قديمًا. */
$sweepReport = array();
$sweep = function () use ($conn, $TAG, &$sweepReport) {
    $n = 0; $sweepReport = array();
    $steps = array(
        'التواقيعُ على التفويضِ الموسوم' =>
            "DELETE FROM approval_signatures WHERE auth_id IN
               (SELECT auth_id FROM signing_authorities WHERE doc_ref = '" . $TAG . "')",
        'تواقيعُ عملياتِ التمويلِ المؤقتة' =>
            "DELETE FROM approval_signatures WHERE document_type = 'financing_operation' AND document_id = 0",
        'أقساطُ العملياتِ الموسومة' =>
            "DELETE FROM financing_installments WHERE op_id IN
               (SELECT op_id FROM financing_operations WHERE op_code LIKE '%" . $TAG . "%')",
        'عملياتُ التمويلِ الموسومة' =>
            "DELETE FROM financing_operations WHERE op_code LIKE '%" . $TAG . "%'",
        'صفوفُ التصعيد' =>
            "DELETE FROM exec_approvals WHERE request_no LIKE '%" . $TAG . "%'
                OR raise_reason LIKE '%" . $TAG . "%' OR document LIKE '%" . $TAG . "%'",
        'التفويضُ الموسوم' =>
            "DELETE FROM signing_authorities WHERE doc_ref = '" . $TAG . "'",
        'طلباتُ الشراءِ الموسومة' =>
            "DELETE FROM proc_request WHERE code LIKE '%" . $TAG . "%'",
    );
    foreach ($steps as $label => $q) {
        if ($conn->query($q)) { $n += $conn->affected_rows; }
        else { $sweepReport[] = $label . ': ' . $conn->error; }
    }
    return $n;
};
$pre = $sweep();
$say('══ INJ-0053 · INJ-0089 — السقفُ يحكم الحالةَ، والحقلُ الماليُّ ليس للطالب'
     . ($pre ? "  (كُنس {$pre} من جولةٍ سابقة)" : ''));

/* ── ① الأعمدةُ الجديدةُ قائمة ────────────────────────────────────────────── */
$cols = array();
$r = $conn->query('SHOW COLUMNS FROM financing_operations');
while ($r && ($x = $r->fetch_row())) { $cols[] = $x[0]; }
$ok(in_array('authority_ref', $cols, true) && in_array('escalated_to', $cols, true),
    'عمودا مرجعِ التفويضِ والتصعيدِ قائمانِ في `financing_operations`');

/* ── ② تهيئةُ الحالة: كيانٌ وتفويضٌ بسقفٍ وعَلَمٌ مُشعَل ─────────────────────── */
$entity = (int) \App\Core\AuthorityGuard::tenantEntity($conn, $CO);
$ok($entity > 0, "كيانُ الشركةِ معروفٌ (#{$entity})");
$actor = 0;
$r = $conn->query("SELECT id FROM users WHERE company_id = {$CO} AND username <> '' ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $actor = (int) $x[0]; }
$CAP = 50000.00;
$authId = 0;
$st = $conn->prepare("INSERT INTO signing_authorities
        (company_id, person_id, entity_id, auth_type, amount_cap, currency,
         scope_type, valid_from, valid_to, doc_ref, state, created_by)
        VALUES (?, ?, ?, 'delegated', ?, 'USD', 'all', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, 'active', ?)");
if ($st) {
    $st->bind_param('iiidsi', $CO, $actor, $entity, $CAP, $TAG, $actor);
    if ($st->execute()) { $authId = (int) $conn->insert_id; }
    $st->close();
}
$ok($authId > 0, "وتفويضٌ ساري بسقف " . number_format($CAP, 0) . " USD (#{$authId})", $conn->error);
$conn->query("INSERT INTO governance_flags (element_code, scope_type, scope_id, enabled)
              VALUES ('signing_caps', 'entity', {$entity}, 1)");

/* نموذجُ تمويلٍ فعّالٌ بمعالجةٍ مكتوبة — شرطُ الخدمةِ نفسِها */
$model = '';
$r = $conn->query("SELECT model_code FROM financing_models
                    WHERE active = 1 AND policy_doc_ref IS NOT NULL AND policy_doc_ref <> '' LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $model = (string) $x[0]; }
$fin = 0;
$r = $conn->query("SELECT e.entity_id FROM legal_entities e
                     JOIN entity_roles rr ON rr.entity_id = e.entity_id AND rr.role = 'financier'
                    WHERE e.state = 'active' LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $fin = (int) $x[0]; }
$ok($model !== '' && $fin > 0, "ونموذجُ تمويلٍ ومموِّلٌ حيّان ({$model} · #{$fin})");

/* ── ③ تحتَ السقفِ ⇒ **نافذةٌ** بمرجعِ تفويض ─────────────────────────────── */
$under = \App\Services\Financing\FinancingService::createOperation($conn, $CO, array(
    'op_code' => $TAG . '-UNDER-' . date('His'), 'financier_entity_id' => $fin,
    'model_code' => $model, 'currency' => 'USD', 'signed_date' => date('Y-m-d'),
    'capital' => 30000.00, 'down_payment' => 0, 'installments_no' => 0,
), $actor);
$ok(!empty($under['ok']), 'عمليةٌ برأسِ مالٍ 30,000 تحتَ سقفِ 50,000 ⇒ أُنشئت',
    (string) ($under['reason'] ?? ''));
$rowU = null;
if (!empty($under['op_id'])) {
    $r = $conn->query('SELECT state, authority_ref, escalated_to FROM financing_operations
                        WHERE op_id = ' . (int) $under['op_id']);
    if ($r) { $rowU = $r->fetch_assoc(); }
}
$ok($rowU && $rowU['state'] === 'active', '**وحالتُها `active` — نافذةٌ**',
    $rowU ? $rowU['state'] : 'لا صف');
$ok($rowU && $rowU['authority_ref'] === 'AUTH-' . $authId,
    '**وتحمل مرجعَ تفويضِ معتمِدها** (' . ($rowU ? (string) $rowU['authority_ref'] : '—') . ')');

/* ── ④ فوقَ السقفِ ⇒ **معلَّقةٌ** + تصعيدٌ حقيقيّ ──────────────────────────── */
$escBefore = 0;
$r = $conn->query("SELECT COUNT(*) FROM exec_approvals WHERE source_kind = 'escalation'");
if ($r) { $escBefore = (int) $r->fetch_row()[0]; }
$over = \App\Services\Financing\FinancingService::createOperation($conn, $CO, array(
    'op_code' => $TAG . '-OVER-' . date('His'), 'financier_entity_id' => $fin,
    'model_code' => $model, 'currency' => 'USD', 'signed_date' => date('Y-m-d'),
    'capital' => 120000.00, 'down_payment' => 0, 'installments_no' => 0,
), $actor);
$rowO = null;
if (!empty($over['op_id'])) {
    $r = $conn->query('SELECT state, authority_ref, escalated_to FROM financing_operations
                        WHERE op_id = ' . (int) $over['op_id']);
    if ($r) { $rowO = $r->fetch_assoc(); }
}
$ok($rowO && $rowO['state'] !== 'active',
    '**عمليةٌ برأسِ مالٍ 120,000 فوقَ السقفِ ⇒ ليست نافذةً** (' . ($rowO ? $rowO['state'] : '—') . ')');
$ok($rowO && $rowO['escalated_to'] !== null && $rowO['escalated_to'] !== '',
    '**وتحمل مرجعَ تصعيدِها** (' . ($rowO ? (string) $rowO['escalated_to'] : '—') . ')');
$escAfter = 0;
$r = $conn->query("SELECT COUNT(*) FROM exec_approvals WHERE source_kind = 'escalation'");
if ($r) { $escAfter = (int) $r->fetch_row()[0]; }
$ok($escAfter === $escBefore + 1,
    "**وصفٌّ واحدٌ جديدٌ في صندوقِ الاعتمادِ الأعلى** ({$escBefore} ⇒ {$escAfter})");
$ok(mb_strpos((string) ($over['reason'] ?? ''), 'معلَّقة') !== false,
    'والرسالةُ تقول «معلَّقة» لا «نافذة» — فالخداعُ أسوأُ من المنع',
    mb_substr((string) ($over['reason'] ?? ''), 0, 90));

/* ── ⑤ INJ-0089 · الحقلُ الماليُّ غائبٌ عن نموذجِ الطالبِ ومُتجاهَلٌ في الخادم ── */
$src = (string) @file_get_contents($ROOT . '/Procurement/requests_proc.php');
$ok(strpos($src, 'ems_proc_may_finance_approve') !== false,
    'شاشةُ طلباتِ الشراءِ تسأل: أيملك هذا المستخدمُ الاعتمادَ المالي؟');
$ok(strpos($src, "\$fin_state    = \$__mayFin ? trim(\$_POST['fin_approval_state'] ?? 'بانتظار') : 'بانتظار'") !== false,
    '**والخادمُ يتجاهل الحقلَ لمن لا يملكه** — لا الواجهةُ وحدَها');

$jar = sys_get_temp_dir() . '/capstate_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('body' => $b, 'code' => $c);
};
/* دورٌ يكتب في طلباتِ الشراءِ ولا يملك الاعتمادَ المالي */
$reqUser = ''; $reqRole = 0;
$q = $conn->query("SELECT rp.role_id FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                    WHERE m.code = 'Procurement/requests_proc.php' AND rp.can_edit = 1 ORDER BY rp.role_id");
while ($q && ($rr = $q->fetch_row())) {
    $rid = (int) $rr[0];
    $f = $conn->prepare("SELECT 1 FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                          WHERE rp.role_id = ? AND rp.can_edit = 1
                            AND m.code IN ('Finance/approvals_inbox.php','FinRequests/requests.php') LIMIT 1");
    $f->bind_param('i', $rid);
    $f->execute();
    $hasFin = (bool) $f->get_result()->fetch_row();
    $f->close();
    if ($hasFin) { continue; }
    /* ◆ **كلُّ حساباتِ الدورِ لا أوّلُها**: للدورِ ١٦ حسابان، وأوّلُهما لا يدخل —
         فاختيارُ الأوّلِ وحدَه أسقط القياسَ على صفحةِ دخول. */
    $u = $conn->prepare("SELECT username FROM users WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id");
    $rs = (string) $rid;
    $u->bind_param('si', $rs, $CO);
    $u->execute();
    $res = $u->get_result();
    $cands = array();
    while ($res && ($x = $res->fetch_row())) { $cands[] = (string) $x[0]; }
    $u->close();
    foreach ($cands as $cand) {
        $probe = sys_get_temp_dir() . '/capprobe_login_' . getmypid() . '.txt';
        @unlink($probe);
        $ch = curl_init($BASE . '/login.php');
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR => $probe, CURLOPT_COOKIEFILE => $probe, CURLOPT_TIMEOUT => 30));
        $pb = (string) curl_exec($ch); curl_close($ch);
        preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $pb, $pt);
        $ch = curl_init($BASE . '/login.php');
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR => $probe, CURLOPT_COOKIEFILE => $probe, CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(array(
                'username' => $cand, 'password' => '12345678',
                'csrf_token' => isset($pt[1]) ? $pt[1] : ''))));
        $pb = (string) curl_exec($ch); curl_close($ch);
        @unlink($probe);
        if (mb_strpos($pb, 'name="password"') === false) { $reqUser = $cand; $reqRole = $rid; break; }
    }
    if ($reqUser !== '') { break; }
}
$ok($reqUser !== '', "ووُجد حسابُ طالبِ شراءٍ بلا اعتمادٍ ماليّ ({$reqUser} · دور {$reqRole})");
/* ── حارسُ الخضرةِ الكاذبة ─────────────────────────────────────────────────────
     «الحقلُ غائبٌ» يمرُّ **بلا معنًى** إن كانت الصفحةُ صفحةَ دخولٍ أو حجب. فيُشترط
     أوّلًا أن يقع الدخولُ وأن تُصيَّر الشاشةُ **بنموذجِها**؛ فإن لم يقع أُعلن
     الشرطُ غيرَ مقيسٍ ورسب الفاحصُ — ولا يُقرأ صمتُ الصفحةِ نجاحًا.
     (وقع فعلًا: حسابُ الدورِ ١٦ لم يدخل، فمرَّ الشرطُ على صفحةِ دخول.) */
$loggedIn = false; $rendered = false;
if ($reqUser !== '') {
    $b = $http($BASE . '/login.php');
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b['body'], $t);
    $lb = $http($BASE . '/login.php', http_build_query(array(
        'username' => $reqUser, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
    $loggedIn = (mb_strpos($lb['body'], 'name="password"') === false);
}
$ok($loggedIn, "ودخل الحسابُ فعلًا ({$reqUser})", 'تعذَّر الدخول — فلا قياسَ على صفحةِ دخول');
if ($loggedIn) {
    $page = $http($BASE . '/Procurement/requests_proc.php');
    $masked = (string) preg_replace('~<script\b[^>]*>.*?</script>~is', '', $page['body']);
    /* النموذجُ حاضرٌ فعلًا — يُقاس بحقلٍ آخرَ فيه لا بالحقلِ محلِّ الفحص */
    $rendered = (mb_strpos($masked, 'name="password"') === false)
             && (strpos($masked, 'name="priority"') !== false
                 || strpos($masked, 'name="need_source"') !== false);
    $ok($rendered, '**وصُيِّرت الشاشةُ بنموذجِها** — فالغيابُ غيابٌ لا صمتُ صفحةٍ محجوبة',
        'لا نموذجَ في المُخرَج (' . $page['code'] . ')');
    if ($rendered) {
        $ok(stripos($masked, 'name="fin_approval_state"') === false,
            '**والحقلُ الماليُّ غائبٌ عن نموذجِ هذا الدور**');
        $ok(mb_strpos($masked, 'يضبطها الاعتمادُ الماليُّ لا مُقدِّمُ الطلب') !== false,
            'ويرى حالتَه قراءةً بسببٍ مكتوب — لا حقلًا مفقودًا بلا تفسير');
    }
}

@unlink($jar);
$conn->query("DELETE FROM governance_flags WHERE element_code = 'signing_caps'
                AND scope_type = 'entity' AND scope_id = {$entity}");
$post = $sweep();
$say("   كُنس ختامًا: {$post} صفًّا");
foreach ($sweepReport as $line) { $say('   ⚠ تعذَّر حذفٌ — ' . $line); }
$left = 0; $authLeft = 0;
$r = $conn->query("SELECT COUNT(*) FROM financing_operations WHERE op_code LIKE '%" . $TAG . "%'");
if ($r) { $left = (int) $r->fetch_row()[0]; }
$r = $conn->query("SELECT COUNT(*) FROM signing_authorities WHERE doc_ref = '" . $TAG . "'");
if ($r) { $authLeft = (int) $r->fetch_row()[0]; }
$ok($left === 0 && $authLeft === 0 && empty($sweepReport),
    "صفرُ أثرٍ من عائلةِ الوسمِ بعد الجولة (عمليات={$left} · تفويضات={$authLeft})",
    implode(' · ', $sweepReport));

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
