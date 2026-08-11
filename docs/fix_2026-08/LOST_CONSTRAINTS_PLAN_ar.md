# القيودُ المفقودةُ — خطةُ الترميم

**القياس:** 2026-08-11 23:26 · المصدر: `auto_pre_up_20260803_084927_equipation_manage.sql`

| # | القيد | الجدول | صفوفُ الجدول | **مخالفون** | الحال |
|---|---|---|---|---|---|
| 1 | `ck_container_alloc` | `op_containers` | 776 | **0** ✔ | يُرمَّم الآن |
| 2 | `ck_container_parent` | `op_containers` | 776 | **0** ✔ | يُرمَّم الآن |
| 3 | `ck_container_consumed` | `op_containers` | 776 | **0** ✔ | يُرمَّم الآن |
| 4 | `ck_swap_differs` | `container_swaps` | 0 | **0** ✔ | يُرمَّم الآن |
| 5 | `ck_rotation_cycle` | `operator_rotations` | 0 | **0** ✔ | يُرمَّم الآن |
| 6 | `ck_sa_standby_zero` | `seat_assignments` | 20 | **0** ✔ | يُرمَّم الآن |
| 7 | `ck_chp_superseded` | `contract_hour_policies` | 49 | **0** ✔ | يُرمَّم الآن |
| 8 | `ck_sadv_inst` | `supplier_advance_requests` | 20 | **0** ✔ | يُرمَّم الآن |
| 9 | `ck_je_balanced` | `fin_journal_entries` | 1653 | **0** ✔ | يُرمَّم الآن |
| 10 | `ck_je_fx_pair` | `fin_journal_entries` | 1653 | **0** ✔ | يُرمَّم الآن |
| 11 | `ck_settlement_invoice_diff` | `settlements` | 320 | **0** ✔ | يُرمَّم الآن |
| 12 | `ck_led_qty_positive` | `capacity_consumption_ledger` | 50 | **0** ✔ | يُرمَّم الآن |
| 13 | `ck_sup_line_standby` | `supplier_contract_lines` | 20 | **0** ✔ | يُرمَّم الآن |
| 14 | `ck_alloc_target` | `fin_collection_allocations` | 214 | **0** ✔ | يُرمَّم الآن |
| 15 | `ck_cg_nature` | `contract_guarantees` | 20 | **0** ✔ | يُرمَّم الآن |
| 16 | `ck_cg_deduct` | `contract_guarantees` | 20 | **0** ✔ | يُرمَّم الآن |
| 17 | `ck_cg_dates` | `contract_guarantees` | 20 | **0** ✔ | يُرمَّم الآن |
| 18 | `ck_cg_state_reason` | `contract_guarantees` | 20 | **0** ✔ | يُرمَّم الآن |
| 19 | `ck_cps_treatment` | `contract_payment_schedule` | 21 | **0** ✔ | يُرمَّم الآن |
| 20 | `ck_cps_advance_link` | `contract_payment_schedule` | 21 | **0** ✔ | يُرمَّم الآن |
| 21 | `ck_pay_fx_pair` | `fin_payments` | 1063 | **0** ✔ | يُرمَّم الآن |
| 22 | `ck_cb_actors` | `contract_baseline` | 20 | **0** ✔ | يُرمَّم الآن |
| 23 | `ck_cle_effects` | `contract_lifecycle_events` | 20 | **0** ✔ | يُرمَّم الآن |
| 24 | `ck_cle_claim_article` | `contract_lifecycle_events` | 20 | **0** ✔ | يُرمَّم الآن |
| 25 | `ck_cle_decision` | `contract_lifecycle_events` | 20 | **0** ✔ | يُرمَّم الآن |
| 26 | `ck_cle_cancel_tree` | `contract_lifecycle_events` | 20 | **0** ✔ | يُرمَّم الآن |
| 27 | `chk_nav_door` | `nav_items` | 1633 | **90** ⚠ | يُعلَن — لا تُمَسُّ بيانةٌ مالية |

**نظيفٌ يُرمَّم فورًا: 26** · بمخالفينَ يُعلَن: 1 · جدولٌ غيرُ موجود: 0

## قيودٌ لها مخالفونَ أحياء — قرارُ مالك

إضافتُها تفشل، وإرضاؤها يعني **تعديلَ بياناتٍ ماليةٍ قائمة**. فتُعرَض:

- **`chk_nav_door`** · `nav_items` · **90 مخالفًا** من 1633
  - الشرط: `(`door` in (_utf8mb4'HOME',_utf8mb4'DAILY',_utf8mb4'APPR',_utf8mb4'REC',_utf8mb4'REP',_utf8mb4'SET',_utf8mb4'GOV',_utf8mb4'FIN'))`
