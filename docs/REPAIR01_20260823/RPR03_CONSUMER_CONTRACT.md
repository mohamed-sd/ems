# `RPR-03` §٤·٢ الخطوة ٣ — عقدُ الأثرِ مسجَّلًا

> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/rpr03_consumer_contract.php --md` · اللقطة `SNAP-526c586e-20260830-114835`

مستهلكون فعّالون **102** على **9** صنفًا · بعقدٍ كاملٍ **0**.

## الأصنافُ وعقودُها المقروءةُ من الشيفرة

| الصنف | الملفّ | اشتراكات | مفاتيحُ حمولة | جداولُ كتابة |
|---|---|---:|---:|---:|
| `App\Services\Bus\Consumers\GovernanceWatchConsumer` | `app/Services/Bus/Consumers/GovernanceWatchConsumer.php` | 70 | 8 | 1 |
| `App\Services\Finance\AccountingCycleService` | `app/Services/Finance/AccountingCycleService.php` | 9 | 3 | 0 |
| `App\Services\Financing\FinancingCycleService` | `app/Services/Financing/FinancingCycleService.php` | 10 | 2 | 0 |
| `App\Services\Governance\SplitProjectionConsumer` | `app/Services/Governance/SplitProjectionConsumer.php` | 5 | 4 | 1 |
| `App\Services\Payroll\PayrollRunService` | `app/Services/Payroll/PayrollRunService.php` | 1 | 2 | 0 |
| `App\Services\Policy\UnitJourneyService` | `app/Services/Policy/UnitJourneyService.php` | 1 | 3 | 2 |
| `App\Services\Treasury\TreasuryCycleService` | `app/Services/Treasury/TreasuryCycleService.php` | 4 | 5 | 0 |

## ⛔ موقوفٌ بلا عقد — ولا يُكتب عقدٌ أجوف

| الصنف | اشتراكات | السبب |
|---|---:|---|
| `App\Services\Payroll\OffsetService` | 1 | NO_PAYLOAD_READ · لا يُنتزع من صفِّه مفتاحُ حمولةٍ واحد — وعقدٌ بحمولةٍ فارغةٍ مرجعٌ أجوف |
| `App\Services\Revenue\CollectionService` | 1 | NO_PAYLOAD_READ · لا يُنتزع من صفِّه مفتاحُ حمولةٍ واحد — وعقدٌ بحمولةٍ فارغةٍ مرجعٌ أجوف |

⛔ **ولا يُقرأ هذا إغلاقًا لمقياسِ #٣** («أحداثُ أعمالٍ بلا عقدِ مستهلكٍ **فعّال**»):
ذاك يشترط **مستهلكَ أثرٍ** (`produces='write'`) على حدثِ الأعمالِ نفسِه — **وهو بناءُ مستهلكٍ
بأثرٍ تجاريّ** لا ملءُ عقدِ مستهلكٍ قائم.
