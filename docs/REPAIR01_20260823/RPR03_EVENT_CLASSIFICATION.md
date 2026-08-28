# `RPR-03` §٤·٢ — تصنيفُ أنواعِ الأحداثِ الثمانيةِ والخمسين

> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/rpr03_event_classify.php --md` · اللقطة `SNAP-d23357e7-20260829-000933`

## القواعدُ الثلاثُ — مقيسةٌ على الحمولةِ لا مؤلَّفةٌ بالاسم

| القاعدة | الحكم | العدد | المعيار |
|---|---|---|---|
| `B2` | `BUSINESS` | **23** | مبلغٌ أو كمّيّةٌ غيرُ صفرٍ ⇒ يستلزم أثرًا خارجَ الحدث |
| `B3` | `AUDIT` | **31** | لا حمولةَ وفئتُه غيرُ ماليّة ⇒ غرضُه الرقابيُّ يتحقّق بوجودِه |
| `B1` | `RETIRED` | **3** | عائلةُ السبرِ بصفرِ حمولةٍ ⇒ عُدّةُ قياسٍ لا واقعةُ أعمال |
| — | ⛔ `NEEDS_ADJUDICATION` | **1** | ماليٌّ بحمولةٍ صفر — ولا يُقحَم |

## خطوةُ صفرٍ — إعادةُ قياسِ خطِّ الأساس

خطُّ الأساسِ يقول **١١** حدثَ أعمالٍ و**٤٧** تدقيقيًّا · **والمقيسُ الآنَ 23 و31** — والمقامُ ٥٨ لم يتغيّر.
◆ **والفرقُ خبرٌ يُعلَن لا يُخفى.**

## الجدولُ الكامل

| المفتاح | الفئة | الحكم | القاعدة | وقائع | بمبلغ | بكمّيّة |
|---|---|---|---|---|---|---|
| `analytics.probe_derived` | analytics | `RETIRED` | B1 | 474 | 0 | 0 |
| `attribution.decided` | operational | `AUDIT` | B3 | 1 | 0 | 0 |
| `capacity.consumed` | operational | `BUSINESS` | B2 | 1 | 0 | 1 |
| `capacity.gap_closed` | operational | `AUDIT` | B3 | 76 | 0 | 0 |
| `capacity.gap_escalated` | operational | `BUSINESS` | B2 | 311 | 0 | 135 |
| `capacity.gap_opened` | operational | `BUSINESS` | B2 | 201 | 0 | 112 |
| `contract.advance.received` | financial | `BUSINESS` | B2 | 128 | 128 | 0 |
| `contract.signed` | commercial | `AUDIT` | B3 | 8 | 0 | 0 |
| `contract.state.changed` | operational | `AUDIT` | B3 | 95 | 0 | 0 |
| `equipment.hour_logged` | operational | `BUSINESS` | B2 | 8 | 0 | 8 |
| `exec.approval.granted` | operational | `AUDIT` | B3 | 9 | 0 | 0 |
| `exec.decision.made` | operational | `AUDIT` | B3 | 6 | 0 | 0 |
| `expense.depreciation.recorded` | financial | `BUSINESS` | B2 | 1990 | 1990 | 0 |
| `expense.landed_cost.recorded` | financial | `BUSINESS` | B2 | 1 | 1 | 0 |
| `expense.maintenance.recorded` | financial | `BUSINESS` | B2 | 2 | 2 | 0 |
| `expense.parts.issued` | financial | `BUSINESS` | B2 | 2 | 2 | 0 |
| `expense.purchase.recorded` | financial | `BUSINESS` | B2 | 13 | 13 | 0 |
| `finance.event.recorded` | financial | `BUSINESS` | B2 | 10 | 10 | 0 |
| `finance.hour_recognized` | financial | `BUSINESS` | B2 | 17 | 0 | 17 |
| `finance.recon_adjustment.posted` | financial | `BUSINESS` | B2 | 220 | 220 | 0 |
| `finance.request.forwarded` | financial | `BUSINESS` | B2 | 1 | 1 | 0 |
| `incentive.approved` | financial | `BUSINESS` | B2 | 82 | 82 | 0 |
| `operations.unit.approved` | operational | `BUSINESS` | B2 | 8 | 8 | 8 |
| `operations.unit.chain_completed` | operational | `AUDIT` | B3 | 2 | 0 | 0 |
| `operations.unit.stage_approved` | operational | `AUDIT` | B3 | 2 | 0 | 0 |
| `operations.unit.submitted` | operational | `AUDIT` | B3 | 10 | 0 | 0 |
| `payable.finance_installment.accrued` | financial | `BUSINESS` | B2 | 219 | 219 | 0 |
| `payable.purchase.accrued` | financial | `BUSINESS` | B2 | 2 | 2 | 0 |
| `penalty.approved` | financial | `BUSINESS` | B2 | 84 | 84 | 84 |
| `penalty.waived` | financial | `NEEDS_ADJUDICATION` | — | 82 | 0 | 0 |
| `probe.k_seven` | operational | `RETIRED` | B1 | 5 | 0 | 0 |
| `probe.source_logged` | operational | `RETIRED` | B1 | 3 | 0 | 0 |
| `procurement.supplier_contract.state_changed` | commercial | `AUDIT` | B3 | 59 | 0 | 0 |
| `procurement.supplier_contract.termination_revoked` | commercial | `AUDIT` | B3 | 26 | 0 | 0 |
| `project.chartered` | operational | `AUDIT` | B3 | 11 | 0 | 0 |
| `request.escalated` | financial | `BUSINESS` | B2 | 3 | 3 | 0 |
| `request.submitted` | financial | `BUSINESS` | B2 | 2 | 2 | 0 |
| `revenue.unit.recognized` | financial | `BUSINESS` | B2 | 5199 | 5041 | 5041 |
| `risk.access_review.attested` | operational | `AUDIT` | B3 | 2 | 0 | 0 |
| `risk.appetite.compared` | analytics | `AUDIT` | B3 | 1 | 0 | 0 |
| `risk.incident.logged` | operational | `AUDIT` | B3 | 4 | 0 | 0 |
| `risk.kri.breached` | analytics | `AUDIT` | B3 | 3 | 0 | 0 |
| `risk.report.exported` | analytics | `AUDIT` | B3 | 4 | 0 | 0 |
| `risk.risk.classified` | operational | `AUDIT` | B3 | 24 | 0 | 0 |
| `risk.risk.escalated` | operational | `AUDIT` | B3 | 4 | 0 | 0 |
| `risk.risk.registered` | operational | `AUDIT` | B3 | 24 | 0 | 0 |
| `risk.risk.reviewed` | operational | `AUDIT` | B3 | 4 | 0 | 0 |
| `risk.signal.raised` | operational | `AUDIT` | B3 | 126 | 0 | 0 |
| `risk.signal.synced` | operational | `AUDIT` | B3 | 8 | 0 | 0 |
| `settlement.approved` | financial | `BUSINESS` | B2 | 2 | 2 | 0 |
| `supplier.rfq_quote.submitted` | commercial | `AUDIT` | B3 | 431 | 0 | 0 |
| `supplier.rfq.awarded` | commercial | `AUDIT` | B3 | 213 | 0 | 0 |
| `supplier.rfq.contracted` | commercial | `AUDIT` | B3 | 212 | 0 | 0 |
| `supplier.rfq.opened` | commercial | `AUDIT` | B3 | 216 | 0 | 0 |
| `supplier.rfq.sent` | commercial | `AUDIT` | B3 | 216 | 0 | 0 |
| `workflow.state_changed` | operational | `AUDIT` | B3 | 110 | 0 | 0 |
| `workforce.contract_state.changed` | operational | `AUDIT` | B3 | 10350 | 0 | 0 |
| `workforce.contract.amended` | operational | `AUDIT` | B3 | 219 | 0 | 0 |
