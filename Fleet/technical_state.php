<?php
/**
 * fleet/technical_state.php — الحالة الفنية (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: أصلٌ واحدٌ — حالتُه الفنيةُ الجاريةُ سطرٌ واحد
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_technical_state
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «الحالة الفنية»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/technical_state.php',
    'screen'     => 'flt_tech_state',
    'table'      => 'flt_technical_state',
    'title'      => 'الحالة الفنية',
    'icon'       => 'fa fa-gauge-high',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · الحالة الفنية',
    'intro'      => 'التصنيفُ الفنيُّ للأصلِ بمؤشراتِه: الجاهزيةُ والأعطالُ والتكلفةُ إلى القيمة',
    'rule'       => 'المرشَّحةُ للاستبدالِ تحتاج مبرِّرًا مكتوبًا وقرارَ إدارةٍ — ولا ترشيحَ صامت',
    'empty_hint' => 'لا حالةَ فنيةٌ مقيَّدةٌ بعدُ لأيِّ أصل',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_tech_state.register',
            'label' => 'تسجيلُ الحالة الفنية',
            'rule'  => 'المرشَّحةُ للاستبدالِ تحتاج مبرِّرًا مكتوبًا وقرارَ إدارةٍ — ولا ترشيحَ صامت',
            'fields' => array(
                'asset_code' => 'كود الأصل',
                'technical_class' => 'التصنيف الفني ▼',
                'last_assessment_date' => 'تاريخ آخر تقييم',
                'class_reason' => 'سبب التصنيف',
                'required_action' => 'الإجراء المطلوب',
                'replacement_candidate' => 'مرشحة للاستبدال؟ ▼',
                'candidacy_reason' => 'مبرر الترشيح',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('asset_code', 'technical_class', 'last_assessment_date', 'class_reason', 'required_action', 'replacement_candidate', 'candidacy_reason');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_technical_state', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_technical_state');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
