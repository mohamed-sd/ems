# `MIGRATION_SETTLEMENT` — تسويةُ الهجراتِ غيرِ المصالَحةِ بأدلّتِها

> **مولَّدٌ من تشغيلٍ حيٍّ** بالسطر: `php tools/repair01_migration_settle.php --apply --md`

| المفردة | القيمة |
|---|---|
| `Commit Hash` | `b5a2cc7f14c61abcef4790fa6343d716b04500fd` |
| `Schema Version` | 953T |
| `Registry Version` | 783 |
| `Measured At` | 2026-08-28 13:41:47 |
| `Tool Version` | `repair01_migration_settle.php v1.0` |
| `Snapshot ID` | `UNFROZEN` |
| الوضع | **تطبيقٌ — يكتب الأحكامَ والدفتر** |

## ١ · الأحكامُ بمقاماتِها

| الحكم | العدد | متحقَّقٌ منه |
|---|---|---|
| `APPLIED_VERIFIED_BY_DATA` | 4 | 4/4 |
| `APPLIED_VERIFIED_BY_SCHEMA` | 29 | 29/29 |
| `NOT_A_MIGRATION` | 1 | 1/1 |
| `ROLLBACK_SCRIPT_NOT_APPLIED` | 24 | 24/24 |
| `SUPERSEDED_BY_INSTALLER_SQUASH` | 241 | 241/241 |
| **الإجمالي** | **299** | **299/299** |

## ٢ · ما لم يُتحقَّق منه

**لا شيء — كلُّ حكمٍ يحمل دليلَه المقيس.**

## ٣ · خطرٌ حيٌّ مُسمًّى — سكربتاتُ التراجعِ في طابورِ المُشغِّل

`database/migrate.php::cmd_up()` **يطابق `_down.php` بعُرفِ التسميةِ ولا يستثنيه**،
و**24** سكربتَ تراجعٍ خارجَ الدفترِ ⇒ **كلُّها في طابورِ `up` الآن**.
وتسويتُها `baseline` **تُخرجها من الطابورِ فعلًا** — ⛔ لا نصحًا في وثيقة.

## ٤ · التطبيق

- أحكامٌ كُتبت في `gov_migration_settlement`: **299/299**
- صفوفٌ قُيِّدت في `schema_migrations`: **57**

