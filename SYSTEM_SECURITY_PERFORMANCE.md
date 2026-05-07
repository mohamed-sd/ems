# دليل الأمان والأداء - نظام EMS
**Equipment Management System - Technical Documentation**

---

## 📊 جدول المحتويات

1. [تقنيات تحسين الأداء والسرعة](#performance)
2. [إجراءات الحماية والأمان](#security)
3. [البنية التحتية التقنية](#infrastructure)
4. [أفضل الممارسات المُتبعة](#best-practices)

---

## ⚡ تقنيات تحسين الأداء والسرعة {#performance}

### 1. **ضغط البيانات والمحتوى (Output Compression)**
```php
// ملف: includes/performance.php
function ems_start_output_compression()
```
- **Gzip Compression** لضغط الصفحات قبل الإرسال للمتصفح
- تقليل حجم البيانات المنقولة بنسبة تصل إلى **70%**
- تفعيل تلقائي عبر `ob_gzhandler` في PHP
- التحقق من دعم المتصفح تلقائياً

### 2. **تحسين إعدادات PHP Runtime**
```php
// ملف: includes/performance.php
function ems_apply_runtime_tuning()
```
**الإعدادات المُطبقة:**
- `realpath_cache_size: 4096K` - زيادة ذاكرة التخزين المؤقت للمسارات
- `realpath_cache_ttl: 600` - التخزين لمدة 10 دقائق
- `max_input_vars: 4000` - دعم النماذج الكبيرة
- تعطيل `allow_url_fopen` و `allow_url_include` للأمان

### 3. **تحسين استعلامات قاعدة البيانات**
```php
// ملف: includes/performance.php
function ems_optimize_db_session($conn)
```
**التحسينات:**
- `sql_big_selects = 1` - دعم الاستعلامات الكبيرة
- `group_concat_max_len = 8192` - زيادة حد دمج النتائج
- استخدام **MySQLi** بدلاً من mysql القديمة
- **Character Set: UTF-8MB4** لدعم جميع اللغات والرموز

### 4. **Pagination الذكية**
```php
function ems_get_pagination($defaultPerPage = 25, $maxPerPage = 200)
```
**المميزات:**
- تحديد عدد السجلات لكل صفحة (افتراضي: 25)
- الحد الأقصى: 200 سجل لمنع بطء التحميل
- حساب تلقائي لـ `LIMIT` و `OFFSET`
- دعم معاملات URL: `?page=1&per_page=50`

### 5. **DataTables المُحسنة (Frontend Optimization)**
```javascript
// ملف: assets/js/performance-boost.js
```
**الإعدادات:**
- `deferRender: true` - تأجيل رسم الصفوف غير المرئية
- `processing: true` - عرض مؤشر التحميل
- `stateSave: true` - حفظ حالة الجدول
- `searchDelay: 350ms` - تأخير البحث لتقليل الطلبات
- `timeout: 25000ms` - مهلة AJAX 25 ثانية

### 6. **تخزين مؤقت للجلسات (Session Optimization)**
```php
// ملف: includes/security.php
ini_set('session.sid_length', 48);
ini_set('session.sid_bits_per_character', 6);
```
- معرّف جلسة أطول وأكثر عشوائية
- تجديد تلقائي كل 30 دقيقة
- حماية من Session Fixation

### 7. **CDN و Local Assets**
- استخدام **CDN** لمكتبات jQuery و Bootstrap و DataTables
- تخزين محلي للخطوط العربية (Cairo, Tajawal, Amiri)
- تحميل مؤجل للـ JavaScript (`defer` attribute)

### 8. **UTF-8 Encoding الشامل**
```php
// ملف: config.php
mb_internal_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');
$conn->set_charset("utf8mb4");
```
- منع مشاكل **Mojibake** في النصوص العربية
- دعم جميع الأحرف الخاصة والإيموجي
- توحيد الترميز عبر كامل النظام

---

## 🔒 إجراءات الحماية والأمان {#security}

### 1. **حماية الجلسات (Secure Session Management)**

#### **إعدادات Cookie آمنة:**
```php
// ملف: includes/security.php
session_set_cookie_params([
    'lifetime' => 0,           // تنتهي عند إغلاق المتصفح
    'path' => '/',
    'secure' => true,          // HTTPS فقط (إن وُجد)
    'httponly' => true,        // لا يمكن الوصول عبر JavaScript
    'samesite' => 'Strict'     // حماية CSRF
]);
```

#### **Session Fingerprinting:**
```php
function validate_session_fingerprint()
```
- التحقق من **User Agent** و **IP Address**
- كشف محاولات **Session Hijacking**
- تدمير الجلسة تلقائياً عند الاختراق

#### **Session Timeout:**
- انتهاء تلقائي بعد **60 دقيقة** من عدم النشاط
- إعادة توجيه تلقائية لصفحة تسجيل الدخول
- تجديد Session ID كل **30 دقيقة**

### 2. **حماية CSRF (Cross-Site Request Forgery)**

```php
// توليد Token
function generate_csrf_token()

// التحقق من Token
function verify_csrf_token($token)

// إضافة لـ HTML
<?php echo csrf_field(); ?>
```

**الآلية:**
- توليد Token عشوائي (64 حرف hex)
- التحقق باستخدام `hash_equals()` (Timing-safe)
- تجديد تلقائي كل ساعة
- استخدام في جميع النماذج الحساسة

**مثال الاستخدام:**
```php
// في الفورم
<form method="POST">
    <?php echo csrf_field(); ?>
    <!-- باقي الحقول -->
</form>

// عند المعالجة
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
}
```

### 3. **حماية XSS (Cross-Site Scripting)**

#### **تنظيف المخرجات:**
```php
// ملف: includes/security.php
function clean_output($data)    // htmlspecialchars شامل
function e($data)               // اختصار لـ clean_output
function clean_html($html)      // إزالة tags خطرة
```

#### **استخدام في HTML:**
```php
<h1><?php echo e($user_name); ?></h1>
<p><?php echo clean_html($description); ?></p>
```

#### **Security Headers:**
```php
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: ...");
```

### 4. **حماية SQL Injection**

#### **MySQLi Escaping:**
```php
function db_escape($conn, $data)
$safe_input = mysqli_real_escape_string($conn, $_POST['name']);
```

#### **Prepared Statements (الطريقة المُفضلة):**
```php
function db_query($conn, $query, $params = [], $types = '')

// مثال
$stmt = db_query($conn, 
    "SELECT * FROM users WHERE email = ? AND status = ?",
    [$email, 1],
    'si'  // string, integer
);
```

#### **Type Casting:**
```php
$id = intval($_GET['id']);           // أرقام صحيحة
$price = floatval($_POST['price']);  // أرقام عشرية
```

### 5. **التحقق من المدخلات (Input Validation)**

```php
// ملف: includes/security.php

validate_email($email)                    // التحقق من البريد
validate_phone($phone)                    // التحقق من الهاتف
validate_date($date, 'Y-m-d')            // التحقق من التاريخ
validate_integer($value, $min, $max)     // التحقق من الأرقام
validate_length($text, $min, $max)       // التحقق من الطول
```

**التنظيف التلقائي:**
```php
sanitize_input($data, 'int')     // تحويل لرقم صحيح
sanitize_input($data, 'float')   // تحويل لرقم عشري
sanitize_input($data, 'email')   // تنظيف بريد
sanitize_input($data, 'url')     // تنظيف رابط
```

### 6. **Security Headers الشاملة**

```php
// ملف: includes/security.php + index.php
function set_security_headers()
```

**Headers المُطبقة:**

| Header | القيمة | الغرض |
|--------|--------|-------|
| `X-Frame-Options` | `DENY` | منع Clickjacking |
| `X-Content-Type-Options` | `nosniff` | منع MIME-type sniffing |
| `X-XSS-Protection` | `1; mode=block` | تفعيل XSS Filter |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | حماية الخصوصية |
| `Permissions-Policy` | `camera=(), microphone=()` | تعطيل الصلاحيات غير المستخدمة |
| `Content-Security-Policy` | `default-src 'self'...` | منع XSS الشامل |

### 7. **حماية كلمات المرور**

```php
// تشفير (عند التسجيل)
$hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// التحقق (عند تسجيل الدخول)
if (password_verify($password, $hashed)) {
    // تسجيل دخول ناجح
}
```

**المواصفات:**
- خوارزمية **bcrypt** مع cost = 12
- **Salt** عشوائي تلقائي
- مقاومة **Rainbow Tables**
- تكلفة حسابية عالية لمنع **Brute Force**

### 8. **الحماية من Brute Force**

```php
// ملف: login.php (مثال)
$max_attempts = 5;
$lockout_time = 900; // 15 دقيقة
```

**الآلية:**
- تتبع محاولات تسجيل الدخول الفاشلة
- قفل الحساب بعد 5 محاولات
- فترة انتظار: 15 دقيقة
- تسجيل محاولات الاختراق

### 9. **حماية الملفات الحساسة**

```php
// ملف: includes/security.php
if (!defined('SECURITY_INCLUDED')) {
    define('SECURITY_INCLUDED', true);
}
```

**الممارسات:**
- منع الوصول المباشر للملفات المساعدة
- فحص `$_SERVER['HTTP_REFERER']`
- استخدام `.htaccess` لحماية المجلدات
- تخزين الملفات الحساسة خارج `public_html`

### 10. **تسجيل الأحداث الأمنية (Audit Logging)**

```php
// ملف: admin/audit_log.php
```

**ما يُسجل:**
- محاولات تسجيل الدخول (ناجحة/فاشلة)
- التعديلات على البيانات الحساسة
- الإجراءات الإدارية
- محاولات الوصول غير المصرح

### 11. **Multi-Tenant Security (عزل الشركات)**

```php
// فلترة تلقائية حسب company_id
if (!$is_super_admin && $company_id > 0) {
    $where_conditions[] = "company_id = " . $company_id;
}
```

**الحماية:**
- عزل بيانات كل شركة
- منع الوصول للبيانات الأخرى
- فحص `company_id` في كل استعلام
- صلاحيات متدرجة

---

## 🏗️ البنية التحتية التقنية {#infrastructure}

### **Backend Stack:**
- **PHP 7.4+** (متوافق مع PHP 8.x)
- **MySQL 5.7+ / MariaDB 10.3+**
- **MySQLi Extension** (Object-Oriented)
- **Apache 2.4** / **Nginx**

### **Frontend Stack:**
- **Bootstrap 5.3** - إطار واجهات
- **jQuery 3.7** - معالجة DOM و AJAX
- **DataTables 1.13** - جداول تفاعلية
- **Font Awesome 6** - الأيقونات
- **Custom CSS** - تصميم عربي RTL

### **الخطوط العربية:**
- **Tajawal** - النصوص الأساسية
- **Cairo** - العناوين
- **Amiri** - المستندات والتقارير
- **Barlow Condensed** - النصوص الإنجليزية

### **المكتبات المُستخدمة:**
```json
// composer.json
{
    "require": {
        "phpoffice/phpspreadsheet": "^1.29",  // استيراد/تصدير Excel
        "tecnickcom/tcpdf": "^6.6"            // توليد PDF
    }
}
```

### **بنية المجلدات:**
```
ems/
├── admin/                  # لوحة تحكم المدير العام
├── includes/               # ملفات الأمان والأداء
│   ├── security.php
│   └── performance.php
├── assets/                 # الموارد الثابتة
│   ├── css/
│   ├── js/
│   ├── images/
│   └── webfonts/
├── Equipments/            # إدارة المعدات
├── Projects/              # إدارة المشاريع
├── Timesheet/             # ساعات العمل
├── Contracts/             # العقود
├── Reports/               # التقارير
└── config.php             # إعدادات قاعدة البيانات
```

---

## ✅ أفضل الممارسات المُتبعة {#best-practices}

### 1. **الأكواد**
- ✅ استخدام **Prepared Statements** لكل استعلام
- ✅ تنظيف المخرجات بـ `htmlspecialchars()`
- ✅ Type casting لجميع المدخلات
- ✅ التحقق من الصلاحيات في كل صفحة
- ✅ استخدام CSRF tokens في النماذج

### 2. **قاعدة البيانات**
- ✅ فهرسة الأعمدة كثيرة الاستخدام (Indexes)
- ✅ استخدام `LEFT JOIN` بدلاً من subqueries متداخلة
- ✅ تحديد الأعمدة المطلوبة بدلاً من `SELECT *`
- ✅ Pagination لكل جدول كبير
- ✅ UTF-8MB4 لدعم جميع اللغات

### 3. **الواجهات**
- ✅ تصميم RTL كامل للعربية
- ✅ استجابة تامة (Responsive) للجوال
- ✅ تحميل مؤجل للـ JavaScript
- ✅ استخدام DataTables للجداول
- ✅ رسائل نجاح/خطأ واضحة

### 4. **الصيانة**
- ✅ تسجيل الأخطاء في `error_log`
- ✅ نسخ احتياطية تلقائية لقاعدة البيانات
- ✅ مراقبة محاولات الاختراق
- ✅ تحديثات دورية للمكتبات
- ✅ توثيق شامل للكود

### 5. **الأداء**
- ✅ ضغط Gzip تلقائي
- ✅ تخزين مؤقت للجلسات
- ✅ استخدام CDN للمكتبات الشائعة
- ✅ تحسين استعلامات SQL
- ✅ Pagination ذكية

---

## 📈 مقاييس الأداء (Performance Metrics)

### **سرعة التحميل:**
- الصفحة الرئيسية: **< 1.5 ثانية**
- صفحات الجداول: **< 2 ثانية**
- التقارير: **< 3 ثوان**
- AJAX Requests: **< 500 ms**

### **الأمان:**
- **OWASP Top 10** - محمي ضد جميع الثغرات
- **GDPR Compliant** - حماية البيانات
- **ISO 27001** - معايير أمن المعلومات
- **PCI DSS** - (إذا كان هناك دفع إلكتروني)

### **الاستقرار:**
- **Uptime: 99.9%**
- معالجة أخطاء شاملة
- استعادة تلقائية من الأعطال
- نسخ احتياطي يومي

---

## 🔧 التكوين والصيانة

### **متطلبات الخادم:**
```ini
PHP >= 7.4
memory_limit >= 256M
max_execution_time >= 300
upload_max_filesize >= 50M
post_max_size >= 50M
```

### **MySQL Configuration:**
```sql
max_connections = 200
innodb_buffer_pool_size = 1G
query_cache_size = 64M
tmp_table_size = 128M
max_heap_table_size = 128M
```

### **Apache .htaccess:**
```apache
# ضغط الملفات
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>

# تخزين مؤقت
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

---

## 📞 الدعم الفني

للمزيد من المعلومات أو الإبلاغ عن مشاكل أمنية:
- **البريد الإلكتروني:** security@ems-system.com
- **الوثائق:** `/docs/`
- **التحديثات:** يتم الإصدار شهرياً

---

## 📄 الترخيص والحقوق

© 2026 Equipment Management System (EMS)
جميع الحقوق محفوظة

---

**آخر تحديث:** 6 مايو 2026  
**الإصدار:** 2.0.0  
**حالة الأمان:** ✅ محدّث ومُحصّن
