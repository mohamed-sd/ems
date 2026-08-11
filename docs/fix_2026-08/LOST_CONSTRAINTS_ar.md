# القيودُ المفقودةُ — تصنيفٌ مقيس

**القياس:** 2026-08-11 23:24 · نسخةُ المقارنة: auto_pre_up_20260803_221911_equipation_manage.sql

| الصنف | العدد | المعنى |
|---|---|---|
| **LOST** | 27 | له تعريفٌ حرفيٌّ قبلَ 2026-08-03 وغائبٌ الآن ⇒ **يُرمَّم بنصِّه** |
| **PREFIX** | 3 | بادئةُ اسمٍ حيٍّ أطول ⇒ **وهمُ باحثٍ** لا فقد |
| **NEVER_BUILT** | 3 | لا تعريفَ في أيِّ نسخةٍ ⇒ مواصفةٌ لم تُبنَ · **لا تُخترع** |
| LIVE | 0 | قائمٌ فعلًا |

## ① LOST — تُرمَّم بنصِّها الأصلي

**`ck_container_alloc`** · جدول `op_containers` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_container_alloc` CHECK (((`allocated_qty` >= 0)
```
**`ck_container_parent`** · جدول `op_containers` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_container_parent` CHECK ((((`level` = _utf8mb4'رئيسية')
```
**`ck_container_consumed`** · جدول `op_containers` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_container_consumed` CHECK (((`consumed_qty` >= 0)
```
**`ck_swap_differs`** · جدول `container_swaps` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_swap_differs` CHECK (((`out_ref` is null)
```
**`ck_rotation_cycle`** · جدول `operator_rotations` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_rotation_cycle` CHECK ((`cycle_on_days` > 0)
```
**`ck_sa_standby_zero`** · جدول `seat_assignments` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_sa_standby_zero` CHECK (((`activation_state` = _utf8mb4'active')
```
**`ck_chp_superseded`** · جدول `contract_hour_policies` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_chp_superseded` CHECK (((`policy_state` <> _utf8mb4'superseded')
```
**`ck_sadv_inst`** · جدول `supplier_advance_requests` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_sadv_inst` CHECK (((`installments_count` >= 1)
```
**`ck_je_balanced`** · جدول `fin_journal_entries` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_je_balanced` CHECK ((round(`total_debit`,2)
```
**`ck_je_fx_pair`** · جدول `fin_journal_entries` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_je_fx_pair` CHECK ((((`fx_rate` is null)
```
**`ck_settlement_invoice_diff`** · جدول `settlements` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_settlement_invoice_diff` CHECK (((`invoice_diff` is null)
```
**`ck_led_qty_positive`** · جدول `capacity_consumption_ledger` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_led_qty_positive` CHECK ((`qty` >= 0)
```
**`ck_sup_line_standby`** · جدول `supplier_contract_lines` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_sup_line_standby` CHECK ((((`standby_basis` = _utf8mb4'none')
```
**`ck_alloc_target`** · جدول `fin_collection_allocations` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_alloc_target` CHECK (((`target_ref` > 0)
```
**`ck_cg_nature`** · جدول `contract_guarantees` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_cg_nature` CHECK ((((`kind` = _utf8mb4'cash_retention')
```
**`ck_cg_deduct`** · جدول `contract_guarantees` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_cg_deduct` CHECK (((`deductible_from_claim` = 0)
```
**`ck_cg_dates`** · جدول `contract_guarantees` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_cg_dates` CHECK ((((`kind` = _utf8mb4'cash_retention')
```
**`ck_cg_state_reason`** · جدول `contract_guarantees` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_cg_state_reason` CHECK (((`state` not in (_utf8mb4'released',_utf8mb4'called',_utf8mb4'expired')
```
**`ck_cps_treatment`** · جدول `contract_payment_schedule` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_cps_treatment` CHECK ((((`advance_type` is null)
```
**`ck_cps_advance_link`** · جدول `contract_payment_schedule` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_cps_advance_link` CHECK (((`advance_id` is null)
```
**`ck_pay_fx_pair`** · جدول `fin_payments` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_pay_fx_pair` CHECK ((((`fx_rate` is null)
```
**`ck_cb_actors`** · جدول `contract_baseline` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_cb_actors` CHECK ((((`state` <> _utf8mb4'reviewed')
```
**`ck_cle_effects`** · جدول `contract_lifecycle_events` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_cle_effects` CHECK ((((`state` = _utf8mb4'extension')
```
**`ck_cle_claim_article`** · جدول `contract_lifecycle_events` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_cle_claim_article` CHECK (((`claim_amount` is null)
```
**`ck_cle_decision`** · جدول `contract_lifecycle_events` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_cle_decision` CHECK (((`state` not in (_utf8mb4'natural_end',_utf8mb4'client_fault_end',_utf8mb4'our_fault_end',_utf8mb4'pre_start_cancel')
```
**`ck_cle_cancel_tree`** · جدول `contract_lifecycle_events` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `ck_cle_cancel_tree` CHECK (((`container_effect` <> _utf8mb4'cancel')
```
**`chk_nav_door`** · جدول `nav_items` · من `auto_pre_up_20260803_084927_equipation_manage.sql`
```sql
CONSTRAINT `chk_nav_door` CHECK ((`door` in (_utf8mb4'HOME',_utf8mb4'DAILY',_utf8mb4'APPR',_utf8mb4'REC',_utf8mb4'REP',_utf8mb4'SET',_utf8mb4'GOV',_utf8mb4'FIN')
```

## ② PREFIX — وهمُ الباحثِ (يُصلَح الباحثُ لا القاعدة)

- `uq_stage_once` ← الحيُّ `uq_stage_once_per_round [unit_approvals]`
- `uq_price_term` ← الحيُّ `uq_price_term_scope [contract_price_terms]`
- `uq_sup_` ← الحيُّ `uq_sup_capacity [supplier_capacity]`

## ③ NEVER_BUILT — ديْنٌ مُعلَنٌ لا اختراع

- `uq_policy_rule`
- `uq_aw2`
- `FK_VIOLATION`
