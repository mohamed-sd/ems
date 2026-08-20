<?php
/**
 * tools/uxui_owner_tables.php — عُدّةٌ مشترَكةٌ لقياسِ ما تكتبه الشاشةُ وما تقرؤه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تُستعمل في شاهدِ المِلكيةِ وفي شجرةِ القرارِ معًا — **وتعريفٌ واحدٌ في موضعٍ
 *   واحد**: لو نُسخ المنطقُ في ملفَّين لتفرّقا مع أولِ تعديلٍ وصار للنظامِ
 *   حكمان على الشاشةِ الواحدة.
 * ◆ **ومسارُ الكتابةِ يتبع الخدمات**: الشاشاتُ تفوّض الكتابةَ لـ`app/Services`
 *   ولمُدرَجاتِ `includes/` — فقياسُ ملفِّ الشاشةِ يقيس الواجهةَ لا الفعل.
 * ◆ **وطبقةُ المنصةِ تُستبعَد بالانتشارِ لا بقائمةٍ مؤلَّفة**: جدولٌ يكتبه أو
 *   يقرؤه ثلثُ الشاشاتِ فأكثرُ طبقةٌ مشترَكةٌ — **ولا يُملِّك أحدًا ولا يُثبت حاجة**.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('uot_tables')) {
    /** كلُّ جداولِ الأساسِ — فلا يُحسب اسمٌ لا يقابله جدول */
    function uot_tables(mysqli $conn) {
        $t = array();
        $r = mysqli_query($conn, "SELECT TABLE_NAME FROM information_schema.TABLES
                                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'");
        while ($r && ($x = mysqli_fetch_row($r))) { $t[mb_strtolower($x[0])] = 1; }
        return $t;
    }
}

if (!function_exists('uot_path_src')) {
    /** مصدرُ الشاشةِ + مُدرَجاتِها + خدماتِ النطاقِ التي تناديها (بعمقٍ محدود) */
    function uot_path_src($path, $ROOT, $depth = 2, &$seen = null) {
        if ($seen === null) { $seen = array(); }
        $real = realpath($path);
        if ($real === false || isset($seen[$real]) || $depth < 0) { return ''; }
        $seen[$real] = 1;
        $src = (string) @file_get_contents($real);
        if ($src === '') { return ''; }
        $all = $src; $dir = dirname($real);
        if (preg_match_all('~(?:require|include)(?:_once)?\s*\(?\s*[\'"]([^\'"]+\.php)[\'"]~i', $src, $m)) {
            foreach ($m[1] as $inc) {
                $cand = (strpos($inc, '/') === 0) ? $ROOT . $inc : $dir . '/' . $inc;
                $all .= "\n" . uot_path_src($cand, $ROOT, $depth - 1, $seen);
            }
        }
        if (preg_match_all('~__DIR__\s*\.\s*[\'"]([^\'"]+\.php)[\'"]~', $src, $m)) {
            foreach ($m[1] as $inc) { $all .= "\n" . uot_path_src($dir . '/' . $inc, $ROOT, $depth - 1, $seen); }
        }
        if (preg_match_all('~\\\\?App\\\\Services\\\\([A-Za-z0-9_\\\\]+)\s*::~', $src, $m)) {
            foreach ($m[1] as $cls) {
                $rel = str_replace('\\', '/', $cls) . '.php';
                $all .= "\n" . uot_path_src($ROOT . '/app/Services/' . $rel, $ROOT, $depth - 1, $seen);
            }
        }
        return $all;
    }
}

if (!function_exists('uot_writes')) {
    /** الجداولُ التي يكتب فيها هذا المصدر */
    function uot_writes($src, array $known, array $exclude = array()) {
        $out = array();
        $pats = array(
            '~\bINSERT\s+(?:IGNORE\s+)?INTO\s+`?([a-zA-Z0-9_]+)`?~i',
            '~\bREPLACE\s+INTO\s+`?([a-zA-Z0-9_]+)`?~i',
            '~\bUPDATE\s+`?([a-zA-Z0-9_]+)`?\s+SET~i',
            '~\bDELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?~i',
        );
        foreach ($pats as $p) {
            if (preg_match_all($p, $src, $m)) {
                foreach ($m[1] as $t) {
                    $t = mb_strtolower($t);
                    if (isset($known[$t]) && !isset($exclude[$t])) { $out[$t] = 1; }
                }
            }
        }
        return $out;
    }
}

if (!function_exists('uot_reads')) {
    /** الجداولُ التي يقرأ منها هذا المصدر */
    function uot_reads($src, array $known, array $exclude = array()) {
        $out = array();
        if (preg_match_all('~\b(?:FROM|JOIN)\s+`?([a-zA-Z0-9_]+)`?~i', $src, $m)) {
            foreach ($m[1] as $t) {
                $t = mb_strtolower($t);
                if (isset($known[$t]) && !isset($exclude[$t])) { $out[$t] = 1; }
            }
        }
        return $out;
    }
}

if (!function_exists('uot_platform')) {
    /**
     * طبقةُ المنصةِ **بالانتشارِ المقيس** — لا بقائمةٍ مكتوبةٍ بيد.
     * تُقاس على عيّنةٍ من المساراتِ المسجَّلة، ويُستبعَد ما تجاوزَ الثلث.
     */
    function uot_platform(mysqli $conn, $ROOT, array $known, $threshold = 0.34, $limit = 200) {
        static $cache = null;
        if ($cache !== null) { return $cache; }
        $routes = array();
        $r = mysqli_query($conn, "SELECT DISTINCT route FROM gov_space_appearances LIMIT " . (int) $limit);
        while ($r && ($x = mysqli_fetch_row($r))) { $routes[] = $x[0]; }
        $freq = array(); $n = 0;
        foreach ($routes as $rt) {
            $f = $ROOT . '/' . $rt;
            if (!is_file($f)) { continue; }
            $sn = array();
            $src = uot_path_src($f, $ROOT, 2, $sn);
            if ($src === '') { continue; }
            $seen = uot_writes($src, $known) + uot_reads($src, $known);
            if (!$seen) { continue; }
            $n++;
            foreach (array_keys($seen) as $t) { $freq[$t] = isset($freq[$t]) ? $freq[$t] + 1 : 1; }
        }
        $cache = array();
        if ($n > 0) {
            foreach ($freq as $t => $c) { if ($c / $n >= $threshold) { $cache[$t] = 1; } }
        }
        return $cache;
    }
}
