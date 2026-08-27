<?php
/**
 * tools/repair01_w16_gate.php — بوّابةُ المرحلةِ السادسةَ عشرة · الأساسُ المؤسسيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ حاجبٍ يُعيد القياسَ ولا يقرأ ما خزّنَته أداةُ الاشتقاق**: استعلاماتُ
 *   الطبقاتِ الثمانيةِ **تُشغَّل الآن** ويُقارَن ناتجُها بالمسجَّل، وحالةُ
 *   الإصدارِ **تُشتقُّ من جديدٍ** وتُطابَق بما كُتب. **وبوّابةٌ تقرأ `verdict`
 *   المخزَّنَ حشوٌ لا فحص.**
 *
 * ◆ **ولا نسبةَ مجمَّعةً واحدة** (‏البندُ ٦٤): الحاجبُ `W16-08` يمسح وثائقَ
 *   الحملةِ **من القرص** ويسقط على أوّلِ «٪ مكتمل».
 *
 * ◆ **والقبولُ البشريُّ لا يُخضَّر بسكربت** (‏البندُ ٦٣): `W16-09` و`W16-10`
 *   و`W16-11` و`W16-12` تقيس **الأداةَ لا النتيجة** — محطّةٌ تُعلَن ناجحةً بلا
 *   **فاعلٍ حقيقيٍّ في سجلِّ المستخدمين** وزمنٍ ودليل **تُسقط البوّابة**،
 *   ⛔ **والصفرُ الناجحُ لا يُسقطها** لأنَّ العبورَ فعلُ إنسانٍ لا فعلُ أداة.
 *
 * ◆ **والمراجعةُ الثانيةُ تُقاس بقدرتِها لا بنتيجتِها** (‏البندُ ٥٠): `W16-15`
 *   يقرأ **محرّكَ التحدّي نفسَه** ويشترط أن يحمل قاعدةً تُصدر `REDESIGN`،
 *   و`W16-16` يشترط ألّا يقرأ دفترَ موجةٍ — **فمحرّكٌ يقرأ ما بنى الهدفَ صدًى**.
 *
 * التشغيل: php tools/repair01_w16_gate.php
 * الخروج : 0 كلُّ الحواجبِ خضراء · 1 حاجبٌ ساقط
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w16_scan.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w16_one($conn, $sql); };

$pass = 0; $fail = 0; $rows = array();
function gate($id, $title, $ok, $detail)
{
    global $pass, $fail, $rows;
    if ($ok) { $pass++; } else { $fail++; }
    $rows[] = array($id, $title, $ok, $detail);
}

echo "═══════ بوّابةُ المرحلةِ السادسةَ عشرة — REPAIR01 · الأساسُ المؤسسيّ ═══════\n";

/* ── حارسُ الخلاء: مجموعةٌ خاويةٌ تُخضِرُّ على العدم ما لم يُعلَن ────────── */
$emptyDeclared = (int) $one("SELECT COUNT(*) FROM repair01_w16_decisions
                              WHERE decision_id = 'W16-D-01' AND rationale <> ''");
$vac = function ($n) use ($emptyDeclared) { return ((int) $n === 0 && $emptyDeclared === 0); };
$vacTag = $emptyDeclared ? 'خلاءٌ مُعلَنٌ في W16-D-01 ✔' : '**خلاءٌ غيرُ مُعلَن**';

/* ══ W16-01 · كلُّ مرحلةٍ سابقةٍ لها بوّابةٌ وشاهدٌ سالبٌ على القرص ═════ */
$STAGES = array('w0','w1','w2','w3','w4','w5','w6','w7','w8','w9','w10','w11','w12','w13','w135','w14','w15','w16');
$stBad = array();
foreach ($STAGES as $s) {
    $g = is_file($ROOT . "/tools/repair01_{$s}_gate.php");
    $n = is_file($ROOT . "/tools/repair01_{$s}_negative.php") || count(glob($ROOT . "/tests/{$s}_*.php")) > 0;
    if (!$g || !$n) { $stBad[] = $s; }
}
gate('W16-01', 'كلُّ مرحلةٍ ببوّابتِها وشاهدِها السالبِ على القرص',
     count($stBad) === 0,
     'مراحلُ ' . count($STAGES) . ' · ناقصةٌ ' . count($stBad)
     . ($stBad ? ' ⇐ ' . implode('، ', $stBad) : ''));

/* ══ W16-02 · الطبقاتُ الثمانيةُ مسجَّلةٌ بمقياسٍ واسمِ مقام ═══════════ */
$lN    = (int) $one("SELECT COUNT(*) FROM repair01_w16_layers");
$lBad  = (int) $one("SELECT COUNT(*) FROM repair01_w16_layers
                      WHERE TRIM(measure_sql) = '' OR TRIM(den_name) = '' OR clause_ref = ''");
gate('W16-02', 'الطبقاتُ الثمانيةُ مسجَّلةٌ بمقياسٍ واسمِ مقامٍ ومرجعِ بند',
     $lN === 8 && $lBad === 0,
     "طبقاتٌ $lN · ناقصةٌ $lBad · المتوقَّع 8");

/* ══ W16-03 · الثمانيةُ تعبر — بإعادةِ تشغيلِ استعلامِ كلِّ طبقةٍ الآن ═ */
$lRun = 0; $lPassNow = 0; $lDrift = array(); $lLines = array();
$q = $conn->query("SELECT layer_no, layer_key, measure_sql, measured_num, measured_den, verdict
                     FROM repair01_w16_layers ORDER BY layer_no");
while ($q && ($x = $q->fetch_assoc())) {
    $lRun++;
    $r = @$conn->query($x['measure_sql']);
    $num = -1; $den = -1;
    if ($r && ($y = $r->fetch_assoc())) { $num = (int) $y['num']; $den = (int) $y['den']; }
    $nowPass = ($den > 0 && $num === $den);
    if ($nowPass) { $lPassNow++; }
    $lLines[] = $x['layer_key'] . ' ' . $num . '/' . $den;
    /* ⛔ والمسجَّلُ يجب أن يطابق المُعادَ قياسُه — وإلّا فالدفترُ يقول غيرَ الحيّ */
    if ((int) $x['measured_num'] !== $num || (int) $x['measured_den'] !== $den
     || ($nowPass ? 'PASS' : ($den > 0 ? 'FAIL' : 'NOT_MEASURED')) !== $x['verdict']) {
        $lDrift[] = $x['layer_key'];
    }
}
gate('W16-03', 'الطبقاتُ الثمانيةُ تعبر بإعادةِ القياسِ لا بقراءةِ حكمٍ مخزَّن',
     $lRun === 8 && $lPassNow === 8 && count($lDrift) === 0,
     "شُغِّلت $lRun · عابرةٌ الآن $lPassNow/8 · انحرافُ الدفترِ عن الحيّ " . count($lDrift)
     . (count($lDrift) ? ' ⇐ ' . implode('، ', $lDrift) : ''));

/* ══ W16-04 · المحاورُ التسعةُ بقاعدتَي بسطٍ ومقامٍ وحدِّ أداة ═════════ */
$aN   = (int) $one("SELECT COUNT(*) FROM repair01_w16_axes");
$aBad = (int) $one("SELECT COUNT(*) FROM repair01_w16_axes
                     WHERE num_rule = '' OR den_rule = '' OR instrument = ''");
gate('W16-04', 'المحاورُ التسعةُ بقاعدةِ بسطٍ ومقامٍ وحدِّ أداةٍ مُعلَن',
     $aN === 9 && $aBad === 0,
     "محاورُ $aN · ناقصةٌ $aBad · المتوقَّع 9");

/* ══ W16-05 · تسعةُ مقاماتٍ لكلِّ نطاقٍ — لا نطاقَ بثمانية ════════════ */
$DOM = repair01_w16_domains();
$domBad = array();
foreach ($DOM as $d) {
    $n = (int) $one("SELECT COUNT(*) FROM repair01_w16_scorecard WHERE domain_code = '" . $esc($d) . "'");
    if ($n !== 9) { $domBad[] = $d . ':' . $n; }
}
$scN = (int) $one("SELECT COUNT(*) FROM repair01_w16_scorecard");
gate('W16-05', 'تسعةُ مقاماتٍ منشورةٌ لكلِّ نطاقٍ بلا استثناء',
     count($domBad) === 0 && $scN === count($DOM) * 9,
     'نطاقاتٌ ' . count($DOM) . ' · صفوفٌ ' . $scN . '/' . (count($DOM) * 9)
     . ' · ناقصةٌ ' . count($domBad) . (count($domBad) ? ' ⇐ ' . implode('، ', $domBad) : ''));

/* ══ W16-06 · لا صفَّ مقيسٍ بمقامٍ خاوٍ ولا ببسطٍ يتجاوز مقامَه ═══════ */
$scBad = (int) $one("SELECT COUNT(*) FROM repair01_w16_scorecard
                      WHERE verdict = 'MEASURED' AND (den <= 0 OR num < 0 OR num > den OR den_name = '')");
$scMeas = (int) $one("SELECT COUNT(*) FROM repair01_w16_scorecard WHERE verdict = 'MEASURED'");
gate('W16-06', 'كلُّ صفٍّ مقيسٍ ببسطٍ ومقامٍ موجبٍ واسمِ مقام',
     $scBad === 0 && !$vac($scMeas),
     "مقيسٌ $scMeas · مخالفٌ $scBad" . ($vac($scMeas) ? ' ⇐ ' . $vacTag : ''));

/* ══ W16-07 · وغيرُ المقيسِ يُعلَن سببَه ولا يُكتب صفرًا ══════════════ */
$nmN   = (int) $one("SELECT COUNT(*) FROM repair01_w16_scorecard WHERE verdict = 'NOT_MEASURED'");
$nmBad = (int) $one("SELECT COUNT(*) FROM repair01_w16_scorecard
                      WHERE verdict = 'NOT_MEASURED' AND (note = '' OR num <> -1 OR den <> -1)");
gate('W16-07', 'غيرُ المقيسِ مُعلَنٌ بسببِه ⛔ لا مكتوبًا صفرًا',
     $nmBad === 0,
     "غيرُ مقيسٍ $nmN · بلا سببٍ أو مكتوبٌ صفرًا $nmBad");

/* ══ W16-08 · نسبةٌ مجمَّعةٌ منشورة = 0 — يُعاد المسحُ من القرص ═══════ */
$docs = array_merge(glob($ROOT . '/docs/REPAIR01_20260823/*.md'),
                    glob($ROOT . '/docs/REPAIR01_20260823/plan/*.md'),
                    glob($ROOT . '/docs/REPAIR01_20260823/open/*.md'),
                    glob($ROOT . '/docs/REPAIR01_20260823/baseline_v1/*.md'));
$aggr = array();
foreach ($docs as $d) {
    $s = (string) @file_get_contents($d);
    if (preg_match('~[0-9]{1,3}(?:[.,][0-9]+)?\s*%\s*(?:مكتمل|اكتمال|منجز|إنجاز)~u', $s)
     || preg_match('~(?:مكتمل|اكتمال|الإنجاز الكلي)\s*[:：]?\s*[0-9]{1,3}(?:[.,][0-9]+)?\s*%~u', $s)) {
        $aggr[] = basename($d);
    }
}
gate('W16-08', 'نسبةٌ مجمَّعةٌ واحدةٌ منشورةٌ في وثائقِ الحملة = 0',
     count($aggr) === 0 && count($docs) > 0,
     'وثائقُ ' . count($docs) . ' · ناشرةٌ لنسبةٍ مجمَّعةٍ ' . count($aggr)
     . ($aggr ? ' ⇐ ' . implode('، ', $aggr) : ''));

/* ══ W16-09 · محطّةٌ ناجحةٌ بلا فاعلٍ أو زمنٍ أو دليلٍ = 0 ═════════════ */
$uN   = (int) $one("SELECT COUNT(*) FROM repair01_w16_uat");
$uOk  = (int) $one("SELECT COUNT(*) FROM repair01_w16_uat WHERE status = 'PASSED'");
$uBad = (int) $one("SELECT COUNT(*) FROM repair01_w16_uat
                     WHERE status = 'PASSED' AND (actor_user_id <= 0 OR acted_at IS NULL
                                               OR evidence_ref = '' OR actor_name = '')");
gate('W16-09', 'القبولُ البشريُّ لا يُعلَن ناجحًا بلا فاعلٍ وزمنٍ ودليل',
     $uBad === 0 && $uN > 0,
     "محطّاتٌ $uN · عبرت $uOk · مُعلَنةٌ ناجحةً بلا الثلاثةِ $uBad");

/* ══ W16-10 · والفاعلُ مستخدمٌ حقيقيٌّ في سجلِّ المستخدمين لا رقمٌ يُكتب ═ */
$uGhost = (int) $one("SELECT COUNT(*) FROM repair01_w16_uat u
                       LEFT JOIN users s ON s.id = u.actor_user_id
                      WHERE u.status = 'PASSED' AND s.id IS NULL");
gate('W16-10', 'فاعلُ المحطّةِ الناجحةِ مستخدمٌ قائمٌ في سجلِّ المستخدمين',
     $uGhost === 0,
     "ناجحةٌ بفاعلٍ لا وجودَ له $uGhost · والمقامُ محطّاتٌ ناجحةٌ $uOk");

/* ══ W16-11 · المسارُ السالبُ الناجحُ بقيدٍ في سجلِّ المحاولات ════════ */
$negN   = (int) $one("SELECT COUNT(*) FROM repair01_w16_uat WHERE is_negative = 1");
$negBad = (int) $one("SELECT COUNT(*) FROM repair01_w16_uat
                       WHERE is_negative = 1 AND status = 'PASSED' AND attempt_log_ref = ''");
gate('W16-11', 'كلُّ مسارٍ سالبٍ عابرٍ مقيَّدٌ في سجلِّ المحاولات',
     $negBad === 0 && $negN > 0,
     "مساراتٌ سالبةٌ $negN · عابرةٌ بلا قيدٍ $negBad");

/* ══ W16-12 · ثلاثةُ أشخاصٍ مختلفين حيث يلزم فصلُ الواجبات ═══════════ */
$slots = array();
$q = $conn->query("SELECT DISTINCT person_slot FROM repair01_w16_uat WHERE person_slot <> ''");
while ($q && ($x = $q->fetch_row())) { $slots[] = $x[0]; }
$actors = (int) $one("SELECT COUNT(DISTINCT actor_user_id) FROM repair01_w16_uat
                       WHERE status = 'PASSED' AND actor_user_id > 0");
$slotClash = (int) $one("SELECT COUNT(*) FROM (
      SELECT actor_user_id FROM repair01_w16_uat
       WHERE status = 'PASSED' AND actor_user_id > 0 AND person_slot <> ''
       GROUP BY actor_user_id HAVING COUNT(DISTINCT person_slot) > 1) z");
gate('W16-12', 'ثلاثُ خاناتِ أشخاصٍ مسجَّلةٌ ⛔ ولا حسابٌ واحدٌ يشغل خانتَين',
     count($slots) >= 3 && $slotClash === 0,
     'خاناتٌ ' . count($slots) . ' (' . implode('،', $slots) . ') · فاعلون متمايزون عبروا ' . $actors
     . ' · حسابٌ في خانتَين ' . $slotClash);

/* ══ W16-13 · رحلةُ الإثباتِ مسجَّلةٌ بمحطّاتِها ومسارِها السالب ══════ */
$jN = (int) $one("SELECT COUNT(DISTINCT journey_key) FROM repair01_w16_uat");
$jBad = (int) $one("SELECT COUNT(*) FROM repair01_w16_uat
                     WHERE station_ar = '' OR required_role = '' OR person_slot = ''");
gate('W16-13', 'رحلاتُ الإثباتِ مسجَّلةٌ بمحطّةٍ ودورٍ وخانةِ شخصٍ لكلِّ صفّ',
     $jN >= 1 && $jBad === 0 && !$vac($uN),
     "رحلاتٌ $jN · محطّاتٌ $uN · ناقصةُ الحقولِ $jBad" . ($vac($uN) ? ' ⇐ ' . $vacTag : ''));

/* ══ W16-14 · المراجعةُ الثانيةُ مسجَّلةٌ بمقامٍ ومصدرٍ أوّليٍّ لكلِّ قاعدة ═ */
$chN   = (int) $one("SELECT COUNT(*) FROM repair01_w16_challenge");
$chBad = (int) $one("SELECT COUNT(*) FROM repair01_w16_challenge
                      WHERE primary_source = '' OR evidence = '' OR measured = '' OR subject = ''");
$chRed = (int) $one("SELECT COUNT(*) FROM repair01_w16_challenge WHERE severity = 'REDESIGN'");
gate('W16-14', 'كلُّ قاعدةِ تحدٍّ بمقامٍ ومصدرٍ أوّليٍّ وشاهد',
     $chN > 0 && $chBad === 0,
     "قواعدُ $chN · ناقصةٌ $chBad · أحكامُ REDESIGN $chRed");

/* ══ W16-15 · محرّكُ التحدّي قادرٌ على REDESIGN — يُقرأ من كودِه ══════ */
$chSrcPath = $ROOT . '/tools/repair01_w16_challenge.php';
$chSrc = (string) @file_get_contents($chSrcPath);
$redRules = preg_match_all("~ch\s*\(\s*'CH-\d+'.*?'REDESIGN'~s", $chSrc);
gate('W16-15', 'محرّكُ المراجعةِ يستطيع إصدارَ REDESIGN — مقيسًا من كودِه',
     $chSrc !== '' && $redRules > 0,
     "قواعدُ شدّتُها REDESIGN عند الخرق $redRules · والمقامُ ملفُّ المحرّك");

/* ══ W16-16 · ولا يقرأ دفترَ موجةٍ — وإلّا فهو صدًى لا تحدٍّ ══════════ */
$echoHits = array();
if (preg_match_all('~repair01_w\d+_(scope|journey|sod|states|sidebar|decisions|fixes|thresholds)~',
                   $chSrc, $m)) { $echoHits = array_unique($m[0]); }
gate('W16-16', 'محرّكُ المراجعةِ لا يقرأ دفترَ موجةٍ بنى الهدف',
     count($echoHits) === 0 && $chSrc !== '',
     'إشاراتٌ إلى دفاترِ الموجاتِ ' . count($echoHits)
     . ($echoHits ? ' ⇐ ' . implode('، ', array_slice($echoHits, 0, 3)) : ''));

/* ══ W16-17 · كلُّ تبويبٍ من الدَّينِ الموروثِ بحكمٍ مسجَّلٍ وسبب ══════ */
$tabLive = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                        WHERE ownership_verdict = 'TAB_CHILD' AND on_disk = 1");
$tabNo   = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry t
                        LEFT JOIN repair01_w16_tabs w ON w.screen_file = t.screen_file
                                                     AND w.dept_code   = t.owner_code
                       WHERE t.ownership_verdict = 'TAB_CHILD' AND t.on_disk = 1
                         AND w.screen_file IS NULL");
$tabBad  = (int) $one("SELECT COUNT(*) FROM repair01_w16_tabs WHERE why = '' OR judged_verdict = ''");
gate('W16-17', 'كلُّ تبويبٍ موروثٍ بحكمٍ مسجَّلٍ فرادى وسببِه',
     $tabNo === 0 && $tabBad === 0 && !$vac($tabLive),
     "تبويباتٌ حيّةٌ $tabLive · بلا حكمٍ $tabNo · بلا سببٍ $tabBad"
     . ($vac($tabLive) ? ' ⇐ ' . $vacTag : ''));

/* ══ W16-18 · وما رُفع للمالكِ له مُعرِّفُ تأجيلٍ قائمٌ في دفترِه ══════ */
$tabOwner = (int) $one("SELECT COUNT(*) FROM repair01_w16_tabs WHERE disposition = 'GRANT_GAP_TO_OWNER'");
$tabOrph  = (int) $one("SELECT COUNT(*) FROM repair01_w16_tabs w
                         LEFT JOIN repair01_w16_deferred d ON d.deferred_id = w.owner_ref
                        WHERE w.disposition = 'GRANT_GAP_TO_OWNER' AND d.deferred_id IS NULL");
gate('W16-18', 'ما رُفع للمالكِ من التبويباتِ بمُعرِّفِ تأجيلٍ قائم',
     $tabOrph === 0,
     "مرفوعٌ للمالكِ $tabOwner · بمُعرِّفٍ يتيمٍ $tabOrph");

/* ══ W16-19 · صنفُ دَينٍ بلا مقياسٍ من الصورتَين = 0 ═════════════════ */
$dcN   = (int) $one("SELECT COUNT(*) FROM repair01_debt_register");
$dcNo  = (int) $one("SELECT COUNT(*) FROM repair01_debt_register
                      WHERE TRIM(measure_sql) = '' AND TRIM(measure_tool) = ''");
gate('W16-19', 'صنفُ دَينٍ بلا مقياسٍ - استعلامًا أو أداةً - = 0',
     $dcNo === 0 && $dcN > 0,
     "أصنافٌ $dcN · بلا مقياسٍ $dcNo");

/* ══ W16-20 · وكلُّ مقياسٍ يعمل الآن ويعيد عددًا ═════════════════════ */
$dcRun = 0; $dcFail = array();
$q = $conn->query("SELECT class_code, measure_sql, measure_tool FROM repair01_debt_register");
while ($q && ($x = $q->fetch_assoc())) {
    if (trim((string) $x['measure_sql']) !== '') {
        $dcRun++;
        $r = @$conn->query($x['measure_sql']);
        $v = $r ? $r->fetch_row() : null;
        if (!$v || !is_numeric($v[0])) { $dcFail[] = $x['class_code']; }
    } elseif (trim((string) $x['measure_tool']) !== '') {
        $dcRun++;
        $parts = preg_split('~\s+~', trim(preg_replace('~^php\s+~i', '', $x['measure_tool'])));
        $script = $ROOT . '/' . array_shift($parts);
        if (!is_file($script)) { $dcFail[] = $x['class_code'] . ' (أداةٌ غائبة)'; continue; }
        $out = array(); $rc = 0;
        exec('"' . PHP_BINARY . '" "' . $script . '"' . ($parts ? ' ' . implode(' ', $parts) : '') . ' 2>&1', $out, $rc);
        $last = trim((string) end($out));
        if ($rc !== 0 || !is_numeric($last)) { $dcFail[] = $x['class_code']; }
    }
}
gate('W16-20', 'كلُّ مقياسِ صنفٍ يعمل الآن ويعيد عددًا',
     count($dcFail) === 0 && $dcRun > 0,
     "مقاييسُ شُغِّلت $dcRun · ساقطةٌ " . count($dcFail)
     . ($dcFail ? ' ⇐ ' . implode('، ', $dcFail) : ''));

/* ══ W16-21 · سجلُّ الإصدارِ ببصمةِ لقطةٍ والتزامٍ قائمَين ═══════════ */
$blN = (int) $one("SELECT COUNT(*) FROM repair01_w16_baseline");
/* ⛔ **ولقطةٌ قائمةٌ لا تكفي — يجب أن تكون لقطةَ هذا الالتزامِ بعينِه**: وإلّا
   خَتَم الأساسُ نسخةً لا تمثّله، وهو ما وقع فعلًا حين اختِيرت اللقطةُ بالوقتِ
   لا بالالتزام (`W16-F-11`). فالمطابقةُ على `commit_hash` لا على الوجودِ وحدَه. */
$blSnapBad = (int) $one("SELECT COUNT(*) FROM repair01_w16_baseline b
                          LEFT JOIN repair01_freeze_snapshot s ON s.snapshot_id = b.snapshot_id
                         WHERE s.snapshot_id IS NULL OR s.commit_hash <> b.commit_hash");
$blCommit = (string) $one("SELECT commit_hash FROM repair01_w16_baseline ORDER BY issued_at DESC LIMIT 1");
$blKnown = 0;
if ($blCommit !== '') {
    /* ⚠ `^{commit}` يُفسَّر في صَدَفةِ ويندوز رمزَ هروبٍ فيسقط الأمرُ بلا سبب —
       فالنوعُ يُسأل مباشرةً ويُطابَق نصُّه. */
    $o = array(); $rc = 0;
    exec('git -C ' . escapeshellarg($ROOT) . ' cat-file -t ' . escapeshellarg($blCommit) . ' 2>&1', $o, $rc);
    $blKnown = ($rc === 0 && trim((string) implode('', $o)) === 'commit') ? 1 : 0;
}
/* ⚠ **واسمُ الإصدارِ يُطابَق حرفًا**: عمودٌ أضيقُ من قيمتِه **يبتر بتحذيرٍ لا
   بخطأ**، فيحمل السجلُّ اسمًا ليس اسمَ الإصدارِ **ولا يشكو أحد**. وقع فعلًا في
   هذه المرحلة (`VARCHAR(24)` واسمٌ من أحدٍ وثلاثين حرفًا) ⇒ فالمطابقةُ حرفيّة. */
$VER = 'ENTERPRISE-TARGET-BASELINE-v1.0';
$blVerBad = (int) $one("SELECT COUNT(*) FROM repair01_w16_baseline
                         WHERE version <> '" . $esc($VER) . "'");
gate('W16-21', 'سجلُّ الإصدارِ بلقطةٍ والتزامٍ قائمَين واسمٍ غيرِ مبتور',
     $blN > 0 && $blSnapBad === 0 && $blKnown === 1 && $blVerBad === 0,
     "صفوفُ الإصدارِ $blN · لقطةٌ غيرُ مسجَّلةٍ $blSnapBad · التزامٌ معروفٌ $blKnown"
     . " · اسمٌ لا يطابق حرفًا $blVerBad");

/* ══ W16-22 · لا اعتمادَ مالكٍ بلا مرجعِ ختمِه ══════════════════════ */
$blApr = (int) $one("SELECT COUNT(*) FROM repair01_w16_baseline WHERE state = 'OWNER_APPROVED'");
$blAprBad = (int) $one("SELECT COUNT(*) FROM repair01_w16_baseline
                         WHERE state = 'OWNER_APPROVED' AND owner_ref = ''");
gate('W16-22', 'لا OWNER_APPROVED بلا مرجعِ ختمِ المالك',
     $blAprBad === 0,
     "مختومٌ من المالكِ $blApr · بلا مرجعٍ $blAprBad · والاعتمادُ قرارُ مالكٍ لا نتيجةُ أداة");

/* ══ W16-23 · حالةُ الإصدارِ تُشتقُّ من جديدٍ وتُطابَق ما سُجِّل ═════ */
$blRow = null;
$r = $conn->query("SELECT * FROM repair01_w16_baseline ORDER BY issued_at DESC LIMIT 1");
if ($r && $r->num_rows) { $blRow = $r->fetch_assoc(); }
$stateOk = false; $stateWhy = 'لا صفَّ إصدار';
if ($blRow) {
    $redNow = (int) $one("SELECT COUNT(*) FROM repair01_w16_challenge WHERE severity = 'REDESIGN'");
    $expect = ($lPassNow === 8 && $redNow === 0) ? 'ISSUED_AWAITING_OWNER' : 'REDESIGN';
    /* وختمُ المالكِ حالةٌ أعلى تُقبل فوق المُشتقّ - وما دونها لا */
    $stateOk = ($blRow['state'] === $expect)
            || ($blRow['state'] === 'OWNER_APPROVED' && $expect === 'ISSUED_AWAITING_OWNER');
    $stateWhy = 'المُشتقُّ ' . $expect . ' · المسجَّلُ ' . $blRow['state']
              . ' · طبقاتٌ ' . $lPassNow . '/8 · REDESIGN ' . $redNow;
}
gate('W16-23', 'حالةُ الأساسِ مشتقّةٌ من المقيسِ لا مختارةً',
     $stateOk, $stateWhy);

/* ══ W16-24 · المصنَّفاتُ المجمَّدةُ لم تُمَسّ ═══════════════════════ */
$srcN = 0; $srcBad = 0;
$q = $conn->query("SELECT file_name, sha256 FROM repair01_source_files");
while ($q && ($x = $q->fetch_assoc())) {
    $srcN++;
    $p = $ROOT . '/docs/REPAIR01_20260823/' . $x['file_name'];
    if (!is_file($p) || hash_file('sha256', $p) !== $x['sha256']) { $srcBad++; }
}
gate('W16-24', 'المصنَّفاتُ الثلاثةَ عشرَ المجمَّدةُ لم تُمَسّ',
     $srcN === 13 && $srcBad === 0,
     "ملفّاتٌ $srcN · منحرفةٌ $srcBad · وإعادةُ التوليدِ تُكتب إسقاطًا لا فوق المُجمَّد");

/* ══ W16-25 · لا ادّعاءَ قبولٍ نهائيٍّ بلا قبولٍ بشريّ ═══════════════ */
$accClaim = (int) $one("SELECT COUNT(*) FROM repair01_w16_scorecard
                         WHERE axis_key = 'ACCEPTANCE' AND verdict = 'MEASURED' AND num > 0");
$huPassed = (int) $one("SELECT COUNT(*) FROM repair01_w16_uat WHERE status = 'PASSED'");
gate('W16-25', 'لا قبولَ نهائيٌّ يُعَدُّ ما لم يعبر إنسانٌ محطّتَه',
     ($huPassed > 0 || $accClaim === 0),
     "نطاقاتٌ تدَّعي قبولًا نهائيًّا $accClaim · محطّاتٌ عبرها إنسانٌ $huPassed");

/* ══ W16-26 · دفاترُ القرارِ والتأجيلِ والإصلاحِ بحجّةِ كلِّ صفّ ═════ */
$dN = (int) $one("SELECT COUNT(*) FROM repair01_w16_decisions");
$dB = (int) $one("SELECT COUNT(*) FROM repair01_w16_decisions WHERE answer = '' OR rationale = ''");
$fN = (int) $one("SELECT COUNT(*) FROM repair01_w16_fixes");
$fB = (int) $one("SELECT COUNT(*) FROM repair01_w16_fixes WHERE found_by = '' OR evidence = ''");
$pN = (int) $one("SELECT COUNT(*) FROM repair01_w16_deferred");
$pB = (int) $one("SELECT COUNT(*) FROM repair01_w16_deferred
                   WHERE built_anyway = '' OR why_needed = '' OR kind = ''");
gate('W16-26', 'كلُّ قرارٍ بحجّتِه وكلُّ إصلاحٍ بكاشفِه وكلُّ مؤجَّلٍ ببيانِه',
     $dB === 0 && $fB === 0 && $pB === 0 && !$vac($dN) && !$vac($fN) && !$vac($pN),
     "قراراتٌ $dN/$dB · إصلاحاتٌ $fN/$fB · مؤجَّلٌ $pN/$pB");

/* ══ W16-27 · المقاماتُ التسعةُ منشورةٌ في وثيقةٍ على القرص ═════════ */
$sheet = $ROOT . '/docs/REPAIR01_20260823/W16_SCORECARD.md';
$sheetSrc = (string) @file_get_contents($sheet);
$domInSheet = 0;
foreach ($DOM as $d) { if (strpos($sheetSrc, $d) !== false) { $domInSheet++; } }
$axInSheet = 0;
foreach (repair01_w16_axis_defs() as $a) { if (strpos($sheetSrc, $a[0]) !== false) { $axInSheet++; } }
gate('W16-27', 'المقاماتُ التسعةُ منشورةٌ لكلِّ نطاقٍ في وثيقةٍ على القرص',
     $sheetSrc !== '' && $domInSheet === count($DOM) && $axInSheet === 9,
     'نطاقاتٌ في الوثيقة ' . $domInSheet . '/' . count($DOM) . ' · محاورُ ' . $axInSheet . '/9');

/* ═══════════════════════════════════════════════════════════════════════════
   الحكم
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n";
foreach ($rows as $r) {
    printf("  %s %-8s %-54s %s\n", $r[2] ? '✔' : '✘', $r[0], mb_substr($r[1], 0, 54), $r[3]);
}
echo "\n  الطبقاتُ الثمانيةُ بإعادةِ القياس: " . implode(' · ', $lLines) . "\n";
echo "────────────────────────────────────────────────────────────\n";
printf("W16 gate: %d/%d  ·  الثمانيةُ %d/8  ·  مقاماتٌ 9/9 لكلِّ نطاق (%d)  ·  نسبةٌ مجمَّعةٌ منشورة %d  ·  قبولٌ بشريٌّ بسكربتٍ %d\n",
       $pass, $pass + $fail, $lPassNow, count($DOM) - count($domBad), count($aggr), $uBad);
echo $fail === 0 ? "الحكم: خضراء ✔\n" : "الحكم: **حاجبٌ ساقط** ✘\n";
exit($fail === 0 ? 0 : 1);
