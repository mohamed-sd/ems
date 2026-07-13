<?php
/**
 * إضافة بيانات المعدات للعقد الجديد أو المحدّث — خلف بوابة العزل (هجرة 2026-07-13).
 * التواقيع محفوظة كما هي (مستدعاة من contracts.php / contracts_details.php / get_equipments.php)؛
 * البوابة تُشتق داخليًّا من الجلسة (السوبر → عابرٌ مُسجَّل).
 * @param int $contract_id - معرف العقد
 * @param array $equipment_data - بيانات المعدات من الفورم
 * @param mysqli $conn - اتصال قاعدة البيانات (محفوظ للتوافق — لم يعد يُستعمل)
 */

if (!function_exists('saveContractEquipments')) {

function contract_children_gate() {
    $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
    return ($role === '-1')
        ? ems_tenant_db()->forAllTenants('contract children super')
        : ems_tenant_db();
}

function saveContractEquipments($contract_id, $equipment_data, $conn) {
    if (empty($contract_id) || !is_numeric($contract_id)) {
        return false;
    }

    // بناء سطور الإدراج بقيمٍ أصلية (أوقات الورديات الفارغة تبقى NULL كالأصل؛
    // company_id يُحقن آليًّا، وcontract_id يضبطه replaceChildren بعد تحقُّق الملكية)
    $rows = array();
    foreach ($equipment_data as $equipment) {
        if (empty($equipment['equip_type'])) {
            continue; // تخطي الأقسام الفارغة
        }
        $rows[] = array(
            'equip_type'           => $equipment['equip_type'],
            'equip_size'           => isset($equipment['equip_size']) ? intval($equipment['equip_size']) : 0,
            'equip_count'          => isset($equipment['equip_count']) ? intval($equipment['equip_count']) : 0,
            'equip_count_basic'    => isset($equipment['equip_count_basic']) ? intval($equipment['equip_count_basic']) : 0,
            'equip_count_backup'   => isset($equipment['equip_count_backup']) ? intval($equipment['equip_count_backup']) : 0,
            'equip_shifts'         => isset($equipment['equip_shifts']) ? intval($equipment['equip_shifts']) : 0,
            'equip_unit'           => isset($equipment['equip_unit']) ? $equipment['equip_unit'] : '',
            'shift1_start'         => !empty($equipment['shift1_start']) ? $equipment['shift1_start'] : null,
            'shift1_end'           => !empty($equipment['shift1_end']) ? $equipment['shift1_end'] : null,
            'shift2_start'         => !empty($equipment['shift2_start']) ? $equipment['shift2_start'] : null,
            'shift2_end'           => !empty($equipment['shift2_end']) ? $equipment['shift2_end'] : null,
            'shift_hours'          => isset($equipment['shift_hours']) ? intval($equipment['shift_hours']) : 0,
            'equip_total_month'    => isset($equipment['equip_total_month']) ? intval($equipment['equip_total_month']) : 0,
            'equip_monthly_target' => isset($equipment['equip_monthly_target']) ? intval($equipment['equip_monthly_target']) : 0,
            'equip_total_contract' => isset($equipment['equip_total_contract']) ? intval($equipment['equip_total_contract']) : 0,
            'equip_price'          => isset($equipment['equip_price']) ? floatval($equipment['equip_price']) : 0,
            'equip_price_currency' => isset($equipment['equip_price_currency']) ? $equipment['equip_price_currency'] : '',
            'equip_operators'      => isset($equipment['equip_operators']) ? intval($equipment['equip_operators']) : 0,
            'equip_supervisors'    => isset($equipment['equip_supervisors']) ? intval($equipment['equip_supervisors']) : 0,
            'equip_technicians'    => isset($equipment['equip_technicians']) ? intval($equipment['equip_technicians']) : 0,
            'equip_assistants'     => isset($equipment['equip_assistants']) ? intval($equipment['equip_assistants']) : 0,
        );
    }

    // استبدالٌ كاملٌ ذرّيّ (حذف القديم + إدراج الجديد في معاملةٍ واحدة) بنطاقٍ مزدوج:
    // الشركة + العقد الأب المملوك المتحقَّق — يستبدل DELETE+INSERT الخام.
    try {
        contract_children_gate()->replaceChildren('contracts', intval($contract_id), 'contractequipments', 'contract_id', $rows, 'contract equipments save');
    } catch (\Throwable $e) {
        return false;
    }

    return true;
}

/**
 * جلب المعدات للعقد (معزولًا بالشركة عبر البوابة)
 * @param int $contract_id - معرف العقد
 * @param mysqli $conn - اتصال قاعدة البيانات (محفوظ للتوافق)
 */
function getContractEquipments($contract_id, $conn) {
    try {
        return contract_children_gate()->select('contractequipments', array(
            'where'   => array('contract_id' => intval($contract_id)),
            'orderBy' => 'id ASC',
        ));
    } catch (\Throwable $e) {
        return array();
    }
}

/**
 * حذف معدة من العقد — صلبًا عبر deleteChild (الشركة + العقد الأب المتحقَّق)
 * @param int $equipment_id - معرف سطر المعدة
 * @param mysqli $conn - اتصال قاعدة البيانات (محفوظ للتوافق)
 */
function deleteEquipment($equipment_id, $conn) {
    $equipment_id = intval($equipment_id);
    try {
        $gate = contract_children_gate();
        $row = $gate->selectOne('contractequipments', array(
            'columns' => array('id', 'contract_id'),
            'where'   => array('id' => $equipment_id),
        ));
        if ($row === null) {
            return false;
        }
        return $gate->deleteChild('contractequipments', $equipment_id, 'contracts', intval($row['contract_id']), 'contract_id', 'contract equipment delete') > 0;
    } catch (\Throwable $e) {
        return false;
    }
}
}
?>
