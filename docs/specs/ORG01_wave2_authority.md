# بطاقة مواصفة · الموجة ② — سلطة ORG-01 (ORG-06→ORG-10)

| البند | القيمة |
|---|---|
| المصدر الحاكم | `ORG-01 §2 · §7 (الخدمات) · §7.1 (O1→O8) · §8` |
| الملفات | `app/Services/Org/{OrgAuthorityResolver,AssignmentService,DeputyResolver,AssignmentExpiryJob}.php` · `Operations/cron_org_assignments.php` · وصل في `app/Core/AuthorityGuard.php` |
| الهجرة | `2026_08_02_org02_signature_asg_ref.sql` — عمود `approval_signatures.org_asg_id` (nullable · بنمط information_schema+PREPARE) |
| العلم | `EMS_ORG_AUTHORITY=monitor` في `.env` — **القلب إلى enforce مؤجَّل للمالك** (§1.3②) |
| الحزام | `tests/org_authority_test.php` — **26/26** |

## الخدمات

- **OrgAuthorityResolver** — `resolve()` التكليفات النافذة بصلاحياتها؛ `can()` قلب
  الحارس: منتهٍ → 403 «سقطت آليًّا» مسجَّلة في `guard_denials` بكود `org_authority`؛
  خارج النطاق → 403؛ فوق سقف الصلاحية النقدي → 409 برسالة المتاح. التغطية:
  تطابق النطاق، أو مشروع يغطي مواقعه (`sites.project_id`)، أو `site_group:0` للمركزي.
- **AssignmentService** — `create` (422 مدة/نطاق · 403 مصدر قرار ليس دور 1/-1 ·
  409 تداخل · 422 موقعي بلا خطين · معاملة ذرية · صلاحيات النوع الافتراضية) ·
  `end` («الطلب للمدير الفني والقرار لمن كلّف»: غير المخوَّل → 202 طلب مسجَّل
  لا تنفيذ) · `suspend` · وكل فعل سطر `assignment_audit`.
- **DeputyResolver** — الغياب أو التعليق يفعّلان النائب بمدة التكليف وسقفه مع سطر
  `delegated`؛ الانتهاء لا نيابة بعده — تُرفع للأعلى.
- **AssignmentExpiryJob + cron** — الإنهاء الآلي بساعة القاعدة (`CURDATE`) + تنبيه
  ثلاثين يومًا عبر `fin_notifications` (`target_level='all'` — الENUM بلا قيمة لمدير
  التشغيل ولا تُخترع قيمة تُبتلع صامتة).

## وصل AuthorityGuard (ORG-07)

قبل فحص التفويض: `EMS_ORG_AUTHORITY` — `monitor` يحلّ ويسجّل المخالفة ويمضي؛
`enforce` يرفض 403؛ وفي الحالين التوقيع الناجح يحمل `org_asg_id` (O8).

**شرط قلب enforce (للتقرير الختامي):** بذر تكليفات كل المعتمدين الفعليين +
صفر منع كاذب في مراقبة 14 يومًا.
