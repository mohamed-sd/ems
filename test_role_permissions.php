<?php
/**
 * اختبار نظام الصلاحيات - Role Permissions Test
 * صفحة اختبار شاملة ومفصلة لنظام الصلاحيات
 */

session_start();

// التحقق من الدخول
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

// فقط المسؤول الأول يمكنه الوصول
if ($_SESSION['user']['role'] != "-1") {
    die("⛔ فقط المسؤول الأول يمكنه الوصول إلى هذه الصفحة");
}

include 'config.php';
include 'includes/permissions_helper.php';

$tests_passed = 0;
$tests_failed = 0;
$warnings = [];

// ═══════════════════════════════════════════════════════════════════════════
// 1️⃣ اختبار وجود الجدول
// ═══════════════════════════════════════════════════════════════════════════

$table_test = [
    'name' => '✓ التحقق من وجود جدول role_permissions',
    'passed' => false,
    'error' => ''
];

$check_result = $conn->query("SHOW TABLES LIKE 'role_permissions'");
if ($check_result && $check_result->num_rows > 0) {
    $table_test['passed'] = true;
    $tests_passed++;
} else {
    $table_test['error'] = 'الجدول غير موجود. يجب تشغيل setup_role_permissions.php أولاً';
    $tests_failed++;
}

// ═══════════════════════════════════════════════════════════════════════════
// 2️⃣ اختبار العلاقات الخارجية
// ═══════════════════════════════════════════════════════════════════════════

$foreign_keys_test = [
    'name' => '✓ التحقق من العلاقات الخارجية',
    'passed' => false,
    'error' => ''
];

if ($table_test['passed']) {
    $fk_result = $conn->query(
        "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
         WHERE TABLE_NAME = 'role_permissions' AND REFERENCED_TABLE_NAME IS NOT NULL"
    );
    
    if ($fk_result && $fk_result->num_rows >= 2) {
        $foreign_keys_test['passed'] = true;
        $tests_passed++;
    } else {
        $foreign_keys_test['error'] = 'العلاقات الخارجية قد لا تكون معرّفة بشكل صحيح';
        $tests_failed++;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 3️⃣ اختبار وجود جدول roles
// ═══════════════════════════════════════════════════════════════════════════

$roles_test = [
    'name' => '✓ التحقق من وجود جدول roles',
    'passed' => false,
    'error' => '',
    'details' => ''
];

$roles_result = $conn->query("SELECT COUNT(*) as count FROM roles");
if ($roles_result) {
    $roles_count = $roles_result->fetch_assoc()['count'];
    if ($roles_count > 0) {
        $roles_test['passed'] = true;
        $roles_test['details'] = "عدد الأدوار: $roles_count";
        $tests_passed++;
    } else {
        $roles_test['error'] = 'لا توجد أدوار في قاعدة البيانات';
        $warnings[] = 'يجب إضافة أدوار من Settings/roles.php أولاً';
        $tests_failed++;
    }
} else {
    $roles_test['error'] = 'خطأ في الوصول إلى جدول roles';
    $tests_failed++;
}

// ═══════════════════════════════════════════════════════════════════════════
// 4️⃣ اختبار وجود جدول modules
// ═══════════════════════════════════════════════════════════════════════════

$modules_test = [
    'name' => '✓ التحقق من وجود جدول modules',
    'passed' => false,
    'error' => '',
    'details' => ''
];

$modules_result = $conn->query("SELECT COUNT(*) as count FROM modules");
if ($modules_result) {
    $modules_count = $modules_result->fetch_assoc()['count'];
    if ($modules_count > 0) {
        $modules_test['passed'] = true;
        $modules_test['details'] = "عدد الشاشات: $modules_count";
        $tests_passed++;
    } else {
        $modules_test['error'] = 'لا توجد شاشات في قاعدة البيانات';
        $warnings[] = 'يجب إضافة شاشات من Settings/modules.php أولاً';
        $tests_failed++;
    }
} else {
    $modules_test['error'] = 'خطأ في الوصول إلى جدول modules';
    $tests_failed++;
}

// ═══════════════════════════════════════════════════════════════════════════
// 5️⃣ اختبار الدوال المساعدة
// ═══════════════════════════════════════════════════════════════════════════

$helper_functions_test = [
    'name' => '✓ التحقق من الدوال المساعدة',
    'passed' => false,
    'error' => ''
];

$functions_to_check = [
    'check_permission',
    'check_view_permission',
    'check_add_permission',
    'check_edit_permission',
    'check_delete_permission',
    'get_module_permissions',
    'get_user_permissions',
    'has_any_permission',
    'has_all_permissions'
];

$missing_functions = [];
foreach ($functions_to_check as $func) {
    if (!function_exists($func)) {
        $missing_functions[] = $func;
    }
}

if (empty($missing_functions)) {
    $helper_functions_test['passed'] = true;
    $tests_passed++;
} else {
    $helper_functions_test['error'] = 'الدوال التالية مفقودة: ' . implode(', ', $missing_functions);
    $tests_failed++;
}

// ═══════════════════════════════════════════════════════════════════════════
// 6️⃣ اختبار البيانات النموذجية
// ═══════════════════════════════════════════════════════════════════════════

$sample_data_test = [
    'name' => '✓ التحقق من البيانات',
    'passed' => false,
    'error' => '',
    'details' => ''
];

if ($table_test['passed']) {
    $data_result = $conn->query("SELECT COUNT(*) as count FROM role_permissions");
    if ($data_result) {
        $data_count = $data_result->fetch_assoc()['count'];
        $sample_data_test['details'] = "عدد الصلاحيات المحفوظة: $data_count";
        
        if ($data_count > 0) {
            $sample_data_test['passed'] = true;
            $tests_passed++;
        } else {
            $sample_data_test['error'] = 'لا توجد صلاحيات محفوظة حتى الآن';
            $warnings[] = 'يجب إضافة صلاحيات من Settings/role_permissions.php';
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 7️⃣ اختبار الفهارس
// ═══════════════════════════════════════════════════════════════════════════

$indexes_test = [
    'name' => '✓ التحقق من الفهارس',
    'passed' => false,
    'error' => '',
    'details' => ''
];

if ($table_test['passed']) {
    $idx_result = $conn->query(
        "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
         WHERE TABLE_NAME = 'role_permissions' AND INDEX_NAME != 'PRIMARY'"
    );
    
    if ($idx_result) {
        $index_count = $idx_result->num_rows;
        $indexes_test['details'] = "عدد الفهارس الإضافية: $index_count";
        if ($index_count >= 2) {
            $indexes_test['passed'] = true;
            $tests_passed++;
        } else {
            $warnings[] = 'قد تكون الأداء أفضل مع المزيد من الفهارس';
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 8️⃣ اختبار الدالة check_permission
// ═══════════════════════════════════════════════════════════════════════════

$check_perm_test = [
    'name' => '✓ اختبار دالة check_permission()',
    'passed' => false,
    'error' => '',
    'details' => ''
];

if ($table_test['passed'] && $roles_test['passed'] && $modules_test['passed']) {
    try {
        // جرب مع أول دور وأول شاشة
        $first_role = $conn->query("SELECT id FROM roles LIMIT 1");
        $first_module = $conn->query("SELECT id FROM modules LIMIT 1");
        
        if ($first_role && $first_module && $first_role->num_rows > 0 && $first_module->num_rows > 0) {
            $role = $first_role->fetch_assoc();
            $module = $first_module->fetch_assoc();
            
            // اختبر الدالة بمحاكاة جلسة
            $_SESSION['user']['role'] = $role['id'];
            
            $result = check_permission($conn, $module['id'], 'view');
            
            // كان يجب أن يعيد boolean
            if (is_bool($result)) {
                $check_perm_test['passed'] = true;
                $check_perm_test['details'] = "الدالة تعمل بشكل صحيح (النتيجة: " . ($result ? "true" : "false") . ")";
                $tests_passed++;
            } else {
                $check_perm_test['error'] = 'الدالة لم تعد boolean';
                $tests_failed++;
            }
        }
    } catch (Exception $e) {
        $check_perm_test['error'] = $e->getMessage();
        $tests_failed++;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// عرض النتائج
// ═══════════════════════════════════════════════════════════════════════════

?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار نظام الصلاحيات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 900px;
            margin-top: 2rem;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1.5rem;
        }

        .test-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .test-item:last-child {
            border-bottom: none;
        }

        .test-item.passed {
            background-color: #f0f8f5;
        }

        .test-item.failed {
            background-color: #fef5f5;
        }

        .test-status {
            font-size: 1.5rem;
            min-width: 50px;
            text-align: center;
        }

        .test-details {
            flex: 1;
            margin: 0 1rem;
        }

        .test-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .test-error {
            color: #dc3545;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .test-details-text {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .summary-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .summary-card h3 {
            margin-bottom: 1rem;
        }

        .test-count {
            font-size: 3rem;
            font-weight: bold;
            color: #667eea;
        }

        .test-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-around;
            margin-top: 1.5rem;
        }

        .summary-item {
            flex: 1;
            padding: 1rem;
        }

        .summary-item.passed {
            background-color: #d4edda;
            border-radius: 5px;
            margin: 0 0.5rem;
        }

        .summary-item.failed {
            background-color: #f8d7da;
            border-radius: 5px;
            margin: 0 0.5rem;
        }

        .summary-item .number {
            font-size: 2rem;
            font-weight: bold;
            color: #155724;
        }

        .summary-item.failed .number {
            color: #721c24;
        }

        .summary-item .label {
            color: #6c757d;
            font-size: 0.85rem;
        }

        .warning-badge {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .action-buttons a,
        .action-buttons button {
            flex: 1;
            min-width: 150px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- الرأس -->
        <div class="text-center mb-4">
            <h1 class="text-white mb-3">
                <i class="fas fa-flask"></i> اختبار نظام الصلاحيات
            </h1>
        </div>

        <!-- الملخص -->
        <div class="summary-card">
            <h3>نتائج الاختبارات</h3>
            <div class="summary-row">
                <div class="summary-item passed">
                    <div class="number"><?php echo $tests_passed; ?></div>
                    <div class="label">اختبارات ناجحة</div>
                </div>
                <div class="summary-item <?php echo $tests_failed > 0 ? 'failed' : 'passed'; ?>">
                    <div class="number"><?php echo $tests_failed; ?></div>
                    <div class="label"><?php echo $tests_failed > 0 ? 'اختبارات فاشلة' : 'لا توجد أخطاء'; ?></div>
                </div>
            </div>
        </div>

        <!-- التحذيرات -->
        <?php if (!empty($warnings)): ?>
            <div class="warning-badge">
                <h6><i class="fas fa-exclamation-triangle"></i> تحذيرات</h6>
                <ul class="mb-0">
                    <?php foreach ($warnings as $warning): ?>
                        <li><?php echo $warning; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- نتائج الاختبارات -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-check-double"></i> تفاصيل الاختبارات
                </h5>
            </div>
            <div class="card-body p-0">
                <!-- الاختبار 1 -->
                <div class="test-item <?php echo $table_test['passed'] ? 'passed' : 'failed'; ?>">
                    <div class="test-status"><?php echo $table_test['passed'] ? '✅' : '❌'; ?></div>
                    <div class="test-details">
                        <div class="test-name"><?php echo $table_test['name']; ?></div>
                        <?php if ($table_test['error']): ?>
                            <div class="test-error"><?php echo $table_test['error']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- الاختبار 2 -->
                <div class="test-item <?php echo $foreign_keys_test['passed'] ? 'passed' : ($table_test['passed'] ? 'failed' : ''); ?>"
                    style="opacity: <?php echo !$table_test['passed'] ? '0.5' : '1'; ?>">
                    <div class="test-status"><?php echo $foreign_keys_test['passed'] ? '✅' : '⚠️'; ?></div>
                    <div class="test-details">
                        <div class="test-name"><?php echo $foreign_keys_test['name']; ?></div>
                        <?php if ($foreign_keys_test['error']): ?>
                            <div class="test-error"><?php echo $foreign_keys_test['error']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- الاختبار 3 -->
                <div class="test-item <?php echo $roles_test['passed'] ? 'passed' : 'failed'; ?>">
                    <div class="test-status"><?php echo $roles_test['passed'] ? '✅' : '❌'; ?></div>
                    <div class="test-details">
                        <div class="test-name"><?php echo $roles_test['name']; ?></div>
                        <?php if ($roles_test['details']): ?>
                            <div class="test-details-text"><?php echo $roles_test['details']; ?></div>
                        <?php endif; ?>
                        <?php if ($roles_test['error']): ?>
                            <div class="test-error"><?php echo $roles_test['error']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- الاختبار 4 -->
                <div class="test-item <?php echo $modules_test['passed'] ? 'passed' : 'failed'; ?>">
                    <div class="test-status"><?php echo $modules_test['passed'] ? '✅' : '❌'; ?></div>
                    <div class="test-details">
                        <div class="test-name"><?php echo $modules_test['name']; ?></div>
                        <?php if ($modules_test['details']): ?>
                            <div class="test-details-text"><?php echo $modules_test['details']; ?></div>
                        <?php endif; ?>
                        <?php if ($modules_test['error']): ?>
                            <div class="test-error"><?php echo $modules_test['error']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- الاختبار 5 -->
                <div class="test-item <?php echo $helper_functions_test['passed'] ? 'passed' : 'failed'; ?>">
                    <div class="test-status"><?php echo $helper_functions_test['passed'] ? '✅' : '❌'; ?></div>
                    <div class="test-details">
                        <div class="test-name"><?php echo $helper_functions_test['name']; ?></div>
                        <?php if ($helper_functions_test['error']): ?>
                            <div class="test-error"><?php echo $helper_functions_test['error']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- الاختبار 6 -->
                <div class="test-item <?php echo $sample_data_test['passed'] ? 'passed' : 'failed'; ?>">
                    <div class="test-status"><?php echo $sample_data_test['passed'] ? '✅' : '⚠️'; ?></div>
                    <div class="test-details">
                        <div class="test-name"><?php echo $sample_data_test['name']; ?></div>
                        <?php if ($sample_data_test['details']): ?>
                            <div class="test-details-text"><?php echo $sample_data_test['details']; ?></div>
                        <?php endif; ?>
                        <?php if ($sample_data_test['error']): ?>
                            <div class="test-error"><?php echo $sample_data_test['error']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- الاختبار 7 -->
                <div class="test-item <?php echo $indexes_test['passed'] ? 'passed' : 'failed'; ?>">
                    <div class="test-status"><?php echo $indexes_test['passed'] ? '✅' : '⚠️'; ?></div>
                    <div class="test-details">
                        <div class="test-name"><?php echo $indexes_test['name']; ?></div>
                        <?php if ($indexes_test['details']): ?>
                            <div class="test-details-text"><?php echo $indexes_test['details']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- الاختبار 8 -->
                <div class="test-item <?php echo $check_perm_test['passed'] ? 'passed' : 'failed'; ?>">
                    <div class="test-status"><?php echo $check_perm_test['passed'] ? '✅' : '⚠️'; ?></div>
                    <div class="test-details">
                        <div class="test-name"><?php echo $check_perm_test['name']; ?></div>
                        <?php if ($check_perm_test['details']): ?>
                            <div class="test-details-text"><?php echo $check_perm_test['details']; ?></div>
                        <?php endif; ?>
                        <?php if ($check_perm_test['error']): ?>
                            <div class="test-error"><?php echo $check_perm_test['error']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- الأزرار -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-link"></i> الروابط السريعة</h5>
            </div>
            <div class="card-body">
                <div class="action-buttons">
                    <a href="setup_role_permissions.php" class="btn btn-primary">
                        <i class="fas fa-hammer"></i> إعداد الجدول
                    </a>
                    <a href="Settings/role_permissions.php" class="btn btn-info">
                        <i class="fas fa-lock-open"></i> إدارة الصلاحيات
                    </a>
                    <a href="Settings/roles.php" class="btn btn-warning">
                        <i class="fas fa-user-shield"></i> إدارة الأدوار
                    </a>
                    <a href="Settings/modules.php" class="btn btn-success">
                        <i class="fas fa-layer-group"></i> إدارة الشاشات
                    </a>
                    <a href="main/dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
