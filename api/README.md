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

## الملفات

```
api/
├── .htaccess                 توجيه + تمرير ترويسة Authorization
├── index.php                 المتحكّم الأمامي والموجّه
├── bootstrap.php             تحميل config + ردود JSON + مدخلات + مصادقة + عزل + أخطاء
├── controllers/
│   ├── auth.php              login · logout · me
│   ├── board.php             board
│   ├── operations.php        operations (GET/POST/PUT)
│   ├── drivers.php           equipment-drivers (POST/PUT) · drivers/available
│   └── lists.php             contracts · suppliers · equipment-types · equipments
└── README.md
```

جدول التوكنات: `database/migrations/2026_06_06_create_api_tokens.sql`.

## الإعداد

1. شغّل ترحيل جدول التوكنات مرّة واحدة:
   ```bash
   mysql -u root equipation_manage < database/migrations/2026_06_06_create_api_tokens.sql
   ```
2. تأكّد أن `mod_rewrite` و `mod_headers` مفعّلان في Apache (XAMPP يفعّلهما افتراضياً).
3. الأساس في تطبيق Flutter: `http://<LAN-IP>/ems/api` (راجع `flutter_app/README.md`).
