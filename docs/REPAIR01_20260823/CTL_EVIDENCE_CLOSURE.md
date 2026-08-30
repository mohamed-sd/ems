# أمرُ الضبطِ §٥ — مسارُ الإغلاقِ بالدليل

> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/ctl_evidence_closure.php --md` · اللقطة ``

| المفردة | العدد |
|---|---:|
| منفَّذٌ غيرُ مثبتٍ بأسطحِه | 223 |
| **أُغلق بالدليل** (E1..E4) | **0** |
| قراءةٌ سقطت في E4 | 0 |
| قراءةٌ مفتوحةٌ بسببٍ قبل E4 | 14 |
| معاملاتٌ (عقدُها يطلب عينًا ومسارًا سالبًا) | 182 |
| بلا نوعٍ في الدفتر | 5 |

## قراءاتٌ مفتوحةٌ بأسبابِها

- `FIN-17` (Financing/fin_payment_orders.php): E1: source_of_truth فارغٌ في السجلِّ المبنيّ
- `FIN-18` (Financing/fin_payment_allocation.php): E1: source_of_truth فارغٌ في السجلِّ المبنيّ
- `HR-14` (Workforce/worker_leave_absence.php): E3: كيانُه `employees` له حقولٌ حسّاسةٌ نافذةُ السياسة — تحقّقُ الإقنعةِ عينٌ لا مسح
- `MY-06` (user_capacities.php): E1: source_of_truth فارغٌ في السجلِّ المبنيّ
- `SAL-02` (Clients/client_contacts.php): E1: source_of_truth فارغٌ في السجلِّ المبنيّ · E3: كيانُه `suppliers` له حقولٌ حسّاسةٌ نافذةُ السياسة — تحقّقُ الإقنعةِ عينٌ لا مسح
- `SAL-08` (Clients/quotation_lines.php): E1: source_of_truth فارغٌ في السجلِّ المبنيّ
- `SAL-09` (Clients/quotation_negotiation.php): E1: source_of_truth فارغٌ في السجلِّ المبنيّ
- `SUP-02` (Suppliers/supplier_contacts.php): E1: source_of_truth فارغٌ في السجلِّ المبنيّ · E3: كيانُه `suppliers` له حقولٌ حسّاسةٌ نافذةُ السياسة — تحقّقُ الإقنعةِ عينٌ لا مسح
- `SUP-09` (Suppliers/supplier_contract_lines.php): E3: كيانُه `suppliers` له حقولٌ حسّاسةٌ نافذةُ السياسة — تحقّقُ الإقنعةِ عينٌ لا مسح
- `SUP-18` (Suppliers/supplier_entitlements.php): E1: source_of_truth فارغٌ في السجلِّ المبنيّ · E3: كيانُه `suppliers` له حقولٌ حسّاسةٌ نافذةُ السياسة — تحقّقُ الإقنعةِ عينٌ لا مسح
- `SUP-20` (Suppliers/supplier_entitlements.php): E1: source_of_truth فارغٌ في السجلِّ المبنيّ · E3: كيانُه `suppliers` له حقولٌ حسّاسةٌ نافذةُ السياسة — تحقّقُ الإقنعةِ عينٌ لا مسح
- `SUP-26` (Suppliers/supplier_violations.php): E1: source_of_truth فارغٌ في السجلِّ المبنيّ
- `SUP-27` (Suppliers/supplier_evaluation.php): E3: كيانُه `suppliers` له حقولٌ حسّاسةٌ نافذةُ السياسة — تحقّقُ الإقنعةِ عينٌ لا مسح
- `TKT-01` (Tickets/gov_dept_crp.php): E1: source_of_truth فارغٌ في السجلِّ المبنيّ · E3: كيانُه `employees` له حقولٌ حسّاسةٌ نافذةُ السياسة — تحقّقُ الإقنعةِ عينٌ لا مسح

⛔ **المعاملاتُ لا تُغلق بأداة** — عقدُها بنصِّه يشمل تحقّقًا بشريًّا ومسارًا سالبًا يُشغَّل (تقدُّمُها الآليُّ: حارسٌ 182/182 · آلةُ حالةٍ 2/182).
