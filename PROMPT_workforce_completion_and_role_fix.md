# برومبت: إكمال طبقة القوى التشغيلية 100% + تصحيح خطأ الدور — Claude Code

> انسخ كل ما تحت الخط والصقه في Claude Code داخل `c:/wamp64/www/ems`.
> هذا عقدٌ تنفيذيٌّ مُلزِم. الطبقة **مبنيّةٌ جزئياً** بالفعل — **لا تُعِد بناء الموجود**؛ اجرد ما نُفِّذ وتخطّاه، صحّح خطأ الدور، ثم نفّذ المتبقّي فقط حتى يكتمل 100%. الأولوية القصوى: **الهندسة العكسية أولاً** والالتزام **بالمعايير** و**صفر كسرٍ للنظام القائم**.

---

## 0) السياق
طبقة `Workforce/` (مواصفات `EQUIP-OPE-S04`) نُفِّذت كطبقةٍ مستقلّةٍ فوق نظام EMS (PHP/MySQLi · عربي RTL · متعدّد الشركات) بنهج Bolt-on (قراءةٌ من الإرث، صفر تعديلٍ عليه). يوجد خطأٌ يجب تصحيحه أولاً، وبنودٌ ناقصةٌ يجب إكمالها.

## 1) اقرأ أولاً — هندسة عكسية إلزامية (لا تكتب قبلها)
- جدول **`roles`** فعلياً في القاعدة: `SELECT id, name, role_scope FROM roles ORDER BY id;` — **هذا مصدر الحقيقة لتعيين الأدوار، لا الافتراضات المبرمَجة.**
- `includes/permissions_helper.php` (`check_page_permissions` يطابق `code LIKE` و**يفشل مفتوحاً** إن غاب الموديول)، `config.php` (`db_table_has_column`, عزل الشركة)، `inheader.php`/`insidebar.php`/`includes/page_header.php`.
- جداول الإرث المقروءة: `employees`, `drivercontracts`, `equipment_drivers`, `operations`, `timesheet` (العمود `operator`=operations.id، `employee_id`=employees.id)، `mnt_order`, `project`, `suppliers`.
- ملفات الطبقة القائمة (المرجع لما هو منفَّذ): مجلد `Workforce/`، `app/Services/Workforce/`، `database/migrations/2026_06_25_workforce_w1..w5.sql`، `REPORT_OPE_S04_workforce_implementation.md`، `Workforce/FUTURE_MERGE_NOTES.md`.

## 2) جرد المنفَّذ — **تخطّاه ولا تُكرّره** (تحقّق من وجوده فقط)
**جداول (14):** worker_profile · worker_qualification · worker_backup · worker_restricted_site · worker_contract · worker_allocation · worker_evaluation · worker_evaluation_kpi · worker_leave_absence · worker_movement · housing_unit · worker_settlement · worker_settlement_line · workforce_requirement.
**Views (3):** v_worker_billable_hours · v_worker_presence · v_worker_worklog.
**شاشات (10) في `Workforce/`:** worker_register (8.1+8.2) · worker_contract (8.3) · worker_allocation (8.4) · worker_evaluation (8.5) · worker_leave_absence (8.6+8.13) · worker_movement (8.11+8.12) · worker_settlement (8.7) · workforce_requirement (8.10) · worker_worklog (8.9) · housing_units.
**محرّكات خدمة (4) في `app/Services/Workforce/`:** WorkerCategory · AccreditationService · QuotaService · HumanReadinessService.
> إن وجدتَ أيّ عنصرٍ أعلاه موجوداً وصحيحاً، **لا تلمسه** إلا للتصحيح المطلوب في القسمين (أ)/(ب).

## 3) الجزء (أ) — تصحيح خطأ الدور: الملكية والصلاحيات إلى **الموارد البشرية**
**الخطأ:** سُجِّلت كل الموديولات والصلاحيات وروابط القائمة للدور **3**، والصحيح أنها لإدارة **الموارد البشرية = الدور 4**.

**أولاً تحقّق عكسياً (إلزامي):** نفّذ `SELECT id, name FROM roles;` وتأكّد أيُّ معرّفٍ يقابل «الموارد البشرية». المطلوب أن تكون الملكية لمعرّف HR (المُبلَّغ أنه **4**). **إن لم يكن الاسم في جدول `roles` مطابقاً لـ«الموارد البشرية» عند المعرّف 4، توقّف واسأل المستخدم قبل أي تغيير** (لا تُصحّح خطأً بخطأ).

**ثم نفّذ التصحيح (idempotent، يعمل سواء طُبِّقت التهجيرات أو لا):**
1. **تهجير تصحيحٍ جديد** `database/migrations/2026_06_25_workforce_role_fix.sql`:
   ```sql
   SET NAMES utf8mb4;
   -- نقل ملكية موديولات الطبقة من الدور 3 إلى 4 (HR)
   UPDATE `modules` SET `owner_role_id` = 4
     WHERE `code` LIKE 'Workforce/%' AND `owner_role_id` = 3;
   -- نقل صلاحيات الدور 3 إلى الدور 4 لموديولات الطبقة (دون تكرار)
   UPDATE `role_permissions` rp
     JOIN `modules` m ON m.id = rp.module_id
     SET rp.role_id = 4
     WHERE m.code LIKE 'Workforce/%' AND rp.role_id = 3
       AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM role_permissions) x
                       WHERE x.role_id = 4 AND x.module_id = rp.module_id);
   -- إزالة أي بقايا للدور 3 على موديولات الطبقة (إن وُجد ازدواج)
   DELETE rp FROM `role_permissions` rp JOIN `modules` m ON m.id = rp.module_id
     WHERE m.code LIKE 'Workforce/%' AND rp.role_id = 3;
   ```
2. **صحّح التهجيرات الخمسة الأصلية** `2026_06_25_workforce_w1..w5.sql`: استبدل في جُمَل `INSERT INTO modules ... owner_role_id` و`INSERT ... role_permissions ... SELECT 3, m.id ...` كلَّ القيمة **3 → 4** (حتى لا يتكرر الخطأ عند إعادة التطبيق على بيئةٍ نظيفة).
3. **`insidebar.php`:** انقل بنود قائمة `Workforce/` العشرة من كتلة `if ($_SESSION['user']['role'] == "3")` إلى كتلة الدور **4** (الموارد البشرية) — **إضافةٌ/نقلٌ فقط، دون المساس ببقية الكتل**. إن لم تكن كتلة الدور 4 موجودةً بالصيغة المناسبة، أنشئ كتلةً نظيفةً للدور 4 على نفس النمط.
4. **أعد التحقق:** `SELECT m.code, m.owner_role_id, rp.role_id FROM modules m LEFT JOIN role_permissions rp ON rp.module_id=m.id WHERE m.code LIKE 'Workforce/%';` — يجب أن تكون كلها 4، ولا شيء على 3.

## 4) الجزء (ب) — إكمال البنود المتبقية حتى 100%
نفّذ فقط ما هو ناقصٌ فعلاً (تحقّق أولاً):

**B1 — تحويل المحرّكات الأربعة إلى خدماتٍ مركزيةٍ مستقلّة** في `app/Services/Workforce/` (نمط الدوال الخفيف، Prepared Statements، تُستدعى من الشاشات لا منطقٌ مكرَّر):
- `RotationService.php`: يقرأ `rotation_pattern`/`work_days`/`leave_days` من `worker_contract`، يحتسب `next_rotation_date` آلياً، ويعيد قائمة «من اقترب تدويره» خلال مدّةٍ. حدّث الشاشات/التقارير لتستدعيه.
- `CoverageService.php`: لعاملٍ خارجٍ (إجازة/غياب)، يرتّب البدائل أساسي←احتياطي←مؤقت من `worker_profile.primary_backup_id` + `worker_backup`، ويعيد البديل المطابق المتاح؛ يُستدعى من `worker_leave_absence.php` و`worker_allocation.php` (active_backup_id).
- `EventService.php`: يشتقّ الحافز/الجزاء من `worker_evaluation` المعتمد (state='معتمد')، ويجمعها للعامل/الفترة؛ يغذّي السجل (8.9). دون أي قيدٍ محاسبيٍّ فعلي (المالية يدوية — قرار 5).
- `PlanningService.php`: يقابل `workforce_requirement.required_qty` بالمتوفّر المحسوب من التخصيصات النشطة، ويعيد العجز/الفائض/الوظائف الحرجة لكل مشروعٍ وفئة (انظر B4).

**B2 — واجهة بنود مؤشّرات التقييم (`worker_evaluation_kpi`)** داخل `worker_evaluation.php`: عند تعديل تقييم، لوحةُ إضافة/عرض/حذف بنود (kpi_name, weight, score) — على نمط بنود التسوية في `worker_settlement.php`. احسب `score` الإجمالي من البنود (متوسطٌ موزون) واعرضه.

**B3 — ربط سقف الحصص بـ `daily_operators`** في `QuotaService.php` (استبدل افتراض «حسب الوردية» المُعلَّم `CONFIRM`): من `operation_id` استنتج العقد/المورد عبر `operations` ثم اقرأ `drivercontracts.daily_operators` المرتبط؛ إن تعذّر الربط، أبقِ الافتراض كحدٍّ احتياطيٍّ مع تعليقٍ واضح. **حدّد منطق الربط بالهندسة العكسية من `Oprators/get_contract_stats.php`.**

**B4 — حساب `available_qty` آلياً** في `workforce_requirement.php` (بدل الإدخال اليدوي): المتوفّر = عدد العاملين المخصَّصين فعلياً (worker_allocation state='نشط') المطابقين للمشروع والفئة (`worker_profile.worker_category`) — عبر `PlanningService`؛ أبقِ التحرير اليدوي ممكناً كتجاوز.

## 5) القواعد الحاكمة (غير قابلة للتفاوض)
- **هندسة عكسية قبل كل تعديل:** افهم الجدول/المسار الفعلي قبل لمسه.
- **صفر تعديلٍ هيكليٍّ على القائم** (لا `ALTER` على جداول الإرث). الجديد فقط، والربط بالإرث **بالقيمة**.
- **Prepared Statements** في كل كتابةٍ جديدة؛ تأكّد من تطابق سلاسل أنواع `bind_param` مع عدد/أنواع المتغيّرات (مصدر خطأٍ شائع — راجعها).
- **idempotency:** كل تهجيرٍ يعمل بأمانٍ عند إعادة التشغيل (`IF NOT EXISTS` / `NOT EXISTS` / `CREATE OR REPLACE`).
- **الهوية الموحّدة:** أي تعديل واجهةٍ يلتزم `inheader`/`insidebar`/`page_header` وأصناف `assets/css/ems.main.all.style.css` — لا CSS مضمَّن.
- **عزل الشركة** في كل استعلام، و`check_page_permissions` بوّابةً لكل شاشة.
- **التوافق مع الدمج المستقبلي:** لا بياناتٍ شخصيةٍ في جداول `worker_*`؛ علامة `// FUTURE-MERGE` عند كل جسر `employee_id↔worker_id` (انظر `Workforce/FUTURE_MERGE_NOTES.md`).

## 6) التحقّق النهائي والتسليم
1. **تقرير جردٍ** بما وُجِد منفَّذاً (مُتخطّى) وما نُفِّذ جديداً.
2. **تأكيد الدور:** استعلامٌ يُظهر أن كل موديولات `Workforce/%` على الدور 4 فقط، وأن القائمة تظهر للدور 4.
3. **فحص صياغة** كل ملفات PHP (`php -l`) وخلوّها من الأخطاء.
4. **اختبار انحدار** للشاشات القائمة (الموظفون/العقود/التشغيل/التايم‌شيت/الحركة/التقارير) — دون تغيّرٍ في سلوكها.
5. **تأكيد عدم الكسر:** `git status` يُظهر أنّ المعدَّل من القائم هو `insidebar.php` فقط (نقل الروابط)، والباقي جديد.
6. مصفوفة تغطيةٍ نهائية: الشاشات الـ13 · الجداول الـ14 · الـViews الـ3 · المحرّكات السبعة (الحصص·الجاهزية·الاعتمادات·التناوب·التغطية·الأحداث·التخطيط) — كلها ✔.

> ابدأ بالقسم (1) الهندسة العكسية، ثم (3) تصحيح الدور، ثم (4) المتبقّي، وقف للمراجعة عند اكتمال كلٍّ منها. لا تطبّق تهجيراً مدمّراً تلقائياً على بيانات الإنتاج دون نسخةٍ احتياطية.
