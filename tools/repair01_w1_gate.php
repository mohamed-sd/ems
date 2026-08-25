<?php
/**
 * tools/repair01_w1_gate.php — بوّابةُ المرحلةِ الأولى · الملكيّةُ والسقّاطة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **البوّابةُ تُعيد القياسَ ولا تقرأ ما خزّنَته** (‏_CONTEXT §قواعد القياس ١):
 *   فحاجبُ الظهورِ السياقيِّ **يُعيد اشتقاقَ السببِ من سجلِّ الشاشات** ولا يصدّق
 *   عمودَ `w1_rule` الذي كتبته الأداة. وبوّابةٌ تقرأ مخرَجَ الأداةِ التي تفحصها
 *   حشوٌ لا فحص.
 *
 * ◆ **والمقامُ كاملٌ لا مختار** (§٤): ٦٦٤ سطحًا و٢٦٥ ظهورًا و١٤ سجلَّ دَينٍ —
 *   لا عيّنةَ ولا «أوّلُ مئة».
 *
 * ◆ **والرسوُّ على البنيةِ لا العبارة** (§٣): `W1-12` يفحص أنّ خطّافَ ما قبل
 *   الالتزام **يستدعي ملفَّ السقّاطةِ باسمِه**، لا أنّ فيه كلمةَ «ratchet».
 *
 * ◆ **وحاجبان عكسيّان** (`W1-06` و`W1-07`): الأوّلُ يمنع وسمَ ظهورٍ بلا سندٍ
 *   «سياقيًّا»، والثاني يمنع سحبَ ظهورٍ **له** سندٌ. فبلا الثاني تُغلَق الـ٢٦٥
 *   كلُّها بـ`REVOKED_TO_OWNER` وتُخضِرُّ البوّابةُ على مذبحة.
 *
 * التشغيل: php tools/repair01_w1_gate.php
 * الخروج : 0 خضراء · 1 حاجبٌ ساقط
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/tools/lib/repair01_debt_scan.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

function q1(mysqli $c, $sql) { $r = $c->query($sql); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; }

$PASS = 0; $FAIL = 0; $LINES = array();
function gate($id, $title, $ok, $detail)
{
    global $PASS, $FAIL, $LINES;
    if ($ok) { $PASS++; $LINES[] = sprintf("  ✔ %-8s %-38s %s", $id, $title, $detail); }
    else     { $FAIL++; $LINES[] = sprintf("  ✘ %-8s %-38s %s", $id, $title, $detail); }
}

echo "═══════════ بوّابةُ المرحلةِ الأولى — REPAIR01 · الملكيّة ═══════════\n";

/* ── W1-01 · الظهورُ المحرَّمُ مغلقٌ بحكمٍ لكلِّ صفّ ───────────────────────── */
$fbAll  = (int) q1($conn, "SELECT COUNT(*) FROM repair01_ownership WHERE classification='FORBIDDEN'");
$fbOpen = (int) q1($conn, "SELECT COUNT(*) FROM repair01_ownership
                            WHERE classification='FORBIDDEN' AND (w1_verdict IS NULL OR w1_verdict='')");
gate('W1-01', 'الظهورُ المحرَّمُ محكومٌ كلُّه',
     $fbOpen === 0 && $fbAll > 0, "المقام {$fbAll} · مفتوحٌ بلا حكم {$fbOpen}");

/* ── W1-02 · لا سطحَ بلا دورٍ مسؤول ──────────────────────────────────────── */
$srAll  = (int) q1($conn, "SELECT COUNT(*) FROM repair01_surfaces");
$srNone = (int) q1($conn, "SELECT COUNT(*) FROM repair01_surfaces WHERE resp_role IN ('','—','-')");
gate('W1-02', 'الدورُ المسؤولُ مملوءٌ للكلّ',
     $srNone === 0 && $srAll > 0, "المقام {$srAll} · فارغ {$srNone}");

/* ── W1-03 · لا سطحَ بلا رمزٍ معياريّ ────────────────────────────────────── */
$cnNull = (int) q1($conn, "SELECT COUNT(*) FROM repair01_surfaces
                            WHERE canonical_code IS NULL OR canonical_code=''");
gate('W1-03', 'الرمزُ المعياريُّ مُسنَدٌ للكلّ', $cnNull === 0, "بلا رمز {$cnNull} من {$srAll}");

/* ── W1-04 · كلُّ رمزٍ مكتوبٍ قائمٌ في سجلِّ الإدارات (لا رمزَ مخترَع) ────── */
$orphan = (int) q1($conn, "SELECT COUNT(*) FROM repair01_surfaces s
                            LEFT JOIN repair01_departments d ON d.canonical_code = s.canonical_code
                            WHERE s.canonical_code IS NOT NULL AND s.canonical_code<>'' AND d.canonical_code IS NULL");
$codes  = (int) q1($conn, "SELECT COUNT(DISTINCT canonical_code) FROM repair01_surfaces");
gate('W1-04', 'الرموزُ من سجلِّ الإدارات لا مخترَعة',
     $orphan === 0, "رموزٌ مستعملة {$codes} · يتيمٌ خارجَ السجلّ {$orphan}");

/* ── W1-05 · كلُّ حكمٍ يحمل قاعدةً ومبرَّرًا ودليلًا ─────────────────────── */
$bare = (int) q1($conn, "SELECT COUNT(*) FROM repair01_ownership
                          WHERE classification='FORBIDDEN' AND w1_verdict IS NOT NULL
                            AND (w1_rule='' OR w1_reason='' OR w1_evidence='')");
gate('W1-05', 'لا حكمَ بلا قاعدةٍ ومبرَّرٍ ودليل', $bare === 0, "حكمٌ عارٍ {$bare}");

/* ── W1-06/07 · الحاجبانِ العكسيّان — يُعاد اشتقاقُ السندِ من سجلِّ الشاشات ─ */
$SPACE_DEPT = array(
    'إدارة المالية' => 'المالية والخزينة', 'المدير المالي' => 'المالية والخزينة',
    'ادارة المبيعات' => 'المبيعات والعقود', 'ادارة الموردين' => 'إدارة الموردين',
    'ادارة التشغيل' => 'إدارة التشغيل', 'ادارة الموارد البشرية' => 'الموارد البشرية',
    'إدارة الموقع' => 'إدارة الموقع', 'ادارة الاسطول' => 'إدارة الأسطول',
    'إدارة التمويل' => 'التمويل والملكية', 'ادارة الصيانة' => 'إدارة الصيانة',
    'إدارة المشتريات' => 'إدارة المشتريات التشغيلية', 'إدارة النقل والترحيل' => 'النقل والترحيل',
    'القوى التشغيلية' => 'القوى التشغيلية', 'أمين المستودع' => 'إدارة المخازن',
);
$cycle = array();
$r = $conn->query("SELECT dept_legacy, screen_file FROM repair01_surfaces");
while ($x = $r->fetch_row()) { $cycle[$x[0]][strtolower(basename((string) $x[1]))] = true; }

$ctxNoBasis = 0; $revHadBasis = 0; $ctxN = 0; $revN = 0; $noBridge = 0;
$r = $conn->query("SELECT space_role, route, w1_verdict FROM repair01_ownership WHERE classification='FORBIDDEN'");
while ($x = $r->fetch_assoc()) {
    $sd = isset($SPACE_DEPT[$x['space_role']]) ? $SPACE_DEPT[$x['space_role']] : null;
    if ($sd === null) { $noBridge++; continue; }
    $has = isset($cycle[$sd][strtolower(basename((string) $x['route']))]);
    if ($x['w1_verdict'] === 'CONTEXTUAL_READ_ONLY') { $ctxN++; if (!$has) { $ctxNoBasis++; } }
    if ($x['w1_verdict'] === 'REVOKED_TO_OWNER')     { $revN++; if ($has)  { $revHadBasis++; } }
}
gate('W1-06', 'سياقيٌّ مقروءٌ ⇐ له سندٌ في السجلّ',
     $ctxNoBasis === 0 && $noBridge === 0, "سياقيّ {$ctxN} · بلا سندٍ {$ctxNoBasis} · مساحةٌ بلا جسرٍ {$noBridge}");
gate('W1-07', 'مسحوبٌ للمالك ⇐ لا سندَ له فعلًا',
     $revHadBasis === 0, "مسحوب {$revN} · سُحب وله سندٌ {$revHadBasis}");

/* ── W1-08 · مصدرُ الدورِ مُعلَنٌ، والقرارُ المُشار إليه قائمٌ بمبرَّر ────── */
$noSrc = (int) q1($conn, "SELECT COUNT(*) FROM repair01_surfaces WHERE role_source<>'' AND role_why=''");
$dang  = 0; $decN = 0;
$r = $conn->query("SELECT DISTINCT role_source FROM repair01_surfaces WHERE role_source LIKE 'W1\\_DECISION:%'");
while ($x = $r->fetch_row()) {
    $decN++;
    $id = substr((string) $x[0], strlen('W1_DECISION:'));
    $ok = (int) q1($conn, "SELECT COUNT(*) FROM repair01_w1_decisions
                            WHERE decision_id='" . $conn->real_escape_string($id) . "'
                              AND rationale IS NOT NULL AND rationale<>''");
    if ($ok === 0) { $dang++; }
}
/* ⚠ **حارسُ الخلاء** (RPR-PATCH-06 · 2026-08-25): كان الحاجبُ يخضرُّ على
   مجموعةٍ خاوية — فلو فرغ `role_source` من كلِّ صفٍّ مرَّ بصفرٍ وصفر، وهو
   **أخضرُ كاذب**. وقع فعلًا: إعادةُ استيعابٍ خاطئةٌ محت أثرَ الإسنادِ فصار
   `role_why` فارغًا في الـ٦٦٤ كلِّها والحاجبُ أخضر.
   فصار يشترط **تغطيةً مُعلَنةً**: إمّا أنّ لكلِّ سطحٍ أثرَ إسنادٍ مكتوبًا،
   وإمّا أنّ الخلاءَ **مُعلَنٌ بقرارٍ مسجَّلٍ بمقامِه** — ولا يمرّ صامتًا. */
$whyN   = (int) q1($conn, "SELECT COUNT(*) FROM repair01_surfaces WHERE COALESCE(role_why,'')<>''");
$allN   = (int) q1($conn, "SELECT COUNT(*) FROM repair01_surfaces");
$gapDec = (int) q1($conn, "SELECT COUNT(*) FROM repair01_w1_decisions
                            WHERE decision_id='W1-D-03' AND COALESCE(rationale,'')<>'' AND scope_rows=" . ($allN - $whyN));
$vacuum = ($whyN < $allN && $gapDec === 0);
gate('W1-08', 'مصدرُ الدورِ مُعلَنٌ وقرارُه قائم — ولا خلاءَ صامت',
     $noSrc === 0 && $dang === 0 && !$vacuum,
     "بلا مبرَّرٍ {$noSrc} · قراراتٌ مُشارٌ إليها {$decN} · معلَّقةٌ في الهواء {$dang}"
     . " · أثرُ إسنادٍ مكتوبٌ {$whyN}/{$allN}"
     . ($whyN < $allN ? ($gapDec ? ' · الفجوةُ مُعلَنةٌ في W1-D-03 ✔' : ' · **خلاءٌ غيرُ مُعلَن**') : ''));

/* ── W1-09 · الشقُّ الماليُّ يحترم قاعدتَه ولا يبتلع المقام ───────────────── */
$fin5 = (int) q1($conn, "SELECT COUNT(*) FROM repair01_surfaces WHERE dept_legacy='المالية والخزينة' AND canonical_code='DEP-05'");
$fin6 = (int) q1($conn, "SELECT COUNT(*) FROM repair01_surfaces WHERE dept_legacy='المالية والخزينة' AND canonical_code='DEP-06'");
$finN = (int) q1($conn, "SELECT COUNT(*) FROM repair01_surfaces WHERE dept_legacy='المالية والخزينة'");
$leak = (int) q1($conn, "SELECT COUNT(*) FROM repair01_surfaces
                          WHERE canonical_code IN ('DEP-05','DEP-06') AND dept_legacy<>'المالية والخزينة'");
gate('W1-09', 'شقُّ المالية: المجموعُ = المقامُ وشقّان حيّان',
     ($fin5 + $fin6) === $finN && $fin5 > 0 && $fin6 > 0 && $leak === 0,
     "DEP-05 {$fin5} + DEP-06 {$fin6} = {$finN} · تسرُّبٌ خارجَ الوحدة {$leak}");

/* ── W1-10 · شقُّ الرئاسة: لا سطحَ نوّابٍ مُدَّعًى مبنيًّا ─────────────────── */
$dvp   = (int) q1($conn, "SELECT COUNT(*) FROM repair01_surfaces WHERE canonical_code='EX-DVP'");
$ceo   = (int) q1($conn, "SELECT COUNT(*) FROM repair01_surfaces WHERE canonical_code='EX-CEO'");
$gaps  = (int) q1($conn, "SELECT COUNT(*) FROM repair01_target_gaps WHERE unit='مكتب الرئيس التنفيذي والنواب'");
gate('W1-10', 'شقُّ الرئاسة: النوّابُ فجوةٌ لا بناء',
     $dvp === 0 && $ceo > 0 && $gaps > 0, "EX-CEO {$ceo} · EX-DVP {$dvp} · فجواتُ المكتبِ {$gaps}");

/* ── W1-11 · السقّاطةُ أربعةَ عشرَ سجلًّا · الثمانيةُ مقيسةٌ لا مُفترَضة ──── */
$rp   = repair01_debt_measure($ROOT, $conn);
$cls  = repair01_debt_classes();
$nulls = 0;
foreach (array_keys($cls) as $k) { if ($rp['counts'][$k] === null) { $nulls++; } }
$BASE = $ROOT . '/docs/update0012/debt_baseline.json';
$bj = is_file($BASE) ? json_decode((string) file_get_contents($BASE), true) : null;
$baseKeys = (is_array($bj) && isset($bj['debts'])) ? array_keys($bj['debts']) : array();
$missing = array_diff(array_keys($cls), $baseKeys);
gate('W1-11', 'ثمانيةُ أصنافِ دَينٍ مقيسةٌ ومُؤسَّسة',
     count($cls) === 8 && $nulls === 0 && count($missing) === 0 && count($baseKeys) >= 14,
     'أصناف ' . count($cls) . " · بلا قياسٍ {$nulls} · خارجَ خطِّ الأساسِ " . count($missing)
     . ' · سجلاتُ الخطِّ ' . count($baseKeys));

/* ── W1-12 · السقّاطةُ موصولةٌ بخطّافِ ما قبل الالتزام (رسوٌّ على المسار) ── */
$hookSrc  = $ROOT . '/scripts/pre-commit.ps1';
$hookLive = $ROOT . '/.git/hooks/pre-commit.ps1';
$srcTxt   = is_file($hookSrc)  ? (string) file_get_contents($hookSrc)  : '';
$liveTxt  = is_file($hookLive) ? (string) file_get_contents($hookLive) : '';
$wired    = strpos($srcTxt, 'tools/u12_debt_ratchet.php') !== false;
$linked   = strpos(str_replace('\\', '/', $liveTxt), 'scripts/pre-commit.ps1') !== false
         || strpos($liveTxt, "scripts\\pre-commit.ps1") !== false;
gate('W1-12', 'السقّاطةُ موصولةٌ بخطّافِ الالتزام',
     $wired && $linked, ($wired ? 'المصدرُ يستدعيها' : 'المصدرُ لا يستدعيها')
     . ' · ' . ($linked ? 'الخطّافُ الحيُّ يشير للمصدر' : 'الخطّافُ الحيُّ منفصل'));

/* ── W1-13 · أساسُ المرحلةِ صفر لم يُمَسّ ────────────────────────────────── */
$dec = (int) q1($conn, "SELECT COUNT(*) FROM repair01_decisions");
$sf  = (int) q1($conn, "SELECT COUNT(*) FROM repair01_source_files");
gate('W1-13', 'مخزنُ W00 لم يُمَسَّ بهذه المرحلة',
     $dec === 108 && $sf === 13 && $srAll === 664 && $fbAll === 265,
     "قرارات {$dec} · مصادر {$sf} · أسطح {$srAll} · محرَّم {$fbAll}");

echo implode("\n", $LINES), "\n";
echo str_repeat('─', 71), "\n";
$tot = $PASS + $FAIL;
echo "W1 gate: {$PASS}/{$tot}  ·  FORBIDDEN مفتوحًا {$fbOpen}  ·  resp_role فارغ {$srNone}"
   . "  ·  canonical_code NULL {$cnNull}  ·  ratchets " . count($cls) . "/8\n";
echo 'الحكم: ' . ($FAIL === 0 ? "خضراء ✔\n" : "ساقطة ✘ ({$FAIL})\n");
exit($FAIL === 0 ? 0 : 1);
