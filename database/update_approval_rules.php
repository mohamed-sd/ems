<?php
/**
 * تحديث قواعد الموافقة - إضافة قواعد تشغيل وإيقاف المشغلين والآليات
 * تاريخ: 2026-03-03
 */

// الاتصال بقاعدة البيانات
$conn = new mysqli('localhost', 'root', '', 'equipation_manage');

if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

echo "====================================================================\n";
echo "تحديث قواعد الموافقة - نظام الاعتماد\n";
echo "====================================================================\n\n";

// قواعد الموافقة
$rules = [
    ['driver', 'activate_driver', '3,-1', 'تشغيل مشغل جديد'],
    ['driver', 'deactivate_driver', '3,-1', 'إيقاف مشغل'],
    ['driver', 'reactivate_driver', '3,-1', 'إعادة تشغيل مشغل'],
    ['equipment', 'deactivate_equipment', '4,-1', 'إيقاف آلية'],
    ['equipment', 'reactivate_equipment', '4,-1', 'إعادة تشغيل آلية']
];

$added = 0;
$existing = 0;

foreach ($rules as $rule) {
    $entity_type = $rule[0];
    $action = $rule[1];
    $role_required = $rule[2];
    $description = $rule[3];
    
    // التحقق من وجود القاعدة
    $check_sql = "SELECT id FROM approval_workflow_rules 
                  WHERE entity_type = '$entity_type' AND action = '$action'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        // تحديث القاعدة الموجودة
        $update_sql = "UPDATE approval_workflow_rules 
                       SET role_required = '$role_required', is_active = 1, created_at = NOW()
                       WHERE entity_type = '$entity_type' AND action = '$action'";
        $conn->query($update_sql);
        echo "✓ تم تحديث: $description ($entity_type:$action)\n";
        $existing++;
    } else {
        // إضافة قاعدة جديدة
        $insert_sql = "INSERT INTO approval_workflow_rules (entity_type, action, role_required, step_order, is_active, created_at) 
                       VALUES ('$entity_type', '$action', '$role_required', 1, 1, NOW())";
        if ($conn->query($insert_sql)) {
            echo "✓ تم إضافة: $description ($entity_type:$action)\n";
            $added++;
        } else {
            echo "✗ فشل إضافة: $description - " . $conn->error . "\n";
        }
    }
}

echo "\n====================================================================\n";
echo "النتيجة: تم إضافة $added قاعدة جديدة، تحديث $existing قاعدة موجودة\n";
echo "====================================================================\n\n";

// عرض جميع القواعد
echo "جميع قواعد الموافقة المسجلة:\n";
echo "--------------------------------------------------------------------\n";

$result = $conn->query("SELECT * FROM approval_workflow_rules 
                        WHERE entity_type IN ('driver', 'equipment') 
                        ORDER BY entity_type, action");

if ($result && $result->num_rows > 0) {
    printf("%-15s | %-25s | %-15s | %-6s\n", 'النوع', 'الإجراء', 'الأدوار المطلوبة', 'نشط');
    echo str_repeat('-', 70) . "\n";
    
    while ($row = $result->fetch_assoc()) {
        $entity_ar = $row['entity_type'] == 'driver' ? 'مشغل' : 'آلية';
        $active = $row['is_active'] ? 'نعم' : 'لا';
        printf("%-15s | %-25s | %-15s | %-6s\n", 
            $entity_ar, 
            $row['action'], 
            $row['role_required'], 
            $active
        );
    }
    echo str_repeat('-', 70) . "\n";
    echo "الإجمالي: " . $result->num_rows . " قاعدة\n";
} else {
    echo "لا توجد قواعد موافقة في الجدول\n";
}

echo "\n====================================================================\n";
echo "✅ تم الانتهاء من تحديث قواعد الموافقة\n";
echo "====================================================================\n";

$conn->close();
?>
