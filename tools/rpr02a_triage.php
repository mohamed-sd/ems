<?php
/**
 * tools/rpr02a_triage.php — العجزُ في السايدبارِ مشتقًّا بمقامِه · وفرزُه ثلاثَ سلال
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **لا رقمَ منقول**: العجزُ يُشتقُّ هنا من الصفرِ، بمقامٍ معلَنٍ في رأسِ التقرير.
 *    وبحثٌ في كلِّ مخرَجاتِ الحملةِ لم يجد تشغيلًا مسجَّلًا أخرج «٨٠» عجزًا في
 *    السايدبار — و`CHANGE_REPORT` وحده يحمل الرقمَ وهو **عدُّ تغييراتٍ أُجريت**
 *    لا عدُّ غيابٍ، ولا سطحَ توثيقٍ واحدًا فيه. فالمقامُ أدناه مقامي أنا.
 *
 * ◆ **المقام**: بطاقاتُ الدليلِ ٣٩٥ − أسطحُ التوثيقِ ٢١ = **٣٧٤ سطحًا مؤهَّلًا
 *   للسايدبار**. وسطحُ التوثيقِ لا يُحسب عجزًا ولا يدخل طابورَ بناءٍ (م ⑥ · G-DOC-01).
 *
 * ◆ **والحضورُ يُقاس بقفلَين لا بعبارة**:
 *     ① صفٌّ في السجلِّ الرسميِّ `repair01_screen_registry` باسمٍ معياريٍّ مطابق
 *     ② وصفٌّ في `nav_items` بمسارِ ذلك الصفِّ — فالسجلُّ بلا رابطٍ ليس ظهورًا
 *   ⛔ **ولا يُقاس بوجودِ نصٍّ في صفحة** — الرسوُّ على `screen_id` أو مسارِ ملفّ.
 *
 * ◆ **والملفُّ يُتحقَّق من نظامِ الملفّاتِ نفسِه** لا من عمودِ `on_disk` وحدَه —
 *   فقد سُجِّل ملفّا مكتبةٍ من `vendor/phpoffice` سطحَين قبلَ اليوم.
 *
 * ◆ **والسلالُ ثلاث**: (أ) مبنيٌّ مسجَّلٌ ينقصه رابط · (ب) مبنيٌّ غيرُ مسجَّل ·
 *   (ج) غيرُ مبنيّ — ولكلِّ صفٍّ **دليلُه**: مسارُ ملفٍّ أو معرِّفُ صفّ.
 *
 * التشغيل: php tools/rpr02a_triage.php [--json=path]
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

$jsonOut = '';
foreach ($argv as $a) { if (strpos($a, '--json=') === 0) { $jsonOut = substr($a, 7); } }

$GUIDE = $ROOT . '/docs/REPAIR01_20260823/01 · الدليل المعماري.xlsx';
$cards = rpr02a_read_cards($GUIDE);

/* ═══ ① فهرسُ القرصِ الحقيقيّ — لا عمودُ `on_disk` وحدَه ═══ */
$diskIdx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $fo) {
    $p = strtr($fo->getPathname(), DIRECTORY_SEPARATOR, '/');
    if (strpos($p, '/.git') !== false || strpos($p, 'node_modules') !== false || strpos($p, '/vendor/') !== false) { continue; }
    if (substr($p, -4) !== '.php') { continue; }
    $bn = strtolower(basename($p));
    if (!isset($diskIdx[$bn])) { $diskIdx[$bn] = str_replace(strtr($ROOT, DIRECTORY_SEPARATOR, '/') . '/', '', $p); }
}

/* ═══ ② السجلُّ الرسميّ — بالاسمِ المعياريِّ المطبَّع ═══ */
$regByLabel = array();
$r = $conn->query("SELECT screen_id, screen_file, route, owner_code, lifecycle, on_disk, visibility_class,
                          canonical_label_ar, ownership_verdict
                     FROM repair01_screen_registry
                    WHERE COALESCE(canonical_label_ar,'') <> ''");
while ($r && ($x = $r->fetch_assoc())) { $regByLabel[rpr02a_nz($x['canonical_label_ar'])][] = $x; }

/* ═══ ②-ب فهرسُ المعرِّفِ — لبلوغِ أبِ التبويب ═══ */
$regById = array();
$r = $conn->query("SELECT screen_id, screen_file, route, lifecycle, visibility_class, parent_screen_id, canonical_label_ar
                     FROM repair01_screen_registry");
while ($r && ($x = $r->fetch_assoc())) { $regById[$x['screen_id']] = $x; }

/* ═══ ③ الملاحةُ الحيّة — بالمسارِ وبالعنوان ═══ */
$navByRoute = array(); $navByLabel = array();
$q = $conn->query("SELECT ni.route, ni.label_ar, ni.active, ni.group_id, lg.name gname
                     FROM nav_items ni LEFT JOIN link_groups lg ON lg.id = ni.group_id");
while ($q && ($x = $q->fetch_assoc())) {
    if ((string) $x['route'] !== '') { $navByRoute[rpr02a_route($x['route'])][] = $x; }
    if ((string) $x['label_ar'] !== '') { $navByLabel[rpr02a_nz($x['label_ar'])][] = $x; }
}

/* ═══ ④ أسطحُ دورةِ العملِ الحيّة — مرشَّحُ «مبنيٌّ غيرُ مسجَّل» ═══ */
$srfByTitle = array();
$q = $conn->query("SELECT screen_title, screen_file, disk_path, on_disk, canonical_code, screen_id
                     FROM repair01_surfaces WHERE COALESCE(screen_title,'') <> ''");
while ($q && ($x = $q->fetch_assoc())) { $srfByTitle[rpr02a_nz($x['screen_title'])][] = $x; }

/* ═══ ④-ب معجمُ الأسماءِ الحيّةِ كلِّها — لمسبارِ المرادفِ الاسميّ ═══
   ⛔ **المرادفُ يُعلَن ولا يُخمَّن**: مطابقٌ تقريبيٌّ لا يُدخل سطحًا سلّةَ
   «مبنيّ»، بل يرفعه إلى سلّةٍ رابعةٍ **يحسمها إنسان**. فالتشابهُ وحدَه خطرٌ
   مُثبَتٌ في هذه الحملة: `Workforce/worker_worklog.php` يطابق «سجل الأحداث
   التشغيلية» حرفًا وهو سجلُّ عاملٍ لا خطًّا زمنيًّا للتشغيل. */
$lex = array();
foreach ($regByLabel as $k => $rows) { $lex[$k][] = 'السجل: `' . $rows[0]['screen_id'] . '` · `' . $rows[0]['screen_file'] . '`'; }
foreach ($navByLabel as $k => $rows) { $lex[$k][] = 'الملاحة: `' . $rows[0]['route'] . '`'; }
foreach ($srfByTitle as $k => $rows) { $lex[$k][] = 'دورةُ العمل: `' . $rows[0]['screen_file'] . '`'; }
$lexKeys = array_keys($lex);

/** أقربُ اسمٍ حيٍّ وتشابهُه (0..1) — ولا يُعتمد إلّا فوقَ العتبةِ المعلنة */
function rpr02a_nearest($needle, array $keys, array $lex, $floor = 0.78) {
    $best = null; $bestS = 0.0;
    foreach ($keys as $k) {
        $len = max(mb_strlen($needle), mb_strlen($k));
        if ($len === 0) { continue; }
        if (abs(mb_strlen($needle) - mb_strlen($k)) / $len > 0.45) { continue; }
        similar_text($needle, $k, $pct);
        $sc = $pct / 100;
        if ($sc > $bestS) { $bestS = $sc; $best = $k; }
    }
    if ($best === null || $bestS < $floor) { return null; }
    return array('name' => $best, 'score' => $bestS, 'where' => implode(' · ', $lex[$best]));
}

/* ═══ ⑤ الفرز ═══ */
$eligible = array(); $docs = array();
foreach ($cards as $c) { if (rpr02a_is_doc($c)) { $docs[] = $c; } else { $eligible[] = $c; } }

$present = array(); $missing = array();
foreach ($eligible as $c) {
    $k = rpr02a_nz($c['name']);
    $reg = isset($regByLabel[$k]) ? $regByLabel[$k] : array();
    /* السجلُّ الرسميُّ الحيّ: غيرُ شبحٍ ومُثبَتٌ على القرصِ فعلًا */
    $regLive = array();
    foreach ($reg as $x) {
        $bn = strtolower(basename((string) $x['screen_file']));
        $onDisk = isset($diskIdx[$bn]);
        if (!in_array($x['lifecycle'], array('GHOST_TARGET', 'GHOST_RETIRED'), true) && $onDisk) {
            $x['disk_real'] = $diskIdx[$bn];
            $regLive[] = $x;
        }
    }
    /* رابطُ ملاحةٍ بمسارِ صفِّ السجلّ */
    $navHit = null;
    foreach ($regLive as $x) {
        $rk = rpr02a_route($x['route'] !== '' ? $x['route'] : $x['screen_file']);
        if (isset($navByRoute[$rk])) { $navHit = array('by' => 'route', 'route' => $rk, 'rows' => $navByRoute[$rk], 'screen_id' => $x['screen_id']); break; }
    }
    /* ⚠ **ابنُ التبويبِ يظهر بأبيه لا بصفٍّ في الملاحة**: `visibility_class='TAB_CHILD'`
         قرارُ ظهورٍ كتبته W02 بقاعدتِه — فعدُّه «ينقصه رابط» يوجب إضافةَ رابطٍ
         يخالف قرارَ الظهورِ نفسَه، وهو إصلاحٌ يكسر إصلاحًا. فالظهورُ طبقتان. */
    $tabVia = null;
    if (!$navHit) {
        foreach ($regLive as $x) {
            if ($x['visibility_class'] !== 'TAB_CHILD') { continue; }
            $pid = (string) $x['parent_screen_id'];
            if ($pid === '' || !isset($regById[$pid])) { continue; }
            $p = $regById[$pid];
            $pk = rpr02a_route($p['route'] !== '' ? $p['route'] : $p['screen_file']);
            if (isset($navByRoute[$pk])) {
                $tabVia = array('child' => $x['screen_id'], 'parent' => $pid, 'parent_route' => $pk,
                                'parent_label' => $p['canonical_label_ar']);
                break;
            }
        }
    }
    $c['_reg'] = $reg; $c['_regLive'] = $regLive; $c['_nav'] = $navHit; $c['_tab'] = $tabVia;
    if ($navHit) { $c['_layer'] = 'بندُ سايدبار'; $present[] = $c; }
    elseif ($tabVia) { $c['_layer'] = 'تبويبٌ تحت `' . $tabVia['parent'] . '`'; $present[] = $c; }
    else { $missing[] = $c; }
}

/* ═══ ⑥ السلالُ الثلاث ═══ */
$A = array(); $B = array(); $C = array(); $D = array();
foreach ($missing as $c) {
    $k = rpr02a_nz($c['name']);
    if ($c['_regLive']) {
        $x = $c['_regLive'][0];
        $c['_basket'] = 'أ';
        $c['_evidence'] = '`' . $x['screen_id'] . '` · `' . $x['disk_real'] . '`';
        $c['_why'] = 'مسجَّلٌ حيًّا (`' . $x['lifecycle'] . '` · `' . $x['visibility_class'] . '`) وملفُّه على القرصِ — ولا صفَّ له في `nav_items`';
        $A[] = $c; continue;
    }
    /* (ب) مبنيٌّ وغيرُ مسجَّل — ثلاثةُ شواهدَ مقبولة، ولكلٍّ مرساةٌ صلبة */
    $ev = ''; $why = '';
    /* ب-١: صفُّ سجلٍّ شبحٌ لكنَّ ملفَّه موجودٌ فعلًا على القرص */
    foreach ($c['_reg'] as $x) {
        $bn = strtolower(basename((string) $x['screen_file']));
        if (isset($diskIdx[$bn])) { $ev = '`' . $diskIdx[$bn] . '`'; $why = 'صفُّ السجلِّ `' . $x['screen_id'] . '` موسومٌ `' . $x['lifecycle'] . '` **والملفُّ موجودٌ على القرصِ فعلًا**'; break; }
    }
    /* ب-٢: سطحُ دورةِ عملٍ بعنوانٍ مطابقٍ وملفُّه على القرص */
    if ($ev === '' && isset($srfByTitle[$k])) {
        foreach ($srfByTitle[$k] as $x) {
            $bn = strtolower(basename((string) $x['screen_file']));
            if (isset($diskIdx[$bn])) { $ev = '`' . $diskIdx[$bn] . '`'; $why = 'سطحٌ في `repair01_surfaces` بعنوانٍ مطابقٍ وملفُّه على القرص — **بلا صفٍّ في السجلِّ الرسميّ**'; break; }
        }
    }
    /* ب-٣: بندُ ملاحةٍ حيٌّ بعنوانٍ مطابق — مُصيَّرٌ فعلًا وغيرُ مسجَّل */
    if ($ev === '' && isset($navByLabel[$k])) {
        $x = $navByLabel[$k][0];
        $bn = strtolower(basename((string) $x['route']));
        $ev = '`nav_items` ⇒ `' . $x['route'] . '`' . (isset($diskIdx[$bn]) ? ' · `' . $diskIdx[$bn] . '`' : '');
        $why = 'بندُ ملاحةٍ حيٌّ بالاسمِ نفسِه (' . ((int) $x['active'] ? 'مفعَّل' : 'معطَّل') . ') — **بلا اسمٍ معياريٍّ في السجل**';
    }
    if ($ev !== '') { $c['_basket'] = 'ب'; $c['_evidence'] = $ev; $c['_why'] = $why; $B[] = $c; continue; }
    /* (ج؟) مرادفٌ اسميٌّ قريب — يُعلَن ويُحسم بيدٍ بشريّة، ولا يُصنَّف آليًّا */
    $near = rpr02a_nearest($k, $lexKeys, $lex);
    if ($near !== null) {
        $c['_basket'] = 'ج؟';
        $c['_evidence'] = '≈ ' . number_format($near['score'] * 100, 0) . '٪ ⇐ «' . $near['name'] . '» · ' . $near['where'];
        $c['_why'] = 'لا مطابقةَ اسميّةً تامّة — **ومرشَّحٌ بالتشابهِ وحدَه لا يُحسم آليًّا**';
        $D[] = $c; continue;
    }
    /* (ج) غيرُ مبنيّ — إثباتُ الغياب */
    $c['_basket'] = 'ج';
    $c['_evidence'] = 'لا ملفَّ · لا صفَّ سجلٍّ · لا بندَ ملاحةٍ · **ولا مرادفَ اسميًّا فوقَ 78٪**';
    $c['_why'] = 'صفرُ مرساةٍ في الأربعة: `repair01_screen_registry` · `repair01_surfaces` · `nav_items` · معجمُ الأسماءِ الحيّة';
    $C[] = $c;
}

/* ═══ ⑦ التقرير ═══ */
$ts = date('Y-m-d H:i:s');
$md  = "# RPR-02-A · جدولُ الفرز — العجزُ في السايدبارِ بمقامِه وسلالِه الثلاث\n\n";
$md .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/rpr02a_triage.php`\n";
$md .= "> **مولَّدٌ**: " . $ts . " · **الدليل**: `01 · الدليل المعماري.xlsx` (الحزمةُ المستبدَلةُ 2026-08-28)\n";
$md .= "> **السجلُّ الرسميّ**: `repair01_screen_registry` · **الملاحة**: `nav_items` × `link_groups` · **القرص**: مسحٌ عوديٌّ حيٌّ يستثني `vendor/` و`node_modules/`\n\n";

$md .= "## ⓪ المقام — ولا رقمَ بلا مقام\n\n";
$md .= "| المفردة | العدد |\n|---|---:|\n";
$md .= "| بطاقاتُ الشاشةِ في الدليل | **" . count($cards) . "** |\n";
$md .= "| − أسطحُ التوثيقِ `Documentation Artifact — خارج السايدبار` | **" . count($docs) . "** |\n";
$md .= "| **= المؤهَّلُ للسايدبار — مقامُ العجز** | **" . count($eligible) . "** |\n";
$layerNav = 0; $layerTab = 0;
foreach ($present as $c) { if ($c['_nav']) { $layerNav++; } else { $layerTab++; } }
$md .= "| منها حاضرٌ في السايدبارِ الحيّ | " . count($present) . " |\n";
$md .= "| — بندًا في `nav_items` | " . $layerNav . " |\n";
$md .= "| — تبويبًا تحت أبٍ مربوطٍ (`TAB_CHILD`) | " . $layerTab . " |\n";
$md .= "| **منها غائب — العجزُ المقيس** | **" . count($missing) . "** |\n\n";
$md .= "**نسبةُ العجز: " . (count($eligible) ? number_format(count($missing) * 100 / count($eligible), 1) : '0.0') . "٪ من " . count($eligible) . "**\n\n";

$md .= "## ① السلالُ الثلاث\n\n";
$md .= "| السلّة | التعريف | العدد | العمل |\n|---|---|---:|---|\n";
$md .= "| **(أ)** | مبنيٌّ ومسجَّلٌ وينقصه رابط | **" . count($A) . "** | يُضاف رابطُه — عملُ دقائق |\n";
$md .= "| **(ب)** | مبنيٌّ وغيرُ مسجَّل | **" . count($B) . "** | يُسجَّل ثمَّ يُربط |\n";
$md .= "| **(ج؟)** | لا مطابقةَ تامّة · مرشَّحٌ بمرادفٍ اسميٍّ **يُحسَم بيد** | **" . count($D) . "** | يُحسَم أوّلًا: (ب) أم (ج) |\n";
$md .= "| **(ج)** | غيرُ مبنيّ | **" . count($C) . "** | يدخل طابورَ البناء |\n";
$md .= "| | **المجموع** | **" . (count($A) + count($B) + count($C) + count($D)) . "** | = العجزُ المقيس |\n\n";

foreach (array(array('أ', $A, 'مبنيٌّ ومسجَّلٌ وينقصه رابط'),
               array('ب', $B, 'مبنيٌّ وغيرُ مسجَّل'),
               array('ج؟', $D, 'مرشَّحٌ بمرادفٍ اسميٍّ — يُحسَم بيدٍ بشريّة'),
               array('ج', $C, 'غيرُ مبنيّ')) as $bk) {
    list($lbl, $set, $desc) = $bk;
    $md .= "## السلّة (" . $lbl . ") — " . $desc . " · " . count($set) . " سطحًا\n\n";
    if (!$set) { $md .= "**صفر.**\n\n"; continue; }
    $md .= "| # | الإدارة | السطح | المجموعةُ المنصوصة | الدليل | السبب |\n|---:|---|---|---|---|---|\n";
    $i = 0;
    foreach ($set as $c) {
        $i++;
        $md .= '| ' . $i . ' | `' . $c['code'] . '` | ' . $c['name'] . ' | ' . $c['group'] . ' | ' . $c['_evidence'] . ' | ' . $c['_why'] . " |\n";
    }
    $md .= "\n";
}

$md .= "## ② أسطحُ التوثيقِ المُستثناة — " . count($docs) . " سطحًا\n\n";
$md .= "> ⛔ **لا يُحسب سطحُ توثيقٍ عجزًا ولا يدخل طابورَ بناء** — والحاجبُ `G-DOC-01` يمنع دخولَها أيَّ عدٍّ قادم.\n\n";
$md .= "| # | الإدارة | السطح | النوعُ المنصوص |\n|---:|---|---|---|\n";
$i = 0;
foreach ($docs as $c) { $i++; $md .= '| ' . $i . ' | `' . $c['code'] . '` | ' . $c['name'] . ' | `' . $c['type'] . "` |\n"; }

$md .= "\n## ③ العجزُ موزَّعًا بالإدارة\n\n";
$byDep = array();
foreach ($eligible as $c) { $byDep[$c['code']]['elig'] = ($byDep[$c['code']]['elig'] ?? 0) + 1; }
foreach ($missing as $c)  { $byDep[$c['code']]['miss'] = ($byDep[$c['code']]['miss'] ?? 0) + 1; }
foreach ($A as $c) { $byDep[$c['code']]['a'] = ($byDep[$c['code']]['a'] ?? 0) + 1; }
foreach ($B as $c) { $byDep[$c['code']]['b'] = ($byDep[$c['code']]['b'] ?? 0) + 1; }
foreach ($C as $c) { $byDep[$c['code']]['c'] = ($byDep[$c['code']]['c'] ?? 0) + 1; }
foreach ($D as $c) { $byDep[$c['code']]['d'] = ($byDep[$c['code']]['d'] ?? 0) + 1; }
ksort($byDep);
$md .= "| الرمز | مؤهَّل | غائب | (أ) | (ب) | (ج؟) | (ج) | ٪ العجز |\n|---|---:|---:|---:|---:|---:|---:|---:|\n";
foreach ($byDep as $code => $d) {
    $e = $d['elig'] ?? 0; $m2 = $d['miss'] ?? 0;
    $md .= '| `' . $code . '` | ' . $e . ' | ' . $m2 . ' | ' . ($d['a'] ?? 0) . ' | ' . ($d['b'] ?? 0) . ' | ' . ($d['d'] ?? 0) . ' | ' . ($d['c'] ?? 0)
         . ' | ' . ($e ? number_format($m2 * 100 / $e, 1) : '0.0') . "٪ |\n";
}

$path = $ROOT . '/docs/REPAIR01_20260823/RPR02A_TRIAGE.md';
file_put_contents($path, $md);
if ($jsonOut !== '') {
    file_put_contents($jsonOut, json_encode(array(
        'denominator' => array('cards' => count($cards), 'docs' => count($docs), 'eligible' => count($eligible)),
        'present' => count($present), 'missing' => count($missing),
        'A' => count($A), 'B' => count($B), 'C_maybe' => count($D), 'C' => count($C),
        'rows' => array_map(function ($c) {
            return array('code' => $c['code'], 'name' => $c['name'], 'group' => $c['group'],
                         'basket' => $c['_basket'], 'evidence' => $c['_evidence'], 'why' => $c['_why']);
        }, array_merge($A, $B, $D, $C)),
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

printf("بطاقات %d · توثيق %d · مؤهَّل %d · حاضر %d · غائب %d  =>  (a) %d · (b) %d · (c?) %d · (c) %d\n",
    count($cards), count($docs), count($eligible), count($present), count($missing), count($A), count($B), count($D), count($C));
echo "=> " . $path . "\n";
