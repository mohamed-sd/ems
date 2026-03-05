# 💻 دليل الدمج - كيفية استخدام الصلاحيات في الصفحات

## 🎯 مقدمة سريعة

بمجرد تفعيل نظام الصلاحيات، يمكنك استخدام الدوال المساعدة لحماية صفحاتك من الوصول غير المصرح.

---

## 📍 الملف الرئيسي

**المسار:** `/includes/permissions_helper.php`

تأكد من تضمين هذا الملف في بداية صفحتك:

```php
<?php
require_once '../config.php';
require_once '../includes/permissions_helper.php';
?>
```

---

## 🛡️ طرق الحماية

### 1️⃣ التحقق والتوقف الفوري (الأسهل والأكثر أماناً)

استخدم هذه الطريقة لإيقاف التنفيذ فوراً إذا لم تكن هناك صلاحية:

```php
<?php
session_start();
require_once '../config.php';
require_once '../includes/permissions_helper.php';

// التحقق من أن المستخدم لديه صلاحية العرض للشاشة رقم 5
// إذا لم يكن لديه → يتم إيقاف التنفيذ فوراً
check_view_permission($conn, 5);

// إذا وصلنا هنا → المستخدم له صلاحية العرض
// يمكنك آمناً عرض البيانات
?>
...
```

#### الدوال المتاحة:

```php
check_view_permission($conn, 5);     // 👁️ التحقق من صلاحية العرض
check_add_permission($conn, 5);      // ➕ التحقق من صلاحية الإضافة
check_edit_permission($conn, 5);     // ✏️ التحقق من صلاحية التعديل
check_delete_permission($conn, 5);   // 🗑️ التحقق من صلاحية الحذف
```

### 2️⃣ التحقق المشروط (للتحكم الدقيق)

إذا أردت معالجة الصلاحيات بطريقة مشروطة:

```php
<?php
require_once '../config.php';
require_once '../includes/permissions_helper.php';

// استعلم عن صلاحية معينة
if (check_permission($conn, 5, 'view')) {
    echo "يمكن العرض!";
} else {
    echo "لا يمكن العرض";
}

// أو تحقق من صلاحية الإضافة
if (check_permission($conn, 5, 'add')) {
    // اسمح بإضافة البيانات
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // معالجة الإضافة
    }
} else {
    // اخفِ نموذج الإضافة
    $add_disabled = true;
}
?>
```

### 3️⃣ الحصول على جميع الصلاحيات

للحصول على كل الصلاحيات دفعة واحدة:

```php
<?php
require_once '../config.php';
require_once '../includes/permissions_helper.php';

// احصل على جميع صلاحيات شاشة معينة
$perms = get_module_permissions($conn, 5);

echo $perms['can_view'] ? '✅ يمكن العرض' : '❌ لا يمكن العرض';
echo $perms['can_add'] ? '✅ يمكن الإضافة' : '❌ لا يمكن الإضافة';
echo $perms['can_edit'] ? '✅ يمكن التعديل' : '❌ لا يمكن التعديل';
echo $perms['can_delete'] ? '✅ يمكن الحذف' : '❌ لا يمكن الحذف';
?>
```

---

## 🎨 أمثلة عملية

### مثال 1: حماية صفحة كاملة

```php
<?php
/**
 * صفحة إدارة العملاء - Manage Clients
 */
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}

require_once '../config.php';
require_once '../includes/permissions_helper.php';

// 🛡️ التحقق من صلاحية العرض
check_view_permission($conn, 5);  // 5 = module_id للعملاء

// إذا وصلنا هنا → الصلاحية موجودة
?>
<!DOCTYPE html>
<html>
<head>
    <title>إدارة العملاء</title>
</head>
<body>
    <h1>قائمة العملاء</h1>
    
    <!-- عرض البيانات -->
    <?php
    $result = $conn->query("SELECT * FROM clients");
    while ($row = $result->fetch_assoc()):
    ?>
        <div class="client-item">
            <h3><?php echo $row['client_name']; ?></h3>
            
            <!-- زر التعديل -->
            <?php if (check_permission($conn, 5, 'edit')): ?>
                <a href="?edit=<?php echo $row['id']; ?>" class="btn-edit">تعديل</a>
            <?php endif; ?>
            
            <!-- زر الحذف -->
            <?php if (check_permission($conn, 5, 'delete')): ?>
                <a href="?delete=<?php echo $row['id']; ?>" class="btn-delete" 
                   onclick="return confirm('هل تريد الحذف؟')">حذف</a>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
    
    <!-- نموذج الإضافة -->
    <?php if (check_permission($conn, 5, 'add')): ?>
        <form method="POST" class="add-form">
            <input type="text" name="name" required>
            <button type="submit">إضافة عميل جديد</button>
        </form>
    <?php endif; ?>
</body>
</html>
```

### مثال 2: معالجة الطلبات بناءً على الصلاحيات

```php
<?php
session_start();
require_once '../config.php';
require_once '../includes/permissions_helper.php';

// التحقق الأساسي
check_view_permission($conn, 5);

// معالجة الطلبات المختلفة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // معالجة الإضافة
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        check_add_permission($conn, 5);  // تحقق من الصلاحية
        
        $name = $_POST['name'] ?? '';
        $stmt = $conn->prepare("INSERT INTO clients (client_name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
    }
    
    // معالجة التعديل
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        check_edit_permission($conn, 5);  // تحقق من الصلاحية
        
        $id = $_POST['id'];
        $name = $_POST['name'];
        $stmt = $conn->prepare("UPDATE clients SET client_name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $id);
        $stmt->execute();
    }
    
    // معالجة الحذف
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        check_delete_permission($conn, 5);  // تحقق من الصلاحية
        
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
}
?>
```

### مثال 3: إخفاء الأزرار بناءً على الصلاحيات

```php
<?php
require_once '../config.php';
require_once '../includes/permissions_helper.php';

$perms = get_module_permissions($conn, 5);
?>

<div class="action-buttons">
    <!-- الزر يظهر فقط إذا كان هناك صلاحية -->
    <button class="btn btn-primary <?php echo !$perms['can_add'] ? 'd-none' : ''; ?>">
        إضافة جديد
    </button>
    
    <!-- أو استخدم الدالة المساعدة -->
    <button class="btn btn-warning <?php echo can_show_button($conn, 5, 'edit'); ?>">
        تعديل
    </button>
    
    <button class="btn btn-danger <?php echo can_show_button($conn, 5, 'delete'); ?>">
        حذف
    </button>
</div>
```

### مثال 4: عرض شارات الصلاحيات

```php
<?php
require_once '../config.php';
require_once '../includes/permissions_helper.php';
?>

<tr>
    <td>العملاء</td>
    <td><?php echo permission_badge($conn, 5, 'view', 'صلاحية العرض'); ?></td>
    <td><?php echo permission_badge($conn, 5, 'add', 'صلاحية الإضافة'); ?></td>
    <td><?php echo permission_badge($conn, 5, 'edit', 'صلاحية التعديل'); ?></td>
    <td><?php echo permission_badge($conn, 5, 'delete', 'صلاحية الحذف'); ?></td>
    <td>
        <?php
        [$available, $total, $percentage] = permission_percentage($conn, 5);
        echo "$available/$total ($percentage%)";
        ?>
    </td>
</tr>
```

---

## 🔧 معرفات الشاشات (Module IDs)

تحتاج إلى معرفة معرف الشاشة (module_id) لاستخدام الصلاحيات:

| الشاشة | الكود | المعرف |
|--------|------|--------|
| العملاء | clients | ? |
| المشاريع | projects | ? |
| الموردين | suppliers | ? |
| الآليات | equipments | ? |
| المشغلين | drivers | ? |
| ساعات العمل | timesheet | ? |

**للعثور على معرف الشاشة:**

```sql
SELECT id, code, name FROM modules WHERE code = 'clients';
```

أو ذهب إلى: `Settings > إدارة الصفحات والموديولات`

---

## 📋 قائمة المراجعة

قبل نشر صفحتك القديمة، تأكد من:

- [ ] إضافة `require_once '../includes/permissions_helper.php'`
- [ ] إضافة `check_view_permission($conn, MODULE_ID)` في بداية الصفحة
- [ ] إضافة التحقق من الصلاحيات المختلفة (إضافة، تعديل، حذف)
- [ ] إخفاء الأزرار غير المصرح بها
- [ ] اختبار مع مستخدم عادي للتأكد من الحماية

---

## 🐛 استكشاف الأخطاء

### الخطأ: "دالة غير معرفة"
**السبب:** لم تقم بتضمين ملف `permissions_helper.php`
**الحل:** أضف هذا السطر:
```php
require_once '../includes/permissions_helper.php';
```

### الخطأ: "لا توجد صلاحيات"
**السبب:** الصلاحية لم تُعيّن للدور
**الحل:** ذهب إلى `Settings/role_permissions.php` وأضف الصلاحية

### الخطأ: "Foreign Key"
**السبب:** معرف الشاشة غير موجود
**الحل:** استعلم عن المعرف الصحيح من جدول `modules`

---

## 💡 نصائح مهمة

1. **استخدم التوقف الفوري** - أفضل للأمان
2. **تحقق من الصلاحيات قبل المعالجة** - منع معالجة البيانات غير المصرح بها
3. **اخفِ الأزرار للتجربة الأفضل** - لكن اعتمد على الفحص الخلفي للأمان
4. **استخدم معرفات الشاشات بشكل متسق** - لتجنب الأخطاء

---

## 🔗 روابط مفيدة

- [صفحة إدارة الصلاحيات](Settings/role_permissions.php)
- [اختبار النظام](test_role_permissions.php)
- [دليل الصلاحيات الكامل](ROLE_PERMISSIONS_GUIDE.md)
- [البدء السريع](ROLE_PERMISSIONS_QUICKSTART.md)

---

**ملاحظة:** جميع الأمثلة تفترض أن معرف الشاشة هو `5` - استبدله بالمعرف الفعلي لشاشتك.
