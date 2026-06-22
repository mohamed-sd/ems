# جرد ترحيل `drivers` → `employees` (الموجة 1)

> أُنشئ في الخطوة 0. مرجع التحويل واختبار الانحدار. **لا git** — الاعتماد على النسخة الاحتياطية اليدوية.

## النسخ الاحتياطية المأخوذة (قبل أي تغيير)
- قاعدة البيانات الكاملة: `C:\tmp\ems_employees_migration_backup_<TS>\equipation_manage_full.sql` (~874 KB).
- جدول `drivers` منفصلاً: `…\drivers_table_only.sql`.
- نسخ ملفات: `config.php`, `Drivers/`, `Timesheet/`, `movement/`, `Equipments/`, `api/`, `emsreports/`, `app/`, `includes/`.
- مسار النسخة محفوظ في `C:\tmp\ems_last_backup_path.txt`.
- **خطة التراجع (Rollback):** استعادة `equipation_manage_full.sql` + نسخ الملفات فوق المشروع. (لا git.)

## ملاحظة بيئة
التطبيق يتّصل بـ`equipation_manage` (مكتوب في config.php)؛ اختبار PHP على staging منفصل غير عملي دون تبديل اسم القاعدة. النهج: تنفيذ على القاعدة الحيّة **مع** نسخة احتياطية كاملة (rollback) و**VIEW انتقالي** `drivers→employees` كشبكة أمان، والترحيل إضافي/غير متلف حتى الأرشفة النهائية (الجدول الأصلي يُعاد تسميته لا يُحذف).

---

## أ) عمليات بيانات على جدول `drivers` — **يجب التحويل إلى `employees`** (41 ملفاً)

| الملف:السطر | النوع |
|---|---|
| Approvals/hours_approval.php:147,186 | LEFT JOIN drivers |
| Approvals/hours_approval_followup.php:156,194 | LEFT JOIN drivers |
| Drivers/deactivate_driver_handler.php:44 | SELECT FROM drivers |
| Drivers/driver_profile.php:44 | FROM drivers d |
| Drivers/driver_truck_history.php:14 | SELECT FROM drivers |
| Drivers/drivers.php:171,225 | SELECT (فرادة الكود) |
| Drivers/drivers.php:188 | UPDATE drivers |
| Drivers/drivers.php:232 | INSERT INTO drivers |
| Drivers/drivers.php:303 | DELETE FROM drivers |
| Drivers/drivers.php:1081 | FROM drivers d (الجدول) |
| Drivers/get_driver_data.php:54 | SELECT * FROM drivers |
| Equipments/equipment_profile.php:118 | INNER JOIN drivers |
| Equipments/equipments.php:1040 | LEFT JOIN drivers |
| Equipments/equipments_drivers.php:1016 | LEFT JOIN drivers |
| Equipments/equipments_fleet.php:1404 | LEFT JOIN drivers |
| Oprators/oprators.php:943 | LEFT JOIN drivers |
| Reports/contractall.php:207 | JOIN drivers |
| Reports/deliy.php:27 | JOIN drivers |
| Reports/deriver.php:24,111 | JOIN / SELECT FROM drivers |
| Reports/driverAndsupplerscontract.php:80,152 | JOIN / SELECT FROM drivers |
| Reports/new_reports.php:23 | SELECT COUNT FROM drivers |
| Reports/timesheet_reports.php:35 | LEFT JOIN drivers |
| Reports/timesheetdeliy.php:43 | JOIN drivers |
| Timesheet/get_drivers.php:54 | JOIN drivers |
| Timesheet/get_timesheet_data.php:89,105,124 | JOIN drivers |
| Timesheet/timesheet.php:469,990 | JOIN / SELECT FROM drivers |
| Timesheet/timesheet_details.php:502 | JOIN drivers |
| Timesheet/view_timesheet.php:149,249 | JOIN drivers |
| api/controllers/board.php:96 | JOIN drivers |
| api/controllers/drivers.php:241 | FROM drivers d |
| api/controllers/operations.php:87 | INNER JOIN drivers |
| api/controllers/sync.php:209 | LEFT JOIN drivers |
| api/controllers/timesheet.php:518,620,942 | JOIN / SELECT FROM drivers |
| app/Services/Excel/ExcelRegistry.php:448 | exportExpr FROM drivers |
| emsreports/includes/functions.php:381 | SELECT FROM drivers |
| emsreports/reports/_report_template.php:174,209,228,245,261,299,323,880,909,920,1012 | JOIN / FROM drivers |
| emsreports/reports/report_timesheet_summary.php:118 | LEFT JOIN drivers |
| main/dashboard.php:201 | COUNT FROM drivers |
| movement/add_drivers.php:81,1054 | JOIN / FROM drivers |
| movement/delete_equipment_driver.php:58 | JOIN drivers |
| movement/map_page.php:135 | JOIN drivers |
| movement/move_oprators.php:670 | LEFT JOIN drivers |
| movement/movement_operations.php:551,586 | JOIN / FROM drivers |
| movement/project_drivers.php:160,395,633,659,685,704 | JOIN / FROM drivers |
| movement/update_shift_type.php:94 | JOIN drivers |

## ب) `db_table_has_column($conn, 'drivers', …)` — **حوّل الوسيط إلى `'employees'`**

Drivers/driver_profile.php:21 · Drivers/drivercontracts.php:19 · Drivers/drivers.php:20,23,24,25,30,35,40,46 · Timesheet/get_drivers.php:59,77 · Timesheet/timesheet.php:974 · Timesheet/view_timesheet.php:252 · api/controllers/drivers.php:231,232,233 · api/controllers/timesheet.php:608,609,611,744,745 · movement/add_drivers.php:20,21 · movement/movement_operations.php:27,28,29 · movement/project_drivers.php:22,23 · movement/save_equipment_drivers.php:22,23

## ج) Schema ديناميكي — **يُحذف/يُستبدل** (الأعمدة دائمة في `employees`)

- Drivers/drivers.php:28,29,34,39,44,45 — `ALTER TABLE drivers ADD COLUMN/INDEX …` → **حذف** (employees يُنشأ بكل الأعمدة).
- Drivers/get_driver_data.php:31 — `SHOW COLUMNS FROM drivers LIKE 'company_id'` → `db_table_has_column($conn,'employees','company_id')`.

## د) إطار Excel — `ExcelRegistry.php`
- سطر 88: `new EntityDefinition('drivers','السائقون','drivers', …)` → الجدول (الوسيط 3) → `'employees'` (مع إبقاء مفتاح الكيان `drivers` المستعمل في `ems_excel_header_actions('drivers',…)`).
- سطر 441، 559: `'table' => 'drivers'` داخل lookup → `'employees'`.
- سطر 448: exportExpr `FROM drivers` → `employees` (مذكور في أ).
- سطر 135: `'moduleCode' => 'drivers'` (رمز صلاحية) → يبقى كما هو.

## هـ) مُساعد نطاق التقارير — تمرير اسم الجدول
- emsreports/includes/functions.php:380 و emsreports/reports/_report_template.php:121: `rptCompanyScope($conn,'d','drivers',…)` → `'employees'`.

---

## مراجع **لا تُحوَّل** (ليست جدول `drivers`)
- أعمدة المفاتيح في الجداول الأخرى تبقى كما هي وتشير إلى `employees.id`: `equipment_drivers.driver_id`, `drivercontracts.driver_id`, `timesheet.driver`, وكل `d.id`/`ed.driver_id`/`t.driver`. **لا إعادة تسمية (الموجة 2 لاحقاً).**
- جداول مختلفة: `equipment_drivers`, `drivercontracts`.
- أعمدة: `driver_code`, `driver_status`, `driver_photo`, `driver_id`, `supplier_id` (تبقى أسماؤها).
- مسارات/أسماء ملفات ومجلّد: `Drivers/`, `*_drivers.php`, `controllers/drivers.php`.
- مسارات REST و«تصنيفات» نصّية: `api/.../drivers`, emsreports `'category'=>'drivers'`، تسميات عرض «المشغلون».
- مفتاح كيان Excel `'drivers'` ورمز `moduleCode='drivers'` (يبقيان).

## ملخّص العدّ
- ملفات بعمليات بيانات (أ): **41** ملفاً.
- ملفات بـ db_table_has_column (ب): ~14 ملفاً.
- schema ديناميكي (ج): ملفان.
- Excel (د): ملف واحد.
- مساعد التقارير (هـ): ملفان.
