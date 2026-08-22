<?php
/**
 * tools/injfrd66_nav_gate.php — بوابةُ XC-01: القائمةُ المُصيَّرةُ مقابلَ الجدولِ المستهدف
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ المعيارُ (INJ-FRD-01 · XC-01): «القائمةُ المُصيَّرةُ مطابقةٌ للجدولِ المستهدفِ
 *   بندًا بندًا» — ستُّ مجموعاتٍ وثلاثةَ عشرَ بندًا للمبيعات (دور 12)، وستٌّ
 *   وأربعةَ عشرَ للموردين (دور 2). والمصدرُ `gov_target_nav` لا قائمةٌ في الكود.
 * ◆ القياسُ بجلسةِ مستخدمٍ حقيقيٍّ لكلِّ دور — فتسري بواباتُ المنحِ fail-closed
 *   كما تسري على المستخدمِ الفعلي (لا بدورٍ مجرَّد).
 * ◆ ثلاثةُ فروقٍ تُقاس منفصلةً — والخلطُ بينها يُخضِّر ما ليس بأخضر:
 *   ① **ناقص**  — بندٌ مستهدَفٌ لا مقابلَ له في المُصيَّر (بالمسارِ المطبَّع).
 *   ② **مخالفُ الاسم** — المسارُ حاضرٌ وفي مجموعتِه، واسمُه ليس اسمَ المستهدف.
 *   ③ **زائد**   — بندٌ حيٌّ لا أصلَ له في الجدولِ المستهدف.
 *   وخطأُ الموضعِ يُقاس رابعًا: المسارُ حاضرٌ في مجموعةٍ غيرِ مجموعتِه.
 * ◆ قراءةٌ خالصة — لا كتابةَ في القاعدةِ إطلاقًا.
 *
 * التشغيل:
 *   php tools/injfrd66_nav_gate.php            التقريرُ التفصيلي
 *   php tools/injfrd66_nav_gate.php --gate     رمزُ خروجٍ 1 عند أيِّ فرق
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

/* ── الجدولُ المستهدف — المصدرُ الوحيد ────────────────────────────────── */
$target = array();               // role => [ ['g'=>, 'gn'=>, 'i'=>, 'item'=>, 'route'=>, 'norm'=>] … ]
$res = mysqli_query($conn, "SELECT role_id, group_no, group_ar, item_no, item_ar, route
                              FROM gov_target_nav ORDER BY role_id, group_no, item_no");
while ($x = mysqli_fetch_assoc($res)) {
    $target[(int) $x['role_id']][] = array(
        'g'     => (int) $x['group_no'],
        'gn'    => $x['group_ar'],
        'i'     => (int) $x['item_no'],
        'item'  => $x['item_ar'],
        'route' => $x['route'],
        'norm'  => strtolower(uxp_norm($x['route'])),
    );
}
if (!$target) { fwrite(STDERR, "جدولُ التنقّلِ المستهدفُ فارغ\n"); exit(2); }

/* ── الأسماءُ المؤسسيةُ للأدوار — للعرضِ لا للحكم ───────────────────────── */
$roleName = array();
$r = mysqli_query($conn, "SELECT id, name FROM roles");
while ($x = mysqli_fetch_assoc($r)) { $roleName[(int) $x['id']] = $x['name']; }

$users   = uxp_role_users($conn);
$totFail = 0;
$summary = array();

foreach ($target as $roleId => $items) {
    $uid  = $users[$roleId] ?? null;
    $live = uxp_render_role($conn, $roleId, $uid);

    echo str_repeat('═', 78) . "\n";
    printf("دور %d · %s   |   المستهدف %d بندًا في %d مجموعة   |   المُصيَّر %d رابطًا\n",
        $roleId, $roleName[$roleId] ?? '—', count($items),
        count(array_unique(array_column($items, 'g'))), count($live));
    echo str_repeat('═', 78) . "\n";

    /* المُصيَّرُ مفهرسًا بالمسارِ المطبَّع — وقد يتكرر المسارُ بمجموعتين */
    $liveByRoute = array();
    foreach ($live as $p) {
        $liveByRoute[strtolower(uxp_norm($p['href']))][] = $p;
    }

    $missing = array(); $renamed = array(); $misplaced = array(); $matched = array();

    foreach ($items as $t) {
        $hits = $liveByRoute[$t['norm']] ?? array();
        if (!$hits) { $missing[] = $t; continue; }

        /* المجموعةُ الصحيحة: رأسٌ يطابق اسمَ المجموعةِ المستهدفة */
        $inGroup = null; $anyHit = $hits[0];
        foreach ($hits as $h) {
            if (mb_strpos($h['group'], $t['gn']) !== false || mb_strpos($t['gn'], $h['group']) !== false) {
                $inGroup = $h; break;
            }
        }
        if ($inGroup === null) { $misplaced[] = array('t' => $t, 'at' => $anyHit['group']); $inGroup = $anyHit; }

        if ($inGroup['label'] !== $t['item']) {
            $renamed[] = array('t' => $t, 'live' => $inGroup['label'], 'grp' => $inGroup['group']);
        } else {
            $matched[] = $t;
        }
    }

    /* الزائد: روابطُ حيةٌ لا أصلَ لها في المستهدف */
    $targetNorms = array_flip(array_column($items, 'norm'));
    $extra = array();
    foreach ($live as $p) {
        $n = strtolower(uxp_norm($p['href']));
        if ($n === '' || isset($targetNorms[$n])) { continue; }
        $extra[$n] = array('label' => $p['label'], 'group' => $p['group'], 'href' => $p['href']);
    }

    $fails = count($missing) + count($renamed) + count($misplaced) + count($extra);
    $totFail += $fails;
    $summary[$roleId] = array(
        'ok' => count($matched), 'n' => count($items), 'miss' => count($missing),
        'ren' => count($renamed), 'mis' => count($misplaced), 'ext' => count($extra),
    );

    if ($missing) {
        echo "\n① ناقصٌ — بندٌ مستهدَفٌ بلا مقابلٍ حيّ (" . count($missing) . "):\n";
        foreach ($missing as $t) { printf("   ✘ [%d.%d] %-42s ← %s\n", $t['g'], $t['i'], $t['item'], $t['route']); }
    }
    if ($misplaced) {
        echo "\n④ خطأُ موضعٍ — المسارُ حاضرٌ في غيرِ مجموعتِه (" . count($misplaced) . "):\n";
        foreach ($misplaced as $m) { printf("   ✘ %-42s المستهدف «%s» · الحيُّ «%s»\n", $m['t']['item'], $m['t']['gn'], $m['at']); }
    }
    if ($renamed) {
        echo "\n② مخالفُ الاسم — المسارُ في مجموعتِه واسمُه غيرُ المستهدف (" . count($renamed) . "):\n";
        foreach ($renamed as $m) {
            printf("   ✘ [%d.%d] المستهدف «%s»\n            الحيُّ  «%s»   (%s)\n",
                $m['t']['g'], $m['t']['i'], $m['t']['item'], $m['live'], $m['t']['route']);
        }
    }
    if ($extra) {
        echo "\n③ زائدٌ — بندٌ حيٌّ لا أصلَ له في المستهدف (" . count($extra) . "):\n";
        foreach ($extra as $n => $e) { printf("   ✘ «%s» في «%s» ← %s\n", $e['label'], $e['group'], $e['href']); }
    }
    if (!$fails) { echo "\n   ✔ مطابقةٌ تامّةٌ بندًا بندًا\n"; }
    echo "\n";
}

/* ── الحصيلة ─────────────────────────────────────────────────────────── */
echo str_repeat('─', 78) . "\n";
echo "الحصيلة:\n";
foreach ($summary as $rid => $s) {
    printf("  دور %-3d مطابقٌ %2d/%2d · ناقص %d · مخالفُ اسمٍ %2d · خطأُ موضعٍ %d · زائد %d\n",
        $rid, $s['ok'], $s['n'], $s['miss'], $s['ren'], $s['mis'], $s['ext']);
}
printf("\n%s  إجماليُّ الفروق: %d\n", $totFail === 0 ? '✔ XC-01 مستوفًى —' : '✘ XC-01 غيرُ مستوفٍ —', $totFail);

exit($GATE && $totFail > 0 ? 1 : 0);
