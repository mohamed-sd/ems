<?php
/**
 * tools/repair01_w7_classify.php — تصنيفُ أسطحِ W07 في سجلِّ المساحات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا**: W07 تبني أحدَ عشرَ سطحًا، وكلُّ مسارٍ نشِطٍ بلا صفٍّ في
 *   `gov_space_appearances` يرفع دَينَ `NF-24` وتمنع السقّاطةُ الالتزام. والسقّاطةُ محقّة:
 *   المسارُ خارجَ سجلِّ التصنيف **مفتوحٌ افتراضًا**.
 *
 * ◆ **ولا يُصنَّف بالرأي**: يُطبَّق سلّمُ الحسمِ السداسيُّ نفسُه القائمُ في
 *   السجلّ، وسندُ الخطوةِ الخامسةِ **عقودُ الأثرِ المقيسةُ في `repair01_events`**
 *   — الإدارةُ التي أُعلنت مستهلكًا لحدثٍ تصدره الشاشةُ تقرأ ما تكتبه فعلًا.
 *   وما لا سندَ له يُوسَم `FORBIDDEN` **تسجيلًا لا إنفاذًا**: نزعُ الظهورِ
 *   تغييرُ وصولٍ حيٍّ بقرارِ مالكٍ (نمطُ `W2-D-03`).
 *
 * ◆ **والمراوغةُ مرفوضة**: السقّاطةُ تقيس بالمسار، فصفٌّ واحدٌ لكلٍّ يُخضِرُّها.
 *   وهنا يُصنَّف **كلُّ ظهورٍ حقيقيٍّ** في كلِّ مساحةٍ لها جسرٌ في
 *   `gov_space_roles` — والأدوارُ بلا مساحةٍ تُسجَّل قرارًا لا تُهمَل.
 *
 * التشغيل: php tools/repair01_w7_classify.php [--dry]
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
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$DRY = in_array('--dry', $argv, true);

function e7($conn, $v) { return $conn->real_escape_string((string) $v); }
function one7($conn, $sql) { $r = $conn->query($sql); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; }

/* ═══ ① أسطحُ النموِّ المختومةُ W05 ═══ */
$screens = array();
$r = $conn->query("SELECT screen_id, route, owner_code FROM repair01_screen_registry WHERE origin = 'W07'");
while ($r && $x = $r->fetch_assoc()) { $screens[$x['route']] = $x; }
if (!$screens) { exit("لا أسطحَ مختومةً W05\n"); }
echo "① أسطحُ النموّ: " . count($screens) . "\n";

/* ═══ ② المستهلكونَ المقيسونَ من عقودِ الأثر — سندُ الخطوةِ الخامسة ═══ */
$consumersOf = array();   /* route => array(كودُ الإدارة => نصُّ السند) */
$r = $conn->query("SELECT source_screen, event_code, consumers FROM repair01_events WHERE contract_stage = 'W07'");
while ($r && $x = $r->fetch_assoc()) {
    if (!preg_match('~([A-Za-z]+/[A-Za-z0-9_]+\.php)~', (string) $x['source_screen'], $sm)) { continue; }
    $route = $sm[1];
    foreach (explode('·', (string) $x['consumers']) as $c) {
        if (!preg_match('/@\s*(\d{2}|AS|E1|E2|WS)\s/u', $c, $dm)) { continue; }
        $dep = 'DEP-' . $dm[1];
        if (!isset($consumersOf[$route][$dep])) {
            $consumersOf[$route][$dep] = 'مستهلكٌ مُعلَنٌ في عقدِ الأثر ' . trim($x['event_code']) . ': ' . trim($c);
        }
    }
}
echo "② عقودُ أثرٍ مقروءة: " . array_sum(array_map('count', $consumersOf)) . " نسبةَ استهلاك\n";

/* ═══ ③ جسرُ الدورِ إلى المساحةِ ونوعِها ═══ */
$spaceOf = array(); $kindOf = array();
$r = $conn->query("SELECT role_id, space_ar FROM gov_space_roles");
while ($r && $x = $r->fetch_assoc()) { $spaceOf[(int) $x['role_id']] = $x['space_ar']; }
$r = $conn->query("SELECT DISTINCT space_ar, space_kind FROM gov_space_appearances WHERE space_kind <> ''");
while ($r && $x = $r->fetch_assoc()) { $kindOf[$x['space_ar']] = $x['space_kind']; }

/* المساحةُ المالكةُ لكلِّ رمزِ إدارة — بالتسميةِ المستعملةِ في هذا السجلِّ حرفًا */
$OWNER_SPACE = array(
    'DEP-04' => 'ادارة الاسطول',
    'DEP-13' => 'القوى التشغيلية',
    'DEP-11' => 'ادارة التشغيل',
    'DEP-12' => 'إدارة الموقع',
    'DEP-14' => 'ادارة الصيانة',
    'DEP-03' => 'إدارة التمويل',
    'DEP-05' => 'إدارة المالية',
    'DEP-07' => 'ادارة الموارد البشرية',
    'DEP-15' => 'إدارة النقل والترحيل',
);
$SPACE_DEP = array_flip($OWNER_SPACE);

/* ═══ ④ الظهوراتُ الحيّة ═══ */
$rows = array(); $noSpace = array();
$in = "'" . implode("','", array_map(function ($k) use ($conn) { return e7($conn, $k); }, array_keys($screens))) . "'";
$r = $conn->query("SELECT DISTINCT n.role_id, n.route, n.label_ar, n.group_id
                   FROM nav_items n WHERE n.active = 1 AND n.route IN ($in)");
while ($r && $x = $r->fetch_assoc()) {
    $rid = (int) $x['role_id'];
    if (!isset($spaceOf[$rid])) { $noSpace[$rid][] = $x['route']; continue; }
    $rows[] = array('role' => $rid, 'space' => $spaceOf[$rid], 'route' => $x['route'], 'label' => $x['label_ar']);
}
echo "④ ظهوراتٌ بمساحة: " . count($rows) . " · أدوارٌ بلا مساحة: " . count($noSpace) . "\n\n";

/* ═══ ⑤ سلّمُ الحسمِ السداسيّ ═══ */
$out = array(); $tally = array();
foreach ($rows as $w) {
    $sc    = $screens[$w['route']];
    $owner = $sc['owner_code'];
    $ownSp = isset($OWNER_SPACE[$owner]) ? $OWNER_SPACE[$owner] : '';
    $kind  = isset($kindOf[$w['space']]) ? $kindOf[$w['space']] : 'DEPARTMENT';
    $spDep = isset($SPACE_DEP[$w['space']]) ? $SPACE_DEP[$w['space']] : '';

    if ($w['space'] === $ownSp) {
        $cls = 'OWNED'; $step = 1;
        $basis = 'المساحةُ هي الإدارةُ المالكةُ للسطحِ في السجلِّ المعياريّ (' . $owner . ')';
    } elseif ($kind === 'PERSONAL') {
        $cls = 'PERSONAL_SPACE'; $step = 2;
        $basis = 'مساحةٌ شخصيّةٌ — إسقاطٌ لا مِلكية';
    } elseif ($kind === 'CONTROL') {
        $cls = 'CONTROL_OVERSIGHT'; $step = 4;
        $basis = 'مساحةٌ رقابية — اطّلاعٌ موسومٌ لا يعدّل مستندَ المالكِ ولا يُحسب مِلكية';
    } elseif ($kind === 'EXECUTIVE') {
        $cls = 'EXECUTIVE_OVERSIGHT'; $step = 4;
        $basis = 'مساحةٌ تنفيذية — مجمَّعٌ أولًا وتعمُّقٌ مصرَّحٌ ثانيًا';
    } elseif ($spDep !== '' && isset($consumersOf[$w['route']][$spDep])) {
        $cls = 'CONTEXTUAL_READ_ONLY'; $step = 5;
        $basis = 'استهلاكٌ **مقيسٌ** لا مُدَّعى — ' . $consumersOf[$w['route']][$spDep];
    } else {
        $cls = 'FORBIDDEN'; $step = 6;
        $basis = 'لا عقدةَ سابقةً تنطبق: ليست مالكةً ولا شخصيّةً ولا رقابيةً ولا تنفيذيّةً '
               . 'ولا تستهلك حدثًا تصدره هذه الشاشةُ في عقودِ الأثرِ المقيسة — '
               . 'يُسجَّل ولا يُنفَّذ: نزعُ الظهورِ تغييرُ وصولٍ حيٍّ بقرارِ مالك';
    }
    $tally[$cls] = (isset($tally[$cls]) ? $tally[$cls] : 0) + 1;
    $out[] = array($w, $cls, $step, $basis, $ownSp);
}

/* ═══ ⑥ الكتابة ═══
   ⚠ `gov_space_appearances.id` مفتاحٌ أساسيٌّ **بلا ترقيمٍ ذاتيّ** — فالإدراجُ
      بلا معرِّفٍ صريحٍ يأخذ صفرًا ويتصادم من الصفِّ الثاني. يُرقَّم هنا صراحةً. */
$nextId = (int) one7($conn, "SELECT COALESCE(MAX(id), 0) + 1 FROM gov_space_appearances");
$ins = 0; $upd = 0;
foreach ($out as $o) {
    list($w, $cls, $step, $basis, $ownSp) = $o;
    $spCount = (int) one7($conn, "SELECT COUNT(DISTINCT space_ar) FROM gov_space_appearances
                                  WHERE route = '" . e7($conn, $w['route']) . "'");
    $spCount = $spCount + 1;
    $exists = (int) one7($conn, "SELECT COUNT(*) FROM gov_space_appearances
                                 WHERE space_ar = '" . e7($conn, $w['space']) . "'
                                   AND route = '" . e7($conn, $w['route']) . "'");
    $kind = isset($kindOf[$w['space']]) ? $kindOf[$w['space']] : 'DEPARTMENT';
    $sql = $exists
      ? "UPDATE gov_space_appearances SET cls='" . e7($conn, $cls) . "', ownership='VALID', decision='CONFIRMED',
           basis='" . e7($conn, $basis) . "', rule_step=" . (int) $step . ", updated_at=NOW()
         WHERE space_ar='" . e7($conn, $w['space']) . "' AND route='" . e7($conn, $w['route']) . "'"
      : "INSERT INTO gov_space_appearances
           (id,space_ar,space_kind,tab_ar,screen_ar,route,owner_dept_ar,owner_kind,
            src_class,src_ownership,src_decision,src_note,spaces_count,cls,ownership,decision,basis,rule_step,view_fields,updated_at)
         VALUES (" . (int) $nextId . ",'" . e7($conn, $w['space']) . "','" . e7($conn, $kind) . "','','" . e7($conn, $w['label']) . "',
                 '" . e7($conn, $w['route']) . "','" . e7($conn, $ownSp) . "','BUSINESS_DEPARTMENT',
                 'RPR-W07','VALID','CONFIRMED','سطحُ نموٍّ مختومٌ W07 — صُنِّف بسلّمِ الحسمِ السداسيّ',
                 " . (int) $spCount . ",'" . e7($conn, $cls) . "','VALID','CONFIRMED',
                 '" . e7($conn, $basis) . "'," . (int) $step . ",'',NOW())";
    if ($DRY) { continue; }
    if ($conn->query($sql) === false) { echo "  ✘ {$w['space']} × {$w['route']}: {$conn->error}\n"; continue; }
    if ($exists) { $upd++; } else { $ins++; $nextId++; }
}

echo "⑤ التصنيف:\n";
ksort($tally);
foreach ($tally as $k => $v) { printf("   %-24s %d\n", $k, $v); }
echo "\n⑥ الكتابة: أُدرج $ins · حُدِّث $upd" . ($DRY ? '  (تجربةٌ جافّة)' : '') . "\n";

if ($noSpace) {
    echo "\n⚠ أدوارٌ بلا جسرٍ في gov_space_roles — تُسجَّل قرارًا ولا تُخمَّن:\n";
    foreach ($noSpace as $rid => $rts) {
        $nm = one7($conn, "SELECT name FROM roles WHERE id = " . (int) $rid);
        printf("   دور %-3d %-22s · %d ظهورًا\n", $rid, (string) $nm, count($rts));
    }
}
