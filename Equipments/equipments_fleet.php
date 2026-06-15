<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';
require_once '../includes/excel_ui.php'; // إطار Excel الموحّد (أزرار + نافذة المعالج)

$equipment_has_machine_number = db_table_has_column($conn, 'equipments', 'machine_number');
$equipment_has_document_type = db_table_has_column($conn, 'equipments', 'document_type');
$equipment_has_site_supervisor_name = db_table_has_column($conn, 'equipments', 'site_supervisor_name');
$equipment_has_site_supervisor_contact = db_table_has_column($conn, 'equipments', 'site_supervisor_contact');
$equipment_has_availability_state = db_table_has_column($conn, 'equipments', 'availability_state');
$equipment_has_company_id = db_table_has_column($conn, 'equipments', 'company_id');
$operations_project_col = db_table_has_column($conn, 'operations', 'project_id') ? 'project_id' : 'project';

// company isolation (SaaS)
$current_company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$equipment_company_filter = ($equipment_has_company_id && $current_company_id > 0) ? " AND m.company_id = $current_company_id" : "";
$equipment_company_filter_plain = ($equipment_has_company_id && $current_company_id > 0) ? " AND company_id = $current_company_id" : "";

if (!function_exists('normalize_equipment_availability_state')) {
    function normalize_equipment_availability_state($availability_state, $availability_status)
    {
        $availability_state = trim((string) $availability_state);
        $availability_status = trim((string) $availability_status);

        if ($availability_state === 'متوفرة' || $availability_state === 'غير متوفرة') {
            return $availability_state;
        }

        if ($availability_status === '' || $availability_status === 'متاحة للعمل' || $availability_status === 'قيد الاستخدام') {
            return 'متوفرة';
        }

        return 'غير متوفرة';
    }
}

if (!function_exists('normalize_equipment_availability_status')) {
    function normalize_equipment_availability_status($availability_state, $availability_status)
    {
        $availability_state = normalize_equipment_availability_state($availability_state, $availability_status);
        $availability_status = trim((string) $availability_status);

        if ($availability_state === 'متوفرة') {
            return 'قيد الاستخدام';
        }

        $legacy_map = [
            'موقوفة للصيانة' => 'تحت الصيانة',
            'مبيعة/مسحوبة' => 'مسحوبة',
            'معطلة مؤقتاً' => 'معطلة'
        ];

        if (isset($legacy_map[$availability_status])) {
            return $legacy_map[$availability_status];
        }

        $valid_statuses = ['تحت الصيانة', 'محجوزة', 'مسحوبة', 'في المستودع', 'معطلة'];
        if (in_array($availability_status, $valid_statuses, true)) {
            return $availability_status;
        }

        return 'تحت الصيانة';
    }
}

// ════════════════════════════════════════════════════════════════════════════
// ðŸ” التحقق من صلاحيات المستخدم
// ════════════════════════════════════════════════════════════════════════════
$page_permissions = check_page_permissions($conn, 'Equipments/equipments_fleet.php');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// منع الوصول إذا لم تكن صلاحية عرض
if (!$can_view) {
    header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+المعدات+❌");
    exit();
}

// معالجة حذف المعدة
if (isset($_GET['delete_id'])) {
    if (!$can_delete) {
        header("Location: equipments_fleet.php?msg=لا+توجد+صلاحية+حذف+المعدات+❌");
        exit();
    }
    $delete_id = intval($_GET['delete_id']);

    // التحقق من عدم استخدام المعدة في عمليات نشطة
    $check_ops = mysqli_query($conn, "SELECT COUNT(*) as count FROM operations WHERE equipment = $delete_id AND status = '1'");
    $ops_count = $check_ops ? (mysqli_fetch_assoc($check_ops)['count'] ?? 0) : 0;

    if ($ops_count > 0) {
        header("Location: equipments_fleet.php?msg=لا+يمكن+حذف+المعدة+لأنها+بصدد+التشغيل+حالياً+❌");
        exit();
    }

    if (mysqli_query($conn, "DELETE FROM equipments WHERE id = $delete_id $equipment_company_filter_plain")) {
        header("Location: equipments_fleet.php?msg=تم+حذف+المعدة+بنجاح+✅");
        exit();
    } else {
        header("Location: equipments_fleet.php?msg=حدث+خطأ+أثناء+الحذف+❌");
        exit();
    }
}

$is_role10 = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10";
$user_project_id = $is_role10 ? intval($_SESSION['user']['project_id']) : 0;

$selected_project_id = 0;
$show_all_projects = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_project_id'])) {
    if ($is_role10) {
        header("Location: equipments_fleet.php");
        exit();
    }
    $selected_project_value = trim($_POST['selected_project_id']);
    if ($selected_project_value === 'all') {
        $_SESSION['equipments_project_id'] = 'all';
    } elseif (is_numeric($selected_project_value) && intval($selected_project_value) > 0) {
        $_SESSION['equipments_project_id'] = intval($selected_project_value);
    } else {
        unset($_SESSION['equipments_project_id']);
    }
    header("Location: equipments_fleet.php");
    exit();
}

if (isset($_GET['project_id']) && is_numeric($_GET['project_id'])) {
    if ($is_role10) {
        header("Location: equipments_fleet.php");
        exit();
    }
    $_SESSION['equipments_project_id'] = intval($_GET['project_id']);
    header("Location: equipments_fleet.php");
    exit();
}

if (isset($_SESSION['equipments_project_id'])) {
    if ($_SESSION['equipments_project_id'] === 'all') {
        $show_all_projects = true;
        $selected_project_id = 0;
    } else {
        $selected_project_id = intval($_SESSION['equipments_project_id']);
    }
}

if ($is_role10) {
    $show_all_projects = false;
    $selected_project_id = $user_project_id;
}

$selected_project = null;
if ($selected_project_id > 0) {
    $project_check_query = "SELECT id, name, project_code FROM project WHERE id = $selected_project_id AND status = '1'";
    $project_check_result = mysqli_query($conn, $project_check_query);
    if ($project_check_result && mysqli_num_rows($project_check_result) > 0) {
        $selected_project = mysqli_fetch_assoc($project_check_result);
    } else {
        unset($_SESSION['equipments_project_id']);
        $selected_project_id = 0;
    }
}

$projects_result = mysqli_query($conn, "SELECT id, name, project_code FROM project WHERE status = '1' ORDER BY name");

$page_title = "إدارة المعدات";
include("../inheader.php");
include("../insidebar.php");

// معالجة رسالة النجاح
$success_msg = '';
if (isset($_GET['msg'])) {
    $success_msg = htmlspecialchars($_GET['msg']);
}
?>
<?php

// معالجة الحفظ أو التعديل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['code'])) {
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

    // فحص الصلاحيات
    if ($edit_id > 0 && !$can_edit) {
        $success_msg = "❌ ليس لديك صلاحية لتعديل المعدات";
        goto skip_save;
    }
    if ($edit_id == 0 && !$can_add) {
        $success_msg = "❌ ليس لديك صلاحية لإضافة المعدات";
        goto skip_save;
    }

    // الحقول الأساسية
    $suppliers = mysqli_real_escape_string($conn, $_POST['suppliers']);
    $code = mysqli_real_escape_string($conn, trim($_POST['code']));
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $status = isset($_POST['status']) ? intval($_POST['status']) : 0;

    // المعلومات الأساسية والتعريفية
    $serial_number = mysqli_real_escape_string($conn, trim($_POST['serial_number'] ?? ''));
    $chassis_number = mysqli_real_escape_string($conn, trim($_POST['chassis_number'] ?? ''));
    $machine_number = mysqli_real_escape_string($conn, trim($_POST['machine_number'] ?? ''));

    // بيانات الصنع والموديل
    $manufacturer = mysqli_real_escape_string($conn, trim($_POST['manufacturer'] ?? ''));
    $model = mysqli_real_escape_string($conn, trim($_POST['model'] ?? ''));
    $manufacturing_year = !empty($_POST['manufacturing_year']) ? intval($_POST['manufacturing_year']) : 'NULL';
    $import_year = !empty($_POST['import_year']) ? intval($_POST['import_year']) : 'NULL';

    // الحالة الفنية والمواصفات
    $equipment_condition = mysqli_real_escape_string($conn, $_POST['equipment_condition'] ?? 'في حالة جيدة');
    $operating_hours = !empty($_POST['operating_hours']) ? intval($_POST['operating_hours']) : 'NULL';
    $engine_condition = mysqli_real_escape_string($conn, $_POST['engine_condition'] ?? 'جيدة');
    $tires_condition = mysqli_real_escape_string($conn, $_POST['tires_condition'] ?? 'N/A');

    // بيانات الملكية
    $actual_owner_name = mysqli_real_escape_string($conn, trim($_POST['actual_owner_name'] ?? ''));
    $owner_type = mysqli_real_escape_string($conn, $_POST['owner_type'] ?? '');
    $owner_phone = mysqli_real_escape_string($conn, trim($_POST['owner_phone'] ?? ''));
    $owner_supplier_relation = mysqli_real_escape_string($conn, $_POST['owner_supplier_relation'] ?? '');

    // الوثائق والتسجيلات
    $license_number = mysqli_real_escape_string($conn, trim($_POST['license_number'] ?? ''));
    $license_authority = mysqli_real_escape_string($conn, trim($_POST['license_authority'] ?? ''));
    $document_type = mysqli_real_escape_string($conn, trim($_POST['document_type'] ?? ''));
    $license_expiry_date = !empty($_POST['license_expiry_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['license_expiry_date']) . "'" : 'NULL';
    $inspection_certificate_number = mysqli_real_escape_string($conn, trim($_POST['inspection_certificate_number'] ?? ''));
    $last_inspection_date = !empty($_POST['last_inspection_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['last_inspection_date']) . "'" : 'NULL';

    // الموقع والتوفر
    $current_location = mysqli_real_escape_string($conn, trim($_POST['current_location'] ?? ''));
    $site_supervisor_name = mysqli_real_escape_string($conn, trim($_POST['site_supervisor_name'] ?? ''));
    $site_supervisor_contact = mysqli_real_escape_string($conn, trim($_POST['site_supervisor_contact'] ?? ''));
    $availability_state_input = $_POST['availability_state'] ?? '';
    $availability_status_input = $_POST['availability_status'] ?? '';
    $availability_state = mysqli_real_escape_string($conn, normalize_equipment_availability_state($availability_state_input, $availability_status_input));
    $availability_status = mysqli_real_escape_string($conn, normalize_equipment_availability_status($availability_state_input, $availability_status_input));

    // البيانات المالية والقيمة
    $estimated_value = !empty($_POST['estimated_value']) ? floatval($_POST['estimated_value']) : 'NULL';
    $daily_rental_price = !empty($_POST['daily_rental_price']) ? floatval($_POST['daily_rental_price']) : 'NULL';
    $monthly_rental_price = !empty($_POST['monthly_rental_price']) ? floatval($_POST['monthly_rental_price']) : 'NULL';
    $insurance_status = mysqli_real_escape_string($conn, $_POST['insurance_status'] ?? '');

    // ملاحظات وسجل الصيانة
    $general_notes = mysqli_real_escape_string($conn, trim($_POST['general_notes'] ?? ''));
    $last_maintenance_date = !empty($_POST['last_maintenance_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['last_maintenance_date']) . "'" : 'NULL';



    // التحقق من عدم تجاوز العدد المتعاقد عليه (فقط عند الإضافة)
    if ($edit_id == 0 && $suppliers && $type) {
        // الحصول على عدد المعدات المتعاقد عليها لهذا المورد ونوع المعدة
        $supplier_contract_query = "SELECT sc.id, sce.equip_count
                                   FROM supplierscontracts sc
                                   JOIN suppliercontractequipments sce ON sc.id = sce.contract_id
                                   WHERE sc.supplier_id = $suppliers
                                   AND sce.equip_type = '$type'
                                   AND sc.status = 1
                                   LIMIT 1";
        $supplier_contract_result = mysqli_query($conn, $supplier_contract_query);

        if ($supplier_contract_result && mysqli_num_rows($supplier_contract_result) > 0) {
            $supplier_contract = mysqli_fetch_assoc($supplier_contract_result);
            $contracted_count = intval($supplier_contract['equip_count']);

            // حساب عدد المعدات المضافة حالياً
            $added_count_query = "SELECT COUNT(*) as added_count
                                 FROM equipments
                                 WHERE suppliers = $suppliers
                                 AND type = '$type'
                                 AND status = 1";
            $added_count_result = mysqli_query($conn, $added_count_query);
            $added_count_row = $added_count_result ? mysqli_fetch_assoc($added_count_result) : null;
            $current_added = intval($added_count_row['added_count'] ?? 0);

            // التحقق من عدم تجاوز العدد المتعاقد عليه
            if ($current_added >= $contracted_count) {
                $success_msg = "⚠️ تحذير: تم الوصول للحد الأقصى! العدد المتعاقد عليه: $contracted_count | المضاف حالياً: $current_added. لا يمكن إضافة المزيد من المعدات.";
                goto skip_save;
            }
        }
    }

    $equipment_save_fields = [
        "suppliers='$suppliers'",
        "code='$code'",
        "type='$type'",
        "name='$name'",
        "status=$status",
        "serial_number='$serial_number'",
        "chassis_number='$chassis_number'",
        "manufacturer='$manufacturer'",
        "model='$model'",
        "manufacturing_year=$manufacturing_year",
        "import_year=$import_year",
        "equipment_condition='$equipment_condition'",
        "operating_hours=$operating_hours",
        "engine_condition='$engine_condition'",
        "tires_condition='$tires_condition'",
        "actual_owner_name='$actual_owner_name'",
        "owner_type='$owner_type'",
        "owner_phone='$owner_phone'",
        "owner_supplier_relation='$owner_supplier_relation'",
        "license_number='$license_number'",
        "license_authority='$license_authority'",
        "license_expiry_date=$license_expiry_date",
        "inspection_certificate_number='$inspection_certificate_number'",
        "last_inspection_date=$last_inspection_date",
        "current_location='$current_location'",
        "availability_status='$availability_status'",
        "estimated_value=$estimated_value",
        "daily_rental_price=$daily_rental_price",
        "monthly_rental_price=$monthly_rental_price",
        "insurance_status='$insurance_status'",
        "general_notes='$general_notes'",
        "last_maintenance_date=$last_maintenance_date"
    ];

    if ($equipment_has_machine_number) {
        $equipment_save_fields[] = "machine_number='$machine_number'";
    }
    if ($equipment_has_document_type) {
        $equipment_save_fields[] = "document_type='$document_type'";
    }
    if ($equipment_has_site_supervisor_name) {
        $equipment_save_fields[] = "site_supervisor_name='$site_supervisor_name'";
    }
    if ($equipment_has_site_supervisor_contact) {
        $equipment_save_fields[] = "site_supervisor_contact='$site_supervisor_contact'";
    }
    if ($equipment_has_availability_state) {
        $equipment_save_fields[] = "availability_state='$availability_state'";
    }

    if ($edit_id > 0) {
        // التحقق: إذا كانت المعدة تعمل في مشروع نشط، لا يُسمح بتغيير الحالة
        $old_status_res = mysqli_query($conn, "SELECT status FROM equipments WHERE id = $edit_id LIMIT 1");
        $old_status_row = $old_status_res ? mysqli_fetch_assoc($old_status_res) : null;
        $old_status = $old_status_row ? intval($old_status_row['status']) : -1;

        if ($old_status !== $status) {
            $active_ops = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM operations WHERE equipment = $edit_id AND status = '1'");
            $active_ops_row = $active_ops ? mysqli_fetch_assoc($active_ops) : null;
            if ($active_ops_row && intval($active_ops_row['cnt']) > 0) {
                $success_msg = "❌ لا يمكن تغيير حالة المعدة وهي تعمل في مشروع نشط";
                goto skip_save;
            }
        }

        // تعديل
        $company_where = ($equipment_has_company_id && $current_company_id > 0) ? " AND company_id='$current_company_id'" : "";
        $sql = "UPDATE equipments SET\n                    " . implode(",\n                    ", $equipment_save_fields) . "\n                WHERE id='$edit_id'$company_where";
        $msg = "تم+تعديل+المعدة+بنجاح+✅";
    } else {
        // إضافة
        $insert_columns = [
            'suppliers',
            'code',
            'type',
            'name',
            'status',
            'serial_number',
            'chassis_number',
            'manufacturer',
            'model',
            'manufacturing_year',
            'import_year',
            'equipment_condition',
            'operating_hours',
            'engine_condition',
            'tires_condition',
            'actual_owner_name',
            'owner_type',
            'owner_phone',
            'owner_supplier_relation',
            'license_number',
            'license_authority',
            'license_expiry_date',
            'inspection_certificate_number',
            'last_inspection_date',
            'current_location',
            'availability_status',
            'estimated_value',
            'daily_rental_price',
            'monthly_rental_price',
            'insurance_status',
            'general_notes',
            'last_maintenance_date'
        ];
        $insert_values = [
            "'$suppliers'",
            "'$code'",
            "'$type'",
            "'$name'",
            "$status",
            "'$serial_number'",
            "'$chassis_number'",
            "'$manufacturer'",
            "'$model'",
            "$manufacturing_year",
            "$import_year",
            "'$equipment_condition'",
            "$operating_hours",
            "'$engine_condition'",
            "'$tires_condition'",
            "'$actual_owner_name'",
            "'$owner_type'",
            "'$owner_phone'",
            "'$owner_supplier_relation'",
            "'$license_number'",
            "'$license_authority'",
            "$license_expiry_date",
            "'$inspection_certificate_number'",
            "$last_inspection_date",
            "'$current_location'",
            "'$availability_status'",
            "$estimated_value",
            "$daily_rental_price",
            "$monthly_rental_price",
            "'$insurance_status'",
            "'$general_notes'",
            "$last_maintenance_date"
        ];

        if ($equipment_has_machine_number) {
            $insert_columns[] = 'machine_number';
            $insert_values[] = "'$machine_number'";
        }
        if ($equipment_has_document_type) {
            $insert_columns[] = 'document_type';
            $insert_values[] = "'$document_type'";
        }
        if ($equipment_has_site_supervisor_name) {
            $insert_columns[] = 'site_supervisor_name';
            $insert_values[] = "'$site_supervisor_name'";
        }
        if ($equipment_has_site_supervisor_contact) {
            $insert_columns[] = 'site_supervisor_contact';
            $insert_values[] = "'$site_supervisor_contact'";
        }
        if ($equipment_has_availability_state) {
            $insert_columns[] = 'availability_state';
            $insert_values[] = "'$availability_state'";
        }
        if ($equipment_has_company_id && $current_company_id > 0) {
            $insert_columns[] = 'company_id';
            $insert_values[] = "'$current_company_id'";
        }

        $sql = "INSERT INTO equipments (" . implode(', ', $insert_columns) . ") VALUES (" . implode(', ', $insert_values) . ")";
        $msg = "تمت+إضافة+المعدة+بنجاح+✅";
    }

    if (mysqli_query($conn, $sql)) {
        header("Location: equipments_fleet.php?msg=$msg");
        exit;
    } else {
        $success_msg = "خطأ في الحفظ: " . mysqli_error($conn);
    }

    skip_save:
}

// في حالة تعديل تجهيز البيانات
$editData = [];
if (isset($_GET['edit']) && $can_edit) {
    $editId = intval($_GET['edit']);
    $edit_company_where = ($equipment_has_company_id && $current_company_id > 0) ? " AND company_id='$current_company_id'" : "";
    $res = mysqli_query($conn, "SELECT * FROM equipments WHERE id='$editId'$edit_company_where");
    if ($res && mysqli_num_rows($res) > 0) {
        $editData = mysqli_fetch_assoc($res);
    }
}

if (!empty($editData)) {
    $editData['machine_number'] = isset($editData['machine_number']) ? $editData['machine_number'] : '';
    $editData['document_type'] = isset($editData['document_type']) ? $editData['document_type'] : '';
    $editData['site_supervisor_name'] = isset($editData['site_supervisor_name']) ? $editData['site_supervisor_name'] : '';
    $editData['site_supervisor_contact'] = isset($editData['site_supervisor_contact']) ? $editData['site_supervisor_contact'] : '';
    $editData['availability_state'] = normalize_equipment_availability_state(
        isset($editData['availability_state']) ? $editData['availability_state'] : '',
        isset($editData['availability_status']) ? $editData['availability_status'] : ''
    );
    $editData['availability_status'] = normalize_equipment_availability_status(
        $editData['availability_state'],
        isset($editData['availability_status']) ? $editData['availability_status'] : ''
    );
}

// إحصائيات المعدات
$fleet_total_count = 0;
$fleet_available_count = 0;
$fleet_unavailable_count = 0;
$fleet_maintenance_count = 0;
$fleet_reserved_count = 0;
$fleet_active_ops_count = 0;

$fleet_company_where_plain = ($equipment_has_company_id && $current_company_id > 0)
    ? " WHERE company_id = $current_company_id"
    : "";

$_ftc_res = mysqli_query($conn, "SELECT COUNT(*) AS t FROM equipments$fleet_company_where_plain");
$fleet_total_count = intval($_ftc_res ? (mysqli_fetch_assoc($_ftc_res)['t'] ?? 0) : 0);

if ($equipment_has_availability_state) {
    $fleet_available_sql = "SELECT COUNT(*) AS t FROM equipments
        WHERE (
            availability_state = 'متوفرة'
            OR ((availability_state IS NULL OR availability_state = '')
                AND (availability_status IS NULL OR availability_status = '' OR availability_status IN ('متاحة للعمل','قيد الاستخدام')))
        )" . (($equipment_has_company_id && $current_company_id > 0) ? " AND company_id = $current_company_id" : '');
} else {
    $fleet_available_sql = "SELECT COUNT(*) AS t FROM equipments
        WHERE (availability_status IS NULL OR availability_status = '' OR availability_status IN ('متاحة للعمل','قيد الاستخدام'))" . (($equipment_has_company_id && $current_company_id > 0) ? " AND company_id = $current_company_id" : '');
}

$_fac_res = mysqli_query($conn, $fleet_available_sql);
$fleet_available_count = intval($_fac_res ? (mysqli_fetch_assoc($_fac_res)['t'] ?? 0) : 0);
$fleet_unavailable_count = max(0, $fleet_total_count - $fleet_available_count);
$_fmc_res = mysqli_query($conn, "SELECT COUNT(*) AS t FROM equipments WHERE status = 1" . (($equipment_has_company_id && $current_company_id > 0) ? " AND company_id = $current_company_id" : ''));
$fleet_maintenance_count = intval($_fmc_res ? (mysqli_fetch_assoc($_fmc_res)['t'] ?? 0) : 0);
$_frc_res = mysqli_query($conn, "SELECT COUNT(*) AS t FROM equipments WHERE status = 2" . (($equipment_has_company_id && $current_company_id > 0) ? " AND company_id = $current_company_id" : ''));
$fleet_reserved_count = intval($_frc_res ? (mysqli_fetch_assoc($_frc_res)['t'] ?? 0) : 0);
$_faoc_res = mysqli_query($conn, "SELECT COUNT(DISTINCT o.equipment) AS t FROM operations o JOIN equipments m ON m.id = o.equipment WHERE o.status = '1'$equipment_company_filter");
$fleet_active_ops_count = intval($_faoc_res ? (mysqli_fetch_assoc($_faoc_res)['t'] ?? 0) : 0);
?>

<style>
/* نافذة عرض المعدة موحّدة عبر EmsDetailsModal — الأنماط في assets/css/ems.main.all.style.css */


.equipments-fleet-main .stats-section {
    margin: 12px 0 16px;
    border: 1px solid #eadfce;
    border-radius: 14px;
    background: linear-gradient(180deg, #fff 0%, #fffbf5 100%);
    padding: 12px;
}

.equipments-fleet-main .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 10px;
}

.equipments-fleet-main .stats-card {
    border: 1px solid #e8dcc8;
    border-radius: 12px;
    background: #fff;
    padding: 12px;
}

.equipments-fleet-main .stats-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
}

.equipments-fleet-main .stats-title {
    font-size: .84rem;
    color: #6b4e2a;
    margin-bottom: 6px;
    font-weight: 700;
}

.equipments-fleet-main .stats-value {
    font-size: 1.4rem;
    font-weight: 900;
    color: #1a1208;
}

.equipments-fleet-main .stats-primary .stats-icon { background: rgba(37,99,235,.14); color: #1d4ed8; }
.equipments-fleet-main .stats-success .stats-icon { background: rgba(22,163,74,.14); color: #15803d; }
.equipments-fleet-main .stats-danger .stats-icon { background: rgba(220,38,38,.14); color: #b91c1c; }
.equipments-fleet-main .stats-purple .stats-icon { background: rgba(124,58,237,.14); color: #6d28d9; }
.equipments-fleet-main .stats-cyan .stats-icon { background: rgba(8,145,178,.14); color: #0e7490; }
.equipments-fleet-main .stats-orange .stats-icon { background: rgba(217,119,6,.14); color: #b45309; }
</style>

<div class="main equipments-fleet-main ems-unified-page-shell">

   <!-- عنوان الصفحة -->
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title = 'إدارة المعدات';
    $header_icon  = 'fas fa-cogs';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'attrs' => 'onclick="toggleFleetForm(event)"', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة معدة جديدة');
        $header_actions[] =  array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'fleet-toggle-stats-text');
        // إطار Excel الموحّد: نموذج + تصدير + استيراد متعدد الخطوات (يستبدل الأزرار/النافذة القديمة).
        foreach (ems_excel_header_actions('equipments', 'المعدات', $can_add) as $a) {
            $header_actions[] = $a;
        }
    }
    $header_back = array(
        array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع'),
    );
    include('../includes/page_header.php');
    ?>



    <?php if (!empty($success_msg)):
        $isSuccess = strpos($success_msg, '✅') !== false;
        ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <div class="stats-section fleet-hidden" id="fleetStatsSection">
        <div class="stats-grid">
            <div class="stats-card stats-primary">
                <div class="stats-icon"><i class="fas fa-truck-monster"></i></div>
                <div class="stats-title">إجمالي المعدات</div>
                <div class="stats-value"><?php echo $fleet_total_count; ?></div>
            </div>
            <div class="stats-card stats-success">
                <div class="stats-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stats-title">المعدات المتوفرة</div>
                <div class="stats-value"><?php echo $fleet_available_count; ?></div>
            </div>
            <div class="stats-card stats-danger">
                <div class="stats-icon"><i class="fas fa-ban"></i></div>
                <div class="stats-title">المعدات غير المتوفرة</div>
                <div class="stats-value"><?php echo $fleet_unavailable_count; ?></div>
            </div>
            <div class="stats-card stats-cyan">
                <div class="stats-icon"><i class="fas fa-play-circle"></i></div>
                <div class="stats-title">معدات في تشغيل نشط</div>
                <div class="stats-value"><?php echo $fleet_active_ops_count; ?></div>
            </div>
            <div class="stats-card stats-purple">
                <div class="stats-icon"><i class="fas fa-tools"></i></div>
                <div class="stats-title">تحت الصيانة</div>
                <div class="stats-value"><?php echo $fleet_maintenance_count; ?></div>
            </div>
            <div class="stats-card stats-orange">
                <div class="stats-icon"><i class="fas fa-bookmark"></i></div>
                <div class="stats-title">محجوزة</div>
                <div class="stats-value"><?php echo $fleet_reserved_count; ?></div>
            </div>
        </div>
    </div>

    <?php if ($can_add || $can_edit) { ?>
        <!-- فورم إضافة / تعديل معدة -->
        <form id="projectForm" action="" method="post"
            class="allforms<?php echo !empty($editData) ? ' allforms-visible' : ''; ?>">
              <div class="card-header">
                    <h5>
                        <i class="fas fa-<?php echo !empty($editData) ? 'edit' : 'plus-circle'; ?>"></i>
                        <?php echo !empty($editData) ? "تعديل الآلية" : "إضافة آلية جديدة"; ?>
                    </h5>
                </div>
            <div class="card">
                <div class="card-body">
                    <div class="form-grid">
                        <?php if (!empty($editData)) { ?>
                            <input type="hidden" name="edit_id"
                                value="<?php echo isset($editData['id']) ? $editData['id'] : ''; ?>">
                        <?php } ?>

                        <div>
                            <label>
                                <i class="fas fa-truck-loading"></i>
                                المورد <span class="required-indicator">*</span>
                            </label>
                            <select name="suppliers" id="suppliers" required>
                                <option value="">-- اختر المورد --</option>
                                <?php
                                $supplier_company_where = ($equipment_has_company_id && $current_company_id > 0) ? " AND company_id = $current_company_id" : "";
                                $supplier_query = "SELECT id, name FROM suppliers WHERE status = 1$supplier_company_where ORDER BY name";
                                $supplier_result = mysqli_query($conn, $supplier_query);
                                if ($supplier_result) while ($supplier = mysqli_fetch_assoc($supplier_result)) {
                                    $selected = (!empty($editData) && $editData['suppliers'] == $supplier['id']) ? 'selected' : '';
                                    echo "<option value='{$supplier['id']}' $selected>{$supplier['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-barcode"></i>
                                كود المعدة <span class="required-indicator">*</span>
                            </label>
                            <input type="text" name="code" id="code" placeholder="أدخل كود المعدة"
                                value="<?php echo isset($editData['code']) ? htmlspecialchars($editData['code']) : ''; ?>"
                                required />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-list-alt"></i>
                                نوع المعدة <span class="required-indicator">*</span>
                            </label>
                            <select name="type" id="type" required>
                                <option value="">-- حدد نوع المعدة --</option>
                                <?php
                                $type_query = "SELECT id, type FROM equipments_types WHERE status = 1 ORDER BY type";
                                $type_result = mysqli_query($conn, $type_query);
                                if ($type_result) {
                                    while ($type_row = mysqli_fetch_assoc($type_result)) {
                                        $selected = (!empty($editData) && $editData['type'] == $type_row['id']) ? 'selected' : '';
                                        echo "<option value='" . intval($type_row['id']) . "' $selected>" . htmlspecialchars($type_row['type']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-tag"></i>
                                اسم المعدة <span class="required-indicator">*</span>
                            </label>
                            <input type="text" name="name" id="name" placeholder="أدخل اسم المعدة"
                                value="<?php echo isset($editData['name']) ? htmlspecialchars($editData['name']) : ''; ?>"
                                required />
                        </div>

                        <!-- ================================= -->
                        <!-- قسم: المعلومات الأساسية والتعريفية -->
                        <!-- ================================= -->
                        <div class="form-section">
                            <h6><i class="fas fa-id-card"></i> المعلومات الأساسية والتعريفية</h6>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-hashtag"></i>
                                رقم المعدة/الرقم التسلسلي
                            </label>
                            <input type="text" name="serial_number" id="serial_number" placeholder="مثال: EXC-2024-001"
                                value="<?php echo isset($editData['serial_number']) ? htmlspecialchars($editData['serial_number']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-barcode"></i>
                                رقم الهيكل/الهيكل الأساسي (VIN/Chassis)
                            </label>
                            <input type="text" name="chassis_number" id="chassis_number"
                                placeholder="مثال: CAT320-ABC123456"
                                value="<?php echo isset($editData['chassis_number']) ? htmlspecialchars($editData['chassis_number']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-microchip"></i>
                                رقم الماكينة
                            </label>
                            <input type="text" name="machine_number" id="machine_number"
                                placeholder="رقم الماكينة او المحرك"
                                value="<?php echo isset($editData['machine_number']) ? htmlspecialchars($editData['machine_number']) : ''; ?>" />
                        </div>

                        <!-- ================================= -->
                        <!-- قسم: بيانات الصنع والموديل -->
                        <!-- ================================= -->
                        <div class="form-section">
                            <h6><i class="fas fa-industry"></i> بيانات الصنع والموديل</h6>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-building"></i>
                                الماركة/الشركة المصنعة
                            </label>
                            <input type="text" name="manufacturer" id="manufacturer"
                                placeholder="مثال: كاتربيلر، كوماتسو، هيونداي"
                                value="<?php echo isset($editData['manufacturer']) ? htmlspecialchars($editData['manufacturer']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-car"></i>
                                الموديل/الطراز
                            </label>
                            <input type="text" name="model" id="model" placeholder="مثال: 320D, PC200, HD1024"
                                value="<?php echo isset($editData['model']) ? htmlspecialchars($editData['model']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-calendar"></i>
                                سنة الصنع
                            </label>
                            <input type="number" name="manufacturing_year" id="manufacturing_year" placeholder="مثال: 2018"
                                min="1950" max="2099"
                                value="<?php echo isset($editData['manufacturing_year']) ? $editData['manufacturing_year'] : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-calendar-plus"></i>
                                سنة الاستيراد/البدء
                            </label>
                            <input type="number" name="import_year" id="import_year" placeholder="مثال: 2020" min="1950"
                                max="2099"
                                value="<?php echo isset($editData['import_year']) ? $editData['import_year'] : ''; ?>" />
                        </div>

                        <!-- ================================= -->
                        <!-- قسم: الحالة الفنية والمواصفات -->
                        <!-- ================================= -->
                        <div class="form-section">
                            <h6><i class="fas fa-wrench"></i> الحالة الفنية والمواصفات</h6>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-cogs"></i>
                                حالة المعدة
                            </label>
                            <select name="equipment_condition" id="equipment_condition">
                                <option value="جديدة (لم تستخدم)" <?php echo (!empty($editData) && $editData['equipment_condition'] == "جديدة (لم تستخدم)") ? "selected" : ""; ?>>جديدة (لم
                                    تستخدم)</option>
                                <option value="جديدة نسبياً (أقل من سنة استخدام)" <?php echo (!empty($editData) && $editData['equipment_condition'] == "جديدة نسبياً (أقل من سنة استخدام)") ? "selected" : ""; ?>>جديدة نسبياً (أقل من سنة استخدام)</option>
                                <option value="في حالة جيدة" <?php echo (empty($editData) || $editData['equipment_condition'] == "في حالة جيدة") ? "selected" : ""; ?>>في حالة جيدة
                                </option>
                                <option value="في حالة متوسطة" <?php echo (!empty($editData) && $editData['equipment_condition'] == "في حالة متوسطة") ? "selected" : ""; ?>>في حالة متوسطة
                                </option>
                                <option value="في حالة ضعيفة" <?php echo (!empty($editData) && $editData['equipment_condition'] == "في حالة ضعيفة") ? "selected" : ""; ?>>في حالة ضعيفة
                                </option>
                                <option value="محتاجة إصلاح فوري" <?php echo (!empty($editData) && $editData['equipment_condition'] == "محتاجة إصلاح فوري") ? "selected" : ""; ?>>محتاجة
                                    إصلاح فوري</option>
                                <option value="معطلة مؤقتاً" <?php echo (!empty($editData) && $editData['equipment_condition'] == "معطلة مؤقتاً") ? "selected" : ""; ?>>معطلة مؤقتاً
                                </option>
                                <option value="مستعملة بكثافة" <?php echo (!empty($editData) && $editData['equipment_condition'] == "مستعملة بكثافة") ? "selected" : ""; ?>>مستعملة بكثافة
                                </option>
                            </select>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-clock"></i>
                                ساعات التشغيل (للمعدات الثقيلة)
                            </label>
                            <input type="number" name="operating_hours" id="operating_hours" placeholder="مثال: 5400 ساعة"
                                min="0"
                                value="<?php echo isset($editData['operating_hours']) ? $editData['operating_hours'] : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-car-crash"></i>
                                حالة المحرك
                            </label>
                            <select name="engine_condition" id="engine_condition">
                                <option value="ممتازة" <?php echo (!empty($editData) && $editData['engine_condition'] == "ممتازة") ? "selected" : ""; ?>>ممتازة</option>
                                <option value="جيدة" <?php echo (empty($editData) || $editData['engine_condition'] == "جيدة") ? "selected" : ""; ?>>جيدة</option>
                                <option value="متوسطة" <?php echo (!empty($editData) && $editData['engine_condition'] == "متوسطة") ? "selected" : ""; ?>>متوسطة</option>
                                <option value="محتاجة صيانة" <?php echo (!empty($editData) && $editData['engine_condition'] == "محتاجة صيانة") ? "selected" : ""; ?>>محتاجة صيانة
                                </option>
                                <option value="محتاجة إصلاح" <?php echo (!empty($editData) && $editData['engine_condition'] == "محتاجة إصلاح") ? "selected" : ""; ?>>محتاجة إصلاح
                                </option>
                            </select>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-circle-notch"></i>
                                حالة الإطارات (للشاحنات)
                            </label>
                            <select name="tires_condition" id="tires_condition">
                                <option value="N/A" <?php echo (empty($editData) || $editData['tires_condition'] == "N/A") ? "selected" : ""; ?>>N/A</option>
                                <option value="جديدة" <?php echo (!empty($editData) && $editData['tires_condition'] == "جديدة") ? "selected" : ""; ?>>جديدة</option>
                                <option value="جيدة" <?php echo (!empty($editData) && $editData['tires_condition'] == "جيدة") ? "selected" : ""; ?>>جيدة</option>
                                <option value="متوسطة" <?php echo (!empty($editData) && $editData['tires_condition'] == "متوسطة") ? "selected" : ""; ?>>متوسطة</option>
                                <option value="محتاجة تبديل" <?php echo (!empty($editData) && $editData['tires_condition'] == "محتاجة تبديل") ? "selected" : ""; ?>>محتاجة تبديل
                                </option>
                            </select>
                        </div>

                        <!-- ================================= -->
                        <!-- قسم: بيانات الملكية -->
                        <!-- ================================= -->
                        <div class="form-section">
                            <h6><i class="fas fa-user-tie"></i> بيانات الملكية</h6>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-user"></i>
                                اسم المالك الفعلي
                            </label>
                            <input type="text" name="actual_owner_name" id="actual_owner_name"
                                placeholder="مثال: محمد علي أحمد"
                                value="<?php echo isset($editData['actual_owner_name']) ? htmlspecialchars($editData['actual_owner_name']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-briefcase"></i>
                                نوع المالك
                            </label>
                            <select name="owner_type" id="owner_type">
                                <option value="">-- اختر نوع المالك --</option>
                                <option value="مالك فردي" <?php echo (!empty($editData) && $editData['owner_type'] == "مالك فردي") ? "selected" : ""; ?>>مالك فردي</option>
                                <option value="شركة متخصصة" <?php echo (!empty($editData) && $editData['owner_type'] == "شركة متخصصة") ? "selected" : ""; ?>>شركة متخصصة</option>
                                <option value="مؤسسة" <?php echo (!empty($editData) && $editData['owner_type'] == "مؤسسة") ? "selected" : ""; ?>>مؤسسة</option>
                                <option value="شركة إيكوبيشن" <?php echo (!empty($editData) && $editData['owner_type'] == "شركة إيكوبيشن") ? "selected" : ""; ?>>شركة إيكوبيشن</option>
                                <option value="أخرى" <?php echo (!empty($editData) && $editData['owner_type'] == "أخرى") ? "selected" : ""; ?>>أخرى</option>
                            </select>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-phone"></i>
                                رقم هاتف المالك
                            </label>
                            <input type="text" name="owner_phone" id="owner_phone" placeholder="مثال: +249-9-123-4567"
                                value="<?php echo isset($editData['owner_phone']) ? htmlspecialchars($editData['owner_phone']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-handshake"></i>
                                علاقة المالك بالمورد
                            </label>
                            <select name="owner_supplier_relation" id="owner_supplier_relation">
                                <option value="">-- اختر العلاقة --</option>
                                <option value="مالك مباشر (يتعاقد معنا مباشرة)" <?php echo (!empty($editData) && $editData['owner_supplier_relation'] == "مالك مباشر (يتعاقد معنا مباشرة)") ? "selected" : ""; ?>>مالك مباشر (يتعاقد معنا مباشرة)</option>
                                <option value="تحت وساطة المورد (المورد يدير المعدة نيابة عنه)" <?php echo (!empty($editData) && $editData['owner_supplier_relation'] == "تحت وساطة المورد (المورد يدير المعدة نيابة عنه)") ? "selected" : ""; ?>>تحت وساطة المورد (المورد يدير المعدة
                                    نيابة عنه)</option>
                                <option value="تابع للمورد (مملوكة للمورد نفسه)" <?php echo (!empty($editData) && $editData['owner_supplier_relation'] == "تابع للمورد (مملوكة للمورد نفسه)") ? "selected" : ""; ?>>تابع للمورد (مملوكة للمورد نفسه)</option>
                                <option value="غير محدد" <?php echo (!empty($editData) && $editData['owner_supplier_relation'] == "غير محدد") ? "selected" : ""; ?>>غير محدد
                                </option>
                            </select>
                        </div>

                        <!-- ================================= -->
                        <!-- قسم: الوثائق والتسجيلات -->
                        <!-- ================================= -->
                        <div class="form-section">
                            <h6><i class="fas fa-file-contract"></i> الوثائق والتسجيلات</h6>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-address-card"></i>
                                رقم الترخيص/التسجيل
                            </label>
                            <input type="text" name="license_number" id="license_number" placeholder="مثال: VEH-2024-12345"
                                value="<?php echo isset($editData['license_number']) ? htmlspecialchars($editData['license_number']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-landmark"></i>
                                جهة الترخيص
                            </label>
                            <input type="text" name="license_authority" id="license_authority"
                                placeholder="مثال: المرور، وزارة النقل"
                                value="<?php echo isset($editData['license_authority']) ? htmlspecialchars($editData['license_authority']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-file-alt"></i>
                                نوع الوثيقة
                            </label>
                            <select name="document_type" id="document_type">
                                <option value="">-- اختر نوع الوثيقة --</option>
                                <option value="شهادة وارد" <?php echo (!empty($editData) && $editData['document_type'] == "شهادة وارد") ? "selected" : ""; ?>>شهادة وارد</option>
                                <option value="ترخيص ( شهادة بحث)" <?php echo (!empty($editData) && $editData['document_type'] == "ترخيص ( شهادة بحث)") ? "selected" : ""; ?>>ترخيص ( شهادة
                                    بحث)</option>
                                <option value="عقد بيع" <?php echo (!empty($editData) && $editData['document_type'] == "عقد بيع") ? "selected" : ""; ?>>عقد بيع</option>
                            </select>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-calendar-times"></i>
                                تاريخ انتهاء الترخيص
                            </label>
                            <input type="date" name="license_expiry_date" id="license_expiry_date"
                                value="<?php echo isset($editData['license_expiry_date']) ? $editData['license_expiry_date'] : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-certificate"></i>
                                رقم شهادة الفحص
                            </label>
                            <input type="text" name="inspection_certificate_number" id="inspection_certificate_number"
                                placeholder="رقم شهادة الفحص الفنية"
                                value="<?php echo isset($editData['inspection_certificate_number']) ? htmlspecialchars($editData['inspection_certificate_number']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-calendar-check"></i>
                                تاريخ آخر فحص
                            </label>
                            <input type="date" name="last_inspection_date" id="last_inspection_date"
                                value="<?php echo isset($editData['last_inspection_date']) ? $editData['last_inspection_date'] : ''; ?>" />
                        </div>

                        <!-- ================================= -->
                        <!-- قسم: الموقع والتوفر -->
                        <!-- ================================= -->
                        <div class="form-section">
                            <h6><i class="fas fa-map-marker-alt"></i> الموقع والتوفر</h6>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-location-arrow"></i>
                                الموقع الحالي
                            </label>
                            <input type="text" name="current_location" id="current_location"
                                placeholder="مثال: منجم الذهب الشرقي، مستودع الخرطوم"
                                value="<?php echo isset($editData['current_location']) ? htmlspecialchars($editData['current_location']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-traffic-light"></i>
                                التوفر
                            </label>
                            <select name="availability_state" id="availability_state">
                                <option value="متوفرة" <?php echo (empty($editData) || $editData['availability_state'] == "متوفرة") ? "selected" : ""; ?>>متوفرة</option>
                                <option value="غير متوفرة" <?php echo (!empty($editData) && $editData['availability_state'] == "غير متوفرة") ? "selected" : ""; ?>>غير متوفرة</option>
                            </select>
                            <small class="availability-note">المعدات غير المتوفرة لن تظهر في جداول التشغيل.</small>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-exclamation-circle"></i>
                                الحالة الحالية
                            </label>
                            <select name="availability_status" id="availability_status">
                                <option value="قيد الاستخدام" <?php echo (empty($editData) || $editData['availability_status'] == "قيد الاستخدام") ? "selected" : ""; ?>>قيد الاستخدام
                                </option>
                                <option value="تحت الصيانة" <?php echo (!empty($editData) && $editData['availability_status'] == "تحت الصيانة") ? "selected" : ""; ?>>تحت الصيانة
                                </option>
                                <option value="محجوزة" <?php echo (!empty($editData) && $editData['availability_status'] == "محجوزة") ? "selected" : ""; ?>>محجوزة</option>
                                <option value="معطلة" <?php echo (!empty($editData) && $editData['availability_status'] == "معطلة") ? "selected" : ""; ?>>معطلة</option>
                                <option value="في المستودع" <?php echo (!empty($editData) && $editData['availability_status'] == "في المستودع") ? "selected" : ""; ?>>في المستودع
                                </option>
                                <option value="مسحوبة" <?php echo (!empty($editData) && $editData['availability_status'] == "مسحوبة") ? "selected" : ""; ?>>مسحوبة</option>
                            </select>
                            <small id="availabilityStatusHint" class="availability-note"></small>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-user-hard-hat"></i>
                                بيانات المهندس أو المشرف في الموقع
                            </label>
                            <input type="text" name="site_supervisor_name" id="site_supervisor_name"
                                placeholder="اسم المهندس أو المشرف المسؤول"
                                value="<?php echo isset($editData['site_supervisor_name']) ? htmlspecialchars($editData['site_supervisor_name']) : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-address-book"></i>
                                بيانات الاتصال بالمشرف
                            </label>
                            <input type="text" name="site_supervisor_contact" id="site_supervisor_contact"
                                placeholder="رقم الهاتف أو أي وسيلة تواصل مباشرة"
                                value="<?php echo isset($editData['site_supervisor_contact']) ? htmlspecialchars($editData['site_supervisor_contact']) : ''; ?>" />
                        </div>

                        <!-- ================================= -->
                        <!-- قسم: البيانات المالية والقيمة -->
                        <!-- ================================= -->
                        <div class="form-section">
                            <h6><i class="fas fa-dollar-sign"></i> البيانات المالية والقيمة</h6>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-money-bill-wave"></i>
                                القيمة المقدرة للمعدة (بالدولار)
                            </label>
                            <input type="number" name="estimated_value" id="estimated_value" placeholder="مثال: 150000"
                                min="0" step="0.01"
                                value="<?php echo isset($editData['estimated_value']) ? $editData['estimated_value'] : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-calendar-day"></i>
                                سعر التأجير اليومي (بالدولار)
                            </label>
                            <input type="number" name="daily_rental_price" id="daily_rental_price" placeholder="مثال: 500"
                                min="0" step="0.01"
                                value="<?php echo isset($editData['daily_rental_price']) ? $editData['daily_rental_price'] : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-calendar-alt"></i>
                                سعر التأجير الشهري (بالدولار)
                            </label>
                            <input type="number" name="monthly_rental_price" id="monthly_rental_price"
                                placeholder="مثال: 10000" min="0" step="0.01"
                                value="<?php echo isset($editData['monthly_rental_price']) ? $editData['monthly_rental_price'] : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-shield-alt"></i>
                                التأمين/الضمان
                            </label>
                            <select name="insurance_status" id="insurance_status">
                                <option value="">-- اختر حالة التأمين --</option>
                                <option value="مؤمن بالكامل" <?php echo (!empty($editData) && $editData['insurance_status'] == "مؤمن بالكامل") ? "selected" : ""; ?>>مؤمن بالكامل
                                </option>
                                <option value="مؤمن جزئياً" <?php echo (!empty($editData) && $editData['insurance_status'] == "مؤمن جزئياً") ? "selected" : ""; ?>>مؤمن جزئياً</option>
                                <option value="غير مؤمن" <?php echo (!empty($editData) && $editData['insurance_status'] == "غير مؤمن") ? "selected" : ""; ?>>غير مؤمن</option>
                                <option value="جاري التأمين" <?php echo (!empty($editData) && $editData['insurance_status'] == "جاري التأمين") ? "selected" : ""; ?>>جاري التأمين
                                </option>
                            </select>
                        </div>

                        <!-- ================================= -->
                        <!-- قسم: ملاحظات وسجل الصيانة -->
                        <!-- ================================= -->
                        <div class="form-section">
                            <h6><i class="fas fa-tools"></i> ملاحظات وسجل الصيانة</h6>
                        </div>

                        <div class="form-grid-full">
                            <label>
                                <i class="fas fa-comment-alt"></i>
                                ملاحظات عامة
                            </label>
                            <textarea name="general_notes" id="general_notes" rows="3"
                                placeholder="مثال: معدة موثوقة، تحتاج إلى صيانة دورية كل 3 أشهر"><?php echo isset($editData['general_notes']) ? htmlspecialchars($editData['general_notes']) : ''; ?></textarea>
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-wrench"></i>
                                تاريخ آخر صيانة
                            </label>
                            <input type="date" name="last_maintenance_date" id="last_maintenance_date"
                                value="<?php echo isset($editData['last_maintenance_date']) ? $editData['last_maintenance_date'] : ''; ?>" />
                        </div>

                        <div>
                            <label>
                                <i class="fas fa-toggle-on"></i>
                                حالة المعدة <span class="required-indicator">*</span>
                            </label>
                            <select name="status" id="status" required>
                                <option value="">-- اختر الحالة --</option>
                                <option value="0" <?php echo (empty($editData) || $editData['status'] == "0") ? "selected" : ""; ?>>متاحة</option>
                                <option value="1" <?php echo (!empty($editData) && $editData['status'] == "1") ? "selected" : ""; ?>>تحت الصيانة</option>
                                <option value="2" <?php echo (!empty($editData) && $editData['status'] == "2") ? "selected" : ""; ?>>محجوزة</option>
                                <option value="3" <?php echo (!empty($editData) && $editData['status'] == "3") ? "selected" : ""; ?>>معطلة</option>
                                <option value="5" <?php echo (!empty($editData) && $editData['status'] == "5") ? "selected" : ""; ?>>مسحوبة</option>
                            </select>
                        </div>

                        <div class="form-actions">
                            <button type="submit">
                                <i class="fas fa-save"></i>
                                <?php echo !empty($editData) ? "تحديث المعدة" : "حفظ المعدة"; ?>
                            </button>
                            <button type="button" class="btn-secondary"
                                onclick="document.getElementById('projectForm').classList.remove('allforms-visible'); document.getElementById('projectForm').reset();">
                                <i class="fas fa-times"></i>
                                إلغاء
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php } ?>

    <!-- جدول المعدات -->
    <div class="card">
        <div class="card-header">
            <h5>
                <i class="fas fa-list-alt"></i>
                قائمة المعدات
            </h5>
        </div>
        <div class="card-body">
            <!-- نظام الفلاتر -->
            <div class="filters-container">
                <div class="filters-header">
                    <h6><i class="fas fa-filter"></i> فلترة المعدات</h6>
                    <button type="button" class="btn-clear-filters" id="clearFiltersBtn">
                        <i class="fas fa-times-circle"></i> إلغاء الفلاتر
                    </button>
                </div>

                <div class="filters-grid">
                    <div class="filter-item">
                        <label><i class="fas fa-truck-loading"></i> فلترة بالمورد</label>
                        <select id="filterSupplier" class="filter-select">
                            <option value="">— جميع الموردين —</option>
                            <?php
                            $supplier_company_filter_where = ($equipment_has_company_id && $current_company_id > 0) ? " AND company_id = $current_company_id" : "";
                            $supplier_filter_query = "SELECT id, name FROM suppliers WHERE status = 1$supplier_company_filter_where ORDER BY name";
                            $supplier_filter_result = mysqli_query($conn, $supplier_filter_query);
                            if ($supplier_filter_result) while ($supplier = mysqli_fetch_assoc($supplier_filter_result)) {
                                echo "<option value='" . htmlspecialchars($supplier['name']) . "'>" . htmlspecialchars($supplier['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label><i class="fas fa-list-alt"></i> فلترة بالنوع</label>
                        <select id="filterType" class="filter-select">
                            <option value="">— جميع الأنواع —</option>
                            <?php
                            $type_filter_query = "SELECT id, type FROM equipments_types WHERE status = 1 ORDER BY type";
                            $type_filter_result = mysqli_query($conn, $type_filter_query);
                            if ($type_filter_result) while ($type_row = mysqli_fetch_assoc($type_filter_result)) {
                                echo "<option value='" . htmlspecialchars($type_row['type']) . "'>" . htmlspecialchars($type_row['type']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label><i class="fas fa-toggle-on"></i> فلترة بالحالة</label>
                        <select id="filterStatus" class="filter-select">
                            <option value="">— جميع الحالات —</option>
                            <option value="متاحة">متاحة</option>
                            <option value="تحت الصيانة">تحت الصيانة</option>
                            <option value="محجوزة">محجوزة</option>
                            <option value="معطلة">معطلة</option>
                            <option value="مسحوبة">مسحوبة</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label><i class="fas fa-traffic-light"></i> فلترة بالتوفر</label>
                        <select id="filterAvailability" class="filter-select">
                            <option value="">— جميع حالات التوفر —</option>
                            <option value="متوفرة">متوفرة</option>
                            <option value="غير متوفرة">غير متوفرة</option>
                        </select>
                    </div>
                </div>

                <div class="filters-summary fleet-hidden" id="filtersSummary">
                    <span class="summary-icon"><i class="fas fa-check-circle"></i></span>
                    <span class="summary-text"></span>
                </div>
            </div>

            <!-- أزرار إظهار/إخفاء المجموعات -->
            <div class="contracts-group-toolbar-wrap">
              <div class="contracts-group-toolbar">
                <span class="contracts-group-toolbar-label">
                    <i class="fas fa-filter"></i> عرض المجموعات:
                </span>
                <button type="button" class="btn-group-toggle active" data-group="basic" title="المعلومات الأساسية">
                    <i class="fas fa-info-circle"></i> أساسية
                </button>
                <button type="button" class="btn-group-toggle" data-group="manufacturing" title="بيانات الصنع">
                    <i class="fas fa-industry"></i> الصنع
                </button>
                <button type="button" class="btn-group-toggle" data-group="technical" title="الحالة الفنية">
                    <i class="fas fa-wrench"></i> فنية
                </button>
                <button type="button" class="btn-group-toggle active" data-group="ownership" title="بيانات الملكية">
                    <i class="fas fa-user-tie"></i> الملكية
                </button>
                <button type="button" class="btn-group-toggle active" data-group="status" title="الحالة والإجراءات">
                    <i class="fas fa-toggle-on"></i> الحالة
                </button>
                <button type="button" class="btn-group-toggle-all" title="إظهار/إخفاء الكل">
                    <i class="fas fa-eye"></i> الكل
                </button>
              </div>
            </div>

            <div class="table-scroll-wrap">
                <table id="projectsTable" class="display nowrap">
                    <thead>
                        <tr>
                            <th data-group="status">> إجراءات</th>
                            <th data-group="basic"> كود المعدة</th>
                            <th data-group="basic"> المورد</th>
                            <th data-group="basic"> النوع</th>
                            <th data-group="manufacturing"> الموديل</th>
                            <th data-group="manufacturing"> سنة الصنع</th>
                            <th data-group="technical"> حالة المعدة</th>
                            <th data-group="ownership"> المالك</th>
                            <th data-group="status"> التوفر</th>
                            <th data-group="status"> الحالة </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $availability_state_select = $equipment_has_availability_state
                            ? "m.availability_state,"
                            : "NULL AS availability_state,";
                        $query2 = "
                        SELECT
                            m.id,
                            s.name AS supplier_name,
                            m.type,
                            et.type AS equipment_type_name,
                            m.code,
                            m.name,
                            m.status,
                            m.serial_number,
                            m.model,
                            m.manufacturing_year,
                            m.equipment_condition,
                            m.actual_owner_name,
                            m.availability_status,
                            $availability_state_select
                            o.$operations_project_col AS project_id,
                            o.status AS operation_status,
                            COUNT(DISTINCT d.id) AS drivers_count
                        FROM equipments m
                        JOIN suppliers s ON m.suppliers = s.id
                        LEFT JOIN equipments_types et ON m.type = et.id
                        LEFT JOIN operations o
                            ON o.equipment = m.id
                            AND o.status = '1'
                        LEFT JOIN equipment_drivers ed
                            ON ed.equipment_id = m.id
                        LEFT JOIN drivers d
                            ON d.id = ed.driver_id
                            AND ed.status = '1'
                        WHERE 1=1 $equipment_company_filter
                        GROUP BY m.id
                        ORDER BY m.id DESC
                    ";
                        $result = mysqli_query($conn, $query2);
                        if ($result) while ($row = mysqli_fetch_assoc($result)) {


                            echo "<tr>";
                                    // الإجراءات
                            echo "<td>";
                            echo "<a href='javascript:void(0)' class='action-btn view viewEquipmentBtn' data-id='" . $row['id'] . "' title='عرض التفاصيل'>
                                                        <i class='fas fa-eye'></i>
                                                    </a>";
                            if ($can_edit) {
                                echo "<a href='equipments_fleet.php?edit=" . $row['id'] . "' class='action-btn btn-edit' title='تعديل'>
                                                                        <i class='fas fa-edit'></i>
                                                                    </a>";
                            }
                            echo "</td>";

                            $equipment_profile_url = "equipment_profile.php?id=" . intval($row['id']);
                            echo "<td><a class='client-name-link' href='" . $equipment_profile_url . "'><strong>" . htmlspecialchars($row['code']) . "</strong></a></td>";
                            echo "<td><strong class='supplier-name'>" . htmlspecialchars($row['supplier_name']) . "</strong></td>";
                            // echo "<td><span class='mono code-badge'>" . htmlspecialchars($row['code']) . "</span></td>";

                            // // رقم تسلسلي
                            // $serial = !empty($row['serial_number'])
                            //     ? "<span class='mono'>" . htmlspecialchars($row['serial_number']) . "</span>"
                            //     : "<span class='text-muted'>غير محدد</span>";
                            // echo "<td>" . $serial . "</td>";

                            // نوع المعدة - من جدول equipments_types
                            $type_text = !empty($row['equipment_type_name']) ? htmlspecialchars($row['equipment_type_name']) : 'غير محدد';

                            // تحديد الأيقونة بناءً على النوع
                            $type_icon = "fa-tools"; // أيقونة افتراضية
                            if (stripos($type_text, 'حفار') !== false) {
                                $type_icon = "fa-tractor";
                            } elseif (stripos($type_text, 'قلاب') !== false) {
                                $type_icon = "fa-truck-moving";
                            } elseif (stripos($type_text, 'خرامه') !== false || stripos($type_text, 'حفر') !== false) {
                                $type_icon = "fa-drill";
                            } elseif (stripos($type_text, 'رافعة') !== false) {
                                $type_icon = "fa-dolly";
                            } elseif (stripos($type_text, 'شاحنة') !== false) {
                                $type_icon = "fa-truck";
                            }

                            // تفاصيل إضافية بجانب الكود
                            $name_display = "";

                            // المشروع النشط
                            if (!empty($row['project_id'])) {
                                $p_res = mysqli_query($conn, "SELECT name FROM project WHERE id='" . intval($row['project_id']) . "'");
                                if ($p_res && mysqli_num_rows($p_res) > 0) {
                                    $p = mysqli_fetch_assoc($p_res);
                                    $name_display .= "<br><span class='project-link'><i class='fas fa-project-diagram'></i> " . htmlspecialchars($p['name']) . "</span>";
                                }
                            }

                            // عدد السائقين النشطين
                            if ($row['drivers_count'] > 0) {
                                $name_display .= "<br><span class='extra-info'><i class='fas fa-users'></i> " . $row['drivers_count'] . " سائق</span>";
                            }

                            echo "<td><span class='badge-type'><i class='fas $type_icon'></i> $type_text</span>" . $name_display . "</td>";

                            // الموديل
                            $model = !empty($row['model']) ? htmlspecialchars($row['model']) : "<span class='text-muted'>غير محدد</span>";
                            echo "<td>" . $model . "</td>";

                            // سنة الصنع
                            $manufacturing_year = !empty($row['manufacturing_year']) ? $row['manufacturing_year'] : "<span class='text-muted'>غير محدد</span>";
                            echo "<td>" . $manufacturing_year . "</td>";

                            // حالة المعدة
                            $equipment_condition = !empty($row['equipment_condition']) ? htmlspecialchars($row['equipment_condition']) : "<span class='text-muted'>غير محدد</span>";
                            echo "<td>" . $equipment_condition . "</td>";

                            // المالك
                            $owner = !empty($row['actual_owner_name']) ? htmlspecialchars($row['actual_owner_name']) : "<span class='text-muted'>غير محدد</span>";
                            echo "<td>" . $owner . "</td>";

                            $row_availability_state = normalize_equipment_availability_state(
                                isset($row['availability_state']) ? $row['availability_state'] : '',
                                isset($row['availability_status']) ? $row['availability_status'] : ''
                            );
                            $row_availability_status = normalize_equipment_availability_status(
                                $row_availability_state,
                                isset($row['availability_status']) ? $row['availability_status'] : ''
                            );

                            // التوفر
                            if ($row_availability_state === 'متوفرة') {
                                echo "<td><span class='badge-available'><i class='fa-regular fa-circle-check'></i> متوفرة</span></td>";
                            } else {
                                echo "<td><span class='badge-busy'><i class='fa-regular fa-circle-xmark'></i> غير متوفرة</span></td>";
                            }

                            // حالة المعدة (من حقل status الرقمي)
                            $eq_status = isset($row['status']) ? intval($row['status']) : 0;
                            $status_map = [
                                0 => ["badge-working", "fa-spinner fa-spin", "متاحة"],
                                1 => ["badge-busy", "fa-tools", "تحت الصيانة"],
                                2 => ["badge-type", "fa-bookmark", "محجوزة"],
                                3 => ["badge-busy", "fa-exclamation-triangle", "معطلة"],
                                5 => ["badge-busy", "fa-arrow-alt-circle-down", "مسحوبة"],
                            ];
                            $s = isset($status_map[$eq_status]) ? $status_map[$eq_status] : ["badge-type", "fa-question-circle", "غير محدد"];
                            echo "<td><span class='{$s[0]}'><i class='fas {$s[1]}'></i> {$s[2]}</span></td>";



                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal عرض تفاصيل المعدة -->
    <!-- نافذة تفاصيل المعدة تُولَّد عبر النظام الموحّد EmsDetailsModal -->

    <!-- jQuery -->
    <script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
    <!-- DataTables JS -->
    <script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
    <script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
    <script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
    <script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
    <script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
    <script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
    <script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

    <script>
        (function () {
            $(document).ready(function () {
                var table = $('#projectsTable').DataTable({
                    dom: 'Bfrtip',
                    scrollX: true,
                    autoWidth: false,
                    buttons: [
                        { extend: 'copy', text: 'نسخ' },
                        { extend: 'excel', text: 'تصدير Excel' },
                        { extend: 'csv', text: 'تصدير CSV' },
                        { extend: 'pdf', text: 'تصدير PDF' },
                        { extend: 'print', text: 'طباعة' }
                    ],
                    "language": {
                        "url": "/ems/assets/i18n/datatables/ar.json"
                    }
                });

                // نظام إظهار/إخفاء المجموعات — خريطة الفهارس تُمرّر للوحدة الموحّدة.
                // الحالة الافتراضية (المُفعّلة/المخفية) تؤخذ من كلاس active على الأزرار.
                var columnGroups = {
                    'basic': [0, 1, 2, 3],          // إجراءات، الكود، المورد، النوع
                    'manufacturing': [4, 5],        // الموديل، سنة الصنع
                    'technical': [6],               // حالة المعدة
                    'ownership': [7],               // المالك
                    'status': [8, 9]                // التوفر، الحالة
                };

                // نظام الفلترة الاحترافي
                var activeFilters = {
                    supplier: '',
                    type: '',
                    status: '',
                    availability: ''
                };

                // تهيئة الفلاتر
                $('#filterSupplier, #filterType, #filterStatus, #filterAvailability').on('change', function () {
                    var filterType = $(this).attr('id').replace('filter', '').toLowerCase();
                    activeFilters[filterType] = $(this).val();
                    applyFilters();
                    updateFiltersSummary();
                });

                // تطبيق الفلاتر
                function applyFilters() {
                    $.fn.dataTable.ext.search.push(
                        function (settings, data, dataIndex) {
                            // data[2] = المورد
                            // data[3] = النوع
                            // data[9] = الحالة
                            // data[8] = التوفر

                            var supplierMatch = true;
                            var typeMatch = true;
                            var statusMatch = true;
                            var availabilityMatch = true;

                            // فلترة المورد
                            if (activeFilters.supplier !== '') {
                                supplierMatch = data[2].indexOf(activeFilters.supplier) !== -1;
                            }

                            // فلترة النوع
                            if (activeFilters.type !== '') {
                                typeMatch = data[3].indexOf(activeFilters.type) !== -1;
                            }

                            // فلترة الحالة
                            if (activeFilters.status !== '') {
                                statusMatch = data[9].indexOf(activeFilters.status) !== -1;
                            }

                            // فلترة التوفر (مطابقة دقيقة لتجنب تشابه "متوفرة" مع "غير متوفرة")
                            if (activeFilters.availability !== '') {
                                if (activeFilters.availability === 'متوفرة') {
                                    availabilityMatch = data[8].indexOf('غير متوفرة') === -1 && data[8].indexOf('متوفرة') !== -1;
                                } else {
                                    availabilityMatch = data[8].indexOf(activeFilters.availability) !== -1;
                                }
                            }

                            return supplierMatch && typeMatch && statusMatch && availabilityMatch;
                        }
                    );

                    table.draw();

                    // إزالة دالة البحث بعد التطبيق لتجنب التكرار
                    $.fn.dataTable.ext.search.pop();
                }

                // تحديث ملخص الفلاتر
                function updateFiltersSummary() {
                    var activeCount = 0;
                    var summaryParts = [];

                    if (activeFilters.supplier) {
                        activeCount++;
                        summaryParts.push('المورد: ' + activeFilters.supplier);
                    }
                    if (activeFilters.type) {
                        activeCount++;
                        summaryParts.push('النوع: ' + activeFilters.type);
                    }
                    if (activeFilters.status) {
                        activeCount++;
                        summaryParts.push('الحالة: ' + activeFilters.status);
                    }
                    if (activeFilters.availability) {
                        activeCount++;
                        summaryParts.push('التوفر: ' + activeFilters.availability);
                    }

                    var $summary = $('#filtersSummary');
                    if (activeCount > 0) {
                        $summary.find('.summary-text').text(
                            'تم تطبيق ' + activeCount + ' فلتر: ' + summaryParts.join(' | ')
                        );
                        $summary.slideDown(300);
                    } else {
                        $summary.slideUp(300);
                    }
                }

                // إلغاء جميع الفلاتر
                $('#clearFiltersBtn').on('click', function () {
                    activeFilters = {
                        supplier: '',
                        type: '',
                        status: '',
                        availability: ''
                    };

                    $('#filterSupplier, #filterType, #filterStatus, #filterAvailability').val('');
                    applyFilters();
                    updateFiltersSummary();

                    // تأثير بصري
                    $(this).addClass('btn-clear-active');
                    setTimeout(function () {
                        $('#clearFiltersBtn').removeClass('btn-clear-active');
                    }, 300);
                });

                // إظهار/إخفاء المجموعات — موحّد عبر assets/js/column-groups.js
                (function () {
                    function go() {
                        if (window.EmsColumnGroups) {
                            EmsColumnGroups.init({
                                storageKey: 'fleetGroupStates',
                                mode: 'datatable',
                                table: table,
                                columnMap: columnGroups
                            });
                        }
                    }
                    if (window.EmsColumnGroups) { go(); } else { window.addEventListener('DOMContentLoaded', go); }
                })();

                const statsToggleBtn = $('#toggleStats');
                const statsSection = $('#fleetStatsSection');

                function updateStatsToggleState(isVisible) {
                    if (!statsToggleBtn.length) return;
                    statsToggleBtn.toggleClass('is-active', isVisible);
                    statsToggleBtn.attr('aria-expanded', isVisible ? 'true' : 'false');
                    statsToggleBtn.find('.fleet-toggle-stats-text').text(isVisible ? 'إخفاء الإحصائيات' : 'إظهار الإحصائيات');
                    const icon = statsToggleBtn.find('i').first();
                    icon.toggleClass('fa-chart-pie', isVisible);
                    icon.toggleClass('fa-eye', !isVisible);
                }

                updateStatsToggleState(statsSection.is(':visible'));
                statsToggleBtn.on('click', function (e) {
                    e.preventDefault();
                    if (statsSection.is(':visible')) {
                        statsSection.stop(true, true).slideUp(250, function () {
                            statsSection.addClass('fleet-hidden');
                            updateStatsToggleState(false);
                        });
                    } else {
                        statsSection.removeClass('fleet-hidden').hide();
                        statsSection.stop(true, true).slideDown(250, function () {
                            updateStatsToggleState(true);
                        });
                    }
                });
            });

            const toggleFormBtn = document.getElementById('toggleForm');
            const equipmentForm = document.getElementById('projectForm');
            const projectSelect = document.getElementById('selected_project_id');
            const availabilityStateInput = document.getElementById('availability_state');
            const availabilityStatusInput = document.getElementById('availability_status');
            const availabilityStatusHint = document.getElementById('availabilityStatusHint');

            window.toggleFleetForm = function (event) {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }

                if (!equipmentForm) {
                    return false;
                }

                const isHidden = !equipmentForm.classList.contains('allforms-visible');
                if (isHidden) {
                    equipmentForm.classList.add('allforms-visible');
                    $('html, body').animate({
                        scrollTop: $('#projectForm').offset().top - 100
                    }, 300);
                } else {
                    equipmentForm.classList.remove('allforms-visible');
                }

                return false;
            };

            // يستخدم الزر onclick="toggleFleetForm(event)" داخل HTML،
            // لذلك نتجنب ربط مستمع إضافي هنا حتى لا يتم التبديل مرتين لكل نقرة.

            if (projectSelect) {
                projectSelect.addEventListener('change', function () {
                    if (this.value) {
                        document.getElementById('projectSelectForm').submit();
                    }
                });
            }

            function normalizeAvailabilityState(value, statusValue) {
                if (value === 'متوفرة' || value === 'غير متوفرة') {
                    return value;
                }

                if (!statusValue || statusValue === 'متاحة للعمل' || statusValue === 'قيد الاستخدام') {
                    return 'متوفرة';
                }

                return 'غير متوفرة';
            }

            function normalizeAvailabilityStatus(stateValue, statusValue) {
                const normalizedState = normalizeAvailabilityState(stateValue, statusValue);
                if (normalizedState === 'متوفرة') {
                    return 'قيد الاستخدام';
                }

                const legacyMap = {
                    'موقوفة للصيانة': 'تحت الصيانة',
                    'مبيعة/مسحوبة': 'مسحوبة',
                    'معطلة مؤقتاً': 'معطلة'
                };

                if (legacyMap[statusValue]) {
                    return legacyMap[statusValue];
                }

                const validStatuses = ['تحت الصيانة', 'محجوزة', 'مسحوبة', 'في المستودع', 'معطلة'];
                return validStatuses.indexOf(statusValue) !== -1 ? statusValue : 'تحت الصيانة';
            }

            function syncAvailabilityFields() {
                if (!availabilityStateInput || !availabilityStatusInput) {
                    return;
                }

                const normalizedState = normalizeAvailabilityState(availabilityStateInput.value, availabilityStatusInput.value);
                availabilityStateInput.value = normalizedState;

                if (normalizedState === 'متوفرة') {
                    availabilityStatusInput.value = 'قيد الاستخدام';
                    availabilityStatusInput.setAttribute('disabled', 'disabled');
                    if (availabilityStatusHint) {
                        availabilityStatusHint.textContent = 'عند توفر الآلية يتم تثبيت الحالة تلقائياً على قيد الاستخدام.';
                    }
                } else {
                    availabilityStatusInput.value = normalizeAvailabilityStatus(normalizedState, availabilityStatusInput.value);
                    availabilityStatusInput.removeAttribute('disabled');
                    if (availabilityStatusHint) {
                        availabilityStatusHint.textContent = 'عند عدم التوفر اختر سبب الحالة الفعلية للآلية.';
                    }
                }
            }

            if (availabilityStateInput && availabilityStatusInput) {
                syncAvailabilityFields();
                availabilityStateInput.addEventListener('change', syncAvailabilityFields);
                availabilityStatusInput.addEventListener('change', syncAvailabilityFields);
                if (equipmentForm) {
                    equipmentForm.addEventListener('submit', function () {
                        availabilityStatusInput.removeAttribute('disabled');
                        syncAvailabilityFields();
                    });
                }
            }

            // تحميل بيانات التعديل عند تحميل الصفحة
            <?php if (!empty($editData)) { ?>
                $(document).ready(function () {
                    // عرض الفورم
                    $('#projectForm').show();

                    // التمرير للفورم
                    $('html, body').animate({
                        scrollTop: $('#projectForm').offset().top - 100
                    }, 500);
                });
            <?php } ?>

            // صلاحيات المستخدم
            const canEdit = <?php echo json_encode($can_edit); ?>;
            const canDelete = <?php echo json_encode($can_delete); ?>;

            // Equipment view modal
            // Equipment view modal — عبر النظام الموحّد EmsDetailsModal
            function eqVal(value) {
                return (value !== null && value !== undefined && value !== '') ? value : 'غير محدد';
            }
            function formatCurrency(value) {
                if (value === null || value === undefined || value === '') return 'غير محدد';
                const num = parseFloat(value);
                if (Number.isNaN(num)) return value;
                return '$' + num.toLocaleString();
            }
            function formatType(value, typeName) {
                if (typeName) return typeName;
                if (!value) return 'غير محدد';
                return String(value);
            }
            function formatEquipmentStatus(statusValue) {
                const map = { '0': 'متاحة', '1': 'تحت الصيانة', '2': 'محجوزة', '3': 'معطلة', '5': 'مسحوبة' };
                const key = String(statusValue ?? '');
                return map[key] || 'غير محدد';
            }
            function formatAvailabilityState(value, fallbackStatus) {
                return normalizeAvailabilityState(value, fallbackStatus);
            }

            function buildFleetFields(data) {
                return [
                    { label: 'كود المعدة', value: eqVal(data.code), icon: 'fas fa-barcode' },
                    { label: 'اسم المعدة', value: eqVal(data.name), icon: 'fas fa-tag', size: 'lg' },
                    { label: 'نوع المعدة', value: formatType(data.type, data.equipment_type_name), icon: 'fas fa-tools' },
                    { label: 'المورد', value: eqVal(data.supplier_name), icon: 'fas fa-truck-loading', size: 'lg' },
                    { label: 'المشروع', value: eqVal(data.project_name), icon: 'fas fa-project-diagram', size: 'lg' },
                    { label: 'المنجم', value: eqVal(data.mine_name), icon: 'fas fa-mountain' },
                    { label: 'الرقم التسلسلي', value: eqVal(data.serial_number), icon: 'fas fa-hashtag' },
                    { label: 'رقم الهيكل', value: eqVal(data.chassis_number), icon: 'fas fa-car' },
                    { label: 'رقم الماكينة', value: eqVal(data.machine_number), icon: 'fas fa-microchip' },
                    { label: 'الشركة المصنعة', value: eqVal(data.manufacturer), icon: 'fas fa-industry' },
                    { label: 'الموديل', value: eqVal(data.model), icon: 'fas fa-car-side' },
                    { label: 'سنة الصنع', value: eqVal(data.manufacturing_year), icon: 'fas fa-calendar' },
                    { label: 'سنة الاستيراد', value: eqVal(data.import_year), icon: 'fas fa-calendar-plus' },
                    { label: 'حالة المعدة', value: eqVal(data.equipment_condition), icon: 'fas fa-cogs' },
                    { label: 'ساعات التشغيل', value: data.operating_hours ? (data.operating_hours + ' ساعة') : 'غير محدد', icon: 'fas fa-clock' },
                    { label: 'حالة المحرك', value: eqVal(data.engine_condition), icon: 'fas fa-car-crash' },
                    { label: 'حالة الإطارات', value: eqVal(data.tires_condition), icon: 'fas fa-circle-notch' },
                    { label: 'اسم المالك', value: eqVal(data.actual_owner_name), icon: 'fas fa-user' },
                    { label: 'نوع المالك', value: eqVal(data.owner_type), icon: 'fas fa-briefcase' },
                    { label: 'هاتف المالك', value: eqVal(data.owner_phone), icon: 'fas fa-phone' },
                    { label: 'علاقة المالك بالمورد', value: eqVal(data.owner_supplier_relation), icon: 'fas fa-handshake' },
                    { label: 'رقم الترخيص', value: eqVal(data.license_number), icon: 'fas fa-address-card' },
                    { label: 'جهة الترخيص', value: eqVal(data.license_authority), icon: 'fas fa-landmark' },
                    { label: 'نوع الوثيقة', value: eqVal(data.document_type), icon: 'fas fa-file-alt' },
                    { label: 'انتهاء الترخيص', value: eqVal(data.license_expiry_date), icon: 'fas fa-calendar-times' },
                    { label: 'رقم شهادة الفحص', value: eqVal(data.inspection_certificate_number), icon: 'fas fa-certificate' },
                    { label: 'آخر فحص', value: eqVal(data.last_inspection_date), icon: 'fas fa-calendar-check' },
                    { label: 'الموقع الحالي', value: eqVal(data.current_location), icon: 'fas fa-map-marker-alt', size: 'lg' },
                    { label: 'مهندس/مشرف الموقع', value: eqVal(data.site_supervisor_name), icon: 'fas fa-user-hard-hat' },
                    { label: 'اتصال المشرف', value: eqVal(data.site_supervisor_contact), icon: 'fas fa-address-book' },
                    { label: 'التوفر', value: formatAvailabilityState(data.availability_state, data.availability_status), icon: 'fas fa-traffic-light' },
                    { label: 'القيمة المقدرة', value: formatCurrency(data.estimated_value), icon: 'fas fa-money-bill-wave' },
                    { label: 'سعر التأجير اليومي', value: formatCurrency(data.daily_rental_price), icon: 'fas fa-calendar-day' },
                    { label: 'سعر التأجير الشهري', value: formatCurrency(data.monthly_rental_price), icon: 'fas fa-calendar-alt' },
                    { label: 'التأمين/الضمان', value: eqVal(data.insurance_status), icon: 'fas fa-shield-alt' },
                    { label: 'ملاحظات عامة', value: eqVal(data.general_notes), icon: 'fas fa-comment-alt', size: 'full' },
                    { label: 'آخر صيانة', value: eqVal(data.last_maintenance_date), icon: 'fas fa-wrench' },
                    { label: 'الحالة الحالية', value: formatEquipmentStatus(data.status), icon: 'fas fa-toggle-on', type: 'status', tone: String(data.status) === '0' ? 'active' : 'inactive' }
                ];
            }

            function fleetActions(equipmentId) {
                const actions = [];
                if (canEdit) {
                    actions.push({ label: 'تعديل المعدة', icon: 'fas fa-edit', variant: 'primary',
                        onClick: function () { window.location.href = 'equipments_fleet.php?edit=' + equipmentId; } });
                }
                if (canDelete) {
                    actions.push({ label: 'حذف المعدة', icon: 'fas fa-trash', variant: 'danger',
                        onClick: function () {
                            if (confirm('هل أنت متأكد من حذف هذه المعدة؟')) {
                                window.location.href = 'equipments_fleet.php?delete_id=' + equipmentId;
                            }
                        } });
                }
                actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });
                return actions;
            }

            $(document).on('click', '.viewEquipmentBtn', function () {
                const equipmentId = $(this).data('id');
                if (!equipmentId) return;

                EmsDetailsModal.open({
                    title: 'بيانات المعدة',
                    icon: 'fas fa-truck-monster',
                    sections: [{ title: 'تحميل البيانات', icon: 'fas fa-spinner',
                        html: '<div style="padding:20px;text-align:center;color:var(--t2)"><i class="fas fa-spinner fa-spin"></i> جار التحميل...</div>' }],
                    actions: fleetActions(equipmentId)
                });

                $.ajax({
                    url: 'get_equipment_details.php',
                    type: 'GET',
                    data: { id: equipmentId },
                    dataType: 'json',
                    success: function (response) {
                        if (!response.success || !response.data) {
                            const failMessage = (response && response.message) ? response.message : 'تعذر تحميل البيانات';
                            EmsDetailsModal.setSection(0, { title: 'خطأ', icon: 'fas fa-exclamation-triangle',
                                html: '<div style="padding:16px;text-align:center;color:#c0392b">' + failMessage + '</div>' });
                            return;
                        }
                        EmsDetailsModal.open({
                            title: 'بيانات المعدة',
                            icon: 'fas fa-truck-monster',
                            fields: buildFleetFields(response.data),
                            actions: fleetActions(equipmentId)
                        });
                    },
                    error: function () {
                        EmsDetailsModal.setSection(0, { title: 'خطأ', icon: 'fas fa-exclamation-triangle',
                            html: '<div style="padding:16px;text-align:center;color:#c0392b">تعذر الاتصال بالخادم</div>' });
                    }
                });
            });

            function closeEquipmentModal() { if (window.EmsDetailsModal) EmsDetailsModal.close(); }

            // Toggle Form Functionality
        })();
    </script>

    <!-- استيراد المعدات: نافذة معالج إطار Excel الموحّد (متعددة الخطوات: رفع ← معاينة ← تنفيذ). -->
    <?php ems_excel_render(); ?>

</div> <!-- closing main div -->
</body>

</html>
