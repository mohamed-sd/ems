<?php
session_start();

while (ob_get_level()) ob_end_clean();

if (!isset($_SESSION['user'])) {
  die(json_encode([]));
}

include '../config.php';

if (isset($_POST['contract_id']) || isset($_GET['contract_id'])) {
  $contract_id = intval(isset($_POST['contract_id']) ? $_POST['contract_id'] : $_GET['contract_id']);

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
