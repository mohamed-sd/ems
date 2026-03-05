<?php
/**
 * ملف التثبيت التلقائي لنظام الروابط الديناميكية
 * Automatic Installation Script for Dynamic Navigation System
 * 
 * استخدام:
 * 1. انتقل إلى: http://localhost/ems/setup_dynamic_nav.php
 * 2. انقر على زر "تثبيت الآن"
 * 3. احذف هذا الملف بعد التثبيت الناجح (لأسباب أمنية)
 */

// التحقق من اتصال قاعدة البيانات
require_once __DIR__ . '/config.php';

if (!isset($conn) || $conn->connect_error) {
    die('خطأ: لا يمكن الاتصال بقاعدة البيانات.');
}

// متغير النجاح
$success = false;
$message = '';
$table_exists = false;

// التحقق من وجود جدول modules
$check_query = "SHOW TABLES LIKE 'modules'";
$check_result = mysqli_query($conn, $check_query);
$table_exists = (mysqli_num_rows($check_result) > 0);

// معالجة الطلب POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    // قراءة ملف SQL
    $sql_file = __DIR__ . '/database/create_modules_table.sql';
    
    if (!file_exists($sql_file)) {
        $message = '❌ خطأ: لم يتم العثور على ملف create_modules_table.sql';
    } else {
        // قراءة الملف
        $sql_content = file_get_contents($sql_file);
        
        // تقسيم الاستعلامات (بسيط جداً - يمكن تحسينه)
        $statements = array_filter(
            array_map('trim', explode(';', $sql_content)),
            function($s) { return strlen($s) > 0 && strpos($s, '--') !== 0; }
        );
        
        $executed = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($statements as $statement) {
            // تخطي التعليقات والأسطر الفارغة
            if (strpos(trim($statement), '--') === 0 || trim($statement) === '') {
                continue;
            }
            
            if (@mysqli_query($conn, $statement . ';')) {
                $executed++;
            } else {
                $failed++;
                $errors[] = mysqli_error($conn);
            }
        }
        
        if ($failed === 0) {
            $success = true;
            $message = "✅ تم التثبيت بنجاح! تم تنفيذ $executed استعلام (queries).";
            $table_exists = true;
        } else {
            $message = "⚠️ تم التثبيت بجزء من الأخطاء: $executed نجح، $failed فشل.<br>";
            if (!empty($errors)) {
                $message .= "<strong>الأخطاء:</strong><pre>" . htmlspecialchars(implode("\n", $errors)) . "</pre>";
            }
        }
    }
}

// عد الروابط الموجودة
$count_query = "SELECT COUNT(*) as total FROM modules";
$count_result = mysqli_query($conn, $count_query);
$count_row = mysqli_fetch_assoc($count_result);
$modules_count = $count_row['total'] ?? 0;

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تثبيت نظام الروابط الديناميكية</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .status-box {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border-right: 4px solid #667eea;
        }
        
        .status-box h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-label {
            color: #666;
            font-weight: 500;
        }
        
        .status-value {
            font-weight: bold;
            color: #667eea;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .message pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            margin-top: 10px;
            font-size: 12px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 30px;
        }
        
        form {
            display: contents;
        }
        
        button, a.button {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .btn-install {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-width: 150px;
        }
        
        .btn-install:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-install:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-refresh {
            background: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
            min-width: 150px;
        }
        
        .btn-refresh:hover {
            background: #eee;
        }
        
        .info-list {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }
        
        .info-list li {
            padding: 8px 0;
            color: #666;
            font-size: 13px;
            border-bottom: 1px solid #eee;
        }
        
        .info-list li:before {
            content: "→ ";
            color: #667eea;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .info-list li:last-child {
            border-bottom: none;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #999;
            font-size: 12px;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 نظام الروابط الديناميكية</h1>
            <p>برنامج التثبيت التلقائي</p>
        </div>

        <div class="status-box">
            <h3>📊 حالة النظام</h3>
            
            <div class="status-item">
                <span class="status-label">جدول modules</span>
                <span class="status-value">
                    <?php if ($table_exists): ?>
                        موجود <span class="badge badge-success">✓</span>
                    <?php else: ?>
                        غير موجود <span class="badge badge-danger">✗</span>
                    <?php endif; ?>
                </span>
            </div>
            
            <div class="status-item">
                <span class="status-label">عدد الروابط</span>
                <span class="status-value"><?php echo $modules_count; ?> رابط</span>
            </div>
            
            <div class="status-item">
                <span class="status-label">ملف SQL</span>
                <span class="status-value">
                    <?php if (file_exists(__DIR__ . '/database/create_modules_table.sql')): ?>
                        موجود <span class="badge badge-success">✓</span>
                    <?php else: ?>
                        غير موجود <span class="badge badge-danger">✗</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="message success">
                <?php echo htmlspecialchars($message); ?>
                <br><br>
                <strong>الخطوات التالية:</strong>
                <ul class="info-list">
                    <li>تحديث الصفحة لرؤية الروابط الجديدة في الشريط الجانبي</li>
                    <li>التأكد من أن جميع الروابط تعمل بشكل صحيح</li>
                    <li>حذف هذا الملف (setup_dynamic_nav.php) لأسباب أمنية</li>
                </ul>
            </div>
        <?php elseif ($message && !$success && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="message error">
                <?php echo $message; ?>
            </div>
        <?php elseif (!$table_exists): ?>
            <div class="message warning">
                ⚠️ جدول modules غير موجود. يجب تثبيت النظام أولاً عن طريق الزر أدناه.
            </div>
        <?php else: ?>
            <div class="message success">
                ✓ النظام مثبت بالفعل! عدد الروابط المسجلة: <strong><?php echo $modules_count; ?> رابط</strong>
            </div>
        <?php endif; ?>

        <div class="button-group">
            <?php if (!$table_exists || $modules_count === 0): ?>
                <form method="POST">
                    <button type="submit" name="install" value="1" class="btn-install">
                        📥 تثبيت الآن
                    </button>
                </form>
            <?php endif; ?>
            <button onclick="location.reload()" class="btn-refresh">🔄 تحديث</button>
        </div>

        <div class="info-list" style="margin-top: 30px; border: 1px solid #f0f0f0; padding: 15px; border-radius: 8px; background: #fafafa;">
            <h4 style="text-align: center; margin-bottom: 15px; color: #333;">ℹ️ معلومات مهمة</h4>
            <li>✓ يتم إنشاء جدول modules تلقائياً</li>
            <li>✓ يتم إضافة بيانات افتراضية لجميع الأدوار</li>
            <li>✓ يمكن تعديل الروابط من قاعدة البيانات</li>
            <li>✓ آمن تماماً - لا توجد ثغرات SQL injection</li>
            <li>⚠️ حذف هذا الملف بعد التثبيت الناجح</li>
        </div>

        <div class="footer">
            <p>Dynamic Navigation System v1.0</p>
            <p>لمزيد من المعلومات، راجع: <a href="DYNAMIC_NAVIGATION_GUIDE.md" target="_blank">DYNAMIC_NAVIGATION_GUIDE.md</a></p>
        </div>
    </div>
</body>
</html>
<?php
mysqli_close($conn);
?>
