# أمرُ الضبطِ §٥ — مسارُ الإغلاقِ بالدليل

> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/ctl_evidence_closure.php --md` · اللقطة `SNAP-df6965aa-20260830-163622`

| المفردة | العدد |
|---|---:|
| منفَّذٌ غيرُ مثبتٍ بأسطحِه | 188 |
| **أُغلق بالدليل** (E1..E4) | **0** |
| قراءةٌ سقطت في E4 | 22 |
| قراءةٌ مفتوحةٌ بسببٍ قبل E4 | 13 |
| معاملاتٌ (عقدُها يطلب عينًا ومسارًا سالبًا) | 153 |
| بلا نوعٍ في الدفتر | 0 |

## سقط في تحقّقِ العرضِ الفعليّ (E4)

- CEO-03 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9)
- CEO-04 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9)
- CEO-05 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9)
- CEO-06 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9)
- CEO-07 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9)
- CEO-12 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9)
- CEO-13 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9)
- CEO-14 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9)
- CEO-15 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9)
- CEO-17 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9)
- CEO-18 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9)
- CEO-19 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9)
- CEO-23 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9·15·33)
- CEO-25 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9)
- CEO-26 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9)
- GOV-01 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 1·15·19·26)
- GOV-05 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 1·15·19·26)
- GOV-26 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 15)
- IAF-01 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9·15·33)
- MY-04 — لا دورَ ممنوحًا له مستخدمٌ حيّ
- MY-05 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 1·2·3·4)
- VP-12 — STATUS:REDIRECT code=GOV-PERM-403 to=../main/dashboard.php msg=لا توجد صلاحية عرض هذه الشاشة (جُرِّبت الأدوارُ 9·15·33)

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
- `SUP-20` (Suppliers/supplier_entitlements.php): E1: source_of_truth فارغٌ في السجلِّ المبنيّ · E3: كيانُه `suppliers` له حقولٌ حسّاسةٌ نافذةُ السياسة — تحقّقُ الإقنعةِ عينٌ لا مسح
- `SUP-26` (Suppliers/supplier_violations.php): E1: source_of_truth فارغٌ في السجلِّ المبنيّ
- `SUP-27` (Suppliers/supplier_evaluation.php): E3: كيانُه `suppliers` له حقولٌ حسّاسةٌ نافذةُ السياسة — تحقّقُ الإقنعةِ عينٌ لا مسح
- `TKT-01` (Tickets/gov_dept_crp.php): E1: source_of_truth فارغٌ في السجلِّ المبنيّ · E3: كيانُه `employees` له حقولٌ حسّاسةٌ نافذةُ السياسة — تحقّقُ الإقنعةِ عينٌ لا مسح

⛔ **المعاملاتُ لا تُغلق بأداة** — عقدُها بنصِّه يشمل تحقّقًا بشريًّا ومسارًا سالبًا يُشغَّل (تقدُّمُها الآليُّ: حارسٌ 153/153 · آلةُ حالةٍ 2/153).
