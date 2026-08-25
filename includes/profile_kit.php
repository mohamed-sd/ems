<?php
/**
 * includes/profile_kit.php — عُدّةُ «بطاقةِ الكِيان» · التأليفُ بديلُ النسخ
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ◆ **المشكلةُ التي تحلُّها**
 *   بطاقةُ العميلِ وبطاقةُ الموظفِ تقولان الشيءَ نفسَه بمفرداتٍ مختلفة:
 *   `.profile-card` هناك و`.stat-card` هنا · `.cp-group` هناك و`.section-card`
 *   هنا · `.state-badge` و`.driver-badge` و`.opp-stage` و`.tnd-badge` أربعُ
 *   نسخٍ من شارةٍ واحدة. فحين تُطلب بطاقةُ المشروعِ يُنسخ أحدُ النسختين وتصير
 *   ثالثةً — وهذا بالضبطِ أصلُ «تعدّدِ ملفاتِ التصميمِ واتساخِ الكود».
 *
 * ◆ **والتوقّعُ صحَّ بالقياس**: حين مُسحت الشجرةُ وُجدت **ثمانيَ بطاقاتٍ**
 *   تعيد اختراعَ الشيءِ نفسِه — العميلُ · الموظفُ · المشروعُ · المورِّدُ ·
 *   المعدّةُ · المستخدمُ · الملفُّ الشخصيُّ · عمليةُ التمويل. وأوضحُها
 *   بطاقةُ المعدة: بنيةُ المكوّنِ حرفًا (`ep-hero`/`ep-chips`/`ep-facts`
 *   بمفتاحٍ وقيمة) بأسماءٍ أخرى. كلُّها الآن على هذا الملفِّ الواحد.
 *
 * ◆ **ما ليس بطاقةَ كِيان لا يُقحَم فيها**: `fleet_depreciation_profiles.php`
 *   و`auth_profiles.php` اسمُهما «profile» ومعناهما **سجلُّ قوالبَ** لا
 *   بطاقةُ كِيانٍ واحد — فلا لوحَ هويةٍ لهما ولم تُمَسّا.
 *
 * ◆ **العلاج**: الشاشةُ **تصف** بطاقتَها ولا **ترسمها**.
 *   والعُدّةُ تُخرج ترميزَ `ems-profile.css` وحدَه — فلا صنفَ يُكتب يدويًّا في
 *   صفحة، ولا كتلةَ `<style>` تُولَد من جديد.
 *
 * ◆ **بطاقةُ كِيانٍ جديدةٍ (مشروع · مورِّد · معدّة) تُبنى هكذا**:
 *
 *     require_once __DIR__ . '/../includes/profile_kit.php';
 *
 *     echo ems_profile_hero(array(
 *         'name'   => $project['name'],
 *         'icon'   => 'fas fa-diagram-project',        // أو 'photo' => المسار
 *         'status' => array('text' => 'نشط', 'tone' => 'ok'),
 *         'chips'  => array(
 *             array('text' => $project['code'], 'icon' => 'fas fa-hashtag', 'mono' => true),
 *             array('text' => $project['type'], 'icon' => 'fas fa-tag'),
 *         ),
 *         'facts'  => array(
 *             array('label' => 'العميل',  'value' => $project['client_name']),
 *             array('label' => 'الموقع',  'value' => $project['site']),
 *         ),
 *     ));
 *
 *     echo ems_profile_stats(array(
 *         array('value' => 12, 'label' => 'المعدات'),
 *         array('value' => 3,  'label' => 'تأخيرات', 'tone' => 'danger'),
 *     ));
 *
 *     echo ems_profile_group_open(array('title' => 'التنفيذ', 'icon' => 'fas fa-helmet-safety'));
 *     echo   ems_profile_section_open(array('title' => 'الوحدات'));
 *     // … جدولُ الوحدات …
 *     echo   ems_profile_section_close();
 *     echo ems_profile_group_close();
 *
 * ◆ **الغيابُ يُعلَن ولا يُلفَّق**: قيمةٌ فارغةٌ تُصيَّر «—» بصنفِ غيابٍ ظاهر،
 *   ومجموعةٌ بلا مصدرٍ لا تُفتح أصلًا (قرارُ الشاشةِ بشرطِ `if`).
 *
 * ◆ **النغماتُ ثمانٍ ولا تاسعةَ**: neutral · ok · warn · danger · info · gold
 *   · purple · cyan. ونغمةٌ غيرُ معلنةٍ تسقط إلى `neutral` بدل أن تكسر الصفحةَ
 *   أو تصمت بلا لون.
 */

if (!function_exists('ems_profile_tones')) {

    /** النغماتُ المعلنةُ — مرآةُ معجمِ `ems-profile.css` حرفًا بحرف. */
    function ems_profile_tones()
    {
        return array('neutral', 'ok', 'warn', 'danger', 'info', 'gold', 'purple', 'cyan');
    }

    /** نغمةٌ صالحةٌ أو `neutral` — لا تُكسر الشاشةُ لأجلِ وسمٍ مجهول. */
    function ems_profile_tone($tone)
    {
        $t = strtolower(trim((string) $tone));
        return in_array($t, ems_profile_tones(), true) ? $t : 'neutral';
    }

    /** هروبُ HTML — مختصرٌ داخليٌّ يُستعمل في كلِّ مُخرَجٍ بلا استثناء. */
    function ems_profile_e($v)
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    /**
     * يُسند قيمةَ أعمالٍ إلى نغمةٍ عبر خريطةٍ تُعلنها الشاشة.
     * مثال: ems_profile_map($stage, array('فوز' => 'ok', 'خسارة' => 'danger'), 'info')
     */
    function ems_profile_map($value, array $map, $default = 'neutral')
    {
        $v = trim((string) $value);
        return ems_profile_tone(isset($map[$v]) ? $map[$v] : $default);
    }

    /**
     * شارةٌ دلاليةٌ — بديلُ `state-badge` و`driver-badge` و`opp-stage` و`tnd-badge`.
     *
     * @param string $text النصُّ المعروض
     * @param string $tone إحدى النغماتِ الثماني
     * @param array  $o    icon (صنفُ FontAwesome) · lg (شارةٌ كبيرةٌ للرأس) · title
     */
    function ems_profile_badge($text, $tone = 'neutral', array $o = array())
    {
        $txt = trim((string) $text);
        if ($txt === '') { return ''; }
        $cls = 'ems-profile__pill ems-profile__pill--' . ems_profile_tone($tone)
             . (empty($o['lg']) ? '' : ' ems-profile__pill--lg');
        $ico = empty($o['icon']) ? '' : '<i class="' . ems_profile_e($o['icon']) . '" aria-hidden="true"></i>';
        $ttl = empty($o['title']) ? '' : ' title="' . ems_profile_e($o['title']) . '"';
        return '<span class="' . $cls . '"' . $ttl . '>' . $ico . ems_profile_e($txt) . '</span>';
    }

    /**
     * شبكةُ حقائق: عنوانٌ فوقَ قيمة.
     *
     * @param array $facts صفوفٌ: array('label' => …, 'value' => …, 'html' => bool)
     *                    و`html` تُمرِّر القيمةَ كما هي (لشارةٍ داخلَ حقيقة) —
     *                    والمسؤوليةُ حينها على المُنادي أن يكون قد هرَّبها.
     * @param bool  $wide شبكةٌ أعرضُ للنصوصِ الطويلة
     */
    function ems_profile_facts(array $facts, $wide = false)
    {
        if (!$facts) { return ''; }
        $out = '<div class="ems-profile__facts' . ($wide ? ' ems-profile__facts--wide' : '') . '">';
        foreach ($facts as $f) {
            if (!is_array($f) || !array_key_exists('label', $f)) { continue; }
            $raw   = array_key_exists('value', $f) ? $f['value'] : null;
            $empty = ($raw === null || (is_string($raw) && trim($raw) === ''));
            $val   = $empty ? '—' : (!empty($f['html']) ? $raw : ems_profile_e($raw));
            $out  .= '<div class="ems-profile__fact">'
                   . '<span class="ems-profile__fact-label">' . ems_profile_e($f['label']) . '</span>'
                   . '<span class="ems-profile__fact-value' . ($empty ? ' ems-profile__fact-value--empty' : '') . '">'
                   . $val . '</span></div>';
        }
        return $out . '</div>';
    }

    /**
     * لوحُ الهوية — رأسُ كلِّ بطاقةِ كِيان.
     *
     * @param array $o
     *   name   (إلزامي) اسمُ الكِيان
     *   photo  مسارُ صورةٍ — وغيابُها يُصيَّر أيقونةَ الكِيانِ لا مربّعًا رماديًّا
     *   icon   أيقونةُ البديلِ (افتراضًا fa-id-card)
     *   alt    نصُّ الصورةِ البديلُ لقارئِ الشاشة
     *   status array('text' => …, 'tone' => …)
     *   chips  شرائحُ الهوية: array('text','icon','mono')
     *   facts  شبكةُ الحقائقِ أسفلَ الاسم
     *   note   سطرُ تعريفٍ صغيرٌ تحتَ الاسم
     */
    function ems_profile_hero(array $o)
    {
        $name = isset($o['name']) ? trim((string) $o['name']) : '';
        if ($name === '') { $name = 'بلا اسم مسجل'; }

        /* ── الوسيط: صورةٌ إن وُجدت، وإلا أيقونةُ الكِيان ── */
        if (!empty($o['photo'])) {
            $media = '<img src="' . ems_profile_e($o['photo']) . '" alt="'
                   . ems_profile_e(isset($o['alt']) ? $o['alt'] : $name) . '">';
        } else {
            $ico   = !empty($o['icon']) ? $o['icon'] : 'fas fa-id-card';
            $media = '<span class="ems-profile__monogram"><i class="' . ems_profile_e($ico)
                   . '" aria-hidden="true"></i></span>';
        }

        $html  = '<section class="ems-profile__hero">';
        $html .= '<div class="ems-profile__hero-media">' . $media . '</div>';
        $html .= '<div class="ems-profile__hero-body">';

        $html .= '<div class="ems-profile__hero-top"><h2 class="ems-profile__name">'
               . ems_profile_e($name) . '</h2>';
        if (!empty($o['status']) && is_array($o['status']) && !empty($o['status']['text'])) {
            $html .= ems_profile_badge(
                $o['status']['text'],
                isset($o['status']['tone']) ? $o['status']['tone'] : 'neutral',
                array('lg' => true, 'icon' => isset($o['status']['icon']) ? $o['status']['icon'] : '')
            );
        }
        $html .= '</div>';

        if (!empty($o['chips']) && is_array($o['chips'])) {
            $html .= '<div class="ems-profile__ident">';
            foreach ($o['chips'] as $c) {
                if (!is_array($c)) { continue; }
                $t = isset($c['text']) ? trim((string) $c['text']) : '';
                if ($t === '') { continue; }
                $html .= '<span class="ems-profile__ident-item'
                       . (empty($c['mono']) ? '' : ' ems-profile__ident-item--code') . '">'
                       . (empty($c['icon']) ? '' : '<i class="' . ems_profile_e($c['icon']) . '" aria-hidden="true"></i>')
                       . ems_profile_e($t) . '</span>';
            }
            $html .= '</div>';
        }

        if (!empty($o['note'])) {
            $html .= '<p class="ems-profile__section-note">' . ems_profile_e($o['note']) . '</p>';
        }
        if (!empty($o['facts']) && is_array($o['facts'])) {
            $html .= ems_profile_facts($o['facts']);
        }

        return $html . '</div></section>';
    }

    /**
     * شريطُ المؤشرات.
     *
     * @param array $items كلُّ عنصر:
     *   value    القيمةُ — و«0» قيمةٌ صحيحةٌ لا غياب
     *   values   بديلُ `value` حين تتعدَّد الأسطر (قيمةٌ لكلِّ عملة) — ولا
     *            تُجمع عملتان في رقمٍ واحدٍ أبدًا
     *   label    (إلزامي) ما يقيسه الرقم
     *   tone     نغمةُ المؤشر: ok · warn · danger · gold · muted
     *   variant  'money' أو 'date' — يضبط حجمَ الرقمِ لا معناه
     *   unit     لاحقةٌ صغيرةٌ بعد الرقم (ساعة · سجل)
     *   href     وجهةُ التعمّق — فيصير المؤشرُ رابطًا
     * @param array $o class (أصنافٌ إضافيةٌ على الشريط)
     */
    function ems_profile_stats(array $items, array $o = array())
    {
        $items = array_filter($items, 'is_array');
        if (!$items) { return ''; }

        $html = '<div class="ems-profile__stats' . (empty($o['class']) ? '' : ' ' . ems_profile_e($o['class'])) . '">';
        foreach ($items as $it) {
            if (!isset($it['label'])) { continue; }

            $cls = 'ems-profile__stat';
            if (!empty($it['tone'])) {
                $t = strtolower(trim((string) $it['tone']));
                if (in_array($t, array('ok', 'warn', 'danger', 'gold', 'muted'), true)) {
                    $cls .= ' ems-profile__stat--' . $t;
                }
            }
            if (!empty($it['variant'])) {
                $v = strtolower(trim((string) $it['variant']));
                if (in_array($v, array('money', 'date'), true)) {
                    $cls .= ' ems-profile__stat--' . $v;
                }
            }

            /* ◆ الأسطرُ المتعددةُ حالةُ العملاتِ: كلُّ عملةٍ سطرٌ مستقلٌّ في
                 البطاقةِ نفسِها — لا مجموعَ ولا تحويلَ ضمنيّ. */
            $lines = array();
            if (isset($it['values']) && is_array($it['values']) && $it['values']) {
                $lines = $it['values'];
            } elseif (array_key_exists('value', $it)) {
                $lines = array($it['value']);
            } else {
                $lines = array('—');
            }
            $unit = empty($it['unit']) ? ''
                  : ' <span class="ems-profile__stat-unit">' . ems_profile_e($it['unit']) . '</span>';

            $body = '';
            foreach ($lines as $ln) {
                /* ◆ **مقياسٌ غيرُ محسوبٍ ليس صفرًا ولا فراغًا**: MTBF بلا عطلٍ
                     واحدٍ لا قيمةَ له — والفراغُ المطبوعُ يُقرأ «لا شيء» بينما
                     الصفرُ يُقرأ «قِيس فكان صفرًا»، وكلاهما كذبٌ على غيرِ
                     المحسوب. فالفارغُ يُعلَن «—» بصنفِ غيابٍ كما في شبكةِ
                     الحقائقِ حرفًا — عقدٌ واحدٌ للغيابِ في المكوّنِ كلِّه.
                     (قِيس على بطاقةِ المعدة: ثلاثةُ مقاييسَ من تسعةٍ تعود null.) */
                $isEmpty = ($ln === null || (is_string($ln) && trim($ln) === ''));
                $body .= '<div class="ems-profile__stat-value'
                       . ($isEmpty ? ' ems-profile__stat-value--empty' : '') . '">'
                       . ($isEmpty ? '—' : ems_profile_e($ln) . $unit) . '</div>';
            }
            $body .= '<div class="ems-profile__stat-label">' . ems_profile_e($it['label']) . '</div>';

            if (!empty($it['href'])) {
                $html .= '<a class="' . $cls . '" href="' . ems_profile_e($it['href'])
                       . '" title="تعمق: ' . ems_profile_e($it['label']) . '">' . $body . '</a>';
            } else {
                $html .= '<div class="' . $cls . '">' . $body . '</div>';
            }
        }
        return $html . '</div>';
    }

    /**
     * فتحُ مجموعةٍ — محطّةٌ من رحلةِ الكِيان.
     * @param array $o title (إلزامي) · icon · meta (شرحُ المحطّة)
     */
    function ems_profile_group_open(array $o)
    {
        $title = isset($o['title']) ? (string) $o['title'] : '';
        $html  = '<section class="ems-profile__group"><div class="ems-profile__group-head">';
        if (!empty($o['icon'])) {
            $html .= '<span class="ems-profile__group-icon"><i class="' . ems_profile_e($o['icon'])
                   . '" aria-hidden="true"></i></span>';
        }
        $html .= '<h3 class="ems-profile__group-title">' . ems_profile_e($title) . '</h3>';
        if (!empty($o['meta'])) {
            $html .= '<span class="ems-profile__group-meta">' . ems_profile_e($o['meta']) . '</span>';
        }
        return $html . '</div>';
    }

    function ems_profile_group_close() { return '</section>'; }

    /**
     * فتحُ قسمٍ — وحدةُ المحتوى داخلَ مجموعةٍ أو خارجَها.
     *
     * @param array $o title (إلزامي) · icon · meta · note (سطرُ تفسيرٍ فوق المحتوى)
     *   · id     مرساةُ القسمِ حين تقصده روابطُ داخلَ الصفحة (`#sec-…`) —
     *            بلا هذا يسقط الوصلُ صامتًا عند الترحيلِ إلى المكوّن
     *   · actions ترميزٌ جاهزٌ يُوضع في رأسِ القسم (زرُّ إضافةٍ مثلًا)
     */
    function ems_profile_section_open(array $o)
    {
        $title = isset($o['title']) ? (string) $o['title'] : '';
        $html  = '<section class="ems-profile__section"'
               . (empty($o['id']) ? '' : ' id="' . ems_profile_e($o['id']) . '"')
               . '><div class="ems-profile__section-head">';
        $html .= '<h4 class="ems-profile__section-title">';
        if (!empty($o['icon'])) {
            $html .= '<i class="' . ems_profile_e($o['icon']) . '" aria-hidden="true"></i>';
        }
        $html .= ems_profile_e($title) . '</h4>';
        if (!empty($o['meta'])) {
            $html .= '<span class="ems-profile__section-meta">' . ems_profile_e($o['meta']) . '</span>';
        }
        if (!empty($o['actions'])) {
            $html .= '<div class="ems-profile__section-actions">' . $o['actions'] . '</div>';
        }
        $html .= '</div><div class="ems-profile__section-body">';
        if (!empty($o['note'])) {
            $html .= '<p class="ems-profile__section-note">' . ems_profile_e($o['note']) . '</p>';
        }
        return $html;
    }

    function ems_profile_section_close() { return '</div></section>'; }

    /** لافتةٌ سطريةٌ داخلَ قسم: 'warn' (افتراضًا) أو 'info'. */
    function ems_profile_note($text, $kind = 'warn')
    {
        $t = trim((string) $text);
        if ($t === '') { return ''; }
        $cls = 'ems-profile__note' . ($kind === 'info' ? ' ems-profile__note--info' : '');
        return '<p class="' . $cls . '">' . ems_profile_e($t) . '</p>';
    }
}
