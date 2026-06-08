# EMS · طبقة REST API لمدير الحركة والتشغيل

طبقة API مبنية على PHP فوق نفس قاعدة بيانات نظام EMS، تغطّي شاشتي **اللوحة الحيّة** (مكافئ `movement/map_page.php`) و**الحركة والتشغيل الموحّدة** (مكافئ `movement/movement_operations.php`)، مخصّصة لتطبيق Flutter لدور مدير الحركة والتشغيل.

- **معزولة بالكامل** في مجلد `api/` ولا تمسّ أي صفحة في النظام الويب القائم.
- **مصادقة Token (Bearer)** بدل الجلسة/الكوكيز و CSRF (مناسبة للجوال).
- كل الردود JSON موحّدة، وكل الكتابات عبر **Prepared Statements**.
- **عزل صارم**: كل طلب مقيّد بشركة المستخدم ومشروعه (إلا السوبر أدمن `role = -1`).

---

## نقطة الدخول والمسارات

المتحكّم الأمامي هو `api/index.php`، ويُوجَّه عبر `api/.htaccess` (mod_rewrite). الجذر الأساسي:

```
http://<host>/ems/api/<resource>[/<id>]
```

> إن لم يتوفّر mod_rewrite، تعمل المسارات أيضاً عبر `index.php?route=<resource>/<id>`.

## الردّ الموحّد

كل استجابة بالشكل التالي مع `Content-Type: application/json; charset=utf-8` و `JSON_UNESCAPED_UNICODE`:

```json
{ "success": true, "message": "رسالة بالعربية", "data": { } }
```

## المصادقة

أرسل التوكن في كل طلب محمي:

```
Authorization: Bearer <token>
```

يُصدَر التوكن من `POST /api/login`، ويُخزَّن في جدول `api_tokens` كـ sha256 (لا يُخزَّن خاماً). صلاحيته الافتراضية 30 يوماً.

## أكواد HTTP

| الكود | المعنى |
|------|--------|
| 200 / 201 | نجاح |
| 400 | إدخال خاطئ |
| 401 | غير مصرّح (توكن مفقود/غير صالح/منتهٍ) |
| 403 | لا صلاحية / حساب أو شركة غير نشطة |
| 404 | غير موجود |
| 405 | الطريقة غير مسموحة لهذا المسار |
| 409 | تعارض قاعدة عمل (تشغيل مزدوج / معدة غير مشغّلة) |
| 422 | فشل تحقّق (تواريخ/قيم) |
| 500 | خطأ خادم (يُسجَّل في `logs/api.log`) |

---

# نقاط النهاية (Endpoints)

## المصادقة والسياق

### `POST /api/login`
مدخلات (JSON أو form): `username`, `password`.

```bash
curl -X POST http://localhost/ems/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"...","password":"..."}'
```

استجابة 200:
```json
{
  "success": true,
  "message": "تم تسجيل الدخول بنجاح ✅",
  "data": {
    "token": "<64-hex>",
    "expires_at": "2026-07-06 12:00:00",
    "user": { "id": 11, "name": "...", "username": "...", "phone": "...", "role": "6", "company_id": 4, "project_id": 4 },
    "project": { "id": 4, "name": "...", "project_code": "...", "location": "...", "client": "...", "latitude": 12.0, "longitude": 31.0 }
  }
}
```
أخطاء: 400 (نقص بيانات)، 401 (بيانات خاطئة)، 403 (حساب/شركة غير نشطة).

### `POST /api/logout`
يبطل التوكن الحالي. يتطلب `Authorization`. يعيد `{success:true}`.

### `GET /api/me`
يعيد سياق المستخدم والمشروع (للاستئناف بعد فتح التطبيق). نفس بنية `user`/`project` في login.

---

## اللوحة الحيّة

### `GET /api/board`
مكافئ `map_page.php` — يجمّع المعدّات السارية حسب المورّد، ويحسب الساعات من التايم شيت.

```bash
curl http://localhost/ems/api/board -H "Authorization: Bearer <token>"
```

`data`:
```json
{
  "project": { "id": 4, "name": "...", "project_code": "...", "location": "...", "latitude": 12.0, "longitude": 31.0 },
  "stats": { "suppliers": 4, "equipment_total": 8, "equipment_working": 6, "equipment_stopped": 2, "operators": 11 },
  "suppliers": [
    {
      "supplier_id": 1, "supplier_name": "...",
      "totals": { "total": 3, "working": 3, "stopped": 0, "operators": 6 },
      "equipments": [
        {
          "op_id": 12, "eq_id": 8, "code": "EX22", "name": "...", "type_name": "حفار",
          "is_working": true, "availability_status": "قيد الاستخدام",
          "manufacturer": "HYUNDAI", "model": "HX340SL", "serial_number": "...", "chassis_number": "...",
          "equipment_category": "أساسي", "ts_total": 104.0, "ts_today": 0.0,
          "drivers": [
            { "driver_id": 4, "name": "...", "driver_code": "DR22", "phone": "...",
              "skill_level": "...", "license_type": "...", "years_in_field": 5, "years_on_equipment": 1 }
          ]
        }
      ]
    }
  ]
}
```
ملاحظة الإحداثيات: جدول `equipments` بلا إحداثيات، فدبابيس الخريطة تستخدم إحداثيات المشروع.

---

## الحركة والتشغيل الموحّدة

### `GET /api/operations`
مكافئ `movement_operations.php` — يعيد التشغيلات مقسّمة جاهزة لجدولي النهار/الليل + بيانات الخريطة + الصلاحيات.

`data`:
```json
{
  "project": { ... },
  "permissions": { "can_view": true, "can_add": true, "can_edit": true },
  "day":   [ <op> ... ],   // التشغيلات بوردية D أو B
  "night": [ <op> ... ],   // التشغيلات بوردية N أو B
  "map":   [ { "equipment": "EQ1001 - ...", "type": "حفار", "drivers": 2, "shift": "B", "lat": 12.0, "lng": 31.0 } ]
}
```
بنية `<op>`:
```json
{
  "op_id": 13, "equipment_id": 10, "equipment_code": "EQ1001", "equipment_name": "...",
  "equipment_type_name": "حفار", "supplier_name": "...", "equipment_category": "أساسي",
  "shift_type": "B", "status": 1, "start": "2025-12-01", "end": "2026-11-01",
  "total_equipment_hours": 20.0, "shift_hours": 10.0, "active_drivers_count": 2,
  "drivers": [
    { "rel_id": 20, "driver_id": 9, "driver_name": "...", "driver_phone": "...",
      "shift_type": "B", "start_date": "2026-05-19", "end_date": "", "status": 1 }
  ]
}
```
`end_date`/`end` الفارغ يعني «مستمر» (القيمة `2099-12-31` تُحوَّل تلقائياً إلى نص فارغ).

### `POST /api/operations` — إضافة تشغيل
يتطلب `can_edit`. مدخلات:
`equipment` (int), `equipment_type` (int), `contract_id` (int), `supplier_id` (int), `equipment_category` (أساسي/احتياطي), `shift_type` (D/N/B), `start` (Y-m-d), `end` (Y-m-d، اختياري), `total_equipment_hours` (float), `shift_hours` (float), `status` (1/0).

القاعدة الصارمة: **يُمنع** إضافة معدة لها تشغيل ساري آخر → 409. نجاح: 201 مع `{op_id}`.

### `PUT /api/operations/{op_id}` — تعديل/إنهاء تشغيل
يتطلب `can_edit`. مدخلات: `equipment_category`, `shift_type`, `status` (1/0)، و(اختياري) `start`, `end`. `status=0` يعني **إنهاء**. مقيّد بمشروع المستخدم وشركته. تحقّق: التواريخ Y-m-d والنهاية بعد البداية.

### `POST /api/equipment-drivers` — إضافة سائق لمعدة
يتطلب `can_edit`. مدخلات: `driver_id` (int), `equipment_id` (int), `shift_type` (D/N/B), `start_date` (افتراضي اليوم), `end_date` (فارغ=2099-12-31).

- المعدة يجب أن تكون مشغّلة في تشغيل ساري ضمن المشروع، وإلا 409.
- **القاعدة الصارمة**: تُنهى تلقائياً تعيينات هذا السائق السارية السابقة. نجاح: 201 مع `{rel_id}`.

### `PUT /api/equipment-drivers/{rel_id}` — تعديل/إنهاء تعيين سائق
يتطلب `can_edit`. مدخلات: `shift_type`, `start_date`, `end_date` (فارغ→2099-12-31), `status` (1/0). `status=0` يعني **إنهاء**.

---

## قوائم مساعدة (لنماذج الإضافة)

| المسار | الوصف | عناصر `data` |
|--------|-------|--------------|
| `GET /api/contracts` | عقود المشروع النشطة (الأحدث أولاً) | `contracts[]`: `{id, contract_signing_date, label}` |
| `GET /api/suppliers` | موردو الشركة النشطون | `suppliers[]`: `{id, name}` |
| `GET /api/equipment-types` | أنواع المعدات النشطة | `equipment_types[]`: `{id, type}` |
| `GET /api/equipments?type=&supplier=` | المعدات المتاحة (بلا تشغيل ساري) | `equipments[]`: `{id, code, name, type_id, type_name, supplier_id, supplier_name}` |
| `GET /api/drivers/available?equipment_id=` | السائقون المتاحون (نشطون، ضمن المشروع/الشركة، مستثنى أصحاب التعيين الساري) | `drivers[]`: `{id, name, phone, driver_code, skill_level}` |

---

# تطبيق مدير الموقع — التايم شيت + المزامنة (Offline-First)

نقاط مخصّصة لدور **مدير الموقع (الدور 5)** لتوثيق ساعات عمل المعدات. **العزل بالمشروع
مفروض على كل المسارات بما فيها تفاصيل السجل المفرد** (تصحيح لسلوك الشاشة الويب القديمة).

## بيانات مرجعية (تُخزَّن محلياً للعمل offline)

### `GET /api/timesheet/refdata`
حزمة مرجعية كاملة لمشروع المستخدم تكفي للعمل دون اتصال. `data`:
```json
{
  "server_time": "2026-06-07 18:00:00",
  "project": { "id": 4, "name": "...", ... },
  "operations": [ { "operation_id", "equipment_id", "code", "name", "equipment_type_id",
                    "type_form": 1, "type_name": "...", "shift_type": "B",
                    "shift_hours": 10.0, "allowed_shifts": ["D","N"] } ],
  "equipment_drivers": [ { "equipment_id", "driver_id", "shift_type" } ],
  "drivers": [ { "id", "name", "phone", "driver_code" } ],
  "equipment_types": [ { "id", "form", "type" } ],
  "contracts": [ { "id", "contract_signing_date" } ],
  "failure_codes": [ { "id", "equipment_type", "event_type_code", "event_type_name",
                       "main_category_code", "main_category_name", "sub_category",
                       "failure_detail", "full_code" } ]   // الشجرة الرباعية كاملة
}
```
> `type_form` (1/2/3) هو نوع الكشف (حفّار/قلّاب/خرّامة) المشتقّ من `equipments_types.form`.

### `GET /api/operations/by-type?type=1|2|3&shift=D|N`
`data.operations[]`: `{ operation_id, code, name }` (مكافئ get_operations.php).

### `GET /api/operations/{operation_id}/drivers?shift=D|N`
`data.drivers[]`: `{ driver_id, name, phone }` (مكافئ get_drivers.php).

### `GET /api/operations/{operation_id}/contract-hours`
`data`: `{ shift_hours, shift_type, allowed_shifts }`.

### `GET /api/failure-codes[?equipment_type=1|2|3]`
`data.failure_codes[]`: نفس بنية refdata. يبني التطبيق الشجرة الرباعية محلياً.

## سجلات التايم شيت

### `GET /api/timesheets?type=&operation_id=&driver_id=&shift=&date=&start_date=&end_date=&month=&status=`
JSON خام لسجلات مشروع المستخدم (≤500) + إحصائيات. `data`:
```json
{
  "stats": { "executed": 7.0, "standby": 1.0, "faults": 3.0, "total_work": 8.0 },
  "count": 1,
  "timesheets": [ { "id", "operation_id", "driver_id", "driver_name", "equipment_code",
                    "equipment_name", "type", "type_name", "shift", "date", "status",
                    "shift_hours", "executed_hours", "bucket_hours", "jackhammer_hours",
                    "total_work_hours", "total_fault_hours", "meters_count", "counter_diff",
                    "fault_count", "client_uuid", "updated_at", ... } ]
}
```

### `GET /api/timesheets/{id}`
سجل واحد ضمن المشروع (404 إن خارجه) + `failures[]` المصنّفة.

### `POST /api/timesheets` · `PUT /api/timesheets/{id}` · `DELETE /api/timesheets/{id}`
المدخلات (حسب النوع): `type` (1/2/3)، `operator` (operation_id)، `driver`، `shift` (D/N)، `date`،
`bucket_hours`/`jackhammer_hours` (حفّار)، `executed_hours` (قلّاب/خرّامة)، `standby_hours`،
`dependence_hours`، `extra_hours`، حقول العدّاد (start/end_hours/minutes/seconds)،
`tons_count`/`trips_count`/`transport_type` (قلّاب)، `drilling_holes_count`/`drilling_depth`/`meters_type` (خرّامة)،
توزيع الأعطال `hr_fault`/`maintenance_fault`/`marketing_fault`/`approval_fault`/`other_fault_hours`،
الملاحظات، و`fault_items[]` (`[{failure_code_id}]`).

**يُحسب خادمياً (لا يُوثق بالعميل):** `executed_hours` (حفّار)، `total_work_hours`، `total_fault_hours`،
`operator_standby_hours`، `machine_standby_hours`، `meters_count` (خرّامة)، `counter_diff`، و`shift_hours` (من التشغيل).

**القواعد:** المعدة/التشغيل ضمن مشروع المستخدم فقط؛ الوردية ضمن `allowed_shifts`؛
**تحقّق الأعطال** — إن كان `total_fault_hours > 0` فمجموع الجهات الخمس = إجماليه بالضبط وإلا **422**؛
الحالة تُنشأ «تحت المراجعة» (1)؛ الأعطال تُحفظ في `timesheet_failure_hours` داخل Transaction.

## المزامنة (Sync)

### `POST /api/sync/timesheets` — رفع دفعي (idempotent)
المدخل:
```json
{ "items": [ { "op": "create|update|delete", "client_uuid": "<uuid محلي>",
              "client_updated_at": "2026-06-07 10:00:00", "payload": { ...نفس حقول POST/PUT, مع id للتحديث/الحذف } } ] }
```
الردّ `data.results[]`: `{ client_uuid, status: "applied|conflict|error", server_id, message[, server_record] }`.
- **idempotency:** `create` بنفس `client_uuid` لا يُكرّر الإدراج (يعيد server_id الموجود).
- **سياسة التعارض:** «الأحدث يفوز» — إن كان `updated_at` على الخادم أحدث من `client_updated_at`
  يُعاد `conflict` مع `server_record` دون كتابة؛ غير ذلك يُطبّق التعديل.
- `update` بلا `server_id` (لم يُرفع بعد) يُنشأ عبر `client_uuid`. `delete` غير الموجود يُعدّ منفّذاً.

### `GET /api/sync/pull?updated_since=YYYY-MM-DD HH:MM:SS`
يعيد `data`: `{ server_time, count, timesheets[] }` لسجلات المشروع المتغيّرة منذ `updated_since`
(فارغ = الكل). استخدم `server_time` المُعاد في الطلب التالي.
> ملاحظة: الحذف على الخادم حذف فعلي (hard delete) فلا يُكتشف عبر pull — وثّق ذلك للعميل.

---

## الملفات

```
api/
├── .htaccess                 توجيه + تمرير ترويسة Authorization
├── index.php                 المتحكّم الأمامي والموجّه
├── bootstrap.php             تحميل config + ردود JSON + مدخلات + مصادقة + عزل + أخطاء
├── controllers/
│   ├── auth.php              login · logout · me
│   ├── board.php             board (تطبيق الحركة)
│   ├── operations.php        operations (GET/POST/PUT) + by-type/drivers/contract-hours
│   ├── drivers.php           equipment-drivers (POST/PUT) · drivers/available
│   ├── lists.php             contracts · suppliers · equipment-types · equipments
│   ├── timesheet.php         refdata · failure-codes · timesheets (CRUD) · حسابات + تحقّق
│   └── sync.php              sync/timesheets (push) · sync/pull
└── README.md
```

ترحيلات: `database/migrations/2026_06_06_create_api_tokens.sql` ·
`database/migrations/2026_06_07_timesheet_sync_columns.sql` (يضيف `client_uuid` و`updated_at` لجدول timesheet).

## الإعداد

1. شغّل ترحيل جدول التوكنات مرّة واحدة:
   ```bash
   mysql -u root equipation_manage < database/migrations/2026_06_06_create_api_tokens.sql
   ```
2. تأكّد أن `mod_rewrite` و `mod_headers` مفعّلان في Apache (XAMPP يفعّلهما افتراضياً).
3. الأساس في تطبيق Flutter: `http://<LAN-IP>/ems/api` (راجع `flutter_app/README.md`).
