<?php
session_start();

while (ob_get_level()) ob_end_clean();

if (!isset($_SESSION['user'])) {
  die(json_encode([]));
}

include '../config.php';

if (isset($_POST['contract_id']) || isset($_GET['contract_id'])) {
  $contract_id = intval(isset($_POST['contract_id']) ? $_POST['contract_id'] : $_GET['contract_id']);

  // عزل الشركة عبر البوابة: عقد المورد الأب يجب أن يتبع شركة المستخدم (السوبر -1 عبر forAllTenants المسجَّل)
  $_is_super = (isset($_SESSION['user']['role']) && strval($_SESSION['user']['role']) === '-1');
  $sce_gate = $_is_super ? ems_tenant_db()->forAllTenants('supplier contract equipments super') : ems_tenant_db();
  try {
    $_own = $sce_gate->selectOne('supplierscontracts', array(
      'columns' => array('id'), 'where' => array('id' => $contract_id),
    ));
  } catch (\Throwable $t) { $_own = null; }
  if (!$_own) {
    header('Content-Type: application/json');
    die(json_encode([]));
  }

  try {
    $equipments = $sce_gate->scopedQuery(array(
      'scope' => array('sce' => 'suppliercontractequipments'),
    ), "SELECT sce.*, et.type AS equipment_type_name
        FROM suppliercontractequipments sce
        LEFT JOIN equipments_types et ON sce.equip_type = et.id
        WHERE {TENANT_SCOPE} AND sce.contract_id = ?
        ORDER BY sce.id ASC", array($contract_id));
  } catch (\Throwable $t) { $equipments = []; }

  header('Content-Type: application/json');
  echo json_encode($equipments);
}
?>
