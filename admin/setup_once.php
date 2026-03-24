<?php
/**
 * سكريبت إنشاء/تحديث حساب المدير الأعلى - للاستخدام مرة واحدة فقط
 * احذف هذا الملف فور الانتهاء منه
 * ONE-TIME SETUP SCRIPT - DELETE AFTER USE
 */

// IMPORTANT: Keep this script disabled in normal operation.
$ENABLE_SETUP_SCRIPT = false;

if ($ENABLE_SETUP_SCRIPT !== true) {
    http_response_code(403);
    die('Setup script is disabled for security. Set $ENABLE_SETUP_SCRIPT = true only for a one-time local setup.');
}

$remoteIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
if (!in_array($remoteIp, array('127.0.0.1', '::1'), true)) {
    http_response_code(403);
    die('Access denied. This setup script can only be executed from localhost.');
}

// ======================== إعداد ========================
// ضع كلمة المرور الجديدة هنا:
$NEW_PASSWORD = ''; // <--- ضع كلمة المرور هنا ثم احذف الملف بعد التنفيذ

$ADMIN_NAME  = 'المدير الأعلى';
$ADMIN_EMAIL = 'change-me@example.com';
// =======================================================

// منع التشغيل في بيئة الإنتاج إذا كان هناك مدير بالفعل
$ALLOW_UPDATE = true; // true = تحديث كلمة المرور إذا كان الحساب موجودًا

require_once dirname(__DIR__) . '/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<title>إعداد حساب المدير</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
  .box { background: #fff; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
  .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
  .error   { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
  .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
  .info    { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 15px 0; }
  h2 { color: #333; }
  code { background: #eee; padding: 3px 6px; border-radius: 3px; font-size: 14px; }
  .btn { display:inline-block; padding:10px 20px; background:#dc3545; color:#fff; text-decoration:none; border-radius:5px; margin-top:15px; }
</style>
</head>
<body>
<div class="box">
<h2>⚙️ إعداد حساب المدير الأعلى</h2>

<?php

// --- الخطوة 1: التحقق من كلمة المرور ---
if (empty($NEW_PASSWORD)) {
    echo '<div class="error"><strong>خطأ:</strong> لم تحدد كلمة المرور!<br>افتح الملف <code>admin/setup_once.php</code> وضع كلمة المرور في المتغير <code>$NEW_PASSWORD</code> ثم أعد تحميل الصفحة.</div>';
    echo '</div></body></html>';
    exit;
}

if (strlen($NEW_PASSWORD) < 8) {
    echo '<div class="error"><strong>خطأ:</strong> كلمة المرور يجب أن تكون 8 أحرف على الأقل.</div>';
    echo '</div></body></html>';
    exit;
}

// --- الخطوة 2: إنشاء الجداول إن لم تكن موجودة ---
$sql_create_admins = "CREATE TABLE IF NOT EXISTS super_admins (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_super_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$sql_create_resets = "CREATE TABLE IF NOT EXISTS super_admin_password_resets (
    id INT NOT NULL AUTO_INCREMENT,
    super_admin_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_super_admin_password_resets_token_hash (token_hash),
    KEY idx_super_admin_password_resets_admin_id (super_admin_id),
    CONSTRAINT fk_super_admin_password_resets_admin
        FOREIGN KEY (super_admin_id) REFERENCES super_admins(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!mysqli_query($conn, $sql_create_admins)) {
    echo '<div class="error"><strong>خطأ في إنشاء جدول super_admins:</strong> ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
    echo '</div></body></html>';
    exit;
}

if (!mysqli_query($conn, $sql_create_resets)) {
    echo '<div class="error"><strong>خطأ في إنشاء جدول super_admin_password_resets:</strong> ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
    echo '</div></body></html>';
    exit;
}

echo '<div class="info">✅ الجداول جاهزة في قاعدة البيانات.</div>';

// --- الخطوة 3: تشفير كلمة المرور ---
$hashed_password = password_hash($NEW_PASSWORD, PASSWORD_BCRYPT);

// --- الخطوة 4: التحقق من وجود الحساب ---
$email_escaped = mysqli_real_escape_string($conn, $ADMIN_EMAIL);
$check = mysqli_query($conn, "SELECT id FROM super_admins WHERE email = '$email_escaped'");
$existing = $check ? mysqli_fetch_assoc($check) : null;

if ($existing) {
    if (!$ALLOW_UPDATE) {
        echo '<div class="warning"><strong>تنبيه:</strong> الحساب موجود بالفعل ولم يتم التحديث. إذا أردت تحديث كلمة المرور، غير المتغير <code>$ALLOW_UPDATE</code> إلى <code>true</code>.</div>';
        echo '</div></body></html>';
        exit;
    }

    $id = intval($existing['id']);
    $hashed_escaped = mysqli_real_escape_string($conn, $hashed_password);
    $update = mysqli_query($conn, "UPDATE super_admins SET password = '$hashed_escaped', is_active = 1, updated_at = NOW() WHERE id = $id");

    if ($update) {
        echo '<div class="success"><strong>✅ تم تحديث كلمة المرور بنجاح!</strong><br>';
        echo 'البريد الإلكتروني: <code>' . htmlspecialchars($ADMIN_EMAIL) . '</code><br>';
        echo 'يمكنك الآن <a href="login">تسجيل الدخول</a></div>';
    } else {
        echo '<div class="error"><strong>خطأ في تحديث كلمة المرور:</strong> ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
    }

} else {
    // إنشاء حساب جديد
    $name_escaped    = mysqli_real_escape_string($conn, $ADMIN_NAME);
    $hashed_escaped  = mysqli_real_escape_string($conn, $hashed_password);

    $insert = mysqli_query($conn, "INSERT INTO super_admins (name, email, password, is_active) VALUES ('$name_escaped', '$email_escaped', '$hashed_escaped', 1)");

    if ($insert) {
        echo '<div class="success"><strong>✅ تم إنشاء الحساب بنجاح!</strong><br>';
        echo 'الاسم: <code>' . htmlspecialchars($ADMIN_NAME) . '</code><br>';
        echo 'البريد الإلكتروني: <code>' . htmlspecialchars($ADMIN_EMAIL) . '</code><br>';
        echo 'يمكنك الآن <a href="login">تسجيل الدخول</a></div>';
    } else {
        echo '<div class="error"><strong>خطأ في إنشاء الحساب:</strong> ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
    }
}

?>

<div class="warning">
  <strong>⚠️ تحذير أمني:</strong> احذف هذا الملف فورًا بعد الانتهاء!<br>
  المسار: <code>admin/setup_once.php</code>
</div>

</div>
</body>
</html>
