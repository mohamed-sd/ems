<?php
/**
 * employees/hr_headcount_plan.php — خطة القوى العاملة (DEP-07 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: سطرُ خطةٍ واحدٌ: وحدةٌ تنظيميّةٌ × فئةٌ × سنة
 * المالك: إدارة الموارد البشرية · مصدرُ الحقيقة: hr_headcount_plan
 * الأصل: ورقةُ «إدارة الموارد البشرية» — السطح «خطة القوى العاملة — بحسب انطباق الشركة»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'employees/hr_headcount_plan.php',
    'screen'     => 'hr_headcount_plan',
    'table'      => 'hr_headcount_plan',
    'title'      => 'خطة القوى العاملة',
    'icon'       => 'fa fa-people-arrows',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · خطة القوى العاملة — بحسب انطباق الشركة',
    'intro'      => 'خطةُ الأعدادِ السنويةِ بالوحداتِ والفئات: المعتمَدُ والفعليُّ والفجوةُ — وبوّابةُ فتحِ الشواغر',
    'rule'       => 'الخطةُ تُعتمد سنويًّا وتحكم فتحَ الشواغر — ولا شاغرَ خارجَها إلّا بموافقةِ مسارِها، وتُقاس في «خارج الخطة بموافقة»',
    'empty_hint' => 'لا سطرَ خطةٍ معتمَدٌ بعد',
    'order'       => 'plan_year DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.hr_headcount_plan.register',
            'label' => 'تسجيلُ خطة القوى العاملة',
            'rule'  => 'الخطةُ تُعتمد سنويًّا وتحكم فتحَ الشواغر — ولا شاغرَ خارجَها إلّا بموافقةِ مسارِها، وتُقاس في «خارج الخطة بموافقة»',
            'fields' => array(
                'plan_year' => 'السنة ◄',
                'org_unit_ref' => 'الوحدة التنظيمية ◄',
                'category_ref' => 'الفئة ▼',
                'approved_headcount' => 'العدد المعتمد',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('plan_year', 'org_unit_ref', 'category_ref', 'approved_headcount');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('hr_headcount_plan', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في hr_headcount_plan');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
