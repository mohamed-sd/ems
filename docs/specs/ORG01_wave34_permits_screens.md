# بطاقة مواصفة · الموجتان ③④ — أذونات ORG-01 وشاشاته

## الموجة ③ (ORG-11→14)

| البند | القيمة |
|---|---|
| الخدمة | `app/Services/Org/PermitGate.php` — request → approve متسلسلة → approved (valid_until بساعة القاعدة) → check/consume → sweep |
| الأحكام | خطوة قبل سابقتها **409** · موافق بلا تفويض لدوره **403** · إذن منتهٍ **423** · بلا إذن **403** (enforce) أو تسجيل ومضي (monitor) |
| حل الموافق | المجالات التشغيلية الست من التكليفات النافذة (`OrgAuthorityResolver`) · والموازية بusers.role: fleet={3,10} hr={4} material_owner={3,2} (J-13) |
| المواضع التسعة | movement_operations (دخول معدة · خدمة دخولًا وخروجًا · خروج بإنهاء التشغيل · دخول مشغّل) · receipt_custody_proc (دخول مشتريات) · issue_proc (خروج مواد) · Maintenance/orders (دخول فني) · final_settlement (خروج عامل) — كلها عبر `includes/permit_gate.php` |
| الأعلام | `EMS_PERMIT_GATE=off` (لا يُقلب ذاتيًّا — enforce بلا بذر يوقف الحركة) · `EMS_PERMIT_GATE_SITES` للتجربة بموقع واحد |
| الصندوق الجامع | صندوق «أذونات المواقع» في `ApprovalsInboxService` — بند لكل إذن معلَّق بدوره الحالي |
| الحزام | `tests/permit_gate_test.php` = **24/24** (مع مسبار فرعي لmonitor لأن ems_env static) |

## الموجة ④ (ORG-15→18)

| الشاشة | المسار | المضمون |
|---|---|---|
| التكليفات | `admin/org_assignments.php` | إنشاء (بخطي التبعية للموقعي) · إنهاء/تعليق عبر الخدمة · سجل Insert-only ظاهر |
| الهيكل | `admin/org_structure.php` | الطبقات الثلاث والأربع عشرة وحدة · الرأس مشتق من `v_org_unit_heads` |
| الأذونات | `admin/org_permits.php` | طلب · خطوات بترتيبها · موافقة/رفض للخطوة المفتوحة وحدها |
| لوحة مدير التشغيل | `admin/ops_manager_board.php` | المجموعات السبع (§3.1) · «ما ينتظر قراره» **بساعات الانتظار ومجموعها** لا بالعدد فقط |

- التسجيل: `2026_08_02_org04_screens_registration.sql` — 4 modules · صلاحيات الدور 1 كاملة والدور 6 (الأذونات) · 6 nav_items.
- الحزام: `tests/org_screens_http_test.php` = **11/11** على Apache حي (تحقُّق حجب الدور 12 خادميًّا).
