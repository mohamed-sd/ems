# الترحيلات — ما بعد خطِّ الأساس

هذا المجلَّدُ **يبدأ فارغًا عمدًا**.

كلُّ ما سبق 2026-08-02 مضغوطٌ في `database/schema/schema.sql` ومُؤرشَفٌ في
`database/_history/`. ما يُوضع هنا من الآن فصاعدًا هو **تغييراتُ المخطَّط على
قاعدةٍ عاملة** — ما لا يستطيع المُثبِّتُ فعلَه لأنّ فيها بياناتٍ حيّة.

## متى تضيف ملفًّا هنا

حين يتغيّر المخطَّطُ بعد أن صار النظامُ يعمل عند أحدٍ ما. عمودٌ جديد، فهرسٌ،
جدولٌ، تعديلُ `ENUM`.

## الدورةُ كاملة

```bash
# ① اكتب الملف: database/migrations/YYYY_MM_DD_slug.sql
# ② طبّقه على قاعدة التطوير
php database/migrate.php up

# ③ حدّث مصنوعات المُثبِّت — وإلّا صار مُثبِّتُك متخلّفًا عن قاعدتك
php database/migrate.php dump-schema

# ④ التزم الاثنين معًا: الترحيل + database/schema/
```

الخطوةُ ③ ليست اختياريّة. حارسُ الانحراف `tools/schema_drift.php` موجودٌ
تحديدًا ليكشف نسيانَها.

## العُرف

- الاسم: `YYYY_MM_DD_slug.sql` (أو `.php` لما يحتاج منطقًا).
- idempotent: النمطُ الحارسُ عبر `information_schema` + `PREPARE` — فـMySQL
  لا يدعم `ADD COLUMN IF NOT EXISTS`.
- `DELIMITER` يفشل عبر `multi_query` — الإجراءاتُ المخزَّنة تُكتب `.php`.
- عميلُ `utf8mb4` إلزامٌ لقيم `ENUM` العربية.

الدليل: [`docs/MIGRATIONS_GUIDE_ar.md`](../../docs/MIGRATIONS_GUIDE_ar.md) ·
التثبيت: [`docs/INSTALL_ar.md`](../../docs/INSTALL_ar.md)
