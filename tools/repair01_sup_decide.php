<?php
/**
 * tools/repair01_sup_decide.php — أيُّها موجودٌ باسمٍ آخرَ وأيُّها ناقصٌ بحقّ
 * ═══════════════════════════════════════════════════════════════════════════
 * **قرارُ المالك**: «انظر أنت — **وإن اكتشفتَ تكرارًا فلا تُنشئ له صفحةً جديدة**».
 *
 * ◆ **والاسمُ وحدَه لا يكفي** — أُثبت ذلك مرّتَين في هذه الحملة: «لوحة المبيعات»
 *   ⇔ «لوحة المستودعات» **85٪** وهما شاشتان مختلفتان. **فثلاثُ إشاراتٍ تُجمَع**:
 *   ① **تقاطعُ كلماتِ الاسم** بعد تجذيرٍ خشِن — يمسك إعادةَ الترتيب
 *      («الأداء والوحدات المعتمدة» ⇔ «اعتماد الوحدات والأداء المعتمد»)
 *   ② **حبّةُ الدليلِ في نصِّ الشاشةِ الحيّة** — والحبّةُ تصف ما يمثّله السطرُ
 *      الواحد، **وهي أصدقُ من الاسمِ لأنّها تصف السلوكَ لا التسمية**
 *   ③ **جداولُ الشاشةِ الحيّةِ** تحمل كلماتِ الحبّةِ أو الاسم
 *
 * ⛔ **والحكمُ يميل إلى إعادةِ الاستعمالِ عند الشكّ**: بناءُ شاشةٍ لما يوجد
 *   **يصنع مصدرًا مكرَّرًا** — وهو عطبٌ حاجبٌ أُغلق عند صفر. **وترْكُ شاشةٍ
 *   بلا بناءٍ نقصٌ ظاهرٌ يُقاس ويُرى**، فالأوّلُ أخطرُ من الثاني.
 *
 * التشغيل: php tools/repair01_sup_decide.php [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$MD = in_array('--md', $argv, true);

$SPEC = $ROOT . '/docs/REPAIR01_20260823/GUIDE_SPEC_02.json';
if (!is_file($SPEC)) { exit("⛔ استخرِج المواصفةَ أوّلًا: php tools/repair01_guide_screen_spec.php 02 --json\n"); }
$spec = json_decode((string) file_get_contents($SPEC), true);

$nz = function ($v) { $v = str_replace(array('أ','إ','آ'), 'ا', (string) $v); $v = str_replace('ة', 'ه', $v);
    $v = preg_replace('~[\x{064B}-\x{0655}\x{0670}\x{0640}]~u', '', $v);
    return preg_replace('~\s+~u', ' ', trim($v)); };
$stem = function ($w) { $w = preg_replace('~^(وال|بال|فال|كال|ال|و)~u', '', $w);
    return preg_replace('~(ات|ون|ين|ه|ي)$~u', '', $w); };
$toks = function ($t) use ($nz, $stem) { $o = array();
    foreach (preg_split('~[\s—·\-–\(\)/،,]+~u', $nz($t)) as $w) { $w = $stem($w); if (mb_strlen($w) >= 3) { $o[$w] = 1; } }
    return $o; };

/* ── الشاشاتُ الحيّةُ في الإدارةِ ونصُّ كلٍّ ─────────────────────────────── */
$idx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) { if (substr($p->getFilename(), -4) !== '.php') { continue; }
    $s = strtr($p->getPathname(), DIRECTORY_SEPARATOR, '/');
    if (strpos($s, '/.git/') !== false || strpos($s, '/vendor/') !== false) { continue; }
    if (!isset($idx[$p->getFilename()])) { $idx[$p->getFilename()] = $s; } }

$live = array();
$r = $conn->query("SELECT screen_file, route, canonical_label_ar FROM repair01_screen_registry
                    WHERE owner_code = 'DEP-02' AND on_disk = 1 AND ownership_verdict NOT IN ('RETIRE')");
while ($r && ($x = $r->fetch_assoc())) {
    $b = basename($x['screen_file']);
    $c = isset($idx[$b]) ? (string) @file_get_contents($idx[$b]) : '';
    preg_match_all('~\b(?:FROM|JOIN|INSERT\s+INTO|UPDATE)\s+`?([a-z_][a-z_0-9]{2,})~i', $c, $m);
    $x['tables'] = array_values(array_unique(array_map('strtolower', $m[1])));
    /* نصُّ الشاشةِ العربيُّ المُصيَّر — عناوينُ ورؤوسُ أعمدة */
    preg_match_all('~>([^<>{}]*[\x{0600}-\x{06FF}][^<>{}]*)<~u', $c, $m2);
    $x['ar'] = $nz(mb_substr(implode(' ', $m2[1]), 0, 4000));
    $live[] = $x;
}
$claimed = array();
foreach ($spec as $g) { foreach ($live as $lv) { if ($nz($lv['canonical_label_ar']) === $nz($g['title'])) { $claimed[$lv['route']] = $g['no']; } } }

$D = array();
foreach ($spec as $g) {
    $exact = null;
    foreach ($live as $lv) { if ($nz($lv['canonical_label_ar']) === $nz($g['title'])) { $exact = $lv; break; } }
    if ($exact) { $D[] = array('no' => $g['no'], 'title' => $g['title'], 'group' => $g['group'],
        'verdict' => 'EXACT', 'target' => $exact['route'], 'why' => 'الاسمُ المعياريُّ مطابقٌ حرفًا', 'score' => 100);
        continue; }
    $gt = $toks($g['title']);
    $gr = $toks($g['grain']);
    $best = null; $bs = -1; $bw = '';
    foreach ($live as $lv) {
        if (isset($claimed[$lv['route']])) { continue; }        /* محجوزةٌ لشاشةٍ أخرى */
        $lt = $toks($lv['canonical_label_ar']);
        $u = count($gt + $lt);
        $s1 = $u ? count(array_intersect_key($gt, $lt)) / $u : 0;      /* تقاطعُ الاسم */
        /* ② حبّةُ الدليلِ في نصِّ الشاشةِ الحيّ */
        $hit = 0; $tot = max(1, count($gr));
        foreach (array_keys($gr) as $w) { if (mb_strpos($lv['ar'], $w) !== false) { $hit++; } }
        $s2 = $hit / $tot;
        /* ③ كلماتُ الاسمِ في أسماءِ جداولِها */
        $tb = $nz(implode(' ', $lv['tables']));
        $h3 = 0; foreach (array_keys($gt) as $w) { if (mb_strpos($tb, $w) !== false) { $h3++; } }
        $s3 = count($gt) ? $h3 / count($gt) : 0;
        $sc = 0.55 * $s1 + 0.30 * $s2 + 0.15 * $s3;
        if ($sc > $bs) { $bs = $sc; $best = $lv; $bw = sprintf('اسم %d%% · حبّة %d%% · جداول %d%%',
            round($s1 * 100), round($s2 * 100), round($s3 * 100)); }
    }
    /* ⛔ **عتبةٌ تميل إلى إعادةِ الاستعمال**: التكرارُ عطبٌ حاجبٌ والنقصُ عطبٌ مرئيّ */
    $v = ($bs >= 0.42) ? 'REUSE' : (($bs >= 0.30) ? 'DOUBT' : 'BUILD');
    $D[] = array('no' => $g['no'], 'title' => $g['title'], 'group' => $g['group'], 'verdict' => $v,
                 'target' => ($v === 'BUILD' || !$best) ? '' : $best['route'],
                 'targetLabel' => ($v === 'BUILD' || !$best) ? '' : $best['canonical_label_ar'],
                 'why' => $bw, 'score' => round($bs * 100));
    if ($v === 'REUSE' && $best) { $claimed[$best['route']] = $g['no']; }
}

$T = array();
foreach ($D as $x) { $T[$x['verdict']] = (isset($T[$x['verdict']]) ? $T[$x['verdict']] : 0) + 1; }
echo "\n═══ قرارُ إدارةِ الموردين — تكرارٌ أم نقص؟ ═══\n";
foreach (array('EXACT' => 'مطابقٌ بالاسم', 'REUSE' => '**موجودٌ باسمٍ آخرَ — لا يُبنى**',
               'DOUBT' => 'مشكوكٌ — يُرفع', 'BUILD' => '**ناقصٌ بحقٍّ — يُبنى**') as $k => $lbl) {
    printf("  %-6s %-34s %d\n", $k, $lbl, isset($T[$k]) ? $T[$k] : 0);
}
echo "\n";
foreach ($D as $x) {
    if ($x['verdict'] === 'EXACT') { continue; }
    printf("  %-6s %2d %-34s", $x['verdict'], $x['no'], mb_substr($x['title'], 0, 32));
    if ($x['target'] !== '') { printf(" ⇒ %-34s %d%%", mb_substr($x['targetLabel'], 0, 32), $x['score']); }
    else { printf(" %d%%", $x['score']); }
    echo "\n";
}

if ($MD) {
    $o  = "# قرارُ إدارةِ الموردين — تكرارٌ أم نقص؟\n\n";
    $o .= "> ⛔ **مولَّدٌ من قياسٍ حيّ**: `php tools/repair01_sup_decide.php --md`\n";
    $o .= "> **قرارُ المالك**: «إن اكتشفتَ تكرارًا فلا تُنشئ له صفحةً جديدة».\n";
    $o .= "> **وثلاثُ إشاراتٍ تُجمَع**: تقاطعُ كلماتِ الاسم · حبّةُ الدليلِ في نصِّ الشاشة · جداولُها.\n\n";
    $o .= "| الحكم | العدد |\n|---|---:|\n";
    foreach ($T as $k => $n) { $o .= "| `$k` | $n |\n"; }
    $o .= "\n| # | المنصوصُ في الدليل | المجموعة | الحكم | الحيُّ المستعمَل | الإشارات |\n|---:|---|---|---|---|---|\n";
    foreach ($D as $x) {
        $o .= sprintf("| %d | %s | %s | `%s` | %s | %s |\n", $x['no'], $x['title'], $x['group'],
            $x['verdict'], $x['target'] !== '' ? ('«' . $x['targetLabel'] . '» `' . $x['target'] . '`') : '—', $x['why']);
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/SUP_DECISION.md', $o);
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/SUP_DECISION.json',
        json_encode($D, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\n✔ كُتب SUP_DECISION.md و .json\n";
}
