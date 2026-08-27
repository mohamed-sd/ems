# تقريرُ الانحدارِ الشامل — البندُ ⑧

> ⛔ **مولَّدٌ من التشغيلِ الحيّ**: `php tools/repair01_regression_run.php --md`

## بصمةُ اللقطة (‏البند ⑬)

| الحقل | القيمة |
|---|---|
| `Snapshot ID` | `SNAP-e5e2d768-20260827135048` |
| `Commit Hash` | `e5e2d7680204db324431b5cbd9110dcff69ff928` |
| `Branch` | `repair01/w01-ownership` |
| `Schema Version` | `916T/14528C` |
| `Registry Version` | 783 صفًّا |
| `Measured At` | 2026-08-27 13:50:48 ← 2026-08-27 13:52:42 |
| `Tool Version` | `repair01_regression_run.php` |
| **ثباتُ البصمة** | ✔ ثابتةٌ من البدءِ إلى الختام |

**نجح 24 · رسب 0 · عطبٌ في التشغيل 0 · المجموع 24**


## بوّاباتُ الموجات

| الأداة | الحكم | الدليل |
|---|---|---|
| `tools/repair01_w0_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w10_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w11_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w12_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w135_gate.php` | ✔ | الحكم: **عبرت السبعةُ — والمرحلةُ الرابعةَ عشرةَ تبدأ** ✔ |
| `tools/repair01_w13_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w14_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w15_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w1_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w2_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w3_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w4_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w5_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w6_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w7_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w8_gate.php` | ✔ | الحكم: خضراء ✔ |
| `tools/repair01_w9_gate.php` | ✔ | الحكم: مُغلَقة ✔  ·  DEC-OPEN-15 مُجابٌ ومُغلَق · وبنودُ التأجيلِ الثلاثةُ استُهلكت بإثباتٍ مقيس |

## حواجبُ الحملة

| الأداة | الحكم | الدليل |
|---|---|---|
| `tools/repair01_w135_gate.php` | ✔ | الحكم: **عبرت السبعةُ — والمرحلةُ الرابعةَ عشرةَ تبدأ** ✔ |

## سقّاطاتُ الدَّين

| الأداة | الحكم | الدليل |
|---|---|---|
| `tools/u12_debt_ratchet.php` | ✔ | 🟢 السقّاطةُ سليمة — لا دَينَ زاد. وهذا هو إنفاذُ L4 لـUI-DEF-11 وUI-DEF-12. |

## حواجبُ الواجهة

| الأداة | الحكم | الدليل |
|---|---|---|
| `tools/uxw_gates.php` | ✔ | ✔ البواباتُ مجتازةٌ على النطاقِ المرحَّلِ الحالي (472 شاشة) |

## شواهدُ W13.5

| الأداة | الحكم | الدليل |
|---|---|---|
| `tests/w135_expired_delegation_denied.php` | ✔ | الحكم: التفويضُ المنتهي لا يمنح سلطةً — والحيُّ يمنحها ✔ |
| `tests/w135_technical_token_in_ui.php` | ✔ | النتيجة: 6 نجاح · 0 رسوب |
| `tests/w135_vendor_not_a_screen.php` | ✔ | النتيجة: 8 نجاح · 0 رسوب |

## شواهدُ الحملة

| الأداة | الحكم | الدليل |
|---|---|---|
| `tests/edc_dead_vocab_not_a_zero.php` | ✔ | النتيجة: 7 نجاح · 0 رسوب |
