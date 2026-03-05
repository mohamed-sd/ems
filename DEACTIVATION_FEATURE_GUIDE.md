# تعليمات إضافة نماذج إيقاف المشغلين والآليات

## 1. إضافة نماذج إيقاف المشغلين إلى صفحة Drivers (Drivers/drivers.php)

### الخطوة الأولى: إضافة المودالز
أضف السطر التالي في نهاية ملف `Drivers/drivers.php` (قبل إغلاق `</body>` أو في نهاية الـ includes):

```php
<?php
// إضافة نماذج الإيقاف (فقط لمدير الحركة والتشغيل)
if ($_SESSION['user']['role'] == '10') {
    include 'deactivate_driver_modals.html';
}
?>
```

### الخطوة الثانية: إضافة أزرار الإجراء في جدول المشغلين

ابحث عن جدول عرض المشغلين في `Drivers/drivers.php` وأضف أزرار الإجراء:

```html
<!-- مثال: في صف الجدول -->
<button type="button" class="btn btn-sm btn-warning deactivateDriverBtn"
        data-id="<?php echo $driver['id']; ?>"
        data-name="<?php echo htmlspecialchars($driver['name']); ?>"
        data-code="<?php echo htmlspecialchars($driver['driver_code']); ?>"
        data-status="<?php echo htmlspecialchars($driver['driver_status']); ?>">
    <i class="fas fa-ban"></i> إيقاف
</button>

<!-- زر إعادة التفعيل (اختياري) -->
<?php if ($driver['driver_status'] === 'موقوف'): ?>
    <button type="button" class="btn btn-sm btn-success reactivateDriverBtn"
            data-id="<?php echo $driver['id']; ?>"
            data-name="<?php echo htmlspecialchars($driver['name']); ?>"
            data-code="<?php echo htmlspecialchars($driver['driver_code']); ?>"
            data-status="<?php echo htmlspecialchars($driver['driver_status']); ?>">
        <i class="fas fa-check-circle"></i> إعادة التفعيل
    </button>
<?php endif; ?>
```

---

## 2. إضافة نماذج إيقاف الآليات إلى صفحة Equipments (Equipments/equipments.php)

### الخطوة الأولى: إضافة المودالز
أضف السطر التالي في نهاية ملف `Equipments/equipments.php`:

```php
<?php
// إضافة نماذج الإيقاف (فقط لمدير الحركة والتشغيل)
if ($_SESSION['user']['role'] == '10') {
    include 'deactivate_equipment_modals.html';
}
?>
```

### الخطوة الثانية: إضافة أزرار الإجراء في جدول الآليات

ابحث عن جدول عرض الآليات وأضف أزرار الإجراء:

```html
<!-- مثال: في صف الجدول -->
<button type="button" class="btn btn-sm btn-warning deactivateEquipmentBtn"
        data-id="<?php echo $equipment['id']; ?>"
        data-code="<?php echo htmlspecialchars($equipment['code']); ?>"
        data-name="<?php echo htmlspecialchars($equipment['name']); ?>"
        data-status="<?php echo htmlspecialchars($equipment['availability_status']); ?>">
    <i class="fas fa-ban"></i> إيقاف
</button>

<!-- زر إعادة التفعيل (اختياري) -->
<?php if ($equipment['availability_status'] === 'موقوفة للصيانة'): ?>
    <button type="button" class="btn btn-sm btn-success reactivateEquipmentBtn"
            data-id="<?php echo $equipment['id']; ?>"
            data-code="<?php echo htmlspecialchars($equipment['code']); ?>"
            data-name="<?php echo htmlspecialchars($equipment['name']); ?>"
            data-status="<?php echo htmlspecialchars($equipment['availability_status']); ?>">
        <i class="fas fa-check-circle"></i> إعادة التفعيل
    </button>
<?php endif; ?>
```

---

## 3. سير العمل (Workflow)

### لإيقاف مشغل:
1. مدير الحركة والتشغيل (Role 10) ينقر على زر "إيقاف"
2. يفتح نموذج يطلب سبب الإيقاف وتاريخ الإيقاف
3. يتم تقديم طلب الموافقة إلى النظام
4. مدير المشغلين (Role 3) يرى الطلب في صفحة "طلبات الموافقات"
5. بعد الموافقة، يتم تحديث حالة المشغل إلى "موقوف"

### لإيقاف آلية:
1. مدير الحركة والتشغيل (Role 10) ينقر على زر "إيقاف"
2. يفتح نموذج يطلب سبب الإيقاف وتاريخ الإيقاف
3. يتم تقديم طلب الموافقة إلى النظام
4. مدير الأسطول (Role 4) يرى الطلب في صفحة "طلبات الموافقات"
5. بعد الموافقة، يتم تحديث حالة الآلية إلى "موقوفة للصيانة"

---

## 4. الملفات التي تم إنشاؤها/تعديلها

### ملفات جديدة:
- `Drivers/deactivate_driver_handler.php` - معالج طلب إيقاف المشغل (API)
- `Drivers/deactivate_driver_modals.html` - نماذج الإيقاف والتفعيل
- `Equipments/deactivate_equipment_handler.php` - معالج طلب إيقاف الآلية (API)
- `Equipments/deactivate_equipment_modals.html` - نماذج الإيقاف والتفعيل

### ملفات معدلة:
- `database/approval_workflow.sql` - إضافة قواعد الموافقة الجديدة
- `sidebar.php` - إضافة روابط "طلبات الموافقات" 

---

## 5. الأدوار المسؤولة

| الدور | الوصف | الصلاحيات |
|------|--------|---------|
| مدير الحركة والتشغيل (10) | يقدم طلبات إيقاف المشغلين والآليات | تقديم الطلبات فقط |
| مدير المشغلين (3) | يوافق على طلبات إيقاف المشغلين | الموافقة على الطلبات |
| مدير الأسطول (4) | يوافق على طلبات إيقاف الآليات | الموافقة على الطلبات |
| الإدارة العليا (-1) | لديها صلاحيات كاملة | كل شيء |

---

## 6. ملاحظات مهمة

1. **التحديث التلقائي**: عند الموافقة على الطلب، يتم تحديث حالة المشغل/الآلية تلقائياً
2. **سجل التدقيق**: جميع الطلبات يتم حفظها في جدول `approval_requests`
3. **التنبيهات**: يمكن إضافة تنبيهات بريدية لاحقاً
4. **قاعدة البيانات**: تم إضافة القواعد الافتراضية في `approval_workflow.sql`

