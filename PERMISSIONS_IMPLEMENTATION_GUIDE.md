# 🔐 دليل تطبيق نظام الصلاحيات على الصفحات

## مقدمة
تم تطبيق نظام الصلاحيات الشامل (role_permissions) على جميع الصفحات الرئيسية. هذا الدليل يشرح كيفية استخدام النظام على أي صفحة في التطبيق.

## 📋 الصفحات التي تم تطبيق الصلاحيات عليها

### ✅ الصفحات المحمية بالفعل:
1. **Clients/clients.php** - إدارة العملاء
2. **Suppliers/suppliers.php** - إدارة الموردين
3. **Drivers/drivers.php** - إدارة المشغلين
4. **Equipments/equipments.php** - إدارة المعدات
5. **Timesheet/view_timesheet.php** - عرض ساعات العمل
6. **Contracts/contracts.php** - إدارة العقود
7. **Projects/oprationprojects.php** - إدارة المشاريع

## 🔧 كيفية التطبيق على صفحة جديدة

### الخطوة 1: إضافة الدوال Helper
في بداية الصفحة (بعد `session_start()` و `include '../config.php'`):

```php
<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';

// ════════════════════════════════════════════════════════════════════════════
// 🔒 التحقق من صلاحيات المستخدم
// ════════════════════════════════════════════════════════════════════════════
$page_permissions = check_page_permissions($conn, 'page_name');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// منع الوصول إذا لم تكن هناك صلاحية عرض
if (!$can_view) {
    header("Location: ../index.php?msg=لا+توجد+صلاحية+للوصول+لهذه+الصفحة+❌");
    exit();
}
?>
```

**ملاحظة:** استبدل `'page_name'` باسم الصفحة (مثلاً: 'suppliers', 'drivers', 'contracts')

### الخطوة 2: حماية عمليات POST (الإضافة والتعديل)

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    // التحقق من الصلاحية (إضافة أو تعديل)
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    
    if ($is_editing && !$can_edit) {
        header("Location: page.php?msg=لا+توجد+صلاحية+تعديل+❌");
        exit();
    } elseif (!$is_editing && !$can_add) {
        header("Location: page.php?msg=لا+توجد+صلاحية+إضافة+❌");
        exit();
    }
    
    // باقي الكود الحالي...
}
```

### الخطوة 3: حماية عملية الحذف

```php
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // التحقق من صلاحية الحذف
    if (!$can_delete) {
        header("Location: page.php?msg=لا+توجد+صلاحية+حذف+❌");
        exit();
    }
    
    // باقي الكود الحالي...
}
```

### الخطوة 4: إخفاء الأزرار في الواجهة

#### إخفاء زر الإضافة:
```php
<?php if ($can_add): ?>
    <a href="javascript:void(0)" id="toggleForm" class="add-btn">
        <i class="fas fa-plus-circle"></i> إضافة جديد
    </a>
<?php else: ?>
    <button class="add-btn" disabled style="opacity: 0.5; cursor: not-allowed;">
        <i class="fas fa-plus-circle"></i> إضافة (بدون صلاحيات)
    </button>
<?php endif; ?>
```

#### إخفاء أزرار التعديل والحذف في الجدول:
```php
<td>
    <div class='action-btns'>
        <!-- زر العرض (دائماً مرئي بصلاحية view) -->
        <a href='javascript:void(0)' class='action-btn view'>
            <i class='fas fa-eye'></i>
        </a>
        
        <!-- زر التعديل (شرطي) -->
        <?php if ($can_edit): ?>
            <a href='javascript:void(0)' class='action-btn edit'>
                <i class='fas fa-edit'></i>
            </a>
        <?php endif; ?>
        
        <!-- زر الحذف (شرطي) -->
        <?php if ($can_delete): ?>
            <a href='?delete_id=<?php echo $row['id']; ?>' class='action-btn delete'>
                <i class='fas fa-trash-alt'></i>
            </a>
        <?php endif; ?>
    </div>
</td>
```

## 📊 مثال عملي كامل: صفحة جديدة

```php
<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';

// التحقق من الصلاحيات
$page_permissions = check_page_permissions($conn, 'custom_page');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// منع الوصول بدون صلاحية عرض
if (!$can_view) {
    header("Location: ../index.php?msg=لا+توجد+صلاحيات+للوصول+❌");
    exit();
}

// معالجة الإضافة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    if (!$can_add) {
        header("Location: page.php?msg=لا+توجد+صلاحية+إضافة+❌");
        exit();
    }
    // ... كود الإضافة
}

// معالجة التعديل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {
    if (!$can_edit) {
        header("Location: page.php?msg=لا+توجد+صلاحية+تعديل+❌");
        exit();
    }
    // ... كود التعديل
}

// معالجة الحذف
if (isset($_GET['delete_id'])) {
    if (!$can_delete) {
        header("Location: page.php?msg=لا+توجد+صلاحية+حذف+❌");
        exit();
    }
    // ... كود الحذف
}

$page_title = "صفحتي";
include("../inheader.php");
include('../insidebar.php');
?>

<div class="main">
    <div class="page-header">
        <h1>إدارة البيانات</h1>
        <?php if ($can_add): ?>
            <a href="javascript:void(0)" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة جديد
            </a>
        <?php endif; ?>
    </div>

    <table class="display">
        <thead>
            <tr>
                <th>الاسم</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $query = "SELECT * FROM items ORDER BY id DESC";
            $result = mysqli_query($conn, $query);
            
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>";
                
                // زر التعديل (شرطي)
                if ($can_edit) {
                    echo "<a href='?edit_id=" . $row['id'] . "' class='action-btn edit'><i class='fas fa-edit'></i></a>";
                }
                
                // زر الحذف (شرطي)
                if ($can_delete) {
                    echo "<a href='?delete_id=" . $row['id'] . "' class='action-btn delete' onclick='return confirm(\"هل تريد الحذف؟\")'><i class='fas fa-trash'></i></a>";
                }
                
                echo "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>
```

## 🎯 الدوال المتاحة في permissions_helper.php

### 1. `check_page_permissions($conn, $module_code)`
تحقق من صلاحيات المستخدم على صفحة معينة.

**المعاملات:**
- `$conn`: اتصال قاعدة البيانات
- `$module_code`: رمز الصفحة (suppliers, drivers, contracts, إلخ)

**القيمة المرجعة:**
```php
[
    'id' => module_id,
    'can_view' => boolean,
    'can_add' => boolean,
    'can_edit' => boolean,
    'can_delete' => boolean
]
```

### 2. `check_permission($conn, $module_id, $permission)`
تحقق من صلاحية محددة.

```php
if (check_permission($conn, 5, 'delete')) {
    // السماح بالحذف
}
```

### 3. `get_module_permissions($conn, $module_id)`
احصل على جميع الصلاحيات الأربعة لوحدة معينة.

### 4. `has_any_permission($conn, $module_id)`
تحقق ما إذا كان للمستخدم أي صلاحية على الصفحة.

### 5. `has_all_permissions($conn, $module_id)`
تحقق ما إذا كان للمستخدم جميع الصلاحيات.

## 🔄 عملية البحث عن الوحدة

الدالة `check_page_permissions()` تبحث عن الوحدة بثلاث طرق:

1. **البحث بالرمز (code)**: `Suppliers/suppliers.php`
2. **البحث بالاسم**: `الموردين`
3. **البحث بنمط يحتوي على الكلمة**: `suppliers`

مثال على الأسماء المدعومة:
- `suppliers` → وحدة الموردين
- `drivers` → وحدة المشغلين
- `equipments` → وحدة المعدات
- `contracts` → وحدة العقود
- `clients` → وحدة العملاء
- `projects` → وحدة المشاريع
- `timesheet` → وحدة ساعات العمل

## ⚙️ إدارة الصلاحيات

للدخول لإدارة الصلاحيات:

1. اذهب إلى **الإعدادات** (Settings)
2. اختر **إدارة صلاحيات الأدوار** (Role Permissions Management)
3. اختر الدور المطلوب
4. اختر الشاشة والصلاحيات المطلوبة

## 📝 ملاحظات مهمة

### عند الإضافة/التعديل:
- تأكد من التحقق من الصلاحيات **قبل** معالجة البيانات
- استخدم رسائل خطأ واضحة
- لا تفصح عن أسباب رفض الوصول تفصيلاً (أمان)

### عند الحذف:
- تحقق من الصلاحيات أولاً
- تحقق من عدم وجود مراجع قبل الحذف
- احفظ سجل الحذف للأمان

### التوافقية مع الصفحات القديمة:
- إذا لم تجد الدالة وحدة في قاعدة البيانات، تفترض أن المستخدم لديه جميع الصلاحيات
- هذا يسمح بالتوافقية مع الصفحات القديمة

## 🔗 الملفات المرتبطة

- [includes/permissions_helper.php](includes/permissions_helper.php) - دوال helper
- [database/role_permissions.sql](database/role_permissions.sql) - جدول قاعدة البيانات
- [Settings/role_permissions.php](Settings/role_permissions.php) - واجهة الإدارة

---

**تاريخ التحديث:** مارس 2026  
**النسخة:** 1.0  
**الحالة:** إنتاجي ✅
