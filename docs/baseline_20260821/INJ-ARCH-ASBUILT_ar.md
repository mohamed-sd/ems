# INJ-ARCH-ASBUILT — المعمارية الفعلية الحالية لمنصة إنجاز (As-Built)

> **اللقطة:** `BL-20260821-f0bc3e4e` · القياس: 2026-08-21 · التفصيل الكامل لنقطة القياس في [SNAPSHOT.md](SNAPSHOT.md)
> **القاعدة:** هذه وثيقة **ما هو مبني فعلًا** — لا Target Architecture. كل حكم مسنود إما إلى `ملف:سطر` وإما إلى استعلام منفَّذ على القاعدة الحية `equipation_manage` (MariaDB 11.4.9). ما تعذّر تحديده وُسم `UNKNOWN`.
> **المعرّفات المشتركة:** `SCR-xxxx` (شاشة — سجلها في INJ-SCREENS-MASTER.xlsx) · `GAP-xx` (فجوة — سجلها الكامل في INJ-CURRENT-STATUS) · `CAP-xx` (قدرة).

---

## §1 الخريطة العليا للنظام

```
┌─ المتصفح ──────────────────────────────────────────────────────────────────┐
│  شاشة PHP (444 مصيَّرة)   │ topbar (9 روابط مصلَّبة) │ AJAX POST │ /api (Bearer)│
└──────┬──────────────────────────┬──────────────┬─────────────┬─────────────┘
       ▼                          ▼              ▼             ▼
┌─ طبقة الدخول والحراسة ─────────────────────────────────────────────────────┐
│ session_bootstrap → config.php:                                            │
│   ① ems_enforce_ajax_endpoint_security (config.php:466)                    │
│   ② ems_enforce_action_permission — حارس الأفعال ADR-06 (config.php:471)   │
│   ③ ems_enforce_csrf_protection (config.php:479)                           │
│ ثم في الشاشة: فحص الجلسة → بوابة الشركة → check_page_permissions /         │
│ enforce_current_page_view_permission → حارس الكتابة → insidebar            │
└──────┬─────────────────────────────────────────────────────────────────────┘
       ▼
┌─ الهوية والصلاحية ─────────────────┐  ┌─ التنقّل ────────────────────────────┐
│ users(75) → roles(35) →            │  │ nav_items(1646 نشط) + nav_canonical  │
│ مساران للحقيقة:                    │  │ + nav_route_group + nav_group_       │
│  أ) gov_authority_grants→profiles→ │  │ taxonomy(12) → unified_nav.php →     │
│     gov_profile_items (97٪ من      │  │ printEmsTenGroupNav → السايدبار      │
│     المستخدمين — يُبطل ب كليًّا)   │  │ (dynamic_nav القديم: للسوبر فقط)     │
│  ب) role_permissions(3041)         │  └──────────────────────────────────────┘
└──────┬─────────────────────────────┘
       ▼
┌─ العزل ────────────────────────────────────────────────────────────────────┐
│ TenantDb/scopedQuery (بوابة المستأجر — enforce) · space_scope (عزل         │
│ المساحات بـcls=FORBIDDEN) · fin_project_scope (أدوار 5,6 fail-closed)      │
└──────┬─────────────────────────────────────────────────────────────────────┘
       ▼
┌─ الخدمات ─ app/Services (193 ملفًا/33 نطاقًا) + includes (98) ─────────────┐
│ Contract(27) Finance(17) Capacity(15) Security(13) Payroll(7) …            │
└──┬───────────────┬───────────────────┬─────────────────────────────────────┘
   ▼               ▼                   ▼
┌─ الاعتماد ─────┐ ┌─ الأحداث ───────┐ ┌─ المالية ──────────────────────────┐
│ ٥ مصادر حقيقة  │ │ EventPublisher  │ │ fin_financial_events (5,256)       │
│ متزامنة (§7) — │ │ → ems_business_ │ │ → EffectFanout → fin_event_effects │
│ الحي: الموروث  │ │ events (21,284) │ │ → PostingService → fin_journal_    │
│ بسقوط للسوبر   │ │ 58 نوعًا/0      │ │ entries(6,713)+lines(13,426)       │
│                │ │ مستهلك أعمال    │ │ قيد مزدوج متوازن: فرق 0.00         │
└────────────────┘ └───────┬─────────┘ └────────────────────────────────────┘
                           ▼
┌─ الخلفية ──────────────────────────────────────────────────────────────────┐
│ Task Scheduler (كل دقيقة) → cron_jobs.php → ems_job_queue (6,236 منجزًا)   │
│ → 8 جدولات (event_retry كل 5د · fin_posting كل 10د · …)                    │
└────────────────────────────────────────────────────────────────────────────┘
┌─ القاعدة ─ 603 جداول أساس · 478 بعمود company_id · FK 292 · CHECK 308 ────┐
```

### من يستدعي من · من يكتب · من يقرأ · من يملك الحقيقة

| العلاقة | الاتجاه المقيس | الدليل |
|---|---|---|
| الشاشات ← الخدمات | الشاشة تستدعي الخدمة؛ 1,985 ملفًا يستعمل `scopedQuery`، لكن 1,257 ملفًا ما زال يمرر SQL خامًّا | مسح شجرة (تقرير الصلاحيات §4ج) |
| الخدمات ← الأحداث | 40 ملفًا منتِجًا · 60 نقطة نشر · **صفر** إدراج مباشر في `ems_business_events` خارج `EventPublisher.php:604` | مسح `INSERT INTO ems_business_events` |
| الأحداث ← المالية | الكتابة مزدوجة ذرّية (جذر + إسقاط)؛ **القراءة العملية كلها من الإسقاط `fin_financial_events`** لا من الجذر | `EventDispatcher.php:99` |
| المالية ← دفتر القيد | `PostingService.php:228` الكاتب المعتمد + 3 كتّاب مباشرون (`journal_form_fin.php:205`، `fin_helpers.php:642`، `fx.php:338`) | تقرير المالية §1 |
| ملكية حقيقة الشاشة | الاسم: `nav_canonical` · المجموعة: `nav_route_group` · الترتيب: `nav_canonical.sort_no` · المالك: ثلاثة سجلات بلا تحكيم (§6) | تقرير التنقّل §2 |

---

## §2 مصدر الحقيقة لكل محرك

لكل محرك: أين يعيش، ماذا يقرأ فعلًا، الجداول الحية، المسار القديم إن وُجد، وما يمنع الالتفاف.

### CAP-01 · الهوية والجلسات
- **الملفات:** `includes/session_bootstrap.php` (مضمَّن في 289 ملفًا) · `includes/EmsDbSessionHandler.php` · `login.php`.
- **Source of Truth:** `users` (75 نشطًا) + `sessions` (مخزن جلسات القاعدة).
- **الحالة:** حي. الحارس: فحص `$_SESSION['user']` في رأس كل شاشة قبل أي شيء.

### CAP-02 · الصلاحيات (مساران للحقيقة — GAP-11)
- **المسار الحاكم فعليًّا:** `get_module_permissions()` (`includes/permissions_helper.php:298-384`): إن كان للمستخدم منحة نافذة في `gov_authority_grants` بقالب `gov_role_profiles.state='active'` → القرار **حصريًّا** من `gov_profile_items` (2,526 بند شاشة) **ويُبطل `role_permissions` كليًّا** — حتى المنع («لا شاشة خارج القالب»: `t_view=-1`). القياس: 76 منحة نافذة تغطي **76/78 مستخدمًا (97٪)**.
- **المسار الثاني (ما زال حيًّا في مواضع):** `check_permission()` (`permissions_helper.php:191`) و`unified_nav.php:90` يقرآن `role_permissions` (3,041) مباشرة متجاهلَين القالب ⇒ **القائمة والأزرار من مصدر، ودخول الشاشة من آخر**.
- **السوبر أدمن (`role='-1'`):** مُعفى في حراس الأفعال/التصدير/الحقول/النطاقات، **محجوب** في `get_module_permissions` (يهبط إلى `role_permissions` حيث **صفر صف له** ⇒ `can_view=false`) — فكل شاشة تحرسه بفحص `$is_super_admin` اليدوي قبل الفحص المركزي.
- **الاختبارات:** `tests/sec_guards_test.php` · `tests/write_guard_negative_test.php` وحزام SEC.

### CAP-03 · عزل المستأجر (ADR-02)
- **الملفات:** `app/Core/TenantDb.php` · `app/Core/TenantRegistry.php` · `.env: EMS_TENANT_GATE=enforce`.
- **ما يمنع الالتفاف:** 7 بوابات رفض في `scopedQuery` (`TenantDb.php:557-670`) كلها ترمي استثناء: إعلان فارغ، جدول غير مسجَّل، **ذكر `company_id` نصًّا مرفوض**، SELECT فقط، رمز نطاق واحد، لا UNION، جدول مستأجر بلا إعلان مرفوض، `enrich` بـLEFT JOIN حصرًا. التحقق **بنيوي** (مسح بعمق الأقواس بعد تجريد النصوص).
- **الفجوة:** العزل في القاعدة نفسها غير موجود (لا CHECK على company_id)؛ و1,257 ملفًا ما زال بSQL خام (عزل بانضباط المطوّر) — GAP-20 جزئيًّا.

### CAP-04 · حارس الأفعال (ADR-06)
- **الملفات:** `includes/action_guard.php` (سجل ~45 معالجًا في `:36-146`) · سجل `actions` بالقاعدة (92 فعلًا/61 كتابيًّا) · `.env: EMS_ACTION_GUARD=enforce`.
- **ما يمنع الالتفاف:** المعالج غير المسجَّل يُرفض 403 (`action_guard.php:216-220`). فوقه: أمن نقطة AJAX (ترويسة + جلسة + معدل 45/180 في الدقيقة، `config.php:435-464`) ثم CSRF.
- **عيب حي (GAP-14):** `action_guard.php:232` يستعمل `$verb` قبل تعريفه (يُعرَّف في `:246`) ⇒ كل رفضِ مساحةٍ يُسجَّل بفعل فارغ.

### CAP-05 · التنقّل والسايدبار — التفصيل في §6
### CAP-06 · محرك الاعتماد — التفصيل في §7 (٥ مصادر حقيقة)
### CAP-07 · ناقل الأحداث ENG-01 — التفصيل في §8
### CAP-08 · موزّع الأحداث K4 (القديم الحي)
- `app/Core/EventDispatcher.php` يقرأ **`fin_financial_events`** (`:99`) بمؤشرات `ems_event_consumers` (4 صفوف: finance وfinance_routing ملاحقان عند 17,985؛ **fx متوقف عند 6,613 منذ 2026-08-12** — GAP-07). هذا هو المسار الذي يؤدي العمل المالي الفعلي — لا الجذر المحايد.

### CAP-09 · مروحة الأثر (EffectFanout)
- `app/Services/EffectFanout.php` — تُستدعى **داخل معاملة المستدعي حصرًا**؛ العطالة بوجود صف في `fin_event_links` (10,519) بمفتاح `UNIQUE uq_link_parent_effect(company_id,parent_kind,parent_ref,effect_type)`. **98.6٪ من الروابط مصدرها timesheet** — المروحة عمليًّا أحادية المصدر.
- تنبيه سجلّي: `event_consumers` يسجّلها بصنف خاطئ `App\Core\EffectFanout` (غير موجود — الصحيح `App\Services\`) وقد عُطّلت (`active=0`) — GAP-09 جزئيًّا.

### CAP-10 · الطابور والمهام الخلفية
- **حي فعلًا لا جدول نائم:** Task Scheduler ينفّذ `cron_jobs.php` كل دقيقة (آخر تشغيل 04:24:01 نتيجة 0)؛ `ems_job_queue` = 6,253 (منها 6,236 done · 17 dead لpilot_monitor · صفر عالق)؛ 8 جدولات في `ems_job_schedule` كلها نشطة بآخر نجاح حديث (`event_retry */5` · `fin_posting */10` · `capacity_rollup */15` · `alert_dispatch */5` · `statement_build` يومي 01:00 · `settlement_recalc` يومي 03:00 · `depreciation_run` شهري · `pilot_monitor */30`).
- **مخاطر تشغيلية (GAP-17):** ثلاث مهام مجدولة بثلاثة إصدارات PHP مختلفة (8.0.30/8.2.30/8.5.0) على الكود نفسه؛ و`cron_proc_replenish.php` غير مجدول أصلًا رغم أن رأسه يقترح «كل ساعة».

### CAP-11 · العمود المالي (القيد المزدوج)
- **دفتر الحقيقة مزدوج الطبقة:** سجل الأحداث `fin_financial_events` (5,256 · 96.4٪ Posted) + دفتر قيد مزدوج حقيقي `fin_journal_entries` (6,713 كلها posted) / `fin_journal_lines` (13,426): مدين 37,694,775.13 = دائن 37,694,775.13 — **فرق 0.00**، ويحرسه `CHECK ck_je_balanced`.
- **الكاتب المعتمد:** `PostingService.php:228` (والعكس يقلب الطرفين لا يمحو، `:403,426`). كتّاب مباشرون ثلاثة: `journal_form_fin.php:205` (1,644 قيدًا يدويًّا غير مربوط بحدث)، `fin_helpers.php:642`، `fx.php:338`.
- **مصفوفة الترحيل الفعلية مصلَّبة في الكود** (`PostingService.php:44-51`، 7 مداخل) — و`fin_posting_matrix` (27 صفًّا) «نص وصفي لا قاعدة آلية» (`PostingService.php:21`).

### CAP-12 · بوابة الطلبات المالية D-05
- `FinRequests/` (11 ملفًا) · `fin_requests` = 22 طلبًا فقط · **1 حدث من 5,256 (0.019٪) وُلد عبر البوابة** — البوابة مبنية بالكامل ومتجاوَزة عمليًّا (الحجم الحقيقي يدخل من خطاف الدوام) — GAP-15. قيد بنيوي واحد نافذ: `chk_party_payment_needs_settlement`.

### CAP-13 · الإقفال المالي
- **الحقيقي في `fin_financial_periods`** (شاشة `Finance/periods_fin.php`): آلة 6 حالات، `soft_close/close/lock` تُطفئ `posting_allowed` و`PostingService::periodOpen()` (`:456-468`) يمنع الترحيل في فترة مقفلة؛ `reopen` بسبب إلزامي وختم مدقَّق. الواقع: 14 planned · 5 open · 1 soft_closed · 3 closed · **صفر locked**.
- **`scr_monthly_close` ليس آلية إقفال** — جدول شاشة CMP-03 بأعمدة varchar نصية وبياناته UAT (GAP-24).

### CAP-14 · الصرف والتسعير اليومي
- `includes/fx.php`: الأساس من `admin_companies.currency` → `fin_currencies.is_base` (الشركتان USD)؛ `base=ROUND(amount×rate,2)` **مفروضة بقيد** `ck_ffe_fx_pair` و`ck_je_fx_pair`. السعر النافذ «آخر سعر سريانه ≤ التاريخ» (`fx.php:174`) — أي استمرار حتى سعر أحدث، وليس «يومها فقط».
- **التسعير اليومي (قرار المالك 2026-08-12):** `Finance/daily_pricing_fin.php` + `PriceAdjustmentService`؛ «لا سعرُ يومٍ مرتين» بقيد `uq_price_index_reading(company,index,reading_date)`، ومرجع مستند إلزامي، و«من أنشأ لا يعتمد» 403. البيانات: 9 قراءات · 97 مراجعة سعر · 94 بند شرط.
- **فجوة:** `fin_fx_rates` فيه **4 صفوف فقط** (GAP-25) — مع ذلك 5,256/5,256 حدثًا مُسعَّر (صفر فجوة صرف محسوبة).

### CAP-15 · الرواتب والتسويات
- `payroll_runs` = 74 (100٪ Approved) · `payroll_lines` = 1,302 تغطي 73/74 دورة (دورة واحدة بلا سطور) · `payroll_run_blocks` = 0 (الفارغ الوحيد من جداول payroll_ السبعة).
- `settlements` = 469 (draft 217 · approved 251 · payment_requested 1 · **صفر مدفوع/مقفل**). `SettlementService` يولّد طلب دفع آليًّا عند الصافي الموجب (`:652`).
- **عيب كتم (GAP-16):** `SettlementService.php:630` يبتلع فشل إنشاء الذمة المدينة (error_log ويمضي) — لا يُرجع المعاملة ولا يمنع الانتقال إلى Approved.

### CAP-16 · التدقيق (Audit Trail)
- `includes/audit_trail.php` → `activity_logs` (يسجّل **الفرق فقط**، لا يرمي أبدًا، CLI-safe، والنيابة تُختم `acted_by/acted_for` بقيد `chk_act_attribution`).
- التغطية على مستويين: بوابة `TenantDb::auditWrite` (`TenantDb.php:171`) لكل عابر بالبوابة + نداءات صريحة في مواضع الخنق المالية. **الثغرة:** الكتّاب بـmysqli الخام (منهم `EventPublisher.php:373` نفسه) خارج التغطية الضمنية.

### CAP-17 · الحقول الحساسة (ثلاث آليات لا تعرف بعضها — GAP-10/12)
- **A العرض:** `ems_may_see_field()` (`includes/field_visibility.php`) — مغلق افتراضًا، السوبر استثناء معلَن، اطلاع مسجَّل في `sensitive_read_log`، والقيمة المحجوبة **لا تعبر الشبكة**. التبني: **6 شاشات فقط** في الشجرة كلها.
- **B التصدير:** `FieldGovernor::exportableColumns` ضد `scr_sensitive_fields` — الحجب **قبل SELECT** (`ExcelService.php:243`)، إن حُجب الكل → 403، والتصدير مسجَّل في `gov_export_log`. **لكن الشرط الحرفي `status='معتمد'`** ⇒ 19 صفًّا من 34 بحالة `active` **خارج الإنفاذ** (GAP-10).
- **C التحرير:** `gov_field_class` (630 حقلًا بأصناف DC-1..4) تُقرأ في مسار التحرير فقط (`FieldGovernor::classOf/assertClassified`) — **لا تحكم عرضًا ولا تصديرًا** (GAP-12). وصف ملوث فعّال في `gov_data_classes` (id=61 اسم شخص — GAP-09).

### CAP-18 · التصدير الموحَّد / CAP-19 · البحث العام / CAP-20 · API
- **Excel** (`/excel.php` + ExcelRegistry): جلسة → عزل مساحة (SCOPE-403) → `authorize` → حجب أعمدة → عزل شركة → سجل تصدير. فشل مغلق مضاعف (غياب طبقة الصلاحيات = 500 ممنوع).
- **البحث العام** (`main/global_search.php`): 11 كيانًا (لا 9)؛ **الفحص قبل الاستعلام** — ما لا يُملك لا يُستعلم عنه؛ عزل مساحة قبله. لا فحص حقول حساسة (النطاق ضيق بنيويًّا — حماية بالمصادفة). عيب: `gs_can_open` تعيد `true` للشاشة غير المسجَّلة (اتجاه فتح يناقض `_deny_all_permissions`).
- **API** (`api/`): Bearer بـsha256 ضد `api_tokens`، فحص صلاحية `api/bootstrap.php:466`، معفى من CSRF (سليم بالتوكن). **صفر حجب حقول حساسة وصفر عزل مساحة** (GAP-13). `Access-Control-Allow-Origin: *` مقبول حاليًا وخطِر لو أُضيف توثيق كوكي.

### CAP-21..29 · محركات النطاقات (موجزة — تفاصيلها في وثائقها)
| المحرك | SoT | الحالة المقيسة |
|---|---|---|
| Timesheet/الدوام | `timesheet` + `TimesheetEntryService` + خطاف `timesheet_event_hook.php` | **المصدر شبه الوحيد للمالية** (98.9٪ من الأحداث المالية) |
| العقود | `contracts` + آلة حالة 12 (H-02) + `ContractStateMachine` | حي؛ ينشر حقائق تغيّر الحالة |
| المشتريات/RFQ | `proc_*` (16 جدولًا) + `RFQService` | حي؛ rfq_award موقَّع بـAuthorityGuard (21 توقيعًا) |
| الصيانة | `mnt_*` (11) | ينشر لكن **2 واقعتان فقط** — التكاليف لا تمر بالأحداث عمليًّا |
| النقل | `trs_`/`transfer_*` (11) | **صفر واقعة حدث** — يكتب في جداوله مباشرة (مسار أحداث غير موصول) |
| المخاطر | `risk_*` (17) + RiskEvents (M-16) | حقائق محايدة بلا إسقاط مالي — **مقصود موثَّق** (RK-06) |
| السعة | `capacity_*` + صادر مستقل `capacity_outbox` | **مسار موازٍ ثالث** خارج الناقل الرئيس |
| التذاكر | `ticket_*` (17) + تصعيد خاص `ticket_escalation_rules` | حي بمحرك تصعيد مستقل |
| الحوكمة | `gov_*` (44 جدولًا) | سجلات حاكمة للقياس والملكية والقوالب — بعضها إنفاذ وبعضها توثيق (انظر §4) |

---

## §3 الفصل الإلزامي: Decision / Implementation / Evidence

| القدرة | Decision (القرار محسوم؟) | Implementation (الكود منفَّذ؟) | Evidence (مثبت على الحي؟) | الحكم |
|---|---|---|---|---|
| عزل المستأجر | ✔ ADR-02 | ✔ بوابة 7 فحوص enforce | ✔ حزام سلبي + رفض حي مقيس | **Closed** |
| حارس الأفعال | ✔ ADR-06 | ✔ enforce | ✔ 403 حي؛ لكن 113/465 فعلًا guard_verified | Closed مع دين توثيق |
| ناقل الأحداث ENG-01 | ✔ ADR-15 | ✔ ناشر واحد + طابور + DLQ | ✔ 21,284 واقعة، 99.88٪ تسليم | Closed **بنيةً** — لكن انظر السطر التالي |
| استهلاك الأحداث بالأعمال | ✔ (مبدأ «لا حدث بلا مستهلك») | ✘ 0 مستهلك أعمال من 58 نوعًا | ✘ | **Open — GAP-05** |
| سلالم الاعتماد الحاكمة | ✔ LAD-01 (13 سلّمًا) | ✔ جداول+منظر جسر | ✘ **صفر تقاطع مفاتيح؛ 14/14 رحلة ladder_wired=0** | **Open — GAP-01** |
| التفويض | ✔ GOV-AUTH-01 | ◐ جداول+قوادح بلا كود مستهلك | ✘ صفر صف، صفر منحة delegation | **Built-not-wired — GAP-03** |
| القيد المزدوج | ✔ | ✔ PostingService+CHECK | ✔ توازن 0.00 على 13,426 سطرًا | **Closed** |
| بوابة D-05 | ✔ | ✔ 11 ملفًا | ✘ 1/5,256 حدثًا عبرها | **Open — GAP-15** |
| الإقفال الشهري | ✔ | ✔ فترات+قفل ترحيل | ◐ يعمل؛ صفر فترة `locked` حتى الآن | Implemented-not-fully-exercised |
| الحقول الحساسة | ✔ SEN-001.. | ◐ 3 آليات منفصلة | ◐ 15/34 داخل الإنفاذ فقط | **Open — GAP-10/12** |
| الشاشات الذهبية | ✔ (10 شاشات مختارة) | ✔ سجل `gov_golden_approvals` | ✘ **10/10 حالتها pending** | **Open — GAP-23** |
| التسعير اليومي | ✔ قرار مالك 2026-08-12 | ✔ شاشة+خدمة+قيود | ✔ 9 قراءات + قيود فريدة حية | Closed |

---

## §4 خريطة البيانات

**الأرقام الحاكمة:** 603 جداول أساس + 25 منظرًا · 478 جدولًا بعمود `company_id` (والمنظر لا «يُعزل» — عزله عزل جدوله) · FK 292 · CHECK 308 · UNIQUE 384 · **Triggers صفر مرئي للمستخدم `ems_app` — لكن قوادح موجودة فعلًا** (منها `trg_approval_rules_retired`، `trg_delivery_backoff`، `trg_deleg_no_relay`، `trg_grant_issuer`) والامتياز يحجبها عن `information_schema.TRIGGERS` (گوتشا M-00 الموثقة: جسّ وظيفيًّا).

### المجموعات بملكية الكتابة والقراءة

| المجموعة (بادئة) | جداول | من يكتب | من يقرأ | العزل | ملاحظات القيود |
|---|---|---|---|---|---|
| مالية `fin_` | 89 (88 مأهولًا · فارغ وحيد: `fin_maint_provisions`) | EventPublisher/PostingService/خدمات المالية + 6 كتّاب مباشرون لـ`fin_dues` | شاشات المالية + موزّع K4 | company_id | `ck_je_balanced` · `ck_ffe_fx_pair` · `uq_ffe_idempotency` — **~35 جدولًا بحجم 20 صفًّا = بذور UAT لا حركة** |
| حوكمة `gov_` | 44 | أدوات البذر/الترحيل + خدمات الحوكمة | الحراس + شاشات gov + هذه الحزمة | company_id=0 عام غالبًا | `chk_no_single_hand` · `chk_temp_has_end` · قوادح التفويض |
| شاشات مرحّلة `scr_` | 42 (كلها ~20 صفًّا) | شاشات CMP-03 | شاشاتها | company_id | **بيانات UAT — «مأهول» ≠ «مستعمل»** |
| عقود `contract_` | 20 | خدمات Contract | مبيعات/مالية/تشغيل | company_id | ENUM 12 حالة · قيود التسعير الفريدة |
| مخاطر `risk_` (17) · تذاكر `ticket_` (17) · مشتريات `proc_` (16) · موردون `supplier_` (13) · صيانة `mnt_` (11) · نقل `transfer_` (11) · قوى عاملة `worker_`/`employee_`/`payroll_` (23) | — | خدمات نطاقاتها | نطاقاتها + المالية عبر الأحداث/المباشر | company_id | — |
| تنقّل `nav_`/link_groups/modules | 16+ | أدوات البذر + شاشة admin | unified_nav وقت الطلب | بالدور | `chk_nav_door` · `chk_nav_route_not_relative` |
| أحداث `ems_business_events`/deliveries/consumers | 5 + منظران | EventPublisher/Worker حصرًا | governance_watch + شاشات المراقبة | company_id | `chk_consumers>0` · `json_valid(payload)` · بصمة تسليم SHA2 في القاعدة |
| اعتماد قديم `approval_*` (7) | | الشاشات الموروثة | شاشاتها | مصنَّفة T_RESTRICTED | `trg_approval_rules_retired` يرفض الكتابة في القواعد |
| صلاحيات users/roles/role_permissions/gov_profile_items/permission_ (8) | | admin/permissions + بذر القوالب | كل حارس | — | — |
| Audit: activity_logs · sensitive_read_log · gov_export_log · security_log · login_attempts | | البوابة والحراس (append-only عمليًّا) | شاشات الأمن | company_id | مستثناة من تدقيق ذاتها (`$AUDIT_SKIP`) |
| **متقاعد/قديم** | `approval_workflow_rules` (مقفل بقادح) · `ems_event_dead_letter` (0) · `nav_items.door`+`sort_order` (يُقرآن ولا يقرّران) · `link_groups.stage_no` · `modules`+`dynamic_nav` (للسوبر فقط) · `scr_monthly_close` | — | — | — | GAP-24 وأخواتها |

### العلاقات المفتاحية (عملية لا ERD-كامل)
- `users.role_id→roles` · `users.employee_id UNIQUE→employees` · `users.project_id` (نطاق الدورين 5/6).
- `ems_business_events ←root_event_id— fin_financial_events —event_id→ fin_journal_entries` · `fin_event_links(parent_kind,parent_ref)→` مصادر متعددة الأشكال (timesheet/unit_record/event/request).
- `gov_authority_grants.profile_id→gov_role_profiles→gov_profile_items(item_ref=route)`.
- `nav_items(role_id,route)` ↔ `nav_canonical(route)` ↔ `nav_route_group(route)` ↔ `gov_space_appearances(route,space)`.
- الدفتران التوثيقيان للشاشات: `gov_migration_ledger(663)` ↔ `gov_screen_cycle(663)` **بتناظر معرّفات 1:1 مثبت 663/663**.

---

## §5 معمارية الصلاحيات والعزل (السلسلة الكاملة)

**User → Role → Permission → Department Scope → Record Scope → Field Scope → Action** — أين يقع كل فحص:

| الطبقة | أين | متى | الدليل |
|---|---|---|---|
| الجلسة | رأس كل شاشة | قبل كل شيء | `Contracts/contracts.php:4-7` |
| بوابة الشركة | الشاشة | قبل الصلاحية | `contracts.php:24-27` (GOV-SCOPE-403) |
| صلاحية العرض | `check_page_permissions` أو `enforce_current_page_view_permission` — **نداء الشاشة نفسها، لا ضمني** | قبل التصيير | `permissions_helper.php:554/764` — شاشة تنسى النداء لا يحرسها إلا فحصها اليدوي |
| صلاحية الكتابة | `ems_enforce_write_permission` | **قبل** الكتابة، على مستوى الطلب POST | `permissions_helper.php:919-1026` |
| فعل AJAX | سلسلة config الثلاثية | قبل وصول المعالج | `config.php:466-481` |
| نطاق السجل (شركة) | `TenantDb.scopedQuery` | حقن `company_id=?` بنيويًّا | `TenantDb.php:652` |
| نطاق السجل (إدارة/مشروع) | `fin_project_scope` (5,6 — fail-closed: أي فشل=−1=صفر صف) / `fin_party_scope` (**fail-open للدور الجديد** — GAP-22) | داخل الخدمة | `fin_helpers.php:100-140` |
| نطاق الحقل | 3 آليات (CAP-17) | عرض/تصدير/تحرير | — |
| الفعل | سجل `actions` + `guard_policies` (`absolute` يمنع مهما بلغت الصلاحيات) | قبل التنفيذ | `permissions_helper.php:962-1009` |
| القاعدة | لا CHECK عزل؛ قيود نوعية (توازن القيد، عطالة، فرادة السعر اليومي، `chk_no_single_hand`) | عند الإدراج | — |

**Direct URL:** يحرسه تتابع الشاشة (جلسة→شركة→can_view) لا السايدبار؛ `nav_items.active=0` (95 صفًّا) **إخفاء لا منع**. **Export/Search/API:** كلٌّ يعيد الفحص خادميًّا ولا يثق بالشاشة (الجدول المقارن الكامل في تقرير الأسطح الأربعة أعلاه CAP-18..20). **الحقول الحساسة:** القيمة المحجوبة لا تُرسل ثم تُخفى — لا تُقرأ من القاعدة أصلًا في مسار التصدير.

---

## §6 معمارية السايدبار والتنقّل

**السلسلة الفعلية** (تصحيح جوهري: المصيِّر هو `unified_nav.php` لا `dynamic_nav.php`):

```
$_SESSION[role] → unifiedNavEnabled(.env EMS_NAV_UNIFIED_ROLES=1..33 ⇒ كل الأدوار)
  → getUnifiedNavItems: SELECT nav_items WHERE role_id=? AND active=1
       AND (permission_code IS NULL OR EXISTS role_permissions.can_view=1)   ← بوابة ①
  → SupplierPortalGuard ② → OwnershipDomainGuard (FIN, fail-closed) ③
  → space_scope (gov_space_appearances.cls='FORBIDDEN') ④ + مخنق printNavLinkItem
  → renderUnifiedNavigationV2 → nav_group_taxonomy(12) موجود؟ → printEmsTenGroupNav
       التبويب: nav_route_group(472) ← استدلال nav_groups.php ← سقوط DAILY
       الاسم:   ANCHOR ← nav_canonical(APPROVED 280/372) ← cur_label ← label_ar
       الترتيب: taxonomy.sort_no للرؤوس · nav_canonical.sort_no للروابط
  → الأيقونة من عمود nav_items.icon مباشرة (nav_icon_map.php مولِّد بذرٍ لا محلِّل طلب)
  → العدّادات: counter_source (35 صفًّا/7 مفاتيح) عبر finreq_badges + قاموس مركزي
```

- **مالك الاسم:** `nav_canonical` (لا `nav_items.label_ar`) · **مالك المجموعة:** `nav_route_group` (لا `link_groups`) · **مالك الترتيب:** `nav_canonical.sort_no` (لا `sort_order`).
- **سقف الـ12 مجموعة:** بنيوي (`nav_group_taxonomy` = 12 صفًّا بالضبط) ومقيس (أقصى دور = 12 رأسًا).
- **صفر روابط مكسورة من 408 مسارات نشطة** — ويحرسه `chk_nav_route_not_relative`.
- **Legacy مسمّى صراحة:** `dynamic_nav.php`+`modules` (السوبر فقط في السايدبار؛ **حي في لوحة التحكم** — فاللوحة والسايدبار يبوّبان من سجلَّين مختلفين بمنطقَي ملكية مختلفَين، GAP-19)؛ `link_groups` يُقرأ ولا يقرّر؛ `printStageNav` وأخواتها شيفرة حية لا تُنفَّذ؛ روابط insidebar اليدوية لا تعمل إلا للسوبر — **وفيها تعليق HTML غير مغلق (`insidebar.php:352`) يبتلع رابط اعتماد الوحدات**؛ `topbar.php` تسعة مسارات مصلَّبة **خارج كل السجلات وخارج عزل المساحة**.
- **ثقب `active=0`:** `unified_nav.php:869-893` تصطنع روابط من `nav_canonical_current` لدورين صفهما معطَّل (17 و25 — مقرّ به نصًّا).
- **ملكية `?view=`:** سجل مركزي مصلَّب (6 ملفات/8 رموز في `nav_views.php:43-87`) — الرمز غير المعلن يُرد 302 إلى الرابط العاري (`nav_view.php:61-68`) عبر مخنق `page_header.php:149`؛ والشاشة تعلن ملكيتها الذاتية بـ`ems_nav_view_claim()`.

---

## §7 محرك دورة العمل والاعتماد

**الجواب على «مصدر واحد أم أكثر؟»: خمسة مصادر حقيقة متزامنة** — والحي فعليًّا هو الموروث معطوبًا:

| # | المصدر | الحجم | الحالة |
|---|---|---|---|
| 1 | `approval_requests/steps` (الموروث) | 48 طلبًا/50 خطوة (44 pending) | **الحي** — لكن بالاحتياط |
| 2 | `approval_links` (WFM/الورقة 09) | 70 (68 approved، آخرها 2026-08-19) | حي ومستعمل (`RequestService.php:155`) |
| 3 | `approval_signatures` (AuthorityGuard) | 154 (entitlement 132 · rfq_award 21) | حي لنوعين، Insert-only |
| 4 | `approval_chains` (PolicyResolver — سلسلة وحدات) | 22 على 8 سياسات | حي ومنفصل تمامًا (`PolicyResolver.php:54`) |
| 5 | `gov_approval_decisions` | 20 — **event_id NULL في 20/20** | **مبذورة لا مُنتَجة** |

**المسار الحقيقي الواحد (timesheet — الأكثر جريانًا):**
```
Timesheet/aprovment.php:85 approval_create_request('timesheet',…)
→ approval_workflow.php:144 يقرأ المنظر v_approval_rules_effective
   (الجسر إلى gov_ladders — migration 2027_07_12)
→ ⚠ المنظر مفهرس بـ slug السلّم (unit_daily_approve…) والشاشات ترسل
   entity_type تطبيقيًّا (timesheet/contract/driver/equipment) ⇒ تقاطع = 0
→ :189-217 الاحتياط: سلّم من خطوة واحدة بدور السوبر (EMS_APPROVAL_RULES=monitor)
   — 27 سقطة fallback_default مسجَّلة في guard_denials خلال يومين
→ :616 توليد الخطوات (43/50 خطوة حية دورها -1 = مولَّدة من الاحتياط)
→ :629 اعتماد تلقائي إن طابق دور المنشئ → :514 التنفيذ → UPDATE timesheet
→ لا نشر حدثًا (صفر EventPublisher في approval_workflow.php) ولا كتابة في
   gov_approval_decisions ولا approval_signatures
```
- **ما يُفحص فعلًا:** مطابقة الدور (`FIND_IN_SET`) + «لا يد تمشي خطوتين» (INJ-0219، `:708-720`) + حارس اعتماد الذات في `approval_api.php`. **ما لا يُفحص:** `gov_authority_grants/limits` لا تُستشار؛ السقف `cap_amount` يُقرأ في المنظر و**يُسقَط** (`:156-159`)؛ 6 سلالم `cap_state=unresolved` والوثيقة تقول «السقف غير المحسوم يوقف السلّم» ولا كود يفرض الإيقاف.
- **الرحلات:** `gov_journey_ladders` 14/14 صفًّا `ladder_wired=0` بنص gap_note موحَّد: «الشاشة تقود الخطوة ولا تقرأ سلّمها». الشاشات القائدة كلها موجودة وحية في التنقّل.
- **التصعيد:** `gov_ladders.escalate_after_hours` (24/48/72) **بلا معالج إطلاقًا**؛ التصعيد الحي الوحيد: `fin_requests.escalation_level` (`cron_requests.php:79-101`) والتذاكر (`ticket_escalation_rules`). و44 خطوة pending (أقدمها 2024) بلا آلية نبش.
- **القديم مقفل للقراءة مفتوح للكتابة الميتة:** `trg_approval_rules_retired` يرفض الإدراج في `approval_workflow_rules`، و4 ملفات إنتاج ما زالت تحاول `INSERT IGNORE` فيها فتُبتلع بصمت (`movement/save_equipment_drivers.php:76`، `Oprators/oprators.php:206`، `move_oprators.php:238`، `delete_equipment_driver.php:62`).
- **الاختبار الحارس `tests/ladder_engine_live_test.php` يثبت البنية لا الجريان** — يشتق حالته من المنظر نفسه فالمطابقة مضمونة بالإنشاء؛ ولا اختبار في `tests/` يمس مسار الاحتياط وهو المسار الفعلي 100٪.

⇒ **GAP-01 (أعلى خطورة معمارية):** إصلاحه مفتاح واحد من جهتين — إما بذر `gov_ladders.slug` بأنواع الكيانات التطبيقية أو تمرير slug السلّم من الشاشات — ثم فرض السقف و«لا يد تمشي خطوتين» من السلّم.

---

## §8 معمارية الأحداث (Event Architecture)

- **الأنواع:** 58 مفتاحًا متمايزًا (`event_key` بصيغة `domain.entity.action`؛ **لا عمود `event_type` في الجذر** — التصنيف بـ`category`(7)+`source_module`(16)). 5 مفاتيح تُنشر من أكثر من وحدة (`source_module` ليس دالة في المفتاح).
- **المنتجون:** 40 ملفًا/60 نقطة نشر عبر `EventPublisher` حصرًا (`publishFact` حقيقة محايدة / `publish` كتابة مزدوجة ذرّية) — **صفر إدراج مباشر** في كود الإنتاج؛ فحارس الفترة المقفلة يغطي كل منبع.
- **التسليم:** Transactional Outbox (فتح صفوف التسليم داخل معاملة المصدر) + عامل كرون كل 5 دقائق (`JobHandlers::eventRetry` → `EventDeliveryWorker`): التقاط ذرّي بشرط WHERE، تباعد 1·4·16·64·256ث **بقادح قاعدة** `trg_delivery_backoff`، DLQ حالةً لا حذفًا (86 صفًّا)، قرار بشري إلزامي السبب (`decideDlq`)، تحرير العالق (STALE_CLAIM)، ورفض النجاح الصامت (`NO_RESULT_REF` + `chk_result`).
- **العطالة ثلاث طبقات:** `uq_ebe_idempotency` (جذر) · `uq_ffe_idempotency` (إسقاط، مع شفاء ذاتي يربط الإسقاطات اليتيمة — بقي 3/5,256) · بصمة SHA2 للتسليم محسوبة في القاعدة. فوقها `fin_event_links` للمروحة و`ems_processed_events` احتياط.
- **التدقيق:** `created_by` 100٪ · `payload` 100٪ صالح JSON · `idempotency_key` 100٪ · `correlation_id` ناقص في 10 · **`source_ref` فارغ في 13,312/21,284 (62.5٪)** — ونوعه `varchar(60)` لا TEXT (سقف بتر محتمل).
- **الرقمان الحاكمان:** **58 نوعًا منتَجًا / 0 نوعًا له مستهلك أعمال حقيقي** — المستهلك النشط الوحيد `governance_watch` (3 قواعد مراقبة تُنذر في `fin_notifications`؛ 21,258 تسليمًا ناجحًا أغلبها فحص بلا أثر). المستهلكون التسعة الآخرون معطَّلون أو مشتركون على 10 مفاتيح **لا تُنتج أصلًا** (اشتراكات كُتبت لمعمارية لم يُنفَّذ منتجوها).
- **الفرق بين «الناقل موجود تقنيًّا» و«الإدارات متكاملة بالأحداث»:** البنية التحتية متينة بمستوى نادر (99.88٪ تسليم، صفر واقعة بلا صف تسليم) — **لكن لا أحد يركبها**: المستهلكون الماليون الأربعة الحقيقيون (finance/finance_routing/replay/fx) يقرؤون `fin_financial_events` عبر `EventDispatcher` القديم؛ ADR-15 منفَّذ على الكتابة لا القراءة.
- **أعطال حية:** fx متوقف منذ 2026-08-12 متأخرًا 11,372 صفًّا بلا إنذار توقف يغطيه (GAP-07) · 60 صف تسليم يتيم `NO_EVENT` ينقض وعد الذرّية (GAP-08) · 20 صف تسليم ملوث بنصوص UAT عربية في `consumer_key` ميتة صامتة (GAP-09) · FQCN خاطئ في السجل (GAP-09).

---

## §9 خريطة أثر التغيير (Architecture Change Impact Map)

| لو أردنا تغيير | أين نعدّل (بالترتيب) | من يتأثر | الاختبارات اللازمة | الخطر |
|---|---|---|---|---|
| **ظهور شاشة/حقل بين إدارتين** | ① ملكية الشاشة: `gov_ownership_rulings` (الحكم الأعلى) / `gov_space_appearances.cls` ② التنقّل: `nav_items` (الدور المستهدف) + `nav_route_group` + `nav_canonical` ③ الصلاحية: **قوالب `gov_profile_items` أولًا** (تحكم 97٪) ثم `role_permissions` (القائمة والأزرار) ④ إسقاط الحقل: `gov_field_class` + `scr_sensitive_fields` (بحالة «معتمد» حصرًا) ⑤ التصدير: ExcelRegistry (يتبع ④ آليًّا) ⑥ البحث: يتبع can_open ⑦ API: **لا حجب حقول — فجوة يجب سدّها قبل الاعتماد عليه** | الدوران المعنيان + كل مساحة يظهر فيها المسار (`spaces_count`) | `tests/nav_view_ownership_test.php` · حزام space_scope السلبي · اختبار can_view للدورين · فحص التصدير بعمود محجوب | متوسط — **الفخ:** تعديل `role_permissions` وحده لا يغيّر دخول محكومي القوالب |
| **«مدير التشغيل لا يرى سعر الوحدة»** (مثال إلزامي) | منع الحقل: صف في `scr_sensitive_fields` بحالة **«معتمد»** حرفيًّا (وإلا خارج الإنفاذ — GAP-10) + `from_visible_to` بلا دوره · منع الشاشة عرضًا: `ems_may_see_field` في الشاشة (إن لم تكن من الست المتبنية — أضف النداء) · منع التصدير: يتبع آليًّا (`ExcelService.php:243` يحجب قبل SELECT) · منع البحث: النطاق الحالي لا يخرج أسعارًا (بنيويًّا) · **API: غير محمي — لا تفتح كيان الأسعار فيه** | شاشات الوحدات/العقود/التقارير التي تعرض السعر (ابحث بـFIELD_REGISTRY عن التسمية) | تصدير الكيان بحساب دور 1 والتحقق من غياب العمود + `sensitive_read_log` | متوسط |
| **تغيير سلّم اعتماد** | `gov_ladders`/`gov_ladder_steps`/`gov_ladder_actor_roles` — **لكن انتبه: السلالم غير موصولة (GAP-01)؛ التغيير الفعّال اليوم في كود الشاشة القائدة نفسها** أو في `approval_chains` لسلسلة الوحدات | الشاشات القائدة الثماني في `gov_journey_ladders.driver_route` | `ladder_engine_live_test` (بنية) + اختبار جريان حقيقي بكيان تطبيقي (غير موجود — يجب كتابته) | **عالٍ** — تعديل السلّم وحده لن يغيّر السلوك |
| **إضافة حالة لآلة عقد** | ENUM في `contracts.status` (هجرة بعميل utf8mb4) + قائمة السماح في `ContractStateMachine` + `pause_state_before` | مبيعات/مالية/تشغيل + أي مؤشر يستعلم الحالة | اختبار آلة الحالة + فحص المؤشرات ضد ENUM | متوسط |
| **تعديل عقد (بنود/تسعير)** | `contract_*` + `ContractStateMachine`/خدمات Contract + الإسقاط الحتمي في `contract_amendments` (مرّر company_id للسوبر) | المالية (أثر) + الالتزامات + التقارير | حزام Contract + إعادة بناء الإسقاط | متوسط |
| **إضافة Event جديد** | ① سجّل مشتركًا في `event_consumers` **قبل** النشر (وإلا `BUS_NO_CONSUMER` يرمي) ② المفتاح بصيغة `domain.entity.action` وcategory/source من القوائم (`EventPublisher.php:67-77`) ③ الناشر عبر `publishFact/publish` حصرًا ④ إن أردت أثرًا ماليًّا: مدخل في مصفوفة `PostingService.php:44-51` (مصلَّبة) | الناقل + المالية إن كان بأثر | بذر واقعة والتحقق من التسليم والقيد | منخفض بنية/عالٍ إن ظُن أن مستهلكًا سيلتقطه تلقائيًّا (لن يفعل — GAP-05) |
| **إضافة شاشة** | ① الملف بنمط الحراسة القياسي (جلسة→config→شركة→can_view) أو ببيان U13 ② `modules` + `role_permissions` أو بند قالب `gov_profile_items` ③ `nav_items` للأدوار + `nav_canonical`+`nav_route_group` ④ `gov_space_appearances` (وإلا «الغياب ليس منعًا» يفتحها لكل مساحة) ⑤ تسجيل أفعالها في `actions` إن كان لها POST/AJAX ⑥ الدفتران التوثيقيان + هذا السجل | الدور المستهدف + العزل | فحص 403 لغير المخول + رابط السايدبار + الحزام السلبي | متوسط — **الفخ:** نسيان ④ يجعلها مرئية عابرة للمساحات |
| **تعديل حقل حساس** | كما في المثال الإلزامي أعلاه + إن كان بصنف تحريري: `gov_field_class.dc_code` (يحكم التحرير) | الشاشات الست المتبنية + التصدير | تصدير + سجل الاطلاع | متوسط |
| **تغيير تجميع/ترتيب السايدبار** | `nav_group_taxonomy` (الرؤوس) · `nav_route_group` (العضوية) · `nav_canonical.sort_no` (الترتيب) — **لا تعدّل `link_groups` أو `sort_order` فلن يتغير شيء** | كل الأدوار | مقارنة المصيَّر قبل/بعد (عدّة UXUI) | منخفض |

---

## §10 سجل الديون والفجوات (مختصر — التفصيل والأدلة في INJ-CURRENT-STATUS §4)

| Gap_ID | الاسم | المجال | يمنع الإنتاج؟ | الأولوية |
|---|---|---|---|---|
| GAP-01 | سلالم الاعتماد غير موصولة (تقاطع مفاتيح=0؛ الاحتياط سوبر بخطوة واحدة) | اعتماد | **نعم** | P0 |
| GAP-02 | خمسة مصادر حقيقة للاعتماد | اعتماد | نعم (حوكمة) | P0 |
| GAP-03 | التفويض/السلطة البديلة مبنية غير موصولة | حوكمة | لا | P2 |
| GAP-04 | لا معالج تصعيد للسلالم و44 خطوة pending بلا نبش | اعتماد | جزئيًّا | P1 |
| GAP-05 | 58 نوع حدث/0 مستهلك أعمال | أحداث | جزئيًّا (تكامل الإدارات وهمي عبر الناقل) | P1 |
| GAP-06 | القراءة كلها من الإسقاط لا الجذر (ADR-15 نصف منفَّذ) | أحداث | لا | P2 |
| GAP-07 | مستهلك fx متوقف 9 أيام/متأخر 11,372 بلا إنذار | مالية | **نعم** | P0 |
| GAP-08 | 60 تسليمًا يتيمًا NO_EVENT ينقض الذرّية | أحداث | يلزم تحقيق | P1 |
| GAP-09 | تلوث UAT حي (deliveries.consumer_key عربي · gov_data_classes#61 · FQCN خاطئ) | جودة بيانات | نعم (نظافة إطلاق) | P1 |
| GAP-10 | 19/34 حقلًا حساسًا خارج الإنفاذ (status='active') | أمن حقول | **نعم** | P0 |
| GAP-11 | مساران لحقيقة الصلاحية (قوالب مقابل role_permissions) | صلاحيات | جزئيًّا | P1 |
| GAP-12 | gov_field_class (630) لا يحكم قراءة/تصدير | أمن حقول | لا | P2 |
| GAP-13 | API بلا حجب حقول ولا عزل مساحة | أمن | نعم إن فُتح API خارجيًّا | P1 |
| GAP-14 | `$verb` قبل تعريفه في action_guard.php:232 | كود | لا | P2 |
| GAP-15 | بوابة D-05 متجاوَزة (1/5,256) | مالية | لا (قرار معماري مطلوب) | P2 |
| GAP-16 | ابتلاع فشل الذمة في SettlementService:630 | مالية | نعم | P1 |
| GAP-17 | 3 إصدارات PHP بالمجدول + proc_replenish غير مجدول | تشغيل | نعم (اتساق) | P1 |
| GAP-18 | 35 هجرة مطبقة خارج دفتر schema_migrations | قاعدة | نعم (قابلية إعادة البناء عن بعد) | P1 |
| GAP-19 | ازدواج تبويب اللوحة/السايدبار + ثقب active=0 + تعليق insidebar غير مغلق | تنقّل | لا | P2 |
| GAP-20 | 63 شاشة قرص خارج كل السجلات · 126 تعارض ملكية · شاشات UNKNOWN المالك | سجل الشاشات | نعم (حوكمة) | P1 |
| GAP-21 | 336/582 جدول شاشة بلا أسماء تقنية موثقة (NEEDS_REVIEW) | توثيق | لا | P3 |
| GAP-22 | fin_party_scope يفتح افتراضيًّا للدور الجديد | صلاحيات | جزئيًّا | P2 |
| GAP-23 | الشاشات الذهبية 10/10 pending بلا اعتماد | UX/حوكمة | نعم (بند اعتماد) | P1 |
| GAP-24 | ازدواج الإقفال (scr_monthly_close الوهمي مقابل fin_financial_periods) | مالية | لا | P3 |
| GAP-25 | fin_fx_rates أربعة أسعار فقط | مالية | نعم لتعدد العملات فعليًّا | P1 |
| GAP-26 | تعديل CSS غير ملتزم ولا مسجل في gov_component_versions (بصمة الشجرة ≠ ux-1.4.0) | إصدار واجهة | لا | P2 |

---
*ولِّدت هذه الوثيقة قياسًا حيًّا على اللقطة `BL-20260821-f0bc3e4e`. أي قراءة لها بعد هجرة أو التزام جديد تستلزم إعادة قياس أو وسم الجزء المتغير `STALE`.*
