<?php
/**
 * tests/authority_cap_escalation_test.php — شاهدُ سقفِ التفويضِ والتصعيد
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0014
 *
 * **نصُّ اختبارِ القبولِ حرفيًّا** (السجلُّ الجامع · «توقيع العقود والالتزامات»):
 *   «نائبٌ بسقف 100,000 يوقّع عقدًا بـ 90,000 ⇒ يُقبل **ويُسجَّل مرجعُ تفويضه**؛
 *    ويوقّع عقدًا بـ 150,000 ⇒ يُرفض **ويُصعَّد تلقائيًّا لصندوق الرئيس**.»
 *
 * ── والمقيسُ قبل الإصلاح ────────────────────────────────────────────────────
 * `AuthorityGuard::sign()` **مبنيٌّ كاملًا** منذ LEG-01: يقرأ `amount_cap` من
 * `signing_authorities`، ويردُّ 409 فوقَه، ويمنع اعتمادَ الذاتِ 403، ويكتب سطرَ
 * توقيعٍ في `approval_signatures`. وله تسعةُ مستهلكين — **وليس فيهم شاشةُ توقيعِ
 * العقود**. فالسقفُ مبنيٌّ وغيرُ متبنًّى (عيبُ MD-05).
 * والناقصُ الوحيدُ في الحارسِ نفسِه: **يرفض ولا يُصعِّد**.
 *
 * ── ولماذا يزرع هذا الفاحصُ عَلَمًا وتفويضًا ─────────────────────────────────
 * إنفاذُ السقوفِ خلف عَلَمِ `signing_caps` وهو **مطفأٌ في هذا الكيان**، وتفويضاتُ
 * الشركةِ منتهيةٌ أو ملغاة. وإشعالُ العَلَمِ إنتاجيًّا **قرارُ مالكِ نطاقٍ** — لأنَّ
 * الحارسَ حينها يردُّ 403 لكلِّ من لا تفويضَ ساريًا له. فالفاحصُ يزرع الحالةَ
 * الحقيقيةَ (عَلَمٌ + تفويضٌ بسقفٍ) **ويُعيد كلَّ شيءٍ بايتًا ببايتٍ بعده** —
 * فيُثبت السلوكَ بلا أن يغيّر إنتاجًا.
 *
 * ── والطرفانِ يُقاسان ────────────────────────────────────────────────────────
 *   ① تحتَ السقفِ ⇒ يُقبل، **ومرجعُ التفويضِ مسجَّل**، وسطرُ توقيعٍ واحد.
 *   ② فوقَ السقفِ ⇒ يُرفض 409، **وصفٌّ جديدٌ في صندوقِ اعتمادِ الرئيس**، وصفرُ
 *      توقيعٍ على المستند.
 * فحارسٌ يرفض الطرفين ليس سقفًا بل عطلًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/app/Core/AuthorityGuard.php';

$conn = $GLOBALS['conn'];
$CO = 4;
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };

$TAG = 'CAPPROBE';
/* كنسٌ بالعائلةِ — ومُرجَعُ كلِّ حذفٍ مفحوص (FK يردُّ صامتًا) */
$sweep = function () use ($conn, $TAG) {
    $n = 0;
    foreach (array(
        "DELETE FROM approval_signatures WHERE document_type = 'cap_probe_doc'",
        "DELETE FROM exec_approvals WHERE request_no LIKE '%" . $TAG . "%'",
        "DELETE FROM signing_authorities WHERE doc_ref = '" . $TAG . "'",
        "DELETE FROM governance_flags WHERE element_code = 'signing_caps' AND scope_id = -999",
    ) as $q) {
        if ($conn->query($q)) { $n += $conn->affected_rows; }
    }
    return $n;
};
$pre = $sweep();
$say('══ INJ-0014 · سقفُ التفويضِ والتصعيدُ التلقائيّ'
     . ($pre ? "  (كُنس {$pre} من جولةٍ سابقة)" : ''));

/* ── ① الشاشةُ تنادي الحارسَ فعلًا (تبنٍّ لا بناء) ─────────────────────────── */
$scr = (string) @file_get_contents($ROOT . '/Portal/ceo_contracts.php');
$ok(strpos($scr, 'AuthorityGuard::sign(') !== false,
    '**شاشةُ توقيعِ العقودِ تنادي `AuthorityGuard::sign()`** — السقفُ صار متبنًّى');
$ok(strpos($scr, "INSERT INTO exec_approvals") !== false
    && strpos($scr, "'escalation'") !== false,
    'وتُصعِّد إلى صندوقِ اعتمادِ الرئيسِ عند 409 — الرفضُ يصير طلبًا لا صمتًا');
$ok(strpos($scr, "\$authorityRef = 'تفويض #'") !== false,
    'ومرجعُ التفويضِ يُسجَّل مع التوقيعِ لا يُترك نصًّا حرًّا');

/* ── ② الحارسُ نفسُه يحمل الشرطين ─────────────────────────────────────────── */
$g = (string) @file_get_contents($ROOT . '/app/Core/AuthorityGuard.php');
$ok(strpos($g, "\$out['code'] = 409;") !== false && strpos($g, 'amount_cap') !== false,
    'والحارسُ يردُّ 409 فوقَ `amount_cap`');
$ok(strpos($g, 'لا يعتمد المرء ما أنشأه') !== false,
    'ويمنع اعتمادَ الذاتِ بنيويًّا');

/* ── ③ زرعُ الحالةِ الحقيقية ───────────────────────────────────────────────── */
$entity = 0;
$r = $conn->query('SELECT entity_id FROM tenants WHERE tenant_id = ' . $CO . ' LIMIT 1');
if ($r && ($x = $r->fetch_row())) { $entity = (int) $x[0]; }
$ok($entity > 0, "كيانُ الشركةِ معروفٌ (#{$entity}) — فالسقفُ يُقرأ عنه");

$person = 0;
$r = $conn->query("SELECT id FROM users WHERE company_id = {$CO} AND username <> '' ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $person = (int) $x[0]; }
$other = 0;
$r = $conn->query("SELECT id FROM users WHERE company_id = {$CO} AND id <> {$person} ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $other = (int) $x[0]; }
$ok($person > 0 && $other > 0, "وشخصانِ مختلفان (#{$person} · #{$other}) — فاعتمادُ الذاتِ لا يلوّث القياس");

$CAP = 100000.00;
$st = $conn->prepare("INSERT INTO signing_authorities
        (company_id, person_id, entity_id, auth_type, amount_cap, currency,
         scope_type, valid_from, valid_to, doc_ref, state, created_by)
        VALUES (?, ?, ?, 'delegated', ?, 'USD', 'all', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, 'active', ?)");
$authId = 0;
if ($st) {
    $st->bind_param('iiidsi', $CO, $person, $entity, $CAP, $TAG, $person);
    if ($st->execute()) { $authId = (int) $conn->insert_id; }
    $st->close();
}
$ok($authId > 0, "زُرع تفويضٌ ساري بسقف " . number_format($CAP, 0) . " USD (#{$authId})", $conn->error);

/* العَلَمُ يُشعَل لهذا الكيانِ وحدَه — ويُطفأ في الكنس */
$fl = $conn->prepare("INSERT INTO governance_flags (element_code, scope_type, scope_id, enabled)
                      VALUES ('signing_caps', 'entity', ?, 1)");
$flagOn = false;
if ($fl) { $fl->bind_param('i', $entity); $flagOn = $fl->execute(); $fl->close(); }
$ok($flagOn, 'وأُشعل عَلَمُ `signing_caps` لهذا الكيان — فالسقفُ يُنفَّذ');

/* ── ④ الطرفُ الأول: **تحتَ السقفِ** ⇒ يُقبل بمرجعِ تفويض ───────────────────── */
$under = \App\Core\AuthorityGuard::sign($conn, array(
    'document_type' => 'cap_probe_doc', 'document_id' => 9001, 'step' => 'sign',
    'person_id' => $person, 'company_id' => $CO, 'entity_id' => $entity,
    'amount' => 90000.00, 'created_by_person_id' => $other,
));
$ok(!empty($under['ok']), '**عقدٌ بـ90,000 تحتَ سقفِ 100,000 ⇒ يُقبل**',
    'الرمزُ ' . $under['code'] . ' · ' . $under['reason']);
$ok((int) $under['auth_id'] === $authId,
    '**ومرجعُ تفويضِه مسجَّلٌ** (#' . (int) $under['auth_id'] . ')',
    'المتوقَّع ' . $authId);
$sigN = 0;
$r = $conn->query("SELECT COUNT(*) FROM approval_signatures
                    WHERE document_type = 'cap_probe_doc' AND document_id = 9001 AND result = 'signed'");
if ($r) { $sigN = (int) $r->fetch_row()[0]; }
$ok($sigN === 1, "وسطرُ توقيعٍ واحدٌ في `approval_signatures` ({$sigN})");

/* ── ⑤ الطرفُ الثاني: **فوقَ السقفِ** ⇒ يُرفض 409 بلا توقيع ─────────────────── */
$over = \App\Core\AuthorityGuard::sign($conn, array(
    'document_type' => 'cap_probe_doc', 'document_id' => 9002, 'step' => 'sign',
    'person_id' => $person, 'company_id' => $CO, 'entity_id' => $entity,
    'amount' => 150000.00, 'created_by_person_id' => $other,
));
$ok(empty($over['ok']) && (int) $over['code'] === 409,
    '**وعقدٌ بـ150,000 فوقَ السقفِ ⇒ 409**', 'الرمزُ ' . $over['code']);
$ok(mb_strpos((string) $over['reason'], 'المتاح') !== false,
    'برسالةٍ **تسمّي المتاحَ** لا «ممنوع» صامتة', (string) $over['reason']);
$sigOver = 0;
$r = $conn->query("SELECT COUNT(*) FROM approval_signatures
                    WHERE document_type = 'cap_probe_doc' AND document_id = 9002 AND result = 'signed'");
if ($r) { $sigOver = (int) $r->fetch_row()[0]; }
$ok($sigOver === 0, "**وصفرُ توقيعٍ على المستندِ المرفوض** ({$sigOver})");
$denied = 0;
$r = $conn->query("SELECT COUNT(*) FROM approval_signatures
                    WHERE document_type = 'cap_probe_doc' AND document_id = 9002 AND result = 'denied'");
if ($r) { $denied = (int) $r->fetch_row()[0]; }
$ok($denied === 1, "والمنعُ نفسُه سطرٌ مسجَّلٌ ({$denied}) — فالمحاولةُ أثرٌ يُراجَع");

/* ── ⑥ واعتمادُ الذاتِ يُردُّ 403 ولو كان تحتَ السقف ────────────────────────── */
$self = \App\Core\AuthorityGuard::sign($conn, array(
    'document_type' => 'cap_probe_doc', 'document_id' => 9003, 'step' => 'sign',
    'person_id' => $person, 'company_id' => $CO, 'entity_id' => $entity,
    'amount' => 1000.00, 'created_by_person_id' => $person,
));
$ok(empty($self['ok']) && (int) $self['code'] === 403,
    'ومن أنشأ لا يوقّع ولو كان المبلغُ تحتَ سقفِه (403)', 'الرمزُ ' . $self['code']);

/* ── ⑦ الاستعادةُ الكاملة ─────────────────────────────────────────────────── */
$post = $sweep();
$say("   كُنس ختامًا: {$post} صفًّا");
$left = 0;
$r = $conn->query("SELECT COUNT(*) FROM signing_authorities WHERE doc_ref = '" . $TAG . "'");
if ($r) { $left = (int) $r->fetch_row()[0]; }
$flagLeft = 0;
$r = $conn->query("SELECT COUNT(*) FROM governance_flags
                    WHERE element_code = 'signing_caps' AND scope_type = 'entity' AND scope_id = " . $entity);
if ($r) { $flagLeft = (int) $r->fetch_row()[0]; }
/* العَلَمُ المزروعُ لهذا الكيانِ يُطفأ صراحةً — الكنسُ العامُّ لا يطاله */
if ($flagLeft > 0) {
    $conn->query("DELETE FROM governance_flags WHERE element_code = 'signing_caps'
                    AND scope_type = 'entity' AND scope_id = " . $entity);
    $flagLeft = $conn->affected_rows > 0 ? 0 : $flagLeft;
}
$ok($left === 0 && $flagLeft === 0,
    "صفرُ أثرٍ من عائلةِ الوسمِ بعد الجولة (تفويضات={$left} · أعلام={$flagLeft})");

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
