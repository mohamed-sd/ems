<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
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

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15): كشوف الأعمدة والترحيل الذاتي
// (shift_type) وبُناة النطاق البديلة أُسقطوا — الأعمدة مضمونة بالترحيلات،
// {TENANT_SCOPE} والبوابة مسؤولا النطاق، والسوبر عبر forAllTenants المسجَّل.
$pd_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('project drivers super') : ems_tenant_db();

// صلاحيات الصفحة: نفس نمط شاشة التشغيل
$page_permissions = check_page_permissions($conn, 'movement/project_drivers.php');
$can_view = !empty($page_permissions['can_view']);
$can_edit = !empty($page_permissions['can_edit']);

if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض شاشة سائقي المشروع ❌', 'GOV-PERM-403', '');
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
    ems_gov_flash_redirect('../main/dashboard.php', '❌ لا يوجد مشروع مرتبط بالمستخدم في الجلسة', 'GOV-SCOPE-403', '');
    exit();
}

try {
    $project_rows = $pd_gate->scopedQuery(array(
        'scope' => array('project' => 'project'),
    ), "SELECT id, name, project_code FROM project WHERE {TENANT_SCOPE} AND id = ? AND status = 1", array($selected_project_id));
} catch (\Throwable $t) { $project_rows = array(); }
if (empty($project_rows)) {
    unset($_SESSION['operations_project_id']);
    ems_gov_flash_redirect('../main/dashboard.php', '❌ المشروع غير متاح أو غير نشط', 'GOV-SCOPE-403', '');
    exit();
}
$selected_project = $project_rows[0];

// شرط ارتباط سجل التشغيل بالمشروع (بلا شرط شركةٍ يدوي — البوابة تتكفّل به)
$pd_exists_ops = " AND EXISTS (
        SELECT 1 FROM operations o
        WHERE o.equipment = equipment_drivers.equipment_id
          AND o.project_id = $selected_project_id
    )";

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
                $employee_id = intval($driver_id_raw);
                if ($employee_id > 0) {
                    $normalized_driver_ids[] = $employee_id;
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
                $inserted_count = 0;
                $skipped_count = 0;
                try {
                    $pd_gate->runInTransaction(function ($g) use ($equipment_id, $selected_project_id, $normalized_driver_ids, $auto_replace, $start_date, $end_date, $shift_type, &$inserted_count, &$skipped_count) {
                        $equipment_check = $g->scopedQuery(array(
                            'scope' => array('o' => 'operations'),
                        ), "SELECT 1 AS x FROM operations o
                            WHERE {TENANT_SCOPE} AND o.equipment = ? AND o.project_id = ? LIMIT 1",
                            array($equipment_id, $selected_project_id));
                        if (empty($equipment_check)) {
                            throw new \Exception('الآلية المختارة ليست ضمن المشروع الحالي');
                        }

                        foreach ($normalized_driver_ids as $employee_id) {
                            $driver_check = $g->selectOne('employees', array(
                                'columns'  => array('id'),
                                'where'    => array('id' => $employee_id),
                                'whereRaw' => 'status = 1',
                            ));
                            if (!$driver_check) {
                                $skipped_count++;
                                continue;
                            }

                            $active_any = $g->scopedQuery(array(
                                'scope' => array('ed' => 'equipment_drivers'),
                                'enrich' => array('operations' => 'operations'),
                            ), "SELECT ed.id, ed.equipment_id
                                FROM equipment_drivers ed
                                WHERE {TENANT_SCOPE} AND ed.employee_id = ?
                                  AND ed.status = 1
                                  AND EXISTS (
                                      SELECT 1 FROM operations o
                                      WHERE o.equipment = ed.equipment_id
                                        AND o.project_id = ?
                                  )
                                LIMIT 1", array($employee_id, $selected_project_id));
                            $active_any_row = !empty($active_any) ? $active_any[0] : null;

                            if ($active_any_row) {
                                $current_equipment_id = intval($active_any_row['equipment_id']);
                                if ($current_equipment_id == $equipment_id) {
                                    $skipped_count++;
                                    continue;
                                }

                                if ($auto_replace === 1) {
                                    $g->update('equipment_drivers',
                                        array('status' => 0, 'end_date' => $start_date),
                                        array('employee_id' => $employee_id),
                                        "status = 1 AND EXISTS (
                                            SELECT 1 FROM operations o
                                            WHERE o.equipment = equipment_drivers.equipment_id
                                              AND o.project_id = " . intval($selected_project_id) . "
                                        )");
                                } else {
                                    $skipped_count++;
                                    continue;
                                }
                            }

                            $g->insert('equipment_drivers', array(
                                'equipment_id' => $equipment_id,
                                'employee_id'  => $employee_id,
                                'start_date'   => $start_date,
                                'end_date'     => $end_date !== '' ? $end_date : '2099-12-31',
                                'status'       => 1,
                                'shift_type'   => $shift_type,
                            ));
                            $inserted_count++;
                        }

                        if ($inserted_count === 0) {
                            throw new \Exception('لم يتم إضافة أي سائق. تحقق من الحالة الحالية للسائقين');
                        }
                    }, 'إضافة تشغيل سائقين لمشروع');

                    $msg = "تمت إضافة $inserted_count سائق بنجاح ✅";
                    if ($skipped_count > 0) {
                        $msg .= " (تم تخطي $skipped_count)";
                    }
                    $is_success = true;
                } catch (\Throwable $ex) {
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
                $processed_stop = 0;
                $processed_move = 0;
                $processed_date_update = 0;
                $added_count = 0;
                $skipped_count = 0;
                try {
                    $pd_gate->runInTransaction(function ($g) use ($equipment_id, $selected_project_id, $existing_action, $existing_start_date, $existing_end_date, $move_to_equipment, $add_driver_ids, $add_shift_type, $auto_replace_manage, $effective_date, $allowed_shift_types, &$processed_stop, &$processed_move, &$processed_date_update, &$added_count, &$skipped_count) {
                    $equipment_check = $g->scopedQuery(array(
                        'scope' => array('o' => 'operations'),
                    ), "SELECT 1 AS x FROM operations o
                        WHERE {TENANT_SCOPE} AND o.equipment = ? AND o.project_id = ? LIMIT 1",
                        array($equipment_id, $selected_project_id));
                    if (empty($equipment_check)) {
                        throw new \Exception('الآلية غير متاحة داخل المشروع الحالي');
                    }

                    foreach ($existing_action as $relation_id_raw => $action_value_raw) {
                        $relation_id = intval($relation_id_raw);
                        $action_value = trim((string) $action_value_raw);
                        if ($relation_id <= 0 || ($action_value !== 'keep' && $action_value !== 'remove' && $action_value !== 'move')) {
                            continue;
                        }

                        $rel_row = $g->selectOne('equipment_drivers', array(
                            'columns'  => array('id', 'employee_id', 'equipment_id', 'status', 'shift_type'),
                            'where'    => array('id' => $relation_id, 'equipment_id' => $equipment_id),
                            'whereRaw' => 'status = 1',
                        ));
                        if (!$rel_row) {
                            continue;
                        }
                        $employee_id = intval($rel_row['employee_id']);
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
                            try {
                                $g->update('equipment_drivers',
                                    array('start_date' => $row_start, 'end_date' => $target_end),
                                    array('id' => $relation_id), 'status = 1');
                                $processed_date_update++;
                            } catch (\Throwable $t) {}
                            continue;
                        }

                        $close_date = $normalized_row_end !== '' ? $normalized_row_end : $effective_date;
                        if (strtotime($close_date) < strtotime($row_start)) {
                            throw new \Exception('تاريخ الإيقاف/النقل لا يمكن أن يكون قبل تاريخ البداية');
                        }

                        $g->update('equipment_drivers',
                            array('status' => 0, 'end_date' => $close_date),
                            array('id' => $relation_id), 'status = 1');

                        if ($action_value === 'remove') {
                            $processed_stop++;
                            continue;
                        }

                        $target_equipment_id = isset($move_to_equipment[$relation_id_raw]) ? intval($move_to_equipment[$relation_id_raw]) : 0;
                        if ($target_equipment_id <= 0 || $target_equipment_id === $equipment_id) {
                            continue;
                        }

                        $target_check = $g->scopedQuery(array(
                            'scope' => array('o' => 'operations'),
                        ), "SELECT 1 AS x FROM operations o
                            WHERE {TENANT_SCOPE} AND o.equipment = ? AND o.project_id = ? LIMIT 1",
                            array($target_equipment_id, $selected_project_id));
                        if (empty($target_check)) {
                            continue;
                        }

                        try {
                            $g->insert('equipment_drivers', array(
                                'equipment_id' => $target_equipment_id,
                                'employee_id'  => $employee_id,
                                'start_date'   => $close_date,
                                'end_date'     => '2099-12-31',
                                'status'       => 1,
                                'shift_type'   => $shift_type_existing,
                            ));
                            $processed_move++;
                        } catch (\Throwable $t) {}
                    }

                    $normalized_new_driver_ids = [];
                    foreach ($add_driver_ids as $driver_id_raw) {
                        $employee_id = intval($driver_id_raw);
                        if ($employee_id > 0) {
                            $normalized_new_driver_ids[] = $employee_id;
                        }
                    }
                    $normalized_new_driver_ids = array_values(array_unique($normalized_new_driver_ids));

                    foreach ($normalized_new_driver_ids as $employee_id) {
                        $driver_check = $g->selectOne('employees', array(
                            'columns'  => array('id'),
                            'where'    => array('id' => $employee_id),
                            'whereRaw' => 'status = 1',
                        ));
                        if (!$driver_check) {
                            $skipped_count++;
                            continue;
                        }

                        $active_any = $g->scopedQuery(array(
                            'scope' => array('ed' => 'equipment_drivers'),
                            'enrich' => array('operations' => 'operations'),
                        ), "SELECT ed.id, ed.equipment_id
                            FROM equipment_drivers ed
                            WHERE {TENANT_SCOPE} AND ed.employee_id = ?
                              AND ed.status = 1
                              AND EXISTS (
                                  SELECT 1 FROM operations o
                                  WHERE o.equipment = ed.equipment_id
                                    AND o.project_id = ?
                              )
                            LIMIT 1", array($employee_id, $selected_project_id));
                        $active_any_row = !empty($active_any) ? $active_any[0] : null;

                        if ($active_any_row) {
                            $current_equipment_id = intval($active_any_row['equipment_id']);
                            if ($current_equipment_id == $equipment_id) {
                                $skipped_count++;
                                continue;
                            }

                            if ($auto_replace_manage === 1) {
                                $g->update('equipment_drivers',
                                    array('status' => 0, 'end_date' => $effective_date),
                                    array('employee_id' => $employee_id),
                                    "status = 1 AND EXISTS (
                                        SELECT 1 FROM operations o
                                        WHERE o.equipment = equipment_drivers.equipment_id
                                          AND o.project_id = " . intval($selected_project_id) . "
                                    )");
                            } else {
                                $skipped_count++;
                                continue;
                            }
                        }

                        try {
                            $g->insert('equipment_drivers', array(
                                'equipment_id' => $equipment_id,
                                'employee_id'  => $employee_id,
                                'start_date'   => $effective_date,
                                'end_date'     => '2099-12-31',
                                'status'       => 1,
                                'shift_type'   => $add_shift_type,
                            ));
                            $added_count++;
                        } catch (\Throwable $t) {}
                    }
                    }, 'إدارة توزيع سائقي آلية');

                    $msg = "تم تحديث التوزيع بنجاح ✅ (تعديل تواريخ: $processed_date_update | إيقاف: $processed_stop | نقل: $processed_move | إضافة: $added_count";
                    if ($skipped_count > 0) {
                        $msg .= " | تخطي: $skipped_count";
                    }
                    $msg .= ")";
                    $is_success = true;
                } catch (\Throwable $ex) {
                    $msg = $ex->getMessage() . ' ❌';
                    $is_success = false;
                }
            }
        } elseif ($action === 'stop_driver') {
            if ($relation_id <= 0) {
                $msg = 'بيانات غير صحيحة ❌';
                $is_success = false;
            } else {
                try {
                    $check_rows = $pd_gate->scopedQuery(array(
                        'scope' => array('ed' => 'equipment_drivers'),
                        'enrich' => array('operations' => 'operations'),
                    ), "SELECT ed.id FROM equipment_drivers ed
                        WHERE {TENANT_SCOPE} AND ed.id = ?
                          AND ed.status = 1
                          AND EXISTS (
                              SELECT 1 FROM operations o
                              WHERE o.equipment = ed.equipment_id
                                AND o.project_id = ?
                          )
                        LIMIT 1", array($relation_id, $selected_project_id));
                } catch (\Throwable $t) { $check_rows = array(); }

                if (empty($check_rows)) {
                    $msg = 'السجل غير موجود أو خارج نطاق المشروع ❌';
                    $is_success = false;
                } else {
                    try {
                        $affected = $pd_gate->update('equipment_drivers',
                            array('status' => 0, 'end_date' => date('Y-m-d')),
                            array('id' => $relation_id), 'status = 1');
                    } catch (\Throwable $t) { $affected = 0; }
                    if ($affected > 0) {
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
                try {
                    $pd_gate->runInTransaction(function ($g) use ($relation_id, $new_equipment_id, $move_start_date, $selected_project_id) {
                        $rel_rows = $g->scopedQuery(array(
                            'scope' => array('ed' => 'equipment_drivers'),
                            'enrich' => array('operations' => 'operations'),
                        ), "SELECT ed.id, ed.employee_id, ed.equipment_id, ed.status, ed.shift_type
                            FROM equipment_drivers ed
                            WHERE {TENANT_SCOPE} AND ed.id = ?
                              AND EXISTS (
                                  SELECT 1 FROM operations o
                                  WHERE o.equipment = ed.equipment_id
                                    AND o.project_id = ?
                              )
                            LIMIT 1", array($relation_id, $selected_project_id));
                        if (empty($rel_rows)) {
                            throw new \Exception('السجل المراد نقله غير موجود داخل المشروع');
                        }

                        $rel_row = $rel_rows[0];
                        $employee_id = intval($rel_row['employee_id']);
                        $old_equipment_id = intval($rel_row['equipment_id']);
                        $old_status = intval($rel_row['status']);

                        if ($old_equipment_id === $new_equipment_id) {
                            throw new \Exception('الآلية الجديدة هي نفسها الآلية الحالية');
                        }

                        $equip_check = $g->scopedQuery(array(
                            'scope' => array('o' => 'operations'),
                        ), "SELECT 1 AS x FROM operations o
                            WHERE {TENANT_SCOPE} AND o.equipment = ? AND o.project_id = ? LIMIT 1",
                            array($new_equipment_id, $selected_project_id));
                        if (empty($equip_check)) {
                            throw new \Exception('الآلية الجديدة ليست ضمن المشروع المحدد');
                        }

                        if ($old_status === 1) {
                            $g->update('equipment_drivers',
                                array('status' => 0, 'end_date' => $move_start_date),
                                array('id' => $relation_id), 'status = 1');
                        }

                        $active_check = $g->scopedQuery(array(
                            'scope' => array('ed' => 'equipment_drivers'),
                            'enrich' => array('operations' => 'operations'),
                        ), "SELECT ed.id
                            FROM equipment_drivers ed
                            WHERE {TENANT_SCOPE} AND ed.employee_id = ?
                              AND ed.status = 1
                              AND EXISTS (
                                  SELECT 1 FROM operations o
                                  WHERE o.equipment = ed.equipment_id
                                    AND o.project_id = ?
                              )
                            LIMIT 1", array($employee_id, $selected_project_id));
                        if (!empty($active_check)) {
                            throw new \Exception('السائق يعمل حاليا على آلية أخرى داخل المشروع');
                        }

                        $existing_shift_type = isset($rel_row['shift_type']) ? strval($rel_row['shift_type']) : 'B';
                        if (!in_array($existing_shift_type, array('D', 'N', 'B'), true)) {
                            $existing_shift_type = 'B';
                        }
                        $g->insert('equipment_drivers', array(
                            'equipment_id' => $new_equipment_id,
                            'employee_id'  => $employee_id,
                            'start_date'   => $move_start_date,
                            'end_date'     => '2099-12-31',
                            'status'       => 1,
                            'shift_type'   => $existing_shift_type,
                        ));
                    }, 'نقل سائق بين آليتي مشروع');

                    $msg = 'تم نقل السائق وتشغيله على الآلية الجديدة ✅';
                    $is_success = true;
                } catch (\Throwable $ex) {
                    $msg = $ex->getMessage() . ' ❌';
                    $is_success = false;
                }
            }
        }
    }
}

try {
    $project_equipments = $pd_gate->scopedQuery(array(
        'scope' => array('o' => 'operations', 'e' => 'equipments'),
    ), "SELECT DISTINCT e.id, e.code, e.name
        FROM operations o
        INNER JOIN equipments e ON e.id = o.equipment
        WHERE {TENANT_SCOPE} AND o.project_id = ?
          AND o.status <> 0
        ORDER BY e.code ASC, e.name ASC", array($selected_project_id));
} catch (\Throwable $t) { $project_equipments = array(); }

// خطوتان مُنطَّقتان بدل NOT EXISTS ذي الـINNER JOIN (قاعدة enrich = LEFT حصرًا):
// (1) المنشغلون في المشروع بعزلٍ كامل (ed+o)، (2) استثناؤهم بمعاملات — دلالة الأصل حرفيًّا.
$pd_busy_ids = array();
try {
    $busy_rows = $pd_gate->scopedQuery(array(
        'scope' => array('ed' => 'equipment_drivers', 'o' => 'operations'),
    ), "SELECT DISTINCT ed.employee_id
        FROM equipment_drivers ed
        INNER JOIN operations o ON o.equipment = ed.equipment_id
        WHERE {TENANT_SCOPE} AND ed.status = 1 AND o.project_id = ?", array($selected_project_id));
    foreach ($busy_rows as $br) { $pd_busy_ids[] = intval($br['employee_id']); }
} catch (\Throwable $t) { $pd_busy_ids = array(); }

$avail_extra = ''; $avail_params = array();
if (!empty($pd_busy_ids)) {
    $avail_extra = " AND d.id NOT IN (" . implode(',', array_fill(0, count($pd_busy_ids), '?')) . ")";
    $avail_params = $pd_busy_ids;
}
try {
    $available_drivers = $pd_gate->scopedQuery(array(
        'scope' => array('d' => 'employees'),
    ), "SELECT d.id, d.name, d.phone
        FROM employees d
        WHERE {TENANT_SCOPE}
          AND d.status = 1" . ems_operation_types_in_sql($conn, 'd') . "$avail_extra
        ORDER BY d.name ASC", $avail_params);
} catch (\Throwable $t) { $available_drivers = array(); }

try {
    $active_assignments_rows = $pd_gate->scopedQuery(array(
        'scope' => array('ed' => 'equipment_drivers', 'd' => 'employees'),
        'enrich' => array('operations' => 'operations'),
    ), "SELECT ed.id, ed.equipment_id, ed.employee_id, ed.start_date, ed.end_date, ed.status,
               ed.shift_type, d.name AS driver_name, d.phone AS driver_phone
        FROM equipment_drivers ed
        INNER JOIN employees d ON d.id = ed.employee_id
        WHERE {TENANT_SCOPE} AND ed.status = 1
          AND EXISTS (
              SELECT 1
              FROM operations o
              WHERE o.equipment = ed.equipment_id
                AND o.project_id = ?
          )
        ORDER BY ed.id DESC", array($selected_project_id));
} catch (\Throwable $t) { $active_assignments_rows = array(); }

$drivers_by_equipment = [];
foreach ($active_assignments_rows as $driver_row) {
    $eq_id = intval($driver_row['equipment_id']);
    if (!isset($drivers_by_equipment[$eq_id])) {
        $drivers_by_equipment[$eq_id] = [];
    }
    $drivers_by_equipment[$eq_id][] = $driver_row;
}

try {
    $all_project_drivers = $pd_gate->scopedQuery(array(
        'scope' => array('d' => 'employees'),
    ), "SELECT DISTINCT d.id, d.name, d.phone
        FROM employees d
        WHERE {TENANT_SCOPE}
          AND d.status = 1" . ems_operation_types_in_sql($conn, 'd') . "
        ORDER BY d.name ASC");
} catch (\Throwable $t) { $all_project_drivers = array(); }

try {
    $drivers_rows = $pd_gate->scopedQuery(array(
        'scope'  => array('ed' => 'equipment_drivers', 'e' => 'equipments', 'd' => 'employees'),
        'enrich' => array('operations' => 'operations'),
    ), "SELECT ed.id, ed.equipment_id, ed.employee_id, ed.start_date, ed.end_date, ed.status,
               ed.shift_type,
               e.code AS equipment_code, e.name AS equipment_name,
               d.name AS driver_name, d.phone AS driver_phone
        FROM equipment_drivers ed
        INNER JOIN equipments e ON e.id = ed.equipment_id
        INNER JOIN employees d ON d.id = ed.employee_id
        WHERE {TENANT_SCOPE} AND EXISTS (
            SELECT 1
            FROM operations o
            WHERE o.equipment = ed.equipment_id
              AND o.project_id = ?
        )
        ORDER BY ed.status DESC, ed.id DESC", array($selected_project_id));
} catch (\Throwable $t) { $drivers_rows = array(); }

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

$page_title = "توزيع المشغلين";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
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
        min-width: 1200px;
        width: 100%;
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
        background: var(--c-eef6ff, #eef6ff);
        color: var(--c-0b4c8c, #0b4c8c);
        border: 1px solid var(--c-cfe2ff, #cfe2ff);
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
        color: var(--c-0c1c3e, #0c1c3e);
        background: var(--c-f7f9fc, #f7f9fc);
        border: 1px solid var(--c-d7dee8, #d7dee8);
        text-decoration: none;
        transition: all .2s ease;
    }

    .driver-icon-link:hover {
        background: var(--c-0c1c3e, #0c1c3e);
        color: var(--c-surface, #ffffff);
        border-color: var(--c-0c1c3e, #0c1c3e);
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
        color: var(--c-0c1c3e, #0c1c3e);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
        padding: 4px 8px;
        border-radius: 6px;
    }

    .equipment-name-link:hover {
        color: var(--c-e8b800, #e8b800);
        text-decoration: underline;
        background: var(--c-rgba232184001, rgba(232, 184, 0, 0.1));
    }

    .equipment-name-link:hover::before {
        content: "🔗 ";
    }
</style>

<div class="main movement-page movement-drivers-page">


    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_icon       = 'fas fa-id-badge';
    $header_title_html = 'إدارة سائقي المشروع
             <i class="fas fa-project-diagram"></i>
           ' . htmlspecialchars($selected_project['name']);
    $header_actions = array();
    if ($can_edit) {
        $header_actions[] = array('id' => 'toggleAddDriverForm', 'class' => 'movement-topbar-btn movement-topbar-btn-primary add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة تشغيل سائق');
    }
    $header_actions[] = array('href' => 'movement_operations.php?project_id=' . intval($selected_project_id), 'class' => 'movement-topbar-btn', 'icon' => 'fas fa-route', 'label' => 'الحركة والتشغيل');
    $header_actions[] = array('href' => 'move_oprators.php?project_id=' . intval($selected_project_id), 'class' => 'movement-topbar-btn', 'icon' => 'fas fa-cogs', 'label' => 'إدارة التشغيل');
    $header_actions[] = array('href' => '../main/dashboard.php', 'class' => 'movement-topbar-btn', 'icon' => 'fas fa-home', 'label' => 'لوحة التحكم');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include(__DIR__ . '/../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا سائقين مسندين لآليات هذا المشروع بعد', 'أضف أول تشغيل سائق بزر «إضافة تشغيل سائق» في رأس الشاشة');
    ?>

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
        <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_driver_assignment">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user-plus"></i> تشغيل سائق جديد</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div>
                                <label for="emsf_741_3b518"><i class="fas fa-id-card"></i> السائقون * (يمكن اختيار أكثر من سائق)</label>
                                <select name="driver_ids[]" multiple required size="8" id="emsf_741_3b518">
                                    <?php foreach ($available_drivers as $drv): ?>
                                        <option value="<?php echo intval($drv['id']); ?>">
                                            <?php echo htmlspecialchars($drv['name'] . ' - ' . $drv['phone'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">استخدم Ctrl أو Shift لاختيار أكثر من سائق.</small>
                            </div>

                            <div>
                                <label for="emsf_742_ad26e"><i class="fas fa-truck"></i> الآلية *</label>
                                <select name="equipment_id" required id="emsf_742_ad26e">
                                    <option value="">-- اختر الآلية --</option>
                                    <?php foreach ($project_equipments as $eq): ?>
                                        <?php $eqLabel = trim((string) $eq['code']) . ' - ' . trim((string) $eq['name']); ?>
                                        <option value="<?php echo intval($eq['id']); ?>">
                                            <?php echo htmlspecialchars($eqLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="emsf_743_02e54"><i class="fas fa-calendar-plus"></i> تاريخ البداية *</label>
                                <input type="date" name="start_date" id="emsf_743_02e54" required value="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <div>
                                <label for="emsf_744_21c43"><i class="fas fa-calendar-times"></i> تاريخ النهاية (اختياري)</label>
                                <input type="date" name="end_date" value="" id="emsf_744_21c43">
                            </div>

                            <div>
                                <label for="emsf_745_ba2a3"><i class="fas fa-sync-alt"></i> نظام الوردية *</label>
                                <select name="shift_type" required id="emsf_745_ba2a3">
                                    <option value="D">نهاري فقط</option>
                                    <option value="N">ليلي فقط</option>
                                    <option value="B" selected>نهاري + ليلي</option>
                                </select>
                            </div>

                            <div class="driver-form-check-row">
                                <input type="checkbox" id="auto_replace" name="auto_replace" value="1" checked>
                                <label for="auto_replace" class="driver-form-check-label">إيقاف أي تشغيل نشط لنفس السائق
                                    داخل المشروع تلقائيا</label>
                            </div>

                            <div class="driver-form-actions">
                                <button type="button" id="cancelAddDriverForm" class="btn btn-secondary">إلغاء</button>
                                <button type="submit" class="btn btn-primary">حفظ التشغيل</button>
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
                <div class="table-container">
                    <table id="projectDriversTable" class="display nowrap table-full-width">
                        <thead>
                            <tr>
                                <th>الإجراءات</th>
                                <th>الآلية</th>
                                <th>عدد السائقين</th>
                                <th>السائقون</th>
                                                <!-- U10-B12: النواة الحاكمة (الخلايا يحشوها ui-unification.js) -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ السجل وبأي صفة">المنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="السجل الذي تولد عنه">المرجع الأب</th>
</tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($project_equipments as $eq) {
                                $eq_id = intval($eq['id']);
                                $eq_label = trim((string) $eq['code']) . ' - ' . trim((string) $eq['name']);
                                $eq_drivers = isset($drivers_by_equipment[$eq_id]) ? $drivers_by_equipment[$eq_id] : [];
                                $driver_count = count($eq_drivers);

                                $drivers_tooltip_html = '';
                                if ($driver_count > 0) {
                                    foreach ($eq_drivers as $drow) {
                                        $employee_id = intval($drow['employee_id']);
                                        $driver_name = isset($drow['driver_name']) ? $drow['driver_name'] : '';
                                        $driver_phone = isset($drow['driver_phone']) ? $drow['driver_phone'] : '';
                                        $shift_label = get_shift_type_label(isset($drow['shift_type']) ? $drow['shift_type'] : 'B');
                                        $tooltip_text = 'الاسم: ' . $driver_name . ' | الهاتف: ' . $driver_phone . ' | الوردية: ' . $shift_label;
                                        $drivers_tooltip_html .= '<a class="driver-icon-link" href="../Employees/employee_profile.php?id=' . $employee_id . '" title="' . htmlspecialchars($tooltip_text, ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="tooltip" data-bs-placement="top">';
                                        $drivers_tooltip_html .= '<i class="fas fa-user"></i>';
                                        $drivers_tooltip_html .= '</a>';
                                    }
                                } else {
                                    $drivers_tooltip_html = '<span class="text-muted">لا يوجد سائقون نشطون</span>';
                                }

                                echo '<tr>';
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
                                echo '</td> ';
                                echo '<td><strong><a href="add_drivers.php?equipment_id=' . $eq_id . '" class="equipment-name-link" title="انقر لإدارة السائقين">' . htmlspecialchars($eq_label, ENT_QUOTES, 'UTF-8') . '</a></strong></td>';
                                echo '<td><span class="drivers-count-badge"><i class="fas fa-user"></i>' . $driver_count . '</span></td>';
                                echo '<td><div class="drivers-icons-wrap">' . $drivers_tooltip_html . '</div></td>';

                                echo '';
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
                            <button type="submit" form="manageDriversForm" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> اتمام التعديل</button>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                    </div>
                    <?php /* الخطّافُ `ems-form` **جِلدٌ بلا طيّ** — المودالُ يدير
                         ظهورَه بنفسه. ⛔ **ولا `.form-grid` هنا**: شبكتُها خمسةُ
                         أعمدةٍ صلبةٍ بعرضِ **الشاشةِ** لا المودالِ، فتزحم حقولَ
                         مودالٍ ضيّق. و`.form-group` يأخذ الحبّةَ الذهبيّةَ ولا
                         يفرض عرضًا — فصفُّ بوتستراب يبقى هو المُخطِّط.
                         وجدولُ منتقي السائقين أسفلَه لم يُمَسّ. */ ?>
                    <form method="post" id="manageDriversForm" class="ems-form">
        <?= csrf_field() ?>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="manage_equipment_drivers">
                            <input type="hidden" name="equipment_id" id="manageEquipmentId" value="">

                            <div class="row g-3 mb-3">
                                <div class="col-md-4 form-group">
                                    <label for="emsf_mgr_eff">تاريخ التطبيق *</label>
                                    <input type="date" id="emsf_mgr_eff" name="effective_date" aria-label="تاريخ تطبيق تغيير السائقين" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="emsf_746_ab2a1">وردية السائقين المضافين *</label>
                                    <select name="add_shift_type" required id="emsf_746_ab2a1">
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
                            <select name="add_driver_ids[]" class="form-control" aria-label="سائقون جدد يضافون لهذه الآلية" multiple size="8">
                                <?php foreach ($all_project_drivers as $drv): ?>
                                    <option value="<?php echo intval($drv['id']); ?>"><?php echo htmlspecialchars($drv['name'] . ' - ' . $drv['phone'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">يمكن اختيار أكثر من سائق. سيتم تجاهل السائقين غير المتاحين تلقائيا.</small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ التحديثات</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
    <script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function () {
            // UXW-01 ⑤: تهيئةُ الجدولِ المركزيةُ في assets/js/ui-unification.js
            // (التعريبُ وعرضُ الأعمدةِ منها) — والتمريرُ الأفقيُّ من غلافِ .table-container.
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
                    var driverId = parseInt(driver.employee_id || 0, 10);
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
                    row += '<td><a href="../Employees/employee_profile.php?id=' + driverId + '" target="_blank" rel="noopener">' + $('<div>').text(driverName).html() + '</a></td>';
                    row += '<td>' + $('<div>').text(driverPhone).html() + '</td>';
                    row += '<td>' + shiftLabel + '</td>';
                    row += '<td><input type="date" class="form-control form-control-sm" aria-label="تاريخ بداية تشغيل السائق" name="existing_start_date[' + relationId + ']" value="' + startDate + '"></td>';
                    row += '<td><input type="date" class="form-control form-control-sm" aria-label="تاريخ نهاية تشغيل السائق" name="existing_end_date[' + relationId + ']" value="' + endDate + '"></td>';
                    row += '<td>';
                    row += '<select class="form-control form-control-sm existing-action" aria-label="الإجراء على تشغيل السائق: إبقاء أو حذف أو نقل" name="existing_action[' + relationId + ']" data-relation-id="' + relationId + '">';
                    row += '<option value="keep" selected>إبقاء</option>';
                    row += '<option value="remove">حذف من الآلية</option>';
                    row += '<option value="move">نقل إلى آلية أخرى</option>';
                    row += '</select>';
                    row += '</td>';
                    row += '<td>';
                    row += '<select class="form-control form-control-sm move-target" aria-label="الآلية التي ينقل إليها السائق" name="move_to_equipment[' + relationId + ']" disabled>' + moveOptions + '</select>';
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
