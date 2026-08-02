# N-26 · جاهزية البنية — تقرير الفحص الحاكم والقياسات (2026-08-02 03:27:00)

## NFR-11 · الفحص الحاكم — يتصدر كل بند

**أتستضيف آلة واحدة التطبيق والقاعدة معًا؟ **نعم** (DB_HOST=localhost · WAMP)**

> **فصلها يسبق كل بند عند الإطلاق**: خادم قاعدة مستقل أولًا — وكل توصيات هذا التقرير مقيسة على وضع ما بعد الفصل. بيئة التجربة المحلية تبقى موحدة بطبيعتها (UAT معزولة لا إنتاج).

## NFR-12 · OPcache والعمال بحساب الذاكرة

| القياس | القيمة | الحكم |
|---|---|---|
| OPcache محمَّل | نعم | ✔ |
| opcache.memory_consumption | 128 | التوجيه الجاهز أدناه يثبته 256M صراحة |
| MaxRequestWorkers | 250 | ضبط بحساب الذاكرة: عامل PHP ≈ 60-80MB → على 8GB لا يتجاوز ~80 عاملًا مع القاعدة على الآلة نفسها |

```ini
; php.ini — توجيهات جاهزة (تطبيقها إعادة تشغيل أباتشي — قرار تشغيل):
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=60
```

## NFR-13 · الجلسات إلى مخزن مشترك

- الحالة: **ملفات — والبنية جاهزة للقلب** · جدول `ems_sessions`: قائم ✔
- المعالج `includes/session_bootstrap.php` محمَّل عبر `auto_prepend_file` في `.htaccess` قبل كل `session_start()` — **القلب: `EMS_SESSION_STORE=db` والرجوع قلبه عكسًا** (فلا يقفل ملفُ جلسةٍ طلباتِ المستخدم المتوازية).

## NFR-14 · اتصالات القاعدة وسجل البطيء

| القياس | القيمة | المطلوب |
|---|---|---|
| max_connections | 151 | ≥ 300 (العمال 250 + 20%) — **ناقص** |
| slow_query_log | OFF | ON |
| long_query_time | 10.000000 | **0.5 ثانية** |

> **صلاحية `SYSTEM_VARIABLES_ADMIN` غير متاحة لمستخدمَي التطبيق والمرحِّل (قياس حي)** — فالتوجيهات تُسلَّم جاهزة لmy.ini وتطبيقها إعادة تشغيل خدمة القاعدة (قرار تشغيل لا يُنفَّذ ذاتيًّا):

```ini
; my.ini — [mysqld]
max_connections=300
slow_query_log=1
long_query_time=0.5
slow_query_log_file="ems-slow.log"
```

## NFR-15 · الكرون خارج الذروة (بعد الثانية والعشرين)

| الدورية | الموعد المقترح | الأمر |
|---|---|---|
| عامل الطابور (خفيف — يجوز نهارًا) | كل 5 دقائق | `php Operations/cron_job_worker.php 10` |
| مهل البلاغات (خفيف) | كل 5 دقائق | `php Tickets/cron_tickets.php` |
| التكليفات والأذونات | 22:15 | `php Operations/cron_org_assignments.php` |
| الصلاحيات (السقوط وكسر الزجاج) | 22:30 | `php Governance/cron_permissions.php` |
| الدوريات المالية (ثقيلة) | 22:45 | `php Operations/cron_job_worker.php` بعد `enqueue periodic_cron` |
| الإهلاك والأحداث الدورية | 23:00 | `php Finance/cron_depreciation_fin.php` · `cron_periodic_fin.php` |
| النقل الآلي للتناوب | 23:30 | `php Operations/cron_rotation_transfer.php` |

```bat
REM جدولة وندوز (تُنفَّذ بيد المشغّل — أمثلة جاهزة):
schtasks /Create /TN EMS\JobWorker /SC MINUTE /MO 5 /TR "C:\wamp64\bin\php\php8.2.30\php.exe C:\wamp64\www\ems\Operations\cron_job_worker.php 10"
schtasks /Create /TN EMS\NightlyOrg /SC DAILY /ST 22:15 /TR "C:\wamp64\bin\php\php8.2.30\php.exe C:\wamp64\www\ems\Operations\cron_org_assignments.php"
```
