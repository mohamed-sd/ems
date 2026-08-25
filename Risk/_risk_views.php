<?php
/**
 * Risk/_risk_views.php — منتقي المنظر CM-09 وزرُّ إظهارِ كلِّ الأعمدة CM-10
 * ─────────────────────────────────────────────────────────────────────────
 * الحكمُ الثاني في §2-2: «نموذجُ البياناتِ الكاملُ لا يُختزل لمعالجةِ كثافةِ
 * العرضِ — والعلاجُ مناظرُ لا حذف». فالسجلُّ المؤسسيُّ يبقى كاملًا والتصديرُ
 * الكاملُ متاحًا للمخوَّل، بينما الواجهةُ اليوميةُ تعرض ما يحتاجه المستخدمُ أولًا.
 *
 * لماذا هنا لا في مكتبةِ المكوناتِ العامة: الأعمدةُ نفسُها حكمٌ مجاليٌّ (أيُّ عمودٍ
 * في أيِّ منظرٍ لأيِّ مهمة) — والمكوّنُ العامُّ يعرف الشكلَ ولا يعرف الحكم.
 * والمنظرُ ليس فلترَ بيانات: الفلترُ يقلّل الصفوفَ والمنظرُ يقلّل الأعمدةَ (§17).
 *
 * ولا يُخفى عمودُ حوكمةٍ في أيِّ منظر: «الكيان» و«المُنشئ» يبقيان في كلِّ منظرٍ
 * لأنهما أساسُ المساءلةِ لا حقلَ عرضٍ (§9-1) — والمنظرُ الذي يُخفيهما مرفوض.
 *
 * ◆ وشاشاتُ المخاطرِ الأربعُ تملك `?view=` بهذه الرموزِ المجالية — لا بسجلِّ
 *   المناظرِ المركزي. فتُعلَن الملكيةُ هنا مرةً واحدةً: هذا الملفُّ مطلوبٌ في
 *   رؤوسِ الأربعِ جميعًا **قبلَ** `page_header.php`، فيكفي إعلانٌ واحدٌ ولا
 *   يُنسى سطحٌ. (الشرحُ والقياسُ في ذيلِ `includes/nav_views.php`.)
 */

require_once __DIR__ . '/../includes/nav_views.php';
ems_nav_view_claim();

/** تعريفُ مناظرِ الشاشاتِ الطويلةِ الأربع (§6-2) */
function risk_view_defs($screen)
{
    $defs = array(
        // سجل المخاطر المؤسسي — 36 عمودًا
        'risk_register' => array(
            'triage' => array(
                'label' => 'منظر الفرز والتصنيف',
                'cols' => array('risk_code', 'title', 'ru_code', 'scope_type', 'root_cause', 'state', 'created_at'),
            ),
            'ownership' => array(
                'label' => 'منظر الملكية والمساءلة',
                'cols' => array('risk_code', 'title', 'owner_unit_name', 'owner_name', 'ru_code', 'state', 'review_due'),
            ),
            'measure' => array(
                'label' => 'منظر القياس والدرجات',
                'cols' => array('risk_code', 'title', 'current_level', 'target_level', 'control_effectiveness',
                                'confidence', 'velocity', 'horizon', 'appetite_verdict'),
            ),
            'exposure' => array(
                'label' => 'منظر التعرض والشهية',
                'cols' => array('risk_code', 'title', 'current_level', 'appetite_verdict',
                                'exposure_amount', 'exposure_currency', 'review_due'),
            ),
            'all' => array('label' => 'كل الأعمدة (36)', 'cols' => array()), // CM-10
        ),
        // ملف الخطر — 24 عمودًا (مناظرُه أقسامٌ لا أعمدةُ جدول)
        'risk_card' => array(
            'summary' => array('label' => 'منظر الملخص', 'cols' => array()),
            'all' => array('label' => 'كل الأقسام (24)', 'cols' => array()),
        ),
        // تقييم الخطر ونسخه التاريخية — 24 عمودًا
        'risk_assessment' => array(
            'timeline' => array(
                'label' => 'منظر التتبع الزمني',
                'cols' => array('assess_type', 'level', 'score', 'assessed_at', 'assessor', 'parent_ref'),
            ),
            'dimensions' => array(
                'label' => 'منظر الأبعاد الثمانية',
                'cols' => array('assess_type', 'likelihood', 'impact_safety', 'impact_operations',
                                'impact_financial', 'impact_legal', 'impact_reputation',
                                'impact_environment', 'impact_data', 'impact_people'),
            ),
            'challenge' => array(
                'label' => 'منظر التحدي والاعتماد',
                'cols' => array('assess_type', 'level', 'confidence', 'technique', 'assessor',
                                'challenger', 'approved_at', 'authority_ref'),
            ),
            'all' => array('label' => 'كل الأعمدة (24)', 'cols' => array()),
        ),
        // الضوابط والضوابط الحرجة — 24 عمودًا
        'risk_controls' => array(
            'operate' => array(
                'label' => 'منظر التشغيل اليومي',
                'cols' => array('control_code', 'name_ar', 'ctype', 'owner_name', 'frequency', 'process_ref'),
            ),
            'verify' => array(
                'label' => 'منظر التحقق والفعالية',
                'cols' => array('control_code', 'name_ar', 'effectiveness', 'last_verified_at',
                                'last_verify_result', 'next_verify_due', 'verifier_name'),
            ),
            'critical' => array(
                'label' => 'منظر الضوابط الحرجة',
                'cols' => array('control_code', 'name_ar', 'is_critical', 'hico_event',
                                'perf_criterion', 'verify_method', 'verifier_name', 'fail_action'),
            ),
            'all' => array('label' => 'كل الأعمدة (24)', 'cols' => array()),
        ),
    );
    return isset($defs[$screen]) ? $defs[$screen] : array();
}

/** المنظرُ الجاري من الطلب — والافتراضُ أولُ منظرٍ معرَّفٍ لا «الكل» */
function risk_current_view($screen)
{
    $defs = risk_view_defs($screen);
    if (empty($defs)) { return 'all'; }
    $v = isset($_GET['view']) ? (string) $_GET['view'] : '';
    if ($v !== '' && isset($defs[$v])) { return $v; }
    $keys = array_keys($defs);
    return $keys[0];
}

/**
 * أيُعرض هذا العمودُ في المنظرِ الجاري؟
 * الأعمدةُ الحاكمةُ استثناءٌ دائم: لا منظرَ يُخفي «الكيان» ولا «المُنشئ» —
 * فهي أساسُ المساءلةِ لا حقولُ عرضٍ (§9-1 · §17 «ليست حقولَ عرض»).
 */
function risk_col_visible($screen, $view, $col)
{
    static $ALWAYS = array('risk_code', 'title', 'control_code', 'name_ar', 'assess_type');
    if ($view === 'all') { return true; }
    if (in_array($col, $ALWAYS, true)) { return true; }
    $defs = risk_view_defs($screen);
    if (empty($defs[$view]['cols'])) { return true; }
    return in_array($col, $defs[$view]['cols'], true);
}

/**
 * شريطُ منتقي المنظرِ CM-09 + زرِّ إظهارِ كلِّ الأعمدةِ CM-10.
 * الفلاترُ الجاريةُ تُحمَل في الروابطِ كي لا يُفقد الترشيحُ بتغييرِ المنظر —
 * فتغييرُ المنظرِ تغييرُ أعمدةٍ لا إعادةُ استعلام.
 */
function risk_view_bar($screen, $view, array $carry = array())
{
    $defs = risk_view_defs($screen);
    if (empty($defs)) { return; }
    $qs = function ($v) use ($carry) {
        $p = $carry;
        $p['view'] = $v;
        return '?' . http_build_query($p);
    };
    /* ف٩-٢ · بوابة G20: منتقي المنظرِ **أداةٌ في الشريطِ الموحَّد** (الأداة ③)
       لا شريطُ أدواتٍ ثانٍ في الصفحة — فيرث تخطيطَه ولا يحمل صنفَه.
       ف١٢-٢ · بوابة G17: والمنظرُ النشطُ **حالةُ عنصرٍ** لا إجراءٌ رئيسيّ،
       فيُصيَّر رقاقةً بلكنةِ العلامة (وهو موضعُها المنصوص: «للإجراءِ الرئيسيِّ
       **والعنصرِ النشط**») — وكان صنفُ الزرِّ الرئيسيِّ يجعله رئيسيًّا ثانيًا
       فيُلغي معنى الواحد. ويُصلَح هنا مرةً فيسري على شاشاتِ المخاطرِ الأربع. */
    echo '<div class="ux-viewpicker" role="group" aria-label="منتقي المنظر" '
       . 'style="gap:6px;flex-wrap:wrap;align-items:center">';
    echo '<span style="font-size:.78rem;opacity:.75">المنظر:</span>';
    foreach ($defs as $k => $d) {
        if ($k === 'all') { continue; }
        $cls = ($view === $k) ? 'ux-chip' : 'ems-btn-secondary';
        printf('<a class="%s" style="font-size:.78rem;padding:4px 10px" href="%s" %s>%s</a>',
            $cls, htmlspecialchars($qs($k)),
            $view === $k ? 'aria-current="true"' : '',
            htmlspecialchars($d['label']));
    }
    if (isset($defs['all'])) {
        $cls = ($view === 'all') ? 'ux-chip' : 'ems-btn-secondary';
        printf('<a class="%s" style="font-size:.78rem;padding:4px 10px" href="%s" '
             . 'title="نموذج البيانات لا يختزل — والعلاج مناظر لا حذف"><i class="fa fa-table-columns"></i> %s</a>',
            $cls, htmlspecialchars($qs('all')), htmlspecialchars($defs['all']['label']));
    }
    echo '</div>';
}

/** نصُّ أعمدةِ المنظرِ لسجلِّ التصدير (§9-4 البندُ الخامس) */
function risk_view_columns_text($screen, $view)
{
    $defs = risk_view_defs($screen);
    if ($view === 'all' || empty($defs[$view]['cols'])) { return 'كل الأعمدة'; }
    return implode(' · ', $defs[$view]['cols']);
}
