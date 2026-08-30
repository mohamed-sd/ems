# `RPR-02` §٨ — فرزُ الفائضِ قبلَ بناءِ الناقص

> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/rpr02_s8_surplus_triage.php --md` · اللقطة `SNAP-bff0b8b8-20260830-075131`

الأسطحُ الحيّةُ **621** · المُطالَبُ بها هدفًا **203** ⇒ **الفائضُ 418**.
وخطُّ الأمرِ يقول ٢٦٧ — **والفرقُ خبرٌ يُعلَن**: الكونُ الهدفيُّ أُعيد فصلُه في §٤·٢.

| الصنف | العدد | متى يُعالَج |
|---|---:|---|
| ⛔ **فائضٌ حرج** | **47** | قبلَ بناءِ أيِّ سطحٍ جديد |
| فائضٌ حميد | 371 | موجةُ تصفيةٍ لاحقةٌ بحكمٍ موثَّق |

## معاييرُ الحرجِ الخمسة

| المعيار | العدد | ما يدخله |
|---|---:|---|
| `DUAL_SOURCE` | **18** | كيانٌ تكتبه أكثرُ من واجهةٍ حيّة — حقيقةٌ بمصدرَين |
| `FOREIGN_WRITER` | **11** | كيانٌ تكتبه إدارتان فأكثرُ — كتابةٌ تعبر الحدود |
| `GUARD_BYPASS` | **0** | يكتب وحارسُه الخادميُّ غائب |
| `OWNER_UNKNOWN` | **18** | يكتب ومصدرُ حقيقتِه غيرُ مُعلَن |
| `CODE_NOT_DEPT` | **29** | ملكيّتُه رمزُ منصّةٍ لا إدارة |

⛔ **والسطحُ قد يحمل أكثرَ من معيار** — فيُعدُّ مرّةً في المقامِ وتُعرض معاييرُه كلُّها.
⛔ **وأربعةٌ من الخمسةِ لم تكن تُقاس قبلَ قياسِ الحبّة** (§٧·١) — فلا يُقرأ صفرُها
السابقُ نظافةً بل **غيابَ مقياس**.

## الفائضُ الحرجُ بأسمائه

| المعرِّف | المسمَّى | الإدارة | المعايير |
|---|---|---|---|
| `SCR-0017` | خطط المعالجة ومتابعتها | `IAF` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0029` | ردود الإدارات على الملاحظات | `IAF` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0032` | مركز تقارير الحوكمة | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0067` | الجزاءات والحوافز التعاقدية | `DEP-01` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0071` | مطابقة بيانات العميل | `DEP-01` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0103` | مساحة عمل محاسب التخصص اليوم | `DEP-05` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0112` | ربط الأصل بساعات تشغيله | `DEP-04` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0130` | احتساب إهلاك الفترة | `DEP-04` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0174` | أحكام العميل والمورد والمشغل | `DEP-05` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0182` | عمليات التمويل | `DEP-03` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0395` | المعاونون والنيابة المؤقتة | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0396` | لوحة الدور الشخصية | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0397` | الرئيسية | `` | `CODE_NOT_DEPT` |
| `SCR-0398` | قاموس المبتدئ بلغة أول يوم | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0399` | مساحة عملي | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0400` | ملفي الشخصي | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0401` | إدارة المعاونين | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0402` | التقارير والتحليلات الشخصية | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0403` | قريبا | `` | `CODE_NOT_DEPT` |
| `SCR-0404` | بطاقة المستخدم | `` | `CODE_NOT_DEPT` |
| `SCR-0405` | حسابات المستخدمين | `DEP-08` | `CODE_NOT_DEPT` |
| `SCR-0412` | أوامر الصيانة | `DEP-05` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0419` | غرفة عمليات التشغيل | `` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0449` | شاشة التشغيل | `DEP-13` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0451` | صندوق ما ينتظر اعتمادي | `DEP-05` | `CODE_NOT_DEPT` |
| `SCR-0453` | اعتمادات الرئيس التنفيذي | `EX-CEO` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0454` | موافقات التكليف | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0455` | تقارير المراجعة الداخلية | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0458` | تقارير الإدارة التنفيذية | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0462` | ورقة الإدارة | `` | `CODE_NOT_DEPT` |
| `SCR-0466` | شهادة إنجازي | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0467` | تقييمي | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0468` | لوحتي | `` | `CODE_NOT_DEPT` |
| `SCR-0470` | طلباتي | `` | `CODE_NOT_DEPT` |
| `SCR-0472` | التنبيهات | `` | `CODE_NOT_DEPT` |
| `SCR-0473` | مكوّنات البوابة | `` | `CODE_NOT_DEPT` |
| `SCR-0476` | سجل تدقيق الظهور | `` | `CODE_NOT_DEPT` |
| `SCR-0477` | من يرى ماذا ومكونات البوابة | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0478` | من يرى ماذا (محاكاة) | `` | `CODE_NOT_DEPT` |
| `SCR-0479` | مساحة العمل | `` | `CODE_NOT_DEPT` |
| `SCR-0505` | الاعتمادات المتأخرة والوثائق | `DEP-05` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0514` | تقرير المنع | `` | `CODE_NOT_DEPT` |
| `SCR-0557` | تغيير كلمة المرور | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0588` | إغلاق البلاغ وتأكيده | `` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0591` | الاستقبال والتصنيف لتوجيه البلاغات الجديدة | `` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0595` | أبلغ عن مشكلة من هذه الشاشة | `DEP-10` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0605` | تقرير من يتأخر ومن لا يستجيب | `` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
