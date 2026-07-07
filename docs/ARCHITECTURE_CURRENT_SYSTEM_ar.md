# وثيقة المعمارية الحالية لنظام EMS (منصّة إنجاز / Equipation)

> **نوع الوثيقة:** توصيف معماري مرجعي شامل للنظام كما هو قائم فعلياً (As-Built Architecture).
> **الغرض:** توفير صورة كاملة ودقيقة عن المعمارية الحالية بما يمكّن فريق العمل من دراسة الانتقال إلى معمارية أخرى (إعادة الهيكلة / التحديث المعماري).
> **تاريخ الإصدار:** 6 يوليو 2026.
> **منهجية الإعداد:** قراءة الكود ملفاً ملفاً + قراءة عكسية للمخطط الحي لقاعدة البيانات (159 جدولاً فعلياً) + استقراء الأنماط المتكررة عبر الوحدات + تحليل ملفات التوثيق القائمة.
> **طبيعة الوثيقة:** وصفية محايدة — تصف «ما هو كائن» لا «ما ينبغي أن يكون». يوجد قسم مستقل في الآخر يرصد الديون المعمارية لأنها ضرورية لأي قرار إعادة هيكلة.

---

## جدول المحتويات

1. الملخّص التنفيذي المعماري
2. النمط المعماري العام
3. المنظومة التقنية (Tech Stack)
4. طوبولوجيا البيئة والنشر
5. الخريطة العليا للمشروع (Directory Map)
6. نقاط الدخول والبوابات الثلاث
7. دورة حياة الطلب (Request Lifecycle)
8. النواة المشتركة (Bootstrap & Shared Core)
9. طبقة الأمان
10. المصادقة وإدارة الجلسات
11. التفويض: نموذج RBAC + الوحدات + هرمية الأدوار
12. تعدّد المستأجرين (Multi-Tenancy)
13. طبقة الوصول للبيانات
14. نموذج البيانات (Data Model)
15. خريطة الموارد البشرية والعلاقات (HR Domain Map)
16. تشريح صفحة الوحدة النموذجية (God-File Anatomy)
17. الأنماط المعمارية المتقاطعة (Cross-Cutting)
18. الوحدات الوظيفية (جرد كامل)
19. نمط «الوحدة المكتفية ذاتياً» (Self-Contained Module Pattern)
20. طبقة الواجهة الأمامية
21. المهام المجدولة (Cron)
22. التسجيل والمراقبة (Observability)
23. سمات الجودة (Quality Attributes)
24. الديون المعمارية ونقاط الاحتكاك
25. طبقة تحكّم الـ SaaS (Admin Panel · Company Portal · نموذج الاشتراك)
26. البناء والترحيلات وحوكمة الترميز (Build · Migrations · Encoding)
27. ملاحق (خرائط مرجعية)

---

## 1. الملخّص التنفيذي المعماري

**EMS** («منصّة إنجاز» / **Equipation**) هو نظام **SaaS متعدّد المستأجرين** متخصّص في **إدارة المعدّات الثقيلة والأساطيل والمناجم والعقود وكشوف ساعات التشغيل** لقطاع التعدين والإنشاءات في السودان.

النظام في جوهره **تطبيق PHP إجرائي أحادي (Procedural Monolith)** مبني فوق `mysqli` مباشرة، حيث كل «صفحة» هي ملف `.php` مستقل يجمع بداخله كامل الطبقات (التفويض + معالجة الطلب + استعلامات SQL + HTML + CSS + JavaScript). تعلو هذا الأساس **قشرة حديثة جزئية**:

- مجلد `app/` بمعمارية موجّهة للكائنات (PSR-4, Namespaces, Services, Repositories, Jobs, Queues, Middleware, Observers) — لكنها مُستخدمة عملياً في **نظامين فقط**: سجلّ النشاط (Activity Log) وإطار Excel الموحّد.
- الوحدات الأحدث (الصيانة، المشتريات، المالية، النقل، الموارد البشرية الموسّعة) تتبع **نمطاً هجيناً أنضج**: جداول ذات بادئة مستقلة + ملف «خدمات» إجرائي واحد (`*_helpers.php`) + مهمّة مجدولة (cron) + تسجيل غير كاسر في جداول الأدوار والوحدات.

**مؤشرات حجم دالّة على الحالة الراهنة:**

| المؤشّر | القيمة |
|---|---|
| ملفات PHP | ~934 |
| جداول قاعدة البيانات (حيّة) | **159** جدولاً (المخطّط المُصدَّر القديم كان 61) |
| المفاتيح الأجنبية (FK) | 84 فقط (مركّزة في النواة القديمة؛ الوحدات الجديدة بلا FK عمداً) |
| الإجراءات/الدوال/المُشغّلات المخزّنة (Stored Procedures/Triggers) | **صفر** — كل المنطق في PHP |
| الـ Views | 3 (طبقة الموارد البشرية `v_worker_*`) |
| استدعاءات `mysqli_query` الخام | ~1135 |
| استدعاءات `mysqli_prepare`/`->prepare` | ~468 |
| ملفات بها فحص أعمدة دفاعي (`SHOW COLUMNS`/`has_column`) | ~106 |
| ملفات تنفّذ DDL وقت التشغيل (`ALTER TABLE`) | ~20 |
| أكبر ملف | `Equipments/equipments_fleet.php` (~119KB / ~2600 سطر) |
| الأدوار المُعرّفة | 23 دوراً (`roles`) |
| الوحدات/الشاشات المُسجّلة | ~104 (`modules`) |

---

## 2. النمط المعماري العام

النظام يجمع بين ثلاثة أنماط في طبقات زمنية متتالية:

### 2.1 الطبقة الأساسية: Monolith إجرائي متمركز حول الصفحة (Page-Centric Procedural Monolith)
- لا يوجد Router مركزي ولا Front Controller للتطبيق الرئيسي؛ **رابط الصفحة = مسار الملف على القرص** (`Clients/clients.php`, `Timesheet/timesheet.php`...).
- كل ملف يبدأ بـ `session_start()` ثم `include '../config.php'` ثم يفحص الجلسة والصلاحيات، ثم ينفّذ منطقه، ثم يرسم HTML عبر `include '../inheader.php'` و`include '../insidebar.php'`.
- **الحالة (State) مُدارة بالكامل عبر جلسة PHP** (`$_SESSION['user']`)، ما يجعل النظام **Stateful** ويقيّد التوسّع الأفقي بلا Session Store مشترك.

### 2.2 الطبقة الحديثة الجزئية: OOP + PSR-4 داخل `app/`
- Namespace `App\` مع Autoloader مخصّص في `app/bootstrap.php`.
- مطبّقة فعلياً على: **سجلّ النشاط** (Middleware → Service → Job → Queue → Repository) و**إطار Excel** (Registry + Service + Importer/Exporter/Validator/Styler...).

### 2.3 الطبقة الأنضج: وحدات مكتفية ذاتياً (Self-Contained Modules)
- تُبنى الوحدات الجديدة كـ«جُزُر» غير كاسرة: بادئة جداول خاصة (`fin_`, `proc_`, `trs_`/`transfer_`, `mnt_`, `worker_`)، طبقة خدمات إجرائية في ملف `*_helpers.php`، وربط بالنظام العام فقط عبر جدولي `roles` و`modules` وأحياناً `fin_financial_events`.

> **الخلاصة المعمارية:** النظام يتطوّر من Monolith إجرائي كلاسيكي نحو **Modular Monolith** بحدود منطقية أوضح، لكنه لم يفصل الطبقات (Controller/Service/Repository/View) في النواة القديمة، ولم يزل يعتمد على الجلسة والملف-كمسار.

---

## 3. المنظومة التقنية (Tech Stack)

| الطبقة | التقنية |
|---|---|
| اللغة | PHP (متوافق 7.4–8.x)، أسلوب إجرائي في النواة + OOP في `app/` |
| الوصول للبيانات | `mysqli` (إجرائي غالباً، OOP أحياناً) |
| قاعدة البيانات | MySQL 8.4 / MariaDB، محرك InnoDB، ترميز موحّد `utf8mb4_unicode_ci` |
| إدارة الحزم | Composer — اعتماد فعلي وحيد تقريباً: `phpoffice/phpspreadsheet` (Excel) |
| الواجهة | HTML/CSS/JS يدوية + Bootstrap 5 (RTL) + jQuery 3.7 + DataTables 1.13 + Font Awesome + Chart.js |
| الخطوط | خطوط عربية محلّية (IBM Plex Sans Arabic, Tajawal, Cairo, Amiri) عبر `local-fonts.css` |
| الخادم | Apache (WAMP على Windows للتطوير؛ Hostinger للإنتاج حسب تعليقات `config.php`) |
| الجدولة | سكربتات CLI/GET تُشغَّل عبر Cron (مثل `Finance/cron_finance_fin.php`) |

**لا يوجد:** إطار عمل PHP (Laravel/Symfony)، لا ORM، لا Container/DI، لا نظام Migrations منضبط (الهجرات ملفات SQL يدوية في `database/migrations/`)، لا طبقة Cache خارجية (Redis/Memcached)، لا Message Broker (طابور المهام ملفات على القرص).

---

## 4. طوبولوجيا البيئة والنشر

```
                         ┌─────────────────────────────────────────┐
   المتصفح               │              Apache + PHP                 │
   ──────────────────────►│  .htaccess (rewrite/headers/gzip)        │
                          │        │                                 │
                          │        ▼                                 │
                          │   ملف .php للصفحة  ─────► config.php      │
                          │                          (bootstrap)     │
                          │        │                                 │
                          │        ▼                                 │
                          │   $conn (mysqli) ─────────────────────┐  │
                          └───────────────────────────────────────┼──┘
                                                                  ▼
                                                    ┌───────────────────────┐
                                                    │  MySQL: equipation_    │
                                                    │  manage (159 جدولاً)   │
                                                    └───────────────────────┘
   التخزين على القرص:  storage/queue/*  (طابور سجل النشاط)  |  logs/*  |  assets/uploads/*
```

- **قاعدة بيانات واحدة مشتركة** لكل المستأجرين (Shared Database / Shared Schema)، والعزل منطقي عبر عمود `company_id`.
- **إعدادات الاتصال مكتوبة مباشرة في `config.php`** (لا `.env`)، مع بيانات إنتاج معطّلة كتعليق وبيانات تطوير `localhost/root` مفعّلة.
- التخزين المحلي: `storage/` (طابور المهام + سجلات + ملفات Excel المستوردة)، محميّ من الوصول المباشر عبر `.htaccess` («Require all denied»).

---

## 5. الخريطة العليا للمشروع (Directory Map)

```
ems/
├── index.php                  # الصفحة التعريفية العامة (Landing) — منصّة إنجاز
├── login.php / logout.php     # مصادقة التطبيق الرئيسي
├── config.php                 # نقطة التمهيد المركزية (bootstrap فعلي للنظام)
├── inheader.php               # رأس HTML المشترك + تحميل الأصول
├── insidebar.php              # القائمة الجانبية + التوببار + عدّادات الإشعارات
├── excel.php                  # نقطة دخول إطار Excel الموحّد
│
├── includes/                  # النواة المشتركة (Security/Perf/Auth/Nav/Helpers)
│   ├── security.php           # نواة الأمان (جلسات/CSRF/XSS/رفع ملفات/rate limit)
│   ├── performance.php        # تحسينات الأداء (gzip/pagination/db session)
│   ├── permissions_helper.php # فحص صلاحيات الوحدات (RBAC)
│   ├── dynamic_nav.php        # بناء القائمة من جدول modules
│   ├── approval_workflow.php  # محرك الموافقات متعدّد المراحل
│   ├── sessions.php / topbar.php / page_header.php / employee_types.php ...
│
├── app/                       # الطبقة الحديثة (PSR-4, App\ namespace)
│   ├── bootstrap.php          # autoloader + boot middleware
│   ├── Middleware/            # ActivityLogMiddleware
│   ├── Services/              # ActivityLogService + Services/Excel/* + Services/Workforce/*
│   ├── Repositories/ Jobs/ Queues/ Observers/
│
├── admin/                     # لوحة الإدارة الفائقة (SaaS Super-Admin)
├── company/                   # بوابة الشركات (تسجيل/اشتراك/فريق)
│
├── [وحدات النواة القديمة]      # Clients, Projects, Contracts, Suppliers, Equipments,
│                              # Oprators, Employees, Workforce, Timesheet, movement,
│                              # Approvals, Reports, emsreports, Settings, chats, ActivityLogs
│
├── [الوحدات الجديدة]           # Maintenance, Procurement, Finance, Transport, Opportunities
│
├── assets/                    # css/ js/ fonts/ vendor/ uploads/ i18n/ images/
├── database/                  # المخطط + migrations/ + backups/
├── storage/                   # queue/ logs/ excel_imports/ fleet/
├── logs/                      # security.log, php_errors.log
└── docs/ + *.md               # وثائق كثيرة (تدقيق، خطط، Prompts بناء الوحدات)
```

---

## 6. نقاط الدخول والبوابات الثلاث

النظام يقدّم **ثلاث بوابات منفصلة** بثلاثة مسارات مصادقة مستقلة (إضافةً إلى الصفحة التعريفية العامة):

| البوابة | نقطة الدخول | الجمهور | نموذج الجلسة |
|---|---|---|---|
| **الصفحة التعريفية** | `index.php` | زوّار عموميون (تسويقي) | لا جلسة |
| **التطبيق الرئيسي** | `login.php` → `main/dashboard.php` | مستخدمو الشركات (كل الأدوار التشغيلية) | `$_SESSION['user']` |
| **بوابة الشركات** | `company/login.php` | مالكو/مسؤولو الشركات (تسجيل، اشتراك، فريق) | `$_SESSION['company_user']` |
| **لوحة الإدارة الفائقة** | `admin/login.php` | مدراء المنصّة (SaaS) — الشركات، الخطط، الاشتراكات، الدعم | `$_SESSION['super_admin']` |

- الصفحة التعريفية `index.php` تشير للبوابات الثلاث («بوابة الشركات»، «لوحة الإدارة»، «تسجيل الدخول»).
- الأدمن مستثنى من كثير من الأنظمة المشتركة (CSRF المركزي، سجل النشاط) لأن له سجلّ تدقيق خاص (`admin_audit_log`).

---

## 7. دورة حياة الطلب (Request Lifecycle)

مسار أي طلب لصفحة داخلية في التطبيق الرئيسي:

```
1. Apache/.htaccess         → قواعد إعادة الكتابة، ضغط gzip، رؤوس أمنية أوّلية
2. <page>.php               → session_start()  +  فحص isset($_SESSION['user'])
3. include config.php        → نقطة التمهيد الفعلية، وتنفّذ بالترتيب:
     a. includes/security.php    (يبدأ الجلسة الآمنة، الرؤوس الأمنية، CSRF token)
     b. includes/performance.php (ems_performance_bootstrap: gzip, ...)
     c. includes/employee_types.php + actor_helper.php
     d. ضبط ترميز UTF-8 على مستوى التشغيل
     e. ob_start('ems_fix_mojibake_output')  → مرشّح إخراج يصحّح تلف الترميز + يحقن سكربت توحيد الأرقام
     f. ob_start('ems_inject_csrf_fields')    → يحقن حقل csrf_token تلقائياً في كل <form method=post>
     g. اتصال mysqli + set_charset(utf8mb4) + ems_optimize_db_session()
     h. ems_force_logout_if_company_suspended()  → طرد فوري إذا كانت الشركة موقوفة
     i. ems_enforce_ajax_endpoint_security()     → حماية معالجات get_*/‏*_handler (XHR + جلسة + rate limit)
     j. ems_enforce_csrf_protection()            → الحارس المركزي لـ CSRF (وضع مراقبة حالياً)
     k. require app/bootstrap.php                → autoloader + ActivityLogMiddleware::boot()
4. الصفحة                    → تفحص صلاحية الوحدة (permissions_helper) + تبني نطاق الشركة + تنفّذ SQL
5. inheader/insidebar        → ترسم الإطار المشترك + القائمة الديناميكية
6. register_shutdown_function → ActivityLogMiddleware::onShutdown() يسجّل النشاط بعد إرسال الرد
```

**ملاحظة معمارية مهمّة:** `config.php` ليس مجرد ملف إعدادات — هو **الـ Bootstrap الفعلي** الذي يركّب كل الأنظمة المتقاطعة (أمان، أداء، ترميز، CSRF، سجل نشاط) عبر مُعالِجات Output-Buffer ودوال حرّاسة تُستدعى تلقائياً. أي إعادة هيكلة يجب أن تبدأ من هنا.

---

## 8. النواة المشتركة (Bootstrap & Shared Core)

### 8.1 `config.php` — نقطة التمهيد المركزية
مسؤولياته (بالترتيب): منع الوصول المباشر → تحميل نواة الأمان والأداء → تشديد ترميز UTF-8 → تسجيل مُعالِجات Output-Buffer (تصحيح mojibake + حقن CSRF) → إعدادات PHP الأمنية (إخفاء الأخطاء، تعطيل `allow_url_*`) → إنشاء اتصال `mysqli` → حرّاس الجلسة/الشركة/AJAX/CSRF → تحميل `app/bootstrap.php`. يوفّر أيضاً اختصارات عالمية: `escape()`, `query_safe()`, `db_table_has_column()` (مع Cache داخلي).

### 8.2 `app/bootstrap.php` — تمهيد الطبقة الحديثة
- يعرّف ثوابت المسارات (`EMS_ROOT_DIR`, `EMS_STORAGE_DIR`, `EMS_QUEUE_SPOOL_DIR`...).
- يحمّل Composer autoloader مركزياً.
- ينشئ مجلدات `storage/` مع `.htaccess` مانع للوصول.
- يسجّل **Autoloader PSR-4 لـ `App\`** (يترجم `App\Services\X` → `app/Services/X.php`).
- يشغّل `ActivityLogMiddleware::boot()`.
- يُنشئ جدول `activity_logs` تلقائياً إن غاب (نمط DDL-وقت-التشغيل، لكن بحماية `IF NOT EXISTS` ومرّة واحدة).

### 8.3 مكوّنات `includes/`
| الملف | المسؤولية |
|---|---|
| `security.php` | نواة الأمان الكاملة (القسم 9) |
| `performance.php` | `ems_performance_bootstrap`، `ems_optimize_db_session`، `ems_get_pagination` |
| `permissions_helper.php` | فحص صلاحيات الوحدات (القسم 11) |
| `dynamic_nav.php` | بناء روابط القائمة من `modules` حسب دور المستخدم |
| `approval_workflow.php` | محرّك الموافقات متعدّد المراحل (القسم 17) |
| `sessions.php` | مساعدات جلسة |
| `topbar.php` / `page_header.php` | مكوّنات واجهة مشتركة |
| `employee_types.php` / `employee_log_helper.php` / `equipment_log_helper.php` | مساعدات المجال |
| `actor_helper.php` | تسمية «الفاعل» الموحّدة (الموظف خلف حساب المستخدم) |
| `excel_ui.php` | واجهة استيراد/تصدير Excel |

### 8.4 طبقة الأداء والبنية التحتية (`performance.php` + `.htaccess`)
مسؤوليات `includes/performance.php` (تُركَّب عبر `ems_performance_bootstrap` في `config.php`):
- **ضغط الإخراج:** `ob_gzhandler` (إن توفّر `zlib` ولم يكن الضغط مفعّلاً في `php.ini`).
- **ضبط زمن التشغيل:** `realpath_cache_size=4096K`, `realpath_cache_ttl=600`, `max_input_vars=4000` (لدعم النماذج الطويلة جداً في النواة القديمة).
- **تحسين جلسة قاعدة البيانات:** `SET SESSION sql_big_selects=1` و`group_concat_max_len=8192` (لدعم استعلامات `GROUP_CONCAT` الكبيرة في التقارير/الأشجار).
- **ترقيم الصفحات:** `ems_get_pagination($default=25,$max=200)` يقرأ `page`/`per_page` من `$_GET` ويعيد `limit_sql` جاهزاً (استخدامه غير مُعمَّم — كثير من الصفحات تعرض بلا LIMIT).

طبقة **`.htaccess`** (الجذر) توفّر — على مستوى Apache قبل PHP —:
- `Options -Indexes` (منع تصفّح المجلدات) + `DirectoryIndex`.
- **حماية الملفات الحسّاسة:** حجب `.htaccess/.env`, `config*.php`, والامتدادات `bak/backup/old/orig/save/swp/tmp/sql/log`.
- **التخزين المؤقت (`mod_expires`)** للصور/CSS/JS، و**الضغط** (حسب توفّر الوحدات).
- الرؤوس الأمنية مُعطّلة على مستوى Apache وتُطبَّق من `security.php` (لتجنّب الازدواج).
- ملفات `.htaccess` إضافية تحمي `storage/`, `database/`, `api/` من الوصول المباشر.

---

## 9. طبقة الأمان

النواة الأمنية مركزية وناضجة نسبياً في `includes/security.php`. أهم مكوّناتها:

- **جلسات صارمة** (`secure_session_start`): `SameSite=Strict`, `HttpOnly`, `use_strict_mode`, `use_only_cookies`, تجديد دوري لمعرّف الجلسة كل 30 دقيقة، `sid_length=48`.
- **مهلة الخمول** (`check_session_timeout`): 60 دقيقة، ثم إعادة توجيه للبوابة الصحيحة حسب السياق.
- **بصمة الجلسة** (`validate_session_fingerprint`): SHA-256 على (User-Agent + IP) لمقاومة سرقة الجلسة.
- **CSRF مركزي**: توليد توكن (`generate_csrf_token`، تجديد كل ساعة)، **حقن تلقائي** في كل فورم POST عبر مُعالِج Output-Buffer (`ems_inject_csrf_fields`) — يُقنّع كتل `<script>` أولاً ويتجاهل الفورمات الخارجية، وحارس مركزي (`ems_enforce_csrf_protection`) يعمل حالياً في **وضع المراقبة** (`EMS_CSRF_ENFORCE=false`) يسجّل المخالفات دون حجب. مستثناة منه لوحة `/admin/` (لها حماية CSRF خاصة بها).
- **XSS**: `clean_output()`/`e()` عبر `htmlspecialchars(ENT_QUOTES|ENT_HTML5)`، ودوال `clean_url/clean_email/clean_html`.
- **رؤوس أمنية** (`set_security_headers`): `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, **CSP** (يسمح حالياً `unsafe-inline`/`unsafe-eval` بسبب السكربتات المضمّنة)، `Referrer-Policy`, `Permissions-Policy`, و`HSTS` على HTTPS.
- **حماية معالجات AJAX** (`ems_enforce_ajax_endpoint_security`): تفرض `X-Requested-With: XMLHttpRequest` + وجود جلسة + Rate-Limit لكل نقطة `get_*.php`/`*_handler.php`.
- **رفع الملفات** (`validate_file_upload`): فحص الحجم + الامتداد + مطابقة MIME الفعلي، وتوليد اسم عشوائي آمن.
- **تسجيل الأحداث الأمنية** (`log_security_event`) في `logs/security.log`.
- **حماية بيانات الاعتماد للمصادقة**: `password_verify` (bcrypt)، قفل بعد محاولات متكرّرة (في `login.php`).

---

## 10. المصادقة وإدارة الجلسات

- **آلية المصادقة:** جلسة PHP قائمة على الملفّات. لا JWT في التطبيق الويب.
- **بنية `$_SESSION['user']`:** `id, name, username, phone, role, employee_id, project_id, contract_id, company_id`.
- **المفتاح المحوري:** `role` (نص) — يحدّد التفويض والقوائم والعزل. القيمة `'-1'` = **مدير النظام الفائق** (يتجاوز كل الفحوص).
- **ربط المستخدم بالموظف:** `users.employee_id` (UNIQUE FK → `employees`) يربط حساب الدخول بشخص في الموارد البشرية؛ طبقة `actor_helper` تُظهر اسم الموظف خلف الحساب.
- **إجبار تغيير كلمة المرور:** `users.force_password_change` + `temp_password_set_at`.
- **بوابات منفصلة بجلسات منفصلة:** `super_admin`، `company_user`، `user` — مع منطق طرد فوري عند إيقاف الشركة (`ems_force_logout_if_company_suspended`).

---

## 11. التفويض: نموذج RBAC + الوحدات + هرمية الأدوار

نظام الصلاحيات مبني على أربعة جداول متعاونة:

### 11.1 الجداول
- **`roles`**: `id, name, parent_role_id, level, role_scope('gloable'|'mine'), status`. الأدوار قد تكون **هرمية** (`parent_role_id`) وقد يكون نطاقها عاماً (`gloable`) أو مقيّداً بمنجم (`mine`).
- **`modules`**: `id, name, code (مسار الملف), owner_role_id, is_link, icon, display_order`. كل «شاشة» = صفّ. `code` هو **مسار الملف النسبي** الذي يربط الشاشة بملفها الفعلي.
- **`role_permissions`**: `role_id, module_id, can_view, can_add, can_edit, can_delete` — مصفوفة صلاحيات ثنائية لكل (دور × وحدة).
- **`report_role_permissions`**: نظام صلاحيات **منفصل** لوحدة التقارير `emsreports/` (لا يمرّ عبر `modules`).

### 11.2 آلية الفحص (`permissions_helper.php`)
- `check_permission($conn, $module_id, 'view|add|edit|delete')` → استعلام مباشر على `role_permissions` (**بلا Cache — يُنفَّذ في كل استدعاء**، وهو مصدر N+1).
- `enforce_current_page_view_permission()` → يُستدعى من `insidebar.php`؛ يستنتج الوحدة من **مسار السكربت الحالي** ويطرد من لا يملك `can_view`. يتعامل بخصوصية مع `emsreports/` (عبر `report_role_permissions`) ومع شاشات موحّدة (المراسلات، البلاغات).
- **حل تعارض الوحدات:** عند تطابق أكثر من صفّ في `modules` لنفس اسم الملف، يُفضَّل `owner_role_id` المطابق لدور المستخدم الحالي ثم العام ثم الأقل `id`. (تنويه: الحارس المركزي قد يحلّ الوحدة بأقل `id` متجاهلاً المالك في بعض المسارات — نقطة احتكاك معروفة.)

### 11.3 القائمة الديناميكية (`dynamic_nav.php`)
`getDynamicNavLinks()` يجلب من `modules` كل الشاشات المملوكة لدور المستخدم **أو لدوره الأب** (عبر `parent_role_id`)، للأدوار النشطة فقط، مرتّبة بـ`display_order`. القائمة تُبنى من قاعدة البيانات لا من كود ثابت.

### 11.4 خريطة الأدوار الحالية (23 دوراً)
| id | الدور | الأب | النطاق |
|---|---|---|---|
| 1 | إدارة التشغيل | — | عام |
| 2 | إدارة الموردين | — | عام |
| 3 | إدارة الأسطول | — | عام |
| 4 | إدارة الموارد البشرية | — | عام |
| 5 | مدير الموقع | — | منجم |
| 6 | مدير حركة وتشغيل | — | منجم |
| 7 | مشرف مشاريع | 1 | عام |
| 8 | مشرف موردين | 2 | عام |
| 10 | مشرف أسطول | 3 | عام |
| 11 | مشغل أسطول | 3 | عام |
| 12 | إدارة المبيعات | — | عام |
| 13 | إدارة الصيانة | — | عام |
| 14 | مشرف صيانة | 13 | عام |
| 15 | مدير الحسابات | — | عام |
| 16 | مسؤول المشتريات | — | عام |
| 17 | المدير المالي | — | عام |
| 18 | محاسب الإدارة المالية | 17 | عام |
| 19 | مدير الإدارة المالية | 17 | عام |
| 20 | المراجع والمدقق المالي | 17 | عام |
| 21 | أمين الخزينة | 17 | عام |
| 22 | قارئ مالي | 17 | عام |
| 23 | مدير النقل والترحيل | — | عام |

> **تنويه دقّة:** الأدوار مُرمّزة في الكود كـ**أرقام نصّية سحرية** (`role == '10'`, `role === '-1'`...) منتشرة في كل النظام، لا كثوابت/تعداد. توزيع الوحدات على المالكين: الدور 4 (HR) يملك 20 وحدة، الدور 17 (المالي) 22، الدور 16 (المشتريات) 10، الدور 12 (المبيعات) 13.

---

## 12. تعدّد المستأجرين (Multi-Tenancy)

**النموذج:** قاعدة بيانات واحدة + مخطّط واحد، والعزل منطقي عبر `company_id`.

- كل مستخدم مرتبط بـ`users.company_id`، وكل جدول رئيسي يحمل عمود `company_id`.
- **نمط بناء النطاق (Scope) مزدوج** — يُبنى في كل صفحة على حِدة عبر دالة محلية (مثل `clients_build_scope_sql`):
  - المسار المفضّل: `WHERE company_id = ?` (عندما يملك الجدول العمود).
  - المسار الاحتياطي الهشّ: `EXISTS (SELECT 1 FROM users su WHERE su.id = <table>.created_by AND su.company_id = ?)` — يربط الرؤية بمنشئ السجل (ينكسر إن حُذف المنشئ).
- **لا توجد دالة عزل مركزية واحدة** — كل وحدة تُعيد بناء المنطق، وهذا مصدر تكرار وتباين.
- **جداول إدارة المستأجرين:** `admin_companies` (بيانات الشركة، الخطة، الحدود `max_users/max_equipments/max_projects`، الحالة `pending/active/suspended/cancelled`، `modules_enabled`)، `admin_subscription_plans`، `admin_subscription_requests`، `super_admins`.
- **إنفاذ حالة الشركة:** فحص `admin_companies.status` عند كل طلب ويب (طرد فوري إن لم تكن `active`).
- **تقييد الميزات حسب الاشتراك (Feature Gating):** يُحمَّل `$_SESSION['plan_modules']` من `admin_subscription_plans` (الحدود + `features`) عند الدخول، إضافةً إلى `admin_companies.modules_enabled`. (التفاصيل الكاملة لطبقة تحكّم الـ SaaS في **القسم 25**.)

---

## 13. طبقة الوصول للبيانات

- **لا توجد طبقة ORM أو Repository موحّدة للنواة القديمة.** الوصول مباشر عبر `mysqli` (`$conn` عالمي).
- **مزيج أنماط:** ~1135 `mysqli_query` خام مقابل ~468 `mysqli_prepare` مقابل استخدام نادر جداً (2) لمساعد `db_query()` المُعدّ. الأمان يعتمد غالباً على `intval()`/`escape()` اليدوي.
- **المرونة أمام انجراف المخطّط (Schema-Drift Resilience):** ~106 ملفاً يفحص وجود الأعمدة وقت التشغيل (`SHOW COLUMNS`/`db_table_has_column`) قبل استخدامها، و~20 ملفاً ينفّذ `ALTER TABLE`/`CREATE TABLE IF NOT EXISTS` **أثناء معالجة الطلب** لترقية المخطّط ذاتياً. هذا يجعل الصفحات «تصلح نفسها» عبر البيئات، لكنه يفرض صلاحية `ALTER` لمستخدم التطبيق ويحمل مخاطر أداء وتزامن.
- **لا Stored Procedures ولا Triggers على الإطلاق** — كل منطق الأعمال في PHP. توجد 3 Views فقط لطبقة الموارد البشرية (`v_worker_billable_hours`, `v_worker_presence`, `v_worker_worklog`).
- **الحذف الناعم (Soft Delete):** غير موحّد — مزيج من `is_deleted` و`deleted_at`/`deleted_by` و`status`، ويُفلتَر محلياً في كل وحدة (مثل `clients_not_deleted_sql`).

---

## 14. نموذج البيانات (Data Model)

قاعدة البيانات الحيّة **159 جدولاً**، تنقسم حسب البادئة إلى مجموعات دالّة على حدود الوحدات:

### 14.1 المجموعات حسب البادئة
| البادئة | العدد | الوحدة |
|---|---|---|
| `fin_*` | 29 | الإدارة المالية (Finance) |
| `proc_*` | 15 | المشتريات (Procurement) |
| `mnt_*` | 11 | الصيانة (Maintenance) |
| `worker_*` + `workforce_*` + `v_worker_*` | ~14 | الموارد البشرية الموسّعة (Workforce/HR) |
| `transfer_*` + `trs_*` | 10 | النقل والترحيل (Transport) |
| `fleet_*` | 8 | بطاقة الأسطول والإهلاك |
| `admin_*` + `super_admins` | ~6 | إدارة المنصّة (SaaS) |
| `timesheet*` | 4 | كشوف الساعات والموافقات |
| `approval_*` | 3 | محرّك الموافقات |
| `contract*` + `driver*` + `supplier*` | متعدّد | العقود (ثلاثة مسارات) |
| `_bak_*` / `*_legacy_backup` | ~17 | جداول نسخ احتياطي/هجرات قديمة |

### 14.2 الجداول الأساسية للنواة (Core Entities)
- **`users`**: حسابات الدخول (role نصّي، company_id، project_id، employee_id، force_password_change، soft-delete).
- **`roles` / `modules` / `role_permissions`**: نظام RBAC.
- **`clients`** → **`project`** → **`contracts`** (+ `contractequipments`): سلسلة العميل→المشروع/المنجم→العقد.
- **`operations`**: قلب التشغيل — ربط المعدّة بالمشروع/العقد/المورد، مع **نموذج حالة ثنائي المحور**: `op_state('تعمل'|'جاهزة'|'معطلة')` (تُدار من صفحة الحركة) و`equipment_health('سليمة'|'معطلة')` (تُدار من الصيانة) — محورين مستقلّين عن الدور التشغيلي (`status`).
- **`equipments`** (+ `equipments_types`, `equipment_drivers`, `equipment_operators`) و**`fleet_*`** (بطاقة المعدّة، الإهلاك، المكوّنات، الامتثال، التاريخ `fleet_equipment_history`).
- **`employees`** (SSOT للأشخاص بعد توحيد `worker_profile`؛ `is_workforce`) + `job_titles` + `employee_roles` (≠ أدوار النظام).
- **`suppliers`/`supplierscontracts`** و**`drivercontracts`**: مساري العقود المتوازيين.
- **`timesheet`** (+ `timesheet_approvals`, `timesheet_approval_notes`, `timesheet_failure_hours`): كشوف تفصيلية جداً (ساعات تنفيذ/كسّارة/قلّاب/حفر بالأمتار/أطنان/نقلات + ساعات أعطال مصنّفة حسب الجهة).
- **`messages`** (المراسلات)، **`activity_logs`** (سجل النشاط)، **`audit_logs`/`admin_audit_log`**.

### 14.3 حقائق معمارية عن العلاقات
- **84 FK فقط** — مركّزة في النواة القديمة (`employees` مُشار إليه 11 مرة، `users` 5، `contracts` 5، `equipments` 5). **الوحدات الجديدة (`fin_`, `proc_`, `trs_`) بلا FK تقريباً** — قرار تصميمي متعمّد لجعلها غير كاسِرة.
- **`operations`** يخزّن مفاتيح خارجية كنصوص (`project_id varchar`, `contract_id varchar`) — ما يفرض أحياناً `CAST(... AS UNSIGNED)` ويكسر الفهارس.
- **قناة تكامل بين الوحدات:** جدول `fin_financial_events` هو نقطة التقاء تُغذّيه الوحدات (المشتريات، النقل...) عبر ENUM موسّع، بدل علاقات FK صريحة.

---

## 15. خريطة الموارد البشرية والعلاقات (HR Domain Map)

طبقة الموارد البشرية هي أكثر مناطق النظام ترابطاً، وتقع في قلبها فكرة **مصدر حقيقة واحد للأشخاص (Single Source of Truth)**. هذا القسم يوثّقها بالكامل: الكيان المركزي، ربط الموظف بحساب الدخول، والعلاقات المشابهة (المشغّل↔المعدّة، العقود، دورة حياة القوى العاملة، والتقارير المشتقّة).

### 15.1 الكيان المركزي: `employees` (SSOT)

جدول **`employees`** هو المرجع الوحيد لكل شخص في النظام (سائق، مشغّل، فنّي، إداري، عامل...). بعد **توحيد 2026-06-27** أُدمج جدول `worker_profile` القديم داخل `employees` وحُذف، وأُعيد توجيه كل جداول `worker_*` لتشير إلى `employees.id` مباشرة. الجدول ضخم (~70 عموداً) ويجمع عدة أبعاد:

| البُعد | أبرز الأعمدة |
|---|---|
| الهوية والتصنيف | `id, employee_code, worker_code, name, nickname, employee_type, company_id, project_id` |
| الوثائق الرسمية | `identity_type/number/expiry`, `license_number/type/grade/expiry/issuer`, صور (`employee_photo, identity_photo, license_photo, medical_report_path`) |
| الكفاءة | `specialized_equipment, years_in_field, years_on_equipment, skill_level, certificates` |
| التوظيف والراتب | `employment_affiliation, salary_type, monthly_salary, start_date, source_type('شركة'/'مورد'/'مقاول'), supplier_id` |
| التصنيف الوظيفي | `job_title_id → job_titles`, `employee_role_id → employee_roles` |
| طبقة القوى العاملة | `is_workforce, worker_category, workforce_class('أساسي'/'احتياطي'/'بديل مؤقت'/'تغطية إجازة'/'تجاري مؤقت'), workforce_state('مرشّح'→'منتهٍ'), job_grade` |
| السلامة الطبية | `medical_fitness_status, fitness_conditions, health_status, blood_type, vaccinations_status` |
| نموذج البديل | `primary_backup_id, is_replaceable` |
| الطوارئ والتواصل | `phone, whatsapp, emergency_contact_*` |

- **`is_workforce`** هو المفتاح الذي يميّز «الموظف العادي» عن «فرد القوى العاملة الميدانية» الذي تُفعَّل له دورة الحياة الكاملة (`worker_*`).
- **`source_type`** يحدّد جهة التوظيف (شركة/مورد/مقاول)، و`supplier_id` يربط العامل بموردٍ عند مصدره الخارجي.

### 15.2 ربط الموظف بحساب المستخدم — `users.employee_id`

العلاقة المحورية بين «الشخص» و«حساب الدخول»:

```
users.employee_id  ──(UNIQUE, FK fk_users_employee)──►  employees.id     [علاقة 1:1 اختيارية]
```

- **علاقة واحد-لواحد اختيارية:** كل حساب دخول قد يُربط بموظف واحد على الأكثر (`UNIQUE`)، وقد يبقى بلا موظف (حسابات نظامية/فنية). عكسياً، قد يوجد موظف بلا حساب دخول (السائق الميداني الذي لا يسجّل الدخول).
- **قرار تصميمي:** اختير **الربط (Link) لا الدمج (Merge)** — بقي `users` (المصادقة/التفويض/الجلسة) منفصلاً عن `employees` (بيانات الشخص)، ويجسر بينهما عمود واحد. (الاختيار موثّق في الذاكرة [[users-employees-link]].)
- **واجهة الربط:** تُدار من `main/users.php` و`Employees/employee_profile.php` (تعيين/فك ربط بأي دور وصول).
- **تسمية «الفاعل» الموحّدة (`includes/actor_helper.php`):** كل أعمدة `*_by` (مثل `created_by/updated_by/deleted_by`) تخزّن **رقم المستخدم**، ودالة `ems_actor_label()` تحوّله إلى «اسم الموظف — (الحساب · الدور)» عبر السلسلة:
  ```
  users.*_by  →  users.employee_id  →  employees.name   (+ roles.name للدور)
  ```
  فإن لم يكن الحساب مرتبطاً بموظف تُظهر تنبيه «بلا موظف». الدالة مُخزَّنة مؤقتاً لكل `user_id` (آمنة للاستدعاء في كل صفّ جدول).

> **تمييز جوهري:** `users.role` (دور **النظام** — تفويض RBAC، أرقام سحرية) شيء، و`employees.employee_role_id` (دور **الموارد البشرية** الوظيفي — مسمّى تنظيمي) شيء آخر تماماً. الجدولان `roles` و`employee_roles` منفصلان ولا يتقاطعان.

### 15.3 التصنيف الوظيفي: `job_titles` و`employee_roles`

تصنيفان مرجعيان مملوكان للموارد البشرية (الدور 4)، معزولان بـ`company_id`:

- **`job_titles`** (`name, is_operator, sort_order, status`): المسمّى الوظيفي. علَم **`is_operator`** يميّز المسمّيات التشغيلية (سائق/مشغّل) التي تستوجب ملف تشغيل ورخصة.
- **`employee_roles`** (`name, description, sort_order`): الدور التنظيمي الوظيفي (≠ دور النظام).
- CRUD لكليهما في `Employees/job_titles.php` و`Employees/employee_roles.php` (الدور 4).

### 15.4 المشغّل والمعدّة: `equipment_operators` و`equipment_drivers`

بُعدان مختلفان لربط الشخص بالتشغيل:

- **`equipment_operators`** (`employee_id → employees`): **بطاقة رخصة/تأهيل التشغيل** للموظف — التفويضات (`driving_authorizations`), الفئات (`operating_categories`), الرخصة (رقم/نوع/درجة/إصدار/انتهاء + صورة), التقرير الطبّي. علاقة تأهيلية بالموظف (بلا ربط بمعدّة بعينها).
- **`equipment_drivers`** (`employee_id → employees`, `equipment_id → equipments`): **تخصيص فعلي** لموظف على معدّة محدّدة، مع `shift_type, start_date, end_date, status`. هذا هو الجسر الذي يربط **الشخص ↔ المعدّة ↔ التشغيل**:
  ```
  employees ──< equipment_drivers >── equipments ──(equipment=equipment_id)── operations
  ```
  الحقل النشط (`status=1`) يُستخدم في الـ Views لاستنتاج «داخل الموقع» وعدد العمليات.

### 15.5 عقود الموظفين: `drivercontracts`

مسار عقود مستقلّ للموظفين/المشغّلين (موازٍ لعقود المشاريع `contracts` وعقود الموردين `supplierscontracts`):

- **`drivercontracts`** (`employee_id, project_id, project_contract_id`): بنود التعاقد (المدّة، الورديات، الأهداف الشهرية `mach_*/equip_*`, الأطراف، الدفع، الإيقاف/الاستئناف).
- **`drivercontractequipments`**: تفاصيل معدّات عقد الموظف (الكميات الأساسية/الاحتياطية، الورديات، الأسعار، المشغّلون/الفنّيون/المشرفون).
- **`driver_contract_notes`**: ملاحظات العقد.

> هذا أحد مواطن **ازدواج المسارات الثلاثة للعقود** المذكور في قسم الديون المعمارية (عام/موردين/موظفين بمنطق مكرّر).

### 15.6 دورة حياة القوى العاملة: جداول `worker_*`

عندما يكون `employees.is_workforce = 1`، تُفعَّل طبقة إدارة القوى العاملة (Workforce). كل جداولها ترتبط بـ`employees.id` عبر FK `employee_id` (وهي مصدر معظم الـ 11 FK المُشيرة إلى `employees`):

| الجدول | الوظيفة | أعمدة دالّة |
|---|---|---|
| `worker_contract` | عقد التوظيف المفصّل | `contract_type, wage, wage_method, rotation_pattern, next_rotation_date, allow_housing/food/transport/site, planned_backup_id, state` |
| `worker_qualification` | الشهادات والاعتمادات مع تنبيهات الانتهاء | `record_type, accreditation_category, equipment_type, issue/expiry_date, is_critical, alert_lead_days, proficiency_level` |
| `worker_evaluation` (+ `worker_evaluation_kpi`) | التقييم والأداء والحوافز/الجزاءات | `period, score, productivity, safety_score, attendance_rate, incentive_penalty_type('حافز'/'جزاء'), amount` |
| `worker_leave_absence` | الإجازات والغياب مع البديل | `event_class, event_type, date_from/to, substitute_id, coverage_impact, state` |
| `worker_movement` | التنقّل بين المشاريع + العُهد + السكن | `direction, from/to_project_id, housing_unit_id, custody_received, safety_kit_received, transport_mode, state` |
| `worker_restricted_site` | حظر عامل عن مشروع معيّن | `project_id, reason` |
| `worker_backup` | اقتران الأساسي↔الاحتياطي | `employee_id, backup_employee_id, backup_type` (FK مزدوج لـ`employees`) |
| `worker_settlement` (+ `worker_settlement_line`) | التسوية المالية للعامل | `worker_contract_id, settlement_basis, settlement_party, net_amount, state` |

جدولان مساندان لا يرتبطان مباشرة بموظف بعينه:
- **`workforce_requirement`**: تخطيط الاحتياج (الطلب) لكل مشروع/فئة (`required/available/shortage/surplus_qty, priority, need_date, fulfillment_stage`).
- **`housing_unit`**: وحدات السكن لكل مشروع (`capacity, location`) — يشير إليها `worker_movement.housing_unit_id`.

### 15.7 التقارير المشتقّة: الـ Views (`v_worker_*`)

الطبقة الوحيدة في النظام التي تستخدم **Views** لتركيب بيانات الموظف من مصادر متعدّدة (كلها تنطلق من `employees`):

- **`v_worker_billable_hours`**: يربط `employees ⋈ timesheet` (عبر `CAST(timesheet.employee_id AS UNSIGNED) = employees.id`) لحساب الساعات المنتِجة/الانتظار/التعطّل و**الأساس القابل للفوترة** (`billable_baseline`) لكل يوم/عملية.
- **`v_worker_presence`**: يشتقّ **حالة التواجد** (`داخل الموقع` / `خارج الموقع/إجازة` / `في الطريق` / `بانتظار التخصيص` / `منتهٍ`) من `workforce_state` + `worker_leave_absence` + `worker_movement` + `equipment_drivers` النشط.
- **`v_worker_worklog`**: يجمّع لكل موظف: عدد العمليات، إجمالي الساعات القابلة للفوترة، عدد الإجازات/التنقّلات/التقييمات، وإجماليات الحوافز والجزاءات.

### 15.8 ربط كشف الساعات بالموظف

جدول **`timesheet`** يربط بالموظف عبر ثلاثة أعمدة نصّية:
- `employee_id` (varchar) → الموظف المُشغِّل (يُقارَن بـ`employees.id` بعد `CAST`).
- `operator` (varchar) → معرّف العملية (`operations.id`).
- `user_id` (int) → حساب المستخدم الذي أدخل الكشف.

> ملاحظة: الربط نصّي مع `CAST`، ما يكسر الفهارس ويعكس النمط العام لتخزين المفاتيح كنصوص في النواة القديمة.

### 15.9 طبقة الخدمات (`app/Services/Workforce/`)

الوحدة الوحيدة — عدا سجل النشاط وExcel — التي لها **طبقة خدمات OOP** فعلية تحت `App\Services\Workforce`:

`AccreditationService` (الاعتمادات/الشهادات) · `CoverageService` (التغطية والبدائل) · `EventService` (الإجازات/التنقّلات) · `HumanReadinessService` (الجاهزية البشرية) · `PlanningService` (تخطيط الاحتياج) · `RotationService` (التناوب) · `WorkerCategory` (تصنيف العامل) · `ViewModal` (عرض التفاصيل). ملكية الطبقة للدور **4 (الموارد البشرية)** الذي يملك ~20 وحدة.

### 15.10 الخريطة العلائقية المجمّعة (Entity-Relationship Map)

```
                       ┌─────────────────────────────┐
   roles (النظام) ◄────┤ users  (المصادقة/التفويض)    │
        ▲              │  · role   (RBAC، رقم نصّي)   │
        │ role         │  · company_id               │
        │              │  · employee_id  (UNIQUE FK) ─┼───┐  1:1 اختيارية
        └──────────────┤                             │   │
                       └─────────────────────────────┘   │
                                                          ▼
   employee_roles ◄──┐                    ┌──────────────────────────────┐
   (دور وظيفي)       ├── employee_role_id │      employees  (SSOT)       │
   job_titles ◄──────┤── job_title_id     │  · is_workforce              │
   (is_operator)     │                    │  · source_type / supplier_id │
   suppliers ◄───────┴── supplier_id      │  · primary_backup_id         │
                                          └──────────────────────────────┘
                                   employee_id │ (FK من كل ما يلي)
        ┌───────────────┬───────────────┬──────┴────────┬───────────────┬───────────────┐
        ▼               ▼               ▼               ▼               ▼               ▼
 equipment_operators  equipment_drivers   drivercontracts   worker_contract   worker_qualification
 (بطاقة الرخصة)       (تخصيص معدّة)        (عقد الموظف)       worker_evaluation  worker_leave_absence
                          │                                  worker_movement    worker_restricted_site
                          ▼                                  worker_backup      worker_settlement
                     equipments ──(equipment=equipment_id)── operations ──< timesheet >── (v_worker_*)
```

**قراءة الخريطة:** الشخص يُدار مرّة واحدة في `employees`؛ حساب الدخول يُلحَق به اختيارياً عبر `users.employee_id`؛ ثم يتفرّع الموظف إلى: تأهيل التشغيل (`equipment_operators`)، تخصيص على معدّة (`equipment_drivers` → `operations` → `timesheet`)، عقد (`drivercontracts`)، ودورة حياة القوى العاملة الكاملة (`worker_*`)، وتُلخَّص النتائج في الـ Views.

### 15.11 ملاحظات وتنبيهات (Gotchas)

- **الموجة التاريخية:** كانت `employees` سابقاً VIEW انتقالياً فوق `drivers`، ثم أصبحت **جدولاً أساسياً** بعد إتمام إعادة التسمية (`drivers` حُذف؛ بقي `drivers_legacy_backup` وجداول `_bak_premerge_20260627_*` كنُسخ). طبقة `Workforce/` القديمة **استُبدلت** بالتوحيد.
- **دور HR = 4** يملك ملفات `Employees/` و`Workforce/` ومرجعيّات `job_titles`/`employee_roles`.
- **ثلاثة معرّفات للشخص قد تلتبس:** `users.id` (الحساب)، `employees.id` (الشخص)، وأعمدة `*_by` (تخزّن `users.id` لا `employees.id`) — و`actor_helper` هو الجسر الرسمي بينها.
- **الربط نصّي في مواضع** (`timesheet.employee_id`, `operations.equipment`) ما يفرض `CAST` ويكسر الفهارس.

---

## 16. تشريح صفحة الوحدة النموذجية (God-File Anatomy)

الملف الواحد في النواة القديمة يجمع كل الطبقات. مثال حيّ (`Clients/clients.php`)، والنمط يتكرّر في كل الوحدات القديمة:

```php
1) session_start()  +  فحص isset($_SESSION['user'])  → إعادة توجيه للدخول
2) include '../config.php'  +  include '../includes/permissions_helper.php'
3) ob_start('<page>_fix_mojibake_output')   ← مرشّح ترميز محلي خاص بالملف (مكرّر لكل صفحة)
4) دوال مساعدة محلية معرّفة داخل الملف:
     - <page>_table_has_column()   (فحص أعمدة دفاعي)
     - <page>_build_scope_sql()    (عزل الشركة — منطق مكرّر لكل صفحة)
     - <page>_not_deleted_sql()    (الحذف الناعم — منطق مكرّر)
     - <page>_e()                  (XSS)
5) (أحياناً) ALTER TABLE / CREATE INDEX  ← ترقية مخطّط وقت التشغيل
6) معالجة POST (إضافة/تعديل/حذف)  +  استعلامات SQL مباشرة
7) HTML + CSS مضمّن + JavaScript مضمّن  (قد يصل الملف لـ 2600 سطر)
8) include '../inheader.php'  +  include '../insidebar.php'  (الإطار المشترك)
```

**السمات المتكرّرة:** غياب فصل الطبقات، تكرار منطق العزل/الحذف/الترميز في كل ملف، اعتماد على الفحص الدفاعي للأعمدة بدل مخطّط ثابت، وحجم ملفّي ضخم (أكبر 12 ملفاً تتراوح بين 90–120KB).

---

## 17. الأنماط المعمارية المتقاطعة (Cross-Cutting)

### 17.1 محرّك الموافقات (`includes/approval_workflow.php`)
- **نمط State Machine مُوجَّه بالبيانات:** القواعد في `approval_workflow_rules` (entity_type × action × role_required × step_order)، والطلبات في `approval_requests` (payload = JSON للعمليات)، والمراحل في `approval_steps`.
- **آلية العمل:** `approval_create_request()` يُنشئ الطلب + مراحله، ويعتمد المرحلة الأولى تلقائياً إن كان المنشئ يملك دورها، ثم `approval_finalize_if_completed()` — وعند اكتمال كل المراحل يُنفّذ الـ payload عبر `approval_execute_db_operation()` (INSERT/UPDATE/DELETE مُعمَّمة بأمان عبر تحقّق أسماء الأعمدة و Prepared Statements).
- **قاعدة احتياطية (Fallback):** إن لم توجد قواعد، خريطة ثابتة داخلية للعمليات المعروفة (تعطيل معدّة/سائق)، وإلا الدور `-1` فقط.
- هذا **أنضج جزء معماري في النظام** (محرّك عام قابل لإعادة الاستخدام، معاملات DB، Prepared Statements).

### 17.2 سجلّ النشاط (Activity Log) — الطبقة الحديثة النموذجية
سلسلة كاملة بمعمارية OOP في `app/`:
```
ActivityLogMiddleware (يلتقط الطلب عبر register_shutdown_function)
   → يستنتج نوع الإجراء (create/update/delete/export/print/login...) من POST/GET/المسار
   → ActivityLogService::log()  (يؤجّل الكتابة عبر fastcgi_finish_request لعدم إبطاء الرد)
   → SaveActivityLogJob  →  QueueManager (طابور ملفّي في storage/queue/activity_logs)
   → ActivityLogRepository  →  جدول activity_logs
```
- **غير متزامن (Async):** يسجّل بعد إرسال الرد للمستخدم.
- يستثني الأدمن (له `admin_audit_log`) والمصادقة ومعالجات القراءة فقط والدردشة (تسجّل نفسها).

### 17.3 إطار Excel الموحّد (`app/Services/Excel/` + `excel.php`)
- نقطة دخول واحدة `/excel.php` + سجلّ كيانات `ExcelRegistry` تُسجَّل فيه الكيانات (Clients هو المرجع).
- مكوّنات: `ExcelService`, `Importer`, `Exporter`, `Validator`, `Styler`, `TemplateBuilder`, `FileReader`, `EntityDefinition`, `Column` — فوق `PhpSpreadsheet`.
- يُلغي التكرار السابق (قوالب/استيراد مكرّرة عبر الوحدات).

### 17.4 DDL وقت التشغيل + الفحص الدفاعي للأعمدة
نمط متقاطع يجعل الصفحات «ذاتية الترقية»: تفحص وجود عمود/جدول وتُنشئه إن غاب. مرن للنشر لكنه دَيْن معماري (أداء/تزامن/صلاحية ALTER).

### 17.5 مرشّح تصحيح الترميز (Mojibake Filter)
مُعالِج Output-Buffer عالمي (`ems_fix_mojibake_output` في config) + مرشّحات محلية لكل صفحة (`<page>_fix_mojibake_output`) لإصلاح تلف الترميز العربي في الإخراج، ولحقن سكربت توحيد الأرقام العربية/اللاتينية.

### 17.6 إطار التقارير (`emsreports/`) — إطار مصغّر مستقل
نظام التقارير الحديث هو **إطار فرعي قائم بذاته** منفصل عن النواة:
- **قالب موحّد** `emsreports/reports/_report_template.php` (~120KB) مدفوع بمتغيّر `$REPORT_CODE`؛ كل ملف تقرير (27 تقريراً) يضبط الرمز والإعدادات ويضمّن القالب — فيرث تلقائياً: العزل SaaS، فلاتر منسدلة، رسوم بيانية (Chart.js)، تصدير، وتصميم RTL.
- **نظام صلاحيات مستقل:** جدول `report_role_permissions(role_id, report_code)` بدل `modules`/`role_permissions`؛ ودوال `checkReportPermission`/`getAvailableReports`/`getReportsCatalog` في `emsreports/includes/functions.php`.
- **عزل خاص:** دالة `rptCompanyScope($conn,$alias,$table,$companyId,$isSuper)` تعيد بناء منطق نطاق الشركة داخل التقارير (تكرار لمنطق العزل)، وقوائم منسدلة جاهزة (`getProjectsForDropdown`...).
- **تصدير:** `emsreports/includes/export.php`. الإعداد الأولي للصلاحيات: `emsreports/setup_permissions.php`. (يوجد أيضاً مجلد `Reports/` القديم الأبسط.)

### 17.7 نمط معالِجات AJAX (`get_*.php` / `*_handler.php`)
نمط متقاطع لكل الوحدات: نقاط نهاية JSON رفيعة منفصلة عن صفحة العرض تخدم القوائم المنسدلة المترابطة والحفظ غير المتزامن.
- **الحماية:** `ems_enforce_ajax_endpoint_security` تفرض `X-Requested-With: XMLHttpRequest` + جلسة + Rate-Limit لكل نقطة.
- **الاصطلاح:** `get_*.php` للقراءة (تعبئة عناصر الواجهة)، و`*_handler.php` للكتابة (إضافة/تعديل/حذف).
- **ثغرة معروفة:** التفويض على مستوى الإجراء غير مُعمَّم — كثير من المعالِجات تكتفي بالجلسة دون فحص `check_permission` للوحدة (انظر قسم الديون المعمارية).

---

## 18. الوحدات الوظيفية (جرد كامل)

### 18.1 وحدات النواة القديمة (إجرائية، God-Files)
| الوحدة | المجلد | الوظيفة |
|---|---|---|
| العملاء | `Clients/` | العملاء وشجرة العميل→المشروع |
| المشاريع | `Projects/` | المشاريع والمناجم |
| العقود | `Contracts/` | العقود الرئيسية (+ مساري الموردين والسائقين المنفصلين) |
| الموردون | `Suppliers/` | الموردون وعقودهم ومعدّاتهم |
| المعدّات والأسطول | `Equipments/` | المعدّات، الأنواع، بطاقة الأسطول، الإهلاك، السائقون على المعدّة |
| المشغّلون | `Oprators/` | المشغّلون وربطهم بالعقود والمناجم |
| الموظفون/الموارد البشرية | `Employees/` `Workforce/` | SSOT الأشخاص، المسمّيات، عقود الموظفين، طبقة HR |
| كشوف الساعات | `Timesheet/` | إدخال ساعات التشغيل والأعطال |
| الحركة والتشغيل | `movement/` | تحريك المشغّلين/السائقين، `operations`، الورديات، الخريطة |
| الموافقات | `Approvals/` | واجهة محرّك الموافقات |
| التقارير | `Reports/` + `emsreports/` | تقارير تفصيلية/ملخّصة (نظام صلاحيات منفصل) |
| المراسلات | `chats/` | رسائل داخلية بين المستخدمين |
| سجل النشاط | `ActivityLogs/` | عرض `activity_logs` |
| الإعدادات | `Settings/` | الأدوار، الصلاحيات، الوحدات، كلمة المرور |
| المبيعات/CRM | `Opportunities/` | الفرص (INJAZ-S05، الدور 12) |

### 18.2 الوحدات الجديدة (نمط مكتفٍ ذاتياً)
| الوحدة | المجلد | الجداول | الدور | ملف الخدمات | Cron |
|---|---|---|---|---|---|
| الصيانة | `Maintenance/` | `mnt_*` (11) | 13/14 | `mnt_helpers` | — |
| المشتريات | `Procurement/` | `proc_*` (15، بلا FK) | 16 | — | — |
| الإدارة المالية | `Finance/` | `fin_*` (29) | 17–22 | `fin_helpers.php` (~40KB) | `cron_finance_fin.php` |
| النقل والترحيل | `Transport/` | `transfer_*`/`trs_*` (10) | 23 | — | — |

### 18.3 نماذج الحالة في المجال (Domain State-Machines)
يعتمد النظام على **آلات حالة ضمنية** مدفونة في منطق الصفحات (لا محرّك حالة مركزي عدا الموافقات):

- **المعدّة ثنائية المحور (`operations`):** حالتان مستقلّتان — `op_state('تعمل'/'جاهزة'/'معطلة')` تُدار **حصرياً من صفحة الحركة**، و`equipment_health('سليمة'/'معطلة')` تُدار **من الصيانة**؛ ومنفصلتان عن الدور التشغيلي (`status`: أساسي/احتياطي). ضبط `op_state='معطلة/صيانة'` **يفتح أمر صيانة تلقائياً** (`mnt_order.is_auto`)، وعدّاد جرس الصيانة يحصي الأوامر التلقائية المفتوحة.
- **الصيانة (`mnt_*`):** دورة عمل كاملة — أوامر (`mnt_order` + `_labor`/`_part`)، فحوص عبر قوالب (`mnt_inspection_template(_line)` → `mnt_inspection(_line)`)، خطط وقائية (`mnt_plan(_task)`)، بلاغات موحّدة (`mnt_breakdown`)، وجداول بحث (`mnt_lookup`, `failure_codes`). الإغلاق يعيد المعدّة تلقائياً (`equipment_health='سليمة'`).
- **سجلّ حركة المعدّة (Equipment Lifecycle Log):** سجلّ مُغذّى تلقائياً على `fleet_equipment_history` عبر مساعد واحد (`includes/equipment_log_helper.php`: `log_equipment_event`) مُستدعى من خطّافات (hooks) في ~5 ملفات، ويُسجّل فقط عند تغيّر فعلي؛ يظهر كتبويب «تحركات الآلية» في بطاقة المعدّة.
- **بطاقة الأسطول (`fleet_*`):** طراز المعدّة (`fleet_model` + `fleet_model_service_spec`)، ملفّ الإهلاك (`fleet_depreciation_profile` + `_audit`)، والمكوّنات/الامتثال/الحماية (`fleet_equipment_component/compliance/protection`).
- **اعتماد الساعات (Timesheet Approval):** موافقة هرمية عبر مستويات الأدوار (1→4) على `timesheet_approvals`(+`_notes`, `_failure_hours`)، وعدّاد المعلّق في القائمة الجانبية مُخزَّن بالجلسة (TTL=60s).

### 18.4 المراسلات وإدارة الميتا (Cross-Module)
- **المراسلات (`chats/`، جدول `messages`):** رسائل داخلية بنمط **Polling** (`get_messages`, `get_unread_count`, `mark_read`, `send_message`, `send_broadcast`)، متاحة لكل مستخدم مسجّل (خارج فحص صلاحية الوحدة)، وتكتب سجلّات نشاطها بنفسها.
- **إدارة الميتا (`Settings/`):** الواجهة التي **تُدار بها جداول RBAC نفسها** — `Settings/roles.php` (الأدوار)، `Settings/modules.php` (تسجيل الشاشات: `code`/`owner_role_id`/`is_link`/`icon`/`display_order`)، `Settings/role_permissions.php` (مصفوفة الصلاحيات)، `change_password.php`. أي وحدة جديدة تُدرَج في القائمة بإضافة صفّ في `modules` من هنا أو عبر هجرة.

---

## 19. نمط «الوحدة المكتفية ذاتياً» (Self-Contained Module Pattern)

هذا هو **النمط المرجعي للتوسّع الحالي**، وأي معمارية مستقبلية على الأرجح ستعمّمه أو تستبدله. عناصره:

1. **جداول ذات بادئة خاصة بلا FK للنواة** (`fin_`, `proc_`, `trs_`) → لا كسر للنظام القائم، وترحيل مستقل.
2. **طبقة خدمات إجرائية في ملف واحد** (`*_helpers.php`) تحوي منطق الأعمال (حسابات، ترحيل قيود، إشعارات) — بديل مصغّر عن طبقة Service حقيقية.
3. **تسجيل غير كاسر في النظام العام:** إضافة أدوار في `roles`، شاشات في `modules`، صلاحيات في `role_permissions` عبر هجرة SQL.
4. **قناة تكامل بالأحداث بدل FK:** التغذية إلى `fin_financial_events` (ENUM موسّع) بدل علاقات صريحة.
5. **مهمّة مجدولة اختيارية** (`cron_*`) للمراقبة الدورية (تحديث حالات، تنبيهات) — تعمل CLI أو GET بمفتاح.
6. **إشعارات داخلية** (`fin_notifications`) مع منع تكرار يومي.

مثال حيّ: `Finance/cron_finance_fin.php` يحدّث الذمم المتأخرة، أقساط التمويل، يعيد احتساب فعلي الموازنة، ويطلق تنبيهات — كل ذلك ضمن جزيرة `fin_*` دون لمس النواة.

---

## 20. طبقة الواجهة الأمامية

- **الإطار المشترك:** `inheader.php` (رأس HTML + تحميل الأصول مع cache-busting عبر `filemtime`) + `insidebar.php` (القائمة الجانبية الديناميكية + عدّادات إشعارات مثل الموافقات المعلّقة، مع Cache في الجلسة TTL=60s) + `includes/topbar.php` (شريط علوي رمادي مشترك `.ems-topbar`).
- **طبقات CSS موحّدة (Design System متطوّر):**
  - `design-tokens.css` / `brand-identity.css` / `site-identity.css` — الهوية والألوان (الذهبي الرسمي في `ems.main.all.style.css`).
  - `ems-forms.css` — **المصدر الوحيد لتصميم كل النماذج** (هوية Floating-Pill الذهبية).
  - `ems-tables.css` — تصميم الجداول الموحّد.
  - `client-tree.css`, `map-page.css`, `admin-style.css`... — متخصّصة.
- **طبقات JS موحّدة:**
  - `ui-unification.js` — يُهيّئ DataTables تلقائياً لكل جدول (استجابة + منع أخطاء إعادة التهيئة via `no-datatable`).
  - `performance-boost.js` — يضبط `stateSave`/`errMode` عالمياً لـ DataTables.
  - `ems-details-modal.js` (`EmsDetailsModal`) — **المكوّن الوحيد** لكل نوافذ التفاصيل/العرض.
  - `ems-forms`/`ems-select.js` — توحيد عناصر النماذج والقوائم المنسدلة.
  - `csrf.js` — حقن توكن CSRF في طلبات AJAX.
  - `number-format-unifier.js` — توحيد نظام الأرقام (لاتيني/عربي-هندي) يُحقَن من مرشّح الإخراج.
  - `column-groups.js`, `ems-excel.js`.
- **RTL كامل** بخطوط عربية محلّية. تجاوب الهاتف جزئي.
- **DataTables** هو محرّك الجداول (فرز/تصفية/ترقيم من جهة العميل غالباً).

---

## 21. المهام المجدولة (Cron)

- نمط: سكربت PHP يعمل عبر **CLI أو GET بمفتاح حماية** (`?key=finance-cron`)، يستدعي `config.php` ثم منطق `*_helpers.php`.
- المثال القائم: `Finance/cron_finance_fin.php` (تحديث حالات الذمم/التمويل، إعادة احتساب الموازنة، إطلاق التنبيهات لكل شركة).
- لا يوجد Scheduler مركزي؛ الجدولة عبر Cron النظام. غياب Cron لا يكسر النظام (المهام تصحيحية/تنبيهية).

---

## 22. التسجيل والمراقبة (Observability)

| السجل | الموقع | المحتوى |
|---|---|---|
| الأمان | `logs/security.log` | مخالفات CSRF، بصمة جلسة، أحداث مشبوهة |
| أخطاء PHP | `logs/php_errors.log` | أخطاء التشغيل (display_errors=0) |
| نشاط المستخدم | جدول `activity_logs` | create/update/delete/export... (غير متزامن) |
| تدقيق الأدمن | جدول `admin_audit_log` | أحداث لوحة الإدارة الفائقة |
| تدقيق عام | جدول `audit_logs` | سجل تدقيق إضافي |

- طابور المهام ملفّي في `storage/queue/`. لا يوجد نظام مراقبة مركزي (APM) ولا تتبّع موزّع.

---

## 23. سمات الجودة (Quality Attributes)

| السمة | الحالة المعمارية |
|---|---|
| **القابلية للتوسّع (Scalability)** | مقيّدة أفقياً: الحالة في جلسة ملفّية، طابور ملفّي، قاعدة واحدة مشتركة، عزل باستعلامات فرعية مترابطة. عمودياً مقبول. |
| **الأداء** | يعتمد على InnoDB + فهارس (152+)، لكن يُثقله: فحص/تعديل مخطّط وقت التشغيل، فحص صلاحيات N+1، مرشّح mojibake على كامل الإخراج، و`CAST` يكسر الفهارس. |
| **الأمان** | نواة ناضجة (جلسات/CSRF/رؤوس/bcrypt)، لكن CSP متساهل، وأسرار في الكود، وتفويض AJAX غير موحّد على مستوى الإجراء. |
| **الصيانة (Maintainability)** | أضعف بُعد في النواة القديمة: ملفات عملاقة، تكرار منطق، أرقام أدوار سحرية، غياب اختبارات آلية. أفضل بكثير في الوحدات الجديدة والطبقة الحديثة. |
| **القابلية للنقل (Portability)** | عالية بفضل الفحص الدفاعي و DDL الذاتي (تعمل عبر بيئات مختلفة المخطّط)، على حساب النظافة. |
| **الاتساق (Consistency)** | متفاوت: النواة القديمة غير متّسقة (عزل/حذف ناعم/ترميز مكرّر)، والوحدات الجديدة متّسقة داخلياً حول نمط موحّد. |
| **الاختبارية (Testability)** | منخفضة في النواة (منطق مدفون في الصفحات)؛ لا توجد اختبارات وحدة/تكامل — فقط مقارنة لقطات شاشة (`.ssdiff/`). |

---

## 24. الديون المعمارية ونقاط الاحتكاك (ضرورية لدراسة إعادة الهيكلة)

> هذا القسم يرصد نقاط الاحتكاك التي تحدّد **حدود المعمارية الحالية** وتُشكّل نقاط الانطلاق لأي انتقال. (مرجع أوسع: `ems_report.md`.)

1. **الجلسة الملفّية = عائق التوسّع الأفقي الأول.** أي انتقال لبنية موزّعة/عديمة الحالة يتطلّب Session Store مشترك أو مصادقة عديمة الحالة (Token) موحّدة على الويب أيضاً.
2. **الملف-كمسار بلا Router.** لا يوجد Front Controller للتطبيق الرئيسي؛ التوجيه = نظام الملفات. أي انتقال لمعمارية MVC / فصل الطبقات يتطلّب طبقة توجيه مركزية.
3. **DDL وقت التشغيل + الفحص الدفاعي للأعمدة** في ~20/106 ملفاً: يجب استبداله بنظام Migrations منضبط قبل أي فصل طبقات.
4. **غياب طبقة وصول بيانات موحّدة:** ~1135 استعلام خام، منطق عزل/حذف مكرّر لكل صفحة. أي معمارية جديدة تبدأ بطبقة Repository/Domain موحّدة.
5. **الملفات العملاقة (God-Files):** 12 ملفاً بين 90–120KB تخلط كل الطبقات — أكبر عائق أمام الفصل التدريجي.
6. **أرقام الأدوار السحرية** منتشرة بلا ثوابت — أي إعادة تصميم للتفويض يجب أن تمركزها.
7. **العزل الهشّ عبر `created_by`/استعلامات فرعية** بدل `company_id` مباشر مفهرس في كل مكان.
8. **الأسرار في `config.php`** (لا `.env`) — يجب فصلها قبل أي أتمتة نشر.
9. **ازدواج أنظمة متوازية:** ثلاثة مسارات عقود (عام/موردين/سائقين)، نظاما صلاحيات (`role_permissions` مقابل `report_role_permissions`)، أنظمة حذف ناعم متعدّدة — مرشّحة للتوحيد.
10. **الطبقة الحديثة (`app/`) جزيرة معزولة** مستخدمة في نظامين فقط رغم جاهزيتها للتعميم — نقطة انطلاق طبيعية لتوسيع المعمارية الموجّهة للكائنات.
11. **CSP متساهل** (`unsafe-inline/eval`) بسبب JS/CSS المضمّن — يرتبط حلّه بفصل الأصول عن الصفحات.

**نقاط القوة القابلة للبناء عليها:** محرّك الموافقات العام، سلسلة سجل النشاط (Middleware→Service→Job→Queue→Repository)، إطار Excel الموحّد، نمط الوحدة المكتفية ذاتياً، و Design System الأمامي الموحّد — كلها نماذج ناضجة يمكن تعميمها في المعمارية القادمة.

---

## 25. طبقة تحكّم الـ SaaS (Admin Panel · Company Portal · نموذج الاشتراك)

هذه هي **مستوى التحكّم (Control Plane)** الذي يدير المستأجرين فوق مستوى البيانات التشغيلية. يتكوّن من بوابتين مستقلّتين ونموذج اشتراك يقيّد الميزات.

### 25.1 لوحة الإدارة الفائقة (`admin/`)
منطقة معزولة تماماً بمصادقتها وحمايتها وسجلّها الخاص:
- **مصادقة مستقلّة** (`admin/includes/auth.php`): جلسة `$_SESSION['super_admin']`، دوال `super_admin_is_logged_in/require_login/logout`، بصمة جلسة خاصة، وقفل محاولات دخول (`super_admin_login_attempts`).
- **مستثناة من الأنظمة المشتركة:** لا CSRF مركزي (لها حمايتها)، ولا سجلّ نشاط عام — بل **سجلّ تدقيق خاص** `admin_audit_log` عبر `super_admin_write_audit`.
- **تخطيط خاص:** `admin/includes/layout_head.php` / `layout_foot.php` + `admin-style.css` (منفصل عن هوية التطبيق).
- **الشاشات:** `dashboard`, `companies(.php + companies/{view,action})`, `plans`, `subscriptions/{requests}`, `managers`, `permissions/` (إدارة موازية لـ RBAC التطبيق: roles/modules/role_permissions)، `reports_permissions`, `support/`, `audit_log`, `settings`, و`setup_once` (تهيئة أولية). نقطة الدخول `admin/index.php` تحوّل حسب حالة الجلسة.
- **الكيانات:** `admin_companies`, `admin_subscription_plans`, `admin_subscription_requests`, `super_admins`, `admin_audit_log`.

### 25.2 بوابة الشركات (`company/`)
مسار موجّه لمالك/مسؤول الشركة (لا لمستخدمي التشغيل):
- **التدفّق:** `register.php` (تسجيل شركة) → `request_subscription.php` (طلب اشتراك في `admin_subscription_requests`) → اعتماد من الأدمن → تفعيل الشركة (`status='active'`). كما توفّر `home.php`, `team.php`, ومسار استعادة كلمة المرور (`forgot_password`/`reset_password` عبر `company_user_password_resets`).
- **جلسة مزدوجة (`company/auth.php`):** عند نجاح الدخول تُنشأ `$_SESSION['company_user']` **و** `$_SESSION['user']` القديمة معاً (توافقية مع صفحات التطبيق)، إضافةً إلى `user_project_scope` و`role_permissions` و`plan_modules`. لكل جلسة بصمة (`fingerprint`) وسجلّ تدقيق (`company_write_audit`).
- **التوجيه حسب الدور:** `company_dashboard_for_role` — مالك الشركة (دور 1) يهبط على `company/home.php`، وبقية الأدوار على `main/dashboard.php`.

### 25.3 نموذج الاشتراك وتقييد الميزات (Subscription & Feature Gating)
- **الخطط** (`admin_subscription_plans`): `plan_name`, حدود `max_users/max_projects/max_equipments`, وقائمة `features` (نصّ متعدّد الأسطر).
- **ربط الشركة بالخطة:** `admin_companies.plan_id` + `subscription_start/end` + `modules_enabled` + `status('pending'/'active'/'suspended'/'cancelled')`.
- **التحميل وقت الدخول:** `company_load_plan_modules()` يجمع الخطة والحدود والميزات في `$_SESSION['plan_modules']` (تُقرأ في `company/home.php` وغيرها). عند إيقاف الشركة تُمسح كل مفاتيح الجلسة ويُطرد المستخدم فوراً (`ems_force_logout_if_company_suspended` في `config.php`).
- **دورة حياة المستأجر:** تسجيل → طلب اشتراك (pending) → اعتماد الأدمن (active) → (تعليق/إلغاء عند انتهاء الاشتراك أو مخالفة). الحدود (max_*) والميزات تُشكّل حدود المستأجر المنطقية فوق العزل بـ`company_id`.

---

## 26. البناء والترحيلات وحوكمة الترميز (Build · Migrations · Encoding)

### 26.1 الاعتماديات (Composer)
`composer.json` يعلن اعتماداً واحداً فقط: **`phpoffice/phpspreadsheet ^5.4`** (لإطار Excel). يسحب معه تبعيّاته (`markbaker/complex`, `markbaker/matrix`, `maennchen/zipstream-php`, حزم `psr/*`). لا إطار عمل ولا مكتبات أخرى — بقية النظام قائم على PHP القياسي و`mysqli`.

### 26.2 الترحيلات (Migrations)
- **الاصطلاح:** ملفات مؤرّخة في `database/migrations/` بصيغة `YYYY_MM_DD_<وصف>.sql` (~33 ملفاً)، تُطبَّق **يدوياً** عند النشر — **لا يوجد مُشغِّل (Runner) آلي** ولا تتبّع لحالة التطبيق في القاعدة.
- **استثناء برمجي:** ترحيل واحد بصيغة PHP (`2026_06_27_employee_unification.php`) لعمليات دمج معقّدة (توحيد `worker_profile` في `employees`).
- **المخطّط المرجعي المُصدَّر** (`equipation_manage (29).sql`) **قديم** (61 جدولاً)؛ الحالة الحيّة (159 جدولاً) هي محصّلة الدمب + الترحيلات المتعاقبة. توجد نُسخ احتياطية في `database/backups/`.
- **ازدواجية مع DDL وقت التشغيل:** إلى جانب ملفات الترحيل، تُرقّي ~20 صفحة مخطّطها ذاتياً وقت الطلب (§13) — ما يجعل مصدر الحقيقة للمخطّط موزّعاً بين الترحيلات والكود.
- **تنبيه ترحيلات ENUM العربية:** يجب تطبيق ترحيلات ENUM/الإدراج العربية بعميل `utf8mb4` (`SET NAMES utf8mb4`) وإلا يتضاعف ترميز القيم ويعجز التطبيق عن مطابقتها.

### 26.3 حوكمة الترميز (Encoding Governance)
النظام يتعامل مع الترميز العربي كأولوية معمارية، بثلاث طبقات:
- **على مستوى المستودع:** `.editorconfig` + `.encoding-config` + `.gitattributes` لفرض UTF-8، وسلسلة وثائق `ENCODING_STANDARDS.md` / `ENCODING_PREVENTION_SYSTEM.md` / `ENCODING_SECURITY_IMPLEMENTATION.md`.
- **على مستوى القاعدة:** توحيد كامل على `utf8mb4_unicode_ci` (ترحيل `2026_06_08_unify_charset`)، وضبط `set_charset('utf8mb4')` + `collation_connection` في `config.php` لمنع «Illegal mix of collations».
- **على مستوى التشغيل:** ضبط `mb_internal_encoding`/`default_charset`، ومرشّح إخراج `ems_fix_mojibake_output` لتصحيح أي تلف متبقٍّ + نظام توحيد الأرقام (`EMS_DIGIT_SYSTEM = latin|arabic-indic` عبر `number-format-unifier.js`).
- **الأدوات المساندة:** سكربتات `scripts/` وملفات `.ps1` لفحص/إصلاح الترميز، وحزمة `.ssdiff/` لمقارنة لقطات الشاشة (أقرب ما لدى النظام لاختبار انحداري).

### 26.4 ضبط البيئة (Environment)
- **الإعدادات في الكود** (`config.php`) لا في `.env` (دَيْن أمني مرصود): مضيف/مستخدم/كلمة مرور القاعدة مكتوبة مباشرة (إنتاج معطّل كتعليق، تطوير `localhost/root` مفعّل).
- **الجذر الديناميكي:** `ems_root_path()`/`ems_url()` يكتشفان بادئة المسار (مثل `/ems`) تلقائياً فيعمل النظام تحت أي مجلد جذر دون تعديل روابط.
- ثوابت التحكّم: `EMS_CSRF_ENFORCE` (مراقبة/حجب)، `EMS_DIGIT_SYSTEM` (نظام الأرقام).

---

## 27. ملاحق (خرائط مرجعية)

### 27.1 ملفات النواة المشتركة (يجب فهمها قبل أي تعديل معماري)
`config.php` (bootstrap فعلي) · `includes/security.php` · `includes/performance.php` · `includes/permissions_helper.php` · `includes/dynamic_nav.php` · `includes/approval_workflow.php` · `app/bootstrap.php` · `inheader.php` · `insidebar.php` · `includes/topbar.php`.

### 27.2 التوثيق القائم ذو الصلة (داخل المستودع)
- `ems_report.md` — تدقيق شامل سابق (4 يونيو 2026) بمنهجية نقدية + خطة 90 يوماً/12 شهراً.
- `SYSTEM_SECURITY_PERFORMANCE.md` / `SECURITY_PERFORMANCE_AR.md` — توثيق الأمان والأداء.
- `DEVELOPER_QUICK_REFERENCE.md` — قوالب الأنماط المعتمدة (CRUD/الأمان).
- `ENCODING_*` — معايير ونظام منع تلف الترميز.
- `app/Services/Excel/README.md` — إطار Excel.
- ملفات `maintenance_*`، `docs/HR_Workforce_Audit_*`، `docs/finance_user_guide_ar.md` — توثيق الوحدات الجديدة.

### 27.3 جرد مجموعات الجداول (159 جدولاً حيّاً)
النواة: `users, roles, modules, role_permissions, report_role_permissions, clients, project, contracts, contractequipments, operations, equipments, equipments_types, equipment_drivers, equipment_operators, employees, employee_roles, job_titles, suppliers, supplierscontracts, suppliercontractequipments, drivercontracts, drivercontractequipments, timesheet, timesheet_approvals, timesheet_approval_notes, timesheet_failure_hours, messages, activity_logs, audit_logs`.
الأسطول: `fleet_model, fleet_model_service_spec, fleet_depreciation_profile(+_audit), fleet_equipment_component, fleet_equipment_compliance, fleet_equipment_protection, fleet_equipment_history`.
الموافقات: `approval_requests, approval_steps, approval_workflow_rules`.
الصيانة (`mnt_*` ×11) · المالية (`fin_*` ×29) · المشتريات (`proc_*` ×15) · النقل (`transfer_*`/`trs_*` ×10) · الموارد البشرية (`worker_*`, `workforce_requirement`, `housing_unit`, `v_worker_*`).
المبيعات/CRM: `opportunities, quotations, tenders, pricelists, products, commercial_risks, units_of_measure`.
المنصّة (SaaS): `admin_companies, admin_subscription_plans, admin_subscription_requests, super_admins, admin_audit_log, api_tokens, company_user_password_resets`.
نُسخ احتياطية/هجرات: `_bak_premerge_20260627_*`, `drivers_legacy_backup`, `_bak_retire_worker_allocation`.

### 27.4 حقائق يجب التحقق منها قبل الاعتماد (تحذيرات دقّة)
- **المخطّط المُصدَّر `database/equipation_manage (29).sql` قديم (61 جدولاً)** — المصدر الموثوق هو **قاعدة البيانات الحيّة (159 جدولاً)**؛ الفروق أُضيفت عبر `database/migrations/`.
- **الدور 15 في القاعدة الحيّة هو «مدير الحسابات»** (بعض التوثيق القديم يسمّيه «مدير الصلاحيات» — تحقّق من الحيّ دائماً).
- عدّادات الأدوار/الوحدات/الجداول في هذه الوثيقة مأخوذة من الحالة الحيّة بتاريخ الإصدار وقد تتغيّر مع الهجرات اللاحقة.

---

*انتهت الوثيقة. أُعِدّت كتوصيف As-Built محايد لتكون أساساً لدراسة الانتقال المعماري.*
