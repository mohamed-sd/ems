<?php
/**
 * tools/repair01_nav_route_register.php — ربطُ المسارِ الحيِّ بسجلَّي الملاحة
 * ═══════════════════════════════════════════════════════════════════════════
 * **`RPR-02` §٦ الخطوةُ ⑦**: «**اربط الكلَّ بسجلِّ الملاحةِ المعياريّ** — ولا
 * ترتيبَ يدويًّا موازيًا». وبندُ ملاحةٍ يُفعَّل بلا تسجيلٍ يفتح عيبَين معًا:
 *
 * ◆ `NF-24` (‏`GAP-22`) — **مسارٌ نشطٌ بلا صفٍّ في `gov_space_appearances`
 *   مفتوحٌ افتراضًا**: «غيابُ التصنيفِ ليس منعًا».
 * ◆ `RP-02` (‏سجلُّ الدَّينِ `U12`) — **مسارُ تنقّلٍ حيٌّ بلا إدارةٍ مالكة**:
 *   يُقاس بغيابِ صفٍّ ذي `dept_name` في `gov_screen_cycle`.
 *
 * ◆ **والمالكُ يُقرأ من السجلِّ الرسميِّ لا يُخمَّن**: `repair01_screen_registry`
 *   يحمل `owner_code`، و`repair01_departments` يحمل اسمَه — ⛔ **وما لا يُقرأ
 *   مالكُه من السجلِّ لا يُسجَّل بل يُسمّى**.
 *
 * ⚠ **والسجلّانِ يهجّئان الاسمَ هجاءَين**: `gov_screen_cycle.dept_name` فيه
 *   «إدارة التشغيل» و`gov_space_appearances.space_ar` فيه «ادارة التشغيل».
 *   **فيُطبَّع الاسمُ ويُطابَق بمفرداتِ العمودِ الحيّةِ نفسِه، ويُكتب بهجائِها هو**
 *   — ⛔ فكتابةُ هجاءٍ ثالثٍ تُنشئ مفردةً جديدةً في عمودٍ محكوم، وذاك عينُ
 *   «صفرٌ من مفردةٍ لا وجودَ لها» مقلوبًا.
 *
 * التشغيل:
 *   php tools/repair01_nav_route_register.php           ← يقيس ويعرض ولا يكتب
 *   php tools/repair01_nav_route_register.php --apply   ← يسجّل
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
$APPLY = in_array('--apply', $argv, true);
/**
 * ⛔ **ولا تُسجَّل الدفعةُ كلُّها**: السقّاطتان `NF-24` و`RP-02` **تمنعان
 *   الازديادَ عن خطٍّ مقيس** — فتسجيلُ سبعةَ عشرَ مسارًا **يُنقص** الدَّينَ دون
 *   خطِّه، وذلك يوجب شدَّ الخطِّ (`--retighten`) **وهو ممنوعٌ بأمرِ المالك**؛
 *   وهو أيضًا تغييرُ ملكيّةٍ ووصولٍ واسعٌ بلا مراجعة.
 * ◆ **فالعلاجُ بقدرِ الارتداد**: `--only=` يحصر التسجيلَ في المسارِ الذي زاد.
 */
$ONLY = null;
foreach ($argv as $a) {
    if (strpos($a, '--only=') === 0) { $ONLY = strtolower(basename(substr($a, 7))); }
}
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$rows = function ($sql) use ($conn) {
    $r = @$conn->query($sql); $o = array();
    if ($r) { while ($x = $r->fetch_assoc()) { $o[] = $x; } }
    return $o;
};

/** تطبيعٌ عربيٌّ للمطابقة — ⛔ ولا يُكتب المُطبَّعُ بل يُكتب هجاءُ العمودِ الحيّ */
function ar_norm($s)
{
    $s = (string) $s;
    $s = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s);
    $s = preg_replace('/[\x{0622}\x{0623}\x{0625}\x{0627}]/u', 'ا', $s);
    $s = preg_replace('/[\x{0629}]/u', 'ه', $s);
    $s = preg_replace('/[\x{0649}\x{064A}]/u', 'ي', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}
/** مفرداتُ عمودٍ حيّةٌ: مُطبَّعٌ ⇐ الهجاءُ كما هو في القاعدة */
function live_vocab($conn, $table, $col)
{
    $v = array();
    $r = @$conn->query("SELECT DISTINCT `$col` FROM `$table` WHERE COALESCE(`$col`,'') <> ''");
    while ($r && $x = $r->fetch_row()) { $v[ar_norm($x[0])][(string) $x[0]] = 1; }
    return $v;
}
$vocabCycle = live_vocab($conn, 'gov_screen_cycle', 'dept_name');
$vocabSpace = live_vocab($conn, 'gov_space_appearances', 'space_ar');

/* ═══ المقياس ═════════════════════════════════════════════════════════════ */
$inCycle = array(); $ownedCycle = array();
foreach ($rows("SELECT screen_file, dept_name FROM gov_screen_cycle") as $r) {
    $bn = strtolower(basename((string) $r['screen_file']));
    if ($bn === '' || $bn === '.php') { continue; }
    $inCycle[$bn] = 1;
    if (trim((string) $r['dept_name']) !== '') { $ownedCycle[$bn] = 1; }
}
$inSpace = array();
foreach ($rows("SELECT DISTINCT route FROM gov_space_appearances") as $r) {
    $bn = strtolower(basename(preg_replace('~[?#].*$~', '', (string) $r['route'])));
    if ($bn !== '') { $inSpace[$bn] = 1; }
}
/* السجلُّ الرسميُّ — المالكُ ورمزُه واسمُه المعتمد */
$reg = array();
foreach ($rows("SELECT r.screen_file, r.route, r.owner_code, r.canonical_label_ar, d.name_ar
                  FROM repair01_screen_registry r
                  LEFT JOIN repair01_departments d ON d.canonical_code = r.owner_code") as $r) {
    $bn = strtolower(basename((string) $r['route'] ?: (string) $r['screen_file']));
    if ($bn !== '') { $reg[$bn] = $r; }
}

$navRoutes = $rows("SELECT DISTINCT n.route, n.label_ar, n.role_id, g.name AS group_name
                      FROM nav_items n LEFT JOIN link_groups g ON g.id = n.group_id
                     WHERE n.active = 1 AND COALESCE(n.route,'') <> ''");
$need = array(); $unresolved = array();
foreach ($navRoutes as $n) {
    $route = (string) $n['route'];
    $path  = preg_replace('~[?#].*$~', '', $route);
    $bn    = strtolower(basename($path));
    if ($bn === '' || substr($bn, -4) !== '.php') { continue; }
    $missCycle = !isset($ownedCycle[$bn]);
    $missSpace = !isset($inSpace[$bn]);
    if (!$missCycle && !$missSpace) { continue; }
    if (isset($need[$bn])) { continue; }
    if ($ONLY !== null && $bn !== $ONLY) { continue; }

    if ($ONLY !== null && $bn !== $ONLY) { continue; }
    if (!isset($reg[$bn]) || trim((string) $reg[$bn]['name_ar']) === '') {
        $unresolved[$bn] = $route . ' — '
            . (isset($reg[$bn]) ? 'مسجَّلٌ برمزِ مالكٍ `' . $reg[$bn]['owner_code'] . '` بلا اسمِ إدارةٍ قانونيّة'
                                : 'غيرُ مسجَّلٍ في `repair01_screen_registry`');
        continue;
    }
    $deptCanon = (string) $reg[$bn]['name_ar'];
    $k = ar_norm($deptCanon);
    $cyc = isset($vocabCycle[$k]) ? array_keys($vocabCycle[$k]) : array();
    $spc = isset($vocabSpace[$k]) ? array_keys($vocabSpace[$k]) : array();
    if (count($cyc) !== 1 || count($spc) !== 1) {
        $unresolved[$bn] = $route . ' — الاسمُ `' . $deptCanon . '` يقابل '
            . count($cyc) . ' هجاءً في `dept_name` و' . count($spc) . ' في `space_ar`'
            . ' ⇒ **لا يُكتب هجاءٌ ثالثٌ ولا يُختار بالظنّ**';
        continue;
    }
    $need[$bn] = array(
        'route' => $route, 'base' => $bn,
        'label' => (string) ($reg[$bn]['canonical_label_ar'] ?: $n['label_ar']),
        'group' => (string) $n['group_name'],
        'owner_code' => (string) $reg[$bn]['owner_code'],
        'dept_cycle' => $cyc[0], 'space_ar' => $spc[0],
        'miss_cycle' => $missCycle, 'miss_space' => $missSpace,
    );
}

echo "══ ربطُ المساراتِ الحيّةِ بسجلَّي الملاحة ══\n";
printf("  مساراتُ تنقّلٍ نشطة: **%d** · تحتاج تسجيلًا: **%d** · غيرُ محسومةٍ: **%d**\n\n",
    count($navRoutes), count($need), count($unresolved));

foreach ($need as $bn => $x) {
    printf("  + %s\n", $x['route']);
    printf("      المالك: %s ⇐ `%s` · المجموعة: %s\n", $x['dept_cycle'], $x['owner_code'], $x['group']);
    printf("      ينقصه: %s%s\n",
        $x['miss_cycle'] ? '`gov_screen_cycle` ' : '',
        $x['miss_space'] ? '`gov_space_appearances`' : '');
}
foreach ($unresolved as $bn => $why) { echo "  ⛔ غيرُ محسوم: $why\n"; }

if (!$need) { echo "\nلا مسارَ يحتاج تسجيلًا.\n"; exit(count($unresolved) ? 1 : 0); }
if (!$APPLY) { echo "\nقياسٌ فقط — أعِد التشغيلَ بـ`--apply` للكتابة.\n"; exit(0); }

/* ═══ التسجيل ═════════════════════════════════════════════════════════════ */
$okC = 0; $okS = 0; $errs = array();
foreach ($need as $bn => $x) {
    if ($x['miss_cycle']) {
        $sql = "INSERT INTO gov_screen_cycle
            (company_id, dept_name, layer_name, stage_order, stage_name, group_name,
             screen_title, screen_file, inputs_note, output_doc, resp_role, next_state,
             consumers, fin_impact, stage_kind)
            VALUES (0, '" . $e($x['dept_cycle']) . "', 'دورة الإدارة', '0',
                    '" . $e($x['group']) . "', '" . $e($x['group']) . "',
                    '" . $e($x['label']) . "', '" . $e($x['route']) . "',
                    'مُسجَّلٌ بربطِ الملاحة — RPR-02 §٦ الخطوة ⑦', '—',
                    '" . $e($x['dept_cycle']) . "', '—', '—',
                    'لا — سطحُ قراءةٍ في دورةِ إدارتِه', 'contextual')";
        if ($conn->query($sql)) { $okC++; } else { $errs[] = 'cycle ' . $bn . ': ' . $conn->error; }
    }
    if ($x['miss_space']) {
        $sql = "INSERT INTO gov_space_appearances
            (space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
             src_class, src_ownership, src_decision, src_note, spaces_count,
             cls, ownership, decision, basis, rule_step, view_fields, updated_at)
            VALUES ('" . $e($x['space_ar']) . "', 'DEPARTMENT', '" . $e($x['group']) . "',
                    '" . $e($x['label']) . "', '" . $e($x['route']) . "',
                    '" . $e($x['space_ar']) . "', 'BUSINESS_DEPARTMENT',
                    'OWNED', 'VALID', 'CONFIRMED',
                    'أصليةٌ لهذه الإدارة — مالكُها في السجلِّ الرسميّ " . $e($x['owner_code']) . "',
                    1, 'OWNED', 'VALID', 'CONFIRMED',
                    'المساحةُ هي المالكُ: السجلُّ الرسميُّ يُسند السطحَ إلى " . $e($x['owner_code'])
                    . " — وبندُ الملاحةِ يظهر في مساحتِه هو',
                    1, '', NOW())";
        if ($conn->query($sql)) { $okS++; } else { $errs[] = 'space ' . $bn . ': ' . $conn->error; }
    }
}
printf("\n✔ `gov_screen_cycle`: **%d** · `gov_space_appearances`: **%d**\n", $okC, $okS);
foreach (array_slice($errs, 0, 6) as $x) { echo "  ⛔ $x\n"; }
exit(($errs || $unresolved) ? 1 : 0);
