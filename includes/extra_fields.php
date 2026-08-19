<?php
/**
 * includes/extra_fields.php — طبقةُ «البياناتِ الإضافية» المركزية (XF-01)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المشكلةُ التي تحلُّها**: وثيقةُ الأعمدة (SCR-DES) تفرض على كلِّ شاشةٍ
 *   أعمدتَها المعتمدة، فحُقنت رؤوسُها في الملفّات (`data-fn` · موجة CMP-03 ⑤)
 *   **قبلَ** أن يكون لها عمودٌ في القاعدة. فصار في النظام **601 عمودًا محقونًا
 *   بلا مصدر** (335 `data-fn` + 266 `data-gov`)، كلُّ خليةٍ فيها «—» ورأسُه
 *   موسومٌ «بلا مصدر» في منتقي الأعمدة.
 *
 * ◆ **قرارُ المالك (2026-08-19)**: هذه الأعمدةُ **ليست نقصًا في الإلزام** — هي
 *   بياناتٌ إضافيةٌ تُستكمَل لاحقًا. فالحدُّ الأدنى لإضافةِ سجلٍّ يبقى كما هو،
 *   وتُعرض الإضافيةُ:
 *     ① في **الفورم** خلفَ زرِّ «المزيد» — مطويةً، **غيرَ مطلوبة**، تُملأ متى شئت.
 *     ② في **جدولِ العرض** مجموعةً واحدةً «بيانات إضافية» مطويةً ابتداءً،
 *        تُظهَر وتُخفى بنقرةٍ من منتقي الأعمدة القائم.
 *     ③ في **نافذةِ التفاصيل** قسمًا مستقلًّا لا يُزاحم البياناتِ الأساسية.
 *
 * ◆ **لماذا سجلٌّ مركزيٌّ لا شيفرةٌ في كلِّ شاشة**: العمليةُ نفسُها ستتكرّر على
 *   عشراتِ الشاشات. فالسجلُّ هنا **مصدرُ الحقيقةِ الوحيد** لكلِّ شاشة: منه
 *   يُرسم الفورمُ، ومنه تُجمَع قيمُ POST، ومنه تُطبَع خلايا الصفِّ، ومنه تُبنى
 *   سماتُ نافذةِ التفاصيل. فتصحيحٌ واحدٌ يسري على الكلّ، ولا تتفرّق النسخ.
 *
 * ◆ **ولا يُولَّد رأسُ العمود من هنا**: رؤوسُ `<th>` تبقى مكتوبةً حرفيًّا في ملفِّ
 *   الشاشة — لأن أدواتِ الجردِ (`tools/cmp03_*`) تقرأ الملفَّ نصًّا لتقيسَ
 *   مطابقتَه لوثيقةِ الأعمدة. فتوليدُها هنا كان سيُعمي تلك البوابةَ عن 13 عمودًا
 *   موجودًا فعلًا. **الرأسُ في الملفّ · والخليةُ من السجل** — والتسميةُ هي
 *   الوصلُ بينهما.
 *
 * ◆ **عقدُ الوسم**: كلُّ رأسٍ موصولٍ يحمل `data-fn-src="<المفتاح>"`. وبها يعرف
 *   `ui-unification.js` أن الشاشةَ تطبع خليتَه بنفسِها فلا يحشوها «—» ولا
 *   يسمها «بلا مصدر». (والوسمُ `data-fn="1"` يبقى — فهو أثرُ المطلبِ في
 *   الوثيقة، وحذفُه يمحو نسبَ العمود.)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_xf_registry')) {

/**
 * سجلُّ الشاشاتِ ← بياناتُها الإضافية.
 *
 * المفتاحُ: مسارُ الشاشةِ من جذرِ المشروع (كما في `nav_canonical.route`).
 * الحقول:
 *   table    اسمُ الجدولِ الذي تُكتب فيه الأعمدةُ الخاصّة (`own`).
 *   title    عنوانُ قسمِ «المزيد» في الفورم.
 *   hint     سطرٌ تحتَ العنوان يشرح أن الحقولَ غيرُ مطلوبة.
 *   group    مجموعةُ الأعمدةِ في منتقي الأعمدة (المفتاح · التسمية · الطيُّ الافتراضي).
 *   columns  الأعمدةُ **بترتيبِ رؤوسِها في الجدول** — والترتيبُ ملزِمٌ لأن
 *            `ems_xf_tds()` تطبع بحسبِه.
 *
 * وكلُّ عمودٍ له `kind` يحدّد ماذا يُفعل به:
 *   own       عمودٌ **جديدٌ اختياريٌّ** في الجدول → يظهر في «المزيد» ويُكتب ويُعرض.
 *   existing  عمودٌ **قائمٌ سلفًا** وله حقلٌ في الفورم الأساسي → يُعرض فقط
 *             (وإدراجُه في «المزيد» كان سيزدوج الحقلَ ويتضارب).
 *   derived   قيمةٌ **مشتقّةٌ** (اسمُ المُنشئ · تاريخُ الإنشاء) → تُعرض فقط.
 */
function ems_xf_registry()
{
    static $reg = null;
    if ($reg !== null) { return $reg; }

    $reg = array(

        /* ══════════════════════════════════════════════════════════════════
         * سجل العملاء — الشاشةُ الأولى (شاهدُ المالك قبلَ التعميم)
         * المصدرُ الحاكم: SCN-325 «سجل العملاء … 18 عمودًا، وأولُها كود العميل ·
         * الاسم القانوني الكامل · الشكل النظامي · بلد التسجيل · رقم السجل
         * التجاري · الرقم الضريبي». والأعمدةُ العشرةُ الخاصّةُ أُنشئت في
         * `database/migrations/2027_07_16_clients_optional_profile_fields.php`.
         * ══════════════════════════════════════════════════════════════════ */
        'Clients/clients.php' => array(
            'table' => 'clients',
            'title' => 'بيانات إضافية للعميل',
            'hint'  => 'كلُّها اختياريةٌ — احفظ العميلَ بالحدِّ الأدنى الآن، وأكملْ هذه متى توفّرت.',
            'group' => array('key' => 'extra', 'label' => 'بيانات إضافية', 'default' => 'hidden'),
            'columns' => array(

                array('key' => 'legal_name', 'label' => 'الاسم القانوني الكامل', 'kind' => 'own',
                      'type' => 'text', 'max' => 255, 'icon' => 'fas fa-file-signature',
                      'ph' => 'الاسم كما هو في السجل التجاري'),

                array('key' => 'legal_form', 'label' => 'الشكل النظامي', 'kind' => 'own',
                      'type' => 'select', 'max' => 100, 'icon' => 'fas fa-landmark',
                      'options' => array('شركة مساهمة', 'شركة ذات مسؤولية محدودة', 'شركة تضامن',
                                         'شركة توصية', 'مؤسسة فردية', 'جهة حكومية',
                                         'منظمة غير ربحية', 'فرع شركة أجنبية', 'أخرى')),

                array('key' => 'registration_country', 'label' => 'بلد التسجيل', 'kind' => 'own',
                      'type' => 'text', 'max' => 100, 'icon' => 'fas fa-globe',
                      'ph' => 'مثال: السودان'),

                array('key' => 'commercial_reg_no', 'label' => 'رقم السجل التجاري', 'kind' => 'own',
                      'type' => 'text', 'max' => 100, 'icon' => 'fas fa-id-card',
                      'ph' => 'رقم القيد في السجل التجاري'),

                array('key' => 'tax_id', 'label' => 'الرقم الضريبي', 'kind' => 'own',
                      'type' => 'text', 'max' => 100, 'icon' => 'fas fa-percent',
                      'ph' => 'الرقم الضريبي المسجَّل'),

                array('key' => 'registered_address', 'label' => 'العنوان المسجَّل', 'kind' => 'own',
                      'type' => 'textarea', 'max' => 500, 'icon' => 'fas fa-map-marker-alt',
                      'ph' => 'العنوان كما في وثائق التسجيل', 'wide' => true),

                array('key' => 'contact_person', 'label' => 'جهة الاتصال', 'kind' => 'own',
                      'type' => 'text', 'max' => 255, 'icon' => 'fas fa-user-tie',
                      'ph' => 'اسم مسؤول التواصل لدى العميل'),

                array('key' => 'contact_title', 'label' => 'المنصب', 'kind' => 'own',
                      'type' => 'text', 'max' => 150, 'icon' => 'fas fa-briefcase',
                      'ph' => 'مثال: مدير المشتريات'),

                /* ◆ قائمٌ سلفًا: حقلُه في الفورم الأساسيّ — يُعرض ولا يُكرَّر */
                array('key' => 'email', 'label' => 'البريد', 'kind' => 'existing',
                      'icon' => 'fas fa-envelope'),

                array('key' => 'client_classification', 'label' => 'تصنيف العميل', 'kind' => 'own',
                      'type' => 'select', 'max' => 100, 'icon' => 'fas fa-star',
                      'options' => array('استراتيجي', 'رئيسي', 'عادي', 'محتمل', 'تحت المراجعة')),

                array('key' => 'importance_tier', 'label' => 'شريحة الأهمية', 'kind' => 'own',
                      'type' => 'select', 'max' => 50, 'icon' => 'fas fa-layer-group',
                      'options' => array('أ — بالغة الأهمية', 'ب — مهمة', 'ج — عادية', 'د — محدودة')),

                /* ◆ مشتقّتان: مصدرُهما `created_by` و`created_at` في الصفِّ نفسِه */
                array('key' => 'created_by', 'label' => 'سجّله', 'kind' => 'derived',
                      'render' => 'actor', 'icon' => 'fas fa-user-plus'),

                array('key' => 'created_at', 'label' => 'تاريخ التسجيل', 'kind' => 'derived',
                      'render' => 'datetime', 'icon' => 'fas fa-calendar-plus'),
            ),
        ),

    );

    /* ══ الدمجُ مع المولَّد — و**المكتوبُ يدويًّا يغلب** ══════════════════════
     * ◆ `tools/xf_apply.php` يكتب `includes/extra_fields_generated.php` عن خطةٍ
     *   راجعها المالك. ويُدمج **تحت** المكتوبِ هنا لا فوقَه: فشاشةٌ ضُبطت بيدٍ
     *   (خياراتُ قائمةٍ · تسميةٌ أدقُّ · حقلٌ عريض) لا يدهسها تشغيلٌ آليٌّ لاحق.
     * ◆ وغيابُ الملفِّ حالةٌ طبيعيةٌ لا خطأ — قبلَ أوّلِ تعميمٍ لا وجودَ له. */
    $gen = __DIR__ . '/extra_fields_generated.php';
    if (is_file($gen)) {
        $g = include $gen;
        if (is_array($g)) { $reg = $reg + $g; }
    }
    return $reg;
}

/** تعريفُ شاشةٍ أو null. المسارُ يُطبَّع (بادئة `../` و`/` تُنزع). */
function ems_xf_screen($screen)
{
    $s = ltrim(preg_replace('~^\.\./~', '', trim((string) $screen)), '/');
    $reg = ems_xf_registry();
    return isset($reg[$s]) ? $reg[$s] : null;
}

/** أعمدةُ شاشةٍ (كلُّ الأنواع) بترتيبِ الرؤوس. */
function ems_xf_columns($screen)
{
    $d = ems_xf_screen($screen);
    return $d ? $d['columns'] : array();
}

/** الأعمدةُ الخاصّةُ وحدَها (`own`) — وهي التي تُكتب وتظهر في «المزيد». */
function ems_xf_own_columns($screen)
{
    $out = array();
    foreach (ems_xf_columns($screen) as $c) {
        if (isset($c['kind']) && $c['kind'] === 'own') { $out[] = $c; }
    }
    return $out;
}

/**
 * سماتُ الرأسِ الواحد — تُلصق في `<th>` داخلَ ملفِّ الشاشة.
 *
 * تُطبع من هنا لا تُكتب يدويًّا كي لا تتفرّق: `data-fn-src` (وصلُ المصدر) +
 * وسومُ المجموعةِ الثلاثة (مفتاحُها · تسميتُها · طيُّها الافتراضي). والرأسُ
 * يبقى نصًّا في الملفّ فتراه أدواتُ الجرد.
 */
function ems_xf_th_attrs($screen, $key)
{
    $d = ems_xf_screen($screen);
    if (!$d) { return ''; }
    $g = isset($d['group']) ? $d['group'] : array('key' => 'extra', 'label' => 'بيانات إضافية', 'default' => 'hidden');
    $a = ' data-fn-src="' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . '"'
       . ' data-col-group="' . htmlspecialchars((string) $g['key'], ENT_QUOTES, 'UTF-8') . '"'
       . ' data-col-group-label="' . htmlspecialchars((string) $g['label'], ENT_QUOTES, 'UTF-8') . '"';
    if (isset($g['default']) && $g['default'] === 'hidden') { $a .= ' data-col-group-default="hidden"'; }
    return $a;
}

/**
 * قيمةُ عمودٍ من صفٍّ — نقطةُ الاشتقاقِ الوحيدة.
 *
 * `$ctx` يمرّر ما تحتاجه المشتقّاتُ (مثل `conn` لترجمةِ `created_by` إلى اسم).
 * ويُرجَع نصٌّ خامٌّ **غيرُ مُهرَّب** — التهريبُ مسؤوليةُ الطابع.
 */
function ems_xf_value($col, $row, $ctx = array())
{
    $k = $col['key'];
    $render = isset($col['render']) ? $col['render'] : '';

    if ($render === 'actor') {
        /* اسمُ المُنشئ: `creator_name` إن جاء مع الصفّ (LEFT JOIN)، وإلّا
           `ems_actor_label()` — ولا يُطبع رقمُ المستخدمِ عاريًا أبدًا. */
        if (isset($row['creator_name']) && trim((string) $row['creator_name']) !== '') {
            return (string) $row['creator_name'];
        }
        $uid = isset($row[$k]) ? (int) $row[$k] : 0;
        if ($uid > 0 && function_exists('ems_actor_label') && isset($ctx['conn'])) {
            return (string) ems_actor_label($ctx['conn'], $uid);
        }
        return '';
    }

    if ($render === 'datetime' || $render === 'date') {
        $v = isset($row[$k]) ? trim((string) $row[$k]) : '';
        if ($v === '' || $v === '0000-00-00 00:00:00' || $v === '0000-00-00') { return ''; }
        $t = strtotime($v);
        if ($t === false) { return $v; }
        return date($render === 'date' ? 'Y-m-d' : 'Y-m-d H:i', $t);
    }

    return isset($row[$k]) ? trim((string) $row[$k]) : '';
}

/**
 * خلايا الصفِّ للأعمدةِ الإضافية — تُنادى داخلَ حلقةِ الصفوفِ بعدَ الخلايا الأساسية.
 *
 * ◆ **تُطبع دائمًا وإن كانت فارغة**: عددُ الخلايا يجب أن يساوي عددَ الرؤوس،
 *   وإلّا رمى DataTables «Incorrect column count» وسقط الجدولُ كلُّه.
 * ◆ والفارغُ يُطبع «—» بصنفِ `ems-gov-empty` — نفسُ مظهرِ الفراغِ في كلِّ النظام،
 *   فلا يقرأ المستخدمُ فراغَ بيانٍ عطلًا.
 */
function ems_xf_tds($screen, $row, $ctx = array())
{
    $out = '';
    foreach (ems_xf_columns($screen) as $c) {
        $v = ems_xf_value($c, $row, $ctx);
        if ($v === '') {
            $out .= '<td class="ems-xf-cell ems-gov-empty">—</td>';
        } else {
            $out .= '<td class="ems-xf-cell">' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '</td>';
        }
    }
    return $out;
}

/**
 * سماتُ `data-xf-*` لزرِّ العرض/التعديل — منها تُبنى نافذةُ التفاصيل وملءُ الفورم.
 * (المفتاحُ يُحوَّل إلى kebab-case كي يقرأه jQuery `.data()` كما هو متوقَّع.)
 */
function ems_xf_data_attrs($screen, $row, $ctx = array())
{
    $out = '';
    foreach (ems_xf_columns($screen) as $c) {
        $v = ems_xf_value($c, $row, $ctx);
        $attr = 'data-xf-' . str_replace('_', '-', $c['key']);
        $out .= ' ' . $attr . "='" . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . "'";
    }
    return $out;
}

/** خريطةُ JS: مفتاحٌ ← {label, kind, icon} — لبناءِ قسمِ التفاصيلِ بلا تكرارِ التسميات. */
function ems_xf_js_map($screen)
{
    $m = array();
    foreach (ems_xf_columns($screen) as $c) {
        $m[$c['key']] = array(
            'label' => $c['label'],
            'kind'  => isset($c['kind']) ? $c['kind'] : 'own',
            'icon'  => isset($c['icon']) ? $c['icon'] : 'fas fa-circle-info',
        );
    }
    return $m;
}

/**
 * جمعُ قيمِ «المزيد» من POST — سليمةً ومقصوصةً على طولِ العمود.
 *
 * ◆ **الغيابُ ليس محوًا**: حقلٌ لم يُرسَل أصلًا (نموذجٌ قديمٌ · طلبٌ جزئي) لا
 *   يدخل المصفوفةَ فلا يُكتب — بينما حقلٌ أُرسِل فارغًا **يُكتب NULL** عمدًا،
 *   فذاك تفريغٌ إراديٌّ من المستخدم. والفرقُ بينهما جوهريٌّ: لولاه لمحا كلُّ
 *   حفظٍ من نموذجٍ لا يحمل الحقلَ بياناتٍ أدخلها غيرُه.
 * ◆ ولا يُقبل مفتاحٌ ليس في السجلِّ — فالمصدرُ هو السجلُّ لا حمولةُ الطلب.
 */
function ems_xf_collect($screen, $post)
{
    $out = array();
    foreach (ems_xf_own_columns($screen) as $c) {
        $k = $c['key'];
        if (!array_key_exists($k, $post)) { continue; }   /* غائبٌ ⇒ لا يُمسّ */
        $v = is_scalar($post[$k]) ? trim((string) $post[$k]) : '';
        if ($v === '') { $out[$k] = null; continue; }      /* أُرسِل فارغًا ⇒ تفريغٌ إرادي */

        $max = isset($c['max']) ? (int) $c['max'] : 255;
        if (function_exists('mb_substr')) { $v = mb_substr($v, 0, $max, 'UTF-8'); }
        else { $v = substr($v, 0, $max); }

        /* ◆ قائمةٌ ⇒ **قائمةُ سماحٍ صلبة**: قيمةٌ خارجَ الخياراتِ تُردُّ NULL لا
             تُكتب. فالـ`<select>` قيدُ واجهةٍ يُتجاوَز بطلبٍ مُلفَّق. */
        if (isset($c['type']) && $c['type'] === 'select' && !empty($c['options'])
            && !in_array($v, $c['options'], true)) {
            $out[$k] = null;
            continue;
        }
        $out[$k] = $v;
    }
    return $out;
}

/**
 * قسمُ «المزيد» في الفورم — مطويٌّ، غيرُ مطلوب، بزرٍّ يفتحه.
 *
 * ◆ `<details>` عنصرُ الطيِّ الأصليّ: يعمل بلا جافاسكربت، ويُعلن حالتَه
 *   للقارئِ الصوتيِّ من غيرِ `aria` مُصطنَع. و`reset()` لا يطويه — فالمعالجُ
 *   في الشاشةِ يفتحه عند التعديلِ إن كان فيه قيمة.
 * ◆ **ولا حقلَ `required` هنا إطلاقًا** — هذا شرطُ القسم لا تفصيلَ تنفيذ.
 */
function ems_xf_render_form($screen, $values = array())
{
    $d = ems_xf_screen($screen);
    if (!$d) { return; }
    $own = ems_xf_own_columns($screen);
    if (!$own) { return; }
    $e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
    $n = count($own);
    ?>
    <details class="ems-xf-more" id="emsXfMore">
        <summary class="ems-xf-more-summary">
            <i class="fas fa-circle-plus ems-xf-more-icon"></i>
            <span class="ems-xf-more-title"><?php echo $e($d['title']); ?></span>
            <span class="ems-xf-more-count"><?php echo (int) $n; ?> حقول اختيارية</span>
            <span class="ems-xf-more-chevron"><i class="fas fa-chevron-down"></i></span>
        </summary>
        <div class="ems-xf-more-body">
            <?php if (!empty($d['hint'])): ?>
                <div class="ems-xf-more-hint"><i class="fas fa-info-circle"></i> <?php echo $e($d['hint']); ?></div>
            <?php endif; ?>
            <div class="form-grid ems-xf-grid">
                <?php foreach ($own as $c):
                    $k    = $c['key'];
                    $val  = isset($values[$k]) ? (string) $values[$k] : '';
                    $type = isset($c['type']) ? $c['type'] : 'text';
                    $wide = !empty($c['wide']); ?>
                    <div class="<?php echo $wide ? 'ems-xf-wide' : ''; ?>">
                        <?php /* ◆ **بلا وسمِ «اختياري» هنا**: عرفُ النظامِ أن الإلزامَ يُعلَن
                                    بـ`*` في التسمية، واختياريتُها قيلت مرّةً في رأسِ القسم.
                                    وتكرارُها عشرَ مراتٍ ضجيجٌ يخالف بقيةَ حقولِ النموذج. */ ?>
                        <label for="<?php echo $e($k); ?>">
                            <i class="<?php echo $e(isset($c['icon']) ? $c['icon'] : 'fas fa-circle-info'); ?>"></i>
                            <?php echo $e($c['label']); ?>
                        </label>
                        <?php if ($type === 'select'): ?>
                            <select name="<?php echo $e($k); ?>" id="<?php echo $e($k); ?>" class="ems-xf-input">
                                <option value="">— بلا تحديد —</option>
                                <?php foreach ((array) $c['options'] as $o): ?>
                                    <option value="<?php echo $e($o); ?>"<?php echo ($val === $o ? ' selected' : ''); ?>><?php echo $e($o); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($type === 'textarea'): ?>
                            <textarea name="<?php echo $e($k); ?>" id="<?php echo $e($k); ?>" rows="2"
                                      class="ems-xf-input"
                                      maxlength="<?php echo (int) (isset($c['max']) ? $c['max'] : 500); ?>"
                                      placeholder="<?php echo $e(isset($c['ph']) ? $c['ph'] : ''); ?>"><?php echo $e($val); ?></textarea>
                        <?php else: ?>
                            <input type="text" name="<?php echo $e($k); ?>" id="<?php echo $e($k); ?>"
                                   class="ems-xf-input"
                                   maxlength="<?php echo (int) (isset($c['max']) ? $c['max'] : 255); ?>"
                                   placeholder="<?php echo $e(isset($c['ph']) ? $c['ph'] : ''); ?>"
                                   value="<?php echo $e($val); ?>" />
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </details>
    <?php
}

} // end function_exists guard
