<?php
/**
 * tools/repair01_ops_decide.php — سايدبارُ إدارةِ التشغيلِ كما ينصُّ الدليل
 * ═══════════════════════════════════════════════════════════════════════════
 * **طلبُ المالك**: «صمِّم السايدبارَ في إدارةِ التشغيلِ بمستخدم `محمد` كما في
 * ملفِّ الإكسل — ورقةِ التشغيل».
 *
 * ◆ **والورقةُ 11 تنصُّ على البنيةِ كاملةً**: «6 مجموعات» · «12 شاشة —
 *   **ترتيبُها في هذا الشيت هو ترتيبُها في الـSidebar وهو ترتيبُ دورةِ العمل**».
 *   ⇒ فالمجموعةُ واسمُها وترتيبُها، والشاشةُ واسمُها وموضعُها — **كلُّها منصوصة**.
 *
 * ⛔ **والوصلُ باسمِ الشاشةِ وحدَه كذبٌ مُثبَت**: أحدَ عشرَ عنوانًا من اثنَي عشرَ
 *   **لا نظيرَ لها بالاسمِ المعياريِّ في الملاحة** (`GUIDE_NAV_GAP.md` §DEP-11)،
 *   ولو وُصلت بالتشابهِ وحدَه لذهبت «الخطةُ الموسمية» إلى «الخطةِ الشهرية».
 *   ⇒ **فأربعُ إشاراتٍ تُجمَع** ولا يُوصَل ما لا يُحسَم:
 *   ① تقاطعُ كلماتِ الاسمِ بعد تجذيرٍ خشِن
 *   ② حبّةُ الدليلِ (Grain) في نصِّ الشاشةِ الحيّة — تصف السلوكَ لا التسمية
 *   ③ **أسماءُ حقولِ الدليلِ في نصِّ الشاشةِ الحيّة** — وهي أقوى الإشاراتِ لأنَّ
 *      الدليلَ ينصُّ على 10..38 حقلًا لكلِّ شاشةٍ **والاسمُ كلمتان أو ثلاث**
 *      ⇒ إشارةٌ رابعةٌ لم تكن في أداةِ الموردين، **وتُعلَن زيادةً لا تُدَسّ**
 *   ④ جداولُ الشاشةِ الحيّةِ تحمل كلماتِ الاسم
 *
 * ⛔ **والمجالُ ليس إدارةَ التشغيلِ وحدَها**: الدليلُ يقول في «حدودِ مصدرِ
 *   الحقيقة» إنَّ التشغيلَ **يملك التايم شيتَ والوحدةَ المعتمدة** بينما السجلُّ
 *   يُسندهما إلى `DEP-04` و`DEP-08` — **فحصرُ البحثِ في `DEP-11` يُنتج «ناقصٌ»
 *   كاذبًا لشاشةٍ حيّةٍ قائمة**. ⇒ المجالُ **السطحُ التشغيليُّ كلُّه** ويُعلَن.
 *
 * التشغيل: php tools/repair01_ops_decide.php [--md]
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

$ROLE = 1;                       /* «ادارة التشغيل» — دورُ المستخدم `محمد` (users.id=4) */
$DEP  = 'DEP-11';

$SPEC = $ROOT . '/docs/REPAIR01_20260823/GUIDE_SPEC_11.json';
if (!is_file($SPEC)) { exit("⛔ استخرِج المواصفةَ أوّلًا: php tools/repair01_guide_screen_spec.php 11 --json\n"); }
$spec = json_decode((string) file_get_contents($SPEC), true);

$nz = function ($v) { $v = str_replace(array('أ', 'إ', 'آ'), 'ا', (string) $v); $v = str_replace('ة', 'ه', $v);
    $v = preg_replace('~[\x{064B}-\x{0655}\x{0670}\x{0640}]~u', '', $v);
    return preg_replace('~\s+~u', ' ', trim($v)); };
$stem = function ($w) { $w = preg_replace('~^(وال|بال|فال|كال|ال|و)~u', '', $w);
    return preg_replace('~(ات|ون|ين|ه|ي)$~u', '', $w); };
$toks = function ($t) use ($nz, $stem) { $o = array();
    foreach (preg_split('~[\s—·\-–\(\)/،,]+~u', $nz($t)) as $w) { $w = $stem($w); if (mb_strlen($w) >= 3) { $o[$w] = 1; } }
    return $o; };

/* ── فهرسُ الملفّاتِ على القرص بمسارِها النسبيّ ─────────────────────────────── */
$idx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    if (substr($p->getFilename(), -4) !== '.php') { continue; }
    $s = strtr($p->getPathname(), DIRECTORY_SEPARATOR, '/');
    if (strpos($s, '/.git/') !== false || strpos($s, '/vendor/') !== false
        || strpos($s, '/storage/backups/') !== false) { continue; }
    $rel = ltrim(str_replace(strtr($ROOT, DIRECTORY_SEPARATOR, '/'), '', $s), '/');
    if (!isset($idx[$rel])) { $idx[$rel] = $s; }
}

/* ── المجالُ المُعلَن: سطحُ التشغيلِ الحيُّ + كلُّ ما يراه الدورُ اليوم ────────── */
$live = array(); $seen = array();
$sql = "SELECT r.screen_file, r.route, r.canonical_label_ar, r.owner_code, r.ownership_verdict,
               r.visibility_class
          FROM repair01_screen_registry r
         WHERE r.on_disk = 1 AND r.ownership_verdict <> 'RETIRE' AND r.route IS NOT NULL
           AND ( r.owner_code = '$DEP'
              OR r.route REGEXP '^(Operations|Timesheet|movement|Approvals|Workforce)/'
              OR r.route IN (SELECT DISTINCT SUBSTRING_INDEX(n.route, '?', 1)
                               FROM nav_items n WHERE n.role_id = $ROLE) )";
$r = $conn->query($sql);
while ($r && ($x = $r->fetch_assoc())) {
    if (isset($seen[$x['route']])) { continue; }
    $seen[$x['route']] = 1;
    $f = isset($idx[$x['route']]) ? $idx[$x['route']] : null;
    $c = $f ? (string) @file_get_contents($f) : '';
    preg_match_all('~\b(?:FROM|JOIN|INSERT\s+INTO|UPDATE)\s+`?([a-z_][a-z_0-9]{2,})~i', $c, $m);
    $x['tables'] = array_values(array_unique(array_map('strtolower', $m[1])));
    /* نصُّ الشاشةِ العربيُّ: ما بين الوسومِ + النصوصُ المقتبَسة (عناوينُ ورؤوسُ أعمدة) */
    preg_match_all('~>([^<>{}]*[\x{0600}-\x{06FF}][^<>{}]*)<~u', $c, $m2);
    preg_match_all('~[\'"]([^\'"]*[\x{0600}-\x{06FF}][^\'"]*)[\'"]~u', $c, $m3);
    $x['ar'] = $nz(mb_substr(implode(' ', $m2[1]) . ' ' . implode(' ', $m3[1]), 0, 24000));
    $x['has'] = $f !== null;
    $live[] = $x;
}
printf("\n═══ قرارُ سايدبارِ التشغيل — الدور %d (`محمد`) ═══\n", $ROLE);
printf("  المجالُ المقيس: %d شاشةً حيّة · المنصوصُ في الدليل: %d\n\n", count($live), count($spec));

/* ── الاسمُ المعياريُّ مطابقٌ حرفًا يحجز الشاشةَ قبل أيِّ ترجيح ──────────────── */
$claimed = array();
foreach ($spec as $g) { foreach ($live as $lv) {
    if ($nz($lv['canonical_label_ar']) === $nz($g['title'])) { $claimed[$lv['route']] = $g['no']; } } }

/* ── مصفوفةُ الإشاراتِ كاملةً قبل أيِّ حكم ──────────────────────────────────
   ⚠ **والعطبُ كان في ترتيبِ المرورِ لا في الإشارة**: `Timesheet/timesheet.php`
     نصُّها العربيُّ آلافُ الكلماتِ فتُصيب حقولَ أكثرِ الشاشات — **فحجزها
     «احتياجُ الغدِ» بـ37٪ لأنَّه مرَّ أوّلًا، و«التايم شيت اليومي» يستحقُّها
     بـ50٪**. ⇒ **فالإسنادُ عامٌّ لا متتالٍ**: تُحسَب الأزواجُ كلُّها ثمَّ يُؤخَذ
     الأعلى فالأعلى، فتذهب كلُّ شاشةٍ حيّةٍ إلى منصوصِها الأقوى لا إلى أسبقِه.
   ⛔ **وطرحُ متوسّطِ الشاشةِ من إصابتِها جُرِّب فأفنى الإشارةَ**: شاشاتُ الإدارةِ
     الواحدةِ تتقاسم مفرداتِها (وردية · موقع · معدة · تاريخ) فالمتوسّطُ مرتفعٌ
     والرفعُ صفرٌ للجميع — **12 «ناقصًا» كاذبًا**. فبقيت الإشارةُ خامًا ويُعلَن. */
$M = array();                        /* [gi][li] => array(s1,s2,s3,s4) */
$G = array();
foreach ($spec as $gi => $g) {
    $gf = array();
    foreach ($g['fields'] as $fd) { foreach ($toks($fd['name']) as $w => $_) { $gf[$w] = 1; } }
    $G[$gi] = array('t' => $toks($g['title']), 'r' => $toks($g['grain'] . ' ' . $g['purpose']), 'f' => $gf);
}
foreach ($spec as $gi => $g) {
    foreach ($live as $li => $lv) {
        $gt = $G[$gi]['t']; $gr = $G[$gi]['r']; $gf = $G[$gi]['f'];
        $lt = $toks($lv['canonical_label_ar']);
        $u = count($gt + $lt);
        $s1 = $u ? count(array_intersect_key($gt, $lt)) / $u : 0;
        $h2 = 0; foreach (array_keys($gr) as $w) { if (mb_strpos($lv['ar'], $w) !== false) { $h2++; } }
        $h3 = 0; foreach (array_keys($gf) as $w) { if (mb_strpos($lv['ar'], $w) !== false) { $h3++; } }
        $tb = $nz(implode(' ', $lv['tables']));
        $h4 = 0; foreach (array_keys($gt) as $w) { if (mb_strpos($tb, $w) !== false) { $h4++; } }
        $M[$gi][$li] = array($s1, count($gr) ? $h2 / count($gr) : 0,
                             count($gf) ? $h3 / count($gf) : 0, count($gt) ? $h4 / count($gt) : 0);
    }
}
$score = function ($gi, $li) use ($M) {
    $s = $M[$gi][$li];
    return 0.40 * $s[0] + 0.20 * $s[1] + 0.30 * $s[2] + 0.10 * $s[3];
};

/* ── والإسنادُ عامٌّ لا متتالٍ: أعلى زوجٍ أوّلًا ─────────────────────────────
   ⛔ **والمرورُ شاشةً شاشةً يُعطي الأولى ما هو للسادسة** — فالسابقُ يحجز.
     ⇒ تُرتَّب الأزواجُ كلُّها تنازليًّا ويُؤخَذ الأعلى فالأعلى. */
$pairs = array();
foreach ($spec as $gi => $g) { foreach ($live as $li => $lv) {
    if (isset($claimed[$lv['route']])) { continue; }
    $pairs[] = array($score($gi, $li), $gi, $li); } }
usort($pairs, function ($a, $b) { return $b[0] <=> $a[0]; });
$pickG = array(); $pickL = array();
foreach ($pairs as $p) {
    if ($p[0] < 0.30) { break; }                     /* دون عتبةِ الشكِّ لا إسناد */
    if (isset($pickG[$p[1]]) || isset($pickL[$p[2]])) { continue; }
    $pickG[$p[1]] = array($p[2], $p[0]); $pickL[$p[2]] = $p[1];
}

$D = array();
foreach ($spec as $gi => $g) {
    $exact = null;
    foreach ($live as $lv) { if ($nz($lv['canonical_label_ar']) === $nz($g['title'])) { $exact = $lv; break; } }
    if ($exact) {
        $D[] = array('no' => $g['no'], 'title' => $g['title'], 'group' => $g['group'], 'verdict' => 'EXACT',
            'target' => $exact['route'], 'targetLabel' => $exact['canonical_label_ar'],
            'owner' => $exact['owner_code'], 'why' => 'الاسمُ المعياريُّ مطابقٌ حرفًا', 'score' => 100);
        continue;
    }
    $best = null; $bs = 0; $bw = 'لا مرشَّحَ فوقَ الأرضيّة';
    if (isset($pickG[$gi])) {
        $li = $pickG[$gi][0]; $bs = $pickG[$gi][1]; $best = $live[$li];
        $s = $M[$gi][$li];
        $bw = sprintf('اسم %d%% · حبّة %d%% · **حقول %d%%** · جداول %d%%',
            round($s[0] * 100), round($s[1] * 100), round($s[2] * 100), round($s[3] * 100));
    }
    /* ⛔ **والعتبةُ لا تُخفَّض حتّى تُنتج اثنتَي عشرةَ** — فذلك يُخفي حكمي خلفَ رقم.
         والحدُّ نفسُه المستعمَلُ في الموردين: 0.42 وصلٌ · 0.30 شكٌّ يُرفَع. */
    $v = ($bs >= 0.42) ? 'REUSE' : (($bs >= 0.30) ? 'DOUBT' : 'BUILD');
    $D[] = array('no' => $g['no'], 'title' => $g['title'], 'group' => $g['group'], 'verdict' => $v,
        'target' => ($v === 'BUILD' || !$best) ? '' : $best['route'],
        'targetLabel' => ($v === 'BUILD' || !$best) ? '' : $best['canonical_label_ar'],
        'owner' => ($v === 'BUILD' || !$best) ? '' : $best['owner_code'],
        'why' => $bw, 'score' => round($bs * 100));
}

/* ── وصلاحيةُ الدورِ تُقاس لكلِّ هدفٍ — فما لا يملكه الدورُ لا يُصيَّر ─────────── */
foreach ($D as $i => $x) {
    $D[$i]['can_view'] = null;
    if ($x['target'] === '') { continue; }
    $q = $conn->query("SELECT rp.can_view FROM modules m
                        LEFT JOIN role_permissions rp ON rp.module_id = m.id AND rp.role_id = $ROLE
                        WHERE m.code = '" . $conn->real_escape_string($x['target']) . "' LIMIT 1");
    $row = $q ? $q->fetch_row() : null;
    $D[$i]['can_view'] = ($row === null) ? 'NO_MODULE'
        : (($row[0] === null) ? 'NO_GRANT' : (((int) $row[0] === 1) ? 'YES' : 'NO'));
}

$T = array();
foreach ($D as $x) { $T[$x['verdict']] = (isset($T[$x['verdict']]) ? $T[$x['verdict']] : 0) + 1; }
foreach (array('EXACT' => 'مطابقٌ بالاسم', 'REUSE' => '**موجودٌ باسمٍ آخرَ — يُوصَل**',
               'DOUBT' => 'مشكوكٌ — يُرفع للمالك', 'BUILD' => '**ناقصٌ بحقٍّ — يُبنى**') as $k => $lbl) {
    printf("  %-6s %-34s %d\n", $k, $lbl, isset($T[$k]) ? $T[$k] : 0);
}
echo "\n";
foreach ($D as $x) {
    printf("  %-6s %2d %-30s", $x['verdict'], $x['no'], mb_substr($x['title'], 0, 28));
    if ($x['target'] !== '') { printf(" ⇒ %-32s %3d%%  [%s · %s]",
        mb_substr($x['target'], 0, 30), $x['score'], $x['owner'], $x['can_view']); }
    else { printf(" %3d%%", $x['score']); }
    echo "\n";
}

if ($MD) {
    $o  = "# قرارُ سايدبارِ إدارةِ التشغيل — الوصلُ بالدليل\n\n";
    $o .= "> ⛔ **مولَّدٌ من قياسٍ حيّ**: `php tools/repair01_ops_decide.php --md`\n";
    $o .= "> **الدور 1 «ادارة التشغيل»** — مستخدمُ المالكِ المذكور `محمد` (`users.id=4`).\n";
    $o .= "> **أربعُ إشاراتٍ تُجمَع**: الاسم · الحبّة · **أسماءُ حقولِ الدليل** · الجداول.\n\n";
    $o .= "| الحكم | العدد |\n|---|---:|\n";
    foreach (array('EXACT', 'REUSE', 'DOUBT', 'BUILD') as $k) { if (isset($T[$k])) { $o .= "| `$k` | {$T[$k]} |\n"; } }
    $o .= "\n| # | المجموعة | المنصوصُ في الدليل | الحكم | الحيُّ المستعمَل | مالكُه | `can_view` | الإشارات |\n";
    $o .= "|---:|---|---|---|---|---|---|---|\n";
    foreach ($D as $x) {
        $o .= sprintf("| %d | %s | %s | `%s` | %s | %s | %s | %s |\n", $x['no'], $x['group'], $x['title'],
            $x['verdict'], $x['target'] !== '' ? ('«' . $x['targetLabel'] . '» `' . $x['target'] . '`') : '—',
            $x['owner'] !== '' ? '`' . $x['owner'] . '`' : '—',
            $x['can_view'] === null ? '—' : '`' . $x['can_view'] . '`', $x['why']);
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/OPS_DECISION.md', $o);
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/OPS_DECISION.json',
        json_encode($D, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\n✔ كُتب OPS_DECISION.md و .json\n";
}
