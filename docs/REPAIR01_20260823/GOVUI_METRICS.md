# `GOVUI_METRICS` — المقاييسُ الاثنا عشرَ المطلوبةُ نصًّا (§19)

> مولَّدةٌ حيًّا بـ`tools/govui_metrics.php` · اللقطة **BL-20260901-57c12596** · 2026-09-01 10:40
> ⛔ **ولا تُجمع في نسبةٍ واحدة** — «لا نسبة واحدة اسمها مطابقة السايدبار» (§19).
> **وكلُّ مقياسٍ ببسطِه ومقامِه واستبعادِه.**

| المقياس | القيمة | الاستبعادُ والقراءة | الأداة |
|---|---|---|---|
| `TARGET_BUILD_COVERAGE` | **413/413** | لا استبعاد — كونُ الأهدافِ كلُّه من الملفَّين | `nav_placements × nav_targets` |
| `MENU_TARGET_COVERAGE` | **327/327** | خارجَ المقام: TAB_CHILD وPROJECTION وDIRECT_ONLY وUTILITY وNOT_BUILT (§8) | `govui_target_registry` |
| `BUILT_NOT_RENDERED` | **0/0** | مبنيٌّ بموضعِه ولا بندَ في سايدبارِ دورِه — الهدف 0 | `govui_label_measure (تصييرٌ حيّ)` |
| `GROUP_CONFORMANCE` | **307/307** | المُصيَّرُ في مجموعةِ دليلِه — المقامُ في اللوحة | `sidebar_guide_compare` |
| `ORDER_CONFORMANCE` | **307/307** | المُصيَّرُ في ترتيبِ دليلِه (LCS) | `sidebar_guide_compare` |
| `LABEL_CONFORMANCE` | **307/307** | غيرُ مبنيٍّ 0 · بلا دورٍ حيٍّ 44 · ابنُ تبويبٍ يُقاس في صفحتِه 62 | `govui_label_measure` |
| `ROLE_VISIBILITY_CONFORMANCE` | **301/301** | موضعُ قائمةٍ مبنيٌّ لدورِ مساحتِه صلاحيةُ عرضِه قائمة | `sidebar_guide_compare` |
| `FIELD_CONFORMANCE` | **5527/5534** | AUDIT إلحاقيّةٌ خارجَ المقام 1002 حقلًا (§7·11) · المقيسُ 366 سطحًا مطابَقًا | `rpr02_field_measure (الدفترُ مُسوًّى على الحزمةِ الجديدة)` |
| `GRAIN_CONFORMANCE` | **412/412** | يجمع حبّتَين 0 · بلا حبّةٍ مقيسةٍ 0 · وغيرُ المبنيِّ خارجَ المقام | `govui_target_registry × rpr02_grain_measure` |
| `STATE_MODEL_CONFORMANCE` | **85/85** | المطلوبُ = ما تُعرِّف له ورقةُ 08 آلةً (62 آلةً حاكمة) · والبسطُ آلةٌ بحقولِها الخمسةِ لا مرجعٌ باسم · والمقامُ الأوسع 392 مفروزٌ: 85 مطلوب + 163 BLOCKED_OWNER + 144 مرجعيٌّ بلا عمودِ حالة · (‏وسابقًا يُقاس 302/392 بمرجعٍ باسمٍ لا بنموذج) | `gov_state_model_bind × gov_state_models · govui_state_author` |
| `STRUCTURAL_DEPARTMENT_PASS` | **17/17** | إداراتٌ بلغت المطابقةَ البنيويّةَ — وDEP-08 وEX-DVP بلا دورٍ حيٍّ خارجَ المقام | `sidebar_guide_compare` |
| `HUMAN_DEPARTMENT_PASS` | **0/17** | التحقُّقُ البشريُّ بدورٍ حقيقيٍّ — بطاقاتُه جاهزةٌ والتوقيعُ بشريّ | `gov_exec_human_card` |
