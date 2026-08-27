# RPR-W14 — دليلُ عبورِ رحلةِ الضابط (§٦-أ)
> مولَّدٌ من `repair01_w14_journey`. **الجولة:** `W14J-20260827062747071757`

**الرحلة:** انحرافٌ تشغيليٌّ يقع ← يُصنَّف بقاعدةٍ مكتوبة ← إن تجاوز شهيّةَ المخاطرِ صار **تعرُّضًا**
عند المخاطر ← إن كسر ضابطًا صار **خرقًا** عند الحوكمة ← إن لم يكن أيَّهما بقي **انحرافًا** عند
مالكِه ولا تُفتح حالةُ حوكمة ← والمراجعةُ تفحص العيّنةَ وترفع نتيجةً **لا تعدّلها الحوكمة**.


## الشوط: القاعدة

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | **الأثرُ التجاريّ** | الحالةُ بعدها | عبرت |
|---:|---|---|---|---|---|---|---|:--:|
| 1 | كتابة قاعدة تصنيف بشروطها الثلاثة | `ctl_classification_rule` | `DeviationClassifier` | قاعدة مسودة بثلاثة شروط مكتوبة | الرمز=OK | التصنيف يصير له مرجع مكتوب - وبلا قاعدة لا يقع تصنيف اصلا | `draft` | ✔ |
| 2 | من كتب القاعدة يعتمدها | `ctl_classification_rule` | `DeviationClassifier` | رمز الرد SAME_ACTOR_AUTHOR_AND_APPROVE_RULE | الرمز=SAME_ACTOR_AUTHOR_AND_APPROVE_RULE | قاعدة يعتمدها كاتبها قرار بيد واحدة - والرد يمنعها قبل ان تنفذ | `مرفوض` | ✔ |
| 3 | اعتماد القاعدة بيد ثانية | `ctl_classification_rule` | `DeviationClassifier` | قاعدة نافذة واحدة | نافذة=1 | التصنيف يصير ممكنا - وقبل النفاذ يرد كل تصنيف يستند اليها | `active` | ✔ |

## الشوط: الانحراف

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | **الأثرُ التجاريّ** | الحالةُ بعدها | عبرت |
|---:|---|---|---|---|---|---|---|:--:|
| 4 | انحراف يملكه نطاق رقابة | `ctl_deviation` | `DeviationClassifier` | رمز الرد DEVIATION_OWNED_BY_CONTROL | الرمز=DEVIATION_OWNED_BY_CONTROL | العطل يبقى Source Event عند التشغيل والصيانة - والرد يمنع ان تملكه الحوكمة | `مرفوض` | ✔ |
| 5 | تسجيل انحراف غير مخطط عند مالكه التشغيلي | `ctl_deviation` | `Risk/risk_events.php` | مالك تشغيلي غير نطاق رقابة | المالك=DEP-04 | سطح احداث المخاطر يقرأ الانحراف بمرجعه ولا ينسخه | `registered` | ✔ |
| 6 | تصنيف بلا قاعدة مكتوبة | `ctl_deviation` | `DeviationClassifier` | رمز الرد CLASSIFY_WITHOUT_WRITTEN_RULE | الرمز=CLASSIFY_WITHOUT_WRITTEN_RULE | التمييز الثلاثي قاعدة تكتب لا اجتهاد مصنف - والرد يمنعه | `مرفوض` | ✔ |

## الشوط: التصنيف

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | **الأثرُ التجاريّ** | الحالةُ بعدها | عبرت |
|---:|---|---|---|---|---|---|---|:--:|
| 7 | انحراف تجاوز الحد يصير تعرضا | `ctl_deviation` | `RiskDomainService` | التصنيف RISK_EXPOSURE ومحفز واحد على الاقل | التصنيف=RISK_EXPOSURE · محفزات=1 | المخاطر تفتح محفزا - والحوكمة لا تفتح شيئا لان الاساس لم يتحقق | `RISK_EXPOSURE` | ✔ |
| 8 | صيانة مخططة اطول من الحد تبقى انحرافا عند مالكها | `ctl_deviation` | `RiskDomainService` | التصنيف DEVIATION_ONLY وصفر محفز | التصنيف=DEVIATION_ONLY · محفزات=0 | سجل المخاطر لا يمتلئ بصيانة مخططة طبيعية - وهو نص قرار المالك الثاني | `DEVIATION_ONLY` | ✔ |
| 9 | انحراف كسر ضابطا يصير خرقا عند الحوكمة | `ctl_deviation` | `GovernanceDomainService` | التصنيف GOVERNANCE_BREACH بلا محفز خطر | التصنيف=GOVERNANCE_BREACH | الحوكمة تفتح حالة - والمخاطر لا تفتح محفزا لان الحد لم يتجاوز | `GOVERNANCE_BREACH` | ✔ |
| 10 | اساس خرق خارج الثمانية | `ctl_deviation` | `DeviationClassifier` | رمز الرد BREACH_BASIS_OUTSIDE_EIGHT | الرمز=BREACH_BASIS_OUTSIDE_EIGHT | اساس مفتوح يجعل كل توقف قضية حوكمة - والحصر يمنعه | `مرفوض` | ✔ |

## الشوط: التعرض

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | **الأثرُ التجاريّ** | الحالةُ بعدها | عبرت |
|---:|---|---|---|---|---|---|---|:--:|
| 11 | محفز الحد على صيانة مخططة | `rsk_trigger` | `RiskDomainService` | رمز الرد TRIGGER_ON_PLANNED_DOWNTIME | الرمز=TRIGGER_ON_PLANNED_DOWNTIME | الصيانة المخططة مستثناة من محفز الحد بنص المالك - والرد ينفذه | `مرفوض` | ✔ |
| 12 | فتح محفز خطر بمرجع الانحراف لا بنسخته | `rsk_trigger` | `RiskDomainService` | محفز واحد يحمل رقم الانحراف مرجعا | مرجع الانحراف=W14J-DEV-A | فتح التعرض يصير ممكنا - والانحراف يبقى صفا واحدا عند مالكه | `raised` | ✔ |
| 13 | المحفز المفروز يصير خطرا مسجلا | `risk_register` | `Risk/risk_register.php` | سجل خطر واحد ومحفز محول | خطر=1 · حالة المحفز=converted | سجل المخاطر يقرأ الخطر برمزه فيصير للتعرض مالك ومهلة | `converted` | ✔ |
| 14 | حدث خطر بلا مرجع مصدر | `rsk_event` | `RiskDomainService` | رمز الرد RISK_EVENT_WITHOUT_SOURCE_REF | الرمز=RISK_EVENT_WITHOUT_SOURCE_REF | الحدث يقرأ مصدره بمرجعه - وبلا مرجع يصير نسخة معزولة | `مرفوض` | ✔ |
| 15 | تسجيل حدث خطر بمرجع مصدره | `rsk_event` | `Risk/risk_events.php` | حدث واحد بمرجع مصدر كامل | أحداث=1 | سطح الاحداث يعرض مصدر الواقعة بمرجعه فيقرأ التقييم مصدرا واحدا لا نسختين | `recorded` | ✔ |
| 16 | نسخة ثانية للمصدر نفسه | `rsk_event` | `RiskDomainService` | رمز الرد RISK_EVENT_DUPLICATES_SOURCE | الرمز=RISK_EVENT_DUPLICATES_SOURCE | النسخة الثانية لا تظهر خطأ بل رقما مضاعفا في تقرير - والمفتاح الفريد يردها | `مرفوض` | ✔ |
| 17 | قبول خطر وشهية المخاطر غير معتمدة عدديا | `risk_acceptances` | `RiskDomainService` | رمز الرد APPETITE_NOT_CONFIGURED | الرمز=APPETITE_NOT_CONFIGURED | المحرك يرد ولا يخترع رقما - والقيمة غير المعتمدة لا تمنع البناء ولا تخترع | `مرفوض` | ✔ |
| 18 | تصعيد الخطر بسببه | `risk_escalations` | `Risk/risk_escalations.php` | واقعة تصعيد واحدة بسبب مكتوب | تصعيدات=1 | سطح التصعيدات يعرض المصعد فيراه مستواه ولا يسكت الا باستلام مسجل | `raised` | ✔ |

## الشوط: الإغلاق

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | **الأثرُ التجاريّ** | الحالةُ بعدها | عبرت |
|---:|---|---|---|---|---|---|---|:--:|
| 19 | من اقترح الاغلاق يعتمده | `rsk_closure` | `RiskDomainService` | رمز الرد SAME_ACTOR_PROPOSE_AND_APPROVE_CLOSURE | الرمز=SAME_ACTOR_PROPOSE_AND_APPROVE_CLOSURE | اغلاق بيد واحدة يجعل الاثبات شكلا - والرد يمنعه في الخدمة وفي القاعدة معا | `مرفوض` | ✔ |
| 20 | اعتماد الاغلاق بيد ثانية بدليله | `rsk_closure` | `Risk/risk_closure.php` | الحالة approved | الحالة=approved | سجل الاغلاق يقرأ الخطر مغلقا بدليله فيقرأ الكون الرقابي انخفاض تقديره | `approved` | ✔ |

## الشوط: الخرق

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | **الأثرُ التجاريّ** | الحالةُ بعدها | عبرت |
|---:|---|---|---|---|---|---|---|:--:|
| 21 | حالة حوكمة على انحراف تشغيلي صرف | `gov_breach` | `GovernanceDomainService` | رمز الرد BREACH_ON_PURE_DEVIATION | الرمز=BREACH_ON_PURE_DEVIATION | محور المرحلة: لا تفتح حالة حوكمة لكل انحراف - والانحراف الصرف يبقى عند مالكه | `مرفوض` | ✔ |
| 22 | فتح حالة باساس خارج الثمانية | `gov_breach` | `GovernanceDomainService` | رمز الرد BREACH_BASIS_OUTSIDE_EIGHT | الرمز=BREACH_BASIS_OUTSIDE_EIGHT | الاساس محصور فيما سماه المالك - فلا يوسع باجتهاد | `مرفوض` | ✔ |
| 23 | فتح حالة حوكمة على انحراف مصنف خرقا | `gov_breach` | `Governance/breaches.php` | حالة واحدة تحمل رقم الانحراف مرجعا | مرجع الانحراف=W14J-DEV-C | سطح الاخلالات يقرأ الحالة بمرجع انحرافها ولا ينسخ الواقعة | `opened` | ✔ |
| 24 | اسناد اجراء تصحيحي بمالك ومهلة | `gov_corrective_action` | `Governance/corrective_actions.php` | اجراء واحد على الحالة | اجراءات=1 | حالة الحوكمة تقرأ اجراءها فتصير قابلة للاغلاق - وقبله لا تغلق | `assigned` | ✔ |
| 25 | مالك الاجراء يتحقق من اجرائه | `gov_corrective_action` | `GovernanceDomainService` | رمز الرد SAME_ACTOR_OWN_AND_VERIFY_ACTION | الرمز=SAME_ACTOR_OWN_AND_VERIFY_ACTION | التحقق بيد المالك يجعل الدليل شهادة على النفس - والرد يمنعه | `مرفوض` | ✔ |
| 26 | التحقق من الاجراء بيد ثانية بدليله | `gov_corrective_action` | `Governance/breaches.php` | الحالة verified | الحالة=verified | اغلاق حالة الحوكمة يصير ممكنا - وقبل التحقق يرد الاغلاق | `verified` | ✔ |
| 27 | من فتح الحالة يغلقها | `gov_breach` | `GovernanceDomainService` | رمز الرد SAME_ACTOR_OPEN_AND_CLOSE_BREACH | الرمز=SAME_ACTOR_OPEN_AND_CLOSE_BREACH | اغلاق بيد الفاتح يجعل الحالة سجل شكوى - والرد يمنعه في الخدمة وفي القاعدة | `مرفوض` | ✔ |
| 28 | اغلاق الحالة بيد ثانية بدليلها | `gov_breach` | `Governance/gov_board.php` | الحالة closed | الحالة=closed | لوحة الحوكمة تنقص عداد المفتوح فيقرأ المستوى اثر الاغلاق لا دعواه | `closed` | ✔ |

## الشوط: التحقيق

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | **الأثرُ التجاريّ** | الحالةُ بعدها | عبرت |
|---:|---|---|---|---|---|---|---|:--:|
| 29 | تحقيق تاديبي عند الحوكمة | `gov_investigation` | `GovernanceDomainService` | رمز الرد INVESTIGATION_KIND_OUTSIDE_OWNER | الرمز=INVESTIGATION_KIND_OUTSIDE_OWNER | التاديبي للموارد البشرية بنص DEC-OPEN-16 - والرد ينفذ الحسم لا يعيده اجتهادا | `مرفوض` | ✔ |
| 30 | تحقيق مستقل للمراجعة بلا تكليف مكتوب | `gov_investigation` | `GovernanceDomainService` | رمز الرد IAF_INVESTIGATION_WITHOUT_MANDATE | الرمز=IAF_INVESTIGATION_WITHOUT_MANDATE | المراجعة لا طابور تحقيق يومي لها - وتدخل بتكليف مكتوب حالة بحالة | `مرفوض` | ✔ |
| 31 | تحقيق من سجل المنع بلا فرز | `gov_investigation` | `GovernanceDomainService` | رمز الرد DENIAL_IS_NOT_AN_INVESTIGATION | الرمز=DENIAL_IS_NOT_AN_INVESTIGATION | النقر الممنوع ليس تحقيقا - والسلسلة سجل ثم نمط ثم تنبيه ثم فرز ثم تحقيق ان لزم | `مرفوض` | ✔ |
| 32 | بلاغ نزاهة بهوية محجوبة | `gov_integrity_report` | `Governance/integrity_reports.php` | بلاغ برمز مبلغ وبلا مفتاح انسان | محجوب=1 | سطح البلاغات يعرض الموضوع بلا هوية - والكشف لمستوى مخول وحده | `received` | ✔ |
| 33 | فرز البلاغ قبل اي تحقيق | `gov_integrity_report` | `Governance/investigations.php` | مرجع فرز مقروء | مرجع الفرز=W14J-REP | التحقيق يصير ممكنا بمرجع الفرز وحده - فلا يفتح تحقيق من تنبيه | `referred` | ✔ |
| 34 | تعارض معلن بلا تنح وسلطة محجوزة | `gov_investigation` | `GovernanceDomainService` | رمز الرد CONFLICT_WITHOUT_RECUSAL | الرمز=CONFLICT_WITHOUT_RECUSAL | ملكية السياسة لا تمنع التحقيق - لكن التعارض على مستوى القضية يوجب التنحي | `مرفوض` | ✔ |
| 35 | من فتح التحقيق يحسمه | `gov_investigation` | `GovernanceDomainService` | رمز الرد SAME_ACTOR_OPEN_AND_CONCLUDE | الرمز=SAME_ACTOR_OPEN_AND_CONCLUDE | الحسم بيد الفاتح يجعل التحقيق رأيا - والرد يمنعه في الخدمة وفي القاعدة | `مرفوض` | ✔ |
| 36 | حسم التحقيق بيد ثانية واحالة اثره | `gov_investigation` | `Employees/hr_disciplinary.php` | الاحالة الى الموارد البشرية | أحيل الى=DEP-07 | الموارد تستقبل النتيجة للاثر التاديبي ولا تعيد التحقيق نفسه | `referred` | ✔ |

## الشوط: المراجعة

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | **الأثرُ التجاريّ** | الحالةُ بعدها | عبرت |
|---:|---|---|---|---|---|---|---|:--:|
| 37 | نطاق برنامج مراجعة تحدده الحوكمة | `iaf_program` | `AuditDomainService` | رمز الرد AUDIT_SCOPE_SET_BY_GOVERNANCE | الرمز=AUDIT_SCOPE_SET_BY_GOVERNANCE | الحوكمة لا تعطي المراجع نطاقه - والرد ينفذ استقلال الخط الثالث | `مرفوض` | ✔ |
| 38 | من نفذ الخطوة يراجعها | `iaf_program` | `AuditDomainService` | رمز الرد SAME_ACTOR_PERFORM_AND_REVIEW | الرمز=SAME_ACTOR_PERFORM_AND_REVIEW | مراجعة المنفذ لعمله تفرغ الاختبار - والرد يمنعها في الخدمة وفي القاعدة | `مرفوض` | ✔ |
| 39 | اعتماد البرنامج بيد ثانية بمنهجية عينته | `iaf_program` | `Audit/iaf_test_samples.php` | الحالة approved | الحالة=approved | سحب العينة يصير ممكنا - وقبل الاعتماد لا مفردة تسحب | `approved` | ✔ |
| 40 | طلب دليل من المراجعة نفسها | `iaf_evidence_request` | `AuditDomainService` | رمز الرد EVIDENCE_REQUEST_TO_SELF | الرمز=EVIDENCE_REQUEST_TO_SELF | طلب المراجع دليلا من نفسه يفرغ التاكيد المستقل - والرد يمنعه | `مرفوض` | ✔ |
| 41 | تاخر التزويد يصعد بعتبة من السجل | `iaf_evidence_request` | `Audit/iaf_evidence_requests.php` | الحالة escalated ووسم العتبة | الحالة=escalated · وسم=TEST_ONLY_VALUE | سطح الطلبات يعرض المتاخر بسلمه - والعتبة معلقة فتقرأ قيمة اختبار موسومة لا رقما مخترعا | `escalated` | ✔ |
| 42 | اختبار مفردة عينة بنتيجتها | `iaf_sample` | `Audit/iaf_findings.php` | النتيجة exception بتفصيلها | النتيجة=exception | رفع الملاحظة يصير مسندا الى مفردة مختبرة لا الى انطباع | `tested` | ✔ |
| 43 | رفع ملاحظة ونتيجتها من المراجعة وحدها | `iaf_findings` | `Governance/audit_followup.php` | واضع النتيجة IAF | واضع النتيجة=IAF | متابعة الحوكمة تفتح على الملاحظة بمرجعها ولا تلمس نتيجتها | `open` | ✔ |

## الشوط: الاستقلال

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | **الأثرُ التجاريّ** | الحالةُ بعدها | عبرت |
|---:|---|---|---|---|---|---|---|:--:|
| 44 | الحوكمة تضع نتيجة مراجعة عبر خدمتها | `iaf_findings` | `GovernanceDomainService` | رمز الرد GOVERNANCE_CANNOT_SET_AUDIT_RESULT | الرمز=GOVERNANCE_CANNOT_SET_AUDIT_RESULT | الخدمة لا تملك بابا الى سجل الملاحظات اصلا - والنداء يعلن الحد ويرده | `مرفوض` | ✔ |
| 45 | الحوكمة تضع نتيجة مراجعة بكتابة مباشرة في القاعدة | `iaf_findings` | `chk_iaf_result_dept` | القاعدة ترد والقيمة تبقى IAF | ردت=نعم · القيمة=IAF | الاستقلال لا يثبت بسياسة اذا كان الباب مفتوحا في المخطط - والقيد يغلقه | `مرفوض` | ✔ |
| 46 | الحوكمة تغلق ملاحظة مراجعة | `iaf_findings` | `AuditDomainService` | رمز الرد AUDIT_RESULT_CLOSED_OUTSIDE_IAF | الرمز=AUDIT_RESULT_CLOSED_OUTSIDE_IAF | الاغلاق بتحقق المراجعة لا بادعاء الجهة ولا بقرار الحوكمة | `مرفوض` | ✔ |
| 47 | الخاضع للمراجعة يغلق ملاحظته | `iaf_findings` | `AuditDomainService` | رمز الرد AUDITEE_CANNOT_CLOSE_OWN_FINDING | الرمز=AUDITEE_CANNOT_CLOSE_OWN_FINDING | الاغلاق بادعاء الجهة يفرغ المتابعة - والرد يمنعه | `مرفوض` | ✔ |
| 48 | الحوكمة تتابع خطة الادارة بمرجع الملاحظة | `gov_audit_followup` | `Governance/audit_followup.php` | متابعة بمرجع وصفر عمود نتيجة | متابعة=1 · عمود نتيجة=0 | الحوكمة تقرأ الملاحظة وتتابع خطتها - ولا تحمل نتيجتها ولا تقديرها اصلا | `tracking` | ✔ |
| 49 | المراجعة تغلق ملاحظتها بتحققها | `iaf_findings` | `Audit/iaf_overview.php` | مغلق النتيجة IAF | مغلق النتيجة=IAF | لوحة المراجعة تنقص عداد المفتوح - والحوكمة تقرأ الاغلاق ولا تصنعه | `closed` | ✔ |

## الشوط: الحدود

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | **الأثرُ التجاريّ** | الحالةُ بعدها | عبرت |
|---:|---|---|---|---|---|---|---|:--:|
| 50 | المخاطر تفتح حالة حوكمة | `repair01_w14_domains` | `RiskDomainService` | رمز الرد RISK_CANNOT_OPEN_GOVERNANCE_CASE | الرمز=RISK_CANNOT_OPEN_GOVERNANCE_CASE | ثلاثة نطاقات لا محرك واحد - والعلاقة مرجع لا مشاركة | `مرفوض` | ✔ |
| 51 | المخاطر تضع نتيجة مراجعة | `repair01_w14_domains` | `RiskDomainService` | رمز الرد RISK_CANNOT_SET_AUDIT_RESULT | الرمز=RISK_CANNOT_SET_AUDIT_RESULT | ثلاثة نطاقات لا محرك واحد - والعلاقة مرجع لا مشاركة | `مرفوض` | ✔ |
| 52 | الحوكمة تكتب في سجل المخاطر | `repair01_w14_domains` | `GovernanceDomainService` | رمز الرد GOVERNANCE_CANNOT_WRITE_RISK_REGISTER | الرمز=GOVERNANCE_CANNOT_WRITE_RISK_REGISTER | ثلاثة نطاقات لا محرك واحد - والعلاقة مرجع لا مشاركة | `مرفوض` | ✔ |
| 53 | الحوكمة تحدد نطاق المراجعة | `repair01_w14_domains` | `GovernanceDomainService` | رمز الرد AUDIT_SCOPE_SET_BY_GOVERNANCE | الرمز=AUDIT_SCOPE_SET_BY_GOVERNANCE | ثلاثة نطاقات لا محرك واحد - والعلاقة مرجع لا مشاركة | `مرفوض` | ✔ |
| 54 | المراجعة تكتب في سجل المخاطر | `repair01_w14_domains` | `AuditDomainService` | رمز الرد AUDIT_CANNOT_WRITE_RISK_REGISTER | الرمز=AUDIT_CANNOT_WRITE_RISK_REGISTER | ثلاثة نطاقات لا محرك واحد - والعلاقة مرجع لا مشاركة | `مرفوض` | ✔ |
| 55 | المراجعة تفتح حالة حوكمة | `repair01_w14_domains` | `AuditDomainService` | رمز الرد AUDIT_CANNOT_OPEN_GOVERNANCE_CASE | الرمز=AUDIT_CANNOT_OPEN_GOVERNANCE_CASE | ثلاثة نطاقات لا محرك واحد - والعلاقة مرجع لا مشاركة | `مرفوض` | ✔ |
| 56 | المراجعة تفتح طابور تحقيق يومي | `repair01_w14_domains` | `AuditDomainService` | رمز الرد IAF_HAS_NO_DAILY_INVESTIGATION_QUEUE | الرمز=IAF_HAS_NO_DAILY_INVESTIGATION_QUEUE | ثلاثة نطاقات لا محرك واحد - والعلاقة مرجع لا مشاركة | `مرفوض` | ✔ |

## الشوط: الختام

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | **الأثرُ التجاريّ** | الحالةُ بعدها | عبرت |
|---:|---|---|---|---|---|---|---|:--:|
| 57 | محاور المرحلة الثلاثة مقيسة على الحي بعد الرحلة | `repair01_w14_domains` | `repair01_w14_gate.php` | حالة على انحراف صرف 0 ونسخ حدث 0 وتعديل نتيجة 0 وكتابة عابرة 0 | حالة=0 · نسخ=0 · تعديل=0 · عابرة=0 | الرحلة مارست الجداول فعلا ثم قيست عليها فالبناء مثبت لا مدعى | `صفر` | ✔ |

---

**المقيس:** محطّاتٌ 57 · عبرت 57 · أشواطٌ 11 · مستهلكونَ متمايزون 21 · بلا أثرٍ تجاريٍّ 0 · أثرٌ باقٍ بعد الكنسِ 0
