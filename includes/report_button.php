<?php
/**
 * includes/report_button.php — زر «أبلغ عن مشكلة» السياقي (TKT-01 §2 · TKT-15)
 * ───────────────────────────────────────────────────────────────────────────
 * «لا تُسلَّم شاشة تشغيلية بلا زر إبلاغ يحمل سياقها — ولا يُدخل المستخدم
 * حرفًا مما يعرفه النظام».
 *
 * الاستعمال داخل أي شاشة:
 *     require_once __DIR__ . '/../includes/report_button.php';
 *     ems_report_button(array('screen' => 'timesheet', 'site_id' => $siteId,
 *         'equipment_id' => $eqId, 'contract_id' => $cid, 'shift_no' => $shift,
 *         'entity_type' => 'unit_entry', 'entity_id' => $rowId));
 */

if (!function_exists('ems_report_button')) {
    /**
     * نصُّ الزرِّ **سلسلةً** لا طباعةً — لأنَّ الاحتياطيَّ يُبنى داخلَ مُعالِجِ
     * حجزِ المُخرَج، وPHP **تمنع** فتحَ حجزٍ جديدٍ داخلَ المُعالِج (فأوّلُ صياغةٍ
     * بنَتِ الزرَّ بـ`ob_start()` هناك فخرجت الصفحةُ **صفرَ بايت** كاملةً).
     */
    function ems_report_button_html(array $ctx, $label = 'أبلغ عن مشكلة')
    {
        $fields = '';
        foreach (array('screen', 'site_id', 'equipment_id', 'contract_id', 'project_id',
                       'shift_no', 'period_no', 'entity_type', 'entity_id') as $k) {
            if (isset($ctx[$k]) && $ctx[$k] !== '' && $ctx[$k] !== null) {
                $fields .= '<input type="hidden" name="ctx_' . $k . '" value="'
                    . htmlspecialchars((string) $ctx[$k]) . '">';
            }
        }
        // النموذج POST عادي إلى نقطة الفتح السياقي — السياق محمول لا مُدخَل
        return '<form method="post" action="' . htmlspecialchars(ems_report_button_base()) . '/Tickets/ticket_contextual_open.php"'
            . ' style="display:inline" class="ems-report-btn-secondary">'
            . $fields
            . '<button type="submit" class="action-btn" title="' . htmlspecialchars($label) . '"'
            . ' style="color:#c0392b"><i class="fas fa-bullhorn"></i> ' . htmlspecialchars($label) . '</button>'
            . '</form>';
    }

    function ems_report_button(array $ctx, $label = 'أبلغ عن مشكلة')
    {
        $GLOBALS['__ems_rb_rendered'] = true; // يُعلم الاحتياطيَّ العالميَّ فلا يزدوج
        echo ems_report_button_html($ctx, $label);
    }

    /** جذر التطبيق نسبيًّا من عمق الشاشة (شاشاتنا كلها على عمق مجلد واحد). */
    function ems_report_button_base()
    {
        return '..';
    }

    /**
     * الزرُّ الاحتياطيُّ العالمي (TKT-01 §2-⑧ · update0006-b E-04):
     * «أيُّ شاشةٍ في النظام — يُحمل الشاشةُ والمسارُ والمستخدمُ والوقت».
     * يطفو أسفلَ يسارِ كلِّ شاشةٍ مصادَقةٍ لم تستدعِ زرًّا أغنى بنفسها —
     * فتتحقق «صفرُ شاشةٍ تشغيليةٍ بلا زرِّ إبلاغ» بلا ازدواجٍ حيث الزرُّ الغني.
     */
    function ems_report_button_fallback()
    {
        if (!empty($GLOBALS['__ems_rb_rendered'])) { return ''; } // شاشةٌ لها زرُّها الغني
        $screen = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
        return '<div style="position:fixed;bottom:14px;inset-inline-end:14px;z-index:1050;direction:rtl"'
            . ' class="ems-report-fallback">'
            . ems_report_button_html(array('screen' => $screen), 'أبلغ عن مشكلة')
            . '</div>';
    }

    /**
     * ── INJ-0518 · الحقنُ **داخلَ الجسدِ** لا بعد `</html>` ────────────────────
     * كان الزرُّ يُبثُّ بـ`register_shutdown_function`، أي **بعد** انتهاءِ تنفيذِ
     * السكربت — فيقع نصُّه بعد وسمِ `</html>` الختامي في كلِّ شاشة. والمتصفحُ
     * يتسامح فيُظهره، لكنَّ المستندَ غيرُ سليمٍ ومدقّقاتُ الوصوليةِ والطباعةِ
     * والقارئاتُ الآليةُ تتعثّر به.
     *
     * والسببُ الذي فرض التأجيلَ حقيقيّ: «هل استدعت الشاشةُ زرًّا سياقيًّا أغنى؟»
     * لا يُعرف إلا بعد تصييرِها كلِّها. فالحلُّ ليس نقلَ النداءِ إلى أعلى الصفحة
     * — بل **حجزُ المُخرَجِ** وحقنُ الزرِّ قبل `</body>` عند الإفراغ: القرارُ
     * يبقى متأخرًا، والموضعُ يصير صحيحًا.
     *
     * وشرطُ الحقنِ وجودُ `</body>`: مُخرَجٌ بلا جسدٍ (JSON · تنزيلٌ · تحويل)
     * **لا يُحقن فيه شيء** — فالعيبُ الأصليُّ كان الإلحاقَ بلا شرط.
     */
    function ems_report_button_capture()
    {
        if (!empty($GLOBALS['__ems_rb_capturing'])) { return; }
        $GLOBALS['__ems_rb_capturing'] = true;
        ob_start(function ($html) {
            if (!empty($GLOBALS['__ems_rb_rendered'])) { return $html; }
            $pos = strripos($html, '</body>');
            if ($pos === false) { return $html; }   // بلا جسدٍ ⇒ بلا حقن
            $btn = ems_report_button_fallback();
            if ($btn === '') { return $html; }
            return substr($html, 0, $pos) . $btn . substr($html, $pos);
        });
    }
}
