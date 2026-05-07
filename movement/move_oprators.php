<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
$page_title = "إيكوبيشن | التشغيل ";
include '../config.php';
include '../includes/permissions_helper.php';
require_once '../includes/approval_workflow.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$project_client_column = db_table_has_column($conn, 'project', 'client_id') ? 'client_id' : 'company_client_id';
$project_has_company_id = db_table_has_column($conn, 'project', 'company_id');

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
if (!$operations_has_company) {
    @mysqli_query($conn, "ALTER TABLE operations ADD COLUMN company_id INT NULL AFTER project_id");
    @mysqli_query($conn, "ALTER TABLE operations ADD INDEX idx_operations_company_id (company_id)");
    $operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
}

if (!$is_super_admin && !$operations_has_company) {
    die('لا يمكن تطبيق عزل الشركات في شاشة التشغيل لأن عمود company_id غير متاح في جدول operations.');
}

$operations_company_scope = (!$is_super_admin && $operations_has_company) ? " AND company_id = $company_id" : "";

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

// التحقق من صلاحيات المستخدم على شاشة التشغيل
$page_permissions = check_page_permissions($conn, 'movement/move_oprators.php');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

if (!$can_view) {
    header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+التشغيل+❌");
    exit();
}

$is_role10 = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10";
$user_project_id  = $is_role10 ? intval($_SESSION['user']['project_id']) : 0;
$user_mine_id     = isset($_SESSION['user']['mine_id']) ? intval($_SESSION['user']['mine_id']) : 0; // يُستخدم لجميع الأدوار
$user_contract_id = $is_role10 ? intval($_SESSION['user']['contract_id']) : 0;

$session_user_project_id = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
// التحقق من وجود مشروع محدد
$selected_project_id = 0;
$selected_project = null;

// التحقق من GET parameter أو SESSION
if ($is_role10) {
    $selected_project_id = $user_project_id;
    if ($selected_project_id > 0) {
        $_SESSION['operations_project_id'] = $selected_project_id;
    }
} elseif (isset($_GET['project_id']) && !empty($_GET['project_id'])) {
    $selected_project_id = intval($_GET['project_id']);
    $_SESSION['operations_project_id'] = $selected_project_id;
} elseif (isset($_SESSION['operations_project_id'])) {
    $selected_project_id = intval($_SESSION['operations_project_id']);
} elseif ($session_user_project_id > 0) {
    $selected_project_id = $session_user_project_id;
    $_SESSION['operations_project_id'] = $selected_project_id;
}

// إذا لم يتم تحديد مشروع، إعادة التوجيه لصفحة الاختيار
if ($selected_project_id == 0) {
    echo "<script>alert('❌ لا يوجد مشروع مرتبط بحسابك في الجلسة'); window.location.href='../main/dashboard.php';</script>";
    exit();
}

// جلب بيانات المشروع المحدد
$project_query = "SELECT id, name, project_code, location FROM project WHERE id = $selected_project_id AND status = 1 AND $project_scope_sql";
$project_result = mysqli_query($conn, $project_query);

if (!$project_result) {
    echo "<script>alert('❌ خطأ في تحميل بيانات المشروع'); window.location.href='select_project.php';</script>";
    exit();
}

if (mysqli_num_rows($project_result) > 0) {
    $selected_project = mysqli_fetch_assoc($project_result);
} else {
    // المشروع غير موجود أو غير نشط
    unset($_SESSION['operations_project_id']);
    echo "<script>alert('❌ المشروع المحفوظ في الجلسة غير متاح أو غير نشط'); window.location.href='../main/dashboard.php';</script>";
    exit();
}

// جلب بيانات المنجم المحدد في الجلسة (يعمل لجميع الأدوار)
$selected_mine = null;
if ($user_mine_id > 0) {
    $mine_q = mysqli_query($conn, "SELECT id, mine_name, mine_code FROM mines WHERE id = $user_mine_id AND project_id = $selected_project_id AND status = '1' LIMIT 1");
    if ($mine_q && mysqli_num_rows($mine_q) > 0) {
        $selected_mine = mysqli_fetch_assoc($mine_q);
    }
}

// تغيير حالة التشغيل (إيقاف/تعطل/استئناف)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_status') {
    if (!$can_edit) {
        $redirect_project = isset($_SESSION['operations_project_id']) ? $_SESSION['operations_project_id'] : '';
        echo "<script>alert('❌ ليس لديك صلاحية تعديل التشغيل'); window.location.href='oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
        exit();
    }

    $operation_id = intval($_POST['operation_id']);
    $new_status = intval($_POST['new_status']);
    $allowed_statuses = [0, 1];

    if (!empty($operation_id) && in_array($new_status, $allowed_statuses, true)) {
        // إذا كان الطلب لتفعيل السجل (status = 1)، تحقق أن المعدة ليس لها سجل ساري آخر
        if ($new_status === 1) {
            // جلب معرف المعدة للسجل الحالي
            $eq_res = mysqli_query($conn, "SELECT equipment FROM operations WHERE id = $operation_id LIMIT 1");
            $eq_id = 0;
            if ($eq_res && mysqli_num_rows($eq_res) > 0) {
                $eq_row = mysqli_fetch_assoc($eq_res);
                $eq_id = intval($eq_row['equipment']);
            }
            if ($eq_id <= 0) {
                $redirect_project = isset($_SESSION['operations_project_id']) ? $_SESSION['operations_project_id'] : '';
                echo "<script>alert('❌ لا يمكن تحديد الآلية المرتبطة بهذا السجل'); window.location.href='move_oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
                exit();
            }
            // التحقق من عدم وجود سجل ساري آخر لنفس المعدة
            $conflict_res = mysqli_query($conn, "SELECT id FROM operations WHERE equipment = $eq_id AND status = 1 AND id != $operation_id LIMIT 1");
            if ($conflict_res && mysqli_num_rows($conflict_res) > 0) {
                $redirect_project = isset($_SESSION['operations_project_id']) ? $_SESSION['operations_project_id'] : '';
                echo "<script>alert('❌ لا يمكن إعادة تشغيل المعدة وهي تعمل بالفعل في سجل آخر'); window.location.href='move_oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
                exit();
            }
        }

        $update_sql = "UPDATE operations SET status = $new_status WHERE id = $operation_id AND project_id = $selected_project_id$operations_company_scope";
        $update_result = mysqli_query($conn, $update_sql);

        if ($update_result) {
            $redirect_project = isset($_SESSION['operations_project_id']) ? $_SESSION['operations_project_id'] : '';
            echo "<script>alert('✅ تم تحديث الحالة بنجاح'); window.location.href='move_oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
            exit();
        }

        echo "<script>alert('❌ خطأ في تحديث الحالة: " . mysqli_error($conn) . "');</script>";
    } else {
        echo "<script>alert('❌ بيانات غير صحيحة لتحديث الحالة');</script>";
    }
}

// طلب إيقاف آلية عبر نظام الموافقات (مدير الحركة والتشغيل فقط)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_equipment_stop') {
    if (!$can_edit) {
        echo "<script>alert('❌ ليس لديك صلاحية تعديل التشغيل');</script>";
    } elseif (!$is_role10) {
        echo "<script>alert('❌ ليس لديك صلاحية لتقديم طلب إيقاف آلية');</script>";
    } else {
        $operation_id = isset($_POST['operation_id']) ? intval($_POST['operation_id']) : 0;
        $request_reason = isset($_POST['request_reason']) ? trim($_POST['request_reason']) : '';

        if ($operation_id <= 0) {
            echo "<script>alert('❌ بيانات غير صحيحة');</script>";
        } else {
            $op_sql = "SELECT o.id, o.equipment, o.status, e.code AS equipment_code, e.name AS equipment_name, e.availability_status
                       FROM operations o
                       LEFT JOIN equipments e ON o.equipment = e.id
                       WHERE o.id = $operation_id AND o.project_id = $selected_project_id" . str_replace('company_id', 'o.company_id', $operations_company_scope) . "
                       LIMIT 1";
            $op_result = mysqli_query($conn, $op_sql);

            if (!$op_result || mysqli_num_rows($op_result) === 0) {
                echo "<script>alert('❌ عملية التشغيل غير موجودة');</script>";
            } else {
                $op_row = mysqli_fetch_assoc($op_result);
                $equipment_id = intval($op_row['equipment']);

                if ($equipment_id <= 0) {
                    echo "<script>alert('❌ لا توجد آلية مرتبطة بهذا التشغيل');</script>";
                } else {
                    $reason_text = $request_reason !== '' ? $request_reason : 'طلب إيقاف آلية من شاشة التشغيل';

                    // ضمان وجود قاعدة الموافقة (مدير الأسطول) قبل إنشاء الطلب
                    mysqli_query(
                        $conn,
                        "INSERT IGNORE INTO approval_workflow_rules (entity_type, action, role_required, step_order, is_active, created_at)
                         VALUES ('equipment', 'deactivate_equipment', '4,-1', 1, 1, NOW())"
                    );

                    $payload = [
                        'summary' => [
                            'operation_id' => $operation_id,
                            'equipment_id' => $equipment_id,
                            'equipment_code' => $op_row['equipment_code'],
                            'equipment_name' => $op_row['equipment_name'],
                            'requested_by_role' => '10',
                            'reason' => $reason_text,
                            'current_availability_status' => $op_row['availability_status'],
                            'new_availability_status' => 'موقوفة للصيانة'
                        ],
                        'operations' => [
                            [
                                'db_action' => 'update',
                                'table' => 'equipments',
                                'where' => ['id' => $equipment_id],
                                'data' => ['availability_status' => 'موقوفة للصيانة']
                            ],
                            [
                                'db_action' => 'update',
                                'table' => 'operations',
                                'where' => ['id' => $operation_id],
                                'data' => ['status' => 3]
                            ]
                        ]
                    ];

                    $approval_result = approval_create_request(
                        'equipment',
                        $equipment_id,
                        'deactivate_equipment',
                        $payload,
                        approval_get_user_id(),
                        $conn
                    );

                    $redirect_project = isset($_SESSION['operations_project_id']) ? $_SESSION['operations_project_id'] : '';
                    if (!empty($approval_result['success'])) {
                        echo "<script>alert('✅ " . addslashes($approval_result['message']) . "'); window.location.href='move_oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
                        exit();
                    }

                    echo "<script>alert('❌ " . addslashes($approval_result['message']) . "');</script>";
                }
            }
        }
    }
}

// انهاء خدمة من الموديل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'end_service') {
    if (!$can_edit) {
        $redirect_project = isset($_SESSION['operations_project_id']) ? $_SESSION['operations_project_id'] : '';
        echo "<script>alert('❌ ليس لديك صلاحية إنهاء الخدمة'); window.location.href='move_oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
        exit();
    }

    if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10") {
        $redirect_project = isset($_SESSION['operations_project_id']) ? $_SESSION['operations_project_id'] : '';
        echo "<script>alert('❌ ليس لديك صلاحية لإنهاء الخدمة'); window.location.href='move_oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
        exit();
    }

    $operation_id = intval($_POST['operation_id']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);

    if (!empty($operation_id) && !empty($end_date)) {
        $days_value = "NULL";
        $start_res = mysqli_query($conn, "SELECT `start` FROM operations WHERE id = $operation_id AND project_id = $selected_project_id$operations_company_scope");
        if ($start_res && mysqli_num_rows($start_res) > 0) {
            $start_row = mysqli_fetch_assoc($start_res);
            $start_date = $start_row['start'];
            if (!empty($start_date)) {
                $start_dt = DateTime::createFromFormat('Y-m-d', $start_date);
                $end_dt = DateTime::createFromFormat('Y-m-d', $end_date);
                if ($start_dt && $end_dt) {
                    $diff = $start_dt->diff($end_dt);
                    $days_value = intval($diff->days);
                }
            }
        }

        $update_sql = "UPDATE operations SET status = 0, `end` = '$end_date', reason = '$reason', days = $days_value WHERE id = $operation_id AND project_id = $selected_project_id$operations_company_scope";
        $update_result = mysqli_query($conn, $update_sql);
        
        if ($update_result) {
            // الحفاظ على المشروع المحدد بعد إنهاء الخدمة
            $redirect_project = isset($_SESSION['operations_project_id']) ? $_SESSION['operations_project_id'] : '';
            echo "<script>alert('✅ تم إنهاء الخدمة بنجاح'); window.location.href='move_oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
            exit();
        } else {
            echo "<script>alert('❌ خطأ في إنهاء الخدمة: " . mysqli_error($conn) . "');</script>";
        }
    } else {
        echo "<script>alert('❌ يرجى إدخال جميع البيانات المطلوبة');</script>";
    }
}

// حذف تشغيل
if (isset($_GET['delete_id'])) {
    if (!$can_delete) {
        $redirect_project = isset($_SESSION['operations_project_id']) ? $_SESSION['operations_project_id'] : '';
        header("Location: move_oprators.php" . ($redirect_project ? "?project_id=$redirect_project&msg=" : "?msg=") . "لا+توجد+صلاحية+حذف+التشغيل+❌");
        exit();
    }

    $delete_id = intval($_GET['delete_id']);
    if ($delete_id > 0) {
        $delete_sql = "DELETE FROM operations WHERE id = $delete_id AND project_id = $selected_project_id$operations_company_scope";
        if (mysqli_query($conn, $delete_sql)) {
            header("Location: move_oprators.php?project_id=$selected_project_id&msg=تم+حذف+التشغيل+بنجاح+✅");
            exit();
        }
        header("Location: move_oprators.php?project_id=$selected_project_id&msg=حدث+خطأ+أثناء+الحذف+❌");
        exit();
    }
}

?>

<?php 
include("../inheader.php");
include('../insidebar.php'); 
?>

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
/* ══ CONTENT WRAP ══ */
.ems-content{padding:18px 22px}
/* ══ ALERTS ══ */
.success-message{padding:12px 18px!important;border-radius:var(--r)!important;margin-bottom:16px!important;font-weight:700!important;font-size:.88rem!important;display:flex!important;align-items:center!important;gap:10px!important}
.is-success{background:rgba(22,163,74,.1)!important;border:1px solid rgba(22,163,74,.25)!important;color:var(--ok)!important}
.is-error{background:rgba(220,38,38,.08)!important;border:1px solid rgba(220,38,38,.2)!important;color:var(--err)!important}
/* ══ SECTION LABEL ══ */
.ems-sec{display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--t3)}
.ems-sec i{color:var(--or);font-size:.72rem}
.ems-sec::after{content:'';flex:1;height:1px;background:var(--bdr)}
.section-title{font-size:.88rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:8px;margin:0 0 16px;padding-bottom:8px;border-bottom:1px solid var(--bdr)}
.section-title i{color:var(--or)}
/* ══ CARDS ══ */
.card{background:var(--s1)!important;border:1px solid var(--bdr)!important;border-radius:var(--rl)!important;box-shadow:var(--sh)!important;margin-bottom:20px!important;overflow:visible!important}
.card-header{background:var(--s2)!important;border-bottom:1px solid var(--bdr)!important;padding:12px 18px!important;border-radius:var(--rl) var(--rl) 0 0!important;overflow:hidden!important}
.card-header h5{margin:0!important;font-size:.9rem!important;font-weight:800!important;color:var(--t1)!important;display:flex!important;align-items:center!important;gap:8px!important}
.card-header h5 i{color:var(--or)!important}
.card-body{padding:18px!important;overflow-x:auto!important;-webkit-overflow-scrolling:touch!important}
.tbl-scroll-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;width:100%;border-radius:0 0 var(--rl) var(--rl)}
/* ══ FORMS ══ */
.form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px}
.form-grid select,.form-grid input[type=date],.form-grid input[type=number],.form-grid input[type=text]{width:100%!important;padding:9px 12px!important;border:1.5px solid var(--bdr)!important;border-radius:var(--r)!important;font-family:'Tajawal',sans-serif!important;font-size:.85rem!important;background:var(--s2)!important;color:var(--t1)!important;transition:border-color .15s!important}
.form-grid select:focus,.form-grid input:focus{outline:none!important;border-color:var(--or)!important;background:var(--s1)!important}
.form-grid label{display:block!important;font-size:.78rem!important;font-weight:700!important;color:var(--t3)!important;margin-bottom:5px!important}
.form-grid button[type=submit]{background:var(--or)!important;color:#fff!important;border:none!important;padding:10px 22px!important;border-radius:var(--r)!important;font-family:'Tajawal',sans-serif!important;font-weight:800!important;font-size:.88rem!important;cursor:pointer!important;transition:all .15s!important}
.form-grid button[type=submit]:hover{background:var(--or2)!important;transform:translateY(-1px)!important}
.form-hidden{display:none!important}
/* ══ STATUS BADGES ══ */
.status-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 12px;border-radius:50px;font-size:.75rem;font-weight:700}
.status-running{background:rgba(22,163,74,.1);color:var(--ok);border:1px solid rgba(22,163,74,.2)}
.status-idle{background:rgba(160,120,72,.1);color:var(--t3);border:1px solid rgba(160,120,72,.2)}
.category-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:50px;font-size:.75rem;font-weight:700}
.category-badge.basic{background:var(--orb);color:var(--or3);border:1px solid var(--ord)}
.category-badge.backup{background:rgba(59,130,246,.08);color:#2563eb;border:1px solid rgba(59,130,246,.2)}
/* ══ ACTION BUTTONS ══ */
.action-btns{display:flex;gap:6px;align-items:center}
.action-btn{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:32px!important;height:32px!important;border-radius:var(--r)!important;font-size:.8rem!important;transition:all .15s!important;text-decoration:none!important}
.action-btn.view{background:var(--orb);color:var(--or);border:1px solid var(--ord)}
.action-btn.view:hover{background:var(--or);color:#fff}
.action-btn.edit{background:rgba(37,99,235,.08);color:#2563eb;border:1px solid rgba(37,99,235,.2)}
.action-btn.edit:hover{background:#2563eb;color:#fff}
.action-btn.delete{background:rgba(220,38,38,.08);color:var(--err);border:1px solid rgba(220,38,38,.2)}
.action-btn.delete:hover{background:var(--err);color:#fff}
/* ══ DATATABLES ══ */
.dataTables_wrapper{font-family:'Tajawal',sans-serif!important;color:var(--t2)!important}
table.dataTable thead th{background:var(--s2)!important;color:var(--t3)!important;font-size:.78rem!important;font-weight:800!important;border-bottom:2px solid var(--bdr)!important;padding:10px 12px!important;white-space:nowrap!important}
table.dataTable tbody tr{background:var(--s1)!important}
table.dataTable tbody tr:hover{background:var(--s3)!important}
table.dataTable tbody td{padding:10px 12px!important;font-size:.85rem!important;border-bottom:1px solid var(--bdr2)!important;vertical-align:middle!important;color:var(--t2)!important}
.dataTables_filter input{border:1.5px solid var(--bdr)!important;border-radius:var(--r)!important;padding:7px 12px!important;font-family:'Tajawal',sans-serif!important;background:var(--s2)!important;color:var(--t1)!important}
.dt-buttons .dt-button{background:var(--s2)!important;border:1px solid var(--bdr)!important;color:var(--t2)!important;border-radius:var(--r)!important;font-family:'Tajawal',sans-serif!important;padding:6px 14px!important;font-size:.78rem!important;font-weight:700!important}
.dt-buttons .dt-button:hover{background:var(--or)!important;color:#fff!important;border-color:var(--or)!important}
/* ══ CONTRACT STATS ══ */
.contract-stats{background:var(--s1);border:1px solid var(--bdr);border-radius:var(--rl);box-shadow:var(--sh);padding:18px;margin-bottom:20px}
.contract-stats.is-hidden{display:none}
.stats-title{font-size:.88rem;font-weight:800;color:var(--t1);margin-bottom:14px;display:flex;align-items:center;gap:8px}
.stats-title i{color:var(--or)}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:14px}
.stat-card{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--rl);padding:14px;text-align:center}
.stat-card-icon{font-size:1.4rem;color:var(--or);margin-bottom:6px}
.stat-card-value{font-size:1.8rem;font-weight:900;color:var(--t1)}
.stat-card-label{font-size:.78rem;color:var(--t3);font-weight:600;margin-top:4px}
.table-scroll{overflow-x:auto;border-radius:var(--rl);border:1px solid var(--bdr)}
.suppliers-table{width:100%;border-collapse:collapse}
.suppliers-table thead th{background:var(--s2);color:var(--t3);font-size:.78rem;font-weight:800;padding:10px 12px;border-bottom:2px solid var(--bdr)}
.suppliers-table tbody td{padding:9px 12px;font-size:.83rem;color:var(--t2);border-bottom:1px solid var(--bdr2);background:var(--s1)}
.suppliers-table tbody tr:hover td{background:var(--s3)}
.suppliers-total-row td{font-weight:700;color:var(--t1);border-top:2px solid var(--bdr);background:var(--s2)!important}
.suppliers-total-label{font-weight:800;color:var(--or3)}
.suppliers-empty{text-align:center;color:var(--t3);font-size:.85rem;padding:20px}
.legend-basic{color:var(--or)}.legend-backup{color:#2563eb}
.suppliers-basic-count{color:var(--or3);font-weight:700}.suppliers-backup-count{color:#2563eb;font-weight:700}
.breakdown-item{margin-bottom:4px;font-size:.78rem;color:var(--t2)}
.breakdown-tag{display:inline-block;padding:2px 7px;border-radius:4px;font-size:.72rem;font-weight:700;margin:0 2px}
.breakdown-tag.is-basic{background:var(--orb);color:var(--or3)}.breakdown-tag.is-backup{background:rgba(37,99,235,.08);color:#2563eb}
.breakdown-count.is-active{color:var(--ok);font-weight:700}.breakdown-count.is-warning{color:var(--warn);font-weight:700}.breakdown-count.is-muted{color:var(--t3)}
.breakdown-empty{color:var(--t3);font-size:.78rem}
.badge-available{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:50px;font-size:.78rem;font-weight:700;background:rgba(22,163,74,.1);color:var(--ok);border:1px solid rgba(22,163,74,.2)}
.badge-working{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:50px;font-size:.78rem;font-weight:700;background:rgba(217,119,6,.1);color:var(--warn);border:1px solid rgba(217,119,6,.2)}
.badge-busy{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:50px;font-size:.78rem;font-weight:700;background:rgba(220,38,38,.08);color:var(--err);border:1px solid rgba(220,38,38,.2)}
/* ══ VIEW MODAL WARM THEME ══ */
#viewOperationModal .modal-content{background:var(--s1)!important;border-radius:var(--rl)!important}
#viewOperationModal .modal-header{background:linear-gradient(135deg,var(--s0),#2a1c08)!important;border-bottom:2px solid var(--or)!important;border-radius:var(--rl) var(--rl) 0 0!important}
</style>

<div class="main">
  <!-- ══ HERO HEADER ══ -->
  <div class="ems-hero">
    <div class="ems-hero-inner">
      <div class="ems-hero-body">
        <div class="ems-hero-sup">
          <i class="fas fa-project-diagram"></i>
          <?php echo htmlspecialchars($selected_project['name']); ?>
          <?php if ($selected_mine): ?> &middot; <?php echo htmlspecialchars($selected_mine['mine_name']); ?><?php endif; ?>
        </div>
        <div class="ems-hero-name">إدارة التشغيل</div>
        <div class="ems-hero-meta">
          <?php if (!empty($selected_project['project_code'])): ?>
          <span class="ems-hero-tag ems-tag-or"><i class="fas fa-barcode"></i> <?php echo htmlspecialchars($selected_project['project_code']); ?></span>
          <?php endif; ?>
          <?php if ($selected_mine): ?>
          <span class="ems-hero-tag ems-tag-ok"><i class="fas fa-mountain"></i> <?php echo htmlspecialchars($selected_mine['mine_name']); ?></span>
          <?php if (!empty($selected_mine['mine_code'])): ?>
          <span class="ems-hero-tag ems-tag-info"><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($selected_mine['mine_code']); ?></span>
          <?php endif; ?>
          <?php else: ?>
          <span class="ems-hero-tag ems-tag-info"><i class="fas fa-layer-group"></i> جميع مناجم المشروع</span>
          <?php endif; ?>
          <span class="ems-hero-tag ems-tag-info"><i class="fas fa-cogs"></i> تنظيم التشغيل</span>
        </div>
      </div>
      <div class="ems-hero-deco">
        <div style="display:flex;flex-direction:column;gap:5px">
          <div class="ems-hx ems-hx-md"><i class="fas fa-mountain"></i></div>
          <div class="ems-hx ems-hx-md"><i class="fas fa-cog"></i></div>
        </div>
        <div class="ems-hx ems-hx-xl"><i class="fas fa-hard-hat"></i></div>
      </div>
    </div>
    <div class="ems-hero-actions">
      <a href="../main/dashboard.php" class="back-btn"><i class="fas fa-arrow-right"></i> رجوع</a>
      <a href="project_drivers.php?project_id=<?php echo intval($selected_project_id); ?>" class="back-btn"><i class="fas fa-id-badge"></i> سائقي المشروع</a>
      <?php if($_SESSION['user']['role'] != "10"): ?>
      <a href="select_project.php" class="back-btn"><i class="fas fa-exchange-alt"></i> تغيير المشروع</a>
      <?php endif; ?>
      <?php if ($can_add): ?>
      <a href="javascript:void(0)" id="toggleForm" class="add-btn"><i class="fa fa-plus"></i> اضافة تشغيل</a>
      <?php endif; ?>
    </div>
  </div><!-- /.ems-hero -->

  <div class="ems-content">
    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
    ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="ems-sec"><i class="fas fa-cogs"></i> إدارة التشغيل</div>

    <!-- فورم إضافة تشغيل -->
    <?php if ($can_add || $can_edit): ?>
    <form id="projectForm" action="" method="post" class="form-hidden">
        <div class="card">
            <div class="card-header">
                <h5 id="formTitle">
                    <i class="fa fa-plus-circle"></i> اضافة تشغيل آلية جديد
                </h5>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <!-- المعرّف أثناء التعديل -->
                    <input type="hidden" name="operation_id" id="operation_id" value="">

                    <!-- المشروع مخفي لأنه محدد مسبقاً -->
                    <input type="hidden" name="project_id" id="project_id" value="<?php echo $selected_project_id; ?>">

                    <!-- المناجم -->
                    <select name="mine_id" id="mine_id" required>
                        <option value="">-- اختر المنجم --</option>
                        <?php
                        // تحميل المناجم للمشروع المحدد مباشرة
                        $mines_filter = $is_role10 && $user_mine_id > 0 ? " AND id = $user_mine_id" : "";
                        $mines_query = "SELECT id, mine_name FROM mines WHERE project_id = $selected_project_id AND status='1'$mines_filter ORDER BY mine_name";
                        $mines_result = mysqli_query($conn, $mines_query);
                        while ($mine = mysqli_fetch_assoc($mines_result)) {
                            $selected_mine = $is_role10 && $user_mine_id > 0 && $user_mine_id == $mine['id'] ? "selected" : "";
                            echo "<option value='" . $mine['id'] . "' $selected_mine>" . htmlspecialchars($mine['mine_name']) . "</option>";
                        }
                        ?>
                    </select>

                    <!-- العقود -->
                    <select name="contract_id" id="contract_id" required>
                        <option value="">-- اختر العقد --</option>
                    </select>

                    <!-- المورد -->
                    <select name="supplier_id" id="supplier_id" required>
                        <option value="">-- اختر المورد --</option>
                    </select>

                    <select name="type" id="type" required>
                        <option value=""> -- حدد نوع المعدة --- </option>
                        <?php
                        $type_query = "SELECT id, type FROM equipments_types WHERE status = 1 ORDER BY type";
                        $type_result = mysqli_query($conn, $type_query);
                        if ($type_result) {
                            while($type_row = mysqli_fetch_assoc($type_result)) {
                                echo "<option value='" . intval($type_row['id']) . "'> " . htmlspecialchars($type_row['type']) . " </option>";
                            }
                        }
                        ?>
                    </select>

                    <select name="equipment" id="equipment" required>
                        <option value="">-- اختر المعدة --</option>
                        <!-- سيتم ملؤها ديناميكيًا عبر AJAX -->
                    </select>

                    <div>
                        <div>
                            <label><i class="fas fa-check-circle"></i> نوع المعدة</label>
                            <select name="equipment_category" id="equipment_category" required>
                                <option value="">-- أساسي / احتياطي --</option>
                                <option value="أساسي"> أساسي</option>
                                <option value="احتياطي"> احتياطي</option>
                            </select>
                        </div>
                    </div>

                    <input type="date" name="start" id="start_date" required placeholder="تاريخ البداية" />
                    <input type="date" name="end" id="end_date" required placeholder="تاريخ النهاية" />
                    <input type="hidden" step="0.01" name="hours" placeholder="عدد الساعات" value="0" />
                    
                    <div>
                        <label><i class="fa fa-clock"></i> عدد ساعات العمل  للآلية</label>
                        <input type="number" name="total_equipment_hours" id="total_equipment_hours" step="0.01" placeholder="إجمالي ساعات العمل" value="0" required />
                    </div>
                    
                    <div>
                        <label><i class="fa fa-hourglass-half"></i> عدد ساعات الوردية</label>
                        <input type="number" name="shift_hours" id="shift_hours" step="0.01" placeholder="ساعات الوردية" value="0" required />
                    </div>
                    
                    <select name="status" id="status" required>
                        <option value="1">ساري</option>
                        <option value="0">منتهي</option>
                    </select>
                    <input type="hidden" name="action" value="save_operation" />
                    <button type="submit" name="save_operation_submit" id="save_operation_submit">حفظ التشغيل</button>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <!-- قسم الإحصائيات -->
    <div id="contractStats" class="contract-stats is-hidden">
        <h5 class="stats-title">
            <i class="fas fa-chart-line"></i>
            إحصائيات عقد المنجم
        </h5>

        <div id="suppliersSection" class="suppliers-section">
            <div class="table-scroll">
                <table class="suppliers-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المورد</th>
                            <th>الساعات المتعاقد عليها</th>
                            <th>عدد المعدات المتعاقد عليها</th>
                            <th><span class="legend-dot legend-basic">■</span> أساسية</th>
                            <th><span class="legend-dot legend-backup">■</span> احتياطية</th>
                            <th>المعدات المضافة</th>
                            <th>المتبقي للإضافة</th>
                            <th>توزيع المعدات والساعات</th>
                        </tr>
                    </thead>
                    <tbody id="suppliersTableBody">
                        <tr>
                            <td colspan="9" class="suppliers-empty">
                                <i class="fas fa-info-circle"></i> لا توجد بيانات
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="suppliers-total-row">
                            <td colspan="2" class="suppliers-total-label">الإجمالي</td>
                            <td id="total_supplier_hours" class="suppliers-total-value">0</td>
                            <td id="total_supplier_equipment" class="suppliers-total-value">0</td>
                            <td id="total_supplier_basic" class="suppliers-total-value">0</td>
                            <td id="total_supplier_backup" class="suppliers-total-value">0</td>
                            <td id="total_added_equipment" class="suppliers-total-value">0</td>
                            <td id="total_remaining_equipment" class="suppliers-total-value">0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-card-value" id="stat_total_hours">0</div>
                <div class="stat-card-label">إجمالي الساعات المتعاقد عليها</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-icon"><i class="fas fa-cogs"></i></div>
                <div class="stat-card-value" id="stat_equipment_count">0</div>
                <div class="stat-card-label">عدد المعدات المشغلة</div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-cogs"></i> قائمة التشغيل</h5>
        </div>
        <div class="card-body" style="padding:0!important">
          <div class="tbl-scroll-wrap" style="padding:0">
            <table id="projectsTable" class="display nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المعدة</th>

                        <th>السائقين</th>

                        <th>المورد</th>
                        <th>ساعات العمل الكلية</th>
                        <th>ساعات الوردية</th>

                        <th>تاريخ البداية</th>
                        <th>تاريخ النهاية</th>
                        <th>النوع</th>
                        <!-- <th style="text-align:right;">عدد الساعات</th> -->
                        <th>الحالة</th>
                        <th>إجراءات</th>

                    </tr>
                </thead>
                <tbody>
                    <?php
                    // إضافة أو تعديل تشغيل
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_operation' && !empty($_POST['equipment'])) {
                        $operation_id = isset($_POST['operation_id']) ? intval($_POST['operation_id']) : 0;

                        if ($operation_id > 0 && !$can_edit) {
                            echo "<script>alert('❌ ليس لديك صلاحية تعديل التشغيل'); window.location.href='move_oprators.php?project_id=$selected_project_id';</script>";
                            exit();
                        }
                        if ($operation_id === 0 && !$can_add) {
                            echo "<script>alert('❌ ليس لديك صلاحية إضافة تشغيل جديد'); window.location.href='move_oprators.php?project_id=$selected_project_id';</script>";
                            exit();
                        }

                        $equipment = intval($_POST['equipment']);
                        $project_id = intval($_POST['project_id']);
                        $mine_id = intval($_POST['mine_id']);
                        $contract_id = intval($_POST['contract_id']);
                        $supplier_id = intval($_POST['supplier_id']);
                        $equipment_type = intval($_POST['type']);
                        $equipment_category = mysqli_real_escape_string($conn, $_POST['equipment_category']);
                        
                        $start = mysqli_real_escape_string($conn, $_POST['start']);
                        $end = mysqli_real_escape_string($conn, $_POST['end']);
                        $hours = floatval($_POST['hours']);
                        $total_equipment_hours = floatval($_POST['total_equipment_hours']);
                        $shift_hours = floatval($_POST['shift_hours']);
                        $status = intval($_POST['status']);

                        // التحقق من عدم وجود سجل ساري آخر لنفس المعدة
                        if ($status === 1 && $equipment > 0) {
                            $exclude_id = $operation_id > 0 ? " AND id != $operation_id" : "";
                            $conflict_check = mysqli_query($conn, "SELECT id FROM operations WHERE equipment = $equipment AND status = 1$exclude_id LIMIT 1");
                            if ($conflict_check && mysqli_num_rows($conflict_check) > 0) {
                                echo "<script>alert('❌ لا يمكن تشغيل المعدة وهي تعمل بالفعل في تشغيل آخر'); window.location.href='move_oprators.php?project_id=$selected_project_id';</script>";
                                exit();
                            }
                        }

                        $status_escaped = mysqli_real_escape_string($conn, $status);

                        if ($operation_id > 0) {
                            // تعديل سجل موجود
                                $sql = "UPDATE operations SET 
                                    equipment = '$equipment',
                                    equipment_type = '$equipment_type',
                                    equipment_category = '$equipment_category',
                                    mine_id = '$mine_id',
                                    contract_id = '$contract_id',
                                    supplier_id = '$supplier_id',
                                    start = '$start',
                                    end = '$end',
                                    days = '$hours',
                                    total_equipment_hours = '$total_equipment_hours',
                                    shift_hours = '$shift_hours',
                                    status = '$status_escaped'
                                        WHERE id = $operation_id AND project_id = '$project_id'$operations_company_scope";
                            mysqli_query($conn, $sql);
                            echo "<script>alert('✅ تم التحديث بنجاح'); window.location.href='move_oprators.php?project_id=$selected_project_id';</script>";
                        } else {
                            // إضافة سجل جديد
                            $insert_company_col = (!$is_super_admin && $operations_has_company) ? ", company_id" : "";
                            $insert_company_val = (!$is_super_admin && $operations_has_company) ? ", '$company_id'" : "";
                            mysqli_query($conn, "INSERT INTO operations (equipment, equipment_type, equipment_category, project_id, mine_id, contract_id, supplier_id, start, end, days, total_equipment_hours, shift_hours, status$insert_company_col) 
                                         VALUES ('$equipment', '$equipment_type', '$equipment_category', '$project_id', '$mine_id', '$contract_id', '$supplier_id', '$start', '$end', '$hours', '$total_equipment_hours', '$shift_hours', '$status_escaped'$insert_company_val)");
                            echo "<script>alert('✅ تم الحفظ بنجاح'); window.location.href='move_oprators.php?project_id=$selected_project_id';</script>";
                        }
                    }

                    // جلب بيانات التشغيل - فلتر المنجم إذا كان محدداً في الجلسة
                    $mine_sql_filter = ($user_mine_id > 0) ? " AND o.mine_id = $user_mine_id" : '';
                  

                          $operations_scope_sql = (!$is_super_admin && $operations_has_company) ? " AND o.company_id = $company_id" : "";

                          $query = "SELECT o.id, o.equipment, o.equipment_type, o.equipment_category, o.mine_id, o.contract_id, o.supplier_id,
                             o.start, o.end, o.days, o.total_equipment_hours, o.shift_hours, o.status, o.reason,
                             e.code AS equipment_code, e.name AS equipment_name,
                             p.name AS project_name, s.name AS suppliers_name,
                             IFNULL(GROUP_CONCAT(DISTINCT d.name SEPARATOR ', '), '') AS driver_names
                      FROM operations o
                      LEFT JOIN equipments e ON o.equipment = e.id
                      LEFT JOIN project p ON o.project_id = p.id
                      LEFT JOIN suppliers s ON e.suppliers = s.id
                      LEFT JOIN equipment_drivers ed ON o.equipment = ed.equipment_id
                      LEFT JOIN drivers d ON ed.driver_id = d.id
                      WHERE o.project_id = $selected_project_id$mine_sql_filter$operations_scope_sql
                      GROUP BY o.id
                      ORDER BY o.id DESC";
                    $result = mysqli_query($conn, $query);
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td>" . $row['equipment_code'] . " - " . $row['equipment_name'] . "</td>";
                        echo "<td>" . (!empty($row['driver_names']) ? $row['driver_names'] : "-") . "</td>";

                        echo "<td>" . $row['suppliers_name'] . "</td>";

                        echo "<td>" . (!empty($row['total_equipment_hours']) ? $row['total_equipment_hours'] : '0') . "</td>";
                        echo "<td>" . (!empty($row['shift_hours']) ? $row['shift_hours'] : '0') . "</td>";
                        echo "<td>" . $row['start'] . "</td>";
                        echo "<td>" . $row['end'] . "</td>";
                        
                        // عرض نوع المعدة (أساسي/احتياطي)
                        $categoryText = ($row['equipment_category'] === 'أساسي') ? 'أساسي' : 'احتياطي';
                        $categoryClass = ($row['equipment_category'] === 'أساسي') ? 'basic' : 'backup';
                        echo "<td><span class='category-badge $categoryClass'>$categoryText</span></td>";
                        
                        // echo "<td>" . $row['hours'] . "</td>";
                        $status_value = intval($row['status']);
                        if ($status_value === 1) {
                            $status_label = 'ساري';
                            $status_class = 'status-running';
                        } else {
                            $status_label = 'منتهي';
                            $status_class = 'status-idle';
                        }

                        $status_cell = "<td><span class='status-pill $status_class'>$status_label</span></td>";

                        $action_buttons = "";
                        if ($status_value === 1 && $_SESSION['user']['role'] != "10" && $can_edit) {
                            $action_buttons .= "<a href='#' class='end-service-btn btn btn-sm btn-outline-secondary' data-bs-toggle='modal' data-bs-target='#endServiceModal' data-id='" . $row['id'] . "'> إنهاء خدمة </a> ";
                        } elseif ($status_value === 0 && $can_edit) {
                            $action_buttons .= "<form method='post' style='display:inline;'>
                                    <input type='hidden' name='action' value='change_status'>
                                    <input type='hidden' name='operation_id' value='" . $row['id'] . "'>
                                    <input type='hidden' name='new_status' value='1'>
                                </form> ";
                        }

                        // جلب اسم المنجم
                        $mine_name_res = mysqli_query($conn, "SELECT mine_name FROM mines WHERE id = " . intval($row['mine_id']) . " LIMIT 1");
                        $mine_name_val = ($mine_name_res && mysqli_num_rows($mine_name_res) > 0) ? mysqli_fetch_assoc($mine_name_res)['mine_name'] : '-';
                        // جلب رقم العقد
                        $contract_code_res = mysqli_query($conn, "SELECT contract_signing_date FROM contracts WHERE id = " . intval($row['contract_id']) . " LIMIT 1");
                        $contract_code_val = ($contract_code_res && mysqli_num_rows($contract_code_res) > 0) ? mysqli_fetch_assoc($contract_code_res)['contract_signing_date'] : '-';

                                                echo $status_cell;
                                                echo "<td>
                                                                <div class='action-btns'>
                                                            " . ($can_view ? "<a href='javascript:void(0)' class='action-btn view viewOperationBtn'
                                                                 data-id='" . $row['id'] . "'
                                                                 data-equipment='" . htmlspecialchars($row['equipment_code'] . ' - ' . $row['equipment_name'], ENT_QUOTES) . "'
                                                                 data-supplier='" . htmlspecialchars($row['suppliers_name'] ?? '-', ENT_QUOTES) . "'
                                                                 data-mine='" . htmlspecialchars($mine_name_val, ENT_QUOTES) . "'
                                                                 data-contract='" . htmlspecialchars($contract_code_val, ENT_QUOTES) . "'
                                                                 data-drivers='" . htmlspecialchars(!empty($row['driver_names']) ? $row['driver_names'] : '-', ENT_QUOTES) . "'
                                                                 data-start='" . $row['start'] . "'
                                                                 data-end='" . $row['end'] . "'
                                                                 data-total-hours='" . $row['total_equipment_hours'] . "'
                                                                 data-shift-hours='" . $row['shift_hours'] . "'
                                                                 data-category='" . htmlspecialchars($row['equipment_category'], ENT_QUOTES) . "'
                                                                 data-status='" . $status_label . "'
                                                                 data-status-class='" . $status_class . "'
                                                                 data-reason='" . htmlspecialchars($row['reason'] ?? '', ENT_QUOTES) . "'
                                                                 title='عرض التفاصيل'><i class='fa fa-eye'></i></a>" : "") . "
                                                            " . ($can_edit ? "<a href='javascript:void(0)' class='action-btn edit editOperationBtn' 
                                                                 data-id='" . $row['id'] . "'
                                                                 data-equipment='" . $row['equipment'] . "'
                                                                 data-equipment-type='" . $row['equipment_type'] . "'
                                                                 data-equipment-category='" . $row['equipment_category'] . "'
                                                                 data-mine='" . $row['mine_id'] . "'
                                                                 data-contract='" . $row['contract_id'] . "'
                                                                 data-supplier='" . $row['supplier_id'] . "'
                                                                 data-start='" . $row['start'] . "'
                                                                 data-end='" . $row['end'] . "'
                                                                 data-total-hours='" . $row['total_equipment_hours'] . "'
                                                                 data-shift-hours='" . $row['shift_hours'] . "'
                                                                 data-status='" . $row['status'] . "'
                                                                 title='تعديل'><i class='fa fa-edit'></i></a>" : "") . "
                                                            " . ($can_delete ? "<a href='move_oprators.php?project_id=" . $selected_project_id . "&delete_id=" . $row['id'] . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من حذف التشغيل؟\")' title='حذف'>
                                                                <i class='fa fa-trash'></i>
                                                            </a>" : "") . "
                                                                </div>
                                                                " . $action_buttons . "
                                                            </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- مكتبة jQuery (مطلوبة أولاً) -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<!-- حزمة Bootstrap (تشمل Popper) -->
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- ملفات DataTables -->
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>

<!-- مودال عرض بيانات التشغيل -->
<div id="viewOperationModal" class="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="modal-content" style="background:#fff;border-radius:12px;max-width:700px;width:95%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <div class="modal-header" style="padding:20px 24px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,#1e40af,#2563eb);border-radius:12px 12px 0 0;">
            <h5 style="margin:0;font-weight:700;color:#ffffff;"><i class="fas fa-eye" style="color:#ffffff;margin-left:8px;"></i> تفاصيل سجل التشغيل</h5>
            <button onclick="closeViewOperationModal()" style="background:rgba(255,255,255,.2);border:none;font-size:1.4rem;cursor:pointer;color:#ffffff;line-height:1;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;">&times;</button>
        </div>
        <div class="modal-body" style="padding:24px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div style="background:#f8fafc;border-radius:8px;padding:14px;border:1px solid #e2e8f0;">
                    <div style="font-size:.75rem;color:#64748b;margin-bottom:4px;"><i class="fas fa-cogs"></i> المعدة</div>
                    <div style="font-weight:700;color:#1e293b;" id="view_op_equipment">-</div>
                </div>
                <div style="background:#f8fafc;border-radius:8px;padding:14px;border:1px solid #e2e8f0;">
                    <div style="font-size:.75rem;color:#64748b;margin-bottom:4px;"><i class="fas fa-truck"></i> المورد</div>
                    <div style="font-weight:700;color:#1e293b;" id="view_op_supplier">-</div>
                </div>
                <div style="background:#f8fafc;border-radius:8px;padding:14px;border:1px solid #e2e8f0;">
                    <div style="font-size:.75rem;color:#64748b;margin-bottom:4px;"><i class="fas fa-mountain"></i> المنجم</div>
                    <div style="font-weight:700;color:#1e293b;" id="view_op_mine">-</div>
                </div>
                <div style="background:#f8fafc;border-radius:8px;padding:14px;border:1px solid #e2e8f0;">
                    <div style="font-size:.75rem;color:#64748b;margin-bottom:4px;"><i class="fas fa-file-contract"></i> تاريخ توقيع العقد</div>
                    <div style="font-weight:700;color:#1e293b;" id="view_op_contract">-</div>
                </div>
                <div style="background:#f8fafc;border-radius:8px;padding:14px;border:1px solid #e2e8f0;">
                    <div style="font-size:.75rem;color:#64748b;margin-bottom:4px;"><i class="fas fa-id-badge"></i> السائقون</div>
                    <div style="font-weight:700;color:#1e293b;" id="view_op_drivers">-</div>
                </div>
                <div style="background:#f8fafc;border-radius:8px;padding:14px;border:1px solid #e2e8f0;">
                    <div style="font-size:.75rem;color:#64748b;margin-bottom:4px;"><i class="fas fa-check-circle"></i> نوع المعدة</div>
                    <div style="font-weight:700;color:#1e293b;" id="view_op_category">-</div>
                </div>
                <div style="background:#f8fafc;border-radius:8px;padding:14px;border:1px solid #e2e8f0;">
                    <div style="font-size:.75rem;color:#64748b;margin-bottom:4px;"><i class="fas fa-calendar-alt"></i> تاريخ البداية</div>
                    <div style="font-weight:700;color:#1e293b;" id="view_op_start">-</div>
                </div>
                <div style="background:#f8fafc;border-radius:8px;padding:14px;border:1px solid #e2e8f0;">
                    <div style="font-size:.75rem;color:#64748b;margin-bottom:4px;"><i class="fas fa-calendar-check"></i> تاريخ النهاية</div>
                    <div style="font-weight:700;color:#1e293b;" id="view_op_end">-</div>
                </div>
                <div style="background:#f8fafc;border-radius:8px;padding:14px;border:1px solid #e2e8f0;">
                    <div style="font-size:.75rem;color:#64748b;margin-bottom:4px;"><i class="fas fa-clock"></i> ساعات العمل الكلية</div>
                    <div style="font-weight:700;color:#1e293b;" id="view_op_total_hours">-</div>
                </div>
                <div style="background:#f8fafc;border-radius:8px;padding:14px;border:1px solid #e2e8f0;">
                    <div style="font-size:.75rem;color:#64748b;margin-bottom:4px;"><i class="fas fa-hourglass-half"></i> ساعات الوردية</div>
                    <div style="font-weight:700;color:#1e293b;" id="view_op_shift_hours">-</div>
                </div>
                <div style="background:#f8fafc;border-radius:8px;padding:14px;border:1px solid #e2e8f0;grid-column:span 2;">
                    <div style="font-size:.75rem;color:#64748b;margin-bottom:4px;"><i class="fas fa-toggle-on"></i> الحالة</div>
                    <div id="view_op_status">-</div>
                </div>
                <div id="view_op_reason_block" style="background:#fff3cd;border-radius:8px;padding:14px;border:1px solid #ffc107;grid-column:span 2;display:none;">
                    <div style="font-size:.75rem;color:#92400e;margin-bottom:4px;"><i class="fas fa-info-circle"></i> سبب الإنهاء</div>
                    <div style="font-weight:600;color:#92400e;" id="view_op_reason">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="padding:16px 24px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:10px;">
            <?php if ($can_edit): ?>
            <button type="button" id="viewOpEditBtn" onclick="triggerEditFromView()" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:10px 20px;font-weight:600;cursor:pointer;">
                <i class="fas fa-edit"></i> تعديل
            </button>
            <?php endif; ?>
            <button type="button" onclick="closeViewOperationModal()" style="background:#6b7280;color:#fff;border:none;border-radius:8px;padding:10px 20px;font-weight:600;cursor:pointer;">
                <i class="fas fa-times"></i> إغلاق
            </button>
        </div>
    </div>
</div>

<!-- موديل إنهاء الخدمة -->
<div class="modal fade" id="endServiceModal" tabindex="-1" aria-labelledby="endServiceLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="endServiceLabel">إنهاء الخدمة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="end_service" />
                    <input type="hidden" name="operation_id" id="modal_operation_id" />
                    <div class="mb-3">
                        <label for="service_end_date" class="form-label">تاريخ الإنهاء</label>
                        <input type="date" class="form-control" name="end_date" id="service_end_date" required />
                    </div>
                    <div class="mb-3">
                        <label for="service_reason" class="form-label">سبب الإنهاء</label>
                        <textarea class="form-control" name="reason" id="service_reason" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" name="end_service_cancel" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <button type="submit" name="end_service_submit" class="btn btn-danger">تأكيد الإنهاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- إضافات DataTables للاستجابة والأزرار -->
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

<script>
    function toggleOperationForm(event) {
        if (event) {
            event.preventDefault();
        }

        const form = document.getElementById('projectForm');
        if (!form) {
            return false;
        }

        if (form.classList.contains('form-hidden')) {
            const formTitle = document.getElementById('formTitle');
            if (formTitle) {
                formTitle.innerHTML = '<i class="fa fa-plus-circle"></i> اضافة تشغيل آلية جديد';
            }

            const operationId = document.getElementById('operation_id');
            const mineId = document.getElementById('mine_id');
            const contractId = document.getElementById('contract_id');
            const supplierId = document.getElementById('supplier_id');
            const typeId = document.getElementById('type');
            const equipmentId = document.getElementById('equipment');
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            const totalEquipmentHours = document.getElementById('total_equipment_hours');
            const shiftHours = document.getElementById('shift_hours');
            const status = document.getElementById('status');

            if (operationId) operationId.value = '';
            if (mineId) mineId.value = '';
            if (contractId) contractId.innerHTML = '<option value="">-- اختر العقد --</option>';
            if (supplierId) supplierId.innerHTML = '<option value="">-- اختر المورد --</option>';
            if (typeId) typeId.value = '';
            if (equipmentId) equipmentId.innerHTML = '<option value="">-- اختر المعدة --</option>';
            if (startDate) startDate.value = '';
            if (endDate) endDate.value = '';
            if (totalEquipmentHours) totalEquipmentHours.value = '0';
            if (shiftHours) shiftHours.value = '0';
            if (status) status.value = '1';

            form.classList.remove('form-hidden');
            form.style.display = 'block';
        } else {
            form.classList.add('form-hidden');
            form.style.display = 'none';
        }

        return false;
    }

    (function () {
        // تشغيل DataTable بالعربية
        // تشغيل DataTable بالعربية
        $(document).ready(function () {
            $('#projectsTable').DataTable({
                dom: 'Bfrtip', // أزرار + بحث + ترقيم الصفحات
                buttons: [
                    { extend: 'copy', text: 'نسخ' },
                    { extend: 'excel', text: 'تصدير Excel' },
                    { extend: 'csv', text: 'تصدير CSV' },
                    { extend: 'pdf', text: 'تصدير PDF' },
                    { extend: 'print', text: 'طباعة' }
                ],
                "language": {
                    "url": "https:/ems/assets/i18n/datatables/ar.json"
                }
            });
        });

    })();

    document.addEventListener('DOMContentLoaded', function () {
        const toggleFormBtn = document.getElementById('toggleForm');
        if (!toggleFormBtn) {
            return;
        }

        toggleFormBtn.addEventListener('click', function (event) {
            toggleOperationForm(event);
        });
    });

    $(document).ready(function () {
        function resetEquipment() {
            $("#equipment").html("<option value=''>-- اختر المعدة --</option>");
        }

        function resetSupplier() {
            $("#supplier_id").html("<option value=''>-- اختر المورد --</option>");
        }

        function resetStats() {
            $("#contractStats").hide();
            $("#suppliersSection").hide();
            $("#suppliersTableBody").html("<tr><td colspan='9' class='suppliers-empty'><i class='fas fa-info-circle'></i> لا توجد بيانات</td></tr>");
            $("#stat_total_hours").text("0");
            $("#stat_equipment_count").text("0");
            $("#total_supplier_hours").text("0");
            $("#total_supplier_equipment").text("0");
            $("#total_supplier_basic").text("0");
            $("#total_supplier_backup").text("0");
            $("#total_added_equipment").text("0");
            $("#total_remaining_equipment").text("0");
        }

        function renderStats(response) {
            if (!response || !response.success) {
                resetStats();
                return;
            }

            $("#contractStats").show();
            $("#stat_total_hours").text(parseFloat(response.contract.total_hours || 0).toLocaleString());
            $("#stat_equipment_count").text(parseInt(response.contract.equipment_count || 0, 10).toLocaleString());

            if (response.suppliers && response.suppliers.length > 0) {
                $("#suppliersSection").show();
                var rows = "";
                var totalAdded = 0;
                var totalRemaining = 0;
                var totalBasic = 0;
                var totalBackup = 0;

                response.suppliers.forEach(function (supplier, index) {
                    var breakdownHtml = "";
                    if (supplier.equipment_breakdown && supplier.equipment_breakdown.length > 0) {
                        breakdownHtml = supplier.equipment_breakdown.map(function (item) {
                            var addedCount = item.added_count || 0;
                            var remaining = item.remaining || 0;
                            var statusClass = remaining === 0 ? 'is-active' : (addedCount > 0 ? 'is-warning' : 'is-muted');
                            var basicInfo = item.count_basic > 0 ? '<span class="breakdown-tag is-basic">أساسي:' + item.count_basic + '</span>' : '';
                            var backupInfo = item.count_backup > 0 ? '<span class="breakdown-tag is-backup">احتياطي:' + item.count_backup + '</span>' : '';

                            return '<div class="breakdown-item">' +
                                '<i class="fas fa-tools"></i> <strong>' + (item.type || 'غير محدد') + '</strong>: ' +
                                item.count + ' متعاقد ' + basicInfo + ' ' + backupInfo + ' | ' +
                                '<span class="breakdown-count ' + statusClass + '">' + addedCount + ' مضاف</span> | ' +
                                '<span class="breakdown-count ' + (remaining === 0 ? 'is-active' : 'is-warning') + '">' + remaining + ' متبقي</span> | ' +
                                '<i class="fas fa-clock"></i> ' + parseFloat(item.hours || 0).toLocaleString() + ' ساعة' +
                                '</div>';
                        }).join('');
                    } else {
                        breakdownHtml = '<span class="breakdown-empty">لا توجد تفاصيل</span>';
                    }

                    var addedEquipment = supplier.added_to_equipments || 0;
                    var remainingEquipment = supplier.remaining_to_add || 0;
                    var supplierBasic = supplier.equipment_count_basic || 0;
                    var supplierBackup = supplier.equipment_count_backup || 0;
                    
                    totalAdded += addedEquipment;
                    totalRemaining += remainingEquipment;
                    totalBasic += supplierBasic;
                    totalBackup += supplierBackup;

                    var addedBadgeClass = 'badge-available';
                    var remainingBadgeClass = 'badge-busy';

                    if (remainingEquipment === 0) {
                        addedBadgeClass = 'badge-available';
                        remainingBadgeClass = 'badge-available';
                    } else if (addedEquipment > 0) {
                        addedBadgeClass = 'badge-working';
                        remainingBadgeClass = 'badge-working';
                    }

                    rows += '<tr>' +
                        '<td class="text-center">' + (index + 1) + '</td>' +
                        '<td><strong>' + (supplier.supplier_name || '-') + '</strong></td>' +
                        '<td class="text-center">' + parseFloat(supplier.hours || 0).toLocaleString() + '</td>' +
                        '<td class="text-center">' + (supplier.equipment_count || 0) + '</td>' +
                        '<td class="suppliers-basic-count">' + supplierBasic + '</td>' +
                        '<td class="suppliers-backup-count">' + supplierBackup + '</td>' +
                        '<td class="text-center">' +
                        '<span class="' + addedBadgeClass + '"><i class="fas fa-check"></i> ' + addedEquipment + '</span>' +
                        '</td>' +
                        '<td class="text-center">' +
                        '<span class="' + remainingBadgeClass + '"><i class="fas fa-' + (remainingEquipment === 0 ? 'check-circle' : 'exclamation-triangle') + '"></i> ' + remainingEquipment + '</span>' +
                        '</td>' +
                        '<td class="suppliers-breakdown">' + breakdownHtml + '</td>' +
                        '</tr>';
                });

                $("#suppliersTableBody").html(rows);
                $("#total_supplier_hours").text(parseFloat(response.summary.total_supplier_hours || 0).toLocaleString());
                $("#total_supplier_equipment").text(response.summary.total_supplier_equipment || 0);
                $("#total_supplier_basic").text(totalBasic);
                $("#total_supplier_backup").text(totalBackup);
                $("#total_added_equipment").text(totalAdded);
                $("#total_remaining_equipment").text(totalRemaining);
            } else {
                $("#suppliersSection").hide();
            }
        }

        function loadEquipments() {
            var type = $("#type").val();
            var supplierId = $("#supplier_id").val();
            if (type !== "" && supplierId !== "") {
                $.ajax({
                    url: "../Oprators/getoprator.php",
                    type: "GET",
                    data: { type: type, supplier_id: supplierId },
                    success: function (response) {
                        $("#equipment").html(response);
                    },
                    error: function (xhr, status, error) {
                        console.error("❌ AJAX Error:", error);
                    }
                });
            } else {
                resetEquipment();
            }
        }

        // لم نعد بحاجة لـ event listener للمشروع لأنه محدد مسبقاً من الصفحة السابقة
        
        $("#mine_id").change(function () {
            var mineId = $(this).val();
            $("#contract_id").html("<option value=''>-- اختر العقد --</option>");
            resetSupplier();
            $("#type").val("");
            resetEquipment();
            resetStats();
            $("#end_date").val("");

            if (mineId !== "") {
                $.ajax({
                    url: "../Oprators/get_mine_contracts.php",
                    type: "POST",
                    dataType: "json",
                    data: { mine_id: mineId },
                    success: function (response) {
                        if (response.success) {
                            var options = "<option value=''>-- اختر العقد --</option>";
                            response.contracts.forEach(function (contract) {
                                options += "<option value='" + contract.id + "' data-end='" + contract.end_date + "'>" + contract.display_name + "</option>";
                            });
                            $("#contract_id").html(options);
                        }
                    }
                });
            }
        });

        $("#contract_id").change(function () {
            var contractId = $(this).val();
            var endDate = $(this).find(":selected").data("end") || "";
            resetSupplier();
            $("#type").val("");
            resetEquipment();
            resetStats();
            if (endDate !== "") {
                $("#end_date").val(endDate);
            }

            if (contractId !== "") {
                $.ajax({
                    url: "../Oprators/get_contract_suppliers.php",
                    type: "POST",
                    dataType: "json",
                    data: { contract_id: contractId },
                    success: function (response) {
                        if (response.success) {
                            var options = "<option value=''>-- اختر المورد --</option>";
                            response.suppliers.forEach(function (supplier) {
                                options += "<option value='" + supplier.id + "'>" + supplier.name + "</option>";
                            });
                            $("#supplier_id").html(options);
                        }
                    }
                });

                $.ajax({
                    url: "../Oprators/get_contract_stats.php",
                    type: "GET",
                    dataType: "json",
                    data: { contract_id: contractId },
                    success: function (response) {
                        renderStats(response);
                    },
                    error: function () {
                        resetStats();
                    }
                });
            }
        });

        $("#type").change(function () {
            loadEquipments();
        });

        $("#supplier_id").change(function () {
            loadEquipments();
        });

        $(document).on("click", ".end-service-btn", function (e) {
            e.preventDefault();
            var opId = $(this).data('id');
            console.log('ðŸ”´ زر إنهاء الخدمة - ID:', opId);
        });

        $("#endServiceModal").on("show.bs.modal", function (event) {
            var button = $(event.relatedTarget);
            var opId = button.data("id") || "";
            console.log('ðŸš¨ إنهاء خدمة التشغيل رقم:', opId);
            $("#modal_operation_id").val(opId);
            $("#service_end_date").val("");
            $("#service_reason").val("");
        });
        
        // وظيفة التعديل
        $(document).on('click', '.editOperationBtn', function() {
            var btn = $(this);
            
            console.log('ðŸ”§ بدء التعديل - ID:', btn.data('id'));
            
            // تغيير عنوان النموذج
            $('#formTitle').html('<i class="fa fa-edit"></i> تعديل بيانات التشغيل');
            
            // إظهار النموذج
            $('#projectForm').removeClass('form-hidden').show();
            $('html, body').animate({scrollTop: $('#projectForm').offset().top - 100}, 500);
            
            // ملء البيانات الأساسية
            $('#operation_id').val(btn.data('id'));
            $('#start_date').val(btn.data('start'));
            $('#end_date').val(btn.data('end'));
            $('#total_equipment_hours').val(btn.data('total-hours'));
            $('#shift_hours').val(btn.data('shift-hours'));
            $('#status').val(btn.data('status'));
            $('#equipment_category').val(btn.data('equipment-category'));
            
            console.log('✅ تم ملء البيانات الأساسية');
            
            // تحميل المنجم
            var mineId = btn.data('mine');
            $('#mine_id').val(mineId);
            
            console.log('ðŸ“ تحميل العقود للمنجم:', mineId);
            
            // تحميل العقود للمنجم المحدد
            setTimeout(function() {
                $.ajax({
                    url: "../Oprators/get_mine_contracts.php",
                    type: "POST",
                    dataType: "json",
                    data: { mine_id: mineId },
                    success: function (response) {
                        console.log('ðŸ“‹ استجابة العقود:', response);
                        if (response.success) {
                            var options = "<option value=''>-- اختر العقد --</option>";
                            response.contracts.forEach(function (contract) {
                                var selected = (contract.id == btn.data('contract')) ? 'selected' : '';
                                options += "<option value='" + contract.id + "' data-end='" + contract.end_date + "' " + selected + ">" + contract.display_name + "</option>";
                            });
                            $('#contract_id').html(options);
                            
                            console.log('✅ تم تحميل العقود');
                            
                            // تحميل الموردين للعقد المحدد
                            setTimeout(function() {
                                var contractId = btn.data('contract');
                                console.log('ðŸ¢ تحميل الموردين للعقد:', contractId);
                                
                                $.ajax({
                                    url: "../Oprators/get_contract_suppliers.php",
                                    type: "POST",
                                    dataType: "json",
                                    data: { contract_id: contractId },
                                    success: function (response) {
                                        console.log('ðŸª استجابة الموردين:', response);
                                        if (response.success) {
                                            var options = "<option value=''>-- اختر المورد --</option>";
                                            response.suppliers.forEach(function (supplier) {
                                                var selected = (supplier.id == btn.data('supplier')) ? 'selected' : '';
                                                options += "<option value='" + supplier.id + "' " + selected + ">" + supplier.name + "</option>";
                                            });
                                            $('#supplier_id').html(options);
                                            
                                            console.log('✅ تم تحميل الموردين');
                                            
                                            // تحديد نوع المعدة
                                            $('#type').val(btn.data('equipment-type'));
                                            
                                            console.log('ðŸ”§ نوع المعدة:', btn.data('equipment-type'));
                                            
                                            // تحميل المعدات
                                            setTimeout(function() {
                                                console.log('ðŸšœ تحميل المعدات...');
                                                loadEquipmentsForEdit(btn.data('equipment'));
                                            }, 300);
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        console.error('❌ خطأ في تحميل الموردين:', error);
                                    }
                                });
                            }, 300);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ خطأ في تحميل العقود:', error);
                    }
                });
            }, 300);
        });
        
        // دالة تحميل المعدات مع تحديد المعدة المختارة
        function loadEquipmentsForEdit(selectedEquipmentId) {
            var typeId = $("#type").val();
            var supplierId = $("#supplier_id").val();
            
            console.log('ðŸšœ تحميل المعدات - النوع:', typeId, '| المورد:', supplierId, '| المعدة المختارة:', selectedEquipmentId);
            
            if (typeId && supplierId) {
                $.ajax({
                    url: "../Oprators/getoprator.php",
                    type: "GET",
                    data: { 
                        type: typeId,
                        supplier_id: supplierId,
                        current_equipment: selectedEquipmentId
                    },
                    success: function (data) {
                        console.log('✅ تم تحميل المعدات بنجاح');
                        $("#equipment").html(data);
                        $("#equipment").val(selectedEquipmentId);
                        console.log('✅ تم تحديد المعدة:', selectedEquipmentId);
                    },
                    error: function(xhr, status, error) {
                        console.error("❌ خطأ في تحميل المعدات:", error);
                        $("#equipment").html("<option value=''>خطأ في التحميل</option>");
                    }
                });
            } else {
                console.warn('⚠️ النوع أو المورد غير محدد');
            }
        }

        // ── مودال عرض بيانات التشغيل ──────────────────────────────────────
        var _viewOpEditData = {};

        $(document).on('click', '.viewOperationBtn', function () {
            var btn = $(this);
            $('#view_op_equipment').text(btn.data('equipment') || '-');
            $('#view_op_supplier').text(btn.data('supplier') || '-');
            $('#view_op_mine').text(btn.data('mine') || '-');
            $('#view_op_contract').text(btn.data('contract') || '-');
            $('#view_op_drivers').text(btn.data('drivers') || '-');
            $('#view_op_start').text(btn.data('start') || '-');
            $('#view_op_end').text(btn.data('end') || '-');
            $('#view_op_total_hours').text(btn.data('total-hours') || '0');
            $('#view_op_shift_hours').text(btn.data('shift-hours') || '0');
            $('#view_op_category').text(btn.data('category') || '-');

            var statusLabel = btn.data('status') || '-';
            var statusClass = btn.data('status-class') || '';
            $('#view_op_status').html("<span class='status-pill " + statusClass + "'>" + statusLabel + "</span>");

            // سبب الإنهاء - يظهر فقط للسجلات المنتهية
            var reason = btn.data('reason') || '';
            if (statusClass === 'status-idle' && reason !== '') {
                $('#view_op_reason').text(reason);
                $('#view_op_reason_block').show();
            } else {
                $('#view_op_reason').text('-');
                $('#view_op_reason_block').hide();
            }

            // حفظ data-id للتعديل
            _viewOpEditData = { id: btn.data('id') };

            $('#viewOperationModal').css('display', 'flex').hide().fadeIn(300);
        });
    });

    function closeViewOperationModal() {
        $('#viewOperationModal').fadeOut(300);
    }

    function triggerEditFromView() {
        closeViewOperationModal();
        setTimeout(function () {
            var editBtn = $('.editOperationBtn[data-id="' + _viewOpEditData.id + '"]');
            if (editBtn.length) {
                editBtn.trigger('click');
            }
        }, 350);
    }

    // إغلاق عند الضغط خارج المودال
    $(document).on('click', '#viewOperationModal', function (e) {
        if ($(e.target).is('#viewOperationModal')) {
            closeViewOperationModal();
        }
    });

    // إغلاق بـ ESC
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#viewOperationModal').is(':visible')) {
            closeViewOperationModal();
        }
    });

</script>

</div><!-- /.main -->

</body>

</html>


