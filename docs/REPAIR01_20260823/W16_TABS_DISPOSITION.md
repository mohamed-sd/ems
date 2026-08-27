# الدَّينُ الموروثُ `DC-13` — سبعةٌ وخمسون تبويبًا بحكمٍ مسجَّلٍ فرادى

> ⛔ **مولَّدٌ من المخزن**: `php tools/repair01_w16_docs.php`
> **حكمُ المالك**: «لا نضحّي بالصلاحيةِ من أجلِ سايدبارٍ أنظف — والثمانيةُ والخمسون تُحكم فرادى».
> **ومعيارُ الخروج**: «كلُّ تبويبٍ يُحكم على حدة **بقرارٍ مسجَّل**» — فهذا الدفترُ هو معيارُ الخروجِ نفسُه.
> ⛔ **ولم يُدمَج بندٌ ولم يُخفَ**: الدمجُ تغييرُ ملاحةٍ حيٍّ بقرارِ مالك، **والمسجَّلُ هنا حكمٌ لا تنفيذ**.

| الحقل | القيمة |
|---|---|
| `Snapshot ID` | `SNAP-02224261-20260827-145545` |
| `Commit Hash` | `f8b6da6ab5a24d3dabb6ae943a2a808955d66f3d` |
| `Measured At` | 2026-08-27 01:07:31 |

| التصرُّفُ المسجَّل | العدد | معناه |
|---|---:|---|
| `KEEP_ITEM` | 13 | يبقى بندًا مستقلًّا — الدمجُ يسلب أدوارًا وصولَها |
| `MERGE_INTO_PARENT` | 13 | مؤهَّلٌ للدمجِ صلاحيًّا وأبوّتُه مقيسةٌ — **والتنفيذُ خطوةٌ تالية** |
| `PARENT_RAISED` | 9 | الأبوّةُ المسجَّلةُ مشكوكةٌ تُرفع ولا يُبنى عليها |
| `GRANT_GAP_TO_OWNER` | 22 | مخفيٌّ والمنحُ حيّ — **فجوةُ وصولٍ سابقةٌ** خروجُها بقرارِ مالك |

| التبويب | الإدارة | الأب | حكمُ الأداة | التصرُّفُ المسجَّل | مرجعُ المالك | السبب |
|---|---|---|---|---|---|---|
| `activities.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `client_contacts.php` | DEP-01 | `clients.php` | `PARENT_DOUBTFUL` | `PARENT_RAISED` | — | امن صلاحيا لكن صفر جدول مشترك م |
| `client_profile.php` | DEP-01 | `clients.php` | `MERGE_READY` | `MERGE_INTO_PARENT` | — | كل من يراه (10 دورا) يرى اباه clien |
| `client_tree.php` | DEP-01 | `clients.php` | `MERGE_READY` | `MERGE_INTO_PARENT` | — | كل من يراه (10 دورا) يرى اباه clien |
| `contract_amendments.php` | DEP-01 | `contracts.php` | `MERGE_READY` | `MERGE_INTO_PARENT` | — | كل من يراه (1 دورا) يرى اباه contra |
| `contract_baseline.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `contract_commitments.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `contract_events.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `contract_guarantees.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `contract_lifecycle.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `contract_lines.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `contract_monthly_plan.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `contract_obligations.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `contract_payment_schedule.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `contract_resource_plan.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `contract_sites.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `contracts_details.php` | DEP-01 | `commercial_board.php` | `KEEP_ITEM` | `KEEP_ITEM` | — | يراه بلا اب: ادوار 1,2,3,4,5,6,7,8,11 |
| `penalties.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `plan_actual_link.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `price_terms.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `pricelists.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `quotation_lines.php` | DEP-01 | `quotations.php` | `PARENT_DOUBTFUL` | `PARENT_RAISED` | — | امن صلاحيا لكن صفر جدول مشترك م |
| `quotation_negotiation.php` | DEP-01 | `quotations.php` | `PARENT_DOUBTFUL` | `PARENT_RAISED` | — | امن صلاحيا لكن صفر جدول مشترك م |
| `readiness_lines.php` | DEP-01 | `quotations.php` | `MERGE_READY` | `MERGE_INTO_PARENT` | — | كل من يراه (1 دورا) يرى اباه quotat |
| `sites_board.php` | DEP-01 | `projects.php` | `MERGE_READY` | `MERGE_INTO_PARENT` | — | كل من يراه (1 دورا) يرى اباه projec |
| `swap_request.php` | DEP-01 | `sites_board.php` | `MERGE_READY` | `MERGE_INTO_PARENT` | — | كل من يراه (1 دورا) يرى اباه sites_ |
| `tenders.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `unit_client_match.php` | DEP-01 | `contracts.php` | `PARENT_DOUBTFUL` | `PARENT_RAISED` | — | امن صلاحيا لكن صفر جدول مشترك م |
| `unit_statement_client.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `units_of_measure.php` | DEP-01 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `sup_handover.php` | DEP-02 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `supplier_advances.php` | DEP-02 | `settlements.php` | `MERGE_READY` | `MERGE_INTO_PARENT` | — | كل من يراه (3 دورا) يرى اباه settle |
| `supplier_closure.php` | DEP-02 | `supplierscontracts.php` | `KEEP_ITEM` | `KEEP_ITEM` | — | يراه بلا اب: ادوار 17 — والدمج يسلبها |
| `supplier_contacts.php` | DEP-02 | `suppliers.php` | `PARENT_DOUBTFUL` | `PARENT_RAISED` | — | امن صلاحيا لكن صفر جدول مشترك م |
| `supplier_contract_lines.php` | DEP-02 | `supplierscontracts.php` | `KEEP_ITEM` | `KEEP_ITEM` | — | يراه بلا اب: ادوار 17 — والدمج يسلبها |
| `supplier_documents.php` | DEP-02 | `suppliers.php` | `KEEP_ITEM` | `KEEP_ITEM` | — | يراه بلا اب: ادوار 17 — والدمج يسلبها |
| `supplier_evaluation.php` | DEP-02 | `suppliers.php` | `KEEP_ITEM` | `KEEP_ITEM` | — | يراه بلا اب: ادوار 17 — والدمج يسلبها |
| `supplier_rules.php` | DEP-02 | `supplierscontracts.php` | `KEEP_ITEM` | `KEEP_ITEM` | — | يراه بلا اب: ادوار 17 — والدمج يسلبها |
| `supplier_violations.php` | DEP-02 | `settlements.php` | `PARENT_DOUBTFUL` | `PARENT_RAISED` | — | امن صلاحيا لكن صفر جدول مشترك م |
| `supplierscontracts_details.php` | DEP-02 | `supplierscontracts.php` | `MERGE_READY` | `MERGE_INTO_PARENT` | — | كل من يراه (6 دورا) يرى اباه suppli |
| `asset_disposal.php` | DEP-03 | `owners_registry.php` | `MERGE_READY` | `MERGE_INTO_PARENT` | — | كل من يراه (5 دورا) يرى اباه owners |
| `operation_profile.php` | DEP-03 | `installments.php` | `MERGE_READY` | `MERGE_INTO_PARENT` | — | كل من يراه (1 دورا) يرى اباه instal |
| `equipment_documents.php` | DEP-04 | `equipments.php` | `KEEP_ITEM` | `KEEP_ITEM` | — | يراه بلا اب: ادوار 8 — والدمج يسلبها |
| `equipment_sourcing.php` | DEP-04 | `equipments.php` | `PARENT_DOUBTFUL` | `PARENT_RAISED` | — | امن صلاحيا لكن صفر جدول مشترك م |
| `client_statement.php` | DEP-05 | `clients.php` | `KEEP_ITEM` | `KEEP_ITEM` | — | يراه بلا اب: ادوار 17,19,22 — والدمج |
| `contracts.php` | DEP-05 | `clients.php` | `KEEP_ITEM` | `KEEP_ITEM` | — | يراه بلا اب: ادوار 9,17,18,19,20,21,2 |
| `operator_pay_fin.php` | DEP-05 | `operator_pay_policies_fin.php` | `MERGE_READY` | `MERGE_INTO_PARENT` | — | كل من يراه (6 دورا) يرى اباه operat |
| `orders.php` | DEP-05 | `equipments.php` | `KEEP_ITEM` | `KEEP_ITEM` | — | يراه بلا اب: ادوار 8,17,18,19,20,21,2 |
| `supplier_entitlements.php` | DEP-05 | `settlements.php` | `PARENT_DOUBTFUL` | `PARENT_RAISED` | — | امن صلاحيا لكن صفر جدول مشترك م |
| `supplier_profile.php` | DEP-05 | `shares_coverage.php` | `KEEP_ITEM` | `KEEP_ITEM` | — | يراه بلا اب: ادوار 3,4,5,6,11,12 — وا |
| `supplier_statement_fin.php` | DEP-05 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `timesheet.php` | DEP-05 | `timesheet_type.php` | `KEEP_ITEM` | `KEEP_ITEM` | — | يراه بلا اب: ادوار 7,10,13,14,17,18,1 |
| `timesheet.php` | DEP-12 | `—` | `HIDDEN_WITH_LIVE_GRANT` | `GRANT_GAP_TO_OWNER` | `W16-P-01` | مخفي من الملاحة (active= |
| `oprators.php` | DEP-13 | `select_project.php` | `PARENT_DOUBTFUL` | `PARENT_RAISED` | — | امن صلاحيا لكن صفر جدول مشترك م |
| `transfer_order_form.php` | DEP-15 | `transfer_dashboard.php` | `MERGE_READY` | `MERGE_INTO_PARENT` | — | كل من يراه (1 دورا) يرى اباه transf |
| `warehouses.php` | DEP-17 | `orders.php` | `KEEP_ITEM` | `KEEP_ITEM` | — | يراه بلا اب: ادوار 9,15,16,25 — والدم |
| `exceptions_report.php` | EX-CEO | `approval_lag_report.php` | `MERGE_READY` | `MERGE_INTO_PARENT` | — | كل من يراه (3 دورا) يرى اباه approv |
