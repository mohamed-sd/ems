<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
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

// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15): كشوف الأعمدة الستة وبُناة
// النطاق البديلة أُسقطوا — {TENANT_SCOPE} والبوابة مسؤولا النطاق، والسوبر مسجَّل.
$sed_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('save equipment drivers super') : ems_tenant_db();

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

    // جلب معلومات الآلية (العزل عبر البوابة)
    try {
        $equipment_info = $sed_gate->selectOne('equipments', array(
            'columns' => array('code', 'name'),
            'where'   => array('id' => $equipment_id),
        ));
    } catch (\Throwable $t) { $equipment_info = null; }
    if (!$equipment_info) {
        echo "❌ الآلية غير موجودة أو خارج نطاق الشركة.";
        exit;
    }
    $equipment_code = $equipment_info ? $equipment_info['code'] : '';
    $equipment_name = $equipment_info ? $equipment_info['name'] : '';

    // إذا كان مدير الحركة والتشغيل (role 10)، إنشاء طلب موافقة
    if ($is_role10) {
        // ضمان وجود قاعدة الموافقة (مدير المشغلين)
        // [مُستثنى موثَّق — عائلة الاعتمادات] approval_workflow_rules مصنَّفة restricted
        // في العقد (بانتظار هجرة وحدة الاعتمادات أخيرًا مع الدوام) — تبقى العبارة خامًا.
        mysqli_query(
            $conn,
            "INSERT IGNORE INTO approval_workflow_rules (entity_type, action, role_required, step_order, is_active, created_at)
             VALUES ('driver', 'activate_driver', '3,-1', 1, 1, NOW())"
        );

        // معالجة كل سائق كطلب منفصل
        $success_count = 0;
        $error_messages = [];

        foreach ($drivers as $employee_id) {
            $employee_id = intval($employee_id);

            // التحقق من عدم وجود ربط نشط بالفعل (العزل عبر البوابة)
            try {
                $check = $sed_gate->selectOne('equipment_drivers', array(
                    'columns'  => array('id'),
                    'where'    => array('equipment_id' => $equipment_id, 'employee_id' => $employee_id),
                    'whereRaw' => 'status = 1',
                ));
            } catch (\Throwable $t) { $check = null; }
            if ($check) {
                continue;
            }

            // جلب معلومات السائق
            try {
                $driver_info = $sed_gate->selectOne('employees', array(
                    'columns' => array('name', 'phone'),
                    'where'   => array('id' => $employee_id),
                ));
            } catch (\Throwable $t) { $driver_info = null; }
            if (!$driver_info) {
                $error_messages[] = "السائق #$employee_id خارج النطاق";
                continue;
            }
            $driver_name = $driver_info ? $driver_info['name'] : "سائق #$employee_id";

            // اشتقاق التواريخ من عقد السائق الساري (وليس من الفورم)
            $dates = ems_resolve_equipment_driver_dates($conn, $employee_id, $company_id, $is_super_admin);
            $start_date = $dates['start'];
            $end_date = $dates['end'];

            $payload = [
                'summary' => [
                    'employee_id' => $employee_id,
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
                            'employee_id' => $employee_id,
                            'start_date' => $start_date,
                            'end_date' => $end_date,
                            'status' => 1,
                            'shift_type' => $shift_type
                        ]
                    ]
                ]
            ];

            $approval_result = approval_create_request(
                'driver',
                $employee_id,
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

    // المستخدمون الآخرون: إضافة مباشرة (الكتابة عبر البوابة حصرًا)
    foreach ($drivers as $employee_id) {
        $employee_id = intval($employee_id);

        // اشتقاق التواريخ من عقد السائق الساري (وليس من الفورم)
        $dates = ems_resolve_equipment_driver_dates($conn, $employee_id, $company_id, $is_super_admin);

        // التحقق من عدم وجود ربط نشط بالفعل
        try {
            $check = $sed_gate->selectOne('equipment_drivers', array(
                'columns'  => array('id'),
                'where'    => array('equipment_id' => $equipment_id, 'employee_id' => $employee_id),
                'whereRaw' => 'status = 1',
            ));
        } catch (\Throwable $t) { $check = null; }
        if ($check) {
            continue;
        }

        try {
            $active_row = $sed_gate->selectOne('equipment_drivers', array(
                'columns'  => array('id', 'equipment_id'),
                'where'    => array('employee_id' => $employee_id),
                'whereRaw' => 'status = 1',
            ));
        } catch (\Throwable $t) { $active_row = null; }
        if ($active_row) {
            $active_equipment_id = isset($active_row['equipment_id']) ? intval($active_row['equipment_id']) : 0;

            if ($active_equipment_id === $equipment_id) {
                continue;
            }

            if ($auto_replace === 1) {
                try {
                    $sed_gate->update('equipment_drivers',
                        array('status' => 0, 'end_date' => $dates['start']),
                        array('employee_id' => $employee_id), 'status = 1');
                } catch (\Throwable $t) {
                    error_log('save_equipment_drivers.php auto-replace failed: ' . $t->getMessage());
                }
            } else {
                continue;
            }
        }

        try {
            $driver_ok = $sed_gate->selectOne('employees', array(
                'columns' => array('id'), 'where' => array('id' => $employee_id),
            ));
        } catch (\Throwable $t) { $driver_ok = null; }
        if (!$driver_ok) {
            continue;
        }

        try {
            $sed_gate->insert('equipment_drivers', array(
                'equipment_id' => $equipment_id,
                'employee_id'  => $employee_id,
                'start_date'   => $dates['start'],
                'end_date'     => $dates['end'],
                'status'       => 1,
                'shift_type'   => $shift_type,
            ));
        } catch (\Throwable $t) {
            error_log('save_equipment_drivers.php insert failed: ' . $t->getMessage());
        }
    }

    echo "✅ تم تحديث السائقين للآلية.";
    echo "<script>alert('✅ تم الحفظ بنجاح'); window.location.href='project_drivers.php';</script>";
}
?>
