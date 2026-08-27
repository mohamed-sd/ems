<?php
/**
 * tools/repair01_guide_nav_extract.php — مجموعاتُ السايدبارِ من الدليلِ المعماريّ
 * ═══════════════════════════════════════════════════════════════════════════
 * **مشكلةُ المالك ①**: «لم تُغيَّر المجموعاتُ وروابطُ الصفحاتِ لكلِّ الإداراتِ كما
 * ينصُّ الدليلُ المعماريّ» — والمطلوب: **توزيعُ المجموعاتِ والروابطِ لكلِّ إدارةٍ
 * بنفسِ الترتيبِ والمسمَّياتِ التي يضعها الدليل**.
 *
 * ◆ **والدليلُ يُملي بنيةً صريحةً في كلِّ ورقةِ إدارة**:
 *     `⬛ مجموعة Sidebar — <اسم المجموعة>`
 *     `■ الشاشة NN من MM · [<المجموعة>] · <عنوان الشاشة>`
 *   فالمجموعةُ واسمُها وترتيبُها، والشاشةُ ورقمُها داخلَها — **كلُّها منصوصة**.
 *
 * ⛔ **ولا يُخترَع اسمٌ ولا ترتيب**: ما لا ينصُّ عليه الدليلُ يُعلَن ناقصًا
 *   **ولا يُملأ بالاجتهاد** — فالدليلُ مصدرُ الحقيقةِ هنا لا الشجرة.
 *
 * ⚠ **والمصنَّفُ مجمَّدٌ بالتجزئة** (‏سجلُّ المصادر · 13 ملفًّا) — فهذه الأداةُ
 *   **تقرأ ولا تكتب فيه**، وأيُّ فرقٍ يُنسَب إلى النظامِ لا إلى الدليل.
 *
 * التشغيل: php tools/repair01_guide_nav_extract.php [--md] [--json]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/vendor/autoload.php';
$MD   = in_array('--md', $argv, true);
$JSON = in_array('--json', $argv, true);

$XLSX = $ROOT . '/docs/REPAIR01_20260823/01 · الدليل المعماري.xlsx';
if (!is_file($XLSX)) { exit("⛔ الدليلُ المعماريُّ غيرُ موجود\n"); }

$rd = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($XLSX);
$rd->setReadDataOnly(true);
$sp = $rd->load($XLSX);

/* ── ورقةُ الإدارةِ تبدأ برقمٍ من رقمَين ثمَّ `_` ───────────────────────────── */
$deps = array();
foreach ($sp->getSheetNames() as $i => $nm) {
    if (!preg_match('~^(\d{2})_~u', $nm, $m)) { continue; }
    $deps[] = array('idx' => $i, 'sheet' => $nm, 'code' => 'DEP-' . $m[1]);
}

$out = array(); $totG = 0; $totS = 0; $orphan = 0;
foreach ($deps as $d) {
    $sh = $sp->getSheet($d['idx']);
    $hi = $sh->getHighestDataRow();
    $groups = array();        /* اسمُ المجموعة ⇒ ترتيبُها */
    $screens = array();       /* [group, no, of, title] */
    $gOrder = 0;
    for ($r = 1; $r <= $hi; $r++) {
        $a = trim((string) $sh->getCell('A' . $r)->getValue());
        if ($a === '') { continue; }
        /* رأسُ مجموعة */
        if (mb_strpos($a, '⬛') === 0) {
            if (preg_match('~مجموعة\s*Sidebar\s*[—\-–]\s*(.+)$~u', $a, $m)) {
                $g = trim($m[1]);
                if (!isset($groups[$g])) { $groups[$g] = ++$gOrder; }
            }
            continue;
        }
        /* سطرُ شاشة */
        if (mb_strpos($a, '■') === 0) {
            if (preg_match('~الشاشة\s*(\d+)\s*من\s*(\d+)\s*·\s*\[([^\]]*)\]\s*·\s*(.+)$~u', $a, $m)) {
                $g = trim($m[3]);
                if ($g !== '' && !isset($groups[$g])) { $groups[$g] = ++$gOrder; }
                $screens[] = array(
                    'group' => $g, 'no' => (int) $m[1], 'of' => (int) $m[2],
                    'title' => trim(preg_replace('~\s+~u', ' ', $m[4])),
                );
                if ($g === '') { $orphan++; }
            }
            continue;
        }
    }
    $totG += count($groups); $totS += count($screens);
    $out[$d['code']] = array('sheet' => $d['sheet'], 'groups' => $groups, 'screens' => $screens);
}

echo "\n═══ ما ينصُّ عليه الدليلُ المعماريّ ═══\n";
printf("  إداراتٌ: %d · مجموعاتٌ: %d · شاشاتٌ منصوصة: %d · شاشةٌ بلا مجموعة: %d\n\n",
    count($out), $totG, $totS, $orphan);
foreach ($out as $code => $d) {
    printf("── %s · %s ──\n", $code, $d['sheet']);
    printf("   مجموعاتٌ %d · شاشاتٌ %d\n", count($d['groups']), count($d['screens']));
    foreach ($d['groups'] as $g => $ord) {
        $in = array();
        foreach ($d['screens'] as $s) { if ($s['group'] === $g) { $in[] = $s['no'] . '·' . $s['title']; } }
        printf("   %2d ⬛ %-42s (%d)\n", $ord, mb_substr($g, 0, 40), count($in));
        foreach (array_slice($in, 0, 3) as $t) { printf("        · %s\n", mb_substr($t, 0, 62)); }
        if (count($in) > 3) { printf("        · … و%d غيرَها\n", count($in) - 3); }
    }
}

if ($JSON) {
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/GUIDE_NAV_SPEC.json',
        json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\n✔ كُتب docs/REPAIR01_20260823/GUIDE_NAV_SPEC.json\n";
}
if ($MD) {
    $o  = "# مجموعاتُ السايدبارِ كما ينصُّ الدليلُ المعماريّ\n\n";
    $o .= "> ⛔ **مستخرَجٌ من `01 · الدليل المعماري.xlsx`**: `php tools/repair01_guide_nav_extract.php --md`\n";
    $o .= "> **ولا يُخترَع اسمٌ ولا ترتيب** — ما لا ينصُّ عليه الدليلُ يُعلَن ناقصًا.\n\n";
    $o .= sprintf("**إداراتٌ %d · مجموعاتٌ %d · شاشاتٌ منصوصة %d**\n", count($out), $totG, $totS);
    foreach ($out as $code => $d) {
        $o .= "\n## `$code` — {$d['sheet']}\n\n| # | المجموعة | الشاشاتُ بترتيبِها |\n|---:|---|---|\n";
        foreach ($d['groups'] as $g => $ord) {
            $in = array();
            foreach ($d['screens'] as $s) { if ($s['group'] === $g) { $in[] = $s['no'] . '. ' . $s['title']; } }
            $o .= sprintf("| %d | **%s** | %s |\n", $ord, $g, $in ? implode('<br>', $in) : '—');
        }
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/GUIDE_NAV_SPEC.md', $o);
    echo "✔ كُتب docs/REPAIR01_20260823/GUIDE_NAV_SPEC.md\n";
}
