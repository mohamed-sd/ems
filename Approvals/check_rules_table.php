<?php
error_reporting(E_ERROR | E_PARSE);
chdir(dirname(__DIR__));
require_once 'config.php';

echo "=== فحص جدول approval_workflow_rules ===\n\n";

// عرض بنية الجدول
$desc = mysqli_query($conn, "DESCRIBE approval_workflow_rules");
if ($desc) {
    echo "بنية الجدول:\n";
    while ($row = mysqli_fetch_assoc($desc)) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "فشل عرض بنية الجدول: " . mysqli_error($conn) . "\n";
    exit(1);
}

echo "\n=== محتوى الجدول (WHERE status = 1) ===\n";
$select = mysqli_query($conn, "SELECT * FROM approval_workflow_rules WHERE is_active = 1");
if ($select) {
    $count = mysqli_num_rows($select);
    echo "عدد السجلات: $count\n\n";
    
    if ($count > 0) {
        while ($row = mysqli_fetch_assoc($select)) {
            echo "ID: {$row['id']}\n";
            echo "  Entity: {$row['entity_type']}\n";
            echo "  Action: {$row['action']}\n";
            echo "  Role: " . (isset($row['role_required']) ? $row['role_required'] : 'N/A') . "\n";
            echo "  Step: " . (isset($row['step_order']) ? $row['step_order'] : 'N/A') . "\n";
            echo "  ---\n";
        }
    }
} else {
    echo "فشل جلب البيانات: " . mysqli_error($conn) . "\n";
}
