# حكمُ التبويباتِ فرادى — البندُ ⑦

> ⛔ **مولَّدٌ من المخزن**: `php tools/repair01_edc_tabs.php --md`
> **حكمُ المالك**: «لا نضحّي بالصلاحيةِ من أجلِ سايدبارٍ أنظف»
> — فكلُّ تبويبٍ يُحكم على حدة، **والمعيارُ من المنحِ الحيّ**:
> من يرى التبويبَ ولا يرى أباه **يفقد الوصولَ بالدمج**.

| الحكم | العدد | معناه |
|---|---:|---|
| `HIDDEN_WITH_LIVE_GRANT` | 22 | **مخفيٌّ من الملاحةِ والمنحُ حيّ** — فجوةُ وصولٍ سابقةٌ لهذه المرحلة |
| `PARENT_DOUBTFUL` | 9 | آمنٌ صلاحيًّا **وصفرُ جدولٍ مشترَك** — الأبوّةُ المسجَّلةُ مشكوكة |
| `MERGE_READY` | 13 | آمنٌ صلاحيًّا **وأبوّتُه مقيسةٌ بجدولٍ مشترَك** |
| `KEEP_ITEM` | 13 | **يبقى بندًا مستقلًّا** — الدمجُ يسلب أدوارًا وصولَها |

| التبويب | الإدارة | الأب | الحكم | السبب |
|---|---|---|---|---|
| `activities.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و1 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `client_contacts.php` | DEP-01 | `clients.php` | `PARENT_DOUBTFUL` | امن صلاحيا لكن صفر جدول مشترك مع clients.php — والتبويب يعمل على كيان ابيه · الابوة المسجلة مشكوكة ترفع ولا يبنى عليها |
| `client_profile.php` | DEP-01 | `clients.php` | `MERGE_READY` | كل من يراه (10 دورا) يرى اباه clients.php ويتشاركان 5 جدولا — لا فقد صلاحية وابوة مقيسة |
| `client_tree.php` | DEP-01 | `clients.php` | `MERGE_READY` | كل من يراه (10 دورا) يرى اباه clients.php ويتشاركان 4 جدولا — لا فقد صلاحية وابوة مقيسة |
| `contract_amendments.php` | DEP-01 | `contracts.php` | `MERGE_READY` | كل من يراه (1 دورا) يرى اباه contracts.php ويتشاركان 2 جدولا — لا فقد صلاحية وابوة مقيسة |
| `contract_baseline.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و4 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `contract_commitments.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و1 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `contract_events.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و1 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `contract_guarantees.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و4 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `contract_lifecycle.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و4 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `contract_lines.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و4 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `contract_monthly_plan.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و3 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `contract_obligations.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و5 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `contract_payment_schedule.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و4 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `contract_resource_plan.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و3 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `contract_sites.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و3 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `contracts_details.php` | DEP-01 | `commercial_board.php` | `KEEP_ITEM` | يراه بلا اب: ادوار 1,2,3,4,5,6,7,8,11 — والدمج يسلبها الوصول · ورفع صلاحية الاب توسيع وصول حي يقرره المالك |
| `penalties.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و7 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `plan_actual_link.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و3 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `price_terms.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و2 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `pricelists.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و1 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `quotation_lines.php` | DEP-01 | `quotations.php` | `PARENT_DOUBTFUL` | امن صلاحيا لكن صفر جدول مشترك مع quotations.php — والتبويب يعمل على كيان ابيه · الابوة المسجلة مشكوكة ترفع ولا يبنى عليها |
| `quotation_negotiation.php` | DEP-01 | `quotations.php` | `PARENT_DOUBTFUL` | امن صلاحيا لكن صفر جدول مشترك مع quotations.php — والتبويب يعمل على كيان ابيه · الابوة المسجلة مشكوكة ترفع ولا يبنى عليها |
| `readiness_lines.php` | DEP-01 | `quotations.php` | `MERGE_READY` | كل من يراه (1 دورا) يرى اباه quotations.php ويتشاركان 2 جدولا — لا فقد صلاحية وابوة مقيسة |
| `sites_board.php` | DEP-01 | `projects.php` | `MERGE_READY` | كل من يراه (1 دورا) يرى اباه projects.php ويتشاركان 2 جدولا — لا فقد صلاحية وابوة مقيسة |
| `swap_request.php` | DEP-01 | `sites_board.php` | `MERGE_READY` | كل من يراه (1 دورا) يرى اباه sites_board.php ويتشاركان 3 جدولا — لا فقد صلاحية وابوة مقيسة |
| `tenders.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و1 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `unit_client_match.php` | DEP-01 | `contracts.php` | `PARENT_DOUBTFUL` | امن صلاحيا لكن صفر جدول مشترك مع contracts.php — والتبويب يعمل على كيان ابيه · الابوة المسجلة مشكوكة ترفع ولا يبنى عليها |
| `unit_statement_client.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و3 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `units_of_measure.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و1 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `sup_handover.php` | DEP-02 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و3 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `supplier_advances.php` | DEP-02 | `settlements.php` | `MERGE_READY` | كل من يراه (3 دورا) يرى اباه settlements.php ويتشاركان 2 جدولا — لا فقد صلاحية وابوة مقيسة |
| `supplier_closure.php` | DEP-02 | `supplierscontracts.php` | `KEEP_ITEM` | يراه بلا اب: ادوار 17 — والدمج يسلبها الوصول · ورفع صلاحية الاب توسيع وصول حي يقرره المالك |
| `supplier_contacts.php` | DEP-02 | `suppliers.php` | `PARENT_DOUBTFUL` | امن صلاحيا لكن صفر جدول مشترك مع suppliers.php — والتبويب يعمل على كيان ابيه · الابوة المسجلة مشكوكة ترفع ولا يبنى عليها |
| `supplier_contract_lines.php` | DEP-02 | `supplierscontracts.php` | `KEEP_ITEM` | يراه بلا اب: ادوار 17 — والدمج يسلبها الوصول · ورفع صلاحية الاب توسيع وصول حي يقرره المالك |
| `supplier_documents.php` | DEP-02 | `suppliers.php` | `KEEP_ITEM` | يراه بلا اب: ادوار 17 — والدمج يسلبها الوصول · ورفع صلاحية الاب توسيع وصول حي يقرره المالك |
| `supplier_evaluation.php` | DEP-02 | `suppliers.php` | `KEEP_ITEM` | يراه بلا اب: ادوار 17 — والدمج يسلبها الوصول · ورفع صلاحية الاب توسيع وصول حي يقرره المالك |
| `supplier_rules.php` | DEP-02 | `supplierscontracts.php` | `KEEP_ITEM` | يراه بلا اب: ادوار 17 — والدمج يسلبها الوصول · ورفع صلاحية الاب توسيع وصول حي يقرره المالك |
| `supplier_violations.php` | DEP-02 | `settlements.php` | `PARENT_DOUBTFUL` | امن صلاحيا لكن صفر جدول مشترك مع settlements.php — والتبويب يعمل على كيان ابيه · الابوة المسجلة مشكوكة ترفع ولا يبنى عليها |
| `supplierscontracts_details.php` | DEP-02 | `supplierscontracts.php` | `MERGE_READY` | كل من يراه (6 دورا) يرى اباه supplierscontracts.php ويتشاركان 3 جدولا — لا فقد صلاحية وابوة مقيسة |
| `asset_disposal.php` | DEP-03 | `owners_registry.php` | `MERGE_READY` | كل من يراه (5 دورا) يرى اباه owners_registry.php ويتشاركان 5 جدولا — لا فقد صلاحية وابوة مقيسة |
| `operation_profile.php` | DEP-03 | `installments.php` | `MERGE_READY` | كل من يراه (1 دورا) يرى اباه installments.php ويتشاركان 3 جدولا — لا فقد صلاحية وابوة مقيسة |
| `equipment_documents.php` | DEP-04 | `equipments.php` | `KEEP_ITEM` | يراه بلا اب: ادوار 8 — والدمج يسلبها الوصول · ورفع صلاحية الاب توسيع وصول حي يقرره المالك |
| `equipment_sourcing.php` | DEP-04 | `equipments.php` | `PARENT_DOUBTFUL` | امن صلاحيا لكن صفر جدول مشترك مع equipments.php — والتبويب يعمل على كيان ابيه · الابوة المسجلة مشكوكة ترفع ولا يبنى عليها |
| `client_statement.php` | DEP-05 | `clients.php` | `KEEP_ITEM` | يراه بلا اب: ادوار 17,19,22 — والدمج يسلبها الوصول · ورفع صلاحية الاب توسيع وصول حي يقرره المالك |
| `contracts.php` | DEP-05 | `clients.php` | `KEEP_ITEM` | يراه بلا اب: ادوار 9,17,18,19,20,21,22,26,27 — والدمج يسلبها الوصول · ورفع صلاحية الاب توسيع وصول حي يقرره المالك |
| `operator_pay_fin.php` | DEP-05 | `operator_pay_policies_fin.php` | `MERGE_READY` | كل من يراه (6 دورا) يرى اباه operator_pay_policies_fin.php ويتشاركان 2 جدولا — لا فقد صلاحية وابوة مقيسة |
| `orders.php` | DEP-05 | `equipments.php` | `KEEP_ITEM` | يراه بلا اب: ادوار 8,17,18,19,20,21,22 — والدمج يسلبها الوصول · ورفع صلاحية الاب توسيع وصول حي يقرره المالك |
| `supplier_entitlements.php` | DEP-05 | `settlements.php` | `PARENT_DOUBTFUL` | امن صلاحيا لكن صفر جدول مشترك مع settlements.php — والتبويب يعمل على كيان ابيه · الابوة المسجلة مشكوكة ترفع ولا يبنى عليها |
| `supplier_profile.php` | DEP-05 | `shares_coverage.php` | `KEEP_ITEM` | يراه بلا اب: ادوار 3,4,5,6,11,12 — والدمج يسلبها الوصول · ورفع صلاحية الاب توسيع وصول حي يقرره المالك |
| `supplier_statement_fin.php` | DEP-05 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و8 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `timesheet.php` | DEP-05 | `timesheet_type.php` | `KEEP_ITEM` | يراه بلا اب: ادوار 7,10,13,14,17,18,19,20,21,22,27 — والدمج يسلبها الوصول · ورفع صلاحية الاب توسيع وصول حي يقرره المالك |
| `timesheet.php` | DEP-12 | `—` | `HIDDEN_WITH_LIVE_GRANT` | مخفي من الملاحة (active=0) و19 دورا يملك رؤيته — فجوة وصول سابقة لهذه المرحلة · لا اب مسجل ولا مشتق (0 مرشحا) · الخروج بسحب المنح او اعادة الاظهار بقرار مالك |
| `oprators.php` | DEP-13 | `select_project.php` | `PARENT_DOUBTFUL` | امن صلاحيا لكن صفر جدول مشترك مع select_project.php — والتبويب يعمل على كيان ابيه · الابوة المسجلة مشكوكة ترفع ولا يبنى عليها |
| `transfer_order_form.php` | DEP-15 | `transfer_dashboard.php` | `MERGE_READY` | كل من يراه (1 دورا) يرى اباه transfer_dashboard.php ويتشاركان 3 جدولا — لا فقد صلاحية وابوة مقيسة |
| `warehouses.php` | DEP-17 | `orders.php` | `KEEP_ITEM` | يراه بلا اب: ادوار 9,15,16,25 — والدمج يسلبها الوصول · ورفع صلاحية الاب توسيع وصول حي يقرره المالك |
| `exceptions_report.php` | EX-CEO | `approval_lag_report.php` | `MERGE_READY` | كل من يراه (3 دورا) يرى اباه approval_lag_report.php ويتشاركان 1 جدولا — لا فقد صلاحية وابوة مقيسة |
