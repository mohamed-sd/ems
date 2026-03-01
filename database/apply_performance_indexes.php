<?php
require_once __DIR__ . '/../config.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    require_login();
    if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== '-1') {
        http_response_code(403);
        exit('غير مصرح لك بتنفيذ هذا الإجراء');
    }
}

function ems_index_exists($conn, $table, $indexName)
{
    $table = mysqli_real_escape_string($conn, $table);
    $indexName = mysqli_real_escape_string($conn, $indexName);
    $sql = "SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'";
    $result = mysqli_query($conn, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

function ems_create_index_if_missing($conn, $table, $indexName, $columns)
{
    if (ems_index_exists($conn, $table, $indexName)) {
        return [true, 'exists'];
    }

    $sql = "ALTER TABLE `$table` ADD INDEX `$indexName` ($columns)";
    $ok = mysqli_query($conn, $sql);

    if ($ok) {
        return [true, 'created'];
    }

    return [false, mysqli_error($conn)];
}

$indexes = [
    ['table' => 'project', 'name' => 'idx_project_status', 'cols' => '`status`'],
    ['table' => 'project', 'name' => 'idx_project_client_status', 'cols' => '`company_client_id`, `status`'],
    ['table' => 'project', 'name' => 'idx_project_create_at', 'cols' => '`create_at`'],
    ['table' => 'project', 'name' => 'idx_project_code', 'cols' => '`project_code`'],

    ['table' => 'operations', 'name' => 'idx_operations_project_status', 'cols' => '`project_id`, `status`'],
    ['table' => 'operations', 'name' => 'idx_operations_equipment_status', 'cols' => '`equipment`, `status`'],
    ['table' => 'operations', 'name' => 'idx_operations_supplier', 'cols' => '`supplier_id`'],
    ['table' => 'operations', 'name' => 'idx_operations_contract', 'cols' => '`contract_id`'],
    ['table' => 'operations', 'name' => 'idx_operations_mine', 'cols' => '`mine_id`'],

    ['table' => 'timesheet', 'name' => 'idx_timesheet_operator_status', 'cols' => '`operator`, `status`'],
    ['table' => 'timesheet', 'name' => 'idx_timesheet_driver_status', 'cols' => '`driver`, `status`'],
    ['table' => 'timesheet', 'name' => 'idx_timesheet_date', 'cols' => '`date`'],
    ['table' => 'timesheet', 'name' => 'idx_timesheet_user', 'cols' => '`user_id`'],

    ['table' => 'drivers', 'name' => 'idx_drivers_status', 'cols' => '`status`'],
    ['table' => 'drivers', 'name' => 'idx_drivers_supplier', 'cols' => '`supplier_id`'],
    ['table' => 'drivers', 'name' => 'idx_drivers_phone', 'cols' => '`phone`'],
    ['table' => 'drivers', 'name' => 'idx_drivers_code', 'cols' => '`driver_code`'],

    ['table' => 'equipments', 'name' => 'idx_equipments_status', 'cols' => '`status`'],
    ['table' => 'equipments', 'name' => 'idx_equipments_supplier', 'cols' => '`suppliers`'],
    ['table' => 'equipments', 'name' => 'idx_equipments_type', 'cols' => '`type`'],
    ['table' => 'equipments', 'name' => 'idx_equipments_code', 'cols' => '`code`'],

    ['table' => 'supplierscontracts', 'name' => 'idx_scontracts_supplier_status', 'cols' => '`supplier_id`, `status`'],
    ['table' => 'supplierscontracts', 'name' => 'idx_scontracts_project_status', 'cols' => '`project_id`, `status`'],
    ['table' => 'supplierscontracts', 'name' => 'idx_scontracts_mine', 'cols' => '`mine_id`'],

    ['table' => 'contracts', 'name' => 'idx_contracts_mine_status', 'cols' => '`mine_id`, `status`'],
    ['table' => 'contracts', 'name' => 'idx_contracts_dates', 'cols' => '`actual_start`, `actual_end`'],

    ['table' => 'mines', 'name' => 'idx_mines_project_status', 'cols' => '`project_id`, `status`'],
    ['table' => 'mines', 'name' => 'idx_mines_code', 'cols' => '`mine_code`']
];

$results = [];
foreach ($indexes as $indexDef) {
    $table = $indexDef['table'];
    $name = $indexDef['name'];
    $cols = $indexDef['cols'];

    list($ok, $message) = ems_create_index_if_missing($conn, $table, $name, $cols);
    $results[] = [
        'table' => $table,
        'index' => $name,
        'status' => $ok ? $message : 'error',
        'message' => $ok ? '' : $message
    ];
}

if ($isCli) {
    foreach ($results as $row) {
        echo '[' . strtoupper($row['status']) . '] ' . $row['table'] . ' -> ' . $row['index'];
        if (!empty($row['message'])) {
            echo ' : ' . $row['message'];
        }
        echo PHP_EOL;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحسين فهارس قاعدة البيانات</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f7f7f7; }
        .card { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background: #f0f0f0; }
        .created { color: #0a7a2f; font-weight: 700; }
        .exists { color: #0f4c81; font-weight: 700; }
        .error { color: #b42318; font-weight: 700; }
    </style>
</head>
<body>
    <div class="card">
        <h2>نتيجة تحسين فهارس قاعدة البيانات</h2>
        <table>
            <thead>
                <tr>
                    <th>الجدول</th>
                    <th>الفهرس</th>
                    <th>الحالة</th>
                    <th>رسالة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                <tr>
                    <td><?php echo e($row['table']); ?></td>
                    <td><?php echo e($row['index']); ?></td>
                    <td class="<?php echo e($row['status']); ?>"><?php echo e($row['status']); ?></td>
                    <td><?php echo e($row['message']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
