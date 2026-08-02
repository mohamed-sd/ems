# بطاقةُ مواصفة — الصادرُ والذريةُ وأحداثُ مجال القدرات (update0005 · الموجة ⑤)

| البند | القيمة |
|---|---|
| المصدر | `CAP-01 §14` · `DEC-CAP-B` · `DEC-CAP-D` (FES-01) |
| المهام | `CAP-26` → `CAP-30` |
| التاريخ | 2026-08-02 |

## 1 · المعاملةُ الذرية — الكتاباتُ الخمس (§14)

`CapacityAtomicCommit::run` داخل `runInTransaction` واحدة:

| # | الكتابة | المنفِّذ |
|---|---|---|
| ① | تثبيتُ سجل الوحدة القانوني بنسخته | `fix_unit` callable — يمرّره مالكُ التايم شيت (UX-03 · الموجة ⑥) |
| ② | أحكامُ الأطراف الثلاثة | `rulings` callable — من مصفوفة العقد النافذة |
| ③ | أسطرُ دفتر الاستهلاك | `CapacityLedgerService::appendLine` |
| ④ | مفتاحُ منع التكرار | الـUQ الخماسيُّ البنيويُّ نفسُه — لا كتابةَ منفصلة |
| ⑤ | صفُّ الصادر | `CapacityOutbox::enqueue` — **كتابةٌ لا نشر** |

**C27**: فشلُ أيِّ واحدةٍ → تراجعُ الكل — صفرُ وحدةٍ وصفرُ استهلاكٍ وصفرُ صفِّ صادر.
**بعد COMMIT**: `CapacityOutbox::drain` ينشر عبر `EventPublisher::publishFact` —
والفاشلُ يُعاد **تصاعديًّا** (2^attempts دقيقة بساعة القاعدة · سقفُ 8 محاولاتٍ ثم
`failed` معلَنة). **C28**: الإعادةُ تنجح ولا تُستهلك الحصةُ ثانيةً — النشرُ لا
يمسّ الدفترَ والعطالةُ (`idempotency_key` الموحَّد عبر الطبقات — CAP-30) تمنع
حدثًا ثانيًا.

## 2 · قاموسُ أحداث المجال — التسجيلُ في FES-01 (CAP-29)

| الحدث (§14) | المفتاح المنشور | الأثرُ المالي |
|---|---|---|
| ① CapacityConsumed | `capacity.consumed` | ماليٌّ بطبيعته — عبر ENT-03 (مؤجَّل DEC-CAP-D) |
| ② SupplierShareConsumed | `capacity.share_consumed` | ماليٌّ — عبر ENT-02 (مؤجَّل) |
| ③ CapacityConsumptionReversed | `capacity.consumption_reversed` | ماليٌّ — عكسٌ بمرجع السطر والأصلُ باقٍ |
| ④ ExceptionalCoverageRecognized | `capacity.coverage_recognized` | ماليٌّ — بسعرِ التغطية المتفق (§7-③) |
| ⑤ StandbyActivated | `capacity.standby_activated` | **تشغيليٌّ** — لا ماليَّ إلا بنصِّ مقابلٍ (`standby_compensation_type` ≠ none) |
| ⑥ CoverageGapOpened | `capacity.gap_opened` | **نطاقيٌّ** — الجزاءُ من قاعدة العقد (`shortfall_rule=penalty`) لا من الحدث |

- **العقدُ الناشر**: `source_module=capacity` (سُجّل في قائمة §9) · `category=operational` ·
  النشرُ في الجذر المحايد `ems_business_events` حصرًا.
- **بوابةُ الأثر المالي**: `CapacityEvents::financialEffectAllowed` — ⑤ و⑥ ترفضان
  بلا قاعدةِ عقدٍ صريحة (§14-◆ · DEC-CAP-A).
- **DEC-CAP-B محفوظ**: صفرُ لمسٍ لـ`EffectFanout` و`fin_effect_map` و`ems_business_events`
  بنيةً — الماليُّ يمرّ لاحقًا عبر محركات ENT المؤجَّلة لا عبر المروحة.
- ومرقبُ الفجوة ينشر إضافةً حدثَي دورةٍ (`capacity.gap_escalated` · `capacity.gap_closed`)
  من §16-④ — تشغيليّان كذلك.

## 3 · العلَم

`EMS_CAP_OUTBOX` = `off` · `on` — off: صفوفُ الصادر تُكتب والعاملُ لا ينشر
(لا-عملية موثَّقة). **شرطُ القلب إلى on: خضرةُ C28** — تحققت في حزام الموجة ⑤.
