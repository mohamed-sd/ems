# شرح شامل - نظام الاعتمادات والموافقات (Approval Workflow System)
## تاريخ الإنشاء: 2026-03-03

---

## 📋 جدول المحتويات
1. [مقدمة عامة](#مقدمة-عامة)
2. [معمارية النظام](#معمارية-النظام)
3. [قاعدة البيانات](#قاعدة-البيانات)
4. [الدوال الأساسية](#الدوال-الأساسية)
5. [دورة حياة الطلب](#دورة-حياة-الطلب)
6. [الميزات المتقدمة](#الميزات-المتقدمة)
7. [حالات الاستخدام](#حالات-الاستخدام)
8. [الأخطاء والمشاكل الشائعة](#الأخطاء-والمشاكل-الشائعة)

---

## 🎯 مقدمة عامة

### ما هو نظام الاعتمادات؟
نظام **Approval Workflow** هو نظام متقدم لإدارة طلبات الموافقات على العمليات الحساسة في نظام إدارة المعدات (EMS). 

**الهدف الرئيسي:**
- ضمان عدم تنفيذ عمليات حساسة إلا بموافقة صاحب الصلاحيات المناسب
- توفير تتبع شامل لجميع الطلبات والموافقات
- دعم الموافقات متعددة المستويات (Multi-step Approvals)
- توفير نظام آمن وشفاف لإدارة العمليات الحساسة

### العمليات المدعومة حالياً:
1. **إيقاف/تفعيل المشغلين** (Drivers Deactivation/Reactivation)
2. **إيقاف/تفعيل الآليات** (Equipment Deactivation/Reactivation)
3. **تجديد العقود** (Contract Renewal)
4. **حل العقود** (Contract Settlement)
5. **إيقاف والاستئناف للعقود** (Contract Pause/Resume)
6. **إنهاء العقود** (Contract Termination)
7. **دمج العقود** (Contract Merge)
8. **موافقات جداول الساعات** (Timesheet Approvals)

---

## 🏗️ معمارية النظام

### المستويات الأربع للنظام:

```
┌─────────────────────────────────────────────────────┐
│  المستوى 1: واجهة المستخدم (UI Layer)             │
│  ├─ Drivers/drivers.php                             │
│  ├─ Equipments/equipments.php                       │
│  ├─ Drivers/deactivate_driver_modals.html          │
│  └─ Equipments/deactivate_equipment_modals.html    │
├─────────────────────────────────────────────────────┤
│  المستوى 2: طبقة معالجة الطلبات (Handler Layer)   │
│  ├─ Drivers/deactivate_driver_handler.php          │
│  ├─ Equipments/deactivate_equipment_handler.php    │
│  └─ Approvals/approval_api.php                     │
├─────────────────────────────────────────────────────┤
│  المستوى 3: طبقة الموافقات (Workflow Layer)       │
│  └─ includes/approval_workflow.php                 │
├─────────────────────────────────────────────────────┤
│  المستوى 4: قاعدة البيانات (Database Layer)      │
│  ├─ approval_requests                              │
│  ├─ approval_steps                                 │
│  └─ approval_workflow_rules                        │
└─────────────────────────────────────────────────────┘
```

### الملفات الأساسية:

| الملف | الوصف | النوع |
|------|-------|-------|
| [includes/approval_workflow.php](includes/approval_workflow.php) | مكتبة الدوال الأساسية للموافقات | PHP Library |
| [Approvals/approval_api.php](Approvals/approval_api.php) | واجهة API للطلبات والموافقات | API Handler |
| [Approvals/requests.php](Approvals/requests.php) | صفحة عرض و إدارة الطلبات | UI Page |
| [Drivers/deactivate_driver_handler.php](Drivers/deactivate_driver_handler.php) | معالج طلبات المشغلين | Handler |
| [Equipments/deactivate_equipment_handler.php](Equipments/deactivate_equipment_handler.php) | معالج طلبات الآليات | Handler |

---

## 🗄️ قاعدة البيانات

### جدول approval_requests (طلبات الموافقات)

```sql
CREATE TABLE approval_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,           -- معرّف فريد للطلب
    entity_type VARCHAR(50) NOT NULL,            -- نوع الكيان (driver, equipment, contract...)
    entity_id INT NOT NULL,                      -- معرّف الكيان
    action VARCHAR(100) NOT NULL,                -- الإجراء المطلوب (deactivate_driver...)
    payload LONGTEXT NOT NULL,                   -- بيانات العملية (JSON)
    requested_by INT NOT NULL,                   -- معرّف المستخدم الطالب
    current_step INT DEFAULT 1,                  -- المرحلة الحالية للموافقة
    status ENUM('pending','approved','rejected') DEFAULT 'pending', -- حالة الطلب
    rejection_reason TEXT NULL,                  -- سبب الرفض إن وجد
    approved_at DATETIME NULL,                   -- وقت الموافقة النهائية
    rejected_at DATETIME NULL,                   -- وقت الرفض
    executed_at DATETIME NULL,                   -- وقت تنفيذ العملية
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  -- وقت الإنشاء
    updated_at DATETIME NULL,                    -- آخر تحديث
    INDEX idx_approval_entity (entity_type, entity_id),
    INDEX idx_approval_status (status),
    INDEX idx_approval_user (requested_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**الغرض:**
- تخزين جميع طلبات الموافقات
- تتبع حالة كل طلب
- حفظ البيانات المتعلقة بالعملية (payload)

**الحقول الرئيسية:**
- **payload**: يحتوي على بيانات العملية كاملة بصيغة JSON
  ```json
  {
    "summary": { ... },      // معلومات عامة عن العملية
    "operations": [ ... ]    // العمليات المراد تنفيذها على قاعدة البيانات
  }
  ```

---

### جدول approval_steps (مراحل الموافقة)

```sql
CREATE TABLE approval_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,          -- معرّف المرحلة
    request_id INT NOT NULL,                    -- معرّف الطلب
    role_required VARCHAR(100) NOT NULL,        -- الأدوار المطلوبة (مثل: 3,4,-1)
    step_order INT NOT NULL,                    -- ترتيب المرحلة
    approved_by INT NULL,                       -- معرّف الموافق
    approved_at DATETIME NULL,                  -- وقت الموافقة
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    note TEXT NULL,                             -- ملاحظات الموافق/الرافض
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_approval_steps_request
        FOREIGN KEY (request_id) REFERENCES approval_requests(id)
        ON DELETE CASCADE,
    INDEX idx_approval_steps_request (request_id),
    INDEX idx_approval_steps_status (status),
    INDEX idx_approval_steps_order (step_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**الغرض:**
- تخزين مراحل الموافقة الفردية
- تتبع من وافق على كل مرحلة ومتى
- السماح بموافقات متعددة المستويات

**مثال لسيناريو مشترك:**
- المرحلة 1: ينتظر موافقة الدور 3 (مدير المشغلين)
- بعد توافق الدور 3: ينتظر موافقة الدور -1 (المدير العام)

---

### جدول approval_workflow_rules (قواعد الموافقات)

```sql
CREATE TABLE approval_workflow_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,          -- معرّف القاعدة
    entity_type VARCHAR(50) NOT NULL,           -- نوع الكيان
    action VARCHAR(100) NOT NULL,               -- الإجراء
    role_required VARCHAR(100) NOT NULL,        -- الأدوار المطلوبة (CSV)
    step_order INT NOT NULL,                    -- ترتيب المرحلة
    is_active TINYINT(1) NOT NULL DEFAULT 1,    -- هل القاعدة نشطة؟
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_workflow_rule (entity_type, action, step_order),
    INDEX idx_workflow_rule_lookup (entity_type, action, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**الغرض:**
- تعريف مراحل الموافقة لكل نوع عملية
- السماح بتغيير القواعد دون تعديل البرنامج

**مثال من البيانات الأساسية:**

| entity_type | action | role_required | step_order | is_active |
|-------------|--------|---------------|-----------|-----------|
| driver | deactivate_driver | 3,-1 | 1 | 1 |
| equipment | deactivate_equipment | 4,-1 | 1 | 1 |
| contract | renewal | 1,-1 | 1 | 1 |
| contract | merge | -1 | 1 | 1 |

**شرح role_required:**
- `3,-1`: الدور 3 (مدير المشغلين) أو الدور -1 (المدير العام) يمكنهم الموافقة
- `-1`: فقط المدير العام يمكنه الموافقة

---

## 🔧 الدوال الأساسية

### 📌 دالة: approval_create_request()

**الموقع:** [includes/approval_workflow.php](includes/approval_workflow.php)

**التوقيع:**
```php
approval_create_request(
    $entity_type,    // 'driver', 'equipment', 'contract'...
    $entity_id,      // معرّف الكيان
    $action,         // 'deactivate_driver', 'reactivate_driver'...
    $payload,        // مصفوفة البيانات
    $requested_by,   // معرّف المستخدم الطالب
    $conn            // اتصال قاعدة البيانات
)
```

**الخطوات الداخلية:**

```
1. التحقق من صحة المدخلات
2. بدء معاملة قاعدة البيانات (Transaction)
3. التحقق من عدم وجود طلب مشابه معلق مسبقاً
4. إنشاء سجل جديد في جدول approval_requests
5. الحصول على مراحل الموافقة من approval_workflow_rules
6. إنشاء سجلات في جدول approval_steps لكل مرحلة
7. اختبار ما إذا كان منشئ الطلب يمكنه الموافقة على المرحلة الأولى
   - إذا نعم: موافقة آلية ومتابعة المراحل التالية
   - إذا لا: انتظار موافقة صاحب الصلاحيات
8. تنفيذ العملية إذا تمت الموافقة على جميع المراحل
9. Commit أو Rollback المعاملة
```

**المخرجات:**
```php
[
    'success' => true/false,
    'message' => 'رسالة النتيجة',
    'request_id' => معرّف الطلب,
    'status' => 'pending' أو 'approved'
]
```

**أمثلة للاستخدام:**

```php
// مثال 1: طلب إيقاف مشغل
$payload = [
    'summary' => [
        'driver_id' => 5,
        'driver_name' => 'أحمد محمد',
        'new_status' => 'موقوف',
        'reason' => 'غياب متكرر'
    ],
    'operations' => [
        [
            'db_action' => 'update',
            'table' => 'drivers',
            'where' => ['id' => 5],
            'data' => ['driver_status' => 'موقوف']
        ]
    ]
];

$result = approval_create_request(
    'driver',
    5,
    'deactivate_driver',
    $payload,
    $current_user_id,
    $conn
);

// مثال 2: طلب إيقاف آلية
$payload = [
    'summary' => [
        'equipment_id' => 12,
        'equipment_code' => 'EQ001',
        'new_status' => 'موقوفة للصيانة',
        'reason' => 'صيانة دورية'
    ],
    'operations' => [
        [
            'db_action' => 'update',
            'table' => 'equipments',
            'where' => ['id' => 12],
            'data' => ['availability_status' => 'موقوفة للصيانة']
        ]
    ]
];

$result = approval_create_request(
    'equipment',
    12,
    'deactivate_equipment',
    $payload,
    $current_user_id,
    $conn
);
```

---

### 📌 دالة: approval_approve_request()

**الموقع:** [includes/approval_workflow.php](includes/approval_workflow.php)

**التوقيع:**
```php
approval_approve_request(
    $request_id,      // معرّف الطلب المراد الموافقة عليه
    $approved_by,     // معرّف المستخدم الموافق
    $conn,            // اتصال قاعدة البيانات
    $note = ''        // ملاحظات اختيارية
)
```

**الخطوات الداخلية:**

```
1. التحقق من المدخلات
2. بدء معاملة قاعدة البيانات
3. التحقق من وجود الطلب وحالته (يجب أن يكون 'pending')
4. الحصول على المرحلة المعلقة التالية
5. التحقق من أن المستخدم لديه صلاحيات لهذه المرحلة
6. تطبيق الموافقة على المرحلة
7. محاولة إنهاء الطلب إذا تمت الموافقة على جميع المراحل
8. تنفيذ العمليات إذا كانت هذه هي المرحلة الأخيرة
9. Commit أو Rollback
```

**المخرجات:**
```php
[
    'success' => true/false,
    'message' => 'رسالة النتيجة',
    'request_status' => 'pending' أو 'approved'
]
```

**مثال:**
```php
$result = approval_approve_request(
    $request_id = 42,
    $approved_by = $_SESSION['user']['id'],
    $conn,
    $note = 'تم المراجعة والموافقة - لا توجد مشاكل'
);

if ($result['success']) {
    if ($result['request_status'] === 'approved') {
        echo "تم الاعتماد النهائي وتنفيذ العملية";
    } else {
        echo "تمت الموافقة على هذه المرحلة. بانتظار موافقة أخرى";
    }
}
```

---

### 📌 دالة: approval_reject_request()

**الموقع:** [includes/approval_workflow.php](includes/approval_workflow.php)

**التوقيع:**
```php
approval_reject_request(
    $request_id,      // معرّف الطلب المراد رفضه
    $rejected_by,     // معرّف المستخدم الرافض
    $conn,            // اتصال قاعدة البيانات
    $reason = ''      // سبب الرفض
)
```

**الخطوات الداخلية:**

```
1. التحقق من المدخلات
2. بدء معاملة قاعدة البيانات
3. التحقق من وجود الطلب وحالته (يجب أن يكون 'pending')
4. الحصول على المرحلة المعلقة
5. التحقق من صلاحيات المستخدم
6. تسجيل الرفض في جدول approval_steps
7. تحديث حالة الطلب إلى 'rejected' في approval_requests
8. حفظ سبب الرفض
9. Commit أو Rollback
```

**المخرجات:**
```php
[
    'success' => true/false,
    'message' => 'تم رفض الطلب بنجاح أو رسالة خطأ'
]
```

---

### 📌 دالة: approval_finalize_if_completed()

**الموقع:** [includes/approval_workflow.php](includes/approval_workflow.php)

**الغرض:** التحقق من إنهاء جميع المراحل وتنفيذ العملية تلقائياً

**الخطوات:**
```
1. التحقق من عدم وجود مراحل معلقة أخرى
2. إذا كانت هناك مراحل معلقة:
   - تحديث الطلب بالمرحلة التالية
   - في انتظار الموافقة عليها
3. إذا انتهت جميع المراحل:
   - استدعاء approval_execute_payload() لتنفيذ العمليات
   - تحديث حالة الطلب إلى 'approved'
   - تسجيل وقت التنفيذ
```

---

### 📌 دالة: approval_execute_payload()

**الموقع:** [includes/approval_workflow.php](includes/approval_workflow.php)

**الغرض:** تنفيذ العمليات المخزنة في payload الطلب

**العمليات المدعومة:**

1. **UPDATE** - تحديث تسجيلات موجودة
   ```php
   [
       'db_action' => 'update',
       'table' => 'drivers',
       'where' => ['id' => 5],
       'data' => ['driver_status' => 'موقوف']
   ]
   ```

2. **INSERT** - إنشاء تسجيلات جديدة
   ```php
   [
       'db_action' => 'insert',
       'table' => 'audit_log',
       'data' => [
           'action' => 'driver_deactivated',
           'driver_id' => 5,
           'timestamp' => date('Y-m-d H:i:s')
       ]
   ]
   ```

3. **DELETE** - حذف تسجيلات
   ```php
   [
       'db_action' => 'delete',
       'table' => 'temporary_records',
       'where' => ['id' => 123]
   ]
   ```

---

### 📌 دالة: approval_get_workflow_rules()

**الموقع:** [includes/approval_workflow.php](includes/approval_workflow.php)

**التوقيع:**
```php
approval_get_workflow_rules($entity_type, $action, $conn)
```

**الغرض:** الحصول على مراحل الموافقة لنوع عملية معينة

**المنطق:**
1. البحث في جدول `approval_workflow_rules` 
2. إذا لم تُوجد: استخدام قيم افتراضية (fallback values)
3. ترجيع مصفوفة المراحل بترتيب تصاعدي

**مثال:**
```php
$rules = approval_get_workflow_rules('driver', 'deactivate_driver', $conn);

// النتيجة:
// [
//     [
//         'role_required' => '3,-1',  // دور 3 أو -1
//         'step_order' => 1
//     ]
// ]
```

---

### 📌 دوال مساعدة

#### approval_user_can_match_role()
التحقق من امتلاك المستخدم للدور المطلوب
```php
if (approval_user_can_match_role('3,4,-1', $_SESSION['user']['role'])) {
    // المستخدم يمتلك الدور 3 أو 4 أو -1
    echo "لديك الصلاحيات";
}
```

#### approval_build_simple_update_payload()
بناء payload بسيط لتحديث جدول
```php
$payload = approval_build_simple_update_payload(
    'drivers',
    ['id' => 5],
    ['driver_status' => 'موقوف'],
    ['driver_status' => 'نشط']  // البيانات القديمة اختيارية
);
```

#### approval_build_simple_delete_payload()
بناء payload بسيط للحذف
```php
$payload = approval_build_simple_delete_payload(
    'table_name',
    ['id' => 123],
    ['name' => 'old_value']  // البيانات المحذوفة
);
```

---

## 📊 دورة حياة الطلب

### السيناريو 1: طلب بمرحلة واحدة (مدير عام مباشرة)

```
┌──────────────────────────────────────────────────────┐
│ 1. المستخدم يقدم طلب إيقاف مشغل                    │
│ (POST إلى deactivate_driver_handler.php)              │
└──────────────────────────────────────────────────────┘
                    ↓
┌──────────────────────────────────────────────────────┐
│ 2. CreateRequest ينشئ طلب ومراحل موافقة             │
│ ملاحظة: المرحلة الأولى مطلوبة من الدور 3 أو -1     │
└──────────────────────────────────────────────────────┘
                    ↓
         ┌──────────────┴──────────────┐
         ↓                             ↓
    [الحالة A]                   [الحالة B]
    المستخدم              المستخدم يملك
    لا يملك                دور 3 أو -1
    صلاحيات                (موافقة مباشرة)
         │                            │
         ↓                            ↓
    الطلب معلق              موافقة تلقائية
    في انتظار             على المرحلة
    مدير المشغلين          ↓
    أو المدير              تنفيذ العملية
    العام                  ↓
    │                  الطلب = approved
    │                  تم التحديث
    ↓
[انتظار الموافقة]
    │
    ├─ [إذا وافق] → [تنفيذ] → status=approved
    └─ [إذا رفض] → [إنهاء] → status=rejected
```

---

### السيناريو 2: طلب بمراحل متعددة

```
المستخدم ينشئ طلب
(مثال: تجديد عقد يحتاج موافقة من دور 1 ثم دور -1)
    ↓
[المرحلة 1: Pending]
منتظرة موافقة الدور 1
    ├─ دور 1 يوافق → [المرحلة 1: Approved]
    └─ دور 1 يرفض → [الطلب: Rejected]
    ↓
[المرحلة 2: Pending]
منتظرة موافقة الدور -1
    ├─ دور -1 يوافق → [المرحلة 2: Approved]
    │                           ↓
    │                        تنفيذ جميع
    │                        العمليات
    │                           ↓
    │                    الطلب: Approved
    │
    └─ دور -1 يرفض → [الطلب: Rejected]
```

---

### مثال واقعي: تتبع طلب إيقاف مشغل

```
الموقت: 2026-03-04 14:30:00
المستخدم: محمد (Role 10 - مدير حركة/تشغيل)
الطلب: إيقاف المشغل أحمد محمد (ID=5)

──────────────────────────────────────────────────────

[14:30:00] محمد ينقر على زر "إيقاف"
          ↓ يفتح نموذج deactivateDriverModal
          ↓ يملأ السبب والتاريخ
          ↓ ينقر "إيقاف المشغل"
          ↓ AJAX POST إلى deactivate_driver_handler.php

[14:30:02] approval_create_request('driver', 5, 'deactivate_driver', {...})
          ↓ INSERT approval_requests: id=42, status=pending
          ↓ INSERT approval_steps: request_id=42, role_required='3,-1', status=pending
          ↓ التحقق: هل محمد (Role 10) يملك دور 3؟ لا
          ↓ ينتظر موافقة مدير المشغلين (Role 3)
          ↓ return {success: true, request_id: 42, status: pending}

[14:30:03] ظهور رسالة: "تم تقديم الطلب في انتظار موافقة مدير المشغلين"

──────────────────────────────────────────────────────

[15:45:00] علي (Role 3 - مدير مشغلين) يدخل صفحة Approvals/requests.php
          ↓ يرى طلب معلق للحالة: إيقاف مشغل
          ↓ يضغط "عرض" لمشاهدة التفاصيل
          ↓ يضغط "موافقة"
          ↓ AJAX POST إلى approval_api.php
             {api_action: 'approve', request_id: 42, note: 'الموافقة مصرح بها'}

[15:45:02] approval_approve_request(42, علي_id, conn, 'note...')
          ↓ بدء معاملة قاعدة البيانات
          ↓ UPDATE approval_steps SET status='approved', approved_by=علي_id, ...
          ↓ finalize_if_completed(42, conn)
          ↓ التحقق: هل بقيت مراحل معلقة؟ لا
          ↓ تنفيذ العمليات:
             UPDATE drivers SET driver_status='موقوف' WHERE id=5
          ↓ UPDATE approval_requests SET status='approved', executed_at=NOW()
          ↓ Commit المعاملة
          ↓ return {success: true, message: 'تم الاعتماد والتنفيذ'}

[15:45:03] ظهور رسالة: "تم اعتماد الطلب وتنفيذه بنجاح"
          ↓ جدول الطلبات يُحدّث الحالة إلى ✓ معتمد

[15:45:05] في صفحات Drivers/drivers.php
          ↓ حالة المشغل أحمد = "موقوف"
          ↓ الزر يتغير من "إيقاف" إلى "تفعيل"
          ↓ يمكن الآن تقديم طلب إعادة التفعيل
```

---

## ⚡ الميزات المتقدمة

### 1. الموافقة التلقائية (Auto-Approval)

عندما ينشئ المستخدم طلباً، النظام يتحقق:
```php
// هل يملك المستخدم صلاحية إعتماد المرحلة الأولى؟
if (approval_user_can_match_role($first_step['role_required'], $user_role)) {
    // موافقة تلقائية!
    // تطبيق الموافقة على المرحلة الأولى
    // المتابعة إلى المراحل التالية
}
```

**الفائدة:** تسريع العمليات عندما يكون لدى المستخدم الصلاحيات الكافية

**مثال:** إذا كان المدير العام (Role -1) يقدم طلب إيقاف:
- الطلب ينتظر موافقة (3,-1)
- المدير العام يملك الدور -1
- الموافقة تطبق تلقائياً على الفور

---

### 2. معاملات قاعدة البيانات (Transactions)

جميع العمليات الحساسة مغطاة بـ transactions:

```php
approval_db_begin($conn);  // START TRANSACTION
try {
    // 1. إنشاء الطلب
    // 2. إنشاء المراحل
    // 3. التحقق من الموافقات
    // 4. تنفيذ العمليات (إن وجدت)
    approval_db_commit($conn);  // COMMIT
} catch (Exception $e) {
    approval_db_rollback($conn);  // ROLLBACK
}
```

**الفائدة:** ضمان عدم تطبيق تحديثات جزئية

---

### 3. Payload المرن

يتم حفظ البيانات الكاملة للعملية:

```json
{
  "summary": {
    "driver_id": 5,
    "driver_name": "أحمد محمد",
    "old_status": "نشط",
    "new_status": "موقوف",
    "reason": "غياب متكرر",
    "date": "2026-03-04"
  },
  "operations": [
    {
      "db_action": "update",
      "table": "drivers",
      "where": {"id": 5},
      "data": {"driver_status": "موقوف"}
    }
  ]
}
```

**الفائدة:**
- تتبع تاريخي شامل
- إمكانية عكس العملية مستقبلاً
- المرونة في إضافة عمليات معقدة

---

### 4. التحقق من التكرار

التحقق من عدم وجود طلب مشابه معلق:

```php
SELECT id FROM approval_requests
WHERE entity_type = 'driver'
  AND entity_id = 5
  AND action = 'deactivate_driver'
  AND status = 'pending'
LIMIT 1;
```

**الفائدة:** منع طلبات معلقة مكررة

---

### 5. النظام الخاضع للقواعد (Rule-Based System)

تغيير مراحل الموافقة دون تعديل الكود:

```sql
-- مثال: إضافة مرحلة جديدة
INSERT INTO approval_workflow_rules (entity_type, action, role_required, step_order)
VALUES ('driver', 'deactivate_driver', '3', 1),
       ('driver', 'deactivate_driver', '2', 2),  -- مرحلة جديدة
       ('driver', 'deactivate_driver', '-1', 3);  -- مرحلة ثالثة
```

**الفائدة:** مرونة في إدارة سيناريوهات الموافقة

---

## 📱 حالات الاستخدام

### استخدام 1: إيقاف مشغل

**الملفات:**
- [Drivers/drivers.php](Drivers/drivers.php) - عرض المشغلين
- [Drivers/deactivate_driver_modals.html](Drivers/deactivate_driver_modals.html) - النموذج
- [Drivers/deactivate_driver_handler.php](Drivers/deactivate_driver_handler.php) - المعالج

**خطوات الاستخدام:**

1. **مدير الحركة والتشغيل (Role 10) ينقر زر "إيقاف"**
   ```javascript
   // في deactivate_driver_modals.html
   $('.deactivateDriverBtn').click(function() {
       let driverId = $(this).data('id');
       $('#deactivateDriverId').val(driverId);
       // ... ملء النموذج
       $('#deactivateDriverModal').modal('show');
   });
   ```

2. **يملأ النموذج ويرسله**
   ```javascript
   $('#submitDeactivateDriverBtn').click(function() {
       $.ajax({
           url: '../Drivers/deactivate_driver_handler.php',
           method: 'POST',
           data: {
               action: 'deactivate_driver',
               driver_id: $('#deactivateDriverId').val(),
               deactivation_reason: $('#deactivateDriverReason').val(),
               deactivation_date: $('#deactivateDriverDate').val()
           },
           // ...
       });
   });
   ```

3. **معالج deactivate_driver_handler.php يستقبل الطلب**
   - يتحقق من التصاريح (Role 10)
   - ينشئ payload الطلب
   - يستدعي `approval_create_request()`

4. **مدير المشغلين (Role 3) يوافق**
   - يدخل [Approvals/requests.php](Approvals/requests.php)
   - يرى الطلب المعلق
   - ينقر "موافقة"
   - يتم تنفيذ الإيقاف

---

### استخدام 2: إيقاف آلية

**نفس التسلسل كما في الاستخدام 1 لكن:**
- **الدور الطالب:** Role 10 (مدير الحركة والتشغيل)
- **الدور الموافق:** Role 4 (مدير الأسطول)
- **الملفات:** في مجلد [Equipments/](Equipments/)
- **الجدول المتأثر:** `equipments.availability_status`

---

### استخدام 3: صفحة إدارة الطلبات

**الملف:** [Approvals/requests.php](Approvals/requests.php)

**الميزات:**
- عرض جميع الطلبات مع فلاتر
- عد الطلبات حسب الحالة
- إمكانية عرض التفاصيل
- الموافقة أو الرفض للمشغلين المخولين

**الفلاتر:**
```
✓ معلقة (pending)
✓ معتمدة (approved)
✓ مرفوضة (rejected)
✓ الكل (all)
```

---

## ⚠️ الأخطاء والمشاكل الشائعة

### ❌ المشكلة 1: طلب معلق مكرر

**الأعراض:**
```
الرسالة: "يوجد طلب معلق مسبقاً لنفس العملية"
```

**السبب:**
- المستخدم أرسل الطلب مرتين

**الحل:**
```php
// النظام يمنع هذا تلقائياً
SELECT id FROM approval_requests
WHERE entity_type = 'driver'
  AND entity_id = 5
  AND action = 'deactivate_driver'
  AND status = 'pending'
LIMIT 1;
```

---

### ❌ المشكلة 2: عدم ظهور طلب للموافقة

**الأعراض:**
- مدير المشغلين لا يرى الطلب في صفحة requests.php

**الأسباب المحتملة:**

1. **الدور غير صحيح:**
   ```php
   // التحقق
   SELECT role_required FROM approval_steps
   WHERE request_id = 42 AND status = 'pending';
   // إذا كانت النتيجة '3,-1' والمستخدم لديه دور 10
   // لن يرى الطلب
   ```

2. **الطلب تمت الموافقة عليه بالفعل:**
   ```php
   SELECT status FROM approval_requests WHERE id = 42;
   // إذا كانت النتيجة 'approved' أو 'rejected'
   // لن يظهر في الطلبات المعلقة
   ```

**الحل:**
- تحقق من الدور في جدول approval_steps
- تحقق من حالة الطلب

---

### ❌ المشكلة 3: رسالة "ليس لديك صلاحيات"

**الأعراض:**
```
الرسالة: "ليس لديك صلاحية لاعتماد هذه المرحلة"
```

**السبب:**
```php
// دوره لا يطابق role_required
'3,-1'        // المطلوب
'2'           // دور المستخدم ← ❌ لا يطابق
```

**الحل:**
- تأكد من أن الدور صحيح في جدول approval_steps
- أو تأكد من أن لديك الصلاحيات المطلوبة

---

### ❌ المشكلة 4: العملية لم تُنفذ بعد الموافقة

**الأعراض:**
- الطلب معتمد في approval_requests
- لكن الحالة لم تتغير في جدول drivers

**السبب المحتمل:**
- خطأ في تنفيذ العملية

**التحقق:**
```php
// تفعيل تسجيل الأخطاء
SELECT status, executed_at, error_log FROM approval_requests
WHERE id = 42;

// إذا كان error_log يحتوي رسالة خطأ:
// تتبع الخطأ في دالة approval_execute_db_operation()
```

---

### ❌ المشكلة 5: Rollback غير متوقع

**الأعراض:**
- الطلب كان جاهز للموافقة
- فجأة حدث Rollback

**الأسباب:**
1. خطأ في إنشاء مراحل الموافقة
2. خطأ في البيانات المدخلة
3. مشكلة في الاتصال بقاعدة البيانات

**التحقق:**
```php
// انظر إلى logs:
error_log('Approval rollback: ' . $exception->getMessage());
```

---

## 🔄 دورة حياة States

```
        ┌─────────────────────────────────┐
        │   approval_requests.status      │
        └─────────────────────────────────┘

          ┌────────────┬─────────┬────────────┐
          ↓            ↓         ↓            ↓
    ┌─────────┐   ┌─────────┐ ┌─────────┐ ┌─────────┐
    │ pending │ → │ approved│ │ rejected│ │rollback │
    └─────────┘   └─────────┘ └─────────┘ └─────────┘
          ↑                         ↑            ↑
          └─────────────────────────┴────────────┘
                   states cycle

  pending    → معلق (ينتظر موافقة)
  approved   → معتمد (تم الموافقة والتنفيذ)
  rejected   → مرفوض (تم رفضه، لم يُنفذ)
```

---

## 🗂️ التكامل مع الأنظمة الأخرى

### تكامل مع جدول Drivers

```php
// الحقل الأساسي: driver_status
VALUES ('نشط', 'موقوف', ...)

// عند الموافقة على الإيقاف:
UPDATE drivers SET driver_status = 'موقوف' WHERE id = ?
```

### تكامل مع جدول Equipments

```php
// الحقل الأساسي: availability_status
VALUES ('متاحة للعمل', 'موقوفة للصيانة', ...)

// عند الموافقة على الإيقاف:
UPDATE equipments SET availability_status = 'موقوفة للصيانة' WHERE id = ?
```

---

## 📚 ملخص الجداول

| الجدول | الغرض | التفاعل |
|-------|-------|---------|
| approval_requests | تخزين الطلبات الرئيسية | المرجع الأساسي |
| approval_steps | تخزين المراحل | يُحقق على مرحلة واحدة في كل مرة |
| approval_workflow_rules | تعريف القواعد | يُحمّل عند إنشاء طلب |
| drivers | بيانات المشغلين | يُحدّث عند الموافقة على الإيقاف |
| equipments | بيانات الآليات | يُحدّث عند الموافقة على الإيقاف |

---

## 🎓 الدروس المستفادة

1. **الفصل بين الشواغل (Separation of Concerns):**
   - طبقة UI منفصلة عن طبقة الموافقات
   - دوال مساعدة معاد استخدامها

2. **الأمان:**
   - تحقق من التصاريح في كل خطوة
   - معاملات قاعدة البيانات (Transactions)
   - التحقق من المدخلات (Input Validation)

3. **المرونة:**
   - قواعد Workflow قابلة للتعديل
   - Payload المرن يدعم عمليات معقدة

4. **قابلية الصيانة:**
   - تتبع شامل لكل طلب
   - رسائل خطأ واضحة
   - توثيق جيد

---

## 🚀 الخطوات التالية للتطوير

1. **إضافة عمليات جديدة:**
   - تحديد entity_type وaction
   - إضافة قواعد في approval_workflow_rules
   - إنشاء handler ونموذج

2. **تحسينات الأداء:**
   - إضافة indexes على approval_requests.entity_type
   - Caching للقواعد المستخدمة كثيراً

3. **واجهة إدارة:**
   - صفحة لإدارة قواعد الموافقات
   - تقارير عن الموافقات
   - رسوم بيانية للإحصائيات

---

## 📞 الأسئلة الشائعة

**س: هل يمكن إضافة مراحل موافقة أكثر من واحدة دون تعديل الكود؟**
الجواب: نعم! عن طريق إضافة صفوف في جدول approval_workflow_rules

**س: ماذا يحدث إذا رفض أحدهم الطلب؟**
الجواب: يتحول الطلب إلى rejected والعملية لا تُنفذ

**س: هل يمكن تعديل طلب معلق؟**
الجواب: حالياً لا، يجب رفضه وتقديم طلب جديد

**س: من يرى جميع الطلبات؟**
الجواب: المدير العام (Role -1) يرى الجميع، والآخرون يرون طلباتهم والطلبات التي ينتظرون موافقتهم عليها

---

## 📖 المراجع

- [approval_workflow.php](includes/approval_workflow.php) - مكتبة الدوال
- [approval_api.php](Approvals/approval_api.php) - واجهة API
- [approval_workflow.sql](database/approval_workflow.sql) - الجداول
- [requests.php](Approvals/requests.php) - صفحة الإدارة

---

**آخر تحديث:** 2026-03-04  
**الإصدار:** 1.0  
**الحالة:** ✅ نشط وعامل

