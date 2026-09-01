<?php
/**
 * tools/specs/dep07_hr_headcount.php — «خطة القوى العاملة» (DEP-07 · الهدف 03)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الهدفُ الوحيدُ الباقي غيرَ مبنيٍّ** في كونِ الأهدافِ كلِّه (412/413).
 *   وحُكم عليه `NOT_BUILT` في جولةِ `GOV_UI_EXEC` بشاهدٍ مقيس:
 *   `workforce/hr_workforce_report.php` سطحُ **تقريرٍ** يخدم الهدف 24
 *   «تقرير القوى العاملة» باسمِه المخزَّن — **ولا يُلبَس التقريرُ اسمَ الخطة**.
 *
 * ◆ **وحبّتُه لا مالكَ لها**: «وحدة × فئة × سنة — سطر خطة» — ولا جدولَ في
 *   الشجرةِ بهذه الحبّة (`workforce_requirement` حبّتُها احتياجُ مشروعٍ لا
 *   خطةٌ سنويّة، و`hr_job_movement` واقعةُ حركة). فـ`table_mode => 'create'`
 *   — ⛔ ولا يُوسَّع جدولٌ حبّتُه غيرُ حبّتِه فيصير سجلّين في واحد.
 *
 * ◆ **ومصدرُ حقيقتِه بنصِّ البطاقة**: «الخطة تعتمد سنويًّا وتحكم فتح الشواغر —
 *   ولا شاغر خارجها إلا بموافقة مسارها». فالسطحُ **بوّابةُ فتحِ الشواغر**،
 *   وحقلُ «خارج الخطة بموافقة» يقيس الاستثناءَ ولا يُبيحه.
 *
 * ◆ **والمفاتيحُ بترتيبِ ورقةِ الحقولِ حرفًا** (`HR-03` — أربعةَ عشرَ حقلًا)،
 *   وحارسُ الأداةِ يردُّ المواصفةَ إن اختلف العدد.
 */
return array(
    'dept'    => 'DEP-07',
    'dept_ar' => 'إدارة الموارد البشرية',
    'unit'    => 'إدارة الموارد البشرية',
    'role_id' => 4,
    'dir'     => 'employees',
    'neighbor_route' => 'employees/job_titles.php',
    'screens' => array(

array(
    'code' => 'hr_headcount_plan', 'file' => 'hr_headcount_plan.php', 'placement_id' => 159,
    'surface' => 'خطة القوى العاملة — بحسب انطباق الشركة',
    'title' => 'خطة القوى العاملة', 'icon' => 'fa fa-people-arrows',
    'table' => 'hr_headcount_plan', 'table_mode' => 'create', 'nature' => 'register',
    'group' => 'التوظيف والتعاقد', 'sort_no' => 2,
    'grain' => 'سطرُ خطةٍ واحدٌ: وحدةٌ تنظيميّةٌ × فئةٌ × سنة',
    'target_ref' => 'DEP-07·3·خطه القوي العامله — بحسب انطباق الشركه',
    'intro' => 'خطةُ الأعدادِ السنويةِ بالوحداتِ والفئات: المعتمَدُ والفعليُّ والفجوةُ — وبوّابةُ فتحِ الشواغر',
    'rule'  => 'الخطةُ تُعتمد سنويًّا وتحكم فتحَ الشواغر — ولا شاغرَ خارجَها إلّا بموافقةِ مسارِها، وتُقاس في «خارج الخطة بموافقة»',
    'empty' => 'لا سطرَ خطةٍ معتمَدٌ بعد',
    'order' => 'plan_year DESC, id DESC',
    'create' => array('plan_year', 'org_unit_ref', 'category_ref', 'approved_headcount'),
    'keys' => array('plan_code', 'plan_year', 'org_unit_ref', 'category_ref',
                    'approved_headcount', 'actual_headcount', 'headcount_gap',
                    'open_vacancies', 'off_plan_approved', 'row_state',
                    'created_by', 'created_at', 'data_state', 'src_ref'),
    'types' => array(
        'plan_year'          => 'SMALLINT NULL DEFAULT NULL',
        'approved_headcount' => 'INT NULL DEFAULT NULL',
        'actual_headcount'   => 'INT NULL DEFAULT NULL',
        'headcount_gap'      => 'INT NULL DEFAULT NULL',
        'open_vacancies'     => 'INT NULL DEFAULT NULL',
        'off_plan_approved'  => 'INT NULL DEFAULT NULL',
        'org_unit_ref'       => 'VARCHAR(120) NULL DEFAULT NULL',
        'category_ref'       => 'VARCHAR(120) NULL DEFAULT NULL',
    ),
),

    ),
);
