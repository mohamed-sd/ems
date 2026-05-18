<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

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
$drivers_has_company = db_table_has_column($conn, 'drivers', 'company_id');
$drivers_has_status = db_table_has_column($conn, 'drivers', 'status');
$equipments_has_lat = db_table_has_column($conn, 'equipments', 'latitude');
$equipments_has_lng = db_table_has_column($conn, 'equipments', 'longitude');
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

                $equipment_category = isset($_POST['equipment_category']) ? trim((string)$_POST['equipment_category']) : 'أساسي';
                if ($equipment_category !== 'أساسي' && $equipment_category !== 'احتياطي') {
                    $equipment_category = 'أساسي';
                }

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

                $category_sql = mysqli_real_escape_string($conn, $equipment_category);
                $shift_sql = mysqli_real_escape_string($conn, $shift_type);

                $start_sql = $start !== '' ? (" start = '" . mysqli_real_escape_string($conn, $start) . "',") : '';
                $end_sql = $end !== '' ? (" end = '" . mysqli_real_escape_string($conn, $end) . "',") : '';

                $update_sql = "UPDATE operations
                               SET equipment_category = '$category_sql',
                                   shift_type = '$shift_sql',
                                   status = $status
                                   $start_sql
                                   $end_sql
                                   id = id
                               WHERE id = $op_id AND project_id = $selected_project_id$operations_company_scope_inline";
                if (!mysqli_query($conn, $update_sql)) {
                    throw new Exception('خطأ في تحديث التشغيل');
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
                if ($equipment_category !== 'أساسي' && $equipment_category !== 'احتياطي') {
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
                $driver_id = intval($_POST['driver_id'] ?? 0);
                $equipment_id = intval($_POST['equipment_id'] ?? 0);

                if ($driver_id <= 0 || $equipment_id <= 0) {
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
                mysqli_query($conn, "UPDATE equipment_drivers SET status = 0, end_date = '$start_save' WHERE driver_id = $driver_id AND status = 1$ed_company_scope_inline");

                $insert_company_col = (!$is_super_admin && $equipment_drivers_has_company) ? ", company_id" : "";
                $insert_company_val = (!$is_super_admin && $equipment_drivers_has_company) ? ", $company_id" : "";
                $insert_shift_col = $equipment_drivers_has_shift_type ? ", shift_type" : "";
                $insert_shift_val = $equipment_drivers_has_shift_type ? ", '$shift_sql'" : "";

                $insert_driver_sql = "INSERT INTO equipment_drivers (equipment_id, driver_id, start_date, end_date, status$insert_shift_col$insert_company_col)
                                      VALUES ($equipment_id, $driver_id, '$start_save', '$end_save', 1$insert_shift_val$insert_company_val)";
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
    }
}

// جلب البيانات
$equip_lat_select = $equipments_has_lat ? 'e.latitude AS latitude' : 'NULL AS latitude';
$equip_lng_select = $equipments_has_lng ? 'e.longitude AS longitude' : 'NULL AS longitude';

$operations_sql = "SELECT o.id, o.equipment, o.equipment_category, o.start, o.end, o.shift_type, o.status,
                          o.total_equipment_hours, o.shift_hours,
                          e.code AS equipment_code, e.name AS equipment_name,
                          et.type AS equipment_type_name,
                          s.name AS supplier_name,
                          $equip_lat_select,
                          $equip_lng_select,
                          COUNT(DISTINCT CASE WHEN ed.status = 1 THEN ed.driver_id END) AS active_drivers_count
                   FROM operations o
                   LEFT JOIN equipments e ON e.id = o.equipment
                   LEFT JOIN equipments_types et ON et.id = e.type
                   LEFT JOIN suppliers s ON s.id = o.supplier_id
                   LEFT JOIN equipment_drivers ed ON ed.equipment_id = o.equipment
                   WHERE o.project_id = $selected_project_id
                     $operations_company_scope
                   GROUP BY o.id
                   ORDER BY o.status DESC, o.id DESC";
$operations_res = mysqli_query($conn, $operations_sql);
$operations_rows = [];
if ($operations_res) {
    while ($r = mysqli_fetch_assoc($operations_res)) {
        $operations_rows[] = $r;
    }
}

$drivers_sql = "SELECT ed.id, ed.equipment_id, ed.driver_id, ed.start_date, ed.end_date, ed.status,
                       " . ($equipment_drivers_has_shift_type ? "ed.shift_type" : "'B' AS shift_type") . ",
                       d.name AS driver_name, d.phone AS driver_phone,
                       e.code AS equipment_code, e.name AS equipment_name
                FROM equipment_drivers ed
                INNER JOIN drivers d ON d.id = ed.driver_id
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

// جلب جميع السائقين والمعدات
$all_drivers_sql = "SELECT DISTINCT d.id, d.name, d.phone
                    FROM drivers d
                    WHERE 1=1
                      $driver_company_scope
                      $driver_status_scope
                    ORDER BY d.name ASC";
$all_drivers_res = mysqli_query($conn, $all_drivers_sql);
$all_drivers = [];
if ($all_drivers_res) {
    while ($drv = mysqli_fetch_assoc($all_drivers_res)) {
        $all_drivers[] = $drv;
    }
}

$all_equipment_sql = "SELECT DISTINCT e.id, e.code, e.name
                      FROM equipments e
                      WHERE EXISTS (
                        SELECT 1 FROM operations o
                        WHERE o.equipment = e.id
                          AND o.project_id = $selected_project_id
                      )
                      $operations_company_scope
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
        'drivers' => intval($op['active_drivers_count']),
        'shift' => isset($op['shift_type']) ? $op['shift_type'] : 'B',
        'lat' => $lat,
        'lng' => $lng,
    ];
}

$page_title = "الحركة والتشغيل الموحدة";
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
</style>

<div class="main movement-page movement-unified-page">
    <div class="main_head">
        <div class="head_actions">
            <a href="move_oprators.php?project_id=<?php echo intval($selected_project_id); ?>" class="movement-topbar-btn" title="الصفحة القديمة"><i class="fas fa-cogs"></i> الصفحة القديمة</a>
            <a href="project_drivers.php?project_id=<?php echo intval($selected_project_id); ?>" class="movement-topbar-btn" title="السائقين القديمة"><i class="fas fa-id-badge"></i> السائقين</a>
            <a href="../main/dashboard.php" class="movement-topbar-btn"><i class="fas fa-home"></i> لوحة التحكم</a>
        </div>
        <h1 class="head-title">
            <div class="title-icon"><i class="fas fa-route"></i></div>
            الحركة والتشغيل الموحدة <i class="fas fa-project-diagram"></i> <?php echo htmlspecialchars($selected_project['name']); ?>
        </h1>
        <div class="head_back">
            <a href="../main/dashboard.php"><i class="fas fa-arrow-right"></i> رجوع</a>
        </div>
    </div>

    <div class="ems-content">
        <?php if ($msg !== ''): ?>
            <div class="success-message <?php echo $is_success ? 'is-success' : 'is-error'; ?>">
                <i class="fas <?php echo $is_success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <!-- إضافة تشغيل جديد -->
        <?php if ($can_add): ?>
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-plus-circle"></i> إضافة تشغيل جديد</h5>
                </div>
                <div class="card-body">
                    <form id="addOperationForm" method="post">
                        <input type="hidden" name="action" value="add_new_operation">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>العقد *</label>
                                <select name="contract_id" id="add_contract_id" required>
                                    <option value="">-- اختر العقد --</option>
                                    <?php foreach ($all_contracts as $ct): ?>
                                        <option value="<?php echo intval($ct['contract_id']); ?>"><?php echo htmlspecialchars($ct['contract_signing_date']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>المورد *</label>
                                <select name="supplier_id" id="add_supplier_id" required>
                                    <option value="">-- اختر المورد --</option>
                                    <?php foreach ($all_suppliers as $sup): ?>
                                        <option value="<?php echo intval($sup['id']); ?>"><?php echo htmlspecialchars($sup['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>نوع المعدة *</label>
                                <select name="equipment_type" id="add_equipment_type" required>
                                    <option value="">-- اختر النوع --</option>
                                    <?php
                                    $type_query = "SELECT DISTINCT id, type FROM equipments_types WHERE status = 1 ORDER BY type";
                                    $type_result = mysqli_query($conn, $type_query);
                                    if ($type_result) {
                                        while ($type_row = mysqli_fetch_assoc($type_result)) {
                                            echo "<option value='" . intval($type_row['id']) . "'>" . htmlspecialchars($type_row['type']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>المعدة *</label>
                                <select name="equipment" id="add_equipment" required>
                                    <option value="">-- اختر المعدة --</option>
                                    <?php foreach ($all_equipment as $eq): ?>
                                        <option value="<?php echo intval($eq['id']); ?>"><?php echo htmlspecialchars($eq['code'] . ' - ' . $eq['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>الفئة</label>
                                <select name="equipment_category">
                                    <option value="أساسي">أساسي</option>
                                    <option value="احتياطي">احتياطي</option>
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
                                <label>بداية التشغيل *</label>
                                <input type="date" name="start" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>نهاية التشغيل</label>
                                <input type="date" name="end">
                            </div>

                            <div class="form-group">
                                <label>إجمالي ساعات العمل</label>
                                <input type="number" name="total_equipment_hours" step="0.01" value="0">
                            </div>

                            <div class="form-group">
                                <label>ساعات الوردية</label>
                                <input type="number" name="shift_hours" step="0.01" value="0">
                            </div>

                            <div class="form-group">
                                <label>الحالة</label>
                                <select name="status">
                                    <option value="1" selected>ساري</option>
                                    <option value="0">منتهي</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn-bulk-save"><i class="fas fa-plus"></i> إضافة التشغيل</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- إضافة سائق جديد -->
        <?php if ($can_add): ?>
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-plus-circle"></i> إضافة سائق جديد</h5>
                </div>
                <div class="card-body">
                    <form id="addDriverForm" method="post">
                        <input type="hidden" name="action" value="add_new_driver">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>السائق *</label>
                                <select name="driver_id" id="add_driver_id" required>
                                    <option value="">-- اختر السائق --</option>
                                    <?php foreach ($all_drivers as $d): ?>
                                        <option value="<?php echo intval($d['id']); ?>"><?php echo htmlspecialchars($d['name'] . ' - ' . $d['phone']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>الآلية *</label>
                                <select name="equipment_id" id="add_driver_equipment" required>
                                    <option value="">-- اختر الآلية --</option>
                                    <?php foreach ($all_equipment as $eq): ?>
                                        <option value="<?php echo intval($eq['id']); ?>"><?php echo htmlspecialchars($eq['code'] . ' - ' . $eq['name']); ?></option>
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
                                <label>بداية التعيين *</label>
                                <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>نهاية التعيين</label>
                                <input type="date" name="end_date">
                            </div>
                        </div>
                        <button type="submit" class="btn-bulk-save"><i class="fas fa-plus"></i> إضافة السائق</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- جدول التشغيلات -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-cogs"></i> إدارة التشغيلات</h5>
            </div>
            <div class="card-body">
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الآلية</th>
                                <th>النوع</th>
                                <th>الفئة</th>
                                <th>الوردية</th>
                                <th>بداية</th>
                                <th>نهاية</th>
                                <th>سائقون</th>
                                <th>الحالة</th>
                                <th>إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $idx = 1; foreach ($operations_rows as $op): ?>
                                <?php
                                    $op_id = intval($op['id']);
                                    $status = intval($op['status']);
                                    $category = isset($op['equipment_category']) ? $op['equipment_category'] : 'أساسي';
                                    $shift = isset($op['shift_type']) ? $op['shift_type'] : 'B';
                                    $is_running = ($status === 1);
                                    $shift_label = ($shift === 'D') ? 'نهاري' : (($shift === 'N') ? 'ليلي' : 'نهاري + ليلي');
                                ?>
                                <tr id="op_row_<?php echo $op_id; ?>">
                                    <td><?php echo $idx++; ?></td>
                                    <td><?php echo htmlspecialchars(($op['equipment_code'] ?? '-') . ' - ' . ($op['equipment_name'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars($op['equipment_type_name'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($is_running && $can_edit): ?>
                                            <select class="op_category" data-op="<?php echo $op_id; ?>">
                                                <option value="أساسي" <?php echo $category === 'أساسي' ? 'selected' : ''; ?>>أساسي</option>
                                                <option value="احتياطي" <?php echo $category === 'احتياطي' ? 'selected' : ''; ?>>احتياطي</option>
                                            </select>
                                        <?php else: ?>
                                            <span><?php echo htmlspecialchars($category); ?></span>
                                        <?php endif; ?>
                                    </td>
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
                                    <td><?php echo intval($op['active_drivers_count']); ?></td>
                                    <td>
                                        <span class="status-<?php echo $is_running ? 'running' : 'idle'; ?>">
                                            <?php echo $is_running ? 'ساري' : 'منتهي'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($is_running && $can_edit): ?>
                                            <button type="button" class="btn-save-row" onclick="saveOperation(<?php echo $op_id; ?>)"><i class="fas fa-save"></i> حفظ</button>
                                        <?php else: ?>
                                            <a href="move_oprators.php?project_id=<?php echo intval($selected_project_id); ?>" class="btn-save-row" style="text-decoration:none; display:inline-block;"><i class="fas fa-eye"></i> عرض</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- جدول السائقين -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-id-badge"></i> إدارة السائقين</h5>
            </div>
            <div class="card-body">
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>السائق</th>
                                <th>الآلية</th>
                                <th>الوردية</th>
                                <th>بداية</th>
                                <th>نهاية</th>
                                <th>الحالة</th>
                                <th>إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $idx = 1; foreach ($drivers_rows as $drv): ?>
                                <?php
                                    $rel_id = intval($drv['id']);
                                    $drv_shift = isset($drv['shift_type']) ? $drv['shift_type'] : 'B';
                                    $drv_status = intval($drv['status']);
                                    $is_active = ($drv_status === 1);
                                    $shift_label = ($drv_shift === 'D') ? 'نهاري' : (($drv_shift === 'N') ? 'ليلي' : 'نهاري + ليلي');
                                    $end_display = ((string)$drv['end_date'] === '2099-12-31') ? '' : (string)$drv['end_date'];
                                ?>
                                <tr id="drv_row_<?php echo $rel_id; ?>">
                                    <td><?php echo $idx++; ?></td>
                                    <td><?php echo htmlspecialchars($drv['driver_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars(($drv['equipment_code'] ?? '-') . ' - ' . ($drv['equipment_name'] ?? '-')); ?></td>
                                    <td>
                                        <?php if ($is_active && $can_edit): ?>
                                            <select class="drv_shift" data-rel="<?php echo $rel_id; ?>">
                                                <option value="D" <?php echo $drv_shift === 'D' ? 'selected' : ''; ?>>نهاري</option>
                                                <option value="N" <?php echo $drv_shift === 'N' ? 'selected' : ''; ?>>ليلي</option>
                                                <option value="B" <?php echo $drv_shift === 'B' ? 'selected' : ''; ?>>نهاري + ليلي</option>
                                            </select>
                                        <?php else: ?>
                                            <span><?php echo htmlspecialchars($shift_label); ?></span>
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
                                        <span class="status-<?php echo $is_active ? 'running' : 'idle'; ?>">
                                            <?php echo $is_active ? 'ساري' : 'منتهي'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($is_active && $can_edit): ?>
                                            <button type="button" class="btn-save-row" onclick="saveDriver(<?php echo $rel_id; ?>)"><i class="fas fa-save"></i> حفظ</button>
                                        <?php else: ?>
                                            <a href="project_drivers.php?project_id=<?php echo intval($selected_project_id); ?>" class="btn-save-row" style="text-decoration:none; display:inline-block;"><i class="fas fa-eye"></i> عرض</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    function saveOperation(opId) {
        const category = document.querySelector('.op_category[data-op="' + opId + '"]')?.value || 'أساسي';
        const shift = document.querySelector('.op_shift[data-op="' + opId + '"]')?.value || 'B';
        const start = document.querySelector('.op_start[data-op="' + opId + '"]')?.value || '';
        const end = document.querySelector('.op_end[data-op="' + opId + '"]')?.value || '';

        const formData = new FormData();
        formData.append('action', 'save_single_operation');
        formData.append('op_id', opId);
        formData.append('equipment_category', category);
        formData.append('shift_type', shift);
        formData.append('status', 1);
        formData.append('start', start);
        formData.append('end', end);
        formData.append('json', '1');

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ تم الحفظ');
                location.reload();
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(error => {
            alert('❌ خطأ في الحفظ: ' + error);
        });
    }

    function saveDriver(relId) {
        const shift = document.querySelector('.drv_shift[data-rel="' + relId + '"]')?.value || 'B';
        const start = document.querySelector('.drv_start[data-rel="' + relId + '"]')?.value || '';
        const end = document.querySelector('.drv_end[data-rel="' + relId + '"]')?.value || '';

        const formData = new FormData();
        formData.append('action', 'save_single_driver');
        formData.append('rel_id', relId);
        formData.append('shift_type', shift);
        formData.append('status', 1);
        formData.append('start_date', start);
        formData.append('end_date', end);
        formData.append('json', '1');

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ تم الحفظ');
                location.reload();
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(error => {
            alert('❌ خطأ في الحفظ: ' + error);
        });
    }

    // خريطة Leaflet
    (function () {
        var mapRows = <?php echo json_encode($map_rows, JSON_UNESCAPED_UNICODE); ?>;
        var projectLat = <?php echo isset($selected_project['latitude']) ? floatval($selected_project['latitude']) : 0; ?>;
        var projectLng = <?php echo isset($selected_project['longitude']) ? floatval($selected_project['longitude']) : 0; ?>;

        var mapCenterLat = 24.7136;
        var mapCenterLng = 46.6753;
        if (projectLat !== 0 && projectLng !== 0) {
            mapCenterLat = projectLat;
            mapCenterLng = projectLng;
        } else if (mapRows.length > 0) {
            mapCenterLat = parseFloat(mapRows[0].lat || 24.7136);
            mapCenterLng = parseFloat(mapRows[0].lng || 46.6753);
        }

        var map = L.map('monitoringMap').setView([mapCenterLat, mapCenterLng], 9);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        mapRows.forEach(function (row) {
            var shiftLabel = row.shift === 'D' ? 'نهاري' : (row.shift === 'N' ? 'ليلي' : 'نهاري + ليلي');
            var marker = L.marker([parseFloat(row.lat), parseFloat(row.lng)]).addTo(map);
            marker.bindPopup(
                '<b>' + row.equipment + '</b><br>' +
                'النوع: ' + (row.type || '-') + '<br>' +
                'السائقون: ' + row.drivers + '<br>' +
                'الوردية: ' + shiftLabel
            );
        });
    })();
</script>
