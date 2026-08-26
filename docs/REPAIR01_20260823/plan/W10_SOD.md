# RPR-W10 — مصفوفةُ فصلِ الواجباتِ لنطاقِ الشقّ

> ⛔ **مولَّدٌ من المخزن — لا تحرّره يدويًّا**: `php tools/repair01_w10_docs.php` يعيد كتابتَه.

**فصلُ الواجباتِ يُنفَّذ لا يُعلَن**: لكلِّ عمليّةٍ حرِجةٍ رمزُ ردٍّ **مُثبَتٌ من القرص** في `app/Services/Governance/DeptSplitService.php`، و`W10-17` يقرأ الملفَّ ولا يصدّق السجلّ.

⛔ **ولا أسماءَ أشخاصٍ** — مفاتيحُ أدوارٍ وقواعدُ صلاحيةٍ ونائبٌ ونطاقٌ وتفويضٌ وتاريخُ سريان.


## AUDIT_READBACK  ·  قراءة سجل تدقيق قديم بمعرفه الاصلي

| الدور | المفتاح |
|---|---|
| Initiator | `ROLE_INTERNAL_AUDIT` |
| Reviewer | `ROLE_FIN_CONTROLLER` |
| Approver | `ROLE_CFO` |
| Executor | `ROLE_INTERNAL_AUDIT` |
| Reconciler/Closer | `ROLE_INTERNAL_AUDIT` |
| Deputy_Role | `ROLE_INTERNAL_AUDIT` |
| Authority_Rule_ID | `AUTH-W10-05` |
| Scope | ems_business_events و activity_logs |
| Delegation | لا تفويض على المراجعة المستقلة |
| Effective_Date | 2026-08-26 |

**التركيبةُ الممنوعةُ صراحةً:** يمنع اعادة ترقيم مرجع تدقيق باي حال — القراءة لا تكتب
**رمزُ الردِّ المنفِّذ:** `AUDIT_REFERENCE_RENUMBER_FORBIDDEN`

## BRIDGE_TRANSLATE  ·  ترجمة مؤشر حي يسمي الوحدة الام

| الدور | المفتاح |
|---|---|
| Initiator | `ROLE_CAMPAIGN_ENGINEER` |
| Reviewer | `ROLE_FIN_CONTROLLER` |
| Approver | `ROLE_CFO` |
| Executor | `ROLE_CAMPAIGN_ENGINEER` |
| Reconciler/Closer | `ROLE_INTERNAL_AUDIT` |
| Deputy_Role | `ROLE_FIN_MANAGER` |
| Authority_Rule_ID | `AUTH-W10-03` |
| Scope | nav_canonical و nav09_file_map و request_types |
| Delegation | لا تفويض على جدول حي |
| Effective_Date | 2026-08-26 |

**التركيبةُ الممنوعةُ صراحةً:** يمنع استبدال الاسم القديم في الجدول الحي — الترجمة في الجسر وحده
**رمزُ الردِّ المنفِّذ:** `LEGACY_POINTER_OVERWRITE_FORBIDDEN`

## OWNER_OVERRIDE  ·  تغيير مالك سطح مطبق

| الدور | المفتاح |
|---|---|
| Initiator | `ROLE_FIN_MANAGER` |
| Reviewer | `ROLE_FIN_CONTROLLER` |
| Approver | `ROLE_CFO` |
| Executor | `ROLE_CAMPAIGN_ENGINEER` |
| Reconciler/Closer | `ROLE_INTERNAL_AUDIT` |
| Deputy_Role | `ROLE_CFO` |
| Authority_Rule_ID | `AUTH-W10-04` |
| Scope | سطح واحد في كل مرة |
| Delegation | التفويض لنائب المدير المالي بحد مكتوب |
| Effective_Date | 2026-08-26 |

**التركيبةُ الممنوعةُ صراحةً:** من يقترح التغيير لا يعتمده — وتغيير المالك يغير من يرى الشاشة
**رمزُ الردِّ المنفِّذ:** `SAME_ACTOR_PROPOSE_AND_APPROVE`

## SPLIT_APPLY  ·  تطبيق الحكم على دفتر الاسطح وسجل الشاشات

| الدور | المفتاح |
|---|---|
| Initiator | `ROLE_CAMPAIGN_ENGINEER` |
| Reviewer | `ROLE_FIN_CONTROLLER` |
| Approver | `ROLE_CFO` |
| Executor | `ROLE_CAMPAIGN_ENGINEER` |
| Reconciler/Closer | `ROLE_INTERNAL_AUDIT` |
| Deputy_Role | `ROLE_FIN_MANAGER` |
| Authority_Rule_ID | `AUTH-W10-02` |
| Scope | السجلان معا |
| Delegation | لا تفويض — التطبيق باداة المرحلة وحدها |
| Effective_Date | 2026-08-26 |

**التركيبةُ الممنوعةُ صراحةً:** لا يطبق حكم بلا قاعدة ومرساة مكتوبتين في الدفتر
**رمزُ الردِّ المنفِّذ:** `SPLIT_OWNER_CHANGE_WITHOUT_RULE`

## SPLIT_DECIDE  ·  حسم شق سطح على احد الشقين

| الدور | المفتاح |
|---|---|
| Initiator | `ROLE_CAMPAIGN_ANALYST` |
| Reviewer | `ROLE_FIN_CONTROLLER` |
| Approver | `ROLE_CFO` |
| Executor | `ROLE_CAMPAIGN_ENGINEER` |
| Reconciler/Closer | `ROLE_INTERNAL_AUDIT` |
| Deputy_Role | `ROLE_FIN_MANAGER` |
| Authority_Rule_ID | `AUTH-W10-01` |
| Scope | اسطح الوحدة المشقوقة |
| Delegation | التفويض بكتاب موقع لا بادعاء دور |
| Effective_Date | 2026-08-26 |

**التركيبةُ الممنوعةُ صراحةً:** من يحسم الشق لا يطبقه على السجلين — والحسم قرار والتطبيق تنفيذ
**رمزُ الردِّ المنفِّذ:** `SAME_ACTOR_DECIDE_AND_APPLY`

---

**المقيس:** 5 عمليّةً حرِجةً — ولكلٍّ رمزُ ردٍّ مُثبَتٌ من القرص.
