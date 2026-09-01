# `GOVUI_DECISION_UI_IMPACT` — أثرُ القراراتِ المعتمَدةِ في الواجهة (§15)

> مولَّدٌ حيًّا بـ`tools/govui_decision_ui_impact.php` · اللقطة **BL-20260901-3dcd5b78** · 2026-09-01 19:39
> **المقامُ** قرارٌ معتمَدٌ **يذكر أسطحًا متأثِّرة** — وقرارٌ بلا سطحٍ خارجَ المقامِ بإعلانِه.
> ⛔ **ولا يُضيَّق المقامُ إلى المحلولِ بمعرِّفٍ (خمسةٌ فقط) فيخرج صفرٌ من فراغ** — ودرجةُ الحلِّ عمودٌ يُقرأ.

| المقياس | القيمة |
|---|---|
| `APPROVED_DECISION_WITH_UNPROPAGATED_UI_IMPACT` | **0/113** |
| `BLOCKED_OWNER_VALUES` | 7 |
| `RUNTIME_PRESENT` | 66 |
| `RUNTIME_VERIFIED` | 30 |
| `TARGET_PROPAGATED_BUILD_PENDING` | 10 |

## الصفوف

| `Decision_ID` | المجال | حلُّ الجسر | أسطحٌ بمعرِّف | حكمُ التشغيل | سندُ المِجَسّ |
|---|---|---|---|---|---|
| `DEC-ACC-01` | المالية | `UNIT` | 0 | `RUNTIME_PRESENT` | تقويمُ الإقفالِ كياناتٌ حيّة (شهري/نهائي/تعاقدي ثلاثتُها جداول) |
| `DEC-ACC-02` | المالية | `UNIT` | 0 | `RUNTIME_PRESENT` | محرّكُ الصرفِ بجدولَي الأسعارِ والفروق — base=amount×rate |
| `DEC-ACC-03` | المالية | `UNIT+UNRESOLVED` | 0 | `RUNTIME_PRESENT` | سجلّاتُ التكلفةِ حيّةٌ — وتعميقُ متوسّطِ التكلفةِ ببندِ حملةِ الماليّة |
| `DEC-ACC-04` | المالية | `UNIT` | 0 | `RUNTIME_PRESENT` | الإهلاكُ عند الماليّةِ وساعاتُ الاستخدامِ تُستورد قراءةً |
| `DEC-CEO-01` | القيادة | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | سجلُّ القراراتِ التنفيذيّةِ بنافذةِ توثيقِه |
| `DEC-CEO-02` | القيادة | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | السلطةُ المحجوزةُ مسجَّلةٌ (قبولُ المخاطرِ بحدودِه) |
| `DEC-CEO-03` | القيادة | `UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | الأسطحُ القياديّةُ إسقاطاتٌ مصنَّفةٌ لا نسخ |
| `DEC-CEO-04` | القيادة | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | محرّكُ الإنابةِ حيٌّ — وربطُ دورِ النوّابِ بقرارِ تنظيمٍ (كشفُ EX-DVP القائم) |
| `DEC-CEO-05` | القيادة | `SCOPE_CLASS` | 0 | `RUNTIME_VERIFIED` | الأسطحُ القياديّةُ مسجَّلةٌ رسميًّا في سجلِّ الشاشات |
| `DEC-ENT-01` | المعمارية | `SCOPE_CLASS` | 0 | `RUNTIME_VERIFIED` | مصدرُ الحقيقةِ مسجَّلٌ سطحًا سطحًا وقيدُ sot_witness يحرسه |
| `DEC-ENT-02` | المعمارية | `SCOPE_CLASS` | 0 | `RUNTIME_VERIFIED` | التنظيمُ من مساحاتِ الإداراتِ لا من موجاتِ الإصلاح |
| `DEC-ENT-03` | المعمارية | `SCOPE_CLASS` | 0 | `RUNTIME_VERIFIED` | كلُّ 1:N في Child Register — جداولُ البنودِ حيّة |
| `DEC-ENT-04` | المعمارية | `SCOPE_CLASS` | 0 | `RUNTIME_VERIFIED` | المشتقُّ صنفٌ محكومٌ صفرَ إدخالٍ (المحظورُ DERIVED+Editable بنصِّ الورقة) |
| `DEC-ENT-05` | المعمارية | `SCOPE_CLASS` | 0 | `RUNTIME_VERIFIED` | كلُّ حدثٍ يستلزم أثرًا بعقدِه — EFFECT_MISSING=0 نمطًا |
| `DEC-ENT-06` | المعمارية | `SCOPE_CLASS` | 0 | `RUNTIME_PRESENT` | محرّكُ الاعتمادِ الواحدُ حيٌّ — وقيمُه عند بوّابةِ OA-06 |
| `DEC-ENT-07` | المعمارية | `SCOPE_CLASS` | 0 | `RUNTIME_PRESENT` | القاعدةُ الحرجةُ في الخادمِ (عقدُ POST بفعلٍ مسجَّلٍ وصلاحيةٍ وIdempotency) |
| `DEC-ENT-08` | المعمارية | `SCOPE_CLASS` | 0 | `RUNTIME_PRESENT` | حصانةُ immutable_key في البوّابةِ — التصحيحُ حدثًا/نسخةً لا دهسًا |
| `DEC-ENT-09` | المعمارية | `UNIT` | 0 | `RUNTIME_PRESENT` | سجلُّ الرفضِ الموحَّدُ حيّ — log_security_event باسطحِه الاربعة |
| `DEC-ENT-10` | المعمارية | `UNIT` | 0 | `RUNTIME_VERIFIED` | حالاتُ التسليمِ الكاملةُ مع نبضِ cron_events المعلَن |
| `DEC-FAIL-01` | الصيانة | `UNIT+UNRESOLVED` | 0 | `TARGET_PROPAGATED_BUILD_PENDING` | نموذجُ أبعادِ العطلِ الثمانيةِ مُسقَطٌ في متطلّباتِ W07/W14 — والبناءُ بترتيبِ السجلّ |
| `DEC-FAIL-02` | الصيانة | `UNIT` | 0 | `RUNTIME_PRESENT` | تقطيعُ التوقّفِ الزمنيُّ سجلٌّ حيّ |
| `DEC-FAIL-03` | الصيانة | `UNIT` | 0 | `TARGET_PROPAGATED_BUILD_PENDING` | ساعةُ العطلِ من لحظةِ التوقّفِ — مُسقَطةٌ في متطلّباتِ الصيانةِ والبناءُ بترتيبِ السجلّ |
| `DEC-FAIL-04` | الصيانة | `UNIT+UNRESOLVED` | 0 | `TARGET_PROPAGATED_BUILD_PENDING` | فرزُ التوقّفِ المخطَّطِ وStandby — مُسقَطٌ هدفًا والبناءُ بترتيبِ السجلّ |
| `DEC-FAIL-05` | الصيانة | `UNIT` | 0 | `RUNTIME_PRESENT` | إقرارُ صاحبِ القطاعِ والتحكيمُ — سلسلةُ التصعيدِ حيّة |
| `DEC-FIN-01` | التمويل | `UNIT` | 0 | `RUNTIME_PRESENT` | نموذجُ الدفعِ المستهدفُ ببابِ خدمتِه المقيَّد |
| `DEC-FIN-02` | التمويل | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | التعثّرُ لا يعلّق آليًّا — البوّابةُ يدويّةٌ في الخدمة |
| `DEC-FIN-03` | التمويل | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | الإقفالاتُ الثلاثةُ كياناتٌ متمايزة (شهري/نهائي/تعاقدي) |
| `DEC-FLEET-01` | الأسطول | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | المسارُ الاستثنائيُّ الموثَّقُ سجلٌّ حيٌّ باعتمادِه واستهلاكِه |
| `DEC-FLEET-02` | الأسطول | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | تصرّفُ الأصلِ عبر محرّكِ الاعتماد |
| `DEC-FLEET-03` | الأسطول | `UNIT+UNRESOLVED` | 0 | `RUNTIME_PRESENT` | الساعاتُ من التشغيلِ/الأسطولِ مصدرًا واحدًا |
| `DEC-FLEET-04` | المالية | `UNIT` | 0 | `RUNTIME_PRESENT` | سياسةُ الإهلاكِ وقيمتُه عند الماليّةِ بملفِّها وتدقيقِه |
| `DEC-GOV-01` | الحوكمة | `UNIT` | 0 | `RUNTIME_VERIFIED` | الحوكمةُ تملك السياسةَ ودورُ مديرِ الصلاحياتِ ينفّذ |
| `DEC-GOV-02` | الحوكمة | `UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | المراجعةُ الداخليّةُ مساحةٌ مستقلّةٌ خارجَ الحوكمة |
| `DEC-GOV-03` | الحوكمة | `UNIT` | 0 | `RUNTIME_PRESENT` | قضيّةُ الحوكمةِ سجلٌّ حيٌّ بشرطِ الخرق |
| `DEC-GOV-04` | الحوكمة | `UNIT+UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | سجلُّ المخاطرِ واحدٌ عند إدارتِه — ولا سجلَّ موازيًا في الحوكمة |
| `DEC-HR-01` | الموارد | `UNIT` | 0 | `RUNTIME_PRESENT` | حافزُ الإنتاجِ من مدخلاتِ الوقتِ اشتقاقًا |
| `DEC-HR-02` | الموارد | `UNIT` | 0 | `RUNTIME_VERIFIED` | الموارد تملك الشخصَ — ولا وحدةَ أفرادِ مشاريعَ موازية |
| `DEC-HR-03` | الموارد | `UNIT` | 0 | `RUNTIME_PRESENT` | رأسُ المسيّرِ وبنودُ الموظّفين Child |
| `DEC-HR-04` | الموارد | `UNIT` | 0 | `TARGET_PROPAGATED_BUILD_PENDING` | رأسُ القرضِ وأقساطُه Child — مُسقَطٌ في متطلّباتِ الموارد والبناءُ بترتيبِ السجلّ |
| `DEC-HR-05` | الموارد | `UNIT` | 0 | `RUNTIME_PRESENT` | القضيّةُ التأديبيّةُ منفصلةٌ والخصمُ بمرجعِها (سلّمُ الخصمِ بثلاثِ أيدٍ) |
| `DEC-INV-01` | التحقيق | `SCREEN+UNIT` | 2 | `RUNTIME_PRESENT` | السلوكُ الوظيفيُّ يوجَّه للموارد بسجلِّه |
| `DEC-INV-02` | التحقيق | `UNIT+UNRESOLVED` | 0 | `RUNTIME_PRESENT` | تحقيقُ الالتزامِ عند الحوكمةِ بسجلِّه |
| `DEC-INV-03` | التحقيق | `UNIT+UNRESOLVED` | 0 | `RUNTIME_PRESENT` | سلسلةُ التصعيدِ بتعارضِ المصلحةِ بالحالة |
| `DEC-MNT-01` | الصيانة | `UNRESOLVED` | 0 | `TARGET_PROPAGATED_BUILD_PENDING` | شهادةُ العودةِ للخدمةِ — مُسقَطةٌ في متطلّباتِ W07 والبناءُ بترتيبِ السجلّ |
| `DEC-MNT-02` | الصيانة | `UNIT+UNRESOLVED` | 0 | `RUNTIME_PRESENT` | استقلالُ الفاحصِ/المصدّقِ ضمن فصلِ الواجباتِ الحيّ |
| `DEC-MNT-03` | الصيانة | `UNIT` | 0 | `TARGET_PROPAGATED_BUILD_PENDING` | جدولةُ OEM أساسًا — مُسقَطةٌ في متطلّباتِ الصيانةِ والبناءُ بترتيبِ السجلّ |
| `DEC-MY-01` | مساحة عملي | `UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | مساحةُ عملي شخصيّةٌ لا تملك طلبَ Domain |
| `DEC-MY-02` | مساحة عملي | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | طلباتي مُطلِقٌ موحَّدٌ بإسقاطٍ — وسجلُّ التوجيهِ حيّ |
| `DEC-MY-03` | مساحة عملي | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | نوعُ الطلبِ يحدّد وجهتَه من سجلٍّ مركزيّ |
| `DEC-OPEN-03` | المالية | `UNIT+UNRESOLVED` | 0 | `BLOCKED_OWNER_VALUES` | آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه |
| `DEC-OPEN-12` | الصيانة | `UNIT+UNRESOLVED` | 0 | `BLOCKED_OWNER_VALUES` | آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه |
| `DEC-OPEN-13` | القوى | `UNIT` | 0 | `BLOCKED_OWNER_VALUES` | آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه |
| `DEC-OPEN-14` | القيادة | `UNRESOLVED` | 0 | `BLOCKED_OWNER_VALUES` | آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه |
| `DEC-OPEN-16` | المراجعة الداخلية | `UNIT+UNRESOLVED` | 0 | `BLOCKED_OWNER_VALUES` | آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه |
| `DEC-OPEN-17` | السجلات | `UNIT+UNRESOLVED` | 0 | `BLOCKED_OWNER_VALUES` | آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه |
| `DEC-OPEN-18` | الهيكل | `SCOPE_CLASS` | 0 | `BLOCKED_OWNER_VALUES` | آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه |
| `DEC-OPS-01` | التشغيل | `UNIT` | 0 | `RUNTIME_PRESENT` | لا تعديلَ رجعيًّا بعد الاعتماد — حصانةُ الدفاترِ في البوّابة |
| `DEC-ORG-01` | الهيكل | `SCOPE_CLASS` | 0 | `RUNTIME_VERIFIED` | السبعَ عشرةَ إدارةً بمساحاتِها الحيّة |
| `DEC-ORG-02` | الهيكل | `SCOPE_CLASS` | 0 | `RUNTIME_VERIFIED` | التشغيلُ رأسُ القطاعِ بدورِه الحيّ |
| `DEC-ORG-03` | الهيكل | `UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | إدارةُ الموقعِ طبقةٌ إداريّةٌ بمساحتِها |
| `DEC-ORG-04` | الهيكل | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | فريقُ المنجمِ تكوينٌ تشغيليٌّ بالقدراتِ لا إدارة |
| `DEC-ORG-05` | الهيكل | `UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | البلاغاتُ إدارةٌ بمساحتِها |
| `DEC-ORG-06` | الهيكل | `SCOPE_CLASS` | 0 | `RUNTIME_VERIFIED` | القيادةُ مساحاتٌ تنفيذيّةٌ خارجَ تعدادِ الإدارات |
| `DEC-ORG-07` | الهيكل | `UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | مساحةُ عملي شخصيّةٌ لا إدارة |
| `DEC-ORG-08` | الهيكل | `UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | المراجعةُ وظيفةُ توكيدٍ مستقلّةٌ خارجَ الإدارات |
| `DEC-ORG-09` | الهيكل | `UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | لا إدارةَ HSE — برهانُ نفيٍ حيّ |
| `DEC-ORG-10` | الهيكل | `UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | المشترياتُ إدارةٌ بوظائفِها الاستراتيجيّةِ مجموعةً داخلَها لا إدارةً مركزيّةً فوقَ الإدارات |
| `DEC-ORG-11` | الهيكل | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | الموارد تملك الشخصَ والعقدَ — والقوى تقرأ بالمفتاح |
| `DEC-PRC-01` | المشتريات | `UNIT` | 0 | `RUNTIME_PRESENT` | حدودُ الإسنادِ المباشرِ من السياسةِ/السلّمِ لا Hardcode |
| `DEC-PRC-02` | المشتريات | `UNIT+UNRESOLVED` | 0 | `RUNTIME_PRESENT` | نقطةُ الطلبِ تنشئ مسودّةً فقط |
| `DEC-PRC-03` | المشتريات | `UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | المجموعةُ الاستراتيجيّةُ داخل إدارةِ المشتريات |
| `DEC-PRC-04` | المشتريات | `UNIT` | 0 | `RUNTIME_PRESENT` | مساراتُ الاستثناءِ workflow مسجَّلٌ باعتمادِه |
| `DEC-ROUTE-01` | الحوكمة | `UNIT+UNRESOLVED` | 0 | `RUNTIME_PRESENT` | الحوكمةُ تملك بنيةَ سجلِّ التوجيه |
| `DEC-RSK-01` | المخاطر | `UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | المخاطرُ إدارةٌ مستقلّةٌ بمساحتِها |
| `DEC-RSK-02` | المخاطر | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | عائلاتُ المخاطرِ بأصنافِها الحيّة |
| `DEC-RSK-03` | المخاطر | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | شهيّةُ المخاطرِ سجلٌّ يعتمدُه صاحبُ السلطةِ المحجوزة |
| `DEC-RSK-04` | المخاطر | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | قبولُ المخاطرِ داخل الحدودِ بسجلِّه |
| `DEC-RSK-05` | المخاطر | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | مصفوفةُ 5×5 بأبعادِ الأثرِ حيّة |
| `DEC-RSK-06` | المخاطر | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | المخاطرُ تقرأ أحداثَ المصدرِ بالمفتاحِ لا نسخًا تشغيليّة |
| `DEC-SAL-01` | المبيعات | `SCREEN` | 1 | `RUNTIME_PRESENT` | المطالبةُ المرحليّةُ بشرطِ عقدِها (دورةُ المطالبات الحيّة) |
| `DEC-SAL-02` | المبيعات | `SCREEN+UNRESOLVED` | 1 | `RUNTIME_PRESENT` | السلّمُ يحدّد المعتمِدَ ولا ينشئ حقًّا تعاقديًّا |
| `DEC-SITE-01` | الموقع | `UNIT` | 0 | `RUNTIME_PRESENT` | يومٌ×ورديّةٌ برأسِه وبنودِه (round_no) |
| `DEC-SITE-02` | الموقع | `UNIT` | 0 | `TARGET_PROPAGATED_BUILD_PENDING` | الموقعُ يملك السكنَ والإعاشةَ — مُسقَطٌ في متطلّباتِ DEP-12 والبناءُ بترتيبِ السجلّ |
| `DEC-SITE-03` | الهيكل | `SCOPE_CLASS` | 0 | `RUNTIME_VERIFIED` | التبعيّةُ من التنظيمِ لا من موجاتِ الإصلاح |
| `DEC-SUP-01` | الموردون | `SCREEN+UNRESOLVED` | 1 | `TARGET_PROPAGATED_BUILD_PENDING` | ترشيحُ البديلِ آليًّا والإحلالُ بقرارٍ — مُسقَطٌ في متطلّباتِ الموردين والبناءُ بترتيبِ السجلّ |
| `DEC-SUP-02` | الموردون | `SCREEN` | 2 | `RUNTIME_PRESENT` | استحقاقُ الموردِ دورةٌ مستقلّةٌ عن التحصيل |
| `DEC-SURF-01` | المعمارية | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | بوّابةُ ازدواجِ المصدرِ شاهدٌ حيٌّ بمِجَسِّه السالب |
| `DEC-SURF-02` | المعمارية | `UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | تقريرُ الجمعِ إسقاطٌ مصنَّفٌ لا مالك |
| `DEC-SURF-03` | المعمارية | `UNIT+UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | سطحُ الجميعِ إسقاطٌ منصّيٌّ مسجَّل |
| `DEC-SURF-04` | المعمارية | `UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | الدمجُ تبويبًا بقاعدةٍ مكتوبةٍ في source_ref كلِّ موضع |
| `DEC-SURF-05` | المعمارية | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | مكوّنُ التصفيةِ المركزيُّ الواحدُ للأسطحِ المؤهَّلة |
| `DEC-SURF-06` | المعمارية | `SCOPE_CLASS` | 0 | `RUNTIME_PRESENT` | موانعُ المصالحةِ الثلاثةُ في أداتِها |
| `DEC-SURF-08` | المعمارية | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | لا اسمَ معروضًا غيرَ معتمدٍ — القاموسُ المعياريُّ يغلب |
| `DEC-TKT-01` | البلاغات | `UNRESOLVED` | 0 | `RUNTIME_VERIFIED` | البلاغاتُ مصدرُ حقيقةِ دورةِ البلاغ |
| `DEC-TKT-02` | البلاغات | `UNIT` | 0 | `RUNTIME_PRESENT` | البلاغُ يوجّه والكيانُ يُنشأ في إدارتِه |
| `DEC-TKT-03` | البلاغات | `UNIT` | 0 | `TARGET_PROPAGATED_BUILD_PENDING` | أدوارُ البلاغِ الأربعةُ المنفصلةُ — مُسقَطةٌ في متطلّباتِ DEP-10 والبناءُ بترتيبِ السجلّ |
| `DEC-TKT-04` | البلاغات | `UNIT` | 0 | `RUNTIME_PRESENT` | سجلّاتُ البلاغِ التابعةُ Child حيّة |
| `DEC-TKT-05` | البلاغات | `UNIT` | 0 | `RUNTIME_PRESENT` | الإغلاقُ الآليُّ بنافذةِ تحقّقٍ من سياسةٍ حيّة |
| `DEC-TKT-06` | البلاغات | `UNIT` | 0 | `RUNTIME_PRESENT` | الحرجةُ لا تُغلق صمتًا — قواعدُ الحراسةِ حيّة |
| `DEC-TKT-07` | البلاغات | `UNIT` | 0 | `RUNTIME_PRESENT` | سجلُّ كياناتِ الموضوعِ مركزيّ |
| `DEC-TRP-01` | النقل | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | أرجلُ الرحلةِ Child منذ الآن |
| `DEC-TRP-02` | النقل | `UNIT+UNRESOLVED` | 0 | `RUNTIME_PRESENT` | التشغيلُ يطلب والنقلُ يملك التنفيذَ بعقدِ أمرِه |
| `DEC-TRS-01` | الخزينة | `UNIT` | 0 | `RUNTIME_PRESENT` | رقابةٌ ثنائيّةٌ للتحويلِ الخارجيّ (خطواتُ اعتمادٍ لا فعلُ فردٍ) |
| `DEC-TRS-02` | الخزينة | `UNIT` | 0 | `RUNTIME_PRESENT` | النثريّةُ ضمن حدِّ سياسةٍ بسجلِّها وعهدتِها |
| `DEC-TRS-03` | الخزينة | `UNIT` | 0 | `RUNTIME_PRESENT` | طلبُ الدفعِ من الإدارةِ المستحقّةِ عبر بوّابةِ D05 والخزينةُ تنفّذ |
| `DEC-TRS-04` | الخزينة | `UNIT` | 0 | `TARGET_PROPAGATED_BUILD_PENDING` | توزيعُ التحصيلِ على فواتيرَ Child — مُسقَطٌ في متطلّباتِ الخزينةِ والبناءُ بترتيبِ السجلّ |
| `DEC-VP-01` | القيادة | `UNIT+UNRESOLVED` | 0 | `RUNTIME_PRESENT` | التفويضُ الجزئيُّ المؤقّتُ بحدودِه ومدّتِه وسجلِّه |
| `DEC-WH-01` | المخازن | `UNRESOLVED` | 0 | `RUNTIME_PRESENT` | التتبّعُ Lot/Serial/Expiry من دليلِ الأصنافِ شرطيًّا |
| `DEC-WH-02` | المخازن | `UNIT` | 0 | `RUNTIME_PRESENT` | المخزنُ يملك سجلَّ العهدةِ — وسجلُّ الإسنادِ الجديدُ بحبّتِه (بُني في هذه الجولة) |
| `DEC-WH-03` | المخازن | `UNIT` | 0 | `RUNTIME_PRESENT` | المطلوبُ/المعتمدُ/المصروفُ على مستوى البنود |
| `DEC-WRK-01` | القوى | `UNIT` | 0 | `RUNTIME_PRESENT` | كتالوجُ المناصبِ منفصلٌ عن كتالوجِ القدرات (كلاهما حيّ) |
| `DEC-WRK-02` | القوى | `UNIT` | 0 | `RUNTIME_PRESENT` | التصعيدُ عند غيابِ البديلِ بمحرّكِه |
| `DEC-WRK-03` | القوى | `UNIT` | 0 | `RUNTIME_VERIFIED` | لا تكرارَ لسجلِّ الشخصِ — القوى تقرأ بالمفتاحِ الأجنبيّ |
