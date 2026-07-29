# مصفوفةُ منع التكرار — N-01 (2026-07-30)

> «مفاتيحُ منعِ تكرارٍ لكل (مستند × نوع أثر × طرف)» — PLAN-01 §4.
> المهمة **تعميمُ القائم وإثباتُه بإعادةِ إرسالٍ فعلية** لا بناءٌ جديد.
> كلُّ سطرٍ أدناه له حارسٌ بنيويٌّ + برهانُ إعادةِ إرسالٍ في اختبارٍ أخضر.

## 1 · ما أُضيف في N-01 (الفجوتان المقيستان)

| الفجوة | العلاج |
|---|---|
| `fin_event_links` — دفترُ عطالة المروحة نفسُه كان **بلا فهرسٍ فريد** (العطالةُ فحصٌ تطبيقيٌّ يلتفّ عليه سباقٌ أو كاتبٌ ناسٍ) | `UNIQUE (company_id, parent_kind, parent_ref, effect_type)` — هجرة `2026_08_11` (قيس قبلها: صفرُ تكرارٍ في الخمسين) |
| `fin_auto_journal` — القيدُ الآلي بلا عطالة (استدعاءٌ ثانٍ للحدث نفسِه يولّد قيدًا ثانيًا) | فحصُ «قيدٌ حيٌّ قائمٌ للحدث → يُعاد مرجعُه» قبل أي توليد |

## 2 · المصفوفة — المسار · المفتاح · الحارس البنيوي · برهان إعادة الإرسال

| # | المسار المالي | مفتاح العطالة | الحارس البنيوي | البرهان (اختبار أخضر) |
|---|---|---|---|---|
| 1 | الجذر المحايد `ems_business_events` (publishFact/publish) | `idempotency_key` حتمي من (event_key × entity × id) | `uq_ebe_idempotency` + `uq_ebe_uuid` + `uq_ebe_no` | `idempotency_resend_test` ②: ×2 → المعرّفُ نفسُه، صفٌّ واحد |
| 2 | الدفتر المالي `fin_financial_events` (EventPublisher::publish) | `idempotency_key` نفسُه (جذرٌ وإسقاطُه بمفتاحٍ واحد) | `uq_ffe_idempotency` + `uq_ffe_event_uuid` + `uq_fin_event_no` | `idempotency_resend_test` ①: ×2 → duplicate=true · `event_publisher_test` (54/0) |
| 3 | آثارُ الحدث `fin_event_effects` (H-12) | (event × effect_type × party × بند) — NOT NULL كلُّها | `uq_effect` الخماسي | `fes_event_contract_test` ⑤ (خرقٌ فعلي 1062) + `idempotency_resend_test` ① (أثرٌ واحدٌ بعد الإعادة) |
| 4 | مروحةُ الأثر `fin_event_links` (EffectFanout §6.1-③) | (شركة × نوع أب × مرجعه × نوع أثر) | **`uq_link_parent_effect` — جديدٌ في N-01** + فحصُ `existingEffects` التطبيقي | `idempotency_resend_test` ④ (1062 فعلية) + `effect_fanout_test` (18/0 — إعادةُ تشغيل المروحة لا تكرر) |
| 5 | القيدُ الآلي `fin_journal_entries` (fin_auto_journal) | حدثٌ واحد → قيدٌ حيٌّ واحد | فحصُ event_id قبل التوليد (**جديدٌ في N-01**) + `uq_fin_entry_no` | `idempotency_resend_test` ③: ×2 → القيدُ نفسُه |
| 6 | إدخالُ الوحدات `unit_entries` (TimesheetEntryService) | (معدة × تاريخ × وردية) | مفتاحُ المزامنة + ردُّ 200 بالمرجع القائم | `timesheet_entry_service_test` T2 (50/0) |
| 7 | أحكامُ الأطراف `unit_party_awards` | (source_ref × party) | `UNIQUE(source_ref, party)` (M-24) | `qty_attribution_test` (17/0) |
| 8 | التسوياتُ (SettlementService) | `idempotency_key` على رقم التسوية | مفتاحُ التسوية (رقمُها يُعاد استعمالُه فالمفتاحُ هو الحارس) | `settlement_test` (أكّد عليه بناؤها) |
| 9 | إشعاراتُ الدائن/المدين (note_helpers) | `sha1` من المدخلات | مفتاحُ العطالة قبل النشر | `credit_debit_note_test` (22/0) |
| 10 | سلفُ العقد (advance_helpers) | العطالةُ قبل فحص الرصيد | مفتاحُ العطالة | `contract_advance_test` (34/0) |
| 11 | الاستيراد (import channel) | عبر EventPublisher حصرًا — يرث مفتاحَه | `uq_ffe_idempotency` | `import_channel_test` (9/0) |
| 12 | استقطاعاتُ الجزاء (claim_penalty_lines) | لقطةُ الاحتساب المُجاز | حدثُ penalty له جذرُه | `penalty_test` (38/0 بعد إصلاحه) |
| 13 | المطابقةُ البنكية | (account × value_date × bank_ref × amount) | **لم تُبنَ بعد** — تُبنى بمفتاحها في H-13 (الموجة ③) وفق UX-02 §15.2-ب | يُثبت مع H-13 |

## 3 · قاعدةُ التعميم للكاتب الجديد

كلُّ مسارٍ ماليٍّ جديد يجب أن يجيب قبل الدمج:
1. ما **مفتاحُ** عمليته المنطقية؟ (مستند × نوع أثر × طرف)
2. أين **فهرسُه الفريد**؟ (فحصٌ تطبيقيٌّ وحدَه لا يكفي — السباقُ يلتف عليه)
3. أين **برهانُ إعادة الإرسال** في اختباره؟ (إرسالٌ ثانٍ فعليٌّ يثبت صفرَ صفٍّ جديد
   وردًّا بالمرجع القائم لا خطأً غامضًا)
