# تقرير الفحص الوقائي — استدعاءات mysqli fetch غير المحصّنة

**التاريخ:** 2026-06-08 · **الحالة:** تقرير فقط (لم يُعدَّل أي كود).

## الخلفية
الخطأ القاتل `mysqli_fetch_assoc(): Argument #1 ($result) must be of type mysqli_result, bool given`
يحدث حين يفشل `mysqli_query()` (يعيد `false`) ثم تُستدعى دالة جلب على النتيجة دون فحص نجاحها.
السبب الجذري الأشهر (خلط الترتيبات/collation) **أُصلح** بترحيل توحيد utf8mb4. هذا التقرير يحصر
المواقع التي ستظلّ تنهار عند **أي** فشل استعلام آخر (خطأ صياغة، عمود ناقص، قيمة غير متوقّعة…).

**«غير محصّن» = لا يوجد قبل الجلب فحص للنتيجة** مثل `if ($r)` / `if (!$r)` / `$r && …` / `$r ? … : null`.

## الإجمالي: ~148 موقعًا في ~40 ملفًا
| المجموعة | HIGH | MED | LOW | المجموع |
|---|---|---|---|---|
| Contracts/Clients/Suppliers/Drivers/Projects/Oprators | 17 | 23 | 0 | 40 |
| Equipments/Timesheet/movement/Approvals/Reports/emsreports | 22 | 36 | 36 | 94 |
| main/api/app | 4 | 9 | 1 | 14 |

> الرمز 🗑️ = الموقع داخل ملف **يتيم** (مرشّح للحذف في حصر الملفات غير المستخدمة) — أُدرج بناءً على طلبك.

---

## 🔴 HIGH — استعلامات JOIN أو مبنية من متغيّرات/مدخلات المستخدم (الأولوية)

| المسار:السطر | النمط | ملاحظة |
|---|---|---|
| main/project_users.php:371 | متغيّر→while | JOIN users+roles + subquery IN، مبني من مدخلات |
| main/project_users.php:106 | متغيّر→num_rows | subquery على roles + مدخلات |
| main/project_users.php:152 | متغيّر→num_rows | نفس الاستعلام |
| api/controllers/timesheet.php:752 | prepared get_result→while | JOIN equipment_drivers+drivers؛ `execute` غير مفحوص |
| Reports/reports.php:48→162 | متغيّر→while | JOIN ديناميكي من GET |
| Reports/deriver.php:50→187 | متغيّر→while | JOIN + WHERE من GET |
| Reports/timesheetdeliy.php:70→233 | متغيّر→while | JOIN 5 جداول + مدخلات (error_log لا يوقف) |
| Reports/driverAndsupplerscontract.php:105→193 | متغيّر→while | JOIN + GET (error_log لا يوقف) |
| Reports/contractall.php:177→319 | متغيّر→while | JOIN + فلتر (error_log لا يوقف) |
| Reports/contractall.php:194→338 | متغيّر→while | JOIN + عمود ديناميكي |
| Reports/contractall.php:210→358 | متغيّر→while | JOIN + متغيّر |
| Timesheet/get_drivers.php:37 | inline fetch(query) | عمليات + scope EXISTS ديناميكي |
| Timesheet/get_drivers.php:80→84 | متغيّر→while | JOIN + عدة EXISTS بـ company_id |
| Timesheet/get_timesheet.php:72→73 | متغيّر→fetch | SELECT* + EXISTS ديناميكي |
| Timesheet/get_timesheet_data.php:96→97 | متغيّر→fetch['total'] | COUNT JOIN + WHERE ديناميكي |
| Timesheet/get_timesheet_data.php:108→109 | متغيّر→fetch['total'] | COUNT JOIN + بحث المستخدم |
| Timesheet/timesheet_details.php:509→531 | متغيّر→while | JOIN 4 جداول + scope |
| Equipments/equipments.php:993→995 | متغيّر→while | JOIN كبير + scope |
| Equipments/equipments_fleet.php:1348→1349 | متغيّر→while | JOIN كبير |
| Equipments/equipments_drivers.php:1023→1025 | متغيّر→while | JOIN كبير |
| 🗑️ Equipments/get_contract_stats.php:144→148 | متغيّر→while | JOIN + row id |
| 🗑️ Equipments/get_contract_stats.php:157→158 | متغيّر→fetch | COUNT JOIN |
| 🗑️ Equipments/get_project_mines.php:68→71 | متغيّر→while | JOIN + scope |
| movement/add_drivers.php:69→70 | متغيّر→fetch | JOIN + scope |
| movement/add_drivers.php:78→83 | متغيّر→while | JOIN + scope |
| movement/add_drivers.php:1056→1066 | متغيّر→num_rows | subquery + scope |
| 🗑️ Suppliers/suppliers_details.php:54→59 | متغيّر→fetch | 3 subqueries + `$_GET['id']` خام (⚠️ حقن SQL) |
| 🗑️ Suppliers/suppliers_details.php:149→151 | متغيّر→fetch | JOIN + `$project` خام |
| Suppliers/suppliers.php:604→605 | متغيّر→fetch | 3 subqueries + scope ديناميكي |
| Suppliers/supplierscontracts.php:1108→1112 | متغيّر→fetch | JOIN ثلاثي + فلتر ديناميكي |
| Suppliers/get_supplier_contract_equipments.php:20→23 | متغيّر→fetch | JOIN |
| Suppliers/get_project_hours.php:45→47 | متغيّر→fetch | JOIN + GROUP BY |
| Suppliers/get_project_hours.php:89→91 | متغيّر→fetch | JOIN + GROUP BY + شرط |
| Suppliers/get_project_hours.php:114→116 | متغيّر→fetch | JOIN + شرط |
| Contracts/contracts.php:1012→1016 | متغيّر→fetch | JOIN + where ديناميكي |
| Contracts/contracts_details.php:962→963 | متغيّر→fetch | JOIN + متغيّرات |
| Drivers/drivers.php:1015→1018 | متغيّر→fetch | JOIN + subquery + scope |
| Drivers/drivercontracts.php:920→924 | متغيّر→fetch | JOIN + scope |
| Oprators/oprators.php:908→910 | متغيّر→fetch | JOIN خماسي + GROUP BY + scope |
| Oprators/getoprator.php:26→32 | متغيّر→fetch | NOT IN subquery + ديناميكي |
| Oprators/get_contract_stats.php:93→98 | متغيّر→fetch | JOIN + GROUP BY |
| Oprators/get_contract_stats.php:109→110 | متغيّر→fetch | JOIN + متغيّرات |
| Projects/projects.php:638→639 | متغيّر→fetch | JOIN + subqueries مرتبطة + فلتر |

---

## 🟡 MED — جدول واحد بشروط ديناميكية

| المسار:السطر | النمط |
|---|---|
| main/users.php:397 | متغيّر→while (users بنطاق شركة) |
| main/users.php:345 | متغيّر→while (project بنطاق ديناميكي) |
| main/users.php:412 | متغيّر→fetch_array (project by id) |
| main/users.php:420 | متغيّر→fetch_array (contracts by id) |
| main/project_users.php:215 | متغيّر→num_rows (users by username/company) |
| main/project_users.php:164 | متغيّر→num_rows (نفسه) |
| api/controllers/timesheet.php:642 | prepared get_result→while (contracts؛ execute غير مفحوص) |
| api/controllers/lists.php:37 | prepared get_result→while (contracts؛ execute غير مفحوص) |
| app/Services/Excel/ExcelService.php:161 | prepared get_result→while (تصدير ديناميكي؛ execute غير مفحوص) |
| Clients/clients.php:371→373 | متغيّر→num_rows (تحقّق تعديل) |
| Clients/clients.php:422→424 | متغيّر→num_rows (تحقّق إضافة) |
| Contracts/contractequipments_handler.php:108→111 | متغيّر→fetch (getContractEquipments) |
| Drivers/drivercontracts.php:141→142 | متغيّر→fetch (project + scope) |
| Drivers/drivers.php:697→698 | متغيّر→fetch (suppliers + scope) |
| Drivers/drivers.php:714→715 | متغيّر→fetch (project + scope) |
| Drivers/drivercontracts_details.php:1306→1307 | متغيّر→fetch (دمج عقود + scope) |
| Drivers/get_project_hours.php:25→26 | متغيّر→fetch (contracts؛ الفحص بعد fetch لا يحمي) |
| Drivers/get_project_hours.php:41→43 | متغيّر→fetch (contractequipments GROUP BY) |
| Drivers/get_project_hours.php:62→63 | متغيّر→fetch (drivercontracts SUM + شرط) |
| 🗑️ Drivers/showcontractdriver.php:58→60 | متغيّر→fetch (جدول واحد) |
| 🗑️ Drivers/get_driver_contract_equipments.php:16→19 | متغيّر→fetch |
| 🗑️ Suppliers/showcontractsuppliers.php:57→59 | متغيّر→fetch |
| Suppliers/get_project_hours.php:25→26 | متغيّر→fetch (contracts) |
| Suppliers/get_project_hours.php:69→70 | متغيّر→fetch (supplierscontracts SUM) |
| Suppliers/suppliers.php:231→232 | متغيّر→fetch (COUNT بشرط delete_id) |
| Suppliers/suppliers.php:234→235 | متغيّر→fetch (COUNT بشرط delete_id) |
| 🗑️ Suppliers/suppliers_details.php:103→105 | متغيّر→fetch (equipments + `$project` خام) |
| Suppliers/supplierscontracts_details.php:1012→1013 | متغيّر→fetch (دمج عقود + scope) |
| Suppliers/supplierscontracts.php:266→267 | متغيّر→fetch (project + scope) |
| Projects/projects.php:516→517 | متغيّر→fetch (clients + scope) |
| Projects/projects.php:250→251 | prepared get_result→fetch (غير مفحوص ضد false) |
| Timesheet/get_failure_codes.php:38→45 | متغيّر→while (failure_codes) |
| Timesheet/get_failure_codes.php:54→63 | متغيّر→while |
| Timesheet/get_failure_codes.php:72→82 | متغيّر→while |
| Timesheet/get_failure_codes.php:91→102 | متغيّر→while |
| Timesheet/get_failure_codes.php:111→114 | متغيّر→fetch |
| Equipments/equipments.php:238→239 | متغيّر→fetch (COUNT) |
| Equipments/equipments.php:416→417 | متغيّر→while (supplier) |
| Equipments/equipments.php:858→859 | متغيّر→while (supplier filter) |
| Equipments/equipments_fleet.php:281→282 | متغيّر→fetch (COUNT) |
| Equipments/equipments_fleet.php:684→685 | متغيّر→while (supplier) |
| Equipments/equipments_fleet.php:1216→1217 | متغيّر→while (supplier filter) |
| Equipments/equipments_drivers.php:277→278 | متغيّر→fetch (COUNT) |
| Equipments/equipments_drivers.php:444→445 | متغيّر→while (supplier) |
| Equipments/equipments_drivers.php:886→887 | متغيّر→while (supplier filter) |
| 🗑️ Equipments/select_project.php:236→238 | متغيّر→num_rows (subqueries) |
| Equipments/equipments_types.php:37 | `$stmt->get_result()->fetch_assoc()` (سلسلة غير مفحوصة) |
| Approvals/requests.php:91→93 | متغيّر→while (تجميع + where) — 🗑️ requests.php مرشّح كميت |
| movement/save_equipment_drivers.php:125→126 | متغيّر→fetch |
| movement/save_equipment_drivers.php:152→153 | متغيّر→num_rows |
| movement/save_equipment_drivers.php:158→159 | متغيّر→fetch |
| movement/save_equipment_drivers.php:238→239 | متغيّر→num_rows |
| movement/move_oprators.php:209 | `$stmt->get_result()->fetch_assoc()` (سلسلة) |
| movement/move_oprators.php:222 | `$stmt->get_result()->num_rows` (سلسلة) |
| movement/move_oprators.php:292 | `$stmt->get_result()->fetch_assoc()` (سلسلة) |
| movement/move_oprators.php:390 | `$stmt->get_result()->fetch_assoc()` (سلسلة) |
| movement/move_oprators.php:485 | `$stmt->get_result()->num_rows` (سلسلة) |
| movement/move_oprators.php:674→678 | متغيّر→while (operations) |
| Reports/reports.php:95→96 | متغيّر→while (suppliers + company) |
| Reports/reports.php:122→123 | متغيّر→while (جدول ديناميكي) |
| Reports/contract_report.php:76→156 | متغيّر→while (JOIN بسيط) |
| Reports/deliy.php:111→112 | متغيّر→while (subquery) |
| Reports/timesheetdeliy.php:156→157 | متغيّر→while (sup) |
| Reports/equipments_reports.php:15→166 | `$conn->query`→while fetch (JOIN) |
| Reports/timesheet_reports.php:16→178 | `$conn->query`→while fetch (JOIN 4 جداول) |
| Reports/projects_reports.php:16→163 | `$conn->query`→while fetch |

---

## 🟢 LOW — تجميعات بسيطة / SELECT ثابت بلا مدخلات
(خطر منخفض جدًّا — تنهار فقط عند خطأ صياغة دائم؛ الأقلّ أولويةً)

- Reports/new_reports.php:11,14,17,20,23,26 — 6× inline `fetch_assoc(query(...))` على COUNT/SUM
- Reports/equipments_reports.php:9–12 · projects_reports.php:10–13 · timesheet_reports.php:9–12 — 12× inline `$conn->query()->fetch_assoc()`
- Equipments/manage_failure_codes.php:161–165 — 5× inline COUNT
- Equipments/equipments_fleet.php:96→97,511,525,527,528,529 — inline/COUNT
- Equipments/equipments.php:872→873 · equipments_fleet.php:1230→1231 · equipments_drivers.php:900→901 — while (نوع، ثابت)
- Equipments/equipments_types.php:167→169 — `$conn->query("SELECT * FROM equipments_types")`→while
- Reports/reports.php:108→109 · deliy.php:125→126 · deriver.php:95→96,109→110,142→147 · timesheetdeliy.php:142→143 · driverAndsupplerscontract.php:136→137,150→151 — while (project/driver بسيط)
- 🗑️ emsreports/reports/report_timesheet_summary.php:369→370 — while (project بسيط)

---

## أنماط الحارس الموصى بها

```php
// 1) حلقة while:
$r = mysqli_query($conn, $sql);
if ($r) { while ($row = mysqli_fetch_assoc($r)) { /* ... */ } }

// 2) صفّ واحد:
$r = mysqli_query($conn, $sql);
$data = $r ? mysqli_fetch_assoc($r) : null;
if (!$data) { /* عالج الغياب */ }

// 3) num_rows:
$r = mysqli_query($conn, $sql);
if ($r && mysqli_num_rows($r) > 0) { /* ... */ }

// 4) inline → فصله أولاً:
$res = mysqli_query($conn, "SELECT COUNT(*) c FROM t");
$count = $res ? (int)(mysqli_fetch_assoc($res)['c'] ?? 0) : 0;

// 5) prepared statement (افحص execute ثم get_result):
if (mysqli_stmt_execute($stmt)) {
    $res = mysqli_stmt_get_result($stmt);
    if ($res) { while ($row = mysqli_fetch_assoc($res)) { /* ... */ } }
}
```

## ملاحظات
- **`error_log` ليس حارسًا:** يسجّل الخطأ ثم يستمر التنفيذ فينهار عند `false` (contractall, timesheetdeliy, driverAndsupplerscontract).
- **الفحص *بعد* fetch لا يحمي:** `if (!$row)` بعد `fetch` لا ينفع لأن الانهيار يقع داخل `fetch(false)` قبله (get_drivers, add_drivers, save_equipment_drivers, move_oprators).
- **prepared statements:** افحص `mysqli_stmt_execute()` أيضًا، فإن فشل يُرجع `get_result()` قيمة `false`.
- **⚠️ حقن SQL منفصل:** `Suppliers/suppliers_details.php` يستخدم `$_GET['id']` خامًا بلا `intval` (وهو ملف يتيم).
