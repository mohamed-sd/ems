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

$operations_has_shift_type = db_table_has_column($conn, 'operations', 'shift_type');
if (!$operations_has_shift_type) {
    @mysqli_query($conn, "ALTER TABLE operations ADD COLUMN shift_type ENUM('D','N','B') NOT NULL DEFAULT 'B' AFTER shift_hours");
    $operations_has_shift_type = db_table_has_column($conn, 'operations', 'shift_type');
}

// الحالة التشغيلية (تعمل/جاهزة/معطلة) تُدار من صفحة الحركة. هنا نقرؤها فقط لتصنيف جدول «المتعطلة».
$operations_has_op_state = db_table_has_column($conn, 'operations', 'op_state');
if (!$operations_has_op_state) {
    @mysqli_query($conn, "ALTER TABLE operations ADD COLUMN op_state ENUM('تعمل','جاهزة','معطلة') NOT NULL DEFAULT 'جاهزة' AFTER status");
    $operations_has_op_state = db_table_has_column($conn, 'operations', 'op_state');
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
$page_permissions = check_page_permissions($conn, 'Oprators/oprators.php');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

if (!$can_view) {
    header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+التشغيل+❌");
    exit();
}

$is_role10 = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10";
$user_project_id = $is_role10 ? intval($_SESSION['user']['project_id']) : 0;
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
// if ($selected_project_id == 0) {
//     echo "<script>alert('❌ لا يوجد مشروع مرتبط بحسابك في الجلسة'); window.location.href='../main/dashboard.php';</script>";
//     exit();
// }

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

// (mine filtering removed - operations filter by project_id directly)
$selected_mine = null;

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
                echo "<script>alert('❌ لا يمكن تحديد الآلية المرتبطة بهذا السجل'); window.location.href='oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
                exit();
            }
            // التحقق من عدم وجود سجل ساري آخر لنفس المعدة
            $conflict_res = mysqli_query($conn, "SELECT id FROM operations WHERE equipment = $eq_id AND status = 1 AND id != $operation_id LIMIT 1");
            if ($conflict_res && mysqli_num_rows($conflict_res) > 0) {
                $redirect_project = isset($_SESSION['operations_project_id']) ? $_SESSION['operations_project_id'] : '';
                echo "<script>alert('❌ لا يمكن إعادة تشغيل المعدة وهي تعمل بالفعل في سجل آخر'); window.location.href='oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
                exit();
            }
        }

        $update_sql = "UPDATE operations SET status = $new_status WHERE id = $operation_id AND project_id = $selected_project_id$operations_company_scope";
        $update_result = mysqli_query($conn, $update_sql);

        if ($update_result) {
            $redirect_project = isset($_SESSION['operations_project_id']) ? $_SESSION['operations_project_id'] : '';
            echo "<script>alert('✅ تم تحديث الحالة بنجاح'); window.location.href='oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
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
                        echo "<script>alert('✅ " . addslashes($approval_result['message']) . "'); window.location.href='oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
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
        echo "<script>alert('❌ ليس لديك صلاحية إنهاء الخدمة'); window.location.href='oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
        exit();
    }

    if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10") {
        $redirect_project = isset($_SESSION['operations_project_id']) ? $_SESSION['operations_project_id'] : '';
        echo "<script>alert('❌ ليس لديك صلاحية لإنهاء الخدمة'); window.location.href='oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
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
            echo "<script>alert('✅ تم إنهاء الخدمة بنجاح'); window.location.href='oprators.php" . ($redirect_project ? "?project_id=$redirect_project" : "") . "';</script>";
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
        header("Location: oprators.php" . ($redirect_project ? "?project_id=$redirect_project&msg=" : "?msg=") . "لا+توجد+صلاحية+حذف+التشغيل+❌");
        exit();
    }

    $delete_id = intval($_GET['delete_id']);
    if ($delete_id > 0) {
        $delete_sql = "DELETE FROM operations WHERE id = $delete_id AND project_id = $selected_project_id$operations_company_scope";
        if (mysqli_query($conn, $delete_sql)) {
            header("Location: oprators.php?project_id=$selected_project_id&msg=تم+حذف+التشغيل+بنجاح+✅");
            exit();
        }
        header("Location: oprators.php?project_id=$selected_project_id&msg=حدث+خطأ+أثناء+الحذف+❌");
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
<link rel="stylesheet" href="../assets/css/ems.main.all.style.css">

<style>
/* ═══════════════════════════════════════════════════════════════
   Unified Modal Design — Movement Operations View Modal
   تصميم موحد للمديول - مثل صفحة العملاء
═══════════════════════════════════════════════════════════════ */

/* Modal overlay */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0, 0, 0, 0.35);
  animation: fadeIn 0.3s ease;
}

.modal.show {
  display: flex;
  align-items: center;
  justify-content: center;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Modal content */
.modal-content.movement-view-modal-content {
  width: min(900px, 95vw);
  max-height: 85vh;
  border: 1px solid #e8dcc8;
  border-radius: 14px;
  background: linear-gradient(180deg, #fff 0%, #fdf8f0 100%);
  box-shadow: 0 22px 42px rgba(26, 18, 8, 0.25);
  overflow: hidden;
  animation: slideIn 0.35s cubic-bezier(0.4, 0, 0.2, 1) both;
  display: flex;
  flex-direction: column;
}

/* Modal header */
.modal-header.movement-view-modal-header {
  background: linear-gradient(135deg, #1a1208, #2a1b0c);
  color: #fff;
  border-bottom: 1px solid rgba(255, 207, 144, 0.22);
  padding: 14px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-shrink: 0;
}

.modal-header.movement-view-modal-header h5 {
  margin: 0;
  font-weight: 900;
  font-size: 1.15rem;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #fff;
}

.modal-header.movement-view-modal-header i {
  color: #f7931a;
  font-size: 1.1rem;
}

/* Close button */
.movement-view-modal-close {
  border: 0;
  background: rgba(255, 255, 255, 0.14);
  color: #fff;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  font-size: 1.3rem;
  line-height: 1;
  cursor: pointer;
  transition: all 0.2s ease;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.movement-view-modal-close:hover {
  background: rgba(255, 255, 255, 0.25);
  transform: rotate(90deg);
}

/* Modal body */
.modal-body.movement-view-modal-body {
  overflow-y: auto;
  padding: 16px;
  flex: 1;
  background: linear-gradient(180deg, #fff 0%, #fffbf5 100%);
}

/* Grid layout */
.movement-view-modal-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 12px;
}

/* Item card */
.movement-view-modal-item {
  border: 1px solid #e8dcc8;
  border-radius: 11px;
  padding: 12px;
  background: #fff;
  box-shadow: 0 1px 3px rgba(26, 18, 8, 0.05);
  transition: all 0.2s ease;
}

.movement-view-modal-item:hover {
  border-color: #f7931a;
  box-shadow: 0 4px 12px rgba(247, 147, 26, 0.12);
}

/* Wide items (full width) */
.movement-view-modal-item-wide {
  grid-column: 1 / -1;
}

/* Label */
.movement-view-modal-label {
  color: #6b4e2a;
  font-size: 0.81rem;
  font-weight: 800;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 5px;
}

.movement-view-modal-label i {
  color: #f7931a;
  font-size: 0.9rem;
}

/* Value */
.movement-view-modal-value {
  color: #1a1208;
  font-weight: 800;
  font-size: 0.92rem;
  word-break: break-word;
  line-height: 1.4;
}

/* Reason section */
.movement-view-modal-reason {
  background: linear-gradient(135deg, rgba(247, 147, 26, 0.08), rgba(247, 147, 26, 0.03));
  border: 1.5px solid rgba(247, 147, 26, 0.2);
}

.movement-view-modal-reason-label {
  color: #b45309;
  font-size: 0.81rem;
  font-weight: 800;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 5px;
}

.movement-view-modal-reason-label i {
  color: #b45309;
  font-size: 0.9rem;
}

.movement-view-modal-reason-value {
  color: #6b4e2a;
  font-weight: 700;
  font-size: 0.88rem;
  line-height: 1.5;
}

/* Modal footer */
.modal-footer.movement-view-modal-footer {
  border-top: 1px solid #e8dcc8;
  background: #fff;
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  flex-wrap: wrap;
  padding: 12px 16px;
  flex-shrink: 0;
}

/* Footer buttons */
.movement-view-modal-btn {
  border: none;
  border-radius: 9px;
  padding: 10px 16px;
  font-weight: 800;
  font-size: 0.92rem;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.movement-view-modal-btn-primary {
  background: linear-gradient(135deg, #1a1208, #2d200a);
  color: #fff;
  border-left: 3px solid #f7931a;
  box-shadow: 0 4px 12px rgba(247, 147, 26, 0.25);
}

.movement-view-modal-btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(247, 147, 26, 0.35);
}

.movement-view-modal-btn-secondary {
  background: #fff;
  color: #6b4e2a;
  border: 1.5px solid #e8dcc8;
}

.movement-view-modal-btn-secondary:hover {
  border-color: #a07848;
  background: #fdf8f0;
  color: #1a1208;
}

/* Responsive */
@media (max-width: 768px) {
  .movement-view-modal-grid {
    grid-template-columns: 1fr;
  }

  .modal-content.movement-view-modal-content {
    width: 98vw;
    max-height: 90vh;
  }
}

/* ── خلفية main العامة بيضاء (#fff) ── */
.main.movement-ops-page {
  background: #fff;
}

/* ── عنوان الفورم (التبويب الذهبي) خلف كارد الفورم تماماً كصفحة المشاريع ──
   main_admin_style.css يضيف animation/transform على .card فينشئ سياق تراصّ
   (stacking context) يحبس z-index جسم الفورم (1000) داخل الكارد، فيظهر العنوان
   فوقه. إلغاء الـ transform يعيد الجسم ليطغى على أسفل العنوان كما في المشاريع. */
.movement-ops-page .allforms .card {
  animation: none !important;
  transform: none !important;
}
</style>

<div class="main movement-page movement-ops-page">

    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_icon = 'fas fa-cogs';
    $header_title_html = 'إدارة التشغيل  <i class="fas fa-project-diagram"></i> ' . htmlspecialchars($selected_project['name']);
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'movement-topbar-btn movement-topbar-btn-primary add-btn', 'icon' => 'fa fa-plus-circle', 'label' => 'إضافة تشغيل جديد');
    }
    $header_actions[] = array('href' => 'project_drivers.php?project_id=' . intval($selected_project_id), 'class' => 'movement-topbar-btn', 'icon' => 'fas fa-id-badge', 'label' => 'سائقي المشروع');
    if ($_SESSION['user']['role'] != "10") {
        $header_actions[] = array('href' => 'select_project.php', 'class' => 'movement-topbar-btn', 'icon' => 'fas fa-exchange-alt', 'label' => 'تغيير المشروع');
    }
    // ── نظام Excel الموحّد (Unified Excel Framework) ──
    require_once __DIR__ . '/../includes/excel_ui.php';
    foreach (ems_excel_header_actions('operations', 'حركات التشغيل', $can_add) as $__xlAction) { $header_actions[] = $__xlAction; }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

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
            <form id="projectForm" action="" method="post" class="allforms">

              <div class="card-header">
                        <h5><i class="fas fa-edit"></i> <span id="formTitle">اضافة تشغيل آلية جديد</span></h5>
                    </div>

                <div class="card shadow-sm pu-form-card">
                    <div class="card-body">
                        <div class="form-grid">
                            <!-- المعرّف أثناء التعديل -->
                            <input type="hidden" name="operation_id" id="operation_id" value="">

                            <!-- المشروع مخفي لأنه محدد مسبقاً -->
                            <input type="hidden" name="project_id" id="project_id"
                                value="<?php echo $selected_project_id; ?>">

                            <!-- العقود -->
                            <div>
                                <label><i class="fas fa-file-contract"></i> العقد</label>
                                <select name="contract_id" id="contract_id" required>
                                    <option value="">-- اختر العقد --</option>
                                </select>
                            </div>

                            <!-- المورد -->
                            <div>
                                <label><i class="fas fa-truck"></i> المورد</label>
                                <select name="supplier_id" id="supplier_id" required>
                                    <option value="">-- اختر المورد --</option>
                                </select>
                            </div>

                            <div>
                                <label><i class="fas fa-cogs"></i> نوع المعدة</label>
                                <select name="type" id="type" required>
                                    <option value=""> -- حدد نوع المعدة --- </option>
                                    <?php
                                    $type_query = "SELECT id, type FROM equipments_types WHERE status = 1 ORDER BY type";
                                    $type_result = mysqli_query($conn, $type_query);
                                    if ($type_result) {
                                        while ($type_row = mysqli_fetch_assoc($type_result)) {
                                            echo "<option value='" . intval($type_row['id']) . "'> " . htmlspecialchars($type_row['type']) . " </option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div>
                                <label><i class="fas fa-tractor"></i> المعدة</label>
                                <select name="equipment" id="equipment" required>
                                    <option value="">-- اختر المعدة --</option>
                                    <!-- سيتم ملؤها ديناميكيًا عبر AJAX -->
                                </select>
                            </div>

                            <div>
                                <label><i class="fas fa-check-circle"></i> فئة المعدة</label>
                                <select name="equipment_category" id="equipment_category" required>
                                    <option value="">-- أساسي / احتياطي --</option>
                                    <option value="أساسي"> أساسي</option>
                                    <option value="احتياطي"> احتياطي</option>
                                </select>
                            </div>

                            <div>
                                <label><i class="fas fa-calendar-alt"></i> تاريخ البداية</label>
                                <input type="date" name="start" id="start_date" required placeholder="تاريخ البداية" />
                            </div>

                            <div>
                                <label><i class="fas fa-calendar-check"></i> تاريخ النهاية</label>
                                <input type="date" name="end" id="end_date" required placeholder="تاريخ النهاية" />
                            </div>

                            <input type="hidden" step="0.01" name="hours" placeholder="عدد الساعات" value="0" />

                            <div>
                                <label><i class="fa fa-clock"></i> عدد ساعات العمل للآلية</label>
                                <input type="number" name="total_equipment_hours" id="total_equipment_hours" step="0.01"
                                    placeholder="إجمالي ساعات العمل" value="0" required />
                            </div>

                            <div>
                                <label><i class="fa fa-hourglass-half"></i> عدد ساعات الوردية</label>
                                <input type="number" name="shift_hours" id="shift_hours" step="0.01"
                                    placeholder="ساعات الوردية" value="0" required />
                            </div>

                            <div>
                                <label><i class="fa fa-sync-alt"></i> نظام الوردية</label>
                                <select name="shift_type" id="shift_type" required>
                                    <option value="D">نهاري فقط</option>
                                    <option value="N">ليلي فقط</option>
                                    <option value="B" selected>نهاري + ليلي</option>
                                </select>
                            </div>

                            <div>
                                <label><i class="fas fa-toggle-on"></i> الحالة</label>
                                <select name="status" id="status" required>
                                    <option value="1">ساري</option>
                                    <option value="0">منتهي</option>
                                </select>
                            </div>

                            <input type="hidden" name="action" value="save_operation" />
                        </div>

                        <div class="pu-form-actions">
                            <button type="submit" name="save_operation_submit" id="save_operation_submit" class="btn-submit">
                                <i class="fas fa-save"></i> <span>حفظ التشغيل</span>
                            </button>
                            <button type="button" id="operationFormCancel" class="btn-cancel">
                                <i class="fas fa-times"></i> إلغاء
                            </button>
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
                    <table class="alltables">
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
            <div class="card-body">
                <!-- ===== جدول المعدات الأساسية ===== -->
                <h6 style="margin:6px 14px 8px;font-size:15px;font-weight:800;color:#1a1208;display:flex;align-items:center;gap:8px;"><span class="legend-dot legend-basic">■</span> المعدات الأساسية</h6>
                <div class="tbl-scroll-wrap tbl-scroll-zero">
                    <table id="primaryTable" class="display nowrap table-full-width">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المعدة</th>
                                <th>نوع المعدة</th>
                                <th>المورد</th>
                                <!-- <th>ساعات العمل الكلية</th> -->
                                <th>ساعات الوردية</th>
                                <th>نظام الوردية</th>

                                <th>تاريخ البداية</th>
                                <!-- <th>تاريخ النهاية</th> -->                                <!-- <th>عدد الساعات</th> -->
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
                                    echo "<script>alert('❌ ليس لديك صلاحية تعديل التشغيل'); window.location.href='oprators.php?project_id=$selected_project_id';</script>";
                                    exit();
                                }
                                if ($operation_id === 0 && !$can_add) {
                                    echo "<script>alert('❌ ليس لديك صلاحية إضافة تشغيل جديد'); window.location.href='oprators.php?project_id=$selected_project_id';</script>";
                                    exit();
                                }

                                $equipment = intval($_POST['equipment']);
                                $project_id = intval($_POST['project_id']);
                                $contract_id = intval($_POST['contract_id']);
                                $supplier_id = intval($_POST['supplier_id']);
                                $equipment_type = intval($_POST['type']);
                                $equipment_category = mysqli_real_escape_string($conn, $_POST['equipment_category']);

                                $start = mysqli_real_escape_string($conn, $_POST['start']);
                                $end = mysqli_real_escape_string($conn, $_POST['end']);
                                $hours = floatval($_POST['hours']);
                                $total_equipment_hours = floatval($_POST['total_equipment_hours']);
                                $shift_hours = floatval($_POST['shift_hours']);
                                $shift_type_raw = isset($_POST['shift_type']) ? strval($_POST['shift_type']) : 'B';
                                $allowed_shift_types = array('D', 'N', 'B');
                                $shift_type = in_array($shift_type_raw, $allowed_shift_types, true) ? $shift_type_raw : 'B';
                                $shift_type_escaped = mysqli_real_escape_string($conn, $shift_type);
                                $status = intval($_POST['status']);

                                // التحقق من عدم وجود سجل ساري آخر لنفس المعدة
                                if ($status === 1 && $equipment > 0) {
                                    $exclude_id = $operation_id > 0 ? " AND id != $operation_id" : "";
                                    $conflict_check = mysqli_query($conn, "SELECT id FROM operations WHERE equipment = $equipment AND status = 1$exclude_id LIMIT 1");
                                    if ($conflict_check && mysqli_num_rows($conflict_check) > 0) {
                                        echo "<script>alert('❌ لا يمكن تشغيل المعدة وهي تعمل بالفعل في تشغيل آخر'); window.location.href='oprators.php?project_id=$selected_project_id';</script>";
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
                                    contract_id = '$contract_id',
                                    supplier_id = '$supplier_id',
                                    start = '$start',
                                    end = '$end',
                                    days = '$hours',
                                    total_equipment_hours = '$total_equipment_hours',
                                    shift_hours = '$shift_hours',
                                    shift_type = '$shift_type_escaped',
                                    status = '$status_escaped'
                                        WHERE id = $operation_id AND project_id = '$project_id'$operations_company_scope";
                                    mysqli_query($conn, $sql);
                                    echo "<script>alert('✅ تم التحديث بنجاح'); window.location.href='oprators.php?project_id=$selected_project_id';</script>";
                                } else {
                                    // إضافة سجل جديد
                                    $insert_company_col = (!$is_super_admin && $operations_has_company) ? ", company_id" : "";
                                    $insert_company_val = (!$is_super_admin && $operations_has_company) ? ", '$company_id'" : "";
                                     mysqli_query($conn, "INSERT INTO operations (equipment, equipment_type, equipment_category, project_id, contract_id, supplier_id, start, end, days, total_equipment_hours, shift_hours, shift_type, status$insert_company_col)
                                         VALUES ('$equipment', '$equipment_type', '$equipment_category', '$project_id', '$contract_id', '$supplier_id', '$start', '$end', '$hours', '$total_equipment_hours', '$shift_hours', '$shift_type_escaped', '$status_escaped'$insert_company_val)");
                                    // سجل «إضافة لمشروع» في سجل تحركات الآلية.
                                    $new_op_id = intval(mysqli_insert_id($conn));
                                    if ($new_op_id > 0) {
                                        require_once __DIR__ . '/../includes/equipment_log_helper.php';
                                        $ev_opts = ['project_id' => intval($project_id), 'operation_id' => $new_op_id];
                                        if (intval($company_id) > 0) { $ev_opts['company_id'] = intval($company_id); }
                                        log_equipment_event($conn, intval($equipment), 'إضافة لمشروع', $ev_opts);
                                    }
                                    echo "<script>alert('✅ تم الحفظ بنجاح'); window.location.href='oprators.php?project_id=$selected_project_id';</script>";
                                }
                            }

                            // جلب بيانات التشغيل - فلتر بالمشروع
                            $operations_scope_sql = (!$is_super_admin && $operations_has_company) ? " AND o.company_id = $company_id" : "";

                            $op_state_col = $operations_has_op_state ? "o.op_state" : "'جاهزة' AS op_state";
                            $query = "SELECT o.id, o.equipment, o.equipment_type, o.equipment_category, $op_state_col, o.contract_id, o.supplier_id,
                             o.start, o.end, o.days, o.total_equipment_hours, o.shift_hours, o.shift_type, o.status, o.reason,
                             e.code AS equipment_code, e.name AS equipment_name, e.type AS equipment_type_id,
                             et.type AS equipment_type_name,
                             p.name AS project_name, s.name AS suppliers_name,
                             IFNULL(GROUP_CONCAT(DISTINCT d.name SEPARATOR ', '), '') AS driver_names
                      FROM operations o
                      LEFT JOIN equipments e ON o.equipment = e.id
                      LEFT JOIN equipments_types et ON e.type = et.id
                      LEFT JOIN project p ON o.project_id = p.id
                      LEFT JOIN suppliers s ON e.suppliers = s.id
                      LEFT JOIN equipment_drivers ed ON o.equipment = ed.equipment_id
                      LEFT JOIN employees d ON ed.driver_id = d.id
                      WHERE o.project_id = $selected_project_id$operations_scope_sql
                      GROUP BY o.id
                      ORDER BY o.id DESC";
                            $result = mysqli_query($conn, $query);
                            // تقسيم التشغيلات: المنتهية (status=0) في جدول مستقل (تاريخ فقط، بلا حالة).
                            // السارية: المعطلة (op_state='معطلة') في جدول مستقل بصرف النظر عن الدور؛
                            // والباقي يُقسَّم بالدور أساسي/احتياطي (الدور ثابت ولا يتأثّر بالحالة).
                            $primary_rows = [];
                            $reserve_rows = [];
                            $broken_rows = [];
                            $ended_rows = [];
                            if ($result) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    if (intval($row['status']) !== 1) {
                                        $ended_rows[] = $row;
                                    } elseif (($row['op_state'] ?? '') === 'معطلة') {
                                        $broken_rows[] = $row;
                                    } elseif (($row['equipment_category'] ?? '') === 'أساسي') {
                                        $primary_rows[] = $row;
                                    } else {
                                        $reserve_rows[] = $row;
                                    }
                                }
                            }
                            // دالة رسم صف تشغيل واحد (تُستخدم للجدولين)
                            $render_op_row = function ($row, $i) use ($conn, $can_view, $can_edit, $can_delete, $selected_project_id) {
                                echo "<tr>";
                                echo "<td>" . $i . "</td>";
                                echo "<td>" . $row['equipment_code'] . " - " . $row['equipment_name'] . "</td>";
                                echo "<td>" . (!empty($row['equipment_type_name']) ? htmlspecialchars($row['equipment_type_name']) : "-") . "</td>";

                                echo "<td>" . $row['suppliers_name'] . "</td>";

                                // echo "<td>" . (!empty($row['total_equipment_hours']) ? $row['total_equipment_hours'] : '0') . "</td>";
                                echo "<td>" . (!empty($row['shift_hours']) ? $row['shift_hours'] : '0') . "</td>";

                                $shift_type_label = 'نهاري + ليلي';
                                if (isset($row['shift_type']) && $row['shift_type'] === 'D') {
                                    $shift_type_label = 'نهاري فقط';
                                } elseif (isset($row['shift_type']) && $row['shift_type'] === 'N') {
                                    $shift_type_label = 'ليلي فقط';
                                }
                                echo "<td>" . $shift_type_label . "</td>";

                                echo "<td>" . $row['start'] . "</td>";
                                // echo "<td>" . $row['end'] . "</td>";

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
                                    $action_buttons .= "<form method='post' class='operation-inline-form'>
                                    <input type='hidden' name='action' value='change_status'>
                                    <input type='hidden' name='operation_id' value='" . $row['id'] . "'>
                                    <input type='hidden' name='new_status' value='1'>
                                </form> ";
                                }

                                // جلب رقم العقد
                                $contract_code_res = mysqli_query($conn, "SELECT contract_signing_date FROM contracts WHERE id = " . intval($row['contract_id']) . " LIMIT 1");
                                $contract_code_val = ($contract_code_res && mysqli_num_rows($contract_code_res) > 0) ? mysqli_fetch_assoc($contract_code_res)['contract_signing_date'] : '-';

                                echo $status_cell;
                                echo "<td>
                                                                <div class='action-btns'>
                                                            " . ($can_view ? "<a href='javascript:void(0)' class='action-btn view viewOperationBtn'
                                                                 data-id='" . $row['id'] . "'
                                                                 data-equipment='" . htmlspecialchars($row['equipment_code'] . ' - ' . $row['equipment_name'], ENT_QUOTES) . "'
                                                                 data-equipment-type='" . htmlspecialchars($row['equipment_type_name'] ?? '-', ENT_QUOTES) . "'
                                                                 data-supplier='" . htmlspecialchars($row['suppliers_name'] ?? '-', ENT_QUOTES) . "'
                                                                 data-contract='" . htmlspecialchars($contract_code_val, ENT_QUOTES) . "'
                                                                 data-drivers='" . htmlspecialchars(!empty($row['driver_names']) ? $row['driver_names'] : '-', ENT_QUOTES) . "'
                                                                 data-start='" . $row['start'] . "'
                                                                 data-end='" . $row['end'] . "'
                                                                 data-total-hours='" . $row['total_equipment_hours'] . "'
                                                                 data-shift-hours='" . $row['shift_hours'] . "'
                                                                 data-shift-type='" . htmlspecialchars($row['shift_type'] ?? 'B', ENT_QUOTES) . "'
                                                                 data-shift-type-label='" . htmlspecialchars($shift_type_label, ENT_QUOTES) . "'
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
                                                                 data-contract='" . $row['contract_id'] . "'
                                                                 data-supplier='" . $row['supplier_id'] . "'
                                                                 data-start='" . $row['start'] . "'
                                                                 data-end='" . $row['end'] . "'
                                                                 data-total-hours='" . $row['total_equipment_hours'] . "'
                                                                 data-shift-hours='" . $row['shift_hours'] . "'
                                                                data-shift-type='" . htmlspecialchars($row['shift_type'] ?? 'B', ENT_QUOTES) . "'
                                                                 data-status='" . $row['status'] . "'
                                                                 title='تعديل'><i class='fa fa-edit'></i></a>" : "") . "
                                                            " . ($can_delete ? "<a href='oprators.php?project_id=" . $selected_project_id . "&delete_id=" . $row['id'] . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من حذف التشغيل؟\")' title='حذف'>
                                                                <i class='fa fa-trash'></i>
                                                            </a>" : "") . "
                                                                </div>
                                                                " . $action_buttons . "
                                                            </td>";
                                echo "</tr>";
                            };
                            // عرض صفوف الجدول الأول: المعدات الأساسية
                            if (empty($primary_rows)) {
                                echo "<tr><td colspan='9' style='text-align:center;color:#999;padding:16px;'>لا توجد معدات أساسية</td></tr>";
                            } else {
                                $i = 1;
                                foreach ($primary_rows as $r) { $render_op_row($r, $i++); }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- ===== جدول المعدات الاحتياطية ===== -->
                <h6 style="margin:18px 14px 8px;font-size:15px;font-weight:800;color:#1a1208;display:flex;align-items:center;gap:8px;"><span class="legend-dot legend-backup">■</span> المعدات الاحتياطية</h6>
                <div class="tbl-scroll-wrap tbl-scroll-zero">
                    <table id="reserveTable" class="display nowrap table-full-width">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المعدة</th>
                                <th>نوع المعدة</th>                                <th>المورد</th>
                                <th>ساعات الوردية</th>
                                <th>نظام الوردية</th>
                                <th>تاريخ البداية</th>                                <th>الحالة</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // عرض صفوف الجدول الثاني: المعدات الاحتياطية
                            if (empty($reserve_rows)) {
                                echo "<tr><td colspan='9' style='text-align:center;color:#999;padding:16px;'>لا توجد معدات احتياطية</td></tr>";
                            } else {
                                $i = 1;
                                foreach ($reserve_rows as $r) { $render_op_row($r, $i++); }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- ===== جدول المعدات المتعطلة ===== -->
                <h6 style="margin:18px 14px 8px;font-size:15px;font-weight:800;color:#1a1208;display:flex;align-items:center;gap:8px;"><span class="legend-dot" style="color:#c0392b;">■</span> المعدات المتعطلة</h6>
                <div class="tbl-scroll-wrap tbl-scroll-zero">
                    <table id="brokenTable" class="display nowrap table-full-width">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المعدة</th>
                                <th>نوع المعدة</th>
                                <th>المورد</th>
                                <th>ساعات الوردية</th>
                                <th>نظام الوردية</th>
                                <th>تاريخ البداية</th>
                                <th>الحالة</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // عرض صفوف الجدول الثالث: المعدات المتعطلة
                            if (empty($broken_rows)) {
                                echo "<tr><td colspan='9' style='text-align:center;color:#999;padding:16px;'>لا توجد معدات متعطلة</td></tr>";
                            } else {
                                $i = 1;
                                foreach ($broken_rows as $r) { $render_op_row($r, $i++); }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- ===== جدول التشغيلات المنتهية ===== -->
                <h6 style="margin:18px 14px 8px;font-size:15px;font-weight:800;color:#1a1208;display:flex;align-items:center;gap:8px;"><span class="legend-dot" style="color:#9aa0a6;">■</span> التشغيلات المنتهية</h6>
                <div class="tbl-scroll-wrap tbl-scroll-zero">
                    <table id="endedTable" class="display nowrap table-full-width">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المعدة</th>
                                <th>نوع المعدة</th>
                                <th>المورد</th>
                                <th>ساعات الوردية</th>
                                <th>نظام الوردية</th>
                                <th>تاريخ البداية</th>
                                <th>الحالة</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // عرض الجدول الثالث: التشغيلات المنتهية (status=0)
                            if (empty($ended_rows)) {
                                echo "<tr><td colspan='9' style='text-align:center;color:#999;padding:16px;'>لا توجد تشغيلات منتهية</td></tr>";
                            } else {
                                $i = 1;
                                foreach ($ended_rows as $r) { $render_op_row($r, $i++); }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- مكتبة jQuery (مطلوبة أولاً) -->
        <script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
        <!-- Bootstrap محمّل مسبقاً في inheader.php (عدم تكراره يمنع تكرار تهيئة المودال) -->
        <!-- ملفات DataTables -->
        <script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>

        <!-- مودال عرض بيانات التشغيل -->
        <!-- نافذة تفاصيل سجل التشغيل تُولَّد عبر EmsDetailsModal -->

        <!-- موديل إنهاء الخدمة -->
        <style>
        /* الحل الأمتن: لا backdrop منفصل (data-bs-backdrop="false")؛ المودال نفسه يُعتّم
           الخلفية. الـbackdrop المنفصل لبوتستراب كان يُرسَم فوق المحتوى فيمنع الكتابة. */
        #endServiceModal.modal { background: rgba(15,23,42,.55) !important; -webkit-backdrop-filter: blur(3px); backdrop-filter: blur(3px); }
        #endServiceModal .modal-dialog { pointer-events: auto; }
        #endServiceModal .modal-content { pointer-events: auto; position: relative; }
        </style>
        <div class="modal fade" id="endServiceModal" tabindex="-1" data-bs-backdrop="false" data-bs-keyboard="true" aria-labelledby="endServiceLabel" aria-hidden="true">
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
                                <input type="date" class="form-control" name="end_date" id="service_end_date"
                                    required />
                            </div>
                            <div class="mb-3">
                                <label for="service_reason" class="form-label">سبب الإنهاء</label>
                                <textarea class="form-control" name="reason" id="service_reason" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" name="end_service_cancel" class="btn btn-secondary"
                                data-bs-dismiss="modal">إغلاق</button>
                            <button type="submit" name="end_service_submit" class="btn btn-danger">تأكيد
                                الإنهاء</button>
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

                if (!form.classList.contains('allforms-visible')) {
                    const formTitle = document.getElementById('formTitle');
                    if (formTitle) {
                        formTitle.textContent = 'اضافة تشغيل آلية جديد';
                    }

                    const operationId = document.getElementById('operation_id');
                    const contractId = document.getElementById('contract_id');
                    const supplierId = document.getElementById('supplier_id');
                    const typeId = document.getElementById('type');
                    const equipmentId = document.getElementById('equipment');
                    const startDate = document.getElementById('start_date');
                    const endDate = document.getElementById('end_date');
                    const totalEquipmentHours = document.getElementById('total_equipment_hours');
                    const shiftHours = document.getElementById('shift_hours');
                    const shiftType = document.getElementById('shift_type');
                    const status = document.getElementById('status');

                    if (operationId) operationId.value = '';
                    if (contractId) contractId.innerHTML = '<option value="">-- جاري تحميل العقود... --</option>';
                    if (supplierId) supplierId.innerHTML = '<option value="">-- اختر المورد --</option>';
                    if (typeId) typeId.value = '';
                    if (equipmentId) equipmentId.innerHTML = '<option value="">-- اختر المعدة --</option>';
                    if (startDate) startDate.value = '';
                    if (endDate) endDate.value = '';
                    if (totalEquipmentHours) totalEquipmentHours.value = '0';
                    if (shiftHours) shiftHours.value = '0';
                    if (shiftType) shiftType.value = 'B';
                    if (status) status.value = '1';

                    form.classList.add('allforms-visible');

                    // تحميل عقود المشروع تلقائياً
                    var sessionProjectId = <?php echo $selected_project_id; ?>;
                    if (sessionProjectId > 0) {
                        $.ajax({
                            url: "../Oprators/get_mine_contracts.php",
                            type: "POST",
                            dataType: "json",
                            data: { project_id: sessionProjectId },
                            success: function (response) {
                                if (response.success) {
                                    var opts = "<option value=''>-- اختر العقد --</option>";
                                    response.contracts.forEach(function (c) {
                                        opts += "<option value='" + c.id + "' data-end='" + c.end_date + "'>" + c.display_name + "</option>";
                                    });
                                    $("#contract_id").html(opts);
                                } else {
                                    $("#contract_id").html("<option value=''>-- لا توجد عقود --</option>");
                                }
                            },
                            error: function () {
                                $("#contract_id").html("<option value=''>-- خطأ في تحميل العقود --</option>");
                            }
                        });
                    }
                } else {
                    form.classList.remove('allforms-visible');
                }

                return false;
            }

            (function () {
                // تشغيل DataTable بالعربية
                // تشغيل DataTable بالعربية
                $(document).ready(function () {
                    function opsTableConfig() {
                        return {
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
                        };
                    }
                    $('#primaryTable').DataTable(opsTableConfig());
                    $('#reserveTable').DataTable(opsTableConfig());
                    $('#brokenTable').DataTable(opsTableConfig());
                    $('#endedTable').DataTable(opsTableConfig());
                });

            })();

            document.addEventListener('DOMContentLoaded', function () {
                const toggleFormBtn = document.getElementById('toggleForm');
                if (toggleFormBtn) {
                    toggleFormBtn.addEventListener('click', function (event) {
                        toggleOperationForm(event);
                    });
                }

                // زر الإلغاء: يطوي نموذج الإضافة/التعديل (بنفس سلوك شاشة العملاء)
                const cancelFormBtn = document.getElementById('operationFormCancel');
                if (cancelFormBtn) {
                    cancelFormBtn.addEventListener('click', function () {
                        const form = document.getElementById('projectForm');
                        if (form) {
                            form.classList.remove('allforms-visible');
                        }
                    });
                }
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
                                    var outTag = item.out_of_contract ? ' <span class="breakdown-tag" style="background:#fdeaea;color:#b91c1c;font-weight:700;">⚠ خارج العقد</span>' : '';

                                    return '<div class="breakdown-item"' + (item.out_of_contract ? ' style="border-right:3px solid #b91c1c;padding-right:6px;"' : '') + '>' +
                                        '<i class="fas fa-tools"></i> <strong>' + (item.type || 'غير محدد') + '</strong>' + outTag + ': ' +
                                        item.count + ' متعاقد ' + basicInfo + ' ' + backupInfo + ' | ' +
                                        '<span class="breakdown-count ' + statusClass + '">' + addedCount + ' مضاف</span> | ' +
                                        (remaining < 0
                                            ? '<span class="breakdown-count is-warning">تجاوز ' + Math.abs(remaining) + '</span> | '
                                            : '<span class="breakdown-count ' + (remaining === 0 ? 'is-active' : 'is-warning') + '">' + remaining + ' متبقي</span> | ') +
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
                                (remainingEquipment < 0
                                    ? '<span class="badge-busy"><i class="fas fa-exclamation-triangle"></i> تجاوز ' + Math.abs(remainingEquipment) + '</span>'
                                    : '<span class="' + remainingBadgeClass + '"><i class="fas fa-' + (remainingEquipment === 0 ? 'check-circle' : 'exclamation-triangle') + '"></i> ' + remainingEquipment + '</span>') +
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

                // لم نعد بحاجة لـ event listener للمنجم - المنجم محدد من الجلسة تلقائياً

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
                $(document).on('click', '.editOperationBtn', function () {
                    var btn = $(this);

                    console.log('ðŸ”§ بدء التعديل - ID:', btn.data('id'));

                    // تغيير عنوان النموذج
                    $('#formTitle').text('تعديل بيانات التشغيل');

                    // إظهار النموذج
                    $('#projectForm').addClass('allforms-visible').show();
                    $('html, body').animate({ scrollTop: $('#projectForm').offset().top - 100 }, 500);

                    // ملء البيانات الأساسية
                    $('#operation_id').val(btn.data('id'));
                    $('#start_date').val(btn.data('start'));
                    $('#end_date').val(btn.data('end'));
                    $('#total_equipment_hours').val(btn.data('total-hours'));
                    $('#shift_hours').val(btn.data('shift-hours'));
                    $('#shift_type').val(btn.data('shift-type') || 'B');
                    $('#status').val(btn.data('status'));
                    $('#equipment_category').val(btn.data('equipment-category'));

                    console.log('✅ تم ملء البيانات الأساسية');

                    // تحميل عقود المشروع المحدد
                    var editProjectId = <?php echo $selected_project_id; ?>;

                    // تحميل العقود للمشروع المحدد
                    setTimeout(function () {
                        $.ajax({
                            url: "../Oprators/get_mine_contracts.php",
                            type: "POST",
                            dataType: "json",
                            data: { project_id: editProjectId },
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
                                    setTimeout(function () {
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
                                                    setTimeout(function () {
                                                        console.log('ðŸšœ تحميل المعدات...');
                                                        loadEquipmentsForEdit(btn.data('equipment'));
                                                    }, 300);
                                                }
                                            },
                                            error: function (xhr, status, error) {
                                                console.error('❌ خطأ في تحميل الموردين:', error);
                                            }
                                        });
                                    }, 300);
                                }
                            },
                            error: function (xhr, status, error) {
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
                            error: function (xhr, status, error) {
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
                    var statusLabel = btn.data('status') || '-';
                    var statusClass = btn.data('status-class') || '';
                    var reason = btn.data('reason') || '';
                    var opId = btn.data('id');

                    var fields = [
                        { label: 'المعدة', value: btn.data('equipment'), icon: 'fas fa-cogs', size: 'lg' },
                        { label: 'تصنيف المعدة', value: btn.data('equipment-type'), icon: 'fas fa-tools' },
                        { label: 'المورد', value: btn.data('supplier'), icon: 'fas fa-truck', size: 'lg' },
                        { label: 'المنجم', value: btn.data('mine'), icon: 'fas fa-mountain' },
                        { label: 'تاريخ توقيع العقد', value: btn.data('contract'), icon: 'fas fa-file-contract' },
                        { label: 'السائقون', value: btn.data('drivers'), icon: 'fas fa-id-badge', size: 'lg' },
                        { label: 'فئة المعدة', value: btn.data('category'), icon: 'fas fa-check-circle' },
                        { label: 'تاريخ البداية', value: btn.data('start'), icon: 'fas fa-calendar-alt' },
                        { label: 'تاريخ النهاية', value: btn.data('end'), icon: 'fas fa-calendar-check' },
                        { label: 'ساعات العمل الكلية', value: btn.data('total-hours') || '0', icon: 'fas fa-clock' },
                        { label: 'ساعات الوردية', value: btn.data('shift-hours') || '0', icon: 'fas fa-hourglass-half' },
                        { label: 'نظام الوردية', value: btn.data('shift-type-label'), icon: 'fas fa-sync-alt' },
                        { label: 'الحالة', icon: 'fas fa-toggle-on', type: 'html',
                          value: "<span class='status-pill " + statusClass + "'>" + statusLabel + "</span>" }
                    ];
                    if (statusClass === 'status-idle' && reason !== '') {
                        fields.push({ label: 'سبب الإنهاء', value: reason, icon: 'fas fa-info-circle', size: 'full' });
                    }

                    var actions = [];
                    <?php if ($can_edit): ?>
                    actions.push({ label: 'تعديل', icon: 'fas fa-edit', variant: 'primary', onClick: function () {
                        EmsDetailsModal.close();
                        setTimeout(function () {
                            var editBtn = $('.editOperationBtn[data-id="' + opId + '"]');
                            if (editBtn.length) editBtn.trigger('click');
                        }, 200);
                    } });
                    <?php endif; ?>
                    actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

                    EmsDetailsModal.open({
                        title: 'تفاصيل سجل التشغيل',
                        icon: 'fas fa-clipboard-list',
                        fields: fields,
                        actions: actions
                    });
                });
            });

            function closeViewOperationModal() {
                if (window.EmsDetailsModal) EmsDetailsModal.close();
            }

        </script>

    </div><!-- /.main -->
<?php if (function_exists('ems_excel_render')) { ems_excel_render(); } ?>
    </body>

    </html>
