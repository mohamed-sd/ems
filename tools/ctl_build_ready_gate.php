<?php
/**
 * tools/ctl_build_ready_gate.php — أمرُ الضبطِ §٣+§٦ · بوّابةُ BUILD_READY الخفيفةُ المشروطة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٣: حدٌّ أدنى إلزاميٌّ لكلِّ هدفٍ قبل البناء، ثمَّ
 *   **فقط** الحقولُ التي تنطبق على نوعِه · §٦: `STATE_MACHINE_APPLICABLE`
 *   و`WORKFLOW_APPLICABLE` **بالانطباقِ لا بالعدد** · §٤: إسقاطُ القرارِ
 *   **على الهدفِ المتأثِّرِ لا على البرنامجِ كلِّه**.
 *
 * ◆ **كلُّ حقلٍ يُشتقُّ من مصدرٍ مسمًّى — ⛔ ولا يُؤلَّف**:
 *   · `realization_type` — متطلبُه له `group_name`+`seq` في الملفِّ التصميميّ
 *     ⇒ **`MENU_SCREEN_DESIGNED`** (الملفُّ نفسُه وضعه في قائمة) · متطلبُه
 *     `PROJECTION_REPORT` ⇒ **`PROJECTION_REPORT`** · وما عداهما `UNDECIDED`
 *     — ⛔ **فترجمةُ `NOT_BUILT` إلى «أنشئ شاشةً» بلا حسمِ النوعِ ممنوعةٌ نصًّا**.
 *   · `sm_applicable`/`wf_applicable` — `TRANSACTION` ⇒ `YES`/`YES` ·
 *     `PROJECTION_REPORT` ⇒ `NO`/`NO` **بسببِ عدمِ الانطباقِ مسجَّلًا** ·
 *     وبلا نوعٍ ⇒ `UNDETERMINED`. ⛔ ولا تُؤلَّف آلةٌ لتقريرٍ لرفعِ نسبة.
 *   · `decision_impact` — **بالمسارِ الحاكم** `DECISION→DOMAIN→TARGET`:
 *     قراراتُ الإدارةِ المُسنَدةُ (`repair01_decision_impact.DEPARTMENTS
 *     PROJECTED`) بمحاورَ لم تُسقَط ⇒ أهدافُ إدارتِها `AFFECTED_PENDING`؛
 *     وإدارةٌ بلا قرارٍ مُسنَدٍ ⇒ `NOT_AFFECTED`.
 *     ⛔ **والأربعةُ والأربعون بلا إدارةٍ محسومةٍ لا تُجعل حاجزًا عالميًّا**
 *     — بنصِّ الأمرِ («لا تُجعل القراراتُ غيرُ المسقطةِ حاجزًا عالميًّا») —
 *     وهي بندُ مالكٍ في `OWNER_ACTION_REGISTER` ويُعلَن عددُها هنا.
 *
 * ◆ `BUILD_READY = YES` **فقط** باكتمالِ الحدِّ الأدنى وكلِّ شرطٍ ينطبق على
 *   نوعِه — والقيدُ في المخطَّطِ نفسِه يمنع `YES` بحاجبٍ غيرِ فارغ.
 *
 * التشغيل: php tools/ctl_build_ready_gate.php [--apply] [--md] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

$snap = '';
$r = $conn->query("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $snap = $x[0]; }
if ($APPLY && $snap === '') { exit("⛔ لا نافذةَ قياسٍ مفتوحة\n"); }

/* ═══ ① إسقاطُ القرارِ على مستوى الإدارة ⇒ الهدف ═══════════════════════ */
$affectedUnits = array(); $decUnattributed = 0;
$r = @$conn->query("SELECT d.impact unit_code, COUNT(*) n
                      FROM repair01_decision_impact d
                     WHERE d.axis = 'DEPARTMENTS' AND d.status = 'PROJECTED'
                       AND EXISTS(SELECT 1 FROM repair01_decision_impact p
                                   WHERE p.decision_id = d.decision_id AND p.status = 'NEEDS_ADJUDICATION')
                     GROUP BY d.impact");
while ($r && ($x = $r->fetch_assoc())) { $affectedUnits[trim((string) $x['unit_code'])] = (int) $x['n']; }
$r = @$conn->query("SELECT COUNT(*) FROM repair01_decision_impact WHERE axis='DEPARTMENTS' AND status='NEEDS_ADJUDICATION'");
if ($r && ($x = $r->fetch_row())) { $decUnattributed = (int) $x[0]; }

/* ═══ ② الأهدافُ غيرُ المبنيّةِ بمتطلباتِها ══════════════════════════════ */
$rows = array();
$r = $conn->query("SELECT u.target_uid, u.unit, u.name_ar, u.requirement_id, u.verdict,
                          r.grain, r.source_of_truth, r.requirement_type, r.group_name, r.seq
                     FROM repair01_target_universe u
                     LEFT JOIN repair01_requirements r ON r.requirement_id = u.requirement_id
                    WHERE u.verdict = 'NOT_BUILT'");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }

/**
 * @return array{real:string, sm:string, wf:string, blockers:array, wit:array}
 */
function br_judge(array $x, array $affectedUnits)
{
    $blk = array(); $wit = array();
    $hasReq = trim((string) $x['requirement_id']) !== '';
    /* realization */
    if ($hasReq && trim((string) $x['group_name']) !== '' && (int) $x['seq'] > 0) {
        $real = 'MENU_SCREEN_DESIGNED';
        $wit[] = 'realization من الملفِّ نفسِه: مجموعةٌ «' . $x['group_name'] . '» seq=' . (int) $x['seq'];
    } elseif ($hasReq && (string) $x['requirement_type'] === 'PROJECTION_REPORT') {
        $real = 'PROJECTION_REPORT';
        $wit[] = 'realization من نوعِ المتطلب PROJECTION_REPORT';
    } else {
        $real = 'UNDECIDED';
        $blk[] = 'REALIZATION_TYPE_UNDECIDED';
    }
    /* sm/wf بالانطباق — والنوعُ من مصدرَين مسمَّيَين لا ثالثَ لهما:
       ① `requirement_type` في الدفترِ (‏مُشتقٌّ من `surface_kind` المبنيّ —
          **وغيرُ المبنيِّ لا سطحَ له فيخلو منه بالبناء**، قِيس: ٣٤٤/٣٤٤ فارغ).
       ② **مفرداتُ حبّةِ التصميمِ نفسِها** — نصُّ مؤلِّفِ الملفِّ الحاكمِ لا
          تخمينُنا: «قراءة حية/مشتق/مؤشر/لوحة/تقرير/كشف» قراءةٌ ·
          «سطر/واقعة/قيد/طلب/أمر/حركة/إقرار/تسوية» معاملةٌ بحبّةِ صفّ.
       ⛔ **وتنازعُ المفردتَين لا يُرجَّح**: حبّةٌ تحمل الجنسَين تبقى
          `UNDETERMINED` باسمِها — فالترجيحُ الصامتُ تلفيقُ نوع.
       ⛔ **والاشتقاقُ في البوّابةِ لا يُكتب في الدفتر**: عمودُ الدفترِ حكمُ
          `phase3` بقاعدتِه، وقارئان يكتبان عمودًا واحدًا يتفرّقان. */
    $rt = $hasReq ? (string) $x['requirement_type'] : '';
    if ($rt === '' && $hasReq) {
        $g = (string) $x['grain'];
        $isRead = (bool) preg_match('~قراءة|مشتق|مؤشر|لوحة|تقرير|كشف~u', $g);
        $isRow  = (bool) preg_match('~سطر|واقعة|قيد|طلب|أمر|حركة|إقرار|تسوية|مستخلص~u', $g);
        if ($isRead && !$isRow) { $rt = 'PROJECTION_REPORT'; $wit[] = 'النوعُ من مفرداتِ حبّةِ التصميم («' . mb_substr($g, 0, 40) . '») ⇒ قراءة'; }
        elseif ($isRow && !$isRead) { $rt = 'TRANSACTION'; $wit[] = 'النوعُ من مفرداتِ حبّةِ التصميم («' . mb_substr($g, 0, 40) . '») ⇒ معاملةُ صفّ'; }
        elseif ($isRead && $isRow) { $wit[] = '⛔ حبّةٌ تحمل جنسَي المفرداتِ معًا — لا تُرجَّح'; }
    }
    if ($rt === 'TRANSACTION') { $sm = 'YES'; $wf = 'YES'; $wit[] = 'sm/wf=YES: معاملة'; }
    elseif ($rt === 'PROJECTION_REPORT') { $sm = 'NO'; $wf = 'NO'; $wit[] = 'sm/wf=NO: قراءةٌ لا معاملة — عدمُ الانطباقِ مسجَّلٌ لا مُفترَض'; }
    else { $sm = 'UNDETERMINED'; $wf = 'UNDETERMINED'; $blk[] = 'REQUIREMENT_TYPE_MISSING'; }
    /* والقراءةُ المصمَّمةُ في قائمةٍ realization لها وإن خلا النوعُ الأصليّ */
    if ($real === 'UNDECIDED' && $rt === 'PROJECTION_REPORT') { $real = 'PROJECTION_REPORT';
        $k0 = array_search('REALIZATION_TYPE_UNDECIDED', $blk, true);
        if ($k0 !== false) { array_splice($blk, $k0, 1); }
    }
    /* الحدُّ الأدنى */
    if (!$hasReq) { $blk[] = 'NO_REQUIREMENT_MAPPING'; }
    if ($hasReq && (trim((string) $x['grain']) === '' || (string) $x['grain'] === 'NEEDS_SOURCE')) { $blk[] = 'GRAIN_NEEDS_SOURCE'; }
    if ($hasReq && trim((string) $x['source_of_truth']) === '') { $blk[] = 'SOURCE_OF_TRUTH_MISSING'; }
    /* الكيانُ القانونيُّ — للمعاملاتِ وحدَها؛ ⛔ ولا يُشتقُّ من نصٍّ حرّ */
    if ($rt === 'TRANSACTION') { $blk[] = 'ENTITY_UNNAMED'; }
    /* أثرُ القرار */
    $di = isset($affectedUnits[$x['unit']]) ? 'AFFECTED_PENDING' : 'NOT_AFFECTED';
    if ($di === 'AFFECTED_PENDING') {
        $blk[] = 'DECISION_IMPACT_PENDING(' . $affectedUnits[$x['unit']] . ')';
        $wit[] = 'إدارتُه عليها ' . $affectedUnits[$x['unit']] . ' قرارًا مُسنَدًا بمحاورَ لم تُسقَط';
    } else { $wit[] = 'لا قرارَ مُسنَدًا إلى إدارتِه (بالمسارِ المحسوم)'; }
    return array('real' => $real, 'sm' => $sm, 'wf' => $wf, 'di' => $di, 'blockers' => $blk, 'wit' => $wit);
}

/* ═══ الاختبارُ السالب ═══════════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    /* هدفٌ كاملُ الأركانِ بلا قراراتٍ معلَّقةٍ ولا معاملةٍ ⇒ جاهز */
    $good = array('requirement_id' => 'R1', 'group_name' => 'مجموعة', 'seq' => 3,
                  'requirement_type' => 'PROJECTION_REPORT', 'grain' => 'مؤشر', 'source_of_truth' => 'جدول', 'unit' => 'DEP-99');
    $j = br_judge($good, array());
    if ($j['blockers']) { echo '  X هدفٌ كاملٌ حُجب: ' . implode(',', $j['blockers']) . "\n"; $fail++; }
    if ($j['sm'] !== 'NO') { echo "  X تقريرٌ أُلزم آلةَ حالة\n"; $fail++; }
    /* **الكاسر ①**: معاملةٌ بلا كيانٍ لا تمرّ — وإلّا بُنيت شاشةُ كتابةٍ بلا كيان */
    $tx = $good; $tx['requirement_type'] = 'TRANSACTION';
    $j2 = br_judge($tx, array());
    if (!in_array('ENTITY_UNNAMED', $j2['blockers'], true)) { echo "  X معاملةٌ بلا كيانٍ مرَّت\n"; $fail++; }
    if ($j2['sm'] !== 'YES') { echo "  X معاملةٌ بلا آلةِ حالةٍ منطبقة\n"; $fail++; }
    /* **الكاسر ②**: هدفٌ في إدارةٍ عليها قرارٌ معلَّقٌ يُحجب — والإدارةُ الأخرى تمرّ */
    $j3 = br_judge($good, array('DEP-99' => 2));
    if ($j3['di'] !== 'AFFECTED_PENDING') { echo "  X أثرُ القرارِ لم يصل هدفَه\n"; $fail++; }
    $j4 = br_judge($good, array('DEP-01' => 5));
    if ($j4['di'] !== 'NOT_AFFECTED') { echo "  X قرارُ إدارةٍ حجب إدارةً أخرى — حاجزٌ عالميٌّ ممنوع\n"; $fail++; }
    /* **الكاسر ③**: بلا نوعٍ ولا مفردةٍ دالّةٍ ⇒ UNDECIDED ولا يُترجم إلى شاشة */
    $nt = $good; $nt['requirement_type'] = ''; $nt['group_name'] = ''; $nt['seq'] = 0; $nt['grain'] = 'شيءٌ غامض';
    $j5 = br_judge($nt, array());
    if ($j5['real'] !== 'UNDECIDED' || !in_array('REALIZATION_TYPE_UNDECIDED', $j5['blockers'], true)) {
        echo "  X مجهولُ النوعِ تُرجم شاشةً\n"; $fail++;
    }
    /* **الكاسر ④**: مفرداتُ الحبّةِ تُشتقّ — والجنسان معًا لا يُرجَّحان */
    $gv = $good; $gv['requirement_type'] = ''; $gv['grain'] = 'مؤشر × فترة — قراءة حية';
    $j6 = br_judge($gv, array());
    if ($j6['sm'] !== 'NO') { echo "  X مفردةُ القراءةِ لم تُشتقّ\n"; $fail++; }
    $tv = $good; $tv['requirement_type'] = ''; $tv['grain'] = 'سطر إقرار × موظف';
    $j7 = br_judge($tv, array());
    if ($j7['sm'] !== 'YES') { echo "  X مفردةُ الصفِّ لم تُشتقّ\n"; $fail++; }
    $bv = $good; $bv['requirement_type'] = ''; $bv['grain'] = 'قراءة حية لسطر قيد';
    $j8 = br_judge($bv, array());
    if ($j8['sm'] !== 'UNDETERMINED') { echo "  X الجنسان معًا رُجِّحا — تلفيقُ نوع\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — البوّابةُ تميّز بالانطباقِ وتحجب بالهدفِ لا بالبرنامج\n";
    exit($fail ? 1 : 0);
}

/* ═══ ③ الحكمُ والكتابة ═════════════════════════════════════════════════ */
$stat = array('YES' => 0, 'NO' => 0); $blkStat = array(); $realStat = array(); $diStat = array();
$plan = array();
foreach ($rows as $x) {
    $j = br_judge($x, $affectedUnits);
    $ready = $j['blockers'] ? 'NO' : 'YES';
    $stat[$ready]++;
    $realStat[$j['real']] = (isset($realStat[$j['real']]) ? $realStat[$j['real']] : 0) + 1;
    $diStat[$j['di']] = (isset($diStat[$j['di']]) ? $diStat[$j['di']] : 0) + 1;
    foreach ($j['blockers'] as $b) {
        $b0 = preg_replace('~\(\d+\)~', '', $b);
        $blkStat[$b0] = (isset($blkStat[$b0]) ? $blkStat[$b0] : 0) + 1;
    }
    $plan[] = array('x' => $x, 'j' => $j, 'ready' => $ready);
}

echo "\n═══ أمرُ الضبطِ §٣ — بوّابةُ `BUILD_READY` على " . count($rows) . " هدفًا غيرَ مبنيّ ═══\n";
printf("  اللقطة %s\n\n", $snap !== '' ? $snap : 'DRY');
printf("  `BUILD_READY = YES` **%d** · `NO` **%d**\n\n", $stat['YES'], $stat['NO']);
echo "  ── `realization_type` — ⛔ ولا يُترجم NOT_BUILT إلى «أنشئ شاشةً» بلا حسمِه ──\n";
foreach ($realStat as $k => $v) { printf("     %-24s %4d\n", $k, $v); }
echo "\n  ── أثرُ القرارِ على مستوى الهدف ──\n";
foreach ($diStat as $k => $v) { printf("     %-24s %4d\n", $k, $v); }
printf("     ⚠ و**%d** قرارًا بلا إدارةٍ محسومةٍ — بندُ مالكٍ، ⛔ لا حاجزَ عالميًّا بنصِّ الأمر\n", $decUnattributed);
echo "\n  ── الحواجبُ بأعدادِها ──\n";
arsort($blkStat);
foreach ($blkStat as $k => $v) { printf("     %-32s %4d\n", $k, $v); }

if ($APPLY) {
    $conn->query('START TRANSACTION');
    $conn->query("DELETE FROM repair01_build_ready");
    $n = 0;
    foreach ($plan as $p) {
        $x = $p['x']; $j = $p['j'];
        $sql = "INSERT INTO repair01_build_ready
                (target_uid, requirement_id, owner_domain, canonical_entity, grain, realization_type,
                 source_of_truth, sm_applicable, wf_applicable, decision_impact, build_blocker,
                 build_ready, witness, snapshot_id)
                VALUES ('" . $e($x['target_uid']) . "','" . $e($x['requirement_id']) . "','" . $e($x['unit'])
              . "','','" . $e(mb_substr((string) $x['grain'], 0, 190)) . "','" . $e($j['real']) . "','"
              . $e(mb_substr((string) $x['source_of_truth'], 0, 190)) . "','" . $j['sm'] . "','" . $j['wf']
              . "','" . $j['di'] . "','" . $e(implode(' · ', $j['blockers'])) . "','" . $p['ready'] . "','"
              . $e(mb_substr(implode(' · ', $j['wit']), 0, 590)) . "','" . $e($snap) . "')";
        if (!$conn->query($sql)) { $conn->query('ROLLBACK'); exit("✘ {$conn->error}\n"); }
        $n++;
    }
    $conn->query('COMMIT');
    printf("\n  ✔ كُتب **%d** صفًّا في `repair01_build_ready` · جاهزٌ للبناءِ **%d**\n", $n, $stat['YES']);
}

if ($MD) {
    $o  = "# أمرُ الضبطِ §٣ — بوّابةُ `BUILD_READY`\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `$snap`\n\n";
    $o .= 'المقامُ **' . count($rows) . '** هدفًا `NOT_BUILT` · `BUILD_READY=YES` **' . $stat['YES']
        . '** · `NO` **' . $stat['NO'] . "**\n\n";
    $o .= "## `realization_type` مشتقًّا من مصدرِه\n\n| النوع | العدد | المصدر |\n|---|---:|---|\n";
    $o .= '| `MENU_SCREEN_DESIGNED` | ' . (isset($realStat['MENU_SCREEN_DESIGNED']) ? $realStat['MENU_SCREEN_DESIGNED'] : 0) . " | الملفُّ التصميميُّ وضعه في مجموعةٍ بتسلسل |\n";
    $o .= '| `PROJECTION_REPORT` | ' . (isset($realStat['PROJECTION_REPORT']) ? $realStat['PROJECTION_REPORT'] : 0) . " | نوعُ المتطلبِ في الدفتر |\n";
    $o .= '| `UNDECIDED` | ' . (isset($realStat['UNDECIDED']) ? $realStat['UNDECIDED'] : 0) . " | ⛔ لا يُترجم إلى شاشةٍ قبل الحسم |\n\n";
    $o .= "## الحواجبُ بأعدادِها\n\n| الحاجب | أهداف |\n|---|---:|\n";
    foreach ($blkStat as $k => $v) { $o .= "| `$k` | $v |\n"; }
    $o .= "\n## أثرُ القرارِ — على الهدفِ لا البرنامج\n\n";
    foreach ($diStat as $k => $v) { $o .= "- `$k`: **$v**\n"; }
    $o .= "- ⚠ **$decUnattributed** قرارًا بلا إدارةٍ محسومةٍ — بندُ مالكٍ في `OWNER_ACTION_REGISTER`، ⛔ لا حاجزَ عالميًّا.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/CTL_BUILD_READY_GATE.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/CTL_BUILD_READY_GATE.md\n";
}
