# RPR-W11 — دليلُ عبورِ رحلةِ الإقفال

> ⛔ **مولَّدٌ من المخزن — لا تحرّره يدويًّا**: `php tools/repair01_w11_docs.php` يعيد كتابتَه.

**الجولة:** `W11J-20260826141647546782`

**طلبُ اعترافٍ من نطاقٍ مصدريّ ← الماليّةُ تقرّر وتثبّت القيد ← ترحيلٌ إلى الأستاذ ← تسويات ← مطابقةٌ بنكيّة ← ميزانُ مراجعة ← قائمةُ فحصِ الإقفال ← إقفالُ الفترة ← قوائمُ مالية — لكيانٍ قانونيٍّ واحدٍ من طرفٍ إلى طرف.**

◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عمودُ «الأثرُ التجاريُّ المقيس» هو الحكم، لا عمودُ «المقيس».


## الشوط · `RECOGNITION`

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | الأثرُ التجاريُّ المقيس | الحالةُ بعدها | ✔ |
|---:|---|---|---|---|---|---|---|:-:|
| 1 | المالية لا تصدر لنفسها طلب اعتراف | `acc_recognition_request` | AccountingCycleService | SCOPE_WRITES_ENTRY | SCOPE_WRITES_ENTRY | **صفر طلب اعتراف مصدره المالية** | مردود | ✔ |
| 2 | نطاق مصدري يصدر طلب اعتراف بواقعته | `acc_recognition_request` | المشتريات | طلب واحد بمرجع واقعته | request_id 921 | **طلب اعتراف قائم بمبلغه وعملته** | قيد الدراسة | ✔ |
| 3 | المستهلك يحل فترة الطلب من تقويم الفترات | `acc_recognition_request` | AccountingCycleService::onRecognitionRequested | period_code غير فارغ | الرد W11:PERIOD_RESOLVED:30 | **الطلب صار في فترة معرفة: 30** | قيد الدراسة | ✔ |
| 4 | من طلب الاعتراف لا يقرره | `acc_recognition_request` | AccountingCycleService | SAME_ACTOR_REQUEST_AND_DECIDE | SAME_ACTOR_REQUEST_AND_DECIDE | **صفر طلب قرره طالبه** | قيد الدراسة | ✔ |
| 5 | الرد بلا سبب مكتوب لا يقع | `acc_recognition_request` | AccountingCycleService | REJECT_WITHOUT_REASON | REJECT_WITHOUT_REASON | **صفر رد بلا سبب** | قيد الدراسة | ✔ |
| 6 | المالية تقرر القبول | `acc_recognition_request` | مدير المالية | الحالة مقبول | OK accepted | **قرار قبول مسجل بقراره وصاحبه** | مقبول | ✔ |
| 7 | المستهلك ينشئ واقعة الاعتراف المالية عند القبول | `fin_financial_events` | AccountingCycleService::onRecognitionDecided | event_id أكبر من صفر | الرد W11:FACT_CREATED:18484 | **واقعة اعتراف مالية قائمة رقم 18484** | مقبول | ✔ |
| 8 | الرد لا ينشئ واقعة اعتراف | `fin_financial_events` | AccountingCycleService::onRecognitionDecided | W11:REJECTED_NO_FACT | الرد W11:REJECTED_NO_FACT | **صفر واقعة عن طلب مردود (event_id 0)** | مردود | ✔ |
| 9 | لا قيد على طلب لم يقبل | `fin_journal_entries` | AccountingCycleService | POST_WITHOUT_ACCEPTED_REQUEST | POST_WITHOUT_ACCEPTED_REQUEST | **صفر قيد على طلب مردود** | مردود | ✔ |
| 10 | القيد لا يمر بلا توازن | `fin_journal_entries` | AccountingCycleService | ENTRY_UNBALANCED | ENTRY_UNBALANCED | **صفر قيد مرحل غير متوازن** | مقبول | ✔ |
| 11 | من قرر الاعتراف لا يرحل قيده | `fin_journal_entries` | AccountingCycleService | SAME_ACTOR_PREPARE_AND_POST | SAME_ACTOR_PREPARE_AND_POST | **صفر قيد رحله من قرره** | مقبول | ✔ |
| 12 | القيد يثبت متوازنا في فترة مفتوحة | `fin_journal_entries` | المحاسب | مدين يساوي دائن وسطران | مدين 1500 دائن 1500 اسطر 2 | **قيد مرحل في دفتر الاستاذ رقم 10718** | مرحل | ✔ |
| 13 | الخيط يعود من القيد الى واقعته | `acc_recognition_request` | FinRequests/effect_map.php | journal_entry_id يساوي القيد | الرابط 10718 | **كل قيد يرد الى واقعته وكل واقعة الى قيدها** | مرحل | ✔ |
| 14 | القيد يحرك التعرض الائتماني للعميل | `acc_credit_limit` | AccountingCycleService::onEntryPosted | التعرض يساوي الذمم القائمة | الرد W11:EXPOSURE_REFRESHED:1 والتعرض 2000 | **اتاحة العميل تقرا من ذممه القائمة: 2000** | مرحل | ✔ |
| 15 | البيع فوق الحد الائتماني يحجب | `acc_credit_limit` | AccountingCycleService | CREDIT_LIMIT_EXCEEDED | CREDIT_LIMIT_EXCEEDED | **صفر عملية تتجاوز الحد بلا اعتماد** | مرحل | ✔ |

## الشوط · `ADJUSTMENT`

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | الأثرُ التجاريُّ المقيس | الحالةُ بعدها | ✔ |
|---:|---|---|---|---|---|---|---|:-:|
| 16 | تسوية استحقاق تعد بمستند اساسها | `acc_period_adjustment` | المحاسب | صف تسوية بمستند وسبب | adjustment_id 307 | **قيد تسوية معد بمستنده** | مسودة | ✔ |
| 17 | من اعد التسوية لا يعتمدها | `acc_period_adjustment` | AccountingCycleService | SAME_ACTOR_PREPARE_AND_APPROVE_ADJ | SAME_ACTOR_PREPARE_AND_APPROVE_ADJ | **صفر تسوية اعتمدها معدها** | مسودة | ✔ |
| 18 | التسوية ترحل باعتماد غير معدها | `acc_period_adjustment` | مدير المالية | الحالة مرحل | OK والحالة posted | **تسوية مرحلة في فترتها** | مرحل | ✔ |
| 19 | المستهلك يغلق بند ترحيل التسويات في قائمة الاقفال | `fin_closing_items` | AccountingCycleService::onAdjustmentPosted | حالة البند مكتمل | الرد W11:CHECKLIST_DONE:post_accruals:1 والحالة done | **شرط اقفال تقدم خطوة واحدة** | مرحل | ✔ |

## الشوط · `RECONCILIATION`

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | الأثرُ التجاريُّ المقيس | الحالةُ بعدها | ✔ |
|---:|---|---|---|---|---|---|---|:-:|
| 20 | جلسة مطابقة حساب رقابي تفتح بفرقها المشتق | `acc_account_recon` | المحاسب | الفرق مشتق لا مكتوب | recon_id 312 والفرق 150 | **فرق مقيس بين الدفتر والمصدر التفصيلي** | مفتوح | ✔ |
| 21 | فرق بلا مسؤول ولا اجراء لا يقيد | `acc_account_recon_line` | AccountingCycleService | DIFFERENCE_WITHOUT_OWNER | DIFFERENCE_WITHOUT_OWNER | **صفر فرق مدفون بلا مسؤول** | مفتوح | ✔ |
| 22 | بند الفرق يقيد بنوعه وسببه ومسؤوله واجرائه | `acc_account_recon_line` | المحاسب | عداد الفروق المفتوحة يساوي واحدا | line_id 311 والمفتوح 1 | **فرق مسمى بمسؤوله لا رقم في حقل** | مفتوح | ✔ |
| 23 | لا تقفل مطابقة وفيها فرق مفتوح | `acc_account_recon` | AccountingCycleService | RECON_CLOSE_WITH_OPEN_DIFF | RECON_CLOSE_WITH_OPEN_DIFF | **صفر جلسة مقفلة بفرق مفتوح** | مفتوح | ✔ |
| 24 | من اعد المطابقة لا يقفلها | `acc_account_recon` | AccountingCycleService | SAME_ACTOR_PREPARE_AND_CLOSE_RECON | SAME_ACTOR_PREPARE_AND_CLOSE_RECON | **صفر جلسة اقفلها معدها** | مفتوح | ✔ |
| 25 | المطابقة تقفل بصفر فرق مفتوح | `acc_account_recon` | مدير المالية | الحالة مقفل | OK والحالة closed | **حساب رقابي مطابق ومقفل** | مقفل | ✔ |
| 26 | المستهلك يغلق بند مطابقة ذمم العملاء | `fin_closing_items` | AccountingCycleService::onAccountReconciled | حالة البند مكتمل | الرد W11:CHECKLIST_DONE:reconcile_ar:1 والحالة done | **شرط اقفال تقدم خطوة ثانية** | مقفل | ✔ |

## الشوط · `TREASURY`

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | الأثرُ التجاريُّ المقيس | الحالةُ بعدها | ✔ |
|---:|---|---|---|---|---|---|---|:-:|
| 27 | سند قبض بلا مرجع بنكي لا يقبل | `fin_payments` | TreasuryCycleService | RECEIPT_WITHOUT_BANK_REF | RECEIPT_WITHOUT_BANK_REF | **صفر سند قبض بلا مرجع موثق** | مردود | ✔ |
| 28 | سند قبض يسجل بمبلغه وعملته | `fin_payments` | أمين الخزينة | سند قبض واحد | receipt_id 7560 | **نقد وارد مسجل بسنده** | معتمد | ✔ |
| 29 | التخصيص لا يتجاوز مبلغ السند | `fin_collection_allocations` | TreasuryCycleService | ALLOCATION_EXCEEDS_RECEIPT | ALLOCATION_EXCEEDS_RECEIPT | **صفر تخصيص يتجاوز سنده** | معتمد | ✔ |
| 30 | التخصيص يقيد سطرا لكل فاتورة | `fin_collection_allocations` | أمين الخزينة | سطر تخصيص واحد | OK اسطر 1 | **دفعة موزعة على فواتيرها بسطورها** | معتمد | ✔ |
| 31 | المستهلك يحدث المحصل والمتبقي وحالة الذمة | `fin_receivables` | TreasuryCycleService::onReceiptAllocated | المتبقي 800 والحالة جزئية | الرد W11:RECEIVABLES_UPDATED:1 والمتبقي 800 والحالة partial | **ذمة العميل انخفضت بالتحصيل فعلا** | جزئي | ✔ |
| 32 | لا دفع لمستفيد غير محقق | `tre_beneficiaries` | TreasuryCycleService | BENEFICIARY_NOT_VERIFIED | BENEFICIARY_NOT_VERIFIED | **صفر دفعة لمستفيد غير محقق** | معتمد | ✔ |
| 33 | التحقق بلا مصدر توثيق لا يقع | `tre_beneficiaries` | TreasuryCycleService | VERIFY_WITHOUT_DOC | VERIFY_WITHOUT_DOC | **صفر مستفيد محقق بلا مستند** | غير محقق | ✔ |
| 34 | التحقق يقفل الحساب البنكي ضد التعديل | `tre_beneficiaries` | مدير المالية | locked_at غير فارغ | القفل واقع | **حساب مستفيد موثق ومقفل ضد التبديل الصامت** | محقق | ✔ |
| 35 | الحساب المقفل لا يبدل بلا توثيق جديد | `tre_beneficiaries` | TreasuryCycleService | BENEFICIARY_ACCOUNT_LOCKED | BENEFICIARY_ACCOUNT_LOCKED | **صفر تبديل صامت لحساب محقق** | محقق | ✔ |
| 36 | من اعد الدفعة لا ينفذها | `fin_payments` | TreasuryCycleService | SAME_ACTOR_PREPARE_AND_EXECUTE | SAME_ACTOR_PREPARE_AND_EXECUTE | **صفر دفعة نفذها معدها** | معتمد | ✔ |
| 37 | التنفيذ يخرج نقدا من وعائه | `tre_cash_move` | TreasuryCycleService::onPaymentExecuted | حركة خروج واحدة | الرد W11:CASH_OUT:607 وحركات الخروج 1 | **رصيد الوعاء انخفض بالتنفيذ فعلا** | منفذ | ✔ |
| 38 | فرق بنكي بلا مسؤول ولا اجراء لا يقيد | `tre_recon_difference` | TreasuryCycleService | DIFFERENCE_WITHOUT_OWNER | DIFFERENCE_WITHOUT_OWNER | **صفر فرق بنكي مدفون** | قيد المطابقة | ✔ |
| 39 | بند فرق المطابقة البنكية يقيد بمسؤوله واجرائه | `tre_recon_difference` | أمين الخزينة | عداد الفروق المفتوحة واحد | difference_id 304 والعداد 1 | **فرق بنكي مسمى بمسؤوله حتى الاغلاق** | مفتوح | ✔ |
| 40 | لا يقفل كشف وفيه فرق مفتوح | `bank_statements` | TreasuryCycleService | BANK_CLOSE_WITH_OPEN_DIFF | BANK_CLOSE_WITH_OPEN_DIFF | **صفر كشف مقفل بفرق مفتوح** | قيد المطابقة | ✔ |
| 41 | من اعد المطابقة البنكية لا يراجعها | `bank_statements` | TreasuryCycleService | SAME_ACTOR_PREPARE_AND_REVIEW_BANK | SAME_ACTOR_PREPARE_AND_REVIEW_BANK | **صفر كشف راجعه معده** | قيد المطابقة | ✔ |
| 42 | المطابقة تقفل والمستهلك يغلق بندها في قائمة الاقفال | `fin_closing_items` | TreasuryCycleService::onBankReconciled | حالة البند مكتمل | OK والرد W11:CHECKLIST_DONE:reconcile_bank:1 والحالة done | **شرط اقفال تقدم خطوة ثالثة** | مقفل | ✔ |
| 43 | الجرد بلجنة لا بامين الصندوق وحده | `tre_cash_count` | TreasuryCycleService | COUNT_WITHOUT_COMMITTEE | COUNT_WITHOUT_COMMITTEE | **صفر جرد بعضو واحد** | مسودة | ✔ |
| 44 | الجرد يفتح ورصيده الدفتري مشتق من الحركات | `tre_cash_count` | لجنة الجرد | الفرق مشتق | count_id 304 والفرق -50 | **عجز مقيس بين الدفتري والمعدود** | مسودة | ✔ |
| 45 | من عد لا يعتمد جرده | `tre_cash_count` | TreasuryCycleService | SAME_ACTOR_COUNT_AND_APPROVE | SAME_ACTOR_COUNT_AND_APPROVE | **صفر جرد اعتمده عاده** | مسودة | ✔ |
| 46 | فرق الجرد لا يمر بلا معالجة مكتوبة | `tre_cash_count` | TreasuryCycleService | COUNT_DIFF_WITHOUT_ACTION | COUNT_DIFF_WITHOUT_ACTION | **صفر فرق مدفون بلا معالجة** | مسودة | ✔ |
| 47 | المستهلك يحول فرق الجرد الى حركة تسوية مسماة | `tre_cash_move` | TreasuryCycleService::onCashCountApproved | حركة تسوية واحدة | الرد W11:COUNT_ADJUSTED:608 وحركات التسوية 1 | **الرصيد الدفتري صار يطابق المعدود بحركة مسماة** | معتمد | ✔ |

## الشوط · `CLOSE`

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | الأثرُ التجاريُّ المقيس | الحالةُ بعدها | ✔ |
|---:|---|---|---|---|---|---|---|:-:|
| 48 | لا اقفال بلا جولة ميزان | `fin_financial_periods` | AccountingCycleService | CLOSE_WITHOUT_TRIAL_BALANCE | CLOSE_WITHOUT_TRIAL_BALANCE | **صفر فترة مقفلة بلا ميزان** | مفتوح | ✔ |
| 49 | جولة ميزان تشتق من القيود المنشورة | `acc_trial_balance_run` | المحاسب | متوازن واسطر مشتقة | run_id 304 متوازن 1 اسطر 2 | **لقطة ميزان بزمنها يستند اليها الاقفال** | متوازن | ✔ |
| 50 | المستهلك يغلق بند مراجعة الفروق عند التوازن | `fin_closing_items` | AccountingCycleService::onTrialBalanced | حالة البند مكتمل | الرد W11:CHECKLIST_DONE:variance_reviewed:1 والحالة done | **شرط اقفال تقدم خطوة رابعة** | متوازن | ✔ |
| 51 | لا اقفال وفي القائمة بند حاجب | `fin_closing_items` | AccountingCycleService | CLOSE_WITH_BLOCKING_ITEM | CLOSE_WITH_BLOCKING_ITEM والحاجب 1 | **صفر فترة مقفلة ببند ناقص** | مفتوح | ✔ |
| 52 | الاستثناء بلا قرار مكتوب لا يقع | `fin_closing_items` | AccountingCycleService | EXCEPTION_WITHOUT_REASON | EXCEPTION_WITHOUT_REASON | **صفر استثناء بلا قرار** | مفتوح | ✔ |
| 53 | توثيق الاستثناء يرفع الحجب لا يمحو البند | `fin_closing_items` | مدير المالية | الحاجب صفر والبند قائم | الحاجب بعد التوثيق 0 | **بند ناقص مر باستثناء موثق لا بحذف** | مفتوح | ✔ |
| 54 | من اجرى الميزان لا يقفل الفترة | `fin_financial_periods` | AccountingCycleService | SAME_ACTOR_PREPARE_AND_CLOSE | SAME_ACTOR_PREPARE_AND_CLOSE | **صفر فترة اقفلها من اجرى ميزانها** | مفتوح | ✔ |
| 55 | لا قوائم قبل الاقفال | `fin_financial_periods` | AccountingCycleService | STATEMENTS_BEFORE_CLOSE | STATEMENTS_BEFORE_CLOSE | **صفر قائمة صدرت قبل اقفال فترتها** | مفتوح | ✔ |
| 56 | الاقفال يقع والمستهلك يمنع الترحيل | `fin_financial_periods` | AccountingCycleService::onPeriodClosed | posting_allowed صفر | OK والرد W11:POSTING_BLOCKED:0 والسماح 0 | **الفترة صارت لا تقبل قيدا — اثبات لا اعلان** | مقفل | ✔ |
| 57 | لا قيد على فترة مقفلة | `fin_journal_entries` | AccountingCycleService | POST_TO_CLOSED_PERIOD | POST_TO_CLOSED_PERIOD | **صفر قيد دخل فترة مقفلة** | مقفل | ✔ |
| 58 | القوائم تشتق بعد الاقفال من ميزانه | `fin_financial_periods` | AccountingCycleService::onStatementsIssued | حالة بند التقارير مكتمل | OK والرد W11:CHECKLIST_DONE:reports_issued:1 والحالة done | **قوائم صادرة عن ميزان مقفل متوازن** | مقفل | ✔ |

## الشوط · `REOPEN`

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | الأثرُ التجاريُّ المقيس | الحالةُ بعدها | ✔ |
|---:|---|---|---|---|---|---|---|:-:|
| 59 | طلب اعادة فتح بلا مبرر ولا قاعدة ولا نطاق يرد | `acc_period_reopen_request` | AccountingCycleService | REOPEN_WITHOUT_AUTHORITY | REOPEN_WITHOUT_AUTHORITY | **صفر طلب اعادة فتح بلا حوكمة** | مقفل | ✔ |
| 60 | طلب اعادة فتح بمبرره ونطاقه وقاعدته | `acc_period_reopen_request` | المحاسب | طلب واحد معلق | reopen_id 309 | **استثناء محكوم مسجل لا فعل صامت** | قيد الدراسة | ✔ |
| 61 | من طلب اعادة الفتح لا يعتمدها | `acc_period_reopen_request` | AccountingCycleService | SAME_ACTOR_REQUEST_AND_APPROVE_REOPEN | SAME_ACTOR_REQUEST_AND_APPROVE_REOPEN | **صفر طلب اعتمده طالبه** | قيد الدراسة | ✔ |
| 62 | الاعتماد يعيد الفتح والمستهلك يعيد السماح بالترحيل | `fin_financial_periods` | AccountingCycleService::onPeriodReopened | posting_allowed واحد | OK والرد W11:POSTING_ALLOWED:1 والسماح 1 والحالة applied | **الترحيل عاد في النطاق المعتمد وحده** | مطبق | ✔ |

## الشوط · `ENTITY`

| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | الأثرُ التجاريُّ المقيس | الحالةُ بعدها | ✔ |
|---:|---|---|---|---|---|---|---|:-:|
| 63 | رقم يخلط كيانين بلا وسم يرفض | `repair01_w11_consolidated` | AccountingCycleService | CROSS_ENTITY_UNTAGGED | CROSS_ENTITY_UNTAGGED | **صفر رقم مختلط غير موسوم** | مرفوض | ✔ |
| 64 | الرقم المجمع الموسوم يمر بوسمه | `repair01_w11_consolidated` | مدير المالية | الوسم GROUP_PROJECTION | OK GROUP_PROJECTION | **رقم مجموعة معلن بوسمه لا يقرا رقم كيان** | موسوم | ✔ |
| 65 | رقم الكيان الواحد يمر بلا وسم | `repair01_w11_consolidated` | مدير المالية | الوسم SINGLE_ENTITY | OK SINGLE_ENTITY | **كل رقم في الرحلة لكيان واحد من طرف الى طرف** | كيان واحد | ✔ |
| 66 | القيد يحمل كيانه ووسم نطاقه | `fin_journal_entries` | مدير المالية | كيان الرحلة ووسم كيان واحد | company_id 4 ووسم SINGLE_ENTITY | **لا قيد بلا كيان ولا رقم يخلط كيانين صامتا** | مرحل | ✔ |
| 67 | الجذر يرفض النشر لحدث بلا مشترك نشط | `event_consumers` | الجذر المحايد | مشتركون نشطون في الطرفين | محاسبة 9 وخزينة 4 | **كل حدث في النطاق له مستهلك نشط بالاسم** | مسجل | ✔ |

---

**المقيس:** محطّات **67/67** · أشواط **7** · مستهلكونَ متمايزون **22** · كياناتٌ قانونيّة **1** · أثرٌ باقٍ بعد الكنس **0**.
