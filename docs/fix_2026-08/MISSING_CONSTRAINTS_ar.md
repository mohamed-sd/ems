# قيودٌ تدّعي الشيفرةُ وجودَها

**القياس:** 2026-08-11 23:49

الحيُّ في القاعدة: **67** CHECK · **335** UNIQUE · **252** FK
أسماءُ قيودٍ مذكورةٌ في الشجرة: **104** — منها **92** قائمةٌ · **5** مفقودةٌ حقًّا · 3 بادئةُ اسمٍ حيٍّ أطول · 4 ليست اسمَ قيدٍ أصلًا

### بادئةُ اسمٍ حيٍّ (لا فقد — يُستحسن توحيدُ الاسمِ في الشيفرة)
- `uq_price_term` ← الحيُّ `uq_price_term_scope [contract_price_terms]`
- `uq_stage_once` ← الحيُّ `uq_stage_once_per_round [unit_approvals]`
- `uq_svr` ← الحيُّ `uq_svr_canonical [screen_view_rows]`

### ليست اسمَ قيدٍ (ثابتٌ أو نصٌّ مبتور)
- `uq_sup_` — بادئةٌ مبتورةٌ في تسلسلِ نصوص
- `FK_VIOLATION` — ثابتُ شيفرةٍ لا اسمُ قيد
- `fk_sup_` — بادئةٌ مبتورةٌ في تسلسلِ نصوص
- `fk_permit_` — بادئةٌ مبتورةٌ في تسلسلِ نصوص

## قيودٌ تدّعي الشيفرةُ وجودَها وهي مفقودة

| # | القيد | النوعُ المرجَّح | يحرسه فاحص | مواضعُ الادّعاء |
|---|---|---|---|---|
| 1 | `chk_nav_door` | CHECK | لا | C:/wamp64/www/ems/tools/fix_extract_lost_constraints.php:43 · C:/wamp64/www/ems/tools/fix_lost_constraints_classify.php:63 · C:/wamp64/www/ems/tools/fix_lost_constraints_plan.php:33 · C:/wamp64/www/ems/docs/fix_2026-08/LOST_CONSTRAINTS_ar.md:118 … +10 |
| 2 | `uq_policy_rule` | UNIQUE | لا | C:/wamp64/www/ems/tools/fix_extract_lost_constraints.php:39 · C:/wamp64/www/ems/tools/fix_lost_constraints_classify.php:59 · C:/wamp64/www/ems/docs/CON02_CLIENT_CONTRACTS_GAP_REPORT_ar.md:206 · C:/wamp64/www/ems/docs/fix_2026-08/LOST_CONSTRAINTS_ar.md:131 … +2 |
| 3 | `uq_aw2` | UNIQUE | لا | C:/wamp64/www/ems/tools/act_seed_contracts.php:38 · C:/wamp64/www/ems/tools/fix_lost_constraints_classify.php:63 · C:/wamp64/www/ems/docs/fix_2026-08/LOST_CONSTRAINTS_ar.md:132 · C:/wamp64/www/ems/docs/fix_2026-08/MISSING_CONSTRAINTS_ar.md:43 |
| 4 | `ck_cps_due` | CHECK | لا | C:/wamp64/www/ems/docs/EXECUTION_LOG_update0001.md:66 · C:/wamp64/www/ems/docs/fix_2026-08/MISSING_CONSTRAINTS_ar.md:45 |
| 5 | `uq_modules_purpose` | UNIQUE | لا | C:/wamp64/www/ems/docs/fix_2026-08/MISSING_CONSTRAINTS_ar.md:47 · C:/wamp64/www/ems/docs/SCR01_SCREEN_REVIEW_AND_DEDUP_ar.md:228 |

◆ **اسمٌ مفقودٌ يحرسه فاحصٌ = حُمرةٌ مضمونةٌ في المجموعة.** وما لا يحرسه فاحصٌ
  أخطرُ: الشيفرةُ تتكئ عليه وصمتُه لا يُكشف حتى يقع المال في المكان الخطأ.
