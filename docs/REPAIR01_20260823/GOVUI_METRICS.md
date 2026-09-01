# `GOVUI_METRICS` — المقاييسُ الاثنا عشرَ المطلوبةُ نصًّا (§19)

> مولَّدةٌ حيًّا بـ`tools/govui_metrics.php` · اللقطة **BL-20260901-efb30839** · 2026-09-01 17:13
> ⛔ **ولا تُجمع في نسبةٍ واحدة** — «لا نسبة واحدة اسمها مطابقة السايدبار» (§19).
> **وكلُّ مقياسٍ ببسطِه ومقامِه واستبعادِه.**

| المقياس | القيمة | الاستبعادُ والقراءة | الأداة |
|---|---|---|---|
| `TARGET_BUILD_COVERAGE` | **412/413** | لا استبعاد — كونُ الأهدافِ كلُّه من الملفَّين | `nav_placements × nav_targets` |
| `MENU_TARGET_COVERAGE` | **326/326** | خارجَ المقام: TAB_CHILD وPROJECTION وDIRECT_ONLY وUTILITY وNOT_BUILT (§8) | `govui_target_registry` |
| `BUILT_NOT_RENDERED` | **0/0** | مبنيٌّ بموضعِه ولا بندَ في سايدبارِ دورِه — الهدف 0 | `govui_label_measure (تصييرٌ حيّ)` |
| `GROUP_CONFORMANCE` | **306/306** | المُصيَّرُ في مجموعةِ دليلِه — المقامُ في اللوحة | `sidebar_guide_compare` |
| `ORDER_CONFORMANCE` | **306/306** | المُصيَّرُ في ترتيبِ دليلِه (LCS) | `sidebar_guide_compare` |
| `LABEL_CONFORMANCE` | **306/306** | غيرُ مبنيٍّ 1 · بلا دورٍ حيٍّ 44 · ابنُ تبويبٍ يُقاس في صفحتِه 62 | `govui_label_measure` |
| `ROLE_VISIBILITY_CONFORMANCE` | **300/300** | موضعُ قائمةٍ مبنيٌّ لدورِ مساحتِه صلاحيةُ عرضِه قائمة | `sidebar_guide_compare` |
| `FIELD_CONFORMANCE` | **2541/5420** | AUDIT إلحاقيّةٌ خارجَ المقام 985 حقلًا (§7·11) · المقيسُ 359 سطحًا مطابَقًا | `rpr02_field_measure (الدفترُ مُسوًّى على الحزمةِ الجديدة)` |
| `GRAIN_CONFORMANCE` | **374/412** | يجمع حبّتَين 6 · بلا حبّةٍ مقيسةٍ 32 · وغيرُ المبنيِّ خارجَ المقام | `govui_target_registry × rpr02_grain_measure` |
| `STATE_MODEL_CONFORMANCE` | **149/210** | المقامُ أسطحُ المعاملاتِ الحقيقيّةُ وحدها (حبّةٌ سطرٌ/بندٌ وحقيقةٌ مملوكة) · والباقي بلا آلةٍ مؤلَّفةٍ — والتأليفُ حكمُ أعمالٍ لا قياس | `rpr02_state_model_bind (المدى نفسُه)` |
| `STRUCTURAL_DEPARTMENT_PASS` | **17/17** | إداراتٌ بلغت المطابقةَ البنيويّةَ — وDEP-08 وEX-DVP بلا دورٍ حيٍّ خارجَ المقام | `sidebar_guide_compare` |
| `HUMAN_DEPARTMENT_PASS` | **0/17** | التحقُّقُ البشريُّ بدورٍ حقيقيٍّ — بطاقاتُه جاهزةٌ والتوقيعُ بشريّ | `gov_exec_human_card` |
