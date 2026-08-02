# تقرير فروق الموجة ⓪ — SEC-01

الأصل: نسخة `Downloads` لم تُمسّ → `SEC-01.docx` (نسخة العمل) → `SEC-01.extracted.md` (النص المستخرج) → `SEC-01.corrected.md` (المصحَّح).

كل تغيير بصيغة unified diff — السطر `-` قبل التصحيح والسطر `+` بعده:

```diff
--- SEC-01.extracted.md	2026-08-02 01:04:36.261414100 +0300
+++ SEC-01.corrected.md	2026-08-02 01:07:27.236324700 +0300
@@ -269,3 +269,3 @@
 |---|---|
-| **إلى ثلاثةٍ منفصلة**: `org_units` للهيكل · `job_titles` للمسميات · `organizational_levels` للمستويات — **و`roles` القديمُ يصير طبقةَ توافقٍ ومصدرَ ترحيلٍ لا مصدرَ حقيقة** | ① «الإدارة = دور» |
+| **إلى اثنين منفصلين**: `org_units` للهيكل · `job_titles` للمسميات والمستويات — **و`roles` القديمُ يصير طبقةَ توافقٍ ومصدرَ ترحيلٍ لا مصدرَ حقيقة** | ① «الإدارة = دور» |
 | **إلى ستةَ عشرَ فعلًا** (§4.1-ب) — فالأربعةُ لا تصف الإرسالَ ولا الاعتمادَ ولا العكسَ ولا التصديرَ ولا السقفَ ولا النطاق | ② الرايات الأربع |
@@ -344,3 +344,3 @@
 | st_id (PK) · req_id (FK) · **seq_no** · **`approver_rule` قاعدةٌ ديناميكيةٌ لا دورٌ ثابت**: `hr` · **`functional_owner`** (يُحلُّ من ORG-01 بحسب المجال والنطاق والتاريخ — **فهو مديرُ التشغيل للتشغيلية والمديرُ التنفيذيُّ للموازية**) · `requester_department_manager` · **`finance_owner_if_financial`** · `security_manager` · `executive` · **mandatory** · approver_person_id (يُحلّ لحظةَ الفتح) · auth_id (FK) · decision · reason · at — **UQ(req_id, seq_no)** · **ولا تُفتح خطوةٌ قبل سابقتها** | permission_approval_steps |
-| sod_id (PK) · **conflict_code** · permission_a · permission_b · severity · **compensating_control** · active — **الثمانيةُ في §6 صفوفٌ هنا** · ويُفحص عند حساب الصلاحية لا بعده | sod_conflicts |
+| sod_id (PK) · **conflict_code** · permission_a · permission_b · severity · **compensating_control** · active — **الثمانيةُ في §5 صفوفٌ هنا** · ويُفحص عند حساب الصلاحية لا بعده | sod_conflicts |
 | cycle_id (PK) · org_unit_id · **period** · manager_person_id · due_at · **state (open·signed·escalated)** · signed_at — **رأسُ دورة المراجعة** · وما لم يُوقَّع في مهلته يُصعَّد | permission_review_cycles |
```
