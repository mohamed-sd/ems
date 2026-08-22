<?php
/**
 * tools/injfrd66_nav_gate.php — بوابةُ XC-01: القائمةُ المُصيَّرةُ مقابلَ الجدولِ المستهدف
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار** (INJ-FRD-01 · XC-01): «القائمةُ المُصيَّرةُ مطابقةٌ للجدولِ
 *   المستهدفِ بندًا بندًا» — ستُّ مجموعاتٍ وثلاثةَ عشرَ بندًا للمبيعات (دور 12)،
 *   وستٌّ وأربعةَ عشرَ للموردين (دور 2). والمصدرُ `gov_target_nav` لا قائمةٌ في
 *   الكود.
 *
 * ◆ **والقياسُ بجلسةِ مستخدمٍ حقيقيٍّ** لكلِّ دور — فتسري بواباتُ المنحِ
 *   fail-closed كما تسري على المستخدمِ الفعلي، لا بدورٍ مجرَّد.
 *
 * ◆ **محوران يُقاسان منفصلَين — وخلطُهما يُضخِّم الرقمَ ولا يقيسه**:
 *   ① **محورُ الاسم** — اسمُ البندِ المُصيَّرِ مقابلَ `item_ar`. ويُستثنى منه
 *      السطحُ الذي **لا تملكه** إدارةُ الوثيقة: سابقةُ هجرة `2027_10_06`
 *      المقيَّدةُ في القاعدة قضت بأنَّ «وثيقةَ إدارةٍ لا تملك تسميةَ سطحٍ
 *      مشترك»، وأنَّ تسميتَها لبندٍ في مجموعتِها وصفٌ لدورِه لا اسمٌ كنسيّ.
 *   ② **محورُ المجموعة** — وهو **قرارُ بنيةٍ لا تسمية**، ويُقاس ولا يُحكم عليه
 *      بالرسوب: `gov_target_nav` يصف مجموعاتٍ **لكلِّ دور**، بينما رأسُ الطيِّ
 *      المُصيَّرَ يأتي من `nav_route_group` وهو **مفتاحُه المسارُ وحدَه**
 *      (472 صفًّا · 472 مسارًا · لا `role_id` فيه) ضمن تصنيفٍ مشتركٍ سقفُه
 *      اثنتا عشرةَ مجموعةً لكلِّ الأدوار. فالنموذجان مختلفان بنيويًّا،
 *      وتسويتُهما قرارُ معماريةٍ لا تُنتحَل في بوابةِ قياس.
 *
 * ◆ قراءةٌ خالصة — لا كتابةَ في القاعدةِ إطلاقًا.
 *
 * التشغيل:
 *   php tools/injfrd66_nav_gate.php            التقريرُ التفصيلي
 *   php tools/injfrd66_nav_gate.php --gate     رمزُ خروجٍ 1 عند خرقٍ في محورِ الاسم
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME']    = '/ems/main/dashboard.php';
$_SERVER['REQUEST_URI']    = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
while (ob_get_level() > 0) { ob_end_clean(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$GATE = in_array('--gate', $argv, true);

/* إدارةُ كلِّ وثيقةٍ — من يملك التسمية (النسخةُ نفسُها في هجرة 2027_10_27) */
$DOC_DEPT = array(
    'INJ-SAL-ALIGN-01' => 'المبيعات والعقود',
    'INJ-SUP-ALIGN-01' => 'إدارة الموردين',
);
$CROSS_OWNED = array('contracts/contract_coverage.php' => 'SAL-15 + SUP-05');

/* ── الجدولُ المستهدفُ مع ملكيةِ كلِّ سطح ─────────────────────────────── */
$target = array();
$res = mysqli_query($conn,
    "SELECT t.doc_code, t.role_id, t.group_no, t.group_ar, t.item_no, t.item_ar, t.route,
            k.owner_dept, k.status
       FROM gov_target_nav t
       LEFT JOIN nav_canonical k ON LOWER(k.route) = LOWER(t.route)
      ORDER BY t.role_id, t.group_no, t.item_no");
while ($x = mysqli_fetch_assoc($res)) {
    $routeLc = strtolower(uxp_norm($x['route']));
    $ownerDoc = $DOC_DEPT[$x['doc_code']] ?? null;
    $owns = ($x['owner_dept'] !== null && $ownerDoc !== null && $x['owner_dept'] === $ownerDoc)
         || isset($CROSS_OWNED[strtolower($x['route'])]);
    $target[(int) $x['role_id']][] = array(
        'g' => (int) $x['group_no'], 'gn' => $x['group_ar'],
        'i' => (int) $x['item_no'],  'item' => $x['item_ar'],
        'route' => $x['route'], 'norm' => $routeLc,
        'owns' => $owns, 'owner' => $x['owner_dept'], 'status' => $x['status'],
    );
}
if (!$target) { fwrite(STDERR, "جدولُ التنقّلِ المستهدفُ فارغ\n"); exit(2); }

$roleName = array();
$r = mysqli_query($conn, "SELECT id, name FROM roles");
while ($x = mysqli_fetch_assoc($r)) { $roleName[(int) $x['id']] = $x['name']; }

/* الأسطحُ الشخصيةُ العابرةُ للمنصّة — تُعلَن مُستثناةً ولا تُحذف («لا حذفَ مسار») */
$PLATFORM = array();
$q = mysqli_query($conn, "SELECT route, COUNT(DISTINCT role_id) c FROM nav_items
                           WHERE active = 1 GROUP BY route HAVING c >= 10");
while ($q && ($x = mysqli_fetch_assoc($q))) { $PLATFORM[strtolower(uxp_norm($x['route']))] = (int) $x['c']; }

$users = uxp_role_users($conn);
$nameFail = 0; $summary = array();

foreach ($target as $roleId => $items) {
    $live = uxp_render_role($conn, $roleId, $users[$roleId] ?? null);

    echo str_repeat('═', 78) . "\n";
    printf("دور %d · %s   |   المستهدف %d بندًا في %d مجموعة   |   المُصيَّر %d رابطًا في %d رأسًا\n",
        $roleId, $roleName[$roleId] ?? '—', count($items),
        count(array_unique(array_column($items, 'g'))), count($live),
        count(array_unique(array_column($live, 'group'))));
    echo str_repeat('═', 78) . "\n";

    $liveByRoute = array();
    foreach ($live as $p) { $liveByRoute[strtolower(uxp_norm($p['href']))][] = $p; }

    $ok = array(); $bad = array(); $missing = array(); $exempt = array();

    foreach ($items as $t) {
        $hits = $liveByRoute[$t['norm']] ?? array();
        if (!$hits) { $missing[] = $t; continue; }
        $labels = array_unique(array_column($hits, 'label'));
        $match = in_array($t['item'], $labels, true);

        if ($match) { $ok[] = $t; continue; }
        if (!$t['owns']) {
            $exempt[] = array('t' => $t, 'live' => $labels[0]);
            continue;
        }
        $bad[] = array('t' => $t, 'live' => $labels[0]);
    }

    /* الزائدُ العابرُ للمنصّة — يُعلَن ولا يُعدُّ خرقًا */
    $tNorms = array_flip(array_column($items, 'norm'));
    $extraPlat = array(); $extraOther = array();
    foreach ($live as $p) {
        $n = strtolower(uxp_norm($p['href']));
        if ($n === '' || isset($tNorms[$n])) { continue; }
        if (isset($PLATFORM[$n])) { $extraPlat[$n] = array($p['label'], $PLATFORM[$n]); }
        else { $extraOther[$n] = $p['label']; }
    }

    if ($ok) {
        echo "\n✔ مطابقُ الاسم (" . count($ok) . "):\n";
        foreach ($ok as $t) { printf("   ✔ [%d.%d] %s\n", $t['g'], $t['i'], $t['item']); }
    }
    if ($missing) {
        echo "\n✘ ناقصٌ — بندٌ مستهدَفٌ بلا مقابلٍ حيّ (" . count($missing) . "):\n";
        foreach ($missing as $t) { printf("   ✘ [%d.%d] %-40s ← %s\n", $t['g'], $t['i'], $t['item'], $t['route']); }
        $nameFail += count($missing);
    }
    if ($bad) {
        echo "\n✘ مخالفُ الاسمِ وهو مملوكٌ للإدارة (" . count($bad) . "):\n";
        foreach ($bad as $m) {
            printf("   ✘ [%d.%d] المستهدف «%s» · الحيُّ «%s»\n", $m['t']['g'], $m['t']['i'], $m['t']['item'], $m['live']);
        }
        $nameFail += count($bad);
    }
    if ($exempt) {
        echo "\n○ مُستثنًى — لا تملكه إدارةُ الوثيقة (" . count($exempt) . "):\n";
        foreach ($exempt as $m) {
            printf("   ○ «%s» ← الوثيقةُ تسمّيه «%s» · يملكه «%s»%s\n",
                $m['live'], $m['t']['item'], $m['t']['owner'] ?? '—',
                $m['t']['status'] && $m['t']['status'] !== 'APPROVED' ? " · حالتُه {$m['t']['status']}" : '');
        }
    }
    if ($extraPlat) {
        echo "\n○ زائدٌ عابرٌ للمنصّة — يُعلَن ولا يُحذف (" . count($extraPlat) . "):\n";
        foreach ($extraPlat as $n => $e) { printf("   ○ «%s» حيٌّ في %d دورًا ← %s\n", $e[0], $e[1], $n); }
    }
    if ($extraOther) {
        echo "\n✘ زائدٌ لا أصلَ له في المستهدفِ ولا هو عابرٌ للمنصّة (" . count($extraOther) . "):\n";
        foreach ($extraOther as $n => $l) { printf("   ✘ «%s» ← %s\n", $l, $n); }
        $nameFail += count($extraOther);
    }

    /* ── محورُ المجموعة: يُقاس ويُعرض ولا يُرسِّب ─────────────────────────── */
    echo "\n◆ محورُ المجموعة (قياسٌ لا حكم):\n";
    $byTargetGroup = array();
    foreach ($items as $t) {
        $hits = $liveByRoute[$t['norm']] ?? array();
        $byTargetGroup[$t['gn']][] = $hits ? $hits[0]['group'] : '—';
    }
    foreach ($byTargetGroup as $gn => $heads) {
        $u = array_count_values($heads);
        arsort($u);
        $shown = array();
        foreach ($u as $h => $c) { $shown[] = "{$h}×{$c}"; }
        printf("   · «%s» ⇐ %s\n", $gn, implode(' · ', $shown));
    }

    $summary[$roleId] = array('ok' => count($ok), 'n' => count($items),
        'bad' => count($bad), 'miss' => count($missing), 'ex' => count($exempt));
    echo "\n";
}

echo str_repeat('─', 78) . "\n";
foreach ($summary as $rid => $s) {
    printf("  دور %-3d اسمٌ مطابقٌ %2d/%2d · مخالفٌ مملوك %d · ناقص %d · مُستثنًى %d\n",
        $rid, $s['ok'], $s['n'], $s['bad'], $s['miss'], $s['ex']);
}
printf("\n%s محورُ الاسم: %d خرقًا\n",
    $nameFail === 0 ? '✔' : '✘', $nameFail);
echo "◆ محورُ المجموعةِ مفتوحٌ ببيانِه: `nav_route_group` مفتاحُه المسارُ وحدَه\n"
   . "   (لا role_id) وسقفُ التصنيفِ اثنتا عشرةَ مجموعةً مشتركةً بين الأدوارِ كلِّها،\n"
   . "   و`gov_target_nav` يصف مجموعاتٍ لكلِّ دور — والتسويةُ قرارُ معمارية.\n";

exit($GATE && $nameFail > 0 ? 1 : 0);
