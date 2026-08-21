# BL-20260821 — نقطة القياس المثبَّتة (Snapshot)

**معرّف اللقطة:** `BL-20260821-f0bc3e4e` — كل رقم في مخرجات هذه الحزمة الثلاثة مقيسٌ على هذه اللقطة وحدها.
أي جزء يُقاس بعد تغيّر البناء يُعاد قياسه أو يُوسم `STALE`.

| البند | القيمة | الدليل |
|---|---|---|
| تاريخ/وقت القياس | 2026-08-21 03:46 (توقيت الجهاز، UTC+03) — ساعة PHP CLI تطبع UTC (00:46)، الفارق موثَّق | `tools/arch_v21_measure.php` + `ls -la` |
| Environment | تطوير محلي — WAMP على Windows 11 Pro (10.0.22000)، Apache محلي `C:\wamp64\www\ems` | بيئة الجلسة |
| PHP (CLI المستخدم للقياس) | 8.2.30 (ZTS VC++ x64) | `php.exe -v` |
| PHP (الويب) | 8.2.30 — استدلالًا من ملف تثبيت WAMP `DO_NOT_DELETE_8.2.30.txt` | NEEDS_REVIEW (لم يُقس بـphpinfo) |
| قاعدة البيانات | MariaDB **11.4.9-log** · schema `equipation_manage` | `SELECT VERSION()` |
| Branch | `fix/remediation-2026-08` | `git branch --show-current` |
| commit_hash | `f0bc3e4eb0733148cb932060c2f1c0e076b9172e` — "Desigin Wave 02" (2026-08-17 15:59 +03) | `git rev-parse HEAD` |
| آخر Tag | `uxui-review-20260817` (الوصف: `uxui-review-20260817-37-gf0bc3e4e` أي 37 التزامًا بعده) | `git describe --tags` |
| التزامات غير مدفوعة | **0** تجاه upstream | `git rev-list @{u}..HEAD --count` |
| الشجرة العاملة | **غير نظيفة**: تعديل غير ملتزَم واحد `assets/css/ems.main.all.style.css` + ملف غير متتبَّع `docs/EMS_دليل_شاشات_السايدبار_الشامل_النهائي_20260821.xlsx` | `git status --porcelain` |
| إصدار الـSchema | 603 جدولًا أساسيًّا + 25 منظرًا · دفتر `schema_migrations` = 528 صفًّا (476 applied + 52 baseline) · آخر قيد: `2027_07_16_clients_optional_profile_fields.php` (baseline، قُيّد 2026-08-19) | قياس حي |
| هجرات غير مدفوعة/غير مقيَّدة | **35 ملف هجرة على القرص غير مقيَّد في `schema_migrations`** (من `2027_07_13` إلى `2027_08_16`) **وجداولها قائمة فعليًّا** — أي طُبِّقت خارج الدفتر ⇒ **FINDING F-02** | مقارنة glob × ledger |
| إصدار مكوّنات الواجهة | سجل `gov_component_versions`: آخر وسم **`ux-1.4.0` بحالة DRAFT** (2026-08-19) · **تحذير:** التعديل غير الملتزَم على `ems.main.all.style.css` يجعل بصمة الشجرة العاملة ≠ آخر بصمة مسجَّلة ⇒ **FINDING F-03** | استعلام السجل |
| إصدار سجل التنقّل | `nav_items` نشط = 1646 · `link_groups` نشط = 909 (من 1265) · `modules` = 490 · آخر هجرة تنقّل: `2027_08_16_nav_twelve_groups.php` | قياس حي |
| إصدار محرك الاعتماد | جيلان في القاعدة: سلالم الحوكمة `gov_ladders` (13 نشطًا) + `gov_journey_ladders` (14 رحلة) هي الجيل الحديث؛ `approval_workflow_rules` القديمة **صفر قاعدة نشطة** من 23 — تحديد المحرك الحي بالكود في INJ-ARCH-ASBUILT §7 | قياس حي + مسح كود |

## أرقام اللقطة المرجعية (كلها بمقامها)

المصدر الكامل: [`snapshot_measures.json`](snapshot_measures.json) — مولَّد بـ`tools/arch_v21_measure.php` على هذه اللقطة.

- جداول أساسية: **603** · منها بعمود `company_id`: **478** (والمناظر بعمود company_id: 11 من 25).
- قيود: FK **292** · CHECK **308** · UNIQUE **384** · Triggers **0** · Routines **0**.
- أدوار: **35** · مستخدمون نشطون: **75** · شركات: **2** · وحدات تنظيمية: **24**.
- صلاحيات: `role_permissions` **3041** · `gov_profile_items` **2620** · قاموس الأفعال `nav09_action_map` **465** · سجل حارس الأفعال `actions` **92** (منها 61 كتابة).
- ملفات PHP: إجمالي **5935** · أسطح الشاشات (مجلدات الواجهة) **635** · خدمات `app/Services` **193** في 33 نطاقًا · includes **98** · أدوات tools **420** · اختبارات tests **322**.
- سجلات الشاشات (المقامات السبعة تُسمّى عند كل استعمال): `nav_items` نشط 1646 · `gov_space_appearances` 836 · `gov_screen_cycle` 639 · `gov_migration_ledger` 606 · أسطح PHP حية 635 · `nav09_file_map` 258 · روابط السايدبار المصيَّرة 337.

## قاعدة عدم الخلط

من هذه اللحظة حتى اكتمال الحزمة: لا يُخلط قياس من commit آخر أو schema آخر داخل التقارير. أي إعادة قياس تُوسم بمعرّف لقطة جديد.
