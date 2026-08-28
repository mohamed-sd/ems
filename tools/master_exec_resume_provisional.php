<?php
/**
 * tools/master_exec_resume_provisional.php — `RESUME_REGISTER_PROVISIONAL`
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **حكمُه بنصِّه** — `MASTER_EXEC` §٤·٧ و`AMD-01` المرحلة ٦: *«يصدر في أوّلِ
 *   الجلسة من المعلومِ حاليًّا، لئلّا تُفقد نقطةُ التقدّم. **ويُقرأ للرؤيةِ
 *   التنفيذيّةِ ولا يُحتجُّ به**»*. ⛔ **فالمؤقّتُ جسرٌ لا مقرّ**، وكلُّ عملٍ
 *   استُؤنف عليه يُوسَم ويُعاد التحقّقُ منه عند صدورِ المصادَق.
 *
 * ◆ **وواحدٌ وعشرون نطاقًا لا اثنان وعشرون**: `DEP-01`..`DEP-17` · `EX-CEO` ·
 *   `EX-DVP` · `WS-MY` · `IAF`. ⛔ **ولا تُعدُّ `PLATFORM` إدارةً ٢٢** — وسجلُّ
 *   القدراتِ المنصّيّةِ مستقلٌّ يصدر مع المصادَق.
 *
 * ◆ **وما لا يُقاس يُسمّى ولا يُخمَّن** (`AMD-01` §٩). و«آخرُ متطلبٍ مغلقٍ
 *   بالدليل» **غيرُ مقيسٍ بنيويًّا**: `repair01_requirements` **لا عمودَ إغلاقٍ
 *   فيه أصلًا** (‏١١ عمودًا: `requirement_id` · `wave` · `stage_no` · `unit` ·
 *   `dependency` · `seq` · `group_name` · `surface` · `grain` ·
 *   `source_of_truth` · `src_ref`). **وهذا نفسُه مخرَجُ المرحلةِ الثالثة** —
 *   فالحكمُ على كلِّ متطلبٍ يحتاج موضعًا يُكتب فيه.
 *
 * ◆ **والحاجزُ يُنسب بالقياسِ لا بالذاكرة**: تُشغَّل حواجبُ الموجاتِ، ويُنسب
 *   الساقطُ منها إلى النطاقاتِ التي **يذكرها نصُّه هو**؛ وما لا يذكر نطاقًا
 *   **عابرٌ يُعلَن عابرًا** ولا يُوزَّع على النطاقاتِ بالتخمين.
 *
 * التشغيل:
 *   php tools/master_exec_resume_provisional.php [--md] [--selftest]
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

$MD   = in_array('--md', $argv, true);
$SELF = in_array('--selftest', $argv, true);

$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x === null ? null : $x[0];
};
$all = function ($sql) use ($conn) {
    $r = @$conn->query($sql); $o = array();
    if ($r) { while ($x = $r->fetch_assoc()) { $o[] = $x; } }
    return $o;
};

/* ═══ ① اللقطةُ — ولا سجلَّ بلا بصمة ═════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap) {
    exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — والسجلُّ المؤقّتُ رؤيةٌ مقيسةٌ لا ذاكرة.\n");
}

/* ═══ ② النطاقاتُ الواحدُ والعشرون — ثابتةٌ بنصِّ الأمر ══════════════════ */
$SCOPES = array();
for ($i = 1; $i <= 17; $i++) { $SCOPES[] = sprintf('DEP-%02d', $i); }
foreach (array('EX-CEO', 'EX-DVP', 'WS-MY', 'IAF') as $x) { $SCOPES[] = $x; }

/* ⛔ **والمقامُ يُحرَس**: أحدٌ وعشرون لا اثنان وعشرون — فمن أضاف `PLATFORM`
     صفًّا رابعًا وعشرين في الجدولِ لا يُضيفه سطرًا هنا. */
if (count($SCOPES) !== 21) { exit("⛔ **مقامُ النطاقاتِ ليس ٢١** — والسجلُّ لا يصدر\n"); }

/* أسماءُ النطاقاتِ من جدولِها لا من نصٍّ مكرَّر */
$NAME = array();
foreach ($all("SELECT canonical_code, name_ar FROM repair01_departments") as $d) {
    $NAME[$d['canonical_code']] = $d['name_ar'];
}

/* جسرُ وحدةِ دفترِ المتطلباتِ إلى رمزِ النطاق — «01 إدارة …» ⇐ `DEP-01` */
$UNIT2CODE = array('AS' => 'IAF', 'E1' => 'EX-CEO', 'E2' => 'EX-DVP', 'WS' => 'WS-MY');
$reqPer = array();
foreach ($all("SELECT unit, COUNT(*) n, MIN(requirement_id) firstReq
                 FROM repair01_requirements GROUP BY unit") as $u) {
    $p = mb_substr($u['unit'], 0, 2);
    $code = isset($UNIT2CODE[$p]) ? $UNIT2CODE[$p]
          : (preg_match('~^\d{2}$~', $p) ? 'DEP-' . $p : null);
    if ($code === null) { continue; }
    $reqPer[$code] = array('n' => (int) $u['n'], 'unit' => $u['unit']);
}

/* ═══ ③ حواجبُ الموجاتِ — تُشغَّل الآنَ ويُنسب الساقطُ بنصِّه ═════════════
   ⛔ **ولا يُقرأ تقريرٌ مكتوب**: حاجبٌ أخضرُ في ملفٍّ قد يكون أحمرَ على القرص. */
$GATES = array();
foreach (glob($ROOT . '/tools/repair01_w*_gate.php') as $g) {
    $name = basename($g, '.php');
    $o = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($g) . ' 2>&1', $o, $rc);
    /* النطاقاتُ التي يذكرها نصُّ الحاجبِ نفسُه — لا تخمينَ توزيع */
    $src = (string) file_get_contents($g);
    preg_match_all('~DEP-\d{2}|EX-CEO|EX-DVP|WS-MY|IAF~', $src, $m);
    $GATES[$name] = array('rc' => $rc, 'scopes' => array_values(array_unique($m[0])));
}
$redGates = array();
foreach ($GATES as $n => $g) { if ($g['rc'] !== 0) { $redGates[$n] = $g; } }

/* ═══ ④ ما يُقاس لكلِّ نطاق ══════════════════════════════════════════════ */
$surfPer = array(); $gapPer = array(); $firstGap = array(); $ownerless = array();
foreach ($all("SELECT owner_code c, COUNT(*) n FROM repair01_screen_registry
                WHERE owner_code <> '' GROUP BY 1") as $x) { $surfPer[$x['c']] = (int) $x['n']; }
foreach ($all("SELECT unit c, COUNT(*) n, MIN(id) mid FROM repair01_target_gaps GROUP BY 1") as $x) {
    $gapPer[$x['c']] = (int) $x['n'];
    $firstGap[$x['c']] = (string) $one("SELECT surface_name FROM repair01_target_gaps
                                         WHERE id = " . (int) $x['mid']);
}
/* ⛔ **مالكٌ مجهولٌ يكتب** — `RPR-02` §١٢ يشترط صفرًا. ولا يُنسب إلى نطاقٍ
     لأنّه **بلا نطاق**؛ يُعرض عابرًا بمقامِه. */
$ownerlessN = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code = ''");
$ownerlessLive = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                              WHERE owner_code = '' AND lifecycle = 'LIVE_REGISTERED'");

/* ⛔ **العمودُ الغائبُ يُسمّى ولا يُخمَّن** */
$hasClosure = (int) $one("SELECT COUNT(*) FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE()
                             AND TABLE_NAME = 'repair01_requirements'
                             AND COLUMN_NAME IN ('closure_state','status','evidence_status')");
$LASTCLOSED = $hasClosure > 0 ? null : 'غير مقيس — لا عمودَ إغلاقٍ في دفترِ المتطلبات';

echo "\n═══ `RESUME_REGISTER_PROVISIONAL` — للرؤيةِ لا للاحتجاج ═══\n";
printf("  `Snapshot ID` %s · `Commit` %s · `MT` %s\n",
       $snap['snapshot_id'], substr($snap['commit_hash'], 0, 8),
       $snap['measurement_tool_version']);
printf("  حواجبُ الموجاتِ: %d مُشغَّلًا · **الساقطُ %d**: %s\n",
       count($GATES), count($redGates), implode(' · ', array_keys($redGates)));

$rows = array(); $noResume = 0;
foreach ($SCOPES as $code) {
    $reqN  = isset($reqPer[$code]) ? $reqPer[$code]['n'] : 0;
    $surfN = isset($surfPer[$code]) ? $surfPer[$code] : 0;
    $gapN  = isset($gapPer[$code]) ? $gapPer[$code] : 0;
    $first = isset($firstGap[$code]) ? $firstGap[$code] : '—';

    /* الحاجزُ: الساقطُ الذي يذكر هذا النطاقَ بنصِّه */
    $mine = array();
    foreach ($redGates as $n => $g) {
        if (in_array($code, $g['scopes'], true)) { $mine[] = str_replace('repair01_', '', $n); }
    }
    $blocker = $mine ? implode(' · ', $mine) : '—';
    /* الدرجةُ من الستِّ — §٣ */
    $degree  = $mine ? 'BUILD_BLOCKER' : ($gapN > 0 ? 'BUILD_BLOCKER' : 'CONFIG_PENDING');
    $valid   = $mine ? 'نعم — حاجبٌ ساقطٌ مقيسٌ الآن' : ($gapN > 0 ? 'نعم — أهدافٌ لم تُبنَ' : 'لا حاجزَ مقيس');
    $resume  = $gapN > 0
        ? 'أوّلُ هدفٍ غيرِ مبنيٍّ بترتيبِ الدفتر: «' . $first . '»'
        : 'لا هدفَ غيرَ مبنيٍّ — الاستئنافُ بالمراجعةِ العكسيّة';
    $next    = $mine
        ? 'شغِّلْ ' . $mine[0] . ' واقرأْ حاجبَه الساقطَ بعينِه'
        : ($gapN > 0 ? 'احكمْ على «' . $first . '» بواحدٍ من أحكامِ `RPR-02` §٤·٢ السبعة'
                     : 'راجِعْ عكسيًّا مقابلَ ملفِّ الإدارة');
    if ($resume === '') { $noResume++; }
    $rows[] = array($code, isset($NAME[$code]) ? $NAME[$code] : '—',
                    $reqN, $surfN, $gapN, $LASTCLOSED, $first,
                    $blocker, $degree, $valid, $resume, $next);
}

/* ⛔ **السالبُ يكسر مفردةً فريدة** — نطاقٌ بلا نقطةِ استئناف */
if ($SELF) { $rows[3][10] = ''; $noResume = 1; }

printf("\n  %-8s %-30s %5s %5s %5s  %s\n", 'الرمز', 'الاسم', 'مطلب', 'سطح', 'فجوة', 'الحاجز');
echo "  " . str_repeat('─', 96) . "\n";
foreach ($rows as $x) {
    printf("  %-8s %-30s %5d %5d %5d  %s\n", $x[0], mb_substr($x[1], 0, 28), $x[2], $x[3], $x[4], $x[7]);
}

echo "\n  ── عابرٌ لا يُنسب إلى نطاق ──\n";
$cross = array();
foreach ($redGates as $n => $g) {
    if (!$g['scopes']) { $cross[] = str_replace('repair01_', '', $n); }
}
printf("     حواجبُ عابرةٌ ساقطة: %s\n", $cross ? implode(' · ', $cross) : 'لا شيء');
printf("     **أسطحٌ بلا مالكٍ مقرَّر: %d** (منها حيٌّ مسجَّلٌ %d) — و`RPR-02` §١٢ يشترط صفرًا\n",
       $ownerlessN, $ownerlessLive);
printf("     أسطحُ `PLATFORM` في السجل: %d — والأمرُ خطُّ أساسِه ١٢\n",
       (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code='PLATFORM'"));

echo "\n────────────────────────────────────────────────────────────\n";
printf("**نطاقاتٌ %d · بلا نقطةِ استئنافٍ %d**\n", count($rows), $noResume);
echo $noResume === 0
    ? "🟢 **ولا نطاقَ بلا نقطةِ استئناف** — والمقامُ ٢١ لا ٢٢\n"
    : "✘ **نطاقٌ بلا نقطةِ استئناف** — و`AMD-01` §٨ يشترط صفرًا\n";
if ($LASTCLOSED !== null) {
    echo "◆ **و«آخرُ متطلبٍ مغلقٍ بالدليل» غيرُ مقيسٍ بنيويًّا** — لا عمودَ إغلاقٍ\n"
       . "  في `repair01_requirements`. **وهذا مخرَجُ المرحلةِ الثالثة** لا سهوٌ يُخمَّن.\n";
}

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    echo $noResume >= 1
        ? "🟢 **العدّادُ تحرَّك بنطاقٍ نُزعت نقطتُه — فالفاحصُ يَحمَرُّ فعلًا**\n"
        : "✘ **العدّادُ لم يتحرّك**\n";
    exit($noResume >= 1 ? 0 : 1);
}

if ($MD) {
    $o  = "# `RESUME_REGISTER_PROVISIONAL` — واحدٌ وعشرون نطاقًا\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md`\n";
    $o .= "> ⛔ **للرؤيةِ التنفيذيّةِ ولا يُحتجُّ به** — `AMD-01` المرحلة ٦: *«المؤقّتُ جسرٌ لا مقرّ»*.\n";
    $o .= "> **وكلُّ عملٍ استُؤنف عليه يُوسَم ويُعاد التحقّقُ منه** عند صدورِ `RESUME_REGISTER_VALIDATED`.\n\n";
    $o .= "| المفردة | القيمة |\n|---|---|\n";
    $o .= "| `Snapshot ID` | `" . $snap['snapshot_id'] . "` |\n";
    $o .= "| `Exact Commit Hash` | `" . $snap['commit_hash'] . "` |\n";
    $o .= "| `Schema Version` | `" . $snap['schema_version'] . "` |\n";
    $o .= "| `Registry Version` | **" . $snap['registry_rows'] . "** |\n";
    $o .= "| `Measurement Tool Version` | `" . $snap['measurement_tool_version'] . "` |\n";
    $o .= "| `Timestamp` | " . $snap['frozen_at'] . " |\n\n";
    $o .= "## السجل\n\n";
    $o .= "| النطاق | الاسم | مطلب | سطح | هدفٌ لم يُبنَ | آخرُ مغلقٍ بالدليل | أوّلُ مفتوح | الحاجز | درجتُه | أصحيح؟ | نقطةُ الاستئناف | الإجراءُ التالي |\n";
    $o .= "|---|---|---|---|---|---|---|---|---|---|---|---|\n";
    foreach ($rows as $x) {
        $o .= '| `' . $x[0] . '` | ' . $x[1] . ' | ' . $x[2] . ' | ' . $x[3] . ' | ' . $x[4]
            . ' | ' . ($x[5] === null ? '—' : '⛔ ' . $x[5]) . ' | ' . $x[6]
            . ' | ' . ($x[7] === '—' ? '—' : '`' . $x[7] . '`') . ' | `' . $x[8] . '` | ' . $x[9]
            . ' | ' . $x[10] . ' | ' . $x[11] . " |\n";
    }
    $o .= "\n## عابرٌ لا يُنسب إلى نطاق\n\n";
    $o .= "- **حواجبُ عابرةٌ ساقطة**: " . ($cross ? '`' . implode('` · `', $cross) . '`' : 'لا شيء') . "\n";
    $o .= "- **أسطحٌ بلا مالكٍ مقرَّر: " . $ownerlessN . "** (منها حيٌّ مسجَّلٌ " . $ownerlessLive
        . ") — و`RPR-02` §١٢ يشترط صفرًا\n";
    $o .= "- أسطحُ `PLATFORM` في السجل: **"
        . (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code='PLATFORM'")
        . "** — والأمرُ خطُّ أساسِه ١٢\n\n";
    $o .= "**نطاقاتٌ " . count($rows) . " · بلا نقطةِ استئنافٍ " . $noResume . "**\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RESUME_REGISTER_PROVISIONAL.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RESUME_REGISTER_PROVISIONAL.md\n";
}
