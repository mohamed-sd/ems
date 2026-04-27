<?php
include '../config.php';

if (isset($_GET['type'])) {
    $type = intval($_GET['type']);
    $supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;
    $current_equipment = isset($_GET['current_equipment']) ? intval($_GET['current_equipment']) : 0;
    $supplier_filter = $supplier_id > 0 ? " AND suppliers = $supplier_id" : "";

    // الشرط الأول: الآلية متاحة (status = 0 في جدول equipments)
    // الشرط الثاني: ليس لديها تشغيل ساري (لا يوجد سجل في operations بحالة 1)
    // استثناء: إذا كانت هذه هي الآلية الحالية عند التعديل، تظهر دائماً
    if ($current_equipment > 0) {
        $where = "(
            (e.status = 0 AND e.id NOT IN (
                SELECT equipment FROM operations WHERE status = '1'
            ))
            OR e.id = $current_equipment
        )";
    } else {
        $where = "e.status = 0 AND e.id NOT IN (
            SELECT equipment FROM operations WHERE status = '1'
        )";
    }

    $result = mysqli_query(
        $conn,
        "SELECT e.id, e.code, e.name FROM equipments e WHERE $where AND e.type = $type$supplier_filter"
    );

    echo "<option value=''>-- اختر الالية --</option>";
    while ($eq = mysqli_fetch_assoc($result)) {
        echo "<option value='" . $eq['id'] . "'>" . $eq['code'] . " - " . $eq['name'] . "</option>";
    }
}
?>
