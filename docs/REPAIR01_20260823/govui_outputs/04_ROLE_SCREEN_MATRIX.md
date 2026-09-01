# ④ `Role-Screen Matrix`

> **لقطةٌ واحدةٌ موحَّدة** — الالتزام `3dcd5b78` · `Snapshot_ID` `SNAP-06237f66-20260901-180159` · `Baseline_ID` **BL-20260901-3dcd5b78** · 2026-09-01 19:39
> ⛔ **وكلُّ رقمٍ مقروءٌ من أداتِه في هذه التشغيلةِ** — لا نقلَ من تقريرٍ سابق.

> ⚠ **ختمُ اللقطةِ مُمتنِعٌ بحاجزٍ مسمًّى**: `repair01_freeze.php` يشترط شجرةً نظيفة،
> وفي الشجرةِ عملُ **جلسةٍ أخرى غيرُ ملتزَم** (`tools/dashv2_measure.php` وذاكرتُه) —
> ولا أحذف عملَ غيري ولا ألتزمه. **فالمخرجاتُ العشرةُ كلُّها من التزامٍ واحدٍ
> وتشغيلةٍ واحدة** (الخاصّةُ بها في الرأس)، والختمُ الرسميُّ يُؤخَذ متى خلت الشجرة.
> وقياسُ الحقولِ المخزَّنُ **2570/5460** والحيُّ **2580/5470** — والفارقُ عشرةُ حقولٍ
> من سطحٍ بُني بعد آخرِ تثبيت، **وتثبيتُه يحتاج النافذةَ نفسَها المحجوبة**.

**§12**: `Role ← Screen ← Action ← Field` — والمقيسُ هنا **الظهورُ المُصيَّرُ** وحارسُ البابِ المباشر.

## حارسُ البابِ المباشرِ (`Direct URL`) لكلِّ هدفٍ مبنيّ

| صنفُ الحارس | العدد |
|---|---|
| `SELF_EARLY` | 235 |
| `SHELL` | 135 |
| `PAGE_PERM` | 24 |
| `VIA_KIT` | 19 |
| **المجموع** | **413** |

⇒ **`DIRECT_URL_UNGUARDED = 0`** — لا هدفَ مبنيٌّ بلا حارسٍ خادميٍّ مصرَّحٍ في سجلِّ الشاشات.

## الدورُ لكلِّ مساحةٍ وما يراه

| المساحة | الدورُ الحيّ | أهدافٌ مبنيّة | مُصيَّرةٌ لدورِه | الحكم |
|---|---|---|---|---|
| `DEP-01` | ادارة المبيعات | 20 | 18 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-02` | ادارة الموردين | 31 | 30 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-03` | إدارة التمويل | 25 | 25 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-04` | ادارة الاسطول | 37 | 37 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-05` | إدارة المالية | 25 | 25 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-06` | أمين الخزينة | 18 | 18 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-07` | ادارة الموارد البشرية | 24 | 24 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-08` | BLOCKED_ROLE_BINDING | 32 | 0 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-09` | إدارة المخاطر | 13 | 13 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-10` | إدارة البلاغات | 13 | 13 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-11` | ادارة التشغيل | 12 | 12 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-12` | إدارة الموقع | 19 | 19 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-13` | القوى التشغيلية | 17 | 17 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-14` | ادارة الصيانة | 17 | 17 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-15` | إدارة النقل والترحيل | 13 | 13 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-16` | إدارة المشتريات | 16 | 16 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `DEP-17` | أمين المستودع | 19 | 18 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `EX-CEO` | الإدارة التنفيذية | 26 | 26 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `EX-DVP` | BLOCKED_ROLE_BINDING | 12 | 0 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `IAF` | المراجع الداخلي المستقل | 17 | 17 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
| `WS-MY` | — لا يُصيَّر لدورِ مساحتِه | 7 | 6 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
