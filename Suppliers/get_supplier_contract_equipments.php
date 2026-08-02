<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();

while (ob_get_level()) ob_end_clean();

if (!isset($_SESSION['user'])) {
  die(json_encode([]));
}

include '../config.php';

if (isset($_POST['contract_id']) || isset($_GET['contract_id'])) {
  $contract_id = intval(isset($_POST['contract_id']) ? $_POST['contract_id'] : $_GET['contract_id']);

  // H-20: قراءةُ معدات عقدٍ — المقيَّدُ لا يقرأ إلا عقدَ موردِه (403 JSON مسجَّلة)
  require_once __DIR__ . '/../app/Services/Portal/SupplierPortalGuard.php';
  \App\Services\Portal\SupplierPortalGuard::enforceJson(
    $conn, $_SESSION['user'],
    \App\Services\Portal\SupplierPortalGuard::supplierOfContract($conn, $contract_id) ?? 0,
    'Suppliers/get_supplier_contract_equipments.php');

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
