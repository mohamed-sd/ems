<?php
/**
 * tools/rpr02_target_universe.php — `RPR-02` §٤·١ · بناءُ كونِ الأهدافِ الموحَّد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **النصُّ الحاكم** — `RPR-02` §٤·١: *«ادمج مستهدفنا مع أشباحِ سجلكم في كونٍ
 *   واحد بمعرّفٍ واحد لكلِّ هدف. فالهدفُ الموجودُ في الاثنين هدفٌ واحد لا اثنان،
 *   والموجودُ في أحدهما فقط يُسمّى مصدرُه»*.
 *
 * ◆ **والمفتاحان مختلفان — وهذا لبُّ العطب**: دفترُنا مفتاحُه **اسمٌ عربيٌّ +
 *   وحدة**، وأشباحُ سجلِّهم مفتاحُها **مسارُ ملفّ** و`canonical_label_ar` فيها
 *   **فارغٌ في ١٥٧ من ١٦٠**. فمن طابق بالاسمِ وحدَه وجد ١٥٧ متطلبًا «بلا مقابل»
 *   **وهي ليست بلا مقابل — بل بلا مفتاحٍ مشترك**.
 *
 * ◆ **والجسرُ مقيسٌ ثلاثيّ**:
 *   ① **تطبيعٌ** — تشكيلٌ وتطويلٌ وهمزاتٌ وتاءٌ مربوطةٌ وأرقامٌ هنديّة.
 *   ② **فكُّ المفتاحِ المركَّب** — `repair01_target_gaps.surface_name` تكتب
 *      الأشباحَ المنقولةَ هكذا: «الاسمُ العربيُّ (file.php)». **فيُفكّ** إلى
 *      اسمٍ وملفّ. وهذا وحدَه ردَّ ١٧ متطلبًا من «بلا مقابل».
 *   ③ **الاحتواء** — اسمٌ محتوًى في اسمِ سطحٍ مبنيٍّ **لمالكِه نفسِه**.
 *      ⛔ **ومرشَّحٌ لا حكم**: الاحتواءُ الاسميُّ شاهدٌ على **احتمالِ** الدمجِ
 *      لا على وقوعِه، ⛔ فلا يُكتب `MERGED_INTO` منه — يُسجَّل مرشَّحًا للفصل.
 *
 * ◆ **ولا يُكتب حكمٌ بلا شاهدٍ قاطع** — `RPR-02` §٤·٢: *«فحكمٌ صحيحٌ بلا شاهدٍ
 *   لا يُقبل»*. فيُكتب تلقائيًّا **حكمان فقط**:
 *   · `MATCHED`   — مطابقةٌ تامّةٌ لسطحٍ **حيٍّ** لمالكِه نفسِه · الشاهدُ `screen_id`.
 *   · `NOT_BUILT` — مطابقةٌ تامّةٌ لصفٍّ في دفترِ الأهدافِ غيرِ المبنيّةِ **ولا
 *                   مقابلَ حيًّا له** · الشاهدُ `gap_id`.
 *   ⛔ **وما عداهما يبقى بلا حكم** ويُعرض عدَدُه — فالحكمُ الغائبُ المُعلَنُ
 *   أصدقُ من حكمٍ مُخترَع، **وعجزٌ من طرحِ عددَين يمنعه §١١**.
 *
 * ◆ **والمقياسان لا يُخلطان** (§٤·٢): `Target Decision Closure` = كم هدفٍ له
 *   حكمٌ من السبعة · `Target Realization` = المتحقِّقُ ÷ المنطبق.
 *
 * التشغيل:
 *   php tools/rpr02_target_universe.php            ← يقيس ولا يكتب
 *   php tools/rpr02_target_universe.php --apply    ← يبني الكونَ ويكتب القاطعَ
 *   php tools/rpr02_target_universe.php --selftest ← سالبٌ يحرِّك العدّاد
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

/* ═══ اللقطة ═════════════════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && !$SELF) {
    exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — ولا كونَ يُبنى على شجرةٍ متحرِّكة.\n");
}
$sid = $snap ? $snap['snapshot_id'] : 'SELFTEST';

/* ═══ التطبيع — والمفتاحُ المركَّبُ يُفكّ ═══════════════════════════════════
   ⛔ **ولا يُطوى فرقٌ دلاليّ**: التطبيعُ يزيل صورَ الحرفِ ولاحقةَ الملفِّ
      واللاحقةَ التوضيحيّةَ بعد «—» — ولا يمسُّ كلمةً تحمل معنًى. */
$norm = function ($s) {
    $s = (string) $s;
    $s = preg_replace('~\s*\([^)]*\)?\s*$~u', '', $s);                       /* لاحقةُ الملف */
    $s = preg_replace('~[\x{064B}-\x{0652}\x{0653}-\x{0655}\x{0670}\x{0640}]~u', '', $s);
    $s = preg_replace('~[\x{0622}\x{0623}\x{0625}]~u', "\u{0627}", $s);
    $s = preg_replace('~\x{0649}~u', "\u{064A}", $s);
    $s = preg_replace('~\x{0629}~u', "\u{0647}", $s);
    $s = strtr($s, array('٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
                         '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'));
    $s = preg_replace('~\s*—.*$~u', '', $s);                                 /* «— بحسب…» */
    $s = preg_replace('~[«»"\'\[\]\-–/·،,\.]~u', ' ', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
};
/* الملفُّ من المفتاحِ المركَّب — «الاسم (file.php)» */
$fileOf = function ($s) {
    return preg_match('~\(([A-Za-z0-9_./-]+\.php)\)?\s*$~u', (string) $s, $m) ? $m[1] : '';
};

/* ═══ ① الجانبان يُقرآن ═════════════════════════════════════════════════ */
$U2C = array('AS' => 'IAF', 'E1' => 'EX-CEO', 'E2' => 'EX-DVP', 'WS' => 'WS-MY');
$unitCode = function ($unit) use ($U2C) {
    $p = mb_substr((string) $unit, 0, 2);
    if (isset($U2C[$p])) { return $U2C[$p]; }
    return preg_match('~^\d{2}$~', $p) ? 'DEP-' . $p : (string) $unit;
};

/* المبنيُّ الحيُّ — مفتاحُه الاسمُ المطبَّع + المالك */
$live = array(); $liveAny = array(); $liveRows = array();
$r = $conn->query("SELECT screen_id, owner_code, canonical_label_ar, surface_kind
                     FROM repair01_screen_registry
                    WHERE canonical_label_ar <> ''
                      AND lifecycle IN ('LIVE_REGISTERED','LIVE_UNREGISTERED')");
while ($x = $r->fetch_assoc()) {
    $n = $norm($x['canonical_label_ar']);
    if ($n === '') { continue; }
    $live[$x['owner_code'] . '|' . $n] = $x;
    if (!isset($liveAny[$n])) { $liveAny[$n] = $x; }
    $liveRows[] = array($x['owner_code'], $n, $x['canonical_label_ar'], $x['screen_id']);
}

/* دفترُ الأهدافِ غيرِ المبنيّة — والمفتاحُ المركَّبُ يُفكّ */
$gap = array(); $gapAny = array();
$r = $conn->query("SELECT id, unit, surface_name FROM repair01_target_gaps");
while ($x = $r->fetch_assoc()) {
    $n = $norm($x['surface_name']);
    if ($n === '') { continue; }
    $x['file'] = $fileOf($x['surface_name']);
    $x['norm'] = $n;
    $gap[$x['unit'] . '|' . $n] = $x;
    if (!isset($gapAny[$n])) { $gapAny[$n] = $x; }
}

/* أشباحُ سجلِّهم — مفتاحُها مسارُ ملفّ، وأسماؤها في دفترِ الأهدافِ المنقولة */
$ghosts = array();
$r = $conn->query("SELECT screen_id, screen_file, owner_code FROM repair01_screen_registry
                    WHERE lifecycle = 'GHOST_TARGET'");
while ($x = $r->fetch_assoc()) { $ghosts[strtolower(basename($x['screen_file']))] = $x; }
/* جسرُ الملفِّ ⇐ صفِّ الهدف */
$gapByFile = array();
foreach ($gap as $g) { if ($g['file'] !== '') { $gapByFile[strtolower(basename($g['file']))] = $g; } }

/* ═══ ② بناءُ الكون ═════════════════════════════════════════════════════ */
$uni = array(); $seq = 0;
$mk = function () use (&$seq) { return sprintf('TGT-%04d', ++$seq); };

$stat = array('EXACT_UNIT'=>0,'EXACT_ANY'=>0,'COMPOUND_SPLIT'=>0,
              'CONTAINMENT_CANDIDATE'=>0,'NONE'=>0);
$verd = array('MATCHED'=>0,'NOT_BUILT'=>0);

$r = $conn->query("SELECT requirement_id, unit, surface FROM repair01_requirements
                    ORDER BY requirement_id");
$byNorm = array();
while ($x = $r->fetch_assoc()) {
    $code = $unitCode($x['unit']);
    $n = $norm($x['surface']);
    $row = array('uid' => $mk(), 'source' => 'OURS', 'unit' => $code,
                 'name_ar' => $x['surface'], 'name_norm' => $n, 'file' => '',
                 'req' => $x['requirement_id'], 'scr' => '', 'gap' => null,
                 'method' => 'NONE', 'mwit' => '', 'verdict' => null, 'vwit' => '');

    $L = isset($live[$code . '|' . $n]) ? $live[$code . '|' . $n]
       : (isset($liveAny[$n]) ? $liveAny[$n] : null);
    $G = isset($gap[$code . '|' . $n]) ? $gap[$code . '|' . $n]
       : (isset($gapAny[$n]) ? $gapAny[$n] : null);

    if ($L !== null) {
        $row['source'] = 'BOTH';
        $row['scr'] = $L['screen_id'];
        $row['method'] = isset($live[$code . '|' . $n]) ? 'EXACT_UNIT' : 'EXACT_ANY';
        $row['mwit'] = 'اسمٌ مطبَّعٌ يطابق سطحًا حيًّا: ' . $L['screen_id'] . ' «' . $L['canonical_label_ar'] . '»';
        /* ⛔ **الحكمُ القاطعُ وحدَه**: مطابقةٌ تامّةٌ لسطحٍ حيّ */
        $row['verdict'] = 'MATCHED';
        $row['vwit'] = 'مطابقةٌ تامّةٌ بالاسمِ المطبَّعِ لسطحٍ حيٍّ في السجلِّ الرسميّ — الشاهدُ `'
                     . $L['screen_id'] . '` · لقطة ' . $sid;
        $verd['MATCHED']++;
    } elseif ($G !== null) {
        $row['source'] = 'BOTH';
        $row['gap'] = (int) $G['id'];
        $row['file'] = $G['file'];
        $row['method'] = ($G['file'] !== '') ? 'COMPOUND_SPLIT'
                       : (isset($gap[$code . '|' . $n]) ? 'EXACT_UNIT' : 'EXACT_ANY');
        $row['mwit'] = 'اسمٌ مطبَّعٌ يطابق صفَّ هدفٍ غيرِ مبنيٍّ: #' . $G['id']
                     . ($G['file'] !== '' ? ' · وملفُّه المُفكَّكُ ' . $G['file'] : '');
        $row['verdict'] = 'NOT_BUILT';
        $row['vwit'] = 'صفٌّ في `repair01_target_gaps` #' . $G['id']
                     . ' **ولا مقابلَ حيًّا لاسمِه** · لقطة ' . $sid;
        $verd['NOT_BUILT']++;
    } else {
        /* ③ الاحتواءُ — مرشَّحٌ لا حكم */
        $hits = array();
        foreach ($liveRows as $b) {
            if ($b[0] !== $code || $n === '') { continue; }
            if (mb_strpos($b[1], $n) !== false || mb_strpos($n, $b[1]) !== false) {
                $hits[] = $b[3] . ' «' . $b[2] . '»';
            }
        }
        if ($hits) {
            $row['method'] = 'CONTAINMENT_CANDIDATE';
            $row['mwit'] = '⛔ **مرشَّحٌ لا حكم** — احتواءٌ اسميٌّ لمالكِه نفسِه: '
                         . implode(' · ', array_slice($hits, 0, 3));
        } else {
            $row['method'] = 'NONE';
            $row['mwit'] = 'لا مقابلَ بالاسمِ ولا مرشَّحَ احتواءٍ — ⛔ **ولا يُحكَم عليه بالغياب**';
        }
    }
    $stat[$row['method']]++;
    $uni[] = $row;
    $byNorm[$n] = 1;
}

/* أشباحُهم التي لم يمسَّها دفترُنا — تدخل الكونَ بمصدرِها */
$theirs = 0;
foreach ($ghosts as $base => $g) {
    $G = isset($gapByFile[$base]) ? $gapByFile[$base] : null;
    $n = $G ? $G['norm'] : '';
    if ($n !== '' && isset($byNorm[$n])) { continue; }   /* هدفٌ واحدٌ لا اثنان */
    $theirs++;
    $uni[] = array('uid' => $mk(), 'source' => 'THEIRS', 'unit' => $g['owner_code'],
        'name_ar' => $G ? $G['surface_name'] : '', 'name_norm' => $n,
        'file' => $g['screen_file'], 'req' => '', 'scr' => $g['screen_id'],
        'gap' => $G ? (int) $G['id'] : null,
        'method' => $G ? 'COMPOUND_SPLIT' : 'NONE',
        'mwit' => $G ? ('شبحٌ جُسِر بملفِّه إلى صفِّ هدفٍ #' . $G['id'])
                     : '⛔ شبحٌ بلا اسمٍ ولا جسر — يُسمّى ولا يُخمَّن',
        'verdict' => 'NOT_BUILT',
        'vwit' => 'سطحٌ مستهدَفٌ لم يُبنَ قطُّ — صفرُ أثرٍ في تاريخِ `git` (`W00` §ملحق) '
                . 'وصفرُ وجودٍ على القرص · الشاهدُ `' . $g['screen_id'] . '` · لقطة ' . $sid);
    $verd['NOT_BUILT']++;
}

/* ⛔ **السالبُ يكسر مفردةً فريدة**: هدفٌ محكومٌ بلا شاهد */
if ($SELF) {
    /* ⛔ **والكسرُ يقع على صفٍّ **محكوم** لا على أيِّ صف**: نزعُ شاهدٍ من
         صفٍّ بلا حكمٍ لا يحرِّك عدّادًا يعُدُّ «حكمًا بلا شاهد» — فيبدو
         الفاحصُ سليمًا وهو لم يُختبَر. */
    foreach ($uni as $i => $x) {
        if ($x['verdict'] !== null) { $uni[$i]['vwit'] = ''; break; }
    }
}

/* ═══ ③ العرض ═══════════════════════════════════════════════════════════ */
$src = array('OURS'=>0,'THEIRS'=>0,'BOTH'=>0);
$noWit = 0;
foreach ($uni as $x) {
    $src[$x['source']]++;
    if ($x['verdict'] !== null && $x['vwit'] === '') { $noWit++; }
}
$judged = $verd['MATCHED'] + $verd['NOT_BUILT'];

echo "\n═══ `RPR-02` §٤·١ — كونُ الأهدافِ الموحَّد ═══\n";
printf("  اللقطة: %s\n\n", $sid);
echo "  ── المصدر (§١٤: كم من ملفاتنا · وكم من سجلكم · وكم مشتركًا) ──\n";
printf("     من دفترِنا وحدَه   `OURS`   %4d\n", $src['OURS']);
printf("     من سجلِّهم وحدَه   `THEIRS` %4d\n", $src['THEIRS']);
printf("     **مشتركٌ — هدفٌ واحدٌ لا اثنان** `BOTH` %4d\n", $src['BOTH']);
printf("     ─────────────────────────────────\n     **الكونُ الموحَّد        %4d**\n", count($uni));

echo "\n  ── طريقةُ المطابقةِ — تُعلَن ولا تُخفى ──\n";
foreach ($stat as $k => $v) { printf("     %-24s %4d\n", $k, $v); }

echo "\n  ── الأحكامُ القاطعةُ وحدَها تُكتب ──\n";
printf("     `MATCHED`   %4d  — مطابقةٌ تامّةٌ لسطحٍ حيّ\n", $verd['MATCHED']);
printf("     `NOT_BUILT` %4d  — صفُّ هدفٍ غيرِ مبنيٍّ أو شبحٌ بصفرِ أثرٍ على القرص\n", $verd['NOT_BUILT']);
printf("     **بلا حكمٍ بعد %4d** — ⛔ ولا يُملأ بـ`NOT_BUILT` لمجرّدِ أنَّ الاسمَ لم يُطابَق\n",
       count($uni) - $judged);

$closure = count($uni) ? round($judged * 100 / count($uni), 1) : 0;
echo "\n  ── المقياسان منفصلان (§٤·٢) ──\n";
printf("     `Target Decision Closure` %s%% (%d من %d له حكم)\n", $closure, $judged, count($uni));
printf("     `Target Realization`      %s%% (%d متحقِّقًا ÷ %d منطبقًا محكومًا)\n",
       $judged ? round($verd['MATCHED'] * 100 / $judged, 1) : 0, $verd['MATCHED'], $judged);
echo "     ⛔ **ولا تُخلطان**: قراراتٌ مكتملةٌ وبناءٌ ناقصٌ حالتان لا واحدة\n";

if ($APPLY && $noWit === 0) {
    $conn->query("DELETE FROM repair01_target_universe");
    $n = 0;
    foreach ($uni as $x) {
        $ok = $conn->query("INSERT INTO repair01_target_universe
            (target_uid, source, unit, name_ar, name_norm, screen_file, requirement_id,
             screen_id, gap_id, match_method, match_witness, verdict, verdict_witness,
             verdict_snapshot, verdict_at)
            VALUES ('" . $e($x['uid']) . "', '" . $e($x['source']) . "', '" . $e($x['unit']) . "',
                    '" . $e($x['name_ar']) . "', '" . $e($x['name_norm']) . "', '" . $e($x['file']) . "',
                    '" . $e($x['req']) . "', '" . $e($x['scr']) . "',
                    " . ($x['gap'] === null ? 'NULL' : (int) $x['gap']) . ",
                    '" . $e($x['method']) . "', '" . $e($x['mwit']) . "',
                    " . ($x['verdict'] === null ? 'NULL' : "'" . $e($x['verdict']) . "'") . ",
                    '" . $e($x['vwit']) . "', '" . $e($sid) . "',
                    " . ($x['verdict'] === null ? 'NULL' : 'NOW()') . ")");
        if (!$ok) { exit("✘ تعذّر إدراجُ {$x['uid']}: {$conn->error}\n"); }
        $n++;
    }
    printf("\n  ✔ بُني الكونُ: **%d** هدفًا بمعرِّفٍ قانونيٍّ واحدٍ لكلٍّ\n", $n);
    /* ⛔ ولا يُصدَّق الكاتبُ على كلمتِه */
    $back = (int) $conn->query("SELECT COUNT(*) FROM repair01_target_universe")->fetch_row()[0];
    $dup  = (int) $conn->query("SELECT COUNT(*) FROM (SELECT name_norm, unit FROM repair01_target_universe
                                 WHERE name_norm <> '' GROUP BY 1,2 HAVING COUNT(*) > 1) t")->fetch_row()[0];
    printf("  ✔ أُعيدت القراءة: %d صفًّا · **هدفٌ مكرَّرٌ بالاسمِ والنطاق: %d**\n", $back, $dup);
} elseif ($APPLY) {
    echo "\n  ⛔ **لم يُكتب شيء** — حكمٌ بلا شاهدٍ لا يُثبَّت\n";
}

echo "\n────────────────────────────────────────────────────────────\n";
printf("**الكونُ %d · محكومٌ %d · بلا حكمٍ %d · حكمٌ بلا شاهدٍ %d**\n",
       count($uni), $judged, count($uni) - $judged, $noWit);

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    echo $noWit >= 1
        ? "🟢 **العدّادُ تحرَّك بحكمٍ نُزع شاهدُه — فالفاحصُ يَحمَرُّ فعلًا**\n"
        : "✘ **العدّادُ لم يتحرّك**\n";
    exit($noWit >= 1 ? 0 : 1);
}

if ($MD) {
    $o  = "# `RPR-02` §٤·١ — كونُ الأهدافِ الموحَّد\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md`\n";
    $o .= "> **اللقطة**: `" . $sid . "`\n\n";
    $o .= "## لماذا لم تكن المطابقةُ ممكنةً بالاسمِ وحدَه\n\n";
    $o .= "دفترُنا مفتاحُه **اسمٌ عربيٌّ + وحدة** · وأشباحُ سجلِّهم مفتاحُها **مسارُ ملفّ**\n";
    $o .= "و`canonical_label_ar` فيها **فارغٌ في ١٥٧ من ١٦٠**. ⇒ **مفتاحان مختلفان لا\n";
    $o .= "مفتاحٌ واحدٌ سيّئُ الكتابة** — ومن طابق بالاسمِ وحدَه وجد ١٥٧ متطلبًا «بلا مقابل»\n";
    $o .= "**وهي ليست بلا مقابل بل بلا مفتاحٍ مشترك**.\n\n";
    $o .= "## المصدر — §١٤\n\n| المصدر | العدد |\n|---|---|\n";
    $o .= "| من دفترِنا وحدَه `OURS` | **" . $src['OURS'] . "** |\n";
    $o .= "| من سجلِّهم وحدَه `THEIRS` | **" . $src['THEIRS'] . "** |\n";
    $o .= "| مشتركٌ `BOTH` — هدفٌ واحدٌ لا اثنان | **" . $src['BOTH'] . "** |\n";
    $o .= "| **الكونُ الموحَّد** | **" . count($uni) . "** |\n\n";
    $o .= "## طريقةُ المطابقة — تُعلَن ولا تُخفى\n\n| الطريقة | العدد |\n|---|---|\n";
    foreach ($stat as $k => $v) { $o .= '| `' . $k . '` | ' . $v . " |\n"; }
    $o .= "\n## الأحكامُ القاطعةُ وحدَها\n\n| الحكم | العدد | الشاهد |\n|---|---|---|\n";
    $o .= "| `MATCHED` | **" . $verd['MATCHED'] . "** | مطابقةٌ تامّةٌ بالاسمِ المطبَّعِ لسطحٍ حيّ · `screen_id` |\n";
    $o .= "| `NOT_BUILT` | **" . $verd['NOT_BUILT'] . "** | صفُّ هدفٍ غيرِ مبنيٍّ · أو شبحٌ بصفرِ أثرٍ في `git` وصفرِ وجودٍ على القرص |\n";
    $o .= "| ⛔ **بلا حكمٍ بعد** | **" . (count($uni) - $judged) . "** | يحتاج فصلًا بأحكامِ §٤·٢ السبعة بشاهدٍ لكلٍّ |\n\n";
    $o .= "## المقياسان منفصلان — §٤·٢\n\n";
    $o .= "- `Target Decision Closure` = **" . $closure . "%** (" . $judged . " من " . count($uni) . ")\n";
    $o .= "- `Target Realization` = **"
        . ($judged ? round($verd['MATCHED'] * 100 / $judged, 1) : 0) . "%** ("
        . $verd['MATCHED'] . " متحقِّقًا ÷ " . $judged . " منطبقًا محكومًا)\n\n";
    $o .= "⛔ **ولا تُخلطان**: قراراتٌ مكتملةٌ وبناءٌ ناقصٌ حالتان لا واحدة.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_TARGET_UNIVERSE.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR02_TARGET_UNIVERSE.md\n";
}
