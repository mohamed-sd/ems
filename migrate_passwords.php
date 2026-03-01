<?php
/**
 * سكريبت تحديث كلمات المرور للنظام الجديد
 * Password Migration Script
 * 
 * هذا السكريبت يحول كلمات المرور من النص الواضح إلى password_hash
 * 
 * تحذير: يجب تشغيل هذا السكريبت مرة واحدة فقط!
 * 
 * الاستخدام:
 * 1. قم برفع هذا الملف إلى مجلد ems
 * 2. افتح المتصفح واذهب إلى: http://localhost/ems/migrate_passwords.php
 * 3. أدخل رمز الأمان (انظر أدناه)
 * 4. اضغط على "تحديث كلمات المرور"
 * 5. بعد النجاح، احذف هذا الملف!
 */

// ═══════════════════════════════════════════════════════════════════════════
// رمز الأمان - غيّر هذا الرمز قبل الاستخدام!
// ═══════════════════════════════════════════════════════════════════════════
define('MIGRATION_SECRET_CODE', 'EMS2026SecureMigration'); // غيّر هذا!

// ═══════════════════════════════════════════════════════════════════════════
// تحميل الإعدادات
// ═══════════════════════════════════════════════════════════════════════════
require_once 'config.php';

$message = '';
$success = false;

// ═══════════════════════════════════════════════════════════════════════════
// معالجة الطلب
// ═══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $input_code = $_POST['secret_code'] ?? '';
    
    if ($input_code !== MIGRATION_SECRET_CODE) {
        $message = '❌ رمز الأمان غير صحيح!';
    } else {
        
        // جلب جميع المستخدمين
        $query = "SELECT id, username, password FROM users";
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            $message = '❌ خطأ في جلب المستخدمين: ' . mysqli_error($conn);
        } else {
            
            $updated_count = 0;
            $errors = [];
            
            while ($user = mysqli_fetch_assoc($result)) {
                $id = $user['id'];
                $username = $user['username'];
                $old_password = $user['password'];
                
                // التحقق من أن كلمة المرور ليست مشفرة بالفعل
                // password_hash يبدأ بـ $2y$ أو $2a$ أو $2x$
                if (preg_match('/^\$2[axy]\$/', $old_password)) {
                    // كلمة المرور مشفرة بالفعل، تخطي
                    continue;
                }
                
                // تشفير كلمة المرور
                $hashed_password = password_hash($old_password, PASSWORD_DEFAULT);
                
                // تحديث كلمة المرور في قاعدة البيانات
                $update_query = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'si', $hashed_password, $id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $updated_count++;
                    } else {
                        $errors[] = "فشل تحديث المستخدم: $username";
                    }
                    
                    mysqli_stmt_close($stmt);
                } else {
                    $errors[] = "فشل تحضير الاستعلام للمستخدم: $username";
                }
            }
            
            if (empty($errors)) {
                $message = "✅ تم تحديث $updated_count كلمة مرور بنجاح!";
                $success = true;
            } else {
                $message = "⚠️ تم تحديث $updated_count كلمة مرور، لكن حدثت بعض الأخطاء:<br>" . implode('<br>', $errors);
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// عرض الصفحة
// ═══════════════════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث كلمات المرور - Password Migration</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Cairo', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .warning strong {
            display: block;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
        }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 16px;
        }
        .message.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .instructions {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }
        .instructions h3 {
            color: #0066cc;
            margin-bottom: 15px;
        }
        .instructions ol {
            margin-right: 20px;
        }
        .instructions li {
            margin-bottom: 10px;
            color: #333;
        }
        .code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #d63384;
        }
        .delete-warning {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }
        .delete-warning strong {
            font-size: 18px;
            display: block;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>🔐 تحديث نظام كلمات المرور</h1>
    <p class="subtitle">Password Migration to Secure Hashing</p>
    
    <?php if ($success): ?>
        <div class="message success">
            <?php echo $message; ?>
        </div>
        
        <div class="delete-warning">
            <strong>⚠️ هام جداً!</strong>
            <p>الآن يجب عليك حذف هذا الملف فوراً لأسباب أمنية!</p>
            <p style="margin-top: 10px;">احذف الملف: <span class="code">migrate_passwords.php</span></p>
        </div>
        
        <div class="instructions">
            <h3>الخطوات التالية:</h3>
            <ol>
                <li>احذف ملف <span class="code">migrate_passwords.php</span></li>
                <li>قم بتحديث ملف <span class="code">index.php</span> لاستخدام <span class="code">password_verify()</span></li>
                <li>اختبر تسجيل الدخول بجميع حسابات المستخدمين</li>
                <li>احتفظ بنسخة احتياطية من قاعدة البيانات</li>
            </ol>
        </div>
        
    <?php else: ?>
        
        <div class="warning">
            <strong>⚠️ تحذير!</strong>
            <ul style="margin-right: 20px;">
                <li>هذا السكريبت سيحول جميع كلمات المرور إلى النظام الجديد المشفر</li>
                <li>تأكد من عمل نسخة احتياطية من قاعدة البيانات أولاً</li>
                <li>يجب تشغيل هذا السكريبت مرة واحدة فقط</li>
                <li>بعد النجاح، احذف هذا الملف فوراً!</li>
            </ul>
        </div>
        
        <?php if ($message): ?>
        <div class="message error">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="secret_code">رمز الأمان (Secret Code):</label>
                <input type="password" 
                       id="secret_code" 
                       name="secret_code" 
                       placeholder="أدخل رمز الأمان" 
                       required 
                       autocomplete="off">
                <small style="color: #666; display: block; margin-top: 5px;">
                    رمز الأمان موجود في أول هذا الملف (السطر 18)
                </small>
            </div>
            
            <button type="submit">
                🔄 تحديث كلمات المرور الآن
            </button>
        </form>
        
        <div class="instructions">
            <h3>كيفية الاستخدام:</h3>
            <ol>
                <li>قم بعمل نسخة احتياطية من قاعدة البيانات</li>
                <li>افتح ملف <span class="code">migrate_passwords.php</span> وغيّر رمز الأمان في السطر 18</li>
                <li>أرفع الملف إلى مجلد <span class="code">ems</span></li>
                <li>افتح الملف في المتصفح</li>
                <li>أدخل رمز الأمان واضغط "تحديث كلمات المرور"</li>
                <li>بعد النجاح، احذف هذا الملف فوراً!</li>
            </ol>
        </div>
        
    <?php endif; ?>
</div>

</body>
</html>
