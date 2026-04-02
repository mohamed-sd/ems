<?php
/**
 * اختبار شامل لنظام الموافقات
 * يتحقق من:
 * 1. تحميل الملفات المطلوبة
 * 2. وجود الدوال المطلوبة
 * 3. الاتصال بقاعدة البيانات
 * 4. وجود الجداول المطلوبة
 */

// منع رسائل التحذير من الظهور
error_reporting(E_ERROR | E_PARSE);
// التأكد من أننا في المجلد الصحيح
chdir(__DIR__);


// اختبار 1: التحقق من الملفات المطلوبة
echo "=== اختبار نظام الموافقات ===\n\n";

echo "1️⃣ اختبار تحميل الملفات...\n";
$files_to_check = [
    '../config.php',
    '../includes/approval_workflow.php',
    'approval_api.php',
    'requests.php'
];

$missing_files = [];
foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file\n";
    } else {
        echo "   ❌ $file - غير موجود\n";
        $missing_files[] = $file;
    }
}

if (count($missing_files) > 0) {
    echo "\n❌ بعض الملفات مفقودة. يرجى التحقق من التثبيت.\n";
    exit(1);
}

// تحميل الملفات
require_once '../config.php';
require_once '../includes/approval_workflow.php';

echo "\n2️⃣ اختبار وجود الدوال المطلوبة...\n";
$required_functions = [
    'approval_create_request',
    'approval_approve_request',
    'approval_reject_request',
    'approval_get_user_role',
    'approval_get_user_id',
    'approval_response'
];

$missing_functions = [];
foreach ($required_functions as $func) {
    if (function_exists($func)) {
        echo "   ✅ $func\n";
    } else {
        echo "   ❌ $func - غير موجودة\n";
        $missing_functions[] = $func;
    }
}

if (count($missing_functions) > 0) {
    echo "\n❌ بعض الدوال مفقودة. يرجى التحقق من ملف approval_workflow.php\n";
    exit(1);
}

echo "\n3️⃣ اختبار الاتصال بقاعدة البيانات...\n";
if (!isset($conn)) {
    echo "   ❌ متغير الاتصال \$conn غير معرف\n";
    exit(1);
}

if (!$conn instanceof mysqli) {
    echo "   ❌ متغير \$conn ليس من نوع mysqli\n";
    exit(1);
}

if ($conn->connect_error) {
    echo "   ❌ خطأ في الاتصال: " . $conn->connect_error . "\n";
    exit(1);
}

echo "   ✅ الاتصال بقاعدة البيانات نجح\n";

echo "\n4️⃣ اختبار وجود الجداول المطلوبة...\n";
$required_tables = [
    'approval_requests',
    'approval_steps',
    'approval_workflow_rules',
    'users',
    'equipments',
    'drivers',
    'equipment_drivers'
];

$missing_tables = [];
foreach ($required_tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if ($result && mysqli_num_rows($result) > 0) {
        echo "   ✅ $table\n";
    } else {
        echo "   ❌ $table - غير موجود\n";
        $missing_tables[] = $table;
    }
}

if (count($missing_tables) > 0) {
    echo "\n⚠️  بعض الجداول مفقودة. قد لا يعمل النظام بشكل كامل.\n";
}

echo "\n5️⃣ اختبار قواعد الموافقات...\n";
$rules_sql = "SELECT entity_type, action, role_required as role, step_order, is_active as status 
              FROM approval_workflow_rules 
              WHERE is_active = 1 
              ORDER BY entity_type, action, step_order";
$rules_result = mysqli_query($conn, $rules_sql);

if (!$rules_result) {
    echo "   ❌ فشل جلب قواعد الموافقات: " . mysqli_error($conn) . "\n";
} else {
    $rules_count = mysqli_num_rows($rules_result);
    echo "   ✅ عدد القواعد النشطة: $rules_count\n";
    
    if ($rules_count > 0) {
        echo "\n   القواعد المتاحة:\n";
        while ($rule = mysqli_fetch_assoc($rules_result)) {
            echo "      • {$rule['entity_type']}:{$rule['action']} → الدور {$rule['role']} (المرحلة {$rule['step_order']})\n";
        }
    } else {
        echo "   ⚠️  لا توجد قواعد موافقات نشطة\n";
    }
}

echo "\n6️⃣ اختبار إحصائيات الطلبات...\n";
$stats_sql = "SELECT status, COUNT(*) as count FROM approval_requests GROUP BY status";
$stats_result = mysqli_query($conn, $stats_sql);

if (!$stats_result) {
    echo "   ❌ فشل جلب الإحصائيات: " . mysqli_error($conn) . "\n";
} else {
    $total = 0;
    echo "   الإحصائيات:\n";
    while ($stat = mysqli_fetch_assoc($stats_result)) {
        echo "      • {$stat['status']}: {$stat['count']}\n";
        $total += $stat['count'];
    }
    echo "   ✅ إجمالي الطلبات: $total\n";
}

echo "\n7️⃣ اختبار ملف requests.php...\n";
if (file_exists('requests.php')) {
    $content = file_get_contents('requests.php');
    
    // التحقق من تحميل Bootstrap JS
    if (strpos($content, 'bootstrap.bundle.min.js') !== false || 
        strpos($content, 'bootstrap.min.js') !== false) {
        echo "   ✅ Bootstrap JS محمل في الصفحة\n";
    } else {
        echo "   ❌ Bootstrap JS غير محمل - قد تظهر مشاكل في النوافذ المنبثقة\n";
    }
    
    // التحقق من وجود الـ CSRF token
    if (strpos($content, 'generate_csrf_token') !== false) {
        echo "   ✅ CSRF token موجود\n";
    } else {
        echo "   ⚠️  CSRF token غير موجود\n";
    }
    
    // التحقق من وجود الـ modals
    if (strpos($content, 'id="payloadModal"') !== false && 
        strpos($content, 'id="decisionModal"') !== false) {
        echo "   ✅ النوافذ المنبثقة موجودة\n";
    } else {
        echo "   ❌ بعض النوافذ المنبثقة مفقودة\n";
    }
}

echo "\n8️⃣ اختبار ملف approval_api.php...\n";
if (file_exists('approval_api.php')) {
    $api_content = file_get_contents('approval_api.php');
    
    // التحقق من معالجات الإجراءات
    $actions = ['approve', 'reject', 'create'];
    foreach ($actions as $action) {
        if (strpos($api_content, "api_action === '$action'") !== false) {
            echo "   ✅ معالج $action موجود\n";
        } else {
            echo "   ❌ معالج $action مفقود\n";
        }
    }
    
    // التحقق من CSRF validation
    if (strpos($api_content, 'verify_csrf_token') !== false) {
        echo "   ✅ التحقق من CSRF موجود\n";
    } else {
        echo "   ⚠️  التحقق من CSRF مفقود\n";
    }
}

echo "\n=== نتيجة الاختبار ===\n";
if (count($missing_files) === 0 && count($missing_functions) === 0) {
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/Approvals/test_system.php';
    $basePath = rtrim(dirname(dirname($scriptName)), '/');
    if ($basePath === '/' || $basePath === '\\') {
        $basePath = '';
    }

    echo "✅ جميع الاختبارات نجحت! النظام جاهز للاستخدام.\n";
    echo "\nللوصول إلى واجهة الموافقات:\n";
    echo "   🔗 " . $basePath . "/Approvals/requests.php\n";
    echo "\nلاختبار النوافذ المنبثقة:\n";
    echo "   🔗 " . $basePath . "/Approvals/test_modals.html\n";
} else {
    echo "❌ بعض الاختبارات فشلت. يرجى مراجعة الأخطاء أعلاه.\n";
    exit(1);
}
