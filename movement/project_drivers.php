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

// (mine filtering removed - operations filter by project_id directly)
$is_movement_manager = ($current_role === '6');
$project_client_column = db_table_has_column($conn, 'project', 'client_id') ? 'client_id' : 'company_client_id';
$project_has_company_id = db_table_has_column($conn, 'project', 'company_id');
$operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
$equipment_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');
$equipment_drivers_has_shift_type = db_table_has_column($conn, 'equipment_drivers', 'shift_type');
$drivers_has_company = db_table_has_column($conn, 'drivers', 'company_id');
$drivers_has_status = db_table_has_column($conn, 'drivers', 'status');

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

if (!$equipment_drivers_has_shift_type) {
    @mysqli_query($conn, "ALTER TABLE equipment_drivers ADD COLUMN shift_type ENUM('D','N','B') NOT NULL DEFAULT 'B' AFTER end_date");
    $equipment_drivers_has_shift_type = db_table_has_column($conn, 'equipment_drivers', 'shift_type');
}

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

// صلاحيات الصفحة: نفس نمط شاشة التشغيل
$page_permissions = check_page_permissions($conn, 'movement/project_drivers.php');
$can_view = !empty($page_permissions['can_view']);
$can_edit = !empty($page_permissions['can_edit']);

if (!$can_view) {
    header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+شاشة+سائقي+المشروع+❌");
    exit();
}

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
    echo "<script>alert('❌ لا يوجد مشروع مرتبط بالمستخدم في الجلسة'); window.location.href='../main/dashboard.php';</script>";
    exit();
}

$project_query = "SELECT id, name, project_code FROM project WHERE id = $selected_project_id AND status = 1 AND $project_scope_sql";
$project_result = mysqli_query($conn, $project_query);
if (!$project_result || mysqli_num_rows($project_result) === 0) {
    unset($_SESSION['operations_project_id']);
    echo "<script>alert('❌ المشروع غير متاح أو غير نشط'); window.location.href='../main/dashboard.php';</script>";
    exit();
}
$selected_project = mysqli_fetch_assoc($project_result);

$operations_company_scope = (!$is_super_admin && $operations_has_company) ? " AND o.company_id = $company_id" : "";
$ed_company_scope = (!$is_super_admin && $equipment_drivers_has_company) ? " AND ed.company_id = $company_id" : "";
$driver_company_scope = (!$is_super_admin && $drivers_has_company) ? " AND d.company_id = $company_id" : "";
$driver_status_scope = $drivers_has_status ? " AND d.status = 1" : "";

// (mine filter removed - operations filtered by project_id only)
$ops_mine_filter = "";

$msg = '';
$is_success = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$can_edit) {
        $msg = 'لا توجد صلاحية للتعديل ❌';
        $is_success = false;
    } else {
        $action = trim($_POST['action']);
        $relation_id = isset($_POST['relation_id']) ? intval($_POST['relation_id']) : 0;

        if ($action === 'add_driver_assignment') {
            $driver_ids = isset($_POST['driver_ids']) && is_array($_POST['driver_ids']) ? $_POST['driver_ids'] : [];
            $equipment_id = isset($_POST['equipment_id']) ? intval($_POST['equipment_id']) : 0;
            $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
            $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
            $shift_type_raw = isset($_POST['shift_type']) ? strval($_POST['shift_type']) : 'B';
            $auto_replace = isset($_POST['auto_replace']) ? intval($_POST['auto_replace']) : 0;

            $allowed_shift_types = array('D', 'N', 'B');
            $shift_type = in_array($shift_type_raw, $allowed_shift_types, true) ? $shift_type_raw : 'B';

            $start_obj = DateTime::createFromFormat('Y-m-d', $start_date);
            $start_valid = $start_obj && $start_obj->format('Y-m-d') === $start_date;

            $end_valid = true;
            if ($end_date !== '') {
                $end_obj = DateTime::createFromFormat('Y-m-d', $end_date);
                $end_valid = $end_obj && $end_obj->format('Y-m-d') === $end_date;
            }

            $normalized_driver_ids = [];
            foreach ($driver_ids as $driver_id_raw) {
                $driver_id = intval($driver_id_raw);
                if ($driver_id > 0) {
                    $normalized_driver_ids[] = $driver_id;
                }
            }
            $normalized_driver_ids = array_values(array_unique($normalized_driver_ids));

            if (count($normalized_driver_ids) === 0 || $equipment_id <= 0 || !$start_valid || !$end_valid) {
                $msg = 'بيانات إضافة التشغيل غير صحيحة ❌';
                $is_success = false;
            } elseif ($end_date !== '' && strtotime($end_date) < strtotime($start_date)) {
                $msg = 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية ❌';
                $is_success = false;
            } else {
                mysqli_begin_transaction($conn);
                try {
                    $equipment_check_sql = "SELECT 1 FROM operations o
                                            WHERE o.equipment = $equipment_id
                                              AND o.project_id = $selected_project_id
                                              $operations_company_scope
                                              $ops_mine_filter
                                            LIMIT 1";
                    $equipment_check_res = mysqli_query($conn, $equipment_check_sql);
                    if (!$equipment_check_res || mysqli_num_rows($equipment_check_res) === 0) {
                        throw new Exception('الآلية المختارة ليست ضمن المشروع الحالي');
                    }

                    $inserted_count = 0;
                    $skipped_count = 0;
                    foreach ($normalized_driver_ids as $driver_id) {
                        $driver_check_sql = "SELECT d.id FROM drivers d
                                             WHERE d.id = $driver_id
                                               $driver_company_scope
                                               $driver_status_scope
                                             LIMIT 1";
                        $driver_check_res = mysqli_query($conn, $driver_check_sql);
                        if (!$driver_check_res || mysqli_num_rows($driver_check_res) === 0) {
                            $skipped_count++;
                            continue;
                        }

                        $active_any_sql = "SELECT ed.id, ed.equipment_id
                                           FROM equipment_drivers ed
                                           WHERE ed.driver_id = $driver_id
                                             AND ed.status = 1
                                             $ed_company_scope
                                             AND EXISTS (
                                                 SELECT 1
                                                 FROM operations o
                                                 WHERE o.equipment = ed.equipment_id
                                                   AND o.project_id = $selected_project_id
                                                   $operations_company_scope
                                                   $ops_mine_filter
                                             )
                                           LIMIT 1";
                        $active_any_res = mysqli_query($conn, $active_any_sql);
                        $active_any_row = ($active_any_res && mysqli_num_rows($active_any_res) > 0) ? mysqli_fetch_assoc($active_any_res) : null;

                        if ($active_any_row) {
                            $current_equipment_id = intval($active_any_row['equipment_id']);
                            if ($current_equipment_id == $equipment_id) {
                                $skipped_count++;
                                continue;
                            }

                            if ($auto_replace === 1) {
                                $update_scope = (!$is_super_admin && $equipment_drivers_has_company) ? " AND company_id = $company_id" : "";
                                $close_sql = "UPDATE equipment_drivers
                                              SET status = 0, end_date = '$start_date'
                                              WHERE driver_id = $driver_id AND status = 1$update_scope
                                                AND EXISTS (
                                                    SELECT 1
                                                    FROM operations o
                                                    WHERE o.equipment = equipment_drivers.equipment_id
                                                      AND o.project_id = $selected_project_id
                                                      $operations_company_scope
                                                      $ops_mine_filter
                                                )";
                                mysqli_query($conn, $close_sql);
                            } else {
                                $skipped_count++;
                                continue;
                            }
                        }

                        $insert_company_col = (!$is_super_admin && $equipment_drivers_has_company) ? ", company_id" : "";
                        $insert_company_val = (!$is_super_admin && $equipment_drivers_has_company) ? ", $company_id" : "";
                        $insert_shift_col = $equipment_drivers_has_shift_type ? ", shift_type" : "";
                        $insert_shift_val = $equipment_drivers_has_shift_type ? ", '$shift_type'" : "";
                        $end_sql = $end_date !== '' ? "'" . mysqli_real_escape_string($conn, $end_date) . "'" : "'2099-12-31'";
                        $insert_sql = "INSERT INTO equipment_drivers (equipment_id, driver_id, start_date, end_date, status$insert_shift_col$insert_company_col)
                                       VALUES ($equipment_id, $driver_id, '$start_date', $end_sql, 1$insert_shift_val$insert_company_val)";

                        if (!mysqli_query($conn, $insert_sql)) {
                            throw new Exception('فشل إضافة تشغيل أحد السائقين');
                        }
                        $inserted_count++;
                    }

                    if ($inserted_count === 0) {
                        throw new Exception('لم يتم إضافة أي سائق. تحقق من الحالة الحالية للسائقين');
                    }

                    mysqli_commit($conn);
                    $msg = "تمت إضافة $inserted_count سائق بنجاح ✅";
                    if ($skipped_count > 0) {
                        $msg .= " (تم تخطي $skipped_count)";
                    }
                    $is_success = true;
                } catch (Exception $ex) {
                    mysqli_rollback($conn);
                    $msg = $ex->getMessage() . ' ❌';
                    $is_success = false;
                }
            }
        } elseif ($action === 'manage_equipment_drivers') {
            $equipment_id = isset($_POST['equipment_id']) ? intval($_POST['equipment_id']) : 0;
            $effective_date = isset($_POST['effective_date']) ? trim($_POST['effective_date']) : date('Y-m-d');
            $existing_action = isset($_POST['existing_action']) && is_array($_POST['existing_action']) ? $_POST['existing_action'] : [];
            $existing_start_date = isset($_POST['existing_start_date']) && is_array($_POST['existing_start_date']) ? $_POST['existing_start_date'] : [];
            $existing_end_date = isset($_POST['existing_end_date']) && is_array($_POST['existing_end_date']) ? $_POST['existing_end_date'] : [];
            $move_to_equipment = isset($_POST['move_to_equipment']) && is_array($_POST['move_to_equipment']) ? $_POST['move_to_equipment'] : [];
            $add_driver_ids = isset($_POST['add_driver_ids']) && is_array($_POST['add_driver_ids']) ? $_POST['add_driver_ids'] : [];
            $add_shift_type_raw = isset($_POST['add_shift_type']) ? strval($_POST['add_shift_type']) : 'B';
            $auto_replace_manage = isset($_POST['auto_replace_manage']) ? intval($_POST['auto_replace_manage']) : 0;

            $date_obj = DateTime::createFromFormat('Y-m-d', $effective_date);
            $valid_date = $date_obj && $date_obj->format('Y-m-d') === $effective_date;
            $allowed_shift_types = array('D', 'N', 'B');
            $add_shift_type = in_array($add_shift_type_raw, $allowed_shift_types, true) ? $add_shift_type_raw : 'B';

            if ($equipment_id <= 0 || !$valid_date) {
                $msg = 'بيانات إدارة السائقين غير صحيحة ❌';
                $is_success = false;
            } else {
                mysqli_begin_transaction($conn);
                try {
                    $equipment_check_sql = "SELECT 1 FROM operations o
                                            WHERE o.equipment = $equipment_id
                                              AND o.project_id = $selected_project_id
                                              $operations_company_scope
                                              $ops_mine_filter
                                            LIMIT 1";
                    $equipment_check_res = mysqli_query($conn, $equipment_check_sql);
                    if (!$equipment_check_res || mysqli_num_rows($equipment_check_res) === 0) {
                        throw new Exception('الآلية غير متاحة داخل المشروع الحالي');
                    }

                    $processed_stop = 0;
                    $processed_move = 0;
                    $processed_date_update = 0;
                    foreach ($existing_action as $relation_id_raw => $action_value_raw) {
                        $relation_id = intval($relation_id_raw);
                        $action_value = trim((string) $action_value_raw);
                        if ($relation_id <= 0 || ($action_value !== 'keep' && $action_value !== 'remove' && $action_value !== 'move')) {
                            continue;
                        }

                        $rel_sql = "SELECT ed.id, ed.driver_id, ed.equipment_id, ed.status, ed.shift_type
                                    FROM equipment_drivers ed
                                    WHERE ed.id = $relation_id
                                      AND ed.equipment_id = $equipment_id
                                      AND ed.status = 1
                                      $ed_company_scope
                                    LIMIT 1";
                        $rel_res = mysqli_query($conn, $rel_sql);
                        if (!$rel_res || mysqli_num_rows($rel_res) === 0) {
                            continue;
                        }
                        $rel_row = mysqli_fetch_assoc($rel_res);
                        $driver_id = intval($rel_row['driver_id']);
                        $shift_type_existing = isset($rel_row['shift_type']) ? strval($rel_row['shift_type']) : 'B';
                        $current_start = isset($rel_row['start_date']) ? $rel_row['start_date'] : date('Y-m-d');
                        $current_end = isset($rel_row['end_date']) ? $rel_row['end_date'] : '2099-12-31';
                        if (!in_array($shift_type_existing, $allowed_shift_types, true)) {
                            $shift_type_existing = 'B';
                        }

                        $row_start = isset($existing_start_date[$relation_id_raw]) ? trim((string) $existing_start_date[$relation_id_raw]) : $current_start;
                        $row_end_input = isset($existing_end_date[$relation_id_raw]) ? trim((string) $existing_end_date[$relation_id_raw]) : $current_end;

                        $row_start_obj = DateTime::createFromFormat('Y-m-d', $row_start);
                        if (!$row_start_obj || $row_start_obj->format('Y-m-d') !== $row_start) {
                            throw new Exception('صيغة تاريخ بداية أحد السائقين غير صحيحة');
                        }

                        $normalized_row_end = '';
                        if ($row_end_input !== '') {
                            $row_end_obj = DateTime::createFromFormat('Y-m-d', $row_end_input);
                            if (!$row_end_obj || $row_end_obj->format('Y-m-d') !== $row_end_input) {
                                throw new Exception('صيغة تاريخ نهاية أحد السائقين غير صحيحة');
                            }
                            if (strtotime($row_end_input) < strtotime($row_start)) {
                                throw new Exception('تاريخ النهاية يجب أن يكون بعد تاريخ البداية لكل سائق');
                            }
                            $normalized_row_end = $row_end_input;
                        }

                        if ($action_value === 'keep') {
                            $target_end = $normalized_row_end !== '' ? $normalized_row_end : '2099-12-31';
                            $update_scope = (!$is_super_admin && $equipment_drivers_has_company) ? " AND company_id = $company_id" : "";
                            $update_dates_sql = "UPDATE equipment_drivers
                                                 SET start_date = '$row_start', end_date = '$target_end'
                                                 WHERE id = $relation_id AND status = 1$update_scope";
                            if (mysqli_query($conn, $update_dates_sql)) {
                                $processed_date_update++;
                            }
                            continue;
                        }

                        $close_date = $normalized_row_end !== '' ? $normalized_row_end : $effective_date;
                        if (strtotime($close_date) < strtotime($row_start)) {
                            throw new Exception('تاريخ الإيقاف/النقل لا يمكن أن يكون قبل تاريخ البداية');
                        }

                        $update_scope = (!$is_super_admin && $equipment_drivers_has_company) ? " AND company_id = $company_id" : "";
                        $close_sql = "UPDATE equipment_drivers
                                      SET status = 0, end_date = '$close_date'
                                      WHERE id = $relation_id AND status = 1$update_scope";
                        mysqli_query($conn, $close_sql);

                        if ($action_value === 'remove') {
                            $processed_stop++;
                            continue;
                        }

                        $target_equipment_id = isset($move_to_equipment[$relation_id_raw]) ? intval($move_to_equipment[$relation_id_raw]) : 0;
                        if ($target_equipment_id <= 0 || $target_equipment_id === $equipment_id) {
                            continue;
                        }

                        $target_check_sql = "SELECT 1 FROM operations o
                                             WHERE o.equipment = $target_equipment_id
                                               AND o.project_id = $selected_project_id
                                               $operations_company_scope
                                               $ops_mine_filter
                                             LIMIT 1";
                        $target_check_res = mysqli_query($conn, $target_check_sql);
                        if (!$target_check_res || mysqli_num_rows($target_check_res) === 0) {
                            continue;
                        }

                        $insert_company_col = (!$is_super_admin && $equipment_drivers_has_company) ? ", company_id" : "";
                        $insert_company_val = (!$is_super_admin && $equipment_drivers_has_company) ? ", $company_id" : "";
                        $insert_shift_col = $equipment_drivers_has_shift_type ? ", shift_type" : "";
                        $insert_shift_val = $equipment_drivers_has_shift_type ? ", '" . mysqli_real_escape_string($conn, $shift_type_existing) . "'" : "";
                        $insert_move_sql = "INSERT INTO equipment_drivers (equipment_id, driver_id, start_date, end_date, status$insert_shift_col$insert_company_col)
                                            VALUES ($target_equipment_id, $driver_id, '$close_date', '2099-12-31', 1$insert_shift_val$insert_company_val)";
                        if (mysqli_query($conn, $insert_move_sql)) {
                            $processed_move++;
                        }
                    }

                    $added_count = 0;
                    $skipped_count = 0;
                    $normalized_new_driver_ids = [];
                    foreach ($add_driver_ids as $driver_id_raw) {
                        $driver_id = intval($driver_id_raw);
                        if ($driver_id > 0) {
                            $normalized_new_driver_ids[] = $driver_id;
                        }
                    }
                    $normalized_new_driver_ids = array_values(array_unique($normalized_new_driver_ids));

                    foreach ($normalized_new_driver_ids as $driver_id) {
                        $driver_check_sql = "SELECT d.id FROM drivers d
                                             WHERE d.id = $driver_id
                                               $driver_company_scope
                                               $driver_status_scope
                                             LIMIT 1";
                        $driver_check_res = mysqli_query($conn, $driver_check_sql);
                        if (!$driver_check_res || mysqli_num_rows($driver_check_res) === 0) {
                            $skipped_count++;
                            continue;
                        }

                        $active_any_sql = "SELECT ed.id, ed.equipment_id
                                           FROM equipment_drivers ed
                                           WHERE ed.driver_id = $driver_id
                                             AND ed.status = 1
                                             $ed_company_scope
                                             AND EXISTS (
                                                 SELECT 1
                                                 FROM operations o
                                                 WHERE o.equipment = ed.equipment_id
                                                   AND o.project_id = $selected_project_id
                                                   $operations_company_scope
                                                   $ops_mine_filter
                                             )
                                           LIMIT 1";
                        $active_any_res = mysqli_query($conn, $active_any_sql);
                        $active_any_row = ($active_any_res && mysqli_num_rows($active_any_res) > 0) ? mysqli_fetch_assoc($active_any_res) : null;

                        if ($active_any_row) {
                            $current_equipment_id = intval($active_any_row['equipment_id']);
                            if ($current_equipment_id == $equipment_id) {
                                $skipped_count++;
                                continue;
                            }

                            if ($auto_replace_manage === 1) {
                                $update_scope = (!$is_super_admin && $equipment_drivers_has_company) ? " AND company_id = $company_id" : "";
                                $close_sql = "UPDATE equipment_drivers
                                              SET status = 0, end_date = '$effective_date'
                                              WHERE driver_id = $driver_id AND status = 1$update_scope
                                                AND EXISTS (
                                                    SELECT 1
                                                    FROM operations o
                                                    WHERE o.equipment = equipment_drivers.equipment_id
                                                      AND o.project_id = $selected_project_id
                                                      $operations_company_scope
                                                      $ops_mine_filter
                                                )";
                                mysqli_query($conn, $close_sql);
                            } else {
                                $skipped_count++;
                                continue;
                            }
                        }

                        $insert_company_col = (!$is_super_admin && $equipment_drivers_has_company) ? ", company_id" : "";
                        $insert_company_val = (!$is_super_admin && $equipment_drivers_has_company) ? ", $company_id" : "";
                        $insert_shift_col = $equipment_drivers_has_shift_type ? ", shift_type" : "";
                        $insert_shift_val = $equipment_drivers_has_shift_type ? ", '$add_shift_type'" : "";
                        $insert_sql = "INSERT INTO equipment_drivers (equipment_id, driver_id, start_date, end_date, status$insert_shift_col$insert_company_col)
                                       VALUES ($equipment_id, $driver_id, '$effective_date', '2099-12-31', 1$insert_shift_val$insert_company_val)";
                        if (mysqli_query($conn, $insert_sql)) {
                            $added_count++;
                        }
                    }

                    mysqli_commit($conn);
                    $msg = "تم تحديث التوزيع بنجاح ✅ (تعديل تواريخ: $processed_date_update | إيقاف: $processed_stop | نقل: $processed_move | إضافة: $added_count";
                    if ($skipped_count > 0) {
                        $msg .= " | تخطي: $skipped_count";
                    }
                    $msg .= ")";
                    $is_success = true;
                } catch (Exception $ex) {
                    mysqli_rollback($conn);
                    $msg = $ex->getMessage() . ' ❌';
                    $is_success = false;
                }
            }
        } elseif ($action === 'stop_driver') {
            if ($relation_id <= 0) {
                $msg = 'بيانات غير صحيحة ❌';
                $is_success = false;
            } else {
                $check_sql = "SELECT ed.id FROM equipment_drivers ed
                              WHERE ed.id = $relation_id
                                AND ed.status = 1
                                $ed_company_scope
                                AND EXISTS (
                                    SELECT 1 FROM operations o
                                    WHERE o.equipment = ed.equipment_id
                                      AND o.project_id = $selected_project_id
                                      $operations_company_scope
                                      $ops_mine_filter
                                )
                              LIMIT 1";
                $check_res = mysqli_query($conn, $check_sql);

                if (!$check_res || mysqli_num_rows($check_res) === 0) {
                    $msg = 'السجل غير موجود أو خارج نطاق المشروع ❌';
                    $is_success = false;
                } else {
                    $update_scope = (!$is_super_admin && $equipment_drivers_has_company) ? " AND company_id = $company_id" : "";
                    $update_sql = "UPDATE equipment_drivers
                                   SET status = 0, end_date = CURDATE()
                                   WHERE id = $relation_id AND status = 1$update_scope";
                    if (mysqli_query($conn, $update_sql) && mysqli_affected_rows($conn) > 0) {
                        $msg = 'تم إيقاف السائق بنجاح ✅';
                        $is_success = true;
                    } else {
                        $msg = 'لم يتم تحديث حالة السائق ❌';
                        $is_success = false;
                    }
                }
            }
        } elseif ($action === 'move_driver') {
            $new_equipment_id = isset($_POST['new_equipment_id']) ? intval($_POST['new_equipment_id']) : 0;
            $move_start_date = isset($_POST['move_start_date']) ? trim($_POST['move_start_date']) : date('Y-m-d');

            $date_obj = DateTime::createFromFormat('Y-m-d', $move_start_date);
            $valid_date = $date_obj && $date_obj->format('Y-m-d') === $move_start_date;

            if ($relation_id <= 0 || $new_equipment_id <= 0 || !$valid_date) {
                $msg = 'بيانات النقل غير صحيحة ❌';
                $is_success = false;
            } else {
                mysqli_begin_transaction($conn);
                try {
                    $rel_sql = "SELECT ed.id, ed.driver_id, ed.equipment_id, ed.status
                                                                , ed.shift_type
                                FROM equipment_drivers ed
                                WHERE ed.id = $relation_id
                                  $ed_company_scope
                                  AND EXISTS (
                                      SELECT 1 FROM operations o
                                      WHERE o.equipment = ed.equipment_id
                                        AND o.project_id = $selected_project_id
                                        $operations_company_scope
                                        $ops_mine_filter
                                  )
                                LIMIT 1";
                    $rel_res = mysqli_query($conn, $rel_sql);
                    if (!$rel_res || mysqli_num_rows($rel_res) === 0) {
                        throw new Exception('السجل المراد نقله غير موجود داخل المشروع');
                    }

                    $rel_row = mysqli_fetch_assoc($rel_res);
                    $driver_id = intval($rel_row['driver_id']);
                    $old_equipment_id = intval($rel_row['equipment_id']);
                    $old_status = intval($rel_row['status']);

                    if ($old_equipment_id === $new_equipment_id) {
                        throw new Exception('الآلية الجديدة هي نفسها الآلية الحالية');
                    }

                    $equip_check_sql = "SELECT 1 FROM operations o
                                       WHERE o.equipment = $new_equipment_id
                                         AND o.project_id = $selected_project_id
                                         $operations_company_scope
                                         $ops_mine_filter
                                       LIMIT 1";
                    $equip_check_res = mysqli_query($conn, $equip_check_sql);
                    if (!$equip_check_res || mysqli_num_rows($equip_check_res) === 0) {
                        throw new Exception('الآلية الجديدة ليست ضمن المشروع المحدد');
                    }

                    if ($old_status === 1) {
                        $stop_scope = (!$is_super_admin && $equipment_drivers_has_company) ? " AND company_id = $company_id" : "";
                        $stop_sql = "UPDATE equipment_drivers
                                     SET status = 0, end_date = '$move_start_date'
                                     WHERE id = $relation_id AND status = 1$stop_scope";
                        mysqli_query($conn, $stop_sql);
                    }

                    $active_check_sql = "SELECT ed.id
                                         FROM equipment_drivers ed
                                         WHERE ed.driver_id = $driver_id
                                           AND ed.status = 1
                                           $ed_company_scope
                                           AND EXISTS (
                                               SELECT 1 FROM operations o
                                               WHERE o.equipment = ed.equipment_id
                                                 AND o.project_id = $selected_project_id
                                                 $operations_company_scope
                                                 $ops_mine_filter
                                           )
                                         LIMIT 1";
                    $active_check_res = mysqli_query($conn, $active_check_sql);
                    if ($active_check_res && mysqli_num_rows($active_check_res) > 0) {
                        throw new Exception('السائق يعمل حالياً على آلية أخرى داخل المشروع');
                    }

                    $insert_company_col = (!$is_super_admin && $equipment_drivers_has_company) ? ", company_id" : "";
                    $insert_company_val = (!$is_super_admin && $equipment_drivers_has_company) ? ", $company_id" : "";
                    $insert_shift_col = $equipment_drivers_has_shift_type ? ", shift_type" : "";
                    $existing_shift_type = isset($rel_row['shift_type']) ? strval($rel_row['shift_type']) : 'B';
                    if (!in_array($existing_shift_type, array('D', 'N', 'B'), true)) {
                        $existing_shift_type = 'B';
                    }
                    $insert_shift_val = $equipment_drivers_has_shift_type ? ", '" . mysqli_real_escape_string($conn, $existing_shift_type) . "'" : "";
                    $insert_sql = "INSERT INTO equipment_drivers
                                   (equipment_id, driver_id, start_date, end_date, status$insert_shift_col$insert_company_col)
                                   VALUES ($new_equipment_id, $driver_id, '$move_start_date', '2099-12-31', 1$insert_shift_val$insert_company_val)";

                    if (!mysqli_query($conn, $insert_sql)) {
                        throw new Exception('فشل تسجيل تشغيل السائق على الآلية الجديدة');
                    }

                    mysqli_commit($conn);
                    $msg = 'تم نقل السائق وتشغيله على الآلية الجديدة ✅';
                    $is_success = true;
                } catch (Exception $ex) {
                    mysqli_rollback($conn);
                    $msg = $ex->getMessage() . ' ❌';
                    $is_success = false;
                }
            }
        }
    }
}

$equipments_sql = "SELECT DISTINCT e.id, e.code, e.name
                   FROM operations o
                   INNER JOIN equipments e ON e.id = o.equipment
                   WHERE o.project_id = $selected_project_id
                     AND o.status <> 0
                     $operations_company_scope
                     $ops_mine_filter
                   ORDER BY e.code ASC, e.name ASC";
$equipments_result = mysqli_query($conn, $equipments_sql);
$project_equipments = [];
if ($equipments_result) {
    while ($eq = mysqli_fetch_assoc($equipments_result)) {
        $project_equipments[] = $eq;
    }
}

$available_drivers_sql = "SELECT d.id, d.name, d.phone
                          FROM drivers d
                          WHERE 1=1
                            $driver_company_scope
                            $driver_status_scope
                            AND NOT EXISTS (
                                SELECT 1
                                FROM equipment_drivers ed
                                INNER JOIN operations o ON o.equipment = ed.equipment_id
                                WHERE ed.driver_id = d.id
                                  AND ed.status = 1
                                  AND o.project_id = $selected_project_id
                                  $operations_company_scope
                                  $ed_company_scope
                            )
                          ORDER BY d.name ASC";
$available_drivers_result = mysqli_query($conn, $available_drivers_sql);
$available_drivers = [];
if ($available_drivers_result) {
    while ($drv = mysqli_fetch_assoc($available_drivers_result)) {
        $available_drivers[] = $drv;
    }
}

$active_assignments_sql = "SELECT ed.id, ed.equipment_id, ed.driver_id, ed.start_date, ed.end_date, ed.status,
                                  ed.shift_type, d.name AS driver_name, d.phone AS driver_phone
                           FROM equipment_drivers ed
                           INNER JOIN drivers d ON d.id = ed.driver_id
                           WHERE ed.status = 1
                             $ed_company_scope
                             AND EXISTS (
                                 SELECT 1
                                 FROM operations o
                                 WHERE o.equipment = ed.equipment_id
                                   AND o.project_id = $selected_project_id
                                   $operations_company_scope
                                   $ops_mine_filter
                             )
                           ORDER BY ed.id DESC";
$active_assignments_res = mysqli_query($conn, $active_assignments_sql);

$drivers_by_equipment = [];
if ($active_assignments_res) {
    while ($driver_row = mysqli_fetch_assoc($active_assignments_res)) {
        $eq_id = intval($driver_row['equipment_id']);
        if (!isset($drivers_by_equipment[$eq_id])) {
            $drivers_by_equipment[$eq_id] = [];
        }
        $drivers_by_equipment[$eq_id][] = $driver_row;
    }
}

$all_project_drivers_sql = "SELECT DISTINCT d.id, d.name, d.phone
                            FROM drivers d
                            WHERE 1=1
                              $driver_company_scope
                              $driver_status_scope
                            ORDER BY d.name ASC";
$all_project_drivers_res = mysqli_query($conn, $all_project_drivers_sql);
$all_project_drivers = [];
if ($all_project_drivers_res) {
    while ($drv = mysqli_fetch_assoc($all_project_drivers_res)) {
        $all_project_drivers[] = $drv;
    }
}

$drivers_sql = "SELECT ed.id, ed.equipment_id, ed.driver_id, ed.start_date, ed.end_date, ed.status,
                       ed.shift_type,
                       e.code AS equipment_code, e.name AS equipment_name,
                       d.name AS driver_name, d.phone AS driver_phone
                FROM equipment_drivers ed
                INNER JOIN equipments e ON e.id = ed.equipment_id
                INNER JOIN drivers d ON d.id = ed.driver_id
                WHERE EXISTS (
                    SELECT 1
                    FROM operations o
                    WHERE o.equipment = ed.equipment_id
                      AND o.project_id = $selected_project_id
                      $operations_company_scope
                      $ops_mine_filter
                )
                $ed_company_scope
                ORDER BY ed.status DESC, ed.id DESC";
$drivers_result = mysqli_query($conn, $drivers_sql);

if (!function_exists('get_shift_type_label')) {
    function get_shift_type_label($value)
    {
        if ($value === 'D') {
            return 'نهاري فقط';
        }

        if ($value === 'N') {
            return 'ليلي فقط';
        }

        return 'نهاري + ليلي';
    }
}

$page_title = "سائقو المشروع";
include '../inheader.php';
include '../insidebar.php';
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

<style>
    .movement-drivers-page .module-horizontal-scroll {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .movement-drivers-page .module-horizontal-inner {
        min-width: 1250px;
    }

    .movement-drivers-page .table-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .movement-drivers-page #projectDriversTable {
        min-width: 1400px;
    }

    .movement-drivers-page .dataTables_wrapper .dataTables_scroll {
        overflow-x: auto;
    }

    .movement-drivers-page .dataTables_wrapper .dataTables_scrollBody {
        overflow-x: auto !important;
    }

    .drivers-count-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #eef6ff;
        color: #0b4c8c;
        border: 1px solid #cfe2ff;
        border-radius: 999px;
        padding: 4px 10px;
        font-weight: 700;
        font-size: 0.85rem;
    }

    .drivers-icons-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
    }

    .driver-icon-link {
        width: 32px;
        height: 32px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #0c1c3e;
        background: #f7f9fc;
        border: 1px solid #d7dee8;
        text-decoration: none;
        transition: all .2s ease;
    }

    .driver-icon-link:hover {
        background: #0c1c3e;
        color: #fff;
        border-color: #0c1c3e;
    }

    .manage-drivers-modal .table td,
    .manage-drivers-modal .table th {
        vertical-align: middle;
    }

    .manage-modal-actions {
        display: inline-flex;
        gap: 8px;
        align-items: center;
    }

    /* نمط الرابط لاسم المعدة */
    .equipment-name-link {
        color: #0c1c3e;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
        padding: 4px 8px;
        border-radius: 6px;
    }

    .equipment-name-link:hover {
        color: #e8b800;
        text-decoration: underline;
        background: rgba(232, 184, 0, 0.1);
    }

    .equipment-name-link:hover::before {
        content: "🔗 ";
    }
</style>

<div class="main movement-page movement-drivers-page">


    <div class="main_head">
        <div class="head_actions">
             <?php if ($can_edit): ?>
                <a href="javascript:void(0)" id="toggleAddDriverForm"
                    class="movement-topbar-btn movement-topbar-btn-primary add-btn"><i class="fas fa-plus-circle"></i> إضافة
                    تشغيل سائق</a>
            <?php endif; ?>
            <a href="movement_operations.php?project_id=<?php echo intval($selected_project_id); ?>"
                class="movement-topbar-btn"><i class="fas fa-route"></i> الحركة والتشغيل</a>
            <a href="move_oprators.php?project_id=<?php echo intval($selected_project_id); ?>"
                class="movement-topbar-btn"><i class="fas fa-cogs"></i> إدارة التشغيل</a>
            <a href="../main/dashboard.php" class="movement-topbar-btn"><i class="fas fa-home"></i> لوحة التحكم</a>
        </div>
        <h1 class="head-title">
            <div class="title-icon"><i class="fas fa-id-badge"></i></div>
           إدارة سائقي المشروع
             <i class="fas fa-project-diagram"></i>
           <?php echo htmlspecialchars($selected_project['name']); ?>
        </h1>
        <div class="head_back">
            <a href="../main/dashboard.php" class="">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="ems-content">
        <div class="module-horizontal-scroll">
            <div class="module-horizontal-inner">
        <?php if ($msg !== ''): ?>
            <div class="success-message <?php echo $is_success ? 'is-success' : 'is-error'; ?>">
                <i class="fas <?php echo $is_success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($can_edit): ?>
            <form id="addDriverForm" action="" method="post" class="allforms add-driver-form">
                <input type="hidden" name="action" value="add_driver_assignment">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user-plus"></i> تشغيل سائق جديد</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div>
                                <label><i class="fas fa-id-card"></i> السائقون * (يمكن اختيار أكثر من سائق)</label>
                                <select name="driver_ids[]" multiple required size="8">
                                    <?php foreach ($available_drivers as $drv): ?>
                                        <option value="<?php echo intval($drv['id']); ?>">
                                            <?php echo htmlspecialchars($drv['name'] . ' - ' . $drv['phone'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">استخدم Ctrl أو Shift لاختيار أكثر من سائق.</small>
                            </div>

                            <div>
                                <label><i class="fas fa-truck"></i> الآلية *</label>
                                <select name="equipment_id" required>
                                    <option value="">-- اختر الآلية --</option>
                                    <?php foreach ($project_equipments as $eq): ?>
                                        <?php $eqLabel = trim((string) $eq['code']) . ' - ' . trim((string) $eq['name']); ?>
                                        <option value="<?php echo intval($eq['id']); ?>">
                                            <?php echo htmlspecialchars($eqLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label><i class="fas fa-calendar-plus"></i> تاريخ البداية *</label>
                                <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div>
                                <label><i class="fas fa-calendar-times"></i> تاريخ النهاية (اختياري)</label>
                                <input type="date" name="end_date" value="">
                            </div>

                            <div>
                                <label><i class="fas fa-sync-alt"></i> نظام الوردية *</label>
                                <select name="shift_type" required>
                                    <option value="D">نهاري فقط</option>
                                    <option value="N">ليلي فقط</option>
                                    <option value="B" selected>نهاري + ليلي</option>
                                </select>
                            </div>

                            <div class="driver-form-check-row">
                                <input type="checkbox" id="auto_replace" name="auto_replace" value="1" checked>
                                <label for="auto_replace" class="driver-form-check-label">إيقاف أي تشغيل نشط لنفس السائق
                                    داخل المشروع تلقائيًا</label>
                            </div>

                            <div class="driver-form-actions">
                                <button type="button" id="cancelAddDriverForm" class="btn btn-secondary">إلغاء</button>
                                <button type="submit" class="btn btn-success">حفظ التشغيل</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-users"></i> توزيع السائقين على آليات المشروع</h5>
            </div>
            <div class="card-body">
                <div class="table-container" style="overflow-x: auto; width: 100%;">
                    <table id="projectDriversTable" class="display nowrap table-full-width" style="min-width: 1200px; width: 100%;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الآلية</th>
                                <th>عدد السائقين</th>
                                <th>السائقون</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $idx = 1;
                            foreach ($project_equipments as $eq) {
                                $eq_id = intval($eq['id']);
                                $eq_label = trim((string) $eq['code']) . ' - ' . trim((string) $eq['name']);
                                $eq_drivers = isset($drivers_by_equipment[$eq_id]) ? $drivers_by_equipment[$eq_id] : [];
                                $driver_count = count($eq_drivers);

                                $drivers_tooltip_html = '';
                                if ($driver_count > 0) {
                                    foreach ($eq_drivers as $drow) {
                                        $driver_id = intval($drow['driver_id']);
                                        $driver_name = isset($drow['driver_name']) ? $drow['driver_name'] : '';
                                        $driver_phone = isset($drow['driver_phone']) ? $drow['driver_phone'] : '';
                                        $shift_label = get_shift_type_label(isset($drow['shift_type']) ? $drow['shift_type'] : 'B');
                                        $tooltip_text = 'الاسم: ' . $driver_name . ' | الهاتف: ' . $driver_phone . ' | الوردية: ' . $shift_label;
                                        $drivers_tooltip_html .= '<a class="driver-icon-link" href="../Drivers/driver_profile.php?id=' . $driver_id . '" title="' . htmlspecialchars($tooltip_text, ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="tooltip" data-bs-placement="top">';
                                        $drivers_tooltip_html .= '<i class="fas fa-user"></i>';
                                        $drivers_tooltip_html .= '</a>';
                                    }
                                } else {
                                    $drivers_tooltip_html = '<span class="text-muted">لا يوجد سائقون نشطون</span>';
                                }

                                echo '<tr>';
                                echo '<td>' . $idx++ . '</td>';
                                echo '<td><strong><a href="add_drivers.php?equipment_id=' . $eq_id . '" class="equipment-name-link" title="انقر لإدارة السائقين">' . htmlspecialchars($eq_label, ENT_QUOTES, 'UTF-8') . '</a></strong></td>';
                                echo '<td><span class="drivers-count-badge"><i class="fas fa-user"></i>' . $driver_count . '</span></td>';
                                echo '<td><div class="drivers-icons-wrap">' . $drivers_tooltip_html . '</div></td>';

                                echo '<td>';
                                if ($can_edit) {
                                    $eq_drivers_json = htmlspecialchars(json_encode($eq_drivers, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                    echo '<button type="button" class="btn btn-sm btn-primary openManageDriversModal"';
                                    echo ' data-equipment-id="' . $eq_id . '"';
                                    echo ' data-equipment-label="' . htmlspecialchars($eq_label, ENT_QUOTES, 'UTF-8') . '"';
                                    echo ' data-drivers="' . $eq_drivers_json . '">';
                                    echo '<i class="fas fa-random"></i> إدارة وتبديل السائقين';
                                    echo '</button>';
                                } else {
                                    echo '<span class="driver-no-permission">لا توجد صلاحية تعديل</span>';
                                }
                                echo '</td>';
                                    echo '</tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
            </div>
        </div>
    </div>

    <?php if ($can_edit): ?>
        <div class="modal fade manage-drivers-modal" id="manageDriversModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-exchange-alt"></i> إدارة السائقين للآلية: <span id="manageEqLabel">-</span></h5>
                        <div class="manage-modal-actions">
                            <button type="submit" form="manageDriversForm" class="btn btn-success btn-sm"><i class="fas fa-save"></i> اتمام التعديل</button>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                    </div>
                    <form method="post" id="manageDriversForm">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="manage_equipment_drivers">
                            <input type="hidden" name="equipment_id" id="manageEquipmentId" value="">

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">تاريخ التطبيق</label>
                                    <input type="date" class="form-control" name="effective_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">وردية السائقين المضافين</label>
                                    <select name="add_shift_type" class="form-control" required>
                                        <option value="D">نهاري فقط</option>
                                        <option value="N">ليلي فقط</option>
                                        <option value="B" selected>نهاري + ليلي</option>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="auto_replace_manage" name="auto_replace_manage" value="1">
                                        <label class="form-check-label" for="auto_replace_manage">استبدال تلقائي إذا كان السائق يعمل على آلية أخرى</label>
                                    </div>
                                </div>
                            </div>

                            <h6 class="mb-2">السائقون الحاليون على الآلية</h6>
                            <div class="table-responsive mb-3">
                                <table class="table table-bordered table-sm align-middle" id="manageCurrentDriversTable">
                                    <thead>
                                        <tr>
                                            <th>السائق</th>
                                            <th>الهاتف</th>
                                            <th>الوردية</th>
                                            <th>تاريخ البداية</th>
                                            <th>تاريخ النهاية</th>
                                            <th>الإجراء</th>
                                            <th>آلية النقل (عند الاختيار)</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <h6 class="mb-2">إضافة سائقين جدد لنفس الآلية</h6>
                            <select name="add_driver_ids[]" class="form-control" multiple size="8">
                                <?php foreach ($all_project_drivers as $drv): ?>
                                    <option value="<?php echo intval($drv['id']); ?>"><?php echo htmlspecialchars($drv['name'] . ' - ' . $drv['phone'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">يمكن اختيار أكثر من سائق. سيتم تجاهل السائقين غير المتاحين تلقائيًا.</small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> حفظ التحديثات</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
    <script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
    <!-- <script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script> -->
    <script>
        $(document).ready(function () {
            var projectDriversTable = $('#projectDriversTable').DataTable({
                responsive: false,
                scrollX: true,
                scrollCollapse: true,
                autoWidth: false,
                language: {
                    url: '/ems/assets/i18n/datatables/ar.json'
                }
            });

            setTimeout(function () {
                projectDriversTable.columns.adjust().draw(false);
            }, 100);

            $(window).on('resize', function () {
                projectDriversTable.columns.adjust();
            });

            $('#toggleAddDriverForm').on('click', function () {
                var $form = $('#addDriverForm');
                if ($form.hasClass('allforms-visible')) {
                    $form.removeClass('allforms-visible').slideUp(200);
                } else {
                    $form.addClass('allforms-visible').hide().slideDown(250);
                }
            });

            $('#cancelAddDriverForm').on('click', function () {
                $('#addDriverForm').removeClass('allforms-visible').slideUp(200);
            });

            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });

            var allEquipments = <?php
                $eq_map = [];
                foreach ($project_equipments as $eq_item) {
                    $eq_id = intval($eq_item['id']);
                    $eq_map[] = [
                        'id' => $eq_id,
                        'label' => trim((string) $eq_item['code']) . ' - ' . trim((string) $eq_item['name'])
                    ];
                }
                echo json_encode($eq_map, JSON_UNESCAPED_UNICODE);
            ?>;

            function renderManageDriversRows(drivers, currentEquipmentId) {
                var tbody = $('#manageCurrentDriversTable tbody');
                tbody.html('');
                if (!drivers || drivers.length === 0) {
                    tbody.append('<tr><td colspan="7" class="text-center text-muted">لا يوجد سائقون نشطون على هذه الآلية</td></tr>');
                    return;
                }

                drivers.forEach(function (driver) {
                    var relationId = parseInt(driver.id || 0, 10);
                    var driverId = parseInt(driver.driver_id || 0, 10);
                    var driverName = driver.driver_name || '-';
                    var driverPhone = driver.driver_phone || '-';
                    var shiftType = driver.shift_type || 'B';
                    var shiftLabel = shiftType === 'D' ? 'نهاري فقط' : (shiftType === 'N' ? 'ليلي فقط' : 'نهاري + ليلي');
                    var startDate = driver.start_date || '';
                    var endDate = (driver.end_date && driver.end_date !== '2099-12-31') ? driver.end_date : '';

                    var moveOptions = '<option value="">-- اختر آلية للنقل --</option>';
                    allEquipments.forEach(function (eq) {
                        if (parseInt(eq.id, 10) === parseInt(currentEquipmentId, 10)) {
                            return;
                        }
                        moveOptions += '<option value="' + eq.id + '">' + $('<div>').text(eq.label).html() + '</option>';
                    });

                    var row = '';
                    row += '<tr>';
                    row += '<td><a href="../Drivers/driver_profile.php?id=' + driverId + '" target="_blank" rel="noopener">' + $('<div>').text(driverName).html() + '</a></td>';
                    row += '<td>' + $('<div>').text(driverPhone).html() + '</td>';
                    row += '<td>' + shiftLabel + '</td>';
                    row += '<td><input type="date" class="form-control form-control-sm" name="existing_start_date[' + relationId + ']" value="' + startDate + '"></td>';
                    row += '<td><input type="date" class="form-control form-control-sm" name="existing_end_date[' + relationId + ']" value="' + endDate + '"></td>';
                    row += '<td>';
                    row += '<select class="form-control form-control-sm existing-action" name="existing_action[' + relationId + ']" data-relation-id="' + relationId + '">';
                    row += '<option value="keep" selected>إبقاء</option>';
                    row += '<option value="remove">حذف من الآلية</option>';
                    row += '<option value="move">نقل إلى آلية أخرى</option>';
                    row += '</select>';
                    row += '</td>';
                    row += '<td>';
                    row += '<select class="form-control form-control-sm move-target" name="move_to_equipment[' + relationId + ']" disabled>' + moveOptions + '</select>';
                    row += '</td>';
                    row += '</tr>';

                    tbody.append(row);
                });
            }

            $(document).on('change', '.existing-action', function () {
                var actionVal = $(this).val();
                var targetSelect = $(this).closest('tr').find('.move-target');
                if (actionVal === 'move') {
                    targetSelect.prop('disabled', false);
                } else {
                    targetSelect.prop('disabled', true).val('');
                }
            });

            $(document).on('click', '.openManageDriversModal', function () {
                var btn = $(this);
                var equipmentId = btn.data('equipment-id');
                var equipmentLabel = btn.data('equipment-label') || '-';
                var driversData = btn.data('drivers') || [];

                $('#manageEquipmentId').val(equipmentId);
                $('#manageEqLabel').text(equipmentLabel);
                renderManageDriversRows(driversData, equipmentId);

                var modal = new bootstrap.Modal(document.getElementById('manageDriversModal'));
                modal.show();
            });
        });
    </script>

</div><!-- /.ems-content -->
</div><!-- /.main -->

</body>

</html>
