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
            'employee_type', 'job_title_id', 'employee_role_id', 'birth_date', 'nationality', 'blood_type', 'whatsapp',
            'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
            'medical_report_path',
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

        $res = @mysqli_query($conn, "SELECT id, company_id, employee_type, employee_role_id, license_number, license_type, license_grade, license_issuer,
                   license_issue_date, license_expiry_date, license_photo, specialized_equipment, medical_report_path,
                   job_title_id FROM employees WHERE id = $emp_id" . $scope_sql . " LIMIT 1");
        if (!$res) return;
        $e = mysqli_fetch_assoc($res);
        if (!$e) return;

        // قاعدة الإسناد التلقائي لجدول المشغّلين: الموظف بدور «سائق/مشغّل» فقط.
        // المسمّى الوظيفي (job_titles.is_operator) هو المصدر الموثوق والحاسم متى وُجد، فلا
        // يُضاف موظفٌ بمسمّى غير تشغيليّ (مدير/مهندس/إداري…) حتى لو كان نوعه القديم
        // (employee_type) تشغيلياً أو كانت لديه رخصة. عند غياب المسمّى نستند إلى الدور ثم النوع.
        $is_operator = false;
        if (!empty($e['job_title_id'])) {
            $jq = @mysqli_query($conn, "SELECT is_operator FROM job_titles WHERE id = " . intval($e['job_title_id']) . " LIMIT 1");
            if ($jq && ($jr = mysqli_fetch_assoc($jq))) $is_operator = intval($jr['is_operator']) === 1;
        } elseif (!empty($e['employee_role_id'])) {
            $rq = @mysqli_query($conn, "SELECT name FROM employee_roles WHERE id = " . intval($e['employee_role_id']) . " LIMIT 1");
            if ($rq && ($rr = mysqli_fetch_assoc($rq))) {
                $rn = (string) $rr['name'];
                $is_operator = (mb_strpos($rn, 'سائق') !== false) || (mb_strpos($rn, 'مشغّل') !== false) || (mb_strpos($rn, 'مشغل') !== false);
            }
        } elseif (function_exists('ems_operation_employee_types')) {
            $is_operator = in_array(trim((string) ($e['employee_type'] ?? '')), ems_operation_employee_types(), true);
        }

        if (!$is_operator) {
            // ليس سائقاً/مشغّلاً (أو جرى تحويله إلى مسمّى غير تشغيليّ): أزِل أيّ سجل مشغّل
            // قائم له حتى لا يبقى عالقاً في جدول المشغّلين، ثم لا تُنشئ سجلاً جديداً.
            if ($ds = $conn->prepare("DELETE FROM equipment_operators WHERE employee_id = ?")) {
                $ds->bind_param('i', $emp_id);
                $ds->execute();
                $ds->close();
            }
            return;
        }

        $sql = "INSERT INTO equipment_operators
                  (company_id, employee_id, license_number, license_type, license_grade, license_issuer,
                   license_issue_date, license_expiry_date, license_photo, operating_categories, medical_report_path, status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,1)
                ON DUPLICATE KEY UPDATE
                  license_number=VALUES(license_number), license_type=VALUES(license_type), license_grade=VALUES(license_grade),
                  license_issuer=VALUES(license_issuer), license_issue_date=VALUES(license_issue_date),
                  license_expiry_date=VALUES(license_expiry_date), license_photo=VALUES(license_photo),
                  operating_categories=VALUES(operating_categories), medical_report_path=VALUES(medical_report_path)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return;
        $cid    = ($e['company_id'] !== null) ? intval($e['company_id']) : null;
        $lnum   = $e['license_number'];
        $ltype  = $e['license_type'];
        $lgrade = $e['license_grade'];
        $liss   = $e['license_issuer'];
        $lid    = $e['license_issue_date'] ?: null;
        $lexp   = $e['license_expiry_date'] ?: null;
        $lphoto = $e['license_photo'];
        $opcat  = $e['specialized_equipment'];
        $med    = $e['medical_report_path'];
        $stmt->bind_param('iisssssssss', $cid, $emp_id, $lnum, $ltype, $lgrade, $liss, $lid, $lexp, $lphoto, $opcat, $med);
        $stmt->execute();
        $stmt->close();
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
