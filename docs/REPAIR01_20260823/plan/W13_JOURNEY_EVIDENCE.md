# RPR-W13 — دليلُ عبورِ رحلةِ العامل (§٦-أ)

> ⛔ **مولَّدٌ من المخزن — لا تحرّره يدويًّا**: `php tools/repair01_w13_docs.php` يعيد كتابتَه.

**تعيينٌ ← تأهيلٌ وجاهزيّة ← إسنادٌ لموقع ← حضورٌ فعليّ ← أداء ← بلاغٌ يخصّه يُنشئه مُبلِّغٌ غيرُه ← توجيهٌ لمالكِ الحلِّ في إدارتِه المختصّة ← تصعيدٌ عند التجاوز ← تحقّق ← إغلاق ← تسويةُ مستحقّاتِه.**

◆ **وأطرافُ التذكرةِ الأربعةُ أشخاصٌ متمايزون في السجلّ** — والمحطّةُ الختاميّةُ تقيس `COUNT(DISTINCT party_role)` و`COUNT(DISTINCT actor_id)` معًا، فأربعةُ أدوارٍ بثلاثةِ أشخاصٍ تُسقط الرحلة.

◆ **والمحطّةُ السالبةُ محطّة**: كلُّ سطرٍ حكمُه `مرفوض` استُدعيت فيه الخدمةُ فعلًا ورُدَّت **برمزٍ يُقرأ** — لا بامتناعٍ عن الاستدعاء.

**الجولة:** `W13J-20260826193153495339`

| # | الشوط | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | **الأثرُ التجاريّ** | الحالة | العبور |
|---:|---|---|---|---|---|---|---|---|:--:|
| 1 | التعيين | فتح شاغر بموجب خطة القوى | `rec_vacancies` | `Workforce/recruitment_pipeline.php` | شاغر واحد مفتوح بسببه | vac_id=33 | الشاغر يقرا مفتوحا في مسار التوظيف فيقبل الترشح | `open` | ✔ |
| 2 | التعيين | ترشح تحت الشاغر لا حقل في الشاغر | `rec_applications` | `Workforce/rec_applications.php` | الشاغر يحمل ترشحا واحدا على الاقل | ترشحات الشاغر=1 | سطح الترشحات يقرا الشاغر ومرشحيه منفصلين | `received` | ✔ |
| 3 | التعيين | كل مرحلة سطر بنتيجتها ومقيمها | `rec_stage_log` | `Workforce/rec_stages.php` | اربع مراحل مسجلة بلا قفز | مراحل مسجلة=4 | سطح المراحل يقرا مسار المرشح كاملا لا حالته الاخيرة وحدها | `contracting` | ✔ |
| 4 | التعيين | الترشح يصير موظفا بمرجعه | `employees` | `Employees/employees.php` | الترشح يحمل مفتاح الموظف الناتج | ترشح مربوط=1 | سجل الموظفين يقرا مصدر التعيين فلا يظهر الموظف بلا مسار | `onboarded` | ✔ |
| 5 | التأهيل | مستند الزامي بصلاحيته | `hr_employee_document` | `Employees/hr_employee_documents.php` | مستند واحد بتاريخ انتهاء | مستندات=1 | سطح المستندات يقرا صلاحية الرخصة فينبه قبل انتهائها | `valid` | ✔ |
| 6 | التأهيل | مستند الزامي بلا تاريخ انتهاء يرد | `hr_employee_document` | `PeopleCycleService` | رمز الرد MANDATORY_DOC_WITHOUT_EXPIRY | الرمز=MANDATORY_DOC_WITHOUT_EXPIRY | المستند الالزامي لا يدخل السجل بلا ما يجعله قابلا للتنبيه | `مرفوض` | ✔ |
| 7 | التأهيل | تدريب سلامة الزامي مكتمل بصلاحيته | `hr_training_record` | `Employees/hr_training.php` | برنامج مكتمل واحد بتاريخ صلاحيته | برامج مكتملة=1 | سطح التدريب يقرا صلاحية شهادة السلامة فينبه قبل انتهائها | `completed` | ✔ |
| 8 | التأهيل | تدريب الزامي مكتمل بلا صلاحية يرد | `hr_training_record` | `PeopleCycleService` | رمز الرد MANDATORY_TRAINING_WITHOUT_EXPIRY | الرمز=MANDATORY_TRAINING_WITHOUT_EXPIRY | شهادة بلا انتهاء تجعل التدريب الالزامي غير قابل للمتابعة | `مرفوض` | ✔ |
| 9 | التأهيل | مباشرة كاملة ببنود الزامية معلقة ترد | `hr_onboarding_item` | `PeopleCycleService` | رمز الرد ONBOARDING_INCOMPLETE | الرمز=ONBOARDING_INCOMPLETE · معلق=3 | المباشرة لا تعلن كاملة والعهدة لم تسلم | `pending` | ✔ |
| 10 | التأهيل | استثناء بند تهيئة بلا مستند يرد | `hr_onboarding_item` | `PeopleCycleService` | رمز الرد WAIVER_WITHOUT_DOCUMENT | الرمز=WAIVER_WITHOUT_DOCUMENT | الاستثناء لا يمر بلا توثيق فلا تصير القائمة شكلا بلا اثر | `مرفوض` | ✔ |
| 11 | التأهيل | اكتمال التهيئة يفتح المباشرة | `hr_onboarding_item` | `Workforce/payroll_runs.php` | صفر بند الزامي معلق | معلق بعد=0 · منجز=3 | الموظف يصير مؤهلا للادراج في المسير فلا يدرج من لم تكتمل مباشرته | `onboarded` | ✔ |
| 12 | الإسناد | طلب حركة وظيفية بموجبها | `hr_job_movement` | `Employees/hr_job_movements.php` | حركة واحدة بحالة مرفوعة | movement_id=6 | سطح الحركات يقرا الطلب بمرجع قراره | `submitted` | ✔ |
| 13 | الإسناد | من طلب الحركة لا يعتمدها | `hr_job_movement` | `PeopleCycleService` | رمز الرد SAME_ACTOR_REQUEST_AND_APPROVE_MOVEMENT | الرمز=SAME_ACTOR_REQUEST_AND_APPROVE_MOVEMENT | الاعتماد لا يقع بيد الطالب فيبقى للحركة شاهدان | `مرفوض` | ✔ |
| 14 | الإسناد | اعتماد الحركة من غير طالبها | `hr_job_movement` | `Employees/hr_job_movements.php` | حركة معتمدة واحدة | حركات معتمدة=1 | المنصب الجديد يقرا في سجل الحركات فيتغير موضع الموظف بقرار لا صامتا | `approved` | ✔ |
| 15 | الحضور | حضور فعلي برمز واحد لليوم | `attendance_days` | `Operations/attendance.php` | ثلاثة ايام حضور بلا تكرار | ايام مسجلة=3 | شاشة الحضور تقرا ايام العامل فيصير له اساس اجر | `P` | ✔ |
| 16 | الأداء | لا يقيم احد نفسه | `hr_performance_review` | `PeopleCycleService` | رمز الرد SAME_ACTOR_REVIEW_SELF | الرمز=SAME_ACTOR_REVIEW_SELF | التقييم راي غيره لا رايه في نفسه | `مرفوض` | ✔ |
| 17 | الأداء | تقييم وظيفي نهائي بمعاييره | `hr_performance_review` | `Employees/hr_performance.php` | تقييم نهائي واحد بدرجته | تقييمات نهائية=1 | سطح التقييم يقرا درجة الدورة بمرجع معاييرها | `finalized` | ✔ |
| 18 | البلاغ | مبلغ ومحل بلاغ متمايزان بمفتاحيهما | `tkt_party` | `Tickets/tkt_parties.php` | طرفان مسجلان بمفتاحين مختلفين | اطراف=2 | سطح الاطراف يقرا من بلغ منفصلا عن عم بلغ | `REPORTER+SUBJECT` | ✔ |
| 19 | البلاغ | فاعل واحد لا يشغل دورين في بلاغ واحد | `tkt_party` | `PeopleCycleService` | رمز الرد MERGED_PARTY_ACTOR | الرمز=MERGED_PARTY_ACTOR | الدمج يرد في القاعدة بمفتاحها لا بالنية | `مرفوض` | ✔ |
| 20 | البلاغ | مالك دورة التذكرة في مركز البلاغات | `tkt_party` | `Tickets/tkt_parties.php` | ادارة مالك التذكرة DEP-10 | الادارة=DEP-10 | المركز يملك الدورة فيقرا البلاغ في لوحته | `TICKET_OWNER` | ✔ |
| 21 | البلاغ | مالك الحل لا يكون ادارة البلاغات | `tkt_party` | `PeopleCycleService` | رمز الرد RESOLUTION_BY_TICKET_CENTER | الرمز=RESOLUTION_BY_TICKET_CENTER | المركز لا يملك تنفيذ الحل فلا يسجل مالكا له | `مرفوض` | ✔ |
| 22 | التوجيه | لا توجيه لادارة البلاغات كمالك حل | `tkt_routing_history` | `PeopleCycleService` | رمز الرد ROUTED_TO_TICKET_CENTER | الرمز=ROUTED_TO_TICKET_CENTER | الوجهة ادارة مختصة لا المركز نفسه | `مرفوض` | ✔ |
| 23 | التوجيه | توجيه الي بلا قاعدة مرجعية يرد | `tkt_routing_history` | `PeopleCycleService` | رمز الرد AUTO_ROUTE_WITHOUT_RULE | الرمز=AUTO_ROUTE_WITHOUT_RULE | التوجيه الالي بقاعدته لا باجتهاد لحظته | `مرفوض` | ✔ |
| 24 | التوجيه | توجيه الي لادارة مختصة بقاعدته | `tkt_routing_history` | `Tickets/tkt_routing.php` | واقعة توجيه واحدة الى غير المركز | وقائع توجيه=1 | سطح التوجيه يقرا وجهة البلاغ فيفتح الاسناد فيها | `AUTO` | ✔ |
| 25 | التوجيه | تصحيح توجيه بلا سبب مكتوب يرد | `tkt_routing_history` | `PeopleCycleService` | رمز الرد ROUTE_CORRECTION_WITHOUT_REASON | الرمز=ROUTE_CORRECTION_WITHOUT_REASON | الاحالة اليدوية موثقة بسببها لا صامتة | `مرفوض` | ✔ |
| 26 | الإسناد | لا اسناد معالجة لادارة البلاغات | `tkt_assignment_history` | `PeopleCycleService` | رمز الرد ASSIGN_TO_TICKET_CENTER | الرمز=ASSIGN_TO_TICKET_CENTER | المركز يوجه ولا يعالج | `مرفوض` | ✔ |
| 27 | الإسناد | الاسناد يثبت الطرف الرابع | `tkt_party` | `Tickets/tkt_parties.php` | اربعة اطراف باربعة مفاتيح متمايزة | اطراف=4 · مفاتيح متمايزة=4 | البلاغ يقرا باربعة اشخاص متمايزين في السجل | `RESOLUTION_OWNER` | ✔ |
| 28 | الإسناد | الاستلام من المكلف نفسه لا من مسنده | `tkt_assignment_history` | `PeopleCycleService` | رمز الرد ACKNOWLEDGE_BY_OTHER | الرمز=ACKNOWLEDGE_BY_OTHER | وقت الاستلام يثبت ان المكلف علم لا ان المركز اعلن | `مرفوض` | ✔ |
| 29 | الإسناد | لا مكلف بلا وقت استلام | `tkt_assignment_history` | `Tickets/tkt_assignment.php` | اسناد واحد بوقت استلام | اسنادات مستلمة=1 | سطح الاسناد يقرا مهلة الاستجابة من وقت الاستلام لا من وقت الاسناد | `received` | ✔ |
| 30 | التصعيد | مستوى فوق سقف السلم المسجل يرد | `ticket_escalations` | `PeopleCycleService` | رمز الرد ESCALATION_LEVEL_OUT_OF_LADDER | الرمز=ESCALATION_LEVEL_OUT_OF_LADDER · السقف=5 | السقف من السجل لا من الشيفرة فتغييره قرار ادارة | `مرفوض` | ✔ |
| 31 | التصعيد | تصعيد بمستواه عند تجاوز المهلة | `ticket_escalations` | `Tickets/tkt_escalation.php` | واقعة تصعيد واحدة بمستوى ضمن السلم | وقائع تصعيد=1 | سطح التصعيد يقرا مستوى البلاغ فيراه مدير الادارة المعالجة | `2` | ✔ |
| 32 | التواصل | كل تواصل سطر بقناته ووقته | `ticket_communications` | `Tickets/tkt_communications.php` | واقعة تواصل واحدة بقناتها | وقائع تواصل=1 | سطح التواصل يقرا ما بلغ به المبلغ فلا يبقى دور المركز شفهيا | `phone` | ✔ |
| 33 | المعالجة | لا معالجة تعلن بلا اجراء مسجل | `tkt_verification` | `PeopleCycleService` | رمز الرد RESOLVE_WITHOUT_ACTION | الرمز=RESOLVE_WITHOUT_ACTION | اعلان المعالجة يسنده عمل وقع لا نية | `مرفوض` | ✔ |
| 34 | المعالجة | اجراء معالجة من المركز يرد | `tkt_resolution_action` | `PeopleCycleService` | رمز الرد RESOLUTION_BY_TICKET_CENTER | الرمز=RESOLUTION_BY_TICKET_CENTER | المعالجة عند مالكها في ادارته المختصة | `مرفوض` | ✔ |
| 35 | المعالجة | اجراء بلا مرجع في شاشة ادارته يرد | `tkt_resolution_action` | `PeopleCycleService` | رمز الرد ACTION_WITHOUT_DEPT_REF | الرمز=ACTION_WITHOUT_DEPT_REF | الاجراء اثر في شاشة ادارته لا سطر في شاشة البلاغات وحدها | `مرفوض` | ✔ |
| 36 | المعالجة | اجراء معالجة بمرجعه في شاشة ادارته | `tkt_resolution_action` | `Tickets/tkt_resolution_actions.php` | اجراء واحد بمرجع شاشة ادارته | اجراءات=1 | اعلان المعالجة يصير ممكنا فلا يقفل بلاغ بلا عمل وقع | `DEP-14` | ✔ |
| 37 | التحقق | نافذة التحقق من السجل بحسب الاولوية | `tkt_verification` | `Tickets/tkt_verification.php` | نافذة الحرج 48 ساعة | المكتوب=48 | المبلغ يقرا مهلة اعتراضه بزمن معلن من السجل لا من الشيفرة | `verification` | ✔ |
| 38 | التحقق | من عالج لا يتحقق من عمله | `tkt_verification` | `PeopleCycleService` | رمز الرد SAME_ACTOR_RESOLVE_AND_VERIFY | الرمز=SAME_ACTOR_RESOLVE_AND_VERIFY | التحقق شهادة غيره لا شهادته على نفسه | `مرفوض` | ✔ |
| 39 | التحقق | لا اغلاق الي للبلاغ الحرج | `tkt_verification` | `PeopleCycleService` | رمز الرد AUTO_CLOSE_ON_CRITICAL | الرمز=AUTO_CLOSE_ON_CRITICAL | الحرج يغلق بشخص لا بمرور الوقت - جواب DEC-OPEN-05 | `مرفوض` | ✔ |
| 40 | الإغلاق | لا اغلاق بلا تحقق | `tkt_verification` | `PeopleCycleService` | رمز الرد CLOSE_WITHOUT_VERIFICATION | الرمز=CLOSE_WITHOUT_VERIFICATION | الاغلاق يعقب التحقق ولا يبدله | `مرفوض` | ✔ |
| 41 | التحقق | المبلغ يتحقق من معالجة بلاغه | `tkt_verification` | `Tickets/tkt_verification.php` | الحالة verified وصفة المتحقق REPORTER | الحالة=verified | الاغلاق يصير ممكنا وقد شهد المبلغ على الحل | `verified` | ✔ |
| 42 | الإغلاق | اغلاق البلاغ بعد تحققه | `tkt_verification` | `Tickets/tkt_verification.php` | الحالة closed | الحالة=closed | اعادة الفتح تصير ممكنة على دورة مغلقة وحدها | `closed` | ✔ |
| 43 | إعادة الفتح | اعادة فتح بلا سبب مكتوب ترد | `tkt_reopen` | `PeopleCycleService` | رمز الرد REOPEN_WITHOUT_REASON | الرمز=REOPEN_WITHOUT_REASON | الاعتراض بسببه لا بضغطة زر | `مرفوض` | ✔ |
| 44 | إعادة الفتح | العودة لمسار المعالجة لا لمركز البلاغات | `tkt_reopen` | `PeopleCycleService` | رمز الرد REOPEN_BACK_TO_CENTER | الرمز=REOPEN_BACK_TO_CENTER | اعادة الفتح تعود بالبلاغ الى ادارة حله لا الى نقطة تسجيله | `مرفوض` | ✔ |
| 45 | إعادة الفتح | اعادة فتح باعتراض المبلغ | `tkt_reopen` | `Tickets/tkt_reopen.php` | الدورة السابقة تصير reopened | حالة الدورة السابقة=reopened | سطح اعادة الفتح يقرا اعتراض المبلغ ويعيد البلاغ لادارة حله | `reopened` | ✔ |
| 46 | إعادة الفتح | دورة تحقق ثانية برقمها لا دورة تدهس سابقتها | `tkt_verification` | `Tickets/tkt_verification.php` | دورتان بارقامهما ونافذة العادي 72 | دورات=2 · النافذة=72 | السطح يقرا الدورتين منفصلتين فيظهر ان البلاغ اعيد فتحه مرة | `verification` | ✔ |
| 47 | الإغلاق | اغلاق الدورة الثانية بتحقق مختص | `tkt_verification` | `Tickets/tkt_verification.php` | دورة مغلقة واحدة والاخرى معادة الفتح | مغلقة=1 | البلاغ يقرا مغلقا بدورته الثانية وسجل دورته الاولى باق | `closed` | ✔ |
| 48 | القضية | لا يبلغ الموظف عن نفسه | `hr_disciplinary_case` | `PeopleCycleService` | رمز الرد SAME_ACTOR_SUBJECT_AND_REPORTER | الرمز=SAME_ACTOR_SUBJECT_AND_REPORTER | المبلغ ومحل البلاغ التاديبي شخصان | `مرفوض` | ✔ |
| 49 | القضية | فتح قضية بواقعتها | `hr_disciplinary_case` | `Employees/hr_disciplinary.php` | مرحلة واقعة واحدة مسجلة | مراحل=1 | سطح القضايا يقرا الواقعة بمبلغها قبل ان يقرا قرارها | `incident` | ✔ |
| 50 | القضية | من بلغ لا يحقق | `hr_disciplinary_case` | `PeopleCycleService` | رمز الرد SAME_ACTOR_REPORT_AND_INVESTIGATE | الرمز=SAME_ACTOR_REPORT_AND_INVESTIGATE | التحقيق يد ثانية غير يد التبليغ | `مرفوض` | ✔ |
| 51 | القضية | تكليف المراجعة الداخلية بلا مستند يرد | `hr_disciplinary_case` | `PeopleCycleService` | رمز الرد IAF_WITHOUT_ASSIGNMENT_DOC | الرمز=IAF_WITHOUT_ASSIGNMENT_DOC | المراجعة الداخلية بتكليف موثق لا باختصاص اصيل - جواب DEC-OPEN-16 | `مرفوض` | ✔ |
| 52 | القضية | تكليف محقق من ادارة مالكة معلنة | `hr_disciplinary_case` | `Employees/hr_disciplinary.php` | ادارة التحقيق DEP-07 | الادارة=DEP-07 | سطح القضايا يقرا مالك التحقيق فيعرف من يسال عنه | `investigation` | ✔ |
| 53 | القضية | من حقق لا يقرر | `hr_disciplinary_case` | `PeopleCycleService` | رمز الرد SAME_ACTOR_INVESTIGATE_AND_DECIDE | الرمز=SAME_ACTOR_INVESTIGATE_AND_DECIDE | القرار يد ثالثة غير يد التحقيق | `مرفوض` | ✔ |
| 54 | الخصم | لا خصم بلا قرار قضية محسوم | `payroll_deductions` | `PeopleCycleService` | رمز الرد DEDUCTION_WITHOUT_DECIDED_CASE | الرمز=DEDUCTION_WITHOUT_DECIDED_CASE | الخصم فرع بمرجع قراره لا حكم يكتب في شاشة المسير | `مرفوض` | ✔ |
| 55 | القضية | قرار تاديبي بعد ثلاث مراحل | `hr_disciplinary_case` | `Employees/hr_disciplinary.php` | ثلاث مراحل متمايزة مسجلة | مراحل متمايزة=3 | الخصم يصير ممكنا بمرجع القرار وحده | `decided` | ✔ |
| 56 | الخصم | خصم مسنود بمرجع قرار قضيته | `payroll_deductions` | `Workforce/payroll_lines.php` | مرجع الخصم يساوي مرجع القرار | المرجع=W13J-DEC | سطر الخصم يظهر في اسطر المسير بمرجع قراره فيقرا الموظف سبب خصمه | `penalty` | ✔ |
| 57 | المزايا | ميزة بلا مرجع في المسير ترد | `hr_benefit_enrollment` | `PeopleCycleService` | رمز الرد BENEFIT_WITHOUT_PAYROLL_REF | الرمز=BENEFIT_WITHOUT_PAYROLL_REF | الميزة تصب في المسير بمرجعها لا تبقى سطرا معلقا | `مرفوض` | ✔ |
| 58 | المزايا | اشتراك بحصتيه ومرجعه في المسير | `hr_benefit_enrollment` | `Employees/hr_benefits.php` | اشتراك ساري واحد | اشتراكات سارية=1 | مكون المزايا يدرج في اسطر المسير بمرجعه | `active` | ✔ |
| 59 | التصفية | العقد المكتوب مصدر حقيقة العلاقة | `employee_contracts` | `Employees/employee_contracts.php` | عقد ساري واحد للعامل | contract_id=6747 | لا اجر ولا خصم ولا تصفية الا على عقد ساري | `active` | ✔ |
| 60 | التصفية | لا تصفية قبل ابراء العهد | `employee_final_settlements` | `PeopleCycleService` | رمز الرد SETTLEMENT_WITHOUT_CLEARANCE | الرمز=SETTLEMENT_WITHOUT_CLEARANCE | الصرف يعقب ابراء العهدة لا يسبقه | `مرفوض` | ✔ |
| 61 | التصفية | سلفة قائمة تمنع التصفية | `employee_final_settlements` | `PeopleCycleService` | رمز الرد SETTLEMENT_WITH_OPEN_ADVANCE | الرمز=SETTLEMENT_WITH_OPEN_ADVANCE | تسوية السلف شرط لا توصية | `مرفوض` | ✔ |
| 62 | التصفية | من اعد التصفية لا يعتمدها | `employee_final_settlements` | `PeopleCycleService` | رمز الرد SAME_ACTOR_PREPARE_AND_APPROVE_SETTLEMENT | الرمز=SAME_ACTOR_PREPARE_AND_APPROVE_SETTLEMENT | التصفية حكم مالي بشاهدين | `مرفوض` | ✔ |
| 63 | التصفية | اعتماد التصفية بابراء عهدة وصفر سلفة | `employee_final_settlements` | `Workforce/final_settlement.php` | مستند ابراء قائم وصفر سلفة | الابراء=W13J-CLR · سلفة=0.00 | التصفية تصير قابلة للصرف عند الخزينة | `approved` | ✔ |
| 64 | الختام | اربعة ادوار باربعة اشخاص متمايزين | `tkt_party` | `Tickets/tkt_parties.php` | ادوار=4 ومفاتيح=4 | ادوار=4 · مفاتيح=4 | الفصل الرباعي مقروء في السجل لا مكتوب في وثيقة | `مكتمل` | ✔ |
| 65 | الختام | محورا المرحلة مقيسان على الحي بعد الرحلة | `repair01_w13_parties` | `repair01_w13_gate.php` | طرف مدموج 0 وتنفيذ حل للبلاغات 0 | مدموج=0 · تنفيذ للمركز=0 | الرحلة مارست الجداول فعلا ثم قيست عليها فالبناء مثبت لا مدعى | `صفر` | ✔ |

---

**المقيس:** محطّات **65/65** · أشواط **18** · مستهلكونَ متمايزون **25** · بلا أثرٍ تجاريٍّ **0**.
