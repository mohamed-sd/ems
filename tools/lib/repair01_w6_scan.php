<?php
/**
 * tools/lib/repair01_w6_scan.php
 *   ماسحُ نقاءِ لغةِ الواجهة — REPAIR01 · W06 · **يُعيد القياسَ ولا يقرأ مخزَّنًا**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **قاعدةُ القياسِ الأولى** (‏_CONTEXT): «البوّابةُ تُعيد القياسَ ولا تقرأ ما
 *   خزّنَته». فلا دالّةَ هنا تقرأ `repair01_w6_scope` ولا مخرَجَ أداةٍ أخرى:
 *   كلُّها تقرأ **الجداولَ الحيّةَ أو النصَّ المُصيَّرَ نفسَه**.
 *
 * ◆ **والنصُّ المُصيَّرُ لا صفُّ الجدول** (‏W06 §٦-أ): بلوغُ السايدبارِ يُقاس
 *   بتصييرِه لكلِّ دورٍ جذريٍّ بجلسةِ مستخدمٍ حقيقيّ (`uxui_nav_probe`) —
 *   وصفٌّ نشِطٌ في `nav_items` **لا يعني بندًا يراه الدور** (‏W03).
 *
 * ◆ **وسطرُ الدورةِ يُصيَّر بمنطقِ الترويسةِ نفسِه**: الاستعلامُ والشرطُ
 *   (`COUNT(DISTINCT next_state) = 1`) مأخوذانِ من `includes/page_header.php`
 *   حرفًا — فما لا يُصيَّر لا يُحاسَب عليه، وما يُصيَّر لا ينجو.
 *
 * ◆ **والعتبةُ من السجلِّ** (‏W06 §٥): حدودُ الطولِ تُقرأ من
 *   `repair01_w6_thresholds` — ولا رقمَ مكتوبٌ في هذا الملفّ.
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once dirname(dirname(__DIR__)) . '/app/Services/Ui/UiPurity.php';
require_once dirname(dirname(__DIR__)) . '/app/Services/Ui/UiLabelRegistry.php';
require_once __DIR__ . '/repair01_w6_sources.php';

use App\Services\Ui\UiPurity;
use App\Services\Ui\UiLabelRegistry;

/** قيمةٌ مفردةٌ من استعلام. */
function repair01_w6_one(mysqli $conn, $sql)
{
    $r = $conn->query($sql);
    if (!$r) { return null; }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
}

/**
 * ① … ③ مسحُ المصادرِ المُصيَّرةِ حيًّا: تشكيلٌ · مصطلحٌ تقنيٌّ · معادلة.
 * @return array<string, array{rows:int,dia:int,tech:int,eq:int,decor:int,samples:array}>
 */
function repair01_w6_scan_sources(mysqli $conn)
{
    $out = array();
    foreach (repair01_w6_rendered_sources() as $key => $src) {
        $rows = repair01_w6_read($conn, $src);
        $c = (int) $src['composite'] === 1;
        $m = array('rows' => count($rows), 'dia' => 0, 'tech' => 0, 'eq' => 0, 'decor' => 0, 'samples' => array());
        foreach ($rows as $rk => $v) {
            $hit = array();
            if (UiPurity::hasDiacritics($v, $c)) { $m['dia']++; $hit[] = 'تشكيل'; }
            if (UiPurity::hasTechTerm($v, $c))   { $m['tech']++; $hit[] = 'تقني'; }
            if (UiPurity::hasEquation($v, $c))   { $m['eq']++; $hit[] = 'معادلة'; }
            if (UiPurity::hasDecoration($v, $c)) { $m['decor']++; }
            if ($hit && count($m['samples']) < 3) {
                $m['samples'][] = $rk . ' [' . implode('·', $hit) . '] ' . mb_substr($v, 0, 70);
            }
        }
        $out[$key] = $m;
    }
    return $out;
}

/**
 * النصُّ المُصيَّرُ في السايدبار — مجموعةٌ وقسمٌ وبندٌ لكلِّ دورٍ جذريّ.
 * @return array{groups:array<string,int>, sections:array<string,int>, labels:array<string,int>, roles:int, positions:int}
 */
function repair01_w6_rendered_text($ROOT, mysqli $conn)
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $cache = array('groups' => array(), 'sections' => array(), 'labels' => array(), 'roles' => 0, 'positions' => 0);
    $probe = $ROOT . '/includes/uxui_nav_probe.php';
    if (!is_file($probe)) { return $cache; }
    require_once $ROOT . '/includes/unified_nav.php';
    require_once $probe;
    if (!function_exists('uxp_render_role') || !function_exists('uxp_root_roles')) { return $cache; }
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    foreach (uxp_root_roles() as $rid) {
        $pos = uxp_render_role($conn, (int) $rid);
        if ($pos) { $cache['roles']++; }
        foreach ($pos as $p) {
            $cache['positions']++;
            foreach (array('group' => 'groups', 'section' => 'sections', 'label' => 'labels') as $f => $b) {
                if (!isset($p[$f])) { continue; }
                $s = trim((string) $p[$f]);
                if ($s === '') { continue; }
                $cache[$b][$s] = isset($cache[$b][$s]) ? $cache[$b][$s] + 1 : 1;
            }
        }
    }
    return $cache;
}

/**
 * ① … ③ على النصِّ المُصيَّرِ نفسِه — لا على صفوفِ الجداول.
 * @return array{n:int, dia:array, tech:array, eq:array, decor:array}
 */
function repair01_w6_scan_rendered($ROOT, mysqli $conn)
{
    $r = repair01_w6_rendered_text($ROOT, $conn);
    $o = array('n' => 0, 'dia' => array(), 'tech' => array(), 'eq' => array(), 'decor' => array());
    foreach (array('groups' => 'مجموعة', 'sections' => 'قسم', 'labels' => 'بند') as $b => $ar) {
        foreach (array_keys($r[$b]) as $s) {
            $o['n']++;
            if (UiPurity::hasDiacritics($s)) { $o['dia'][] = "$ar: $s"; }
            if (UiPurity::hasTechTerm($s))   { $o['tech'][] = "$ar: $s"; }
            if (UiPurity::hasEquation($s))   { $o['eq'][] = "$ar: $s"; }
            if (UiPurity::hasDecoration($s)) { $o['decor'][] = "$ar: $s"; }
        }
    }
    return $o;
}

/**
 * سطرُ الدورةِ كما يُصيَّر في الترويسة — **بمنطقِ `page_header` حرفًا**:
 * الشاشةُ تُصيِّر سطرَها إن كان لملفِّها حالةٌ تاليةٌ واحدةٌ متمايزة.
 * @return array{screens:int, texts:array<string>, dia:array, tech:array, eq:array}
 */
function repair01_w6_scan_cycle(mysqli $conn)
{
    $o = array('screens' => 0, 'texts' => array(), 'dia' => array(), 'tech' => array(), 'eq' => array());
    $q = $conn->query("SELECT screen_file,
                              COUNT(DISTINCT next_state) dn, MAX(next_state) ns, MAX(output_doc) od
                         FROM gov_screen_cycle
                        WHERE next_state NOT IN ('', '—', '-')
                        GROUP BY screen_file");
    while ($q && $x = $q->fetch_assoc()) {
        if ((int) $x['dn'] !== 1) { continue; }
        $o['screens']++;
        $od = (string) $x['od'];
        $shown = (string) $x['ns'];
        if ($od !== '' && mb_strpos($od, 'بلا مستندٍ رسمي') === false
            && mb_strpos($od, 'بلا مستند رسمي') === false && $od !== '—' && $od !== '-') {
            $shown .= ' ينتج: ' . $od;
        }
        $o['texts'][(string) $x['screen_file']] = $shown;
        if (UiPurity::hasDiacritics($shown)) { $o['dia'][] = $x['screen_file'] . ': ' . mb_substr($shown, 0, 60); }
        if (UiPurity::hasTechTerm($shown))   { $o['tech'][] = $x['screen_file'] . ': ' . mb_substr($shown, 0, 60); }
        if (UiPurity::hasEquation($shown))   { $o['eq'][] = $x['screen_file'] . ': ' . mb_substr($shown, 0, 60); }
    }
    return $o;
}

/**
 * ④ الطولُ الزائد — **الحدُّ من السجلِّ لا من الشيفرة**.
 * يُقاس على المسمّياتِ المسجَّلةِ بسياقِها وعلى بنودِ السايدبارِ المُصيَّرة.
 * @return array{checked:int, over:array<string>, limits:array<string,int>}
 */
function repair01_w6_scan_length($ROOT, mysqli $conn)
{
    $lim = array();
    $q = $conn->query("SELECT threshold_key, value_no FROM repair01_w6_thresholds");
    while ($q && $x = $q->fetch_row()) { $lim[(string) $x[0]] = (int) $x[1]; }

    $o = array('checked' => 0, 'over' => array(), 'limits' => $lim);
    if (!$lim) { return $o; }   /* لا عتبةَ مسجَّلة ⇒ لا قياس — ويُعلَن ولا يُعَدُّ صفرًا */

    $q = $conn->query("SELECT technical_key, arabic_ui_label, short_label, allowed_context
                         FROM repair01_ui_labels WHERE label_state <> 'DEPRECATED'");
    while ($q && $x = $q->fetch_assoc()) {
        $ctx = trim((string) $x['allowed_context']);
        if ($ctx === '') { continue; }
        foreach (preg_split('/[\s,·]+/u', $ctx) as $one) {
            $k = 'MAX_LEN_' . strtoupper(trim($one));
            if (!isset($lim[$k])) { continue; }
            $o['checked']++;
            /* الصيغةُ القصيرةُ هي المقصودةُ بالحدِّ إن وُجدت — وإلّا الطويلة. */
            $probe = trim((string) $x['short_label']) !== '' ? $x['short_label'] : $x['arabic_ui_label'];
            if (UiPurity::tooLong($probe, $lim[$k])) {
                $o['over'][] = $x['technical_key'] . ' [' . $one . ' > ' . $lim[$k] . '] '
                             . mb_strlen($probe, 'UTF-8') . ' حرفًا';
            }
        }
    }

    $r = repair01_w6_rendered_text($ROOT, $conn);
    $menuLim = isset($lim['MAX_LEN_MENU']) ? $lim['MAX_LEN_MENU'] : 0;
    foreach (array_keys($r['labels']) as $s) {
        $o['checked']++;
        if (UiPurity::tooLong($s, $menuLim)) {
            $o['over'][] = 'بندٌ مُصيَّر [MENU > ' . $menuLim . '] ' . mb_strlen($s, 'UTF-8') . ' حرفًا: ' . $s;
        }
    }
    return $o;
}

/**
 * اسمٌ خارجَ السجلّ: مسمًّى **يُصيَّر** وليس له صفٌّ في `repair01_ui_labels`.
 * @return array{rendered:int, missing:array<string>}
 */
function repair01_w6_unregistered($ROOT, mysqli $conn)
{
    $known = array();
    $q = $conn->query("SELECT arabic_ui_label FROM repair01_ui_labels WHERE arabic_ui_label <> ''");
    while ($q && $x = $q->fetch_row()) { $known[trim((string) $x[0])] = true; }

    $r = repair01_w6_rendered_text($ROOT, $conn);
    $o = array('rendered' => 0, 'missing' => array());
    foreach (array('groups' => 'مجموعة', 'labels' => 'بند') as $b => $ar) {
        foreach (array_keys($r[$b]) as $s) {
            $o['rendered']++;
            if (!isset($known[$s])) { $o['missing'][] = "$ar: $s"; }
        }
    }
    return $o;
}

/**
 * مسمًّى متقاعدٌ حيّ: صيغةٌ وُسمت `deprecated_label` وما زالت في مصدرٍ يُصيَّر.
 * @return array{checked:int, alive:array<string>}
 */
function repair01_w6_deprecated_live(mysqli $conn)
{
    $dep = array();
    $q = $conn->query("SELECT technical_key, deprecated_label FROM repair01_ui_labels
                        WHERE deprecated_label <> ''");
    while ($q && $x = $q->fetch_assoc()) { $dep[(string) $x['deprecated_label']] = (string) $x['technical_key']; }

    $o = array('checked' => count($dep), 'alive' => array());
    if (!$dep) { return $o; }

    /* المصادرُ المُصيَّرةُ وحدَها — والمقارنةُ بالنصِّ الكاملِ لا بالجزء:
       «الموظفون والمشغّلون» متقاعدةٌ، و«الموظفون» ليست جزءًا منها في الحكم. */
    foreach (repair01_w6_rendered_sources() as $src) {
        $rows = repair01_w6_read($conn, $src);
        foreach ($rows as $rk => $v) {
            $v = trim((string) $v);
            if (isset($dep[$v])) {
                $o['alive'][] = $src['table'] . '.' . $src['column'] . ' › ' . $rk . ' ⇐ ' . $dep[$v];
            }
        }
    }
    return $o;
}

/**
 * رمزٌ داخليٌّ خامٌّ في نصٍّ مُصيَّرٍ بلا عرضٍ في القاموس (‏§٤-٦).
 * @return array{dict:int, raw:array<string>}
 */
function repair01_w6_raw_codes($ROOT, mysqli $conn)
{
    $dict = array();
    $q = $conn->query("SELECT raw_code FROM repair01_w6_code_dict");
    while ($q && $x = $q->fetch_row()) { $dict[(string) $x[0]] = true; }

    $o = array('dict' => count($dict), 'raw' => array());
    $r = repair01_w6_rendered_text($ROOT, $conn);
    $texts = array_merge(array_keys($r['groups']), array_keys($r['sections']), array_keys($r['labels']));
    $cy = repair01_w6_scan_cycle($conn);
    foreach ($cy['texts'] as $t) { $texts[] = $t; }
    foreach ($texts as $s) {
        $probe = UiPurity::maskProtected($s);
        if (preg_match_all('/\b[A-Za-z][A-Za-z0-9_.]*\b/u', $probe, $mm)) {
            foreach ($mm[0] as $w) { $o['raw'][] = $w . ' ⇐ ' . mb_substr($s, 0, 60); }
        }
    }
    return $o;
}

/**
 * صنفُ الظهورِ (‏§٤-٧): `DEVELOPER_ONLY` **لا يُصيَّر للمستخدمِ النهائيّ**.
 * @return array{dev:int, leaked:array<string>}
 */
function repair01_w6_dev_only_leak($ROOT, mysqli $conn)
{
    $dev = array();
    $q = $conn->query("SELECT technical_key, arabic_ui_label FROM repair01_ui_labels
                        WHERE visibility_class = '" . UiLabelRegistry::DEVELOPER_ONLY . "'
                          AND arabic_ui_label <> ''");
    while ($q && $x = $q->fetch_assoc()) { $dev[(string) $x['arabic_ui_label']] = (string) $x['technical_key']; }

    $o = array('dev' => count($dev), 'leaked' => array());
    if (!$dev) { return $o; }
    $r = repair01_w6_rendered_text($ROOT, $conn);
    foreach (array('groups', 'sections', 'labels') as $b) {
        foreach (array_keys($r[$b]) as $s) {
            if (isset($dev[$s])) { $o['leaked'][] = $dev[$s] . ' ⇐ ' . $s; }
        }
    }
    return $o;
}
