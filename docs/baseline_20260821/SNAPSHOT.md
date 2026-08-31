# BL-20260901 — نقطة القياس المثبَّتة (Snapshot)

**معرّف اللقطة:** `BL-20260901-15ba9f3c` — كل رقم في مخرجات هذه الحزمة مقيسٌ على هذه اللقطة وحدها.
**اللقطات التسع السابقة** في [`historical/`](historical/) — **أرقامها تاريخ ولا تُقرأ حاضرًا** (آخرها `BL-20260831d-aba54573`).

> **حزمةُ GATE-00 الذرّيّة**: هذه اللقطةُ قُصَّت تنفيذًا لأمرِ `GOV_EXEC` (الحزمةُ الحاكمةُ الجديدة -3/-3/-2) —
> **تشغيلٌ حاكمٌ واحدٌ** على `HEAD=15ba9f3c` (شجرةٌ نظيفةٌ قبل القياس) أعاد توليدَ:
> الاستخراجات (`db_dump` · `db_dump2` · `db_dump4` · `disk_scan` · `reconcile` ·
> `field_extract`) ثم `SIDEBAR_GUIDE_COMPARE` + `NAVR_METRICS` — وكلُّ وثائقِ
> الحزمةِ الثمانيةِ تحمل معرِّفَ اللقطةِ هذا (انظر [`GATE00_BASELINE.md`](../REPAIR01_20260823/GATE00_BASELINE.md)).
>
> **وأربعُ نتائجَ تُقرأ قبل أيِّ رقم** (دلتا الحزمةِ الجديدة aba54573 ⇒ 15ba9f3c · **4 التزامات**):
> ① **الحزمةُ الحاكمةُ الجديدة**: الدليل -3 والدستور -3 (م113–122 + ورقة J) والسجلات -2 —
>   فرقُها المقيسُ في `GOV_PACK_DIFF_20260831.md` وأعمالُ الحزمةِ القديمةِ كلُّها صامدةٌ فوقها.
> ② **مصالحةُ نسبِ WH-\*** (م121): `gov_req_id_recon` 19 حكمًا بالاسمِ المطبَّع · 421 صفًّا
>   رُقّمت عبر 11 سجلًّا حيًّا والمغلقاتُ الثماني صامدةٌ بأدلّتِها · الفارقُ +17 مفكَّكٌ حقلًا حقلًا.
> ③ **شاشةُ «إسناد أمناء المخازن» بُنيت كاملةً** (WH-03 الجديد · `proc_wh_custodian` ·
>   `SCR-0817` · TAB_CHILD بحكمِ الورقة «Child of خ02») بتوصيلِها الكامل — وW9 30/30
>   ولوحةُ المقارنةِ `MATCH_BUILT=19` وغيرُ مطابقٍ = 0 على الدليلِ **الجديد**.
> ④ **تمرينا تثبيتٍ** (`DR-2026-0008/0009` · 1025 كائنًا) بعد هجرتَي الحزمة — مطابقةٌ تامّة.

## نقطة القياس

| البند | القيمة | الدليل |
|---|---|---|
| تاريخ/وقت القياس | **2026-09-01 · 00:55 → 01:00** (UTC+03) | طوابع الاستخراج |
| Environment | تطوير محلي — WAMP على Windows 11 Pro، `C:\wamp64\www\ems` | بيئة الجلسة |
| PHP | **8.2.30** (أدوات القياس) · **8.3.28** (جلسة التنفيذ) | `php -v` |
| قاعدة البيانات | MariaDB **11.4.9-log** · `equipation_manage` · المنفَذ **3307** | `SELECT VERSION()` |
| Branch | **`repair01/w01-ownership`** — **الدفعُ ما زال محجوبًا بيئيًّا** (مفتاح SSH بعبارة مرور) | `git branch` · محاولة push |
| commit_hash | **`15ba9f3c`** — «GOV_EXEC محور 1» | `git rev-parse HEAD` |
| التزامات غير مدفوعة | **323 تجاه `origin/main`** · **4 التزامات منذ لقطة aba54573** | `git rev-list --count` |
| الوسوم | **20** | `git tag` |
| **الشجرة العاملة** | ✔ **نظيفة عند بدء القياس** — والمتغيّر بعده مخرجات التشغيلة الحاكمة نفسها | `git status --porcelain` |
| إصدار الـSchema | **997 جدولًا أساسيًّا** (+2: سجلُّ مصالحةِ المعرِّفات وجدولُ الإسناد) + 28 منظرًا | information_schema |
| **دفتر الهجرات** | `schema_migrations` **820 صفًّا** (+4: هجرتان وعكساهما baseline) · خارج الدفتر **صفر** · البوابة **4/4 خضراء** | `tools/repair01_migration_gate.php` |
| إصدار سجل التنقّل | `nav_items` نشط **2366** · **`nav_placements` 413** · `nav_targets` **413** · `nav_workspaces` **22** · `nav_lifecycle_groups` **118** · `gov_legacy_nav_recon` **725** · `gov_nav_findings` **2** (بعد ضبطِ تفرّدِ المسجِّل) | قياس حي |
| إصدار سجل الشاشات | `repair01_screen_registry` **814** (+`SCR-0817`) · `repair01_requirements` **434** (+WH-03) | قياس حي |
| الناقل | `ems_business_events` **21,555** · `ems_event_deliveries` **21,349** (بعد حجر 10 صفوف بذر UAT بقيدها) · `EFFECT_MISSING` **0** | قياس حي |
| تمرين التثبيت | **`DR-2026-0009` pass** على المخطَّط الحالي (1025 كائنًا) | `dr_drills` |
| ثبات اللقطة | `HEAD` لم يتغيّر أثناء التشغيلة الحاكمة | `git rev-parse HEAD` قبل/بعد |

## حزمةُ الأدلّةِ الموحَّدة (GATE-00)

الوثائقُ الثماني — كلُّها بمعرِّف `BL-20260901-15ba9f3c`:

| الوثيقة | الموضع |
|---|---|
| NAVR_ROOT_AUDIT | `docs/REPAIR01_20260823/NAVR_ROOT_AUDIT.md` |
| NAVR_PATTERN_SCAN | `docs/REPAIR01_20260823/NAVR_PATTERN_SCAN.md` |
| NAVR_METRICS | `docs/REPAIR01_20260823/NAVR_METRICS.md` |
| SIDEBAR_GUIDE_COMPARE | `docs/REPAIR01_20260823/SIDEBAR_GUIDE_COMPARE.md` |
| SIDEBAR_GUIDE_WHY | `docs/REPAIR01_20260823/SIDEBAR_GUIDE_WHY.md` |
| CURRENT_STATUS | `docs/baseline_20260821/INJ-CURRENT-STATUS_ar.md` |
| FINDINGS | `docs/baseline_20260821/FINDINGS.md` |
| AS_BUILT | `docs/baseline_20260821/INJ-ARCH-ASBUILT_ar.md` |

⛔ **وأيُّ رقمٍ في وثائقِ الحالةِ الثلاثِ الأخيرةِ لم يُعَد قياسُه في هذه الحزمةِ
يُقرأ `HISTORICAL` بنصِّ رأسِ وثيقتِه** — والحاضرُ الحاكمُ للملاحةِ
`NAVR_METRICS.md` وللبرنامجِ `registers/MASTER_FINAL_CLOSURE_REGISTER.json`.
