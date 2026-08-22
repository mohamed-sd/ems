<?php
/**
 * tools/injfrd66_xc04_gate.php — بوابةُ XC-04: صفرُ سطحٍ مبنيٍّ بلا مدخلٍ ولا إعلان
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «لكلِّ سطحٍ مبنيٍّ مدخلٌ من القائمةِ أو تبويبٌ في ملفِّ أبيه —
 *   أو يُعلَن سطحًا خدميًّا لا واجهةَ له».
 *
 * ◆ **فثلاثةُ مخارجَ لا مخرجٌ واحد**، وتُقاس ثلاثتُها:
 *   ① **بندُ تنقّلٍ نشِط** في `nav_items`.
 *   ② **تبويبٌ في ملفِّ أبيه** — يُقاس بورودِ المسارِ في ملفِّ عُدّةِ تبويباتٍ
 *      أو في ملفٍّ أبٍ على القرص، **لا في هجرةٍ ولا في شاهدٍ ولا في أداة**:
 *      فذكرُ المسارِ في هجرةٍ ليس مدخلًا للمستخدم.
 *   ③ **إعلانٌ مكتوب** في `nav_canonical.placement_basis` — وهو الحقلُ الذي
 *      يحمل قرارَ الورقةِ نصًّا («صفحةُ هبوطٍ للمساحةِ لا بندًا في مجموعة —
 *      قرارُ الورقة م٢٣»). والإعلانُ **يُعفي من المدخلِ ولا يُعفي من البناء**.
 *
 * ◆ **ولا يُعَدُّ المسارُ مبنيًّا إلا إن كان على القرص** — فالغائبُ ليس بلا
 *   مدخلٍ بل بلا سطح، وخلطُهما يُضخّم الرقمَ ويُضيّع العلاج.
 *
 * ◆ قراءةٌ خالصة — لا كتابةَ في القاعدةِ إطلاقًا.
 *
 * التشغيل:
 *   php tools/injfrd66_xc04_gate.php          التقرير
 *   php tools/injfrd66_xc04_gate.php --gate   رمزُ خروجٍ 1 عند سطحٍ بلا مدخلٍ ولا إعلان
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$GATE = in_array('--gate', $argv, true);

/* ── ① نطاقُ القياس: أسطحُ الإدارتَين على القرص ────────────────────────── */
$DIRS = array('Suppliers', 'Clients', 'Contracts', 'Opportunities', 'Projects');
$surfaces = array();
foreach ($DIRS as $d) {
    foreach ((array) glob($ROOT . '/' . $d . '/*.php') as $f) {
        $rel = $d . '/' . basename($f);
        $surfaces[strtolower($rel)] = $rel;
    }
}

/* ── ② المداخلُ الثلاثة ────────────────────────────────────────────────── */
/* ⓐ بندُ تنقّلٍ نشِط */
$navLive = array();
$res = @mysqli_query($conn, "SELECT DISTINCT LOWER(route) r FROM nav_items WHERE active = 1");
while ($res && ($x = mysqli_fetch_assoc($res))) {
    $navLive[preg_replace('/[?#].*$/', '', $x['r'])] = true;
}

/* ⓑ إعلانٌ مكتوبٌ في السجلِّ المعتمَد */
$declared = array();
$res = @mysqli_query($conn, "SELECT LOWER(route) r, placement_basis, canonical_ar
                               FROM nav_canonical
                              WHERE placement_basis IS NOT NULL AND placement_basis <> ''");
while ($res && ($x = mysqli_fetch_assoc($res))) { $declared[$x['r']] = $x['placement_basis']; }

/* ⓒ تبويبٌ في ملفٍّ أبٍ أو عُدّةِ تبويبات — **من ملفاتِ الواجهةِ وحدَها**.
   والهجرةُ والشاهدُ والأداةُ **ليست مداخلَ للمستخدم** فتُستبعد صراحةً. */
$parentFiles = array();
foreach (array('includes/*.php', 'Suppliers/*.php', 'Clients/*.php', 'Contracts/*.php',
               'Projects/*.php', 'Opportunities/*.php', 'main/*.php') as $g) {
    foreach ((array) glob($ROOT . '/' . $g) as $f) { $parentFiles[] = $f; }
}
$tabRef = array();
foreach ($parentFiles as $f) {
    $body = (string) @file_get_contents($f);
    if ($body === '') { continue; }
    $self = strtolower(str_replace('\\', '/', substr($f, strlen($ROOT) + 1)));
    foreach ($surfaces as $lc => $rel) {
        if ($lc === $self) { continue; }                 /* الملفُّ لا يكون مدخلَ نفسِه */
        $base = basename($rel);
        if (mb_strpos($body, $base) !== false) { $tabRef[$lc][] = $self; }
    }
}

/* ── ③ الحكم ───────────────────────────────────────────────────────────── */
$ok = array(); $bad = array();
foreach ($surfaces as $lc => $rel) {
    $hasNav  = isset($navLive[$lc]);
    $hasDecl = isset($declared[$lc]);
    $hasTab  = isset($tabRef[$lc]);
    if ($hasNav || $hasDecl || $hasTab) {
        $ok[] = array('r' => $rel, 'nav' => $hasNav, 'decl' => $hasDecl, 'tab' => $hasTab);
    } else {
        $bad[] = $rel;
    }
}

echo "\n═══ INJ-FRD-01 · XC-04 — سطحٌ مبنيٌّ بلا مدخلٍ ولا إعلان ═══\n\n";
printf("  أسطحٌ على القرصِ في نطاقِ الإدارتَين: %d\n", count($surfaces));
printf("  لها مدخلٌ أو إعلان: %d   ·   بلا مدخلٍ ولا إعلان: %d\n\n", count($ok), count($bad));

/* المُعلَنُ بلا بندِ تنقّلٍ — يُعرض لأنَّ إعلانَه هو ما يُعفيه */
$declOnly = array_filter($ok, static fn(array $o): bool => !$o['nav'] && $o['decl']);
if ($declOnly) {
    echo "  ◆ مُعفًى بإعلانٍ مكتوبٍ في السجل (لا بندَ تنقّلٍ له عمدًا):\n";
    foreach ($declOnly as $o) {
        printf("     ○ %-44s %s\n", $o['r'], mb_substr($declared[strtolower($o['r'])], 0, 62));
    }
    echo "\n";
}

$tabOnly = array_filter($ok, static fn(array $o): bool => !$o['nav'] && !$o['decl'] && $o['tab']);
if ($tabOnly) {
    printf("  ◆ مدخلُه تبويبٌ في ملفِّ أبيه (%d سطحًا) — عيّنة:\n", count($tabOnly));
    $i = 0;
    foreach ($tabOnly as $o) {
        if ($i++ >= 6) { printf("     … و%d غيرُها\n", count($tabOnly) - 6); break; }
        printf("     ○ %-44s ⇐ %s\n", $o['r'], implode(' · ', array_slice($tabRef[strtolower($o['r'])], 0, 2)));
    }
    echo "\n";
}

if ($bad) {
    echo "  ✘ بلا مدخلٍ ولا إعلان:\n";
    foreach ($bad as $r) { printf("     ✘ %s\n", $r); }
    echo "\n     ◆ العلاجُ أحدُ ثلاثةٍ لا رابعَ لها: بندُ تنقّلٍ · تبويبٌ في ملفِّ أبيه ·\n";
    echo "       أو إعلانٌ مكتوبٌ في `nav_canonical.placement_basis` بقرارِ ورقتِه.\n\n";
} else {
    echo "  ✔ صفرُ سطحٍ مبنيٍّ بلا مدخلٍ ولا إعلان\n\n";
}

exit($GATE && $bad ? 1 : 0);
