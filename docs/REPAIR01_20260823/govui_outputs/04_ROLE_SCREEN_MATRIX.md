# ④ `Role-Screen Matrix`

> **لقطةٌ واحدةٌ موحَّدة** — الالتزام `9c39e08a` · `Snapshot_ID` `SNAP-cd953373-20260901-143349` · `Baseline_ID` **BL-20260901-9c39e08a** · 2026-09-01 17:07
> ⛔ **وكلُّ رقمٍ مقروءٌ من أداتِه في هذه التشغيلةِ** — لا نقلَ من تقريرٍ سابق.

**§12**: `Role ← Screen ← Action ← Field` — والمقيسُ هنا **الظهورُ المُصيَّرُ** وحارسُ البابِ المباشر.

## حارسُ البابِ المباشرِ (`Direct URL`) لكلِّ هدفٍ مبنيّ

| صنفُ الحارس | العدد |
|---|---|
| `SELF_EARLY` | 234 |
| `SHELL` | 135 |
| `PAGE_PERM` | 24 |
| `VIA_KIT` | 19 |
| **المجموع** | **412** |

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
| `DEP-07` | ادارة الموارد البشرية | 23 | 23 | ✔ `ROLE_VISIBILITY_MISMATCH = 0` |
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
