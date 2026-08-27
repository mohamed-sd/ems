# قراراتُ المالكِ النهائيّة — 2026-08-27 · بوّابةُ G1–G7

> **المصدر:** `قرارات المالك.docx` — النسخةُ الأصليّةُ محفوظةٌ بجانبَه.
> ⛔ **نصُّ المالكِ حرفًا — لا يُحرَّر ولا يُلخَّص في موضعِه.**
> **الاستلام:** 2026-08-27 · **سبعةُ قراراتٍ وبوّابةٌ من سبعِ حواجب.**

---

قرار المالك النهائي على البنود المفتوحة وبوابة الانتقال إلى المرحلة الرابعة عشرة

راجعت البنود التي رفعتها، والتقييم الفني اللاحق عليها، وما ترتب عليها من آثار على المراحل W01 إلى W13.

هذه هي القرارات النهائية الحاكمة.

يجب تسجيلها في OWNER_DECISIONS_MASTER وربط كل قرار بمرجعه وتاريخه، ولا يجوز بعد ذلك تحويل أي توصية أو استنتاج أو نتيجة Migration إلى قرار مالك إلا بمرجع اعتماد صريح.

① شركة واحدة أم عدة شركات

القرار:

متعدد الشركات بالتصميم

إنجاز منصة واحدة، لكنها يجب أن تكون:

Multi Entity by Design

بحيث يستطيع النظام خدمة أكثر من كيان قانوني دون إعادة بناء المعمارية المالية مستقبلا.

ويكون لكل كيان قانوني مستقل:

Legal_Entity_ID

دفتر محاسبي مستقل

فترات مالية مستقلة

ميزان مراجعة مستقل

ذمم مستقلة

حسابات بنكية مستقلة

إقفال مستقل

قوائم مالية مستقلة

أصول والتزامات محاسبية مستقلة

ويجوز أن يبدأ التشغيل الفعلي حاليا بإكوبيشن للتشغيل وحدها، لكن لا تبنى المعمارية على افتراض أنها ستظل الكيان الوحيد.

القاعدة:

لا فاتورة مالية بلا Legal Entity
لا قيد محاسبي بلا Legal Entity
لا حساب بنكي بلا Legal Entity
لا إقفال بلا Legal Entity + Period
لا قائمة مالية تخلط كيانين إلا إذا كانت Consolidated Projection

وسم المعاملات بين شركات المجموعة

هذه الإضافة إلزامية من الآن، ولا تؤجل إلى مرحلة التجميع المالي.

أي معاملة بين كيانين تابعين للمجموعة يجب أن تحمل منذ إنشائها:

From_Legal_Entity_ID

To_Legal_Entity_ID

Intercompany_Flag

Counterparty_Entity_ID

Transaction_Type

المرجع المقابل عند توفره

لأن اكتشاف معاملات Intercompany بعد سنوات بأثر رجعي سيكون غير موثوق.

وإذا كانت شركة شقيقة تعامل تجاريا مع إكوبيشن للتشغيل كعميل أو مورد مستقل، تبقى دورة العميل أو المورد كاملة كما هي، مع إضافة:

Related / Intercompany Entity

ولا يؤدي كونها شركة شقيقة إلى حذف الدورة التعاقدية أو التشغيلية.

أما:

Intercompany Reconciliation

Eliminations

Consolidation Adjustments

فتبنى في طبقة التجميع لاحقا.

الحالة: APPROVED

② متى يكون عطل المعدة خطرا

هذا القرار يحتاج أن يبنى على طبيعة أعمال إكوبيشن، وليس على مفهوم السلامة وحده.

الخطر لدينا أوسع من Safety Risk

المعدة أصل منتج.

والأصل المنتج يفترض أن يعمل باستمرار.

لذلك:

أي توقف غير مخطط للمعدة يمثل تعرضا تشغيليا يجب قياسه وإدارته، حتى لو كان العطل الفني نفسه بسيطا

لكن يجب ألا نخلط أربعة مفاهيم مختلفة:

شدة العطل الفني

مدة التوقف

أثر التوقف على التشغيل

فشل إدارة الاستجابة

فقد يكون العطل:

بسيطا جدا

لكن المعدة تبقى متوقفة ثلاثة أيام بسبب عدم وجود قطعة أو فني.

وهنا:

العطل الفني بسيط
لكن خطر التوقف التشغيلي مرتفع جدا

وهذا الفصل يجب أن يظهر في نموذج البيانات.

التصنيف الأول: Technical Failure Severity

يصنف العطل الفني نفسه مثلا إلى:

بسيط

مشكلة محدودة وقابلة للإصلاح السريع.

رئيسي

عطل في مكون أو نظام رئيسي يحتاج تدخلا فنيا منظما أو استبدالا.

حرج فنيا

عطل جسيم في الأصل أو أحد أنظمته الأساسية وقد يحتاج إصلاحا كبيرا أو قرارا فنيا خاصا.

لكن:

Technical Severity لا تحدد وحدها Operational Risk

التصنيف الثاني: Downtime Risk

يقيس:

لماذا المعدة متوقفة وكم بقيت متوقفة وما أثر ذلك

ويعتمد على:

Duration

Production Impact

Preventability

Recurrence

Delay Cause

Response Time

Parts Availability

Technician Availability

Operational Substitution

Contractual Impact

المستهدفات التشغيلية

هدفنا أن نصل تدريجيا إلى:

المشكلات الصغيرة

الحد المستهدف 30 دقيقة

من لحظة توقف المعدة إلى عودتها الفعلية للعمل إذا كانت طبيعة المشكلة تسمح بذلك.

المشكلات الكبيرة القابلة للإصلاح أو الاستبدال المباشر

الحد المستهدف 3 ساعات

إذا كان:

التشخيص معروفا

القطعة موجودة أو البديل متاحا

الفني متاحا

ولا توجد ظروف استثنائية تمنع التنفيذ

ولا نقبل أن يكون معظم الزمن انتظار إجراءات أو اتصالات أو قرارات.

متى تبدأ ساعة التوقف

تبدأ من:

Actual Stop Time

أي اللحظة التي أصبحت فيها المعدة غير قادرة على أداء عملها المطلوب.

ولا تبدأ من:

لحظة تسجيل البلاغ

لحظة وصول الفني

لحظة استلام الصيانة

لأن هذه أزمنة يجب أن نقيسها داخل إجمالي التوقف.

مثال:

توقفت 08:00
المشغل أبلغ 08:20

إذن:

Reporting Delay = 20 minutes

ولا نسمح لتأخير البلاغ بإخفاء زمن التوقف الحقيقي.

الحالات التي تعتبر Trigger للخطر التشغيلي

الحالة الأولى

توقف غير مخطط يتجاوز 24 ساعة

يفتح:

Operational_Risk_Trigger

إلزاميا.

لكن هذه القاعدة لا تشمل تلقائيا كل حالة تكون المعدة فيها غير عاملة.

استثناء مهم من Trigger الأربع والعشرين ساعة

لا تعتبر الحالات التالية Operational Downtime Risk بنفس الآلية إذا كانت مخططة أو مبررة:

Planned Major Maintenance

صيانة رئيسية مخططة ومجازة مسبقا ولها:

Plan

Start Date

Expected Completion

Resources

Approval

Planned Overhaul

عمرة مخططة.

Client Caused Standby

استعداد أو توقف سببه العميل ومصنف تعاقديا بصورة صحيحة.

Approved Operational Standby

استعداد مخطط لأسباب تشغيلية معروفة.

لكن هذا لا يعني تجاهلها.

تسجل كل حالة بنوعها:

UNPLANNED_DOWNTIME

PLANNED_MAINTENANCE

PLANNED_OVERHAUL

CLIENT_STANDBY

OPERATIONAL_STANDBY

ويكون لكل نوع KPI وTrigger مناسب.

ولا نريد ملء Risk Register بصيانة مخططة طبيعية.

الحالة الثانية

مشكلة بسيطة تمتد لأكثر من ثلاثة أيام

هذه تعتبر:

Critical Downtime Management Failure

حتى لو كان أصل العطل بسيطا.

خصوصا إذا كان سبب استمرار التوقف:

قطعة غيار بسيطة غير متوفرة

عدم إنشاء Reorder

عدم توقع الحاجة

ضعف المخزون

تأخر شراء

تأخر نقل

عدم توفر فني

عدم تصعيد المشكلة

وجود حل بديل لم يستخدم

تأخر قرار إداري

وهنا التحقيق يكون:

لماذا بقيت المعدة متوقفة

وليس فقط:

ما الذي تعطل فيها

الحالة الثالثة

عطل كان يمكن منعه

إذا كان السبب:

صيانة وقائية لم تنفذ

تشحيم لم ينفذ

فحص لم ينفذ

مؤشر سابق تم تجاهله

توصية فنية لم تنفذ

جزء كان ينبغي تغييره

سوء تشغيل

استمرار تشغيل مع أعراض واضحة

يصنف:

PREVENTABLE_DOWNTIME

حتى لو كان زمن الإصلاح قصيرا.

الحالة الرابعة

عدم توفر الفني

إذا كان العطل لا يحتاج قطعة غيار وكان استمرار التوقف ناتجا عن عدم توفر:

الفني

المهندس

الخبير

المورد الفني

الدعم الخارجي

يصنف سبب استمرار التوقف:

TECHNICAL_CAPABILITY_DELAY

ولا يحمل العطل الفني زمنا سببه نقص القدرة الفنية.

الحالة الخامسة

تكرار نفس المشكلة أكثر من ثلاث مرات

بعد الحالة الثالثة المتشابهة:

لا يعامل الحدث التالي كعطل مستقل عادي

بل يفتح:

Recurrent Failure Review

ويراجع:

Root Cause

جودة الإصلاح السابق

التشخيص

الجزء المستخدم

جودة المورد

طريقة التشغيل

مهارة الفني

مشكلة تصنيع

Preventive Maintenance

ويجب ربط الحالات المتكررة تقنيا باستخدام:

Asset ID

Component

Failure Code

Symptom

Root Cause

قدر الإمكان.

ثمانية أبعاد يجب ألا تدمج في رقم واحد

لكل Failure/Downtime Event:

1 Technical Failure Classification

ما العطل.

2 Downtime Duration

كم استمر.

3 Operational Impact

ما أثره على الإنتاج والخطة والمشروع.

4 Preventability

هل كان قابلا للمنع.

5 Recurrence

هل تكرر.

6 Response Performance

هل استجبنا في الزمن المستهدف.

7 Delay Cause

لماذا استمر.

8 Responsibility Timeline

من كانت لديه المسؤولية في كل فترة.

تقطيع مسؤولية زمن التوقف

هذه قاعدة أساسية.

لا يحمل النظام كامل زمن التوقف تلقائيا على:

الصيانة

مثال:

توقف 48 ساعة قد يكون:

20 دقيقة تأخير إبلاغ

ساعتين تشخيص

30 ساعة انتظار قطعة

3 ساعات نقل

4 ساعات انتظار فني

ساعتين إصلاح

بقية الزمن انتظار اختبار أو قرار

يجب أن يسجل النظام هذه الفترات منفصلة.

Downtime Timeline

نحتاج نقاطا زمنية مثل:

Actual Stop

Operator Detection

Reported At

Site Acknowledged

Maintenance Accepted

Technician Assigned

Technician Arrived

Diagnosis Started

Diagnosis Completed

Part Requirement Raised

Store Checked

Procurement Requested

PO/Source Confirmed

Part Dispatched

Part Arrived

Repair Started

Repair Completed

Testing Completed

Return to Service Approved

Actual Production Restart

ثم يحسب:

Reporting Time

Response Time

Diagnosis Time

Waiting Technician

Waiting Spare

Procurement Time

Logistics Time

Repair Time

Testing Time

Total Downtime

من يقر مسؤولية كل قطاع زمني

لا نريد أن يتحول النظام إلى أداة اتهام.

كل Segment يحمل:

Proposed Delay Cause

Responsible Function

Evidence

Start

End

والقاعدة:

صاحب المرحلة

يقر بيانات مرحلته أو يعترض عليها.

إدارة التشغيل

هي مالك:

Downtime Timeline and Operational Reconciliation

وتفصل في النزاع التشغيلي على تقسيم الزمن.

مدير التشغيل

هو الحكم التنفيذي عند النزاع بين إدارتين حول:

أي فترة كانت تحت مسؤولية من

مع الاحتفاظ بالأدلة.

الحوكمة

لا تدخل في كل خلاف توقيت.

تدخل فقط إذا ظهر:

تلاعب

إخفاء

تغيير متعمد

تكرار خلافات بسبب ضعف ضابط

عدم الالتزام بإجراء إلزامي

المسؤولية لا تعني الإدانة

النظام يستطيع أن يقول:

هذه الـ30 ساعة كانت في حالة Waiting for Procurement

لكنه لا يقول تلقائيا:

إدارة المشتريات مقصرة

لأن السبب قد يكون:

عدم توفر القطعة في السوق

حظر استيراد

مورد خارجي

طريق مغلق

ظرف قاهر

إثبات التقصير يحتاج:

Cause Review + Evidence

إذن نفصل:

PROCESS_OWNERSHIP

عن:

FAULT_ACCOUNTABILITY

المسؤوليات الرئيسية

المشغل

الاكتشاف

الإبلاغ المبكر

الاستخدام الصحيح

Daily Care بحسب مسؤوليته

الموقع

إثبات التوقف

التنسيق

متابعة الحالة

التصعيد

الصيانة

التشخيص

خطة الإصلاح

تحديد القطعة

الفني

التنفيذ

الاختبار

المخازن

كشف الرصيد

الصرف

Reorder Data

Critical Stock

المشتريات

Sourcing

Buying

Supplier Follow Up

النقل

إذا احتاج الأمر نقل قطعة أو فني أو معدة.

إدارة التشغيل

Total Downtime Ownership

Operational Escalation

Substitute Equipment

Production Recovery

Cross Department Coordination

لوحة مدير التشغيل

يجب أن يرى:

المعدات المتوقفة الآن

مدة التوقف الحية

أقل من 30 دقيقة

تجاوز 30 دقيقة

تجاوز 3 ساعات

تجاوز 24 ساعة

تجاوز 3 أيام

Waiting Technician

Waiting Spare

Waiting Procurement

Waiting Transport

Waiting Decision

Recurrent Failures

Preventable Failures

Current Process Owner

Expected Return

Lost Production

Substitute Status

Return to Service

لا نربطه فقط بمفهوم السلامة.

يستخدم عندما:

خرج الأصل من Available

تم إصلاح رئيسي

تغير مكون رئيسي

عطل متكرر

حالة تحتاج اختبارا فنيا

أما أعمال العناية البسيطة التي لم تخرج الأصل من الخدمة فلا تحتاج شهادة منفصلة.

العلاقة بالمخاطر

العطل يبقى:

Source Event

عند التشغيل والصيانة.

لا ينشئ Risk Record لكل عطل.

ينشئ Risk Trigger عند تحقق قواعد مثل:

Unplanned downtime > 24 hours

Simple issue > 3 days

Recurrence > 3

Preventable failure

Material production impact

Repeated technical capability gap

Material procurement delay

ثم إدارة المخاطر تقرر معالجة التعرض.

العلاقة بالحوكمة

لا يفتح Governance Case لكل Downtime.

يفتح Trigger فقط إذا ظهر:

تجاهل إجراء إلزامي

عدم تصعيد مطلوب

تجاوز صلاحية

تلاعب

إخفاء

تزوير

خرق سياسة

المبدأ النهائي

التوقف حدث تشغيلي له ساعة بدأت لحظة توقف الأصل ولكل دقيقة لاحقة حالة وسبب ومالك عملية ويمكن تحليل المسؤولية عنها دون افتراض الإدانة

وهدفنا:

تقليل Mean Time to Restore إلى أدنى مستوى ممكن
وليس فقط تحسين Mean Time to Repair

لان الجزء الأكبر من خسارة المعدة قد يكون انتظار:

قرار

فني

قطعة

شراء

نقل

وليس زمن الإصلاح نفسه.

الحالة: APPROVED FINAL

③ من يملك توجيه الطلبات

القرار:

سجل أنواع الطلبات قدرة منصية مركزية وليس إدارة تشغيلية

نسميه:

Unified Request Type Registry

الحوكمة والالتزام

تملك:

Governance of the Registry

أي:

قواعد إنشاء نوع طلب

قواعد التسمية

متطلبات الملكية

قواعد الصلاحيات

قواعد الاستثناء

Versioning

Retirement

ولا تملك توجيه الطلبات اليومية.

كل Domain

يملك:

تعريف الطلبات الخاصة به

مثلا:

HR → Leave Request

Maintenance → Maintenance Request

Transport → Transport Request

Treasury → Treasury Request

Tickets → Ticket Types

AAM

يحدد:

من يعتمد

ولا يحدد:

إلى أي إدارة يذهب الطلب

النظام

ينفذ:

Routing Rule

مساحة عملي

تعمل:

Launcher + Projection

ولا تصبح Owner.

القاعدة الرباعية

Governance governs the registry
Domain owns request definition
AAM resolves approval authority
System executes routing

الحالة: APPROVED FINAL

④ الثلاثة والستون اسم شاشة

لا أعتمدها جماعيا.

نفذ:

Canonical Name Reconciliation

ولكل اسم أربع حالات بعد إضافة فحص الهوية:

CANONICAL_FROM_APPROVED_SOURCE

الاسم موجود في مصدر معتمد والوظيفة تطابق تعريفه.

يعتمد آليا.

SAME_MEANING_FORMAT_ONLY

فرق كتابة أو تنسيق فقط.

يصحح آليا.

IDENTITY_CONFLICT

الاسم موجود في مصدر معتمد لكن:

وظيفة الشاشة الفعلية لا تطابق تعريف الشاشة في المصدر

هذه ليست Naming Issue.

هذه:

Screen Identity Conflict

وترفع للمراجعة المعمارية قبل اعتماد الاسم.

TRUE_NAMING_DECISION

لا يوجد اسم حاكم أو يوجد تعارض حقيقي.

ترفع لي فقط هذه الحالات.

معيار أسماء واجهة إنجاز

بلا تشكيل

بلا شرح

بلا مصطلح تقني

بلا علامات ترقيم غير ضرورية

قصيرة

واضحة

مرتبطة بوظيفة الشاشة

الحالة: RECONCILE FIRST

⑤ السبعة والثلاثون ملفا القديم

لا تخمن مالكها.

ولا تتقاعدها كلها.

نفذ:

Legacy Surface Forensic Review

واجمع لكل ملف:

File

Route

Calls

Called By

Tables Read

Tables Written

Actions

Guard

Permission

Nav

Screen ID

Usage

Data

Target replacement

قاعدة مهمة لا يجوز تجاوزها

أي Legacy File يكتب في قاعدة البيانات لا يصنف DEAD أو RETIRE تلقائيا

WRITE_SURFACE = YES

يعني:

Mandatory Individual Review

حتى لو:

لم يظهر في Sidebar

لم يستخدم كثيرا

اسمه سيئ

لديه Target بديل

لأن ملف الكتابة قد يمثل مسار أعمال أو تكامل خفي.

أما Read Only Legacy Surface فيمكن مراقبته ثم إحالته أو تقاعده وفقا للتحليل.

التصنيفات

KEEP

MOVE

MERGE

TAB

REDIRECT

RETIRE

DEAD

NEEDS_OWNER_DECISION

لكن:

WRITE_SURFACE cannot become RETIRE or DEAD without explicit review evidence.

التقاعد

Disable New Use
→ Redirect if applicable
→ Preserve History
→ Monitor
→ Remove from Navigation
→ Retire

ولا حذف مادي متعجل.

الحالة: ANALYZE AND CLASSIFY

⑥ هل نبدأ المرحلة الرابعة عشرة

نعم

ولكن بعد اجتياز:

W13.5

ولا يكفي القول إن شروطها خضراء.

كل شرط يجب أن يملك:

Measurable test + evidence.

بوابة W13.5 النهائية

G1 Organizational Scope

PASS إذا:

17/17 Domains canonical

0 HSE Department

0 Central Procurement Department

0 Project Personnel Department

Leadership / My Workspace / IAF مصنفة خارج التسلسل

G2 Critical Source of Truth

PASS إذا لم توجد Source Ownership غير محسومة في التقاطعات الحرجة:

HR / Workforce

Procurement / Warehouse

Funding / Finance / Treasury

Fleet / Finance Depreciation

Risk / Domain Projections

Governance / Domain Projections

الهدف:

UNRESOLVED_CRITICAL_SOT = 0

G3 Owner Decision Integrity

PASS إذا:

100% من OWNER_APPROVED لها Approval Reference أو Legacy Verification Status

0 System Assumed Owner Approval

Negative test يمنع اعتماد بلا Evidence

G4 New Surface Ratchet

أي Surface جديد منذ تاريخ تفعيل البوابة يجب أن يملك:

Canonical ID

Owner

Label

Route

Lifecycle

Guard

Permission Policy

Source/Projection Classification

الهدف:

NEW_NONCOMPLIANT_SURFACES = 0

ولا نطلب صفر Legacy Debt بعد.

G5 Permission Model

PASS إذا تم تثبيت النموذج الحاكم واختبار:

Identity → Entity → Domain → Project → Site → Record → Field → Action → State → Authority → Delegation

مع اختبار سلبي على الأقل لـ:

Direct URL

Unauthorized Action

SoD

Expired Delegation

G6 W14 Domain Boundaries

يجب أن يكون مسجلا صراحة:

Risk

Source = Risk Register

Governance and Compliance

Source = Governance / Compliance / Integrity

Internal Audit

Source = Audit Engagement / Audit Finding

ولا Shared Master Transaction بينهم.

G7 Blocking Decisions

PASS إذا:

OPEN_STRUCTURAL_BLOCKERS_FOR_W14 = 0

أما القرارات العددية غير اللازمة لبناء المحرك:

CONFIG_PENDING

ولا تمنع W14.

إذن المرحلة الرابعة عشرة تبدأ بعد G1 إلى G7 فقط

ولا تنتظر:

إنهاء كل Legacy

تنظيف كل أسماء المالية

تقاعد كل الشاشات القديمة

حسم كل قيمة مالية

ما تبقى من القرارات المالية

التقييم الأخير يقول إن ما بقي تقريبا من القرارات المفتوحة أصبح من نوع:

Approval Amounts

Direct Award Threshold

Petty Cash Limit

Aggregation Window

Risk Appetite Quantitative Limits

Reserved Matters values

هذه لا أريد Hardcode لها الآن.

صنفها:

CONFIG_PENDING

وابن المحركات بحيث تقرأ القيم من:

Policy Registry

AAM

Risk Appetite Registry

Treasury Policy

Procurement Policy

ثم نعتمد الأرقام لاحقا قبل UAT أو Go Live حسب أثر كل قيمة.

لا تجعل CONFIG_PENDING سببا لاختراع قيمة افتراضية

إذا كانت القيمة غير معتمدة:

لا يكتب المبرمج رقما من عنده.

في بيئة الاختبار يمكن استخدام:

TEST_ONLY_VALUE

موسومة بوضوح وغير قابلة للانتقال إلى Production Configuration.

حدود W14 النهائية

إدارة الحوكمة والالتزام

Second Line.

تملك:

Governance Framework

Policy Governance

Compliance Obligations

Compliance Monitoring

AAM Governance

SoD Governance

Delegations

Exceptions

Waivers

Conflicts

Related Parties

Governance Breaches

Integrity Investigations

Corrective Action Monitoring

ولا تملك:

Risk Register

Audit Plan

Audit Finding

إدارة المخاطر

Second Line مستقلة عن Governance.

تملك:

Risk Identification

Classification

Assessment

Inherent Risk

Controls Reference

Residual Risk

Treatment

KRI

Monitoring

Acceptance

Escalation

في العائلات الأربع المعتمدة.

المراجعة الداخلية

Independent Third Line.

تملك:

Audit Universe

Audit Planning

Engagement

Scope

Audit Program

Testing

Sampling

Workpapers

Evidence

Findings

Recommendations

Management Responses

Follow Up

Assurance Conclusion

Independence

Quality

ولا تملك:

Policies

Compliance Operations

AAM

Risk Register

Operational Control Execution

التحقيقات

HR

Employee disciplinary matters.

Governance and Compliance

Integrity and compliance matters.

Internal Audit

لا تملك Daily Investigation Function.

تدخل في:

Special Independent Investigation

بتكليف مكتوب عندما تتطلب القضية استقلالا خاصا.

Conflict of Interest في التحقيق

ملكية الحوكمة للسياسة لا تمنعها من التحقيق في مخالفتها.

التعارض يقاس على مستوى القضية.

إذا كان:

موظف الحوكمة موضوع التحقيق

رئيسها طرفا

قرارها الخاص موضوع الشبهة

لا يوجد محقق مستقل

توجد مصلحة مباشرة في النتيجة

يتم:

Recusal

وترفع إلى Reserved Authority لتعيين جهة مستقلة.

Denied Actions

لا تنشئ تحقيقا تلقائيا.

التسلسل:

Denial Log
→ Threshold / Pattern
→ Governance Alert
→ Triage
→ Investigation if warranted

المراجعة الداخلية وصلاحية الوصول

لديها:

Assurance Read Access

حسب Engagement Scope.

ولا نعطيها Transactional Write Access لمجرد أنها Internal Audit.

أي وصول حساس يسجل.

المرحلة التالية بعد W14 وبقية النطاقات

لا أعتبر اكتمال المراحل النطاقية نهاية حملة الإصلاح.

يجب إنشاء:

Enterprise Debt Closure

وتغلق فيها:

Missing Ids

Missing Canonical Labels

Missing Guards

Missing Permissions

Unknown Owners

Duplicate Sources

Orphan Screens

Finance Legacy

Treasury Duplicates

HR Workforce Legacy overlaps

Procurement Warehouse overlaps

Funding Treasury overlaps

Tabs

Ghosts

Retired Routes

ثم Full Reconciliation

بعدها فقط نعيد القياس على:

17 Domains

Executive Workspace

Personal Workspace

IAF

ثم Functional and Integration Acceptance

لأن W01 إلى W13 وموجات UI لا تثبت وحدها:

Field completeness

Document lifecycle

State Machines

SoD effectiveness

Approval Enforcement

Event Effects

Retry

Compensation

Human UAT

القاعدة النهائية للمشروع

من الآن فصاعدا لا يوجد:

مكتمل

بمعنى واحد.

يجب التفريق بين:

Built

Registered

Owned

Guarded

Connected

Data Ready

Tested

Human UAT

Accepted

خلاصة قرارات المالك النهائية

① إنجاز Multi Entity by Design مع وسم Intercompany منذ إنشاء المعاملة — APPROVED

② خطر المعدة عندنا هو خطر التوقف التشغيلي الشامل لا شدة العطل الفني فقط مع فصل Planned Maintenance وClient Standby عن Unplanned Risk Trigger وبدء الساعة من Actual Stop Time وتقطيع المسؤولية زمنيا — APPROVED FINAL

③ Request Registry قدرة مركزية تحكمها الحوكمة وتملك الإدارات تعريف طلباتها ويحل AAM سلطة اعتمادها وينفذ النظام Routing — APPROVED FINAL

④ لا اعتماد جماعي لـ63 اسما بل مصالحة مع فحص هوية الشاشة ورفع TRUE NAMING وIDENTITY CONFLICT فقط — APPROVED PROCESS

⑤ الـ37 Legacy files تخضع لتحليل جنائي معماري وأي ملف كاتب لقاعدة البيانات يخضع لمراجعة فردية إلزامية قبل التقاعد — APPROVED PROCESS

⑥ ابدأ W14 بعد اجتياز G1 إلى G7 القابلة للقياس ولا تنتظر تنظيف كل Legacy Debt — APPROVED

وأضيف قرارا حاكما أخيرا:

كل ما بقي من عتبات مالية أو كميات أو حدود لا تغير المعمارية يصنف CONFIG_PENDING ولا يمنع البناء ولا يجوز للمبرمج أن يخترع قيمته ولا تستخدم قيمة اختبارية في الإنتاج

بهذه الصياغة أعتبر الأسئلة البنيوية اللازمة لبدء W14 محسومة بما يكفي للبناء دون اجتهاد، وما تبقى بعد ذلك هو إعداد سياسات رقمية وتشغيلية، وليس إعادة تصميم للنظام.