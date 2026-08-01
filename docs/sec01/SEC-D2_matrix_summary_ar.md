# SEC-D2 · مصفوفة الشاشات والأفعال (212 × 25) — مسودة التحويل

> CSV كامل: `SEC-D2_matrix_208x25.csv` — صف لكل (دور × موديول) = 5300 صفًّا، منها **1014** بمنح قائم (role_permissions=1008 بالرايات الأربع).

## قاعدة التحويل المعلنة (وكلها ★ تنتظر الاعتماد)

| الراية القديمة | الأفعال الستة عشر المشتقة |
|---|---|
| can_view | screen_view · tab_view · field_view · export · print |
| can_add | create · submit |
| can_edit | update · return_for_fix |
| can_delete | **delete_draft فقط** — وحذف ذي الأثر ممنوع بحارس never (§11.5) |
| — | approve · reject · cancel · reverse · grant_permission · override_cap = **لا تُشتق من راية** — تُعبَّأ من القوالب المعتمدة |

## النطاقات التسعة

شركة · إدارة · قسم · وحدة · مشروع · موقع · مجموعة مواقع · وردية · سجلاته هو —
الاقتراح الآلي: `role_scope=mine` → **site** و`gloable` → **company** (★ يُضبط دورًا دورًا عند الاعتماد).
