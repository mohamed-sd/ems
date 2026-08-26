<?php
/**
 * Finance/w11_view.php — أدواتُ عرضٍ لأسطحِ المرحلةِ الحاديةَ عشرة (RPR-W11)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الرمزُ يبقى لاتينيًّا في السجلِّ ويُعرَض عربيًّا من القاموس** — القيمةُ
 *   تُقارَن في الشيفرةِ وفي `CHECK`، ونزعُ لاتينيّتِها يفكُّ المقارنةَ بلا خطإٍ
 *   يظهر (‏W06 · «نصٌّ يُقارَن لا يُعرَض»). فالعرضُ عبرَ `repair01_w6_code_dict`
 *   وحدَه، و⛔ **لا مصفوفةَ مسمّياتٍ عربيّةٍ مكتوبةٌ هنا**.
 *
 * ◆ **والعدّاداتُ مشتقّةٌ من الصفوفِ المقروءةِ لا مخزَّنة** — فالبطاقةُ تعرض
 *   ما في الجدولِ نفسِه، ورقمانِ من مصدرَين يتفرّقان.
 */
require_once __DIR__ . '/../includes/w7_codes.php';

if (!function_exists('ems_w11_ar')) {
    /** مسمّى الرمزِ من القاموسِ المركزيّ — والرمزُ نفسُه إن غاب */
    function ems_w11_ar($code)
    {
        $c = isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli ? $GLOBALS['conn'] : null;
        return ems_w7_ar($code, $c);
    }
    function ems_w11_state($v)      { return htmlspecialchars(ems_w11_ar($v)); }
    function ems_w11_adj_kind($v)   { return htmlspecialchars(ems_w11_ar($v)); }
    function ems_w11_step($v)       { return htmlspecialchars(ems_w11_ar($v)); }
    function ems_w11_item_state($v) { return htmlspecialchars(ems_w11_ar($v)); }
    function ems_w11_vessel($v)     { return htmlspecialchars(ems_w11_ar($v)); }
    function ems_w11_instr($v)      { return htmlspecialchars(ems_w11_ar($v)); }

    /** عدُّ الصفوفِ التي يساوي عمودُها قيمةً — بالمقارنةِ النصّيّةِ لا بالنوع */
    function ems_w11_count(array $rows, $col, $val)
    {
        $n = 0;
        foreach ($rows as $r) {
            if (isset($r[$col]) && (string) $r[$col] === (string) $val) { $n++; }
        }
        return $n;
    }

    /** عددُ القيمِ المتمايزةِ في عمود */
    function ems_w11_distinct(array $rows, $col)
    {
        $seen = array();
        foreach ($rows as $r) {
            if (isset($r[$col]) && (string) $r[$col] !== '') { $seen[(string) $r[$col]] = true; }
        }
        return count($seen);
    }

    /** مجموعُ عمودٍ صحيح */
    function ems_w11_sum(array $rows, $col)
    {
        $s = 0;
        foreach ($rows as $r) { $s += isset($r[$col]) ? (int) $r[$col] : 0; }
        return $s;
    }

    /** مجموعُ عمودٍ عشريّ */
    function ems_w11_sumf(array $rows, $col)
    {
        $s = 0.0;
        foreach ($rows as $r) { $s += isset($r[$col]) ? (float) $r[$col] : 0; }
        return $s;
    }

    /** مجموعُ حركةٍ باتّجاهها */
    function ems_w11_dir(array $rows, $dir)
    {
        $s = 0.0;
        foreach ($rows as $r) {
            if (isset($r['direction']) && (string) $r['direction'] === (string) $dir) {
                $s += (float) $r['amount'];
            }
        }
        return $s;
    }

    /** عملاءُ تجاوز تعرُّضُهم حدَّهم — مقيسٌ من الصفَّين لا مخزَّن */
    function ems_w11_over(array $rows)
    {
        $n = 0;
        foreach ($rows as $r) {
            if ((float) $r['exposure_amount'] > (float) $r['limit_amount'] + 0.005) { $n++; }
        }
        return $n;
    }

    /** بنودُ قائمةِ الإقفالِ التي ما زالت تحجب */
    function ems_w11_blocking(array $rows)
    {
        $n = 0;
        foreach ($rows as $r) {
            if ((int) $r['required'] !== 1) { continue; }
            if ((string) $r['item_state'] === 'done' || (string) $r['item_state'] === 'na') { continue; }
            if ((int) $r['blocks_close'] === 0 && trim((string) $r['exception_reason']) !== '') { continue; }
            $n++;
        }
        return $n;
    }

    /** بنودٌ مرَّت باستثناءٍ موثَّق */
    function ems_w11_excepted(array $rows)
    {
        $n = 0;
        foreach ($rows as $r) {
            if (trim((string) $r['exception_reason']) !== '') { $n++; }
        }
        return $n;
    }

    /** جلساتُ جردٍ بفرقٍ غيرِ صفريّ */
    function ems_w11_diffs(array $rows)
    {
        $n = 0;
        foreach ($rows as $r) { if (abs((float) $r['difference']) > 0.005) { $n++; } }
        return $n;
    }
}
