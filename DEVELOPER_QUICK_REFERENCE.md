# 📋 دليل المطور السريع - EMS Security & Performance

> **Quick Reference Guide for Developers**

---

## 🔐 Security Cheat Sheet

### ✅ حماية المخرجات (XSS Prevention)

```php
// ✅ صحيح
echo e($user_input);
echo clean_output($data);
echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

// ❌ خطأ
echo $_POST['name'];
echo $user_input;
```

### ✅ حماية SQL (SQL Injection Prevention)

```php
// ✅ صحيح - Prepared Statement
$stmt = db_query($conn, 
    "SELECT * FROM users WHERE id = ? AND status = ?",
    [$user_id, 1],
    'ii'
);

// ✅ صحيح - Escaping
$safe_name = mysqli_real_escape_string($conn, $_POST['name']);
$query = "SELECT * FROM users WHERE name = '$safe_name'";

// ✅ صحيح - Type Casting
$id = intval($_GET['id']);
$price = floatval($_POST['price']);

// ❌ خطأ
$query = "SELECT * FROM users WHERE id = {$_GET['id']}";
```

### ✅ حماية CSRF

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

### ✅ حماية الجلسات

```php
// بداية كل صفحة محمية
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// فحص الصلاحيات
$user_role = $_SESSION['user']['role'];
if ($user_role != "-1" && $user_role != "4") {
    die("غير مصرح لك بالوصول");
}
```

---

## ⚡ Performance Best Practices

### ✅ استعلامات Database

```php
// ✅ صحيح - تحديد الأعمدة المطلوبة
SELECT id, name, email FROM users WHERE status = 1

// ✅ صحيح - استخدام Pagination
$pagination = ems_get_pagination(25, 200);
$query .= $pagination['limit_sql'];

// ✅ صحيح - Indexes
CREATE INDEX idx_company ON users(company_id);
CREATE INDEX idx_date ON timesheet(date);

// ❌ خطأ - SELECT *
SELECT * FROM huge_table

// ❌ خطأ - بدون LIMIT
SELECT * FROM timesheet
```

### ✅ DataTables Setup

```javascript
// ✅ الإعداد الصحيح
$('#myTable').DataTable({
    deferRender: true,      // رسم مؤجل
    processing: true,        // مؤشر تحميل
    stateSave: true,        // حفظ الحالة
    pageLength: 25,         // 25 سجل
    searchDelay: 350,       // تأخير البحث
    language: {
        url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
    }
});
```

### ✅ AJAX Best Practices

```javascript
// ✅ صحيح
$.ajax({
    url: 'api.php',
    method: 'POST',
    data: {
        action: 'get_data',
        csrf_token: $('meta[name="csrf-token"]').attr('content')
    },
    timeout: 25000,
    success: function(response) {
        // معالجة النتيجة
    },
    error: function(xhr, status, error) {
        console.error('Error:', error);
    }
});
```

---

## 📝 Validation Functions

### ✅ التحقق من المدخلات

```php
// البريد الإلكتروني
if (!validate_email($email)) {
    $errors[] = "البريد الإلكتروني غير صحيح";
}

// رقم الهاتف
if (!validate_phone($phone)) {
    $errors[] = "رقم الهاتف غير صحيح";
}

// التاريخ
if (!validate_date($date, 'Y-m-d')) {
    $errors[] = "التاريخ غير صحيح";
}

// الأرقام
if (!validate_integer($age, 18, 100)) {
    $errors[] = "العمر يجب أن يكون بين 18 و 100";
}

// الطول
if (!validate_length($name, 3, 50)) {
    $errors[] = "الاسم يجب أن يكون بين 3 و 50 حرف";
}
```

### ✅ التنظيف (Sanitization)

```php
$clean_int = sanitize_input($_POST['age'], 'int');
$clean_float = sanitize_input($_POST['price'], 'float');
$clean_email = sanitize_input($_POST['email'], 'email');
$clean_url = sanitize_input($_POST['website'], 'url');
$clean_string = sanitize_input($_POST['name'], 'string');
```

---

## 🔄 Common Patterns

### ✅ CRUD Page Template

```php
<?php
session_start();

// 1. التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

// 2. الاتصال بقاعدة البيانات
require_once '../config.php';

// 3. معالجة POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    // التحقق من المدخلات
    $name = sanitize_input($_POST['name'], 'string');
    $email = sanitize_input($_POST['email'], 'email');
    
    if (!validate_email($email)) {
        $error = "البريد الإلكتروني غير صحيح";
    } else {
        // الحفظ في قاعدة البيانات
        $safe_name = mysqli_real_escape_string($conn, $name);
        $safe_email = mysqli_real_escape_string($conn, $email);
        
        $query = "INSERT INTO users (name, email) VALUES ('$safe_name', '$safe_email')";
        
        if (mysqli_query($conn, $query)) {
            header("Location: success.php");
            exit();
        } else {
            $error = "خطأ في الحفظ: " . mysqli_error($conn);
        }
    }
}

// 4. عنوان الصفحة
$page_title = "إدارة المستخدمين";

// 5. تضمين الواجهة
include '../inheader.php';
include '../insidebar.php';
?>

<!-- HTML Content -->
<div class="content-wrapper">
    <form method="POST">
        <?php echo csrf_field(); ?>
        <!-- الحقول -->
    </form>
</div>

<?php mysqli_close($conn); ?>
```

### ✅ API Endpoint Template

```php
<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. التحقق من الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير صحيحة'
    ]));
}

// 2. التحقق من CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    die(json_encode([
        'success' => false,
        'message' => 'رمز الأمان غير صحيح'
    ]));
}

// 3. الاتصال بقاعدة البيانات
require_once '../config.php';

// 4. معالجة الإجراء
$action = $_POST['action'] ?? '';

if ($action === 'create') {
    // الكود هنا
    echo json_encode([
        'success' => true,
        'message' => 'تم الإنشاء بنجاح'
    ]);
} elseif ($action === 'update') {
    // الكود هنا
    echo json_encode([
        'success' => true,
        'message' => 'تم التحديث بنجاح'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'الإجراء غير معروف'
    ]);
}

exit;
?>
```

---

## 🎨 Frontend Patterns

### ✅ Form with AJAX

```javascript
$('#myForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'api.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert(response.message);
                location.reload();
            } else {
                alert('خطأ: ' + response.message);
            }
        },
        error: function() {
            alert('حدث خطأ في الاتصال');
        }
    });
});
```

### ✅ Modal Form

```html
<!-- Button -->
<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#myModal">
    إضافة جديد
</button>

<!-- Modal -->
<div class="modal fade" id="myModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إضافة جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="modalForm" method="POST">
                <div class="modal-body">
                    <?php echo csrf_field(); ?>
                    <!-- الحقول -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>
```

---

## 🔍 Debugging Tips

### ✅ Database Errors

```php
$result = mysqli_query($conn, $query);
if (!$result) {
    error_log("SQL Error: " . mysqli_error($conn));
    die("حدث خطأ في الاستعلام");
}
```

### ✅ Session Debugging

```php
// عرض محتويات الجلسة
echo '<pre>';
var_dump($_SESSION);
echo '</pre>';
```

### ✅ AJAX Debugging

```javascript
$.ajax({
    // ...
    success: function(response) {
        console.log('Response:', response);
    },
    error: function(xhr, status, error) {
        console.error('Status:', status);
        console.error('Error:', error);
        console.error('Response:', xhr.responseText);
    }
});
```

---

## ⚠️ Common Mistakes

### ❌ **لا تفعل:**

```php
// ❌ SQL Injection
$query = "SELECT * FROM users WHERE id = {$_GET['id']}";

// ❌ XSS
echo $_POST['name'];
echo "<div>$user_input</div>";

// ❌ بدون CSRF
<form method="POST">
    <!-- فقط الحقول بدون csrf_field() -->
</form>

// ❌ عرض أخطاء SQL
die("Error: " . mysqli_error($conn));

// ❌ بدون Type Casting
$id = $_GET['id'];
$query = "SELECT * FROM users WHERE id = $id";

// ❌ بدون Pagination
SELECT * FROM huge_table
```

### ✅ **افعل:**

```php
// ✅ آمن
$id = intval($_GET['id']);
$stmt = db_query($conn, "SELECT * FROM users WHERE id = ?", [$id], 'i');

// ✅ آمن
echo e($user_input);
echo clean_output($_POST['name']);

// ✅ آمن
<form method="POST">
    <?php echo csrf_field(); ?>
</form>

// ✅ آمن
if (!$result) {
    error_log("SQL Error: " . mysqli_error($conn));
    die("حدث خطأ في النظام");
}

// ✅ مُحسّن
$pagination = ems_get_pagination(25);
$query .= $pagination['limit_sql'];
```

---

## 📦 Required Includes

### ✅ في كل صفحة PHP

```php
<?php
// 1. بدء الجلسة
session_start();

// 2. التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

// 3. قاعدة البيانات (تشمل security.php و performance.php)
require_once '../config.php';

// 4. عنوان الصفحة
$page_title = "عنوان الصفحة";

// 5. الواجهة
include '../inheader.php';
include '../insidebar.php';
?>
```

---

## 🎯 Performance Checklist

### ✅ قبل نشر أي صفحة:

- [ ] استخدام Pagination للجداول الكبيرة
- [ ] تحديد الأعمدة المطلوبة (لا `SELECT *`)
- [ ] إضافة Indexes للأعمدة المستخدمة في WHERE
- [ ] استخدام `LEFT JOIN` بدلاً من subqueries
- [ ] ضغط Gzip مفعّل
- [ ] تحميل مؤجل للـ JavaScript
- [ ] استخدام CDN للمكتبات

### ✅ Security Checklist:

- [ ] CSRF token في كل فورم
- [ ] `e()` لكل مخرج من المستخدم
- [ ] `intval()` لكل رقم من GET/POST
- [ ] `mysqli_real_escape_string()` لكل نص في SQL
- [ ] فحص الصلاحيات في بداية الصفحة
- [ ] Session timeout مفعّل
- [ ] Security headers موجودة
- [ ] لا عرض أخطاء SQL للمستخدم

---

## 📞 الدعم السريع

**مشكلة في:**
- **الأمان:** اقرأ `includes/security.php`
- **الأداء:** اقرأ `includes/performance.php`
- **قاعدة البيانات:** اقرأ `config.php`
- **DataTables:** اقرأ `assets/js/performance-boost.js`

---

**الإصدار:** 2.0.0  
**آخر تحديث:** 6 مايو 2026
