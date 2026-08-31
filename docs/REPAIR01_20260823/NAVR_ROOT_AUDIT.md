# NAVR — تشريحُ نموذجِ الملاحةِ من جذورِه

> **الحملة**: إصلاحُ نموذجِ الملاحةِ والسايدبارِ من الجذور (أمرُ المالك 2026-08-31).
> **اللقطة الموحَّدة**: `BL-20260901-13010626` (حزمة GATE-00 الذرّيّة) — كلُّ رقمٍ هنا من قياسٍ حيٍّ، من التشغيلةِ
> نفسِها التي ولّدت `SIDEBAR_GUIDE_COMPARE.md` و`SIDEBAR_GUIDE_WHY.md` (NAVR-①).

---

## ① سلسلةُ الحقيقةِ الحاليّة — طبقةً طبقةً

`Guide → Target Registry → Workspace → Group → Placement → Permission → Runtime Renderer → User Session`

| الطبقة | المخزن الحالي | Source of Truth | PK | القارئ (Reader) | الكاتب (Writer) | Fallback | Override | تبعية الدور | السلوك الحي |
|---|---|---|---|---|---|---|---|---|---|
| **Guide** | `01 · الدليل المعماري.xlsx` (19 ورقة) | **هو الحاكم** (قرار المالك) | ورقة+صفّ | `rpr02a_read_cards` (قياس فقط) | المالك | — | تجاوزُ تسميةٍ بقرار (`nav_canonical` APPROVED) | لا | **لا قارئَ إنتاجٍ** — يُقرأ للقياس لا للتصيير ⛔ |
| **Target Registry** | `gov_target_nav` (725 صفًّا · 28 دورًا) | ⛔ **مقلوب**: 682/725 (94٪) بوثيقة `RENDER-ALIGN*` — **أُلِّف من التصيير القائم لا من الدليل** | id (route·role·group) | `uxuiDeclaredSections` | جولات RENDER-ALIGN | — | — | نعم (`role_id`) | جزءٌ منه **ميّتٌ تصييرًا** (الدور 26: 17 مجموعة معلنة · تُعرض 8 رؤوس تصنيف) |
| **Workspace** | **لا جدولَ له** | ضمنيٌّ: `repair01_departments` + جسرُ اسمِ دورٍ (`sidebar_guide_compare` ②) | — | جسرُ القياس فقط | — | — | 4 إسنادات يدوية (DEP-06⇒21 · DEP-17⇒25 · EX-CEO⇒9 · IAF⇒33) | نعم | **حقيقةٌ سياقيّةٌ بلا مخزن** ⛔ |
| **Group** | ثلاثةُ مخازنَ متنازعة: `nav_group_taxonomy` (12 رأسًا عامًّا) · `link_groups` (لكلِّ دورٍ `owner_role_id`) · `gov_target_nav.group_ar` | ⛔ متعدِّد | code / id / — | `printEmsTenGroupNav` يقرأ الأولَ والثالث؛ `getUnifiedNavItems` يضمُّ الثاني | جولاتٌ شتّى | التصنيفُ الـ12 | — | الأولُ **لا** · الثاني نعم · الثالث نعم | العامُّ يغلب |
| **Placement** | `nav_route_group` (**PK = `route` وحدَه** · 557 صفًّا) + `nav_items` (role·group_id·sort) | ⛔ **حقيقةٌ سياقيّةٌ (لكلِّ مساحةٍ) مخزونةٌ علاقةً عالميّةً 1:1** — والدليلُ يطلب 1:N (الشاشةُ المشتركةُ بموضعٍ لكلِّ مساحة) | route | خريطةُ رؤوسِ الطيّ | أسسٌ متفرّقة (`PIN` 58 · فارغ 55 · `LEVEL:*` · `DIR:*` · اجتهادات) — **صفٌّ واحدٌ فقط أصلُه ورقةُ الدليل** (`RPR-OPS-11`) | التصنيفُ الـ12 | `PIN` | **لا** ⛔ | 147/162 شاشةً مُصيَّرةً في غيرِ مجموعةِ دليلِها |
| **Permission** | `nav_items.permission_code` + `gov_profile_items` + `get_module_permissions` | سليمُ الفصلِ نسبيًّا | — | `getUnifiedNavItems` + الحارس | جولات المنح | — | قوالب `unifiedNavTemplateState` | نعم | يعمل |
| **Renderer** | `insidebar.php` ⇒ `renderUnifiedNavigationV2` | — | — | — | — | ⛔ **سقوطٌ صامت**: متى بُذرت `nav_group_taxonomy` سلك مسارَ الرؤوسِ الـ12 **ولو كان للدورِ إعلانُ مجموعاتٍ** — بلا تسجيلِ واقعة | `EMS_NAV_TEN` | نعم | الفشلُ يبدو قائمةً «منطقيّة» خاطئة |
| **Session** | `$_SESSION['user']['role']` | — | — | كلُّ ما فوق | — | المسارُ القديمُ لغيرِ المعمَّم | — | — | — |

## ② مواضعُ «حقيقةٌ سياقيّةٌ مخزونةٌ عالميًّا» (المطلوب ٣)

1. **`nav_route_group.route` PK** — رأسُ طيِّ الشاشةِ يعتمد المساحةَ، والمخزنُ يفرض رأسًا واحدًا للجميع. ⇒ **جذرُ 147 شاشةً في غيرِ مجموعتِها**.
2. **غيابُ طبقةِ Workspace** — إسنادُ إدارة⇒دور يعيش في جسرِ أداةِ قياسٍ لا في مخزنٍ محكوم.
3. **`nav_group_taxonomy` عالميّةٌ بلا سياق** — تُعرض لكلِّ الأدوارِ بدل مجموعاتِ دورةِ كلِّ إدارة.

## ③ تدقيقُ اتجاهِ المصدر — TARGET_SOURCE_DIRECTION_AUDIT (المطلوب ٢)

| المقياس | القيمة @ الحزمة الموحَّدة |
|---|---|
| صفوف `gov_target_nav` | **725** |
| منها بوثيقةِ `RENDER-ALIGN*` (Target مولَّدٌ من Current) | **682 (94٪)** |
| منها بوثيقةٍ أخرى (`INJ-SAL-ALIGN-01` · `INJ-SUP-ALIGN-01` · `REPAIR01-OPS-11` · `RPR-NAV-SEC-01`) | 43 |
| صفوفُ `nav_route_group` أساسُها ورقةُ الدليل | **1 من 557** |
| **`TARGET_DERIVED_FROM_CURRENT_WITHOUT_RULING`** (بلا حكمِ اعتمادٍ لاحق) | **682** ← الهدف 0 |

**الحكم**: طبقةُ الهدفِ الحاليّةُ (`gov_target_nav`) **ساقطةُ الصلاحيّةِ كـTarget** —
تصلح لقطةَ توثيقٍ للحاضرِ لا مرجعَ قياس. ⇒ الهدفُ الحاكمُ الوحيدُ: **ورقةُ الدليلِ
المعماريِّ لكلِّ إدارة** + تجاوزاتُ التسميةِ المعتمدةُ (`nav_canonical` APPROVED)
+ أحكامٌ مسجَّلةٌ لاحقة. ويُمنع توليدُ Target من Current بلا حكمٍ — قياسُه أداةُ
`tools/navr_metrics.php`.

## ④ النموذجُ المستهدَف (المطلوب ٤ · ٥ · ٦)

```
Screen Identity  (repair01_screen_registry.screen_id · والهدفُ غيرُ المبنيِّ بهويّةِ ورقتِه)
   ↓
nav_workspaces   (workspace_id · kind: DEPARTMENT/EXECUTIVE/PERSONAL/PLATFORM_UTILITY · حكمُ كلٍّ مسجَّل)
   ↓ nav_ws_roles (ربطُ الدورِ بمساحتِه — PRIMARY/SECONDARY · بسندِه)
nav_lifecycle_groups (مجموعاتُ دورةِ كلِّ مساحةٍ من ورقتِها · بترتيبِها · source_ref)
   ↓
nav_placements   (workspace_id · screen_id/target · group_id · sort_no · placement_type · source_ref · active)
   ↓
Permission layer (كما هي — مستقلّة: nav_items/gov_profile_items/get_module_permissions)
   ↓
Renderer: مساحةُ أعمالٍ لها Placements ⇒ تُصيَّر منها حصرًا · وما تعذّر ⇒
  Fail-visible في بيئةِ الاختبار + قيدُ Architectural Finding في gov_nav_findings
  ⛔ ولا سقوطَ صامتًا إلى التصنيفِ العامّ — BUSINESS_WORKSPACE_GLOBAL_FALLBACK = 0
```

- الشاشةُ الواحدةُ تظهر بموضعٍ مختلفٍ في أكثرَ من مساحةٍ (صفُّ placement لكلِّ مساحة) — بلا شاشةٍ مكرَّرة.
- دورةُ العملِ خاصّةٌ بالمساحةِ لا Taxonomy عامّة — والأدواتُ خارجَ الدورةِ (مساحتي · المراسلات · المرجعيات) تُصيَّر **بإعلانٍ معماريٍّ مسجَّلٍ** بعد مجموعاتِ الدورة، لا سقوطًا.
- غيرُ المبنيِّ `placement_type=NOT_BUILT` بموضعِه المستهدَفِ المسجَّل — **لا يُصيَّر ولا يُعَدُّ عطبَ ملاحة** (المطلوب ٨).

## ⑤ أحكامُ الحالاتِ الخاصّة (المطلوب ٩)

| الكيان | الحكم | السند |
|---|---|---|
| `DEP-01..17` | `DEPARTMENT` Workspace | ورقةُ دليلٍ لكلٍّ |
| `DEP-08` الحوكمة | `DEPARTMENT` **بلا دورٍ حيٍّ مربوط** — المساحةُ والمواضعُ تُسجَّل الآن، والربطُ يقع متى أُنشئ الدورُ (فجوةُ دورٍ لا فجوةُ ملاحة — تُقيَّد Finding) | القياس: `NO_ROLE` في COMPARE |
| `EX-CEO` · `EX-DVP` | `EXECUTIVE` Workspace — دورةُ عملٍ تنفيذيّةٌ لا إداريّة (EX-DVP بلا ورقةِ دليلٍ ⇒ `NO_SPEC` مواضعُه تُحكم لاحقًا بورقتِه) | جسرُ EX-CEO⇒9 قائم |
| `WS-MY` | `PERSONAL` Workspace — بنودٌ شخصيّةٌ تُحقن داخلَ كلِّ دورٍ، **ليست إدارةً** ولا تُقاس مساحةَ أعمال | ورقةُ WS-MY في الدليل |
| `IAF` | `DEPARTMENT` (المراجعة الداخليّة · الدور 33) | جسرٌ قائم |
| `PLATFORM_SHARED` | `PLATFORM_UTILITY` — أسطحُ منصّةٍ تُصيَّر أدواتٍ خارجَ الدورةِ بإعلانِها، لا تُحشر في دورةِ إدارة | حكم T13 (12 سطحًا على مراجعة المالك) |

## ⑥ تصنيفُ بنودِ الهدف (المطلوب ١٠)

كلُّ صفِّ دليلٍ يُصنَّف قبل دخولِ مقامِ السايدبار:
`MENU_ITEM` (الافتراضُ للشاشةِ المبنيّةِ المستقلّة) · `TAB_CHILD` (سطحٌ ابنٌ
`SCREEN_SUBSYSTEM`/تبويبُ كيان) · `DIRECT_ONLY` (يُفتح من سياقِه لا من قائمة) ·
`PROJECTION` (`VIEW_VARIANT`) · `UTILITY` (بنيةٌ لا شاشةُ دورة) ·
`NOT_BUILT` (هدفٌ `NOT_IMPLEMENTED`/بلا صفّ). **ولا يدخل مقامَ التطابقِ إلا
`MENU_ITEM` المبنيّ.**
