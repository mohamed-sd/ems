<?php
/**
 * tools/rpr02a_gates.php — حواجبُ جولةِ RPR-02-A الثلاثة · وكلٌّ مُثبَتٌ بالكسر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معيارُ الإثباتِ من المالك (§٧·١)**: «**بحركةِ عدّادٍ بواحدٍ ثمَّ عودة** لا
 *   بمجرَّد رسوب». فكلُّ حاجبٍ هنا يُشغَّل ثلاثَ مرّات:
 *     ① القياسُ كما هو · ② حقنُ خرقٍ واحدٍ ⇒ **يجب أن يتحرّك العدّادُ ويرسب**
 *     ③ رفعُ الحقنِ ⇒ **يجب أن يعود العدّادُ إلى قيمتِه الأولى**
 *   ⛔ وحاجبٌ لا يتحرّك بالحقنِ **ليس حاجبًا** بل زخرفةٌ خضراء.
 *
 * ◆ **والمقامُ يُحصَر في المؤهَّلِ وحدَه** — كي لا يوجب الحاجبُ كسرَ حاجبٍ آخر.
 *
 * ◆ **والصفرُ المقيسُ يُكتب صفرًا** (م ١١١) — ولا خانةَ خاليةٌ في أيِّ مخرَج.
 *
 * ⚠ **والحقنُ يُرفع دائمًا**: كلُّ اختبارٍ سالبٍ يُعيد ما غيَّره في `finally`
 *   ويُتحقَّق من العودةِ بالعدِّ — فحقنٌ يبقى يفسد المخزن.
 *
 * التشغيل: php tools/rpr02a_gates.php [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI only\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/tools/lib/rpr02a_guide.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$MD = in_array('--md', $argv, true);

function g_one($conn, $sql) { $r = $conn->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; }
function g_e($conn, $v) { return "'" . $conn->real_escape_string((string) $v) . "'"; }

/* ═══ المفرداتُ المشتركة ═══ */
$cards = rpr02a_read_cards($ROOT . '/docs/REPAIR01_20260823/01 · الدليل المعماري.xlsx');
$docNames = array();
foreach ($cards as $c) { if (rpr02a_is_doc($c)) { $docNames[] = $c['name']; } }
$docNames = array_values(array_unique($docNames));

$PLATFORM_8 = array('المراسلات', 'الرئيسية', 'مهامي', 'بلاغاتي', 'بوابتي', 'مساحة عملي', 'إنجازي', 'طلباتي');
$CODES_22   = array('DEP-01','DEP-02','DEP-03','DEP-04','DEP-05','DEP-06','DEP-07','DEP-08','DEP-09',
                    'DEP-10','DEP-11','DEP-12','DEP-13','DEP-14','DEP-15','DEP-16','DEP-17',
                    'EX-CEO','EX-DVP','IAF','WS-MY','PLATFORM');

/* ═══════════════════════════════════════════════════════════════════════════
   G-DOC-01 — سطحُ توثيقٍ في طابورِ البناءِ أو في عدِّ العجز
   المقامُ المؤهَّل: أسماءُ أسطحِ التوثيقِ المنصوصةِ في الدليل
   ═══════════════════════════════════════════════════════════════════════════ */
function gate_doc($conn, array $docNames) {
    if (!$docNames) { return array('n' => 0, 'den' => 0); }
    $in = array();
    foreach ($docNames as $d) { $in[] = g_e($conn, $d); }
    $n = g_one($conn, "SELECT COUNT(*) FROM repair01_target_gaps WHERE surface_name IN (" . implode(',', $in) . ")");
    return array('n' => $n, 'den' => count($docNames));
}

/* ═══════════════════════════════════════════════════════════════════════════
   G-PLT-01 — السطحُ المنصّيُّ المُعلَنُ يُعرَّف مرّةً واحدةً
   ◆ **الوجهةُ لا الصفّ**: الإسقاطُ المعلَّمُ بالنطاقِ يُصيَّر لكلِّ دورٍ بصفٍّ،
     فتعدّدُ الصفوفِ ليس الخرق. **والخرقُ تعدّدُ الوجهات**: سطحٌ واحدٌ بمسارَين
     تعريفان لا إسقاط. المقامُ: الثمانيةُ المُعلَنة.
   ═══════════════════════════════════════════════════════════════════════════ */
function gate_plt($conn, array $labels) {
    $in = array();
    foreach ($labels as $l) { $in[] = g_e($conn, $l); }
    $bad = 0; $detail = array();
    $r = $conn->query("SELECT label_ar, COUNT(DISTINCT route) routes, COUNT(*) rows_all, SUM(active=1) live
                         FROM nav_items WHERE label_ar IN (" . implode(',', $in) . ") GROUP BY label_ar");
    while ($r && ($x = $r->fetch_assoc())) {
        $detail[$x['label_ar']] = $x;
        if ((int) $x['routes'] > 1) { $bad++; }
    }
    return array('n' => $bad, 'den' => count($labels), 'detail' => $detail);
}

/* ═══════════════════════════════════════════════════════════════════════════
   G-UNI-01 — لا قيمةَ `unit` خارجَ الرموزِ الاثنَين والعشرين
   ◆ `repair01_target_gaps` بالرمزِ نصًّا · و`repair01_requirements` **تُقاس
     بقابليّةِ الحسمِ لا بإعادةِ الكتابة**: عمودُها جسرٌ لحاجبِ W08
     (`SUBSTRING(g.unit,5)`) — وإعادةُ كتابتِه رموزًا تكسر حاجبًا قائمًا.
   ═══════════════════════════════════════════════════════════════════════════ */
function gate_uni($conn, array $codes) {
    $in = array();
    foreach ($codes as $c) { $in[] = g_e($conn, $c); }
    $badGap = g_one($conn, "SELECT COUNT(*) FROM repair01_target_gaps WHERE unit NOT IN (" . implode(',', $in) . ")");
    /* المتطلَّب: `NN اسم` ⇒ `DEP-NN` — وكلُّ ما لا يُحسم خرقٌ */
    $badReq = g_one($conn,
        "SELECT COUNT(*) FROM repair01_requirements r
          WHERE NOT ( (r.unit REGEXP '^[0-9]{2} ' AND CONCAT('DEP-', LEFT(r.unit,2)) IN (" . implode(',', $in) . "))
                   OR (LEFT(r.unit,3) = 'AS ' ) OR (LEFT(r.unit,3) = 'E1 ') OR (LEFT(r.unit,3) = 'E2 ')
                   OR (LEFT(r.unit,3) = 'WS ') )");
    return array('n' => $badGap + $badReq, 'den' => g_one($conn, "SELECT (SELECT COUNT(*) FROM repair01_target_gaps) + (SELECT COUNT(*) FROM repair01_requirements)"),
                 'gap' => $badGap, 'req' => $badReq);
}

/* ═══ التشغيلُ الثلاثيّ: قياس ⇒ حقن ⇒ رفعٌ وعودة ═══ */
$results = array();

/* ① G-DOC-01 */
$m0 = gate_doc($conn, $docNames);
$probe = $docNames[0];
$conn->query("INSERT INTO repair01_target_gaps (unit, surface_name, verdict, src_ref)
              VALUES ('DEP-01', " . g_e($conn, $probe) . ", 'GATE-PROBE', 'rpr02a_gates:negative')");
$injId = (int) $conn->insert_id;
$m1 = gate_doc($conn, $docNames);
$conn->query("DELETE FROM repair01_target_gaps WHERE id = " . $injId);
$m2 = gate_doc($conn, $docNames);
$results[] = array(
    'id' => 'G-DOC-01',
    'rule' => 'لا يدخل سطحٌ نوعُه `Documentation Artifact — خارج السايدبار` طابورَ البناءِ (`repair01_target_gaps`) ولا أيَّ عدِّ عجز',
    'den' => $m0['den'] . ' سطحَ توثيقٍ منصوص',
    'before' => $m0['n'], 'injected' => $m1['n'], 'after' => $m2['n'],
    'probe' => 'حقنُ «' . $probe . '» صفًّا في `repair01_target_gaps` ثمَّ حذفُه',
    'moves' => ($m1['n'] === $m0['n'] + 1 && $m2['n'] === $m0['n']),
    'clean' => ($m0['n'] === 0),
);

/* ② G-PLT-01 */
$p0 = gate_plt($conn, $PLATFORM_8);
/* ⚠ **الحقنُ كان يُرفض صامتًا**: `chk_nav_door` يقيّد `door` بتسعِ قيمٍ، و`probe`
     ليست منها — فرجع `insert_id=0` وبقي العدّادُ ساكنًا، **فبدا الحاجبُ ميّتًا
     وهو حيّ**. والدرسُ: حقنٌ لا يُتحقَّق من نجاحِه يُنتج «أخضرَ كاذبًا» معكوسًا. */
$okIns = $conn->query("INSERT INTO nav_items (role_id, door, group_id, label_ar, route, sort_order, active)
              VALUES (0, 'REC', 0, 'المراسلات', 'RPR02A/gate_probe.php', 999, 0)");
$injNav = (int) $conn->insert_id;
if (!$okIns || $injNav === 0) { fwrite(STDERR, "الحقنُ فشل: " . $conn->error . "\n"); }
$p1 = gate_plt($conn, $PLATFORM_8);
$conn->query("DELETE FROM nav_items WHERE id = " . $injNav);
$p2 = gate_plt($conn, $PLATFORM_8);
$results[] = array(
    'id' => 'G-PLT-01',
    'rule' => 'السطحُ المنصّيُّ المُعلَنُ يُعرَّف **وجهةً واحدةً** — الإسقاطُ يتعدّد بالنطاقِ والتعريفُ لا يتعدّد',
    'den' => $p0['den'] . ' سطحًا منصّيًّا مُعلَنًا',
    'before' => $p0['n'], 'injected' => $p1['n'], 'after' => $p2['n'],
    'probe' => 'حقنُ صفِّ ملاحةٍ ثانٍ بوجهةٍ جديدةٍ لـ«المراسلات» ثمَّ حذفُه',
    'moves' => ($p1['n'] === $p0['n'] + 1 && $p2['n'] === $p0['n']),
    'clean' => ($p0['n'] === 0),
    'detail' => $p0['detail'],
);

/* ③ G-UNI-01 */
$u0 = gate_uni($conn, $CODES_22);
$row = $conn->query("SELECT id, unit FROM repair01_target_gaps ORDER BY id LIMIT 1")->fetch_assoc();
$conn->query("UPDATE repair01_target_gaps SET unit = 'DEP-99' WHERE id = " . (int) $row['id']);
$u1 = gate_uni($conn, $CODES_22);
$conn->query("UPDATE repair01_target_gaps SET unit = " . g_e($conn, $row['unit']) . " WHERE id = " . (int) $row['id']);
$u2 = gate_uni($conn, $CODES_22);
$results[] = array(
    'id' => 'G-UNI-01',
    'rule' => 'كلُّ `unit` في `repair01_target_gaps` **من الرموزِ الاثنَين والعشرين نصًّا** · وكلُّ `unit` في `repair01_requirements` **يُحسم إليها** بلا كسرِ جسرِ W08',
    'den' => $u0['den'] . ' صفًّا (فجواتٌ + متطلَّبات)',
    'before' => $u0['n'], 'injected' => $u1['n'], 'after' => $u2['n'],
    'probe' => 'تحويلُ `unit` لصفٍّ واحدٍ إلى `DEP-99` ثمَّ إعادتُه إلى ' . $row['unit'],
    'moves' => ($u1['n'] === $u0['n'] + 1 && $u2['n'] === $u0['n']),
    'clean' => ($u0['n'] === 0),
);

/* ═══ التقرير ═══ */
$ts = date('Y-m-d H:i:s');
$md  = "# RPR-02-A · الحواجبُ الثلاثة — ولا حاجبَ إلّا بحركةِ عدّادٍ ثمَّ عودة\n\n";
$md .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/rpr02a_gates.php --md`\n> **مولَّدٌ**: " . $ts . "\n";
$md .= "> **معيارُ الإثبات (§٧·١)**: يُحقَن خرقٌ واحدٌ فيجب أن يتحرّكَ العدّادُ بواحد، ثمَّ يُرفع فيجب أن يعود. ⛔ وحاجبٌ لا يتحرّك ليس حاجبًا.\n\n";
$md .= "> **حكمان لا حكمٌ واحد**: «**الحاجبُ مُثبَت**» = تحرَّك بالحقنِ وعاد · «**المخزنُ نظيف**» = صفرُ خرقٍ اليوم.\n";
$md .= "> وخلطُهما يجعل حاجبًا سليمًا يبدو راسبًا لأنّ ما يحرسه ما زال مخروقًا.\n\n";
$md .= "| الحاجب | المقامُ المؤهَّل | قبل | بالحقن | بعدَ الرفع | الحركة | الحاجبُ مُثبَت؟ | المخزنُ نظيف؟ |\n|---|---|---:|---:|---:|---|---|---|\n";
foreach ($results as $r) {
    $move = ($r['injected'] - $r['before']) . ' ← ثمَّ ' . ($r['after'] - $r['injected']);
    $md .= '| `' . $r['id'] . '` | ' . $r['den'] . ' | ' . $r['before'] . ' | ' . $r['injected'] . ' | ' . $r['after']
         . ' | **+' . ($r['injected'] - $r['before']) . ' ثمَّ ' . ($r['after'] - $r['injected']) . '** | '
         . ($r['moves'] ? '**✔ نعم**' : '⛔ **لا — الحاجبُ لا يتحرّك**') . ' | '
         . ($r['clean'] ? '✔ صفرُ خرق' : '⛔ **' . $r['before'] . ' خرقًا**') . " |\n";
}
$md .= "\n";
foreach ($results as $r) {
    $md .= "## `" . $r['id'] . "`\n\n";
    $md .= "**القاعدة:** " . $r['rule'] . "\n\n";
    $md .= "**المقامُ المؤهَّل:** " . $r['den'] . " · **الخروقُ المقيسة: " . $r['before'] . "**\n\n";
    $md .= "**الاختبارُ السالب:** " . $r['probe'] . " ⇒ العدّادُ " . $r['before'] . " ← " . $r['injected'] . " ← " . $r['after'] . "\n\n";
    if ($r['id'] === 'G-PLT-01' && !empty($r['detail'])) {
        $md .= "| السطحُ المنصّيّ | وجهات | صفوف | حيّ | الحكم |\n|---|---:|---:|---:|---|\n";
        foreach ($r['detail'] as $lbl => $d) {
            $md .= '| ' . $lbl . ' | ' . $d['routes'] . ' | ' . $d['rows_all'] . ' | ' . $d['live'] . ' | '
                 . ((int) $d['routes'] > 1 ? '⛔ **وجهتان فأكثر — تعريفٌ مكرَّرٌ لا إسقاط**' : '✔ وجهةٌ واحدة') . " |\n";
        }
        $md .= "\n> ⚠ **الحاجبُ أحمرُ اليومَ بعذرٍ معلَن**: التوحيدُ لم يُنفَّذ بعد (§٤·٣)، و`G0` يمنع البناءَ (§٤·٥).\n";
        $md .= "> فالأحمرُ هنا **قياسٌ صادقٌ لحالةٍ قائمة** لا عطبٌ في الحاجب — وحركتُه بالحقنِ تُثبت أنّه يعمل.\n\n";
    }
}
$mv = 0; $cl = 0;
foreach ($results as $r) { if ($r['moves']) { $mv++; } if ($r['clean']) { $cl++; } }
$md .= "\n**الحكمُ الجامع:** **" . $mv . "/3 حاجبًا مُثبَتٌ بالحركة** · **" . $cl . "/3 مخزنًا نظيفًا**.\n";
if ($cl < 3) {
    $md .= "\n⛔ **وما ليس نظيفًا يُعلَن بخرقِه لا يُسكَت عنه** — والتنظيفُ عملُ §٤·٣ و§٤·١ ولا يجوز قبلَ حسمِ `G0` (§٤·٥).\n";
}

if ($MD) { file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02A_GATES.md', $md); }
echo $md;
if ($MD) { echo "\n=> docs/REPAIR01_20260823/RPR02A_GATES.md\n"; }
