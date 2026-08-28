<?php
/**
 * tools/amd01_phase4_impact.php — `AMD-01` المرحلة ٤ · إسقاطُ كلِّ قرارٍ على أثرِه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `MASTER_EXEC` §٤·٥: *«أنشئْ `Decision Impact Register`»*
 *   بأربعةَ عشرَ محورًا · *«⛔ ولا يُعتبر القرارُ مغلقًا إن بقي صحيحًا في سجلٍّ
 *   وخاطئًا في ملفٍّ أو شاشةٍ أو اختبار»*.
 *
 * ◆ **خمسةٌ تُنقل حرفًا** من `repair01_decisions` وهي مملوءةٌ ١٢٥/١٢٥:
 *   `DOCUMENTS` ⇐ `affected_documents` · `SCREENS` ⇐ `affected_screens` ·
 *   `MIGRATION` ⇐ `migration_impact` · `TESTS` ⇐ `evidence` ·
 *   و`STATE_MACHINES`/`APPROVALS` تُقرآن من `affected_rules` + `code_impact`.
 *   ⛔ **ولا يُعاد تأليفُ ما هو منقول.**
 *
 * ◆ **و`DEPARTMENTS` تُشتقّ بدليل**: `domain` نصٌّ حرٌّ، فيُطابق باسمِ الإدارةِ
 *   في `repair01_departments` — **ومن لم يُطابق يُوسَم محجوبًا** لا يُخمَّن.
 *
 * ◆ **والباقي محجوبٌ بسببٍ مقيس** — وهذا لبُّ ما كشفته هذه المرحلة:
 *   `affected_screens` **مفرداتُه حرّةٌ لا معرِّفات**: ١٨٠ مفردةً فريدةً منها
 *   «م08-4» و«ر09-2» و«شيت» و«كل الأسطح»، **وصفرٌ منها يشبه `SCR-nnnn`
 *   وصفرٌ يشبه رمزَ نطاق**. ⇒ لا جسرَ من القرارِ إلى الشاشةِ، فلا إلى حقولِها
 *   ولا حبّتِها ولا مصدرِ حقيقتِها ولا صلاحياتِها ولا أحداثِها.
 *   ⛔ **ولا تُملأ بنصٍّ مؤلَّفٍ ليكتمل عدَدُ الأعمدة** — سجلُّ أثرٍ مخترَعٌ
 *   أسوأُ من سجلٍّ ناقصٍ مُعلَن، لأنَّ الأوّلَ يُغلق القرارَ كذبًا.
 *   ⇒ `Track AMD-01 phase 4 blocked at stage: جسرُ «مفرداتِ الأثرِ» إلى `screen_id``
 *
 * التشغيل:
 *   php tools/amd01_phase4_impact.php [--apply] [--md] [--selftest]
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

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && !$SELF) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة**.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'SELFTEST';

/* ═══ ① قياسُ الجسرِ الغائب — ولا يُدَّعى غيابُه ═════════════════════════ */
$tok = array();
$r = $conn->query("SELECT affected_screens FROM repair01_decisions");
while ($x = $r->fetch_row()) {
    foreach (preg_split('~[\s·,،]+~u', (string) $x[0]) as $t) {
        $t = trim($t); if ($t !== '') { $tok[$t] = 1; }
    }
}
$asScr = 0; $asUnit = 0;
foreach (array_keys($tok) as $t) {
    if (preg_match('~^SCR-\d+$~', $t)) { $asScr++; }
    if (preg_match('~^(DEP-\d{2}|EX-CEO|EX-DVP|WS-MY|IAF)$~', $t)) { $asUnit++; }
}
$BRIDGE_WHY = 'عمودُ `affected_screens` مفرداتٌ حرّةٌ لا معرِّفات: ' . count($tok)
            . ' مفردةً فريدةً · تشبه `SCR-nnnn`: ' . $asScr . ' · تشبه رمزَ نطاق: ' . $asUnit
            . ' ⇒ لا جسرَ من القرارِ إلى الشاشةِ بالمعرِّف';

/* ═══ ② أسماءُ الإداراتِ — جسرٌ قائمٌ يُستعمل ═════════════════════════════ */
$dept = array();
$r = $conn->query("SELECT canonical_code, name_ar FROM repair01_departments");
while ($x = $r->fetch_assoc()) { $dept[$x['name_ar']] = $x['canonical_code']; }
$deptFind = function ($domain) use ($dept) {
    $hit = array();
    foreach ($dept as $name => $code) {
        $bare = trim(str_replace(array('إدارة', 'الإدارة'), '', $name));
        if ($bare !== '' && mb_strpos((string) $domain, $bare) !== false) { $hit[] = $code; }
    }
    return array_values(array_unique($hit));
};

/* ═══ ③ المحاورُ الأربعةَ عشر ════════════════════════════════════════════ */
$AXES = array('DOCUMENTS','DEPARTMENTS','SCREENS','FIELDS','GRAIN','SOURCE_OF_TRUTH',
              'DATA','PERMISSIONS','STATE_MACHINES','APPROVALS','EVENTS',
              'INTEGRATIONS','TESTS','MIGRATION');

$rows = array(); $proj = 0; $need = 0;
$r = $conn->query("SELECT decision_id, domain, affected_documents, affected_screens,
                          affected_rules, migration_impact, code_impact, evidence
                     FROM repair01_decisions ORDER BY decision_id");
while ($d = $r->fetch_assoc()) {
    $codes = $deptFind($d['domain']);
    $map = array(
        'DOCUMENTS'  => array($d['affected_documents'], 'repair01_decisions.affected_documents'),
        'SCREENS'    => array($d['affected_screens'],   'repair01_decisions.affected_screens'),
        'MIGRATION'  => array($d['migration_impact'],   'repair01_decisions.migration_impact'),
        'TESTS'      => array($d['evidence'],           'repair01_decisions.evidence'),
        'STATE_MACHINES' => array($d['affected_rules'], 'repair01_decisions.affected_rules'),
        'APPROVALS'  => array($d['code_impact'],        'repair01_decisions.code_impact'),
        'DEPARTMENTS' => array($codes ? implode(' · ', $codes) : '',
                               'repair01_departments.name_ar × repair01_decisions.domain'),
    );
    foreach ($AXES as $ax) {
        if (isset($map[$ax]) && trim((string) $map[$ax][0]) !== '') {
            $rows[] = array($d['decision_id'], $ax, $map[$ax][0], $map[$ax][1], 'PROJECTED', '');
            $proj++;
        } else {
            $why = ($ax === 'DEPARTMENTS')
                 ? 'مجالُ القرارِ «' . $d['domain'] . '» لا يطابق اسمَ إدارةٍ في الجدول — ولا يُخمَّن'
                 : $BRIDGE_WHY;
            $rows[] = array($d['decision_id'], $ax, null, '', 'NEEDS_ADJUDICATION', $why);
            $need++;
        }
    }
}

/* ⛔ **السالبُ يكسر مفردةً فريدة**: محجوبٌ بلا سبب */
if ($SELF) {
    foreach ($rows as $i => $x) {
        if ($x[4] === 'NEEDS_ADJUDICATION') { $rows[$i][5] = ''; break; }
    }
}
$noWhy = 0; $noSrc = 0;
foreach ($rows as $x) {
    if ($x[4] === 'NEEDS_ADJUDICATION' && $x[5] === '') { $noWhy++; }
    if ($x[4] === 'PROJECTED' && ($x[2] === null || $x[3] === '')) { $noSrc++; }
}

$decN = (int) $conn->query("SELECT COUNT(*) FROM repair01_decisions")->fetch_row()[0];
echo "\n═══ `AMD-01` المرحلة ٤ — سجلُّ أثرِ القرار ═══\n";
printf("  اللقطة: %s · قراراتٌ %d × محاورُ %d = **%d خليّة**\n\n", $sid, $decN, count($AXES), count($rows));
$perAxis = array();
foreach ($rows as $x) { $perAxis[$x[1]][$x[4]] = (isset($perAxis[$x[1]][$x[4]]) ? $perAxis[$x[1]][$x[4]] : 0) + 1; }
printf("  %-18s %9s %9s\n", 'المحور', 'مُسقَط', 'محجوب');
echo "  " . str_repeat('─', 40) . "\n";
foreach ($AXES as $ax) {
    printf("  %-18s %9d %9d\n", $ax,
        isset($perAxis[$ax]['PROJECTED']) ? $perAxis[$ax]['PROJECTED'] : 0,
        isset($perAxis[$ax]['NEEDS_ADJUDICATION']) ? $perAxis[$ax]['NEEDS_ADJUDICATION'] : 0);
}
printf("\n  **مُسقَطٌ %d · محجوبٌ %d · محجوبٌ بلا سبب %d · مُسقَطٌ بلا مصدر %d**\n",
       $proj, $need, $noWhy, $noSrc);
echo "\n  ⛔ **والحجبُ سببُه مقيس**: " . $BRIDGE_WHY . "\n";

if ($APPLY && $noWhy === 0 && $noSrc === 0) {
    $conn->query("DELETE FROM repair01_decision_impact");
    $n = 0;
    foreach ($rows as $x) {
        $ok = $conn->query("INSERT INTO repair01_decision_impact
            (decision_id, axis, impact, impact_source, status, blocked_why, snapshot_id, updated_at)
            VALUES ('" . $e($x[0]) . "', '" . $e($x[1]) . "',
                    " . ($x[2] === null ? 'NULL' : "'" . $e($x[2]) . "'") . ",
                    '" . $e($x[3]) . "', '" . $e($x[4]) . "', '" . $e($x[5]) . "',
                    '" . $e($sid) . "', NOW())");
        if (!$ok) { exit("✘ تعذّر {$x[0]}/{$x[1]}: {$conn->error}\n"); }
        $n++;
    }
    printf("\n  ✔ كُتبت **%d** خليّةً\n", $n);
    $back = (int) $conn->query("SELECT COUNT(*) FROM repair01_decision_impact")->fetch_row()[0];
    $full = (int) $conn->query("SELECT COUNT(*) FROM (SELECT decision_id FROM repair01_decision_impact
                                 WHERE status='PROJECTED' GROUP BY 1 HAVING COUNT(*) = 14) t")->fetch_row()[0];
    printf("  ✔ أُعيدت القراءة: %d خليّةً · **قرارٌ أُسقط على المحاورِ الأربعةَ عشرَ كلِّها: %d**\n", $back, $full);
}

echo "\n────────────────────────────────────────────────────────────\n";
$fullDec = 0;
$byDec = array();
foreach ($rows as $x) { if ($x[4] === 'PROJECTED') { $byDec[$x[0]] = (isset($byDec[$x[0]]) ? $byDec[$x[0]] : 0) + 1; } }
foreach ($byDec as $v) { if ($v === 14) { $fullDec++; } }
printf("**قرارٌ حاكمٌ أُسقط على كلِّ ما يتأثّر: %d من %d** — و`AMD-01` §٨ يشترط الكلّ\n", $fullDec, $decN);
echo $fullDec === $decN
    ? "🟢 **المرحلةُ الرابعةُ مستوفيةٌ لقبولِها**\n"
    : "◆ `Track AMD-01 phase 4 blocked at stage: جسرُ مفرداتِ الأثرِ إلى `screen_id`` —\n"
    . "  ⛔ **ولا تُملأ المحاورُ بنصٍّ مؤلَّفٍ ليكتمل العدد**: سجلُّ أثرٍ مخترَعٌ يُغلق\n"
    . "  القرارَ كذبًا، وسجلٌّ ناقصٌ مُعلَنٌ يُبقيه مفتوحًا صادقًا.\n";

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    echo $noWhy >= 1
        ? "🟢 **العدّادُ تحرَّك بمحجوبٍ نُزع سببُه — فالفاحصُ يَحمَرُّ فعلًا**\n"
        : "✘ **العدّادُ لم يتحرّك**\n";
    exit($noWhy >= 1 ? 0 : 1);
}

if ($MD) {
    $o  = "# `AMD-01` المرحلة ٤ — `Decision Impact Register`\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## نصّان بعددَين — والأعلى يحكم\n\n";
    $o .= "`AMD-01` المرحلة ٤ تقول «بأعمدته التسعة» و`MASTER_EXEC` §٤·٥ يعدّ **أربعةَ عشر**.\n";
    $o .= "⛔ **وليس تعارضًا يُرفع**: الأربعةَ عشرَ **تشمل** التسعةَ ولا تناقضها، و`MASTER_EXEC`\n";
    $o .= "رتبتُه ٠ في سلطةِ النصوصِ الإجرائيّة. ⇒ **يُعمل بالأشمل**.\n\n";
    $o .= "## المحاورُ الأربعةَ عشر\n\n| المحور | مُسقَط | محجوب |\n|---|---|---|\n";
    foreach ($AXES as $ax) {
        $o .= '| `' . $ax . '` | ' . (isset($perAxis[$ax]['PROJECTED']) ? $perAxis[$ax]['PROJECTED'] : 0)
            . ' | ' . (isset($perAxis[$ax]['NEEDS_ADJUDICATION']) ? $perAxis[$ax]['NEEDS_ADJUDICATION'] : 0) . " |\n";
    }
    $o .= "\n**مُسقَطٌ " . $proj . " · محجوبٌ " . $need . " من " . count($rows) . " خليّة**\n\n";
    $o .= "## سببُ الحجبِ — مقيسٌ لا مظنون\n\n> " . $BRIDGE_WHY . "\n\n";
    $o .= "⛔ **ولا تُملأ المحاورُ بنصٍّ مؤلَّفٍ ليكتمل العدد**: سجلُّ أثرٍ مخترَعٌ يُغلق القرارَ\n";
    $o .= "كذبًا، وسجلٌّ ناقصٌ مُعلَنٌ يُبقيه مفتوحًا صادقًا.\n\n";
    $o .= "`Track AMD-01 phase 4 blocked at stage: جسرُ مفرداتِ الأثرِ إلى screen_id`\n\n";
    $o .= "**قرارٌ أُسقط على المحاورِ كلِّها: " . $fullDec . " من " . $decN . "**\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/AMD01_PHASE4_IMPACT.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/AMD01_PHASE4_IMPACT.md\n";
}
