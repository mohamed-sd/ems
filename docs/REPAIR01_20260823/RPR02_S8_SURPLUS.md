# `RPR-02` §٨ — فرزُ الفائضِ قبلَ بناءِ الناقص

> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/rpr02_s8_surplus_triage.php --md` · اللقطة `SNAP-251c1eae-20260829-220456`

الأسطحُ الحيّةُ **621** · المُطالَبُ بها هدفًا **203** ⇒ **الفائضُ 418**.
وخطُّ الأمرِ يقول ٢٦٧ — **والفرقُ خبرٌ يُعلَن**: الكونُ الهدفيُّ أُعيد فصلُه في §٤·٢.

| الصنف | العدد | متى يُعالَج |
|---|---:|---|
| ⛔ **فائضٌ حرج** | **150** | قبلَ بناءِ أيِّ سطحٍ جديد |
| فائضٌ حميد | 268 | موجةُ تصفيةٍ لاحقةٌ بحكمٍ موثَّق |

## معاييرُ الحرجِ الخمسة

| المعيار | العدد | ما يدخله |
|---|---:|---|
| `DUAL_SOURCE` | **47** | كيانٌ تكتبه أكثرُ من واجهةٍ حيّة — حقيقةٌ بمصدرَين |
| `FOREIGN_WRITER` | **31** | كيانٌ تكتبه إدارتان فأكثرُ — كتابةٌ تعبر الحدود |
| `GUARD_BYPASS` | **0** | يكتب وحارسُه الخادميُّ غائب |
| `OWNER_UNKNOWN` | **127** | يكتب ومصدرُ حقيقتِه غيرُ مُعلَن |
| `CODE_NOT_DEPT` | **29** | ملكيّتُه رمزُ منصّةٍ لا إدارة |

⛔ **والسطحُ قد يحمل أكثرَ من معيار** — فيُعدُّ مرّةً في المقامِ وتُعرض معاييرُه كلُّها.
⛔ **وأربعةٌ من الخمسةِ لم تكن تُقاس قبلَ قياسِ الحبّة** (§٧·١) — فلا يُقرأ صفرُها
السابقُ نظافةً بل **غيابَ مقياس**.

## الفائضُ الحرجُ بأسمائه

| المعرِّف | المسمَّى | الإدارة | المعايير |
|---|---|---|---|
| `SCR-0001` | سجل التدقيق والاطلاع | `DEP-08` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0004` | التكليفات التنظيمية | `EX-CEO` | `OWNER_UNKNOWN` |
| `SCR-0007` | صفحات النظام والإدارات | `DEP-08` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0008` | الأدوار وقوالب صلاحياتها | `DEP-08` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0009` | معالج إعداد الموظف | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0010` | فصل الواجبات المتعارضة | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0011` | سجل الموظفين | `DEP-07` | `OWNER_UNKNOWN` |
| `SCR-0012` | تسجيل التايم شيت والإنتاج في الموقع | `DEP-05` | `OWNER_UNKNOWN` |
| `SCR-0015` | صندوق موافقاتي وما ينتظر يدي | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0017` | خطط المعالجة ومتابعتها | `IAF` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0018` | صلاحيات المراجع داخل النظام | `IAF` | `OWNER_UNKNOWN` |
| `SCR-0021` | اختصاصات المراجعة العشرون | `IAF` | `OWNER_UNKNOWN` |
| `SCR-0028` | تقارير الجهة المشرفة | `IAF` | `OWNER_UNKNOWN` |
| `SCR-0029` | ردود الإدارات على الملاحظات | `IAF` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0032` | مركز تقارير الحوكمة | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0067` | الجزاءات والحوافز التعاقدية | `DEP-01` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0071` | مطابقة بيانات العميل | `DEP-01` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0084` | مطابقة سجل الأصول بالتشغيل | `DEP-04` | `OWNER_UNKNOWN` |
| `SCR-0085` | جسر ترقيم المعدات | `DEP-04` | `OWNER_UNKNOWN` |
| `SCR-0088` | مصدر المعدة ونمط تملكها | `DEP-04` | `OWNER_UNKNOWN` |
| `SCR-0093` | الأعيان الممولة | `DEP-03` | `OWNER_UNKNOWN` |
| `SCR-0102` | المرتجع المالي للإدارات | `DEP-05` | `OWNER_UNKNOWN` |
| `SCR-0103` | مساحة عمل محاسب التخصص اليوم | `DEP-05` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0104` | مصفوفة التوجيه لمحاسبي التخصصات | `DEP-05` | `OWNER_UNKNOWN` |
| `SCR-0105` | التخصصات المحاسبية العشرة | `DEP-05` | `OWNER_UNKNOWN` |
| `SCR-0109` | توليد استحقاقات عقد العميل | `DEP-05` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0110` | فاتورة المطالبة وإحالتها | `DEP-05` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0111` | شهادة الإنجاز الشهرية | `DEP-05` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0112` | ربط الأصل بساعات تشغيله | `DEP-04` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0121` | الحدود الصريحة لما لا يملكه كل دور | `DEP-05` | `OWNER_UNKNOWN` |
| `SCR-0123` | سجل بنود الوثائق وتغطيتها | `DEP-05` | `OWNER_UNKNOWN` |
| `SCR-0124` | مخالفات الوثائق وحسمها | `DEP-05` | `OWNER_UNKNOWN` |
| `SCR-0126` | ترحيل الأدوار المالية القديمة | `DEP-05` | `OWNER_UNKNOWN` |
| `SCR-0127` | إشراف رئيس الحسابات على محاسبي التخصصات | `DEP-05` | `OWNER_UNKNOWN` |
| `SCR-0130` | احتساب إهلاك الفترة | `DEP-04` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0133` | فحص شروط الاستحقاق | `DEP-05` | `OWNER_UNKNOWN` |
| `SCR-0147` | التمويل والالتزامات | `DEP-05` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0149` | استقبال معاملات الإدارات | `DEP-05` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0151` | مخصص الصيانة والعمرات | `DEP-05` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0154` | الالتزامات المحتملة والإفصاح | `DEP-05` | `OWNER_UNKNOWN` |
| `SCR-0158` | سجل الالتزامات | `DEP-05` | `OWNER_UNKNOWN` |
| `SCR-0159` | جدول الاستحقاقات | `DEP-05` | `OWNER_UNKNOWN` |
| `SCR-0162` | طلبات الدفع والسداد | `DEP-06` | `OWNER_UNKNOWN` |
| `SCR-0167` | سقوف سلطة الالتزام والدفع | `DEP-06` | `OWNER_UNKNOWN` |
| `SCR-0169` | مراحل دورتي الدفع والقبض | `DEP-06` | `OWNER_UNKNOWN` |
| `SCR-0171` | مصفوفة فصل الواجبات الثلاثة عشر | `DEP-06` | `OWNER_UNKNOWN` |
| `SCR-0172` | الأدوار الثمانية داخل وحدة الخزينة | `DEP-06` | `OWNER_UNKNOWN` |
| `SCR-0173` | الاعتماد المالي النهائي | `DEP-05` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0174` | أحكام العميل والمورد والمشغل | `DEP-05` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0178` | انحرافات التمويل في السداد والملكية والتوثيق | `DEP-03` | `OWNER_UNKNOWN` |
| `SCR-0179` | تغيرات عقود التمويل | `DEP-03` | `OWNER_UNKNOWN` |
| `SCR-0180` | نماذج التمويل ومعالجتها | `DEP-03` | `OWNER_UNKNOWN` |
| `SCR-0182` | عمليات التمويل | `DEP-03` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0187` | حصص الملكية في الأصول | `DEP-03` | `OWNER_UNKNOWN` |
| `SCR-0188` | علاقات الملكية بين الكيانات | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0189` | مهام المحاسب اليومية | `DEP-05` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0191` | موافقات إدارتي | `DEP-05` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0195` | طلباتي المالية | `DEP-05` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0197` | قواعد توجيه الطلبات المالية | `DEP-05` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0360` | دورة المراجعة الدورية للصلاحيات | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0362` | منح الصلاحية | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0364` | حدود المبالغ | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0367` | تسليمات الأحداث وحالاتها | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0369` | سجل الأسماء المعتمدة | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0372` | الاستعادة ومحضرها | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0382` | جلسات النيابة | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0383` | طابور المهام | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0386` | منح المجال المقيد | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0388` | حسابات بوابة الأطراف الخارجية | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0393` | حالات المستندات وانتقالاتها | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0394` | مركز الحوكمة التقني | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0395` | المعاونون والنيابة المؤقتة | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0396` | لوحة الدور الشخصية | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0397` | الرئيسية | `` | `CODE_NOT_DEPT` |
| `SCR-0398` | قاموس المبتدئ بلغة أول يوم | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0399` | مساحة عملي | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0400` | ملفي الشخصي | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0401` | إدارة المعاونين | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0402` | التقارير والتحليلات الشخصية | `WS-MY` | `OWNER_UNKNOWN` `CODE_NOT_DEPT` |
| `SCR-0403` | قريبا | `` | `CODE_NOT_DEPT` |
| `SCR-0404` | بطاقة المستخدم | `` | `CODE_NOT_DEPT` |
| `SCR-0405` | حسابات المستخدمين | `DEP-08` | `CODE_NOT_DEPT` |
| `SCR-0411` | إعدادات الصيانة | `DEP-14` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0412` | أوامر الصيانة | `DEP-05` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0419` | غرفة عمليات التشغيل | `` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0426` | توزيع وحدات المورد على معداته | `DEP-01` | `OWNER_UNKNOWN` |
| `SCR-0431` | الإقفال الشهري للوحدة | `DEP-05` | `OWNER_UNKNOWN` |
| `SCR-0434` | الإنتاج والقياس | `DEP-11` | `OWNER_UNKNOWN` |
| `SCR-0436` | سجل الوردية | `DEP-13` | `OWNER_UNKNOWN` |
| `SCR-0438` | أذون دخول وخروج المشغلين | `DEP-12` | `OWNER_UNKNOWN` |
| `SCR-0439` | جدول ورديات النهار والليل | `DEP-12` | `OWNER_UNKNOWN` |
| `SCR-0440` | جدول عمل المنجم الأسبوعي والشهري | `DEP-12` | `OWNER_UNKNOWN` |
| `SCR-0442` | التوقفات وتحديد المتحمل | `DEP-11` | `OWNER_UNKNOWN` |
| `SCR-0444` | الأعمال غير المفوترة | `DEP-01` | `OWNER_UNKNOWN` |
| `SCR-0445` | تصحيح الوحدات بالسلسلة الثلاثية | `DEP-11` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0446` | الأداء الشهري للوحدة | `DEP-11` | `OWNER_UNKNOWN` |
| `SCR-0449` | شاشة التشغيل | `DEP-13` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0451` | صندوق ما ينتظر اعتمادي | `DEP-05` | `CODE_NOT_DEPT` |
| `SCR-0452` | نماذج العمل ووحدات القياس | `DEP-01` | `OWNER_UNKNOWN` |
| `SCR-0453` | اعتمادات الرئيس التنفيذي | `EX-CEO` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0454` | موافقات التكليف | `WS-MY` | `OWNER_UNKNOWN` `CODE_NOT_DEPT` |
| `SCR-0455` | تقارير المراجعة الداخلية | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0457` | العقود والالتزامات المحجوزة أو المصعدة | `EX-CEO` | `OWNER_UNKNOWN` |
| `SCR-0458` | تقارير الإدارة التنفيذية | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0459` | المخاطر والقرارات العليا | `EX-CEO` | `OWNER_UNKNOWN` |
| `SCR-0460` | مراجعة العقود وملاحظاتها | `EX-CEO` | `OWNER_UNKNOWN` |
| `SCR-0462` | ورقة الإدارة | `` | `CODE_NOT_DEPT` |
| `SCR-0463` | وضع التأسيس وإغلاقه | `EX-CEO` | `OWNER_UNKNOWN` |
| `SCR-0466` | شهادة إنجازي | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0467` | تقييمي | `WS-MY` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` `CODE_NOT_DEPT` |
| `SCR-0468` | لوحتي | `` | `CODE_NOT_DEPT` |
| `SCR-0470` | طلباتي | `` | `OWNER_UNKNOWN` `CODE_NOT_DEPT` |
| `SCR-0472` | التنبيهات | `` | `OWNER_UNKNOWN` `CODE_NOT_DEPT` |
| `SCR-0473` | مكوّنات البوابة | `` | `OWNER_UNKNOWN` `CODE_NOT_DEPT` |
| `SCR-0475` | بصمة الإصدار وتقرير النشر | `EX-CEO` | `OWNER_UNKNOWN` |
| `SCR-0476` | سجل تدقيق الظهور | `` | `CODE_NOT_DEPT` |
| `SCR-0477` | من يرى ماذا ومكونات البوابة | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0478` | من يرى ماذا (محاكاة) | `` | `CODE_NOT_DEPT` |
| `SCR-0479` | مساحة العمل | `` | `OWNER_UNKNOWN` `CODE_NOT_DEPT` |
| `SCR-0480` | استهلاك المعدة ومعدله | `DEP-17` | `OWNER_UNKNOWN` |
| `SCR-0484` | صرف مواد من المخزن | `DEP-16` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0485` | كتالوج الأصناف والقطع الحرجة | `DEP-16` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0486` | المخازن وأنواعها | `DEP-16` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0489` | الاستلام المؤقت | `DEP-16` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0495` | موردو المشتريات | `DEP-16` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0500` | المرتجعات | `` | `OWNER_UNKNOWN` |
| `SCR-0505` | الاعتمادات المتأخرة والوثائق | `DEP-05` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0514` | تقرير المنع | `` | `CODE_NOT_DEPT` |
| `SCR-0553` | إعدادات المخاطر والتصنيف | `DEP-09` | `OWNER_UNKNOWN` |
| `SCR-0557` | تغيير كلمة المرور | `WS-MY` | `CODE_NOT_DEPT` |
| `SCR-0558` | تصنيف قواعد المنع للظهور وضبط البوابة | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0559` | صفحات النظام والإدارات | `DEP-08` | `DUAL_SOURCE` `OWNER_UNKNOWN` |
| `SCR-0560` | صلاحيات الأدوار | `DEP-08` | `OWNER_UNKNOWN` |
| `SCR-0588` | إغلاق البلاغ وتأكيده | `` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0591` | الاستقبال والتصنيف لتوجيه البلاغات الجديدة | `` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0593` | تصنيفات الأعطال والبلاغات | `` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0595` | أبلغ عن مشكلة من هذه الشاشة | `DEP-10` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0597` | مهل البلاغات وتصعيدها | `` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0600` | البلاغات المتكررة وسببها الجذري | `DEP-10` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0601` | أزمنة الاستجابة والإنجاز | `` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0602` | إعدادات البلاغات للأنواع والمهل والتصعيد | `` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0603` | تحويل البلاغ وتفريعه لمسارات | `DEP-10` | `OWNER_UNKNOWN` |
| `SCR-0605` | تقرير من يتأخر ومن لا يستجيب | `` | `DUAL_SOURCE` `FOREIGN_WRITER` `OWNER_UNKNOWN` |
| `SCR-0629` | الخصومات والجزاءات | `DEP-07` | `OWNER_UNKNOWN` |
| `SCR-0630` | السلف والعهد المالية | `DEP-13` | `OWNER_UNKNOWN` |
| `SCR-0636` | أرقام المشغلين الشاغرة | `DEP-13` | `OWNER_UNKNOWN` |
| `SCR-0637` | الأداء الشهري للمشغل | `DEP-07` | `OWNER_UNKNOWN` |
| `SCR-0638` | تأهيل المشغلين على أنواع المعدات | `DEP-07` | `OWNER_UNKNOWN` |
| `SCR-0643` | دورات التناوب والإجازة الميدانية | `DEP-13` | `OWNER_UNKNOWN` |
| `SCR-0772` | الطلبات المرفوعة إلى القيادة | `EX-CEO` | `DUAL_SOURCE` `FOREIGN_WRITER` |
