# بطاقة مواصفة · الموجة ① — بنية ORG-01 (ORG-01→ORG-05)

| البند | القيمة |
|---|---|
| المصدر الحاكم | `ORG-01 §1.1 · §2 · §5 · §7` — `docs/sources/ORG-01.extracted.md` |
| الهجرة | `database/migrations/2026_08_02_org01_structure.sql` |
| الحزام | `tests/org_structure_test.php` — **28/28** |

## الجداول الأحد عشر + المنظر

| الجدول | الدور | التصنيف في TenantRegistry |
|---|---|---|
| `org_units` | الوحدات الأربع عشرة (§1.1) بطبقاتها الثلاث وشجرتها | T_TENANT |
| `org_assignment_types` | 13 نوع تكليف (5 مركزية + 8 موقعية) — صف لا Enum | T_CATALOG |
| `org_assignments` | مصدر الحقيقة الوحيد للتكليف: نطاق + مدة إلزامية + مصدر قرار + نائب | T_TENANT |
| `assignment_capabilities` | الصلاحيات وسقوفها — التشغيلي نطاقي (`amount_cap=NULL`) والمالي نقدي | T_CHILD←org_assignments |
| `assignment_reporting_lines` | خطا التبعية (تشغيلي/فني) — `UQ(asg_id, line_type)` | T_CHILD←org_assignments |
| `assignment_audit` | سجل Insert-only | T_CHILD←org_assignments |
| `permit_types` | الأنواع التسعة (§5) | T_CATALOG |
| `permit_requests` | طلب الإذن بحالاته الست | T_TENANT |
| `permit_required_approvals` | مصفوفة §5 منمذجة — 24 صفًّا | T_CHILD←permit_types |
| `permit_approval_actions` | القرارات — `UQ(req_id, rq_id)` | T_CHILD←permit_requests |
| `permit_status_history` | Insert-only | T_CHILD←permit_requests |
| `v_org_unit_heads` | **ORG-02**: رأس الوحدة مشتق من التكليف النافذ — لا عمود يُكتب | T_TENANT (قراءة) |

## القرارات البنيوية

- **القيد الحرج** «مدير حركة واحد نشط لكل موقع»: عمود مولَّد `active_site_mgr_key`
  (NULL لغير النشط) + فهرس فريد — نمط `primary_flag` القائم في المستودع، لا `CHECK`.
- **ORG-02**: خيار الوثيقة الثاني («يُحذف ويُقرأ بدالة») — لا عمود `head_person_id`
  أصلًا؛ القراءة عبر `v_org_unit_heads` المقيَّد بـ`is_unit_head=1` وصلاحية التاريخ والحالة.
- `person_id` يشير إلى `users.id` — محاكاةً لنمط `signing_authorities.person_id`.
- قيود لا تعبّر عنها القاعدة تُحرس في `AssignmentService` (الموجة ②): «الموقعي له
  خطان» 422 · «مصدر القرار مخوَّل» 403 · «تداخل تكليفين» 409.
- بذر الوحدات لشركة العمليات (4) — الشركة 1 قشرة مجانية بلا مواقع.
