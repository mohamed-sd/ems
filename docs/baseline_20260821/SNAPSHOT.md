# BL-20260831c — نقطة القياس المثبَّتة (Snapshot)

**معرّف اللقطة:** `BL-20260831c-f592cdf9` — كل رقم في مخرجات هذه الحزمة مقيسٌ على هذه اللقطة وحدها.
**اللقطات الثماني السابقة** في [`historical/`](historical/) — **أرقامها تاريخ ولا تُقرأ حاضرًا** (آخرها `BL-20260831-52f4fe37` — لقطة صباح اليوم).

> **حزمةُ GATE-00 الذرّيّة**: هذه اللقطةُ قُصَّت تنفيذًا لأمرِ الحوكمةِ الموحَّد —
> **تشغيلٌ حاكمٌ واحدٌ** على `HEAD=f592cdf9` (شجرةٌ نظيفةٌ قبل القياس) أعاد توليدَ:
> الاستخراجات (`db_dump` · `db_dump2` · `db_dump4` · `disk_scan` · `reconcile` ·
> `field_extract`) ثم `SIDEBAR_GUIDE_COMPARE` + `NAVR_METRICS` — وكلُّ وثائقِ
> الحزمةِ الثمانيةِ تحمل معرِّفَ اللقطةِ هذا (انظر [`GATE00_BASELINE.md`](../REPAIR01_20260823/GATE00_BASELINE.md)).
>
> **وثلاثُ نتائجَ تُقرأ قبل أيِّ رقم** (دلتا اليومِ الواحدِ 52f4fe37 ⇒ f592cdf9 · **19 التزامًا**):
> ① **حملةُ CLOSURE_SYSTEM**: السجلُّ الجامعُ أُسّس (173 بندًا · 107 مغلقًا بالدليل) ·
>   `GAP-10` أُغلقت والقنواتُ التسع 15/15 · `EFFECT_MISSING=0` نمطًا · الحزامُ 25/34 ⇒ 33/35.
> ② **حملةُ NAVR**: نموذجُ الملاحةِ أُعيد بناؤه — 5 جداولَ جديدة (`nav_workspaces` ·
>   `nav_ws_roles` · `nav_lifecycle_groups` · `nav_placements` · `gov_nav_findings`) ·
>   374 موضعًا من ورقةِ الدليل · **صفرُ موضعٍ مبنيٍّ غيرِ مُصيَّر** · `GLOBAL_FALLBACK=0`.
> ③ **تمرينا تثبيتٍ جديدان** (`DR-2026-0005` · `DR-2026-0006`) على المخطَّطِ الحاليِّ — مطابقةٌ تامّة.

## نقطة القياس

| البند | القيمة | الدليل |
|---|---|---|
| تاريخ/وقت القياس | **2026-08-31 · 20:40 → 20:50** (UTC+03) | طوابع الاستخراج |
| Environment | تطوير محلي — WAMP على Windows 11 Pro، `C:\wamp64\www\ems` | بيئة الجلسة |
| PHP | **8.2.30** (أدوات القياس) · **8.3.28** (جلسة التنفيذ) | `php -v` |
| قاعدة البيانات | MariaDB **11.4.9-log** · `equipation_manage` · المنفَذ **3307** | `SELECT VERSION()` |
| Branch | **`repair01/w01-ownership`** — **الدفعُ ما زال محجوبًا بيئيًّا** (مفتاح SSH بعبارة مرور) | `git branch` · محاولة push |
| commit_hash | **`f592cdf9`** — «NAVR ③ فض اقفال CONFORM» | `git rev-parse HEAD` |
| التزامات غير مدفوعة | **315 تجاه `origin/main`** · **19 التزامًا منذ لقطة الصباح** | `git rev-list --count` |
| الوسوم | **20** | `git tag` |
| **الشجرة العاملة** | ✔ **نظيفة عند بدء القياس** — والمتغيّر بعده مخرجات التشغيلة الحاكمة نفسها | `git status --porcelain` |
| إصدار الـSchema | **993 جدولًا أساسيًّا** (+6 منذ الصباح: 5 ملاحة + حجر المفتاح) + 28 منظرًا · CHECK **920** · UNIQUE **586** · FK **315** | information_schema |
| **دفتر الهجرات** | `schema_migrations` **812 صفًّا** (+4) · خارج الدفتر **صفر** · البوابة **4/4 خضراء** | `tools/repair01_migration_gate.php` |
| إصدار سجل التنقّل | `nav_items` نشط **2344** · **`nav_placements` 374** (جديدة) · `nav_workspaces` **22** · `nav_lifecycle_groups` **110** · `gov_nav_findings` **8** | قياس حي |
| إصدار سجل الشاشات | `repair01_screen_registry` **813** | قياس حي |
| الناقل | `ems_business_events` **21,555** · `ems_event_deliveries` **21,349** (بعد حجر 10 صفوف بذر UAT بقيدها) · `EFFECT_MISSING` **0** | قياس حي |
| تمرين التثبيت | **`DR-2026-0006` pass** على المخطَّط الحالي (987 دفعة التوليد · 1021 كائنًا) | `dr_drills` |
| ثبات اللقطة | `HEAD` لم يتغيّر أثناء التشغيلة الحاكمة | `git rev-parse HEAD` قبل/بعد |

## حزمةُ الأدلّةِ الموحَّدة (GATE-00)

الوثائقُ الثماني — كلُّها بمعرِّف `BL-20260831c-f592cdf9`:

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
