<?php
/**
 * tools/repair01_guide_nav_gap.php — الفجوةُ بين الدليلِ والسايدبارِ الحيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * **مشكلةُ المالك ①**. والدليلُ ينصُّ على **١٠٥ مجموعةٍ و٣٦٧ شاشةٍ** موزَّعةً على
 * سبعَ عشرةَ إدارةً بأسمائها وترتيبها.
 *
 * ◆ **والفجوةُ تُقاس على ثلاثةِ محاورَ لا محورٍ واحد**:
 *   ① **المجموعةُ حاضرة؟** — اسمُها في `link_groups` كما نصَّ الدليل
 *   ② **الشاشةُ مربوطةٌ بمجموعتِها؟** — لا بمجموعةٍ أخرى
 *   ③ **الترتيبُ مطابق؟** — فمجموعةٌ حاضرةٌ في غيرِ موضعِها **مخالفةٌ أيضًا**
 *
 * ⚠ **وربطُ عنوانِ الدليلِ بشاشةِ النظامِ هو المفصل**: العنوانُ نصٌّ عربيٌّ
 *   («سجل العملاء») والشاشةُ ملفّ. **والوصلُ عبرَ `canonical_label_ar`** في
 *   سجلِّ الشاشاتِ — وهو الاسمُ المعياريُّ الذي اعتُمد في الموجاتِ السابقة.
 *   ⛔ **وما لا يُوصَل يُعلَن ولا يُخمَّن** — فربطٌ بالتقريبِ يصنع خريطةً كاذبة.
 *
 * ⛔ **ولا تكتب هذه الأداةُ شيئًا** — تقيس وتُخرج الفجوة.
 *
 * التشغيل: php tools/repair01_guide_nav_gap.php [--md]
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

$SPEC = $ROOT . '/docs/REPAIR01_20260823/GUIDE_NAV_SPEC.json';
if (!is_file($SPEC)) { exit("⛔ استخرِج المواصفةَ أوّلًا: php tools/repair01_guide_nav_extract.php --json\n"); }
$spec = json_decode((string) file_get_contents($SPEC), true);

/* تطبيعٌ عربيٌّ خفيف — ولا حذفَ كلمات */
$nz = function ($s) {
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', (string) $s);
    $s = str_replace('ة', 'ه', $s);
    $s = str_replace(array('ـ', "\xC2\xA0", 'ً', 'ٌ', 'ٍ', 'َ', 'ُ', 'ِ', 'ّ', 'ْ'), array('', ' ', '', '', '', '', '', '', '', ''), $s);
    return preg_replace('~\s+~u', ' ', trim($s));
};

/* ── الحالُ الحيّ ─────────────────────────────────────────────────────────── */
$liveGroups = array();                    /* اسمٌ مطبَّع ⇒ id */
/* ⚠ **عمودان مفترَضان خاطئان**: `link_groups.name` **لا `name_ar`**، و`nav_items.route`
     **يخلط الصيغتَين**: 2208 صفًّا بلاحقةِ `.php` و115 بلا لاحقة.
     ⇒ **ففرضيّتي أعطت صفرًا من 105 وصفرًا من 367** — **وهو صفرٌ عن أداتي لا عن
     النظام**. والمسارُ يُطبَّع من اللاحقةِ على الطرفَين قبل أيِّ مقارنة. */
$r = $conn->query("SELECT id, name FROM link_groups");
while ($r && ($x = $r->fetch_assoc())) { $liveGroups[$nz($x['name'])][] = (int) $x['id']; }
$rt = function ($s) { return strtolower(preg_replace('~\.php$~i', '', ltrim((string) $s, '/'))); };

$byLabel = array();                       /* اسمُ الشاشةِ المعياريُّ ⇒ صفُّ السجل */
$r = $conn->query("SELECT screen_file, route, owner_code, canonical_label_ar
                     FROM repair01_screen_registry
                    WHERE on_disk = 1 AND ownership_verdict NOT IN ('RETIRE')
                      AND COALESCE(canonical_label_ar,'') <> ''");
while ($r && ($x = $r->fetch_assoc())) { $byLabel[$nz($x['canonical_label_ar'])][] = $x; }

/* موضعُ الشاشةِ الحيُّ في الملاحة: المجموعةُ والترتيب */
$navIdx = array();
$q = $conn->query("SELECT ni.route, ni.group_id, ni.sort_order, ni.active, lg.name
                     FROM nav_items ni LEFT JOIN link_groups lg ON lg.id = ni.group_id
                    WHERE COALESCE(ni.route,'') <> ''");
while ($q && ($x = $q->fetch_assoc())) { $navIdx[$rt($x['route'])][] = $x; }
$navOf = function ($route) use (&$navIdx, $rt) {
    $k = $rt($route);
    return isset($navIdx[$k]) ? $navIdx[$k] : array();
};

$T = array('gOk' => 0, 'gMissing' => 0, 'sLinked' => 0, 'sUnmatched' => 0,
           'sWrongGroup' => 0, 'sNotInNav' => 0, 'sAmbig' => 0);
$rows = array();
foreach ($spec as $code => $d) {
    if (!$d['groups'] && !$d['screens']) { continue; }
    foreach ($d['groups'] as $g => $ord) {
        $has = isset($liveGroups[$nz($g)]);
        if ($has) { $T['gOk']++; } else { $T['gMissing']++; }
        $rows[] = array('t' => 'G', 'dep' => $code, 'ord' => $ord, 'name' => $g,
                        'state' => $has ? 'حاضرة' : '**غائبة**');
    }
    foreach ($d['screens'] as $s) {
        $k = $nz($s['title']);
        $cand = isset($byLabel[$k]) ? $byLabel[$k] : array();
        if (!$cand) { $T['sUnmatched']++;
            $rows[] = array('t' => 'S', 'dep' => $code, 'ord' => $s['no'], 'name' => $s['title'],
                            'group' => $s['group'], 'state' => '**لا شاشةَ بهذا الاسمِ المعياريّ**');
            continue; }
        if (count($cand) > 1) { $T['sAmbig']++; }
        $x = $cand[0];
        $nav = $navOf((string) $x['route']);
        if (!$nav) { $T['sNotInNav']++;
            $rows[] = array('t' => 'S', 'dep' => $code, 'ord' => $s['no'], 'name' => $s['title'],
                            'group' => $s['group'], 'route' => $x['route'],
                            'state' => '**غيرُ مربوطةٍ في الملاحةِ أصلًا**');
            continue; }
        $ok = false; $seen = array();
        foreach ($nav as $nv) { $seen[] = (string) $nv['name'];
            if ($nz((string) $nv['name']) === $nz($s['group'])) { $ok = true; } }
        if ($ok) { $T['sLinked']++;
            $rows[] = array('t' => 'S', 'dep' => $code, 'ord' => $s['no'], 'name' => $s['title'],
                            'group' => $s['group'], 'route' => $x['route'], 'state' => 'مطابقة'); }
        else { $T['sWrongGroup']++;
            $rows[] = array('t' => 'S', 'dep' => $code, 'ord' => $s['no'], 'name' => $s['title'],
                            'group' => $s['group'], 'route' => $x['route'],
                            'state' => '**في مجموعةٍ أخرى**: ' . implode('، ', array_unique(array_filter($seen)))); }
    }
}

echo "\n═══ الفجوةُ بين الدليلِ المعماريِّ والسايدبارِ الحيّ ═══\n";
printf("  ⬛ مجموعاتُ الدليل: %d · حاضرةٌ بالاسم %d · **غائبة %d**\n",
    $T['gOk'] + $T['gMissing'], $T['gOk'], $T['gMissing']);
printf("  ■ شاشاتُ الدليل: %d\n", $T['sLinked'] + $T['sUnmatched'] + $T['sWrongGroup'] + $T['sNotInNav']);
printf("      ✔ في مجموعتِها المنصوصة          %d\n", $T['sLinked']);
printf("      ✘ في مجموعةٍ أخرى                 %d\n", $T['sWrongGroup']);
printf("      ✘ غيرُ مربوطةٍ في الملاحة        %d\n", $T['sNotInNav']);
printf("      ◆ لا شاشةَ بهذا الاسمِ المعياريّ %d\n", $T['sUnmatched']);
printf("      ⚠ اسمٌ يطابق أكثرَ من شاشة       %d\n", $T['sAmbig']);

if ($MD) {
    $o  = "# الفجوةُ بين الدليلِ المعماريِّ والسايدبارِ الحيّ\n\n";
    $o .= "> ⛔ **مولَّدٌ من قياسٍ حيّ**: `php tools/repair01_guide_nav_gap.php --md`\n";
    $o .= "> **والوصلُ عبر `canonical_label_ar`** ⛔ وما لا يُوصَل يُعلَن ولا يُخمَّن.\n\n";
    $o .= "| المقياس | العدد |\n|---|---:|\n";
    $o .= sprintf("| ⬛ مجموعاتُ الدليل | %d |\n", $T['gOk'] + $T['gMissing']);
    $o .= sprintf("| — حاضرةٌ بالاسم | %d |\n| — **غائبة** | **%d** |\n", $T['gOk'], $T['gMissing']);
    $o .= sprintf("| ■ في مجموعتِها المنصوصة | %d |\n", $T['sLinked']);
    $o .= sprintf("| ■ **في مجموعةٍ أخرى** | **%d** |\n", $T['sWrongGroup']);
    $o .= sprintf("| ■ **غيرُ مربوطةٍ في الملاحة** | **%d** |\n", $T['sNotInNav']);
    $o .= sprintf("| ◆ لا شاشةَ بهذا الاسم | %d |\n", $T['sUnmatched']);
    $dep = '';
    foreach ($rows as $x) {
        if ($x['dep'] !== $dep) { $dep = $x['dep'];
            $o .= "\n## `$dep`\n\n| النوع | # | الاسمُ في الدليل | المجموعةُ المنصوصة | الحال |\n|---|---:|---|---|---|\n"; }
        $o .= sprintf("| %s | %d | %s | %s | %s |\n", $x['t'] === 'G' ? '⬛' : '■', $x['ord'],
            $x['name'], isset($x['group']) ? $x['group'] : '—', $x['state']);
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/GUIDE_NAV_GAP.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/GUIDE_NAV_GAP.md\n";
}
