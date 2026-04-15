<?php
/**
 * مثال لصفحة محدثة بنظام الأمان الجديد
 * Example Secure Page Template
 * 
 * نسخ هذا الملف واستخدامه كقالب لإنشاء صفحات جديدة آمنة
 */

// ═══════════════════════════════════════════════════════════════════════════
// 1. تحميل الإعدادات (يحمل security.php تلقائياً)
// ═══════════════════════════════════════════════════════════════════════════
require_once '../config.php';

// ═══════════════════════════════════════════════════════════════════════════
// 2. التحقق من الصلاحيات
// ═══════════════════════════════════════════════════════════════════════════
require_login(); // التحقق من تسجيل الدخول

// التحقق من الصلاحية (اختياري - حسب الحاجة)
// require_role(['-1', '1']); // فقط المدير ومدراء المشاريع

// عنوان الصفحة
$page_title = "إيكوبيشن | صفحة آمنة - مثال";

// ═══════════════════════════════════════════════════════════════════════════
// 3. معالجة الحذف (DELETE)
// ═══════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    // التحقق من CSRF Token
    if (!verify_csrf_token($_GET['csrf_token'])) {
        die('خطأ في التحقق من الأمان - CSRF Token غير صحيح');
    }
    
    $id = intval($_GET['delete']);
    
    if ($id > 0) {
        // استخدام Prepared Statement
        $stmt = query_safe(
            "UPDATE example_table SET status = 0 WHERE id = ?",
            [$id],
            'i'
        );
        
        if ($stmt) {
            log_security_event('RECORD_DELETED', "Deleted record ID: $id");
            header("Location: secure_page_example.php?msg=تم+الحذف+بنجاح");
            exit;
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 4. معالجة إضافة/تعديل (POST)
// ═══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ══════════════════════════════════════════════════════════════════
    // 4.1 التحقق من CSRF Token
    // ══════════════════════════════════════════════════════════════════
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('خطأ في التحقق من الأمان - CSRF Token غير صحيح');
    }
    
    // ══════════════════════════════════════════════════════════════════
    // 4.2 Rate Limiting (حماية من الإرسال المتكرر)
    // ══════════════════════════════════════════════════════════════════
    check_rate_limit('form_submit', 10, 60); // 10 محاولات كل دقيقة
    
    // ══════════════════════════════════════════════════════════════════
    // 4.3 استقبال وتنظيف المدخلات
    // ══════════════════════════════════════════════════════════════════
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    // المدخلات النصية
    $name = sanitize_input($_POST['name'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    
    // Email
    $email = sanitize_input($_POST['email'] ?? '', 'email');
    
    // أرقام
    $phone = sanitize_input($_POST['phone'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    
    // Select/Enum
    $status = intval($_POST['status'] ?? 1);
    $category = sanitize_input($_POST['category'] ?? '');
    
    // تاريخ
    $start_date = sanitize_input($_POST['start_date'] ?? '');
    
    // ══════════════════════════════════════════════════════════════════
    // 4.4 التحقق من صحة المدخلات
    // ══════════════════════════════════════════════════════════════════
    $errors = [];
    
    // التحقق من الحقول المطلوبة
    if (empty($name)) {
        $errors[] = 'الاسم مطلوب';
    }
    
    // التحقق من طول النص
    if (!validate_length($name, 3, 100)) {
        $errors[] = 'الاسم يجب أن يكون بين 3 و 100 حرف';
    }
    
    // التحقق من Email
    if (!empty($email) && !validate_email($email)) {
        $errors[] = 'البريد الإلكتروني غير صحيح';
    }
    
    // التحقق من رقم الهاتف
    if (!empty($phone) && !validate_phone($phone)) {
        $errors[] = 'رقم الهاتف غير صحيح';
    }
    
    // التحقق من التاريخ
    if (!empty($start_date) && !validate_date($start_date)) {
        $errors[] = 'التاريخ غير صحيح';
    }
    
    // التحقق من الأرقام
    if (!validate_integer($age, 18, 100)) {
        $errors[] = 'العمر يجب أن يكون بين 18 و 100';
    }
    
    // ══════════════════════════════════════════════════════════════════
    // 4.5 حفظ البيانات
    // ══════════════════════════════════════════════════════════════════
    if (empty($errors)) {
        
        if ($id > 0) {
            // ═══ تحديث ═══
            $stmt = query_safe(
                "UPDATE example_table SET 
                    name = ?, 
                    description = ?, 
                    email = ?, 
                    phone = ?, 
                    age = ?, 
                    price = ?, 
                    category = ?, 
                    start_date = ?, 
                    status = ?
                WHERE id = ?",
                [$name, $description, $email, $phone, $age, $price, $category, $start_date, $status, $id],
                'ssssidssii' // s=string, i=integer, d=double
            );
            
            if ($stmt) {
                log_security_event('RECORD_UPDATED', "Updated record ID: $id, Name: $name");
                header("Location: secure_page_example.php?msg=تم+التعديل+بنجاح");
                exit;
            } else {
                $errors[] = 'حدث خطأ أثناء التعديل';
            }
            
        } else {
            // ═══ إضافة ═══
            $stmt = query_safe(
                "INSERT INTO example_table 
                (name, description, email, phone, age, price, category, start_date, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$name, $description, $email, $phone, $age, $price, $category, $start_date, $status],
                'ssssidssi'
            );
            
            if ($stmt) {
                $new_id = mysqli_insert_id($conn);
                log_security_event('RECORD_CREATED', "Created record ID: $new_id, Name: $name");
                header("Location: secure_page_example.php?msg=تمت+الإضافة+بنجاح");
                exit;
            } else {
                $errors[] = 'حدث خطأ أثناء الإضافة';
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// 5. جلب البيانات للعرض
// ═══════════════════════════════════════════════════════════════════════════
$query = "SELECT * FROM example_table WHERE status = 1 ORDER BY id DESC";
$result = mysqli_query($conn, $query);

// جلب سجل للتعديل (إذا كان هناك ID في URL)
$edit_record = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = query_safe("SELECT * FROM example_table WHERE id = ?", [$edit_id], 'i');
    if ($stmt) {
        $stmt_result = mysqli_stmt_get_result($stmt);
        $edit_record = mysqli_fetch_assoc($stmt_result);
    }
}

?>

<?php include '../inheader.php'; ?>
<?php include '../insidebar.php'; ?>

<div class="content">
    <div class="container-fluid">
        
        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- Header Section -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="page-header">
                    <i class="fas fa-shield-alt"></i>
                    <?php echo e($page_title); ?>
                </h1>
            </div>
        </div>
        
        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- Messages Section -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo e($_GET['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <h5>الأخطاء:</h5>
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?php echo e($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- Form Section -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>
                    <i class="fas fa-plus-circle"></i>
                    <?php echo $edit_record ? 'تعديل السجل' : 'إضافة سجل جديد'; ?>
                </h4>
            </div>
            <div class="card-body">
                
                <form method="POST" id="mainForm">
                    
                    <!-- CSRF Token (مهم جداً!) -->
                    <?php echo csrf_field(); ?>
                    
                    <!-- Hidden ID for Edit -->
                    <?php if ($edit_record): ?>
                    <input type="hidden" name="id" value="<?php echo e($edit_record['id']); ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        
                        <!-- الاسم -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الاسم *</label>
                            <input type="text" 
                                   name="name" 
                                   class="form-control" 
                                   value="<?php echo $edit_record ? e($edit_record['name']) : ''; ?>"
                                   required>
                        </div>
                        
                        <!-- البريد الإلكتروني -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" 
                                   name="email" 
                                   class="form-control"
                                   value="<?php echo $edit_record ? e($edit_record['email']) : ''; ?>">
                        </div>
                        
                        <!-- رقم الهاتف -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="text" 
                                   name="phone" 
                                   class="form-control"
                                   value="<?php echo $edit_record ? e($edit_record['phone']) : ''; ?>">
                        </div>
                        
                        <!-- العمر -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">العمر</label>
                            <input type="number" 
                                   name="age" 
                                   class="form-control" 
                                   min="18" 
                                   max="100"
                                   value="<?php echo $edit_record ? e($edit_record['age']) : ''; ?>">
                        </div>
                        
                        <!-- السعر -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">السعر</label>
                            <input type="number" 
                                   name="price" 
                                   class="form-control" 
                                   step="0.01"
                                   value="<?php echo $edit_record ? e($edit_record['price']) : ''; ?>">
                        </div>
                        
                        <!-- الفئة -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الفئة</label>
                            <select name="category" class="form-control">
                                <option value="">اختر...</option>
                                <option value="فئة أ" <?php echo ($edit_record && $edit_record['category'] == 'فئة أ') ? 'selected' : ''; ?>>فئة أ</option>
                                <option value="فئة ب" <?php echo ($edit_record && $edit_record['category'] == 'فئة ب') ? 'selected' : ''; ?>>فئة ب</option>
                                <option value="فئة ج" <?php echo ($edit_record && $edit_record['category'] == 'فئة ج') ? 'selected' : ''; ?>>فئة ج</option>
                            </select>
                        </div>
                        
                        <!-- تاريخ البدء -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تاريخ البدء</label>
                            <input type="date" 
                                   name="start_date" 
                                   class="form-control"
                                   value="<?php echo $edit_record ? e($edit_record['start_date']) : ''; ?>">
                        </div>
                        
                        <!-- الحالة -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الحالة</label>
                            <select name="status" class="form-control">
                                <option value="1" <?php echo ($edit_record && $edit_record['status'] == 1) ? 'selected' : ''; ?>>نشط</option>
                                <option value="0" <?php echo ($edit_record && $edit_record['status'] == 0) ? 'selected' : ''; ?>>غير نشط</option>
                            </select>
                        </div>
                        
                        <!-- الوصف -->
                        <div class="col-12 mb-3">
                            <label class="form-label">الوصف</label>
                            <textarea name="description" 
                                      class="form-control" 
                                      rows="3"><?php echo $edit_record ? e($edit_record['description']) : ''; ?></textarea>
                        </div>
                        
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <?php echo $edit_record ? 'تحديث' : 'حفظ'; ?>
                        </button>
                        <?php if ($edit_record): ?>
                        <a href="secure_page_example.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            إلغاء
                        </a>
                        <?php endif; ?>
                    </div>
                    
                </form>
                
            </div>
        </div>
        
        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- Table Section -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div class="card">
            <div class="card-header">
                <h4>
                    <i class="fas fa-list"></i>
                    قائمة السجلات
                </h4>
            </div>
            <div class="card-body">
                
                <table id="dataTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>البريد الإلكتروني</th>
                            <th>الهاتف</th>
                            <th>الفئة</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?php echo e($row['id']); ?></td>
                                <td><?php echo e($row['name']); ?></td>
                                <td><?php echo e($row['email']); ?></td>
                                <td><?php echo e($row['phone']); ?></td>
                                <td><?php echo e($row['category']); ?></td>
                                <td>
                                    <?php if ($row['status'] == 1): ?>
                                        <span class="badge bg-success">نشط</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">غير نشط</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?edit=<?php echo e($row['id']); ?>" 
                                       class="btn btn-sm btn-warning" 
                                       title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <a href="?delete=<?php echo e($row['id']); ?>&csrf_token=<?php echo e(generate_csrf_token()); ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('هل أنت متأكد من الحذف؟');"
                                       title="حذف">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">لا توجد سجلات</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
            </div>
        </div>
        
    </div>
</div>

<!-- DataTables Script -->
<script>
$(document).ready(function() {
    $('#dataTable').DataTable({
        language: {
            url: '/ems/assets/i18n/datatables/ar.json'
        },
        pageLength: 10,
        order: [[0, 'desc']]
    });
});
</script>

</body>
</html>


