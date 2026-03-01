<?php
/**
 * ملف الإعدادات الرئيسي - EMS Configuration
 * @version 2.0 - Enhanced Security
 * @date 2026-03-01
 */

// ═══════════════════════════════════════════════════════════════════════════
// 1. تحميل نظام الأمان المركزي
// ═══════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/performance.php';

ems_performance_bootstrap();

// ═══════════════════════════════════════════════════════════════════════════
// 2. إعدادات PHP الأمنية
// ═══════════════════════════════════════════════════════════════════════════

// إخفاء معلومات PHP من الهيدر (لمنع كشف الإصدار)
if (!headers_sent()) {
    header_remove('X-Powered-By');
}

// تعطيل عرض الأخطاء في الإنتاج (Production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// تسجيل الأخطاء في ملف log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// حماية من file inclusion attacks
ini_set('allow_url_fopen', 0);
ini_set('allow_url_include', 0);

// ═══════════════════════════════════════════════════════════════════════════
// 3. إعدادات قاعدة البيانات
// ═══════════════════════════════════════════════════════════════════════════

$host = "localhost";
$user = "root";
$pass = "";
$db   = "equipation_manage";

// Establish Secure Connection
$conn = new mysqli($host, $user, $pass, $db);

// Check Connection
if ($conn->connect_error) {
    // تسجيل الخطأ
    error_log("Database Connection Failed: " . $conn->connect_error);
    
    // عرض رسالة عامة للمستخدم (بدون كشف تفاصيل فنية)
    die('
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>خطأ في الاتصال</title>
            <style>
                body { font-family: Cairo, Arial; text-align: center; padding: 50px; background: #f5f5f5; }
                .error { background: white; padding: 40px; border-radius: 10px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #dc2626; }
            </style>
        </head>
        <body>
            <div class="error">
                <h1>⚠️ خطأ في الاتصال</h1>
                <p>عذراً، حدث خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.</p>
            </div>
        </body>
        </html>
    ');
}

// تعيين charset لمنع SQL Injection عبر encoding
$conn->set_charset("utf8mb4");

// تهيئة إعدادات أداء اتصال قاعدة البيانات
ems_optimize_db_session($conn);

// ═══════════════════════════════════════════════════════════════════════════
// 4. Global Security Functions Shortcuts
// ═══════════════════════════════════════════════════════════════════════════

/**
 * اختصار لتنظيف المدخلات من قاعدة البيانات
 */
function escape($data) {
    global $conn;
    return db_escape($conn, $data);
}

/**
 * اختصار لتنفيذ استعلام آمن
 */
function query_safe($query, $params = [], $types = '') {
    global $conn;
    return db_query($conn, $query, $params, $types);
}

// ═══════════════════════════════════════════════════════════════════════════
// END OF CONFIG
// ═══════════════════════════════════════════════════════════════════════════
?>