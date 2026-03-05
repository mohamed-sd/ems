<?php
/**
 * اختبار نظام الاعتماد - فحص سريع
 * تاريخ: 2026-03-03
 */

// الاتصال بقاعدة البيانات
$conn = new mysqli('localhost', 'root', '', 'equipation_manage');

if ($conn->connect_error) {
    die("❌ فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

echo "====================================================================\n";
echo "اختبار نظام الاعتماد - فحص سريع\n";
echo "====================================================================\n\n";

$all_passed = true;

// اختبار 1: التحقق من وجود جداول نظام الموافقات
echo "📋 اختبار 1: التحقق من وجود الجداول\n";
echo str_repeat('-', 70) . "\n";

$tables = ['approval_workflow_rules', 'approval_requests', 'approval_steps'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "  ✅ جدول $table موجود\n";
    } else {
        echo "  ❌ جدول $table غير موجود\n";
        $all_passed = false;
    }
}
echo "\n";

// اختبار 2: التحقق من قواعد الموافقة
echo "📋 اختبار 2: التحقق من قواعد الموافقة\n";
echo str_repeat('-', 70) . "\n";

$expected_rules = [
    ['driver', 'activate_driver', '3,-1'],
    ['driver', 'deactivate_driver', '3,-1'],
    ['driver', 'reactivate_driver', '3,-1'],
    ['equipment', 'deactivate_equipment', '4,-1'],
    ['equipment', 'reactivate_equipment', '4,-1']
];

foreach ($expected_rules as $rule) {
    $entity = $rule[0];
    $action = $rule[1];
    $role = $rule[2];
    
    $result = $conn->query("SELECT * FROM approval_workflow_rules 
                            WHERE entity_type = '$entity' 
                            AND action = '$action' 
                            AND role_required = '$role' 
                            AND is_active = 1");
    
    if ($result && $result->num_rows > 0) {
        echo "  ✅ قاعدة $entity:$action → $role موجودة ونشطة\n";
    } else {
        echo "  ❌ قاعدة $entity:$action → $role غير موجودة أو غير نشطة\n";
        $all_passed = false;
    }
}
echo "\n";

// اختبار 3: التحقق من وجود ملفات النظام
echo "📋 اختبار 3: التحقق من وجود ملفات النظام\n";
echo str_repeat('-', 70) . "\n";

$files = [
    'includes/approval_workflow.php' => 'ملف المكتبة الأساسية',
    'Approvals/requests.php' => 'صفحة عرض الطلبات',
    'Approvals/approval_api.php' => 'API الموافقات',
    'Equipments/delete_equipment_driver.php' => 'حذف/إيقاف المشغل',
    'Equipments/save_equipment_drivers.php' => 'حفظ المشغلين',
    'Oprators/oprators.php' => 'صفحة التشغيل'
];

foreach ($files as $file => $description) {
    if (file_exists(__DIR__ . '/../' . $file)) {
        echo "  ✅ $description\n     ($file)\n";
    } else {
        echo "  ❌ $description غير موجود\n     ($file)\n";
        $all_passed = false;
    }
}
echo "\n";

// اختبار 4: التحقق من دوال المكتبة
echo "📋 اختبار 4: التحقق من دوال المكتبة\n";
echo str_repeat('-', 70) . "\n";

require_once __DIR__ . '/../includes/approval_workflow.php';

$functions = [
    'approval_create_request',
    'approval_approve_request',
    'approval_reject_request',
    'approval_get_workflow_rules',
    'approval_get_user_role',
    'approval_get_user_id',
    'approval_execute_payload',
    'approval_finalize_if_completed'
];

foreach ($functions as $function) {
    if (function_exists($function)) {
        echo "  ✅ دالة $function موجودة\n";
    } else {
        echo "  ❌ دالة $function غير موجودة\n";
        $all_passed = false;
    }
}
echo "\n";

// اختبار 5: اختبار فحص صلاحيات الأدوار
echo "📋 اختبار 5: اختبار منطق الصلاحيات\n";
echo str_repeat('-', 70) . "\n";

// اختبار approval_user_can_match_role
$tests = [
    ['3,-1', '3', true, 'role 3 يمكنه الموافقة على 3,-1'],
    ['3,-1', '-1', true, 'role -1 يمكنه الموافقة على أي شيء'],
    ['3,-1', '4', false, 'role 4 لا يمكنه الموافقة على 3,-1'],
    ['4,-1', '4', true, 'role 4 يمكنه الموافقة على 4,-1'],
];

foreach ($tests as $test) {
    list($role_required, $user_role, $expected, $description) = $test;
    $result = approval_user_can_match_role($role_required, $user_role);
    
    if ($result === $expected) {
        echo "  ✅ $description\n";
    } else {
        echo "  ❌ $description (متوقع: " . ($expected ? 'true' : 'false') . ", النتيجة: " . ($result ? 'true' : 'false') . ")\n";
        $all_passed = false;
    }
}
echo "\n";

// اختبار 6: عد الطلبات الموجودة
echo "📋 اختبار 6: إحصائيات الطلبات\n";
echo str_repeat('-', 70) . "\n";

$stats = [];
$result = $conn->query("SELECT status, COUNT(*) as count FROM approval_requests GROUP BY status");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stats[$row['status']] = $row['count'];
    }
}

$total = $conn->query("SELECT COUNT(*) as total FROM approval_requests")->fetch_assoc()['total'];

echo "  📊 إجمالي الطلبات: $total\n";
echo "     - معلقة (pending): " . ($stats['pending'] ?? 0) . "\n";
echo "     - معتمدة (approved): " . ($stats['approved'] ?? 0) . "\n";
echo "     - مرفوضة (rejected): " . ($stats['rejected'] ?? 0) . "\n";
echo "\n";

// النتيجة النهائية
echo "====================================================================\n";
if ($all_passed) {
    echo "✅ جميع الاختبارات نجحت! النظام جاهز للاستخدام\n";
} else {
    echo "⚠️ بعض الاختبارات فشلت. يرجى مراجعة الأخطاء أعلاه\n";
}
echo "====================================================================\n";

$conn->close();
?>
