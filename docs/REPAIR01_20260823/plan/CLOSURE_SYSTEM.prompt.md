# أمرُ الإغلاقِ والتنفيذِ المعماريِّ الموحَّد — **الوثائقُ الأربعُ**

> `MASTER FINAL CLOSURE REGISTER` · `EXECUTION SPRINT-01` ·
> `LEGACY REQUIREMENT DISPOSITION REGISTER` · `OWNER ACTION REGISTER`

> **اقرأ هذا الملفَّ كاملًا قبل أن تكتب سطرًا واحدًا.**
> **والمصمَّمُ الحاكم:** [`orders/CLOSURE_SYSTEM.txt`](../orders/CLOSURE_SYSTEM.txt)
> — «أمرُ الإغلاقِ والتنفيذِ المعماريِّ الموحَّد · النسخةُ المصحَّحةُ الشاملة».
> **وهو لا يستبدل المعماريّةَ السابقةَ — بل يحوّلها إلى نظامِ إغلاقٍ مستمرّ.**

---

# ⛔⛔⛔ ٠ · القاعدةُ العليا — التقاريرُ مراحلُ لا نقاطُ توقّف

**نصُّ الأمر:** *«`Milestone Reports` وليست نقاطَ توقّف. **وبعد كلِّ تقريرٍ يستمرّ
العملُ تلقائيًّا في كلِّ بندٍ غيرِ محجوب**»* · *«**ولا ينتظر المنفِّذُ سؤال: هل
أكمل؟**»* · *«**استمرَّ دون انتظارِ رسالةٍ جديدة**»*.

### ٠·١ عباراتٌ محظورةٌ حرفيًّا

```
⛔ «هل أتابع؟»                       ⛔ «هل أكمل؟»
⛔ «انتهيتُ من WORK-0X — أستمرّ؟»     ⛔ «أنتظر توجيهك»
⛔ «أصدرتُ التقرير — هل أبدأ التالي؟»
⛔ «الوقتُ طال — هل أكمل في جلسةٍ أخرى؟»
⛔ «هذا يحتاج قرارًا منك» — **إلّا لما يستوفي §٤ حرفًا**
```

**والبديلُ الوحيد:** سطرُ تقدُّمٍ واحدٌ **ثمَّ البندُ التاليَ في الرسالةِ نفسِها.**

### ٠·٢ سلسلةُ الحالةِ الملزِمة

```
DECISION → IMPLEMENTATION → WIRING → EXERCISE → EVIDENCE → CLOSURE → CONTINUE
```

### ٠·٣ ثمانيةُ نفيٍ — ⛔ **ولا يُعتبر أيٌّ منها إغلاقًا**

| ⛔ لا يُعتبر | لأنَّ |
|---|---|
| **وجودُ الكودِ** = إغلاقًا | — |
| **وجودُ الشاشةِ** = قبولًا | — |
| **`Wiring`** = `Verification` | — |
| **`Registry` صحيحٌ** = `UI` صحيح | — |
| **`Consumer` موصولٌ** = أثرُ أعمالٍ مثبَت | — |
| **`Ladder` يقرأ** = `Approval Enforcement` | — |
| **`Backup` قديمٌ** = صالحًا للمخطَّطِ الحاليّ | — |
| **`Report` مُرسَلٌ** = `Stop Signal` | — |

### ٠·٤ والحاجز

**«الحاجزُ يمنع البندَ أو الهدفَ أو النطاقَ المتأثِّرَ فقط، ولا يوقف البرنامجَ
كلَّه.»**

### ٠·٥ وإن نفد السياق

⛔ **لا تسأل** — **حدِّثِ `MASTER_FINAL_CLOSURE_REGISTER` والتزمْ ثمَّ استأنفْ
منه.** ⛔ **ولا من ذاكرةِ المبرمجِ ولا من آخرِ رسالة.**

---

# ١ · أين أنت — مقيسٌ حيًّا على `52f4fe37`

| المفردة | القيمة |
|---|---|
| الجذر · PHP | `C:\wamp64\www\ems` · `/c/wamp64/bin/php/php8.3.28/php.exe` |
| الفرع | `repair01/w01-ownership` |
| **`origin`** | `git@github.com:mohamed-sd/ems.git` |
| **upstream** | `origin/repair01/w01-ownership` ✔ **معرَّف** |
| ⛔ **محليٌّ متقدِّمٌ عن البعيد** | **٢٢٦ التزامًا** (‏`LOCAL=52f4fe37` · `REMOTE=96a640e1`) |
| تقدُّمٌ عن `origin/main` | **٢٩٦** |
| الوسوم | **٢٠** |
| الشجرة | **٤٠ بندًا غيرَ ملتزم** |

**واللوحاتُ:** `RPR-02` **١٢/١٦** · `RPR-03` **١٥/١٩** · **مغلقٌ بالدليل ٢٤٤**
· بلا إثبات **١٣** · غيرُ منفَّذ **١٧٦** · بلا نوع **٢٦**.

> ⚠ **وأرقامُ الأمرِ نفسِه تحتاج إعادةَ قياس** — فبعضُها من لقطةٍ أقدم:
> يقول *«‏٢٣/٥٨ موصولة»* و*«`cron_events.php` غيرُ مجدول»*، **والمقيسُ اليومَ**:
> ‏**٥٨ نوعًا · ٤ مستهلكين · صندوق ٢١٬٥٥٦ · فاشل ٢٧** · **و`cron_events_task.php`
> أُنشئ في `b82bf7f3`**. ⛔ **فأعِدْ قياسَ كلِّ رقمٍ قبل أن تبني عليه.**

---

# ٢ · `WORK-00` — تأسيسُ السجلِّ الجامع

**أنشئ `MASTER_FINAL_CLOSURE_REGISTER`** — ⛔ **ولا يتحوّل إلى مشروعٍ مستقلٍّ ولا
يوقف `Sprint-01`.**

**يُستورَد فيه:** `GAP-01..77` · سجلُّ `FR-*` · أهدافُ `RPR-02` · مقاييسُ
`RPR-03` · `Owner Actions` الحالية · `Legacy obligations` المعروفة.

**الحقولُ الإلزاميّةُ لكلِّ `Closure Item`:**
`Closure_ID` · `Source_Document` · `Source_ID` · `Domain` · `Target_ID` ·
`Requirement_ID` · `Current_Status` · `Priority` · `Execution_Rank` ·
`Severity` · `Release_Impact` · `Applicability` · `Business_Owner` ·
`Technical_Owner` · `Blocker_Class` · `Required_By_Gate` · `Depends_On` ·
`Blocks` · `Dependency_Type` · `Dependency_Fanout` · `Unlock_Value` ·
`Estimated_Effort` · `Evidence_Contract` · `Next_Action` · `Current_Snapshot` ·
`Last_Updated_Snapshot` · `Last_Evidence_Ref` · `Status_Changed_At` ·
`Final_Disposition`.

⛔ **ولا يُستخدَم كقائمةِ مهامٍّ يوميّة** — بل **مرجعًا دائمًا يجيب عن ثمانية:**
ما الذي بقي؟ · لماذا بقي؟ · ما مصدرُه الحاكم؟ · **أهو خطأٌ أم دَينٌ تقنيٌّ أم
قرارُ مالكٍ أم `UAT`؟** · ما الذي يعتمد عليه؟ · ماذا يحجب؟ · ما الدليلُ المطلوبُ
لإغلاقِه؟ · **أهو `Release blocker` أم `Deferred`؟**

⛔ **ولا يُشترَط حلُّ جميعِ البنودِ قبل بدءِ الأعمالِ التنفيذيّة.**

**القبول:** **كلُّ بندٍ معروفٍ له صفٌّ أو مرجعٌ واضحٌ إلى سجلٍّ تابع.**

### ٢·١ الحالاتُ العشرُ المسموحة

`OPEN` · `IN_PROGRESS` · `IMPLEMENTED_NOT_VERIFIED` · `PARTIALLY_IMPLEMENTED` ·
`BLOCKED_OWNER` · `BLOCKED_GOVERNING_SOURCE` · `BLOCKED_UAT` ·
`BLOCKED_ENVIRONMENT` · `REGRESSION` · `EVIDENCE_CLOSED`

### ٢·٢ والمصائرُ النهائيّةُ الأربعةُ فقط

`EVIDENCE_CLOSED` · `NOT_APPLICABLE` (‏بمرجعٍ حاكم) · `SUPERSEDED` ·
**`GOVERNED_DEFERRED`** (‏بمالكٍ وإصدارٍ هدفٍ وسببٍ **وخطّافاتٍ معماريّةٍ مطلوبةٍ
الآن** وخطرِ التأجيل).

⛔ **ولا يُقبل:** `UNKNOWN` · `LATER` · `TODO` · **`CLOSED` بلا `Evidence`**.

### ٢·٣ ستُّ مستوياتٍ مستقلّةٍ لكلِّ بند

`Decision` → `Implementation` → `Wiring` → `Exercise` → `Evidence` → `Closure`

⛔ **وهذا يمنع الخطأَ التاريخيّ: `Implemented = Closed`.**

### ٢·٤ قاعدةُ التحديث

⛔ **لا تتغيّر حالةُ بندٍ بلا `Evidence_Snapshot_ID`** · **ولا `OPEN → CLOSED`
بتقريرٍ وصفيٍّ أو حكمٍ يدويّ**.
**وعند الارتداد:** ⛔ **لا يُعدَّل تاريخُ الإغلاقِ السابق — بل يُفتح `Finding`
جديدٌ مرتبطٌ به.**

### ٢·٥ `Priority` ≠ `Execution Order`

**كلُّ بندٍ يحمل `Priority` (`P0..P3`) و`Execution Rank`** — ⇒ **فيمكن تنفيذُ
`Unlocker` صغيرٍ بالتوازي مع `P0` إن لم يتداخل.**

⛔ **ولا يختار المنفِّذُ البنودَ يدويًّا حسبَ راحتِه** — بل تُرتَّب وفق:

**وخوارزميّةُ استخراجِ `Sprint`:** `Severity` · `Release Impact` ·
`Security/Data Integrity` · `Unlock Value` · `Dependency Fan-out` ·
`Estimated Effort` · `Owner/Environment Dependency` — **ووزنٌ مرتفعٌ لما كلفتُه
منخفضةٌ وأثرُه مرتفعٌ ويفتح عدّةَ بنود.**

### ٢·٦ دوريّةُ السجلّ

**يُحدَّث بعد كلِّ لقطةٍ · وكلِّ `Sprint` · وكلِّ قرارِ مالكٍ · وكلِّ ارتدادٍ ·
وكلِّ `Build batch` معتبَرٍ · وقبل أيِّ قرارِ إصدار.**
⛔ **ولا يوجد `Current Closure Register` بلا `Snapshot` حاكم.**

---

# ٣ · `EXECUTION SPRINT-01` — **خمسةُ أعمالٍ · تبدأ فورًا**

⛔ **لا تنتظرْ إتمامَ `WORK-00`.** **ولا واحدٌ من هذه الأعمالِ يحتاج قرارَ
أعمالٍ أو سياسةٍ من المالك.**

**وحدِّدْ `SPRINT_TIMEBOX` عند البداية وسجِّلْه.** وعند بلوغِه: **يُغلق ما اكتمل ·
ويُعاد قياسُ غيرِ المكتملِ · ويعود إلى السجلِّ بحالتِه الفعليّة · ويُسجَّل
`Next Action`.** ⛔ **وانتهاءُ الوقتِ لا يخفض أولويّةَ `P0` ولا يسمح بتجاوزِه.**

**وثلاثةُ مساراتٍ لا سبعة:** `Primary Security Lane` (‏لـ`P0`) ·
`Low-Conflict Unlocker Lane` · `Background Evidence/Test Lane`.

### `WORK-01` — ⭐ حمايةُ مصدرِ العمل · **ابدأْ بها فورًا**

**المقيسُ الآن:** `upstream` معرَّفٌ ✔ · **لكنَّ المحليَّ متقدِّمٌ بـ٢٢٦ التزامًا**
(`LOCAL=52f4fe37` · `REMOTE=96a640e1`) · و**٢٠ وسمًا** · و`origin` =
`git@github.com:mohamed-sd/ems.git`.

**العمل:** ادفعِ الفرعَ · **وادفعْ كلَّ الوسوم** · **وادفعِ الأُسُس** ·
**واستنسخْ نسخةَ اختبارٍ من البعيد** · **وأثبِتْ `REMOTE_HEAD = LOCAL_HEAD`**.

**القبول:** `REMOTE_HEAD = LOCAL_HEAD` · **والوسومُ والأُسُسُ قابلةٌ للاسترجاعِ
من جهازٍ آخر.**

> ⚠ **والدفعُ بمفتاحٍ `SSH` بعبارةِ مرور** — فإن طُلبت ولم تتوفّر:
> ⛔ **وسمْه `BLOCKED_ENVIRONMENT` لا `OWNER_DECISION`**، **وسلِّمِ الأمرَ
> للمستخدمِ بسطرٍ واحدٍ وامضِ إلى `WORK-02`.**

### `WORK-02` — إغلاقُ `GAP-10` (`P0` أمنيّ)

**فرضيّةُ العملِ الابتدائيّة بنصِّ الأمر:** **العطبُ في
`Channel Integration/Bypass Path` لا في وجودِ `SensitiveFieldGuard`.**
(‏والحارسُ موجودٌ: `app/Services/Security/SensitiveFieldGuard.php` ·
و`app/Services/Governance/FieldGovernor.php` · و`includes/sensitive_read_log.php`.)

**العمل:** تتبَّعْ مسارَ `Saved View` استعلامًا وتصييرًا · **وحدِّدْ نقطةَ تجاوزِ
القرارِ المركزيّ** · **وأصلِحِ القناةَ نمطًا** ⛔ **ولا تُرقِّعْ شاشةً واحدة** ·
**وأعِدِ اختبارَ القنواتِ التسع:** `Screen` · `Stored Views` · `Search` ·
`Export` · `API` · `AJAX` · `Direct URL` · `Projection` · `Field visibility`.

**القبول:** `ACTIVE_SENSITIVE_FIELD_BYPASS = 0`.

### `WORK-03` — جدولةُ `cron_events.php`

⚠ **وأعِدْ قياسَه أوّلًا:** الأمرُ يقول «غيرُ مجدول» · **و`cron_events_task.php`
أُنشئ في `b82bf7f3`** — **فإن كان مجدولًا فالبندُ تحقُّقٌ لا إنشاء.**

**العمل:** مدخلُ جدولةٍ صحيحٌ · و`PHP/runtime` صحيح · و`heartbeat` ·
و`Last Result` · **وصفرُ تشغيلٍ مزدوج** · وتقدُّمُ مؤشّرِ المستهلك · ومراقبةُ
التوقّف.

**القبول:** `Scheduler exists` · `Last Result = 0` · `heartbeat` يتحرّك ·
`consumers` تتحرّك · `duplicate scheduler invocation = 0`.

⛔ **والجدولةُ تنقل الحالةَ من `Wired` إلى `Exercised` — ولا يقال `Verified`
إلّا بعد تحقُّقِ الأثرِ التجاريّ.**

### `WORK-04` — إصلاحُ `EFFECT_MISSING` **نمطًا**

**الحالةُ المرجعيّة:** `settlement.approved / settlement#5502` — فشل لأنّه لم
يجد `Intended Effect`. ⛔ **ولا يُكتب استثناءٌ لـ5502.**

**حدِّدِ العقدَ:** متى يجب أن يُنتج `settlement.approved` أثرًا؟ · أين
`Mapping`؟ · ما `Source Entity`؟ · ما الأثرُ المالي/التشغيليُّ المطلوب؟

**والاختباراتُ الستّة:** `Positive` · `Missing Mapping` **يفشل صراحةً** ·
`Duplicate Delivery` **أثرٌ واحد** · `Retry` · `Partial Failure` **بلا نصفِ
حالة** · `Compensation`.

**القبول:** `REQUIRED_SETTLEMENT_EFFECT_MISSING = 0` **ثمَّ تُعمَّم القاعدةُ على
بقيّةِ أحداثِ الأعمال.**

### `WORK-05` — شدُّ السقّاطاتِ الستّ

⛔ **لا تشدَّ سقّاطةً أثناءَ نافذةِ القياس.** وبعدها: **أثبِتْ أنَّ التغيُّرَ
تحسُّنٌ فعليّ** · **وثبِّتِ المقام** · **ونفِّذِ اختبارًا سالبًا** · **واختبارَ
انحدار** · **وأنشئ `Proposed Baseline`** · **ونفِّذْ `--retighten`** · **وأعِدِ
القياس** · **وسجِّلْ لقطةً جديدة**.

⛔ **ولا `Auto-Mutation` للأساس** · ⛔ **ولا يُعلَن مسبقًا «١٦/٣٣ ⇒ ٢٢/٣٣» —
تُعلَن النتيجةُ الفعليّةُ بعد القياسِ فقط.**

**والأداتان:** `tools/repair01_w135_ratchet.php` · `tools/u12_debt_ratchet.php`.

### قبولُ `Sprint-01`

**لقطةٌ واحدةٌ تجيب:** هل البعيدُ محميّ؟ · هل `GAP-10` أُغلق؟ · هل `cron` يعمل؟
· كم حدثًا صار `Exercised`؟ · كم أثرًا صار `Verified`؟ · هل أثرُ التسويةِ يعمل
نمطًا؟ · هل السقّاطاتُ تقيس ارتدادًا حقيقيًّا؟ · **وما أعلى البنودِ التاليةِ
حسبَ السجلِّ الجامع؟**

⛔ **ثمَّ `EXECUTION SPRINT-02` يُولَّد من السجلِّ — ولا ينتظر المنفِّذُ سؤالَ «هل
أكمل؟».**

---

# ٤ · `LEGACY REGISTER` و`OWNER REGISTER`

### ٤·١ سجلُّ مصيرِ الالتزاماتِ القديمة

**الغرضُ ليس تنفيذَها الآن — بل ألّا يسقط التزامٌ بلا حكم.**

**والكونُ الواجبُ مراجعتُه:** `Offline/Field` (‏`PWA` · `Service Worker` ·
`IndexedDB` · `Delta Sync` · `Conflict handling` · `Offline write idempotency`) ·
`Integration` (‏`Outbox` · `Retry` · `DLQ` · `Compensation` · `Idempotency` ·
`External API contracts`) · `Multi-Entity` (‏دفاترُ الكيان · حساباتُه البنكيّة ·
`intercompany tagging` · `consolidation readiness`) · `Customer Revenue`
(`ENT-03` · `Billing` · `Collection` · `Debit/Credit Notes` · `AR aging` ·
`Customer statement` · الحدُّ الأدنى المضمونُ تعاقديًّا) · `Operational
Architecture`.

**والمصيرُ واحدٌ من أربعة:** `CURRENT_RELEASE` · `NEXT_RELEASE` · `SUPERSEDED` ·
`NOT_APPLICABLE`. ⛔ **ولا يُقبل «غيرُ موجودٍ في `RPR` الحالي».**

⭐ **و`NEXT_RELEASE` يوجب تحديدَ الخطّافاتِ المعماريّةِ المطلوبةِ الآن** —
*«لا نبني محرِّكَ `Offline` الآن، **لكن لا نبني معاملاتٍ تمنع لاحقًا**
`version numbers` و`idempotency` و`delta sync` و`conflict resolution`»*.
⇒ **«التأجيلُ لا يعني إغلاقَ الباب.»**

**وحقولُ كلِّ `Legacy Item` الاثنا عشر:**
`Requirement ID` · `Original Source` · `Original Intent` ·
`Current Applicability` · `Current Architecture Support` · `Disposition` ·
`Owner` · `Target Release` · **`Architectural Hooks Needed Now`** ·
`Dependencies` · **`Risk of Deferral`** · `Decision Reference`.

**القبول:** `LEGACY_REQUIREMENT_WITHOUT_DISPOSITION = 0`.

⛔ **ولا شيءَ يختفي بالصمت.**

### ٤·٢ سجلُّ أعمالِ المالك — ⛔ **ما لا يدخله**

**الغرضُ منعُ تحويلِ المالكِ إلى:** مسؤولِ دعمٍ تقنيٍّ · `DBA` ·
`Scheduler Admin` · مصنِّفِ آلافِ القيود · مصحِّحِ أداةٍ · `UAT coordinator`.

⭐ **ولا يدخل السجلَّ إلّا:** **قرارُ أعمالٍ أو سياسةٍ أو `Config` حقيقيٌّ
لا تحسمه الوثائقُ ولا المعماريّةُ ولا الهندسة.**

⛔ **لا يدخل:** `Hash Algorithm` · `Scheduler/Cron` · التسميةُ التقنيّة ·
إصلاحُ أداة · تصحيحُ هجرة · تصنيفُ أحداث · تصنيفُ `Replay` ·
`Bulk data cleanup` · إنشاءُ `Test Persona` · جدولةُ `UAT` ·
أمرُ بيئةٍ · `Mapping` حاكمٌ **يمكن اشتقاقُه**.
⇒ **هذه `Engineering / Environment / UAT`.**

**✔ ويدخله:** قيمُ الاعتماد (‏عند `Approval Shadow Window`) · `Aggregation
Window` إن كانت `Config` أعمالٍ حقيقيّة · **قرارُ مقامِ التايم شيت** (`Timesheet denominator decision`) ·
`Business access/naming` **فقط إن كانت المراجعُ لا تحسمها**.

⭐ **وقرارُ مقامِ التايم شيت يُعرض بتسعةٍ:** المقامُ الأوّل · المقامُ الثاني ·
مصدرُ كلٍّ · المعنى التجاريّ · أثرُ التاريخ · أثرُ المسيَّر · الأثرُ المالي ·
استحقاقُ المورد/الموظف · **والتوصيةُ الفنيّة**. ⛔ **ولا تُغيَّر بياناتٌ إرثيّةٌ
قبل القرار.**

**وحقولُ كلِّ `Owner Action` الأربعةَ عشرَ:**
`Owner_Action_ID` · `Decision_Question` · `Decision_Type` ·
`Affected_Targets` · `Affected_Domains` · **`What_It_Blocks`** ·
`Required_By_Gate` · `Depends_On` · `Options` · **`Impact_Of_Each_Option`** ·
**`Technical_Recommendation`** · `Owner_Decision` · `Decision_Date` ·
**`Propagation_Status`**.

**و`Required_By_Gate` لا تاريخٌ مخترَع** — ⛔ **ولا يضع المنفِّذُ تاريخًا من
عنده.**

**و`Owner Action` لا يصير حاجزًا عامًّا:** يحجب هدفًا ⇒ هدفًا · مسارًا ⇒ مسارًا
· **ولا يحجب البرنامجَ إلّا بقرارٍ بنيويٍّ موثَّق.**

---

# ٥ · بروتوكولُ التشغيلِ السبع

```
① Bootstrap  — أنشئ السجلَّ ⛔ ولا توقفْ Sprint أثناء ملئه
② Sprint     — استخرجْ أعلى 5–8 أعمالٍ فقط
③ Execute    — Verify → Implement → Wire → Exercise → Evidence → Close
④ Update     — حدِّثِ السجلَّ باللقطةِ الجديدة
⑤ Next Sprint— من السجلِّ ⛔ لا من الذاكرةِ ولا من آخرِ رسالة
⑥ Legacy     — أيُّ التزامٍ قديمٍ يظهر يدخل سجلَّه ⛔ ولا يقتحم Sprint
⑦ Owner      — القرارُ الحقيقيُّ وحدَه يدخل سجلَّه عند بوّابتِه
```

**ومعيارُ قبولِ كلِّ `Sprint`:** `SPRINT_ACCEPTANCE = 100%` — أي **كلُّ بندٍ فيه
`Evidence Closed`** أو **أُعيد إلى السجلِّ بحالةٍ واضحةٍ عند `Timebox`** أو
**صار `Blocked` بحاجزٍ حقيقيٍّ مسمًّى**. ⛔ **ولا يُسمّى `Closed` إن عاد مفتوحًا.**

---

# ٦ · معيارُ الإغلاقِ النهائيِّ للنظام

**`OWNER_APPROVED_PRODUCTION_BASELINE` لا يصدر حتى:**

`P0 = 0` · `Governing conflicts = 0` · `Security bypass = 0` ·
`Applicable Target unresolved = 0` · **أحداثُ الأعمالِ التي تستلزم أثرًا =
`Verified`** · `Critical consumers stalled = 0` · `unresolved DLQ = 0` ·
**مساراتُ قرارِ الصلاحيّةِ موحَّدة** · `Approval Shadow` مكتملة ·
`Approval Enforcement` ناجح · `Critical silent stations = 0` ·
`Clean Clone` مطابق · `Backup/Restore/PITR` على المخطَّطِ الحاليّ ·
`Golden Screens = 10/10` · `Human E2E` مقبول · `unsupported manual journals = 0`
· **`Rendered Sidebar verification = PASS`** · `documentation stale sections = 0`
· `Remote source = verified` · **`Legacy Requirements` كلُّها محكومة**.

⛔ **ولا تُجمع هذه المقاماتُ في نسبةٍ واحدة.**

---

# ٧ · المحظورات

| ⛔ | المحظور |
|---|---|
| ١ | **لا تتوقّفْ ولا تستأذنْ** — والتقريرُ مرحلةٌ لا نقطةُ توقّف |
| ٢ | **ولا `CLOSED` بلا `Evidence_Snapshot_ID`** |
| ٣ | **ولا تعديلَ لتاريخِ إغلاقٍ سابقٍ عند الارتداد** — `Finding` جديد |
| ٤ | **ولا استثناءَ لحالةٍ مفردة** — أصلِحِ النمط |
| ٥ | **ولا شدَّ سقّاطةٍ أثناءَ نافذةِ القياس** · ولا إعلانَ نتيجةٍ قبل قياسِها |
| ٦ | **ولا تكتبْ نسبةً لا يُخرجها المقياسُ حرفًا** — `G-CLAIM-01` يردُّها |
| ٧ | **ولا ترفعْ إلى المالكِ ما هو `Engineering/Environment/UAT`** |
| ٨ | **ولا تُجمع المقاماتُ في نسبةٍ واحدة** |
| ٩ | **ولا تبدأْ من ذاكرةِ المحادثة** — السجلُّ هو المصدر |

---

# ٨ · سطرُ التقدّم

```
[<Snapshot>] <WORK-0X> · <المقياس> <من> ⇒ <إلى> · دليل: <الأداة> · التالي: <WORK-0Y>
```

⛔ **ثمَّ ابدأِ التاليَ فورًا في الرسالةِ نفسِها.**

---

# ٩ · ترتيبُ التنفيذ

```
①  ⛔ اقفلِ الشجرةَ (40 بندًا) إلى صفر — ولا قياسَ قبله
②  WORK-00: أنشئ السجلَّ الجامعَ ⛔ ولا توقفْ Sprint أثناءه
③  ⭐ WORK-01: ادفعِ الفرعَ والوسومَ والأُسُسَ — REMOTE_HEAD = LOCAL_HEAD
    ⛔ فإن مُنع بمفتاحٍ: BLOCKED_ENVIRONMENT وسلِّمْه بسطرٍ وامضِ
④  WORK-02 وWORK-03 بالتوازي — لا تعارضَ بينهما
⑤  WORK-04: EFFECT_MISSING نمطًا بالاختباراتِ الستّة
⑥  WORK-05: السقّاطاتُ خارجَ نافذةِ القياس
⑦  أصدِرْ لقطةَ قبولِ Sprint-01 بأسئلتِها الثمانية
⑧  ولِّدْ SPRINT-02 من السجلِّ ⛔ واستمرَّ بلا انتظار
⑨  وبالتوازي: املأْ Legacy Register وOwner Register
⑩  ⛔ ولا تتوقّفْ بين أيِّ خطوتَين
```

---

## الخاتمة — بنصِّ الأمر

> **«هذا النظامُ لا يحتاج مزيدًا من القوائمِ الطويلة. يحتاج: **دَينًا كاملًا
> محفوظًا في سجلّ** + **عددًا قليلًا من الأعمالِ يُنفَّذ الآن** + **التزاماتٍ
> قديمةً لا تضيع** + **قراراتِ مالكٍ لا تُطلب إلّا عند بوّابتِها**.»**
