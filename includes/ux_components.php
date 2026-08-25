<?php
/**
 * includes/ux_components.php — مساعدُ مكوّناتِ UXW-01 (الحالاتُ التسعُ · الخطوةُ التالية · السقفُ الموقوف)
 * ───────────────────────────────────────────────────────────────────────────
 * الشاشةُ تنادي هذه الدوالَّ ولا تبني حالةً بيدِها — والنصُّ لغةُ مؤسسةٍ
 * تمرَّر من الشاشةِ («لا توجد بياناتٌ لهذه الفترة») لا رسالةَ مطوِّر.
 * الأنماطُ في assets/css/ems-states.css من الرموزِ حصرًا.
 */

if (!function_exists('ems_state')) {
    /**
     * حالةُ شاشةٍ من التسع: loading · empty · noresults · error · noperm ·
     * readonly · offline · syncpending · stale
     */
    function ems_state(string $kind, string $title, string $hint = '', string $actionHtml = '', bool $hidden = false): string
    {
        $kinds = array('loading','empty','noresults','error','noperm','readonly','offline','syncpending','stale');
        if (!in_array($kind, $kinds, true)) { $kind = 'empty'; }
        $h = $hidden ? ' hidden' : '';
        $icon = ($kind === 'loading') ? '<span class="ems-spinner" aria-hidden="true"></span>' : '';
        $out  = '<div class="ems-state ems-state-' . $kind . '"' . $h . ' role="status" aria-live="polite">';
        $out .= $icon;
        $out .= '<div class="ems-state-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>';
        if ($hint !== '') {
            $out .= '<div class="ems-state-hint">' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if ($actionHtml !== '') {
            $out .= '<div class="ems-state-action">' . $actionHtml . '</div>';
        }
        $out .= '</div>';
        return $out;
    }
}

if (!function_exists('ems_states_bundle')) {
    /**
     * الحزمةُ الدنيا الإلزاميةُ لكلِّ شاشة (بوابةُ المنعِ ٩): تحميلٌ وفراغٌ وخطأٌ —
     * مخفيةٌ افتراضًا ويُظهرها منطقُ الشاشةِ عند حالِها.
     */
    function ems_states_bundle(string $emptyTitle = 'لا توجد بيانات لهذه الفترة',
                               string $emptyHint  = 'غير الفترة أو تحقق من توفر السجلات'): string
    {
        return ems_state('loading', 'جار تحميل البيانات', '', '', true)
             . ems_state('empty', $emptyTitle, $emptyHint, '', true)
             . ems_state('error', 'تعذر عرض البيانات', 'أعد المحاولة — وإن استمر الخلل أبلغ عن مشكلة من هذه الشاشة', '', true);
    }
}

if (!function_exists('ems_next_step')) {
    /** الخطوةُ التاليةُ في الدورةِ المستندية (بوابةُ المنعِ ١٢) — تظهر في ترويسةِ شاشاتِ الدورة */
    function ems_next_step(string $label, string $href = ''): string
    {
        /* «الخطوةُ التالية» ⇐ «الخطوة التالية» — نقاءُ لغةِ الواجهة (‏W06 §٤-٥):
           السطرُ يُصيَّر في ترويسةِ كلِّ شاشةٍ لها دورة، فتشكيلُ لافتتِه أكثرُ
           نصٍّ مشكولٍ يقع عليه بصرُ المستخدمِ في اليوم. */
        $inner = '<span class="ems-next-step-label">الخطوة التالية</span>'
               . '<span class="ems-next-step-value">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        if ($href !== '') {
            return '<a class="ems-next-step" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $inner . '</a>';
        }
        return '<span class="ems-next-step">' . $inner . '</span>';
    }
}

if (!function_exists('ems_cap_hold_card')) {
    /** بطاقةُ السقفِ الموقوف — الوقفُ الآمنُ ليس عطلًا (UXW-01 §6-3) */
    function ems_cap_hold_card(string $what = 'هذه المعاملة'): string
    {
        return '<div class="ems-cap-hold" role="status">'
             . '<div><div class="ems-cap-hold-title">بانتظار اعتماد السقف</div>'
             . '<div>' . htmlspecialchars($what, ENT_QUOTES, 'UTF-8')
             . ' موقوفة لأن سقفها لم يعتمد رسميا — الطلب محفوظ ويستأنف حين يعتمد.</div></div></div>';
    }
}
