<?php
/**
 * تطبيق جدول الصلاحيات - Role Permissions Table Setup
 * صفحة مساعدة لإنشاء وتحديث قاعدة البيانات
 */

session_start();

// التحقق من أن المستخدم مسؤول
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "-1") {
    die("⛔ لا توجد صلاحيات كافية للوصول إلى هذه الصفحة");
}

include 'config.php';

$message = '';
$is_success = false;

// ═══════════════════════════════════════════════════════════════════════════
// تطبيق الجدول
// ═══════════════════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_table') {
    // قراءة ملف SQL
    $sql_file = __DIR__ . '/database/role_permissions.sql';
    
    if (!file_exists($sql_file)) {
        $message = "❌ ملف قاعدة البيانات غير موجود: " . $sql_file;
    } else {
        $sql_content = file_get_contents($sql_file);
        
        // تقسيم الاستعلامات
        $queries = array_filter(
            array_map('trim', explode(';', $sql_content)),
            function($q) { return !empty($q) && !preg_match('/^--/', $q); }
        );
        
        $success_count = 0;
        $error_details = '';
        
        foreach ($queries as $query) {
            // تجاهل التعليقات
            if (preg_match('/^--/', trim($query))) continue;
            
            if ($conn->query($query) === TRUE) {
                $success_count++;
            } else {
                $error_details .= "❌ " . $conn->error . "\n";
            }
        }
        
        if ($error_details === '') {
            $is_success = true;
            $message = "✅ تم إنشاء الجدول بنجاح!\n✓ تم تنفيذ " . $success_count . " استعلام";
        } else {
            $message = "⚠️ حدثت بعض الأخطاء:\n" . $error_details;
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// التحقق من وجود الجدول
// ═══════════════════════════════════════════════════════════════════════════

$table_exists = false;
$table_info = null;

$check_result = $conn->query("SHOW TABLES LIKE 'role_permissions'");
if ($check_result && $check_result->num_rows > 0) {
    $table_exists = true;
    
    // احصل على معلومات الجدول
    $info_result = $conn->query("DESCRIBE role_permissions");
    if ($info_result) {
        $table_info = [];
        while ($row = $info_result->fetch_assoc()) {
            $table_info[] = $row;
        }
    }
    
    // احصل على عدد الصلاحيات
    $count_result = $conn->query("SELECT COUNT(*) as total FROM role_permissions");
    $count_row = $count_result->fetch_assoc();
    $permissions_count = $count_row['total'];
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعداد جدول الصلاحيات</title>
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
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1.5rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: bold;
            margin: 1rem 0;
        }

        .status-badge.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-badge.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status-badge.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .table-columns {
            font-size: 0.9rem;
        }

        .table-columns th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .message-box {
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
        }

        .message-box.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-box.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }

        .feature-list {
            list-style: none;
            padding: 0;
        }

        .feature-list li {
            padding: 0.5rem 0;
            padding-right: 1.5rem;
            position: relative;
        }

        .feature-list li:before {
            content: "✓";
            position: absolute;
            right: 0;
            color: #28a745;
            font-weight: bold;
        }

        .info-section {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 5px;
            margin: 1rem 0;
            border-right: 4px solid #0d6efd;
        }

        .info-section h6 {
            color: #0d6efd;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .pages-count {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            margin: 1rem 0;
        }

        .pages-count .number {
            font-size: 2.5rem;
            font-weight: bold;
        }

        .pages-count .label {
            font-size: 1.1rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- الرأس -->
        <div class="text-center mb-4">
            <h1 class="text-white mb-3">
                <i class="fas fa-lock-open"></i> إعداد نظام الصلاحيات
            </h1>
            <p class="text-white">جدول role_permissions - ⭐ أهم جدول في النظام</p>
        </div>

        <!-- البطاقة الرئيسية -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-database"></i> حالة قاعدة البيانات
                </h5>
            </div>
            <div class="card-body">
                <!-- حالة الجدول -->
                <?php if ($table_exists): ?>
                    <div class="status-badge success">
                        <i class="fas fa-check-circle"></i> الجدول موجود ✅
                    </div>
                    
                    <div class="pages-count">
                        <div class="label">عدد الصلاحيات المحفوظة</div>
                        <div class="number"><?php echo $permissions_count; ?></div>
                    </div>

                    <div class="info-section">
                        <h6><i class="fas fa-info-circle"></i> معلومات الجدول</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-columns">
                                <thead>
                                    <tr>
                                        <th>اسم الحقل</th>
                                        <th>النوع</th>
                                        <th>الخصائص</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($table_info as $col): ?>
                                        <tr>
                                            <td><strong><?php echo $col['Field']; ?></strong></td>
                                            <td><?php echo $col['Type']; ?></td>
                                            <td>
                                                <?php
                                                $props = [];
                                                if ($col['Null'] === 'NO') $props[] = 'NOT NULL';
                                                if ($col['Key'] === 'PRI') $props[] = 'PRIMARY KEY';
                                                if ($col['Key'] === 'UNI') $props[] = 'UNIQUE';
                                                if (!empty($col['Default'])) $props[] = 'DEFAULT: ' . $col['Default'];
                                                echo implode(', ', $props) ?: '-';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="fas fa-arrow-left"></i> 
                        <a href="Settings/role_permissions.php" class="alert-link">
                            اذهب إلى صفحة إدارة الصلاحيات
                        </a>
                    </div>

                <?php else: ?>
                    <div class="status-badge error">
                        <i class="fas fa-times-circle"></i> الجدول غير موجود ❌
                    </div>

                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i>
                        يجب إنشاء الجدول قبل استخدام نظام الصلاحيات
                    </div>

                    <form method="POST" class="mt-4">
                        <input type="hidden" name="action" value="create_table">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-hammer"></i> إنشاء الجدول الآن
                        </button>
                    </form>
                <?php endif; ?>

                <!-- رسالة من العملية -->
                <?php if ($message): ?>
                    <div class="message-box <?php echo $is_success ? 'success' : 'error'; ?> mt-3">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- معلومات إضافية -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-lightbulb"></i> شرح الجدول
                </h5>
            </div>
            <div class="card-body">
                <p>جدول <strong>role_permissions</strong> يحدد بالضبط ماذا يمكن لكل دور أن يفعل على كل شاشة:</p>

                <ul class="feature-list">
                    <li><strong>👁️ can_view</strong> - يمكن للمستخدم رؤية الشاشة والبيانات</li>
                    <li><strong>➕ can_add</strong> - يمكن للمستخدم إضافة سجلات جديدة</li>
                    <li><strong>✏️ can_edit</strong> - يمكن للمستخدم تعديل السجلات الموجودة</li>
                    <li><strong>🗑️ can_delete</strong> - يمكن للمستخدم حذف السجلات</li>
                </ul>

                <div class="info-section mt-3">
                    <h6><i class="fas fa-link"></i> الارتباطات</h6>
                    <p class="mb-0">
                        يرتبط الجدول بـ:
                        <br>
                        • <strong>role_id</strong> → جدول <code>roles</code>
                        <br>
                        • <strong>module_id</strong> → جدول <code>modules</code>
                    </p>
                </div>
            </div>
        </div>

        <!-- رابط العودة -->
        <div class="text-center mt-4">
            <a href="Settings/settings.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> العودة إلى الإعدادات
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
