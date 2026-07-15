<?php
include '../config.php';

if (isset($_GET['type'])) {
    $type = intval($_GET['type']);
    $supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;
    $current_equipment = isset($_GET['current_equipment']) ? intval($_GET['current_equipment']) : 0;

    // العزل عبر البوابة (K9 · هجرة 2026-07-15): الأصل كان بلا فحص جلسةٍ ولا عزل —
    // البوابة fail-closed بطبيعتها (بلا جلسة/شركة = صفر صفوف)، والسوبر مسجَّل.
    $gop_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
    $gop_gate = ($gop_role === '-1') ? ems_tenant_db()->forAllTenants('getoprator super') : ems_tenant_db();

    $gop_extra = ''; $gop_params = array();

    // الشرط الأول: الآلية متاحة (status = 0 في جدول equipments)
    // الشرط الثاني: ليس لديها تشغيل ساري (لا يوجد سجل في operations بحالة 1)
    // استثناء: إذا كانت هذه هي الآلية الحالية عند التعديل، تظهر دائماً
    if ($current_equipment > 0) {
        $gop_extra .= " AND (
            (e.status = 0 AND e.id NOT IN (
                SELECT equipment FROM operations WHERE status = '1'
            ))
            OR e.id = ?
        )";
        $gop_params[] = $current_equipment;
    } else {
        $gop_extra .= " AND e.status = 0 AND e.id NOT IN (
            SELECT equipment FROM operations WHERE status = '1'
        )";
    }

    $gop_extra .= " AND e.type = ?";
    $gop_params[] = $type;
    if ($supplier_id > 0) {
        $gop_extra .= " AND e.suppliers = ?";
        $gop_params[] = $supplier_id;
    }

    try {
        $eq_rows = $gop_gate->scopedQuery(array(
            'scope'  => array('e' => 'equipments'),
            'enrich' => array('operations' => 'operations'), // قائمة الاستثناء غير مترابطة — إعلانٌ بدلالة الأصل (بلا تنطيق)
        ), "SELECT e.id, e.code, e.name FROM equipments e WHERE {TENANT_SCOPE}$gop_extra", $gop_params);
    } catch (\Throwable $t) {
        $eq_rows = array();
    }

    echo "<option value=''>-- اختر الالية --</option>";
    foreach ($eq_rows as $eq) {
        echo "<option value='" . $eq['id'] . "'>" . $eq['code'] . " - " . $eq['name'] . "</option>";
    }
}
?>
