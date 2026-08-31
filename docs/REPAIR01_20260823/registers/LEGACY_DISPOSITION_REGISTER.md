# LEGACY REQUIREMENT DISPOSITION REGISTER — سجلُّ مصيرِ الالتزاماتِ القديمة

> القبول: `LEGACY_REQUIREMENT_WITHOUT_DISPOSITION = 0` — ولا شيءَ يختفي بالصمت.
> **اللقطة:** `1c3ed647`

| ID | الكون | المصير | الخطّافاتُ المعماريّةُ الآن | مرجعُ القرار |
|---|---|---|---|---|
| `LG-OFFLINE` | Offline/Field: PWA · Service Worker · IndexedDB · Delta Sync · Conflict handling · Offline write ide | ⏳ بلا مصير | version numbers · idempotency keys · delta-sync-friendly transactions · conflict resolution slots | — |
| `LG-INTEGRATION` | Integration: Outbox · Retry · DLQ · Compensation · Idempotency · External API contracts | ⏳ بلا مصير | ems_business_events جذرا محايدا · مروحة أثر ذرية · مؤشرات مستهلكين | — |
| `LG-MULTIENTITY` | Multi-Entity: دفاتر الكيان · حساباته البنكية · intercompany tagging · consolidation readiness | ⏳ بلا مصير | entity_id في الجداول المالية · فصل الرؤية عن الملكية | — |
| `LG-REVENUE` | Customer Revenue: ENT-03 · Billing · Collection · Debit/Credit Notes · AR aging · Customer statement | ⏳ بلا مصير | دورة المطالبات والإيراد القائمة (المروحة تعترف والمستخلص يفوتر) | — |
| `LG-OPARCH` | Operational Architecture: multi-entity assignment/lending · shared platform capabilities · field/mob | ⏳ بلا مصير | سجل المنصة المبرر · بوابة المستأجر ADR-02 | — |

LEGACY_REQUIREMENT_WITHOUT_DISPOSITION = **5**
