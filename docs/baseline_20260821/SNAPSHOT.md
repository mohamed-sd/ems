# BL-20260831d — نقطة القياس المثبَّتة (Snapshot)

**معرّف اللقطة:** `BL-20260831d-aba54573` — كل رقم في مخرجات هذه الحزمة مقيسٌ على هذه اللقطة وحدها.
**اللقطات الثماني السابقة** في [`historical/`](historical/) — **أرقامها تاريخ ولا تُقرأ حاضرًا** (آخرها `BL-20260831-52f4fe37` — لقطة صباح اليوم).

> **حزمةُ GATE-00 الذرّيّة**: هذه اللقطةُ قُصَّت تنفيذًا لأمرِ الحوكمةِ الموحَّد —
> **تشغيلٌ حاكمٌ واحدٌ** على `HEAD=aba54573` (شجرةٌ نظيفةٌ قبل القياس — أُعيد قصُّها بعد اكتمالِ NAVR البنيويِّ لأنَّ حزمةَ f592cdf9 انزاحت بأربعةِ التزاماتِ الإكمال) أعاد توليدَ:
> الاستخراجات (`db_dump` · `db_dump2` · `db_dump4` · `disk_scan` · `reconcile` ·
> `field_extract`) ثم `SIDEBAR_GUIDE_COMPARE` + `NAVR_METRICS` — وكلُّ وثائقِ
> الحزمةِ الثمانيةِ تحمل معرِّفَ اللقطةِ هذا (انظر [`GATE00_BASELINE.md`](../REPAIR01_20260823/GATE00_BASELINE.md)).
>
> **وأربعُ نتائجَ تُقرأ قبل أيِّ رقم** (دلتا اليومِ الواحدِ 52f4fe37 ⇒ aba54573 · **23 التزامًا**):
> ① **حملةُ CLOSURE_SYSTEM**: السجلُّ الجامعُ أُسّس (173 بندًا · 107 مغلقًا بالدليل) ·
>   `GAP-10` أُغلقت والقنواتُ التسع 15/15 · `EFFECT_MISSING=0` نمطًا · الحزامُ 25/34 ⇒ 33/35.
> ② **حملةُ NAVR**: نموذجُ الملاحةِ أُعيد بناؤه — 5 جداولَ جديدة (`nav_workspaces` ·
>   `nav_ws_roles` · `nav_lifecycle_groups` · `nav_placements` · `gov_nav_findings`) ·
>   412 موضعًا من ورقتَي الدليلِ والقيادة · **صفرُ موضعٍ مبنيٍّ غيرِ مُصيَّر** · `GLOBAL_FALLBACK=0`.
> ③ **الاكتمالُ البنيويُّ الشامل**: غيرُ مطابقٍ=0 — `STRUCTURAL_NAV_PASS=17/17` + EX-CEO + WS-MY · تصيير/مجموعات/ترتيب **182/182** · ومصالحةُ الإرثِ 725/725 والنسبُ بمعادلتِها.
> ④ **ثلاثةُ تمارينِ تثبيتٍ** (`DR-2026-0005..0007`) على المخطَّطِ الحاليِّ — مطابقةٌ تامّة.

## نقطة القياس

| البند | القيمة | الدليل |
|---|---|---|
| تاريخ/وقت القياس | **2026-08-31 · 21:50 → 21:56** (UTC+03) | طوابع الاستخراج |
| Environment | تطوير محلي — WAMP على Windows 11 Pro، `C:\wamp64\www\ems` | بيئة الجلسة |
| PHP | **8.2.30** (أدوات القياس) · **8.3.28** (جلسة التنفيذ) | `php -v` |
| قاعدة البيانات | MariaDB **11.4.9-log** · `equipation_manage` · المنفَذ **3307** | `SELECT VERSION()` |
| Branch | **`repair01/w01-ownership`** — **الدفعُ ما زال محجوبًا بيئيًّا** (مفتاح SSH بعبارة مرور) | `git branch` · محاولة push |
| commit_hash | **`aba54573`** — «NAVR ⑥ الاكتمال البنيوي الشامل» | `git rev-parse HEAD` |
| التزامات غير مدفوعة | **319 تجاه `origin/main`** · **23 التزامًا منذ لقطة الصباح** | `git rev-list --count` |
| الوسوم | **20** | `git tag` |
| **الشجرة العاملة** | ✔ **نظيفة عند بدء القياس** — والمتغيّر بعده مخرجات التشغيلة الحاكمة نفسها | `git status --porcelain` |
| إصدار الـSchema | **995 جدولًا أساسيًّا** (+8 منذ الصباح: 7 ملاحة/حوكمة + حجر المفتاح) + 28 منظرًا | information_schema |
| **دفتر الهجرات** | `schema_migrations` **816 صفًّا** (+8) · خارج الدفتر **صفر** · البوابة **4/4 خضراء** | `tools/repair01_migration_gate.php` |
| إصدار سجل التنقّل | `nav_items` نشط **2366** · **`nav_placements` 412** · `nav_targets` **412** · `nav_workspaces` **22** · `nav_lifecycle_groups` **118** · `gov_legacy_nav_recon` **725** · `gov_nav_findings` **11** | قياس حي |
| إصدار سجل الشاشات | `repair01_screen_registry` **813** | قياس حي |
| الناقل | `ems_business_events` **21,555** · `ems_event_deliveries` **21,349** (بعد حجر 10 صفوف بذر UAT بقيدها) · `EFFECT_MISSING` **0** | قياس حي |
| تمرين التثبيت | **`DR-2026-0007` pass** على المخطَّط الحالي (1023 كائنًا) | `dr_drills` |
| ثبات اللقطة | `HEAD` لم يتغيّر أثناء التشغيلة الحاكمة | `git rev-parse HEAD` قبل/بعد |

## حزمةُ الأدلّةِ الموحَّدة (GATE-00)

الوثائقُ الثماني — كلُّها بمعرِّف `BL-20260831d-aba54573`:

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
