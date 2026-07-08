# دليل مُشغِّل الترحيلات — EMS Migrations Runner

**المرجع:** ADR-03 (EQUIP-ARC-R02 §2) · المخرَج ⑦ · المرحلة 0 — التثبيت
**الأداة:** [`database/migrate.php`](../database/migrate.php) · **التتبّع:** جدول `schema_migrations`
**تاريخ التفعيل:** 2026-07-07

---

## 1. المبدأ

**باب واحد لأي تغيير على مخطّط قاعدة البيانات.** كل `ALTER/CREATE/DROP` يُكتب ملفَ ترحيلٍ في `database/migrations/` ويُطبَّق حصريًا عبر المُشغِّل، الذي يسجّل ما طُبِّق في جدول `schema_migrations` (الملف، بصمة SHA-1، الوقت، المنفِّذ، المدة، الحالة).

ممنوع (قائمة التجميد · R02 §5):
- أي `ALTER/CREATE` وقت الطلب جديد في أي صفحة.
- أي استدعاء جديد لـ `ems_runtime_ddl()` — هذا الغلاف لتقنين القديم لا لشرعنة الجديد.
- تعديل ملف ترحيلٍ **بعد** تطبيقه (يكشفه checksum ويرفضه `up`) — التصحيح يكون بملفٍ جديد.

## 2. الأوامر

```bash
# من جذر المشروع، بـ PHP CLI (وامب: /c/wamp64/bin/php/php8.4.15/php.exe)
php database/migrate.php status        # ماذا طُبِّق وماذا ينتظر ومن تغيّر
php database/migrate.php up            # تطبيق المعلَّق بالترتيب (لقطة مخطط تلقائية قبله)
php database/migrate.php up --dry-run  # عرض ما سيُطبَّق دون تنفيذ
php database/migrate.php mark-applied <file>          # تسجيل ملفٍ طُبِّق يدويًا سابقًا (دون تنفيذ)
php database/migrate.php mark-applied --all-pending   # تسوية كل المعلَّق كخطّ أساس
php database/migrate.php baseline      # تصدير المخطط الكامل إلى database/baseline/
```

- **CLI حصريًا** — لا مسار ويب، ومجلد `database/` كله محجوب عن الويب بـ `.htaccess`.
- الاتصال يؤخذ من `config.php` (لا تكرار أسرار)، والترميز يُفرض `utf8mb4` تلقائيًا.
- رموز الخروج: `0` نجاح، `1` فشل — صالحة لأتمتة النشر.
- عند فشل ملف: يتوقف `up` فورًا ويسجّل الخطأ في `schema_migrations.error_text`. الملفات الفاشلة **يجوز** تعديلها وإعادة `up`.

## 3. عُرف كتابة الترحيلات

**التسمية:** `YYYY_MM_DD_slug.sql` (أو `.php`) — الترتيب الزمني هو ترتيب التطبيق. أي ملف خارج هذا العُرف يظهر `[UNMANAGED]` في `status` ولا يُدار.

**Idempotent إلزامي** — الملف يجب أن يكون آمنًا لإعادة التشغيل:

```sql
-- عمود جديد (النمط الحارس — MySQL لا يدعم ADD COLUMN IF NOT EXISTS):
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='t' AND COLUMN_NAME='c'),
    'ALTER TABLE t ADD COLUMN c INT NULL', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- فهرس جديد: نفس النمط مع information_schema.STATISTICS و INDEX_NAME
-- جدول جديد: CREATE TABLE IF NOT EXISTS ... (بـ utf8mb4_unicode_ci دائمًا)
-- بيانات: INSERT IGNORE أو INSERT ... WHERE NOT EXISTS
```

**قواعد إضافية:**
- يبدأ كل ملف بـ `SET NAMES utf8mb4;` — وأي قيم ENUM/نصوص عربية تُطبَّق بعميل utf8mb4 وإلا تشوّهت (المُشغِّل يضمنها؛ التطبيق اليدوي: `mysql --default-character-set=utf8mb4`).
- **ممنوع `DELIMITER`** في ملفات `.sql` — توجيه خاص بعميل mysql يفشل عبر المُشغِّل. الإجراءات المخزنة/Triggers تُكتب ترحيل `.php` (استدعاء `query()` واحد لكل عبارة).
- ترحيلات `.php` مكتفية ذاتيًا (اتصالها الخاص، مخرجاتها، `exit(0)` نجاحًا) وتُنفَّذ في عملية منفصلة — المرجع: `2026_06_27_employee_unification.php`.
- الجداول الجديدة تلتزم: `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` + أعمدة الحذف الناعم حيث يلزم.

## 4. خطّ الأساس (Baseline)

- **المرجع المعتمد يُصدَّر من قاعدة الإنتاج الحيّة** (159 جدولًا) لا من أي نسخة محلية ولا من المُصدَّر القديم (61 جدولًا — `equipation_manage (29).sql` متقادم ولا يُستخدم).
- المحلي الحالي: `database/baseline/schema_baseline_20260707_*.sql` (157 جدولًا) — **مرجع تطوير مؤقت** حتى يُصدَّر خطّ الإنتاج.
- قبل كل `up` يأخذ المُشغِّل لقطة تلقائية `auto_pre_up_*` — بديل غياب rollback للـ DDL في MySQL (implicit commit). ترحيلات **البيانات** الخطرة تتطلب `mysqldump` كاملًا قبلها.

### إجراء التفعيل الأول على الإنتاج

```bash
php database/migrate.php baseline            # 1) المخطط الحي = المرجع
php database/migrate.php up --dry-run        # 2) عرض القائمة (35 ملفًا أول مرة)
# 3) تسوية الملفات القديمة المطبَّقة يدويًا (كل شيء قبل 2026_07_07) — لا تنفيذ:
for f in database/migrations/2026_0[456]*.sql database/migrations/*.php; do
  php database/migrate.php mark-applied "$(basename "$f")"
done
php database/migrate.php mark-applied 2026_07_01_procurement_module.sql
# ... (بقية ملفات 2026_07_01 الأربعة كذلك)
php database/migrate.php up                  # 4) يطبّق فقط: فهارس 2026_05_11 + ملفَي اللحاق
php database/migrate.php status              # 5) صفر pending = جاهز
```

> ملاحظة: `2026_05_11_add_reports_performance_indexes.sql` **لا يُسوَّى** بل يُطبَّق — صيغته الأصلية (DELIMITER + إجراء مخزن) كانت تفشل بصمت (فهرس على عمود MEDIUMTEXT بلا طول بادئة)، فالفهارس غير موجودة فعليًا. الصيغة المحوَّلة حارسة وتتخطى تلقائيًا ما تقادم (جدول `drivers` المُزال وأعمدة `mine_id/driver`).

## 5. تجميد DDL وقت التشغيل — `EMS_DDL_FREEZE`

كانت 21 صفحة تنفّذ DDL دفاعيًا مع كل طلب. عولجت على نمط CSRF المنزلي (مراقبة ← حجب):

1. **ترحيلا اللحاق** `2026_07_07_catchup_runtime_ddl.sql` و`_b.sql` نقلا كل تلك العبارات إلى المُشغِّل — فحوص `db_table_has_column` أصبحت صادقة دائمًا والـ ALTER لا يُنفَّذ أبدًا.
2. **الغلاف المركزي** `ems_runtime_ddl($conn, $sql, $origin)` في `config.php` — كل مواضع الـ DDL الـ21 حُوِّلت إليه (لا `mysqli_query` خام بـ DDL خارج المُشغِّل):
   - `EMS_DDL_FREEZE = false` (الحالي): ينفَّذ كما كان + يسجَّل كل تنفيذٍ فعلي `RUNTIME_DDL_EXECUTED` في `logs/security.log`. (استدعاء `CREATE IF NOT EXISTS` على جدولٍ قائم لا-شيء ولا يُسجَّل — منع ضجيج صفحات الاعتماد.)
   - `EMS_DDL_FREEZE = true`: لا ينفَّذ + يسجَّل `RUNTIME_DDL_BLOCKED`.
3. **القلب إلى `true`**: بعد أسبوع إنتاج خالٍ من أي `RUNTIME_DDL_EXECUTED` في السجل (`grep RUNTIME_DDL logs/security.log`). قابل للتراجع بقلب الثابت.
4. **الإزالة النهائية** لكتل الفحص من الصفحات: ضمن هجرة كل شاشة في المرحلة 3 (تجنّب تعديلٍ جوهريٍّ مبكر على god-files).

### المواضع المحوَّلة (الجرد الكامل)

`Oprators/oprators.php` · `movement/movement_operations.php` · `movement/move_oprators.php` · `movement/project_drivers.php` · `main/users.php` · `main/project_users.php` · `Contracts/contracts.php` · `Clients/clients.php` · `Projects/projects.php` · `Suppliers/suppliers.php` · `Equipments/equipments.php` · `Equipments/equipments_drivers.php` · `Maintenance/breakdowns.php` · `Approvals/hours_approval.php` · `Approvals/hours_approval_followup.php` · `Approvals/hours_approval_handler.php` · `company/register.php` · `app/bootstrap.php` · `admin/setup_once.php` · `emsreports/setup_permissions.php`

## 6. سير النشر القياسي

```bash
git pull
php database/migrate.php status     # مراجعة المعلَّق
php database/migrate.php up         # التطبيق (لقطة تلقائية قبله)
```

أي ملف بحالة `[MODIFIED]` (طُبِّق ثم تغيّر محتواه) يوقف `up` — أعد المحتوى الأصلي أو ضع التصحيح في ملفٍ جديد. حالة `[FAILED]` تعني آخر تطبيقٍ فشل: عالج السبب (السجل في `schema_migrations.error_text`) وأعد `up`.

---

## ملحق (2026-07-08) — مستخدم المُرحِّل المنفصل وصلاحياته

منذ P0-2ب يتصل المشغّل بمستخدمٍ منفصل `ems_migrator` (يُقرأ من `DB_MIGRATOR_USER/PASS` في `.env`؛ غيابهما = اتصال config — توافق رجعي). صلاحياته **على `equipation_manage.*` حصرًا (لا `*.*`)**:

| الصلاحية | المبرّر |
|---|---|
| SELECT/INSERT/UPDATE/DELETE | تسجيل التتبّع وقراءة البيانات في الترحيلات |
| CREATE/ALTER/DROP/INDEX/REFERENCES | DDL الترحيلات |
| SHOW VIEW *(وُسّعت 2026-07-08 — K2)* | **ضرورة بنيوية**: لقطة المخطّط قبل التنفيذ وbaseline يستدعيان `SHOW CREATE VIEW` على الـViews — بدونها يفشل `up` ذريًّا |
| CREATE VIEW *(وُسّعت 2026-07-08 — K2)* | حاجة مُثبَتة من التاريخ الفعلي (ترحيل `2026_06_23_employees_transitional_view.sql`)؛ `DROP VIEW` مغطاة بـDROP |

التوسّع جرى بموافقة المستخدم الصريحة، وتحقّق سلوكيًّا مزدوجًا: المُرحِّل يملكهما فعلًا و`ems_app` مرفوض 1142 فيهما (لا تسرّب للمستخدم الخطأ).
