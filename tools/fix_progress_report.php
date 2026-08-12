<?php
/**
 * tools/fix_progress_report.php — قياسُ نسبةِ المنجَزِ من مستنداتِ التصحيحِ والتدقيق
 * ═══════════════════════════════════════════════════════════════════════════
 * **التشغيل** (أمرٌ واحدٌ يُعاد كلَّ مرةٍ يُطلب فيها التقرير):
 *     php tools/fix_progress_report.php            # قياسٌ ساكنٌ — ثوانٍ
 *     php tools/fix_progress_report.php --live     # ويُشغّل المسابيرَ فيشترط خُضرتَها
 *     php tools/fix_progress_report.php --md=docs/reports/FIX_PROGRESS.md
 *
 * ── المبدأُ الحاكمُ: **ثلاثةُ مقاماتٍ لا واحد** ─────────────────────────────────
 * خلطُها في نسبةٍ واحدةٍ يُنتج رقمًا لا معنى له، لأنَّ كلَّ مقامٍ يجيب سؤالًا آخر:
 *   ① **حزمُ التصحيح** `docs/fix/*.docx` — أحكامٌ ذريةٌ: **ما يجب أن يُفعَل**.
 *   ② **السجلُّ الجامع** `docs/audit_2026-08/07_*.xlsx` — ملاحظاتٌ: **ما هو معطوب**.
 *   ③ **حواجبُ الإطلاق** `docs/audit_2026-08/05_*.xlsx` — **ما يمنع الإطلاق**.
 * فتُعرض ثلاثُ نسبٍ مستقلّةٍ، ولا تُجمع.
 *
 * ── وقاعدةُ الإغلاقِ صارمةٌ ومُعلَنة ─────────────────────────────────────────
 * أربعُ حالاتٍ لكلِّ بندٍ، وثالثتُها هي التي تصنع الصدقَ:
 *   · **مُغلقٌ بشاهدٍ** — معرِّفُه مذكورٌ في مِسبارٍ أو فاحصٍ، **والمِسبارُ أخضرُ الآن**
 *     (بـ`--live`) أو مُعلَنٌ في قائمةِ الإغلاقِ المشهودة.
 *   · **مُغطًّى** — له دليلٌ في الشيفرةِ (ملفٌّ/دالةٌ يذكرها نصُّه) ولا شاهدَ مخصَّص.
 *   · **غيرُ مقيس** — **لا مِسبارَ يُخصُّه**. لا يُحسَب منجَزًا ولا مفتوحًا: يُعلَن.
 *   · **مفتوح** — قِيس فلم يتحقّق.
 * و«غيرُ مقيس» ليس نقصًا في التقريرِ بل **أصدقُ ما فيه**: إعلانُ 200 بندٍ لا يقيسها
 * أحدٌ أنفعُ من ادّعاءِ إغلاقِها أو فتحِها بلا دليل.
 *
 * ◆ ولا رقمَ مكتوبٌ بيدٍ في هذا الملف: كلُّ عددٍ يُقرأ من مصدرِه وقتَ التشغيل،
 *   فلا يتعفّن التقريرُ بمرورِ الوقتِ كما تعفّنت قوائمُ سابقة.
 * ◆ والمقاماتُ تُقابَل بمصادرِها: إن خالفَ عددُ المستخرَجِ ما تُعلنه الوثيقةُ
 *   **يُعلَن الفارقُ** — فسجلٌّ ناقصٌ يُقرأ اكتمالًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

$LIVE = in_array('--live', $argv, true);
$MD = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $MD = substr($a, 5); } }
$OUTDIR = $ROOT . '/docs/reports';
$REGDIR = $ROOT . '/docs/fix_progress';
foreach (array($OUTDIR, $REGDIR) as $d) { if (!is_dir($d)) { @mkdir($d, 0777, true); } }

$out = array();          // أسطرُ التقرير
$say = function ($s) use (&$out) { $out[] = $s; fwrite(STDOUT, $s . "\n"); };

$say('══ قياسُ نسبةِ المنجَزِ من مستنداتِ التصحيحِ — ' . date('Y-m-d H:i'));
$say($LIVE ? '   (قياسٌ حيٌّ: تُشغَّل المسابيرُ ويُشترط خُضرتُها)' : '   (قياسٌ ساكنٌ · للحيِّ أضِف --live)');

/* ═══════════════════════════════════════════════════════════════════════════
 * ① أدواتُ القراءة
 * ═══════════════════════════════════════════════════════════════════════════ */

/** نصُّ docx مع صفوفِ الجداولِ سليمةً — فالأحكامُ الذريةُ صفوفُ جدول */
function fpr_docx_rows($path)
{
    $z = new ZipArchive();
    if ($z->open($path) !== true) { return null; }
    $xml = (string) $z->getFromName('word/document.xml');
    $z->close();
    if ($xml === '') { return null; }
    $rows = array();
    if (preg_match_all('~<w:tr[ >].*?</w:tr>~s', $xml, $trs)) {
        foreach ($trs[0] as $tr) {
            $cells = array();
            if (preg_match_all('~<w:tc[ >].*?</w:tc>~s', $tr, $tcs)) {
                foreach ($tcs[0] as $tc) {
                    $t = preg_replace('~<w:(tab|br)[^>]*/?>~', ' ', $tc);
                    $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
                    $cells[] = trim(preg_replace('~\s+~u', ' ', $t));
                }
            }
            if ($cells) { $rows[] = $cells; }
        }
    }
    /* والعناوينُ («N متطلبًا ذريًّا») من الفقراتِ — لمقابلةِ المُعلَنِ بالمستخرَج */
    /* ◆ **المُعلَنُ = إعلاناتُ الأقسامِ وحدَها.** أوّلُ عدَّادٍ لي جمع كلَّ ذكرٍ
         لـ«N متطلبًا» فبلغ 1857 لأنه ضمَّ جداولَ الملخَّصِ والترويسةَ — رقمٌ لا
         معنى له. فيُشترط أن تكون الفقرةُ نصَّها **هو** الإعلانُ لا جملةً تحويه. */
    $paras = explode("\n", html_entity_decode(strip_tags(
        preg_replace('~</w:p>~', "\n", $xml)), ENT_QUOTES, 'UTF-8'));
    $decl = 0;
    foreach ($paras as $pp) {
        $pp = trim(preg_replace('~\s+~u', ' ', $pp));
        if (preg_match('~^([0-9]+)\s*متطلبًا\s*ذريًّا$~u', $pp, $dm)) { $decl += (int) $dm[1]; }
    }
    /* وكلُّ معرِّفٍ في الوثيقةِ خامًّا — لمقابلةِ ما تُلقطه صفوفُ الجدول */
    preg_match_all('~FIX[ABC]-\d{4}(?:-\S)?~u', html_entity_decode(strip_tags($xml), ENT_QUOTES, 'UTF-8'), $rawIds);
    return array('rows' => $rows, 'declared' => $decl,
                 'raw_ids' => array_values(array_unique($rawIds[0])));
}

/** كلُّ معرِّفٍ بنمطٍ في مصنَّفِ xlsx — من النصوصِ المشتركةِ والأوراقِ معًا */
function fpr_xlsx_ids($path, $pattern)
{
    $z = new ZipArchive();
    if ($z->open($path) !== true) { return null; }
    $seen = array();
    $sx = (string) $z->getFromName('xl/sharedStrings.xml');
    if ($sx !== '' && preg_match_all($pattern, $sx, $m)) {
        foreach ($m[0] as $x) { $seen[$x] = true; }
    }
    for ($i = 1; $i <= 40; $i++) {
        $s = $z->getFromName("xl/worksheets/sheet{$i}.xml");
        if ($s === false) { continue; }
        if (preg_match_all($pattern, $s, $m2)) {
            foreach ($m2[0] as $x) { $seen[$x] = true; }
        }
    }
    $z->close();
    $k = array_keys($seen);
    sort($k);
    return $k;
}

/** كلُّ معرِّفٍ مذكورٌ في شيفرةٍ أو مِسبارٍ ⇒ معرِّف => [ملفات] */
function fpr_code_mentions($root, $pattern, array $dirs)
{
    $hits = array();
    foreach ($dirs as $d) {
        $abs = $root . '/' . $d;
        if (!is_dir($abs)) { continue; }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) { continue; }
            $ext = strtolower($f->getExtension());
            if (!in_array($ext, array('php', 'md', 'sql', 'tsv', 'json'), true)) { continue; }
            if ($f->getSize() > 6000000) { continue; }
            $body = (string) file_get_contents($f->getPathname());
            if (!preg_match_all($pattern, $body, $m)) { continue; }
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            foreach (array_unique($m[0]) as $id) {
                if (!isset($hits[$id])) { $hits[$id] = array(); }
                $hits[$id][$rel] = true;
            }
        }
    }
    return $hits;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * ② المقامُ الأولُ — حزمُ التصحيحِ الثلاث (docs/fix)
 * ═══════════════════════════════════════════════════════════════════════════ */
$say('');
$say('── ① حزمُ التصحيح · docs/fix — أحكامٌ ذريةٌ: ما يجب أن يُفعَل');

$FIXDOCS = array(
    'FIX-01 — التصحيح البنيوي.docx'                  => 'FIXA',
    'FIX-02 — تصحيح القشرة والنظام التصميمي.docx'    => 'FIXB',
    'FIX-03 — تصحيح المالية والمراجعة.docx'          => 'FIXC',
);
$fixRules = array();      // id => array(doc, req, test)
$fixDeclared = 0;
foreach ($FIXDOCS as $file => $pfx) {
    $p = $ROOT . '/docs/fix/' . $file;
    if (!is_file($p)) { $say('   ✘ مستندٌ غائبٌ: ' . $file); continue; }
    $d = fpr_docx_rows($p);
    if ($d === null) { $say('   ✘ تعذّر فتحُ: ' . $file); continue; }
    $n = 0;
    foreach ($d['rows'] as $r) {
        if (!isset($r[0]) || !preg_match('~^(' . $pfx . '-\d{4}(?:-\S)?)$~u', trim($r[0]), $m)) { continue; }
        $id = $m[1];
        if (isset($fixRules[$id])) { continue; }
        $fixRules[$id] = array('doc' => $pfx,
                               'req' => isset($r[1]) ? $r[1] : '',
                               'test' => isset($r[2]) ? $r[2] : '');
        $n++;
    }
    /* ◆ **ما لا تُلقطه صفوفُ الجدولِ يُضاف من المسحِ الخامّ** — خليةُ معرِّفٍ
         بمحتوًى زائدٍ أو صفٌّ منقسمٌ يُسقط الحكمَ، والفارقُ هو الذي يدلُّ عليه. */
    $addedRaw = 0;
    foreach ($d['raw_ids'] as $rid) {
        if (strpos($rid, $pfx . '-') !== 0 || isset($fixRules[$rid])) { continue; }
        $fixRules[$rid] = array('doc' => $pfx, 'req' => '(لم تُلقطه صفوفُ الجدولِ — أُضيف من المسحِ الخامّ)', 'test' => '');
        $addedRaw++; $n++;
    }
    $fixDeclared += (int) $d['declared'];
    printf("   %-46s مستخرَجٌ %3d%s\n", mb_substr($file, 0, 44), $n,
           $addedRaw ? "  (منها {$addedRaw} من المسحِ الخامّ)" : '');
}
$fixTotal = count($fixRules);
$say('   ══ أحكامٌ ذريةٌ مستخرَجةٌ: ' . $fixTotal
   . ' · ومُعلَنٌ في عناوينِ الوثائق: ' . $fixDeclared
   . ($fixTotal === $fixDeclared ? '  ✔ متطابقان' : '  ⚠ فارقٌ يُعلَن ولا يُسكَت عنه'));

/* ═══════════════════════════════════════════════════════════════════════════
 * ③ المقامُ الثاني — السجلُّ الجامع (07) · والثالثُ حواجبُ الإطلاق (05)
 * ═══════════════════════════════════════════════════════════════════════════ */
$say('');
$say('── ② السجلُّ الجامع · docs/audit_2026-08/07 — ملاحظاتٌ: ما هو معطوب');
$masterXlsx = $ROOT . '/docs/audit_2026-08/07_Master_Findings_Register.xlsx';
$injIds = is_file($masterXlsx) ? fpr_xlsx_ids($masterXlsx, '~INJ-\d{4}~') : null;
if ($injIds === null) { $say('   ✘ المصنَّفُ غائبٌ أو تعذّر فتحُه'); $injIds = array(); }
$say('   ملاحظاتٌ فريدةٌ في المصنَّف: ' . count($injIds));

/* والـTSV المشتقُّ — يُقابَل بالمصنَّفِ فلا يتعفّن */
$tsvPath = $ROOT . '/docs/fix_2026-08/master_register.tsv';
$tsvIds = array();
$tsvRow = array();
if (is_file($tsvPath)) {
    foreach (file($tsvPath) as $l) {
        $c = explode("\t", rtrim($l, "\r\n"));
        if (!isset($c[0]) || !preg_match('~^INJ-\d{4}$~', trim($c[0]))) { continue; }
        $tsvIds[trim($c[0])] = true;
        $tsvRow[trim($c[0])] = array('sev' => isset($c[10]) ? trim($c[10]) : '',
                                     'blocks' => isset($c[18]) ? trim($c[18]) : '');
    }
}
$tsvIds = array_keys($tsvIds);
$onlyX = array_values(array_diff($injIds, $tsvIds));
$onlyT = array_values(array_diff($tsvIds, $injIds));
$say('   الـTSV المشتقُّ: ' . count($tsvIds)
   . ' · في المصنَّفِ وحدَه ' . count($onlyX) . ' · في الـTSV وحدَه ' . count($onlyT)
   . ((count($onlyX) + count($onlyT)) === 0 ? '  ✔ صورةٌ صادقة' : '  ⚠ انحرافٌ يُعلَن'));

$say('');
$say('── ③ حواجبُ الإطلاق · docs/audit_2026-08/05 — ما يمنع الإطلاق');
$blockers = 0;
foreach ($tsvRow as $id => $r) { if (mb_strpos($r['blocks'], 'نعم') !== false) { $blockers++; } }
$say('   بنودٌ موسومةٌ «يمنع الإطلاق» في السجل: ' . $blockers);

/* ═══════════════════════════════════════════════════════════════════════════
 * ④ الدليلُ — أيُّ معرِّفٍ يذكره مِسبارٌ أو فاحصٌ أو شيفرة
 * ═══════════════════════════════════════════════════════════════════════════ */
$say('');
$say('── ④ الدليلُ: مِسبارٌ يذكر المعرِّفَ (شاهدٌ) · أو شيفرةٌ تذكره (تغطية)');

$WITNESS_DIRS = array('tests', 'tools');
$COVER_DIRS   = array('app', 'includes', 'database/migrations', 'docs/owf01');

$injWitness = fpr_code_mentions($ROOT, '~INJ-\d{4}~', $WITNESS_DIRS);
$injCover   = fpr_code_mentions($ROOT, '~INJ-\d{4}~', $COVER_DIRS);
$fixWitness = fpr_code_mentions($ROOT, '~FIX[ABC]-\d{4}(?:-\S)?~u', $WITNESS_DIRS);
$fixCover   = fpr_code_mentions($ROOT, '~FIX[ABC]-\d{4}(?:-\S)?~u', $COVER_DIRS);

/* قائمةُ الإغلاقِ المشهودةِ — تُقرأ من مصدرِها لا تُنسخ */
$closedDeclared = array();
$statusTool = $ROOT . '/tools/fix_status_report.php';
if (is_file($statusTool)) {
    $src = (string) file_get_contents($statusTool);
    if (preg_match('~\$CLOSED\s*=\s*array\((.*?)\n\);~s', $src, $m)
        && preg_match_all("~'(INJ-\d{4})'~", $m[1], $mm)) {
        foreach ($mm[1] as $id) { $closedDeclared[$id] = true; }
    }
}
$say('   قائمةُ الإغلاقِ المشهودةِ (من tools/fix_status_report.php): ' . count($closedDeclared));
$say('   معرِّفاتٌ يذكرها مِسبارٌ — ملاحظاتٌ: ' . count($injWitness) . ' · أحكامٌ: ' . count($fixWitness));

/* ── خُضرةُ المسابيرِ حيًّا (بـ--live) ──────────────────────────────────────── */
$greenFiles = array();
if ($LIVE) {
    $say('   ⟳ تشغيلُ المسابيرِ المذكورةِ…');
    $probe = array();
    foreach (array_merge($injWitness, $fixWitness) as $id => $files) {
        foreach (array_keys($files) as $f) {
            if (preg_match('~^(tests|tools)/.+\.php$~', $f)) { $probe[$f] = true; }
        }
    }
    $php = PHP_BINARY;
    $ran = 0;
    foreach (array_keys($probe) as $f) {
        if (preg_match('~/(_|seed_)~', $f)) { continue; }
        $o = array(); $code = 0;
        @exec('"' . $php . '" "' . $ROOT . '/' . $f . '" 2>&1', $o, $code);
        $txt = implode("\n", $o);
        $fail = null;
        if (preg_match('~FAIL\s*=\s*(\d+)~', $txt, $m)) { $fail = (int) $m[1]; }
        if ($fail === null && preg_match('~(\d+)\s*(?:فاشلة?|فشل|ساقطة?)~u', $txt, $m)) { $fail = (int) $m[1]; }
        if ($fail === null) { $fail = preg_match_all('~^\s*✘~mu', $txt); }
        $greenFiles[$f] = ($code === 0 && $fail === 0);
        $ran++;
    }
    $say('   شُغِّل ' . $ran . ' مِسبارًا · أخضرُ منها ' . count(array_filter($greenFiles)));
}

/** حالةُ بندٍ واحد — والقاعدةُ مكتوبةٌ في موضعٍ واحد */
$classify = function ($id, array $witness, array $cover, array $closedList)
             use ($LIVE, &$greenFiles) {
    if (isset($closedList[$id])) { return 'مُغلقٌ بشاهد'; }
    if (isset($witness[$id])) {
        if (!$LIVE) { return 'مُغلقٌ بشاهد'; }
        foreach (array_keys($witness[$id]) as $f) {
            if (isset($greenFiles[$f]) && $greenFiles[$f]) { return 'مُغلقٌ بشاهد'; }
        }
        return 'مفتوح';        /* له مِسبارٌ وهو أحمرُ الآن */
    }
    if (isset($cover[$id])) { return 'مُغطًّى'; }
    return 'غيرُ مقيس';
};

/* ═══════════════════════════════════════════════════════════════════════════
 * ⑤ النسبُ — ثلاثٌ مستقلّةٌ لا تُجمع
 * ═══════════════════════════════════════════════════════════════════════════ */
$STATES = array('مُغلقٌ بشاهد', 'مُغطًّى', 'مفتوح', 'غيرُ مقيس');
$tables = array();

/* ── ① حزمُ التصحيح ─────────────────────────────────────────────────────── */
$fixState = array();
foreach ($fixRules as $id => $r) {
    $fixState[$id] = $classify($id, $fixWitness, $fixCover, array());
}
$tables['حزمُ التصحيح (أحكامٌ ذرية)'] = array('total' => $fixTotal, 'state' => $fixState);

/* ── ② السجلُّ الجامع ────────────────────────────────────────────────────── */
$injState = array();
foreach ($tsvIds as $id) {
    $injState[$id] = $classify($id, $injWitness, $injCover, $closedDeclared);
}
$tables['السجلُّ الجامع (ملاحظات)'] = array('total' => count($tsvIds), 'state' => $injState);

/* ── ③ حواجبُ الإطلاق — مجموعةٌ فرعيةٌ من السجل، تُقاس على حِدة ─────────────── */
$blkState = array();
foreach ($tsvRow as $id => $r) {
    if (mb_strpos($r['blocks'], 'نعم') === false) { continue; }
    $blkState[$id] = isset($injState[$id]) ? $injState[$id] : 'غيرُ مقيس';
}
$tables['حواجبُ الإطلاق'] = array('total' => count($blkState), 'state' => $blkState);

$say('');
$say('══════════════════════════════════════════════════════════════════');
$say('  النسبُ — ثلاثةُ مقاماتٍ مستقلّةٌ لا تُجمع');
$say('══════════════════════════════════════════════════════════════════');
$summary = array();
foreach ($tables as $name => $t) {
    $cnt = array_fill_keys($STATES, 0);
    foreach ($t['state'] as $s) { if (isset($cnt[$s])) { $cnt[$s]++; } }
    $tot = max(1, $t['total']);
    $say('');
    $say('  【' . $name . '】 المقامُ ' . $t['total']);
    foreach ($STATES as $s) {
        printf("      %-16s %4d   %5.1f٪\n", $s, $cnt[$s], $cnt[$s] * 100 / $tot);
        $out[] = sprintf('      %-16s %4d   %5.1f٪', $s, $cnt[$s], $cnt[$s] * 100 / $tot);
    }
    $measured = $tot - $cnt['غيرُ مقيس'];
    /* ◆ **التباسٌ يجب أن يُعلَن**: في الوضعِ الساكنِ لا يُمكن التمييزُ بين «مفتوحٍ»
         و«غيرِ مقيسٍ» — فما لا مِسبارَ له لا يُعرَف حالُه. فيُقال ذلك صريحًا بدلَ
         أن يُقرأ «صفرُ مفتوحٍ» إنجازًا. */
    if (!$LIVE) {
        $say("      ○ في الوضعِ الساكنِ «مفتوح» صفرٌ بالضرورةِ — التمييزُ يحتاج --live");
    }
    $say('      ── المنجَزُ من **المقيسِ** وحدَه: ' . $cnt['مُغلقٌ بشاهد'] . ' من ' . $measured
       . ($measured > 0 ? sprintf('  (%.1f٪)', $cnt['مُغلقٌ بشاهد'] * 100 / $measured) : ''));
    $summary[$name] = array('total' => $t['total'], 'cnt' => $cnt, 'measured' => $measured);
}

/* ── سجلاتٌ مكتوبةٌ ليُعمَل عليها ────────────────────────────────────────────── */
$wrote = array();
$dump = function ($file, array $header, array $rows) use (&$wrote) {
    $fh = fopen($file, 'w');
    fwrite($fh, "\xEF\xBB\xBF" . implode("\t", $header) . "\n");
    foreach ($rows as $r) { fwrite($fh, implode("\t", $r) . "\n"); }
    fclose($fh);
    $wrote[] = $file . ' (' . count($rows) . ' صفًّا)';
};
$rowsFix = array();
foreach ($fixRules as $id => $r) {
    $rowsFix[] = array($id, $r['doc'], str_replace("\t", ' ', mb_substr($r['req'], 0, 400)),
                       str_replace("\t", ' ', mb_substr($r['test'], 0, 300)), $fixState[$id],
                       isset($fixWitness[$id]) ? implode(' · ', array_keys($fixWitness[$id])) : '');
}
$dump($REGDIR . '/FIX_rules_register.tsv',
      array('id', 'doc', 'requirement', 'acceptance_test', 'state', 'witness'), $rowsFix);
$rowsInj = array();
foreach ($tsvIds as $id) {
    $rowsInj[] = array($id, isset($tsvRow[$id]) ? $tsvRow[$id]['sev'] : '',
                       isset($tsvRow[$id]) ? $tsvRow[$id]['blocks'] : '', $injState[$id],
                       isset($injWitness[$id]) ? implode(' · ', array_keys($injWitness[$id])) : '');
}
$dump($REGDIR . '/INJ_findings_state.tsv',
      array('id', 'severity', 'blocks_launch', 'state', 'witness'), $rowsInj);

$say('');
$say('── سجلاتٌ كُتبت:');
foreach ($wrote as $w) { $say('   · ' . str_replace($ROOT . '/', '', $w)); }

/* ── تقريرٌ بصيغةِ Markdown إن طُلب ──────────────────────────────────────── */
if ($MD !== null) {
    $md = "# نسبةُ المنجَزِ من مستنداتِ التصحيح\n\n";
    $md .= '> مقيسٌ آليًّا: ' . date('Y-m-d H:i') . ' · ' . ($LIVE ? 'قياسٌ حيٌّ' : 'قياسٌ ساكن') . "\n";
    $md .= "> التشغيل: `php tools/fix_progress_report.php --live --md=" . $MD . "`\n\n";
    $md .= "**ثلاثةُ مقاماتٍ مستقلّةٌ لا تُجمع** — كلٌّ يجيب سؤالًا آخر.\n\n";
    $md .= "| المقام | العدد | مُغلقٌ بشاهد | مُغطًّى | مفتوح | غيرُ مقيس | المنجَزُ من المقيس |\n";
    $md .= "|---|---:|---:|---:|---:|---:|---:|\n";
    foreach ($summary as $name => $s) {
        $c = $s['cnt'];
        $md .= sprintf("| %s | %d | %d (%.1f٪) | %d | %d | %d | %s |\n",
            $name, $s['total'], $c['مُغلقٌ بشاهد'], $c['مُغلقٌ بشاهد'] * 100 / max(1, $s['total']),
            $c['مُغطًّى'], $c['مفتوح'], $c['غيرُ مقيس'],
            $s['measured'] > 0 ? sprintf('%d/%d (%.1f٪)', $c['مُغلقٌ بشاهد'], $s['measured'],
                $c['مُغلقٌ بشاهد'] * 100 / $s['measured']) : '—');
    }
    $md .= "\n## قاعدةُ الحساب\n\n";
    $md .= "- **مُغلقٌ بشاهد**: معرِّفُه مذكورٌ في مِسبارٍ أو فاحصٍ" . ($LIVE ? " **وهو أخضرُ الآن**" : "") . "، أو في قائمةِ الإغلاقِ المشهودة.\n";
    $md .= "- **مُغطًّى**: له دليلٌ في الشيفرةِ ولا شاهدَ مخصَّص.\n";
    $md .= "- **غيرُ مقيس**: لا مِسبارَ يُخصُّه — **لا يُحسَب منجَزًا ولا مفتوحًا**.\n";
    $md .= "- **مفتوح**: قِيس فلم يتحقّق.\n\n";
    $md .= "والعمودُ الأخيرُ («المنجَزُ من المقيس») هو النسبةُ الصادقةُ لما يُمكن الحكمُ عليه اليومَ.\n";
    if (!is_dir(dirname($MD))) { @mkdir(dirname($MD), 0777, true); }
    file_put_contents($MD, $md);
    $say('   · ' . $MD . ' (تقريرُ Markdown)');
}

$say('');
$say('✅ اكتمل. لإعادةِ القياسِ بعد أيِّ تصحيحٍ: أعِد الأمرَ نفسَه — لا رقمَ يدويًّا فيه.');
exit(0);
