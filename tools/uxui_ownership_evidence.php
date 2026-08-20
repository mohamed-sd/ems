<?php
/**
 * tools/uxui_ownership_evidence.php — شاهدُ المِلكيةِ من مصدرٍ حاكم (ثامنًا-٣)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب: «لكلِّ واحدةٍ: المالكُ الحاليُّ ← المقترَحُ ← الشاهدُ ← مصدرُه ←
 *   القرار. والشاهدُ من مصدرٍ حاكم: الوثيقةُ الوظيفيةُ · **خدمةُ النطاقِ التي
 *   تكتبها** · **الكيانُ الذي تُنشئه** · المستندُ الناتجُ · مالكُ الفعلِ · مالكُ
 *   البيان».
 *
 * ◆ **والمختارُ من الستةِ ما يُقاس آليًّا ويُعاد**: **الجداولُ التي تكتب فيها
 *   الشاشة**. فهو «خدمةُ النطاق» و«الكيانُ الذي تُنشئه» و«المستندُ الناتج»
 *   في شاهدٍ واحدٍ يُستخرَج من المصدرِ نفسِه. **ومَن يكتب الكيانَ يملكه** —
 *   وهي القاعدةُ التي أعطاها المالكُ حرفًا: «من يُنتج الواقعةَ يملك شاشتَها».
 *
 * ◆ **والقراءةُ ليست مِلكية**: `SELECT` من جدولِ غيرِك استهلاكٌ لا إنتاج،
 *   فلا يُحسب شاهدًا. **ولو حُسبت القراءةُ مِلكيةً لملكَ الجميعُ كلَّ شيء.**
 *
 * ◆ وما لا يكتب جدولًا حاسمًا (شاشةُ عرضٍ أو تقرير) يُحكَم بـ`NO_WRITE` —
 *   **ولا يُخمَّن له مالك**، بل يُرفَع بشاهدٍ ثانٍ: المسارُ ومجلّدُه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

/** بادئةُ الجدولِ ⇒ الإدارةُ المالكة — مرتَّبةٌ من الأخصِّ إلى الأعمّ */
$T2DEPT = array(
    'iaf_'        => 'المراجع الداخلي المستقل',
    'gov_'        => 'الحوكمة والالتزام',
    'risk_'       => 'إدارة المخاطر المؤسسية',
    'fin_'        => 'المالية والخزينة',
    'acc_'        => 'المالية والخزينة',
    'tre_'        => 'المالية والخزينة',
    'trs_'        => 'النقل والترحيل',
    'mnt_'        => 'إدارة الصيانة',
    'proc_'       => 'إدارة المشتريات التشغيلية',
    'wh_'         => 'إدارة المخازن',
    'stock_'      => 'إدارة المخازن',
    'ticket'      => 'مركز البلاغات',
    'worker_'     => 'القوى التشغيلية',
    'workforce_'  => 'القوى التشغيلية',
    'payroll_'    => 'الموارد البشرية',
    'attendance'  => 'الموارد البشرية',
    'employee'    => 'الموارد البشرية',
    'equipment'   => 'إدارة الأسطول',
    'unit_'       => 'إدارة التشغيل',
    'movement'    => 'إدارة التشغيل',
    'ops_'        => 'إدارة التشغيل',
    'shift_'      => 'إدارة الموقع',
    'site_'       => 'إدارة الموقع',
    'supplier'    => 'إدارة الموردين',
    'contract'    => 'المبيعات والعقود',
    'client'      => 'المبيعات والعقود',
    'exec_'       => 'مكتب الرئيس التنفيذي والنواب',
);

/** كلُّ جداولِ القاعدةِ — فلا يُحسب شاهدًا اسمٌ لا يقابله جدول */
$TABLES = array();
$r = mysqli_query($conn, "SELECT TABLE_NAME FROM information_schema.TABLES
                           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'");
while ($r && ($x = mysqli_fetch_row($r))) { $TABLES[mb_strtolower($x[0])] = 1; }

function dept_of($table, array $map) {
    $t = mb_strtolower($table);
    foreach ($map as $pre => $d) { if (strpos($t, $pre) === 0) { return $d; } }
    return '';
}

/**
 * مسارُ الكتابةِ لا ملفُّ الشاشةِ وحدَه.
 *
 * ◆ **أولُ قياسٍ أعطى 46 من 46 «بلا كتابة» — وهو مستحيلٌ لا نتيجة.** والسببُ
 *   أن الشاشاتِ **تفوّض الكتابةَ إلى خدماتِ نطاق** (`app/Services/...`) وإلى
 *   مُدرَجاتٍ في `includes/`. فقياسُ ملفِّ الشاشةِ وحدَه يقيس **الواجهةَ لا الفعل**.
 *   **ونتيجةٌ يستحيل أن تكون صحيحةً في كلِّ صفٍّ عيبُ أداةٍ لا كشف.**
 * ◆ فيُجمع مصدرُ الشاشةِ مع كلِّ ما تُدرجه وما تُنادي من خدماتٍ — بعمقٍ اثنَين
 *   يكفيان للخدمةِ وما تُدرجه هي.
 */
function write_path_src($path, $ROOT, $depth = 2, &$seen = null) {
    if ($seen === null) { $seen = array(); }
    $real = realpath($path);
    if ($real === false || isset($seen[$real]) || $depth < 0) { return ''; }
    $seen[$real] = 1;
    $src = (string) @file_get_contents($real);
    if ($src === '') { return ''; }
    $all = $src;
    $dir = dirname($real);

    /* ① المُدرَجاتُ بمسارٍ نصّيّ */
    if (preg_match_all('~(?:require|include)(?:_once)?\s*\(?\s*[\'"]([^\'"]+\.php)[\'"]~i', $src, $m)) {
        foreach ($m[1] as $inc) {
            $cand = (strpos($inc, '/') === 0) ? $ROOT . $inc : $dir . '/' . $inc;
            $all .= "\n" . write_path_src($cand, $ROOT, $depth - 1, $seen);
        }
    }
    /* ② المُدرَجاتُ بـ__DIR__ . '/...' */
    if (preg_match_all('~__DIR__\s*\.\s*[\'"]([^\'"]+\.php)[\'"]~', $src, $m)) {
        foreach ($m[1] as $inc) { $all .= "\n" . write_path_src($dir . '/' . $inc, $ROOT, $depth - 1, $seen); }
    }
    /* ③ خدماتُ النطاقِ المُنادَاةُ بالصنف */
    if (preg_match_all('~\\\\?App\\\\Services\\\\([A-Za-z0-9_\\\\]+)\s*::~', $src, $m)) {
        foreach ($m[1] as $cls) {
            $rel = str_replace('\\', '/', $cls) . '.php';
            $all .= "\n" . write_path_src($ROOT . '/app/Services/' . $rel, $ROOT, $depth - 1, $seen);
        }
    }
    return $all;
}

/** الجداولُ التي **تكتب** فيها الشاشة — INSERT/UPDATE/DELETE/REPLACE */
function written_tables($src, array $known) {
    $out = array();
    $pats = array(
        '~\bINSERT\s+(?:IGNORE\s+)?INTO\s+`?([a-zA-Z0-9_]+)`?~i',
        '~\bREPLACE\s+INTO\s+`?([a-zA-Z0-9_]+)`?~i',
        '~\bUPDATE\s+`?([a-zA-Z0-9_]+)`?\s+SET~i',
        '~\bDELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?~i',
    );
    foreach ($pats as $p) {
        if (preg_match_all($p, $src, $m)) {
            foreach ($m[1] as $t) {
                $t = mb_strtolower($t);
                if (isset($known[$t])) { $out[$t] = isset($out[$t]) ? $out[$t] + 1 : 1; }
            }
        }
    }
    arsort($out);
    return $out;
}

$rows = array();
$r = mysqli_query($conn, "SELECT id, space_ar, route, screen_ar, owner_dept_ar, spaces_count
                            FROM gov_space_appearances
                           WHERE src_ownership='ERROR_SUSPECTED' ORDER BY route");
while ($r && ($x = mysqli_fetch_assoc($r))) { $rows[] = $x; }

/* ══ طبقةُ المنصةِ تُستبعَد — **بالقياسِ لا بقائمةٍ مكتوبةٍ بيدي** ══════════
   ◆ القياسُ الثاني رفع `iaf_findings` و`guard_denials` و`ems_sessions` شاهدَ
     مِلكيةٍ لتسعَ عشرةَ شاشة — وهي جداولُ **تكتب فيها الطبقةُ المشترَكةُ في كلِّ
     شاشة**. **وجدولٌ يكتبه الجميعُ لا يُثبت مِلكيةَ أحد.**
   ◆ فبدل قائمةِ استثناءٍ أؤلّفها (وتتقادم وتُخفي اجتهادي)، تُقاس **نسبةُ
     انتشارِ كلِّ جدولٍ** في مساراتِ الكتابةِ لعيّنةٍ واسعةٍ من الشاشات: ما تجاوز
     ثلثَها **طبقةُ منصةٍ بحكمِ الانتشارِ** ويُستبعَد من الشاهد. */
$SAMPLE = array();
$r = mysqli_query($conn, "SELECT DISTINCT route FROM gov_space_appearances LIMIT 200");
while ($r && ($x = mysqli_fetch_row($r))) { $SAMPLE[] = $x[0]; }
$freq = array(); $sampleN = 0;
foreach ($SAMPLE as $rt) {
    $pth = $ROOT . '/' . $rt;
    if (!is_file($pth)) { continue; }
    $sn = array();
    $w = written_tables(write_path_src($pth, $ROOT, 2, $sn), $TABLES);
    if (!$w) { continue; }
    $sampleN++;
    foreach (array_keys($w) as $t) { $freq[$t] = isset($freq[$t]) ? $freq[$t] + 1 : 1; }
}
$PLATFORM = array();
if ($sampleN > 0) {
    foreach ($freq as $t => $n) { if ($n / $sampleN >= 0.34) { $PLATFORM[$t] = $n; } }
}

/* ══ وسجلاتُ الضبطِ تُستبعَد من شاهدِ **القراءة** بالمعيارِ نفسِه ══════════
   ◆ القياسُ الرابعُ رفع أربعَ عشرةَ شاشةَ ضبطٍ ماليٍّ (`acc_*` · `ctrl_*` ·
     `tre_*`) إلى «الحوكمة» لأنها **تقرأ `gov_*`**. وتلك جداولُ **ضبطٍ يقرؤها
     الجميع** — كالمنصةِ سواءً بسواء. **وسجلُّ ضبطٍ يقرؤه الجميعُ لا يُملِّك أحدًا.**
   ◆ ودورةُ العملِ تقطع بذلك: `tre_sod_matrix` فصلُ مهامِّ الخزينة و
     `ctrl_authority_limits` سقفُ الاعتمادِ المالي — **دورتُها المستنديةُ ماليةٌ
     وإن كان مخزنُ ضبطِها في الحوكمة.** فيُقاس الانتشارُ في القراءةِ كما قيس
     في الكتابة، ويُستبعَد ما تجاوز الثلث. */
$rfreq = array(); $rsN = 0;
foreach ($SAMPLE as $rt) {
    $pth = $ROOT . '/' . $rt;
    if (!is_file($pth)) { continue; }
    $sn = array();
    $s2 = write_path_src($pth, $ROOT, 2, $sn);
    if ($s2 === '') { continue; }
    $seenT = array();
    if (preg_match_all('~\bFROM\s+`?([a-zA-Z0-9_]+)`?~i', $s2, $rm)) {
        foreach ($rm[1] as $t) {
            $t = mb_strtolower($t);
            if (isset($TABLES[$t])) { $seenT[$t] = 1; }
        }
    }
    if (!$seenT) { continue; }
    $rsN++;
    foreach (array_keys($seenT) as $t) { $rfreq[$t] = isset($rfreq[$t]) ? $rfreq[$t] + 1 : 1; }
}
$REGISTRY = array();
if ($rsN > 0) {
    foreach ($rfreq as $t => $n) { if ($n / $rsN >= 0.34) { $REGISTRY[$t] = $n; } }
}
arsort($REGISTRY);
echo "  ◆ سجلاتُ الضبطِ المستبعَدةُ من شاهدِ القراءة (عيّنة {$rsN} · عتبة 34٪): "
   . count($REGISTRY) . " جدولًا\n";
if ($REGISTRY) {
    $sh = array();
    foreach (array_slice($REGISTRY, 0, 6, true) as $t => $n) { $sh[] = $t . ' (' . round($n * 100 / $rsN) . '٪)'; }
    echo "    " . implode(' · ', $sh) . "\n";
}

/* ══ ومصارفُ الأثرِ تُستبعَد أيضًا — وهي قاعدةُ المالكِ نفسُها ══════════════
   ◆ «**من يُنتج الواقعةَ يملك شاشتَها · ومن يستهلك أثرَها يملك مستندَه هو**».
     فشاشةٌ تنشر حدثًا فيهبط أثرُه في دفترِ المالية **مُنتِجةٌ لا مملوكةٌ للمالية**.
   ◆ والقياسُ الثالثُ رفع أربعَ شاشاتٍ إلى «المالية» لهذا السببِ بعينِه
     (`movement_operations` · `warehouse_board` · `ticket_form` · `gov_reports`).
     **وترجيحُ المستهلِكِ على المنتِجِ يقلب القاعدةَ رأسًا على عقب.**
   ◆ فتُقرأ مصارفُ الأثرِ **من الناشرِ والمروحةِ نفسَيهما** لا من قائمةٍ أؤلّفها. */
$SINK = array();
foreach (array('/app/Core/EventPublisher.php', '/app/Services/EffectFanout.php') as $f) {
    if (!is_file($ROOT . $f)) { continue; }
    $sn = array();
    foreach (array_keys(written_tables(write_path_src($ROOT . $f, $ROOT, 1, $sn), $TABLES)) as $t) {
        $SINK[$t] = 1;
    }
}
echo "  ◆ مصارفُ الأثرِ المستبعَدةُ (من الناشرِ والمروحة): " . count($SINK) . " جدولًا — "
   . implode(' · ', array_slice(array_keys($SINK), 0, 5)) . "\n";
arsort($PLATFORM);
echo "  ◆ طبقةُ المنصةِ المستبعَدةُ بالانتشار (عيّنة {$sampleN} شاشة · عتبة 34٪): "
   . count($PLATFORM) . " جدولًا\n";
if ($PLATFORM) {
    $show = array();
    foreach (array_slice($PLATFORM, 0, 6, true) as $t => $n) {
        $show[] = $t . ' (' . round($n * 100 / $sampleN) . '٪)';
    }
    echo "    " . implode(' · ', $show) . "\n";
}
echo "\n";

echo "══ شاهدُ المِلكيةِ للستَّةِ والأربعين ══\n";
echo "  المعيار: **الجداولُ التي تكتب فيها الشاشة** — والقراءةُ لا تُحسب مِلكية.\n\n";

$agree = 0; $differ = 0; $nowrite = 0;
$out = array();
foreach ($rows as $x) {
    $path = $ROOT . '/' . $x['route'];
    $seen = array();
    $src  = is_file($path) ? write_path_src($path, $ROOT, 2, $seen) : '';
    $w    = $src !== '' ? written_tables($src, $TABLES) : array();

    /* طبقةُ المنصةِ ومصارفُ الأثرِ تُطرحان قبلَ الترجيح */
    $wDomain = array();
    foreach ($w as $t => $n) {
        if (isset($PLATFORM[$t]) || isset($SINK[$t])) { continue; }
        $wDomain[$t] = $n;
    }

    $readWitness = false;
    $byDept = array();
    foreach ($wDomain as $t => $n) {
        $d = dept_of($t, $T2DEPT);
        if ($d === '') { continue; }
        $byDept[$d] = isset($byDept[$d]) ? $byDept[$d] + $n : $n;
    }
    arsort($byDept);
    $proposed = $byDept ? key($byDept) : '';
    $topTables = implode(' · ', array_slice(array_keys($wDomain), 0, 3));

    /* ══ شاهدٌ ثانٍ للشاشةِ التي لا تكتب: **مالكُ البيان** ══════════════════
       ◆ الشاهدُ الأولُ (خدمةُ النطاقِ التي تكتب) لا ينطبق على شاشةِ عرضٍ أو
         تقرير — وهي 28 من الستَّةِ والأربعين. والمواصفةُ تُجيز ستةَ شواهدَ
         منها **«مالكُ البيان»**: فتقريرٌ عن دفترِ إدارةٍ **بيانُها هي**.
       ◆ ويُوسَم `REPORT_READS` صراحةً **ولا يُخلط بشاهدِ الكتابة**: القراءةُ
         شاهدٌ أضعفُ، وإخفاءُ ضعفِه تحتَ اسمٍ واحدٍ تزويرُ درجةِ ثقة. */
    if ($proposed === '' && $src !== '') {
        $readT = array();
        if (preg_match_all('~\bFROM\s+`?([a-zA-Z0-9_]+)`?~i', $src, $rm)) {
            foreach ($rm[1] as $t) {
                $t = mb_strtolower($t);
                if (!isset($TABLES[$t]) || isset($PLATFORM[$t]) || isset($SINK[$t])
                    || isset($REGISTRY[$t])) { continue; }
                $readT[$t] = isset($readT[$t]) ? $readT[$t] + 1 : 1;
            }
        }
        $rDept = array();
        foreach ($readT as $t => $n) {
            $d = dept_of($t, $T2DEPT);
            if ($d === '') { continue; }
            $rDept[$d] = isset($rDept[$d]) ? $rDept[$d] + $n : $n;
        }
        arsort($rDept);
        if ($rDept) {
            $proposed = key($rDept);
            $topTables = implode(' · ', array_slice(array_keys($readT), 0, 3));
            $readWitness = true;
        }
    }

    if ($proposed === '') {
        $verdict = 'NO_WRITE'; $nowrite++;
        $ev = $src === '' ? 'الملفُّ غيرُ موجودٍ على القرص' : 'لا كتابةَ ولا قراءةَ في جدولِ نطاقٍ — لا شاهدَ آليّ';
    } elseif (mb_strpos($x['owner_dept_ar'], mb_substr($proposed, 0, 8)) !== false
           || mb_strpos($proposed, mb_substr($x['owner_dept_ar'], 0, 8)) !== false) {
        $verdict = $readWitness ? 'CONFIRMED_BY_READS' : 'CONFIRMED_BY_EVIDENCE';
        $agree++;
        $ev = ($readWitness ? 'مالكُ البيان — تقرأ: ' : 'تكتب في: ') . $topTables;
    } else {
        $verdict = $readWitness ? 'OWNER_DIFFERS_BY_READS' : 'OWNER_DIFFERS';
        $differ++;
        $ev = ($readWitness ? 'مالكُ البيان — تقرأ: ' : 'تكتب في: ') . $topTables;
    }
    $out[] = array($x['id'], $x['route'], $x['space_ar'], $x['owner_dept_ar'], $proposed, $verdict, $ev);
    printf("  %-44s %-14s حالي=%-22s شاهد=%-24s\n",
        mb_substr($x['route'], 0, 44), $verdict,
        mb_substr($x['owner_dept_ar'], 0, 22), mb_substr($proposed !== '' ? $proposed : '—', 0, 24));
    if ($ev !== '') { echo "      ◆ {$ev}\n"; }
}

echo "\n  ── الحصيلة: مالكٌ مؤكَّدٌ بالشاهد={$agree} · شاهدٌ يخالف={$differ} · بلا كتابة={$nowrite}\n";

/* المخرَجُ TSV ليُستهلَك في هجرةِ الحسمِ — ولا يُعاد استنتاجُه مرتَين */
$csv = $ROOT . '/docs/uxui/ownership_evidence_46.tsv';
$fh = fopen($csv, 'w');
foreach ($out as $o) { fwrite($fh, implode("\t", $o) . "\n"); }
fclose($fh);
echo "  المخرج: docs/uxui/ownership_evidence_46.tsv\n";
exit(0);
