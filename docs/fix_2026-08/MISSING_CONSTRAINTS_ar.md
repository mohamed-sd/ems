# قيودٌ تدّعي الشيفرةُ وجودَها

**القياس:** 2026-08-11 23:20

الحيُّ في القاعدة: **41** CHECK · **335** UNIQUE · **252** FK
أسماءُ قيودٍ مذكورةٌ في الشجرة: **102** — منها **64** قائمةٌ و**38** مفقودة

## قيودٌ تدّعي الشيفرةُ وجودَها وهي مفقودة

| # | القيد | النوعُ المرجَّح | يحرسه فاحص | مواضعُ الادّعاء |
|---|---|---|---|---|
| 1 | `ck_container_alloc` | CHECK | لا | C:/wamp64/www/ems/app/Services/Operations/OperationalTransformService.php:9 · C:/wamp64/www/ems/tests/containers_structure_test.php:143 · C:/wamp64/www/ems/tests/container_pilot_test.php:69 · C:/wamp64/www/ems/Operations/containers.php:10 … +2 |
| 2 | `chk_nav_door` | CHECK | لا | C:/wamp64/www/ems/docs/UPDATE0004_AUTONOMOUS_PROMPT_ar.md:193 · C:/wamp64/www/ems/docs/UPDATE0004_EXECUTION_LOG.md:177 · C:/wamp64/www/ems/docs/UPDATE0004_EXECUTION_PROMPT_ar.md:63 · C:/wamp64/www/ems/docs/UPDATE0004_EXECUTION_PROMPT_ar.md:143 … +2 |
| 3 | `ck_sa_standby_zero` | CHECK | لا | C:/wamp64/www/ems/app/Services/Capacity/SeatAssignmentService.php:106 · C:/wamp64/www/ems/tests/cap_w1_standby_fields_test.php:97 · C:/wamp64/www/ems/tests/cap_w1_standby_fields_test.php:98 · C:/wamp64/www/ems/docs/UPDATE0005_EXECUTION_LOG.md:9 |
| 4 | `ck_container_parent` | CHECK | لا | C:/wamp64/www/ems/tests/containers_structure_test.php:169 · C:/wamp64/www/ems/tests/containers_structure_test.php:172 · C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:83 · C:/wamp64/www/ems/docs/UPDATE0001_SPRINT_01_EXECUTION_PROMPT_ar.md:154 |
| 5 | `FK_VIOLATION` | CHECK | لا | C:/wamp64/www/ems/tests/fx_currency_test.php:162 · C:/wamp64/www/ems/tests/fx_currency_test.php:167 · C:/wamp64/www/ems/tests/fx_currency_test.php:175 |
| 6 | `ck_chp_superseded` | CHECK | لا | C:/wamp64/www/ems/tests/pay_policy_state_http_proof.php:72 · C:/wamp64/www/ems/tests/pay_policy_state_test.php:52 · C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:76 |
| 7 | `ck_cg_dates` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:58 · C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:65 · C:/wamp64/www/ems/docs/specs/P-06_contract_guarantees.md:33 |
| 8 | `ck_sadv_inst` | CHECK | لا | C:/wamp64/www/ems/tests/supplier_closure_test.php:158 · C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:83 |
| 9 | `uq_stage_once` | UNIQUE | لا | C:/wamp64/www/ems/tests/timesheet_entry_service_test.php:230 · C:/wamp64/www/ems/docs/SESSION_HANDOFF_20260726_ar.md:156 |
| 10 | `ck_settlement_invoice_diff` | CHECK | لا | C:/wamp64/www/ems/docs/ARCHITECTURE_CURRENT_SYSTEM_v11_ar.md:86 · C:/wamp64/www/ems/docs/UPDATE0001_REVERSE_AUDIT_ar.md:58 |
| 11 | `uq_policy_rule` | UNIQUE | لا | C:/wamp64/www/ems/docs/CON02_CLIENT_CONTRACTS_GAP_REPORT_ar.md:206 · C:/wamp64/www/ems/docs/PROMPT_CON02_MEDIUM_TIER_ar.md:64 |
| 12 | `ck_cle_effects` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:60 · C:/wamp64/www/ems/docs/specs/P-11_lifecycle_economics.md:26 |
| 13 | `ck_cle_claim_article` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:60 · C:/wamp64/www/ems/docs/specs/P-11_lifecycle_economics.md:27 |
| 14 | `ck_cle_decision` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:60 · C:/wamp64/www/ems/docs/specs/P-11_lifecycle_economics.md:28 |
| 15 | `ck_cle_cancel_tree` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:60 · C:/wamp64/www/ems/docs/specs/P-11_lifecycle_economics.md:29 |
| 16 | `ck_cb_actors` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:61 · C:/wamp64/www/ems/docs/specs/P-10_contract_baseline.md:26 |
| 17 | `ck_alloc_target` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:64 · C:/wamp64/www/ems/docs/specs/P-07_allocation_targets.md:33 |
| 18 | `ck_cg_nature` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:65 · C:/wamp64/www/ems/docs/specs/P-06_contract_guarantees.md:31 |
| 19 | `ck_cg_deduct` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:65 · C:/wamp64/www/ems/docs/specs/P-06_contract_guarantees.md:32 |
| 20 | `ck_cg_state_reason` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:65 · C:/wamp64/www/ems/docs/specs/P-06_contract_guarantees.md:34 |
| 21 | `ck_cps_treatment` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:66 · C:/wamp64/www/ems/docs/specs/P-05_contract_payment_schedule.md:42 |
| 22 | `ck_cps_advance_link` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:66 · C:/wamp64/www/ems/docs/specs/P-05_contract_payment_schedule.md:42 |
| 23 | `ck_pay_fx_pair` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:87 · C:/wamp64/www/ems/docs/specs/M-14_supplier_statement.md:50 |
| 24 | `uq_price_term` | UNIQUE | لا | C:/wamp64/www/ems/app/Services/Contract/PriceAdjustmentService.php:148 |
| 25 | `ck_sup_line_standby` | CHECK | لا | C:/wamp64/www/ems/app/Services/Contract/SupplierContractService.php:361 |
| 26 | `uq_sup_` | UNIQUE | لا | C:/wamp64/www/ems/app/Services/Contract/SupplierContractService.php:606 |
| 27 | `ck_container_consumed` | CHECK | لا | C:/wamp64/www/ems/tests/containers_structure_test.php:154 |
| 28 | `ck_swap_differs` | CHECK | لا | C:/wamp64/www/ems/tests/containers_structure_test.php:206 |
| 29 | `ck_rotation_cycle` | CHECK | لا | C:/wamp64/www/ems/tests/containers_structure_test.php:212 |
| 30 | `ck_je_balanced` | CHECK | لا | C:/wamp64/www/ems/tests/journal_head_columns_test.php:15 |
| 31 | `ck_je_fx_pair` | CHECK | لا | C:/wamp64/www/ems/tests/journal_head_columns_test.php:16 |
| 32 | `uq_aw2` | UNIQUE | لا | C:/wamp64/www/ems/tools/act_seed_contracts.php:38 |
| 33 | `ck_led_qty_positive` | CHECK | لا | C:/wamp64/www/ems/tools/seed_update0007_demo.php:362 |
| 34 | `ck_cps_due` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:66 |
| 35 | `fk_sup_` | FK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:86 |
| 36 | `uq_modules_purpose` | UNIQUE | لا | C:/wamp64/www/ems/docs/SCR01_SCREEN_REVIEW_AND_DEDUP_ar.md:228 |
| 37 | `fk_permit_` | FK | لا | C:/wamp64/www/ems/docs/UPDATE0004_JUDGMENT_LOG.md:15 |
| 38 | `uq_svr` | UNIQUE | لا | C:/wamp64/www/ems/docs/UPDATE0007_EXECUTION_LOG.md:45 |

◆ **اسمٌ مفقودٌ يحرسه فاحصٌ = حُمرةٌ مضمونةٌ في المجموعة.** وما لا يحرسه فاحصٌ
  أخطرُ: الشيفرةُ تتكئ عليه وصمتُه لا يُكشف حتى يقع المال في المكان الخطأ.
