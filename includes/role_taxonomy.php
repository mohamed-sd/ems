<?php
/**
 * includes/role_taxonomy.php — تصنيفُ الأدوار: الدور ← (عائلة · مستوى · قالب مسمّى)
 * ───────────────────────────────────────────────────────────────────────────
 * **المصدرُ الواحد** الذي يقرؤه بناءُ القوالب (tools/perm_templates_build.php)
 * وجسرُ الهوية (tools/identity_bridge_build.php) معًا — فلو تفرّقا لاختلف
 * المشتقُّ عن الحي وامتنع شرطُ «صفر فرق» في مقارنة الظل (E-04 SEC-019).
 *
 * الطبقاتُ الثلاث تُجمَع عند الحل: عائلة + مستوى + مسمّى = صلاحياتُ الدور حرفًا.
 * والمسمّى هنا **قالبُ الدور** (`role_N`) لا مسمًّى وظيفيًّا حقيقيًّا — جسرٌ
 * انتقاليٌّ معلَن: يُستبدل بمسميات `job_titles` الحقيقية متى بُنيت الهويةُ
 * الكاملة، وحينها يُحذف هذا الملف. (قرار المالك 2026-08-06: الأقصرُ أولًا
 * كي تُثبت البنودُ قبل أن تُبنى الهويةُ فوقها — عزلُ المتغيرات.)
 *
 * ملاحظة: `person_positions.family_code`/`level_code` مقيَّدان بمفاتيح خارجية
 * إلى `hr_dictionaries`، فكلُّ قيمةٍ هنا يجب أن تكون كودًا قائمًا في القاموس.
 */

if (!function_exists('ems_role_family')) {
    /** الدور ← عائلتُه الوظيفية (كودٌ قائمٌ في hr_dictionaries) */
    function ems_role_family($roleId)
    {
        static $m = array(
            1 => 'fam_ops', 5 => 'fam_ops', 6 => 'fam_ops', 7 => 'fam_ops',
            3 => 'fam_fleet', 10 => 'fam_fleet', 11 => 'fam_fleet',
            13 => 'fam_maintenance', 14 => 'fam_maintenance',
            17 => 'fam_finance', 18 => 'fam_finance', 19 => 'fam_finance',
            20 => 'fam_finance', 21 => 'fam_finance', 22 => 'fam_finance',
            2  => 'fam_procurement', 8 => 'fam_procurement', 16 => 'fam_procurement',
            4  => 'fam_hr', 12 => 'fam_sales', 15 => 'fam_governance', 9 => 'fam_governance',
            23 => 'fam_transport', 24 => 'fam_tickets', 25 => 'fam_warehouse',
            26 => 'fam_financing', 27 => 'fam_operators',
        );
        $r = (int) $roleId;
        return isset($m[$r]) ? $m[$r] : null;
    }
}

if (!function_exists('ems_role_level')) {
    /** الدور ← مستواه التنظيمي (كودٌ قائمٌ في hr_dictionaries) */
    function ems_role_level($roleId, $lvl = 1)
    {
        if ((int) $roleId === 9) { return 'lvl_executive'; }
        return ((int) $lvl === 2) ? 'lvl_supervisor' : 'lvl_dept_mgr';
    }
}

if (!function_exists('ems_role_title_key')) {
    /** الدور ← مفتاحُ قالب مسمّاه (tpl_kind='title') — وهو نفسُه title_code في المركز */
    function ems_role_title_key($roleId)
    {
        return 'role_' . (int) $roleId;
    }
}
