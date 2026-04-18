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
$project_client_column = db_table_has_column($conn, 'project', 'client_id') ? 'client_id' : 'company_client_id';
$project_has_company_id = db_table_has_column($conn, 'project', 'company_id');
$operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
$equipment_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');
$drivers_has_company = db_table_has_column($conn, 'drivers', 'company_id');
$drivers_has_status = db_table_has_column($conn, 'drivers', 'status');

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
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

// صلاحيات الصفحة: نفس صلاحيات شاشة التشغيل
$current_role_id = intval($_SESSION['user']['role']);
$module_query = "SELECT m.id
                 FROM modules m
                 LEFT JOIN role_permissions rp
                        ON rp.module_id = m.id
                     AND rp.role_id = $current_role_id
                 WHERE (
                        m.code = 'movement/move_oprators.php'
                     OR m.code = 'movement/oprators.php'
                     OR m.code = 'movement/oprators'
                     OR m.code LIKE '%movement/move_oprators.php%'
                     OR m.code LIKE '%movement/oprators.php%'
                 )
                   AND m.owner_role_id IN ($current_role_id, -1)
                 ORDER BY
                    CASE WHEN rp.module_id IS NOT NULL THEN 0 ELSE 1 END,
                    CASE
                        WHEN m.owner_role_id = $current_role_id THEN 0
                        WHEN m.owner_role_id = -1 THEN 1
                        ELSE 2
                    END,
                    m.id ASC
                 LIMIT 1";
$module_result = $conn->query($module_query);
$module_info = $module_result ? $module_result->fetch_assoc() : null;
$module_id = $module_info ? intval($module_info['id']) : 0;

$can_view = false;
$can_edit = false;

if ($module_id > 0) {
    $perms = get_module_permissions($conn, $module_id);
    $can_view = !empty($perms['can_view']);
    $can_edit = !empty($perms['can_edit']);
}

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
            $driver_id = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 0;
            $equipment_id = isset($_POST['equipment_id']) ? intval($_POST['equipment_id']) : 0;
            $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
            $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
            $auto_replace = isset($_POST['auto_replace']) ? intval($_POST['auto_replace']) : 0;

            $start_obj = DateTime::createFromFormat('Y-m-d', $start_date);
            $start_valid = $start_obj && $start_obj->format('Y-m-d') === $start_date;

            $end_valid = true;
            if ($end_date !== '') {
                $end_obj = DateTime::createFromFormat('Y-m-d', $end_date);
                $end_valid = $end_obj && $end_obj->format('Y-m-d') === $end_date;
            }

            if ($driver_id <= 0 || $equipment_id <= 0 || !$start_valid || !$end_valid) {
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
                                            LIMIT 1";
                    $equipment_check_res = mysqli_query($conn, $equipment_check_sql);
                    if (!$equipment_check_res || mysqli_num_rows($equipment_check_res) === 0) {
                        throw new Exception('الآلية المختارة ليست ضمن المشروع الحالي');
                    }

                    $driver_check_sql = "SELECT d.id FROM drivers d
                                         WHERE d.id = $driver_id
                                           $driver_company_scope
                                           $driver_status_scope
                                         LIMIT 1";
                    $driver_check_res = mysqli_query($conn, $driver_check_sql);
                    if (!$driver_check_res || mysqli_num_rows($driver_check_res) === 0) {
                        throw new Exception('السائق غير متاح أو خارج نطاق الشركة');
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
                                         )
                                       LIMIT 1";
                    $active_any_res = mysqli_query($conn, $active_any_sql);
                    $active_any_row = ($active_any_res && mysqli_num_rows($active_any_res) > 0) ? mysqli_fetch_assoc($active_any_res) : null;

                    if ($active_any_row) {
                        $current_equipment_id = intval($active_any_row['equipment_id']);
                        if ($current_equipment_id == $equipment_id) {
                            throw new Exception('السائق يعمل بالفعل على هذه الآلية');
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
                                            )";
                            mysqli_query($conn, $close_sql);
                        } else {
                            throw new Exception('السائق يعمل حالياً على آلية أخرى، فعّل خيار الاستبدال أو أوقفه أولاً');
                        }
                    }

                    $insert_company_col = (!$is_super_admin && $equipment_drivers_has_company) ? ", company_id" : "";
                    $insert_company_val = (!$is_super_admin && $equipment_drivers_has_company) ? ", $company_id" : "";
                    $end_sql = $end_date !== '' ? "'" . mysqli_real_escape_string($conn, $end_date) . "'" : "'2099-12-31'";
                    $insert_sql = "INSERT INTO equipment_drivers (equipment_id, driver_id, start_date, end_date, status$insert_company_col)
                                   VALUES ($equipment_id, $driver_id, '$start_date', $end_sql, 1$insert_company_val)";

                    if (!mysqli_query($conn, $insert_sql)) {
                        throw new Exception('فشل إضافة تشغيل السائق');
                    }

                    mysqli_commit($conn);
                    $msg = 'تم إضافة تشغيل السائق بنجاح ✅';
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
                                FROM equipment_drivers ed
                                WHERE ed.id = $relation_id
                                  $ed_company_scope
                                  AND EXISTS (
                                      SELECT 1 FROM operations o
                                      WHERE o.equipment = ed.equipment_id
                                        AND o.project_id = $selected_project_id
                                        $operations_company_scope
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
                                           )
                                         LIMIT 1";
                    $active_check_res = mysqli_query($conn, $active_check_sql);
                    if ($active_check_res && mysqli_num_rows($active_check_res) > 0) {
                        throw new Exception('السائق يعمل حالياً على آلية أخرى داخل المشروع');
                    }

                    $insert_company_col = (!$is_super_admin && $equipment_drivers_has_company) ? ", company_id" : "";
                    $insert_company_val = (!$is_super_admin && $equipment_drivers_has_company) ? ", $company_id" : "";
                    $insert_sql = "INSERT INTO equipment_drivers
                                   (equipment_id, driver_id, start_date, end_date, status$insert_company_col)
                                   VALUES ($new_equipment_id, $driver_id, '$move_start_date', '2099-12-31', 1$insert_company_val)";

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
                          ORDER BY d.name ASC";
$available_drivers_result = mysqli_query($conn, $available_drivers_sql);
$available_drivers = [];
if ($available_drivers_result) {
    while ($drv = mysqli_fetch_assoc($available_drivers_result)) {
        $available_drivers[] = $drv;
    }
}

$drivers_sql = "SELECT ed.id, ed.equipment_id, ed.driver_id, ed.start_date, ed.end_date, ed.status,
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
                )
                $ed_company_scope
                ORDER BY ed.status DESC, ed.id DESC";
$drivers_result = mysqli_query($conn, $drivers_sql);

$page_title = "سائقو المشروع";
include '../inheader.php';
include '../insidebar.php';
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-id-badge"></i></div>
            <div>
                <div>سائقو المشروع: <?php echo htmlspecialchars($selected_project['name']); ?></div>
                <?php if (!empty($selected_project['project_code'])) { ?>
                    <small class="page-subtitle">كود المشروع: <?php echo htmlspecialchars($selected_project['project_code']); ?></small>
                <?php } ?>
            </div>
        </h1>
        <div class="page-header-actions">
            <a href="move_oprators.php?project_id=<?php echo intval($selected_project_id); ?>" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع للتشغيل
            </a>
            <?php if ($can_edit): ?>
            <a href="javascript:void(0)" id="toggleAddDriverForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة تشغيل سائق
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($msg !== ''): ?>
        <div class="success-message <?php echo $is_success ? 'is-success' : 'is-error'; ?>">
            <i class="fas <?php echo $is_success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($can_edit): ?>
    <form id="addDriverForm" action="" method="post" class="form-hidden" style="display:none; margin-bottom: 16px;">
        <input type="hidden" name="action" value="add_driver_assignment">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-user-plus"></i> تشغيل سائق جديد</h5>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-id-card"></i> السائق *</label>
                        <select name="driver_id" required>
                            <option value="">-- اختر السائق --</option>
                            <?php foreach ($available_drivers as $drv): ?>
                                <option value="<?php echo intval($drv['id']); ?>">
                                    <?php echo htmlspecialchars($drv['name'] . ' - ' . $drv['phone'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label><i class="fas fa-truck"></i> الآلية *</label>
                        <select name="equipment_id" required>
                            <option value="">-- اختر الآلية --</option>
                            <?php foreach ($project_equipments as $eq): ?>
                                <?php $eqLabel = trim((string) $eq['code']) . ' - ' . trim((string) $eq['name']); ?>
                                <option value="<?php echo intval($eq['id']); ?>"><?php echo htmlspecialchars($eqLabel, ENT_QUOTES, 'UTF-8'); ?></option>
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

                    <div style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" id="auto_replace" name="auto_replace" value="1" checked>
                        <label for="auto_replace" style="margin:0;">إيقاف أي تشغيل نشط لنفس السائق داخل المشروع تلقائيًا</label>
                    </div>

                    <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content:center;">
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
            <h5><i class="fas fa-users"></i> السائقون المرتبطون بآليات المشروع</h5>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="projectDriversTable" class="display nowrap" style="width:100%;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>السائق</th>
                            <th>الهاتف</th>
                            <th>الآلية الحالية</th>
                            <th>تاريخ البداية</th>
                            <th>تاريخ النهاية</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $idx = 1;
                        if ($drivers_result) {
                            while ($row = mysqli_fetch_assoc($drivers_result)) {
                                $is_active = intval($row['status']) === 1;
                                echo '<tr>';
                                echo '<td>' . $idx++ . '</td>';
                                echo '<td><strong>' . htmlspecialchars($row['driver_name'], ENT_QUOTES, 'UTF-8') . '</strong></td>';
                                echo '<td>' . htmlspecialchars($row['driver_phone'], ENT_QUOTES, 'UTF-8') . '</td>';
                                echo '<td>' . htmlspecialchars($row['equipment_code'] . ' - ' . $row['equipment_name'], ENT_QUOTES, 'UTF-8') . '</td>';
                                echo '<td>' . htmlspecialchars($row['start_date'], ENT_QUOTES, 'UTF-8') . '</td>';
                                echo '<td>' . htmlspecialchars($row['end_date'], ENT_QUOTES, 'UTF-8') . '</td>';
                                echo '<td>';
                                if ($is_active) {
                                    echo '<span class="status-pill status-running">يعمل</span>';
                                } else {
                                    echo '<span class="status-pill status-idle">موقوف</span>';
                                }
                                echo '</td>';

                                echo '<td>';
                                if ($can_edit) {
                                    if ($is_active) {
                                        echo '<form method="post" style="display:inline-block; margin-left:6px;">';
                                        echo '<input type="hidden" name="action" value="stop_driver">';
                                        echo '<input type="hidden" name="relation_id" value="' . intval($row['id']) . '">';
                                        echo '<button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(\'تأكيد إيقاف السائق؟\')">إيقاف</button>';
                                        echo '</form>';
                                    }

                                    echo '<form method="post" style="display:inline-flex; gap:6px; align-items:center; flex-wrap:wrap;">';
                                    echo '<input type="hidden" name="action" value="move_driver">';
                                    echo '<input type="hidden" name="relation_id" value="' . intval($row['id']) . '">';
                                    echo '<select name="new_equipment_id" required style="min-width:170px;">';
                                    echo '<option value="">اختر آلية جديدة</option>';
                                    foreach ($project_equipments as $eq) {
                                        $eq_id = intval($eq['id']);
                                        if ($eq_id === intval($row['equipment_id'])) {
                                            continue;
                                        }
                                        $eq_label = trim((string) $eq['code']) . ' - ' . trim((string) $eq['name']);
                                        echo '<option value="' . $eq_id . '">' . htmlspecialchars($eq_label, ENT_QUOTES, 'UTF-8') . '</option>';
                                    }
                                    echo '</select>';
                                    echo '<input type="date" name="move_start_date" value="' . date('Y-m-d') . '" required>';
                                    echo '<button type="submit" class="btn btn-sm btn-primary">تشغيل على آلية أخرى</button>';
                                    echo '</form>';
                                } else {
                                    echo '<span style="color:#9ca3af;">لا توجد صلاحية تعديل</span>';
                                }
                                echo '</td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>
<script>
$(document).ready(function () {
    $('#projectDriversTable').DataTable({
        responsive: true,
        language: {
            url: '/ems/assets/i18n/datatables/ar.json'
        }
    });

    $('#toggleAddDriverForm').on('click', function () {
        $('#addDriverForm').slideToggle(250);
    });

    $('#cancelAddDriverForm').on('click', function () {
        $('#addDriverForm').slideUp(200);
    });
});
</script>

</body>
</html>
