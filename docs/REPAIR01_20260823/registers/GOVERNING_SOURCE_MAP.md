# GOVERNING_SOURCE_MAP — خريطةُ مصادرِ الحكم

> **التصنيف: `GOVERNANCE_REFERENCE — NON-RUNTIME`** — مرجعٌ حوكميٌّ يحدّد من أين
> تستمدّ الأهدافُ قراراتِها. **ليست** Runtime Authority ولا Transaction SoT،
> **ولا يقرؤها Runtime أثناء المعاملة** — فلا تخضع لمعيارِ Production Reader (المادة ٢).
> **Baseline_ID:** `BL-20260831d-aba54573` · بأمرِ الحوكمةِ الموحَّد §١١–§١٣.

## مفرداتُ Authority_Class (§١١)

`GOVERNING_DOCUMENT` · `OWNER_DECISION` · `TARGET_REGISTRY` · `RUNTIME_AUTHORITY` ·
`TRANSACTION_SOT` · `REFERENCE_ONLY` · `CURRENT_STATE_SNAPSHOT`

⛔ ولا تُستعمل عبارةُ «Source of Truth» بعد اليومِ إلا مقرونةً بصنفِها.

## الخريطة

| Universe_ID | Workspace/Domain | Authority_Class للمصدر | Primary_Governing_Source | Secondary_Source | Owner_Decision_Source | Target_Registry | Runtime_Authority | Evidence_Source | Precedence | Effective_From | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| NAV-DEP | ملاحة DEP-01..17 | GOVERNING_DOCUMENT | `01 · الدليل المعماري.xlsx` (ورقةُ كلِّ إدارة) | `nav_canonical` (APPROVED — تجاوزُ تسميةٍ بقرار) | `OWNER_ACTION_REGISTER` (OA-04 الأسماء) | `nav_targets` + `nav_placements` | `nav_placements` ⇐ `uxuiDeclaredSections` | `SIDEBAR_GUIDE_COMPARE` + `NAVR_METRICS` | الدليل ← التسمية المعتمدة ← حكم مسجَّل | 2026-08-31 | ACTIVE |
| NAV-IAF | ملاحة المراجعة الداخلية | GOVERNING_DOCUMENT | ورقة IAF في الدليل | — | — | `nav_placements` | `nav_placements` | COMPARE/METRICS | كما فوق | 2026-08-31 | ACTIVE |
| NAV-EX | ملاحة EX-CEO · EX-DVP | GOVERNING_DOCUMENT | **`02 · القيادة.xlsx`** (ملفُ القيادةِ التنفيذيّة) | أحكام EX في المخزن | Owner Register | `nav_targets` + `nav_placements` (**مستورَدة**: 38 هدفًا NT-EX-*) | `nav_placements` لـEX-CEO (مطابقٌ فيما بُني) · EX-DVP بانتظارِ دورٍ حيٍّ (Finding) | COMPARE (يقرأ ملفَّ القيادة §١٣) | القيادة ← أحكامُ EX | 2026-08-31 | ACTIVE |
| NAV-MY | مساحتي WS-MY | GOVERNING_DOCUMENT | مواصفةُ مساحةِ عملي (ورقة WS-MY) | الدستور §6 (الرئيسية/المراسلات أولًا) | قرار 2026-08-17 | `nav_placements` | حقنُ المراسي + placements | COMPARE | الدستور ← الورقة | 2026-08-31 | ACTIVE |
| NAV-PLATFORM | أدوات المنصة | OWNER_DECISION | قرارات Platform Capabilities (T13 §٥·٤) | سجل المنصة | مراجعةُ الـ12 سطحًا (CL-R2-T13) | `nav_workspaces` (WS-PLATFORM) | خارجَ الدورةِ بإعلانِه | METRICS | القرار ← السجل | 2026-08-31 | ACTIVE |
| SCREENS | سجل الشاشات | TARGET_REGISTRY | `repair01_requirements` (الأهداف) + `repair01_target_universe` (المصالحة) | الدليل المعماري | Owner Register | `repair01_screen_registry` | حرّاسُ الشاشةِ + `modules` | لوحتا RPR + الحزام | المتطلب ← المصالحة ← السجل | قائم | ACTIVE |
| FIELDS | الحقول | TARGET_REGISTRY | `repair01_fields` + ورقات الدليل | `gov_field_class` | Owner (الحساسية) | `field_registry` (extract) | `SensitiveFieldGuard`/`FieldGovernor` | شاهد القنوات التسع | الورقة ← السجل ← الحارس | قائم | ACTIVE |
| STATE | آلات الحالة | GOVERNING_DOCUMENT | أوامر W03/W04 STATE_MACHINES | `sm_model_ref` | Owner | `repair01_screen_registry.state_model_ref` | محرّكات الحالة | RPR-02 هدف 4 | الأمر ← المرجع | قائم | ACTIVE |
| APPROVAL | الاعتماد | GOVERNING_DOCUMENT | أوامر السلالم + `gov_ladders` | نافذة الظل | **OA-06 قيم الاعتماد** | `gov_ladders` | `EMS_UNIT_LADDER` (monitor مُعلَن) | شاهد app001 + M5/M6 | الأمر ← السلم ← القيم | قائم | ACTIVE |
| PERMISSIONS | الصلاحيات | RUNTIME_AUTHORITY | القوالب `gov_role_profiles/items` | `role_permissions` | Owner (الوصول التجاري) | القوالب | `get_module_permissions` (قالبٌ نافذٌ يحكم حصرًا — مُعلَن) | فصل الواجبات 92/92 | القالب ← الأدوار | قائم | ACTIVE |
| WORKFLOW | سيرُ العملِ والدورات | GOVERNING_DOCUMENT | أوامرُ الموجاتِ (دورةُ كلِّ إدارةٍ) + ورقاتُ الدليل | `gov_screen_cycle` (جسرُ الدورة) | Owner | `gov_screen_cycle` | محرّكاتُ المراحلِ وWFM | لوحتا RPR + شاهدُ الجسر | الأمرُ ← الجسر | قائم | ACTIVE |
| EVT-SUBS | سجلُّ اشتراكاتِ الأحداث | **REFERENCE_ONLY** (حكمُ تخفيضٍ §٥: 125 اشتراكًا مُعلَنًا بلا معالجٍ مسجَّلٍ — سلطةُ Runtime بلا قارئٍ تُخفَّض أو تُوصَل، والتفعيلُ «قلبٌ مُدارٌ» مؤجَّلٌ بنصِّ `cron_events.php`؛ فالسجلُّ **نيّةٌ مُعلَنةٌ** يُنذَر بفرقِها كلَّ تشغيلةٍ لا سلطةً تُقرأ) | إعلاناتُ الجولات | `EffectLinkConsumer` عقودُ الواقع | Owner (قرارُ التفعيل) | عقودُ `event_consumers` | `EventDispatcher.register()` الصريح | نبضُ `cron_events.log` (bus-unwired) | العقدُ الحيُّ ← الإعلان | 2026-08-31 | ACTIVE-DOWNGRADED |
| EVENTS | أحداث الأعمال | TRANSACTION_SOT | ADR-15 (`ems_business_events` دفتر الحقائق) | `rpr03_event_classification` | أحكام الأنواع (58/58) | عقود `EffectLinkConsumer` | الناشر/الموزّع/العامل | حزام الناقل 22/22 | الجذر ← العقد ← الأثر | قائم | ACTIVE |
| OWNERSHIP | الملكية | TARGET_REGISTRY | أحكام `gov_ownership_rulings` (سجل القرارات الدائم) | الدليل (owner لكل ورقة) | Owner | `repair01_screen_registry.owner_code` | — (حوكمة) | شاهد ownership 3/3 | الحكم ← السجل | قائم | ACTIVE |
| REPORTS | التقارير | GOVERNING_DOCUMENT | ورقات الدليل (مجموعات التقارير) | `PROJECTION` في السجل | Owner | placements (`PROJECTION`/MENU) | كالملاحة | METRICS | كالملاحة | 2026-08-31 | ACTIVE |
| LEGACY-NAV | `gov_target_nav` القديم | **CURRENT_STATE_SNAPSHOT** (أُسقطت صلاحيّتُه Target) | — | — | — | — | قارئُ إرثٍ لغيرِ المغطَّى (مُعلَنًا · يُقاس `LEGACY_TARGET_RUNTIME_READ_COUNT`) | `LEGACY_NAV_RECONCILIATION` | لا يعتمد نفسَه | 2026-08-31 | RETIRING |

## قاعدةُ §١٣ — لا `NO_SPEC` قبل استنفادِ مصادرِ النطاق

| النطاق | مصدرُ الحكمِ الذي يُستنفد أولًا |
|---|---|
| DEP-01..17 | الدليل المعماري الإداري |
| EX-CEO · EX-DVP | **ملف القيادة التنفيذية (`02 · القيادة.xlsx`)** |
| WS-MY | مواصفة مساحة عملي |
| IAF | معمارية المراجعة الداخلية |
| PLATFORM_SHARED | قرارات Platform Capabilities |

⇒ نُفِّذ: `NO_SPEC` أُزيلت — المواضعُ استُوردت من ملفِّ القيادةِ (`navr_import_exec` · CL-NAVR-EX مغلقٌ بالدليل)، وأداةُ القياسِ تقرأ الملفَّ نفسَه.
