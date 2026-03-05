# دليل التكامل - إضافة الأزرار والنماذج إلى موقع Drivers و Equipments

## 📌 نقطة مهمة جداً
اتبع الخطوات التالية لتوصيل نماذج الإيقاف بصفحات Drivers و Equipments

---

## الخطوة 1️⃣: تحديث Drivers/drivers.php

### 1.1 - إضافة النموذج في نهاية الملف

ابحث عن نهاية ملف `Drivers/drivers.php` (قبل أي علامات إغلاق PHP أو HTML)، وأضف:

```php
<!-- إضافة نماذج الإيقاف والتفعيل -->
<?php 
// فقط مدير الحركة والتشغيل (Role 10) يرى هذه النماذج
if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == '10') {
    include __DIR__ . '/deactivate_driver_modals.html';
}
?>
```

### 1.2 - إضافة الأزرار في جدول المشغلين

ابحث عن جدول عرض المشغلين (Table with driver data)، وفي كل صف (row) أضف أزرار الإجراء:

**مثال - في loop المشغلين:**
```php
<?php while ($row = mysqli_fetch_assoc($result)): ?>
    <tr>
        <!-- أعمدة أخرى -->
        <td>
            <?php 
            $status = $row['driver_status'] ?? 'نشط';
            if ($status === 'نشط'): 
            ?>
                <button type="button" class="btn btn-sm btn-warning deactivateDriverBtn"
                        data-id="<?php echo intval($row['id']); ?>"
                        data-name="<?php echo htmlspecialchars($row['name']); ?>"
                        data-code="<?php echo htmlspecialchars($row['driver_code'] ?? ''); ?>"
                        data-status="نشط"
                        title="تقديم طلب إيقاف هذا المشغل">
                    <i class="fas fa-ban"></i> إيقاف
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-sm btn-success reactivateDriverBtn"
                        data-id="<?php echo intval($row['id']); ?>"
                        data-name="<?php echo htmlspecialchars($row['name']); ?>"
                        data-code="<?php echo htmlspecialchars($row['driver_code'] ?? ''); ?>"
                        data-status="<?php echo htmlspecialchars($status); ?>"
                        title="تقديم طلب إعادة تفعيل هذا المشغل">
                    <i class="fas fa-check-circle"></i> تفعيل
                </button>
            <?php endif; ?>
        </td>
    </tr>
<?php endwhile; ?>
```

---

## الخطوة 2️⃣: تحديث Equipments/equipments.php

### 2.1 - إضافة النموذج في نهاية الملف

ابحث عن نهاية ملف `Equipments/equipments.php`، وأضف:

```php
<!-- إضافة نماذج الإيقاف والتفعيل -->
<?php 
// فقط مدير الحركة والتشغيل (Role 10) يرى هذه النماذج
if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == '10') {
    include __DIR__ . '/deactivate_equipment_modals.html';
}
?>
```

### 2.2 - إضافة الأزرار في جدول الآليات

ابحث عن جدول عرض الآليات (Table with equipment data)، وفي كل صف أضف:

**مثال - في loop الآليات:**
```php
<?php while ($row = mysqli_fetch_assoc($result)): ?>
    <tr>
        <!-- أعمدة أخرى -->
        <td>
            <?php 
            $status = $row['availability_status'] ?? 'متاحة للعمل';
            if ($status !== 'موقوفة للصيانة'): 
            ?>
                <button type="button" class="btn btn-sm btn-warning deactivateEquipmentBtn"
                        data-id="<?php echo intval($row['id']); ?>"
                        data-code="<?php echo htmlspecialchars($row['code'] ?? ''); ?>"
                        data-name="<?php echo htmlspecialchars($row['name'] ?? ''); ?>"
                        data-status="<?php echo htmlspecialchars($status); ?>"
                        title="تقديم طلب إيقاف هذه الآلية">
                    <i class="fas fa-ban"></i> إيقاف
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-sm btn-success reactivateEquipmentBtn"
                        data-id="<?php echo intval($row['id']); ?>"
                        data-code="<?php echo htmlspecialchars($row['code'] ?? ''); ?>"
                        data-name="<?php echo htmlspecialchars($row['name'] ?? ''); ?>"
                        data-status="<?php echo htmlspecialchars($status); ?>"
                        title="تقديم طلب إعادة تفعيل هذه الآلية">
                    <i class="fas fa-check-circle"></i> تفعيل
                </button>
            <?php endif; ?>
        </td>
    </tr>
<?php endwhile; ?>
```

---

## 🔍 القائمة التفقدية

- [ ] تم فتح ملف `Drivers/drivers.php`
- [ ] تم إضافة `include 'deactivate_driver_modals.html'` في النهاية
- [ ] تم إضافة أزرار `deactivateDriverBtn` و `reactivateDriverBtn` في جدول المشغلين
- [ ] تم فتح ملف `Equipments/equipments.php`
- [ ] تم إضافة `include 'deactivate_equipment_modals.html'` في النهاية
- [ ] تم إضافة أزرار `deactivateEquipmentBtn` و `reactivateEquipmentBtn` في جدول الآليات
- [ ] تم تشغيل `setup_approval_workflow.php` لإنشاء جداول الموافقات
- [ ] تم التحقق من أن `approval_workflow.sql` يحتوي على القواعد الجديدة

---

## 📝 ملاحظات مهمة

### البيانات المطلوبة للأزرار
كل زر يجب أن يحتوي على `data` attributes:

**للمشغلين:**
- `data-id`: معرف المشغل
- `data-name`: اسم المشغل
- `data-code`: كود المشغل
- `data-status`: حالة المشغل الحالية (نشط/موقوف)

**للآليات:**
- `data-id`: معرف الآلية
- `data-code`: كود الآلية
- `data-name`: اسم الآلية
- `data-status`: حالة الآلية (متاحة للعمل/موقوفة للصيانة)

### الفئات (Classes) المستخدمة
النماذج تقوم بالبحث عن الأزرار عند طريق هذه الفئات:
- `.deactivateDriverBtn` - زر إيقاف المشغل
- `.reactivateDriverBtn` - زر تفعيل المشغل
- `.deactivateEquipmentBtn` - زر إيقاف الآلية
- `.reactivateEquipmentBtn` - زر تفعيل الآلية

---

## 🧪 اختبار سريع

بعد إضافة الأزرار والنماذج:

1. سجل الدخول كـ **مدير الحركة والتشغيل (Role 10)**
2. انتقل إلى صفحة **Drivers** أو **Equipments**
3. يجب أن ترى أزرار **إيقاف** و **تفعيل**
4. اضغط على أحد الأزرار
5. يجب أن يفتح نموذج (Modal)
6. أكمل ملء النموذج والاختبار

---

## 🆘 استكشاف الأخطاء

### المشكلة: لا ترى الأزرار
**الحل:**
- تحقق من أن دورك هو `10` (مدير الحركة والتشغيل)
- تأكد أن الملفات تم تضمينها بشكل صحيح
- فتش console في المتصفح (F12) للأخطاء

### المشكلة: النموذج لا يفتح
**الحل:**
- تأكد من أن jQuery و Bootstrap محملة
- تحقق من وجود `deactivate_driver_modals.html` و `deactivate_equipment_modals.html`
- فتش console للأخطاء JavaScript

### المشكلة: الطلب لا ينجح
**الحل:**
- تأكد من أن `deactivate_driver_handler.php` و `deactivate_equipment_handler.php` موجودة
- تحقق من أن `approval_workflow.php` موجودة في `includes/`
- فتش Network tab في console لترى استجابة السيرفر

---

## 📚 ملفات إضافية

- `DEACTIVATION_FEATURE_GUIDE.md` - دليل مفصل
- `DEACTIVATION_FEATURE.json` - مواصفات تقنية
- `SETUP_DEACTIVATION.sh` - سكريبت الإعداد

