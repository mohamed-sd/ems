<?php
/**
 * إضافة بيانات المعدات للعقد الجديد أو المحدّث
 * @param int $contract_id - معرف العقد
 * @param array $equipment_data - بيانات المعدات من الفورم
 * @param mysqli $conn - اتصال قاعدة البيانات
 */

if (!function_exists('saveContractEquipments')) {
function saveContractEquipments($contract_id, $equipment_data, $conn) {
    if (empty($contract_id) || !is_numeric($contract_id)) {
        return false;
    }

    // حذف المعدات القديمة للعقد (في حالة التحديث)
    $delete_sql = "DELETE FROM contractequipments WHERE contract_id = " . intval($contract_id);
    mysqli_query($conn, $delete_sql);

    // إدراج المعدات الجديدة
    foreach ($equipment_data as $equipment) {
        if (empty($equipment['equip_type'])) {
            continue; // تخطي الأقسام الفارغة
        }

        $equip_type = mysqli_real_escape_string($conn, $equipment['equip_type']);
        $equip_size = isset($equipment['equip_size']) ? intval($equipment['equip_size']) : 0;
        $equip_count = isset($equipment['equip_count']) ? intval($equipment['equip_count']) : 0;
        $equip_unit = isset($equipment['equip_unit']) ? mysqli_real_escape_string($conn, $equipment['equip_unit']) : '';
        $equip_target_per_month = isset($equipment['equip_target_per_month']) ? intval($equipment['equip_target_per_month']) : 0;
        $equip_total_month = isset($equipment['equip_total_month']) ? intval($equipment['equip_total_month']) : 0;
        $equip_total_contract = isset($equipment['equip_total_contract']) ? intval($equipment['equip_total_contract']) : 0;
        $equip_price = isset($equipment['equip_price']) ? floatval($equipment['equip_price']) : 0;
        $equip_price_currency = isset($equipment['equip_price_currency']) ? mysqli_real_escape_string($conn, $equipment['equip_price_currency']) : '';
        $equip_operators = isset($equipment['equip_operators']) ? intval($equipment['equip_operators']) : 0;
        $equip_supervisors = isset($equipment['equip_supervisors']) ? intval($equipment['equip_supervisors']) : 0;
        $equip_technicians = isset($equipment['equip_technicians']) ? intval($equipment['equip_technicians']) : 0;
        $equip_assistants = isset($equipment['equip_assistants']) ? intval($equipment['equip_assistants']) : 0;

        $insert_sql = "INSERT INTO contractequipments (
            contract_id,
            equip_type,
            equip_size,
            equip_count,
            equip_unit,
            equip_target_per_month,
            equip_total_month,
            equip_total_contract,
            equip_price,
            equip_price_currency,
            equip_operators,
            equip_supervisors,
            equip_technicians,
            equip_assistants
        ) VALUES (
            " . intval($contract_id) . ",
            '" . $equip_type . "',
            " . $equip_size . ",
            " . $equip_count . ",
            '" . $equip_unit . "',
            " . $equip_target_per_month . ",
            " . $equip_total_month . ",
            " . $equip_total_contract . ",
            " . $equip_price . ",
            '" . $equip_price_currency . "',
            " . $equip_operators . ",
            " . $equip_supervisors . ",
            " . $equip_technicians . ",
            " . $equip_assistants . "
        )";

        mysqli_query($conn, $insert_sql);
    }

    return true;
}

/**
 * جلب المعدات للعقد
 * @param int $contract_id - معرف العقد
 * @param mysqli $conn - اتصال قاعدة البيانات
 */
function getContractEquipments($contract_id, $conn) {
    $sql = "SELECT * FROM contractequipments WHERE contract_id = " . intval($contract_id) . " ORDER BY id ASC";
    $result = mysqli_query($conn, $sql);
    
    $equipments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $equipments[] = $row;
    }
    
    return $equipments;
}

/**
 * حذف معدة من العقد
 * @param int $equipment_id - معرف المعدة
 * @param mysqli $conn - اتصال قاعدة البيانات
 */
function deleteEquipment($equipment_id, $conn) {
    $sql = "DELETE FROM contractequipments WHERE id = " . intval($equipment_id);
    return mysqli_query($conn, $sql);
}
}
?>
