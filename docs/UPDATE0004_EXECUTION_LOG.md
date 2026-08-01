# update0004 · سجلُّ التنفيذ

> صفٌّ لكل مهمة — البرومت §6⑤. الفرع: `feature/update0004`.

## الموجة ⓪ · الحسمُ النصي — 7/7

| المهمة | ما فُعل | الملفات | الاختبار | اجتهاد |
|---|---|---|---|---|
| D-01 | `NAV-01 §9.5`: «إدارةٌ موازيةٌ تحت المدير التنفيذي» → «إدارةٌ تحت مدير التشغيل إداريًّا باستقلالٍ فنيٍّ تام» — الموضعُ الوحيدُ المخالف، وORG-01/SEC-01 لم تُمسّا | `docs/sources/NAV-01.corrected.md` | diff يظهر التغييرَ وحدَه ✅ | — |
| D-02 | ترقيمُ TKT-01: `#6` الثاني (الأثر الحقيقي) → 7 · وما بعده 8→13 · `§11.1`→`§12.1` · إحالةُ WatchTowerService «مؤشرات §8» → §11 — تسعةُ تعديلات | `docs/sources/TKT-01.corrected.md` | مسحُ كل `§n` بعد التعديل — لا إحالة يتيمة ✅ | J-03 |
| D-03 | `sod_conflicts` «الثمانية في §6» → §5 · وحذفُ `organizational_levels` من §11.2① («إلى اثنين منفصلين») | `docs/sources/SEC-01.corrected.md` | diff بتعديلين فقط ✅ | J-01 |
| D-04 | UAT-01: الصفُّ الأخير ⑯→⑱ (`DEC-UAT-I`) · «بالستة عشر»→«بالثمانية عشر» (§12③) · «الستةَ عشرَ»→«الثمانيةَ عشرَ» (§13②) · «الشواهد العشرة»→«الأربعةَ عشرَ» (§13④) · ⑥ المكرر في §13 → ⑦ والنظافة → ⑧ · `SEC-01 §8.2`→`§7.2` — سبعةُ تعديلات | `docs/sources/UAT-01.corrected.md` | diff بسبعة مواضع ✅ | — |
| D-05 | الملحق: 13→12.2 · 14→12.3 · 15→12.4 · 16→12.5 · 17→12.6 · 17.1→12.6.1 — فلا يصطدم §13 بالأساس، وإحالاتُه الداخلية (§12.2–12.5) صارت مطابقة | `docs/sources/UAT-01_APPENDIX12.corrected.md` | فحصُ الإحالات الداخلية — متطابقة ✅ | J-02 |
| D-06 | سطرُ تثبيت `DEC-NAV-J` بعد عنوان `NAV-01 §4`: المجموعاتُ الثماني مستوًى ثانٍ داخل الأبواب الثمانية، إحالةً إلى §10① و`DEC-01 ②` | `docs/sources/NAV-01.corrected.md` | ✅ | — |
| D-07 | سجلُّ القرارات: `DEC-ORG-A` معكوسًا · `DEC-SEC-F/H` و`DEC-NAV-C/J` و`DEC-UAT-I` مغلقةً · `DEC-SEC-K` و`DEC-NAV-E` و`DEC-UAT-G` مفتوحةً بأحكام مضيّها · وقرارا النطاق | `docs/UPDATE0004_DECISIONS_ar.md` | — | — |

**ملاحظة قياس:** الحزمةُ اكتملت تسعَ وثائقَ في `docs/sources/` (الخمسُ الأصلية + `DEC-01` + ملحق §12 + `PLAN-05` + طلب تجربة النظام) منسوخةً من Downloads بلا مساس بالأصول، ومستخرَجةً نصًّا بـ`docx2md.php`.

## الموجة ① · ORG-01 البنية — 5/5

**القياس قبل التنفيذ:** 320 جدولًا · صفر جدول `org_*`/`permit_*`/`assignment_*` · `positions`=0 · `signing_authorities` قائم بperson_id=users.id · نمط الفريد المشروط القائم: عمود مولَّد NULL + UNIQUE (`primary_flag`/`live_type_key`).

| المهمة | ما فُعل | الملفات | الاختبار | اجتهاد |
|---|---|---|---|---|
| ORG-01 | الجداول الستة: `org_units` · `org_assignment_types` · `org_assignments` (المدة والنطاق إلزاميان · UQ طبيعي · **الفريد المشروط `active_site_mgr_key`** لمدير الحركة) · `assignment_capabilities` · `assignment_reporting_lines` · `assignment_audit` | `database/migrations/2026_08_02_org01_structure.sql` · `app/Core/TenantRegistry.php` | O-belt ①③④⑥ ✅ | J-09 |
| ORG-02 | لا عمود `head_person_id` — منظر `v_org_unit_heads` يشتق الرأس من التكليف النافذ (نوع `is_unit_head` + حالة active + سريان التاريخ) | الهجرة نفسها | ⑤ يظهر ويسقط بالإنهاء ✅ | J-05 |
| ORG-03 | بذر 13 نوعًا: 5 مركزية + 8 موقعية كلها `requires_functional_line=1` — وضابط البوابة صف (DEC-ORG-B) | الهجرة | عدّ 5+8 ✅ | — |
| ORG-04 | بذر الوحدات الأربع عشرة (§1.1) بطبقاتها: 8 تشغيلية (البنات تحت «التشغيل» والموارد البشرية معها بالتبعية الإدارية) · 4 موازية · 2 رقابية — للشركة 4 | الهجرة | عدّ 14 ✅ | J-06 |
| ORG-05 | جداول الأذونات الخمسة + بذر الأنواع التسعة + مصفوفة §5 = 24 صف موافقة مرتبة | الهجرة | عدّ 9 و24 وترتيب الحركة أولًا ✅ | J-07 · J-08 |

**الحزام:** `tests/org_structure_test.php` = **28/28** ✅ · `dump-schema` حُدِّث (323 جدولًا + منظر).

## الموجة ② · ORG-01 السلطة — 5/5

**القياس قبل التنفيذ:** `AuthorityGuard::sign` يفحص الذات ثم التفويض (`signing_authorities`) — لا مساس بمساره؛ `guard_denials` جاهز للتسجيل؛ الدور 1 = مدير التشغيل و-1 = الأعلى (`includes/roles.php`)؛ `fin_notifications.target_level` ENUM بلا قيمة لمدير التشغيل.

| المهمة | ما فُعل | الملفات | الاختبار | اجتهاد |
|---|---|---|---|---|
| ORG-06 | `OrgAuthorityResolver`: التكليفات النافذة وصلاحياتها وسقوفها · منتهٍ→403 مسجَّلة · خارج النطاق→403 · فوق السقف→409 بالمتاح · التغطية بالتقاطع (مشروع↔مواقعه) | `app/Services/Org/OrgAuthorityResolver.php` | O2·O3·O5 ✅ | J-11 |
| ORG-07 | الوصل بـ`AuthorityGuard` خلف `EMS_ORG_AUTHORITY` (off·monitor·enforce) + عمود `org_asg_id` في `approval_signatures` فالتوقيع بمرجع التكليف | `app/Core/AuthorityGuard.php` · `2026_08_02_org02_signature_asg_ref.sql` · `.env`(+example)=monitor | O8 ✅ | J-10 |
| ORG-08 | `AssignmentService`: 422 المدة/النطاق · 403 مصدر القرار · 409 التداخل وO1 · 422 الموقعي بلا خطين · إنشاء ذري بصلاحيات وخطوط وسجل · إنهاء «الطلب للفني والقرار لمن كلّف» (202) | `app/Services/Org/AssignmentService.php` | O1·O6·O6-ب·O6-و ✅ | J-12 |
| ORG-09 | `AssignmentExpiryJob` (سقوط آلي بساعة القاعدة + تنبيه 30 يومًا بعطالة يومية) · `DeputyResolver` (الغياب/التعليق يفعّلان النائب بسطر delegated · لا نيابة بعد الانتهاء) · كرون يومي | `app/Services/Org/AssignmentExpiryJob.php` · `DeputyResolver.php` · `Operations/cron_org_assignments.php` | O2·O4·O7 ✅ | — |
| ORG-10 | حزام O1→O8 كاملًا | `tests/org_authority_test.php` | **26/26** ✅ | — |

## الموجة ③ · ORG-01 الأذونات — 4/4

| المهمة | ما فُعل | الملفات | الاختبار | اجتهاد |
|---|---|---|---|---|
| ORG-11 | `PermitGate`: طلب → موافقات متسلسلة → approved بvalid_until من ساعة القاعدة → استهلاك used · وأحداث PermitApproved/Used/Expired سطور `permit_status_history` · والمنع في `guard_denials` بكود `permit_gate` | `app/Services/Org/PermitGate.php` | ①③④ ✅ | — |
| ORG-12 | التسلسل: خطوة قبل سابقتها → **409** · موافق بلا تفويض لدوره → **403** (الدور يُحل من التكليفات النافذة للمجالات التشغيلية الست، وبusers.role للموازية fleet/hr/material_owner) · إذن منتهٍ يُستعمل → **423** + مسح دوري في الكرون | نفسه + `Operations/cron_org_assignments.php` | ①②④ ✅ | J-13 |
| ORG-13 | الوصل بالمواضع التسعة خلف `EMS_PERMIT_GATE` (off افتراضًا · monitor يسجّل ويمضي · enforce يمنع · `EMS_PERMIT_GATE_SITES` للتجربة بموقع واحد): دخول معدة + إنهاء تشغيل (خروج) + تعمل/جاهزة (خدمة) + دخول مشغّل في `movement_operations.php` · دخول مشتريات `receipt_custody_proc.php` · خروج مواد `issue_proc.php` · دخول فني `Maintenance/orders.php` · خروج عامل `final_settlement.php` | الخمسة + `includes/permit_gate.php` | مسبار monitor فرعي ✅ | J-14 |
| ORG-14 | صندوق «أذونات المواقع» في الصندوق الجامع — بند واحد لكل إذن معلَّق بدوره الحالي | `app/Services/Finance/ApprovalsInboxService.php` | ⑥ ✅ | — |

**الحزام:** `tests/permit_gate_test.php` = **24/24** ✅ · lint كل الملفات المعدلة نظيف.
