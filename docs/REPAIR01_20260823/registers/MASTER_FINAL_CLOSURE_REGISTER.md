# MASTER FINAL CLOSURE REGISTER — السجلُّ الجامعُ للإغلاقِ النهائيّ

> **المخزنُ الحاكم:** `registers/MASTER_FINAL_CLOSURE_REGISTER.json` — وهذا إسقاطُه.
> **اللقطة:** `efb30839` · **حُدِّث:** 2026-09-01 14:18
> **المصمَّمُ الحاكم:** `docs/REPAIR01_20260823/orders/CLOSURE_SYSTEM.txt`

## مقامُ الحالات — ⛔ ولا تُجمَع في نسبةٍ واحدة

| الحالة | العدد |
|---|---|
| `BLOCKED_ENVIRONMENT` | 2 |
| `BLOCKED_GOVERNING_SOURCE` | 3 |
| `BLOCKED_OWNER` | 40 |
| `BLOCKED_UAT` | 5 |
| `EVIDENCE_CLOSED` | 120 |
| `IMPLEMENTED_NOT_VERIFIED` | 9 |
| `IN_PROGRESS` | 2 |
| `OPEN` | 16 |
| **الجملة** | **197** |

## البنودُ غيرُ المغلقة — بحاجزِها وفعلِها التالي

| Closure_ID | الحالة | الأولوية | الحاجز | المصدر | اللقطةُ الحالية | الفعلُ التالي |
|---|---|---|---|---|---|---|
| `CL-WORK-01` | `BLOCKED_ENVIRONMENT` | P0 | — | docs/REPAIR01_20260823/orders/CLOSURE_SYSTEM.txt | حماية مصدر العمل — REMOTE_HEAD=LOCAL_HEAD والوسوم والأسس @ efb30839 |  |
| `CL-FR-APP-001` | `IMPLEMENTED_NOT_VERIFIED` | P0 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-APP-002` | `BLOCKED_OWNER` | P0 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ efb30839 |  |
| `CL-FR-APP-004` | `BLOCKED_OWNER` | P0 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ efb30839 |  |
| `CL-FR-DAT-001` | `BLOCKED_OWNER` | P0 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ efb30839 |  |
| `CL-NAVR-HUMAN` | `BLOCKED_UAT` | P1 | UAT | أمر المالك: إصلاح نموذج الملاحة من الجذور (2026-08-31) | المؤهل للتحقق البشري: 17 ادارة + EX-CEO (مطابق فيما بني من ملف القيادة) — HUMAN_NAV_PASS=0 | جولة تحقق بشري بدور حقيقي لكل ادارة — 16 قابلة فورا وDEP-08 محجوبة برب |
| `CL-GOV3-WH03` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/REPAIR01_20260823/orders/GOV_EXEC.txt | IMPLEMENTED_NOT_VERIFIED @ 15ba9f3c | تشغيل جولة استخدام حية ثم رفع الحالة VERIFIED — والتحقق البشري للمساحة |
| `CL-GOV3-BUILD` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/REPAIR01_20260823/orders/GOV_EXEC.txt | CURRENT_RELEASE_TARGET_NOT_BUILT صفر لكل مساحة بدور حي — والاثبات البشري وحده يفصل بين IMP | استخراج Sprint من السجل واخذ اعلى الاهداف قيمة — 134 موضعا حاليا (لوحة |
| `CL-GOV3-FIELDS` | `IN_PROGRESS` | P1 | — | docs/REPAIR01_20260823/orders/GOV_EXEC.txt | والمقام صعد بدخول 90 سطحا جديدا الجسر — فخ المقام §20 معلن، ومفتاح الدفتر صحح الى (screen_ | ابدا بالادارات البانية سقف البنية (ترتيب السجل) — المقيس الحالي في SCR |
| `CL-GOVUI-02` | `IN_PROGRESS` | P1 | — | docs/REPAIR01_20260823/orders/GOV_UI_EXEC.txt | جبهة الحقول — FIELD_CONFORMANCE على الدفتر المسوى @ efb30839 |  |
| `CL-GAP-23` | `BLOCKED_UAT` | P1 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ efb30839 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-GAP-29` | `OPEN` | P1 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ efb30839 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-FR-APP-005` | `BLOCKED_OWNER` | P1 | BLOCKED_OWNER | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-APP-007` | `BLOCKED_OWNER` | P1 | BLOCKED_OWNER | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-APP-008` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ efb30839 |  |
| `CL-FR-SEC-003` | `BLOCKED_OWNER` | P1 | BLOCKED_OWNER | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-SEC-008` | `BLOCKED_OWNER` | P1 | BLOCKED_OWNER | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-FIN-005` | `BLOCKED_ENVIRONMENT` | P1 | BLOCKED_ENVIRONMENT | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-JRN-001` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ efb30839 |  |
| `CL-FR-APP-006` | `BLOCKED_GOVERNING_SOURCE` | P1 | GOVERNING_SOURCE | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_GOVERNING_SOURCE @ efb30839 |  |
| `CL-FR-APP-009` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ efb30839 |  |
| `CL-FR-APP-010` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ efb30839 |  |
| `CL-FR-APP-011` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ efb30839 |  |
| `CL-FR-SEC-006` | `BLOCKED_GOVERNING_SOURCE` | P1 | BLOCKED_GOVERNING_SOURCE | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-SEC-007` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-FIN-004` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ efb30839 |  |
| `CL-FR-FIN-006` | `BLOCKED_OWNER` | P1 | BLOCKED_OWNER | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-FIN-007` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-EVT-004` | `BLOCKED_OWNER` | P1 | BLOCKED_OWNER | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-JRN-002` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ efb30839 |  |
| `CL-FR-JRN-004` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ efb30839 |  |
| `CL-FR-JRN-005` | `BLOCKED_OWNER` | P1 | BLOCKED_OWNER | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-JRN-006` | `IMPLEMENTED_NOT_VERIFIED` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-NAV-002` | `BLOCKED_OWNER` | P1 | BLOCKED_OWNER | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-NAV-004` | `BLOCKED_OWNER` | P1 | BLOCKED_OWNER | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-NAV-006` | `BLOCKED_UAT` | P1 | BLOCKED_UAT | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-APP-012` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ efb30839 |  |
| `CL-FR-GOV-008` | `BLOCKED_OWNER` | P1 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=OPEN @ efb30839 |  |
| `CL-R2-T13` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/RPR02_TARGET_UNIVERSE.md | شاشة PLATFORM بلا تبرير منصي معتمد = 0 (المقيس 12) @ efb30839 |  |
| `CL-R2-T16` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/RPR02_TARGET_UNIVERSE.md | اسم معروض غير معتمد = 0 (المقيس 3 منها 2 PENDING_OWNER) @ efb30839 |  |
| `CL-R3-M08` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/RPR03_SCORECARD.md | أسطح PLATFORM بلا تبرير = 0 (المقيس 12) @ efb30839 |  |
| `CL-R3-M11` | `BLOCKED_OWNER` | P1 | — | docs/REPAIR01_20260823/RPR03_SCORECARD.md | قيود يدوية بلا مصدر = 0 (المقيس 1644) @ efb30839 |  |
| `CL-OA-01` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/open/DEC-OPEN-15.md | قوائم تتبع الأصناف الثلاث: Lot · Serial · Expiry @ 808a2c03 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-OA-02` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/open/DEC-OPEN-16.md | من يملك التحقيق؟ (الحوكمة أم المراجعة الداخلية) @ 808a2c03 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-OA-03` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/open/W135_OWNER_DECISIONS.md §② | من يملك Entity Routing Registry وكتالوج أنواع الطلب؟ @ 808a2c03 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-OA-04` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/open/W135_OWNER_DECISIONS.md §③ · RPR-02 هدف 16 | اعتماد الأسماء المعروضة PENDING_OWNER (63 اسما + 2 مصيرة) @ 808a2c03 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-OA-05` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | orders/CLOSURE_SYSTEM.txt §الوثيقة الرابعة | قرار مقام التايم شيت (يعرض بالتسعة المنصوصة في الأمر §4) @ 808a2c03 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-OA-06` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | orders/CLOSURE_SYSTEM.txt §الوثيقة الرابعة | قيم الاعتماد (حدود السلم) عند نافذة الظل @ 808a2c03 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-OA-07` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | registers/OWNER_ACTION_REGISTER.md | اعتماد الاصدار الهدف للكونين المؤجلين @ c60c09b7 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-GAP-63` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/baseline_20260821/FINDINGS.md F-H19 | نافذة ظل الاعتماد صفر تقييم — مرجعها CL-FR-APP-002/004 (BLOCKED_OWNER) وOA-06 قيم الاعتماد | تنتظر قيم الاعتماد (OA-06) ووقائع حية — والمحرك مبني (app001 11/11) |
| `CL-OA-09` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/GOV_UI_EXEC_CLOSURE.md §④ | انشاء دور نواب الرئيس وربطه بمساحة EX-DVP @ efb30839 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-OA-10` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/GOV_UI_EXEC_CLOSURE.md §④ | انشاء دور الحوكمة والالتزام وربطه بمساحة DEP-08 @ efb30839 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-OA-11` | `BLOCKED_OWNER` | P1 | OWNER_DECISION | docs/REPAIR01_20260823/govui_outputs/10_OPEN_GOVERNING_CONFLICTS.md §② | اينزع السجل التابع ذو المسار المستقل من السايدبار؟ (§8) @ efb30839 | ينتظر بوابته — ولا يحجب إلا نطاقه |
| `CL-NAVR-LEG626` | `OPEN` | P2 | — | أمر المالك: إصلاح نموذج الملاحة من الجذور (2026-08-31) | 626 بند قائمة ارثيا خارج كل ورقة حاكمة (TAXONOMY_LEGACY في NAVR_LINEAGE_RECON) — كل منها F | فض الاقتراحات دفعات باولوية المساحات — والقرار الحاكم اضافة/تقاعد لا ا |
| `CL-PAT-DUPLABEL` | `OPEN` | P2 | — | docs/REPAIR01_20260823/orders/GOV_EXEC.txt | OPEN @ 15ba9f3c | فرز الازواج الستة: (اغلاق البلاغ admin_close/ticket_close · المعدات fl |
| `CL-GOV3-PERMPATH` | `OPEN` | P2 | — | docs/REPAIR01_20260823/orders/GOV_EXEC.txt | OPEN @ 909cf6a3 | تصميم خطة القلب: القالب يصير المصدر الوحيد والقائم يشتق منه — ثم ظل ثم |
| `CL-GOVUI-03` | `OPEN` | P2 | — | docs/REPAIR01_20260823/orders/GOV_UI_EXEC.txt | ارثي يصير بندا بلا قيد مصالحة فردي — حكم CL-NAVR-LEG626 صنفي @ efb30839 |  |
| `CL-GOVUI-04` | `OPEN` | P2 | — | docs/REPAIR01_20260823/orders/GOV_UI_EXEC.txt | ثلاثة اسطح تجمع حبتين — GRAIN_MISMATCH @ efb30839 |  |
| `CL-GOVUI-05` | `OPEN` | P2 | — | docs/REPAIR01_20260823/orders/GOV_UI_EXEC.txt | خطة القوى العاملة سطح تخطيط لم يبن — والموصول تقرير @ efb30839 |  |
| `CL-GAP-03` | `OPEN` | P2 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ efb30839 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-GAP-12` | `OPEN` | P2 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ efb30839 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-GAP-14` | `OPEN` | P2 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ efb30839 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-GAP-22` | `OPEN` | P2 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ efb30839 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-GAP-26` | `OPEN` | P2 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ efb30839 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-GAP-32` | `OPEN` | P2 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ efb30839 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-FR-SEC-005` | `BLOCKED_OWNER` | P2 | OWNER_DECISION | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=BLOCKED_OWNER_DECISION @ efb30839 |  |
| `CL-FR-FIN-008` | `BLOCKED_GOVERNING_SOURCE` | P2 | BLOCKED_GOVERNING_SOURCE | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-NAV-003` | `BLOCKED_OWNER` | P2 | BLOCKED_OWNER | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-DAT-005` | `BLOCKED_OWNER` | P2 | BLOCKED_OWNER | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |
| `CL-FR-GOV-011` | `OPEN` | P2 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=OPEN @ efb30839 | شاهدُ حزامٍ يثبت أن قارئَ لقطاتِ التجميدِ يرتّب بمعرِّفِ اللقطةِ لا بـ |
| `CL-FR-GOV-013` | `OPEN` | P2 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=OPEN @ efb30839 | شاهدُ حزامٍ يثبت أن مقامَ تقريرِ الفرقِ يُشتقّ من الجداولِ الممسوسةِ ف |
| `CL-R3-M05` | `IMPLEMENTED_NOT_VERIFIED` | P2 | — | docs/REPAIR01_20260823/RPR03_SCORECARD.md | نافذة ظل الاعتماد مقيسة (تقييمات 0 — تحتاج وقائع) @ efb30839 |  |
| `CL-R3-M06` | `IMPLEMENTED_NOT_VERIFIED` | P2 | — | docs/REPAIR01_20260823/RPR03_SCORECARD.md | رحلات اعتماد تحجب فعلا 14/14 — وضع monitor @ efb30839 |  |
| `CL-R3-M09` | `BLOCKED_UAT` | P2 | UAT | docs/REPAIR01_20260823/RPR03_SCORECARD.md | رحلات بشرية كاملة بمسارها السالب 6/6 @ efb30839 |  |
| `CL-R3-M16` | `BLOCKED_UAT` | P2 | UAT | docs/REPAIR01_20260823/RPR03_SCORECARD.md | مراجعة يدوية عميقة للذهبيات العشر @ efb30839 |  |
| `CL-GAP-21` | `OPEN` | P3 | — | tools/injfix01_gap_coverage.php | شاهدُها أحمرُ @ efb30839 | إغلاقُ سببِ حمرةِ الشاهد — والقراءةُ من نصِّ رسوبِه لا رقمِه |
| `CL-FR-GOV-004` | `IMPLEMENTED_NOT_VERIFIED` | P3 | — | docs/sources/INJ-FRD-REM-01/workbook.xlsx | Closure_State=IMPLEMENTED_NOT_CLOSED @ efb30839 |  |

## المغلقُ بالدليل

| Closure_ID | الدليل |
|---|---|
| `CL-WORK-02` | tests/injfix01_sensitive_fields_nine_channels_proof.php 15/15 والسالب يثبت العض @ 59136c85 |
| `CL-NAVR-01` | SIDEBAR_GUIDE_COMPARE.md + WHY @ 7e366870/b0d2c3e9 @ b0d2c3e9 |
| `CL-NAVR-02` | NAVR_ROOT_AUDIT.md §③ + NAVR_METRICS @ b0d2c3e9 |
| `CL-NAVR-04` | هجرة 2028_02_02 + بوابة الهجرات 4/4 + DR-2026-0006 @ b0d2c3e9 |
| `CL-NAVR-05` | navr_import_guide + sidebar_guide_compare @ b0d2c3e9 |
| `CL-NAVR-06` | NAVR_METRICS.md @ b0d2c3e9 |
| `CL-NAVR-07` | navr_import_guide --apply + navr_wire_missing --apply @ b0d2c3e9 |
| `CL-GATE-00` | GATE00_BASELINE.md @ f592cdf9 |
| `CL-WORK-03` | schtasks EMS_cron_events LastTaskResult=0 + storage/logs/cron_events.log نبض START/END + سالب الازدواج SKIP + مؤشرات عند الراس 17985 @ 59136c85 |
| `CL-GOVUI-01` |  |
| `CL-GAP-07` | bus_stall_alarm_proof 11/11 — العدة حوكيت بتسجيل الانتاج كاملا (fx موصول) واليتم يثبت بمجس اصطناعي @ 747cfbe7 |
| `CL-GAP-10` | tests/injfix01_sensitive_fields_nine_channels_proof.php 15/15 + سالب --negative=GAP-10 اثبت الرسوب @ 59136c85 |
| `CL-FR-EVT-003` |  |
| `CL-FR-DAT-002` |  |
| `CL-FR-GOV-009` | tests/injfrd01_gov009_migration_ledger_gate.php 9/9 — بوابة 4/4 · غير مصالح=0 · السالب يرسب بالحقن · Commit=ec0aff80 @ ec0aff80 |
| `CL-FR-GOV-015` | nine_channels 15/15 @ 747cfbe7 — ACTIVE_SENSITIVE_FIELD_BYPASS=0 والسالب مثبت @ 012c4db7 |
| `CL-GAP-76` | CL-WORK-03 + tests/injfix01_scheduler_parity_proof.php 5/5 @ ad1bf56f |
| `CL-NAVR-03` | NAVR_ROOT_AUDIT.md §①§② @ b0d2c3e9 |
| `CL-NAVR-08` | nav_placements placement_type=NOT_BUILT @ b0d2c3e9 |
| `CL-NAVR-09` | nav_workspaces.ruling + NAVR_ROOT_AUDIT §⑤ @ b0d2c3e9 |
| `CL-NAVR-10` | sidebar_guide_compare (المصنف خارج المقام) + NAVR_METRICS @ b0d2c3e9 |
| `CL-NAVR-CONFORM` | STRUCTURAL_NAV_PASS=17/17 على المقام المنطبق — مجموعات 155/160 · ترتيب 158/160 · صفر مبني غير مصير للادارات · SIDEBAR_GUIDE_COMPARE+NAVR_METRICS @ 0d8f8acd |
| `CL-GOV-SRCMAP` | registers/GOVERNING_SOURCE_MAP.md @ f592cdf9 |
| `CL-NAVR-CARD` | tests/navr_ws_cardinality_proof.php 5/5 + هجرة 2028_02_03 @ f592cdf9 |
| `CL-NAVR-TID` | هجرة 2028_02_03 + navr_import_guide @ f592cdf9 |
| `CL-NAVR-RECON` | tools/navr_legacy_reconcile.php + gov_legacy_nav_recon @ f592cdf9 |
| `CL-NAVR-EX` | navr_import_exec: 38 موضعا من ملف القيادة (EX-CEO 4 مجموعات 26 هدفا · EX-DVP 4 مجموعات 12) — وEX-CEO ربط بدوره 9 وصار مطابقا فيما بني 4/4 و21/21 · EX-DVP بلا دور حي Finding @ df5adb85 |
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
| `CL-GAP-17` | scheduler_parity 5/5 — المدخل الغلاف يجدول عامله قياسا بحل التضمين درجة @ 1c3ed647 |
| `CL-GAP-18` | injexec01_migration_ledger_gate 3/3 — الانزياحان اعلنا بسببيهما الموثقين (تعليق اعفاء 747cfbe7 · تاليف لاحق 0bbed277) @ 1c3ed647 |
| `CL-GAP-20` | ownership_ruling 3/3 — حكم NOT_APPLICABLE لمدخل cron_events_task في gov_ownership_rulings @ 59136c85 |
| `CL-FR-SEC-001` |  |
| `CL-FR-FIN-001` |  |
| `CL-FR-EVT-001` |  |
| `CL-FR-NAV-001` | tests/injfrd01_nav001_source_clean.php = 8 نجاح · 0 رسوب — بعد كنس اللفظ المتقاعد وفعل المتكلم من link_groups (هجرة 2028_02_22 بعكسها): خمسة صفوف باللفظ المتقاعد و14 صفا بفعل المتكلم، والبدائل منقولة من gov_cycle_name_log وورقة الموردين لا مخترعة، وكل تغيير مقيد بقيمته السابقة (77 قيد تغيير) @ cce83748 |
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
| `CL-FR-GOV-007` | gov007 witness 5/5 · Commit=ba23a05a @ ba23a05a |
| `CL-FR-GOV-010` | gov010 witness 9/9 (سالب حي) · Commit=ba23a05a @ ba23a05a |
| `CL-FR-GOV-012` | gov012 witness 6/6 — مناقض=0 من 13 والسالب يعض · Commit=bf99b4bc @ bf99b4bc |
| `CL-FR-GOV-014` | gov014 witness 4/4 — بوابة 4/4 + DR-2026-0004 غير متقادم · Commit=bf99b4bc @ bf99b4bc |
| `CL-FR-GOV-016` | permission_gate 7/7 + extraction 2/2 @ 747cfbe7 — صفر سقاطة راسبة على تحسن @ 012c4db7 |
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
| `CL-GAP-56` | tools/injfix01_gap_coverage.php --negative + tools/injrev01_audit_align_reverse.php (DONE) @ ad1bf56f |
| `CL-GAP-77` | tests/injfix01_consumer_key_hygiene_proof.php 5/5 + هجرة 2028_02_01 @ ad1bf56f |
| `CL-NAVR-11` | NAVR_PATTERN_SCAN.md @ b0d2c3e9 |
| `CL-NAVR-LINEAGE` | NAVR_LINEAGE_RECON: المعادلة 1282=206+1076 وكل Without مصنف (89 اداة · 35 بوابة مالية · 326 استعارة منسوبة عند مالكها · 626 ارثي يقترح) — UNEXPLAINED=0 @ df5adb85 |
| `CL-NAVR-MY` | التراكب الشخصي: بابا ورقة WS-MY (مساحتي الشخصية · عملي اليومي) يليان المراسي لكل الادوار — WS-MY مطابق فيما بني 2/2 و6/6 · وصفاتي ربطت لـ23 دورا ماذونا بسجلاتها الاربعة @ f042a8f5 |
| `CL-PAT-EVTSUBS` | GOVERNING_SOURCE_MAP (EVT-SUBS) + NAVR_PATTERN_SCAN §2 @ aba54573 |
| `CL-PAT-USERROLE` | هجرة 2028_02_08 (ردم 34 + وسم اثري) + شاهد tests/gov_userrole_axis_proof.php 7/7 + قارئا القياس حولا للحاكم @ a60f6d97 |
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
| `CL-LG-OFFLINE` | سجل الارث: مصير مسجل بدعمه المقيس وخطافاته — LEGACY_REQUIREMENT_WITHOUT_DISPOSITION=0 @ c60c09b7 |
| `CL-LG-INTEGRATION` | سجل الارث: مصير مسجل بدعمه المقيس وخطافاته — LEGACY_REQUIREMENT_WITHOUT_DISPOSITION=0 @ c60c09b7 |
| `CL-LG-MULTIENTITY` | سجل الارث: مصير مسجل بدعمه المقيس وخطافاته — LEGACY_REQUIREMENT_WITHOUT_DISPOSITION=0 @ c60c09b7 |
| `CL-LG-REVENUE` | سجل الارث: مصير مسجل بدعمه المقيس وخطافاته — LEGACY_REQUIREMENT_WITHOUT_DISPOSITION=0 @ c60c09b7 |
| `CL-LG-OPARCH` | سجل الارث: مصير مسجل بدعمه المقيس وخطافاته — LEGACY_REQUIREMENT_WITHOUT_DISPOSITION=0 @ c60c09b7 |
| `CL-GAP-24` | path_rulings 6/6 — قارئ الوهمي الجديد (exec_indicator_engine) حول الى السلطة fin_financial_periods @ 59136c85 |
| `CL-FR-DAT-004` |  |
