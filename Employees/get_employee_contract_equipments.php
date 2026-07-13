<?php
session_start();

while (ob_get_level()) ob_end_clean();

if (!isset($_SESSION['user'])) {
  die(json_encode([]));
}

include '../config.php';

if (isset($_POST['contract_id'])) {
  $contract_id = intval($_POST['contract_id']);

  // معزولةً عبر البوابة (كانت بلا عزل شركةٍ — تسرّبٌ كامنٌ أُغلق)
  $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
  $gate = ($role === '-1') ? ems_tenant_db()->forAllTenants('dce super') : ems_tenant_db();
  try {
    $equipments = $gate->select('drivercontractequipments', array(
      'where'   => array('contract_id' => $contract_id),
      'orderBy' => 'id ASC',
    ));
  } catch (\Throwable $t) {
    $equipments = [];
  }

  header('Content-Type: application/json');
  echo json_encode($equipments);
}
?>
