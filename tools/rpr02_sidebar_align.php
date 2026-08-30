<?php
/**
 * tools/rpr02_sidebar_align.php — `RPR-02` #٨ · محاذاةُ السايدبارِ بالملفِّ التصميميّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٥·٦: مجموعاتُ السايدبارِ وترتيبُه **تطابق الملفَّ
 *   التصميميّ**. والمقيسُ بـ`rpr02_s6_sidebar.php`: **مطابقٌ ٥٢٢ من ٧٦٧ ⇒
 *   ٦٨٫١٪** · ومخالفٌ **٢٤٥** موضعًا على **٤٥** مسارًا فريدًا.
 *
 * ◆ **والمقارنةُ هي هي — ولا قارئَ ثانٍ يتفرّق**: المُصيَّرُ يُقرأ بترتيبِ
 *   الأسبقيّةِ نفسِه الذي يقرؤه المُصيِّر (‏مُعلَنٌ `gov_target_nav.group_ar` ⇐
 *   معتمَدٌ `nav_canonical.group_name` ⇐ إرثٌ `link_groups.name`)، والملفُّ
 *   من `repair01_requirements` بجسرِ `repair01_target_universe` (`MATCHED`).
 *   ⛔ **ومقارنةُ المُصيَّرِ بـ`nav_canonical` دَورٌ لا قياس** — لذا الطرفُ
 *   الثاني **الملفُّ** لا السجلّ.
 *
 * ◆ **وموضعُ الكتابةِ `nav_canonical`** — لأنّه **المصدرُ المعتمَدُ الذي يقرؤه
 *   المُصيِّر**: تغييرُ `link_groups` لا يراه مستخدمٌ له صفٌّ معتمَد (‏إرثٌ
 *   مُصيَّرٌ = صفر من ٧٦٧). ⛔ **وما كان مُعلَنًا في `gov_target_nav` لا يُمَسّ**:
 *   الإعلانُ يغلب المعتمَدَ في التصيير، فتغييرُ المعتمَدِ تحته **لا يُرى**
 *   ويُنتج فرقًا في المخزنِ لا يراه أحد.
 *
 * ⛔ **ولا يُطبَّق إلّا ما جُسِر**: مسارٌ بلا متطلبٍ مطابَقٍ (`NO_BRIDGE`)
 *   **لا ملفَّ له يُحاذى إليه** — ولا يُخترع له موضع.
 *
 * ◆ **والمعاينةُ أثرٌ للمراجعةِ لا بوّابةُ إذن** (‏أمرُ الإنهاءِ §٣):
 *   `SIDEBAR_PREVIEW.md` قبل/بعد لكلِّ إدارةٍ — **ثمَّ يُطبَّق فورًا**،
 *   والتراجعُ بهجرةٍ عكسيّةٍ من `repair01_sidebar_align` نفسِه.
 *
 * التشغيل:
 *   php tools/rpr02_sidebar_align.php [--apply] [--md] [--selftest]
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

/* ═══ ① التطبيعُ — نفسُه في المقياسِ والمحاذاة، ولا نسختان تتفرّقان ══════ */
function sa_norm($s)
{
    $s = preg_replace('~[\x{0617}-\x{061A}\x{064B}-\x{0652}\x{0640}]~u', '', (string) $s);
    $s = strtr($s, array('أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي'));
    $s = preg_replace('~[^\p{Arabic}\p{L}\p{N}]+~u', ' ', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
}
/* صنفُ التغييرِ — ⛔ **ولا صفَّ بلا تغييرٍ فعليّ** */
function sa_kind($bg, $ag, $bo, $ao)
{
    $g = (sa_norm($bg) !== sa_norm($ag));
    $o = ((int) $bo !== (int) $ao && (int) $ao > 0);
    if ($g && $o) { return 'GROUP_AND_ORDER'; }
    if ($g) { return 'GROUP'; }
    return $o ? 'ORDER' : '';
}

/* ═══ ② الاختبارُ السالبُ — يُصيب الطرفَين ولا يمرُّ بمفردةٍ فريدة ═══════ */
if ($SELF) {
    $fail = 0;
    if (sa_norm('العُقودُ وأحكامُها') !== sa_norm('العقود واحكامها')) { echo "  X التشكيلُ لم يُطبَّع\n"; $fail++; }
    if (sa_norm('التعاقد') === sa_norm('التعاقد والالتزام'))          { echo "  X معنيانِ طوبقا\n"; $fail++; }
    if (sa_kind('أ', 'ب', 3, 3) !== 'GROUP')            { echo "  X تغييرُ المجموعةِ لم يُعرَف\n"; $fail++; }
    if (sa_kind('أ', 'أ', 3, 7) !== 'ORDER')            { echo "  X تغييرُ الترتيبِ لم يُعرَف\n"; $fail++; }
    if (sa_kind('أ', 'ب', 3, 7) !== 'GROUP_AND_ORDER')  { echo "  X التغييرانِ لم يُجمعا\n"; $fail++; }
    /* **الكاسر**: لا تغييرَ ⇒ لا صفّ — ولولاه لامتلأ سجلُّ تغييرٍ بضجيج */
    if (sa_kind('أ', 'أ', 3, 3) !== '')                 { echo "  X صفٌّ بلا تغييرٍ قُبِل\n"; $fail++; }
    /* ⛔ **وترتيبُ الملفِّ صفرًا ليس ترتيبًا** — فلا يُقلَب موجودٌ إلى عدم */
    if (sa_kind('أ', 'أ', 5, 0) !== '')                 { echo "  X صفرُ الملفِّ عُدَّ ترتيبًا\n"; $fail++; }
    if (sa_kind('أ', 'ا', 3, 3) !== '')                 { echo "  X فرقُ الهمزةِ عُدَّ تغييرًا\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — والتطبيعُ يميّز ولا صفَّ بلا تغيير\n";
    exit($fail ? 1 : 0);
}

/* ═══ ③ نافذةُ القياس ════════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && $APPLY) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — جمِّدْ أوّلًا.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ═══ ④ الأطراف ══════════════════════════════════════════════════════════ */
$key = function ($s) { return strtolower(trim(preg_replace('~[?#].*$~', '', (string) $s), '/')); };

/* المُعلَنُ — يغلب المعتمَدَ في التصيير، ⛔ فلا يُمَسّ ما تحته */
$declared = array();
$q = @$conn->query("SELECT route, group_ar FROM gov_target_nav WHERE group_ar <> ''");
while ($q && ($z = $q->fetch_row())) {
    if (strncmp((string) $z[0], 'GAP:', 4) === 0) { continue; }
    $declared[$key($z[0])] = (string) $z[1];
}
/* المعتمَدُ — وهو موضعُ الكتابة */
$canon = array();
$q = $conn->query("SELECT route, group_name, sort_no FROM nav_canonical");
while ($q && ($z = $q->fetch_assoc())) { $canon[$key($z['route'])] = $z; }
/* الإرثُ — يُقرأ للعرضِ ولا يُكتب */
$legacy = array();
$q = $conn->query("SELECT n.route, g.name gname FROM nav_items n
                    LEFT JOIN link_groups g ON g.id = n.group_id WHERE n.active = 1");
while ($q && ($z = $q->fetch_assoc())) {
    $k = $key($z['route']);
    if (!isset($legacy[$k]) && trim((string) $z['gname']) !== '') { $legacy[$k] = (string) $z['gname']; }
}
/* الملفُّ التصميميُّ بجسرِ المعرِّف */
$spec = array();
$q = $conn->query("SELECT r.requirement_id, r.unit, r.group_name, r.seq, s.route
                     FROM repair01_requirements r
                     JOIN repair01_target_universe t ON t.requirement_id = r.requirement_id
                          AND t.verdict = 'MATCHED' AND t.screen_id <> ''
                     JOIN repair01_screen_registry s ON s.screen_id = t.screen_id
                    WHERE r.group_name <> '' AND s.route <> ''");
while ($q && ($z = $q->fetch_assoc())) { $spec[$key($z['route'])] = $z; }

/* ═══ ⑤ المقارنةُ والخطّة ════════════════════════════════════════════════ */
$plan = array(); $byUnit = array();
$stat = array('match' => 0, 'GROUP' => 0, 'ORDER' => 0, 'GROUP_AND_ORDER' => 0,
              'declared' => 0, 'no_canon' => 0);
foreach ($spec as $k => $sp) {
    $shown = isset($declared[$k]) ? $declared[$k]
           : (isset($canon[$k]) ? (string) $canon[$k]['group_name']
              : (isset($legacy[$k]) ? $legacy[$k] : ''));
    $curOrd = isset($canon[$k]) ? (int) $canon[$k]['sort_no'] : 0;
    $kind = sa_kind($shown, $sp['group_name'], $curOrd, (int) $sp['seq']);
    if ($kind === '') { $stat['match']++; continue; }
    /* ⛔ **والمُعلَنُ لا يُمَسّ** — تغييرُ المعتمَدِ تحته لا يُرى */
    if (isset($declared[$k])) { $stat['declared']++; continue; }
    if (!isset($canon[$k]))   { $stat['no_canon']++; continue; }
    $stat[$kind]++;
    $row = array(
        'route' => $canon[$k]['route'] ?? $k, 'k' => $k, 'req' => $sp['requirement_id'],
        'unit' => (string) $sp['unit'], 'bg' => $shown, 'ag' => (string) $sp['group_name'],
        'bo' => $curOrd, 'ao' => (int) $sp['seq'], 'kind' => $kind,
    );
    $row['wit'] = 'محاذاةٌ بالملفِّ التصميميِّ (`' . $sp['requirement_id'] . '` · ' . $sp['unit'] . '): '
        . ($kind === 'ORDER' ? '' : 'المجموعةُ المُصيَّرةُ «' . $shown . '» ⇐ «' . $sp['group_name'] . '»')
        . ($kind === 'GROUP_AND_ORDER' ? ' · ' : '')
        . ($kind === 'GROUP' ? '' : 'الترتيبُ ' . $curOrd . ' ⇐ ' . (int) $sp['seq'])
        . '. ⛔ **والمُعلَنُ في `gov_target_nav` لا يُمَسّ** — الإعلانُ يغلب المعتمَدَ في التصيير. '
        . 'والموضعُ `nav_canonical` لأنّه ما يقرؤه المُصيِّر · لقطة ' . $sid;
    $plan[] = $row;
    $byUnit[$row['unit']][] = $row;
}

/* الصفُّ الحقيقيُّ للمسارِ في `nav_canonical` — كي تُكتب المطابقةُ بالمفتاحِ الحيّ */
$routeOf = array();
$q = $conn->query("SELECT route FROM nav_canonical");
while ($q && ($z = $q->fetch_row())) { $routeOf[$key($z[0])] = (string) $z[0]; }

/* ═══ ⑥ العرض ════════════════════════════════════════════════════════════ */
$N = count($spec);
echo "\n═══ `RPR-02` #٨ — محاذاةُ السايدبارِ بالملفِّ التصميميّ ═══\n";
printf("  اللقطة: %s · مساراتٌ مجسورةٌ بالملفّ: **%d**\n\n", $sid, $N);
printf("  مطابقٌ سلفًا                 **%4d**\n", $stat['match']);
printf("  `GROUP`            يُحاذى  **%4d**\n", $stat['GROUP']);
printf("  `ORDER`            يُحاذى  **%4d**\n", $stat['ORDER']);
printf("  `GROUP_AND_ORDER`  يُحاذى  **%4d**\n", $stat['GROUP_AND_ORDER']);
printf("  ⛔ مُعلَنٌ في `gov_target_nav` لا يُمَسّ  **%4d**\n", $stat['declared']);
printf("  ⛔ بلا صفٍّ معتمَدٍ يُكتب فيه          **%4d**\n", $stat['no_canon']);
printf("\n  ⇒ **يُحاذى %d مسارًا** على %d إدارةً\n", count($plan), count($byUnit));

/* ═══ ⑦ المعاينةُ — أثرٌ للمراجعةِ لا بوّابةُ إذن ═════════════════════════ */
if ($MD || $APPLY) {
    $o  = "# معاينةُ السايدبار — قبل/بعد بالملفِّ التصميميّ\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n";
    $o .= "> ◆ **والمعاينةُ أثرٌ للمراجعةِ اللاحقةِ لا بوّابةُ إذن** (أمرُ الإنهاءِ §٣) — ";
    $o .= "**والتراجعُ بهجرةٍ عكسيّةٍ** من `repair01_sidebar_align` نفسِه.\n\n";
    $o .= "## المقام\n\n| المفردة | العدد |\n|---|---:|\n";
    $o .= "| مساراتٌ مجسورةٌ بالملفِّ التصميميّ | **$N** |\n";
    $o .= "| مطابقٌ سلفًا | " . $stat['match'] . " |\n";
    $o .= "| يُحاذى — مجموعةً | " . $stat['GROUP'] . " |\n";
    $o .= "| يُحاذى — ترتيبًا | " . $stat['ORDER'] . " |\n";
    $o .= "| يُحاذى — مجموعةً وترتيبًا | " . $stat['GROUP_AND_ORDER'] . " |\n";
    $o .= "| ⛔ مُعلَنٌ في `gov_target_nav` **لا يُمَسّ** | " . $stat['declared'] . " |\n";
    $o .= "| ⛔ بلا صفٍّ معتمَدٍ يُكتب فيه | " . $stat['no_canon'] . " |\n\n";
    $o .= "⛔ **والمُعلَنُ لا يُمَسّ**: الإعلانُ في `gov_target_nav` **يغلب المعتمَدَ في التصيير**، ";
    $o .= "فتغييرُ المعتمَدِ تحته **لا يراه مستخدمٌ** ويُنتج فرقًا في المخزنِ وحدَه.\n\n";
    ksort($byUnit);
    foreach ($byUnit as $unit => $rows) {
        $o .= "## " . ($unit === '' ? '—' : $unit) . "\n\n";
        $o .= "| المسار | المجموعةُ قبل | المجموعةُ بعد | الترتيبُ قبل | بعد |\n|---|---|---|---:|---:|\n";
        foreach ($rows as $x) {
            $o .= '| `' . $x['k'] . '` | ' . ($x['bg'] === '' ? '—' : $x['bg']) . ' | '
                . $x['ag'] . ' | ' . $x['bo'] . ' | ' . $x['ao'] . " |\n";
        }
        $o .= "\n";
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/SIDEBAR_PREVIEW.md', $o);
    echo "\n✔ كُتبت المعاينة: docs/REPAIR01_20260823/SIDEBAR_PREVIEW.md\n";
}

/* ═══ ⑧ التطبيق ══════════════════════════════════════════════════════════ */
if ($APPLY) {
    $has = $conn->query("SHOW TABLES LIKE 'repair01_sidebar_align'");
    if (!$has || !$has->num_rows) {
        exit("\n⛔ **`repair01_sidebar_align` غيرُ موجود** — والعُدّةُ لا تُنشئ مخطَّطًا.\n"
           . "   شغِّلْ: php database/migrations/2028_01_12_rpr02_sidebar_align.php\n");
    }
    $conn->query("DELETE FROM repair01_sidebar_align");
    $n = 0; $w = 0;
    foreach ($plan as $x) {
        $rt = isset($routeOf[$x['k']]) ? $routeOf[$x['k']] : $x['k'];
        $ok = $conn->query("INSERT INTO repair01_sidebar_align
              (route, requirement_id, unit_name, before_group, after_group,
               before_order, after_order, change_kind, witness, snapshot_id, applied_at)
            VALUES ('" . $e($rt) . "','" . $e($x['req']) . "','" . $e($x['unit']) . "','"
             . $e($x['bg']) . "','" . $e($x['ag']) . "'," . (int) $x['bo'] . "," . (int) $x['ao']
             . ",'" . $e($x['kind']) . "','" . $e(mb_substr($x['wit'], 0, 600)) . "','"
             . $e($sid) . "',NOW())");
        if (!$ok) { exit("✘ تعذّر تسجيلُ {$x['k']}: {$conn->error}\n"); }
        $n++;
        $set = array();
        if ($x['kind'] !== 'ORDER')  { $set[] = "`group_name` = '" . $e($x['ag']) . "'"; }
        if ($x['kind'] !== 'GROUP')  { $set[] = "`sort_no` = " . (int) $x['ao']; }
        $u = $conn->query("UPDATE nav_canonical SET " . implode(', ', $set)
                        . " WHERE route = '" . $e($rt) . "'");
        if (!$u) { exit("✘ تعذّرت محاذاةُ {$x['k']}: {$conn->error}\n"); }
        $w += $conn->affected_rows;
    }
    printf("\n  ✔ سُجِّل **%d** مسارًا بقبلِه وبعدِه (وهو مصدرُ التراجع) · وكُتب في `nav_canonical` **%d**\n", $n, $w);
    echo "  ◆ **والتراجعُ**: `php database/migrations/2028_01_12_rpr02_sidebar_align_down.php`\n";
}
