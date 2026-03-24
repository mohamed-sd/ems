<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once '../includes/approval_workflow.php';

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
    
    // Ø¯Ø¹Ù… ÙƒÙ„Ø§Ù‹ Ù…Ù† Ø§Ù„Ù†Ø¸Ø§Ù… Ø§Ù„Ø¬Ø¯ÙŠØ¯ ÙˆØ§Ù„Ù‚Ø¯ÙŠÙ…
    $drivers = [];
    if (isset($_POST['drivers']) && is_array($_POST['drivers'])) {
        // Ø§Ù„Ù†Ø¸Ø§Ù… Ø§Ù„Ù‚Ø¯ÙŠÙ… (select multiple)
        $drivers = $_POST['drivers'];
    } elseif (isset($_POST['drivers_selected']) && !empty($_POST['drivers_selected'])) {
        // Ø§Ù„Ù†Ø¸Ø§Ù… Ø§Ù„Ø¬Ø¯ÙŠØ¯ (cards with checkboxes)
        $drivers = explode(',', $_POST['drivers_selected']);
    }
    
    if (empty($drivers)) {
        echo "âŒ ÙŠØ¬Ø¨ Ø§Ø®ØªÙŠØ§Ø± Ù…Ø´ØºÙ„ ÙˆØ§Ø­Ø¯ Ø¹Ù„Ù‰ Ø§Ù„Ø£Ù‚Ù„.";
        exit;
    }

    $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';

    $start_valid = false;
    if ($start_date !== '') {
        $start_dt = DateTime::createFromFormat('Y-m-d', $start_date);
        $start_valid = $start_dt && $start_dt->format('Y-m-d') === $start_date;
    }

    $end_valid = false;
    if ($end_date !== '') {
        $end_dt = DateTime::createFromFormat('Y-m-d', $end_date);
        $end_valid = $end_dt && $end_dt->format('Y-m-d') === $end_date;
    }

    if (!$start_valid) {
        echo "âŒ ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¨Ø¯Ø§ÙŠØ© ØºÙŠØ± ØµØ­ÙŠØ­.";
        exit;
    }

    if ($end_date !== '' && !$end_valid) {
        echo "âŒ ØªØ§Ø±ÙŠØ® Ø§Ù„Ù†Ù‡Ø§ÙŠØ© ØºÙŠØ± ØµØ­ÙŠØ­.";
        exit;
    }

    if ($end_date !== '' && strtotime($start_date) > strtotime($end_date)) {
        echo "âŒ ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¨Ø¯Ø§ÙŠØ© ÙŠØ¬Ø¨ Ø£Ù† ÙŠÙƒÙˆÙ† Ù‚Ø¨Ù„ ØªØ§Ø±ÙŠØ® Ø§Ù„Ù†Ù‡Ø§ÙŠØ©.";
        exit;
    }

    $start_sql = "'" . mysqli_real_escape_string($conn, $start_date) . "'";
    $end_sql = $end_date !== '' ? "'" . mysqli_real_escape_string($conn, $end_date) . "'" : "'2099-12-31'";

    // Ø¬Ù„Ø¨ Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø¢Ù„ÙŠØ©
    $equipment_res = mysqli_query($conn, "SELECT e.code, e.name FROM equipments e WHERE e.id = $equipment_id AND $equipment_scope_sql LIMIT 1");
    $equipment_info = mysqli_fetch_assoc($equipment_res);
    if (!$equipment_info) {
        echo "❌ المعدة غير موجودة أو خارج نطاق الشركة.";
        exit;
    }
    $equipment_code = $equipment_info ? $equipment_info['code'] : '';
    $equipment_name = $equipment_info ? $equipment_info['name'] : '';

    // Ø¥Ø°Ø§ ÙƒØ§Ù† Ù…Ø¯ÙŠØ± Ø§Ù„Ø­Ø±ÙƒØ© ÙˆØ§Ù„ØªØ´ØºÙŠÙ„ (role 10)ØŒ Ø¥Ù†Ø´Ø§Ø¡ Ø·Ù„Ø¨ Ù…ÙˆØ§ÙÙ‚Ø©
    if ($is_role10) {
        // Ø¶Ù…Ø§Ù† ÙˆØ¬ÙˆØ¯ Ù‚Ø§Ø¹Ø¯Ø© Ø§Ù„Ù…ÙˆØ§ÙÙ‚Ø© (Ù…Ø¯ÙŠØ± Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ†)
        mysqli_query(
            $conn,
            "INSERT IGNORE INTO approval_workflow_rules (entity_type, action, role_required, step_order, is_active, created_at)
             VALUES ('driver', 'activate_driver', '3,-1', 1, 1, NOW())"
        );

        // Ù…Ø¹Ø§Ù„Ø¬Ø© ÙƒÙ„ Ø³Ø§Ø¦Ù‚ ÙƒØ·Ù„Ø¨ Ù…Ù†ÙØµÙ„
        $success_count = 0;
        $error_messages = [];

        foreach ($drivers as $driver_id) {
            $driver_id = intval($driver_id);
            
            // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø¹Ø¯Ù… ÙˆØ¬ÙˆØ¯ Ø±Ø¨Ø· Ù†Ø´Ø· Ø¨Ø§Ù„ÙØ¹Ù„
            $check_scope = ($is_super_admin || !$equipment_drivers_has_company) ? "" : " AND company_id = $company_id";
            $check = mysqli_query($conn, "SELECT id FROM equipment_drivers WHERE equipment_id=$equipment_id AND driver_id=$driver_id AND status=1$check_scope");
            if (mysqli_num_rows($check) > 0) {
                continue;
            }

            // Ø¬Ù„Ø¨ Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø³Ø§Ø¦Ù‚
            $driver_res = mysqli_query($conn, "SELECT d.name, d.phone FROM drivers d WHERE d.id = $driver_id AND $driver_scope_sql LIMIT 1");
            $driver_info = mysqli_fetch_assoc($driver_res);
            if (!$driver_info) {
                $error_messages[] = "السائق #$driver_id خارج النطاق";
                continue;
            }
            $driver_name = $driver_info ? $driver_info['name'] : "Ø³Ø§Ø¦Ù‚ #$driver_id";

            $payload = [
                'summary' => [
                    'driver_id' => $driver_id,
                    'driver_name' => $driver_name,
                    'equipment_id' => $equipment_id,
                    'equipment_code' => $equipment_code,
                    'equipment_name' => $equipment_name,
                    'start_date' => $start_date,
                    'end_date' => $end_date !== '' ? $end_date : '2099-12-31',
                    'action' => 'ØªØ´ØºÙŠÙ„ Ù…Ø´ØºÙ„ Ø¬Ø¯ÙŠØ¯',
                    'requested_by_role' => '10',
                    'reason' => "Ø·Ù„Ø¨ ØªØ´ØºÙŠÙ„ Ù…Ø´ØºÙ„ Ø¬Ø¯ÙŠØ¯ Ø¹Ù„Ù‰ Ø¢Ù„ÙŠØ© Ù…Ù† Ø´Ø§Ø´Ø© Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ†"
                ],
                'operations' => [
                    [
                        'db_action' => 'insert',
                        'table' => 'equipment_drivers',
                        'data' => [
                            'equipment_id' => $equipment_id,
                            'driver_id' => $driver_id,
                            'start_date' => $start_date,
                            'end_date' => $end_date !== '' ? $end_date : '2099-12-31',
                            'status' => 1
                        ]
                    ]
                ]
            ];

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
            $msg = "âœ… ØªÙ… Ø¥Ø±Ø³Ø§Ù„ $success_count Ø·Ù„Ø¨ Ù…ÙˆØ§ÙÙ‚Ø© Ù„ØªØ´ØºÙŠÙ„ Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ†";
            if (!empty($error_messages)) {
                $msg .= "\\nâš ï¸ Ø¨Ø¹Ø¶ Ø§Ù„Ø·Ù„Ø¨Ø§Øª ÙØ´Ù„Øª: " . implode(", ", $error_messages);
            }
            echo "<script>alert('" . addslashes($msg) . "'); window.location.href='equipments.php';</script>";
        } else {
            $msg = "âŒ ÙØ´Ù„Øª Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø·Ù„Ø¨Ø§Øª";
            if (!empty($error_messages)) {
                $msg .= ": " . implode(", ", $error_messages);
            }
            echo "<script>alert('" . addslashes($msg) . "'); window.location.href='add_drivers.php?equipment_id=$equipment_id';</script>";
        }
        exit;
    }
    
    // Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙˆÙ† Ø§Ù„Ø¢Ø®Ø±ÙˆÙ†: Ø¥Ø¶Ø§ÙØ© Ù…Ø¨Ø§Ø´Ø±Ø©
    foreach ($drivers as $driver_id) {
        $driver_id = intval($driver_id);
        
        // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø¹Ø¯Ù… ÙˆØ¬ÙˆØ¯ Ø±Ø¨Ø· Ù†Ø´Ø· Ø¨Ø§Ù„ÙØ¹Ù„
        $check_scope = ($is_super_admin || !$equipment_drivers_has_company) ? "" : " AND company_id = $company_id";
        $check = mysqli_query($conn, "SELECT id FROM equipment_drivers WHERE equipment_id=$equipment_id AND driver_id=$driver_id AND status=1$check_scope");
        if (mysqli_num_rows($check) > 0) {
            continue;
        }

        $driver_res = mysqli_query($conn, "SELECT d.id FROM drivers d WHERE d.id = $driver_id AND $driver_scope_sql LIMIT 1");
        if (!$driver_res || mysqli_num_rows($driver_res) === 0) {
            continue;
        }

        $insert_company_col = ($is_super_admin || !$equipment_drivers_has_company) ? "" : ", company_id";
        $insert_company_val = ($is_super_admin || !$equipment_drivers_has_company) ? "" : ", $company_id";
        
        mysqli_query(
            $conn,
            "INSERT INTO equipment_drivers (equipment_id, driver_id, start_date, end_date, status$insert_company_col) 
             VALUES ($equipment_id, $driver_id, $start_sql, $end_sql, 1$insert_company_val)"
        );
    }

    echo "âœ… ØªÙ… ØªØ­Ø¯ÙŠØ« Ø§Ù„Ø³Ø§Ø¦Ù‚ÙŠÙ† Ù„Ù„Ø¢Ù„ÙŠØ©.";
    echo "<script>alert('âœ… ØªÙ… Ø§Ù„Ø­ÙØ¸ Ø¨Ù†Ø¬Ø§Ø­'); window.location.href='equipments.php';</script>";
}
?>

