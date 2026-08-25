<?php
/**
 * includes/status_display.php — قاموسُ الحالاتِ الموحَّدُ ودالةُ العرضِ الواحدة
 * ═══════════════════════════════════════════════════════════════════════════
 * INJAZ-UXUI-01 ف٦-٢: «اثنتا عشرةَ حالةً للنظامِ كلِّه — تُبنى دالةٌ واحدةٌ
 * للعرضِ تقرأ القيمةَ الداخليةَ وتُرجع الاسمَ العربيَّ والشارةَ واللون،
 * وتُستدعى في كلِّ شاشة. ويُمنع كتابةُ نصِّ حالةٍ في صفحةٍ مباشرةً.
 * وقيمُ القاعدةِ لا تُمسُّ إطلاقًا: الترجمةُ في طبقةِ العرضِ وحدَها.»
 *
 * ◆ المرادفاتُ الحيّة: القاعدةُ تحمل قيمًا تاريخيةً أوسعَ من الاثنتَي عشرةَ
 *   (pending/submitted/active/paid…) — تُسنَد لأقربِ حالةٍ معياريةٍ عرضًا،
 *   والقيمةُ المخزونةُ كما هي. مرادفٌ غيرُ معروفٍ يُعرض محايدًا بنصِّه
 *   ويُرصَد ببوابةِ الحالاتِ لا يُخترع له معنًى.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_status_dictionary')) {
    /** القاموسُ الحاكم: القيمةُ الداخلية ⇐ [الاسمُ الظاهر، النغمةُ الدلالية، متى تظهر] */
    function ems_status_dictionary()
    {
        return array(
            'draft'             => array('label' => 'مسودة',                     'tone' => 'neutral',      'when' => 'أنشئ ولم يرسل بعد'),
            'under_review'      => array('label' => 'قيد المراجعة',              'tone' => 'info',         'when' => 'عند مراجع فني أو محاسبي'),
            'pending_approval'  => array('label' => 'بانتظار الاعتماد',          'tone' => 'warning',      'when' => 'في خطوة سلم اعتماد لم تحسم'),
            'payment_requested' => array('label' => 'بانتظار الدفع',             'tone' => 'warning',      'when' => 'اعتمد ماليا وينتظر الصرف'),
            'approved'          => array('label' => 'معتمَد',                     'tone' => 'success',      'when' => 'اكتمل سلمه'),
            'executed'          => array('label' => 'منفذ',                     'tone' => 'success',      'when' => 'نفذ أثره فعلا'),
            'rejected'          => array('label' => 'مرفوض',                      'tone' => 'danger',       'when' => 'رفض بسبب مسجل'),
            'suspended'         => array('label' => 'معلق',                    'tone' => 'suspend',      'when' => 'أوقف مؤقتا بسبب مسجل'),
            'cancelled'         => array('label' => 'ملغى',                      'tone' => 'neutral-dark', 'when' => 'ألغي ولا أثر له'),
            'closed'            => array('label' => 'مغلق',                       'tone' => 'neutral-dark', 'when' => 'انتهت دورته بلا فتح ثان'),
            'cap_unresolved'    => array('label' => 'موقوف لسقف غير معتمد',   'tone' => 'warning',      'when' => 'سقف لم يعتمده المالك — يظهر بنصه لا كعطل'),
            'training'          => array('label' => 'بيانات تدريب',              'tone' => 'training',     'when' => 'سجل من وضع التدريب — لا يدخل تقريرا حقيقيا'),
        );
    }
}

if (!function_exists('ems_status_synonyms')) {
    /** المرادفاتُ الحيّةُ في القاعدة ⇐ حالتُها المعياريةُ عرضًا (القيمةُ لا تُمسّ) */
    function ems_status_synonyms()
    {
        return array(
            'pending'        => 'pending_approval',
            'submitted'      => 'under_review',
            'in_review'      => 'under_review',
            'reviewing'      => 'under_review',
            'waiting'        => 'pending_approval',
            'accepted'       => 'approved',
            'active'         => 'executed',
            'done'           => 'executed',
            'completed'      => 'executed',
            'paid'           => 'executed',
            'payment_pending'=> 'payment_requested',
            'declined'       => 'rejected',
            'refused'        => 'rejected',
            'on_hold'        => 'suspended',
            'paused'         => 'suspended',
            'canceled'       => 'cancelled',
            'void'           => 'cancelled',
            'archived'       => 'closed',
            'finished'       => 'closed',
        );
    }
}

if (!function_exists('ems_status_display')) {
    /**
     * الدالةُ الواحدة: قيمةٌ داخلية ⇐ ['value','canonical','label','tone','known']
     * لا تكتب في القاعدةِ شيئًا — عرضٌ خالص.
     */
    function ems_status_display($value)
    {
        $raw = trim((string) $value);
        $key = strtolower($raw);
        $dict = ems_status_dictionary();
        $canonical = $key;
        if (!isset($dict[$canonical])) {
            $syn = ems_status_synonyms();
            $canonical = isset($syn[$key]) ? $syn[$key] : null;
        }
        if ($canonical !== null && isset($dict[$canonical])) {
            $d = $dict[$canonical];
            return array('value' => $raw, 'canonical' => $canonical, 'label' => $d['label'], 'tone' => $d['tone'], 'known' => true);
        }
        /* عربيٌّ مخزونٌ أصلًا (تعداداتُ ENUM العربية) يُعرض بنصِّه محايدًا */
        return array('value' => $raw, 'canonical' => null, 'label' => ($raw === '' ? '—' : $raw), 'tone' => 'neutral', 'known' => false);
    }
}

if (!function_exists('ems_status_badge')) {
    /** شارةُ الحالةِ الجاهزة — «اللونُ لا يُعتمد وحدَه: نصٌّ دائمًا» */
    function ems_status_badge($value, $extraClass = '')
    {
        $d = ems_status_display($value);
        $cls = 'ux-status ux-status--' . $d['tone'] . ($extraClass !== '' ? ' ' . $extraClass : '');
        return '<span class="' . $cls . '">' . htmlspecialchars($d['label'], ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

if (!function_exists('ems_status_internal_values')) {
    /** لبوابةِ المنع: القيمُ الداخليةُ التي يُحظَر ظهورُها نصًّا في أيِّ شاشة */
    function ems_status_internal_values()
    {
        return array_merge(array_keys(ems_status_dictionary()), array_keys(ems_status_synonyms()));
    }
}
