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
    /** بوابةُ مساعدي الموظفين — تُشتق من الجلسة (نمط contract_children_gate). */
    function ems_employee_helper_gate()
    {
        $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
        return ($role === '-1')
            ? ems_tenant_db()->forAllTenants('employee helpers super')
            : ems_tenant_db();
    }

    function ems_save_employee_extra($conn, $emp_id, $scope_sql = '')
    {
        $emp_id = intval($emp_id);
        if ($emp_id <= 0) return;

        $cols = [
            'employee_type', 'job_title_id', 'employee_role_id', 'birth_date', 'nationality', 'blood_type', 'whatsapp',
            'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
            'medical_report_path',
        ];
        // مصفوفة قيمٍ أصلية (NULL حقيقي للفارغ) — التوقيع محفوظ و$scope_sql لم يعد
        // مصدرَ العزل (البوابة تحقنه)
        $data = [];
        foreach ($cols as $col) {
            if (!db_table_has_column($conn, 'employees', $col)) continue;
            if (!isset($_POST[$col])) continue;
            $raw = trim((string) $_POST[$col]);
            if ($col === 'employee_type' && $raw === '') $raw = 'سائق/مشغّل';
            $data[$col] = ($raw === '') ? null : $raw;
        }
        if (empty($data)) return;

        try {
            ems_employee_helper_gate()->update('employees', $data, ['id' => $emp_id]);
        } catch (\Throwable $e) {
            // كالأصل: فشل الحفظ الإضافي لا يقطع حفظ الموظف الرئيس
        }
    }
}

if (!function_exists('ems_sync_equipment_operator')) {
    /**
     * يزامن سجل المشغّل (equipment_operators) من حقول الرخصة في employees بعد كل حفظ.
     * يُنشئ/يحدّث السجل فقط للموظف الذي لديه بيانات رخصة أو مسمّى وظيفيّ من نوع مشغّل.
     * بهذا يبقى equipment_operators مرآةً تشغيليةً لبيانات السائق/المشغّل دون كسر الشاشات
     * التي تقرأ employees.license_* مباشرةً.
     *
     * @param string $scope_sql قيد عزلٍ إضافي مثل " AND company_id = 5" أو ''.
     */
    function ems_sync_equipment_operator($conn, $emp_id, $scope_sql = '')
    {
        $emp_id = intval($emp_id);
        if ($emp_id <= 0) return;
        if (!db_table_has_column($conn, 'equipment_operators', 'employee_id')) return; // الطبقة غير مطبَّقة

        try {
            $gate = ems_employee_helper_gate();
            $e = $gate->selectOne('employees', [
                'columns' => ['id', 'company_id', 'employee_type', 'employee_role_id', 'license_number', 'license_type', 'license_grade', 'license_issuer',
                    'license_issue_date', 'license_expiry_date', 'license_photo', 'specialized_equipment', 'medical_report_path', 'job_title_id'],
                'where'   => ['id' => $emp_id],
            ]);
        } catch (\Throwable $t) {
            return;
        }
        if (!$e) return;

        // قاعدة الإسناد التلقائي لجدول المشغّلين: الموظف بدور «سائق/مشغّل» فقط.
        // المسمّى الوظيفي (job_titles.is_operator) هو المصدر الموثوق والحاسم متى وُجد، فلا
        // يُضاف موظفٌ بمسمّى غير تشغيليّ (مدير/مهندس/إداري…) حتى لو كان نوعه القديم
        // (employee_type) تشغيلياً أو كانت لديه رخصة. عند غياب المسمّى نستند إلى الدور ثم النوع.
        $is_operator = false;
        if (!empty($e['job_title_id'])) {
            $jr = $gate->selectOne('job_titles', ['columns' => ['is_operator'], 'where' => ['id' => intval($e['job_title_id'])]]);
            if ($jr) $is_operator = intval($jr['is_operator']) === 1;
        } elseif (!empty($e['employee_role_id'])) {
            $rr = $gate->selectOne('employee_roles', ['columns' => ['name'], 'where' => ['id' => intval($e['employee_role_id'])]]);
            if ($rr) {
                $rn = (string) $rr['name'];
                $is_operator = (mb_strpos($rn, 'سائق') !== false) || (mb_strpos($rn, 'مشغّل') !== false) || (mb_strpos($rn, 'مشغل') !== false);
            }
        } elseif (function_exists('ems_operation_employee_types')) {
            $is_operator = in_array(trim((string) ($e['employee_type'] ?? '')), ems_operation_employee_types(), true);
        }

        if (!$is_operator) {
            // ليس سائقاً/مشغّلاً: أزِل سجل المشغّل القائم — حذفٌ صلبٌ عبر deleteChild
            // (النطاق المزدوج: الشركة + الموظف الأب المملوك المتحقَّق).
            try {
                $op = $gate->selectOne('equipment_operators', ['columns' => ['id'], 'where' => ['employee_id' => $emp_id]]);
                if ($op) {
                    $gate->deleteChild('equipment_operators', intval($op['id']), 'employees', $emp_id, 'employee_id', 'operator unsync');
                }
            } catch (\Throwable $t) { /* لا سجل/غير مملوك → لا شيء */ }
            return;
        }

        // ترقية upsert (كان ON DUPLICATE KEY): employee_id مفتاحٌ فريد — قراءةٌ ثم
        // تحديث/إدراج عبر البوابة. غير السوبر: الشركة تُحقن؛ السوبر: شركة الموظف صراحةً.
        $op_data = [
            'license_number'      => $e['license_number'],
            'license_type'        => $e['license_type'],
            'license_grade'       => $e['license_grade'],
            'license_issuer'      => $e['license_issuer'],
            'license_issue_date'  => $e['license_issue_date'] ?: null,
            'license_expiry_date' => $e['license_expiry_date'] ?: null,
            'license_photo'       => $e['license_photo'],
            'operating_categories' => $e['specialized_equipment'],
            'medical_report_path' => $e['medical_report_path'],
        ];
        try {
            $existing = $gate->selectOne('equipment_operators', ['columns' => ['id'], 'where' => ['employee_id' => $emp_id]]);
            if ($existing) {
                $gate->update('equipment_operators', $op_data, ['id' => intval($existing['id'])]);
            } else {
                $op_data['employee_id'] = $emp_id;
                $op_data['status'] = 1;
                $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
                if ($role === '-1' && $e['company_id'] !== null) {
                    $op_data['company_id'] = intval($e['company_id']);
                }
                $gate->insert('equipment_operators', $op_data);
            }
        } catch (\Throwable $t) {
            // كالأصل: فشل المزامنة لا يقطع حفظ الموظف
        }
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
