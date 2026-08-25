<?php
/**
 * includes/file_tabs_kit.php — شريطُ «تبويباتِ الملف» · مكوّنٌ واحدٌ ومَوضعٌ واحد
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ◆ **العطبُ الذي يعالجه** — قرارُ المالك 2026-08-19:
 *   ثلاثةُ شرائطَ (`supplier_file_tabs` · `contract_file_tabs` ·
 *   `financing_file_tabs`) كانت تُطبع **قبل** `<div class="main">` في أربعٍ
 *   وعشرين شاشة، فتخرج من غلافِ الشاشةِ وتظهر **بجانبِها** لا داخلَها:
 *   شريطُ ملاحةٍ يطفو فوقَ القشرةِ بلا انتماء.
 *   وموضعُه الصحيحُ **تحتَ الشريطِ الأصفرِ مباشرةً** (`.main_head` الذي يحمل
 *   زرَّ «عن الشاشة») و**فوقَ شريطِ رحلةِ الكِيان** (`ems-entity-tabs`):
 *   فالترتيبُ يقرأ من العامِّ إلى الخاص — رأسُ الشاشةِ ← ملفُّ الكِيانِ الذي
 *   نحن فيه ← رحلتُه ← المحتوى.
 *
 * ◆ **ولِمَ طابورٌ لا طباعةٌ مباشرة**
 *   الشريطُ يُضمَّن في الشاشةِ **قبل** أن تفتح `.main`، فطباعتُه لحظةَ التضمينِ
 *   تضعه حيث لا يجب. والبديلُ تعديلُ موضعِ التضمينِ في أربعٍ وعشرين ملفًّا —
 *   أربعةٌ وعشرون فرصةً للخطأ، وكلُّ شاشةٍ جديدةٍ تكرّره.
 *   فالمكوّنُ **يصفُّ** ترميزَه هنا، و`page_header.php` يصرفه في موضعِه
 *   الواحد. الشاشةُ تقول «لي ملفٌّ تبويباتُه كذا» ولا تقول «اطبعْ هنا».
 *
 * ◆ **والصرفُ مرةً واحدة**: الطابورُ يُفرَّغ عند أولِ رأسٍ يُطبع، فشاشةٌ
 *   تُحمِّل الرأسَ مرتين (فرعُ «غيرُ موجودة» وفرعُ الوجود) لا تطبع شريطَين.
 *
 * ◆ **وما لا يُصرَف يُطبع آخرًا**: لو استُعمل المكوّنُ في شاشةٍ بلا رأسٍ
 *   موحَّدٍ لبقي الطابورُ معلَّقًا والشريطُ يختفي صامتًا — وهو أسوأُ من ظهورِه
 *   في غيرِ مكانِه. فـ`ems_file_tabs_flush()` تُنادى في نهايةِ القشرةِ أيضًا،
 *   فإن بقي شيءٌ طُبع مع إعلانِ سببِه في تعليقٍ في المصدر.
 *   (مقيسٌ الآن: الستُّ والعشرون كلُّها تُحمِّل `page_header.php`.)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_file_tabs')) {

    /**
     * يبني شريطَ تبويباتِ الملفِّ ويصفُّه للطباعةِ تحتَ الرأس.
     *
     * @param array $o
     *   label    اسمُ الملفِّ الظاهر (مثل «ملفُّ المورد») — يسبق التبويبات
     *   tabs     array of array('text','href','active'=>bool)
     *   subs     روابطُ القسمِ النشطِ (اختياريّ) — array('text','href','active')
     *   subs_label  عنوانُ سطرِ الأقسامِ (افتراضًا 'أقسامُ التبويب')
     */
    function ems_file_tabs(array $o)
    {
        $tabs = isset($o['tabs']) && is_array($o['tabs']) ? $o['tabs'] : array();
        if (!$tabs) { return; }

        $e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

        $h = '<nav class="ems-file-tabs" dir="rtl" aria-label="'
           . $e(isset($o['label']) ? $o['label'] : 'تبويبات الملف') . '">';

        if (!empty($o['label'])) {
            $h .= '<span class="ems-file-tabs-label">' . $e($o['label']) . ':</span>';
        }

        $h .= '<div class="ems-file-tabs-row">';
        foreach ($tabs as $t) {
            if (!is_array($t) || empty($t['text'])) { continue; }
            $cls = 'ems-file-tab' . (empty($t['active']) ? '' : ' is-active');
            if (empty($t['href'])) {
                $h .= '<span class="' . $cls . ' is-unbuilt" title="غير مبنية بعد">'
                    . $e($t['text']) . '</span>';
            } elseif (!empty($t['active'])) {
                $h .= '<span class="' . $cls . '" aria-current="page">' . $e($t['text']) . '</span>';
            } else {
                $h .= '<a class="' . $cls . '" href="' . $e($t['href']) . '">' . $e($t['text']) . '</a>';
            }
        }
        $h .= '</div>';

        /* سطرُ أقسامِ التبويبِ النشط — لا يُطبع إلا إذا كان فيه أكثرُ من واحد،
           فقسمٌ وحيدٌ تحت تبويبِه تكرارٌ لاسمِ التبويبِ لا تنقّلٌ فيه. */
        if (!empty($o['subs']) && is_array($o['subs']) && count($o['subs']) > 1) {
            $h .= '<div class="ems-file-tabs-subs">';
            $h .= '<span class="ems-file-tabs-subs-label">'
                . $e(isset($o['subs_label']) ? $o['subs_label'] : 'أقسام التبويب') . ':</span>';
            foreach ($o['subs'] as $s) {
                if (!is_array($s) || empty($s['text'])) { continue; }
                $scls = 'ems-file-sub' . (empty($s['active']) ? '' : ' is-active');
                if (empty($s['href'])) {
                    $h .= '<span class="' . $scls . '">' . $e($s['text']) . '</span>';
                } else {
                    $h .= '<a class="' . $scls . '" href="' . $e($s['href']) . '">' . $e($s['text']) . '</a>';
                }
            }
            $h .= '</div>';
        }

        $h .= '</nav>';

        /* ◆ **ولا يختفي شريطٌ صامتًا**: لو نُودِي المكوّنُ **بعد** أن طُبع
             الرأسُ (شاشةٌ تُضمِّن شريطَها متأخرًا — وقع في شاشتَي التمويل)
             لبقي في الطابورِ إلى الأبدِ ولم يره أحد. فالحكمُ بلحظةِ النداء
             كما في `ems_entity_tabs`: قبلَ الرأسِ يُصَفّ، وبعدَه يُطبع
             مكانَه — في غيرِ الموضعِ المثاليِّ لكنه **ظاهرٌ لا مفقود**. */
        if (!empty($GLOBALS['__ems_head_printed'])) { echo $h; return; }

        $GLOBALS['__ems_file_tabs'] = $h;
    }

    /** يصرف الطابورَ مرةً واحدة — يُنادى من `page_header.php` تحتَ الشريطِ الأصفر. */
    function ems_file_tabs_flush()
    {
        if (empty($GLOBALS['__ems_file_tabs'])) { return ''; }
        $h = $GLOBALS['__ems_file_tabs'];
        $GLOBALS['__ems_file_tabs'] = '';
        return $h;
    }
}
