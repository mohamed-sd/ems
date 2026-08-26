# RPR-W12 — دليلُ عبورِ رحلةِ التمويل (§٦-أ)

> ⛔ **مولَّدٌ من المخزن — لا تحرّره يدويًّا**: `php tools/repair01_w12_docs.php` يعيد كتابتَه.

**اتّفاقيّةُ تمويلٍ ← التزامٌ تعاقديّ ← إقفالٌ تعاقديّ ← دفعاتٌ شهريّةٌ وإقفالٌ شهريّ ← تسويةٌ نهائيّةٌ وإقفالٌ نهائيّ** — والثلاثةُ **كياناتٌ متمايزةٌ تُقرأ منفصلةً**، وأمرُ دفعٍ مستقبليٌّ يُنشأ **بنموذجِه** لا بقالبِ التجميعِ التاريخيّ.

◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عند كلِّ مستهلكٍ **رقمٌ يعنيه** — لا «الحدثُ سُجِّل».

**الجولة:** `W12J-20260826155647448514`

| # | الشوط | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | الأثرُ التجاريّ | الحالة | ✔ |
|---:|---|---|---|---|---|---|---|---|:-:|
| 1 | التأسيس | جهة اتصال الممول مسجلة بمستند تفويضها | `fin_financier_contact` | سجل جهات الاتصال | مفوض بمستند تفويض | المعرف 10 | الممول له مفوض معلن فلا يوقع العقد من لا تفويض له | `active` | ✔ |
| 2 | التأسيس | مفوض بلا مستند تفويض يرد في القاعدة | `fin_financier_contact` | قيد chk_ffc_mandate | ترد الكتابة | ردت | لا تفويض بالقول - والمستند شرط في القاعدة لا في المراجعة | `refused` | ✔ |
| 3 | التأسيس | وثيقة تاهيل محققة بمحققها | `fin_financier_document` | سجل العناية الواجبة | وثيقة محققة | المعرف 10 | الممول مؤهل فيفتح له باب العروض | `verified` | ✔ |
| 4 | الدورة | حاجة تمويلية مرفوعة بمبررها | `fin_funding_need` | FinancingCycleService::raiseNeed | تقبل بمبرر مكتوب | المعرف 10 | الحاجة تسبق العرض فلا عرض بلا طلب | `submitted` | ✔ |
| 5 | الدورة | من رفع الحاجة لا يعتمدها | `fin_funding_need` | FinancingCycleService::approveNeed | SAME_ACTOR_RAISE_AND_APPROVE_NEED | SAME_ACTOR_RAISE_AND_APPROVE_NEED | الاعتماد بيد ثانية فلا تصير الادارة الطالبة سلطة اعتماد | `refused` | ✔ |
| 6 | الدورة | الحاجة تعتمد بيد ثانية | `fin_funding_need` | FinancingCycleService::approveNeed | approved | approved | الحاجة المعتمدة تفتح باب العروض | `approved` | ✔ |
| 7 | الدورة | عرض تمويل وارد باصداره | `fin_funding_offer` | FinancingCycleService::receiveOffer | عرض باصدار اول | المعرف 19 | العرض يقابل حاجة معتمدة | `received` | ✔ |
| 8 | الدورة | التفاوض ينشئ اصدارا ولا يدهس سابقه | `fin_funding_offer` | FinancingCycleService::receiveOffer | الاصدار الاول يبقى بحالة تفاوض | negotiating | تاريخ التفاوض محفوظ فيقرا الفرق بين الاصدارين | `negotiating` | ✔ |
| 9 | الدورة | مراجعة ما قبل التعاقد تجاز براي كل جهة | `fin_precontract_review` | FinancingCycleService::decidePrecontract | cleared | cleared | لا توقيع عقد قبل اجازة المراجعة | `cleared` | ✔ |
| 10 | التعاقد | عملية تمويل مفتوحة للرحلة | `financing_operations` | سجل العمليات | عملية بكيانها وممولها | المعرف 587 | العملية الكيان الاب لكل ما يلي | `active` | ✔ |
| 11 | التعاقد | مسودة عقد بمعدها | `fin_finance_contract` | FinancingCycleService::draftContract | مسودة عقد | المعرف 9 | المسودة لا تلزم حتى توقع | `draft` | ✔ |
| 12 | التعاقد | من اعد العقد لا يوقعه | `fin_finance_contract` | FinancingCycleService::signContract | SAME_ACTOR_PREPARE_AND_SIGN | SAME_ACTOR_PREPARE_AND_SIGN | التوقيع بيد ثانية فلا يصير المعد سلطة تعاقد | `refused` | ✔ |
| 13 | التعاقد | لا توقيع بلا مستند عقد | `fin_finance_contract` | FinancingCycleService::signContract | SIGN_WITHOUT_DOCUMENT | SIGN_WITHOUT_DOCUMENT | العقد مستند لا حالة في شاشة | `refused` | ✔ |
| 14 | التعاقد | توقيع العقد يربط العملية بسندها | `financing_operations` | FinancingCycleService::onContractSigned | contract_id = 9 | 9 | العملية تقرا سندها فلا تبقى التزامات بلا مستند | `W12:OPERATION_LINKED:587` | ✔ |
| 15 | التعاقد | بند تعاقدي بمرجع بنده في المستند | `fin_contract_term` | سجل البنود | بند بمرجعه | المعرف 9 | كل بند سطر يقرا ويراجع لا عمود مخترع | `binding` | ✔ |
| 16 | التعاقد | تنازل بلا مستند يرد في القاعدة | `fin_contract_covenant` | قيد chk_fcc_waiv | ترد الكتابة | ردت | التنازل عن التزام مستند من الممول لا قرار داخلي صامت | `refused` | ✔ |
| 17 | الإقفال التعاقدي | جدول اقساط العملية مولد | `financing_installments` | سجل الاقساط | قسطان بتاريخي استحقاقهما | المعرفان 3951 و 3952 | الاستحقاق يقرا من الجدول لا يكتب بيد | `scheduled` | ✔ |
| 18 | الإقفال التعاقدي | اقفال تعاقدي بلا رقم فترته يرد | `fin_contract_close` | FinancingCycleService::prepareContractClose | CLOSE_WITHOUT_CONTRACT_PERIOD | CLOSE_WITHOUT_CONTRACT_PERIOD | رقم الفترة التعاقدية هو ما يميز التعاقدي عن الشهري فلا يقفز | `refused` | ✔ |
| 19 | الإقفال التعاقدي | اقفال تعاقدي معد بفترته التعاقدية | `fin_contract_close` | FinancingCycleService::prepareContractClose | CONTRACTUAL | CONTRACTUAL | كيان مستقل بصنفه فلا يقرا شهريا ولا نهائيا | `prepared` | ✔ |
| 20 | الإقفال التعاقدي | رصيد افتتاحي لا يساوي ختامي السابقة يرد | `fin_contract_close` | FinancingCycleService::prepareContractClose | ROLLFORWARD_BROKEN | ROLLFORWARD_BROKEN | اختبار الترحيل حارس لا نص - فلا تنشا سلسلة ارصدة مكسورة | `refused` | ✔ |
| 21 | الإقفال التعاقدي | من اعد الاقفال التعاقدي لا يعتمده | `fin_contract_close` | FinancingCycleService::approveContractClose | SAME_ACTOR_PREPARE_AND_APPROVE_CLOSE | SAME_ACTOR_PREPARE_AND_APPROVE_CLOSE | الاعتماد بيد ثانية فلا يشهد المعد على عمله | `refused` | ✔ |
| 22 | الإقفال التعاقدي | اعتماد الاقفال يختم اقساط فترته | `financing_installments` | FinancingCycleService::onContractClosed | contract_close_id = 9 | 9 | القسط يقرا في اقفاله فلا يحسب في فترتين | `W12:PERIOD_INSTALLMENTS_SEALED:1` | ✔ |
| 23 | أمر الدفع | طبقة تاريخية لا تدخل نموذج امر الدفع | `fin_payment_order` | FinancingCycleService::requestPaymentOrder | LEGACY_AGGREGATE_AS_ORDER | LEGACY_AGGREGATE_AS_ORDER | تصميم المستقبل لا يخفض ليقبل ما لا يملكه الماضي | `refused` | ✔ |
| 24 | أمر الدفع | امر دفع مطلوب بطالبه وتاريخ طلبه | `fin_payment_order` | FinancingCycleService::requestPaymentOrder | FUTURE | FUTURE | اربعة حقول لا يملكها الصف المجمع: طالب وتاريخ ومبلغ وعملة | `requested` | ✔ |
| 25 | أمر الدفع | من طلب امر الدفع لا يعتمده | `fin_payment_order` | FinancingCycleService::approvePaymentOrder | SAME_ACTOR_REQUEST_AND_APPROVE_ORDER | SAME_ACTOR_REQUEST_AND_APPROVE_ORDER | الاعتماد سلطة لا اجراء | `refused` | ✔ |
| 26 | أمر الدفع | لا تنفيذ لامر غير معتمد | `fin_payment_order` | FinancingCycleService::executePaymentOrder | EXECUTE_WITHOUT_APPROVED_ORDER | EXECUTE_WITHOUT_APPROVED_ORDER | لا يخرج نقد بلا سلطة اذنت به | `refused` | ✔ |
| 27 | أمر الدفع | الاعتماد يفتح باب التنفيذ | `fin_payment_order` | FinancingCycleService::onOrderApproved | approved | approved | الامر المعتمد وحده ينفذ | `W12:ORDER_READY_TO_EXECUTE:50.00` | ✔ |
| 28 | أمر الدفع | لا تنفيذ بلا مرجع بنكي | `fin_payment_order` | FinancingCycleService::executePaymentOrder | EXECUTE_WITHOUT_BANK_REF | EXECUTE_WITHOUT_BANK_REF | الحركة النقدية تسند بمرجعها | `refused` | ✔ |
| 29 | أمر الدفع | التنفيذ يصدر طلب اعتراف الى المالية ولا يكتب قيدا | `acc_recognition_request` | AccountingCycleService::requestRecognition | financing | financing | قاعدة §48 - النطاق يطلب والمالية تقرر وتثبت | `pending` | ✔ |
| 30 | أمر الدفع | التنفيذ ينزل الرصيد القائم للعملية | `financing_operations` | FinancingCycleService::onOrderExecuted | ينقص بمقدار المنفذ | 100 ⇐ 50 | الرصيد مشتق من الحركة لا مكتوب بيد | `W12:OUTSTANDING_UPDATED:50` | ✔ |
| 31 | أمر الدفع | النطاق لم يكتب قيدا في دفتر المالية | `fin_journal_entries` | قاعدة §48 | صفر قيد من النطاق | 0 | باب الاعتراف واحد ولا يلتف عليه نطاق | `none` | ✔ |
| 32 | أمر الدفع | التخصيص يقفل القسط بتغطيته | `financing_installments` | FinancingCycleService::onPaymentAllocated | paid و 50 | paid و 50 | القسط يقرا سداده من التخصيص لا من دعوى | `W12:INSTALLMENTS_ALLOCATED:1` | ✔ |
| 33 | الطبقة التاريخية | صف تاريخي بلا حجية يرد | `fin_legacy_payment_aggregate` | FinancingCycleService::ingestLegacyAggregate | LEGACY_WITHOUT_EVIDENCE_GRADE | LEGACY_WITHOUT_EVIDENCE_GRADE | رقم تاريخي بلا سند دعوى لا حقيقة | `refused` | ✔ |
| 34 | الطبقة التاريخية | الصف التاريخي يدخل بطبقته موسوما بحجيته | `fin_legacy_payment_aggregate` | FinancingCycleService::ingestLegacyAggregate | LEGACY وغير قابل للتخصيص | LEGACY و 0 | التاريخي يقرا موسوما ولا يخلط بنموذج المستقبل | `legacy` | ✔ |
| 35 | الطبقة التاريخية | لا صف تاريخي في جدول نموذج المستقبل | `fin_payment_order` | قيد chk_fpo_future | صفر | 0 | الطبقتان لا تختلطان في جدول واحد فلا يخفض النموذج | `separated` | ✔ |
| 36 | الطبقة التاريخية | تخصيص بلا امر دفع يرد في القاعدة | `fin_payment_allocation` | قيد chk_fpa_order | ترد الكتابة | ردت | المجمع لا يخصص كامر فلا يوهم بسداد مفصل لا وجود له | `refused` | ✔ |
| 37 | الطبقة التاريخية | لا قدرة في النموذج خفضت لتناسب التاريخي | `repair01_w12_layers` | دفتر الطبقتين | صفر تخفيض وصفر عمود يقبل العدم | 0 و 0 | تصميم المستقبل مستقل عن محدودية البيانات التاريخية | `independent` | ✔ |
| 38 | الإقفال الشهري | شهر ليس تقويميا يرد | `fin_monthly_close` | FinancingCycleService::prepareMonthlyClose | MONTH_NOT_CALENDAR | MONTH_NOT_CALENDAR | وعاء الشهر لا يقبل فترة تعاقدية فلا يخدم معنيين | `refused` | ✔ |
| 39 | الإقفال الشهري | شهر لا يبدا باوله يرد في القاعدة | `fin_monthly_close` | قيد chk_fmc_month | ترد الكتابة | ردت | الحبة تفرض في القاعدة لا في الخدمة وحدها | `refused` | ✔ |
| 40 | الإقفال الشهري | اقفال شهري معد بشهره التقويمي | `fin_monthly_close` | FinancingCycleService::prepareMonthlyClose | MONTHLY | MONTHLY | كيان ثان بصنفه ومفتاحه لا حالة للتعاقدي | `prepared` | ✔ |
| 41 | الإقفال الشهري | شهري بلا تعاقدي مربوط لا يعتمد | `fin_monthly_close` | FinancingCycleService::approveMonthlyClose | MONTHLY_WITHOUT_CONTRACT_CLOSE | MONTHLY_WITHOUT_CONTRACT_CLOSE | الشهر يضم اقفالات فتراته ولا ينشئ معناها وحده | `refused` | ✔ |
| 42 | الإقفال الشهري | الشهري يضم التعاقدي بربط لا باحلال | `fin_close_link` | FinancingCycleService::linkContractCloseToMonth | رابط واحد | 1 | الكيانان يبقيان متمايزين والعلاقة صف مستقل | `linked` | ✔ |
| 43 | الإقفال الشهري | رابط من صنف الى صنفه نفسه يرد | `fin_close_link` | قيد chk_fcl_self | ترد الكتابة | ردت | هذا عين اقفال يخدم معنيين فيمنع في القاعدة | `refused` | ✔ |
| 44 | الإقفال الشهري | من اعد الشهري لا يعتمده | `fin_monthly_close` | FinancingCycleService::approveMonthlyClose | SAME_ACTOR_PREPARE_AND_APPROVE_MONTHLY | SAME_ACTOR_PREPARE_AND_APPROVE_MONTHLY | فصل الواجبات في الشهري كما في التعاقدي | `refused` | ✔ |
| 45 | الإقفال الشهري | الشهري يعلن عدد تعاقدياته المضمومة | `fin_monthly_close` | FinancingCycleService::onMonthlyClosed | 1 | 1 | الرقم مشتق من الربط فيقرا الشهر ضما لا كيانا بديلا | `W12:MONTH_AGGREGATED:1` | ✔ |
| 46 | الإقفال النهائي | انحراف مرفوع يحجب الاقفال النهائي | `financing_deviations` | FinancingCycleService::onDeviationRaised | انحراف حاجب مفتوح | W12:BLOCKING_DEVIATIONS:198 | الحجب مقيس من الانحرافات لا مدعى | `open` | ✔ |
| 47 | الإقفال النهائي | طلب اقفال نهائي يقيس موقف العملية | `fin_final_close` | FinancingCycleService::requestFinalClose | FINAL بموقف مقيس | FINAL · انحرافات 1 · استحقاقات 1 | كيان ثالث بصنفه ومفتاحه ويقرا اخر دوري مرجعا | `requested` | ✔ |
| 48 | الإقفال النهائي | الاقفال النهائي مرة واحدة لعملية | `fin_final_close` | FinancingCycleService::requestFinalClose | FINAL_CLOSE_ALREADY_EXISTS | FINAL_CLOSE_ALREADY_EXISTS | شهادة الاقفال واقعة لا رقم يتكرر | `refused` | ✔ |
| 49 | الإقفال النهائي | لا اقفال نهائي باستحقاق مفتوح | `fin_final_close` | FinancingCycleService::approveFinalClose | FINAL_CLOSE_WITH_OPEN_DUES | FINAL_CLOSE_WITH_OPEN_DUES | اخلاء طرف على ذمة قائمة نقض للشهادة | `refused` | ✔ |
| 50 | الإقفال النهائي | من رفع الانحراف لا يحسمه | `financing_deviations` | FinancingCycleService::resolveDeviation | SAME_ACTOR_RAISE_AND_RESOLVE_DEVIATION | SAME_ACTOR_RAISE_AND_RESOLVE_DEVIATION | الحسم شهادة على عمل غيره | `refused` | ✔ |
| 51 | الإقفال النهائي | الانحراف يحسم بقرار مكتوب من يد ثانية | `financing_deviations` | FinancingCycleService::resolveDeviation | closed | closed | الحجب يرفع بحسم موثق لا بتجاهل | `closed` | ✔ |
| 52 | الإقفال النهائي | موقف العملية يعاد قياسه بعد الاستيفاء | `fin_final_close` | قياس حي | صفر استحقاق وصفر انحراف حاجب | 0 و 0 | البوابة تقيس ولا تقرا ما خزنته | `reviewed` | ✔ |
| 53 | الإقفال النهائي | من طلب الاقفال النهائي لا يعتمده | `fin_final_close` | FinancingCycleService::approveFinalClose | SAME_ACTOR_PREPARE_AND_APPROVE_FINAL | SAME_ACTOR_PREPARE_AND_APPROVE_FINAL | الاعتماد سلطة بسقفها | `refused` | ✔ |
| 54 | الإقفال النهائي | الاقفال النهائي يقفل العملية ويربطها بختامها | `financing_operations` | FinancingCycleService::onFinalClosed | final_close_id = 9 و closed | 9 و closed | العملية تقرا ختامها فلا يبقى عقد مقفل بحالة سارية | `W12:OPERATION_CLOSED:587` | ✔ |
| 55 | الإقفال النهائي | انتقال الملكية يوسم في الاقفال النهائي بمستنده | `fin_final_close` | FinancingCycleService::onOwnershipTransferred | 1 | 1 | حكم الملكية جزء من الاقفال لا واقعة منفصلة عنه | `W12:OWNERSHIP_SEALED:9` | ✔ |
| 56 | الفصل مقيس | ثلاثة اصناف اقفال متمايزة في جولة واحدة | `fin_contract_close` | قراءة منفصلة | 3 | 3 | كل صنف يقرا من جدوله بمفتاحه فلا يخلط رقم بمعنى غيره | `separated` | ✔ |
| 57 | الفصل مقيس | صفر اقفال يخدم معنيين | `fin_close_consumption` | repair01_w12_close_dual_role | 0 | 0 | المحور الاول للمرحلة مقيس على خمس جبهات | `measured` | ✔ |
| 58 | الفصل مقيس | صفر تصميم مقيد ببيانات تاريخية | `repair01_w12_layers` | repair01_w12_design_constrained | 0 | 0 | المحور الثاني للمرحلة مقيس على خمس جبهات | `measured` | ✔ |
| 59 | الفصل مقيس | كل حدث في النطاق له مستهلك نشط بالاسم | `event_consumers` | الجذر المحايد | مشتركون بعدد العقود | 10 مقابل 10 | حدث بلا مشترك نشط يرفض في الجذر نفسه | `registered` | ✔ |

---

**المقيس:** محطّاتٌ **59/59** · أشواطٌ **9** · مستهلكونَ متمايزون **46** · بلا أثرٍ تجاريٍّ **0** · أثرٌ باقٍ **0**.
