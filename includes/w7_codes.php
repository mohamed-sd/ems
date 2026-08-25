<?php
/**
 * includes/w7_codes.php — عرضُ الرموزِ الداخليّةِ بالعربيّة (RPR-W07)
 * ═══════════════════════════════════════════════════════════════════════════
 * **معيارُ نقاءِ لغةِ الواجهة** (‏قرارُ المالك · 2026-08-25) يمنع «الرموزَ
 * التقنيّة» في واجهةِ الأعمال. وقيمُ `ENUM` في جداولِ W07 لاتينيّةٌ بالتصميم —
 * لأنّها **قيمٌ تُقارَن في القاعدةِ وفي `CHECK`**، ونزعُ لاتينيّتِها يفكُّ
 * المقارنةَ بلا خطأٍ يظهر (‏W06 · «نصٌّ يُقارَن لا يُعرَض»).
 *
 * فالفصلُ هنا: **القيمةُ لاتينيّةٌ في السجلِّ، والمعروضُ عربيٌّ من القاموس.**
 * والقاموسُ `repair01_w6_code_dict` — **مصدرٌ واحدٌ** يقرؤه المُصيِّرُ ويفحصه
 * `W6-09`. ⛔ ولا مصفوفةَ مسمّياتٍ عربيّةٍ مكتوبةٌ في هذا الملفّ.
 *
 * وإن غاب القاموسُ أو الصفُّ **يُعاد الرمزُ كما هو** — فالغيابُ يُرى ويُصلَح،
 * ولا يُستَر بترجمةٍ مخترَعةٍ في الشيفرة.
 */
if (!function_exists('ems_w7_code_map')) {
    /**
     * قاموسُ عرضِ الرموز — يُقرأ مرّةً في الطلبِ الواحد.
     * @return array<string,string>
     */
    function ems_w7_code_map($conn = null)
    {
        static $map = null;
        if ($map !== null) { return $map; }
        $map = array();
        if (!($conn instanceof mysqli)) { return $map; }
        $r = @$conn->query("SELECT raw_code, display_ar FROM repair01_w6_code_dict WHERE display_ar <> ''");
        while ($r && $x = $r->fetch_assoc()) { $map[(string) $x['raw_code']] = (string) $x['display_ar']; }
        return $map;
    }
}

if (!function_exists('ems_w7_ar')) {
    /** مسمّى الرمزِ بالعربيّة — والرمزُ نفسُه إن لم يكن في القاموس */
    function ems_w7_ar($code, $conn = null)
    {
        $code = (string) $code;
        if ($code === '') { return '—'; }
        $m = ems_w7_code_map($conn);
        return isset($m[$code]) ? $m[$code] : $code;
    }
}
