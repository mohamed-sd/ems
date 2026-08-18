<?php
/**
 * includes/uxui_components.php — مكتبةُ مكوّناتِ INJAZ-UXUI-01 المركزية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ ف١٣-١ خطوة ①: «طبقةُ واجهةٍ مركزيةٌ مرجعًا حيًّا من الكود — وصفرُ مكوّنٍ
 *   محليٍّ جديدٍ يُقبل بعدَها». هذا الملفُّ هو المرجعُ الحيُّ: سجلُّ المكوّناتِ
 *   الثمانيةِ والثلاثينَ الإلزاميةِ (ورقة «مصفوفة المكونات») مسنَدًا كلٌّ منها
 *   إلى تنفيذِه — قائمًا كان (نُظُمُ EMS الموحَّدةُ السابقة) أو جديدًا هنا.
 * ◆ المكوّناتُ الجديدةُ دوالُّ عرضٍ خالصة: لا تقرأ قاعدةً ولا تكتبها — منطقُ
 *   الحالةِ والصلاحيةِ يمرَّر إليها من الشاشةِ ومصدرِه آلاتُ الحالةِ القائمة.
 * ◆ CSS: assets/css/uxui-tokens.css + uxui-components.css (بادئة ux-).
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/status_display.php';

if (!function_exists('ux_e')) {
    function ux_e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}

/* ══ ترويسةُ الصفحةِ المضغوطة (ف٨-٣) — سطرٌ واحدٌ وسقفُها 96px ═══════════
   $opts: status (قيمة حالة) · next (سطرُ الخطوةِ التالية) · primary =
   ['label','href'|'attrs'] الزرُّ الرئيسيُّ الواحد · secondary = مصفوفةُ
   أزرارٍ ثانوية · more = بنودُ قائمةِ «المزيد ⋯» [['label','href'],…] */
if (!function_exists('ux_page_header')) {
    function ux_page_header($title, $opts = array())
    {
        $h  = '<header class="ux-page-header">';
        $h .= '<h1 class="ux-page-header__title">' . ux_e($title) . '</h1>';
        if (!empty($opts['status'])) {
            $h .= '<span class="ux-page-header__status">' . ems_status_badge($opts['status']) . '</span>';
        }
        if (!empty($opts['next'])) {
            $h .= '<span class="ux-page-header__next" title="' . ux_e($opts['next']) . '">' . ux_e($opts['next']) . '</span>';
        }
        $h .= '<span class="ux-page-header__spacer"></span>';
        $h .= '<div class="ux-page-header__actions">';
        if (!empty($opts['secondary']) && is_array($opts['secondary'])) {
            foreach ($opts['secondary'] as $b) {
                $h .= '<a class="ux-btn ux-btn--secondary" href="' . ux_e(isset($b['href']) ? $b['href'] : '#') . '">' . ux_e($b['label']) . '</a>';
            }
        }
        if (!empty($opts['more']) && is_array($opts['more'])) {
            $h .= '<details class="ux-more"><summary class="ux-btn ux-btn--ghost" style="list-style:none">المزيد ⋯</summary><div class="ux-transition__menu" data-open="1">';
            foreach ($opts['more'] as $m) {
                $h .= '<a class="ux-transition__item" href="' . ux_e(isset($m['href']) ? $m['href'] : '#') . '">' . ux_e($m['label']) . '</a>';
            }
            $h .= '</div></details>';
        }
        if (!empty($opts['primary'])) {
            $p = $opts['primary'];
            $h .= '<a class="ux-btn ux-btn--primary" href="' . ux_e(isset($p['href']) ? $p['href'] : '#') . '"'
                . (isset($p['attrs']) ? ' ' . $p['attrs'] : '') . '>' . ux_e($p['label']) . '</a>';
        }
        $h .= '</div></header>';
        return $h;
    }
}

/* ══ الشريطُ الموحَّد (ف٩-٢) — ستُّ أدواتٍ بترتيبٍ حصريّ ══════════════════
   بحثٌ · مرشِّحاتٌ · منظرٌ · أعمدةٌ · تصديرٌ · المزيد. الشاشةُ تمرِّر ما
   تُفعِّله؛ والغائبُ لا يُرسَم — ولا يُرسَم شريطٌ ثانٍ في الصفحة. */
if (!function_exists('ux_toolbar')) {
    function ux_toolbar($tools = array())
    {
        $h = '<div class="ux-toolbar" role="toolbar">';
        if (array_key_exists('search', $tools)) {
            $ph = is_string($tools['search']) && $tools['search'] !== '' ? $tools['search'] : 'بحثٌ في السجلِّ الحالي';
            $h .= '<div class="ux-toolbar__search"><input type="search" placeholder="' . ux_e($ph) . '" aria-label="بحث"></div>';
        }
        foreach (array('filters' => 'مرشِّحات', 'views' => 'منظر', 'columns' => 'أعمدة', 'export' => 'تصدير') as $k => $lbl) {
            if (array_key_exists($k, $tools)) {
                $h .= '<button type="button" class="ux-btn ux-btn--secondary" data-ux-tool="' . $k . '">' . $lbl . '</button>';
            }
        }
        if (!empty($tools['more']) && is_array($tools['more'])) {
            $h .= '<details class="ux-more"><summary class="ux-btn ux-btn--ghost" style="list-style:none">المزيد ⋯</summary><div class="ux-transition__menu" data-open="1">';
            foreach ($tools['more'] as $m) {
                $h .= '<a class="ux-transition__item" href="' . ux_e(isset($m['href']) ? $m['href'] : '#') . '">' . ux_e($m['label']) . '</a>';
            }
            $h .= '</div></details>';
        }
        $h .= '</div>';
        return $h;
    }
}

/* ══ رقاقاتُ المرشِّحاتِ النشطة + «مسحُ الكل» ══ */
if (!function_exists('ux_filter_chips')) {
    function ux_filter_chips($chips, $clearAllHref = '')
    {
        if (empty($chips)) { return ''; }
        $h = '<div class="ux-chiprow">';
        foreach ($chips as $c) {
            $h .= '<span class="ux-chip">' . ux_e($c['label'])
                . (isset($c['remove']) ? '<a class="ux-chip__x" href="' . ux_e($c['remove']) . '" aria-label="إزالةُ المرشِّح">×</a>' : '')
                . '</span>';
        }
        if ($clearAllHref !== '') {
            $h .= '<a class="ux-btn ux-btn--ghost" href="' . ux_e($clearAllHref) . '">مسحُ الكل</a>';
        }
        $h .= '</div>';
        return $h;
    }
}

/* ══ بطاقةُ المؤشر (ف٨-٢) — القيمةُ بسياقِها لا وحدَها ══════════════════
   «بلا مقارنةٍ معلنة» لا تُعرض: إن غاب أساسُ المقارنةِ حُذف سطرُها. */
if (!function_exists('ux_kpi_card')) {
    function ux_kpi_card($label, $value, $opts = array())
    {
        $cls = 'ux-kpi' . (isset($opts['tone']) && $opts['tone'] === 'alert' ? ' ux-kpi--alert' : '') . (isset($opts['tone']) && $opts['tone'] === 'danger' ? ' ux-kpi--danger' : '');
        $h  = '<div class="' . $cls . '">';
        $h .= '<span class="ux-kpi__label">' . ux_e($label) . '</span>';
        $h .= '<span class="ux-kpi__value">' . ux_e($value) . (isset($opts['unit']) ? '<span class="ux-kpi__unit">' . ux_e($opts['unit']) . '</span>' : '') . '</span>';
        $meta = array();
        if (!empty($opts['period']))  { $meta[] = ux_e($opts['period']); }
        if (!empty($opts['compare'])) { $meta[] = ux_e($opts['compare']); }
        if (!empty($opts['stale']))   { $meta[] = '<span class="ux-stale">آخرُ تحديث: ' . ux_e($opts['stale']) . '</span>'; }
        if ($meta) { $h .= '<span class="ux-kpi__meta">' . implode(' · ', $meta) . '</span>'; }
        if (!empty($opts['href'])) { $h .= '<a class="ux-btn ux-btn--ghost" href="' . ux_e($opts['href']) . '">تعمّق</a>'; }
        $h .= '</div>';
        return $h;
    }
}

/* ══ بطاقةُ القرار (ف٨-٢) — «١٠ قراراتٍ تنتظرك · ٣ تجاوزت المهلة → مراجعة» ══ */
if (!function_exists('ux_decision_card')) {
    function ux_decision_card($count, $what, $actionLabel, $actionHref, $overdueHint = '')
    {
        $h  = '<div class="ux-decision">';
        $h .= '<span class="ux-decision__count">' . ux_e($count) . '</span>';
        $h .= '<span class="ux-decision__body"><span class="ux-decision__what">' . ux_e($what) . '</span>';
        if ($overdueHint !== '') { $h .= '<span class="ux-decision__hint">' . ux_e($overdueHint) . '</span>'; }
        $h .= '</span>';
        $h .= '<a class="ux-btn ux-btn--primary" href="' . ux_e($actionHref) . '">' . ux_e($actionLabel) . '</a>';
        $h .= '</div>';
        return $h;
    }
}

/* ══ مكوّنُ الانتقال (ف١٠) — يقرأ المسموحَ الممرَّرَ من آلةِ الحالة ═══════
   $transitions: [['label','href'|'attrs','allowed'=>bool,'why'=>سببُ المنع],…]
   الأولُ المسموحُ هو الرئيسيُّ والبقيةُ في «⋯» — والممنوعُ معطَّلٌ بسببِه
   لا مخفيًّا (فالإخفاءُ يوهم النقصَ والتعطيلُ يعلّم القاعدة). */
if (!function_exists('ux_transition')) {
    function ux_transition($currentStatus, $transitions = array(), $reasonHint = '')
    {
        $h = '<div class="ux-transition">' . ems_status_badge($currentStatus);
        if ($reasonHint !== '') { $h .= '<span class="ux-stale" title="' . ux_e($reasonHint) . '">ⓘ</span>'; }
        $primary = null; $rest = array();
        foreach ($transitions as $t) {
            if ($primary === null && !empty($t['allowed'])) { $primary = $t; } else { $rest[] = $t; }
        }
        if ($primary !== null) {
            $h .= '<a class="ux-btn ux-btn--primary" href="' . ux_e(isset($primary['href']) ? $primary['href'] : '#') . '"'
                . (isset($primary['attrs']) ? ' ' . $primary['attrs'] : '') . '>' . ux_e($primary['label']) . '</a>';
        }
        if ($rest) {
            $h .= '<details class="ux-more"><summary class="ux-btn ux-btn--ghost" style="list-style:none">⋯</summary><div class="ux-transition__menu" data-open="1">';
            foreach ($rest as $t) {
                if (!empty($t['allowed'])) {
                    $h .= '<a class="ux-transition__item" href="' . ux_e(isset($t['href']) ? $t['href'] : '#') . '"'
                        . (isset($t['attrs']) ? ' ' . $t['attrs'] : '') . '>' . ux_e($t['label']) . '</a>';
                } else {
                    $h .= '<span class="ux-transition__item" aria-disabled="1">' . ux_e($t['label'])
                        . '<span class="ux-transition__why">' . ux_e(isset($t['why']) ? $t['why'] : 'غيرُ متاحٍ في هذه الحالة') . '</span></span>';
                }
            }
            $h .= '</div></details>';
        }
        $h .= '</div>';
        return $h;
    }
}

/* ══ حالاتُ الشاشة: فراغٌ · خطأٌ · لا صلاحيةٌ · تحميل ══ */
if (!function_exists('ux_empty_state')) {
    function ux_empty_state($title, $hint = '', $action = null)
    {
        $h = '<div class="ux-empty"><span class="ux-empty__title">' . ux_e($title) . '</span>';
        if ($hint !== '') { $h .= '<span class="ux-empty__hint">' . ux_e($hint) . '</span>'; }
        if ($action) { $h .= '<a class="ux-btn ux-btn--primary" href="' . ux_e($action['href']) . '">' . ux_e($action['label']) . '</a>'; }
        return $h . '</div>';
    }
}
if (!function_exists('ux_error_state')) {
    function ux_error_state($title, $hint = '', $retryHref = '')
    {
        $h = '<div class="ux-error" role="alert"><span class="ux-error__title">' . ux_e($title) . '</span>';
        if ($hint !== '') { $h .= '<span class="ux-error__hint">' . ux_e($hint) . '</span>'; }
        if ($retryHref !== '') { $h .= '<a class="ux-btn ux-btn--secondary" href="' . ux_e($retryHref) . '">إعادةُ المحاولة</a>'; }
        return $h . '</div>';
    }
}
if (!function_exists('ux_noperm_state')) {
    function ux_noperm_state($reason, $requestHint = '')
    {
        $h = '<div class="ux-noperm"><span class="ux-noperm__title">لا صلاحيةَ لهذا المحتوى</span>'
           . '<span class="ux-noperm__hint">' . ux_e($reason) . '</span>';
        if ($requestHint !== '') { $h .= '<span class="ux-noperm__hint">' . ux_e($requestHint) . '</span>'; }
        return $h . '</div>';
    }
}
if (!function_exists('ux_loading_state')) {
    function ux_loading_state($rows = 5)
    {
        $h = '<div class="ux-loading" aria-busy="true">';
        for ($i = 0; $i < max(1, (int) $rows); $i++) { $h .= '<div class="ux-loading__row"></div>'; }
        return $h . '</div>';
    }
}

/* ══ شاراتُ السياق ══ */
if (!function_exists('ux_training_banner')) {
    function ux_training_banner()
    {
        return '<div class="ux-training-banner">وضعُ التدريب — البياناتُ هنا تدريبيةٌ ولا تدخل تقريرًا حقيقيًّا</div>';
    }
}
if (!function_exists('ux_cap_hold_card')) {
    function ux_cap_hold_card($capName, $ref = '')
    {
        return '<div class="ux-cap-hold"><span>' . ems_status_badge('cap_unresolved') . '</span>'
             . '<span>هذه المعاملةُ محفوظةٌ وتستأنف تلقائيًّا فورَ اعتمادِ السقف: <b>' . ux_e($capName) . '</b>'
             . ($ref !== '' ? ' — المرجع: ' . ux_e($ref) : '') . '</span></div>';
    }
}
if (!function_exists('ux_stale_badge')) {
    function ux_stale_badge($lastUpdated)
    {
        return '<span class="ux-stale">آخرُ تحديث: ' . ux_e($lastUpdated) . '</span>';
    }
}

/* ══ سجلُّ المكوّناتِ الثمانيةِ والثلاثين — المرجعُ الحيُّ من الكود ═══════
   لكلِّ مكوّنٍ من ورقةِ «مصفوفة المكونات»: أين تنفيذُه اليوم. gate ف١٣-١ ①:
   «صفرُ مكوّنٍ محليٍّ جديدٍ يُقبل بعدَها» — فما ليس في هذا السجلِّ لا يُبنى
   في صفحة. (existing = نظامٌ موحَّدٌ قائمٌ قبلَ الجولة · new = هذه المكتبة) */
if (!function_exists('ux_component_registry')) {
    function ux_component_registry()
    {
        return array(
            'ترويسة الصفحة'        => array('impl' => 'new',      'ref' => 'ux_page_header()'),
            'مسار التنقل'           => array('impl' => 'new',      'ref' => 'ux_page_header() — العمقُ الثالثُ فصاعدًا'),
            'التبويبات'             => array('impl' => 'existing', 'ref' => 'includes/entity_tabs.php (نظامُ تبويباتٍ واحدٌ لبطاقةِ الكيان)'),
            'الخطوات'               => array('impl' => 'existing', 'ref' => 'includes/journey_bar.php (شريطُ الرحلةِ الموحَّد)'),
            'الدرج'                 => array('impl' => 'existing', 'ref' => 'EmsDetailsModal (assets/js) — درجُ التفاصيل'),
            'النافذة'               => array('impl' => 'existing', 'ref' => 'EmsDetailsModal / bootstrap modal الموحَّد'),
            'حوار التأكيد'          => array('impl' => 'existing', 'ref' => 'EmsAlert.confirm (نظامُ الرسائلِ الموحَّد)'),
            'رفع المرفق'            => array('impl' => 'existing', 'ref' => 'نظامُ المرفقاتِ الموحَّدُ في النماذج (ems-forms)'),
            'قائمة المرفقات'        => array('impl' => 'existing', 'ref' => 'مكوّنُ المرفقاتِ في بطاقاتِ الكيان'),
            'INJAZ DataGrid'        => array('impl' => 'existing', 'ref' => 'DataTables المركزي + ems-tables.css + كثافةُ ux-grid-density'),
            'الشريط الموحد'         => array('impl' => 'new',      'ref' => 'ux_toolbar()'),
            'البحث'                 => array('impl' => 'new',      'ref' => 'ux_toolbar()[search]'),
            'البحث العام'           => array('impl' => 'existing', 'ref' => 'main/global_search.php (التسعُ كيانات)'),
            'المرشحات'              => array('impl' => 'new',      'ref' => 'ux_toolbar()[filters] + ux_filter_chips()'),
            'المناظر المحفوظة'      => array('impl' => 'existing', 'ref' => 'includes/nav_views.php (المناظرُ المعلنةُ ?view=)'),
            'منتقي الأعمدة'         => array('impl' => 'existing', 'ref' => 'DataTables colvis المركزي — يُلبَس ux-btn عندَ الترحيل'),
            'التصفيح'               => array('impl' => 'existing', 'ref' => 'DataTables paging المركزي'),
            'بطاقة مؤشر'            => array('impl' => 'new',      'ref' => 'ux_kpi_card()'),
            'بطاقة قرار'            => array('impl' => 'new',      'ref' => 'ux_decision_card()'),
            'بطاقة مهمة'            => array('impl' => 'existing', 'ref' => 'Portal/my_tasks.php بطاقةُ المهمة — تُوحَّد قشرتُها ux عندَ ترحيلِ الذهبية ②'),
            'بطاقة اعتماد'          => array('impl' => 'existing', 'ref' => 'Finance/approvals_inbox.php — تُوحَّد قشرتُها ux عندَ ترحيلِ الذهبية ⑦'),
            'بطاقة تنبيه'           => array('impl' => 'existing', 'ref' => 'EmsAlert.banner (اللافتةُ السطرية)'),
            'وسم الحالة'            => array('impl' => 'new',      'ref' => 'ems_status_badge() — القاموسُ الاثنا عشر'),
            'مكون الانتقال'         => array('impl' => 'new',      'ref' => 'ux_transition() — يقرأ المسموحَ من آلةِ الحالةِ الممرَّرة'),
            'الخط الزمني'           => array('impl' => 'existing', 'ref' => 'includes/journey_bar.php + سجلُّ أحداثِ الكيان'),
            'سجل النشاط'            => array('impl' => 'existing', 'ref' => 'ActivityLogs/activity_logs.php — لغتُه تُهذَّب في موجتِه'),
            'الرسائل العابرة'       => array('impl' => 'existing', 'ref' => 'EmsAlert.toast'),
            'الرسوم'                => array('impl' => 'existing', 'ref' => 'مكوّنُ الرسومِ في اللوحات (Chart.js المركزي)'),
            'حالة الفراغ'           => array('impl' => 'new',      'ref' => 'ux_empty_state() — وems-states.css القائمةُ للمرحَّلِ سابقًا'),
            'حالة التحميل'          => array('impl' => 'new',      'ref' => 'ux_loading_state()'),
            'حالة الخطأ'            => array('impl' => 'new',      'ref' => 'ux_error_state()'),
            'حالة لا صلاحية'        => array('impl' => 'new',      'ref' => 'ux_noperm_state() — وincludes/deny_page.php للصفحةِ الكاملة'),
            'قراءة فقط'             => array('impl' => 'existing', 'ref' => 'نمطُ القراءةِ في النماذجِ المعتمدة (ems-forms) + شارةُ ems_status_badge'),
            'غير متصل'              => array('impl' => 'existing', 'ref' => 'صندوقُ الميدان (Timesheet offline) — شريطُه يُلبَس ux-offline عندَ موجتِه'),
            'مؤشر صندوق الخروج'     => array('impl' => 'existing', 'ref' => 'عدّاداتُ الرفعِ المحليِّ في شاشاتِ الميدان'),
            'بيانات متقادمة'        => array('impl' => 'new',      'ref' => 'ux_stale_badge()'),
            'شارة وضع التدريب'      => array('impl' => 'new',      'ref' => 'ux_training_banner() + حالةُ training في القاموس'),
            'بطاقة السقف الموقوف'   => array('impl' => 'new',      'ref' => 'ux_cap_hold_card() + حالةُ cap_unresolved'),
        );
    }
}
