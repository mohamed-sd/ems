# التقنيات المستخدمة في نظام EMS
# Technologies Stack

---

## 🖥️ تقنيات Backend | Server-Side Technologies

### 1. PHP
- **الإصدار المطلوب:** PHP 7.0+
- **الاستخدام:**
  - معالجة جميع طلبات الخادم
  - معالجة نماذج POST/GET
  - إدارة الجلسات (Sessions)
  - التحقق من الصلاحيات (Authorization)
  - معالجة البيانات وتنسيقها
- **الملفات الرئيسية:**
  - `config.php` - الاتصال بقاعدة البيانات
  - `index.php` - صفحة تسجيل الدخول
  - جميع الملفات التنفيذية في المشروع

### 2. MySQL Database
- **الإصدار:** MySQL 5.7+ أو MariaDB 10.3+
- **قاعدة البيانات:** `equipation_manage`
- **المحرك:** InnoDB (دعم المعاملات)
- **الترميز:** UTF-8 (دعم اللغة العربية)
- **الجداول الرئيسية:**
  - `users` - بيانات المستخدمين والصلاحيات
  - `project` - المشاريع
  - `clients` - العملاء
  - `mines` - المناجم المرتبطة بالمشاريع
  - `equipments` - المعدات والآليات
  - `equipments_types` - أنواع المعدات (حفارات، قلابات، خرامات)
  - `suppliers` - الموردين
  - `drivers` - المشغلين/السائقين
  - `operations` - تشغيل المعدات في المشاريع
  - `timesheet` - سجلات ساعات العمل
  - `contracts` - عقود المشاريع
  - `contractequipments` - معدات العقود
  - `supplierscontracts` - عقود الموردين
  - `drivercontracts` - عقود المشغلين
  - `timesheet_approvals` - اعتمادات الساعات
  - `timesheet_approval_notes` - ملاحظات الاعتماد

### 3. MySQLi Extension
- **نوع الاتصال:** Procedural + Object-Oriented
- **الوظائف الرئيسية:**
  - `mysqli_connect()` - الاتصال بقاعدة البيانات
  - `mysqli_query()` - تنفيذ الاستعلامات
  - `mysqli_real_escape_string()` - حماية SQL Injection
  - `mysqli_prepare()` - Prepared Statements
- **متغير الاتصال:** `$conn`

---

## 🎨 تقنيات Frontend | Client-Side Technologies

### 1. HTML5
- **استخدام Semantic HTML**
- **Attributes رئيسية:**
  - `dir="rtl"` - دعم الكتابة من اليمين لليسار
  - `lang="ar"` - تحديد اللغة العربية
  - `data-*` attributes - تخزين بيانات مخصصة للعناصر

### 2. CSS3
- **Bootstrap 5.3**
  - **الإصدار:** Bootstrap 5.3.0 (RTL Version)
  - **CDN:** jsDelivr
  - **الميزات المستخدمة:**
    - Grid System (12 columns)
    - Responsive Utilities
    - Form Controls
    - Buttons & Badges
    - Cards & Modals
    - Alerts & Toasts
    - Navigation (Navbar, Sidebar)
  - **التخصيصات:** دعم RTL كامل للعربية

- **ملف مخصص: `assets/css/style.css`**
  - تنسيقات مخصصة لـ RTL
  - ألوان العلامة التجارية
  - تخصيصات الجداول
  - تنسيقات النماذج
  - Responsive adjustments

- **FontAwesome 6**
  - **الإصدار:** 6.4.0
  - **الاستخدام:** أيقونات الواجهة (Icons)
  - **الموقع:** `assets/webfonts/`
  - **أمثلة:** fa-user, fa-tractor, fa-truck, fa-hammer

### 3. JavaScript (ES5/ES6)
- **الاستخدام:**
  - التفاعل الديناميكي مع الواجهة
  - التحقق من النماذج (Form Validation)
  - التعامل مع AJAX
  - معالجة الأحداث (Event Handling)
  - التلاعب بـ DOM
  - معالجة التواريخ والوقت

### 4. jQuery
- **الإصدار:** jQuery 3.6+
- **CDN:** jQuery CDN
- **الاستخدام:** AJAX, Event Handling, DOM Manipulation

---

## 📚 المكتبات والأطر | Libraries & Frameworks

### 1. DataTables.js
- **الإصدار:** DataTables 1.13+
- **CDN:** DataTables CDN
- **الميزات المستخدمة:**
  - عرض الجداول مع pagination
  - البحث والفرز التلقائي
  - Responsive tables
  - تصدير البيانات (Excel, PDF, CSV)
  - التصفية المخصصة (Custom Filtering)
  - اللغة العربية الكاملة
- **الإضافات:**
  - **Buttons Extension** - تصدير البيانات
  - **Responsive Extension** - جداول متجاوبة
  - **JSZip** - دعم تصدير Excel
  - **pdfMake** - دعم تصدير PDF
- **ملف اللغة:** `/ems/assets/i18n/datatables/ar.json`

### 2. Bootstrap 5 Components
- **المكونات المستخدمة:**
  - **Modals** - نوافذ منبثقة للإضافة/التعديل
  - **Dropdowns** - قوائم منسدلة
  - **Forms** - عناصر النماذج
  - **Alerts** - رسائل التنبيه
  - **Badges** - شارات الحالة
  - **Cards** - بطاقات المحتوى
  - **Navbar** - شريط التنقل
  - **Buttons** - الأزرار بأنواعها

### 3. PHPSpreadsheet
- **التثبيت:** عبر Composer
- **الاستخدام:**
  - استيراد بيانات Excel/CSV
  - تصدير البيانات إلى Excel
  - قراءة ملفات .xlsx, .xls, .csv
- **الملفات:**
  - `Projects/import_clients_excel.php`
  - `Projects/download_clients_template.php`
  - `Drivers/import_drivers_excel.php`
  - `Equipments/import_equipments_excel.php`

### 4. SweetAlert2 (محتمل)
- **الاستخدام:** رسائل تأكيد مخصصة
- **البديل:** window.confirm()

---

## 🔒 الأمان | Security Technologies

### 1. CSRF Protection
- Token-based protection: `bin2hex(openssl_random_pseudo_bytes(32))`

### 2. SQL Injection Prevention
- `mysqli_real_escape_string()`
- `intval()`, `floatval()`
- Prepared Statements

### 3. Security Headers
- Content-Security-Policy
- X-Frame-Options
- X-Content-Type-Options
- Referrer-Policy
- HttpOnly Cookies

### 4. Brute-Force Protection
- 5 محاولات فاشلة → قفل 15 دقيقة

### 5. Password Security
- `password_hash()` مع PASSWORD_DEFAULT
- `password_verify()`

---

## 📱 تصميم متجاوب | Responsive Design

- **Bootstrap Grid System** - Breakpoints (xs, sm, md, lg, xl, xxl)
- **DataTables Responsive** - `responsive: true`
- **CSS Media Queries** - في `assets/css/style.css`

---

## 🌐 الدعم الدولي | Internationalization

- **RTL Support** - Bootstrap RTL Version
- **UTF-8 Encoding** - في كل مكان
- **DataTables Arabic** - `/ems/assets/i18n/datatables/ar.json`
- **Date Format** - YYYY-MM-DD (Database), DD/MM/YYYY (Display)

---

## � إدارة التبعيات | Dependency Management

### Composer (PHP)
- **PHPSpreadsheet** - Excel/CSV processing
- **التثبيت:** `composer install`

### CDN (Frontend)
- jQuery: `https://code.jquery.com/jquery-3.6.0.min.js`
- Bootstrap 5.3: `https://cdn.jsdelivr.net/npm/bootstrap@5.3.0`
- DataTables: `https://cdn.datatables.net/1.13.6/`
- FontAwesome: محلي في `assets/webfonts/`

---

## 🛠️ أدوات التطوير | Development Tools

### بيئة التطوير
- **XAMPP** - Apache + MySQL + PHP
- **VS Code** - محرر النصوص
- **Chrome/Firefox DevTools** - تصحيح الأخطاء

### إدارة قاعدة البيانات
- **phpMyAdmin** - واجهة رسومية
- **MySQL CLI** - `c:\xamppnew\mysql\bin\mysql.exe`

### أدوات التصحيح
- **PHP:** `var_dump()`, `print_r()`, `mysqli_error()`
- **JavaScript:** `console.log()`, Browser DevTools

---

**تاريخ آخر تحديث:** 1 مايو 2026
