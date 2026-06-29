<?php
session_start();

while (ob_get_level()) ob_end_clean();

if (!isset($_SESSION['user'])) {
  die(json_encode([]));
}

include '../config.php';

if (isset($_POST['contract_id']) || isset($_GET['contract_id'])) {
  $contract_id = intval(isset($_POST['contract_id']) ? $_POST['contract_id'] : $_GET['contract_id']);

  // عزل الشركة: تأكّد أن عقد المورد الأب يتبع شركة المستخدم قبل إرجاع معداته (المدير الأعلى -1 معفى).
  $_is_super = (isset($_SESSION['user']['role']) && strval($_SESSION['user']['role']) === '-1');
  $_cid = intval($_SESSION['user']['company_id'] ?? 0);
  if (!$_is_super && db_table_has_column($conn, 'supplierscontracts', 'company_id')) {
    $_own = mysqli_query($conn, "SELECT 1 FROM supplierscontracts WHERE id = $contract_id AND company_id = $_cid LIMIT 1");
    if (!$_own || !mysqli_num_rows($_own)) {
      header('Content-Type: application/json');
      die(json_encode([]));
    }
  }

  $query = "SELECT sce.*, et.type AS equipment_type_name
            FROM suppliercontractequipments sce
            LEFT JOIN equipments_types et ON sce.equip_type = et.id
            WHERE sce.contract_id = $contract_id
            ORDER BY sce.id ASC";
  $result = mysqli_query($conn, $query);

  $equipments = [];
  if ($result) { while ($row = mysqli_fetch_assoc($result)) {
    $equipments[] = $row;
  } }

  header('Content-Type: application/json');
  echo json_encode($equipments);
}
?>
