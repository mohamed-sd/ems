# `RPR-03` §٤ — مفرداتُ المُنتِجِ ومفرداتُ المستهلك

> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/rpr03_consumer_vocabulary.php --md` · اللقطة `SNAP-84861c0c-20260829-214517`

§٤ يقول: «المشكلةُ ليست في الناقلِ بل في أنَّ أحدًا لا يستمع». **والقياسُ أدقُّ ويقلبه**:
المستهلكون موجودون ونشِطون — **لكنَّهم يستمعون إلى أسماءٍ لم تُنطَق قطّ**.

| المقياس | العدد |
|---|---:|
| مفرداتٌ نُطقت فعلًا | 58 |
| اشتراكاتٌ نشِطة | 102 |
| منها على مفردةٍ وقعت | 58 |
| **اشتراكُ أثرٍ على مفردةٍ حيّة** | **0** |
| ⛔ **اشتراكُ أثرٍ على مفردةٍ ميّتة** | **30** |
| حدثُ أعمالٍ منطوقٌ بلا كاتب | **23** |

## المفردتان متقابلتَين — ⛔ **ولا رَبطَ هنا**

| ينتظره مستهلكُ أثرٍ ولم يقع | نُطق ولا كاتبَ له |
|---|---|
| `acc.account.reconciled` ⇐ AccountingCycleService | `revenue.unit.recognized` ×5199 |
| `acc.adjustment.posted` ⇐ AccountingCycleService | `expense.depreciation.recorded` ×1990 |
| `acc.entry.posted` ⇐ AccountingCycleService | `capacity.gap_escalated` ×311 |
| `acc.period.closed` ⇐ AccountingCycleService | `finance.recon_adjustment.posted` ×220 |
| `acc.period.reopened` ⇐ AccountingCycleService | `payable.finance_installment.accrued` ×219 |
| `acc.recognition.decided` ⇐ AccountingCycleService | `capacity.gap_opened` ×201 |
| `acc.recognition.requested` ⇐ AccountingCycleService | `contract.advance.received` ×128 |
| `acc.trial.balanced` ⇐ AccountingCycleService | `penalty.approved` ×84 |
| `fin.contract.closed` ⇐ FinancingCycleService | `incentive.approved` ×82 |
| `fin.contract.signed` ⇐ FinancingCycleService | `finance.hour_recognized` ×17 |
| `fin.deviation.raised` ⇐ FinancingCycleService | `expense.purchase.recorded` ×13 |
| `fin.final.closed` ⇐ FinancingCycleService | `finance.event.recorded` ×10 |
| `fin.monthly.closed` ⇐ FinancingCycleService | `equipment.hour_logged` ×8 |
| `fin.order.approved` ⇐ FinancingCycleService | `operations.unit.approved` ×8 |
| `fin.order.executed` ⇐ FinancingCycleService | `request.escalated` ×3 |
| `fin.ownership.transferred` ⇐ FinancingCycleService | `request.submitted` ×2 |
| `fin.payment.allocated` ⇐ FinancingCycleService | `payable.purchase.accrued` ×2 |
| `fin.schedule.generated` ⇐ FinancingCycleService | `settlement.approved` ×2 |
| `legacy.pointer.translated` ⇐ SplitProjectionConsumer | `expense.parts.issued` ×2 |
| `payroll.deductions.reversed` ⇐ OffsetService | `expense.maintenance.recorded` ×2 |
| `payroll.run.opened` ⇐ PayrollRunService | `finance.request.forwarded` ×1 |
| `policy.line.objected` ⇐ UnitJourneyService | `expense.landed_cost.recorded` ×1 |
| `revenue.collection.allocated` ⇐ CollectionService | `capacity.consumed` ×1 |
| `sidebar.item.replaced` ⇐ SplitProjectionConsumer |  |
| `split.conflict.detected` ⇐ SplitProjectionConsumer |  |
| `surface.owner.reassigned` ⇐ SplitProjectionConsumer |  |
| `tre.bank.reconciled` ⇐ TreasuryCycleService |  |
| `tre.count.approved` ⇐ TreasuryCycleService |  |
| `tre.payment.executed` ⇐ TreasuryCycleService |  |
| `tre.receipt.allocated` ⇐ TreasuryCycleService |  |

⛔ **والربطُ بينهما حكمُ أعمالٍ موثَّقٌ لا تشابهُ أسماء** — ومن يربط بالحدسِ
يُشغِّل أثرًا ماليًّا على حدثٍ غيرِ مقصودِه.
