> ✅ **نُفِّذ بتاريخ 2026-06-27.** تمّ دمج `worker_profile` داخل `employees` وحذفه نهائياً،
> وأصبحت جداول `worker_*` تعتمد على `employee_id` (FK نحو `employees`). انظر
> `database/migrations/2026_06_27_employee_unification.php`. ما يلي هو خطة الدمج الأصلية (تاريخية).

# ملاحظات الدمج المستقبلي: `employee` ⇄ `worker`

> ملفُ حوكمةٍ إلزاميٌّ. الهدف النهائي: دمج `worker_profile` داخل `employees` ليصبحا **كياناً واحداً**،
> وإلغاء جسر `employee_id`. هذه الطبقة (`Workforce/`) مصمَّمةٌ ليكون ذلك الدمج **عمليةً نظيفةً** لاحقاً.

## المبدأ الذي يحفظ نظافة الدمج
- `worker_profile` **لا يخزّن أي بياناتٍ شخصيةٍ** (الاسم/الهوية/الهاتف...) — تبقى كلها في `employees`.
- يخزّن فقط **السمات التشغيلية** (الفئة/المصدر/موقع القوة/الدرجة/الحالة/اللياقة/البدائل).
- العلاقة **1:1** عبر `worker_profile.employee_id` (UNIQUE) — جسرٌ بالقيمة، بلا FK مفروضٍ على `employees`.

## نقاط الجسر في الكود (علامة موحّدة `// FUTURE-MERGE`)
كل موضعٍ يترجم بين `worker_id` و`employee_id` يحمل التعليق:
`// FUTURE-MERGE: employee_id<->worker_id bridge — to be collapsed`
المواضع الحالية: `Workforce/worker_register.php` (قراءة بيانات الموظف)، وطبقة الخدمة عند مطابقة جداول الإرث (`equipment_drivers`/`timesheet`).

## خطة الدمج لاحقاً (حين يُقرَّر توحيد الكيان)
1. نقل أعمدة `worker_profile` التشغيلية إلى `employees` عبر `ALTER TABLE employees ADD COLUMN ...` (محروسة).
2. ترحيل البيانات: `UPDATE employees e JOIN worker_profile wp ON wp.employee_id = e.id SET e.<col> = wp.<col>`.
3. تحويل المفاتيح في جداول الطبقة من `worker_id` إلى `employee_id` (أو إبقاء `worker_id = employees.id` مباشرةً).
4. استبدال `worker_profile` بـVIEW توافقٍ `SELECT id AS id, id AS employee_id, ... FROM employees` لإبقاء الكود يعمل، ثم الإزالة التدريجية.
5. حذف جسر `employee_id` بعد تحويل كل المراجع.

## الجداول الحاملة لتعليق الدمج (`COMMENT`)
`worker_profile` · `worker_qualification` · `worker_backup` · `worker_restricted_site` — وكل جدولٍ جديدٍ لاحقٍ في الطبقة.

## قاعدةٌ للمطوّرين
عند إضافة أي حقلٍ جديدٍ للطبقة: إن كان **شخصياً** ضعه في `employees` (لا تكرّره)، وإن كان **تشغيلياً** ضعه في جداول `worker_*`. هذا وحده يضمن أن الدمج يبقى نقلَ أعمدةٍ تشغيليةٍ فقط.
