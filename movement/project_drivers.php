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

<style>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');
:root{--bg:#F5F0E8;--s0:#1A1208;--s1:#FFFFFF;--s2:#FDF8F0;--s3:#FFF4E6;--bdr:#E8DCC8;--bdr2:#F0E8D8;--or:#F7931A;--or2:#E67E00;--or3:#C96A00;--ord:rgba(247,147,26,.15);--orb:rgba(247,147,26,.08);--t1:#1A1208;--t2:#6B4E2A;--t3:#A07848;--ok:#16A34A;--warn:#D97706;--err:#DC2626;--r:8px;--rl:12px;--hex:polygon(8% 0,92% 0,100% 50%,92% 100%,8% 100%,0 50%);--sh:0 1px 3px rgba(26,18,8,.08),0 4px 12px rgba(26,18,8,.06);--sh2:0 2px 8px rgba(26,18,8,.1),0 8px 24px rgba(26,18,8,.08)}
body,.main{font-family:'Tajawal',sans-serif!important;background:var(--bg)!important;color:var(--t1)!important}
/* ══ HERO ══ */
.ems-hero{background:var(--s0);border-bottom:2px solid var(--or);position:relative;overflow:hidden}
.ems-hero::before{content:'';position:absolute;inset:0;background-image:radial-gradient(rgba(247,147,26,.05) 1px,transparent 1px);background-size:22px 22px;pointer-events:none}
.ems-hero-inner{position:relative;z-index:1;padding:16px 22px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.ems-hero-sup{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:5px;display:flex;align-items:center;gap:7px}
.ems-hero-sup::before{content:'';width:20px;height:2px;background:var(--or);border-radius:1px;flex-shrink:0}
.ems-hero-name{font-size:clamp(1.1rem,2vw,1.6rem);font-weight:900;color:#fff;line-height:1.1}
.ems-hero-meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.ems-hero-tag{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:50px;font-size:.73rem;font-weight:700;white-space:nowrap}
.ems-hero-tag i{font-size:.6rem}
.ems-tag-or{background:rgba(247,147,26,.18);border:1px solid rgba(247,147,26,.35);color:var(--or)}
.ems-tag-ok{background:rgba(74,222,128,.12);border:1px solid rgba(74,222,128,.25);color:#4ADE80}
.ems-tag-info{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.6)}
.ems-hero-deco{display:flex;align-items:center;gap:5px;flex-shrink:0}
.ems-hx{clip-path:var(--hex);display:flex;align-items:center;justify-content:center;color:#fff}
.ems-hx-xl{width:56px;height:56px;font-size:1.4rem;background:linear-gradient(135deg,var(--or),var(--or2));box-shadow:0 0 24px rgba(247,147,26,.4)}
.ems-hx-md{width:30px;height:30px;font-size:.8rem;background:rgba(247,147,26,.25)}
.ems-hero-actions{position:relative;z-index:1;padding:0 22px 14px;display:flex;flex-wrap:wrap;gap:8px}
/* ══ BUTTONS ══ */
.add-btn,.back-btn{display:inline-flex!important;align-items:center!important;gap:7px!important;padding:7px 17px!important;border-radius:var(--r)!important;font-family:'Tajawal',sans-serif!important;font-size:.82rem!important;font-weight:700!important;text-decoration:none!important;transition:all .15s!important;cursor:pointer!important;white-space:nowrap!important;border:none!important}
.add-btn{background:var(--or)!important;color:#fff!important;box-shadow:0 2px 8px rgba(247,147,26,.3)!important}
.add-btn:hover{background:var(--or2)!important;transform:translateY(-1px)!important;color:#fff!important}
.back-btn{background:rgba(255,255,255,.08)!important;border:1px solid rgba(255,255,255,.15)!important;color:rgba(255,255,255,.8)!important}
.back-btn:hover{background:rgba(255,255,255,.15)!important;color:#fff!important}
/* ══ CONTENT ══ */
.ems-content{padding:18px 22px}
/* ══ ALERTS ══ */
.success-message{padding:12px 18px!important;border-radius:var(--r)!important;margin-bottom:16px!important;font-weight:700!important;font-size:.88rem!important;display:flex!important;align-items:center!important;gap:10px!important}
.is-success{background:rgba(22,163,74,.1)!important;border:1px solid rgba(22,163,74,.25)!important;color:var(--ok)!important}
.is-error{background:rgba(220,38,38,.08)!important;border:1px solid rgba(220,38,38,.2)!important;color:var(--err)!important}
/* ══ CARDS ══ */
.card{background:var(--s1)!important;border:1px solid var(--bdr)!important;border-radius:var(--rl)!important;box-shadow:var(--sh)!important;margin-bottom:20px!important;overflow:visible!important}
.card-header{background:var(--s2)!important;border-bottom:1px solid var(--bdr)!important;padding:12px 18px!important;border-radius:var(--rl) var(--rl) 0 0!important;overflow:hidden!important}
.card-header h5{margin:0!important;font-size:.9rem!important;font-weight:800!important;color:var(--t1)!important;display:flex!important;align-items:center!important;gap:8px!important}
.card-header h5 i{color:var(--or)!important}
.card-body{padding:18px!important;overflow-x:auto!important;-webkit-overflow-scrolling:touch!important}
.table-container{overflow-x:auto!important;-webkit-overflow-scrolling:touch!important;width:100%!important}
/* ══ FORMS ══ */
.form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}
.form-grid select,.form-grid input[type=date],.form-grid input[type=number],.form-grid input[type=text]{width:100%!important;padding:9px 12px!important;border:1.5px solid var(--bdr)!important;border-radius:var(--r)!important;font-family:'Tajawal',sans-serif!important;font-size:.85rem!important;background:var(--s2)!important;color:var(--t1)!important;transition:border-color .15s!important}
.form-grid select:focus,.form-grid input:focus{outline:none!important;border-color:var(--or)!important;background:var(--s1)!important}
.form-grid label{display:block!important;font-size:.78rem!important;font-weight:700!important;color:var(--t3)!important;margin-bottom:5px!important}
.form-hidden{display:none!important}
/* ══ STATUS ══ */
.status-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 12px;border-radius:50px;font-size:.75rem;font-weight:700}
.status-running{background:rgba(22,163,74,.1);color:var(--ok);border:1px solid rgba(22,163,74,.2)}
.status-idle{background:rgba(160,120,72,.1);color:var(--t3);border:1px solid rgba(160,120,72,.2)}
/* ══ DATATABLES ══ */
.dataTables_wrapper{font-family:'Tajawal',sans-serif!important;color:var(--t2)!important}
table.dataTable thead th{background:var(--s2)!important;color:var(--t3)!important;font-size:.78rem!important;font-weight:800!important;border-bottom:2px solid var(--bdr)!important;padding:10px 12px!important;white-space:nowrap!important}
table.dataTable tbody tr{background:var(--s1)!important}
table.dataTable tbody tr:hover{background:var(--s3)!important}
table.dataTable tbody td{padding:10px 12px!important;font-size:.85rem!important;border-bottom:1px solid var(--bdr2)!important;vertical-align:middle!important;color:var(--t2)!important}
.dataTables_filter input{border:1.5px solid var(--bdr)!important;border-radius:var(--r)!important;padding:7px 12px!important;font-family:'Tajawal',sans-serif!important;background:var(--s2)!important;color:var(--t1)!important}
/* ══ INLINE FORM CONTROLS ══ */
select{border:1.5px solid var(--bdr)!important;border-radius:var(--r)!important;padding:6px 10px!important;font-family:'Tajawal',sans-serif!important;background:var(--s2)!important;color:var(--t1)!important}
input[type=date]{border:1.5px solid var(--bdr)!important;border-radius:var(--r)!important;padding:6px 10px!important;font-family:'Tajawal',sans-serif!important;background:var(--s2)!important;color:var(--t1)!important}
.btn.btn-sm.btn-danger{background:var(--err)!important;border:none!important;border-radius:var(--r)!important;font-family:'Tajawal',sans-serif!important;font-weight:700!important;font-size:.78rem!important}
.btn.btn-sm.btn-primary{background:var(--or)!important;border:none!important;border-radius:var(--r)!important;font-family:'Tajawal',sans-serif!important;font-weight:700!important;font-size:.78rem!important}
.btn.btn-sm.btn-primary:hover{background:var(--or2)!important}
.btn.btn-success{background:var(--ok)!important;border:none!important;border-radius:var(--r)!important;font-family:'Tajawal',sans-serif!important;font-weight:700!important}
.btn.btn-secondary{background:var(--s2)!important;border:1px solid var(--bdr)!important;color:var(--t2)!important;border-radius:var(--r)!important;font-family:'Tajawal',sans-serif!important;font-weight:700!important}
</style>

<div class="main">
  <!-- ══ HERO HEADER ══ -->
  <div class="ems-hero">
    <div class="ems-hero-inner">
      <div class="ems-hero-body">
        <div class="ems-hero-sup">
          <i class="fas fa-project-diagram"></i>
          <?php echo htmlspecialchars($selected_project['name']); ?>
        </div>
        <div class="ems-hero-name">سائقو المشروع</div>
        <div class="ems-hero-meta">
          <?php if (!empty($selected_project['project_code'])): ?>
          <span class="ems-hero-tag ems-tag-or"><i class="fas fa-barcode"></i> <?php echo htmlspecialchars($selected_project['project_code']); ?></span>
          <?php endif; ?>
          <span class="ems-hero-tag ems-tag-info"><i class="fas fa-id-badge"></i> إدارة المشغلين</span>
          <span class="ems-hero-tag ems-tag-info"><i class="fas fa-link"></i> ربط السائقين بالآليات</span>
        </div>
      </div>
      <div class="ems-hero-deco">
        <div style="display:flex;flex-direction:column;gap:5px">
          <div class="ems-hx ems-hx-md"><i class="fas fa-id-card"></i></div>
          <div class="ems-hx ems-hx-md"><i class="fas fa-cog"></i></div>
        </div>
        <div class="ems-hx ems-hx-xl"><i class="fas fa-id-badge"></i></div>
      </div>
    </div>
    <div class="ems-hero-actions">
      <a href="move_oprators.php?project_id=<?php echo intval($selected_project_id); ?>" class="back-btn"><i class="fas fa-arrow-right"></i> رجوع للتشغيل</a>
      <?php if ($can_edit): ?>
      <a href="javascript:void(0)" id="toggleAddDriverForm" class="add-btn"><i class="fas fa-plus-circle"></i> إضافة تشغيل سائق</a>
      <?php endif; ?>
    </div>
  </div><!-- /.ems-hero -->

  <div class="ems-content">
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

  </div><!-- /.ems-content -->
</div><!-- /.main -->

</body>
</html>
