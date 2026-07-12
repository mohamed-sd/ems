# ملخّص التسليم — هجرة المالية خلف بوابة العزل (2026-07-12)

> وثيقة لبدء **محادثة جديدة** بنفس السياق والصياغة وطريقة العمل بلا انقطاع.
> المرجع الحيّ الدائم يبقى `MIGRATION_PROGRESS.md` (اقرأه أولًا — أعلاه «🔖 نقطة الاستئناف»).

---

## 0) السياق العام
- **المشروع**: نظام EMS (PHP/MySQL مونوليث) في `C:\wamp64\www\ems`.
- **العملية**: هجرة معمارية محكومة (REV-07 «المسار المتوازي») — نقل شاشات النظام خلف **بوابة عزل الشركة `TenantDb`** شاشةً شاشة، بلا كسر إنتاجي.
- **المرحلة الحالية**: المرحلة 3 — هجرة الإدارات. **المالية أولًا**، ثم Maintenance → Equipments/Employees → Contracts → **Timesheet أخيرًا** (لجواره المقدّس).
- **المستخدم = مالك النظام**؛ يتواصل بالعربية؛ بريده m.sayed@equipation.sd؛ اليوم المرجعي 2026-07-12.

---

## 1) طريقة العمل (البروتوكول — التزِمْ بها حرفيًّا)
1. **«الكود هو الحقيقة»**: لا تثق بأي سرد «مكتمل/مقبول» — تحقّق دائمًا عبر `git log` / grep / T3 قبل البناء على أي ادعاء. (تكرّر أن وردت رسائل قبول لأعمال لم تقع.)
2. **«قاعدة عدم الكسر»**: صفر كسر إنتاجي. كل تحويل قابل للعكس (git revert).
3. **مقياس T3** (نداءات SQL الخام في ملف): 
   `grep -cE "mysqli_query\(|->query\(|mysqli_prepare|mysqli_fetch|mysqli_stmt" <file>` — يجب أن يبلغ **0** بعد الهجرة.
4. **الدورة لكل شاشة** (صرامة إلزامية):
   1. اقرأ الشاشة + grep مواضع الخام.
   2. **golden قبل**: `php tests/golden_run.php --user=72 --pages=Finance/<x>.php[?param=..]` (لعرضٍ يعتمد على باراميتر: `git stash` لالتقاط الأصل ثم `git stash pop`).
   3. حوّل الخام → نداءات البوابة.
   4. `php -l` + T3=0.
   5. **golden بعد == قبل** (مطابقة بايتية — نفس عدد البايتات تمامًا).
   6. **إثبات أثر حي**: سكربت في الـscratchpad بنمط `f2_*_proof.php` — دخول u72 بتبديل hash مضمون الاسترجاع، تنفيذ **كل مسار كتابة** والتأكد من أثره في القاعدة، ثم تنظيف كامل واسترجاع كلمة المرور.
   7. **commit مستقل** لكل شاشة، ينتهي بـ:
      `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`
   8. حدّث `MIGRATION_PROGRESS.md`.
5. **انضباط النقاط الفاصلة**: أظهِر القرارات للمالك ولا تعبر بوابةً يملكها هو؛ صحّح أي رقم/ادعاء خاطئ بأمانة فورًا.

---

## 2) البوابة `TenantDb` — القدرات والأنماط المثبَتة
**الملفات**: `app/Core/TenantDb.php` · `app/Core/TenantRegistry.php` · مساعد `fin_gate($is_super)` في `Finance/fin_helpers.php:145`.

**القدرات**:
- **CRUD أساسي**: `insert/update/softDelete/select/selectOne/count` — **حقن company_id تلقائي**، حذف ناعم حصريًّا.
- `update($table,$data,$where,$whereRaw='',$rawParams=[])` — `whereRaw` لحُرّاس الحالة؛ **حارس الحصانة §12** (`immutable_key`: صفٌّ يحمل `idempotency_key` يُرفض تعديله/حذفه — يشمل softDelete والسوبر).
- `runInTransaction(callable,$note)` (**§9**) — للأزواج الكتابية المترابطة (ذرّية؛ أي رفض داخلي = rollback الكل؛ لا تعشيش).
- `scopedQuery(['scope'=>['alias'=>'table']], $sql, $params)` (**§10**) — للمجاميع/الـJOINs؛ رمز `{TENANT_SCOPE}` مرّة واحدة بعد WHERE بعمق 0 قبل GROUP BY؛ **إثراء LEFT JOIN حصرًا**؛ **ممنوع ذكر company_id في SQL**.
- `forAllTenants()` (سوبر) / `forSystem($cid,...)` (**§11**، للـcron).

**أنماط تحويل مثبَتة بالذهبي**:
- تكرار 1062: `catch (\App\Core\TenantGateException $e){ if(strpos($e->getMessage(),'Duplicate entry')!==false){...} }`.
- **INNER JOIN مُرشِّح ≡ LEFT JOIN + شرط WHERE + `enrich.id IS NOT NULL`** (تكافؤ مبرهَن بايتيًّا — يلائم قاعدة scopedQuery الـLEFT).
- استعلام **متداخل/مزدوج النطاق → قراءتان معزولتان تُدمجان في PHP**.
- **`NOT EXISTS` مترابط → ترطيب على خطوتين** (تحميل المرشّحين + مجموعة «المستعمَل»، والمطابقة في PHP).
- تعبيرات أعمدة SQL (`LEAST/CASE`/مجاميع) تُحسب **PHP-side** وتُمرَّر قيمًا حرفية (update يضبط `col=?` لا تعبيرات).
- حذف ناعم مشروط بحارس حالة → `update(...,whereRaw)` (لأن softDelete بالـid فقط).
- SUM/تجميع → `scopedQuery` بـ`{TENANT_SCOPE}`.
- **cron متعدد الشركات → override بنمط النقل**: `trs_gate()` يفحص `$GLOBALS['__trs_gate_override']`؛ الـcron يضبط `forSystem($cid)` لكل دورة ثم يصفّره. (سيُطبَّق مثله على fin عند فتح F3.)

---

## 3) القرارات الحاكمة (لا تُخالَف)
- **مقدّس**: آلية الموافقات الأربع في `Approvals/` (خصوصًا `timesheet.php`) **لا تُعدَّل إطلاقًا**.
- **قرار مالك معلّق — الحدثان 54 (رواتب 2.65M) + 55 (وقود 0.95M)**: `posted/unresolved` بمال حقيقي، مصدرهما غير مُثبَت. **لا يُعزَلان/يُعدَّلان بلا نصّك الصريح** (محاولة softDelete رفضها المصنّف — وهو محقّ).
- **خطوط F3 الحمراء الخمسة** (شاشات الناقل الحيّة): تُفتح فقط بمراجعة مستقلة — لا مسّ لكود الناشر/المستهلك/الموزّع؛ golden عدّادات الناقل `ev/dl/dlq/cursor` قبل/بعد كل commit؛ E2E ختامية؛ عزل مباشر؛ commit+rollback لكل شاشة.
- **الأسوار الأربعة**: لا بطاقة على استعلام خام · لا مستهلك جديد قبل العكسي+العطالة · لا شاشة Fail-Open · لا منح صلاحية يدوي.
- **CSRF**: `CSRF_ENFORCE_PATHS=/Finance/` في `.env` (**git يتجاهله — لا تفقده؛ Rollback = تفريغه**)؛ الحقن التلقائي عبر `ems_inject_csrf_fields`؛ الإنفاذ يحجب POST/PUT/PATCH/DELETE بلا توكن (GET مستثنى — إجراءات GET الحسّاسة تحرسها `fin_verify_action_token`).
- **مستخدم الاختبار**: `u72` (دور 17 «المدير المالي»، company_id=4). أمين الخزينة دور 21 لاختبار فصل الواجبات.

---

## 4) ما أُنجز (بمراجع commit)
- **المرحلة 0 (P0-1..P0-4)** ✅ مقبولة: `ems_app/ems_migrator`، أسرار `.env`، إطلاق CSRF، تجهيز DDL freeze.
- **المرحلة 1 (K1..K10)** ✅ مغلقة ومقبولة: البوابة الخماسية مبنية ومختبرة (117/117) ومُنفَّذة على 3 وحدات (M0 Opportunities · M1 Procurement · M2 Transport — 21 ملفًا خلف البوابة)؛ ناقل الأحداث حي (exactly-once)؛ جسر المنصب + آلة الحالة؛ الترقيم الخادمي؛ حارس الفعل (enforce).
- **A0** ✅: حارس حصانة دفتر الأحداث (§12) + ختم كتابة `events_list` (commits 5876e1f, da91a92).
- **A1** ✅: عقد عزل company_id مكتمل بالقياس، صفر DDL.
- **A2** ✅: تعبئة company_id للمالية (433 صفًّا، صفر ناقص)؛ تحقيق العشرة أحداث (8 مثبتة، 2 = 54/55 unresolved).
- **F1 — 3 شاشات قرائية** ✅: `supplier_statement` (a9a6e90) · `financial_statements` (8a8cb12) · `cfo_daily_board` (556b7b7).
- **F2 — 13 شاشة كاتبة** ✅ **مغلقة كاملةً 13/13**:
  `accounts` (2ab08ca) · `cost_report` (c88269e) · `accountants` (be66aa1) · `budget_form` (47ad05d، head+lines §9) · `dues` (a72c92f) · `management_accounting` (0a5d6dd) · `assets` (bc8189c، إهلاك ذرّي §9) · `cash_forecast` (99f5512، 3 مجاميع scopedQuery) · `funding` (c942958، §9) · `tax` (decbf21) · `payments` (7edf622، execute+effect §9 + فصل واجبات) · `periods` (cc04385، آلة حالة + close-guard count() + §9) · `bank_reconciliation` (ff8a869، automatch: NOT EXISTS→قراءتان + زوج ذرّي).
- **توثيق الحالة**: 9d5530d (إغلاق F2) · 50ff1ca (اقتران fin_helpers+cron بـF3).

---

## 5) الحالة الحالية بالضبط
- **خلف البوابة: 37 ملفًا** = 21 (مرحلة 1) + 3 (F1 قرائية) + 13 (F2 كاتبة). **الشاشات الـ16 المالية كلها `T3=0`** (مؤكَّد بالقياس).
- **الفائض الخام المتبقّي في `Finance/` — كلّه محجوب**:

| الملف | T3 | البوابة الحاجبة |
|---|---|---|
| `journal_form_fin` | 20 | مراجعة F3 |
| `events_list_fin` | 18 | مراجعة F3 |
| `unit_records_fin` | 15 | مراجعة F3 |
| `import_events_fin` | 5 | مراجعة F3 |
| `fin_helpers.php` | 34 | مقترنة بـF3 (تُهاجَر معها) |
| `cron_finance_fin` | 13 | مقترنة بـF3 (تُهاجَر معها) |
| `executive_dashboard_fin` | 5 | قرار مالك (54/55) |

---

## 6) أهمّ استنتاج هذه الجلسة (يعيد تشكيل الخطة)
**`fin_helpers` + `cron` ليسا وحدة «غير محجوبة»** — مقترنان ببنية F3 (قياس grep للمستدعين):
الدوال الخام `fin_notify`(cron+events_list+unit_records+payments) · `fin_recalc_budget_actuals`(cron+journal_form) · `fin_gen_code`(6 شاشات منها 3 F3) · `fin_auto_journal`(events_list) · `fin_log_approval`(events_list+unit_records) · `fin_period_posting_open`(journal_form) + قوائم الخيارات (events_list/unit_records/journal_form) **كلها مشتركة مع شاشات F3**.
⇒ **هجرتهما الآن تُهاجِر مسارات كتابة F3 خارج المراجعة المستقلة التي فرضها المالك.** القرار: **تُهاجَران مع F3 تحت المراجعة نفسها، لا قبلها.** المعزول الوحيد `fin_supplier_net` (dues فقط) — بلا قيمة منفردًا داخل ملفٍ مقترنٍ بالكامل.
**النتيجة: لا عمل مالية غير محجوب متبقٍّ.**

---

## 7) القراران المطلوبان منك للمضيّ
1. **عزل الحدثين 54/55** (softDelete قابل للعكس تفلتره كل تقارير المالية) → يفتح `executive_dashboard_fin`.
2. **فتح مراجعة F3 المستقلة** بخطوطها الخمسة → تُهاجَر شاشات الناقل الأربع **+ fin_helpers + cron** معًا (بنمط: forSystem للـcron + مستدعٍ+مُستدعى معًا + golden عدّادات الناقل قبل/بعد + E2E ختامية).

---

## 8) مراجع سريعة
- **المرجع الحيّ**: `MIGRATION_PROGRESS.md` (اقرأه أولًا — «🔖 نقطة الاستئناف» أعلاه).
- **المعمارية**: `docs/ARCHITECTURE_CURRENT_SYSTEM_v3_ar.md`.
- **البوابة**: `app/Core/TenantDb.php` + `app/Core/TenantRegistry.php`.
- **عدّة الذهبي**: `tests/golden_run.php` (دخول hash-swap مضمون الاسترجاع).
- **سكربتات الإثبات**: scratchpad `f2_periods_proof.php` · `f2_bankrecon_proof.php` · `f2_accounts_proof.php` (أنماط مرجعية).
- **golden baselines (u72)**: periods 27633 · bank_reconciliation 22241 (قاعدي)/26435 (بحساب) · payments 24203 · tax 26700 · funding 26710 · cash_forecast 23345 · assets 29415 · management_accounting 33119 · dues 35772 · budget_form 27855 · accountants 34668 · cost_report 25573 · supplier_statement 21444/24912/24723 · financial_statements 23421 · cfo 29231.
- **أحدث commits**: 50ff1ca · 9d5530d · ff8a869 · cc04385.
