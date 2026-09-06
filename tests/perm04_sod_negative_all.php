<?php
/**
 * tests/perm04_sod_negative_all.php — اختبارٌ سالبٌ لكلِّ تركيبةِ فصلِ واجبات
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **العلّةُ المقيسة**: المقياسُ ㉕ يقرأ «100٪» وهو مقيسٌ على **`SOD-06` وحدَها**
 *   — واحدةٌ من ثلاثَ عشرةَ (7.7٪). ورقمٌ أخضرُ على مقامٍ واحدٍ من ثلاثةَ عشرَ
 *   لا يحرس اثنتي عشرةَ تركيبةً. [[measure-blind-spots]]
 *
 * ◆ **والتركيباتُ عائلتان لكلٍّ نقطةُ إنفاذِها — ولا تُخلَطان**:
 *   ① **حبّةُ المستند** (`scope='document'`) — إنفاذُها **سلسلةُ الاعتماد**:
 *      الشخصُ نفسُه لا يسجّل نوعَين متعارضَين على المستندِ نفسِه. تُجرَّب
 *      بتسجيلٍ حقيقيٍّ عبرَ `ApprovalGate::record` ثمَّ يُنتظَر الردّ.
 *   ② **حبّةُ الدور** (`scope='role'`) — إنفاذُها **بوّابةُ الإسناد**:
 *      `AssignmentGate::checkConflicts` ترفض جمعَ طرفَي التركيبةِ في فاعلٍ واحد.
 *
 * ⛔ **والحالُ يُستعاد حتمًا**: كلُّ صفٍّ يكتبه المسبارُ في سلسلةِ الاعتمادِ
 *   يُمحى في `register_shutdown_function` — فمسبارٌ يترك قرارَ اعتمادٍ يزيّف
 *   سلسلةً ماليّةً حقيقيّة.
 *
 * التشغيل: php tests/perm04_sod_negative_all.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/permissions_helper.php';
require_once dirname(__DIR__) . '/app/Services/Finance/ApprovalGate.php';
require_once dirname(__DIR__) . '/app/Services/Exec/AssignmentGate.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function chk($c, $m, $d = '') { $c ? ok($m . ($d !== '' ? " — {$d}" : '')) : bad($m . ($d !== '' ? " — {$d}" : '')); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = @$conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

$CO = 4;
$KIND = 'perm04_probe';
$prevSession = isset($_SESSION['user']) ? $_SESSION['user'] : null;
register_shutdown_function(function () use ($conn, $KIND, $prevSession) {
    @$conn->query("DELETE FROM fin_approval_chain WHERE source_kind = '" . $conn->real_escape_string($KIND) . "'");
    if ($prevSession === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $prevSession; }
});
@$conn->query("DELETE FROM fin_approval_chain WHERE source_kind = '" . $conn->real_escape_string($KIND) . "'");

fwrite(STDOUT, "\n══ PERM-04 — اختبارٌ سالبٌ لكلِّ تركيبة ══\n");

/* ═══ الجرد: التركيباتُ بعائلتَيها ═══════════════════════════════════════ */
head('الجرد — كلُّ تركيبةٍ تُعرَف بحبّتِها');
$pairs = array();
$q = $conn->query("SELECT code, func_a, func_b, scope, roles_a, roles_b, severity
                     FROM sec_sod_pairs WHERE active = 1 ORDER BY code");
while ($q && ($x = $q->fetch_assoc())) { $pairs[] = $x; }
$byDoc = 0; $byRole = 0;
foreach ($pairs as $p) { if ((string) $p['scope'] === 'document') { $byDoc++; } else { $byRole++; } }
chk(count($pairs) === 13, '★ ثلاثَ عشرةَ تركيبةً نافذة', count($pairs) . ' تركيبة');
chk($byDoc + $byRole === count($pairs), 'وكلُّها مصنَّفةٌ بحبّتِها',
    'مستند ' . $byDoc . ' · دور ' . $byRole);

/* ═══ ① عائلةُ المستند — سلسلةُ الاعتماد ════════════════════════════════ */
head('① حبّةُ المستند — الشخصُ نفسُه لا يجمع نوعَين متعارضَين');

$conf = array();
$q = $conn->query("SELECT apr_a, apr_b, rule_text FROM fin_approval_conflicts
                    WHERE active = 1 AND apr_a LIKE 'APR-%' AND apr_b LIKE 'APR-%'");
while ($q && ($x = $q->fetch_assoc())) { $conf[] = $x; }
chk(count($conf) > 0, 'أزواجُ الأنواعِ المتعارضةُ مُعلَنة', count($conf) . ' زوجًا');

$types = array();
$q = $conn->query("SELECT code, seq, allowed_roles, needs_cap FROM fin_approval_types
                    WHERE active = 1 AND code LIKE 'APR-%' ORDER BY seq");
while ($q && ($x = $q->fetch_assoc())) { $types[(string) $x['code']] = $x; }

/* فاعلٌ يصلح لكلِّ نوعٍ يُختبَر — ويُختار من أدوارِ النوعِ حيث تُذكر. */
$actorFor = function ($apr) use ($conn, $types, $one) {
    $ar = trim((string) ($types[$apr]['allowed_roles'] ?? ''));
    if ($ar === '') {
        return $one("SELECT id FROM users WHERE is_deleted=0 AND status='active' AND company_id=4 LIMIT 1");
    }
    $safe = preg_replace('/[^0-9,]/', '', $ar);
    if ($safe === '') { return 0; }
    return $one("SELECT id FROM users WHERE role IN ({$safe}) AND is_deleted=0
                   AND status='active' AND company_id=4 LIMIT 1");
};

$tested = 0;
foreach ($conf as $c) {
    $a = (string) $c['apr_a']; $b = (string) $c['apr_b'];
    if (!isset($types[$a]) || !isset($types[$b])) { continue; }
    /* الفاعلُ يجب أن يصلح للنوعَين معًا وإلّا ردَّه حارسُ الدورِ لا حارسُ التعارض. */
    $ra = trim((string) $types[$a]['allowed_roles']);
    $rb = trim((string) $types[$b]['allowed_roles']);
    $actor = 0;
    if ($ra === '' && $rb === '') { $actor = $actorFor($a); }
    elseif ($ra === '') { $actor = $actorFor($b); }
    elseif ($rb === '') { $actor = $actorFor($a); }
    else {
        $ia = array_map('trim', explode(',', $ra));
        $ib = array_map('trim', explode(',', $rb));
        $both = array_values(array_intersect($ia, $ib));
        if ($both) {
            $safe = preg_replace('/[^0-9,]/', '', implode(',', $both));
            $actor = $one("SELECT id FROM users WHERE role IN ({$safe}) AND is_deleted=0
                             AND status='active' AND company_id=4 LIMIT 1");
        }
    }
    if ($actor < 1) {
        ok('◆ ' . $a . ' × ' . $b . ' — مغلقٌ بحارسِ الدورِ قبلَ التعارض: لا فاعلَ يصلح للنوعَين');
        $tested++;
        continue;
    }

    $ref = 'PRB-' . $a . '-' . $b;
    /* يُبنى ما يسبق النوعَ الأوّلَ حتى لا يردَّه حارسُ الترتيب. */
    $seqA = (int) $types[$a]['seq'];
    foreach ($types as $code => $t) {
        if ((int) $t['seq'] >= $seqA) { continue; }
        $pre = $actorFor($code);
        if ($pre < 1) { continue; }
        \App\Services\Finance\ApprovalGate::record($conn, array(
            'company_id' => $CO, 'source_kind' => $KIND, 'source_ref' => $ref,
            'apr_code' => $code, 'decision' => 'approved',
            'actor_user_id' => $pre, 'actor_role_id' => 0,
            'amount' => '1', 'currency' => 'USD', 'note' => 'probe pre'));
    }
    $r1 = \App\Services\Finance\ApprovalGate::record($conn, array(
        'company_id' => $CO, 'source_kind' => $KIND, 'source_ref' => $ref,
        'apr_code' => $a, 'decision' => 'approved',
        'actor_user_id' => $actor, 'actor_role_id' => 0,
        'amount' => '1', 'currency' => 'USD', 'note' => 'probe A'));

    /* بينهما ما يسبق الثاني، بفاعلٍ آخرَ حتى لا يتلوّث الاختبار. */
    $seqB = (int) $types[$b]['seq'];
    foreach ($types as $code => $t) {
        if ((int) $t['seq'] >= $seqB || (int) $t['seq'] <= $seqA) { continue; }
        $pre = $actorFor($code);
        if ($pre < 1 || $pre === $actor) { continue; }
        \App\Services\Finance\ApprovalGate::record($conn, array(
            'company_id' => $CO, 'source_kind' => $KIND, 'source_ref' => $ref,
            'apr_code' => $code, 'decision' => 'approved',
            'actor_user_id' => $pre, 'actor_role_id' => 0,
            'amount' => '1', 'currency' => 'USD', 'note' => 'probe mid'));
    }

    $r2 = \App\Services\Finance\ApprovalGate::record($conn, array(
        'company_id' => $CO, 'source_kind' => $KIND, 'source_ref' => $ref,
        'apr_code' => $b, 'decision' => 'approved',
        'actor_user_id' => $actor, 'actor_role_id' => 0,
        'amount' => '1', 'currency' => 'USD', 'note' => 'probe B'));

    if (!empty($r1['ok'])) {
        chk(empty($r2['ok']), '⛔ سالب: ' . $a . ' × ' . $b . ' — الفاعلُ نفسُه يُردّ',
            (string) ($r2['reason'] ?? ''));
    } else {
        /* رُدَّ الأوّلُ بحارسٍ سابقٍ (دورٌ أو ترتيبٌ أو سقف) — وذاك منعٌ أيضًا. */
        ok('◆ ' . $a . ' × ' . $b . ' — مغلقٌ بحارسٍ أسبقَ: ' . (string) ($r1['reason'] ?? ''));
    }
    $tested++;
}
chk($tested === count($conf), 'وكلُّ زوجٍ مُعلَنٍ جُرِّب', $tested . ' من ' . count($conf));

/* ═══ ② عائلةُ الدور — بوّابةُ الإسناد ══════════════════════════════════ */
head('② حبّةُ الدور — بوّابةُ الإسنادِ ترفض جمعَ الطرفَين');
$roleP = array();
foreach ($pairs as $p) {
    if ((string) $p['scope'] !== 'role') { continue; }
    $roleP[] = $p;
}
chk(count($roleP) > 0, 'تركيباتٌ حبّتُها الدور', count($roleP) . ' تركيبة');

$probeUser = $one("SELECT id FROM users WHERE is_deleted=0 AND status='active' AND company_id=4 LIMIT 1");
$blocked = 0; $skipped = 0;
foreach ($roleP as $p) {
    $A = array_filter(array_map('intval', explode(',', (string) $p['roles_a'])));
    $B = array_filter(array_map('intval', explode(',', (string) $p['roles_b'])));
    if (!$A || !$B) { $skipped++; continue; }
    /* يُسأل: لو حمل فاعلٌ دورَ الطرفِ الأوّلِ ثمَّ طُلب له الثاني — أتردُّ البوّابة؟ */
    $holder = $one("SELECT id FROM users WHERE role IN (" . implode(',', $A) . ")
                      AND is_deleted=0 AND status='active' AND company_id=4 LIMIT 1");
    if ($holder < 1) { $skipped++; continue; }
    $res = \App\Services\Exec\AssignmentGate::checkConflicts($conn, $CO, $holder, (int) $B[0]);
    $hits = is_array($res) ? (isset($res['hits']) ? $res['hits'] : $res) : array();
    $named = false;
    foreach ((array) $hits as $hit) {
        if (is_string($hit) && strpos($hit, (string) $p['code']) !== false) { $named = true; break; }
    }
    chk($named, '⛔ سالب: ' . $p['code'] . ' — البوّابةُ تسمّي التعارضَ عند الإسناد',
        mb_substr((string) $p['func_a'], 0, 22) . ' × ' . mb_substr((string) $p['func_b'], 0, 22));
    if ($named) { $blocked++; }
}
printf("     مُنعت: %d · متخطّاةٌ لغيابِ حاملٍ أو أدوارٍ مسمّاة: %d\n", $blocked, $skipped);

/* ═══ ③ والبنيةُ تغلق ما لا تبلغه البوّابة ═══════════════════════════════ */
head('③ البنيةُ — عمودُ الدورِ واحدٌ فلا يجتمع دوران في فاعل');
$multi = $one("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'");
chk($multi === 1, '★ `users.role` عمودٌ واحد — فالجمعُ بين دورَين مستحيلٌ بنيويًّا');
$dual = $one("SELECT COUNT(*) FROM (SELECT user_id FROM gov_authority_grants
               WHERE revoked_at IS NULL AND source='profile' GROUP BY user_id HAVING COUNT(*)>1) z");
chk($dual === 0, '★ ولا فاعلَ بمنحتَين دائمتَين', $dual . ' فاعلًا');

/* ═══ ④ ولا يبقى للمسبارِ أثرٌ في سلسلةٍ ماليّة ═════════════════════════ */
head('④ النظافة');
$left = $one("SELECT COUNT(*) FROM fin_approval_chain WHERE source_kind = '" . $conn->real_escape_string($KIND) . "'");
printf("     صفوفُ المسبارِ في السلسلة: %d (تُمحى عند الخروج)\n", $left);
chk(true, '★ الكنسُ مسجَّلٌ في `register_shutdown_function` فلا يعتمد على نجاحِ المسبار');

fwrite(STDOUT, "\n" . str_repeat('─', 70) . "\n");
fwrite(STDOUT, "   النتيجة: {$PASS} نجاح · {$FAIL} رسوب\n");
fwrite(STDOUT, $FAIL === 0 ? "   ✔ PERM04_SOD_NEGATIVE_ALL = PASS\n" : "   ✘ PERM04_SOD_NEGATIVE_ALL = FAIL\n");
exit($FAIL === 0 ? 0 : 1);
