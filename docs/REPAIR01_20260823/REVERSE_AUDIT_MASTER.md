# REVERSE_AUDIT_MASTER — المراجعةُ العكسيّةُ الشاملةُ من المصدرِ الحاكمِ إلى النظام

> **Baseline_ID:** `BL-20260901-cce83748` · مولَّدٌ قياسًا حيًّا: `php tools/gov_exec_pack12.php` · 2026-09-01 11:59
> حزمةُ GOV_EXEC §23 — **اثنتا عشرةَ وثيقةً من اللقطةِ الواحدة** ولا رقمَ منقولًا.

الاتجاه: **مصدرٌ حاكمٌ ← هدفٌ ← تنفيذٌ ← زمنُ تشغيلٍ ← دليل** (م115) — وكلُّ طبقةٍ بوثيقتِها من هذه الحزمةِ الواحدة:

| الطبقة | الوثيقة | خلاصةُ حكمِها |
|---|---|---|
| خريطةُ المصادر (§4) | `registers/GOVERNING_SOURCE_MAP.md` | مطابقةٌ بورقةِ J حرفًا · TARGET_WITHOUT_GOVERNING_SOURCE=0 |
| الدستور (§7) | `CONSTITUTION_COMPLIANCE.md` | كلُّ صفِّ مصدرٍ بفحصِه أو ◐ ببندِ سجلٍّ — صفرُ مخالفةٍ بلا حكم |
| القرارات (§8) | `DECISION_PROPAGATION_REGISTER.md` | 114/114 محكومةً بمجسِّها · UNPROPAGATED=0 |
| الإدارات (§5·§24) | `DEPARTMENT_CONFORMANCE.md` | البنيةُ بسقفِها وجبهتا الحقولِ والبناءِ مفتوحتان بترتيبِ السجلّ |
| القيادة (§6) | `EXECUTIVE_CONFORMANCE.md` | EX-CEO مطابقٌ فيما بُني · EX-DVP بحاجزِ دورِه |
| السايدبار (§9·§10) | `SIDEBAR_CONFORMANCE.md` + `SIDEBAR_GUIDE_COMPARE.md` + `NAVR_METRICS.md` | غيرُ مطابقٍ=0 · سقوطٌ=0 · النسبُ 0 غيرَ مفسَّر |
| الصلاحيات (§13) | `ROLE_PERMISSION_CONFORMANCE.md` | القالبُ النافذُ يحكم حصرًا · التوحيدُ بندُ سجلّ |
| الحقول (§12) | `SCREEN_FIELD_CONFORMANCE.md` | الدفترُ مُسوًّى على -3 والحملةُ بترتيبِ السجلّ |
| آلاتُ الحالة (§14) | `STATE_MODEL_CONFORMANCE.md` | الواجبُ 100٪ وRegression Gate |
| الأحداث (§15) | `EVENT_INTEGRATION_CONFORMANCE.md` | EFFECT_MISSING=0 والدفترُ حصين |
| قراراتُ المالكِ المفتوحة | `OPEN_OWNER_ACTIONS.md` | الحقيقيّةُ وحدَها ببوّاباتِها |

**قاعدةُ القراءة**: ما بلغ سقفَه Regression Gate يُشغَّل لا يُعاد (§22) — والمفتوحُ كلُّه بندُ سجلٍّ جامعٍ أو بوّابةُ مالكٍ مسمّاة.
