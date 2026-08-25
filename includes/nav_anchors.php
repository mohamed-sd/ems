<?php
/**
 * includes/nav_anchors.php — **مراسي القشرةِ من السجلِّ المعياريِّ لا من الشيفرة**
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W02 §٤-٣: «ولّدِ السايدبارَ من السجلِّ المعياريّ — وأزلْ كلَّ مسارِ
 * تحريرٍ يدويٍّ للبند».
 *
 * ◆ **ما كان**: خمسةُ مساراتٍ حرفيّةٍ مكتوبةٍ بيدٍ في ملفَّي القشرةِ والمصيِّرِ
 *   الموحَّد — المراسلاتُ والرئيسيةُ ومركزا التقاريرِ والإعدادات. تغييرُ وجهةِ
 *   أيٍّ منها أو اسمِه **تحريرُ ملفِّ قشرة**، فيتفرَّق ما في `nav_canonical`
 *   عمّا يراه المستخدمُ بلا أن يُنبِّه أحدٌ.
 *
 * ◆ **ولماذا لا يُسمّى ملفُّ القشرةِ هنا بحرفِه**: كاشفُ `RP-01` في
 *   `tools/lib/repair01_debt_scan.php` يعرِّف «الشاشةَ الحيّةَ» بأنّها **ملفٌّ
 *   يذكر اسمَ القشرة** — فملفُّ عُدّةٍ يشرح القشرةَ يُحسب شاشةً بلا سجلّ،
 *   ويرتفع الدَّينُ بواحدٍ لأنَّ تعليقًا ذكر اسمًا. **والكاشفُ يرصد مفرداتِه هو**
 *   (نمطُ `RP-07` مع `main/global_search.php`). وخطُّ أساسِ السقّاطةِ مقفلُ
 *   الاتّجاه، فلم تُحرَّك تلك الدالّة؛ والقياسُ المصحَّحُ في
 *   `repair01_w2_live_screens()` — إغلاقٌ عوديٌّ على **جملةِ التضمين** لا على
 *   ذكرِ اسم.
 *
 * ◆ **ما صار**: الغلافُ ينادي **مفتاحَ مِرساة** (`CHATS`)، والسجلُّ يعطي
 *   المسارَ والاسمَ المعتمَد (`nav_canonical.anchor_key`)، و`nav_icon_map`
 *   يعطي الأيقونة. **ثلاثةُ مصادرَ لكلٍّ دورُه، ولا رابعَ في الشيفرة.**
 *
 * ◆ **والفشلُ صامتٌ بقصد**: مرساةٌ بلا صفٍّ في السجلِّ **لا تُطبع** — ولا
 *   يُصطنع لها مسارٌ احتياطيٌّ حرفيٌّ في الشيفرة، فذاك عودةُ العطبِ نفسِه
 *   من بابِ «الاحتياط». والهجرةُ `2027_11_17_nav_anchor_key.php` تسقط إن
 *   نقصت مرساةٌ واحدة، فالنقصُ يُرصد هناك لا يُغطّى هنا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_nav_anchor')) {
    /**
     * صفُّ المرساةِ من السجلِّ المعياريّ.
     * @return array{route:string,label:string,icon:string}|null
     */
    function ems_nav_anchor($conn, $key)
    {
        /* ⚠ الذاكرةُ تُملأ **متى نجح الاستعلامُ فقط**: نداءٌ أوّلُ باتصالٍ `null`
           (وقع في `printStageNav` التي لا تأخذ `$conn` وسيطًا) كان سيثبّت
           ذاكرةً فارغةً فتصمت المراسي في بقيّةِ الصفحة صمتًا لا يُرى. */
        static $cache = null;
        if ($cache === null) {
            if ($conn instanceof mysqli) {
                $cache = array();
                $res = @mysqli_query($conn,
                    "SELECT `anchor_key`, `route`, `canonical_ar`, `current_label`
                       FROM `nav_canonical` WHERE `anchor_key` IS NOT NULL AND `anchor_key` <> ''");
                if (!$res) { $cache = null; return null; }
                while ($row = mysqli_fetch_assoc($res)) {
                    /* اسمُ الانتقالِ يغلب المعياريَّ متى وُجد — قاعدةُ الأربعةِ مصادرَ
                       نفسُها التي يطبّقها المصيِّر، فلا يتفرَّق اسمُ المرساةِ عن أختِها. */
                    $label = trim((string) $row['current_label']);
                    if ($label === '') { $label = trim((string) $row['canonical_ar']); }
                    $cache[$row['anchor_key']] = array(
                        'route' => (string) $row['route'],
                        'label' => $label,
                    );
                }
            } else {
                return null;   /* لا اتصالَ — ولا تُثبَّت ذاكرةٌ فارغة */
            }
        }
        if (!isset($cache[$key])) { return null; }
        $a = $cache[$key];
        $icon = 'fa fa-link';
        if (function_exists('ems_nav_icon_for')) {
            $ic = ems_nav_icon_for($a['label'], $a['route']);
            if (is_string($ic) && $ic !== '') { $icon = $ic; }
        }
        return array('route' => $a['route'], 'label' => $a['label'], 'icon' => $icon);
    }
}

if (!function_exists('ems_nav_anchor_li')) {
    /**
     * وسمُ بندِ المرساةِ جاهزًا — أو '' إن لا صفَّ لها في السجلّ.
     *
     * @param string $attrs وسومٌ إضافيّةٌ على `<a>` (مُعرِّفُ رابطِ المراسلاتِ مثلًا)
     * @param string $tail  وسمٌ يُلحق داخلَ `<a>` (شارةُ غيرِ المقروء)
     */
    function ems_nav_anchor_li($conn, $key, $basePrefix = '../', $attrs = '', $tail = '')
    {
        $a = ems_nav_anchor($conn, $key);
        if ($a === null) { return ''; }
        return '<li><a href="' . htmlspecialchars($basePrefix . $a['route'], ENT_QUOTES, 'UTF-8') . '"'
             . ($attrs !== '' ? ' ' . $attrs : '') . '>'
             . '<i class="' . htmlspecialchars($a['icon'], ENT_QUOTES, 'UTF-8') . '"></i> '
             . '<span class="sidebar-link-text">' . htmlspecialchars($a['label'], ENT_QUOTES, 'UTF-8') . '</span>'
             . $tail . '</a></li>' . "\n";
    }
}
