<?php
/**
 * tools/fix_nav_href_probe.php — شاهدُ INJ-0061/0059: هل يفتح كلُّ رابطٍ صفحتَه؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لا يقرأ عمودَ `route`** — بل يُصيِّر السايدبارَ حيًّا لكلِّ دورٍ نشطٍ ثم
 *   يستخرج `href` الفعليَّ من الناتج ويحلُّه على القرصِ نسبةً إلى الصفحةِ
 *   الحاملة. فالعيبُ في **التركيب** لا في التخزين: `printNavLinkItem` تبني
 *   `href = $basePrefix . $route` و`$basePrefix = '../'` — فصفٌّ يخزّن
 *   `../Audit/x.php` يُصيَّر `../../Audit/x.php` أي خارجَ جذرِ التطبيق.
 *   وفحصُ المخزَّنِ وحدَه يُرجع «صفر مضاعفة» والمضاعفةُ قائمة.
 *
 * ◆ الصفحةُ الحاملةُ المفترضةُ `main/dashboard.php` — عمقٌ واحدٌ من الجذر،
 *   وهو عمقُ كلِّ شاشاتِ النظام. فـ`../X/y.php` منها يحلُّ إلى `X/y.php` ✔
 *   و`../../X/y.php` يحلُّ إلى ما فوقَ الجذر ✘.
 *
 * التشغيل: php tools/fix_nav_href_probe.php [--role=N] [--json]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$only = null;
$asJson = false;
foreach ($argv as $a) {
    if (strpos($a, '--role=') === 0) { $only = (int) substr($a, 7); }
    if ($a === '--json') { $asJson = true; }
}

/* ══ وضعُ التصيير — عمليةٌ منفصلةٌ لكلِّ دور ══════════════════════════════
   الجلسةُ والحالةُ العامةُ (`$_SESSION` · ذاكرةُ المجموعات · علمُ المجالِ
   المقيَّد) تتلوث بين الأدوار داخلَ العمليةِ الواحدة، فيرث الدورُ التالي قائمةَ
   سابقِه ويبدو الفحصُ ناجحًا. عمليةٌ لكلِّ دورٍ هي الشرطُ الوحيدُ للعزل. */
foreach ($argv as $a) {
    if (strpos($a, '--render=') !== 0) { continue; }
    $role = (int) substr($a, 9);
    $_SERVER['SCRIPT_NAME']    = '/ems/main/dashboard.php';
    $_SERVER['REQUEST_URI']    = $_SERVER['SCRIPT_NAME'];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    require_once $ROOT . '/config.php';
    require_once $ROOT . '/includes/unified_nav.php';
    require_once $ROOT . '/tools/fix_lib.php';
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $_SESSION['user'] = array('id' => 0, 'role' => $role, 'company_id' => 1, 'name' => 'nav-href-probe');
    $db = fix_db();

    /* وضعُ التوقُّع: ما **يُرجعه المُنتقي** لا ما في الجدول.
       المقارنةُ مع `nav_items` الخام تكذب: المُنتقي يُرشِّح بالصلاحيةِ وببوابةِ
       المجالِ المقيَّدِ وبمرشِّح بوابةِ المورد — والمحجوبُ عمدًا ليس ميتًا.
       فالفرقُ المطلوبُ قياسُه هو ما يقبله المُنتقي **ثم تُسقطه حلقةُ التصيير**. */
    if (in_array('--expect', $argv, true)) {
        foreach (getUnifiedNavItems($db, $role) as $it) {
            echo $it['door'] . "\t" . $it['route'] . "\t" . $it['label_ar'] . "\n";
        }
        exit(0);
    }

    ob_start();
    try {
        // التوقيع: ($conn, $roleId, $basePrefix='../', ...) — والبادئةُ تُترك
        // على قيمتها الحيةِ عمدًا، فهي نصفُ العيبِ المفحوص.
        renderUnifiedNavigationV2($db, $role);
    } catch (\Throwable $e) {
        // CS-12: لا يُبتلع استثناء. الفشلُ يُعلَن على stderr ويُرفع رمزُ خروج،
        // وإلا قرأ الفاحصُ الفراغَ الناتجَ عن العطلِ «قائمةً نظيفة».
        ob_end_clean();
        fwrite(STDERR, 'FATAL role=' . $role . ' :: ' . $e->getMessage() . "\n");
        exit(2);
    }
    echo ob_get_clean();
    exit(0);
}

require_once $ROOT . '/tools/fix_lib.php';
$conn = fix_db();

/* الأدوارُ النشطةُ ذاتُ صفوفِ تنقُّل */
$roles = array();
$r = $conn->query('SELECT DISTINCT role_id FROM nav_items WHERE active=1 ORDER BY role_id');
while ($x = $r->fetch_row()) { $roles[] = (int) $x[0]; }
if ($only !== null) { $roles = array($only); }

/* الصفحةُ الحاملة: عمقٌ واحدٌ من الجذر */
$carrierDir = $ROOT . '/main';

$rows    = array();
$fatal   = array();
$empty   = array();
$badHref = 0;
$total   = 0;

foreach ($roles as $role) {
    /* التصييرُ في عمليةٍ منفصلةٍ — الجلسةُ والحالةُ العامةُ تتلوث بين الأدوار */
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --render=' . $role . ' 2>&1';
    $html = (string) shell_exec($cmd);

    if (strpos($html, 'FATAL role=') !== false) {
        $fatal[] = $role . ' :: ' . trim(strtok(substr($html, strpos($html, 'FATAL role=')), "\n"));
        continue;
    }
    if (!preg_match_all('/<a\s+href="([^"]+)"/i', $html, $m)) {
        // دورٌ نشطٌ بصفرِ رابطٍ مُصيَّرٍ ليس «نظيفًا» — هو أسوأُ الحالات: صفوفُه
        // قائمةٌ والقائمةُ خاوية. يُحسب عطلًا لا نجاحًا.
        $empty[] = $role;
        continue;
    }
    foreach (array_unique($m[1]) as $href) {
        if ($href === '' || $href === '#' || strpos($href, 'http') === 0
            || strpos($href, 'javascript:') === 0 || strpos($href, 'mailto:') === 0) { continue; }
        $total++;
        $path = strtok($href, '?#');
        $abs  = realpath($carrierDir . '/' . $path);
        $ok   = ($abs !== false && is_file($abs) && strpos($abs, realpath($ROOT)) === 0);
        if (!$ok) {
            $badHref++;
            $rows[] = array('role' => $role, 'href' => $href,
                            'why'  => ($abs === false || !is_file((string) $abs))
                                      ? 'الملفُّ غيرُ موجود' : 'خارجَ جذرِ التطبيق');
        }
    }
}

/* ══ الوصولية: صفٌّ نشطٌ لا يُصيَّر هو شاشةٌ ميتةٌ لا عنصرٌ مفقود ═════════
   العطلُ الذي كشفه هذا: حلقةُ التصيير تمرُّ على **الأبواب الثمانيةِ المعرَّفة**
   وحدَها، فصفٌّ بابُه خارجَها يسقط بلا خطأٍ ولا أثر — إلا أن يكون له
   `stage_no` فيُصيَّر بالمسارِ المرحليِّ ويَنجو. فالنجاةُ صدفةُ إعدادٍ لا قاعدة.
   ولا يكشف هذا عدُّ الصفوفِ ولا فحصُ الروابطِ المكسورة: الصفُّ سليمٌ والرابطُ
   غيرُ موجودٍ أصلًا. */
$unreachable = array();
foreach ($roles as $role) {
    $exp = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
                               . ' --render=' . $role . ' --expect 2>&1');
    $want = array();
    foreach (explode("\n", trim($exp)) as $line) {
        if ($line === '' || substr_count($line, "\t") < 2) { continue; }
        list($d, $rt, $lb) = explode("\t", $line, 3);
        $want[] = array('door' => $d, 'route' => $rt, 'label' => $lb);
    }
    if (!$want) { continue; }

    $html = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
                                . ' --render=' . $role . ' 2>&1');
    if (strpos($html, 'FATAL role=') !== false) { continue; }   // أُحصي عطلًا أعلاه
    foreach ($want as $w) {
        if ($w['route'] !== '' && strpos($html, $w['route']) === false) {
            $unreachable[] = array('role' => $role, 'door' => $w['door'],
                                   'route' => $w['route'], 'label' => $w['label']);
        }
    }
}

/* الرسوبُ على الصفر: فاحصٌ يقول «✔ 0/0» يصادق على نفسِه. */
$vacuous = ($total === 0);
$failed  = ($badHref > 0 || $fatal || $empty || $vacuous || $unreachable);

if ($asJson) {
    echo json_encode(array('total' => $total, 'bad' => $badHref, 'fatal' => $fatal,
                           'empty' => $empty, 'unreachable' => $unreachable, 'rows' => $rows),
                     JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
    exit($failed ? 1 : 0);
}

echo "═══ شاهدُ الروابط — تصييرٌ حيٌّ لا عدُّ صفوف ═══\n";
echo 'الأدوار: ' . count($roles) . " · الروابطُ المُصيَّرةُ الفريدة: {$total}\n\n";
if ($vacuous) {
    echo "✘ صفرُ رابطٍ مُصيَّرٍ في كلِّ الأدوار — الفاحصُ نفسُه معطوبٌ أو التصييرُ ميت.\n";
    echo "  ولا يُقرأ هذا نجاحًا: «٠/٠» تصديقٌ على النفس.\n";
}
if ($fatal) {
    echo '✘ أدوارٌ رمى تصييرُها (' . count($fatal) . "):\n";
    foreach (array_slice($fatal, 0, 8) as $f) { echo '    ' . $f . "\n"; }
}
if ($empty) {
    echo '✘ أدوارٌ نشطةٌ بصفرِ رابطٍ مُصيَّرٍ رغم وجودِ صفوفِها (' . count($empty) . '): '
       . implode(',', array_slice($empty, 0, 20)) . "\n";
}
if ($unreachable) {
    echo '✘ صفوفٌ نشطةٌ لا تُصيَّر إطلاقًا — شاشاتٌ ميتة (' . count($unreachable) . "):\n";
    $byDoor = array();
    foreach ($unreachable as $u) { $byDoor[$u['door']][] = $u; }
    foreach ($byDoor as $d => $list) {
        echo '    باب «' . $d . '» → ' . count($list) . " صفًّا · أدوار: "
           . implode(',', array_values(array_unique(array_column($list, 'role')))) . "\n";
        foreach (array_slice($list, 0, 4) as $u) { echo '      · ' . $u['route'] . "\n"; }
        if (count($list) > 4) { echo '      … (+' . (count($list) - 4) . ")\n"; }
    }
    echo "\n";
}
if ($badHref === 0 && !$vacuous) {
    echo "✔ كلُّ رابطٍ مُصيَّرٍ يحلُّ إلى ملفٍّ قائم — 0/{$total} مكسور\n";
} elseif ($badHref > 0) {
    $byHref = array();
    foreach ($rows as $x) { $byHref[$x['href']][] = $x['role']; }
    echo "✘ روابطُ مكسورة: {$badHref} من {$total} (فريدةٌ: " . count($byHref) . ")\n\n";
    $i = 0;
    foreach ($byHref as $href => $rs) {
        if ($i++ >= 20) { echo '  … (+' . (count($byHref) - 20) . " فريدًا)\n"; break; }
        printf("  %-46s أدوار: %s\n", mb_substr($href, 0, 46), implode(',', array_slice($rs, 0, 6)));
    }
}
exit($failed ? 1 : 0);
