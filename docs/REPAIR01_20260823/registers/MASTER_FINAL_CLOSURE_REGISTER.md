# MASTER FINAL CLOSURE REGISTER — السجلُّ الجامعُ للإغلاقِ النهائيّ

> **المخزنُ الحاكم:** `registers/MASTER_FINAL_CLOSURE_REGISTER.json` — وهذا إسقاطُه.
> **اللقطة:** `59136c85` · **حُدِّث:** 2026-08-31 12:20
> **المصمَّمُ الحاكم:** `docs/REPAIR01_20260823/orders/CLOSURE_SYSTEM.txt`

## مقامُ الحالات — ⛔ ولا تُجمَع في نسبةٍ واحدة

| الحالة | العدد |
|---|---|
| `BLOCKED_ENVIRONMENT` | 1 |
| `BLOCKED_GOVERNING_SOURCE` | 1 |
| `BLOCKED_OWNER` | 22 |
| `BLOCKED_UAT` | 2 |
| `EVIDENCE_CLOSED` | 78 |
| `IMPLEMENTED_NOT_VERIFIED` | 23 |
| `OPEN` | 28 |
| **الجملة** | **155** |

## البنودُ غيرُ المغلقة — بحاجزِها وفعلِها التالي

| Closure_ID | الحالة | الأولوية | الحاجز | المصدر | اللقطةُ الحالية | الفعلُ التالي |
|---|---|---|---|---|---|---|
| `CL-WORK-01` | `BLOCKED_ENVIRONMENT` | P0 | — | docs/REPAIR01_20260823/orders/CLOSURE_SYSTEM.txt | محلي 59136c85 متقدم عن البعيد 96a640e1 — الدفع مسلم للمستخدم @ 59136c85 |  |
| `CL-GAP-07` | `OPEN` | P0 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ 808a2c03 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-FR-APP-001` | `IMPLEMENTED_NOT_VERIFIED` | P0 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-APP-002` | `BLOCKED_OWNER` | P0 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ 808a2c03 |  |
| `CL-FR-APP-004` | `BLOCKED_OWNER` | P0 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ 808a2c03 |  |
| `CL-FR-DAT-001` | `BLOCKED_OWNER` | P0 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ 808a2c03 |  |
| `CL-FR-GOV-009` | `OPEN` | P0 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=OPEN @ 808a2c03 |  |
| `CL-FR-GOV-015` | `OPEN` | P0 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=OPEN @ 808a2c03 |  |
| `CL-GAP-17` | `OPEN` | P1 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ 808a2c03 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-GAP-18` | `OPEN` | P1 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ 808a2c03 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-GAP-23` | `OPEN` | P1 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ 808a2c03 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-GAP-29` | `OPEN` | P1 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ 808a2c03 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-FR-APP-005` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-APP-007` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-APP-008` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ 808a2c03 |  |
| `CL-FR-SEC-003` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-SEC-008` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-FIN-005` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-JRN-001` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ 808a2c03 |  |
| `CL-FR-NAV-001` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-APP-006` | `BLOCKED_GOVERNING_SOURCE` | P1 | GOVERNING_SOURCE | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_GOVERNING_SOURCE @ 808a2c03 |  |
| `CL-FR-APP-009` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ 808a2c03 |  |
| `CL-FR-APP-010` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ 808a2c03 |  |
| `CL-FR-APP-011` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ 808a2c03 |  |
| `CL-FR-SEC-006` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-SEC-007` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-FIN-004` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ 808a2c03 |  |
| `CL-FR-FIN-006` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-FIN-007` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-EVT-004` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-JRN-002` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ 808a2c03 |  |
| `CL-FR-JRN-004` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ 808a2c03 |  |
| `CL-FR-JRN-005` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-JRN-006` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-NAV-002` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-NAV-004` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-NAV-006` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-APP-012` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ 808a2c03 |  |
| `CL-FR-GOV-007` | `OPEN` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=OPEN @ 808a2c03 |  |
| `CL-FR-GOV-008` | `OPEN` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=OPEN @ 808a2c03 |  |
| `CL-FR-GOV-010` | `OPEN` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=OPEN @ 808a2c03 |  |
| `CL-FR-GOV-012` | `OPEN` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=OPEN @ 808a2c03 |  |
| `CL-FR-GOV-014` | `OPEN` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=OPEN @ 808a2c03 |  |
| `CL-FR-GOV-016` | `OPEN` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=OPEN @ 808a2c03 |  |
| `CL-R2-T13` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/RPR02_TARGET_UNIVERSE.md | شاشة PLATFORM بلا تبرير منصي معتمد = 0 (المقيس 12) @ 808a2c03 |  |
| `CL-R2-T16` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/RPR02_TARGET_UNIVERSE.md | اسم معروض غير معتمد = 0 (المقيس 3 منها 2 PENDING_OWNER) @ 808a2c03 |  |
| `CL-R3-M08` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/RPR03_SCORECARD.md | أسطح PLATFORM بلا تبرير = 0 (المقيس 12) @ 808a2c03 |  |
| `CL-R3-M11` | `OPEN` | P1 | — | docs/REPAIR01_20260823/RPR03_SCORECARD.md | قيود يدوية بلا مصدر = 0 (المقيس 1644) @ 808a2c03 |  |
| `CL-OA-01` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/open/DEC-OPEN-15.md | قوائم تتبع الأصناف الثلاث: Lot · Serial · Expiry @ 808a2c03 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-OA-02` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/open/DEC-OPEN-16.md | من يملك التحقيق؟ (الحوكمة أم المراجعة الداخلية) @ 808a2c03 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-OA-03` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/open/W135_OWNER_DECISIONS.md §② | من يملك Entity Routing Registry وكتالوج أنواع الطلب؟ @ 808a2c03 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-OA-04` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/open/W135_OWNER_DECISIONS.md §③ · RPR-02 هدف 16 | اعتماد الأسماء المعروضة PENDING_OWNER (63 اسما + 2 مصيرة) @ 808a2c03 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-OA-05` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | orders/CLOSURE_SYSTEM.txt §الوثيقة الرابعة | قرار مقام التايم شيت (يعرض بالتسعة المنصوصة في الأمر §4) @ 808a2c03 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-OA-06` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | orders/CLOSURE_SYSTEM.txt §الوثيقة الرابعة | قيم الاعتماد (حدود السلم) عند نافذة الظل @ 808a2c03 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-GAP-03` | `OPEN` | P2 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ 808a2c03 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-GAP-12` | `OPEN` | P2 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ 808a2c03 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-GAP-14` | `OPEN` | P2 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ 808a2c03 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-GAP-22` | `OPEN` | P2 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ 808a2c03 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-GAP-26` | `OPEN` | P2 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ 808a2c03 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-GAP-32` | `OPEN` | P2 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ 808a2c03 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-FR-SEC-005` | `BLOCKED_OWNER` | P2 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ 808a2c03 |  |
| `CL-FR-FIN-008` | `IMPLEMENTED_NOT_VERIFIED` | P2 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-NAV-003` | `IMPLEMENTED_NOT_VERIFIED` | P2 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-DAT-005` | `IMPLEMENTED_NOT_VERIFIED` | P2 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |
| `CL-FR-GOV-011` | `OPEN` | P2 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=OPEN @ 808a2c03 |  |
| `CL-FR-GOV-013` | `OPEN` | P2 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=OPEN @ 808a2c03 |  |
| `CL-R3-M05` | `IMPLEMENTED_NOT_VERIFIED` | P2 | — | docs/REPAIR01_20260823/RPR03_SCORECARD.md | نافذة ظل الاعتماد مقيسة (تقييمات 0 — تحتاج وقائع) @ 808a2c03 |  |
| `CL-R3-M06` | `IMPLEMENTED_NOT_VERIFIED` | P2 | — | docs/REPAIR01_20260823/RPR03_SCORECARD.md | رحلات اعتماد تحجب فعلا 14/14 — وضع monitor @ 808a2c03 |  |
| `CL-R3-M09` | `BLOCKED_UAT` | P2 | UAT | docs/REPAIR01_20260823/RPR03_SCORECARD.md | رحلات بشرية كاملة بمسارها السالب 6/6 @ 808a2c03 |  |
| `CL-R3-M16` | `BLOCKED_UAT` | P2 | UAT | docs/REPAIR01_20260823/RPR03_SCORECARD.md | مراجعة يدوية عميقة للذهبيات العشر @ 808a2c03 |  |
| `CL-LG-OFFLINE` | `OPEN` | P2 | — | orders/CLOSURE_SYSTEM.txt §الوثيقة الثالثة | بلا مصير بعد @ 808a2c03 | مراجعة الكون وتسجيل المصير — ولا شيء يختفي بالصمت |
| `CL-LG-INTEGRATION` | `OPEN` | P2 | — | orders/CLOSURE_SYSTEM.txt §الوثيقة الثالثة | بلا مصير بعد @ 808a2c03 | مراجعة الكون وتسجيل المصير — ولا شيء يختفي بالصمت |
| `CL-LG-MULTIENTITY` | `OPEN` | P2 | — | orders/CLOSURE_SYSTEM.txt §الوثيقة الثالثة | بلا مصير بعد @ 808a2c03 | مراجعة الكون وتسجيل المصير — ولا شيء يختفي بالصمت |
| `CL-LG-REVENUE` | `OPEN` | P2 | — | orders/CLOSURE_SYSTEM.txt §الوثيقة الثالثة | بلا مصير بعد @ 808a2c03 | مراجعة الكون وتسجيل المصير — ولا شيء يختفي بالصمت |
| `CL-LG-OPARCH` | `OPEN` | P2 | — | orders/CLOSURE_SYSTEM.txt §الوثيقة الثالثة | بلا مصير بعد @ 808a2c03 | مراجعة الكون وتسجيل المصير — ولا شيء يختفي بالصمت |
| `CL-GAP-21` | `OPEN` | P3 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ 808a2c03 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-FR-GOV-004` | `IMPLEMENTED_NOT_VERIFIED` | P3 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ 808a2c03 |  |

## المغلقُ بالدليل

| Closure_ID | الدليل |
|---|---|
| `CL-WORK-02` | tests/injfix01_sensitive_fields_nine_channels_proof.php 15/15 والسالب يثبت العض @ 59136c85 |
| `CL-WORK-03` | schtasks EMS_cron_events LastTaskResult=0 + storage/logs/cron_events.log نبض START/END + سالب الازدواج SKIP + مؤشرات عند الراس 17985 @ 59136c85 |
| `CL-GAP-10` | tests/injfix01_sensitive_fields_nine_channels_proof.php 15/15 + سالب --negative=GAP-10 اثبت الرسوب @ 59136c85 |
| `CL-FR-EVT-003` |  |
| `CL-FR-DAT-002` |  |
| `CL-WORK-04` | REQUIRED_SETTLEMENT_EFFECT_MISSING=0 مقيسا + settlement_proof 16/16 + eng01_bus_test 22/22 + rpr03_contract_register صفر مفردة بلا عقد @ 59136c85 |
| `CL-WORK-05` | سقاطتان شدتا (permission 72⇒20 · extraction 77⇒36 بتمديد الاستخراج للعديات) ودينان جديدان سدا لا شدا (قارئ وهمي + ملكية cron_task) — الحزام 25⇒30/34 والمقامات ثابتة @ 59136c85 |
| `CL-GAP-01` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-02` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-04` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-05` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-06` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-08` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-09` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-13` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-15` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-16` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-19` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-25` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-27` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-28` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-30` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-33` | tools/injfix01_gap_coverage.php @ 808a2c03 |
| `CL-GAP-11` | permission_single_point_gate 7/7 بعد --retighten (72⇒20 قارئا) @ 59136c85 |
| `CL-GAP-20` | ownership_ruling 3/3 — حكم NOT_APPLICABLE لمدخل cron_events_task في gov_ownership_rulings @ 59136c85 |
| `CL-FR-SEC-001` |  |
| `CL-FR-FIN-001` |  |
| `CL-FR-EVT-001` |  |
| `CL-FR-APP-003` |  |
| `CL-FR-SEC-002` |  |
| `CL-FR-SEC-004` |  |
| `CL-FR-SEC-009` |  |
| `CL-FR-FIN-002` |  |
| `CL-FR-FIN-003` |  |
| `CL-FR-EVT-002` |  |
| `CL-FR-EVT-006` |  |
| `CL-FR-JRN-003` |  |
| `CL-FR-NAV-005` |  |
| `CL-FR-DAT-003` |  |
| `CL-FR-DAT-006` |  |
| `CL-FR-GOV-002` |  |
| `CL-FR-EVT-008` |  |
| `CL-R2-T01` | tools/rpr02_acceptance_scorecard.php @ 808a2c03 |
| `CL-R2-T02` | tools/rpr02_acceptance_scorecard.php @ 808a2c03 |
| `CL-R2-T03` | tools/rpr02_acceptance_scorecard.php @ 808a2c03 |
| `CL-R2-T04` | tools/rpr02_acceptance_scorecard.php @ 808a2c03 |
| `CL-R2-T05` | tools/rpr02_acceptance_scorecard.php @ 808a2c03 |
| `CL-R2-T06` | tools/rpr02_acceptance_scorecard.php @ 808a2c03 |
| `CL-R2-T07` | tools/rpr02_acceptance_scorecard.php @ 808a2c03 |
| `CL-R2-T08` | tools/rpr02_acceptance_scorecard.php @ 808a2c03 |
| `CL-R2-T09` | tools/rpr02_acceptance_scorecard.php @ 808a2c03 |
| `CL-R2-T10` | tools/rpr02_acceptance_scorecard.php @ 808a2c03 |
| `CL-R2-T11` | tools/rpr02_acceptance_scorecard.php @ 808a2c03 |
| `CL-R2-T12` | tools/rpr02_acceptance_scorecard.php @ 808a2c03 |
| `CL-R2-T14` | tools/rpr02_acceptance_scorecard.php @ 808a2c03 |
| `CL-R2-T15` | tools/rpr02_acceptance_scorecard.php @ 808a2c03 |
| `CL-GAP-31` | path_rulings 6/6 GAPV GAP-31 PASS @ 59136c85 |
| `CL-FR-EVT-005` |  |
| `CL-FR-EVT-007` |  |
| `CL-FR-GOV-001` |  |
| `CL-FR-GOV-003` |  |
| `CL-FR-GOV-005` |  |
| `CL-FR-GOV-006` |  |
| `CL-R3-M01` | tools/rpr03_scorecard.php @ 808a2c03 |
| `CL-R3-M02` | tools/rpr03_scorecard.php @ 808a2c03 |
| `CL-R3-M03` | tools/rpr03_scorecard.php @ 808a2c03 |
| `CL-R3-M04` | tools/rpr03_scorecard.php @ 808a2c03 |
| `CL-R3-M07` | tools/rpr03_scorecard.php @ 808a2c03 |
| `CL-R3-M10` | tools/rpr03_scorecard.php @ 808a2c03 |
| `CL-R3-M12` | tools/rpr03_scorecard.php @ 808a2c03 |
| `CL-R3-M13` | tools/rpr03_scorecard.php @ 808a2c03 |
| `CL-R3-M14` | tools/rpr03_scorecard.php @ 808a2c03 |
| `CL-R3-M15` | tools/rpr03_scorecard.php @ 808a2c03 |
| `CL-R3-M17` | tools/rpr03_scorecard.php @ 808a2c03 |
| `CL-R3-M18` | tools/rpr03_scorecard.php @ 808a2c03 |
| `CL-R3-M19` | tools/rpr03_scorecard.php @ 808a2c03 |
| `CL-GAP-24` | path_rulings 6/6 — قارئ الوهمي الجديد (exec_indicator_engine) حول الى السلطة fin_financial_periods @ 59136c85 |
| `CL-FR-DAT-004` |  |
