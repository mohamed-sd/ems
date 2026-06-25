<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/equipment_log_helper.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$current_user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

// الأعمدة الاختيارية
$project_client_column = db_table_has_column($conn, 'project', 'client_id') ? 'client_id' : 'company_client_id';
$project_has_company_id = db_table_has_column($conn, 'project', 'company_id');
$operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
$operations_has_shift_type = db_table_has_column($conn, 'operations', 'shift_type');
$equipment_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');
$equipment_drivers_has_shift_type = db_table_has_column($conn, 'equipment_drivers', 'shift_type');
$drivers_has_company = db_table_has_column($conn, 'employees', 'company_id');
$drivers_has_status = db_table_has_column($conn, 'employees', 'status');
$drivers_has_project_id = db_table_has_column($conn, 'employees', 'project_id');
$equipments_has_lat = db_table_has_column($conn, 'equipments', 'latitude');
$equipments_has_lng = db_table_has_column($conn, 'equipments', 'longitude');
$equipments_has_company = db_table_has_column($conn, 'equipments', 'company_id');
$equipments_has_availability_status = db_table_has_column($conn, 'equipments', 'availability_status');
$equipments_has_availability_state = db_table_has_column($conn, 'equipments', 'availability_state');
$project_has_lat = db_table_has_column($conn, 'project', 'latitude');
$project_has_lng = db_table_has_column($conn, 'project', 'longitude');

if (!$operations_has_shift_type) {
    @mysqli_query($conn, "ALTER TABLE operations ADD COLUMN shift_type ENUM('D','N','B') NOT NULL DEFAULT 'B' AFTER shift_hours");
    $operations_has_shift_type = db_table_has_column($conn, 'operations', 'shift_type');
}
if (!$equipment_drivers_has_shift_type) {
    @mysqli_query($conn, "ALTER TABLE equipment_drivers ADD COLUMN shift_type ENUM('D','N','B') NOT NULL DEFAULT 'B' AFTER end_date");
    $equipment_drivers_has_shift_type = db_table_has_column($conn, 'equipment_drivers', 'shift_type');
}
// عمود حفظ الدور السابق (أساسي/احتياطي) قبل تحويل الفئة إلى «متعطل» — لاستعادته بعد إصلاح الصيانة.
$operations_has_prev_category = db_table_has_column($conn, 'operations', 'prev_equipment_category');
if (!$operations_has_prev_category) {
    @mysqli_query($conn, "ALTER TABLE operations ADD COLUMN prev_equipment_category VARCHAR(20) NULL DEFAULT NULL AFTER equipment_category");
    $operations_has_prev_category = db_table_has_column($conn, 'operations', 'prev_equipment_category');
}
// عمود الحالة التشغيلية (تعمل/جاهزة/معطلة) — يُدار من صفحة الحركة فقط، منفصل عن الدور (أساسي/احتياطي).
$operations_has_op_state = db_table_has_column($conn, 'operations', 'op_state');
if (!$operations_has_op_state) {
    @mysqli_query($conn, "ALTER TABLE operations ADD COLUMN op_state ENUM('تعمل','جاهزة','معطلة') NOT NULL DEFAULT 'جاهزة' AFTER status");
    $operations_has_op_state = db_table_has_column($conn, 'operations', 'op_state');
}

// الصلاحيات
$ops_perm = check_page_permissions($conn, 'movement/move_oprators.php');
$drv_perm = check_page_permissions($conn, 'movement/project_drivers.php');
$can_view = (!empty($ops_perm['can_view']) || !empty($drv_perm['can_view']));
$can_add = (!empty($ops_perm['can_add']) || !empty($drv_perm['can_add']));
$can_edit = (!empty($ops_perm['can_edit']) || !empty($drv_perm['can_edit']));

if (!$can_view) {
    header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+الشاشة+الموحدة+❌");
    exit();
}

// نطاق المشروع
$project_scope_sql = "1=1";
if (!$is_super_admin) {
    if ($project_has_company_id) {
        $project_scope_sql = "project.company_id = $company_id";
    } else {
        $project_scope_sql = "(
            EXISTS (SELECT 1 FROM users su WHERE su.id = project.created_by AND su.company_id = $company_id)
            OR EXISTS (
                SELECT 1
                FROM clients sc
                INNER JOIN users scu ON scu.id = sc.created_by
                WHERE sc.id = project.$project_client_column AND scu.company_id = $company_id
            )
        )";
    }
}

// اختيار المشروع
$session_user_project_id = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
$selected_project_id = 0;
if (isset($_GET['project_id']) && intval($_GET['project_id']) > 0) {
    $selected_project_id = intval($_GET['project_id']);
    $_SESSION['operations_project_id'] = $selected_project_id;
} elseif (isset($_SESSION['operations_project_id']) && intval($_SESSION['operations_project_id']) > 0) {
    $selected_project_id = intval($_SESSION['operations_project_id']);
} elseif ($session_user_project_id > 0) {
    $selected_project_id = $session_user_project_id;
    $_SESSION['operations_project_id'] = $selected_project_id;
}

if ($selected_project_id <= 0) {
    echo "<script>alert('❌ لا يوجد مشروع مرتبط بالمستخدم'); window.location.href='../main/dashboard.php';</script>";
    exit();
}

$project_lat_select = $project_has_lat ? 'project.latitude AS latitude' : 'NULL AS latitude';
$project_lng_select = $project_has_lng ? 'project.longitude AS longitude' : 'NULL AS longitude';
$project_query = "SELECT id, name, project_code, $project_lat_select, $project_lng_select FROM project WHERE id = $selected_project_id AND status = 1 AND $project_scope_sql";
$project_result = mysqli_query($conn, $project_query);
if (!$project_result || mysqli_num_rows($project_result) === 0) {
    unset($_SESSION['operations_project_id']);
    echo "<script>alert('❌ المشروع غير متاح'); window.location.href='../main/dashboard.php';</script>";
    exit();
}
$selected_project = mysqli_fetch_assoc($project_result);

// نطاق الشركة
$operations_company_scope = (!$is_super_admin && $operations_has_company) ? " AND o.company_id = $company_id" : "";
$operations_company_scope_inline = (!$is_super_admin && $operations_has_company) ? " AND company_id = $company_id" : "";
$ed_company_scope = (!$is_super_admin && $equipment_drivers_has_company) ? " AND ed.company_id = $company_id" : "";
$ed_company_scope_inline = (!$is_super_admin && $equipment_drivers_has_company) ? " AND company_id = $company_id" : "";
$driver_company_scope = (!$is_super_admin && $drivers_has_company) ? " AND d.company_id = $company_id" : "";
$driver_status_scope = $drivers_has_status ? " AND d.status = 1" : "";

$msg = '';
$is_success = true;

// معالجة الـ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$can_edit) {
        $msg = 'لا توجد صلاحية للتعديل ❌';
        $is_success = false;
    } else {
        $action = trim((string)$_POST['action']);
        $allowed_shift = array('D', 'N', 'B');

        // حفظ فردي لتشغيل واحد
        if ($action === 'save_single_operation') {
            try {
                $op_id = intval($_POST['op_id'] ?? 0);
                if ($op_id <= 0) {
                    throw new Exception('معرّف التشغيل غير صحيح');
                }

                // اقرأ القيم الحالية قبل التحديث (لتسجيل التغيّر الفعلي ومعرفة المعدة).
                $old_shift = null; $old_status = null; $log_equipment = 0;
                if ($pre = mysqli_query($conn, "SELECT equipment, shift_type, status FROM operations WHERE id = $op_id AND project_id = $selected_project_id$operations_company_scope_inline LIMIT 1")) {
                    if ($pre_row = mysqli_fetch_assoc($pre)) {
                        $log_equipment = intval($pre_row['equipment']);
                        $old_shift     = (string)$pre_row['shift_type'];
                        $old_status    = intval($pre_row['status']);
                    }
                }

                // الدور (أساسي/احتياطي) لا يُكتب من هذه الشاشة إطلاقاً — يُدار من شاشة التشغيل فقط.
                // الحالة التشغيلية (op_state) لها معالج مستقل (set_op_state). هنا نحفظ الوردية والتواريخ فقط.
                $shift_type = isset($_POST['shift_type']) ? trim((string)$_POST['shift_type']) : 'B';
                if (!in_array($shift_type, $allowed_shift, true)) {
                    $shift_type = 'B';
                }

                $status = intval($_POST['status'] ?? 0);
                $status = ($status === 0) ? 0 : 1;

                $start = isset($_POST['start']) ? trim((string)$_POST['start']) : '';
                $end = isset($_POST['end']) ? trim((string)$_POST['end']) : '';

                if ($start !== '') {
                    $start_obj = DateTime::createFromFormat('Y-m-d', $start);
                    if (!$start_obj || $start_obj->format('Y-m-d') !== $start) {
                        throw new Exception('صيغة تاريخ البداية غير صحيحة');
                    }
                }
                if ($end !== '') {
                    $end_obj = DateTime::createFromFormat('Y-m-d', $end);
                    if (!$end_obj || $end_obj->format('Y-m-d') !== $end) {
                        throw new Exception('صيغة تاريخ النهاية غير صحيحة');
                    }
                }
                if ($start !== '' && $end !== '' && strtotime($end) < strtotime($start)) {
                    throw new Exception('تاريخ النهاية يجب أن يكون بعد البداية');
                }

                $shift_sql = mysqli_real_escape_string($conn, $shift_type);

                $set_parts = [
                    "shift_type = '$shift_sql'",
                    "status = $status",
                ];
                if ($start !== '') {
                    $set_parts[] = "start = '" . mysqli_real_escape_string($conn, $start) . "'";
                }
                if ($end !== '') {
                    $set_parts[] = "end = '" . mysqli_real_escape_string($conn, $end) . "'";
                }
                $set_clause = implode(', ', $set_parts);

                $update_sql = "UPDATE operations
                               SET $set_clause
                               WHERE id = $op_id AND project_id = $selected_project_id$operations_company_scope_inline";
                if (!mysqli_query($conn, $update_sql)) {
                    throw new Exception('خطأ في تحديث التشغيل');
                }

                // تسجيل التحركات: تغيير وردية و/أو إنهاء تشغيل — عند التغيّر الفعلي فقط.
                if ($log_equipment > 0) {
                    $log_base = ['operation_id' => $op_id];
                    if ($company_id > 0) { $log_base['company_id'] = $company_id; }
                    if ($old_shift !== null && $old_shift !== $shift_type) {
                        $ems_shift_label = function ($s) { return $s === 'D' ? 'نهاري' : ($s === 'N' ? 'ليلي' : 'نهاري + ليلي'); };
                        log_equipment_event($conn, $log_equipment, 'تغيير وردية',
                            array_merge($log_base, ['from' => $ems_shift_label($old_shift), 'to' => $ems_shift_label($shift_type)]));
                    }
                    if ($old_status === 1 && $status === 0) {
                        log_equipment_event($conn, $log_equipment, 'إنهاء تشغيل', $log_base);
                    }
                }

                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => 'تم حفظ التشغيل ✅']);
                    exit;
                }
                $msg = 'تم حفظ التشغيل ✅';
                $is_success = true;
            } catch (Exception $ex) {
                $msg = $ex->getMessage() . ' ❌';
                $is_success = false;
                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
            }
        }

        // حفظ فردي لسائق واحد
        elseif ($action === 'save_single_driver') {
            try {
                $rel_id = intval($_POST['rel_id'] ?? 0);
                if ($rel_id <= 0) {
                    throw new Exception('معرّف السائق غير صحيح');
                }

                $shift_type = isset($_POST['shift_type']) ? trim((string)$_POST['shift_type']) : 'B';
                if (!in_array($shift_type, $allowed_shift, true)) {
                    $shift_type = 'B';
                }

                $status = intval($_POST['status'] ?? 0);
                $status = ($status === 0) ? 0 : 1;

                $start_date = isset($_POST['start_date']) ? trim((string)$_POST['start_date']) : '';
                $end_date = isset($_POST['end_date']) ? trim((string)$_POST['end_date']) : '';

                if ($start_date !== '') {
                    $start_obj = DateTime::createFromFormat('Y-m-d', $start_date);
                    if (!$start_obj || $start_obj->format('Y-m-d') !== $start_date) {
                        throw new Exception('صيغة تاريخ بداية السائق غير صحيحة');
                    }
                }
                if ($end_date !== '') {
                    $end_obj = DateTime::createFromFormat('Y-m-d', $end_date);
                    if (!$end_obj || $end_obj->format('Y-m-d') !== $end_date) {
                        throw new Exception('صيغة تاريخ نهاية السائق غير صحيحة');
                    }
                }
                if ($start_date !== '' && $end_date !== '' && strtotime($end_date) < strtotime($start_date)) {
                    throw new Exception('تاريخ النهاية يجب أن يكون بعد البداية');
                }

                $shift_sql = mysqli_real_escape_string($conn, $shift_type);
                $start_save = $start_date !== '' ? mysqli_real_escape_string($conn, $start_date) : date('Y-m-d');
                $end_save = $end_date !== '' ? mysqli_real_escape_string($conn, $end_date) : '2099-12-31';

                $update_shift_col = $equipment_drivers_has_shift_type ? ", shift_type = '$shift_sql'" : "";
                $driver_update_sql = "UPDATE equipment_drivers
                                      SET start_date = '$start_save',
                                          end_date = '$end_save',
                                          status = $status
                                          $update_shift_col
                                      WHERE id = $rel_id$ed_company_scope_inline";
                if (!mysqli_query($conn, $driver_update_sql)) {
                    throw new Exception('خطأ في تحديث السائق');
                }

                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => 'تم حفظ السائق ✅']);
                    exit;
                }
                $msg = 'تم حفظ السائق ✅';
                $is_success = true;
            } catch (Exception $ex) {
                $msg = $ex->getMessage() . ' ❌';
                $is_success = false;
                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
            }
        }

        // إضافة تشغيل جديد
        elseif ($action === 'add_new_operation') {
            try {
                $equipment = intval($_POST['equipment'] ?? 0);
                $contract_id = intval($_POST['contract_id'] ?? 0);
                $supplier_id = intval($_POST['supplier_id'] ?? 0);
                $equipment_type = intval($_POST['equipment_type'] ?? 0);

                if ($equipment <= 0 || $contract_id <= 0 || $supplier_id <= 0 || $equipment_type <= 0) {
                    throw new Exception('بيانات ناقصة: تأكد من اختيار المعدة والعقد والمورد والنوع');
                }

                $equipment_category = isset($_POST['equipment_category']) ? trim((string)$_POST['equipment_category']) : 'أساسي';
                if ($equipment_category !== 'أساسي' && $equipment_category !== 'احتياطي' && $equipment_category !== 'متعطل') {
                    $equipment_category = 'أساسي';
                }

                $shift_type = isset($_POST['shift_type']) ? trim((string)$_POST['shift_type']) : 'B';
                if (!in_array($shift_type, $allowed_shift, true)) {
                    $shift_type = 'B';
                }

                $start = isset($_POST['start']) ? trim((string)$_POST['start']) : date('Y-m-d');
                $end = isset($_POST['end']) ? trim((string)$_POST['end']) : '';

                if ($start !== '') {
                    $start_obj = DateTime::createFromFormat('Y-m-d', $start);
                    if (!$start_obj || $start_obj->format('Y-m-d') !== $start) {
                        throw new Exception('صيغة تاريخ البداية غير صحيحة');
                    }
                }
                if ($end !== '') {
                    $end_obj = DateTime::createFromFormat('Y-m-d', $end);
                    if (!$end_obj || $end_obj->format('Y-m-d') !== $end) {
                        throw new Exception('صيغة تاريخ النهاية غير صحيحة');
                    }
                }
                if ($start !== '' && $end !== '' && strtotime($end) < strtotime($start)) {
                    throw new Exception('تاريخ النهاية يجب أن يكون بعد البداية');
                }

                // التحقق من عدم وجود تشغيل ساري للمعدة نفسها
                $conflict_check = mysqli_query($conn, "SELECT id FROM operations WHERE equipment = $equipment AND status = 1 LIMIT 1");
                if ($conflict_check && mysqli_num_rows($conflict_check) > 0) {
                    throw new Exception('المعدة تعمل بالفعل في تشغيل ساري آخر');
                }

                $total_equipment_hours = floatval($_POST['total_equipment_hours'] ?? 0);
                $shift_hours = floatval($_POST['shift_hours'] ?? 0);
                $status = intval($_POST['status'] ?? 1);
                $status = ($status === 0) ? 0 : 1;

                $category_sql = mysqli_real_escape_string($conn, $equipment_category);
                $shift_sql = mysqli_real_escape_string($conn, $shift_type);
                $start_sql = mysqli_real_escape_string($conn, $start);
                $end_sql = mysqli_real_escape_string($conn, $end);

                $insert_company_col = (!$is_super_admin && $operations_has_company) ? ", company_id" : "";
                $insert_company_val = (!$is_super_admin && $operations_has_company) ? ", $company_id" : "";

                $insert_sql = "INSERT INTO operations (equipment, equipment_type, equipment_category, project_id, contract_id, supplier_id, start, end, days, total_equipment_hours, shift_hours, shift_type, status$insert_company_col)
                               VALUES ($equipment, $equipment_type, '$category_sql', $selected_project_id, $contract_id, $supplier_id, '$start_sql', '$end_sql', 0, $total_equipment_hours, $shift_hours, '$shift_sql', $status$insert_company_val)";
                if (!mysqli_query($conn, $insert_sql)) {
                    throw new Exception('خطأ في إضافة التشغيل الجديد');
                }

                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => 'تم إضافة التشغيل ✅']);
                    exit;
                }
                $msg = 'تم إضافة التشغيل الجديد ✅';
                $is_success = true;
            } catch (Exception $ex) {
                $msg = $ex->getMessage() . ' ❌';
                $is_success = false;
                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
            }
        }

        // إضافة سائق جديد
        elseif ($action === 'add_new_driver') {
            try {
                $employee_id = intval($_POST['employee_id'] ?? 0);
                $equipment_id = intval($_POST['equipment_id'] ?? 0);

                if ($employee_id <= 0 || $equipment_id <= 0) {
                    throw new Exception('بيانات ناقصة: اختر السائق والمعدة');
                }

                $shift_type = isset($_POST['shift_type']) ? trim((string)$_POST['shift_type']) : 'B';
                if (!in_array($shift_type, $allowed_shift, true)) {
                    $shift_type = 'B';
                }

                $start_date = isset($_POST['start_date']) ? trim((string)$_POST['start_date']) : date('Y-m-d');
                $end_date = isset($_POST['end_date']) ? trim((string)$_POST['end_date']) : '2099-12-31';

                // التحقق من وجود معدة في التشغيلات النشطة
                $eq_check = mysqli_query($conn, "SELECT 1 FROM operations WHERE equipment = $equipment_id AND project_id = $selected_project_id AND status = 1 LIMIT 1");
                if (!$eq_check || mysqli_num_rows($eq_check) === 0) {
                    throw new Exception('المعدة المختارة غير مشغّلة في مشروع ساري');
                }

                $shift_sql = mysqli_real_escape_string($conn, $shift_type);
                $start_save = mysqli_real_escape_string($conn, $start_date);
                $end_save = mysqli_real_escape_string($conn, $end_date);

                // إنهاء التعيينات السابقة النشطة للسائق
                mysqli_query($conn, "UPDATE equipment_drivers SET status = 0, end_date = '$start_save' WHERE employee_id = $employee_id AND status = 1$ed_company_scope_inline");

                $insert_company_col = (!$is_super_admin && $equipment_drivers_has_company) ? ", company_id" : "";
                $insert_company_val = (!$is_super_admin && $equipment_drivers_has_company) ? ", $company_id" : "";
                $insert_shift_col = $equipment_drivers_has_shift_type ? ", shift_type" : "";
                $insert_shift_val = $equipment_drivers_has_shift_type ? ", '$shift_sql'" : "";

                $insert_driver_sql = "INSERT INTO equipment_drivers (equipment_id, employee_id, start_date, end_date, status$insert_shift_col$insert_company_col)
                                      VALUES ($equipment_id, $employee_id, '$start_save', '$end_save', 1$insert_shift_val$insert_company_val)";
                if (!mysqli_query($conn, $insert_driver_sql)) {
                    throw new Exception('خطأ في إضافة السائق');
                }

                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => 'تم إضافة السائق ✅']);
                    exit;
                }
                $msg = 'تم إضافة السائق الجديد ✅';
                $is_success = true;
            } catch (Exception $ex) {
                $msg = $ex->getMessage() . ' ❌';
                $is_success = false;
                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
            }
        }

        // تحويل المعدة إلى وضع الصيانة (مدير الحركة أول من يعلم بالعطل من السائق)
        // ملاحظة: الحالة الفعلية للمعدة تُدار عبر availability_status / availability_state،
        // وليس عبر العمود العام status (الذي هو علم «صف نشط» = 1). لا نمسّ operations.status
        // ولا equipments.status — تغيير الصحة الفنية للتشغيل من اختصاص أمر الصيانة نفسه.
        elseif ($action === 'set_equipment_maintenance') {
            try {
                $equipment_id = intval($_POST['equipment_id'] ?? 0);
                if ($equipment_id <= 0) {
                    throw new Exception('معرّف المعدة غير صحيح');
                }
                if (!$equipments_has_availability_status) {
                    throw new Exception('عمود حالة التوفر غير متاح في جدول المعدات');
                }

                // التأكد أن المعدة مرتبطة بتشغيل في هذا المشروع (وضمن نطاق الشركة)
                $check_sql = "SELECT 1 FROM operations
                              WHERE equipment = $equipment_id
                                AND project_id = $selected_project_id
                                $operations_company_scope_inline
                              LIMIT 1";
                $check_res = mysqli_query($conn, $check_sql);
                if (!$check_res || mysqli_num_rows($check_res) === 0) {
                    throw new Exception('المعدة غير مرتبطة بهذا المشروع');
                }

                $maint_sets = ["availability_status = 'تحت الصيانة'"];
                if ($equipments_has_availability_state) {
                    $maint_sets[] = "availability_state = 'غير متوفرة'";
                }
                $equip_company_scope = (!$is_super_admin && $equipments_has_company)
                    ? " AND (company_id = $company_id OR company_id IS NULL)"
                    : "";

                $maint_sql = "UPDATE equipments
                                 SET " . implode(', ', $maint_sets) . "
                               WHERE id = $equipment_id$equip_company_scope
                               LIMIT 1";
                if (!mysqli_query($conn, $maint_sql)) {
                    throw new Exception('خطأ في تحويل المعدة إلى وضع الصيانة');
                }

                // تحويل الحالة التشغيلية للتشغيلات السارية لهذه المعدة إلى «معطلة» + فتح أمر صيانة تلقائي (ذرّيًا).
                // الدور (أساسي/احتياطي) لا يُمسّ — الحالة منفصلة عن الدور.
                require_once __DIR__ . '/../Maintenance/mnt_helpers.php';
                $maint_state_changed = false;
                mysqli_begin_transaction($conn);
                try {
                    if (!empty($operations_has_op_state)) {
                        if (!mysqli_query($conn, "UPDATE operations SET op_state = 'معطلة'
                                                  WHERE equipment = $equipment_id
                                                    AND project_id = $selected_project_id
                                                    AND status = 1
                                                    $operations_company_scope_inline")) {
                            throw new Exception('خطأ في تحديث حالة التشغيل');
                        }
                        $maint_state_changed = (mysqli_affected_rows($conn) > 0);
                    }

                    // منع التكرار: لا نفتح أمرًا تلقائيًا ثانيًا لمعدة لها أمر تلقائي مفتوح بالفعل.
                    if ($company_id > 0) {
                        $dup = mysqli_query($conn, "SELECT 1 FROM mnt_order
                            WHERE equipment_id = $equipment_id AND project_id = $selected_project_id AND company_id = $company_id
                              AND is_auto = 1 AND state IN ('بلاغ','تنفيذ','فحص') AND COALESCE(is_deleted,0)=0 LIMIT 1");
                        if (!$dup || mysqli_num_rows($dup) === 0) {
                            $auto_code  = mnt_next_code($conn, 'mnt_order', 'MNT', $company_id);
                            $auto_src   = 'بلاغ';   // المصدر الدلالي
                            $auto_state = 'بلاغ';   // الحالة الابتدائية (مفتوح)
                            if ($ins = mysqli_prepare($conn,
                                "INSERT INTO mnt_order (company_id, code, equipment_id, project_id, source, is_auto, state, created_by)
                                 VALUES (?, ?, ?, ?, ?, 1, ?, ?)")) {
                                mysqli_stmt_bind_param($ins, 'isiissi',
                                    $company_id, $auto_code, $equipment_id, $selected_project_id, $auto_src, $auto_state, $current_user_id);
                                mysqli_stmt_execute($ins);
                                mysqli_stmt_close($ins);
                            }
                        }
                    }

                    // سجل «إسناد للصيانة» — فقط إن تغيّرت الحالة فعليًا إلى «معطلة».
                    if ($maint_state_changed) {
                        $log_opts = ['to' => 'معطلة', 'project_id' => $selected_project_id];
                        if ($company_id > 0) { $log_opts['company_id'] = $company_id; }
                        log_equipment_event($conn, $equipment_id, 'إسناد للصيانة', $log_opts);
                    }
                    mysqli_commit($conn);
                } catch (Exception $txEx) {
                    mysqli_rollback($conn);
                    throw $txEx;
                }

                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => 'تم تحويل المعدة إلى وضع الصيانة ✅']);
                    exit;
                }
                $msg = 'تم تحويل المعدة إلى وضع الصيانة ✅';
                $is_success = true;
            } catch (Exception $ex) {
                $msg = $ex->getMessage() . ' ❌';
                $is_success = false;
                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
            }
        }

        // تبديل الحالة التشغيلية يدويًا: تعمل ⇄ جاهزة (يُمنع ضبط «معطلة» من هنا — لها مسار الصيانة).
        elseif ($action === 'set_op_state') {
            try {
                $op_id = intval($_POST['op_id'] ?? 0);
                if ($op_id <= 0) {
                    throw new Exception('معرّف التشغيل غير صحيح');
                }
                $new_state = (isset($_POST['op_state']) && in_array($_POST['op_state'], ['تعمل', 'جاهزة'], true))
                    ? $_POST['op_state'] : null;
                if ($new_state === null) {
                    throw new Exception('حالة غير صالحة (يُسمح بـ «تعمل» أو «جاهزة» فقط)');
                }
                if (empty($operations_has_op_state)) {
                    throw new Exception('عمود الحالة غير متاح');
                }

                // اقرأ الحالة الحالية والمعدة قبل التحديث (لتسجيل التغيّر الفعلي).
                $old_op_state = null; $sos_equipment = 0;
                if ($sos_pre = mysqli_query($conn, "SELECT equipment, op_state FROM operations WHERE id = $op_id AND project_id = $selected_project_id$operations_company_scope_inline LIMIT 1")) {
                    if ($sos_row = mysqli_fetch_assoc($sos_pre)) {
                        $sos_equipment = intval($sos_row['equipment']);
                        $old_op_state  = (string)$sos_row['op_state'];
                    }
                }

                $scope_company = (!$is_super_admin && $operations_has_company);
                $sql = "UPDATE operations SET op_state = ? WHERE id = ? AND project_id = ? AND status = 1"
                     . ($scope_company ? " AND company_id = ?" : "");
                if (!($stmt = mysqli_prepare($conn, $sql))) {
                    throw new Exception('تعذّر تجهيز الاستعلام');
                }
                if ($scope_company) {
                    mysqli_stmt_bind_param($stmt, 'siii', $new_state, $op_id, $selected_project_id, $company_id);
                } else {
                    mysqli_stmt_bind_param($stmt, 'sii', $new_state, $op_id, $selected_project_id);
                }
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                // سجل «تغيير حالة» عند التغيّر الفعلي فقط.
                if ($sos_equipment > 0 && $old_op_state !== null && $old_op_state !== $new_state) {
                    $log_opts = ['from' => $old_op_state, 'to' => $new_state, 'operation_id' => $op_id];
                    if ($company_id > 0) { $log_opts['company_id'] = $company_id; }
                    log_equipment_event($conn, $sos_equipment, 'تغيير حالة', $log_opts);
                }

                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => 'تم تحديث الحالة ✅']);
                    exit;
                }
                $msg = 'تم تحديث الحالة ✅';
                $is_success = true;
            } catch (Exception $ex) {
                $msg = $ex->getMessage() . ' ❌';
                $is_success = false;
                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
            }
        }

        // ملاحظة: لا يوجد إرجاع يدوي من الصيانة من شاشة الحركة — المعدة تعود «جاهزة» تلقائياً
        // عند إغلاق/إلغاء أمر الصيانة (mnt_return_equipment_available في Maintenance/mnt_helpers.php).
    }
}

// جلب البيانات
$equip_lat_select = $equipments_has_lat ? 'e.latitude AS latitude' : 'NULL AS latitude';
$equip_lng_select = $equipments_has_lng ? 'e.longitude AS longitude' : 'NULL AS longitude';
$op_state_select  = $operations_has_op_state ? 'o.op_state' : "'جاهزة' AS op_state";

$operations_sql = "SELECT o.id, o.equipment, o.equipment_category, o.start, o.end, o.shift_type, o.status,
                          $op_state_select,
                          o.total_equipment_hours, o.shift_hours,
                          e.code AS equipment_code, e.name AS equipment_name,
                          e.availability_status AS equipment_availability_status,
                          et.type AS equipment_type_name,
                          s.name AS supplier_name,
                          $equip_lat_select,
                          $equip_lng_select
                   FROM operations o
                   LEFT JOIN equipments e ON e.id = o.equipment
                   LEFT JOIN equipments_types et ON et.id = e.type
                   LEFT JOIN suppliers s ON s.id = o.supplier_id
                   WHERE o.project_id = $selected_project_id
                     $operations_company_scope
                   ORDER BY o.status DESC, o.id DESC";
$operations_res = mysqli_query($conn, $operations_sql);
$operations_rows = [];
if ($operations_res) {
    while ($r = mysqli_fetch_assoc($operations_res)) {
        $operations_rows[] = $r;
    }
}

$operations_rows_day = [];
$operations_rows_night = [];
foreach ($operations_rows as $op_row) {
    $shift_code = isset($op_row['shift_type']) ? strtoupper((string)$op_row['shift_type']) : 'B';
    if ($shift_code === 'D' || $shift_code === 'B') {
        $operations_rows_day[] = $op_row;
    }
    if ($shift_code === 'N' || $shift_code === 'B') {
        $operations_rows_night[] = $op_row;
    }
}

$drivers_sql = "SELECT ed.id, ed.equipment_id, ed.employee_id, ed.start_date, ed.end_date, ed.status,
                       " . ($equipment_drivers_has_shift_type ? "ed.shift_type" : "'B' AS shift_type") . ",
                       d.name AS driver_name, d.phone AS driver_phone,
                       e.code AS equipment_code, e.name AS equipment_name
                FROM equipment_drivers ed
                INNER JOIN employees d ON d.id = ed.employee_id
                INNER JOIN equipments e ON e.id = ed.equipment_id
                WHERE EXISTS (
                    SELECT 1 FROM operations o
                    WHERE o.equipment = ed.equipment_id
                      AND o.project_id = $selected_project_id
                      $operations_company_scope
                )
                $ed_company_scope
                ORDER BY ed.status DESC, ed.id DESC";
$drivers_res = mysqli_query($conn, $drivers_sql);
$drivers_rows = [];
if ($drivers_res) {
    while ($d = mysqli_fetch_assoc($drivers_res)) {
        $drivers_rows[] = $d;
    }
}

// تجميع السائقين حسب equipment_id للعرض المدمج داخل جداول التشغيل
$drivers_by_equipment = [];
foreach ($drivers_rows as $drv_item) {
    $eid = intval($drv_item['equipment_id']);
    if (!isset($drivers_by_equipment[$eid])) {
        $drivers_by_equipment[$eid] = [];
    }
    $drivers_by_equipment[$eid][] = $drv_item;
}

// عدّاد السائقين الفعّالين (status=1) لكل آلية — يُشتق من نفس قائمة السائقين المعروضة في الجدول الفرعي
// (المقيّدة بالشركة ووجود الموظف عبر JOIN employees)، فلا يتضخّم العدّاد عن المعروض كما كان يحدث
// حين كان يُحسب مباشرة من equipment_drivers دون تلك الفلاتر (يظهر «3» بلا سائقين معروضين).
$active_drivers_count_by_eq = [];
$ems_seen_active_driver = [];
foreach ($drivers_rows as $drv_item) {
    if (intval($drv_item['status']) !== 1) {
        continue;
    }
    $eid = intval($drv_item['equipment_id']);
    $did = intval($drv_item['employee_id']);
    $seen_key = $eid . ':' . $did;
    if (isset($ems_seen_active_driver[$seen_key])) {
        continue;
    }
    $ems_seen_active_driver[$seen_key] = true;
    $active_drivers_count_by_eq[$eid] = ($active_drivers_count_by_eq[$eid] ?? 0) + 1;
}

// جلب جميع السائقين والمعدات (فقط المرتبطين بالمشروع المحدد)
$driver_project_scope = "";
if ($drivers_has_project_id) {
    $driver_project_scope = " AND (d.project_id = $selected_project_id OR d.project_id IS NULL)";
}

$all_drivers_sql = "SELECT DISTINCT d.id, d.name, d.phone
                    FROM employees d
                    WHERE 1=1
                      $driver_company_scope
                      $driver_status_scope
                      $driver_project_scope" . ems_operation_types_in_sql($conn, 'd') . "
                    ORDER BY d.name ASC";
$all_drivers_res = mysqli_query($conn, $all_drivers_sql);
$all_drivers = [];
if ($all_drivers_res) {
    while ($drv = mysqli_fetch_assoc($all_drivers_res)) {
        $all_drivers[] = $drv;
    }
}

// السائقون الذين لديهم تعيين ساري حالياً — يُستثنون من قوائم الإضافة
$active_driver_ids = [];
$active_drv_res = mysqli_query($conn, "SELECT DISTINCT employee_id FROM equipment_drivers WHERE status = 1$ed_company_scope_inline");
if ($active_drv_res) {
    while ($adr = mysqli_fetch_assoc($active_drv_res)) {
        $active_driver_ids[] = intval($adr['employee_id']);
    }
}

$all_equipment_sql = "SELECT DISTINCT e.id, e.code, e.name
                      FROM equipments e
                      WHERE EXISTS (
                        SELECT 1 FROM operations o
                        WHERE o.equipment = e.id
                          AND o.project_id = $selected_project_id
                          $operations_company_scope_inline
                      )
                      ORDER BY e.code ASC";
$all_equipment_res = mysqli_query($conn, $all_equipment_sql);
$all_equipment = [];
if ($all_equipment_res) {
    while ($eq = mysqli_fetch_assoc($all_equipment_res)) {
        $all_equipment[] = $eq;
    }
}

// جلب العقود والموردين
$all_contracts_sql = "SELECT DISTINCT c.id, c.contract_signing_date, c.id as contract_id
                      FROM contracts c
                      WHERE c.project_id = $selected_project_id
                        AND c.status = 1
                      ORDER BY c.contract_signing_date DESC";
$all_contracts_res = mysqli_query($conn, $all_contracts_sql);
$all_contracts = [];
if ($all_contracts_res) {
    while ($ct = mysqli_fetch_assoc($all_contracts_res)) {
        $all_contracts[] = $ct;
    }
}

$all_suppliers_sql = "SELECT DISTINCT s.id, s.name
                      FROM suppliers s
                      WHERE EXISTS (
                        SELECT 1 FROM operations o
                        WHERE o.supplier_id = s.id
                          AND o.project_id = $selected_project_id
                      )
                      ORDER BY s.name ASC";
$all_suppliers_res = mysqli_query($conn, $all_suppliers_sql);
$all_suppliers = [];
if ($all_suppliers_res) {
    while ($sup = mysqli_fetch_assoc($all_suppliers_res)) {
        $all_suppliers[] = $sup;
    }
}

// خريطة
$map_rows = [];
$project_lat = isset($selected_project['latitude']) ? floatval($selected_project['latitude']) : 0;
$project_lng = isset($selected_project['longitude']) ? floatval($selected_project['longitude']) : 0;
foreach ($operations_rows as $op) {
    if (intval($op['status']) !== 1) {
        continue;
    }
    $lat = isset($op['latitude']) ? floatval($op['latitude']) : 0;
    $lng = isset($op['longitude']) ? floatval($op['longitude']) : 0;
    if ($lat == 0 || $lng == 0) {
        $lat = $project_lat;
        $lng = $project_lng;
    }
    if ($lat == 0 || $lng == 0) {
        continue;
    }

    $map_rows[] = [
        'equipment' => trim((string)$op['equipment_code']) . ' - ' . trim((string)$op['equipment_name']),
        'type' => isset($op['equipment_type_name']) ? $op['equipment_type_name'] : '-',
        'drivers' => intval($active_drivers_count_by_eq[intval($op['equipment'])] ?? 0),
        'shift' => isset($op['shift_type']) ? $op['shift_type'] : 'B',
        'lat' => $lat,
        'lng' => $lng,
    ];
}

$page_title = "توزيع المعدات";
include '../inheader.php';
include '../insidebar.php';
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

<style>
    .movement-unified-page {
        overflow-y: auto;
    }

    .movement-unified-page .main_head {
        margin-bottom: 20px;
    }

    .movement-unified-page .section-title {
        font-size: 18px;
        font-weight: 700;
        color: #0b4c8c;
        margin: 20px 0 12px 0;
        border-right: 4px solid #0b4c8c;
        padding-right: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .movement-unified-page .card {
        margin-bottom: 20px;
        border: 1px solid #dae5f1;
        border-radius: 12px;
        background: #fff;
    }

    .movement-unified-page .card-header {
        background: #f8fbff;
        padding: 12px 15px;
        border-bottom: 1px solid #dae5f1;
        border-radius: 12px 12px 0 0;
    }

    .movement-unified-page .card-header h5 {
        margin: 0;
        font-size: 16px;
        color: #0b4c8c;
        font-weight: 700;
    }

    .movement-unified-page .card-body {
        padding: 15px;
    }

    .movement-unified-page table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
    }

    .movement-unified-page table th {
        background: #f0f5fa;
        color: #0b4c8c;
        font-weight: 700;
        padding: 10px;
        text-align: right;
        border-bottom: 2px solid #d0deec;
    }

    .movement-unified-page table td {
        padding: 10px;
        border-bottom: 1px solid #e6edf5;
        text-align: right;
    }

    .movement-unified-page table input,
    .movement-unified-page table select {
        min-width: 100px;
        padding: 6px;
        border: 1px solid #cfe0f2;
        border-radius: 6px;
        font-size: 13px;
    }

    .movement-unified-page table input:disabled,
    .movement-unified-page table select:disabled {
        background: #f5f9ff;
        cursor: not-allowed;
        color: #999;
    }

    .movement-unified-page .status-running {
        background: #d4edda;
        color: #155724;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }

    .movement-unified-page .status-idle {
        background: #e8eef5;
        color: #666;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }

    .movement-unified-page .status-maint {
        background: #fde7c8;
        color: #92400e;
        border: 1px solid #f0c98a;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-right: 4px;
        white-space: nowrap;
    }

    .movement-unified-page .btn-save-row {
        background: #0b4c8c;
        color: #fff;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
    }

    .movement-unified-page .btn-save-row:hover {
        background: #083a63;
    }

    .movement-unified-page .btn-end-row {
        background: #dc2626;
        color: #fff;
        border: none;
        padding: 5px 10px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        margin-right: 4px;
    }

    .movement-unified-page .btn-end-row:hover {
        background: #b91c1c;
    }

    .movement-unified-page .btn-maint-row {
        background: #d97706;
        color: #fff;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        margin-right: 4px;
    }

    .movement-unified-page .btn-maint-row:hover {
        background: #b45309;
    }

    /* حاوية أزرار الإجراء (حفظ / صيانة / إنهاء) جنبًا إلى جنب */
    .movement-unified-page .row-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
    }

    .movement-unified-page .row-actions .btn-save-row,
    .movement-unified-page .row-actions .btn-maint-row,
    .movement-unified-page .row-actions .btn-end-row {
        margin-right: 0;
        white-space: nowrap;
    }

    .movement-unified-page .row-actions .op_state_sel {
        min-width: auto;
        padding: 5px 8px;
        font-size: 12px;
        font-weight: 600;
    }

    /* نص «في الصيانة» في عمود الإجراء لصفوف الآليات المعطلة (مدير الحركة لا يُرجعها يدويًا) */
    .movement-unified-page .op-maint-note {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #92400e;
        background: #fde7c8;
        border: 1px solid #f0c98a;
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }

    /* شارة الحالة التشغيلية الموحّدة (تعمل / جاهزة / معطلة) */
    .movement-unified-page .op-state-badge {
        padding: 3px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
        display: inline-block;
        white-space: nowrap;
    }

    .movement-unified-page .op-state-badge.op-state-working {
        background: #d4edda;
        color: #155724;
    }

    .movement-unified-page .op-state-badge.op-state-ready {
        background: #fde7c8;
        color: #92400e;
        border: 1px solid #f0c98a;
    }

    .movement-unified-page .op-state-badge.op-state-broken {
        background: #f8d7da;
        color: #b91c1c;
        border: 1px solid #f1aeb5;
    }

    .movement-unified-page #monitoringMap {
        height: 400px;
        border: 1px solid #d5e2ef;
        border-radius: 12px;
        margin-bottom: 20px;
    }

    .movement-unified-page .form-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }

    .movement-unified-page .form-group {
        display: flex;
        flex-direction: column;
    }

    .movement-unified-page .form-group label {
        font-size: 13px;
        font-weight: 600;
        color: #0b4c8c;
        margin-bottom: 4px;
    }

    .movement-unified-page .form-group input,
    .movement-unified-page .form-group select {
        padding: 8px;
        border: 1px solid #cfe0f2;
        border-radius: 6px;
        font-size: 13px;
    }

    .movement-unified-page .btn-bulk-save {
        background: #28a745;
        color: #fff;
        border: none;
        padding: 12px 24px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 700;
        margin-top: 12px;
    }

    .movement-unified-page .btn-bulk-save:hover {
        background: #218838;
    }

    .movement-unified-page .success-message {
        padding: 12px 15px;
        border-radius: 6px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .movement-unified-page .success-message.is-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .movement-unified-page .success-message.is-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .movement-unified-page .table-scroll {
        overflow-x: auto;
        margin-bottom: 12px;
    }

    .movement-unified-page .collapse-section {
        display: none;
    }

    .movement-unified-page .collapse-section.show {
        display: block;
    }

    .movement-unified-page .collapse-btn {
        background: #0b4c8c;
        color: #fff;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .movement-unified-page .collapse-btn:hover {
        background: #083a63;
    }

    /* صفوف السائقين المدمجة */
    .movement-unified-page .drivers-sub-row > td {
        background: #f4f8fd;
        padding: 0;
        border-bottom: 2px solid #c0d4ea;
    }

    .movement-unified-page .sub-drivers-wrap {
        padding: 14px 18px;
    }

    .movement-unified-page .sub-drivers-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
        font-size: 13px;
        border-radius: 8px;
        overflow: hidden;
    }

    .movement-unified-page .sub-drivers-table th {
        background: #dce9f6;
        color: #0b4c8c;
        font-weight: 700;
        padding: 8px 10px;
        text-align: right;
        border-bottom: 1px solid #b8ceea;
        font-size: 12px;
    }

    .movement-unified-page .sub-drivers-table td {
        padding: 8px 10px;
        border-bottom: 1px solid #e6edf5;
        background: #fff;
    }

    .movement-unified-page .btn-drivers-toggle {
        background: #0b4c8c;
        color: #fff;
        border: none;
        padding: 5px 11px;
        border-radius: 20px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: background 0.2s;
        white-space: nowrap;
    }

    .movement-unified-page .btn-drivers-toggle:hover {
        background: #083a63;
    }

    .movement-unified-page .btn-drivers-toggle .toggle-icon {
        transition: transform 0.25s;
        font-size: 10px;
    }

    .movement-unified-page .btn-drivers-toggle.open .toggle-icon {
        transform: rotate(180deg);
    }

    .movement-unified-page .add-driver-inline {
        background: #eef6ff;
        border: 1px dashed #7aadd4;
        border-radius: 8px;
        padding: 12px 16px;
        margin-top: 6px;
    }

    .movement-unified-page .add-driver-inline > strong {
        display: block;
        color: #0b4c8c;
        margin-bottom: 10px;
        font-size: 13px;
    }

    .movement-unified-page .inline-form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: flex-end;
    }

    .movement-unified-page .inline-form-row .form-group {
        flex: 1;
        min-width: 140px;
    }

    /* تبويبات الورديات (نهار/ليل) */
    .movement-unified-page .ems-tabs-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        border-bottom: 2px solid #dae5f1;
        margin-bottom: 18px;
    }

    .movement-unified-page .ems-tab-btn {
        background: #f0f5fa;
        color: #0b4c8c;
        border: 1px solid #dae5f1;
        border-bottom: none;
        padding: 11px 26px;
        border-radius: 10px 10px 0 0;
        cursor: pointer;
        font-size: 15px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: -2px;
        transition: background 0.2s, color 0.2s;
    }

    .movement-unified-page .ems-tab-btn:hover {
        background: #e3eef9;
    }

    .movement-unified-page .ems-tab-btn.active {
        background: #0b4c8c;
        color: #fff;
        border-color: #0b4c8c;
    }

    .movement-unified-page .ems-tab-count {
        background: rgba(11, 76, 140, 0.12);
        color: #0b4c8c;
        border-radius: 12px;
        padding: 1px 9px;
        font-size: 12px;
        font-weight: 700;
        min-width: 20px;
        text-align: center;
    }

    .movement-unified-page .ems-tab-btn.active .ems-tab-count {
        background: #d4a017;
        color: #fff;
    }

    .movement-unified-page .ems-tab-panel {
        display: none;
    }

    .movement-unified-page .ems-tab-panel.active {
        display: block;
    }
</style>

<div class="main movement-page movement-unified-page">
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_icon       = 'fas fa-route';
    $header_title_html = 'توزيع المعدات <i class="fas fa-project-diagram"></i> ' . htmlspecialchars($selected_project['name']);
    $header_actions = array(
        array('href' => '../main/dashboard.php', 'class' => 'movement-topbar-btn', 'icon' => 'fas fa-home', 'label' => 'لوحة التحكم'),
    );
    $header_back = array('href' => '../main/dashboard.php', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include(__DIR__ . '/../includes/page_header.php');
    ?>

    <div class="ems-content">
        <?php if ($msg !== ''): ?>
            <div class="success-message <?php echo $is_success ? 'is-success' : 'is-error'; ?>">
                <i class="fas <?php echo $is_success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php
        // تبويب التشغيلات حسب الوردية (نهار/ليل): تاب لكل وردية، وجدول موحّد يضم كل الآليات دون تقسيم حسب الفئة.
        $operations_tables = [
            ['key' => 'day',   'title' => 'إدارة تشغيلات النهار', 'icon' => 'fa-sun',  'table_key' => 'day',   'rows' => $operations_rows_day],
            ['key' => 'night', 'title' => 'إدارة تشغيلات الليل',  'icon' => 'fa-moon', 'table_key' => 'night', 'rows' => $operations_rows_night],
        ];
        ?>
        <div class="ems-tabs">
            <div class="ems-tabs-nav">
                <?php foreach ($operations_tables as $tab_i => $tab_item): ?>
                    <button type="button"
                            class="ems-tab-btn<?php echo $tab_i === 0 ? ' active' : ''; ?>"
                            data-tab="<?php echo $tab_item['key']; ?>"
                            onclick="switchShiftTab('<?php echo $tab_item['key']; ?>')">
                        <i class="fas <?php echo $tab_item['icon']; ?>"></i>
                        <?php echo htmlspecialchars($tab_item['title'], ENT_QUOTES, 'UTF-8'); ?>
                        <span class="ems-tab-count"><?php echo count($tab_item['rows']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($operations_tables as $tab_i => $operations_table): ?>
            <div class="ems-tab-panel<?php echo $tab_i === 0 ? ' active' : ''; ?>" id="tab_<?php echo $operations_table['key']; ?>">
        <?php
        // داخل كل وردية: الآليات السارية مقسّمة حسب الحالة (تعمل / جاهزة / معطلة) ثم التشغيلات المنتهية.
        $ems_active_rows = array_values(array_filter($operations_table['rows'], function ($r) { return intval($r['status']) === 1; }));
        $ems_ended_rows  = array_values(array_filter($operations_table['rows'], function ($r) { return intval($r['status']) !== 1; }));
        $ems_state_of = function ($r) { return (isset($r['op_state']) && $r['op_state'] !== '') ? $r['op_state'] : 'جاهزة'; };
        $ems_working = array_values(array_filter($ems_active_rows, function ($r) use ($ems_state_of) { return $ems_state_of($r) === 'تعمل'; }));
        $ems_ready   = array_values(array_filter($ems_active_rows, function ($r) use ($ems_state_of) { return $ems_state_of($r) === 'جاهزة'; }));
        $ems_broken  = array_values(array_filter($ems_active_rows, function ($r) use ($ems_state_of) { return $ems_state_of($r) === 'معطلة'; }));
        $status_groups = [
            ['key' => 'working', 'label' => 'الآليات التي تعمل', 'icon' => 'fa-play-circle',         'empty' => 'لا توجد آليات تعمل',     'rows' => $ems_working],
            ['key' => 'ready',   'label' => 'الآليات الجاهزة',   'icon' => 'fa-clock',                'empty' => 'لا توجد آليات جاهزة',    'rows' => $ems_ready],
            ['key' => 'broken',  'label' => 'الآليات المعطلة',   'icon' => 'fa-triangle-exclamation', 'empty' => 'لا توجد آليات معطلة',    'rows' => $ems_broken],
            ['key' => 'ended',   'label' => 'التشغيلات المنتهية', 'icon' => 'fa-flag-checkered',       'empty' => 'لا توجد تشغيلات منتهية', 'rows' => $ems_ended_rows],
        ];
        foreach ($status_groups as $sgrp):
            $show_drivers = ($sgrp['key'] !== 'ended'); // عمود السائقين يظهر في الجداول السارية فقط، لا في المنتهية
            $col_count    = $show_drivers ? 9 : 8;
        ?>
        <div class="card">
            <div class="card-header">
                <h5><i class="fas <?php echo $sgrp['icon']; ?>"></i> <?php echo htmlspecialchars($operations_table['title'] . ' — ' . $sgrp['label'], ENT_QUOTES, 'UTF-8'); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-scroll">
                    <table class="no-datatable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الآلية</th>
                                <th>النوع</th>
                                <th>الوردية</th>
                                <th>بداية</th>
                                <th>نهاية</th>
                                <?php if ($show_drivers): ?><th>السائقون</th><?php endif; ?>
                                <th>الحالة</th>
                                <th>إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sgrp['rows'])): ?>
                                <tr><td colspan="<?php echo $col_count; ?>" style="text-align:center;color:#888;padding:14px;"><?php echo htmlspecialchars($sgrp['empty']); ?></td></tr>
                            <?php else: ?>
                                <?php $idx = 1; foreach ($sgrp['rows'] as $op):
                                    $op_id      = intval($op['id']);
                                    $eq_id      = intval($op['equipment']);
                                    $status     = intval($op['status']);
                                    $shift      = isset($op['shift_type']) ? $op['shift_type'] : 'B';
                                    $is_running = ($status === 1);
                                    $op_state   = (isset($op['op_state']) && $op['op_state'] !== '') ? $op['op_state'] : 'جاهزة';
                                    $is_broken  = ($op_state === 'معطلة');
                                    $shift_label = ($shift === 'D') ? 'نهاري' : (($shift === 'N') ? 'ليلي' : 'نهاري + ليلي');
                                    $eq_drivers  = isset($drivers_by_equipment[$eq_id]) ? $drivers_by_equipment[$eq_id] : [];
                                    $tkey        = $operations_table['table_key'];
                                ?>
                                <!-- صف الآلية الرئيسي -->
                                <tr id="op_row_<?php echo $tkey; ?>_<?php echo $op_id; ?>">
                                    <td><?php echo $idx++; ?></td>
                                    <td><?php echo htmlspecialchars(($op['equipment_code'] ?? '-') . ' - ' . ($op['equipment_name'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars($op['equipment_type_name'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($is_running && $can_edit): ?>
                                            <select class="op_shift" data-op="<?php echo $op_id; ?>">
                                                <option value="D" <?php echo $shift === 'D' ? 'selected' : ''; ?>>نهاري</option>
                                                <option value="N" <?php echo $shift === 'N' ? 'selected' : ''; ?>>ليلي</option>
                                                <option value="B" <?php echo $shift === 'B' ? 'selected' : ''; ?>>نهاري + ليلي</option>
                                            </select>
                                        <?php else: ?>
                                            <span><?php echo htmlspecialchars($shift_label); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_running && $can_edit): ?>
                                            <input type="date" class="op_start" data-op="<?php echo $op_id; ?>" value="<?php echo htmlspecialchars($op['start'] ?? ''); ?>">
                                        <?php else: ?>
                                            <span><?php echo htmlspecialchars($op['start'] ?? '-'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_running && $can_edit): ?>
                                            <input type="date" class="op_end" data-op="<?php echo $op_id; ?>" value="<?php echo htmlspecialchars($op['end'] ?? ''); ?>">
                                        <?php else: ?>
                                            <span><?php echo htmlspecialchars($op['end'] ?? '-'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($show_drivers): ?>
                                    <td>
                                        <button type="button"
                                                class="btn-drivers-toggle"
                                                id="toggle_btn_<?php echo $tkey; ?>_<?php echo $op_id; ?>"
                                                onclick="toggleDrivers('<?php echo $tkey; ?>', <?php echo $op_id; ?>)">
                                            <i class="fas fa-users"></i>
                                            <?php echo intval($active_drivers_count_by_eq[$eq_id] ?? 0); ?>
                                            <i class="fas fa-chevron-down toggle-icon"></i>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if ($is_running): ?>
                                            <?php $state_class = ($op_state === 'تعمل') ? 'op-state-working' : (($op_state === 'معطلة') ? 'op-state-broken' : 'op-state-ready'); ?>
                                            <span class="op-state-badge <?php echo $state_class; ?>"><?php echo htmlspecialchars($op_state); ?></span>
                                        <?php else: ?>
                                            <span class="status-idle">منتهي</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_running && $is_broken): ?>
                                            <span class="op-maint-note"><i class="fas fa-wrench"></i> في الصيانة</span>
                                        <?php elseif ($is_running && $can_edit): ?>
                                            <div class="row-actions">
                                                <select class="op_state_sel" data-op="<?php echo $op_id; ?>" onchange="setOpState(<?php echo $op_id; ?>, this.value)" title="الحالة التشغيلية">
                                                    <option value="تعمل" <?php echo $op_state === 'تعمل' ? 'selected' : ''; ?>>تعمل</option>
                                                    <option value="جاهزة" <?php echo $op_state === 'جاهزة' ? 'selected' : ''; ?>>جاهزة</option>
                                                </select>
                                                <button type="button" class="btn-save-row" onclick="saveOperation(<?php echo $op_id; ?>, this)"><i class="fas fa-save"></i> حفظ</button>
                                                <button type="button" class="btn-maint-row" onclick="setMaintenance(<?php echo $eq_id; ?>, this)" title="تحويل المعدة إلى وضع الصيانة (يفتح أمر صيانة تلقائي)"><i class="fas fa-wrench"></i> صيانة</button>
                                                <button type="button" class="btn-end-row" onclick="endOperation(<?php echo $op_id; ?>, this)"><i class="fas fa-stop-circle"></i> إنهاء</button>
                                            </div>
                                        <?php else: ?>
                                            <span>-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if ($show_drivers): ?>
                                <!-- صف السائقين القابل للتوسيع -->
                                <tr id="op_drivers_<?php echo $tkey; ?>_<?php echo $op_id; ?>" class="drivers-sub-row" style="display:none;">
                                    <td colspan="9">
                                        <div class="sub-drivers-wrap">
                                            <table class="sub-drivers-table no-datatable">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>السائق</th>
                                                        <th>الهاتف</th>
                                                        <th>الوردية</th>
                                                        <th>بداية التعيين</th>
                                                        <th>نهاية التعيين</th>
                                                        <th>الحالة</th>
                                                        <th>إجراء</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($eq_drivers)): ?>
                                                        <tr><td colspan="8" style="text-align:center;color:#888;padding:10px;">لا يوجد سائقون مرتبطون بهذه الآلية</td></tr>
                                                    <?php else: ?>
                                                        <?php $didx = 1; foreach ($eq_drivers as $drv):
                                                            $rel_id     = intval($drv['id']);
                                                            $drv_shift  = isset($drv['shift_type']) ? $drv['shift_type'] : 'B';
                                                            $drv_status = intval($drv['status']);
                                                            $is_active  = ($drv_status === 1);
                                                            $drv_shift_label = ($drv_shift === 'D') ? 'نهاري' : (($drv_shift === 'N') ? 'ليلي' : 'نهاري + ليلي');
                                                            $end_display = ((string)$drv['end_date'] === '2099-12-31') ? '' : (string)$drv['end_date'];
                                                        ?>
                                                        <tr id="drv_row_<?php echo $rel_id; ?>">
                                                            <td><?php echo $didx++; ?></td>
                                                            <td><?php echo htmlspecialchars($drv['driver_name'] ?? '-'); ?></td>
                                                            <td><?php echo htmlspecialchars($drv['driver_phone'] ?? '-'); ?></td>
                                                            <td>
                                                                <?php if ($is_active && $can_edit): ?>
                                                                    <select class="drv_shift" data-rel="<?php echo $rel_id; ?>">
                                                                        <option value="D" <?php echo $drv_shift === 'D' ? 'selected' : ''; ?>>نهاري</option>
                                                                        <option value="N" <?php echo $drv_shift === 'N' ? 'selected' : ''; ?>>ليلي</option>
                                                                        <option value="B" <?php echo $drv_shift === 'B' ? 'selected' : ''; ?>>نهاري + ليلي</option>
                                                                    </select>
                                                                <?php else: ?>
                                                                    <span><?php echo htmlspecialchars($drv_shift_label); ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($is_active && $can_edit): ?>
                                                                    <input type="date" class="drv_start" data-rel="<?php echo $rel_id; ?>" value="<?php echo htmlspecialchars($drv['start_date'] ?? ''); ?>">
                                                                <?php else: ?>
                                                                    <span><?php echo htmlspecialchars($drv['start_date'] ?? '-'); ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($is_active && $can_edit): ?>
                                                                    <input type="date" class="drv_end" data-rel="<?php echo $rel_id; ?>" value="<?php echo htmlspecialchars($end_display); ?>">
                                                                <?php else: ?>
                                                                    <span><?php echo htmlspecialchars($end_display ?: '-'); ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($is_active): ?>
                                                                    <span class="status-running">ساري</span>
                                                                <?php else: ?>
                                                                    <span class="status-idle">منتهي</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($is_active && $can_edit): ?>
                                                                    <button type="button" class="btn-save-row" onclick="saveDriver(<?php echo $rel_id; ?>)"><i class="fas fa-save"></i> حفظ</button>
                                                                    <button type="button" class="btn-end-row"  onclick="endDriver(<?php echo $rel_id; ?>)"><i class="fas fa-stop-circle"></i> إنهاء</button>
                                                                <?php else: ?>
                                                                    <span>-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>

                                            <?php if ($can_add && $is_running): ?>
                                            <div class="add-driver-inline">
                                                <strong><i class="fas fa-plus-circle"></i> إضافة سائق لهذه الآلية</strong>
                                                <form class="add-driver-form" onsubmit="submitAddDriver(event, this, <?php echo $eq_id; ?>)">
                                                    <div class="inline-form-row">
                                                        <div class="form-group">
                                                            <label>السائق *</label>
                                                            <select name="employee_id" required>
                                                                <option value="">-- اختر السائق --</option>
                                                                <?php foreach ($all_drivers as $d): ?>
                                                                    <?php if (in_array(intval($d['id']), $active_driver_ids)) continue; ?>
                                                                    <option value="<?php echo intval($d['id']); ?>"><?php echo htmlspecialchars($d['name'] . ' - ' . $d['phone']); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>الوردية</label>
                                                            <select name="shift_type">
                                                                <option value="D">نهاري</option>
                                                                <option value="N">ليلي</option>
                                                                <option value="B" selected>نهاري + ليلي</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>بداية التعيين</label>
                                                            <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>">
                                                        </div>
                                                        <div class="form-group">
                                                            <label>نهاية التعيين</label>
                                                            <input type="date" name="end_date">
                                                        </div>
                                                        <div class="form-group" style="justify-content:flex-end;">
                                                            <label>&nbsp;</label>
                                                            <button type="submit" class="btn-bulk-save" style="padding:8px 16px;font-size:13px;margin-top:0;"><i class="fas fa-plus"></i> إضافة</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        </div>

        <!-- خريطة المراقبة -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-map-marked-alt"></i> خريطة المراقبة</h5>
            </div>
            <div class="card-body" style="padding:0;">
                <div id="monitoringMap"></div>
            </div>
        </div>
    </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    function toggleDrivers(tableKey, opId) {
        var subRow = document.getElementById('op_drivers_' + tableKey + '_' + opId);
        var btn    = document.getElementById('toggle_btn_' + tableKey + '_' + opId);
        if (!subRow) return;
        var isOpen = subRow.style.display !== 'none';
        subRow.style.display = isOpen ? 'none' : 'table-row';
        if (btn) btn.classList.toggle('open', !isOpen);
    }

    function switchShiftTab(key) {
        document.querySelectorAll('.movement-unified-page .ems-tab-btn').forEach(function (b) {
            b.classList.toggle('active', b.getAttribute('data-tab') === key);
        });
        document.querySelectorAll('.movement-unified-page .ems-tab-panel').forEach(function (p) {
            p.classList.toggle('active', p.id === 'tab_' + key);
        });
    }

    function saveOperation(opId, triggerBtn) {
        var row = triggerBtn ? triggerBtn.closest('tr') : null;
        var get = function(sel) {
            var el = (row && row.querySelector(sel)) || document.querySelector(sel);
            return el ? el.value : '';
        };
        var formData = new FormData();
        formData.append('action', 'save_single_operation');
        formData.append('op_id', opId);
        formData.append('shift_type', get('.op_shift[data-op="' + opId + '"]') || 'B');
        formData.append('status', 1);
        formData.append('start', get('.op_start[data-op="' + opId + '"]'));
        formData.append('end',   get('.op_end[data-op="'   + opId + '"]'));
        formData.append('json', '1');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) { d.success ? (alert('✅ تم الحفظ'), location.reload()) : alert('❌ ' + d.message); })
            .catch(function()  { alert('❌ خطأ في الاتصال'); });
    }

    function setMaintenance(equipmentId, triggerBtn) {
        if (!confirm('هل تريد تحويل هذه المعدة إلى وضع الصيانة؟ ستصبح «معطلة» ويُفتح أمر صيانة تلقائي.')) return;
        if (triggerBtn) triggerBtn.disabled = true;
        var formData = new FormData();
        formData.append('action', 'set_equipment_maintenance');
        formData.append('equipment_id', equipmentId);
        formData.append('json', '1');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) { d.success ? (alert('✅ ' + d.message), location.reload()) : (alert('❌ ' + d.message), triggerBtn && (triggerBtn.disabled = false)); })
            .catch(function()  { alert('❌ خطأ في الاتصال'); if (triggerBtn) triggerBtn.disabled = false; });
    }

    function setOpState(opId, newState) {
        var formData = new FormData();
        formData.append('action', 'set_op_state');
        formData.append('op_id', opId);
        formData.append('op_state', newState);
        formData.append('json', '1');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) { d.success ? location.reload() : alert('❌ ' + d.message); })
            .catch(function()  { alert('❌ خطأ في الاتصال'); });
    }

    function endOperation(opId, triggerBtn) {
        if (!confirm('هل تريد إنهاء هذا التشغيل؟')) return;
        var row = triggerBtn ? triggerBtn.closest('tr') : null;
        var get = function(sel) {
            var el = (row && row.querySelector(sel)) || document.querySelector(sel);
            return el ? el.value : '';
        };
        var formData = new FormData();
        formData.append('action', 'save_single_operation');
        formData.append('op_id', opId);
        formData.append('shift_type', get('.op_shift[data-op="' + opId + '"]') || 'B');
        formData.append('status', 0);
        formData.append('json', '1');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) { d.success ? (alert('✅ تم إنهاء التشغيل'), location.reload()) : alert('❌ ' + d.message); })
            .catch(function()  { alert('❌ خطأ في الاتصال'); });
    }

    function saveDriver(relId) {
        var get = function(sel) { var el = document.querySelector(sel); return el ? el.value : ''; };
        var formData = new FormData();
        formData.append('action', 'save_single_driver');
        formData.append('rel_id', relId);
        formData.append('shift_type', get('.drv_shift[data-rel="' + relId + '"]') || 'B');
        formData.append('status', 1);
        formData.append('start_date', get('.drv_start[data-rel="' + relId + '"]'));
        formData.append('end_date',   get('.drv_end[data-rel="'   + relId + '"]'));
        formData.append('json', '1');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) { d.success ? (alert('✅ تم الحفظ'), location.reload()) : alert('❌ ' + d.message); })
            .catch(function()  { alert('❌ خطأ في الاتصال'); });
    }

    function endDriver(relId) {
        if (!confirm('هل تريد إنهاء تشغيل هذا السائق؟')) return;
        var shiftEl = document.querySelector('.drv_shift[data-rel="' + relId + '"]');
        var formData = new FormData();
        formData.append('action', 'save_single_driver');
        formData.append('rel_id', relId);
        formData.append('shift_type', shiftEl ? shiftEl.value : 'B');
        formData.append('status', 0);
        formData.append('end_date', new Date().toISOString().split('T')[0]);
        formData.append('json', '1');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) { d.success ? (alert('✅ تم إنهاء تشغيل السائق'), location.reload()) : alert('❌ ' + d.message); })
            .catch(function()  { alert('❌ خطأ في الاتصال'); });
    }

    function submitAddDriver(event, form, equipmentId) {
        event.preventDefault();
        var driverSel  = form.querySelector('[name="employee_id"]');
        var shiftSel   = form.querySelector('[name="shift_type"]');
        var startInput = form.querySelector('[name="start_date"]');
        var endInput   = form.querySelector('[name="end_date"]');
        if (!driverSel || !driverSel.value) { alert('يرجى اختيار السائق'); return; }
        var formData = new FormData();
        formData.append('action', 'add_new_driver');
        formData.append('employee_id',   driverSel.value);
        formData.append('equipment_id', equipmentId);
        formData.append('shift_type',  shiftSel   ? shiftSel.value   : 'B');
        formData.append('start_date',  startInput ? startInput.value : '');
        formData.append('end_date',    endInput   ? endInput.value   : '');
        formData.append('json', '1');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) { d.success ? (alert('✅ ' + d.message), location.reload()) : alert('❌ ' + d.message); })
            .catch(function()  { alert('❌ خطأ في الاتصال'); });
    }

    // خريطة Leaflet
    (function () {
        var mapRows    = <?php echo json_encode($map_rows, JSON_UNESCAPED_UNICODE); ?>;
        var projectLat = <?php echo isset($selected_project['latitude']) ? floatval($selected_project['latitude']) : 0; ?>;
        var projectLng = <?php echo isset($selected_project['longitude']) ? floatval($selected_project['longitude']) : 0; ?>;

        var mapCenterLat = 15.5007;
        var mapCenterLng = 32.5599;
        if (projectLat !== 0 && projectLng !== 0) {
            mapCenterLat = projectLat;
            mapCenterLng = projectLng;
        } else if (mapRows.length > 0) {
            mapCenterLat = parseFloat(mapRows[0].lat || mapCenterLat);
            mapCenterLng = parseFloat(mapRows[0].lng || mapCenterLng);
        }

        var map = L.map('monitoringMap').setView([mapCenterLat, mapCenterLng], 9);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        mapRows.forEach(function (row) {
            var shiftLabel = row.shift === 'D' ? 'نهاري' : (row.shift === 'N' ? 'ليلي' : 'نهاري + ليلي');
            L.marker([parseFloat(row.lat), parseFloat(row.lng)]).addTo(map)
                .bindPopup('<b>' + row.equipment + '</b><br>النوع: ' + (row.type || '-') + '<br>السائقون: ' + row.drivers + '<br>الوردية: ' + shiftLabel);
        });
    })();
</script>
