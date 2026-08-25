<?php
/**
 * tools/repair01_w1_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ الأولى
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأخضرُ وحدَه لا يُثبت شيئًا**: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدم. فهنا يُكسَر كلُّ حاجبٍ على حِدةٍ ويجب أن يسقط **باسمِه هو** — ثمّ
 *   تُرجَع الحالة. الحاجبُ الذي لا يسقط عند كسرِه **أعمى** والمرحلةُ مفتوحة.
 *
 * ◆ **والرسوُّ على الرمزِ لا العبارة**: يُلتقط `✘ W1-nn` — لا نصٌّ عربيٌّ قد
 *   يظهر في رسالةِ حالةِ خطأٍ فيُخضِرَّ كذبًا.
 *
 * ◆ **وتسعةُ كسورٍ في القاعدةِ واثنان في الملفّات**: السقّاطةُ ووصلُها بالخطّافِ
 *   يُكسَران في نسختَيهما على القرصِ وتُرجَعان بالبايتِ الأصليّ.
 *
 * ◆ **والإرجاعُ مضمونٌ بـtry/finally ثمّ مُتحقَّقٌ منه**: تُعاد البوّابةُ في
 *   النهايةِ ويجب أن تعود خضراءَ ١٣/١٣ — وإلّا فالفحصُ السلبيُّ نفسُه أفسد الدفتر.
 *
 * التشغيل: php tools/repair01_w1_negative.php
 * الخروج : 0 كلُّ الحواجبِ يقظة · 1 حاجبٌ أعمى أو إرجاعٌ فاشل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$PHP  = PHP_BINARY;
$GATE = $ROOT . '/tools/repair01_w1_gate.php';
$BASE = $ROOT . '/docs/update0012/debt_baseline.json';
$HOOK = $ROOT . '/scripts/pre-commit.ps1';

/** يشغّل البوّابةَ ويعيد [رمزُ الخروج، الحواجبُ الساقطةُ برموزها] */
function run_gate($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W1-') !== false && preg_match('/W1-\d+/', $l, $m)) { $failed[] = $m[0]; }
    }
    return array($code, $failed);
}
function e(mysqli $c, $s) { return "'" . $c->real_escape_string((string) $s) . "'"; }

/* ── الأساس ─────────────────────────────────────────────────────────────── */
list($c0, $f0) = run_gate($PHP, $GATE);
if ($c0 !== 0) {
    echo "✘ البوّابةُ ساقطةٌ قبل الكسر (" . implode(',', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

/* ── القيمُ الأصليةُ المُلتقَطةُ قبل الكسر ───────────────────────────────── */
function one(mysqli $c, $sql) { $r = $c->query($sql); return $r ? $r->fetch_assoc() : null; }

$oFb   = one($conn, "SELECT id, w1_verdict, w1_reason FROM repair01_ownership
                      WHERE classification='FORBIDDEN' ORDER BY id LIMIT 1");
$oRev  = one($conn, "SELECT id FROM repair01_ownership
                      WHERE classification='FORBIDDEN' AND w1_verdict='REVOKED_TO_OWNER' ORDER BY id LIMIT 1");
$oCtx  = one($conn, "SELECT id FROM repair01_ownership
                      WHERE classification='FORBIDDEN' AND w1_verdict='CONTEXTUAL_READ_ONLY' ORDER BY id LIMIT 1");
$oSurf = one($conn, "SELECT id, resp_role, canonical_code FROM repair01_surfaces ORDER BY id LIMIT 1");
$oFin  = one($conn, "SELECT id, canonical_code FROM repair01_surfaces
                      WHERE dept_legacy='المالية والخزينة' ORDER BY id LIMIT 1");
$oCeo  = one($conn, "SELECT id FROM repair01_surfaces WHERE canonical_code='EX-CEO' ORDER BY id LIMIT 1");
/* ⚠ **الكسرُ يستهدف ما يفحصه الحاجبُ لا أوّلَ صفٍّ أبجديًّا** (RPR-PATCH-07):
   كان يأخذ `ORDER BY decision_id LIMIT 1` — وW1-08 لا يفحص كلَّ القرارات، بل
   **المُشارَ إليه من `role_source`** ومعه قرارُ إعلانِ الخلاء. فحين خلا
   `role_source` صار الكسرُ يقع على قرارٍ لا يراه الحاجبُ فلا يسقط —
   **وهو فحصٌ سلبيٌّ أعمى**، أخطرُ من غيابِه لأنّه يمنح ثقةً كاذبة.
   فيُلتقط المُشارُ إليه أوّلًا، وإن خلا فقرارُ إعلانِ الخلاء `W1-D-03`. */
$oDec = one($conn, "SELECT decision_id, rationale FROM repair01_w1_decisions
                     WHERE decision_id IN (
                       SELECT DISTINCT SUBSTRING(role_source, 13) FROM repair01_surfaces
                        WHERE role_source LIKE 'W1\\_DECISION:%')
                     LIMIT 1");
if (!$oDec) {
    $oDec = one($conn, "SELECT decision_id, rationale FROM repair01_w1_decisions
                         WHERE decision_id = 'W1-D-03' LIMIT 1");
}
$baseTxt = (string) file_get_contents($BASE);
$hookTxt = (string) file_get_contents($HOOK);

if (!$oFb || !$oRev || !$oCtx || !$oSurf || !$oFin || !$oCeo || !$oDec || $baseTxt === '' || $hookTxt === '') {
    echo "✘ تعذّر التقاطُ الحالةِ الأصلية — لا يُكسَر ما لا يُرجَع.\n";
    exit(1);
}

/* ── حالاتُ الكسر: [الحاجب، الوصف، الكسر، الإرجاع] ───────────────────────── */
$cases = array(
    array('W1-01', 'نزعُ الحكمِ عن ظهورٍ محرَّم',
        "UPDATE repair01_ownership SET w1_verdict=NULL WHERE id=" . (int) $oFb['id'],
        "UPDATE repair01_ownership SET w1_verdict=" . e($conn, $oFb['w1_verdict']) . " WHERE id=" . (int) $oFb['id']),

    array('W1-02', 'إفراغُ الدورِ المسؤولِ لسطح',
        "UPDATE repair01_surfaces SET resp_role='—' WHERE id=" . (int) $oSurf['id'],
        "UPDATE repair01_surfaces SET resp_role=" . e($conn, $oSurf['resp_role']) . " WHERE id=" . (int) $oSurf['id']),

    array('W1-03', 'نزعُ الرمزِ المعياريِّ عن سطح',
        "UPDATE repair01_surfaces SET canonical_code=NULL WHERE id=" . (int) $oSurf['id'],
        "UPDATE repair01_surfaces SET canonical_code=" . e($conn, $oSurf['canonical_code']) . " WHERE id=" . (int) $oSurf['id']),

    array('W1-04', 'رمزٌ مخترَعٌ خارجَ سجلِّ الإدارات',
        "UPDATE repair01_surfaces SET canonical_code='DEP-99' WHERE id=" . (int) $oSurf['id'],
        "UPDATE repair01_surfaces SET canonical_code=" . e($conn, $oSurf['canonical_code']) . " WHERE id=" . (int) $oSurf['id']),

    array('W1-05', 'حكمٌ بلا مبرَّرٍ مكتوب',
        "UPDATE repair01_ownership SET w1_reason='' WHERE id=" . (int) $oFb['id'],
        "UPDATE repair01_ownership SET w1_reason=" . e($conn, $oFb['w1_reason']) . " WHERE id=" . (int) $oFb['id']),

    array('W1-06', 'وسمُ ظهورٍ بلا سندٍ «سياقيًّا مقروءًا»',
        "UPDATE repair01_ownership SET w1_verdict='CONTEXTUAL_READ_ONLY' WHERE id=" . (int) $oRev['id'],
        "UPDATE repair01_ownership SET w1_verdict='REVOKED_TO_OWNER' WHERE id=" . (int) $oRev['id']),

    array('W1-07', 'سحبُ ظهورٍ **له** سندٌ في السجلّ',
        "UPDATE repair01_ownership SET w1_verdict='REVOKED_TO_OWNER' WHERE id=" . (int) $oCtx['id'],
        "UPDATE repair01_ownership SET w1_verdict='CONTEXTUAL_READ_ONLY' WHERE id=" . (int) $oCtx['id']),

    array('W1-08', 'قرارٌ مُشارٌ إليه بلا مبرَّر',
        "UPDATE repair01_w1_decisions SET rationale='' WHERE decision_id=" . e($conn, $oDec['decision_id']),
        "UPDATE repair01_w1_decisions SET rationale=" . e($conn, $oDec['rationale']) . " WHERE decision_id=" . e($conn, $oDec['decision_id'])),

    array('W1-09', 'تسريبُ سطحٍ ماليٍّ خارجَ الشقَّين',
        "UPDATE repair01_surfaces SET canonical_code='DEP-01' WHERE id=" . (int) $oFin['id'],
        "UPDATE repair01_surfaces SET canonical_code=" . e($conn, $oFin['canonical_code']) . " WHERE id=" . (int) $oFin['id']),

    array('W1-10', 'ادّعاءُ سطحِ نوّابٍ مبنيّ',
        "UPDATE repair01_surfaces SET canonical_code='EX-DVP' WHERE id=" . (int) $oCeo['id'],
        "UPDATE repair01_surfaces SET canonical_code='EX-CEO' WHERE id=" . (int) $oCeo['id']),

    array('W1-13', 'حقنُ قرارٍ في مخزنٍ مُجمَّد',
        "INSERT INTO repair01_decisions (decision_id, domain, status) VALUES ('ZZ-NEG-01','فحصٌ سلبيّ','APPROVED')",
        "DELETE FROM repair01_decisions WHERE decision_id='ZZ-NEG-01'"),
);

$blind = array(); $ok = 0;
foreach ($cases as $cse) {
    list($id, $desc, $break, $undo) = $cse;
    $conn->query($break);
    list($code, $failed) = run_gate($PHP, $GATE);
    $conn->query($undo);
    $caught = in_array($id, $failed, true);
    if ($caught) { $ok++; printf("  ✔ %-7s سقط عند: %s\n", $id, $desc); }
    else { $blind[] = $id; printf("  ✘ %-7s **أعمى** — لم يسقط عند: %s  (الساقط: %s)\n", $id, $desc, $failed ? implode(',', $failed) : 'لا شيء'); }
}

/* ── كسرانِ على القرص: خطُّ الأساسِ ووصلُ الخطّاف ─────────────────────────── */
$fileCases = array(
    array('W1-11', 'نزعُ صنفِ دَينٍ من خطِّ الأساس', $BASE, $baseTxt,
        function ($t) { $j = json_decode($t, true); unset($j['debts']['RP-06']); return json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); }),
    array('W1-12', 'فكُّ وصلِ السقّاطةِ عن الخطّاف', $HOOK, $hookTxt,
        function ($t) { return str_replace('tools/u12_debt_ratchet.php', 'tools/__unwired__.php', $t); }),
);
foreach ($fileCases as $fc) {
    list($id, $desc, $path, $orig, $mutate) = $fc;
    try {
        file_put_contents($path, $mutate($orig));
        list($code, $failed) = run_gate($PHP, $GATE);
    } finally {
        file_put_contents($path, $orig);
    }
    $caught = in_array($id, $failed, true);
    if ($caught) { $ok++; printf("  ✔ %-7s سقط عند: %s\n", $id, $desc); }
    else { $blind[] = $id; printf("  ✘ %-7s **أعمى** — لم يسقط عند: %s  (الساقط: %s)\n", $id, $desc, $failed ? implode(',', $failed) : 'لا شيء'); }
}

/* ═══════════════════════════════════════════════════════════════════════════
 * ثمانيةُ كواشفِ الدَّينِ — كلٌّ يُثبَّت بزرعِ مخالفةٍ واحدةٍ ثمّ نزعِها
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **سقّاطةٌ لا ترصد شيئًا وثيقةٌ لا سقّاطة**: `RP-06` اليومَ صفر — والصفرُ
 *   إمّا نظافةٌ حقيقيةٌ أو كاشفٌ أعمى، ولا يفرّق بينهما إلّا الزرعُ المتعمَّد.
 * ◆ والملفُّ المزروعُ **خاملٌ بالبنية**: `exit;` في سطرِه الأوّلِ وكلُّ الأنماطِ
 *   داخلَ تعليق — فلا يُنفَّذ شيءٌ لو طُلب في اللحظةِ التي كان فيها موجودًا.
 * ◆ ومسارُه `Governance/` لأنّ `RP-04` لا يقيس إلّا في مساراتِ الإدارة.
 * ═══════════════════════════════════════════════════════════════════════════ */
require_once $ROOT . '/tools/lib/repair01_debt_scan.php';

$PROBE = $ROOT . '/Governance/zz_repair01_negative_probe.php';
$NAVROUTE = 'zz_repair01_negative_probe.php';
$probeSrc = "<?php exit; /* insidebar — ملفُّ زرعٍ للفحصِ السلبيِّ · يُنشأ ويُنزع في نفسِ الثانية\n"
    . "  RP-03: if (\$_SESSION['user']['role'] == \"10\") {}\n"
    . "  RP-04: \$conn->query(\"SELECT 1 FROM gov_screen_cycle\");\n"
    . "  RP-05: UPDATE zz SET approval_status = 1\n"
    . "  RP-06: INSERT INTO ems_business_events (a) VALUES (1)\n"
    . "  RP-07: \$_GET['search'] ... WHERE x LIKE '%y%'\n"
    . "  RP-08: SELECT SUM(qty) AS zz_total FROM zz\n"
    . "*/\n";

$before = repair01_debt_measure($ROOT, $conn);
/* `door` محكومٌ بـCHECK — والزرعُ يحترم القيدَ ولا يلتفُّ عليه.
   و`role_id = 0` لا يملكه أحد، فالصفُّ لا يظهر لمستخدمٍ في الثانيةِ التي عاشها. */
$navOk  = $conn->query("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
                        VALUES (0, 'SET', 0, 0, 'zz فحصٌ سلبيّ', " . e($conn, $NAVROUTE) . ", '', 0, '', 1)");
$navId  = $navOk ? (int) $conn->insert_id : 0;
if (!$navOk) { echo "  ⚠ تعذّر زرعُ مسارِ التنقّل: " . $conn->error . "\n"; }
try {
    file_put_contents($PROBE, $probeSrc);
    $after = repair01_debt_measure($ROOT, $conn);
} finally {
    if (is_file($PROBE)) { unlink($PROBE); }
    if ($navId > 0) { $conn->query("DELETE FROM nav_items WHERE id=" . $navId); }
}
$restoreRp = repair01_debt_measure($ROOT, $conn);

echo "\n── كواشفُ الدَّينِ الثمانيةُ · زرعٌ ونزع ──\n";
$rpBlind = array(); $rpOk = 0;
foreach (array_keys(repair01_debt_classes()) as $k) {
    $b = $before['counts'][$k]; $a = $after['counts'][$k]; $z = $restoreRp['counts'][$k];
    $rose = ($b !== null && $a !== null && $a > $b);
    $back = ($z === $b);
    if ($rose && $back) { $rpOk++; printf("  ✔ %-6s رصد الزرعَ %d ← %d ثمّ عاد %d\n", $k, $b, $a, $z); }
    else { $rpBlind[] = $k; printf("  ✘ %-6s **أعمى أو لم يرجع** — قبل %s · بعد %s · بعد النزع %s\n",
        $k, $b === null ? '—' : $b, $a === null ? '—' : $a, $z === null ? '—' : $z); }
}

/* ── الإرجاعُ مُتحقَّقٌ منه لا مُدَّعى ────────────────────────────────────── */
echo "\n";
list($cz, $fz) = run_gate($PHP, $GATE);
$restored = ($cz === 0);
echo $restored
    ? "الإرجاع: البوّابةُ عادت خضراءَ ✔\n"
    : "✘ الإرجاعُ فشل — البوّابةُ ما زالت ساقطة (" . implode(',', $fz) . ")\n";

$total = count($cases) + count($fileCases);
$rpTot = count(repair01_debt_classes());
echo str_repeat('─', 71), "\n";
echo "الفحصُ السلبيّ: {$ok}/{$total} حاجبًا يقظًا  ·  أعمى: " . count($blind)
   . (count($blind) ? ' (' . implode(',', $blind) . ')' : '') . "\n";
echo "كواشفُ الدَّين: {$rpOk}/{$rpTot} رصدت الزرعَ ورجعت  ·  أعمى: " . count($rpBlind)
   . (count($rpBlind) ? ' (' . implode(',', $rpBlind) . ')' : '') . "\n";
$allOk = (count($blind) === 0 && count($rpBlind) === 0 && $restored);
echo 'الحكم: ' . ($allOk ? "لا حاجبَ أعمى ولا كاشفَ أعمى ✔\n" : "المرحلةُ غيرُ مُغلقة ✘\n");
exit($allOk ? 0 : 1);
