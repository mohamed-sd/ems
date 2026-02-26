# توثيق شامل لنظام إيكوبيشن لإدارة المعدات (EMS)
# Equipment Management System - Complete Documentation

<div dir="rtl">

## 📋 جدول المحتويات

1. [نظرة عامة على النظام](#نظرة-عامة)
2. [متطلبات النظام](#متطلبات-النظام)
3. [الهيكل المعماري](#الهيكل-المعماري)
4. [قاعدة البيانات](#قاعدة-البيانات)
5. [نظام الصلاحيات والأدوار](#نظام-الصلاحيات)
6. [الوحدات الرئيسية](#الوحدات-الرئيسية)
7. [سير العمل](#سير-العمل)
8. [الأمان والحماية](#الأمان)
9. [واجهات المستخدم](#واجهات-المستخدم)
10. [الملفات الرئيسية](#الملفات-الرئيسية)

---

## 📖 1. نظرة عامة على النظام {#نظرة-عامة}

### 1.1 وصف النظام
**إيكوبيشن** (Equipation) هو نظام إدارة متكامل لإدارة المعدات والآليات في مشاريع التعدين والبناء. يوفر النظام:
- إدارة شاملة للمشاريع والمناجم
- تتبع المعدات والموردين
- إدارة عقود المشاريع والموردين والسائقين
- تسجيل ومراقبة ساعات العمل
- تقارير تحليلية مفصلة

### 1.2 الأهداف الرئيسية
- **تحسين الكفاءة**: تتبع دقيق لساعات عمل المعدات والمشغلين
- **إدارة العقود**: نظام متكامل لإدارة دورة حياة العقود
- **الشفافية**: تقارير مفصلة لجميع العمليات
- **التحكم في التكاليف**: مراقبة الساعات المتعاقد عليها مقابل المنفذة
- **الأمان**: نظام صلاحيات متعدد المستويات

### 1.3 المستخدمون المستهدفون
- **الإدارة العليا** (-1): وصول كامل لجميع الميزات
- **مدراء المشاريع** (1): إدارة المشاريع والعقود
- **مدراء الموردين** (2): إدارة الموردين والمعدات
- **مدراء المشغلين** (3): إدارة السائقين والمشغلين
- **مدراء الأسطول** (4): إدارة التشغيل والمعدات
- **مدراء المواقع** (5): إدخال ومراجعة ساعات العمل
- **حركة وتشغيل** (10): مراقبة حركة المعدات
- **مدخلو البيانات** (6): إدخال ساعات العمل
- **المراجعون** (7,8,9): مراجعة واعتماد البيانات

---

## 🔧 2. متطلبات النظام {#متطلبات-النظام}

### 2.1 المتطلبات التقنية

#### خادم الويب
- **Apache**: 2.4 أو أحدث
- **PHP**: 5.6+ (يفضل 7.4 أو أحدث)
- **MySQL/MariaDB**: 5.7+ / 10.4+
- **الذاكرة**: 512 MB على الأقل
- **المساحة**: 500 MB على الأقل

#### المكتبات المطلوبة
- **mysqli**: لاتصال قاعدة البيانات
- **session**: لإدارة الجلسات
- **openssl**: للتشفير وتوليد الرموز
- **Composer**: لإدارة التبعيات

#### التبعيات (Composer)
```json
{
  "require": {
    "phpoffice/phpspreadsheet": "^1.29",
    "maennchen/zipstream-php": "^2.1",
    "markbaker/matrix": "*",
    "markbaker/complex": "*"
  }
}
```

### 2.2 متطلبات المتصفح

#### المتصفحات المدعومة
- Google Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

#### المكتبات الأمامية (CDN)
- **jQuery**: 3.7.1
- **Bootstrap**: 5.3.2
- **DataTables**: 1.13.6
- **Font Awesome**: 6.4.0
- **Cairo Font**: للدعم العربي

### 2.3 البيئة التشغيلية
- **نظام التشغيل**: Windows/Linux/macOS
- **بيئة التطوير**: XAMPP/WAMP/MAMP أو مماثل
- **الترميز**: UTF-8 (دعم كامل للعربية)
- **الاتجاه**: RTL (من اليمين لليسار)

---

## 🏗️ 3. الهيكل المعماري {#الهيكل-المعماري}

### 3.1 نمط المعمارية
النظام يتبع نمط **MVC المبسط** (Model-View-Controller):
- **Model**: التعامل المباشر مع قاعدة البيانات عبر MySQLi
- **View**: ملفات PHP تحتوي على HTML/CSS/JavaScript
- **Controller**: منطق PHP في نفس ملفات العرض

### 3.2 هيكل المجلدات
```
ems/
├── assets/                    # الملفات الثابتة
│   ├── css/                   # ملفات التنسيق
│   │   ├── style.css          # التنسيق الرئيسي
│   │   ├── admin-style.css    # تنسيق لوحة التحكم
│   │   └── main_admin_style.css
│   ├── js/                    # ملفات JavaScript
│   ├── images/                # الصور
│   └── webfonts/              # خطوط Font Awesome
│
├── Clients/                   # وحدة العملاء
│   ├── clients.php            # إدارة العملاء
│   ├── import_clients_excel.php
│   └── download_clients_template.php
│
├── Projects/                  # وحدة المشاريع
│   ├── oprationprojects.php   # إدارة المشاريع
│   ├── view_projects.php      # عرض المشاريع
│   ├── view_clients.php       # ربط العملاء
│   ├── project_mines.php      # إدارة المناجم
│   └── import_*.php           # استيراد البيانات
│
├── Contracts/                 # وحدة العقود
│   ├── contracts.php          # قائمة العقود
│   ├── contracts_details.php  # تفاصيل العقد
│   ├── contract_actions_handler.php  # معالج الإجراءات
│   ├── contractequipments_handler.php
│   └── get_*.php              # نقاط AJAX
│
├── Suppliers/                 # وحدة الموردين
│   ├── suppliers.php          # إدارة الموردين
│   ├── supplierscontracts.php # عقود الموردين
│   ├── supplierscontracts_details.php
│   └── supplier_contract_actions_handler.php
│
├── Drivers/                   # وحدة المشغلين
│   ├── drivers.php            # إدارة المشغلين
│   ├── drivercontracts.php    # عقود المشغلين
│   ├── drivercontracts_details.php
│   ├── driver_contract_actions_handler.php
│   ├── import_drivers_excel.php
│   └── download_drivers_template.php
│
├── Equipments/                # وحدة المعدات
│   ├── equipments.php         # إدارة المعدات
│   ├── equipments_types.php   # أنواع المعدات
│   ├── save_equipment_drivers.php
│   └── get_*.php              # نقاط AJAX
│
├── Oprators/                  # وحدة التشغيل
│   ├── oprators.php           # إدارة التشغيل
│   ├── select_project.php     # اختيار المشروع
│   └── get_*.php              # نقاط AJAX
│
├── Timesheet/                 # وحدة ساعات العمل
│   ├── timesheet.php          # إدخال الساعات
│   ├── timesheet_type.php     # اختيار النوع
│   ├── view_timesheet.php     # عرض الساعات
│   ├── timesheet_details.php  # تفاصيل الساعات
│   └── get_*.php              # نقاط AJAX
│
├── Reports/                   # وحدة التقارير
│   ├── reports.php            # التقرير الرئيسي
│   ├── new_reports.php        # تقارير جديدة
│   ├── contract_report.php    # تقارير العقود
│   └── *.php                  # تقارير متنوعة
│
├── main/                      # الوحدات الرئيسية
│   ├── dashboard.php          # لوحة التحكم
│   ├── users.php              # إدارة المستخدمين
│   └── project_users.php      # مستخدمي المشروع
│
├── database/                  # قاعدة البيانات
│   └── equipation_manage (14).sql
│
├── vendor/                    # مكتبات Composer
│
├── includes/                  # الملفات المشتركة
│   ├── sessions.php           # إدارة الجلسات
│   └── js/                    # JavaScript مشترك
│
├── index.php                  # صفحة تسجيل الدخول
├── config.php                 # إعدادات الاتصال
├── logout.php                 # تسجيل الخروج
├── inheader.php               # رأس الصفحة
├── insidebar.php              # الشريط الجانبي
├── sidebar.php                # قائمة التنقل
└── composer.json              # تبعيات Composer
```

### 3.3 نمط الاتصال
```
┌─────────────┐
│   Browser   │
└──────┬──────┘
       │ HTTP/HTTPS
       ↓
┌─────────────┐
│   Apache    │
└──────┬──────┘
       │
       ↓
┌─────────────┐
│   PHP 7.4+  │ ← تشغيل السكريبتات
└──────┬──────┘
       │ MySQLi
       ↓
┌─────────────┐
│   MySQL     │ ← قاعدة البيانات
└─────────────┘
```

---

## 💾 4. قاعدة البيانات {#قاعدة-البيانات}

### 4.1 نظرة عامة
- **اسم القاعدة**: `equipation_manage`
- **المحرك**: InnoDB
- **الترميز**: UTF-8mb4 (دعم كامل للعربية)
- **عدد الجداول**: 20+ جدول

### 4.2 الجداول الرئيسية

#### 4.2.1 جدول المستخدمين (users)
```sql
CREATE TABLE `users` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(150) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20),
  `role` varchar(30) NOT NULL,
  `project_id` varchar(20) DEFAULT '0',
  `mine_id` int(11) DEFAULT 0,
  `contract_id` int(11) DEFAULT 0,
  `parent_id` varchar(20) DEFAULT '0',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**الحقول الهامة:**
- `role`: صلاحية المستخدم (-1: أدمن، 1-10: صلاحيات محددة)
- `project_id`: المشروع المخصص (لمدراء المواقع)
- `mine_id`: المنجم المخصص
- `contract_id`: العقد المخصص

#### 4.2.2 جدول العملاء (clients)
```sql
CREATE TABLE `clients` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `client_code` varchar(50) NOT NULL UNIQUE,
  `client_name` varchar(255) NOT NULL,
  `entity_type` varchar(100),
  `sector_category` varchar(100),
  `phone` varchar(50),
  `email` varchar(100),
  `whatsapp` varchar(50),
  `status` enum('نشط','متوقف') DEFAULT 'نشط',
  `created_by` int(11),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**المميزات:**
- `client_code`: كود فريد لكل عميل
- `status`: حالة العميل (نشط/متوقف)
- دعم معلومات التواصل المتعددة

#### 4.2.3 جدول المشاريع (project)
```sql
CREATE TABLE `project` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `company_client_id` int(11),
  `name` varchar(150) NOT NULL,
  `client` varchar(150) NOT NULL,
  `location` varchar(200) NOT NULL,
  `project_code` varchar(50),
  `category` varchar(100),
  `sub_sector` varchar(100),
  `state` varchar(100),
  `region` varchar(100),
  `nearest_market` varchar(100),
  `latitude` varchar(50),
  `longitude` varchar(50),
  `total` varchar(50) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_by` int(11),
  `create_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (company_client_id) REFERENCES clients(id)
);
```

**الحقول الهامة:**
- `company_client_id`: ربط مع جدول العملاء
- `latitude`, `longitude`: الإحداثيات الجغرافية
- بيانات شاملة عن موقع المشروع

#### 4.2.4 جدول المناجم (mines)
```sql
CREATE TABLE `mines` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `mine_name` varchar(255) NOT NULL,
  `mine_code` varchar(50) NOT NULL UNIQUE,
  `manager_name` varchar(255),
  `mineral_type` varchar(100),
  `mine_type` enum('حفرة مفتوحة','تحت أرضي','آبار','مهجور','مجمع معالجة/تركيز','موقع تخزين/مستودع','أخرى') NOT NULL,
  `mine_type_other` varchar(100),
  `ownership_type` enum('تعدين أهلي/تقليدي','شركة سودانية خاصة','شركة حكومية/قطاع عام','شركة أجنبية','مشروع مشترك (سوداني-أجنبي)','أخرى') NOT NULL,
  `ownership_type_other` varchar(100),
  `mine_area` decimal(10,2),
  `mine_area_unit` enum('هكتار','كم²') DEFAULT 'هكتار',
  `mining_depth` decimal(10,2),
  `contract_nature` enum('موظف مباشر لدى المالك','مقاول/شركة مقاولات'),
  `status` tinyint(1) DEFAULT 1,
  `notes` text,
  `created_by` int(11),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES project(id)
);
```

**المميزات:**
- كل مشروع يمكن أن يحتوي على عدة مناجم
- تفاصيل شاملة عن المنجم (النوع، الملكية، المساحة، العمق)
- `mine_code`: كود فريد لكل منجم

#### 4.2.5 جدول الموردين (suppliers)
```sql
CREATE TABLE `suppliers` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `supplier_code` varchar(100),
  `supplier_type` enum('فرد','شركة','وسيط','مالك','جهة حكومية'),
  `dealing_nature` varchar(255),
  `equipment_types` text,
  `commercial_registration` varchar(100),
  `identity_type` varchar(100),
  `identity_number` varchar(100),
  `identity_expiry_date` date,
  `email` varchar(255),
  `phone` varchar(15) NOT NULL,
  `phone_alternative` varchar(50),
  `full_address` text,
  `contact_person_name` varchar(255),
  `contact_person_phone` varchar(50),
  `financial_registration_status` enum('مسجل رسميا','غير مسجل','تحت التسجيل','معفى من التسجيل'),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` tinyint(1) DEFAULT 1
);
```

**المميزات:**
- معلومات شاملة عن المورد
- أنواع متعددة من الموردين
- بيانات قانونية ومالية كاملة

#### 4.2.6 جدول المعدات (equipments)
```sql
CREATE TABLE `equipments` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `suppliers` varchar(10) NOT NULL,
  `code` varchar(100) NOT NULL,
  `type` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `serial_number` varchar(100),
  `chassis_number` varchar(100),
  `manufacturer` varchar(100),
  `model` varchar(100),
  `manufacturing_year` int(4),
  `import_year` int(4),
  `equipment_condition` varchar(50) DEFAULT 'في حالة جيدة',
  `operating_hours` int(11),
  `engine_condition` varchar(50) DEFAULT 'جيدة',
  `tires_condition` varchar(50) DEFAULT 'N/A',
  `actual_owner_name` varchar(200),
  `owner_type` varchar(50),
  `owner_phone` varchar(50),
  `owner_supplier_relation` varchar(100),
  `license_number` varchar(100),
  `license_authority` varchar(100),
  `license_expiry_date` date,
  `inspection_certificate_number` varchar(100),
  `last_inspection_date` date,
  `current_location` varchar(255),
  `availability_status` varchar(50) DEFAULT 'متاحة للعمل',
  `estimated_value` decimal(15,2),
  `daily_rental_price` decimal(10,2),
  `monthly_rental_price` decimal(10,2),
  `insurance_status` varchar(50),
  `general_notes` text,
  `last_maintenance_date` date,
  `status` tinyint(1) DEFAULT 1
);
```

**المميزات:**
- معلومات تفصيلية جداً عن كل معدة
- بيانات الملكية والتراخيص
- الحالة الفنية والتسعير

#### 4.2.7 جدول المشغلين (drivers)
```sql
CREATE TABLE `drivers` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `driver_code` varchar(100),
  `nickname` varchar(100),
  `identity_type` varchar(100),
  `identity_number` varchar(100),
  `identity_expiry_date` date,
  `license_number` varchar(100),
  `license_type` varchar(100),
  `license_expiry_date` date,
  `license_issuer` varchar(100),
  `specialized_equipment` text,
  `years_in_field` int(11),
  `years_on_equipment` int(11),
  `skill_level` varchar(50),
  `certificates` text,
  `owner_supervisor` varchar(255),
  `supplier_id` int(11),
  `employment_affiliation` varchar(100),
  `salary_type` varchar(50),
  `monthly_salary` decimal(10,2),
  `email` varchar(255),
  `phone` varchar(255) NOT NULL,
  `phone_alternative` varchar(50),
  `address` text,
  `performance_rating` varchar(50),
  `behavior_record` varchar(100),
  `accident_record` varchar(100),
  `health_status` varchar(50),
  `health_issues` text,
  `vaccinations_status` varchar(50),
  `previous_employer` varchar(255),
  `employment_duration` varchar(100),
  `reference_contact` varchar(255),
  `general_notes` text,
  `driver_status` varchar(50),
  `start_date` date,
  `status` tinyint(1) DEFAULT 1
);
```

**المميزات:**
- ملف شامل لكل مشغل
- بيانات المهارات والخبرات
- التقييم والسلوك الوظيفي
- الصحة والسلامة

#### 4.2.8 جدول عقود المشاريع (contracts)
```sql
CREATE TABLE `contracts` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `mine_id` int(250) NOT NULL,
  `contract_signing_date` date NOT NULL,
  `grace_period_days` int(11) DEFAULT 0,
  `contract_duration_months` int(11) DEFAULT 0,
  `contract_duration_days` int(11) DEFAULT 0,
  `equip_shifts_contract` int(11) DEFAULT 0,
  `shift_contract` int(11) DEFAULT 0,
  `equip_total_contract_daily` int(11) DEFAULT 0,
  `total_contract_permonth` int(11) DEFAULT 0,
  `total_contract_units` int(11) DEFAULT 0,
  `actual_start` date,
  `actual_end` date,
  `transportation` text,
  `accommodation` text,
  `place_for_living` text,
  `workshop` text,
  `hours_monthly_target` int(11) DEFAULT 0,
  `forecasted_contracted_hours` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp ON UPDATE CURRENT_TIMESTAMP,
  `daily_work_hours` varchar(20),
  `daily_operators` varchar(20),
  `first_party` varchar(255),
  `second_party` varchar(255),
  `witness_one` varchar(255),
  `witness_two` varchar(255),
  `price_currency_contract` varchar(20),
  `paid_contract` varchar(100),
  `payment_time` varchar(50),
  `guarantees` text,
  `payment_date` date,
  `contract_status` text,
  `pause_reason` text,
  `pause_date` date,
  `resume_date` date,
  `termination_type` varchar(50),
  `termination_reason` text,
  `merged_with` int(11),
  `status` tinyint(1) DEFAULT 1,
  FOREIGN KEY (mine_id) REFERENCES mines(id),
  FOREIGN KEY (merged_with) REFERENCES contracts(id)
);
```

**المميزات:**
- دورة حياة كاملة للعقد
- إدارة الإيقاف والاستئناف
- الدمج والإنهاء
- حسابات الساعات المتعاقد عليها

#### 4.2.9 جدول معدات العقود (contractequipments)
```sql
CREATE TABLE `contractequipments` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `contract_id` int(11) NOT NULL,
  `equip_type` varchar(255) NOT NULL,
  `equip_size` int(11),
  `equip_count` int(11),
  `equip_count_basic` int(11) DEFAULT 0,
  `equip_count_backup` int(11) DEFAULT 0,
  `equip_shifts` int(11) DEFAULT 0,
  `equip_unit` varchar(50) DEFAULT 'ساعة',
  `shift1_start` time,
  `shift1_end` time,
  `shift2_start` time,
  `shift2_end` time,
  `shift_hours` int(11) DEFAULT 0,
  `equip_total_month` int(11),
  `equip_monthly_target` int(11) DEFAULT 0,
  `equip_total_contract` int(11),
  `equip_price` decimal(10,2) DEFAULT 0.00,
  `equip_operators` int(11) DEFAULT 0,
  `equip_supervisors` int(11) DEFAULT 0,
  `equip_technicians` int(11) DEFAULT 0,
  `equip_assistants` int(11) DEFAULT 0,
  `equip_price_currency` varchar(20),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
);
```

**المميزات:**
- تفاصيل المعدات المتعاقد عليها
- تقسيم المعدات (أساسي/احتياطي)
- إدارة الورديات
- التسعير والموارد البشرية

#### 4.2.10 جدول عقود الموردين (supplierscontracts)
```sql
CREATE TABLE `supplierscontracts` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `supplier_id` int(250) NOT NULL,
  `contract_signing_date` date NOT NULL,
  `grace_period_days` int(11) DEFAULT 0,
  `contract_duration_months` int(11) DEFAULT 0,
  `contract_duration_days` int(11) DEFAULT 0,
  -- ... نفس حقول contracts
  `project_id` int(255) NOT NULL DEFAULT 0,
  `mine_id` int(11),
  `project_contract_id` int(11),
  `status` tinyint(1) DEFAULT 1,
  `pause_reason` text,
  `pause_date` date,
  `resume_date` date,
  `termination_type` varchar(50),
  `termination_reason` text,
  `merged_with` int(11),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  FOREIGN KEY (project_id) REFERENCES project(id),
  FOREIGN KEY (mine_id) REFERENCES mines(id),
  FOREIGN KEY (project_contract_id) REFERENCES contracts(id),
  FOREIGN KEY (merged_with) REFERENCES supplierscontracts(id)
);
```

**الفرق عن عقود المشاريع:**
- مرتبط بمورد معين
- يحتوي على `project_id` (المورد يمكن أن يكون له عقود في عدة مشاريع)
- `project_contract_id`: ربط مع عقد المشروع الرئيسي

#### 4.2.11 جدول عقود المشغلين (drivercontracts)
```sql
CREATE TABLE `drivercontracts` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `driver_id` int(250) NOT NULL,
  -- ... نفس حقول contracts
  `project_id` int(255) NOT NULL DEFAULT 0,
  `mine_id` int(11),
  `project_contract_id` int(11),
  `status` tinyint(1) DEFAULT 1,
  FOREIGN KEY (driver_id) REFERENCES drivers(id),
  FOREIGN KEY (project_id) REFERENCES project(id),
  FOREIGN KEY (mine_id) REFERENCES mines(id),
  FOREIGN KEY (project_contract_id) REFERENCES contracts(id)
);
```

**المميزات:**
- عقد خاص بكل مشغل
- مرتبط بالمشروع والمنجم والعقد الرئيسي

#### 4.2.12 جدول التشغيل (operations)
```sql
CREATE TABLE `operations` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `equipment` varchar(100) NOT NULL,
  `equipment_type` varchar(100) DEFAULT '0',
  `equipment_category` varchar(20) NOT NULL,
  `project_id` varchar(20) NOT NULL,
  `mine_id` varchar(10) NOT NULL,
  `contract_id` varchar(10) NOT NULL,
  `supplier_id` varchar(10) NOT NULL,
  `start` varchar(50) NOT NULL,
  `end` varchar(50) NOT NULL,
  `reason` text NOT NULL,
  `days` varchar(20) NOT NULL,
  `total_equipment_hours` decimal(10,2) DEFAULT 0.00,
  `shift_hours` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1
);
```

**المميزات:**
- ربط المعدة بالمشروع/المنجم/العقد
- تتبع فترة التشغيل
- تصنيف المعدات (أساسي/احتياطي)
- إجمالي الساعات المخططة

#### 4.2.13 جدول ساعات العمل (timesheet)
```sql
CREATE TABLE `timesheet` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `operator` varchar(20) NOT NULL,
  `driver` varchar(20) NOT NULL,
  `shift` varchar(100) NOT NULL,
  `date` varchar(30) NOT NULL,
  `shift_hours` float DEFAULT 0,
  `executed_hours` float DEFAULT 0,
  `bucket_hours` float DEFAULT 0,
  `jackhammer_hours` float DEFAULT 0,
  `extra_hours` float DEFAULT 0,
  `extra_hours_total` float DEFAULT 0,
  `standby_hours` float DEFAULT 0,
  `dependence_hours` float DEFAULT 0,
  `total_work_hours` float DEFAULT 0,
  `work_notes` text,
  `hr_fault` float DEFAULT 0,
  `maintenance_fault` float DEFAULT 0,
  `marketing_fault` float DEFAULT 0,
  `approval_fault` float DEFAULT 0,
  `other_fault_hours` float DEFAULT 0,
  `total_fault_hours` float DEFAULT 0,
  `fault_notes` text,
  `start_seconds` int(11) DEFAULT 0,
  `start_minutes` int(11) DEFAULT 0,
  `start_hours` int(11) DEFAULT 0,
  `end_seconds` int(11) DEFAULT 0,
  `end_minutes` int(11) DEFAULT 0,
  `end_hours` int(11) DEFAULT 0,
  `counter_diff` varchar(255) DEFAULT '0',
  `fault_type` varchar(255),
  `fault_department` varchar(255),
  `fault_part` varchar(255),
  `fault_details` text,
  `general_notes` text,
  `operator_hours` float DEFAULT 0,
  `machine_standby_hours` float DEFAULT 0,
  `jackhammer_standby_hours` float DEFAULT 0,
  `bucket_standby_hours` float DEFAULT 0,
  `extra_operator_hours` float DEFAULT 0,
  `operator_standby_hours` float DEFAULT 0,
  `operator_notes` text,
  `type` varchar(20) NOT NULL,
  `user_id` int(50) DEFAULT 0,
  `time_notes` text DEFAULT 'لاتوجد ملاحظات',
  `status` tinyint(1) DEFAULT 1
);
```

**المميزات:**
- تتبع دقيق لساعات العمل
- تفاصيل الأعطال والتوقفات
- العداد (البداية والنهاية)
- ساعات الاستعداد والإضافية
- فصل بين ساعات المعدة والمشغل

#### 4.2.14 جداول السجلات (Audit Tables)
```sql
-- سجل إجراءات عقود المشاريع
CREATE TABLE `contract_notes` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `contract_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `user_id` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11),
  FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
);

-- سجل إجراءات عقود الموردين
CREATE TABLE `supplier_contract_notes` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `contract_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11),
  FOREIGN KEY (contract_id) REFERENCES supplierscontracts(id) ON DELETE CASCADE
);

-- سجل إجراءات عقود المشغلين
CREATE TABLE `driver_contract_notes` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `contract_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (contract_id) REFERENCES drivercontracts(id) ON DELETE CASCADE
);
```

### 4.3 العلاقات بين الجداول

```
clients (1) ←→ (∞) project
                     ↓ (1..∞)
                    mines
                     ↓ (1..∞)
                  contracts ← contract_notes
                     ↓ (1..∞)
              contractequipments

suppliers (1) ←→ (∞) equipments
          ↓ (1..∞)
    supplierscontracts ← supplier_contract_notes
          ↓ (1..∞)
  suppliercontractequipments

drivers (1) ←→ (∞) equipment_drivers ←→ (∞) equipments
        ↓ (1..∞)
  drivercontracts ← driver_contract_notes
        ↓ (1..∞)
  drivercontractequipments

equipments (1) ←→ (∞) operations ←→ (1) project
                                 ←→ (1) mine
                                 ←→ (1) contract
                 ↓ (1..∞)
               timesheet ←→ (1) driver
```

### 4.4 الفهارس (Indexes)
الجداول تحتوي على فهارس لتحسين الأداء:
- **Primary Keys**: على كل جدول
- **Foreign Keys**: للعلاقات بين الجداول
- **Unique Indexes**: على الحقول الفريدة (client_code, mine_code, username)
- **Regular Indexes**: على حقول البحث المتكررة (status, project_id, mine_id)

---

## 🔐 5. نظام الصلاحيات والأدوار {#نظام-الصلاحيات}

### 5.1 أنواع المستخدمين

| كود الدور | اسم الدور | الصلاحيات |
|-----------|-----------|-----------|
| **-1** | **الإدارة العليا** | وصول كامل لجميع الميزات |
| **1** | **مدير المشاريع** | إدارة المشاريع، العقود، المستخدمين، التقارير |
| **2** | **مدير الموردين** | إدارة الموردين، عقود الموردين، التقارير |
| **3** | **مدير المشغلين** | إدارة المشغلين، المعدات، التقارير |
| **4** | **مدير الأسطول** | إدارة المعدات، التشغيل، التقارير |
| **5** | **مدير الموقع** | إدارة مستخدمي المشروع، ساعات العمل، التقارير |
| **6** | **مدخل ساعات** | إدخال ساعات العمل فقط |
| **7** | **مراجع ساعات مورد** | مراجعة واعتماد ساعات الموردين |
| **8** | **مراجع ساعات مشغل** | مراجعة واعتماد ساعات المشغلين |
| **9** | **مراجع الأعطال** | مراجعة واعتماد الأعطال |
| **10** | **حركة وتشغيل** | مراقبة حركة المعدات (قراءة فقط للمعدات، إدارة التشغيل) |

### 5.2 مصفوفة الصلاحيات

| الميزة | -1 | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 |
|-------|----|----|----|----|----|----|----|----|----|----|-----|
| **العملاء** |
| إدارة العملاء | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| عرض العملاء | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **المشاريع** |
| إدارة المشاريع | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| إدارة المناجم | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **العقود** |
| عقود المشاريع | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| عقود الموردين | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| عقود المشغلين | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **الموردين** |
| إدارة الموردين | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **المعدات** |
| إدارة المعدات (كتابة) | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| عرض المعدات (قراءة) | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **المشغلين** |
| إدارة المشغلين | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **التشغيل** |
| إدارة التشغيل | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **ساعات العمل** |
| إدخال الساعات | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| مراجعة الساعات | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ |
| **المستخدمين** |
| إدارة المستخدمين | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| مستخدمي المشروع | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **التقارير** |
| تقارير العقود | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| تقارير الساعات | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

### 5.3 التحكم في الوصول

#### 5.3.1 فحص الجلسة (Session Check)
كل صفحة تبدأ بفحص الجلسة:
```php
<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
```

#### 5.3.2 فحص الصلاحية (Role Check)
```php
// في sidebar.php - إظهار القوائم حسب الصلاحية
if ($_SESSION['user']['role'] == "1") { 
    // عرض قوائم مدير المشاريع
}

if ($_SESSION['user']['role'] == "2") { 
    // عرض قوائم مدير الموردين
}
```

#### 5.3.3 تقييد العمليات
```php
// في equipments.php - منع دور 10 من التعديل
if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10") {
    $success_msg = "❌ ليس لديك صلاحية لتعديل أو إضافة المعدات";
    goto skip_save;
}
```

#### 5.3.4 تصفية البيانات حسب المشروع
```php
// في oprators.php - تقييد مستخدم دور 10 لمشروعه فقط
$is_role10 = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10";
$user_project_id = $is_role10 ? intval($_SESSION['user']['project_id']) : 0;

if ($is_role10) {
    $selected_project_id = $user_project_id;
}
```

### 5.4 تعيين المستخدمين للمشاريع

مدراء المواقع (دور 5) ومستخدمو الحركة والتشغيل (دور 10) يتم تعيينهم لمشروع/منجم/عقد معين:

```php
// في users.php - تعيين المشروع عند الإضافة/التعديل
$project = (($role == "5" || $role == "10") && !empty($_POST['project_id'])) 
    ? intval($_POST['project_id']) : 0;
$mine = (($role == "5" || $role == "10") && !empty($_POST['mine_id'])) 
    ? intval($_POST['mine_id']) : 0;
$contract = (($role == "5" || $role == "10") && !empty($_POST['contract_id'])) 
    ? intval($_POST['contract_id']) : 0;
```

---

## 📦 6. الوحدات الرئيسية {#الوحدات-الرئيسية}

### 6.1 وحدة المصادقة (Authentication)

#### 6.1.1 تسجيل الدخول (index.php)
**المميزات:**
- حماية من هجمات Brute Force (5 محاولات، 15 دقيقة حظر)
- رمز CSRF للحماية من هجمات Cross-Site Request Forgery
- رؤوس أمان HTTP (X-Frame-Options, CSP, X-Content-Type-Options)
- Prepared Statements لمنع SQL Injection
- جلسات آمنة مع HttpOnly cookies

**الكود الرئيسي:**
```php
// توليد رمز CSRF
function generate_csrf() { 
    if (empty($_SESSION['csrf_token'])) 
        $_SESSION['csrf_token']=bin2hex(openssl_random_pseudo_bytes(32)); 
    return $_SESSION['csrf_token']; 
}

// التحقق من الـ CSRF
function verify_csrf($t) { 
    return isset($_SESSION['csrf_token'])&&$_SESSION['csrf_token']===$t; 
}

// فحص الحظر
function is_locked_out() {
  global $max_attempts,$lockout_minutes;
  if (!empty($_SESSION['last_attempt_time']) && $_SESSION['login_attempts'] >= $max_attempts) {
    if ((time()-$_SESSION['last_attempt_time']) < ($lockout_minutes*60)) return true;
    $_SESSION['login_attempts']=0; $_SESSION['last_attempt_time']=null;
  }
  return false;
}

// المصادقة
$stmt=mysqli_prepare($conn,"SELECT id,name,username,password,phone,role,project_id,parent_id,created_at,updated_at FROM users WHERE username=? LIMIT 1");
mysqli_stmt_bind_param($stmt,"s",$u); 
mysqli_stmt_execute($stmt);
$res=mysqli_stmt_get_result($stmt);
```

**رؤوس الأمان:**
```php
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
```

#### 6.1.2 إدارة الجلسات
```php
// إعدادات الجلسة الآمنة
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
$cookieParams = session_get_cookie_params();
session_set_cookie_params(0, $cookieParams['path'], $cookieParams['domain'], $secure, true);
ini_set('session.use_strict_mode', 1);
session_start();

// تجديد معرف الجلسة عند النجاح
session_regenerate_id(true);
```

### 6.2 وحدة العملاء (Clients)

#### 6.2.1 إدارة العملاء (clients.php)
**الوظائف:**
- إضافة/تعديل/حذف العملاء
- كود فريد لكل عميل
- معلومات الاتصال (هاتف، بريد إلكتروني، واتساب)
- تصنيف العملاء (نوع الكيان، قطاع)
- حالة العميل (نشط/متوقف)

**البيانات المطلوبة:**
- كود العميل (client_code) - **إجباري وفريد**
- اسم العميل (client_name) - **إجباري**
- نوع الكيان (entity_type) - اختياري
- تصنيف القطاع (sector_category) - اختياري
- معلومات الاتصال - اختياري

#### 6.2.2 استيراد العملاء (import_clients_excel.php)
**المميزات:**
- استيراد جماعي من Excel/CSV
- التحقق من صحة البيانات
- معالجة الأخطاء والتكرار
- استخدام PHPSpreadsheet

**التنسيق المطلوب:**
| client_code | client_name | entity_type | sector_category | phone | email | whatsapp | status |
|-------------|-------------|-------------|-----------------|-------|-------|----------|--------|

### 6.3 وحدة المشاريع (Projects)

#### 6.3.1 إدارة المشاريع (oprationprojects.php)
**الوظائف:**
- إضافة/تعديل/حذف المشاريع
- ربط المشروع بعميل
- معلومات جغرافية (إحداثيات، ولاية، منطقة)
- كود المشروع
- حالة المشروع

**البيانات:**
- اسم المشروع - **إجباري**
- العميل المرتبط (company_client_id)
- كود المشروع (project_code)
- الموقع (location)
- الولاية (state)
- المنطقة (region)
- أقرب سوق (nearest_market)
- خطوط العرض والطول (latitude, longitude)
- الفئة (category)
- القطاع الفرعي (sub_sector)

#### 6.3.2 إدارة المناجم (project_mines.php)
**الوظائف:**
- إضافة/تعديل/حذف مناجم المشروع
- كود فريد لكل منجم
- تفاصيل شاملة عن المنجم

**البيانات:**
- اسم المنجم - **إجباري**
- كود المنجم - **إجباري وفريد**
- نوع المنجم - **إجباري**
- نوع الملكية - **إجباري**
- اسم مدير المنجم
- نوع المعدن (ذهب، فضة، نحاس...)
- مساحة المنجم ووحدة القياس
- عمق التعدين
- طبيعة التعاقد
- ملاحظات

**أنواع المناجم:**
- حفرة مفتوحة
- تحت أرضي
- آبار
- مهجور
- مجمع معالجة/تركيز
- موقع تخزين/مستودع
- أخرى (مع تحديد)

**أنواع الملكية:**
- تعدين أهلي/تقليدي
- شركة سودانية خاصة
- شركة حكومية/قطاع عام
- شركة أجنبية
- مشروع مشترك (سوداني-أجنبي)
- أخرى (مع تحديد)

### 6.4 وحدة العقود (Contracts)

#### 6.4.1 عقود المشاريع (contracts.php)

**الوظائف:**
- إنشاء عقد جديد لمنجم
- تحديد معدات العقد
- حساب الساعات المستهدفة
- إدارة دورة حياة العقد

**البيانات الأساسية:**
- المنجم المرتبط - **إجباري**
- تاريخ التوقيع - **إجباري**
- فترة السماح (grace_period_days)
- مدة العقد بالأيام/الأشهر
- تاريخ البدء والانتهاء الفعلي
- عدد الورديات للعقد
- ساعات الوردية
- الساعات اليومية المطلوبة
- وحدات العمل الشهرية
- إجمالي الساعات المتعاقد عليها

**معلومات المسؤوليات:**
- النقل (Transportation)
- السكن (Accommodation)
- مكان الإقامة (Place for Living)
- الورشة (Workshop)

**معلومات الأطراف:**
- الطرف الأول (first_party)
- الطرف الثاني (second_party)
- الشاهد الأول (witness_one)
- الشاهد الثاني (witness_two)

**المعلومات المالية:**
- العملة (price_currency_contract): دولار/جنيه
- المبلغ المدفوع (paid_contract)
- وقت الدفع (payment_time): مقدم/مؤخر
- الضمانات (guarantees)
- تاريخ الدفع (payment_date)

#### 6.4.2 معدات العقد (contractequipments_handler.php)

**الوظائف:**
- إضافة معدات متعددة للعقد
- تقسيم المعدات (أساسي/احتياطي)
- تحديد الورديات والساعات
- التسعير والموارد البشرية

**البيانات لكل معدة:**
- نوع المعدة - **إجباري**
- حجم المعدة
- عدد المعدات - **إجباري**
- عدد المعدات الأساسية
- عدد المعدات الاحتياطية
- عدد الورديات
- وحدة القياس (ساعة/طن/متر)
- أوقات الورديات (بداية/نهاية)
- ساعات الوردية
- الوحدات اليومية
- الهدف الشهري
- إجمالي الوحدات للعقد
- السعر للوحدة
- العملة
- عدد المشغلين/المشرفين/الفنيين/المساعدين

#### 6.4.3 إجراءات العقد (contract_actions_handler.php)

**الوظائف:**
نقطة نهاية JSON API لإدارة دورة حياة العقد:

1. **التجديد (renew)**
   - معلمات: `contract_id`, `renewal_months`, `renewal_days`, `renewal_reason`
   - منطق: إضافة فترة جديدة للعقد الحالي
   - تسجيل: يتم تسجيل الإجراء في `contract_notes`

2. **التسوية (settle)**
   - معلمات: `contract_id`, `settlement_amount`, `settlement_date`, `settlement_notes`
   - منطق: تسجيل تسوية مالية
   - تسجيل: يتم تسجيل التسوية في السجلات

3. **الإيقاف المؤقت (pause)**
   - معلمات: `contract_id`, `pause_reason`, `pause_date`
   - منطق: تحديث `contract_status='paused'`
   - تسجيل: يتم تسجيل سبب الإيقاف

4. **الاستئناف (resume)**
   - معلمات: `contract_id`, `resume_date`
   - منطق: تحديث `contract_status='active'`
   - تسجيل: يتم تسجيل الاستئناف

5. **الإنهاء (terminate)**
   - معلمات: `contract_id`, `termination_type` (amicable/hardship), `termination_reason`, `termination_date`
   - منطق: تحديث `contract_status='terminated'` + `status=0`
   - تسجيل: يتم تسجيل الإنهاء مع النوع والسبب

6. **الدمج (merge)**
   - معلمات: `old_contract_id`, `new_contract_id`, `merge_reason`
   - منطق: 
     - تحديث `old_contract`: `status=0`, `merged_with=new_contract_id`
     - نقل المعدات إلى العقد الجديد (إن وُجدت)
   - تسجيل: يتم تسجيل الدمج في كلا العقدين

**نمط الاستخدام:**
```php
// الطلب
$.ajax({
    url: 'contract_actions_handler.php',
    method: 'POST',
    data: {
        action: 'renew',
        contract_id: 123,
        renewal_months: 6,
        renewal_reason: 'تمديد مدة العقد'
    },
    dataType: 'json',
    success: function(response) {
        if (response.success) {
            // نجح الإجراء
        }
    }
});
```

#### 6.4.4 عقود الموردين (supplierscontracts.php)

**الاختلافات عن عقود المشاريع:**
- مرتبطة بمورد معين (supplier_id)
- تحتوي على معرف المشروع (project_id) - المورد يمكن أن يكون له عقود في عدة مشاريع
- مرتبطة بعقد المشروع الأساسي (project_contract_id)
- نفس البنية والحقول كعقود المشاريع
- لها جدول معدات خاص (suppliercontractequipments)
- لها معالج إجراءات خاص (supplier_contract_actions_handler.php)

**سير العمل:**
1. اختيار المورد
2. اختيار المشروع
3. اختيار المنجم
4. اختيار عقد المشروع
5. إدخال تفاصيل العقد
6. إضافة المعدات

#### 6.4.5 عقود المشغلين (drivercontracts.php)

**الاختلافات:**
- مرتبطة بمشغل معين (driver_id)
- تحتوي على معرف المشروع (project_id)
- مرتبطة بعقد المشروع الأساسي (project_contract_id)
- نفس البنية والحقول كعقود المشاريع
- لها جدول معدات خاص (drivercontractequipments)
- لها معالج إجراءات خاص (driver_contract_actions_handler.php)

### 6.5 وحدة الموردين (Suppliers)

#### 6.5.1 إدارة الموردين (suppliers.php)

**الوظائف:**
- إضافة/تعديل/حذف الموردين
- معلومات شاملة عن المورد
- البيانات القانونية والمالية

**البيانات:**

**1. المعلومات الأساسية:**
- اسم المورد - **إجباري**
- كود المورد (supplier_code)
- نوع المورد - **إجباري**
  - فرد
  - شركة
  - وسيط
  - مالك
  - جهة حكومية
- طبيعة التعامل
- أنواع المعدات (اختيار متعدد)

**2. البيانات القانونية:**
- رقم التسجيل التجاري
- نوع الهوية
- رقم الهوية
- تاريخ انتهاء الهوية

**3. البيانات التواصلية:**
- البريد الإلكتروني
- الهاتف - **إجباري**
- هاتف بديل
- العنوان الكامل
- جهة الاتصال الأساسية
- هاتف جهة الاتصال

**4. الحالة المالية:**
- حالة التسجيل المالي
  - مسجل رسمياً
  - غير مسجل
  - تحت التسجيل
  - معفى من التسجيل

### 6.6 وحدة المعدات (Equipments)

#### 6.6.1 إدارة المعدات (equipments.php)

**الوظائف:**
- إضافة/تعديل/عرض المعدات
- معلومات تفصيلية شاملة
- ربط المعدة بالمورد
- تتبع الحالة والصيانة
- التحقق من عدم تجاوز العدد المتعاقد عليه

**البيانات:**

**1. المعلومات الأساسية:**
- المورد - **إجباري**
- كود المعدة - **إجباري**
- نوع المعدة - **إجباري**
- اسم المعدة - **إجباري**

**2. المعلومات التعريفية:**
- الرقم التسلسلي (serial_number)
- رقم الهيكل (chassis_number)
- الماركة/الشركة المصنعة (manufacturer)
- الموديل/الطراز (model)
- سنة الصنع (manufacturing_year)
- سنة الاستيراد (import_year)

**3. الحالة الفنية:**
- حالة المعدة (equipment_condition)
- ساعات التشغيل (operating_hours)
- حالة المحرك (engine_condition)
- حالة الإطارات (tires_condition)

**4. بيانات الملكية:**
- اسم المالك الفعلي (actual_owner_name)
- نوع المالك (owner_type)
- هاتف المالك (owner_phone)
- علاقة المالك بالمورد (owner_supplier_relation)

**5. الوثائق والتسجيلات:**
- رقم الترخيص (license_number)
- جهة الترخيص (license_authority)
- تاريخ انتهاء الترخيص (license_expiry_date)
- رقم شهادة الفحص (inspection_certificate_number)
- تاريخ آخر فحص (last_inspection_date)

**6. الموقع والتوفر:**
- الموقع الحالي (current_location)
- حالة التوفر (availability_status)

**7. البيانات المالية:**
- القيمة المقدرة (estimated_value)
- سعر التأجير اليومي (daily_rental_price)
- سعر التأجير الشهري (monthly_rental_price)
- التأمين/الضمان (insurance_status)

**8. الصيانة والملاحظات:**
- تاريخ آخر صيانة (last_maintenance_date)
- ملاحظات عامة (general_notes)

**التحقق من العدد المتعاقد عليه:**
```php
// عند إضافة معدة جديدة، يتم التحقق من عدم تجاوز العدد المتعاقد عليه
$supplier_contract_query = "SELECT sc.id, sce.equip_count
    FROM supplierscontracts sc
    JOIN suppliercontractequipments sce ON sc.id = sce.contract_id
    WHERE sc.supplier_id = $suppliers 
    AND sce.equip_type = '$type'
    AND sc.status = 1
    LIMIT 1";
```

#### 6.6.2 أنواع المعدات (equipments_types.php)

**الوظائف:**
- إدارة أنواع المعدات
- حالة النوع (نشط/غير نشط)

**الأنواع الشائعة:**
- حفار
- قلاب
- خرامة
- لودر
- جريدر
- ...إلخ

#### 6.6.3 ربط المعدات بالسائقين (equipment_drivers)

**الوظائف:**
- ربط معدة بسائق
- تحديد فترة الربط (بداية/نهاية)
- تتبع التاريخ

**البيانات:**
- معدة - **إجباري**
- سائق - **إجباري**
- تاريخ البداية - **إجباري**
- تاريخ النهاية - **إجباري**

### 6.7 وحدة المشغلين (Drivers)

#### 6.7.1 إدارة المشغلين (drivers.php)

**الوظائف:**
- إضافة/تعديل/حذف المشغلين
- معلومات شاملة عن كل مشغل
- تقييم الأداء والمهارات
- بيانات الصحة والسلامة

**البيانات:**

**1. المعلومات الأساسية والتعريفية:**
- الاسم - **إجباري**
- كود المشغل (driver_code)
- الاسم المستعار (nickname)

**2. بيانات الهوية والتوثيق:**
- نوع الهوية (identity_type)
- رقم الهوية (identity_number)
- تاريخ انتهاء الهوية (identity_expiry_date)

**3. رخصة القيادة والمهارات:**
- رقم الرخصة (license_number)
- نوع الرخصة (license_type)
- تاريخ انتهاء الرخصة (license_expiry_date)
- جهة إصدار الرخصة (license_issuer)

**4. التخصص والمهارات:**
- المعدات المتخصص فيها (specialized_equipment) - اختيار متعدد

**5. سنوات الخبرة والكفاءة:**
- سنوات في المجال (years_in_field)
- سنوات على المعدة (years_on_equipment)
- مستوى المهارة (skill_level)
- الشهادات (certificates)

**6. علاقة العمل والتبعية:**
- المالك/المشرف (owner_supervisor)
- المورد التابع له (supplier_id)
- الانتماء الوظيفي (employment_affiliation)
- نوع الراتب (salary_type)
- الراتب الشهري (monthly_salary)

**7. البيانات التواصلية:**
- البريد الإلكتروني (email)
- الهاتف - **إجباري**
- هاتف بديل (phone_alternative)
- العنوان (address)

**8. تقييم الأداء والسلوك:**
- تقييم الأداء (performance_rating)
- سجل السلوك (behavior_record)
- سجل الحوادث (accident_record)

**9. الصحة والسلامة:**
- الحالة الصحية (health_status)
- المشاكل الصحية (health_issues)
- حالة التطعيمات (vaccinations_status)

**10. المراجع والسجل:**
- صاحب العمل السابق (previous_employer)
- مدة العمل (employment_duration)
- جهة اتصال للمرجع (reference_contact)
- ملاحظات عامة (general_notes)

**11. الحالة والتفعيل:**
- حالة المشغل (driver_status)
- تاريخ البدء (start_date)
- الحالة العامة (status)

#### 6.7.2 استيراد المشغلين (import_drivers_excel.php)

**المميزات:**
- استيراد جماعي من Excel/CSV
- التحقق من صحة البيانات
- معالجة الأخطاء والتكرار

### 6.8 وحدة التشغيل (Operations)

#### 6.8.1 إدارة التشغيل (oprators.php)

**الوظائف:**
- ربط المعدة بالمشروع/المنجم/العقد
- تحديد فترة التشغيل
- تتبع الساعات المخططة
- إدارة حالة التشغيل
- عرض إحصائيات العقد والموردين

**البيانات:**
- المشروع - **إجباري** (محدد مسبقاً)
- المنجم - **إجباري**
- العقد - **إجباري** (يتم تحميله حسب المنجم)
- المورد - **إجباري** (يتم تحميله حسب العقد)
- نوع المعدة - **إجباري**
- المعدة - **إجباري** (يتم تحميلها حسب النوع والمورد)
- تصنيف المعدة - **إجباري** (أساسي/احتياطي)
- تاريخ البداية - **إجباري**
- تاريخ النهاية - **إجباري**
- إجمالي ساعات العمل للآلية
- عدد ساعات الوردية
- الحالة:
  - 1: تعمل
  - 0: متاحة
  - 3: متوقفة
  - 4: معطلة

**التحقق من العدد المتعاقد عليه:**
عند إضافة تشغيل جديد، يتم:
1. جلب عدد المعدات المتعاقد عليها (أساسي + احتياطي)
2. حساب عدد المعدات المضافة حالياً
3. التحقق من عدم تجاوز العدد المسموح

**إحصائيات العقد:**
يتم عرض جدول بجميع الموردين المرتبطين بالعقد مع:
- اسم المورد
- الساعات المتعاقد عليها
- عدد المعدات المتعاقد عليها
- عدد المعدات الأساسية
- عدد المعدات الاحتياطية
- عدد المعدات المضافة
- المتبقي للإضافة
- توزيع المعدات والساعات (رسم بياني)

#### 6.8.2 اختيار المشروع (select_project.php)

**الوظائف:**
- اختيار المشروع للعمل عليه
- حفظ الاختيار في الجلسة
- إعادة التوجيه لصفحة التشغيل

### 6.9 وحدة ساعات العمل (Timesheet)

#### 6.9.1 نوع المعدة (timesheet_type.php)

**الوظائف:**
- اختيار نوع المعدة (حفار/قلاب)
- إعادة التوجيه للصفحة المناسبة

#### 6.9.2 إدخال ساعات العمل (timesheet.php)

**الوظائف:**
- إدخال ساعات العمل اليومية
- تسجيل تفاصيل شاملة
- حساب تلقائي للإجماليات
- نماذج مختلفة حسب نوع المعدة

**البيانات المشتركة:**
- الآلية (operator) - **إجباري**
- السائق (driver) - **إجباري** (يتم تحميله حسب الآلية)
- الوردية (shift) - **إجباري** (D: صباحية، N: مسائية)
- التاريخ (date) - **إجباري**

**للحفارات (type=1):**

**ساعات العمل:**
- ساعات الوردية (shift_hours)
- عداد البداية (start_hours:start_minutes:start_seconds)
- الساعات المنفذة (executed_hours)
- ساعات جردل (bucket_hours)
- ساعات جاك همر (jackhammer_hours)
- ساعات إضافية (extra_hours)
- مجموع الساعات الإضافية (extra_hours_total)
- ساعات الاستعداد بسبب العميل (standby_hours)
- ساعات الاستعداد اعتماد (dependence_hours)
- **مجموع ساعات العمل** (total_work_hours) - محسوب تلقائياً
- ملاحظات ساعات العمل (work_notes)

**ساعات الأعطال:**
- عطل HR (hr_fault)
- عطل صيانة (maintenance_fault)
- عطل تسويق (marketing_fault)
- عطل اعتماد (approval_fault)
- ساعات أعطال أخرى (other_fault_hours)
- **مجموع ساعات التعطل** (total_fault_hours) - محسوب تلقائياً
- ملاحظات ساعات الأعطال (fault_notes)

**تفاصيل الأعطال:**
- عداد النهاية (end_hours:end_minutes:end_seconds)
- فرق العداد (counter_diff) - محسوب تلقائياً
- نوع العطل (fault_type)
- قسم العطل (fault_department)
- الجزء المعطل (fault_part)
- تفاصيل العطل (fault_details)
- ملاحظات عامة (general_notes)

**ساعات عمل المشغل:**
- ساعات عمل المشغل (operator_hours)
- ساعات استعداد الآلية (machine_standby_hours)
- ساعات استعداد الجاك همر (jackhammer_standby_hours)
- ساعات استعداد الجردل (bucket_standby_hours)
- الساعات الإضافية (extra_operator_hours)
- ساعات استعداد المشغل (operator_standby_hours)
- ملاحظات المشغل (operator_notes)

**للقلابات (type=2):**
- نفس البنية مع اختلافات طفيفة
- لا توجد ساعات جردل/جاك همر
- التركيز على ساعات النقل

**الحسابات التلقائية:**
```javascript
// حساب مجموع ساعات العمل
total_work_hours = executed_hours + bucket_hours + jackhammer_hours + 
                  extra_hours + standby_hours + dependence_hours

// حساب مجموع ساعات الأعطال
total_fault_hours = hr_fault + maintenance_fault + marketing_fault + 
                   approval_fault + other_fault_hours

// حساب فرق العداد
counter_diff = (end_hours * 3600 + end_minutes * 60 + end_seconds) - 
              (start_hours * 3600 + start_minutes * 60 + start_seconds)
```

#### 6.9.3 عرض ساعات العمل (view_timesheet.php)

**الوظائف:**
- عرض جميع ساعات العمل المسجلة
- الفلترة حسب التاريخ/المعدة/السائق
- تصدير البيانات (Excel/PDF)

#### 6.9.4 تفاصيل ساعات العمل (timesheet_details.php)

**الوظائف:**
- عرض تفاصيل كاملة لساعات عمل معينة
- إمكانية التعديل والمراجعة

### 6.10 وحدة التقارير (Reports)

#### 6.10.1 التقرير الرئيسي (reports.php)

**الوظائف:**
- تقرير شامل لساعات التشغيل
- فلترة متعددة المستويات
- تصدير البيانات

**الفلاتر:**
- المورد (supplier)
- المشروع (project)
- المنجم (mine) - يتم تحميله حسب المشروع
- العقد (contract) - يتم تحميله حسب المنجم

**البيانات المعروضة:**
- المشروع
- المنجم (مع الكود)
- العقد (مع التاريخ)
- المورد
- إجمالي ساعات التشغيل المنفذة

**الاستعلام الرئيسي:**
```sql
SELECT 
    s.name AS supplier_name,
    p.name AS project_name,
    IFNULL(m.mine_name, '') AS mine_name,
    IFNULL(m.mine_code, '') AS mine_code,
    c.id AS contract_id,
    c.contract_signing_date,
    SUM(t.executed_hours) AS total_hours
FROM timesheet t
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id   
JOIN suppliers s ON e.suppliers = s.id
JOIN project p ON o.project_id = p.id
LEFT JOIN mines m ON o.mine_id = m.id
LEFT JOIN contracts c ON o.contract_id = c.id
WHERE t.status = 1 AND o.status = 1
GROUP BY s.id, p.id, m.id, c.id
ORDER BY p.name, m.mine_name, s.name
```

#### 6.10.2 تقارير العقود (contract_report.php)

**الوظائف:**
- تقارير تفصيلية عن العقود
- مقارنة الساعات المتعاقد عليها مع المنفذة
- حالة العقود

#### 6.10.3 تقارير متنوعة

**لمدير الموقع (role=5):**
- ساعات اليوم (deliy.php)
- ساعات السائق (deriver.php)
- ساعات العمل اليومية (timesheetdeliy.php)

**لمدير المشغلين (role=3):**
- ساعات السائق (deriver.php)

**لمدير الموردين (role=2):**
- ساعات العمل اليومية (timesheetdeliy.php)

**لمدير الأسطول (role=4):**
- ساعات اليوم (deliy.php)

**لمدير المشاريع (role=1):**
- العقد (contract_report.php)
- إحصائيات العقد (contractall.php)
- إحصائيات عقود الموردين والمشغلين (driverAndsupplerscontract.php)

### 6.11 وحدة المستخدمين (Users)

#### 6.11.1 إدارة المستخدمين (main/users.php)

**الوظائف:**
- إضافة/تعديل/حذف المستخدمين
- تعيين الأدوار والصلاحيات
- ربط بالمشروع/المنجم/العقد (للأدوار 5 و 10)
- التحقق من تكرار اسم المستخدم (AJAX)

**البيانات:**
- الاسم الثلاثي - **إجباري**
- اسم المستخدم - **إجباري وفريد**
- كلمة المرور - **إجباري عند الإضافة**
- الدور/الصلاحية - **إجباري**
- رقم الهاتف - **إجباري**
- المشروع - **إجباري للأدوار 5 و 10**
- المنجم - **إجباري للأدوار 5 و 10**
- العقد - **إجباري للأدوار 5 و 10**

**التحقق من اسم المستخدم:**
```php
// التحقق من عدم التكرار عند الإضافة
$check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' LIMIT 1");
if (mysqli_num_rows($check) > 0) {
    echo "<script>alert('⚠️ اسم المستخدم موجود مسبقاً!');</script>";
}

// التحقق عند التعديل (يتجاهل السجل الحالي)
$check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' AND id != '$uid' LIMIT 1");
```

#### 6.11.2 مستخدمو المشروع (main/project_users.php)

**الوظائف:**
- إدارة المستخدمين الفرعيين التابعين لمدير الموقع
- نفس الوظائف مع تقييد على المستخدمين التابعين

---

## 🔄 7. سير العمل {#سير-العمل}

### 7.1 سير عمل المشروع

```
1. إضافة العميل (clients)
   ↓
2. إنشاء المشروع (project) + ربطه بالعميل
   ↓
3. إضافة المناجم للمشروع (mines)
   ↓
4. إنشاء عقد المشروع (contracts)
   ├─ تحديد المنجم
   ├─ إدخال البيانات الأساسية
   └─ إضافة المعدات المطلوبة (contractequipments)
   ↓
5. إدارة دورة حياة العقد
   ├─ التجديد (renew)
   ├─ التسوية (settle)
   ├─ الإيقاف المؤقت (pause)
   ├─ الاستئناف (resume)
   ├─ الإنهاء (terminate)
   └─ الدمج (merge)
```

### 7.2 سير عمل المورد

```
1. إضافة المورد (suppliers)
   ├─ معلومات أساسية
   ├─ بيانات قانونية
   └─ معلومات تواصل
   ↓
2. إنشاء عقد المورد (supplierscontracts)
   ├─ اختيار المشروع
   ├─ اختيار المنجم
   ├─ ربط بعقد المشروع
   └─ إضافة معدات العقد (suppliercontractequipments)
   ↓
3. إضافة المعدات الفعلية (equipments)
   ├─ معلومات تفصيلية
   ├─ التحقق من العدد المتعاقد عليه
   └─ حالة المعدة
```

### 7.3 سير عمل المشغل

```
1. إضافة المشغل (drivers)
   ├─ معلومات شخصية
   ├─ بيانات الرخصة
   ├─ المهارات والخبرات
   └─ التقييم والصحة
   ↓
2. إنشاء عقد المشغل (drivercontracts)
   ├─ اختيار المشروع
   ├─ اختيار المنجم
   ├─ ربط بعقد المشروع
   └─ إضافة معدات العقد (drivercontractequipments)
   ↓
3. ربط المشغل بالمعدة (equipment_drivers)
   ├─ تحديد المعدة
   └─ فترة الربط
```

### 7.4 سير عمل التشغيل

```
1. اختيار المشروع (select_project.php)
   ↓
2. إنشاء تشغيل جديد (oprators.php)
   ├─ اختيار المنجم
   ├─ اختيار العقد (يتم تحميله حسب المنجم)
   ├─ اختيار المورد (يتم تحميله حسب العقد)
   ├─ اختيار نوع المعدة
   ├─ اختيار المعدة (يتم تحميلها حسب النوع والمورد)
   ├─ تحديد التصنيف (أساسي/احتياطي)
   ├─ التحقق من العدد المتاح
   ├─ تحديد فترة التشغيل
   └─ إدخال الساعات المخططة
   ↓
3. عرض إحصائيات العقد
   ├─ جدول الموردين
   ├─ الساعات المتعاقد عليها
   ├─ المعدات المتعاقد عليها
   ├─ المعدات المضافة
   └─ المتبقي للإضافة
```

### 7.5 سير عمل ساعات العمل

```
1. اختيار نوع المعدة (timesheet_type.php)
   ├─ حفار (type=1)
   └─ قلاب (type=2)
   ↓
2. إدخال ساعات العمل (timesheet.php)
   ├─ اختيار الآلية (من التشغيلات النشطة للمشروع)
   ├─ اختيار السائق (يتم تحميله حسب الآلية)
   ├─ تحديد الوردية والتاريخ
   ├─ إدخال ساعات العمل
   │   ├─ عداد البداية
   │   ├─ الساعات المنفذة
   │   ├─ ساعات الملحقات (جردل/جاك همر)
   │   ├─ ساعات الاستعداد
   │   └─ ساعات الأعطال
   ├─ إدخال تفاصيل الأعطال
   │   ├─ عداد النهاية
   │   ├─ نوع العطل
   │   ├─ قسم العطل
   │   └─ تفاصيل
   ├─ إدخال ساعات المشغل
   │   ├─ ساعات العمل
   │   ├─ ساعات الاستعداد
   │   └─ الساعات الإضافية
   └─ الحفظ
   ↓
3. المراجعة والاعتماد
   ├─ مراجع ساعات المورد (role=7)
   ├─ مراجع ساعات المشغل (role=8)
   └─ مراجع الأعطال (role=9)
```

### 7.6 سير عمل التقارير

```
1. اختيار التقرير المطلوب
   ↓
2. تطبيق الفلاتر
   ├─ المورد
   ├─ المشروع
   ├─ المنجم (يتم تحميله حسب المشروع)
   └─ العقد (يتم تحميله حسب المنجم)
   ↓
3. عرض النتائج
   ├─ جدول تفاعلي (DataTables)
   └─ رسوم بيانية (Charts)
   ↓
4. التصدير
   ├─ Excel
   ├─ PDF
   ├─ CSV
   └─ طباعة
```

---

## 🔒 8. الأمان والحماية {#الأمان}

### 8.1 آليات الأمان

#### 8.1.1 المصادقة (Authentication)
- **Prepared Statements**: منع SQL Injection
- **Session Regeneration**: تجديد معرف الجلسة عند النجاح
- **Brute Force Protection**: حد أقصى 5 محاولات خاطئة، حظر 15 دقيقة
- **HttpOnly Cookies**: منع الوصول عبر JavaScript

#### 8.1.2 حماية CSRF
```php
// توليد رمز CSRF
$_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));

// التحقق
if (!verify_csrf($_POST['csrf_token']??'')) {
    $error = "رمز الأمان غير صحيح";
}
```

#### 8.1.3 رؤوس الأمان HTTP
```php
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
```

#### 8.1.4 منع SQL Injection
```php
// استخدام mysqli_real_escape_string
$name = mysqli_real_escape_string($conn, $_POST['name']);

// أو Prepared Statements
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username=?");
mysqli_stmt_bind_param($stmt, "s", $username);
```

#### 8.1.5 منع XSS
```php
// عند العرض
function e($s) { 
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); 
}
echo e($user_input);
```

#### 8.1.6 التحكم في الوصول
- فحص الجلسة في كل صفحة
- التحقق من الصلاحيات حسب الدور
- تقييد الوصول للبيانات حسب المشروع/المنجم

### 8.2 أفضل الممارسات الأمنية

#### 8.2.1 كلمات المرور
⚠️ **ملاحظة أمنية مهمة**: حالياً، كلمات المرور تُخزن كنص عادي (Plain Text)!  
**التوصية الأمنية**: استخدام `password_hash()` و `password_verify()`

```php
// الطريقة الموصى بها (غير مطبقة حالياً)
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// التحقق
if (password_verify($password, $hashed_password)) {
    // تسجيل الدخول
}
```

#### 8.2.2 التحقق من البيانات
```php
// التحقق من الأعداد
$id = intval($_POST['id']);
$price = floatval($_POST['price']);

// التحقق من التواريخ
$date = DateTime::createFromFormat('Y-m-d', $_POST['date']);
if (!$date) {
    // تاريخ غير صحيح
}
```

#### 8.2.3 تقييد حجم المدخلات
```php
if (mb_strlen($u) > 50 || mb_strlen($p) > 128) { 
    $error = "المدخلات تتجاوز الحد المسموح."; 
}
```

### 8.3 سجلات الأمان (Audit Trails)

النظام يحتفظ بسجلات للإجراءات المهمة:

#### 8.3.1 سجل العقود (contract_notes)
- جميع الإجراءات على العقود (تجديد، إيقاف، استئناف، إنهاء، دمج)
- معرف المستخدم الذي قام بالإجراء
- التاريخ والوقت
- وصف الإجراء

#### 8.3.2 سجل عقود الموردين (supplier_contract_notes)
- نفس سجل عقود المشاريع
- خاص بعقود الموردين

#### 8.3.3 سجل عقود المشغلين (driver_contract_notes)
- نفس سجل عقود المشاريع
- خاص بعقود المشغلين

#### 8.3.4 حقول التتبع في الجداول
- `created_at`: تاريخ الإنشاء
- `updated_at`: تاريخ آخر تحديث
- `created_by`: معرف المستخدم المنشئ

---

## 🎨 9. واجهات المستخدم {#واجهات-المستخدم}

### 9.1 التصميم العام

#### 9.1.1 نظام الألوان
```css
:root {
  --navy:    #0c1c3e;
  --navy-m:  #132050;
  --navy-l:  #1b2f6e;
  --gold:    #e8b800;
  --gold-l:  #ffd740;
  --gold-d:  rgba(232,184,0,.14);
  --bg:      #f0f2f8;
  --card:    #ffffff;
  --bdr:     rgba(12,28,62,.08);
  --txt:     #0c1c3e;
  --sub:     #64748b;
  --danger:  #dc2626;
  --danger-d:rgba(220,38,38,.09);
}
```

#### 9.1.2 الخطوط
- **الخط الرئيسي**: Cairo (دعم كامل للعربية)
- **الوزن**: 300, 400, 500, 600, 700, 900

#### 9.1.3 الاتجاه
- **RTL**: من اليمين لليسار (Right-to-Left)
- `dir="rtl"` على جميع الصفحات
- تصميم متجاوب (Responsive)

### 9.2 المكونات الرئيسية

#### 9.2.1 شريط التنقل (Sidebar)
- قائمة ديناميكية حسب الصلاحيات
- أيقونات Font Awesome
- تبديل الإظهار/الإخفاء
- دعم الموبايل

#### 9.2.2 الجداول (DataTables)
- بحث وفرز
- تصدير (Excel, PDF, CSV)
- استجابة (Responsive)
- ترجمة عربية كاملة

#### 9.2.3 النماذج (Forms)
- تنسيق متسق (Form Grid)
- التحقق من البيانات (Validation)
- رسائل الخطأ والنجاح
- حقول ديناميكية (AJAX Dropdowns)

#### 9.2.4 الأزرار (Buttons)
أنماط موحدة:
- **إضافة**: `add-btn` (أخضر)
- **تعديل**: `editBtn` (أزرق)
- **حذف**: (أحمر)
- **رجوع**: `back-btn` (رمادي)
- **حفظ**: `btn-success` (أخضر)

#### 9.2.5 البطاقات (Cards)
```html
<div class="card">
  <div class="card-header">
    <h5>العنوان</h5>
  </div>
  <div class="card-body">
    المحتوى
  </div>
</div>
```

### 9.3 التفاعل والـ UX

#### 9.3.1 رسائل المستخدم
```php
<?php if (!empty($_GET['msg'])): ?>
  <div class="success-message">
    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['msg']); ?>
  </div>
<?php endif; ?>
```

#### 9.3.2 نوافذ التأكيد
```javascript
onclick="return confirm('هل أنت متأكد من الحذف؟')"
```

#### 9.3.3 التحميل الديناميكي (AJAX)
```javascript
$('#project_id').on('change', function() {
    const projectId = $(this).val();
    $.ajax({
        url: 'get_project_mines.php',
        type: 'GET',
        data: { project_id: projectId },
        dataType: 'json',
        success: function(response) {
            // تحديث القائمة
        }
    });
});
```

#### 9.3.4 الحسابات التلقائية
```javascript
// حساب مجموع ساعات العمل
function calculateTotalWorkHours() {
    const executed = parseFloat($('#executed_hours').val()) || 0;
    const bucket = parseFloat($('#bucket_hours').val()) || 0;
    const jackhammer = parseFloat($('#jackhammer_hours').val()) || 0;
    const extra = parseFloat($('#extra_hours').val()) || 0;
    const standby = parseFloat($('#standby_hours').val()) || 0;
    const dependence = parseFloat($('#dependence_hours').val()) || 0;
    
    const total = executed + bucket + jackhammer + extra + standby + dependence;
    $('#total_work_hours').val(total.toFixed(2));
}
```

### 9.4 الإشعارات والتنبيهات

#### 9.4.1 رسائل النجاح
```html
<div class="success-message">
  <i class="fas fa-check-circle"></i> تم الحفظ بنجاح ✅
</div>
```

#### 9.4.2 رسائل الخطأ
```html
<div class="err">
  <i class="fas fa-exclamation-circle"></i>
  <span>حدث خطأ ❌</span>
</div>
```

#### 9.4.3 رسائل JavaScript
```javascript
alert('✅ تم الحفظ بنجاح');
alert('❌ حدث خطأ');
alert('⚠️ تحذير');
```

---

## 📂 10. الملفات الرئيسية {#الملفات-الرئيسية}

### 10.1 الملفات الأساسية

#### config.php
```php
<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "equipation_manage";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}
?>
```

#### index.php
- صفحة تسجيل الدخول
- حماية من Brute Force
- رموز CSRF
- رؤوس أمان HTTP
- واجهة مستخدم احترافية

#### inheader.php
```php
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'إيكوبيشن'; ?></title>
    
    <!-- Bootstrap, jQuery, DataTables, Font Awesome -->
    <!-- تضمين جميع المكتبات -->
</head>
<body>
```

#### sidebar.php
- القائمة الجانبية
- ديناميكية حسب الصلاحيات
- دعم الموبايل

#### logout.php
```php
<?php
session_start();
session_destroy();
header("Location: index.php");
exit();
?>
```

### 10.2 ملفات AJAX المساعدة

#### get_project_mines.php
```php
// إرجاع مناجم المشروع
$project_id = intval($_GET['project_id']);
$mines = mysqli_query($conn, "SELECT id, mine_name, mine_code FROM mines WHERE project_id = $project_id AND status = 1");
$result = array('success' => true, 'mines' => array());
while ($mine = mysqli_fetch_assoc($mines)) {
    $result['mines'][] = $mine;
}
echo json_encode($result);
```

#### get_mine_contracts.php
```php
// إرجاع عقود المنجم
$mine_id = intval($_GET['mine_id']);
$contracts = mysqli_query($conn, "SELECT id, contract_signing_date FROM contracts WHERE mine_id = $mine_id AND status = 1");
// ...
```

#### get_contract_suppliers.php
```php
// إرجاع موردي العقد
$contract_id = intval($_GET['contract_id']);
// جلب الموردين المرتبطين بهذا العقد
// ...
```

#### get_drivers.php
```php
// إرجاع سائقي المعدة
$operator_id = intval($_GET['operator_id']);
// جلب السائقين المرتبطين بهذه المعدة
// ...
```

### 10.3 معالجات الإجراءات (Action Handlers)

جميع المعالجات تتبع نفس النمط:

```php
<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']));
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'renew') {
    // معالجة التجديد
} elseif ($action === 'settle') {
    // معالجة التسوية
} elseif ($action === 'pause') {
    // معالجة الإيقاف
} elseif ($action === 'resume') {
    // معالجة الاستئناف
} elseif ($action === 'terminate') {
    // معالجة الإنهاء
} elseif ($action === 'merge') {
    // معالجة الدمج
} else {
    die(json_encode(['success' => false, 'message' => 'الإجراء غير معروف']));
}

echo json_encode(['success' => true, 'message' => 'تم الإجراء بنجاح']);
exit;
?>
```

### 10.4 ملفات الاستيراد/التصدير

#### import_clients_excel.php
- استيراد العملاء من Excel/CSV
- استخدام PHPSpreadsheet
- التحقق من البيانات
- معالجة الأخطاء

#### download_clients_template.php
- تحميل نموذج Excel للعملاء
- يحتوي على الأعمدة المطلوبة
- تعليمات الاستخدام

#### import_drivers_excel.php
- استيراد المشغلين من Excel/CSV
- نفس نمط استيراد العملاء

#### download_drivers_template.php
- تحميل نموذج Excel للمشغلين

---

## 📊 ملخص الإحصائيات

### إحصائيات النظام

| العنصر | العدد |
|--------|-------|
| **الجداول** | 20+ |
| **الوحدات** | 10 |
| **الأدوار** | 11 |
| **الصفحات الرئيسية** | 50+ |
| **نقاط AJAX** | 30+ |

### الوظائف الرئيسية

- ✅ إدارة العملاء
- ✅ إدارة المشاريع والمناجم
- ✅ إدارة العقود (3 أنواع)
- ✅ إدارة الموردين والمعدات
- ✅ إدارة المشغلين
- ✅ إدارة التشغيل
- ✅ تسجيل ساعات العمل
- ✅ التقارير والإحصائيات
- ✅ نظام صلاحيات متقدم
- ✅ استيراد/تصدير Excel
- ✅ أمان متعدد الطبقات

---

## 🔄 تحديثات مستقبلية مقترحة

### الأمان
1. ✅ **تشفير كلمات المرور**: استخدام `password_hash()`
2. ✅ **مصادقة ثنائية**: إضافة 2FA
3. ✅ **سجلات تفصيلية**: تتبع جميع العمليات
4. ✅ **صلاحيات دقيقة**: RBAC متقدم

### الوظائف
1. ✅ **لوحة تحكم تحليلية**: رسوم بيانية متقدمة
2. ✅ **إشعارات تلقائية**: عند انتهاء العقود/التراخيص
3. ✅ **تطبيق موبايل**: React Native/Flutter
4. ✅ **API RESTful**: للتكامل مع أنظمة أخرى
5. ✅ **نظام المهام**: Workflow Management

### الأداء
1. ✅ **تخزين مؤقت**: Redis/Memcached
2. ✅ **تحسين الاستعلامات**: Indexing متقدم
3. ✅ **CDN**: لتحميل أسرع
4. ✅ **Lazy Loading**: للجداول الكبيرة

---

## 📞 الدعم والتواصل

### معلومات المشروع
- **الاسم**: إيكوبيشن (Equipation)
- **النوع**: Equipment Management System
- **الإصدار**: 1.0
- **التاريخ**: 2026
- **اللغة**: PHP 7.4+
- **قاعدة البيانات**: MySQL 5.7+

### البيئة
- **XAMPP**: للتطوير المحلي
- **Apache**: خادم الويب
- **MySQL**: قاعدة البيانات
- **Composer**: إدارة التبعيات

---

## 📜 الخلاصة

**نظام إيكوبيشن** هو حل متكامل لإدارة المعدات في مشاريع التعدين والبناء. يوفر:

### النقاط القوية
- ✅ تغطية شاملة لجميع جوانب العمل
- ✅ نظام صلاحيات دقيق
- ✅ واجهة مستخدم عربية احترافية
- ✅ تقارير تحليلية مفصلة
- ✅ أمان متعدد المستويات
- ✅ قابلية التوسع

### نقاط التحسين
- ⚠️ كلمات المرور غير مشفرة
- ⚠️ يمكن تحسين الأداء
- ⚠️ يحتاج لتوثيق API
- ⚠️ يحتاج لاختبارات آلية

### التوصيات
1. **أولوية عالية**: تشفير كلمات المرور
2. **أولوية متوسطة**: تحسين الأداء
3. **أولوية منخفضة**: إضافة ميزات جديدة

---

**تم إعداد هذا التوثيق بشكل شامل وكامل بناءً على قراءة وتحليل كامل كود المشروع وقاعدة البيانات.**

**آخر تحديث**: 26 فبراير 2026  
**الإصدار**: 1.0  
**الحالة**: إنتاج (Production)

</div>
