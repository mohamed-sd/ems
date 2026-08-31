# LEGACY REQUIREMENT DISPOSITION REGISTER — سجلُّ مصيرِ الالتزاماتِ القديمة

> القبول: `LEGACY_REQUIREMENT_WITHOUT_DISPOSITION = 0` — ولا شيءَ يختفي بالصمت.
> **اللقطة:** `ad1bf56f`

| ID | الكون | المصير | الخطّافاتُ المعماريّةُ الآن | مرجعُ القرار |
|---|---|---|---|---|
| `LG-OFFLINE` | Offline/Field: PWA · Service Worker · IndexedDB · Delta Sync · Conflict handling · Offline write ide | NEXT_RELEASE | محفوظة الان: idempotency_key في الناقل والمعاملات · sync_uuid رابط رخو (timesheet-mirror) · ولا يبنى ما يمنع version numbers/delta sync لاحقا | OA-07: تاكيد المالك على الاصدار الهدف |
| `LG-INTEGRATION` | Integration: Outbox · Retry · DLQ · Compensation · Idempotency · External API contracts | CURRENT_RELEASE | قائمة فعلا — لا خطاف ناقص | tests/eng01_bus_test.php @ c60c09b7 |
| `LG-MULTIENTITY` | Multi-Entity: دفاتر الكيان · حساباته البنكية · intercompany tagging · consolidation readiness | CURRENT_RELEASE | consolidation readiness: تقارير التجميع عبر الكيانات NEXT_RELEASE — والبنية لا تمنعها (العملة الاساس base=amount×rate قائمة) | ADR-02 · fx-currency-foundation @ c60c09b7 |
| `LG-REVENUE` | Customer Revenue: ENT-03 · Billing · Collection · Debit/Credit Notes · AR aging · Customer statement | CURRENT_RELEASE | AR aging كشف تقادم مخصص — يقاس وينزل ضمن شاشات المالية القائمة | docs/REPAIR01_20260823/registers/_gap_coverage.json @ c60c09b7 |
| `LG-OPARCH` | Operational Architecture: multi-entity assignment/lending · shared platform capabilities · field/mob | NEXT_RELEASE | multi-entity assignment/lending: intercompany_dues/loans جاهزان اساسا — لا معاملة تمنع الاسناد البيني | OA-07: تاكيد المالك على الاصدار الهدف |

LEGACY_REQUIREMENT_WITHOUT_DISPOSITION = **0**
