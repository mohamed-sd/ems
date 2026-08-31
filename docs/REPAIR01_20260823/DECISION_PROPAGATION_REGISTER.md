# DECISION_PROPAGATION_REGISTER — نفاذُ قراراتِ المالكِ قرارًا قرارًا

> **Baseline_ID:** `BL-20260831-07a21929` · أمرُ GOV_EXEC §8 — «لا يُقبل صحيحٌ في Registry غيرُ نافذٍ في Runtime».
> المولِّد: `tools/gov_exec_decision_propagation.php` والمخزنُ الحاكمُ `gov_decision_propagation` — **كلُّ حكمٍ بمجسٍّ نُفِّذ لا صُدِّق**.

## اللوحة

| الحكم | العدد | القراءة |
|---|---|---|
| `RUNTIME_PRESENT` | **67** | أثرُ القرارِ المسمّى (جدولٌ/محرّكٌ) حيٌّ في النظامِ الجاري |
| `RUNTIME_VERIFIED` | **30** | المجسُّ هو الثابتُ نفسُه (استعلامُ إثباتٍ أو نفيٍ قيس الآن) |
| `TARGET_PROPAGATED_BUILD_PENDING` | **10** | مُسقَطٌ في دفترِ الأهدافِ الحاكمِ والبناءُ بترتيبِ السجلِّ — طبقةُ Runtime تلحق ببنائِها |
| `BLOCKED_OWNER_VALUES` | **7** | آليّتُه نافذةٌ وقيمُه بانتظارِ المالك — بوّابتُه مسمّاة |

**`APPROVED_DECISION_WITH_UNPROPAGATED_IMPACT` = 0** — البسط: معتمدٌ بلا حكمِ نفاذٍ مسجَّل · المقام: 114 معتمدًا (الأحدَ عشرَ الباقون `NEEDS_OWNER_DECISION` خارجَ المقامِ بنصِّ حالتِهم).

## السجلُّ قرارًا قرارًا

| القرار | الحكم | المجسّ | الأساس |
|---|---|---|---|
| `DEC-ACC-01` | RUNTIME_PRESENT | TABLE_PROBE: fin_monthly_close (0 صفًّا) | تقويمُ الإقفالِ كياناتٌ حيّة (شهري/نهائي/تعاقدي ثلاثتُها جداول) |
| `DEC-ACC-02` | RUNTIME_PRESENT | TABLE_PROBE: fin_fx_rates (4 صفًّا) | محرّكُ الصرفِ بجدولَي الأسعارِ والفروق — base=amount×rate |
| `DEC-ACC-03` | RUNTIME_PRESENT | TABLE_PROBE: fin_cost_records (23 صفًّا) | سجلّاتُ التكلفةِ حيّةٌ — وتعميقُ متوسّطِ التكلفةِ ببندِ حملةِ الماليّة |
| `DEC-ACC-04` | RUNTIME_PRESENT | TABLE_PROBE: fin_depreciation (20 صفًّا) | الإهلاكُ عند الماليّةِ وساعاتُ الاستخدامِ تُستورد قراءةً |
| `DEC-CEO-01` | RUNTIME_PRESENT | TABLE_PROBE: exec_decisions (30 صفًّا) | سجلُّ القراراتِ التنفيذيّةِ بنافذةِ توثيقِه |
| `DEC-CEO-02` | RUNTIME_PRESENT | TABLE_PROBE: risk_acceptances (20 صفًّا) | السلطةُ المحجوزةُ مسجَّلةٌ (قبولُ المخاطرِ بحدودِه) |
| `DEC-CEO-03` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 34 | الأسطحُ القياديّةُ إسقاطاتٌ مصنَّفةٌ لا نسخ |
| `DEC-CEO-04` | RUNTIME_PRESENT | TABLE_PROBE: gov_delegations (0 صفًّا) | محرّكُ الإنابةِ حيٌّ — وربطُ دورِ النوّابِ بقرارِ تنظيمٍ (كشفُ EX-DVP القائم) |
| `DEC-CEO-05` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 42 | الأسطحُ القياديّةُ مسجَّلةٌ رسميًّا في سجلِّ الشاشات |
| `DEC-ENT-01` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 317 | مصدرُ الحقيقةِ مسجَّلٌ سطحًا سطحًا وقيدُ sot_witness يحرسه |
| `DEC-ENT-02` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 18 | التنظيمُ من مساحاتِ الإداراتِ لا من موجاتِ الإصلاح |
| `DEC-ENT-03` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 40 | كلُّ 1:N في Child Register — جداولُ البنودِ حيّة |
| `DEC-ENT-04` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 2056 | المشتقُّ صنفٌ محكومٌ صفرَ إدخالٍ (المحظورُ DERIVED+Editable بنصِّ الورقة) |
| `DEC-ENT-05` | RUNTIME_VERIFIED | NEGATIVE_PROBE: قيس = 0 | كلُّ حدثٍ يستلزم أثرًا بعقدِه — EFFECT_MISSING=0 نمطًا |
| `DEC-ENT-06` | RUNTIME_PRESENT | TABLE_PROBE: gov_ladders (17 صفًّا) | محرّكُ الاعتمادِ الواحدُ حيٌّ — وقيمُه عند بوّابةِ OA-06 |
| `DEC-ENT-07` | RUNTIME_PRESENT | ENGINE_FILE: includes/post_contract.php | القاعدةُ الحرجةُ في الخادمِ (عقدُ POST بفعلٍ مسجَّلٍ وصلاحيةٍ وIdempotency) |
| `DEC-ENT-08` | RUNTIME_PRESENT | ENGINE_FILE: app/Core/TenantDb.php | حصانةُ immutable_key في البوّابةِ — التصحيحُ حدثًا/نسخةً لا دهسًا |
| `DEC-ENT-09` | RUNTIME_PRESENT | ENGINE_FILE: includes/security.php | سجلُّ الرفضِ الموحَّدُ حيّ — log_security_event باسطحِه الاربعة |
| `DEC-ENT-10` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 21349 | حالاتُ التسليمِ الكاملةُ مع نبضِ cron_events المعلَن |
| `DEC-FAIL-01` | TARGET_PROPAGATED_BUILD_PENDING | TARGET_RULING: دفترُ المتطلّباتِ الحاكم | نموذجُ أبعادِ العطلِ الثمانيةِ مُسقَطٌ في متطلّباتِ W07/W14 — والبناءُ بترتيبِ السجلّ |
| `DEC-FAIL-02` | RUNTIME_PRESENT | TABLE_PROBE: monthly_performance_downtime (4 صفًّا) | تقطيعُ التوقّفِ الزمنيُّ سجلٌّ حيّ |
| `DEC-FAIL-03` | TARGET_PROPAGATED_BUILD_PENDING | TARGET_RULING: دفترُ المتطلّباتِ الحاكم | ساعةُ العطلِ من لحظةِ التوقّفِ — مُسقَطةٌ في متطلّباتِ الصيانةِ والبناءُ بترتيبِ السجلّ |
| `DEC-FAIL-04` | TARGET_PROPAGATED_BUILD_PENDING | TARGET_RULING: دفترُ المتطلّباتِ الحاكم | فرزُ التوقّفِ المخطَّطِ وStandby — مُسقَطٌ هدفًا والبناءُ بترتيبِ السجلّ |
| `DEC-FAIL-05` | RUNTIME_PRESENT | TABLE_PROBE: work_escalations (3962 صفًّا) | إقرارُ صاحبِ القطاعِ والتحكيمُ — سلسلةُ التصعيدِ حيّة |
| `DEC-FIN-01` | RUNTIME_PRESENT | ENGINE_FILE: app/Services/Financing/FinancingService.php | نموذجُ الدفعِ المستهدفُ ببابِ خدمتِه المقيَّد |
| `DEC-FIN-02` | RUNTIME_PRESENT | ENGINE_FILE: app/Services/Financing/FinancingService.php | التعثّرُ لا يعلّق آليًّا — البوّابةُ يدويّةٌ في الخدمة |
| `DEC-FIN-03` | RUNTIME_PRESENT | TABLE_PROBE: fin_final_close (0 صفًّا) | الإقفالاتُ الثلاثةُ كياناتٌ متمايزة (شهري/نهائي/تعاقدي) |
| `DEC-FLEET-01` | RUNTIME_PRESENT | TABLE_PROBE: exception_requests (0 صفًّا) | المسارُ الاستثنائيُّ الموثَّقُ سجلٌّ حيٌّ باعتمادِه واستهلاكِه |
| `DEC-FLEET-02` | RUNTIME_PRESENT | TABLE_PROBE: gov_ladders (17 صفًّا) | تصرّفُ الأصلِ عبر محرّكِ الاعتماد |
| `DEC-FLEET-03` | RUNTIME_PRESENT | ENGINE_FILE: app/Services/Unit/TimesheetEntryService.php | الساعاتُ من التشغيلِ/الأسطولِ مصدرًا واحدًا |
| `DEC-FLEET-04` | RUNTIME_PRESENT | TABLE_PROBE: fleet_depreciation_profile (33 صفًّا) | سياسةُ الإهلاكِ وقيمتُه عند الماليّةِ بملفِّها وتدقيقِه |
| `DEC-GOV-01` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 1 | الحوكمةُ تملك السياسةَ ودورُ مديرِ الصلاحياتِ ينفّذ |
| `DEC-GOV-02` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 1 | المراجعةُ الداخليّةُ مساحةٌ مستقلّةٌ خارجَ الحوكمة |
| `DEC-GOV-03` | RUNTIME_PRESENT | TABLE_PROBE: gov_investigation (0 صفًّا) | قضيّةُ الحوكمةِ سجلٌّ حيٌّ بشرطِ الخرق |
| `DEC-GOV-04` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 20 | سجلُّ المخاطرِ واحدٌ عند إدارتِه — ولا سجلَّ موازيًا في الحوكمة |
| `DEC-HR-01` | RUNTIME_PRESENT | TABLE_PROBE: payroll_time_inputs (4 صفًّا) | حافزُ الإنتاجِ من مدخلاتِ الوقتِ اشتقاقًا |
| `DEC-HR-02` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 1 | الموارد تملك الشخصَ — ولا وحدةَ أفرادِ مشاريعَ موازية |
| `DEC-HR-03` | RUNTIME_PRESENT | TABLE_PROBE: payroll_runs (74 صفًّا) | رأسُ المسيّرِ وبنودُ الموظّفين Child |
| `DEC-HR-04` | TARGET_PROPAGATED_BUILD_PENDING | TARGET_RULING: دفترُ المتطلّباتِ الحاكم | رأسُ القرضِ وأقساطُه Child — مُسقَطٌ في متطلّباتِ الموارد والبناءُ بترتيبِ السجلّ |
| `DEC-HR-05` | RUNTIME_PRESENT | TABLE_PROBE: hr_disciplinary_case (0 صفًّا) | القضيّةُ التأديبيّةُ منفصلةٌ والخصمُ بمرجعِها (سلّمُ الخصمِ بثلاثِ أيدٍ) |
| `DEC-INV-01` | RUNTIME_PRESENT | TABLE_PROBE: hr_disciplinary_case (0 صفًّا) | السلوكُ الوظيفيُّ يوجَّه للموارد بسجلِّه |
| `DEC-INV-02` | RUNTIME_PRESENT | TABLE_PROBE: gov_investigation (0 صفًّا) | تحقيقُ الالتزامِ عند الحوكمةِ بسجلِّه |
| `DEC-INV-03` | RUNTIME_PRESENT | TABLE_PROBE: gov_investigation (0 صفًّا) | سلسلةُ التصعيدِ بتعارضِ المصلحةِ بالحالة |
| `DEC-MNT-01` | TARGET_PROPAGATED_BUILD_PENDING | TARGET_RULING: دفترُ المتطلّباتِ الحاكم | شهادةُ العودةِ للخدمةِ — مُسقَطةٌ في متطلّباتِ W07 والبناءُ بترتيبِ السجلّ |
| `DEC-MNT-02` | RUNTIME_PRESENT | ENGINE_FILE: Governance/sod_conflicts.php | استقلالُ الفاحصِ/المصدّقِ ضمن فصلِ الواجباتِ الحيّ |
| `DEC-MNT-03` | TARGET_PROPAGATED_BUILD_PENDING | TARGET_RULING: دفترُ المتطلّباتِ الحاكم | جدولةُ OEM أساسًا — مُسقَطةٌ في متطلّباتِ الصيانةِ والبناءُ بترتيبِ السجلّ |
| `DEC-MY-01` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 1 | مساحةُ عملي شخصيّةٌ لا تملك طلبَ Domain |
| `DEC-MY-02` | RUNTIME_PRESENT | TABLE_PROBE: request_routes (20 صفًّا) | طلباتي مُطلِقٌ موحَّدٌ بإسقاطٍ — وسجلُّ التوجيهِ حيّ |
| `DEC-MY-03` | RUNTIME_PRESENT | TABLE_PROBE: request_routes (20 صفًّا) | نوعُ الطلبِ يحدّد وجهتَه من سجلٍّ مركزيّ |
| `DEC-OPEN-03` | BLOCKED_OWNER_VALUES | PENDING_CFG: config_pending_stage= blocker=STRUCTURAL | آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه |
| `DEC-OPEN-12` | BLOCKED_OWNER_VALUES | PENDING_CFG: config_pending_stage= blocker=STRUCTURAL | آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه |
| `DEC-OPEN-13` | BLOCKED_OWNER_VALUES | PENDING_CFG: config_pending_stage= blocker=THRESHOLD | آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه |
| `DEC-OPEN-14` | BLOCKED_OWNER_VALUES | PENDING_CFG: config_pending_stage= blocker=THRESHOLD | آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه |
| `DEC-OPEN-16` | BLOCKED_OWNER_VALUES | PENDING_CFG: config_pending_stage= blocker=STRUCTURAL | آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه |
| `DEC-OPEN-17` | BLOCKED_OWNER_VALUES | PENDING_CFG: config_pending_stage= blocker=STRUCTURAL | آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه |
| `DEC-OPEN-18` | BLOCKED_OWNER_VALUES | PENDING_CFG: config_pending_stage= blocker=STRUCTURAL | آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه |
| `DEC-OPS-01` | RUNTIME_PRESENT | ENGINE_FILE: app/Core/TenantDb.php | لا تعديلَ رجعيًّا بعد الاعتماد — حصانةُ الدفاترِ في البوّابة |
| `DEC-ORG-01` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 18 | السبعَ عشرةَ إدارةً بمساحاتِها الحيّة |
| `DEC-ORG-02` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 1 | التشغيلُ رأسُ القطاعِ بدورِه الحيّ |
| `DEC-ORG-03` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 1 | إدارةُ الموقعِ طبقةٌ إداريّةٌ بمساحتِها |
| `DEC-ORG-04` | RUNTIME_PRESENT | TABLE_PROBE: assignment_capabilities (333 صفًّا) | فريقُ المنجمِ تكوينٌ تشغيليٌّ بالقدراتِ لا إدارة |
| `DEC-ORG-05` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 1 | البلاغاتُ إدارةٌ بمساحتِها |
| `DEC-ORG-06` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 2 | القيادةُ مساحاتٌ تنفيذيّةٌ خارجَ تعدادِ الإدارات |
| `DEC-ORG-07` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 1 | مساحةُ عملي شخصيّةٌ لا إدارة |
| `DEC-ORG-08` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 1 | المراجعةُ وظيفةُ توكيدٍ مستقلّةٌ خارجَ الإدارات |
| `DEC-ORG-09` | RUNTIME_VERIFIED | NEGATIVE_PROBE: قيس = 0 | لا إدارةَ HSE — برهانُ نفيٍ حيّ |
| `DEC-ORG-10` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 1 | المشترياتُ إدارةٌ بوظائفِها الاستراتيجيّةِ مجموعةً داخلَها لا إدارةً مركزيّةً فوقَ الإدارات |
| `DEC-ORG-11` | RUNTIME_PRESENT | TABLE_PROBE: workforce_requirement (20 صفًّا) | الموارد تملك الشخصَ والعقدَ — والقوى تقرأ بالمفتاح |
| `DEC-PRC-01` | RUNTIME_PRESENT | TABLE_PROBE: gov_ladders (17 صفًّا) | حدودُ الإسنادِ المباشرِ من السياسةِ/السلّمِ لا Hardcode |
| `DEC-PRC-02` | RUNTIME_PRESENT | TABLE_PROBE: proc_orderpoint (20 صفًّا) | نقطةُ الطلبِ تنشئ مسودّةً فقط |
| `DEC-PRC-03` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 1 | المجموعةُ الاستراتيجيّةُ داخل إدارةِ المشتريات |
| `DEC-PRC-04` | RUNTIME_PRESENT | TABLE_PROBE: exception_requests (0 صفًّا) | مساراتُ الاستثناءِ workflow مسجَّلٌ باعتمادِه |
| `DEC-ROUTE-01` | RUNTIME_PRESENT | TABLE_PROBE: request_routes (20 صفًّا) | الحوكمةُ تملك بنيةَ سجلِّ التوجيه |
| `DEC-RSK-01` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 1 | المخاطرُ إدارةٌ مستقلّةٌ بمساحتِها |
| `DEC-RSK-02` | RUNTIME_PRESENT | TABLE_PROBE: risk_units (12 صفًّا) | عائلاتُ المخاطرِ بأصنافِها الحيّة |
| `DEC-RSK-03` | RUNTIME_PRESENT | TABLE_PROBE: risk_appetite (20 صفًّا) | شهيّةُ المخاطرِ سجلٌّ يعتمدُه صاحبُ السلطةِ المحجوزة |
| `DEC-RSK-04` | RUNTIME_PRESENT | TABLE_PROBE: risk_acceptances (20 صفًّا) | قبولُ المخاطرِ داخل الحدودِ بسجلِّه |
| `DEC-RSK-05` | RUNTIME_PRESENT | TABLE_PROBE: risk_assessments (20 صفًّا) | مصفوفةُ 5×5 بأبعادِ الأثرِ حيّة |
| `DEC-RSK-06` | RUNTIME_PRESENT | TABLE_PROBE: risk_signals (110 صفًّا) | المخاطرُ تقرأ أحداثَ المصدرِ بالمفتاحِ لا نسخًا تشغيليّة |
| `DEC-SAL-01` | RUNTIME_PRESENT | TABLE_PROBE: claims (300 صفًّا) | المطالبةُ المرحليّةُ بشرطِ عقدِها (دورةُ المطالبات الحيّة) |
| `DEC-SAL-02` | RUNTIME_PRESENT | TABLE_PROBE: gov_ladders (17 صفًّا) | السلّمُ يحدّد المعتمِدَ ولا ينشئ حقًّا تعاقديًّا |
| `DEC-SITE-01` | RUNTIME_PRESENT | ENGINE_FILE: app/Services/Unit/TimesheetEntryService.php | يومٌ×ورديّةٌ برأسِه وبنودِه (round_no) |
| `DEC-SITE-02` | TARGET_PROPAGATED_BUILD_PENDING | TARGET_RULING: دفترُ المتطلّباتِ الحاكم | الموقعُ يملك السكنَ والإعاشةَ — مُسقَطٌ في متطلّباتِ DEP-12 والبناءُ بترتيبِ السجلّ |
| `DEC-SITE-03` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 18 | التبعيّةُ من التنظيمِ لا من موجاتِ الإصلاح |
| `DEC-SUP-01` | TARGET_PROPAGATED_BUILD_PENDING | TARGET_RULING: دفترُ المتطلّباتِ الحاكم | ترشيحُ البديلِ آليًّا والإحلالُ بقرارٍ — مُسقَطٌ في متطلّباتِ الموردين والبناءُ بترتيبِ السجلّ |
| `DEC-SUP-02` | RUNTIME_PRESENT | TABLE_PROBE: sup_close (0 صفًّا) | استحقاقُ الموردِ دورةٌ مستقلّةٌ عن التحصيل |
| `DEC-SURF-01` | RUNTIME_PRESENT | ENGINE_FILE: tests/gov_m114_scope_filter_proof.php | بوّابةُ ازدواجِ المصدرِ شاهدٌ حيٌّ بمِجَسِّه السالب |
| `DEC-SURF-02` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 98 | تقريرُ الجمعِ إسقاطٌ مصنَّفٌ لا مالك |
| `DEC-SURF-03` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 7 | سطحُ الجميعِ إسقاطٌ منصّيٌّ مسجَّل |
| `DEC-SURF-04` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 40 | الدمجُ تبويبًا بقاعدةٍ مكتوبةٍ في source_ref كلِّ موضع |
| `DEC-SURF-05` | RUNTIME_PRESENT | ENGINE_FILE: assets/css/ems-filters.css | مكوّنُ التصفيةِ المركزيُّ الواحدُ للأسطحِ المؤهَّلة |
| `DEC-SURF-06` | RUNTIME_PRESENT | ENGINE_FILE: tools/baseline_reconcile.php | موانعُ المصالحةِ الثلاثةُ في أداتِها |
| `DEC-SURF-07` | RUNTIME_PRESENT | ENGINE_FILE: docs/REPAIR01_20260823/GATE00_BASELINE.md | انضباطُ اللقطةِ: شجرةٌ نظيفةٌ وHEAD ثابتٌ (عقدُ GATE-00 المطبَّقُ في كلِّ قصّ) |
| `DEC-SURF-08` | RUNTIME_PRESENT | TABLE_PROBE: nav_canonical (559 صفًّا) | لا اسمَ معروضًا غيرَ معتمدٍ — القاموسُ المعياريُّ يغلب |
| `DEC-TKT-01` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 1 | البلاغاتُ مصدرُ حقيقةِ دورةِ البلاغ |
| `DEC-TKT-02` | RUNTIME_PRESENT | TABLE_PROBE: request_routes (20 صفًّا) | البلاغُ يوجّه والكيانُ يُنشأ في إدارتِه |
| `DEC-TKT-03` | TARGET_PROPAGATED_BUILD_PENDING | TARGET_RULING: دفترُ المتطلّباتِ الحاكم | أدوارُ البلاغِ الأربعةُ المنفصلةُ — مُسقَطةٌ في متطلّباتِ DEP-10 والبناءُ بترتيبِ السجلّ |
| `DEC-TKT-04` | RUNTIME_PRESENT | TABLE_PROBE: ticket_escalations (74 صفًّا) | سجلّاتُ البلاغِ التابعةُ Child حيّة |
| `DEC-TKT-05` | RUNTIME_PRESENT | TABLE_PROBE: ticket_sla_policies (20 صفًّا) | الإغلاقُ الآليُّ بنافذةِ تحقّقٍ من سياسةٍ حيّة |
| `DEC-TKT-06` | RUNTIME_PRESENT | TABLE_PROBE: ticket_escalation_rules (20 صفًّا) | الحرجةُ لا تُغلق صمتًا — قواعدُ الحراسةِ حيّة |
| `DEC-TKT-07` | RUNTIME_PRESENT | TABLE_PROBE: request_routes (20 صفًّا) | سجلُّ كياناتِ الموضوعِ مركزيّ |
| `DEC-TRP-01` | RUNTIME_PRESENT | TABLE_PROBE: trp_trip_leg (0 صفًّا) | أرجلُ الرحلةِ Child منذ الآن |
| `DEC-TRP-02` | RUNTIME_PRESENT | TABLE_PROBE: transfer_delivery_docs (21 صفًّا) | التشغيلُ يطلب والنقلُ يملك التنفيذَ بعقدِ أمرِه |
| `DEC-TRS-01` | RUNTIME_PRESENT | TABLE_PROBE: permission_approval_steps (0 صفًّا) | رقابةٌ ثنائيّةٌ للتحويلِ الخارجيّ (خطواتُ اعتمادٍ لا فعلُ فردٍ) |
| `DEC-TRS-02` | RUNTIME_PRESENT | TABLE_PROBE: tre_petty_expense (0 صفًّا) | النثريّةُ ضمن حدِّ سياسةٍ بسجلِّها وعهدتِها |
| `DEC-TRS-03` | RUNTIME_PRESENT | TABLE_PROBE: fin_financial_events (5256 صفًّا) | طلبُ الدفعِ من الإدارةِ المستحقّةِ عبر بوّابةِ D05 والخزينةُ تنفّذ |
| `DEC-TRS-04` | TARGET_PROPAGATED_BUILD_PENDING | TARGET_RULING: دفترُ المتطلّباتِ الحاكم | توزيعُ التحصيلِ على فواتيرَ Child — مُسقَطٌ في متطلّباتِ الخزينةِ والبناءُ بترتيبِ السجلّ |
| `DEC-VP-01` | RUNTIME_PRESENT | TABLE_PROBE: gov_delegations (0 صفًّا) | التفويضُ الجزئيُّ المؤقّتُ بحدودِه ومدّتِه وسجلِّه |
| `DEC-WH-01` | RUNTIME_PRESENT | TABLE_PROBE: proc_item_track_rule (0 صفًّا) | التتبّعُ Lot/Serial/Expiry من دليلِ الأصنافِ شرطيًّا |
| `DEC-WH-02` | RUNTIME_PRESENT | TABLE_PROBE: proc_wh_custodian (0 صفًّا) | المخزنُ يملك سجلَّ العهدةِ — وسجلُّ الإسنادِ الجديدُ بحبّتِه (بُني في هذه الجولة) |
| `DEC-WH-03` | RUNTIME_PRESENT | TABLE_PROBE: proc_issue_request_line (0 صفًّا) | المطلوبُ/المعتمدُ/المصروفُ على مستوى البنود |
| `DEC-WRK-01` | RUNTIME_PRESENT | TABLE_PROBE: positions (20 صفًّا) | كتالوجُ المناصبِ منفصلٌ عن كتالوجِ القدرات (كلاهما حيّ) |
| `DEC-WRK-02` | RUNTIME_PRESENT | TABLE_PROBE: work_escalations (3962 صفًّا) | التصعيدُ عند غيابِ البديلِ بمحرّكِه |
| `DEC-WRK-03` | RUNTIME_VERIFIED | SQL_PROBE: قيس = 16 | لا تكرارَ لسجلِّ الشخصِ — القوى تقرأ بالمفتاحِ الأجنبيّ |

> الأحدَ عشرَ `NEEDS_OWNER_DECISION` بقيمِها في `OWNER_ACTION_REGISTER` — لا تدخل هذا المقامَ حتى تُعتمد.
