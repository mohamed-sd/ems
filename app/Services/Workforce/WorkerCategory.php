<?php
/**
 * طبقة القوى التشغيلية (EQUIP-OPE-S04) — مساعد فئات العامل.
 *
 * قرار 2: worker_category فئةٌ تشغيليةٌ على سجل الموظف (employees.worker_category)، مع تعيينٍ آليٍّ
 * أوليٍّ من employees.employee_type القائم — قابلةٌ للتعديل يدوياً.
 *
 * نمط دوالٍ خفيفٌ (كـ includes/employee_types.php) بلا أي اعتمادٍ على autoloader،
 * فلا يلمس النظام القائم ويُضمَّن بـ require_once مباشرةً.
 */

if (!function_exists('ems_worker_categories')) {
    /** الفئات التشغيلية المعتمدة (قرار 2). «مشغّل/سائق» فئةٌ واحدةٌ حفظاً لروابط الإرث. */
    function ems_worker_categories()
    {
        return ['مشغّل/سائق', 'فني', 'مهندس', 'مشرف', 'مراقب', 'عمالة مساندة'];
    }
}

if (!function_exists('ems_map_employee_type_to_category')) {
    /**
     * تعيينٌ آليٌّ أوليٌّ من employee_type القائم إلى worker_category.
     * يُستعمل كاقتراحٍ عند التصنيف فقط؛ القيمة تبقى قابلةً للتعديل.
     */
    function ems_map_employee_type_to_category($employee_type)
    {
        $t = trim((string) $employee_type);
        $map = [
            'سائق/مشغّل' => 'مشغّل/سائق',
            'مشغّل'      => 'مشغّل/سائق',
            'سائق'       => 'مشغّل/سائق',
            'فني'        => 'فني',
            'فني ورشة'   => 'فني',
            'مهندس'      => 'مهندس',
            'مشرف'       => 'مشرف',
            'مراقب'      => 'مراقب',
            'مبنشر'      => 'عمالة مساندة',
            'مساعد'      => 'عمالة مساندة',
            'إداري'      => 'عمالة مساندة',
            'أمن'        => 'مراقب',
            'أخرى'       => 'عمالة مساندة',
        ];
        if (isset($map[$t]) && $t !== '') {
            return $map[$t];
        }
        return 'مشغّل/سائق'; // الافتراض الآمن
    }
}

if (!function_exists('ems_worker_job_grades')) {
    /** سلّم الدرجة المهنية (8.1) — يُستعمل في القائمة وسجل التدرّج. */
    function ems_worker_job_grades()
    {
        return ['مساعد مشغّل', 'مشغّل', 'مشغّل أول', 'مشرف وردية', 'قائد طاقم'];
    }
}
