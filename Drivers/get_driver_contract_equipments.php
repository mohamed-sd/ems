<?php
session_start();
if (!isset($_SESSION['user'])) {
  die(json_encode([]));
}

include '../config.php';

if (isset($_POST['contract_id'])) {
  $contract_id = intval($_POST['contract_id']);
  
  $query = "SELECT * FROM drivercontractequipments WHERE contract_id = $contract_id ORDER BY id ASC";
  $result = mysqli_query($conn, $query);
  
  $equipments = [];
  while ($row = mysqli_fetch_assoc($result)) {
    $equipments[] = $row;
  }
  
  header('Content-Type: application/json');
  echo json_encode($equipments);
}
?>
