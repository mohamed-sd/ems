<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once '../includes/approval_workflow.php';
require_once '../includes/driver_contract_dates.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    echo "❌ معرّف الشركة غير متوفر.";
    exit;
}

$equipments_has_company = db_table_has_column($conn, 'equipments', 'company_id');
$drivers_has_company = db_table_has_column($conn, 'drivers', 'company_id');
$drivers_has_supplier = db_table_has_column($conn, 'drivers', 'supplier_id');
$suppliers_has_company = db_table_has_column($conn, 'suppliers', 'company_id');
$equipment_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');
$equipment_drivers_has_shift_type = db_table_has_column($conn, 'equipment_drivers', 'shift_type');

$equipment_scope_sql = '1=1';
if (!$is_super_admin) {
    if ($equipments_has_company) {
        $equipment_scope_sql = "e.company_id = $company_id";
    } else {
        $equipment_scope_sql = "EXISTS (
            SELECT 1
            FROM operations so
            JOIN project sp ON sp.id = so.project_id
            WHERE so.equipment = e.id
              AND (
                  EXISTS (SELECT 1 FROM users su WHERE su.id = sp.created_by AND su.company_id = $company_id)
                  OR EXISTS (
                      SELECT 1
                      FROM clients sc
                      JOIN users scu ON scu.id = sc.created_by
                      WHERE sc.id = sp.company_client_id AND scu.company_id = $company_id
                  )
              )
        )";
    }
}

$driver_scope_sql = '1=1';
if (!$is_super_admin) {
    if ($drivers_has_company) {
        $driver_scope_sql = "d.company_id = $company_id";
    } elseif ($drivers_has_supplier && $suppliers_has_company) {
        $driver_scope_sql = "EXISTS (
            SELECT 1
            FROM suppliers ds
            WHERE ds.id = d.supplier_id
              AND ds.company_id = $company_id
        )";
    } else {
        $driver_scope_sql = "0=1";
    }
}

if (isset($_POST['equipment_id'])) {
    $equipment_id = intval($_POST['equipment_id']);
    $user_role = isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : '';
    $is_role10 = ($user_role == "10");

    // دعم كلاً من النظام الجديد والقديم
    $drivers = [];
    if (isset($_POST['drivers']) && is_array($_POST['drivers'])) {
        // النظام القديم (select multiple)
        $drivers = $_POST['drivers'];
    } elseif (isset($_POST['drivers_selected']) && !empty($_POST['drivers_selected'])) {
        // النظام الجديد (cards with checkboxes)
        $drivers = explode(',', $_POST['drivers_selected']);
    }

    if (empty($drivers)) {
        echo "❌ يجب اختيار مشغل واحد على الأقل.";
        exit;
    }

    $shift_type_raw = isset($_POST['shift_type']) ? strval($_POST['shift_type']) : 'B';
    $auto_replace = isset($_POST['auto_replace']) ? intval($_POST['auto_replace']) : 0;

    $allowed_shift_types = array('D', 'N', 'B');
    $shift_type = in_array($shift_type_raw, $allowed_shift_types, true) ? $shift_type_raw : 'B';

    // ملاحظة: تواريخ بداية/نهاية التشغيل لم تعد تؤخذ من الفورم، بل تُشتق آلياً
    // لكل سائق من عقده الساري (راجع includes/driver_contract_dates.php).

    // جلب معلومات الآلية
    $equipment_res = mysqli_query($conn, "SELECT e.code, e.name FROM equipments e WHERE e.id = $equipment_id AND $equipment_scope_sql LIMIT 1");
    $equipment_info = $equipment_res ? mysqli_fetch_assoc($equipment_res) : null;
    if (!$equipment_info) {
        echo "❌ الآلية غير موجودة أو خارج نطاق الشركة.";
        exit;
    }
    $equipment_code = $equipment_info ? $equipment_info['code'] : '';
    $equipment_name = $equipment_info ? $equipment_info['name'] : '';

    // إذا كان مدير الحركة والتشغيل (role 10)، إنشاء طلب موافقة
    if ($is_role10) {
        // ضمان وجود قاعدة الموافقة (مدير المشغلين)
        mysqli_query(
            $conn,
            "INSERT IGNORE INTO approval_workflow_rules (entity_type, action, role_required, step_order, is_active, created_at)
             VALUES ('driver', 'activate_driver', '3,-1', 1, 1, NOW())"
        );

        // معالجة كل سائق كطلب منفصل
        $success_count = 0;
        $error_messages = [];

        foreach ($drivers as $driver_id) {
            $driver_id = intval($driver_id);

            // التحقق من عدم وجود ربط نشط بالفعل
            $check_scope = ($is_super_admin || !$equipment_drivers_has_company) ? "" : " AND company_id = $company_id";
            $check = mysqli_query($conn, "SELECT id FROM equipment_drivers WHERE equipment_id=$equipment_id AND driver_id=$driver_id AND status=1$check_scope");
            if ($check && mysqli_num_rows($check) > 0) {
                continue;
            }

            // جلب معلومات السائق
            $driver_res = mysqli_query($conn, "SELECT d.name, d.phone FROM drivers d WHERE d.id = $driver_id AND $driver_scope_sql LIMIT 1");
            $driver_info = $driver_res ? mysqli_fetch_assoc($driver_res) : null;
            if (!$driver_info) {
                $error_messages[] = "السائق #$driver_id خارج النطاق";
                continue;
            }
            $driver_name = $driver_info ? $driver_info['name'] : "سائق #$driver_id";

            // اشتقاق التواريخ من عقد السائق الساري (وليس من الفورم)
            $dates = ems_resolve_equipment_driver_dates($conn, $driver_id, $company_id, $is_super_admin);
            $start_date = $dates['start'];
            $end_date = $dates['end'];

            $payload = [
                'summary' => [
                    'driver_id' => $driver_id,
                    'driver_name' => $driver_name,
                    'equipment_id' => $equipment_id,
                    'equipment_code' => $equipment_code,
                    'equipment_name' => $equipment_name,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'shift_type' => $shift_type,
                    'action' => 'تشغيل مشغل جديد',
                    'requested_by_role' => '10',
                    'reason' => "طلب تشغيل مشغل جديد على آلية من شاشة إدارة المشغلين"
                ],
                'operations' => [
                    [
                        'db_action' => 'insert',
                        'table' => 'equipment_drivers',
                        'data' => [
                            'equipment_id' => $equipment_id,
                            'driver_id' => $driver_id,
                            'start_date' => $start_date,
                            'end_date' => $end_date,
                            'status' => 1,
                            'shift_type' => $shift_type
                        ]
                    ]
                ]
            ];

            if (!$equipment_drivers_has_shift_type) {
                unset($payload['operations'][0]['data']['shift_type']);
            }

            $approval_result = approval_create_request(
                'driver',
                $driver_id,
                'activate_driver',
                $payload,
                approval_get_user_id(),
                $conn
            );

            if (!empty($approval_result['success'])) {
                $success_count++;
            } else {
                $error_messages[] = $driver_name . ': ' . $approval_result['message'];
            }
        }

        if ($success_count > 0) {
            $msg = "✅ تم إرسال $success_count طلب موافقة لتشغيل المشغلين";
            if (!empty($error_messages)) {
                $msg .= "\\n⚠️ بعض الطلبات فشلت: " . implode(", ", $error_messages);
            }
            echo "<script>alert('" . addslashes($msg) . "'); window.location.href='equipments.php';</script>";
        } else {
            $msg = "❌ فشلت جميع الطلبات";
            if (!empty($error_messages)) {
                $msg .= ": " . implode(", ", $error_messages);
            }
            echo "<script>alert('" . addslashes($msg) . "'); window.location.href='add_drivers.php?equipment_id=$equipment_id';</script>";
        }
        exit;
    }

    // المستخدمون الآخرون: إضافة مباشرة
    foreach ($drivers as $driver_id) {
        $driver_id = intval($driver_id);

        // اشتقاق التواريخ من عقد السائق الساري (وليس من الفورم)
        $dates = ems_resolve_equipment_driver_dates($conn, $driver_id, $company_id, $is_super_admin);
        $start_sql = "'" . mysqli_real_escape_string($conn, $dates['start']) . "'";
        $end_sql = "'" . mysqli_real_escape_string($conn, $dates['end']) . "'";

        // التحقق من عدم وجود ربط نشط بالفعل
        $check_scope = ($is_super_admin || !$equipment_drivers_has_company) ? "" : " AND company_id = $company_id";
        $check = mysqli_query($conn, "SELECT id FROM equipment_drivers WHERE equipment_id=$equipment_id AND driver_id=$driver_id AND status=1$check_scope");
        if ($check && mysqli_num_rows($check) > 0) {
            continue;
        }

        $active_any = mysqli_query($conn, "SELECT id, equipment_id FROM equipment_drivers WHERE driver_id=$driver_id AND status=1$check_scope LIMIT 1");
        if ($active_any && mysqli_num_rows($active_any) > 0) {
            $active_row = mysqli_fetch_assoc($active_any);
            $active_equipment_id = isset($active_row['equipment_id']) ? intval($active_row['equipment_id']) : 0;

            if ($active_equipment_id === $equipment_id) {
                continue;
            }

            if ($auto_replace === 1) {
                mysqli_query(
                    $conn,
                    "UPDATE equipment_drivers SET status=0, end_date=$start_sql WHERE driver_id=$driver_id AND status=1$check_scope"
                );
            } else {
                continue;
            }
        }

        $driver_res = mysqli_query($conn, "SELECT d.id FROM drivers d WHERE d.id = $driver_id AND $driver_scope_sql LIMIT 1");
        if (!$driver_res || mysqli_num_rows($driver_res) === 0) {
            continue;
        }

        $insert_company_col = ($is_super_admin || !$equipment_drivers_has_company) ? "" : ", company_id";
        $insert_company_val = ($is_super_admin || !$equipment_drivers_has_company) ? "" : ", $company_id";
        $insert_shift_col = $equipment_drivers_has_shift_type ? ", shift_type" : "";
        $insert_shift_val = $equipment_drivers_has_shift_type ? ", '" . mysqli_real_escape_string($conn, $shift_type) . "'" : "";

        mysqli_query(
            $conn,
            "INSERT INTO equipment_drivers (equipment_id, driver_id, start_date, end_date, status$insert_shift_col$insert_company_col)
             VALUES ($equipment_id, $driver_id, $start_sql, $end_sql, 1$insert_shift_val$insert_company_val)"
        );
    }

    echo "✅ تم تحديث السائقين للآلية.";
    echo "<script>alert('✅ تم الحفظ بنجاح'); window.location.href='project_drivers.php';</script>";
}
?>
