<?php
/**
 * أنواع الموظفين الموحّدة (الموجة 1) ومساعد فلترة أنواع التشغيل.
 *
 * يُستعمل في قوائم اختيار «السائق/المشغّل» (التايم شيت، إسناد المعدات، التشغيل)
 * كي لا يظهر موظف إداري/أمن/ورشة في قوائم تشغيل المعدات — دون كسر:
 * لو غاب العمود employee_type يُعيد المساعد سلسلة فارغة (لا فلترة).
 */

if (!function_exists('ems_employee_types')) {
    /** كل أنواع الموظفين المعتمدة. */
    function ems_employee_types()
    {
        return ['سائق/مشغّل', 'مساعد', 'فني', 'مبنشر', 'مشرف', 'إداري', 'فني ورشة', 'أمن', 'أخرى'];
    }
}

if (!function_exists('ems_operation_employee_types')) {
    /** الأنواع التي تُشغّل المعدات (تظهر في قوائم اختيار المشغّل). */
    function ems_operation_employee_types()
    {
        return ['سائق/مشغّل', 'مبنشر', 'مساعد'];
    }
}

if (!function_exists('ems_save_employee_extra')) {
    /**
     * حفظ حقول سجل الموظفين الجديدة (employee_type + بيانات عامة) للموظف $emp_id
     * عبر تحديث ثانوي مَحروس بـ db_table_has_column (لا يلمس الاستعلام الرئيسي).
     * يحدّث فقط الحقول المُرسَلة والموجودة كأعمدة. employee_type لا يُترك فارغاً.
     *
     * @param string $scope_sql قيد إضافي مثل " AND company_id = 5" أو ''.
     */
    function ems_save_employee_extra($conn, $emp_id, $scope_sql = '')
    {
        $emp_id = intval($emp_id);
        if ($emp_id <= 0) return;

        $cols = [
            'employee_type', 'birth_date', 'nationality', 'blood_type', 'whatsapp',
            'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
            'license_issue_date', 'license_grade', 'license_photo', 'medical_report_path',
        ];
        $sets = [];
        $types = '';
        $vals = [];
        foreach ($cols as $col) {
            if (!db_table_has_column($conn, 'employees', $col)) continue;
            if (!isset($_POST[$col])) continue;
            $raw = trim((string) $_POST[$col]);
            if ($col === 'employee_type' && $raw === '') $raw = 'سائق/مشغّل';
            $sets[] = "`$col` = ?";
            $types .= 's';
            $vals[] = ($raw === '') ? null : $raw;
        }
        if (empty($sets)) return;

        $sql = "UPDATE employees SET " . implode(', ', $sets) . " WHERE id = ?" . $scope_sql;
        $types .= 'i';
        $vals[] = $emp_id;
        $stmt = $conn->prepare($sql);
        if (!$stmt) return;
        $bind = [$types];
        for ($i = 0; $i < count($vals); $i++) $bind[] = &$vals[$i];
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
    }
}

if (!function_exists('ems_operation_types_in_sql')) {
    /**
     * يعيد شرط SQL ` AND <alias.>employee_type IN ('...')` لتقييد القوائم بأنواع التشغيل.
     * يعيد '' إن لم يوجد العمود (توافق رجعي — لا فلترة فتظهر الكل).
     *
     * @param mysqli $conn
     * @param string $alias اسم مستعار للجدول (مثل 'd') أو '' بلا بادئة.
     */
    function ems_operation_types_in_sql($conn, $alias = '')
    {
        if (!function_exists('db_table_has_column') || !db_table_has_column($conn, 'employees', 'employee_type')) {
            return '';
        }
        $prefix = ($alias !== '') ? ($alias . '.') : '';
        $vals = array_map(function ($t) use ($conn) {
            return "'" . mysqli_real_escape_string($conn, $t) . "'";
        }, ems_operation_employee_types());
        return " AND {$prefix}employee_type IN (" . implode(', ', $vals) . ")";
    }
}
