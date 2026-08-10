<?php
/**
 * includes/kpi_card.php — بطاقةُ المؤشر: سبعةُ حقولٍ إلزامًا (SH-09 · AC-U10)
 * ═══════════════════════════════════════════════════════════════════════════
 * الحكم: «سبعةُ حقولٍ إلزامًا: العنوانُ والقيمةُ والوحدةُ والفترةُ والمقارنةُ
 * والحالةُ والتعمّق» · «والمقارنةُ الغائبةُ تُعلَن ‹بلا مقارنةٍ معلنة› ولا
 * تُلفَّق» · «ورقمٌ لا يُتعمَّق فيه لا يُقرَّر عليه — فالتعمّقُ إلزاميٌّ لا ترف»
 * · «المكوّنُ يرفض التصييرَ بأقلَّ من السبعةِ فيصير الحكمُ بالبناء».
 *
 * لماذا مصيِّرٌ في PHP لا في جافاسكربت وحدَها:
 *   كان في النظامِ `EmsUI.kpiCard` بالعقدِ نفسِه — **بصفرِ مستهلك**، ومستعمِلوه
 *   الوحيدون أدواتُ تدقيقٍ تفحص أنه بُني (عيبُ MD-05). والسببُ بنيويٌّ لا
 *   إهمال: الشاشاتُ الثلاثُ تصيِّر خادميًّا، فمكوّنٌ يعيش في المتصفحِ لا سبيلَ
 *   لها إليه. فالمكوّنُ يُوضع حيثُ يُصيَّر الرقمُ فعلًا.
 *
 * ولماذا «المقارنةُ الغائبة» لا تمنع التصيير:
 *   الحكمُ يفرّق بين نقصٍ يُفسد الرقمَ ونقصٍ يُعلَن. فالعنوانُ والقيمةُ والوحدةُ
 *   والفترةُ والحالةُ والتعمّقُ تمنع غيبتُها التصييرَ — أما المقارنةُ فغيبتُها
 *   **تُعلَن نصًّا**: «بلا مقارنة معلنة». وحقلُها حاضرٌ في البطاقةِ إذًا،
 *   فالسبعةُ سبعةٌ — والفرقُ أن السابعَ يقول الحقَّ عن نفسِه بدل أن يُلفَّق.
 */

if (!function_exists('ems_kpi_card')) {

    /** النغماتُ المعلنةُ وحدَها — وأيُّ غيرِها يُعَدُّ عقدًا مكسورًا لا نغمةً صامتة. */
    function ems_kpi_tones()
    {
        return array(
            'ok'      => array('ems-kpi-ok',   'سليم'),
            'warn'    => array('ems-kpi-warn', 'إنذار'),
            'err'     => array('ems-kpi-err',  'حرج'),
            'neutral' => array('',             'محايد'),
        );
    }

    /**
     * يصيّر بطاقةَ مؤشرٍ واحدةً، أو بطاقةَ عقدٍ مكسورٍ إن نقص واجب.
     *
     * @param array $k الحقول:
     *   title      (إلزامي) عنوانُ المؤشر
     *   value      (إلزامي) القيمة — «0» قيمةٌ صحيحةٌ لا غياب
     *   unit       (إلزامي) الوحدةُ: «أمر» · «سجل» · «ساعة»
     *   period     (إلزامي) الفترةُ: «لحظي (…)» · «أغسطس 2026»
     *   status     (إلزامي) واحدةٌ من ems_kpi_tones()
     *   drill      (إلزامي) وجهةُ التعمّق
     *   comparison (اختياري) — غيبتُها تُعلَن ولا تُلفَّق
     *   icon       (اختياري) صنفُ أيقونةِ FontAwesome
     *   scope      (اختياري) سطرُ نطاقٍ إضافيّ
     *   class      (اختياري) أصنافُ تخطيطٍ (ems-col-4 …)
     */
    function ems_kpi_card(array $k)
    {
        $E = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

        /* ◆ «0» و«0.0» قيمٌ صحيحةٌ — والفحصُ بالغيابِ لا بالخوائِ المنطقيّ،
             وإلا رفض المكوّنُ أصحَّ الأرقام. */
        $blank = function ($v) { return $v === null || (is_string($v) && trim($v) === ''); };

        $need = array(
            'title'  => 'العنوان',
            'value'  => 'القيمة',
            'unit'   => 'الوحدة',
            'period' => 'الفترة',
            'status' => 'الحالة',
            'drill'  => 'التعمّق',
        );
        $missing = array();
        foreach ($need as $f => $label) {
            if (!array_key_exists($f, $k) || $blank($k[$f])) { $missing[] = $label; }
        }

        $tones = ems_kpi_tones();
        if (!$missing && !isset($tones[(string) $k['status']])) {
            $missing[] = 'الحالة (نغمةٌ غيرُ معلنة: ' . $k['status'] . ')';
        }

        if ($missing) {
            /* لا يُصيَّر رقمٌ ناقصُ العقد: رقمٌ بلا مقامِه ومصدرِه يُقرَّر عليه
               خطأً — والبطاقةُ تقول ما ينقصها بدل أن تصمت. */
            return '<div class="ems-kpi-card ems-kpi-err" role="alert">'
                 . '<div class="ems-kpi-title">بطاقةُ مؤشرٍ ناقصةُ العقد</div>'
                 . '<div class="ems-kpi-value"><small>الحقولُ الغائبة: '
                 . $E(implode('، ', $missing)) . '</small></div>'
                 . '<div class="ems-kpi-meta"><span>SH-09</span><span>املأِ السبعةَ كاملةً</span></div>'
                 . '</div>';
        }

        $tone       = $tones[(string) $k['status']];
        $comparison = (!array_key_exists('comparison', $k) || $blank($k['comparison']))
                    ? 'بلا مقارنة معلنة'
                    : (string) $k['comparison'];

        $cls = 'ems-kpi-card ' . $tone[0] . (empty($k['class']) ? '' : ' ' . $k['class']);
        $ico = empty($k['icon']) ? '' : '<i class="fas ' . $E($k['icon']) . '" aria-hidden="true"></i> ';

        /* الحالةُ نصًّا لا لونًا فقط: اللونُ وحدَه إشارةٌ لا يراها كلُّ قارئ. */
        $html  = '<a class="' . $E(trim($cls)) . '" href="' . $E($k['drill'])
               . '" title="تعمّق: ' . $E($k['title']) . '">';
        $html .= '<div class="ems-kpi-title">' . $ico . $E($k['title']) . '</div>';
        $html .= '<div class="ems-kpi-value">' . $E($k['value'])
               . ' <small>' . $E($k['unit']) . '</small></div>';
        $html .= '<div class="ems-kpi-meta"><span>' . $E($k['period']) . '</span>'
               . '<span>' . $E($comparison) . '</span></div>';
        $html .= '<div class="ems-kpi-meta"><span class="ems-kpi-state">' . $E($tone[1]) . '</span>'
               . (empty($k['scope']) ? '<span></span>' : '<span>' . $E($k['scope']) . '</span>')
               . '</div>';
        $html .= '</a>';
        return $html;
    }
}
